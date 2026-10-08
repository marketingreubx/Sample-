<?php
/**
 * WhatsApp notifications through the Meta WhatsApp Cloud API.
 *
 * Clients get a WhatsApp message as soon as a LatePoint booking or a WooCommerce
 * order is confirmed. Messages are sent after the page response is flushed, so
 * booking and checkout stay fast. Business-initiated messages must use templates
 * approved in Meta WhatsApp Manager; the settings page lists the exact wording.
 *
 * @package SkyncoStudio
 */

defined( 'ABSPATH' ) || exit;

function skynco_wa_settings() {
	return wp_parse_args(
		(array) get_option( 'skynco_wa', [] ),
		[
			'enabled'      => '0',
			'token'        => '',
			'phone_id'     => '',
			'version'      => 'v21.0',
			'lang'         => 'en_US',
			'tpl_booking'  => 'booking_confirmed',
			'tpl_order'    => 'order_confirmed',
			'country'      => '1',
			'owner_phone'  => '',
			'owner_key'    => '',
		]
	);
}

/** Normalise a phone number to E.164 digits (no plus). US numbers by default. */
function skynco_wa_e164( $phone ) {
	$phone = trim( (string) $phone );
	$plus  = 0 === strpos( $phone, '+' ) || 0 === strpos( $phone, '00' );
	$d     = preg_replace( '/\D+/', '', $phone );
	if ( 0 === strpos( $d, '00' ) ) {
		$d = substr( $d, 2 );
	}
	if ( ! $plus ) {
		$cc = skynco_wa_settings()['country'];
		if ( 10 === strlen( $d ) && '1' === $cc ) {
			$d = '1' . $d;
		} elseif ( 0 === strpos( $d, '0' ) ) {
			$d = $cc . ltrim( $d, '0' );
		}
	}
	return ( strlen( $d ) >= 10 && strlen( $d ) <= 15 ) ? $d : '';
}

/** Template parameters cannot contain new lines, tabs or long runs of spaces. */
function skynco_wa_param( $text ) {
	$text = preg_replace( '/\s+/', ' ', wp_strip_all_tags( html_entity_decode( (string) $text, ENT_QUOTES ) ) );
	return mb_substr( trim( $text ), 0, 900 ) ?: '-';
}

function skynco_wa_log( $kind, $to, $ok, $note ) {
	$log = (array) get_option( 'skynco_wa_log', [] );
	array_unshift( $log, [ time(), $kind, $to, $ok ? 1 : 0, mb_substr( (string) $note, 0, 300 ) ] );
	update_option( 'skynco_wa_log', array_slice( $log, 0, 40 ), false );
}

/**
 * Send a template message. $params fill {{1}}, {{2}}… in the template body.
 *
 * @return true|string True on success, or the error text.
 */
