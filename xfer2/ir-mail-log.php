<?php
/**
 * Plugin Name: IR Mail Log
 * Description: Keeps a record of every email the site sends (Tools > Email log) and retries any that fail, up to 3 more times.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_filter( 'wp_mail', function ( $a ) { $GLOBALS['ir_ml_cur'] = $a; return $a; }, 999 );

function ir_ml_add( $a, $ok, $err = '' ) {
	$log = get_option( 'ir_mail_log', [] );
	$log = is_array( $log ) ? $log : [];
	$to  = is_array( $a['to'] ?? '' ) ? implode( ', ', $a['to'] ) : (string) ( $a['to'] ?? '' );
	array_unshift( $log, [ 't' => time(), 'to' => $to, 's' => (string) ( $a['subject'] ?? '' ), 'ok' => $ok ? 1 : 0, 'e' => $err, 'r' => ! empty( $GLOBALS['ir_ml_retrying'] ) ? 1 : 0 ] );
	update_option( 'ir_mail_log', array_slice( $log, 0, 200 ), false );
}
add_action( 'wp_mail_succeeded', function ( $d ) { ir_ml_add( $d, true ); } );
add_action( 'wp_mail_failed', function ( $e ) {
	$d = $e->get_error_data();
	if ( ! is_array( $d ) || empty( $d['to'] ) ) { $d = $GLOBALS['ir_ml_cur'] ?? []; }
	ir_ml_add( $d, false, $e->get_error_message() );
	if ( ! empty( $GLOBALS['ir_ml_retrying'] ) || empty( $d['to'] ) ) { return; }
	$q = get_option( 'ir_mail_queue', [] );
	$q = is_array( $q ) ? $q : [];
	if ( count( $q ) < 50 ) {
		$q[] = [ 'to' => $d['to'], 'subject' => $d['subject'] ?? '', 'message' => $d['message'] ?? '', 'headers' => $d['headers'] ?? [], 'n' => 0 ];
		update_option( 'ir_mail_queue', $q, false );
	}
	if ( ! wp_next_scheduled( 'ir_mail_retry' ) ) { wp_schedule_single_event( time() + 300, 'ir_mail_retry' ); }
} );

/* Failed emails are tried again after 5 minutes, then every 15 minutes, 3 times in all. */
add_action( 'ir_mail_retry', function () {
	$q = get_option( 'ir_mail_queue', [] );
	update_option( 'ir_mail_queue', [], false );
	$left = [];
	$GLOBALS['ir_ml_retrying'] = true;
	foreach ( (array) $q as $m ) {
		if ( ! wp_mail( $m['to'], $m['subject'], $m['message'], $m['headers'] ) && ++$m['n'] < 3 ) { $left[] = $m; }
	}
	unset( $GLOBALS['ir_ml_retrying'] );
	if ( $left ) {
		update_option( 'ir_mail_queue', $left, false );
		wp_schedule_single_event( time() + 900, 'ir_mail_retry' );
	}
} );

add_action( 'admin_menu', function () {
	add_management_page( 'Email log', 'Email log', 'manage_options', 'ir-mail-log', 'ir_ml_page' );
} );
function ir_ml_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$log = (array) get_option( 'ir_mail_log', [] );
	$q   = (array) get_option( 'ir_mail_queue', [] );
	echo '<div class="wrap"><h1>Email log</h1><p>The last 200 emails the site sent. Failed emails are retried automatically.' . ( $q ? ' <b>' . count( $q ) . ' waiting to retry.</b>' : '' ) . '</p>';
	echo '<table class="widefat striped"><thead><tr><th>When</th><th>To</th><th>Subject</th><th>Status</th></tr></thead><tbody>';
	foreach ( $log as $r ) {
		echo '<tr><td>' . esc_html( wp_date( 'j M, g:i a', (int) $r['t'] ) ) . '</td><td>' . esc_html( $r['to'] ) . '</td><td>' . esc_html( $r['s'] ) . '</td><td>' . ( $r['ok'] ? '<span style="color:#16a34a;font-weight:600">Sent' . ( ! empty( $r['r'] ) ? ' (retry)' : '' ) . '</span>' : '<span style="color:#dc2626;font-weight:600">Failed</span> ' . esc_html( $r['e'] ) ) . '</td></tr>';
	}
	if ( ! $log ) { echo '<tr><td colspan="4">Nothing sent yet.</td></tr>'; }
	echo '</tbody></table></div>';
}
