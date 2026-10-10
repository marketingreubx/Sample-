<?php
/**
 * Skyn&Co. – retail shop: product catalogue, setup and page sections.
 * Runtime features (product cards shortcode, free-shipping bar, order bump) live in the skynco-studio plugin.
 */
if ( ! defined( 'ABSPATH' ) || function_exists( 'lux_shop_catalog' ) ) {
	return;
}

/**
 * Retail catalogue: key => [name, category, regular, sale, short, description, image, badge, upsells[], cross-sells[], kit contents[], url slug].
 * Keys are internal (recommendations, kits, checkout add-on); the url slug shows in the address bar.
 * Image: 'asset:<file>' = studio packshot in skynco-site/assets/products (official brand imagery on the Skyn&Co. backdrop).
 * Professional brands at their official US retail prices; the studio confirms its own line-up.
 */
function lux_shop_catalog() {
	return [
		'gentle-cleansing-gel'         => [ 'Dermalogica Special Cleansing Gel', 'cleansers', 49, 0, 'Soap-free foaming gel that deep cleans without stripping.', 'Dermalogica’s iconic everyday cleanser for all skin types. A gentle, soap-free gel with quillaja saponaria that lifts impurities and debris, while balm mint and lavender soothe and balance. Massage onto damp skin morning and night, then rinse. 8.4 fl oz / 250 mL.', 'asset:dermalogica-special-cleansing-gel-v2.jpg', 'Bestseller', [ 'glow-kit' ], [ 'vitamin-c-brightening-serum', 'daily-mineral-spf-40' ], [], 'dermalogica-special-cleansing-gel' ],
		'clarifying-spot-treatment'    => [ 'Image Skincare CLEAR CELL Clarifying Salicylic Gel Cleanser', 'cleansers', 41, 0, '2% salicylic acid gel cleanser for breakout-prone skin.', 'A clarifying gel cleanser formulated with 2% salicylic acid to gently exfoliate dead skin cells that can clog pores, with a soothing lather of mint, eucalyptus and tea tree oil. Leaves oily and breakout-prone skin fresh and clear. Use morning and night. 6 fl oz / 177 mL.', 'asset:image-clear-cell-salicylic-gel-cleanser-v2.jpg', '', [ 'clear-skin-kit' ], [ 'enzyme-exfoliating-mask', 'barrier-repair-moisturizer' ], [], 'image-skincare-clear-cell-salicylic-gel-cleanser' ],
		'vitamin-c-brightening-serum'  => [ 'Image Skincare VITAL C Hydrating Anti-Aging Serum', 'serums', 91, 0, 'Image’s #1 bestseller: brightens, hydrates and smooths.', 'A lightweight serum that pairs hyaluronic acid with a multi-vitamin C complex to brighten, tighten and smooth while locking in hydration. A cult favourite in treatment rooms for dull, dehydrated or sensitive skin. Apply in the morning before moisturizer and SPF. 1.7 fl oz / 50 mL.', 'asset:image-vital-c-hydrating-anti-aging-serum-v2.jpg', 'Hana’s pick', [ 'glow-kit' ], [ 'daily-mineral-spf-40', 'hydrating-hyaluronic-serum' ], [], 'image-skincare-vital-c-hydrating-anti-aging-serum' ],
		'hydrating-hyaluronic-serum'   => [ 'iS Clinical Hydra-Cool Serum', 'serums', 108, 0, 'Cooling hyaluronic serum that hydrates, calms and clarifies.', 'A refreshing, oil-free serum with hyaluronic acid and vitamin B5 plus purifying botanicals. Instantly cools and soothes, deeply hydrates and helps calm irritated or post-treatment skin. Ideal after microneedling, peels and facials. Apply morning and night before moisturizer. 1 fl oz / 30 mL.', 'asset:is-clinical-hydra-cool-serum-v2.jpg', '', [ 'recovery-kit' ], [ 'barrier-repair-moisturizer', 'daily-mineral-spf-40' ], [], 'is-clinical-hydra-cool-serum' ],
		'barrier-repair-moisturizer'   => [ 'Dermalogica Skin Smoothing Cream', 'moisturizers-spf', 49, 0, 'Delivers 48 hours of continuous hydration.', 'A best-selling moisturizer with Active HydraMesh Technology that infuses skin with 48 hours of continuous hydration, soothes and balances, and helps protect against environmental stress. Lightweight enough for every day, rich enough for dry skin. Use morning and night. 1.7 oz / 50 mL.', 'asset:dermalogica-skin-smoothing-cream-v2.jpg', '', [ 'clear-skin-kit' ], [ 'hydrating-hyaluronic-serum', 'daily-mineral-spf-40' ], [], 'dermalogica-skin-smoothing-cream' ],
		'daily-mineral-spf-40'         => [ 'EltaMD UV Clear Broad-Spectrum SPF 46', 'moisturizers-spf', 46, 0, 'The dermatologist favourite SPF for sensitive, acne-prone skin.', 'An oil-free, lightweight sunscreen with transparent zinc oxide and niacinamide that calms and protects skin prone to acne, rosacea and discoloration. Sheer, non-comedogenic and makeup-friendly. Daily SPF is the single best way to protect your facial results. Apply as the last step every morning. 1.7 oz / 48 g.', 'asset:eltamd-uv-clear-spf-46-v2.jpg', 'Bestseller', [ 'glow-kit', 'recovery-kit' ], [ 'vitamin-c-brightening-serum', 'barrier-repair-moisturizer' ], [], 'eltamd-uv-clear-broad-spectrum-spf-46' ],
		'enzyme-exfoliating-mask'      => [ 'Dermalogica Daily Microfoliant', 'masks-treatments', 69, 0, 'Rice-based powder exfoliant for brighter, smoother skin.', 'The cult rice-based powder that activates with water into a creamy foam, gently polishing away dead skin for a brighter, smoother complexion. Gentle enough for daily use and helps balance uneven skin tone. Work a half teaspoon with water into a paste, massage, then rinse. 2.6 oz / 74 g.', 'asset:dermalogica-daily-microfoliant-v2.jpg', 'Cult favourite', [ 'clear-skin-kit' ], [ 'hydrating-hyaluronic-serum', 'barrier-repair-moisturizer' ], [], 'dermalogica-daily-microfoliant' ],
		'post-treatment-recovery-balm' => [ 'Alastin Soothe + Protect Recovery Balm', 'masks-treatments', 52, 0, 'A thick, protective balm for skin after professional treatments.', 'Developed for use and application following skin-rejuvenating treatments such as microneedling, peels and lasers. This thick, moisturizing balm soothes, protects and comforts compromised skin while it recovers. Apply a generous layer as directed by your esthetician. 4 fl oz / 118 mL.', 'asset:alastin-soothe-protect-recovery-balm-v2.jpg', '', [ 'recovery-kit' ], [ 'daily-mineral-spf-40', 'hydrating-hyaluronic-serum' ], [], 'alastin-soothe-protect-recovery-balm' ],
		'glow-kit'                     => [ 'The Glow Kit', 'kits', 186, 159, 'Dermalogica cleanser, Image VITAL C serum and EltaMD SPF 46.', 'Everything for bright, protected skin every day: Dermalogica Special Cleansing Gel, Image Skincare VITAL C Hydrating Anti-Aging Serum and EltaMD UV Clear SPF 46. Bought together, you save $27.', 'asset:kit-glow-kit.jpg', 'Save $27', [], [ 'enzyme-exfoliating-mask', 'skynco-gift-card' ], [ 'gentle-cleansing-gel', 'vitamin-c-brightening-serum', 'daily-mineral-spf-40' ], 'glow-kit' ],
		'clear-skin-kit'               => [ 'The Clear Skin Kit', 'kits', 159, 135, 'CLEAR CELL cleanser, Daily Microfoliant and Skin Smoothing Cream.', 'A professional routine for breakout-prone skin that clears without stripping: Image Skincare CLEAR CELL Salicylic Gel Cleanser, Dermalogica Daily Microfoliant and Dermalogica Skin Smoothing Cream. Save $24.', 'asset:kit-clear-skin-kit.jpg', 'Save $24', [], [ 'daily-mineral-spf-40', 'hydrating-hyaluronic-serum' ], [ 'clarifying-spot-treatment', 'enzyme-exfoliating-mask', 'barrier-repair-moisturizer' ], 'clear-skin-kit' ],
		'recovery-kit'                 => [ 'The Recovery Kit', 'kits', 206, 175, 'Alastin balm, iS Clinical Hydra-Cool and EltaMD SPF 46.', 'Recommended after microneedling, dermaplaning and chemical peels: Alastin Soothe + Protect Recovery Balm, iS Clinical Hydra-Cool Serum and EltaMD UV Clear SPF 46. Save $31.', 'asset:kit-recovery-kit.jpg', 'Save $31', [], [ 'barrier-repair-moisturizer', 'skynco-gift-card' ], [ 'post-treatment-recovery-balm', 'hydrating-hyaluronic-serum', 'daily-mineral-spf-40' ], 'recovery-kit' ],
		'skynco-gift-card'             => [ 'Skyn&Co. E-Gift Card', 'gift-cards', 100, 0, 'Use it for any treatment or product. Delivered by email.', 'The easiest gift for anyone who deserves a little time for themselves. Redeemable for any Skyn&Co. treatment or product, delivered by email and never expires.', 8101512, 'Gift idea', [], [ 'glow-kit' ], [], 'skynco-gift-card' ],
	];
}

