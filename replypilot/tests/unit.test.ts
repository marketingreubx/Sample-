import { describe, expect, it } from "vitest";
import { loadConfig } from "../src/config.js";
import { createSignedState, decryptSecret, encryptSecret, verifySignedState } from "../src/lib/crypto.js";
import { matchKeywords } from "../src/lib/keywords.js";
import { classifyMetaError } from "../src/lib/meta/errors.js";
import { parseWebhook, signBody, verifySignature } from "../src/lib/meta/webhook.js";
import {
  PRIVATE_REPLY_WINDOW_MS,
  STANDARD_MESSAGING_WINDOW_MS,
  privateReplyAllowed,
  withinMessagingWindow,
} from "../src/lib/restrictions.js";
import { isRecipientToken, recipientToken, renderTemplate, trackedUrl, truncateBytes } from "../src/lib/templating.js";
import { campaignSchema } from "../src/server/routes/campaigns.js";

describe("keyword matching", () => {
  it("matches case-insensitively with emoji and punctuation", () => {
    expect(matchKeywords("GUIDE please 🙏!!", ["guide"]).matched).toBe(true);
    expect(matchKeywords("I want the Guide.", ["GUIDE"]).keyword).toBe("GUIDE");
  });
  it("respects whole-word matching", () => {
    expect(matchKeywords("read the guidelines", ["guide"], true).matched).toBe(false);
    expect(matchKeywords("read the guidelines", ["guide"], false).matched).toBe(true);
  });
  it("folds Latin accents and numeric homoglyphs", () => {
    expect(matchKeywords("café", ["cafe"]).matched).toBe(true);
    expect(matchKeywords("send 1OO", ["100"]).matched).toBe(true);
  });
  it("supports multi-word and non-Latin keywords", () => {
    expect(matchKeywords("price list please", ["price list"]).matched).toBe(true);
    expect(matchKeywords("أريد الدليل", ["الدليل"]).matched).toBe(true);
    expect(matchKeywords("hello", []).matched).toBe(false);
    expect(matchKeywords("", ["x"]).matched).toBe(false);
  });
});

describe("templating", () => {
  it("personalizes and inserts the link", () => {
    expect(renderTemplate("Hi {username}! {link}", { username: "jane", link: "https://x.y/r/a" })).toBe("Hi @jane! https://x.y/r/a");
    expect(renderTemplate("Hi {username}", { username: null })).toBe("Hi there");
    expect(renderTemplate("Here you go", { link: "https://l" })).toBe("Here you go\nhttps://l");
    expect(renderTemplate("Tap below {link}", { link: null })).toBe("Tap below");
    expect(renderTemplate("You said {keyword}", { keyword: "GUIDE" })).toBe("You said GUIDE");
  });
  it("truncates to Instagram's 1000-byte limit on character boundaries", () => {
    const t = truncateBytes("é".repeat(800));
    expect(Buffer.byteLength(t)).toBeLessThanOrEqual(1000);
    expect(t.endsWith("…")).toBe(true);
  });
  it("builds keyed recipient tokens and tracked URLs", () => {
    const tok = recipientToken("12345", "s");
    expect(isRecipientToken(tok)).toBe(true);
    expect(recipientToken("12345", "s")).toBe(tok);
    expect(recipientToken("12345", "other")).not.toBe(tok);
    expect(trackedUrl("https://a.b/", "slug", tok)).toBe(`https://a.b/r/slug?r=${tok}`);
  });
});

describe("webhook signature + parsing", () => {
  const body = JSON.stringify({ object: "instagram", entry: [] });
  it("verifies sha256 signatures with either secret", () => {
    expect(verifySignature(body, signBody(body, "b"), ["a", "b"])).toBe(true);
    expect(verifySignature(body, signBody(body, "c"), ["a", "b"])).toBe(false);
    expect(verifySignature(body, undefined, ["a"])).toBe(false);
    expect(verifySignature(body + " ", signBody(body, "a"), ["a"])).toBe(false);
  });

  it("parses comments, DMs and story replies and skips echoes/self", () => {
    const events = parseWebhook({
      object: "instagram",
      entry: [
        {
          id: "ACC",
          time: 1_700_000_000,
          changes: [
            { field: "comments", value: { id: "c1", text: "GUIDE", from: { id: "U1", username: "jane" }, media: { id: "M1" } } },
            { field: "comments", value: { id: "c2", text: "my own reply", from: { id: "ACC" }, media: { id: "M1" } } },
            { field: "comments", value: { id: "c3", text: "ad", from: { id: "U2" }, media: { id: "AD", original_media_id: "M1" } } },
          ],
          messaging: [
            { sender: { id: "U3" }, recipient: { id: "ACC" }, timestamp: 1_700_000_001_000, message: { mid: "m1", text: "price" } },
            { sender: { id: "U4" }, recipient: { id: "ACC" }, timestamp: 1, message: { mid: "m2", text: "", reply_to: { story: { id: "S1" } } } },
            { sender: { id: "ACC" }, recipient: { id: "U3" }, message: { mid: "m3", text: "echo", is_echo: true } },
            { sender: { id: "U5" }, recipient: { id: "ACC" }, message: { mid: "m4", is_deleted: true } },
            { sender: { id: "U6" }, recipient: { id: "ACC" }, read: { watermark: 1 } },
          ],
        },
      ],
    });
    expect(events.map((e) => e.kind)).toEqual(["comment", "comment", "dm", "story_reply"]);
    const c = events[0] as any;
    expect(c).toMatchObject({ commentId: "c1", senderUsername: "jane", mediaId: "M1", timestamp: 1_700_000_000_000 });
    expect((events[1] as any).originalMediaId).toBe("M1");
    expect(events[3]).toMatchObject({ storyId: "S1", senderId: "U4" });
  });

  it("ignores non-instagram objects", () => {
    expect(parseWebhook({ object: "page", entry: [{ id: "x" }] })).toEqual([]);
  });
});