function skynco_wa_send( $to, $template, array $params = [], $kind = 'manual', $lang = '' ) {
	$s  = skynco_wa_settings();
	$to = skynco_wa_e164( $to );
	if ( ! $s['token'] || ! $s['phone_id'] ) {
		return 'WhatsApp is not connected yet';
	}
	if ( ! $to ) {
		skynco_wa_log( $kind, '', false, 'No valid phone number' );
		return 'No valid phone number';
	}
	$tpl = [ 'name' => $template, 'language' => [ 'code' => $lang ?: $s['lang'] ] ];
	if ( $params ) {
		$tpl['components'] = [
			[
				'type'       => 'body',
				'parameters' => array_map( fn( $p ) => [ 'type' => 'text', 'text' => skynco_wa_param( $p ) ], array_values( $params ) ),
			],
		];
	}
	$res  = wp_remote_post(
		'https://graph.facebook.com/' . rawurlencode( $s['version'] ) . '/' . rawurlencode( $s['phone_id'] ) . '/messages',
		[
			'timeout' => 12,
			'headers' => [ 'Authorization' => 'Bearer ' . $s['token'], 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( [ 'messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template', 'template' => $tpl ] ),
		]
	);
	$code = wp_remote_retrieve_response_code( $res );
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	$ok   = ! is_wp_error( $res ) && $code >= 200 && $code < 300 && ! empty( $body['messages'] );
	$note = $ok ? 'Sent ' . $template : ( is_wp_error( $res ) ? $res->get_error_message() : ( $body['error']['message'] ?? 'HTTP ' . $code ) );
	skynco_wa_log( $kind, $to, $ok, $note );
	return $ok ? true : $note;
}

/* ---------------------------------------------------------------------------
 * Queue: collect during the request, send after the response is flushed.
 * ------------------------------------------------------------------------ */
function skynco_wa_queue( $type, $id ) {
	if ( ! skynco_wa_client_ready() && ! skynco_wa_owner_ready() ) {
		return;
	}
	$GLOBALS['skynco_wa_queue'][ $type . ':' . $id ] = [ $type, $id ];
	static $hooked = false;
	if ( ! $hooked ) {
		$hooked = true;
		add_action( 'shutdown', 'skynco_wa_flush', 1000 );
	}
}

function skynco_wa_flush() {
	if ( empty( $GLOBALS['skynco_wa_queue'] ) ) {
		return;
	}
	if ( function_exists( 'litespeed_finish_request' ) ) {
		litespeed_finish_request();
	} elseif ( function_exists( 'fastcgi_finish_request' ) ) {
		fastcgi_finish_request();
	}
	foreach ( $GLOBALS['skynco_wa_queue'] as $job ) {
		'booking' === $job[0] ? skynco_wa_notify_booking( $job[1] ) : skynco_wa_notify_order( $job[1] );
	}
	$GLOBALS['skynco_wa_queue'] = [];
}

function skynco_wa_client_ready() {
	$s = skynco_wa_settings();
	return '1' === $s['enabled'] && $s['token'] && $s['phone_id'];
}

function skynco_wa_owner_ready() {
	$s = skynco_wa_settings();
	return $s['owner_phone'] && $s['owner_key'];
}

/** Instant alert to the studio owner's own WhatsApp (free CallMeBot service). */
function skynco_wa_owner_alert( $text, $kind ) {
	$s = skynco_wa_settings();
	if ( ! skynco_wa_owner_ready() ) {
		return;
	}
	$to  = skynco_wa_e164( $s['owner_phone'] );
	$res = wp_remote_get( 'https://api.callmebot.com/whatsapp.php?' . http_build_query( [ 'phone' => '+' . $to, 'text' => $text, 'apikey' => $s['owner_key'] ] ), [ 'timeout' => 12 ] );
	$ok  = ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) && false === stripos( wp_remote_retrieve_body( $res ), 'error' );
	skynco_wa_log( 'owner: ' . $kind, $to, $ok, $ok ? 'Alert sent to studio' : ( is_wp_error( $res ) ? $res->get_error_message() : wp_strip_all_tags( mb_substr( wp_remote_retrieve_body( $res ), 0, 200 ) ) ) );
}

/** Respect the opt-out toggle in the client dashboard. */
function skynco_wa_opted_out( $email ) {
	$u = $email ? get_user_by( 'email', $email ) : null;
	return $u && '0' === get_user_meta( $u->ID, 'skynco_wa_optin', true );
}

/* ---------------------------------------------------------------------------
 * Bookings (LatePoint)
 * ------------------------------------------------------------------------ */
add_action(
	'latepoint_booking_created',
	function ( $booking ) {
		if ( ! empty( $booking->id ) ) {
			skynco_wa_queue( 'booking', (int) $booking->id );
		}
	}
);