/** Studio packshot from the repo (skynco-site/assets/products), sideloaded once and cached by file name. */
function lux_shop_asset_image( $file, $title ) {
	$cache = get_option( 'skynco_asset_media', [] );
	if ( ! empty( $cache[ $file ] ) && get_post( $cache[ $file ] ) ) {
		return (int) $cache[ $file ];
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = download_url( 'https://raw.githubusercontent.com/marketingreubx/Sample-/' . ( defined( 'SKYNCO_ASSET_REF' ) ? SKYNCO_ASSET_REF : 'claude/wizardly-noether-iac7ie' ) . '/skynco-site/assets/products/' . rawurlencode( $file ), 60 );
	if ( is_wp_error( $tmp ) ) {
		return 0;
	}
	$id = media_handle_sideload( [ 'name' => 'skynco-' . $file, 'tmp_name' => $tmp ], 0, $title );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp );
		return 0;
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $title );
	$cache[ $file ] = (int) $id;
	update_option( 'skynco_asset_media', $cache, false );
	return (int) $id;
}

/** Product photo from Open Beauty Facts, squared on white (pad) or cropped to a square (crop). Cached by barcode. */
function lux_shop_obf_image( $code, $mode, $title ) {
	$cache = get_option( 'skynco_obf_media_v2', [] );
	if ( ! empty( $cache[ $code ] ) && get_post( $cache[ $code ] ) ) {
		return (int) $cache[ $code ];
	}
	$api = json_decode( wp_remote_retrieve_body( wp_remote_get( 'https://world.openbeautyfacts.org/api/v2/product/' . rawurlencode( $code ) . '.json?fields=image_front_url', [ 'timeout' => 30, 'user-agent' => 'SkyncoStudio/1.0' ] ) ), true );
	$url = $api['product']['image_front_url'] ?? '';
	if ( ! $url ) {
		return 0;
	}
	$im = @imagecreatefromstring( wp_remote_retrieve_body( wp_remote_get( str_replace( '.400.jpg', '.full.jpg', $url ), [ 'timeout' => 60 ] ) ) );
	if ( ! $im ) {
		return 0;
	}
	$w = imagesx( $im );
	$h = imagesy( $im );
	$S = 1200;
	$c = imagecreatetruecolor( $S, $S );
	imagefill( $c, 0, 0, imagecolorallocate( $c, 255, 255, 255 ) );
	if ( 'crop' === $mode ) {
		$side = min( $w, $h );
		imagecopyresampled( $c, $im, 0, 0, (int) ( ( $w - $side ) / 2 ), (int) ( ( $h - $side ) / 2 ), $S, $S, $side, $side );
	} else {
		[ $im, $w, $h ] = lux_shop_trim( $im );
		$sc = min( $S * 0.9 / $w, $S * 0.9 / $h );
		$nw = (int) ( $w * $sc );
		$nh = (int) ( $h * $sc );
		imagecopyresampled( $c, $im, (int) ( ( $S - $nw ) / 2 ), (int) ( ( $S - $nh ) / 2 ), 0, 0, $nw, $nh, $w, $h );
	}
	$id = lux_shop_save_image( $c, $title );
	if ( $id ) {
		$cache[ $code ] = $id;
		update_option( 'skynco_obf_media_v2', $cache, false );
	}
	return $id;
}

