<?php
/**
 * Plugin Name: IR DM Automation
 * Description: Private Instagram comment-to-DM automation for the site owner at /dm/ (admins only).
 *   Connect an Instagram professional account through Meta's official API, pick a post, choose a trigger word
 *   and write the DM. Also handles keyword DMs and Story replies, optional public comment replies, tracked links,
 *   stats and a plain-English activity log. One-time Meta setup: wp-admin > DM Automation.
 *   WordPress port of ReplyPilot. Keyword normalization approach adapted from OpenReply
 *   (https://github.com/diwenne/openreply, MIT, (c) 2026 Anish Raj, Diwen Huang).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'IR_DM_VER', '1.1.0' );

/* =========================================================================
 * Settings, storage, crypto
 * ========================================================================= */

function ir_dm_opt( $k = null, $def = '' ) {
	$o = get_option( 'ir_dm_settings', [] );
	if ( ! is_array( $o ) ) { $o = []; }
	if ( empty( $o['verify'] ) ) { $o['verify'] = wp_generate_password( 24, false ); update_option( 'ir_dm_settings', $o, false ); }
	if ( $k === null ) { return $o; }
	return isset( $o[ $k ] ) && $o[ $k ] !== '' ? $o[ $k ] : $def;
}
function ir_dm_graph_ver() { return ir_dm_opt( 'graph', 'v25.0' ); }
function ir_dm_unified() { return ir_dm_opt( 'login' ) === 'unified'; }
function ir_dm_ready() {
	return ir_dm_unified() ? ( ir_dm_opt( 'meta_app_id' ) && ir_dm_opt( 'config_id' ) && ir_dm_secret( 'fb_secret' ) )
		: ( ir_dm_opt( 'ig_app_id' ) && ir_dm_secret( 'ig_secret' ) );
}

function ir_dm_key() { return hash( 'sha256', wp_salt( 'secure_auth' ) . '|ir-dm', true ); }
function ir_dm_enc( $plain ) {
	$iv = random_bytes( 12 ); $tag = '';
	$c = openssl_encrypt( (string) $plain, 'aes-256-gcm', ir_dm_key(), OPENSSL_RAW_DATA, $iv, $tag );
	return 'v1.' . base64_encode( $iv . $tag . $c );
}
function ir_dm_dec( $v ) {
	if ( ! is_string( $v ) || strpos( $v, 'v1.' ) !== 0 ) { return ''; }
	$b = base64_decode( substr( $v, 3 ) );
	if ( strlen( $b ) < 29 ) { return ''; }
	$p = openssl_decrypt( substr( $b, 28 ), 'aes-256-gcm', ir_dm_key(), OPENSSL_RAW_DATA, substr( $b, 0, 12 ), substr( $b, 12, 16 ) );
	return $p === false ? '' : $p;
}
function ir_dm_secret( $k ) { return ir_dm_dec( ir_dm_opt( $k ) ); }

function ir_dm_t( $n ) { global $wpdb; return $wpdb->prefix . 'ir_dm_' . $n; }

add_action( 'init', function () {
	register_post_type( 'ir_dm_auto', [ 'public' => false, 'show_ui' => false, 'label' => 'DM automations', 'supports' => [ 'title', 'author' ] ] );
	if ( get_option( 'ir_dm_db' ) !== IR_DM_VER ) { ir_dm_install(); }
} );

function ir_dm_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$cs = $wpdb->get_charset_collate();
	dbDelta( 'CREATE TABLE ' . ir_dm_t( 'log' ) . " (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 user_id bigint(20) unsigned NOT NULL,
 auto_id bigint(20) unsigned NOT NULL,
 ig_id varchar(40) NOT NULL,
 trig varchar(12) NOT NULL,
 event_id varchar(191) NOT NULL,
 sender_id varchar(40) NOT NULL,
 sender_name varchar(100) NOT NULL DEFAULT '',
 incoming text NULL,
 keyword varchar(100) NOT NULL DEFAULT '',
 dm_text text NULL,
 status varchar(10) NOT NULL DEFAULT 'pending',
 reason varchar(30) NOT NULL DEFAULT '',
 error text NULL,
 attempts smallint(5) unsigned NOT NULL DEFAULT 0,
 public_status varchar(10) NOT NULL DEFAULT 'none',
 public_text text NULL,
 created_at datetime NOT NULL,
 sent_at datetime NULL,
 PRIMARY KEY  (id),
 UNIQUE KEY auto_event (auto_id,event_id),
 KEY event_id (event_id),
 KEY ig_sent (ig_id,sent_at),
 KEY user_created (user_id,created_at)
) $cs;" );
	dbDelta( 'CREATE TABLE ' . ir_dm_t( 'click' ) . " (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 auto_id bigint(20) unsigned NOT NULL,
 user_id bigint(20) unsigned NOT NULL,
 rhash varchar(40) NOT NULL DEFAULT '',
 iphash varchar(64) NOT NULL DEFAULT '',
 created_at datetime NOT NULL,
 PRIMARY KEY  (id),
 KEY auto_id (auto_id)
) $cs;" );
	dbDelta( 'CREATE TABLE ' . ir_dm_t( 'lead' ) . " (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 user_id bigint(20) unsigned NOT NULL,
 ig_id varchar(40) NOT NULL,
 auto_id bigint(20) unsigned NOT NULL DEFAULT 0,
 igsid varchar(40) NOT NULL DEFAULT '',
 username varchar(100) NOT NULL DEFAULT '',
 email varchar(190) NOT NULL,
 created_at datetime NOT NULL,
 PRIMARY KEY  (id),
 UNIQUE KEY user_email (user_id,email),
 KEY user_created (user_id,created_at)
) $cs;" );
	dbDelta( 'CREATE TABLE ' . ir_dm_t( 'wait' ) . " (
 id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 ig_id varchar(40) NOT NULL,
 igsid varchar(40) NOT NULL,
 auto_id bigint(20) unsigned NOT NULL,
 nudged tinyint(1) NOT NULL DEFAULT 0,
 created_at datetime NOT NULL,
 PRIMARY KEY  (id),
 UNIQUE KEY who (ig_id,igsid)
) $cs;" );
	update_option( 'ir_dm_db', IR_DM_VER, false );
}

function ir_dm_now() { return gmdate( 'Y-m-d H:i:s' ); }

/* ---------- connected Instagram accounts (several per user) ---------- */

function ir_dm_accts( $uid ) {
	$all = get_user_meta( $uid, 'ir_dm_igs', true );
	if ( ! is_array( $all ) ) { $all = []; }
	$old = get_user_meta( $uid, 'ir_dm_ig', true ); // single-account data from v1
	if ( is_array( $old ) && ! empty( $old['id'] ) ) { $all[ (string) $old['id'] ] = $old; update_user_meta( $uid, 'ir_dm_igs', $all ); delete_user_meta( $uid, 'ir_dm_ig' ); }
	return $all;
}
function ir_dm_acct( $uid, $ig = null ) {
	$all = ir_dm_accts( $uid );
	if ( $ig !== null && $ig !== '' ) { return $all[ (string) $ig ] ?? null; }
	return $all ? reset( $all ) : null;
}
function ir_dm_save_acct( $uid, $a ) {
	$all = ir_dm_accts( $uid );
	$all[ (string) $a['id'] ] = $a;
	update_user_meta( $uid, 'ir_dm_igs', $all );
	if ( ! in_array( (string) $a['id'], array_map( 'strval', (array) get_user_meta( $uid, 'ir_dm_ig_id' ) ), true ) ) { add_user_meta( $uid, 'ir_dm_ig_id', (string) $a['id'] ); }
}
function ir_dm_remove_acct( $uid, $ig ) {
	$all = ir_dm_accts( $uid );
	unset( $all[ (string) $ig ] );
	update_user_meta( $uid, 'ir_dm_igs', $all );
	delete_user_meta( $uid, 'ir_dm_ig_id', (string) $ig );
}
function ir_dm_acct_public( $a ) {
	if ( ! $a ) { return null; }
	return [ 'id' => (string) $a['id'], 'username' => $a['username'], 'name' => $a['name'] ?? '', 'pic' => $a['pic'] ?? '', 'status' => $a['status'] ?? 'active', 'expires' => $a['expires'] ?? 0, 'subscribed' => ! empty( $a['subscribed'] ) ];
}
function ir_dm_owner( $ig_id ) {
	$u = get_users( [ 'meta_key' => 'ir_dm_ig_id', 'meta_value' => (string) $ig_id, 'number' => 1, 'fields' => 'ID' ] );
	return $u ? (int) $u[0] : 0;
}
function ir_dm_owners() { return array_values( array_unique( array_map( 'intval', get_users( [ 'meta_key' => 'ir_dm_ig_id', 'fields' => 'ID', 'number' => 5000 ] ) ) ) ); }
function ir_dm_mark_acct( $uid, $status, $detail = '', $ig = null ) {
	$a = ir_dm_acct( $uid, $ig ); if ( ! $a ) { return; }
	$a['status'] = $status; $a['detail'] = $detail;
	ir_dm_save_acct( $uid, $a );
}
/** Who may use the app: admins always; everyone else depends on the Access setting. */
function ir_dm_can( $uid = 0 ) {
	$uid = $uid ?: get_current_user_id();
	if ( ! $uid ) { return false; }
	if ( user_can( $uid, 'manage_options' ) ) { return true; }
	switch ( ir_dm_opt( 'access', 'everyone' ) ) {
		case 'everyone': return true;
		case 'members': return ( function_exists( 'ir_pp_has_access' ) && ir_pp_has_access( $uid ) ) || ( function_exists( 'ir_pb_has_access' ) && ir_pb_has_access( $uid ) );
		default: return false;
	}
}

/* ---------- automations (custom post type, config in post meta) ---------- */

function ir_dm_autos( $uid, $trigger = null, $only_active = true ) {
	$posts = get_posts( [ 'post_type' => 'ir_dm_auto', 'author' => (int) $uid, 'post_status' => $only_active ? 'publish' : [ 'publish', 'draft' ], 'numberposts' => 100, 'orderby' => 'date', 'order' => 'ASC' ] );
	$out = [];
	foreach ( $posts as $p ) {
		$c = get_post_meta( $p->ID, '_ir_dm', true );
		if ( ! is_array( $c ) ) { continue; }
		if ( $trigger && empty( $c['t'][ $trigger ] ) ) { continue; }
		$out[] = [ 'id' => $p->ID, 'name' => $p->post_title, 'on' => $p->post_status === 'publish', 'cfg' => $c, 'created' => $p->post_date_gmt ];
	}
	return $out;
}
function ir_dm_autos_for( $uid, $trigger, $ig ) {
	$first = ir_dm_acct( $uid );
	$firstid = $first ? (string) $first['id'] : '';
	return array_values( array_filter( ir_dm_autos( $uid, $trigger ), function ( $a ) use ( $ig, $firstid ) {
		$acct = (string) ( $a['cfg']['acct'] ?? '' );
		return $acct === (string) $ig || ( $acct === '' && (string) $ig === $firstid );
	} ) );
}
function ir_dm_slug( $auto_id ) {
	$s = get_post_meta( $auto_id, '_ir_dm_slug', true );
	if ( ! $s ) { $s = strtolower( wp_generate_password( 8, false ) ); update_post_meta( $auto_id, '_ir_dm_slug', $s ); }
	return $s;
}

/* =========================================================================
 * Matching, personalization, Meta rules
 * ========================================================================= */

function ir_dm_norm( $s ) {
	$s = remove_accents( (string) $s );
	$s = mb_strtolower( $s, 'UTF-8' );
	$s = preg_replace( '/[^\p{L}\p{N}\p{M}\s]+/u', ' ', $s ); // drops emoji and punctuation
	return trim( (string) preg_replace( '/\s+/u', ' ', (string) $s ) );
}
/** Returns the matched keyword, '' for "any message", or false. */
function ir_dm_match( $text, $cfg ) {
	if ( ! empty( $cfg['any_word'] ) ) { return ''; }
	$t = ir_dm_norm( $text );
	if ( $t === '' ) { return false; }
	foreach ( (array) ( $cfg['keywords'] ?? [] ) as $k ) {
		$n = ir_dm_norm( $k );
		if ( $n === '' ) { continue; }
		if ( preg_match( '/^[0-9o]+$/', $n ) ) { $hay = str_replace( 'o', '0', $t ); $n = str_replace( 'o', '0', $n ); } else { $hay = $t; }
		if ( preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $n, '/' ) . '(?![\p{L}\p{N}])/u', $hay ) ) { return (string) $k; }
	}
	return false;
}
function ir_dm_render( $tpl, $name, $kw, $link ) {
	$o = str_ireplace( [ '{name}', '{username}' ], $name ? '@' . ltrim( $name, '@' ) : 'there', (string) $tpl );
	$o = str_ireplace( '{keyword}', (string) $kw, $o );
	if ( $link ) { $o = stripos( $o, '{link}' ) !== false ? str_ireplace( '{link}', $link, $o ) : $o . "\n" . $link; }
	else { $o = preg_replace( '/[ \t]*\{link\}[ \t]*/i', ' ', $o ); }
	$o = preg_replace( [ '/[ \t]{2,}/', '/[ \t]+\n/', '/\n[ \t]+/', '/\n{3,}/' ], [ ' ', "\n", "\n", "\n\n" ], (string) $o );
	return trim( (string) $o );
}
/** Instagram rejects text above 1000 bytes. */
function ir_dm_trunc( $s, $max = 1000 ) { return strlen( $s ) <= $max ? $s : mb_strcut( $s, 0, $max - 3, 'UTF-8' ) . '…'; }
function ir_dm_rtok( $sender ) { return substr( hash_hmac( 'sha256', 'r:' . $sender, wp_salt( 'auth' ) ), 0, 22 ); }

function ir_dm_build( $cfg, $auto_id, $name, $kw, $sender ) {
	$link = '';
	if ( ! empty( $cfg['link'] ) ) { $link = home_url( '/dmlink/' . ( $auto_id ? ir_dm_slug( $auto_id ) : 'preview' ) . '/' ) . '?r=' . ir_dm_rtok( $sender ); }
	if ( $link && ! empty( $cfg['button'] ) ) {
		$t = ir_dm_render( $cfg['message'], $name, $kw, '' );
		return [ 'text' => mb_substr( $t !== '' ? $t : 'Here you go 👇', 0, 640 ), 'button' => [ 'title' => mb_substr( $cfg['button'], 0, 20 ), 'url' => $link ] ];
	}
	return [ 'text' => ir_dm_trunc( ir_dm_render( $cfg['message'], $name, $kw, $link ) ) ];
}

/* Meta's rules, enforced before every send:
 * - one private reply per comment, ever, within 7 days of the comment
 * - DM / Story reply answers only within 24 hours of the person's message, one answer per message, 10-min cooldown
 * - never reply to the account itself or to echoes of our own messages
 * - hourly caps per account (private replies 750 documented by Meta, DMs 200 by default) + 15-min pause when throttled */
define( 'IR_DM_PRIVATE_WINDOW', 7 * DAY_IN_SECONDS );
define( 'IR_DM_MSG_WINDOW', DAY_IN_SECONDS );
define( 'IR_DM_COOLDOWN', 600 );
define( 'IR_DM_MAX_ATTEMPTS', 5 );

function ir_dm_reason_text( $r ) {
	$map = [
		'old_comment'     => "The comment was more than 7 days old. Instagram doesn't allow a DM after that.",
		'already_replied' => 'This comment already got its DM from another automation (Instagram allows one).',
		'window'          => "They messaged more than 24 hours ago. Instagram doesn't allow a reply after that.",
		'cooldown'        => 'You already replied to them a few minutes ago.',
		'not_connected'   => 'Your Instagram needs reconnecting.',
		'not_following'   => 'Asked them to follow you first.',
		'asked_again'     => 'Asked them again for their email.',
		'no_email'        => 'They did not send an email address.',
	];
	return $map[ $r ] ?? $r;
}
function ir_dm_friendly( $err ) {
	$err = (string) $err;
	if ( $err === '' ) { return ''; }
	if ( strpos( $err, 'code=190' ) !== false ) { return 'Instagram logged you out. Tap Reconnect at the top of this page.'; }
	if ( strpos( $err, '2534037' ) !== false ) { return 'Another app (like ManyChat) controls your DMs. Remove it in Meta Business Suite > Settings > Integrations, then try again.'; }
	if ( preg_match( '/code=551\b|2534022|2018278|2018108/', $err ) ) { return "Instagram won't let you message this person right now."; }
	if ( preg_match( '/code=(4|17|32|613|80002|368)\b/', $err ) ) { return 'Instagram asked us to slow down. It will retry by itself.'; }
	if ( stripos( $err, 'private reply' ) !== false ) { return 'This comment already got a DM or was deleted.'; }
	if ( preg_match( '/code=(1|2)\b|HTTP 5|timed out|cURL/i', $err ) ) { return 'Instagram was busy. It will retry by itself.'; }
	return "Instagram didn't accept this message.";
}

/* =========================================================================
 * Graph API
 * ========================================================================= */

function ir_dm_err( $j, $http ) {
	$e = is_array( $j ) && isset( $j['error'] ) && is_array( $j['error'] ) ? $j['error'] : [];
	$c = (int) ( $e['code'] ?? 0 ); $s = (int) ( $e['error_subcode'] ?? 0 );
	$msg = ( $e['message'] ?? ( $j['error_message'] ?? "HTTP $http" ) ) . " [code=$c sub=$s]";
	$kind = 'unknown';
	if ( $c === 190 ) { $kind = 'token'; }
	elseif ( in_array( $c, [ 4, 17, 32, 613, 80002, 368 ], true ) ) { $kind = 'rate'; }
	elseif ( $c === 551 || in_array( $s, [ 2534022, 2018278, 2018108 ], true ) ) { $kind = 'window'; }
	elseif ( in_array( $c, [ 10, 100, 200, 230 ], true ) ) { $kind = 'permission'; }
	elseif ( in_array( $c, [ 1, 2 ], true ) || $http >= 500 ) { $kind = 'transient'; }
	return new WP_Error( $kind, $msg, [ 'code' => $c, 'sub' => $s ] );
}

function ir_dm_api( $acct, $method, $path, $body = null ) {
	$base = ( $acct['login'] ?? 'instagram' ) === 'unified' ? 'https://graph.facebook.com/' : 'https://graph.instagram.com/';
	$args = [ 'method' => $method, 'timeout' => 20, 'headers' => [ 'Authorization' => 'Bearer ' . ir_dm_dec( $acct['token'] ), 'Content-Type' => 'application/json' ] ];
	if ( $body !== null ) { $args['body'] = wp_json_encode( $body ); }
	$r = wp_remote_request( $base . ir_dm_graph_ver() . '/' . ltrim( $path, '/' ), $args );
	if ( is_wp_error( $r ) ) { return new WP_Error( 'transient', 'Network: ' . $r->get_error_message() ); }
	$http = (int) wp_remote_retrieve_response_code( $r );
	$j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	if ( $http >= 400 || ( is_array( $j ) && isset( $j['error'] ) ) ) { return ir_dm_err( $j, $http ); }
	return is_array( $j ) ? $j : [];
}

function ir_dm_msg_body( $m ) {
	if ( empty( $m['button'] ) ) { return [ 'text' => $m['text'] ]; }
	return [ 'attachment' => [ 'type' => 'template', 'payload' => [ 'template_type' => 'button', 'text' => $m['text'],
		'buttons' => [ ! empty( $m['button']['payload'] ) ? [ 'type' => 'postback', 'payload' => $m['button']['payload'], 'title' => $m['button']['title'] ] : [ 'type' => 'web_url', 'url' => $m['button']['url'], 'title' => $m['button']['title'] ] ] ] ] ];
}

/* ---------- extras: follow to unlock, collect email ---------- */

