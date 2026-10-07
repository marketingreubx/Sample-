<?php
/**
 * Skyn&Co. – one Elementor page per treatment (children of /services/).
 * Copy is drawn from the skynandco.com service menu.
 */
if ( ! defined( 'ABSPATH' ) || function_exists( 'lux_treatment_details' ) ) {
	return;
}

/** Per-treatment detail copy: [included[], best_for[], good_to_know]. */
function lux_treatment_details() {
	return [
		'new-client-facial'  => [ [ 'Full Signature Facial customized to your skin', '15-minute skin consultation', 'Completed skin analysis', 'Detailed home-care regimen and product recommendations', 'Your next best treatments, planned with you' ], [ 'First-time clients', 'Returning after 12 months or more', 'Anyone unsure what to book' ], 'For both women and men. Arrive with a clean face, and bring a list of the products you use now.' ],
		'signature-facial'   => [ [ 'Facial cleanse and second cleanse', 'Skin analysis', 'Steam with exfoliation', 'Extractions', 'Light therapy', 'Custom skin treatment and finishing products' ], [ 'Regular skin maintenance', 'Dull or congested skin', 'A personalized glow-up' ], 'Each session is customized on the day to your skin type, concerns and goals.' ],
		'acne-facial'        => [ [ 'Deep cleanse and exfoliation', 'Customized purifying mask', 'Blue LED light therapy', 'Argon high frequency to target bacteria' ], [ 'Breakouts and clogged pores', 'Oily or sensitive, acne-prone skin' ], 'Designed to detoxify and balance the skin and support its natural healing process.' ],
		'brightening-facial' => [ [ 'Enzyme exfoliation', 'Smoothing and brightening treatment', 'Hydrating finish' ], [ 'Uneven tone', 'Sun and age spots', 'Dull, tired-looking skin' ], 'Leaves skin glowing and bright with no downtime.' ],
		'exfoliating-facial' => [ [ 'Professionally applied peel solution', 'Exfoliation of dead skin cells', 'Soothing finish' ], [ 'Uneven tone and texture', 'Acne and dullness', 'Early signs of aging' ], 'Skin does not physically peel and there is no downtime. Monthly treatments give the best results.' ],
		'gentlemans-facial'  => [ [ 'Deep cleanse', 'Exfoliating scrub and extractions', 'Cooling hydro-jelly mask', 'Beard treatment and beard high frequency' ], [ 'Men’s skin concerns', 'Shaving irritation and ingrown hairs', 'Congested pores' ], 'A full 60-minute facial built around men’s skin and grooming.' ],
		'express-facial'     => [ [ 'Cleanse', 'Deep exfoliation', 'Replenishing mask' ], [ 'Busy schedules', 'A quick glow before an event' ], 'For a longer, fully customized session, choose the Signature Facial.' ],
		'teen-facial'        => [ [ 'Exfoliation', 'Deep pore cleansing', 'Clarifying treatment for blemishes' ], [ 'Teens aged 14 to 17', 'Hormonal breakouts' ], 'A legal guardian must complete the consent form, and proof of age may be requested.' ],
		'glo2-facial'        => [ [ 'OxFoliation with steam', 'LUX ultrasound serum infusion', 'Lymphatic drainage massage', 'Celluma light therapy and hydro-jelly mask' ], [ 'All skin types and seasons', 'Instant, long-lasting glow', 'Choose Retouch, Glam (24K Gold), Detox, Hydrate, Revive, Balance or Illuminate' ], 'Patented oxygenating technology with no downtime.' ],
		'oxy-facial'         => [ [ 'Concentrated oxygen mist with targeted serums', '10 to 15 minutes in the Oxygen Dome (90 to 95% pure oxygen)', 'Anion therapy' ], [ 'Acne-prone skin', 'Tired, dehydrated skin', 'Supporting collagen production' ], 'Nourishes the skin for a more youthful, radiant complexion.' ],
		'microneedling'      => [ [ 'Skin preparation', 'Microneedling treatment', 'Soothing post-treatment care' ], [ 'Fine lines and wrinkles', 'Texture and acne scarring', 'Loss of firmness' ], 'Skin feels sensitive, red and dry for about 3 days, with full recovery in roughly 7 days. Best results come from a series of treatments.' ],
		'derma-facial'       => [ [ 'Dermaplaning exfoliation', 'Removal of fine vellus hair (peach fuzz)', 'Nutrient infusion' ], [ 'Rough, dry skin', 'Superficial hyperpigmentation', 'Mild acne scarring and fine lines' ], 'A simple, safe exfoliation that leaves skin smooth and glowing.' ],
		'chemical-peel'      => [ [ '30% glycolic acid with phytic acid', 'Prickly pear flower extract', 'Soothing finish' ], [ 'Dark marks and hyperpigmentation', 'Fine lines', 'Uneven skin tone' ], 'No physical skin peeling. Makeup removal is an extra charge, so please arrive makeup-free.' ],
		'tca-peel'           => [ [ 'Potent TCA peel', 'Strong exfoliation and resurfacing', 'Aftercare guidance' ], [ 'Acne', 'Hyperpigmentation', 'All skin types' ], 'Skin will peel for about 7 to 10 days. You may need 1 to 2 treatments for full results.' ],
		'upper-lip-wax'      => [ [ 'Upper lip waxing' ], [ 'Smooth, quick touch-ups' ], 'Please let us know about any retinoid or exfoliating products you use.' ],
		'chin-wax'           => [ [ 'Chin waxing' ], [ 'Smooth, quick touch-ups' ], 'Please let us know about any retinoid or exfoliating products you use.' ],
		'underarm-wax'       => [ [ 'Underarm waxing' ], [ 'Longer-lasting smoothness than shaving' ], 'Skip deodorant on the day for the most comfortable wax.' ],
		'stomach-wax'        => [ [ 'Stomach waxing' ], [ 'Smooth skin with slower regrowth' ], 'Hair should be about a quarter inch long for the best result.' ],
		'arm-wax'            => [ [ 'Full arm waxing' ], [ 'Smooth arms for weeks' ], 'Hair should be about a quarter inch long for the best result.' ],
		'leg-wax'            => [ [ 'Full leg waxing' ], [ 'Smooth legs for weeks' ], 'Hair should be about a quarter inch long for the best result.' ],
	];
}

