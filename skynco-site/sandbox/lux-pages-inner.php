<?php
/**
 * Skyn&Co. – inner pages (Services, About, Contact, Policies).
 * Copy, prices and policies come from skynandco.com.
 */
if ( ! defined( 'ABSPATH' ) || function_exists( 'lux_services_elements' ) ) {
	return;
}

/** Shared links and small builders for inner pages. */
function lux_inner_kit() {
	$k = [
		'book'  => home_url( '/book/' ),
		'tel'   => 'tel:+18572284708',
		'phone' => '(857) 228-4708',
		'email' => 'Info@skyandco.com',
		'addr'  => '150 Arsenal St, Suite 210, Watertown, MA 02472',
	];
	$k['img'] = function ( $name ) {
		$media = get_option( 'skynco_media_ids', [] );
		$id    = isset( $media[ $name ] ) ? (int) $media[ $name ] : 0;
		return [ 'url' => $id ? wp_get_attachment_url( $id ) : \Elementor\Utils::get_placeholder_image_src(), 'id' => $id ?: '', 'alt' => '', 'source' => 'library', 'size' => '' ];
	};
	$k['card'] = function ( $bg, array $x = [] ) {
		$s = [ 'background_background' => 'classic' ];
		lux_color( $s, 'background_color', $bg );
		return array_replace( $s, $x );
	};
	$k['border'] = function ( $color = 'line' ) {
		$s = [ 'border_border' => 'solid', 'border_width' => lux_box( 1 ) ];
		lux_color( $s, 'border_color', $color );
		return $s;
	};
	$k['grid'] = function ( $d, $t, $m, $gap ) {
		return [
			'container_type'           => 'grid',
			'grid_columns_grid'        => lux_u( $d, 'fr' ),
			'grid_columns_grid_tablet' => lux_u( $t, 'fr' ),
			'grid_columns_grid_mobile' => lux_u( $m, 'fr' ),
			'grid_rows_grid'           => lux_u( 'auto', 'custom' ),
			'grid_rows_grid_tablet'    => lux_u( 'auto', 'custom' ),
			'grid_rows_grid_mobile'    => lux_u( 'auto', 'custom' ),
			'grid_gaps'                => lux_gap( $gap ),
			'grid_auto_flow'           => 'row',
		];
	};
	$k['narrow'] = function ( $px ) {
		return [ '_element_width' => 'initial', '_element_custom_width' => lux_u( $px ), '_element_custom_width_mobile' => lux_u( 100, '%' ) ];
	};
	return $k;
}

/** Page hero: text left, optional visual right. */
function lux_page_hero( $title, $eyebrow, $h1, $text, array $buttons, $right = null ) {
	$k    = lux_inner_kit();
	$left = lux_con(
		[
			'flex_direction'   => 'column',
			'width'            => lux_u( $right ? 54 : 100, '%' ),
			'width_tablet'     => lux_u( 100, '%' ),
			'flex_align_items' => 'flex-start',
			'flex_gap'         => lux_gap( 22 ),
		],
		[
			lux_eyebrow( $eyebrow ),
			lux_heading( $h1, 'h1', [ 'f' => 'Fraunces', 's' => 56, 'st' => 46, 'sm' => 38, 'w' => '500', 'lh' => 1.1, 'ls' => -0.01 ], 'primary', $k['narrow']( 720 ) ),
			lux_text( '<p>' . $text . '</p>', 'body', 'muted', $k['narrow']( 560 ) ),
			lux_con( [ 'flex_direction' => 'row', 'flex_wrap' => 'wrap', 'flex_gap' => lux_gap( 12 ), 'margin' => lux_box( 8, 0, 0, 0 ) ], $buttons, 'Buttons' ),
		],
		'Hero text'
	);
	$kids = [ $left ];
	if ( $right ) {
		$kids[] = $right;
	}
	return lux_section(
		$title,
		[
			'background_background'     => 'gradient',
			'background_color'          => '#FFF8F6',
			'background_color_stop'     => lux_u( 20, '%' ),
			'background_color_b'        => '#F7E3E6',
			'background_color_b_stop'   => lux_u( 100, '%' ),
			'background_gradient_type'  => 'linear',
			'background_gradient_angle' => lux_u( 170, 'deg' ),
			'flex_direction'            => 'row',
			'flex_direction_tablet'     => 'column',
			'flex_justify_content'      => 'space-between',
			'flex_align_items'          => 'center',
			'flex_gap'                  => lux_gap( 56 ),
			'padding'                   => lux_box( 88, 24, 96, 24 ),
			'padding_mobile'            => lux_box( 56, 16, 64, 16 ),
		],
		$kids
	);
}

/** Full-bleed photo hero with a plum overlay (light text). */
function lux_photo_hero( $title, $eyebrow, $h1, $text, array $buttons, $image, $video = null ) {
	$k     = lux_inner_kit();
	// Photo backgrounds only. $video names a hero still (poster-{name}) taken from the studio footage.
	$media = get_option( 'skynco_media_ids', [] );
	if ( $video && ! empty( $media[ 'hero-' . $video ] ) ) {
		$image = 'hero-' . $video; // High-resolution hero photo for this page.
	}
	$vid   = '';
	$extra = [];
	return lux_section(
		$title,
		$extra + [
			'background_background'          => 'classic',
			'background_image'               => $k['img']( $image ),
			'background_position'            => 'center right',
			'background_position_mobile'     => 'center center',
			'background_size'                => 'cover',
			'background_overlay_background'  => 'gradient',
			'background_overlay_color'       => 'rgba(38,16,31,0.92)',
			'background_overlay_color_stop'  => lux_u( 0, '%' ),
			'background_overlay_color_b'     => 'rgba(59,21,48,0.45)',
			'background_overlay_color_b_stop' => lux_u( 100, '%' ),
			'background_overlay_gradient_angle' => lux_u( 90, 'deg' ),
			'background_overlay_opacity'     => lux_u( 1, '' ),
			'min_height'                     => lux_u( 520 ),
			'min_height_mobile'              => lux_u( 460 ),
			'flex_justify_content'           => 'center',
			'flex_align_items'               => 'flex-start',
			'flex_gap'                       => lux_gap( 22 ),
			'padding'                        => lux_box( 96, 24, 96, 24 ),
			'padding_mobile'                 => lux_box( 64, 16, 64, 16 ),
			'css_classes'                    => 'lux-photo-hero' . ( $vid ? ' lux-video-hero' : '' ),
		],
		[
			lux_eyebrow( $eyebrow, 'dark' ),
			lux_heading( $h1, 'h1', [ 'f' => 'Fraunces', 's' => 60, 'st' => 48, 'sm' => 40, 'w' => '500', 'lh' => 1.08, 'ls' => -0.01 ], 'cream', $k['narrow']( 680 ) ),
			lux_text( '<p>' . $text . '</p>', 'body', 'misttext', $k['narrow']( 540 ) ),
			lux_con( [ 'flex_direction' => 'row', 'flex_wrap' => 'wrap', 'flex_gap' => lux_gap( 12 ), 'margin' => lux_box( 8, 0, 0, 0 ) ], $buttons, 'Buttons' ),
		]
	);
}

