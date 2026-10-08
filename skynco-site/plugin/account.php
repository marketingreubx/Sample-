<?php
/**
 * Skyn&Co. client dashboard: [skynco_account] on /account/.
 *
 * Passwordless sign-in: the client enters an email and gets a one-time link.
 * Clicking it signs them in (creating a WordPress account on first use) and links
 * any past LatePoint bookings and WooCommerce guest orders made with that email.
 *
 * @package SkyncoStudio
 */

defined( 'ABSPATH' ) || exit;

/** Weeks between visits before we nudge a rebook, by keyword in the service name. */
function skynco_rebook_weeks( $service_name ) {
	$n = strtolower( $service_name );
	foreach ( [ 'wax' => 4, 'brow' => 4, 'lash' => 6, 'microneedling' => 6, 'peel' => 6, 'derma' => 4, 'facial' => 5 ] as $k => $w ) {
		if ( false !== strpos( $n, $k ) ) {
			return $w;
		}
	}
	return 5;
}

/** Visits needed to earn the loyalty treat, and what it is. */
function skynco_loyalty() {
	return [ 6, 'a complimentary LED Light Therapy add-on' ];
}

function skynco_account_url( $tab = '' ) {
	return home_url( '/account/' . ( $tab ? $tab . '/' : '' ) );
}

/* ---------------------------------------------------------------------------
 * Magic-link sign-in
 * ------------------------------------------------------------------------ */
add_action( 'admin_post_nopriv_skynco_magic', 'skynco_send_magic_link' );
add_action( 'admin_post_skynco_magic', 'skynco_send_magic_link' );
function skynco_send_magic_link() {
	$back = skynco_account_url();
	if ( ! isset( $_POST['_sknonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_sknonce'] ), 'skynco_magic' ) ) {
		wp_safe_redirect( add_query_arg( 'sk', 'expired', $back ) );
		exit;
	}
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	if ( ! is_email( $email ) ) {
		wp_safe_redirect( add_query_arg( 'sk', 'bad-email', $back ) );
		exit;
	}
	$throttle = 'skynco_ml_wait_' . md5( strtolower( $email ) );
	if ( ! get_transient( $throttle ) ) {
		set_transient( $throttle, 1, MINUTE_IN_SECONDS );
		$link = skynco_magic_link_url( $email, 2 * DAY_IN_SECONDS );
		$body = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#2A1A24">'
			. '<h2 style="font-family:Georgia,serif;color:#3B1530;font-weight:500">Your Skyn&amp;Co. sign-in link</h2>'
			. '<p>Tap the button below to open your client dashboard. The link works for 48 hours on any device.</p>'
			. '<p style="margin:28px 0"><a href="' . esc_url( $link ) . '" style="background:#D1127E;color:#fff;text-decoration:none;padding:14px 26px;border-radius:999px;font-weight:bold">Open my dashboard</a></p>'
			. '<p style="font-size:13px;color:#6E5A66">If you didn’t ask for this, you can ignore this email.</p></div>';
		wp_mail( $email, 'Your Skyn&Co. sign-in link', $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
	}
	wp_safe_redirect( add_query_arg( [ 'sk' => 'sent', 'e' => rawurlencode( $email ) ], $back ) );
	exit;
}

/**
 * Sign-in link for an email address. The link is signed (no stored token), so it
 * keeps working until it expires, even if it is tapped several times or opened in
 * a different browser (e.g. Gmail's in-app browser, then Safari).
 */
function skynco_magic_link_url( $email, $ttl, $tab = '' ) {
	$email = strtolower( trim( $email ) );
	$exp   = time() + (int) $ttl;
	$b64   = rtrim( strtr( base64_encode( $email ), '+/', '-_' ), '=' );
	$sig   = substr( hash_hmac( 'sha256', $email . '|' . $exp, wp_salt( 'auth' ) ), 0, 32 );
	return add_query_arg( 'sk_login', $b64 . '.' . $exp . '.' . $sig, skynco_account_url( $tab ) );
}

/** Personal dashboard link used in emails (valid for 60 days). */
function skynco_dashboard_link( $email, $tab = '' ) {
	return skynco_magic_link_url( $email, 60 * DAY_IN_SECONDS, $tab );
}

/** Email address from a valid sign-in token, or ''. */
function skynco_magic_link_email( $token ) {
	$parts = explode( '.', (string) $token );
	if ( 3 === count( $parts ) ) {
		[ $b64, $exp, $sig ] = $parts;
		$email = strtolower( (string) base64_decode( strtr( $b64, '-_', '+/' ) ) );
		if ( (int) $exp > time() && is_email( $email ) && hash_equals( substr( hash_hmac( 'sha256', $email . '|' . (int) $exp, wp_salt( 'auth' ) ), 0, 32 ), $sig ) ) {
			return $email;
		}
		return '';
	}
	// Links sent before signed links existed.
	return (string) get_transient( 'skynco_ml_' . hash( 'sha256', (string) $token ) );
}

/* Clients stay signed in on their device for six months. */
add_filter(
	'auth_cookie_expiration',
	function ( $len, $user_id, $remember ) {
		return $remember && ! user_can( $user_id, 'edit_posts' ) ? 180 * DAY_IN_SECONDS : $len;
	},
	10,
	3
);

add_action(
	'template_redirect',
	function () {
		if ( is_page( 'account' ) ) {
			// Personal page with a form nonce: never serve it from the page cache.
			nocache_headers();
			do_action( 'litespeed_control_set_nocache', 'skynco client dashboard' );
		}
		if ( empty( $_GET['sk_login'] ) ) {
			return;
		}
		$tab   = sanitize_key( (string) get_query_var( 'sk_tab' ) );
		$dest  = skynco_account_url( $tab );
		$email = skynco_magic_link_email( sanitize_text_field( wp_unslash( $_GET['sk_login'] ) ) );
		$me    = wp_get_current_user();
		if ( $me->ID && ( ! $email || strtolower( $me->user_email ) === $email ) ) {
			// Already signed in on this device: just open the dashboard.
			wp_safe_redirect( $dest );
			exit;
		}
		if ( ! $email ) {
			wp_safe_redirect( add_query_arg( 'sk', 'expired', skynco_account_url() ) );
			exit;
		}
		if ( $me->ID && user_can( $me, 'edit_posts' ) ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			$uid = wp_insert_user(
				[
					'user_login'   => skynco_unique_login( $email ),
					'user_email'   => $email,
					'user_pass'    => wp_generate_password( 24 ),
					'role'         => get_role( 'customer' ) ? 'customer' : 'subscriber',
					'display_name' => skynco_client_first_name( $email ) ?: strstr( $email, '@', true ),
					'first_name'   => skynco_client_first_name( $email ),
				]
			);
			if ( is_wp_error( $uid ) ) {
				wp_safe_redirect( add_query_arg( 'sk', 'expired', skynco_account_url() ) );
				exit;
			}
			$user = get_user_by( 'id', $uid );
		}
		if ( user_can( $user, 'edit_posts' ) ) {
			// Staff accounts sign in with a password only.
			wp_safe_redirect( wp_login_url( skynco_account_url() ) );
			exit;
		}
		skynco_link_client_records( $user );
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		wp_safe_redirect( $dest );
		exit;
	}
);

function skynco_unique_login( $email ) {
	$base  = sanitize_user( strstr( $email, '@', true ), true ) ?: 'client';
	$login = $base;
	$i     = 1;
	while ( username_exists( $login ) ) {
		$login = $base . ( ++$i );
	}
	return $login;
}

function skynco_client_first_name( $email ) {
	global $wpdb;
	$t = $wpdb->prefix . 'latepoint_customers';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) {
		return '';
	}
	return (string) $wpdb->get_var( $wpdb->prepare( "SELECT first_name FROM {$t} WHERE email = %s ORDER BY id DESC LIMIT 1", $email ) ); // phpcs:ignore
}

/** Attach guest bookings and orders made with this email to the WordPress user. */
function skynco_link_client_records( $user ) {
	global $wpdb;
	$t = $wpdb->prefix . 'latepoint_customers';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET wordpress_user_id = %d WHERE email = %s AND ( wordpress_user_id IS NULL OR wordpress_user_id = 0 )", $user->ID, $user->user_email ) ); // phpcs:ignore
	}
	if ( function_exists( 'wc_get_orders' ) ) {
		foreach ( wc_get_orders( [ 'billing_email' => $user->user_email, 'limit' => 50 ] ) as $o ) {
			if ( $o->get_customer_id() ) {
				continue;
			}
			$o->set_customer_id( $user->ID );
			$o->save();
		}
	}
}

add_action(
	'wp_login',
	function ( $login, $user ) {
		if ( ! user_can( $user, 'edit_posts' ) ) {
			skynco_link_client_records( $user );
		}
	},
	10,
	2
);

/* ---------------------------------------------------------------------------
 * Data
 * ------------------------------------------------------------------------ */
function skynco_client_bookings( $user ) {
	global $wpdb;
	$c = $wpdb->prefix . 'latepoint_customers';
	$b = $wpdb->prefix . 'latepoint_bookings';
	$s = $wpdb->prefix . 'latepoint_services';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $b ) ) !== $b ) {
		return [];
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT bk.id, bk.booking_code, bk.start_date, bk.start_time, bk.end_time, bk.start_datetime_utc, bk.end_datetime_utc, bk.duration, bk.status, bk.service_id, sv.name AS service FROM {$b} bk LEFT JOIN {$s} sv ON sv.id = bk.service_id WHERE bk.customer_id IN ( SELECT id FROM {$c} WHERE email = %s OR wordpress_user_id = %d ) AND bk.status NOT IN ('cancelled') ORDER BY bk.start_datetime_utc ASC", $user->user_email, $user->ID ) );
	$now  = time();
	$out  = [ 'upcoming' => [], 'past' => [] ];
	foreach ( (array) $rows as $r ) {
		$dt         = date_create( $r->start_date . ' 00:00', wp_timezone() );
		$ts         = $dt ? $dt->getTimestamp() + (int) $r->start_time * 60 : 0;
		$r->ts      = $ts;
		$r->when    = $ts ? wp_date( 'l, F j · g:i a', $ts ) : $r->start_date;
		$r->service = $r->service ?: 'Appointment';
		$out[ $ts >= $now ? 'upcoming' : 'past' ][] = $r;
	}
	$out['past'] = array_reverse( $out['past'] );
	return $out;
}

