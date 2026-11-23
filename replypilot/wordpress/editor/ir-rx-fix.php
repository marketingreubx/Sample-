<?php
/**
 * Plugin Name: IR Reel Editor word check
 * Description: The Reel Editor sends a small, compressed copy of the voice (about 0.25 MB a minute) and its own draft
 *   captions; the site's AI listens and returns the exact words. The browser keeps the word timings. Nothing is stored.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_ajax_ir_rx_fix', function () {
	if ( ! check_ajax_referer( 'ir_rx_fix', 'n', false ) ) { wp_send_json( [ 'ok' => false, 'err' => 'expired' ] ); }
	if ( ! function_exists( 'ir_ai_gemini' ) || ! function_exists( 'ir_ai_key' ) || ir_ai_key() === '' ) { wp_send_json( [ 'ok' => false, 'err' => 'noai' ] ); }
	$uid = get_current_user_id();
	$k = 'ir_rx_fix_n_' . $uid; $n = (int) get_transient( $k );
	if ( $n >= 30 ) { wp_send_json( [ 'ok' => false, 'err' => 'limit' ] ); }
	set_transient( $k, $n + 1, HOUR_IN_SECONDS );
	$f = $_FILES['a'] ?? null;
	if ( ! $f || ! empty( $f['error'] ) || (int) $f['size'] < 500 || (int) $f['size'] > 4 * MB_IN_BYTES ) { wp_send_json( [ 'ok' => false, 'err' => 'file' ] ); }
	$bytes = file_get_contents( $f['tmp_name'] );
	if ( substr( $bytes, 0, 3 ) !== 'ID3' && ( ord( $bytes[0] ) !== 0xFF ) ) { wp_send_json( [ 'ok' => false, 'err' => 'type' ] ); }
	$draft = mb_substr( sanitize_textarea_field( wp_unslash( (string) ( $_POST['draft'] ?? '' ) ) ), 0, 12000 );
	@set_time_limit( 90 );
	$sys = 'You are a careful transcriber. Write down exactly what the speaker says in this audio, word for word, in the order spoken. '
		. 'Keep their own wording, slang and repeated words; fix only mishearings. Use normal spelling, capitalisation and punctuation. '
		. 'Write names of brands, apps and people correctly (for example Instagram, TikTok, ChatGPT, Claude, Canva, CapCut). '
		. 'No timestamps, no speaker labels, no notes, no sound descriptions. Output only the transcript.';
	$r = ir_ai_gemini( $sys, [ ir_ai_media_part( $bytes, 'audio/mpeg' ), [ 'text' => "A rough draft from another tool (it has mistakes, use it only as a hint):\n" . $draft ] ], [ 'temp' => 0, 'max' => 6000, 'think' => 0, 'timeout' => 45 ] );
	if ( is_wp_error( $r ) ) { wp_send_json( [ 'ok' => false, 'err' => 'ai' ] ); }
	$t = trim( (string) ( is_array( $r ) ? ( $r['text'] ?? '' ) : $r ) );
	wp_send_json( [ 'ok' => $t !== '', 'text' => mb_substr( $t, 0, 20000 ) ] );
} );
