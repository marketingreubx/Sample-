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

function skynco_account_url() {
	return home_url( '/account/' );
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
		$token = wp_generate_password( 40, false );
		set_transient( 'skynco_ml_' . hash( 'sha256', $token ), strtolower( $email ), 30 * MINUTE_IN_SECONDS );
		$link = add_query_arg( 'sk_login', $token, $back );
		$body = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#2A1A24">'
			. '<h2 style="font-family:Georgia,serif;color:#3B1530;font-weight:500">Your Skyn&amp;Co. sign-in link</h2>'
			. '<p>Tap the button below to open your client dashboard. The link works once and expires in 30 minutes.</p>'
			. '<p style="margin:28px 0"><a href="' . esc_url( $link ) . '" style="background:#D1127E;color:#fff;text-decoration:none;padding:14px 26px;border-radius:999px;font-weight:bold">Open my dashboard</a></p>'
			. '<p style="font-size:13px;color:#6E5A66">If you didn’t ask for this, you can ignore this email.</p></div>';
		wp_mail( $email, 'Your Skyn&Co. sign-in link', $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
	}
	wp_safe_redirect( add_query_arg( [ 'sk' => 'sent', 'e' => rawurlencode( $email ) ], $back ) );
	exit;
}

add_action(
	'template_redirect',
	function () {
		if ( empty( $_GET['sk_login'] ) ) {
			return;
		}
		$key   = 'skynco_ml_' . hash( 'sha256', sanitize_text_field( wp_unslash( $_GET['sk_login'] ) ) );
		$email = get_transient( $key );
		if ( ! $email ) {
			wp_safe_redirect( add_query_arg( 'sk', 'expired', skynco_account_url() ) );
			exit;
		}
		delete_transient( $key );
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
		wp_safe_redirect( skynco_account_url() );
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
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT bk.id, bk.booking_code, bk.start_date, bk.start_time, bk.start_datetime_utc, bk.duration, bk.status, bk.service_id, sv.name AS service FROM {$b} bk LEFT JOIN {$s} sv ON sv.id = bk.service_id WHERE bk.customer_id IN ( SELECT id FROM {$c} WHERE email = %s OR wordpress_user_id = %d ) AND bk.status NOT IN ('cancelled') ORDER BY bk.start_datetime_utc ASC", $user->user_email, $user->ID ) );
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
		wp_safe_redirect( add_query_arg( 'sk', 'saved', skynco_account_url() ) . '#profile' );
		exit;
	}
);

/* ---------------------------------------------------------------------------
 * Shortcode
 * ------------------------------------------------------------------------ */
