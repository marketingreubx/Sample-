<?php
/**
 * Branded booking confirmation (LatePoint confirmation step) and calendar files.
 *
 * @package SkyncoStudio
 */

defined( 'ABSPATH' ) || exit;

function skynco_order_bookings( $order_id ) {
	global $wpdb;
	$p = $wpdb->prefix . 'latepoint_';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT b.id, b.booking_code, b.start_date, b.start_time, b.end_time, b.start_datetime_utc, b.end_datetime_utc, b.duration, b.status, s.name AS service FROM {$p}bookings b JOIN {$p}order_items oi ON oi.id = b.order_item_id LEFT JOIN {$p}services s ON s.id = b.service_id WHERE oi.order_id = %d ORDER BY b.start_datetime_utc", $order_id ) );
}

function skynco_booking_when( $b ) {
	$dt = date_create( $b->start_date . ' 00:00', wp_timezone() );
	if ( ! $dt ) {
		return [ $b->start_date, '' ];
	}
	$s = $dt->getTimestamp() + (int) $b->start_time * 60;
	$e = $dt->getTimestamp() + (int) $b->end_time * 60;
	return [ wp_date( 'l, F j', $s ), wp_date( 'g:i a', $s ) . ' – ' . wp_date( 'g:i a', $e ) ];
}

function skynco_ics_url( $b ) {
	return add_query_arg( [ 'action' => 'skynco_ics', 'b' => $b->booking_code, 'k' => substr( wp_hash( 'ics' . $b->booking_code ), 0, 12 ) ], admin_url( 'admin-post.php' ) );
}

function skynco_gcal_url( $b ) {
	$f = fn( $t ) => gmdate( 'Ymd\THis\Z', strtotime( $t . ' UTC' ) );
	return add_query_arg(
		[
			'action'   => 'TEMPLATE',
			'text'     => rawurlencode( $b->service . ' at Skyn&Co.' ),
			'dates'    => $f( $b->start_datetime_utc ) . '/' . $f( $b->end_datetime_utc ),
			'location' => rawurlencode( '150 Arsenal St, Suite 210, Watertown, MA 02472' ),
			'details'  => rawurlencode( 'Booking code ' . $b->booking_code . '. Questions? Call or text (857) 228-4708.' ),
		],
		'https://calendar.google.com/calendar/render'
	);
}

