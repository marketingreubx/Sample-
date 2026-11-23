<?php
/**
 * Plugin Name: IR Cold Outreach tools
 * Description: Two AI tools inside the Cold Outreach System. Reply Handler: paste or screenshot what a prospect said and get the next message that keeps selling the member's offer, using the system's objection scripts. Cold Call Script: paste a Google Business or website link and get an exact phone script with gatekeeper, voicemail and objection answers.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** The member's offer: what they saved in the Smart Writer, plus price and booking link. Fields sent with a request win and are saved. */
function ir_or_me( $in, $save = true ) {
	$uid = get_current_user_id();
	$p = get_user_meta( $uid, 'ir_rw_profile', true );
	$p = is_array( $p ) ? $p : [];
	$keys = [ 'name', 'job', 'client', 'result', 'service', 'price', 'proof', 'link', 'book' ];
	$changed = false;
	foreach ( $keys as $k ) {
		if ( isset( $in[ 'me_' . $k ] ) ) {
			$v = sanitize_textarea_field( (string) $in[ 'me_' . $k ] );
			if ( $v !== ( $p[ $k ] ?? '' ) ) { $p[ $k ] = $v; $changed = true; }
		}
	}
	if ( $save && $changed && $uid ) { update_user_meta( $uid, 'ir_rw_profile', $p ); }
	$lab = [ 'name' => 'My name', 'job' => 'What I do', 'client' => 'Who I help', 'result' => 'The result I get them', 'service' => 'How I do it (my offer)', 'price' => 'My price or packages', 'proof' => 'Proof (results, clients, numbers)', 'link' => 'My portfolio or page', 'book' => 'Where they book a call' ];
	$o = [];
	foreach ( $lab as $k => $l ) { $v = trim( (string) ( $p[ $k ] ?? '' ) ); if ( $v !== '' ) { $o[] = $l . ': ' . mb_substr( $v, 0, 700 ); } }
	return $o ? implode( "\n", $o ) : 'Not given. Assume a freelancer who helps small businesses get more clients online. Do not invent a price or results.';
}

/** The system's own scripts for a set of sections, cut to the rules and the first scripts of each lesson. */
function ir_or_playbook( $skeys, $per = 1100 ) {
	if ( ! function_exists( 'ir_pb_lessons' ) ) { return ''; }
	$ck = 'ir_or_pb_' . md5( implode( ',', $skeys ) . $per . filemtime( WP_CONTENT_DIR . '/ir-playbook/playbook.json' ) );
	$c = get_transient( $ck );
	if ( is_string( $c ) && $c !== '' ) { return $c; }
	$out = '';
	foreach ( ir_pb_lessons() as $l ) {
		if ( ! in_array( $l['skey'], $skeys, true ) ) { continue; }
		$t = preg_replace( '#<(br|/p|/li|/h\d|/div)[^>]*>#i', "\n", (string) $l['html'] );
		$t = html_entity_decode( wp_strip_all_tags( $t ), ENT_QUOTES, 'UTF-8' );
		$t = trim( preg_replace( [ '/[ \t]+/', '/\n\s*\n+/', '/Copy script/i' ], [ ' ', "\n", '' ], $t ) );
		$out .= '### ' . $l['title'] . ' [' . $l['slug'] . "]\n" . mb_substr( $t, 0, $per ) . "\n\n";
	}
	set_transient( $ck, $out, DAY_IN_SECONDS );
	return $out;
}

/** Images a member dropped in (screenshots), ready for the AI. */
function ir_or_images( $files, $max = 4 ) {
	$parts = [];
	foreach ( (array) $files as $f ) {
		if ( count( $parts ) >= $max ) { break; }
		if ( strpos( (string) $f['type'], 'image/' ) === 0 && strlen( $f['bytes'] ) < 8 * MB_IN_BYTES ) { $parts[] = [ 'inline_data' => [ 'mime_type' => $f['type'], 'data' => base64_encode( $f['bytes'] ) ] ]; }
	}
	return $parts;
}

function ir_or_clean( $s ) {
	$s = str_replace( [ "\u{2011}", "\u{2010}" ], '-', (string) $s );
	return trim( preg_replace( '/\s*[\x{2013}\x{2014}]\s*/u', ', ', $s ) );
}

/* ---------- Reply Handler ---------- */