function lux_treatment_url( $slug ) {
	return home_url( '/services/' . $slug . '/' );
}

function lux_treatment_elements( array $t ) {
	$k       = lux_inner_kit();
	$col     = [ 'flex_direction' => 'column' ];
	$row     = [ 'flex_direction' => 'row' ];
	$details = lux_treatment_details()[ $t[0] ] ?? [ [], [], '' ];
	$book    = home_url( '/book/?service=' . $t[0] );
	$els     = [];

	$card_kids = [];
	if ( $t[7] ) {
		$card_kids[] = lux_img( $t[1], [ 'image' => $k['img']( $t[7] ), '_css_classes' => 'lux-ratio-43', 'image_border_radius' => lux_box( 20 ) ] );
	}
	$card_kids[] = lux_con(
		$row + [ 'flex_justify_content' => 'space-between', 'flex_align_items' => 'flex-end', 'flex_gap' => lux_gap( 12 ), 'flex_wrap_mobile' => 'nowrap' ],
		[
			lux_heading( '$' . $t[4], 'div', [ 'f' => 'Fraunces', 's' => 44, 'w' => '500', 'lh' => 1 ] ),
			lux_heading( $t[3] . ' minutes', 'div', [ 'f' => 'Manrope', 's' => 15, 'w' => '600', 'lh' => 1.3 ], 'muted' ),
		],
		'Price'
	);
	$card_kids[] = lux_text( '<p>' . ( $t[5] > 0 ? 'A $' . $t[5] . ' deposit holds your appointment. The balance is paid at the studio.' : 'Pay at the studio after your appointment.' ) . '</p>', 'small', 'muted' );
	$card_kids[] = lux_button( 'Book this treatment', $book, 'lime', [ 'align' => 'justify' ] );
	$card_kids[] = lux_button( 'Call ' . $k['phone'], $k['tel'], 'outline', [ 'align' => 'justify' ] );

	$els[] = lux_section(
		'01 Hero',
		[
			'background_background'     => 'gradient',
			'background_color'          => '#FFF8F6',
			'background_color_stop'     => lux_u( 30, '%' ),
			'background_color_b'        => '#F7E3E6',
			'background_color_b_stop'   => lux_u( 100, '%' ),
			'background_gradient_type'  => 'linear',
			'background_gradient_angle' => lux_u( 170, 'deg' ),
			'flex_direction'            => 'row',
			'flex_direction_tablet'     => 'column',
			'flex_justify_content'      => 'space-between',
			'flex_align_items'          => 'center',
			'flex_gap'                  => lux_gap( 56 ),
			'padding'                   => lux_box( 80, 24, 88, 24 ),
			'padding_mobile'            => lux_box( 48, 16, 56, 16 ),
		],
		[
			lux_con(
				$col + [ 'width' => lux_u( 54, '%' ), 'width_tablet' => lux_u( 100, '%' ), 'flex_gap' => lux_gap( 20 ), 'flex_align_items' => 'flex-start' ],
				[
					lux_text( '<p><a href="' . home_url( '/services/' ) . '">Services</a> &nbsp;/&nbsp; ' . esc_html( $t[2] ) . '</p>', [ 'f' => 'Manrope', 's' => 14, 'w' => '600', 'lh' => 1.4 ], 'muted', [ '_css_classes' => 'lux-crumbs' ] ),
					lux_heading( esc_html( $t[1] ), 'h1', [ 'f' => 'Fraunces', 's' => 56, 'st' => 46, 'sm' => 38, 'w' => '500', 'lh' => 1.08, 'ls' => -0.01 ] ),
					lux_text( '<p>' . esc_html( $t[6] ) . '</p>', [ 'f' => 'Manrope', 's' => 18, 'w' => '400', 'lh' => 30, 'lhu' => 'px' ], 'muted', $k['narrow']( 560 ) ),
					lux_w( 'html', [ 'html' => '<div class="lux-chips"><span>' . (int) $t[3] . ' min</span><span>$' . (int) $t[4] . '</span>' . ( $t[5] > 0 ? '<span>$' . (int) $t[5] . ' deposit</span>' : '<span>Pay in studio</span>' ) . '<span>With Hana</span></div>' ], 'Chips' ),
				],
				'Hero text'
			),
			lux_con(
				$col + $k['card']( 'white', $k['border']() ) + [ 'width' => lux_u( 40, '%' ), 'width_tablet' => lux_u( 100, '%' ), 'padding' => lux_box( 22 ), 'border_radius' => lux_box( 28 ), 'flex_gap' => lux_gap( 14 ), 'css_classes' => 'lux-book-card' ],
				$card_kids,
				'Booking card'
			),
		]
	);

	$list = function ( $items, $title, $dark = false ) use ( $k, $col ) {
		$s = [ 'view' => 'traditional', 'icon_size' => lux_u( 13 ), 'text_indent' => lux_u( 12 ), 'space_between' => lux_u( 10 ) ];
		lux_color( $s, 'icon_color', $dark ? 'gold' : 'accent' );
		$s['text_color'] = $dark ? '#F6E6EE' : '#2A1A24';
		lux_typo( $s, 'icon_', [ 'f' => 'Manrope', 's' => 16, 'w' => '500', 'lh' => 1.5 ] );
		return lux_con(
			$col + $k['card']( $dark ? 'primary' : 'white', $dark ? [] : $k['border']() ) + [ 'padding' => lux_box( 32 ), 'padding_mobile' => lux_box( 24 ), 'border_radius' => lux_box( 28 ), 'flex_gap' => lux_gap( 18 ) ],
			[
				lux_heading( $title, 'h2', [ 'f' => 'Fraunces', 's' => 26, 'w' => '400', 'lh' => 1.2 ], $dark ? 'cream' : 'primary' ),
				lux_icon_list( $items, 'fas fa-check', $s ),
			],
			$title
		);
	};
	$cards = [];
	if ( $details[0] ) {
		$cards[] = $list( $details[0], 'What’s included', true );
	}
	if ( $details[1] ) {
		$cards[] = $list( $details[1], 'Best for' );
	}
	$cards[] = lux_con(
		$col + $k['card']( 'sagemist' ) + [ 'padding' => lux_box( 32 ), 'padding_mobile' => lux_box( 24 ), 'border_radius' => lux_box( 28 ), 'flex_gap' => lux_gap( 14 ) ],
		[
			lux_heading( 'Good to know', 'h2', [ 'f' => 'Fraunces', 's' => 26, 'w' => '400', 'lh' => 1.2 ] ),
			lux_text( '<p>' . esc_html( $details[2] ) . '</p><p><a href="' . home_url( '/policies/' ) . '">Studio policies</a> &nbsp;·&nbsp; <a href="' . home_url( '/faq/' ) . '">FAQ</a></p>', 'small', 'text' ),
		],
		'Good to know'
	);
	$els[] = lux_section( '02 Details', [ 'flex_gap' => lux_gap( 16 ) ], [ lux_con( $k['grid']( 3, 1, 1, 16 ), $cards, 'Detail cards' ) ] );

	$els[] = lux_shop_strip( 'Pair it with the right <em>home care.</em>', 'Hana’s picks to protect and extend your ' . esc_html( $t[1] ) . ' results.', lux_shop_recs_for( $t[0] ), 'tight' );

	// Related treatments from the same category.
	$rel = [];
	foreach ( lux_lp_treatments() as $o ) {
		if ( $o[0] !== $t[0] && $o[2] === $t[2] ) {
			$rel[] = $o;
		}
	}
	if ( count( $rel ) < 3 ) {
		foreach ( lux_lp_treatments() as $o ) {
			if ( $o[0] !== $t[0] && ! in_array( $o, $rel, true ) && 'Waxing' !== $o[2] ) {
				$rel[] = $o;
			}
		}
	}
	$rel   = array_slice( $rel, 0, 3 );
	$rcard = [];
	foreach ( $rel as $o ) {
		$rcard[] = lux_con(
			$col + $k['card']( 'white', $k['border']() ) + [ 'padding' => lux_box( 26 ), 'border_radius' => lux_box( 24 ), 'flex_gap' => lux_gap( 8 ), 'html_tag' => 'a', 'link' => lux_link( lux_treatment_url( $o[0] ) ), 'css_classes' => 'lux-row-link' ],
			[
				lux_heading( esc_html( $o[1] ), 'h3', [ 'f' => 'Fraunces', 's' => 22, 'w' => '400', 'lh' => 1.25 ] ),
				lux_heading( $o[3] . ' min · $' . $o[4], 'div', [ 'f' => 'Manrope', 's' => 15, 'w' => '700', 'lh' => 1.3 ], 'accent' ),
				lux_text( '<p>' . esc_html( $o[6] ) . '</p>', 'small', 'muted' ),
			],
			'Related: ' . $o[1]
		);
	}
	$els[] = lux_section(
		'04 Related',
		[ 'flex_gap' => lux_gap( 32 ) ],
		[
			lux_head_row( [ lux_heading( 'You might also like', 'h2', 'h2' ) ], lux_button( 'All services', home_url( '/services/' ), 'outline' ), 640 ),
			lux_con( $k['grid']( 3, 3, 1, 16 ), $rcard, 'Related cards' ),
		]
	);

	$els[] = lux_page_cta( 'Ready for your <em>' . esc_html( $t[1] ) . '?</em>', 'Pick a time that suits you. ' . ( $t[5] > 0 ? 'A $' . $t[5] . ' deposit holds your spot.' : 'Pay at the studio.' ), $book );
	return $els;
}

