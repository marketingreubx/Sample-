<?php
/**
 * Skyn&Co. cart and checkout design.
 * Cart: custom template (templates/cart/cart.php). Checkout: classic WooCommerce
 * checkout with a two-column layout, fewer fields and branded styling.
 *
 * @package SkyncoStudio
 */

defined( 'ABSPATH' ) || exit;

/* Use our cart template. */
add_filter(
	'woocommerce_locate_template',
	function ( $template, $name ) {
		$ours = __DIR__ . '/templates/' . $name;
		return 'cart/cart.php' === $name && file_exists( $ours ) ? $ours : $template;
	},
	10,
	2
);

/* Cross-sells sit under the items, not in the summary column. */
add_action(
	'init',
	function () {
		remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );
	}
);

function skynco_checkout_steps( $active ) {
	$steps = [ 1 => 'Bag', 2 => 'Details & payment', 3 => 'Confirmed' ];
	echo '<ol class="skc-steps">';
	foreach ( $steps as $i => $label ) {
		echo '<li class="' . ( $i < $active ? 'is-done' : ( $i === $active ? 'is-on' : '' ) ) . '"><span>' . ( $i < $active ? '✓' : (int) $i ) . '</span>' . esc_html( $label ) . '</li>';
	}
	echo '</ol>';
}

function skynco_checkout_trust() {
	echo '<ul class="skc-trust"><li>Free shipping over ' . wp_kses_post( wc_price( function_exists( 'skynco_free_ship_min' ) ? skynco_free_ship_min() : 75 ) ) . ', or free pickup in Watertown</li><li>Products Hana uses and trusts in the studio</li><li>Questions? Text (857) 228-4708</li></ul>';
}

add_action(
	'woocommerce_before_checkout_form',
	function () {
		skynco_checkout_steps( 2 );
	},
	1
);
add_action(
	'woocommerce_before_cart',
	function () {
		skynco_checkout_steps( 1 );
	},
	1
);

/* Page titles are replaced by the step bar. */
add_filter(
	'astra_the_title_enabled',
	function ( $on ) {
		return ( function_exists( 'is_cart' ) && ( is_cart() || ( is_checkout() && ! is_order_received_page() ) ) ) ? false : $on;
	}
);

/* Friendlier wording. */
add_filter(
	'gettext',
	function ( $t, $text, $domain ) {
		if ( 'woocommerce' !== $domain ) {
			return $t;
		}
		$map = [
			'Cart totals'                 => 'Order summary',
			'Proceed to checkout'         => 'Checkout securely',
			'Billing details'             => 'Your details',
			'Your order'                  => 'Order summary',
			'Ship to a different address?' => 'Ship to a different address',
			'Additional information'      => 'Anything we should know?',
			'Have a coupon?'              => 'Have a discount code?',
			'Click here to enter your code' => 'Add it here',
		];
		return $map[ $text ] ?? $t;
	},
	10,
	3
);

/* Shorter "added to bag" message, without the redundant View cart button. */
add_filter(
	'wc_add_to_cart_message_html',
	function ( $message, $products ) {
		$names = [];
		foreach ( (array) $products as $id => $qty ) {
			$names[] = '<b>' . esc_html( get_the_title( $id ) ) . '</b>';
		}
		return implode( ', ', $names ) . ' ' . ( count( $names ) > 1 ? 'are' : 'is' ) . ' in your bag.';
	},
	10,
	2
);

/* Fewer, clearer checkout fields. */
add_filter(
	'woocommerce_checkout_fields',
	function ( $f ) {
		unset( $f['billing']['billing_company'], $f['shipping']['shipping_company'] );
		if ( isset( $f['billing']['billing_phone'] ) ) {
			$f['billing']['billing_phone']['required']    = true;
			$f['billing']['billing_phone']['label']       = 'Mobile number';
			$f['billing']['billing_phone']['description'] = 'For order updates by text or WhatsApp.';
			$f['billing']['billing_phone']['priority']    = 25;
			$f['billing']['billing_phone']['class']       = [ 'form-row-wide' ];
		}
		if ( isset( $f['billing']['billing_email'] ) ) {
			$f['billing']['billing_email']['priority'] = 5;
			$f['billing']['billing_email']['class']    = [ 'form-row-wide' ];
		}
		if ( isset( $f['order']['order_comments'] ) ) {
			$f['order']['order_comments']['placeholder'] = 'Gift note, delivery instructions or skin concerns (optional)';
		}
		return $f;
	},
	20
);

