import { config, isUnifiedLogin } from "../../config.js";
import { query } from "../../db/pool.js";
import { MetaApiError, classifyMetaError } from "./errors.js";

export interface AccountAuth {
  id: string; // our DB id
  igUserId: string;
  loginType: "instagram" | "unified" | "demo";
  accessToken: string;
}

export interface OutMessage {
  text: string;
  button?: { title: string; url: string };
}

export interface MediaItem {
  id: string;
  caption?: string;
  media_type?: string;
  media_url?: string;
  thumbnail_url?: string;
  permalink?: string;
  timestamp?: string;
}

export interface MetaClient {
  sendPrivateReply(a: AccountAuth, commentId: string, msg: OutMessage): Promise<{ message_id?: string }>;
  sendDirectMessage(a: AccountAuth, igsid: string, msg: OutMessage): Promise<{ message_id?: string }>;
  replyToComment(a: AccountAuth, commentId: string, text: string): Promise<{ id?: string }>;
  getUsername(a: AccountAuth, igsid: string): Promise<string | null>;
  listMedia(a: AccountAuth, limit?: number): Promise<MediaItem[]>;
  subscribeWebhooks(a: AccountAuth): Promise<boolean>;
}

function graphBase(a: Pick<AccountAuth, "loginType">) {
  const v = config().graphVersion;
  return a.loginType === "unified" ? `https://graph.facebook.com/${v}` : `https://graph.instagram.com/${v}`;
}

async function call<T>(url: string, init: RequestInit & { token: string }): Promise<T> {
  const { token, ...rest } = init;
  let res: Response;
  try {
    res = await fetch(url, {
      ...rest,
      headers: { "Content-Type": "application/json", Authorization: `Bearer ${token}`, ...(rest.headers ?? {}) },
      signal: AbortSignal.timeout(20_000),
    });
  } catch (e) {
    throw new MetaApiError(`Network error calling Meta: ${(e as Error).message}`, "transient");
  }
  const body = await res.json().catch(() => ({}));
  if (!res.ok || (body as any)?.error) {
    throw classifyMetaError(body, res.status, new URL(url).pathname);
  }
  return body as T;
}

function messageBody(msg: OutMessage) {
  if (!msg.button) return { text: msg.text };
  return {
    attachment: {
      type: "template",
      payload: {
        template_type: "button",
        text: msg.text.slice(0, 640),
        buttons: [{ type: "web_url", url: msg.button.url, title: msg.button.title.slice(0, 20) }],
      },
    },
  };
}

export const realMetaClient: MetaClient = {
  async sendPrivateReply(a, commentId, msg) {
    return call(`${graphBase(a)}/${a.igUserId}/messages`, {
      method: "POST",
      token: a.accessToken,
      body: JSON.stringify({ recipient: { comment_id: commentId }, message: messageBody(msg) }),
    });
  },
  async sendDirectMessage(a, igsid, msg) {
    return call(`${graphBase(a)}/${a.igUserId}/messages`, {
      method: "POST",
      token: a.accessToken,
      body: JSON.stringify({ recipient: { id: igsid }, message: messageBody(msg) }),
    });
  },
  async replyToComment(a, commentId, text) {
    return call(`${graphBase(a)}/${commentId}/replies`, {
      method: "POST",
      token: a.accessToken,
      body: JSON.stringify({ message: text }),
    });
  },
  async getUsername(a, igsid) {
    try {
      const r = await call<{ username?: string }>(`${graphBase(a)}/${igsid}?fields=username`, {
        method: "GET",
        token: a.accessToken,
      });
      return r.username ?? null;
    } catch {
      return null; // best effort: personalization falls back to "there"
    }
  },
  async listMedia(a, limit = 50) {
    const r = await call<{ data?: MediaItem[] }>(
      `${graphBase(a)}/${a.igUserId}/media?fields=id,caption,media_type,media_url,thumbnail_url,permalink,timestamp&limit=${limit}`,
      { method: "GET", token: a.accessToken }
    );
    return r.data ?? [];
  },
  async subscribeWebhooks(a) {
    const r = await call<{ success?: boolean }>(
      `${graphBase(a)}/${a.igUserId}/subscribed_apps?subscribed_fields=comments,messages`,
      { method: "POST", token: a.accessToken }
    );
    return Boolean(r.success);
  },
};

