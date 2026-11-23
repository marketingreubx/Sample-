import { useEffect, useState } from "react";
import { api, fmtDate, type Me } from "../api";
import { ErrorNote, PageHeader } from "../ui";

export function SettingsPage({ me, onChange }: { me: Me; onChange: () => void }) {
  const ws = me.workspaces?.find((w) => w.id === me.workspaceId);
  const [name, setName] = useState(ws?.name ?? "");
  const [members, setMembers] = useState<any[]>([]);
  const [invite, setInvite] = useState({ email: "", role: "member" });
  const [newWs, setNewWs] = useState("");
  const [health, setHealth] = useState<any>(null);
  const [events, setEvents] = useState<any[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [msg, setMsg] = useState<string | null>(null);
  const canEdit = me.role === "owner" || me.role === "admin";

  const loadMembers = () => api("/workspace/members").then(setMembers);
  useEffect(() => {
    loadMembers();
    fetch("/api/health").then((r) => r.json()).then(setHealth);
    if (canEdit) api("/webhook-events").then(setEvents).catch(() => {});
  }, []);

  const run = async (fn: () => Promise<unknown>, ok: string) => {
    setError(null);
    setMsg(null);
    try {
      await fn();
      setMsg(ok);
    } catch (e) {
      setError((e as Error).message);
    }
  };

  return (
    <>
      <PageHeader title="Settings" />
      <ErrorNote error={error} />
      {msg && <div className="alert alert-success">{msg}</div>}
      <div className="grid two">
        <section className="card">
          <h3>Workspace</h3>
          <label>Name<input value={name} disabled={!canEdit} onChange={(e) => setName(e.target.value)} /></label>
          {canEdit && <button className="btn" onClick={() => run(async () => { await api("/workspace", { method: "PATCH", body: { name } }); onChange(); }, "Saved")}>Save</button>}
          <hr />
          <h4>Create another workspace</h4>
          <p className="muted small">Agencies: one workspace per client keeps accounts, campaigns and logs separate.</p>
          <div className="row">
            <input placeholder="Client name" value={newWs} onChange={(e) => setNewWs(e.target.value)} />
            <button className="btn" disabled={!newWs.trim()} onClick={() => run(async () => { await api("/workspaces", { body: { name: newWs } }); setNewWs(""); onChange(); }, "Workspace created and selected")}>Create</button>
          </div>
        </section>

        <section className="card">
          <h3>Team</h3>
          <table>
            <tbody>
              {members.map((m) => (
                <tr key={m.id}>
                  <td>{m.name || m.email}<div className="muted small">{m.email}</div></td>
                  <td className="small">{m.role}</td>
                  <td>
                    {canEdit && m.role !== "owner" && (
                      <button className="btn btn-sm btn-danger-ghost" onClick={() => run(async () => { await api(`/workspace/members/${m.id}`, { method: "DELETE" }); loadMembers(); }, "Removed")}>Remove</button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {canEdit && (
            <div className="row">
              <input placeholder="teammate@email.com" value={invite.email} onChange={(e) => setInvite({ ...invite, email: e.target.value })} />
              <select value={invite.role} onChange={(e) => setInvite({ ...invite, role: e.target.value })} aria-label="Role">
                <option value="member">Member (view only)</option>
                <option value="admin">Admin</option>
              </select>
              <button className="btn" onClick={() => run(async () => { await api("/workspace/members", { body: invite }); setInvite({ email: "", role: "member" }); loadMembers(); }, "Added")}>Add</button>
            </div>
          )}
          <p className="muted small">Teammates sign up first, then you add them by email.</p>
        </section>

        <section className="card">
          <h3>System health</h3>
          {health && (
            <div className="kv small">
              <span>Status</span><b>{health.status}</b>
              <span>Database</span><span>{health.database}</span>
              <span>Redis</span><span>{health.redis}</span>
              <span>Worker</span><span>{health.worker?.healthy ? `running (seen ${fmtDate(health.worker.lastSeen)})` : "NOT running"}</span>
              <span>Queue</span><span>{health.queue && Object.entries(health.queue).map(([k, v]) => `${k}: ${v}`).join(" · ")}</span>
              <span>Mode</span><span>{health.demoMode ? "Demo" : "Live (Meta API)"}</span>
            </div>
          )}
          <p className="small muted">
            Webhook callback URL for Meta: <code>{me.appUrl}/api/webhook</code>
            <br />
            OAuth redirect URI: <code>{me.appUrl}/api/instagram/callback</code>
          </p>
        </section>

        {canEdit && (
          <section className="card">
            <h3>Recent webhook deliveries</h3>
            <table>
              <tbody>
                {events.slice(0, 12).map((e) => (
                  <tr key={e.id}>
                    <td className="small nowrap">{fmtDate(e.created_at)}</td>
                    <td className="small">{e.status}</td>
                    <td className="small">{e.jobs_enqueued} job(s)</td>
                    <td className="small text-red">{e.error}</td>
                  </tr>
                ))}
                {!events.length && <tr><td className="muted small">No webhooks received yet.</td></tr>}
              </tbody>
            </table>
          </section>
        )}
      </div>
    </>
  );
}
