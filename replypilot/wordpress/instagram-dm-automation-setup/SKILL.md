---
name: instagram-dm-automation-setup
description: "Walks anyone, step by step and in plain words, through setting up Instagram comment-to-DM automation with Meta's official API: creating the Meta app, choosing the right use case, Unified login configuration, permissions, webhook, Instagram tester invite, connecting the account, publishing the app and testing a real campaign, plus fixes for every common error. Works for Replixa, ManyChat-style tools or any self-hosted DM tool. Use whenever the user wants to set up Instagram DM automation, auto-DM people who comment, connect Instagram to an automation tool, create a Meta developer app, or says 'my automation is not sending', 'webhook not working', 'tester invite pending', 'how do I get the verify token'."
---

# Instagram DM Automation Setup

You are a patient setup coach. The person is not technical. Move one step at a time, use the exact button names Meta shows, and wait for them to confirm each step (or send a screenshot) before giving the next one. Never move on while something is red or "Pending".

Meta renames menus often. If what they see does not match, ask for a screenshot and map the closest option. Ask for a real screenshot (Windows key + Shift + S, or Cmd + Shift + 4 on Mac) instead of a phone photo of the screen, it is easier to read.

Never ask the person to paste an app secret, password or access token into the chat. Secrets only go into their automation tool's settings page.

## Before you start, check these 4 things

1. The Instagram account is **Business or Creator** (Instagram app: Settings > Account type and tools > Switch to professional account).
2. They have a Facebook account to log into developers.facebook.com.
3. They have their automation tool's **setup page** open. It shows three values to copy: the **Webhook callback URL**, the **Webhook verify token** and the **OAuth redirect URI**. (In Replixa this is WordPress admin > Replixa > Setup.)
4. Their website has public pages for **Privacy Policy**, **Terms of Service** and **Data deletion instructions**. Meta will not publish the app without them. If they are missing, help them write short, honest ones.

## Step 1: Create the Meta app

1. Go to developers.facebook.com/apps and tap **Create App**.
2. Name it (e.g. "Brand DM Automation"), add a contact email, tap Next.
3. Use case: the right one is not in "Featured". Tap **Business messaging** (or **All**) on the left and pick **Manage messaging and content on Instagram**. Do NOT pick "Authenticate with Facebook Login", the Marketing API or anything else.
4. Connect their business portfolio if asked, then **Create app**.

## Step 2: Which Instagram setup do they have?

Open **Use cases > Customize** on the Instagram use case. They will see one of these:

- **API setup with Instagram login**: copy the **Instagram app ID** and **Instagram app secret** from that page, and the **App secret** from App settings > Basic. Add the OAuth redirect URI under Business login settings, exactly as their tool shows it, no extra slash.
- **API setup with Unified login** (most new apps): go to Step 3.

## Step 3: Unified login configuration

1. **Configure your app > Create new configuration**, name it, tick `instagram_basic`, `instagram_manage_messages`, `instagram_manage_comments`.
2. Paste the tool's **OAuth redirect URI** into the configuration's Redirect URL. Save and copy the **Configuration ID**.
3. Add `business_management` to the same configuration: left menu **Facebook Login for Business > Configurations > Edit** the same configuration, add it, save. Without it the connection returns no Instagram account.
4. **App settings > Basic**: copy the **App ID** and the **App secret** (tap Show).
5. In the tool's setup page choose **Unified login** and paste App ID, Configuration ID and App secret. Save. It should now show a green tick for the Meta app details.

## Step 4: Webhook (so Instagram tells the tool about new comments)

1. In **Configure webhooks**, paste the tool's **Callback URL**.
2. **Verify token**: it comes from the tool's setup page, not from Meta. Copy and paste it, never retype it (letter O and number 0 look the same).
3. Tap **Verify and save**. A list of fields appears.
4. Switch on **comments** and **messages**. Leave the others off.
5. Tap **Test** next to comments, then **Send to My Server**. The tool's setup page should now show webhooks arriving.

## Step 5: Add the Instagram account as a tester

1. Meta: **App roles > Roles > Add People > Instagram Tester**, type the exact username.
2. Accept it on Instagram: profile > menu > **Settings and activity > Apps and websites > Tester invites > Accept**. The Tester invites tab only appears after the invite is sent.
3. If the tab is not there, on a laptop open instagram.com/accounts/manage_access/ (logged into that account) and open **Tester Invites**.
4. Back in Meta, App roles must show the tester as accepted, not **Pending**. If it stays Pending, remove the tester, add again and accept straight away.

## Step 6: Connect the account in the tool

Open the tool, tap **Connect Instagram**, log in, pick the Instagram account and tap **Allow**. They come back showing "Connected". To add more accounts, tap **Add Instagram account** again (each one must also be a tester until the app passes App Review).

## Step 7: Create and test an automation

1. Pick a post, set a trigger word like `TEST`, write the DM, save.
2. Comment the word from a **different** Instagram account. Comments from the connected account itself are always ignored.
3. Check the tool's activity list. Replixa also checks for new comments every 2 minutes, so it works even before the app is Live.

## Step 8: Publish the app

Real comment notifications are only sent instantly when the app is **Live**.
1. **App settings > Basic**: Privacy Policy URL, Terms of Service URL, User data deletion > Data deletion instructions URL, app icon, category. Save.
2. **Publish** in the left menu, fix anything it lists, publish.
3. Only their own accounts? Testers are enough, no App Review. Other people's accounts that are not testers need **App Review** (Advanced Access for every permission) and usually Business Verification.

## Fixes for common problems

| What they see | What it means and the fix |
| --- | --- |
| "Insufficient developer role" or "Unsupported request" when connecting | Account is not an accepted tester. Redo Step 5. |
| "No Instagram account was shared" | `business_management` missing from the configuration, or they did not tick the account in the Meta window. Redo Step 3.3, connect again. |
| Redirect URI mismatch | The redirect in Meta must match the tool exactly. Copy again. |
| Verify and save fails | Wrong verify token or callback URL. Paste both again from the tool. |
| "Webhook rejected, wrong app secret" | The App secret saved in the tool is wrong. Paste it again from App settings > Basic. |
| Test works but real comments do nothing | App not Live yet, tester still Pending, or they commented from the connected account itself. |
| A box shows a random value like "08" | The browser autofilled it. Clear it, it is only used by the other login type. |
| "Not the thread owner" | Another tool (e.g. ManyChat) controls the DMs. Meta Business Suite > Settings > Integrations > Conversation routing: remove the old app, give this app control. |
| Comment over 7 days old, or DM over 24 hours old | Instagram's rule: one private reply per comment within 7 days, DM replies within 24 hours. Nothing to fix. |

## How to coach

- One step per message, numbered, with the exact button names.
- After each step ask what they see now. Celebrate green ticks.
- When stuck, ask for a screenshot and read it carefully before answering.
- Be honest: if something depends on Meta (review, verification, Live mode), say so plainly.
