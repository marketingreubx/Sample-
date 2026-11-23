<?php
/**
 * Bulk DMs: paste up to 5 pages (Instagram profiles, @handles or websites) and the site reads each one
 * and finds what to mention in the DM. Instagram profiles are read from their public profile embed.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** An Instagram profile, read from its public embed (works when the profile API is blocked). */
function ir_ct_ig_embed( $h ) {
	$h = strtolower( trim( (string) $h, "@/ \t" ) );
	if ( ! preg_match( '/^[a-z0-9._]{2,30}$/', $h ) ) { return new WP_Error( 'ig', 'That does not look like an Instagram handle.' ); }
	if ( function_exists( 'ir_px_ig' ) && ir_px_token() !== '' ) { $px = ir_px_ig( $h ); if ( ! is_wp_error( $px ) ) { return $px; } }
	$ck = 'ir_ct_ige_' . md5( $h );
	$c = get_transient( $ck );
	if ( is_array( $c ) ) { return $c; }
	$r = wp_remote_get( 'https://www.instagram.com/' . $h . '/embed/', [ 'timeout' => 15, 'redirection' => 3, 'headers' => [ 'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', 'Accept-Language' => 'en-GB,en;q=0.9' ] ] );
	if ( is_wp_error( $r ) || (int) wp_remote_retrieve_response_code( $r ) !== 200 ) { return new WP_Error( 'ig_blocked', 'Instagram did not open @' . $h . ' right now.', [ 'handle' => $h ] ); }
	$b = (string) wp_remote_retrieve_body( $r );
	if ( ! preg_match( '/"contextJSON":("(?:[^"\\\\]++|\\\\.)*+")/s', $b, $m ) ) { return new WP_Error( 'ig_blocked', 'Instagram did not show @' . $h . '. Check the handle is right and the account is public.', [ 'handle' => $h ] ); }
	$j = json_decode( (string) json_decode( $m[1] ), true );
	$x = is_array( $j ) ? ( $j['context'] ?? null ) : null;
	if ( ! is_array( $x ) || empty( $x['username'] ) ) { return new WP_Error( 'ig_blocked', 'Instagram did not show @' . $h . '. Check the handle is right and the account is public.', [ 'handle' => $h ] ); }
	$posts = [];
	foreach ( array_slice( (array) ( $x['graphql_media'] ?? [] ), 0, 12 ) as $g ) {
		$n = (array) ( $g['shortcode_media'] ?? [] );
		if ( ! $n ) { continue; }
		$ty = (string) ( $n['__typename'] ?? '' );
		$posts[] = [
			'ts'       => (int) ( $n['taken_at_timestamp'] ?? 0 ),
			'kind'     => ( ! empty( $n['is_video'] ) || $ty === 'GraphVideo' ) ? 'video' : ( $ty === 'GraphSidecar' ? 'carousel' : 'photo' ),
			'likes'    => (int) ( $n['edge_liked_by']['count'] ?? 0 ),
			'comments' => (int) ( $n['edge_media_to_comment']['count'] ?? 0 ),
			'cap'      => trim( preg_replace( '/\s+/', ' ', (string) ( $n['edge_media_to_caption']['edges'][0]['node']['text'] ?? '' ) ) ),
			'pinned'   => ! empty( $n['pinned_for_users'] ),
		];
	}
	$fol = (int) ( $x['followers_count'] ?? 0 );
	$now = time();
	$ts = array_filter( wp_list_pluck( array_filter( $posts, function ( $p ) { return ! $p['pinned']; } ), 'ts' ) );
	if ( ! $ts ) { $ts = array_filter( wp_list_pluck( $posts, 'ts' ) ); }
	$last = $ts ? max( $ts ) : 0;
	$st = [
		'followers' => $fol,
		'posts'     => (int) ( $x['posts_count'] ?? 0 ),
		'seen'      => count( $posts ),
		'days'      => $last ? (int) floor( ( $now - $last ) / DAY_IN_SECONDS ) : -1,
		'n30'       => count( array_filter( $posts, function ( $p ) use ( $now ) { return $p['ts'] > $now - 30 * DAY_IN_SECONDS; } ) ),
		'videos'    => count( array_filter( $posts, function ( $p ) { return $p['kind'] === 'video'; } ) ),
		'cta'       => count( array_filter( $posts, function ( $p ) { return (bool) preg_match( '/\bbook|booking|link in (my )?bio|\bdm\b|dm me|send (me|us) a|whatsapp|call (me|us)|appointment|tap the link|order now|slots?\b|available/i', $p['cap'] ); } ) ),
		'nocap'     => count( array_filter( $posts, function ( $p ) { return mb_strlen( $p['cap'] ) < 15; } ) ),
		'avg'       => $posts ? (int) round( array_sum( array_map( function ( $p ) { return $p['likes'] + $p['comments']; }, $posts ) ) / count( $posts ) ) : 0,
	];
	$lines = [];
	foreach ( $posts as $p ) {
		$lines[] = '- ' . ( $p['ts'] ? gmdate( 'j M Y', $p['ts'] ) : '?' ) . ( $p['pinned'] ? ' (pinned)' : '' ) . ', ' . $p['kind'] . ', ' . $p['likes'] . ' likes, ' . $p['comments'] . ' comments: ' . ( $p['cap'] !== '' ? mb_substr( $p['cap'], 0, 170 ) : '(no caption)' );
	}
	$facts = 'Instagram profile @' . $x['username'] . "\nName: " . ( $x['full_name'] ?? '' ) . "\nFollowers: " . $fol . "\nPosts: " . $st['posts']
		. "\nLast post: " . ( $last ? gmdate( 'j M Y', $last ) . ' (' . $st['days'] . ' days ago)' : 'none' )
		. "\nPosts in the last 30 days: " . $st['n30'] . ' (of the ' . $st['seen'] . ' latest we can see)'
		. "\nVideos or Reels among the latest " . $st['seen'] . ': ' . $st['videos']
		. "\nLatest posts with a call to book, DM or click: " . $st['cta'] . ' of ' . $st['seen']
		. ( $fol && $posts ? "\nAverage likes plus comments per post: " . $st['avg'] . ' (' . round( $st['avg'] / max( 1, $fol ) * 100, 2 ) . '% of followers)' : '' )
		. "\nNot visible to us: their bio, link in bio and story highlights. Never mention those."
		. "\nLatest posts:\n" . ( $lines ? implode( "\n", $lines ) : '(none we can see; the account may be new or private)' );
	$out = [ 'facts' => $facts, 'contact' => [ 'instagram' => (string) $x['username'] ], 'name' => (string) ( $x['full_name'] ?? '' ), 'stats' => $st ];
	set_transient( $ck, $out, 6 * HOUR_IN_SECONDS );
	return $out;
}

