<?php
/**
 * Lux esthetician site – global kit (colours/fonts) and shared CSS.
 * Functions only; called from build scripts.
 */
if ( ! defined( 'ABSPATH' ) || function_exists( 'lux_setup_kit' ) ) {
	return;
}

function lux_setup_kit() {
	$kit_id   = (int) get_option( 'elementor_active_kit' );
	$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
	$settings = is_array( $settings ) ? $settings : [];

	$system = [];
	$custom = [];
	foreach ( lux_colors() as $id => $c ) {
		$row = [ '_id' => $id, 'title' => $c[0], 'color' => $c[1] ];
		if ( in_array( $id, [ 'primary', 'secondary', 'text', 'accent' ], true ) ) {
			$system[] = $row;
		} else {
			$custom[] = $row;
		}
	}
	$settings['system_colors'] = $system;
	$settings['custom_colors'] = $custom;

	$settings['system_typography'] = [
		[ '_id' => 'primary', 'title' => 'Headings (Fraunces)', 'typography_typography' => 'custom', 'typography_font_family' => 'Fraunces', 'typography_font_weight' => '500' ],
		[ '_id' => 'secondary', 'title' => 'Eyebrow (Manrope caps)', 'typography_typography' => 'custom', 'typography_font_family' => 'Manrope', 'typography_font_weight' => '600', 'typography_font_size' => lux_u( 13 ), 'typography_text_transform' => 'uppercase', 'typography_letter_spacing' => lux_u( 0.14, 'em' ) ],
		[ '_id' => 'text', 'title' => 'Body (Manrope)', 'typography_typography' => 'custom', 'typography_font_family' => 'Manrope', 'typography_font_weight' => '400', 'typography_font_size' => lux_u( 17 ), 'typography_line_height' => lux_u( 28 ) ],
		[ '_id' => 'accent', 'title' => 'Buttons (Manrope)', 'typography_typography' => 'custom', 'typography_font_family' => 'Manrope', 'typography_font_weight' => '600', 'typography_font_size' => lux_u( 15 ) ],
	];

	$settings['body_background_background'] = 'classic';
	$settings['body_background_color']      = '#FFF8F6';
	$settings['body_color']                 = '#2A1A24';
	$settings['body_typography_typography'] = 'custom';
	$settings['body_typography_font_family'] = 'Manrope';
	$settings['link_normal_color']          = '#3B1530';
	$settings['link_hover_color']           = '#26101F';
	foreach ( [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ] as $h ) {
		$settings[ $h . '_typography_typography' ]  = 'custom';
		$settings[ $h . '_typography_font_family' ] = 'Fraunces';
		$settings[ $h . '_typography_font_weight' ] = '500';
		$settings[ $h . '_color' ]                  = '#3B1530';
	}
	$settings['container_width']   = lux_u( 1200 );
	$settings['container_padding'] = lux_box( 0 );
	$settings['site_name']         = get_bloginfo( 'name' );

	update_post_meta( $kit_id, '_elementor_page_settings', $settings );
	return $kit_id;
}