/** The first DM for an automation: follow gate, email question, or the content itself. */
function ir_dm_first( $c, $auto_id, $name, $kw, $sender ) {
	if ( ! empty( $c['follow'] ) ) {
		$t = ir_dm_render( ( $c['follow_msg'] ?? '' ) ?: 'Hey {name}! Tap below and I will send it right over 👇', $name, $kw, '' );
		return [ 'text' => mb_substr( $t, 0, 640 ), 'button' => [ 'title' => mb_substr( ( $c['follow_btn'] ?? '' ) ?: 'Send it to me', 0, 20 ), 'payload' => 'fg:' . (int) $auto_id ] ];
	}
	if ( ! empty( $c['ask_email'] ) ) {
		return [ 'text' => ir_dm_trunc( ir_dm_render( ( $c['email_ask'] ?? '' ) ?: 'Hey {name}! What is the best email to send it to? 📩', $name, $kw, '' ) ) ];
	}
	return ir_dm_build( $c, $auto_id, $name, $kw, $sender );
}
/** Content sent once the email arrives (thank-you text if set, else the normal DM). */
function ir_dm_after_email( $c, $auto_id, $name, $sender ) {
	if ( ! empty( $c['email_thanks'] ) ) { $c['message'] = $c['email_thanks']; }
	return ir_dm_build( $c, $auto_id, $name, '', $sender );
}
function ir_dm_wait_set( $ig, $igsid, $auto_id ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'REPLACE INTO ' . ir_dm_t( 'wait' ) . ' (ig_id,igsid,auto_id,nudged,created_at) VALUES (%s,%s,%d,0,%s)', $ig, $igsid, $auto_id, ir_dm_now() ) );
}
function ir_dm_wait_get( $ig, $igsid ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ir_dm_t( 'wait' ) . ' WHERE ig_id=%s AND igsid=%s AND created_at > %s', $ig, $igsid, gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) );
}
/** Save a lead, then forward it to the user's email list (webhook) if they set one. */
function ir_dm_save_lead( $uid, $ig, $auto_id, $igsid, $username, $email ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . ir_dm_t( 'lead' ) . ' (user_id,ig_id,auto_id,igsid,username,email,created_at) VALUES (%d,%s,%d,%s,%s,%s,%s)', $uid, $ig, $auto_id, $igsid, $username, $email, ir_dm_now() ) );
	$hook = (string) get_user_meta( $uid, 'ir_dm_hook', true );
	if ( $hook && wp_http_validate_url( $hook ) ) {
		wp_remote_post( $hook, [ 'timeout' => 5, 'blocking' => false, 'headers' => [ 'Content-Type' => 'application/json' ], 'body' => wp_json_encode( [ 'email' => $email, 'instagram' => $username, 'automation' => get_the_title( $auto_id ), 'account' => ( ir_dm_acct( $uid, $ig )['username'] ?? '' ), 'source' => IR_DM_NAME, 'date' => gmdate( 'c' ) ] ) ] );
	}
}

function ir_dm_get( $url, $args = [] ) {
	$r = wp_remote_request( $url, $args + [ 'timeout' => 20 ] );
	if ( is_wp_error( $r ) ) { return new WP_Error( 'transient', $r->get_error_message() ); }
	$http = (int) wp_remote_retrieve_response_code( $r );
	$j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	if ( $http >= 400 || ( is_array( $j ) && ( isset( $j['error'] ) || isset( $j['error_message'] ) ) ) ) { return ir_dm_err( $j, $http ); }
	return is_array( $j ) ? $j : [];
}

/* =========================================================================
 * Webhook: GET verification + signed POST deliveries
 * ========================================================================= */

function ir_dm_webhook_url() { return rest_url( 'ir-dm/v1/webhook' ); }
define( 'IR_DM_NAME', 'Reubx DM' );
function ir_dm_url( $p = '' ) { return home_url( '/reubxdm/' . ( $p ? trim( $p, '/' ) . '/' : '' ) ); }
/* Registered in Meta as the OAuth redirect: keep it stable. */
function ir_dm_redirect_uri() { return home_url( '/dm/callback/' ); }

add_action( 'rest_api_init', function () {
	register_rest_route( 'ir-dm/v1', '/webhook', [
		[ 'methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => 'ir_dm_webhook_verify' ],
		[ 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'ir_dm_webhook_post' ],
	] );
} );

function ir_dm_webhook_verify( WP_REST_Request $req ) {
	$q = $req->get_query_params();
	if ( ( $q['hub_mode'] ?? $q['hub.mode'] ?? '' ) === 'subscribe' && hash_equals( (string) ir_dm_opt( 'verify' ), (string) ( $q['hub_verify_token'] ?? $q['hub.verify_token'] ?? '' ) ) ) {
		header( 'Content-Type: text/plain' );
		echo esc_html( (string) ( $q['hub_challenge'] ?? $q['hub.challenge'] ?? '' ) );
		exit;
	}
	return new WP_REST_Response( [ 'error' => 'Verification failed' ], 403 );
}

function ir_dm_sig_ok( $raw, $sig ) {
	if ( ! is_string( $sig ) || strpos( $sig, 'sha256=' ) !== 0 ) { return false; }
	foreach ( array_filter( [ ir_dm_secret( 'ig_secret' ), ir_dm_secret( 'fb_secret' ) ] ) as $s ) {
		if ( hash_equals( 'sha256=' . hash_hmac( 'sha256', $raw, $s ), $sig ) ) { return true; }
	}
	return false;
}

/** Normalizes a webhook body into events. Skips the account's own comments, echoes, deleted and text-less messages. */
function ir_dm_parse( $p ) {
	$out = [];
	if ( ! is_array( $p ) || ( $p['object'] ?? '' ) !== 'instagram' ) { return $out; }
	foreach ( (array) ( $p['entry'] ?? [] ) as $en ) {
		$acct = isset( $en['id'] ) ? (string) $en['id'] : '';
		$ts = isset( $en['time'] ) ? (int) $en['time'] : time();
		if ( $ts > 1e12 ) { $ts = (int) ( $ts / 1000 ); }
		foreach ( (array) ( $en['changes'] ?? [] ) as $ch ) {
			if ( ! in_array( $ch['field'] ?? '', [ 'comments', 'live_comments' ], true ) ) { continue; }
			$v = (array) ( $ch['value'] ?? [] );
			$cid = $v['id'] ?? ( $v['comment_id'] ?? '' ); $mid = $v['media']['id'] ?? ( $v['media_id'] ?? '' ); $from = $v['from']['id'] ?? '';
			if ( ! $acct || ! $cid || ! $mid || ! $from || (string) $from === $acct ) { continue; }
			$om = $v['media']['original_media_id'] ?? '';
			$out[] = [ 'k' => 'comment', 'ig' => $acct, 'id' => (string) $cid, 'text' => (string) ( $v['text'] ?? '' ), 'from' => (string) $from,
				'uname' => (string) ( $v['from']['username'] ?? '' ), 'media' => (string) $mid, 'omedia' => $om && $om !== $mid ? (string) $om : '', 'ts' => $ts ];
		}
		foreach ( (array) ( $en['messaging'] ?? [] ) as $m ) {
			if ( ! empty( $m['postback']['payload'] ) ) {
				$from = (string) ( $m['sender']['id'] ?? '' ); $a = $acct ?: (string) ( $m['recipient']['id'] ?? '' );
				if ( $from && $a && $from !== $a ) {
					$pts = isset( $m['timestamp'] ) ? (int) $m['timestamp'] : $ts; if ( $pts > 1e12 ) { $pts = (int) ( $pts / 1000 ); }
					$out[] = [ 'k' => 'postback', 'ig' => $a, 'id' => 'pb_' . ( $m['postback']['mid'] ?? md5( $from . $m['postback']['payload'] . $pts ) ), 'text' => (string) $m['postback']['payload'], 'from' => $from, 'uname' => '', 'media' => '', 'omedia' => '', 'ts' => $pts ];
				}
				continue;
			}
			$msg = $m['message'] ?? null;
			if ( ! is_array( $msg ) || ! empty( $msg['is_echo'] ) || ! empty( $msg['is_deleted'] ) || ! empty( $msg['is_unsupported'] ) ) { continue; }
			$from = (string) ( $m['sender']['id'] ?? '' ); $a = $acct ?: (string) ( $m['recipient']['id'] ?? '' );
			$text = trim( (string) ( $msg['text'] ?? '' ) ); $story = $msg['reply_to']['story'] ?? null;
			if ( ! $from || ! $a || empty( $msg['mid'] ) || $from === $a || ( $text === '' && ! $story ) ) { continue; }
			$mts = isset( $m['timestamp'] ) ? (int) $m['timestamp'] : $ts;
			if ( $mts > 1e12 ) { $mts = (int) ( $mts / 1000 ); }
			$out[] = [ 'k' => $story ? 'story' : 'dm', 'ig' => $a, 'id' => (string) $msg['mid'], 'text' => $text, 'from' => $from, 'uname' => '', 'media' => '', 'omedia' => '', 'ts' => $mts ];
		}
	}
	return $out;
}

function ir_dm_webhook_post( WP_REST_Request $req ) {
	$raw = (string) $req->get_body();
	if ( ! ir_dm_sig_ok( $raw, $req->get_header( 'x_hub_signature_256' ) ) ) {
		update_option( 'ir_dm_last_bad_sig', time(), false );
		$j = json_decode( $raw, true );
		update_option( 'ir_dm_bad_sig_info', [ 't' => time(), 'len' => strlen( $raw ), 'object' => $j['object'] ?? null, 'entry_ids' => array_map( function ( $e ) { return $e['id'] ?? null; }, (array) ( $j['entry'] ?? [] ) ), 'fields' => array_map( function ( $c ) { return $c['field'] ?? null; }, (array) ( $j['entry'][0]['changes'] ?? [] ) ), 'has_msg' => ! empty( $j['entry'][0]['messaging'] ), 'sig_prefix' => substr( (string) $req->get_header( 'x_hub_signature_256' ), 0, 14 ), 'ua' => substr( (string) $req->get_header( 'user_agent' ), 0, 60 ) ], false );
		return new WP_REST_Response( [ 'error' => 'Invalid signature' ], 401 );
	}
	update_option( 'ir_dm_last_hook', time(), false );
	// Duplicate delivery (Meta retries identical bodies): accept and ignore.
	$dk = 'ir_dm_wh_' . md5( $raw );
	if ( get_transient( $dk ) ) { return new WP_REST_Response( [ 'ok' => true, 'duplicate' => true ], 200 ); }
	set_transient( $dk, 1, 2 * DAY_IN_SECONDS );

	$events = [];
	foreach ( ir_dm_parse( json_decode( $raw, true ) ) as $e ) {
		if ( ! ir_dm_owner( $e['ig'] ) ) { continue; }
		// Durable queue first (Action Scheduler retries if this request dies), then process right away after replying.
		if ( function_exists( 'as_enqueue_async_action' ) ) { as_enqueue_async_action( 'ir_dm_job', [ $e, 0 ], 'ir-dm', true ); }
		$events[] = $e;
	}
	if ( $events ) {
		update_option( 'ir_dm_last_real_hook', time(), false );
		add_action( 'shutdown', function () use ( $events ) {
			if ( function_exists( 'litespeed_finish_request' ) ) { litespeed_finish_request(); }
			elseif ( function_exists( 'fastcgi_finish_request' ) ) { fastcgi_finish_request(); }
			foreach ( $events as $e ) { ir_dm_job( $e, 0 ); }
		} );
	}
	return new WP_REST_Response( [ 'ok' => true, 'events' => count( $events ) ], 200 );
}

/* =========================================================================
 * Worker: process one event (idempotent; safe to run twice)
 * ========================================================================= */

add_action( 'ir_dm_job', 'ir_dm_job', 10, 2 );

function ir_dm_lock( $key ) {
	$name = '_ir_dm_lock_' . md5( $key );
	if ( add_option( $name, time(), '', 'no' ) ) { return $name; }
	if ( (int) get_option( $name ) < time() - 120 ) { update_option( $name, time(), false ); return $name; }
	return false;
}

function ir_dm_job( $e, $n = 0 ) {
	if ( ! is_array( $e ) || empty( $e['id'] ) ) { return; }
	$lock = ir_dm_lock( $e['id'] );
	if ( ! $lock ) { if ( function_exists( 'as_schedule_single_action' ) ) { as_schedule_single_action( time() + 30, 'ir_dm_job', [ $e, $n ], 'ir-dm' ); } return; }
	try {
		$res = $e['k'] === 'comment' ? ir_dm_do_comment( $e ) : ( $e['k'] === 'postback' ? ir_dm_do_postback( $e ) : ir_dm_do_message( $e ) );
	} catch ( Throwable $t ) {
		$res = [ 'retry' => 1 ];
		error_log( '[ir-dm] ' . $t->getMessage() );
	} finally {
		delete_option( $lock );
	}
	if ( ! function_exists( 'as_schedule_single_action' ) || ! is_array( $res ) ) { return; }
	if ( ! empty( $res['defer'] ) ) { as_schedule_single_action( time() + (int) $res['defer'], 'ir_dm_job', [ $e, $n ], 'ir-dm' ); }
	elseif ( ! empty( $res['retry'] ) && $n < IR_DM_MAX_ATTEMPTS ) { as_schedule_single_action( time() + [ 60, 300, 900, 1800, 3600 ][ min( $n, 4 ) ], 'ir_dm_job', [ $e, $n + 1 ], 'ir-dm' ); }
}

function ir_dm_claim( $uid, $auto_id, $e, $kw ) {
	global $wpdb; $t = ir_dm_t( 'log' );
	$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $t (user_id,auto_id,ig_id,trig,event_id,sender_id,sender_name,incoming,keyword,created_at) VALUES (%d,%d,%s,%s,%s,%s,%s,%s,%s,%s)",
		$uid, $auto_id, $e['ig'], $e['k'], $e['id'], $e['from'], mb_substr( (string) $e['uname'], 0, 100 ), mb_substr( (string) $e['text'], 0, 2000 ), mb_substr( (string) $kw, 0, 100 ), ir_dm_now() ) );
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE auto_id=%d AND event_id=%s", $auto_id, $e['id'] ) );
}
function ir_dm_set( $log_id, $data ) { global $wpdb; $wpdb->update( ir_dm_t( 'log' ), $data, [ 'id' => (int) $log_id ] ); }

function ir_dm_paused( $uid ) { $p = (int) get_user_meta( $uid, 'ir_dm_pause', true ); return $p > time() ? $p - time() : 0; }
function ir_dm_pause( $uid ) { update_user_meta( $uid, 'ir_dm_pause', time() + 900 + wp_rand( 0, 120 ) ); }

/** Seconds to wait before the hourly cap frees a slot (0 = send now). */
function ir_dm_cap_wait( $ig, $kind ) {
	global $wpdb; $t = ir_dm_t( 'log' );
	$max = $kind === 'comment' ? (int) ir_dm_opt( 'cap_private', 750 ) : (int) ir_dm_opt( 'cap_dm', 200 );
	$trig = $kind === 'comment' ? "trig='comment'" : "trig IN ('dm','story')";
	$since = gmdate( 'Y-m-d H:i:s', time() - 3600 );
	$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE ig_id=%s AND $trig AND status='sent' AND sent_at > %s", $ig, $since ) );
	if ( $n < $max ) { return 0; }
	$oldest = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(sent_at) FROM $t WHERE ig_id=%s AND $trig AND status='sent' AND sent_at > %s", $ig, $since ) );
	return max( 60, strtotime( $oldest . ' UTC' ) + 3600 - time() );
}

/** Records a failed send. Returns ['retry'=>1], ['defer'=>secs] or null. */
function ir_dm_fail( $uid, $log_id, WP_Error $err, $ig = null ) {
	ir_dm_set( $log_id, [ 'status' => 'failed', 'error' => $err->get_error_message() ] );
	switch ( $err->get_error_code() ) {
		case 'token': ir_dm_mark_acct( $uid, 'expired', $err->get_error_message(), $ig ); return null;
		case 'rate': ir_dm_pause( $uid ); return [ 'defer' => 900 ];
		case 'window':
		case 'permission': return null;
		default: return [ 'retry' => 1 ];
	}
}

function ir_dm_send( $acct, $recipient, $m ) {
	$r = ir_dm_api( $acct, 'POST', $acct['id'] . '/messages', [ 'recipient' => $recipient, 'message' => ir_dm_msg_body( $m ) ] );
	// Some accounts refuse button templates: fall back once to plain text with the link inline.
	if ( is_wp_error( $r ) && ! empty( $m['button']['url'] ) && $r->get_error_code() === 'permission' && ( $r->get_error_data()['code'] ?? 0 ) === 100 && ( $r->get_error_data()['sub'] ?? 0 ) !== 2534037 ) {
		$m = [ 'text' => ir_dm_trunc( $m['text'] . "\n" . $m['button']['url'] ) ];
		$r = ir_dm_api( $acct, 'POST', $acct['id'] . '/messages', [ 'recipient' => $recipient, 'message' => ir_dm_msg_body( $m ) ] );
	}
	return [ $r, $m ];
}

function ir_dm_do_comment( $e ) {
	$uid = ir_dm_owner( $e['ig'] );
	if ( ! $uid || $e['from'] === $e['ig'] ) { return 'ignored'; }
	$acct = ir_dm_acct( $uid, $e['ig'] );
	$retry = false; $defer = 0;
	foreach ( ir_dm_autos_for( $uid, 'comments', $e['ig'] ) as $a ) {
		$c = $a['cfg'];
		if ( empty( $c['any_post'] ) && ! in_array( (string) ( $c['post'] ?? '' ), array_filter( [ $e['media'], $e['omedia'] ] ), true ) ) { continue; }
		$kw = ir_dm_match( $e['text'], $c );
		if ( $kw === false ) { continue; }
		$log = ir_dm_claim( $uid, $a['id'], $e, $kw );
		if ( ! $log ) { continue; }
		$dm_done = in_array( $log->status, [ 'sent', 'skipped' ], true ) || (int) $log->attempts >= IR_DM_MAX_ATTEMPTS;
		$pub = array_values( array_filter( (array) ( $c['public'] ?? [] ) ) );
		$pub_done = empty( $c['public_on'] ) || ! $pub || $log->public_status === 'sent';
		if ( $dm_done && $pub_done ) { continue; }
		if ( ! $acct || ( $acct['status'] ?? 'active' ) !== 'active' ) { ir_dm_set( $log->id, [ 'status' => 'skipped', 'reason' => 'not_connected' ] ); continue; }
		if ( $w = ir_dm_paused( $uid ) ) { $defer = max( $defer, $w ); continue; }

		// 1) Optional public reply under the comment (independent of the DM).
		if ( ! $pub_done ) {
			$txt = ir_dm_trunc( ir_dm_render( $pub[ array_rand( $pub ) ], $e['uname'], $kw, '' ), 1000 );
			$r = ir_dm_api( $acct, 'POST', rawurlencode( $e['id'] ) . '/replies', [ 'message' => $txt ] );
			if ( is_wp_error( $r ) ) {
				ir_dm_set( $log->id, [ 'public_status' => 'failed', 'public_text' => $txt, 'error' => 'Public reply: ' . $r->get_error_message() ] );
				if ( $r->get_error_code() === 'token' ) { ir_dm_mark_acct( $uid, 'expired', $r->get_error_message(), $e['ig'] ); continue; }
				if ( $r->get_error_code() === 'rate' ) { ir_dm_pause( $uid ); $defer = max( $defer, 900 ); continue; }
				if ( $r->get_error_code() === 'transient' ) { $retry = true; }
			} else {
				ir_dm_set( $log->id, [ 'public_status' => 'sent', 'public_text' => $txt ] );
			}
		}
		if ( $dm_done ) { continue; }

		// 2) The private reply DM.
		if ( time() - (int) $e['ts'] > IR_DM_PRIVATE_WINDOW ) { ir_dm_set( $log->id, [ 'status' => 'skipped', 'reason' => 'old_comment' ] ); continue; }
		global $wpdb;
		$used = $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . ir_dm_t( 'log' ) . " WHERE event_id=%s AND trig='comment' AND status='sent' AND auto_id<>%d LIMIT 1", $e['id'], $a['id'] ) );
		if ( $used ) { ir_dm_set( $log->id, [ 'status' => 'skipped', 'reason' => 'already_replied' ] ); continue; }
		if ( $w = ir_dm_cap_wait( $e['ig'], 'comment' ) ) {
			if ( time() + $w > (int) $e['ts'] + IR_DM_PRIVATE_WINDOW ) { ir_dm_set( $log->id, [ 'status' => 'skipped', 'reason' => 'old_comment' ] ); }
			else { ir_dm_set( $log->id, [ 'error' => 'Waiting: hourly limit reached' ] ); $defer = max( $defer, $w ); }
			continue;
		}
		$m = ir_dm_first( $c, $a['id'], $e['uname'], $kw, $e['from'] );
		$attempts = (int) $log->attempts + 1;
		ir_dm_set( $log->id, [ 'attempts' => $attempts, 'dm_text' => $m['text'] ] );
		list( $r, $m ) = ir_dm_send( $acct, [ 'comment_id' => $e['id'] ], $m );
		if ( is_wp_error( $r ) ) {
			$f = ir_dm_fail( $uid, $log->id, $r, $e['ig'] );
			if ( ! empty( $f['defer'] ) ) { $defer = max( $defer, $f['defer'] ); }
			if ( ! empty( $f['retry'] ) && $attempts < IR_DM_MAX_ATTEMPTS ) { $retry = true; }
		} else {
			ir_dm_set( $log->id, [ 'status' => 'sent', 'sent_at' => ir_dm_now(), 'error' => null, 'dm_text' => $m['text'] ] );
			if ( empty( $c['follow'] ) && ! empty( $c['ask_email'] ) ) { ir_dm_wait_set( $e['ig'], $e['from'], $a['id'] ); }
		}
	}
	if ( $defer ) { return [ 'defer' => $defer ]; }
	return $retry ? [ 'retry' => 1 ] : 'done';
}