/** Closing call to action, shared by all inner pages. */
function lux_page_cta( $h2, $text, $book = null, array $buttons = null ) {
	$k = lux_inner_kit();
	return lux_section(
		'CTA',
		[ 'padding' => lux_box( 0, 24, 120, 24 ), 'padding_mobile' => lux_box( 0, 16, 72, 16 ) ],
		[
			lux_con(
				[ 'flex_direction' => 'column' ] + $k['card']( 'forestdk' ) + [
					'css_classes'          => 'lux-grid-bg',
					'padding'              => lux_box( 88, 24, 88, 24 ),
					'padding_mobile'       => lux_box( 56, 20, 56, 20 ),
					'border_radius'        => lux_box( 40 ),
					'border_radius_mobile' => lux_box( 28 ),
					'flex_align_items'     => 'center',
					'flex_gap'             => lux_gap( 18 ),
				],
				[
					lux_heading( $h2, 'h2', [ 'f' => 'Fraunces', 's' => 48, 'st' => 40, 'sm' => 32, 'w' => '500', 'lh' => 1.15, 'ls' => -0.01 ], 'cream', [ 'align' => 'center' ] + $k['narrow']( 720 ) ),
					lux_text( '<p>' . $text . '</p>', 'body', 'misttext', [ 'align' => 'center' ] + $k['narrow']( 560 ) ),
					lux_con(
						[ 'flex_direction' => 'row', 'flex_wrap' => 'wrap', 'flex_justify_content' => 'center', 'flex_gap' => lux_gap( 12 ), 'margin' => lux_box( 14, 0, 0, 0 ) ],
						$buttons ? $buttons : [
							lux_button( 'Book Appointment', $book ? $book : $k['book'], 'lime' ),
							lux_button( 'Call ' . $k['phone'], $k['tel'], 'outline-light' ),
						],
						'Buttons'
					),
				],
				'CTA panel'
			),
		]
	);
}

