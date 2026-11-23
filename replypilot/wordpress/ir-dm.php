<?php
/**
 * Plugin Name: IR DM Automation
 * Description: Instagram comment-to-DM automation inside the AI Profit System (AI Tools > DM Automation, /members/prompts/dm/).
 *   Members connect their Instagram professional account through Meta's official API, pick a post, choose a trigger word
 *   and write the DM. Also handles keyword DMs and Story replies, optional public comment replies, tracked links,
 *   stats and a plain-English activity log. Admin setup: Tools > DM Automation.
 *   WordPress port of ReplyPilot. Keyword normalization approach adapted from OpenReply
 *   (https://github.com/diwenne/openreply, MIT, (c) 2026 Anish Raj, Diwen Huang).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'IR_DM_VER', '1.0.0' );

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
	update_option( 'ir_dm_db', IR_DM_VER, false );
}

function ir_dm_now() { return gmdate( 'Y-m-d H:i:s' ); }

/* ---------- connected Instagram account (one per member) ---------- */

function ir_dm_acct( $uid ) {
	$a = get_user_meta( $uid, 'ir_dm_ig', true );
	return is_array( $a ) && ! empty( $a['id'] ) ? $a : null;
}
function ir_dm_acct_public( $a ) {
	if ( ! $a ) { return null; }
	return [ 'username' => $a['username'], 'name' => $a['name'] ?? '', 'pic' => $a['pic'] ?? '', 'status' => $a['status'] ?? 'active', 'expires' => $a['expires'] ?? 0, 'subscribed' => ! empty( $a['subscribed'] ) ];
}
function ir_dm_owner( $ig_id ) {
	$u = get_users( [ 'meta_key' => 'ir_dm_ig_id', 'meta_value' => (string) $ig_id, 'number' => 1, 'fields' => 'ID' ] );
	return $u ? (int) $u[0] : 0;
}
function ir_dm_mark_acct( $uid, $status, $detail = '' ) {
	$a = ir_dm_acct( $uid ); if ( ! $a ) { return; }
	$a['status'] = $status; $a['detail'] = $detail;
	update_user_meta( $uid, 'ir_dm_ig', $a );
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
		'buttons' => [ [ 'type' => 'web_url', 'url' => $m['button']['url'], 'title' => $m['button']['title'] ] ] ] ] ];
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
function ir_dm_redirect_uri() { return function_exists( 'ir_pp_url' ) ? ir_pp_url( 'dm-callback' ) : home_url( '/members/prompts/dm-callback/' ); }

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
		$res = $e['k'] === 'comment' ? ir_dm_do_comment( $e ) : ir_dm_do_message( $e );
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
function ir_dm_fail( $uid, $log_id, WP_Error $err ) {
	ir_dm_set( $log_id, [ 'status' => 'failed', 'error' => $err->get_error_message() ] );
	switch ( $err->get_error_code() ) {
		case 'token': ir_dm_mark_acct( $uid, 'expired', $err->get_error_message() ); return null;
		case 'rate': ir_dm_pause( $uid ); return [ 'defer' => 900 ];
		case 'window':
		case 'permission': return null;
		default: return [ 'retry' => 1 ];
	}
}

function ir_dm_send( $acct, $recipient, $m ) {
	$r = ir_dm_api( $acct, 'POST', $acct['id'] . '/messages', [ 'recipient' => $recipient, 'message' => ir_dm_msg_body( $m ) ] );
	// Some accounts refuse button templates: fall back once to plain text with the link inline.
	if ( is_wp_error( $r ) && ! empty( $m['button'] ) && $r->get_error_code() === 'permission' && ( $r->get_error_data()['code'] ?? 0 ) === 100 && ( $r->get_error_data()['sub'] ?? 0 ) !== 2534037 ) {
		$m = [ 'text' => ir_dm_trunc( $m['text'] . "\n" . $m['button']['url'] ) ];
		$r = ir_dm_api( $acct, 'POST', $acct['id'] . '/messages', [ 'recipient' => $recipient, 'message' => ir_dm_msg_body( $m ) ] );
	}
	return [ $r, $m ];
}

function ir_dm_do_comment( $e ) {
	$uid = ir_dm_owner( $e['ig'] );
	if ( ! $uid || $e['from'] === $e['ig'] ) { return 'ignored'; }
	$acct = ir_dm_acct( $uid );
	$retry = false; $defer = 0;
	foreach ( ir_dm_autos( $uid, 'comments' ) as $a ) {
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
				if ( $r->get_error_code() === 'token' ) { ir_dm_mark_acct( $uid, 'expired', $r->get_error_message() ); continue; }
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
		$m = ir_dm_build( $c, $a['id'], $e['uname'], $kw, $e['from'] );
		$attempts = (int) $log->attempts + 1;
		ir_dm_set( $log->id, [ 'attempts' => $attempts, 'dm_text' => $m['text'] ] );
		list( $r, $m ) = ir_dm_send( $acct, [ 'comment_id' => $e['id'] ], $m );
		if ( is_wp_error( $r ) ) {
			$f = ir_dm_fail( $uid, $log->id, $r );
			if ( ! empty( $f['defer'] ) ) { $defer = max( $defer, $f['defer'] ); }
			if ( ! empty( $f['retry'] ) && $attempts < IR_DM_MAX_ATTEMPTS ) { $retry = true; }
		} else {
			ir_dm_set( $log->id, [ 'status' => 'sent', 'sent_at' => ir_dm_now(), 'error' => null, 'dm_text' => $m['text'] ] );
		}
	}
	if ( $defer ) { return [ 'defer' => $defer ]; }
	return $retry ? [ 'retry' => 1 ] : 'done';
}