function ir_dm_do_message( $e ) {
	$uid = ir_dm_owner( $e['ig'] );
	if ( ! $uid || $e['from'] === $e['ig'] ) { return 'ignored'; }
	if ( $e['k'] === 'dm' && ( $w = ir_dm_wait_get( $e['ig'], $e['from'] ) ) ) { return ir_dm_do_email( $uid, $e, $w ); }
	$hit = null;
	foreach ( ir_dm_autos_for( $uid, $e['k'] === 'story' ? 'story' : 'dms', $e['ig'] ) as $a ) {
		$kw = ir_dm_match( $e['text'], $a['cfg'] );
		if ( $kw !== false ) { $hit = [ $a, $kw ]; break; } // one automated answer per message: the oldest matching automation
	}
	if ( ! $hit ) { return 'nomatch'; }
	list( $a, $kw ) = $hit;
	$log = ir_dm_claim( $uid, $a['id'], $e, $kw );
	if ( ! $log || in_array( $log->status, [ 'sent', 'skipped' ], true ) || (int) $log->attempts >= IR_DM_MAX_ATTEMPTS ) { return 'done'; }
	$acct = ir_dm_acct( $uid, $e['ig'] );
	if ( ! $acct || ( $acct['status'] ?? 'active' ) !== 'active' ) { ir_dm_set( $log->id, [ 'status' => 'skipped', 'reason' => 'not_connected' ] ); return 'done'; }
	if ( time() - (int) $e['ts'] > IR_DM_MSG_WINDOW ) { ir_dm_set( $log->id, [ 'status' => 'skipped', 'reason' => 'window' ] ); return 'done'; }
	global $wpdb;
	$recent = $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . ir_dm_t( 'log' ) . " WHERE auto_id=%d AND sender_id=%s AND status='sent' AND id<>%d AND sent_at > %s LIMIT 1",
		$a['id'], $e['from'], $log->id, gmdate( 'Y-m-d H:i:s', time() - IR_DM_COOLDOWN ) ) );
	if ( $recent ) { ir_dm_set( $log->id, [ 'status' => 'skipped', 'reason' => 'cooldown' ] ); return 'done'; }
	if ( $w = ir_dm_paused( $uid ) ) { return [ 'defer' => $w ]; }
	if ( $w = ir_dm_cap_wait( $e['ig'], 'dm' ) ) {
		if ( time() + $w > (int) $e['ts'] + IR_DM_MSG_WINDOW ) { ir_dm_set( $log->id, [ 'status' => 'skipped', 'reason' => 'window' ] ); return 'done'; }
		return [ 'defer' => $w ];
	}
	$name = '';
	$u = ir_dm_api( $acct, 'GET', rawurlencode( $e['from'] ) . '?fields=username' );
	if ( ! is_wp_error( $u ) && ! empty( $u['username'] ) ) { $name = (string) $u['username']; ir_dm_set( $log->id, [ 'sender_name' => mb_substr( $name, 0, 100 ) ] ); }
	$m = ir_dm_first( $a['cfg'], $a['id'], $name, $kw, $e['from'] );
	$attempts = (int) $log->attempts + 1;
	ir_dm_set( $log->id, [ 'attempts' => $attempts, 'dm_text' => $m['text'] ] );
	list( $r, $m ) = ir_dm_send( $acct, [ 'id' => $e['from'] ], $m );
	if ( is_wp_error( $r ) ) {
		$f = ir_dm_fail( $uid, $log->id, $r, $e['ig'] );
		if ( ! empty( $f['defer'] ) ) { return $f; }
		return ( ! empty( $f['retry'] ) && $attempts < IR_DM_MAX_ATTEMPTS ) ? [ 'retry' => 1 ] : 'failed';
	}
	ir_dm_set( $log->id, [ 'status' => 'sent', 'sent_at' => ir_dm_now(), 'error' => null, 'dm_text' => $m['text'] ] );
	if ( empty( $a['cfg']['follow'] ) && ! empty( $a['cfg']['ask_email'] ) ) { ir_dm_wait_set( $e['ig'], $e['from'], $a['id'] ); }
	return 'sent';
}

/** They replied while we wait for their email. */
function ir_dm_do_email( $uid, $e, $w ) {
	$c = get_post_meta( (int) $w->auto_id, '_ir_dm', true );
	$acct = ir_dm_acct( $uid, $e['ig'] );
	if ( ! is_array( $c ) || ! $acct ) { return 'ignored'; }
	$ev = $e; $ev['k'] = 'email';
	$log = ir_dm_claim( $uid, (int) $w->auto_id, $ev, '' );
	if ( ! $log || in_array( $log->status, [ 'sent', 'skipped' ], true ) ) { return 'done'; }
	if ( ! preg_match( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $e['text'], $mm ) ) {
		if ( (int) $w->nudged ) { ir_dm_set( $log->id, [ 'status' => 'skipped', 'reason' => 'no_email' ] ); return 'done'; }
		global $wpdb; $wpdb->update( ir_dm_t( 'wait' ), [ 'nudged' => 1 ], [ 'id' => (int) $w->id ] );
		list( $r ) = ir_dm_send( $acct, [ 'id' => $e['from'] ], [ 'text' => 'Could you reply with just your email address? Then I will send it straight over 🙏' ] );
		ir_dm_set( $log->id, is_wp_error( $r ) ? [ 'status' => 'failed', 'error' => $r->get_error_message() ] : [ 'status' => 'skipped', 'reason' => 'asked_again' ] );
		return 'nudged';
	}
	$email = strtolower( sanitize_email( $mm[0] ) );
	$name = '';
	$u = ir_dm_api( $acct, 'GET', rawurlencode( $e['from'] ) . '?fields=username' );
	if ( ! is_wp_error( $u ) && ! empty( $u['username'] ) ) { $name = (string) $u['username']; }
	ir_dm_save_lead( $uid, $e['ig'], (int) $w->auto_id, $e['from'], $name, $email );
	global $wpdb; $wpdb->delete( ir_dm_t( 'wait' ), [ 'id' => (int) $w->id ] );
	$m = ir_dm_after_email( $c, (int) $w->auto_id, $name, $e['from'] );
	ir_dm_set( $log->id, [ 'sender_name' => $name, 'keyword' => $email, 'attempts' => 1, 'dm_text' => $m['text'] ] );
	list( $r, $m ) = ir_dm_send( $acct, [ 'id' => $e['from'] ], $m );
	if ( is_wp_error( $r ) ) { $f = ir_dm_fail( $uid, $log->id, $r, $e['ig'] ); return ! empty( $f['retry'] ) ? [ 'retry' => 1 ] : 'failed'; }
	ir_dm_set( $log->id, [ 'status' => 'sent', 'sent_at' => ir_dm_now(), 'error' => null, 'dm_text' => $m['text'] ] );
	return 'sent';
}

/** They tapped the "Send it to me" button: check they follow, then deliver. */
function ir_dm_do_postback( $e ) {
	$uid = ir_dm_owner( $e['ig'] );
	if ( ! $uid || $e['from'] === $e['ig'] || ! preg_match( '/^fg:(\d+)$/', (string) $e['text'], $pm ) ) { return 'ignored'; }
	$p = get_post( (int) $pm[1] );
	$c = $p ? get_post_meta( $p->ID, '_ir_dm', true ) : null;
	$acct = ir_dm_acct( $uid, $e['ig'] );
	if ( ! $p || (int) $p->post_author !== $uid || ! is_array( $c ) || ! $acct ) { return 'ignored'; }
	$ev = $e; $ev['k'] = 'follow'; $ev['text'] = 'Tapped "' . ( ( $c['follow_btn'] ?? '' ) ?: 'Send it to me' ) . '"';
	$log = ir_dm_claim( $uid, $p->ID, $ev, '' );
	if ( ! $log || in_array( $log->status, [ 'sent', 'skipped' ], true ) ) { return 'done'; }
	$u = ir_dm_api( $acct, 'GET', rawurlencode( $e['from'] ) . '?fields=username,is_user_follow_business' );
	$name = ! is_wp_error( $u ) && ! empty( $u['username'] ) ? (string) $u['username'] : '';
	if ( $name ) { ir_dm_set( $log->id, [ 'sender_name' => $name ] ); }
	$follows = is_wp_error( $u ) || ! array_key_exists( 'is_user_follow_business', $u ) ? null : (bool) $u['is_user_follow_business'];
	if ( $follows === false ) {
		$k = 'ir_dm_fg_' . md5( $e['ig'] . $e['from'] . $p->ID ); $n = (int) get_transient( $k );
		if ( $n >= 3 ) { ir_dm_set( $log->id, [ 'status' => 'skipped', 'reason' => 'not_following' ] ); return 'done'; }
		set_transient( $k, $n + 1, DAY_IN_SECONDS );
		$m = [ 'text' => 'Almost there' . ( $name ? ' @' . $name : '' ) . '! Follow @' . $acct['username'] . ' first, then tap the button again and it is yours 👇', 'button' => [ 'title' => mb_substr( ( $c['follow_btn'] ?? '' ) ?: 'Send it to me', 0, 20 ), 'payload' => 'fg:' . $p->ID ] ];
		list( $r ) = ir_dm_send( $acct, [ 'id' => $e['from'] ], $m );
		ir_dm_set( $log->id, is_wp_error( $r ) ? [ 'status' => 'failed', 'error' => $r->get_error_message() ] : [ 'status' => 'skipped', 'reason' => 'not_following', 'dm_text' => $m['text'] ] );
		return 'asked_follow';
	}
	if ( ! empty( $c['ask_email'] ) ) {
		$m = [ 'text' => ir_dm_trunc( ir_dm_render( ( $c['email_ask'] ?? '' ) ?: 'Hey {name}! What is the best email to send it to? 📩', $name, '', '' ) ) ];
	} else {
		$m = ir_dm_build( $c, $p->ID, $name, '', $e['from'] );
	}
	ir_dm_set( $log->id, [ 'attempts' => 1, 'dm_text' => $m['text'] ] );
	list( $r, $m ) = ir_dm_send( $acct, [ 'id' => $e['from'] ], $m );
	if ( is_wp_error( $r ) ) { $f = ir_dm_fail( $uid, $log->id, $r, $e['ig'] ); return ! empty( $f['retry'] ) ? [ 'retry' => 1 ] : 'failed'; }
	ir_dm_set( $log->id, [ 'status' => 'sent', 'sent_at' => ir_dm_now(), 'error' => null, 'dm_text' => $m['text'] ] );
	if ( ! empty( $c['ask_email'] ) ) { ir_dm_wait_set( $e['ig'], $e['from'], $p->ID ); }
	return 'sent';
}

/* ---------- backup: check posts for new comments every 2 minutes ----------
 * Works even when Meta does not send webhooks (e.g. app not Live). Safe to run alongside webhooks:
 * every comment is processed once (log table + seen list). */
add_action( 'init', function () {
	if ( ! function_exists( 'as_has_scheduled_action' ) ) { return; }
	if ( get_option( 'ir_dm_poll_iv' ) !== '60' ) { as_unschedule_all_actions( 'ir_dm_poll', [], 'ir-dm' ); update_option( 'ir_dm_poll_iv', '60', false ); }
	if ( ! as_has_scheduled_action( 'ir_dm_poll' ) ) {
		as_schedule_recurring_action( time() + 30, 60, 'ir_dm_poll', [], 'ir-dm' );
	}
}, 21 );
add_action( 'ir_dm_poll', 'ir_dm_poll' );
function ir_dm_poll() {
	if ( get_transient( 'ir_dm_polling' ) ) { return 0; }
	set_transient( 'ir_dm_polling', 1, 55 );
	update_option( 'ir_dm_last_poll', time(), false );
	$found = 0;
	foreach ( ir_dm_owners() as $uid ) { foreach ( ir_dm_accts( $uid ) as $a ) {
		if ( ( $a['status'] ?? '' ) !== 'active' ) { continue; }
		ir_dm_poll_replies( $a );
		$autos = ir_dm_autos_for( $uid, 'comments', $a['id'] );
		if ( ! $autos ) { continue; }
		$media = []; $any = false;
		foreach ( $autos as $x ) { if ( ! empty( $x['cfg']['any_post'] ) ) { $any = true; } elseif ( ! empty( $x['cfg']['post'] ) ) { $media[] = (string) $x['cfg']['post']; } }
		if ( $any ) {
			$r = ir_dm_api( $a, 'GET', $a['id'] . '/media?fields=id&limit=10' );
			if ( ! is_wp_error( $r ) ) { foreach ( (array) ( $r['data'] ?? [] ) as $m ) { $media[] = (string) $m['id']; } }
		}
		$seen = get_user_meta( $uid, 'ir_dm_seen', true );
		if ( ! is_array( $seen ) ) { $seen = []; }
		foreach ( array_unique( $media ) as $mid ) {
			$r = ir_dm_api( $a, 'GET', $mid . '/comments?fields=id,text,timestamp,username,from&limit=50' );
			if ( is_wp_error( $r ) ) { if ( $r->get_error_code() === 'token' ) { ir_dm_mark_acct( $uid, 'expired', $r->get_error_message(), $a['id'] ); } continue; }
			foreach ( (array) ( $r['data'] ?? [] ) as $c ) {
				$cid = (string) ( $c['id'] ?? '' );
				if ( $cid === '' || isset( $seen[ 'c' . $cid ] ) ) { continue; }
				$seen[ 'c' . $cid ] = time();
				$ts = strtotime( (string) ( $c['timestamp'] ?? '' ) ) ?: time();
				if ( time() - $ts > DAY_IN_SECONDS ) { continue; }
				$from = (string) ( $c['from']['id'] ?? '' );
				$un = (string) ( $c['from']['username'] ?? ( $c['username'] ?? '' ) );
				if ( $from === '' || $from === (string) $a['id'] || strcasecmp( $un, (string) $a['username'] ) === 0 ) { continue; }
				$found++;
				ir_dm_job( [ 'k' => 'comment', 'ig' => (string) $a['id'], 'id' => $cid, 'text' => (string) ( $c['text'] ?? '' ), 'from' => $from, 'uname' => $un, 'media' => $mid, 'omedia' => '', 'ts' => $ts ], 0 );
			}
		}
		if ( count( $seen ) > 3000 ) { $seen = array_slice( $seen, -3000, null, true ); }
		update_user_meta( $uid, 'ir_dm_seen', $seen );
	} }
	delete_transient( 'ir_dm_polling' );
	return $found;
}

/** Read DM replies from people we are waiting on (email), for when Meta's notifications are not arriving. */
function ir_dm_poll_replies( $a ) {
	global $wpdb;
	$waits = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . ir_dm_t( 'wait' ) . ' WHERE ig_id=%s AND created_at > %s LIMIT 20', (string) $a['id'], gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) );
	foreach ( $waits as $w ) {
		$r = ir_dm_api( $a, 'GET', $a['id'] . '/conversations?platform=instagram&user_id=' . rawurlencode( $w->igsid ) . '&fields=messages.limit(5){id,message,from,created_time}' );
		if ( is_wp_error( $r ) ) { continue; }
		$since = strtotime( $w->created_at . ' UTC' );
		foreach ( (array) ( $r['data'][0]['messages']['data'] ?? [] ) as $m ) {
			$t = strtotime( (string) ( $m['created_time'] ?? '' ) ) ?: 0;
			if ( (string) ( $m['from']['id'] ?? '' ) !== (string) $w->igsid || $t < $since || empty( $m['message'] ) ) { continue; }
			ir_dm_job( [ 'k' => 'dm', 'ig' => (string) $a['id'], 'id' => (string) $m['id'], 'text' => (string) $m['message'], 'from' => (string) $w->igsid, 'uname' => '', 'media' => '', 'omedia' => '', 'ts' => $t ], 0 );
		}
	}
}

/* ---------- daily token refresh (Instagram-login tokens last 60 days) ---------- */

add_action( 'init', function () {
	if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( 'ir_dm_refresh' ) ) {
		as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, 'ir_dm_refresh', [], 'ir-dm' );
	}
}, 20 );
add_action( 'ir_dm_refresh', function () {
	foreach ( ir_dm_owners() as $uid ) { foreach ( ir_dm_accts( $uid ) as $a ) {
		if ( ( $a['login'] ?? '' ) !== 'instagram' || ( $a['status'] ?? '' ) !== 'active' ) { continue; }
		if ( empty( $a['expires'] ) || $a['expires'] > time() + 10 * DAY_IN_SECONDS || ( $a['refreshed'] ?? 0 ) > time() - DAY_IN_SECONDS ) { continue; }
		$r = ir_dm_get( 'https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token=' . rawurlencode( ir_dm_dec( $a['token'] ) ) );
		if ( is_wp_error( $r ) ) { if ( $r->get_error_code() === 'token' ) { ir_dm_mark_acct( $uid, 'expired', $r->get_error_message(), $a['id'] ); } continue; }
		$a['token'] = ir_dm_enc( $r['access_token'] ); $a['expires'] = time() + (int) ( $r['expires_in'] ?? 5184000 ); $a['refreshed'] = time();
		ir_dm_save_acct( $uid, $a );
	} }
} );

/* =========================================================================
 * Tracked links: /dmlink/{slug}/?r={recipient}
 * ========================================================================= */

add_action( 'template_redirect', function () {
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( ! preg_match( '#^dmlink/([a-z0-9]{6,20})$#', $path, $m ) ) { return; }
	$ids = get_posts( [ 'post_type' => 'ir_dm_auto', 'post_status' => [ 'publish', 'draft' ], 'meta_key' => '_ir_dm_slug', 'meta_value' => $m[1], 'numberposts' => 1, 'fields' => 'ids' ] );
	$cfg = $ids ? get_post_meta( $ids[0], '_ir_dm', true ) : null;
	if ( ! $ids || empty( $cfg['link'] ) ) { status_header( 404 ); echo 'Link not found'; exit; }
	do_action( 'litespeed_control_set_nocache', 'dm link' );
	$ua = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
	$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	$ipk = 'ir_dm_c_' . md5( $ids[0] . $ip );
	if ( ! preg_match( '/facebookexternalhit|bot|crawler|spider|preview/i', $ua ) && (int) get_transient( $ipk ) < 3 ) {
		set_transient( $ipk, (int) get_transient( $ipk ) + 1, 10 );
		global $wpdb;
		$r = isset( $_GET['r'] ) && preg_match( '/^[a-f0-9]{22}$/', (string) $_GET['r'] ) ? (string) $_GET['r'] : '';
		$wpdb->insert( ir_dm_t( 'click' ), [ 'auto_id' => $ids[0], 'user_id' => (int) get_post_field( 'post_author', $ids[0] ), 'rhash' => $r,
			'iphash' => hash( 'sha256', wp_salt( 'nonce' ) . $ip ), 'created_at' => ir_dm_now() ] );
	}
	wp_redirect( $cfg['link'], 302 ); // phpcs:ignore WordPress.Security.SafeRedirect -- member's own destination
	exit;
}, 1 );

/* =========================================================================
 * Connecting Instagram (OAuth)
 * ========================================================================= */

function ir_dm_state( $uid ) { $p = $uid . '.' . time(); return $p . '.' . substr( hash_hmac( 'sha256', $p, wp_salt( 'auth' ) . 'ir-dm-state' ), 0, 32 ); }
function ir_dm_state_ok( $s, $uid ) {
	$x = explode( '.', (string) $s );
	if ( count( $x ) !== 3 || (int) $x[0] !== (int) $uid || time() - (int) $x[1] > 900 ) { return false; }
	return hash_equals( substr( hash_hmac( 'sha256', $x[0] . '.' . $x[1], wp_salt( 'auth' ) . 'ir-dm-state' ), 0, 32 ), $x[2] );
}

function ir_dm_page_connect() {
	if ( ! ir_dm_ready() ) { wp_safe_redirect( ir_dm_url() ); exit; }
	$state = ir_dm_state( get_current_user_id() );
	if ( ir_dm_unified() ) {
		$url = 'https://www.facebook.com/' . ir_dm_graph_ver() . '/dialog/oauth?' . http_build_query( [ 'client_id' => ir_dm_opt( 'meta_app_id' ), 'config_id' => ir_dm_opt( 'config_id' ), 'redirect_uri' => ir_dm_redirect_uri(), 'response_type' => 'code', 'state' => $state ] );
	} else {
		$url = 'https://www.instagram.com/oauth/authorize?' . http_build_query( [ 'client_id' => ir_dm_opt( 'ig_app_id' ), 'redirect_uri' => ir_dm_redirect_uri(), 'response_type' => 'code',
			'scope' => 'instagram_business_basic,instagram_business_manage_messages,instagram_business_manage_comments', 'state' => $state ] );
	}
	wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect -- Meta login
	exit;
}