/* Our confirmation card, shown instead of LatePoint's default summary. */
add_action(
	'latepoint_step_confirmation_before',
	function ( $order ) {
		if ( empty( $order->id ) ) {
			return;
		}
		$bookings = skynco_order_bookings( $order->id );
		$first    = ! empty( $order->customer->first_name ) ? $order->customer->first_name : '';
		$map      = 'https://maps.google.com/?q=' . rawurlencode( 'Skyn&Co. 150 Arsenal St Suite 210 Watertown MA 02472' );
		$addons   = class_exists( 'OsMetaHelper' ) ? OsMetaHelper::get_order_meta_by_key( 'skynco_addons', $order->id ) : '';
		$total    = class_exists( 'OsMoneyHelper' ) ? OsMoneyHelper::format_price( $order->total, true, false ) : '$' . number_format( (float) $order->total, 2 );
		$paid     = 'fully_paid' === ( $order->payment_status ?? '' );

		$h  = '<div class="skbc">';
		$h .= '<div class="skbc-head"><span class="skbc-check" aria-hidden="true"></span><p class="skbc-eyebrow">Booking confirmed</p><h2>You’re booked' . ( $first ? ', ' . esc_html( $first ) : '' ) . '!</h2><p class="skbc-sub">A confirmation is on its way to your email. Order <b>#' . esc_html( $order->confirmation_code ) . '</b></p></div>';
		foreach ( $bookings as $b ) {
			[ $day, $time ] = skynco_booking_when( $b );
			$h .= '<div class="skbc-card"><p class="skbc-k">Your visit</p><p class="skbc-service">' . esc_html( $b->service ) . '</p>';
			$h .= '<div class="skbc-rows"><div><span>Date</span><b>' . esc_html( $day ) . '</b></div><div><span>Time</span><b>' . esc_html( $time ) . '</b></div><div><span>With</span><b>Hana Rahim</b></div><div><span>Code</span><b>' . esc_html( $b->booking_code ) . '</b></div></div>';
			$h .= '<div class="skbc-actions"><a class="skbc-btn" href="' . esc_url( skynco_gcal_url( $b ) ) . '" target="_blank" rel="noopener">Add to Google Calendar</a><a class="skbc-btn" href="' . esc_url( skynco_ics_url( $b ) ) . '">Apple / Outlook</a></div></div>';
		}
		$h .= '<div class="skbc-card skbc-card--soft"><p class="skbc-k">Studio</p><p class="skbc-t">Skyn&amp;Co. Skincare &amp; Wellness</p><p class="skbc-s">150 Arsenal St, Suite 210, Watertown, MA 02472</p><a class="skbc-link" href="' . esc_url( $map ) . '" target="_blank" rel="noopener">Get directions</a></div>';
		$h .= '<div class="skbc-card skbc-card--soft"><p class="skbc-k">Payment</p><div class="skbc-rows"><div><span>Total</span><b>' . wp_kses_post( $total ) . '</b></div><div><span>Status</span><b>' . ( $paid ? 'Paid' : 'Pay at your visit' ) . '</b></div></div>' . ( $addons ? '<p class="skbc-s" style="margin-top:10px">Add-ons: ' . esc_html( $addons ) . '</p>' : '' ) . '</div>';
		$h .= '<div class="skbc-card skbc-card--soft"><p class="skbc-k">Before you come</p><ul class="skbc-list"><li>Arrive with clean skin, or come as you are and we will cleanse.</li><li>Skip retinoids and exfoliants for 2 days before.</li><li>Need to change? Text (857) 228-4708 at least 24 hours before.</li></ul></div>';
		$email = ! empty( $order->customer->email ) ? $order->customer->email : '';
		$me    = wp_get_current_user();
		if ( $me->ID && $email && strtolower( $me->user_email ) === strtolower( $email ) ) {
			$h .= '<a class="skbc-cta" href="' . esc_url( skynco_account_url( 'visits' ) ) . '">Open my client dashboard</a>';
		} else {
			$h .= '<a class="skbc-cta" href="' . esc_url( add_query_arg( 'e', rawurlencode( $email ), skynco_account_url() ) ) . '">Open my client dashboard</a><p class="skbc-s" style="text-align:center;margin-top:8px">Your personal dashboard link is also in your confirmation email.</p>';
		}
		$h .= '</div>';
		echo $h; // phpcs:ignore
	}
);

/* Calendar file download. */
add_action( 'admin_post_nopriv_skynco_ics', 'skynco_ics_download' );
add_action( 'admin_post_skynco_ics', 'skynco_ics_download' );
function skynco_ics_download() {
	global $wpdb;
	$code = sanitize_text_field( wp_unslash( $_GET['b'] ?? '' ) );
	$key  = sanitize_text_field( wp_unslash( $_GET['k'] ?? '' ) );
	if ( ! $code || ! hash_equals( substr( wp_hash( 'ics' . $code ), 0, 12 ), $key ) ) {
		wp_die( 'Link expired' );
	}
	$p = $wpdb->prefix . 'latepoint_';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$b = $wpdb->get_row( $wpdb->prepare( "SELECT b.booking_code, b.start_datetime_utc, b.end_datetime_utc, s.name AS service FROM {$p}bookings b LEFT JOIN {$p}services s ON s.id = b.service_id WHERE b.booking_code = %s", $code ) );
	if ( ! $b ) {
		wp_die( 'Booking not found' );
	}
	$f   = fn( $t ) => gmdate( 'Ymd\THis\Z', strtotime( $t . ' UTC' ) );
	$ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Skyn&Co.//Booking//EN\r\nBEGIN:VEVENT\r\nUID:" . $b->booking_code . '@skynco' . "\r\nDTSTAMP:" . gmdate( 'Ymd\THis\Z' ) . "\r\nDTSTART:" . $f( $b->start_datetime_utc ) . "\r\nDTEND:" . $f( $b->end_datetime_utc ) . "\r\nSUMMARY:" . $b->service . " at Skyn&Co.\r\nLOCATION:150 Arsenal St\\, Suite 210\\, Watertown\\, MA 02472\r\nDESCRIPTION:Booking code " . $b->booking_code . ". Questions? (857) 228-4708\r\nBEGIN:VALARM\r\nTRIGGER:-PT2H\r\nACTION:DISPLAY\r\nDESCRIPTION:Skyn&Co. appointment\r\nEND:VALARM\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="skynco-' . $b->booking_code . '.ics"' );
	echo $ics; // phpcs:ignore
	exit;
}