/** Demo mode: records every "send" in demo_outbox instead of calling Instagram. */
export const demoMetaClient: MetaClient = {
  async sendPrivateReply(a, commentId, msg) {
    await recordDemo(a, "private_reply", commentId, msg.text, msg);
    return { message_id: `demo_mid_${Date.now()}` };
  },
  async sendDirectMessage(a, igsid, msg) {
    await recordDemo(a, "dm", igsid, msg.text, msg);
    return { message_id: `demo_mid_${Date.now()}` };
  },
  async replyToComment(a, commentId, text) {
    await recordDemo(a, "comment_reply", commentId, text, null);
    return { id: `demo_reply_${Date.now()}` };
  },
  async getUsername(_a, igsid) {
    return `demo_user_${igsid.slice(-4)}`;
  },
  async listMedia() {
    return [1, 2, 3, 4].map((i) => ({
      id: `demo_media_${i}`,
      caption: ["Free skincare routine guide 👇", "Before & after glow-up", "Booking link inside", "Q&A: acne myths"][i - 1],
      media_type: "IMAGE",
      media_url: demoImage(i),
      permalink: `https://instagram.com/p/demo${i}`,
      timestamp: new Date(Date.now() - i * 86400000).toISOString(),
    }));
  },
  async subscribeWebhooks() {
    return true;
  },
};

const DEMO_COLORS = ["#a78bfa", "#f472b6", "#34d399", "#fbbf24"];
function demoImage(i: number): string {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"><rect width="400" height="400" fill="${DEMO_COLORS[i - 1]}"/><text x="200" y="215" font-size="48" text-anchor="middle" fill="white" font-family="sans-serif">Post ${i}</text></svg>`;
  return `data:image/svg+xml;base64,${Buffer.from(svg).toString("base64")}`;
}

async function recordDemo(a: AccountAuth, kind: string, recipient: string, text: string, payload: unknown) {
  await query(
    `INSERT INTO demo_outbox(instagram_account_id, kind, recipient, text, payload) VALUES ($1,$2,$3,$4,$5)`,
    [a.id, kind, recipient, text, payload ? JSON.stringify(payload) : null]
  );
}

let override: MetaClient | null = null;
export function setMetaClientForTests(c: MetaClient | null) {
  override = c;
}

export function metaClientFor(a: Pick<AccountAuth, "loginType">): MetaClient {
  if (override) return override;
  return a.loginType === "demo" ? demoMetaClient : realMetaClient;
}

// ---------- OAuth (connecting an Instagram professional account) ----------

export const INSTAGRAM_SCOPES = [
  "instagram_business_basic",
  "instagram_business_manage_messages",
  "instagram_business_manage_comments",
];

export function authorizationUrl(redirectUri: string, state: string): string {
  const cfg = config();
  if (isUnifiedLogin(cfg)) {
    const p = new URLSearchParams({
      client_id: cfg.metaAppId!,
      config_id: cfg.metaLoginConfigId!,
      redirect_uri: redirectUri,
      response_type: "code",
      state,
    });
    return `https://www.facebook.com/${cfg.graphVersion}/dialog/oauth?${p}`;
  }
  if (!cfg.instagramAppId) throw new Error("INSTAGRAM_APP_ID is not configured");
  const p = new URLSearchParams({
    client_id: cfg.instagramAppId,
    redirect_uri: redirectUri,
    response_type: "code",
    scope: INSTAGRAM_SCOPES.join(","),
    state,
  });
  return `https://www.instagram.com/oauth/authorize?${p}`;
}

