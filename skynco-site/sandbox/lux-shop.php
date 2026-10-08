<?php
/**
 * Skyn&Co. – retail shop: product catalogue, setup and page sections.
 * Runtime features (product cards shortcode, free-shipping bar, order bump) live in the skynco-studio plugin.
 */
if ( ! defined( 'ABSPATH' ) || function_exists( 'lux_shop_catalog' ) ) {
	return;
}

/**
 * Retail catalogue: slug => [name, category, regular, sale, short, description, pexels photo id, badge, upsells[], cross-sells[], kit contents[]].
 * Product names and prices are placeholders until the owner confirms the real product line.
 */
function lux_shop_catalog() {
	return [
		'gentle-cleansing-gel'          => [ 'Gentle Cleansing Gel', 'cleansers', 34, 0, 'A low-foam gel that lifts makeup, SPF and oil without stripping.', 'Our everyday cleanser for every skin type. It removes makeup, sunscreen and excess oil while keeping the skin barrier calm and comfortable. Use morning and night on damp skin, then rinse.', 16378446, 'Bestseller', [ 'glow-kit', 'clear-skin-kit' ], [ 'vitamin-c-brightening-serum', 'daily-mineral-spf-40' ] ],
		'hydrating-hyaluronic-serum'    => [ 'Hydrating Hyaluronic Serum', 'serums', 48, 0, 'Multi-weight hyaluronic acid for plump, bouncy skin.', 'Draws water into the skin and holds it there, so skin looks smoother and feels comfortable all day. Ideal after microneedling, peels and oxygen facials. Apply 2 to 3 drops to damp skin before moisturizer.', 8101534, '', [ 'recovery-kit' ], [ 'barrier-repair-moisturizer', 'daily-mineral-spf-40' ] ],
		'vitamin-c-brightening-serum'   => [ 'Vitamin C Brightening Serum', 'serums', 58, 0, 'Stable vitamin C to fade dark marks and boost glow.', 'An antioxidant serum that brightens dull skin, softens the look of dark marks and helps protect against daily environmental stress. Use in the morning, followed by SPF.', 16378443, 'Hana’s pick', [ 'glow-kit' ], [ 'daily-mineral-spf-40', 'hydrating-hyaluronic-serum' ] ],
		'barrier-repair-moisturizer'    => [ 'Barrier Repair Moisturizer', 'moisturizers-spf', 46, 0, 'Ceramide cream that calms, softens and restores.', 'A rich but breathable moisturizer with ceramides and niacinamide to strengthen the skin barrier, calm redness and lock in hydration. Use morning and night.', 8100691, '', [ 'clear-skin-kit' ], [ 'hydrating-hyaluronic-serum', 'daily-mineral-spf-40' ] ],
		'daily-mineral-spf-40'          => [ 'Daily Mineral SPF 40', 'moisturizers-spf', 42, 0, 'Sheer zinc sunscreen with no white cast.', 'Lightweight mineral protection that wears well under makeup. Daily SPF is the single best way to protect your facial results, especially after peels and microneedling. Apply as the last step every morning.', 6476115, 'Bestseller', [ 'glow-kit', 'recovery-kit' ], [ 'vitamin-c-brightening-serum', 'barrier-repair-moisturizer' ] ],
		'clarifying-spot-treatment'     => [ 'Clarifying Spot Treatment', 'masks-treatments', 26, 0, 'Targets breakouts overnight without over-drying.', 'Salicylic acid and tea tree work on active breakouts while soothing the skin around them. Dab a thin layer onto blemishes at night.', 8709569, '', [ 'clear-skin-kit' ], [ 'gentle-cleansing-gel', 'enzyme-exfoliating-mask' ] ],
		'enzyme-exfoliating-mask'       => [ 'Enzyme Exfoliating Mask', 'masks-treatments', 44, 0, 'A weekly fruit-enzyme polish for smooth, bright skin.', 'Gently dissolves dead skin cells for a smoother, brighter complexion between facials. Leave on for 10 minutes once or twice a week, then rinse.', 8101673, '', [ 'glow-kit' ], [ 'hydrating-hyaluronic-serum', 'barrier-repair-moisturizer' ] ],
		'post-treatment-recovery-balm'  => [ 'Post-Treatment Recovery Balm', 'masks-treatments', 32, 0, 'Soothes and protects skin after peels and microneedling.', 'A calming balm that comforts tight, sensitive skin after advanced treatments and supports healing. Apply a thin layer as often as needed during the first week.', 8100775, '', [ 'recovery-kit' ], [ 'daily-mineral-spf-40', 'hydrating-hyaluronic-serum' ] ],
		'glow-kit'                      => [ 'The Glow Kit', 'kits', 134, 115, 'Cleanser, Vitamin C serum and SPF 40. Your daily glow routine.', 'Everything you need for bright, protected skin every day: Gentle Cleansing Gel, Vitamin C Brightening Serum and Daily Mineral SPF 40. Bought together, you save $19.', 8076229, 'Save $19', [], [ 'enzyme-exfoliating-mask', 'skynco-gift-card' ], [ 'gentle-cleansing-gel', 'vitamin-c-brightening-serum', 'daily-mineral-spf-40' ] ],
		'clear-skin-kit'                => [ 'The Clear Skin Kit', 'kits', 106, 90, 'Cleanser, spot treatment and barrier moisturizer for breakout-prone skin.', 'A simple routine for acne-prone skin that clears breakouts without stripping: Gentle Cleansing Gel, Clarifying Spot Treatment and Barrier Repair Moisturizer. Save $16.', 8102021, 'Save $16', [], [ 'enzyme-exfoliating-mask', 'daily-mineral-spf-40' ], [ 'gentle-cleansing-gel', 'clarifying-spot-treatment', 'barrier-repair-moisturizer' ] ],
		'recovery-kit'                  => [ 'The Recovery Kit', 'kits', 122, 104, 'Balm, hyaluronic serum and SPF for the week after advanced treatments.', 'Recommended after microneedling, dermaplaning and chemical peels: Post-Treatment Recovery Balm, Hydrating Hyaluronic Serum and Daily Mineral SPF 40. Save $18.', 7795755, 'Save $18', [], [ 'barrier-repair-moisturizer', 'skynco-gift-card' ], [ 'post-treatment-recovery-balm', 'hydrating-hyaluronic-serum', 'daily-mineral-spf-40' ] ],
		'skynco-gift-card'              => [ 'Skyn&Co. E-Gift Card', 'gift-cards', 100, 0, 'Use it for any treatment or product. Delivered by email.', 'The easiest gift for anyone who deserves a little time for themselves. Redeemable for any Skyn&Co. treatment or product, delivered by email and never expires.', 8101512, 'Gift idea', [], [ 'glow-kit' ] ],
	];
}

