<?php
/**
 * Skin analysis engine for the Skin Assistant.
 *
 * Takes a short consultation (concerns, how skin behaves, sensitivity, breakout
 * pattern, current actives, sun, pregnancy, age, budget, first visit) and builds a
 * personalised plan: skin profile, what is likely going on, a treatment plan with
 * cadence, AM/PM home routine from the shop, what to avoid for now, safety notes and
 * when to see a dermatologist. Works without AI; the AI mode calls it as a tool so
 * recommendations stay consistent with the studio's menu and safety rules.
 *
 * Skincare guidance, not a medical diagnosis.
 *
 * @package SkyncoStudio
 */

defined( 'ABSPATH' ) || exit;

/** Consultation questions, shown as chips in the chat. */
function skynco_skin_questions() {
	return [
		[ 'id' => 'concerns', 'multi' => 3, 'q' => 'What would you like to improve? Pick up to 3.', 'opts' => [
			[ 'breakouts', 'Breakouts or acne' ], [ 'congestion', 'Blackheads and clogged pores' ], [ 'spots', 'Dark spots or uneven tone' ],
			[ 'melasma', 'Patchy brown areas (melasma)' ], [ 'redness', 'Redness or flushing' ], [ 'sensitive', 'Sensitive, reacts easily' ],
			[ 'dry', 'Dryness or tightness' ], [ 'oily', 'Shine and large pores' ], [ 'lines', 'Fine lines or loss of firmness' ],
			[ 'scars', 'Acne scars or rough texture' ], [ 'dull', 'Dull, tired-looking skin' ], [ 'bumps', 'Shaving bumps or ingrown hairs' ],
			[ 'wax', 'Hair removal (waxing)' ],
		] ],
		[ 'id' => 'feel', 'q' => 'By midday, how does your skin feel?', 'opts' => [
			[ 'oily_all', 'Shiny all over' ], [ 'oily_t', 'Shiny forehead and nose only' ], [ 'comfortable', 'Comfortable' ], [ 'tight', 'Tight, dry or flaky' ], [ 'varies', 'It changes a lot' ],
		] ],
		[ 'id' => 'react', 'q' => 'How does your skin react to new products?', 'opts' => [
			[ 'never', 'Rarely reacts' ], [ 'sometimes', 'Sometimes stings or goes pink' ], [ 'often', 'Often burns, stings or flares' ],
		] ],
		[ 'id' => 'breakout', 'if' => [ 'concerns', [ 'breakouts' ] ], 'q' => 'Which sounds most like your breakouts?', 'opts' => [
			[ 'small', 'Occasional small spots' ], [ 'hormonal', 'Chin and jaw, around my cycle or stress' ], [ 'deep', 'Painful, deep or cystic' ], [ 'congested', 'Mostly blackheads and small bumps' ],
		] ],
		[ 'id' => 'actives', 'multi' => 6, 'q' => 'Are you using any of these right now? Pick all that apply.', 'opts' => [
			[ 'none', 'None of these' ], [ 'retinoid', 'Retinol, tretinoin or adapalene' ], [ 'accutane', 'Accutane (isotretinoin), now or in the last 6 months' ],
			[ 'acids', 'Exfoliating acids (AHA, BHA)' ], [ 'bpo', 'Benzoyl peroxide' ], [ 'rx', 'Prescription skin medication' ],
		] ],
		[ 'id' => 'sun', 'q' => 'How often do you wear sunscreen?', 'opts' => [
			[ 'daily', 'Every day' ], [ 'sometimes', 'Sometimes' ], [ 'rarely', 'Rarely' ], [ 'tan', 'I have a recent tan or sunburn' ],
		] ],
		[ 'id' => 'preg', 'q' => 'Are you pregnant, trying, or breastfeeding?', 'opts' => [ [ 'no', 'No' ], [ 'yes', 'Yes' ], [ 'skip', 'Prefer not to say' ] ] ],
		[ 'id' => 'age', 'q' => 'What is your age range?', 'opts' => [ [ 'teen', '14 to 17' ], [ '18', '18 to 29' ], [ '30', '30 to 44' ], [ '45', '45 to 59' ], [ '60', '60 or over' ] ] ],
		[ 'id' => 'budget', 'q' => 'How simple should your home routine be?', 'opts' => [ [ 'essentials', 'Just the essentials (3 steps)' ], [ 'full', 'A complete routine' ], [ 'best', 'Whatever gets the best results' ] ] ],
		[ 'id' => 'visit', 'q' => 'Have you had a treatment at Skyn&Co. before?', 'opts' => [ [ 'new', 'No, this would be my first visit' ], [ 'back', 'Yes, I have been before' ] ] ],
	];
}

