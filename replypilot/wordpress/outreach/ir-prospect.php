<?php
/**
 * Plugin Name: IR Prospect reader
 * Description: Reads a prospect's full Instagram profile (bio, links, category, posts) and Google Business listing
 *   (phone, address, website, rating, reviews, hours) through Apify, because Instagram and Google block website servers.
 *   Finds their email and phone on the website they link to. Also rewrites drafted outreach so it reads naturally,
 *   is about the prospect and ends with an easy question. Apify token: Settings > Prospect reader.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------- settings ---------- */

function ir_px_token() {
	$v = (string) get_option( 'ir_px_apify', '' );
	if ( $v === '' ) { return ''; }
	return function_exists( 'ir_dm_dec' ) && strpos( $v, 'v1.' ) === 0 ? ir_dm_dec( $v ) : $v;
}
add_action( 'admin_menu', function () {
	add_options_page( 'Prospect reader', 'Prospect reader', 'manage_options', 'ir-prospect', 'ir_px_admin' );
} );
function ir_px_admin() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$msg = '';
	if ( isset( $_POST['ir_px_save'] ) && check_admin_referer( 'ir_px' ) ) {
		$t = trim( sanitize_text_field( wp_unslash( (string) ( $_POST['tok'] ?? '' ) ) ) );
		if ( ! empty( $_POST['clear'] ) ) { delete_option( 'ir_px_apify' ); $msg = 'Token removed.'; }
		elseif ( $t !== '' ) {
			$r = wp_remote_get( 'https://api.apify.com/v2/users/me?token=' . rawurlencode( $t ), [ 'timeout' => 15 ] );
			if ( is_wp_error( $r ) || (int) wp_remote_retrieve_response_code( $r ) !== 200 ) { $msg = 'Apify did not accept this token. Copy it again from Apify Console > Settings > API & Integrations.'; }
			else { update_option( 'ir_px_apify', function_exists( 'ir_dm_enc' ) ? ir_dm_enc( $t ) : $t, false ); $msg = 'Saved and checked. Instagram profiles and Google listings will now be read in full.'; }
		}
	}
	$has = ir_px_token() !== '';
	echo '<div class="wrap"><h1>Prospect reader</h1>';
	if ( $msg ) { echo '<div class="notice notice-info"><p>' . esc_html( $msg ) . '</p></div>'; }
	echo '<p style="max-width:760px">Instagram and Google block website servers, so the Smart Writer, Call Script and Scanner read prospects through your <b>Apify</b> account (about $0.003 per Instagram profile and $0.005 per Google listing). Paste your Apify API token from <b>Apify Console → Settings → API &amp; Integrations</b>.</p>';
	echo '<p>Status: ' . ( $has ? '<b style="color:#16a34a">✓ Connected</b>' : '<b style="color:#dc2626">Not connected</b> (only basic details can be read)' ) . '</p>';
	echo '<form method="post">';
	wp_nonce_field( 'ir_px' );
	echo '<input type="password" name="tok" class="regular-text" placeholder="' . ( $has ? 'Saved. Paste a new token to replace it' : 'apify_api_...' ) . '" autocomplete="off"> ';
	submit_button( 'Save token', 'primary', 'ir_px_save', false );
	if ( $has ) { echo ' <label style="margin-left:12px"><input type="checkbox" name="clear" value="1"> Remove token</label>'; }
	echo '</form></div>';
}

/* ---------- Apify ---------- */

function ir_px_apify( $actor, $input, $timeout = 60 ) {
	$t = ir_px_token();
	if ( $t === '' ) { return new WP_Error( 'notoken', 'no token' ); }
	@set_time_limit( $timeout + 30 );
	$r = wp_remote_post( 'https://api.apify.com/v2/acts/' . $actor . '/run-sync-get-dataset-items?timeout=' . (int) $timeout . '&token=' . rawurlencode( $t ), [ 'timeout' => $timeout + 10, 'headers' => [ 'Content-Type' => 'application/json' ], 'body' => wp_json_encode( $input ) ] );
	if ( is_wp_error( $r ) ) { return $r; }
	$code = (int) wp_remote_retrieve_response_code( $r );
	$j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	if ( $code >= 300 || ! is_array( $j ) ) { return new WP_Error( 'apify', 'Apify error ' . $code . ( isset( $j['error']['message'] ) ? ': ' . $j['error']['message'] : '' ) ); }
	return $j;
}

