import { config } from "../src/config.js";
import { migrate } from "../src/db/migrate.js";
import { one, query } from "../src/db/pool.js";
import { encryptSecret } from "../src/lib/crypto.js";
import type { MetaClient, OutMessage } from "../src/lib/meta/client.js";
import { MetaApiError } from "../src/lib/meta/errors.js";
import { getRedis } from "../src/lib/redis.js";

export async function infraAvailable(): Promise<boolean> {
  try {
    await migrate(config().databaseUrl, () => {});
    await getRedis().ping();
    return true;
  } catch (e) {
    console.warn(`[tests] Skipping integration tests: Postgres/Redis unavailable (${(e as Error).message})`);
    return false;
  }
}

export async function resetState() {
  await query(`TRUNCATE users, workspaces, sessions, webhook_events, operational_events, demo_outbox CASCADE`);
  await getRedis().flushdb();
}

export async function seed(opts: { loginType?: "instagram" | "demo"; campaign?: Record<string, unknown> } = {}) {
  const ws = await one(`INSERT INTO workspaces(name) VALUES ('Test WS') RETURNING id`);
  const igUserId = `1784${Math.floor(Math.random() * 1e12)}`;
  const acct = await one(
    `INSERT INTO instagram_accounts(workspace_id, ig_user_id, username, login_type, access_token_enc)
     VALUES ($1,$2,'studio',$3,$4) RETURNING *`,
    [ws.id, igUserId, opts.loginType ?? "instagram", encryptSecret("IGAA-test-token")]
  );
  const campaign = await createCampaign(ws.id, acct.id, opts.campaign);
  return { workspaceId: ws.id as string, account: acct, igUserId, campaign };
}

export async function createCampaign(workspaceId: string, accountId: string, patch: Record<string, unknown> = {}) {
  const c = {
    name: "Guide",
    post_id: "M1",
    match_any_post: false,
    keywords: ["GUIDE"],
    match_any_word: false,
    trigger_comments: true,
    trigger_dms: false,
    trigger_story_replies: false,
    dm_message: "Hi {username}! {link}",
    link_url: "https://example.com/guide",
    link_button_label: null,
    public_reply_enabled: false,
    public_replies: [],
    ...patch,
  };
  const row = await one(
    `INSERT INTO campaigns(workspace_id, instagram_account_id, name, post_id, match_any_post, keywords, match_any_word,
       trigger_comments, trigger_dms, trigger_story_replies, dm_message, link_url, link_button_label, public_reply_enabled, public_replies)
     VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15) RETURNING *`,
    [workspaceId, accountId, c.name, c.post_id, c.match_any_post, c.keywords, c.match_any_word, c.trigger_comments,
      c.trigger_dms, c.trigger_story_replies, c.dm_message, c.link_url, c.link_button_label, c.public_reply_enabled, c.public_replies]
  );
  if (c.link_url) {
    await query(`INSERT INTO tracked_links(workspace_id, campaign_id, slug, destination_url) VALUES ($1,$2,$3,$4)`, [
      workspaceId,
      row.id,
      `s${Math.random().toString(36).slice(2, 10)}`,
      c.link_url,
    ]);
  }
  return row;
}

export interface FakeCall {
  method: string;
  target: string;
  msg?: OutMessage;
  text?: string;
}

/** Records calls; queue failures with fail("sendPrivateReply", error). */
export function fakeMeta() {
  const calls: FakeCall[] = [];
  const failures: Record<string, MetaApiError[]> = {};
  const maybeFail = (m: string) => {
    const e = failures[m]?.shift();
    if (e) throw e;
  };
  const client: MetaClient = {
    async sendPrivateReply(_a, commentId, msg) {
      maybeFail("sendPrivateReply");
      calls.push({ method: "sendPrivateReply", target: commentId, msg });
      return { message_id: "mid" };
    },
    async sendDirectMessage(_a, igsid, msg) {
      maybeFail("sendDirectMessage");
      calls.push({ method: "sendDirectMessage", target: igsid, msg });
      return { message_id: "mid" };
    },
    async replyToComment(_a, commentId, text) {
      maybeFail("replyToComment");
      calls.push({ method: "replyToComment", target: commentId, text });
      return { id: "r" };
    },
    async getUsername() {
      return "dm_user";
    },
    async listMedia() {
      return [];
    },
    async subscribeWebhooks() {
      return true;
    },
  };
  return {
    client,
    calls,
    fail(method: string, e: MetaApiError) {
      (failures[method] ??= []).push(e);
    },
  };
}

export class Deferred extends Error {
  deferred = true;
  constructor(public ms: number) {
    super(`deferred ${ms}`);
  }
}

export const ctx = {
  async defer(ms: number): Promise<never> {
    throw new Deferred(ms);
  },
};
