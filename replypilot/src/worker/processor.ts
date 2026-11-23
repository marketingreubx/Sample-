import { config } from "../config.js";
import { logOp, one, query } from "../db/pool.js";
import { decryptSecret } from "../lib/crypto.js";
import { matchKeywords } from "../lib/keywords.js";
import { metaClientFor, type AccountAuth, type OutMessage } from "../lib/meta/client.js";
import { MetaApiError } from "../lib/meta/errors.js";
import type { CommentEvent, InboundMessageEvent } from "../lib/meta/webhook.js";
import { acquireLock, acquireSendSlot, pauseAccount, releaseLock, type LimitKind } from "../lib/redis.js";
import {
  DM_TRIGGER_COOLDOWN_MS,
  privateReplyAllowed,
  sendDeadline,
  withinMessagingWindow,
  type SkipReason,
} from "../lib/restrictions.js";
import {
  MAX_BUTTON_TEXT,
  pickRandom,
  recipientToken,
  renderTemplate,
  trackedUrl,
  truncateBytes,
} from "../lib/templating.js";

export class RetryLaterError extends Error {
  name = "RetryLaterError";
}

export interface ProcessCtx {
  /** Re-schedule the whole job after `ms` without consuming a retry attempt. Must throw. */
  defer(ms: number): Promise<never>;
  now?: () => number;
}

interface AccountRow {
  id: string;
  workspace_id: string;
  ig_user_id: string;
  username: string;
  login_type: "instagram" | "unified" | "demo";
  access_token_enc: string;
  status: string;
}

interface CampaignRow {
  id: string;
  workspace_id: string;
  instagram_account_id: string;
  name: string;
  keywords: string[];
  match_any_word: boolean;
  whole_word: boolean;
  dm_message: string;
  link_url: string | null;
  link_button_label: string | null;
  public_reply_enabled: boolean;
  public_replies: string[];
  tracked_slug: string | null;
}

interface LogRow {
  id: string;
  status: "pending" | "sent" | "failed" | "skipped";
  attempts: number;
  public_reply_status: "none" | "sent" | "failed";
}

const MAX_SEND_ATTEMPTS = 5;

async function loadAccount(igUserId: string): Promise<AccountRow | null> {
  return one<AccountRow>(`SELECT * FROM instagram_accounts WHERE ig_user_id = $1`, [igUserId]);
}

function authFor(a: AccountRow): AccountAuth {
  return { id: a.id, igUserId: a.ig_user_id, loginType: a.login_type, accessToken: decryptSecret(a.access_token_enc) };
}

const CAMPAIGN_COLUMNS = `c.*, (SELECT slug FROM tracked_links t WHERE t.campaign_id = c.id ORDER BY created_at LIMIT 1) AS tracked_slug`;

async function upsertLog(
  c: CampaignRow,
  trigger: "comment" | "dm" | "story_reply",
  eventId: string,
  senderId: string,
  senderUsername: string | undefined,
  text: string,
  keyword: string | null
): Promise<LogRow> {
  await query(
    `INSERT INTO message_logs(workspace_id, campaign_id, instagram_account_id, trigger, source_event_id,
       sender_id, sender_username, incoming_text, matched_keyword)
     VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9) ON CONFLICT (campaign_id, source_event_id) DO NOTHING`,
    [c.workspace_id, c.id, c.instagram_account_id, trigger, eventId, senderId, senderUsername ?? null, text, keyword]
  );
  return (await one<LogRow>(
    `SELECT id, status, attempts, public_reply_status FROM message_logs WHERE campaign_id=$1 AND source_event_id=$2`,
    [c.id, eventId]
  ))!;
}

async function markSkipped(logId: string, reason: SkipReason) {
  await query(`UPDATE message_logs SET status='skipped', skip_reason=$2, updated_at=now() WHERE id=$1`, [logId, reason]);
}

function buildMessage(c: CampaignRow, vars: { username?: string | null; keyword?: string | null; recipientId: string }): OutMessage {
  const cfg = config();
  const link = c.link_url
    ? c.tracked_slug
      ? trackedUrl(cfg.appUrl, c.tracked_slug, recipientToken(vars.recipientId, cfg.sessionSecret))
      : c.link_url
    : null;
  if (link && c.link_button_label) {
    const text = renderTemplate(c.dm_message, { username: vars.username, keyword: vars.keyword, link: null });
    return { text: text.slice(0, MAX_BUTTON_TEXT) || "Here you go 👇", button: { title: c.link_button_label, url: link } };
  }
  return { text: truncateBytes(renderTemplate(c.dm_message, { username: vars.username, keyword: vars.keyword, link })) };
}

