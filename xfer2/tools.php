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
		if ( $pl === 'TikTok' && function_exists( 'ir_pp_reel_tiktok' ) && preg_match( '#/video/\d+#', $url ) ) {
			$t = ir_pp_reel_tiktok( $url );
			if ( is_array( $t ) && ! empty( $t['vtt'] ) ) {
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
	$map = [ 'comments' => 'ir_tl_page_comments', 'flyer' => 'ir_tl_page_flyer', 'clone' => 'ir_tl_page_clone' ];
	if ( ! isset( $map[ $route ] ) ) { return false; }
	call_user_func( $map[ $route ] );
	return true;
}

/** White line icons for the tool tiles and headers. */
function ir_tl_svg( $k, $size = 26 ) {
	$p = [
		'clone'   => '<rect x="3" y="5" width="13" height="14" rx="3"/><path d="M16 10.5l5-3v9l-5-3"/><path d="M8 9.5v5l4-2.5z" fill="#fff" stroke="none"/>',
		'comment' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8a2.5 2.5 0 0 1-2.5 2.5H10l-4.5 4v-4h0A1.5 1.5 0 0 1 4 14.5z"/><path d="M8 8h8M8 11.5h5"/>',
		'flyer'   => '<rect x="4" y="3" width="16" height="18" rx="2.5"/><circle cx="9.5" cy="8.5" r="1.8"/><path d="M4 16l4.5-4.5 3.5 3.5 2.5-2.5L20 17"/>',
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
	ir_pp_open( $title, 'home' );
	echo '<style>' . ir_tl_css() . '</style>';
	echo '<main class="pp-main narrow"><div class="tl" style="--k1:' . esc_attr( $k1 ) . ';--k2:' . esc_attr( $k2 ) . '">';
	echo '<nav class="pp-crumb"><a href="' . esc_url( ir_pp_url() ) . '">AI Profit System</a><span>›</span><span>' . esc_html( $title ) . '</span></nav>';
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
 <label class="tl-f"><span>What you do <em>optional, saved for next time</em></span><input id="tlMe" placeholder="e.g. social media manager for estheticians"></label>
 <div class="tl-f"><span>Your goal</span><div class="tl-chips" id="tlGoal"><button type="button" data-v="get noticed by this person and start a relationship" class="on">Get noticed</button><button type="button" data-v="show my expertise to their audience">Show expertise</button><button type="button" data-v="start a real conversation">Start a conversation</button><button type="button" data-v="support a friend or client genuinely">Support them</button></div></div>
 <div class="tl-err" id="tlErr" hidden></div>
 <button class="tl-go" id="tlGo">✨ Write 5 comments</button>
 <div class="tl-status" id="tlStatus" hidden></div>
</form>
<div class="tl-out" id="tlOut"></div>
<?php
	$js = <<<'JS'
(function(){var T=TL,$=T.$,url=$('#tlUrl'),cap=$('#tlCap'),me=$('#tlMe'),goal='get noticed by this person and start a relationship',out=$('#tlOut'),POST=null,lastUrl='';
me.value=T.ls('tl_me');
T.$$('#tlGoal button').forEach(function(b){b.onclick=function(){T.$$('#tlGoal button').forEach(function(x){x.classList.toggle('on',x===b)});goal=b.getAttribute('data-v')}});
var SYS="You write comments on other people's social media posts for a professional who wants to be noticed as someone worth following. Each comment must add something the post did not say. Never generic praise.\n\n"+
"Write exactly 5 comments, each a different style, in this order:\n1. style \"Expert angle\": adds a distinction or specific case the post did not make.\n2. style \"Take it further\": takes the post's idea one logical step further.\n3. style \"Sharp question\": one thoughtful question a skimmer would never ask, with one line of context.\n4. style \"Friendly nuance\": a respectful edge case, or a small 'yes, and'.\n5. style \"Short and warm\": 1 or 2 short sentences reacting to one exact detail of the post.\n\n"+
"Rules:\n- Start each comment by anchoring on something exact from this post (a phrase, a number, a detail), not something that fits any post.\n- One idea per comment. Contractions. Short sentences. Sound like a real person typing on a phone.\n- Never invent personal results, client stories, numbers or credentials.\n- No em dashes, no en dashes, no semicolons, no hashtags. At most one emoji in the whole set.\n- Never use: great post, love this, so true, couldn't agree more, this resonates, thanks for sharing, spot on, game changer, unlock, elevate, leverage, insightful.\n- No self-promotion, no pitch.\n- If the post is personal news (a launch, a birthday, a win), keep every comment warm and genuine, still specific.\n- Write in the same language as the post.\n\n"+
'Reply with JSON only: {"comments":[{"style":"Expert angle","text":"..."},{"style":"...","text":"..."}]}';
function getPost(u){if(POST&&lastUrl===u)return Promise.resolve(POST);if(!u)return Promise.resolve({platform:'Instagram',author:'',text:'',said:'',image:''});
T.busy(true,'Reading the post...');return IRAI.post('ir_tl_post',{url:u}).then(function(j){if(!j||!j.ok){var e=new Error((j&&j.err)||'This post did not open.');e.paste=1;throw e}POST=j.post;lastUrl=u;return POST})}
$('#tlForm').onsubmit=function(e){e.preventDefault();T.err('');var u=url.value.trim(),c=cap.value.trim();if(!u&&!c){T.err('Paste the link to the post.');return}
T.ls('tl_me',me.value.trim());out.innerHTML='';
getPost(u).then(function(p){p=Object.assign({},p);if(c)p.text=c;if(!(p.text||'').trim()&&!(p.said||'').trim()){var e=new Error('We could not read the words of this post. Tap "Paste the caption instead" and paste them.');e.paste=1;throw e}
var long=['LinkedIn','Facebook','Website'].indexOf(p.platform)>=0,user='Platform: '+p.platform+'\n'+(p.author?'Posted by: '+p.author+'\n':'')+'Comment length: comments 1 to 4 '+(long?'50 to 110':'25 to 70')+' words, comment 5 8 to 25 words.\n'+(p.text?'The caption:\n<<<\n'+p.text.slice(0,3000)+'\n>>>\n':'')+(p.said?'What is said in the video:\n<<<\n'+p.said.slice(0,2500)+'\n>>>\n':'')+(me.value.trim()?'The person commenting: '+me.value.trim()+'\n':'')+'Their goal: '+goal;
T.busy(true,'Writing 5 comments...');
return IRAI.askJSON({system:SYS,user:user,max:3000,onStatus:function(s){T.busy(true,s)}},function(j){return (j.comments||j).length>=3}).then(function(j){T.busy(false);render(p,u,(j.comments||j))})}).catch(function(x){T.busy(false);T.err(x.message);if(x.paste)$('#tlMore').open=true})};
url.oninput=function(){POST=null};
function render(p,u,list){var h='';
h+='<div class="tl-res tl-post">'+(p.image?'<img src="'+T.esc(p.image)+'" alt="" referrerpolicy="no-referrer" onerror="this.remove()">':'')+'<div><span class="tl-tag">'+T.esc(p.platform||'Post')+(p.author?' · @'+T.esc(String(p.author).replace(/^@/,'')):'')+'</span><p>'+T.esc((p.text||p.said||'').slice(0,220))+((p.text||p.said||'').length>220?'...':'')+'</p></div></div>';
h+='<h2>Pick a comment</h2>';
list.slice(0,5).forEach(function(c,i){var t=String(c.text||c||'').replace(/;/g,',');h+='<div class="tl-res"><div class="tl-res-h"><span class="tl-tag">'+T.esc(c.style||('Option '+(i+1)))+'</span></div><textarea id="tlC'+i+'" rows="'+Math.min(8,Math.max(2,Math.ceil(t.length/40)))+'">'+T.esc(t)+'</textarea><div class="tl-acts"><button type="button" class="tl-copy" data-copy="#tlC'+i+'">Copy</button>'+(u?'<a class="tl-btn2" href="'+T.esc(p.url||u)+'" target="_blank" rel="noopener">Open the post</a>':'')+'</div></div>'});
h+='<p class="tl-note">Tip: change one or two words so it sounds like you, then post it within the first hour of the post going live. Early, thoughtful comments get seen the most.</p>';
out.innerHTML=h;T.bindCopy(out);out.scrollIntoView({behavior:'smooth',block:'start'})}
})();
JS;
	ir_tl_close( $js );
}

function ir_tl_page_flyer() {
	ir_tl_open( 'Flyer Remixer', 'flyer', 'Drop any flyer or graphic. Get the prompt to recreate its style, plus every line of text rewritten for your niche in the same layout.', '#F97316', '#EC4899' );
	?>
<form class="tl-card" id="tlForm">
 <input type="file" id="tlFile" accept="image/*" hidden>
 <div class="tl-drop" id="tlDrop"><span class="e">🖼️</span><b>Tap to add the flyer</b><span>JPG, PNG or WebP. A screenshot works too.</span><div class="tl-thumbs" id="tlThumb"></div></div>
 <label class="tl-f"><span>What is in the main picture? <em>optional, makes the prompt exact</em></span><input id="tlPic" placeholder="e.g. smiling woman holding a serum bottle, gold background"></label>
 <div class="tl-two"><label class="tl-f"><span>Your niche or business type</span><input id="tlNiche" placeholder="e.g. esthetician, real estate agent"></label><label class="tl-f"><span>Business name <em>optional</em></span><input id="tlBiz" placeholder="e.g. Glow by Amaka"></label></div>
 <label class="tl-f"><span>What should your flyer promote? <em>optional</em></span><textarea id="tlOffer" rows="2" placeholder="e.g. Black Friday: 30% off facials, book by 30 November"></textarea></label>
 <label class="tl-f"><span>Your brand colours <em>optional</em></span><input id="tlColors" placeholder="e.g. soft pink and gold"></label>
 <div class="tl-err" id="tlErr" hidden></div>
 <button class="tl-go" id="tlGo">✨ Remix this flyer</button>
 <div class="tl-status" id="tlStatus" hidden></div>
</form>
<div class="tl-out" id="tlOut"></div>
<?php
	$js = <<<'JS'
(function(){var T=TL,$=T.$,file=null,out=$('#tlOut'),READ=null;
$('#tlNiche').value=T.ls('tl_niche');$('#tlBiz').value=T.ls('tl_biz');$('#tlColors').value=T.ls('tl_colors');
T.drop($('#tlDrop'),$('#tlFile'),function(fs){file=fs.filter(function(f){return /^image\//.test(f.type)})[0]||null;READ=null;$('#tlThumb').innerHTML=file?'<img src="'+URL.createObjectURL(file)+'" alt="">':'';if(!file&&fs.length)T.err('That file is not a picture. Use a JPG, PNG or WebP.');if(file)read()});
function read(){if(READ)return READ;var f=file;READ=IRMEDIA.toCanvas(f,1400).then(function(c){return Promise.all([IRMEDIA.readImage(c).catch(function(){return []}),IRMEDIA.palette(c)]).then(function(r){return{w:c.width,h:c.height,blocks:r[0],pal:r[1]}})});READ.catch(function(){READ=null});return READ}
function ratio(w,h){var r=w/h,opts=[['1:1',1],['4:5',0.8],['9:16',0.5625],['16:9',1.7778],['3:4',0.75],['2:3',0.6667],['A4 portrait',0.707]];opts.sort(function(a,b){return Math.abs(a[1]-r)-Math.abs(b[1]-r)});return opts[0][0]}
var SYS="You are a senior graphic designer and an expert at writing prompts for AI image tools (Midjourney, ChatGPT images, Ideogram, Leonardo, Canva).\n"+
"You cannot see the flyer itself. You get what was measured from it: every text block with its position and relative size (100 = the biggest text), the main colours with their share, brightness, saturation, the shape, and the user's own description of the main picture. Use these facts to rebuild its look.\n\n"+
"Do four things:\n1. Describe the design style precisely: layout, hierarchy, colour palette with hex codes, mood, likely fonts, graphic elements, and what the picture shows (from the description).\n"+
"2. Write a ready image prompt of 80 to 140 words for the background artwork only, with NO text in the image, leaving empty space where the text sits.\n"+
"3. List every text block in reading order with its role (headline, subheadline, offer, date, list item, call to action, contact, small print), where it sits and how it is styled.\n"+
"4. Rewrite every text block for the user's niche and business, keeping the same role, structure and about the same length. Keep numbers and dates as [DATE], [PRICE], [PHONE] placeholders unless the user gave them. Never invent prices, dates, phone numbers or claims.\n"+
"Also write a second prompt that includes the new text in quotes, for tools that can render text (Ideogram, ChatGPT images).\nNo em dashes or en dashes.\n"+
'Reply with JSON only: {"design_summary":"2 sentences","image_prompt":"...","prompt_with_text":"...","negative_prompt":"...","aspect_ratio":"4:5","palette":[{"hex":"#000000","use":"background"}],"fonts":"font advice with similar free Google fonts","layout":[{"role":"Headline","original":"...","new":"...","position":"top center","style":"bold condensed sans, white, all caps"}],"tips":["..."]}';
$('#tlForm').onsubmit=function(e){e.preventDefault();T.err('');if(!file){T.err('Add the flyer first.');return}var n=$('#tlNiche').value.trim();if(!n){T.err('Tell us your niche or business type.');return}
T.ls('tl_niche',n);T.ls('tl_biz',$('#tlBiz').value.trim());T.ls('tl_colors',$('#tlColors').value.trim());out.innerHTML='';
T.busy(true,'Reading the flyer on your phone...');
read().then(function(r){var b=r.blocks,p=r.pal;
var user='FLYER MEASUREMENTS\nShape: '+r.w+' x '+r.h+' pixels, closest ratio '+ratio(r.w,r.h)+'.\nMain colours (share of the image): '+p.colors.map(function(c){return c.hex+' '+c.share+'%'}).join(', ')+'.\nBrightness '+p.bright+'/100, colour saturation '+p.sat+'/100, '+(p.spread>40?'detailed photo-like image':p.spread>18?'mix of photo and flat design':'mostly flat graphic design')+'.\n'+
'Text blocks, top to bottom:\n'+(b.length?b.map(function(x,i){return (i+1)+'. "'+x.text+'" at '+x.pos+', size '+x.size}).join('\n'):'(no readable text found)')+'\n'+
'The main picture (from the user): '+($('#tlPic').value.trim()||'not described, infer a fitting picture for the niche')+'\n\nMY DETAILS\nNiche or business type: '+n+'\n'+($('#tlBiz').value.trim()?'Business name: '+$('#tlBiz').value.trim()+'\n':'')+($('#tlOffer').value.trim()?'What the flyer should promote: '+$('#tlOffer').value.trim()+'\n':'')+($('#tlColors').value.trim()?'My brand colours: '+$('#tlColors').value.trim()+' (use these instead of the original colours in the prompts)\n':'');
T.busy(true,'Designing your version...');
return IRAI.askJSON({system:SYS,user:user,max:4000,onStatus:function(s){T.busy(true,s)}},function(j){return j.image_prompt}).then(function(d){T.busy(false);if(!d.aspect_ratio)d.aspect_ratio=ratio(r.w,r.h);render(d)})}).catch(function(x){T.busy(false);T.err(x.message)})};
function block(title,id,text,note){return '<div class="tl-res"><div class="tl-res-h"><b>'+title+'</b><button type="button" class="tl-copy" data-copy="#'+id+'">Copy</button></div>'+(note?'<p class="tl-txt" style="color:var(--muted);font-size:13.5px;margin:0 0 8px">'+note+'</p>':'')+'<p class="tl-txt" id="'+id+'">'+T.esc(text)+'</p></div>'}
function render(d){var h='';['layout','palette','tips'].forEach(function(k){if(!Array.isArray(d[k]))d[k]=d[k]?[d[k]]:[]});d.layout=d.layout.map(function(l){return typeof l==='string'?{new:l}:l||{}});d.palette=d.palette.map(function(c){return typeof c==='string'?{hex:c}:c||{}});
if(d.design_summary)h+='<p class="tl-note">'+T.esc(d.design_summary)+'</p>';
h+='<h2>1. Recreate the image style</h2>';
h+=block('Image prompt (no text)','tlP1',d.image_prompt+(d.aspect_ratio&&/\d+:\d+/.test(d.aspect_ratio)?' --ar '+d.aspect_ratio:''),'Use in Midjourney, Leonardo or ChatGPT. Add your text after in Canva.');
if(d.prompt_with_text)h+=block('Prompt with your new text','tlP2',d.prompt_with_text,'Use in Ideogram or ChatGPT images, which can write text.');
if(d.negative_prompt)h+=block('Avoid (negative prompt)','tlP3',d.negative_prompt);
if((d.layout||[]).length){h+='<h2>2. Your text, same structure</h2><div class="tl-res">';
d.layout.forEach(function(l,i){h+='<div class="tl-beat"><span class="t">'+T.esc(l.role||'')+'</span><div>'+(l.original?'<p><b>Was</b>'+T.esc(l.original)+'</p>':'')+'<p><b>Yours</b><span id="tlL'+i+'">'+T.esc(l.new||'')+'</span> <button type="button" class="tl-copy" data-copy="#tlL'+i+'" style="padding:4px 9px;font-size:12px">Copy</button></p>'+((l.position||l.style)?'<p style="color:var(--muted);font-size:13px">'+T.esc([l.position,l.style].filter(Boolean).join(' · '))+'</p>':'')+'</div></div>'});h+='</div>'}
if((d.palette||[]).length||d.fonts){h+='<h2>3. Colours and fonts</h2><div class="tl-res">';if((d.palette||[]).length){h+='<div class="tl-sw">'+d.palette.map(function(c){return '<span><i style="background:'+T.esc(c.hex)+'"></i>'+T.esc(c.hex)+(c.use?' · '+T.esc(c.use):'')+'</span>'}).join('')+'</div>'}if(d.fonts)h+='<p class="tl-txt" style="margin-top:10px">'+T.esc(d.fonts)+'</p>';h+='</div>'}
if((d.tips||[]).length)h+='<h2>Tips</h2><div class="tl-res"><ul class="tl-list">'+d.tips.map(function(t){return '<li>'+T.esc(t)+'</li>'}).join('')+'</ul></div>';
out.innerHTML=h;T.bindCopy(out);out.scrollIntoView({behavior:'smooth',block:'start'})}
})();
JS;
	ir_tl_close( $js );
}

function ir_tl_page_clone() {
	ir_tl_open( 'Content Cloner', 'clone', 'Paste any viral post or upload it. We find why it works, then give you the same structure for your topic: script, on-screen text, filming direction and the caption.', '#2563EB', '#22D3EE' );
	?>
<form class="tl-card" id="tlForm">
 <label class="tl-f"><span>Paste the link</span><input id="tlUrl" type="url" inputmode="url" placeholder="TikTok or Instagram link" autocomplete="off"></label>
 <div class="tl-or">or</div>
 <input type="file" id="tlFile" accept="video/*,image/*" multiple hidden>
 <div class="tl-drop" id="tlDrop"><span class="e">📲</span><b>Upload the video or pictures</b><span>Any video, meme, skit or carousel. For a carousel, add every slide.</span><div class="tl-thumbs" id="tlThumb"></div></div>
 <label class="tl-f"><span>What is your version about?</span><textarea id="tlTopic" rows="2" placeholder="e.g. 3 mistakes estheticians make with their Instagram bio"></textarea></label>
 <div class="tl-two"><label class="tl-f"><span>Your niche or brand <em>optional, saved</em></span><input id="tlNiche" placeholder="e.g. skincare studio in Lagos"></label><label class="tl-f"><span>Anything to include? <em>optional</em></span><input id="tlExtra" placeholder="e.g. end with: DM me GLOW"></label></div>
 <div class="tl-err" id="tlErr" hidden></div>
 <button class="tl-go" id="tlGo">✨ Clone this content</button>
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
var SYS="You are a short-form content strategist who reverse engineers viral posts: talking head, skit, meme, text on screen, voiceover with b-roll, tutorial, before and after, trend audio, POV, carousel, single image.\n"+
"You get what was read from the original: the words spoken with timestamps, the text shown on screen with timestamps, the format, pace and length, and the caption (or the text of each slide for carousels). Work out the format and why it spread from these facts.\n\n"+
"Step 1, study it: the hook (first 1 to 3 seconds or first slide), the beats, the on-screen text, the pacing, the turn, the payoff and the call to action.\nStep 2, map its structure as beats with timestamps (or slide numbers).\n"+
"Step 3, remake it for the user's topic: same format, same beats, same pacing and length, same kind of hook and payoff. Swap only the subject. For memes and trends keep the joke's mechanics and make the punchline about the user's world. Write new on-screen text line by line. For carousels write the new text for every slide.\n"+
"Step 4, give clear filming and editing direction a beginner can follow with a phone (or design direction for slides).\n"+
"Step 5, write the caption: first line under 125 characters that works alone, real value or context, one engagement trigger, one call to action. 25 to 60 words for videos, 70 to 130 for carousels. Give exactly 3 lowercase hashtags separately.\n\n"+
"Rules: natural spoken language, contractions, short sentences. Never invent results, numbers, testimonials or credentials, use [YOUR NUMBER] style placeholders. No em dashes or en dashes. Do not copy the original's words, only its structure.\n\n"+
'Reply with JSON only: {"content_type":"e.g. Text-on-screen meme with trending audio","summary":"one or two sentences","why_it_works":["..."],"structure":[{"time":"0-2s","part":"Hook","what_happens":"...","on_screen_text":"...","audio":"..."}],"keep":["..."],"remake":{"title":"...","format":"...","length":"about 15 seconds","hooks":["3 hook options"],"script":[{"time":"0-2s","visual":"what we see and how to film it","on_screen_text":"...","say":"spoken words or empty"}],"shot_list":["..."],"audio":"...","editing":"..."},"caption":"...","hashtags":["...","...","..."]}';
function fmt(t){t=Math.max(0,Math.round(t||0));return Math.floor(t/60)+':'+('0'+t%60).slice(-2)}
$('#tlForm').onsubmit=function(e){e.preventDefault();T.err('');var u=$('#tlUrl').value.trim(),t=$('#tlTopic').value.trim();
if(!u&&!files.length){T.err('Paste a link or upload the video or pictures.');return}if(!t){T.err('Tell us what your version is about.');return}
if(u&&/youtu\.?be/i.test(u)){T.err('YouTube does not let websites download Shorts. Save the Short to your phone and upload it here.');return}
T.ls('tl_niche',$('#tlNiche').value.trim());out.innerHTML='';
(u?getLink(u):getFiles()).then(function(m){
  var i=m.info||{},sp=m.speech||{segs:[]},w=m.seen,L=[];
  L.push('THE ORIGINAL: '+(i.platform||'post')+(i.author?' by @'+String(i.author).replace(/^@/,''):'')+'.');
  if(w){L.push('Format: '+w.desc)}else if(i.dur){L.push('Length: '+Math.round(i.dur)+' seconds.')}
  if(sp.segs&&sp.segs.length){L.push('\nSpoken words with timestamps:\n'+sp.segs.map(function(s){return (s.t!=null?'['+fmt(s.t)+'] ':'')+s.text}).join('\n').slice(0,4000))}else if(!m.slides){L.push('\nNo speech (music or trending audio only).')}
  if(w&&w.cards.length){L.push('\nText shown on screen, in order:\n'+w.cards.map(function(c){return '['+fmt(c.t)+'] '+c.text}).join('\n').slice(0,2500))}
  if(m.slides){L.push('\nSlides:\n'+m.slides.map(function(s,k){return 'Slide '+(k+1)+': '+(s.text||'(no text, picture only)')+' (colours '+s.colors+')'}).join('\n').slice(0,4000))}
  if(i.caption){L.push('\nOriginal caption, for context only:\n'+i.caption.slice(0,1200))}
  if(!(sp.segs&&sp.segs.length)&&!(w&&w.cards.length)&&!(m.slides&&m.slides.some(function(s){return s.text}))&&!i.caption){throw new Error('We could not find any words or text in this post. Try another post, or upload the video file.')}
  L.push('\nMY VERSION\nTopic: '+t+($('#tlNiche').value.trim()?'\nNiche or brand: '+$('#tlNiche').value.trim():'')+($('#tlExtra').value.trim()?'\nAlso include: '+$('#tlExtra').value.trim():''));
  step('Finding the viral structure and writing your version...',0.95);
  return IRAI.askJSON({system:SYS,user:L.join('\n'),max:6000,onStatus:function(s){step(s,0.95)}},function(j){return j.remake}).then(function(d){T.busy(false);d.hashtags=(d.hashtags||[]).slice(0,3).map(function(h){return '#'+String(h).toLowerCase().replace(/[^a-z0-9_]/g,'')});render(d,m)});
}).catch(function(x){T.busy(false);IRMEDIA.stopOcr();T.err(x.message||'Something went wrong. Please try again.')})};
function li(a){return(a||[]).length?'<ul class="tl-list">'+a.map(function(x){return '<li>'+T.esc(x)+'</li>'}).join('')+'</ul>':''}
function render(d,m){var A=function(x){return Array.isArray(x)?x:x?[x]:[]};d.why_it_works=A(d.why_it_works);d.structure=A(d.structure);d.keep=A(d.keep);d.remake=d.remake||{};var r=d.remake,h='',plain=[];r.hooks=A(r.hooks);r.script=A(r.script).map(function(x){return typeof x==='string'?{say:x}:x||{}});r.shot_list=A(r.shot_list);d.structure=d.structure.map(function(x){return typeof x==='string'?{what_happens:x}:x||{}});
h+='<h2>Why the original works</h2><div class="tl-res"><span class="tl-tag">'+T.esc(d.content_type||'')+'</span><p class="tl-txt" style="margin:6px 0 8px">'+T.esc(d.summary||'')+'</p>'+li(d.why_it_works)+'</div>';
if((d.structure||[]).length){h+='<details class="tl-res tl-more"><summary>See the original structure, beat by beat</summary>'+d.structure.map(function(b){return '<div class="tl-beat"><span class="t">'+T.esc(b.time||'')+'</span><div><p><b>'+T.esc(b.part||'')+'</b>'+T.esc(b.what_happens||'')+'</p>'+(b.on_screen_text?'<p><b>Text</b>'+T.esc(b.on_screen_text)+'</p>':'')+(b.audio?'<p><b>Audio</b>'+T.esc(b.audio)+'</p>':'')+'</div></div>'}).join('')+(d.keep&&d.keep.length?'<p style="margin:12px 0 4px;font-weight:700">Keep these when you remake it:</p>'+li(d.keep):'')+'</details>'}
h+='<h2>Your version'+(r.title?': '+T.esc(r.title):'')+'</h2>';
if(r.format||r.length)h+='<p class="tl-note">'+T.esc([r.format,r.length].filter(Boolean).join(' · '))+'</p>';
if((r.hooks||[]).length){h+='<div class="tl-res"><div class="tl-res-h"><b>Hook options</b></div>'+r.hooks.map(function(x,i){return '<p class="tl-txt" style="margin:0 0 8px"><span id="tlH'+i+'">'+T.esc(x)+'</span> <button type="button" class="tl-copy" data-copy="#tlH'+i+'" style="padding:4px 9px;font-size:12px">Copy</button></p>'}).join('')+'</div>'}
h+='<div class="tl-res"><div class="tl-res-h"><b>Script and direction</b><button type="button" class="tl-copy" id="tlAll">Copy all</button></div>';
(r.script||[]).forEach(function(s){plain.push((s.time?'['+s.time+'] ':'')+(s.visual?'SHOW: '+s.visual+'\n':'')+(s.on_screen_text?'TEXT: '+s.on_screen_text+'\n':'')+(s.say?'SAY: '+s.say:''));h+='<div class="tl-beat"><span class="t">'+T.esc(s.time||'')+'</span><div>'+(s.visual?'<p><b>Show</b>'+T.esc(s.visual)+'</p>':'')+(s.on_screen_text?'<p><b>Text</b>'+T.esc(s.on_screen_text)+'</p>':'')+(s.say?'<p><b>Say</b>'+T.esc(s.say)+'</p>':'')+'</div></div>'});
h+='</div>';
if((r.shot_list||[]).length||r.audio||r.editing){h+='<div class="tl-res"><div class="tl-res-h"><b>How to film and edit it</b></div>'+li(r.shot_list)+(r.audio?'<p class="tl-txt" style="margin-top:8px"><b>Audio:</b> '+T.esc(r.audio)+'</p>':'')+(r.editing?'<p class="tl-txt" style="margin-top:6px"><b>Editing:</b> '+T.esc(r.editing)+'</p>':'')+'</div>'}
var cp=(d.caption||'')+((d.hashtags||[]).length?'\n\n'+d.hashtags.join(' '):'');
h+='<h2>Caption</h2><div class="tl-res"><div class="tl-res-h"><b>Ready to post</b><button type="button" class="tl-copy" data-copy="#tlCap">Copy</button></div><p class="tl-txt" id="tlCap">'+T.esc(cp)+'</p></div>';
out.innerHTML=h;T.bindCopy(out);var all=$('#tlAll');if(all)all.onclick=function(){T.copy(((r.hooks||[]).length?'HOOK: '+r.hooks[0]+'\n\n':'')+plain.join('\n\n')+'\n\nCAPTION:\n'+cp,all)};out.scrollIntoView({behavior:'smooth',block:'start'})}
})();
JS;
	ir_tl_close( $js );
}