/** Free text → likely concerns and red flags (used to pre-fill the consultation). */
function skynco_skin_parse_text( $text ) {
	$t   = strtolower( (string) $text );
	$map = [
		'breakouts'  => '/acne|break ?out|pimple|zit|spots? on|blemish|cyst/',
		'congestion' => '/blackhead|clogged|congest|whitehead|bumps on (my )?(nose|forehead)/',
		'spots'      => '/dark (spot|mark)|hyperpig|pigment|uneven|sun ?spot|age spot|post.?acne mark/',
		'melasma'    => '/melasma|brown patch|mask of pregnancy/',
		'redness'    => '/red(ness)?|flush|rosacea|broken capillar/',
		'sensitive'  => '/sensitiv|sting|burn|react|irritat/',
		'dry'        => '/dry|dehydrat|flak|tight|peeling skin/',
		'oily'       => '/oily|greasy|shine|shiny|large pores|big pores/',
		'lines'      => '/wrinkle|fine line|aging|ageing|sagg|firm|crow/',
		'scars'      => '/scar|pitted|texture|rough|uneven texture/',
		'dull'       => '/dull|tired|lacklustre|lackluster|no glow|glow/',
		'bumps'      => '/ingrown|razor|shav|beard|bumps on (my )?neck/',
		'wax'        => '/wax|hair removal|unwanted hair/',
	];
	$found = [];
	foreach ( $map as $k => $re ) {
		if ( preg_match( $re, $t ) ) {
			$found[] = $k;
		}
	}
	$red = (bool) preg_match( '/bleed|mole (is )?(changing|grow)|changing mole|open sore|infect|pus|swollen|fever|sudden rash|hives|spreading/', $t );
	return [ 'concerns' => array_slice( $found, 0, 3 ), 'redflag' => $red ];
}

/* ---------------------------------------------------------------------------
 * Analysis
 * ------------------------------------------------------------------------ */
