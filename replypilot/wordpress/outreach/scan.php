<?php
/**
 * Prospect scan v2 (Cold Outreach System). The site reads the prospect's page, checks the numbers in code, and with an AI key the AI also LOOKS at
 * their latest post pictures. Every problem carries its proof, and anything that cannot be proved is thrown out, so the diagnosis is never a guess.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** The JSON an Instagram embed page carries as a string. */
function ir_sc_ctx( $b ) {
	$b = (string) $b; $a = strpos( $b, '"contextJSON":"' );
	if ( $a === false ) { return null; }
	$a += 14; $i = $a + 1; $len = strlen( $b );
	while ( $i < $len ) { if ( $b[ $i ] === '\\' ) { $i += 2; continue; } if ( $b[ $i ] === '"' ) { break; } $i++; }
	$j = json_decode( (string) json_decode( substr( $b, $a, $i - $a + 1 ) ), true );
	return is_array( $j ) ? $j : null;
}

/** An Instagram profile: the numbers, the latest posts with their pictures, and plain facts for the AI. */
function ir_sc_ig( $h ) {
	$h = strtolower( trim( (string) $h, "@/ \t" ) );
	if ( ! preg_match( '/^[a-z0-9._]{2,30}$/', $h ) ) { return new WP_Error( 'ig', 'That does not look like an Instagram handle.' ); }
	if ( function_exists( 'ir_px_ig' ) && ir_px_token() !== '' ) { $px = ir_px_ig( $h ); if ( ! is_wp_error( $px ) ) { return $px; } }
	$ck = 'ir_sc_ig_' . md5( $h ); $c = get_transient( $ck );
	if ( is_array( $c ) ) { return $c; }
	$x = null;
	for ( $try = 0; $try < 4 && ! $x; $try++ ) {
		if ( $try ) { usleep( 700000 ); }
		$r = wp_remote_get( 'https://www.instagram.com/' . $h . '/embed/' . ( $try > 1 ? '?cr=1&v=' . $try : '' ), [ 'timeout' => 15, 'redirection' => 3, 'headers' => [ 'User-Agent' => $try % 2 ? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36' : ir_po_ua(), 'Accept-Language' => 'en-GB,en;q=0.9' ] ] );
		if ( is_wp_error( $r ) || (int) wp_remote_retrieve_response_code( $r ) !== 200 ) { continue; }
		$j = ir_sc_ctx( wp_remote_retrieve_body( $r ) );
		$x = is_array( $j ) ? ( $j['context'] ?? null ) : null;
		if ( ! is_array( $x ) || empty( $x['username'] ) ) { $x = null; }
	}
	if ( ! $x ) { return new WP_Error( 'ig_blocked', 'Instagram did not open @' . $h . ' for us this time (some accounts block outside tools, or the handle may have a typo). Paste the link to one of their reels or posts instead, it works for every account. Or tap Add screenshots and add 1 or 2 screenshots of their profile.', [ 'handle' => $h ] ); }
	$posts = [];
	foreach ( array_slice( (array) ( $x['graphql_media'] ?? [] ), 0, 12 ) as $g ) {
		$n = (array) ( $g['shortcode_media'] ?? [] );
		if ( ! $n ) { continue; }
		$ty = (string) ( $n['__typename'] ?? '' );
		$posts[] = [
			'ts' => (int) ( $n['taken_at_timestamp'] ?? 0 ), 'kind' => ( ! empty( $n['is_video'] ) || $ty === 'GraphVideo' ) ? 'video' : ( $ty === 'GraphSidecar' ? 'carousel' : 'photo' ),
			'likes' => (int) ( $n['edge_liked_by']['count'] ?? 0 ), 'comments' => (int) ( $n['edge_media_to_comment']['count'] ?? 0 ), 'hidden' => ! empty( $n['like_and_view_counts_disabled'] ),
			'cap' => trim( preg_replace( '/\s+/', ' ', (string) ( $n['edge_media_to_caption']['edges'][0]['node']['text'] ?? '' ) ) ), 'pinned' => ! empty( $n['pinned_for_users'] ), 'img' => (string) ( $n['display_url'] ?? '' ),
		];
	}
	$fol = (int) ( $x['followers_count'] ?? 0 ); $now = time(); $seen = count( $posts );
	$ts = array_filter( wp_list_pluck( array_filter( $posts, function ( $p ) { return ! $p['pinned']; } ), 'ts' ) );
	if ( ! $ts ) { $ts = array_filter( wp_list_pluck( $posts, 'ts' ) ); }
	$last = $ts ? max( $ts ) : 0;
	$words = function ( $c ) { return trim( preg_replace( '/#[\p{L}\p{N}_]+|[^\p{L}\p{N}\s]/u', ' ', $c ) ); };
	$counted = array_filter( $posts, function ( $p ) { return ! $p['hidden']; } );
	$st = [
		'followers' => $fol, 'posts' => (int) ( $x['posts_count'] ?? 0 ), 'seen' => $seen,
		'days'   => $last ? (int) floor( ( $now - $last ) / DAY_IN_SECONDS ) : -1,
		'last'   => $last ? gmdate( 'j M Y', $last ) : '',
		'n30'    => count( array_filter( $posts, function ( $p ) use ( $now ) { return $p['ts'] > $now - 30 * DAY_IN_SECONDS; } ) ),
		'videos' => count( array_filter( $posts, function ( $p ) { return $p['kind'] === 'video'; } ) ),
		'cta'    => count( array_filter( $posts, function ( $p ) { return (bool) preg_match( '/\bbook|booking|link in (my |our |the )?bio|\bdm\b|dm (me|us)|send (me|us) a|message (me|us)|whatsapp|call (me|us)|appointment|tap the link|order now|slots?\b|available|click the link|comment "?[a-z]+"? (to|and|below)/i', $p['cap'] ); } ) ),
		'nocap'  => count( array_filter( $posts, function ( $p ) use ( $words ) { return mb_strlen( $words( $p['cap'] ) ) < 20; } ) ),
		'avg'    => -1,
	];
	/* The typical post (the middle one), so one viral post does not hide a quiet page. */
	if ( count( $counted ) >= 3 ) { $e = array_values( array_map( function ( $p ) { return $p['likes'] + $p['comments']; }, $counted ) ); sort( $e ); $m = count( $e ); $st['avg'] = (int) round( $m % 2 ? $e[ ( $m - 1 ) / 2 ] : ( $e[ $m / 2 - 1 ] + $e[ $m / 2 ] ) / 2 ); }
	$lines = [];
	foreach ( $posts as $i => $p ) {
		$lines[] = 'Post ' . ( $i + 1 ) . ' (' . ( $p['ts'] ? gmdate( 'j M Y', $p['ts'] ) : 'date unknown' ) . ( $p['pinned'] ? ', pinned' : '' ) . ', ' . $p['kind'] . ( $p['hidden'] ? ', likes hidden' : ', ' . $p['likes'] . ' likes' ) . ', ' . $p['comments'] . ' comments): ' . ( $p['cap'] !== '' ? mb_substr( $p['cap'], 0, 260 ) : '(no caption)' );
	}
	$facts = 'Instagram profile @' . $x['username'] . "\nName on the profile: " . ( $x['full_name'] ?? '' ) . "\nFollowers: " . $fol . "\nTotal posts: " . $st['posts']
		. "\nNewest post: " . ( $last ? $st['last'] . ' (' . $st['days'] . ' days ago)' : 'none visible' )
		. "\nOf the " . $seen . ' latest posts: ' . $st['n30'] . ' were posted in the last 30 days, ' . $st['videos'] . ' are Reels or videos, ' . $st['cta'] . ' have a caption that tells people how to book, DM or click, ' . $st['nocap'] . ' have little or no caption.'
		. ( $fol && $st['avg'] >= 0 ? "\nA typical post gets " . $st['avg'] . ' likes plus comments' . ' (' . round( $st['avg'] / max( 1, $fol ) * 100, 2 ) . '% of followers)' : '' )
		. "\nThe " . $seen . " latest posts:\n" . ( $lines ? implode( "\n", $lines ) : '(none visible; the account may be new or private)' );
	$out = [ 'facts' => $facts, 'contact' => [ 'instagram' => (string) $x['username'] ], 'name' => (string) ( $x['full_name'] ?? '' ), 'stats' => $st, 'caps' => implode( "\n", wp_list_pluck( $posts, 'cap' ) ), 'imgs' => array_values( array_filter( wp_list_pluck( array_slice( $posts, 0, 6 ), 'img' ) ) ) ];
	set_transient( $ck, $out, 3 * HOUR_IN_SECONDS );
	return $out;
}

/** Problems proved by the numbers alone: [ k, re (words that mean an AI issue covers the same thing), title, detail, obs, impact, fix ]. */
function ir_sc_auto_ig( $st ) {
	$a = [];
	if ( ! is_array( $st ) || $st['seen'] < 1 ) { return $a; }
	$s = $st['seen'];
	if ( $st['days'] >= 14 ) { $a[] = [ 'k' => 'quiet', 're' => 'days ago|last post|inactive|quiet|stopped posting', 'title' => 'The page has gone quiet', 'detail' => 'Their newest post is from ' . $st['last'] . ', ' . $st['days'] . ' days ago.', 'obs' => 'last post was ' . $st['days'] . ' days ago', 'impact' => 'That means people who find you today see a page that looks inactive, and many will book someone else.', 'fix' => 'Plan and post fresh content every week so the page always looks open for business.' ]; }
	elseif ( $s >= 5 && $st['n30'] <= 2 ) { $a[] = [ 'k' => 'thin', 're' => 'last 30 days|last month|irregular|consisten|posting (is|schedule)|frequency', 'title' => 'Posting is thin', 'detail' => 'Just ' . $st['n30'] . ' of their latest ' . $s . ' posts went up in the last 30 days.', 'obs' => 'page has had just ' . $st['n30'] . ' post' . ( $st['n30'] === 1 ? '' : 's' ) . ' in the last 30 days', 'impact' => 'So Instagram shows you to fewer people, and followers forget you between posts.', 'fix' => 'Set up a simple weekly content plan and keep it running for them.' ]; }
	if ( $s >= 5 && $st['videos'] === 0 ) { $a[] = [ 'k' => 'reels', 're' => 'reels?\b|video', 'title' => 'No Reels in the latest posts', 'detail' => 'None of their latest ' . $s . ' posts is a Reel or video.', 'obs' => 'last ' . $s . ' posts have no Reels or videos', 'impact' => 'So you miss the reach Instagram gives to video, and very few new people discover you.', 'fix' => 'Script, film and edit short Reels that show the work and the results.' ]; }
	if ( $s >= 4 && $st['cta'] === 0 ) { $a[] = [ 'k' => 'cta', 're' => 'call to action|\bcta\b|how to book|tell people|next step', 'title' => 'Captions never ask people to book', 'detail' => 'None of their latest ' . $s . ' captions tells people how to book, DM or click.', 'obs' => 'latest ' . $s . ' captions never tell people how to book or message you', 'impact' => 'That means people who like a post have no next step, so interest does not turn into bookings.', 'fix' => 'Write captions that end with one clear way to book.' ]; }
	if ( $s >= 4 && $st['nocap'] >= (int) ceil( $s / 2 ) ) { $a[] = [ 'k' => 'caption', 're' => 'caption', 'title' => 'Captions say almost nothing', 'detail' => $st['nocap'] . ' of their latest ' . $s . ' posts have little or no caption.', 'obs' => $st['nocap'] . ' of your last ' . $s . ' posts have little or no caption', 'impact' => 'So visitors learn nothing about the service, and Instagram has no words to show you in search.', 'fix' => 'Write short captions that name the service, the result and who it is for.' ]; }
	if ( $st['followers'] >= 1500 && $st['avg'] >= 0 && $st['avg'] / $st['followers'] < 0.004 ) { $a[] = [ 'k' => 'engage', 're' => 'likes|engag|reach', 'title' => 'Followers are not reacting', 'detail' => 'A typical post gets ' . $st['avg'] . ' likes and comments on a page with ' . number_format( $st['followers'] ) . ' followers.', 'obs' => 'posts usually get about ' . $st['avg'] . ' likes and comments from ' . number_format( $st['followers'] ) . ' followers', 'impact' => 'That means most of your followers never see or react to your posts, so the page is not bringing in enquiries.', 'fix' => 'Create content people stop for: strong hooks, faces, results and questions.' ]; }
	return $a;
}

function ir_sc_auto_site( $facts ) {
	$f = [];
	foreach ( explode( "\n", (string) $facts ) as $l ) { if ( preg_match( '/^([A-Za-z][^:]{2,40}):\s*(.*)$/', $l, $m ) ) { $f[ $m[1] ] = strtolower( trim( $m[2] ) ); } if ( strpos( $l, 'Visible text' ) === 0 ) { break; } }
	$a = []; $is = function ( $k, $v ) use ( $f ) { return isset( $f[ $k ] ) && strpos( $f[ $k ], $v ) === 0; };
	if ( $is( 'Online booking link', 'no' ) ) { $a[] = [ 'k' => 'book', 're' => 'book', 'title' => 'No clear way to book', 'detail' => 'We found no booking link or button on the page.', 'obs' => 'website has no booking link or button I could find', 'impact' => 'That means visitors who are ready to book have to go looking, and most leave instead.', 'fix' => 'Add a clear book now button at the top that goes straight to booking.' ]; }
	$rd = isset( $f['Read through a page reader'] ) && strpos( $f['Read through a page reader'], 'yes' ) === 0;
	if ( ! $rd && $is( 'Mobile friendly tag', 'no' ) ) { $a[] = [ 'k' => 'mobile', 're' => 'mobile|phone', 'title' => 'Not built for phones', 'detail' => 'The page is missing the tag that makes a site fit a phone screen.', 'obs' => 'website is not set up to fit a phone screen', 'impact' => 'So most visitors, who are on their phones, have to pinch and zoom, and they leave.', 'fix' => 'Rebuild the page so it reads well on a phone.' ]; }
	if ( $is( 'Reviews or testimonials', 'none' ) ) { $a[] = [ 'k' => 'reviews', 're' => 'review|testimonial|proof', 'title' => 'No client reviews shown', 'detail' => 'We found no reviews or testimonials on the page.', 'obs' => 'website shows no client reviews or results', 'impact' => 'That means new visitors have no proof you are good, so they hesitate to book.', 'fix' => 'Add a reviews section with real client words and results near the top.' ]; }
	if ( $is( 'WhatsApp button', 'no' ) && $is( 'Contact form', 'no' ) && $is( 'Phone link', 'no' ) ) { $a[] = [ 'k' => 'contact', 're' => 'contact|whatsapp|form', 'title' => 'No quick way to reach them', 'detail' => 'No WhatsApp button, contact form or tap to call link on the page.', 'obs' => 'website has no WhatsApp button, contact form or tap to call link', 'impact' => 'So anyone with a quick question cannot ask it, and you lose that enquiry.', 'fix' => 'Add a WhatsApp button and a short contact form.' ]; }
	if ( $is( 'Prices shown', 'no' ) ) { $a[] = [ 'k' => 'price', 're' => 'price|pricing|cost', 'title' => 'No prices on the page', 'detail' => 'We found no prices on the page.', 'obs' => 'website shows no prices for your services', 'impact' => 'That means people who want a rough idea of cost leave to check a competitor.', 'fix' => 'Add a simple services and prices section.' ]; }
	if ( ! $rd && $is( 'Meta description', '(none)' ) ) { $a[] = [ 'k' => 'seo', 're' => 'google|description|seo|search', 'title' => 'No description for Google', 'detail' => 'The page has no meta description.', 'obs' => 'website has no description for Google to show in search results', 'impact' => 'So Google picks random text for your listing, and fewer people click through.', 'fix' => 'Write clear page titles and descriptions for Google.' ]; }
	if ( isset( $f['Copyright year'] ) && preg_match( '/^(\d{4})$/', $f['Copyright year'], $y ) && (int) $y[1] < (int) gmdate( 'Y' ) - 1 ) { $a[] = [ 'k' => 'year', 're' => 'copyright|out of date|outdated', 'title' => 'The site looks out of date', 'detail' => 'The footer still says ' . $y[1] . '.', 'obs' => 'website footer still says ' . $y[1], 'impact' => 'That means visitors wonder if the business is still running.', 'fix' => 'Refresh the site and keep it up to date.' ]; }
	return $a;
}

function ir_sc_run( $in ) {
	$url = trim( (string) ( $in['url'] ?? '' ) );
	if ( $url !== '' && ! preg_match( '#^https?://#i', $url ) ) {
		$handle = $url[0] === '@' || ( preg_match( '/^[a-z0-9._]{2,30}$/i', $url ) && ! preg_match( '/\.[a-z]{2,}$/i', $url ) );
		$url = $handle ? 'https://www.instagram.com/' . ltrim( $url, '@' ) . '/' : 'https://' . $url;
	}
	$url = esc_url_raw( $url );
	if ( $url === '' ) { return new WP_Error( 'in', 'Paste their link first.' ); }
	$kind = ir_ct_kind( $url ); $eyes = function_exists( 'ir_ai_can_see' ) && ir_ai_can_see();
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	/* Pages we cannot read from outside: say so plainly instead of guessing from a login wall. */
	if ( $kind === 'social' ) { $nm = [ 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'fb' => 'Facebook', 'linkedin' => 'LinkedIn', 'threads' => 'Threads', 'youtube' => 'YouTube' ]; $pn = 'This'; foreach ( $nm as $k => $v ) { if ( strpos( $host, $k ) !== false ) { $pn = $v; break; } } if ( $pn === 'This' && preg_match( '/(^|\.)(x|twitter)\.com$/', $host ) ) { $pn = 'X'; } return new WP_Error( 'need_shot', $pn . ' pages only open for people who are logged in, so we cannot read them. Paste their Instagram or website link instead, or tap Add screenshots and add 1 or 2 screenshots of their page.' ); }
	if ( preg_match( '/(^|\.)(wa\.me|whatsapp\.com|api\.whatsapp\.com)$/', $host ) ) { return new WP_Error( 'need_shot', 'That is a WhatsApp chat link, so there is no page to read. Paste their Instagram or website link instead.' ); }
	/* A link-in-bio page: read the real website it points to. */
	$via = '';
	if ( $kind === 'website' && preg_match( '/(^|\.)(linktr\.ee|beacons\.ai|bio\.link|lnk\.bio|linkin\.bio|taplink\.cc|campsite\.bio|komi\.io|stan\.store|solo\.to|msha\.ke|tap\.bio|linkbio\.co|snipfeed\.co|hoo\.be|direct\.me)$/', $host ) && function_exists( 'ir_cf_find' ) ) {
		ir_ai_step( 'Opening the website on their link page...' );
		$lp = ir_cf_find( $url );
		if ( $lp['website'] !== '' ) { $via = $url; $url = $lp['website']; $kind = ir_ct_kind( $url ); }
	}
	ir_ai_step( 'Reading their page...' );
	$facts = ''; $auto = []; $parts = []; $contact = []; $name = ''; $ig = false; $have = 'Only the facts below.'; $cant = ''; $based = '';
	if ( $kind === 'instagram' ) {
		$seg = strtolower( explode( '/', trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' ) )[0] ?? '' );
		$cant = 'their bio, link in bio, highlights, stories, profile picture and older posts';
		if ( in_array( $seg, [ 'p', 'reel', 'reels', 'tv' ], true ) ) {
			$p = ir_po_read( $url );
			if ( is_wp_error( $p ) ) { return new WP_Error( 'need_shot', 'Instagram did not let us read this post. Paste their profile link, or add a screenshot of their profile.' ); }
			$u = ir_po_understand( $p );
			$facts = "ONE of their Instagram posts (we have not seen the rest of the page):\n" . ir_po_brief( $p, $u );
			$contact = [ 'instagram' => (string) $p['author'] ]; $ig = true;
			$have = 'One of their posts' . ( $u ? ', which we ' . ( $u['saw'] === 'video' ? 'watched' : 'looked at' ) : ' (caption only)' ) . '. Judge only this post.';
			$based = 'Based on this one post only. Paste their profile link for a fuller check.';
		} else {
			$d = ir_sc_ig( $seg );
			if ( is_wp_error( $d ) ) { return new WP_Error( 'need_shot', $d->get_error_message() ); }
			$facts = $d['facts']; $contact = $d['contact']; $name = $d['name']; $ig = true; $auto = ir_sc_auto_ig( $d['stats'] );
			$rate = ( $d['stats']['followers'] ?? 0 ) > 0 && ( $d['stats']['avg'] ?? -1 ) >= 0 ? $d['stats']['avg'] / $d['stats']['followers'] * 100 : -1;
			if ( $eyes && $d['imgs'] ) {
				ir_ai_step( 'Looking at their latest posts...' );
				foreach ( $d['imgs'] as $im ) {
					$r = wp_remote_get( $im, [ 'timeout' => 12, 'limit_response_size' => 4 * MB_IN_BYTES, 'headers' => [ 'User-Agent' => ir_po_ua(), 'Referer' => 'https://www.instagram.com/' ] ] );
					$type = is_wp_error( $r ) ? '' : strtolower( (string) strtok( (string) wp_remote_retrieve_header( $r, 'content-type' ), ';' ) );
					if ( strpos( $type, 'image/' ) === 0 && (int) wp_remote_retrieve_response_code( $r ) === 200 ) { $parts[] = [ 'inline_data' => [ 'mime_type' => $type, 'data' => base64_encode( wp_remote_retrieve_body( $r ) ) ] ]; }
				}
			}
			$n = (int) $d['stats']['seen'];
			$have = $parts ? 'The facts below (the numbers, and their ' . $n . ' latest posts with date, type, likes, comments and caption) and the picture of each of the first ' . count( $parts ) . ' of those posts, in the same order: picture 1 is post 1.' : 'The facts below: the numbers, and their ' . $n . ' latest posts with date, type, likes, comments and caption.';
			$based = 'Based on their ' . $n . ' latest posts: ' . ( $parts ? 'the pictures, ' : '' ) . 'captions, dates, likes and comments' . ( ! empty( $d['bio'] ) ? ', plus their bio, profile picture and links. Highlights and stories are left out.' : '. Their bio, highlights and stories are not visible to us, so they are left out.' );
		}
	} elseif ( $kind === 'google' ) {
		$d = ir_ct_google( $url );
		if ( is_wp_error( $d ) ) { return new WP_Error( 'need_shot', $d->get_error_message() ); }
		$facts = $d['facts']; $name = (string) $d['name']; $cant = 'their reviews in detail, photos and website'; $based = 'Based on the short details Google shares for this listing.';
	} else {
		$d = ir_ct_site( $url );
		if ( is_wp_error( $d ) ) { return new WP_Error( 'need_shot', $d->get_error_message() ); }
		$facts = $d['facts']; $contact = array_filter( (array) $d['contact'] ); $name = (string) $d['name'];
		$lb = (bool) preg_match( '/(^|\.)(linktr\.ee|beacons\.ai|bio\.link|lnk\.bio|linkin\.bio|taplink\.cc|campsite\.bio|komi\.io|stan\.store|solo\.to|msha\.ke|tap\.bio|linkbio\.co|snipfeed\.co|hoo\.be|direct\.me|carrd\.co)$/', strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
		/* A link page is not a website: only the booking and contact checks are fair there. */
		$auto = array_values( array_filter( ir_sc_auto_site( $facts ), function ( $a ) use ( $lb ) { return ! $lb || in_array( $a['k'], [ 'book', 'contact' ], true ); } ) );
		if ( $via !== '' ) { $facts = 'Their link-in-bio page (' . $via . ') points to this website, so we read the website.' . "\n" . $facts; }
		$cant = 'how the page looks (design, colours, pictures), any page other than this one, and their social media';
		$based = 'Based on what this page contains: its text, headings, buttons, links and set up. We read the page, we do not see its design.';
	}
	/* Contact details: the page, its contact and about pages, link-in-bio pages and the site a Google listing points to. */
	$owner = '';
	if ( function_exists( 'ir_cf_find' ) ) {
		ir_ai_step( 'Finding their email and phone...' );
		if ( $kind === 'website' || $kind === 'google' ) {
			$cf = ir_cf_find( $url );
			if ( $cf['email'] !== '' && empty( $contact['email'] ) ) { $contact['email'] = $cf['email']; }
			if ( $cf['whatsapp'] !== '' && empty( $contact['whatsapp'] ) ) { $contact['whatsapp'] = $cf['whatsapp']; }
			if ( $cf['phone'] !== '' ) { $contact['phone'] = $cf['phone']; }
			if ( $cf['website'] !== '' ) { $contact['website'] = $cf['website']; }
			$owner = $cf['owner'];
			if ( $cf['about'] !== '' ) { $facts .= "\n\nFrom their About or Team page: " . $cf['about']; }
		} else {
			/* Instagram: every caption in full (phone numbers often sit at the end), WhatsApp links, and any website they mention. */
			$all = $facts . "\n" . (string) ( $d['caps'] ?? '' );
			$tx = ir_cf_from_text( $all );
			if ( $tx['email'] !== '' ) { $contact['email'] = $tx['email']; }
			if ( $tx['phone'] !== '' ) { $contact['phone'] = $tx['phone']; }
			if ( preg_match( '#(?:wa\.me/|api\.whatsapp\.com/send/?\?phone=)\+?(\d{8,15})#i', $all, $w ) ) { $contact['whatsapp'] = $w[1]; }
			elseif ( ! empty( $contact['phone'] ) && preg_match( '/whatsapp/i', $all ) && preg_match( '/^\+234[789]/', $contact['phone'] ) ) { $contact['whatsapp'] = ltrim( $contact['phone'], '+' ); }
			if ( preg_match( '#\b((?:https?://)?(?:www\.)?[a-z0-9-]{2,}\.(?:com\.ng|ng|com|co\.uk|co|net|org|store|shop|beauty|salon|spa|clinic|studio|online|site|me|io)(?:/[^\s]*)?)#i', preg_replace( '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', ' ', (string) ( $d['caps'] ?? '' ) ), $wm ) && ! preg_match( '/instagram|facebook|tiktok|whatsapp|youtube|twitter|google|apple|gmail|yahoo|hotmail|outlook|wa\.me/i', $wm[1] ) ) {
				ir_ai_step( 'Checking the website in their captions...' );
				$cf = ir_cf_find( preg_replace( '#[.,;:!?)]+$#', '', $wm[1] ) );
				if ( $cf['email'] !== '' && empty( $contact['email'] ) ) { $contact['email'] = $cf['email']; }
				if ( $cf['phone'] !== '' && empty( $contact['phone'] ) ) { $contact['phone'] = $cf['phone']; }
				if ( $cf['whatsapp'] !== '' && empty( $contact['whatsapp'] ) ) { $contact['whatsapp'] = $cf['whatsapp']; }
				if ( $cf['pages'] ) { $contact['website'] = preg_match( '#^https?://#i', $wm[1] ) ? $wm[1] : 'https://' . $wm[1]; }
				if ( $owner === '' ) { $owner = $cf['owner']; }
			}
		}
		$found = array_filter( [ ! empty( $contact['email'] ) ? 'email ' . $contact['email'] : '', ! empty( $contact['phone'] ) ? 'phone ' . $contact['phone'] : '', $owner !== '' ? 'owner or manager ' . $owner : '' ] );
		if ( $found ) { $facts .= "\n\nContact details found on their pages: " . implode( ', ', $found ) . '.'; }
	}
	$ch = ( $in['ch'] ?? '' ) === 'email' ? 'email' : 'dm';
	$ap = trim( sanitize_text_field( (string) ( $in['ap'] ?? '' ) ) );
	$checked = '';
	foreach ( $auto as $x ) { $checked .= '- ' . $x['detail'] . "\n"; }
	$mk = function ( $see ) use ( $ch, $have, $cant, $checked, $ap, $in ) {
		return "You are a sharp, kind strategist helping a freelancer write a cold " . ( $ch === 'email' ? 'email' : 'DM' ) . " that gets a reply. You study what we read from a prospect's page and find the specific problems that cost them clients, especially the ones the freelancer's service fixes.\n\n"
			. "WHAT YOU HAVE: " . $have . "\n"
			. ( $see ? '' : "You CANNOT see any pictures. Never say anything about how posts or pages look: nothing about design, colours, fonts, photo quality, layout, graphics, templates or branding.\n" )
			. ( $cant !== '' ? "WHAT YOU DO NOT HAVE: " . $cant . ". Never mention these, not even to say they are missing.\n" : '' )
			. "\nRULES\n"
			. "- Proof first. Every issue needs \"evidence\": the exact number, date, post number or quoted words that prove it. If you cannot prove it from what you have, leave it out. Two real issues beat five guesses.\n"
			. "- Numbers: use only numbers that appear in the facts. Never estimate, round up or invent.\n"
			. ( $see ? "- When you describe how posts look (what they show, text on graphics, faces or no faces, variety, quality), say only what is visible in the pictures and name the post numbers.\n" : '' )
			. ( $checked !== '' ? "- CHECKED FINDINGS, already verified by us (use and sharpen them, never contradict them):\n" . $checked : '' )
			. "- WHAT TO LOOK FOR: the freelancer fixes things like these, so check for them first: " . ir_ct_checklist( (string) ( $in['me_job'] ?? '' ) ) . ". Report only the ones you can prove.\n"
			. "- Give 2 to 4 issues, the most costly first. Each has: title (3 to 6 words); detail (what you found, 1 or 2 sentences, with the proof); evidence; obs, which finishes the sentence \"I noticed your ...\" (the exact thing, 6 to 18 words, lowercase, no full stop, do not start with the word your); impact (ONE sentence said to them, starting with So or That means, on how it costs them bookings, trust or reach, 10 to 25 words, no made up numbers); fix (what the freelancer would do about it, 1 sentence).\n"
			. "- like finishes \"I really like your ...\": something the owner would be proud to hear, about the quality of their actual work: their results, a specific service or product, their before and afters, how clearly they explain things, the look of their photos or videos, a happy client. Describe it in your own words, 3 to 10 words, lowercase. It must read naturally after \"I really like your\". Never quote a caption. Never compliment a sale, discount, promo, deadline, giveaway or announcement. Never about followers, likes, views, posting or numbers. Never anything negative or vague (not \"content\", not \"page\"). Good: \"brow lamination results\", \"calm, clean look of your treatment room photos\", \"how simply you explain chemical peels\". Bad: \"post that said my biggest sale\", \"caption about 48 hours\". likes: 2 other real things they do well, same rules, each different.\n"
			. "- obs, impact, like and likes are said to the prospect, who does not know our post numbers. Never write 'post 1' or any post number in them: describe the post instead ('your microneedling reel', 'your last flyer'). Post numbers belong only in detail and evidence.\n"
			. "- tip: one free, practical tip for the first issue that they could do today, 1 or 2 sentences.\n"
			. "- summary: 2 sentences on who they are and how they come across, only from what you have. need: 1 to 10, how much they need the freelancer.\n"
			. ( $ap !== '' ? "- The freelancer picked the approach \"" . $ap . "\". Word the compliment, observations and impact to fit it.\n" : '' )
			. "- Also choose the best approach: d-problem (a gap that costs clients), d-result (a result for someone similar), d-value (give value first), d-audit (compliment then a free audit), d-direct (short and direct), d-soft (soft question), d-niche (only work with people like them); for emails: e-problem, e-result, e-curious, e-audit, e-compliment, e-direct.\n"
			. "- Plain, warm English. No em dashes or en dashes.\n\n"
			. 'Reply with JSON only: {"name":"owner first name if clearly given, else empty","business":"business or page name","summary":"","need":7,"like":"","likes":["",""],"issues":[{"title":"","detail":"","evidence":"","obs":"","impact":"","fix":""}],"tip":"","best":{"dm":"d-problem","email":"e-problem","why":"1 sentence"}}';
	};
	$user = "THE FREELANCER\n" . ir_ct_me( $in ) . "\n\nTHE PROSPECT (" . $url . ")\n" . mb_substr( $facts, 0, 7000 );
	$ctx = [ 'rate' => isset( $rate ) ? $rate : -1, 'ng' => ir_sc_ng( $facts . ' ' . implode( ' ', array_map( 'strval', $contact ) ) . ' ' . $url ), 'kind' => $kind, 'ig' => $ig, 'auto' => $auto, 'facts' => mb_substr( $facts, 0, 7000 ), 'contact' => $contact, 'name' => $name, 'based' => $based, 'owner' => $owner, 'bio' => isset( $d ) && is_array( $d ) && ! empty( $d['bio'] ) ];
	ir_ai_step( 'Finding what to mention...' );
	if ( $parts ) {
		$raw = ir_ai_gemini( $mk( true ), array_merge( $parts, [ [ 'text' => $user ] ] ), [ 'json' => 1, 'temp' => 0.3, 'max' => 5000, 'think' => 1024, 'timeout' => 80 ] );
		$j = is_wp_error( $raw ) ? null : ir_ai_json( $raw );
		if ( is_array( $j ) && ! empty( $j['issues'] ) ) { return ir_sc_post( $j, [ 'eyes' => 1 ] + $ctx ); }
	}
	$r = ir_v2_ai( $mk( false ), $user, [ 'json' => 1, 'temp' => 0.3, 'max' => 5000, 'think' => 1024 ], 'ir_sc_post', [ 'eyes' => 0 ] + $ctx );
	if ( is_wp_error( $r ) ) { $fb = ir_sc_post( null, [ 'eyes' => 0 ] + $ctx ); return is_wp_error( $fb ) ? $r : $fb; }
	return $r;
}

/** Keeps only the findings that are proved, adds the ones the numbers prove, and returns what the Smart Writer shows. */
/** Compliments built from their real captions, most liked first: used when the AI gives none. */
function ir_sc_like_fb( $facts ) {
	if ( ! preg_match_all( '/^Post \d+ \(([^)]*)\): (.+)$/m', (string) $facts, $m, PREG_SET_ORDER ) ) { return []; }
	$ps = [];
	foreach ( $m as $x ) {
		$cap = trim( preg_replace( [ '/#[\p{L}\p{N}_]+/u', '/@[\w.]+/u', '/[^\p{L}\p{N}\s\'’,.!?%&-]/u', '/\s+/' ], [ '', '', '', ' ' ], $x[2] ) );
		if ( $cap === '' || stripos( $x[2], '(no caption)' ) === 0 ) { continue; }
		preg_match( '/(\d+) likes/', $x[1], $lk ); preg_match( '/(\d+) comments/', $x[1], $cm );
		$kind = strpos( $x[1], 'video' ) !== false ? 'reel' : ( strpos( $x[1], 'carousel' ) !== false ? 'carousel' : 'post' );
		$sent = preg_split( '/(?<=[.!?])\s+/', $cap )[0];
		$w = preg_split( '/\s+/', trim( $sent, " .!?,'’-" ) );
		if ( count( $w ) < 3 ) { continue; }
		$ps[] = [ (int) ( $lk[1] ?? 0 ) + 3 * (int) ( $cm[1] ?? 0 ), $kind, implode( ' ', array_slice( $w, 0, 10 ) ) . ( count( $w ) > 10 ? '...' : '' ) ];
	}
	usort( $ps, function ( $a, $b ) { return $b[0] - $a[0]; } );
	/* Never quote captions back (that is how compliments like "your post that said MY BIGGEST SALE" happened). Praise the kind of work they show. */
	$kinds = array_count_values( array_map( function ( $p ) { return $p[1]; }, $ps ) );
	$out = [];
	if ( ! empty( $kinds['reel'] ) ) { $out[] = 'reels showing your work up close'; }
	if ( ! empty( $kinds['carousel'] ) ) { $out[] = 'carousels that walk people through your work'; }
	$out[] = 'work you share on your page';
	return $out;
}

/** Is this a Nigerian business? true, false (clearly somewhere else) or null (no sign either way). */
function ir_sc_ng( $t ) {
	$t = (string) $t;
	if ( preg_match( '/\+234|\b234[789][01]\d{8}\b|\b0[789][01]\d[\s-]?\d{3}[\s-]?\d{4}\b|\.ng\b|\x{20A6}|\bngn\b|naija|nigeria|\blagos\b|\babuja\b|\blekki\b|\bikeja\b|\bikoyi\b|victoria island|port ?harcourt|\bibadan\b|\benugu\b|\bowerri\b|benin city|\bkano\b|\bkaduna\b|\buyo\b|\bcalabar\b|\basaba\b|\bwarri\b|\babeokuta\b|\bilorin\b/iu', $t ) ) { return true; }
	if ( preg_match( '/\+1[\s(]|\+44|\.co\.uk\b|\.ca\b|\.com\.au\b|\b(usa|united states|london|toronto|atlanta|houston|new york|los angeles|chicago|dallas|miami)\b/i', $t ) ) { return false; }
	return null;
}

function ir_sc_post( $j, $ctx ) {
	$cl = function ( $s ) { return is_string( $s ) ? trim( preg_replace( [ '/\s*[\x{2013}\x{2014}]\s*/u', '/\s+/' ], [ ', ', ' ' ], str_replace( "\u{2011}", '-', $s ) ) ) : ''; };
	$strip = function ( $s ) use ( $cl ) { return rtrim( preg_replace( [ '/^(i noticed\s+)?(that\s+)?(your\s+)/i', '/\s*\(?\b(shown |seen |posted )?(in|on|from) posts? \d+( (and|&|to) \d+)*\)?/i', '/\bposts? \d+\b/i' ], [ '', '', 'a recent post' ], $cl( $s ) ), '. ' ); };
	$facts = str_replace( ',', '', strtolower( (string) ( $ctx['facts'] ?? '' ) ) );
	$auto = (array) ( $ctx['auto'] ?? [] ); $ai = [];
	foreach ( (array) ( is_array( $j ) ? ( $j['issues'] ?? [] ) : [] ) as $x ) {
		if ( ! is_array( $x ) ) { continue; }
		$t = $cl( $x['title'] ?? '' ); $d = $cl( $x['detail'] ?? '' ); $o = $strip( $x['obs'] ?? '' ); $ev = $cl( $x['evidence'] ?? '' );
		if ( $o === '' || $t === '' ) { continue; }
		$all = strtolower( $t . ' ' . $d . ' ' . $o );
		if ( ! empty( $ctx['ig'] ) && preg_match( empty( $ctx['bio'] ) ? '/\b(bio|highlights?|stor(y|ies)|profile (pic|photo|picture))\b/' : '/\b(highlights?|stor(y|ies))\b/', $all ) ) { continue; }
		if ( empty( $ctx['eyes'] ) && preg_match( '/\b(design(s|ed)?|colou?rs?|fonts?|graphics?|blurry|pixelated|aesthetics?|layout|visual(s|ly)?|templates?|backgrounds?|lighting|photo quality|low quality|cluttered|branding|homemade)\b/', $all ) ) { continue; }
		if ( $ev === '' && ! preg_match( '/\d/', $o . $d ) ) { continue; }
		/* Low engagement is only said when the numbers show it (under 0.6% of followers on a typical post). */
		if ( ( $ctx['rate'] ?? -1 ) >= 0.6 && preg_match( '/\b(engag\w*|likes?|comments?|interactions?|reactions?)\b/', $all ) && preg_match( '/\b(low|few|little|poor|weak|barely|hardly|not many|not getting|struggl\w*)\b/', $all ) ) { continue; }
		/* Every figure it quotes must be one we gave it. */
		$bad = false;
		if ( preg_match_all( '/(\d[\d,]*(?:\.\d+)?)(\s?%|\s?[km]\b)?/i', $o . ' ' . $d, $nm, PREG_SET_ORDER ) ) {
			foreach ( $nm as $q ) { $num = str_replace( ',', '', $q[1] ); $suf = strtolower( trim( $q[2] ?? '' ) ); if ( $suf === 'k' || $suf === 'm' ) { continue; } if ( $suf !== '%' && (float) $num <= 12 ) { continue; } if ( strpos( $facts, $num ) === false ) { $bad = true; break; } }
		}
		if ( $bad ) { continue; }
		$ai[] = [ 'title' => $t, 'detail' => $d, 'obs' => $o, 'impact' => $cl( $x['impact'] ?? '' ), 'fix' => $cl( $x['fix'] ?? '' ) ];
	}
	$issues = array_slice( $ai, 0, 3 );
	$covered = strtolower( implode( ' ', array_map( function ( $x ) { return $x['title'] . ' ' . $x['obs']; }, $ai ) ) );
	foreach ( $auto as $a ) { if ( count( $issues ) >= 5 ) { break; } if ( $covered !== '' && preg_match( '/' . $a['re'] . '/', $covered ) ) { continue; } $issues[] = [ 'title' => $a['title'], 'detail' => $a['detail'], 'obs' => $a['obs'], 'impact' => $a['impact'], 'fix' => $a['fix'] ]; }
	foreach ( array_slice( $ai, 3 ) as $x ) { if ( count( $issues ) < 5 ) { $issues[] = $x; } }
	if ( ! $issues ) { return new WP_Error( 'ai', is_array( $j ) ? 'We read their page but could not prove a clear problem from it. Add a screenshot of their profile so we can look closer.' : 'The analysis did not finish. Tap the button again.' ); }
	$j = is_array( $j ) ? $j : [];
	$like = preg_replace( '/^i really like\s+/i', '', $strip( $j['like'] ?? '' ) );
	if ( empty( $ctx['eyes'] ) && preg_match( '/\b(design|colou?rs?|aesthetics?|visuals?|branding|photos?|pictures?|feed looks)\b/i', $like ) ) { $like = ''; }
	/* A compliment must be about their work: no quotes, numbers, sales or announcements, no follower talk, and it must read after "I really like your". */
	$okL = function ( $l ) { return $l !== '' && ! preg_match( '/["“”]|\d|\b(sale|sales|discount|promo|offer ends|ends in|deadline|giveaway|announce\w*|follow\w*|likes|views|engagement|posting|post that said|caption|hashtags?|link in bio|dm me|comment)\b|^(page|content|account|posts?)$/i', $l ) && str_word_count( $l ) >= 2 && str_word_count( $l ) <= 14; };
	if ( ! $okL( $like ) ) { $like = ''; }
	$likes = [];
	foreach ( (array) ( $j['likes'] ?? [] ) as $l ) { if ( ! $okL( $strip( (string) $l ) ) ) { continue; } $l = preg_replace( '/^i really like\s+/i', '', $strip( $l ) ); if ( $l !== '' && $l !== $like && ! ( empty( $ctx['eyes'] ) && preg_match( '/\b(design|colou?rs?|aesthetics?|visuals?|branding|photos?|pictures?)\b/i', $l ) ) ) { $likes[] = $l; } }
	/* The compliment is never left empty: when the AI gives none, it comes from their best performing posts. */
	if ( $like === '' || count( $likes ) < 2 ) {
		$fb = ir_sc_like_fb( (string) ( $ctx['facts'] ?? '' ) );
		if ( $like === '' && $fb ) { $like = array_shift( $fb ); }
		foreach ( $fb as $l ) { if ( count( $likes ) >= 2 ) { break; } if ( $l !== $like ) { $likes[] = $l; } }
	}
	$best = (array) ( $j['best'] ?? [] );
	$c = array_merge( [ 'email' => '', 'whatsapp' => '', 'instagram' => '', 'phone' => '' ], array_map( 'strval', (array) ( $ctx['contact'] ?? [] ) ) );
	$own = (string) ( $ctx['owner'] ?? '' );
	return [
		'kind' => (string) ( $ctx['kind'] ?? '' ), 'name' => $cl( $j['name'] ?? '' ) ?: ( $own !== '' ? explode( ' ', $own )[0] : '' ), 'owner' => $own, 'business' => $cl( ( $j['business'] ?? '' ) ?: ( $ctx['name'] ?? '' ) ), 'summary' => $cl( $j['summary'] ?? '' ),
		'need' => max( 1, min( 10, (int) ( $j['need'] ?? ( 4 + count( $issues ) ) ) ) ), 'like' => $like, 'likes' => array_slice( $likes, 0, 3 ), 'issues' => $issues, 'tip' => $cl( $j['tip'] ?? '' ),
		'best' => [ 'dm' => preg_match( '/^d-[a-z]+$/', (string) ( $best['dm'] ?? '' ) ) ? $best['dm'] : 'd-problem', 'email' => preg_match( '/^e-[a-z]+$/', (string) ( $best['email'] ?? '' ) ) ? $best['email'] : 'e-problem', 'why' => $cl( $best['why'] ?? '' ) ],
		'contact' => $c, 'based' => (string) ( $ctx['based'] ?? '' ), 'checked' => count( $auto ), 'ng' => $ctx['ng'] ?? null,
		'facts' => mb_substr( (string) ( $ctx['facts'] ?? '' ), 0, 3500 ),
	];
}

add_filter( 'ir_ai_tools', function ( $t ) {
	$t['scan2'] = [ 'access' => function () { return is_user_logged_in() && ( current_user_can( 'manage_options' ) || ( function_exists( 'ir_pb_has_access' ) && ir_pb_has_access() ) ); }, 'limit' => 80, 'run' => 'ir_sc_run' ];
	return $t;
} );