function ir_dm_page_callback() {
	$uid = get_current_user_id();
	$back = function ( $q ) { wp_safe_redirect( add_query_arg( $q, ir_dm_url() ) ); exit; };
	if ( ! empty( $_GET['error'] ) ) { $back( [ 'err' => 'You cancelled the Instagram login. Tap Connect Instagram to try again.' ] ); }
	if ( ! ir_dm_state_ok( wp_unslash( $_GET['state'] ?? '' ), $uid ) ) { $back( [ 'err' => 'That connection link expired. Please tap Connect Instagram again.' ] ); }
	$code = preg_replace( '/#_$/', '', (string) wp_unslash( $_GET['code'] ?? '' ) );
	if ( $code === '' ) { $back( [ 'err' => 'Instagram did not finish the login. Please try again.' ] ); }

	$ver = ir_dm_graph_ver();
	if ( ir_dm_unified() ) {
		$tok = ir_dm_get( "https://graph.facebook.com/$ver/oauth/access_token?" . http_build_query( [ 'client_id' => ir_dm_opt( 'meta_app_id' ), 'client_secret' => ir_dm_secret( 'fb_secret' ), 'redirect_uri' => ir_dm_redirect_uri(), 'code' => $code ] ) );
		if ( is_wp_error( $tok ) ) { $back( [ 'err' => ir_dm_connect_err( $tok ) ] ); }
		$list = ir_dm_get( "https://graph.facebook.com/$ver/me/assigned_instagram_accounts?fields=id,username,name,profile_picture_url&access_token=" . rawurlencode( $tok['access_token'] ) );
		if ( is_wp_error( $list ) || empty( $list['data'][0]['id'] ) ) { $back( [ 'err' => 'No Instagram account was shared. Make sure you pick your Instagram account in the Meta window.' ] ); }
		$accts = [];
		foreach ( $list['data'] as $me ) {
			if ( empty( $me['id'] ) ) { continue; }
			$accts[] = [ 'id' => (string) $me['id'], 'username' => (string) ( $me['username'] ?? $me['id'] ), 'name' => (string) ( $me['name'] ?? '' ), 'pic' => (string) ( $me['profile_picture_url'] ?? '' ),
				'login' => 'unified', 'token' => ir_dm_enc( $tok['access_token'] ), 'expires' => ! empty( $tok['expires_in'] ) ? time() + (int) $tok['expires_in'] : 0 ];
		}
	} else {
		$short = ir_dm_get( 'https://api.instagram.com/oauth/access_token', [ 'method' => 'POST', 'body' => [ 'client_id' => ir_dm_opt( 'ig_app_id' ), 'client_secret' => ir_dm_secret( 'ig_secret' ),
			'grant_type' => 'authorization_code', 'redirect_uri' => ir_dm_redirect_uri(), 'code' => $code ] ] );
		if ( is_wp_error( $short ) ) { $back( [ 'err' => ir_dm_connect_err( $short ) ] ); }
		$long = ir_dm_get( 'https://graph.instagram.com/access_token?grant_type=ig_exchange_token&client_secret=' . rawurlencode( ir_dm_secret( 'ig_secret' ) ) . '&access_token=' . rawurlencode( $short['access_token'] ) );
		if ( is_wp_error( $long ) ) { $back( [ 'err' => ir_dm_connect_err( $long ) ] ); }
		// user_id (not id) is the professional account id that webhooks use.
		$me = ir_dm_get( "https://graph.instagram.com/$ver/me?fields=user_id,username,name,profile_picture_url&access_token=" . rawurlencode( $long['access_token'] ) );
		if ( is_wp_error( $me ) ) { $back( [ 'err' => ir_dm_connect_err( $me ) ] ); }
		$acct = [ 'id' => (string) ( $me['user_id'] ?? $short['user_id'] ), 'username' => (string) $me['username'], 'name' => (string) ( $me['name'] ?? '' ), 'pic' => (string) ( $me['profile_picture_url'] ?? '' ),
			'login' => 'instagram', 'token' => ir_dm_enc( $long['access_token'] ), 'expires' => time() + (int) ( $long['expires_in'] ?? 5184000 ), 'refreshed' => time() ];
		$accts = [ $acct ];
	}
	$n = 0; $taken = [];
	foreach ( $accts as $acct ) {
		$other = ir_dm_owner( $acct['id'] );
		if ( $other && $other !== $uid ) { $taken[] = '@' . $acct['username']; continue; }
		$acct['status'] = 'active';
		$sub = ir_dm_api( $acct, 'POST', $acct['id'] . '/subscribed_apps?subscribed_fields=comments,messages' );
		$acct['subscribed'] = ! is_wp_error( $sub ) && ! empty( $sub['success'] );
		ir_dm_save_acct( $uid, $acct );
		delete_transient( 'ir_dm_media_' . $uid . '_' . $acct['id'] );
		$n++;
	}
	delete_user_meta( $uid, 'ir_dm_pause' );
	if ( ! $n ) { $back( [ 'err' => implode( ', ', $taken ) . ' is already connected to another user.' ] ); }
	$back( [ 'connected' => $n ] );
}

function ir_dm_connect_err( WP_Error $e ) {
	$m = $e->get_error_message();
	if ( stripos( $m, 'Insufficient developer role' ) !== false || stripos( $m, 'Unsupported request' ) !== false ) {
		return 'Instagram says this account is not allowed yet. In your Meta app add it under App roles > Instagram testers, accept the invite in the Instagram app (Settings > Apps and websites > Tester invites), then try again.';
	}
	if ( stripos( $m, 'redirect_uri' ) !== false ) { return 'The redirect URL in your Meta app does not match. Copy it again from the Setup page.'; }
	return 'Instagram could not connect your account. Make sure it is a Business or Creator account and try again.';
}

/* =========================================================================
 * Member AJAX API (all scoped to the logged-in member)
 * ========================================================================= */

function ir_dm_member_ok() {
	check_ajax_referer( 'ir_dm', 'n' );
	if ( ! ir_dm_can() ) { wp_send_json( [ 'ok' => false, 'err' => 'Please sign in again.' ] ); }
	return get_current_user_id();
}
function ir_dm_own_auto( $uid, $id ) {
	$p = get_post( (int) $id );
	return $p && $p->post_type === 'ir_dm_auto' && (int) $p->post_author === (int) $uid ? $p : null;
}

function ir_dm_stats( $uid ) {
	global $wpdb;
	$l = ir_dm_t( 'log' ); $c = ir_dm_t( 'click' );
	$out = [];
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT auto_id, SUM(status='sent') sent, COUNT(*) total FROM $l WHERE user_id=%d GROUP BY auto_id", $uid ) ) as $r ) { $out[ $r->auto_id ] = [ 'sent' => (int) $r->sent, 'total' => (int) $r->total, 'clicks' => 0 ]; }
	foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT auto_id, COUNT(DISTINCT IF(rhash<>'',rhash,iphash)) n FROM $c WHERE user_id=%d GROUP BY auto_id", $uid ) ) as $r ) { $out[ $r->auto_id ]['clicks'] = (int) $r->n; }
	return $out;
}

add_action( 'wp_ajax_ir_dm_state', function () {
	$uid = ir_dm_member_ok();
	global $wpdb;
	$stats = ir_dm_stats( $uid );
	$autos = array_map( function ( $a ) use ( $stats ) {
		$s = $stats[ $a['id'] ] ?? [ 'sent' => 0, 'total' => 0, 'clicks' => 0 ];
		return [ 'id' => $a['id'], 'name' => $a['name'], 'on' => $a['on'], 'cfg' => $a['cfg'], 'sent' => $s['sent'], 'total' => $s['total'], 'clicks' => $s['clicks'] ?? 0 ];
	}, ir_dm_autos( $uid, null, false ) );
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT l.*, p.post_title name FROM ' . ir_dm_t( 'log' ) . " l LEFT JOIN {$wpdb->posts} p ON p.ID=l.auto_id WHERE l.user_id=%d ORDER BY l.id DESC LIMIT 40", $uid ) );
	$act = array_map( function ( $r ) {
		$res = $r->status === 'sent' ? 'Sent' : ( $r->status === 'skipped' ? 'Not sent: ' . ir_dm_reason_text( $r->reason ) : ( $r->status === 'failed' ? 'Not sent: ' . ir_dm_friendly( $r->error ) : 'Sending…' ) );
		return [ 'when' => strtotime( $r->created_at . ' UTC' ), 'who' => $r->sender_name ? '@' . $r->sender_name : 'Someone', 'trig' => $r->trig, 'said' => (string) $r->incoming,
			'auto' => (string) $r->name, 'ok' => $r->status === 'sent', 'res' => $res, 'dm' => (string) $r->dm_text, 'pub' => $r->public_status === 'sent' ? (string) $r->public_text : '' ];
	}, $rows );
	$tot = [ 'sent' => array_sum( array_column( $autos, 'sent' ) ), 'clicks' => array_sum( array_column( $autos, 'clicks' ) ) ];
	$tot['leads'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . ir_dm_t( 'lead' ) . ' WHERE user_id=%d', $uid ) );
	$per = [];
	foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT ig_id, SUM(status=\'sent\') n FROM ' . ir_dm_t( 'log' ) . ' WHERE user_id=%d GROUP BY ig_id', $uid ) ) as $x ) { $per[ $x->ig_id ] = (int) $x->n; }
	$leads = $wpdb->get_results( $wpdb->prepare( 'SELECT email, username, auto_id, created_at FROM ' . ir_dm_t( 'lead' ) . ' WHERE user_id=%d ORDER BY id DESC LIMIT 100', $uid ) );
	$u = wp_get_current_user();
	$accts = array_values( array_map( function ( $a ) use ( $per, $autos ) { $p = ir_dm_acct_public( $a ); $p['sent'] = $per[ $p['id'] ] ?? 0; $first = (string) ( ir_dm_acct( $uid )['id'] ?? '' ); $p['autos'] = count( array_filter( $autos, function ( $x ) use ( $p, $first ) { $ac = (string) ( $x['cfg']['acct'] ?? '' ); return $ac === $p['id'] || ( $ac === '' && $p['id'] === $first ); } ) ); return $p; }, ir_dm_accts( $uid ) ) );
	wp_send_json( [ 'ok' => true, 'ready' => ir_dm_ready(), 'accts' => $accts, 'autos' => $autos, 'activity' => $act, 'tot' => $tot,
		'leads' => array_map( function ( $l ) { return [ 'email' => $l->email, 'who' => $l->username ? '@' . $l->username : '', 'auto' => get_the_title( (int) $l->auto_id ), 'when' => strtotime( $l->created_at . ' UTC' ) ]; }, $leads ),
		'hook' => (string) get_user_meta( $uid, 'ir_dm_hook', true ), 'instant' => (int) get_option( 'ir_dm_last_real_hook' ) > time() - 7 * DAY_IN_SECONDS,
		'me' => [ 'name' => $u->first_name ?: $u->display_name, 'email' => $u->user_email, 'admin' => user_can( $uid, 'manage_options' ) ] ] );
} );

add_action( 'wp_ajax_ir_dm_media', function () {
	$uid = ir_dm_member_ok();
	$ig = preg_replace( '/[^0-9]/', '', (string) ( $_POST['ig'] ?? '' ) );
	$a = ir_dm_acct( $uid, $ig );
	if ( ! $a ) { wp_send_json( [ 'ok' => true, 'media' => [] ] ); }
	$ck = 'ir_dm_media_' . $uid . '_' . $a['id'];
	$cached = get_transient( $ck );
	if ( is_array( $cached ) && empty( $_POST['fresh'] ) ) { wp_send_json( [ 'ok' => true, 'media' => $cached ] ); }
	$r = ir_dm_api( $a, 'GET', $a['id'] . '/media?fields=id,caption,media_type,media_url,thumbnail_url,permalink,timestamp&limit=30' );
	if ( is_wp_error( $r ) ) { wp_send_json( [ 'ok' => false, 'err' => ir_dm_friendly( $r->get_error_message() ) ] ); }
	$media = array_map( function ( $m ) {
		return [ 'id' => (string) $m['id'], 'img' => (string) ( $m['thumbnail_url'] ?? ( $m['media_url'] ?? '' ) ), 'cap' => mb_substr( (string) ( $m['caption'] ?? '' ), 0, 80 ), 'link' => (string) ( $m['permalink'] ?? '' ), 'video' => ( $m['media_type'] ?? '' ) === 'VIDEO' ];
	}, (array) ( $r['data'] ?? [] ) );
	set_transient( $ck, $media, 10 * MINUTE_IN_SECONDS );
	wp_send_json( [ 'ok' => true, 'media' => $media ] );
} );

function ir_dm_clean_cfg( $in ) {
	$s = function ( $v, $max ) { return mb_substr( trim( sanitize_textarea_field( (string) $v ) ), 0, $max ); };
	$kws = array_values( array_unique( array_filter( array_map( function ( $k ) use ( $s ) { return $s( $k, 40 ); }, (array) ( $in['keywords'] ?? [] ) ) ) ) );
	$link = trim( (string) ( $in['link'] ?? '' ) );
	if ( $link !== '' && ! preg_match( '#^https?://#i', $link ) ) { $link = 'https://' . $link; }
	$link = $link !== '' ? esc_url_raw( $link, [ 'http', 'https' ] ) : '';
	$c = [
		't'        => [ 'comments' => ! empty( $in['t']['comments'] ), 'dms' => ! empty( $in['t']['dms'] ), 'story' => ! empty( $in['t']['story'] ) ],
		'any_post' => ! empty( $in['any_post'] ),
		'post'     => preg_replace( '/[^0-9A-Za-z_]/', '', (string) ( $in['post'] ?? '' ) ),
		'post_img' => esc_url_raw( (string) ( $in['post_img'] ?? '' ) ),
		'keywords' => array_slice( $kws, 0, 20 ),
		'any_word' => ! empty( $in['any_word'] ),
		'message'  => $s( $in['message'] ?? '', 1000 ),
		'link'     => $link,
		'button'   => $link ? $s( $in['button'] ?? '', 20 ) : '',
		'public_on'=> ! empty( $in['public_on'] ),
		'acct'     => preg_replace( '/[^0-9]/', '', (string) ( $in['acct'] ?? '' ) ),
		'follow'   => ! empty( $in['follow'] ),
		'follow_msg' => $s( $in['follow_msg'] ?? '', 640 ),
		'follow_btn' => $s( $in['follow_btn'] ?? '', 20 ),
		'ask_email' => ! empty( $in['ask_email'] ),
		'email_ask' => $s( $in['email_ask'] ?? '', 640 ),
		'email_thanks' => $s( $in['email_thanks'] ?? '', 1000 ),
		'public'   => array_slice( array_values( array_filter( array_map( function ( $p ) use ( $s ) { return $s( $p, 300 ); }, (array) ( $in['public'] ?? [] ) ) ) ), 0, 10 ),
	];
	if ( $c['any_post'] ) { $c['post'] = ''; $c['post_img'] = ''; }
	$err = '';
	if ( ! $c['t']['comments'] && ! $c['t']['dms'] && ! $c['t']['story'] ) { $err = 'Choose when this should run (comments, DMs or Story replies).'; }
	elseif ( $c['t']['comments'] && ! $c['any_post'] && ! $c['post'] ) { $err = 'Pick a post, or choose "Any post".'; }
	elseif ( ! $c['any_word'] && ! $c['keywords'] ) { $err = 'Type the word people should comment, like GUIDE.'; }
	elseif ( $c['message'] === '' ) { $err = 'Write the DM people will get.'; }
	elseif ( ! empty( $in['link'] ) && ! $c['link'] ) { $err = 'That link does not look right. It should look like https://yourwebsite.com'; }
	elseif ( $c['public_on'] && ! $c['public'] ) { $err = 'Write the public reply, or switch it off.'; }
	return [ $c, $err ];
}

add_action( 'wp_ajax_ir_dm_save', function () {
	$uid = ir_dm_member_ok();
	$in = json_decode( (string) wp_unslash( $_POST['cfg'] ?? '' ), true );
	if ( ! is_array( $in ) ) { wp_send_json( [ 'ok' => false, 'err' => 'Something went wrong. Please try again.' ] ); }
	list( $cfg, $err ) = ir_dm_clean_cfg( $in );
	if ( $err ) { wp_send_json( [ 'ok' => false, 'err' => $err ] ); }
	$mine = ir_dm_accts( $uid );
	if ( $mine && ! isset( $mine[ $cfg['acct'] ] ) ) { $cfg['acct'] = (string) reset( $mine )['id']; }
	$name = mb_substr( trim( sanitize_text_field( (string) ( $in['name'] ?? '' ) ) ), 0, 80 );
	if ( $name === '' ) { $name = $cfg['any_word'] ? 'Reply to everyone' : strtoupper( $cfg['keywords'][0] ) . ' automation'; }
	$id = (int) ( $in['id'] ?? 0 );
	if ( $id ) {
		if ( ! ir_dm_own_auto( $uid, $id ) ) { wp_send_json( [ 'ok' => false, 'err' => 'Automation not found.' ] ); }
		wp_update_post( [ 'ID' => $id, 'post_title' => $name ] );
	} else {
		if ( count( ir_dm_autos( $uid, null, false ) ) >= 50 ) { wp_send_json( [ 'ok' => false, 'err' => 'You have reached 50 automations. Delete one first.' ] ); }
		$id = wp_insert_post( [ 'post_type' => 'ir_dm_auto', 'post_status' => 'publish', 'post_title' => $name, 'post_author' => $uid ] );
		if ( ! $id || is_wp_error( $id ) ) { wp_send_json( [ 'ok' => false, 'err' => 'Could not save. Please try again.' ] ); }
	}
	update_post_meta( $id, '_ir_dm', $cfg );
	if ( $cfg['link'] ) { ir_dm_slug( $id ); }
	wp_send_json( [ 'ok' => true, 'id' => $id ] );
} );

add_action( 'wp_ajax_ir_dm_hook', function () {
	$uid = ir_dm_member_ok();
	$u = esc_url_raw( trim( (string) wp_unslash( $_POST['url'] ?? '' ) ), [ 'https' ] );
	if ( ! empty( $_POST['url'] ) && ! $u ) { wp_send_json( [ 'ok' => false, 'err' => 'Paste a link that starts with https://' ] ); }
	update_user_meta( $uid, 'ir_dm_hook', $u );
	if ( $u ) { wp_remote_post( $u, [ 'timeout' => 8, 'headers' => [ 'Content-Type' => 'application/json' ], 'body' => wp_json_encode( [ 'email' => 'test@example.com', 'instagram' => 'test_user', 'automation' => 'Test', 'source' => IR_DM_NAME, 'test' => true ] ) ] ); }
	wp_send_json( [ 'ok' => true ] );
} );
add_action( 'wp_ajax_ir_dm_leads_csv', function () {
	check_ajax_referer( 'ir_dm', 'n' );
	if ( ! ir_dm_can() ) { wp_die( 'No access' ); }
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT email, username, auto_id, created_at FROM ' . ir_dm_t( 'lead' ) . ' WHERE user_id=%d ORDER BY id DESC', get_current_user_id() ) );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="reubx-dm-leads-' . gmdate( 'Y-m-d' ) . '.csv"' );
	$f = fopen( 'php://output', 'w' );
	fputcsv( $f, [ 'email', 'instagram', 'automation', 'date' ] );
	foreach ( $rows as $l ) { fputcsv( $f, [ $l->email, $l->username, get_the_title( (int) $l->auto_id ), $l->created_at ] ); }
	exit;
} );

add_action( 'wp_ajax_ir_dm_toggle', function () {
	$uid = ir_dm_member_ok();
	$p = ir_dm_own_auto( $uid, $_POST['id'] ?? 0 );
	if ( ! $p ) { wp_send_json( [ 'ok' => false, 'err' => 'Automation not found.' ] ); }
	wp_update_post( [ 'ID' => $p->ID, 'post_status' => ! empty( $_POST['on'] ) ? 'publish' : 'draft' ] );
	wp_send_json( [ 'ok' => true ] );
} );

add_action( 'wp_ajax_ir_dm_delete', function () {
	$uid = ir_dm_member_ok();
	$p = ir_dm_own_auto( $uid, $_POST['id'] ?? 0 );
	if ( ! $p ) { wp_send_json( [ 'ok' => false, 'err' => 'Automation not found.' ] ); }
	ir_dm_delete_auto( $p->ID );
	wp_send_json( [ 'ok' => true ] );
} );
function ir_dm_delete_auto( $id ) {
	global $wpdb;
	$wpdb->delete( ir_dm_t( 'log' ), [ 'auto_id' => (int) $id ] );
	$wpdb->delete( ir_dm_t( 'click' ), [ 'auto_id' => (int) $id ] );
	wp_delete_post( (int) $id, true );
}

