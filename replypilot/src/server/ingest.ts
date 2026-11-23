import { webhookSecrets } from "../config.js";
import { logOp, one, query } from "../db/pool.js";
import { sha256 } from "../lib/crypto.js";
import { parseWebhook, verifySignature, type WebhookPayload } from "../lib/meta/webhook.js";
import { enqueueEvent } from "../lib/redis.js";

export interface IngestResult {
  status: number;
  body: Record<string, unknown>;
}

/**
 * Handles one webhook delivery from Meta:
 * verify signature -> store once (sha256 of body = dedup key) -> parse -> enqueue one job per event.
 * Each job has a deterministic id, so a comment that arrives twice in different bodies is still queued once.
 */
export async function ingestWebhook(rawBody: Buffer | string, signature: string | undefined): Promise<IngestResult> {
  const raw = typeof rawBody === "string" ? rawBody : rawBody.toString("utf8");
  if (!verifySignature(raw, signature, webhookSecrets())) {
    await logOp("warning", "webhook", "Webhook signature verification failed", {
      hadSignature: Boolean(signature),
      bodyLength: raw.length,
    });
    return { status: 401, body: { error: "Invalid signature" } };
  }

  let payload: WebhookPayload;
  try {
    payload = JSON.parse(raw);
  } catch {
    return { status: 400, body: { error: "Invalid JSON" } };
  }

  const row = await one<{ id: string }>(
    `INSERT INTO webhook_events(dedup_key, object, payload) VALUES ($1,$2,$3)
     ON CONFLICT (dedup_key) DO UPDATE SET status='received', error=NULL
       WHERE webhook_events.status='failed'
     RETURNING id`,
    [sha256(raw), payload?.object ?? null, raw]
  );
  if (!row) return { status: 200, body: { ok: true, duplicate: true } };

  try {
    const events = parseWebhook(payload);
    const ids = [...new Set(events.map((e) => e.igAccountId))];
    const known = ids.length
      ? new Set(
          (await query<{ ig_user_id: string }>(`SELECT ig_user_id FROM instagram_accounts WHERE ig_user_id = ANY($1)`, [ids])).map(
            (r) => r.ig_user_id
          )
        )
      : new Set<string>();
    let enqueued = 0;
    for (const ev of events) {
      if (!known.has(ev.igAccountId)) continue;
      if (ev.kind === "comment") await enqueueEvent({ type: "comment", event: ev });
      else await enqueueEvent({ type: "message", event: ev });
      enqueued++;
    }
    await query(
      `UPDATE webhook_events SET status=$2, jobs_enqueued=$3, processed_at=now() WHERE id=$1`,
      [row.id, enqueued ? "processed" : "ignored", enqueued]
    );
    return { status: 200, body: { ok: true, enqueued } };
  } catch (e) {
    await query(`UPDATE webhook_events SET status='failed', error=$2, processed_at=now() WHERE id=$1`, [
      row.id,
      (e as Error).message,
    ]);
    await logOp("error", "webhook", "Failed to enqueue webhook events", { error: (e as Error).message });
    // 500 makes Meta retry the delivery; the failed row is reopened on retry.
    return { status: 500, body: { error: "Processing failed" } };
  }
}
