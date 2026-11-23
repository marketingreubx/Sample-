import type { NextFunction, Request, Response } from "express";
import { config } from "../config.js";
import { one, query } from "../db/pool.js";
import { randomToken, sha256 } from "../lib/crypto.js";

export const SESSION_COOKIE = "rp_session";
const SESSION_DAYS = 30;

export interface AuthedUser {
  id: string;
  email: string;
  name: string | null;
}

export interface AuthContext {
  user: AuthedUser;
  sessionId: string;
  workspaceId: string;
  role: "owner" | "admin" | "member";
}

declare module "express-serve-static-core" {
  interface Request {
    auth?: AuthContext;
  }
}

export function parseCookies(req: Request, _res: Response, next: NextFunction) {
  const out: Record<string, string> = {};
  for (const part of (req.headers.cookie ?? "").split(";")) {
    const i = part.indexOf("=");
    if (i < 0) continue;
    const k = part.slice(0, i).trim();
    if (k) out[k] = decodeURIComponent(part.slice(i + 1).trim());
  }
  req.cookies = out;
  next();
}

export async function createSession(req: Request, res: Response, userId: string, workspaceId: string | null) {
  const token = randomToken(32);
  const expires = new Date(Date.now() + SESSION_DAYS * 86400_000);
  await query(`INSERT INTO sessions(id, user_id, workspace_id, expires_at) VALUES ($1,$2,$3,$4)`, [
    sha256(token),
    userId,
    workspaceId,
    expires,
  ]);
  res.cookie(SESSION_COOKIE, token, {
    httpOnly: true,
    sameSite: "lax",
    // Always Secure in production on an https APP_URL; otherwise follow the request (lets local http dev work).
    secure: req.secure || (config().isProduction && config().appUrl.startsWith("https://")),
    expires,
    path: "/",
  });
}

export async function destroySession(req: Request, res: Response) {
  const token = req.cookies?.[SESSION_COOKIE];
  if (token) await query(`DELETE FROM sessions WHERE id=$1`, [sha256(token)]);
  res.clearCookie(SESSION_COOKIE, { path: "/" });
}

/** Loads the session (if any) and the active workspace membership. */
export async function loadAuth(req: Request, _res: Response, next: NextFunction) {
  try {
    const token = req.cookies?.[SESSION_COOKIE];
    if (!token) return next();
    const sid = sha256(token);
    const s = await one<{ user_id: string; workspace_id: string | null; email: string; name: string | null }>(
      `SELECT s.user_id, s.workspace_id, u.email, u.name FROM sessions s JOIN users u ON u.id=s.user_id
        WHERE s.id=$1 AND s.expires_at > now()`,
      [sid]
    );
    if (!s) return next();
    let m = s.workspace_id
      ? await one<{ workspace_id: string; role: AuthContext["role"] }>(
          `SELECT workspace_id, role FROM workspace_members WHERE user_id=$1 AND workspace_id=$2`,
          [s.user_id, s.workspace_id]
        )
      : null;
    if (!m) {
      m = await one(`SELECT workspace_id, role FROM workspace_members WHERE user_id=$1 ORDER BY created_at LIMIT 1`, [
        s.user_id,
      ]);
      if (m) await query(`UPDATE sessions SET workspace_id=$2 WHERE id=$1`, [sid, m.workspace_id]);
    }
    if (m) {
      req.auth = {
        user: { id: s.user_id, email: s.email, name: s.name },
        sessionId: sid,
        workspaceId: m.workspace_id,
        role: m.role,
      };
    }
    next();
  } catch (e) {
    next(e);
  }
}

export function requireAuth(req: Request, res: Response, next: NextFunction) {
  if (!req.auth) return res.status(401).json({ error: "Not signed in" });
  next();
}

export function requireRole(...roles: AuthContext["role"][]) {
  return (req: Request, res: Response, next: NextFunction) => {
    if (!req.auth) return res.status(401).json({ error: "Not signed in" });
    if (!roles.includes(req.auth.role)) return res.status(403).json({ error: "Your role can't do that" });
    next();
  };
}

/**
 * CSRF defence: state-changing API calls must carry a custom header, which a
 * cross-site form or image request cannot add. Cookies are also SameSite=Lax.
 */
export function requireCsrfHeader(req: Request, res: Response, next: NextFunction) {
  if (["GET", "HEAD", "OPTIONS"].includes(req.method)) return next();
  if (req.get("x-requested-with") !== "replypilot") {
    return res.status(403).json({ error: "Missing X-Requested-With header" });
  }
  next();
}
