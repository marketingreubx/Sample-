/**
 * Meta messaging rules this app enforces before every send.
 *
 * - Private replies (comment -> DM): one per comment, ever, and only within
 *   7 days of the comment. Enforced across all campaigns.
 * - Standard messaging window: a business may reply to a DM / story reply only
 *   within 24 hours of the user's last message. No promotional follow-ups
 *   outside the window (this app never sends unsolicited messages).
 * - Never message the connected account itself, never react to echoes of our
 *   own messages or our own comments (prevents loops).
 * - Per-account hourly send caps (private replies: Meta documents 750/hour;
 *   DMs: conservative default 200/hour) plus a 15-minute pause whenever Meta
 *   returns a throttling error.
 * - Text is capped at 1000 bytes, button template text at 640 chars, button
 *   titles at 20 chars.
 */
export const PRIVATE_REPLY_WINDOW_MS = 7 * 24 * 3600_000;
export const STANDARD_MESSAGING_WINDOW_MS = 24 * 3600_000;
/** Don't answer the same person on the same DM campaign more than once in this period. */
export const DM_TRIGGER_COOLDOWN_MS = 10 * 60_000;

export type SkipReason =
  | "private_reply_window_expired"
  | "private_reply_already_used"
  | "messaging_window_closed"
  | "cooldown"
  | "account_unavailable";

export function privateReplyAllowed(commentTimestampMs: number, now = Date.now()): boolean {
  return now - commentTimestampMs <= PRIVATE_REPLY_WINDOW_MS;
}

export function withinMessagingWindow(lastInboundMs: number | null | undefined, now = Date.now()): boolean {
  if (!lastInboundMs) return false;
  return now - lastInboundMs <= STANDARD_MESSAGING_WINDOW_MS;
}

/** The latest time a deferred send may still go out, or null if the window already closed. */
export function sendDeadline(kind: "comment" | "dm", eventMs: number): number {
  return eventMs + (kind === "comment" ? PRIVATE_REPLY_WINDOW_MS : STANDARD_MESSAGING_WINDOW_MS);
}

export const SKIP_LABELS: Record<SkipReason, string> = {
  private_reply_window_expired: "Comment is older than 7 days; Instagram no longer allows a private reply.",
  private_reply_already_used: "Another campaign already sent the one private reply Instagram allows for this comment.",
  messaging_window_closed: "More than 24 hours since this person last messaged you; Instagram doesn't allow a reply.",
  cooldown: "Already replied to this person on this campaign in the last 10 minutes.",
  account_unavailable: "Instagram account is disconnected or its token expired. Reconnect it in Accounts.",
};