/** Crop away a plain border (white or the photo's corner colour) around the product. */
function lux_shop_trim( $im ) {
	$w  = imagesx( $im );
	$h  = imagesy( $im );
	$bg = imagecolorsforindex( $im, imagecolorat( $im, 2, 2 ) );
	$st = max( 1, (int) ( min( $w, $h ) / 300 ) );
	$x0 = $w;
	$y0 = $h;
	$x1 = 0;
	$y1 = 0;
	for ( $y = 0; $y < $h; $y += $st ) {
		for ( $x = 0; $x < $w; $x += $st ) {
			$c = imagecolorsforindex( $im, imagecolorat( $im, $x, $y ) );
			if ( abs( $c['red'] - $bg['red'] ) + abs( $c['green'] - $bg['green'] ) + abs( $c['blue'] - $bg['blue'] ) > 42 ) {
				$x0 = min( $x0, $x );
				$y0 = min( $y0, $y );
				$x1 = max( $x1, $x );
				$y1 = max( $y1, $y );
			}
		}
	}
	if ( $x1 <= $x0 || $y1 <= $y0 || ( $x1 - $x0 ) < $w * 0.2 ) {
		return [ $im, $w, $h ];
	}
	$cw = $x1 - $x0 + 1;
	$ch = $y1 - $y0 + 1;
	$c  = imagecreatetruecolor( $cw, $ch );
	imagecopy( $c, $im, 0, 0, $x0, $y0, $cw, $ch );
	return [ $c, $cw, $ch ];
}

