import { config } from "../config.js";
import { migrate } from "../db/migrate.js";
import { closeDb } from "../db/pool.js";
import { closeRedis } from "../lib/redis.js";
import { createApp } from "./app.js";

async function main() {
  const cfg = config();
  if (process.env.SKIP_MIGRATIONS !== "true") await migrate(cfg.databaseUrl);
  const server = createApp().listen(cfg.port, () => {
    console.log(`[web] ReplyPilot listening on :${cfg.port} (public URL ${cfg.appUrl}, demo=${cfg.demoMode})`);
  });
  const shutdown = () =>
    server.close(async () => {
      await closeRedis();
      await closeDb();
      process.exit(0);
    });
  process.on("SIGTERM", shutdown);
  process.on("SIGINT", shutdown);
}

main().catch((e) => {
  console.error("[web] fatal", e.message ?? e);
  process.exit(1);
});