function skynco_wa_notify_booking( $id ) {
	global $wpdb;
	$p = $wpdb->prefix . 'latepoint_';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$b = $wpdb->get_row( $wpdb->prepare( "SELECT bk.id, bk.booking_code, bk.start_date, bk.start_time, bk.status, c.first_name, c.email, c.phone, s.name AS service FROM {$p}bookings bk LEFT JOIN {$p}customers c ON c.id = bk.customer_id LEFT JOIN {$p}services s ON s.id = bk.service_id WHERE bk.id = %d", $id ) );
	if ( ! $b || 'cancelled' === $b->status || get_option( 'skynco_wa_b_' . $id ) ) {
		return;
	}
	$dt   = date_create( $b->start_date . ' 00:00', wp_timezone() );
	$when = $dt ? wp_date( 'l, F j \a\t g:i a', $dt->getTimestamp() + (int) $b->start_time * 60 ) : $b->start_date;
	if ( ! get_option( 'skynco_wa_ob_' . $id ) ) {
		update_option( 'skynco_wa_ob_' . $id, time(), false );
		skynco_wa_owner_alert( "New booking: {$b->service}\n{$when}\nClient: {$b->first_name} · {$b->phone}\nCode {$b->booking_code}", 'booking #' . $id );
	}
	if ( ! skynco_wa_client_ready() || skynco_wa_opted_out( $b->email ) ) {
		return;
	}
	$s    = skynco_wa_settings();
	$r    = skynco_wa_send( $b->phone, $s['tpl_booking'], [ $b->first_name ?: 'there', $b->service, $when, $b->booking_code, skynco_wa_account_link() ], 'booking #' . $id );
	if ( true === $r ) {
		update_option( 'skynco_wa_b_' . $id, time(), false );
	}
}

/* ---------------------------------------------------------------------------
 * Orders (WooCommerce)
 * ------------------------------------------------------------------------ */
add_action(
	'woocommerce_order_status_changed',
	function ( $order_id, $from, $to ) {
		if ( in_array( $to, [ 'processing', 'completed', 'on-hold' ], true ) ) {
			skynco_wa_queue( 'order', (int) $order_id );
		}
	},
	20,
	3
);

/* Opt-in checkbox at checkout, ticked by default. */
add_filter(
	'woocommerce_checkout_fields',
	function ( $fields ) {
		if ( '1' === skynco_wa_settings()['enabled'] ) {
			$fields['billing']['skynco_wa'] = [
				'type'     => 'checkbox',
				'label'    => 'Send my order updates on WhatsApp',
				'default'  => 1,
				'required' => false,
				'class'    => [ 'form-row-wide' ],
				'priority' => 101,
			];
		}
		return $fields;
	}
);

add_action(
	'woocommerce_checkout_create_order',
	function ( $order, $data ) {
		if ( '1' === skynco_wa_settings()['enabled'] ) {
			$order->update_meta_data( '_skynco_wa', empty( $data['skynco_wa'] ) ? '0' : '1' );
		}
	},
	10,
	2
);

function skynco_wa_notify_order( $order_id ) {
	$o = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
	if ( ! $o || 'pos' === $o->get_created_via() ) {
		return;
	}
	$items = [];
	foreach ( $o->get_items() as $it ) {
		$items[] = $it->get_name() . ( $it->get_quantity() > 1 ? ' x' . $it->get_quantity() : '' );
	}
	if ( ! $o->get_meta( '_skynco_wa_owner' ) ) {
		$o->update_meta_data( '_skynco_wa_owner', time() );
		$o->save();
		skynco_wa_owner_alert( 'New order #' . $o->get_order_number() . ' · ' . html_entity_decode( wp_strip_all_tags( wc_price( $o->get_total() ) ) ) . "\n" . implode( ', ', $items ) . "\nClient: " . $o->get_billing_first_name() . ' · ' . $o->get_billing_phone() . "\n" . $o->get_shipping_method(), 'order #' . $order_id );
	}
	if ( ! skynco_wa_client_ready() || $o->get_meta( '_skynco_wa_sent' ) || '0' === $o->get_meta( '_skynco_wa' ) || skynco_wa_opted_out( $o->get_billing_email() ) ) {
		return;
	}
	$s = skynco_wa_settings();
	$r = skynco_wa_send(
		$o->get_billing_phone(),
		$s['tpl_order'],
		[ $o->get_billing_first_name() ?: 'there', $o->get_order_number(), html_entity_decode( wp_strip_all_tags( wc_price( $o->get_total(), [ 'currency' => $o->get_currency() ] ) ) ), implode( ', ', $items ), skynco_wa_account_link() ],
		'order #' . $order_id
	);
	if ( true === $r ) {
		$o->update_meta_data( '_skynco_wa_sent', time() );
		$o->add_order_note( 'WhatsApp confirmation sent to the client.' );
		$o->save();
	}
}

