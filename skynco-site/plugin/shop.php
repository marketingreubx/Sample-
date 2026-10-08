<?php
/**
 * Skyn&Co. Studio – retail shop features: product cards, free-shipping progress,
 * checkout order bump, treatment recommendations and post-purchase rebooking.
 */
defined( 'ABSPATH' ) || exit;

/** Product id for a catalogue slug. */
function skynco_shop_id( $slug ) {
	$ids = get_option( 'skynco_shop_product_ids', [] );
	return isset( $ids[ $slug ] ) ? (int) $ids[ $slug ] : 0;
}

function skynco_free_ship_min() {
	return (float) get_option( 'skynco_free_ship_min', 75 );
}

/* ---------------------------------------------------------------------------
 * [skynco_products slugs="a,b" category="kits" columns="4" style="feature"]
 * ------------------------------------------------------------------------- */
add_shortcode(
	'skynco_products',
	function ( $atts ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}
		$a = shortcode_atts( [ 'slugs' => '', 'category' => '', 'columns' => 4, 'style' => '' ], $atts );
		$products = [];
		if ( $a['slugs'] ) {
			foreach ( array_map( 'trim', explode( ',', $a['slugs'] ) ) as $slug ) {
				$p = wc_get_product( skynco_shop_id( $slug ) );
				if ( $p && 'publish' === $p->get_status() ) {
					$products[] = $p;
				}
			}
		} elseif ( $a['category'] ) {
			$products = wc_get_products( [ 'category' => [ $a['category'] ], 'status' => 'publish', 'limit' => 12, 'orderby' => 'menu_order', 'order' => 'ASC' ] );
		}
		if ( ! $products ) {
			return '';
		}
		$html = '<div class="sk-grid sk-cols-' . (int) $a['columns'] . ( 'feature' === $a['style'] ? ' sk-grid--feature' : '' ) . '">';
		foreach ( $products as $p ) {
			$html .= skynco_product_card( $p, 'feature' === $a['style'] );
		}
		return $html . '</div>';
	}
);

function skynco_product_card( WC_Product $p, $feature = false ) {
	$link  = get_permalink( $p->get_id() );
	$badge = (string) get_post_meta( $p->get_id(), '_skynco_badge', true );
	$add   = add_query_arg( 'add-to-cart', $p->get_id(), wc_get_cart_url() );
	$img   = $p->get_image_id() ? wp_get_attachment_image( $p->get_image_id(), 'woocommerce_thumbnail', false, [ 'loading' => 'lazy', 'alt' => $p->get_name() ] ) : wc_placeholder_img();
	$kit   = (array) get_post_meta( $p->get_id(), '_skynco_kit', true );
	$extra = '';
	if ( $feature && $kit ) {
		$extra = '<ul class="sk-card__kit">';
		foreach ( $kit as $slug ) {
			$item = wc_get_product( skynco_shop_id( $slug ) );
			if ( $item ) {
				$extra .= '<li>' . esc_html( $item->get_name() ) . '</li>';
			}
		}
		$extra .= '</ul>';
	}
	return '<article class="sk-card' . ( $feature ? ' sk-card--feature' : '' ) . '">'
		. '<a class="sk-card__img" href="' . esc_url( $link ) . '">' . $img . ( $badge ? '<span class="sk-badge">' . esc_html( $badge ) . '</span>' : '' ) . '</a>'
		. '<div class="sk-card__body">'
		. '<h3 class="sk-card__title"><a href="' . esc_url( $link ) . '">' . esc_html( $p->get_name() ) . '</a></h3>'
		. '<p class="sk-card__short">' . esc_html( wp_strip_all_tags( $p->get_short_description() ) ) . '</p>'
		. $extra
		. '<div class="sk-card__foot"><span class="sk-price">' . $p->get_price_html() . '</span>'
		. '<a class="sk-add" href="' . esc_url( $add ) . '" rel="nofollow" aria-label="' . esc_attr( 'Add ' . $p->get_name() . ' to bag' ) . '">Add to bag</a></div>'
		. '</div></article>';
}

/* ---------------------------------------------------------------------------
 * Free-shipping progress bar (cart, checkout, mini cart).
 * ------------------------------------------------------------------------- */
