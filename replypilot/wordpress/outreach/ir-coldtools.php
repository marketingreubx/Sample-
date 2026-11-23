<?php
/**
 * Plugin Name: IR Cold DM tools
 * Description: Prospect analyser and reply handler for the Smart Writer (AI jobs through ir-ai.php), plus morning email reminders for follow-ups and goals.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------- reading a prospect's page ---------- */

function ir_ct_meta( $html, $prop ) {
	if ( preg_match( '/<meta[^>]+(?:property|name)=["\']' . preg_quote( $prop, '/' ) . '["\'][^>]*content=["\']([^"\']*)/i', $html, $m ) ) { return trim( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) ); }
	if ( preg_match( '/<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']' . preg_quote( $prop, '/' ) . '["\']/i', $html, $m ) ) { return trim( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) ); }
	return '';
}

function ir_ct_kind( $url ) {
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	if ( preg_match( '/(^|\.)(instagram\.com|instagr\.am)$/', $host ) ) { return 'instagram'; }
	if ( preg_match( '/(^|\.)(google\.[a-z.]+|goo\.gl|g\.page|maps\.app\.goo\.gl|share\.google)$/', $host ) ) { return 'google'; }
	if ( preg_match( '/(^|\.)(tiktok\.com|facebook\.com|fb\.com|linkedin\.com|x\.com|twitter\.com|threads\.net|youtube\.com)$/', $host ) ) { return 'social'; }
	return 'website';
}

/** What a visitor sees on a website, as plain facts for the AI. */
function ir_ct_site( $url ) {
	$t0 = microtime( true );
	$r = wp_remote_get( $url, [ 'timeout' => 20, 'redirection' => 5, 'limit_response_size' => 3 * MB_IN_BYTES, 'headers' => [ 'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', 'Accept-Language' => 'en-GB,en;q=0.9' ] ] );
	$sec = round( microtime( true ) - $t0, 1 );
	$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
	$b = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
	/* Sites that block automated visits (Cloudflare and similar): read them through a page reader instead. */
	$rdr = false;
	if ( ( $code >= 400 || strlen( $b ) < 200 ) && function_exists( 'ir_cf_reader' ) ) { $rb = ir_cf_reader( $url ); if ( $rb !== '' ) { $b = $rb; $code = 200; $rdr = true; } }
	if ( is_wp_error( $r ) && $b === '' ) { return new WP_Error( 'fetch', 'This website did not open: ' . $r->get_error_message() ); }
	if ( $code >= 400 || strlen( $b ) < 200 ) { return new WP_Error( 'fetch', 'This website did not open (error ' . $code . '). Check the link, or add a screenshot instead.' ); }
	$low = strtolower( $b );
	$body = preg_replace( '#<(script|style|noscript|svg)[^>]*>.*?</\1>#is', ' ', $b );
	$heads = [];
	if ( preg_match_all( '#<h([1-3])[^>]*>(.*?)</h\1>#is', $body, $m, PREG_SET_ORDER ) ) {
		foreach ( array_slice( $m, 0, 12 ) as $x ) { $t = trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $x[2] ), ENT_QUOTES, 'UTF-8' ) ) ); if ( $t !== '' ) { $heads[] = 'H' . $x[1] . ': ' . mb_substr( $t, 0, 120 ); } }
	}
	$links = [];
	if ( preg_match_all( '#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', $body, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $x ) { $t = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $x[2] ) ) ); $links[] = [ $x[1], mb_substr( $t, 0, 40 ) ]; }
	}
	$hrefs = strtolower( implode( ' ', wp_list_pluck( $links, 0 ) ) );
	$has = function ( $re ) use ( $low ) { return (bool) preg_match( $re, $low ); };
	preg_match_all( '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', html_entity_decode( $b ), $em );
	$emails = array_values( array_unique( array_filter( array_map( 'strtolower', $em[0] ), function ( $e ) { return ! preg_match( '/\.(png|jpe?g|gif|webp|svg)$|sentry|example\.|wixpress|@2x/', $e ); } ) ) );
	preg_match( '#(?:wa\.me/|api\.whatsapp\.com/send\?phone=)(\d{8,15})#i', $b, $wa );
	preg_match( '#instagram\.com/([a-z0-9._]{2,30})#i', $b, $ig );
	$imgs = preg_match_all( '#<img\b#i', $b );
	$noalt = preg_match_all( '#<img\b(?![^>]*\balt=["\'][^"\']+)[^>]*>#i', $b );
	$text = trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $body ), ENT_QUOTES, 'UTF-8' ) ) );
	$platform = strpos( $low, 'cdn.shopify' ) !== false ? 'Shopify' : ( strpos( $low, 'wp-content' ) !== false ? 'WordPress' : ( strpos( $low, 'wix.com' ) !== false || strpos( $low, 'wixstatic' ) !== false ? 'Wix' : ( strpos( $low, 'squarespace' ) !== false ? 'Squarespace' : ( strpos( $low, 'webflow' ) !== false ? 'Webflow' : 'unknown' ) ) ) );
	/* The newest year in any copyright line ("2019 - 2026" means 2026). */
	$cy = null; $dec = html_entity_decode( $b, ENT_QUOTES, 'UTF-8' );
	if ( preg_match_all( '/(?:©|\(c\)|copyright)[^<\n]{0,40}?((?:19|20)\d{2})(?:\s*[-–—\/]\s*((?:19|20)\d{2}))?/iu', $dec, $cm, PREG_SET_ORDER ) ) { $yy = 0; foreach ( $cm as $q ) { $yy = max( $yy, (int) $q[1], (int) ( $q[2] ?? 0 ) ); } if ( $yy ) { $cy = [ 0, (string) $yy ]; } }
	if ( preg_match( '/new Date\(\)\.getFullYear|date\(.Y.\)|\[year\]|current_year/i', $b ) ) { $cy = null; }
	$f = [
		'Read through a page reader' => $rdr ? 'yes (the site blocks automated visits, so its head tags may be missing from our copy)' : 'no',
		'Address'                 => (string) wp_remote_retrieve_header( $r, 'content-location' ) ?: $url,
		'Page title'              => preg_match( '#<title[^>]*>(.*?)</title>#is', $b, $tm ) ? trim( html_entity_decode( $tm[1], ENT_QUOTES, 'UTF-8' ) ) : '(none)',
		'Meta description'        => ir_ct_meta( $b, 'description' ) ?: '(none)',
		'Secure (https)'          => strpos( $url, 'https://' ) === 0 ? 'yes' : 'no',
		'Load time'               => $sec . ' seconds for the HTML on a fast server connection',
		'Page weight'             => round( strlen( $b ) / 1024 ) . ' KB of HTML',
		'Built with'              => $platform,
		'Mobile friendly tag'     => $has( '/<meta[^>]+name=["\']viewport/' ) ? 'yes' : 'no (likely not mobile friendly)',
		'Online booking link'     => preg_match( '/calendly|acuityscheduling|booksy|fresha|vagaro|setmore|squareup\.com\/appointments|simplybook|book now|book an appointment|book online|schedule/', $hrefs . ' ' . $low ) ? 'yes' : 'no booking link found',
		'WhatsApp button'         => $wa ? 'yes (' . $wa[1] . ')' : ( $has( '/wa\.me|whatsapp/' ) ? 'mentioned' : 'no' ),
		'Contact form'            => preg_match_all( '#<form\b#i', $b ) ? 'yes' : 'no form found',
		'Phone link'              => $has( '/href=["\']tel:/' ) ? 'yes' : 'no',
		'Reviews or testimonials' => $has( '/testimonial|review|what (our|my) clients say|★|stars?\b/' ) ? 'some found' : 'none found',
		'Prices shown'            => ( $has( '/(₦|\$|£|€|ngn|usd)\s?\d|woocommerce-price-amount|class=["\'][^"\']*\bprice\b|&#8358;|&#x20a6;/' ) || preg_match( '/(₦|\$|£|€|\bngn|\busd|\bn)\s?\d{1,3}(,\d{3})+|(₦|\$|£|€)\s?\d+|\b\d+(\.\d+)?\s?k\b.{0,12}(naira|ngn)|\bprices?\s*(from|start|:)/iu', $text ) ) ? 'yes' : 'no',
		'Blog'                    => $has( '/\/blog|\/news|\/articles/' ) ? 'yes' : 'no',
		'Social links'            => implode( ', ', array_filter( [ $has( '/instagram\.com/' ) ? 'Instagram' : '', $has( '/facebook\.com/' ) ? 'Facebook' : '', $has( '/tiktok\.com/' ) ? 'TikTok' : '', $has( '/linkedin\.com/' ) ? 'LinkedIn' : '', $has( '/youtube\.com/' ) ? 'YouTube' : '' ] ) ) ?: 'none',
		'Meta (Facebook) pixel'   => $has( '/fbq\(|connect\.facebook\.net/' ) ? 'yes' : 'no',
		'Google Analytics'        => $has( '/gtag\(|googletagmanager|google-analytics/' ) ? 'yes' : 'no',
		'Email signup'            => $has( '/newsletter|subscribe|mailchimp|klaviyo|convertkit/' ) ? 'yes' : 'no',
		'Images'                  => $imgs . ' images, ' . $noalt . ' without alt text',
		'Copyright year'          => $cy ? $cy[1] : 'not shown',
		'Headings'                => $heads ? implode( ' | ', $heads ) : '(no headings)',
		'Menu and button links'   => implode( ' | ', array_slice( array_unique( array_filter( wp_list_pluck( $links, 1 ) ) ), 0, 30 ) ),
	];
	$facts = '';
	foreach ( $f as $k => $v ) { $facts .= $k . ': ' . $v . "\n"; }
	$facts .= "\nVisible text (start of the page):\n" . mb_substr( $text, 0, 3500 );
	return [ 'facts' => $facts, 'contact' => [ 'email' => $emails[0] ?? '', 'whatsapp' => $wa[1] ?? '', 'instagram' => isset( $ig[1] ) && ! in_array( strtolower( $ig[1] ), [ 'p', 'reel', 'explore', 'accounts' ], true ) ? $ig[1] : '', 'website' => $url ], 'name' => ir_ct_meta( $b, 'og:site_name' ) ];
}