/* =========================== SERVICES =========================== */
function lux_services_elements() {
	$k   = lux_inner_kit();
	$col = [ 'flex_direction' => 'column' ];
	$row = [ 'flex_direction' => 'row' ];
	$els = [];

	$els[] = lux_photo_hero(
		'01 Hero',
		'Services and prices',
		'Treatments for every <em>skin goal.</em>',
		'Customized facials, advanced skin treatments and waxing at our Watertown studio. Every price is listed below, and a deposit holds your appointment when you book online.',
		[
			lux_button( 'Book Appointment', $k['book'], 'lime' ),
			lux_button( 'Shop home care', home_url( '/shop/' ), 'outline-light' ),
		],
		'skynco-glo2-facial.jpg',
		'services'
	);

	/* Start here: new clients */
	$checks = [ 'view' => 'traditional', 'icon_size' => lux_u( 13 ), 'text_indent' => lux_u( 10 ), '_css_classes' => 'lux-cols-2' ];
	lux_color( $checks, 'icon_color', 'gold' );
	$checks['text_color'] = '#F6E6EE';
	lux_typo( $checks, 'icon_', [ 'f' => 'Manrope', 's' => 15, 'w' => '400', 'lh' => 1.5 ] );
	$els[] = lux_section(
		'02 Start here',
		[
			'padding'               => lux_box( 96, 24, 0, 24 ),
			'padding_mobile'        => lux_box( 56, 16, 0, 16 ),
			'flex_direction'        => 'row',
			'flex_direction_tablet' => 'column',
			'flex_align_items'      => 'stretch',
			'flex_gap'              => lux_gap( 20 ),
			'_element_id'           => 'new-clients',
		],
		[
			lux_con(
				$col + $k['card']( 'primary' ) + [ 'width' => lux_u( 62, '%' ), 'width_tablet' => lux_u( 100, '%' ), 'padding' => lux_box( 44 ), 'padding_mobile' => lux_box( 28 ), 'border_radius' => lux_box( 28 ), 'flex_gap' => lux_gap( 20 ), 'flex_align_items' => 'flex-start' ],
				[
					lux_heading( 'Start here', 'div', [ 'f' => 'Manrope', 's' => 13, 'w' => '700', 'lh' => 1.3 ], 'primary', [ '_css_classes' => 'lux-badge lux-badge--lime' ] ),
					lux_heading( 'New Client Facial &amp; Consultation', 'h2', [ 'f' => 'Fraunces', 's' => 40, 'st' => 34, 'sm' => 30, 'w' => '400', 'lh' => 1.15 ], 'cream' ),
					lux_text( '<p>The best first booking, and the right one if your last visit was more than 12 months ago. A full Signature Facial customized to your skin, plus a 15-minute consultation. For women and men.</p>', 'body', 'misttext', $k['narrow']( 560 ) ),
					lux_con(
						$row + [ 'flex_align_items' => 'flex-end', 'flex_gap' => lux_gap( 10 ) ],
						[
							lux_heading( '$155', 'div', [ 'f' => 'Fraunces', 's' => 44, 'w' => '400', 'lh' => 1 ], 'cream' ),
							lux_heading( '60 minutes', 'div', lux_t( 'small' ), 'misttext' ),
						],
						'Price'
					),
					lux_con(
						[ 'flex_direction' => 'row', 'flex_wrap' => 'wrap', 'flex_gap' => lux_gap( 12 ) ],
						[
							lux_button( 'Book this facial', $k['book'] . '?service=new-client-facial', 'lime' ),
							lux_button( 'What’s included', lux_treatment_url( 'new-client-facial' ), 'outline-light' ),
						],
						'Buttons'
					),
				],
				'Featured: New Client Facial'
			),
			lux_con(
				$col + $k['card']( 'accent' ) + [ 'width' => lux_u( 38, '%' ), 'width_tablet' => lux_u( 100, '%' ), 'padding' => lux_box( 36 ), 'padding_mobile' => lux_box( 28 ), 'border_radius' => lux_box( 28 ), 'flex_gap' => lux_gap( 16 ), 'flex_justify_content' => 'flex-end', 'flex_align_items' => 'flex-start' ],
				[
					lux_heading( 'Give the gift of glow.', 'h3', [ 'f' => 'Fraunces', 's' => 30, 'w' => '400', 'lh' => 1.2 ], 'white' ),
					lux_text( '<p>A Skyn&amp;Co. e-gift card can be used for any treatment or product. Delivered by email, ready in minutes.</p>', 'body', 'white' ),
					lux_button( 'Buy a gift card', home_url( '/product/skynco-gift-card/' ) ),
				],
				'Gift card'
			),
		]
	);

	/* Facials */
	$facials = [
		[ 'Signature Facial', '45 to 50 min', '$150', 'Fully customized to your skin type and goals. Double cleanse, skin analysis, steam with exfoliation, extractions, light therapy, a custom treatment and finishing products, plus home-care advice.' ],
		[ 'Acne Facial', '50 to 60 min', '$150', 'Detoxifies and balances breakout-prone skin with a deep cleanse, exfoliation and a purifying mask, plus Blue LED light therapy and argon high frequency to target bacteria.' ],
		[ 'Brightening Facial', '50 to 60 min', '$150', 'Powerful enzymes exfoliate and smooth the skin to even out tone and soften the look of sun and age spots. Leaves skin glowing and bright.' ],
		[ 'Exfoliating Facial', '40 to 50 min', '$150', 'A professional peel solution, safe for all skin types, lifts dead skin cells for even tone, clearer skin and a brighter complexion. No visible peeling and no downtime.' ],
		[ 'Gentleman’s Facial', '60 min', '$155', 'Made for men’s skin: deep cleanse, exfoliating scrub, extractions and a cooling hydro-jelly mask, with a beard treatment and beard high frequency. Great for shaving irritation.' ],
		[ 'Express Facial', '30 min', '$115', 'Skin rejuvenation for people on the go: a cleanse, deep exfoliation and a replenishing mask for a healthy glow. For a longer session, choose the Signature Facial.' ],
		[ 'Teen Facial, ages 14 to 17', '40 to 50 min', '$120', 'Exfoliation and deep pore cleansing to clear hormonal breakouts. A legal guardian must complete the consent form, and proof of age may be requested.' ],
	];
	$fcards = [];
	foreach ( $facials as $f ) {
		$fcards[] = lux_con(
			$col + $k['card']( 'white', $k['border']() ) + [ 'padding' => lux_box( 28 ), 'padding_mobile' => lux_box( 22 ), 'border_radius' => lux_box( 24 ), 'flex_gap' => lux_gap( 10 ), 'css_classes' => 'lux-row-link' ],
			[
				lux_con(
					$row + [ 'flex_justify_content' => 'space-between', 'flex_align_items' => 'flex-start', 'flex_gap' => lux_gap( 12 ), 'flex_wrap_mobile' => 'nowrap' ],
					[
						lux_heading( $f[0], 'h3', [ 'f' => 'Fraunces', 's' => 23, 'w' => '400', 'lh' => 1.25 ] ),
						lux_heading( $f[2], 'div', [ 'f' => 'Manrope', 's' => 15, 'w' => '700', 'lh' => 1.3 ], 'primary', [ '_css_classes' => 'lux-tag', '_flex_shrink' => 0 ] ),
					]
				),
				lux_heading( $f[1], 'div', [ 'f' => 'Manrope', 's' => 14, 'w' => '600', 'lh' => 1.3, 'ls' => 0.04 ], 'muted' ),
				lux_text( '<p>' . $f[3] . '</p>', 'small', 'muted' ),
				lux_text( '<p><a href="' . lux_tlink( $f[0] ) . '">Details and booking ↗</a></p>', [ 'f' => 'Manrope', 's' => 15, 'w' => '600', 'lh' => 1.4 ], 'primary', [ '_css_classes' => 'lux-textlink' ] ),
			],
			'Facial: ' . $f[0]
		);
	}
	$els[] = lux_section(
		'03 Facials',
		[ 'flex_gap' => lux_gap( 40 ), '_element_id' => 'facials' ],
		[
			lux_head_row(
				[
					lux_eyebrow( 'Facials' ),
					lux_heading( 'Customized facials, matched to what your skin needs today.', 'h2', 'h2' ),
				],
				null,
				720
			),
			lux_con( $k['grid']( 2, 2, 1, 16 ), $fcards, 'Facial cards' ),
		]
	);

	/* Advanced treatments */
	$adv = [
		[ 'Glo2 Facial', '60 min', '$200', 'A patented oxygenating treatment in three steps: OxFoliation with steam, LUX ultrasound serum infusion and a lymphatic drainage massage. Includes Celluma light therapy and a hydro-jelly mask. Options: Retouch, Glam (24K Gold), Detox, Hydrate, Revive, Balance and Illuminate. No downtime.' ],
		[ 'Oxy Facial', '75 min', '$175', 'A mist of concentrated oxygen with targeted serums, plus 10 to 15 minutes in the Oxygen Dome (90 to 95% pure oxygen) to calm acne-causing bacteria and support collagen.' ],
		[ 'Microneedling', '90 min', '$185', 'Tiny micro-channels prompt your skin to make new collagen and elastin, improving texture, fine lines and scarring. Expect about 3 days of redness and dryness, with full recovery in roughly 7 days.' ],
		[ 'Derma Facial (Dermaplaning)', '75 min', '$150', 'Removes dead skin cells and peach fuzz for smoother, brighter skin that absorbs products better. Good for dry, rough skin, mild acne scarring and fine lines.' ],
		[ 'Chemical Peel, no visible peeling', '45 min', '$140', 'A 30% glycolic peel with phytic acid and prickly pear extract to fade dark marks, soften fine lines and even skin tone. Makeup removal is an extra charge.' ],
		[ 'Chemical Peel, visible peeling (TCA)', '45 min', '$155', 'Strong resurfacing for acne and hyperpigmentation. Skin peels for about 7 to 10 days, and 1 to 2 treatments may be needed for full results.' ],
	];
	$arows = [];
	foreach ( $adv as $a ) {
		$arows[] = lux_con(
			$col + [ 'flex_gap' => lux_gap( 8 ), 'padding' => lux_box( 24, 0, 24, 0 ), 'border_border' => 'solid', 'border_width' => lux_box( 0, 0, 1, 0 ), 'border_color' => '#5A2E4C' ],
			[
				lux_con(
					$row + [ 'flex_justify_content' => 'space-between', 'flex_align_items' => 'baseline', 'flex_gap' => lux_gap( 12 ), 'flex_wrap_mobile' => 'nowrap' ],
					[
						lux_heading( $a[0], 'h3', [ 'f' => 'Fraunces', 's' => 24, 'sm' => 21, 'w' => '400', 'lh' => 1.25 ], 'cream' ),
						lux_heading( $a[2], 'div', [ 'f' => 'Manrope', 's' => 17, 'w' => '700', 'lh' => 1.3 ], 'gold', [ '_flex_shrink' => 0 ] ),
					]
				),
				lux_heading( $a[1], 'div', [ 'f' => 'Manrope', 's' => 14, 'w' => '600', 'lh' => 1.3, 'ls' => 0.04 ], 'gold' ),
				lux_text( '<p>' . $a[3] . '</p>', 'small', 'misttext' ),
				lux_text( '<p><a href="' . lux_tlink( $a[0] ) . '">Details and booking ↗</a></p>', [ 'f' => 'Manrope', 's' => 15, 'w' => '600', 'lh' => 1.4 ], 'cream', [ '_css_classes' => 'lux-textlink lux-textlink--light' ] ),
			],
			'Advanced: ' . $a[0]
		);
	}
	$els[] = lux_section(
		'04 Advanced treatments',
		[ 'padding' => lux_box( 0, 24, 0, 24 ), 'padding_mobile' => lux_box( 0, 16, 0, 16 ), '_element_id' => 'advanced' ],
		[
			lux_con(
				$k['card']( 'primary' ) + [
					'flex_direction'        => 'row',
					'flex_direction_tablet' => 'column',
					'flex_gap'              => lux_gap( 56 ),
					'padding'               => lux_box( 72, 56, 72, 56 ),
					'padding_mobile'        => lux_box( 44, 20, 44, 20 ),
					'border_radius'         => lux_box( 40 ),
					'border_radius_mobile'  => lux_box( 24 ),
				],
				[
					lux_con(
						$col + [ 'width' => lux_u( 38, '%' ), 'width_tablet' => lux_u( 100, '%' ), 'flex_gap' => lux_gap( 22 ), 'flex_align_items' => 'flex-start' ],
						[
							lux_eyebrow( 'Advanced treatments', 'dark' ),
							lux_heading( 'Oxygen, peels and collagen work for <em>visible change.</em>', 'h2', 'h2', 'cream' ),
							lux_text( '<p>Not sure which one fits your skin? Start with the New Client Facial and Hana will plan your next treatments with you.</p>', 'body', 'misttext' ),
							lux_img( 'Oxygen facial treatment', [ 'image' => $k['img']( 'skynco-oxygen-facial.jpg' ), '_css_classes' => 'lux-ratio-45', 'image_border_radius' => lux_box( 28 ), 'hide_mobile' => 'hidden-mobile' ] ),
						],
						'Advanced intro'
					),
					lux_con( $col + [ 'width' => lux_u( 58, '%' ), 'width_tablet' => lux_u( 100, '%' ) ], $arows, 'Advanced list' ),
				],
				'Advanced panel'
			),
		],
		1296
	);

	/* Waxing */
	$wax   = [ [ 'Upper Lip Wax', '$10' ], [ 'Chin Wax', '$15' ], [ 'Underarm Wax', '$20' ], [ 'Stomach Wax', '$30' ], [ 'Arm Wax', '$50' ], [ 'Leg Wax', '$60' ] ];
	$tiles = [];
	foreach ( $wax as $w ) {
		$tiles[] = lux_con(
			$row + $k['card']( 'white' ) + [ 'flex_justify_content' => 'space-between', 'flex_align_items' => 'center', 'flex_gap' => lux_gap( 12 ), 'flex_wrap_mobile' => 'nowrap', 'padding' => lux_box( 20, 22, 20, 22 ), 'border_radius' => lux_box( 20 ), 'html_tag' => 'a', 'link' => lux_link( lux_tlink( $w[0] ) ), 'css_classes' => 'lux-row-link' ],
			[
				lux_heading( $w[0], 'h3', [ 'f' => 'Fraunces', 's' => 20, 'w' => '400', 'lh' => 1.3 ] ),
				lux_heading( $w[1], 'div', [ 'f' => 'Manrope', 's' => 17, 'w' => '700', 'lh' => 1.3 ] ),
			],
			'Wax: ' . $w[0]
		);
	}
	$els[] = lux_section(
		'05 Waxing',
		[ 'flex_gap' => lux_gap( 40 ), '_element_id' => 'waxing' ],
		[
			lux_con(
				$k['card']( 'sagemist' ) + [
					'flex_direction'        => 'row',
					'flex_direction_tablet' => 'column',
					'flex_gap'              => lux_gap( 48 ),
					'flex_align_items'      => 'center',
					'padding'               => lux_box( 56 ),
					'padding_mobile'        => lux_box( 28, 20, 28, 20 ),
					'border_radius'         => lux_box( 32 ),
				],
				[
					lux_con(
						$col + [ 'width' => lux_u( 36, '%' ), 'width_tablet' => lux_u( 100, '%' ), 'flex_gap' => lux_gap( 16 ), 'flex_align_items' => 'flex-start' ],
						[
							lux_heading( 'Waxing', 'h2', 'h2' ),
							lux_text( '<p>Quick, clean waxing for face and body, done with the same care as every facial.</p>', 'body', 'muted' ),
							lux_button( 'Book waxing', $k['book'] ),
						],
						'Waxing intro'
					),
					lux_con( $k['grid']( 2, 2, 1, 12 ) + [ 'width' => lux_u( 64, '%' ), 'width_tablet' => lux_u( 100, '%' ) ], $tiles, 'Waxing prices' ),
				],
				'Waxing panel'
			),
		]
	);

	$els[] = lux_page_cta(
		'Make every facial <em>last longer.</em>',
		'Home care is half the result. Shop the cleansers, serums and SPF Hana uses in the studio, or save with a ready-made kit.',
		null,
		[ lux_button( 'Shop home care', home_url( '/shop/' ), 'lime' ), lux_button( 'Book Appointment', $k['book'], 'outline-light' ) ]
	);
	return $els;
}

