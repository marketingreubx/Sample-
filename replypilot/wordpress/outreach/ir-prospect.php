<?php
/**
 * Plugin Name: IR Prospect reader
 * Description: Reads a prospect's Instagram profile (bio, links, category, posts) through the site's own connected Meta app
 *   (Business Discovery, free) and a Google Business listing (phone, address, website, rating, reviews count, hours)
 *   from Google Maps, free. Finds their email and phone on the website they link to. Also rewrites drafted outreach so
 *   it reads naturally, is about the prospect and ends with an easy question. No paid services.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------- Instagram: Business Discovery through the owner's connected account ---------- */

/** A connected Reubx DM account whose token can look up other business profiles. */
function ir_px_bd_acct() {
	if ( get_transient( 'ir_px_bd_off' ) || ! function_exists( 'ir_dm_owners' ) ) { return null; }
	foreach ( ir_dm_owners() as $uid ) { foreach ( ir_dm_accts( $uid ) as $a ) {
		if ( ( $a['status'] ?? '' ) === 'active' && ( $a['login'] ?? '' ) === 'unified' && user_can( $uid, 'manage_options' ) ) { return $a; }
	} }
	return null;
}
/** Kept for the readers that ask "is the full reader available?". */
function ir_px_token() { return ir_px_bd_acct() ? 'bd' : ''; }

function ir_px_ig_raw( $handles ) {
	$out = [];
	foreach ( (array) $handles as $h ) { $r = ir_px_ig( $h ); if ( ! is_wp_error( $r ) ) { $out[ strtolower( trim( (string) $h, "@/ \t" ) ) ] = $r; } elseif ( $r->get_error_code() === 'bd_off' ) { break; } }
	return $out;
}

