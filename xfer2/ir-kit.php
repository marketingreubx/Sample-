<?php
/**
 * Plugin Name: IR Booked & Paid SMM Kit
 * Description: Members area for The Booked & Paid SMM Kit (pricing calculator, invoices, contracts, client forms and templates at /members/smm-tools/).
 * Access: SMM Workshop students (free, automatic), shop buyers, and people added under Users > SMM Kit Members.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'IR_KIT_NAME', 'The Booked & Paid SMM Kit' );
function ir_kit_product() { return (int) get_option( 'ir_kit_product' ); }
function ir_kit_sales_url() { return ir_kit_product() ? get_permalink( ir_kit_product() ) : home_url( '/academy/' ); }

/** SMM Workshop students get the kit free. */
function ir_kit_workshop( $uid ) {
	if ( ! $uid || ! function_exists( 'tutor_utils' ) ) { return false; }
	foreach ( (array) get_option( 'ir_pb_bonus_courses', [] ) as $cid ) { if ( tutor_utils()->is_enrolled( (int) $cid, $uid ) ) { return true; } }
	return false;
}
function ir_kit_has_access( $uid = 0 ) {
	$uid = $uid ?: get_current_user_id();
	if ( ! $uid ) { return false; }
	if ( user_can( $uid, 'manage_options' ) || get_user_meta( $uid, 'ir_kit_access', true ) || ir_kit_workshop( $uid ) ) { return true; }
	if ( ir_kit_product() && function_exists( 'wc_customer_bought_product' ) ) {
		$u = get_userdata( $uid );
		if ( $u && wc_customer_bought_product( $u->user_email, $uid, ir_kit_product() ) ) { ir_kit_grant( $uid, 'woo', false ); return true; }
	}
	return false;
}
function ir_kit_grant( $uid, $source, $notify = true ) {
	$had = get_user_meta( $uid, 'ir_kit_access', true );
	update_user_meta( $uid, 'ir_kit_access', $had ?: time() );
	if ( ! $had ) { update_user_meta( $uid, 'ir_kit_source', $source ); }
	if ( $notify && ! $had ) { ir_kit_mail_access( $uid ); }
}
function ir_kit_revoke( $uid ) { delete_user_meta( $uid, 'ir_kit_access' ); delete_user_meta( $uid, 'ir_kit_source' ); }

function ir_kit_mail_access( $uid ) {
	$u = get_userdata( $uid );
	if ( ! $u || ! function_exists( 'ir_pb_mail' ) ) { return false; }
	$needs = (bool) get_user_meta( $uid, 'ir_pb_needs_pass', true );
	$url = function_exists( 'ir_smm_url' ) ? ir_smm_url() : home_url( '/members/smm-tools/' );
	$p = 'margin:0 0 14px;font-size:16px;line-height:1.6;color:#33415F';
	$inner = '<p style="' . $p . '">' . esc_html( IR_KIT_NAME ) . ' is ready in your dashboard. Everything you need to price, bill, sign and onboard clients, in your own name and brand.</p>'
		. '<p style="margin:18px 0 2px;font-size:13px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#8A97B4">What is inside</p>'
		. ir_pb_mail_steps( [ [ 'SMM Pricing Calculator.', '3 packages priced on real rates in your country, plus a branded price card PDF.' ], [ 'Invoice and Contract Generators.', 'Clean, branded PDFs with your name and your client\'s.' ], [ 'Client Forms and templates.', 'Enquiry and onboarding forms that email you every answer, plus a proposal, agreement, strategy and report template.' ] ] )
		. ir_pb_mail_card( 'Your login email', $u->user_email, 'You log in with this email.' )
		. ir_pb_btn( $needs && function_exists( 'ir_pb_set_link' ) ? ir_pb_set_link( $uid ) : $url, $needs ? 'Set my password and open the kit' : 'Open the kit' );
	return ir_pb_mail( $u->user_email, 'Your Booked & Paid SMM Kit is ready', $inner, [ 'kicker' => 'SMM Kit · Access ready', 'title' => 'Welcome,', 'preheader' => 'Price, invoice, contract and onboard clients in minutes.' ] );
}

/* Shop purchase. */
function ir_kit_on_paid( $order_id ) {
	$o = wc_get_order( $order_id );
	if ( ! $o || ! ir_kit_product() ) { return; }
	$has = false;
	foreach ( $o->get_items() as $it ) { if ( (int) $it->get_product_id() === ir_kit_product() ) { $has = true; } }
	if ( ! $has ) { return; }
	$uid = $o->get_user_id();
	if ( ! $uid && function_exists( 'ir_pb_user_for_email' ) ) { list( $uid ) = ir_pb_user_for_email( $o->get_billing_email(), $o->get_billing_first_name(), $o->get_billing_last_name() ); }
	if ( $uid ) { ir_kit_grant( $uid, 'woo:' . $order_id ); }
}
add_action( 'woocommerce_order_status_processing', 'ir_kit_on_paid' );
add_action( 'woocommerce_order_status_completed', 'ir_kit_on_paid' );