function skynco_client_orders( $user ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return [];
	}
	$orders = wc_get_orders( [ 'customer_id' => $user->ID, 'limit' => 30, 'orderby' => 'date', 'order' => 'DESC' ] );
	$guest  = wc_get_orders( [ 'billing_email' => $user->user_email, 'limit' => 30, 'orderby' => 'date', 'order' => 'DESC' ] );
	$all    = [];
	foreach ( array_merge( $orders, $guest ) as $o ) {
		if ( ! in_array( $o->get_status(), [ 'failed', 'cancelled', 'checkout-draft' ], true ) ) {
			$all[ $o->get_id() ] = $o;
		}
	}
	krsort( $all );
	return array_values( $all );
}

function skynco_is_gift_item( $item ) {
	$pid = $item->get_product_id();
	return $pid && has_term( 'gift-cards', 'product_cat', $pid );
}

/* ---------------------------------------------------------------------------
 * Profile save
 * ------------------------------------------------------------------------ */
add_action(
	'admin_post_skynco_profile',
	function () {
		if ( ! is_user_logged_in() || ! check_admin_referer( 'skynco_profile' ) ) {
			wp_die( 'Not allowed' );
		}
		$uid   = get_current_user_id();
		$first = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
		$phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		wp_update_user( [ 'ID' => $uid, 'first_name' => $first, 'display_name' => $first ?: wp_get_current_user()->display_name ] );
		update_user_meta( $uid, 'billing_phone', $phone );
		update_user_meta( $uid, 'billing_first_name', $first );
		update_user_meta( $uid, 'skynco_wa_optin', empty( $_POST['wa'] ) ? '0' : '1' );
		update_user_meta( $uid, 'skynco_birthday', sanitize_text_field( wp_unslash( $_POST['birthday'] ?? '' ) ) );
		wp_safe_redirect( add_query_arg( 'sk', 'saved', skynco_account_url( 'profile' ) ) );
		exit;
	}
);

/* ---------------------------------------------------------------------------
 * Shortcode
 * ------------------------------------------------------------------------ */
add_shortcode( 'skynco_account', 'skynco_account_shortcode' );

/* Separate pages: /account/visits/, /account/orders/ … */
function skynco_dash_tabs() {
	return [
		''              => [ 'home', 'Overview', 'Your next visit, reminders and shortcuts.' ],
		'visits'        => [ 'calendar', 'Visits', 'Upcoming appointments and your treatment history.' ],
		'orders'        => [ 'bag', 'Orders', 'Your home-care orders and gift cards.' ],
		'rewards'       => [ 'star', 'Rewards', 'Your Glow Club card, perks and gifts.' ],
		'notifications' => [ 'bell', 'Notifications', 'Booking and order updates from the studio.' ],
		'profile'       => [ 'user', 'Profile', 'Your details and how we keep in touch.' ],
	];
}

add_action(
	'init',
	function () {
		add_rewrite_rule( '^account/(visits|orders|rewards|notifications|profile)/?$', 'index.php?pagename=account&sk_tab=$matches[1]', 'top' );
		if ( get_option( 'skynco_dash_rewrite' ) !== '2' ) {
			flush_rewrite_rules( false );
			update_option( 'skynco_dash_rewrite', '2' );
		}
	}
);
add_filter(
	'query_vars',
	function ( $v ) {
		$v[] = 'sk_tab';
		return $v;
	}
);

/** Small line icons (no emoji). */
function skynco_icon( $name ) {
	$p = [
		'home'     => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v10h14V10"/>',
		'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="3"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
		'bag'      => '<path d="M5 8h14l-1.2 12H6.2z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>',
		'gift'     => '<rect x="3.5" y="8.5" width="17" height="4" rx="1"/><path d="M5 12.5V20h14v-7.5M12 8.5V20M12 8.5C10.5 5 7 5 7.5 7.2 8 8.5 12 8.5 12 8.5ZM12 8.5c1.5-3.5 5-3.5 4.5-1.3C16 8.5 12 8.5 12 8.5Z"/>',
		'star'     => '<path d="m12 3.8 2.5 5.1 5.6.8-4 4 .9 5.6-5-2.7-5 2.7.9-5.6-4-4 5.6-.8z"/>',
		'user'     => '<circle cx="12" cy="8.5" r="4"/><path d="M4.5 20c1.2-3.6 4-5.3 7.5-5.3s6.3 1.7 7.5 5.3"/>',
		'chat'     => '<path d="M4 5.5h16v10.5H9l-5 4z"/>',
		'pin'      => '<path d="M12 21s-6.5-6.2-6.5-11a6.5 6.5 0 0 1 13 0c0 4.8-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.3"/>',
		'clock'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
		'sparkle'  => '<path d="M12 3.5c.7 4.3 2.2 5.8 6.5 6.5-4.3.7-5.8 2.2-6.5 6.5-.7-4.3-2.2-5.8-6.5-6.5 4.3-.7 5.8-2.2 6.5-6.5zM18.5 15.5c.3 1.7.9 2.3 2.5 2.5-1.6.3-2.2.9-2.5 2.5-.3-1.6-.9-2.2-2.5-2.5 1.6-.2 2.2-.8 2.5-2.5z"/>',
		'out'      => '<path d="M14 4.5h5.5v15H14M10 8l-4 4 4 4M6 12h10"/>',
		'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'bell'     => '<path d="M6 16.5V11a6 6 0 0 1 12 0v5.5l1.5 2h-15z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
	];
	return '<svg class="skd-i" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $p[ $name ] ?? '' ) . '</svg>';
}

/** Everything the dashboard pages need, gathered once. */
function skynco_dash_context() {
	$user = wp_get_current_user();
	$c    = [
		'user'     => $user,
		'first'    => $user->first_name ?: skynco_client_first_name( $user->user_email ) ?: $user->display_name,
		'bookings' => skynco_client_bookings( $user ),
		'orders'   => skynco_client_orders( $user ),
		'book'     => home_url( '/book/' ),
		'svcs'     => home_url( '/services/' ),
		'sms'      => 'sms:+18572284708',
		'map'      => 'https://maps.google.com/?q=' . rawurlencode( 'Skyn&Co. 150 Arsenal St Suite 210 Watertown MA 02472' ),
		'bday'     => get_user_meta( $user->ID, 'skynco_birthday', true ),
	];
	$c['bookings'] += [ 'upcoming' => [], 'past' => [] ];
	$c['next']      = $c['bookings']['upcoming'][0] ?? null;
	$c['last']      = $c['bookings']['past'][0] ?? null;
	[ $c['goal'], $c['treat'] ] = skynco_loyalty();
	$c['visits'] = count( $c['bookings']['past'] );
	$c['stamp']  = $c['visits'] % $c['goal'];
	$c['earned'] = (int) floor( $c['visits'] / $c['goal'] );
	$c['unread'] = function_exists( 'skynco_notices_unread' ) ? skynco_notices_unread( 'client', $user->user_email ) : 0;
	return $c;
}

function skynco_account_shortcode() {
	$msg = sanitize_key( $_GET['sk'] ?? '' );
	if ( ! is_user_logged_in() ) {
		return skynco_account_signin( $msg );
	}
	$tabs = skynco_dash_tabs();
	$tab  = sanitize_key( (string) get_query_var( 'sk_tab' ) );
	$tab  = isset( $tabs[ $tab ] ) ? $tab : '';
	$c    = skynco_dash_context();

	$h  = '<div class="skd"><div class="skd-wrap">' . skynco_dash_sidebar( $c, $tab );
	$h .= '<main class="skd-main">';
	if ( 'saved' === $msg ) {
		$h .= '<p class="skd-note">Your details are saved.</p>';
	}
	if ( '' === $tab ) {
		$hour  = (int) wp_date( 'G' );
		$greet = $hour < 12 ? 'Good morning' : ( $hour < 18 ? 'Good afternoon' : 'Good evening' );
		$h    .= '<header class="skd-top"><p class="skd-eyebrow">' . esc_html( $greet ) . '</p><h1>Hi ' . esc_html( $c['first'] ) . ', <em>welcome back.</em></h1><p class="skd-date">' . esc_html( wp_date( 'l, F j' ) ) . '</p></header>';
	} else {
		$h .= '<header class="skd-top skd-top--page"><a class="skd-back" href="' . esc_url( skynco_account_url() ) . '">' . skynco_icon( 'home' ) . 'Overview</a><h1>' . esc_html( $tabs[ $tab ][1] ) . '</h1><p class="skd-date">' . esc_html( $tabs[ $tab ][2] ) . '</p></header>';
	}
	$fn = 'skynco_dash_page_' . ( $tab ?: 'overview' );
	$h .= $fn( $c );
	$h .= '</main></div></div>';
	return $h;
}

