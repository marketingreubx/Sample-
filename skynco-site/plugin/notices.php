<?php
/**
 * Notification centre: a reliable on-site feed for clients (client dashboard →
 * Notifications) and for the studio (Studio → Notifications), in addition to email.
 *
 * @package SkyncoStudio
 */

defined( 'ABSPATH' ) || exit;

const SKYNCO_NOTICES_DB = '1';

function skynco_notices_table() {
	global $wpdb;
	return $wpdb->prefix . 'skynco_notices';
}

add_action(
	'init',
	function () {
		if ( get_option( 'skynco_notices_db' ) === SKYNCO_NOTICES_DB ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = skynco_notices_table();
		dbDelta(
			"CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			audience varchar(10) NOT NULL DEFAULT 'client',
			email varchar(190) NOT NULL DEFAULT '',
			type varchar(30) NOT NULL DEFAULT '',
			ref varchar(60) NOT NULL DEFAULT '',
			title varchar(255) NOT NULL DEFAULT '',
			body text NOT NULL,
			link varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			read_at datetime NULL,
			PRIMARY KEY  (id),
			KEY aud_email (audience,email),
			UNIQUE KEY uniq_ref (audience,email,type,ref)
		) {$wpdb->get_charset_collate()};"
		);
		update_option( 'skynco_notices_db', SKYNCO_NOTICES_DB );
	}
);

/**
 * Add a notice. $audience is 'studio' or 'client' (with the client's email).
 * The same type + ref is only stored once, so repeated hooks never duplicate.
 */
function skynco_notice_add( $audience, $email, $type, $ref, $title, $body = '', $link = '' ) {
	global $wpdb;
	$wpdb->query( // phpcs:ignore
		$wpdb->prepare(
			'INSERT IGNORE INTO ' . skynco_notices_table() . ' (audience,email,type,ref,title,body,link,created_at) VALUES (%s,%s,%s,%s,%s,%s,%s,%s)', // phpcs:ignore
			$audience,
			'studio' === $audience ? '' : strtolower( (string) $email ),
			$type,
			(string) $ref,
			mb_substr( wp_strip_all_tags( $title ), 0, 250 ),
			wp_strip_all_tags( $body ),
			(string) $link,
			current_time( 'mysql', true )
		)
	);
}

function skynco_notices_get( $audience, $email = '', $limit = 50 ) {
	global $wpdb;
	return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . skynco_notices_table() . ' WHERE audience = %s AND email = %s ORDER BY id DESC LIMIT %d', $audience, strtolower( $email ), $limit ) ); // phpcs:ignore
}

function skynco_notices_unread( $audience, $email = '' ) {
	global $wpdb;
	if ( get_option( 'skynco_notices_db' ) !== SKYNCO_NOTICES_DB ) {
		return 0;
	}
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . skynco_notices_table() . ' WHERE audience = %s AND email = %s AND read_at IS NULL', $audience, strtolower( $email ) ) ); // phpcs:ignore
}

function skynco_notices_mark_read( $audience, $email = '' ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . skynco_notices_table() . ' SET read_at = %s WHERE audience = %s AND email = %s AND read_at IS NULL', current_time( 'mysql', true ), $audience, strtolower( $email ) ) ); // phpcs:ignore
}

function skynco_notice_time( $mysql_gmt ) {
	$ts   = strtotime( $mysql_gmt . ' UTC' );
	$diff = time() - $ts;
	if ( $diff < HOUR_IN_SECONDS ) {
		return max( 1, (int) round( $diff / 60 ) ) . ' min ago';
	}
	if ( $diff < DAY_IN_SECONDS ) {
		return (int) round( $diff / HOUR_IN_SECONDS ) . ' h ago';
	}
	return wp_date( 'M j, g:i a', $ts );
}

/* ---------------------------------------------------------------------------
 * Events
 * ------------------------------------------------------------------------ */