/* ---------- Users > SMM Kit Members ---------- */

add_action( 'admin_menu', function () {
	add_users_page( 'SMM Kit Members', 'SMM Kit Members', 'manage_options', 'ir-kit', 'ir_kit_admin_page' );
} );
add_action( 'admin_post_ir_kit_add', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ir_kit' ) ) { wp_die( 'Not allowed' ); }
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$m = 'bad';
	if ( is_email( $email ) && function_exists( 'ir_pb_user_for_email' ) ) {
		$parts = explode( ' ', $name, 2 );
		list( $uid ) = ir_pb_user_for_email( $email, $parts[0] ?? '', $parts[1] ?? '' );
		if ( $uid ) { $had = get_user_meta( $uid, 'ir_kit_access', true ); ir_kit_grant( $uid, 'manual', ! empty( $_POST['notify'] ) ); $m = $had ? 'had' : 'added'; }
	}
	wp_safe_redirect( admin_url( 'users.php?page=ir-kit&m=' . $m ) ); exit;
} );
add_action( 'admin_post_ir_kit_remove', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ir_kit_rm' ) ) { wp_die( 'Not allowed' ); }
	ir_kit_revoke( (int) ( $_POST['uid'] ?? 0 ) );
	wp_safe_redirect( admin_url( 'users.php?page=ir-kit&m=removed' ) ); exit;
} );
function ir_kit_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$m = sanitize_key( $_GET['m'] ?? '' );
	$msg = [ 'added' => 'Added. They now have access' . '.', 'had' => 'They already had access.', 'removed' => 'Access removed.', 'bad' => 'Enter a valid email.' ][ $m ] ?? '';
	$rows = [];
	foreach ( get_users( [ 'meta_key' => 'ir_kit_access', 'fields' => [ 'ID' ] ] ) as $u ) { $rows[ $u->ID ] = 'meta'; }
	foreach ( get_users( [ 'fields' => [ 'ID' ] ] ) as $u ) { if ( ! isset( $rows[ $u->ID ] ) && ir_kit_workshop( $u->ID ) ) { $rows[ $u->ID ] = 'workshop'; } }
	echo '<div class="wrap"><h1>' . esc_html( IR_KIT_NAME ) . ' members</h1>';
	if ( $msg ) { echo '<div class="notice notice-' . ( $m === 'bad' ? 'error' : 'success' ) . '"><p>' . esc_html( $msg ) . '</p></div>'; }
	echo '<p>SMM Workshop students get the kit free and show here automatically. Shop buyers are added when they pay. You can add anyone below.' . ( ir_kit_product() ? ' Product: <a href="' . esc_url( get_edit_post_link( ir_kit_product() ) ) . '">edit</a> · <a href="' . esc_url( ir_kit_sales_url() ) . '" target="_blank">view</a>.' : '' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="background:#fff;border:1px solid #dcdcde;padding:14px 16px;max-width:640px;margin:12px 0 20px"><input type="hidden" name="action" value="ir_kit_add">';
	wp_nonce_field( 'ir_kit' );
	echo '<h2 style="margin-top:0">Add a member</h2><p><input type="email" name="email" placeholder="Email" class="regular-text" required> <input type="text" name="name" placeholder="Full name (optional)" class="regular-text"></p><p><label><input type="checkbox" name="notify" value="1" checked> Send them the welcome email</label></p><p><button class="button button-primary">Give access</button></p></form>';
	echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Name</th><th>Email</th><th>How they got it</th><th>Since</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $id => $kind ) {
		$u = get_userdata( $id ); if ( ! $u ) { continue; }
		$src = (string) get_user_meta( $id, 'ir_kit_source', true );
		$how = $kind === 'workshop' ? 'SMM Workshop (free)' : ( strpos( $src, 'woo' ) === 0 ? 'Bought it' : ( $src === 'grandfathered' ? 'AI Profit member (before the kit launched)' : 'Added by you' ) );
		if ( $kind === 'meta' && ir_kit_workshop( $id ) ) { $how .= ' · also SMM Workshop'; }
		$since = $kind === 'meta' ? wp_date( 'j M Y', (int) get_user_meta( $id, 'ir_kit_access', true ) ) : '';
		echo '<tr><td>' . esc_html( trim( $u->first_name . ' ' . $u->last_name ) ?: $u->display_name ) . '</td><td>' . esc_html( $u->user_email ) . '</td><td>' . esc_html( $how ) . '</td><td>' . esc_html( $since ) . '</td><td>';
		if ( $kind === 'meta' ) { echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'Remove access?\')"><input type="hidden" name="action" value="ir_kit_remove"><input type="hidden" name="uid" value="' . (int) $id . '">' . wp_nonce_field( 'ir_kit_rm', '_wpnonce', true, false ) . '<button class="button-link" style="color:#b32d2e">Remove</button></form>'; }
		echo '</td></tr>';
	}
	if ( ! $rows ) { echo '<tr><td colspan="5">No members yet.</td></tr>'; }
	echo '</tbody></table></div>';
}
