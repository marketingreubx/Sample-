<?php
/**
 * Plugin Name: IR Playbook Members
 * Description: Locked members area for The Cold DM Playbook at /playbook/. Access comes from a WooCommerce purchase, a verified Paystack payment on the landing page, or a manual grant under Users > Playbook Members.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'IR_PB_DIR', WP_CONTENT_DIR . '/ir-playbook' );
define( 'IR_PB_VER', '2' );

function ir_pb_page_id() { return (int) get_option( 'ir_pb_page_id' ); }
function ir_pb_product_id() { return (int) get_option( 'ir_pb_product_id' ); }
function ir_pb_url( $lesson = '' ) { return home_url( '/members/' . ( $lesson ? $lesson . '/' : '' ) ); }
/** On-site buy link: the normal shop product page (landing pages are kept for ads). */
function ir_pb_sales_url() { return ir_pb_product_id() ? get_permalink( ir_pb_product_id() ) : home_url( '/academy/' ); }
/** Landing page amounts in kobo: playbook alone, or with the "First Reply" bump. */
function ir_pb_amounts() { return [ 450000, 600000 ]; }

function ir_pb_data() {
	static $d = null;
	if ( $d === null ) {
		$f = IR_PB_DIR . '/playbook.json';
		$d = file_exists( $f ) ? json_decode( file_get_contents( $f ), true ) : [ 'sections' => [] ];
	}
	return $d;
}
function ir_pb_lessons() {
	$out = [];
	foreach ( ir_pb_data()['sections'] as $s ) {
		foreach ( $s['lessons'] as $l ) { $l['section'] = $s['title']; $l['skey'] = $s['key']; $out[] = $l; }
	}
	return $out;
}

/* ---------- access ---------- */

function ir_pb_has_access( $uid = 0 ) {
	$uid = $uid ?: get_current_user_id();
	if ( ! $uid ) { return false; }
	if ( user_can( $uid, 'manage_options' ) || get_user_meta( $uid, 'ir_pb_access', true ) ) { return true; }
	/* Bonus for courses that include the playbook (SMM Workshop). */
	if ( function_exists( 'tutor_utils' ) ) {
		foreach ( (array) get_option( 'ir_pb_bonus_courses', [] ) as $cid ) { if ( tutor_utils()->is_enrolled( (int) $cid, $uid ) ) { return true; } }
	}
	$pid = ir_pb_product_id();
	if ( $pid && function_exists( 'wc_customer_bought_product' ) ) {
		$u = get_userdata( $uid );
		if ( $u && wc_customer_bought_product( $u->user_email, $uid, $pid ) ) { ir_pb_grant( $uid, 'woo', false ); return true; }
	}
	return false;
}

function ir_pb_grant( $uid, $source, $notify = true, $set_link = false ) {
	$had = get_user_meta( $uid, 'ir_pb_access', true );
	update_user_meta( $uid, 'ir_pb_access', $had ?: time() );
	if ( ! $had ) { update_user_meta( $uid, 'ir_pb_source', $source ); }
	if ( $notify && ( ! $had || $set_link ) ) { ir_pb_mail_access( $uid, $set_link ); }
}

/** Find a user by email or create a customer account. Returns [user_id, created]. */
function ir_pb_user_for_email( $email, $first = '', $last = '' ) {
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) ) { return [ 0, false ]; }
	$u = get_user_by( 'email', $email );
	if ( $u ) { return [ $u->ID, false ]; }
	$base  = sanitize_user( strtolower( strstr( $email, '@', true ) ), true ) ?: 'member';
	$login = $base; $n = 1;
	while ( username_exists( $login ) ) { $login = $base . ( ++$n ); }
	$uid = wp_insert_user( [
		'user_login'   => $login,
		'user_email'   => $email,
		'user_pass'    => wp_generate_password( 24, true, true ),
		'first_name'   => sanitize_text_field( $first ),
		'last_name'    => sanitize_text_field( $last ),
		'display_name' => $first ? sanitize_text_field( $first ) : $login,
		'role'         => get_role( 'customer' ) ? 'customer' : 'subscriber',
	] );
	if ( is_wp_error( $uid ) ) { return [ 0, false ]; }
	update_user_meta( $uid, 'ir_pb_needs_pass', 1 );
	return [ $uid, true ];
}