function skynco_wa_account_link() {
	return function_exists( 'skynco_account_url' ) ? skynco_account_url() : home_url( '/' );
}

/* ---------------------------------------------------------------------------
 * Admin: Studio → WhatsApp
 * ------------------------------------------------------------------------ */
add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'skynco-studio', 'WhatsApp', 'WhatsApp', SKYNCO_STUDIO_CAP, 'skynco-whatsapp', 'skynco_page_whatsapp' );
	},
	30
);

add_action(
	'admin_post_skynco_wa_save',
	function () {
		if ( ! current_user_can( SKYNCO_STUDIO_CAP ) || ! check_admin_referer( 'skynco_wa' ) ) {
			wp_die( 'Not allowed' );
		}
		$old = skynco_wa_settings();
		$in  = wp_unslash( $_POST['wa'] ?? [] );
		$new = [];
		foreach ( array_keys( $old ) as $k ) {
			$new[ $k ] = sanitize_text_field( $in[ $k ] ?? '' );
		}
		$new['enabled'] = empty( $in['enabled'] ) ? '0' : '1';
		if ( '' === $new['token'] ) {
			$new['token'] = $old['token']; // Blank keeps the saved token.
		}
		update_option( 'skynco_wa', $new, false );
		$note = 'saved';
		if ( ! empty( $_POST['test_owner'] ) ) {
			skynco_wa_owner_alert( 'Test alert from your Skyn&Co. website. Booking and order alerts will arrive here.', 'test' );
			wp_safe_redirect( admin_url( 'admin.php?page=skynco-whatsapp&n=saved' ) );
			exit;
		}
		if ( ! empty( $_POST['test_to'] ) ) {
			$r    = skynco_wa_send( sanitize_text_field( wp_unslash( $_POST['test_to'] ) ), 'hello_world', [], 'test', 'en_US' );
			$note = true === $r ? 'test-ok' : 'test-fail';
		}
		wp_safe_redirect( admin_url( 'admin.php?page=skynco-whatsapp&n=' . $note ) );
		exit;
	}
);