/** "Try it": shows exactly what someone would get, without sending anything. */
add_action( 'wp_ajax_ir_dm_test', function () {
	$uid = ir_dm_member_ok();
	$in = json_decode( (string) wp_unslash( $_POST['cfg'] ?? '' ), true );
	list( $cfg ) = ir_dm_clean_cfg( is_array( $in ) ? $in : [] );
	$text = (string) wp_unslash( $_POST['text'] ?? '' );
	$kw = ir_dm_match( $text, $cfg );
	if ( $kw === false ) {
		wp_send_json( [ 'ok' => true, 'match' => false, 'why' => 'No DM. "' . mb_substr( $text, 0, 60 ) . '" does not contain ' . ( $cfg['keywords'] ? 'the word ' . implode( ' or ', array_map( 'strtoupper', $cfg['keywords'] ) ) : 'a trigger word' ) . '.' ] );
	}
	$acct = ir_dm_acct( $uid );
	$aid = (int) ( $in['id'] ?? 0 );
	$m = ir_dm_build( $cfg, $aid && ir_dm_own_auto( $uid, $aid ) ? $aid : 0, 'their_name', $kw, 'preview' );
	$pub = $cfg['public_on'] && $cfg['public'] ? ir_dm_render( $cfg['public'][0], 'their_name', $kw, '' ) : '';
	wp_send_json( [ 'ok' => true, 'match' => true, 'dm' => $m['text'], 'button' => $m['button']['title'] ?? '', 'pub' => $pub, 'from' => $acct ? '@' . $acct['username'] : 'you' ] );
} );

add_action( 'wp_ajax_ir_dm_disconnect', function () {
	$uid = ir_dm_member_ok();
	$ig = preg_replace( '/[^0-9]/', '', (string) ( $_POST['ig'] ?? '' ) );
	if ( $ig === '' || ! ir_dm_acct( $uid, $ig ) ) { wp_send_json( [ 'ok' => false, 'err' => 'Account not found.' ] ); }
	ir_dm_remove_acct( $uid, $ig );
	delete_transient( 'ir_dm_media_' . $uid . '_' . $ig );
	wp_send_json( [ 'ok' => true ] );
} );

add_action( 'delete_user', function ( $uid ) {
	foreach ( get_posts( [ 'post_type' => 'ir_dm_auto', 'author' => (int) $uid, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] ) as $id ) { ir_dm_delete_auto( $id ); }
} );

/* =========================================================================
 * Dashboard tile + pages
 * ========================================================================= */

function ir_dm_icon( $size = 26 ) {
	return '<svg width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8a2.5 2.5 0 0 1-2.5 2.5H10l-4.5 4v-4A1.5 1.5 0 0 1 4 14.5z"/><path d="M12.8 6.5 10 10.5h3.5l-2.6 4"/></svg>';
}

/* App routes: /reubxdm/ (app), /reubxdm/connect/, /dm/callback/ (OAuth, registered in Meta). /dm/ and /replixa/ forward to /reubxdm/. */
add_action( 'template_redirect', function () {
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( ! preg_match( '#^(dm|replixa|reubxdm)(?:/(connect|callback))?$#', $path, $m ) ) { return; }
	nocache_headers();
	do_action( 'litespeed_control_set_nocache', 'reubxdm' );
	status_header( 200 );
	header( 'X-Robots-Tag: noindex, nofollow', true );
	$sub = $m[2] ?? '';
	if ( $m[1] !== 'reubxdm' && $sub === '' ) { wp_safe_redirect( ir_dm_url() ); exit; }
	if ( ! is_user_logged_in() ) {
		if ( $sub !== '' ) { wp_safe_redirect( ir_dm_url() ); exit; }
		ir_dm_auth();
		exit;
	}
	if ( ! ir_dm_can() ) { status_header( 200 ); ir_dm_shell( 'No access', '<div class="rx-auth"><div class="rx-card rx-pad"><h1 class="rx-h1">Not open yet</h1><p class="rx-muted">Your account doesn\'t have access to ' . esc_html( IR_DM_NAME ) . ' yet.</p><a class="rx-btn rx-dark" href="' . esc_url( wp_logout_url( ir_dm_url() ) ) . '">Sign out</a></div></div>', '' ); exit; }
	status_header( 200 );
	if ( $sub === 'connect' ) { ir_dm_page_connect(); }
	elseif ( $sub === 'callback' ) { ir_dm_page_callback(); }
	else { ir_dm_page(); }
	exit;
}, 0 );

add_action( 'admin_bar_menu', function ( $bar ) {
	if ( ir_dm_can() ) { $bar->add_node( [ 'id' => 'ir-dm', 'title' => IR_DM_NAME, 'href' => ir_dm_url() ] ); }
}, 80 );

/** Sign in / create account, styled like the app. */
function ir_dm_auth() {
	$mode = ( $_GET['m'] ?? '' ) === 'signup' ? 'signup' : 'login';
	$err = '';
	$open = ir_dm_opt( 'access', 'everyone' ) === 'everyone';
	if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST' && isset( $_POST['rx_act'] ) ) {
		$mode = $_POST['rx_act'] === 'signup' ? 'signup' : 'login';
		$ipk = 'ir_dm_auth_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$tries = (int) get_transient( $ipk );
		if ( ! wp_verify_nonce( (string) ( $_POST['_n'] ?? '' ), 'ir_dm_auth' ) ) { $err = 'The page expired. Please try again.'; }
		elseif ( $tries > 20 ) { $err = 'Too many attempts. Please wait 15 minutes.'; }
		else {
			set_transient( $ipk, $tries + 1, 15 * MINUTE_IN_SECONDS );
			$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
			$pass = (string) wp_unslash( $_POST['pass'] ?? '' );
			if ( $mode === 'signup' ) {
				$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
				if ( ! $open ) { $err = 'Sign-ups are closed right now.'; }
				elseif ( ! is_email( $email ) ) { $err = 'Enter a valid email address.'; }
				elseif ( strlen( $pass ) < 8 ) { $err = 'Use a password of at least 8 characters.'; }
				elseif ( email_exists( $email ) ) { $err = 'You already have an account. Sign in instead.'; $mode = 'login'; }
				else {
					$base = sanitize_user( strtolower( strstr( $email, '@', true ) ), true ) ?: 'user';
					$login = $base; $n = 1;
					while ( username_exists( $login ) ) { $login = $base . ( ++$n ); }
					$uid = wp_insert_user( [ 'user_login' => $login, 'user_email' => $email, 'user_pass' => $pass, 'first_name' => $name, 'display_name' => $name ?: $login, 'role' => 'subscriber' ] );
					if ( is_wp_error( $uid ) ) { $err = 'Could not create your account. Please try again.'; }
					else { wp_set_current_user( $uid ); wp_set_auth_cookie( $uid, true ); wp_safe_redirect( ir_dm_url() ); exit; }
				}
			} else {
				$u = wp_signon( [ 'user_login' => $email ?: sanitize_user( wp_unslash( $_POST['email'] ?? '' ) ), 'user_password' => $pass, 'remember' => true ], is_ssl() );
				if ( is_wp_error( $u ) ) { $err = 'Wrong email or password.'; }
				else { wp_safe_redirect( ir_dm_url() ); exit; }
			}
		}
	}
	$signup = $mode === 'signup';
	ob_start(); ?>
<div class="rx-auth-wrap"><div class="rx-auth-side"><div style="display:flex;align-items:center;gap:12px;font-size:18px;position:relative;z-index:1"><span class="rx-logo" style="background:rgba(255,255,255,.18);box-shadow:none"><?php echo ir_dm_logo(); // phpcs:ignore ?></span><b><?php echo esc_html( IR_DM_NAME ); ?></b></div>
 <div style="position:relative;z-index:1"><h2>Your AI assistant that turns <span>comments</span> into clients.</h2>
 <ul><li><i>💬</i>Comment a word, get a DM, automatically</li><li><i>🔗</i>Tracked links show who clicked</li><li><i>📱</i>Several Instagram accounts, one dashboard</li><li><i>🛡️</i>Official Meta API, follows Instagram's rules</li></ul></div>
 <small style="position:relative;z-index:1;color:rgba(255,255,255,.7)">by Igho Reuben</small></div>
<div class="rx-auth">
 <div class="rx-auth-top"><span class="rx-logo"><?php echo ir_dm_logo(); // phpcs:ignore ?></span><b class="rx-brand"><?php echo esc_html( IR_DM_NAME ); ?><small>AI DM automation</small></b><button type="button" class="rx-ib" onclick="rxTheme()" aria-label="Light or dark mode"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg></button></div>
 <h1 class="rx-hero"><?php echo $signup ? 'Let AI turn <span>comments</span><br>into clients.' : 'Welcome <span>back.</span>'; ?></h1>
 <p class="rx-muted">Someone comments your word, your AI assistant sends them a DM. 24/7, on autopilot.</p>
 <form method="post" class="rx-card rx-pad rx-form">
  <input type="hidden" name="_n" value="<?php echo esc_attr( wp_create_nonce( 'ir_dm_auth' ) ); ?>">
  <input type="hidden" name="rx_act" value="<?php echo $signup ? 'signup' : 'login'; ?>">
  <?php if ( $err ) : ?><div class="rx-alert bad"><?php echo esc_html( $err ); ?></div><?php endif; ?>
  <?php if ( $signup ) : ?><label>Your name<input name="name" autocomplete="name" placeholder="e.g. Amaka" value="<?php echo esc_attr( wp_unslash( $_POST['name'] ?? '' ) ); ?>"></label><?php endif; ?>
  <label>Email<input name="email" type="email" required autocomplete="email" placeholder="you@email.com" value="<?php echo esc_attr( wp_unslash( $_POST['email'] ?? '' ) ); ?>"></label>
  <label>Password<input name="pass" type="password" required minlength="8" autocomplete="<?php echo $signup ? 'new-password' : 'current-password'; ?>" placeholder="At least 8 characters"></label>
  <button class="rx-btn rx-lime rx-wide"><?php echo $signup ? 'Create my free account' : 'Sign in'; ?></button>
  <?php if ( ! $signup ) : ?><a class="rx-link" href="<?php echo esc_url( wp_lostpassword_url( ir_dm_url() ) ); ?>">Forgot password?</a><?php endif; ?>
 </form>
 <?php if ( $open ) : ?><p class="rx-center rx-muted"><?php echo $signup ? 'Already have an account?' : 'New here?'; ?> <a class="rx-link" href="<?php echo esc_url( add_query_arg( 'm', $signup ? 'login' : 'signup', ir_dm_url() ) ); ?>"><?php echo $signup ? 'Sign in' : 'Create a free account'; ?></a></p><?php endif; ?>
</div></div>
<?php
	ir_dm_shell( $signup ? 'Create account' : 'Sign in', ob_get_clean(), '' );
}

function ir_dm_logo() {
	return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#141414" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v7a2.5 2.5 0 0 1-2.5 2.5H11l-4.5 4v-4A2.5 2.5 0 0 1 4 13.5z"/><path d="M13 7.5 10.5 11h3.5L11.5 14"/></svg>';
}

