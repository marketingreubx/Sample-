import { Router } from "express";
import { config } from "../config.js";

/**
 * Privacy policy, terms and data-deletion instructions. Meta requires these
 * URLs before an app can be published. Replace the text with your own policy
 * (operator name and contact email come from LEGAL_* env vars).
 */
export const legalRouter = Router();

function page(title: string, body: string) {
  const operator = process.env.LEGAL_OPERATOR_NAME ?? "the operator of this ReplyPilot instance";
  const contact = process.env.LEGAL_CONTACT_EMAIL ?? "the contact email shown on this site";
  return `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>${title}</title><style>body{font:16px/1.6 system-ui,sans-serif;max-width:720px;margin:40px auto;padding:0 16px;color:#1f2937}h1{font-size:28px}</style></head>
<body><h1>${title}</h1>${body.replaceAll("{operator}", operator).replaceAll("{contact}", contact).replaceAll("{url}", config().appUrl)}
<p style="margin-top:40px;color:#6b7280">Last updated ${new Date().getFullYear()}.</p></body></html>`;
}

legalRouter.get("/privacy", (_req, res) => {
  res.type("html").send(
    page(
      "Privacy Policy",
      `<p>This service is operated by {operator}. It automates replies to Instagram comments and direct messages for Instagram professional accounts that connect to it.</p>
<h2>Data we process</h2><ul>
<li>For connected accounts: Instagram account ID, username, profile picture, and an encrypted access token.</li>
<li>For people who comment on or message a connected account: their Instagram-scoped ID, username, and the text of the comment or message that triggered an automation, plus the reply sent.</li>
<li>Tracked link clicks: a hashed IP address, a keyed hash of the recipient ID, and the browser user agent.</li></ul>
<h2>How it is used</h2><p>Only to send the automated replies the account owner configured and to show them delivery logs and analytics. We do not sell data or use it for advertising.</p>
<h2>Retention and deletion</h2><p>Data is kept while the account stays connected. Disconnecting an account deletes its campaigns, logs and token. See <a href="/data-deletion">data deletion</a>.</p>
<h2>Contact</h2><p>{contact}</p>`
    )
  );
});

legalRouter.get("/terms", (_req, res) => {
  res.type("html").send(
    page(
      "Terms of Service",
      `<p>By connecting an Instagram account you confirm you are authorized to manage it and agree to follow Meta's Platform Terms, Instagram Community Guidelines and messaging policies. You are responsible for the content of your automated messages. The service is provided "as is" without warranty. Contact: {contact}.</p>`
    )
  );
});

legalRouter.get("/data-deletion", (_req, res) => {
  res.type("html").send(
    page(
      "Data Deletion Instructions",
      `<p>To delete your data:</p><ol>
<li>Account owners: sign in at <a href="{url}">{url}</a>, open <b>Accounts</b> and click <b>Disconnect</b>. This permanently deletes the account's token, campaigns, message logs and click data.</li>
<li>You can also remove the app in Instagram: Settings → Apps and websites → remove the app.</li>
<li>People who interacted with a connected account can request deletion of their messages by emailing {contact} with their Instagram username. We delete it within 30 days.</li></ol>`
    )
  );
});