function ir_dm_do_message( $e ) {
	$uid = ir_dm_owner( $e['ig'] );
	if ( ! $uid || $e['from'] === $e['ig'] ) { return 'ignored'; }
	$hit = null;
	foreach ( ir_dm_autos( $uid, $e['k'] === 'story' ? 'story' : 'dms' ) as $a ) {
		$kw = ir_dm_match( $e['text'], $a['cfg'] );
		if ( $kw !== false ) { $hit = [ $a, $kw ]; break; } // one automated answer per message: the oldest matching automation
	}
	if ( ! $hit ) { return 'nomatch'; }
	list( $a, $kw ) = $hit;
	$log = ir_dm_claim( $uid, $a['id'], $e, $kw );
	if ( ! $log || in_array( $log->status, [ 'sent', 'skipped' ], true ) || (int) $log->attempts >= IR_DM_MAX_ATTEMPTS ) { return 'done'; }
	$acct = ir_dm_acct( $uid );
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
	$m = ir_dm_build( $a['cfg'], $a['id'], $name, $kw, $e['from'] );
	$attempts = (int) $log->attempts + 1;
	ir_dm_set( $log->id, [ 'attempts' => $attempts, 'dm_text' => $m['text'] ] );
	list( $r, $m ) = ir_dm_send( $acct, [ 'id' => $e['from'] ], $m );
	if ( is_wp_error( $r ) ) {
		$f = ir_dm_fail( $uid, $log->id, $r );
		if ( ! empty( $f['defer'] ) ) { return $f; }
		return ( ! empty( $f['retry'] ) && $attempts < IR_DM_MAX_ATTEMPTS ) ? [ 'retry' => 1 ] : 'failed';
	}
	ir_dm_set( $log->id, [ 'status' => 'sent', 'sent_at' => ir_dm_now(), 'error' => null, 'dm_text' => $m['text'] ] );
	return 'sent';
}

/* ---------- daily token refresh (Instagram-login tokens last 60 days) ---------- */

add_action( 'init', function () {
	if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( 'ir_dm_refresh' ) ) {
		as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, 'ir_dm_refresh', [], 'ir-dm' );
	}
}, 20 );
add_action( 'ir_dm_refresh', function () {
	foreach ( get_users( [ 'meta_key' => 'ir_dm_ig_id', 'fields' => 'ID', 'number' => 2000 ] ) as $uid ) {
		$a = ir_dm_acct( $uid );
		if ( ! $a || ( $a['login'] ?? '' ) !== 'instagram' || ( $a['status'] ?? '' ) !== 'active' ) { continue; }
		if ( empty( $a['expires'] ) || $a['expires'] > time() + 10 * DAY_IN_SECONDS || ( $a['refreshed'] ?? 0 ) > time() - DAY_IN_SECONDS ) { continue; }
		$r = ir_dm_get( 'https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token=' . rawurlencode( ir_dm_dec( $a['token'] ) ) );
		if ( is_wp_error( $r ) ) { if ( $r->get_error_code() === 'token' ) { ir_dm_mark_acct( $uid, 'expired', $r->get_error_message() ); } continue; }
		$a['token'] = ir_dm_enc( $r['access_token'] ); $a['expires'] = time() + (int) ( $r['expires_in'] ?? 5184000 ); $a['refreshed'] = time();
		update_user_meta( $uid, 'ir_dm_ig', $a );
	}
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
	if ( ! ir_dm_ready() ) { wp_safe_redirect( ir_pp_url( 'dm' ) ); exit; }
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
	$back = function ( $q ) { wp_safe_redirect( add_query_arg( $q, ir_pp_url( 'dm' ) ) ); exit; };
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
		$me = $list['data'][0];
		$acct = [ 'id' => (string) $me['id'], 'username' => (string) ( $me['username'] ?? $me['id'] ), 'name' => (string) ( $me['name'] ?? '' ), 'pic' => (string) ( $me['profile_picture_url'] ?? '' ),
			'login' => 'unified', 'token' => ir_dm_enc( $tok['access_token'] ), 'expires' => ! empty( $tok['expires_in'] ) ? time() + (int) $tok['expires_in'] : 0 ];
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
	}
	$other = ir_dm_owner( $acct['id'] );
	if ( $other && $other !== $uid ) { $back( [ 'err' => '@' . $acct['username'] . ' is already connected to another member account.' ] ); }
	$acct['status'] = 'active';
	$sub = ir_dm_api( $acct, 'POST', $acct['id'] . '/subscribed_apps?subscribed_fields=comments,messages' );
	$acct['subscribed'] = ! is_wp_error( $sub ) && ! empty( $sub['success'] );
	update_user_meta( $uid, 'ir_dm_ig', $acct );
	update_user_meta( $uid, 'ir_dm_ig_id', $acct['id'] );
	delete_user_meta( $uid, 'ir_dm_pause' );
	delete_transient( 'ir_dm_media_' . $uid );
	$back( [ 'connected' => 1 ] );
}

function ir_dm_connect_err( WP_Error $e ) {
	$m = $e->get_error_message();
	if ( stripos( $m, 'Insufficient developer role' ) !== false || stripos( $m, 'Unsupported request' ) !== false ) {
		return 'Instagram says this account is not allowed yet. Message support so we can add your account, then try again.';
	}
	if ( stripos( $m, 'redirect_uri' ) !== false ) { return 'Instagram login is not fully set up yet. Please message support.'; }
	return 'Instagram could not connect your account. Make sure it is a Business or Creator account and try again.';
}

/* =========================================================================
 * Member AJAX API (all scoped to the logged-in member)
 * ========================================================================= */

function ir_dm_member_ok() {
	check_ajax_referer( 'ir_dm', 'n' );
	if ( ! ( function_exists( 'ir_tl_member' ) ? ir_tl_member() : current_user_can( 'manage_options' ) ) ) { wp_send_json( [ 'ok' => false, 'err' => 'Please log in again.' ] ); }
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
	wp_send_json( [ 'ok' => true, 'ready' => ir_dm_ready(), 'acct' => ir_dm_acct_public( ir_dm_acct( $uid ) ), 'autos' => $autos, 'activity' => $act, 'tot' => $tot ] );
} );

