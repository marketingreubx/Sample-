import {
  createCipheriv,
  createDecipheriv,
  createHash,
  createHmac,
  randomBytes,
  timingSafeEqual,
} from "node:crypto";
import { config } from "../config.js";

const ALGO = "aes-256-gcm";
const IV_LEN = 12;
const TAG_LEN = 16;
const VERSION = "v1";

/** Encrypts a secret (e.g. an Instagram access token) with AES-256-GCM. Output: v1.<base64(iv|tag|ciphertext)> */
export function encryptSecret(plaintext: string, key: Buffer = config().encryptionKey): string {
  const iv = randomBytes(IV_LEN);
  const cipher = createCipheriv(ALGO, key, iv);
  const enc = Buffer.concat([cipher.update(plaintext, "utf8"), cipher.final()]);
  return `${VERSION}.${Buffer.concat([iv, cipher.getAuthTag(), enc]).toString("base64")}`;
}

export function decryptSecret(value: string, key: Buffer = config().encryptionKey): string {
  const [version, body] = value.split(".");
  if (version !== VERSION || !body) throw new Error("Unsupported ciphertext format");
  const buf = Buffer.from(body, "base64");
  const iv = buf.subarray(0, IV_LEN);
  const tag = buf.subarray(IV_LEN, IV_LEN + TAG_LEN);
  const data = buf.subarray(IV_LEN + TAG_LEN);
  const decipher = createDecipheriv(ALGO, key, iv);
  decipher.setAuthTag(tag);
  return Buffer.concat([decipher.update(data), decipher.final()]).toString("utf8");
}

export function sha256(value: string): string {
  return createHash("sha256").update(value).digest("hex");
}

export function randomToken(bytes = 32): string {
  return randomBytes(bytes).toString("base64url");
}

export function hmac(secret: string, value: string, encoding: "hex" | "base64url" = "hex") {
  return createHmac("sha256", secret).update(value).digest(encoding);
}

export function safeEqual(a: string, b: string): boolean {
  const ab = Buffer.from(a);
  const bb = Buffer.from(b);
  return ab.length === bb.length && timingSafeEqual(ab, bb);
}

/** Signed, expiring OAuth state so the callback can't be forged or replayed later. */
export function createSignedState(data: Record<string, string>, secret = config().sessionSecret) {
  const payload = Buffer.from(JSON.stringify({ ...data, ts: Date.now(), n: randomToken(8) })).toString(
    "base64url"
  );
  return `${payload}.${hmac(secret, payload, "base64url")}`;
}

export function verifySignedState(
  state: string | undefined | null,
  maxAgeMs = 10 * 60 * 1000,
  secret = config().sessionSecret
): Record<string, string> | null {
  if (!state) return null;
  const [payload, sig] = state.split(".");
  if (!payload || !sig || !safeEqual(sig, hmac(secret, payload, "base64url"))) return null;
  try {
    const parsed = JSON.parse(Buffer.from(payload, "base64url").toString("utf8"));
    const age = Date.now() - Number(parsed.ts);
    if (!Number.isFinite(age) || age < 0 || age > maxAgeMs) return null;
    return parsed;
  } catch {
    return null;
  }
}
