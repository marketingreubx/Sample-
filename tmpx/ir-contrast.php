<?php
/**
 * Plugin Name: IR Contrast
 * Description: Readability fixes across the site: dark-mode accent split (light blue for text, deeper blue behind white text), WooCommerce dark mode, badges, links and loop buttons.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_head', function () {
	if ( is_admin() ) { return; }
	?>
<style id="ir-contrast">
/* Dark sections: solid navy behind the gradient so text never sits on a light page */
body:not(.elementor-template-canvas) .irx-band{background-color:#071233!important}

/* Dark mode: accent used as text gets a lighter blue... */
html[data-theme="dark"] .elementor-kit-205{--e-global-color-iraccent:#7FA8F5;--e-global-color-primary:#7FA8F5}
/* ...and anything that puts white text on the accent keeps a deeper blue */
html[data-theme="dark"] .elementor-button,html[data-theme="dark"] .e-n-tab-title,html[data-theme="dark"] .ir-av-more,html[data-theme="dark"] .is-on,html[data-theme="dark"] .irb-total,html[data-theme="dark"] .is-me,html[data-theme="dark"] .woocommerce .button,html[data-theme="dark"] .woocommerce button.button,html[data-theme="dark"] input[type="submit"],html[data-theme="dark"] .irc-btn{--e-global-color-iraccent:#1F5FD6;--e-global-color-primary:#1F5FD6}
html[data-theme="dark"] .elementor-field-type-submit .elementor-button,html[data-theme="dark"] .ir-btn--primary .elementor-button{background-color:#1F5FD6}

/* Links inside dark sections */
.ir-on-band .elementor-widget-text-editor a:not(.elementor-button),.irx-band .elementor-widget-text-editor a:not(.elementor-button){color:#9CC2FF!important}

/* Product cards */
.irc-off{background:#C2410C!important}
.irc-price s{color:#5D6880!important;opacity:1!important}
html[data-theme="dark"] .irc-price s{color:#A9B6D0!important}
.irc-btn{background:#1450C2!important}

/* WooCommerce: loop buttons and titles (related products, shop) */
.woocommerce ul.products li.product .button,.woocommerce-page ul.products li.product .button{background:#1450C2!important;color:#fff!important;border:0!important;border-radius:10px!important;padding:12px 18px!important;font-weight:700!important;opacity:1!important}
.woocommerce ul.products li.product .button:hover{background:#0E3F9E!important}
.woocommerce ul.products li.product .woocommerce-loop-product__title{font-size:17px!important;line-height:1.35!important}
.ast-woo-product-category,.woocommerce .product_meta,.woocommerce .product_meta a{color:#4F5D78!important}
.woocommerce div.product p.price del,.woocommerce div.product span.price del,.woocommerce ul.products li.product .price del{color:#5D6880!important;opacity:1!important}

/* WooCommerce: dark mode */
html[data-theme="dark"] .woocommerce div.product p.price,html[data-theme="dark"] .woocommerce div.product span.price,html[data-theme="dark"] .woocommerce ul.products li.product .price,html[data-theme="dark"] .woocommerce .woocommerce-Price-amount,html[data-theme="dark"] .woocommerce ins{color:#F2F5FA!important}
html[data-theme="dark"] .woocommerce del,html[data-theme="dark"] .woocommerce del .woocommerce-Price-amount{color:#A9B6D0!important;opacity:1!important}
html[data-theme="dark"] .woocommerce-tabs ul.tabs li a{color:#C9D3E6!important}
html[data-theme="dark"] .woocommerce-tabs ul.tabs li.active a{color:#FFFFFF!important}
html[data-theme="dark"] .ast-woo-product-category,html[data-theme="dark"] .woocommerce .product_meta,html[data-theme="dark"] .woocommerce .product_meta a,html[data-theme="dark"] .woocommerce ul.products li.product .ast-woo-product-category{color:#A9B6D0!important}
html[data-theme="dark"] .woocommerce ul.products li.product .woocommerce-loop-product__title,html[data-theme="dark"] .woocommerce div.product .product_title,html[data-theme="dark"] .woocommerce-tabs h2,html[data-theme="dark"] .related > h2,html[data-theme="dark"] .upsells > h2{color:#F2F5FA!important}
html[data-theme="dark"] .woocommerce div.product .woocommerce-tabs .panel,html[data-theme="dark"] .woocommerce-product-details__short-description{color:#C9D3E6!important}
html[data-theme="dark"] .woocommerce table.shop_table,html[data-theme="dark"] .woocommerce table.shop_table th,html[data-theme="dark"] .woocommerce table.shop_table td,html[data-theme="dark"] .woocommerce-checkout h3,html[data-theme="dark"] .woocommerce form .form-row label,html[data-theme="dark"] .woocommerce-billing-fields h3,html[data-theme="dark"] .woocommerce-checkout-review-order,html[data-theme="dark"] .woocommerce-privacy-policy-text,html[data-theme="dark"] .woocommerce-terms-and-conditions-wrapper{color:#E6ECF7!important}
html[data-theme="dark"] .woocommerce form .input-text,html[data-theme="dark"] .woocommerce form select,html[data-theme="dark"] .woocommerce .select2-container .select2-selection{background:#101C33!important;color:#F2F5FA!important;border-color:#2A3A5E!important}
html[data-theme="dark"] .woocommerce-info,html[data-theme="dark"] .woocommerce-message{background:#101C33!important;color:#E6ECF7!important}
html[data-theme="dark"] .woocommerce span.onsale{background:#C2410C!important;color:#fff!important}

/* Tutor login popup stays white in dark mode: keep its links dark enough */
.tutor-modal .tutor-btn-ghost,.tutor-modal .tutor-btn-link,.tutor-login-form-wrapper a,.tutor-login-wrap a{color:#1450C2!important}
</style>
	<?php
}, 99 );