/* Product thumbnails in the checkout summary. */
add_filter(
	'woocommerce_cart_item_name',
	function ( $name, $item, $key ) {
		if ( ! is_checkout() || empty( $item['data'] ) ) {
			return $name;
		}
		return '<span class="skc-rv">' . $item['data']->get_image( [ 64, 64 ] ) . '<span>' . $name . '</span></span>';
	},
	10,
	3
);

/* Small JS: quantity steppers that update the bag automatically. */
add_action(
	'wp_footer',
	function () {
		if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return;
		}
		?>
<script>
jQuery(function($){
	var t;
	function update(){ clearTimeout(t); t=setTimeout(function(){ $('[name="update_cart"]').prop('disabled',false).trigger('click'); },450); }
	$(document.body).on('click','.skc-step',function(){
		var i=$(this).closest('.skc-qty').find('input.qty'), v=parseInt(i.val()||0,10)+parseInt($(this).data('d'),10), mn=parseInt(i.attr('min')||0,10), mx=parseInt(i.attr('max'),10)||999;
		i.val(Math.max(mn,Math.min(mx,v))).trigger('change');
	});
	$(document.body).on('change','.skc-qty input.qty',update);
});
</script>
		<?php
	},
	50
);

add_action(
	'wp_head',
	function () {
		if ( ! function_exists( 'is_cart' ) || ! ( is_cart() || is_checkout() ) ) {
			return;
		}
		?>
<style id="skynco-checkout">
.woocommerce-cart .site-content>.ast-container,.woocommerce-checkout .site-content>.ast-container{max-width:1180px}
.woocommerce-cart .entry-content,.woocommerce-checkout .entry-content{font-family:Manrope,sans-serif;color:#2A1A24}
.woocommerce-cart #primary,.woocommerce-checkout #primary{margin-top:28px;margin-bottom:72px}
/* Steps */
.skc-steps{list-style:none;display:flex;gap:8px;margin:0 0 26px;padding:0;counter-reset:s}
.skc-steps li{display:flex;align-items:center;gap:8px;flex:1;padding:10px 12px;border-radius:999px;background:#F6ECEE;color:#9A8791;font:700 13px/1.2 Manrope,sans-serif;white-space:nowrap}
.skc-steps li span{display:grid;place-items:center;width:24px;height:24px;border-radius:50%;background:#fff;color:#9A8791;font-size:12px;flex:0 0 24px}
.skc-steps li.is-on{background:#3B1530;color:#fff}.skc-steps li.is-on span{background:#D1127E;color:#fff}
.skc-steps li.is-done{background:#FBEFF0;color:#3B1530}.skc-steps li.is-done span{background:#3B1530;color:#fff}
/* Notices */
.woocommerce-cart .woocommerce-message,.woocommerce-cart .woocommerce-info,.woocommerce-checkout .woocommerce-message,.woocommerce-checkout .woocommerce-info,.woocommerce-cart .woocommerce-error,.woocommerce-checkout .woocommerce-error{border:0!important;border-radius:14px!important;background:#FBEFF0!important;color:#3B1530!important;padding:14px 18px 14px 46px!important;margin:0 0 16px!important;font:500 14px/1.5 Manrope,sans-serif!important;box-shadow:none!important}
.woocommerce-cart .woocommerce-message::before,.woocommerce-checkout .woocommerce-message::before,.woocommerce-cart .woocommerce-info::before,.woocommerce-checkout .woocommerce-info::before{color:#D1127E!important;top:14px!important;left:18px!important}
.woocommerce-checkout .woocommerce-error{background:#FFF0F0!important;color:#8A1C2B!important}
.woocommerce-cart .woocommerce-message .button{display:none!important}
.woocommerce-info a,.woocommerce-message a{color:#D1127E!important;font-weight:700}
.sk-ship{background:#fff!important;border:1px solid #EADDE0;padding:14px 18px!important}
/* Cart grid */
.skc-main,.skc-side{min-width:0}
.skc-grid{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:28px;align-items:start}
.skc-h{margin:0 0 16px;font:400 34px/1.15 Fraunces,serif;color:#3B1530}.skc-h span{font:600 15px Manrope,sans-serif;color:#9A8791;margin-left:6px}
.skc-items{list-style:none;margin:0;padding:0;border:0!important;display:flex;flex-direction:column;gap:12px;background:none!important}
.skc-item{display:grid;grid-template-columns:112px minmax(0,1fr);gap:18px;padding:16px;background:#fff;border:1px solid #EADDE0;border-radius:20px}
.skc-img img{width:112px!important;height:112px!important;object-fit:cover;border-radius:14px;display:block;background:#FBEFF0}
.skc-info{display:flex;flex-direction:column;min-width:0}
.skc-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
.skc-top h3{margin:0;font:500 19px/1.3 Fraunces,serif}.skc-top h3 a{color:#3B1530;text-decoration:none}
.skc-price{font:700 16px Manrope,sans-serif;color:#2A1A24;white-space:nowrap}
.skc-desc{margin:4px 0 0;font-size:13.5px;line-height:1.45;color:#6E5A66}
.skc-tag{display:inline-block;margin:6px 0 0;padding:3px 10px;border-radius:99px;background:#FFF0F7;color:#D1127E;font:700 12px Manrope,sans-serif;align-self:flex-start}
.skc-item dl.variation{margin:4px 0 0;font-size:13px;color:#6E5A66}
.skc-bottom{display:flex;align-items:center;justify-content:space-between;margin-top:auto;padding-top:12px}
.skc-qty{font:700 14px Manrope,sans-serif;color:#6E5A66}
.skc-qty.has-steps{display:inline-flex;align-items:center;border:1px solid #E2CCD4;border-radius:999px;overflow:hidden;background:#fff}
.skc-qty .quantity{display:inline-block;margin:0!important}
.skc-qty .quantity input.qty{width:38px!important;height:38px!important;min-height:0!important;border:0!important;border-radius:0!important;text-align:center;font:700 15px Manrope,sans-serif;color:#2A1A24;background:transparent!important;padding:0!important;-moz-appearance:textfield;box-shadow:none!important}
.skc-qty .quantity input::-webkit-inner-spin-button,.skc-qty .quantity input::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}
.skc-step{width:38px;height:38px;border:0!important;border-radius:0!important;background:transparent!important;color:#3B1530!important;font:500 20px/1 Manrope,sans-serif!important;cursor:pointer;padding:0!important;box-shadow:none!important}
.skc-step:hover{background:#FBEFF0!important}
.skc-remove{font:600 13px Manrope,sans-serif;color:#9A8791!important;text-decoration:underline!important}
.skc-remove:hover{color:#D1127E!important}
.skc-actions{display:flex;gap:10px;align-items:center;justify-content:space-between;margin-top:14px;flex-wrap:wrap}
.skc-coupon{display:flex;gap:8px;flex:1;min-width:260px}
.skc-coupon input{flex:1;height:46px;padding:0 16px!important;border:1px solid #E2CCD4!important;border-radius:999px!important;background:#fff!important;font:500 14px Manrope,sans-serif}
.skc-coupon .button,.woocommerce-checkout .checkout_coupon .button{height:46px;padding:0 22px!important;border-radius:999px!important;background:#3B1530!important;color:#fff!important;font:700 14px Manrope,sans-serif!important;border:0!important}
.skc-update{display:none!important}
.skc-cross{margin-top:36px}
.skc-cross .cross-sells h2{font:400 26px/1.2 Fraunces,serif;color:#3B1530;margin:0 0 14px}
.skc-cross ul.products{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:0!important}
.skc-cross ul.products::before,.skc-cross ul.products::after{display:none!important}
.skc-cross ul.products li.product{width:auto!important;margin:0!important;float:none!important;background:#fff;border:1px solid #EADDE0;border-radius:18px;padding:12px!important;text-align:left}
.skc-cross ul.products li.product img{border-radius:12px;margin-bottom:10px!important}
.skc-cross ul.products li.product .woocommerce-loop-product__title{font:500 16px/1.3 Fraunces,serif!important;color:#3B1530;padding:0!important}
/* Summary */
.skc-side{position:sticky;top:110px}
.skc-side .cart-collaterals .cart_totals{width:100%!important;float:none!important;background:#fff;border:1px solid #EADDE0;border-radius:22px;padding:22px}
.skc-side .cart_totals h2{margin:0 0 10px;font:400 24px/1.2 Fraunces,serif;color:#3B1530}
.skc-side .cart_totals table{border:0!important;margin:0!important}
.skc-side .cart_totals th,.skc-side .cart_totals td{border:0!important;border-top:1px solid #F1E6E9!important;padding:12px 0!important;background:none!important;font:500 14px/1.45 Manrope,sans-serif;vertical-align:top}
.skc-side .cart_totals th{color:#6E5A66;font-weight:600;width:38%}
.skc-side .cart_totals td{text-align:right}
.skc-side .cart_totals tr.order-total th,.skc-side .cart_totals tr.order-total td{font:700 18px Manrope,sans-serif;color:#2A1A24}
.skc-side .cart_totals tr:first-child th,.skc-side .cart_totals tr:first-child td{border-top:0!important}
.skc-side #shipping_method{margin:0;padding:0;list-style:none;text-align:left}
.skc-side #shipping_method li{display:flex;align-items:center;gap:8px;margin:0 0 6px!important;font-size:13.5px}
.skc-side #shipping_method input{accent-color:#D1127E;margin:0}
.skc-side .woocommerce-shipping-destination,.skc-side .shipping-calculator-button{font-size:12.5px;color:#9A8791;text-align:left}
.skc-side .wc-proceed-to-checkout{padding:14px 0 0!important}
.skc-side .wc-proceed-to-checkout .checkout-button{display:block;width:100%;padding:17px!important;border-radius:999px!important;background:#D1127E!important;color:#fff!important;font:700 16px Manrope,sans-serif!important;text-align:center;margin:0!important}
.skc-side .wc-proceed-to-checkout .checkout-button:hover{background:#B00F6A!important}
.skc-trust{list-style:none;margin:14px 0 0;padding:0 6px;display:flex;flex-direction:column;gap:8px}
.skc-trust li{position:relative;padding-left:22px;font:500 13px/1.45 Manrope,sans-serif;color:#6E5A66}
.skc-trust li::before{content:"";position:absolute;left:2px;top:4px;width:10px;height:6px;border-left:2px solid #D1127E;border-bottom:2px solid #D1127E;transform:rotate(-45deg)}
.woocommerce-cart .cart-empty{font:400 26px Fraunces,serif!important;color:#3B1530;background:none!important;padding:0!important}
.woocommerce-cart .return-to-shop .button{border-radius:999px!important;background:#D1127E!important;color:#fff!important}
/* Checkout */
.woocommerce-checkout form.checkout{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:0 28px;align-items:start}
.woocommerce-checkout form.checkout>.woocommerce-NoticeGroup{grid-column:1/-1}
.woocommerce-checkout #customer_details{grid-column:1;grid-row:1/span 2;background:#fff;border:1px solid #EADDE0;border-radius:22px;padding:26px}
.woocommerce-checkout #customer_details .col-1,.woocommerce-checkout #customer_details .col-2{width:100%!important;float:none!important;max-width:none!important;padding:0!important}
.woocommerce-checkout #order_review_heading{grid-column:2;grid-row:1;margin:0!important;padding:22px 22px 0;background:#fff;border:1px solid #EADDE0;border-bottom:0;border-radius:22px 22px 0 0;font:400 24px/1.2 Fraunces,serif!important;color:#3B1530}
.woocommerce-checkout #order_review{grid-column:2;grid-row:2;background:#fff;border:1px solid #EADDE0;border-top:0;border-radius:0 0 22px 22px;padding:8px 22px 22px;position:sticky;top:110px;width:100%!important;float:none!important}
.woocommerce-checkout h3{font:400 22px/1.2 Fraunces,serif!important;color:#3B1530;margin:0 0 14px!important}
.woocommerce-checkout #ship-to-different-address{margin-top:18px!important;font:600 15px Manrope,sans-serif!important}
.woocommerce-checkout .form-row{margin:0 0 14px!important;padding:0!important}
.woocommerce-checkout .form-row label{font:700 13px Manrope,sans-serif!important;color:#3B1530!important;margin-bottom:6px!important}
.woocommerce-checkout .form-row .required{color:#D1127E!important;text-decoration:none}
.woocommerce-checkout .form-row input.input-text,.woocommerce-checkout .form-row textarea,.woocommerce-checkout .form-row select,.woocommerce-checkout .select2-container .select2-selection--single{min-height:50px;padding:12px 16px!important;border:1px solid #E2CCD4!important;border-radius:14px!important;background:#fff!important;font:500 15px Manrope,sans-serif!important;color:#2A1A24!important;box-shadow:none!important}
.woocommerce-checkout .select2-container .select2-selection--single{display:flex;align-items:center}
.woocommerce-checkout .select2-container .select2-selection__arrow{top:12px!important;right:10px!important}
.woocommerce-checkout .form-row input.input-text:focus,.woocommerce-checkout .form-row textarea:focus{border-color:#D1127E!important;outline:0;box-shadow:0 0 0 3px rgba(209,18,126,.12)!important}
.woocommerce-checkout .form-row .description{font-size:12.5px;color:#9A8791;margin-top:6px;background:none!important;padding:0!important}
.woocommerce-checkout .form-row .description::before{display:none}
.woocommerce-checkout .woocommerce-form__label-for-checkbox{display:flex!important;align-items:center;gap:10px;font:600 14px Manrope,sans-serif!important}
.woocommerce-checkout input[type=checkbox]{accent-color:#D1127E;width:18px;height:18px}
.woocommerce-checkout table.shop_table{border:0!important;margin:0 0 16px!important}
.woocommerce-checkout table.shop_table th,.woocommerce-checkout table.shop_table td{border:0!important;border-top:1px solid #F1E6E9!important;padding:12px 0!important;background:none!important;font:500 14px/1.45 Manrope,sans-serif;vertical-align:middle}
.woocommerce-checkout table.shop_table thead{display:none}
.woocommerce-checkout table.shop_table tbody tr:first-child td{border-top:0!important}
.woocommerce-checkout table.shop_table td.product-total,.woocommerce-checkout table.shop_table tfoot td{text-align:right}
.woocommerce-checkout table.shop_table tfoot th{color:#6E5A66;font-weight:600}
.woocommerce-checkout table.shop_table tr.order-total th,.woocommerce-checkout table.shop_table tr.order-total td{font:700 18px Manrope,sans-serif!important;color:#2A1A24}
.skc-rv{display:inline-flex;align-items:center;gap:12px}
.skc-rv img{width:52px!important;height:52px!important;object-fit:cover;border-radius:10px;flex:0 0 52px}
.skc-rv span{font-weight:600;color:#2A1A24}
.woocommerce-checkout .product-quantity{color:#9A8791;font-weight:600}
.woocommerce-checkout #shipping_method{list-style:none;margin:0;padding:0;text-align:left}
.woocommerce-checkout #shipping_method li{display:flex;align-items:center;gap:8px;margin:0 0 6px!important;font-size:13.5px;justify-content:flex-end}
.woocommerce-checkout #payment{background:none!important;border-radius:0!important}
.woocommerce-checkout #payment ul.payment_methods{padding:0!important;border:0!important;margin:0 0 14px!important}
.woocommerce-checkout #payment ul.payment_methods li{list-style:none;padding:14px 16px!important;border:1px solid #EADDE0;border-radius:14px;margin:0 0 8px!important;background:#fff;font:600 14px Manrope,sans-serif}
.woocommerce-checkout #payment ul.payment_methods li input{accent-color:#D1127E;margin-right:8px}
.woocommerce-checkout #payment div.payment_box{background:#FBEFF0!important;border-radius:12px!important;margin:10px 0 0!important;padding:12px 14px!important;font:400 13px/1.5 Manrope,sans-serif;color:#6E5A66}
.woocommerce-checkout #payment div.payment_box::before{display:none!important}
.woocommerce-checkout #payment .place-order{padding:0!important;margin:0!important}
.woocommerce-checkout .woocommerce-privacy-policy-text p{font-size:12.5px;line-height:1.5;color:#9A8791;margin:0 0 14px}
.woocommerce-checkout #place_order{width:100%;padding:18px!important;border-radius:999px!important;background:#D1127E!important;color:#fff!important;font:700 16px Manrope,sans-serif!important;border:0!important;float:none!important}
.woocommerce-checkout #place_order:hover{background:#B00F6A!important}
.woocommerce-checkout .checkout_coupon{border:1px solid #EADDE0!important;border-radius:16px!important;background:#fff;padding:14px!important;display:flex;gap:8px;flex-wrap:wrap}
.woocommerce-checkout .checkout_coupon p{margin:0!important}.woocommerce-checkout .checkout_coupon p:first-child{width:100%;font-size:13px;color:#6E5A66}
.woocommerce-checkout .checkout_coupon .form-row-first{flex:1}
.woocommerce-checkout .checkout_coupon input{height:46px;border-radius:999px!important;border:1px solid #E2CCD4!important;padding:0 16px!important}
.woocommerce-cart .woocommerce-info::before,.woocommerce-checkout .woocommerce-info::before{display:none!important}
.woocommerce-cart .woocommerce-info,.woocommerce-checkout .woocommerce-info{padding-left:18px!important}
tr.woocommerce-shipping-totals th,tr.woocommerce-shipping-totals td{display:block;width:100%!important;text-align:left!important}
tr.woocommerce-shipping-totals th{padding-bottom:4px!important}
tr.woocommerce-shipping-totals td{border-top:0!important;padding-top:4px!important}
#shipping_method li{display:flex!important;align-items:center;justify-content:flex-start!important;gap:10px;margin:0 0 8px!important;font:500 14px/1.4 Manrope,sans-serif!important;color:#2A1A24}
#shipping_method li label{margin:0!important;display:inline!important}
#shipping_method input[type=radio]{width:18px!important;height:18px!important;flex:0 0 18px;margin:0!important;accent-color:#D1127E}
.woocommerce-checkout table.shop_table td.product-name{width:72%}
.woocommerce-checkout .product-name .product-quantity{display:block;margin:2px 0 0 64px;font-size:12.5px}
/* Order bump: keep prices on one line */
.sk-bump{background:#FFF8F6!important;margin:0 0 16px!important}
.sk-bump>span{display:flex;flex-direction:column;gap:4px}
.sk-bump>span span,.sk-bump bdi{display:inline!important}
.sk-bump b{font:700 14.5px/1.4 Manrope,sans-serif;color:#2A1A24}
.sk-bump b .amount{color:#D1127E}
.sk-bump s{font-size:13px}
@media(max-width:921px){
.skc-grid,.woocommerce-checkout form.checkout{grid-template-columns:1fr}
.skc-side,.woocommerce-checkout #order_review{position:static}
.woocommerce-checkout #customer_details{grid-row:auto;margin-bottom:18px}
.woocommerce-checkout #order_review_heading,.woocommerce-checkout #order_review{grid-column:1;grid-row:auto}
}
@media(max-width:600px){
.woocommerce-cart #primary,.woocommerce-checkout #primary{margin-top:16px}
.skc-steps{gap:6px;margin-bottom:18px}
.skc-steps li{padding:8px;justify-content:center;font-size:0;flex:0 0 auto}
.skc-steps li.is-on{flex:1;font-size:13px;justify-content:flex-start}
.skc-h{font-size:28px}
.skc-item{grid-template-columns:84px minmax(0,1fr);gap:14px;padding:12px;border-radius:18px}
.skc-img img{width:84px!important;height:84px!important}
.skc-top{flex-direction:column;gap:2px}
.skc-top h3{font-size:17px}
.skc-desc{display:none}
.skc-coupon{min-width:0;width:100%}
.skc-cross ul.products{display:flex!important;overflow-x:auto;scroll-snap-type:x mandatory;gap:12px;padding-bottom:6px}
.skc-cross ul.products li.product{flex:0 0 62%;scroll-snap-align:start}
.woocommerce-checkout #customer_details{padding:18px;border-radius:18px}
.woocommerce-checkout #order_review_heading{padding:18px 18px 0}
.woocommerce-checkout #order_review{padding:6px 18px 18px}
.woocommerce-checkout .form-row-first,.woocommerce-checkout .form-row-last{width:100%!important;float:none!important}
}
</style>
		<?php
	}
);
