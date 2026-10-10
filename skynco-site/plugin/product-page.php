<?php
/**
 * Single product page, designed for phones first: brand label, quantity stepper and
 * a full-width "Add to bag" button, tidy details, product grids that match the shop,
 * and a sticky buy bar on mobile.
 *
 * @package SkyncoStudio
 */

defined( 'ABSPATH' ) || exit;

/* Brand label above the title. */
add_action(
	'woocommerce_single_product_summary',
	function () {
		global $product;
		if ( $product && function_exists( 'skynco_brand_of' ) ) {
			[ $brand ] = skynco_brand_of( $product->get_name() );
			echo '<p class="skp-brand">' . esc_html( $brand ) . '</p>';
		}
	},
	4
);

/* "Add to cart" → "Add to bag · $91". */
add_filter(
	'woocommerce_product_single_add_to_cart_text',
	function ( $text, $product ) {
		return $product && $product->get_price() ? 'Add to bag · ' . html_entity_decode( wp_strip_all_tags( wc_price( $product->get_price() ) ) ) : 'Add to bag';
	},
	10,
	2
);
add_filter( 'woocommerce_product_add_to_cart_text', fn( $t ) => 'Add to bag' );

/* Quantity stepper buttons around the number field. */
add_action( 'woocommerce_before_quantity_input_field', fn() => is_product() ? print( '<button type="button" class="skp-step" data-d="-1" aria-label="Decrease quantity">−</button>' ) : null );
add_action( 'woocommerce_after_quantity_input_field', fn() => is_product() ? print( '<button type="button" class="skp-step" data-d="1" aria-label="Increase quantity">+</button>' ) : null );

/* No SKU / category line, no tabs: the details get their own section. */
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
add_filter( 'woocommerce_product_tabs', '__return_empty_array', 99 );

add_action(
	'woocommerce_after_single_product_summary',
	function () {
		global $product;
		$desc = trim( (string) $product->get_description() );
		if ( '' === $desc ) {
			return;
		}
		$how = '';
		if ( preg_match( '/(Apply[^.]*\.|Use[^.]*\.|Massage[^.]*\.)/i', wp_strip_all_tags( $desc ), $m ) ) {
			$how = $m[1];
		}
		$size = preg_match( '/(\d+(?:\.\d+)?\s?(?:fl )?oz[^.]*)/i', wp_strip_all_tags( $desc ), $s ) ? $s[1] : '';
		echo '<section class="skp-details"><div class="skp-details__main"><h2>About this product</h2>' . wp_kses_post( wpautop( $desc ) ) . '</div><dl class="skp-facts">';
		if ( $how ) {
			echo '<div><dt>How to use</dt><dd>' . esc_html( $how ) . '</dd></div>';
		}
		if ( $size ) {
			echo '<div><dt>Size</dt><dd>' . esc_html( rtrim( $size, '. ' ) ) . '</dd></div>';
		}
		echo '<div><dt>Delivery</dt><dd>Free over $75, or free pickup at the studio in Watertown.</dd></div><div><dt>Questions?</dt><dd>Text Hana on (857) 228-4708 or try the free skin consult.</dd></div></dl></section>';
	},
	11
);

/* Kits and related products as the same cards the shop uses. */
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
add_action(
	'woocommerce_after_single_product_summary',
	function () {
		global $product;
		if ( ! function_exists( 'skynco_product_card' ) ) {
			return;
		}
		$ups = array_filter( array_map( 'wc_get_product', $product->get_upsell_ids() ) );
		if ( $ups ) {
			echo '<section class="skp-more"><h2>Save with a kit</h2><div class="sk-grid sk-cols-3">';
			foreach ( array_slice( $ups, 0, 3 ) as $p ) {
				echo skynco_product_card( $p ); // phpcs:ignore
			}
			echo '</div></section>';
		}
		$rel = array_filter( array_map( 'wc_get_product', wc_get_related_products( $product->get_id(), 8 ) ), fn( $p ) => $p && $p->is_visible() && ! has_term( [ 'treatments' ], 'product_cat', $p->get_id() ) );
		if ( $rel ) {
			echo '<section class="skp-more"><h2>You may also like</h2><div class="sk-grid">';
			foreach ( array_slice( $rel, 0, 4 ) as $p ) {
				echo skynco_product_card( $p ); // phpcs:ignore
			}
			echo '</div></section>';
		}
	},
	20
);