/* =========================== ABOUT =========================== */
function lux_about_elements() {
	$k   = lux_inner_kit();
	$col = [ 'flex_direction' => 'column' ];
	$els = [];

	$els[] = lux_page_hero(
		'01 Hero',
		'About Skyn&amp;Co.',
		'Skincare that feels like a <em>ritual.</em>',
		'Skyn&amp;Co. Skincare &amp; Wellness is a skincare studio in Watertown, MA, founded by licensed esthetician Hana Rahim. We believe skincare is restoration, confidence and self-care.',
		[
			lux_button( 'Book Appointment', $k['book'] ),
			lux_button( 'View services', '/services/', 'outline' ),
		],
		lux_con(
			$col + [ 'width' => lux_u( 42, '%' ), 'width_tablet' => lux_u( 100, '%' ), 'flex_align_items' => 'center' ],
			[ lux_img( 'Skincare and self-care moments', [ 'image' => $k['img']( 'skynco-our-story.png' ), 'space' => lux_u( 500 ) ] ) ],
			'Hero image'
		)
	);

	$stat = function ( $num, $label ) {
		return lux_con(
			[ 'flex_direction' => 'column', 'flex_gap' => lux_gap( 4 ), 'padding' => lux_box( 16, 0, 0, 0 ), 'border_border' => 'solid', 'border_width' => lux_box( 1, 0, 0, 0 ), 'border_color' => '#EADDE0' ],
			[
				lux_heading( $num, 'div', [ 'f' => 'Fraunces', 's' => 44, 'w' => '500', 'lh' => 1.1 ] ),
				lux_heading( $label, 'div', lux_t( 'small' ), 'muted' ),
			],
			'Stat: ' . $label
		);
	};
	$els[] = lux_section(
		'02 Story',
		[
			'flex_direction'        => 'row',
			'flex_direction_tablet' => 'column',
			'flex_gap'              => lux_gap( 72 ),
			'flex_align_items'      => 'flex-start',
		],
		[
			lux_con(
				$col + [ 'width' => lux_u( 40, '%' ), 'width_tablet' => lux_u( 100, '%' ), 'flex_gap' => lux_gap( 28 ) ],
				[
					lux_heading( 'Founded by Hana Rahim, a Boston native and beauty entrepreneur.', 'h2', 'h2' ),
					lux_con( lux_inner_kit()['grid']( 2, 2, 2, 20 ), [ lux_counter( 10, '+', 'years in the beauty industry' ), lux_counter( 8, '+', 'years of hands-on practice' ) ], 'Stats' ),
				],
				'Story heading'
			),
			lux_con(
				$col + [ 'width' => lux_u( 54, '%' ), 'width_tablet' => lux_u( 100, '%' ), 'flex_gap' => lux_gap( 16 ) ],
				[
					lux_text(
						'<p>Hana is a licensed esthetician with over 10 years of experience in the beauty industry and more than 8 years of hands-on practice. She created Skyn&amp;Co. to provide results-driven skincare in a calm, elevated space where clients can truly relax and reconnect with themselves.</p>'
						. '<p>What began as a passion for helping others feel confident in their skin has grown into a space where science, wellness and luxury meet. Every service is designed to support healthy skin while also nurturing the body and mind.</p>'
						. '<p>We specialize in customized facials, advanced skincare treatments and wellness services that promote circulation, recovery and overall skin vitality. Our approach focuses on education, personal care and treatments tailored to each client’s skin.</p>'
						. '<p>Skyn&amp;Co. is more than a skincare studio. It is a place to slow down, reset and invest in yourself.</p>',
						'body',
						'muted'
					),
				],
				'Story text'
			),
		]
	);

	$els[] = lux_section(
		'03 Mission',
		[ 'padding' => lux_box( 0, 24, 0, 24 ), 'padding_mobile' => lux_box( 0, 16, 0, 16 ) ],
		[
			lux_con(
				$col + $k['card']( 'primary' ) + [ 'padding' => lux_box( 80, 48, 80, 48 ), 'padding_mobile' => lux_box( 48, 22, 48, 22 ), 'border_radius' => lux_box( 40 ), 'border_radius_mobile' => lux_box( 24 ), 'flex_align_items' => 'center', 'flex_gap' => lux_gap( 22 ) ],
				[
					lux_eyebrow( 'Our mission', 'dark', [ 'align' => 'center' ] ),
					lux_text( '<p>“To provide an exceptional experience for every client: a place where beauty, comfort, knowledge and personal attention come together for a memorable, rejuvenating visit.”</p>', [ 'f' => 'Fraunces', 's' => 34, 'st' => 30, 'sm' => 24, 'w' => '400', 'lh' => 1.35 ], 'cream', [ 'align' => 'center' ] + $k['narrow']( 900 ) ),
				],
				'Mission panel'
			),
		],
		1296
	);

	$principles = [
		[ 'Education first', 'You leave every visit knowing what was done, why, and how to care for your skin at home.' ],
		[ 'Personal care', 'Each treatment is customized on the day, based on your skin type, concerns and goals.' ],
		[ 'Results-driven', 'Professional products and advanced techniques, from LED and oxygen therapy to peels and microneedling.' ],
		[ 'A calm space', 'A private studio designed for you to relax, slow down and reconnect with yourself.' ],
	];
	$p_els = [];
	foreach ( $principles as $i => $p ) {
		$p_els[] = lux_con(
			[ 'flex_direction' => 'row', 'flex_gap' => lux_gap( 18 ), 'flex_wrap_mobile' => 'nowrap', 'padding' => lux_box( 22, 0, 22, 0 ), 'border_border' => 'solid', 'border_width' => lux_box( 0, 0, 1, 0 ), 'border_color' => '#EADDE0' ],
			[
				lux_heading( '0' . ( $i + 1 ), 'div', [ 'f' => 'Fraunces', 's' => 28, 'w' => '400', 'lh' => 1.1, 'fs' => 'italic' ], 'secondary', [ '_flex_shrink' => 0, '_element_width' => 'initial', '_element_custom_width' => lux_u( 48 ) ] ),
				lux_con(
					$col + [ 'flex_gap' => lux_gap( 6 ) ],
					[
						lux_heading( $p[0], 'h3', 'h3' ),
						lux_text( '<p>' . $p[1] . '</p>', 'small', 'muted' ),
					]
				),
			],
			'Principle: ' . $p[0]
		);
	}
	$els[] = lux_section(
		'04 How we work',
		[
			'flex_direction'        => 'row',
			'flex_direction_tablet' => 'column',
			'flex_gap'              => lux_gap( 64 ),
			'flex_align_items'      => 'center',
		],
		[
			lux_con(
				$col + [ 'width' => lux_u( 44, '%' ), 'width_tablet' => lux_u( 100, '%' ) ],
				[ lux_img( 'Client relaxing during a facial', [ 'image' => $k['img']( 'skynco-new-client-facial.jpg' ), '_css_classes' => 'lux-ratio-45', 'image_border_radius' => lux_box( 28 ) ] ) ],
				'Image'
			),
			lux_con(
				$col + [ 'width' => lux_u( 52, '%' ), 'width_tablet' => lux_u( 100, '%' ), 'flex_gap' => lux_gap( 8 ) ],
				array_merge( [ lux_heading( 'How we care for your skin.', 'h2', 'h2', 'primary', [ '_margin' => lux_box( 0, 0, 12, 0 ) ] ) ], $p_els ),
				'Principles'
			),
		]
	);

	$els[] = lux_page_cta( 'Come and meet <em>Hana.</em>', 'Book the New Client Facial &amp; Consultation and start with a full picture of your skin.' );
	return $els;
}

