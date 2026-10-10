<?php
/**
 * Email delivery: send every site email (WooCommerce, LatePoint, dashboard links)
 * through an authenticated SMTP mailbox so it reaches the inbox, not spam.
 * Settings: Studio → Email delivery.
 *
 * @package SkyncoStudio
 */

defined( 'ABSPATH' ) || exit;

function skynco_smtp_settings() {
	return wp_parse_args(
		(array) get_option( 'skynco_smtp', [] ),
		[
			'enabled'    => '0',
			'host'       => '',
			'port'       => '587',
			'secure'     => 'tls',
			'user'       => '',
			'pass'       => '',
			'from_email' => '',
			'from_name'  => 'Skyn&Co. Skincare & Wellness',
			'reply_to'   => '',
		]
	);
}

/** SMTP is only used once a password has been saved, so mail never stops working. */
function skynco_smtp_on() {
	$s = skynco_smtp_settings();
	return '1' === $s['enabled'] && $s['host'] && $s['user'] && $s['pass'];
}

/**
 * Sender used while SMTP is not connected. A temporary host name (e.g. *.hostingersite.com)
 * is silently dropped by Gmail, so a real domain on the same hosting can be set in
 * Studio → Email delivery (option skynco_mail_sender). Never a Gmail address.
 */
function skynco_site_sender() {
	$set = (string) get_option( 'skynco_mail_sender', '' );
	if ( is_email( $set ) ) {
		return $set;
	}
	return 'noreply@' . preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
}

function skynco_smtp_presets() {
	return [
		'gmail'     => [ 'Gmail / Google Workspace', 'smtp.gmail.com', '587', 'tls', 'Use an App Password (Google Account → Security → 2-Step Verification → App passwords).' ],
		'hostinger' => [ 'Hostinger Email', 'smtp.hostinger.com', '465', 'ssl', 'Use the mailbox address and its password from hPanel → Emails.' ],
		'brevo'     => [ 'Brevo (free 300/day)', 'smtp-relay.brevo.com', '587', 'tls', 'Use the SMTP login and key from Brevo → SMTP & API.' ],
	];
}

/* Same sender everywhere. */
add_filter(
	'wp_mail_from',
	function ( $from ) {
		$s = skynco_smtp_settings();
		return skynco_smtp_on() ? ( $s['from_email'] ?: $s['user'] ) : skynco_site_sender();
	},
	99
);
add_filter(
	'wp_mail_from_name',
	function ( $name ) {
		$s = skynco_smtp_settings();
		return $s['from_name'] ?: $name;
	},
	99
);
foreach ( [ 'woocommerce_email_from_address' => 'from_email', 'woocommerce_email_from_name' => 'from_name' ] as $opt => $key ) {
	add_filter(
		'pre_option_' . $opt,
		function ( $v ) use ( $key ) {
			$s = skynco_smtp_settings();
			if ( 'from_email' === $key ) {
				return skynco_smtp_on() ? ( $s['from_email'] ?: $s['user'] ) : skynco_site_sender();
			}
			return $s[ $key ] ?: $v;
		}
	);
}

add_action(
	'phpmailer_init',
	function ( $m ) {
		$s = skynco_smtp_settings();
		// Spam-filter hygiene: a plain-text part for every HTML email, no "PHPMailer" header,
		// and message IDs from our own domain.
		$m->XMailer  = ' ';
		$m->Hostname = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		if ( 'text/html' === $m->ContentType && ! $m->AltBody ) {
			$txt = preg_replace( '#<(br|/p|/div|/h[1-6]|/li|/tr)[^>]*>#i', "\n", $m->Body );
			$txt = preg_replace( '#<a [^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', '$2 ($1)', $txt );
			$txt = html_entity_decode( wp_strip_all_tags( preg_replace( '#<(style|script)[^>]*>.*?</\1>#is', '', $txt ) ), ENT_QUOTES, 'UTF-8' );
			$m->AltBody = trim( preg_replace( "/[ \t]+/", ' ', preg_replace( "/\n\s*\n+/", "\n\n", $txt ) ) );
		}
		if ( ! skynco_smtp_on() ) {
			if ( ! $m->getReplyToAddresses() ) {
				$m->addReplyTo( get_option( 'admin_email' ), $s['from_name'] ?: 'Skyn&Co.' );
			}
			return;
		}
		$m->isSMTP();
		$m->Host       = $s['host'];
		$m->Port       = (int) $s['port'];
		$m->SMTPSecure = 'none' === $s['secure'] ? '' : $s['secure'];
		$m->SMTPAuth   = true;
		$m->Username   = $s['user'];
		$m->Password   = $s['pass'];
		$m->Timeout    = 15;
		if ( $s['from_email'] ) {
			$m->setFrom( $s['from_email'], $s['from_name'] ?: 'Skyn&Co.', false );
			$m->Sender = $s['from_email'];
		}
		if ( $s['reply_to'] && ! $m->getReplyToAddresses() ) {
			$m->addReplyTo( $s['reply_to'], $s['from_name'] );
		}
	},
	99
);