/* Sticky buy bar on phones. */
add_action(
	'wp_footer',
	function () {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product || ! $product->is_purchasable() ) {
			return;
		}
		$img = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' );
		echo '<div class="skp-bar" aria-hidden="true"><img src="' . esc_url( $img ) . '" alt=""><div class="skp-bar__t"><b>' . esc_html( $product->get_name() ) . '</b><span>' . wp_kses_post( $product->get_price_html() ) . '</span></div><button type="button" class="skp-bar__btn" tabindex="-1">Add to bag</button></div>';
		?>
<script>
(function(){
	document.addEventListener('click',function(e){var b=e.target.closest('.skp-step');if(!b)return;var q=b.parentNode.querySelector('input.qty');if(!q)return;var v=Math.max(+q.min||1,(+q.value||1)+(+b.dataset.d));if(q.max&&+q.max>0)v=Math.min(v,+q.max);q.value=v;q.dispatchEvent(new Event('change',{bubbles:true}));});
	var bar=document.querySelector('.skp-bar'),btn=document.querySelector('form.cart .single_add_to_cart_button');if(!bar||!btn)return;
	bar.querySelector('.skp-bar__btn').addEventListener('click',function(){btn.click();});
	if('IntersectionObserver' in window){new IntersectionObserver(function(en){var past=!en[0].isIntersecting&&en[0].boundingClientRect.top<0;bar.classList.toggle('is-on',past);document.body.classList.toggle('skp-bar-on',past);}).observe(btn);}
})();
</script>
		<?php
	}
);