/* =========================== CONTACT =========================== */
function lux_contact_elements() {
	$k   = lux_inner_kit();
	$col = [ 'flex_direction' => 'column' ];
	$els = [];

	$els[] = lux_photo_hero(
		'01 Hero',
		'Contact',
		'Visit the <em>studio.</em>',
		'Questions about a treatment, a booking or which facial is right for you? Call, email or stop by our private studio in Watertown, MA.',
		[
			lux_button( 'Call ' . $k['phone'], $k['tel'], 'lime' ),
			lux_button( 'Book Appointment', $k['book'], 'outline-light' ),
		],
		'skynco-new-client-facial.jpg',
		'contact'
	);

	$ways = [
		[ 'fas fa-phone', 'Call', $k['phone'], $k['tel'], 'Questions about treatments or booking.' ],
		[ 'fas fa-envelope', 'Email', $k['email'], 'mailto:' . $k['email'], 'We reply as soon as we can.' ],
		[ 'fas fa-map-marker-alt', 'Visit', '150 Arsenal St, Suite 210', 'https://www.google.com/maps/search/?api=1&query=150+Arsenal+St+Suite+210+Watertown+MA+02472', 'Watertown, MA 02472' ],
	];
	$w_els = [];
	foreach ( $ways as $w ) {
		$ic = [ 'view' => 'stacked', 'shape' => 'circle', 'size' => lux_u( 18 ), 'icon_padding' => lux_u( 15 ), '_element_width' => 'auto' ];
		lux_color( $ic, 'primary_color', 'sagemist' );
		lux_color( $ic, 'secondary_color', 'primary' );
		$w_els[] = lux_con(
			$col + $k['card']( 'white', $k['border']() ) + [ 'padding' => lux_box( 28 ), 'border_radius' => lux_box( 24 ), 'flex_gap' => lux_gap( 12 ), 'flex_align_items' => 'flex-start', 'html_tag' => 'a', 'link' => lux_link( $w[3] ), 'css_classes' => 'lux-row-link' ],
			[
				lux_icon( $w[0], $ic ),
				lux_heading( $w[1], 'div', 'eye', 'muted' ),
				lux_heading( $w[2], 'h3', [ 'f' => 'Fraunces', 's' => 22, 'w' => '400', 'lh' => 1.3 ] ),
				lux_text( '<p>' . $w[4] . '</p>', 'small', 'muted' ),
			],
			'Contact: ' . $w[1]
		);
	}
	$map = '<iframe class="lux-map" title="Map to Skyn&amp;Co. at 150 Arsenal St, Watertown" src="https://www.google.com/maps?q=150+Arsenal+St+Suite+210+Watertown+MA+02472&amp;output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
	$els[] = lux_section(
		'02 Contact details',
		[ 'flex_gap' => lux_gap( 20 ) ],
		[
			lux_con( $k['grid']( 3, 3, 1, 16 ), $w_els, 'Contact cards' ),
			lux_con(
				[ 'flex_direction' => 'row', 'flex_direction_tablet' => 'column', 'flex_gap' => lux_gap( 16 ), 'flex_align_items' => 'stretch' ],
				[
					lux_w( 'html', [ 'html' => $map, '_element_width' => 'initial', '_element_custom_width' => lux_u( 64, '%' ), '_element_custom_width_tablet' => lux_u( 100, '%' ) ], 'Google map' ),
					lux_con(
						$col + $k['card']( 'primary' ) + [ 'width' => lux_u( 36, '%' ), 'width_tablet' => lux_u( 100, '%' ), 'padding' => lux_box( 32 ), 'border_radius' => lux_box( 28 ), 'flex_gap' => lux_gap( 14 ) ],
						[
							lux_heading( 'Studio hours', 'h2', [ 'f' => 'Fraunces', 's' => 28, 'w' => '400', 'lh' => 1.2 ], 'cream' ),
							lux_text( '<ul class="lux-hours"><li><span>Monday</span><b>11am to 6pm</b></li><li><span>Tuesday</span><b>Closed</b></li><li><span>Wednesday</span><b>2pm to 8pm</b></li><li><span>Thursday</span><b>2pm to 8pm</b></li><li><span>Friday</span><b>Closed</b></li><li><span>Saturday</span><b>10am to 4pm</b></li><li><span>Sunday</span><b>12pm to 7pm</b></li></ul>', 'small', 'misttext' ),
							lux_text( '<p>Hours can change. The booking page always shows live availability.</p>', [ 'f' => 'Manrope', 's' => 14, 'w' => '400', 'lh' => 21, 'lhu' => 'px' ], 'misttext' ),
						],
						'Hours'
					),
				],
				'Map and hours'
			),
		]
	);

	$els[] = lux_section(
		'03 Contact form',
		[ 'padding' => lux_box( 0, 24, 96, 24 ), 'padding_mobile' => lux_box( 0, 16, 64, 16 ), '_element_id' => 'contact-form' ],
		[
			lux_con(
				$k['card']( 'white', $k['border']() ) + [ 'flex_direction' => 'row', 'flex_direction_tablet' => 'column', 'flex_gap' => lux_gap( 56 ), 'padding' => lux_box( 48 ), 'padding_mobile' => lux_box( 28, 20, 28, 20 ), 'border_radius' => lux_box( 32 ) ],
				[
					lux_con(
						$col + [ 'width' => lux_u( 36, '%' ), 'width_tablet' => lux_u( 100, '%' ), 'flex_gap' => lux_gap( 16 ), 'flex_align_items' => 'flex-start' ],
						[
							lux_eyebrow( 'Send a message' ),
							lux_heading( 'We’d love to <em>hear from you.</em>', 'h2', 'h2' ),
							lux_text( '<p>Ask about a treatment, a booking, products or gift cards. Hana replies within one business day.</p><p>Need an answer today? Call or text <a href="' . $k['tel'] . '">' . $k['phone'] . '</a>.</p>', 'body', 'muted' ),
						]
					),
					lux_con( $col + [ 'width' => lux_u( 64, '%' ), 'width_tablet' => lux_u( 100, '%' ) ], [ lux_w( 'shortcode', [ 'shortcode' => '[skynco_contact_form]' ], 'Contact form' ) ] ),
				],
				'Form card'
			),
		]
	);

	$els[] = lux_section(
		'04 Quick answers',
		[ 'padding' => lux_box( 0, 24, 120, 24 ), 'padding_mobile' => lux_box( 0, 16, 72, 16 ) ],
		[
			lux_con(
				$k['card']( 'sagemist' ) + [ 'flex_direction' => 'row', 'flex_direction_tablet' => 'column', 'flex_justify_content' => 'space-between', 'flex_align_items' => 'center', 'flex_gap' => lux_gap( 24 ), 'padding' => lux_box( 40 ), 'padding_mobile' => lux_box( 28, 22, 28, 22 ), 'border_radius' => lux_box( 28 ) ],
				[
					lux_con(
						[ 'flex_direction' => 'column', 'flex_gap' => lux_gap( 8 ), 'width' => lux_u( 60, '%' ), 'width_tablet' => lux_u( 100, '%' ) ],
						[
							lux_heading( 'Looking for a quick answer?', 'h2', [ 'f' => 'Fraunces', 's' => 30, 'w' => '400', 'lh' => 1.2 ] ),
							lux_text( '<p>Deposits, downtime, what to bring and how cancellations work are all covered in our FAQ and policies.</p>', 'body', 'muted' ),
						]
					),
					lux_con(
						[ 'flex_direction' => 'row', 'flex_wrap' => 'wrap', 'flex_gap' => lux_gap( 12 ) ],
						[ lux_button( 'Read the FAQ', home_url( '/faq/' ) ), lux_button( 'Studio policies', home_url( '/policies/' ), 'outline' ) ],
						'Buttons'
					),
				],
				'FAQ band'
			),
		]
	);
	return $els;
}

