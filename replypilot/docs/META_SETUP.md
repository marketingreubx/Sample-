# Meta app setup: connect a real Instagram account

This is the slow part. The code is ready; Meta's console is where people lose an afternoon. Follow the steps in order.

> **Legend**
> 🔑 = needs your credentials / your Meta or Instagram login
> ⏳ = needs approval from Meta (or an invite you must accept)
> Meta renames console menus often. If a label below doesn't match exactly, look for the closest equivalent; the concepts don't change.

---

## 0. Before you start

- [ ] ReplyPilot is deployed at a **public HTTPS URL** (see [DEPLOYMENT.md](DEPLOYMENT.md)). Meta won't call `http://` or `localhost`. For a quick local test, use a tunnel: `cloudflared tunnel --url http://localhost:3000` or `ngrok http 3000`, then set `APP_URL` to the `https://…` URL it prints and restart.
- [ ] 🔑 The Instagram account is a **Professional account (Business or Creator)**. In the Instagram app: Settings → Account type and tools → Switch to professional account.
- [ ] 🔑 You have a Facebook account to log into [developers.facebook.com](https://developers.facebook.com) (register as a developer if asked).
- [ ] Open `https://YOUR-DOMAIN/privacy`, `/terms` and `/data-deletion` — they must load. Set `LEGAL_OPERATOR_NAME` and `LEGAL_CONTACT_EMAIL` in `.env` so they show your details.

## 1. Create the Meta app 🔑

1. Go to [developers.facebook.com/apps](https://developers.facebook.com/apps) → **Create app**.
2. App type / use case: choose **Manage messaging and content on Instagram** (filter by "All" if you don't see it). Do **not** pick "Authenticate with Facebook Login" or the Marketing API use case.
3. Business portfolio: connect yours (or create one). Contact email: one you check.
4. Open the use case → **Customize**. Meta shows one of two setups. Note which one you have:
   - **API setup with Instagram login** → follow 2A.
   - **API setup with Unified login only** (apps created from about October 2026 get only this) → follow 2B.

## 2A. Instagram login setup 🔑

1. In **API setup with Instagram login**, copy:
   - **Instagram app ID** → `INSTAGRAM_APP_ID`
   - **Instagram app secret** (click Show) → `INSTAGRAM_APP_SECRET`
   - (Note: this is *not* the Facebook App ID on the Basic settings page.)
2. **App settings → Basic → App secret** → `FACEBOOK_APP_SECRET`. Set both secrets; ReplyPilot verifies webhook signatures against either.
3. **Set up Instagram business login → Business login settings → OAuth redirect URIs**, add exactly:
   ```
   https://YOUR-DOMAIN/api/instagram/callback
   ```
   No trailing slash.
4. Permissions requested by ReplyPilot: `instagram_business_basic`, `instagram_business_manage_messages`, `instagram_business_manage_comments`.

## 2B. Unified login setup 🔑

1. In **API setup with Unified login → Configure your app → Create new configuration**. Include `instagram_basic`, `instagram_manage_messages`, `instagram_manage_comments`.
2. Add **`business_management`** to the same configuration. The Unified login editor may not offer it: add the **Facebook Login for Business** product, open its **Configurations**, edit the *same* configuration (same ID) and add `business_management`. Without it Meta returns no Instagram accounts and ReplyPilot shows "No Instagram account was granted".
3. Configuration **Redirect URL**: `https://YOUR-DOMAIN/api/instagram/callback`
4. Set in `.env`:
   - `META_LOGIN_CONFIG_ID` = the configuration's ID
   - `META_APP_ID` = App settings → Basic → **App ID**
   - `FACEBOOK_APP_SECRET` = App settings → Basic → **App secret**
5. Unified login tokens expire when the configuration says (shown on the Accounts page). Reconnect before then; Instagram-login tokens are refreshed automatically by the worker.

## 3. Configure the webhook 🔑

In the use case's **Configure webhooks** step (same tab as your setup above):

| Field | Value |
| --- | --- |
| Callback URL | `https://YOUR-DOMAIN/api/webhook` |
| Verify token | the value of `WEBHOOK_VERIFY_TOKEN` in your `.env` |

Click **Verify and save** — it succeeds immediately if the app is running and the token matches. Then **subscribe to both fields: `comments` and `messages`**.

- `comments` → comment-to-DM
- `messages` → DM keyword triggers and Story replies

Test it: click **Test** next to `comments`, then **Send to My Server**. In ReplyPilot → Settings → *Recent webhook deliveries* a row appears (status `ignored`, because Meta's sample account isn't connected — that's expected and proves signature verification works).

## 4. Add yourself as a tester ⏳

In Development mode (and with Standard Access after publishing) **only Instagram accounts with a role on the app can connect.** Two halves, both required:

1. Meta side: **App roles → Roles → Instagram testers → Add**, enter the exact Instagram username. (Newer consoles also show this under the use case's *Generate access tokens → Add account*.)
2. ⏳ Instagram side, logged in as that account (phone app is easiest): **Profile → Menu → Settings and activity → Apps and websites → Tester invites → Accept**.

Skipping half 2 causes "Insufficient developer role", or later every Graph call fails with `Unsupported request - method type: get [code=100]`.

## 5. Connect the account in ReplyPilot 🔑

1. Make sure `.env` has `DEMO_MODE=false` and the Meta variables, then restart (`docker compose up -d`).
2. Sign in → **Instagram accounts → Connect Instagram** → log in as the tester account → approve all permissions.
3. You land back on the Accounts page with "Connected 1 account(s)". Check the card says **Webhooks: Subscribed**. If not, click Reconnect.

## 6. Publish the app (go Live) ⏳

**Real comment webhooks are only delivered when the app is Live.** In Development mode only the console's Test button delivers. This is the #1 reason for "I set it up and nothing happens".

1. **App settings → Basic**: fill Privacy Policy URL (`https://YOUR-DOMAIN/privacy`), Terms URL (`/terms`), User data deletion → *Data deletion instructions URL* (`/data-deletion`), app icon, category.
2. Left sidebar → **Publish** → Publish. Depending on your business verification status Meta may let you go Live right away for your tester accounts.

## 7. Test a real comment-to-DM campaign

1. **Campaigns → New campaign** → pick the connected account → pick a post → keyword `TEST` → DM text `Hi {username}! Here's the link: {link}` → link `https://example.com` → Save.
2. From a **different** Instagram account (your own comments are ignored on purpose), comment `TEST` on that post.
3. Within a few seconds: the DM arrives as a message request/DM for that account, and **Message logs** shows `sent`.
4. Tap the link → the campaign's click count goes up.

If nothing arrives, check in this order:

| Where | What it tells you |
| --- | --- |
| Settings → System health | Worker must say *running*. If not, no DM is sent even though webhooks arrive. |
| Settings → Recent webhook deliveries | No new row = Meta isn't sending (app not Live, field not subscribed, wrong callback URL, account not connected). |
| Message logs → click the row | The exact Meta error or the rule that skipped it. |

Common errors:

| Error in logs | Fix |
| --- | --- |
| `code=190` / account shows *token expired* | Reconnect the account. |
| `The action is invalid since it's not the thread owner … sub=2534037` | Another tool (e.g. ManyChat) owns the conversation. Meta Business Suite → Settings → Integrations / **Conversation routing** → remove the old app and give ReplyPilot "Access all conversations" + "Take control of conversations". |
| `code=10` / `sub=2534022` (skipped: messaging window) | The person messaged more than 24h ago; Instagram doesn't allow a reply. |
| `code=100 … comment is invalid for a private reply` | The comment already got its one private reply, or it was deleted. |
| `code=4`, `17`, `613` | Meta throttled the account. ReplyPilot pauses that account 15 minutes and retries automatically. |

## 8. App Review: connecting accounts you don't control ⏳

A published app still has **Standard Access**, which only works for accounts with a role on your app (step 4). That's enough to run your own account, and client accounts *if* each client accepts a tester invite (up to Meta's tester limits).

To let any business connect without being a tester (for example agency clients who sign up themselves), request **Advanced Access** through **App Review** for every permission you use:

- Instagram login: `instagram_business_basic`, `instagram_business_manage_messages`, `instagram_business_manage_comments`
- Unified login: `instagram_basic`, `instagram_manage_messages`, `instagram_manage_comments`, `business_management`

Meta usually also requires **Business Verification** of your business portfolio. For each permission you'll need:

1. A written explanation of how it's used. Example for messages: *"When a person comments a keyword on a business's post or sends a keyword by DM, the app sends them one private reply containing the resource they requested. Businesses configure the keyword and message in the dashboard. Replies are only sent in response to user-initiated interactions, within Meta's 7-day private-reply and 24-hour messaging windows."*
2. A screencast showing: logging into ReplyPilot → Connect Instagram → creating a campaign → commenting from a test account → the DM arriving → the Message logs entry.
3. Test credentials for the reviewer (create a ReplyPilot user for them; leave `ALLOW_SIGNUP=true` or add their email to `ALLOWED_EMAILS`).

Review typically takes days to a few weeks and can bounce; answer reviewer questions precisely. **Never** claim a feature in review that the app doesn't do.

## Messaging rules ReplyPilot enforces (so your app stays compliant)

- One private reply per comment, ever, and only within 7 days of the comment.
- DM / Story-reply answers only within 24 hours of the person's message; one automated answer per message; 10-minute cooldown per person per campaign.
- Never messages the connected account itself; ignores echoes of its own messages and its own public replies (no loops).
- Per-account hourly caps (`PRIVATE_REPLY_MAX_PER_HOUR`, default 750 as documented by Meta; `DM_MAX_PER_HOUR`, default 200) plus a 15-minute pause whenever Meta throttles.
- Text capped at 1000 bytes, button text 640 chars, button label 20 chars.
- No unsolicited / bulk / follow-up marketing messages are possible in the product.
