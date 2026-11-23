import { Router } from "express";
import { config } from "../../config.js";
import { logOp, one, query } from "../../db/pool.js";
import { createSignedState, decryptSecret, encryptSecret, randomToken, verifySignedState } from "../../lib/crypto.js";
import { authorizationUrl, exchangeCode, metaClientFor, type AccountAuth } from "../../lib/meta/client.js";
import { requireAuth, requireRole } from "../auth.js";
import { isUuid } from "../util.js";

export const accountsRouter = Router();

const PUBLIC_COLUMNS = `id, ig_user_id, username, name, profile_picture_url, login_type, token_expires_at, status,
  status_detail, webhook_subscribed, connected_at`;

export function redirectUri() {
  return `${config().appUrl}/api/instagram/callback`;
}

accountsRouter.get("/accounts", requireAuth, async (req, res) => {
  res.json(await query(`SELECT ${PUBLIC_COLUMNS} FROM instagram_accounts WHERE workspace_id=$1 ORDER BY connected_at`, [req.auth!.workspaceId]));
});

accountsRouter.post("/accounts/connect", requireRole("owner", "admin"), async (req, res) => {
  const cfg = config();
  if (cfg.demoMode && !cfg.instagramAppId && !cfg.metaAppId) {
    return res.status(400).json({ error: "Demo mode without Meta credentials: use 'Add demo account' instead." });
  }
  const state = createSignedState({ ws: req.auth!.workspaceId, u: req.auth!.user.id });
  res.json({ url: authorizationUrl(redirectUri(), state) });
});

/** Demo mode only: creates a fake Instagram account wired to the demo Meta client. */
accountsRouter.post("/accounts/demo", requireRole("owner", "admin"), async (req, res) => {
  if (!config().demoMode) return res.status(404).json({ error: "Demo mode is off" });
  const n = Math.floor(Math.random() * 9000 + 1000);
  const acct = await one(
    `INSERT INTO instagram_accounts(workspace_id, ig_user_id, username, name, login_type, access_token_enc, webhook_subscribed)
     VALUES ($1,$2,$3,$4,'demo',$5,true) RETURNING ${PUBLIC_COLUMNS}`,
    [req.auth!.workspaceId, `1784${Date.now()}${n}`.slice(0, 17), `demo_esthetics_${n}`, "Demo Esthetics Studio", encryptSecret(`demo-token-${randomToken(8)}`)]
  );
  res.status(201).json(acct);
});

accountsRouter.get("/instagram/callback", async (req, res) => {
  const fail = (msg: string) => res.redirect(`/accounts?error=${encodeURIComponent(msg)}`);
  if (req.query.error) return fail(String(req.query.error_description ?? req.query.error));
  const state = verifySignedState(String(req.query.state ?? ""));
  if (!state) return fail("Connection link expired or was tampered with. Try again.");
  if (!req.auth || req.auth.user.id !== state.u) return fail("Sign in with the same user that started the connection.");
  const member = await one(`SELECT role FROM workspace_members WHERE workspace_id=$1 AND user_id=$2`, [state.ws, state.u]);
  if (!member || member.role === "member") return fail("You need to be an owner or admin of the workspace.");
  const code = String(req.query.code ?? "").replace(/#_$/, "");
  if (!code) return fail("Meta didn't return an authorization code.");

  try {
    const accounts = await exchangeCode(code, redirectUri());
    for (const a of accounts) {
      const existing = await one<{ workspace_id: string }>(`SELECT workspace_id FROM instagram_accounts WHERE ig_user_id=$1`, [a.igUserId]);
      if (existing && existing.workspace_id !== state.ws) {
        return fail(`@${a.username} is already connected to another workspace. Disconnect it there first.`);
      }
      const row = await one<{ id: string }>(
        `INSERT INTO instagram_accounts(workspace_id, ig_user_id, username, name, profile_picture_url, login_type, access_token_enc, token_expires_at, status, status_detail)
         VALUES ($1,$2,$3,$4,$5,$6,$7,$8,'active',NULL)
         ON CONFLICT (ig_user_id) DO UPDATE SET username=EXCLUDED.username, name=EXCLUDED.name,
           profile_picture_url=EXCLUDED.profile_picture_url, login_type=EXCLUDED.login_type,
           access_token_enc=EXCLUDED.access_token_enc, token_expires_at=EXCLUDED.token_expires_at,
           status='active', status_detail=NULL, updated_at=now()
         RETURNING id`,
        [state.ws, a.igUserId, a.username, a.name ?? null, a.profilePictureUrl ?? null, a.loginType, encryptSecret(a.accessToken), a.expiresAt]
      );
      const auth: AccountAuth = { id: row!.id, igUserId: a.igUserId, loginType: a.loginType, accessToken: a.accessToken };
      let subscribed = false;
      try {
        subscribed = await metaClientFor(auth).subscribeWebhooks(auth);
      } catch (e) {
        await logOp("warning", "oauth", `Webhook subscription failed for @${a.username}`, { error: (e as Error).message });
      }
      await query(`UPDATE instagram_accounts SET webhook_subscribed=$2 WHERE id=$1`, [row!.id, subscribed]);
    }
    res.redirect(`/accounts?connected=${accounts.length}`);
  } catch (e) {
    await logOp("error", "oauth", "Instagram connection failed", { error: (e as Error).message });
    fail((e as Error).message.slice(0, 300));
  }
});

accountsRouter.delete("/accounts/:id", requireRole("owner", "admin"), async (req, res) => {
  if (!isUuid(req.params.id)) return res.status(404).json({ error: "Not found" });
  const r = await query(`DELETE FROM instagram_accounts WHERE id=$1 AND workspace_id=$2 RETURNING id`, [req.params.id, req.auth!.workspaceId]);
  if (!r.length) return res.status(404).json({ error: "Not found" });
  res.json({ ok: true });
});

accountsRouter.get("/accounts/:id/media", requireAuth, async (req, res) => {
  if (!isUuid(req.params.id)) return res.status(404).json({ error: "Not found" });
  const a = await one(`SELECT id, ig_user_id, login_type, access_token_enc FROM instagram_accounts WHERE id=$1 AND workspace_id=$2`, [
    req.params.id,
    req.auth!.workspaceId,
  ]);
  if (!a) return res.status(404).json({ error: "Not found" });
  const auth: AccountAuth = { id: a.id, igUserId: a.ig_user_id, loginType: a.login_type, accessToken: decryptSecret(a.access_token_enc) };
  try {
    res.json(await metaClientFor(auth).listMedia(auth));
  } catch (e) {
    res.status(502).json({ error: `Couldn't load posts from Instagram: ${(e as Error).message}` });
  }
});
