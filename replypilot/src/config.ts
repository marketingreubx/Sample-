import { createHash } from "node:crypto";

function bool(v: string | undefined, def = false): boolean {
  if (v === undefined || v === "") return def;
  return ["1", "true", "yes", "on"].includes(v.toLowerCase());
}

function int(v: string | undefined, def: number): number {
  const n = Number(v);
  return Number.isFinite(n) && n > 0 ? Math.floor(n) : def;
}

export interface AppConfig {
  nodeEnv: string;
  isProduction: boolean;
  port: number;
  appUrl: string;
  databaseUrl: string;
  redisUrl: string;
  sessionSecret: string;
  encryptionKey: Buffer;
  demoMode: boolean;
  allowSignup: boolean;
  allowedEmails: string[];
  graphVersion: string;
  instagramAppId?: string;
  instagramAppSecret?: string;
  facebookAppSecret?: string;
  metaAppId?: string;
  metaLoginConfigId?: string;
  webhookVerifyToken: string;
  privateReplyMaxPerHour: number;
  dmMaxPerHour: number;
  workerConcurrency: number;
  trustProxy: boolean;
}

let cached: AppConfig | null = null;

export function loadConfig(env: NodeJS.ProcessEnv = process.env): AppConfig {
  const demoMode = bool(env.DEMO_MODE);
  const nodeEnv = env.NODE_ENV ?? "development";
  const isProduction = nodeEnv === "production";
  const errors: string[] = [];

  const need = (name: string, demoFallback?: string): string => {
    const v = env[name];
    if (v) return v;
    if (demoMode && demoFallback !== undefined) return demoFallback;
    errors.push(`${name} is required`);
    return "";
  };

  const sessionSecret = need("SESSION_SECRET", "demo-session-secret-change-me");
  let keyHex = env.ENCRYPTION_KEY ?? "";
  if (!keyHex && demoMode) {
    keyHex = createHash("sha256").update("replypilot-demo-key").digest("hex");
  }
  if (!/^[0-9a-fA-F]{64}$/.test(keyHex)) {
    errors.push("ENCRYPTION_KEY must be 64 hex characters (openssl rand -hex 32)");
  }

  const cfg: AppConfig = {
    nodeEnv,
    isProduction,
    port: int(env.PORT, 3000),
    appUrl: (env.APP_URL ?? "http://localhost:3000").replace(/\/$/, ""),
    databaseUrl: need("DATABASE_URL"),
    redisUrl: env.REDIS_URL ?? "redis://localhost:6379",
    sessionSecret,
    encryptionKey: Buffer.from(keyHex || "0".repeat(64), "hex"),
    demoMode,
    allowSignup: bool(env.ALLOW_SIGNUP, true),
    allowedEmails: (env.ALLOWED_EMAILS ?? "")
      .split(",")
      .map((e) => e.trim().toLowerCase())
      .filter(Boolean),
    graphVersion: env.META_GRAPH_API_VERSION ?? "v25.0",
    instagramAppId: env.INSTAGRAM_APP_ID || undefined,
    instagramAppSecret: env.INSTAGRAM_APP_SECRET || undefined,
    facebookAppSecret: env.FACEBOOK_APP_SECRET || undefined,
    metaAppId: env.META_APP_ID || undefined,
    metaLoginConfigId: env.META_LOGIN_CONFIG_ID || undefined,
    webhookVerifyToken: need("WEBHOOK_VERIFY_TOKEN", "demo-verify-token"),
    privateReplyMaxPerHour: int(env.PRIVATE_REPLY_MAX_PER_HOUR, 750),
    dmMaxPerHour: int(env.DM_MAX_PER_HOUR, 200),
    workerConcurrency: int(env.WORKER_CONCURRENCY, 5),
    trustProxy: bool(env.TRUST_PROXY, isProduction),
  };

  if (!demoMode && !cfg.instagramAppSecret && !cfg.facebookAppSecret) {
    errors.push(
      "INSTAGRAM_APP_SECRET or FACEBOOK_APP_SECRET is required unless DEMO_MODE=true"
    );
  }
  if (isProduction && !demoMode && !cfg.appUrl.startsWith("https://")) {
    errors.push("APP_URL must be an https:// URL in production (Meta requires HTTPS)");
  }
  if (errors.length) {
    throw new Error(`Invalid configuration:\n - ${errors.join("\n - ")}`);
  }
  return cfg;
}

export function config(): AppConfig {
  if (!cached) cached = loadConfig();
  return cached;
}

export function setConfigForTests(cfg: AppConfig | null) {
  cached = cfg;
}

/** Secrets Meta may sign webhooks with (Instagram Login vs Facebook/Unified login apps). */
export function webhookSecrets(cfg: AppConfig = config()): string[] {
  const s = [cfg.facebookAppSecret, cfg.instagramAppSecret].filter(
    (x): x is string => Boolean(x)
  );
  if (cfg.demoMode && s.length === 0) s.push(DEMO_APP_SECRET);
  return s;
}

export const DEMO_APP_SECRET = "demo-app-secret";

export function isUnifiedLogin(cfg: AppConfig = config()): boolean {
  return Boolean(cfg.metaLoginConfigId && cfg.metaAppId);
}