function skynco_dash_sidebar( $c, $tab ) {
	$user     = $c['user'];
	$initials = strtoupper( mb_substr( $c['first'], 0, 1 ) . mb_substr( $user->last_name ?: '', 0, 1 ) );
	$since    = $user->user_registered ? wp_date( 'F Y', strtotime( $user->user_registered ) ) : '';
	$h        = '<aside class="skd-side"><div class="skd-me"><span class="skd-avatar">' . esc_html( $initials ?: 'S' ) . '</span><div><p class="skd-name">' . esc_html( $c['first'] ) . '</p><p class="skd-since">' . ( $since ? 'Client since ' . esc_html( $since ) : 'Skyn&amp;Co. client' ) . '</p></div></div>';
	$h       .= '<div class="skd-tier"><span class="skd-tier__k">Glow Club</span><span class="skd-tier__v">' . (int) $c['stamp'] . ' of ' . (int) $c['goal'] . ' visits to your treat</span><span class="skd-tier__bar"><i style="width:' . (int) round( $c['stamp'] / $c['goal'] * 100 ) . '%"></i></span></div>';
	$h       .= '<nav class="skd-nav" aria-label="Dashboard">';
	foreach ( skynco_dash_tabs() as $id => $n ) {
		$badge = 'notifications' === $id && $c['unread'] ? '<em class="skd-badge">' . (int) $c['unread'] . '</em>' : '';
		$h    .= '<a href="' . esc_url( skynco_account_url( $id ) ) . '"' . ( $id === $tab ? ' class="is-on" aria-current="page"' : '' ) . '>' . skynco_icon( $n[0] ) . '<span>' . esc_html( $n[1] ) . '</span>' . $badge . '</a>';
	}
	$h .= '</nav><a class="skd-signout" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . skynco_icon( 'out' ) . 'Sign out</a>';
	$h .= '<div class="skd-help"><p>Questions about your skin or a booking?</p><a href="' . esc_url( $c['sms'] ) . '">' . skynco_icon( 'chat' ) . 'Text Hana</a></div></aside>';
	return $h;
}

function skynco_dash_next_card( $c ) {
	$next = $c['next'];
	$last = $c['last'];
	if ( $next ) {
		$days = max( 0, (int) floor( ( $next->ts - time() ) / DAY_IN_SECONDS ) );
		$when = $days ? 'In ' . $days . ' day' . ( 1 === $days ? '' : 's' ) : 'Today';
		$h    = '<section class="skd-next"><div class="skd-next__date"><span>' . esc_html( wp_date( 'M', $next->ts ) ) . '</span><b>' . esc_html( wp_date( 'j', $next->ts ) ) . '</b><span>' . esc_html( wp_date( 'D', $next->ts ) ) . '</span></div>';
		$h   .= '<div class="skd-next__body"><p class="skd-next__k">Your next visit <span class="skd-pill skd-pill--glow">' . esc_html( $when ) . '</span></p><h2>' . esc_html( $next->service ) . '</h2><p class="skd-next__meta">' . skynco_icon( 'clock' ) . esc_html( wp_date( 'g:i a', $next->ts ) ) . ' · ' . (int) $next->duration . ' min</p><p class="skd-next__meta">' . skynco_icon( 'pin' ) . '150 Arsenal St, Suite 210, Watertown</p><div class="skd-next__acts">';
		if ( function_exists( 'skynco_gcal_url' ) && ! empty( $next->end_datetime_utc ) ) {
			$h .= '<a class="skd-btn skd-btn--light" href="' . esc_url( skynco_gcal_url( $next ) ) . '" target="_blank" rel="noopener">Add to calendar</a>';
		}
		return $h . '<a class="skd-btn skd-btn--ghost" href="' . esc_url( $c['map'] ) . '" target="_blank" rel="noopener">Directions</a><a class="skd-btn skd-btn--ghost" href="' . esc_url( $c['sms'] . '?&body=' . rawurlencode( 'Hi Hana, I need to change my booking ' . $next->booking_code ) ) . '">Change</a></div></div></section>';
	}
	$label = $last ? 'Time for your next ' . $last->service . '?' : 'Ready for your first glow?';
	$link  = $last ? add_query_arg( 'service', (int) $last->service_id, $c['book'] ) : add_query_arg( 'service', 'new-client-facial', $c['book'] );
	return '<section class="skd-next skd-next--empty"><div class="skd-next__body"><p class="skd-next__k">No upcoming visits</p><h2>' . esc_html( $label ) . '</h2><p class="skd-next__meta">' . ( $last ? 'Regular visits keep your results going. Most clients rebook every 4 to 6 weeks.' : 'Start with the New Client Facial &amp; Consultation, made for your skin.' ) . '</p><div class="skd-next__acts"><a class="skd-btn skd-btn--light" href="' . esc_url( $link ) . '">' . ( $last ? 'Rebook' : 'Book now' ) . '</a><a class="skd-btn skd-btn--ghost" href="' . esc_url( $c['svcs'] ) . '">See treatments</a></div></div></section>';
}

/* ---------- Overview ---------- */
function skynco_dash_page_overview( $c ) {
	$h     = skynco_dash_next_card( $c );
	$stats = [
		[ 'calendar', count( $c['bookings']['upcoming'] ), 'Upcoming', 'visits' ],
		[ 'bag', count( $c['orders'] ), 'Orders', 'orders' ],
		[ 'star', $c['stamp'] . '/' . $c['goal'], 'Loyalty stamps', 'rewards' ],
		[ 'bell', $c['unread'], 'New updates', 'notifications' ],
	];
	$h .= '<section class="skd-stats">';
	foreach ( $stats as $s ) {
		$h .= '<a class="skd-stat" href="' . esc_url( skynco_account_url( $s[3] ) ) . '"><span class="skd-stat__i">' . skynco_icon( $s[0] ) . '</span><b>' . esc_html( (string) $s[1] ) . '</b><span>' . esc_html( $s[2] ) . '</span></a>';
	}
	$h .= '</section>';

	// Reminders.
	$rem = [];
	if ( $c['last'] && ! $c['next'] ) {
		$due   = $c['last']->ts + skynco_rebook_weeks( $c['last']->service ) * WEEK_IN_SECONDS;
		$rem[] = [ 'calendar', $due <= time() ? 'Your ' . $c['last']->service . ' is due' : 'Next ' . $c['last']->service . ' due ' . wp_date( 'M j', $due ), 'Book now to keep your favourite time.', add_query_arg( 'service', (int) $c['last']->service_id, $c['book'] ), 'Rebook' ];
	}
	if ( $c['next'] ) {
		$rem[] = [ 'sparkle', 'Prep for your ' . $c['next']->service, 'Skip retinoids and exfoliants for 2 days before and arrive with clean skin.', '', '' ];
	}
	if ( ! $c['orders'] ) {
		$rem[] = [ 'gift', 'Your welcome gift: 10% off', 'Use code GLOW10 on your first home-care order.', home_url( '/shop/' ), 'Shop now' ];
	} elseif ( ! $c['bday'] ) {
		$rem[] = [ 'star', 'Add your birthday', 'Get 15% off in your birthday month.', skynco_account_url( 'profile' ), 'Add it' ];
	}
	if ( $rem ) {
		$h .= '<section class="skd-sec"><div class="skd-head"><h2>For you</h2></div><div class="skd-rem">';
		foreach ( array_slice( $rem, 0, 2 ) as $r ) {
			$h .= '<div class="skd-remcard"><span class="skd-remcard__i">' . skynco_icon( $r[0] ) . '</span><div><p class="skd-t">' . esc_html( $r[1] ) . '</p><p class="skd-s">' . esc_html( $r[2] ) . '</p>' . ( $r[3] ? '<a class="skd-link" href="' . esc_url( $r[3] ) . '">' . esc_html( $r[4] ) . skynco_icon( 'arrow' ) . '</a>' : '' ) . '</div></div>';
		}
		$h .= '</div></section>';
	}

	// Latest updates.
	$notes = function_exists( 'skynco_notices_get' ) ? skynco_notices_get( 'client', $c['user']->user_email, 3 ) : [];
	if ( $notes ) {
		$h .= '<section class="skd-sec"><div class="skd-head"><h2>Latest updates</h2><a class="skd-link" href="' . esc_url( skynco_account_url( 'notifications' ) ) . '">See all' . skynco_icon( 'arrow' ) . '</a></div>' . skynco_dash_notice_list( $notes ) . '</section>';
	}

	$quick = [
		[ 'calendar', 'Book a visit', $c['svcs'] ],
		[ 'bag', 'Shop home care', home_url( '/shop/' ) ],
		[ 'gift', 'Send a gift card', home_url( '/product/skynco-gift-card/' ) ],
		[ 'chat', 'Text Hana', $c['sms'] ],
	];
	$h .= '<section class="skd-quick">';
	foreach ( $quick as $q ) {
		$h .= '<a href="' . esc_url( $q[2] ) . '"><span>' . skynco_icon( $q[0] ) . '</span>' . esc_html( $q[1] ) . '</a>';
	}
	return $h . '</section>';
}