function ir_dm_shell( $title, $body, $script ) {
	?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#F8FAFF">
<title><?php echo esc_html( $title . ' · ' . IR_DM_NAME ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script>(function(){var t=null;try{t=localStorage.getItem("ir-theme")}catch(e){}if(t!=="dark"&&t!=="light"){t=matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light"}document.documentElement.setAttribute("data-theme",t);window.rxTheme=function(){var n=document.documentElement.getAttribute("data-theme")==="dark"?"light":"dark";document.documentElement.setAttribute("data-theme",n);try{localStorage.setItem("ir-theme",n)}catch(e){}};})();</script>
<style><?php echo ir_dm_css(); // phpcs:ignore ?></style></head>
<body><?php echo $body; // phpcs:ignore ?><?php echo $script; // phpcs:ignore ?></body></html>
<?php
}

function ir_dm_page() {
	$cfg = [ 'aj' => admin_url( 'admin-ajax.php' ), 'n' => wp_create_nonce( 'ir_dm' ), 'connect' => ir_dm_url( 'connect' ), 'setup' => admin_url( 'admin.php?page=ir-dm' ),
		'logout' => wp_logout_url( ir_dm_url() ), 'name' => IR_DM_NAME,
		'msg' => isset( $_GET['connected'] ) ? 'Instagram connected. Now create an automation.' : '', 'err' => sanitize_text_field( wp_unslash( $_GET['err'] ?? '' ) ) ];
	ir_dm_shell( 'Home', '<div class="rx-app" id="rx"><div class="rx-load"><i></i></div></div>',
		'<script>window.IRDM=' . wp_json_encode( $cfg ) . ';</script><script>' . ir_dm_js() . '</script>' );
}

function ir_dm_css() {
	return <<<'CSS'
:root{--bg:#F8FAFF;--card:#fff;--ink:#0A1633;--muted:#5B6B82;--line:#E5EBF6;--soft:#F1F5FF;--acc:#1660E8;--acc2:#0F4ACB;--accs:#EEF3FF;--or:#FF7A1A;--navy:#0B1A33;--ok:#16A34A;--red:#E5484D;--navbg:rgba(255,255,255,.78);--sh:rgba(11,26,51,.18);--r:26px;color-scheme:light}
:root[data-theme="dark"]{--bg:#071233;--card:#0F1D45;--ink:#EEF3FF;--muted:#A9B6D0;--line:#22346A;--soft:#0B1A3A;--accs:#16285A;--navbg:rgba(15,29,69,.82);--sh:rgba(0,0,0,.5);color-scheme:dark}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}html,body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 Inter,system-ui,sans-serif;-webkit-font-smoothing:antialiased;transition:background .25s,color .25s}
h1,h2,h3,b,.rx-hero,.rx-btn,.rx-pill,.rx-nav{font-family:"Plus Jakarta Sans",Inter,sans-serif}
a{color:inherit}button{font:inherit;color:inherit;cursor:pointer}input,textarea,select{font:inherit;color:var(--ink)}
.rx-app{max-width:480px;margin:0 auto;padding:18px 18px 120px;min-height:100vh}
.rx-load{display:grid;place-items:center;height:70vh}.rx-load i{width:34px;height:34px;border-radius:50%;border:3px solid var(--line);border-top-color:var(--acc);animation:rxs 1s linear infinite}@keyframes rxs{to{transform:rotate(360deg)}}
.rx-top{display:flex;align-items:center;gap:10px;margin:4px 0 22px}.rx-hello{display:flex;align-items:center;gap:8px;background:var(--card);border:1px solid var(--line);border-radius:999px;padding:5px 14px 5px 5px;font-weight:600;font-size:14px;margin-right:auto}
.rx-av img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}.rx-av{position:relative;overflow:hidden;width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#1660E8,#FF7A1A);display:grid;place-items:center;font-weight:800;color:#fff;flex:none}
.rx-ib{width:48px;height:48px;border-radius:50%;border:1px solid var(--line);background:var(--card);display:grid;place-items:center;flex:none;color:var(--ink);text-decoration:none}.rx-ib.lime{background:var(--acc);border-color:var(--acc);color:#fff;box-shadow:0 10px 22px -10px var(--acc)}
.rx-ib svg,.rx-ic svg{width:20px;height:20px}
.rx-hero{font-size:34px;line-height:1.08;letter-spacing:-1.2px;font-weight:800;margin:0 0 18px}.rx-hero span{color:var(--acc)}.rx-hero em{font-style:normal;color:var(--muted);font-weight:600}
.rx-chips{display:flex;gap:10px;margin:0 0 18px;overflow-x:auto;scrollbar-width:none}.rx-chips::-webkit-scrollbar{display:none}
.rx-pill{border:1.5px solid var(--acc);background:var(--card);color:var(--acc);border-radius:999px;padding:12px 18px;font-weight:700;white-space:nowrap}
.rx-card{background:var(--card);border-radius:var(--r);border:1px solid var(--line)}.rx-pad{padding:18px}
.rx-promo{position:relative;overflow:hidden;background:linear-gradient(140deg,#2A74F2,#1660E8 45%,#0F3FB0);color:#fff;border-radius:var(--r);padding:18px;margin:0 0 16px;box-shadow:0 18px 34px -18px rgba(22,96,232,.8)}
.rx-promo:before{content:"";position:absolute;inset:0;background:radial-gradient(120% 80% at 100% 0%,rgba(255,255,255,.28),transparent 55%),radial-gradient(90% 70% at 0% 100%,rgba(125,170,255,.35),transparent 60%);pointer-events:none}
.rx-promo:after{content:"";position:absolute;right:18px;top:16px;width:92px;height:92px;border-radius:28px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.28);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);transform:rotate(14deg);pointer-events:none}
.rx-promo .row{position:relative;z-index:1;display:flex;gap:14px;align-items:flex-start;margin:0 0 14px}.rx-promo h3{margin:0;font-size:18px;letter-spacing:-.3px}.rx-promo p{margin:3px 0 0;font-size:13.5px;color:rgba(255,255,255,.82)}.rx-promo .rx-btn{position:relative;z-index:1}
.rx-promo.warn{background:linear-gradient(140deg,#FF7A1A,#E2560B)}
.rx-crown{width:50px;height:50px;border-radius:16px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.3);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);display:grid;place-items:center;flex:none}.rx-crown svg{width:24px;height:24px}
.rx-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:999px;padding:14px 20px;font-weight:700;text-decoration:none;background:var(--card);color:var(--ink)}
.rx-promo .rx-btn{background:#fff;color:#0A1633}
.rx-wide{width:100%}.rx-dark{background:var(--navy);color:#fff}:root[data-theme="dark"] .rx-dark{background:var(--acc)}.rx-lime{background:var(--acc);color:#fff}.rx-btn[disabled]{opacity:.6}
.rx-tiles{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:0 0 22px}.rx-tile{background:var(--card);border:1px solid var(--line);border-radius:22px;padding:16px 8px;display:flex;flex-direction:column;align-items:center;gap:10px;font-weight:600;font-size:13.5px;text-align:center}
.rx-tile.on{background:var(--accs);border-color:var(--accs)}.rx-ic{width:44px;height:44px;border-radius:50%;display:grid;place-items:center;background:rgba(124,58,237,.13);color:#7C3AED}.rx-ic.g{background:rgba(22,96,232,.13);color:var(--acc)}.rx-ic.b{background:rgba(143,184,154,.25);color:#3F7A52}.rx-ic.o{background:rgba(255,122,26,.15);color:var(--or)}.rx-ic.k{background:var(--soft);color:var(--muted)}
:root[data-theme="dark"] .rx-ic{color:#C4B5FD}:root[data-theme="dark"] .rx-ic.g{color:#7EA8FF}:root[data-theme="dark"] .rx-ic.b{color:#A7D3B4}
.rx-sec{display:flex;justify-content:space-between;align-items:center;margin:4px 0 10px}.rx-sec h2{font-size:18px;margin:0;letter-spacing:-.3px;font-weight:700}.rx-sec button{border:0;background:none;color:var(--acc);font-size:13px;font-weight:700}
.rx-item{display:flex;gap:12px;align-items:center;background:var(--card);border:1px solid var(--line);border-radius:20px;padding:12px 14px;margin:0 0 10px}.rx-item .t{flex:1;min-width:0}.rx-item b{display:block;font-weight:600;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.rx-item small{display:block;color:var(--muted);font-size:12.5px;line-height:1.35}
.rx-go{width:34px;height:34px;border-radius:50%;border:1px solid var(--line);display:grid;place-items:center;flex:none;background:var(--card)}
.rx-nav{position:fixed;left:50%;bottom:calc(16px + env(safe-area-inset-bottom));transform:translateX(-50%);display:flex;gap:6px;padding:6px;background:var(--navbg);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border:1px solid var(--line);border-radius:999px;box-shadow:0 18px 40px -20px var(--sh);z-index:9}
.rx-nav button{height:52px;min-width:52px;border-radius:999px;border:1px solid var(--line);background:var(--card);display:flex;align-items:center;justify-content:center;gap:8px;padding:0 15px;font-weight:700}
.rx-nav button svg{width:21px;height:21px}.rx-nav button.on{background:var(--navy);color:#fff;border-color:var(--navy);padding:0 20px 0 7px}:root[data-theme="dark"] .rx-nav button.on{background:var(--acc);border-color:var(--acc)}.rx-nav button.on i{width:38px;height:38px;border-radius:50%;background:#fff;display:grid;place-items:center;color:#0A1633}.rx-nav button span{display:none}.rx-nav button.on span{display:inline}
.rx-head{display:flex;align-items:center;justify-content:space-between;margin:4px 0 18px}.rx-head h1{font-size:19px;margin:0;font-weight:700}
.rx-prof{display:grid;grid-template-columns:1.15fr 1fr;gap:10px;margin:0 0 12px}.rx-prof .me{padding:16px}.rx-prof .me .rx-av{width:78px;height:78px;font-size:28px;margin-bottom:10px}
.rx-prof h2{font-size:19px;margin:0;letter-spacing:-.3px}.rx-badge{display:inline-flex;gap:5px;align-items:center;background:var(--accs);color:var(--acc);border-radius:999px;padding:3px 9px;font-size:11.5px;font-weight:700;margin:5px 0}:root[data-theme="dark"] .rx-badge{color:#9DBBFF}
.rx-mini{display:flex;flex-direction:column;gap:10px}.rx-mini .rx-card{padding:12px 14px;display:flex;gap:10px;align-items:center}.rx-mini b{font-size:19px;display:block}.rx-mini small{color:var(--muted);font-size:12px}
.rx-stats{display:grid;grid-template-columns:repeat(3,1fr);text-align:center;padding:14px 6px;margin:0 0 12px}.rx-stats>div{border-left:1px solid var(--line)}.rx-stats>div:first-child{border:0}.rx-stats .rx-ic{width:36px;height:36px;margin:0 auto 6px}.rx-stats small{color:var(--muted);font-size:12px;display:block}.rx-stats b{font-size:20px}
.rx-sw{position:relative;width:50px;height:30px;flex:none}.rx-sw input{opacity:0;width:0;height:0}.rx-sw i{position:absolute;inset:0;border-radius:99px;background:var(--line);transition:.2s}.rx-sw i:before{content:"";position:absolute;width:24px;height:24px;left:3px;top:3px;border-radius:50%;background:#fff;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.25)}.rx-sw input:checked+i{background:var(--acc)}.rx-sw input:checked+i:before{transform:translateX(20px)}
.rx-thumb{width:54px;height:54px;border-radius:16px;object-fit:cover;flex:none;background:var(--soft);display:grid;place-items:center;font-size:22px}
.rx-alert{border-radius:16px;padding:12px 14px;font-weight:600;font-size:14px;margin:0 0 14px;line-height:1.45}.rx-alert.ok{background:rgba(22,163,74,.12);color:var(--ok)}.rx-alert.bad{background:rgba(229,72,77,.12);color:var(--red)}.rx-alert.info{background:var(--card);border:1px solid var(--line)}
.rx-muted{color:var(--muted)}.rx-center{text-align:center}.rx-link{color:var(--acc);font-weight:700;text-decoration:none;background:none;border:0;padding:0}:root[data-theme="dark"] .rx-link{color:#7EA8FF}.rx-red{color:var(--red)!important}.rx-okc{color:var(--ok);font-weight:700}
.rx-step{margin:0 0 12px}.rx-step h3{display:flex;align-items:center;gap:10px;font-size:16px;margin:0 0 12px;font-weight:700}.rx-num{width:28px;height:28px;border-radius:50%;background:var(--acc);color:#fff;display:grid;place-items:center;font-size:13px;font-weight:800;flex:none}
.rx-form label,.rx-f{display:grid;gap:6px;font-weight:600;font-size:14px;margin:0 0 14px}.rx-f small{color:var(--muted);font-weight:500;font-size:12.5px}
.rx-form input,.rx-f input,.rx-f textarea,.rx-in2{width:100%;font-size:16px;padding:14px 16px;border:1.5px solid var(--line);border-radius:18px;background:var(--soft);outline:none}.rx-form input:focus,.rx-f input:focus,.rx-f textarea:focus,.rx-in2:focus{border-color:var(--acc);background:var(--card)}.rx-f textarea{resize:vertical;line-height:1.5}
.rx-chk{display:flex;gap:10px;align-items:flex-start;font-size:14.5px;margin:0 0 12px;cursor:pointer;line-height:1.4;font-weight:500}.rx-chk input{width:22px;height:22px;accent-color:#1660E8;flex:none;margin:0}
.rx-tag{display:inline-flex;align-items:center;gap:6px;border:1.5px solid var(--line);background:var(--card);border-radius:999px;padding:8px 12px;font-weight:600;font-size:13px;margin:0 6px 8px 0}.rx-tag.on{background:var(--acc);color:#fff;border-color:var(--acc)}.rx-tag img{width:22px;height:22px;border-radius:50%}
.rx-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:0 0 10px}.rx-post{position:relative;border:2.5px solid transparent;border-radius:18px;overflow:hidden;padding:0;background:var(--soft);aspect-ratio:1}.rx-post img{width:100%;height:100%;object-fit:cover;display:block}
.rx-post.on{border-color:var(--acc)}.rx-post.on:after{content:"✓";position:absolute;top:6px;right:6px;width:24px;height:24px;border-radius:50%;background:var(--acc);color:#fff;display:grid;place-items:center;font-weight:800;font-size:13px}.rx-post.any{display:grid;place-items:center;text-align:center;font-weight:700;font-size:12.5px;padding:6px}
.rx-phone{background:var(--soft);border-radius:22px;padding:14px;display:flex;flex-direction:column;gap:8px}.rx-in{align-self:flex-end;background:var(--acc);color:#fff;padding:9px 14px;border-radius:20px 20px 6px 20px;max-width:80%}
.rx-out{align-self:flex-start;background:var(--card);border:1px solid var(--line);padding:10px 14px;border-radius:20px 20px 20px 6px;max-width:88%;white-space:pre-wrap;word-break:break-word;line-height:1.5}.rx-bt{margin-top:8px;padding-top:8px;border-top:1px solid var(--line);text-align:center;font-weight:700;color:var(--acc)}
.rx-tpls{display:grid;grid-template-columns:1fr 1fr;gap:10px}.rx-tpl{border:1px solid var(--line);background:var(--card);border-radius:22px;padding:16px;text-align:left}.rx-tpl:hover{border-color:var(--acc)}.rx-tpl .e{font-size:26px;display:block;margin-bottom:6px}.rx-tpl b{display:block;font-weight:700}.rx-tpl small{color:var(--muted);font-size:12.5px;line-height:1.35}
.rx-act{padding:12px 0;border-top:1px solid var(--line)}.rx-act:first-child{border:0;padding-top:0}.rx-act .r{font-weight:700;font-size:13.5px;margin-top:4px}.rx-act .r.ok{color:var(--ok)}.rx-act .r.no{color:var(--or)}
.rx-row{display:flex;gap:10px;align-items:center}.rx-err{color:var(--red);font-weight:600;margin:0 0 12px}.rx-err[hidden]{display:none}
.rx-auth{max-width:480px;width:100%;margin:0 auto;padding:28px 18px 40px}.rx-auth-top{display:flex;align-items:center;gap:10px;font-size:18px;margin:0 0 34px}.rx-auth-top .rx-ib{margin-left:auto}.rx-logo{width:44px;height:44px;border-radius:14px;background:linear-gradient(135deg,#1660E8,#0F4ACB);display:grid;place-items:center;box-shadow:0 10px 22px -10px #1660E8}.rx-logo svg{stroke:#fff}.rx-h1{margin:0 0 8px}
.rx-brand small{display:block;font:600 11px/1 Inter,sans-serif;color:var(--muted);letter-spacing:.08em;text-transform:uppercase;margin-top:3px}
details.rx-card>summary{list-style:none;cursor:pointer;font-weight:700}details.rx-card>summary::-webkit-details-marker{display:none}
.rx-side{display:none}.rx-kpis{display:none}.rx-acard{margin:0 0 12px}
@media(min-width:900px){
.rx-app{max-width:none;padding:0;display:grid;grid-template-columns:268px minmax(0,1fr);min-height:100vh}
.rx-mob,.rx-nav{display:none!important}
.rx-side{display:flex;flex-direction:column;gap:18px;position:sticky;top:0;height:100vh;padding:24px 18px;background:var(--card);border-right:1px solid var(--line);overflow:auto}
.rx-sbrand{display:flex;align-items:center;gap:12px;font-size:17px;padding:0 6px}
.rx-snav{display:flex;flex-direction:column;gap:4px}.rx-snav button{display:flex;align-items:center;gap:12px;border:0;background:none;border-radius:14px;padding:12px 14px;font-weight:600;color:var(--muted);text-align:left}
.rx-snav button svg{width:20px;height:20px}.rx-snav button:hover{background:var(--soft);color:var(--ink)}.rx-snav button.on{background:var(--accs);color:var(--acc)}:root[data-theme="dark"] .rx-snav button.on{color:#9DBBFF}
.rx-snav em{margin-left:auto;font-style:normal;font-size:12px;background:var(--soft);border-radius:99px;padding:1px 8px;color:var(--muted)}
.rx-sacc{border-top:1px solid var(--line);padding:16px 6px 0;display:flex;flex-direction:column;gap:10px}.rx-sacc>small{color:var(--muted);font-weight:700;font-size:11px;letter-spacing:.08em;text-transform:uppercase}
.rx-srow{display:flex;align-items:center;gap:10px;font-weight:600;font-size:14px}.rx-srow .rx-av{width:28px;height:28px;font-size:12px}.rx-srow>span:not(.rx-av){flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis}.rx-srow i{width:8px;height:8px;border-radius:50%;background:var(--ok)}.rx-srow i.bad{background:var(--red)}
.rx-sfoot{margin-top:auto;display:flex;flex-direction:column;gap:2px;border-top:1px solid var(--line);padding-top:12px}
.rx-sbtn{display:flex;align-items:center;gap:12px;border:0;background:none;border-radius:12px;padding:10px 14px;font-weight:600;color:var(--muted);text-decoration:none;font-size:14px}.rx-sbtn:hover{background:var(--soft);color:var(--ink)}.rx-sbtn svg{width:19px;height:19px}
.rx-main{padding:32px 44px 60px;max-width:1280px;width:100%}
.rx-top{margin:0 0 26px}.rx-hero{font-size:50px;letter-spacing:-1.8px;margin:0 0 22px}
.rx-dash{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,1fr);gap:28px;align-items:start}
.rx-dr{position:sticky;top:24px}.rx-kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:0 0 20px}.rx-kpis .rx-card{padding:16px}.rx-kpis small{display:block;color:var(--muted);font-size:12.5px;font-weight:600}.rx-kpis b{font-size:26px;letter-spacing:-.5px}
.rx-tile{padding:22px 10px;font-size:14.5px}.rx-tile:hover,.rx-tpl:hover,.rx-item:hover{border-color:var(--acc)}.rx-tile,.rx-tpl,.rx-item{transition:transform .15s,border-color .15s,box-shadow .15s}.rx-tile:hover,.rx-tpl:hover{transform:translateY(-2px);box-shadow:0 14px 30px -20px var(--sh)}
.rx-promo{padding:24px}.rx-promo h3{font-size:21px}
.rx-head{margin:0 0 24px}.rx-head h1{font-size:28px;letter-spacing:-.6px}
.rx-alist{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:16px}.rx-acard{margin:0}
.rx-pick{max-width:800px}.rx-pick .rx-tpls{grid-template-columns:repeat(4,1fr)}
.rx-ed{display:grid;grid-template-columns:minmax(0,1fr) 400px;gap:24px;align-items:start}.rx-edr{position:sticky;top:24px}
.rx-grid{grid-template-columns:repeat(5,1fr)}
.rx-pgrid{display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start}
}
@media(min-width:900px) and (max-width:1150px){.rx-dash,.rx-ed{grid-template-columns:1fr}.rx-dr,.rx-edr{position:static}.rx-grid{grid-template-columns:repeat(4,1fr)}}
.rx-auth-wrap{min-height:100vh}.rx-auth-side{display:none}
@media(min-width:900px){
.rx-auth-wrap{display:grid;grid-template-columns:1.1fr 1fr}
.rx-auth-side{display:flex;flex-direction:column;justify-content:space-between;padding:48px;color:#fff;background:linear-gradient(150deg,#1660E8,#0F4ACB 45%,#071233);position:relative;overflow:hidden}
.rx-auth-side:after{content:"";position:absolute;right:-90px;bottom:-90px;width:380px;height:380px;border-radius:90px;transform:rotate(18deg);background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.2);backdrop-filter:blur(6px)}
.rx-auth-side h2{font:800 46px/1.05 "Plus Jakarta Sans",sans-serif;letter-spacing:-1.6px;margin:0 0 22px;max-width:520px}.rx-auth-side h2 span{color:#BFD5FF}
.rx-auth-side ul{list-style:none;padding:0;margin:0;display:grid;gap:14px}.rx-auth-side li{display:flex;gap:12px;align-items:center;font-size:16px;color:rgba(255,255,255,.9)}.rx-auth-side li i{width:36px;height:36px;border-radius:12px;background:rgba(255,255,255,.15);display:grid;place-items:center;font-style:normal}
.rx-auth{display:flex;flex-direction:column;justify-content:center;padding:48px}
}
body{background:radial-gradient(900px 600px at 0% 0%,rgba(22,96,232,.10),transparent 60%),radial-gradient(800px 600px at 100% 100%,rgba(96,165,250,.12),transparent 60%),var(--bg);background-attachment:fixed}
:root[data-theme="dark"] body{background:radial-gradient(900px 600px at 0% 0%,rgba(22,96,232,.28),transparent 60%),radial-gradient(800px 600px at 100% 100%,rgba(96,165,250,.14),transparent 60%),var(--bg);background-attachment:fixed}
.rx-card,.rx-item,.rx-tile,.rx-tpl,.rx-hello,.rx-glass{background:color-mix(in srgb,var(--card) 74%,transparent);backdrop-filter:blur(18px) saturate(150%);-webkit-backdrop-filter:blur(18px) saturate(150%);border-color:color-mix(in srgb,var(--line) 70%,transparent)}
:root:not([data-theme="dark"]) .rx-card,:root:not([data-theme="dark"]) .rx-item,:root:not([data-theme="dark"]) .rx-tile{box-shadow:0 1px 0 rgba(255,255,255,.7) inset,0 12px 30px -22px rgba(11,26,51,.25)}
.rx-tile.on{background:color-mix(in srgb,var(--accs) 80%,transparent)}
@media(min-width:900px){.rx-side{background:color-mix(in srgb,var(--card) 70%,transparent);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px)}}
.rx-accs{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;margin:0 0 14px}
.rx-acc{border:1px solid var(--line);border-radius:24px;padding:16px;display:flex;flex-direction:column;gap:14px}
.rx-av.big{width:54px;height:54px;font-size:20px;box-shadow:0 0 0 3px var(--card),0 0 0 5px color-mix(in srgb,var(--acc) 45%,transparent)}
.rx-ell{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:16px}
.rx-pillst{display:inline-block;margin-top:4px;font-size:12px;font-weight:700;border-radius:99px;padding:3px 10px}.rx-pillst.ok{background:rgba(22,163,74,.12);color:var(--ok)}.rx-pillst.bad{background:rgba(229,72,77,.12);color:var(--red)}
.rx-accst{display:grid;grid-template-columns:1fr 1fr;gap:8px}.rx-accst>div{background:color-mix(in srgb,var(--soft) 80%,transparent);border-radius:16px;padding:10px 12px}.rx-accst b{display:block;font-size:20px}.rx-accst small{color:var(--muted);font-size:12px}
.rx-acc .rx-row .rx-btn{flex:1;padding:11px 12px;font-size:14px}.rx-ghost{background:transparent;border:1px solid var(--line)}
[hidden]{display:none!important}
CSS;
}

function ir_dm_js() {
	return <<<'JS'
(function(){
var C=window.IRDM,R=document.getElementById('rx'),S=null,TAB='home',ED=null,MEDIA={};
var I={
home:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>',
chat:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v7a2.5 2.5 0 0 1-2.5 2.5H11l-4.5 4v-4A2.5 2.5 0 0 1 4 13.5z"/><path d="M8.5 9h7M8.5 12h4"/></svg>',
list:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v14H4z"/><path d="M4 9h16M9 13h7M9 16h5"/></svg>',
gear:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>',
spark:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg>',
menu:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h10"/></svg>',
back:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg>',
arrow:'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M8 7h9v9"/></svg>',
crown:'<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r=".6" fill="#fff"/></svg>',
dm:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 3 10 14"/><path d="M21 3l-7 18-4-7-7-4z"/></svg>',
story:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="12" cy="12" r="8.5" stroke-dasharray="3 2.4"/><circle cx="12" cy="12" r="4"/></svg>',
link:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg>',
send:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h12M12 6l6 6-6 6"/></svg>',
bolt:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3 5 14h6l-1 7 8-11h-6z"/></svg>',
logo:'<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v7a2.5 2.5 0 0 1-2.5 2.5H11l-4.5 4v-4A2.5 2.5 0 0 1 4 13.5z"/><path d="M13 7.5 10.5 11h3.5L11.5 14"/></svg>',
out:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H3"/></svg>',
mail:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg>',
dl:'<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>',
moon:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>',
plus:'<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>'};
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function post(action,data){var f=new FormData();f.append('action',action);f.append('n',C.n);Object.keys(data||{}).forEach(function(k){f.append(k,data[k])});
 return fetch(C.aj,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json()}).catch(function(){return{ok:false,err:'No connection. Check your internet and try again.'}})}
function ago(t){var s=Math.floor(Date.now()/1000-t);if(s<60)return 'just now';if(s<3600)return Math.floor(s/60)+'m ago';if(s<86400)return Math.floor(s/3600)+'h ago';return new Date(t*1000).toLocaleDateString()}
function av(a,cls){return '<span class="rx-av '+(cls||'')+'">'+esc(((a&&(a.username||a.name))||(S&&S.me.name)||'?')[0].toUpperCase())+(a&&a.pic?'<img src="'+esc(a.pic)+'" alt="" onerror="this.remove()">':'')+'</span>'}
var TPL=[
 {e:'🎁',n:'Free guide',d:'Comment GUIDE, get the guide',kw:['GUIDE'],msg:'Hey {name}! Here is the free guide you asked for 👇\n{link}\n\nEnjoy, and tell me what you think!',btn:'Get the guide',pub:['Sent! Check your DMs 💌','Just sent it to you {name} 📩','Check your inbox 👀']},
 {e:'📅',n:'Book a call',d:'Comment BOOK, get your booking link',kw:['BOOK'],msg:'Hi {name}! So happy you want to work together. Pick a time that suits you here:\n{link}',btn:'Book now',pub:['Sent you the link {name} 📅','Check your DMs!']},
 {e:'💰',n:'Price list',d:'Comment PRICE, get your prices',kw:['PRICE','PRICES'],msg:'Hi {name}! Here are my prices and packages 👇\n{link}\n\nAny questions, just reply here.',btn:'See prices',pub:['Sent you the price list {name} 💌','Check your DMs!']},
 {e:'✏️',n:'Start blank',d:'Write your own',kw:[],msg:'Hey {name}! ',btn:'',pub:['Check your DMs {name} 💌']}];
function blank(t,trig){t=t||TPL[3];var a=S.accts[0];return{id:0,name:'',acct:a?a.id:'',t:{comments:trig?trig==='comments':true,dms:trig==='dms',story:trig==='story'},any_post:!!trig&&trig!=='comments',post:'',post_img:'',keywords:t.kw.slice(),any_word:false,message:t.msg,link:'',button:t.btn,public_on:t.n!=='Start blank'&&(!trig||trig==='comments'),public:t.pub.slice(),follow:false,follow_msg:'Hey {name}! Tap below and I will send it right over 👇',follow_btn:'Send it to me',ask_email:false,email_ask:'Hey {name}! What is the best email to send it to? 📩',email_thanks:''}}
function load(){return post('ir_dm_state').then(function(r){if(!r.ok){R.innerHTML='<div class="rx-alert bad">'+esc(r.err)+'</div>';return}S=r;draw()})}
var TABS=[['home','Home','home'],['autos','Automations','chat'],['activity','Activity','list'],['leads','Leads','mail'],['profile','Profile','gear']];
function side(){var on=function(t){return TAB===t&&!ED};
 return '<aside class="rx-side"><div class="rx-sbrand"><span class="rx-logo">'+I.logo+'</span><b class="rx-brand">'+esc(C.name)+'<small>AI DM automation</small></b></div>'+
 '<button class="rx-btn rx-lime rx-wide" data-new="">'+I.plus+' New automation</button>'+
 '<nav class="rx-snav">'+TABS.map(function(x){return '<button data-tab="'+x[0]+'" class="'+(on(x[0])?'on':'')+'">'+I[x[2]]+'<span>'+x[1]+'</span>'+(x[0]==='autos'&&S.autos.length?'<em>'+S.autos.length+'</em>':'')+'</button>'}).join('')+'</nav>'+
 '<div class="rx-sacc"><small>Accounts</small>'+(S.accts.length?S.accts.map(function(a){return '<div class="rx-srow">'+av(a)+'<span>@'+esc(a.username)+'</span><i class="'+(a.status==='active'?'ok':'bad')+'"></i></div>'}).join(''):'<div class="rx-muted" style="font-size:13px">None connected</div>')+'<a class="rx-link" href="'+esc(C.connect)+'">+ Add account</a></div>'+
 '<div class="rx-sfoot"><button class="rx-sbtn" onclick="rxTheme()">'+I.moon+'<span>Light / dark</span></button>'+(S.me.admin?'<a class="rx-sbtn" href="'+esc(C.setup)+'">'+I.gear+'<span>Setup</span></a>':'')+'<a class="rx-sbtn" href="'+esc(C.logout)+'">'+I.out+'<span>Sign out</span></a></div></aside>'}
function shell(h){return side()+'<main class="rx-main">'+h+'</main>'+nav()}
function nav(){return '<nav class="rx-nav">'+TABS.map(function(x){var on=TAB===x[0]&&!ED;return '<button data-tab="'+x[0]+'" class="'+(on?'on':'')+'" aria-label="'+x[1]+'">'+(on?'<i>'+I[x[2]]+'</i><span>'+x[1]+'</span>':I[x[2]])+'</button>'}).join('')+'</nav>'}
function banner(){var h='';if(C.msg)h+='<div class="rx-alert ok">'+esc(C.msg)+'</div>';if(C.err)h+='<div class="rx-alert bad">'+esc(C.err)+'</div>';C.msg=C.err='';return h}
function draw(){if(ED)return editor();var h='';
 if(TAB==='home')h=home();else if(TAB==='autos')h=autos();else if(TAB==='activity')h=activity();else if(TAB==='leads')h=leads();else h=profile();
 R.innerHTML=shell(h);bind();window.scrollTo(0,0)}
function connectCard(){var a=S.accts;
 if(!S.ready&&!a.length)return '<div class="rx-promo"><div class="row"><span class="rx-crown">'+I.crown+'</span><div><h3>Almost ready</h3><p>'+(S.me.admin?'Finish the one-time Meta setup first. You can build automations meanwhile.':'Instagram connection opens soon. You can build automations meanwhile.')+'</p></div></div>'+(S.me.admin?'<a class="rx-btn rx-wide" href="'+esc(C.setup)+'">Open setup</a>':'')+'</div>';
 if(!a.length)return '<div class="rx-promo"><div class="row"><span class="rx-crown">'+I.crown+'</span><div><h3>Connect Instagram</h3><p>Business or Creator account. Log in, tap Allow, done.</p></div></div><a class="rx-btn rx-wide" href="'+esc(C.connect)+'">'+I.plus+' Connect Instagram</a></div>';
 var bad=a.filter(function(x){return x.status!=='active'});
 if(bad.length)return '<div class="rx-promo warn"><div class="row"><span class="rx-crown">'+I.crown+'</span><div><h3>@'+esc(bad[0].username)+' needs you</h3><p>Instagram logged it out. Reconnect to keep DMs flowing.</p></div></div><a class="rx-btn rx-wide" href="'+esc(C.connect)+'">Reconnect</a></div>';
 return '<div class="rx-promo"><div class="row"><span class="rx-crown">'+I.crown+'</span><div><h3>'+S.tot.sent+' DMs sent</h3><p>'+S.autos.filter(function(x){return x.on}).length+' automation'+(S.autos.length===1?'':'s')+' live on '+a.map(function(x){return '@'+esc(x.username)}).join(', ')+'</p></div></div><button class="rx-btn rx-wide" data-new="">'+I.plus+' New automation</button></div>'}
function home(){var top='<div class="rx-top"><button class="rx-hello" data-tab="profile">'+av(S.accts[0])+'<span>Hello, '+esc(S.me.name||'there')+'!</span></button><button class="rx-ib lime" data-new="" aria-label="New automation">'+I.spark+'</button><button class="rx-ib rx-mob" onclick="rxTheme()" aria-label="Light or dark mode">'+I.moon+'</button></div>';
 var L=banner()+'<h1 class="rx-hero">Let AI turn your <span>comments</span><br><em>into</em> clients today.</h1>'+
 '<div class="rx-chips"><button class="rx-pill" data-new="">New automation</button><button class="rx-ib" data-new="comments" aria-label="Comment trigger">'+I.chat+'</button><button class="rx-ib" data-new="dms" aria-label="DM trigger">'+I.dm+'</button><button class="rx-ib" data-new="story" aria-label="Story trigger">'+I.story+'</button></div>'+connectCard()+
 '<div class="rx-tiles"><button class="rx-tile" data-new="comments"><span class="rx-ic">'+I.chat+'</span>Comment to DM</button><button class="rx-tile on" data-new="dms"><span class="rx-ic g">'+I.dm+'</span>DM keyword</button><button class="rx-tile" data-new="story"><span class="rx-ic b">'+I.story+'</span>Story reply</button></div>';
 var Rr='<div class="rx-kpis"><div class="rx-card"><small>DMs sent</small><b>'+S.tot.sent+'</b></div><div class="rx-card"><small>Link clicks</small><b>'+S.tot.clicks+'</b></div><div class="rx-card"><small>Live automations</small><b>'+S.autos.filter(function(x){return x.on}).length+'</b></div></div>'+
 '<div class="rx-sec"><h2>Recent activity</h2><button data-tab="activity">View all</button></div>'+
 (S.activity.length?S.activity.slice(0,6).map(actItem).join(''):'<div class="rx-item"><span class="rx-ic k">'+I.bolt+'</span><div class="t"><b>Nothing yet</b><small>When someone comments your word, it shows up here.</small></div></div>');
 return top+'<div class="rx-dash"><div>'+L+'</div><div class="rx-dr">'+Rr+'</div></div>'}
function actItem(x){return '<div class="rx-item"><span class="rx-ic '+(x.ok?'g':'o')+'">'+(x.trig==='comment'?I.chat:x.trig==='story'?I.story:I.dm)+'</span><div class="t"><b>'+esc(x.who)+' · '+esc(x.said||'…')+'</b><small>'+(x.ok?'✓ DM sent':esc(x.res))+' · '+ago(x.when)+'</small></div><span class="rx-go">'+I.arrow+'</span></div>'}
function acctOf(id){return S.accts.filter(function(a){return a.id===String(id)})[0]||S.accts[0]}
function autos(){var h='<div class="rx-head"><h1>Automations</h1><button class="rx-ib lime" data-new="">'+I.plus+'</button></div>'+banner();
 if(!S.autos.length)return h+'<div class="rx-card rx-pad"><p style="margin:0 0 12px;font-weight:700">Pick a starting point</p>'+tpls()+'</div>';
 return h+'<div class="rx-alist">'+S.autos.map(function(a){var c=a.cfg,ac=acctOf(c.acct),w=[];if(c.t.comments)w.push('comments '+(c.any_word?'anything':c.keywords.join(' / ')));if(c.t.dms)w.push('DMs '+(c.any_word?'you':c.keywords.join(' / ')));if(c.t.story)w.push('replies to your Story');
  return '<div class="rx-card rx-pad rx-acard"><div class="rx-row">'+(c.post_img&&!c.any_post?'<img class="rx-thumb" src="'+esc(c.post_img)+'" alt="">':'<span class="rx-thumb">💬</span>')+'<div style="flex:1;min-width:0"><b style="display:block">'+esc(a.name)+'</b><small class="rx-muted">When someone '+esc(w.join(', or '))+(ac&&S.accts.length>1?' · @'+esc(ac.username):'')+'</small><div style="font-size:13px;font-weight:700;margin-top:4px">'+a.sent+' <span class="rx-muted">sent</span> · '+a.clicks+' <span class="rx-muted">clicks</span></div></div><label class="rx-sw"><input type="checkbox" data-tog="'+a.id+'"'+(a.on?' checked':'')+'><i></i></label></div>'+
  '<div class="rx-row" style="margin-top:12px"><button class="rx-btn rx-dark" style="padding:10px 16px" data-edit="'+a.id+'">Edit</button><button class="rx-btn" style="padding:10px 16px;border:1px solid var(--line)" data-try="'+a.id+'">Try it</button><button class="rx-link rx-red" style="margin-left:auto" data-del="'+a.id+'">Delete</button></div><div data-tryout="'+a.id+'"></div></div>'}).join('')+'</div>'}
function tpls(){return '<div class="rx-tpls">'+TPL.map(function(t,i){return '<button class="rx-tpl" data-tpl="'+i+'"><span class="e">'+t.e+'</span><b>'+esc(t.n)+'</b><small>'+esc(t.d)+'</small></button>'}).join('')+'</div>'}
function activity(){return '<div class="rx-head"><h1>Activity</h1><button class="rx-ib" data-reload="1" aria-label="Refresh">'+I.bolt+'</button></div>'+(S.activity.length?'<div class="rx-card rx-pad">'+S.activity.map(function(x){return '<div class="rx-act"><div class="rx-row" style="justify-content:space-between"><b>'+esc(x.who)+'</b><small class="rx-muted">'+ago(x.when)+'</small></div><div class="rx-muted" style="font-size:14px">'+(x.trig==='comment'?'Commented':x.trig==='story'?'Replied to your Story':'Sent you a DM')+': “'+esc(x.said||'…')+'” · '+esc(x.auto)+'</div><div class="r '+(x.ok?'ok':'no')+'">'+(x.ok?'✓ ':'')+esc(x.res)+'</div></div>'}).join('')+'</div>':'<div class="rx-alert info">No activity yet. New comments are picked up within about a minute.</div>')}
function profile(){var a=S.accts,live=S.autos.filter(function(x){return x.on}).length;
 var h='<div class="rx-head"><button class="rx-ib" data-tab="home">'+I.back+'</button><h1>Profile</h1>'+(S.me.admin?'<a class="rx-ib" href="'+esc(C.setup)+'" aria-label="Setup">'+I.gear+'</a>':'<span style="width:48px"></span>')+'</div>'+banner()+'<div class="rx-pgrid"><div>';
 h+='<div class="rx-prof"><div class="rx-card me">'+av(a[0])+'<h2>'+esc(S.me.name||'You')+'</h2><span class="rx-badge">★ '+live+' live automation'+(live===1?'':'s')+'</span><div class="rx-muted" style="font-size:12px">'+esc(S.me.email)+'</div></div><div class="rx-mini"><div class="rx-card"><span class="rx-ic">'+I.dm+'</span><div><b>'+S.tot.sent+'</b><small>DMs sent</small></div></div><div class="rx-card"><span class="rx-ic g">'+I.link+'</span><div><b>'+S.tot.clicks+'</b><small>Link clicks</small></div></div></div></div>';
 h+='<div class="rx-card rx-stats"><div><span class="rx-ic">'+I.chat+'</span><small>Automations</small><b>'+S.autos.length+'</b></div><div><span class="rx-ic b">'+I.crown.split('#fff').join('currentColor')+'</span><small>Accounts</small><b>'+a.length+'</b></div><div><span class="rx-ic o">'+I.bolt+'</span><small>Triggers</small><b>'+S.activity.length+'</b></div></div>';
 h+='</div><div>';
 h+='<div class="rx-promo"><div class="row"><span class="rx-crown">'+I.crown+'</span><div><h3>'+(a.length?'Add another account':'Connect Instagram')+'</h3><p>Run automations on several Instagram accounts from one place.</p></div></div><a class="rx-btn rx-wide" href="'+esc(C.connect)+'">'+I.plus+' '+(a.length?'Add Instagram account':'Connect Instagram')+'</a></div>';
 if(a.length)h+='<div class="rx-sec"><h2>Your accounts</h2></div><div class="rx-accs">'+a.map(function(x){var ok=x.status==='active';return '<div class="rx-acc rx-glass"><div class="rx-row">'+av(x,'big')+'<div style="flex:1;min-width:0"><b class="rx-ell">@'+esc(x.username)+'</b><span class="rx-pillst '+(ok?'ok':'bad')+'">'+(ok?'● Live':'● Reconnect needed')+'</span></div></div><div class="rx-accst"><div><b>'+(x.sent||0)+'</b><small>DMs sent</small></div><div><b>'+(x.autos||0)+'</b><small>Automations</small></div></div><div class="rx-row">'+(ok?'<a class="rx-btn rx-ghost" href="'+esc(C.connect)+'">Refresh</a>':'<a class="rx-btn rx-lime" href="'+esc(C.connect)+'">Reconnect</a>')+'<button class="rx-btn rx-ghost rx-red" data-disc="'+esc(x.id)+'">Remove</button></div></div>'}).join('')+'</div>';
 h+='<details class="rx-card rx-pad" style="margin:12px 0"><summary>How it works & Instagram rules</summary><p class="rx-muted" style="margin:10px 0 0;font-size:14px;line-height:1.6">• One automatic DM per comment, within 7 days of the comment.<br>• DM and Story replies within 24 hours of their message.<br>• Your own comments never trigger anything.<br>• New comments are picked up within about a minute (instantly once your Meta app is Live).<br>• Links are tracked so you can see clicks.</p></details>';
 h+='<a class="rx-btn rx-wide rx-mob" style="border:1px solid var(--line)" href="'+esc(C.logout)+'">Sign out</a></div></div>';return h}
function leads(){var h='<div class="rx-head"><h1>Leads</h1><a class="rx-btn rx-lime" style="padding:11px 16px" href="'+esc(C.aj)+'?action=ir_dm_leads_csv&n='+esc(C.n)+'">'+I.dl+' Export CSV</a></div>'+banner();
 h+='<div class="rx-pgrid"><div><div class="rx-card rx-pad" style="margin:0 0 14px"><div class="rx-row" style="margin:0 0 12px;align-items:flex-start"><span class="rx-ic g">'+I.mail+'</span><div><b style="display:block">Send leads to your email list</b><small class="rx-muted">Paste a webhook link from Zapier, Make, Pabbly, Mailchimp or Brevo. Every new email goes there instantly.</small></div></div>'+
 '<div class="rx-row"><input id="rxHook" class="rx-in2" placeholder="https://hooks.zapier.com/..." value="'+esc(S.hook||'')+'"><button class="rx-btn rx-dark" id="rxHookSave" style="padding:12px 18px">Save</button></div><div class="rx-muted" id="rxHookMsg" style="font-size:13px;margin-top:8px">'+(S.hook?'✓ Connected. New leads are sent automatically.':'Not connected yet. Leads are still saved here and in the CSV.')+'</div></div>'+
 '<div class="rx-alert info" style="font-size:13.5px;font-weight:500">To collect emails, open an automation and switch on <b>Ask for their email first</b>.</div></div><div>';
 h+=S.leads.length?'<div class="rx-card rx-pad">'+S.leads.map(function(l){return '<div class="rx-act"><div class="rx-row" style="justify-content:space-between"><b>'+esc(l.email)+'</b><small class="rx-muted">'+ago(l.when)+'</small></div><div class="rx-muted" style="font-size:13.5px">'+esc(l.who||'')+(l.auto?' · '+esc(l.auto):'')+'</div></div>'}).join('')+'</div>':'<div class="rx-card rx-pad rx-center"><span class="rx-ic g" style="margin:0 auto 10px;width:56px;height:56px">'+I.mail+'</span><b style="display:block">No leads yet</b><small class="rx-muted">Emails people send through your automations appear here.</small></div>';
 return h+'</div></div>'}
function find(id){return S.autos.filter(function(a){return String(a.id)===String(id)})[0]}
function bind(){var q=function(s){return R.querySelectorAll(s)};
 q('[data-tab]').forEach(function(b){b.onclick=function(){TAB=b.dataset.tab;ED=null;draw()}});
 q('[data-new]').forEach(function(b){b.onclick=function(){var t=b.dataset.new;if(t){edit(blank(TPL[t==='comments'?0:3],t))}else{TAB='autos';R.innerHTML=shell('<div class="rx-head"><button class="rx-ib" data-tab="autos">'+I.back+'</button><h1>New automation</h1><span style="width:48px"></span></div><div class="rx-card rx-pad rx-pick"><p style="margin:0 0 14px;font-weight:700">Pick a starting point. You can change everything.</p>'+tpls()+'</div>');bind()}}});
 q('[data-tpl]').forEach(function(b){b.onclick=function(){edit(blank(TPL[+b.dataset.tpl]))}});
 q('[data-edit]').forEach(function(b){b.onclick=function(){var a=find(b.dataset.edit),c=JSON.parse(JSON.stringify(a.cfg));c.id=a.id;c.name=a.name;if(!c.acct&&S.accts[0])c.acct=S.accts[0].id;edit(c)}});
 q('[data-try]').forEach(function(b){b.onclick=function(){var a=find(b.dataset.try),c=JSON.parse(JSON.stringify(a.cfg));c.id=a.id;tryIt(c,R.querySelector('[data-tryout="'+a.id+'"]'))}});
 q('[data-del]').forEach(function(b){b.onclick=function(){if(!confirm('Delete this automation?'))return;post('ir_dm_delete',{id:b.dataset.del}).then(load)}});
 q('[data-tog]').forEach(function(i){i.onchange=function(){post('ir_dm_toggle',{id:i.dataset.tog,on:i.checked?1:''}).then(function(r){if(!r.ok){alert(r.err);i.checked=!i.checked}else load()})}});
 q('[data-disc]').forEach(function(b){b.onclick=function(){if(confirm('Remove this Instagram account? Its automations stop.'))post('ir_dm_disconnect',{ig:b.dataset.disc}).then(load)}});
 q('[data-reload]').forEach(function(b){b.onclick=load});
 var hs=R.querySelector('#rxHookSave');if(hs)hs.onclick=function(){hs.disabled=true;post('ir_dm_hook',{url:R.querySelector('#rxHook').value.trim()}).then(function(x){hs.disabled=false;var m=R.querySelector('#rxHookMsg');if(!x.ok){m.textContent=x.err;m.style.color='var(--red)';return}S.hook=R.querySelector('#rxHook').value.trim();m.style.color='';m.textContent=S.hook?'✓ Saved. We sent a test lead (test@example.com) so you can check it arrived.':'Disconnected.'})}}
/* ---------- editor ---------- */
function edit(c){ED=c;TAB='autos';editor()}
function editor(){var c=ED,h='<div class="rx-head"><button class="rx-ib" id="rxBack">'+I.back+'</button><h1>'+(c.id?'Edit automation':'New automation')+'</h1><span style="width:48px"></span></div><div class="rx-ed"><div>';
 if(S.accts.length>1)h+='<div class="rx-card rx-pad rx-step"><h3><span class="rx-num">@</span>Which account?</h3><div>'+S.accts.map(function(a){return '<button class="rx-tag'+(c.acct===a.id?' on':'')+'" data-acct="'+esc(a.id)+'">'+(a.pic?'<img src="'+esc(a.pic)+'" alt="" onerror="this.remove()">':'')+'@'+esc(a.username)+'</button>'}).join('')+'</div></div>';
 h+='<div class="rx-card rx-pad rx-step"><h3><span class="rx-num">1</span>When should it run?</h3><label class="rx-chk"><input type="checkbox" id="rxTc"'+(c.t.comments?' checked':'')+'><span>Someone <b>comments</b> on my post</span></label><label class="rx-chk"><input type="checkbox" id="rxTd"'+(c.t.dms?' checked':'')+'><span>Someone <b>sends me a DM</b> with the word</span></label><label class="rx-chk" style="margin:0"><input type="checkbox" id="rxTs"'+(c.t.story?' checked':'')+'><span>Someone <b>replies to my Story</b></span></label></div>';
 h+='<div class="rx-card rx-pad rx-step" id="rxPostBox"'+(c.t.comments?'':' hidden')+'><h3><span class="rx-num">2</span>Which post?</h3><div id="rxPosts"></div></div>';
 h+='<div class="rx-card rx-pad rx-step"><h3><span class="rx-num">3</span>Trigger word</h3><label class="rx-f"><input id="rxKw" placeholder="e.g. GUIDE" value="'+esc(c.keywords.join(', '))+'" autocapitalize="characters"><small>Capitals, emojis and dots don\'t matter. Add more with a comma.</small></label><label class="rx-chk" style="margin:0"><input type="checkbox" id="rxAny"'+(c.any_word?' checked':'')+'><span>Reply to <b>every</b> comment or message</span></label></div>';
 h+='<div class="rx-card rx-pad rx-step"><h3><span class="rx-num">4</span>The DM they get</h3><div style="margin:0 0 8px"><button class="rx-tag" data-ins="{name}">+ Their @name</button><button class="rx-tag" data-ins="{link}">+ Your link</button></div><label class="rx-f"><textarea id="rxMsg" rows="5" maxlength="1000">'+esc(c.message)+'</textarea></label><label class="rx-f"><span>Your link <small>(optional, clicks are counted)</small></span><input id="rxLink" type="url" inputmode="url" placeholder="https://yourwebsite.com/offer" value="'+esc(c.link)+'"></label><label class="rx-chk"><input type="checkbox" id="rxBtnOn"'+(c.button?' checked':'')+'><span>Show the link as a button</span></label><label class="rx-f" id="rxBtnBox"'+(c.button?'':' hidden')+' style="margin:0"><span>Button text</span><input id="rxBtn" maxlength="20" value="'+esc(c.button||'Open link')+'"></label></div>';
 h+='<div class="rx-card rx-pad rx-step" id="rxPubBox"'+(c.t.comments?'':' hidden')+'><h3><span class="rx-num">5</span>Reply under the comment?</h3><label class="rx-chk"><input type="checkbox" id="rxPubOn"'+(c.public_on?' checked':'')+'><span>Yes, reply publicly too</span></label><label class="rx-f" id="rxPubTxt"'+(c.public_on?'':' hidden')+' style="margin:0"><textarea id="rxPub" rows="3">'+esc(c.public.join('\n'))+'</textarea><small>One per line. A different one is picked each time.</small></label></div>';
 h+='<div class="rx-card rx-pad rx-step"><h3><span class="rx-num">+</span>Grow your list and followers</h3>'+(S.instant?'':'<div class="rx-alert info" style="font-size:13px;font-weight:500;margin:0 0 12px">These two need your Meta app to be <b>Live</b>, so Instagram tells us when people reply or tap a button.</div>')+
 '<label class="rx-chk"><input type="checkbox" id="rxFol"'+(c.follow?' checked':'')+'><span><b>Only for followers.</b> They tap a button, we check they follow you, then send it. If not, we ask them to follow first.</span></label>'+
 '<div id="rxFolBox"'+(c.follow?'':' hidden')+'><label class="rx-f"><span>First message</span><textarea id="rxFolMsg" rows="2" maxlength="640">'+esc(c.follow_msg||'Hey {name}! Tap below and I will send it right over 👇')+'</textarea></label><label class="rx-f"><span>Button text</span><input id="rxFolBtn" maxlength="20" value="'+esc(c.follow_btn||'Send it to me')+'"></label></div>'+
 '<label class="rx-chk"><input type="checkbox" id="rxEm"'+(c.ask_email?' checked':'')+'><span><b>Ask for their email first.</b> We save it to your Leads (and your email list), then send what they asked for.</span></label>'+
 '<div id="rxEmBox"'+(c.ask_email?'':' hidden')+'><label class="rx-f"><span>Question</span><textarea id="rxEmAsk" rows="2" maxlength="640">'+esc(c.email_ask||'Hey {name}! What is the best email to send it to? 📩')+'</textarea></label><label class="rx-f" style="margin:0"><span>After they reply <small>(optional, otherwise your DM above is sent)</small></span><textarea id="rxEmThx" rows="2" maxlength="1000" placeholder="Thanks {name}! Here it is: {link}">'+esc(c.email_thanks||'')+'</textarea></label></div></div>';
 h+='</div><div class="rx-edr">';
 h+='<div class="rx-card rx-pad rx-step"><h3><span class="rx-num">👀</span>Preview</h3><div class="rx-phone" id="rxPrev"></div></div>';
 h+='<label class="rx-f"><span>Name <small>(just for you)</small></span><input id="rxName" placeholder="e.g. Free guide giveaway" value="'+esc(c.name||'')+'"></label>';
 h+='<div class="rx-err" id="rxErr" hidden></div><button class="rx-btn rx-dark rx-wide" id="rxSave" style="padding:17px">'+(c.id?'Save changes':'Save and switch on')+'</button><button class="rx-btn rx-wide" id="rxTry" style="margin-top:10px;border:1px solid var(--line)">Try it first</button><div id="rxTryOut"></div></div></div>';
 R.innerHTML=shell(h);
 var $=function(s){return R.querySelector(s)};
 $('#rxBack').onclick=function(){ED=null;draw()};
 R.querySelectorAll('[data-tab]').forEach(function(b){b.onclick=function(){ED=null;TAB=b.dataset.tab;draw()}});
 R.querySelectorAll('[data-acct]').forEach(function(b){b.onclick=function(){collect();ED.acct=b.dataset.acct;ED.post='';ED.post_img='';editor()}});
 R.querySelectorAll('[data-ins]').forEach(function(p){p.onclick=function(){var t=$('#rxMsg'),v=p.dataset.ins,s=t.selectionStart||t.value.length;t.value=t.value.slice(0,s)+v+t.value.slice(t.selectionEnd||s);t.focus();prev()}});
 $('#rxTc').onchange=function(){$('#rxPostBox').hidden=!this.checked;$('#rxPubBox').hidden=!this.checked};
 $('#rxBtnOn').onchange=function(){$('#rxBtnBox').hidden=!this.checked;prev()};
 $('#rxPubOn').onchange=function(){$('#rxPubTxt').hidden=!this.checked};
 $('#rxFol').onchange=function(){$('#rxFolBox').hidden=!this.checked;prev()};$('#rxEm').onchange=function(){$('#rxEmBox').hidden=!this.checked;prev()};
 ['#rxFolMsg','#rxFolBtn','#rxEmAsk'].forEach(function(s){$(s).addEventListener('input',prev)});
 ['#rxKw','#rxMsg','#rxLink','#rxBtn','#rxAny'].forEach(function(s){$(s).addEventListener('input',prev);$(s).addEventListener('change',prev)});
 $('#rxSave').onclick=save;$('#rxTry').onclick=function(){tryIt(collect(),$('#rxTryOut'))};
 posts();prev();window.scrollTo(0,0)}
function collect(){var $=function(s){return R.querySelector(s)},c=ED;
 c.keywords=$('#rxKw').value.split(',').map(function(s){return s.trim()}).filter(Boolean);c.any_word=$('#rxAny').checked;c.message=$('#rxMsg').value;c.link=$('#rxLink').value.trim();
 c.button=$('#rxBtnOn').checked?($('#rxBtn').value.trim()||'Open link'):'';c.public_on=$('#rxPubOn').checked;c.public=$('#rxPub').value.split('\n').map(function(s){return s.trim()}).filter(Boolean);
 c.t={comments:$('#rxTc').checked,dms:$('#rxTd').checked,story:$('#rxTs').checked};c.name=$('#rxName').value.trim();
 c.follow=$('#rxFol').checked;c.follow_msg=$('#rxFolMsg').value;c.follow_btn=$('#rxFolBtn').value.trim();c.ask_email=$('#rxEm').checked;c.email_ask=$('#rxEmAsk').value;c.email_thanks=$('#rxEmThx').value;return c}
function prev(){var c=collect(),link=c.link?location.origin+'/dmlink/…':'',btn=!!(c.link&&c.button);
 var m=c.message.replace(/\{name\}|\{username\}/gi,'@their_name').replace(/\{keyword\}/gi,c.keywords[0]||'');
 if(link&&!btn){m=/\{link\}/i.test(m)?m.replace(/\{link\}/gi,link):m+'\n'+link}else{m=m.replace(/[ \t]*\{link\}[ \t]*/gi,' ')}
 m=m.replace(/[ \t]+\n/g,'\n').replace(/\n{3,}/g,'\n\n');
 var nm=function(t){return String(t||'').replace(/\{name\}|\{username\}/gi,'@their_name')},h='<div class="rx-in">'+esc(c.any_word?'Love this! 😍':(c.keywords[0]||'GUIDE'))+'</div>';
 if(c.follow)h+='<div class="rx-out">'+esc(nm(c.follow_msg))+'<div class="rx-bt">'+esc(c.follow_btn||'Send it to me')+'</div></div><div class="rx-in">Tapped</div>';
 if(c.ask_email)h+='<div class="rx-out">'+esc(nm(c.email_ask))+'</div><div class="rx-in">amaka@gmail.com</div>';
 h+='<div class="rx-out">'+esc(m.trim()||'…')+(btn?'<div class="rx-bt">'+esc(c.button)+'</div>':'')+'</div>';R.querySelector('#rxPrev').innerHTML=h}
function posts(){var box=R.querySelector('#rxPosts'),c=ED;if(!box)return;
 var anyB='<button class="rx-post any'+(c.any_post?' on':'')+'" data-any="1">🌐<br>Any post<br><small class="rx-muted">incl. new ones</small></button>';
 if(!S.accts.length){c.any_post=true;box.innerHTML='<div class="rx-grid">'+anyB.replace('rx-post any','rx-post any on')+'</div><small class="rx-muted">Connect Instagram to pick one post.</small>';return}
 var key=c.acct||S.accts[0].id;
 var paint=function(){box.innerHTML='<div class="rx-grid">'+anyB+(MEDIA[key]||[]).map(function(m){return '<button class="rx-post'+(!c.any_post&&c.post===m.id?' on':'')+'" data-id="'+esc(m.id)+'" data-img="'+esc(m.img)+'" title="'+esc(m.cap)+'">'+(m.img?'<img src="'+esc(m.img)+'" alt="" loading="lazy">':'')+'</button>'}).join('')+'</div>';
  box.querySelectorAll('.rx-post').forEach(function(b){b.onclick=function(){if(b.dataset.any){c.any_post=true;c.post='';c.post_img=''}else{c.any_post=false;c.post=b.dataset.id;c.post_img=b.dataset.img}paint()}})};
 if(MEDIA[key])return paint();box.innerHTML='<div class="rx-load" style="height:120px"><i></i></div>';
 post('ir_dm_media',{ig:key}).then(function(r){MEDIA[key]=r.ok?r.media:[];if(!r.ok)box.innerHTML='<div class="rx-alert bad">'+esc(r.err)+'</div>';else paint()})}
function save(){var c=collect(),b=R.querySelector('#rxSave'),e=R.querySelector('#rxErr');b.disabled=true;b.textContent='Saving…';e.hidden=true;
 post('ir_dm_save',{cfg:JSON.stringify(c)}).then(function(r){b.disabled=false;b.textContent=c.id?'Save changes':'Save and switch on';
  if(!r.ok){e.textContent=r.err;e.hidden=false;e.scrollIntoView({behavior:'smooth',block:'center'});return}
  C.msg=c.id?'Saved.':(S.accts.length?'Your automation is live! Comment the word from another account to test it.':'Saved. It goes live when you connect Instagram.');ED=null;TAB='autos';load()})}
function tryIt(c,out){out.innerHTML='<div class="rx-card rx-pad" style="margin-top:12px;background:var(--soft)"><b>Try it</b><p class="rx-muted" style="margin:4px 0 10px;font-size:14px">Type a comment like a follower would. Nothing is sent.</p><div class="rx-row"><input class="rxTi rx-in2" style="flex:1;min-width:0" value="'+esc((c.keywords[0]||'Love this')+' please! 🙏')+'"><button class="rx-btn rx-dark rxTg" style="padding:12px 16px">Test</button></div><div class="rxTr" style="margin-top:12px"></div></div>';
 var go=function(){var res=out.querySelector('.rxTr');res.innerHTML='<div class="rx-load" style="height:60px"><i></i></div>';
  post('ir_dm_test',{cfg:JSON.stringify(c),text:out.querySelector('.rxTi').value}).then(function(r){if(!r.ok){res.innerHTML='<div class="rx-alert bad">'+esc(r.err)+'</div>';return}
   if(!r.match){res.innerHTML='<div class="rx-alert bad">'+esc(r.why)+'</div>';return}
   res.innerHTML='<div class="rx-alert ok" style="margin-bottom:8px">✓ This comment gets your DM</div><div class="rx-phone"><div class="rx-out">'+esc(r.dm)+(r.button?'<div class="rx-bt">'+esc(r.button)+'</div>':'')+'</div></div>'+(r.pub?'<p style="margin:10px 0 0;font-size:14px"><b>Public reply:</b> '+esc(r.pub)+'</p>':'')})};
 out.querySelector('.rxTg').onclick=go;go()}
load();
})();
JS;
}

/* =========================================================================
 * Admin: Tools > DM Automation (one-time setup for the site owner)
 * ========================================================================= */

add_action( 'admin_menu', function () {
	global $submenu;
	add_menu_page( IR_DM_NAME, IR_DM_NAME, 'manage_options', 'ir-dm', 'ir_dm_admin', 'dashicons-instagram', 58 );
	add_submenu_page( 'ir-dm', IR_DM_NAME . ' setup', 'Setup', 'manage_options', 'ir-dm', 'ir_dm_admin' );
	$submenu['ir-dm'][] = [ 'Open the app ↗', 'manage_options', ir_dm_url() ];
} );

add_action( 'admin_post_ir_dm_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No access' ); }
	check_admin_referer( 'ir_dm_admin' );
	$o = ir_dm_opt();
	$o['login'] = ( $_POST['login'] ?? '' ) === 'unified' ? 'unified' : 'instagram';
	foreach ( [ 'ig_app_id', 'meta_app_id', 'config_id' ] as $k ) { $o[ $k ] = preg_replace( '/[^0-9]/', '', (string) wp_unslash( $_POST[ $k ] ?? '' ) ); }
	foreach ( [ 'ig_secret', 'fb_secret' ] as $k ) {
		$v = trim( (string) wp_unslash( $_POST[ $k ] ?? '' ) );
		if ( $v !== '' ) { $o[ $k ] = ir_dm_enc( $v ); }
		if ( ! empty( $_POST[ $k . '_clear' ] ) ) { $o[ $k ] = ''; }
	}
	$o['access'] = in_array( $_POST['access'] ?? '', [ 'everyone', 'members', 'admin' ], true ) ? (string) $_POST['access'] : 'everyone';
	$o['graph'] = preg_match( '/^v\d+\.\d+$/', (string) ( $_POST['graph'] ?? '' ) ) ? (string) $_POST['graph'] : 'v25.0';
	$o['cap_private'] = max( 1, min( 750, (int) ( $_POST['cap_private'] ?? 750 ) ) );
	$o['cap_dm'] = max( 1, min( 1000, (int) ( $_POST['cap_dm'] ?? 200 ) ) );
	update_option( 'ir_dm_settings', $o, false );
	wp_safe_redirect( admin_url( 'admin.php?page=ir-dm&saved=1' ) ); exit;
} );

function ir_dm_admin() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	global $wpdb;
	$o = ir_dm_opt();
	$members = count( get_users( [ 'meta_key' => 'ir_dm_ig_id', 'fields' => 'ID', 'number' => 5000 ] ) );
	$autos = (int) wp_count_posts( 'ir_dm_auto' )->publish;
	$sent7 = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . ir_dm_t( 'log' ) . " WHERE status='sent' AND sent_at > %s", gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) );
	$last = (int) get_option( 'ir_dm_last_hook' );
	$bad = (int) get_option( 'ir_dm_last_bad_sig' );
	$ok = function ( $b ) { return $b ? '<span style="color:#16a34a;font-weight:700">✓</span>' : '<span style="color:#dc2626;font-weight:700">✗</span>'; };
	$copy = function ( $v ) { return '<code style="user-select:all;padding:4px 8px">' . esc_html( $v ) . '</code>'; };
	echo '<div class="wrap" style="max-width:900px"><h1>DM Automation</h1>';
	if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success"><p>Saved.</p></div>'; }
	echo '<p><a class="button button-primary button-hero" href="' . esc_url( ir_dm_url() ) . '">Open ' . esc_html( IR_DM_NAME ) . ' →</a></p><p>The app lives at <a href="' . esc_url( ir_dm_url() ) . '">' . esc_html( ir_dm_url() ) . '</a>. This page is the one-time Meta setup.</p>';
	echo '<h2>Status</h2><table class="widefat striped" style="max-width:900px"><tbody>';
	echo '<tr><td>' . $ok( ir_dm_ready() ) . ' Meta app details saved</td><td>' . ( ir_dm_ready() ? 'You can connect Instagram in the app.' : 'Fill in the form below.' ) . '</td></tr>'; // phpcs:ignore
	echo '<tr><td>' . $ok( $last > 0 ) . ' Webhooks arriving from Meta</td><td>' . ( $last ? 'Last one ' . esc_html( human_time_diff( $last ) ) . ' ago' : 'None yet' ) . ( $bad && $bad > $last ? ' · <b style="color:#dc2626">A webhook was rejected (wrong app secret?) ' . esc_html( human_time_diff( $bad ) ) . ' ago</b>' : '' ) . '</td></tr>'; // phpcs:ignore
	echo '<tr><td>' . $ok( function_exists( 'as_enqueue_async_action' ) ) . ' Background queue (Action Scheduler)</td><td>Retries and delayed sends.</td></tr>'; // phpcs:ignore
	echo '<tr><td>Instagram accounts connected</td><td>' . (int) $members . '</td></tr><tr><td>Active automations</td><td>' . (int) $autos . '</td></tr><tr><td>DMs sent (7 days)</td><td>' . (int) $sent7 . '</td></tr>';
	echo '</tbody></table>';
	echo '<h2>Paste these into your Meta app</h2><table class="form-table"><tbody>';
	echo '<tr><th>Webhook callback URL</th><td>' . $copy( ir_dm_webhook_url() ) . '</td></tr>'; // phpcs:ignore
	echo '<tr><th>Webhook verify token</th><td>' . $copy( $o['verify'] ) . '<p class="description">Subscribe to the <b>comments</b> and <b>messages</b> fields.</p></td></tr>'; // phpcs:ignore
	echo '<tr><th>OAuth redirect URI</th><td>' . $copy( ir_dm_redirect_uri() ) . '</td></tr>'; // phpcs:ignore
	echo '<tr><th>Privacy / Terms / Data deletion URLs</th><td>Use your site\'s privacy policy, terms and a data deletion page (Meta requires them before going Live).</td></tr>';
	echo '</tbody></table>';
	echo '<h2>Meta app details</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'ir_dm_admin' );
	echo '<input type="hidden" name="action" value="ir_dm_save"><table class="form-table"><tbody>';
	echo '<tr><th>Login type</th><td><label><input type="radio" name="login" value="instagram"' . checked( ( $o['login'] ?? 'instagram' ) !== 'unified', true, false ) . '> API setup with Instagram login</label><br><label><input type="radio" name="login" value="unified"' . checked( ( $o['login'] ?? '' ) === 'unified', true, false ) . '> API setup with Unified login (newer apps that only offer this)</label></td></tr>';
	echo '<tr><th>Instagram app ID</th><td><input name="ig_app_id" class="regular-text" value="' . esc_attr( $o['ig_app_id'] ?? '' ) . '"><p class="description">Instagram login only. Instagram product page, not the Facebook App ID.</p></td></tr>';
	echo '<tr><th>Instagram app secret</th><td><input name="ig_secret" type="password" class="regular-text" placeholder="' . ( ! empty( $o['ig_secret'] ) ? '•••••••• saved (leave blank to keep)' : '' ) . '" autocomplete="off"></td></tr>';
	echo '<tr><th>Facebook app secret</th><td><input name="fb_secret" type="password" class="regular-text" placeholder="' . ( ! empty( $o['fb_secret'] ) ? '•••••••• saved (leave blank to keep)' : '' ) . '" autocomplete="off"><p class="description">App settings › Basic › App secret. Used to check webhook signatures (and for Unified login).</p></td></tr>';
	echo '<tr><th>Meta app ID</th><td><input name="meta_app_id" class="regular-text" value="' . esc_attr( $o['meta_app_id'] ?? '' ) . '"><p class="description">Unified login only. App settings › Basic › App ID.</p></td></tr>';
	echo '<tr><th>Login configuration ID</th><td><input name="config_id" class="regular-text" value="' . esc_attr( $o['config_id'] ?? '' ) . '"><p class="description">Unified login only. Include instagram_basic, instagram_manage_messages, instagram_manage_comments and business_management.</p></td></tr>';
	$acc = ir_dm_opt( 'access', 'everyone' );
	echo '<tr><th>Who can use ' . esc_html( IR_DM_NAME ) . '</th><td><select name="access"><option value="everyone"' . selected( $acc, 'everyone', false ) . '>Anyone who signs up</option><option value="members"' . selected( $acc, 'members', false ) . '>AI Profit System / Cold Outreach members</option><option value="admin"' . selected( $acc, 'admin', false ) . '>Only me (admins)</option></select><p class="description">Sign-up page: ' . esc_html( ir_dm_url() ) . '. Note: until Meta approves your app (App Review), only Instagram accounts added as testers on your Meta app can connect.</p></td></tr>';
	echo '<tr><th>Graph API version</th><td><input name="graph" value="' . esc_attr( ir_dm_graph_ver() ) . '" style="width:90px"></td></tr>';
	echo '<tr><th>Hourly limits per account</th><td>Comment DMs <input name="cap_private" type="number" value="' . (int) ir_dm_opt( 'cap_private', 750 ) . '" style="width:80px"> &nbsp; DM/Story replies <input name="cap_dm" type="number" value="' . (int) ir_dm_opt( 'cap_dm', 200 ) . '" style="width:80px"></td></tr>';
	echo '</tbody></table>';
	submit_button( 'Save' );
	echo '</form><p class="description">Secrets are stored encrypted with this site\'s security keys. If you ever change the keys in wp-config.php, re-enter the secrets and members reconnect Instagram.</p></div>';
}