/** Which products to recommend after each treatment. */
function lux_shop_recs_for( $treatment ) {
	$map = [
		'acne'      => [ 'clear-skin-kit', 'clarifying-spot-treatment', 'gentle-cleansing-gel', 'barrier-repair-moisturizer' ],
		'recovery'  => [ 'recovery-kit', 'post-treatment-recovery-balm', 'hydrating-hyaluronic-serum', 'daily-mineral-spf-40' ],
		'bright'    => [ 'glow-kit', 'vitamin-c-brightening-serum', 'daily-mineral-spf-40', 'enzyme-exfoliating-mask' ],
		'hydrate'   => [ 'glow-kit', 'hydrating-hyaluronic-serum', 'vitamin-c-brightening-serum', 'daily-mineral-spf-40' ],
		'men'       => [ 'gentle-cleansing-gel', 'barrier-repair-moisturizer', 'daily-mineral-spf-40', 'clear-skin-kit' ],
		'wax'       => [ 'post-treatment-recovery-balm', 'barrier-repair-moisturizer', 'gentle-cleansing-gel', 'skynco-gift-card' ],
	];
	$by = [
		'acne-facial' => 'acne', 'teen-facial' => 'acne',
		'microneedling' => 'recovery', 'derma-facial' => 'recovery', 'tca-peel' => 'recovery',
		'brightening-facial' => 'bright', 'chemical-peel' => 'bright', 'exfoliating-facial' => 'bright',
		'gentlemans-facial' => 'men',
	];
	if ( false !== strpos( $treatment, 'wax' ) ) {
		return $map['wax'];
	}
	return $map[ $by[ $treatment ] ?? 'hydrate' ];
}

