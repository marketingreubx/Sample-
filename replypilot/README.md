# ReplyPilot

**Self-hosted Instagram DM automation.** When someone comments a keyword on your post, DMs you a keyword, or replies to your Story, ReplyPilot sends them a personalized DM (with a tracked link) through Meta's official Instagram API — and optionally replies publicly under their comment.

Built with Node.js/TypeScript (Express API + BullMQ worker), React, PostgreSQL and Redis. Functionally modelled on [OpenReply](https://github.com/diwenne/openreply) (MIT) — see [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).

## Features

- **Accounts & teams** — email/password sign-in, multiple workspaces (one per client), owner/admin/member roles.
- **Instagram connection** — official OAuth (Instagram login *or* Meta Unified login), webhooks auto-subscribed, tokens encrypted (AES-256-GCM) and auto-refreshed.
- **Keyword campaigns** — triggers: comments on one post or any post, inbound DMs, Story replies. Case/emoji/accent-insensitive matching, whole-word option, or "any message".
- **Messages** — `{username}`, `{keyword}`, `{link}` personalization; link inline or as a tappable button; optional public comment replies with random variations.
- **Tracked links & analytics** — every link goes through `/r/<slug>`, counted per recipient (bots filtered). Dashboard + per-campaign stats: triggers, sent, failed, skipped, clicks, unique clicks, CTR, daily chart, top keywords.
- **Message logs** — every trigger, the exact DM sent, and the precise Meta error or rule behind any failure/skip.
- **Reliability** — webhook signature verification, 3-layer duplicate prevention (body hash, deterministic job IDs, per-campaign DB uniqueness + per-event locks), retries with exponential backoff, per-account hourly rate limits, automatic pause when Meta throttles.
- **Meta rule enforcement** — one private reply per comment within 7 days, DM replies only within the 24h window, no self-replies/loops, message length limits. See [docs/META_SETUP.md](docs/META_SETUP.md#messaging-rules-replypilot-enforces-so-your-app-stays-compliant).
- **Demo mode** — try everything with zero Meta credentials: fake accounts, a "Demo lab" that fires signed webhooks at your server, and an outbox showing what would have been sent.

## How it works

```
Instagram ──webhook──▶ web (/api/webhook) ──verify signature, dedup──▶ Redis queue
                                                                         │
            Postgres ◀── logs/stats ── worker ◀────────────────────────┘
                                         │  match campaign → check Meta rules → rate limit
                                         └──▶ Instagram Graph API (private reply / DM / comment reply)
```

---

## Quick start (demo mode, ~5 minutes)

You need **Docker Desktop** ([download](https://www.docker.com/products/docker-desktop/)). Nothing else.

```bash
cd replypilot
cp .env.example .env          # DEMO_MODE=true is already set
docker compose up -d --build
```

Open **http://localhost:3000**, then:

1. **Create account** (any email/password — it's your local instance).
2. **Instagram accounts → Add demo account.**
3. **Campaigns → New campaign**: pick a post, add keyword `GUIDE`, keep the sample DM, add a link like `https://example.com`, tick *public comment reply* → **Save**.
4. **Demo lab → Send webhook** (comment "GUIDE please!"). Within a second the outbox shows the private-reply DM and the public reply, and **Message logs** shows `sent`.
5. Click the tracked link from the outbox → the campaign's click count goes up.

Stop: `docker compose down` (add `-v` to wipe the data).

> In demo mode nothing is sent to Instagram. Demo mode works without `SESSION_SECRET`/`ENCRYPTION_KEY`, but set real ones before putting anything online.

## Going live with real Instagram

1. **Deploy on public HTTPS** → [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) (VPS + Docker + automatic HTTPS, or Render/Railway).
2. **Create and configure the Meta app, connect your account, publish, and test a real comment-to-DM campaign** → [docs/META_SETUP.md](docs/META_SETUP.md).

What needs *you* (can't be automated):

| Step | Why |
| --- | --- |
| 🔑 Meta developer login, app creation, copying app ID/secrets into `.env` | Your credentials |
| 🔑 Instagram professional account; accepting the tester invite in the Instagram app | Meta requires the account owner's consent |
| 🔑 Domain + server (or a tunnel for testing) | Meta only talks to public HTTPS |
| ⏳ Publishing the app (Live mode) | Real comment webhooks only arrive for Live apps |
| ⏳ App Review + Business Verification | Only needed to connect accounts that aren't testers on your app (e.g. self-serve clients) |

## Local development (without Docker for the app)

Requirements: Node.js 22+, PostgreSQL 14+, Redis 6+ (or run just those two with `docker compose up -d postgres redis` and expose their ports).

```bash
npm install
cp .env.example .env          # point DATABASE_URL / REDIS_URL at your local services
npm run dev                   # API + webhook on :3000 (runs migrations)
npm run worker                # background worker (second terminal)
npm run dev:web               # dashboard with hot reload on :5173 (third terminal)
```

Or build once and serve the dashboard from :3000: `npm run build && npm start` + `npm run start:worker`.

Useful scripts: `npm run migrate`, `npm run typecheck`, `npm run build`.

## Run the tests

```bash
npm test
```

The suite has unit tests (keyword matching, templating, signature verification, webhook parsing, Meta error mapping, crypto, config, validation) and integration tests that run against **real PostgreSQL and Redis**: the full webhook → queue → worker → send flow, duplicate suppression, the 7-day and 24-hour rules, one-private-reply-per-comment, retries, token expiry, throttling/rate limits, workspace isolation, CSRF, tracked-link clicks and the demo flow.

By default tests use `postgresql://postgres@localhost:5432/replypilot_test` and Redis DB 15. Override with `TEST_DATABASE_URL` / `TEST_REDIS_URL`. Create the DB once: `createdb replypilot_test`. If Postgres/Redis aren't reachable, integration tests are skipped with a warning.

## Configuration

All settings live in `.env` — every variable is explained in [.env.example](.env.example). The important ones:

| Variable | Purpose |
| --- | --- |
| `APP_URL` | Public URL (https in production). Used for OAuth redirect, tracked links, cookies. |
| `SESSION_SECRET`, `ENCRYPTION_KEY` | Generate with `openssl rand -base64 32` / `openssl rand -hex 32`. Same values on web and worker. |
| `DEMO_MODE` | `true` = fake Instagram; `false` = real Meta API. |
| `WEBHOOK_VERIFY_TOKEN` | Must match the token you type in Meta's webhook settings. |
| `INSTAGRAM_APP_ID`, `INSTAGRAM_APP_SECRET`, `FACEBOOK_APP_SECRET` | Instagram-login apps. |
| `META_APP_ID`, `META_LOGIN_CONFIG_ID` | Unified-login apps (newer Meta apps). |
| `PRIVATE_REPLY_MAX_PER_HOUR`, `DM_MAX_PER_HOUR` | Per-account send caps. |
| `ALLOW_SIGNUP`, `ALLOWED_EMAILS` | Lock down who can create accounts. |

## Project layout

```
src/
  config.ts              env parsing/validation
  db/                    pg pool, migration runner, SQL migrations
  lib/
    meta/                Graph API client (real + demo), webhook parsing/signatures, error mapping, OAuth
    keywords.ts          keyword matcher
    templating.ts        personalization, limits, tracked URLs
    restrictions.ts      Meta messaging rules
    redis.ts             BullMQ queue, sliding-window rate limiter, locks
  server/                Express app, auth/sessions, routes, webhook ingest, legal pages
  worker/                BullMQ worker + campaign processor
web/                     React dashboard (Vite)
tests/                   Vitest unit + integration tests
docs/                    META_SETUP.md, DEPLOYMENT.md
```

## API endpoints (for reference)

`GET/POST /api/webhook` (Meta) · `GET /api/instagram/callback` (OAuth) · `GET /r/:slug` (tracked link) · `GET /api/health` · `/privacy` `/terms` `/data-deletion` · authenticated JSON API under `/api` (`auth`, `workspaces`, `accounts`, `campaigns`, `logs`, `analytics`, `demo`). State-changing calls need the header `X-Requested-With: replypilot`.

## Limitations

- Instagram only (no Facebook Messenger); no broadcast/bulk messaging by design (Meta forbids unsolicited messages).
- Unified-login tokens aren't auto-refreshed — reconnect before the expiry shown on the Accounts page.
- Live Instagram delivery depends on your Meta app's status, permissions and Meta's own rules; it can only be verified with your app and account (see docs/META_SETUP.md step 7).

## License

MIT — see [LICENSE](LICENSE) and [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
