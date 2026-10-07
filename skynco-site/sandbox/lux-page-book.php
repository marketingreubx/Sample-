<?php
/** Skyn&Co. – /book/ page: branded wrapper around the LatePoint booking form. */
if ( ! defined( 'ABSPATH' ) || function_exists( 'lux_book_elements' ) ) {
	return;
}

function lux_book_elements() {
	$k   = lux_inner_kit();
	$col = [ 'flex_direction' => 'column' ];
	$s   = [ 'view' => 'traditional', 'icon_size' => lux_u( 13 ), 'text_indent' => lux_u( 12 ), 'space_between' => lux_u( 12 ) ];
	lux_color( $s, 'icon_color', 'accent' );
	$s['text_color'] = '#2A1A24';
	lux_typo( $s, 'icon_', [ 'f' => 'Manrope', 's' => 15, 'w' => '500', 'lh' => 1.5 ] );
	$notes = lux_con(
		$col + $k['card']( 'white', $k['border']() ) + [ 'width' => lux_u( 40, '%' ), 'width_tablet' => lux_u( 100, '%' ), 'padding' => lux_box( 28 ), 'border_radius' => lux_box( 24 ), 'flex_gap' => lux_gap( 14 ) ],
		[
			lux_heading( 'Good to know', 'h2', [ 'f' => 'Fraunces', 's' => 24, 'w' => '400', 'lh' => 1.2 ] ),
			lux_icon_list( [ 'A $25 to $50 deposit holds most facials', 'Waxing is paid at the studio', 'Add more than one treatment before checkout', 'Free changes up to 24 hours before' ], 'fas fa-check', $s ),
			lux_text( '<p>Please read the <a href="' . home_url( '/booking-conditions/' ) . '">booking conditions</a> before you book.</p>', 'small', 'muted' ),
		],
		'Booking notes'
	);
	$els   = [];
	$els[] = lux_page_hero( '01 Hero', 'Online booking', 'Book your <em>visit.</em>', 'Choose your treatment, pick a time that suits you and check out in a few steps. Not sure what to book? Start with the New Client Facial &amp; Consultation.', [ lux_button( 'Browse treatments', home_url( '/services/' ), 'outline' ), lux_button( 'Call ' . $k['phone'], $k['tel'], 'outline' ) ], $notes );
	$els[] = lux_section(
		'02 Booking form',
		[ 'padding' => lux_box( 0, 24, 96, 24 ), 'padding_mobile' => lux_box( 0, 12, 64, 12 ), 'margin' => lux_box( -48, 0, 0, 0 ) ],
		[
			lux_con(
				$col + $k['card']( 'white', $k['border']() ) + [ 'padding' => lux_box( 12 ), 'border_radius' => lux_box( 28 ), 'css_classes' => 'lux-book-shell' ],
				[ lux_w( 'shortcode', [ 'shortcode' => '[skynco_booking]' ], 'LatePoint booking form' ) ],
				'Form card'
			),
		]
	);
	return $els;
}