add_shortcode( 'skynco_account', 'skynco_account_shortcode' );
function skynco_account_shortcode() {
	$msg = sanitize_key( $_GET['sk'] ?? '' );
	if ( ! is_user_logged_in() ) {
		return skynco_account_signin( $msg );
	}
	$user     = wp_get_current_user();
	$first    = $user->first_name ?: skynco_client_first_name( $user->user_email ) ?: $user->display_name;
	$bookings = skynco_client_bookings( $user );
	$orders   = skynco_client_orders( $user );
	$book     = home_url( '/book/' );
	$tel      = 'sms:+18572284708';
	$h        = '<div class="ska">';

	// Hero.
	$next = $bookings['upcoming'][0] ?? null;
	$h   .= '<section class="ska-hero"><div class="ska-wrap"><p class="ska-eyebrow">Your client dashboard</p><h1>Hi ' . esc_html( $first ) . ', <em>welcome back.</em></h1>';
	$h   .= '<p class="ska-lead">' . ( $next ? 'Your next visit is <b>' . esc_html( $next->when ) . '</b>.' : 'Everything for your visits, orders and rewards in one place.' ) . '</p>';
	$h   .= '<nav class="ska-tabs"><a href="#visits">Visits</a><a href="#orders">Orders</a><a href="#rewards">Rewards &amp; gifts</a><a href="#profile">Profile</a></nav></div></section>';
	if ( 'saved' === $msg ) {
		$h .= '<div class="ska-wrap"><p class="ska-note">Your details are saved.</p></div>';
	}

	// Reminders.
	$rem  = [];
	$last = $bookings['past'][0] ?? null;
	if ( $next ) {
		$days  = max( 0, (int) floor( ( $next->ts - time() ) / DAY_IN_SECONDS ) );
		$rem[] = [ 'Coming up', esc_html( $next->service ) . ' in ' . ( $days ? $days . ' day' . ( 1 === $days ? '' : 's' ) : 'less than a day' ), 'Arrive with clean skin and skip retinoids for 2 days before.', '', '' ];
	} elseif ( $last ) {
		$due   = $last->ts + skynco_rebook_weeks( $last->service ) * WEEK_IN_SECONDS;
		$label = $due <= time() ? 'Your next ' . esc_html( $last->service ) . ' is due now' : 'Next ' . esc_html( $last->service ) . ' due ' . wp_date( 'F j', $due );
		$rem[] = [ 'Time to rebook', $label, 'Regular visits keep your results going. Pick a time that suits you.', add_query_arg( 'service', (int) $last->service_id, $book ), 'Rebook' ];
	} else {
		$rem[] = [ 'First visit', 'Start with the New Client Facial', 'A full consultation and a facial made for your skin.', $book, 'Book now' ];
	}
	foreach ( $orders as $o ) {
		$age = time() - ( $o->get_date_created() ? $o->get_date_created()->getTimestamp() : time() );
		if ( $age > 60 * DAY_IN_SECONDS ) {
			foreach ( $o->get_items() as $it ) {
				if ( ! skynco_is_gift_item( $it ) && ( $p = $it->get_product() ) && $p->is_purchasable() ) {
					$rem[] = [ 'Running low?', 'Restock your ' . esc_html( $p->get_name() ), 'Most bottles last about two months.', add_query_arg( 'add-to-cart', $p->get_id(), wc_get_cart_url() ), 'Add to cart' ];
					break 2;
				}
			}
		}
	}
	$h .= '<section class="ska-wrap ska-rem">';
	foreach ( $rem as $r ) {
		$h .= '<div class="ska-remcard"><p class="ska-k">' . esc_html( $r[0] ) . '</p><p class="ska-t">' . $r[1] . '</p><p class="ska-s">' . esc_html( $r[2] ) . '</p>' . ( $r[3] ? '<a class="ska-btn" href="' . esc_url( $r[3] ) . '">' . esc_html( $r[4] ) . '</a>' : '' ) . '</div>';
	}
	$h .= '</section>';

	// Visits.
	$h .= '<section class="ska-wrap ska-sec" id="visits"><div class="ska-head"><h2>Visits</h2><a class="ska-btn ska-btn--ghost" href="' . esc_url( $book ) . '">Book a visit</a></div>';
	if ( $bookings['upcoming'] ) {
		foreach ( $bookings['upcoming'] as $b ) {
			$h .= '<div class="ska-row ska-row--next"><div><p class="ska-t">' . esc_html( $b->service ) . '</p><p class="ska-s">' . esc_html( $b->when ) . ' · ' . (int) $b->duration . ' min · Code ' . esc_html( $b->booking_code ) . '</p></div><div class="ska-acts"><span class="ska-pill">' . esc_html( ucfirst( $b->status ) ) . '</span><a href="' . esc_url( $tel . '?&body=' . rawurlencode( 'Hi Hana, I need to change my booking ' . $b->booking_code ) ) . '">Change</a></div></div>';
		}
	} else {
		$h .= '<p class="ska-empty">No upcoming visits. <a href="' . esc_url( $book ) . '">Book your next one</a>.</p>';
	}
	if ( $bookings['past'] ) {
		$h .= '<p class="ska-sub">Past visits</p>';
		foreach ( array_slice( $bookings['past'], 0, 8 ) as $b ) {
			$h .= '<div class="ska-row"><div><p class="ska-t">' . esc_html( $b->service ) . '</p><p class="ska-s">' . esc_html( wp_date( 'F j, Y', $b->ts ) ) . '</p></div><div class="ska-acts"><a href="' . esc_url( add_query_arg( 'service', (int) $b->service_id, $book ) ) . '">Book again</a></div></div>';
		}
	}
	$h .= '</section>';

	// Orders.
	$h .= '<section class="ska-wrap ska-sec" id="orders"><div class="ska-head"><h2>Orders</h2><a class="ska-btn ska-btn--ghost" href="' . esc_url( home_url( '/shop/' ) ) . '">Shop</a></div>';
	if ( $orders ) {
		foreach ( array_slice( $orders, 0, 10 ) as $o ) {
			$names = [];
			foreach ( $o->get_items() as $it ) {
				$names[] = $it->get_name() . ( $it->get_quantity() > 1 ? ' × ' . $it->get_quantity() : '' );
			}
			$first_item = current( $o->get_items() );
			$again      = $first_item && ( $p = $first_item->get_product() ) && $p->is_purchasable() ? '<a href="' . esc_url( add_query_arg( 'add-to-cart', $p->get_id(), wc_get_cart_url() ) ) . '">Buy again</a>' : '';
			$view       = (int) $o->get_customer_id() === $user->ID ? '<a href="' . esc_url( $o->get_view_order_url() ) . '">Details</a>' : '';
			$h         .= '<div class="ska-row"><div><p class="ska-t">Order #' . esc_html( $o->get_order_number() ) . ' · ' . wp_kses_post( $o->get_formatted_order_total() ) . '</p><p class="ska-s">' . esc_html( wc_format_datetime( $o->get_date_created() ) ) . ' · ' . esc_html( implode( ', ', $names ) ) . '</p></div><div class="ska-acts"><span class="ska-pill">' . esc_html( wc_get_order_status_name( $o->get_status() ) ) . '</span>' . $view . $again . '</div></div>';
		}
	} else {
		$h .= '<p class="ska-empty">No orders yet. Your first home-care order is 10% off with code <b>GLOW10</b>.</p>';
	}
	$h .= '</section>';

	// Rewards and gifts.
	[ $goal, $treat ] = skynco_loyalty();
	$visits = count( $bookings['past'] );
	$stamp  = $visits % $goal;
	$earned = (int) floor( $visits / $goal );
	$dots   = '';
	for ( $i = 1; $i <= $goal; $i++ ) {
		$dots .= '<span class="ska-dot' . ( $i <= $stamp ? ' is-on' : '' ) . '">' . ( $i === $goal ? '★' : $i ) . '</span>';
	}
	$gifts = [];
	foreach ( $orders as $o ) {
		foreach ( $o->get_items() as $it ) {
			if ( skynco_is_gift_item( $it ) ) {
				$gifts[] = '<div class="ska-row"><div><p class="ska-t">' . esc_html( $it->get_name() ) . ' · ' . wp_kses_post( wc_price( $it->get_total() ) ) . '</p><p class="ska-s">Bought ' . esc_html( wc_format_datetime( $o->get_date_created() ) ) . ' · sent by email</p></div></div>';
			}
		}
	}
	$bday = get_user_meta( $user->ID, 'skynco_birthday', true );
	$h   .= '<section class="ska-wrap ska-sec" id="rewards"><div class="ska-head"><h2>Rewards &amp; gifts</h2></div><div class="ska-grid2">';
	$h   .= '<div class="ska-card"><p class="ska-k">Loyalty card</p><p class="ska-t">' . ( $stamp ? ( $goal - $stamp ) . ' more visit' . ( $goal - $stamp > 1 ? 's' : '' ) . ' to your treat' : 'Every ' . $goal . 'th visit is on us' ) . '</p><div class="ska-dots">' . $dots . '</div><p class="ska-s">Your ' . $goal . 'th visit includes ' . esc_html( $treat ) . '.' . ( $earned ? ' You’ve earned ' . $earned . ' so far.' : '' ) . '</p></div>';
	$h   .= '<div class="ska-card"><p class="ska-k">Your gifts</p>' . ( $orders ? '' : '<p class="ska-t">Welcome gift: 10% off</p><p class="ska-s">Use <b>GLOW10</b> on your first home-care order.</p>' ) . '<p class="ska-t">' . ( $bday ? 'Birthday treat: 15% off in ' . esc_html( wp_date( 'F', strtotime( $bday ) ) ) : 'Add your birthday for a treat' ) . '</p><p class="ska-s">' . ( $bday ? 'We’ll send your code at the start of your birthday month.' : '<a href="#profile">Add it in your profile</a> and get 15% off in your birthday month.' ) . '</p>';
	$h   .= '<a class="ska-btn ska-btn--ghost" href="' . esc_url( home_url( '/product/skynco-gift-card/' ) ) . '">Send a gift card</a></div></div>';
	if ( $gifts ) {
		$h .= '<p class="ska-sub">Gift cards you bought</p>' . implode( '', $gifts );
	}
	$h .= '</section>';

	// Recommended.
	$slugs = 'glow-kit,daily-mineral-spf-40,hydrating-hyaluronic-serum';
	if ( $last && function_exists( 'lux_tslug' ) && function_exists( 'lux_shop_recs_for' ) && ( $ts = lux_tslug( $last->service ) ) ) {
		$slugs = implode( ',', array_slice( lux_shop_recs_for( $ts ), 0, 3 ) );
	}
	$h .= '<section class="ska-wrap ska-sec"><div class="ska-head"><h2>Picked for your skin</h2></div>' . do_shortcode( '[skynco_products slugs="' . esc_attr( $slugs ) . '" columns="3"]' ) . '</section>';

	// Profile.
	$phone = get_user_meta( $user->ID, 'billing_phone', true );
	$wa    = '0' !== get_user_meta( $user->ID, 'skynco_wa_optin', true );
	$h    .= '<section class="ska-wrap ska-sec" id="profile"><div class="ska-head"><h2>Profile</h2><a class="ska-link" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">Sign out</a></div>';
	$h    .= '<form class="ska-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'skynco_profile', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="skynco_profile">';
	$h    .= '<label>First name<input name="first_name" value="' . esc_attr( $user->first_name ) . '" autocomplete="given-name"></label>';
	$h    .= '<label>Email<input value="' . esc_attr( $user->user_email ) . '" disabled></label>';
	$h    .= '<label>Mobile (WhatsApp)<input name="phone" type="tel" value="' . esc_attr( $phone ) . '" autocomplete="tel" placeholder="(617) 555-0123"></label>';
	$h    .= '<label>Birthday<input name="birthday" type="date" value="' . esc_attr( $bday ) . '"></label>';
	$h    .= '<label class="ska-check"><input type="checkbox" name="wa" value="1"' . checked( $wa, true, false ) . '> Send my booking and order updates on WhatsApp</label>';
	$h    .= '<p><button class="ska-btn" type="submit">Save details</button></p></form></section>';

	return $h . '</div>';
}

