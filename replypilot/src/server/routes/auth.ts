import bcrypt from "bcryptjs";
import { Router } from "express";
import { z } from "zod";
import { config } from "../../config.js";
import { db, one, query } from "../../db/pool.js";
import { hitCounter } from "../../lib/redis.js";
import { createSession, destroySession, requireAuth, requireRole } from "../auth.js";
import { clientIp } from "../util.js";

export const authRouter = Router();
let dummyHash: string | undefined;

const credentials = z.object({
  email: z.string().trim().toLowerCase().email().max(200),
  password: z.string().min(8, "Password must be at least 8 characters").max(200),
});

async function throttle(key: string, max: number) {
  return (await hitCounter(key, 15 * 60)) > max;
}

authRouter.post("/auth/signup", async (req, res) => {
  const cfg = config();
  if (!cfg.allowSignup) return res.status(403).json({ error: "Sign-up is disabled on this server" });
  if (await throttle(`signup:${clientIp(req)}`, 10)) return res.status(429).json({ error: "Too many attempts, try later" });
  const body = credentials
    .extend({ name: z.string().trim().max(100).optional(), workspaceName: z.string().trim().max(100).optional() })
    .safeParse(req.body);
  if (!body.success) return res.status(400).json({ error: body.error.issues[0].message });
  const { email, password, name, workspaceName } = body.data;
  if (cfg.allowedEmails.length && !cfg.allowedEmails.includes(email)) {
    return res.status(403).json({ error: "This email isn't allowed to sign up here" });
  }
  if (await one(`SELECT 1 FROM users WHERE email=$1`, [email])) {
    return res.status(409).json({ error: "An account with this email already exists" });
  }
  const hash = await bcrypt.hash(password, 12);
  const client = await db().connect();
  let userId: string, workspaceId: string;
  try {
    await client.query("BEGIN");
    userId = (await client.query(`INSERT INTO users(email, name, password_hash) VALUES ($1,$2,$3) RETURNING id`, [email, name ?? null, hash])).rows[0].id;
    workspaceId = (
      await client.query(`INSERT INTO workspaces(name) VALUES ($1) RETURNING id`, [workspaceName || `${name || email.split("@")[0]}'s workspace`])
    ).rows[0].id;
    await client.query(`INSERT INTO workspace_members(workspace_id, user_id, role) VALUES ($1,$2,'owner')`, [workspaceId, userId]);
    await client.query("COMMIT");
  } catch (e) {
    await client.query("ROLLBACK");
    throw e;
  } finally {
    client.release();
  }
  await createSession(req, res, userId, workspaceId);
  res.status(201).json({ ok: true });
});

authRouter.post("/auth/login", async (req, res) => {
  if (await throttle(`login:${clientIp(req)}`, 20)) return res.status(429).json({ error: "Too many attempts, try later" });
  const body = credentials.safeParse(req.body);
  if (!body.success) return res.status(400).json({ error: "Invalid email or password" });
  const u = await one<{ id: string; password_hash: string }>(`SELECT id, password_hash FROM users WHERE email=$1`, [body.data.email]);
  // Compare against a dummy hash when the user doesn't exist so timing doesn't reveal registered emails.
  dummyHash ??= await bcrypt.hash("not-a-real-password", 12);
  const ok = await bcrypt.compare(body.data.password, u?.password_hash ?? dummyHash);
  if (!u || !ok) return res.status(401).json({ error: "Invalid email or password" });
  await createSession(req, res, u.id, null);
  res.json({ ok: true });
});

authRouter.post("/auth/logout", async (req, res) => {
  await destroySession(req, res);
  res.json({ ok: true });
});

