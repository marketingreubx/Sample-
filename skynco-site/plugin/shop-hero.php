<?php
/**
 * [skynco_shop_hero] Shop page hero: a full-width slideshow of styled product
 * photos with the copy over the image (crossfades every 4s, swipe on phones).
 * Scene images live in the media library; their IDs are kept in the
 * skynco_shop_hero_bg option (file => attachment ID).
 *
 * @package SkyncoStudio
 */

defined( 'ABSPATH' ) || exit;

function skynco_brand_of( $name ) {
	foreach ( [ 'Dermalogica', 'Image Skincare', 'iS Clinical', 'EltaMD', 'Alastin', 'Skyn&Co.' ] as $b ) {
		if ( 0 === stripos( $name, $b ) ) {
			return [ $b, trim( substr( $name, strlen( $b ) ) ) ];
		}
	}
	return [ 'Skyn&Co. kit', $name ];
}

function skynco_stars_html( $rating, $count = null ) {
	$rating = (float) $rating;
	$pct    = max( 0, min( 100, $rating / 5 * 100 ) );
	$out    = '<span class="sk-stars" role="img" aria-label="' . esc_attr( sprintf( 'Rated %s out of 5', number_format( $rating, 1 ) ) ) . '"><span class="sk-stars__bg">★★★★★</span><span class="sk-stars__fg" style="width:' . $pct . '%">★★★★★</span></span>';
	if ( null !== $count ) {
		$out .= '<span class="sk-stars__n">' . esc_html( number_format( $rating, 1 ) ) . ' <span>(' . (int) $count . ')</span></span>';
	}
	return $out;
}

/** Slides: background photo, featured product, copy. */
function skynco_shop_hero_slides() {
	return [
		[ 'shop-hero-alastin.jpg', 'post-treatment-recovery-balm', 'Shop home care', 'Studio results, <em>at home.</em>', 'The professional skincare Hana uses in the treatment room, from Dermalogica, Image Skincare, iS Clinical, EltaMD and Alastin.', '100% 50%' ],
		[ 'shop-hero-image-md.jpg', 'vitamin-c-brightening-serum', 'Image Skincare', 'Your glow, <em>bottled.</em>', 'VITAL C serum for brighter, plumper, more even skin from the first week.', '100% 30%' ],
		[ 'shop-hero-dermalogica-guasha.jpg', 'enzyme-exfoliating-mask', 'Dermalogica', 'Smoother skin, <em>every day.</em>', 'Daily Microfoliant: the rice-powder exfoliant that brightens and refines without irritation.', '100% 40%' ],
		[ 'shop-hero-dermalogica-men.jpg', 'gentle-cleansing-gel', 'Dermalogica', 'The cleanse <em>pros swear by.</em>', 'Special Cleansing Gel: a soap-free foaming gel for every skin type, his and hers.', '100% 30%' ],
	];
}

function skynco_shop_hero_bg( $file ) {
	$ids = (array) get_option( 'skynco_shop_hero_bg', [] );
	if ( ! empty( $ids[ $file ] ) && ( $url = wp_get_attachment_image_url( $ids[ $file ], 'full' ) ) ) {
		return $url;
	}
	return 'https://raw.githubusercontent.com/marketingreubx/Sample-/claude/wizardly-noether-iac7ie/skynco-site/assets/products/' . rawurlencode( $file );
}