/* ---------- Visits ---------- */
function skynco_dash_page_visits( $c ) {
	$b = $c['bookings'];
	$h = '';
	if ( $c['next'] ) {
		$h .= skynco_dash_next_card( $c );
	}
	$h .= '<section class="skd-sec"><div class="skd-head"><h2>Upcoming</h2><a class="skd-btn skd-btn--dark" href="' . esc_url( $c['svcs'] ) . '">Book a visit</a></div>';
	if ( $b['upcoming'] ) {
		$h .= '<div class="skd-list">';
		foreach ( $b['upcoming'] as $v ) {
			$h .= '<div class="skd-row"><div class="skd-chip"><span>' . esc_html( wp_date( 'M', $v->ts ) ) . '</span><b>' . esc_html( wp_date( 'j', $v->ts ) ) . '</b></div><div class="skd-row__body"><p class="skd-t">' . esc_html( $v->service ) . '</p><p class="skd-s">' . esc_html( wp_date( 'l · g:i a', $v->ts ) ) . ' · ' . (int) $v->duration . ' min · ' . esc_html( $v->booking_code ) . '</p></div><div class="skd-row__acts"><span class="skd-pill">' . esc_html( 'approved' === $v->status ? 'Confirmed' : ucfirst( $v->status ) ) . '</span><a class="skd-link" href="' . esc_url( $c['sms'] . '?&body=' . rawurlencode( 'Hi Hana, I need to change my booking ' . $v->booking_code ) ) . '">Change</a></div></div>';
		}
		$h .= '</div>';
	} else {
		$h .= '<div class="skd-empty">' . skynco_icon( 'calendar' ) . '<p>No upcoming visits.</p><a class="skd-link" href="' . esc_url( $c['svcs'] ) . '">Explore treatments' . skynco_icon( 'arrow' ) . '</a></div>';
	}
	$h .= '</section><section class="skd-sec"><div class="skd-head"><h2>History</h2></div>';
	if ( $b['past'] ) {
		$h .= '<div class="skd-timeline">';
		foreach ( array_slice( $b['past'], 0, 20 ) as $v ) {
			$h .= '<div class="skd-tl"><span class="skd-tl__dot"></span><div class="skd-row__body"><p class="skd-t">' . esc_html( $v->service ) . '</p><p class="skd-s">' . esc_html( wp_date( 'F j, Y', $v->ts ) ) . '</p></div><a class="skd-link" href="' . esc_url( add_query_arg( 'service', (int) $v->service_id, $c['book'] ) ) . '">Book again</a></div>';
		}
		$h .= '</div>';
	} else {
		$h .= '<div class="skd-empty">' . skynco_icon( 'sparkle' ) . '<p>Your past treatments will appear here after your first visit.</p></div>';
	}
	return $h . '</section>';
}

/* ---------- Orders ---------- */
function skynco_dash_page_orders( $c ) {
	$user = $c['user'];
	$h    = '<section class="skd-sec"><div class="skd-head"><h2>Your orders</h2><a class="skd-btn skd-btn--dark" href="' . esc_url( home_url( '/shop/' ) ) . '">Shop</a></div>';
	if ( ! $c['orders'] ) {
		return $h . '<div class="skd-empty">' . skynco_icon( 'bag' ) . '<p>No orders yet. Your first home-care order is 10% off with code <b>GLOW10</b>.</p><a class="skd-link" href="' . esc_url( home_url( '/shop/' ) ) . '">Visit the shop' . skynco_icon( 'arrow' ) . '</a></div></section>';
	}
	$h .= '<div class="skd-orders">';
	foreach ( array_slice( $c['orders'], 0, 20 ) as $o ) {
		$thumbs = '';
		$names  = [];
		$again  = '';
		foreach ( $o->get_items() as $it ) {
			$p       = $it->get_product();
			$names[] = $it->get_name() . ( $it->get_quantity() > 1 ? ' × ' . $it->get_quantity() : '' );
			if ( $p && substr_count( $thumbs, '<img' ) < 3 ) {
				$thumbs .= $p->get_image( [ 120, 120 ] );
			}
			if ( ! $again && $p && $p->is_purchasable() ) {
				$again = '<a class="skd-link" href="' . esc_url( add_query_arg( 'add-to-cart', $p->get_id(), wc_get_cart_url() ) ) . '">Buy again</a>';
			}
		}
		$status = $o->get_status();
		$tone   = 'completed' === $status ? 'done' : ( in_array( $status, [ 'processing', 'on-hold' ], true ) ? 'live' : '' );
		$view   = (int) $o->get_customer_id() === $user->ID ? '<a class="skd-link" href="' . esc_url( $o->get_view_order_url() ) . '">Details</a>' : '';
		$h     .= '<article class="skd-order"><div class="skd-order__thumbs">' . $thumbs . '</div><div class="skd-order__body"><div class="skd-order__top"><p class="skd-t">Order #' . esc_html( $o->get_order_number() ) . '</p><span class="skd-pill skd-pill--' . $tone . '">' . esc_html( wc_get_order_status_name( $status ) ) . '</span></div><p class="skd-s">' . esc_html( wc_format_datetime( $o->get_date_created(), 'M j, Y' ) ) . ' · ' . esc_html( implode( ', ', $names ) ) . '</p><div class="skd-order__foot"><b>' . wp_kses_post( $o->get_formatted_order_total() ) . '</b><span>' . $view . $again . '</span></div></div></article>';
	}
	return $h . '</div></section>';
}

/* ---------- Rewards ---------- */
function skynco_dash_page_rewards( $c ) {
	$goal  = $c['goal'];
	$stamp = $c['stamp'];
	$dots  = '';
	for ( $i = 1; $i <= $goal; $i++ ) {
		$dots .= '<span class="skd-stamp' . ( $i <= $stamp ? ' is-on' : '' ) . ( $i === $goal ? ' is-goal' : '' ) . '">' . ( $i === $goal ? skynco_icon( 'gift' ) : ( $i <= $stamp ? skynco_icon( 'sparkle' ) : $i ) ) . '</span>';
	}
	$gifts = [];
	foreach ( $c['orders'] as $o ) {
		foreach ( $o->get_items() as $it ) {
			if ( skynco_is_gift_item( $it ) ) {
				$gifts[] = '<div class="skd-giftrow">' . skynco_icon( 'gift' ) . '<div><p class="skd-t">' . esc_html( $it->get_name() ) . ' · ' . wp_kses_post( wc_price( $it->get_total() ) ) . '</p><p class="skd-s">Bought ' . esc_html( wc_format_datetime( $o->get_date_created(), 'M j, Y' ) ) . ' · sent by email</p></div></div>';
			}
		}
	}
	$name = trim( $c['first'] . ' ' . $c['user']->last_name );
	$h    = '<section class="skd-sec"><div class="skd-rewards">';
	$h   .= '<div class="skd-loyalty"><div class="skd-loyalty__top"><p>Glow Club card</p><span>Skyn&amp;Co.</span></div><p class="skd-loyalty__big">' . ( $stamp ? ( $goal - $stamp ) . ' more visit' . ( $goal - $stamp > 1 ? 's' : '' ) . ' to go' : 'Your card is ready' ) . '</p><div class="skd-stamps">' . $dots . '</div><p class="skd-loyalty__fine">Every ' . $goal . 'th visit includes ' . esc_html( $c['treat'] ) . '.' . ( $c['earned'] ? ' Treats earned: ' . $c['earned'] . '.' : '' ) . '</p><p class="skd-loyalty__name">' . esc_html( $name ) . '</p></div>';
	$h   .= '<div class="skd-gifts">';
	$h   .= '<div class="skd-perk"><span class="skd-perk__i">' . skynco_icon( 'gift' ) . '</span><div><p class="skd-t">' . ( $c['orders'] ? 'Member perk: free shipping over $75' : 'Welcome gift: 10% off' ) . '</p><p class="skd-s">' . ( $c['orders'] ? 'Or free pickup at the studio, any time.' : 'Use <b>GLOW10</b> on your first home-care order.' ) . '</p></div></div>';
	$h   .= '<div class="skd-perk"><span class="skd-perk__i">' . skynco_icon( 'star' ) . '</span><div><p class="skd-t">' . ( $c['bday'] ? 'Birthday treat in ' . esc_html( wp_date( 'F', strtotime( $c['bday'] ) ) ) : 'Birthday treat: 15% off' ) . '</p><p class="skd-s">' . ( $c['bday'] ? 'Your code arrives at the start of your birthday month.' : '<a href="' . esc_url( skynco_account_url( 'profile' ) ) . '">Add your birthday</a> to unlock it.' ) . '</p></div></div>';
	$h   .= '<a class="skd-giftcta" href="' . esc_url( home_url( '/product/skynco-gift-card/' ) ) . '">' . skynco_icon( 'gift' ) . '<span><b>Send a gift card</b>Delivered by email, never expires</span>' . skynco_icon( 'arrow' ) . '</a></div></div>';
	if ( $gifts ) {
		$h .= '<p class="skd-sub" style="margin-top:22px!important">Gift cards you sent</p><div class="skd-list">' . implode( '', $gifts ) . '</div>';
	}
	$h .= '</section>';

	$slugs = 'glow-kit,daily-mineral-spf-40,hydrating-hyaluronic-serum';
	$ref   = $c['last'] ?: $c['next'];
	if ( $ref && function_exists( 'lux_tslug' ) && function_exists( 'lux_shop_recs_for' ) && ( $ts = lux_tslug( $ref->service ) ) ) {
		$slugs = implode( ',', array_slice( lux_shop_recs_for( $ts ), 0, 3 ) );
	}
	return $h . '<section class="skd-sec skd-recs"><div class="skd-head"><h2>Picked for your skin</h2><a class="skd-link" href="' . esc_url( home_url( '/shop/' ) ) . '">See all' . skynco_icon( 'arrow' ) . '</a></div>' . do_shortcode( '[skynco_products slugs="' . esc_attr( $slugs ) . '" columns="3"]' ) . '</section>';
}

/* ---------- Notifications ---------- */
function skynco_dash_notice_list( $notes ) {
	$icons = [ 'booking' => 'calendar', 'order' => 'bag' ];
	$h     = '<div class="skd-notes">';
	foreach ( $notes as $n ) {
		$base = strtok( $n->type, '-' );
		$tag  = $n->link ? 'a href="' . esc_url( $n->link ) . '"' : 'div';
		$h   .= '<' . $tag . ' class="skd-notice' . ( $n->read_at ? '' : ' is-new' ) . '"><span class="skd-notice__i">' . skynco_icon( $icons[ $base ] ?? 'bell' ) . '</span><span class="skd-notice__b"><span class="skd-t">' . esc_html( $n->title ) . '</span><span class="skd-s">' . esc_html( $n->body ) . '</span></span><span class="skd-notice__t">' . esc_html( skynco_notice_time( $n->created_at ) ) . '</span></' . ( $n->link ? 'a' : 'div' ) . '>';
	}
	return $h . '</div>';
}

