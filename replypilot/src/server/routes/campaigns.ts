import { Router } from "express";
import type pg from "pg";
import { z } from "zod";
import { db, one, query } from "../../db/pool.js";
import { MAX_BUTTON_TITLE, generateSlug, isSafeHttpUrl } from "../../lib/templating.js";
import { requireAuth, requireRole } from "../auth.js";
import { isUuid } from "../util.js";

export const campaignsRouter = Router();

export const campaignSchema = z
  .object({
    name: z.string().trim().min(1, "Name is required").max(100),
    instagramAccountId: z.string().uuid("Pick an Instagram account"),
    isActive: z.boolean().default(true),
    triggerComments: z.boolean().default(true),
    triggerDms: z.boolean().default(false),
    triggerStoryReplies: z.boolean().default(false),
    postId: z.string().trim().max(100).nullish(),
    postPermalink: z.string().trim().max(500).nullish(),
    matchAnyPost: z.boolean().default(false),
    keywords: z.array(z.string().trim().min(1).max(50)).max(30).default([]),
    matchAnyWord: z.boolean().default(false),
    wholeWord: z.boolean().default(true),
    dmMessage: z.string().trim().min(1, "DM message is required").max(1000, "Instagram limits messages to 1000 characters"),
    linkUrl: z.string().trim().max(2000).nullish(),
    linkButtonLabel: z.string().trim().max(MAX_BUTTON_TITLE, `Button labels are limited to ${MAX_BUTTON_TITLE} characters`).nullish(),
    publicReplyEnabled: z.boolean().default(false),
    publicReplies: z.array(z.string().trim().min(1).max(300)).max(10).default([]),
  })
  .superRefine((v, ctx) => {
    if (!v.triggerComments && !v.triggerDms && !v.triggerStoryReplies)
      ctx.addIssue({ code: "custom", message: "Choose at least one trigger", path: ["triggerComments"] });
    if (v.triggerComments && !v.matchAnyPost && !v.postId)
      ctx.addIssue({ code: "custom", message: "Pick a post or choose 'any post'", path: ["postId"] });
    if (!v.matchAnyWord && v.keywords.length === 0)
      ctx.addIssue({ code: "custom", message: "Add at least one keyword or choose 'any message'", path: ["keywords"] });
    if (v.linkUrl && !isSafeHttpUrl(v.linkUrl))
      ctx.addIssue({ code: "custom", message: "Link must start with http:// or https://", path: ["linkUrl"] });
    if (v.linkButtonLabel && !v.linkUrl)
      ctx.addIssue({ code: "custom", message: "A button needs a link", path: ["linkButtonLabel"] });
    if (v.publicReplyEnabled && v.publicReplies.length === 0)
      ctx.addIssue({ code: "custom", message: "Add at least one public reply", path: ["publicReplies"] });
  });

type CampaignInput = z.infer<typeof campaignSchema>;

const STATS_SQL = `
  SELECT c.*, a.username AS account_username,
    (SELECT destination_url FROM tracked_links t WHERE t.campaign_id=c.id ORDER BY created_at LIMIT 1) AS tracked_destination,
    (SELECT slug FROM tracked_links t WHERE t.campaign_id=c.id ORDER BY created_at LIMIT 1) AS tracked_slug,
    COALESCE(s.triggered,0)::int AS triggered, COALESCE(s.sent,0)::int AS sent, COALESCE(s.failed,0)::int AS failed,
    COALESCE(s.skipped,0)::int AS skipped, COALESCE(s.public_replies_sent,0)::int AS public_replies_sent,
    COALESCE(k.clicks,0)::int AS clicks, COALESCE(k.unique_clicks,0)::int AS unique_clicks
  FROM campaigns c
  JOIN instagram_accounts a ON a.id=c.instagram_account_id
  LEFT JOIN LATERAL (
    SELECT count(*) AS triggered, count(*) FILTER (WHERE status='sent') AS sent,
      count(*) FILTER (WHERE status='failed') AS failed, count(*) FILTER (WHERE status='skipped') AS skipped,
      count(*) FILTER (WHERE public_reply_status='sent') AS public_replies_sent
    FROM message_logs l WHERE l.campaign_id=c.id) s ON true
  LEFT JOIN LATERAL (
    SELECT count(*) AS clicks, count(DISTINCT COALESCE(recipient_hash, ip_hash)) AS unique_clicks
    FROM link_clicks lc WHERE lc.campaign_id=c.id) k ON true
  WHERE c.workspace_id=$1`;