/** Instagram profile facts, when Instagram allows it (it often blocks servers). */
function ir_ct_instagram( $url ) {
	$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
	$h = strtolower( explode( '/', $path )[0] ?? '' );
	if ( in_array( $h, [ 'p', 'reel', 'reels', 'tv' ], true ) ) {
		$p = function_exists( 'ir_tl_fetch_post' ) ? ir_tl_fetch_post( $url ) : null;
		if ( $p && ! is_wp_error( $p ) && $p['text'] !== '' ) { return [ 'facts' => 'One of their Instagram posts' . ( $p['author'] ? ' by @' . $p['author'] : '' ) . ":\n" . mb_substr( $p['text'], 0, 1500 ), 'contact' => [ 'instagram' => $p['author'] ], 'name' => '', 'image' => $p['image'] ]; }
		return new WP_Error( 'ig', 'Instagram did not let us read this post. Add a screenshot of their profile instead.' );
	}
	if ( ! preg_match( '/^[a-z0-9._]{2,30}$/', $h ) ) { return new WP_Error( 'ig', 'That does not look like an Instagram profile link.' ); }
	if ( function_exists( 'ir_px_ig' ) && ir_px_token() !== '' ) { $px = ir_px_ig( $h ); if ( ! is_wp_error( $px ) ) { return $px; } }
	$r = wp_remote_get( 'https://i.instagram.com/api/v1/users/web_profile_info/?username=' . $h, [ 'timeout' => 15, 'headers' => [ 'x-ig-app-id' => '936619743392459', 'User-Agent' => 'Instagram 312.0.0.32.112 Android (33/13; 420dpi; 1080x2400; samsung; SM-G991B; o1s; exynos2100; en_GB; 548323757)' ] ] );
	$j = is_wp_error( $r ) ? null : json_decode( wp_remote_retrieve_body( $r ), true );
	$u = $j['data']['user'] ?? null;
	if ( ! $u && function_exists( 'ir_ct_ig_embed' ) ) { $e2 = ir_ct_ig_embed( $h ); if ( ! is_wp_error( $e2 ) ) { return $e2; } }
	if ( ! $u ) { return new WP_Error( 'ig_blocked', 'Instagram did not let us read @' . $h . ' right now. Add 1 or 2 screenshots of their profile and we will read those instead.', [ 'handle' => $h ] ); }
	$posts = (array) ( $u['edge_owner_to_timeline_media']['edges'] ?? [] );
	$fol = (int) ( $u['edge_followed_by']['count'] ?? 0 );
	$lines = []; $eng = []; $last = 0;
	foreach ( array_slice( $posts, 0, 12 ) as $e ) {
		$n = $e['node']; $ts = (int) ( $n['taken_at_timestamp'] ?? 0 ); $last = max( $last, $ts );
		$lk = (int) ( $n['edge_liked_by']['count'] ?? $n['edge_media_preview_like']['count'] ?? 0 ); $cm = (int) ( $n['edge_media_to_comment']['count'] ?? 0 );
		if ( $fol ) { $eng[] = ( $lk + $cm ) / $fol * 100; }
		$cap = (string) ( $n['edge_media_to_caption']['edges'][0]['node']['text'] ?? '' );
		$lines[] = '- ' . gmdate( 'j M Y', $ts ) . ', ' . ( ! empty( $n['is_video'] ) ? 'video' : 'photo' ) . ', ' . $lk . ' likes, ' . $cm . ' comments: ' . mb_substr( preg_replace( '/\s+/', ' ', $cap ), 0, 160 );
	}
	$facts = "Instagram profile @" . $h . "\nName: " . ( $u['full_name'] ?? '' ) . "\nBio: " . ( $u['biography'] ?? '' ) . "\nLink in bio: " . ( $u['external_url'] ?: '(none)' )
		. "\nCategory: " . ( $u['category_name'] ?? '' ) . "\nBusiness account: " . ( ! empty( $u['is_business_account'] ) ? 'yes' : 'no' )
		. "\nFollowers: " . $fol . "\nFollowing: " . (int) ( $u['edge_follow']['count'] ?? 0 ) . "\nPosts: " . (int) ( $u['edge_owner_to_timeline_media']['count'] ?? 0 )
		. "\nHighlights: " . (int) ( $u['highlight_reel_count'] ?? 0 )
		. "\nLast post: " . ( $last ? gmdate( 'j M Y', $last ) . ' (' . floor( ( time() - $last ) / DAY_IN_SECONDS ) . ' days ago)' : 'none' )
		. ( $eng ? "\nAverage engagement on recent posts: " . round( array_sum( $eng ) / count( $eng ), 2 ) . '% of followers' : '' )
		. "\nRecent posts:\n" . implode( "\n", $lines );
	return [ 'facts' => $facts, 'contact' => [ 'instagram' => $h, 'website' => (string) ( $u['external_url'] ?? '' ), 'email' => (string) ( $u['business_email'] ?? '' ) ], 'name' => (string) ( $u['full_name'] ?? '' ) ];
}