authRouter.get("/auth/me", async (req, res) => {
  const cfg = config();
  if (!req.auth) return res.json({ user: null, demoMode: cfg.demoMode, allowSignup: cfg.allowSignup });
  const workspaces = await query(
    `SELECT w.id, w.name, m.role FROM workspace_members m JOIN workspaces w ON w.id=m.workspace_id WHERE m.user_id=$1 ORDER BY w.created_at`,
    [req.auth.user.id]
  );
  res.json({
    user: req.auth.user,
    workspaceId: req.auth.workspaceId,
    role: req.auth.role,
    workspaces,
    demoMode: cfg.demoMode,
    appUrl: cfg.appUrl,
  });
});

// ---------------- Workspaces ----------------

authRouter.post("/workspaces", requireAuth, async (req, res) => {
  const name = z.string().trim().min(1).max(100).safeParse(req.body?.name);
  if (!name.success) return res.status(400).json({ error: "Workspace name is required" });
  const w = await one(`INSERT INTO workspaces(name) VALUES ($1) RETURNING id, name`, [name.data]);
  await query(`INSERT INTO workspace_members(workspace_id, user_id, role) VALUES ($1,$2,'owner')`, [w.id, req.auth!.user.id]);
  await query(`UPDATE sessions SET workspace_id=$2 WHERE id=$1`, [req.auth!.sessionId, w.id]);
  res.status(201).json(w);
});

authRouter.post("/workspaces/switch", requireAuth, async (req, res) => {
  const m = await one(`SELECT 1 FROM workspace_members WHERE workspace_id=$1 AND user_id=$2`, [req.body?.workspaceId, req.auth!.user.id]);
  if (!m) return res.status(404).json({ error: "Workspace not found" });
  await query(`UPDATE sessions SET workspace_id=$2 WHERE id=$1`, [req.auth!.sessionId, req.body.workspaceId]);
  res.json({ ok: true });
});

authRouter.patch("/workspace", requireRole("owner", "admin"), async (req, res) => {
  const name = z.string().trim().min(1).max(100).safeParse(req.body?.name);
  if (!name.success) return res.status(400).json({ error: "Workspace name is required" });
  await query(`UPDATE workspaces SET name=$2 WHERE id=$1`, [req.auth!.workspaceId, name.data]);
  res.json({ ok: true });
});

authRouter.get("/workspace/members", requireAuth, async (req, res) => {
  res.json(
    await query(
      `SELECT u.id, u.email, u.name, m.role, m.created_at FROM workspace_members m JOIN users u ON u.id=m.user_id
        WHERE m.workspace_id=$1 ORDER BY m.created_at`,
      [req.auth!.workspaceId]
    )
  );
});

authRouter.post("/workspace/members", requireRole("owner", "admin"), async (req, res) => {
  const body = z
    .object({ email: z.string().trim().toLowerCase().email(), role: z.enum(["admin", "member"]).default("member") })
    .safeParse(req.body);
  if (!body.success) return res.status(400).json({ error: "A valid email is required" });
  const u = await one<{ id: string }>(`SELECT id FROM users WHERE email=$1`, [body.data.email]);
  if (!u) return res.status(404).json({ error: "No user with that email yet. Ask them to sign up first, then add them." });
  await query(
    `INSERT INTO workspace_members(workspace_id, user_id, role) VALUES ($1,$2,$3) ON CONFLICT DO NOTHING`,
    [req.auth!.workspaceId, u.id, body.data.role]
  );
  res.status(201).json({ ok: true });
});

authRouter.delete("/workspace/members/:userId", requireRole("owner", "admin"), async (req, res) => {
  const target = await one<{ role: string }>(`SELECT role FROM workspace_members WHERE workspace_id=$1 AND user_id=$2`, [
    req.auth!.workspaceId,
    req.params.userId,
  ]);
  if (!target) return res.status(404).json({ error: "Member not found" });
  if (target.role === "owner") return res.status(400).json({ error: "The owner can't be removed" });
  await query(`DELETE FROM workspace_members WHERE workspace_id=$1 AND user_id=$2`, [req.auth!.workspaceId, req.params.userId]);
  res.json({ ok: true });
});