function skynco_account_signin( $msg ) {
	$notes = [
		'sent'      => 'Check your inbox. We sent a sign-in link to <b>' . esc_html( sanitize_email( wp_unslash( $_GET['e'] ?? '' ) ) ) . '</b>. It expires in 30 minutes.',
		'expired'   => 'That link has expired or was already used. Enter your email for a new one.',
		'bad-email' => 'Please enter a valid email address.',
	];
	$h  = '<div class="ska"><section class="ska-hero ska-hero--in"><div class="ska-wrap ska-in">';
	$h .= '<div><p class="ska-eyebrow">Client dashboard</p><h1>Your visits, orders <em>and rewards.</em></h1><p class="ska-lead">See upcoming appointments, rebook in a tap, track orders and collect your loyalty treats.</p></div>';
	$h .= '<div class="ska-signin"><h2>Sign in or create an account</h2><p class="ska-s">No password needed. Use the email you booked or ordered with and we’ll send you a secure link.</p>';
	if ( isset( $notes[ $msg ] ) ) {
		$h .= '<p class="ska-note">' . $notes[ $msg ] . '</p>';
	}
	$h .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="skynco_magic"><input type="hidden" name="_sknonce" value="' . esc_attr( wp_create_nonce( 'skynco_magic' ) ) . '">';
	$h .= '<label class="ska-sr" for="ska-email">Email</label><input id="ska-email" type="email" name="email" required autocomplete="email" placeholder="you@example.com"><button class="ska-btn" type="submit">Email me a sign-in link</button></form>';
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
.ska-card .ska-t+.ska-s{margin-bottom:12px}
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
.ska-remcard{flex:0 0 82%;scroll-snap-align:start}
.ska-row{flex-direction:column;align-items:flex-start;gap:10px}
.ska-head h2{font-size:24px}
}
</style>
		<?php
	}
);
