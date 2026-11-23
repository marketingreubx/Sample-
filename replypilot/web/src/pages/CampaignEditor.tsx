import { useEffect, useState, type FormEvent } from "react";
import { api, type Account, type Campaign } from "../api";
import { navigate } from "../router";
import { ErrorNote, PageHeader } from "../ui";

interface Media {
  id: string;
  caption?: string;
  media_url?: string;
  thumbnail_url?: string;
  permalink?: string;
  media_type?: string;
}

const blank = {
  name: "",
  instagramAccountId: "",
  isActive: true,
  triggerComments: true,
  triggerDms: false,
  triggerStoryReplies: false,
  postId: "" as string | null,
  postPermalink: "" as string | null,
  matchAnyPost: false,
  keywords: [] as string[],
  matchAnyWord: false,
  wholeWord: true,
  dmMessage: "Hey {username}! Here's the link you asked for 👉 {link}",
  linkUrl: "",
  linkButtonLabel: "",
  publicReplyEnabled: false,
  publicReplies: ["Sent you a DM {username} 💌"],
};

function fromCampaign(c: Campaign): typeof blank {
  return {
    name: c.name,
    instagramAccountId: c.instagram_account_id,
    isActive: c.is_active,
    triggerComments: c.trigger_comments,
    triggerDms: c.trigger_dms,
    triggerStoryReplies: c.trigger_story_replies,
    postId: c.post_id,
    postPermalink: c.post_permalink,
    matchAnyPost: c.match_any_post,
    keywords: c.keywords,
    matchAnyWord: c.match_any_word,
    wholeWord: c.whole_word,
    dmMessage: c.dm_message,
    linkUrl: c.link_url ?? "",
    linkButtonLabel: c.link_button_label ?? "",
    publicReplyEnabled: c.public_reply_enabled,
    publicReplies: c.public_replies.length ? c.public_replies : [""],
  };
}

function preview(msg: string, link: string, asButton: boolean) {
  const url = link ? "https://your-app/r/abc123" : "";
  let out = msg.replace(/\{username\}/gi, "@jane").replace(/\{keyword\}/gi, "GUIDE");
  if (url && !asButton) out = /\{link\}/i.test(out) ? out.replace(/\{link\}/gi, url) : `${out}\n${url}`;
  else out = out.replace(/\s*\{link\}\s*/gi, " ");
  return out.trim();
}