function ir_pb_set_link( $uid ) {
	$u = get_userdata( $uid );
	$key = get_password_reset_key( $u );
	if ( is_wp_error( $key ) ) { return ir_pb_url(); }
	return add_query_arg( [ 'pb_set' => $key, 'u' => rawurlencode( $u->user_login ) ], ir_pb_url() );
}

function ir_pb_mail( $to, $subject, $inner ) {
	$html = '<div style="background:#F1F5FF;padding:32px 16px;font-family:Arial,Helvetica,sans-serif"><div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;padding:32px 28px;color:#0A1633">'
		. '<div style="font-weight:800;font-size:20px;margin-bottom:20px">Igho Reuben<span style="color:#1660E8">.</span></div>' . $inner
		. '<p style="margin:28px 0 0;color:#4F5D78;font-size:13px">Questions? Reply on WhatsApp: +234 810 469 9214</p></div></div>';
	return wp_mail( $to, $subject, $html, [ 'Content-Type: text/html; charset=UTF-8' ] );
}
function ir_pb_btn( $url, $label ) {
	return '<p style="margin:24px 0"><a href="' . esc_url( $url ) . '" style="display:inline-block;background:#1660E8;color:#fff;text-decoration:none;font-weight:700;padding:14px 22px;border-radius:12px">' . esc_html( $label ) . '</a></p>';
}
function ir_pb_mail_access( $uid, $set_link = false ) {
	$u = get_userdata( $uid );
	if ( ! $u ) { return; }
	$name = $u->first_name ?: '';
	$needs = $set_link || get_user_meta( $uid, 'ir_pb_needs_pass', true );
	$inner = '<h1 style="font-size:24px;margin:0 0 12px">Your Cold DM Playbook is ready' . ( $name ? ', ' . esc_html( $name ) : '' ) . '</h1>'
		. '<p style="font-size:16px;line-height:1.6;color:#4F5D78">Everything in the playbook lives in your private dashboard: the 7 scripts, objection replies, follow-ups, the discovery call script and the bonuses.</p>';
	if ( $needs ) {
		$inner .= '<p style="font-size:16px;line-height:1.6;color:#4F5D78">Create your password to open it. Your login email is <b>' . esc_html( $u->user_email ) . '</b>.</p>' . ir_pb_btn( ir_pb_set_link( $uid ), 'Create password and open playbook' );
	} else {
		$inner .= '<p style="font-size:16px;line-height:1.6;color:#4F5D78">Log in with <b>' . esc_html( $u->user_email ) . '</b>.</p>' . ir_pb_btn( ir_pb_url(), 'Open my playbook' );
	}
	ir_pb_mail( $u->user_email, 'Your Cold DM Playbook access', $inner );
}

/* ---------- Paystack ---------- */

function ir_pb_ps_secret() {
	$s = get_option( 'woocommerce_paystack_settings' );
	if ( ! is_array( $s ) ) { return ''; }
	return ( ( $s['testmode'] ?? 'no' ) === 'yes' ) ? ( $s['test_secret_key'] ?? '' ) : ( $s['live_secret_key'] ?? '' );
}
function ir_pb_ps_get( $path ) {
	$r = wp_remote_get( 'https://api.paystack.co' . $path, [ 'timeout' => 20, 'headers' => [ 'Authorization' => 'Bearer ' . ir_pb_ps_secret() ] ] );
	if ( is_wp_error( $r ) ) { return null; }
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	return ( is_array( $j ) && ! empty( $j['status'] ) ) ? $j['data'] : null;
}
/** A successful landing-page payment for the playbook. */
function ir_pb_tx_ok( $tx ) {
	if ( ! is_array( $tx ) || ( $tx['status'] ?? '' ) !== 'success' || ( $tx['currency'] ?? '' ) !== 'NGN' ) { return false; }
	$meta = is_array( $tx['metadata'] ?? null ) ? $tx['metadata'] : [];
	if ( ( $meta['product'] ?? '' ) === 'colddm' ) { return true; }
	if ( ! empty( $meta['order_id'] ) || ! empty( $meta['product'] ) ) { return false; }
	return in_array( (int) $tx['amount'], ir_pb_amounts(), true );
}

function ir_pb_rate_ok( $what, $max ) {
	$k = 'ir_pb_rl_' . $what . '_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
	$n = (int) get_transient( $k );
	if ( $n >= $max ) { return false; }
	set_transient( $k, $n + 1, HOUR_IN_SECONDS );
	return true;
}