/** Turn one pasted line into a link we can read. */
function ir_ct_bulk_url( $tok ) {
	$tok = trim( (string) $tok, " \t,;" );
	if ( $tok === '' ) { return ''; }
	if ( ! preg_match( '#^https?://#i', $tok ) ) {
		$handle = $tok[0] === '@' || ( preg_match( '/^[a-z0-9._]{2,30}$/i', $tok ) && ! preg_match( '/\.[a-z]{2,}$/i', $tok ) );
		$tok = $handle ? 'https://www.instagram.com/' . ltrim( $tok, '@' ) . '/' : 'https://' . $tok;
	}
	return esc_url_raw( $tok );
}

/** Notes we can always write from the numbers, used when the AI is busy. */
function ir_ct_bulk_auto( $st ) {
	$gaps = []; $other = [];
	if ( ! $st ) { return [ $gaps, $other ]; }
	if ( $st['days'] >= 14 ) { $other[] = 'last post was ' . $st['days'] . ' days ago'; }
	elseif ( $st['seen'] >= 6 && $st['n30'] <= 3 ) { $other[] = 'just ' . $st['n30'] . ' post' . ( $st['n30'] === 1 ? '' : 's' ) . ' in the last month'; }
	if ( $st['seen'] >= 6 && $st['videos'] === 0 ) { $gaps[] = 'reels'; }
	if ( $st['seen'] >= 4 && $st['cta'] === 0 ) { $gaps[] = 'clear call to book in your captions'; }
	if ( $st['seen'] >= 4 && $st['nocap'] >= (int) ceil( $st['seen'] / 2 ) ) { $other[] = 'most posts go up with almost nothing in the caption'; }
	return [ $gaps, $other ];
}