/** Same shape as the Scanner's own reader (facts, contact, name, stats, caps, imgs), plus the bio and links. */
function ir_px_ig( $h ) {
	$h = strtolower( trim( (string) $h, "@/ \t" ) );
	if ( ! preg_match( '/^[a-z0-9._]{2,30}$/', $h ) ) { return new WP_Error( 'ig', 'That does not look like an Instagram handle.' ); }
	$ck = 'ir_px_ig_' . md5( $h );
	$c = get_transient( $ck );
	if ( is_array( $c ) ) { return $c; }
	$a = ir_px_bd_acct();
	if ( ! $a ) { return new WP_Error( 'bd_off', 'Business Discovery not available.' ); }
	$f = 'business_discovery.username(' . $h . '){username,name,biography,website,followers_count,follows_count,media_count,profile_picture_url,media.limit(12){caption,like_count,comments_count,timestamp,media_type,media_product_type,media_url,thumbnail_url}}';
	$GLOBALS['ir_dm_to'] = 15;
	$r = ir_dm_api( $a, 'GET', $a['id'] . '?fields=' . rawurlencode( $f ) );
	unset( $GLOBALS['ir_dm_to'] );
	if ( is_wp_error( $r ) ) {
		$code = (int) ( $r->get_error_data()['code'] ?? 0 );
		if ( in_array( $code, [ 10, 200, 190 ], true ) ) { set_transient( 'ir_px_bd_off', 1, 20 * MINUTE_IN_SECONDS ); return new WP_Error( 'bd_off', $r->get_error_message() ); }
		return new WP_Error( 'ig', 'Instagram did not share @' . $h . ' (it may be a personal account, private, or the handle has a typo).' );
	}
	$u = (array) ( $r['business_discovery'] ?? [] );
	if ( ! $u ) { return new WP_Error( 'ig', 'Instagram did not share @' . $h . '.' ); }
	$posts = [];
	foreach ( array_slice( (array) ( $u['media']['data'] ?? [] ), 0, 12 ) as $p ) {
		$ty = strtoupper( (string) ( $p['media_type'] ?? '' ) );
		$posts[] = [
			'ts' => strtotime( (string) ( $p['timestamp'] ?? '' ) ) ?: 0,
			'kind' => $ty === 'VIDEO' || ( $p['media_product_type'] ?? '' ) === 'REELS' ? 'video' : ( $ty === 'CAROUSEL_ALBUM' ? 'carousel' : 'photo' ),
			'likes' => (int) ( $p['like_count'] ?? 0 ), 'comments' => (int) ( $p['comments_count'] ?? 0 ), 'hidden' => ! isset( $p['like_count'] ),
			'cap' => trim( preg_replace( '/\s+/', ' ', (string) ( $p['caption'] ?? '' ) ) ), 'pinned' => false, 'img' => (string) ( $p['thumbnail_url'] ?? ( $p['media_url'] ?? '' ) ),
		];
	}
	$fol = (int) ( $u['followers_count'] ?? 0 ); $now = time(); $seen = count( $posts );
	$ts = array_filter( wp_list_pluck( $posts, 'ts' ) );
	$last = $ts ? max( $ts ) : 0;
	$counted = array_values( array_filter( $posts, function ( $p ) { return ! $p['hidden']; } ) );
	$st = [
		'followers' => $fol, 'posts' => (int) ( $u['media_count'] ?? 0 ), 'seen' => $seen,
		'days' => $last ? (int) floor( ( $now - $last ) / DAY_IN_SECONDS ) : -1, 'last' => $last ? gmdate( 'j M Y', $last ) : '',
		'n30' => count( array_filter( $posts, function ( $p ) use ( $now ) { return $p['ts'] > $now - 30 * DAY_IN_SECONDS; } ) ),
		'videos' => count( array_filter( $posts, function ( $p ) { return $p['kind'] === 'video'; } ) ),
		'cta' => count( array_filter( $posts, function ( $p ) { return (bool) preg_match( '/\bbook|booking|link in (my |our |the )?bio|\bdm\b|dm (me|us)|send (me|us) a|message (me|us)|whatsapp|call (me|us)|appointment|tap the link|order now|slots?\b|available|click the link/i', $p['cap'] ); } ) ),
		'nocap' => count( array_filter( $posts, function ( $p ) { return mb_strlen( trim( preg_replace( '/#[\p{L}\p{N}_]+|[^\p{L}\p{N}\s]/u', ' ', $p['cap'] ) ) ) < 20; } ) ),
		'avg' => -1,
	];
	if ( count( $counted ) >= 3 ) { $e = array_map( function ( $p ) { return $p['likes'] + $p['comments']; }, $counted ); sort( $e ); $m = count( $e ); $st['avg'] = (int) round( $m % 2 ? $e[ ( $m - 1 ) / 2 ] : ( $e[ $m / 2 - 1 ] + $e[ $m / 2 ] ) / 2 ); }
	$bio = trim( (string) ( $u['biography'] ?? '' ) );
	$site = trim( (string) ( $u['website'] ?? '' ) ); $wa = '';
	if ( preg_match( '#(?:wa\.me/|whatsapp\.com/send\?phone=)(\d{8,15})#i', $site . ' ' . $bio, $m ) ) { $wa = $m[1]; }
	preg_match( '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $bio, $bm );
	$email = strtolower( $bm[0] ?? '' ); $phone = '';
	if ( $phone === '' && preg_match( '/(?:\+?\d[\d\s-]{8,15}\d)/', $bio, $pm ) ) { $phone = trim( $pm[0] ); }
	$read = $site !== '' && ! preg_match( '#wa\.me|whatsapp|instagram\.com|facebook\.com|tiktok\.com#i', $site ) ? $site : '';
	if ( $read !== '' && function_exists( 'ir_cf_find' ) ) {
		$cf = (array) ir_cf_find( $read );
		if ( $email === '' && ! empty( $cf['email'] ) ) { $email = (string) $cf['email']; }
		if ( $phone === '' && ! empty( $cf['phone'] ) ) { $phone = (string) $cf['phone']; }
		if ( $wa === '' && ! empty( $cf['whatsapp'] ) ) { $wa = (string) $cf['whatsapp']; }
	}
	$name = trim( (string) ( $u['name'] ?? '' ) );
	$lines = [];
	foreach ( $posts as $i => $p ) {
		$lines[] = 'Post ' . ( $i + 1 ) . ' (' . ( $p['ts'] ? gmdate( 'j M Y', $p['ts'] ) : 'date unknown' ) . ', ' . $p['kind'] . ( $p['hidden'] ? ', likes hidden' : ', ' . $p['likes'] . ' likes' ) . ', ' . $p['comments'] . ' comments): ' . ( $p['cap'] !== '' ? mb_substr( $p['cap'], 0, 260 ) : '(no caption)' );
	}
	$facts = 'Instagram profile @' . $h . "\nName on the profile: " . $name
		. "\nBusiness name (clean): " . ir_px_clean_biz( $name ?: $h )
		. "\nBio: " . ( $bio !== '' ? str_replace( "\n", ' / ', $bio ) : '(empty)' )
		. "\nLink in bio: " . ( $site !== '' ? $site : 'none' )
		. "\nProfile picture: " . ( ! empty( $u['profile_picture_url'] ) ? 'yes' : 'none' )
		. "\nFollowers: " . $fol . ' · Following: ' . (int) ( $u['follows_count'] ?? 0 ) . ' · Total posts: ' . $st['posts']
		. "\nNewest post: " . ( $last ? $st['last'] . ' (' . $st['days'] . ' days ago)' : 'none visible' )
		. "\nOf the " . $seen . ' latest posts: ' . $st['n30'] . ' were posted in the last 30 days, ' . $st['videos'] . ' are Reels or videos, ' . $st['cta'] . ' have a caption that tells people how to book, DM or click, ' . $st['nocap'] . ' have little or no caption.'
		. ( $fol && $st['avg'] >= 0 ? "\nA typical post gets " . $st['avg'] . ' likes plus comments (' . round( $st['avg'] / max( 1, $fol ) * 100, 2 ) . '% of followers)' : '' )
		. ( $email !== '' ? "\nEmail: " . $email : '' ) . ( $wa !== '' ? "\nWhatsApp: +" . $wa : '' ) . ( $phone !== '' ? "\nPhone: " . $phone : '' )
		. "\nThe " . $seen . " latest posts:\n" . ( $lines ? implode( "\n", $lines ) : '(none visible)' );
	$out = [ 'facts' => $facts, 'contact' => array_filter( [ 'instagram' => $h, 'email' => $email, 'whatsapp' => $wa, 'phone' => $phone, 'website' => $read ] ), 'name' => $name, 'stats' => $st,
		'caps' => implode( "\n", wp_list_pluck( $posts, 'cap' ) ), 'imgs' => array_values( array_filter( wp_list_pluck( array_slice( $posts, 0, 6 ), 'img' ) ) ), 'bio' => true ];
	set_transient( $ck, $out, 12 * HOUR_IN_SECONDS );
	return $out;
}

/** "ENHANCE AESTHETIC HQ | COSMETIC NURSE" -> "Enhance Aesthetic HQ". */
function ir_px_clean_biz( $s ) {
	$s = trim( (string) preg_split( '/\s+[|•·–—-]\s+|\s*\|\s*/u', (string) $s )[0] );
	$s = trim( preg_replace( '/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}]/u', '', $s ) );
	if ( $s !== '' && $s === mb_strtoupper( $s ) && preg_match( '/[A-Z]{4,}/', $s ) ) {
		$s = implode( ' ', array_map( function ( $w ) { return mb_strlen( $w ) <= 3 && preg_match( '/^[A-Z]+$/', $w ) && ! in_array( $w, [ 'THE', 'AND', 'FOR', 'BY', 'OF' ], true ) ? $w : mb_convert_case( mb_strtolower( $w ), MB_CASE_TITLE ); }, preg_split( '/\s+/', $s ) ) );
	}
	return $s;
}

/* ---------- Google Business: free, from Google Maps ---------- */

/** Follows share.google / maps.app.goo.gl / g.page links to the business name, Google's id for it, the country and map position. */
function ir_px_g_resolve( $url ) {
	$ua = [ 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'Accept-Language' => 'en-GB,en;q=0.9' ];
	$u = $url; $all = $url;
	for ( $i = 0; $i < 6; $i++ ) {
		$r = wp_remote_get( $u, [ 'redirection' => 0, 'timeout' => 10, 'headers' => $ua ] );
		if ( is_wp_error( $r ) ) { break; }
		$loc = (string) wp_remote_retrieve_header( $r, 'location' );
		if ( $loc === '' ) { break; }
		$u = strpos( $loc, 'http' ) === 0 ? $loc : 'https://www.google.com' . $loc;
		$all .= ' ' . $u;
		if ( strpos( $u, 'share.google/error' ) !== false ) { return new WP_Error( 'google', 'This Google link does not open (Google says it is not available). Copy the link again from Google, or paste their website.' ); }
	}
	$q = []; parse_str( (string) wp_parse_url( $u, PHP_URL_QUERY ), $q );
	$name = (string) ( $q['q'] ?? ( $q['query'] ?? '' ) );
	if ( preg_match( '#/maps/place/([^/@?]+)#', $all, $m ) ) { $name = str_replace( '+', ' ', rawurldecode( $m[1] ) ); }
	$ll = preg_match( '#@(-?\d+\.\d+),(-?\d+\.\d+)#', $all, $m ) ? [ (float) $m[1], (float) $m[2] ] : null;
	$cc = '';
	if ( preg_match( '/[?&]hl=[a-z]{2}-([a-z]{2})/i', $all, $m ) ) { $cc = strtolower( $m[1] ); } elseif ( ! empty( $q['gl'] ) ) { $cc = strtolower( (string) $q['gl'] ); }
	return [ 'name' => trim( $name ), 'kgmid' => (string) ( $q['kgmid'] ?? '' ), 'cc' => $cc, 'll' => $ll, 'final' => $u ];
}

/** Read the listing from Google Maps' own search data. */
function ir_px_g_maps( $name, $cc, $ll, $kgmid = '' ) {
	$view = [ 'ng' => [ 9.07, 7.4, 6 ], 'gb' => [ 54, -2.5, 6 ], 'uk' => [ 54, -2.5, 6 ], 'us' => [ 39, -98, 4 ], 'gh' => [ 7.9, -1.0, 7 ], 'ke' => [ 0.2, 37.9, 6 ], 'za' => [ -29, 24.5, 5 ], 'ca' => [ 56, -100, 4 ], 'ae' => [ 24.3, 54.4, 7 ], 'au' => [ -25, 134, 4 ], 'ie' => [ 53.4, -8, 7 ], 'in' => [ 22, 79, 5 ] ];
	$v = $ll ? [ $ll[0], $ll[1], 14 ] : ( $view[ $cc ] ?? [ 9.07, 7.4, 6 ] );
	$ua = [ 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'Accept-Language' => 'en-GB,en;q=0.9' ];
	$r = wp_remote_get( 'https://www.google.com/maps/search/' . rawurlencode( $name ) . '/@' . $v[0] . ',' . $v[1] . ',' . $v[2] . 'z?hl=en' . ( $cc ? '&gl=' . $cc : '' ), [ 'timeout' => 15, 'headers' => $ua ] );
	if ( is_wp_error( $r ) || ! preg_match( '#(/search\?tbm=map[^"]+)"#', (string) wp_remote_retrieve_body( $r ), $m ) ) { return null; }
	$r2 = wp_remote_get( 'https://www.google.com' . html_entity_decode( $m[1] ), [ 'timeout' => 15, 'headers' => $ua ] );
	if ( is_wp_error( $r2 ) ) { return null; }
	$d = json_decode( preg_replace( '/^\)\]\}\'\s*/', '', (string) wp_remote_retrieve_body( $r2 ) ), true );
	if ( ! is_array( $d ) ) { return null; }
	$cands = [];
	foreach ( (array) ( $d[0][1] ?? [] ) as $x ) { if ( isset( $x[14][11] ) ) { $cands[] = $x[14]; } }
	if ( ! $cands && isset( $d[6][11] ) ) { $cands[] = $d[6]; }
	if ( ! $cands ) { return null; }
	$p = $cands[0];
	foreach ( $cands as $x ) { if ( $kgmid !== '' && ( $x[89] ?? '' ) === $kgmid ) { $p = $x; break; } if ( strcasecmp( (string) $x[11], $name ) === 0 ) { $p = $x; } }
	$hours = [];
	foreach ( (array) ( $p[203][0] ?? [] ) as $dd ) { if ( isset( $dd[0] ) ) { $hours[] = $dd[0] . ' ' . str_replace( [ "\u{202f}", "\u{2013}" ], [ ' ', ' to ' ], (string) ( $dd[3][0][0] ?? 'Closed' ) ); } }
	return [
		'name' => (string) $p[11], 'address' => (string) ( $p[39] ?? '' ), 'phone' => (string) ( $p[178][0][1][1][0] ?? ( $p[178][0][0] ?? '' ) ),
		'website' => preg_replace( '/\?utm_.*$/', '', (string) ( $p[7][0] ?? '' ) ), 'rating' => $p[4][7] ?? null, 'reviews' => (int) ( $p[4][8] ?? 0 ),
		'cats' => array_values( array_filter( (array) ( $p[13] ?? [] ), 'is_string' ) ), 'hours' => implode( ', ', $hours ), 'kgmid' => (string) ( $p[89] ?? '' ),
	];
}

function ir_px_google( $url ) {
	$ck = 'ir_px_g_' . md5( $url );
	$c = get_transient( $ck );
	if ( is_array( $c ) ) { return $c; }
	$g = ir_px_g_resolve( $url );
	if ( is_wp_error( $g ) ) { return $g; }
	if ( $g['name'] === '' ) { return new WP_Error( 'google', 'We could not tell which business this Google link is for. Paste their website link, or add a screenshot of their Google listing.' ); }
	$p = ir_px_g_maps( $g['name'], $g['cc'], $g['ll'], $g['kgmid'] );
	if ( ! $p ) { return [ 'facts' => "Google Business listing\nName: " . $g['name'], 'contact' => [], 'name' => $g['name'], 'phone' => '', 'phones' => [], 'thin' => true ]; }
	$site = $p['website']; $phone = $p['phone']; $email = '';
	if ( $site !== '' && function_exists( 'ir_cf_find' ) ) { $cf = (array) ir_cf_find( $site ); $email = (string) ( $cf['email'] ?? '' ); if ( $phone === '' ) { $phone = (string) ( $cf['phone'] ?? '' ); } }
	$facts = "Google Business listing\nName: " . $p['name'] . "\nCategory: " . implode( ', ', array_slice( $p['cats'], 0, 4 ) )
		. "\nAddress: " . ( $p['address'] ?: 'not shown' ) . "\nPhone: " . ( $phone ?: 'not shown' ) . "\nWebsite: " . ( $site ?: 'none on the listing' )
		. ( $email !== '' ? "\nEmail (from their website): " . $email : '' )
		. "\nRating: " . ( $p['rating'] !== null ? $p['rating'] . ' stars from ' . $p['reviews'] . ' reviews' : 'no rating yet' )
		. "\nOpening hours: " . ( $p['hours'] ?: 'not shown' );
	$out = [ 'facts' => $facts, 'contact' => array_filter( [ 'phone' => $phone, 'email' => $email, 'website' => $site ] ), 'name' => $p['name'], 'phone' => $phone, 'phones' => $phone ? [ $phone ] : [], 'email' => $email, 'website' => $site ];
	set_transient( $ck, $out, DAY_IN_SECONDS );
	return $out;
}

/* ---------- rewrite drafted outreach so it sounds human and is about them ---------- */

add_action( 'wp_ajax_ir_ct_polish', function () {
	check_ajax_referer( 'ir_tool', 'n' );
	if ( ! ( function_exists( 'ir_pb_has_access' ) && ir_pb_has_access() ) ) { wp_send_json( [ 'ok' => false, 'err' => 'login' ] ); }
	if ( ! function_exists( 'ir_ai_text' ) ) { wp_send_json( [ 'ok' => false, 'err' => 'noai' ] ); }
	$g = function ( $k, $max ) { return mb_substr( trim( wp_unslash( (string) ( $_POST[ $k ] ?? '' ) ) ), 0, $max ); };
	$ch = $g( 'ch', 10 ) === 'email' ? 'email' : 'dm';
	$draft = json_decode( $g( 'draft', 8000 ), true );
	if ( ! is_array( $draft ) ) { wp_send_json( [ 'ok' => false, 'err' => 'draft' ] ); }
	$sys = 'You rewrite cold outreach for a freelancer so it sounds like a real, warm person and gets a reply. You keep the structure (same number of messages, same order and the same job for each message) but fix the wording.' . "\n\n"
		. "RULES:\n"
		. "- Keep the greeting at the very start exactly as written (for example \"Good afternoon @handle,\"). Do not add another greeting.\n"
		. "- The compliment must be specific and genuine, built from the strongest real detail in the prospect facts: first their bio or listing (what they specialise in, where they trained, years of experience, awards, rating and number of reviews, a happy client review), then a specific post. Ignore the suggested compliment ideas if they are generic. Make it sound like a person who really looked, for example \"Love that you trained in the UK, South Korea and South Africa, you can tell from how confidently you talk about PRP.\" Never generic praise like \"I like your page\", \"your reels showing your work up close\" or \"your content is great\". One sentence.\n"
		. "- Emails greet without @handles: keep the draft's greeting (\"Good afternoon,\" or \"Hi Name,\").\n"
		. "- The sentence about the freelancer must read naturally, for example \"I build websites for aesthetic clinics that book clients and answer their questions automatically.\" Never put the prospect's business name inside the description of the freelancer's general service.\n"
		. "- Everything is about the prospect: write to them with you and your. Never call the prospect them, their, these clients or esthetician in the third person.\n"
		. "- Rewrite the freelancer's description in plain words as one short sentence about what it does FOR THE PROSPECT. Fix grammar. If the freelancer says they already built or made something for prospects (for example a website), say they built it for this prospect, using the business name: \"I've already built a website for Enhance Aesthetic HQ\".\n"
		. "- Never ask for their WhatsApp, phone number or a call, and never say it will make more sense on a call.\n"
		. "- End the last message with one short, easy question they can answer with yes, that makes them curious (for example \"Want me to send it over?\" or \"Would it be okay if I sent you the link?\"). Only one question in the whole draft, and it is in the last message; earlier messages end with a full stop.\n"
		. "- Use the business name in normal case, without taglines after | or - and never in ALL CAPS (\"Enhance Aesthetic HQ\", not \"ENHANCE AESTHETIC HQ | COSMETIC NURSE\").\n"
		. "- Use only facts given. Never invent numbers, results, clients or details. Keep the observation and what it costs them, in kind words.\n"
		. ( $ch === 'dm' ? "- DMs are short: each message under 45 words. No sign off.\n" : "- Email body under 130 words, short paragraphs, keep the sign off line with the freelancer's name if there is one. Subject: 2 to 6 words, lowercase, no name in capitals.\n" )
		. "- Plain English. No em dashes or en dashes, no hashtags, at most one emoji in total.\n\n"
		. ( $ch === 'dm' ? 'Reply with JSON only: {"msgs":["message 1","message 2",...]} with exactly the same number of messages as the draft.' : 'Reply with JSON only: {"subject":"...","body":"..."}' );
	$user = "The freelancer:\n" . $g( 'me', 1500 ) . "\n\nThe prospect facts:\n" . $g( 'facts', 6000 ) . "\n\nThe draft to rewrite:\n" . wp_json_encode( $draft, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	$r = ir_ai_text( $sys, $user, [ 'json' => 1, 'temp' => 0.6, 'max' => 2000, 'think' => 0, 'timeout' => 40, 'strict' => 1 ] );
	if ( is_wp_error( $r ) ) { wp_send_json( [ 'ok' => false, 'err' => 'ai' ] ); }
	$j = function_exists( 'ir_ai_json' ) ? ir_ai_json( is_array( $r ) ? ( $r['text'] ?? '' ) : $r ) : json_decode( (string) $r, true );
	$fix = function ( $s ) { return trim( preg_replace( [ '/\s*[\x{2014}\x{2013}]\s*/u' ], [ ', ' ], (string) $s ) ); };
	if ( $ch === 'dm' ) {
		$m = array_values( array_filter( array_map( $fix, (array) ( $j['msgs'] ?? [] ) ) ) );
		if ( ! $m || ( ! empty( $draft['msgs'] ) && count( $m ) !== count( (array) $draft['msgs'] ) ) ) { wp_send_json( [ 'ok' => false, 'err' => 'shape' ] ); }
		wp_send_json( [ 'ok' => true, 'msgs' => $m ] );
	}
	if ( empty( $j['body'] ) ) { wp_send_json( [ 'ok' => false, 'err' => 'shape' ] ); }
	wp_send_json( [ 'ok' => true, 'subject' => $fix( $j['subject'] ?? '' ), 'body' => $fix( $j['body'] ) ] );
} );
