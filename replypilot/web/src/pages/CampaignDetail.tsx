import { useEffect, useState } from "react";
import { api, pct, type Campaign } from "../api";
import { navigate } from "../router";
import { Badge, DailyChart, PageHeader, Stat } from "../ui";
import { LogsTable } from "./Logs";
import { triggersLabel } from "./Campaigns";

export function CampaignDetail({ id, canEdit }: { id: string; canEdit: boolean }) {
  const [c, setC] = useState<Campaign | null>(null);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => {
    api<Campaign>(`/campaigns/${id}`).then(setC).catch((e) => setError(e.message));
  }, [id]);
  if (error) return <div className="alert alert-error">{error}</div>;
  if (!c) return <div className="muted">Loading…</div>;

  return (
    <>
      <PageHeader
        title={c.name}
        subtitle={`@${c.account_username} · ${triggersLabel(c)} · ${c.match_any_word ? "any message" : c.keywords.join(", ")}`}
        actions={
          <>
            {c.is_active ? <Badge tone="green">Active</Badge> : <Badge tone="gray">Paused</Badge>}
            {canEdit && <button className="btn" onClick={() => navigate(`/campaigns/${id}/edit`)}>Edit</button>}
          </>
        }
      />
      <div className="grid stats">
        <Stat label="Triggers" value={c.triggered} />
        <Stat label="DMs sent" value={c.sent} hint={`${pct(c.sent, c.triggered)} delivery`} />
        <Stat label="Link clicks" value={c.clicks} hint={`${c.unique_clicks} unique`} />
        <Stat label="Click-through" value={pct(c.unique_clicks, c.sent)} hint="unique clicks / DMs sent" />
        <Stat label="Public replies" value={c.public_replies_sent ?? 0} />
        <Stat label="Failed / skipped" value={`${c.failed} / ${c.skipped}`} />
      </div>
      <div className="grid two">
        <div className="card">
          <h3>Last 30 days</h3>
          {c.daily && <DailyChart data={c.daily} />}
        </div>
        <div className="card">
          <h3>Top keywords</h3>
          {c.keywordBreakdown?.length ? (
            <ul className="kw-list">
              {c.keywordBreakdown.map((k) => (
                <li key={k.keyword}>
                  <span>{k.keyword}</span>
                  <b>{k.count}</b>
                </li>
              ))}
            </ul>
          ) : (
            <p className="muted">No matches yet.</p>
          )}
          {c.tracked_slug && (
            <p className="small muted">
              Tracked link: <code>/r/{c.tracked_slug}</code> → {c.link_url}
            </p>
          )}
          {c.post_permalink && (
            <p className="small">
              <a href={c.post_permalink} target="_blank" rel="noreferrer">Open the post on Instagram ↗</a>
            </p>
          )}
        </div>
      </div>
      <h2>Message log</h2>
      <LogsTable campaignId={id} />
    </>
  );
}
