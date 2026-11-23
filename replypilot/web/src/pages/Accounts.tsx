import { useEffect, useState } from "react";
import { api, fmtDate, type Account, type Me } from "../api";
import { Badge, Empty, ErrorNote, PageHeader, statusTone } from "../ui";

export function AccountsPage({ me, canEdit }: { me: Me; canEdit: boolean }) {
  const [rows, setRows] = useState<Account[] | null>(null);
  const params = new URLSearchParams(window.location.search);
  const [error, setError] = useState<string | null>(params.get("error"));
  const connected = params.get("connected");
  const load = () => api<Account[]>("/accounts").then(setRows);
  useEffect(() => {
    load();
  }, []);

  async function connect() {
    setError(null);
    try {
      const { url } = await api<{ url: string }>("/accounts/connect", { method: "POST" });
      window.location.href = url;
    } catch (e) {
      setError((e as Error).message);
    }
  }

  return (
    <>
      <PageHeader
        title="Instagram accounts"
        subtitle="Connect Instagram professional (Business or Creator) accounts through Meta's official API."
        actions={
          canEdit && (
            <>
              {me.demoMode && (
                <button className="btn" onClick={async () => { await api("/accounts/demo", { method: "POST" }); load(); }}>
                  Add demo account
                </button>
              )}
              <button className="btn btn-primary" onClick={connect}>Connect Instagram</button>
            </>
          )
        }
      />
      {connected && <div className="alert alert-success">Connected {connected} account(s). Webhooks are subscribed automatically.</div>}
      <ErrorNote error={error} />
      {rows && rows.length === 0 && (
        <Empty title="No accounts connected">
          <p className="muted">
            Click <b>Connect Instagram</b> and approve the permissions.{" "}
            {me.demoMode && "In demo mode you can add a fake account to test everything without Meta."}
          </p>
        </Empty>
      )}
      <div className="grid accounts">
        {rows?.map((a) => (
          <div className="card account" key={a.id}>
            <div className="row">
              {a.profile_picture_url ? <img className="avatar" src={a.profile_picture_url} alt="" /> : <div className="avatar">{a.username[0]?.toUpperCase()}</div>}
              <div>
                <b>@{a.username}</b>
                <div className="muted small">{a.name}</div>
              </div>
            </div>
            <div className="kv small">
              <span>Status</span>
              <Badge tone={statusTone(a.status)}>{a.status.replace("_", " ")}</Badge>
              <span>Login</span>
              <span>{a.login_type === "demo" ? "Demo (no real sends)" : a.login_type === "unified" ? "Meta unified login" : "Instagram login"}</span>
              <span>Webhooks</span>
              <span>{a.webhook_subscribed ? "Subscribed" : "Not subscribed — reconnect"}</span>
              <span>Token expires</span>
              <span>{a.login_type === "demo" ? "—" : fmtDate(a.token_expires_at)}</span>
              <span>Account ID</span>
              <code>{a.ig_user_id}</code>
            </div>
            {a.status_detail && <div className="alert alert-error small">{a.status_detail}</div>}
            {canEdit && (
              <div className="row">
                {a.login_type !== "demo" && <button className="btn btn-sm" onClick={connect}>Reconnect</button>}
                <button
                  className="btn btn-sm btn-danger-ghost"
                  onClick={async () => {
                    if (!confirm(`Disconnect @${a.username}? This deletes its campaigns, logs and token.`)) return;
                    await api(`/accounts/${a.id}`, { method: "DELETE" });
                    load();
                  }}
                >
                  Disconnect
                </button>
              </div>
            )}
          </div>
        ))}
      </div>
    </>
  );
}
