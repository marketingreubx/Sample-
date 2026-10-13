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

/* Dark mode: the accent stays a deep blue (white text on it stays readable)... */
html[data-theme="dark"] .elementor-kit-205{--e-global-color-iraccent:#1F5FD6;--e-global-color-primary:#1F5FD6}
/* ...and text widgets that use the accent as a text colour get a lighter blue */
html[data-theme="dark"] .elementor-widget-heading,html[data-theme="dark"] .elementor-widget-text-editor,html[data-theme="dark"] .elementor-widget-icon-list,html[data-theme="dark"] .elementor-widget-counter,html[data-theme="dark"] .elementor-widget-icon-box{--e-global-color-iraccent:#7FA8F5;--e-global-color-primary:#7FA8F5}

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

/* Tutor LMS: brand buttons in both modes */
.tutor-btn-primary{background-color:#1450C2!important;border-color:#1450C2!important;color:#fff!important}
.tutor-btn-primary:hover{background-color:#0E3F9E!important}
html:not([data-theme="dark"]) .tutor-modal .tutor-btn-ghost,html:not([data-theme="dark"]) .tutor-modal .tutor-btn-link{color:#1450C2!important}
/* Tutor LMS: dark mode through its own colour tokens */
html[data-theme="dark"] body{--tutor-surface-base:#070F1F;--tutor-surface-l1:#101C33;--tutor-surface-l1-hover:#15233E;--tutor-surface-l2:#1E2C47;--tutor-surface-l2-hover:#2A3A5E;--tutor-surface-l3:#15233E;--tutor-surface-sidebar-l1:#0B1529;--tutor-surface-brand-secondary:#1E3A6E;--tutor-surface-brand-tertiary:#13274A;--tutor-surface-brand-quaternary:#13274A;--tutor-surface-success:#0F2E1C;--tutor-surface-warning:#2A2312;--tutor-surface-critical:#3A1414;
--tutor-text-primary:#F2F5FA;--tutor-text-secondary:#C9D3E6;--tutor-text-subdued:#A9B6D0;--tutor-text-disabled:#5A6785;--tutor-text-brand:#7FA8F5;--tutor-text-brand-hover:#A5BFF0;--tutor-text-highlighted-hover:#C9D3E6;--tutor-text-success:#4ADE80;--tutor-text-warning:#FDBA74;--tutor-text-caution:#FCD34D;
--tutor-icon-idle:#C9D3E6;--tutor-icon-hover:#F2F5FA;--tutor-icon-secondary:#A9B6D0;--tutor-icon-subdued:#8592AE;--tutor-icon-brand:#7FA8F5;--tutor-icon-brand-hover:#A5BFF0;
--tutor-button-primary:#1450C2;--tutor-button-primary-hover:#0E3F9E;--tutor-button-primary-soft:#13274A;--tutor-button-primary-soft-hover:#1E3A6E;--tutor-button-secondary:#1E2C47;--tutor-button-secondary-hover:#2A3A5E;--tutor-button-outline-hover:#1E2C47;--tutor-button-ghost-hover:#1E2C47;--tutor-button-disabled:#1E2C47;
--tutor-border-idle:#1E2C47;--tutor-border-idle2:#1E2C47;--tutor-border-hover:#2A3A5E;--tutor-border-tertiary:#5A6785;
--tutor-body-color:#E6ECF7;--tutor-color-black:#F2F5FA;--tutor-color-secondary:#C9D3E6;--tutor-color-muted:#A9B6D0;--tutor-color-gray-10:#15233E;--tutor-border-color:#1E2C47;--tutor-color-primary:#7FA8F5}
html[data-theme="dark"] .tutor-page-wrap,html[data-theme="dark"] .tutor-wrap{background:transparent!important}
html[data-theme="dark"] .tutor-accordion-item,html[data-theme="dark"] .tutor-card,html[data-theme="dark"] .tutor-sidebar-card{background-color:#101C33!important;border-color:#1E2C47!important}
/* Remaining spots */
html[data-theme="dark"] .ir-av-more{background:#1F5FD6!important}
html[data-theme="dark"] .elementor-element-1c223fe{--e-global-color-iraccent:#1F5FD6}
.elementor-element-2f60333 .elementor-heading-title{color:#FFFFFF!important}
.ast-woo-product-category,.single-product-category a{opacity:1!important}
html[data-theme="dark"] .single-product-category a{color:#7FA8F5!important}
html[data-theme="dark"] .tutor-btn-ghost,html[data-theme="dark"] .tutor-btn-link,html[data-theme="dark"] .tutor-nav-link,html[data-theme="dark"] .tutor-course-wishlist-btn,html[data-theme="dark"] .tutor-course-share-btn,html[data-theme="dark"] .tutor-mr-4{color:#7FA8F5!important}
html[data-theme="dark"] .tutor-color-secondary,html[data-theme="dark"] .tutor-color-secondary p{color:#C9D3E6!important}
html[data-theme="dark"] .tutor-color-muted,html[data-theme="dark"] .tutor-enrolled-info-text{color:#A9B6D0!important}
html[data-theme="dark"] .tutor-color-black,html[data-theme="dark"] .tutor-fs-6:not(.tutor-btn){color:#F2F5FA}
html[data-theme="dark"] .tutor-alert,html[data-theme="dark"] .tutor-alert-text{background-color:#15233E!important;color:#E6ECF7!important}
html[data-theme="dark"] .tutor-color-success{color:#4ADE80!important}
</style>
	<?php
	echo ir_contrast_tutor_dark_css();
}, 99 );

/**
 * Tutor hard-codes white and light-grey panels in its stylesheets. Collect those selectors once
 * (cached per Tutor version) and give them a dark panel in dark mode.
 */
function ir_contrast_tutor_dark_css() {
	if ( ! defined( 'TUTOR_VERSION' ) ) { return ''; }
	$key = 'ir_tutor_dark_css_' . md5( TUTOR_VERSION . '2' );
	$css = get_transient( $key );
	if ( $css === false ) {
		$sel = [];
		foreach ( [ 'tutor-front.min.css', 'tutor.min.css', 'tutor-core.min.css', 'tutor-learning-area.min.css' ] as $fn ) {
			$c = @file_get_contents( WP_PLUGIN_DIR . '/tutor/assets/css/' . $fn );
			if ( ! $c ) { continue; }
			$c = preg_replace( '~/\*.*?\*/~s', '', $c );
			foreach ( explode( '}', $c ) as $rule ) {
				$p = strrpos( $rule, '{' );
				if ( $p === false ) { continue; }
				$body = substr( $rule, $p + 1 );
				if ( ! preg_match( '~background(?:-color)?:\s*(#fff\b|#ffffff|#fcfcfd|#fcfcfc|#fafafa|#f4f6f9|#eff1f6|#f5f5f6|#f9fafb|white\b)~i', $body ) ) { continue; }
				$sels = preg_replace( '~^.*\{~s', '', trim( substr( $rule, 0, $p ) ) );
				foreach ( explode( ',', $sels ) as $one ) {
					$one = trim( $one );
					if ( ! $one || strlen( $one ) > 140 || preg_match( '~hover|btn|::|white|slider|switch|ui-|backend|builder|modal-close|tutor-bg-~', $one ) ) { continue; }
					$sel[ $one ] = 1;
				}
			}
		}
		$css = $sel ? '<style id="ir-tutor-dark">' . implode( ',', array_map( function ( $x ) { return 'html[data-theme="dark"] ' . $x; }, array_keys( $sel ) ) ) . '{background-color:#101C33!important;border-color:#1E2C47!important}</style>' : '';
		set_transient( $key, $css, WEEK_IN_SECONDS );
	}
	return $css;
}