/** Waits for a rate-limit slot; defers the job if none is free (unless that would miss Meta's window). */
async function takeSlot(
  ctx: ProcessCtx,
  account: AccountRow,
  kind: LimitKind,
  member: string,
  deadline: number,
  logId: string,
  windowReason: SkipReason
): Promise<boolean> {
  const wait = await acquireSendSlot(account.ig_user_id, kind, member);
  if (wait <= 0) return true;
  const now = (ctx.now ?? Date.now)();
  if (now + wait > deadline) {
    await markSkipped(logId, windowReason);
    return false;
  }
  await query(`UPDATE message_logs SET error=$2, updated_at=now() WHERE id=$1`, [
    logId,
    `Rate limited: waiting ${Math.ceil(wait / 1000)}s for a send slot`,
  ]);
  return ctx.defer(wait + Math.floor(Math.random() * 2000));
}

/** Records a failed send and decides whether to stop, retry, or pause the account. Returns true when a retry is warranted. */
async function handleSendError(
  ctx: ProcessCtx,
  err: unknown,
  account: AccountRow,
  logId: string,
  attempts: number
): Promise<boolean> {
  const e = err instanceof MetaApiError ? err : new MetaApiError(String((err as Error)?.message ?? err), "unknown");
  await query(`UPDATE message_logs SET status='failed', error=$2, updated_at=now() WHERE id=$1`, [logId, e.message]);
  if (e.kind === "token") {
    await query(
      `UPDATE instagram_accounts SET status='token_expired', status_detail=$2, updated_at=now() WHERE id=$1`,
      [account.id, e.message]
    );
    await logOp("error", "worker", `Instagram token rejected for @${account.username}`, { error: e.message });
    return false;
  }
  if (e.kind === "rate_limit") {
    await pauseAccount(account.ig_user_id);
    await logOp("warning", "worker", `Meta throttled @${account.username}; pausing sends 15 minutes`, { error: e.message });
    return ctx.defer(15 * 60_000);
  }
  if (e.kind === "window" || e.kind === "permission") return false;
  return attempts < MAX_SEND_ATTEMPTS; // transient / unknown
}

// ---------------------------------------------------------------------------

export async function processComment(ev: CommentEvent, ctx: ProcessCtx): Promise<string> {
  const lockKey = `comment_${ev.commentId}`;
  if (!(await acquireLock(lockKey))) return ctx.defer(15_000);
  try {
    return await processCommentLocked(ev, ctx);
  } finally {
    await releaseLock(lockKey);
  }
}

