<?php
/**
 * Skyn&Co. – FAQ page (questions grouped by topic, with FAQ schema).
 */
if ( ! defined( 'ABSPATH' ) || function_exists( 'lux_faq_accordion' ) ) {
	return;
}

/** Elementor nested accordion in the site's FAQ style. */
function lux_faq_accordion( array $faqs, $schema = false ) {
	$items = [];
	$kids  = [];
	foreach ( $faqs as $f ) {
		$items[] = [ 'item_title' => $f[0], '_id' => lux_id() ];
		$kids[]  = lux_con( [ 'flex_direction' => 'column' ], [ lux_text( '<p>' . $f[1] . '</p>', [ 'f' => 'Manrope', 's' => 16, 'w' => '400', 'lh' => 26, 'lhu' => 'px' ], 'muted' ) ] );
	}
	$acc = [
		'items'                                      => $items,
		'faq_schema'                                 => $schema ? 'yes' : '',
		'default_state'                              => 'all_collapsed',
		'max_items_expended'                         => 'one',
		'title_tag'                                  => 'h3',
		'accordion_item_title_position_horizontal'   => 'stretch',
		'accordion_item_title_icon_position'         => 'end',
		'accordion_item_title_icon'                  => [ 'value' => 'fas fa-plus', 'library' => 'fa-solid' ],
		'accordion_item_title_icon_active'           => [ 'value' => 'fas fa-minus', 'library' => 'fa-solid' ],
		'accordion_item_title_space_between'         => lux_u( 0 ),
		'accordion_item_title_distance_from_content' => lux_u( 0 ),
		'accordion_border_normal_border'             => 'none',
		'accordion_border_hover_border'              => 'none',
		'accordion_border_active_border'             => 'none',
		'accordion_padding'                          => lux_box( 22, 0, 22, 0 ),
		'content_padding'                            => lux_box( 0, 52, 24, 0 ),
		'_css_classes'                               => 'lux-faq',
	];
	lux_typo( $acc, 'title_', [ 'f' => 'Manrope', 's' => 17, 'w' => '600', 'lh' => 26, 'lhu' => 'px' ] );
	$w             = lux_w( 'nested-accordion', $acc, 'FAQ accordion' );
	$w['elements'] = $kids;
	return $w;
}

function lux_faq_data() {
	$p = home_url( '/policies/' );
	$b = home_url( '/book/' );
	return [
		'Your first visit' => [
			[ 'I’m a new client. What should I book?', 'Start with the <a href="' . home_url( '/services/new-client-facial/' ) . '">New Client Facial &amp; Consultation</a> ($155, 60 minutes). It includes a full Signature Facial, a 15-minute consultation and a written skin analysis with your home regimen. It is also the right choice if your last visit was more than 12 months ago.' ],
			[ 'How should I prepare?', 'Arrive 5 to 10 minutes early with a clean, makeup-free face. A $5 makeup removal fee may apply if removal is needed. Bring a list of the products you use now.' ],
			[ 'Do you treat men and teens?', 'Yes. The Gentleman’s Facial is built for men’s skin and includes a beard treatment. The Teen Facial is for ages 14 to 17; a legal guardian must fill out the consent form, and ID may be requested.' ],
			[ 'I have allergies or a medical condition. Can I still book?', 'Usually, yes. Tell us about any allergies, sensitivities, pregnancy or medical conditions before your service so your treatment can be safely customized.' ],
		],
		'Treatments and results' => [
			[ 'Will a chemical peel make my skin peel?', 'It depends on the peel. The Chemical Peel (no visible peeling) and the Exfoliating Facial have no downtime. The TCA Chemical Peel causes peeling for about 7 to 10 days, and 1 to 2 treatments may be needed for full results.' ],
			[ 'What is the downtime after microneedling?', 'Expect your skin to feel sensitive, red and dry for about 3 days, with full recovery in roughly 7 days. Results build over a series of treatments.' ],
			[ 'How often should I get a facial?', 'Every 4 to 6 weeks keeps most skin on track, which matches your skin’s natural renewal cycle. Hana will suggest a schedule for your goals.' ],
			[ 'Which facial is best for acne?', 'The Acne Facial uses Blue LED and high frequency to target breakout bacteria. For teens, choose the Teen Facial. Pair either with the Clear Skin Kit at home.' ],
		],
		'Booking and payment' => [
			[ 'Is there a deposit?', 'Most facials and advanced treatments need a $25 to $50 deposit to hold your time, taken when you book online. Waxing is paid at the studio. See our <a href="' . $p . '">policies</a>.' ],
			[ 'Can I book more than one treatment at once?', 'Yes. On the <a href="' . $b . '">booking page</a> you can add more treatments before you check out.' ],
			[ 'How do I cancel or reschedule?', 'Please give 24 hours’ notice. Cancellations within 2 hours are last-minute and incur a $50 rebooking fee.' ],
			[ 'What payment methods do you accept?', 'Cards online, and cash, Venmo, debit and credit cards in the studio.' ],
		],
		'Shop and gift cards' => [
			[ 'Is there a discount on my first order?', 'Yes. Use code <b>GLOW10</b> at checkout for 10% off your first home-care order.' ],
			[ 'How much is shipping?', 'Free on orders over $75, $8 otherwise, or choose free pickup at the studio in Watertown.' ],
			[ 'Can I return a product?', 'Unopened products can be returned within 30 days. Opened products can only be returned if they caused a reaction. Contact us first.' ],
			[ 'How do gift cards work?', 'E-gift cards are delivered by email, never expire and can be used for any treatment or product.' ],
		],
	];
}

