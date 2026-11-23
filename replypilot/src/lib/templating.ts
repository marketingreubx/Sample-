import { createHash, createHmac, randomBytes } from "node:crypto";

/** Instagram rejects message text above 1000 bytes (UTF-8). */
export const MAX_TEXT_BYTES = 1000;
/** Button template text limit. */
export const MAX_BUTTON_TEXT = 640;
/** Button title limit. */
export const MAX_BUTTON_TITLE = 20;

export function truncateBytes(text: string, maxBytes = MAX_TEXT_BYTES): string {
  if (Buffer.byteLength(text, "utf8") <= maxBytes) return text;
  let out = "";
  for (const ch of text) {
    if (Buffer.byteLength(out + ch + "…", "utf8") > maxBytes) break;
    out += ch;
  }
  return out + "…";
}

export interface TemplateVars {
  username?: string | null;
  keyword?: string | null;
  link?: string | null;
}

/**
 * Personalizes a message. Supported tokens: {username}, {keyword}, {link}.
 * When a link is delivered as a button, pass link: null and {link} is removed.
 */
export function renderTemplate(message: string, vars: TemplateVars): string {
  let out = message
    .replace(/\{username\}/gi, vars.username ? `@${vars.username.replace(/^@/, "")}` : "there")
    .replace(/\{keyword\}/gi, vars.keyword ?? "");
  if (vars.link) {
    out = /\{link\}/i.test(out) ? out.replace(/\{link\}/gi, vars.link) : `${out}\n${vars.link}`;
  } else {
    out = out.replace(/\s*\{link\}\s*/gi, " ");
  }
  return out.replace(/[ \t]+\n/g, "\n").replace(/[ \t]{2,}/g, " ").trim();
}

export function pickRandom<T>(items: T[], rnd: () => number = Math.random): T | undefined {
  if (!items.length) return undefined;
  return items[Math.floor(rnd() * items.length)];
}

export function generateSlug(): string {
  return randomBytes(6).toString("base64url");
}

/** Keyed token so repeat taps by one recipient count once, without exposing their Instagram id. */
export function recipientToken(recipientId: string, secret: string): string {
  return createHmac("sha256", secret).update(`recipient:${recipientId}`).digest("base64url").slice(0, 22);
}

export function isRecipientToken(v: unknown): v is string {
  return typeof v === "string" && /^[A-Za-z0-9_-]{22}$/.test(v);
}

export function hashIp(ip: string | null | undefined, secret: string): string | null {
  if (!ip) return null;
  return createHash("sha256").update(`${secret}:${ip}`).digest("hex");
}

export function trackedUrl(appUrl: string, slug: string, token?: string): string {
  const base = `${appUrl.replace(/\/$/, "")}/r/${slug}`;
  return token ? `${base}?r=${token}` : base;
}

export function isSafeHttpUrl(value: string): boolean {
  try {
    const u = new URL(value);
    return u.protocol === "https:" || u.protocol === "http:";
  } catch {
    return false;
  }
}