async function syncTrackedLink(client: pg.PoolClient, workspaceId: string, campaignId: string, url: string | null | undefined) {
  if (!url) {
    // Keep historical clicks: only drop links that have none.
    await client.query(`DELETE FROM tracked_links t WHERE campaign_id=$1 AND NOT EXISTS (SELECT 1 FROM link_clicks c WHERE c.tracked_link_id=t.id)`, [campaignId]);
    return;
  }
  const existing = await client.query(`SELECT id FROM tracked_links WHERE campaign_id=$1 ORDER BY created_at LIMIT 1`, [campaignId]);
  if (existing.rows[0]) {
    await client.query(`UPDATE tracked_links SET destination_url=$2 WHERE id=$1`, [existing.rows[0].id, url]);
  } else {
    await client.query(`INSERT INTO tracked_links(workspace_id, campaign_id, slug, destination_url) VALUES ($1,$2,$3,$4)`, [
      workspaceId,
      campaignId,
      generateSlug(),
      url,
    ]);
  }
}

function values(v: CampaignInput) {
  return [
    v.name, v.instagramAccountId, v.isActive, v.triggerComments, v.triggerDms, v.triggerStoryReplies,
    v.matchAnyPost ? null : v.postId ?? null, v.matchAnyPost ? null : v.postPermalink ?? null, v.matchAnyPost,
    [...new Set(v.keywords)], v.matchAnyWord, v.wholeWord, v.dmMessage, v.linkUrl || null, v.linkButtonLabel || null,
    v.publicReplyEnabled, v.publicReplies,
  ];
}

async function ownsAccount(workspaceId: string, accountId: string) {
  return Boolean(await one(`SELECT 1 FROM instagram_accounts WHERE id=$1 AND workspace_id=$2`, [accountId, workspaceId]));
}

campaignsRouter.get("/campaigns", requireAuth, async (req, res) => {
  res.json(await query(`${STATS_SQL} ORDER BY c.created_at DESC`, [req.auth!.workspaceId]));
});

campaignsRouter.get("/campaigns/:id", requireAuth, async (req, res) => {
  if (!isUuid(req.params.id)) return res.status(404).json({ error: "Not found" });
  const c = await one(`${STATS_SQL} AND c.id=$2`, [req.auth!.workspaceId, req.params.id]);
  if (!c) return res.status(404).json({ error: "Not found" });
  const daily = await query(
    `WITH days AS (SELECT generate_series(current_date - 29, current_date, interval '1 day')::date AS d)
     SELECT to_char(d,'YYYY-MM-DD') AS date,
       (SELECT count(*) FROM message_logs l WHERE l.campaign_id=$1 AND l.status='sent' AND l.sent_at::date=d)::int AS sent,
       (SELECT count(*) FROM link_clicks k WHERE k.campaign_id=$1 AND k.created_at::date=d)::int AS clicks
     FROM days ORDER BY d`,
    [req.params.id]
  );
  const keywords = await query(
    `SELECT matched_keyword AS keyword, count(*)::int AS count FROM message_logs WHERE campaign_id=$1 AND matched_keyword IS NOT NULL
      GROUP BY matched_keyword ORDER BY count DESC LIMIT 10`,
    [req.params.id]
  );
  res.json({ ...c, daily, keywordBreakdown: keywords });
});