/** Record a verified playbook payment once: create or find the buyer, give access, email the login link. */
function ir_pb_accept_tx( $ref, $tx ) {
	$used = get_option( 'ir_pb_refs', [] );
	if ( isset( $used[ $ref ] ) ) { return (int) $used[ $ref ]; }
	$c = $tx['customer'] ?? [];
	list( $uid, $created ) = ir_pb_user_for_email( $c['email'] ?? '', $c['first_name'] ?? '', $c['last_name'] ?? '' );
	if ( ! $uid ) { return 0; }
	$used[ $ref ] = $uid;
	update_option( 'ir_pb_refs', $used, false );
	if ( $created ) {
		$p = get_option( 'ir_pb_autologin', [] );
		$p[ $ref ] = time();
		update_option( 'ir_pb_autologin', $p, false );
	}
	ir_pb_grant( $uid, 'paystack:' . $ref );
	return $uid;
}

/* Thank-you page: confirm the reference, then log a brand-new buyer straight in (once per payment). */
function ir_pb_ajax_claim() {
	$ref = sanitize_text_field( wp_unslash( $_POST['ref'] ?? '' ) );
	if ( ! $ref || ! ir_pb_rate_ok( 'claim', 40 ) ) { wp_send_json( [ 'ok' => false, 'state' => 'error' ] ); }
	$used = get_option( 'ir_pb_refs', [] );
	if ( ! isset( $used[ $ref ] ) ) {
		$tx = ir_pb_ps_get( '/transaction/verify/' . rawurlencode( $ref ) );
		if ( ! ir_pb_tx_ok( $tx ) || ! ir_pb_accept_tx( $ref, $tx ) ) { wp_send_json( [ 'ok' => false, 'state' => 'unverified' ] ); }
		$used = get_option( 'ir_pb_refs', [] );
	}
	$uid = (int) $used[ $ref ];
	$p = get_option( 'ir_pb_autologin', [] );
	if ( isset( $p[ $ref ] ) ) {
		$fresh = time() - (int) $p[ $ref ] < DAY_IN_SECONDS;
		unset( $p[ $ref ] );
		update_option( 'ir_pb_autologin', $p, false );
		if ( $fresh ) {
			wp_set_current_user( $uid );
			wp_set_auth_cookie( $uid, true );
			wp_send_json( [ 'ok' => true, 'state' => 'ready', 'url' => ir_pb_url() ] );
		}
	}
	$u = get_userdata( $uid );
	wp_send_json( [ 'ok' => true, 'state' => get_current_user_id() === $uid ? 'ready' : 'login', 'url' => ir_pb_url(), 'email' => $u ? $u->user_email : '' ] );
}

/* Paystack webhook (the URL the WooCommerce Paystack plugin uses). Works even when the API blocks this server's IP. */
add_action( 'woocommerce_api_tbz_wc_paystack_webhook', function () {
	$sig = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
	$json = file_get_contents( 'php://input' );
	$secret = ir_pb_ps_secret();
	if ( ! $sig || ! $json || ! $secret || ! hash_equals( hash_hmac( 'sha512', $json, $secret ), $sig ) ) { return; }
	$ev = json_decode( $json, true );
	if ( ( $ev['event'] ?? '' ) !== 'charge.success' || empty( $ev['data']['reference'] ) ) { return; }
	if ( ir_pb_tx_ok( $ev['data'] ) ) { ir_pb_accept_tx( sanitize_text_field( $ev['data']['reference'] ), $ev['data'] ); }
}, 1 );
add_action( 'wp_ajax_ir_pb_claim', 'ir_pb_ajax_claim' );
add_action( 'wp_ajax_nopriv_ir_pb_claim', 'ir_pb_ajax_claim' );