/** The note format the bulk writer understands: "no X, no Y, something seen, love Z". */
function ir_ct_bulk_note( $gaps, $other, $like ) {
	$c = function ( $s ) { return trim( preg_replace( [ '/[,.!;:]+/', '/\s*[\x{2013}\x{2014}]\s*/u', '/\s+/' ], [ ' ', ' ', ' ' ], strtolower( (string) $s ) ) ); };
	$parts = [];
	foreach ( array_slice( (array) $gaps, 0, 2 ) as $g ) {
		$g = preg_replace( [ '/^(there (is|are) )?(no|not any|zero|without|any)\s+/', '/\b(booking )?(cta|call to action)s?( for booking)?\b/' ], [ '', 'call to book' ], $c( $g ) );
		if ( $g !== '' && mb_strlen( $g ) < 60 ) { $parts[] = 'no ' . $g; }
	}
	foreach ( array_slice( (array) $other, 0, 1 ) as $o ) {
		$o = preg_replace( [ '/\bonly\b/', '/^(i noticed|noticed)\s+/', '/^your\s+/' ], [ 'just', '', '' ], $c( $o ) );
		if ( $o === '' || mb_strlen( $o ) > 80 || preg_match( '/\b(no|not|zero|without|lots of|a lot of|too many|few|love|like)\b/', $o ) ) { continue; }
		$parts[] = $o;
	}
	$l = preg_replace( [ '/^(i )?(really )?(love|like|loved)\s+/', '/^your\s+/' ], '', $c( $like ) );
	if ( $l !== '' && mb_strlen( $l ) < 70 && ! preg_match( '/\b(no|not|zero|without)\b/', $l ) ) { $parts[] = 'love ' . $l; }
	return implode( ', ', array_values( array_unique( $parts ) ) );
}

/** What a freelancer in this line of work should look for on a prospect's page. */
function ir_ct_checklist( $job ) {
	$j = strtolower( (string) $job );
	if ( preg_match( '/web|website|wordpress|shopify|funnel|landing|developer|ux|ui\b/', $j ) ) {
		return 'no website at all, or only a link list page; the first screen does not say what they do or who for; no clear button to book, buy or contact near the top; not built for phones; slow or heavy page; no prices or services page; no reviews or results shown; no contact form, WhatsApp or phone link; copyright year is old; missing page title or description for Google; no analytics or pixel; broken or empty links; weak or stock looking pictures';
	}
	if ( preg_match( '/video|reel|editor|videograph|youtube|tiktok/', $j ) ) {
		return 'few or no Reels or videos; videos have no hook in the first line; no captions or text on screen; only photos and flyers; no talking to camera; no behind the scenes; old trends only; captions with no call to action; posting is irregular; covers do not match';
	}
	if ( preg_match( '/design|brand|flyer|graphic|canva|logo/', $j ) ) {
		return 'flyers that look homemade, crowded or made with low quality AI art; no consistent colours or fonts; hard to read text on graphics; no branded carousels; no clear offer or price on promo posts; no logo or a blurry one; covers and highlights do not match; photos and graphics clash';
	}
	if ( preg_match( '/ads?\b|advert|media buy|ppc|meta ads|google ads/', $j ) ) {
		return 'no sign of running ads (no pixel, no offer posts); posts with offers but no clear call to action; no landing page or booking link; no reviews to use as proof; no clear offer or price; website has no pixel or analytics; no lead form or WhatsApp link';
	}
	if ( preg_match( '/copy|caption|writer|email|content writ/', $j ) ) {
		return 'captions that are one line or only hashtags; no hook in the first line; no call to action in captions; bio does not say who they help or how to book; no storytelling or client stories; offers explained poorly; no email sign up';
	}
	return 'no educational Reels or carousels that teach their audience; no well designed promotional flyers or graphics; flyers that look like low quality AI or crowded template art; bio does not say who they help, where they are or how to book; no clear call to action; no booking link, or the link does not go to booking; no trending or relatable content; the feed is only before and after photos; captions are one line or only hashtags; captions have no call to action; posting is irregular or stopped; no faces, voice or behind the scenes; no client reviews shown; no Reels at all';
}

