import { Fragment, useEffect, useState } from "react";
import { api, fmtDate } from "../api";
import { Badge, PageHeader, statusTone } from "../ui";

export function LogsTable({ campaignId, refreshKey }: { campaignId?: string; refreshKey?: number }) {
  const [status, setStatus] = useState("");
  const [q, setQ] = useState("");
  const [page, setPage] = useState(1);
  const [data, setData] = useState<any>(null);
  const [open, setOpen] = useState<string | null>(null);

  useEffect(() => {
    const p = new URLSearchParams({ page: String(page) });
    if (campaignId) p.set("campaignId", campaignId);
    if (status) p.set("status", status);
    if (q) p.set("q", q);
    api(`/logs?${p}`).then(setData);
  }, [campaignId, status, q, page, refreshKey]);

  return (
    <div className="card table-wrap">
      <div className="row filters">
        <select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} aria-label="Status">
          <option value="">All statuses</option>
          <option value="sent">Sent</option>
          <option value="failed">Failed</option>
          <option value="skipped">Skipped</option>
          <option value="pending">Pending</option>
        </select>
        <input placeholder="Search username or text" value={q} onChange={(e) => { setQ(e.target.value); setPage(1); }} />
        <span className="muted small">{data?.total ?? 0} entries</span>
      </div>
      <table>
        <thead>
          <tr>
            <th>When</th>
            {!campaignId && <th>Campaign</th>}
            <th>From</th>
            <th>Trigger</th>
            <th>They wrote</th>
            <th>DM</th>
            <th>Public reply</th>
          </tr>
        </thead>
        <tbody>
          {data?.rows.map((r: any) => (
            <Fragment key={r.id}>
              <tr className="clickable" onClick={() => setOpen(open === r.id ? null : r.id)}>
                <td className="small nowrap">{fmtDate(r.created_at)}</td>
                {!campaignId && <td className="small">{r.campaign_name}</td>}
                <td>{r.sender_username ? `@${r.sender_username}` : <span className="muted small">{r.sender_id}</span>}</td>
                <td className="small">{r.trigger.replace("_", " ")}</td>
                <td className="small clip">{r.incoming_text || <i className="muted">(no text)</i>}</td>
                <td>
                  <Badge tone={statusTone(r.status)}>{r.status}</Badge>
                  {r.attempts > 1 && <span className="muted small"> ×{r.attempts}</span>}
                </td>
                <td>{r.public_reply_status === "none" ? <span className="muted small">—</span> : <Badge tone={statusTone(r.public_reply_status)}>{r.public_reply_status}</Badge>}</td>
              </tr>
              {open === r.id && (
                <tr className="detail">
                  <td colSpan={7}>
                    <div className="small">
                      {r.matched_keyword && <div><b>Matched keyword:</b> {r.matched_keyword}</div>}
                      {r.dm_text && <div><b>DM text:</b> {r.dm_text}</div>}
                      {r.sent_at && <div><b>Sent at:</b> {fmtDate(r.sent_at)}</div>}
                      {r.skip_label && <div><b>Skipped:</b> {r.skip_label}</div>}
                      {r.error && <div className="text-red"><b>Error:</b> {r.error}</div>}
                      {r.public_reply_text && <div><b>Public reply:</b> {r.public_reply_text}</div>}
                      {r.public_reply_error && <div className="text-red"><b>Public reply error:</b> {r.public_reply_error}</div>}
                      <div className="muted">Event ID: {r.source_event_id}</div>
                    </div>
                  </td>
                </tr>
              )}
            </Fragment>
          ))}
          {data && !data.rows.length && (
            <tr>
              <td colSpan={7} className="muted center">Nothing logged yet.</td>
            </tr>
          )}
        </tbody>
      </table>
      {data && data.total > data.pageSize && (
        <div className="row pager">
          <button className="btn btn-sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</button>
          <span className="small">Page {page} of {Math.ceil(data.total / data.pageSize)}</span>
          <button className="btn btn-sm" disabled={page * data.pageSize >= data.total} onClick={() => setPage(page + 1)}>Next</button>
        </div>
      )}
    </div>
  );
}

export function LogsPage() {
  return (
    <>
      <PageHeader title="Message logs" subtitle="Every trigger, what was sent, and why anything was skipped or failed. Click a row for details." />
      <LogsTable />
    </>
  );
}
