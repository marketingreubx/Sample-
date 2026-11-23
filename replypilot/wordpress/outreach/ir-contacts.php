<?php
/**
 * Plugin Name: IR Contact finder
 * Description: Finds a business's email, phone, WhatsApp and owner name from a link: the page itself, its contact, about and team pages, link-in-bio pages and the website a Google listing points to. Used by the Smart Writer (cold email) and the Cold Call Script.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ir_cf_ua() { return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36'; }

/** Several pages at once. Returns [url => html]. */
function ir_cf_fetch_many( $urls, $timeout = 9 ) {
	$out = [];
	$urls = array_values( array_unique( array_filter( $urls ) ) );
	if ( ! $urls ) { return $out; }
	$opt = [ 'timeout' => $timeout, 'connect_timeout' => 6, 'follow_redirects' => true, 'redirects' => 5, 'verify' => true ];
	$hdr = [ 'User-Agent' => ir_cf_ua(), 'Accept-Language' => 'en-GB,en;q=0.9', 'Accept' => 'text/html,application/xhtml+xml' ];
	if ( class_exists( '\WpOrg\Requests\Requests' ) ) {
		$reqs = [];
		foreach ( $urls as $u ) { $reqs[ $u ] = [ 'url' => $u, 'headers' => $hdr, 'type' => 'GET', 'options' => $opt ]; }
		try {
			$res = \WpOrg\Requests\Requests::request_multiple( $reqs, $opt );
			foreach ( $res as $u => $r ) {
				if ( $r instanceof \WpOrg\Requests\Response && $r->status_code < 400 && strlen( $r->body ) > 100 ) { $out[ $u ] = substr( $r->body, 0, 2500000 ); }
			}
			return $out;
		} catch ( \Throwable $e ) { /* fall through to one at a time */ }
	}
	foreach ( $urls as $u ) {
		$r = wp_remote_get( $u, [ 'timeout' => $timeout, 'redirection' => 5, 'headers' => $hdr, 'limit_response_size' => 2500000 ] );
		if ( ! is_wp_error( $r ) && (int) wp_remote_retrieve_response_code( $r ) < 400 ) { $out[ $u ] = (string) wp_remote_retrieve_body( $r ); }
	}
	return $out;
}

/** A page through a reader service, for sites that block servers. Returns HTML or ''. */
function ir_cf_reader( $url ) {
	$r = wp_remote_get( 'https://r.jina.ai/' . $url, [ 'timeout' => 20, 'headers' => [ 'X-Return-Format' => 'html', 'X-Timeout' => '15' ] ] );
	if ( is_wp_error( $r ) || (int) wp_remote_retrieve_response_code( $r ) !== 200 ) { return ''; }
	$b = (string) wp_remote_retrieve_body( $r );
	return strlen( $b ) > 300 ? $b : '';
}