describe("Meta error classification", () => {
  const cls = (code: number, sub?: number, status = 400) => classifyMetaError({ error: { code, error_subcode: sub, message: "x" } }, status).kind;
  it("maps codes to worker reactions", () => {
    expect(cls(190)).toBe("token");
    expect(cls(4)).toBe("rate_limit");
    expect(cls(613)).toBe("rate_limit");
    expect(cls(10, 2534022)).toBe("window");
    expect(cls(551)).toBe("window");
    expect(cls(100)).toBe("permission");
    expect(cls(2, undefined, 500)).toBe("transient");
    expect(classifyMetaError({}, 503).retryable).toBe(true);
  });
});

describe("restrictions", () => {
  const now = Date.now();
  it("enforces the 7-day private reply window", () => {
    expect(privateReplyAllowed(now - 1000, now)).toBe(true);
    expect(privateReplyAllowed(now - PRIVATE_REPLY_WINDOW_MS - 1, now)).toBe(false);
  });
  it("enforces the 24-hour messaging window", () => {
    expect(withinMessagingWindow(now - 3600_000, now)).toBe(true);
    expect(withinMessagingWindow(now - STANDARD_MESSAGING_WINDOW_MS - 1, now)).toBe(false);
    expect(withinMessagingWindow(null, now)).toBe(false);
  });
});

describe("crypto", () => {
  it("round-trips secrets and rejects tampering", () => {
    const key = Buffer.alloc(32, 7);
    const enc = encryptSecret("IGAA-token", key);
    expect(enc).not.toContain("IGAA");
    expect(decryptSecret(enc, key)).toBe("IGAA-token");
    expect(() => decryptSecret(enc, Buffer.alloc(32, 8))).toThrow();
    const tampered = enc.slice(0, -4) + (enc.endsWith("AAAA") ? "BBBB" : "AAAA");
    expect(() => decryptSecret(tampered, key)).toThrow();
  });
  it("signs and expires OAuth state", () => {
    const s = createSignedState({ ws: "w1" }, "sec");
    expect(verifySignedState(s, 60_000, "sec")?.ws).toBe("w1");
    expect(verifySignedState(s, 60_000, "other")).toBeNull();
    expect(verifySignedState(s + "x", 60_000, "sec")).toBeNull();
    expect(verifySignedState(s, -1, "sec")).toBeNull();
  });
});

describe("config", () => {
  const base = { DATABASE_URL: "postgres://x", SESSION_SECRET: "s", ENCRYPTION_KEY: "b".repeat(64), WEBHOOK_VERIFY_TOKEN: "v" };
  it("requires Meta secrets outside demo mode", () => {
    expect(() => loadConfig({ ...base })).toThrow(/APP_SECRET/);
    expect(loadConfig({ ...base, FACEBOOK_APP_SECRET: "f" }).demoMode).toBe(false);
  });
  it("rejects a bad encryption key and non-https production URLs", () => {
    expect(() => loadConfig({ ...base, ENCRYPTION_KEY: "short", FACEBOOK_APP_SECRET: "f" })).toThrow(/ENCRYPTION_KEY/);
    expect(() => loadConfig({ ...base, FACEBOOK_APP_SECRET: "f", NODE_ENV: "production", APP_URL: "http://x" })).toThrow(/https/);
  });
  it("runs in demo mode with no secrets at all", () => {
    const c = loadConfig({ DATABASE_URL: "postgres://x", DEMO_MODE: "true" });
    expect(c.demoMode).toBe(true);
    expect(c.encryptionKey.length).toBe(32);
  });
});

describe("campaign validation", () => {
  const ok = {
    name: "Guide",
    instagramAccountId: "8f0e7a50-7f3c-4a7e-9a33-6f1c1d2b3c4d",
    postId: "M1",
    keywords: ["GUIDE"],
    dmMessage: "Hi {username} {link}",
  };
  it("accepts a valid campaign", () => {
    expect(campaignSchema.safeParse(ok).success).toBe(true);
  });
  it("rejects missing post, keywords, triggers, bad links and long messages", () => {
    const bad = (patch: object) => campaignSchema.safeParse({ ...ok, ...patch }).success;
    expect(bad({ postId: null })).toBe(false);
    expect(bad({ keywords: [] })).toBe(false);
    expect(bad({ triggerComments: false })).toBe(false);
    expect(bad({ linkUrl: "javascript:alert(1)" })).toBe(false);
    expect(bad({ linkButtonLabel: "Tap" })).toBe(false);
    expect(bad({ linkUrl: "https://x.y", linkButtonLabel: "x".repeat(21) })).toBe(false);
    expect(bad({ dmMessage: "x".repeat(1001) })).toBe(false);
    expect(bad({ publicReplyEnabled: true, publicReplies: [] })).toBe(false);
    expect(bad({ postId: null, matchAnyPost: true, keywords: [], matchAnyWord: true })).toBe(true);
  });
});