async function processCommentLocked(ev: CommentEvent, ctx: ProcessCtx): Promise<string> {
  const now = (ctx.now ?? Date.now)();
  const account = await loadAccount(ev.igAccountId);
  if (!account) return "ignored:unknown_account";
  if (ev.senderId === account.ig_user_id) return "ignored:own_comment";

  const mediaIds = [ev.mediaId, ev.originalMediaId].filter(Boolean);
  const campaigns = await query<CampaignRow>(
    `SELECT ${CAMPAIGN_COLUMNS} FROM campaigns c
      WHERE c.instagram_account_id=$1 AND c.is_active AND c.trigger_comments
        AND (c.match_any_post OR c.post_id = ANY($2::text[]))
      ORDER BY c.created_at ASC`,
    [account.id, mediaIds]
  );

  let retry = false;
  let handled = 0;
  for (const c of campaigns) {
    const m = c.match_any_word ? { matched: true, keyword: null } : matchKeywords(ev.text, c.keywords, c.whole_word);
    if (!m.matched) continue;
    handled++;
    const log = await upsertLog(c, "comment", ev.commentId, ev.senderId, ev.senderUsername, ev.text, m.keyword);
    const dmDone = log.status === "sent" || log.status === "skipped" || log.attempts >= MAX_SEND_ATTEMPTS;
    const publicDone = !c.public_reply_enabled || !c.public_replies.length || log.public_reply_status === "sent";
    if (dmDone && publicDone) continue;

    if (account.status !== "active") {
      await markSkipped(log.id, "account_unavailable");
      continue;
    }
    const auth = authFor(account);
    const client = metaClientFor(auth);

    // 1) Optional public reply under the comment. Independent of the DM, so a DM refusal never suppresses it.
    if (!publicDone) {
      const text = truncateBytes(
        renderTemplate(pickRandom(c.public_replies) ?? "", { username: ev.senderUsername, keyword: m.keyword, link: null })
      );
      try {
        if (await takeSlot(ctx, account, "comment_reply", `pr_${ev.commentId}_${c.id}`, sendDeadline("comment", ev.timestamp), log.id, "private_reply_window_expired")) {
          await client.replyToComment(auth, ev.commentId, text);
          await query(
            `UPDATE message_logs SET public_reply_status='sent', public_reply_text=$2, public_reply_error=NULL, updated_at=now() WHERE id=$1`,
            [log.id, text]
          );
        }
      } catch (err) {
        if (err instanceof MetaApiError || !(err as any)?.deferred) {
          const e = err as Error;
          await query(
            `UPDATE message_logs SET public_reply_status='failed', public_reply_error=$2, updated_at=now() WHERE id=$1`,
            [log.id, e.message]
          );
          if (err instanceof MetaApiError && err.kind === "rate_limit") await pauseAccount(account.ig_user_id);
          if (err instanceof MetaApiError && err.retryable) retry = true;
        } else throw err;
      }
    }

    if (dmDone) continue;

    // 2) The private reply DM, subject to Meta's rules.
    if (!privateReplyAllowed(ev.timestamp, now)) {
      await markSkipped(log.id, "private_reply_window_expired");
      continue;
    }
    const used = await one(
      `SELECT 1 FROM message_logs WHERE source_event_id=$1 AND trigger='comment' AND status='sent' AND campaign_id<>$2 LIMIT 1`,
      [ev.commentId, c.id]
    );
    if (used) {
      await markSkipped(log.id, "private_reply_already_used");
      continue;
    }
    if (!(await takeSlot(ctx, account, "private_reply", `dm_${ev.commentId}`, sendDeadline("comment", ev.timestamp), log.id, "private_reply_window_expired"))) {
      continue;
    }

    let msg = buildMessage(c, { username: ev.senderUsername, keyword: m.keyword, recipientId: ev.senderId });
    const attempts = log.attempts + 1;
    await query(`UPDATE message_logs SET attempts=$2, dm_text=$3, updated_at=now() WHERE id=$1`, [log.id, attempts, msg.text]);
    try {
      try {
        await client.sendPrivateReply(auth, ev.commentId, msg);
      } catch (err) {
        // Some accounts reject button templates; fall back once to plain text with the link inline.
        if (msg.button && err instanceof MetaApiError && err.kind === "permission" && err.code === 100 && err.subcode !== 2534037) {
          msg = { text: truncateBytes(`${msg.text}\n${msg.button.url}`) };
          await client.sendPrivateReply(auth, ev.commentId, msg);
        } else throw err;
      }
      await query(
        `UPDATE message_logs SET status='sent', sent_at=now(), error=NULL, dm_text=$2, updated_at=now() WHERE id=$1`,
        [log.id, msg.text]
      );
      await query(
        `INSERT INTO contacts(instagram_account_id, igsid, username, last_outbound_at) VALUES ($1,$2,$3,now())
         ON CONFLICT (instagram_account_id, igsid) DO UPDATE SET last_outbound_at=now(), username=COALESCE(EXCLUDED.username, contacts.username)`,
        [account.id, ev.senderId, ev.senderUsername ?? null]
      );
    } catch (err) {
      if ((err as any)?.deferred) throw err;
      if (await handleSendError(ctx, err, account, log.id, attempts)) retry = true;
    }
  }

  if (retry) throw new RetryLaterError(`Retrying comment ${ev.commentId} after a transient error`);
  return handled ? `handled:${handled}` : "ignored:no_match";
}

// ---------------------------------------------------------------------------

export async function processMessage(ev: InboundMessageEvent, ctx: ProcessCtx): Promise<string> {
  const lockKey = `msg_${Buffer.from(ev.messageId).toString("base64url")}`;
  if (!(await acquireLock(lockKey))) return ctx.defer(15_000);
  try {
    return await processMessageLocked(ev, ctx);
  } finally {
    await releaseLock(lockKey);
  }
}

