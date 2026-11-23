import { StrictMode, useCallback, useEffect, useState } from "react";
import { createRoot } from "react-dom/client";
import { api, type Me } from "./api";
import { AccountsPage } from "./pages/Accounts";
import { AuthPage } from "./pages/Auth";
import { CampaignDetail } from "./pages/CampaignDetail";
import { CampaignEditor } from "./pages/CampaignEditor";
import { CampaignsPage } from "./pages/Campaigns";
import { Dashboard } from "./pages/Dashboard";
import { DemoLab } from "./pages/DemoLab";
import { LogsPage } from "./pages/Logs";
import { SettingsPage } from "./pages/Settings";
import { Link, match, navigate, usePath } from "./router";
import "./styles.css";

function App() {
  const [me, setMe] = useState<Me | null>(null);
  const path = usePath();
  const refresh = useCallback(() => api<Me>("/auth/me").then(setMe).catch(() => setMe({ user: null, demoMode: false })), []);
  useEffect(() => {
    refresh();
  }, [refresh]);

  if (!me) return <div className="center muted">Loading…</div>;
  if (!me.user) return <AuthPage me={me} onDone={refresh} />;

  const pathname = path.split("?")[0];
  const canEdit = me.role === "owner" || me.role === "admin";
  let page;
  let p: Record<string, string> | null;
  if (pathname === "/" || pathname === "/dashboard") page = <Dashboard me={me} />;
  else if (pathname === "/campaigns") page = <CampaignsPage canEdit={canEdit} />;
  else if (pathname === "/campaigns/new") page = <CampaignEditor />;
  else if ((p = match("/campaigns/:id/edit", pathname))) page = <CampaignEditor id={p.id} />;
  else if ((p = match("/campaigns/:id", pathname))) page = <CampaignDetail id={p.id} canEdit={canEdit} />;
  else if (pathname === "/accounts") page = <AccountsPage me={me} canEdit={canEdit} />;
  else if (pathname === "/logs") page = <LogsPage />;
  else if (pathname === "/settings") page = <SettingsPage me={me} onChange={refresh} />;
  else if (pathname === "/demo" && me.demoMode) page = <DemoLab />;
  else page = <div className="card">Page not found. <Link to="/">Go home</Link></div>;

  const nav: [string, string][] = [
    ["/", "Dashboard"],
    ["/campaigns", "Campaigns"],
    ["/accounts", "Instagram accounts"],
    ["/logs", "Message logs"],
    ...(me.demoMode ? ([["/demo", "Demo lab"]] as [string, string][]) : []),
    ["/settings", "Settings"],
  ];
  const active = (to: string) => (to === "/" ? pathname === "/" || pathname === "/dashboard" : pathname.startsWith(to));

  return (
    <div className="layout">
      <aside className="sidebar">
        <div className="brand">
          <span className="logo">↩</span> ReplyPilot
        </div>
        <select
          className="ws-select"
          value={me.workspaceId}
          aria-label="Workspace"
          onChange={async (e) => {
            await api("/workspaces/switch", { body: { workspaceId: e.target.value } });
            await refresh();
            navigate("/");
          }}
        >
          {me.workspaces?.map((w) => (
            <option key={w.id} value={w.id}>
              {w.name}
            </option>
          ))}
        </select>
        <nav>
          {nav.map(([to, label]) => (
            <Link key={to} to={to} className={active(to) ? "active" : ""}>
              {label}
            </Link>
          ))}
        </nav>
        <div className="sidebar-foot">
          {me.demoMode && <div className="badge badge-purple">Demo mode</div>}
          <div className="muted small">{me.user.email}</div>
          <button
            className="btn btn-ghost btn-sm"
            onClick={async () => {
              await api("/auth/logout", { method: "POST" });
              navigate("/");
              refresh();
            }}
          >
            Sign out
          </button>
        </div>
      </aside>
      <main className="main">{page}</main>
    </div>
  );
}

createRoot(document.getElementById("root")!).render(
  <StrictMode>
    <App />
  </StrictMode>
);