function skynco_free_ship_bar() {
	if ( ! WC()->cart || WC()->cart->is_empty() || ! WC()->cart->needs_shipping() ) {
		return;
	}
	$min   = skynco_free_ship_min();
	$total = (float) WC()->cart->get_displayed_subtotal() - (float) WC()->cart->get_discount_total();
	$pct   = min( 100, round( $total / $min * 100 ) );
	$msg   = $total >= $min
		? 'You’ve unlocked <b>free shipping</b>.'
		: 'You’re <b>' . wc_price( $min - $total ) . '</b> away from free shipping.';
	echo '<div class="sk-ship"><p>' . wp_kses_post( $msg ) . '</p><div class="sk-ship__bar"><span style="width:' . (int) $pct . '%"></span></div></div>';
}
add_action( 'woocommerce_before_cart', 'skynco_free_ship_bar', 5 );
add_action( 'woocommerce_before_checkout_form', 'skynco_free_ship_bar', 5 );
add_action( 'woocommerce_widget_shopping_cart_before_buttons', 'skynco_free_ship_bar' );

add_action(
	'woocommerce_after_add_to_cart_form',
	function () {
		echo '<ul class="sk-assure"><li>Free shipping over ' . wp_kses_post( wc_price( skynco_free_ship_min() ) ) . ', or free studio pickup</li><li>10% off your first order with code <b>GLOW10</b></li><li>Chosen and used by Hana in the studio</li></ul>';
	}
);

/* ---------------------------------------------------------------------------
 * Checkout order bump: Recovery Balm at 25% off, one click.
 * ------------------------------------------------------------------------- */
const SKYNCO_BUMP_SLUG = 'post-treatment-recovery-balm';
const SKYNCO_BUMP_OFF  = 0.25;

function skynco_bump_key() {
	$pid = skynco_shop_id( SKYNCO_BUMP_SLUG );
	foreach ( WC()->cart->get_cart() as $key => $item ) {
		if ( (int) $item['product_id'] === $pid ) {
			return [ $key, ! empty( $item['skynco_bump'] ) ];
		}
	}
	return [ null, false ];
}

add_action(
	'woocommerce_review_order_before_submit',
	function () {
		$p = wc_get_product( skynco_shop_id( SKYNCO_BUMP_SLUG ) );
		if ( ! $p ) {
			return;
		}
		list( $key, $is_bump ) = skynco_bump_key();
		if ( $key && ! $is_bump ) {
			return; // Already bought at full price.
		}
		$price = (float) $p->get_regular_price();
		echo '<label class="sk-bump"><input type="checkbox" id="sk-bump" ' . checked( (bool) $key, true, false ) . '> '
			. '<span><b>Yes, add the Post-Treatment Recovery Balm for ' . wp_kses_post( wc_price( $price * ( 1 - SKYNCO_BUMP_OFF ) ) ) . '</b> <s>' . wp_kses_post( wc_price( $price ) ) . '</s>'
			. '<em>Calms and protects skin after peels, microneedling and waxing. Checkout-only price.</em></span></label>';
	}
);

add_action(
	'wp_ajax_skynco_bump',
	'skynco_ajax_bump'
);
add_action( 'wp_ajax_nopriv_skynco_bump', 'skynco_ajax_bump' );
function skynco_ajax_bump() {
	check_ajax_referer( 'skynco_bump' );
	list( $key ) = skynco_bump_key();
	if ( ! empty( $_POST['on'] ) ) {
		if ( ! $key ) {
			WC()->cart->add_to_cart( skynco_shop_id( SKYNCO_BUMP_SLUG ), 1, 0, [], [ 'skynco_bump' => 1 ] );
		}
	} elseif ( $key ) {
		WC()->cart->remove_cart_item( $key );
	}
	wp_send_json_success();
}

add_action(
	'woocommerce_before_calculate_totals',
	function ( $cart ) {
		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item['skynco_bump'] ) ) {
				$item['data']->set_price( (float) $item['data']->get_regular_price() * ( 1 - SKYNCO_BUMP_OFF ) );
			}
		}
	},
	20
);
add_filter(
	'woocommerce_cart_item_quantity',
	function ( $qty, $key, $item ) {
		return empty( $item['skynco_bump'] ) ? $qty : '1';
	},
	10,
	3
);

add_action(
	'wp_footer',
	function () {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		$url   = admin_url( 'admin-ajax.php' );
		$nonce = wp_create_nonce( 'skynco_bump' );
		echo "<script>jQuery(function($){\$(document.body).on('change','#sk-bump',function(){var c=this;\$(c).prop('disabled',true);\$.post('" . esc_url( $url ) . "',{action:'skynco_bump',_ajax_nonce:'" . esc_js( $nonce ) . "',on:c.checked?1:''}).always(function(){\$(document.body).trigger('update_checkout');});});});</script>";
	}
);