add_shortcode(
	'skynco_shop_hero',
	function () {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}
		$all    = skynco_shop_hero_slides();
		$slides = '';
		$i      = 0;
		foreach ( $all as [ $file, $key, $eyebrow, $title, $text, $pos ] ) {
			$p = wc_get_product( skynco_shop_id( $key ) );
			if ( ! $p ) {
				continue;
			}
			[ $brand, $short ] = skynco_brand_of( $p->get_name() );
			$link   = get_permalink( $p->get_id() );
			$add    = add_query_arg( 'add-to-cart', $p->get_id(), wc_get_cart_url() );
			$tag    = 0 === $i ? 'h1' : 'h2';
			$thumb  = wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' );
			$cta    = 0 === $i
				? '<a class="skh-btn" href="#kits">Shop the kits</a><a class="skh-btn skh-btn--ghost" href="#routine">Find your routine</a>'
				: '<a class="skh-btn" href="' . esc_url( $link ) . '">Shop now</a><a class="skh-btn skh-btn--ghost" href="#all">Shop all</a>';
			$card   = '<div class="skh-card"><a class="skh-card__img" href="' . esc_url( $link ) . '"><img src="' . esc_url( $thumb ) . '" alt="" loading="lazy"></a><div class="skh-card__body"><p class="skh-card__brand">' . esc_html( $brand ) . '</p><a class="skh-card__name" href="' . esc_url( $link ) . '">' . esc_html( $short ) . '</a>'
				. '<div class="skh-card__buy"><span class="skh-card__price">' . $p->get_price_html() . '</span><a class="skh-card__add" href="' . esc_url( $add ) . '" rel="nofollow" aria-label="' . esc_attr( 'Add ' . $p->get_name() . ' to bag' ) . '">Add to bag</a></div></div></div>';
			$slides .= '<div class="skh-slide' . ( 0 === $i ? ' is-on' : '' ) . '" aria-roledescription="slide" aria-label="' . esc_attr( ( $i + 1 ) . ' of ' . count( $all ) ) . '"' . ( $i ? ' aria-hidden="true"' : '' ) . '>'
				. '<img class="skh-bg" src="' . esc_url( skynco_shop_hero_bg( $file ) ) . '" alt="" style="object-position:' . esc_attr( $pos ) . '"' . ( $i ? ' loading="lazy"' : ' fetchpriority="high"' ) . '>'
				. '<div class="skh-wrap"><div class="skh-copy"><' . $tag . ' class="skh-title">' . wp_kses( $title, [ 'em' => [] ] ) . '</' . $tag . '><p class="skh-lead">' . esc_html( $text ) . '</p>'
				. '<div class="skh-cta">' . $cta . '</div>' . $card . '</div></div></div>';
			$i++;
		}
		ob_start();
		?>
<section class="skh" aria-label="Featured products" tabindex="0">
	<div class="skh-slides" aria-live="polite"><?php echo $slides; // phpcs:ignore ?></div>
</section>
<script>
(function(){
	var s=document.currentScript.previousElementSibling, sl=s.querySelectorAll('.skh-slide'), n=sl.length, i=0, t;
	if(n<2) return;
	function go(k){ i=(k+n)%n; sl.forEach(function(e,j){e.classList.toggle('is-on',j===i);e.setAttribute('aria-hidden',j===i?'false':'true');}); restart(); }
	function restart(){ clearInterval(t); if(!window.matchMedia('(prefers-reduced-motion: reduce)').matches){t=setInterval(function(){go(i+1);},4000);} }
	s.addEventListener('keydown',function(e){if(e.key==='ArrowRight')go(i+1);if(e.key==='ArrowLeft')go(i-1);});
	var x0=null; s.addEventListener('touchstart',function(e){x0=e.touches[0].clientX;},{passive:true});
	s.addEventListener('touchend',function(e){if(x0===null)return;var dx=e.changedTouches[0].clientX-x0;if(Math.abs(dx)>40)go(i+(dx<0?1:-1));x0=null;});
	restart();
})();
</script>
		<?php
		return ob_get_clean();
	}
);

