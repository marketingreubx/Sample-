import { useEffect, useState } from "react";
import { api, fmtDate, type Account } from "../api";
import { ErrorNote, PageHeader } from "../ui";
import { LogsTable } from "./Logs";

export function DemoLab() {
  const [accounts, setAccounts] = useState<Account[]>([]);
  const [form, setForm] = useState({ accountId: "", type: "comment", text: "GUIDE please!", mediaId: "demo_media_1", senderUsername: "jane_doe" });
  const [outbox, setOutbox] = useState<any[]>([]);
  const [result, setResult] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    api<Account[]>("/accounts").then((a) => {
      const demo = a.filter((x) => x.login_type === "demo");
      setAccounts(demo);
      if (demo[0]) setForm((f) => ({ ...f, accountId: demo[0].id }));
    });
  }, []);
  useEffect(() => {
    api("/demo/outbox").then(setOutbox);
    const t = setInterval(() => {
      api("/demo/outbox").then(setOutbox);
      setTick((x) => x + 1);
    }, 2000);
    return () => clearInterval(t);
  }, []);

  async function simulate() {
    setError(null);
    setResult(null);
    try {
      const r = await api("/demo/simulate", { body: form });
      setResult(r.enqueued ? "Webhook accepted and queued. The worker will process it in a moment ↓" : "Webhook accepted, but no job was queued.");
    } catch (e) {
      setError((e as Error).message);
    }
  }

  return (
    <>
      <PageHeader title="Demo lab" subtitle="Fire realistic, signed Instagram webhooks at your own server and watch the worker respond. Nothing is sent to Instagram." />
      {!accounts.length && <div className="alert alert-info">Add a demo account on the Instagram accounts page first, then create a campaign for it.</div>}
      <div className="grid two">
        <section className="card">
          <h3>Simulate an event</h3>
          <label>
            Demo account
            <select value={form.accountId} onChange={(e) => setForm({ ...form, accountId: e.target.value })}>
              {accounts.map((a) => <option key={a.id} value={a.id}>@{a.username}</option>)}
            </select>
          </label>
          <label>
            Event type
            <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>
              <option value="comment">Comment on a post</option>
              <option value="dm">Direct message</option>
              <option value="story_reply">Story reply</option>
            </select>
          </label>
          {form.type === "comment" && (
            <label>
              Post
              <select value={form.mediaId} onChange={(e) => setForm({ ...form, mediaId: e.target.value })}>
                {[1, 2, 3, 4].map((i) => <option key={i} value={`demo_media_${i}`}>demo_media_{i}</option>)}
              </select>
            </label>
          )}
          {form.type === "comment" && <label>Commenter username<input value={form.senderUsername} onChange={(e) => setForm({ ...form, senderUsername: e.target.value })} /></label>}
          <label>Text<input value={form.text} onChange={(e) => setForm({ ...form, text: e.target.value })} /></label>
          <button className="btn btn-primary" disabled={!form.accountId} onClick={simulate}>Send webhook</button>
          {result && <div className="alert alert-success">{result}</div>}
          <ErrorNote error={error} />
        </section>
        <section className="card">
          <h3>Demo outbox <span className="muted small">(what would have been sent)</span></h3>
          <ul className="outbox">
            {outbox.map((o) => (
              <li key={o.id}>
                <div className="small muted">{fmtDate(o.created_at)} · {o.kind.replace("_", " ")} → {o.recipient}</div>
                <div>{o.text}</div>
                {o.payload?.button && <div className="bubble-btn inline">{o.payload.button.title} → {o.payload.button.url}</div>}
              </li>
            ))}
            {!outbox.length && <li className="muted">Nothing yet.</li>}
          </ul>
        </section>
      </div>
      <h2>Logs</h2>
      <LogsTable refreshKey={tick} />
    </>
  );
}