/* ---------- Instagram ---------- */

function ir_px_ig_raw( $handles ) {
	$need = []; $out = [];
	foreach ( (array) $handles as $h ) {
		$h = strtolower( trim( (string) $h, "@/ \t" ) );
		if ( ! preg_match( '/^[a-z0-9._]{2,30}$/', $h ) ) { continue; }
		$c = get_transient( 'ir_px_igr_' . md5( $h ) );
		if ( is_array( $c ) ) { $out[ $h ] = $c; } else { $need[] = $h; }
	}
	if ( $need ) {
		$j = ir_px_apify( 'apify~instagram-profile-scraper', [ 'usernames' => array_values( array_unique( $need ) ), 'resultsLimit' => 12 ], min( 120, 40 + 6 * count( $need ) ) );
		if ( is_wp_error( $j ) ) { return $out ?: $j; }
		foreach ( $j as $u ) {
			$h = strtolower( (string) ( $u['username'] ?? '' ) );
			if ( $h === '' || ! empty( $u['error'] ) ) { continue; }
			set_transient( 'ir_px_igr_' . md5( $h ), $u, 12 * HOUR_IN_SECONDS );
			$out[ $h ] = $u;
		}
	}
	return $out;
}

/** Same shape as the Scanner's own reader (facts, contact, name, stats, caps, imgs), plus the bio and links. */
function ir_px_ig( $h ) {
	$h = strtolower( trim( (string) $h, "@/ \t" ) );
	$ck = 'ir_px_ig_' . md5( $h );
	$c = get_transient( $ck );
	if ( is_array( $c ) ) { return $c; }
	$all = ir_px_ig_raw( [ $h ] );
	if ( is_wp_error( $all ) ) { return $all; }
	$u = $all[ $h ] ?? null;
	if ( ! $u ) { return new WP_Error( 'ig', 'We could not find @' . $h . '. Check the handle is right and the account is public.' ); }
	if ( ! empty( $u['private'] ) ) { return new WP_Error( 'ig', '@' . $h . ' is a private account, so its posts cannot be read. Add a screenshot instead.' ); }
	$posts = [];
	foreach ( array_slice( (array) ( $u['latestPosts'] ?? [] ), 0, 12 ) as $p ) {
		$ty = strtolower( (string) ( $p['type'] ?? '' ) );
		$posts[] = [
			'ts' => strtotime( (string) ( $p['timestamp'] ?? '' ) ) ?: 0,
			'kind' => $ty === 'video' ? 'video' : ( $ty === 'sidecar' ? 'carousel' : 'photo' ),
			'likes' => max( 0, (int) ( $p['likesCount'] ?? 0 ) ), 'comments' => (int) ( $p['commentsCount'] ?? 0 ), 'hidden' => (int) ( $p['likesCount'] ?? 0 ) < 0,
			'cap' => trim( preg_replace( '/\s+/', ' ', (string) ( $p['caption'] ?? '' ) ) ), 'pinned' => ! empty( $p['isPinned'] ), 'img' => (string) ( $p['displayUrl'] ?? '' ),
		];
	}
	$fol = (int) ( $u['followersCount'] ?? 0 ); $now = time(); $seen = count( $posts );
	$ts = array_filter( wp_list_pluck( array_filter( $posts, function ( $p ) { return ! $p['pinned']; } ), 'ts' ) );
	if ( ! $ts ) { $ts = array_filter( wp_list_pluck( $posts, 'ts' ) ); }
	$last = $ts ? max( $ts ) : 0;
	$counted = array_values( array_filter( $posts, function ( $p ) { return ! $p['hidden']; } ) );
	$st = [
		'followers' => $fol, 'posts' => (int) ( $u['postsCount'] ?? 0 ), 'seen' => $seen,
		'days' => $last ? (int) floor( ( $now - $last ) / DAY_IN_SECONDS ) : -1, 'last' => $last ? gmdate( 'j M Y', $last ) : '',
		'n30' => count( array_filter( $posts, function ( $p ) use ( $now ) { return $p['ts'] > $now - 30 * DAY_IN_SECONDS; } ) ),
		'videos' => count( array_filter( $posts, function ( $p ) { return $p['kind'] === 'video'; } ) ),
		'cta' => count( array_filter( $posts, function ( $p ) { return (bool) preg_match( '/\bbook|booking|link in (my |our |the )?bio|\bdm\b|dm (me|us)|send (me|us) a|message (me|us)|whatsapp|call (me|us)|appointment|tap the link|order now|slots?\b|available|click the link/i', $p['cap'] ); } ) ),
		'nocap' => count( array_filter( $posts, function ( $p ) { return mb_strlen( trim( preg_replace( '/#[\p{L}\p{N}_]+|[^\p{L}\p{N}\s]/u', ' ', $p['cap'] ) ) ) < 20; } ) ),
		'avg' => -1,
	];
	if ( count( $counted ) >= 3 ) { $e = array_map( function ( $p ) { return $p['likes'] + $p['comments']; }, $counted ); sort( $e ); $m = count( $e ); $st['avg'] = (int) round( $m % 2 ? $e[ ( $m - 1 ) / 2 ] : ( $e[ $m / 2 - 1 ] + $e[ $m / 2 ] ) / 2 ); }
	/* Links in bio, WhatsApp and the website to read for an email. */
	$links = []; $wa = ''; $site = '';
	foreach ( array_merge( [ [ 'url' => (string) ( $u['externalUrl'] ?? '' ) ] ], (array) ( $u['externalUrls'] ?? [] ) ) as $l ) {
		$x = (string) ( $l['url'] ?? '' ); if ( $x === '' ) { continue; }
		$x = preg_replace( '/\?countryName=.*$/', '', $x );
		$links[ $x ] = 1;
		if ( preg_match( '#(?:wa\.me/|whatsapp\.com/send\?phone=)(\d{8,15})#i', $x, $m ) ) { $wa = $wa ?: $m[1]; }
		elseif ( $site === '' && ! preg_match( '#instagram\.com|facebook\.com|tiktok\.com|youtube\.com|wa\.me|whatsapp#i', $x ) ) { $site = $x; }
	}
	$bio = trim( (string) ( $u['biography'] ?? '' ) );
	preg_match( '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $bio, $bm );
	$email = strtolower( $bm[0] ?? (string) ( $u['businessEmail'] ?? $u['publicEmail'] ?? '' ) );
	$phone = (string) ( $u['businessPhoneNumber'] ?? $u['publicPhoneNumber'] ?? '' );
	$pic = (string) ( $u['profilePicUrl'] ?? '' );
	$defpic = $pic === '' || strpos( $pic, '44884218_345707102882519_2446069589734326272_n' ) !== false;
	if ( $site !== '' && ( $email === '' || $phone === '' ) && function_exists( 'ir_cf_find' ) ) {
		$cf = (array) ir_cf_find( $site );
		if ( $email === '' && ! empty( $cf['email'] ) ) { $email = (string) $cf['email']; }
		if ( $phone === '' && ! empty( $cf['phone'] ) ) { $phone = (string) $cf['phone']; }
		if ( $wa === '' && ! empty( $cf['whatsapp'] ) ) { $wa = (string) $cf['whatsapp']; }
	}
	$name = trim( (string) ( $u['fullName'] ?? '' ) );
	$lines = [];
	foreach ( $posts as $i => $p ) {
		$lines[] = 'Post ' . ( $i + 1 ) . ' (' . ( $p['ts'] ? gmdate( 'j M Y', $p['ts'] ) : 'date unknown' ) . ( $p['pinned'] ? ', pinned' : '' ) . ', ' . $p['kind'] . ( $p['hidden'] ? ', likes hidden' : ', ' . $p['likes'] . ' likes' ) . ', ' . $p['comments'] . ' comments): ' . ( $p['cap'] !== '' ? mb_substr( $p['cap'], 0, 260 ) : '(no caption)' );
	}
	$facts = 'Instagram profile @' . $h . "\nName on the profile: " . $name
		. "\nBusiness name (clean): " . ir_px_clean_biz( $name ?: $h )
		. "\nCategory: " . ( (string) ( $u['businessCategoryName'] ?? '' ) ?: 'not shown' )
		. "\nBio: " . ( $bio !== '' ? str_replace( "\n", ' / ', $bio ) : '(empty)' )
		. "\nLinks in bio: " . ( $links ? implode( ', ', array_keys( $links ) ) : 'none' )
		. "\nProfile picture: " . ( $defpic ? 'none (default avatar)' : 'yes' )
		. "\nVerified: " . ( ! empty( $u['verified'] ) ? 'yes' : 'no' )
		. "\nFollowers: " . $fol . ' · Following: ' . (int) ( $u['followsCount'] ?? 0 ) . ' · Total posts: ' . $st['posts']
		. "\nNewest post: " . ( $last ? $st['last'] . ' (' . $st['days'] . ' days ago)' : 'none visible' )
		. "\nOf the " . $seen . ' latest posts: ' . $st['n30'] . ' were posted in the last 30 days, ' . $st['videos'] . ' are Reels or videos, ' . $st['cta'] . ' have a caption that tells people how to book, DM or click, ' . $st['nocap'] . ' have little or no caption.'
		. ( $fol && $st['avg'] >= 0 ? "\nA typical post gets " . $st['avg'] . ' likes plus comments (' . round( $st['avg'] / max( 1, $fol ) * 100, 2 ) . '% of followers)' : '' )
		. ( $email !== '' ? "\nEmail: " . $email : '' ) . ( $wa !== '' ? "\nWhatsApp: +" . $wa : '' ) . ( $phone !== '' ? "\nPhone: " . $phone : '' )
		. "\nThe " . $seen . " latest posts:\n" . ( $lines ? implode( "\n", $lines ) : '(none visible)' );
	$out = [ 'facts' => $facts, 'contact' => array_filter( [ 'instagram' => $h, 'email' => $email, 'whatsapp' => $wa, 'phone' => $phone, 'website' => $site ] ), 'name' => $name, 'stats' => $st,
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

/* ---------- Google Business ---------- */

/** Follows share.google / maps.app.goo.gl / g.page links to the business name, Google's id for it and the country. */
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
	$name = (string) ( $q['q'] ?? '' );
	if ( $name === '' && preg_match( '#/maps/place/([^/@?]+)#', $all, $m ) ) { $name = str_replace( '+', ' ', rawurldecode( $m[1] ) ); }
	$cc = '';
	if ( preg_match( '/[?&]hl=[a-z]{2}-([a-z]{2})/i', $all, $m ) ) { $cc = strtolower( $m[1] ); } elseif ( ! empty( $q['gl'] ) ) { $cc = strtolower( (string) $q['gl'] ); }
	$countries = [ 'ng' => 'Nigeria', 'gb' => 'United Kingdom', 'uk' => 'United Kingdom', 'us' => 'United States', 'gh' => 'Ghana', 'ke' => 'Kenya', 'za' => 'South Africa', 'ca' => 'Canada', 'ae' => 'United Arab Emirates', 'au' => 'Australia', 'ie' => 'Ireland', 'in' => 'India', 'de' => 'Germany', 'fr' => 'France', 'nl' => 'Netherlands' ];
	return [ 'name' => trim( $name ), 'kgmid' => (string) ( $q['kgmid'] ?? '' ), 'country' => $countries[ $cc ] ?? '', 'final' => $u ];
}

function ir_px_google( $url ) {
	$ck = 'ir_px_g_' . md5( $url );
	$c = get_transient( $ck );
	if ( is_array( $c ) ) { return $c; }
	$g = ir_px_g_resolve( $url );
	if ( is_wp_error( $g ) ) { return $g; }
	if ( $g['name'] === '' ) { return new WP_Error( 'google', 'We could not tell which business this Google link is for. Paste their website link, or add a screenshot of their Google listing.' ); }
	$basic = [ 'facts' => "Google Business listing\nName: " . $g['name'] . ( $g['country'] ? "\nCountry: " . $g['country'] : '' ), 'contact' => [], 'name' => $g['name'], 'phone' => '', 'phones' => [], 'thin' => true ];
	$in = [ 'searchStringsArray' => [ $g['name'] ], 'maxCrawledPlacesPerSearch' => 3, 'language' => 'en', 'maxReviews' => 5, 'reviewsSort' => 'newest', 'scrapeContacts' => false ];
	if ( $g['country'] ) { $in['locationQuery'] = $g['country']; }
	$j = ir_px_apify( 'compass~crawler-google-places', $in, 90 );
	if ( is_wp_error( $j ) || ! $j ) { return $basic; }
	$p = $j[0];
	foreach ( $j as $x ) { if ( $g['kgmid'] !== '' && (string) ( $x['kgmid'] ?? '' ) === $g['kgmid'] ) { $p = $x; break; } }
	$hours = implode( ', ', array_map( function ( $h ) { return ( $h['day'] ?? '' ) . ' ' . ( $h['hours'] ?? '' ); }, (array) ( $p['openingHours'] ?? [] ) ) );
	$revs = [];
	foreach ( array_slice( (array) ( $p['reviews'] ?? [] ), 0, 5 ) as $rv ) {
		$t = trim( preg_replace( '/\s+/', ' ', (string) ( $rv['text'] ?? '' ) ) );
		$revs[] = '- ' . (int) ( $rv['stars'] ?? 0 ) . ' stars' . ( ! empty( $rv['publishAt'] ) ? ' (' . $rv['publishAt'] . ')' : '' ) . ': ' . ( $t !== '' ? mb_substr( $t, 0, 220 ) : '(no text)' ) . ( ! empty( $rv['responseFromOwnerText'] ) ? ' [owner replied]' : ' [no owner reply]' );
	}
	$site = preg_replace( '/\?utm_.*$/', '', (string) ( $p['website'] ?? '' ) );
	$phone = (string) ( $p['phone'] ?? '' );
	$email = '';
	if ( $site !== '' && function_exists( 'ir_cf_find' ) ) { $cf = (array) ir_cf_find( $site ); $email = (string) ( $cf['email'] ?? '' ); if ( $phone === '' ) { $phone = (string) ( $cf['phone'] ?? '' ); } }
	$facts = "Google Business listing\nName: " . ( $p['title'] ?? $g['name'] ) . "\nCategory: " . ( $p['categoryName'] ?? '' )
		. "\nAddress: " . ( $p['address'] ?? 'not shown' ) . "\nPhone: " . ( $phone ?: 'not shown' ) . "\nWebsite: " . ( $site ?: 'none on the listing' )
		. ( $email !== '' ? "\nEmail (from their website): " . $email : '' )
		. "\nRating: " . ( isset( $p['totalScore'] ) ? $p['totalScore'] . ' stars from ' . (int) ( $p['reviewsCount'] ?? 0 ) . ' reviews' : 'no rating yet' )
		. "\nPhotos on the listing: " . (int) ( $p['imagesCount'] ?? 0 )
		. "\nOpening hours: " . ( $hours ?: 'not shown' )
		. ( ! empty( $p['bookingLinks'] ) ? "\nBooking link on the listing: yes" : "\nBooking link on the listing: no" )
		. ( ! empty( $p['description'] ) ? "\nDescription: " . $p['description'] : '' )
		. ( $revs ? "\nNewest reviews:\n" . implode( "\n", $revs ) : '' );
	$out = [ 'facts' => $facts, 'contact' => array_filter( [ 'phone' => $phone, 'email' => $email, 'website' => $site ] ), 'name' => (string) ( $p['title'] ?? $g['name'] ), 'phone' => $phone, 'phones' => $phone ? [ $phone ] : [], 'email' => $email, 'website' => $site ];
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