/* Earlier buyers: look up their payments by email and send an access link. Never logs anyone in. */
function ir_pb_claim_by_email( $email ) {
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) ) { return; }
	$u = get_user_by( 'email', $email );
	if ( $u && ir_pb_has_access( $u->ID ) ) { ir_pb_mail_access( $u->ID, true ); return; }
	$found = false;
	if ( function_exists( 'wc_get_orders' ) && ir_pb_product_id() ) {
		foreach ( wc_get_orders( [ 'billing_email' => $email, 'status' => [ 'processing', 'completed' ], 'limit' => 20 ] ) as $o ) {
			foreach ( $o->get_items() as $it ) { if ( (int) $it->get_product_id() === ir_pb_product_id() ) { $found = true; } }
		}
	}
	if ( ! $found ) {
		$cust = ir_pb_ps_get( '/customer/' . rawurlencode( $email ) );
		if ( ! empty( $cust['id'] ) ) {
			$list = ir_pb_ps_get( '/transaction?status=success&perPage=100&customer=' . (int) $cust['id'] );
			foreach ( (array) $list as $tx ) { if ( ir_pb_tx_ok( $tx ) ) { $found = true; break; } }
		}
	}
	if ( ! $found ) { return; }
	list( $uid ) = ir_pb_user_for_email( $email, $cust['first_name'] ?? '', $cust['last_name'] ?? '' );
	if ( $uid ) { ir_pb_grant( $uid, 'email-claim', true, true ); }
}

/* ---------- WooCommerce ---------- */

function ir_pb_order_has_product( $order ) {
	$pid = ir_pb_product_id();
	if ( ! $pid || ! $order ) { return false; }
	foreach ( $order->get_items() as $it ) { if ( (int) $it->get_product_id() === $pid ) { return true; } }
	return false;
}
function ir_pb_on_paid( $order_id ) {
	$o = wc_get_order( $order_id );
	if ( ! ir_pb_order_has_product( $o ) ) { return; }
	$uid = $o->get_user_id();
	if ( ! $uid ) { list( $uid ) = ir_pb_user_for_email( $o->get_billing_email(), $o->get_billing_first_name(), $o->get_billing_last_name() ); }
	if ( $uid ) { ir_pb_grant( $uid, 'woo:' . $order_id ); }
}
add_action( 'woocommerce_order_status_processing', 'ir_pb_on_paid' );
add_action( 'woocommerce_order_status_completed', 'ir_pb_on_paid' );
add_action( 'woocommerce_thankyou', function ( $order_id ) {
	$o = wc_get_order( $order_id );
	if ( ! ir_pb_order_has_product( $o ) || ! $o->is_paid() ) { return; }
	echo '<div style="margin:0 0 28px;padding:24px;border-radius:16px;background:#EEF3FF;border:1px solid #D6E2FF"><h2 style="margin:0 0 8px">Your Cold DM Playbook is unlocked</h2><p style="margin:0 0 16px">Open it any time from your private dashboard.</p><a href="' . esc_url( ir_pb_url() ) . '" style="display:inline-block;background:#1660E8;color:#fff;padding:14px 22px;border-radius:12px;font-weight:700;text-decoration:none">Open my playbook →</a></div>';
}, 1 );
add_filter( 'woocommerce_account_menu_items', function ( $items ) {
	if ( ! ir_pb_has_access() ) { return $items; }
	return array_slice( $items, 0, 1, true ) + [ 'ir-playbook' => 'My Dashboard' ] + array_slice( $items, 1, null, true );
} );
add_filter( 'woocommerce_get_endpoint_url', function ( $url, $endpoint ) {
	return $endpoint === 'ir-playbook' ? ir_pb_url() : $url;
}, 10, 2 );

/* ---------- progress ---------- */

add_action( 'wp_ajax_ir_pb_progress', function () {
	check_ajax_referer( 'ir_pb', 'n' );
	if ( ! ir_pb_has_access() ) { wp_send_json( [ 'ok' => false ] ); }
	$slug = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) );
	$valid = wp_list_pluck( ir_pb_lessons(), 'slug' );
	if ( ! in_array( $slug, $valid, true ) ) { wp_send_json( [ 'ok' => false ] ); }
	$uid  = get_current_user_id();
	$done = array_values( array_intersect( (array) get_user_meta( $uid, 'ir_pb_done', true ), $valid ) );
	$done = ! empty( $_POST['done'] ) ? array_values( array_unique( array_merge( $done, [ $slug ] ) ) ) : array_values( array_diff( $done, [ $slug ] ) );
	update_user_meta( $uid, 'ir_pb_done', $done );
	update_user_meta( $uid, 'ir_pb_last', $slug );
	wp_send_json( [ 'ok' => true, 'done' => count( $done ), 'total' => count( $valid ) ] );
} );