/* ---------------------------------------------------------------------------
 * Product page: treatments it pairs with, and what is inside a kit.
 * ------------------------------------------------------------------------- */
add_action(
	'woocommerce_single_product_summary',
	function () {
		global $product;
		$kit = (array) get_post_meta( $product->get_id(), '_skynco_kit', true );
		if ( $kit ) {
			$sum   = 0;
			$items = '';
			foreach ( $kit as $slug ) {
				$item = wc_get_product( skynco_shop_id( $slug ) );
				if ( $item ) {
					$sum   += (float) $item->get_regular_price();
					$items .= '<li><a href="' . esc_url( get_permalink( $item->get_id() ) ) . '">' . esc_html( $item->get_name() ) . '</a> <span>' . wp_kses_post( wc_price( $item->get_regular_price() ) ) . '</span></li>';
				}
			}
			echo '<div class="sk-kit"><p><b>In this kit</b> (worth ' . wp_kses_post( wc_price( $sum ) ) . ')</p><ul>' . $items . '</ul></div>'; // phpcs:ignore
		}
		$map = get_option( 'skynco_product_treatments', [] );
		$ts  = $map[ $product->get_id() ] ?? [];
		if ( $ts && function_exists( 'lux_lp_treatments' ) ) {
			$names = wp_list_pluck( array_map( fn( $t ) => [ 'slug' => $t[0], 'name' => $t[1] ], lux_lp_treatments() ), 'name', 'slug' );
			$links = [];
			foreach ( array_slice( array_unique( $ts ), 0, 4 ) as $slug ) {
				$links[] = '<a href="' . esc_url( home_url( '/services/' . $slug . '/' ) ) . '">' . esc_html( $names[ $slug ] ?? $slug ) . '</a>';
			}
			echo '<p class="sk-pairs"><b>Recommended after:</b> ' . implode( ', ', $links ) . '</p>'; // phpcs:ignore
		}
	},
	25
);

/* ---------------------------------------------------------------------------
 * Thank-you page: invite the client to book (the next sale).
 * ------------------------------------------------------------------------- */
add_action(
	'woocommerce_thankyou',
	function () {
		echo '<div class="sk-next"><h2>Get the most from your new routine</h2><p>Products work best alongside regular facials. Book your next visit and Hana will check in on how your skin is responding.</p><p><a class="button" href="' . esc_url( home_url( '/book/' ) ) . '">Book your next facial</a></p></div>';
	},
	5
);

/* ---------------------------------------------------------------------------
 * The /shop/ page is an Elementor page; point Woo's "shop" links at it.
 * ------------------------------------------------------------------------- */
add_filter( 'woocommerce_return_to_shop_redirect', fn() => home_url( '/shop/' ) );
add_filter( 'woocommerce_continue_shopping_redirect', fn() => home_url( '/shop/' ) );
add_filter( 'woocommerce_get_shop_page_permalink', fn() => home_url( '/shop/' ) );
add_filter( 'woocommerce_cross_sells_columns', fn() => 3 );
add_filter( 'woocommerce_cross_sells_total', fn() => 3 );
add_filter( 'woocommerce_upsell_display_args', fn( $a ) => array_merge( $a, [ 'posts_per_page' => 3, 'columns' => 3 ] ) );
add_filter( 'woocommerce_product_cross_sells_products_heading', fn() => 'Complete your routine' );
add_filter( 'woocommerce_product_upsells_products_heading', fn() => 'Save with a kit' );

/* Hide service products (used by the POS) from every shop listing and search. */
add_action(
	'woocommerce_product_query',
	function ( $q ) {
		$tax   = (array) $q->get( 'tax_query' );
		$tax[] = [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => [ 'treatments' ], 'operator' => 'NOT IN' ];
		$q->set( 'tax_query', $tax );
	}
);

/* ---------------------------------------------------------------------------
 * Styles for cards and shop extras.
 * ------------------------------------------------------------------------- */