/* Remember the last delivery error for the settings page. */
add_action(
	'wp_mail_failed',
	function ( $err ) {
		update_option( 'skynco_mail_last_error', [ time(), $err->get_error_message() ], false );
	}
);

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'skynco-studio', 'Email delivery', 'Email delivery', SKYNCO_STUDIO_CAP, 'skynco-email', 'skynco_page_email' );
	},
	31
);

add_action(
	'admin_post_skynco_smtp_save',
	function () {
		if ( ! current_user_can( SKYNCO_STUDIO_CAP ) || ! check_admin_referer( 'skynco_smtp' ) ) {
			wp_die( 'Not allowed' );
		}
		$old = skynco_smtp_settings();
		$in  = wp_unslash( $_POST['smtp'] ?? [] );
		$new = [];
		foreach ( array_keys( $old ) as $k ) {
			$new[ $k ] = 'pass' === $k ? (string) ( $in[ $k ] ?? '' ) : sanitize_text_field( $in[ $k ] ?? '' );
		}
		$new['enabled'] = empty( $in['enabled'] ) ? '0' : '1';
		if ( '' === $new['pass'] ) {
			$new['pass'] = $old['pass'];
		}
		if ( ! $new['from_email'] ) {
			$new['from_email'] = $new['user'];
		}
		update_option( 'skynco_smtp', $new, false );
		$sender = sanitize_email( wp_unslash( $_POST['site_sender'] ?? '' ) );
		update_option( 'skynco_mail_sender', is_email( $sender ) ? $sender : '', false );
		$note = 'saved';
		if ( ! empty( $_POST['test_to'] ) ) {
			delete_option( 'skynco_mail_last_error' );
			$ok   = wp_mail( sanitize_email( wp_unslash( $_POST['test_to'] ) ), 'Skyn&Co. test email', '<p>This is a test from your Skyn&amp;Co. website. If it landed in your inbox, email delivery is working.</p>', [ 'Content-Type: text/html; charset=UTF-8' ] );
			$note = $ok ? 'test-ok' : 'test-fail';
		}
		wp_safe_redirect( admin_url( 'admin.php?page=skynco-email&n=' . $note ) );
		exit;
	}
);