function skynco_notice_booking_row( $id ) {
	global $wpdb;
	$p = $wpdb->prefix . 'latepoint_';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wpdb->get_row( $wpdb->prepare( "SELECT b.id, b.booking_code, b.start_date, b.start_time, b.status, c.first_name, c.last_name, c.email, c.phone, s.name AS service FROM {$p}bookings b LEFT JOIN {$p}customers c ON c.id = b.customer_id LEFT JOIN {$p}services s ON s.id = b.service_id WHERE b.id = %d", $id ) );
}

function skynco_notice_when( $b ) {
	$dt = date_create( $b->start_date . ' 00:00', wp_timezone() );
	return $dt ? wp_date( 'D, M j \a\t g:i a', $dt->getTimestamp() + (int) $b->start_time * 60 ) : $b->start_date;
}

add_action(
	'latepoint_booking_created',
	function ( $booking ) {
		if ( empty( $booking->id ) ) {
			return;
		}
		$b = skynco_notice_booking_row( (int) $booking->id );
		if ( ! $b ) {
			return;
		}
		$when = skynco_notice_when( $b );
		$name = trim( $b->first_name . ' ' . $b->last_name );
		skynco_notice_add( 'client', $b->email, 'booking', $b->id, 'Booking confirmed: ' . $b->service, $when . ' at Skyn&Co., 150 Arsenal St, Suite 210, Watertown. Code ' . $b->booking_code . '.', skynco_account_url( 'visits' ) );
		skynco_notice_add( 'studio', '', 'booking', $b->id, 'New booking: ' . $b->service, $name . ' · ' . $when . ' · ' . $b->phone . ' · ' . $b->email, admin_url( 'admin.php?page=latepoint&route_name=bookings__index' ) );
	},
	5
);

add_action(
	'latepoint_booking_updated',
	function ( $booking, $old = null ) {
		if ( empty( $booking->id ) || empty( $old ) || $booking->status === $old->status ) {
			return;
		}
		$b = skynco_notice_booking_row( (int) $booking->id );
		if ( ! $b ) {
			return;
		}
		$when  = skynco_notice_when( $b );
		$label = 'cancelled' === $b->status ? 'cancelled' : ( 'approved' === $b->status ? 'confirmed' : $b->status );
		skynco_notice_add( 'client', $b->email, 'booking-' . $b->status, $b->id, 'Your ' . $b->service . ' is ' . $label, $when . '. Questions? Text (857) 228-4708.', skynco_account_url( 'visits' ) );
		skynco_notice_add( 'studio', '', 'booking-' . $b->status, $b->id, 'Booking ' . $label . ': ' . $b->service, trim( $b->first_name . ' ' . $b->last_name ) . ' · ' . $when, admin_url( 'admin.php?page=latepoint&route_name=bookings__index' ) );
	},
	10,
	2
);

add_action(
	'woocommerce_checkout_order_processed',
	function ( $order_id ) {
		$o = wc_get_order( $order_id );
		if ( ! $o ) {
			return;
		}
		$items = [];
		foreach ( $o->get_items() as $it ) {
			$items[] = $it->get_name() . ( $it->get_quantity() > 1 ? ' × ' . $it->get_quantity() : '' );
		}
		$total = html_entity_decode( wp_strip_all_tags( wc_price( $o->get_total() ) ) );
		skynco_notice_add( 'client', $o->get_billing_email(), 'order', $o->get_id(), 'Order #' . $o->get_order_number() . ' received', implode( ', ', $items ) . ' · ' . $total . ' · ' . $o->get_shipping_method(), skynco_account_url( 'orders' ) );
		skynco_notice_add( 'studio', '', 'order', $o->get_id(), 'New order #' . $o->get_order_number() . ' · ' . $total, trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ) . ' · ' . implode( ', ', $items ) . ' · ' . $o->get_shipping_method(), $o->get_edit_order_url() );
	},
	1
);

add_action(
	'woocommerce_order_status_changed',
	function ( $order_id, $from, $to ) {
		$msgs = [
			'completed' => [ 'is complete', 'Thank you! Picking up? It is ready at the studio.' ],
			'cancelled' => [ 'was cancelled', 'Questions? Text (857) 228-4708.' ],
			'refunded'  => [ 'was refunded', 'The refund goes back to your original payment method.' ],
		];
		$o = wc_get_order( $order_id );
		if ( ! $o || ! isset( $msgs[ $to ] ) ) {
			return;
		}
		skynco_notice_add( 'client', $o->get_billing_email(), 'order-' . $to, $order_id, 'Order #' . $o->get_order_number() . ' ' . $msgs[ $to ][0], $msgs[ $to ][1], skynco_account_url( 'orders' ) );
	},
	10,
	3
);