/* Search index, loaded the first time a member opens search. */
add_action( 'wp_ajax_ir_pb_index', function () {
	check_ajax_referer( 'ir_pb', 'n' );
	if ( ! ir_pb_has_access() ) { wp_send_json( [] ); }
	$out = [];
	foreach ( ir_pb_lessons() as $l ) {
		$h = preg_replace( '#<button.*?</button>#s', ' ', $l['html'] );
		$t = html_entity_decode( wp_strip_all_tags( str_replace( [ '</p>', '</li>', '</h2>', '</h3>', '</td>', '<br>', '</div>' ], ' ', $h ) ), ENT_QUOTES, 'UTF-8' );
		$out[] = [ 's' => $l['slug'], 't' => $l['title'], 'i' => $l['icon'], 'g' => $l['section'], 'x' => trim( preg_replace( '/\s+/u', ' ', $t ) ) ];
	}
	wp_send_json( $out );
} );

/* Saved lessons. */
add_action( 'wp_ajax_ir_pb_save', function () {
	check_ajax_referer( 'ir_pb', 'n' );
	if ( ! ir_pb_has_access() ) { wp_send_json( [ 'ok' => false ] ); }
	$slug  = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) );
	$valid = wp_list_pluck( ir_pb_lessons(), 'slug' );
	if ( ! in_array( $slug, $valid, true ) ) { wp_send_json( [ 'ok' => false ] ); }
	$uid   = get_current_user_id();
	$saved = array_values( array_intersect( (array) get_user_meta( $uid, 'ir_pb_saved', true ), $valid ) );
	$saved = ! empty( $_POST['on'] ) ? array_values( array_unique( array_merge( [ $slug ], $saved ) ) ) : array_values( array_diff( $saved, [ $slug ] ) );
	update_user_meta( $uid, 'ir_pb_saved', $saved );
	wp_send_json( [ 'ok' => true, 'saved' => $saved ] );
} );

/* Tracker + writer data (one JSON blob per member). */
add_action( 'wp_ajax_ir_pb_trk', function () {
	check_ajax_referer( 'ir_pb', 'n' );
	if ( ! ir_pb_has_access() ) { wp_send_json( [ 'ok' => false ] ); }
	$raw = (string) wp_unslash( $_POST['data'] ?? '' );
	if ( strlen( $raw ) > 400000 ) { wp_send_json( [ 'ok' => false, 'error' => 'too big' ] ); }
	$d = json_decode( $raw, true );
	if ( ! is_array( $d ) ) { wp_send_json( [ 'ok' => false ] ); }
	update_user_meta( get_current_user_id(), 'ir_pb_trk', $d );
	wp_send_json( [ 'ok' => true ] );
} );

/** Everything a member owns: Tutor courses they are enrolled in and WooCommerce downloads. */
function ir_pb_library( $uid ) {
	$out = [ 'courses' => [], 'downloads' => [] ];
	if ( function_exists( 'tutor_utils' ) && method_exists( tutor_utils(), 'get_enrolled_courses_ids_by_user' ) ) {
		foreach ( (array) tutor_utils()->get_enrolled_courses_ids_by_user( $uid ) as $cid ) {
			if ( get_post_status( $cid ) !== 'publish' ) { continue; }
			$out['courses'][] = [ 't' => get_the_title( $cid ), 'url' => get_permalink( $cid ), 'img' => get_the_post_thumbnail_url( $cid, 'medium' ), 'pct' => (int) tutor_utils()->get_course_completed_percent( $cid, $uid ) ];
		}
	}
	if ( function_exists( 'wc_get_customer_available_downloads' ) ) {
		foreach ( wc_get_customer_available_downloads( $uid ) as $dl ) {
			$out['downloads'][] = [ 't' => preg_replace( '/\s*\(.*$/s', '', $dl['product_name'] ), 'f' => $dl['download_name'] ?? '', 'url' => $dl['download_url'], 'img' => get_the_post_thumbnail_url( $dl['product_id'], 'medium' ) ];
		}
	}
	return $out;
}