function ir_or_reply( $in, $files ) {
	$reply = trim( (string) ( $in['reply'] ?? '' ) );
	$imgs = ir_or_images( $files );
	if ( $reply === '' && ! $imgs ) { return new WP_Error( 'in', 'Paste what they said, or add a screenshot of the chat.' ); }
	$ch = ( $in['ch'] ?? '' ) === 'email' ? 'email' : 'dm';
	$me = ir_or_me( $in );
	$thread = json_decode( (string) ( $in['thread'] ?? '' ), true );
	$conv = '';
	foreach ( array_slice( (array) $thread, -14 ) as $t ) {
		if ( ! is_array( $t ) || trim( (string) ( $t['t'] ?? '' ) ) === '' ) { continue; }
		$conv .= ( ( $t['who'] ?? '' ) === 'me' ? 'ME' : 'THEM' ) . ': ' . mb_substr( trim( (string) $t['t'] ), 0, 1200 ) . "\n";
	}
	$first = trim( (string) ( $in['first'] ?? '' ) );
	if ( $conv === '' && $first !== '' ) { $conv = 'ME (my first message): ' . mb_substr( $first, 0, 1500 ) . "\n"; }
	$pb = ir_or_playbook( $ch === 'email' ? [ 'email-replies', 'objections' ] : [ 'objections', 'closing' ], $ch === 'email' ? 900 : 1100 );
	ir_ai_step( 'Reading their reply...' );
	$sys = "You are the closer for a freelancer who sent cold outreach by " . ( $ch === 'email' ? 'email' : 'DM (Instagram, WhatsApp or LinkedIn)' ) . ". The prospect has replied. Write the freelancer's next " . ( $ch === 'email' ? 'email' : 'message or messages' ) . " so the conversation keeps moving toward a paying client, selling the freelancer's own offer.\n\n"
		. "HOW TO DO IT\n"
		. "1. Read the conversation so far and the latest reply" . ( $imgs ? ' (the screenshot shows the chat: the prospect is the other person, usually the grey or left bubbles; their LAST message is the reply to answer)' : '' ) . ". Decide the stage: interested, asked_price, asked_info, asked_who, question, objection, not_now, not_interested, stop or unclear.\n"
		. "2. If it is an objection or a known situation, find the matching lesson in THE PLAYBOOK below and follow its approach: its rule, its order of steps and the spirit of its script. Adapt it to this exact conversation and this freelancer. Never copy a template with blanks or braces.\n"
		. "3. Keep selling, gently. Tie what they said back to the result the freelancer gets for clients like them. Give ONE clear next step per reply: a quick call, a WhatsApp chat, a free mini audit, a quote or a booking link if one is given. Questions get a straight answer first.\n"
		. "4. Price: if the freelancer gave a price, use it the way the playbook says (value first, then the number or a range, then the next step). If no price is given, never make one up: ask one or two quick questions about their needs and offer a short call to tailor it.\n"
		. "5. Stop, remove me or rude: one polite line, no selling. Not interested: thank them, one soft door-opener, no pressure.\n"
		. "6. Sound like a person texting a business owner: warm, confident, relaxed, never needy or pushy. Match their language, energy and length. Use their name if known.\n"
		. "7. Answer EVERY question in their reply, directly, in the first message (price, what is included, how long, how it works). Then end with ONE clear next step, asked as a question.\n"
		. "8. A bad past experience: acknowledge it, say plainly what is different about how this freelancer works (from their offer), and offer a low-risk first step.\n"
		. "9. Never write placeholders like [price], {name}, [link] or [time]. If something is unknown, say it in plain words (for example: it depends on how many posts you need, I can give you the exact number after a 10-minute call).\n"
		. ( $ch === 'email' ? "FORMAT: one email under 130 words with a short greeting and the freelancer's first name as sign-off.\n" : "FORMAT: 1 to 3 short messages to send one after another, each under 55 words, no greeting like Dear.\n" )
		. "RULES: never invent results, client names, numbers, prices, discounts or guarantees the freelancer did not give. No em dashes or en dashes. No hashtags.\n\n"
		. 'Reply with JSON only: {"stage":"","they_mean":"what they really mean, one sentence","objection":"the objection in a few words, or none","lesson":"the [slug] of the playbook lesson you followed, or none","messages":["message to send"],"why":"one or two sentences on why this reply works","if_they":[{"say":"a likely next reply from them","you":"what to send back"},{"say":"","you":""}],"follow_up":{"when":"when to follow up if they go quiet","message":"the follow-up message"}}';
	$user = "THE FREELANCER\n" . $me
		. "\n\nCHANNEL: " . ( $ch === 'email' ? 'Email' : 'DM' )
		. ( trim( (string) ( $in['who'] ?? '' ) ) !== '' ? "\nTHE PROSPECT: " . sanitize_text_field( (string) $in['who'] ) : '' )
		. "\n\nTHE CONVERSATION SO FAR\n" . ( $conv !== '' ? $conv : "(not given: assume the freelancer sent a short cold message about their service)\n" )
		. "\nTHEIR LATEST REPLY\n" . ( $reply !== '' ? mb_substr( $reply, 0, 3000 ) : '(in the screenshot)' )
		. "\n\nTHE PLAYBOOK (the system's own scripts)\n" . $pb;
	ir_ai_step( 'Writing your reply...' );
	$o = [ 'json' => 1, 'temp' => 0.6, 'max' => 3000, 'think' => 512, 'timeout' => 90 ];
	$raw = $imgs ? ir_ai_gemini( $sys, array_merge( $imgs, [ [ 'text' => $user ] ] ), $o ) : ir_ai_text( $sys, $user, $o + [ 'strict' => 1 ] );
	if ( is_wp_error( $raw ) ) { $raw = $imgs ? ir_ai_gemini( $sys, array_merge( $imgs, [ [ 'text' => $user ] ] ), $o ) : ir_ai_text( $sys, $user, $o ); }
	$j = is_wp_error( $raw ) ? null : ir_ai_json( $raw );
	$msgs = array_values( array_filter( array_map( 'ir_or_clean', array_filter( (array) ( $j['messages'] ?? [] ), 'is_string' ) ) ) );
	if ( ! $msgs ) { return new WP_Error( 'ai', 'The AI is busy right now. Tap the button again in a few seconds.' ); }
	$if = [];
	foreach ( (array) ( $j['if_they'] ?? [] ) as $x ) { if ( is_array( $x ) && ! empty( $x['say'] ) && ! empty( $x['you'] ) ) { $if[] = [ 'say' => ir_or_clean( $x['say'] ), 'you' => ir_or_clean( $x['you'] ) ]; } }
	$fu = (array) ( $j['follow_up'] ?? [] );
	$slug = preg_replace( '/[^a-z0-9-]/', '', strtolower( trim( (string) ( $j['lesson'] ?? '' ), '[] ' ) ) );
	$lt = '';
	if ( $slug && function_exists( 'ir_pb_lessons' ) ) { foreach ( ir_pb_lessons() as $l ) { if ( $l['slug'] === $slug ) { $lt = $l['title']; break; } } }
	return [
		'stage' => sanitize_key( (string) ( $j['stage'] ?? '' ) ), 'they_mean' => ir_or_clean( $j['they_mean'] ?? '' ), 'objection' => ir_or_clean( $j['objection'] ?? '' ),
		'lesson' => $lt ? [ 'title' => $lt, 'url' => ir_pb_url( $slug ) ] : null, 'messages' => $msgs, 'why' => ir_or_clean( $j['why'] ?? '' ), 'if_they' => array_slice( $if, 0, 3 ),
		'follow_up' => [ 'when' => ir_or_clean( $fu['when'] ?? '' ), 'message' => ir_or_clean( $fu['message'] ?? '' ) ], 'read' => $reply,
	];
}