/** Download a Pexels photo (through the weserv resizer) into the media library once. */
function lux_shop_image( $pexels_id, $title ) {
	$ids = get_option( 'skynco_shop_media', [] );
	if ( ! empty( $ids[ $pexels_id ] ) && get_post( $ids[ $pexels_id ] ) ) {
		return (int) $ids[ $pexels_id ];
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$src = 'https://images.weserv.nl/?url=' . rawurlencode( "images.pexels.com/photos/$pexels_id/pexels-photo-$pexels_id.jpeg" ) . '&w=1000&h=1000&fit=cover&a=attention&output=jpg&q=82';
	$tmp = download_url( $src, 60 );
	if ( is_wp_error( $tmp ) ) {
		return 0;
	}
	$id = media_handle_sideload( [ 'name' => 'skynco-shop-' . sanitize_title( $title ) . '.jpg', 'tmp_name' => $tmp ], 0, $title );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp );
		return 0;
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $title );
	$ids[ $pexels_id ] = $id;
	update_option( 'skynco_shop_media', $ids, false );
	return (int) $id;
}

/** Create categories, products, cross/upsells, coupon, shipping and the classic cart flow. Idempotent. */
function lux_shop_setup() {
	$out  = [];
	$cats = [ 'cleansers' => 'Cleansers', 'serums' => 'Serums', 'moisturizers-spf' => 'Moisturizers & SPF', 'masks-treatments' => 'Masks & Spot Care', 'kits' => 'Kits & Bundles', 'gift-cards' => 'Gift Cards' ];
	$cat_ids = [];
	$order = 0;
	foreach ( $cats as $slug => $name ) {
		$t = get_term_by( 'slug', $slug, 'product_cat' );
		$cat_ids[ $slug ] = $t ? (int) $t->term_id : (int) wp_insert_term( $name, 'product_cat', [ 'slug' => $slug ] )['term_id'];
		update_term_meta( $cat_ids[ $slug ], 'order', ++$order );
	}

	$ids = [];
	$i   = 0;
	foreach ( lux_shop_catalog() as $slug => $p ) {
		$pid     = wc_get_product_id_by_sku( 'SKY-R-' . strtoupper( $slug ) );
		$product = $pid ? wc_get_product( $pid ) : new WC_Product_Simple();
		$product->set_name( $p[0] );
		$product->set_slug( $slug );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_sku( 'SKY-R-' . strtoupper( $slug ) );
		$product->set_regular_price( (string) $p[2] );
		$product->set_sale_price( $p[3] ? (string) $p[3] : '' );
		$product->set_short_description( $p[4] );
		$product->set_description( $p[5] );
		$product->set_category_ids( [ $cat_ids[ $p[1] ] ] );
		$product->set_menu_order( ++$i );
		$product->set_virtual( 'gift-cards' === $p[1] );
		$product->set_sold_individually( false );
		$product->set_reviews_allowed( true );
		$img = lux_shop_image( $p[6], $p[0] );
		if ( $img ) {
			$product->set_image_id( $img );
		}
		$ids[ $slug ] = $product->save();
		update_post_meta( $ids[ $slug ], '_skynco_badge', $p[7] );
		update_post_meta( $ids[ $slug ], '_skynco_kit', $p[10] ?? [] );
	}
	foreach ( lux_shop_catalog() as $slug => $p ) {
		$product = wc_get_product( $ids[ $slug ] );
		$product->set_upsell_ids( array_values( array_map( fn( $s ) => $ids[ $s ], $p[8] ) ) );
		$product->set_cross_sell_ids( array_values( array_map( fn( $s ) => $ids[ $s ], $p[9] ) ) );
		$product->save();
	}
	update_option( 'skynco_shop_product_ids', $ids, false );
	$hero = lux_shop_image( 8166777, 'Skincare bottles with palm leaves' );
	if ( $hero ) {
		$media = get_option( 'skynco_media_ids', [] );
		$media['skynco-shop-hero'] = $hero;
		update_option( 'skynco_media_ids', $media );
	}
	$out['products'] = $ids;

	// Treatment pages → products recommended after them (used on the product page too).
	$recs = [];
	foreach ( lux_lp_treatments() as $t ) {
		foreach ( lux_shop_recs_for( $t[0] ) as $s ) {
			$recs[ $ids[ $s ] ][] = $t[0];
		}
	}
	update_option( 'skynco_product_treatments', $recs, false );

	// First-order offer: 10% off home care (not treatments or gift cards).
	$coupon_id = wc_get_coupon_id_by_code( 'GLOW10' );
	$coupon    = new WC_Coupon( $coupon_id ?: 0 );
	$coupon->set_code( 'GLOW10' );
	$coupon->set_discount_type( 'percent' );
	$coupon->set_amount( 10 );
	$coupon->set_individual_use( true );
	$coupon->set_usage_limit_per_user( 1 );
	$coupon->set_excluded_product_categories( array_filter( [ (int) get_term_by( 'slug', 'treatments', 'product_cat' )->term_id, $cat_ids['gift-cards'] ] ) );
	$coupon->set_description( 'First online order: 10% off home care.' );
	$coupon->save();
	$out['coupon'] = $coupon->get_id();

	// Shipping: free over $75, flat $8 otherwise, free studio pickup.
	if ( ! WC_Shipping_Zones::get_zones() ) {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'United States' );
		$zone->set_zone_order( 1 );
		$zone->add_location( 'US', 'country' );
		$zone->save();
		$fid = $zone->add_shipping_method( 'free_shipping' );
		update_option( 'woocommerce_free_shipping_' . $fid . '_settings', [ 'title' => 'Free shipping', 'requires' => 'min_amount', 'min_amount' => '75', 'ignore_discounts' => 'no' ] );
		$rid = $zone->add_shipping_method( 'flat_rate' );
		update_option( 'woocommerce_flat_rate_' . $rid . '_settings', [ 'title' => 'Standard shipping', 'tax_status' => 'none', 'cost' => '8' ] );
		$lid = $zone->add_shipping_method( 'local_pickup' );
		update_option( 'woocommerce_local_pickup_' . $lid . '_settings', [ 'title' => 'Free pickup at the studio (Watertown)', 'tax_status' => 'none', 'cost' => '0' ] );
		$out['shipping'] = 'created';
	}
	update_option( 'woocommerce_enable_coupons', 'yes' );
	update_option( 'woocommerce_cart_redirect_after_add', 'no' );
	update_option( 'woocommerce_enable_ajax_add_to_cart', 'yes' );
	update_option( 'woocommerce_ship_to_countries', 'specific' );
	update_option( 'woocommerce_specific_ship_to_countries', [ 'US' ] );
	update_option( 'skynco_free_ship_min', 75 );
	return $out;
}

