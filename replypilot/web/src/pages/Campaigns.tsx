import { useEffect, useState } from "react";
import { api, pct, type Campaign } from "../api";
import { Link, navigate } from "../router";
import { Badge, Empty, PageHeader } from "../ui";

export function triggersLabel(c: Campaign) {
  return [c.trigger_comments && "Comments", c.trigger_dms && "DMs", c.trigger_story_replies && "Story replies"].filter(Boolean).join(" · ");
}

export function CampaignsPage({ canEdit }: { canEdit: boolean }) {
  const [rows, setRows] = useState<Campaign[] | null>(null);
  const load = () => api<Campaign[]>("/campaigns").then(setRows);
  useEffect(() => {
    load();
  }, []);

  return (
    <>
      <PageHeader
        title="Campaigns"
        subtitle="Keyword automations for comments, DMs and Story replies"
        actions={canEdit && <button className="btn btn-primary" onClick={() => navigate("/campaigns/new")}>New campaign</button>}
      />
      {rows && rows.length === 0 && (
        <Empty title="No campaigns yet">
          <p className="muted">Create one: pick a post, add a keyword like GUIDE, and write the DM people get.</p>
        </Empty>
      )}
      {rows && rows.length > 0 && (
        <div className="card table-wrap">
          <table>
            <thead>
              <tr>
                <th>Campaign</th>
                <th>Triggers</th>
                <th>Keywords</th>
                <th className="num">Sent</th>
                <th className="num">Clicks</th>
                <th className="num">CTR</th>
                <th>Status</th>
                {canEdit && <th />}
              </tr>
            </thead>
            <tbody>
              {rows.map((c) => (
                <tr key={c.id}>
                  <td>
                    <Link to={`/campaigns/${c.id}`}><b>{c.name}</b></Link>
                    <div className="muted small">@{c.account_username} · {c.match_any_post ? "any post" : c.post_id ? "one post" : "—"}</div>
                  </td>
                  <td className="small">{triggersLabel(c)}</td>
                  <td className="small">{c.match_any_word ? <i>any message</i> : c.keywords.join(", ")}</td>
                  <td className="num">{c.sent}</td>
                  <td className="num">{c.clicks}</td>
                  <td className="num">{pct(c.unique_clicks, c.sent)}</td>
                  <td>{c.is_active ? <Badge tone="green">Active</Badge> : <Badge tone="gray">Paused</Badge>}</td>
                  {canEdit && (
                    <td className="row-actions">
                      <button
                        className="btn btn-sm btn-ghost"
                        onClick={async () => {
                          await api(`/campaigns/${c.id}/status`, { method: "PATCH", body: { isActive: !c.is_active } });
                          load();
                        }}
                      >
                        {c.is_active ? "Pause" : "Resume"}
                      </button>
                      <button className="btn btn-sm btn-ghost" onClick={() => navigate(`/campaigns/${c.id}/edit`)}>Edit</button>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}
