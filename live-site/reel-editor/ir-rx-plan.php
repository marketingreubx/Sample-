<?php
/**
 * Plugin Name: IR Reel Editor planner
 * Description: The Reel Editor sends the numbered transcript (text only) and gets back an edit plan written by the
 *   site's AI: hook, numbered list, the key word of every line, punchlines, full-screen word cards and correct
 *   display spellings for numbers and times ("2 AM", "$5,000", "30%"). The browser renders everything.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_ajax_ir_rx_plan', function () {
	if ( ! check_ajax_referer( 'ir_rx_fix', 'n', false ) ) { wp_send_json( [ 'ok' => false, 'err' => 'expired' ] ); }
	if ( ! function_exists( 'ir_ai_gemini' ) || ! function_exists( 'ir_ai_key' ) || ir_ai_key() === '' ) { wp_send_json( [ 'ok' => false, 'err' => 'noai' ] ); }
	$uid = get_current_user_id(); $k = 'ir_rx_plan_n_' . $uid; $n = (int) get_transient( $k );
	if ( $n >= 40 ) { wp_send_json( [ 'ok' => false, 'err' => 'limit' ] ); }
	set_transient( $k, $n + 1, HOUR_IN_SECONDS );
	$words = json_decode( wp_unslash( (string) ( $_POST['words'] ?? '[]' ) ), true );
	if ( ! is_array( $words ) || count( $words ) < 3 ) { wp_send_json( [ 'ok' => false, 'err' => 'words' ] ); }
	$words = array_slice( array_map( function ( $w ) { return mb_substr( sanitize_text_field( (string) $w ), 0, 40 ); }, $words ), 0, 2600 );
	$style = sanitize_key( (string) ( $_POST['style'] ?? '' ) );
	$numbered = ''; foreach ( $words as $i => $w ) { $numbered .= $i . ':' . $w . ' '; }
	$sys = 'You are a senior short-form video editor (Instagram Reels). You get a talking-head transcript as numbered words (index:word). '
		. 'Plan the on-screen text so the edit feels premium and clearly follows what the speaker is saying. Answer ONLY with JSON in this shape: '
		. '{"topic":"<=8 words","hook":{"from":i,"to":j,"text":"<=6 words, punchy, from the opening line"},'
		. '"list":{"title":"<=5 words or empty","items":[{"from":i,"to":j,"n":1,"label":"2-5 words naming the item"}]},'
		. '"keywords":[i,...],"stacks":[{"from":i,"to":j,"lead":"2-4 small words","big":"1-3 BIG words","tail":"0-3 small words"}],'
		. '"cards":[{"from":i,"to":j,"lead":"2-4 small words","text":"1-3 big words"}],"fixes":[{"i":i,"text":"display spelling"}],"cta":{"from":i,"keyword":"WORD"}}. '
		. 'Rules: every index must exist and point at the words spoken at that moment. '
		. 'list: only when the speaker really goes through numbered points, steps, mistakes, tips or reasons (2 or more); each item starts where that point starts and ends where it ends; label names the point itself (e.g. "Show the result first"), never just the word "number". Otherwise items is []. '
		. 'keywords: the single most important word of each sentence (noun, number, result or emotion), at most one per sentence, never filler. '
		. 'stacks: the punchlines, about one every 6-10 seconds of speech; big = the key words exactly as spoken. '
		. 'cards: only very strong short statements or numbers with a unit ("6 Months", "$10K", "Good News"), at most one every 15 seconds, never inside a list item. '
		. 'fixes: display spelling for numbers, times, money and brand names as people write them: "2 am" becomes "2 AM" (never 2000 or 200), "two thousand" becomes "2,000", "30 percent" becomes "30%", "cloud" becomes "Claude" when it is the AI, "chat gpt" becomes "ChatGPT". Only words that need it. '
		. 'cta: if the speaker asks viewers to comment a word, its index and the keyword, else null. Use the speaker\'s own words; never invent claims.';
	@set_time_limit( 90 );
	$r = ir_ai_gemini( $sys, [ [ 'text' => 'Style: ' . $style . "\nWords:\n" . $numbered ] ], [ 'temp' => 0.2, 'max' => 6000, 'think' => 0, 'timeout' => 60, 'json' => true ] );
	if ( is_wp_error( $r ) ) { wp_send_json( [ 'ok' => false, 'err' => 'ai' ] ); }
	$t = trim( (string) ( is_array( $r ) ? ( $r['text'] ?? '' ) : $r ) );
	$t = trim( preg_replace( '/^```(?:json)?|```$/m', '', $t ) );
	$j = json_decode( $t, true );
	if ( ! is_array( $j ) ) { wp_send_json( [ 'ok' => false, 'err' => 'json' ] ); }
	wp_send_json( [ 'ok' => true, 'plan' => $j ] );
} );