/** A brand font file, downloaded once into uploads. */
function lux_shop_font( $family ) {
	$dir  = wp_get_upload_dir()['basedir'] . '/skynco-fonts';
	$file = $dir . '/' . sanitize_file_name( $family ) . '.ttf';
	if ( file_exists( $file ) ) {
		return $file;
	}
	wp_mkdir_p( $dir );
	$css = wp_remote_retrieve_body( wp_remote_get( 'https://fonts.googleapis.com/css2?family=' . rawurlencode( $family ), [ 'timeout' => 20, 'user-agent' => 'Mozilla/4.0' ] ) );
	if ( preg_match( '#https://fonts\.gstatic\.com[^)]+#', $css, $m ) ) {
		file_put_contents( $file, wp_remote_retrieve_body( wp_remote_get( $m[0], [ 'timeout' => 30 ] ) ) );
	}
	return file_exists( $file ) ? $file : '';
}

/** Kit image: a 2x2 grid of the kit's three product photos plus a plum tile with the kit name and saving. */
function lux_shop_kit_image( $key, array $image_ids, $title, $badge = '' ) {
	$S   = 1200;
	$g   = 36;
	$t   = (int) ( ( $S - 3 * $g ) / 2 );
	$c   = imagecreatetruecolor( $S, $S );
	imagefill( $c, 0, 0, imagecolorallocate( $c, 251, 239, 240 ) );
	$pos = [ [ $g, $g ], [ 2 * $g + $t, $g ], [ $g, 2 * $g + $t ] ];
	foreach ( array_slice( array_values( $image_ids ), 0, 3 ) as $i => $aid ) {
		$im = @imagecreatefromstring( (string) @file_get_contents( get_attached_file( $aid ) ) );
		if ( $im ) {
			imagecopyresampled( $c, $im, $pos[ $i ][0], $pos[ $i ][1], 0, 0, $t, $t, imagesx( $im ), imagesy( $im ) );
		}
	}
	$x = 2 * $g + $t;
	$y = 2 * $g + $t;
	imagefilledrectangle( $c, $x, $y, $x + $t, $y + $t, imagecolorallocate( $c, 59, 21, 48 ) );
	$serif = lux_shop_font( 'Fraunces:ital,wght@1,500' );
	$sans  = lux_shop_font( 'Manrope:wght@700' );
	$white = imagecolorallocate( $c, 255, 255, 255 );
	$pink  = imagecolorallocate( $c, 255, 179, 217 );
	if ( $serif && $sans ) {
		imagettftext( $c, 22, 0, $x + 46, $y + 90, $pink, $sans, 'SKYN&CO. KIT' );
		$words = preg_split( '/\s+/', trim( preg_replace( '/^The\s+/i', '', $title ) ) );
		$lines = count( $words ) > 2 ? [ implode( ' ', array_slice( $words, 0, 2 ) ), implode( ' ', array_slice( $words, 2 ) ) ] : [ implode( ' ', $words ) ];
		foreach ( $lines as $n => $ln ) {
			imagettftext( $c, 62, 0, $x + 44, $y + 210 + $n * 84, $white, $serif, $ln );
		}
		imagettftext( $c, 24, 0, $x + 46, $y + $t - 110, $white, $sans, '3-step routine' );
		if ( $badge ) {
			imagettftext( $c, 24, 0, $x + 46, $y + $t - 60, $pink, $sans, $badge );
		}
	}
	return lux_shop_save_image( $c, $title, 'kit-' . $key . '-' . substr( md5( implode( ',', $image_ids ) . $badge ), 0, 6 ) );
}