function skynco_dash_page_notifications( $c ) {
	$email = $c['user']->user_email;
	$notes = function_exists( 'skynco_notices_get' ) ? skynco_notices_get( 'client', $email, 50 ) : [];
	if ( function_exists( 'skynco_notices_mark_read' ) ) {
		skynco_notices_mark_read( 'client', $email );
	}
	$h = '<section class="skd-sec">';
	if ( ! $notes ) {
		return $h . '<div class="skd-empty">' . skynco_icon( 'bell' ) . '<p>No updates yet. Booking confirmations and order updates will appear here.</p></div></section>';
	}
	return $h . skynco_dash_notice_list( $notes ) . '</section>';
}

/* ---------- Profile ---------- */
function skynco_dash_page_profile( $c ) {
	$user  = $c['user'];
	$phone = get_user_meta( $user->ID, 'billing_phone', true );
	$wa    = '0' !== get_user_meta( $user->ID, 'skynco_wa_optin', true );
	$h     = '<section class="skd-sec"><form class="skd-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'skynco_profile', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="skynco_profile">';
	$h    .= '<label>First name<input name="first_name" value="' . esc_attr( $user->first_name ?: $c['first'] ) . '" autocomplete="given-name"></label>';
	$h    .= '<label>Email<input value="' . esc_attr( $user->user_email ) . '" disabled></label>';
	$h    .= '<label>Mobile (WhatsApp)<input name="phone" type="tel" value="' . esc_attr( $phone ) . '" autocomplete="tel" placeholder="(617) 555-0123"></label>';
	$h    .= '<label>Birthday<input name="birthday" type="date" value="' . esc_attr( $c['bday'] ) . '"></label>';
	$h    .= '<label class="skd-switch"><input type="checkbox" name="wa" value="1"' . checked( $wa, true, false ) . '><span class="skd-switch__ui"></span><span>Send my booking and order updates on WhatsApp</span></label>';
	return $h . '<div class="skd-form__foot"><button class="skd-btn skd-btn--pink" type="submit">Save details</button><a class="skd-link skd-link--muted" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">Sign out</a></div></form></section>';
}

function skynco_account_signin( $msg ) {
	$notes = [
		'sent'      => 'Check your inbox. We sent a sign-in link to <b>' . esc_html( sanitize_email( wp_unslash( $_GET['e'] ?? '' ) ) ) . '</b>. It works for 48 hours.',
		'expired'   => 'That link has expired or was already used. Enter your email for a new one.',
		'bad-email' => 'Please enter a valid email address.',
	];
	$h  = '<div class="ska ska--in"><section class="ska-hero ska-hero--in"><div class="ska-wrap ska-in">';
	$h .= '<div><p class="ska-eyebrow">Client dashboard</p><h1>Your visits, orders <em>and rewards.</em></h1><p class="ska-lead">See upcoming appointments, rebook in a tap, track orders and collect your loyalty treats.</p></div>';
	$h .= '<div class="ska-signin"><h2>Sign in or create an account</h2><p class="ska-s">No password needed. Use the email you booked or ordered with and we’ll send you a secure link.</p>';
	if ( isset( $notes[ $msg ] ) ) {
		$h .= '<p class="ska-note">' . $notes[ $msg ] . '</p>';
	}
	$h .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="skynco_magic"><input type="hidden" name="_sknonce" value="' . esc_attr( wp_create_nonce( 'skynco_magic' ) ) . '">';
	$h .= '<label class="ska-sr" for="ska-email">Email</label><input id="ska-email" type="email" name="email" required autocomplete="email" placeholder="you@example.com" value="' . esc_attr( sanitize_email( wp_unslash( $_GET['e'] ?? '' ) ) ) . '"><button class="ska-btn" type="submit">Email me a sign-in link</button></form>';
	$h .= '<details class="ska-pw"><summary>Use a password instead</summary>' . wp_login_form( [ 'echo' => false, 'redirect' => skynco_account_url(), 'label_username' => 'Email', 'remember' => true ] ) . '<a href="' . esc_url( wp_lostpassword_url( skynco_account_url() ) ) . '">Forgot password?</a></details>';
	$h .= '</div></div></section></div>';
	return $h;
}

/* Logged-in WooCommerce "My account" dashboard points to the new one. */
add_action(
	'woocommerce_account_dashboard',
	function () {
		echo '<p><a class="button" href="' . esc_url( skynco_account_url() ) . '">Open your Skyn&amp;Co. dashboard</a></p>';
	}
);

/* Thank-you pages link to the dashboard. */
add_action(
	'woocommerce_thankyou',
	function () {
		echo '<p class="sk-thanks-acct">Track this order, your visits and rewards in <a href="' . esc_url( skynco_account_url() ) . '">your client dashboard</a>.</p>';
	},
	30
);

