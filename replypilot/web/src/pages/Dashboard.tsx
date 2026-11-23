import { useEffect, useState } from "react";
import { api, pct, type Me } from "../api";
import { Link } from "../router";
import { DailyChart, PageHeader, Stat } from "../ui";

export function Dashboard({ me }: { me: Me }) {
  const [days, setDays] = useState(30);
  const [data, setData] = useState<any>(null);
  const [health, setHealth] = useState<any>(null);
  useEffect(() => {
    api(`/analytics/overview?days=${days}`).then(setData);
  }, [days]);
  useEffect(() => {
    fetch("/api/health").then((r) => r.json()).then(setHealth).catch(() => {});
  }, []);
  const t = data?.totals;

  return (
    <>
      <PageHeader
        title="Dashboard"
        subtitle={`Last ${days} days across this workspace`}
        actions={
          <select value={days} onChange={(e) => setDays(Number(e.target.value))} aria-label="Range">
            <option value={7}>7 days</option>
            <option value={30}>30 days</option>
            <option value={90}>90 days</option>
          </select>
        }
      />
      {health && !health.worker?.healthy && (
        <div className="alert alert-error">
          The background worker isn't running, so no DMs will be sent. Start it with <code>npm run worker</code> (or check the worker container).
        </div>
      )}
      {t && t.accounts === 0 && (
        <div className="alert alert-info">
          Start here: <Link to="/accounts">connect an Instagram account</Link>
          {me.demoMode ? " (or add a demo account)" : ""}, then <Link to="/campaigns/new">create a campaign</Link>.
        </div>
      )}
      {t && (
        <div className="grid stats">
          <Stat label="Triggers" value={t.triggered} hint="comments/DMs that matched" />
          <Stat label="DMs sent" value={t.sent} hint={`${pct(t.sent, t.triggered)} of triggers`} />
          <Stat label="Link clicks" value={t.clicks} hint={`${t.unique_clicks} unique · CTR ${pct(t.unique_clicks, t.sent)}`} />
          <Stat label="Public replies" value={t.public_replies} />
          <Stat label="Failed" value={t.failed} hint={`${t.skipped} skipped by Meta rules`} />
          <Stat label="Active campaigns" value={t.active_campaigns} hint={`${t.accounts} account(s)`} />
        </div>
      )}
      {data && (
        <div className="card">
          <h3>Daily activity</h3>
          <DailyChart data={data.daily} />
        </div>
      )}
    </>
  );
}