async function processMessageLocked(ev: InboundMessageEvent, ctx: ProcessCtx): Promise<string> {
  const now = (ctx.now ?? Date.now)();
  const account = await loadAccount(ev.igAccountId);
  if (!account) return "ignored:unknown_account";
  if (ev.senderId === account.ig_user_id) return "ignored:own_message";

  // Track the inbound message: it opens Meta's 24-hour standard messaging window.
  const contact = await one<{ last_inbound_at: Date }>(
    `INSERT INTO contacts(instagram_account_id, igsid, last_inbound_at) VALUES ($1,$2,to_timestamp($3/1000.0))
     ON CONFLICT (instagram_account_id, igsid) DO UPDATE
       SET last_inbound_at = GREATEST(contacts.last_inbound_at, EXCLUDED.last_inbound_at)
     RETURNING last_inbound_at`,
    [account.id, ev.senderId, ev.timestamp]
  );

  const triggerCol = ev.kind === "story_reply" ? "trigger_story_replies" : "trigger_dms";
  const campaigns = await query<CampaignRow>(
    `SELECT ${CAMPAIGN_COLUMNS} FROM campaigns c
      WHERE c.instagram_account_id=$1 AND c.is_active AND c.${triggerCol}
      ORDER BY c.created_at ASC`,
    [account.id]
  );

  // A DM gets at most one automated answer: the oldest matching campaign.
  let chosen: { c: CampaignRow; keyword: string | null } | null = null;
  for (const c of campaigns) {
    const m = c.match_any_word ? { matched: true, keyword: null } : matchKeywords(ev.text, c.keywords, c.whole_word);
    if (m.matched) {
      chosen = { c, keyword: m.keyword };
      break;
    }
  }
  if (!chosen) return "ignored:no_match";
  const { c, keyword } = chosen;

  const log = await upsertLog(c, ev.kind, ev.messageId, ev.senderId, undefined, ev.text, keyword);
  if (log.status === "sent" || log.status === "skipped" || log.attempts >= MAX_SEND_ATTEMPTS) return "ignored:already_handled";

  if (account.status !== "active") {
    await markSkipped(log.id, "account_unavailable");
    return "skipped:account_unavailable";
  }
  const lastInbound = contact?.last_inbound_at ? new Date(contact.last_inbound_at).getTime() : ev.timestamp;
  if (!withinMessagingWindow(lastInbound, now)) {
    await markSkipped(log.id, "messaging_window_closed");
    return "skipped:messaging_window_closed";
  }
  const recent = await one(
    `SELECT 1 FROM message_logs WHERE campaign_id=$1 AND sender_id=$2 AND status='sent' AND id<>$3
       AND sent_at > now() - ($4::int * interval '1 millisecond') LIMIT 1`,
    [c.id, ev.senderId, log.id, DM_TRIGGER_COOLDOWN_MS]
  );
  if (recent) {
    await markSkipped(log.id, "cooldown");
    return "skipped:cooldown";
  }
  if (!(await takeSlot(ctx, account, "dm", `dm_${ev.messageId}`, sendDeadline("dm", lastInbound), log.id, "messaging_window_closed"))) {
    return "skipped:messaging_window_closed";
  }

  const auth = authFor(account);
  const client = metaClientFor(auth);
  const username = await client.getUsername(auth, ev.senderId);
  if (username) {
    await query(`UPDATE message_logs SET sender_username=$2 WHERE id=$1`, [log.id, username]);
    await query(`UPDATE contacts SET username=$3 WHERE instagram_account_id=$1 AND igsid=$2`, [account.id, ev.senderId, username]);
  }
  const msg = buildMessage(c, { username, keyword, recipientId: ev.senderId });
  const attempts = log.attempts + 1;
  await query(`UPDATE message_logs SET attempts=$2, dm_text=$3, updated_at=now() WHERE id=$1`, [log.id, attempts, msg.text]);
  try {
    await client.sendDirectMessage(auth, ev.senderId, msg);
    await query(`UPDATE message_logs SET status='sent', sent_at=now(), error=NULL WHERE id=$1`, [log.id]);
    await query(`UPDATE contacts SET last_outbound_at=now() WHERE instagram_account_id=$1 AND igsid=$2`, [account.id, ev.senderId]);
    return "sent";
  } catch (err) {
    if ((err as any)?.deferred) throw err;
    if (await handleSendError(ctx, err, account, log.id, attempts)) {
      throw new RetryLaterError(`Retrying message ${ev.messageId} after a transient error`);
    }
    return "failed";
  }
}