/* =========================== POLICIES =========================== */
function lux_terms_elements() {
	$k   = lux_inner_kit();
	$col = [ 'flex_direction' => 'column' ];
	$els = [];

	$els[] = lux_page_hero(
		'01 Hero',
		'Please read before booking',
		'Studio <em>policies.</em>',
		'To make sure every client gets a relaxing, personal experience, please review these policies before your appointment. Product returns and shipping are covered at the end.',
		[ lux_button( 'Book Appointment', $k['book'] ), lux_button( 'Read the FAQ', home_url( '/faq/' ), 'outline' ) ]
	);

	$rules = [
		[ 'Deposits', 'A deposit is required to secure all appointments. The amount depends on the service. Deposits are non-refundable, but can move to a new time if you reschedule at least 24 hours before your appointment.' ],
		[ 'Arrival time', 'Please arrive 5 to 10 minutes before your appointment so you have time to settle in.' ],
		[ 'Late arrivals', 'Arriving more than 10 minutes late incurs a $25 late fee. After 15 minutes, the appointment may be cancelled to respect other clients’ time.' ],
		[ 'Cancellations', 'Please give 24 hours’ notice to cancel or reschedule. Cancellations within 2 hours of the appointment are last-minute cancellations and incur a $50 rebooking fee.' ],
		[ 'No call, no show', 'A $50 fee is required before booking any future appointment.' ],
		[ 'Skin preparation', 'For the best results, arrive with a clean, makeup-free face. A $5 makeup removal fee may apply.' ],
		[ 'Allergies and medical concerns', 'Tell your esthetician about any allergies, sensitivities or medical conditions before your service, so your treatment can be safely customized.' ],
		[ 'Payment methods', 'We accept cash, Venmo, debit and credit cards in the studio, and cards online.' ],
		[ 'Shipping', 'Product orders ship within 2 business days. Shipping is free on orders over $75, or choose free pickup at the studio.' ],
		[ 'Product returns', 'Unopened products can be returned within 30 days for a full refund. For hygiene reasons, opened products cannot be returned unless they caused a reaction. Contact us first.' ],
		[ 'Gift cards', 'E-gift cards are delivered by email, never expire and can be used for any treatment or product. They cannot be exchanged for cash.' ],
	];
	$r_els = [];
	foreach ( $rules as $i => $r ) {
		$r_els[] = lux_con(
			$col + $k['card']( 'white', $k['border']() ) + [ 'padding' => lux_box( 28 ), 'padding_mobile' => lux_box( 22 ), 'border_radius' => lux_box( 24 ), 'flex_gap' => lux_gap( 10 ) ],
			[
				lux_heading( sprintf( '%02d', $i + 1 ), 'div', [ 'f' => 'Fraunces', 's' => 22, 'w' => '400', 'lh' => 1.1, 'fs' => 'italic' ], 'secondary' ),
				lux_heading( $r[0], 'h2', [ 'f' => 'Fraunces', 's' => 24, 'w' => '400', 'lh' => 1.25 ] ),
				lux_text( '<p>' . $r[1] . '</p>', 'small', 'muted' ),
			],
			'Policy: ' . $r[0]
		);
	}
	$els[] = lux_section( '02 Policies', [], [ lux_con( $k['grid']( 2, 2, 1, 16 ), $r_els, 'Policy cards' ) ] );
	$els[] = lux_page_cta( 'All set? <em>Book your visit.</em>', 'Questions about a policy? Call ' . $k['phone'] . ' before you book.' );
	return $els;
}