function ir_ct_bulk_run( $in ) {
	$rows = array_slice( array_values( array_filter( array_map( 'trim', explode( "\n", (string) ( $in['list'] ?? '' ) ) ) ) ), 0, 5 );
	if ( ! $rows ) { return new WP_Error( 'in', 'Paste up to 5 Instagram links, @handles or websites, one per line.' ); }
	$people = [];
	/* Read every Instagram profile in one go (bio, links, posts), so a list is quick. */
	if ( function_exists( 'ir_px_ig_raw' ) && ir_px_token() !== '' ) { $hs = []; foreach ( $rows as $row ) { $uu = ir_ct_bulk_url( preg_split( '/\s+/', $row )[0] ); if ( $uu && ir_ct_kind( $uu ) === 'instagram' ) { $sg = strtolower( explode( '/', trim( (string) wp_parse_url( $uu, PHP_URL_PATH ), '/' ) )[0] ?? '' ); if ( ! in_array( $sg, [ 'p', 'reel', 'reels', 'tv' ], true ) ) { $hs[] = $sg; } } } if ( $hs ) { ir_ai_step( 'Reading ' . count( $hs ) . ' Instagram profiles...' ); ir_px_ig_raw( $hs ); } }
	foreach ( $rows as $i => $row ) {
		ir_ai_step( 'Reading page ' . ( $i + 1 ) . ' of ' . count( $rows ) . '...' );
		$tok = preg_split( '/\s+/', $row )[0];
		$url = ir_ct_bulk_url( $tok );
		$p = [ 'in' => $tok, 'url' => $url, 'kind' => $url ? ir_ct_kind( $url ) : '', 'h' => '', 'ok' => false, 'err' => '', 'facts' => '', 'stats' => null, 'name' => '' ];
		if ( ! $url ) { $p['err'] = 'This line is not a link or @handle.'; $people[] = $p; continue; }
		if ( $p['kind'] === 'instagram' ) {
			$seg = strtolower( explode( '/', trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' ) )[0] ?? '' );
			$d = in_array( $seg, [ 'p', 'reel', 'reels', 'tv' ], true ) ? ir_ct_instagram( $url ) : ir_ct_ig_embed( $seg );
			if ( ! in_array( $seg, [ 'p', 'reel', 'reels', 'tv' ], true ) ) { $p['h'] = $seg; }
		} elseif ( $p['kind'] === 'google' ) {
			$d = ir_ct_google( $url );
		} else {
			$d = ir_ct_site( $url );
		}
		if ( is_wp_error( $d ) ) {
			$p['err'] = $d->get_error_message();
		} else {
			$p['ok'] = true;
			$p['facts'] = mb_substr( (string) $d['facts'], 0, $p['kind'] === 'instagram' ? 3200 : 2600 );
			$p['stats'] = $d['stats'] ?? null;
			$p['name'] = (string) ( $d['name'] ?? '' );
			$ig = (string) ( $d['contact']['instagram'] ?? '' );
			if ( $ig !== '' && preg_match( '/^[A-Za-z0-9._]{2,30}$/', $ig ) ) { $p['h'] = strtolower( $ig ); }
		}
		$people[] = $p;
	}
	$read = array_filter( $people, function ( $p ) { return $p['ok']; } );
	$ctx = [ 'people' => $people, 'rows' => count( $rows ) ];
	if ( ! $read ) { return ir_ct_bulk_post( null, $ctx ); }
	{
		ir_ai_step( 'Finding the problems on each page...' );
		$sys = "You help a freelancer send cold Instagram DMs. For each prospect you study the facts we read from their page and find the specific, visible problems that cost them clients, especially ones the freelancer's service fixes.\n\n"
			. "Rules:\n- Only use what is in the facts. Never guess, never invent numbers. If the facts say something is not visible to us (like the bio), never mention it.\n"
			. "- gaps: 0 to 2 things that are missing, each a short noun phrase WITHOUT the word no, e.g. \"reels\", \"client reviews\", \"before and after posts\", \"prices\", \"posts\", \"call to book in your captions\". Plural when it can be. For an Instagram profile we cannot see the bio, link in bio, highlights or contact buttons, so never list those as missing.\n"
			. "- other: 0 to 2 other problems, concrete and checkable, using the real number from the facts when there is one, e.g. \"last post was 47 days ago\", \"just 2 posts in the last month\", \"most posts get under 40 likes\", \"most captions are just hashtags\". Never vague phrases like low engagement or poor branding. Never use the words no, not, only, few, love or like in these. If something is missing, put it in gaps instead.\n"
			. "- like: one sincere, specific compliment about their actual work, taken from their posts or captions (a treatment, a result, a style, a product), 2 to 8 words, e.g. \"your brow lamination results\", \"the hydrafacial before and afters\". Never about followers, likes or numbers. Empty if nothing real.\n"
			. "- summary: 1 short sentence on who they are and the biggest problem.\n"
			. "- WHAT TO LOOK FOR: the freelancer fixes things like these, so check each prospect for them first and report the ones the facts support: " . ir_ct_checklist( (string) ( $in['me_job'] ?? '' ) ) . ". Always find at least one real problem per prospect. If the page is strong, pick the smallest real gap.\n"
			. "- dm: a 3 message sequence for this prospect, written as the freelancer, in this order: [first message, follow-up for day 2, follow-up for day 5].\n"
			. "  First message: starts by tagging them with @ and their handle exactly as given, then one sincere specific compliment, then the ONE problem you found and what it costs them in plain words, then one line on what the freelancer does, then one easy question. 45 to 70 words. No links, no prices, no 'hope you are well'.\n"
			. "  Day 2 follow-up: 20 to 35 words. Do not repeat the first message. Give one small free tip about their problem, then a light question.\n"
			. "  Day 5 follow-up: 15 to 25 words. Friendly, no pressure, leaves the door open.\n"
			. "  Sound like a real person typing on a phone: contractions, short sentences, warm. Every prospect's messages must open differently and be built differently from the others. Never reuse a sentence across prospects. The angle to use: " . ( trim( (string) ( $in['ap'] ?? '' ) ) !== '' ? 'the approach the freelancer picked, ' . sanitize_text_field( (string) $in['ap'] ) : ( [ 'soft' => 'open with a soft question, do not pitch', 'compliment' => 'lead with the compliment and offer a free idea', 'direct' => 'short and direct about what you do and a quick chat', 'problem' => 'the problem you noticed and what it costs' ][ (string) ( $in['ang'] ?? 'problem' ) ] ?? 'the problem you noticed and what it costs' ) ) . ".\n"
			. "- Plain English, lowercase phrases, no em dashes.\n\n"
			. 'Reply with JSON only: {"people":[{"i":1,"business":"name of the business or page","gaps":["..."],"other":["..."],"like":"...","summary":"...","dm":["first message","day 2 follow-up","day 5 follow-up"]}]}';
		$u = "The freelancer:\n" . ir_ct_me( $in ) . "\n\nThe prospects:\n";
		foreach ( $people as $i => $p ) {
			if ( ! $p['ok'] ) { continue; }
			$u .= "\n=== Prospect " . ( $i + 1 ) . ' (' . $p['url'] . ( $p['h'] ? ', handle @' . $p['h'] : '' ) . ") ===\n" . $p['facts'] . "\n";
		}
		$u .= "\nReturn one entry per prospect above, with \"i\" set to the prospect number.";
		/* With an AI key the site answers in seconds. Without one the member's own device writes it, so nobody queues. */
		if ( function_exists( 'ir_v2_ai' ) ) {
			$r = ir_v2_ai( $sys, $u, [ 'json' => 1, 'temp' => 0.5, 'max' => 7000, 'think' => 512 ], 'ir_ct_bulk_post', $ctx );
			return is_wp_error( $r ) ? ir_ct_bulk_post( null, $ctx ) : $r;
		}
		$raw = ir_ai_free( $sys, $u );
		return ir_ct_bulk_post( is_wp_error( $raw ) ? null : ir_ai_json( $raw ), $ctx );
	}
}

/** Turns the AI's notes (or none) into the list the bulk writer shows. */
function ir_ct_bulk_post( $j, $ctx ) {
	$people = (array) ( $ctx['people'] ?? [] ); $ai = [];
	$read = array_filter( $people, function ( $p ) { return $p['ok']; } );
	{
		if ( is_array( $j ) ) {
			foreach ( (array) ( $j['people'] ?? ( isset( $j[0] ) ? $j : [] ) ) as $k => $e ) {
				if ( ! is_array( $e ) ) { continue; }
				$n = (int) ( $e['i'] ?? ( $k + 1 ) );
				if ( $n >= 1 ) { $ai[ $n - 1 ] = $e; }
			}
		}
	}
	$out = [];
	foreach ( $people as $i => $p ) {
		$e = $ai[ $i ] ?? null;
		$note = '';
		if ( $p['ok'] ) {
			list( $ag, $ao ) = ir_ct_bulk_auto( $p['stats'] );
			if ( $e ) {
				$eg = array_values( array_filter( (array) ( $e['gaps'] ?? [] ), function ( $g ) use ( $p ) { return is_string( $g ) && ! ( $p['kind'] === 'instagram' && preg_match( strpos( (string) $p['facts'], "\nBio: " ) !== false ? '/highlight|review|testimonial/i' : '/link|bio|highlight|contact|whatsapp|email|website|button|review|testimonial/i', $g ) ); } ) );
				$eo = array_values( array_filter( (array) ( $e['other'] ?? [] ), function ( $g ) use ( $p ) { return is_string( $g ) && ! preg_match( '/engagement|branding|presence|strategy|consisten|average|per post|followers|analytics/i', $g ) && ! ( $p['kind'] === 'instagram' && preg_match( strpos( (string) $p['facts'], "\nBio: " ) !== false ? '/highlight/i' : '/link|bio|highlight/i', $g ) ); } ) );
				$note = ir_ct_bulk_note( $eg, array_merge( $p['stats'] && $p['stats']['days'] >= 14 ? array_slice( $ao, 0, 1 ) : [], $eo ), preg_match( '/follower|likes|engagement|stats|numbers|new page/i', (string) ( $e['like'] ?? '' ) ) ? '' : (string) ( $e['like'] ?? '' ) );
			}
			if ( $note === '' ) { $note = ir_ct_bulk_note( $ag, $ao, '' ); }
		}
		$out[] = [
			'in'       => $p['in'],
			'url'      => $p['url'],
			'kind'     => $p['kind'],
			'h'        => $p['h'],
			'ng'       => $p['ok'] && function_exists( 'ir_sc_ng' ) ? ir_sc_ng( $p['facts'] . ' ' . $p['url'] ) : null,
			'ok'       => $p['ok'],
			'err'      => $p['ok'] && $p['h'] === '' ? 'We read this page but found no Instagram account on it. Add their @handle.' : $p['err'],
			'note'     => $note,
			'business' => trim( (string) ( $e['business'] ?? $p['name'] ) ),
			'summary'  => trim( preg_replace( '/\s*[\x{2013}\x{2014}]\s*/u', ', ', (string) ( $e['summary'] ?? '' ) ) ),
			'dm'       => array_slice( array_values( array_filter( array_map( function ( $m ) { return is_string( $m ) ? trim( preg_replace( '/\s*[\x{2013}\x{2014}]\s*/u', ', ', $m ) ) : ''; }, (array) ( $e['dm'] ?? [] ) ) ) ), 0, 3 ),
		];
	}
	$log = (array) get_option( 'ir_ct_bulk_log', [] );
	array_unshift( $log, [ gmdate( 'j M H:i' ), get_current_user_id(), (int) ( $ctx['rows'] ?? 0 ), count( $read ), count( $ai ) ] );
	update_option( 'ir_ct_bulk_log', array_slice( $log, 0, 30 ), false );
	return [ 'people' => $out, 'ai' => count( $ai ) > 0 ];
}

add_filter( 'ir_ai_tools', function ( $t ) {
	$t['ctbulk'] = [
		'access' => function () { return is_user_logged_in() && function_exists( 'ir_pb_has_access' ) && ir_pb_has_access(); },
		'limit'  => 60,
		'run'    => function ( $in ) { return ir_ct_bulk_run( $in ); },
	];
	return $t;
} );
