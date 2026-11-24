<?php
/**
 * Plugin Name: IR Reel Editor planner + pictures
 * Description: The Reel Editor sends the numbered transcript (text only) and gets back an edit plan written by the site's AI
 *   (hook, list, key words, punchlines, word cards, number/time spellings and the viral format: ranking, worth it / not worth it,
 *   numbered listicle, yes / no). It can also fetch free-to-use photos (Openverse, commercial licences only) for the things the
 *   speaker names; every photo is downloaded once, resized and cached on this site, so the browser never calls a third party.
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
	$sys = <<<'TXT'
You are a senior short-form video editor (Instagram Reels / TikTok). You get a talking-head transcript as numbered words (index:word).
Plan the on-screen graphics so the edit feels premium and clearly follows what the speaker says. Answer ONLY with JSON in this shape:
{"topic":"<=8 words","hook":{"from":i,"to":j,"text":"<=6 words, punchy, from the opening line"},
"list":{"title":"<=5 words or empty","noun":"sign|step|tip|mistake|reason|rule|habit|myth|hack|lesson|symptom|secret|question|point","items":[{"from":i,"to":j,"n":1,"label":"2-5 words naming the point"}]},
"keywords":[i,...],"stacks":[{"from":i,"to":j,"lead":"2-4 small words","big":"1-3 BIG words","tail":"0-3 small words"}],
"cards":[{"from":i,"to":j,"lead":"2-4 small words","text":"1-3 big words"}],"fixes":[{"i":i,"text":"display spelling"}],"cta":{"from":i,"keyword":"WORD"},
"pictures":[{"from":i,"to":j,"query":"2-4 words"}],
"viral":{"format":"ranking|worth|listicle|yesno|plain","title":"<=7 words","sides":{"left":"NOT WORTH IT","right":"WORTH IT"},
"entries":[{"from":i,"to":j,"label":"1-5 words","result":"","query":"","score":"","side":"left|right","question":""}]}}
Rules:
- Every index must exist and point at the words spoken at that moment. Use the speaker's own words; never invent claims, numbers or products.
- noun: the speaker's own word for the points (a video about warning signs uses sign, a how-to uses step); never default to step. list: only when the speaker really goes through numbered points (2 or more); each item starts where that point starts and ends where it ends; label names the point itself, never just the word "number". Otherwise items is [].
- keywords: the single most important word of each sentence (noun, number, result or emotion), at most one per sentence, never filler.
- stacks: the punchlines, about one every 6-10 seconds of speech; big = the key words exactly as spoken.
- cards: only very strong short statements or numbers with a unit ("6 Months", "$10K"), at most one every 15 seconds, never inside a list item.
- fixes: display spelling for numbers, times, money and brand names as people write them: "2 am" becomes "2 AM" (never 2000 or 200), "two thousand" becomes "2,000", "30 percent" becomes "30%", "cloud" becomes "Claude" when it is the AI, "chat gpt" becomes "ChatGPT". Only words that need it.
- cta: if the speaker asks viewers to comment a word, its index and the keyword, else null.
- pictures: only concrete things a viewer could see in a photo (food, product type, body part, object, place), at most one per 8 seconds, from = the word where it is named, to = a few words later, query = plain search words (no brand unless the speaker says one). Empty array when nothing fits. Never for abstract ideas.
- viral.format: ranking = the speaker rates or ranks things (x out of 10, tiers); entries: label = the thing, score = "9/10" style (convert "nine out of ten" to "9/10"), query = photo of the thing, from = where the thing is named, to = where the score is said.
  worth = the speaker judges things worth it / not worth it, good / bad, do / don't, avoid / eat, or sorts things into two groups (for example foods and drinks); sides = the two group names in uppercase from the speaker's framing (NOT WORTH IT / WORTH IT, DON'Ts / DOs, AVOID / EAT, FOODS / DRINKS), entries: label = the thing, side = left or right, query = photo of the thing.
  listicle = numbered tips, hacks or steps; entries in order: label = the tip in at most 6 words using the speaker's words; when each point pairs a thing with an effect ("ACV for toenail fungus") set label = the thing and result = the effect.
  yesno = the speaker asks a question and answers yes or no; entries: question = the full question (<=14 words), label = "YES" or "NO", from = where the question starts, to = the answer word.
  plain = anything else; entries = [].
- viral.title: the topic as a short title using the speaker's words ("Doctor Ranks Foods for Muscle Building", "Menopause", "Tretinoin: Yes or No"). Empty for plain.
TXT;
	@set_time_limit( 90 );
	$r = ir_ai_gemini( $sys, [ [ 'text' => 'Style: ' . $style . "\nWords:\n" . $numbered ] ], [ 'temp' => 0.2, 'max' => 8000, 'think' => 0, 'timeout' => 60, 'json' => true ] );
	if ( is_wp_error( $r ) ) { wp_send_json( [ 'ok' => false, 'err' => 'ai' ] ); }
	$t = trim( (string) ( is_array( $r ) ? ( $r['text'] ?? '' ) : $r ) );
	$t = trim( preg_replace( '/^```(?:json)?|```$/m', '', $t ) );
	$j = json_decode( $t, true );
	if ( ! is_array( $j ) ) { wp_send_json( [ 'ok' => false, 'err' => 'json' ] ); }
	wp_send_json( [ 'ok' => true, 'plan' => $j ] );
} );

/* ---------------- free-to-use photos ---------------- */
function ir_rx_multi_get( $urls, $ua, $timeout = 14 ) {
	$out = []; if ( ! $urls ) { return $out; }
	$mh = curl_multi_init(); $hs = [];
	foreach ( $urls as $k => $u ) {
		$ch = curl_init( $u ); curl_setopt_array( $ch, [ CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_USERAGENT => $ua, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS ] );
		curl_multi_add_handle( $mh, $ch ); $hs[ $k ] = $ch;
	}
	do { $st = curl_multi_exec( $mh, $run ); if ( $run ) { curl_multi_select( $mh, 1 ); } } while ( $run && $st === CURLM_OK );
	foreach ( $hs as $k => $ch ) { $code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE ); $b = curl_multi_getcontent( $ch ); if ( $code === 200 && is_string( $b ) && $b !== '' ) { $out[ $k ] = $b; } curl_multi_remove_handle( $mh, $ch ); curl_close( $ch ); }
	curl_multi_close( $mh ); return $out;
}
/* Finds ONE photo that really shows the thing: candidates from Openverse + Wikimedia Commons (commercial-use licences only),
   the AI looks at them and picks the one that clearly shows it, or none. */
