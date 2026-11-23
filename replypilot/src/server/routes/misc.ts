import express, { Router } from "express";
import { z } from "zod";
import { DEMO_APP_SECRET, config, webhookSecrets } from "../../config.js";
import { one, query } from "../../db/pool.js";
import { randomToken } from "../../lib/crypto.js";
import { signBody } from "../../lib/meta/webhook.js";
import { getQueue, getRedis, hitCounter } from "../../lib/redis.js";
import { SKIP_LABELS } from "../../lib/restrictions.js";
import { hashIp, isRecipientToken } from "../../lib/templating.js";
import { requireAuth, requireRole } from "../auth.js";
import { ingestWebhook } from "../ingest.js";
import { clientIp, isUuid } from "../util.js";

// ---------------- Webhook (mounted before JSON body parsing: needs the raw body) ----------------

export const webhookRouter = Router();

webhookRouter.get("/api/webhook", (req, res) => {
  const mode = req.query["hub.mode"];
  const token = req.query["hub.verify_token"];
  const challenge = req.query["hub.challenge"];
  if (mode === "subscribe" && typeof token === "string" && token === config().webhookVerifyToken && typeof challenge === "string") {
    return res.status(200).type("text/plain").send(challenge);
  }
  res.status(403).json({ error: "Verification failed" });
});

webhookRouter.post("/api/webhook", express.raw({ type: "*/*", limit: "1mb" }), async (req, res) => {
  const body = Buffer.isBuffer(req.body) ? req.body : Buffer.from("");
  const r = await ingestWebhook(body, req.get("x-hub-signature-256"));
  res.status(r.status).json(r.body);
});

// ---------------- Tracked link redirect ----------------

export const redirectRouter = Router();

redirectRouter.get("/r/:slug", async (req, res) => {
  const link = await one<{ id: string; workspace_id: string; campaign_id: string; destination_url: string }>(
    `SELECT id, workspace_id, campaign_id, destination_url FROM tracked_links WHERE slug=$1`,
    [req.params.slug]
  );
  if (!link) return res.status(404).send("Link not found");
  const secret = config().sessionSecret;
  const ua = req.get("user-agent") ?? "";
  // Skip link-preview bots so they don't inflate clicks.
  const isBot = /facebookexternalhit|bot|crawler|spider|preview/i.test(ua);
  const ip = clientIp(req);
  if (!isBot && (await hitCounter(`click:${link.id}:${ip}`, 10)) <= 3) {
    await query(
      `INSERT INTO link_clicks(workspace_id, campaign_id, tracked_link_id, recipient_hash, ip_hash, user_agent) VALUES ($1,$2,$3,$4,$5,$6)`,
      [link.workspace_id, link.campaign_id, link.id, isRecipientToken(req.query.r) ? req.query.r : null, hashIp(ip, secret), ua.slice(0, 300)]
    );
  }
  res.redirect(302, link.destination_url);
});

// ---------------- Authenticated API ----------------

export const apiRouter = Router();

apiRouter.get("/logs", requireAuth, async (req, res) => {
  const page = Math.max(1, Number(req.query.page) || 1);
  const pageSize = 50;
  const params: unknown[] = [req.auth!.workspaceId];
  let where = "l.workspace_id=$1";
  if (isUuid(req.query.campaignId)) {
    params.push(req.query.campaignId);
    where += ` AND l.campaign_id=$${params.length}`;
  }
  if (typeof req.query.status === "string" && ["pending", "sent", "failed", "skipped"].includes(req.query.status)) {
    params.push(req.query.status);
    where += ` AND l.status=$${params.length}`;
  }
  if (typeof req.query.q === "string" && req.query.q.trim()) {
    params.push(`%${req.query.q.trim()}%`);
    where += ` AND (l.sender_username ILIKE $${params.length} OR l.incoming_text ILIKE $${params.length})`;
  }
  const total = (await one<{ n: number }>(`SELECT count(*)::int AS n FROM message_logs l WHERE ${where}`, params))!.n;
  params.push(pageSize, (page - 1) * pageSize);
  const rows = await query(
    `SELECT l.*, c.name AS campaign_name FROM message_logs l JOIN campaigns c ON c.id=l.campaign_id
      WHERE ${where} ORDER BY l.created_at DESC LIMIT $${params.length - 1} OFFSET $${params.length}`,
    params
  );
  res.json({
    total,
    page,
    pageSize,
    rows: rows.map((r) => ({ ...r, skip_label: r.skip_reason ? SKIP_LABELS[r.skip_reason as keyof typeof SKIP_LABELS] ?? r.skip_reason : null })),
  });
});