/* ---------- Cold Call Script ---------- */

/** What we can read about the business behind a Google Business link or a website, including a phone number. */
function ir_or_business( $url ) {
	$url = trim( (string) $url );
	$none = [ 'facts' => '', 'phone' => '', 'phones' => [], 'name' => '', 'kind' => '', 'owner' => '', 'email' => '', 'from' => '' ];
	if ( $url === '' ) { return $none; }
	if ( ! preg_match( '#^https?://#i', $url ) ) { $url = 'https://' . $url; }
	$url = esc_url_raw( $url );
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$google = (bool) preg_match( '/(^|\.)(google\.[a-z.]+|goo\.gl|g\.co|g\.page|maps\.app\.goo\.gl|share\.google)$/', $host );
	$cf = function_exists( 'ir_cf_find' ) ? ir_cf_find( $url ) : [];
	$cf = array_merge( [ 'email' => '', 'phone' => '', 'phones' => [], 'whatsapp' => '', 'owner' => '', 'website' => '', 'about' => '', 'pages' => [] ], (array) $cf );
	$phone = $cf['phone'] !== '' ? $cf['phone'] : ( $cf['whatsapp'] !== '' ? '+' . ltrim( $cf['whatsapp'], '+' ) : '' );
	$from = $phone === '' ? '' : ( $cf['website'] !== '' ? 'their website (' . preg_replace( '#^https?://(www\.)?|/$#', '', $cf['website'] ) . ')' : ( $google ? 'their Google listing' : 'their website' ) );
	$extra = ( $phone !== '' ? "\nPhone: " . $phone : "\nPhone: not found on their pages" )
		. ( $cf['email'] !== '' ? "\nEmail: " . $cf['email'] : '' )
		. ( $cf['owner'] !== '' ? "\nOwner or manager named on their site: " . $cf['owner'] : '' )
		. ( $cf['about'] !== '' ? "\nFrom their About or Team page: " . $cf['about'] : '' );
	if ( $google && function_exists( 'ir_px_google' ) ) {
		$px = ir_px_google( $url );
		if ( is_wp_error( $px ) ) { return $px; }
		$ph = (string) ( $px['phone'] ?? '' );
		if ( $ph !== '' || empty( $px['thin'] ) ) { return [ 'facts' => (string) $px['facts'], 'phone' => $ph, 'phones' => (array) ( $px['phones'] ?? [] ), 'name' => (string) $px['name'], 'kind' => 'google', 'owner' => '', 'email' => (string) ( $px['email'] ?? '' ), 'from' => $ph !== '' ? 'their Google listing' : '' ]; }
		return new WP_Error( 'google', 'We found ' . $px['name'] . ' but Google only shares the name with us. Add a screenshot of their Google listing so we can read the number.' );
	}
	if ( $google ) {
		$r = wp_remote_get( $url, [ 'timeout' => 20, 'redirection' => 6, 'headers' => [ 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36', 'Accept-Language' => 'en-GB,en;q=0.9' ] ] );
		$b = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
		$final = is_wp_error( $r ) ? $url : (string) ( $r['http_response'] && method_exists( $r['http_response'], 'get_response_object' ) ? $r['http_response']->get_response_object()->url : $url );
		$title = function_exists( 'ir_ct_meta' ) ? ir_ct_meta( $b, 'og:title' ) : '';
		$desc = function_exists( 'ir_ct_meta' ) ? ir_ct_meta( $b, 'og:description' ) : '';
		$pname = preg_match( '#/maps/place/([^/@?]+)#', $final . ' ' . $url, $pm ) ? trim( str_replace( '+', ' ', rawurldecode( $pm[1] ) ) ) : '';
		if ( $title === 'Google Maps' ) { $title = ''; }
		$name = $title !== '' ? preg_replace( '/\s*[·\-|].*$/u', '', $title ) : $pname;
		if ( $name === '' && $desc === '' && $cf['website'] === '' ) { return new WP_Error( 'google', 'Google did not share this listing with us. Paste their website link instead, or add a screenshot of their Google listing so we can read the number.' ); }
		return [ 'facts' => "Google Business listing\nName: " . ( $title ?: $pname ) . ( $desc !== '' ? "\nDetails Google shows: " . $desc : '' ) . ( $cf['website'] !== '' ? "\nWebsite: " . $cf['website'] : "\nWebsite: none found on the listing" ) . $extra, 'phone' => $phone, 'phones' => $cf['phones'], 'name' => $name, 'kind' => 'google', 'owner' => $cf['owner'], 'email' => $cf['email'], 'from' => $from ];
	}
	if ( ! function_exists( 'ir_ct_site' ) ) { return new WP_Error( 'site', 'The website reader is not available.' ); }
	$d = ir_ct_site( $url );
	if ( is_wp_error( $d ) ) { return $d; }
	return [ 'facts' => (string) $d['facts'] . $extra, 'phone' => $phone, 'phones' => $cf['phones'], 'name' => (string) $d['name'], 'kind' => 'site', 'owner' => $cf['owner'], 'email' => $cf['email'], 'from' => $from ];
}

/** The call parts are read in order as one conversation: drop any sentence that already came up in an earlier part. */
function ir_or_dedupe( $j ) {
	if ( ! is_array( $j ) ) { return $j; }
	$seen = [];
	$norm = function ( $x ) { return trim( preg_replace( '/[^a-z0-9 ]+/', '', strtolower( preg_replace( '/\s+/', ' ', $x ) ) ) ); };
	$dup = function ( $n ) use ( &$seen ) { if ( strlen( $n ) < 12 ) { return false; } foreach ( $seen as $p ) { if ( $n === $p || ( strlen( $n ) > 25 && ( strpos( $p, $n ) !== false ) ) ) { return true; } similar_text( $n, $p, $pc ); if ( $pc > 85 ) { return true; } } return false; };
	$clean = function ( $text ) use ( $norm, $dup, &$seen ) {
		if ( ! is_string( $text ) || trim( $text ) === '' ) { return $text; }
		$parts = preg_split( '/(?<=[.!?])\s+/', trim( $text ) ); $keep = []; $add = [];
		foreach ( $parts as $p ) { $n = $norm( $p ); if ( $n !== '' && $dup( $n ) ) { continue; } $keep[] = $p; $add[] = $n; }
		foreach ( $add as $n ) { if ( $n !== '' ) { $seen[] = $n; } }
		return $keep ? implode( ' ', $keep ) : $text;
	};
	/* The permission line has its own slot: take it out of the opener when the opener already asks it. */
	if ( ! empty( $j['opener'] ) && ! empty( $j['permission'] ) && is_string( $j['opener'] ) ) {
		$pn = $norm( $j['permission'] ); $op = preg_split( '/(?<=[.!?])\s+/', trim( $j['opener'] ) );
		$op2 = array_values( array_filter( $op, function ( $x ) use ( $norm, $pn ) { $n = $norm( $x ); if ( strlen( $n ) < 12 ) { return true; } similar_text( $n, $pn, $pc ); return ! ( $pc > 80 || strpos( $pn, $n ) !== false ); } ) );
		if ( $op2 ) { $j['opener'] = implode( ' ', $op2 ); }
	}
	foreach ( [ 'opener', 'permission', 'reason' ] as $k ) { if ( isset( $j[ $k ] ) ) { $j[ $k ] = $clean( $j[ $k ] ); } }
	if ( isset( $j['questions'] ) && is_array( $j['questions'] ) ) { $q = []; foreach ( $j['questions'] as $x ) { if ( ! is_string( $x ) ) { continue; } $n = $norm( $x ); if ( $dup( $n ) ) { continue; } $seen[] = $n; $q[] = $x; } $j['questions'] = $q ?: $j['questions']; }
	foreach ( [ 'pitch', 'close' ] as $k ) { if ( isset( $j[ $k ] ) ) { $j[ $k ] = $clean( $j[ $k ] ); } }
	return $j;
}

/** The way to address the person: their first name when research found it, otherwise ma'am or sir. */
function ir_or_address( $j, $owner ) {
	$first = trim( (string) $owner ) !== '' ? explode( ' ', trim( $owner ) )[0] : '';
	$fill = function ( $v ) use ( $first ) {
		if ( ! is_string( $v ) ) { return $v; }
		$v = preg_replace_callback( '/\{\s*(?:their\s+|owner\s*\'?s?\s+|the\s+)?(?:first\s+)?name\s*\}|\[\s*(?:their\s+|owner\s*\'?s?\s+)?(?:first\s+)?name\s*\]|<\s*name\s*>/i', function () use ( $first ) { return $first !== '' ? $first : "ma'am/sir"; }, $v );
		/* Capital at the start of a sentence. */
		$v = preg_replace_callback( "/(^|[.!?]\\s+)ma'am\\/sir/", function ( $m ) { return $m[1] . "Ma'am/Sir"; }, $v );
		return $v;
	};
	return map_deep( $j, $fill );
}

/** Every call script adds the business to the member's prospect list (once a day per business), so the tracker records calls too. */
function ir_or_track_call( $biz, $phone, $email, $owner, $url ) {
	$uid = get_current_user_id();
	if ( ! $uid || trim( (string) $biz ) === '' ) { return; }
	$tk = get_user_meta( $uid, 'ir_pb_trk', true );
	$tk = is_array( $tk ) ? $tk : [];
	$leads = isset( $tk['leads'] ) && is_array( $tk['leads'] ) ? $tk['leads'] : [];
	try { $tz = new DateTimeZone( ! empty( $tk['tz'] ) && is_string( $tk['tz'] ) ? $tk['tz'] : 'Africa/Lagos' ); } catch ( Exception $e ) { $tz = new DateTimeZone( 'Africa/Lagos' ); }
	$today = ( new DateTime( 'now', $tz ) )->format( 'Y-m-d' );
	$key = strtolower( trim( $biz ) );
	foreach ( $leads as $i => $l ) {
		if ( ( $l['ch'] ?? '' ) === 'call' && strtolower( (string) ( $l['h'] ?? '' ) ) === $key ) {
			if ( $phone !== '' ) { $leads[ $i ]['contact']['phone'] = $phone; }
			$tk['leads'] = $leads; update_user_meta( $uid, 'ir_pb_trk', $tk ); return;
		}
	}
	array_unshift( $leads, [
		'id' => base_convert( (string) ( time() * 1000 ), 10, 36 ) . wp_generate_password( 4, false, false ),
		'h' => mb_substr( trim( $biz ), 0, 80 ), 'pl' => 'Phone', 'st' => 'sent', 'sent' => $today, 'fu' => 0, 'ang' => 'Cold call',
		'notes' => trim( 'Call script ready.' . ( $phone !== '' ? ' Phone ' . $phone . '.' : '' ) . ( $owner !== '' ? ' Ask for ' . $owner . '.' : '' ) ),
		'ch' => 'call', 'at' => false, 'name' => $owner !== '' ? explode( ' ', $owner )[0] : '', 'biz' => mb_substr( trim( $biz ), 0, 80 ), 'obs' => '',
		'contact' => array_filter( [ 'phone' => $phone, 'email' => $email, 'site' => $url ] ), 'like' => '', 'log' => [], 'next' => '',
	] );
	$tk['leads'] = array_slice( $leads, 0, 600 );
	update_user_meta( $uid, 'ir_pb_trk', $tk );
}

function ir_or_call( $in, $files ) {
	$url = trim( (string) ( $in['url'] ?? '' ) );
	$name = trim( sanitize_text_field( (string) ( $in['biz'] ?? '' ) ) );
	$notes = trim( sanitize_textarea_field( (string) ( $in['notes'] ?? '' ) ) );
	$imgs = ir_or_images( $files, 3 );
	if ( $url === '' && $name === '' && ! $imgs ) { return new WP_Error( 'in', 'Paste their Google Business or website link, or type the business name.' ); }
	ir_ai_step( 'Reading their business...' );
	$b = ir_or_business( $url );
	if ( is_wp_error( $b ) ) {
		if ( $name === '' && $notes === '' && ! $imgs ) { return $b; }
		$b = [ 'facts' => '', 'phone' => '', 'phones' => [], 'name' => '', 'kind' => '', 'owner' => '', 'email' => '', 'from' => '' ];
	}
	$me = ir_or_me( $in );
	$pb = ir_or_playbook( [ 'closing' ], 1800 ) . ir_or_playbook( [ 'objections' ], 650 );
	$job = (string) ( ( get_user_meta( get_current_user_id(), 'ir_rw_profile', true ) ?: [] )['job'] ?? '' );
	$src = $b['kind'] === 'google' ? 'their Google Business listing' : ( $b['kind'] === 'site' ? 'their website' : 'nothing online (only the name and notes the freelancer gave)' );
	if ( $notes !== '' ) { $src .= ', plus the notes the freelancer wrote'; }
	if ( $imgs ) { $src .= ', plus the screenshots attached'; }
	$sys = "You are a cold calling coach. Write the exact phone script a freelancer will read when they call this business, plus what to say to every objection. The goal of the call is NOT to sell on the phone: it is to get a short meeting (15 minutes, Zoom, in person or WhatsApp) or permission to send a free audit.\n\n"
		. "RULES\n"
		. "- Use only facts we give you about the business. Pick 1 or 2 specific things you can see (from the facts, the notes or the screenshots) that the freelancer's service fixes, and use one as the reason for the call. Never invent reviews, numbers, names or problems.\n"
		. "- Say only where you really looked: we read " . "{SOURCE}" . ". Never say you looked at their Instagram, reviews or anything else unless it is in the facts, the notes or the screenshots. If what we read shows no clear problem, make the reason a short honest question about the area the freelancer helps with.\n"
		. ( $job !== '' && function_exists( 'ir_ct_checklist' ) ? "- Things this freelancer fixes, so look for them first: " . ir_ct_checklist( $job ) . ".\n" : '' )
		. "- Spoken English for the phone: short lines, natural, warm and confident. Use [pause] where the caller should stop and let them talk. Every line must be something you can say out loud in one breath.\n"
		. "- {ADDRESS}\n"
		. "- The opener is permission-based and honest about it being a cold call (for example: I know I am calling out of the blue, can I have 30 seconds and you can tell me if it is worth talking further?).\n"
		. "- Objections follow the spirit of THE PLAYBOOK: agree or validate first, ask a question, reframe around the result, offer a small easy next step, never argue, never beg. Give the exact words to say.\n"
		. "- The opener, permission, reason, questions, pitch and close are read top to bottom as ONE conversation. Each part picks up where the last one stopped and says something new. Never repeat a sentence, a question or the same point in two parts: the opener does not ask for time (that is the permission line), the pitch does not restate the reason, the close does not repeat the pitch. Objection answers and the voicemail may reuse ideas but in fresh words.\n"
		. "- No placeholders at all: never write {their name}, [name], [link], [time], [price] or similar: use the freelancer's own link or booking details if given, otherwise leave them out.\n"
		. "- No em dashes or en dashes.\n\n"
		. 'Reply with JSON only: {"business":"the business name","what_they_do":"one line","phone":"the phone number exactly as written in the facts, notes or screenshots, else empty. Never guess one","best_time":"when to call this kind of business and when not to","prep":["3 short things to have ready before dialling"],"findings":[{"what":"a specific thing we can see","why":"why it costs them customers"}],"gatekeeper":"exactly what to say if a receptionist or staff member answers, to reach the owner or manager","opener":"greeting and who you are only, under 15 seconds, no request yet","permission":"only the line asking for 30 seconds","reason":"why you are calling, built on a finding","questions":["3 or 4 short discovery questions to get them talking"],"pitch":"20 to 30 seconds on what the freelancer does, in terms of the result for them","close":"asking for the meeting with two time options","voicemail":"a voicemail under 25 seconds","objections":[{"say":"what they say","you":"exactly what to say back"}],"after":"the WhatsApp or text to send right after the call (whatever happened)","tips":["3 short delivery tips for this call"]}. The objections list must cover at least: not interested; send me an email or information; I am busy, call later; how much is it; we already have someone or we do it ourselves; how did you get my number; we tried that before and it did not work; we do not have the budget right now.';
	$owner = trim( (string) ( $b['owner'] ?? '' ) );
	$addr = $owner !== '' ? 'The owner or manager is named on their site: ' . $owner . '. Ask for them by first name (' . explode( ' ', $owner )[0] . ') and greet them by first name once they are on the line. If the name in the facts looks like a different person, still use it only for asking for them.' : "We could not find the owner's name. Never invent one. Address the person as ma'am or sir, written exactly as ma'am/sir (for example: Good morning ma'am/sir). The gatekeeper line asks for the owner or manager.";
	$sys = str_replace( [ '{SOURCE}', '{ADDRESS}' ], [ $src, $addr ], $sys );
	$user = "THE FREELANCER\n" . $me . "\n\nTHE BUSINESS" . ( $url !== '' ? ' (' . $url . ')' : '' ) . "\n"
		. ( $name !== '' ? 'Name given by the freelancer: ' . $name . "\n" : '' )
		. ( $b['facts'] !== '' ? mb_substr( $b['facts'], 0, 6000 ) . "\n" : "We could not read a page for them, so work from the name, the notes and any screenshots.\n" )
		. ( $notes !== '' ? "\nThe freelancer's own notes about them: " . mb_substr( $notes, 0, 1500 ) . "\n" : '' )
		. ( $imgs ? "\nThe screenshots attached show their Google profile, website or social page.\n" : '' )
		. "\nWHAT WE READ: " . $src . ". WE DID NOT SEE anything else" . ( preg_match( '/instagram|tiktok|facebook|review/i', $notes ) || $imgs ? ' except what the notes or screenshots say' : ': not their Instagram, social media or reviews. Do not claim anything about those; if they matter to the freelancer, ask about them on the call instead' ) . ".\n"
		. "\nTHE PLAYBOOK (the system's own call and objection scripts)\n" . $pb;
	ir_ai_step( 'Writing your call script...' );
	$o = [ 'json' => 1, 'temp' => 0.5, 'max' => 6000, 'think' => 1024, 'timeout' => 95 ];
	$raw = $imgs ? ir_ai_gemini( $sys, array_merge( $imgs, [ [ 'text' => $user ] ] ), $o ) : ir_ai_text( $sys, $user, $o + [ 'strict' => 1 ] );
	if ( is_wp_error( $raw ) && ir_ai_left() > 30 ) { $raw = $imgs ? ir_ai_gemini( $sys, array_merge( $imgs, [ [ 'text' => $user ] ] ), $o ) : ir_ai_text( $sys, $user, $o ); }
	$j = is_wp_error( $raw ) ? null : ir_ai_json( $raw );
	/* It said it looked at a page we never read: one more try with that pointed out. */
	$seen = strtolower( $b['facts'] . ' ' . $notes ) . ( $imgs ? ' instagram reviews' : '' );
	$claims = is_array( $j ) ? strtolower( wp_json_encode( [ $j['reason'] ?? '', $j['findings'] ?? [], $j['opener'] ?? '' ] ) ) : '';
	if ( is_array( $j ) && preg_match( '/instagram|your feed|reels|followers|reviews/', $claims ) && ! preg_match( '/instagram|reels|followers|review/', $seen ) && ir_ai_left() > 25 ) {
		$raw2 = ir_ai_text( $sys, $user . "\n\nIMPORTANT: your last draft claimed things about their Instagram, feed, reels, followers or reviews. We did NOT see those. Rewrite the findings, reason and opener using only " . $src . ", and turn anything about social media into a question to ask on the call.", $o );
		$j2 = is_wp_error( $raw2 ) ? null : ir_ai_json( $raw2 );
		if ( is_array( $j2 ) && ! empty( $j2['opener'] ) && ! empty( $j2['objections'] ) ) { $j = $j2; }
	}
	if ( ! is_array( $j ) || empty( $j['opener'] ) || empty( $j['objections'] ) ) { return new WP_Error( 'ai', 'The AI is busy right now. Tap the button again in a few seconds.' ); }
	$j = map_deep( $j, function ( $v ) { return is_string( $v ) ? ir_or_clean( $v ) : $v; } );
	$ob = [];
	foreach ( (array) $j['objections'] as $x ) { if ( is_array( $x ) && ! empty( $x['say'] ) && ! empty( $x['you'] ) ) { $ob[] = [ 'say' => (string) $x['say'], 'you' => (string) $x['you'] ]; } }
	$fi = [];
	foreach ( (array) ( $j['findings'] ?? [] ) as $x ) { if ( is_array( $x ) && ! empty( $x['what'] ) ) { $fi[] = [ 'what' => (string) $x['what'], 'why' => (string) ( $x['why'] ?? '' ) ]; } }
	$s = function ( $k ) use ( $j ) { return is_string( $j[ $k ] ?? null ) ? $j[ $k ] : ''; };
	$l = function ( $k ) use ( $j ) { return array_values( array_filter( (array) ( $j[ $k ] ?? [] ), 'is_string' ) ); };
	$ai_ph = preg_replace( '/[^\d+]/', '', $s( 'phone' ) );
	$seen_digits = preg_replace( '/\D/', '', $b['facts'] . ' ' . $notes );
	$ai_ok = $ai_ph !== '' && strlen( preg_replace( '/\D/', '', $ai_ph ) ) >= 7 && ( $imgs || strpos( $seen_digits, substr( preg_replace( '/\D/', '', $ai_ph ), -7 ) ) !== false );
	$phone = $b['phone'] !== '' ? $b['phone'] : ( $ai_ok ? $s( 'phone' ) : '' );
	if ( $phone === '' && preg_match( '/(\+?\d[\d\s().-]{6,18}\d)/', $notes, $nm ) ) { $phone = trim( $nm[1] ); }
	$pfrom = $b['phone'] !== '' ? ( $b['from'] ?? '' ) : ( $phone === '' ? '' : ( $imgs && ! $ai_ok ? 'your screenshot' : ( strpos( preg_replace( '/\D/', '', $notes ), substr( preg_replace( '/\D/', '', $phone ), -7 ) ) !== false ? 'your notes' : 'your screenshot' ) ) );
	$j = ir_or_dedupe( $j );
	ir_or_track_call( ( is_string( $j['business'] ?? null ) && $j['business'] !== '' ) ? $j['business'] : ( $name ?: $b['name'] ), $phone, (string) ( $b['email'] ?? '' ), $owner, $url );
	$j = ir_or_address( $j, $owner );
	return [
		'business' => $s( 'business' ) ?: ( $name ?: $b['name'] ), 'what_they_do' => $s( 'what_they_do' ), 'phone' => $phone, 'tel' => preg_replace( '/[^\d+]/', '', $phone ), 'phone_from' => $pfrom, 'owner' => $owner, 'email' => (string) ( $b['email'] ?? '' ),
		'best_time' => $s( 'best_time' ), 'prep' => $l( 'prep' ), 'findings' => array_slice( $fi, 0, 3 ), 'gatekeeper' => $s( 'gatekeeper' ), 'opener' => $s( 'opener' ), 'permission' => $s( 'permission' ),
		'reason' => $s( 'reason' ), 'questions' => $l( 'questions' ), 'pitch' => $s( 'pitch' ), 'close' => $s( 'close' ), 'voicemail' => $s( 'voicemail' ), 'objections' => $ob, 'after' => $s( 'after' ), 'tips' => $l( 'tips' ),
		'read' => $b['kind'] ? ( $b['kind'] === 'google' ? 'Read from their Google Business listing.' : 'Read from their website.' ) : 'Built from the name and notes you gave.',
	];
}

add_filter( 'ir_ai_tools', function ( $t ) {
	$acc = function () { return is_user_logged_in() && function_exists( 'ir_pb_has_access' ) && ir_pb_has_access(); };
	$t['pbreply'] = [ 'access' => $acc, 'limit' => 300, 'run' => 'ir_or_reply' ];
	$t['pbcall'] = [ 'access' => $acc, 'limit' => 100, 'run' => 'ir_or_call' ];
	return $t;
} );

/** The tool page body for /members/replies/ and /members/cold-call/. */
function ir_or_page( $tool ) {
	$p = get_user_meta( get_current_user_id(), 'ir_rw_profile', true );
	$p = is_array( $p ) ? $p : [];
	$me = [];
	foreach ( [ 'name', 'job', 'client', 'result', 'service', 'price', 'proof', 'link', 'book' ] as $k ) { $me[ $k ] = (string) ( $p[ $k ] ?? '' ); }
	echo '<div id="orApp" data-tool="' . esc_attr( $tool ) . '"></div>';
	echo '<style>' . (string) @file_get_contents( WP_CONTENT_DIR . '/ir-playbook/outreach.css' ) . '</style>'; // phpcs:ignore
	echo '<script>window.ORC=' . wp_json_encode( [ 'me' => $me, 'tool' => $tool, 'lessons' => [ 'objections' => ir_pb_url( 'it-s-too-expensive' ), 'call' => ir_pb_url( 'discovery-call-script-from-call-to-closed-client' ) ] ] ) . ';</script>';
	echo '<script>' . (string) @file_get_contents( WP_CONTENT_DIR . '/ir-playbook/outreach.js' ) . '</script>'; // phpcs:ignore
}