add_action(
	'wp_head',
	function () {
		?>
<style id="skynco-shop">
.sk-grid{display:grid;gap:16px;grid-template-columns:repeat(4,minmax(0,1fr))}
.sk-cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}
.sk-cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}
@media(max-width:1024px){.sk-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.sk-grid{grid-template-columns:minmax(0,1fr)}.sk-grid:not(.sk-grid--feature){grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}}
.sk-card{display:flex;flex-direction:column;background:#fff;border:1px solid #EADDE0;border-radius:24px;overflow:hidden;transition:transform .25s ease,box-shadow .25s ease}
.sk-card:hover{transform:translateY(-3px);box-shadow:0 18px 40px rgba(59,21,48,.10)}
.sk-card__img{position:relative;display:block;aspect-ratio:1/1;background:#FBEFF0;overflow:hidden}
.sk-card__img img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .5s ease}
.sk-card:hover .sk-card__img img{transform:scale(1.04)}
.sk-badge{position:absolute;top:12px;left:12px;background:#3B1530;color:#FFF8F6;font:700 12px/1 Manrope,sans-serif;letter-spacing:.04em;padding:8px 12px;border-radius:999px}
.sk-card__body{display:flex;flex-direction:column;gap:8px;padding:18px 18px 20px;flex:1}
.sk-card__title{margin:0;font:400 20px/1.25 Fraunces,serif}
.sk-card__title a{color:#3B1530;text-decoration:none}
.sk-card__short{margin:0;color:#6E5A66;font:400 14px/21px Manrope,sans-serif}
.sk-card__kit{margin:4px 0 0;padding:0;list-style:none;font:500 14px/1.5 Manrope,sans-serif;color:#2A1A24}
.sk-card__kit li::before{content:"✓ ";color:#D1127E}
.sk-card__foot{margin-top:auto;padding-top:10px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.sk-price{font:700 17px/1.2 Manrope,sans-serif;color:#2A1A24}
.sk-price del{color:#9A8791;font-weight:500;margin-right:4px}
.sk-price ins{text-decoration:none;color:#D1127E}
.sk-add{display:inline-flex;align-items:center;padding:11px 18px;border-radius:999px;background:#D1127E;color:#fff!important;font:600 14px/1 Manrope,sans-serif;text-decoration:none;transition:background .2s}
.sk-add:hover{background:#B00E6A}
.sk-card--feature .sk-card__title{font-size:24px}
@media(max-width:560px){.sk-grid:not(.sk-grid--feature) .sk-card__short{display:none}.sk-grid:not(.sk-grid--feature) .sk-add{width:100%;justify-content:center}.sk-card__title{font-size:17px}}
.sk-offers{display:grid;grid-template-columns:repeat(3,1fr);gap:0;background:#fff;border:1px solid #EADDE0;border-radius:24px;box-shadow:0 24px 60px rgba(59,21,48,.10);overflow:hidden}
.sk-offers>div{padding:22px 26px;display:flex;flex-direction:column;gap:4px;border-right:1px solid #EADDE0}
.sk-offers>div:last-child{border-right:0}
.sk-offers b{font:600 16px/1.3 Manrope,sans-serif;color:#3B1530}
.sk-offers span{font:400 14px/1.4 Manrope,sans-serif;color:#6E5A66}
.sk-offers code{background:#FBEFF0;color:#D1127E;padding:2px 6px;border-radius:6px;font-weight:700}
@media(max-width:767px){.sk-offers{grid-template-columns:1fr}.sk-offers>div{border-right:0;border-bottom:1px solid #EADDE0}}
.sk-ship{background:#FBEFF0;border-radius:16px;padding:14px 18px;margin:0 0 20px}
.sk-ship p{margin:0 0 8px;font:500 15px/1.4 Manrope,sans-serif;color:#2A1A24}
.sk-ship__bar{height:8px;border-radius:99px;background:#F6DDE1;overflow:hidden}
.sk-ship__bar span{display:block;height:100%;background:linear-gradient(90deg,#D1127E,#FF8CC8);border-radius:99px;transition:width .6s ease}
.sk-bump{display:flex;gap:12px;align-items:flex-start;border:2px dashed #D1127E;background:#FFF8F6;border-radius:16px;padding:16px;margin:0 0 18px;cursor:pointer}
.sk-bump input{margin-top:4px;width:18px;height:18px;accent-color:#D1127E}
.sk-bump>span{display:flex;flex-direction:column;gap:4px;font:400 14px/1.45 Manrope,sans-serif;color:#2A1A24}
.sk-bump s{color:#9A8791}
.sk-bump em{font-style:normal;color:#6E5A66}
.sk-assure{list-style:none;margin:18px 0 0;padding:16px 18px;background:#FBEFF0;border-radius:16px;font:500 14px/1.6 Manrope,sans-serif;color:#2A1A24}
.sk-assure li::before{content:"✓ ";color:#D1127E;font-weight:700}
.sk-kit{margin:14px 0;padding:16px 18px;border:1px solid #EADDE0;border-radius:16px}
.sk-kit p{margin:0 0 8px}
.sk-kit ul{margin:0;padding:0;list-style:none}
.sk-kit li{display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-top:1px dotted #EADDE0}
.sk-pairs{font:400 14px/1.5 Manrope,sans-serif;color:#6E5A66}
.sk-pairs a{color:#D1127E}
.sk-next{margin:0 0 28px;padding:28px;border-radius:24px;background:#3B1530;color:#F3DCE8}
.sk-next h2{color:#FFF8F6;font-family:Fraunces,serif;font-weight:400;margin:0 0 8px}
.sk-next .button{background:#D1127E!important;color:#fff!important;border-radius:999px!important;padding:14px 24px!important}
.woocommerce div.product .product_title{font-size:clamp(32px,4vw,44px)!important;line-height:1.1!important;margin-bottom:10px}
.woocommerce div.product p.price{font-size:22px!important;font-weight:700}
.woocommerce div.product p.price del{color:#9A8791;font-weight:500}
.woocommerce div.product p.price ins{text-decoration:none}
.woocommerce-message,.woocommerce-info{border-top-color:#D1127E!important;background:#FBEFF0!important;border-radius:14px}
.woocommerce-message::before,.woocommerce-info::before{color:#D1127E!important}
.woocommerce .woocommerce-message .button,.woocommerce .woocommerce-info .button{background:#3B1530!important;color:#fff!important;border-radius:999px!important}
.woocommerce div.product .woocommerce-tabs ul.tabs li.active a{color:#3B1530}
.woocommerce .cart-collaterals h2,.woocommerce-checkout h3,.woocommerce .cross-sells h2,.woocommerce .upsells h2,.woocommerce .related h2{font-family:Fraunces,serif;font-weight:400;color:#3B1530}
.woocommerce ul.products li.product .button{display:inline-flex!important;align-items:center;white-space:nowrap;padding:11px 18px!important;font-size:14px!important;line-height:1!important;margin-top:10px!important}
.woocommerce ul.products li.product .price{display:block;margin:4px 0 0;color:#2A1A24;font-weight:700}
.woocommerce ul.products li.product .price ins{color:#D1127E;text-decoration:none}
.woocommerce ul.products li.product img{border-radius:20px}
.woocommerce ul.products li.product .woocommerce-loop-product__title{font-family:Fraunces,serif!important;font-weight:400!important;font-size:19px!important;color:#3B1530}
.woocommerce ul.products li.product .button,.woocommerce .single_add_to_cart_button,.woocommerce button.button.alt,.woocommerce a.button.alt,.woocommerce #respond input#submit.alt{background:#D1127E;border-radius:999px}
.woocommerce button.button.alt:hover,.woocommerce a.button.alt:hover{background:#B00E6A}
.woocommerce span.onsale{background:#3B1530;border-radius:999px;min-height:auto;line-height:1;padding:8px 12px}
.woocommerce div.product p.price,.woocommerce div.product span.price{color:#D1127E}
.woocommerce div.product .product_title{font-family:Fraunces,serif;font-weight:500;color:#3B1530}
</style>
		<?php
	}
);

/* Free /shop/ for the designed page: the product archive lives at /all-products/. */
add_filter( 'woocommerce_register_post_type_product', fn( $a ) => array_merge( $a, [ 'has_archive' => 'all-products' ] ) );

/* POS-only payment methods never appear on the public checkout. */
add_filter(
	'woocommerce_available_payment_gateways',
	function ( $gateways ) {
		if ( ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			unset( $gateways['wepos_cash'], $gateways['skynco_pos_card'], $gateways['skynco_pos_venmo'] );
		}
		return $gateways;
	},
	99
);

/* Old URL from the first build. */
add_action(
	'template_redirect',
	function () {
		if ( is_404() && false !== strpos( (string) $_SERVER['REQUEST_URI'], '/booking-conditions' ) ) {
			wp_safe_redirect( home_url( '/policies/' ), 301 );
			exit;
		}
	}
);