apiRouter.get("/analytics/overview", requireAuth, async (req, res) => {
  const days = Math.min(90, Math.max(1, Number(req.query.days) || 30));
  const ws = req.auth!.workspaceId;
  const totals = await one(
    `SELECT
       (SELECT count(*) FROM message_logs WHERE workspace_id=$1 AND created_at > now() - $2 * interval '1 day')::int AS triggered,
       (SELECT count(*) FROM message_logs WHERE workspace_id=$1 AND status='sent' AND sent_at > now() - $2 * interval '1 day')::int AS sent,
       (SELECT count(*) FROM message_logs WHERE workspace_id=$1 AND status='failed' AND created_at > now() - $2 * interval '1 day')::int AS failed,
       (SELECT count(*) FROM message_logs WHERE workspace_id=$1 AND status='skipped' AND created_at > now() - $2 * interval '1 day')::int AS skipped,
       (SELECT count(*) FROM message_logs WHERE workspace_id=$1 AND public_reply_status='sent' AND created_at > now() - $2 * interval '1 day')::int AS public_replies,
       (SELECT count(*) FROM link_clicks WHERE workspace_id=$1 AND created_at > now() - $2 * interval '1 day')::int AS clicks,
       (SELECT count(DISTINCT COALESCE(recipient_hash, ip_hash)) FROM link_clicks WHERE workspace_id=$1 AND created_at > now() - $2 * interval '1 day')::int AS unique_clicks,
       (SELECT count(*) FROM campaigns WHERE workspace_id=$1 AND is_active)::int AS active_campaigns,
       (SELECT count(*) FROM instagram_accounts WHERE workspace_id=$1)::int AS accounts`,
    [ws, days]
  );
  const daily = await query(
    `WITH days AS (SELECT generate_series(current_date - ($2::int - 1), current_date, interval '1 day')::date AS d)
     SELECT to_char(d,'YYYY-MM-DD') AS date,
       (SELECT count(*) FROM message_logs l WHERE l.workspace_id=$1 AND l.status='sent' AND l.sent_at::date=d)::int AS sent,
       (SELECT count(*) FROM link_clicks k WHERE k.workspace_id=$1 AND k.created_at::date=d)::int AS clicks
     FROM days ORDER BY d`,
    [ws, days]
  );
  const byTrigger = await query(
    `SELECT trigger, count(*)::int AS count FROM message_logs WHERE workspace_id=$1 AND created_at > now() - $2 * interval '1 day' GROUP BY trigger`,
    [ws, days]
  );
  res.json({ days, totals, daily, byTrigger });
});

apiRouter.get("/webhook-events", requireRole("owner", "admin"), async (_req, res) => {
  // Webhook events are global (Meta sends one body per app); show only metadata, not other workspaces' payloads.
  res.json(
    await query(`SELECT id, object, status, jobs_enqueued, error, created_at FROM webhook_events ORDER BY created_at DESC LIMIT 50`)
  );
});

apiRouter.get("/health", async (_req, res) => {
  const out: Record<string, unknown> = { demoMode: config().demoMode };
  let healthy = true;
  try {
    await one("SELECT 1");
    out.database = "ok";
  } catch (e) {
    out.database = (e as Error).message;
    healthy = false;
  }
  try {
    await getRedis().ping();
    out.redis = "ok";
    const hb = Number(await getRedis().get("rp:worker:heartbeat"));
    out.worker = hb && Date.now() - hb < 90_000 ? { healthy: true, lastSeen: new Date(hb).toISOString() } : { healthy: false };
    out.queue = await getQueue().getJobCounts("waiting", "active", "delayed", "failed", "completed");
  } catch (e) {
    out.redis = (e as Error).message;
    healthy = false;
  }
  out.status = healthy ? "ok" : "degraded";
  res.status(healthy ? 200 : 503).json(out);
});

// ---------------- Demo mode ----------------

const simulateSchema = z.object({
  accountId: z.string().uuid(),
  type: z.enum(["comment", "dm", "story_reply"]),
  text: z.string().max(1000).default(""),
  mediaId: z.string().max(100).optional(),
  senderUsername: z.string().trim().max(30).optional(),
  senderId: z.string().max(40).optional(),
});

/**
 * Builds a webhook body exactly like Meta's, signs it with the app secret and
 * feeds it through the same ingestion path as a real delivery.
 */
apiRouter.post("/demo/simulate", requireAuth, async (req, res) => {
  if (!config().demoMode) return res.status(404).json({ error: "Demo mode is off" });
  const p = simulateSchema.safeParse(req.body);
  if (!p.success) return res.status(400).json({ error: p.error.issues[0].message });
  const v = p.data;
  const acct = await one<{ ig_user_id: string; login_type: string }>(
    `SELECT ig_user_id, login_type FROM instagram_accounts WHERE id=$1 AND workspace_id=$2`,
    [v.accountId, req.auth!.workspaceId]
  );
  if (!acct) return res.status(404).json({ error: "Account not found" });
  if (acct.login_type !== "demo") return res.status(400).json({ error: "Simulation only works with demo accounts" });
  const senderId = v.senderId || `9${Math.floor(Math.random() * 1e15)}`;
  const now = Date.now();
  const entry: Record<string, unknown> = { id: acct.ig_user_id, time: Math.floor(now / 1000) };
  if (v.type === "comment") {
    entry.changes = [
      {
        field: "comments",
        value: {
          id: `demo_c_${randomToken(9)}`,
          text: v.text,
          from: { id: senderId, username: v.senderUsername || "demo_follower" },
          media: { id: v.mediaId || "demo_media_1", media_product_type: "FEED" },
        },
      },
    ];
  } else {
    entry.messaging = [
      {
        sender: { id: senderId },
        recipient: { id: acct.ig_user_id },
        timestamp: now,
        message: {
          mid: `demo_m_${randomToken(12)}`,
          text: v.text,
          ...(v.type === "story_reply" ? { reply_to: { story: { id: "demo_story_1", url: "https://example.com/story" } } } : {}),
        },
      },
    ];
  }
  const raw = JSON.stringify({ object: "instagram", entry: [entry] });
  const secret = webhookSecrets()[0] ?? DEMO_APP_SECRET;
  const r = await ingestWebhook(raw, signBody(raw, secret));
  res.status(r.status).json({ ...r.body, senderId });
});

apiRouter.get("/demo/outbox", requireAuth, async (req, res) => {
  if (!config().demoMode) return res.status(404).json({ error: "Demo mode is off" });
  res.json(
    await query(
      `SELECT o.* FROM demo_outbox o JOIN instagram_accounts a ON a.id=o.instagram_account_id
        WHERE a.workspace_id=$1 ORDER BY o.created_at DESC LIMIT 50`,
      [req.auth!.workspaceId]
    )
  );
});