/** Create or update every treatment page as a child of /services/. */
function lux_build_treatment_pages() {
	$parent = get_page_by_path( 'services' );
	$ids    = [];
	foreach ( lux_lp_treatments() as $t ) {
		$existing = get_page_by_path( 'services/' . $t[0] );
		if ( $existing ) {
			$id = $existing->ID;
		} else {
			$id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $t[1], 'post_name' => $t[0], 'post_parent' => $parent ? $parent->ID : 0 ] );
		}
		wp_update_post( [ 'ID' => $id, 'post_title' => $t[1], 'post_status' => 'publish', 'post_excerpt' => $t[6] ] );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
		update_post_meta( $id, 'site-sidebar-layout', 'no-sidebar' );
		update_post_meta( $id, 'site-content-layout', 'page-builder' );
		update_post_meta( $id, 'ast-site-content-layout', 'full-width-container' );
		update_post_meta( $id, 'site-post-title', 'disabled' );
		$doc = \Elementor\Plugin::$instance->documents->get( $id, false );
		$doc->save( [ 'elements' => lux_animate( lux_treatment_elements( $t ) ), 'settings' => [ 'hide_title' => 'yes', 'template' => 'elementor_header_footer' ] ] );
		$ids[ $t[0] ] = $id;
	}
	update_option( 'skynco_treatment_page_ids', $ids, false );
	return $ids;
}

/** Map a menu label ("Chemical Peel, visible peeling (TCA)") to its treatment slug, or '' if none. */
function lux_tslug( $name ) {
	$n = strtolower( html_entity_decode( $name ) );
	$rules = [ 'tca' => 'tca-peel', 'peel' => 'chemical-peel', 'derma' => 'derma-facial', 'teen' => 'teen-facial', 'new client' => 'new-client-facial', 'gentleman' => 'gentlemans-facial', 'virtual' => '' ];
	foreach ( $rules as $needle => $slug ) {
		if ( false !== strpos( $n, $needle ) ) {
			return $slug;
		}
	}
	$slug  = sanitize_title( trim( preg_replace( '/\(.*\)/', '', $n ) ) );
	$slugs = array_column( lux_lp_treatments(), 0 );
	return in_array( $slug, $slugs, true ) ? $slug : '';
}

function lux_tlink( $name ) {
	$slug = lux_tslug( $name );
	return $slug ? lux_treatment_url( $slug ) : home_url( '/services/' );
}