add_action( 'wp_ajax_ir_dm_media', function () {
	$uid = ir_dm_member_ok();
	$a = ir_dm_acct( $uid );
	if ( ! $a ) { wp_send_json( [ 'ok' => true, 'media' => [] ] ); }
	$cached = get_transient( 'ir_dm_media_' . $uid );
	if ( is_array( $cached ) && empty( $_POST['fresh'] ) ) { wp_send_json( [ 'ok' => true, 'media' => $cached ] ); }
	$r = ir_dm_api( $a, 'GET', $a['id'] . '/media?fields=id,caption,media_type,media_url,thumbnail_url,permalink,timestamp&limit=30' );
	if ( is_wp_error( $r ) ) { wp_send_json( [ 'ok' => false, 'err' => ir_dm_friendly( $r->get_error_message() ) ] ); }
	$media = array_map( function ( $m ) {
		return [ 'id' => (string) $m['id'], 'img' => (string) ( $m['thumbnail_url'] ?? ( $m['media_url'] ?? '' ) ), 'cap' => mb_substr( (string) ( $m['caption'] ?? '' ), 0, 80 ), 'link' => (string) ( $m['permalink'] ?? '' ), 'video' => ( $m['media_type'] ?? '' ) === 'VIDEO' ];
	}, (array) ( $r['data'] ?? [] ) );
	set_transient( 'ir_dm_media_' . $uid, $media, 10 * MINUTE_IN_SECONDS );
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
	delete_user_meta( $uid, 'ir_dm_ig' );
	delete_user_meta( $uid, 'ir_dm_ig_id' );
	delete_transient( 'ir_dm_media_' . $uid );
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

add_action( 'ir_pp_tool_tiles', function () {
	echo '<a class="px-tool" style="--a:#E1306C;--b:#F77737" href="' . esc_url( ir_pp_url( 'dm' ) ) . '"><span class="ic">' . ir_dm_icon() . '</span><span><b>DM Automation</b><small>Auto-send a DM to anyone who comments your word on a post</small></span></a>'; // phpcs:ignore
} );

add_filter( 'ir_tools_map', function ( $map ) {
	$map['dm'] = 'ir_dm_page';
	$map['dm-connect'] = 'ir_dm_page_connect';
	$map['dm-callback'] = 'ir_dm_page_callback';
	return $map;
} );

function ir_dm_page() {
	ir_pp_open( 'DM Automation', 'home' );
	if ( function_exists( 'ir_tl_css' ) ) { echo '<style>' . ir_tl_css() . '</style>'; } // phpcs:ignore
	echo '<style>' . ir_dm_css() . '</style>'; // phpcs:ignore
	echo '<main class="pp-main narrow"><div class="tl dm" style="--k1:#E1306C;--k2:#F77737">';
	echo '<nav class="pp-crumb"><a href="' . esc_url( ir_pp_url() ) . '">AI Profit System</a><span>›</span><span>DM Automation</span></nav>';
	echo '<div class="tl-hero"><span class="ic">' . ir_dm_icon( 28 ) . '</span><div><h1>DM Automation</h1><p>When someone comments a word on your post, they get your DM automatically. Set it up once, it works 24/7.</p></div></div>'; // phpcs:ignore
	$cfg = [ 'aj' => admin_url( 'admin-ajax.php' ), 'n' => wp_create_nonce( 'ir_dm' ), 'connect' => ir_pp_url( 'dm-connect' ),
		'msg' => isset( $_GET['connected'] ) ? 'Your Instagram is connected. Now create your first automation below.' : '', 'err' => sanitize_text_field( wp_unslash( $_GET['err'] ?? '' ) ) ];
	echo '<div id="dmApp"><div class="tl-status"><span class="tl-spin"></span><span>Loading…</span></div></div>';
	echo '</div></main>';
	echo '<script>window.IRDM=' . wp_json_encode( $cfg ) . ';</script><script>' . ir_dm_js() . '</script>'; // phpcs:ignore
	ir_pp_close();
}

function ir_dm_css() {
	return <<<'CSS'
.dm .dm-sec{margin:0 0 22px}.dm h2{font-size:19px;margin:0 0 10px;letter-spacing:-.2px}
.dm .dm-acct{display:flex;gap:12px;align-items:center}.dm .dm-av{width:48px;height:48px;border-radius:50%;object-fit:cover;background:linear-gradient(135deg,#E1306C,#F77737);display:grid;place-items:center;color:#fff;font-weight:800;flex:none}
.dm .dm-acct b{display:block;font-size:16px}.dm .dm-acct small{color:var(--muted)}.dm .dm-ok{color:#16a34a;font-weight:800}
.dm .dm-steps{margin:10px 0 14px;padding-left:20px;line-height:1.7;font-size:15px}
.dm .dm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:8px;margin:6px 0 10px}
.dm .dm-post{position:relative;border:2.5px solid transparent;border-radius:14px;overflow:hidden;padding:0;background:var(--soft);cursor:pointer;aspect-ratio:1}
.dm .dm-post img{width:100%;height:100%;object-fit:cover;display:block}.dm .dm-post.on{border-color:var(--acc);box-shadow:0 0 0 3px color-mix(in srgb,var(--acc) 25%,transparent)}
.dm .dm-post.on:after{content:"✓";position:absolute;top:6px;right:6px;background:var(--acc);color:#fff;border-radius:50%;width:24px;height:24px;display:grid;place-items:center;font-weight:800}
.dm .dm-any{display:grid;place-items:center;text-align:center;font-weight:800;font-size:13px;color:var(--text);padding:8px}
.dm .dm-auto{display:flex;gap:12px;align-items:center}.dm .dm-thumb{width:56px;height:56px;border-radius:12px;object-fit:cover;flex:none;background:var(--soft);display:grid;place-items:center;font-size:22px}
.dm .dm-auto .tx{flex:1;min-width:0}.dm .dm-auto .tx>b{display:block;font-size:16px}.dm .dm-auto .tx small{display:block;color:var(--muted);font-size:13.5px;line-height:1.4}
.dm .dm-nums{display:flex;gap:14px;margin-top:6px;font-size:13.5px;font-weight:700}.dm .dm-nums span{color:var(--muted);font-weight:600}
.dm .dm-sw{position:relative;width:52px;height:30px;flex:none}.dm .dm-sw input{opacity:0;width:0;height:0}.dm .dm-sw i{position:absolute;inset:0;border-radius:99px;background:var(--line);cursor:pointer;transition:.2s}
.dm .dm-sw i:before{content:"";position:absolute;width:24px;height:24px;left:3px;top:3px;border-radius:50%;background:#fff;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.25)}.dm .dm-sw input:checked+i{background:#16a34a}.dm .dm-sw input:checked+i:before{transform:translateX(22px)}
.dm .dm-tpls{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px}.dm .dm-tpl{border:1.5px solid var(--line);border-radius:16px;padding:14px;background:var(--surface);text-align:left;cursor:pointer;color:var(--text);font:inherit}
.dm .dm-tpl b{display:block;font-size:15px}.dm .dm-tpl small{color:var(--muted);font-size:13px;line-height:1.4}.dm .dm-tpl .e{font-size:24px;display:block;margin-bottom:4px}.dm .dm-tpl:hover{border-color:var(--acc)}
.dm .dm-num{display:inline-grid;place-items:center;width:26px;height:26px;border-radius:50%;background:var(--acc);color:#fff;font-weight:800;font-size:14px;margin-right:8px}
.dm .dm-step h3{display:flex;align-items:center;font-size:17px;margin:0 0 10px}
.dm .dm-phone{background:var(--soft);border-radius:18px;padding:14px;display:flex;flex-direction:column;gap:8px}
.dm .dm-in{align-self:flex-end;background:linear-gradient(135deg,#E1306C,#F77737);color:#fff;padding:9px 13px;border-radius:18px 18px 4px 18px;max-width:80%;font-size:15px}
.dm .dm-out{align-self:flex-start;background:var(--surface);border:1px solid var(--line);padding:9px 13px;border-radius:18px 18px 18px 4px;max-width:88%;white-space:pre-wrap;word-break:break-word;font-size:15px;line-height:1.5}
.dm .dm-bt{margin-top:8px;padding-top:8px;border-top:1px solid var(--line);text-align:center;font-weight:800;color:#2563eb}
.dm .dm-pill{display:inline-block;border:1.5px solid var(--line);background:var(--surface);color:var(--text);border-radius:99px;padding:6px 12px;font-weight:700;font-size:13.5px;cursor:pointer;margin:0 6px 6px 0}
.dm .dm-chk{display:flex;gap:10px;align-items:flex-start;font-size:15px;margin:0 0 10px;cursor:pointer;line-height:1.4}.dm .dm-chk input{width:20px;height:20px;margin-top:1px;accent-color:var(--acc);flex:none}
.dm .dm-act{border-top:1px solid var(--line);padding:12px 0}.dm .dm-act:first-child{border-top:0}.dm .dm-act .top{display:flex;justify-content:space-between;gap:8px;font-size:14px}.dm .dm-act .said{color:var(--muted);font-size:14px;margin:3px 0}
.dm .dm-res{font-weight:700;font-size:14px}.dm .dm-res.ok{color:#16a34a}.dm .dm-res.no{color:#b45309}
.dm .dm-banner{border-radius:14px;padding:12px 14px;font-weight:600;margin:0 0 14px;line-height:1.5}.dm .dm-banner.ok{background:#dcfce7;color:#166534}.dm .dm-banner.bad{background:#fee2e2;color:#991b1b}
.dm .dm-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.dm .dm-link{background:none;border:0;color:var(--acc);font-weight:700;cursor:pointer;padding:6px 0;font-size:14px}
.dm .dm-big{display:flex;justify-content:space-around;text-align:center;gap:10px}.dm .dm-big b{display:block;font-size:26px}.dm .dm-big span{color:var(--muted);font-size:13px;font-weight:600}
.dm .tl-go.ig{background:linear-gradient(135deg,#E1306C,#F77737)}
CSS;
}

function ir_dm_js() {
	return <<<'JS'
(function(){
var C=window.IRDM,app=document.getElementById('dmApp'),S=null,ED=null,MEDIA=null;
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function post(action,data){var f=new FormData();f.append('action',action);f.append('n',C.n);Object.keys(data||{}).forEach(function(k){f.append(k,data[k])});
 return fetch(C.aj,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json()}).catch(function(){return{ok:false,err:'No connection. Check your internet and try again.'}})}
function ago(t){var s=Math.floor(Date.now()/1000-t);if(s<60)return 'just now';if(s<3600)return Math.floor(s/60)+' min ago';if(s<86400)return Math.floor(s/3600)+' h ago';return new Date(t*1000).toLocaleDateString()}
var TPL=[
 {e:'🎁',n:'Free guide',d:'Comment GUIDE, get your free guide',kw:['GUIDE'],msg:'Hey {name}! Here is the free guide you asked for 👇\n{link}\n\nEnjoy, and tell me what you think!',btn:'Get the guide',pub:['Sent! Check your DMs 💌','Just sent it to you {name} 📩','Check your inbox 👀']},
 {e:'📅',n:'Book a call',d:'Comment BOOK, get your booking link',kw:['BOOK'],msg:'Hi {name}! So happy you want to work together. Pick a time that suits you here:\n{link}',btn:'Book now',pub:['Sent you the link {name} 📅','Check your DMs!']},
 {e:'💰',n:'Price list',d:'Comment PRICE, get your prices',kw:['PRICE','PRICES'],msg:'Hi {name}! Here are my prices and packages 👇\n{link}\n\nAny questions, just reply here.',btn:'See prices',pub:['Sent you the price list {name} 💌','Check your DMs!']},
 {e:'✏️',n:'Start blank',d:'Write your own from scratch',kw:[],msg:'Hey {name}! ',btn:'',pub:['Check your DMs {name} 💌']}
];
function blank(t){t=t||TPL[3];return{id:0,name:'',t:{comments:true,dms:false,story:false},any_post:false,post:'',post_img:'',keywords:t.kw.slice(),any_word:false,message:t.msg,link:'',button:t.btn,public_on:t.n!=='Start blank',public:t.pub.slice()}}

function load(){return post('ir_dm_state').then(function(r){if(!r.ok){app.innerHTML='<div class="tl-err">'+esc(r.err)+'</div>';return}S=r;render()})}

function banner(){var h='';if(C.msg)h+='<div class="dm-banner ok">'+esc(C.msg)+'</div>';if(C.err)h+='<div class="dm-banner bad">'+esc(C.err)+'</div>';C.msg=C.err='';return h}

function acctCard(){var a=S.acct;
 if(!S.ready&&!a)return '<div class="tl-card"><h2>Step 1: Connect your Instagram</h2><p class="tl-note">Instagram connection is opening soon. You can already create your automations below and test them with "Try it". They start working the moment you connect.</p></div>';
 if(!a)return '<div class="tl-card"><h2>Step 1: Connect your Instagram</h2><ol class="dm-steps"><li>Your Instagram must be a <b>Business</b> or <b>Creator</b> account (Instagram app: Settings › Account type and tools › Switch to professional account).</li><li>Tap the button below and log in to Instagram.</li><li>Tap <b>Allow</b>. You come straight back here.</li></ol><a class="tl-go ig" style="display:block;text-align:center;text-decoration:none" href="'+esc(C.connect)+'">Connect Instagram</a></div>';
 var bad=a.status!=='active';
 return '<div class="tl-card"><div class="dm-acct">'+(a.pic?'<img class="dm-av" src="'+esc(a.pic)+'" alt="">':'<span class="dm-av">'+esc((a.username||'?')[0].toUpperCase())+'</span>')+'<div style="flex:1"><b>@'+esc(a.username)+'</b>'+(bad?'<small style="color:#b91c1c;font-weight:700">Instagram logged you out. Reconnect to keep sending DMs.</small>':'<small><span class="dm-ok">● Connected</span> · automations are live</small>')+'</div>'+(bad?'<a class="tl-btn2" href="'+esc(C.connect)+'">Reconnect</a>':'<button class="dm-link" id="dmDisc">Disconnect</button>')+'</div>'+(!bad&&!a.subscribed?'<p class="tl-note" style="margin:12px 0 0">Almost there: tap <a href="'+esc(C.connect)+'">Reconnect</a> once so Instagram starts sending us your comments.</p>':'')+'</div>';
}

function autoCard(a){var c=a.cfg,when=[];if(c.t.comments)when.push('comments '+(c.any_word?'anything':'<b>'+esc(c.keywords.join(' or '))+'</b>')+(c.any_post?' on any post':' on this post'));if(c.t.dms)when.push('DMs '+(c.any_word?'you':'<b>'+esc(c.keywords.join(' or '))+'</b>'));if(c.t.story)when.push('replies to your Story');
 return '<div class="tl-card"><div class="dm-auto">'+(c.post_img&&!c.any_post?'<img class="dm-thumb" src="'+esc(c.post_img)+'" alt="">':'<span class="dm-thumb">💬</span>')+'<div class="tx"><b>'+esc(a.name)+'</b><small>When someone '+when.join(', or ')+'</small><div class="dm-nums">'+a.sent+' <span>DMs sent</span> '+a.clicks+' <span>link clicks</span></div></div><label class="dm-sw" title="On / off"><input type="checkbox" data-tog="'+a.id+'"'+(a.on?' checked':'')+'><i></i></label></div><div class="tl-acts"><button class="tl-btn2" data-edit="'+a.id+'">Edit</button><button class="tl-btn2" data-try="'+a.id+'">Try it</button><button class="dm-link" style="color:#b91c1c;margin-left:auto" data-del="'+a.id+'">Delete</button></div></div>';
}

function render(){ED=null;var h=banner()+acctCard();
 h+='<div class="dm-sec"><div class="dm-row" style="justify-content:space-between;margin:0 0 10px"><h2 style="margin:0">Your automations</h2>'+(S.autos.length?'<button class="tl-btn2" id="dmNew">+ New automation</button>':'')+'</div>';
 if(!S.autos.length){h+='<div class="tl-card"><p style="margin:0 0 12px;font-weight:700">Pick a starting point:</p><div class="dm-tpls">'+TPL.map(function(t,i){return '<button class="dm-tpl" data-tpl="'+i+'"><span class="e">'+t.e+'</span><b>'+esc(t.n)+'</b><small>'+esc(t.d)+'</small></button>'}).join('')+'</div></div>'}
 else{if(S.tot.sent)h+='<div class="tl-card dm-big"><div><b>'+S.tot.sent+'</b><span>DMs sent</span></div><div><b>'+S.tot.clicks+'</b><span>people clicked your link</span></div></div>';h+=S.autos.map(autoCard).join('')}
 h+='</div>';
 if(S.activity.length){h+='<div class="dm-sec"><h2>Recent activity</h2><div class="tl-card">'+S.activity.map(function(x){return '<div class="dm-act"><div class="top"><b>'+esc(x.who)+'</b><span style="color:var(--muted)">'+ago(x.when)+'</span></div><div class="said">'+(x.trig==='comment'?'Commented':x.trig==='story'?'Replied to your Story':'Sent you a DM')+': “'+esc(x.said||'…')+'” · '+esc(x.auto)+'</div><div class="dm-res '+(x.ok?'ok':'no')+'">'+(x.ok?'✓ ':'')+esc(x.res)+'</div></div>'}).join('')+'</div></div>'}
 h+='<details class="tl-more"><summary>How it works & Instagram rules</summary><div class="tl-note">• Instagram allows one automatic DM per comment, within 7 days of the comment.<br>• When someone DMs you or replies to your Story, you can reply within 24 hours.<br>• Your own comments never trigger anything.<br>• The DM arrives in their message requests if they don\'t follow you, that\'s normal.<br>• Links are tracked so you can see how many people clicked.</div></details>';
 app.innerHTML=h;bind();}

function bind(){var q=function(s){return app.querySelectorAll(s)};
 q('[data-tpl]').forEach(function(b){b.onclick=function(){edit(blank(TPL[+b.dataset.tpl]))}});
 var nb=app.querySelector('#dmNew');if(nb)nb.onclick=function(){chooseTpl()};
 q('[data-edit]').forEach(function(b){b.onclick=function(){var a=find(b.dataset.edit);var c=JSON.parse(JSON.stringify(a.cfg));c.id=a.id;c.name=a.name;edit(c)}});
 q('[data-try]').forEach(function(b){b.onclick=function(){var a=find(b.dataset.try);var c=JSON.parse(JSON.stringify(a.cfg));c.id=a.id;tryIt(c,b.closest('.tl-card'))}});
 q('[data-del]').forEach(function(b){b.onclick=function(){if(!confirm('Delete this automation? Its stats are deleted too.'))return;post('ir_dm_delete',{id:b.dataset.del}).then(load)}});
 q('[data-tog]').forEach(function(i){i.onchange=function(){post('ir_dm_toggle',{id:i.dataset.tog,on:i.checked?1:''}).then(function(r){if(!r.ok){alert(r.err);i.checked=!i.checked}})}});
 var d=app.querySelector('#dmDisc');if(d)d.onclick=function(){if(confirm('Disconnect your Instagram? Automations stop until you connect again.'))post('ir_dm_disconnect').then(load)};
}
function find(id){return S.autos.filter(function(a){return String(a.id)===String(id)})[0]}
function chooseTpl(){app.innerHTML='<div class="tl-card"><h2>New automation</h2><p style="margin:0 0 12px">Pick a starting point (you can change everything):</p><div class="dm-tpls">'+TPL.map(function(t,i){return '<button class="dm-tpl" data-tpl="'+i+'"><span class="e">'+t.e+'</span><b>'+esc(t.n)+'</b><small>'+esc(t.d)+'</small></button>'}).join('')+'</div><button class="dm-link" id="dmBack" style="margin-top:12px">← Back</button></div>';
 app.querySelectorAll('[data-tpl]').forEach(function(b){b.onclick=function(){edit(blank(TPL[+b.dataset.tpl]))}});app.querySelector('#dmBack').onclick=render;window.scrollTo(0,0)}

/* ---------- editor ---------- */
function edit(c){ED=c;window.scrollTo(0,0);
 var h='<nav class="pp-crumb" style="margin:0 0 12px"><button class="dm-link" id="dmBack">← All automations</button></nav>';
 h+='<div class="tl-card dm-step"><h3><span class="dm-num">1</span>Which post?</h3><div id="dmPosts"></div></div>';
 h+='<div class="tl-card dm-step"><h3><span class="dm-num">2</span>What word should they comment?</h3><label class="tl-f"><input id="dmKw" placeholder="e.g. GUIDE" value="'+esc(c.keywords.join(', '))+'" autocapitalize="characters"><small>Capital letters, emojis and dots don\'t matter. Add more words with a comma: GUIDE, LINK</small></label><label class="dm-chk"><input type="checkbox" id="dmAny"'+(c.any_word?' checked':'')+'><span>Reply to <b>every</b> comment (no word needed)</span></label></div>';
 h+='<div class="tl-card dm-step"><h3><span class="dm-num">3</span>The DM they get</h3><div><span class="dm-pill" data-ins="{name}">+ Their @name</span><span class="dm-pill" data-ins="{link}">+ Your link</span></div><label class="tl-f"><textarea id="dmMsg" rows="5" maxlength="1000">'+esc(c.message)+'</textarea></label><label class="tl-f"><span>Your link <em>(optional)</em></span><input id="dmLink" type="url" inputmode="url" placeholder="https://yourwebsite.com/offer" value="'+esc(c.link)+'"><small>Your guide, booking page, WhatsApp link, anything. We count the clicks for you.</small></label><label class="dm-chk"><input type="checkbox" id="dmBtnOn"'+(c.button?' checked':'')+'><span>Show the link as a button</span></label><label class="tl-f" id="dmBtnBox"'+(c.button?'':' hidden')+'><span>Button text</span><input id="dmBtn" maxlength="20" value="'+esc(c.button||'Open link')+'"></label></div>';
 h+='<div class="tl-card dm-step"><h3><span class="dm-num">4</span>Reply under their comment too?</h3><label class="dm-chk"><input type="checkbox" id="dmPubOn"'+(c.public_on?' checked':'')+'><span>Yes, also reply publicly (shows others it works, and gets more comments)</span></label><label class="tl-f" id="dmPubBox"'+(c.public_on?'':' hidden')+'><textarea id="dmPub" rows="3">'+esc(c.public.join('\n'))+'</textarea><small>One reply per line. We pick a different one each time so it looks natural.</small></label></div>';
 h+='<details class="tl-card tl-more"'+(c.t.dms||c.t.story||!c.t.comments?' open':'')+'><summary>More ways to trigger it (optional)</summary><label class="dm-chk"><input type="checkbox" id="dmTc"'+(c.t.comments?' checked':'')+'><span>When someone <b>comments</b> the word on my post</span></label><label class="dm-chk"><input type="checkbox" id="dmTd"'+(c.t.dms?' checked':'')+'><span>When someone <b>sends me a DM</b> with the word</span></label><label class="dm-chk"><input type="checkbox" id="dmTs"'+(c.t.story?' checked':'')+'><span>When someone <b>replies to my Story</b> with the word</span></label></details>';
 h+='<div class="tl-card"><h3 style="margin:0 0 10px;font-size:17px">Preview</h3><div class="dm-phone" id="dmPrev"></div></div>';
 h+='<label class="tl-f"><span>Name <em>(optional, just for you)</em></span><input id="dmName" placeholder="e.g. Free guide giveaway" value="'+esc(c.name||'')+'"></label>';
 h+='<div class="tl-err" id="dmErr" hidden></div><button class="tl-go ig" id="dmSave">'+(c.id?'Save changes':'Save and switch on')+'</button><div class="tl-acts" style="justify-content:center"><button class="tl-btn2" id="dmTry">Try it first</button></div><div id="dmTryOut"></div>';
 app.innerHTML=h;posts();
 var $=function(s){return app.querySelector(s)};
 $('#dmBack').onclick=function(){render()};
 app.querySelectorAll('[data-ins]').forEach(function(p){p.onclick=function(){var t=$('#dmMsg'),v=p.dataset.ins,s=t.selectionStart||t.value.length;t.value=t.value.slice(0,s)+v+t.value.slice(t.selectionEnd||s);t.focus();prev()}});
 $('#dmBtnOn').onchange=function(){$('#dmBtnBox').hidden=!this.checked;prev()};
 $('#dmPubOn').onchange=function(){$('#dmPubBox').hidden=!this.checked};
 ['#dmKw','#dmMsg','#dmLink','#dmBtn','#dmAny'].forEach(function(s){$(s).addEventListener('input',prev);$(s).addEventListener('change',prev)});
 $('#dmSave').onclick=save;$('#dmTry').onclick=function(){tryIt(collect(),null,$('#dmTryOut'))};prev();
}
function collect(){var $=function(s){return app.querySelector(s)},c=ED;
 c.keywords=$('#dmKw').value.split(',').map(function(s){return s.trim()}).filter(Boolean);c.any_word=$('#dmAny').checked;c.message=$('#dmMsg').value;c.link=$('#dmLink').value.trim();
 c.button=$('#dmBtnOn').checked?($('#dmBtn').value.trim()||'Open link'):'';c.public_on=$('#dmPubOn').checked;c.public=$('#dmPub').value.split('\n').map(function(s){return s.trim()}).filter(Boolean);
 c.t={comments:$('#dmTc').checked,dms:$('#dmTd').checked,story:$('#dmTs').checked};c.name=$('#dmName').value.trim();return c}
function prev(){var c=collect(),link=c.link?'https://ighoreuben.com/dmlink/…':'',btn=!!(c.link&&c.button);
 var m=c.message.replace(/\{name\}|\{username\}/gi,'@their_name').replace(/\{keyword\}/gi,c.keywords[0]||'');
 if(link&&!btn){m=/\{link\}/i.test(m)?m.replace(/\{link\}/gi,link):m+'\n'+link}else{m=m.replace(/[ \t]*\{link\}[ \t]*/gi,' ')}m=m.replace(/[ \t]+\n/g,'\n').replace(/\n{3,}/g,'\n\n')
 app.querySelector('#dmPrev').innerHTML='<div class="dm-in">'+esc(c.any_word?'Love this! 😍':(c.keywords[0]||'GUIDE'))+'</div><div class="dm-out">'+esc(m.trim()||'…')+(btn?'<div class="dm-bt">'+esc(c.button)+'</div>':'')+'</div>'}
function posts(){var box=app.querySelector('#dmPosts'),c=ED;
 var anyBtn='<button class="dm-post dm-any'+(c.any_post?' on':'')+'" data-any="1">🌐<br>Any post<br><small style="font-weight:600;color:var(--muted)">incl. new ones</small></button>';
 if(!S.acct){c.any_post=true;box.innerHTML='<div class="dm-grid">'+anyBtn.replace('dm-post dm-any','dm-post dm-any on')+'</div><small style="color:var(--muted)">Connect your Instagram to pick one specific post. For now it works on any post.</small>';return}
 box.innerHTML='<div class="tl-status"><span class="tl-spin"></span><span>Loading your posts…</span></div>';
 var draw=function(){box.innerHTML='<div class="dm-grid">'+anyBtn+(MEDIA||[]).map(function(m){return '<button class="dm-post'+(!c.any_post&&c.post===m.id?' on':'')+'" data-id="'+esc(m.id)+'" data-img="'+esc(m.img)+'" title="'+esc(m.cap)+'">'+(m.img?'<img src="'+esc(m.img)+'" alt="" loading="lazy">':'')+'</button>'}).join('')+'</div><small style="color:var(--muted)">Tap the post people will comment on.</small>';
  box.querySelectorAll('.dm-post').forEach(function(b){b.onclick=function(){if(b.dataset.any){c.any_post=true;c.post='';c.post_img=''}else{c.any_post=false;c.post=b.dataset.id;c.post_img=b.dataset.img}draw()}})};
 if(MEDIA)return draw();post('ir_dm_media').then(function(r){MEDIA=r.ok?r.media:[];if(!r.ok)box.innerHTML='<div class="tl-err">'+esc(r.err)+'</div>';else draw()})}
function save(){var c=collect(),b=app.querySelector('#dmSave'),e=app.querySelector('#dmErr');b.disabled=true;b.textContent='Saving…';e.hidden=true;
 post('ir_dm_save',{cfg:JSON.stringify(c)}).then(function(r){b.disabled=false;b.textContent=c.id?'Save changes':'Save and switch on';
  if(!r.ok){e.textContent=r.err;e.hidden=false;e.scrollIntoView({behavior:'smooth',block:'center'});return}
  C.msg=c.id?'Saved.':(S.acct?'Your automation is on! Comment the word from another account to see it work.':'Saved! It switches on as soon as you connect your Instagram.');load()})}

/* ---------- try it ---------- */
function tryIt(c,card,out){if(!out){out=document.createElement('div');card.appendChild(out)}
 out.innerHTML='<div class="tl-card" style="margin-top:12px;background:var(--soft)"><b>Try it</b><p style="margin:6px 0 10px;color:var(--muted);font-size:14px">Type a comment like your followers would. Nothing is sent.</p><div class="dm-row"><input class="dmTryIn" style="flex:1;min-width:0;font:inherit;font-size:16px;padding:12px;border:1px solid var(--line);border-radius:12px;background:var(--surface);color:var(--text)" value="'+esc((c.keywords[0]||'Love this')+' please! 🙏')+'"><button class="tl-btn2 dmTryGo">Test</button></div><div class="dmTryRes" style="margin-top:12px"></div></div>';
 var go=function(){var res=out.querySelector('.dmTryRes');res.innerHTML='<div class="tl-status"><span class="tl-spin"></span></div>';
  post('ir_dm_test',{cfg:JSON.stringify(c),text:out.querySelector('.dmTryIn').value}).then(function(r){if(!r.ok){res.innerHTML='<div class="tl-err">'+esc(r.err)+'</div>';return}
   if(!r.match){res.innerHTML='<div class="dm-res no">'+esc(r.why)+'</div>';return}
   res.innerHTML='<div class="dm-res ok" style="margin:0 0 8px">✓ This comment would get your DM:</div><div class="dm-phone"><div class="dm-out">'+esc(r.dm)+(r.button?'<div class="dm-bt">'+esc(r.button)+'</div>':'')+'</div></div>'+(r.pub?'<p style="margin:10px 0 0;font-size:14px"><b>Public reply:</b> '+esc(r.pub)+'</p>':'')})};
 out.querySelector('.dmTryGo').onclick=go;go()}

load();
})();
JS;
}

/* =========================================================================
 * Admin: Tools > DM Automation (one-time setup for the site owner)
 * ========================================================================= */

add_action( 'admin_menu', function () {
	add_management_page( 'DM Automation', 'DM Automation', 'manage_options', 'ir-dm', 'ir_dm_admin' );
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
	$o['graph'] = preg_match( '/^v\d+\.\d+$/', (string) ( $_POST['graph'] ?? '' ) ) ? (string) $_POST['graph'] : 'v25.0';
	$o['cap_private'] = max( 1, min( 750, (int) ( $_POST['cap_private'] ?? 750 ) ) );
	$o['cap_dm'] = max( 1, min( 1000, (int) ( $_POST['cap_dm'] ?? 200 ) ) );
	update_option( 'ir_dm_settings', $o, false );
	wp_safe_redirect( admin_url( 'tools.php?page=ir-dm&saved=1' ) ); exit;
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
	echo '<p>Members use it at <a href="' . esc_url( ir_pp_url( 'dm' ) ) . '" target="_blank">' . esc_html( ir_pp_url( 'dm' ) ) . '</a> (AI Profit System › AI Tools). This page is the one-time Meta setup.</p>';
	echo '<h2>Status</h2><table class="widefat striped" style="max-width:900px"><tbody>';
	echo '<tr><td>' . $ok( ir_dm_ready() ) . ' Meta app details saved</td><td>' . ( ir_dm_ready() ? 'Members can connect Instagram.' : 'Fill in the form below.' ) . '</td></tr>'; // phpcs:ignore
	echo '<tr><td>' . $ok( $last > 0 ) . ' Webhooks arriving from Meta</td><td>' . ( $last ? 'Last one ' . esc_html( human_time_diff( $last ) ) . ' ago' : 'None yet' ) . ( $bad && $bad > $last ? ' · <b style="color:#dc2626">A webhook was rejected (wrong app secret?) ' . esc_html( human_time_diff( $bad ) ) . ' ago</b>' : '' ) . '</td></tr>'; // phpcs:ignore
	echo '<tr><td>' . $ok( function_exists( 'as_enqueue_async_action' ) ) . ' Background queue (Action Scheduler)</td><td>Retries and delayed sends.</td></tr>'; // phpcs:ignore
	echo '<tr><td>Members connected</td><td>' . (int) $members . '</td></tr><tr><td>Active automations</td><td>' . (int) $autos . '</td></tr><tr><td>DMs sent (7 days)</td><td>' . (int) $sent7 . '</td></tr>';
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
	echo '<tr><th>Graph API version</th><td><input name="graph" value="' . esc_attr( ir_dm_graph_ver() ) . '" style="width:90px"></td></tr>';
	echo '<tr><th>Hourly limits per account</th><td>Comment DMs <input name="cap_private" type="number" value="' . (int) ir_dm_opt( 'cap_private', 750 ) . '" style="width:80px"> &nbsp; DM/Story replies <input name="cap_dm" type="number" value="' . (int) ir_dm_opt( 'cap_dm', 200 ) . '" style="width:80px"></td></tr>';
	echo '</tbody></table>';
	submit_button( 'Save' );
	echo '</form><p class="description">Secrets are stored encrypted with this site\'s security keys. If you ever change the keys in wp-config.php, re-enter the secrets and members reconnect Instagram.</p></div>';
}