add_action(
	'wp_head',
	function () {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		?>
<style id="skynco-product">
.single-product .summary .single-product-category,.single-product .summary .product_meta{display:none!important}
.single-product .summary .woocommerce-breadcrumb{margin:0 0 14px!important;font:500 12.5px/1.5 Manrope,sans-serif!important;color:#9A8791!important}
.single-product .summary .woocommerce-breadcrumb a{color:#9A8791!important}
.skp-brand{margin:0 0 6px!important;font:700 12px/1.2 Manrope,sans-serif;letter-spacing:.16em;text-transform:uppercase;color:#D1127E}
.single-product .summary .product_title{font:400 clamp(30px,3.4vw,44px)/1.1 Fraunces,serif!important;color:#3B1530!important;margin:0 0 10px!important}
.single-product .summary .price{margin:6px 0 14px!important;font:700 26px Manrope,sans-serif!important;color:#2A1A24!important}
.single-product .summary .price del{font-size:17px;color:#9A8791;font-weight:500}.single-product .summary .price ins{text-decoration:none;color:#D1127E}
.single-product .summary .woocommerce-product-details__short-description p{margin:0 0 18px;font:400 16px/1.6 Manrope,sans-serif;color:#4A3843}
.single-product form.cart{display:flex!important;gap:10px;align-items:stretch;margin:0 0 4px!important;flex-wrap:nowrap}
.single-product form.cart .quantity{display:flex!important;align-items:center;flex:none;margin:0!important;border:1px solid #E2CCD4;border-radius:999px;background:#fff;overflow:hidden;height:54px}
.single-product form.cart .quantity input.qty{width:40px!important;height:52px!important;border:0!important;background:transparent!important;text-align:center;font:700 16px Manrope,sans-serif!important;color:#2A1A24;padding:0!important;-moz-appearance:textfield;box-shadow:none!important}
.single-product form.cart .quantity input.qty::-webkit-inner-spin-button,.single-product form.cart .quantity input.qty::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}
.skp-step{width:42px;height:52px;border:0!important;background:transparent!important;color:#3B1530!important;font:500 20px Manrope,sans-serif;cursor:pointer;padding:0!important}
.single-product form.cart .single_add_to_cart_button{flex:1;height:54px;margin:0!important;padding:0 22px!important;border-radius:999px!important;background:#D1127E!important;color:#fff!important;font:700 16px Manrope,sans-serif!important;letter-spacing:.01em}
.single-product form.cart .single_add_to_cart_button:hover{background:#B00F6A!important}
.single-product .sk-assure{display:grid;gap:6px;margin-top:14px}
.single-product .sk-pairs{margin-top:16px;display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.single-product .sk-pairs b{width:100%;font:700 12px Manrope,sans-serif;letter-spacing:.08em;text-transform:uppercase;color:#6E5A66}
.single-product .sk-pairs a{display:inline-block;padding:6px 12px;border-radius:99px;background:#fff;border:1px solid #EADDE0;font:600 13px Manrope,sans-serif;text-decoration:none!important}
.single-product .sk-pairs{font-size:0}
.skp-details{clear:both;max-width:1200px;margin:48px auto 0;display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:28px;font-family:Manrope,sans-serif}
.skp-details h2,.skp-more h2{margin:0 0 12px!important;font:400 30px/1.2 Fraunces,serif!important;color:#3B1530!important}
.skp-details__main p{margin:0 0 12px;font-size:16px;line-height:1.7;color:#4A3843}
.skp-facts{margin:0;display:grid;gap:10px}
.skp-facts div{padding:14px 16px;border-radius:16px;background:#fff;border:1px solid #EFE2E6}
.skp-facts dt{font:700 11.5px Manrope,sans-serif;letter-spacing:.12em;text-transform:uppercase;color:#D1127E}
.skp-facts dd{margin:4px 0 0;font-size:14.5px;line-height:1.55;color:#2A1A24}
.skp-more{clear:both;max-width:1200px;margin:56px auto 0}
.skp-bar{display:none}
@media(max-width:921px){
.single-product .ast-container,.single-product .site-content>.ast-container{padding-left:16px!important;padding-right:16px!important}
.single-product div.product .woocommerce-product-gallery,.single-product div.product .summary{width:100%!important;float:none!important;margin:0 0 20px!important}
.single-product div.product .woocommerce-product-gallery img{border-radius:22px}
.single-product .summary .woocommerce-breadcrumb{display:none}
.skp-details{grid-template-columns:1fr;margin-top:32px;gap:16px}
.skp-details h2,.skp-more h2{font-size:26px!important}
.skp-more{margin-top:40px}
.skp-bar{display:flex;position:fixed;left:0;right:0;bottom:0;z-index:99980;align-items:center;gap:10px;padding:10px 12px calc(10px + env(safe-area-inset-bottom));background:#fff;border-top:1px solid #EADDE0;box-shadow:0 -10px 30px -18px rgba(59,21,48,.35);transform:translateY(110%);transition:transform .3s ease;font-family:Manrope,sans-serif}
.skp-bar.is-on{transform:none}
.skp-bar img{width:44px;height:44px;border-radius:10px;object-fit:cover;flex:none}
.skp-bar__t{min-width:0;flex:1;display:flex;flex-direction:column}
.skp-bar__t b{font:600 13px/1.3 Manrope,sans-serif;color:#2A1A24;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.skp-bar__t span{font:700 14px Manrope,sans-serif;color:#2A1A24}.skp-bar__t del{font-weight:500;color:#9A8791;margin-right:4px}.skp-bar__t ins{text-decoration:none;color:#D1127E}
.skp-bar__btn{flex:none;height:46px;padding:0 20px!important;border:0!important;border-radius:999px!important;background:#D1127E!important;color:#fff!important;font:700 15px Manrope,sans-serif!important}
body.skp-bar-on .skb{bottom:84px;transition:bottom .3s ease}
body.skp-bar-on #ast-scroll-top{bottom:84px!important}
}
@media(max-width:560px){.single-product .summary .product_title{font-size:30px!important}.single-product form.cart .single_add_to_cart_button{font-size:15px!important;padding:0 14px!important}}
</style>
		<?php
	}
);