/** Real client reviews (from skynandco.com and The Beauty Loc), shown as a slow marquee. */
function lux_reviews_data() {
	return [
		[ 'Candyce B.', 'First facial', 'Hands down the best facial I’ve experienced. From the moment I walked in, I felt welcomed and relaxed. Hana is very knowledgeable and gives great advice during and after your facial.' ],
		[ 'Laury C.', 'Facial client', 'I loved getting a facial with Hana. She’s very professional, polite and a great listener. I’ve learned so much about my skin in just one hour with her. I will always be back. 10/10 recommend.' ],
		[ 'Ruby J.', 'Regular client', 'I always look forward to getting facials done by Hana. Super informative, detail oriented and thorough! She has been careful with product choices during my pregnancy and while breastfeeding.' ],
		[ 'Tiffany', 'Regular client', 'I can’t even put into words how great Hana is. She knows exactly what she is doing and is very knowledgeable. My skin is always left glowing, and I always leave feeling like a weight is lifted off my chest.' ],
		[ 'Samuel H.', 'Parent of a client', 'My daughter is a client of hers and is very happy anytime she goes to see her. Professional, and always has her skin glowing.' ],
	];
}

function lux_reviews_section( $title = 'Reviews' ) {
	$cards = '';
	foreach ( lux_reviews_data() as $r ) {
		$pid    = (int) ( get_option( 'skynco_media_ids', [] )[ 'review-' . sanitize_title( strtok( $r[0], ' ' ) ) ] ?? 0 );
		$avatar = $pid ? '<img class="lux-review__photo" src="' . esc_url( wp_get_attachment_image_url( $pid, 'thumbnail' ) ) . '" alt="" width="48" height="48" style="width:48px;height:48px;border-radius:50%;object-fit:cover" loading="lazy">' : '<span class="lux-review__avatar" aria-hidden="true">' . mb_substr( $r[0], 0, 1 ) . '</span>';
		$cards .= '<figure class="lux-review"><div class="lux-review__stars" aria-label="5 out of 5 stars">★★★★★</div><blockquote>“' . $r[2] . '”</blockquote><figcaption>' . $avatar . '<span><strong>' . $r[0] . '</strong><em>' . $r[1] . '</em></span></figcaption></figure>';
	}
	$html = '<div class="lux-reviews" role="region" aria-label="Client reviews"><div class="lux-reviews__track"><div class="lux-reviews__set">' . $cards . '</div><div class="lux-reviews__set" aria-hidden="true">' . $cards . '</div></div></div>';

	$score = lux_con(
		[ 'flex_direction' => 'row', 'flex_align_items' => 'center', 'flex_gap' => lux_gap( 16 ), 'flex_wrap_mobile' => 'nowrap', 'css_classes' => 'lux-fit' ],
		[
			lux_heading( '5.0', 'div', [ 'f' => 'Fraunces', 's' => 64, 'sm' => 52, 'w' => '500', 'lh' => 1 ] ),
			lux_con(
				[ 'flex_direction' => 'column', 'flex_gap' => lux_gap( 4 ), 'css_classes' => 'lux-fit' ],
				[
					lux_heading( '★★★★★', 'div', [ 'f' => 'Manrope', 's' => 20, 'w' => '400', 'lh' => 1.1, 'ls' => 0.12 ], 'accent' ),
					lux_heading( 'Average from 90 client reviews', 'div', lux_t( 'small' ), 'muted' ),
				]
			),
		],
		'Rating'
	);

	return lux_section(
		'09 ' . $title,
		[
			'background_background' => 'classic',
			'background_color'      => '#FFFFFF',
			'border_border'         => 'solid',
			'border_width'          => lux_box( 1, 0, 1, 0 ),
			'border_color'          => '#EADDE0',
			'flex_gap'              => lux_gap( 48 ),
			'_element_id'           => 'reviews',
		],
		[
			lux_head_row(
				[
					lux_eyebrow( 'Client reviews' ),
					lux_heading( 'Glowing skin, and clients who <em>keep coming back.</em>', 'h2', 'h2' ),
				],
				$score,
				680
			),
			lux_w( 'html', [ 'html' => $html, '_css_classes' => 'lux-reviews-wrap' ], 'Reviews marquee (real reviews)' ),
		]
	);
}
