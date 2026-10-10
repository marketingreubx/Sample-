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

/** Slides: scene image, featured product, copy. */
function skynco_shop_hero_slides() {
	return [
		[ 'hero-glow.jpg', 'vitamin-c-brightening-serum', '#FBEBE6', 'Shop home care', 'Studio results, <em>at home.</em>', 'The professional skincare Hana uses in the treatment room, from Dermalogica, Image Skincare, iS Clinical, EltaMD and Alastin.' ],
		[ 'hero-hydrate.jpg', 'hydrating-hyaluronic-serum', '#ECEEF8', 'Hydration', 'Hydration that <em>cools.</em>', 'A cult serum for instant, cooling moisture. Hana’s go-to after peels and facials.' ],
		[ 'hero-spf.jpg', 'daily-mineral-spf-40', '#FAF0E2', 'Daily protection', 'The SPF you’ll <em>actually wear.</em>', 'Sheer, oil-free and made for sensitive, breakout-prone skin. No white cast.' ],
		[ 'hero-exfoliate.jpg', 'enzyme-exfoliating-mask', '#FAEAEE', 'Exfoliate', 'Smoother skin, <em>every day.</em>', 'The rice-powder exfoliant that brightens and refines without irritation.' ],
		[ 'hero-recover.jpg', 'post-treatment-recovery-balm', '#F0ECF0', 'Recovery', 'Heal faster <em>between visits.</em>', 'Soothes, protects and supports skin after peels, microneedling and laser.' ],
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
		foreach ( $all as [ $file, $key, $tint, $eyebrow, $title, $text ] ) {
			$p = wc_get_product( skynco_shop_id( $key ) );
			if ( ! $p ) {
				continue;
			}
			$link   = get_permalink( $p->get_id() );
			$add    = add_query_arg( 'add-to-cart', $p->get_id(), wc_get_cart_url() );
			$tag    = 0 === $i ? 'h1' : 'h2';
			$rating = $p->get_review_count() ? skynco_stars_html( $p->get_average_rating(), $p->get_review_count() ) : '';
			$feat   = '<a class="skh-prod" href="' . esc_url( $link ) . '"><span class="skh-prod__name">' . esc_html( $p->get_name() ) . '</span><span class="skh-prod__meta">' . $rating . '<span class="skh-prod__price">' . $p->get_price_html() . '</span></span></a>';
			$cta    = 0 === $i
				? '<a class="skh-btn" href="#kits">Shop the kits</a><a class="skh-btn skh-btn--ghost" href="#routine">Find your routine</a>'
				: '<a class="skh-btn" href="' . esc_url( $add ) . '" rel="nofollow">Add to bag</a><a class="skh-btn skh-btn--ghost" href="' . esc_url( $link ) . '">View product</a>';
			$slides .= '<div class="skh-slide' . ( 0 === $i ? ' is-on' : '' ) . '" style="--tint:' . esc_attr( $tint ) . '" aria-roledescription="slide" aria-label="' . esc_attr( ( $i + 1 ) . ' of ' . count( $all ) ) . '"' . ( $i ? ' aria-hidden="true"' : '' ) . '>'
				. '<img class="skh-bg" src="' . esc_url( skynco_shop_hero_bg( $file ) ) . '" alt="' . esc_attr( $p->get_name() ) . '"' . ( $i ? ' loading="lazy"' : ' fetchpriority="high"' ) . '>'
				. '<div class="skh-wrap"><div class="skh-copy"><p class="skh-eyebrow">' . esc_html( $eyebrow ) . '</p><' . $tag . ' class="skh-title">' . wp_kses( $title, [ 'em' => [] ] ) . '</' . $tag . '><p class="skh-lead">' . esc_html( $text ) . '</p>'
				. ( $i ? $feat : '' ) . '<div class="skh-cta">' . $cta . '</div></div></div></div>';
			[ $brand ] = skynco_brand_of( $p->get_name() );
			$dots     .= '<button type="button" class="skh-dot' . ( 0 === $i ? ' is-on' : '' ) . '" data-go="' . $i . '" aria-label="' . esc_attr( 'Slide ' . ( $i + 1 ) ) . '"><b>' . sprintf( '%02d', $i + 1 ) . '</b><span>' . esc_html( 0 === $i ? 'Shop' : $eyebrow ) . '</span><i><em></em></i></button>';
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
.skh{position:relative;overflow:hidden;background:#FBEBE6;color:#2A1A24;font-family:Manrope,sans-serif;outline:0}
.skh *{box-sizing:border-box}.skh p{margin:0}
.skh-slides{position:relative;display:grid;min-height:clamp(600px,82vh,780px)}
.skh-slide{grid-area:1/1;position:relative;display:flex;align-items:center;background:var(--tint);opacity:0;visibility:hidden;transition:opacity 1s ease,visibility 0s 1s}
.skh-slide.is-on{opacity:1;visibility:visible;transition:opacity 1s ease;z-index:1}
.skh-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:78% 15%;transform:scale(1.06);transition:transform 7s ease-out}
.skh-slide.is-on .skh-bg{transform:scale(1)}
.skh-slide::before{content:"";position:absolute;inset:0;z-index:1;background:linear-gradient(90deg,var(--tint) 0%,color-mix(in srgb,var(--tint) 82%,transparent) 34%,transparent 60%)}
.skh-wrap{position:relative;z-index:2;width:100%;max-width:1200px;margin:0 auto;padding:72px 24px 128px}
.skh-copy{max-width:540px}
.skh-copy>*{opacity:0;transform:translateY(16px);transition:opacity .7s ease,transform .7s ease}
.skh-slide.is-on .skh-copy>*{opacity:1;transform:none}
.skh-slide.is-on .skh-copy>*:nth-child(2){transition-delay:.12s}.skh-slide.is-on .skh-copy>*:nth-child(3){transition-delay:.22s}.skh-slide.is-on .skh-copy>*:nth-child(4){transition-delay:.32s}.skh-slide.is-on .skh-copy>*:nth-child(5){transition-delay:.42s}
.skh-eyebrow{display:inline-block;padding:7px 14px;border-radius:999px;background:rgba(255,255,255,.7);font:700 11.5px/1 Manrope,sans-serif;letter-spacing:.16em;text-transform:uppercase;color:#D1127E}
.skh .skh-title{margin:18px 0 16px!important;font:400 clamp(42px,5.4vw,70px)/1.02 Fraunces,serif!important;color:#3B1530!important;letter-spacing:-.015em}
.skh-title em{font-style:italic;color:#D1127E}
.skh-lead{max-width:470px;font-size:17px;line-height:1.65;color:#5A4652}
.skh-prod{display:inline-flex;flex-direction:column;gap:6px;margin-top:22px;padding:14px 18px;border-radius:18px;background:rgba(255,255,255,.78);backdrop-filter:blur(8px);text-decoration:none!important;box-shadow:0 10px 30px -18px rgba(59,21,48,.5)}
.skh-prod__name{font:700 14.5px/1.35 Manrope,sans-serif;color:#2A1A24}
.skh-prod__meta{display:flex;align-items:center;flex-wrap:wrap;gap:6px 14px}
.skh-prod__price{font:700 15px Manrope,sans-serif;color:#3B1530}.skh-prod__price del{color:#9A8791;font-weight:500;margin-right:4px}.skh-prod__price ins{text-decoration:none;color:#D1127E}
.skh-cta{display:flex;gap:10px;flex-wrap:wrap;margin-top:26px}
.skh-btn{display:inline-flex;align-items:center;padding:15px 26px;border-radius:999px;background:#D1127E;color:#fff!important;font:700 15px Manrope,sans-serif;text-decoration:none!important;transition:transform .2s,background .2s;box-shadow:0 12px 24px -12px rgba(209,18,126,.7)}
.skh-btn:hover{background:#B00F6A;transform:translateY(-2px)}
.skh-btn--ghost{background:rgba(255,255,255,.6);color:#3B1530!important;border:1px solid rgba(59,21,48,.25);box-shadow:none}
.skh-btn--ghost:hover{background:#fff}
.skh-ctrl{position:absolute;left:0;right:0;bottom:64px;z-index:3;pointer-events:none}
.skh-ctrl__in{max-width:1200px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;gap:20px}
.skh-dots{display:flex;gap:18px;pointer-events:auto}
.skh-dot{display:grid;grid-template-columns:auto 1fr;gap:2px 8px;width:118px;padding:0!important;background:none!important;border:0!important;text-align:left;cursor:pointer;color:#3B1530!important;opacity:.55;transition:opacity .2s}
.skh-dot.is-on,.skh-dot:hover{opacity:1}
.skh-dot b{font:700 12px Manrope,sans-serif}.skh-dot span{font:600 12px Manrope,sans-serif;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.skh-dot i{grid-column:1/-1;height:2px;margin-top:6px;background:rgba(59,21,48,.18);border-radius:2px;overflow:hidden}
.skh-dot em{display:block;height:100%;width:0;background:#D1127E}
.skh-dot.is-on em{width:100%;transition:none}
.skh.is-run .skh-dot.is-on em{width:0;animation:skhbar 6s linear forwards}
.skh.is-paused .skh-dot.is-on em{animation-play-state:paused}
@keyframes skhbar{to{width:100%}}
.skh-arrows{display:flex;gap:10px;pointer-events:auto}
.skh-arrow{width:48px;height:48px;border-radius:50%!important;border:1px solid rgba(59,21,48,.2)!important;background:rgba(255,255,255,.75)!important;color:#3B1530!important;display:grid;place-items:center;cursor:pointer;padding:0!important;backdrop-filter:blur(6px)}
.skh-arrow:hover{background:#fff!important}
.sk-stars{position:relative;display:inline-block;font-size:15px;line-height:1;letter-spacing:2px;vertical-align:middle}
.sk-stars__bg{color:#E9D3DB}.sk-stars__fg{position:absolute;left:0;top:0;overflow:hidden;white-space:nowrap;color:#D1127E}
.sk-stars__n{margin-left:6px;font:700 13px Manrope,sans-serif;color:#2A1A24;vertical-align:middle}.sk-stars__n span{color:#9A8791;font-weight:600}
@media(max-width:1024px){.skh-slide::before{background:linear-gradient(90deg,var(--tint) 0%,color-mix(in srgb,var(--tint) 85%,transparent) 45%,transparent 75%)}.skh-bg{object-position:72% 15%}}
@media(max-width:760px){
.skh-slides{min-height:0}
.skh-slide{flex-direction:column;align-items:stretch}
.skh-bg{position:relative;inset:auto;height:330px;object-position:77% 40%}
.skh-slide::before{top:250px;bottom:auto;height:82px;background:linear-gradient(180deg,transparent,var(--tint))}
.skh-wrap{padding:0 16px 128px}
.skh-copy{max-width:none}
.skh .skh-title{font-size:38px!important;margin:14px 0 12px!important}
.skh-lead{font-size:15.5px}
.skh-cta .skh-btn{flex:1;justify-content:center;padding:14px 16px}
.skh-ctrl{bottom:62px}
.skh-dots{gap:8px}.skh-dot{width:auto;grid-template-columns:1fr}.skh-dot b,.skh-dot span{display:none}.skh-dot i{width:28px;margin:0}
.skh-arrow{width:40px;height:40px}
}
@media(prefers-reduced-motion:reduce){.skh-bg,.skh-copy>*{transition:none!important;transform:none!important}}
</style>
		<?php
	}
);
