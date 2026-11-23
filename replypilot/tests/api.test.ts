import request from "supertest";
import { afterAll, beforeEach, describe, expect, it } from "vitest";
import { closeDb, one, query } from "../src/db/pool.js";
import { decryptSecret } from "../src/lib/crypto.js";
import { setMetaClientForTests } from "../src/lib/meta/client.js";
import { signBody } from "../src/lib/meta/webhook.js";
import { closeRedis, getQueue } from "../src/lib/redis.js";
import { createApp } from "../src/server/app.js";
import { handleJob } from "../src/worker/index.js";
import { infraAvailable, resetState, seed } from "./helpers.js";

const ok = await infraAvailable();
const app = createApp();
const H = { "X-Requested-With": "replypilot" };

async function signup(email = `u${Math.random().toString(36).slice(2)}@example.com`) {
  const agent = request.agent(app);
  const r = await agent.post("/api/auth/signup").set(H).send({ email, password: "password123", name: "Tester" });
  expect(r.status).toBe(201);
  return agent;
}

describe.skipIf(!ok)("HTTP API", () => {
  beforeEach(async () => {
    await resetState();
    await getQueue().obliterate({ force: true });
  });
  afterAll(async () => {
    setMetaClientForTests(null);
    await closeRedis();
    await closeDb();
  });

  it("signs up, logs in, rejects bad passwords and requires the CSRF header", async () => {
    await signup("a@example.com");
    expect((await request(app).post("/api/auth/login").set(H).send({ email: "a@example.com", password: "wrongpass1" })).status).toBe(401);
    expect((await request(app).post("/api/auth/login").send({ email: "a@example.com", password: "password123" })).status).toBe(403);
    const r = await request(app).post("/api/auth/login").set(H).send({ email: "a@example.com", password: "password123" });
    expect(r.status).toBe(200);
    expect(String(r.headers["set-cookie"])).toMatch(/rp_session=.*HttpOnly/i);
    expect((await request(app).post("/api/auth/signup").set(H).send({ email: "a@example.com", password: "password123" })).status).toBe(409);
    expect((await request(app).get("/api/campaigns")).status).toBe(401);
  });

  it("creates demo accounts and campaigns; stores tokens encrypted; isolates workspaces", async () => {
    const a = await signup();
    const acctRes = await a.post("/api/accounts/demo").set(H);
    expect(acctRes.status, JSON.stringify(acctRes.body)).toBe(201);
    const acct = acctRes.body;
    expect(acct.access_token_enc).toBeUndefined();
    const raw = await one(`SELECT access_token_enc FROM instagram_accounts WHERE id=$1`, [acct.id]);
    expect(raw.access_token_enc).toMatch(/^v1\./);
    expect(decryptSecret(raw.access_token_enc)).toMatch(/^demo-token-/);

    const bad = await a.post("/api/campaigns").set(H).send({ name: "x", instagramAccountId: acct.id, dmMessage: "hi" });
    expect(bad.status).toBe(400);
    const created = await a
      .post("/api/campaigns")
      .set(H)
      .send({ name: "Guide", instagramAccountId: acct.id, postId: "demo_media_1", keywords: ["GUIDE"], dmMessage: "Hi {link}", linkUrl: "https://example.com/g" });
    expect(created.status).toBe(201);
    const detail = (await a.get(`/api/campaigns/${created.body.id}`)).body;
    expect(detail.tracked_slug).toBeTruthy();
    expect(detail.daily).toHaveLength(30);

    const b = await signup();
    expect((await b.get(`/api/campaigns/${created.body.id}`)).status).toBe(404);
    expect((await b.get("/api/campaigns")).body).toHaveLength(0);
    expect((await b.post("/api/campaigns").set(H).send({ name: "Steal", instagramAccountId: acct.id, postId: "x", keywords: ["a"], dmMessage: "x" })).status).toBe(400);
  });

  it("verifies the webhook subscription challenge", async () => {
    const good = await request(app).get("/api/webhook").query({ "hub.mode": "subscribe", "hub.verify_token": "test-verify-token", "hub.challenge": "12345" });
    expect(good.status).toBe(200);
    expect(good.text).toBe("12345");
    const bad = await request(app).get("/api/webhook").query({ "hub.mode": "subscribe", "hub.verify_token": "nope", "hub.challenge": "1" });
    expect(bad.status).toBe(403);
  });

  it("rejects unsigned webhooks, dedups repeats, and runs a real comment through queue + worker", async () => {
    const s = await seed();
    const sent: string[] = [];
    setMetaClientForTests({
      async sendPrivateReply(_a, id, m) { sent.push(`${id}:${m.text}`); return {}; },
      async sendDirectMessage() { return {}; },
      async replyToComment() { return {}; },
      async getUsername() { return null; },
      async listMedia() { return []; },
      async subscribeWebhooks() { return true; },
    });
    const body = JSON.stringify({
      object: "instagram",
      entry: [{ id: s.igUserId, time: Math.floor(Date.now() / 1000), changes: [{ field: "comments", value: { id: "C42", text: "guide!", from: { id: "U9", username: "sam" }, media: { id: "M1" } } }] }],
    });
    const post = (sig: string) => request(app).post("/api/webhook").set("Content-Type", "application/json").set("X-Hub-Signature-256", sig).send(body);

    expect((await post("sha256=deadbeef")).status).toBe(401);
    const first = await post(signBody(body, "test-ig-secret"));
    expect(first.body).toMatchObject({ ok: true, enqueued: 1 });
    const again = await post(signBody(body, "test-fb-secret"));
    expect(again.body).toMatchObject({ ok: true, duplicate: true });

    const jobs = await getQueue().getJobs(["waiting"]);
    expect(jobs).toHaveLength(1);
    expect(jobs[0].id).toBe(`comment_${s.igUserId}_C42`);
    await handleJob(jobs[0] as any);
    expect(sent).toHaveLength(1);
    expect(sent[0]).toMatch(/^C42:Hi @sam! https:\/\/replypilot\.test\/r\//);

    // Same comment in a different body (e.g. Meta batching) still dedups at the job level.
    const body2 = body.replace('"time":', '"x":1,"time":');
    await request(app).post("/api/webhook").set("Content-Type", "application/json").set("X-Hub-Signature-256", signBody(body2, "test-ig-secret")).send(body2);
    expect(await getQueue().getJobs(["waiting"])).toHaveLength(1);
  });

  it("redirects tracked links and records unique clicks for analytics", async () => {
    const s = await seed();
    const link = await one(`SELECT slug FROM tracked_links WHERE campaign_id=$1`, [s.campaign.id]);
    const token = "A".repeat(22);
    const r = await request(app).get(`/r/${link.slug}?r=${token}`).set("User-Agent", "Mozilla/5.0");
    expect(r.status).toBe(302);
    expect(r.headers.location).toBe("https://example.com/guide");
    await request(app).get(`/r/${link.slug}?r=${token}`).set("User-Agent", "Mozilla/5.0");
    await request(app).get(`/r/${link.slug}`).set("User-Agent", "facebookexternalhit/1.1");
    const clicks = await query(`SELECT recipient_hash FROM link_clicks WHERE campaign_id=$1`, [s.campaign.id]);
    expect(clicks).toHaveLength(2);
    expect(clicks[0].recipient_hash).toBe(token);
    expect((await request(app).get("/r/nope")).status).toBe(404);
  });

  it("runs the full demo flow: simulate comment -> worker -> outbox + logs + analytics", async () => {
    setMetaClientForTests(null);
    const a = await signup();
    const acct = (await a.post("/api/accounts/demo").set(H)).body;
    await a.post("/api/campaigns").set(H).send({
      name: "Demo", instagramAccountId: acct.id, postId: "demo_media_1", keywords: ["GUIDE"], dmMessage: "Hey {username} {link}",
      linkUrl: "https://example.com", publicReplyEnabled: true, publicReplies: ["Check your DMs!"],
    });
    const sim = await a.post("/api/demo/simulate").set(H).send({ accountId: acct.id, type: "comment", text: "GUIDE", senderUsername: "lia" });
    expect(sim.body.enqueued).toBe(1);
    const [job] = await getQueue().getJobs(["waiting"]);
    expect(await handleJob(job as any)).toBe("handled:1");
    const outbox = (await a.get("/api/demo/outbox")).body;
    expect(outbox.map((o: any) => o.kind).sort()).toEqual(["comment_reply", "private_reply"]);
    const logs = (await a.get("/api/logs")).body;
    expect(logs.rows[0]).toMatchObject({ status: "sent", public_reply_status: "sent", sender_username: "lia" });
    const overview = (await a.get("/api/analytics/overview")).body;
    expect(overview.totals).toMatchObject({ triggered: 1, sent: 1, public_replies: 1 });
  });

  it("reports health and serves Meta's required legal pages", async () => {
    const h = await request(app).get("/api/health");
    expect(h.body.database).toBe("ok");
    expect(h.body.redis).toBe("ok");
    for (const p of ["/privacy", "/terms", "/data-deletion"]) expect((await request(app).get(p)).status).toBe(200);
  });
});