function ir_ct_google( $url ) {
	if ( function_exists( 'ir_px_google' ) ) { $px = ir_px_google( $url ); if ( ! is_wp_error( $px ) ) { return $px; } if ( $px->get_error_code() === 'google' ) { return $px; } }
	$r = wp_remote_get( $url, [ 'timeout' => 20, 'redirection' => 6, 'headers' => [ 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36', 'Accept-Language' => 'en-GB,en;q=0.9' ] ] );
	$b = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
	$title = ir_ct_meta( $b, 'og:title' );
	$desc = ir_ct_meta( $b, 'og:description' );
	if ( $title === '' && $desc === '' ) { return new WP_Error( 'google', 'Google did not share this listing with us. Add a screenshot of their Google profile instead.' ); }
	return [ 'facts' => "Google Business listing\nName: " . $title . "\nDetails shown: " . $desc, 'contact' => [], 'name' => preg_replace( '/\s*[·\-|].*$/u', '', $title ), 'thin' => true ];
}

function ir_ct_approaches() {
	return "Cold DM approaches (key: name, when to use):\n"
		. "d-problem: Problem-Focused, a gap that costs them clients\nd-result: Result-Oriented, lead with a result for someone similar\nd-value: Value-Based, give something useful first\nd-audit: Compliment and Quick Audit, a real compliment then a free 3 point audit\nd-direct: The Direct Approach, short and clear for busy people\nd-soft: Soft Question Opener, a gentle question\nd-niche: Niche-Expertise Opener, you only work with people like them\n"
		. "Cold email approaches:\ne-problem: Problem-Focused\ne-result: Result-Oriented\ne-curious: Curiosity Question\ne-audit: Free Mini-Audit\ne-compliment: Compliment and Gap\ne-direct: Direct Offer\n";
}

function ir_ct_me( $in ) {
	$me = [];
	foreach ( [ 'job' => 'What I do', 'client' => 'Who I help', 'result' => 'The result I get them', 'service' => 'How I do it' ] as $k => $l ) {
		$v = sanitize_text_field( (string) ( $in[ 'me_' . $k ] ?? '' ) );
		if ( $v !== '' ) { $me[] = $l . ': ' . $v; }
	}
	return $me ? implode( "\n", $me ) : 'Not given. Assume a freelancer who helps businesses get more clients online.';
}

function ir_ct_scan_run( $in, $files ) {
	$url = trim( (string) ( $in['url'] ?? '' ) );
	if ( $url !== '' && ! preg_match( '#^https?://#i', $url ) ) {
		$handle = $url[0] === '@' || ( preg_match( '/^[a-z0-9._]{2,30}$/i', $url ) && ! preg_match( '/\.[a-z]{2,}$/i', $url ) );
		$url = $handle ? 'https://www.instagram.com/' . ltrim( $url, '@' ) . '/' : 'https://' . $url;
	}
	$url = esc_url_raw( $url );
	$shots = [];
	foreach ( $files as $f ) {
		if ( strpos( (string) $f['type'], 'image/' ) === 0 && count( $shots ) < 4 && strlen( $f['bytes'] ) < 8 * MB_IN_BYTES ) { $shots[] = [ 'inline_data' => [ 'mime_type' => $f['type'], 'data' => base64_encode( $f['bytes'] ) ] ]; }
	}
	if ( $url === '' && ! $shots ) { return new WP_Error( 'in', 'Paste their link or add a screenshot.' ); }
	if ( $shots && ! ir_ai_can_see() ) { return new WP_Error( 'nokey', 'Reading screenshots is being switched on. For now, paste their website link.' ); }
	$facts = ''; $contact = []; $name = ''; $kind = $url ? ir_ct_kind( $url ) : 'screenshot'; $note = '';
	if ( $url ) {
		ir_ai_step( 'Reading their page...' );
		$d = $kind === 'instagram' ? ir_ct_instagram( $url ) : ( $kind === 'google' ? ir_ct_google( $url ) : ir_ct_site( $url ) );
		if ( is_wp_error( $d ) ) {
			if ( ! $shots ) {
				return new WP_Error( 'need_shot', $d->get_error_message() );
			}
			$note = $d->get_error_message();
			if ( $kind === 'instagram' ) { $h = (array) $d->get_error_data(); $contact['instagram'] = $h['handle'] ?? ''; }
		} else {
			$facts = $d['facts']; $contact = array_filter( (array) $d['contact'] ); $name = (string) $d['name'];
			if ( ! empty( $d['image'] ) && function_exists( 'ir_tl_image_part' ) ) { $ip = ir_tl_image_part( $d['image'] ); if ( $ip ) { $shots[] = $ip; } }
		}
	}
	ir_ai_step( 'Finding what to mention...' );
	$ch = ( $in['ch'] ?? '' ) === 'email' ? 'email' : 'dm';
	$sys = "You help a freelancer write a cold " . ( $ch === 'email' ? 'email' : 'DM' ) . " that gets a reply. You study a prospect's online presence and find the specific, visible problems that cost them clients or sales, especially the ones the freelancer's service fixes.\n\n"
		. "Rules:\n- Only mention what you can actually see in the facts or screenshots. Quote or describe the exact thing. Never guess numbers or invent details.\n"
		. "- Put the problems the freelancer can fix first, most costly first. Give 3 to 5.\n"
		. "- Each problem has an \"obs\" that finishes the sentence \"I noticed your ...\". Write it to the owner, start with the thing itself, lowercase, 4 to 14 words, no full stop, no \"your\" at the start. Good: \"booking page asks for 9 details before anyone can book\". Good: \"last post was 47 days ago\".\n"
		. "- \"like\" finishes \"I really like your ...\": a sincere, specific compliment about something real, 3 to 10 words, lowercase, no full stop.\n"
		. "- Pick the best approach for a " . ( $ch === 'email' ? 'cold email' : 'cold DM' ) . " from the list, based on what you found.\n"
		. "- \"opener\" is one natural first line the freelancer could send, under 25 words, no em dashes.\n"
		. "- Plain, friendly English. No em dashes or en dashes.\n\n"
		. ir_ct_approaches() . "\n"
		. 'Reply with JSON only: {"name":"owner first name if clearly visible, else empty","business":"business or page name","platform":"Instagram, Website, Google or other","summary":"2 sentences: who they are and how they come across online","need":7,"issues":[{"title":"short title","detail":"what you saw and what it costs them, 1 or 2 sentences","obs":"...","fix":"what the freelancer would do, 1 sentence"}],"strengths":["..."],"like":"...","best":{"dm":"d-problem","email":"e-problem","why":"1 sentence"},"opener":"...","contact":{"email":"","whatsapp":"","instagram":""}}';
	$user = "The freelancer:\n" . ir_ct_me( $in ) . "\n\nThe prospect" . ( $url ? ' (' . $url . ')' : '' ) . ":\n" . ( $facts !== '' ? $facts : '(See the screenshots.)' ) . ( $note ? "\n\nNote: " . $note . ' Use the screenshots.' : '' ) . ( $shots ? "\n\nThe screenshots show their page as a visitor sees it. Read everything on them: bio, highlights, post grid, dates, numbers, reviews." : '' );
	$raw = ( ir_ai_key() !== '' ) ? ir_ai_gemini( $sys, array_merge( $shots, [ [ 'text' => $user ] ] ), [ 'json' => 1, 'temp' => 0.5, 'max' => 6000, 'think' => 1024 ] ) : ir_ai_free( $sys, $user . "\n\nReply with valid JSON only." );
	if ( is_wp_error( $raw ) ) { return new WP_Error( 'ai', 'The analysis did not finish (' . $raw->get_error_message() . '). Please try again.' ); }
	$j = ir_ai_json( $raw );
	if ( ! $j || empty( $j['issues'] ) ) { return new WP_Error( 'ai', 'The analysis did not finish. Please try again.' ); }
	$clean = function ( $s ) { return trim( preg_replace( [ '/\s*\x{2014}\s*/u', '/\s*\x{2013}\s*/u' ], [ ', ', ', ' ], (string) $s ) ); };
	$issues = [];
	foreach ( array_slice( (array) $j['issues'], 0, 5 ) as $i ) {
		$obs = preg_replace( '/^(i noticed\s+)?(your\s+)/i', '', rtrim( $clean( $i['obs'] ?? '' ), '. ' ) );
		$issues[] = [ 'title' => $clean( $i['title'] ?? '' ), 'detail' => $clean( $i['detail'] ?? '' ), 'obs' => $obs, 'fix' => $clean( $i['fix'] ?? '' ) ];
	}
	$c = array_merge( [ 'email' => '', 'whatsapp' => '', 'instagram' => '' ], array_map( 'strval', (array) ( $j['contact'] ?? [] ) ), $contact );
	$c['whatsapp'] = preg_replace( '/\D/', '', $c['whatsapp'] );
	$c['instagram'] = ltrim( preg_replace( '#^https?://(www\.)?instagram\.com/#i', '', trim( $c['instagram'], '/ ' ) ), '@' );
	$c['email'] = is_email( $c['email'] ) ? $c['email'] : '';
	$best = (array) ( $j['best'] ?? [] );
	return [
		'kind'      => $kind,
		'name'      => $clean( $j['name'] ?? '' ),
		'business'  => $clean( ( $j['business'] ?? '' ) ?: $name ),
		'summary'   => $clean( $j['summary'] ?? '' ),
		'need'      => max( 1, min( 10, (int) ( $j['need'] ?? 5 ) ) ),
		'issues'    => $issues,
		'strengths' => array_map( $clean, array_slice( (array) ( $j['strengths'] ?? [] ), 0, 3 ) ),
		'like'      => preg_replace( '/^(i really like\s+)?(your\s+)/i', '', rtrim( $clean( $j['like'] ?? '' ), '. ' ) ),
		'best'      => [ 'dm' => preg_match( '/^d-[a-z]+$/', (string) ( $best['dm'] ?? '' ) ) ? $best['dm'] : 'd-problem', 'email' => preg_match( '/^e-[a-z]+$/', (string) ( $best['email'] ?? '' ) ) ? $best['email'] : 'e-problem', 'why' => $clean( $best['why'] ?? '' ) ],
		'opener'    => $clean( $j['opener'] ?? '' ),
		'contact'   => $c,
	];
}

/* ---------- replies and objections ---------- */

/** The System's own reply scripts, as plain text for the AI. */
function ir_ct_scripts( $ch, $max ) {
	$k = 'ir_ct_scripts_' . $ch . '_' . $max;
	$c = get_transient( $k );
	if ( is_string( $c ) && $c !== '' ) { return $c; }
	$j = json_decode( (string) @file_get_contents( WP_CONTENT_DIR . '/ir-playbook/playbook.json' ), true );
	$want = $ch === 'email' ? [ 'email-replies' ] : [ 'objections', 'closing' ];
	$out = '';
	foreach ( (array) ( $j['sections'] ?? [] ) as $s ) {
		if ( ! in_array( $s['key'] ?? '', $want, true ) ) { continue; }
		foreach ( (array) ( $s['lessons'] ?? [] ) as $l ) {
			$t = html_entity_decode( wp_strip_all_tags( str_replace( [ '</p>', '<br>', '<br/>', '<br />', '</li>', '</h3>', '</h4>' ], "\n", (string) $l['html'] ) ), ENT_QUOTES, 'UTF-8' );
			$t = preg_replace( [ '/Copy script/i', "/[ \t]+/", "/\n{2,}/" ], [ '', ' ', "\n" ], $t );
			$out .= '### ' . $l['title'] . "\n" . trim( $t ) . "\n\n";
		}
	}
	$out = mb_substr( $out, 0, $max );
	set_transient( $k, $out, DAY_IN_SECONDS );
	return $out;
}

function ir_ct_reply_run( $in ) {
	$reply = trim( sanitize_textarea_field( (string) ( $in['reply'] ?? '' ) ) );
	if ( $reply === '' ) { return new WP_Error( 'in', 'Paste what they replied.' ); }
	$ch = ( $in['ch'] ?? '' ) === 'email' ? 'email' : 'dm';
	$ctx = sanitize_textarea_field( (string) ( $in['ctx'] ?? '' ) );
	$goal = sanitize_text_field( (string) ( $in['goal'] ?? '' ) );
	$name = sanitize_text_field( (string) ( $in['name'] ?? '' ) );
	$me = sanitize_text_field( (string) ( $in['me_name'] ?? '' ) );
	ir_ai_step( 'Reading their reply...' );
	$gem = ir_ai_key() !== '';
	$sys = "You are a calm, experienced closer helping a freelancer reply to a prospect in a cold " . ( $ch === 'email' ? 'email thread' : 'DM conversation' ) . ". Your job: understand what the prospect really means, then write replies that move the conversation forward without sounding salesy or desperate.\n\n"
		. "How to reply:\n- Acknowledge what they said first, in their own words where possible.\n- Answer any question directly and honestly.\n- If it is an objection (price, timing, already have someone, think about it, busy), use the matching approach from the scripts below, adapted to this exact conversation. Never argue.\n"
		. "- End with one easy next step: a short question, a call time, a free sample, or permission to follow up later.\n- If they are clearly not interested, leave the door open politely in one or two lines.\n"
		. "- Never invent prices, results, client names or guarantees. Use [PRICE], [CLIENT RESULT], [LINK] placeholders when needed.\n"
		. "- " . ( $ch === 'email' ? 'Emails: 40 to 120 words, no subject needed, sign off with the freelancer\'s first name.' : 'DMs: short, like texting. 1 to 4 short sentences per message. No sign-off.' ) . "\n"
		. "- Plain, warm English. Contractions. No em dashes or en dashes. Match the prospect's language and level of formality.\n\n"
		. "The System's own scripts (use their ideas, never copy {placeholders} literally):\n" . ir_ct_scripts( $ch, $gem ? 22000 : 5000 ) . "\n\n"
		. 'Reply with JSON only: {"kind":"one of: interested, question, price, objection, not_now, not_interested, referral, other","label":"a 2 to 5 word name for what this reply is, e.g. Asked about price","meaning":"what they really mean, 1 or 2 sentences","stage":"one of: replied, interested, call, client, no","replies":[{"label":"short name of this route, e.g. Book the call","text":"the message to send"}],"next":"what to do after sending, 1 sentence","avoid":"one thing not to say, 1 sentence"}. Give exactly 3 replies, each a different route.';
	$user = ( $ctx !== '' ? "What happened so far:\n" . $ctx . "\n\n" : '' ) . ( $name ? 'Prospect name: ' . $name . "\n" : '' ) . ( $me ? 'Freelancer first name: ' . $me . "\n" : '' ) . "The freelancer:\n" . ir_ct_me( $in ) . "\n"
		. ( $goal !== '' ? 'What the freelancer wants next: ' . $goal . "\n" : '' ) . "\nTheir latest reply:\n<<<\n" . mb_substr( $reply, 0, 3000 ) . "\n>>>";
	ir_ai_step( 'Writing your replies...' );
	$raw = $gem ? ir_ai_gemini( $sys, [ [ 'text' => $user ] ], [ 'json' => 1, 'temp' => 0.8, 'max' => 5000, 'think' => 1024 ] ) : ir_ai_free( $sys, $user . "\n\nReply with valid JSON only." );
	if ( is_wp_error( $raw ) ) { return new WP_Error( 'ai', 'The writer is busy right now (' . $raw->get_error_message() . '). Please try again in a minute.' ); }
	$j = ir_ai_json( $raw );
	if ( ! $j || empty( $j['replies'] ) ) { return new WP_Error( 'ai', 'The writer did not finish. Please try again.' ); }
	$clean = function ( $s ) { return trim( preg_replace( [ '/\s*\x{2014}\s*/u', '/\s*\x{2013}\s*/u' ], [ ', ', ', ' ], (string) $s ) ); };
	$reps = [];
	foreach ( array_slice( (array) $j['replies'], 0, 3 ) as $r ) { $t = $clean( is_array( $r ) ? ( $r['text'] ?? '' ) : $r ); if ( $t !== '' ) { $reps[] = [ 'label' => $clean( $r['label'] ?? '' ), 'text' => $t ]; } }
	$stage = in_array( $j['stage'] ?? '', [ 'replied', 'interested', 'call', 'client', 'no' ], true ) ? $j['stage'] : 'replied';
	return [ 'kind' => sanitize_key( $j['kind'] ?? 'other' ), 'label' => $clean( $j['label'] ?? '' ), 'meaning' => $clean( $j['meaning'] ?? '' ), 'stage' => $stage, 'replies' => $reps, 'next' => $clean( $j['next'] ?? '' ), 'avoid' => $clean( $j['avoid'] ?? '' ) ];
}

add_filter( 'ir_ai_tools', function ( $t ) {
	$acc = function () { return function_exists( 'ir_pb_has_access' ) && ir_pb_has_access(); };
	$t['scan'] = [ 'access' => $acc, 'run' => 'ir_ct_scan_run', 'limit' => 60 ];
	$t['reply'] = [ 'access' => $acc, 'run' => 'ir_ct_reply_run', 'limit' => 80 ];
	return $t;
} );

/* ---------- morning reminders ---------- */

function ir_ct_notify_on( $uid ) { return get_user_meta( $uid, 'ir_ct_notify', true ) !== 'off'; }
function ir_ct_unsub_url( $uid ) { return add_query_arg( 'ir_ct_unsub', $uid . '.' . substr( hash_hmac( 'sha256', 'ctr' . $uid, wp_salt( 'auth' ) ), 0, 20 ), home_url( '/' ) ); }

add_action( 'wp_ajax_ir_ct_notify', function () {
	check_ajax_referer( 'ir_pb', 'n' );
	$uid = get_current_user_id();
	update_user_meta( $uid, 'ir_ct_notify', ! empty( $_POST['on'] ) ? 'on' : 'off' );
	wp_send_json( [ 'ok' => true, 'on' => ir_ct_notify_on( $uid ) ] );
} );

add_action( 'init', function () {
	if ( empty( $_GET['ir_ct_unsub'] ) ) { return; }
	$p = explode( '.', sanitize_text_field( wp_unslash( $_GET['ir_ct_unsub'] ) ) );
	$uid = (int) ( $p[0] ?? 0 );
	if ( $uid && isset( $p[1] ) && hash_equals( substr( hash_hmac( 'sha256', 'ctr' . $uid, wp_salt( 'auth' ) ), 0, 20 ), $p[1] ) ) {
		update_user_meta( $uid, 'ir_ct_notify', 'off' );
		wp_die( '<h1 style="font-family:Arial">Reminders turned off</h1><p style="font-family:Arial;font-size:16px">You will not get follow-up reminder emails any more. You can turn them back on any time in your Tracker.</p>', 'Reminders turned off', [ 'response' => 200 ] );
	}
	wp_die( 'This link is not valid.', 'Not valid', [ 'response' => 400 ] );
} );

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'ir_ct_hourly' ) ) { wp_schedule_event( time() + 300, 'hourly', 'ir_ct_hourly' ); }
} );
add_action( 'ir_ct_hourly', 'ir_ct_send_reminders' );

