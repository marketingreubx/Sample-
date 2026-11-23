import { resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { DelayedError, UnrecoverableError, Worker, type Job } from "bullmq";
import { config } from "../config.js";
import { migrate } from "../db/migrate.js";
import { closeDb, logOp, query } from "../db/pool.js";
import { decryptSecret, encryptSecret } from "../lib/crypto.js";
import { refreshInstagramToken } from "../lib/meta/client.js";
import { MetaApiError } from "../lib/meta/errors.js";
import { QUEUE_NAME, closeRedis, getQueue, getRedis, newRedisConnection, type JobData } from "../lib/redis.js";
import { processComment, processMessage, type ProcessCtx } from "./processor.js";

class DeferredError extends DelayedError {
  deferred = true;
}

export async function refreshExpiringTokens(): Promise<number> {
  // Instagram Login tokens: 60-day life, refreshable once at least 24h old. Refresh those expiring within 10 days.
  const rows = await query<{ id: string; username: string; access_token_enc: string }>(
    `SELECT id, username, access_token_enc FROM instagram_accounts
      WHERE login_type='instagram' AND status='active'
        AND token_expires_at IS NOT NULL AND token_expires_at < now() + interval '10 days'
        AND updated_at < now() - interval '1 day'`
  );
  let ok = 0;
  for (const r of rows) {
    try {
      const t = await refreshInstagramToken(decryptSecret(r.access_token_enc));
      await query(
        `UPDATE instagram_accounts SET access_token_enc=$2, token_expires_at=$3, updated_at=now() WHERE id=$1`,
        [r.id, encryptSecret(t.accessToken), t.expiresAt]
      );
      ok++;
    } catch (e) {
      const expired = e instanceof MetaApiError && e.kind === "token";
      if (expired) {
        await query(`UPDATE instagram_accounts SET status='token_expired', status_detail=$2 WHERE id=$1`, [r.id, (e as Error).message]);
      }
      await logOp("error", "token_refresh", `Token refresh failed for @${r.username}`, { error: (e as Error).message });
    }
  }
  return ok;
}

export async function handleJob(job: Job<JobData>, token?: string): Promise<string> {
  const ctx: ProcessCtx = {
    async defer(ms: number): Promise<never> {
      await job.moveToDelayed(Date.now() + ms, token);
      throw new DeferredError();
    },
  };
  const data = job.data;
  switch (data.type) {
    case "comment":
      return processComment(data.event, ctx);
    case "message":
      return processMessage(data.event, ctx);
    case "refresh-tokens":
      return `refreshed:${await refreshExpiringTokens()}`;
    default:
      throw new UnrecoverableError(`Unknown job type ${(data as any)?.type}`);
  }
}

async function heartbeat() {
  await getRedis().set("rp:worker:heartbeat", String(Date.now()), "EX", 120);
}

async function main() {
  const cfg = config();
  await migrate(cfg.databaseUrl);
  const worker = new Worker<JobData>(QUEUE_NAME, handleJob, {
    connection: newRedisConnection(),
    concurrency: cfg.workerConcurrency,
  });
  worker.on("completed", (job, result) => console.log(`[worker] ${job.name} ${job.id} -> ${result}`));
  worker.on("failed", (job, err) => {
    console.error(`[worker] ${job?.name} ${job?.id} failed (attempt ${job?.attemptsMade}): ${err.message}`);
    if (job && job.attemptsMade >= (job.opts.attempts ?? 1)) {
      void logOp("error", "worker", `Job ${job.id} gave up after ${job.attemptsMade} attempts`, { error: err.message });
    }
  });

  await getQueue().upsertJobScheduler("refresh-tokens-daily", { pattern: "17 3 * * *" }, {
    name: "refresh-tokens",
    data: { type: "refresh-tokens" },
  });

  await heartbeat();
  const hb = setInterval(() => void heartbeat().catch(() => {}), 30_000);
  console.log(`[worker] listening on queue ${QUEUE_NAME} (concurrency ${cfg.workerConcurrency}, demo=${cfg.demoMode})`);

  const shutdown = async () => {
    clearInterval(hb);
    await worker.close();
    await closeRedis();
    await closeDb();
    process.exit(0);
  };
  process.on("SIGTERM", shutdown);
  process.on("SIGINT", shutdown);
}

const isMain = Boolean(process.argv[1]) && fileURLToPath(import.meta.url) === resolve(process.argv[1]);
if (isMain) {
  main().catch((e) => {
    console.error("[worker] fatal", e);
    process.exit(1);
  });
}
