-- ReplyPilot initial schema
CREATE TABLE users (
  id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  email         text NOT NULL UNIQUE,
  name          text,
  password_hash text NOT NULL,
  created_at    timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE sessions (
  id          text PRIMARY KEY,             -- sha256 of the cookie token
  user_id     uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  workspace_id uuid,
  expires_at  timestamptz NOT NULL,
  created_at  timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX sessions_user_idx ON sessions(user_id);

CREATE TABLE workspaces (
  id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name       text NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE workspace_members (
  workspace_id uuid NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
  user_id      uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role         text NOT NULL CHECK (role IN ('owner','admin','member')),
  created_at   timestamptz NOT NULL DEFAULT now(),
  PRIMARY KEY (workspace_id, user_id)
);
CREATE INDEX workspace_members_user_idx ON workspace_members(user_id);

CREATE TABLE instagram_accounts (
  id                 uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  workspace_id       uuid NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
  ig_user_id         text NOT NULL UNIQUE,   -- professional account id (webhook entry.id)
  username           text NOT NULL,
  name               text,
  profile_picture_url text,
  login_type         text NOT NULL DEFAULT 'instagram' CHECK (login_type IN ('instagram','unified','demo')),
  access_token_enc   text NOT NULL,          -- AES-256-GCM, never returned by the API
  token_expires_at   timestamptz,
  status             text NOT NULL DEFAULT 'active' CHECK (status IN ('active','token_expired','error')),
  status_detail      text,
  webhook_subscribed boolean NOT NULL DEFAULT false,
  connected_at       timestamptz NOT NULL DEFAULT now(),
  updated_at         timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX instagram_accounts_ws_idx ON instagram_accounts(workspace_id);

CREATE TABLE campaigns (
  id                    uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  workspace_id          uuid NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
  instagram_account_id  uuid NOT NULL REFERENCES instagram_accounts(id) ON DELETE CASCADE,
  name                  text NOT NULL,
  is_active             boolean NOT NULL DEFAULT true,
  trigger_comments      boolean NOT NULL DEFAULT true,
  trigger_dms           boolean NOT NULL DEFAULT false,
  trigger_story_replies boolean NOT NULL DEFAULT false,
  post_id               text,                -- null + match_any_post = every post
  post_permalink        text,
  match_any_post        boolean NOT NULL DEFAULT false,
  keywords              text[] NOT NULL DEFAULT '{}',
  match_any_word        boolean NOT NULL DEFAULT false,
  whole_word            boolean NOT NULL DEFAULT true,
  dm_message            text NOT NULL,
  link_url              text,
  link_button_label     text,
  public_reply_enabled  boolean NOT NULL DEFAULT false,
  public_replies        text[] NOT NULL DEFAULT '{}',
  created_at            timestamptz NOT NULL DEFAULT now(),
  updated_at            timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX campaigns_ws_idx ON campaigns(workspace_id);
CREATE INDEX campaigns_account_idx ON campaigns(instagram_account_id);
CREATE INDEX campaigns_post_idx ON campaigns(post_id);

CREATE TABLE tracked_links (
  id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  workspace_id    uuid NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
  campaign_id     uuid NOT NULL REFERENCES campaigns(id) ON DELETE CASCADE,
  slug            text NOT NULL UNIQUE,
  destination_url text NOT NULL,
  created_at      timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX tracked_links_campaign_idx ON tracked_links(campaign_id);

CREATE TABLE link_clicks (
  id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  workspace_id    uuid NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
  campaign_id     uuid NOT NULL REFERENCES campaigns(id) ON DELETE CASCADE,
  tracked_link_id uuid NOT NULL REFERENCES tracked_links(id) ON DELETE CASCADE,
  recipient_hash  text,
  ip_hash         text,
  user_agent      text,
  created_at      timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX link_clicks_campaign_idx ON link_clicks(campaign_id, created_at);

-- Who messaged us and when: enforces Meta's 24-hour standard messaging window.
CREATE TABLE contacts (
  instagram_account_id uuid NOT NULL REFERENCES instagram_accounts(id) ON DELETE CASCADE,
  igsid                text NOT NULL,
  username             text,
  last_inbound_at      timestamptz,
  last_outbound_at     timestamptz,
  PRIMARY KEY (instagram_account_id, igsid)
);

CREATE TABLE message_logs (
  id                   uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  workspace_id         uuid NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
  campaign_id          uuid NOT NULL REFERENCES campaigns(id) ON DELETE CASCADE,
  instagram_account_id uuid NOT NULL REFERENCES instagram_accounts(id) ON DELETE CASCADE,
  trigger              text NOT NULL CHECK (trigger IN ('comment','dm','story_reply')),
  source_event_id      text NOT NULL,        -- comment id or message mid
  sender_id            text NOT NULL,
  sender_username      text,
  incoming_text        text NOT NULL DEFAULT '',
  matched_keyword      text,
  dm_text              text,
  status               text NOT NULL DEFAULT 'pending'
                       CHECK (status IN ('pending','sent','failed','skipped')),
  skip_reason          text,
  error                text,
  attempts             integer NOT NULL DEFAULT 0,
  sent_at              timestamptz,
  public_reply_text    text,
  public_reply_status  text NOT NULL DEFAULT 'none' CHECK (public_reply_status IN ('none','sent','failed')),
  public_reply_error   text,
  created_at           timestamptz NOT NULL DEFAULT now(),
  updated_at           timestamptz NOT NULL DEFAULT now(),
  UNIQUE (campaign_id, source_event_id)
);
CREATE INDEX message_logs_ws_idx ON message_logs(workspace_id, created_at DESC);
CREATE INDEX message_logs_event_idx ON message_logs(source_event_id);

CREATE TABLE webhook_events (
  id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  dedup_key    text NOT NULL UNIQUE,          -- sha256 of the raw body
  object       text,
  payload      jsonb NOT NULL,
  status       text NOT NULL DEFAULT 'received' CHECK (status IN ('received','processed','ignored','failed')),
  jobs_enqueued integer NOT NULL DEFAULT 0,
  error        text,
  created_at   timestamptz NOT NULL DEFAULT now(),
  processed_at timestamptz
);
CREATE INDEX webhook_events_created_idx ON webhook_events(created_at DESC);

-- Demo mode: what the fake Meta client "sent" instead of calling Instagram.
CREATE TABLE demo_outbox (
  id                   uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  instagram_account_id uuid REFERENCES instagram_accounts(id) ON DELETE CASCADE,
  kind                 text NOT NULL,
  recipient            text NOT NULL,
  text                 text,
  payload              jsonb,
  created_at           timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE operational_events (
  id         uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  level      text NOT NULL CHECK (level IN ('info','warning','error')),
  source     text NOT NULL,
  message    text NOT NULL,
  payload    jsonb,
  created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX operational_events_created_idx ON operational_events(created_at DESC);