function ir_ct_tips() {
	return [
		'Like or comment on one of their posts before you message. A familiar name gets more replies.',
		'Mention one specific thing you noticed. Generic messages get ignored.',
		'Follow-ups win most deals. Most replies come after the second or third message.',
		'Ask a question that is easy to answer with one line.',
		'Send your messages at the same time every day. Consistency beats volume.',
		'Keep the first message about them, not about you.',
		'If someone says "not now", ask when a better time would be and note it in your tracker.',
		'Record a 60 second video audit for your warmest prospects. It stands out.',
		'Reply within an hour when someone answers. Speed builds trust.',
		'Every no is data. Note the reason in your tracker and adjust your next message.',
	];
}

/** Builds one person's reminder, or null when there is nothing worth sending. */
function ir_ct_digest( $uid, $force = false ) {
	$s = get_user_meta( $uid, 'ir_pb_trk', true );
	if ( ! is_array( $s ) || empty( $s['leads'] ) && empty( $s['days'] ) ) { return null; }
	try { $tz = new DateTimeZone( ! empty( $s['tz'] ) && is_string( $s['tz'] ) ? $s['tz'] : 'Africa/Lagos' ); } catch ( Exception $e ) { $tz = new DateTimeZone( 'Africa/Lagos' ); }
	$now = new DateTime( 'now', $tz );
	/* Send at the first check on or after 8am (until 9pm), once a day: WP-Cron only runs when the site gets a visit, so an exact 8 o'clock hour can be missed. */
	$hr = (int) $now->format( 'G' );
	if ( ! $force && ( $hr < 8 || $hr >= 21 ) ) { return null; }
	$today = $now->format( 'Y-m-d' );
	if ( ! $force && get_user_meta( $uid, 'ir_ct_mailed', true ) === $today ) { return null; }
	$days = (array) ( $s['days'] ?? [] );
	$tot = function ( $d ) use ( $days ) { $x = (array) ( $days[ $d ] ?? [] ); return (int) ( $x['dm'] ?? 0 ) + (int) ( $x['em'] ?? 0 ); };
	$ago = function ( $n ) use ( $now ) { $d = clone $now; $d->modify( '-' . $n . ' day' ); return $d->format( 'Y-m-d' ); };
	$active = false;
	for ( $i = 0; $i < 45; $i++ ) { if ( $tot( $ago( $i ) ) ) { $active = true; break; } }
	$due = [];
	foreach ( (array) ( $s['leads'] ?? [] ) as $l ) {
		if ( ! empty( $l['next'] ) && $l['next'] <= $today && in_array( $l['st'] ?? '', [ 'sent', 'seen' ], true ) ) { $due[] = $l; }
	}
	$monday = $now->format( 'N' ) === '1';
	if ( ! $force && ! $due && ! ( $monday && $active ) ) { return null; }
	$goal = max( 1, (int) ( $s['goal'] ?? 15 ) );
	$y = $tot( $ago( 1 ) );
	$streak = 0; for ( $i = 1; $i < 400 && $tot( $ago( $i ) ) > 0; $i++ ) { $streak++; }
	$week = 0; $rep = 0; $sent30 = 0;
	for ( $i = 0; $i < 30; $i++ ) { $d = $ago( $i ); $x = (array) ( $days[ $d ] ?? [] ); if ( $i < 7 ) { $week += $tot( $d ); } $rep += (int) ( $x['rep'] ?? 0 ); $sent30 += $tot( $d ); }
	$u = get_userdata( $uid );
	$first = trim( (string) ( $s['me']['name'] ?? '' ) ) ?: ( $u->first_name ?: '' );
	$base = function_exists( 'ir_pb_url' ) ? ir_pb_url() : home_url( '/' );
	$p = 'font-size:16px;line-height:1.65;color:#4F5D78;margin:0 0 18px';
	$n = count( $due );
	$h1 = $n ? ( $first ? 'Good morning, ' . esc_html( $first ) . '. ' : 'Good morning. ' ) . $n . ' follow-up' . ( $n > 1 ? 's are' : ' is' ) . ' due today' : ( $first ? 'New week, ' . esc_html( $first ) . '. Let\'s get clients.' : 'New week. Let\'s get clients.' );
	$in = '<h1>' . ( $n ? $n . ' follow-up' . ( $n > 1 ? 's are' : ' is' ) . ' due today' : 'New week. Let\'s get you clients.' ) . '</h1>';
	if ( $n ) {
		$in .= '<p style="' . $p . '">These people already saw your name. A short, friendly nudge today is where most replies come from. Tap <b style="color:#1660E8">Write it</b> and the message is ready for you.</p>';
		$in .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:6px 0 26px">';
		foreach ( array_slice( $due, 0, 8 ) as $l ) {
			$em = ( $l['ch'] ?? 'dm' ) === 'email';
			$who = ( $em || ( isset( $l['at'] ) && $l['at'] === false ) ) ? (string) $l['h'] : '@' . $l['h'];
			$fd = $em ? [ 3, 7, 14, 30 ] : [ 2, 5, 7, 30 ];
			$which = (int) ( $l['fu'] ?? 0 );
			$late = $l['next'] < $today ? ' · <span style="color:#C2410C;font-weight:700">overdue</span>' : '';
			$in .= '<tr><td style="padding:16px 0;border-top:1px solid #EEF2F8"><div style="font-size:16px;line-height:1.35;font-weight:700;color:#0A1633;word-break:break-all">' . esc_html( $who ) . '</div>'
				. '<div style="margin-top:6px;font-size:14px;line-height:1.5;color:#6B7893">' . ( $em ? 'Email' : 'DM' ) . ' follow-up ' . ( $which + 1 ) . ' · day ' . ( $fd[ min( 3, $which ) ] ?? 30 ) . $late . ' &nbsp;·&nbsp; <a href="' . esc_url( $base . 'writer/?lead=' . rawurlencode( (string) $l['id'] ) ) . '" style="color:#1660E8;font-weight:700;text-decoration:none;white-space:nowrap">Write it&nbsp;→</a></div></td></tr>';
		}
		$in .= '</table>' . ( $n > 8 ? '<p style="' . $p . '">And ' . ( $n - 8 ) . ' more in your tracker.</p>' : '' );
	}
	$box = 'background:#F6F8FD;border:1px solid #EAEFF8;border-radius:14px;padding:16px 6px;text-align:center';
	$val = 'font-size:24px;line-height:1.2;font-weight:700;color:#0A1633';
	$lab = 'margin-top:4px;font-size:12.5px;line-height:1.35;color:#6B7893';
	$in .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px"><tr>'
		. '<td width="33%" style="padding-right:5px"><div style="' . $box . '"><div style="' . $val . '">' . $y . '<span style="font-size:14px;color:#6B7893;font-weight:600">/' . $goal . '</span></div><div style="' . $lab . '">yesterday</div></div></td>'
		. '<td width="34%" style="padding:0 5px"><div style="' . $box . '"><div style="' . $val . '">' . $streak . '</div><div style="' . $lab . '">day streak</div></div></td>'
		. '<td width="33%" style="padding-left:5px"><div style="' . $box . '"><div style="' . $val . '">' . $week . '</div><div style="' . $lab . '">this week</div></div></td></tr></table>';
	if ( $y >= $goal ) { $in .= '<p style="' . $p . '">🎯 You hit your goal yesterday. Do it again today and the replies will follow.</p>'; }
	elseif ( $streak === 0 && $active ) { $in .= '<p style="' . $p . '">Your streak reset. Send just 5 messages today to start a new one.</p>'; }
	else { $in .= '<p style="' . $p . '">Today\'s goal: <b style="color:#0A1633">' . $goal . ' messages</b>. Small, daily action is what gets clients.</p>'; }
	if ( $sent30 >= 20 ) { $in .= '<p style="' . $p . '">Last 30 days: ' . $sent30 . ' sent, ' . $rep . ' replies (' . round( $rep / max( 1, $sent30 ) * 100 ) . '% reply rate).</p>'; }
	$tips = ir_ct_tips();
	$in .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 26px"><tr><td style="background:#FFF6EE;border-left:4px solid #FF7A1A;border-radius:10px;padding:14px 16px;font-size:14.5px;line-height:1.6;color:#7C2D12"><b>Tip of the day</b><br>' . esc_html( $tips[ (int) $now->format( 'z' ) % count( $tips ) ] ) . '</td></tr></table>';
	$in .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px"><tr><td bgcolor="#1660E8" style="border-radius:12px;background:#1660E8"><a href="' . esc_url( $base . 'tracker/' ) . '" style="display:inline-block;padding:15px 26px;font-size:16px;line-height:1;font-weight:700;color:#ffffff;text-decoration:none;border-radius:12px">Open my tracker&nbsp;→</a></td></tr></table>';
	$in .= '<p style="margin:24px 0 0;font-size:12.5px;line-height:1.6;color:#8A94A6">You get this because follow-up reminders are on. <a href="' . esc_url( ir_ct_unsub_url( $uid ) ) . '" style="color:#8A94A6">Turn off reminders</a></p>';
	$subject = $n ? $n . ' follow-up' . ( $n > 1 ? 's' : '' ) . ' due today' . ( $streak > 1 ? ' · ' . $streak . '-day streak' : '' ) : 'Your outreach week starts now';
	return [ 'to' => $u->user_email, 'subject' => $subject, 'html' => $in, 'today' => $today ];
}