function skynco_skin_analyze( array $a ) {
	$c       = array_values( array_intersect( (array) ( $a['concerns'] ?? [] ), [ 'breakouts', 'congestion', 'spots', 'melasma', 'redness', 'sensitive', 'dry', 'oily', 'lines', 'scars', 'dull', 'bumps', 'wax' ] ) );
	$has     = fn( $k ) => in_array( $k, $c, true );
	$act     = (array) ( $a['actives'] ?? [] );
	$on      = fn( $k ) => in_array( $k, $act, true );
	$feel    = $a['feel'] ?? 'comfortable';
	$react   = $a['react'] ?? 'never';
	$preg    = 'yes' === ( $a['preg'] ?? '' );
	$teen    = 'teen' === ( $a['age'] ?? '' );
	$age     = $a['age'] ?? '30';
	$tan     = 'tan' === ( $a['sun'] ?? '' );
	$noSpf   = in_array( $a['sun'] ?? '', [ 'rarely', 'sometimes' ], true );
	$accut   = $on( 'accutane' );
	$retin   = $on( 'retinoid' );
	$deep    = 'deep' === ( $a['breakout'] ?? '' );
	$hormone = 'hormonal' === ( $a['breakout'] ?? '' );
	$budget  = $a['budget'] ?? 'full';
	$new     = 'back' !== ( $a['visit'] ?? 'new' );

	// Skin type.
	$type = [ 'oily_all' => 'oily', 'oily_t' => 'combination', 'comfortable' => 'normal', 'tight' => 'dry', 'varies' => 'combination' ][ $feel ] ?? 'normal';
	if ( 'normal' === $type && $has( 'oily' ) ) {
		$type = 'combination';
	}
	$dehydrated = $has( 'dry' ) && in_array( $type, [ 'oily', 'combination' ], true );
	$sensitive  = 'often' === $react || $has( 'sensitive' ) || $has( 'redness' );
	$very_sens  = 'often' === $react;

	// Safety flags: what must wait, and why.
	$no_resurf = $accut || $preg || $tan;           // Peels, microneedling, dermaplaning.
	$no_facewax = $accut || $retin;
	$flags      = [];
	if ( $accut ) {
		$flags[] = [ 'Accutane (isotretinoin)', 'Skin is fragile during and for 6 months after a course, so peels, microneedling, dermaplaning and facial waxing wait until then. Gentle, hydrating facials are fine.' ];
	}
	if ( $retin ) {
		$flags[] = [ 'Retinoids', 'Stop retinol, tretinoin or adapalene 5 to 7 days before peels, microneedling, dermaplaning or facial waxing, and on nights you use an exfoliant.' ];
	}
	if ( $preg ) {
		$flags[] = [ 'Pregnancy or breastfeeding', 'We skip chemical peels, microneedling and retinoids for now and keep everything gentle. Your plan already reflects this. Check any new product with your doctor.' ];
	}
	if ( $tan ) {
		$flags[] = [ 'Recent tan or sunburn', 'Let skin settle for about 2 weeks before peels, microneedling or dermaplaning, to avoid irritation and dark marks.' ];
	}
	$refer = $deep || ! empty( $a['redflag'] );

	// Waxing only.
	if ( $c === [ 'wax' ] ) {
		return [ 'wax' => true, 'no_facewax' => $no_facewax, 'flags' => $flags ];
	}
	$c = array_values( array_diff( $c, [ 'wax' ] ) );
	if ( ! $c ) {
		$c = [ 'dull' ];
	}

	// Primary concern by clinical priority.
	$order   = [ 'breakouts', 'melasma', 'scars', 'redness', 'sensitive', 'spots', 'congestion', 'lines', 'dry', 'oily', 'bumps', 'dull' ];
	$primary = 'dull';
	foreach ( $order as $k ) {
		if ( $has( $k ) ) {
			$primary = $k;
			break;
		}
	}

	// Treatment choice.
	$tx = function ( $k ) use ( $no_resurf, $sensitive, $very_sens, $teen, $age, $accut ) {
		if ( $teen && in_array( $k, [ 'breakouts', 'congestion', 'oily', 'dull' ], true ) ) {
			return [ 8, 'every 4 weeks', 'The Teen Facial is designed for young skin: a deep cleanse, gentle extractions and a calming mask, plus simple home-care coaching.' ];
		}
		switch ( $k ) {
			case 'breakouts':
				return [ 3, 'every 3 to 4 weeks for 3 visits, then monthly', 'The Acne Facial deep-cleans pores, uses blue LED and high frequency to reduce acne bacteria, and calms inflammation without stripping your skin.' ];
			case 'congestion':
				return $accut ? [ 2, 'every 4 weeks', 'A Signature Facial with gentle extractions clears congestion while your skin is fragile.' ] : [ 5, 'every 4 weeks', 'The Exfoliating Facial lifts dead skin and clears clogged pores, so blackheads loosen and products absorb better.' ];
			case 'melasma':
				return $no_resurf ? [ 4, 'every 4 weeks', 'The Brightening Facial evens tone gently with enzymes, which is the safest place to start for melasma.' ] : [ 13, 'a series of 4 to 6, every 2 to 3 weeks', 'A Chemical Peel with no visible peeling fades pigment gradually without heat or downtime, which matters with melasma.' ];
			case 'scars':
				return $no_resurf ? [ 9, 'every 4 weeks for now', 'The Glo2 Facial improves texture and glow safely now. Microneedling can follow once it is safe for your skin.' ] : [ 11, 'a series of 3, 4 to 6 weeks apart', 'Microneedling triggers new collagen to soften acne scars and rough texture over a short series.' ];
			case 'redness':
			case 'sensitive':
				return [ 2, 'every 4 to 6 weeks', 'A Signature Facial lets Hana choose calming, barrier-friendly products for your skin, with LED to soothe redness.' ];
			case 'spots':
				return $no_resurf || $very_sens ? [ 4, 'every 4 weeks', 'The Brightening Facial uses enzymes to fade dark marks and even out tone with no downtime.' ] : [ 13, 'a series of 3 to 6, every 2 to 4 weeks', 'A Chemical Peel series fades dark marks and evens tone with no visible peeling.' ];
			case 'lines':
				if ( ! $no_resurf && in_array( $age, [ '30', '45', '60' ], true ) && ! $very_sens ) {
					return [ 11, 'a series of 3, 4 to 6 weeks apart', 'Microneedling stimulates collagen to soften fine lines and improve firmness over a series.' ];
				}
				return $no_resurf ? [ 9, 'every 4 weeks', 'The Glo2 Facial plumps and hydrates with no downtime, which softens the look of fine lines.' ] : [ 12, 'every 4 weeks', 'The Derma Facial removes dead skin and fine hair so skin looks smoother and products work harder.' ];
			case 'dry':
				return $accut ? [ 2, 'every 4 weeks', 'A Signature Facial focused on hydration, with a Hydro-Jelly mask, comforts dry, fragile skin.' ] : [ 9, 'every 4 weeks', 'The Glo2 Facial floods skin with hydration through OxFoliation, ultrasound infusion and LED.' ];
			case 'oily':
				return [ 10, 'every 4 weeks', 'The Oxy Facial deep-cleans and balances oil, leaving pores looking smaller and skin fresh, not stripped.' ];
			case 'bumps':
				return [ 6, 'every 4 weeks', 'The Gentleman’s Facial deep-cleans, frees trapped hairs and calms shaving irritation.' ];
			default:
				return $no_resurf ? [ 9, 'every 4 weeks', 'The Glo2 Facial is the fastest route to a glow, with no downtime.' ] : [ 12, 'every 4 weeks', 'The Derma Facial removes dull surface skin for an instant glow and smoother makeup.' ];
		}
	};
	[ $main_id, $cadence, $why ] = $tx( $primary );
	$second                     = null;
	foreach ( $c as $k ) {
		if ( $k !== $primary ) {
			$t = $tx( $k );
			if ( $t[0] !== $main_id ) {
				$second = [ $t[0], $k ];
				break;
			}
		}
	}
	$addons = [];
	if ( $has( 'breakouts' ) || $has( 'redness' ) || $sensitive ) {
		$addons[] = 'LED Light Therapy (+$25): calms redness and helps clear breakouts';
	}
	if ( $has( 'dry' ) || $dehydrated || $accut ) {
		$addons[] = 'Hydro-Jelly Mask (+$15): cooling, deep hydration to finish';
	}

	// Home routine.
	$cleanser  = ( in_array( $type, [ 'oily', 'combination' ], true ) && ( $has( 'breakouts' ) || $has( 'congestion' ) || $has( 'oily' ) ) && ! $preg && ! $accut && ! $very_sens ) ? 'clarifying-spot-treatment' : 'gentle-cleansing-gel';
	$serum     = ( $has( 'spots' ) || $has( 'melasma' ) || $has( 'dull' ) || $has( 'lines' ) ) && ! $very_sens ? 'vitamin-c-brightening-serum' : 'hydrating-hyaluronic-serum';
	$serum2    = ( 'vitamin-c-brightening-serum' === $serum && ( $dehydrated || $has( 'dry' ) || $sensitive ) ) ? 'hydrating-hyaluronic-serum' : null;
	$moist     = ( 'oily' !== $type || $dehydrated || $accut || $sensitive || $retin ) ? 'barrier-repair-moisturizer' : null;
	$exfol     = ( $has( 'congestion' ) || $has( 'dull' ) || $has( 'spots' ) || $has( 'scars' ) || $has( 'oily' ) || $has( 'lines' ) || $has( 'bumps' ) ) && ! $accut && ! $very_sens ? 'enzyme-exfoliating-mask' : null;
	$balm      = ( $accut || $has( 'redness' ) || in_array( $main_id, [ 11, 14 ], true ) ) ? 'post-treatment-recovery-balm' : null;
	$spf       = 'daily-mineral-spf-40';

	$core  = array_values( array_filter( [ $cleanser, $serum, ( $retin || $accut ) ? $moist : null, $spf ] ) );
	$extra = array_values( array_filter( [ $moist, $exfol, $serum2, $balm ] ) );
	$picks = 'essentials' === $budget ? $core : ( 'full' === $budget ? array_merge( $core, array_slice( $extra, 0, 2 ) ) : array_merge( $core, $extra ) );
	$picks = array_values( array_unique( $picks ) );
	$in    = fn( $s ) => in_array( $s, $picks, true );

	$am = [ [ $cleanser, 'Cleanse' ] ];
	if ( $in( $serum ) ) {
		$am[] = [ $serum, 'vitamin-c-brightening-serum' === $serum ? 'Brighten and protect' : 'Hydrate and calm' ];
	}
	if ( $moist && $in( $moist ) ) {
		$am[] = [ $moist, 'Moisturise' ];
	}
	$am[] = [ $spf, 'Protect (every day, even when cloudy)' ];
	$pm   = [ [ $cleanser, 'Cleanse' ] ];
	if ( $exfol && $in( $exfol ) ) {
		$pm[] = [ $exfol, $retin ? 'Exfoliate 2 nights a week, on nights off your retinoid' : 'Exfoliate 2 to 3 nights a week' ];
	}
	if ( $serum2 && $in( $serum2 ) ) {
		$pm[] = [ $serum2, 'Hydrate' ];
	} elseif ( 'hydrating-hyaluronic-serum' === $serum && $in( $serum ) ) {
		$pm[] = [ $serum, 'Hydrate and calm' ];
	}
	if ( $retin && ! $preg ) {
		$pm[] = [ null, 'Your retinoid, as prescribed (pea-sized amount)' ];
	}
	if ( $moist && $in( $moist ) ) {
		$pm[] = [ $moist, 'Moisturise' ];
	}
	if ( $balm && $in( $balm ) ) {
		$pm[] = [ $balm, $accut ? 'Seal in moisture on dry areas and lips' : 'After treatments, or on irritated areas' ];
	}

	// What is likely going on.
	$explain = [
		'breakouts'  => $hormone ? 'Chin and jaw breakouts that follow your cycle or stress are usually hormonal: oil production spikes and pores clog. Consistent gentle exfoliation and calming the skin work better than drying it out.' : 'Breakouts form when oil and dead skin clog pores and bacteria cause inflammation. Clearing pores regularly and calming inflammation, without stripping, is what keeps them away.',
		'congestion' => 'Blackheads are pores filled with oil and dead skin that darken in the air. Regular exfoliation and professional extractions keep them clear.',
		'spots'      => 'Dark spots are extra pigment left after sun, breakouts or irritation. They fade with brightening ingredients and treatments, and only stay gone with daily SPF.',
		'melasma'    => 'Melasma is patchy pigment driven by hormones, sun and heat. It responds to gentle, consistent brightening and strict sun protection; aggressive heat or peels can make it worse.',
		'redness'    => 'Redness and flushing usually mean a reactive skin barrier or visible blood vessels. Calming, barrier-repairing care and avoiding triggers (heat, hot showers, harsh scrubs) help most.',
		'sensitive'  => 'Skin that stings or flares easily usually has a weakened barrier. The fastest fix is fewer, gentler products, steady hydration and no harsh scrubs.',
		'dry'        => $dehydrated ? 'Your skin makes oil but lacks water (dehydration), which can trigger even more oil. Lightweight hydration and a gentler cleanser rebalance it.' : 'Dry skin makes too little oil, so it loses water and feels tight. Hydrating serums, a good moisturiser and gentle cleansing restore comfort.',
		'oily'       => 'Shine and visible pores come from active oil glands. Balancing (not stripping) oil and keeping pores clear makes them look smaller.',
		'lines'      => 'Fine lines appear as collagen slows and skin loses water. Collagen-building treatments, hydration and daily SPF give the most visible results.',
		'scars'      => 'Acne scars and rough texture are changes in the skin’s collagen. They improve with treatments that rebuild collagen over a series.',
		'dull'       => 'Dullness is usually a build-up of dead skin and low hydration. Exfoliation and hydration bring back the glow quickly.',
		'bumps'      => 'Shaving bumps happen when cut hairs curl back into the skin. Exfoliating and shaving with the grain prevent them.',
	];
	$why_list = [];
	foreach ( $c as $k ) {
		if ( isset( $explain[ $k ] ) ) {
			$why_list[] = $explain[ $k ];
		}
	}

	// Avoid for now.
	$avoid = [];
	if ( $sensitive ) {
		$avoid[] = 'Scrubs, hot water and fragranced products';
	}
	if ( $has( 'breakouts' ) || $has( 'congestion' ) ) {
		$avoid[] = 'Picking or squeezing, which spreads inflammation and leaves marks';
	}
	if ( $has( 'melasma' ) || $has( 'spots' ) ) {
		$avoid[] = 'Unprotected sun and heat (saunas, hot yoga) while pigment is fading';
	}
	if ( $has( 'breakouts' ) && $on( 'bpo' ) && $on( 'acids' ) ) {
		$avoid[] = 'Using benzoyl peroxide and acids at the same time, which over-dries skin';
	}
	if ( $noSpf ) {
		$avoid[] = 'Skipping sunscreen: UV undoes most of the progress from treatments';
	}
	if ( ! $avoid ) {
		$avoid[] = 'Changing too many products at once; give your new routine 4 to 6 weeks';
	}

	$label = [ 'oily' => 'Oily', 'combination' => 'Combination', 'normal' => 'Normal', 'dry' => 'Dry' ][ $type ];
	if ( $dehydrated ) {
		$label .= ', dehydrated';
	}
	if ( $sensitive ) {
		$label .= ', sensitive';
	}

	return [
		'wax'       => false,
		'type'      => $label,
		'concerns'  => $c,
		'primary'   => $primary,
		'main'      => $main_id,
		'cadence'   => $cadence,
		'why'       => $why,
		'second'    => $second,
		'start_new' => $new,
		'addons'    => $addons,
		'am'        => $am,
		'pm'        => $pm,
		'products'  => $picks,
		'explain'   => $why_list,
		'avoid'     => $avoid,
		'flags'     => $flags,
		'refer'     => $refer,
		'teen'      => $teen,
	];
}

