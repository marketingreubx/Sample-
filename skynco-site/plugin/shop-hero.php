<?php
/**
 * [skynco_shop_hero] Shop page hero: a full-width slideshow of styled product
 * scenes with the copy over the image (crossfades, pauses on hover, swipe on phones).
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
		[ 'shop-hero-glow.jpg', 'glow-kit', 'Shop home care', 'Studio results, <em>at home.</em>', 'The professional skincare Hana uses in the treatment room, from Dermalogica, Image Skincare, iS Clinical, EltaMD and Alastin.', '72% 30%' ],
		[ 'shop-hero-hydrate.jpg', 'hydrating-hyaluronic-serum', 'Hydration', 'Hydration that <em>cools.</em>', 'A cult serum for instant, cooling moisture. Hana’s go-to after peels and facials.', '70% 60%' ],
		[ 'shop-hero-brighten.jpg', 'vitamin-c-brightening-serum', 'Brighten', 'Your glow, <em>bottled.</em>', 'Vitamin C for brighter, plumper, more even skin from the first week.', '75% 60%' ],
		[ 'shop-hero-exfoliate.jpg', 'enzyme-exfoliating-mask', 'Exfoliate', 'Smoother skin, <em>every day.</em>', 'The rice-powder exfoliant that brightens and refines without irritation.', '78% 30%' ],
		[ 'shop-hero-recover.jpg', 'post-treatment-recovery-balm', 'Recovery', 'Heal faster <em>between visits.</em>', 'Soothes, protects and supports skin after peels, microneedling and laser.', '70% 55%' ],
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
		$dots   = '';
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
			$rating = $p->get_review_count() ? skynco_stars_html( $p->get_average_rating(), $p->get_review_count() ) : '';
			$thumb  = wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' );
			$cta    = 0 === $i
				? '<a class="skh-btn" href="#kits">Shop the kits</a><a class="skh-btn skh-btn--ghost" href="#routine">Find your routine</a>'
				: '<a class="skh-btn" href="' . esc_url( $link ) . '">Shop now</a><a class="skh-btn skh-btn--ghost" href="#all">Browse all products</a>';
			$card   = '<div class="skh-cards"><div class="skh-glass"><span class="skh-dotp"></span><b>' . ( 0 === $i ? 'Free shipping over $75' : 'Hana’s pick' ) . '</b><span>' . ( 0 === $i ? 'Or free pickup at the studio in Watertown' : 'Used in the treatment room and chosen for home care' ) . '</span></div>'
				. '<div class="skh-card"><a class="skh-card__img" href="' . esc_url( $link ) . '"><img src="' . esc_url( $thumb ) . '" alt="" loading="lazy"></a><div class="skh-card__body"><p class="skh-card__brand">' . esc_html( $brand ) . '</p><a class="skh-card__name" href="' . esc_url( $link ) . '">' . esc_html( $short ) . '</a>'
				. ( $rating ? '<div class="skh-card__rate">' . $rating . '</div>' : '' )
				. '<div class="skh-card__buy"><span class="skh-card__price">' . $p->get_price_html() . '</span><a class="skh-card__add" href="' . esc_url( $add ) . '" rel="nofollow" aria-label="' . esc_attr( 'Add ' . $p->get_name() . ' to bag' ) . '">Add to bag</a></div></div></div></div>';
			$slides .= '<div class="skh-slide' . ( 0 === $i ? ' is-on' : '' ) . '" aria-roledescription="slide" aria-label="' . esc_attr( ( $i + 1 ) . ' of ' . count( $all ) ) . '"' . ( $i ? ' aria-hidden="true"' : '' ) . '>'
				. '<img class="skh-bg" src="' . esc_url( skynco_shop_hero_bg( $file ) ) . '" alt="" style="object-position:' . esc_attr( $pos ) . '"' . ( $i ? ' loading="lazy"' : ' fetchpriority="high"' ) . '>'
				. '<div class="skh-wrap"><div class="skh-copy"><p class="skh-eyebrow">' . esc_html( $eyebrow ) . '</p><' . $tag . ' class="skh-title">' . wp_kses( $title, [ 'em' => [] ] ) . '</' . $tag . '><p class="skh-lead">' . esc_html( $text ) . '</p>'
				. '<div class="skh-cta">' . $cta . '</div></div>' . $card . '</div></div>';
			$dots   .= '<button type="button" class="skh-dot' . ( 0 === $i ? ' is-on' : '' ) . '" data-go="' . $i . '" aria-label="' . esc_attr( 'Slide ' . ( $i + 1 ) ) . '"><b>' . sprintf( '%02d', $i + 1 ) . '</b><span>' . esc_html( 0 === $i ? 'Shop' : $eyebrow ) . '</span><i><em></em></i></button>';
			$i++;
		}
		ob_start();
		?>
<section class="skh" aria-label="Featured products" tabindex="0">
	<div class="skh-slides" aria-live="polite"><?php echo $slides; // phpcs:ignore ?></div>
	<div class="skh-ctrl"><div class="skh-ctrl__in">
		<div class="skh-dots"><?php echo $dots; // phpcs:ignore ?></div>
		<div class="skh-arrows">
			<button type="button" class="skh-arrow" data-dir="-1" aria-label="Previous slide"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg></button>
			<button type="button" class="skh-arrow" data-dir="1" aria-label="Next slide"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></button>
		</div>
	</div></div>
</section>
<script>
(function(){
	var s=document.currentScript.previousElementSibling, sl=s.querySelectorAll('.skh-slide'), dt=s.querySelectorAll('.skh-dot'), n=sl.length, i=0, t;
	if(n<2) return;
	function go(k){ i=(k+n)%n; sl.forEach(function(e,j){e.classList.toggle('is-on',j===i);e.setAttribute('aria-hidden',j===i?'false':'true');}); dt.forEach(function(e,j){e.classList.toggle('is-on',j===i);}); restart(); }
	function restart(){ clearInterval(t); s.classList.remove('is-run'); void s.offsetWidth; if(!window.matchMedia('(prefers-reduced-motion: reduce)').matches){ s.classList.add('is-run'); t=setInterval(function(){go(i+1);},6000);} }
	s.querySelectorAll('.skh-arrow').forEach(function(b){b.addEventListener('click',function(){go(i+ +b.dataset.dir);});});
	dt.forEach(function(b){b.addEventListener('click',function(){go(+b.dataset.go);});});
	s.addEventListener('mouseenter',function(){clearInterval(t);s.classList.add('is-paused');});
	s.addEventListener('mouseleave',function(){s.classList.remove('is-paused');restart();});
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
.skh-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transform:scale(1.08);transition:transform 8s ease-out}
.skh-slide.is-on .skh-bg{transform:scale(1)}
.skh-slide::before{content:"";position:absolute;inset:0;z-index:1;background:linear-gradient(90deg,rgba(38,16,31,.94) 28%,rgba(38,16,31,.12) 92%)}
.skh-wrap{position:relative;z-index:2;width:100%;max-width:1200px;margin:0 auto;padding:110px 24px 190px;display:flex;align-items:center;justify-content:space-between;gap:48px}
.skh-copy{max-width:600px;min-width:0}
.skh-copy>*,.skh-cards>*{opacity:0;transform:translateY(18px);transition:opacity .8s ease,transform .8s ease}
.skh-slide.is-on .skh-copy>*,.skh-slide.is-on .skh-cards>*{opacity:1;transform:none}
.skh-slide.is-on .skh-copy>*:nth-child(2){transition-delay:.12s}.skh-slide.is-on .skh-copy>*:nth-child(3){transition-delay:.22s}.skh-slide.is-on .skh-copy>*:nth-child(4){transition-delay:.32s}
.skh-slide.is-on .skh-cards>*:nth-child(1){transition-delay:.35s}.skh-slide.is-on .skh-cards>*:nth-child(2){transition-delay:.5s}
.skh-eyebrow{display:inline-flex;align-items:center;gap:8px;font:700 12px/1 Manrope,sans-serif;letter-spacing:.18em;text-transform:uppercase;color:#FFB3D9}
.skh-eyebrow::before{content:"";width:28px;height:1px;background:#FFB3D9}
.skh .skh-title{margin:18px 0 22px!important;font:500 clamp(42px,5.2vw,68px)/1.06 Fraunces,serif!important;color:#FBF6F4!important;letter-spacing:-.01em}
.skh-title em{font-style:italic;color:#FF8FCB}
.skh-lead{max-width:540px;font-size:18px;line-height:1.7;color:#EBD7E1}
.skh-cta{display:flex;gap:12px;flex-wrap:wrap;margin-top:32px}
.skh-btn{display:inline-flex;align-items:center;padding:16px 28px;border-radius:999px;background:#D1127E;color:#fff!important;font:600 16px Manrope,sans-serif;text-decoration:none!important;transition:transform .2s,background .2s}
.skh-btn:hover{background:#B00F6A;transform:translateY(-2px)}
.skh-btn--ghost{background:transparent;border:1px solid rgba(255,214,236,.55)}
.skh-btn--ghost:hover{background:rgba(255,255,255,.1)}
.skh-cards{flex:0 0 330px;display:flex;flex-direction:column;align-items:flex-end;gap:16px;align-self:flex-end}
.skh-glass{width:290px;display:grid;grid-template-columns:auto 1fr;gap:4px 8px;padding:18px 22px;border-radius:20px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.3);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px)}
.skh-dotp{width:8px;height:8px;border-radius:50%;background:#D1127E;margin-top:6px}
.skh-glass b{font:700 14.5px Manrope,sans-serif;color:#fff}.skh-glass span:last-child{grid-column:2;font-size:13.5px;line-height:1.45;color:#F3E3EA}
.skh-card{width:330px;display:flex;gap:16px;align-items:center;padding:16px;border-radius:24px;background:#fff;color:#2A1A24;box-shadow:0 30px 60px -30px rgba(0,0,0,.55)}
.skh-card__img{flex:0 0 96px;height:96px;border-radius:16px;overflow:hidden;background:#F6ECEF}
.skh-card__img img{width:100%;height:100%;object-fit:cover;display:block}
.skh-card__body{min-width:0;display:flex;flex-direction:column;gap:4px}
.skh-card__brand{font:700 10.5px/1.2 Manrope,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#D1127E}
.skh-card__name{font:500 17px/1.25 Fraunces,serif;color:#3B1530!important;text-decoration:none!important}
.skh-card__rate .sk-stars{font-size:12px}.skh-card__rate .sk-stars__n{font-size:11.5px}
.skh-card__buy{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:4px}
.skh-card__price{font:700 15px Manrope,sans-serif;color:#2A1A24}.skh-card__price del{font-size:12.5px;color:#9A8791;font-weight:500;margin-right:3px}.skh-card__price ins{text-decoration:none;color:#D1127E}
.skh-card__add{padding:8px 14px;border-radius:999px;background:#3B1530;color:#fff!important;font:700 12.5px Manrope,sans-serif;text-decoration:none!important;white-space:nowrap}
.skh-card__add:hover{background:#D1127E}
.skh-ctrl{position:absolute;left:0;right:0;bottom:84px;z-index:3;pointer-events:none}
.skh-ctrl__in{max-width:1200px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;gap:20px}
.skh-dots{display:flex;gap:18px;pointer-events:auto}
.skh-dot{display:grid;grid-template-columns:auto 1fr;gap:2px 8px;width:112px;padding:0!important;background:none!important;border:0!important;text-align:left;cursor:pointer;color:#fff!important;opacity:.6;transition:opacity .2s}
.skh-dot.is-on,.skh-dot:hover{opacity:1}
.skh-dot b{font:700 12px Manrope,sans-serif}.skh-dot span{font:600 12px Manrope,sans-serif;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.skh-dot i{grid-column:1/-1;height:2px;margin-top:7px;background:rgba(255,255,255,.25);border-radius:2px;overflow:hidden}
.skh-dot em{display:block;height:100%;width:0;background:#FF8FCB}
.skh-dot.is-on em{width:100%}
.skh.is-run .skh-dot.is-on em{width:0;animation:skhbar 6s linear forwards}
.skh.is-paused .skh-dot.is-on em{animation-play-state:paused}
@keyframes skhbar{to{width:100%}}
.skh-arrows{display:flex;gap:10px;pointer-events:auto}
.skh-arrow{width:48px;height:48px;border-radius:50%!important;border:1px solid rgba(255,255,255,.35)!important;background:rgba(255,255,255,.08)!important;color:#fff!important;display:grid;place-items:center;cursor:pointer;padding:0!important;backdrop-filter:blur(6px)}
.skh-arrow:hover{background:rgba(255,255,255,.18)!important}
.sk-stars{position:relative;display:inline-block;font-size:15px;line-height:1;letter-spacing:2px;vertical-align:middle}
.sk-stars__bg{color:#E9D3DB}.sk-stars__fg{position:absolute;left:0;top:0;overflow:hidden;white-space:nowrap;color:#D1127E}
.sk-stars__n{margin-left:6px;font:700 13px Manrope,sans-serif;color:#2A1A24;vertical-align:middle}.sk-stars__n span{color:#9A8791;font-weight:600}
@media(max-width:1024px){.skh-wrap{flex-direction:column;align-items:flex-start;gap:36px}.skh-cards{align-self:flex-start;align-items:flex-start;flex-basis:auto}.skh-glass{display:none}}
@media(max-width:760px){
.skh-slides{min-height:680px}
.skh-slide{align-items:flex-end}
.skh-slide::before{background:linear-gradient(180deg,rgba(38,16,31,.35) 0%,rgba(38,16,31,.7) 38%,rgba(38,16,31,.95) 70%)}
.skh-wrap{padding:150px 16px 150px;gap:24px}
.skh .skh-title{font-size:40px!important;margin:14px 0 14px!important}
.skh-lead{font-size:16px}
.skh-cta{margin-top:24px}.skh-cta .skh-btn{flex:1;justify-content:center;padding:14px 16px;font-size:15px}
.skh-card{width:100%;padding:12px}.skh-card__img{flex-basis:76px;height:76px}.skh-cards{width:100%}
.skh-ctrl{bottom:76px}
.skh-dots{gap:8px}.skh-dot{width:auto;grid-template-columns:1fr}.skh-dot b,.skh-dot span{display:none}.skh-dot i{width:28px;margin:0}
.skh-arrow{width:40px;height:40px}
}
@media(prefers-reduced-motion:reduce){.skh-bg,.skh-copy>*,.skh-cards>*{transition:none!important;transform:none!important}}
</style>
		<?php
	}
);