campaignsRouter.post("/campaigns", requireRole("owner", "admin"), async (req, res) => {
  const parsed = campaignSchema.safeParse(req.body);
  if (!parsed.success) return res.status(400).json({ error: parsed.error.issues[0].message, issues: parsed.error.issues });
  const v = parsed.data;
  if (!(await ownsAccount(req.auth!.workspaceId, v.instagramAccountId))) return res.status(400).json({ error: "Unknown Instagram account" });
  const client = await db().connect();
  try {
    await client.query("BEGIN");
    const r = await client.query(
      `INSERT INTO campaigns(workspace_id, name, instagram_account_id, is_active, trigger_comments, trigger_dms, trigger_story_replies,
         post_id, post_permalink, match_any_post, keywords, match_any_word, whole_word, dm_message, link_url, link_button_label,
         public_reply_enabled, public_replies)
       VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,$17,$18) RETURNING id`,
      [req.auth!.workspaceId, ...values(v)]
    );
    await syncTrackedLink(client, req.auth!.workspaceId, r.rows[0].id, v.linkUrl);
    await client.query("COMMIT");
    res.status(201).json({ id: r.rows[0].id });
  } catch (e) {
    await client.query("ROLLBACK");
    throw e;
  } finally {
    client.release();
  }
});

campaignsRouter.put("/campaigns/:id", requireRole("owner", "admin"), async (req, res) => {
  if (!isUuid(req.params.id)) return res.status(404).json({ error: "Not found" });
  const parsed = campaignSchema.safeParse(req.body);
  if (!parsed.success) return res.status(400).json({ error: parsed.error.issues[0].message, issues: parsed.error.issues });
  const v = parsed.data;
  if (!(await ownsAccount(req.auth!.workspaceId, v.instagramAccountId))) return res.status(400).json({ error: "Unknown Instagram account" });
  const client = await db().connect();
  try {
    await client.query("BEGIN");
    const r = await client.query(
      `UPDATE campaigns SET name=$3, instagram_account_id=$4, is_active=$5, trigger_comments=$6, trigger_dms=$7, trigger_story_replies=$8,
         post_id=$9, post_permalink=$10, match_any_post=$11, keywords=$12, match_any_word=$13, whole_word=$14, dm_message=$15,
         link_url=$16, link_button_label=$17, public_reply_enabled=$18, public_replies=$19, updated_at=now()
       WHERE id=$1 AND workspace_id=$2 RETURNING id`,
      [req.params.id, req.auth!.workspaceId, ...values(v)]
    );
    if (!r.rows.length) {
      await client.query("ROLLBACK");
      return res.status(404).json({ error: "Not found" });
    }
    await syncTrackedLink(client, req.auth!.workspaceId, req.params.id, v.linkUrl);
    await client.query("COMMIT");
    res.json({ id: req.params.id });
  } catch (e) {
    await client.query("ROLLBACK");
    throw e;
  } finally {
    client.release();
  }
});

campaignsRouter.patch("/campaigns/:id/status", requireRole("owner", "admin"), async (req, res) => {
  if (!isUuid(req.params.id)) return res.status(404).json({ error: "Not found" });
  const r = await query(`UPDATE campaigns SET is_active=$3, updated_at=now() WHERE id=$1 AND workspace_id=$2 RETURNING id`, [
    req.params.id,
    req.auth!.workspaceId,
    Boolean(req.body?.isActive),
  ]);
  if (!r.length) return res.status(404).json({ error: "Not found" });
  res.json({ ok: true });
});

campaignsRouter.delete("/campaigns/:id", requireRole("owner", "admin"), async (req, res) => {
  if (!isUuid(req.params.id)) return res.status(404).json({ error: "Not found" });
  const r = await query(`DELETE FROM campaigns WHERE id=$1 AND workspace_id=$2 RETURNING id`, [req.params.id, req.auth!.workspaceId]);
  if (!r.length) return res.status(404).json({ error: "Not found" });
  res.json({ ok: true });
});