/* ---------------------------------------------------------------------------
 * Rendering
 * ------------------------------------------------------------------------ */
function skynco_skin_concern_label( $k ) {
	foreach ( skynco_skin_questions()[0]['opts'] as $o ) {
		if ( $o[0] === $k ) {
			return $o[1];
		}
	}
	return $k;
}

function skynco_skin_plan_html( array $p ) {
	$svc = function_exists( 'skynco_ai_services' ) ? skynco_ai_services() : [];
	$e   = 'esc_html';
	if ( ! empty( $p['wax'] ) ) {
		$h = '<div class="skp-plan"><p class="skp-plan__k">Your waxing plan</p><p>We offer quick, gentle waxing for the face and body. Pick the area to book:</p><div class="skp-plan__wax">';
		foreach ( [ 15, 16, 17, 18, 19, 20 ] as $id ) {
			if ( isset( $svc[ $id ] ) && ! ( $p['no_facewax'] && in_array( $id, [ 15, 16 ], true ) ) ) {
				$h .= '<a href="' . esc_url( skynco_ai_book_url( $id ) ) . '">' . $e( $svc[ $id ]['name'] ) . ' · $' . (int) $svc[ $id ]['price'] . '</a>';
			}
		}
		$h .= '</div>';
		foreach ( $p['flags'] as $f ) {
			$h .= '<p class="skp-plan__flag"><b>' . $e( $f[0] ) . ':</b> ' . $e( $f[1] ) . '</p>';
		}
		return $h . '</div>';
	}
	$prod = function ( $slug ) {
		$id = function_exists( 'skynco_shop_id' ) ? skynco_shop_id( $slug ) : 0;
		return $id ? wc_get_product( $id ) : null;
	};
	$main = $svc[ $p['main'] ] ?? null;

	$h  = '<div class="skp-plan">';
	$h .= '<p class="skp-plan__k">Your personalised skin plan</p>';
	$h .= '<div class="skp-plan__profile"><div><span>Skin type</span><b>' . $e( $p['type'] ) . '</b></div><div><span>Focus</span><b>' . $e( implode( ', ', array_map( 'skynco_skin_concern_label', $p['concerns'] ) ) ) . '</b></div></div>';

	if ( $p['refer'] ) {
		$h .= '<p class="skp-plan__alert"><b>Please see a dermatologist too.</b> Painful, deep or cystic breakouts (or anything bleeding, spreading or changing) need medical care, often prescription treatment. Facials can support that care, but not replace it.</p>';
	}

	$h .= '<p class="skp-plan__h">What is likely going on</p><ul class="skp-plan__ul">';
	foreach ( $p['explain'] as $x ) {
		$h .= '<li>' . $e( $x ) . '</li>';
	}
	$h .= '</ul>';

	$h .= '<p class="skp-plan__h">Your treatment plan</p><ol class="skp-plan__steps">';
	if ( $p['start_new'] && $main && 1 !== $p['main'] && isset( $svc[1] ) ) {
		$h .= '<li><b>Start with the ' . $e( $svc[1]['name'] ) . '</b> ($' . (int) $svc[1]['price'] . ', ' . (int) $svc[1]['duration'] . ' min). Hana analyses your skin in person and confirms this plan.</li>';
		$h .= '<li><b>Then the ' . $e( $main['name'] ) . '</b> ($' . (int) $main['price'] . '), ' . $e( $p['cadence'] ) . '. ' . $e( $p['why'] ) . '</li>';
	} elseif ( $main ) {
		$h .= '<li><b>' . $e( $main['name'] ) . '</b> ($' . (int) $main['price'] . ', ' . (int) $main['duration'] . ' min), ' . $e( $p['cadence'] ) . '. ' . $e( $p['why'] ) . '</li>';
	}
	if ( $p['second'] && isset( $svc[ $p['second'][0] ] ) ) {
		$s2 = $svc[ $p['second'][0] ];
		$h .= '<li><b>Later, for ' . $e( strtolower( skynco_skin_concern_label( $p['second'][1] ) ) ) . ':</b> the ' . $e( $s2['name'] ) . ' ($' . (int) $s2['price'] . ').</li>';
	}
	if ( $p['addons'] ) {
		$h .= '<li><b>Add-on:</b> ' . $e( implode( '; ', $p['addons'] ) ) . '.</li>';
	}
	$h .= '</ol>';

	$row = function ( $list ) use ( $prod, $e ) {
		$o = '<ol class="skp-plan__routine">';
		foreach ( $list as [ $slug, $step ] ) {
			$pp = $slug ? $prod( $slug ) : null;
			$o .= '<li><span>' . $e( $step ) . '</span>' . ( $pp ? '<a href="' . esc_url( get_permalink( $pp->get_id() ) ) . '">' . $e( $pp->get_name() ) . '</a>' : '' ) . '</li>';
		}
		return $o . '</ol>';
	};
	$h .= '<div class="skp-plan__cols"><div><p class="skp-plan__h">Morning</p>' . $row( $p['am'] ) . '</div><div><p class="skp-plan__h">Evening</p>' . $row( $p['pm'] ) . '</div></div>';

	// Products and one-tap routine.
	$ids   = [];
	$total = 0;
	foreach ( $p['products'] as $slug ) {
		$pp = $prod( $slug );
		if ( $pp ) {
			$ids[]  = $pp->get_id();
			$total += (float) $pp->get_price();
		}
	}
	if ( $ids ) {
		$n    = count( $ids );
		$rate = $n >= 3 ? 0.15 : ( 2 === $n ? 0.10 : 0 );
		$url  = add_query_arg( [ 'skynco_routine' => implode( '-', $ids ), '_skr' => wp_create_nonce( 'skynco_routine' ) ], wc_get_cart_url() );
		$h   .= '<div class="skp-plan__buy"><div><b>Your routine: ' . (int) $n . ' products</b><span>' . ( $rate ? '<s>$' . number_format( $total, 2 ) . '</s> $' . number_format( $total * ( 1 - $rate ), 2 ) . ' with ' . (int) ( $rate * 100 ) . '% routine savings' : '$' . number_format( $total, 2 ) ) . '</span></div><a class="skb-btn" href="' . esc_url( $url ) . '">Add my routine to bag</a></div>';
	}

	if ( $p['flags'] ) {
		$h .= '<p class="skp-plan__h">Safety notes for you</p>';
		foreach ( $p['flags'] as $f ) {
			$h .= '<p class="skp-plan__flag"><b>' . $e( $f[0] ) . ':</b> ' . $e( $f[1] ) . '</p>';
		}
	}
	$h .= '<p class="skp-plan__h">Avoid for now</p><ul class="skp-plan__ul">';
	foreach ( $p['avoid'] as $x ) {
		$h .= '<li>' . $e( $x ) . '</li>';
	}
	$h .= '</ul>';
	if ( $p['teen'] ) {
		$h .= '<p class="skp-plan__flag">Clients under 18 need a parent or guardian’s consent for treatments.</p>';
	}
	$h .= '<p class="skp-plan__fine">Guidance based on your answers, not a medical diagnosis. Hana confirms everything at your visit.</p></div>';
	return $h;
}