add_action(
	'wp_insert_post',
	function ( $id, $post, $update ) {
		if ( $update || 'skynco_message' !== $post->post_type ) {
			return;
		}
		skynco_notice_add( 'studio', '', 'message', $id, 'New website message: ' . $post->post_title, wp_trim_words( $post->post_content, 30 ), admin_url( 'edit.php?post_type=skynco_message' ) );
	},
	10,
	3
);

/* ---------------------------------------------------------------------------
 * Studio side: menu badge, admin bar bell, notifications page.
 * ------------------------------------------------------------------------ */
add_action(
	'admin_menu',
	function () {
		$n     = skynco_notices_unread( 'studio' );
		$badge = $n ? ' <span class="awaiting-mod">' . $n . '</span>' : '';
		add_submenu_page( 'skynco-studio', 'Notifications', 'Notifications' . $badge, SKYNCO_STUDIO_CAP, 'skynco-notices', 'skynco_page_notices', 1 );
		global $menu;
		foreach ( (array) $menu as $i => $m ) {
			if ( isset( $m[2] ) && 'skynco-studio' === $m[2] && $n ) {
				$menu[ $i ][0] .= ' <span class="awaiting-mod">' . $n . '</span>'; // phpcs:ignore
			}
		}
	},
	40
);

add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( ! current_user_can( SKYNCO_STUDIO_CAP ) ) {
			return;
		}
		$n = skynco_notices_unread( 'studio' );
		$bar->add_node(
			[
				'id'    => 'skynco-notices',
				'title' => '<span class="ab-icon dashicons dashicons-bell" style="top:2px"></span>' . ( $n ? '<span style="background:#D1127E;color:#fff;border-radius:99px;padding:0 7px;font-size:11px;line-height:18px;display:inline-block">' . $n . '</span>' : '' ),
				'href'  => admin_url( 'admin.php?page=skynco-notices' ),
				'meta'  => [ 'title' => $n ? $n . ' new notifications' : 'Notifications' ],
			]
		);
	},
	80
);

function skynco_page_notices() {
	$list = skynco_notices_get( 'studio', '', 100 );
	skynco_notices_mark_read( 'studio' );
	$icons = [ 'booking' => 'calendar-alt', 'order' => 'cart', 'message' => 'email-alt' ];
	echo '<div class="wrap skynco-wrap"><h1>Notifications</h1><p class="description">Every booking, order and website message. You also get these by email at ' . esc_html( get_option( 'admin_email' ) ) . '.</p>';
	echo '<div class="sk-card" style="padding:0;overflow:hidden">';
	if ( ! $list ) {
		echo '<p style="padding:20px">Nothing yet. New bookings and orders will appear here the moment they come in.</p>';
	}
	foreach ( $list as $n ) {
		$base = strtok( $n->type, '-' );
		echo '<a href="' . esc_url( $n->link ) . '" style="display:flex;gap:14px;align-items:flex-start;padding:16px 20px;border-bottom:1px solid #F1E6E9;text-decoration:none;color:#2A1A24;' . ( $n->read_at ? '' : 'background:#FFF6FA' ) . '">';
		echo '<span class="dashicons dashicons-' . esc_attr( $icons[ $base ] ?? 'bell' ) . '" style="color:#D1127E;margin-top:2px"></span><span style="flex:1"><b style="display:block;font-size:14px">' . esc_html( $n->title ) . ( $n->read_at ? '' : ' <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#D1127E;margin-left:4px"></span>' ) . '</b><span style="display:block;color:#6E5A66;margin-top:3px">' . esc_html( $n->body ) . '</span></span><span style="color:#9A8791;font-size:12px;white-space:nowrap">' . esc_html( skynco_notice_time( $n->created_at ) ) . '</span></a>';
	}
	echo '</div></div>';
}