/** Emails on a page, best first. */
function ir_cf_emails( $html ) {
	$found = [];
	$add = function ( $e, $w ) use ( &$found ) {
		$e = strtolower( trim( rawurldecode( $e ), " \t\n\r\0\x0B.,;:<>()[]\"'" ) );
		$e = preg_replace( '/\?.*$/', '', $e );
		if ( ! is_email( $e ) ) { return; }
		if ( preg_match( '/\.(png|jpe?g|gif|webp|svg|css|js)$|@(2x|3x)|sentry|example\.|wixpress|domain\.com|email\.com$|yourname|youremail|name@|user@|test@|noreply|no-reply|@sentry|godaddy|wordpress\.com$|schema\.org/', $e ) ) { return; }
		$found[ $e ] = max( $found[ $e ] ?? 0, $w );
	};
	if ( preg_match_all( '/href=["\']mailto:([^"\'?]+)/i', $html, $m ) ) { foreach ( $m[1] as $e ) { $add( $e, 5 ); } }
	if ( preg_match_all( '/data-cfemail=["\']([0-9a-f]+)["\']/i', $html, $m ) ) {
		foreach ( $m[1] as $hex ) { $k = hexdec( substr( $hex, 0, 2 ) ); $s = ''; for ( $i = 2; $i < strlen( $hex ); $i += 2 ) { $s .= chr( hexdec( substr( $hex, $i, 2 ) ) ^ $k ); } $add( $s, 5 ); }
	}
	if ( preg_match_all( '/"email"\s*:\s*"([^"]+)"/i', $html, $m ) ) { foreach ( $m[1] as $e ) { $add( str_replace( 'mailto:', '', $e ), 4 ); } }
	$text = html_entity_decode( preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', ' ', $html ), ENT_QUOTES, 'UTF-8' );
	$text = preg_replace( '/\s*[\[\(\{]\s*(at|@)\s*[\]\)\}]\s*/i', '@', $text );
	$text = preg_replace( '/\s*[\[\(\{]\s*(dot)\s*[\]\)\}]\s*/i', '.', $text );
	if ( preg_match_all( '/[a-z0-9._%+-]+@[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}/i', $text, $m ) ) { foreach ( $m[0] as $e ) { $add( $e, 3 ); } }
	arsort( $found );
	/* Prefer an inbox a person reads: info@, hello@, bookings@ beat support@ or careers@. */
	$score = function ( $e ) use ( $found ) { $s = $found[ $e ]; if ( preg_match( '/^(info|hello|hi|contact|bookings?|enquir|inquir|appointments?|admin|office|studio|team)@/', $e ) ) { $s += 1; } if ( preg_match( '/^(careers|jobs|privacy|legal|abuse|webmaster|dpo|press)@/', $e ) ) { $s -= 3; } return $s; };
	$list = array_keys( $found );
	usort( $list, function ( $a, $b ) use ( $score ) { return $score( $b ) <=> $score( $a ); } );
	return $list;
}

/** Normalise a phone number. Nigerian mobiles get +234, UK numbers on UK sites get +44, anything else stays as written. */
function ir_cf_norm_phone( $p, $cc = '' ) {
	$p = trim( $p );
	$plus = strpos( $p, '+' ) === 0 || strpos( $p, '00' ) === 0;
	$d = preg_replace( '/\D/', '', $p );
	if ( strpos( $p, '00' ) === 0 ) { $d = substr( $d, 2 ); }
	if ( strlen( $d ) < 7 || strlen( $d ) > 15 ) { return ''; }
	if ( $plus ) { return '+' . $d; }
	if ( preg_match( '/^234[789][01]\d{8}$/', $d ) ) { return '+' . $d; }
	if ( preg_match( '/^0[789][01]\d{8}$/', $d ) && ( $cc === '' || $cc === '234' ) ) { return '+234' . substr( $d, 1 ); }
	if ( $cc === '44' && preg_match( '/^0\d{9,10}$/', $d ) ) { return '+44' . substr( $d, 1 ); }
	if ( $cc === '1' && strlen( $d ) === 10 ) { return '+1' . $d; }
	return $d;
}

/** The country code a site most likely uses, from its domain. */
function ir_cf_cc( $url ) {
	$h = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	if ( preg_match( '/\.ng$/', $h ) ) { return '234'; }
	if ( preg_match( '/\.uk$/', $h ) ) { return '44'; }
	if ( preg_match( '/\.(us|ca)$/', $h ) ) { return '1'; }
	return '';
}

/** Phone numbers on a page, best first: tel: links, structured data, WhatsApp, then numbers written next to "call", "phone" and similar. */
function ir_cf_phones( $html, $cc = '' ) {
	$found = [];
	$add = function ( $p, $w ) use ( &$found, $cc ) { $n = ir_cf_norm_phone( $p, $cc ); if ( $n === '' || preg_match( '/(\d)\1{5,}|1234567|2345678|3456789|9876543|5550100|5551234/', $n ) ) { return; } $found[ $n ] = max( $found[ $n ] ?? 0, $w ); };
	if ( preg_match_all( '/href=["\']tel:([^"\']+)["\']/i', $html, $m ) ) { foreach ( $m[1] as $p ) { $add( rawurldecode( $p ), 6 ); } }
	if ( preg_match_all( '/"telephone"\s*:\s*"([^"]+)"/i', $html, $m ) ) { foreach ( $m[1] as $p ) { $add( $p, 5 ); } }
	if ( preg_match_all( '#(?:wa\.me/|api\.whatsapp\.com/send/?\?phone=|whatsapp://send\?phone=)\+?(\d{8,15})#i', $html, $m ) ) { foreach ( $m[1] as $p ) { $add( '+' . $p, 4 ); } }
	$text = html_entity_decode( wp_strip_all_tags( preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', ' ', $html ) ), ENT_QUOTES, 'UTF-8' );
	$text = preg_replace( '/\s+/', ' ', $text );
	$num = '(\+?\d[\d\s().-]{6,18}\d)';
	if ( preg_match_all( '/(?:call|phone|tel|mobile|whatsapp|contact|reach us|hotline|cell)[^0-9+]{0,24}' . $num . '/i', $text, $m ) ) { foreach ( $m[1] as $p ) { $add( $p, 3 ); } }
	if ( preg_match_all( '/(?<![\d])(0[789][01]\d[\s-]?\d{3}[\s-]?\d{4}|\+234[\s-]?\(?0?\)?[789][01]\d[\s-]?\d{3}[\s-]?\d{4})(?![\d])/', $text, $m ) ) { foreach ( $m[1] as $p ) { $add( $p, 2 ); } }
	arsort( $found );
	return array_keys( $found );
}

/** The owner's or manager's name, only when a page says it plainly. */
function ir_cf_owner( $html ) {
	$bad = '/^(the|our|meet|welcome|about|contact|book|skin|beauty|spa|studio|clinic|salon|team|home|hello|hi|dear|founder|owner|ceo|lead|head|chief|managing|creative|director|esthetician|aesthetician|services|privacy|terms|cookie|copyright|all|we|us|my|your|and|of|for|with|by|in|at|lagos|abuja|london|nigeria|instagram|facebook|google)$/i';
	$ok = function ( $n ) use ( $bad ) { $n = trim( $n ); $w = preg_split( '/\s+/', $n ); if ( ! $w || count( $w ) > 3 ) { return ''; } foreach ( $w as $x ) { if ( preg_match( $bad, $x ) || ! preg_match( '/^[A-Z][a-z\'’-]{1,20}$/u', $x ) ) { return ''; } } return $n; };
	if ( preg_match_all( '/"(?:founder|founders|author|employee|member)"\s*:\s*\[?\s*\{[^{}]*?"name"\s*:\s*"([^"]{3,40})"/i', $html, $m ) ) { foreach ( $m[1] as $n ) { if ( $o = $ok( $n ) ) { return $o; } } }
	$text = html_entity_decode( wp_strip_all_tags( preg_replace( '#<(script|style|nav|footer)[^>]*>.*?</\1>#is', ' ', $html ) ), ENT_QUOTES, 'UTF-8' );
	$text = preg_replace( '/\s+/', ' ', $text );
	$N = '([A-Z][a-z\'’-]{1,20}(?:\s[A-Z][a-z\'’-]{1,20}){0,2})';
	$pats = [
		'/\b(?:founder|co-founder|owner|ceo|creative director|managing director|lead (?:esthetician|aesthetician|stylist|therapist|artist)|principal)\s*(?:[:,\-–|]|is|\bof\b[^.]{0,30}?[:,\-–])\s*' . $N . '/i',
		'/' . $N . ',?\s*(?:is\s+)?(?:the\s+|our\s+)?(?:founder|co-founder|owner|ceo|creative director|managing director|lead (?:esthetician|aesthetician))\b/',
		'/\b(?:Hi|Hello|Hey),?\s+I[\'’]?m\s+' . $N . '/',
		'/\bMy name is\s+' . $N . '/',
		'/\bMeet\s+' . $N . '/',
	];
	foreach ( $pats as $p ) {
		if ( preg_match_all( $p, $text, $m ) ) { foreach ( $m[1] as $n ) { $n = preg_replace( '/\s+(?:Founder|Owner|CEO|Is|The|Our)\b.*$/', '', $n ); if ( $o = $ok( $n ) ) { return $o; } } }
	}
	return '';
}

/** Short readable text of an about or team page, so the AI can read who runs the business. */
function ir_cf_text( $html, $len = 1200 ) {
	$t = html_entity_decode( wp_strip_all_tags( preg_replace( '#<(script|style|nav|header|footer|form|svg)[^>]*>.*?</\1>#is', ' ', $html ) ), ENT_QUOTES, 'UTF-8' );
	return mb_substr( trim( preg_replace( '/\s+/', ' ', $t ) ), 0, $len );
}

/** Links on the same site that usually hold contact details or who runs it. */
function ir_cf_inner_links( $html, $base ) {
	$host = strtolower( preg_replace( '/^www\./', '', (string) wp_parse_url( $base, PHP_URL_HOST ) ) );
	$scheme = (string) wp_parse_url( $base, PHP_URL_SCHEME ) ?: 'https';
	$out = [];
	if ( ! preg_match_all( '#<a\s[^>]*href=["\']([^"\'\#]+)["\'][^>]*>(.*?)</a>#is', $html, $m, PREG_SET_ORDER ) ) { return $out; }
	foreach ( $m as $x ) {
		$h = trim( html_entity_decode( $x[1] ) ); $t = strtolower( wp_strip_all_tags( $x[2] ) );
		if ( preg_match( '#^(mailto|tel|javascript|whatsapp):#i', $h ) ) { continue; }
		if ( strpos( $h, '//' ) === 0 ) { $h = $scheme . ':' . $h; }
		elseif ( $h[0] === '/' ) { $h = $scheme . '://' . wp_parse_url( $base, PHP_URL_HOST ) . $h; }
		elseif ( ! preg_match( '#^https?://#i', $h ) ) { $h = rtrim( $base, '/' ) . '/' . $h; }
		$hh = strtolower( preg_replace( '/^www\./', '', (string) wp_parse_url( $h, PHP_URL_HOST ) ) );
		if ( $hh !== $host ) { continue; }
		$path = strtolower( (string) wp_parse_url( $h, PHP_URL_PATH ) );
		$score = 0;
		if ( preg_match( '/contact|get-in-touch|reach|enquir|inquir/', $path . ' ' . $t ) ) { $score = 3; }
		elseif ( preg_match( '/about|our-story|team|meet|founder|who-we-are/', $path . ' ' . $t ) ) { $score = 2; }
		elseif ( preg_match( '/book|appointment|location|visit/', $path . ' ' . $t ) ) { $score = 1; }
		if ( $score ) { $out[ strtok( $h, '#' ) ] = max( $out[ $h ] ?? 0, $score ); }
	}
	arsort( $out );
	return array_slice( array_keys( $out ), 0, 3 );
}

/** The first real website a link-in-bio page or Google listing points to. */
function ir_cf_outbound( $html, $base ) {
	$host = strtolower( (string) wp_parse_url( $base, PHP_URL_HOST ) );
	if ( ! preg_match_all( '#href=["\'](https?://[^"\']+)["\']#i', $html, $m ) ) { return ''; }
	foreach ( $m[1] as $u ) {
		$u = html_entity_decode( $u );
		if ( preg_match( '#[?&](?:q|url|u)=(https?%3A[^&]+)#i', $u, $q ) ) { $u = rawurldecode( $q[1] ); }
		$h = strtolower( (string) wp_parse_url( $u, PHP_URL_HOST ) );
		if ( $h === '' || $h === $host ) { continue; }
		if ( preg_match( '/(google|gstatic|googleusercontent|goo\.gl|instagram|facebook|fb\.com|tiktok|twitter|x\.com|youtube|linkedin|pinterest|whatsapp|wa\.me|linktr\.ee|beacons|bio\.link|lnk\.bio|apple\.com|cdn|cloudflare|schema\.org|w3\.org|jsdelivr|unpkg|fonts\.|paystack|flutterwave|stripe|calendly|wix(static)?\.com|squarespace|shopify\.com|wordpress\.org|gravatar)/', $h ) ) { continue; }
		return $u;
	}
	return '';
}

/**
 * Everything we can find for one link.
 * Returns [ email, emails[], phone, phones[], whatsapp, owner, website, notes, pages[] ].
 */
function ir_cf_find( $url, $start_html = '' ) {
	$res = [ 'email' => '', 'emails' => [], 'phone' => '', 'phones' => [], 'whatsapp' => '', 'owner' => '', 'website' => '', 'about' => '', 'pages' => [] ];
	$url = trim( (string) $url );
	if ( $url === '' ) { return $res; }
	if ( ! preg_match( '#^https?://#i', $url ) ) { $url = 'https://' . $url; }
	$ck = 'ir_cf_' . md5( strtolower( $url ) );
	$c = get_transient( $ck );
	if ( is_array( $c ) ) { return $c; }
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	if ( preg_match( '/(^|\.)instagram\.com$|(^|\.)tiktok\.com$|(^|\.)facebook\.com$/', $host ) ) { return $res; }
	$pages = [];
	$first = $start_html !== '' ? [ $url => $start_html ] : ir_cf_fetch_many( [ $url ], 12 );
	$html = (string) ( $first[ $url ] ?? reset( $first ) ?: '' );
	if ( $html === '' ) { $html = ir_cf_reader( $url ); }
	if ( $html === '' ) { return $res; }
	$pages[ $url ] = $html;
	$google = (bool) preg_match( '/(^|\.)(google\.[a-z.]+|goo\.gl|g\.co|g\.page|share\.google)$/', $host );
	$linkpage = (bool) preg_match( '/(^|\.)(linktr\.ee|beacons\.ai|bio\.link|lnk\.bio|taplink\.cc|campsite\.bio|carrd\.co|komi\.io|stan\.store|solo\.to|msha\.ke)$/', $host );
	$site = $url;
	if ( $google || $linkpage ) {
		$out = ir_cf_outbound( $html, $url );
		if ( $out !== '' ) { $res['website'] = $out; $site = $out; $more = ir_cf_fetch_many( [ $out ], 10 ); if ( ! empty( $more ) ) { $pages[ $out ] = reset( $more ); } }
	}
	$home = (string) ( $pages[ $site ] ?? '' );
	if ( $home !== '' ) {
		$inner = ir_cf_inner_links( $home, $site );
		if ( $inner ) {
			$got = ir_cf_fetch_many( $inner, 8 );
			if ( ! $got ) { foreach ( array_slice( $inner, 0, 2 ) as $u ) { $h = ir_cf_reader( $u ); if ( $h !== '' ) { $got[ $u ] = $h; } } }
			foreach ( $got as $u => $h ) { $pages[ $u ] = $h; }
		}
	}
	$em = []; $ph = []; $owner = ''; $about = '';
	foreach ( $pages as $u => $h ) {
		$em = array_merge( $em, ir_cf_emails( $h ) );
		$ph = array_merge( $ph, ir_cf_phones( $h, ir_cf_cc( $u ) ) );
		if ( $owner === '' ) { $owner = ir_cf_owner( $h ); }
		if ( preg_match( '/about|team|story|meet|founder/i', $u ) && $about === '' ) { $about = ir_cf_text( $h ); }
	}
	$em = array_values( array_unique( $em ) ); $ph = array_values( array_unique( $ph ) );
	foreach ( $pages as $h ) { if ( preg_match( '#(?:wa\.me/|api\.whatsapp\.com/send/?\?phone=)\+?(\d{8,15})#i', $h, $w ) ) { $res['whatsapp'] = $w[1]; break; } }
	$res['emails'] = array_slice( $em, 0, 4 ); $res['email'] = $em[0] ?? '';
	$res['phones'] = array_slice( $ph, 0, 4 ); $res['phone'] = $ph[0] ?? '';
	$res['owner'] = $owner; $res['about'] = $about; $res['pages'] = array_keys( $pages );
	set_transient( $ck, $res, 6 * HOUR_IN_SECONDS );
	return $res;
}

/** Contact details written in a caption or bio. */
function ir_cf_from_text( $text ) {
	$text = (string) $text;
	return [ 'email' => ir_cf_emails( $text )[0] ?? '', 'phone' => ir_cf_phones( $text )[0] ?? '' ];
}
