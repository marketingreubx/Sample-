# Deploying ReplyPilot on public HTTPS

ReplyPilot is four pieces. All of them must run all the time:

| Piece | What it does |
| --- | --- |
| **web** | Dashboard, API, OAuth callback, Meta webhook endpoint, tracked-link redirects (`node dist/server/index.js`) |
| **worker** | Consumes the Redis queue and sends DMs / replies; refreshes tokens daily (`node dist/worker/index.js`) |
| **PostgreSQL 14+** | Users, workspaces, accounts, campaigns, logs, clicks |
| **Redis 6+** | Job queue (BullMQ), rate limiter, locks. Must support blocking commands (not an HTTP-only Redis) and should use `maxmemory-policy noeviction`. |

`web` and `worker` must share the **same** `DATABASE_URL`, `REDIS_URL`, `ENCRYPTION_KEY` and `SESSION_SECRET`. Different encryption keys = the worker can't decrypt tokens. Migrations run automatically when either process starts (safe to run concurrently).

Serverless platforms (e.g. Vercel functions) can host only `web`; the worker must be an always-on process.

---

## Option A — one VPS with Docker + Caddy (recommended, ~$5–12/month)

Any Ubuntu 22.04/24.04 server with 1 GB+ RAM (Hetzner, DigitalOcean, Lightsail…).

1. 🔑 Buy/point a domain: create a DNS **A record** `dm.yourdomain.com → your server's IP`.
2. SSH in and install Docker:
   ```bash
   curl -fsSL https://get.docker.com | sh
   ```
3. Get the code onto the server (git clone your repo, or `scp` the `replypilot` folder), then:
   ```bash
   cd replypilot
   cp .env.example .env
   nano .env
   ```
   Set at least:
   ```ini
   DOMAIN=dm.yourdomain.com
   APP_URL=https://dm.yourdomain.com
   NODE_ENV=production
   SESSION_SECRET=<openssl rand -base64 32>
   ENCRYPTION_KEY=<openssl rand -hex 32>
   POSTGRES_PASSWORD=<a long random password>
   DEMO_MODE=false            # true is fine for a first smoke test
   WEBHOOK_VERIFY_TOKEN=<any random string>
   # + the Meta variables from docs/META_SETUP.md
   ALLOWED_EMAILS=you@yourdomain.com   # recommended for a private instance
   ```
4. Open ports 80 and 443 (e.g. `ufw allow 80,443/tcp`), then start everything:
   ```bash
   docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
   ```
   Caddy obtains a Let's Encrypt certificate automatically on first request.
5. Check: `https://dm.yourdomain.com/api/health` → `"status":"ok"` and `"worker":{"healthy":true}`.
6. Update later: `git pull && docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build`.

Backups: `docker compose exec postgres pg_dump -U replypilot replypilot > backup.sql` (schedule it with cron).

## Option B — Render / Railway (managed, no server admin)

1. Create a **PostgreSQL** and a **Redis** instance on the platform.
2. Create a **Web Service** from this repo (root directory `replypilot`), runtime Docker (uses the `Dockerfile`), port 3000.
3. Create a **Background Worker** from the same repo/Dockerfile with start command `node dist/worker/index.js`.
4. Give both services the same environment variables (`DATABASE_URL`, `REDIS_URL` from step 1, plus everything in `.env.example`). Set `APP_URL` to the web service's `https://…` URL and `TRUST_PROXY=true`.
5. Use the platform's internal URLs for the database/Redis when both services are on the same platform.

## After deploying

- Point Meta's webhook and OAuth redirect at the new domain ([META_SETUP.md](META_SETUP.md)). If you change domains, update **both** in Meta and `APP_URL`, or tracked links and logins break. Meta does not reliably follow redirects for webhooks.
- Turn off open sign-up for a private instance: `ALLOW_SIGNUP=false` (after creating your user) or `ALLOWED_EMAILS=…`.
- Logs: `docker compose logs -f web worker`.

## Security notes

- Instagram tokens are encrypted at rest with AES-256-GCM (`ENCRYPTION_KEY`) and never returned by the API. Back up the key: losing it means reconnecting every account.
- Webhooks are rejected unless `X-Hub-Signature-256` matches your app secret.
- Sessions are HttpOnly, SameSite=Lax, Secure on HTTPS; state-changing API calls also require an `X-Requested-With` header (CSRF defence). Login/sign-up are rate limited per IP.
- Keep Postgres and Redis off the public internet (the compose file doesn't publish their ports).