function skynco_page_email() {
	$s    = skynco_smtp_settings();
	$n    = sanitize_key( $_GET['n'] ?? '' );
	$err  = get_option( 'skynco_mail_last_error' );
	$msgs = [ 'saved' => 'Settings saved.', 'test-ok' => 'Test email sent. Check the inbox (and spam, the first time).', 'test-fail' => 'The test email failed: ' . ( $err[1] ?? 'unknown error' ) ];
	$f    = fn( $k ) => esc_attr( $s[ $k ] );
	echo '<div class="wrap skynco-wrap"><h1>Email delivery</h1>';
	if ( isset( $msgs[ $n ] ) ) {
		echo '<div class="notice notice-' . ( 'test-fail' === $n ? 'error' : 'success' ) . '"><p>' . esc_html( $msgs[ $n ] ) . '</p></div>';
	}
	$on = skynco_smtp_on();
	echo '<div class="sk-card"><p>Status: <b>' . ( $on ? 'Sending through ' . esc_html( $s['host'] ) . ' as ' . esc_html( $s['from_email'] ) : 'Not connected. Emails are sent by the web server and often land in spam.' ) . '</b></p>';
	echo '<p>Pick your provider to fill in the server details, then add the mailbox login.</p><p>';
	foreach ( skynco_smtp_presets() as $k => $p ) {
		echo '<button type="button" class="button sk-preset" data-host="' . esc_attr( $p[1] ) . '" data-port="' . esc_attr( $p[2] ) . '" data-secure="' . esc_attr( $p[3] ) . '" data-help="' . esc_attr( $p[4] ) . '">' . esc_html( $p[0] ) . '</button> ';
	}
	echo '</p><p id="sk-preset-help" class="description"></p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'skynco_smtp' );
	echo '<input type="hidden" name="action" value="skynco_smtp_save"><table class="form-table">';
	echo '<tr><th>Use SMTP</th><td><label><input type="checkbox" name="smtp[enabled]" value="1"' . checked( $s['enabled'], '1', false ) . '> Send all site emails through this mailbox</label></td></tr>';
	echo '<tr><th>SMTP server</th><td><input class="regular-text" id="sk-host" name="smtp[host]" value="' . $f( 'host' ) . '"></td></tr>';
	echo '<tr><th>Port</th><td><input id="sk-port" name="smtp[port]" value="' . $f( 'port' ) . '" size="5"> <select id="sk-secure" name="smtp[secure]">';
	foreach ( [ 'tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None' ] as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['secure'], $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><th>Username (email)</th><td><input class="regular-text" name="smtp[user]" value="' . $f( 'user' ) . '" autocomplete="off"></td></tr>';
	echo '<tr><th>Password</th><td><input type="password" class="regular-text" name="smtp[pass]" placeholder="' . ( $s['pass'] ? 'Saved. Leave blank to keep it.' : '' ) . '" autocomplete="new-password"></td></tr>';
	echo '<tr><th>From email</th><td><input class="regular-text" name="smtp[from_email]" value="' . $f( 'from_email' ) . '"> <span class="description">Must be the same mailbox (or an alias of it). Leave blank to use the username.</span></td></tr>';
	echo '<tr><th>From name</th><td><input class="regular-text" name="smtp[from_name]" value="' . $f( 'from_name' ) . '"></td></tr>';
	echo '<tr><th>Reply-to</th><td><input class="regular-text" name="smtp[reply_to]" value="' . $f( 'reply_to' ) . '"> <span class="description">Optional. Where client replies should go.</span></td></tr>';
	echo '<tr><th>Sender without SMTP</th><td><input class="regular-text" name="site_sender" value="' . esc_attr( get_option( 'skynco_mail_sender', '' ) ) . '" placeholder="' . esc_attr( 'noreply@' . wp_parse_url( home_url(), PHP_URL_HOST ) ) . '"> <span class="description">Used until SMTP is connected. Must be an address on a real domain hosted on this server.</span></td></tr>';
	echo '<tr><th>Send a test to</th><td><input class="regular-text" name="test_to" placeholder="you@example.com"></td></tr>';
	echo '</table><p><button class="button button-primary">Save</button></p></form></div>';
	echo '<script>document.querySelectorAll(".sk-preset").forEach(function(b){b.addEventListener("click",function(){document.getElementById("sk-host").value=b.dataset.host;document.getElementById("sk-port").value=b.dataset.port;document.getElementById("sk-secure").value=b.dataset.secure;document.getElementById("sk-preset-help").textContent=b.dataset.help;});});</script></div>';
}

/* ---------------------------------------------------------------------------
 * Branded email look.
 * ------------------------------------------------------------------------ */
add_filter( 'pre_option_woocommerce_email_base_color', fn() => '#D1127E' );
add_filter( 'pre_option_woocommerce_email_background_color', fn() => '#FBF6F4' );
add_filter( 'pre_option_woocommerce_email_text_color', fn() => '#2A1A24' );
add_filter( 'pre_option_woocommerce_email_footer_text', fn() => 'Skyn&Co. Skincare & Wellness · 150 Arsenal St, Suite 210, Watertown, MA 02472 · (857) 228-4708' );