/** Plan plus the cards the chat shows (booking card first). */
function skynco_skin_plan_payload( array $answers ) {
	$p     = skynco_skin_analyze( $answers );
	$cards = [ [ 'type' => 'plan', 'html' => skynco_skin_plan_html( $p ) ] ];
	if ( empty( $p['wax'] ) && function_exists( 'skynco_ai_service_card' ) ) {
		$first = ( $p['start_new'] && 1 !== $p['main'] ) ? 1 : $p['main'];
		$sc    = skynco_ai_service_card( $first );
		if ( $sc ) {
			$cards[] = $sc;
		}
	}
	$svc     = function_exists( 'skynco_ai_services' ) ? skynco_ai_services() : [];
	$summary = ! empty( $p['wax'] ) ? 'Waxing plan shown.' : sprintf(
		'Plan shown. Skin type: %s. Focus: %s. Treatment: %s (%s). Routine: %s.%s%s',
		$p['type'],
		implode( ', ', $p['concerns'] ),
		$svc[ $p['main'] ]['name'] ?? '',
		$p['cadence'],
		implode( ', ', $p['products'] ),
		$p['flags'] ? ' Safety: ' . implode( '; ', wp_list_pluck( $p['flags'], 0 ) ) . '.' : '',
		$p['refer'] ? ' Advised a dermatologist.' : ''
	);
	return [ 'plan' => $p, 'cards' => $cards, 'summary' => $summary ];
}