add_action(
	'wp_head',
	function () {
		?>
<style id="skynco-shop-hero">
.skh{position:relative;overflow:hidden;background:#26101F;color:#F7EAF0;font-family:Manrope,sans-serif;outline:0}
.skh *{box-sizing:border-box}.skh p{margin:0}
.skh-slides{position:relative;display:grid;min-height:760px}
.skh-slide{grid-area:1/1;position:relative;display:flex;align-items:center;opacity:0;visibility:hidden;transition:opacity 1.1s ease,visibility 0s 1.1s}
.skh-slide.is-on{opacity:1;visibility:visible;transition:opacity 1.1s ease;z-index:1}
.skh-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.skh-slide::before{content:"";position:absolute;inset:0;z-index:1;background:linear-gradient(90deg,rgba(38,16,31,.94) 28%,rgba(38,16,31,.12) 92%)}
.skh-wrap{position:relative;z-index:2;width:100%;max-width:1200px;margin:0 auto;padding:100px 24px 150px}
.skh-copy{max-width:600px;min-width:0}
.skh-copy>*{opacity:0;transform:translateY(18px);transition:opacity .8s ease,transform .8s ease}
.skh-slide.is-on .skh-copy>*{opacity:1;transform:none}
.skh-slide.is-on .skh-copy>*:nth-child(2){transition-delay:.12s}.skh-slide.is-on .skh-copy>*:nth-child(3){transition-delay:.22s}.skh-slide.is-on .skh-copy>*:nth-child(4){transition-delay:.32s}
.skh-slide.is-on .skh-copy>*:nth-child(5){transition-delay:.44s}
.skh .skh-title{margin:0 0 22px!important;font:500 clamp(42px,5.2vw,68px)/1.06 Fraunces,serif!important;color:#FBF6F4!important;letter-spacing:-.01em}
.skh-title em{font-style:italic;color:#FF8FCB}
.skh-lead{max-width:540px;font-size:18px;line-height:1.7;color:#EBD7E1}
.skh-cta{display:flex;gap:12px;flex-wrap:wrap;margin-top:32px}
.skh-btn{display:inline-flex;align-items:center;padding:16px 28px;border-radius:999px;background:#D1127E;color:#fff!important;font:600 16px Manrope,sans-serif;text-decoration:none!important;transition:transform .2s,background .2s}
.skh-btn:hover{background:#B00F6A;transform:translateY(-2px)}
.skh-btn--ghost{background:transparent;border:1px solid rgba(255,214,236,.55)}
.skh-btn--ghost:hover{background:rgba(255,255,255,.1)}
.skh-card{width:360px;max-width:100%;margin-top:28px;display:flex;gap:16px;align-items:center;padding:16px;border-radius:24px;background:#fff;color:#2A1A24;box-shadow:0 30px 60px -30px rgba(0,0,0,.55)}
.skh-card__img{flex:0 0 96px;height:96px;border-radius:16px;overflow:hidden;background:#F6ECEF}
.skh-card__img img{width:100%;height:100%;object-fit:cover;display:block}
.skh-card__body{min-width:0;display:flex;flex-direction:column;gap:4px}
.skh-card__brand{font:700 10.5px/1.2 Manrope,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#D1127E}
.skh-card__name{font:500 17px/1.25 Fraunces,serif;color:#3B1530!important;text-decoration:none!important}
.skh-card__buy{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:4px}
.skh-card__price{font:700 15px Manrope,sans-serif;color:#2A1A24}.skh-card__price del{font-size:12.5px;color:#9A8791;font-weight:500;margin-right:3px}.skh-card__price ins{text-decoration:none;color:#D1127E}
.skh-card__add{padding:8px 14px;border-radius:999px;background:#3B1530;color:#fff!important;font:700 12.5px Manrope,sans-serif;text-decoration:none!important;white-space:nowrap}
.skh-card__add:hover{background:#D1127E}
.sk-stars{position:relative;display:inline-block;font-size:15px;line-height:1;letter-spacing:2px;vertical-align:middle}
.sk-stars__bg{color:#E9D3DB}.sk-stars__fg{position:absolute;left:0;top:0;overflow:hidden;white-space:nowrap;color:#D1127E}
.sk-stars__n{margin-left:6px;font:700 13px Manrope,sans-serif;color:#2A1A24;vertical-align:middle}.sk-stars__n span{color:#9A8791;font-weight:600}
@media(max-width:1024px){.skh-slide::before{background:linear-gradient(90deg,rgba(38,16,31,.94) 35%,rgba(38,16,31,.3) 100%)}}
@media(max-width:760px){
.skh-slides{min-height:680px}
.skh-slide{align-items:flex-end}
.skh-slide::before{background:linear-gradient(180deg,rgba(38,16,31,.35) 0%,rgba(38,16,31,.7) 38%,rgba(38,16,31,.95) 70%)}
.skh-wrap{padding:150px 16px 96px;gap:24px}
.skh .skh-title{font-size:40px!important;margin:0 0 14px!important}
.skh-lead{font-size:16px}
.skh-cta{margin-top:24px}.skh-cta .skh-btn{flex:1;justify-content:center;padding:14px 16px;font-size:15px}
.skh-card{width:100%;padding:12px;margin-top:22px}.skh-card__img{flex-basis:76px;height:76px}
}
@media(prefers-reduced-motion:reduce){.skh-copy>*{transition:none!important;transform:none!important}}
</style>
		<?php
	}
);