add_action(
	'wp_head',
	function () {
		?>
<style id="skynco-confirm">
.step-confirmation-w .confirmation-info-w,.step-confirmation-w .confirmation-cabinet-info,.step-confirmation-w .step-confirmation-set-password{display:none!important}
.latepoint-w .latepoint-step-content,.latepoint-w .latepoint-form-w,.latepoint-w .latepoint-body,.latepoint-w .latepoint-booking-form-element,.lux-book-shell .latepoint-w{min-width:0!important;max-width:100%!important}
.latepoint-w .latepoint-body{overflow-x:hidden}
.sk-lpafter{min-width:0;max-width:100%;overflow:hidden}
.skbc{font-family:Manrope,sans-serif;color:#2A1A24;display:flex;flex-direction:column;gap:12px;max-width:560px;margin:0 auto}
.skbc *{box-sizing:border-box}
.skbc p{margin:0}
.skbc-head{text-align:center;padding:8px 6px 6px}
.skbc-check{display:block;width:64px;height:64px;margin:0 auto 14px;border-radius:50%;background:#D1127E;box-shadow:0 0 0 10px #FBE3EE;position:relative}
.skbc-check::after{content:"";position:absolute;left:23px;top:18px;width:14px;height:24px;border:solid #fff;border-width:0 4px 4px 0;transform:rotate(45deg)}
.skbc-eyebrow,.skbc-k{font:700 11px/1.2 Manrope,sans-serif!important;letter-spacing:.14em;text-transform:uppercase;color:#D1127E}
.skbc-head h2{margin:6px 0 6px!important;font:400 30px/1.15 Fraunces,serif!important;color:#3B1530!important}
.skbc-sub{font-size:14px;color:#6E5A66}.skbc-sub b{color:#3B1530}
.skbc-card{background:#fff;border:1px solid #EADDE0;border-radius:20px;padding:18px}
.skbc-card--soft{background:#FBF6F4}
.skbc-service{margin:6px 0 12px!important;font:500 22px/1.25 Fraunces,serif;color:#3B1530}
.skbc-rows{display:grid;grid-template-columns:1fr 1fr;gap:10px 14px}
.skbc-rows div{display:flex;flex-direction:column;gap:2px;min-width:0}
.skbc-rows span{font-size:12px;color:#9A8791;font-weight:600}
.skbc-rows b{font-size:14.5px;color:#2A1A24;font-weight:700;overflow-wrap:anywhere}
.skbc-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
.skbc-btn{flex:1;min-width:140px;text-align:center;padding:11px 14px;border-radius:999px;border:1px solid #3B1530;color:#3B1530!important;font:700 13.5px Manrope,sans-serif;text-decoration:none!important}
.skbc-btn:hover{background:#3B1530;color:#fff!important}
.skbc-t{margin:6px 0 2px!important;font:600 15px Manrope,sans-serif}
.skbc-s{font-size:14px;line-height:1.5;color:#6E5A66}
.skbc-link{display:inline-block;margin-top:8px;color:#D1127E!important;font-weight:700;font-size:14px;text-decoration:none!important}
.skbc-list{margin:8px 0 0!important;padding-left:18px!important;font-size:14px;line-height:1.55;color:#6E5A66}
.skbc-list li{margin:0 0 4px}
.skbc-cta{display:block;text-align:center;padding:15px;border-radius:999px;background:#D1127E;color:#fff!important;font:700 15px Manrope,sans-serif;text-decoration:none!important}
.skbc-cta:hover{background:#B00F6A}
.step-confirmation-w .sk-lpacct{display:none}
</style>
		<?php
	}
);
