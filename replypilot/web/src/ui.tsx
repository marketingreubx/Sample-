import type { ReactNode } from "react";

export function Stat({ label, value, hint }: { label: string; value: ReactNode; hint?: string }) {
  return (
    <div className="card stat">
      <div className="stat-label">{label}</div>
      <div className="stat-value">{value}</div>
      {hint && <div className="muted small">{hint}</div>}
    </div>
  );
}

export function Badge({ tone, children }: { tone: "green" | "red" | "gray" | "amber" | "purple"; children: ReactNode }) {
  return <span className={`badge badge-${tone}`}>{children}</span>;
}

export function statusTone(s: string): "green" | "red" | "gray" | "amber" {
  return s === "sent" || s === "active" ? "green" : s === "failed" || s === "token_expired" ? "red" : s === "pending" ? "amber" : "gray";
}

export function PageHeader({ title, subtitle, actions }: { title: string; subtitle?: string; actions?: ReactNode }) {
  return (
    <div className="page-header">
      <div>
        <h1>{title}</h1>
        {subtitle && <p className="muted">{subtitle}</p>}
      </div>
      {actions && <div className="row">{actions}</div>}
    </div>
  );
}

export function Empty({ title, children }: { title: string; children?: ReactNode }) {
  return (
    <div className="card empty">
      <h3>{title}</h3>
      {children}
    </div>
  );
}

/** Dependency-free bar chart: sent DMs vs link clicks per day. */
export function DailyChart({ data }: { data: { date: string; sent: number; clicks: number }[] }) {
  const max = Math.max(1, ...data.map((d) => Math.max(d.sent, d.clicks)));
  const w = 100 / Math.max(1, data.length);
  return (
    <div className="chart">
      <div className="legend">
        <span><i className="dot dot-a" /> DMs sent</span>
        <span><i className="dot dot-b" /> Link clicks</span>
      </div>
      <svg viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-label="Daily DMs sent and link clicks">
        {data.map((d, i) => (
          <g key={d.date}>
            <title>{`${d.date}: ${d.sent} sent, ${d.clicks} clicks`}</title>
            <rect x={i * w + w * 0.1} width={w * 0.38} y={40 - (d.sent / max) * 38} height={(d.sent / max) * 38} className="bar-a" />
            <rect x={i * w + w * 0.52} width={w * 0.38} y={40 - (d.clicks / max) * 38} height={(d.clicks / max) * 38} className="bar-b" />
          </g>
        ))}
      </svg>
      <div className="chart-axis muted small">
        <span>{data[0]?.date}</span>
        <span>max {max}/day</span>
        <span>{data[data.length - 1]?.date}</span>
      </div>
    </div>
  );
}

export function ErrorNote({ error }: { error: string | null }) {
  return error ? <div className="alert alert-error">{error}</div> : null;
}
