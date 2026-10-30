<?php
/**
 * AI Profit System tools: Content Cloner, Comment Writer and Flyer Remixer.
 * The AI runs on the member's own device (ir-client.js): the free writer is called from their browser,
 * and pictures and videos are read on their device. The server only fetches public post data.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ir_tl_nodash( $s ) {
	if ( is_array( $s ) ) { return array_map( 'ir_tl_nodash', $s ); }
	if ( ! is_string( $s ) ) { return $s; }
	return trim( preg_replace( [ '/\s*\x{2014}\s*/u', '/\s*\x{2013}\s*/u' ], [ ', ', ', ' ], $s ) );
}

function ir_tl_meta( $html, $prop ) {
	if ( preg_match( '/<meta[^>]+(?:property|name)=["\']' . preg_quote( $prop, '/' ) . '["\'][^>]*content=["\']([^"\']*)/i', $html, $m ) ) { return html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ); }
	if ( preg_match( '/<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']' . preg_quote( $prop, '/' ) . '["\']/i', $html, $m ) ) { return html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ); }
	return '';
}

function ir_tl_platform( $host ) {
	$map = [ 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'youtu.be' => 'YouTube', 'linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'fb.' => 'Facebook', 'threads' => 'Threads', 'x.com' => 'X', 'twitter' => 'X' ];
	foreach ( $map as $k => $v ) { if ( strpos( $host, $k ) !== false ) { return $v; } }
	return 'Website';
}

/** Short share links (vt.tiktok.com, instagram.com/share, t.co, bit.ly ...) → the real post link. */
function ir_tl_expand( $url ) {
	for ( $i = 0; $i < 4; $i++ ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$short = preg_match( '/^(vt|vm)\.tiktok\.com$|^(www\.)?tiktok\.com$/', $host ) && ! preg_match( '#/video/|/photo/#', $url );
		$short = $short || preg_match( '#instagram\.com/share/#', $url ) || preg_match( '/^(t\.co|bit\.ly|fb\.watch|lnkd\.in|tinyurl\.com|youtu\.be|on\.soundcloud\.com)$/', $host );
		if ( ! $short || $host === 'youtu.be' ) { return $url; }
		$r = wp_remote_get( $url, [ 'timeout' => 12, 'redirection' => 0, 'user-agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1' ] );
		$loc = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_header( $r, 'location' );
		if ( $loc === '' ) { return $url; }
		if ( strpos( $loc, '//' ) === 0 ) { $loc = 'https:' . $loc; }
		$url = $loc;
	}
	return $url;
}

/** Reads a post from its link: [ platform, author, text, said, image ] or WP_Error. */
/** Caption, author, cover and auto captions from a TikTok video page. */
function ir_tl_tiktok_page( $url ) {
	$r = wp_remote_get( $url, [ 'timeout' => 15, 'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36', 'headers' => [ 'Accept-Language' => 'en-US,en;q=0.9' ] ] );
	if ( is_wp_error( $r ) || ! preg_match( '#<script id="__UNIVERSAL_DATA_FOR_REHYDRATION__"[^>]*>(.*?)</script>#s', (string) wp_remote_retrieve_body( $r ), $m ) ) { return null; }
	$j = json_decode( $m[1], true );
	$it = $j['__DEFAULT_SCOPE__']['webapp.video-detail']['itemInfo']['itemStruct'] ?? null;
	if ( ! is_array( $it ) ) { return null; }
	$out = [ 'desc' => trim( (string) ( $it['desc'] ?? '' ) ), 'author' => (string) ( $it['author']['uniqueId'] ?? '' ), 'cover' => (string) ( $it['video']['cover'] ?? '' ), 'said' => '' ];
	$caps = [];
	foreach ( (array) ( $it['video']['subtitleInfos'] ?? [] ) as $c ) { if ( ! empty( $c['Url'] ) ) { $caps[] = [ (string) ( $c['LanguageCodeName'] ?? '' ), $c['Url'] ]; } }
	foreach ( (array) ( $it['video']['claInfo']['captionInfos'] ?? [] ) as $c ) { if ( ! empty( $c['url'] ) ) { $caps[] = [ (string) ( $c['language'] ?? '' ), $c['url'] ]; } }
	usort( $caps, function ( $a, $b ) { return (int) ( stripos( $b[0], 'en' ) === 0 ) - (int) ( stripos( $a[0], 'en' ) === 0 ); } );
	if ( $caps ) {
		$v = wp_remote_get( $caps[0][1], [ 'timeout' => 12, 'headers' => [ 'Referer' => 'https://www.tiktok.com/' ], 'cookies' => wp_remote_retrieve_cookies( $r ) ] );
		$vt = is_wp_error( $v ) ? '' : (string) wp_remote_retrieve_body( $v );
		if ( strpos( $vt, '-->' ) !== false ) {
			$lines = [];
			foreach ( preg_split( '/\n\n+/', str_replace( "\r", '', $vt ) ) as $b ) {
				$txt = trim( implode( ' ', array_filter( explode( "\n", $b ), function ( $l ) { return strpos( $l, '-->' ) === false && ! preg_match( '/^WEBVTT|^\d+$/', trim( $l ) ); } ) ) );
				if ( $txt !== '' && ( ! $lines || end( $lines ) !== $txt ) ) { $lines[] = $txt; }
			}
			$out['said'] = mb_substr( implode( ' ', $lines ), 0, 3000 );
		}
	}
	return $out;
}

function ir_tl_fetch_post( $url ) {
	$url = ir_tl_expand( $url );
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	if ( ! $host ) { return new WP_Error( 'url', 'Paste the full link, starting with https://' ); }
	$pl = ir_tl_platform( $host );
	$out = [ 'platform' => $pl, 'author' => '', 'text' => '', 'said' => '', 'image' => '', 'url' => $url ];
	if ( $pl === 'TikTok' || $pl === 'YouTube' ) {
		$api = $pl === 'TikTok' ? 'https://www.tiktok.com/oembed?url=' : 'https://www.youtube.com/oembed?format=json&url=';
		$r = wp_remote_get( $api . rawurlencode( $url ), [ 'timeout' => 15 ] );
		$j = is_wp_error( $r ) ? null : json_decode( wp_remote_retrieve_body( $r ), true );
		if ( $j ) { $out['text'] = (string) ( $j['title'] ?? '' ); $out['author'] = (string) ( $j['author_unique_id'] ?? $j['author_name'] ?? '' ); $out['image'] = (string) ( $j['thumbnail_url'] ?? '' ); }
		/* TikTok's oEmbed no longer carries the caption: read it, and any auto captions, from the video page. */
		if ( $pl === 'TikTok' && $out['text'] === '' ) {
			$tp = ir_tl_tiktok_page( $url );
			if ( $tp ) { $out['text'] = $tp['desc']; $out['author'] = $out['author'] ?: $tp['author']; $out['image'] = $out['image'] ?: $tp['cover']; if ( $tp['said'] !== '' ) { $out['said'] = $tp['said']; } }
		}
		if ( $pl === 'TikTok' && function_exists( 'ir_pp_reel_tiktok' ) && preg_match( '#/video/\d+#', $url ) ) {
			$t = ir_pp_reel_tiktok( $url );
			if ( is_array( $t ) && ! empty( $t['media'] ) ) { $out['media'] = (string) $t['media']; }
			if ( $out['said'] === '' && is_array( $t ) && ! empty( $t['vtt'] ) ) {
				$lines = [];
				foreach ( preg_split( '/\n\n+/', str_replace( "\r", '', $t['vtt'] ) ) as $b ) {
					$txt = trim( implode( ' ', array_filter( explode( "\n", $b ), function ( $l ) { return strpos( $l, '-->' ) === false && ! preg_match( '/^WEBVTT|^\d+$/', trim( $l ) ); } ) ) );
					if ( $txt === '' ) { continue; }
					if ( $lines && strpos( $txt, end( $lines ) ) === 0 ) { $lines[ count( $lines ) - 1 ] = $txt; } elseif ( ! $lines || end( $lines ) !== $txt ) { $lines[] = $txt; }
				}
				$out['said'] = mb_substr( implode( ' ', $lines ), 0, 3000 );
				if ( ! $out['author'] && ! empty( $t['author'] ) ) { $out['author'] = $t['author']; }
			}
		}
		if ( $out['text'] !== '' || $out['said'] !== '' ) { return $out; }
	}
	if ( $pl === 'X' ) {
		$r = wp_remote_get( 'https://publish.twitter.com/oembed?omit_script=1&url=' . rawurlencode( $url ), [ 'timeout' => 15 ] );
		$j = is_wp_error( $r ) ? null : json_decode( wp_remote_retrieve_body( $r ), true );
		if ( $j && ! empty( $j['html'] ) ) {
			$t = html_entity_decode( wp_strip_all_tags( preg_replace( '#<br\s*/?>#', "\n", $j['html'] ) ), ENT_QUOTES, 'UTF-8' );
			$out['text'] = trim( preg_replace( '/\x{2014}\s*[^\n]*$/u', '', $t ) );
			$out['author'] = (string) ( $j['author_name'] ?? '' );
			return $out;
		}
	}
	/* Instagram blocks normal page reads, but its public embed page carries the caption. */
	if ( $pl === 'Instagram' && preg_match( '#/(?:reel|reels|p|tv)/([A-Za-z0-9_-]+)#', $url, $ig ) ) {
		$e = wp_remote_get( 'https://www.instagram.com/p/' . $ig[1] . '/embed/captioned/', [ 'timeout' => 15, 'user-agent' => defined( 'IR_RSC_UA' ) ? IR_RSC_UA : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36', 'headers' => [ 'Accept-Language' => 'en-US,en;q=0.9' ] ] );
		$eb = is_wp_error( $e ) ? '' : (string) wp_remote_retrieve_body( $e );
		if ( $eb !== '' ) {
			if ( preg_match( '#class="UsernameText"[^>]*>([^<]+)<#', $eb, $um ) ) { $out['author'] = trim( $um[1] ); }
			if ( preg_match( '#<div class="Caption">(.*?)<div class="CaptionComments"#s', $eb, $cm ) ) {
				$c = preg_replace( '#<a class="CaptionUsername".*?</a>#s', '', $cm[1] );
				$c = html_entity_decode( wp_strip_all_tags( preg_replace( '#<br\s*/?>#', "\n", $c ) ), ENT_QUOTES, 'UTF-8' );
				$out['text'] = trim( preg_replace( "/\n{3,}/", "\n\n", preg_replace( '/[ \t]+/', ' ', $c ) ) );
			} elseif ( preg_match( '#\\?"caption\\?"\s*:\s*\{[^}]*?\\?"text\\?"\s*:\s*\\?"(.*?)\\?"\s*[,}]#s', $eb, $jm ) ) {
				$out['text'] = trim( json_decode( '"' . str_replace( '\\"', '"', $jm[1] ) . '"' ) ?: '' );
			}
			if ( preg_match( '#class="EmbeddedMediaImage"[^>]*src="([^"]+)"#', $eb, $im ) ) { $out['image'] = html_entity_decode( $im[1] ); }
			if ( $out['text'] !== '' ) { return $out; }
		}
	}
	$r = wp_remote_get( $url, [ 'timeout' => 15, 'redirection' => 5, 'user-agent' => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', 'headers' => [ 'Accept-Language' => 'en-US,en;q=0.9' ] ] );
	if ( is_wp_error( $r ) || wp_remote_retrieve_response_code( $r ) >= 400 ) { return new WP_Error( 'fetch', 'This post did not open for us. Tap "Paste the caption instead" and paste its words.' ); }
	$b = (string) wp_remote_retrieve_body( $r );
	$desc = ir_tl_meta( $b, 'og:description' ) ?: ir_tl_meta( $b, 'twitter:description' ) ?: ir_tl_meta( $b, 'description' );
	$title = ir_tl_meta( $b, 'og:title' ) ?: ( preg_match( '#<title[^>]*>(.*?)</title>#is', $b, $m ) ? html_entity_decode( trim( $m[1] ), ENT_QUOTES, 'UTF-8' ) : '' );
	$out['image'] = ir_tl_meta( $b, 'og:image' );
	if ( $pl === 'Instagram' && preg_match( '/^[^-]*-\s*([A-Za-z0-9._]+) on [^:]+:\s*"?(.*?)"?\.?$/su', $desc, $m ) ) {
		$out['author'] = $m[1]; $out['text'] = trim( preg_replace( '/["\x{201D}]\.?\s*$/u', '', trim( $m[2] ) ) );
	} else {
		$out['text'] = trim( $desc !== '' ? $desc : $title );
		$out['author'] = $pl === 'Website' ? (string) wp_parse_url( $url, PHP_URL_HOST ) : preg_replace( '/\s+(on|\|)\s+.*$/', '', $title );
	}
	if ( preg_match( '/^(TikTok|Instagram|Facebook|LinkedIn)( · .*)?$/u', $out['text'] ) ) { $out['text'] = ''; }
	return $out;
}

function ir_tl_image_part( $url ) {
	if ( ! $url || ! function_exists( 'ir_ai_can_see' ) || ! ir_ai_can_see() ) { return null; }
	$r = wp_remote_get( $url, [ 'timeout' => 20, 'limit_response_size' => 6 * MB_IN_BYTES, 'user-agent' => 'facebookexternalhit/1.1' ] );
	if ( is_wp_error( $r ) || wp_remote_retrieve_response_code( $r ) !== 200 ) { return null; }
	$type = (string) wp_remote_retrieve_header( $r, 'content-type' );
	if ( strpos( $type, 'image/' ) !== 0 ) { return null; }
	return [ 'inline_data' => [ 'mime_type' => strtok( $type, ';' ), 'data' => base64_encode( wp_remote_retrieve_body( $r ) ) ] ];
}

function ir_tl_member() {
	return ( function_exists( 'ir_pp_has_access' ) && ir_pp_has_access() ) || ( function_exists( 'ir_pb_has_access' ) && ir_pb_has_access() );
}

/* The post behind a link, for the Comment Writer (fast, no AI). */
add_action( 'wp_ajax_ir_tl_post', function () {
	check_ajax_referer( 'ir_tool', 'n' );
	if ( ! ir_tl_member() ) { wp_send_json( [ 'ok' => false, 'err' => 'Please log in again.' ] ); }
	$url = esc_url_raw( trim( (string) wp_unslash( $_POST['url'] ?? '' ) ) );
	$p = ir_tl_fetch_post( $url );
	if ( is_wp_error( $p ) ) { wp_send_json( [ 'ok' => false, 'err' => $p->get_error_message() ] ); }
	$p['text'] = mb_substr( $p['text'], 0, 4000 );
	wp_send_json( [ 'ok' => true, 'post' => $p ] );
} );

/* ---------- pages ---------- */

function ir_tools_route( $route ) {
	$map = [ 'comments' => 'ir_tl_page_comments', 'flyer' => 'ir_tl_page_flyer', 'clone' => 'ir_tl_page_clone', 'offer' => 'ir_tl_page_offer', 'pricing' => 'ir_tl_page_pricing', 'invoice' => 'ir_tl_page_invoice', 'contract' => 'ir_tl_page_contract', 'enquiry' => 'ir_tl_page_enquiry' ];
	if ( ! isset( $map[ $route ] ) ) { return false; }
	if ( in_array( $route, ir_smm_tools(), true ) ) { wp_safe_redirect( ir_smm_url( $route ) ); exit; }
	if ( $route === 'offer' && function_exists( 'ir_pb_has_access' ) && ir_pb_has_access() ) { wp_safe_redirect( ir_pb_url( 'offer' ) ); exit; }
	call_user_func( $map[ $route ] );
	return true;
}

/** White line icons for the tool tiles and headers. */
function ir_tl_svg( $k, $size = 26 ) {
	$p = [
		'clone'   => '<rect x="3" y="5" width="13" height="14" rx="3"/><path d="M16 10.5l5-3v9l-5-3"/><path d="M8 9.5v5l4-2.5z" fill="#fff" stroke="none"/>',
		'comment' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8a2.5 2.5 0 0 1-2.5 2.5H10l-4.5 4v-4h0A1.5 1.5 0 0 1 4 14.5z"/><path d="M8 8h8M8 11.5h5"/>',
		'flyer'   => '<rect x="4" y="3" width="16" height="18" rx="2.5"/><circle cx="9.5" cy="8.5" r="1.8"/><path d="M4 16l4.5-4.5 3.5 3.5 2.5-2.5L20 17"/>',
		'offer'   => '<path d="M8 21h8M12 17v4"/><path d="M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 6H4v1a3 3 0 0 0 3 3M17 6h3v1a3 3 0 0 1-3 3"/>',
		'invoice' => '<path d="M6 3h9l4 4v14l-2.5-1.5L14 21l-2-1.5L10 21l-2.5-1.5L5 21V4a1 1 0 0 1 1-1z"/><path d="M9 9h6M9 13h6M9 17h3"/>',
		'contract' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h3"/><path d="M14 18.5c.8-.9 1.6-1.5 2.4-1.2"/>',
		'enquiry' => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>',
		'tracker' => '<path d="M4 20V11M10 20V5M16 20v-6M21 20H3"/>',
		'writer'  => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4zM13.5 6.5l4 4"/>',
		'tools'   => '<rect x="3" y="3" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="2"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="2"/><path d="M17.25 14v6.5M14 17.25h6.5"/>',
		'doc'     => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M12 11v6M9.5 14.5 12 17l2.5-2.5"/>',
		'price'   => '<rect x="4" y="3" width="16" height="18" rx="2.5"/><path d="M8 7h8"/><circle cx="8.5" cy="12" r=".6" fill="#fff"/><circle cx="12" cy="12" r=".6" fill="#fff"/><circle cx="15.5" cy="12" r=".6" fill="#fff"/><circle cx="8.5" cy="16" r=".6" fill="#fff"/><circle cx="12" cy="16" r=".6" fill="#fff"/><path d="M15.5 15v2"/>',
	];
	return '<svg width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $p[ $k ] ?? '' ) . '</svg>';
}

function ir_tl_css() {
	return <<<'CSS'
.tl{max-width:760px;margin:0 auto}
.tl-hero{display:flex;gap:14px;align-items:center;margin:4px 0 18px}
.tl-hero .ic{width:56px;height:56px;border-radius:16px;display:grid;place-items:center;background:linear-gradient(135deg,var(--k1,#4f7cff),var(--k2,#22d3ee));flex:none;box-shadow:0 8px 20px rgba(0,0,0,.18)}
.tl-hero h1{margin:0;font-size:26px;letter-spacing:-.5px;line-height:1.15}.tl-hero p{margin:5px 0 0;color:var(--muted);font-size:15px;line-height:1.5}
.tl-card{background:var(--surface);border:1px solid var(--line);border-radius:20px;padding:18px;margin:0 0 14px}
.tl-f{display:grid;gap:6px;margin:0 0 14px}.tl-f>span{font-weight:700;font-size:14.5px}.tl-f>span em{font-style:normal;font-weight:600;color:var(--muted);font-size:13px}.tl-f small{color:var(--muted);font-size:13px;line-height:1.4}
.tl-f input,.tl-f textarea,.tl-f select{width:100%;box-sizing:border-box;font:inherit;font-size:16px!important;padding:13px 14px;border:1px solid var(--line);border-radius:12px;background:var(--surface);color:var(--text)}
.tl-f textarea{resize:vertical;line-height:1.55}
.tl-two{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media(max-width:560px){.tl-two{grid-template-columns:1fr}}
.tl-drop{display:grid;place-items:center;text-align:center;gap:6px;border:2px dashed var(--line);border-radius:16px;padding:22px 16px;cursor:pointer;color:var(--muted);font-size:14.5px;margin:0 0 14px}.tl-drop b{color:var(--text);font-size:16px}.tl-drop .e{font-size:28px}.tl-drop.on{border-color:var(--acc);background:color-mix(in srgb,var(--acc) 7%,transparent)}
.tl-thumbs{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-top:8px}.tl-thumbs img,.tl-thumbs video{width:72px;height:72px;object-fit:cover;border-radius:10px;border:1px solid var(--line)}
.tl-or{display:flex;align-items:center;gap:10px;color:var(--muted);font-size:13px;font-weight:700;margin:0 0 14px}.tl-or:before,.tl-or:after{content:"";flex:1;height:1px;background:var(--line)}
.tl-go{width:100%;border:0;border-radius:16px;padding:17px;font-size:17px;font-weight:800;background:var(--acc);color:#fff;cursor:pointer;box-shadow:0 10px 24px color-mix(in srgb,var(--acc) 30%,transparent)}.tl-go[disabled]{opacity:.6;cursor:wait}
.tl-status{display:flex;gap:10px;align-items:center;justify-content:center;padding:16px 4px 4px;color:var(--muted);font-weight:600;text-align:center;font-size:15px;line-height:1.4}
.tl-status[hidden]{display:none}
.tl-spin{width:18px;height:18px;border-radius:50%;border:3px solid var(--line);border-top-color:var(--acc);animation:tlspin 1s linear infinite;flex:none}@keyframes tlspin{to{transform:rotate(360deg)}}
.tl-bar{height:6px;border-radius:9px;background:var(--soft);overflow:hidden;margin:10px 0 0}.tl-bar i{display:block;height:100%;width:0;background:var(--acc);transition:width .3s}
.tl-err{color:#E5484D;font-weight:600;margin:10px 0;line-height:1.45}.tl-err[hidden]{display:none}
.tl-out h2{font-size:19px;margin:24px 0 10px;letter-spacing:-.2px}
.tl-res{background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:14px 16px;margin:0 0 12px}
.tl-res-h{display:flex;justify-content:space-between;gap:10px;align-items:center;margin:0 0 8px}.tl-res-h b{font-size:15px}
.tl-tag{font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--acc)}
.tl-txt{white-space:pre-wrap;line-height:1.6;font-size:15.5px;margin:0}
.tl-res textarea{width:100%;box-sizing:border-box;font:inherit;font-size:16px!important;line-height:1.6;padding:10px 12px;border:1px solid var(--line);border-radius:12px;background:var(--surface);color:var(--text);resize:vertical}
.tl-copy,.tl-btn2{border:1px solid var(--line);background:var(--soft);color:var(--text);border-radius:10px;padding:8px 12px;font-weight:700;font-size:13.5px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px}.tl-copy.ok{background:#16a34a;border-color:#16a34a;color:#fff}
.tl-acts{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.tl-post{display:flex;gap:12px;align-items:flex-start}.tl-post img{width:64px;height:64px;border-radius:12px;object-fit:cover;flex:none}.tl-post p{margin:4px 0 0;color:var(--muted);font-size:14px;line-height:1.5}
.tl-note{background:var(--soft);border-radius:14px;padding:12px 14px;font-size:14.5px;line-height:1.55;margin:0 0 14px}
.tl-list{margin:4px 0 0;padding-left:20px;line-height:1.6;font-size:15px}
.tl-sw{display:flex;gap:10px;flex-wrap:wrap}.tl-sw span{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:700}.tl-sw i{width:24px;height:24px;border-radius:7px;display:inline-block;border:1px solid var(--line)}
.tl-beat{display:grid;grid-template-columns:70px 1fr;gap:10px;padding:11px 0;border-top:1px solid var(--line)}.tl-beat:first-child{border-top:0;padding-top:2px}.tl-beat .t{font-weight:800;color:var(--acc);font-size:13px}
.tl-beat p{margin:0 0 5px;font-size:14.5px;line-height:1.5}.tl-beat p b{color:var(--muted);font-weight:800;font-size:11.5px;text-transform:uppercase;letter-spacing:.06em;margin-right:6px}
.tl-chips{display:flex;flex-wrap:wrap;gap:8px}.tl-chips button{border:1.5px solid var(--line);background:var(--surface);color:var(--text);border-radius:999px;padding:9px 14px;font-weight:700;font-size:14px;cursor:pointer}.tl-chips button.on{background:var(--acc);border-color:var(--acc);color:#fff}
.tl-more>summary{cursor:pointer;font-weight:700;color:var(--acc);font-size:14.5px;margin:0 0 12px;list-style:none}.tl-more>summary::-webkit-details-marker{display:none}
.tl-seen{font-size:14px;color:var(--muted);line-height:1.5;white-space:pre-wrap;margin:0}
CSS;
}

function ir_tl_common_js() {
	return <<<'JS'
window.TL=(function(){var d=document;function $(s,r){return(r||d).querySelector(s)}function $$(s,r){return[].slice.call((r||d).querySelectorAll(s))}
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
function copy(t,b){var done=function(){if(!b)return;var l=b.textContent;b.classList.add('ok');b.textContent='Copied';setTimeout(function(){b.classList.remove('ok');b.textContent=l},1500)};if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(t).then(done,done)}else{var a=d.createElement('textarea');a.value=t;d.body.appendChild(a);a.select();try{d.execCommand('copy')}catch(e){}a.remove();done()}}
function bindCopy(root){$$('[data-copy]',root).forEach(function(b){b.onclick=function(){var src=b.getAttribute('data-copy'),el=src?$(src,root):null;copy(el?(el.tagName==='TEXTAREA'?el.value:el.textContent):b.getAttribute('data-text'),b)}})}
function busy(on,txt,pct){var g=$('#tlGo'),s=$('#tlStatus');if(g)g.disabled=on;if(s){s.hidden=!on;s.innerHTML='<div style="width:100%"><div style="display:flex;gap:10px;align-items:center;justify-content:center"><span class="tl-spin"></span><span>'+esc(txt||'Working...')+'</span></div>'+(pct!=null?'<div class="tl-bar"><i style="width:'+Math.round(pct*100)+'%"></i></div>':'')+'</div>'}}
function err(m){var e=$('#tlErr');if(e){e.hidden=!m;e.textContent=m||'';if(m)e.scrollIntoView({behavior:'smooth',block:'center'})}}
function ls(k,v){try{if(v===undefined)return localStorage.getItem(k)||'';localStorage.setItem(k,v)}catch(e){return ''}}
function drop(zone,input,onFiles){zone.onclick=function(){input.click()};input.onchange=function(){onFiles([].slice.call(input.files||[]))};['dragover','dragenter'].forEach(function(ev){zone.addEventListener(ev,function(e){e.preventDefault();zone.classList.add('on')})});['dragleave','drop'].forEach(function(ev){zone.addEventListener(ev,function(e){e.preventDefault();zone.classList.remove('on')})});zone.addEventListener('drop',function(e){var f=[].slice.call(e.dataTransfer.files||[]);if(f.length){onFiles(f)}})}
return{$:$,$$:$$,esc:esc,copy:copy,bindCopy:bindCopy,busy:busy,err:err,ls:ls,drop:drop}})();
JS;
}

function ir_tl_open( $title, $icon, $sub, $k1, $k2 ) {
	$smm = ! empty( $GLOBALS['ir_tl_smm'] );
	if ( $smm ) { ir_smm_open( $title ); } else { ir_pp_open( $title, 'home' ); }
	echo '<style>' . ir_tl_css() . '</style>';
	echo '<main class="pp-main narrow"><div class="tl" style="--k1:' . esc_attr( $k1 ) . ';--k2:' . esc_attr( $k2 ) . '">';
	echo '<nav class="pp-crumb"><a href="' . esc_url( $smm ? ir_smm_url() : ir_pp_url() ) . '">' . ( $smm ? 'SMM Kit' : 'AI Profit System' ) . '</a><span>›</span><span>' . esc_html( $title ) . '</span></nav>';
	echo '<div class="tl-hero"><span class="ic">' . ir_tl_svg( $icon, 28 ) . '</span><div><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $sub ) . '</p></div></div>'; // phpcs:ignore
}
function ir_tl_close( $js ) {
	echo '</div></main>';
	echo function_exists( 'ir_ai_client_tag' ) ? ir_ai_client_tag() : ''; // phpcs:ignore
	echo '<script>' . ir_tl_common_js() . '</script><script>' . $js . '</script>'; // phpcs:ignore
	ir_pp_close();
}

function ir_tl_page_comments() {
	ir_tl_open( 'Comment Writer', 'comment', 'Paste a post link. Get 5 natural comments in different styles. Pick one, copy it, post it.', '#7C3AED', '#EC4899' );
	?>
<form class="tl-card" id="tlForm">
 <label class="tl-f"><span>Link to the post</span><input id="tlUrl" type="url" inputmode="url" placeholder="https://www.instagram.com/p/..." autocomplete="off"><small>Instagram, TikTok, LinkedIn, X, Facebook, Threads or YouTube.</small></label>
 <details class="tl-more" id="tlMore"><summary>+ Paste the caption instead</summary><label class="tl-f"><span>The post's words</span><textarea id="tlCap" rows="4" placeholder="Paste the caption, or what is said in the video."></textarea></label></details>
 <label class="tl-f"><span>Your niche and what you want to be known for <em>optional, saved</em></span><input id="tlMe" placeholder="e.g. social media manager for estheticians, known for content that books clients"></label>
 <div class="tl-f"><span>Why you are commenting</span><div class="tl-chips" id="tlGoal"><button type="button" data-v="build a relationship with the creator" class="on">Build a relationship</button><button type="button" data-v="reach their audience">Reach their audience</button><button type="button" data-v="build a relationship with the creator and reach their audience">Both</button></div></div>
 <div class="tl-f"><span>Your personality in comments</span><div class="tl-chips" id="tlVibe"><button type="button" data-v="warm" class="on">Warm</button><button type="button" data-v="witty">Witty</button><button type="button" data-v="expert">Expert</button><button type="button" data-v="bold">Bold</button></div></div>
 <div class="tl-err" id="tlErr" hidden></div>
 <button class="tl-go" id="tlGo">✨ Write 5 comments</button>
 <div class="tl-status" id="tlStatus" hidden></div>
</form>
<div class="tl-out" id="tlOut"></div>
<?php
	$js = <<<'JS'
(function(){var T=TL,$=T.$,url=$('#tlUrl'),cap=$('#tlCap'),me=$('#tlMe'),goal='build a relationship with the creator',vibe=T.ls('tl_vibe')||'warm',out=$('#tlOut'),POST=null,lastUrl='';
me.value=T.ls('tl_me');
T.$$('#tlGoal button').forEach(function(b){b.onclick=function(){T.$$('#tlGoal button').forEach(function(x){x.classList.toggle('on',x===b)});goal=b.getAttribute('data-v')}});
T.$$('#tlVibe button').forEach(function(b){b.classList.toggle('on',b.getAttribute('data-v')===vibe);b.onclick=function(){T.$$('#tlVibe button').forEach(function(x){x.classList.toggle('on',x===b)});vibe=b.getAttribute('data-v');T.ls('tl_vibe',vibe)}});
var SYS="You are a social media engagement strategist with deep expertise in Instagram, TikTok and LinkedIn growth. You know that smart comments on the right posts bring followers, collaborations and clients, because the creator and their followers notice the person who left them.\n\n"+
"I want thoughtful, curiosity-sparking comments on this post that make the creator and their followers notice me and want to check out my page, without me ever promoting myself.\n\n"+
"YOUR TASK\n\n"+
"PART 1: READ THE POST\n- point: the main point of the post in one sentence.\n- creator_wants: what the creator would love to hear, beyond praise (for example: that their tip worked, that a detail was noticed, a question that lets them show more of what they know).\n- details: 2 to 4 exact things from the post (phrases, numbers, steps, names) you can refer to.\n\n"+
"PART 2: 5 COMMENTS, in this order\n1. style \"Expert insight\": agree with their point and add one quick insight that backs it up or explains why it works. It supports what they said, it does not tell them what to do.\n2. style \"Clever question\": a smart, specific question about the post that the creator would enjoy answering and that makes other people want to read the reply.\n3. style \"Relatable experience\": a short, honest, everyday moment that shows their point is true, told in a general way (for example \"the first time I tried this...\" or \"this is exactly what happens when...\"). Never mention my business, clients, results or numbers.\n4. style \"Light humour\": a friendly, light joke about the post's situation that the creator would smile at. Never at anyone's expense.\n5. style \"Different angle\": respectfully add another way to look at the same idea, starting from agreement (\"yes, and...\"), never correcting them.\n\n"+
"PART 3: PICK AND FOLLOW UP\n- best: the number of the comment most likely to get a reply from the creator, and why in one sentence.\n- reply_tip: one line on how to reply if the creator answers, to keep the conversation going.\n\n"+
"RULES\n- Every comment must clearly be about THIS post. Mention one exact detail from it.\n- Authentic and meaningful. No \"Love this!\", no \"🔥\", no empty praise.\n- Add value through an insight, an experience or a smart question. NEVER give unsolicited advice: do not tell the creator what they should do, what they missed or how to improve, and do not lecture their followers. If the post asks for tips, one short tip is fine.\n- Show expertise without self-promotion: no links, no \"check my page\", no \"DM me\", no \"as a ...\", no mention of my job, clients or services.\n- Sound casual and human, like a real person typing on their phone. Match my personality.\n- 1 to 3 short sentences each. Plain words. Contractions.\n- If the post is an offer, an announcement, a win or personal news, keep every comment warm and celebrating, still specific.\n- Only talk about what is actually in the post. Never invent what a picture or video shows.\n- No em dashes, no en dashes, no semicolons, no hashtags. At most one emoji in the whole set.\n- Never use: great post, love this, so true, couldn't agree more, this resonates, thanks for sharing, spot on, game changer, unlock, elevate, leverage, dive in, in today's world, insightful, powerful, journey.\n- Write in the same language as the post.\n\n"+
"QUALITY CHECK (run this before you reply)\n- Would the creator smile or want to reply to each comment?\n- Is each comment specific to this post, not something you could paste under any post?\n- Does any comment tell the creator what to do, correct them, or promote me? Rewrite it.\n- Is the language plain and human, with no AI tells?\nFix anything that fails before you answer.\n\n"+
'Reply with JSON only: {"understanding":{"point":"...","creator_wants":"...","details":["..."]},"comments":[{"style":"Expert insight","text":"..."},{"style":"Clever question","text":"..."},{"style":"Relatable experience","text":"..."},{"style":"Light humour","text":"..."},{"style":"Different angle","text":"..."}],"best":{"n":2,"why":"..."},"reply_tip":"..."}';
var SELF=/\b(as an? [a-z ]{2,40}\b(manager|marketer|strategist|owner|coach|designer|esthetician|consultant|expert|creator|agency)|my clients?|i help|i work with|in my work|dm me|check (out )?my|link in (my )?bio|my (page|agency|services|business|brand))\b/i;
function getPost(u){if(POST&&lastUrl===u)return Promise.resolve(POST);if(!u)return Promise.resolve({platform:'Instagram',author:'',text:'',said:'',image:''});
T.busy(true,'Reading the post...');return IRAI.post('ir_tl_post',{url:u}).then(function(j){if(!j||!j.ok){var e=new Error((j&&j.err)||'This post did not open.');e.paste=1;throw e}POST=j.post;lastUrl=u;return POST})}
$('#tlForm').onsubmit=function(e){e.preventDefault();T.err('');var u=url.value.trim(),c=cap.value.trim();if(!u&&!c){T.err('Paste the link to the post.');return}
T.ls('tl_me',me.value.trim());out.innerHTML='';
getPost(u).then(function(p){p=Object.assign({},p);if(c)p.text=c;return listen(p)}).then(function(p){if(!(p.text||'').trim()&&!(p.said||'').trim()){var e=new Error('We could not read the words of this post. Tap "Paste the caption instead" and paste them.');e.paste=1;throw e}
var long=['LinkedIn','Facebook','Website'].indexOf(p.platform)>=0,user='Platform: '+p.platform+'\n'+(p.author?'Posted by: '+p.author+'\n':'')+'Comment length: 1 to 3 short sentences each, up to '+(long?'60':'40')+' words.\n'+(p.text?'The caption:\n<<<\n'+p.text.slice(0,3000)+'\n>>>\n':'')+(p.said?'What is said in the video:\n<<<\n'+p.said.slice(0,2500)+'\n>>>\n':'')+'\nCONTEXT\n- My niche and what I want to be known for: '+(me.value.trim()||'(not given)')+' (for your understanding only, never mention it)\n- My personality in comments: '+vibe+'\n'+'- Why I am commenting: '+goal+(((p.text||'')+(p.said||'')).trim().length<80?'\nThe post has very few words. Keep every comment simple and about what is clearly in those words.':'');
T.busy(true,'Writing 5 comments...');
return IRAI.askJSON({system:SYS,user:user,max:3000,onStatus:function(s){T.busy(true,s)}},function(j){return (j.comments||j).length>=3}).then(function(j){T.busy(false);render(p,u,(j.comments||j),j.understanding,j)})}).catch(function(x){T.busy(false);T.err(x.message);if(x.paste)$('#tlMore').open=true})};
url.oninput=function(){POST=null};
/* Short caption and no captions: download the video and listen to it on the phone. */
function listen(p){var n=((p.text||'')+' '+(p.said||'')).replace(/#\S+/g,'').trim().split(/\s+/).filter(Boolean).length,C=window.IRC||{};
 if(p.said||n>=25||!p.media||!window.IRMEDIA||!C.aj||!C.ppn)return Promise.resolve(p);
 T.busy(true,'Listening to the video...');
 return fetch(C.aj+'?action=ir_pp_reel_media&n='+encodeURIComponent(C.ppn)+'&t='+encodeURIComponent(p.media),{credentials:'same-origin'}).then(function(r){return r.ok?r.blob():null}).then(function(b){if(!b)return p;
  return IRMEDIA.transcribe(b,function(f,m){T.busy(true,m,f)}).then(function(sp){if(sp&&sp.text)p.said=sp.text;return p})}).catch(function(){return p})}
function render(p,u,list,und,J){var h='',best=J&&J.best&&+J.best.n||0;
h+='<div class="tl-res tl-post">'+(p.image?'<img src="'+T.esc(p.image)+'" alt="" referrerpolicy="no-referrer" onerror="this.remove()">':'')+'<div><span class="tl-tag">'+T.esc(p.platform||'Post')+(p.author?' · @'+T.esc(String(p.author).replace(/^@/,'')):'')+'</span><p>'+T.esc((p.text||p.said||'').slice(0,220))+((p.text||p.said||'').length>220?'...':'')+'</p></div></div>';
if(und&&und.point)h+='<p class="tl-note"><b>What the post says:</b> '+T.esc(und.point)+(und.creator_wants?'<br><b>What they would love to hear:</b> '+T.esc(und.creator_wants):'')+'</p>';h+='<h2>Pick a comment</h2>';
if(best&&list[best-1]&&typeof list[best-1]==='object')list[best-1]._best=1;
var keep=list.filter(function(c){return !SELF.test(String(c&&c.text||c||''))});if(keep.length>=3)list=keep;
list.slice(0,5).forEach(function(c,i){var t=String(c.text||c||'').replace(/\s*[—–]\s*/g,', ').replace(/;/g,',').replace(/,\s*,/g,',').replace(/^I lov(e|ed) /i,'I really like ').replace(/^Lov(e|ed|ing) /i,'Really like ').replace(/\bspot on\b/gi,'just right').replace(/\bgame[- ]changer\b/gi,'big help').replace(/\bgreat post\b[,.!]?\s*/gi,'');h+='<div class="tl-res"'+(c._best?' style="border-color:var(--acc);box-shadow:0 0 0 2px color-mix(in srgb,var(--acc) 18%,transparent)"':'')+'><div class="tl-res-h"><span class="tl-tag">'+T.esc(c.style||('Option '+(i+1)))+'</span>'+(c._best?'<span class="tl-tag" style="background:var(--acc);color:#fff;border-radius:999px;padding:4px 10px">⭐ Most likely to get a reply</span>':'')+'</div><textarea id="tlC'+i+'" rows="'+Math.min(8,Math.max(2,Math.ceil(t.length/40)))+'">'+T.esc(t)+'</textarea><div class="tl-acts"><button type="button" class="tl-copy" data-copy="#tlC'+i+'">Copy</button>'+(u?'<a class="tl-btn2" href="'+T.esc(p.url||u)+'" target="_blank" rel="noopener">Open the post</a>':'')+'</div></div>'});
if(J&&(J.best&&J.best.why||J.reply_tip))h+='<div class="tl-res"><div class="tl-res-h"><b>If they reply</b></div>'+(J.best&&J.best.why?'<p class="tl-txt" style="margin:0 0 8px"><b>Why the starred one:</b> '+T.esc(J.best.why)+'</p>':'')+(J.reply_tip?'<p class="tl-txt" style="margin:0"><b>Keep it going:</b> '+T.esc(J.reply_tip)+'</p>':'')+'</div>';
h+='<p class="tl-note">Tip: change one or two words so it sounds like you, then post it within the first hour of the post going live. Early, thoughtful comments get seen the most.</p>';
out.innerHTML=h;T.bindCopy(out);out.scrollIntoView({behavior:'smooth',block:'start'})}
})();
JS;
	ir_tl_close( $js );
}

/* ---------- Flyer Remixer ---------- */

/** Free Pollinations key (enter.pollinations.ai) for the vision model. Tools > Vision AI. */
function ir_tl_vision_key() { return trim( (string) get_option( 'ir_pollen_key', '' ) ); }

/** The flyer goes to a model that can actually see it. Only used when a key is saved. */
add_action( 'wp_ajax_ir_tl_see', function () {
	check_ajax_referer( 'ir_tool', 'n' );
	if ( ! ir_tl_member() && ! ( function_exists( 'ir_smm_access' ) && ir_smm_access() ) ) { wp_send_json( [ 'ok' => false, 'err' => 'Please log in again.' ] ); }
	$key = ir_tl_vision_key();
	if ( $key === '' ) { wp_send_json( [ 'ok' => false, 'err' => 'nokey' ] ); }
	$img = (string) wp_unslash( $_POST['img'] ?? '' );
	if ( strlen( $img ) > 3000000 || ! preg_match( '#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $img ) ) { wp_send_json( [ 'ok' => false, 'err' => 'That picture is too big. Try a smaller one.' ] ); }
	$uid = get_current_user_id(); $k = 'ir_see_' . $uid; $n = (int) get_transient( $k );
	if ( $n >= 30 ) { wp_send_json( [ 'ok' => false, 'err' => 'You have remixed a lot of flyers in the last hour. Try again a bit later.' ] ); }
	set_transient( $k, $n + 1, HOUR_IN_SECONDS );
	@set_time_limit( 150 );
	$body = [
		'model' => (string) get_option( 'ir_pollen_model', 'openai' ), 'max_tokens' => 6000, 'reasoning_effort' => 'low', 'response_format' => [ 'type' => 'json_object' ],
		'messages' => [
			[ 'role' => 'system', 'content' => mb_substr( (string) wp_unslash( $_POST['sys'] ?? '' ), 0, 12000 ) ],
			[ 'role' => 'user', 'content' => [ [ 'type' => 'text', 'text' => mb_substr( (string) wp_unslash( $_POST['user'] ?? '' ), 0, 6000 ) ], [ 'type' => 'image_url', 'image_url' => [ 'url' => $img, 'detail' => 'high' ] ] ] ],
		],
	];
	$r = wp_remote_post( 'https://gen.pollinations.ai/v1/chat/completions', [ 'timeout' => 120, 'headers' => [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $key ], 'body' => wp_json_encode( $body ) ] );
	if ( is_wp_error( $r ) ) { wp_send_json( [ 'ok' => false, 'err' => 'busy' ] ); }
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	$t = (string) ( $j['choices'][0]['message']['content'] ?? '' );
	if ( wp_remote_retrieve_response_code( $r ) !== 200 || $t === '' ) { update_option( 'ir_see_last_err', [ time(), wp_remote_retrieve_response_code( $r ), mb_substr( wp_remote_retrieve_body( $r ), 0, 400 ) ], false ); wp_send_json( [ 'ok' => false, 'err' => 'busy' ] ); }
	wp_send_json( [ 'ok' => true, 'text' => $t ] );
} );

/** Text writing for every tool through the site's own key: stronger model, no wait between requests. */
add_action( 'wp_ajax_ir_tl_ai', function () {
	check_ajax_referer( 'ir_tool', 'n' );
	if ( ! ir_tl_member() && ! ( function_exists( 'ir_smm_access' ) && ir_smm_access() ) ) { wp_send_json( [ 'ok' => false, 'err' => 'Please log in again.' ] ); }
	$key = ir_tl_vision_key();
	if ( $key === '' ) { wp_send_json( [ 'ok' => false, 'err' => 'nokey' ] ); }
	$uid = get_current_user_id(); $k = 'ir_ai_n_' . $uid; $n = (int) get_transient( $k );
	if ( $n >= 150 ) { wp_send_json( [ 'ok' => false, 'err' => 'busy' ] ); }
	set_transient( $k, $n + 1, HOUR_IN_SECONDS );
	@set_time_limit( 150 );
	$body = [
		'model' => (string) get_option( 'ir_pollen_text_model', get_option( 'ir_pollen_model', 'openai' ) ), 'max_tokens' => max( 500, min( 12000, (int) ( $_POST['max'] ?? 4000 ) + 2000 ) ), 'reasoning_effort' => 'low',
		'messages' => [
			[ 'role' => 'system', 'content' => mb_substr( (string) wp_unslash( $_POST['sys'] ?? '' ), 0, 16000 ) ],
			[ 'role' => 'user', 'content' => mb_substr( (string) wp_unslash( $_POST['user'] ?? '' ), 0, 16000 ) ],
		],
	];
	$r = wp_remote_post( 'https://gen.pollinations.ai/v1/chat/completions', [ 'timeout' => 120, 'headers' => [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $key ], 'body' => wp_json_encode( $body ) ] );
	$j = is_wp_error( $r ) ? null : json_decode( wp_remote_retrieve_body( $r ), true );
	$t = (string) ( $j['choices'][0]['message']['content'] ?? '' );
	if ( $t === '' ) { update_option( 'ir_ai_last_err', [ time(), is_wp_error( $r ) ? $r->get_error_message() : wp_remote_retrieve_response_code( $r ), is_wp_error( $r ) ? '' : mb_substr( wp_remote_retrieve_body( $r ), 0, 300 ) ], false ); wp_send_json( [ 'ok' => false, 'err' => 'busy' ] ); }
	wp_send_json( [ 'ok' => true, 'text' => $t ] );
} );

add_action( 'admin_menu', function () { add_management_page( 'Vision AI', 'Vision AI', 'manage_options', 'ir-vision', 'ir_tl_vision_page' ); } );
add_action( 'admin_post_ir_vision_save', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ir_vision' ) ) { wp_die( 'Not allowed' ); }
	update_option( 'ir_pollen_key', sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) ), false );
	$m = 'saved';
	if ( ir_tl_vision_key() !== '' ) {
		$r = wp_remote_post( 'https://gen.pollinations.ai/v1/chat/completions', [ 'timeout' => 40, 'headers' => [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . ir_tl_vision_key() ], 'body' => wp_json_encode( [ 'model' => (string) get_option( 'ir_pollen_model', 'openai' ), 'max_tokens' => 20, 'messages' => [ [ 'role' => 'user', 'content' => 'Reply with the word OK.' ] ] ] ) ] );
		$m = ( ! is_wp_error( $r ) && wp_remote_retrieve_response_code( $r ) === 200 ) ? 'works' : 'fails';
	}
	wp_safe_redirect( admin_url( 'tools.php?page=ir-vision&m=' . $m ) ); exit;
} );
function ir_tl_vision_page() {
	$m = sanitize_key( $_GET['m'] ?? '' );
	echo '<div class="wrap"><h1>Vision AI</h1><p>The Flyer Remixer works best when the AI can actually see the flyer. Create a free key at <a href="https://enter.pollinations.ai/keys" target="_blank" rel="noopener">enter.pollinations.ai/keys</a> (log in with GitHub or Google), then paste it here. Without a key, the picture is read on the member\'s phone instead, which is slower and less exact. With a key, every AI tool (Content Cloner, Comment Writer, Offer Builder, Cold DM writer and the rest) also writes through it: a stronger model and no waiting between requests.</p>';
	if ( $m === 'works' ) { echo '<div class="notice notice-success"><p>Key saved and working.</p></div>'; }
	if ( $m === 'fails' ) { echo '<div class="notice notice-error"><p>Key saved, but the test failed. Check the key.</p></div>'; }
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ir_vision_save">';
	wp_nonce_field( 'ir_vision' );
	echo '<p><input type="password" name="key" value="' . esc_attr( ir_tl_vision_key() ) . '" class="regular-text" placeholder="sk_..." autocomplete="off"></p><p><button class="button button-primary">Save and test</button></p></form></div>';
}

function ir_tl_page_flyer() {
	ir_tl_open( 'Flyer Remixer', 'flyer', 'Add any flyer. Get a detailed prompt for its exact image, plus a full mega prompt that rebuilds the whole flyer in the same style and fonts, looking like a designer made it.', '#F97316', '#EC4899' );
	?>
<form class="tl-card" id="tlForm">
 <input type="file" id="tlFile" accept="image/*" hidden>
 <div class="tl-drop" id="tlDrop"><span class="e">🖼️</span><b>Tap to add the flyer</b><span>JPG, PNG or WebP. A screenshot works too.</span><div class="tl-thumbs" id="tlThumb"></div></div>
 <div class="tl-f"><span>What do you want?</span><div class="tl-chips" id="tlMode"><button type="button" data-v="same" class="on">Recreate it exactly</button><button type="button" data-v="mine">Make it for my business</button></div></div>
 <div id="tlMine" hidden>
  <div class="tl-two"><label class="tl-f"><span>Your niche or business type</span><input id="tlNiche" placeholder="e.g. esthetician, real estate agent"></label><label class="tl-f"><span>Business name <em>optional</em></span><input id="tlBiz" placeholder="e.g. Glow by Amaka"></label></div>
  <label class="tl-f"><span>What should your flyer say or promote? <em>optional</em></span><textarea id="tlOffer" rows="2" placeholder="e.g. Black Friday: 30% off facials, book by 30 November, call 0810 000 0000"></textarea></label>
  <label class="tl-f"><span>Your brand colours <em>optional, leave empty to keep the original colours</em></span><input id="tlColors" placeholder="e.g. soft pink and gold"></label>
 </div>
 <details class="tl-more"><summary>Add details the AI might miss (optional)</summary><label class="tl-f"><span>Anything about the picture or fonts you know</span><input id="tlPic" placeholder="e.g. the headline font is Bebas Neue, the woman is holding a serum"></label></details>
 <div class="tl-err" id="tlErr" hidden></div>
 <button class="tl-go" id="tlGo">✨ Remix this flyer</button>
 <div class="tl-status" id="tlStatus" hidden></div>
</form>
<div class="tl-out" id="tlOut"></div>
<style>.tl-mega{white-space:pre-wrap;line-height:1.6;font-size:14.5px;margin:0;max-height:420px;overflow:auto;background:var(--soft);border-radius:12px;padding:12px 14px}.tl-fonts td{padding:8px 6px;border-top:1px solid var(--line);font-size:14px;vertical-align:top}.tl-fonts tr:first-child td{border-top:0}.tl-fonts td b{display:block}.tl-fonts td small{color:var(--muted)}</style>
<?php
	$cfg = [ 'see' => ir_tl_vision_key() !== '' ];
	$js = 'window.IRFL=' . wp_json_encode( $cfg ) . ';' . <<<'JS'
(function(){var T=TL,$=T.$,file=null,out=$('#tlOut'),READ=null,mode='same';
$('#tlNiche').value=T.ls('tl_niche');$('#tlBiz').value=T.ls('tl_biz');$('#tlColors').value=T.ls('tl_colors');
T.$$('#tlMode button').forEach(function(b){b.onclick=function(){mode=b.getAttribute('data-v');T.$$('#tlMode button').forEach(function(x){x.classList.toggle('on',x===b)});$('#tlMine').hidden=mode!=='mine'}});
T.drop($('#tlDrop'),$('#tlFile'),function(fs){file=fs.filter(function(f){return /^image\//.test(f.type)})[0]||null;READ=null;$('#tlThumb').innerHTML=file?'<img src="'+URL.createObjectURL(file)+'" alt="">':'';if(!file&&fs.length)T.err('That file is not a picture. Use a JPG, PNG or WebP.');if(file)read()});
function read(){if(READ)return READ;var f=file;READ=IRMEDIA.toCanvas(f,1400).then(function(c){var r=[];/* one job at a time so phones never run out of memory */return IRMEDIA.readImage(c).catch(function(){return []}).then(function(b){r[0]=b;try{IRMEDIA.stopOcr()}catch(e){}r[1]=IRMEDIA.palette(c);return IRMEDIA.pose?IRMEDIA.pose(c):{people:0,text:''}}).then(function(ps){r[2]=ps;return r}).then(function(r){var s=Math.min(1,1024/Math.max(c.width,c.height)),x=document.createElement('canvas');x.width=Math.round(c.width*s);x.height=Math.round(c.height*s);x.getContext('2d').drawImage(c,0,0,x.width,x.height);return{w:c.width,h:c.height,blocks:r[0],pal:r[1],pose:r[2]||{people:0,text:''},jpg:x.toDataURL('image/jpeg',0.86),canvas:x}})});READ.catch(function(){READ=null});return READ}
function ratio(w,h){var r=w/h,opts=[['1:1',1],['4:5',0.8],['9:16',0.5625],['16:9',1.7778],['3:4',0.75],['2:3',0.6667],['A4 portrait',0.707]];opts.sort(function(a,b){return Math.abs(a[1]-r)-Math.abs(b[1]-r)});return opts[0][0]}
function sys(seeing){return "You are an art director, senior graphic designer and commercial photographer. You reverse engineer flyers and write prompts for AI image tools (ChatGPT images, Midjourney, Ideogram, Leonardo, Canva AI) that recreate them so well that nobody can tell they were not made by a human designer in Photoshop or Canva.\n"+
(seeing?"You can SEE the flyer. Study it closely: the photo or artwork, every piece of text, the fonts, the layout, the colours, the shapes and effects. Measurements read from the image are also given to help you.\n\n":"You cannot see the flyer, but you get a detailed description of the picture from a vision model, every text block read from it with its position and relative size (100 = the biggest text), and its exact colours. Trust these facts and rebuild it precisely.\n\n")+
"Write these, in JSON:\n\n"+
"1. image_prompt: a detailed prompt (180 to 260 words) for ONLY the main image inside the flyer (the photo, person, product or illustration), so the user can generate that exact image. Describe it like a photographer's shot list: the subject (age range, skin tone, hair, expression, pose, hands, what they hold or do), wardrobe and styling, products and props with their exact look, the setting and background, the light (direction, softness, colour temperature), the camera angle and distance, lens (for example 85mm f/2.0 on a full frame camera), depth of field, the colour grade and the crop, and where empty space is left for the text. If the image is an illustration or 3D render, describe its exact art style instead. No text in this image.\n   POSE IS THE MOST IMPORTANT PART: write a dedicated, precise pose description inside the image_prompt: framing and crop, where the person stands in the frame, body angle, shoulder line, head tilt and turn, eye direction, exactly where each arm and each hand is (and what each hand touches or holds), finger position if visible, and the legs. When an EXACT POSE is given, copy every detail of it. Use the viewer's left and right. The person must end up in exactly this pose.\n"+
"2. mega_prompt: one complete, ready to paste prompt (450 to 750 words) that recreates the WHOLE flyer as a finished design. Structure it with short labelled sections in this order: FORMAT (shape, size in pixels, print or social). STYLE (the design style in plain words, for example luxury minimal beauty flyer, bold Nigerian event flyer, clean corporate). LAYOUT (zone by zone from top to bottom: what sits where, alignment, margins, how much space each zone takes). IMAGE (a short version of the image prompt and exactly where and how the image is placed: full bleed, cut out, inside a circle, behind text). TEXT (every text element in reading order, the exact words in double quotes, then its font style and the closest real font name, weight, case, letter spacing, relative size, colour with hex, alignment and any effect such as outline, shadow, highlight bar or script accent). GRAPHICS (shapes, lines, badges, frames, icons, patterns, textures, overlays, with colours and positions). COLOURS (the full palette with hex codes and where each is used). FINISH (rules that make it look designed by a human: a clear grid, consistent margins, real typography with correct kerning and line spacing, sharp clean text exactly as written with correct spelling, flat print-ready colours, subtle paper or print texture only if the original has it, no AI artefacts).\n"+
"   Keep the flyer's style, layout and fonts exactly. Change only what the user asks for.\nSELF-CONTAINED: the image_prompt and mega_prompt will be pasted into ChatGPT or another tool WITHOUT the flyer. They must work completely on their own. Never mention the original, a reference, an uploaded or attached image, recreating, copying or matching anything, and never ask for an image. Describe everything directly as a brand new design. Start image_prompt with \"Generate this image now:\" and mega_prompt with \"Generate this flyer design now:\".\n"+
"3. negative_prompt: the AI tells to avoid: garbled or misspelled text, extra letters, warped logos, plastic or airbrushed skin, waxy faces, extra or broken fingers, glowing edges, over-sharpening, oversaturated colours, fake bokeh, random symbols, cluttered layout, cheap stock look, 3D clip art, watermark.\n"+
"4. fonts: for every text style, what the font looks like and the closest free Google Font plus one alternative. Be specific: say condensed, geometric, didone serif, brush script and so on.\n"+
"5. palette: the main colours with hex codes and where they are used.\n"+
"6. texts: every text element in reading order with its role, the original words, and the words used in your mega prompt.\n"+
"7. tips: 4 to 6 practical tips to get a real, designed result (which tool to use, generate the image first then add text in Canva with the named fonts, pick the most natural of 4, fix any misspelled text in Canva).\n\n"+
"Rules for realism: never use the words hyperrealistic, ultra detailed, 8k, 4k, masterpiece, cinematic, epic, award winning, octane, unreal engine, ethereal or magical. Describe real light, real skin texture with pores and fine hairs, true-to-life colours, natural poses, believable places. For graphic design flyers describe them as print or Canva designs with clean vector shapes, not 3D renders.\n"+
"Text rules: copy the original text exactly when recreating. When making it for the user's business, rewrite each text element with the same role and about the same length, using the user's details. Use [DATE], [PRICE], [PHONE], [ADDRESS] placeholders for any detail the user did not give. Never invent prices, dates, phone numbers, results or claims.\n"+
"Precision rules: the text sizes you are given are relative (100 = the biggest text). Turn them into real pixel font sizes for the flyer size (the biggest headline is usually 8 to 13 percent of the flyer height). Use one camera and one lens only. The aspect ratio and pixel size must match everywhere. Never contradict yourself. Before you reply, check every section against the flyer facts and fix anything that does not match.\n"+
"Write in plain, clear English. No em dashes or en dashes.\n"+
'Reply with JSON only: {"summary":"2 sentences describing the flyer and its style","image_prompt":"...","mega_prompt":"...","negative_prompt":"...","aspect_ratio":"4:5","fonts":[{"element":"Headline","looks_like":"...","google_font":"...","alternative":"..."}],"palette":[{"hex":"#000000","use":"background"}],"texts":[{"role":"Headline","original":"...","new":"..."}],"tips":["..."]}'}
function details(r,vis){var b=r.blocks,p=r.pal,n=$('#tlNiche').value.trim(),u='';
 if(vis&&vis.caption)u+='WHAT THE PICTURE SHOWS (from a vision model)\n'+vis.caption+(vis.objects?'\nThings it found in the image: '+vis.objects:'')+'\n\n';
 if(r.pose&&r.pose.text)u+='EXACT POSE OF THE MAIN PERSON (measured from the photo with a pose detector, left and right are the viewer\'s; copy every detail)\n'+r.pose.text+(r.pose.people>1?'\nThere are '+r.pose.people+' people in the photo; this is the biggest one.':'')+'\n\n';
 u+='MEASUREMENTS FROM THE FLYER\nSize: '+r.w+' x '+r.h+' pixels, closest ratio '+ratio(r.w,r.h)+'.\nMain colours (share of the image): '+p.colors.map(function(c){return c.hex+' '+c.share+'%'}).join(', ')+'.\nBrightness '+p.bright+'/100, saturation '+p.sat+'/100, '+(p.spread>40?'photo-heavy':p.spread>18?'mix of photo and flat design':'mostly flat graphic design')+'.\n'+
 'Text read from it, top to bottom:\n'+(b.length?b.map(function(x,i){return (i+1)+'. "'+x.text+'" at '+x.pos+', size '+x.size}).join('\n'):'(no readable text found)')+'\n';
 if($('#tlPic').value.trim())u+='The user adds: '+$('#tlPic').value.trim()+'\n';
 if(mode==='mine'){u+='\nMAKE IT FOR MY BUSINESS\nNiche or business type: '+n+'\n'+($('#tlBiz').value.trim()?'Business name: '+$('#tlBiz').value.trim()+'\n':'')+($('#tlOffer').value.trim()?'What it should say or promote: '+$('#tlOffer').value.trim()+'\n':'')+($('#tlColors').value.trim()?'My brand colours: '+$('#tlColors').value.trim()+' (use these instead of the original colours, keep everything else)\n':'Keep the original colours.\n')+'Keep the same style, layout and fonts. The main image should show the same kind of shot, adapted to my niche.\n'}
 else u+='\nRECREATE IT EXACTLY: same image, same text word for word, same fonts, colours and layout.\n';
 return u}
function server(r){var f=new FormData();f.append('action','ir_tl_see');f.append('n',IRC.n);f.append('img',r.jpg);f.append('sys',sys(true));f.append('user',details(r,null));
 return fetch(IRC.aj,{method:'POST',body:f,credentials:'same-origin'}).then(function(x){return x.json()}).then(function(j){if(!j||!j.ok)throw new Error((j&&j.err)||'busy');var t=String(j.text||'').replace(/^```(json)?|```$/g,'').trim(),a=t.indexOf('{'),z=t.lastIndexOf('}');var d=JSON.parse(t.slice(a,z+1));if(!d.mega_prompt)throw new Error('busy');return d})}
function onDevice(r){return IRMEDIA.see(dataBlob(r.jpg),function(p,m){T.busy(true,m,p*0.8)}).catch(function(){return null}).then(function(vis){T.busy(true,'Writing your prompts...',0.85);
 return IRAI.askJSON({system:sys(false),user:details(r,vis),max:7000,onStatus:function(s){T.busy(true,s,0.9)}},function(j){return j.mega_prompt&&j.image_prompt})})}
function dataBlob(u){var b=atob(u.split(',')[1]),a=new Uint8Array(b.length);for(var i=0;i<b.length;i++)a[i]=b.charCodeAt(i);return new Blob([a],{type:'image/jpeg'})}
$('#tlForm').onsubmit=function(e){e.preventDefault();T.err('');if(!file){T.err('Add the flyer first.');return}
 if(mode==='mine'&&!$('#tlNiche').value.trim()){T.err('Tell us your niche or business type.');return}
 T.ls('tl_niche',$('#tlNiche').value.trim());T.ls('tl_biz',$('#tlBiz').value.trim());T.ls('tl_colors',$('#tlColors').value.trim());out.innerHTML='';
 T.busy(true,'Reading the flyer...',0.05);
 read().then(function(r){T.busy(true,IRFL.see?'Studying the flyer...':'Looking at the picture...',0.15);
  var go=IRFL.see?server(r).catch(function(){return onDevice(r)}):onDevice(r);
  return go.then(function(d){T.busy(false);if(!d.aspect_ratio)d.aspect_ratio=ratio(r.w,r.h);render(d)})}).catch(function(x){T.busy(false);T.err(x.message)})};
function block(title,id,text,note,big){return '<div class="tl-res"><div class="tl-res-h"><b>'+title+'</b><button type="button" class="tl-copy" data-copy="#'+id+'">Copy</button></div>'+(note?'<p class="tl-txt" style="color:var(--muted);font-size:13.5px;margin:0 0 8px">'+note+'</p>':'')+'<p class="'+(big?'tl-mega':'tl-txt')+'" id="'+id+'">'+T.esc(text)+'</p></div>'}
function A(x){return Array.isArray(x)?x:x?[x]:[]}
function clean(s){return String(s||'').replace(/\b(the |this )?(original|reference|uploaded|attached) (flyer|image|photo|picture|design)\b/gi,'the design').replace(/\b(recreate|replicate|match|copy) (it|this|the design)( exactly)?\b/gi,'design it').replace(/[—–]/g,', ').replace(/\b(hyper-?realistic|ultra[- ]detailed|8k|4k|masterpiece|award[- ]winning|octane render|unreal engine)\b,?\s*/gi,'').trim()}
function render(d){var h='';d.fonts=A(d.fonts);d.palette=A(d.palette).map(function(c){return typeof c==='string'?{hex:c}:c||{}});d.texts=A(d.texts).map(function(l){return typeof l==='string'?{new:l}:l||{}});d.tips=A(d.tips);
 var ar=d.aspect_ratio&&/\d+:\d+/.test(d.aspect_ratio)?d.aspect_ratio.match(/\d+:\d+/)[0]:'';
 if(d.summary)h+='<p class="tl-note">'+T.esc(d.summary)+'</p>';
 h+='<h2>1. The image in the flyer</h2>'+block('Image prompt','tlP1',clean(d.image_prompt)+(ar?'\n\nAspect ratio '+ar+'.':''),'Paste it into ChatGPT, Midjourney (add --style raw) or Leonardo on its own. No picture needed. Then place the image in Canva.');
 var ex='Generate this image now. Keep the exact pose, body angle, head tilt, hand positions, camera angle and framing of the person in the attached photo, and create a brand new, realistic photo with these details:\n\n'+clean(d.image_prompt).replace(/^Generate this image now:\s*/i,'')+(ar?'\n\nAspect ratio '+ar+'.':'');
 h+=block('Exact same pose (attach the photo)','tlP1x',ex,'Want the pose 100% identical? In ChatGPT, attach the flyer (or a screenshot of just the person) and paste this. It copies the pose and makes a new image.');
 h+='<h2>2. Mega prompt for the whole flyer</h2>'+block('Mega prompt','tlP2',clean(d.mega_prompt),'Paste it into ChatGPT or Ideogram on its own. No picture needed: it describes the whole design.',true);
 if(d.negative_prompt)h+=block('Avoid (negative prompt)','tlP3',d.negative_prompt);
 if(d.fonts.length){h+='<h2>3. Fonts</h2><div class="tl-res"><table class="tl-fonts" style="width:100%;border-collapse:collapse">'+d.fonts.map(function(f){f=typeof f==='string'?{looks_like:f}:f||{};return '<tr><td style="width:34%"><b>'+T.esc(f.element||'')+'</b><small>'+T.esc(f.looks_like||'')+'</small></td><td><b>'+T.esc(f.google_font||'')+'</b>'+(f.alternative?'<small>or '+T.esc(f.alternative)+'</small>':'')+'</td></tr>'}).join('')+'</table></div>'}
 if(d.palette.length)h+='<h2>4. Colours</h2><div class="tl-res"><div class="tl-sw">'+d.palette.map(function(c){return '<span><i style="background:'+T.esc(c.hex)+'"></i>'+T.esc(c.hex)+(c.use?' · '+T.esc(c.use):'')+'</span>'}).join('')+'</div></div>';
 if(d.texts.length){h+='<h2>5. The text</h2><div class="tl-res">';d.texts.forEach(function(l,i){h+='<div class="tl-beat"><span class="t">'+T.esc(l.role||'')+'</span><div>'+(l.original&&l.original!==l.new?'<p><b>Was</b>'+T.esc(l.original)+'</p>':'')+'<p><b>'+(mode==='mine'?'Yours':'Text')+'</b><span id="tlL'+i+'">'+T.esc(l.new||l.original||'')+'</span> <button type="button" class="tl-copy" data-copy="#tlL'+i+'" style="padding:4px 9px;font-size:12px">Copy</button></p></div></div>'});h+='</div>'}
 if(d.tips.length)h+='<h2>Tips</h2><div class="tl-res"><ul class="tl-list">'+d.tips.map(function(t){return '<li>'+T.esc(t)+'</li>'}).join('')+'</ul></div>';
 out.innerHTML=h;T.bindCopy(out);out.scrollIntoView({behavior:'smooth',block:'start'})}
})();
JS;
	ir_tl_close( $js );
}

function ir_tl_page_clone() {
	ir_tl_open( 'Content Cloner', 'clone', 'Paste any viral video or upload it. Get the original script word for word, then your own version to record: remixed for your niche, or reworded so it never looks copied.', '#2563EB', '#22D3EE' );
	?>
<form class="tl-card" id="tlForm">
 <label class="tl-f"><span>Paste the link</span><input id="tlUrl" type="url" inputmode="url" placeholder="TikTok or Instagram link" autocomplete="off"></label>
 <div class="tl-or">or</div>
 <input type="file" id="tlFile" accept="video/*,image/*" multiple hidden>
 <div class="tl-drop" id="tlDrop"><span class="e">📲</span><b>Upload the video or pictures</b><span>Any video, meme, skit or carousel. For a carousel, add every slide.</span><div class="tl-thumbs" id="tlThumb"></div></div>
 <label class="tl-f"><span>Your niche or brand</span><input id="tlNiche" placeholder="e.g. social media manager for estheticians"><small>Saved for next time.</small></label>
 <label class="tl-f"><span>What should your version be about? <em>optional</em></span><textarea id="tlTopic" rows="2" placeholder="Leave empty to keep the same topic, just reworded so it does not look copied. Or type your own topic, e.g. why estheticians need Reels"></textarea></label>
 <label class="tl-f"><span>Anything to include? <em>optional</em></span><input id="tlExtra" placeholder="e.g. end with: DM me GLOW"></label>
 <div class="tl-err" id="tlErr" hidden></div>
 <button class="tl-go" id="tlGo">✨ Get the scripts</button>
 <div class="tl-status" id="tlStatus" hidden></div>
</form>
<div class="tl-out" id="tlOut"></div>
<?php
	$js = <<<'JS'
(function(){var T=TL,$=T.$,files=[],out=$('#tlOut'),C=window.IRC||{};
$('#tlNiche').value=T.ls('tl_niche');
T.drop($('#tlDrop'),$('#tlFile'),function(fs){files=fs.filter(function(f){return /^(video|image)\//.test(f.type)}).slice(0,12);$('#tlThumb').innerHTML=files.map(function(f){var u=URL.createObjectURL(f);return /^video/.test(f.type)?'<video src="'+u+'" muted playsinline></video>':'<img src="'+u+'" alt="">'}).join('');if(files.length)$('#tlUrl').value=''});
$('#tlUrl').oninput=function(){if(this.value.trim()){files=[];$('#tlThumb').innerHTML='';$('#tlFile').value=''}};
function step(m,p){T.busy(true,m,p)}
function cap(p,ms){return Promise.race([p,new Promise(function(r){setTimeout(function(){r(null)},ms)})])}
/* Video → what is said + what is shown, all read on this phone. */
function readVideo(blob,spokenSegs){
  var getSpeech=spokenSegs?Promise.resolve({text:spokenSegs.map(function(s){return s.text}).join(' '),segs:spokenSegs}):IRMEDIA.transcribe(blob,function(f,m){step(m,0.1+0.5*f)}).catch(function(){return{text:'',segs:[]}});
  return getSpeech.then(function(sp){step('Watching the video for on-screen text...',0.62);
    return cap(IRMEDIA.watch(blob,{spoken:sp.text,onProgress:function(f,m){step(m,0.62+0.3*f)}}).catch(function(){return null}),25000).then(function(w){IRMEDIA.stopOcr();return{speech:sp,seen:w}})})}
function getLink(u){step('Opening the post...',0.05);
  return Promise.all([IRAI.post('ir_pp_reel_fetch',{url:u},C.ppn),IRAI.post('ir_tl_post',{url:u}).catch(function(){return null})]).then(function(r){var j=r[0],p=r[1]&&r[1].ok?r[1].post:{};
    if(!j||!j.ok){throw new Error((j&&j.err)||'We could not open that post. Save the video to your phone and upload it here.')}
    var info={platform:j.platform||p.platform||'',author:j.author||p.author||'',caption:p.text||'',dur:j.duration||0};
    var segs=j.vtt?IRMEDIA.vttToSegs(j.vtt):null;
    if(!j.media){return{info:info,speech:{text:segs?segs.map(function(s){return s.text}).join(' '):'',segs:segs||[]},seen:null}}
    step('Downloading the video...',0.1);
    return fetch(C.aj+'?action=ir_pp_reel_media&n='+encodeURIComponent(C.ppn)+'&t='+encodeURIComponent(j.media),{credentials:'same-origin'}).then(function(r){return r.ok?r.blob():null}).then(function(b){
      if(!b){if(segs)return{info:info,speech:{text:segs.map(function(s){return s.text}).join(' '),segs:segs},seen:null};throw new Error('The app did not let us download this video. Save it to your phone and upload it here.')}
      return readVideo(b,segs).then(function(x){x.info=info;return x})})})}
function getFiles(){var vids=files.filter(function(f){return /^video/.test(f.type)}),imgs=files.filter(function(f){return /^image/.test(f.type)});
  if(vids.length){if(vids[0].size>300*1048576)return Promise.reject(new Error('That video is too big. Use one under 300MB.'));return readVideo(vids[0],null).then(function(x){x.info={platform:'Uploaded video',caption:''};return x})}
  var slides=[],chain=Promise.resolve();
  imgs.forEach(function(f,i){chain=chain.then(function(){step('Reading slide '+(i+1)+' of '+imgs.length+'...',0.1+0.8*i/imgs.length);return IRMEDIA.toCanvas(f,1400).then(function(c){return IRMEDIA.readImage(c).then(function(b){var pal=IRMEDIA.palette(c);slides.push({text:b.map(function(x){return x.text}).join(' / '),blocks:b,colors:pal.colors.slice(0,3).map(function(c2){return c2.hex}).join(' ')})})})})});
  return chain.then(function(){IRMEDIA.stopOcr();return{info:{platform:imgs.length>1?'Carousel ('+imgs.length+' slides)':'Image post',caption:''},slides:slides}})}
var SYS="You rewrite short-form video scripts so they can be recorded straight away.\n"+
"You get the ORIGINAL script, numbered line by line, exactly as it was spoken (or shown on screen, or written on each slide).\n\n"+
"Write the NEW script line by line: one new line for every original line, same order, about the same number of words per line (within 20 percent), same rhythm, same strength of hook, same structure, same kind of payoff and call to action. The new script must be as long as the original when spoken.\n\n"+
"First work out the original's niche and topic. Then pick the mode:\n- REWORD: the user's niche is the same as the original's (or very close) and they gave no different topic. Keep the meaning and the points, but say every line in new words, with fresh examples and phrasing, so it never looks copied.\n- REMIX: the user's niche is different, or they gave their own topic. Keep the same idea and angle and the job of every line (hook, problem, point, example, story beat, turn, call to action), but move it fully into the user's niche and topic: their customers, their examples, their words. Nothing from the original niche may remain.\nAlways speak as the user, in their real role. If the user offers a service to businesses (for example a social media manager for restaurants), talk to those business owners and use examples like my client's restaurant, never pretend to own the business. Set same_topic to true for REWORD, false for REMIX.\n\n"+
"Also decide the format: talking_head, voiceover_broll, text_on_screen, skit, tutorial, carousel or other. If it is NOT a plain talking head, write film_note: 1 to 3 short, simple sentences on what to show while the script plays. For a talking head leave film_note empty.\n\n"+
"Rules: natural spoken English that sounds like a real person talking, contractions, short sentences. Keep the user's 'also include' line where it fits best, usually the end. Never invent results, numbers, testimonials or credentials, use [YOUR RESULT] style placeholders. No em dashes or en dashes. No captions, no hashtags, no timestamps.\n\n"+
'Reply with JSON only: {"format":"talking_head","same_topic":true,"film_note":"","lines":[{"n":1,"say":"..."}]}';
function fmt(t){t=Math.max(0,Math.round(t||0));return Math.floor(t/60)+':'+('0'+t%60).slice(-2)}
function sentences(t){return (String(t||'').replace(/\s+/g,' ').trim().match(/[^.!?…]+[.!?…]+["'”’)]*|[^.!?…]+$/g)||[]).map(function(x){return x.trim()}).filter(function(x){return /[A-Za-z0-9]/.test(x)})}
function words(t){return (String(t||'').match(/\S+/g)||[]).length}
function secs(t){return Math.max(1,Math.round(words(t)/2.6))}
/* The original, word for word: built from what was actually said or shown, never rewritten. */
function original(m){var sp=m.speech||{},w=m.seen,L=[],kind='speech';
  if(m.slides){kind='slides';m.slides.forEach(function(x,k){L.push('Slide '+(k+1)+': '+(x.text||'(picture only)'))})}
  else if(sp.text&&words(sp.text)>=4&&!(w&&w.textLed)){L=sentences(sp.text)}
  else if(w&&w.cards&&w.cards.length){kind='screen';L=w.cards.map(function(c){return c.text})}
  else if(sp.text){L=sentences(sp.text)}
  return{kind:kind,lines:L}}
$('#tlForm').onsubmit=function(e){e.preventDefault();T.err('');var u=$('#tlUrl').value.trim(),t=$('#tlTopic').value.trim(),n=$('#tlNiche').value.trim();
if(!u&&!files.length){T.err('Paste a link or upload the video or pictures.');return}if(!n&&!t){T.err('Add your niche so your version fits you.');return}
if(u&&/youtu\.?be/i.test(u)){T.err('YouTube does not let websites download Shorts. Save the Short to your phone and upload it here.');return}
T.ls('tl_niche',n);out.innerHTML='';var O=null,M=null;
(u?getLink(u):getFiles()).then(function(m){M=m;O=original(m);
  if(!O.lines.length){throw new Error('We could not find any words in this video. If it only has music, upload a video where someone talks or text appears on screen.')}
  var i=m.info||{},L=['THE ORIGINAL ('+(O.kind==='slides'?'carousel':O.kind==='screen'?'text on screen, no talking':'spoken')+(m.seen&&m.seen.desc?', '+m.seen.desc:'')+'):'];
  O.lines.forEach(function(x,k){L.push((k+1)+'. '+x)});
  if(i.caption)L.push('\nOriginal caption, for context only: '+i.caption.slice(0,600));
  L.push('\nME\nMy niche or brand: '+(n||'(not given)')+'\nMy topic: '+(t||'(none given: if my niche matches the original, reword it; if not, move the same idea into my niche)')+($('#tlExtra').value.trim()?'\nAlso include: '+$('#tlExtra').value.trim():'')+'\n\nWrite exactly '+O.lines.length+' new lines, numbered 1 to '+O.lines.length+'.');
  step('Writing your script...',0.95);var want=O.lines.length;
  var okFn=function(j){return Array.isArray(j.lines)&&j.lines.length>=Math.max(1,want-Math.floor(want/10))};
  return IRAI.askJSON({system:SYS,user:L.join('\n'),max:Math.min(9000,2500+want*120),onStatus:function(s){step(s,0.95)}},okFn).then(function(d){
    var got={};(d.lines||[]).forEach(function(x,k){var no=+(x&&x.n)||k+1,say=String(x&&x.say||x||'').trim();if(say)got[no]=say});
    var miss=[];for(var k=1;k<=want;k++){if(!got[k])miss.push(k)}
    if(!miss.length)return d;
    step('Finishing the last lines...',0.97);
    return IRAI.askJSON({system:SYS,user:L.join('\n')+'\n\nYou already wrote these lines:\n'+Object.keys(got).map(function(k){return k+'. '+got[k]}).join('\n')+'\n\nNow write ONLY lines '+miss.join(', ')+', matching the originals with the same numbers.',max:2500},function(j){return Array.isArray(j.lines)}).then(function(d2){(d2.lines||[]).forEach(function(x){var no=+(x&&x.n);if(no&&!got[no]&&x.say)got[no]=String(x.say).trim()});d.lines=Object.keys(got).sort(function(a,b){return a-b}).map(function(k){return{n:+k,say:got[k]}});return d}).catch(function(){return d})
  }).then(function(d){T.busy(false);render(d,O,M)});
}).catch(function(x){T.busy(false);try{IRMEDIA.stopOcr()}catch(e2){}T.err(x.message||'Something went wrong. Please try again.')})};
function render(d,O,m){var lines=(d.lines||[]).map(function(x){return String(x&&x.say||x||'').trim()}).filter(Boolean),h='';
  var orig=O.lines.join('\n'),mine=lines.join('\n'),dur=(m.info&&m.info.dur)||(m.seen&&m.seen.dur)||0;
  var lab={talking_head:'Talking head',voiceover_broll:'Voiceover with b-roll',text_on_screen:'Text on screen',skit:'Skit',tutorial:'Tutorial',carousel:'Carousel'}[d.format]||'';
  h+='<h2>The original script</h2><div class="tl-res"><div class="tl-res-h"><b>Word for word'+(O.kind==='speech'?' · about '+(dur?Math.round(dur):secs(orig))+' seconds':'')+'</b><button type="button" class="tl-copy" data-copy="#tlO">Copy</button></div><p class="tl-txt" id="tlO">'+T.esc(orig)+'</p></div>';
  h+='<h2>Your script'+(lab?' <span class="tl-tag" style="margin-left:6px">'+T.esc(lab)+'</span>':'')+'</h2>';
  if(d.film_note&&d.format!=='talking_head')h+='<div class="tl-note"><b>What to film:</b> '+T.esc(d.film_note)+'</div>';
  h+='<div class="tl-res"><div class="tl-res-h"><b>Ready to record · about '+secs(mine)+' seconds</b><button type="button" class="tl-copy" data-copy="#tlS">Copy</button></div><p class="tl-txt" id="tlS" style="font-size:17px;line-height:1.7">'+T.esc(mine)+'</p></div>';
  h+='<p class="tl-note">Read it once out loud and change any word that does not sound like you. Then record.</p>';
  out.innerHTML=h;T.bindCopy(out);out.scrollIntoView({behavior:'smooth',block:'start'})}
})();
JS;
	ir_tl_close( $js );
}

/* ---------- Grand Slam Offer Builder ---------- */

function ir_tl_page_offer() {
	ir_tl_open( 'Grand Slam Offer Builder', 'offer', 'Tell us what you do. Get a complete Grand Slam Offer built on Alex Hormozi\'s $100M Offers method: the stack, bonuses, guarantee, price and name.', '#0EA5E9', '#6366F1' );
	ir_tl_offer_form();
	ir_tl_close( ir_tl_offer_js() );
}

/** The offer builder inside the Cold DM System (/members/offer/). */
function ir_tl_offer_embed() {
	echo '<style>' . ir_tl_css() . '.pb-main .tl{margin:0}</style><div class="tl" style="--k1:#0EA5E9;--k2:#6366F1">';
	ir_tl_offer_form();
	echo '</div><script>' . ir_tl_common_js() . '</script><script>' . ir_tl_offer_js() . '</script>'; // phpcs:ignore
}

function ir_tl_offer_form() {
	?>
<form class="tl-card" id="tlForm">
 <label class="tl-f"><span>What do you do, or what services do you offer?</span><textarea id="ofWhat" rows="4" placeholder="e.g. I'm a social media manager. I create content, edit Reels, write captions and manage Instagram for estheticians and med spas."></textarea></label>
 <div class="tl-two"><label class="tl-f"><span>Who do you serve? <em>optional</em></span><input id="ofWho" placeholder="e.g. estheticians in the US"></label><label class="tl-f"><span>What do you charge now? <em>optional</em></span><input id="ofPrice" placeholder="e.g. $500 a month"></label></div>
 <div class="tl-two"><label class="tl-f"><span>Currency</span><select id="ofCur"></select></label><label class="tl-f"><span>Proof you have <em>optional</em></span><input id="ofProof" placeholder="e.g. got a spa 40 bookings in 30 days"></label></div>
 <div class="tl-err" id="tlErr" hidden></div>
 <button class="tl-go" id="tlGo">🏆 Build my Grand Slam Offer</button>
 <div class="tl-status" id="tlStatus" hidden></div>
</form>
<div class="tl-out" id="tlOut"></div>
<style>.of-sec{background:var(--surface);border:1px solid var(--line);border-radius:18px;padding:16px 18px;margin:0 0 12px}.of-sec h3{margin:0 0 10px;font-size:16.5px;letter-spacing:-.2px;display:flex;align-items:center;gap:8px}.of-sec h3 i{font-style:normal;width:26px;height:26px;border-radius:8px;background:var(--soft);display:grid;place-items:center;font-size:13px;color:var(--acc);font-weight:800}
.of-hero{border-radius:20px;padding:20px;margin:0 0 14px;color:#fff;background:linear-gradient(135deg,#0B1B4D,#1E3A8A 60%,#4338CA)}.of-hero small{display:block;text-transform:uppercase;letter-spacing:.1em;font-weight:800;font-size:12px;color:#BFD3FF}.of-hero h2{margin:6px 0 8px!important;font-size:24px!important;line-height:1.2;color:#fff}.of-hero p{margin:0;color:#DCE6FF;line-height:1.55;font-size:15.5px}
.of-price{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}.of-price div{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);border-radius:12px;padding:8px 12px}.of-price b{display:block;font-size:20px}.of-price span{font-size:12.5px;color:#C7D1E8}
.of-tab{width:100%;border-collapse:collapse;font-size:14.5px}.of-tab td{padding:9px 6px;border-top:1px solid var(--line);vertical-align:top;line-height:1.45}.of-tab tr:first-child td{border-top:0}.of-tab td:last-child{text-align:right;font-weight:800;white-space:nowrap}
.of-sc{display:grid;grid-template-columns:1fr 1fr;gap:8px}.of-sc div{background:var(--soft);border-radius:12px;padding:10px 12px;font-size:14px;line-height:1.4}.of-sc b{display:block;font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)}.of-sc em{font-style:normal;font-weight:800;color:var(--acc)}
.of-pp{display:grid;gap:8px}.of-pp div{display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:14px;line-height:1.45;padding:10px 0;border-top:1px solid var(--line)}.of-pp div:first-child{border-top:0}.of-pp b{display:block;font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}</style>
<?php
}

function ir_tl_offer_js() {
	return <<<'JS'
(function(){var T=TL,$=T.$,out=$('#tlOut');
var CUR=[['USD','$'],['NGN','₦'],['GBP','£'],['EUR','€'],['CAD','CA$'],['AUD','A$'],['GHS','GH₵'],['KES','KSh'],['ZAR','R'],['INR','₹'],['AED','AED ']];
$('#ofCur').innerHTML=CUR.map(function(c){return '<option value="'+c[0]+'">'+c[0]+' ('+c[1].trim()+')</option>'}).join('');
$('#ofWhat').value=T.ls('of_what');$('#ofWho').value=T.ls('of_who');$('#ofCur').value=T.ls('of_cur')||'USD';
var SYS="You are an offer strategist who applies Alex Hormozi's $100M Offers method exactly. You build a Grand Slam Offer: an offer so good people feel stupid saying no.\n\n"+
"Work through these steps for the user's business:\n"+
"1. Market check: rate the market 1 to 10 on massive pain, purchasing power, easy to target and growing, and say if they should niche down (give a sharper niche if helpful).\n"+
"2. Dream outcome: the specific result the buyer really wants, in their words.\n"+
"3. Value equation: Value = (Dream Outcome x Perceived Likelihood of Achievement) / (Time Delay x Effort and Sacrifice). Say how this offer raises the top and cuts the bottom.\n"+
"4. Problems: list 8 to 10 real obstacles the buyer faces before, during and after getting the result, each written as the buyer's doubt.\n"+
"5. Solutions: turn each problem into a 'How to' solution, then pick the delivery vehicle (done for you, done with you, do it yourself, 1 to 1, group, template, software, checklist, audit).\n"+
"6. Trim and stack: keep the high value, low cost items. Build the offer stack of 5 to 8 named components, each with a believable standalone value.\n"+
"7. Bonuses: 3 to 5 named bonuses that each remove a specific objection, with a value.\n"+
"8. Guarantee: pick the strongest type that fits (unconditional, conditional, anti-guarantee or implied, performance based), and write it word for word.\n"+
"9. Scarcity and urgency: honest limits (client spots, cohort dates, seasonal bonuses, price rise), never fake ones.\n"+
"10. Price: a premium price (never compete on price), the total stack value as the anchor, and payment options.\n"+
"11. Name: 3 names using the MAGIC formula (Magnetic reason why, Avatar, Goal, Interval, Container), e.g. 'Free 30-Day Glow-Up Bookings Challenge for Estheticians'.\n"+
"12. The pitch: the full offer in 4 to 6 sentences, plus a 1-line version for a bio or DM.\n\n"+
"Rules: be specific to this business and niche, never generic. Use the user's currency for every number. Never invent results, testimonials or credentials; use the user's proof only if given, otherwise use [YOUR RESULT] placeholders. Plain, confident English. No em dashes or en dashes.\n"+
'Reply with JSON only: {"niche":"...","market":{"pain":8,"power":7,"target":9,"growing":8,"advice":"..."},"dream":"...","value":{"outcome":"...","likelihood":"...","time":"...","effort":"..."},"problems":[{"problem":"...","solution":"...","vehicle":"..."}],"stack":[{"name":"...","what":"...","value":"..."}],"bonuses":[{"name":"...","removes":"objection it removes","value":"..."}],"guarantee":{"type":"...","text":"..."},"scarcity":"...","urgency":"...","price":{"total_value":"...","price":"...","payment":"...","why":"..."},"names":["...","...","..."],"pitch":"...","short":"..."}';
function li(a){return(a||[]).map(function(x){return '<li>'+T.esc(x)+'</li>'}).join('')}
function A(x){return Array.isArray(x)?x:x?[x]:[]}
function render(d){var h='',m=d.market||{},v=d.value||{},p=d.price||{},g=d.guarantee||{};d.problems=A(d.problems);d.stack=A(d.stack);d.bonuses=A(d.bonuses);d.names=A(d.names);
 h+='<div class="of-hero"><small>Your Grand Slam Offer</small><h2 id="ofName">'+T.esc(d.names[0]||'Your offer')+'</h2><p>'+T.esc(d.short||'')+'</p><div class="of-price">'+(p.total_value?'<div><span>Total value</span><b>'+T.esc(p.total_value)+'</b></div>':'')+(p.price?'<div><span>Your price</span><b>'+T.esc(p.price)+'</b></div>':'')+(p.payment?'<div><span>Payment</span><b style="font-size:14px">'+T.esc(p.payment)+'</b></div>':'')+'</div></div>';
 h+='<div class="of-sec"><h3><i>1</i>The pitch</h3><p class="tl-txt" id="ofPitch">'+T.esc(d.pitch||'')+'</p><div class="tl-acts"><button type="button" class="tl-copy" data-copy="#ofPitch">Copy pitch</button><button type="button" class="tl-copy" data-text="'+T.esc(d.short||'')+'">Copy 1-line version</button></div></div>';
 h+='<div class="of-sec"><h3><i>2</i>The offer stack</h3><table class="of-tab">'+d.stack.map(function(s){return '<tr><td><b>'+T.esc(s.name)+'</b><br><span style="color:var(--muted)">'+T.esc(s.what||'')+'</span></td><td>'+T.esc(s.value||'')+'</td></tr>'}).join('')+'</table></div>';
 h+='<div class="of-sec"><h3><i>3</i>Bonuses</h3><table class="of-tab">'+d.bonuses.map(function(s){return '<tr><td><b>'+T.esc(s.name)+'</b><br><span style="color:var(--muted)">Removes: '+T.esc(s.removes||'')+'</span></td><td>'+T.esc(s.value||'')+'</td></tr>'}).join('')+'</table></div>';
 h+='<div class="of-sec"><h3><i>4</i>Guarantee <span class="tl-tag" style="margin-left:auto">'+T.esc(g.type||'')+'</span></h3><p class="tl-txt" id="ofG">'+T.esc(g.text||'')+'</p><div class="tl-acts"><button type="button" class="tl-copy" data-copy="#ofG">Copy</button></div></div>';
 h+='<div class="of-sec"><h3><i>5</i>Scarcity and urgency</h3><p class="tl-txt"><b>Scarcity:</b> '+T.esc(d.scarcity||'')+'</p><p class="tl-txt" style="margin-top:8px"><b>Urgency:</b> '+T.esc(d.urgency||'')+'</p></div>';
 h+='<div class="of-sec"><h3><i>6</i>Price</h3><p class="tl-txt">'+T.esc(p.why||'')+'</p></div>';
 h+='<div class="of-sec"><h3><i>7</i>Name options</h3><ul class="tl-list">'+li(d.names)+'</ul></div>';
 h+='<div class="of-sec"><h3><i>8</i>Problems you solve</h3><div class="of-pp">'+d.problems.map(function(x){return '<div><p style="margin:0"><b>Their doubt</b>'+T.esc(x.problem||'')+'</p><p style="margin:0"><b>Your solution · '+T.esc(x.vehicle||'')+'</b>'+T.esc(x.solution||'')+'</p></div>'}).join('')+'</div></div>';
 h+='<div class="of-sec"><h3><i>9</i>Dream outcome and value equation</h3><p class="tl-txt"><b>Dream outcome:</b> '+T.esc(d.dream||'')+'</p><div class="of-sc" style="margin-top:12px"><div><b>Dream outcome ↑</b>'+T.esc(v.outcome||'')+'</div><div><b>Likelihood ↑</b>'+T.esc(v.likelihood||'')+'</div><div><b>Time delay ↓</b>'+T.esc(v.time||'')+'</div><div><b>Effort ↓</b>'+T.esc(v.effort||'')+'</div></div></div>';
 h+='<div class="of-sec"><h3><i>10</i>Market check</h3><div class="of-sc"><div><b>Pain</b><em>'+(m.pain||'?')+'/10</em></div><div><b>Can pay</b><em>'+(m.power||'?')+'/10</em></div><div><b>Easy to reach</b><em>'+(m.target||'?')+'/10</em></div><div><b>Growing</b><em>'+(m.growing||'?')+'/10</em></div></div>'+(m.advice?'<p class="tl-txt" style="margin-top:10px">'+T.esc(m.advice)+'</p>':'')+'</div>';
 out.innerHTML=h;T.bindCopy(out);out.scrollIntoView({behavior:'smooth',block:'start'})}
$('#tlForm').onsubmit=function(e){e.preventDefault();T.err('');var w=$('#ofWhat').value.trim();if(w.length<10){T.err('Tell us what you do in a sentence or two.');return}
 T.ls('of_what',w);T.ls('of_who',$('#ofWho').value.trim());T.ls('of_cur',$('#ofCur').value);out.innerHTML='';T.busy(true,'Building your offer...');
 var user='What I do: '+w+'\n'+($('#ofWho').value.trim()?'Who I serve: '+$('#ofWho').value.trim()+'\n':'')+($('#ofPrice').value.trim()?'What I charge now: '+$('#ofPrice').value.trim()+'\n':'')+'Currency for every price: '+$('#ofCur').value+'\n'+($('#ofProof').value.trim()?'My real proof: '+$('#ofProof').value.trim()+'\n':'No proof given, use placeholders.\n');
 IRAI.askJSON({system:SYS,user:user,max:6000,onStatus:function(s){T.busy(true,s)},onText:function(t){T.busy(true,'Building your offer... '+Math.min(99,Math.round(t.length/55))+'%')}},function(j){return j.stack&&j.pitch}).then(function(d){T.busy(false);render(d)}).catch(function(x){T.busy(false);T.err(x.message)})};
})();
JS;
}

/* ---------- SMM Pricing Calculator ---------- */

function ir_tl_page_pricing() {
	ir_tl_open( 'SMM Pricing Calculator', 'price', 'Get 3 ready-to-sell social media packages priced on real market rates in your country, then download a branded price card for your client.', '#16A34A', '#0EA5E9' );
	?>
<form class="tl-card" id="tlForm">
 <div class="tl-f"><span>Your level</span><select id="prLvl"><option value="beg">Beginner · 0 to 1 year</option><option value="mid" selected>Intermediate · 1 to 3 years, some results</option><option value="pro">Expert · 3+ years, case studies</option><option value="agency">Agency · team and proven system</option></select></div>
 <div class="tl-two"><label class="tl-f"><span>Niche you serve</span><input id="prNiche" placeholder="e.g. estheticians, restaurants, real estate"></label><label class="tl-f"><span>Your clients' market and currency</span><select id="prMkt"></select><small>Prices use real rates in this market, not a currency conversion.</small></label></div>
 <div class="tl-f"><span>Platforms you manage</span><div class="tg" id="prPlat"></div><small>Tap to pick, or type a platform and press Add.</small></div>
 <div class="tl-f"><span>Services you offer</span><div class="tg" id="prSvc"></div><small>Pick the core services, and type any other service you offer (for example "Google Business Profile" or "Email newsletter").</small></div>
 <div class="tl-two" style="grid-template-columns:1fr 1fr 1fr"><label class="tl-f"><span>Feed posts / week</span><input id="prPosts" type="number" min="0" max="30" value="3"></label><label class="tl-f"><span>Reels / week</span><input id="prReels" type="number" min="0" max="30" value="2"></label><label class="tl-f"><span>Stories / week</span><input id="prStories" type="number" min="0" max="50" value="5"></label></div>
 <label class="tl-f"><span>Also show prices in <em>optional</em></span><select id="prAlso"><option value="">No second currency</option></select></label>
 <div class="tl-err" id="tlErr" hidden></div>
 <button class="tl-go" id="tlGo">💰 Price my packages</button>
 <div class="tl-status" id="tlStatus" hidden></div>
</form>
<div class="tl-out" id="tlOut"></div>
<?php echo ir_tl_pkg_css() . ir_tl_tag_js(); // phpcs:ignore ?>
<?php
	$js = ir_tl_pdf_js() . <<<'JS'
(function(){var T=TL,$=T.$,$$=T.$$,out=$('#tlOut');
/* Monthly retainer for a standard package (1 to 2 platforms, about 3 posts and 1 Reel a week, captions, scheduling, light engagement),
   by level, in each market's own currency. From published rate surveys and local price lists, rounded. */
var MK={
 us:{n:'USA',c:'USD',lv:{beg:[500,1000],mid:[1000,2500],pro:[2500,5000],agency:[4000,9000]}},
 ca:{n:'Canada',c:'CAD',lv:{beg:[700,1400],mid:[1400,3300],pro:[3300,6500],agency:[5000,12000]}},
 uk:{n:'United Kingdom',c:'GBP',lv:{beg:[300,600],mid:[600,1500],pro:[1500,3000],agency:[2500,8000]}},
 eu:{n:'Europe',c:'EUR',lv:{beg:[350,700],mid:[700,1700],pro:[1700,3500],agency:[3000,8000]}},
 au:{n:'Australia',c:'AUD',lv:{beg:[800,1500],mid:[1500,3500],pro:[3500,6500],agency:[5000,12000]}},
 ae:{n:'UAE / Gulf',c:'AED',lv:{beg:[1500,3500],mid:[3500,8000],pro:[8000,16000],agency:[15000,35000]}},
 ng:{n:'Nigeria',c:'NGN',lv:{beg:[40000,100000],mid:[100000,250000],pro:[250000,600000],agency:[500000,1500000]}},
 gh:{n:'Ghana',c:'GHS',lv:{beg:[800,2000],mid:[2000,5000],pro:[5000,10000],agency:[8000,20000]}},
 ke:{n:'Kenya',c:'KES',lv:{beg:[15000,30000],mid:[30000,60000],pro:[60000,120000],agency:[80000,200000]}},
 za:{n:'South Africa',c:'ZAR',lv:{beg:[3000,7000],mid:[7000,15000],pro:[15000,30000],agency:[25000,60000]}},
 in:{n:'India',c:'INR',lv:{beg:[10000,25000],mid:[25000,60000],pro:[60000,120000],agency:[100000,300000]}},
 gl:{n:'International clients (remote)',c:'USD',lv:{beg:[300,800],mid:[800,2000],pro:[2000,4000],agency:[3500,8000]}}};
var SYM={USD:'$',CAD:'CA$',GBP:'£',EUR:'€',AUD:'A$',AED:'AED ',NGN:'₦',GHS:'GH₵',KES:'KSh ',ZAR:'R',INR:'₹'};
var RATES={USD:1,CAD:1.37,GBP:0.78,EUR:0.92,AUD:1.52,AED:3.67,NGN:1550,GHS:15,KES:129,ZAR:18.2,INR:84};
var LVN={beg:'Beginner',mid:'Intermediate',pro:'Expert',agency:'Agency'};
var CORE=[['design','Branded posts and carousels'],['video','Reels editing'],['copy','Captions and hashtags'],['schedule','Scheduling and posting'],['stories','Stories'],['community','Comments and DM replies'],['strategy','Strategy and content calendar'],['report','Monthly report'],['ads','Paid ads management'],['shoot','Monthly content shoot'],['outreach','Lead generation outreach'],['ugc','UGC or influencer sourcing']];
$('#prMkt').innerHTML=Object.keys(MK).map(function(k){return '<option value="'+k+'">'+MK[k].n+' ('+MK[k].c+')</option>'}).join('');
$('#prAlso').innerHTML+=Object.keys(SYM).map(function(k){return '<option value="'+k+'">'+k+' ('+SYM[k].trim()+')</option>'}).join('');
var saved={};try{saved=JSON.parse(T.ls('pr_form')||'{}')}catch(e){}
var plat=TagField($('#prPlat'),['Instagram','TikTok','Facebook','LinkedIn','X','YouTube','Pinterest','Threads','Snapchat','Google Business Profile'],saved.plat||['Instagram'],'Pick or type a platform');
var svc=TagField($('#prSvc'),CORE.map(function(c){return c[1]}),saved.svc||['Branded posts and carousels','Reels editing','Captions and hashtags','Scheduling and posting','Stories','Strategy and content calendar','Monthly report'],'Pick or type a service');
['prLvl','prNiche','prMkt','prPosts','prReels','prStories','prAlso'].forEach(function(id){if(saved[id]!=null)$('#'+id).value=saved[id]});
fetch('https://open.er-api.com/v6/latest/USD').then(function(r){return r.json()}).then(function(j){if(j&&j.rates)Object.keys(RATES).forEach(function(k){if(j.rates[k])RATES[k]=j.rates[k]})}).catch(function(){});
function rnd(x){if(x<=0)return 0;var step=x<100?5:x<1000?10:x<10000?50:x<100000?500:x<1000000?5000:50000;return Math.round(x/step)*step}
function nice(x,cur){return SYM[cur]+rnd(x).toLocaleString('en-US')}
/* Monthly hours of work in a package. Extra platforms add repurposing time. Custom services count 4 hours a month each. */
function hours(p,on,custom,plats){var w=4.33,rep=1+0.2*Math.max(0,plats.length-1),H=[],add=function(n,h){if(h>0)H.push([n,h])};
 var posts=p.posts*w,reels=p.reels*w,stories=p.stories*w;
 if(on.design)add(Math.round(posts)+' branded posts and carousels',posts*rep);
 if(on.video&&reels)add(Math.round(reels)+' edited Reels',reels*2*rep);
 if(on.copy)add('Captions and hashtags for '+Math.round(posts+reels)+' posts',(posts+reels)*0.3);
 if(on.schedule)add('Scheduling and posting on '+plats.length+' platform'+(plats.length>1?'s':''),(posts+reels+stories)*0.08*plats.length);
 if(on.stories&&stories)add(Math.round(stories)+' Stories',stories*0.25);
 if(on.community)add('Comments and DM replies, 5 days a week',6.5*plats.length);
 if(on.strategy)add('Monthly strategy and content calendar',4);
 if(on.report)add('Monthly analytics report and review call',2.5);
 if(on.ads)add('Paid ads management (ad spend paid by client)',10);
 if(on.shoot)add('Monthly content shoot (photo and video)',6);
 if(on.outreach)add('Lead generation outreach',10);
 if(on.ugc)add('UGC or influencer sourcing',5);
 (custom||[]).forEach(function(c){add(c,4)});
 return H}
function sum(H){return H.reduce(function(a,x){return a+x[1]},0)}
var STD=sum(hours({posts:3,reels:1,stories:3},{design:1,video:1,copy:1,schedule:1,stories:1,community:1},[],['Instagram','Facebook']));
function pkgs(){var mk=MK[$('#prMkt').value],lvl=$('#prLvl').value,band=mk.lv[lvl],cur=mk.c,plats=plat.get(),picked=svc.get(),on={},custom=[];if(!plats.length)plats=['Instagram'];
 picked.forEach(function(n){var c=CORE.filter(function(x){return x[1].toLowerCase()===n.toLowerCase()})[0];if(c)on[c[0]]=1;else custom.push(n)});
 var nk=nicheK($('#prNiche').value),mid=Math.sqrt(band[0]*band[1])*nk.k,base={posts:+$('#prPosts').value||0,reels:+$('#prReels').value||0,stories:+$('#prStories').value||0};
 var core={design:on.design,video:on.video,copy:on.copy,schedule:on.schedule,stories:on.stories};
 var tiers=[{vol:0.5,on:core,custom:[],plats:plats.slice(0,1),lift:1},{vol:1,on:on,custom:custom,plats:plats,lift:1.05},{vol:1.5,on:Object.assign({},on,{strategy:1,report:1,community:1}),custom:custom,plats:plats,lift:1.15}];
 var res=tiers.map(function(t,i){var p={posts:Math.max(1,Math.round(base.posts*t.vol)),reels:Math.round(base.reels*t.vol),stories:Math.round(base.stories*t.vol)};if(i===0&&!p.reels&&base.reels)p.reels=1;
  var H=hours(p,t.on,t.custom,t.plats),h=sum(H),v=rnd(Math.max(band[0]*0.5,mid*Math.pow(h/STD,0.85)*t.lift));
  H=H.map(function(x){return [x[0],x[1],nice(v*x[1]/h,cur)]});
  return {plats:t.plats,items:H,hours:Math.round(h),value:v,price:nice(v,cur),setup:nice(v*(i===0?0.3:0.5),cur),quarter:nice(v*3*0.9,cur)}});
 res.band=[nice(band[0],cur),nice(band[1],cur)];res.mk=mk;res.cur=cur;res.lvl=lvl;res.nk=nk;res.custom=custom;res.hr=mid/STD;res.add=addons(res);return res}
/* Niches with bigger budgets pay more for the same work; smaller-budget niches pay less. */
function nicheK(n){n=(n||'').toLowerCase();var R=[[/med ?spa|medspa|aesthetic clinic|plastic|cosmetic|dermatolog|dental|dentist|orthodont|clinic|medical|doctor|chiropract|physio|surgeon/,1.25,'clinics and medical brands usually have bigger marketing budgets'],[/real ?estate|realtor|propert|law|legal|attorney|lawyer|financ|accountant|insurance|mortgage|b2b|saas|software|tech|consult/,1.3,'one client in this niche is worth a lot, so they pay more for marketing'],[/luxury|hotel|resort|jewel|wedding|venue|interior/,1.15,'premium brands expect premium content'],[/church|ministry|non-?profit|charity|student|school|pupil|thrift/,0.85,'budgets in this niche are usually smaller']];for(var i=0;i<R.length;i++){if(R[i][0].test(n))return{k:R[i][1],why:R[i][2]}}return{k:1,why:''}}
function addons(P){var cur=P.cur,r=P.hr*1.5,c=P.custom||[],L=[['Branded post or carousel','each',1],['Edited Reel or short video','each',2],['Story','each',0.25],['Caption and hashtags only','each',0.3],['Comments and DM replies','per platform, per month',6.5],['Strategy and content calendar','per month',4],['Analytics report and review call','per month',2.5],['Paid ads management','per month, plus ad spend',10],['Content shoot (photo and video)','per session',6],['Lead generation outreach','per month',10],['UGC or influencer sourcing','per creator',5],['Profile optimisation','one-off',3]];
 c.forEach(function(x){L.push([x,'per month',4])});return L.map(function(x){return[x[0],x[1],nice(Math.max(x[2]*r,1),cur),rnd(x[2]*r)]})}
function also(v,cur){var a=$('#prAlso').value;if(!a||a===cur)return '';return ' <small class="also">≈ '+nice(v/RATES[cur]*RATES[a],a)+'</small>'}
function fallbackNames(n){var N=(n||'').trim().replace(/s$/,'');N=N?N.charAt(0).toUpperCase()+N.slice(1):'';
 return ['Spark','Momentum','Signature'].map(function(x,i){return{name:N?N+' '+x:x,tagline:['Show up consistently and look professional.','Grow your audience and turn followers into clients.','A done-for-you presence that brings in clients every month.'][i]}})}
function render(P,names){var cur=P.cur,lv=LVN[P.lvl],h='<div class="tl-note">Market rate for '+(/^[aeiou]/i.test(lv)?'an ':'a ')+lv.toLowerCase()+' with clients in '+P.mk.n+': <b>'+P.band[0]+' to '+P.band[1]+' a month</b> for a standard package. Your packages are scaled from this by the work they include.'+(P.nk.k!==1?' <b>Niche adjustment: '+(P.nk.k>1?'+':'')+Math.round((P.nk.k-1)*100)+'%</b>, because '+P.nk.why+'.':'')+'</div><div class="pk-grid">';
 P.forEach(function(k,i){var n=names[i]||{};h+='<div class="pk'+(i===1?' hot':'')+'">'+(i===1?'<span class="badge">Most popular</span>':'')+'<h3>'+T.esc(n.name)+'</h3><div class="tg2">'+T.esc(n.tagline||'')+'</div><div class="pr">'+k.price+'<small> / month</small>'+also(k.value,cur)+'</div><div class="su">'+k.setup+' setup · or '+k.quarter+' for 3 months paid upfront (save 10%)</div><ul>'+
  k.items.map(function(x){return '<li><span>'+T.esc(x[0])+'</span><em>'+x[2]+'</em></li>'}).join('')+'<li><span>'+T.esc(k.plats.join(', '))+'</span></li></ul><div class="ft">About '+k.hours+' hours of work a month</div><a class="tl-btn2" style="margin-top:12px;justify-content:center" href="../invoice/?pkg='+i+'">Invoice this package →</a></div>'});
 h+='</div>';
 h+='<div class="tl-res"><div class="tl-res-h"><b>Add-ons and single services</b></div><p class="tl-txt" style="font-size:13.5px;color:var(--muted);margin:0 0 6px">For clients who want one thing, or something extra on top of a package. Single services cost more than the same work inside a package.</p><table class="ad-tab">'+P.add.map(function(a){return '<tr><td>'+T.esc(a[0])+'<small>'+T.esc(a[1])+'</small></td><td>'+a[2]+'</td></tr>'}).join('')+'</table></div>';
 h+='<div class="tl-res"><div class="tl-res-h"><b>Your branded price card (PDF)</b></div>'+
  '<div class="tl-two"><label class="tl-f"><span>Business name</span><input id="pcBiz" placeholder="e.g. Reubx Social"></label><label class="tl-f"><span>Your name</span><input id="pcName" placeholder="e.g. Igho Reuben"></label></div>'+
  '<div class="tl-two"><label class="tl-f"><span>Prepared for <em>client name, optional</em></span><input id="pcFor" placeholder="e.g. Amaka at Glow by Amaka"></label><label class="tl-f"><span>Contact</span><input id="pcContact" placeholder="hello@reubx.com · @reubx"></label></div>'+
  '<div class="tl-two"><label class="tl-f"><span>Brand colour</span><input id="pcColor" type="color" value="#1660E8" style="height:48px;padding:6px"></label><label class="tl-f"><span>Logo <em>optional</em></span><input id="pcLogo" type="file" accept="image/png,image/jpeg"></label></div>'+
  '<button type="button" class="tl-go" id="pcGo" style="margin-top:4px">⬇️ Download price card PDF</button></div>';
 var txt=P.map(function(k,i){var n=names[i]||{};return n.name+' ('+k.price+'/month, '+k.setup+' setup)\n'+(n.tagline||'')+'\n'+k.items.map(function(x){return '- '+x[0]}).join('\n')+'\n- Platforms: '+k.plats.join(', ')}).join('\n\n');
 h+='<div class="tl-res"><div class="tl-res-h"><b>Send as text</b><button type="button" class="tl-copy" data-copy="#pkTxt">Copy all packages</button></div><p class="tl-txt" id="pkTxt" style="font-size:14px">'+T.esc(txt)+'</p></div>';
 h+='<details class="tl-res pk-how"><summary style="cursor:pointer;font-weight:700">How these prices were worked out</summary><p>We start from the typical monthly retainer for your level in '+P.mk.n+', in '+cur+' (a standard package: 1 to 2 platforms, about 3 posts and 1 Reel a week, captions, scheduling and light engagement). Each package is scaled up or down by the hours of work it includes, so more content, platforms and services cost more. Services you typed in yourself count as about 4 hours a month each. The bigger packages carry a small premium for priority and results.</p><table>'+P.map(function(k,i){return '<tr><td>'+T.esc((names[i]||{}).name)+'</td><td>'+k.hours+' h</td><td>'+k.price+'</td></tr>'}).join('')+'</table><p style="margin-top:10px">Based on published rate surveys and local price lists (HeyOrca 2024 pricing survey, UK and US pricing guides, Nigerian, Kenyan and Indian agency price lists), rounded. Raise your prices 10 to 20% each time you add a new case study.</p></details>';
 out.innerHTML=h;T.bindCopy(out);
 ['pcBiz','pcName','pcContact','pcColor'].forEach(function(id){var e=$('#'+id),v=T.ls('pc_'+id);if(v)e.value=v;e.onchange=function(){T.ls('pc_'+id,e.value)}});
 $('#pcGo').onclick=function(){var b=this,f=$('#pcLogo').files[0];b.disabled=true;b.textContent='Making your PDF...';
  (f?IRPDF.img(f):Promise.resolve(null)).then(function(logo){return IRPDF.priceCard({biz:$('#pcBiz').value.trim()||'Social Media Packages',name:$('#pcName').value.trim(),forWho:$('#pcFor').value.trim(),contact:$('#pcContact').value.trim(),color:$('#pcColor').value,logo:logo,niche:$('#prNiche').value.trim(),cur:cur,P:P,names:names})})
  .then(function(){b.disabled=false;b.textContent='⬇️ Download price card PDF'}).catch(function(e){b.disabled=false;b.textContent='⬇️ Download price card PDF';T.err('The PDF could not be made: '+e.message)})};
 try{localStorage.setItem('pr_last',JSON.stringify({cur:cur,pk:P.map(function(k,i){return{name:(names[i]||{}).name,price:k.value,items:k.items.map(function(x){return x[0]}),plats:k.plats}}),add:P.add}))}catch(e){}}
$('#tlForm').onsubmit=function(e){e.preventDefault();T.err('');var niche=$('#prNiche').value.trim();
 if(!svc.get().length){T.err('Add at least one service you offer.');return}
 if(!(+$('#prPosts').value||+$('#prReels').value)){T.err('Add how many posts or Reels you make a week.');return}
 var sv={plat:plat.get(),svc:svc.get()};['prLvl','prNiche','prMkt','prPosts','prReels','prStories','prAlso'].forEach(function(id){sv[id]=$('#'+id).value});T.ls('pr_form',JSON.stringify(sv));
 var P=pkgs(),names=fallbackNames(niche);render(P,names);out.scrollIntoView({behavior:'smooth',block:'start'});
 if(!window.IRAI||!niche)return;
 T.busy(true,'Naming your packages for '+niche+'...');
 IRAI.askJSON({system:'You name social media management packages for a freelancer so they feel premium and speak to one niche. Each name is 1 to 3 words, memorable, tied to the outcome that niche wants (for estheticians: glow, bookings, booked out; for restaurants: tables, cravings). Names must clearly rise in value from first to third. Taglines are 6 to 12 words about the result, not the tasks. No em dashes. Reply with JSON only: {"packages":[{"name":"...","tagline":"..."},{"name":"...","tagline":"..."},{"name":"...","tagline":"..."}]}',
  user:'Niche: '+niche+'\nPackage 1: '+P[0].items.map(function(x){return x[0]}).join('; ')+'\nPackage 2: '+P[1].items.map(function(x){return x[0]}).join('; ')+'\nPackage 3: '+P[2].items.map(function(x){return x[0]}).join('; '),max:1500,onStatus:function(s){T.busy(true,s)}},function(j){return (j.packages||[]).length===3})
  .then(function(j){T.busy(false);render(P,j.packages)}).catch(function(){T.busy(false)})};
})();
JS;
	ir_tl_close( $js );
}

function ir_tl_pkg_css() {
	return '<style>.pk-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:0 0 14px}@media(max-width:820px){.pk-grid{grid-template-columns:1fr}}
.pk{position:relative;background:var(--surface);border:1px solid var(--line);border-radius:20px;padding:18px;display:flex;flex-direction:column}.pk.hot{border:2px solid var(--acc);box-shadow:0 14px 34px color-mix(in srgb,var(--acc) 18%,transparent)}
.pk .badge{position:absolute;top:-11px;left:18px;background:var(--acc);color:#fff;font-size:11.5px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;padding:4px 10px;border-radius:99px}
.pk h3{margin:4px 0 2px;font-size:19px;letter-spacing:-.3px}.pk .tg2{color:var(--muted);font-size:14px;line-height:1.45;min-height:40px}
.pk .pr{font-size:30px;font-weight:800;letter-spacing:-1px;margin:12px 0 0}.pk .pr small{font-size:14px;font-weight:600;color:var(--muted);letter-spacing:0}.pk .pr .also{display:block;font-size:13px}.pk .su{font-size:13px;color:var(--muted);margin:2px 0 12px}
.pk ul{list-style:none;margin:0;padding:0;display:grid;gap:7px;font-size:14.5px;line-height:1.4}.pk li{display:flex;gap:8px}.pk li:before{content:"✓";color:var(--acc);font-weight:800}.pk li span{flex:1}.pk li em{font-style:normal;color:var(--muted);font-size:13px;white-space:nowrap}
.ad-tab{width:100%;border-collapse:collapse;font-size:14.5px}.ad-tab td{padding:9px 4px;border-top:1px solid var(--line);vertical-align:top}.ad-tab tr:first-child td{border-top:0}.ad-tab td:last-child{text-align:right;font-weight:800;white-space:nowrap}.ad-tab small{display:block;color:var(--muted);font-size:12.5px}
.pk .ft{margin-top:auto;padding-top:14px;font-size:12.5px;color:var(--muted)}
.pk-how{font-size:14px;line-height:1.55}.pk-how table{width:100%;border-collapse:collapse;margin-top:8px}.pk-how td{padding:6px 4px;border-top:1px solid var(--line)}.pk-how td:last-child{text-align:right;white-space:nowrap}</style>';
}

/* A form field that holds several choices as tags: pick from a list, or type your own. */
function ir_tl_tag_js() {
	return '<style>.tg{position:relative;border:1px solid var(--line);border-radius:12px;background:var(--surface);padding:6px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;min-height:50px;box-sizing:border-box}.tg:focus-within{border-color:var(--acc);box-shadow:0 0 0 3px color-mix(in srgb,var(--acc) 15%,transparent)}
.tg .t{display:inline-flex;align-items:center;gap:6px;background:color-mix(in srgb,var(--acc) 12%,var(--surface));color:var(--text);border-radius:9px;padding:6px 8px 6px 10px;font-size:14px;font-weight:600}.tg .t button{border:0;background:none;color:var(--muted);font-size:17px;line-height:1;cursor:pointer;padding:0 2px}
.tg input{flex:1;min-width:150px;border:0!important;outline:0;background:transparent;font:inherit;font-size:16px!important;padding:8px 6px!important;color:var(--text)}.tg .add{border:0;background:var(--acc);color:#fff;border-radius:9px;padding:8px 12px;font-weight:700;font-size:14px;cursor:pointer}
.tg .dd{position:absolute;left:-1px;right:-1px;top:calc(100% + 4px);background:var(--surface);border:1px solid var(--line);border-radius:12px;box-shadow:0 14px 30px rgba(0,0,0,.12);z-index:30;max-height:260px;overflow:auto;padding:6px}.tg .dd[hidden]{display:none}
.tg .dd button{display:flex;width:100%;align-items:center;gap:10px;border:0;background:none;text-align:left;padding:10px;border-radius:9px;font:inherit;font-size:15px;color:var(--text);cursor:pointer}.tg .dd button:hover,.tg .dd button.hl{background:var(--soft)}.tg .dd button i{width:18px;height:18px;border-radius:5px;border:1.5px solid var(--line);flex:none;font-style:normal;font-size:12px;display:grid;place-items:center}.tg .dd button.on i{background:var(--acc);border-color:var(--acc);color:#fff}</style>'
		. '<script>window.TagField=function(el,opts,sel,ph){var V=(sel||[]).slice();el.innerHTML=\'<span class="tags"></span><input placeholder="\'+ph+\'" autocomplete="off"><button type="button" class="add">Add</button><div class="dd" hidden></div>\';el.style.cursor="text";var tags=el.querySelector(".tags"),inp=el.querySelector("input"),dd=el.querySelector(".dd");tags.style.display="contents";
function esc(s){return String(s).replace(/[&<>"]/g,function(c){return{"&":"&amp;","<":"&lt;",">":"&gt;","\\"":"&quot;"}[c]})}
function has(v){return V.some(function(x){return x.toLowerCase()===v.toLowerCase()})}
function draw(){tags.innerHTML=V.map(function(v,i){return \'<span class="t">\'+esc(v)+\'<button type="button" data-i="\'+i+\'" aria-label="Remove">×</button></span>\'}).join("");tags.querySelectorAll("button").forEach(function(b){b.onclick=function(e){e.stopPropagation();V.splice(+b.getAttribute("data-i"),1);draw();list()}})}
function list(){var q=inp.value.trim().toLowerCase(),o=opts.filter(function(x){return !q||x.toLowerCase().indexOf(q)>-1});var h=o.map(function(x){return \'<button type="button" data-v="\'+esc(x)+\'" class="\'+(has(x)?"on":"")+\'"><i>\'+(has(x)?"✓":"")+\'</i>\'+esc(x)+\'</button>\'}).join("");if(q&&!opts.some(function(x){return x.toLowerCase()===q}))h=\'<button type="button" data-new="1" class="hl"><i>+</i>Add "\'+esc(inp.value.trim())+\'"</button>\'+h;dd.innerHTML=h;dd.querySelectorAll("button").forEach(function(b){b.onmousedown=function(e){e.preventDefault()};b.onclick=function(){if(b.getAttribute("data-new")){add(inp.value)}else{var v=b.getAttribute("data-v");if(has(v))V=V.filter(function(x){return x.toLowerCase()!==v.toLowerCase()});else V.push(v);draw();list()}}})}
function add(v){v=String(v||"").trim().replace(/\\s+/g," ");if(!v)return;if(!has(v))V.push(v.charAt(0).toUpperCase()+v.slice(1));inp.value="";draw();list()}
el.onclick=function(e){if(e.target===el)inp.focus()};inp.onfocus=function(){list();dd.hidden=false};inp.onblur=function(){setTimeout(function(){dd.hidden=true},150)};inp.oninput=list;
inp.onkeydown=function(e){if(e.key==="Enter"||e.key===","){e.preventDefault();add(inp.value)}else if(e.key==="Backspace"&&!inp.value&&V.length){V.pop();draw();list()}};el.querySelector(".add").onclick=function(){if(inp.value.trim())add(inp.value);else{inp.focus()}};
draw();return{get:function(){return V.slice()},set:function(a){V=a.slice();draw()}}};</script>';
}

/* PDF helpers (jsPDF, loaded only when a member downloads). Built-in fonts cannot draw ₦ ₵ ₹, so PDFs show currency codes for those. */
function ir_tl_pdf_js() {
	return <<<'JS'
window.IRPDF=(function(){var P=null;
function lib(){if(window.jspdf)return Promise.resolve(window.jspdf.jsPDF);if(P)return P;P=new Promise(function(res,rej){var s=document.createElement('script');s.src='https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';s.onload=function(){res(window.jspdf.jsPDF)};s.onerror=function(){P=null;rej(new Error('Check your connection and try again.'))};document.head.appendChild(s)});return P}
function img(f){return new Promise(function(res){var r=new FileReader();r.onload=function(){var i=new Image();i.onload=function(){var c=document.createElement('canvas'),m=500,k=Math.min(1,m/Math.max(i.width,i.height));c.width=Math.round(i.width*k);c.height=Math.round(i.height*k);c.getContext('2d').drawImage(i,0,0,c.width,c.height);res({src:c.toDataURL('image/png'),w:c.width,h:c.height})};i.onerror=function(){res(null)};i.src=r.result};r.readAsDataURL(f)})}
function clean(t){return String(t==null?'':t).replace(/[‘’]/g,"'").replace(/[“”]/g,'"').replace(/[–—]/g,'-').replace(/·/g,'|').replace(/₦/g,'NGN ').replace(/GH₵/g,'GHS ').replace(/₵/g,'GHS ').replace(/₹/g,'INR ').replace(/KSh ?/g,'KES ').replace(/[^\x20-\x7E\n£€]/g,'').replace(/ {2,}/g,' ').trim()}
function rgb(h){h=String(h||'#1660E8').replace('#','');return[parseInt(h.substr(0,2),16),parseInt(h.substr(2,2),16),parseInt(h.substr(4,2),16)]}
function tint(c,k){return c.map(function(v){return Math.round(v+(255-v)*k)})}
function money(v,cur){var sym={USD:'$',CAD:'CA$',GBP:'£',EUR:'€',AUD:'A$'}[cur];var n=(Math.round(v*100)/100).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});return sym?sym+n:cur+' '+n}
function save(doc,name){var b=doc.output('blob'),u=URL.createObjectURL(b),a=document.createElement('a');a.href=u;a.download=name;document.body.appendChild(a);a.click();setTimeout(function(){URL.revokeObjectURL(u);a.remove()},4000)}
function slug(t){return clean(t).toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')||'file'}
function today(){return new Date().toLocaleDateString('en-GB',{day:'numeric',month:'long',year:'numeric'})}
function fill(d,c){d.setFillColor(c[0],c[1],c[2])}function ink(d,c){d.setTextColor(c[0],c[1],c[2])}function line(d,c){d.setDrawColor(c[0],c[1],c[2])}
var NAVY=[10,22,51],GREY=[91,107,140],SOFT=[226,232,242];
function header(d,o,W,H,c){fill(d,c);d.rect(0,0,W,H,'F');fill(d,tint(c,0.18));d.circle(W-18,-6,44,'F');fill(d,tint(c,0.32));d.circle(W+6,H-4,26,'F');
 var x=16;if(o.logo){var lh=18,lw=Math.min(42,o.logo.w/o.logo.h*lh);fill(d,[255,255,255]);d.roundedRect(14,14,lw+6,lh+6,3,3,'F');d.addImage(o.logo.src,'PNG',17,17,lw,lh);x=14+lw+12}return x}
function priceCard(o){return lib().then(function(J){var d=new J({unit:'mm',format:'a4'}),c=rgb(o.color),W=210,Hh=64;
 var x=header(d,o,W,Hh,c);ink(d,[255,255,255]);d.setFont('helvetica','bold');d.setFontSize(23);d.text(clean(o.biz),x,27);
 d.setFont('helvetica','normal');d.setFontSize(11);d.text(clean('Social media management packages'+(o.niche?' for '+o.niche:'')),x,35);
 d.setFontSize(9);if(o.forWho){d.setFont('helvetica','bold');d.text('PREPARED FOR',16,50);d.setFont('helvetica','normal');d.setFontSize(11);d.text(clean(o.forWho),16,56)}
 d.setFontSize(9);d.setFont('helvetica','normal');d.text(today(),W-16,56,{align:'right'});
 var y=76,cw=(W-32-12)/3,tops=[];
 o.P.forEach(function(k,i){var n=o.names[i]||{},cx=16+i*(cw+6),hot=i===1;d.setFontSize(10);
  var tag=d.splitTextToSize(clean(n.tagline||''),cw-10),items=[];k.items.forEach(function(it){items.push(d.splitTextToSize(clean(it[0])+(it[2]?' ('+clean(it[2])+')':''),cw-14))});items.push(d.splitTextToSize(clean(k.plats.join(', ')),cw-14));
  var il=items.reduce(function(a,x){return a+x.length},0),nl=d.setFontSize(14)&&d.splitTextToSize(clean(n.name),cw-10),hgt=36+nl.length*6.2+tag.length*4.8+il*4.9+items.length*1.5;
  fill(d,hot?tint(c,0.92):[255,255,255]);line(d,hot?c:SOFT);d.setLineWidth(hot?0.9:0.35);d.roundedRect(cx,y,cw,hgt,4,4,'FD');
  if(hot){fill(d,c);d.roundedRect(cx+cw/2-15,y-4,30,8,4,4,'F');ink(d,[255,255,255]);d.setFont('helvetica','bold');d.setFontSize(7);d.text('MOST POPULAR',cx+cw/2,y+1.2,{align:'center'})}
  var yy=y+11;ink(d,NAVY);d.setFont('helvetica','bold');d.setFontSize(14);d.text(nl,cx+5,yy);yy+=nl.length*6.2;
  d.setFont('helvetica','normal');d.setFontSize(10);ink(d,GREY);d.text(tag,cx+5,yy);yy+=tag.length*4.8+9;
  ink(d,c);d.setFont('helvetica','bold');d.setFontSize(19);d.text(clean(k.price),cx+5,yy);yy+=5.5;d.setFont('helvetica','normal');d.setFontSize(9);ink(d,GREY);d.text('per month  |  '+clean(k.setup)+' setup',cx+5,yy);yy+=5;
  line(d,SOFT);d.setLineWidth(0.3);d.line(cx+5,yy,cx+cw-5,yy);yy+=6.5;d.setFontSize(10);
  items.forEach(function(L){fill(d,c);d.circle(cx+6.5,yy-1.2,1.1,'F');ink(d,[40,52,80]);d.text(L,cx+10,yy);yy+=L.length*4.9+1.5});tops.push(y+hgt)});
 var by=Math.max.apply(null,tops)+10;if(by>252){d.addPage();by=24}
 fill(d,tint(c,0.92));d.roundedRect(16,by,W-32,32,4,4,'F');ink(d,NAVY);d.setFont('helvetica','bold');d.setFontSize(13);d.text('Ready to start?',22,by+9.5);
 d.setFont('helvetica','normal');d.setFontSize(10.5);ink(d,GREY);d.text(clean('Save 10% when you pay 3 months upfront. Prices are per month. Ad spend is paid separately.'),22,by+15.5);
 ink(d,c);d.setFont('helvetica','bold');d.setFontSize(12);d.text(clean([o.name,o.contact].filter(Boolean).join('  |  ')),22,by+23.5);
 d.setFont('helvetica','normal');d.setFontSize(8);ink(d,[150,160,180]);d.text(clean(o.biz),W/2,289,{align:'center'});
 if(o.P.add&&o.P.add.length){d.addPage();fill(d,c);d.rect(0,0,W,30,'F');ink(d,[255,255,255]);d.setFont('helvetica','bold');d.setFontSize(17);d.text('Add-ons and single services',16,19);d.setFont('helvetica','normal');d.setFontSize(9.5);d.text(clean(o.biz),W-16,19,{align:'right'});
  var ay=44;ink(d,GREY);d.setFontSize(11.5);d.text('For one-off work, or extras on top of a package.',16,ay);ay+=8;
  o.P.add.forEach(function(a,i){if(i%2){fill(d,tint(c,0.94));d.rect(16,ay-6,W-32,14.5,'F')}ink(d,NAVY);d.setFont('helvetica','bold');d.setFontSize(12);d.text(clean(a[0]),20,ay);d.setFont('helvetica','normal');d.setFontSize(10);ink(d,GREY);d.text(clean(a[1]),20,ay+5.2);ink(d,c);d.setFont('helvetica','bold');d.setFontSize(13);d.text(clean(a[2]),W-20,ay+2,{align:'right'});ay+=15});
  ink(d,GREY);d.setFont('helvetica','normal');d.setFontSize(10.5);d.text(clean('Prices are per item unless shown otherwise. '+[o.name,o.contact].filter(Boolean).join('  |  ')),16,Math.min(ay+8,280));d.setFontSize(8);ink(d,[150,160,180]);d.text(clean(o.biz),W/2,289,{align:'center'})}
 save(d,slug(o.biz)+'-price-card'+(o.forWho?'-'+slug(o.forWho):'')+'.pdf')})}
function invoice(o){return lib().then(function(J){var d=new J({unit:'mm',format:'a4'}),c=rgb(o.color),W=210,L=16,R=W-16,Hh=54,LH=6;
 var x=header(d,o,W,Hh,c);ink(d,[255,255,255]);d.setFont('helvetica','bold');d.setFontSize(22);d.text(d.splitTextToSize(clean(o.from.name||''),110)[0],x,27);d.setFont('helvetica','normal');d.setFontSize(12);if(o.from.person)d.text(clean(o.from.person),x,35);
 d.setFont('helvetica','bold');d.setFontSize(32);d.text('INVOICE',R,30,{align:'right'});d.setFont('helvetica','normal');d.setFontSize(12);d.text(clean(o.no),R,39,{align:'right'});
 var y=Hh+10,bw=(R-L-9)/4,box=[['Invoice no.',o.no],['Issue date',o.date],['Due date',o.due],['Amount due',money(o.status==='paid'?0:o.total,o.cur)]];
 box.forEach(function(b,i){var bx=L+i*(bw+3);fill(d,i===3?c:tint(c,0.92));d.roundedRect(bx,y,bw,21,3,3,'F');ink(d,i===3?[255,255,255]:GREY);d.setFontSize(9);d.setFont('helvetica','bold');d.text(b[0].toUpperCase(),bx+4,y+7.5);ink(d,i===3?[255,255,255]:NAVY);var v=clean(b[1]),fs=12.5;d.setFontSize(fs);while(fs>9&&d.getTextWidth(v)>bw-7){fs-=0.5;d.setFontSize(fs)}d.text(v,bx+4,y+15.5)});
 y+=32;var colW=(R-L-8)/2;function party(px,title,p){var yy=y;ink(d,GREY);d.setFont('helvetica','bold');d.setFontSize(9.5);d.text(title,px,yy);yy+=7;ink(d,NAVY);d.setFontSize(14);d.text(d.splitTextToSize(clean(p.name||''),colW)[0],px,yy);yy+=7;d.setFont('helvetica','normal');d.setFontSize(11.5);ink(d,[60,72,100]);
  [p.biz,p.email,p.phone].concat(String(p.address||'').split('\n')).forEach(function(t){if(clean(t)){d.text(d.splitTextToSize(clean(t),colW)[0],px,yy);yy+=LH}});return yy}
 var y1=party(L,'FROM',{name:o.from.name,biz:o.from.person,email:o.from.email,phone:o.from.phone,address:o.from.address}),y2=party(L+colW+8,'BILL TO',o.to);y=Math.max(y1,y2)+8;
 var cA=R-4,cP=R-44,cQ=R-84;fill(d,c);d.roundedRect(L,y,R-L,12,2,2,'F');ink(d,[255,255,255]);d.setFont('helvetica','bold');d.setFontSize(10);d.text('DESCRIPTION',L+4,y+7.8);d.text('QTY',cQ,y+7.8,{align:'right'});d.text('PRICE',cP,y+7.8,{align:'right'});d.text('AMOUNT',cA,y+7.8,{align:'right'});y+=14;
 o.items.forEach(function(it,i){d.setFont('helvetica','normal');d.setFontSize(12);var ls=d.splitTextToSize(clean(it.desc),cQ-L-16),h=Math.max(11,ls.length*LH+5);if(y+h>258){d.addPage();y=20}
  if(i%2){fill(d,[247,249,253]);d.rect(L,y-1,R-L,h,'F')}ink(d,NAVY);d.text(ls,L+4,y+6);d.text(String(it.qty),cQ,y+6,{align:'right'});d.text(clean(money(it.price,o.cur)),cP,y+6,{align:'right'});d.setFont('helvetica','bold');d.text(clean(money(it.qty*it.price,o.cur)),cA,y+6,{align:'right'});y+=h});
 var TW=100;y+=4;line(d,SOFT);d.setLineWidth(0.3);d.line(R-TW,y,R,y);y+=8;function row(l,v){d.setFont('helvetica','normal');d.setFontSize(12);ink(d,GREY);d.text(l,R-TW+2,y);ink(d,NAVY);d.text(clean(v),R-4,y,{align:'right'});y+=7.5}
 row('Subtotal',money(o.sub,o.cur));if(o.disc)row('Discount ('+o.discP+'%)','-'+money(o.disc,o.cur));if(o.tax)row(clean(o.taxName||'Tax')+' ('+o.taxP+'%)',money(o.tax,o.cur));
 fill(d,c);d.roundedRect(R-TW,y-5,TW,16,3,3,'F');ink(d,[255,255,255]);d.setFont('helvetica','bold');d.setFontSize(14.5);d.text(o.status==='paid'?'Paid':'Total due',R-TW+5,y+5.2);d.text(clean(money(o.total,o.cur)),R-4,y+5.2,{align:'right'});
 if(o.status==='paid'){line(d,[22,163,74]);ink(d,[22,163,74]);d.setLineWidth(1);d.roundedRect(L,y-6,38,15,3,3,'S');d.setFontSize(16);d.text('PAID',L+19,y+4,{align:'center'})}
 y+=24;if(clean(o.pay)){d.setFontSize(12);var pl=d.splitTextToSize(clean(o.pay),R-L-14);if(y+pl.length*LH+18>272){d.addPage();y=20}fill(d,tint(c,0.92));d.roundedRect(L,y,R-L,pl.length*LH+17,3,3,'F');ink(d,c);d.setFont('helvetica','bold');d.setFontSize(9.5);d.text('HOW TO PAY',L+7,y+8.5);ink(d,NAVY);d.setFont('helvetica','normal');d.setFontSize(12);d.text(pl,L+7,y+15.5);y+=pl.length*LH+25}
 if(clean(o.notes)){d.setFontSize(12);var nl=d.splitTextToSize(clean(o.notes),R-L);if(y+nl.length*LH+8>275){d.addPage();y=20}ink(d,GREY);d.setFont('helvetica','bold');d.setFontSize(9.5);d.text('NOTES',L,y);y+=6.5;d.setFont('helvetica','normal');d.setFontSize(12);ink(d,NAVY);d.text(nl,L,y)}
 line(d,SOFT);d.line(L,279,R,279);d.setFontSize(10);ink(d,GREY);d.text(clean(['Thank you for your business',o.from.person||o.from.name].filter(Boolean).join(' | ')),L,285.5);d.text(clean([o.from.email,o.from.phone].filter(Boolean).join(' | ')),R,285.5,{align:'right'});
 save(d,'invoice-'+slug(o.no)+(o.to.name?'-'+slug(o.to.name):'')+'.pdf')})}
function contract(o){return lib().then(function(J){var d=new J({unit:'mm',format:'a4'}),c=rgb(o.color),W=210,L=20,R=W-20,y=0,page=1,BODY=11.5,LH=5.9;
 function foot(){d.setFont('helvetica','normal');d.setFontSize(8.5);ink(d,[150,160,180]);d.text(clean((o.title||'Social Media Management Agreement')+'  |  '+o.biz+' and '+(o.clientBiz||o.client)),L,289);d.text('Page '+page,R,289,{align:'right'})}
 function need(h){if(y+h>275){foot();d.addPage();page++;fill(d,c);d.rect(0,0,W,5,'F');y=22}}
 var x=header(d,o,W,52,c);ink(d,[255,255,255]);d.setFont('helvetica','bold');d.setFontSize(21);d.text(d.splitTextToSize(clean(o.title||'Social Media Management Agreement'),R-x),x,26);
 d.setFont('helvetica','normal');d.setFontSize(12);d.text(clean(o.biz+'  and  '+(o.clientBiz||o.client)),x,36);
 y=62;var S=o.sum||[],bw=(R-L-3*(S.length-1))/Math.max(1,S.length);
 S.forEach(function(b,i){var bx=L+i*(bw+3);fill(d,tint(c,0.92));d.roundedRect(bx,y,bw,18,3,3,'F');ink(d,GREY);d.setFont('helvetica','bold');d.setFontSize(7.5);d.text(clean(b[0]).toUpperCase(),bx+4,y+6.5);ink(d,NAVY);d.setFontSize(11);d.text(d.splitTextToSize(clean(b[1]),bw-7)[0],bx+4,y+13.5)});
 y+=S.length?30:4;
 String(o.text||'').split(/\n/).forEach(function(raw){var t=clean(raw);if(!t){y+=2.2;return}
  var head=/^\d+\.\s/.test(t)&&t.length<70,bul=/^-\s/.test(t);
  if(head){need(LH*3+8);y+=4;var m=t.match(/^(\d+)\.\s*(.*)$/);fill(d,c);d.circle(L+3.6,y-1.6,3.6,'F');ink(d,[255,255,255]);d.setFont('helvetica','bold');d.setFontSize(9.5);d.text(m[1],L+3.6,y-0.2,{align:'center'});ink(d,NAVY);d.setFontSize(13.5);d.text(m[2],L+10,y);y+=LH+2;return}
  d.setFont('helvetica','normal');d.setFontSize(BODY);ink(d,[40,52,80]);
  if(bul){var ls=d.splitTextToSize(t.replace(/^-\s*/,''),R-L-16);need(ls.length*LH+1);fill(d,c);d.circle(L+12,y-1.4,1.2,'F');d.text(ls,L+16,y);y+=ls.length*LH+1;return}
  var ls2=d.splitTextToSize(t,R-L-10);need(ls2.length*LH+1);d.text(ls2,L+10,y);y+=ls2.length*LH+1.5});
 need(92);y+=10;fill(d,c);d.rect(L,y-6,3,9,'F');ink(d,NAVY);d.setFont('helvetica','bold');d.setFontSize(14);d.text('Agreed and signed',L+7,y+1);y+=10;var cw=(R-L-10)/2;
 [['Service provider',o.biz,o.name,o.email,o.addr],['Client',o.clientBiz||o.client,o.client,o.clientEmail,o.clientAddr]].forEach(function(p,i){var px=L+i*(cw+10),yy=y;line(d,SOFT);d.setLineWidth(0.4);fill(d,[255,255,255]);d.roundedRect(px,yy,cw,74,4,4,'FD');fill(d,tint(c,0.9));d.roundedRect(px,yy,cw,12,4,4,'F');d.rect(px,yy+6,cw,6,'F');
  ink(d,c);d.setFont('helvetica','bold');d.setFontSize(8.5);d.text(p[0].toUpperCase(),px+5,yy+7.8);
  var ly=yy+20;ink(d,NAVY);d.setFontSize(12);d.text(d.splitTextToSize(clean(p[1]),cw-10)[0],px+5,ly);ly+=6.5;d.setFont('helvetica','normal');d.setFontSize(10);ink(d,[40,52,80]);
  [[p[2]&&p[2]!==p[1]?p[2]:''],[p[3]],[p[4]]].forEach(function(v){if(clean(v[0])){d.text(d.splitTextToSize(clean(v[0]),cw-10)[0],px+5,ly);ly+=5.2}});
  line(d,[160,170,190]);d.setLineWidth(0.35);d.line(px+5,yy+56,px+cw-5,yy+56);ink(d,GREY);d.setFontSize(9);d.text('Signature',px+5,yy+61);d.line(px+5,yy+69,px+cw/2,yy+69);d.text('Date',px+5,yy+73)});
 foot();save(d,'contract-'+slug(o.clientBiz||o.client)+'.pdf')})}
return{lib:lib,img:img,priceCard:priceCard,invoice:invoice,contract:contract,money:money,clean:clean}})();
JS;
}

/* ---------- Invoice Generator ---------- */

function ir_tl_page_invoice() {
	ir_tl_open( 'Invoice Generator', 'invoice', 'Create a branded invoice with your name, logo and colours, addressed to your client. Download it as a PDF. Your details are saved for next time.', '#7C3AED', '#1660E8' );
	?>
<form class="tl-card" id="tlForm" autocomplete="on">
 <h3 class="iv-h">Your details</h3>
 <div class="tl-two"><label class="tl-f"><span>Business name</span><input id="ivFromName" placeholder="Reubx Social"></label><label class="tl-f"><span>Your name</span><input id="ivFromPerson" placeholder="Igho Reuben"></label></div>
 <div class="tl-two"><label class="tl-f"><span>Email</span><input id="ivFromEmail" type="email" placeholder="hello@reubx.com"></label><label class="tl-f"><span>Phone <em>optional</em></span><input id="ivFromPhone" placeholder="+234 801 234 5678"></label></div>
 <label class="tl-f"><span>Address <em>optional</em></span><input id="ivFromAddr" placeholder="Lagos, Nigeria"></label>
 <div class="tl-two"><label class="tl-f"><span>Logo <em>optional</em></span><input id="ivLogo" type="file" accept="image/png,image/jpeg"></label><label class="tl-f"><span>Brand colour</span><input id="ivColor" type="color" value="#1660E8" style="height:48px;padding:6px"></label></div>
 <h3 class="iv-h">Your client</h3>
 <div class="tl-two"><label class="tl-f"><span>Client name</span><input id="ivToName" placeholder="Amaka Obi"></label><label class="tl-f"><span>Client business <em>optional</em></span><input id="ivToBiz" placeholder="Glow by Amaka"></label></div>
 <div class="tl-two"><label class="tl-f"><span>Client email <em>optional</em></span><input id="ivToEmail" type="email"></label><label class="tl-f"><span>Client address <em>optional</em></span><input id="ivToAddr"></label></div>
 <h3 class="iv-h">Invoice</h3>
 <div class="tl-two" style="grid-template-columns:1fr 1fr 1fr"><label class="tl-f"><span>Invoice no.</span><input id="ivNo"></label><label class="tl-f"><span>Issue date</span><input id="ivDate" type="date"></label><label class="tl-f"><span>Due date</span><input id="ivDue" type="date"></label></div>
 <div class="tl-two"><label class="tl-f"><span>Currency</span><select id="ivCur"></select></label><label class="tl-f"><span>Status</span><select id="ivStatus"><option value="due">Unpaid</option><option value="paid">Paid</option></select></label></div>
 <div class="tl-f"><span>Items</span><div id="ivItems"></div><div class="tl-acts"><button type="button" class="tl-btn2" id="ivAdd">+ Add item</button><select id="ivPk" class="tl-btn2" hidden><option value="">+ Add a package from the Pricing Calculator</option></select></div></div>
 <div class="tl-two" style="grid-template-columns:1fr 1fr 1fr"><label class="tl-f"><span>Discount %</span><input id="ivDisc" type="number" min="0" max="100" value="0"></label><label class="tl-f"><span>Tax name</span><input id="ivTaxN" value="VAT"></label><label class="tl-f"><span>Tax %</span><input id="ivTax" type="number" min="0" max="50" step="0.5" value="0"></label></div>
 <label class="tl-f"><span>How to pay</span><textarea id="ivPay" rows="3" placeholder="Bank: GTBank&#10;Account name: Reubx Social&#10;Account number: 0123456789"></textarea></label>
 <label class="tl-f"><span>Notes <em>optional</em></span><textarea id="ivNotes" rows="2" placeholder="Thank you! Payment is due within 7 days."></textarea></label>
 <div class="iv-tot" id="ivTot"></div>
 <div class="tl-err" id="tlErr" hidden></div>
 <button class="tl-go" id="tlGo">⬇️ Download invoice PDF</button>
 <div class="tl-status" id="tlStatus" hidden></div>
</form>
<?php echo ir_tl_doc_css(); // phpcs:ignore ?>
<?php
	$js = ir_tl_pdf_js() . <<<'JS'
(function(){var T=TL,$=T.$,$$=T.$$;
var CURS=['USD','NGN','GBP','EUR','CAD','AUD','GHS','KES','ZAR','INR','AED'];$('#ivCur').innerHTML=CURS.map(function(c){return '<option>'+c+'</option>'}).join('');
var KEEP=['ivFromName','ivFromPerson','ivFromEmail','ivFromPhone','ivFromAddr','ivColor','ivPay','ivNotes','ivCur','ivTaxN','ivTax'];KEEP.forEach(function(id){var v=T.ls('iv_'+id);if(v)$('#'+id).value=v});
if(!$('#ivFromName').value&&T.ls('pc_pcBiz'))$('#ivFromName').value=T.ls('pc_pcBiz');if(!$('#ivFromPerson').value&&T.ls('pc_pcName'))$('#ivFromPerson').value=T.ls('pc_pcName');if(T.ls('pc_pcColor')&&!T.ls('iv_ivColor'))$('#ivColor').value=T.ls('pc_pcColor');
var now=new Date(),iso=function(d){return d.toISOString().slice(0,10)};$('#ivDate').value=iso(now);var du=new Date(now);du.setDate(du.getDate()+7);$('#ivDue').value=iso(du);
$('#ivNo').value='INV-'+now.getFullYear()+'-'+('00'+((+T.ls('iv_seq')||0)+1)).slice(-3);
function row(desc,qty,price){var r=document.createElement('div');r.className='iv-row';r.innerHTML='<input placeholder="Description, e.g. Social media management, May" class="d"><input type="number" min="0" step="1" class="q" value="1" aria-label="Quantity"><input type="number" min="0" step="0.01" class="p" placeholder="Price"><button type="button" aria-label="Remove">×</button>';
 $('.d',r).value=desc||'';$('.q',r).value=qty||1;if(price!=null)$('.p',r).value=Math.round(price*100)/100;$('button',r).onclick=function(){r.remove();tot()};$$('input',r).forEach(function(i){i.oninput=tot});$('#ivItems').appendChild(r);tot()}
function items(){return $$('#ivItems .iv-row').map(function(r){return{desc:$('.d',r).value.trim(),qty:+$('.q',r).value||0,price:+$('.p',r).value||0}}).filter(function(i){return i.desc||i.price})}
function calc(){var it=items(),sub=it.reduce(function(a,i){return a+i.qty*i.price},0),dp=+$('#ivDisc').value||0,tp=+$('#ivTax').value||0,disc=sub*dp/100,tax=(sub-disc)*tp/100;return{it:it,sub:sub,disc:disc,dp:dp,tax:tax,tp:tp,total:sub-disc+tax}}
function tot(){var c=calc(),cur=$('#ivCur').value,m=function(v){return IRPDF.money(v,cur)};$('#ivTot').innerHTML='<div><span>Subtotal</span><span>'+m(c.sub)+'</span></div>'+(c.disc?'<div><span>Discount ('+c.dp+'%)</span><span>-'+m(c.disc)+'</span></div>':'')+(c.tax?'<div><span>'+T.esc($('#ivTaxN').value||'Tax')+' ('+c.tp+'%)</span><span>'+m(c.tax)+'</span></div>':'')+'<div class="t"><span>Total due</span><span>'+m(c.total)+'</span></div>'}
['ivDisc','ivTax','ivTaxN','ivCur'].forEach(function(id){$('#'+id).oninput=tot;$('#'+id).onchange=tot});
$('#ivAdd').onclick=function(){row()};
var last=null;try{last=JSON.parse(localStorage.getItem('pr_last')||'null')}catch(e){}
function addPk(i){var k=last&&last.pk[i];if(!k)return;$('#ivCur').value=last.cur;var empty=$$('#ivItems .iv-row').filter(function(r){return !$('.d',r).value&&!$('.p',r).value});empty.forEach(function(r){r.remove()});row(k.name+' social media package (1 month): '+k.items.join(', ')+', '+k.plats.join(', '),1,k.price)}
if(last&&last.pk){var s=$('#ivPk');s.hidden=false;s.innerHTML+=last.pk.map(function(k,i){return '<option value="'+i+'">'+T.esc(k.name)+'</option>'}).join('');s.onchange=function(){if(s.value!=='')addPk(+s.value);s.value=''}}
var q=new URLSearchParams(location.search).get('pkg');if(last&&q!=null)addPk(+q);else row();
$('#tlForm').onsubmit=function(e){e.preventDefault();T.err('');var c=calc();
 if(!$('#ivFromName').value.trim()){T.err('Add your business name.');return}
 if(!$('#ivToName').value.trim()){T.err('Add your client\'s name.');return}
 if(!c.it.length||!c.total){T.err('Add at least one item with a price.');return}
 KEEP.forEach(function(id){T.ls('iv_'+id,$('#'+id).value)});
 var f=$('#ivLogo').files[0];T.busy(true,'Making your invoice...');
 (f?IRPDF.img(f):Promise.resolve(null)).then(function(logo){return IRPDF.invoice({logo:logo,color:$('#ivColor').value,no:$('#ivNo').value.trim(),date:$('#ivDate').value,due:$('#ivDue').value,status:$('#ivStatus').value,cur:$('#ivCur').value,
  from:{name:$('#ivFromName').value,person:$('#ivFromPerson').value,email:$('#ivFromEmail').value,phone:$('#ivFromPhone').value,address:$('#ivFromAddr').value},to:{name:$('#ivToName').value,biz:$('#ivToBiz').value,email:$('#ivToEmail').value,address:$('#ivToAddr').value},
  items:c.it,sub:c.sub,disc:c.disc,discP:c.dp,tax:c.tax,taxP:c.tp,taxName:$('#ivTaxN').value,total:c.total,pay:$('#ivPay').value,notes:$('#ivNotes').value})})
 .then(function(){T.busy(false);var m=$('#ivNo').value.match(/(\d+)\s*$/);if(m)T.ls('iv_seq',String(+m[1]))}).catch(function(x){T.busy(false);T.err('The PDF could not be made: '+x.message)})};
})();
JS;
	ir_tl_close( $js );
}

function ir_tl_doc_css() {
	return '<style>.iv-h{margin:6px 0 12px;font-size:14px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted)}.iv-h:not(:first-child){margin-top:18px;padding-top:16px;border-top:1px solid var(--line)}
.iv-row{display:grid;grid-template-columns:1fr 64px 120px 40px;gap:8px;margin:0 0 8px}.iv-row input{width:100%;box-sizing:border-box;font:inherit;font-size:16px!important;padding:11px 12px;border:1px solid var(--line);border-radius:12px;background:var(--surface);color:var(--text)}.iv-row button{border:0;background:var(--soft);border-radius:10px;cursor:pointer;font-size:18px;color:var(--muted)}
@media(max-width:560px){.iv-row{grid-template-columns:1fr 1fr 40px}.iv-row input:first-child{grid-column:1/-1}}
.iv-tot{background:var(--soft);border-radius:14px;padding:12px 16px;margin:4px 0 14px;font-size:15px;line-height:1.9}.iv-tot div{display:flex;justify-content:space-between}.iv-tot .t{font-size:19px;font-weight:800;border-top:1px solid var(--line);margin-top:4px;padding-top:4px}
select.tl-btn2{font:inherit;font-weight:700;font-size:13.5px}
.ct-doc{width:100%;box-sizing:border-box;min-height:420px;font:inherit;font-size:15px!important;line-height:1.6;padding:14px;border:1px solid var(--line);border-radius:14px;background:var(--surface);color:var(--text)}</style>';
}

/* ---------- Contract Generator ---------- */

function ir_tl_page_contract() {
	ir_tl_open( 'Contract Generator', 'contract', 'Fill in the details and get a clear social media management contract with your name and your client\'s, ready to edit, sign and send as a PDF.', '#0F766E', '#1660E8' );
	?>
<form class="tl-card" id="tlForm">
 <h3 class="iv-h">You</h3>
 <div class="tl-two"><label class="tl-f"><span>Business name</span><input id="ctBiz" placeholder="Reubx Social"></label><label class="tl-f"><span>Your name</span><input id="ctName" placeholder="Igho Reuben"></label></div>
 <div class="tl-two"><label class="tl-f"><span>Your email</span><input id="ctEmail" type="email"></label><label class="tl-f"><span>Your address <em>optional</em></span><input id="ctAddr" placeholder="Lagos, Nigeria"></label></div>
 <h3 class="iv-h">Your client</h3>
 <div class="tl-two"><label class="tl-f"><span>Client name</span><input id="ctClient" placeholder="Amaka Obi"></label><label class="tl-f"><span>Client business</span><input id="ctClientBiz" placeholder="Glow by Amaka"></label></div>
 <div class="tl-two"><label class="tl-f"><span>Client email <em>optional</em></span><input id="ctClientEmail" type="email"></label><label class="tl-f"><span>Client address <em>optional</em></span><input id="ctClientAddr"></label></div>
 <h3 class="iv-h">The work</h3>
 <label class="tl-f"><span>Services included</span><textarea id="ctSvc" rows="4" placeholder="One per line, e.g.&#10;12 branded posts a month on Instagram&#10;8 edited Reels a month&#10;Captions, hashtags and scheduling"></textarea><small id="ctPkWrap" hidden><a href="#" id="ctPk">Use my package from the Pricing Calculator</a></small></label>
 <div class="tl-two" style="grid-template-columns:1fr 1fr 1fr"><label class="tl-f"><span>Monthly fee</span><input id="ctFee" type="number" min="0" placeholder="250000"></label><label class="tl-f"><span>Currency</span><select id="ctCur"></select></label><label class="tl-f"><span>Setup fee <em>optional</em></span><input id="ctSetup" type="number" min="0"></label></div>
 <div class="tl-two" style="grid-template-columns:1fr 1fr 1fr"><label class="tl-f"><span>Start date</span><input id="ctStart" type="date"></label><label class="tl-f"><span>Minimum term</span><select id="ctTerm"><option value="1">1 month</option><option value="3" selected>3 months</option><option value="6">6 months</option><option value="12">12 months</option></select></label><label class="tl-f"><span>Notice to end</span><select id="ctNotice"><option>14</option><option selected>30</option><option>60</option></select></label></div>
 <div class="tl-two" style="grid-template-columns:1fr 1fr 1fr"><label class="tl-f"><span>Payment due</span><select id="ctPayWhen"><option value="before">Before each month starts</option><option value="start">Within 7 days of invoice</option></select></label><label class="tl-f"><span>Revisions per post</span><select id="ctRev"><option>1</option><option selected>2</option><option>3</option></select></label><label class="tl-f"><span>Late fee % <em>0 for none</em></span><input id="ctLate" type="number" min="0" max="20" value="5"></label></div>
 <div class="tl-two"><label class="tl-f"><span>Governing law <em>country or state</em></span><input id="ctLaw" placeholder="Nigeria"></label><label class="tl-f"><span>Brand colour</span><input id="ctColor" type="color" value="#1660E8" style="height:48px;padding:6px"></label></div>
 <label class="tl-f"><span>Logo <em>optional</em></span><input id="ctLogo" type="file" accept="image/png,image/jpeg"></label>
 <div class="tl-err" id="tlErr" hidden></div>
 <button class="tl-go" id="tlGo">📝 Write my contract</button>
</form>
<div class="tl-out" id="tlOut"></div>
<?php echo ir_tl_doc_css(); // phpcs:ignore ?>
<?php
	$js = ir_tl_pdf_js() . <<<'JS'
(function(){var T=TL,$=T.$,out=$('#tlOut');
var CURS=['USD','NGN','GBP','EUR','CAD','AUD','GHS','KES','ZAR','INR','AED'];$('#ctCur').innerHTML=CURS.map(function(c){return '<option>'+c+'</option>'}).join('');
var KEEP=['ctBiz','ctName','ctEmail','ctAddr','ctCur','ctLaw','ctColor','ctTerm','ctNotice','ctPayWhen','ctRev','ctLate'];KEEP.forEach(function(id){var v=T.ls('ct_'+id);if(v)$('#'+id).value=v});
if(!$('#ctBiz').value)$('#ctBiz').value=T.ls('iv_ivFromName')||T.ls('pc_pcBiz');if(!$('#ctName').value)$('#ctName').value=T.ls('iv_ivFromPerson')||T.ls('pc_pcName');if(!$('#ctEmail').value)$('#ctEmail').value=T.ls('iv_ivFromEmail');if(!T.ls('ct_ctColor')&&(T.ls('iv_ivColor')||T.ls('pc_pcColor')))$('#ctColor').value=T.ls('iv_ivColor')||T.ls('pc_pcColor');
$('#ctStart').value=new Date().toISOString().slice(0,10);
var last=null;try{last=JSON.parse(localStorage.getItem('pr_last')||'null')}catch(e){}
if(last&&last.pk){$('#ctPkWrap').hidden=false;$('#ctPk').onclick=function(e){e.preventDefault();var k=last.pk[1];$('#ctSvc').value=k.items.join('\n')+'\nPlatforms: '+k.plats.join(', ');$('#ctFee').value=k.price;$('#ctCur').value=last.cur}}
function fmt(v,cur){return IRPDF.money(+v||0,cur).replace(/\.00$/,'')}
function date(s){if(!s)return '';var d=new Date(s+'T12:00:00');return d.toLocaleDateString('en-GB',{day:'numeric',month:'long',year:'numeric'})}
function build(){var g=function(id){return $('#'+id).value.trim()},cur=g('ctCur'),fee=fmt(g('ctFee'),cur),cl=g('ctClientBiz')||g('ctClient'),me=g('ctBiz')||g('ctName'),term=+g('ctTerm'),n=g('ctNotice');
 var svc=g('ctSvc').split('\n').map(function(s){return s.trim()}).filter(Boolean).map(function(s){return '- '+s.replace(/^[-*]\s*/,'')}).join('\n');
 return ['This Social Media Management Agreement (the "Agreement") is made on '+date(g('ctStart'))+' between '+me+' (the "Provider") and '+cl+' (the "Client"). It sets out the work the Provider will do for the Client and the terms both parties agree to.','',
 '1. Services','The Provider will deliver the following each month:',svc||'- [List the services]','Anything not listed above is outside this Agreement and will be quoted separately.','',
 '2. Fees and payment','The Client will pay '+fee+' per month'+(+g('ctSetup')?' and a one-time setup fee of '+fmt(g('ctSetup'),cur)+', payable before work begins':'')+'. '+(g('ctPayWhen')==='before'?'Each monthly fee is paid in advance, before that month\'s work starts.':'Each invoice is due within 7 days of the date it is sent.')+(+g('ctLate')?' Payments more than 7 days late incur a late fee of '+g('ctLate')+'% of the overdue amount, and the Provider may pause work until the account is up to date.':' The Provider may pause work while a payment is overdue.')+' Advertising spend is not included and is paid by the Client directly to the platform.','',
 '3. Term and ending the Agreement','This Agreement starts on '+date(g('ctStart'))+' and runs for a minimum of '+term+' month'+(term>1?'s':'')+'. After that it continues month to month. Either party can end it with '+n+' days\' written notice (email is fine). Fees for work already done, and for the notice period, remain payable.','',
 '4. Approvals and revisions','The Provider will share content for approval before it is posted. The Client will reply within 2 working days. Content not reviewed in that time may be posted as planned. Each post includes '+g('ctRev')+' round'+(+g('ctRev')>1?'s':'')+' of revisions. Extra revisions or new requests may be charged.','',
 '5. What the Client provides','The Client will provide access to the relevant accounts, brand assets, product or service details and timely answers to questions. Delays in providing these may delay delivery and do not reduce the fee.','',
 '6. Results','The Provider will use professional skill and care. Because social media platforms and audiences change, the Provider does not guarantee a specific number of followers, views, leads or sales.','',
 '7. Ownership of content','Once each invoice is paid in full, the Client owns the final content created for them under this Agreement. The Provider may show the work in their portfolio unless the Client asks them not to in writing. Stock assets, templates and tools remain under their own licences.','',
 '8. Confidentiality','Both parties will keep each other\'s private information (including passwords, customer data and business plans) confidential, during and after this Agreement. The Provider will return or delete access details when the Agreement ends.','',
 '9. Limitation of liability','Neither party is liable for indirect losses. The Provider\'s total liability under this Agreement is limited to the fees paid in the 3 months before the claim. Neither party is responsible for delays caused by events outside their control, including platform outages or changes.','',
 '10. General','This Agreement is the full agreement between the parties and replaces earlier discussions. Changes must be agreed in writing. The Provider works as an independent contractor, not an employee.'+(g('ctLaw')?' This Agreement is governed by the laws of '+g('ctLaw')+'.':'')].join('\n')}
$('#tlForm').onsubmit=function(e){e.preventDefault();T.err('');
 if(!$('#ctBiz').value.trim()&&!$('#ctName').value.trim()){T.err('Add your business name or your name.');return}
 if(!$('#ctClient').value.trim()&&!$('#ctClientBiz').value.trim()){T.err('Add your client\'s name.');return}
 if(!(+$('#ctFee').value)){T.err('Add the monthly fee.');return}
 KEEP.forEach(function(id){T.ls('ct_'+id,$('#'+id).value)});
 out.innerHTML='<div class="tl-res"><div class="tl-res-h"><b>Your contract</b><button type="button" class="tl-copy" data-copy="#ctDoc">Copy</button></div><p class="tl-note" style="margin:0 0 10px">Read it through and edit anything you need. This is a general template, not legal advice. For large contracts, ask a local lawyer to check it.</p><textarea id="ctDoc" class="ct-doc"></textarea><button type="button" class="tl-go" id="ctPdf" style="margin-top:12px">⬇️ Download contract PDF</button></div>';
 $('#ctDoc').value=build();T.bindCopy(out);out.scrollIntoView({behavior:'smooth',block:'start'});
 $('#ctPdf').onclick=function(){var b=this,f=$('#ctLogo').files[0];b.disabled=true;b.textContent='Making your PDF...';
  (f?IRPDF.img(f):Promise.resolve(null)).then(function(logo){var cu=$('#ctCur').value,tm=+$('#ctTerm').value;return IRPDF.contract({logo:logo,color:$('#ctColor').value,biz:$('#ctBiz').value.trim()||$('#ctName').value.trim(),name:$('#ctName').value.trim(),email:$('#ctEmail').value.trim(),addr:$('#ctAddr').value.trim(),client:$('#ctClient').value.trim(),clientBiz:$('#ctClientBiz').value.trim(),clientEmail:$('#ctClientEmail').value.trim(),clientAddr:$('#ctClientAddr').value.trim(),text:$('#ctDoc').value,
   sum:[['Monthly fee',fmt($('#ctFee').value,cu)],['Start date',date($('#ctStart').value)],['Minimum term',tm+' month'+(tm>1?'s':'')],['Notice',$('#ctNotice').value+' days']]})})
  .then(function(){b.disabled=false;b.textContent='⬇️ Download contract PDF'}).catch(function(x){b.disabled=false;b.textContent='⬇️ Download contract PDF';T.err('The PDF could not be made: '+x.message)})}};
})();
JS;
	ir_tl_close( $js );
}

/* ---------- Enquiry Form Builder ---------- */

function ir_tl_page_enquiry() {
	ir_tl_open( 'Client Forms', 'enquiry', 'Make branded enquiry and client onboarding forms. Share the link, and every answer arrives in your email and here.', '#DB2777', '#7C3AED' );
	$forms = function_exists( 'ir_forms_mine' ) ? array_values( ir_forms_mine() ) : [];
	?>
<div id="efList"></div>
<form class="tl-card" id="tlForm" hidden>
 <div class="tl-res-h" style="margin:0 0 6px"><b id="efHead" style="font-size:17px">New form</b><button type="button" class="tl-btn2" id="efCancel">Close</button></div>
 <div class="tl-two"><label class="tl-f"><span>Form type</span><select id="efType"><option value="enquiry">Enquiry form (new leads)</option><option value="onboarding">Client onboarding form (new clients)</option></select></label><label class="tl-f"><span>Currency</span><select id="efCur"></select><small>Used for the budget question.</small></label></div>
 <h3 class="iv-h">Your brand</h3>
 <div class="tl-two"><label class="tl-f"><span>Business name</span><input id="efBiz" placeholder="Reubx Social"></label><label class="tl-f"><span>Your name</span><input id="efName" placeholder="Igho Reuben"></label></div>
 <div class="tl-two"><label class="tl-f"><span>Send answers to</span><input id="efEmail" type="email"></label><label class="tl-f"><span>Brand colour</span><input id="efColor" type="color" value="#1660E8" style="height:48px;padding:6px"></label></div>
 <label class="tl-f"><span>Logo <em>optional, small square image</em></span><input id="efLogo" type="file" accept="image/png,image/jpeg"></label>
 <h3 class="iv-h">The form</h3>
 <label class="tl-f"><span>Headline</span><input id="efTitle"></label>
 <label class="tl-f"><span>Short intro</span><textarea id="efIntro" rows="2"></textarea></label>
 <div class="tl-f"><span>Questions</span><div id="efQs"></div><button type="button" class="tl-btn2" id="efAdd" style="margin-top:6px">+ Add your own question</button><small>Name, email and phone are always asked. Untick a question to hide it.</small></div>
 <label class="tl-f"><span>Thank-you message</span><input id="efThanks"></label>
 <div class="tl-err" id="tlErr" hidden></div>
 <button class="tl-go" id="tlGo">💾 Save and get my link</button>
 <div class="tl-status" id="tlStatus" hidden></div>
</form>
<div class="tl-out" id="tlOut"></div>
<?php echo ir_tl_doc_css(); // phpcs:ignore ?>
<style>.ef-q{display:grid;grid-template-columns:28px 1fr 130px 34px;gap:8px;align-items:center;margin:0 0 8px}.ef-q input[type=text],.ef-q select{width:100%;box-sizing:border-box;font:inherit;font-size:16px!important;padding:10px 11px;border:1px solid var(--line);border-radius:11px;background:var(--surface);color:var(--text)}.ef-q input[type=checkbox]{width:20px;height:20px;accent-color:var(--acc)}.ef-q button{border:0;background:var(--soft);border-radius:9px;cursor:pointer;font-size:17px;color:var(--muted);height:38px}
.ef-q .opts{grid-column:2/-1}@media(max-width:560px){.ef-q{grid-template-columns:28px 1fr 34px}.ef-q select{grid-column:2/-1;grid-row:2}}
.ef-new{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:0 0 14px}@media(max-width:560px){.ef-new{grid-template-columns:1fr}}.ef-new button{text-align:left;border:1.5px dashed var(--line);background:var(--surface);border-radius:16px;padding:14px 16px;cursor:pointer;font:inherit;color:var(--text)}.ef-new b{display:block;font-size:15.5px}.ef-new small{color:var(--muted);font-size:13px}
.ef-card{background:var(--surface);border:1px solid var(--line);border-radius:18px;padding:14px 16px;margin:0 0 10px}.ef-card .h{display:flex;gap:10px;align-items:center;justify-content:space-between}.ef-card b{font-size:16px}.ef-card .tp{font-size:11.5px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--acc)}
.ef-link{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px}.ef-link input{flex:1;min-width:180px;font:inherit;font-size:14.5px!important;padding:11px;border:1px solid var(--line);border-radius:12px;background:var(--soft);color:var(--text)}
.ef-sub{border-top:1px solid var(--line);padding:12px 0}.ef-sub:first-child{border-top:0}.ef-sub b{font-size:15px}.ef-sub small{color:var(--muted);margin-left:6px}.ef-sub dl{margin:8px 0 0;display:grid;grid-template-columns:minmax(110px,38%) 1fr;gap:4px 10px;font-size:14px}.ef-sub dt{color:var(--muted)}.ef-sub dd{margin:0;white-space:pre-wrap;word-break:break-word}</style>
<?php
	$cfg = [ 'aj' => admin_url( 'admin-ajax.php' ), 'n' => wp_create_nonce( 'ir_form' ), 'forms' => $forms, 'me' => [ 'email' => wp_get_current_user()->user_email, 'name' => function_exists( 'ir_first_name' ) ? ir_first_name( wp_get_current_user() ) : '' ] ];
	$js = 'window.IRF=' . wp_json_encode( $cfg ) . ';' . <<<'JS'
(function(){var T=TL,$=T.$,$$=T.$$,C=window.IRF||{},FORMS=C.forms||[],SUBS=[],cur=null,logo='';
var CURS=['USD','NGN','GBP','EUR','CAD','AUD','GHS','KES','ZAR','INR','AED'];$('#efCur').innerHTML=CURS.map(function(c){return '<option>'+c+'</option>'}).join('');
var BUD={USD:['Under $500','$500 to $1,000','$1,000 to $2,500','$2,500+'],NGN:['Under ₦100,000','₦100,000 to ₦250,000','₦250,000 to ₦500,000','₦500,000+'],GBP:['Under £400','£400 to £800','£800 to £1,500','£1,500+'],EUR:['Under €450','€450 to €900','€900 to €1,800','€1,800+'],CAD:['Under CA$700','CA$700 to CA$1,400','CA$1,400 to CA$3,000','CA$3,000+'],AUD:['Under A$800','A$800 to A$1,500','A$1,500 to A$3,500','A$3,500+'],GHS:['Under GH₵2,000','GH₵2,000 to GH₵5,000','GH₵5,000 to GH₵10,000','GH₵10,000+'],KES:['Under KSh 30,000','KSh 30,000 to KSh 60,000','KSh 60,000 to KSh 120,000','KSh 120,000+'],ZAR:['Under R7,000','R7,000 to R15,000','R15,000 to R30,000','R30,000+'],INR:['Under ₹25,000','₹25,000 to ₹60,000','₹60,000 to ₹1,20,000','₹1,20,000+'],AED:['Under AED 3,500','AED 3,500 to AED 8,000','AED 8,000 to AED 16,000','AED 16,000+']};
var Q={enquiry:[['Business name','short',1],['Instagram or website link','short',1],['What do you need help with?','choice',1,['Content creation','Full social media management','Paid ads','Strategy only','Not sure yet']],['What is your biggest challenge with social media right now?','long',1],['What is your monthly budget?','choice',1,'BUDGET'],['When would you like to start?','choice',1,['As soon as possible','Within a month','Just exploring']],['How did you hear about me?','short',0],['Anything else I should know?','long',0]],
 onboarding:[['Business name','short',1],['Website and social media links','long',1],['How will you give me access to your accounts?','choice',1,['I will add you to Meta Business Suite (safest)','I will share login details securely','Please guide me']],['Describe your business and what you sell','long',1],['Who is your ideal customer?','long',1],['Your top 3 goals for social media','long',1],['Brand colours and fonts','short',1],['Link to your logo, photos and brand files (Google Drive or Dropbox)','short',1],['Accounts or competitors you admire','long',1],['Tone of voice','choice',1,['Friendly and fun','Professional','Luxury and elegant','Bold and direct','Educational']],['Anything to avoid (topics, words, styles)?','long',1],['Who approves content? Name and WhatsApp number','short',1],['Preferred start date','short',1],['Billing contact name and email','short',1],['Anything else I should know?','long',0]]};
var TXT={enquiry:{title:"Let's grow your business on social media",intro:"Answer a few quick questions and I'll get back to you within 24 hours with ideas for your page.",thanks:"Thank you! I'll be in touch within 24 hours."},onboarding:{title:"Welcome aboard! Let's get you set up",intro:"This helps me understand your brand so your content is right from day one. It takes about 10 minutes.",thanks:"Thank you! I have everything I need. I'll be in touch with next steps shortly."}};
function post(a,d){var f=new FormData();f.append('action',a);f.append('n',C.n);Object.keys(d||{}).forEach(function(k){f.append(k,d[k])});return fetch(C.aj,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json()})}
function link(id){return location.origin+'/enquiry/'+id+'/'}
function list(){var h='<div class="ef-new"><button type="button" data-new="enquiry"><b>+ New enquiry form</b><small>For new leads: needs, budget, timing</small></button><button type="button" data-new="onboarding"><b>+ New onboarding form</b><small>For new clients: brand, access, goals, files</small></button></div>';
 h+=FORMS.map(function(f){var n=SUBS.filter(function(s){return s.form===f.id||(!s.form&&FORMS.length===1)}).length;return '<div class="ef-card"><div class="h"><div><div class="tp">'+(f.type==='onboarding'?'Onboarding form':'Enquiry form')+' · '+T.esc(f.cur||'USD')+'</div><b>'+T.esc(f.title||f.biz)+'</b></div><div class="tl-acts" style="margin:0"><button type="button" class="tl-btn2" data-edit="'+f.id+'">Edit</button><button type="button" class="tl-btn2" data-del="'+f.id+'">Delete</button></div></div><div class="ef-link"><input readonly value="'+link(f.id)+'" id="u_'+f.id+'"><button type="button" class="tl-copy" data-copy="#u_'+f.id+'">Copy link</button><a class="tl-btn2" href="'+link(f.id)+'" target="_blank" rel="noopener">Open</a><button type="button" class="tl-btn2" data-subs="'+f.id+'">Answers ('+n+')</button><button type="button" class="tl-btn2" data-own="'+f.id+'">🔗 Use my own link</button></div></div>'}).join('');
 $('#efList').innerHTML=h;T.bindCopy($('#efList'));
 $$('[data-new]').forEach(function(b){b.onclick=function(){edit(null,b.getAttribute('data-new'))}});$$('[data-edit]').forEach(function(b){b.onclick=function(){edit(FORMS.filter(function(f){return f.id===b.getAttribute('data-edit')})[0])}});
 $$('[data-del]').forEach(function(b){b.onclick=function(){if(!confirm('Delete this form? Its link will stop working.'))return;post('ir_form_delete',{id:b.getAttribute('data-del')}).then(function(j){if(j&&j.ok){FORMS=j.forms;list()}})}});
 $$('[data-subs]').forEach(function(b){b.onclick=function(){answers(b.getAttribute('data-subs'))}});
 $$('[data-own]').forEach(function(b){b.onclick=function(){own(FORMS.filter(function(f){return f.id===b.getAttribute('data-own')})[0])}})}
/* The form on the member's own link: a tiny page they host anywhere (Netlify, their website). The form inside still runs here, so answers still reach their email. */
function slugify(t){return String(t||'form').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'').slice(0,40)||'form'}
function ownPage(f){var u=link(f.id),biz=f.biz||f.name||'',title=(f.title||(f.type==='onboarding'?'Client onboarding':'Work with us'))+(biz?' | '+biz:''),c=/^#[0-9a-f]{6}$/i.test(f.color||'')?f.color:'#1660E8',e=function(x){return String(x).replace(/[&<>"]/g,function(ch){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[ch]})};
 var desc=f.type==='onboarding'?'Fill in the onboarding form for '+biz+'.':'Tell '+(biz||'us')+' about your business and what you need.';
 return '<!doctype html>\n<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">\n<title>'+e(title)+'</title>\n<meta name="description" content="'+e(desc)+'">\n<meta property="og:title" content="'+e(title)+'">\n<meta property="og:description" content="'+e(desc)+'">\n<meta name="theme-color" content="'+c+'">\n'+(f.logo&&/^data:image\//.test(f.logo)?'<link rel="icon" href="'+f.logo+'">\n':'')+'<style>html,body{margin:0;height:100%;background:'+c+'}iframe{position:fixed;inset:0;width:100%;height:100%;border:0;background:#fff}</style></head>\n<body><iframe src="'+e(u)+'" title="'+e(title)+'" allow="clipboard-write"></iframe>\n<noscript><a href="'+e(u)+'">Open the form</a></noscript></body></html>\n'}
function own(f){if(!f)return;var name=slugify(f.biz||f.title),file=ownPage(f),embed='<iframe src="'+link(f.id)+'" title="'+String(f.title||f.biz||'Form').replace(/"/g,'')+'" style="width:100%;min-height:900px;border:0" id="irf_'+f.id+'"></iframe>\n<script>addEventListener("message",function(e){if(e.data&&e.data.irf==="'+f.id+'"){document.getElementById("irf_'+f.id+'").style.height=e.data.h+"px"}});<\/script>';
 var h='<div class="tl-res"><div class="tl-res-h"><b>Put this form on your own link</b></div><p class="tl-txt" style="margin:0 0 6px">Share the form on a free Netlify link or your own website instead of ighoreuben.com. It looks the same, and every answer still comes to <b>'+T.esc(f.email||'your email')+'</b> and shows up here.</p></div>';
 h+='<div class="tl-res"><div class="tl-res-h"><b>Option 1: Free Netlify link</b></div><ol class="tl-list"><li>Download your page: <button type="button" class="tl-copy" id="ownDl">⬇ Download index.html</button></li><li>On your computer, make a folder named <b>'+T.esc(name)+'</b> and put index.html inside it.</li><li>Open <a href="https://app.netlify.com/drop" target="_blank" rel="noopener">app.netlify.com/drop</a> and drag the folder onto the page. Create a free account so the link stays live.</li><li>You get a link like <b>random-name.netlify.app</b>. Go to Site configuration, then Change site name, and type <b>'+T.esc(name)+'</b>. Your link becomes <b>'+T.esc(name)+'.netlify.app</b>.</li><li>Share that link. If you edit the form here later, the Netlify link updates by itself.</li></ol><p class="tl-txt" style="color:var(--muted);font-size:13.5px">On a phone? Upload index.html at <a href="https://tiiny.host" target="_blank" rel="noopener">tiiny.host</a> instead. It works the same way and also gives you a free link.</p></div>';
 h+='<div class="tl-res"><div class="tl-res-h"><b>Option 2: Your own website or domain</b></div><ol class="tl-list"><li><b>Its own page:</b> upload the same index.html into a folder on your website, for example <b>yourdomain.com/enquiry/</b>. Or connect your domain to the Netlify site in Netlify\'s Domain management.</li><li><b>Inside a page you already have</b> (WordPress, Wix, Squarespace, Shopify): add an HTML or Embed block and paste this code.</li></ol><textarea id="ownEmbed" rows="5" readonly>'+T.esc(embed)+'</textarea><div class="tl-acts"><button type="button" class="tl-copy" data-copy="#ownEmbed">Copy embed code</button></div></div>';
 $('#tlOut').innerHTML=h;T.bindCopy($('#tlOut'));
 $('#ownDl').onclick=function(){var b=new Blob([file],{type:'text/html'}),a=document.createElement('a');a.href=URL.createObjectURL(b);a.download='index.html';document.body.appendChild(a);a.click();setTimeout(function(){URL.revokeObjectURL(a.href);a.remove()},3000)};
 $('#tlOut').scrollIntoView({behavior:'smooth',block:'start'})}
function answers(id){var f=FORMS.filter(function(x){return x.id===id})[0]||{},L=SUBS.filter(function(s){return s.form===id||(!s.form&&FORMS.length===1)});
 $('#tlOut').innerHTML='<div class="tl-res"><div class="tl-res-h"><b>Answers: '+T.esc(f.title||f.biz||'')+' ('+L.length+')</b></div>'+(L.length?L.map(function(s){return '<div class="ef-sub"><b>'+T.esc(s.name||'New answer')+'</b><small>'+T.esc(s.at)+'</small><dl>'+s.a.map(function(p){return '<dt>'+T.esc(p[0])+'</dt><dd>'+T.esc(p[1])+'</dd>'}).join('')+'</dl></div>'}).join(''):'<p class="tl-txt" style="color:var(--muted)">No answers yet. Share your link to get your first one.</p>')+'</div>';$('#tlOut').scrollIntoView({behavior:'smooth',block:'start'})}
function qrow(q){var r=document.createElement('div');r.className='ef-q';r.innerHTML='<input type="checkbox" class="on"><input type="text" class="l" placeholder="Your question"><select class="t"><option value="short">Short answer</option><option value="long">Paragraph</option><option value="choice">Multiple choice</option></select><button type="button" aria-label="Remove">×</button><input type="text" class="opts" placeholder="Choices, separated by commas">';
 $('.on',r).checked=q.on!==false;$('.l',r).value=q.label||'';$('.t',r).value=q.type||'short';$('.opts',r).value=(q.options||[]).join(', ');var sync=function(){$('.opts',r).style.display=$('.t',r).value==='choice'?'':'none'};$('.t',r).onchange=sync;sync();$('button',r).onclick=function(){r.remove()};$('#efQs').appendChild(r)}
function defaults(type,c){return Q[type].map(function(d){return{label:d[0],type:d[1],on:!!d[2],options:d[3]==='BUDGET'?BUD[c]:(d[3]||[])}})}
function budgetRows(){return $$('#efQs .ef-q').filter(function(r){return /budget/i.test($('.l',r).value)&&$('.t',r).value==='choice'})}
$('#efCur').onchange=function(){var c=$('#efCur').value;budgetRows().forEach(function(r){$('.opts',r).value=BUD[c].join(', ')})};
$('#efType').onchange=function(){if(cur)return;var t=$('#efType').value;$('#efQs').innerHTML='';defaults(t,$('#efCur').value).forEach(qrow);$('#efTitle').value=TXT[t].title;$('#efIntro').value=TXT[t].intro;$('#efThanks').value=TXT[t].thanks};
function edit(f,type){cur=f||null;logo=f?f.logo||'':'';var t=f?f.type||'enquiry':type,c=f?f.cur||'USD':(T.ls('ef_cur')||T.ls('iv_ivCur')||'USD');
 $('#tlForm').hidden=false;$('#efHead').textContent=f?'Edit form':(t==='onboarding'?'New onboarding form':'New enquiry form');$('#efType').value=t;$('#efType').disabled=!!f;$('#efCur').value=c;
 var last=FORMS[0]||{};$('#efBiz').value=(f||last).biz||T.ls('iv_ivFromName')||T.ls('pc_pcBiz')||'';$('#efName').value=(f||last).name||T.ls('iv_ivFromPerson')||T.ls('pc_pcName')||(C.me&&C.me.name)||'';$('#efEmail').value=(f||last).email||(C.me&&C.me.email)||'';$('#efColor').value=(f||last).color||T.ls('iv_ivColor')||T.ls('pc_pcColor')||'#1660E8';if(!f)logo=last.logo||'';
 $('#efTitle').value=f?f.title:TXT[t].title;$('#efIntro').value=f?f.intro:TXT[t].intro;$('#efThanks').value=f?f.thanks:TXT[t].thanks;
 $('#efQs').innerHTML='';(f&&f.questions&&f.questions.length?f.questions:defaults(t,c)).forEach(qrow);T.err('');$('#tlForm').scrollIntoView({behavior:'smooth',block:'start'})}
$('#efCancel').onclick=function(){$('#tlForm').hidden=true;cur=null};
$('#efAdd').onclick=function(){qrow({label:'',type:'short',on:true})};
$('#efLogo').onchange=function(){var f=this.files[0];if(!f)return;var r=new FileReader();r.onload=function(){var i=new Image();i.onload=function(){var c=document.createElement('canvas'),k=Math.min(1,160/Math.max(i.width,i.height));c.width=Math.round(i.width*k);c.height=Math.round(i.height*k);c.getContext('2d').drawImage(i,0,0,c.width,c.height);logo=c.toDataURL('image/png')};i.src=r.result};r.readAsDataURL(f)};
$('#tlForm').onsubmit=function(e){e.preventDefault();T.err('');
 var qs=$$('#efQs .ef-q').map(function(r){return{label:$('.l',r).value.trim(),type:$('.t',r).value,on:$('.on',r).checked,options:$('.opts',r).value.split(',').map(function(s){return s.trim()}).filter(Boolean)}}).filter(function(q){return q.label});
 if(!$('#efBiz').value.trim()){T.err('Add your business name.');return}
 if(!/@/.test($('#efEmail').value)){T.err('Add the email where answers should go.');return}
 T.ls('ef_cur',$('#efCur').value);T.busy(true,'Saving your form...');
 post('ir_form_save',{data:JSON.stringify({id:cur?cur.id:'',type:$('#efType').value,cur:$('#efCur').value,biz:$('#efBiz').value.trim(),name:$('#efName').value.trim(),email:$('#efEmail').value.trim(),color:$('#efColor').value,logo:logo,title:$('#efTitle').value.trim(),intro:$('#efIntro').value.trim(),thanks:$('#efThanks').value.trim(),questions:qs})})
 .then(function(j){T.busy(false);if(!j||!j.ok){T.err((j&&j.err)||'Could not save. Try again.');return}FORMS=j.forms;cur=null;$('#tlForm').hidden=true;list();window.scrollTo({top:0,behavior:'smooth'})}).catch(function(){T.busy(false);T.err('Connection problem. Try again.')})};
list();post('ir_form_subs',{}).then(function(j){SUBS=(j&&j.subs)||[];list()});
if(!FORMS.length)edit(null,'enquiry');
})();
JS;
	ir_tl_close( $js );
}

/* ---------- The Booked & Paid SMM Kit (/members/smm-tools/). Access lives in mu-plugins/ir-kit.php. ---------- */

function ir_smm_tools() { return [ 'pricing', 'invoice', 'contract', 'enquiry' ]; }
function ir_smm_url( $tool = '' ) { return home_url( '/members/smm-tools/' . ( $tool ? $tool . '/' : '' ) ); }
/** SMM Workshop students, AI Profit System members and admins. */
function ir_smm_access( $uid = 0 ) {
	$uid = $uid ?: get_current_user_id();
	if ( ! $uid ) { return false; }
	if ( function_exists( 'ir_kit_has_access' ) ) { return ir_kit_has_access( $uid ); }
	return function_exists( 'ir_pb_files_access' ) && ir_pb_files_access( $uid );
}

function ir_smm_open( $title, $active = '' ) {
	$u = wp_get_current_user();
	$first = function_exists( 'ir_first_name' ) ? ir_first_name( $u ) : $u->display_name;
	ir_pb_head( ( $active === 'home' ? '' : $title . ' | ' ) . 'The Booked & Paid SMM Kit' );
	echo '<style>' . ir_pp_css() . '</style>';
	$nav = [ [ 'home', ir_smm_url(), 'spark', 'Tools' ] ];
	$nav[] = [ 'tpl', ir_smm_url() . '#templates', 'pack', 'Templates' ];
	$nav[] = [ 'dash', ir_pb_url(), 'grid', 'Dashboard' ];
	?>
<header class="pb-top pp-top">
 <a class="pb-logo" href="<?php echo esc_url( ir_pb_url() ); ?>">Igho Reuben<span>.</span></a>
 <nav class="pp-nav" aria-label="Resources and tools">
  <?php foreach ( $nav as $n ) : ?><a class="pp-nav-i<?php echo $active === $n[0] ? ' on' : ''; ?>" href="<?php echo esc_url( $n[1] ); ?>"><?php echo ir_pp_icon( $n[2] ); ?><span><?php echo esc_html( $n[3] ); ?></span></a><?php endforeach; ?>
 </nav>
 <button class="pp-theme" id="pbTheme" type="button" aria-label="Switch light or dark"><?php echo ir_pp_icon( 'theme' ); ?></button>
 <span class="pb-av" title="<?php echo esc_attr( $u->user_email ); ?>"><?php echo esc_html( strtoupper( mb_substr( $first, 0, 1 ) ) ); ?></span>
</header>
<?php
}

function ir_smm_render_home() {
	$uid = get_current_user_id();
	$tpl = ir_pb_files();
	$tools = [
		[ 'pricing', 'price', '#16A34A', '#0EA5E9', 'SMM Pricing Calculator', '3 packages priced on real rates in your country, plus a PDF price card' ],
		[ 'invoice', 'invoice', '#7C3AED', '#1660E8', 'Invoice Generator', 'Branded invoices for your clients, downloaded as a PDF' ],
		[ 'contract', 'contract', '#0F766E', '#1660E8', 'Contract Generator', 'A ready social media contract with your name and your client\'s' ],
		[ 'enquiry', 'enquiry', '#DB2777', '#7C3AED', 'Client Forms', 'Enquiry and onboarding forms. Answers arrive in your email' ],
	];
	ir_smm_open( 'SMM Kit', 'home' );
	?>
<style>
.sm-h2{font-size:22px;letter-spacing:-.3px;margin:30px 0 4px}.sm-sub{color:var(--muted);margin:0 0 14px;font-size:15.5px}
.sm-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px}
.sm-tool{display:flex;gap:14px;align-items:center;padding:16px;border:1px solid var(--line);border-radius:18px;background:var(--surface);color:var(--text);text-decoration:none;transition:border-color .2s,transform .2s}.sm-tool:hover{border-color:var(--a);transform:translateY(-1px)}
.sm-ic{width:50px;height:50px;border-radius:15px;display:grid;place-items:center;flex:none;background:linear-gradient(135deg,var(--a),var(--b));box-shadow:inset 0 0 0 1px rgba(255,255,255,.18),0 6px 16px -6px var(--a)}.sm-ic svg{display:block}
.sm-tool b{display:block;font-size:16px;letter-spacing:-.2px}.sm-tool small{display:block;color:var(--muted);font-size:13.5px;line-height:1.4;margin-top:2px}
.sm-files{display:grid;gap:10px}
.sm-file{display:flex;gap:14px;align-items:center;padding:14px 16px;border:1px solid var(--line);border-radius:16px;background:var(--surface);color:var(--text);text-decoration:none}.sm-file:hover{border-color:var(--acc)}
.sm-file .sm-ic{width:44px;height:44px;border-radius:13px;--a:#E8650E;--b:#F59E0B}
.sm-file span.t{flex:1;min-width:0}.sm-file b{display:block;font-size:15.5px}.sm-file small{display:block;color:var(--muted);font-size:13.5px;line-height:1.4;margin-top:2px}
.sm-dl{font-weight:800;font-size:13.5px;color:var(--acc);border:1px solid var(--line);border-radius:10px;padding:8px 12px;white-space:nowrap}
@media(max-width:560px){.sm-dl{display:none}}
.sm-how{margin:12px 0 0;background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:12px 16px}.sm-how summary{cursor:pointer;font-weight:700;color:var(--acc)}.sm-how ol{margin:10px 0 2px;padding-left:20px;line-height:1.7;font-size:15px}
</style>
<main class="pp-main">
 <section class="pp-hero">
  <h1>The Booked &amp; Paid SMM Kit</h1>
  <p>Price, pitch, bill and onboard clients in minutes. Everything carries your name and brand, so you look like the agency they want to pay.</p>
  <div class="pp-stats"><span><?php echo count( $tools ); ?> tools</span><?php if ( $tpl ) : ?><span><?php echo count( $tpl ); ?> templates</span><?php endif; ?></div>
 </section>
 <h2 class="sm-h2">Tools</h2><p class="sm-sub">Fill in your details once. They are saved for next time.</p>
 <div class="sm-grid">
  <?php foreach ( $tools as $t ) : ?><a class="sm-tool" style="--a:<?php echo esc_attr( $t[2] ); ?>;--b:<?php echo esc_attr( $t[3] ); ?>" href="<?php echo esc_url( ir_smm_url( $t[0] ) ); ?>"><span class="sm-ic"><?php echo ir_tl_svg( $t[1], 26 ); // phpcs:ignore ?></span><span><b><?php echo esc_html( $t[4] ); ?></b><small><?php echo esc_html( $t[5] ); ?></small></span></a><?php endforeach; ?>
 </div>
 <?php if ( $tpl ) : ?>
  <h2 class="sm-h2" id="templates">Templates</h2><p class="sm-sub">Proposal, agreement, strategy and monthly report, plus a Notion content calendar to plan every client's posts.</p>
  <div class="sm-files">
   <?php foreach ( $tpl as $k => $f ) : ?><a class="sm-file" href="<?php echo esc_url( ir_pb_file_url( $k ) ); ?>"><span class="sm-ic"><?php echo ir_tl_svg( 'doc', 22 ); // phpcs:ignore ?></span><span class="t"><b><?php echo esc_html( $f['t'] ); ?></b><small><?php echo esc_html( $f['when'] ); ?></small></span><span class="sm-dl">Download PDF</span></a><?php endforeach; ?>
   <?php $notion = (string) get_option( 'ir_kit_notion', 'https://polished-girl-063.notion.site/Socialreubx-Dashboard-2d2b4a84572880e68d0bee953478fad3?source=copy_link' ); ?>
   <a class="sm-file" href="<?php echo esc_url( $notion ); ?>" target="_blank" rel="noopener"><span class="sm-ic" style="--a:#111827;--b:#4B5563"><?php echo ir_tl_svg( 'tools', 22 ); // phpcs:ignore ?></span><span class="t"><b>Notion Content Calendar</b><small>Brand plan, audience profile and a content calendar with day, format, viral hook and reference for every post.</small></span><span class="sm-dl">Open in Notion</span></a>
  </div>
  <details class="sm-how"><summary>How to make your own copy of the Notion calendar</summary><ol><li>Log in to Notion (free at notion.so).</li><li>Open the calendar with the button above.</li><li>On a computer, click <b>Duplicate</b> at the top right. On a phone, tap the <b>•••</b> menu, then <b>Duplicate</b>.</li><li>Rename it for your client, fill in Brand Plan and Audience Profile, then plan each post in Content Calendar.</li><li>Make a fresh copy for every client. Share it with <b>Can comment</b> access so they can approve posts.</li></ol></details>
 <?php endif; ?>
</main>
<?php
	ir_pp_close();
}

add_action( 'template_redirect', function () {
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( ! preg_match( '#^members/smm-tools(?:/([a-z]+))?/?$#', $path, $m ) ) { return; }
	if ( ! function_exists( 'ir_pp_ready' ) || ! ir_pp_ready() ) { return; }
	nocache_headers();
	do_action( 'litespeed_control_set_nocache', 'smm tools' );
	if ( ! is_user_logged_in() ) { wp_safe_redirect( ir_pb_url() ); exit; }
	if ( ! ir_smm_access() ) { wp_safe_redirect( function_exists( 'ir_kit_sales_url' ) ? ir_kit_sales_url() : ir_pb_url() ); exit; }
	$route = $m[1] ?? '';
	status_header( 200 );
	header( 'X-Robots-Tag: noindex, nofollow', true );
	if ( $route === '' ) { ir_smm_render_home(); exit; }
	if ( ! in_array( $route, ir_smm_tools(), true ) ) { wp_safe_redirect( ir_smm_url() ); exit; }
	$GLOBALS['ir_tl_smm'] = true;
	call_user_func( 'ir_tl_page_' . $route );
	exit;
}, 0 );