/* ?add-to-cart=ID&coupon=CODE applies the code automatically (remembered until the item is in the cart). */
add_action( 'wp_loaded', function () {
	if ( empty( $_GET['coupon'] ) || is_admin() || ! function_exists( 'WC' ) || ! WC()->session ) { return; }
	if ( ! WC()->session->has_session() ) { WC()->session->set_customer_session_cookie( true ); }
	WC()->session->set( 'ir_pending_coupon', wc_format_coupon_code( wp_unslash( $_GET['coupon'] ) ) );
}, 5 );
function ir_pb_apply_pending_coupon() {
	if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart || WC()->cart->is_empty() ) { return; }
	$code = WC()->session->get( 'ir_pending_coupon' );
	if ( ! $code ) { return; }
	WC()->session->set( 'ir_pending_coupon', null );
	if ( ! WC()->cart->has_discount( $code ) ) { WC()->cart->apply_coupon( $code ); }
}
add_action( 'woocommerce_add_to_cart', 'ir_pb_apply_pending_coupon', 20 );
add_action( 'wp_loaded', 'ir_pb_apply_pending_coupon', 30 );

/* ---------- routing ---------- */

add_action( 'init', function () {
	add_rewrite_rule( '^members/([a-z0-9-]+)/?$', 'index.php?pagename=members&ir_pb_l=$matches[1]', 'top' );
	if ( get_option( 'ir_pb_rewrite' ) !== IR_PB_VER ) { flush_rewrite_rules( false ); update_option( 'ir_pb_rewrite', IR_PB_VER ); }
} );
add_filter( 'query_vars', function ( $v ) { $v[] = 'ir_pb_l'; return $v; } );
/* The hub moved from /playbook/ to /members/. Old links keep working. */
add_action( 'template_redirect', function () {
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( ! preg_match( '#^playbook(?:/([a-z0-9-]+))?/?$#', $path, $m ) ) { return; }
	$q = $_SERVER['QUERY_STRING'] ?? '';
	wp_safe_redirect( ir_pb_url( $m[1] ?? '' ) . ( $q ? '?' . $q : '' ), 301 ); exit;
}, 0 );
add_filter( 'wp_sitemaps_posts_query_args', function ( $a ) {
	if ( ir_pb_page_id() ) { $a['post__not_in'] = array_merge( (array) ( $a['post__not_in'] ?? [] ), [ ir_pb_page_id() ] ); }
	return $a;
} );