function lux_faq_elements() {
	$k   = lux_inner_kit();
	$els = [];
	$els[] = lux_photo_hero(
		'01 Hero',
		'FAQ',
		'Questions, <em>answered.</em>',
		'Everything clients ask before their first visit, about results and downtime, booking, and our shop. Still unsure? Call ' . $k['phone'] . '.',
		[ lux_button( 'Book Appointment', $k['services'], 'lime' ), lux_button( 'Studio policies', home_url( '/policies/' ), 'outline-light' ) ],
		'skynco-peel-mask.png',
		'faq'
	);
	$first = true;
	foreach ( lux_faq_data() as $group => $faqs ) {
		$els[] = lux_section(
			'FAQ: ' . $group,
			[
				'flex_direction'        => 'row',
				'flex_direction_tablet' => 'column',
				'flex_gap'              => lux_gap( 56 ),
				'flex_align_items'      => 'flex-start',
				'padding'               => lux_box( 56, 24, 56, 24 ),
				'padding_mobile'        => lux_box( 40, 16, 32, 16 ),
				'border_border'         => 'solid',
				'border_width'          => lux_box( 0, 0, 1, 0 ),
				'border_color'          => '#EADDE0',
				'_element_id'           => sanitize_title( $group ),
			],
			[
				lux_con( [ 'flex_direction' => 'column', 'width' => lux_u( 32, '%' ), 'width_tablet' => lux_u( 100, '%' ) ], [ lux_heading( $group, 'h2', [ 'f' => 'Fraunces', 's' => 32, 'sm' => 28, 'w' => '400', 'lh' => 1.2 ] ) ] ),
				lux_con( [ 'flex_direction' => 'column', 'width' => lux_u( 64, '%' ), 'width_tablet' => lux_u( 100, '%' ) ], [ lux_faq_accordion( $faqs, $first ) ] ),
			]
		);
		$first = false;
	}
	$els[] = lux_section( 'Spacer', [ 'padding' => lux_box( 80, 0, 0, 0 ), 'padding_mobile' => lux_box( 48, 0, 0, 0 ) ], [] );
	$els[] = lux_page_cta( 'Still have a <em>question?</em>', 'Call or text ' . $k['phone'] . ' and we will help you choose the right treatment.' );
	return $els;
}