function lux_shop_save_image( $gd, $title, $name = '' ) {
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = wp_tempnam( 'skynco-product' );
	imagejpeg( $gd, $tmp, 88 );
	$id = media_handle_sideload( [ 'name' => 'skynco-product-' . sanitize_title( $name ?: $title ) . '.jpg', 'tmp_name' => $tmp ], 0, $title );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp );
		return 0;
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $title );
	return (int) $id;
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
		$product->set_slug( $p[11] ?? $slug );
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
		if ( is_string( $p[6] ) && 0 === strpos( $p[6], 'asset:' ) ) {
			$img = lux_shop_asset_image( substr( $p[6], 6 ), $p[0] );
		} elseif ( 'kit' === $p[6] ) {
			$parts = array_filter( array_map( fn( $k ) => isset( $ids[ $k ] ) ? (int) get_post_thumbnail_id( $ids[ $k ] ) : 0, $p[10] ) );
			$img   = $parts ? lux_shop_kit_image( $slug, $parts, $p[0], $p[7] ) : 0;
		} elseif ( is_string( $p[6] ) && 0 === strpos( $p[6], 'obf:' ) ) {
			[ , $code, $mode ] = array_pad( explode( ':', $p[6] ), 3, 'pad' );
			$img = lux_shop_obf_image( $code, $mode, $p[0] );
		} else {
			$img = lux_shop_image( $p[6], $p[0] );
		}
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
				[ lux_eyebrow( 'Best value' ), lux_heading( 'Kits that do the thinking <em>for you.</em>', 'h2', 'h2' ), lux_text( '<p>Three-step routines built around your skin goal. Buy the set and save up to $31.</p>', 'body', 'muted' ) ],
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
			lux_text( '<p>Product names, packaging and imagery belong to their respective brands.</p>', [ 'f' => 'Manrope', 's' => 12, 'w' => '500', 'lh' => 1.5 ], 'muted', [ 'align' => 'center' ] ),
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