add_action( 'template_redirect', function () {
	if ( ! ir_pb_page_id() || ! is_page( ir_pb_page_id() ) ) { return; }
	nocache_headers();
	do_action( 'litespeed_control_set_nocache', 'members area' );
	header( 'X-Robots-Tag: noindex, nofollow', true );

	$msg = ''; $err = ''; $view = '';
	$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
	$act = sanitize_key( $_POST['pb_act'] ?? '' );

	if ( $method === 'POST' && $act === 'login' ) {
		$login = sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) );
		if ( is_email( $login ) && ( $by = get_user_by( 'email', $login ) ) ) { $login = $by->user_login; }
		$u = wp_signon( [ 'user_login' => $login, 'user_password' => (string) wp_unslash( $_POST['pwd'] ?? '' ), 'remember' => true ], is_ssl() );
		if ( is_wp_error( $u ) && $u->get_error_code() === 'tutor_login_limit' && ( $lu = get_user_by( 'login', $login ) ) ) {
			// Right password, but Tutor's one-device limit is full. Offer to sign the other device out.
			$tok = wp_generate_password( 32, false );
			set_transient( 'ir_pb_force_' . $tok, $lu->ID, 10 * MINUTE_IN_SECONDS );
			$GLOBALS['ir_pb_force'] = $tok;
			$err = 'You are already logged in on another device. Your account can be open on one device at a time.'; $view = 'login';
		} elseif ( is_wp_error( $u ) ) { $err = 'That email and password did not match. Try again or reset your password.'; $view = 'login'; }
		else { wp_safe_redirect( ir_pb_url() ); exit; }
	}
	if ( $method === 'POST' && $act === 'force' ) {
		$tok = sanitize_key( wp_unslash( $_POST['tok'] ?? '' ) );
		$uid = $tok ? (int) get_transient( 'ir_pb_force_' . $tok ) : 0;
		if ( $uid ) {
			delete_transient( 'ir_pb_force_' . $tok );
			if ( class_exists( '\\TUTOR_PRO\\DeviceManagement' ) ) {
				\TUTOR_PRO\DeviceManagement::clear_all_sessions_by_user_id( $uid );
				\TUTOR_PRO\DeviceManagement::add_new_login_device( $uid );
			}
			WP_Session_Tokens::get_instance( $uid )->destroy_all();
			wp_set_current_user( $uid ); wp_set_auth_cookie( $uid, true );
			wp_safe_redirect( ir_pb_url() ); exit;
		}
		$err = 'That took too long. Log in again.'; $view = 'login';
	}
	if ( $method === 'POST' && $act === 'forgot' ) {
		if ( ir_pb_rate_ok( 'forgot', 8 ) ) {
			$e = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
			$u = get_user_by( 'email', $e );
			if ( $u ) { ir_pb_mail( $u->user_email, 'Reset your playbook password', '<h1 style="font-size:22px;margin:0 0 12px">Reset your password</h1><p style="font-size:16px;line-height:1.6;color:#4F5D78">Tap below to choose a new password for <b>' . esc_html( $u->user_email ) . '</b>. The link works once.</p>' . ir_pb_btn( ir_pb_set_link( $u->ID ), 'Choose a new password' ) ); }
		}
		$msg = 'If that email has an account, a reset link is on its way. Check your inbox and spam folder.'; $view = 'login';
	}
	if ( $method === 'POST' && $act === 'claim' ) {
		if ( ir_pb_rate_ok( 'claimmail', 6 ) ) { ir_pb_claim_by_email( wp_unslash( $_POST['email'] ?? '' ) ); }
		$msg = 'If that email paid for the playbook, an access link is on its way. Check your inbox and spam folder. Still stuck? Message me on WhatsApp.'; $view = 'login';
	}
	if ( ! empty( $_GET['pb_set'] ) ) {
		$u = check_password_reset_key( sanitize_text_field( wp_unslash( $_GET['pb_set'] ) ), sanitize_user( wp_unslash( $_GET['u'] ?? '' ) ) );
		if ( is_wp_error( $u ) ) { $err = 'That link has expired or was already used. Request a new one below.'; $view = 'login'; }
		elseif ( $method === 'POST' && $act === 'setpass' ) {
			$p = (string) wp_unslash( $_POST['pass'] ?? '' );
			if ( strlen( $p ) < 8 ) { $err = 'Use at least 8 characters.'; $view = 'set'; }
			else {
				reset_password( $u, $p );
				delete_user_meta( $u->ID, 'ir_pb_needs_pass' );
				wp_set_current_user( $u->ID ); wp_set_auth_cookie( $u->ID, true );
				wp_safe_redirect( add_query_arg( 'welcome', '1', ir_pb_url() ) ); exit;
			}
		} else { $view = 'set'; }
		if ( $view === 'set' ) { $GLOBALS['ir_pb_set_user'] = $u; }
	}
	if ( $method === 'POST' && $act === 'newpass' && is_user_logged_in() ) {
		check_admin_referer( 'ir_pb_newpass' );
		$p = (string) wp_unslash( $_POST['pass'] ?? '' );
		if ( strlen( $p ) >= 8 ) {
			$uid = get_current_user_id();
			wp_set_password( $p, $uid );
			delete_user_meta( $uid, 'ir_pb_needs_pass' );
			wp_set_current_user( $uid ); wp_set_auth_cookie( $uid, true );
			wp_safe_redirect( add_query_arg( 'saved', '1', ir_pb_url( get_query_var( 'ir_pb_l' ) ) ) ); exit;
		}
		$err = 'Use at least 8 characters.';
	}
	if ( isset( $_GET['logout'] ) && is_user_logged_in() ) {
		check_admin_referer( 'ir_pb_logout' );
		wp_logout(); wp_safe_redirect( ir_pb_url() ); exit;
	}

	if ( ! $view ) {
		if ( ! is_user_logged_in() ) { $view = 'login'; }
		elseif ( ! ir_pb_has_access() ) { $lib = ir_pb_library( get_current_user_id() ); $view = ( $lib['courses'] || $lib['downloads'] ) ? 'app' : 'noaccess'; }
		else { $view = 'app'; }
	}
	ir_pb_render( $view, $msg, $err );
	exit;
}, 1 );

/* One dashboard: Tutor's student dashboard home (and enrolled-courses list) open the member hub instead.
   Instructors and admins keep Tutor's dashboard; course pages, settings and quizzes are untouched. */