/** Product cards strip, rendered live by the [skynco_products] shortcode. */
function lux_shop_strip( $h2, $text, array $slugs = null, $variant = 'default' ) {
	$k     = lux_inner_kit();
	$slugs = $slugs ?: [ 'glow-kit', 'vitamin-c-brightening-serum', 'daily-mineral-spf-40', 'hydrating-hyaluronic-serum' ];
	return lux_section(
		'Shop: ' . wp_strip_all_tags( $h2 ),
		[ 'flex_gap' => lux_gap( 36 ) ] + ( 'tight' === $variant ? [ 'padding' => lux_box( 96, 24, 96, 24 ), 'padding_mobile' => lux_box( 56, 16, 56, 16 ) ] : [] ),
		[
			lux_head_row(
				[
					lux_eyebrow( 'Home care' ),
					lux_heading( $h2, 'h2', 'h2' ),
					lux_text( '<p>' . $text . '</p>', 'body', 'muted' ),
				],
				lux_button( 'Shop all', home_url( '/shop/' ), 'outline' ),
				680
			),
			lux_w( 'shortcode', [ 'shortcode' => '[skynco_products slugs="' . implode( ',', $slugs ) . '" columns="4"]' ], 'Product cards (live prices)' ),
		]
	);
}

/** /shop/ page. */
function lux_shop_elements() {
	$k   = lux_inner_kit();
	$col = [ 'flex_direction' => 'column' ];
	$els = [];

	$els[] = lux_photo_hero(
		'01 Hero',
		'Shop home care',
		'Studio results, <em>at home.</em>',
		'The cleansers, serums and SPF Hana uses and recommends, chosen to extend every facial. Free shipping over $75, or free pickup at the studio.',
		[ lux_button( 'Shop the kits', '#kits', 'lime' ), lux_button( 'Find your routine', '#routine', 'outline-light' ) ],
		'skynco-shop-hero',
		'shop'
	);

	// Offer bar.
	$offer = '<div class="sk-offers"><div><b>10% off your first order</b><span>Use code <code>GLOW10</code> at checkout</span></div><div><b>Free shipping over $75</b><span>Or free pickup in Watertown</span></div><div><b>Chosen by your esthetician</b><span>Only products Hana uses in the studio</span></div></div>';
	$els[] = lux_section( '02 Offers', [ 'padding' => lux_box( 0, 24, 0, 24 ), 'padding_mobile' => lux_box( 0, 16, 0, 16 ), 'margin' => lux_box( -40, 0, 0, 0 ), 'z_index' => 2 ], [ lux_w( 'html', [ 'html' => $offer ], 'Offers' ) ] );

	// Kits (bundle savings).
	$els[] = lux_section(
		'03 Kits',
		[ 'flex_gap' => lux_gap( 36 ), '_element_id' => 'kits' ],
		[
			lux_head_row(
				[ lux_eyebrow( 'Best value' ), lux_heading( 'Kits that do the thinking <em>for you.</em>', 'h2', 'h2' ), lux_text( '<p>Three-step routines built around your skin goal. Buy the set and save up to $19.</p>', 'body', 'muted' ) ],
				null,
				680
			),
			lux_w( 'shortcode', [ 'shortcode' => '[skynco_products category="kits" columns="3" style="feature"]' ], 'Kits' ),
		]
	);

	// Routine finder by concern.
	$concerns = [
		[ 'Breakouts', 'clear-skin-kit', 'Clear Skin Kit' ],
		[ 'Dullness and dark marks', 'glow-kit', 'Glow Kit' ],
		[ 'After a peel or microneedling', 'recovery-kit', 'Recovery Kit' ],
		[ 'Dry or sensitive skin', 'barrier-repair-moisturizer', 'Barrier Repair Moisturizer' ],
	];
	$list = '<ul class="lux-concerns">';
	foreach ( $concerns as $c ) {
		$list .= '<li><span>' . $c[0] . '</span><b><a href="' . home_url( '/product/' . $c[1] . '/' ) . '">' . $c[2] . ' →</a></b></li>';
	}
	$list .= '</ul>';
	$els[] = lux_section(
		'04 Routine finder',
		[ 'padding' => lux_box( 0, 24, 0, 24 ), 'padding_mobile' => lux_box( 0, 16, 0, 16 ), '_element_id' => 'routine' ],
		[
			lux_con(
				$k['card']( 'primary' ) + [ 'flex_direction' => 'row', 'flex_direction_tablet' => 'column', 'flex_gap' => lux_gap( 48 ), 'padding' => lux_box( 56 ), 'padding_mobile' => lux_box( 28, 22, 28, 22 ), 'border_radius' => lux_box( 32 ), 'flex_align_items' => 'center' ],
				[
					lux_con(
						$col + [ 'width' => lux_u( 42, '%' ), 'width_tablet' => lux_u( 100, '%' ), 'flex_gap' => lux_gap( 16 ), 'flex_align_items' => 'flex-start' ],
						[
							lux_eyebrow( 'Find your routine', 'dark' ),
							lux_heading( 'Start from your skin concern.', 'h2', 'h2', 'cream' ),
							lux_text( '<p>Not sure? Ask Hana at your next visit, or book the New Client Facial for a written home-care plan.</p>', 'body', 'misttext' ),
							lux_button( 'Book a skin consultation', $k['book'] . '?service=new-client-facial', 'lime' ),
						]
					),
					lux_con( $col + [ 'width' => lux_u( 52, '%' ), 'width_tablet' => lux_u( 100, '%' ), 'css_classes' => 'lux-concerns-dark' ], [ lux_text( $list, 'small', 'cream' ) ] ),
				],
				'Routine finder'
			),
		],
		1296
	);

	// Everything else in one grid, in routine order.
	$els[] = lux_section(
		'05 All products',
		[ 'flex_gap' => lux_gap( 36 ), '_element_id' => 'all' ],
		[
			lux_head_row(
				[ lux_eyebrow( 'Shop all' ), lux_heading( 'Cleanse, treat, <em>protect.</em>', 'h2', 'h2' ), lux_text( '<p>Every product is one Hana uses in the studio. Build your routine in that order: cleanser, serum, moisturizer, SPF.</p>', 'body', 'muted' ) ],
				null,
				680
			),
			lux_w( 'shortcode', [ 'shortcode' => '[skynco_products slugs="gentle-cleansing-gel,vitamin-c-brightening-serum,hydrating-hyaluronic-serum,barrier-repair-moisturizer,daily-mineral-spf-40,enzyme-exfoliating-mask,clarifying-spot-treatment,post-treatment-recovery-balm" columns="4"]' ], 'All products' ),
		]
	);

	// Gift card band.
	$els[] = lux_section(
		'06 Gift card',
		[ 'padding' => lux_box( 0, 24, 96, 24 ), 'padding_mobile' => lux_box( 0, 16, 64, 16 ) ],
		[
			lux_con(
				$k['card']( 'sagemist' ) + [ 'flex_direction' => 'row', 'flex_direction_tablet' => 'column', 'flex_justify_content' => 'space-between', 'flex_align_items' => 'center', 'flex_gap' => lux_gap( 24 ), 'padding' => lux_box( 40 ), 'padding_mobile' => lux_box( 28, 22, 28, 22 ), 'border_radius' => lux_box( 28 ) ],
				[
					lux_con(
						$col + [ 'flex_gap' => lux_gap( 8 ), 'width' => lux_u( 62, '%' ), 'width_tablet' => lux_u( 100, '%' ) ],
						[
							lux_heading( 'Not sure what they’d like? <em>Give a gift card.</em>', 'h2', [ 'f' => 'Fraunces', 's' => 30, 'w' => '400', 'lh' => 1.2 ] ),
							lux_text( '<p>Redeemable for any treatment or product, delivered by email and never expires.</p>', 'body', 'muted' ),
						]
					),
					lux_button( 'Buy a $100 gift card', home_url( '/product/skynco-gift-card/' ) ),
				],
				'Gift band'
			),
		]
	);

	$els[] = lux_page_cta(
		'Your best skin is a <em>routine.</em>',
		'Products work best alongside regular facials. Book your next visit and Hana will check in on your home care.',
		null,
		[ lux_button( 'Book Appointment', $k['services'], 'lime' ), lux_button( 'Gift card', home_url( '/product/skynco-gift-card/' ), 'outline-light' ) ]
	);
	return $els;
}