function ir_ct_send_reminders() {
	if ( ! function_exists( 'ir_pb_mail' ) ) { return; }
	$ids = get_users( [ 'meta_key' => 'ir_pb_trk', 'fields' => 'ID', 'number' => 2000 ] );
	foreach ( $ids as $uid ) {
		$uid = (int) $uid;
		if ( ! ir_ct_notify_on( $uid ) || ( function_exists( 'ir_pb_has_access' ) && ! ir_pb_has_access( $uid ) ) ) { continue; }
		$d = ir_ct_digest( $uid );
		if ( ! $d || ! is_email( $d['to'] ) ) { continue; }
		update_user_meta( $uid, 'ir_ct_mailed', $d['today'] );
		$GLOBALS['ir_mail_unsub'] = ir_ct_unsub_url( $uid );
		ir_pb_mail( $d['to'], $d['subject'], $d['html'] );
		unset( $GLOBALS['ir_mail_unsub'] );
	}
}

/* ---------- for the analyser and reply writer that run on the member's device ---------- */

add_action( 'wp_ajax_ir_ct_facts', function () {
	check_ajax_referer( 'ir_tool', 'n' );
	if ( ! ( function_exists( 'ir_pb_has_access' ) && ir_pb_has_access() ) ) { wp_send_json( [ 'ok' => false, 'err' => 'Please log in again.' ] ); }
	$url = trim( (string) wp_unslash( $_POST['url'] ?? '' ) );
	if ( $url !== '' && ! preg_match( '#^https?://#i', $url ) ) {
		$handle = $url[0] === '@' || ( preg_match( '/^[a-z0-9._]{2,30}$/i', $url ) && ! preg_match( '/\.[a-z]{2,}$/i', $url ) );
		$url = $handle ? 'https://www.instagram.com/' . ltrim( $url, '@' ) . '/' : 'https://' . $url;
	}
	$url = esc_url_raw( $url );
	if ( $url === '' ) { wp_send_json( [ 'ok' => false, 'err' => 'Paste their link or add a screenshot.' ] ); }
	$kind = ir_ct_kind( $url );
	$d = $kind === 'instagram' ? ir_ct_instagram( $url ) : ( $kind === 'google' ? ir_ct_google( $url ) : ir_ct_site( $url ) );
	if ( is_wp_error( $d ) ) {
		$h = (array) $d->get_error_data();
		wp_send_json( [ 'ok' => false, 'kind' => $kind, 'need_shot' => true, 'err' => $d->get_error_message(), 'contact' => [ 'instagram' => (string) ( $h['handle'] ?? '' ) ] ] );
	}
	wp_send_json( [ 'ok' => true, 'kind' => $kind, 'facts' => mb_substr( (string) $d['facts'], 0, 9000 ), 'contact' => array_filter( (array) $d['contact'] ), 'name' => (string) $d['name'], 'image' => (string) ( $d['image'] ?? '' ) ] );
} );

add_action( 'wp_ajax_ir_ct_scripts', function () {
	check_ajax_referer( 'ir_tool', 'n' );
	if ( ! ( function_exists( 'ir_pb_has_access' ) && ir_pb_has_access() ) ) { wp_send_json( [ 'ok' => false, 'err' => 'Please log in again.' ] ); }
	wp_send_json( [ 'ok' => true, 'text' => ir_ct_scripts( ( $_POST['ch'] ?? '' ) === 'email' ? 'email' : 'dm', 9000 ) ] );
} );