/* Chat endpoint for the guided consultation. */
add_action( 'wp_ajax_skynco_skin_plan', 'skynco_skin_plan_ajax' );
add_action( 'wp_ajax_nopriv_skynco_skin_plan', 'skynco_skin_plan_ajax' );
function skynco_skin_plan_ajax() {
	check_ajax_referer( 'skynco_chat', 'nonce' );
	$raw = json_decode( wp_unslash( $_POST['answers'] ?? '{}' ), true );
	$a   = [];
	foreach ( (array) $raw as $k => $v ) {
		$a[ sanitize_key( $k ) ] = is_array( $v ) ? array_map( 'sanitize_key', $v ) : sanitize_key( (string) $v );
	}
	$a['redflag'] = ! empty( $raw['redflag'] );
	$out          = skynco_skin_plan_payload( $a );
	$intro        = ! empty( $out['plan']['wax'] ) ? 'Here are the waxing options.' : 'Thank you, that gives me a clear picture. Here is your personalised plan:';
	wp_send_json_success( [ 'text' => $intro, 'cards' => $out['cards'] ] );
}

/* Free text → pre-filled concerns. */
add_action( 'wp_ajax_skynco_skin_parse', 'skynco_skin_parse_ajax' );
add_action( 'wp_ajax_nopriv_skynco_skin_parse', 'skynco_skin_parse_ajax' );
function skynco_skin_parse_ajax() {
	check_ajax_referer( 'skynco_chat', 'nonce' );
	wp_send_json_success( skynco_skin_parse_text( sanitize_textarea_field( wp_unslash( $_POST['text'] ?? '' ) ) ) );
}