add_action( 'template_redirect', function () {
	if ( ! function_exists( 'tutor_utils' ) || ! ir_pb_page_id() ) { return; }
	$dash = (int) tutor_utils()->get_option( 'tutor_dashboard_page_id' );
	if ( ! $dash || ! is_page( $dash ) ) { return; }
	$sub = (string) get_query_var( 'tutor_dashboard_page' );
	if ( $sub !== '' && ! in_array( $sub, [ 'index', 'enrolled-courses' ], true ) ) { return; }
	if ( is_user_logged_in() && ( current_user_can( 'manage_options' ) || tutor_utils()->is_instructor( get_current_user_id(), true ) ) ) { return; }
	wp_safe_redirect( ir_pb_url() ); exit;
}, 4 );

/* ---------- admin ---------- */

add_action( 'admin_menu', function () {
	add_users_page( 'Playbook Members', 'Playbook Members', 'manage_options', 'ir-playbook', 'ir_pb_admin' );
} );
function ir_pb_admin() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$note = '';
	if ( ! empty( $_POST['ir_pb_grant'] ) && check_admin_referer( 'ir_pb_admin' ) ) {
		list( $uid, $created ) = ir_pb_user_for_email( wp_unslash( $_POST['email'] ), wp_unslash( $_POST['first'] ?? '' ) );
		if ( $uid ) { ir_pb_grant( $uid, 'manual', true, true ); $note = 'Access granted and an email with the login link was sent' . ( $created ? ' (new account created).' : '.' ); }
		else { $note = 'Enter a valid email.'; }
	}
	if ( ! empty( $_GET['revoke'] ) && check_admin_referer( 'ir_pb_revoke' ) ) {
		delete_user_meta( (int) $_GET['revoke'], 'ir_pb_access' ); $note = 'Access removed.';
	}
	$users = get_users( [ 'meta_key' => 'ir_pb_access', 'orderby' => 'registered', 'order' => 'DESC', 'number' => 500 ] );
	$total = count( ir_pb_lessons() );
	echo '<div class="wrap"><h1>Cold DM Playbook members</h1>';
	if ( $note ) { echo '<div class="notice notice-success"><p>' . esc_html( $note ) . '</p></div>'; }
	echo '<p>Buyers get access automatically after paying on the <a href="' . esc_url( ir_pb_sales_url() ) . '" target="_blank">landing page</a> or through the <a href="' . esc_url( get_permalink( ir_pb_product_id() ) ) . '" target="_blank">shop product</a>. Use the form to add someone by hand. Dashboard: <a href="' . esc_url( ir_pb_url() ) . '" target="_blank">' . esc_html( ir_pb_url() ) . '</a></p>';
	echo '<form method="post" style="margin:16px 0 24px;display:flex;gap:8px;flex-wrap:wrap">';
	wp_nonce_field( 'ir_pb_admin' );
	echo '<input type="email" name="email" placeholder="buyer@email.com" required class="regular-text"><input type="text" name="first" placeholder="First name (optional)"><button class="button button-primary" name="ir_pb_grant" value="1">Give access + email login link</button></form>';
	echo '<table class="widefat striped"><thead><tr><th>Member</th><th>Email</th><th>Since</th><th>Source</th><th>Progress</th><th></th></tr></thead><tbody>';
	foreach ( $users as $u ) {
		$done = count( (array) get_user_meta( $u->ID, 'ir_pb_done', true ) );
		$rev = wp_nonce_url( add_query_arg( [ 'page' => 'ir-playbook', 'revoke' => $u->ID ], admin_url( 'users.php' ) ), 'ir_pb_revoke' );
		echo '<tr><td>' . esc_html( $u->display_name ) . '</td><td>' . esc_html( $u->user_email ) . '</td><td>' . esc_html( date_i18n( 'j M Y', (int) get_user_meta( $u->ID, 'ir_pb_access', true ) ) ) . '</td><td>' . esc_html( get_user_meta( $u->ID, 'ir_pb_source', true ) ) . '</td><td>' . (int) $done . ' / ' . (int) $total . '</td><td><a href="' . esc_url( $rev ) . '" onclick="return confirm(\'Remove access?\')">Remove</a></td></tr>';
	}
	if ( ! $users ) { echo '<tr><td colspan="6">No members yet.</td></tr>'; }
	echo '</tbody></table></div>';
}

/* ---------- views ---------- */

if ( file_exists( IR_PB_DIR . '/view.php' ) ) { require_once IR_PB_DIR . '/view.php'; }
if ( ! function_exists( 'ir_pb_render' ) ) { function ir_pb_render() { wp_die( 'The playbook is being updated. Please try again in a minute.' ); } }