export function CampaignEditor({ id }: { id?: string }) {
  const [form, setForm] = useState(blank);
  const [accounts, setAccounts] = useState<Account[]>([]);
  const [media, setMedia] = useState<Media[] | null>(null);
  const [mediaError, setMediaError] = useState<string | null>(null);
  const [kw, setKw] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api<Account[]>("/accounts").then((a) => {
      setAccounts(a);
      if (!id && a[0]) setForm((f) => ({ ...f, instagramAccountId: f.instagramAccountId || a[0].id }));
    });
    if (id) api<Campaign>(`/campaigns/${id}`).then((c) => setForm(fromCampaign(c)));
  }, [id]);

  useEffect(() => {
    if (!form.instagramAccountId) return;
    setMedia(null);
    setMediaError(null);
    api<Media[]>(`/accounts/${form.instagramAccountId}/media`).then(setMedia).catch((e) => setMediaError(e.message));
  }, [form.instagramAccountId]);

  const set = <K extends keyof typeof blank>(k: K, v: (typeof blank)[K]) => setForm((f) => ({ ...f, [k]: v }));

  function addKeyword() {
    const parts = kw.split(",").map((s) => s.trim()).filter(Boolean);
    if (parts.length) set("keywords", [...new Set([...form.keywords, ...parts])]);
    setKw("");
  }

  async function submit(e: FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    const body = {
      ...form,
      keywords: kw.trim() ? [...new Set([...form.keywords, ...kw.split(",").map((s) => s.trim()).filter(Boolean)])] : form.keywords,
      postId: form.matchAnyPost ? null : form.postId || null,
      linkUrl: form.linkUrl || null,
      linkButtonLabel: form.linkButtonLabel || null,
      publicReplies: form.publicReplies.map((s) => s.trim()).filter(Boolean),
    };
    try {
      const r = await api<{ id: string }>(id ? `/campaigns/${id}` : "/campaigns", { method: id ? "PUT" : "POST", body });
      navigate(`/campaigns/${r.id}`);
    } catch (err) {
      setError((err as Error).message);
      window.scrollTo({ top: 0, behavior: "smooth" });
    } finally {
      setBusy(false);
    }
  }

  if (!accounts.length && !id) {
    return (
      <>
        <PageHeader title="New campaign" />
        <div className="alert alert-info">Connect an Instagram account first (Instagram accounts page).</div>
      </>
    );
  }

  const asButton = Boolean(form.linkUrl && form.linkButtonLabel);
  return (
    <form onSubmit={submit}>
      <PageHeader
        title={id ? "Edit campaign" : "New campaign"}
        actions={
          <>
            {id && (
              <button
                type="button"
                className="btn btn-danger-ghost"
                onClick={async () => {
                  if (!confirm("Delete this campaign and its logs?")) return;
                  await api(`/campaigns/${id}`, { method: "DELETE" });
                  navigate("/campaigns");
                }}
              >
                Delete
              </button>
            )}
            <button className="btn btn-primary" disabled={busy}>{busy ? "Saving…" : "Save campaign"}</button>
          </>
        }
      />
      <ErrorNote error={error} />
      <div className="editor">
        <div className="editor-main">
          <section className="card">
            <h3>1. Basics</h3>
            <label>Campaign name<input required value={form.name} onChange={(e) => set("name", e.target.value)} placeholder="Free guide giveaway" /></label>
            <label>
              Instagram account
              <select value={form.instagramAccountId} onChange={(e) => set("instagramAccountId", e.target.value)}>
                {accounts.map((a) => (
                  <option key={a.id} value={a.id}>@{a.username}{a.login_type === "demo" ? " (demo)" : ""}</option>
                ))}
              </select>
            </label>
            <label className="check"><input type="checkbox" checked={form.isActive} onChange={(e) => set("isActive", e.target.checked)} /> Active</label>
          </section>

          <section className="card">
            <h3>2. Triggers</h3>
            <label className="check"><input type="checkbox" checked={form.triggerComments} onChange={(e) => set("triggerComments", e.target.checked)} /> Comments on a post → private reply DM</label>
            <label className="check"><input type="checkbox" checked={form.triggerDms} onChange={(e) => set("triggerDms", e.target.checked)} /> Direct messages containing the keyword</label>
            <label className="check"><input type="checkbox" checked={form.triggerStoryReplies} onChange={(e) => set("triggerStoryReplies", e.target.checked)} /> Replies to your Stories</label>

            {form.triggerComments && (
              <div className="sub">
                <label className="check"><input type="checkbox" checked={form.matchAnyPost} onChange={(e) => set("matchAnyPost", e.target.checked)} /> Any post or reel (including future ones)</label>
                {!form.matchAnyPost && (
                  <>
                    <div className="muted small">Pick the post people will comment on:</div>
                    {mediaError && <div className="alert alert-error">{mediaError}</div>}
                    {!media && !mediaError && <div className="muted small">Loading posts…</div>}
                    <div className="media-grid">
                      {media?.map((m) => (
                        <button
                          type="button"
                          key={m.id}
                          className={`media ${form.postId === m.id ? "selected" : ""}`}
                          onClick={() => setForm((f) => ({ ...f, postId: m.id, postPermalink: m.permalink ?? null }))}
                          title={m.caption}
                        >
                          {m.thumbnail_url || m.media_url ? <img src={m.thumbnail_url || m.media_url} alt="" loading="lazy" /> : <span>{m.media_type}</span>}
                          <span className="media-cap">{m.caption?.slice(0, 40) || m.id}</span>
                        </button>
                      ))}
                    </div>
                    <label className="small">Or paste a media ID<input value={form.postId ?? ""} onChange={(e) => set("postId", e.target.value)} placeholder="17895695668004550" /></label>
                  </>
                )}
              </div>
            )}
          </section>

          <section className="card">
            <h3>3. Keywords</h3>
            <label className="check"><input type="checkbox" checked={form.matchAnyWord} onChange={(e) => set("matchAnyWord", e.target.checked)} /> Reply to every comment / message (no keyword)</label>
            {!form.matchAnyWord && (
              <>
                <div className="row">
                  <input
                    value={kw}
                    onChange={(e) => setKw(e.target.value)}
                    onKeyDown={(e) => {
                      if (e.key === "Enter" || e.key === ",") {
                        e.preventDefault();
                        addKeyword();
                      }
                    }}
                    placeholder="Type a keyword and press Enter (e.g. GUIDE)"
                  />
                  <button type="button" className="btn" onClick={addKeyword}>Add</button>
                </div>
                <div className="chips">
                  {form.keywords.map((k) => (
                    <span className="chip" key={k}>
                      {k}
                      <button type="button" aria-label={`Remove ${k}`} onClick={() => set("keywords", form.keywords.filter((x) => x !== k))}>×</button>
                    </span>
                  ))}
                </div>
                <label className="check"><input type="checkbox" checked={form.wholeWord} onChange={(e) => set("wholeWord", e.target.checked)} /> Whole word only ("guide" won't match "guidelines")</label>
                <p className="muted small">Matching ignores case, emoji, punctuation and accents.</p>
              </>
            )}
          </section>

          <section className="card">
            <h3>4. The DM</h3>
            <label>
              Message <span className="muted small">({form.dmMessage.length}/1000) · tokens: {"{username}"} {"{keyword}"} {"{link}"}</span>
              <textarea rows={4} maxLength={1000} required value={form.dmMessage} onChange={(e) => set("dmMessage", e.target.value)} />
            </label>
            <label>Link (tracked automatically)<input type="url" value={form.linkUrl} onChange={(e) => set("linkUrl", e.target.value)} placeholder="https://yourbooking.com/offer" /></label>
            <label>
              Button label (optional, max 20) — sends the link as a tappable button
              <input maxLength={20} value={form.linkButtonLabel} onChange={(e) => set("linkButtonLabel", e.target.value)} placeholder="Get the guide" />
            </label>
          </section>

          {form.triggerComments && (
            <section className="card">
              <h3>5. Public comment reply (optional)</h3>
              <label className="check"><input type="checkbox" checked={form.publicReplyEnabled} onChange={(e) => set("publicReplyEnabled", e.target.checked)} /> Also reply publicly under the comment</label>
              {form.publicReplyEnabled && (
                <>
                  <p className="muted small">One is picked at random per comment, so replies don't look identical.</p>
                  {form.publicReplies.map((r, i) => (
                    <div className="row" key={i}>
                      <input value={r} maxLength={300} onChange={(e) => set("publicReplies", form.publicReplies.map((x, j) => (j === i ? e.target.value : x)))} />
                      <button type="button" className="btn btn-ghost" onClick={() => set("publicReplies", form.publicReplies.filter((_, j) => j !== i))}>Remove</button>
                    </div>
                  ))}
                  {form.publicReplies.length < 10 && (
                    <button type="button" className="btn btn-sm" onClick={() => set("publicReplies", [...form.publicReplies, ""])}>+ Add variation</button>
                  )}
                </>
              )}
            </section>
          )}
        </div>

        <aside className="editor-side">
          <div className="card sticky">
            <h3>Preview</h3>
            <div className="phone">
              <div className="bubble-in">{form.matchAnyWord ? "any message" : form.keywords[0] ?? "GUIDE"}</div>
              <div className="bubble-out">
                {preview(form.dmMessage, form.linkUrl, asButton)}
                {asButton && <div className="bubble-btn">{form.linkButtonLabel}</div>}
              </div>
            </div>
            <ul className="muted small rules">
              <li>Comments get one private reply each, within 7 days (Meta rule).</li>
              <li>DM / Story replies only within 24h of their message.</li>
              <li>Your own comments and messages never trigger campaigns.</li>
            </ul>
          </div>
        </aside>
      </div>
    </form>
  );
}