/* "Add my routine to bag": adds every product in the plan, then opens the bag. */
add_action(
	'wp_loaded',
	function () {
		if ( empty( $_GET['skynco_routine'] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_skr'] ?? '' ), 'skynco_routine' ) ) {
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}
		$n = 0;
		foreach ( array_slice( array_unique( array_map( 'absint', explode( '-', sanitize_text_field( wp_unslash( $_GET['skynco_routine'] ) ) ) ) ), 0, 8 ) as $id ) {
			$p = wc_get_product( $id );
			if ( $p && $p->is_purchasable() && WC()->cart->add_to_cart( $id ) ) {
				$n++;
			}
		}
		if ( $n ) {
			wc_add_notice( 'Your routine (' . $n . ' products) is in your bag. Routine savings are applied automatically.' );
		}
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}
);

add_action(
	'wp_head',
	function () {
		?>
<style id="skynco-skin-plan">
.skp-plan{margin-top:8px;padding:16px;border-radius:18px;background:#fff;border:1px solid #EADDE0;font:400 13.5px/1.55 Manrope,sans-serif;color:#2A1A24}
.skp-plan p{margin:0}
.skp-plan__k{font:700 10.5px Manrope,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#D1127E;margin-bottom:10px!important}
.skp-plan__profile{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px}
.skp-plan__profile div{padding:10px 12px;border-radius:12px;background:#FBF1F4}
.skp-plan__profile span{display:block;font:700 10px Manrope,sans-serif;letter-spacing:.1em;text-transform:uppercase;color:#9A8791}
.skp-plan__profile b{font:600 13.5px/1.35 Manrope,sans-serif;color:#3B1530}
.skp-plan__h{margin:14px 0 6px!important;font:500 16px Fraunces,serif;color:#3B1530}
.skp-plan__ul,.skp-plan__steps,.skp-plan__routine{margin:0;padding-left:18px}
.skp-plan__ul li,.skp-plan__steps li{margin:0 0 6px}
.skp-plan__routine{padding:0;list-style:none;counter-reset:r}
.skp-plan__routine li{counter-increment:r;position:relative;padding:6px 0 6px 26px;border-bottom:1px dashed #F0E2E7}
.skp-plan__routine li::before{content:counter(r);position:absolute;left:0;top:7px;width:18px;height:18px;border-radius:50%;background:#3B1530;color:#fff;font:700 10px/18px Manrope,sans-serif;text-align:center}
.skp-plan__routine span{display:block;font-size:12.5px;color:#6E5A66}
.skp-plan__routine a{font-weight:700;color:#D1127E!important;text-decoration:none!important}
.skp-plan__cols{display:grid;grid-template-columns:1fr;gap:4px}
.skp-plan__buy{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-top:14px;padding:12px;border-radius:14px;background:#FBEFF0}
.skp-plan__buy b{display:block;font-size:13.5px}.skp-plan__buy span{font-size:12.5px;color:#24704A;font-weight:700}.skp-plan__buy s{color:#9A8791;font-weight:500;margin-right:4px}
.skp-plan__flag{margin:6px 0 0!important;padding:10px 12px;border-radius:12px;background:#FFF6E8;color:#6B4A12;font-size:12.5px}
.skp-plan__alert{margin:0 0 10px!important;padding:12px;border-radius:12px;background:#FDECEC;color:#8A1F1F;font-size:13px}
.skp-plan__wax{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.skp-plan__wax a{padding:8px 12px;border-radius:999px;border:1px solid #E2CCD4;font:600 12.5px Manrope,sans-serif;color:#3B1530!important;text-decoration:none!important}
.skp-plan__fine{margin-top:12px!important;font-size:11.5px;color:#9A8791}
.skb-step{display:flex;align-items:center;gap:8px;margin:0 0 6px;font:700 10.5px Manrope,sans-serif;letter-spacing:.1em;text-transform:uppercase;color:#9A8791}
.skb-step i{flex:1;height:4px;border-radius:99px;background:#F3E3E9;overflow:hidden}.skb-step i b{display:block;height:100%;background:#D1127E;border-radius:99px}
.skb-chips button.is-sel{background:#3B1530!important;color:#fff!important;border-color:#3B1530!important}
.skb-chips button.skb-go{background:#D1127E!important;color:#fff!important;border-color:#D1127E!important}
</style>
		<?php
	}
);
