import { afterAll, beforeEach, describe, expect, it } from "vitest";
import { closeDb, one, query } from "../src/db/pool.js";
import { setMetaClientForTests } from "../src/lib/meta/client.js";
import { MetaApiError } from "../src/lib/meta/errors.js";
import type { CommentEvent, InboundMessageEvent } from "../src/lib/meta/webhook.js";
import { acquireSendSlot, closeRedis } from "../src/lib/redis.js";
import { RetryLaterError, processComment, processMessage } from "../src/worker/processor.js";
import { Deferred, createCampaign, ctx, fakeMeta, infraAvailable, resetState, seed } from "./helpers.js";

const ok = await infraAvailable();

describe.skipIf(!ok)("worker: comment-to-DM", () => {
  let fake: ReturnType<typeof fakeMeta>;
  beforeEach(async () => {
    await resetState();
    fake = fakeMeta();
    setMetaClientForTests(fake.client);
  });
  afterAll(async () => {
    setMetaClientForTests(null);
    await closeRedis();
    await closeDb();
  });

  const comment = (igUserId: string, patch: Partial<CommentEvent> = {}): CommentEvent => ({
    kind: "comment",
    igAccountId: igUserId,
    commentId: `c_${Math.random().toString(36).slice(2)}`,
    text: "GUIDE please",
    senderId: "U1",
    senderUsername: "jane",
    mediaId: "M1",
    timestamp: Date.now(),
    ...patch,
  });

  it("sends one private reply with a tracked, personalized link and logs it", async () => {
    const s = await seed();
    const ev = comment(s.igUserId);
    expect(await processComment(ev, ctx)).toBe("handled:1");
    expect(fake.calls).toHaveLength(1);
    expect(fake.calls[0].method).toBe("sendPrivateReply");
    expect(fake.calls[0].target).toBe(ev.commentId);
    expect(fake.calls[0].msg!.text).toMatch(/^Hi @jane! https:\/\/replypilot\.test\/r\/s\w+\?r=[\w-]{22}$/);
    const log = await one(`SELECT * FROM message_logs WHERE source_event_id=$1`, [ev.commentId]);
    expect(log).toMatchObject({ status: "sent", trigger: "comment", matched_keyword: "GUIDE", attempts: 1 });
  });

  it("is idempotent: reprocessing the same comment never sends twice", async () => {
    const s = await seed();
    const ev = comment(s.igUserId);
    await processComment(ev, ctx);
    await processComment(ev, ctx);
    expect(fake.calls).toHaveLength(1);
  });

  it("ignores non-matching comments, other posts, and the account's own comments", async () => {
    const s = await seed();
    expect(await processComment(comment(s.igUserId, { text: "nice photo" }), ctx)).toBe("ignored:no_match");
    expect(await processComment(comment(s.igUserId, { mediaId: "OTHER" }), ctx)).toBe("ignored:no_match");
    expect(await processComment(comment(s.igUserId, { senderId: s.igUserId }), ctx)).toBe("ignored:own_comment");
    expect(await processComment(comment("unknown-account"), ctx)).toBe("ignored:unknown_account");
    expect(fake.calls).toHaveLength(0);
  });

  it("matches ad comments through original_media_id and any-post campaigns", async () => {
    const s = await seed();
    await processComment(comment(s.igUserId, { mediaId: "AD1", originalMediaId: "M1" }), ctx);
    await createCampaign(s.workspaceId, s.account.id, { name: "Any", post_id: null, match_any_post: true, keywords: ["PRICE"] });
    await processComment(comment(s.igUserId, { mediaId: "M9", text: "price?" }), ctx);
    expect(fake.calls.map((c) => c.method)).toEqual(["sendPrivateReply", "sendPrivateReply"]);
  });

  it("enforces one private reply per comment across campaigns, but still posts each public reply", async () => {
    const s = await seed({ campaign: { public_reply_enabled: true, public_replies: ["Check DMs {username}"] } });
    await createCampaign(s.workspaceId, s.account.id, { name: "Dup", public_reply_enabled: true, public_replies: ["Sent!"] });
    const ev = comment(s.igUserId);
    await processComment(ev, ctx);
    expect(fake.calls.filter((c) => c.method === "sendPrivateReply")).toHaveLength(1);
    expect(fake.calls.filter((c) => c.method === "replyToComment").map((c) => c.text)).toEqual(["Check DMs @jane", "Sent!"]);
    const logs = await query(`SELECT status, skip_reason FROM message_logs WHERE source_event_id=$1 ORDER BY created_at`, [ev.commentId]);
    expect(logs.map((l) => l.skip_reason ?? l.status).sort()).toEqual(["private_reply_already_used", "sent"]);
  });

  it("skips comments older than Meta's 7-day private reply window", async () => {
    const s = await seed();
    const ev = comment(s.igUserId, { timestamp: Date.now() - 8 * 86400_000 });
    await processComment(ev, ctx);
    expect(fake.calls).toHaveLength(0);
    expect((await one(`SELECT skip_reason FROM message_logs WHERE source_event_id=$1`, [ev.commentId])).skip_reason).toBe(
      "private_reply_window_expired"
    );
  });

  it("sends the link as a button and falls back to inline text if the template is refused", async () => {
    const s = await seed({ campaign: { link_button_label: "Get it", dm_message: "Here you go {username}" } });
    fake.fail("sendPrivateReply", new MetaApiError("template not allowed", "permission", 100, 123));
    await processComment(comment(s.igUserId), ctx);
    expect(fake.calls).toHaveLength(1);
    expect(fake.calls[0].msg!.button).toBeUndefined();
    expect(fake.calls[0].msg!.text).toMatch(/^Here you go @jane\nhttps:\/\/replypilot\.test\/r\//);
  });

  it("retries transient errors and succeeds on the next attempt without double-sending", async () => {
    const s = await seed();
    const ev = comment(s.igUserId);
    fake.fail("sendPrivateReply", new MetaApiError("Service unavailable", "transient", 2, undefined, 503));
    await expect(processComment(ev, ctx)).rejects.toBeInstanceOf(RetryLaterError);
    expect((await one(`SELECT status, attempts FROM message_logs WHERE source_event_id=$1`, [ev.commentId]))).toMatchObject({ status: "failed", attempts: 1 });
    await processComment(ev, ctx);
    expect(fake.calls).toHaveLength(1);
    expect((await one(`SELECT status, attempts FROM message_logs WHERE source_event_id=$1`, [ev.commentId]))).toMatchObject({ status: "sent", attempts: 2 });
  });

  it("marks the account when Meta rejects the token and stops", async () => {
    const s = await seed();
    fake.fail("sendPrivateReply", new MetaApiError("Session expired", "token", 190));
    expect(await processComment(comment(s.igUserId), ctx)).toBe("handled:1");
    expect((await one(`SELECT status FROM instagram_accounts WHERE id=$1`, [s.account.id])).status).toBe("token_expired");
    // Later comments are skipped, not attempted.
    await processComment(comment(s.igUserId), ctx);
    expect(fake.calls).toHaveLength(0);
  });

  it("pauses the account and defers when Meta throttles", async () => {
    const s = await seed();
    fake.fail("sendPrivateReply", new MetaApiError("rate", "rate_limit", 613));
    await expect(processComment(comment(s.igUserId), ctx)).rejects.toBeInstanceOf(Deferred);
    // While paused, new sends defer too.
    const err = await processComment(comment(s.igUserId), ctx).catch((e) => e);
    expect(err).toBeInstanceOf(Deferred);
    expect(err.ms).toBeGreaterThan(14 * 60_000);
  });

  it("defers when the hourly cap is reached", async () => {
    const s = await seed();
    for (let i = 0; i < 750; i++) await acquireSendSlot(s.igUserId, "private_reply", `x${i}`);
    const err = await processComment(comment(s.igUserId), ctx).catch((e) => e);
    expect(err).toBeInstanceOf(Deferred);
    expect(fake.calls).toHaveLength(0);
  });
});

describe.skipIf(!ok)("worker: DM and Story reply triggers", () => {
  let fake: ReturnType<typeof fakeMeta>;
  beforeEach(async () => {
    await resetState();
    fake = fakeMeta();
    setMetaClientForTests(fake.client);
  });

  const msg = (igUserId: string, patch: Partial<InboundMessageEvent> = {}): InboundMessageEvent => ({
    kind: "dm",
    igAccountId: igUserId,
    messageId: `m_${Math.random().toString(36).slice(2)}`,
    text: "guide",
    senderId: "U7",
    timestamp: Date.now(),
    ...patch,
  });

  it("answers a keyword DM once, with the fetched username", async () => {
    const s = await seed({ campaign: { trigger_comments: false, trigger_dms: true, post_id: null } });
    const ev = msg(s.igUserId);
    expect(await processMessage(ev, ctx)).toBe("sent");
    expect(await processMessage(ev, ctx)).toBe("ignored:already_handled");
    expect(fake.calls).toHaveLength(1);
    expect(fake.calls[0]).toMatchObject({ method: "sendDirectMessage", target: "U7" });
    expect(fake.calls[0].msg!.text).toMatch(/^Hi @dm_user!/);
  });

  it("only uses story-reply campaigns for story replies", async () => {
    const s = await seed({ campaign: { trigger_comments: false, trigger_dms: true, post_id: null } });
    expect(await processMessage(msg(s.igUserId, { kind: "story_reply" }), ctx)).toBe("ignored:no_match");
    await createCampaign(s.workspaceId, s.account.id, { trigger_comments: false, trigger_story_replies: true, match_any_word: true, keywords: [] });
    expect(await processMessage(msg(s.igUserId, { kind: "story_reply", text: "" }), ctx)).toBe("sent");
  });

  it("refuses to reply outside the 24-hour messaging window", async () => {
    const s = await seed({ campaign: { trigger_comments: false, trigger_dms: true, post_id: null } });
    const r = await processMessage(msg(s.igUserId, { timestamp: Date.now() - 25 * 3600_000 }), ctx);
    expect(r).toBe("skipped:messaging_window_closed");
    expect(fake.calls).toHaveLength(0);
  });

  it("applies a cooldown so one person can't trigger the same campaign repeatedly", async () => {
    const s = await seed({ campaign: { trigger_comments: false, trigger_dms: true, post_id: null } });
    expect(await processMessage(msg(s.igUserId), ctx)).toBe("sent");
    expect(await processMessage(msg(s.igUserId), ctx)).toBe("skipped:cooldown");
    expect(fake.calls).toHaveLength(1);
  });
});