function lux_site_css() {
	return <<<'CSS'
/* Brand v2 */
.lux-badge--lime .elementor-heading-title{color:#fff!important}
.custom-logo-link img{max-height:64px;width:auto}
.lux-hero--photo .lux-pill--dark .elementor-heading-title{border-color:rgba(255,255,255,.35);color:#FFD1E8}
.lux-hero--photo h1 em{color:#FF8CC8}
.lux-rating{display:inline-flex;align-items:center;gap:10px;margin-top:6px;padding:10px 16px;border-radius:999px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.28);color:#FFF8F6!important;font:500 15px/1 Manrope,sans-serif;text-decoration:none!important;-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px)}
.lux-rating strong{font-weight:700}
.lux-rating__stars{color:#FF8CC8;letter-spacing:2px}
.lux-glass{-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px)}
.lux-live .elementor-heading-title::before{animation:luxPulse 2.4s ease-out infinite}
@keyframes luxPulse{0%{box-shadow:0 0 0 0 rgba(250,38,160,.55)}70%{box-shadow:0 0 0 9px rgba(250,38,160,0)}100%{box-shadow:0 0 0 0 rgba(250,38,160,0)}}
.lux-bob{animation:luxBob 6s ease-in-out infinite}
.lux-bob--late{animation-delay:-3s}
@keyframes luxBob{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
.lux-hero--photo{background-attachment:scroll}
.elementor-button{transition:transform .2s ease,background-color .2s ease,box-shadow .2s ease}
.elementor-button:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(209,18,126,.22)}
.lux-card,.lux-row-link{transition:transform .25s ease,box-shadow .25s ease,border-color .2s}
.lux-row-link:hover{transform:translateY(-3px);box-shadow:0 16px 36px rgba(59,21,48,.10)}
/* hero-mobile-overlay */
@media (max-width:1024px){.lux-hero--photo::before{background-image:none!important;background-color:rgba(38,16,31,.80)!important;opacity:1!important}}
/* Reviews marquee */
.lux-reviews{overflow:hidden;-webkit-mask-image:linear-gradient(90deg,transparent,#000 5%,#000 95%,transparent);mask-image:linear-gradient(90deg,transparent,#000 5%,#000 95%,transparent)}
.lux-reviews__track{display:flex;width:max-content;animation:luxMarquee 70s linear infinite}
.lux-reviews:hover .lux-reviews__track{animation-play-state:paused}
.lux-reviews__set{display:flex;gap:16px;padding-right:16px}
@keyframes luxMarquee{to{transform:translateX(-50%)}}
.lux-review{width:380px;margin:0;padding:28px;border-radius:24px;background:#FBEFF0;display:flex;flex-direction:column;gap:14px}
.lux-review__stars{color:#D1127E;letter-spacing:2px;font-size:15px}
.lux-review blockquote{margin:0;padding:0;border:0;font:400 18px/1.5 Fraunces,serif;color:#3B1530;font-style:normal}
.lux-review figcaption{display:flex;align-items:center;gap:12px;margin-top:auto}
.lux-review figcaption strong{display:block;font:700 15px/1.3 Manrope,sans-serif;color:#2A1A24}
.lux-review figcaption em{display:block;font:400 14px/1.3 Manrope,sans-serif;color:#6E5A66;font-style:normal}
.lux-review__avatar{width:40px;height:40px;border-radius:50%;background:#3B1530;color:#FF8CC8;display:flex;align-items:center;justify-content:center;font:600 16px Fraunces,serif;flex-shrink:0}
@media (max-width:767px){.lux-review{width:300px;padding:22px}.lux-review blockquote{font-size:17px}}
/* Hours */
.lux-hours{list-style:none;margin:0;padding:0}
.lux-hours li{display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid #5A2E4C;margin:0}
.lux-hours li:last-child{border-bottom:0}
.lux-hours b{color:#FFF8F6;font-weight:600}
/* Respect reduced motion */
@media (prefers-reduced-motion:reduce){.lux-bob,.lux-live .elementor-heading-title::before,.lux-reviews__track{animation:none!important}.lux-reviews{overflow-x:auto}.elementor-invisible{visibility:visible!important}.animated{animation:none!important}.elementor-button:hover,.lux-row-link:hover,.lux-card:hover{transform:none}}
/* Header and footer (Astra) */
.ast-primary-header-bar,.ast-mobile-header-wrap .ast-primary-header-bar{background:#FFF8F6!important;border-bottom:1px solid #EADDE0!important}
.site-title a,.site-title a:hover{font-family:Fraunces,serif!important;font-weight:500;font-size:26px;letter-spacing:-.01em;color:#3B1530!important}
.main-header-menu .menu-link{font:600 15px/1.2 Manrope,sans-serif!important;color:#3B1530!important}
.main-header-menu .menu-item:hover>.menu-link{color:#26101F!important;text-decoration:underline;text-underline-offset:6px}
.ast-builder-button-wrap .ast-custom-button{background:#D1127E!important;color:#FFFFFF!important;border-radius:999px!important;padding:14px 24px!important;font:600 15px/1 Manrope,sans-serif!important;border:0!important}
.ast-builder-button-wrap .ast-custom-button:hover{background:#B00E6A!important;color:#fff!important}
.ast-mobile-header-wrap .menu-toggle .ast-mobile-svg{fill:#3B1530!important}
.ast-mobile-popup-drawer .ast-mobile-popup-inner{background:#FFF8F6}
.ast-mobile-header-wrap .ast-primary-header-bar .ast-builder-grid-row,.ast-mobile-header-wrap .site-primary-header-wrap{padding-left:16px!important;padding-right:16px!important}
.lux-bookbar select{text-overflow:ellipsis;white-space:nowrap;overflow:hidden}
.site-below-footer-wrap{background:#26101F!important;border-top:0!important}
.site-below-footer-wrap .ast-footer-copyright,.site-below-footer-wrap .ast-footer-copyright p{color:#F3DCE8!important;font:400 14px/1.6 Manrope,sans-serif}
.site-below-footer-wrap a{color:#FF8CC8!important}

/* Base */
body{background:#FFF8F6}
.elementor-widget-text-editor p:last-child{margin-bottom:0}
.elementor-widget-text-editor a{font-weight:600}
.elementor-heading-title em{font-weight:400}

/* Eyebrow pills */
.lux-pill .elementor-heading-title{display:inline-flex;align-items:center;gap:8px;padding:7px 14px;border-radius:999px;background:#fff;border:1px solid #EADDE0}
.lux-pill .elementor-heading-title::before{content:"";width:6px;height:6px;border-radius:50%;background:#E8B4B8;flex-shrink:0}
.lux-pill--dark .elementor-heading-title{background:transparent;border-color:#5A2E4C}
.lux-pill--dark .elementor-heading-title::before{display:none}
.lux-pill--mist .elementor-heading-title{background:#FBEFF0;border-color:transparent}
.lux-pill--mist .elementor-heading-title::before{background:#3B1530}
.lux-pill--plain .elementor-heading-title{border-color:transparent}

/* Badges and tags */
.lux-badge .elementor-heading-title{display:inline-block;padding:4px 10px;border-radius:999px;background:#FBEAE5}
.lux-badge--lime .elementor-heading-title{background:#D1127E;padding:6px 12px}
.lux-tag .elementor-heading-title{display:inline-block;padding:5px 12px;border-radius:999px;background:#FBEFF0}
.lux-tag--white .elementor-heading-title{background:#fff;padding:8px 14px}

/* Layout helpers */
.e-con.lux-fit{width:fit-content!important;max-width:100%}
.lux-center-abs{position:absolute!important;left:50%;top:50%;transform:translate(-50%,-50%);width:auto!important;z-index:2}
.lux-ratio-1 img{aspect-ratio:1/1;object-fit:cover;width:100%}
.lux-ratio-45 img{aspect-ratio:4/5;object-fit:cover;width:100%}
.lux-fill-img,.lux-fill-img .elementor-widget-container{height:100%}
.lux-fill-img img{width:100%;height:100%!important;min-height:260px;object-fit:cover}
.lux-shadow{box-shadow:0 18px 40px rgba(59,21,48,.14)}

/* Hero */
.lux-wave{position:absolute!important;left:0;right:0;top:46%;width:100%!important;max-width:none!important;pointer-events:none;z-index:0;margin:0!important}
.lux-wave svg{display:block;width:100%;height:200px}
.lux-hero > .e-con-inner > .e-con{position:relative;z-index:1}
.lux-avatar img{width:36px!important;height:36px!important;border-radius:50%;border:2px solid #fff;object-fit:cover;display:block}
.lux-avatar + .lux-avatar{margin-left:-22px!important}
.lux-live .elementor-heading-title{display:flex;align-items:center;gap:8px}
.lux-live .elementor-heading-title::before{content:"";width:8px;height:8px;border-radius:50%;background:#FA26A0;box-shadow:0 0 0 4px #F6DDE1;flex-shrink:0}
.lux-slot{flex:1 1 0!important}
.lux-slot .elementor-heading-title{display:block;padding:8px 0;border:1px solid #E8B4B8;border-radius:999px;text-align:center}
@media (max-width:767px){.lux-float{max-width:210px}.lux-wave{display:none!important}}
.e-con>.elementor-widget-html:has(.lux-ba),.e-con>.lux-ratio-1,.e-con>.lux-ratio-45{flex-shrink:0}
/* Column containers holding ratio media must not wrap (mobile wrap collapses their height) */
.e-con:has(> .elementor-widget-html .lux-ba),.e-con:has(> .lux-ratio-45),.e-con:has(> .lux-ratio-1){flex-wrap:nowrap!important}

/* Booking bar */
.lux-bookbar{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:8px;margin:0}
.lux-bookbar .lux-field{display:flex;flex-direction:column;justify-content:center;gap:4px;padding:12px 20px;border-radius:20px;background:#FFF8F6;margin:0;cursor:pointer}
.lux-bookbar .lux-field>span{font:600 13px/1.2 Manrope,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#6E5A66}
.lux-bookbar select,.lux-bookbar input{border:0!important;background:transparent!important;font:600 17px/1.4 Manrope,sans-serif!important;color:#3B1530!important;padding:0!important;margin:0;outline:none;box-shadow:none!important;width:100%;min-height:0;height:auto;-webkit-appearance:none;appearance:none;cursor:pointer}
.lux-bookbar button{display:flex;align-items:center;justify-content:center;gap:8px;min-height:64px;border:0;border-radius:20px;background:#3B1530;color:#FFF8F6;font:600 15px Manrope,sans-serif;cursor:pointer;transition:background .2s}
.lux-bookbar button:hover,.lux-bookbar button:focus{background:#26101F;color:#fff}

.lux-bookbar .lux-field strong,.lux-bookbar .lux-field a{font:600 17px/1.4 Manrope,sans-serif;color:#3B1530;text-decoration:none}
.lux-bookbar .lux-field a:hover{text-decoration:underline}

.lux-map{display:block;width:100%;height:440px;border:0;border-radius:28px}
.lux-textlink a{text-decoration:none;border-bottom:1px solid #E8B4B8;padding-bottom:2px}
.lux-textlink a:hover{border-color:#3B1530}

/* Concern guide */
.lux-concerns{list-style:none;margin:0;padding:0}
.lux-concerns li{display:flex;justify-content:space-between;align-items:baseline;gap:12px;padding:11px 0;border-bottom:1px solid #EADDE0;margin:0}
.lux-concerns li:last-child{border-bottom:0;padding-bottom:0}
.lux-concerns b{color:#3B1530;font-weight:700;text-align:right;flex-shrink:0;max-width:55%}

/* Trust row */
.lux-trust .elementor-icon-list-items{justify-content:space-between!important;row-gap:16px}
.lux-trust .elementor-icon-list-icon{width:28px;height:28px;border-radius:50%;background:#FBEFF0;display:inline-flex!important;align-items:center;justify-content:center;flex-shrink:0}

/* Treatment cards */
.lux-card{transition:transform .25s ease}
.lux-card:hover{transform:translateY(-4px)}

/* Numbered list */
.lux-ol ol{list-style:none;counter-reset:n;padding:0;margin:0;display:flex;flex-direction:column;gap:14px}
.lux-ol li{counter-increment:n;display:flex;gap:14px;margin:0}
.lux-ol li::before{content:counter(n);flex-shrink:0;width:26px;height:26px;border-radius:50%;border:1px solid #E8B4B8;color:#E8B4B8;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center}

/* Two-column check list */
.lux-cols-2 .elementor-icon-list-items{display:grid!important;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px 20px}
.lux-cols-2 .elementor-icon-list-item{margin:0!important;padding:0!important}

/* Membership rows */
.lux-row-link{transition:border-color .2s}
.lux-row-link:hover{border-color:#E8B4B8!important}

/* Before / after slider */
.lux-ba{position:relative;aspect-ratio:4/5;border-radius:28px;overflow:hidden;background:#F6DDE1;--pos:50%}
.lux-ba img{position:absolute;inset:0;width:100%!important;height:100%!important;max-width:none;object-fit:cover;margin:0}
.lux-ba__before{clip-path:inset(0 calc(100% - var(--pos)) 0 0)}
.lux-ba__line{position:absolute;top:0;bottom:0;left:var(--pos);width:2px;margin-left:-1px;background:#fff;pointer-events:none}
.lux-ba__line i{position:absolute;top:50%;left:50%;width:44px;height:44px;transform:translate(-50%,-50%);border-radius:50%;background:#fff;box-shadow:0 6px 18px rgba(59,21,48,.2);display:flex;align-items:center;justify-content:center;color:#3B1530;font:700 15px Manrope,sans-serif;font-style:normal}
.lux-ba__tag{position:absolute;top:14px;padding:5px 12px;border-radius:999px;font:700 13px Manrope,sans-serif;pointer-events:none}
.lux-ba__tag--b{left:14px;background:#fff;color:#3B1530}
.lux-ba__tag--a{right:14px;background:#3B1530;color:#FFF8F6}
.lux-ba input[type=range]{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:ew-resize;margin:0;padding:0}

/* Play button */
.lux-play .elementor-icon{box-shadow:0 18px 40px rgba(59,21,48,.2)}

/* FAQ accordion */
.lux-faq .e-n-accordion{border-top:1px solid #EADDE0}
.lux-faq .e-n-accordion-item{border-bottom:1px solid #EADDE0}
.lux-faq .e-n-accordion-item-title{padding:22px 0!important;border:0!important;background:transparent!important;gap:16px}
.lux-faq .e-n-accordion-item-title-text{color:#3B1530!important}
.lux-faq .e-n-accordion-item-title-icon{width:36px;height:36px;border-radius:50%;background:#FBEFF0;color:#3B1530;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .2s}
.lux-faq .e-n-accordion-item[open] .e-n-accordion-item-title-icon{background:#3B1530;color:#FFF8F6}
.lux-faq .e-n-accordion-item-title-icon svg{fill:currentColor!important;width:14px;height:14px}
.lux-faq .e-n-accordion-item-title-icon i{color:currentColor!important;font-size:14px}
.lux-faq .e-n-accordion-item > .e-con{padding:0 52px 24px 0!important;border:0!important;background:transparent!important}

/* CTA grid background */
.lux-grid-bg{background-image:linear-gradient(rgba(232,180,184,.08) 1px,transparent 1px),linear-gradient(90deg,rgba(232,180,184,.08) 1px,transparent 1px)!important;background-size:48px 48px!important}

/* Treatment pages */
.lux-chips{display:flex;flex-wrap:wrap;gap:8px}
.lux-chips span{display:inline-flex;align-items:center;padding:9px 16px;border-radius:999px;background:#fff;border:1px solid #EADDE0;font:600 14px/1 Manrope,sans-serif;color:#3B1530}
.lux-chips span:first-child{background:#3B1530;border-color:#3B1530;color:#FFF8F6}
.lux-crumbs a{color:#D1127E;text-decoration:none}
.lux-crumbs a:hover{text-decoration:underline}
.lux-book-card{box-shadow:0 24px 60px rgba(59,21,48,.12)}
.lux-row-link .lux-textlink a{position:relative;z-index:1}

/* v4: contrast, counters, photo hero, dark concern list */
.lux-textlink--light a{color:#FFD6EC!important;border-bottom-color:rgba(255,214,236,.55)!important}
.lux-textlink--light a:hover{color:#fff!important;border-bottom-color:#fff!important}
.lux-counter .elementor-counter-number-wrapper{justify-content:flex-start;font-variant-numeric:tabular-nums}
.lux-counter .elementor-counter-title{text-align:left;text-transform:none!important;letter-spacing:0!important;font:500 15px/1.4 Manrope,sans-serif!important;color:#6E5A66}
.lux-counter .elementor-counter-number-wrapper{font-family:Fraunces,serif!important;color:#3B1530}
.lux-concerns-dark .lux-concerns li{border-bottom-color:#5A2E4C;color:#F3DCE8}
.lux-concerns-dark .lux-concerns b,.lux-concerns-dark .lux-concerns a{color:#FFD6EC;text-decoration:none}
.lux-concerns-dark .lux-concerns a:hover{color:#fff}
.lux-photo-hero .elementor-heading-title em{color:#FFB8DD}
.lux-review__photo{width:48px;height:48px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid #fff;box-shadow:0 4px 12px rgba(59,21,48,.15)}
.lux-video-hero{min-height:560px}
@media(prefers-reduced-motion:reduce){.lux-video-hero .elementor-background-video-container{display:none}}

/* ===== v6: mobile menu ===== */
#ast-mobile-popup .ast-mobile-popup-inner{background:#26101F radial-gradient(120% 60% at 100% 0%,rgba(209,18,126,.35),transparent 60%)!important;color:#F3DCE8;max-width:100%!important;padding:0 0 32px}
#ast-mobile-popup .ast-mobile-popup-header{display:flex;align-items:center;justify-content:space-between;padding:22px 24px 8px;min-height:72px}
#ast-mobile-popup .ast-mobile-popup-header::before{content:"Menu";font:500 15px/1 Manrope,sans-serif;letter-spacing:.16em;text-transform:uppercase;color:#FF8CC8}
#ast-mobile-popup .menu-toggle-close{width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.08)!important;display:flex;align-items:center;justify-content:center;padding:0!important}
#ast-mobile-popup .menu-toggle-close svg{fill:#FFF8F6!important;width:18px;height:18px}
#ast-mobile-popup .ast-mobile-popup-content{padding:8px 24px 0!important}
#ast-mobile-popup .main-header-menu{background:transparent!important;border:0!important}
#ast-mobile-popup .main-header-menu .menu-item{border:0!important;background:transparent!important}
#ast-mobile-popup .main-header-menu .menu-link{display:flex!important;justify-content:space-between;align-items:center;padding:16px 0!important;border-bottom:1px solid rgba(255,214,236,.14)!important;font:400 32px/1.1 Fraunces,serif!important;color:#FFF8F6!important;background:transparent!important}
#ast-mobile-popup .main-header-menu .menu-link::after{content:"\2192";font:400 20px Manrope,sans-serif;color:#FF8CC8;opacity:.8}
#ast-mobile-popup .main-header-menu .current-menu-item>.menu-link{color:#FF8CC8!important}
#ast-mobile-popup .ast-header-button-1{width:100%;margin-top:28px}
#ast-mobile-popup .ast-header-button-1 .ast-builder-button-wrap{width:100%}
#ast-mobile-popup .ast-header-button-1 .ast-custom-button-link{display:block;width:100%}
#ast-mobile-popup .ast-header-button-1 .ast-custom-button{display:flex!important;justify-content:center;align-items:center;width:100%;min-height:58px;border-radius:999px!important;background:#D1127E!important;color:#fff!important;font:700 17px Manrope,sans-serif!important}
#ast-mobile-popup .ast-header-button-1 .menu-link{display:none!important}
#ast-mobile-popup .ast-header-html-1{width:100%;margin-top:28px}
.lux-mnav-info{display:grid;grid-template-columns:1fr 1fr;gap:18px 16px;padding-top:24px;border-top:1px solid rgba(255,214,236,.14);font:400 14px/1.5 Manrope,sans-serif;color:#F3DCE8}
.lux-mnav-info p{margin:0}
.lux-mnav-info p:first-child{grid-column:1/-1}
.lux-mnav-info b{display:block;margin-bottom:4px;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#FF8CC8}
.lux-mnav-info a{color:#FFF8F6!important;font:500 20px Fraunces,serif;text-decoration:none}
@keyframes luxMenuIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
#ast-mobile-popup.active .main-header-menu>.menu-item{animation:luxMenuIn .45s ease both}
#ast-mobile-popup.active .main-header-menu>.menu-item:nth-child(2){animation-delay:.04s}
#ast-mobile-popup.active .main-header-menu>.menu-item:nth-child(3){animation-delay:.08s}
#ast-mobile-popup.active .main-header-menu>.menu-item:nth-child(4){animation-delay:.12s}
#ast-mobile-popup.active .main-header-menu>.menu-item:nth-child(5){animation-delay:.16s}
#ast-mobile-popup.active .main-header-menu>.menu-item:nth-child(6){animation-delay:.2s}
.ast-mobile-header-wrap .ast-button-wrap .menu-toggle{width:44px;height:44px;border-radius:50%;background:#FBEFF0!important;display:flex;align-items:center;justify-content:center;padding:0!important}
.ast-mobile-header-wrap .menu-toggle .ahfb-svg-iconset svg{fill:#3B1530!important}

/* ===== v6: mobile layout ===== */
@media (max-width:767px){
  .lux-mscroll{display:flex!important;overflow-x:auto;scroll-snap-type:x mandatory;gap:12px!important;margin:0 -16px;padding:0 16px 6px;scrollbar-width:none}
  .lux-mscroll::-webkit-scrollbar,.skba-grid::-webkit-scrollbar{display:none}
  .lux-mscroll>.e-con{flex:0 0 78%!important;width:78%!important;min-height:340px!important;scroll-snap-align:start}
  .skba-grid{display:flex!important;overflow-x:auto;scroll-snap-type:x mandatory;gap:12px;margin:0 -16px;padding:0 16px 6px;scrollbar-width:none}
  .skba-grid .skba{flex:0 0 82%;scroll-snap-align:start}
  .skba-n1 .skba{flex-basis:100%}
  .lux-story-img img{max-width:300px!important;margin:0 auto}
  .lux-steps .elementor-heading-title{font-size:18px!important}
  .lux-steps div.elementor-heading-title,.lux-steps .elementor-widget-heading:first-child .elementor-heading-title{font-size:32px!important}
  .lux-steps .elementor-widget-text-editor{font-size:14px!important;line-height:21px!important}
  .site-footer .ast-footer-copyright,.site-below-footer-wrap{padding-left:16px!important;padding-right:16px!important}
  .site-footer .ast-footer-copyright p{font-size:13px;line-height:1.7}
  .elementor-widget-heading h2.elementor-heading-title{text-wrap:balance}
  body{padding-bottom:72px}
}
.lux-mbar{display:none}
@media (max-width:767px){
  .lux-mbar{display:flex;position:fixed;left:12px;right:12px;bottom:12px;z-index:9990;gap:8px;padding:8px;border-radius:999px;background:rgba(38,16,31,.94);backdrop-filter:blur(8px);box-shadow:0 12px 30px rgba(38,16,31,.35)}
  .lux-mbar a{flex:1;display:flex;align-items:center;justify-content:center;min-height:46px;border-radius:999px;font:700 15px Manrope,sans-serif;text-decoration:none}
  .lux-mbar a.lux-mbar__book{background:#D1127E;color:#fff}
  .lux-mbar a.lux-mbar__call{flex:0 0 46px;background:rgba(255,255,255,.1);color:#FFF8F6}
  .lux-mbar a.lux-mbar__call svg{width:18px;height:18px;fill:#FFF8F6}
  .ast-popup-nav-open .lux-mbar{display:none}
}

/* ===== v6: header cart ===== */
.ast-header-woo-cart .ast-site-header-cart-li>a.cart-container{display:flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:50%;background:#FBEFF0;padding:0!important;line-height:1!important}
.ast-header-woo-cart .ast-addon-cart-wrap{padding:0!important;border:0!important;background:transparent!important}
.ast-header-woo-cart .astra-icon{position:relative;display:flex;line-height:1}
.ast-header-woo-cart .ast-icon svg{width:20px!important;height:20px!important;fill:#3B1530!important}
.ast-header-woo-cart .astra-icon::after{content:attr(data-cart-total)!important;position:absolute;top:-9px;right:-11px;min-width:18px;height:18px;padding:0 4px;border-radius:999px;background:#D1127E!important;color:#fff!important;border:2px solid #FFF8F6!important;font:700 10px/14px Manrope,sans-serif!important;text-align:center;box-sizing:border-box}
.ast-header-woo-cart .astra-icon[data-cart-total="0"]::after{display:none!important}
.ast-header-woo-cart .ast-site-header-cart-data{display:none!important}
.ast-mobile-header-wrap .ast-grid-right-section{gap:10px}

/* ===== v6b: mobile menu layout fixes ===== */
#ast-mobile-popup .main-header-menu,#ast-mobile-popup .main-navigation ul{display:flex!important;flex-direction:column!important;width:100%!important}
#ast-mobile-popup .main-header-menu>.menu-item{width:100%!important;margin:0!important}
#ast-mobile-popup .ast-mobile-popup-content>.ast-builder-layout-element,#ast-mobile-popup .ast-mobile-popup-content .ast-builder-menu-mobile,#ast-mobile-popup .main-header-bar-navigation,#ast-mobile-popup .site-navigation{width:100%!important;margin:0!important;padding:0!important}
#ast-mobile-popup .ast-header-button-1,#ast-mobile-popup .ast-header-html-1{justify-content:stretch!important;padding:0!important}
#ast-mobile-popup .ast-header-button-1 .ast-custom-button{padding:0 24px!important}
@media (max-width:767px){#ast-scroll-top{bottom:88px!important;right:16px!important;border-radius:50%!important;width:44px;height:44px;display:flex;align-items:center;justify-content:center;background:#3B1530!important}}

/* hero overlay: stronger on phones where text sits over the whole photo */
@media (max-width:767px){.lux-photo-hero>.elementor-background-overlay,.lux-photo-hero::before{background-image:linear-gradient(180deg,rgba(38,16,31,.55) 0%,rgba(38,16,31,.86) 100%)!important}}
CSS;
}
