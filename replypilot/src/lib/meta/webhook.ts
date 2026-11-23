import { createHmac, timingSafeEqual } from "node:crypto";

/** Verifies Meta's X-Hub-Signature-256 header against any of the app secrets. */
export function verifySignature(rawBody: string | Buffer, header: string | undefined | null, secrets: string[]): boolean {
  if (!header || !header.startsWith("sha256=") || !secrets.length) return false;
  const given = Buffer.from(header);
  return secrets.some((secret) => {
    const expected = Buffer.from("sha256=" + createHmac("sha256", secret).update(rawBody).digest("hex"));
    return expected.length === given.length && timingSafeEqual(expected, given);
  });
}

export function signBody(rawBody: string, secret: string): string {
  return "sha256=" + createHmac("sha256", secret).update(rawBody).digest("hex");
}

export interface CommentEvent {
  kind: "comment";
  igAccountId: string;
  commentId: string;
  text: string;
  senderId: string;
  senderUsername?: string;
  mediaId: string;
  originalMediaId?: string;
  parentId?: string;
  timestamp: number; // ms
}

export interface InboundMessageEvent {
  kind: "dm" | "story_reply";
  igAccountId: string;
  messageId: string;
  text: string;
  senderId: string;
  storyId?: string;
  timestamp: number; // ms
}

export type ParsedEvent = CommentEvent | InboundMessageEvent;

interface Entry {
  id?: string;
  time?: number;
  changes?: Array<{ field?: string; value?: any }>;
  messaging?: Array<any>;
}

export interface WebhookPayload {
  object?: string;
  entry?: Entry[];
}

const toMs = (t: unknown, fallback: number) => {
  const n = Number(t);
  if (!Number.isFinite(n) || n <= 0) return fallback;
  return n < 1e12 ? n * 1000 : n; // Meta sends seconds for entry.time, ms for messaging.timestamp
};

/**
 * Normalizes an Instagram webhook into comment / DM / story-reply events.
 * Skips the account's own comments and echoes of its own messages (so our
 * public replies and DMs never retrigger campaigns), deleted, unsupported
 * and text-less messages, reactions, reads and postbacks.
 */
export function parseWebhook(payload: WebhookPayload, now = Date.now()): ParsedEvent[] {
  const out: ParsedEvent[] = [];
  if (payload?.object !== "instagram" || !Array.isArray(payload.entry)) return out;

  for (const entry of payload.entry) {
    const accountId = entry.id ? String(entry.id) : undefined;
    const entryTime = toMs(entry.time, now);

    for (const change of entry.changes ?? []) {
      if (change.field !== "comments" && change.field !== "live_comments") continue;
      const v = change.value ?? {};
      const commentId = v.id ?? v.comment_id;
      const mediaId = v.media?.id ?? v.media_id;
      const senderId = v.from?.id;
      if (!accountId || !commentId || !mediaId || !senderId) continue;
      if (String(senderId) === accountId) continue; // our own comment / public reply
      const original = v.media?.original_media_id;
      out.push({
        kind: "comment",
        igAccountId: accountId,
        commentId: String(commentId),
        text: String(v.text ?? ""),
        senderId: String(senderId),
        senderUsername: v.from?.username,
        mediaId: String(mediaId),
        originalMediaId: original && original !== mediaId ? String(original) : undefined,
        parentId: v.parent_id ? String(v.parent_id) : undefined,
        timestamp: entryTime,
      });
    }

    for (const m of entry.messaging ?? []) {
      const msg = m?.message;
      if (!msg) continue;
      if (msg.is_echo || msg.is_deleted || msg.is_unsupported) continue;
      const senderId = m.sender?.id ? String(m.sender.id) : undefined;
      const acct = accountId ?? (m.recipient?.id ? String(m.recipient.id) : undefined);
      const text = typeof msg.text === "string" ? msg.text.trim() : "";
      if (!senderId || !acct || !msg.mid || senderId === acct) continue;
      const story = msg.reply_to?.story;
      // A story reply may be an emoji/reaction with no text; keep it so "any message" campaigns can fire.
      if (!text && !story) continue;
      out.push({
        kind: story ? "story_reply" : "dm",
        igAccountId: acct,
        messageId: String(msg.mid),
        text,
        senderId,
        storyId: story?.id ? String(story.id) : undefined,
        timestamp: toMs(m.timestamp, entryTime),
      });
    }
  }
  return out;
}