function ir_rx_img_find( $q ) {
	$q = trim( preg_replace( '/\s+/', ' ', $q ) ); if ( $q === '' ) { return null; }
	$up = wp_upload_dir(); $dir = $up['basedir'] . '/ir-editor/imgc'; $base = $up['baseurl'] . '/ir-editor/imgc';
	$key = 'ir_rx_img2_' . md5( strtolower( $q ) ); $c = get_transient( $key );
	if ( is_array( $c ) && ! empty( $c['file'] ) && file_exists( $dir . '/' . $c['file'] ) ) { return $c; }
	if ( $c === 'none' ) { return null; }
	wp_mkdir_p( $dir ); $ua = 'ReelEditor/1.0 (+https://ighoreuben.com; photo lookup)';
	$res = ir_rx_multi_get( [
		'ov' => 'https://api.openverse.org/v1/images/?q=' . rawurlencode( $q ) . '&license_type=commercial&category=photograph&mature=false&page_size=12',
		'wm' => 'https://commons.wikimedia.org/w/api.php?action=query&format=json&generator=search&gsrnamespace=6&gsrlimit=10&gsrsearch=' . rawurlencode( $q . ' filetype:bitmap' ) . '&prop=imageinfo&iiprop=url|size|mime|extmetadata&iiurlwidth=500',
	], $ua, 14 );
	$cands = [];
	$j = json_decode( $res['ov'] ?? '', true );
	foreach ( (array) ( $j['results'] ?? [] ) as $x ) {
		$w = (int) ( $x['width'] ?? 0 ); $h = (int) ( $x['height'] ?? 0 ); $lic = strtolower( (string) ( $x['license'] ?? '' ) );
		if ( $w < 600 || $h < 450 || empty( $x['url'] ) || empty( $x['thumbnail'] ) || ! in_array( $lic, [ 'cc0', 'pdm', 'by', 'by-sa' ], true ) ) { continue; }
		$ar = $w / max( $h, 1 ); $sc = ( in_array( $lic, [ 'cc0', 'pdm' ], true ) ? 1 : 0 ) + ( $ar >= 0.8 && $ar <= 1.8 ? 1.5 : 0 ) + min( $w, 1600 ) / 1600;
		$L = strtoupper( $lic ) . ( ! empty( $x['license_version'] ) ? ' ' . $x['license_version'] : '' );
		$cands[] = [ 's' => $sc, 'thumb' => (string) $x['thumbnail'], 'full' => (string) $x['url'], 'id' => 'ov' . ( $x['id'] ?? '' ), 'credit' => mb_substr( trim( (string) ( $x['creator'] ?? '' ) ) ?: 'Unknown', 0, 60 ) . ' (' . $L . ')', 'license' => $L, 'title' => mb_substr( (string) ( $x['title'] ?? '' ), 0, 80 ), 'link' => (string) ( $x['foreign_landing_url'] ?? '' ) ];
	}
	$j = json_decode( $res['wm'] ?? '', true );
	foreach ( (array) ( $j['query']['pages'] ?? [] ) as $pg ) {
		$ii = $pg['imageinfo'][0] ?? null; if ( ! $ii ) { continue; }
		$em = $ii['extmetadata'] ?? []; $ln = trim( (string) ( $em['LicenseShortName']['value'] ?? '' ) );
		if ( ! preg_match( '/^(cc0|public domain|pd\b|cc by(-sa)? [\d.]+)/i', $ln ) || preg_match( '/\b(nc|nd)\b/i', $ln ) ) { continue; }
		if ( ( $ii['width'] ?? 0 ) < 600 || ( $ii['height'] ?? 0 ) < 450 || empty( $ii['thumburl'] ) || ! in_array( $ii['mime'] ?? '', [ 'image/jpeg', 'image/png', 'image/webp' ], true ) ) { continue; }
		$ar = $ii['width'] / max( $ii['height'], 1 ); $who = trim( wp_strip_all_tags( (string) ( $em['Artist']['value'] ?? '' ) ) ) ?: 'Wikimedia Commons';
		$cands[] = [ 's' => 0.5 + ( $ar >= 0.8 && $ar <= 1.8 ? 1.5 : 0 ) + min( (int) $ii['width'], 1600 ) / 1600, 'thumb' => (string) $ii['thumburl'], 'full' => str_replace( '/500px-', '/1280px-', (string) $ii['thumburl'] ), 'id' => 'wm' . ( $pg['pageid'] ?? '' ), 'credit' => mb_substr( $who, 0, 60 ) . ' (' . $ln . ')', 'license' => $ln, 'title' => mb_substr( preg_replace( '/^File:/', '', (string) ( $pg['title'] ?? '' ) ), 0, 80 ), 'link' => (string) ( $ii['descriptionurl'] ?? '' ) ];
	}
	usort( $cands, function ( $a, $b ) { return $b['s'] <=> $a['s']; } ); $cands = array_slice( $cands, 0, 8 );
	if ( ! $cands ) { set_transient( $key, 'none', HOUR_IN_SECONDS ); return null; }
	$th = ir_rx_multi_get( array_map( function ( $c ) { return $c['thumb']; }, $cands ), $ua, 14 );
	$parts = [ [ 'text' => 'Search words: "' . $q . '". Images follow, numbered from 0.' ] ]; $map = [];
	foreach ( $cands as $i => $cd ) { if ( empty( $th[ $i ] ) ) { continue; } $inf = @getimagesizefromstring( $th[ $i ] ); if ( ! $inf || ! in_array( $inf[2], [ IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP ], true ) ) { continue; } $parts[] = [ 'text' => 'Image ' . $i . ':' ]; $parts[] = [ 'inline_data' => [ 'mime_type' => $inf['mime'], 'data' => base64_encode( $th[ $i ] ) ] ]; $map[] = $i; }
	if ( ! $map ) { set_transient( $key, 'none', 10 * MINUTE_IN_SECONDS ); return null; }
	$sys = 'You pick one picture for a short video. Choose the ONE image that clearly and mainly shows exactly the thing in the search words, as a clean, well-lit real photo anyone would recognise at a glance. Reject illustrations, logos, diagrams, collages, maps, people posing as the main subject, packaged products with big brand text, and anything that shows a different thing. If no image clearly qualifies, answer -1. Reply only JSON: {"pick": number}';
	$r = ir_ai_gemini( $sys, $parts, [ 'temp' => 0, 'max' => 60, 'think' => 0, 'json' => true, 'timeout' => 40, 'res' => 'low' ] );
	if ( is_wp_error( $r ) ) { return null; }
	$pj = json_decode( trim( preg_replace( '/^```(?:json)?|```$/m', '', (string) $r ) ), true ); $pick = isset( $pj['pick'] ) ? (int) $pj['pick'] : -1;
	if ( ! in_array( $pick, $map, true ) ) { set_transient( $key, 'none', HOUR_IN_SECONDS ); return null; }
	$cd = $cands[ $pick ]; $g = wp_safe_remote_get( $cd['full'], [ 'timeout' => 18, 'limit_response_size' => 8 * MB_IN_BYTES, 'user-agent' => $ua ] );
	$body = ( ! is_wp_error( $g ) && wp_remote_retrieve_response_code( $g ) === 200 ) ? wp_remote_retrieve_body( $g ) : $th[ $pick ];
	$tmp = wp_tempnam( 'rximg' ); file_put_contents( $tmp, $body ); $info = @getimagesize( $tmp );
	if ( ! $info || ! in_array( $info[2], [ IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP ], true ) || $info[0] < 400 ) { @unlink( $tmp ); $tmp = wp_tempnam( 'rximg' ); file_put_contents( $tmp, $th[ $pick ] ); $info = @getimagesize( $tmp ); if ( ! $info ) { @unlink( $tmp ); return null; } }
	$ed = wp_get_image_editor( $tmp ); if ( is_wp_error( $ed ) ) { @unlink( $tmp ); return null; }
	$ed->resize( 1100, 1100, false ); $ed->set_quality( 86 ); $name = md5( $q . $cd['id'] ) . '.jpg'; $s = $ed->save( $dir . '/' . $name, 'image/jpeg' ); @unlink( $tmp );
	if ( is_wp_error( $s ) ) { return null; }
	$out = [ 'file' => $name, 'url' => $base . '/' . $name, 'credit' => $cd['credit'], 'license' => $cd['license'], 'title' => $cd['title'], 'link' => $cd['link'] ];
	set_transient( $key, $out, 14 * DAY_IN_SECONDS ); return $out;
}
add_action( 'wp_ajax_ir_rx_img', function () {
	if ( ! check_ajax_referer( 'ir_rx_fix', 'n', false ) ) { wp_send_json( [ 'ok' => false, 'err' => 'expired' ] ); }
	$uid = get_current_user_id(); $k = 'ir_rx_img_n_' . $uid; $n = (int) get_transient( $k );
	if ( $n >= 240 ) { wp_send_json( [ 'ok' => false, 'err' => 'limit' ] ); }
	set_transient( $k, $n + 1, HOUR_IN_SECONDS );
	$qs = json_decode( wp_unslash( (string) ( $_POST['q'] ?? '[]' ) ), true ); if ( ! is_array( $qs ) ) { wp_send_json( [ 'ok' => false, 'err' => 'q' ] ); }
	@set_time_limit( 100 ); $out = []; $t0 = microtime( true );
	foreach ( array_slice( array_unique( array_map( function ( $s ) { return mb_substr( sanitize_text_field( (string) $s ), 0, 60 ); }, $qs ) ), 0, 3 ) as $q ) {
		if ( microtime( true ) - $t0 > 60 ) { break; } $r = ir_rx_img_find( $q ); if ( $r ) { $out[ $q ] = [ 'url' => $r['url'], 'credit' => $r['credit'], 'license' => $r['license'], 'title' => $r['title'], 'link' => $r['link'] ]; }
	}
	$old = glob( wp_upload_dir()['basedir'] . '/ir-editor/imgc/*.jpg' ) ?: []; foreach ( $old as $f ) { if ( filemtime( $f ) < time() - 15 * DAY_IN_SECONDS ) { @unlink( $f ); } }
	wp_send_json( [ 'ok' => true, 'imgs' => $out ] );
} );
