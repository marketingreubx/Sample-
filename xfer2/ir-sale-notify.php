<?php
/**
 * Plugin Name: IR Sale Notify
 * Description: Emails the site owner when someone gets the Cold DM System or the AI Profit System outside the shop
 * (landing page Paystack payments and payment claims). Shop orders already send WooCommerce's own "New order" email.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'added_user_meta', function ( $mid, $uid, $key, $source ) {
	if ( ! in_array( $key, [ 'ir_pb_source', 'ir_pp_source' ], true ) ) { return; }
	$source = (string) $source;
	if ( strpos( $source, 'woo' ) === 0 || $source === 'manual' ) { return; }
	$u = get_userdata( $uid );
	if ( ! $u || ! function_exists( 'ir_pb_mail' ) ) { return; }
	$prog = $key === 'ir_pb_source' ? 'The Cold DM System' : 'The AI Profit System';
	$name = trim( $u->first_name . ' ' . $u->last_name ) ?: $u->display_name;
	$ref  = preg_match( '/paystack:(\S+)/', $source, $m ) ? $m[1] : '';
	$inner = '<p style="margin:0 0 14px;font-size:16px;line-height:1.6;color:#33415F"><b>' . esc_html( $name ) . '</b> now has access to ' . esc_html( $prog ) . '. Their welcome email has been sent.</p>'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 10px">'
		. '<tr><td style="padding:10px 0;border-top:1px solid #E6EBF5;color:#6B7A99;font-size:13px;width:38%">Email</td><td style="padding:10px 0;border-top:1px solid #E6EBF5;font-size:15px;color:#0A1633">' . esc_html( $u->user_email ) . '</td></tr>'
		. '<tr><td style="padding:10px 0;border-top:1px solid #E6EBF5;color:#6B7A99;font-size:13px">Paid through</td><td style="padding:10px 0;border-top:1px solid #E6EBF5;font-size:15px;color:#0A1633">' . ( strpos( $source, 'paystack' ) !== false ? 'Paystack (landing page)' : esc_html( $source ) ) . '</td></tr>'
		. ( $ref ? '<tr><td style="padding:10px 0;border-top:1px solid #E6EBF5;color:#6B7A99;font-size:13px">Reference</td><td style="padding:10px 0;border-top:1px solid #E6EBF5;font-size:15px;color:#0A1633">' . esc_html( $ref ) . '</td></tr>' : '' )
		. '</table>';
	ir_pb_mail( get_option( 'admin_email' ), 'New student: ' . $name . ' joined ' . $prog, $inner, [ 'kicker' => 'New sale', 'title' => 'New student,', 'name' => $name ] );
}, 10, 4 );
