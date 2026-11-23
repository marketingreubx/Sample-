import { Queue } from "bullmq";
import { Redis } from "ioredis";
import { config } from "../config.js";
import type { CommentEvent, InboundMessageEvent } from "./meta/webhook.js";

export const QUEUE_NAME = "replypilot-events";

let redis: Redis | null = null;
let queue: Queue | null = null;

export function getRedis(): Redis {
  if (!redis) {
    redis = new Redis(config().redisUrl, { maxRetriesPerRequest: null });
    redis.on("error", (e) => console.error("[redis]", e.message));
  }
  return redis;
}

export function newRedisConnection(): Redis {
  return new Redis(config().redisUrl, { maxRetriesPerRequest: null });
}

export type JobData =
  | { type: "comment"; event: CommentEvent }
  | { type: "message"; event: InboundMessageEvent }
  | { type: "refresh-tokens" };

export function getQueue(): Queue {
  if (!queue) {
    queue = new Queue(QUEUE_NAME, {
      connection: getRedis(),
      defaultJobOptions: {
        attempts: 6,
        backoff: { type: "exponential", delay: 30_000 }, // 30s, 1m, 2m, 4m, 8m
        // Keeping finished jobs for 7 days makes the jobId a 7-day dedup key.
        removeOnComplete: { age: 7 * 24 * 3600, count: 50_000 },
        removeOnFail: { age: 14 * 24 * 3600 },
      },
    });
  }
  return queue;
}

/** Deterministic job id per Instagram event, so the same event is never queued twice. */
export function jobIdFor(data: JobData): string {
  if (data.type === "comment") return `comment_${data.event.igAccountId}_${data.event.commentId}`;
  if (data.type === "message")
    return `message_${data.event.igAccountId}_${Buffer.from(data.event.messageId).toString("base64url")}`;
  return `refresh_${Date.now()}`;
}

export async function enqueueEvent(data: JobData) {
  return getQueue().add(data.type, data, { jobId: jobIdFor(data) });
}

export async function closeRedis() {
  await queue?.close();
  queue = null;
  if (redis) {
    redis.disconnect();
    redis = null;
  }
}

// ---------------- Rate limiting ----------------

const WINDOW_MS = 3600_000;

/**
 * Sliding-window limiter in a sorted set. Returns 0 when a slot was taken,
 * otherwise the milliseconds to wait. Also honors an account-wide pause set
 * after Meta throttles us.
 */
const ACQUIRE = `
local pause = redis.call('PTTL', KEYS[2])
if pause and pause > 0 then return pause end
local now = tonumber(ARGV[1])
local window = tonumber(ARGV[2])
local max = tonumber(ARGV[3])
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, now - window)
local count = redis.call('ZCARD', KEYS[1])
if count < max then
  redis.call('ZADD', KEYS[1], now, ARGV[4])
  redis.call('PEXPIRE', KEYS[1], window)
  return 0
end
local oldest = redis.call('ZRANGE', KEYS[1], 0, 0, 'WITHSCORES')
return math.max(1, tonumber(oldest[2]) + window - now)
`;

export type LimitKind = "private_reply" | "dm" | "comment_reply";

export function limitFor(kind: LimitKind): number {
  const c = config();
  return kind === "private_reply" ? c.privateReplyMaxPerHour : kind === "dm" ? c.dmMaxPerHour : c.privateReplyMaxPerHour;
}

export async function acquireSendSlot(
  igAccountId: string,
  kind: LimitKind,
  member: string,
  max = limitFor(kind),
  r: Redis = getRedis()
): Promise<number> {
  const wait = await r.eval(
    ACQUIRE,
    2,
    `rp:rl:${kind}:${igAccountId}`,
    `rp:pause:${igAccountId}`,
    Date.now(),
    WINDOW_MS,
    max,
    `${member}:${Date.now()}`
  );
  return Number(wait);
}

/** Called when Meta answers with a throttling error: stop sending from this account for a while. */
export async function pauseAccount(igAccountId: string, ms = 15 * 60_000, r: Redis = getRedis()) {
  await r.set(`rp:pause:${igAccountId}`, "1", "PX", ms);
}

/** Short lock so two workers never handle the same Instagram event concurrently. */
export async function acquireLock(key: string, ttlMs = 120_000, r: Redis = getRedis()): Promise<boolean> {
  return (await r.set(`rp:lock:${key}`, "1", "PX", ttlMs, "NX")) === "OK";
}

export async function releaseLock(key: string, r: Redis = getRedis()) {
  await r.del(`rp:lock:${key}`);
}

/** Fixed-window counter used to throttle login attempts and public endpoints. */
export async function hitCounter(key: string, windowSec: number, r: Redis = getRedis()): Promise<number> {
  const k = `rp:cnt:${key}`;
  const n = await r.incr(k);
  if (n === 1) await r.expire(k, windowSec);
  return n;
}