add_action(
	'wp_head',
	function () {
		if ( ! is_page( 'account' ) ) {
			return;
		}
		?>
<style id="skynco-account">
.ska{font-family:Manrope,sans-serif;color:#2A1A24;background:#FBF6F4;padding-bottom:72px}
.ska *{box-sizing:border-box}
.ska p{margin:0}.ska a{text-decoration:none!important}
.ska .ska-t{margin-bottom:4px}.ska .ska-s a{text-decoration:underline!important}
.ska-wrap{max-width:1080px;margin:0 auto;padding:0 24px}
.ska-hero{background:radial-gradient(120% 140% at 85% 0%,#5A2148 0%,#3B1530 45%,#26101F 100%);color:#F7EAF0;padding:72px 0 34px}
.ska-hero h1{font:400 clamp(34px,5vw,54px)/1.08 Fraunces,serif;margin:8px 0 12px;color:#fff;letter-spacing:-.01em}
.ska-hero h1 em{font-style:italic;color:#FFB3D9}
.ska-eyebrow,.ska-k{margin:0;font:700 11px/1.2 Manrope,sans-serif;letter-spacing:.16em;text-transform:uppercase;color:#FFB3D9}
.ska-k{color:#D1127E;margin-bottom:6px}
.ska-lead{max-width:560px;margin:0;font-size:16px;line-height:1.6;color:#EBD7E1}.ska-lead b{color:#fff}
.ska-tabs{display:flex;gap:8px;margin-top:26px;overflow-x:auto;scrollbar-width:none}
.ska-tabs a{flex:0 0 auto;padding:9px 16px;border-radius:999px;border:1px solid rgba(255,214,236,.35);color:#FFE3F1;text-decoration:none;font-weight:600;font-size:14px}
.ska-tabs a:hover{background:rgba(255,255,255,.08)}
.ska-rem{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin-top:-18px;position:relative}
.ska-remcard,.ska-card,.ska-signin{background:#fff;border:1px solid #EADDE0;border-radius:22px;padding:22px;box-shadow:0 10px 30px rgba(59,21,48,.06)}
.ska-t{margin:0 0 4px;font:600 16px/1.35 Manrope,sans-serif;color:#2A1A24}
.ska-s{margin:0;font-size:14px;line-height:1.5;color:#6E5A66}.ska-s a{color:#D1127E}
.ska-btn{display:inline-block;margin-top:14px;padding:12px 22px;border-radius:999px;background:#D1127E;color:#fff!important;text-decoration:none;font:700 14px Manrope,sans-serif;border:0;cursor:pointer}
.ska-btn:hover{background:#B00F6A}
.ska-btn--ghost{background:transparent;color:#3B1530!important;border:1px solid #3B1530;margin-top:0}
.ska-btn--ghost:hover{background:#3B1530;color:#fff!important}
.ska-sec{margin-top:44px}
.ska-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
.ska-head h2{margin:0;font:400 28px/1.2 Fraunces,serif;color:#3B1530}
.ska-sub{margin:22px 0 10px;font:700 12px Manrope,sans-serif;letter-spacing:.12em;text-transform:uppercase;color:#9A8791}
.ska-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 18px;background:#fff;border:1px solid #EADDE0;border-radius:16px;margin-bottom:10px}
.ska-row--next{border-color:#D1127E;box-shadow:0 0 0 1px #D1127E inset}
.ska-acts{display:flex;align-items:center;gap:14px;flex-shrink:0}
.ska-acts a,.ska-link{color:#D1127E;font-weight:700;font-size:14px;text-decoration:none}
.ska-pill{padding:4px 10px;border-radius:999px;background:#FBEFF0;color:#3B1530;font-size:12px;font-weight:700}
.ska-empty{padding:18px;border:1px dashed #E2CCD4;border-radius:16px;color:#6E5A66;margin:0}.ska-empty a{color:#D1127E}
.ska-grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.ska .ska-card .ska-s{margin-bottom:12px}.ska .ska-k{margin-bottom:6px}.ska .ska-lead{margin-top:0}.ska .ska-eyebrow{margin-bottom:0}
.ska-dots{display:flex;gap:8px;margin:12px 0}
.ska-dot{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;border:1px dashed #D9BFCB;color:#9A8791;font-size:13px;font-weight:700}
.ska-dot.is-on{background:#D1127E;border:0;color:#fff}
.ska-form{display:grid;grid-template-columns:1fr 1fr;gap:14px;background:#fff;border:1px solid #EADDE0;border-radius:22px;padding:22px}
.ska-form label{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:700;color:#3B1530}
.ska-form input,.ska-signin input[type=email],.ska-pw input[type=text],.ska-pw input[type=password]{width:100%;padding:13px 14px;border:1px solid #E2CCD4;border-radius:12px;font:500 15px Manrope,sans-serif;background:#fff;color:#2A1A24}
.ska-form .ska-check{grid-column:1/-1;flex-direction:row;align-items:center;gap:10px;font-weight:600}
.ska-form .ska-check input{width:18px;height:18px;accent-color:#D1127E}
.ska-form p{grid-column:1/-1;margin:0}
.ska-note{margin:14px 0 0;padding:12px 16px;border-radius:12px;background:#FFF0F7;border:1px solid #F5C6DD;color:#3B1530;font-size:14px}
.ska--in{padding-bottom:0}
.ska-in>*{min-width:0}
.ska-note,.ska-signin,.ska-hero h1,.ska-lead{overflow-wrap:anywhere}
.ska-pw form,.ska-pw input{max-width:100%}
.ska-hero--in{padding:80px 0 90px}
.ska-in{display:grid;grid-template-columns:1.1fr .9fr;gap:40px;align-items:center}
.ska-signin{color:#2A1A24}
.ska-signin h2{margin:0 0 6px;font:400 24px/1.25 Fraunces,serif;color:#3B1530}
.ska-signin form{margin-top:16px}
.ska-signin .ska-btn{width:100%;margin-top:10px}
.ska-pw{margin-top:18px;font-size:14px;color:#6E5A66}.ska-pw summary{cursor:pointer;font-weight:700;color:#3B1530}
.ska-pw p{margin:10px 0}.ska-pw label{display:block;font-weight:600;margin-bottom:4px}
.ska-pw .button{background:#3B1530;color:#fff;border:0;border-radius:999px;padding:11px 20px;font-weight:700}
.ska-pw a{color:#D1127E}
.ska-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
@media(max-width:767px){
.ska-wrap{padding:0 16px}
.ska-hero{padding:48px 0 30px}
.ska-hero--in{padding:44px 0 48px}
.ska-in,.ska-grid2,.ska-form{grid-template-columns:1fr}
.ska-rem{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;margin-left:-16px;margin-right:-16px;padding:0 16px 6px;scrollbar-width:none}
.ska-remcard{flex:0 0 82%;scroll-snap-align:start}.ska-remcard:only-child{flex-basis:100%}
.ska-row{flex-direction:column;align-items:flex-start;gap:10px}
.ska-head h2{font-size:24px}
}
/* ---------- Client dashboard ---------- */
.skd{font-family:Manrope,sans-serif;color:#2A1A24;background:linear-gradient(180deg,#F7EEF0 0,#FBF6F4 420px);padding:36px 0 80px}
.skd *{box-sizing:border-box}
.skd p{margin:0}.skd a{text-decoration:none!important}
.skd-i{flex:0 0 auto;display:block}
.skd .skd-wrap{max-width:1180px!important;width:100%;margin:0 auto!important;padding:0 24px;display:grid;grid-template-columns:270px minmax(0,1fr);gap:28px;align-items:start}
/* Sidebar */
.skd-side{position:sticky;top:110px;background:#fff;border:1px solid #EFE2E6;border-radius:26px;padding:22px;box-shadow:0 18px 40px -24px rgba(59,21,48,.25);display:flex;flex-direction:column;gap:16px}
.skd-me{display:flex;align-items:center;gap:12px}
.skd-avatar{width:52px;height:52px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(135deg,#D1127E,#7A1E5E);color:#fff;font:600 18px/1 Fraunces,serif;letter-spacing:.02em;box-shadow:0 0 0 4px #FBE3EE}
.skd-name{font:500 20px/1.2 Fraunces,serif;color:#3B1530}
.skd-since{font-size:12.5px;color:#9A8791;margin-top:2px!important}
.skd-tier{display:flex;flex-direction:column;gap:6px;padding:14px;border-radius:16px;background:#FBF1F4}
.skd-tier__k{font:700 10.5px/1 Manrope,sans-serif;letter-spacing:.16em;text-transform:uppercase;color:#D1127E}
.skd-tier__v{font-size:13px;font-weight:600;color:#3B1530}
.skd-tier__bar{height:6px;border-radius:99px;background:#F1D9E2;overflow:hidden}.skd-tier__bar i{display:block;height:100%;background:linear-gradient(90deg,#D1127E,#FF8CC8);border-radius:99px}
.skd-nav{display:flex;flex-direction:column;gap:4px}
.skd-nav a{display:flex;align-items:center;gap:12px;padding:11px 12px;border-radius:14px;color:#6E5A66!important;font:600 14.5px Manrope,sans-serif;transition:background .2s,color .2s}
.skd-nav a:hover{background:#FBF1F4;color:#3B1530!important}
.skd-nav a.is-on{background:#3B1530;color:#fff!important}
.skd-signout{display:flex;align-items:center;gap:12px;padding:8px 12px;color:#9A8791!important;font:600 13.5px Manrope,sans-serif}
.skd-help{padding:16px;border-radius:18px;background:#26101F;color:#EBD7E1;font-size:13px;line-height:1.5}
.skd-help a{display:inline-flex;align-items:center;gap:8px;margin-top:10px;padding:9px 14px;border-radius:999px;background:#fff;color:#3B1530!important;font-weight:700;font-size:13px}
/* Main */
.skd-main{min-width:0;display:flex;flex-direction:column;gap:22px}
.skd-note{padding:12px 16px;border-radius:14px;background:#FFF0F7;border:1px solid #F5C6DD;color:#3B1530;font-size:14px}
.skd-eyebrow{font:700 11px/1 Manrope,sans-serif;letter-spacing:.16em;text-transform:uppercase;color:#D1127E}
.skd-top h1{margin:8px 0 6px!important;font:400 clamp(32px,4.2vw,46px)/1.08 Fraunces,serif;color:#3B1530;letter-spacing:-.01em}
.skd-top h1 em{font-style:italic;color:#D1127E}
.skd-date{font-size:14px;color:#9A8791}
/* Next appointment */
.skd-next{position:relative;overflow:hidden;display:flex;gap:22px;align-items:stretch;padding:24px;border-radius:28px;color:#F7EAF0;background:radial-gradient(120% 140% at 100% 0%,#6A2453 0%,#3B1530 48%,#26101F 100%);box-shadow:0 28px 50px -28px rgba(38,16,31,.6)}
.skd-next::after{content:"";position:absolute;right:-60px;bottom:-80px;width:240px;height:240px;border-radius:50%;background:radial-gradient(circle,rgba(255,140,200,.35),transparent 70%)}
.skd-next__date{flex:0 0 92px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;border-radius:20px;background:rgba(255,255,255,.1);border:1px solid rgba(255,214,236,.25);padding:12px 6px}
.skd-next__date span{font:700 11px/1 Manrope,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#FFB3D9}
.skd-next__date b{font:400 44px/1 Fraunces,serif;color:#fff}
.skd-next__body{position:relative;z-index:1;min-width:0;display:flex;flex-direction:column;gap:6px}
.skd-next__k{display:flex;align-items:center;gap:10px;flex-wrap:wrap;font:700 11px/1 Manrope,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#FFB3D9}
.skd-next h2{margin:2px 0 4px!important;font:400 30px/1.15 Fraunces,serif!important;color:#fff!important}
.skd-next__meta{display:flex;align-items:center;gap:8px;font-size:14px;color:#EBD7E1}
.skd-next__meta .skd-i{width:16px;height:16px;color:#FFB3D9}
.skd-next__acts{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.skd-next--empty{background:radial-gradient(120% 140% at 100% 0%,#8A2A66 0%,#3B1530 60%,#26101F 100%)}
/* Buttons, pills, links */
.skd-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:11px 18px;border-radius:999px;font:700 13.5px Manrope,sans-serif;border:1px solid transparent;cursor:pointer;transition:transform .2s,background .2s}
.skd-btn:hover{transform:translateY(-1px)}
.skd-btn--light{background:#fff;color:#3B1530!important}
.skd-btn--ghost{background:transparent;color:#fff!important;border-color:rgba(255,214,236,.45)}
.skd-btn--ghost:hover{background:rgba(255,255,255,.08)}
.skd-btn--dark{background:#3B1530;color:#fff!important;padding:9px 16px;font-size:13px}
.skd-btn--pink{background:#D1127E;color:#fff!important;padding:13px 24px;font-size:14.5px}
.skd-btn--pink:hover{background:#B00F6A}
.skd-pill{display:inline-flex;align-items:center;padding:5px 10px;border-radius:999px;background:#F4EBEE;color:#3B1530;font:700 11.5px/1 Manrope,sans-serif;letter-spacing:.02em;text-transform:none}
.skd-pill--glow{background:#D1127E;color:#fff}
.skd-pill--live{background:#FFF0F7;color:#D1127E}
.skd-pill--done{background:#E9F5EE;color:#24704A}
.skd-link{display:inline-flex;align-items:center;gap:6px;color:#D1127E!important;font:700 13.5px Manrope,sans-serif}
.skd-link .skd-i{width:16px;height:16px;transition:transform .2s}.skd-link:hover .skd-i{transform:translateX(3px)}
.skd-link--muted{color:#9A8791!important}
.skd-t{font:600 15px/1.35 Manrope,sans-serif;color:#2A1A24}
.skd-s{margin-top:3px!important;font-size:13.5px;line-height:1.5;color:#6E5A66}.skd-s a{color:#D1127E!important;text-decoration:underline!important}
/* Stats */
.skd-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.skd-stat{display:flex;flex-direction:column;gap:2px;padding:16px;border-radius:20px;background:#fff;border:1px solid #EFE2E6;transition:transform .2s,box-shadow .2s}
.skd-stat:hover{transform:translateY(-2px);box-shadow:0 14px 28px -20px rgba(59,21,48,.4)}
.skd-stat__i{width:36px;height:36px;border-radius:12px;display:grid;place-items:center;background:#FBF1F4;color:#D1127E;margin-bottom:8px}
.skd-stat b{font:400 28px/1.1 Fraunces,serif;color:#3B1530}
.skd-stat>span:last-child{font-size:12.5px;font-weight:600;color:#9A8791}
/* Sections */
.skd-sec{background:#fff;border:1px solid #EFE2E6;border-radius:26px;padding:24px;scroll-margin-top:110px}
.skd-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}
.skd-head h2{margin:0!important;font:400 26px/1.2 Fraunces,serif!important;color:#3B1530!important}
.skd-sub{margin:18px 0 10px!important;font:700 11px/1 Manrope,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#9A8791}
.skd-sub:first-of-type{margin-top:0!important}
/* For you */
.skd-rem{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
.skd-remcard{display:flex;gap:12px;padding:16px;border-radius:18px;background:#FBF6F4;border:1px solid #F1E6E9}
.skd-remcard__i{width:38px;height:38px;flex:0 0 38px;border-radius:12px;display:grid;place-items:center;background:#fff;color:#D1127E;box-shadow:0 4px 12px -6px rgba(209,18,126,.4)}
.skd-remcard .skd-link{margin-top:10px}
/* Quick actions */
.skd-quick{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.skd-quick a{display:flex;flex-direction:column;align-items:flex-start;gap:12px;padding:16px;border-radius:20px;background:#fff;border:1px solid #EFE2E6;color:#3B1530!important;font:700 14px/1.3 Manrope,sans-serif;transition:transform .2s,border-color .2s}
.skd-quick a:hover{transform:translateY(-2px);border-color:#D1127E}
.skd-quick a span{width:40px;height:40px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(135deg,#D1127E,#8A2A66);color:#fff}
/* Lists */
.skd-list{display:flex;flex-direction:column;gap:10px}
.skd-row{display:flex;align-items:center;gap:16px;padding:14px;border-radius:18px;border:1px solid #F1E6E9;background:#FDFAFA}
.skd-chip{flex:0 0 58px;height:62px;border-radius:16px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#3B1530;color:#fff}
.skd-chip span{font:700 10px/1 Manrope,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#FFB3D9}
.skd-chip b{font:400 26px/1.05 Fraunces,serif}
.skd-row__body{flex:1;min-width:0}
.skd-row__acts{display:flex;align-items:center;gap:14px;flex-shrink:0}
.skd-timeline{position:relative;padding-left:22px}
.skd-timeline::before{content:"";position:absolute;left:6px;top:8px;bottom:8px;width:2px;background:#F1D9E2;border-radius:2px}
.skd-tl{position:relative;display:flex;align-items:center;gap:14px;padding:10px 0}
.skd-tl__dot{position:absolute;left:-21px;top:50%;width:12px;height:12px;margin-top:-6px;border-radius:50%;background:#fff;border:3px solid #D1127E}
.skd-empty{display:flex;flex-direction:column;align-items:center;text-align:center;gap:8px;padding:28px 16px;border-radius:18px;border:1px dashed #E2CCD4;color:#6E5A66;font-size:14px}
.skd-empty>.skd-i{width:30px;height:30px;color:#D1127E}
/* Orders */
.skd-orders{display:flex;flex-direction:column;gap:12px}
.skd-order{display:flex;gap:16px;padding:14px;border-radius:20px;border:1px solid #F1E6E9;background:#FDFAFA}
.skd-order__thumbs{display:flex;flex:0 0 auto}
.skd-order__thumbs img{width:64px!important;height:64px!important;object-fit:cover;border-radius:14px;border:3px solid #FDFAFA;background:#FBEFF0}
.skd-order__thumbs img+img{margin-left:-22px}
.skd-order__body{flex:1;min-width:0}
.skd-order__top{display:flex;align-items:center;justify-content:space-between;gap:10px}
.skd-order__foot{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:10px}
.skd-order__foot b{font:700 16px Manrope,sans-serif;color:#2A1A24}
.skd-order__foot span{display:flex;gap:14px}
/* Rewards */
.skd-rewards{display:grid;grid-template-columns:1.1fr 1fr;gap:14px}
.skd-loyalty{position:relative;overflow:hidden;display:flex;flex-direction:column;gap:10px;padding:22px;border-radius:24px;color:#fff;background:linear-gradient(135deg,#D1127E 0%,#8A2A66 55%,#3B1530 100%);box-shadow:0 24px 40px -26px rgba(209,18,126,.8);min-height:230px}
.skd-loyalty::before{content:"";position:absolute;inset:auto -40px -70px auto;width:220px;height:220px;border-radius:50%;border:30px solid rgba(255,255,255,.07)}
.skd-loyalty__top{display:flex;justify-content:space-between;align-items:center;font:700 11px/1 Manrope,sans-serif;letter-spacing:.16em;text-transform:uppercase;color:#FFD6EC}
.skd-loyalty__top span{font:italic 400 16px/1 Fraunces,serif;letter-spacing:0;text-transform:none;color:#fff}
.skd-loyalty__big{font:400 26px/1.15 Fraunces,serif}
.skd-stamps{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px;max-width:360px}
.skd-stamp{aspect-ratio:1;border-radius:50%;display:grid;place-items:center;border:1.5px dashed rgba(255,255,255,.55);font:700 13px Manrope,sans-serif;color:rgba(255,255,255,.75)}
.skd-stamp .skd-i{width:18px;height:18px}
.skd-stamp.is-on{background:#fff;border:0;color:#D1127E}
.skd-stamp.is-goal{border-style:solid;border-color:#fff;color:#fff}
.skd-loyalty__fine{font-size:12.5px;line-height:1.5;color:#FFD6EC;max-width:340px}
.skd-loyalty__name{margin-top:auto!important;font:600 13px Manrope,sans-serif;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.85)}
.skd-gifts{display:flex;flex-direction:column;gap:10px}
.skd-perk{display:flex;gap:12px;padding:14px;border-radius:18px;background:#FBF6F4;border:1px solid #F1E6E9}
.skd-perk__i{width:38px;height:38px;flex:0 0 38px;border-radius:12px;display:grid;place-items:center;background:#fff;color:#D1127E}
.skd-giftcta{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:18px;background:#26101F;color:#fff!important}
.skd-giftcta>.skd-i:first-child{color:#FFB3D9}
.skd-giftcta span{flex:1;display:flex;flex-direction:column;font-size:12.5px;color:#EBD7E1}.skd-giftcta b{font-size:14.5px;color:#fff}
.skd-giftrow{display:flex;gap:12px;align-items:center;padding:12px 14px;border-radius:16px;background:#FDFAFA;border:1px solid #F1E6E9}.skd-giftrow>.skd-i{color:#D1127E}
/* Recs */
.skd-recs .sk-grid{margin:0!important}
/* Profile */
.skd-form{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.skd-form label{display:flex;flex-direction:column;gap:6px;font:700 12.5px Manrope,sans-serif;color:#3B1530}
.skd-form input:not([type=checkbox]){width:100%;padding:13px 14px;border:1px solid #E2CCD4;border-radius:14px;font:500 15px Manrope,sans-serif;background:#fff;color:#2A1A24}
.skd-form input:focus{outline:0;border-color:#D1127E;box-shadow:0 0 0 3px rgba(209,18,126,.12)}
.skd-form input[disabled]{background:#FBF6F4;color:#9A8791}
.skd-switch{grid-column:1/-1;flex-direction:row!important;align-items:center;gap:12px!important;font:600 14px Manrope,sans-serif!important;cursor:pointer}
.skd-switch input{position:absolute;opacity:0;width:1px;height:1px}
.skd-switch__ui{position:relative;width:44px;height:26px;flex:0 0 44px;border-radius:99px;background:#E2CCD4;transition:background .2s}
.skd-switch__ui::after{content:"";position:absolute;left:3px;top:3px;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:transform .2s}
.skd-switch input:checked+.skd-switch__ui{background:#D1127E}
.skd-switch input:checked+.skd-switch__ui::after{transform:translateX(18px)}
.skd-switch input:focus-visible+.skd-switch__ui{box-shadow:0 0 0 3px rgba(209,18,126,.25)}
.skd-form__foot{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:4px}
@media(max-width:1024px){.skd .skd-wrap{grid-template-columns:230px minmax(0,1fr);gap:20px}.skd-quick,.skd-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.skd-rewards{grid-template-columns:1fr}}
@media(max-width:767px){
.skd{padding:16px 0 56px}
.skd .skd-wrap{display:block;padding:0 16px}
.skd-side{position:sticky;top:0;z-index:20;margin:0 -16px 16px;padding:10px 16px;border-radius:0;border:0;border-bottom:1px solid #EFE2E6;box-shadow:0 8px 20px -18px rgba(59,21,48,.5);background:rgba(255,255,255,.96);-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);gap:0}
.skd-me,.skd-tier,.skd-signout,.skd-help{display:none}
.skd-nav{flex-direction:row;overflow-x:auto;scrollbar-width:none;gap:6px}
.skd-nav::-webkit-scrollbar{display:none}
.skd-nav a{flex:0 0 auto;padding:9px 14px;border-radius:999px;font-size:13.5px;background:#FBF1F4}
.skd-nav a .skd-i{width:16px;height:16px}
.skd-main{gap:16px}
.skd-next{flex-direction:column;gap:16px;padding:20px;border-radius:24px}
.skd-next__date{flex:none;flex-direction:row;gap:10px;justify-content:flex-start;align-self:flex-start;padding:10px 14px;border-radius:16px}
.skd-next__date b{font-size:30px}
.skd-next h2{font-size:26px!important}
.skd-next__acts .skd-btn{flex:1 1 0;padding:11px 8px;min-width:0}.skd-next__acts .skd-btn:first-child:not(:only-child){flex-basis:100%}
.skd-stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.skd-stat{padding:14px}
.skd-quick{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;gap:10px;margin:0;padding:0 0 4px;scrollbar-width:none}
.skd-quick a{flex:0 0 42%;scroll-snap-align:start}
.skd-sec{padding:18px;border-radius:22px;scroll-margin-top:70px}
.skd-head h2{font-size:23px!important}
.skd-rem{display:flex;overflow-x:auto;scroll-snap-type:x mandatory;gap:10px;margin:0;padding:0 0 4px;scrollbar-width:none}
.skd-remcard{flex:0 0 86%;scroll-snap-align:start}.skd-remcard:only-child{flex-basis:100%}
.skd-row{flex-wrap:wrap;gap:12px}
.skd-row__acts{width:100%;justify-content:space-between;padding-left:70px}
.skd-order{flex-direction:column;gap:12px}
.skd-form{grid-template-columns:1fr}
.skd-form__foot{flex-direction:column;align-items:stretch}.skd-form__foot .skd-btn{width:100%}
.skd-form__foot .skd-link{justify-content:center}
}
.skd-badge{margin-left:auto;min-width:20px;height:20px;padding:0 6px;border-radius:99px;background:#D1127E;color:#fff;font:700 11px/20px Manrope,sans-serif;font-style:normal;text-align:center}
.skd-nav a.is-on .skd-badge{background:#fff;color:#D1127E}
.skd-top--page h1{margin:10px 0 4px!important}
.skd-back{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:999px;background:#fff;border:1px solid #EFE2E6;color:#6E5A66!important;font:700 12.5px Manrope,sans-serif}
.skd-back .skd-i{width:15px;height:15px}
.skd-notes{display:flex;flex-direction:column;gap:8px}
.skd-notice{display:flex;align-items:flex-start;gap:14px;padding:14px 16px;border-radius:18px;border:1px solid #F1E6E9;background:#FDFAFA;color:inherit!important;transition:border-color .2s}
a.skd-notice:hover{border-color:#D1127E}
.skd-notice.is-new{background:#FFF6FA;border-color:#F5C6DD}
.skd-notice__i{width:38px;height:38px;flex:0 0 38px;border-radius:12px;display:grid;place-items:center;background:#fff;color:#D1127E;box-shadow:0 4px 12px -6px rgba(209,18,126,.4)}
.skd-notice__b{flex:1;min-width:0;display:flex;flex-direction:column}
.skd-notice__t{font-size:12px;color:#9A8791;white-space:nowrap;margin-top:2px}
.skd-notice.is-new .skd-t::after{content:"";display:inline-block;width:8px;height:8px;border-radius:50%;background:#D1127E;margin-left:8px;vertical-align:middle}
@media(max-width:767px){.skd-back{display:none!important}.skd-top--page h1{margin-top:0!important}.skd-notice{flex-wrap:wrap}.skd-notice__t{width:100%;padding-left:52px;margin-top:-4px}.skd-badge{margin-left:6px}.skd-top--page h1{font-size:30px!important}}
</style>
		<?php
	}
);

/* ---------------------------------------------------------------------------
 * Accounts are created automatically when someone books or orders.
 * New clients are signed in straight away and emailed a link to their dashboard.
 * Existing accounts are never signed in this way (that needs the emailed link).
 * ------------------------------------------------------------------------ */
function skynco_ensure_client_account( $email, $first = '', $last = '', $phone = '' ) {
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) ) {
		return [ 0, false ];
	}
	$user = get_user_by( 'email', $email );
	if ( $user ) {
		return [ $user->ID, false ];
	}
	$uid = wp_insert_user(
		[
			'user_login'   => skynco_unique_login( $email ),
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 24 ),
			'role'         => get_role( 'customer' ) ? 'customer' : 'subscriber',
			'first_name'   => $first,
			'last_name'    => $last,
			'display_name' => $first ?: strstr( $email, '@', true ),
		]
	);
	if ( is_wp_error( $uid ) ) {
		return [ 0, false ];
	}
	foreach ( [ 'billing_first_name' => $first, 'billing_last_name' => $last, 'billing_email' => $email, 'billing_phone' => $phone ] as $k => $v ) {
		if ( $v ) {
			update_user_meta( $uid, $k, $v );
		}
	}
	skynco_link_client_records( get_user_by( 'id', $uid ) );
	$link = skynco_dashboard_link( $email );
	$body = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#2A1A24">'
		. '<h2 style="font-family:Georgia,serif;color:#3B1530;font-weight:500">Your Skyn&amp;Co. account is ready' . ( $first ? ', ' . esc_html( $first ) : '' ) . '</h2>'
		. '<p>We made you a client dashboard so you can see your visits and orders, rebook in a tap and collect loyalty rewards. No password needed.</p>'
		. '<p style="margin:28px 0"><a href="' . esc_url( $link ) . '" style="background:#D1127E;color:#fff;text-decoration:none;padding:14px 26px;border-radius:999px;font-weight:bold">Open my dashboard</a></p>'
		. '<p style="font-size:13px;color:#6E5A66">Next time, just enter your email at ' . esc_html( preg_replace( '#^https?://#', '', skynco_account_url() ) ) . ' and we will send you a new link.</p></div>';
	wp_mail( $email, 'Your Skyn&Co. client dashboard', $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
	return [ $uid, true ];
}

function skynco_sign_in_new_client( $uid ) {
	if ( $uid && ! is_user_logged_in() && ! headers_sent() ) {
		wp_set_current_user( $uid );
		wp_set_auth_cookie( $uid, true );
	}
}

/* LatePoint bookings. */
add_action(
	'latepoint_order_created',
	function ( $order ) {
		if ( empty( $order->customer_id ) || ! class_exists( 'OsCustomerModel' ) ) {
			return;
		}
		$c = new OsCustomerModel( $order->customer_id );
		if ( empty( $c->email ) ) {
			return;
		}
		[ $uid, $new ] = skynco_ensure_client_account( $c->email, $c->first_name, $c->last_name, $c->phone );
		if ( $new ) {
			skynco_sign_in_new_client( $uid );
		}
	},
	20
);

/* WooCommerce orders placed as a guest. */
add_action(
	'woocommerce_checkout_order_processed',
	function ( $order_id ) {
		$o = wc_get_order( $order_id );
		if ( ! $o || $o->get_customer_id() ) {
			return;
		}
		[ $uid, $new ] = skynco_ensure_client_account( $o->get_billing_email(), $o->get_billing_first_name(), $o->get_billing_last_name(), $o->get_billing_phone() );
		if ( $uid ) {
			$o->set_customer_id( $uid );
			$o->save();
		}
		if ( $new ) {
			skynco_sign_in_new_client( $uid );
		}
	},
	5
);

/* LatePoint's own "My bookings" area is replaced by the client dashboard. */
add_action(
	'template_redirect',
	function () {
		if ( is_page( 'my-bookings' ) ) {
			wp_safe_redirect( skynco_account_url() );
			exit;
		}
	}
);

/* Booking confirmation: point to the dashboard. */
add_action(
	'latepoint_after_step_content',
	function ( $step ) {
		if ( 'confirmation' === $step ) {
			echo '<p class="sk-lpacct" style="margin:18px 0 0;padding:14px 18px;border-radius:14px;background:#FBEFF0;font:500 14px/1.5 Manrope,sans-serif;color:#3B1530">Your visit is saved in <a href="' . esc_url( skynco_account_url() ) . '">your client dashboard</a>, where you can see your bookings, rebook and collect rewards.</p>';
		}
	},
	5
);

/* Mobile: keep the current page's tab in view. */
add_action(
	'wp_footer',
	function () {
		if ( is_page( 'account' ) && is_user_logged_in() ) {
			echo '<script>(function(){var a=document.querySelector(".skd-nav a.is-on");if(a&&a.parentNode.scrollWidth>a.parentNode.clientWidth){a.parentNode.scrollLeft=a.offsetLeft-16;}})();</script>';
		}
	}
);

/* ---------------------------------------------------------------------------
 * Personal "Open my dashboard" button in booking and order emails.
 * ------------------------------------------------------------------------ */
add_filter(
	'latepoint_replace_customer_vars',
	function ( $text, $customer ) {
		if ( false !== strpos( (string) $text, '{{skynco_dashboard_url}}' ) && ! empty( $customer->email ) ) {
			$text = str_replace( '{{skynco_dashboard_url}}', esc_url( skynco_dashboard_link( $customer->email, 'visits' ) ), $text );
		}
		return $text;
	},
	10,
	2
);

add_action(
	'woocommerce_email_after_order_table',
	function ( $order, $sent_to_admin ) {
		if ( $sent_to_admin || ! $order || ! $order->get_billing_email() ) {
			return;
		}
		echo '<p style="margin:24px 0;text-align:center"><a href="' . esc_url( skynco_dashboard_link( $order->get_billing_email(), 'orders' ) ) . '" style="display:inline-block;background:#D1127E;color:#ffffff;text-decoration:none;padding:13px 26px;border-radius:999px;font-weight:bold">Open my client dashboard</a></p><p style="text-align:center;font-size:13px;color:#6E5A66">Track this order, your visits and rewards. No password needed.</p>';
	},
	20,
	2
);

/* Site-wide: nothing may push the page wider than the screen (no sideways sliding on phones). */
add_action(
	'wp_head',
	function () {
		// Brand fonts on every page (Elementor only loads them on pages it builds).
		echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..700;1,9..144,300..700&family=Manrope:wght@400..800&display=swap">';
		echo '<style id="skynco-noslide">html,body{max-width:100%;overflow-x:clip}@supports not (overflow:clip){body{overflow-x:hidden}}img,video,iframe{max-width:100%}</style>';
	},
	1
);