export interface ConnectedAccount {
  igUserId: string;
  username: string;
  name?: string;
  profilePictureUrl?: string;
  accessToken: string;
  expiresAt: Date | null;
  loginType: "instagram" | "unified";
}

async function getJson(url: string, init?: RequestInit) {
  const res = await fetch(url, { ...init, signal: AbortSignal.timeout(20_000) });
  const body: any = await res.json().catch(() => ({}));
  if (!res.ok || body?.error || body?.error_message) {
    if (body?.error) throw classifyMetaError(body, res.status, new URL(url).pathname);
    throw new MetaApiError(body?.error_message ?? `HTTP ${res.status}`, "permission", undefined, undefined, res.status);
  }
  return body;
}

/** Exchanges the OAuth code for long-lived token(s) and returns the account(s) it grants. */
export async function exchangeCode(code: string, redirectUri: string): Promise<ConnectedAccount[]> {
  const cfg = config();
  if (isUnifiedLogin(cfg)) {
    const t = new URL(`https://graph.facebook.com/${cfg.graphVersion}/oauth/access_token`);
    t.search = new URLSearchParams({
      client_id: cfg.metaAppId!,
      client_secret: cfg.facebookAppSecret ?? "",
      redirect_uri: redirectUri,
      code,
    }).toString();
    const tok = await getJson(t.toString());
    const accessToken: string = tok.access_token;
    const expiresAt = tok.expires_in ? new Date(Date.now() + tok.expires_in * 1000) : null;
    const list = await getJson(
      `https://graph.facebook.com/${cfg.graphVersion}/me/assigned_instagram_accounts?fields=id,username,name,profile_picture_url&access_token=${encodeURIComponent(accessToken)}`
    );
    const accounts: any[] = list.data ?? [];
    if (!accounts.length) {
      throw new Error(
        "No Instagram account was granted. Add business_management to your Meta login configuration and connect again."
      );
    }
    return accounts.map((a) => ({
      igUserId: String(a.id),
      username: a.username ?? String(a.id),
      name: a.name,
      profilePictureUrl: a.profile_picture_url,
      accessToken,
      expiresAt,
      loginType: "unified" as const,
    }));
  }

  // Instagram API with Instagram Login
  const short = await getJson("https://api.instagram.com/oauth/access_token", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({
      client_id: cfg.instagramAppId ?? "",
      client_secret: cfg.instagramAppSecret ?? "",
      grant_type: "authorization_code",
      redirect_uri: redirectUri,
      code,
    }).toString(),
  });
  const long = await getJson(
    `https://graph.instagram.com/access_token?grant_type=ig_exchange_token&client_secret=${encodeURIComponent(
      cfg.instagramAppSecret ?? ""
    )}&access_token=${encodeURIComponent(short.access_token)}`
  );
  const accessToken: string = long.access_token;
  // user_id (not id) is the professional account id that webhooks use as entry.id.
  const me = await getJson(
    `https://graph.instagram.com/${cfg.graphVersion}/me?fields=user_id,username,name,profile_picture_url&access_token=${encodeURIComponent(accessToken)}`
  );
  return [
    {
      igUserId: String(me.user_id ?? short.user_id),
      username: me.username,
      name: me.name,
      profilePictureUrl: me.profile_picture_url,
      accessToken,
      expiresAt: long.expires_in ? new Date(Date.now() + long.expires_in * 1000) : null,
      loginType: "instagram",
    },
  ];
}

/** Long-lived Instagram Login tokens last 60 days and can be refreshed once they're 24h old. */
export async function refreshInstagramToken(token: string): Promise<{ accessToken: string; expiresAt: Date }> {
  const r = await getJson(
    `https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token=${encodeURIComponent(token)}`
  );
  return { accessToken: r.access_token, expiresAt: new Date(Date.now() + (r.expires_in ?? 5184000) * 1000) };
}
