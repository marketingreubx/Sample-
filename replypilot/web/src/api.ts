export class ApiError extends Error {
  constructor(message: string, public status: number) {
    super(message);
  }
}

export async function api<T = any>(path: string, opts: { method?: string; body?: unknown } = {}): Promise<T> {
  const res = await fetch(`/api${path}`, {
    method: opts.method ?? (opts.body ? "POST" : "GET"),
    headers: { "Content-Type": "application/json", "X-Requested-With": "replypilot" },
    credentials: "same-origin",
    body: opts.body ? JSON.stringify(opts.body) : undefined,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new ApiError(data.error ?? `Request failed (${res.status})`, res.status);
  return data as T;
}

export interface Me {
  user: { id: string; email: string; name: string | null } | null;
  workspaceId?: string;
  role?: "owner" | "admin" | "member";
  workspaces?: { id: string; name: string; role: string }[];
  demoMode: boolean;
  allowSignup?: boolean;
  appUrl?: string;
}

export interface Account {
  id: string;
  ig_user_id: string;
  username: string;
  name: string | null;
  profile_picture_url: string | null;
  login_type: "instagram" | "unified" | "demo";
  token_expires_at: string | null;
  status: "active" | "token_expired" | "error";
  status_detail: string | null;
  webhook_subscribed: boolean;
  connected_at: string;
}

export interface Campaign {
  id: string;
  name: string;
  instagram_account_id: string;
  account_username: string;
  is_active: boolean;
  trigger_comments: boolean;
  trigger_dms: boolean;
  trigger_story_replies: boolean;
  post_id: string | null;
  post_permalink: string | null;
  match_any_post: boolean;
  keywords: string[];
  match_any_word: boolean;
  whole_word: boolean;
  dm_message: string;
  link_url: string | null;
  link_button_label: string | null;
  public_reply_enabled: boolean;
  public_replies: string[];
  tracked_slug: string | null;
  triggered: number;
  sent: number;
  failed: number;
  skipped: number;
  public_replies_sent?: number;
  clicks: number;
  unique_clicks: number;
  created_at: string;
  daily?: { date: string; sent: number; clicks: number }[];
  keywordBreakdown?: { keyword: string; count: number }[];
}

export const fmtDate = (s: string | null | undefined) => (s ? new Date(s).toLocaleString() : "—");
export const pct = (a: number, b: number) => (b ? `${Math.round((a / b) * 100)}%` : "—");