function skynco_page_whatsapp() {
	$s    = skynco_wa_settings();
	$n    = sanitize_key( $_GET['n'] ?? '' );
	$msgs = [ 'saved' => 'Settings saved.', 'test-ok' => 'Test message sent. Check that phone.', 'test-fail' => 'The test message failed. See the log below.' ];
	$f    = fn( $k ) => esc_attr( $s[ $k ] );
	echo '<div class="wrap skynco-wrap"><h1>WhatsApp notifications</h1>';
	if ( isset( $msgs[ $n ] ) ) {
		echo '<div class="notice notice-' . ( 'test-fail' === $n ? 'error' : 'success' ) . '"><p>' . esc_html( $msgs[ $n ] ) . '</p></div>';
	}
	echo '<div class="sk-card"><p>Client confirmations: <b>' . ( skynco_wa_client_ready() ? 'On' : 'Off (connect the WhatsApp Business account below)' ) . '</b> · Studio alerts: <b>' . ( skynco_wa_owner_ready() ? 'On' : 'Off' ) . '</b></p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'skynco_wa' );
	echo '<input type="hidden" name="action" value="skynco_wa_save"><table class="form-table">';
	echo '<tr><th>Send messages</th><td><label><input type="checkbox" name="wa[enabled]" value="1"' . checked( $s['enabled'], '1', false ) . '> Send WhatsApp confirmations to clients</label></td></tr>';
	echo '<tr><th>Access token</th><td><input type="password" class="regular-text" name="wa[token]" placeholder="' . ( $s['token'] ? 'Saved. Leave blank to keep it.' : 'Permanent system-user token' ) . '" autocomplete="off"></td></tr>';
	echo '<tr><th>Phone number ID</th><td><input class="regular-text" name="wa[phone_id]" value="' . $f( 'phone_id' ) . '"></td></tr>';
	echo '<tr><th>Booking template name</th><td><input class="regular-text" name="wa[tpl_booking]" value="' . $f( 'tpl_booking' ) . '"></td></tr>';
	echo '<tr><th>Order template name</th><td><input class="regular-text" name="wa[tpl_order]" value="' . $f( 'tpl_order' ) . '"></td></tr>';
	echo '<tr><th>Template language</th><td><input name="wa[lang]" value="' . $f( 'lang' ) . '" size="8"></td></tr>';
	echo '<tr><th>Default country code</th><td><input name="wa[country]" value="' . $f( 'country' ) . '" size="4"> <span class="description">Used when a client types a local number, for example 1 for the US.</span></td></tr>';
	echo '<tr><th>Graph API version</th><td><input name="wa[version]" value="' . $f( 'version' ) . '" size="8"></td></tr>';
	echo '<tr><th colspan="2"><h2 style="margin:10px 0 0">Instant alerts to the studio</h2><p class="description" style="font-weight:400">Free. Get a WhatsApp message on your own phone for every booking and order. 1) Open callmebot.com → WhatsApp and save the bot number shown there in your contacts. 2) Send it the WhatsApp message <code>I allow callmebot to send me messages</code>. 3) Paste the API key it replies with below.</p></th></tr>';
	echo '<tr><th>Your WhatsApp number</th><td><input class="regular-text" name="wa[owner_phone]" value="' . $f( 'owner_phone' ) . '" placeholder="+1 857 228 4708"></td></tr>';
	echo '<tr><th>CallMeBot API key</th><td><input class="regular-text" name="wa[owner_key]" value="' . $f( 'owner_key' ) . '"></td></tr>';
	echo '<tr><th colspan="2"><h2 style="margin:10px 0 0">Test</h2></th></tr>';
	echo '<tr><th>Send a test</th><td><input name="test_to" placeholder="Your mobile, e.g. +1 857 228 4708" class="regular-text"> <span class="description">Sends Meta’s built-in hello_world template.</span></td></tr>';
	echo '</table><p><button class="button button-primary">Save</button> <button class="button" name="test_owner" value="1">Save and send me a test alert</button></p></form></div>';

	echo '<div class="sk-card"><h2>Templates to create in WhatsApp Manager</h2><p>Create these as <b>Utility</b> templates in English. Meta usually approves them within minutes.</p>';
	echo '<p><b>booking_confirmed</b><br><code>Hi {{1}}, your {{2}} at Skyn&amp;Co. is confirmed for {{3}}. Your booking code is {{4}}. See your visits and rebook anytime: {{5}}</code></p>';
	echo '<p><b>order_confirmed</b><br><code>Hi {{1}}, thank you for your Skyn&amp;Co. order #{{2}} ({{3}}): {{4}}. Track it in your client dashboard: {{5}}</code></p></div>';

	echo '<div class="sk-card"><h2>Recent messages</h2><table class="sk-table widefat"><thead><tr><th>When</th><th>For</th><th>To</th><th>Result</th></tr></thead><tbody>';
	$log = (array) get_option( 'skynco_wa_log', [] );
	if ( ! $log ) {
		echo '<tr><td colspan="4">Nothing sent yet.</td></tr>';
	}
	foreach ( $log as $l ) {
		echo '<tr><td>' . esc_html( wp_date( 'M j, g:i a', $l[0] ) ) . '</td><td>' . esc_html( $l[1] ) . '</td><td>' . esc_html( $l[2] ? '+' . $l[2] : '' ) . '</td><td>' . ( $l[3] ? '✓ ' : '✗ ' ) . esc_html( $l[4] ) . '</td></tr>';
	}
	echo '</tbody></table></div></div>';
}
