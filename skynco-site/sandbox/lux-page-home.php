<?php
/**
 * Skyn&Co. – Home page element tree (Elementor containers + free widgets).
 * Copy and prices come from skynandco.com; booking links go to the on-site LatePoint booking page (/book/).
 */
if ( ! defined( 'ABSPATH' ) || function_exists( 'lux_home_elements' ) ) {
	return;
}

function lux_home_elements() {
	$book  = home_url( '/book/' );
	$menu  = '/services/';
	$svcs  = home_url( '/services/' );
	$terms = '/policies/';
	$tel   = 'tel:+18572284708';

	$media = get_option( 'skynco_media_ids', [] );
	$img   = function ( $name ) use ( $media ) {
		$id = isset( $media[ $name ] ) ? (int) $media[ $name ] : 0;
		return [ 'url' => $id ? wp_get_attachment_url( $id ) : \Elementor\Utils::get_placeholder_image_src(), 'id' => $id ?: '', 'alt' => '', 'source' => 'library', 'size' => '' ];
	};

	$col  = [ 'flex_direction' => 'column' ];
	$row  = [ 'flex_direction' => 'row' ];
	$card = function ( $bg, array $x = [] ) {
		$s = [ 'background_background' => 'classic' ];
		lux_color( $s, 'background_color', $bg );
		return array_replace( $s, $x );
	};
	$border = function ( $color = 'line', $w = 1 ) {
		$s = [ 'border_border' => 'solid', 'border_width' => lux_box( $w ) ];
		lux_color( $s, 'border_color', $color );
		return $s;
	};
	$shadow = function ( $y = 18, $blur = 40, $a = 0.14 ) {
		return [
			'box_shadow_box_shadow_type' => 'yes',
			'box_shadow_box_shadow'      => [ 'horizontal' => 0, 'vertical' => $y, 'blur' => $blur, 'spread' => 0, 'color' => "rgba(59,21,48,$a)" ],
		];
	};
	$abs = function ( $h, $x, $v, $y, $xu = 'px', $yu = '%' ) {
		$s = [ 'position' => 'absolute', 'z_index' => 3, '_offset_orientation_h' => $h, '_offset_orientation_v' => $v ];
		$s[ 'start' === $h ? '_offset_x' : '_offset_x_end' ] = lux_u( $x, $xu );
		$s[ 'start' === $h ? '_offset_x_mobile' : '_offset_x_end_mobile' ] = lux_u( 0, 'px' );
		if ( 'end' === $v ) {
			$s['_offset_y_end'] = lux_u( $y, $yu );
		} else {
			$s['_offset_y'] = lux_u( $y, $yu );
		}
		return $s;
	};
	$grid = function ( $d, $t, $m, $gap ) {
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
	$narrow = function ( $px ) {
		return [ '_element_width' => 'initial', '_element_custom_width' => lux_u( $px ), '_element_custom_width_mobile' => lux_u( 100, '%' ) ];
	};

	$els = [];

	/* ---------------- 01 HERO ---------------- */
	$hero_left = lux_con(
		$col + [
			'width'            => lux_u( 56, '%' ),
			'width_tablet'     => lux_u( 100, '%' ),
			'flex_align_items' => 'flex-start',
			'flex_gap'         => lux_gap( 24 ),
		],
		[
			lux_heading( 'Your road to beautiful skin <em>starts here.</em>', 'h1', [ 'f' => 'Fraunces', 's' => 68, 'st' => 54, 'sm' => 42, 'w' => '500', 'lh' => 1.06, 'ls' => -0.015 ], 'cream' ),
			lux_text(
				'<p>Customized facials, advanced skin treatments and waxing, led by licensed esthetician Hana Rahim. Every treatment is matched to your skin, and you leave with a home routine you can keep.</p>',
				[ 'f' => 'Manrope', 's' => 18, 'sm' => 17, 'w' => '400', 'lh' => 30, 'lhu' => 'px' ],
				'misttext',
				$narrow( 520 )
			),
			lux_con(
				$row + [ 'flex_wrap' => 'wrap', 'flex_gap' => lux_gap( 12 ), 'margin' => lux_box( 12, 0, 0, 0 ) ],
				[
					lux_button( 'Book Appointment', $svcs, 'lime', [ 'align_mobile' => 'justify', '_element_width_mobile' => 'inherit' ] ),
					lux_button( 'View Treatments ↓', '#treatments', 'outline-light', [ 'align_mobile' => 'justify', '_element_width_mobile' => 'inherit' ] ),
				],
				'Buttons'
			),
			lux_w( 'html', [ 'html' => '<a class="lux-rating" href="#reviews"><span class="lux-rating__stars" aria-hidden="true">★★★★★</span><strong>5.0</strong><span>from 90 client reviews</span></a>' ], 'Rating pill' ),
		],
		'Hero text'
	);

	$review_card = lux_con(
		$col + $card( 'white' ) + $shadow( 24, 60, 0.25 ) + [
			'width'         => lux_u( 320 ),
			'width_mobile'  => lux_u( 100, '%' ),
			'padding'       => lux_box( 22, 24, 22, 24 ),
			'border_radius' => lux_box( 24 ),
			'flex_gap'      => lux_gap( 8 ),
			'css_classes'   => '',
		],
		[
			lux_heading( '★★★★★', 'div', [ 'f' => 'Manrope', 's' => 16, 'w' => '400', 'lh' => 1.2, 'ls' => 0.13 ], 'accent', [ '_title' => 'Stars' ] ),
			lux_text( '<p>“I’ve learned so much about my skin in just one hour with her. 10/10 recommend.”</p>', [ 'f' => 'Fraunces', 's' => 18, 'w' => '400', 'lh' => 1.4 ], 'primary' ),
			lux_heading( 'Laury C., facial client', 'div', [ 'f' => 'Manrope', 's' => 14, 'w' => '700', 'lh' => 1.3 ], 'muted' ),
		],
		'Floating card: review'
	);
	$new_card = lux_con(
		$col + [
			'background_background' => 'classic',
			'background_color'      => 'rgba(255,255,255,0.14)',
			'border_border'         => 'solid',
			'border_width'          => lux_box( 1 ),
			'border_color'          => 'rgba(255,255,255,0.35)',
			'width'                 => lux_u( 260 ),
			'width_mobile'          => lux_u( 100, '%' ),
			'padding'               => lux_box( 18, 22, 18, 22 ),
			'border_radius'         => lux_box( 20 ),
			'flex_gap'              => lux_gap( 4 ),
			'css_classes'           => 'lux-glass',
		],
		[
			lux_heading( 'New client?', 'div', [ 'f' => 'Manrope', 's' => 14, 'w' => '700', 'lh' => 1.3 ], 'cream', [ '_css_classes' => 'lux-live' ] ),
			lux_heading( 'Facial + skin consultation, 60 min, $155', 'div', [ 'f' => 'Manrope', 's' => 15, 'w' => '500', 'lh' => 22, 'lhu' => 'px' ], 'cream' ),
		],
		'Floating card: new client'
	);
	$hero_right = lux_con(
		$col + [
			'width'                => lux_u( 36, '%' ),
			'width_tablet'         => lux_u( 100, '%' ),
			'flex_align_items'     => 'flex-end',
			'flex_align_items_tablet' => 'flex-start',
			'flex_justify_content' => 'flex-end',
			'flex_gap'             => lux_gap( 16 ),
			'align_self'           => 'flex-end',
			'hide_mobile'          => 'hidden-mobile',
		],
		[ $new_card, $review_card ],
		'Hero cards'
	);

	$els[] = lux_section(
		'01 Hero',
		[
			'css_classes'                         => 'lux-hero lux-hero--photo',
			'background_background'               => 'classic',
			'background_image'                    => $img( 'skynco-hero-facial.jpg' ),
			'background_position'                 => 'center right',
			'background_position_mobile'          => '70% center',
			'background_size'                     => 'cover',
			'background_overlay_background'       => 'gradient',
			'background_overlay_color'            => 'rgba(38,16,31,0.94)',
			'background_overlay_color_stop'       => lux_u( 28, '%' ),
			'background_overlay_color_b'          => 'rgba(38,16,31,0.12)',
			'background_overlay_color_b_stop'     => lux_u( 92, '%' ),
			'background_overlay_gradient_type'    => 'linear',
			'background_overlay_gradient_angle'   => lux_u( 90, 'deg' ),
			'background_overlay_gradient_angle_mobile' => lux_u( 180, 'deg' ),
			'flex_direction'                      => 'row',
			'flex_direction_tablet'               => 'column',
			'flex_justify_content'                => 'space-between',
			'flex_align_items'                    => 'center',
			'flex_align_items_tablet'             => 'flex-start',
			'flex_gap'                            => lux_gap( 48 ),
			'min_height'                          => lux_u( 760 ),
			'min_height_mobile'                   => lux_u( 640 ),
			'padding'                             => lux_box( 120, 24, 180, 24 ),
			'padding_mobile'                      => lux_box( 72, 16, 140, 16 ),
			'overflow'                            => 'hidden',
		],
		[ $hero_left, $hero_right ]
	);
	/* ---------------- 02 BOOKING BAR ---------------- */
	$services = [
		'New Client Facial & Consultation ($155)',
		'Signature Facial ($150)',
		'Express Facial ($115)',
		'Acne Facial ($150)',
		'Brightening Facial ($150)',
		'Exfoliating Facial ($150)',
		'Derma Facial / Dermaplaning ($150)',
		'Oxy Facial ($175)',
		'Glo2 Facial ($200)',
		'Microneedling ($185)',
		'Chemical Peel ($140 to $155)',
		'Gentleman’s Facial ($155)',
		'Teen Facial, ages 14 to 17 ($120)',
		'Waxing (from $10)',
		'Virtual Consultation',
	];
	$opts = '';
	foreach ( $services as $label ) {
		$opts .= "\n      <option value=\"" . esc_attr( lux_tslug( $label ) ) . '">' . esc_html( $label ) . '</option>';
	}
	$form = <<<HTML
<form class="lux-bookbar" action="$book" method="get">
  <label class="lux-field"><span>Treatment</span>
    <select name="service" aria-label="Treatment">$opts
    </select></label>
  <div class="lux-field"><span>Studio</span>
    <strong>150 Arsenal St, Suite 210, Watertown</strong></div>
  <div class="lux-field"><span>Questions?</span>
    <a href="$tel">(857) 228-4708</a></div>
  <button type="submit">Book Appointment</button>
</form>
HTML;
	$els[] = lux_section(
		'02 Booking bar',
		[
			'padding'        => lux_box( 0, 24, 0, 24 ),
			'padding_mobile' => lux_box( 0, 16, 0, 16 ),
			'margin'         => lux_box( -72, 0, 0, 0 ),
			'z_index'        => 3,
		],
		[
			lux_con(
				$col + $card( 'white', $border() ) + $shadow( 24, 60, 0.12 ) + [ 'padding' => lux_box( 12 ), 'border_radius' => lux_box( 28 ) ],
				[ lux_w( 'html', [ 'html' => $form ], 'Booking bar (opens the live booking page)' ) ],
				'Booking card'
			),
		]
	);

	/* ---------------- 03 TRUST ROW ---------------- */
	$trust = [
		'view'          => 'inline',
		'icon_size'     => lux_u( 12 ),
		'text_indent'   => lux_u( 12 ),
		'_css_classes'  => 'lux-trust',
		'space_between' => lux_u( 40 ),
	];
	lux_color( $trust, 'icon_color', 'primary' );
	lux_color( $trust, 'text_color', 'primary' );
	lux_typo( $trust, 'icon_', [ 'f' => 'Manrope', 's' => 16, 'w' => '600', 'lh' => 1.4 ] );
	$els[] = lux_section(
		'03 Trust row',
		[ 'padding' => lux_box( 56, 24, 0, 24 ), 'padding_mobile' => lux_box( 40, 16, 0, 16 ) ],
		[
			lux_con(
				$col + [
					'border_border' => 'dotted',
					'border_width'  => lux_box( 2, 0, 2, 0 ),
					'border_color'  => '#EBC9CF',
					'padding'       => lux_box( 24, 8, 24, 8 ),
				],
				[ lux_icon_list( [ 'Licensed esthetician, 10+ years in beauty', 'Professional products, chosen for your skin', 'Virtual consultations available' ], 'fas fa-check', $trust ) ],
				'Trust items'
			),
		]
	);

	/* ---------------- 04 TREATMENTS BAND ---------------- */
	$tx = [
		[ 'New Client Facial & Consultation', '60 min', '$155', 'skynco-new-client-facial.jpg' ],
		[ 'Glo2 Facial', '60 min', '$200', 'skynco-glo2-facial.jpg' ],
		[ 'Oxy Facial', '75 min', '$175', 'skynco-oxygen-facial.jpg' ],
	];
	$arrow_icon = function ( $bg, $fg ) {
		$a = [ 'view' => 'stacked', 'shape' => 'circle', 'size' => lux_u( 16 ), 'icon_padding' => lux_u( 16 ), 'rotate' => lux_u( -45, 'deg' ) ];
		lux_color( $a, 'primary_color', $bg );
		lux_color( $a, 'secondary_color', $fg );
		return $a + [ '_position' => 'absolute', '_offset_orientation_h' => 'end', '_offset_x_end' => lux_u( 14 ), '_offset_y' => lux_u( 14 ), '_element_width' => 'auto', '_z_index' => 2 ];
	};
	$cards = [];
	foreach ( $tx as $t ) {
		$cards[] = lux_con(
			$col + $card( 'sagelt' ) + [
				'background_image'     => $img( $t[3] ),
				'background_size'      => 'cover',
				'background_position'  => 'center center',
				'min_height'           => lux_u( 380 ),
				'min_height_mobile'    => lux_u( 340 ),
				'flex_justify_content' => 'flex-end',
				'padding'              => lux_box( 10 ),
				'border_radius'        => lux_box( 28 ),
				'overflow'             => 'hidden',
				'html_tag'             => 'a',
				'link'                 => lux_link( lux_tlink( $t[0] ) ),
				'css_classes'          => 'lux-card',
			],
			[
				lux_icon( 'fas fa-arrow-right', $arrow_icon( 'accent', 'white' ) ),
				lux_con(
					$col + $card( 'white' ) + [ 'padding' => lux_box( 16, 18, 16, 18 ), 'border_radius' => lux_box( 20 ), 'flex_gap' => lux_gap( 4 ) ],
					[
						lux_heading( $t[0], 'h3', [ 'f' => 'Fraunces', 's' => 21, 'w' => '400', 'lh' => 28, 'lhu' => 'px' ] ),
						lux_con(
							$row + [ 'flex_justify_content' => 'space-between', 'flex_gap' => lux_gap( 8 ) ],
							[
								lux_heading( $t[1], 'div', lux_t( 'small' ), 'muted' ),
								lux_heading( $t[2], 'div', [ 'f' => 'Manrope', 's' => 15, 'w' => '700', 'lh' => 24, 'lhu' => 'px' ], 'primary' ),
							]
						),
					],
					'Card label'
				),
			],
			'Card: ' . $t[0]
		);
	}
	$cards[] = lux_con(
		$col + $card( 'accent' ) + [
			'min_height'           => lux_u( 380 ),
			'min_height_mobile'    => lux_u( 280 ),
			'flex_justify_content' => 'flex-end',
			'flex_gap'             => lux_gap( 12 ),
			'padding'              => lux_box( 28 ),
			'border_radius'        => lux_box( 28 ),
			'html_tag'             => 'a',
			'link'                 => lux_link( $menu ),
			'css_classes'          => 'lux-card',
		],
		[
			lux_icon( 'fas fa-arrow-right', $arrow_icon( 'primary', 'white' ) ),
			lux_heading( '11 more treatments, plus waxing', 'h3', [ 'f' => 'Fraunces', 's' => 26, 'w' => '400', 'lh' => 1.2 ], 'white' ),
			lux_text( '<p>Signature, Acne, Brightening and Derma facials, chemical peels, microneedling, the Gentleman’s and Teen facials. Waxing from $10.</p>', [ 'f' => 'Manrope', 's' => 15, 'w' => '500', 'lh' => 23, 'lhu' => 'px' ], 'white' ),
		],
		'Card: full menu'
	);
	$els[] = lux_section(
		'04 Treatments band',
		[ 'padding' => lux_box( 120, 24, 0, 24 ), 'padding_mobile' => lux_box( 72, 16, 0, 16 ), '_element_id' => 'treatments' ],
		[
			lux_con(
				$col + $card( 'primary' ) + [
					'padding'              => lux_box( 88, 48, 88, 48 ),
					'padding_mobile'       => lux_box( 48, 20, 48, 20 ),
					'border_radius'        => lux_box( 40 ),
					'border_radius_mobile' => lux_box( 24 ),
					'flex_gap'             => lux_gap( 48 ),
				],
				[
					lux_head_row(
						[
							lux_eyebrow( 'Treatments', 'dark' ),
							lux_heading( 'Facials built around your skin, with every price <em>upfront.</em>', 'h2', 'h2', 'cream' ),
						],
						lux_button( 'Full menu and prices', $menu, 'lime' ),
						640
					),
					lux_con( $grid( 4, 2, 1, 16 ) + [ 'css_classes' => 'lux-mscroll' ], $cards, 'Treatment cards' ),
				],
				'Treatments panel'
			),
		],
		1296
	);

	/* ---------------- 05 FIRST VISIT / BENTO ---------------- */
	$concerns = '<ul class="lux-concerns">'
		. '<li><span>Acne and breakouts</span><b>Acne Facial</b></li>'
		. '<li><span>Dark marks and hyperpigmentation</span><b>Chemical Peel</b></li>'
		. '<li><span>Fine lines and texture</span><b>Microneedling</b></li>'
		. '<li><span>Dull, tired skin</span><b>Glo2 or Brightening Facial</b></li>'
		. '<li><span>Rough skin and peach fuzz</span><b>Derma Facial</b></li>'
		. '<li><span>Shaving irritation</span><b>Gentleman’s Facial</b></li>'
		. '</ul>';

	$els[] = lux_section(
		'05 First visit',
		[ 'flex_gap' => lux_gap( 20 ) ],
		[
			lux_heading( 'Your first visit starts with a skin analysis, not a sales pitch.', 'h2', 'h2', 'primary', $narrow( 760 ) ),
			lux_con(
				$grid( 3, 1, 1, 16 ) + [ 'margin' => lux_box( 28, 0, 0, 0 ) ],
				[
					lux_con(
						$col + $card( 'forestdk' ) + [
							'padding'              => lux_box( 40 ),
							'padding_mobile'       => lux_box( 28 ),
							'border_radius'        => lux_box( 28 ),
							'flex_justify_content' => 'space-between',
							'flex_align_items'     => 'flex-start',
							'flex_gap'             => lux_gap( 28 ),
						],
						[
							lux_eyebrow( 'New Client Facial, 60 min, $155', 'none-gold' ),
							lux_heading( 'We study your skin, then build your plan.', 'h3', [ 'f' => 'Fraunces', 's' => 30, 'w' => '400', 'lh' => 38, 'lhu' => 'px' ], 'cream' ),
							lux_text(
								'<ol><li>A full Signature Facial, customized to your skin on the day.</li><li>A 15-minute consultation on your history, routine and goals.</li><li>A written skin analysis with your home regimen.</li><li>Product picks and the next best treatments for you.</li></ol>',
								[ 'f' => 'Manrope', 's' => 16, 'w' => '400', 'lh' => 25, 'lhu' => 'px' ],
								'misttext',
								[ '_css_classes' => 'lux-ol' ]
							),
							lux_button( 'Book your first visit', $book . '?service=new-client-facial', 'lime' ),
						],
						'Card: new client facial'
					),
					lux_con(
						$col + $card( 'sagemist' ) + [
							'padding'              => lux_box( 32 ),
							'border_radius'        => lux_box( 28 ),
							'flex_justify_content' => 'center',
							'flex_align_items'     => 'center',
							'hide_mobile'          => 'hidden-mobile',
						],
						[ lux_img( 'Treatment mask being brushed on', [ 'image' => $img( 'skynco-peel-mask.png' ), 'width' => lux_u( 100, '%' ), 'space' => lux_u( 340 ) ] ) ],
						'Card: image'
					),
					lux_con(
						$col + $card( 'white', $border() ) + $shadow( 10, 30, 0.06 ) + [
							'padding'       => lux_box( 28 ),
							'border_radius' => lux_box( 28 ),
							'flex_gap'      => lux_gap( 14 ),
						],
						[
							lux_heading( 'Not sure what to book?', 'h3', [ 'f' => 'Fraunces', 's' => 22, 'w' => '400', 'lh' => 1.3 ] ),
							lux_text( '<p>Start from your main concern. Hana will confirm the right treatment at your visit.</p>', 'small', 'muted' ),
							lux_text( $concerns, 'small', 'text' ),
						],
						'Card: concern guide'
					),
				],
				'First visit cards'
			),
		]
	);

	/* ---------------- 06 YOUR VISIT ---------------- */
	$steps = [
		[ '01', 'Book online', 'Choose your treatment and time. A deposit holds your appointment.' ],
		[ '02', 'Consult', 'Hana reviews your skin, history and routine, so the treatment fits your skin that day.' ],
		[ '03', 'Treat', 'Relax while your facial or treatment is done in a calm, private studio.' ],
		[ '04', 'Home care', 'Leave with a regimen and product advice, then rebook when your skin is ready.' ],
	];
	$step_els = [];
	foreach ( $steps as $s ) {
		$step_els[] = lux_con(
			$col + [
				'flex_gap'      => lux_gap( 14 ),
				'padding'       => lux_box( 24, 0, 0, 0 ),
				'border_border' => 'dotted',
				'border_width'  => lux_box( 2, 0, 0, 0 ),
				'border_color'  => '#E8B4B8',
			],
			[
				lux_heading( $s[0], 'div', [ 'f' => 'Fraunces', 's' => 40, 'w' => '400', 'lh' => 1.1, 'fs' => 'italic' ] ),
				lux_heading( $s[1], 'h3', 'h3' ),
				lux_text( '<p>' . $s[2] . '</p>', [ 'f' => 'Manrope', 's' => 16, 'w' => '400', 'lh' => 26, 'lhu' => 'px' ] ),
			],
			'Step ' . $s[0]
		);
	}
	$els[] = lux_section(
		'06 Your visit',
		$card( 'sagemist' ) + [ 'flex_gap' => lux_gap( 56 ) ],
		[
			lux_head_row(
				[ lux_heading( 'Four steps, and you always know what comes next.', 'h2', 'h2' ) ],
				lux_button( 'Book Appointment', $svcs ),
				640
			),
			lux_con( $grid( 4, 2, 2, 32 ) + [ 'grid_gaps_mobile' => lux_gap( 20 ), 'css_classes' => 'lux-steps' ], $step_els, 'Steps' ),
		]
	);

	/* ---------------- 07 MEET HANA ---------------- */
	$stat = function ( $num, $label ) {
		return lux_con(
			[ 'flex_direction' => 'column', 'flex_gap' => lux_gap( 4 ), 'padding' => lux_box( 16, 0, 0, 0 ), 'border_border' => 'solid', 'border_width' => lux_box( 1, 0, 0, 0 ), 'border_color' => '#EADDE0' ],
			[
				lux_heading( $num, 'div', [ 'f' => 'Fraunces', 's' => 40, 'w' => '500', 'lh' => 1.1 ] ),
				lux_heading( $label, 'div', lux_t( 'small' ), 'muted' ),
			],
			'Stat: ' . $label
		);
	};
	$els[] = lux_section(
		'07 Meet Hana',
		[
			'flex_direction'        => 'row',
			'flex_direction_tablet' => 'column',
			'flex_align_items'      => 'center',
			'flex_justify_content'  => 'space-between',
			'flex_gap'              => lux_gap( 72 ),
			'_element_id'           => 'about',
		],
		[
			lux_con(
				$col + [ 'width' => lux_u( 44, '%' ), 'width_tablet' => lux_u( 100, '%' ), 'flex_align_items' => 'center', 'css_classes' => 'lux-story-img' ],
				[ lux_img( 'Skincare and self-care moments', [ 'image' => $img( 'skynco-our-story.png' ), 'width' => lux_u( 100, '%' ), 'space' => lux_u( 520 ) ] ) ],
				'Story image'
			),
			lux_con(
				$col + [ 'width' => lux_u( 50, '%' ), 'width_tablet' => lux_u( 100, '%' ), 'flex_align_items' => 'flex-start', 'flex_gap' => lux_gap( 20 ) ],
				[
					lux_eyebrow( 'Our story' ),
					lux_heading( 'Founded by Hana Rahim, for skin that <em>feels cared for.</em>', 'h2', 'h2' ),
					lux_text( '<p>At Skyn&amp;Co. Skincare &amp; Wellness, skincare is more than a treatment. It is a ritual of restoration, confidence and self-care.</p><p>Hana is a licensed esthetician with over 10 years in the beauty industry and more than 8 years of hands-on practice. She built Skyn&amp;Co. to offer results-driven skincare in a calm, elevated space where science, wellness and luxury meet, with education and personal care at every visit.</p>', 'body', 'muted' ),
					lux_con(
						$grid( 3, 3, 3, 20 ) + [ 'margin' => lux_box( 8, 0, 8, 0 ), 'grid_gaps_mobile' => lux_gap( 12 ) ],
						[ lux_counter( 10, '+', 'years in beauty' ), lux_counter( 8, '+', 'years hands-on' ), lux_counter( 90, '', 'five-star reviews' ) ],
						'Stats'
					),
					lux_button( 'Book with Hana', $svcs, 'outline' ),
				],
				'Story text'
			),
		]
	);

	/* ---------------- 08 SHOP: HOME CARE ---------------- */
	$els[] = lux_shop_strip( 'Keep your results going <em>at home.</em>', 'The products Hana uses and recommends most, chosen to extend every facial.' );

	/* ---------------- 08b REAL RESULTS (before & after sliders) ---------------- */
	$els[] = lux_section( '08b Real results', [ 'padding' => lux_box( 0, 0, 96, 0 ), 'padding_mobile' => lux_box( 0, 0, 56, 0 ) ], [ lux_w( 'shortcode', [ 'shortcode' => '[skynco_before_after]' ], 'Before & after sliders (featured)' ) ] );

	/* ---------------- 09 REVIEWS ---------------- */
	$els[] = lux_reviews_section();
	/* ---------------- 11 CTA ---------------- */
	$els[] = lux_section(
		'11 CTA',
		[ 'padding' => lux_box( 0, 24, 120, 24 ), 'padding_mobile' => lux_box( 0, 16, 72, 16 ) ],
		[
			lux_con(
				$col + $card( 'forestdk' ) + [
					'css_classes'          => 'lux-grid-bg',
					'padding'              => lux_box( 96, 24, 96, 24 ),
					'padding_mobile'       => lux_box( 56, 20, 56, 20 ),
					'border_radius'        => lux_box( 40 ),
					'border_radius_mobile' => lux_box( 28 ),
					'flex_align_items'     => 'center',
					'flex_gap'             => lux_gap( 18 ),
				],
				[
					lux_heading( 'Your skin goals start with <em>one visit.</em>', 'h2', [ 'f' => 'Fraunces', 's' => 52, 'st' => 44, 'sm' => 32, 'w' => '500', 'lh' => 1.15, 'ls' => -0.01 ], 'cream', [ 'align' => 'center', '_element_width' => 'initial', '_element_custom_width' => lux_u( 720 ), '_element_custom_width_mobile' => lux_u( 100, '%' ) ] ),
					lux_text( '<p>Book the New Client Facial &amp; Consultation and leave with a plan made for your skin. 150 Arsenal St, Suite 210, Watertown, MA 02472.</p>', 'body', 'misttext', [ 'align' => 'center', '_element_width' => 'initial', '_element_custom_width' => lux_u( 560 ), '_element_custom_width_mobile' => lux_u( 100, '%' ) ] ),
					lux_con(
						$row + [ 'flex_wrap' => 'wrap', 'flex_justify_content' => 'center', 'flex_gap' => lux_gap( 12 ), 'margin' => lux_box( 18, 0, 0, 0 ) ],
						[
							lux_button( 'Book Appointment', $svcs, 'lime' ),
							lux_button( 'Call (857) 228-4708', $tel, 'outline-light' ),
						],
						'Buttons'
					),
				],
				'CTA panel'
			),
		]
	);

	return $els;
}
