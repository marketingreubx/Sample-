<?php
/**
 * [skynco_shop_hero] Shop page hero: headline on the left, a rotating product
 * spotlight on the right (auto-plays, pauses on hover, swipe on phones).
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

add_shortcode(
	'skynco_shop_hero',
	function () {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}
		$keys   = [ 'vitamin-c-brightening-serum', 'hydrating-hyaluronic-serum', 'daily-mineral-spf-40', 'enzyme-exfoliating-mask', 'post-treatment-recovery-balm', 'glow-kit' ];
		$lines  = [
			'vitamin-c-brightening-serum'  => 'Brighter, plumper skin from the first week.',
			'hydrating-hyaluronic-serum'   => 'Instant cooling hydration, perfect after treatments.',
			'daily-mineral-spf-40'         => 'The sheer daily SPF dermatologists love.',
			'enzyme-exfoliating-mask'      => 'The cult rice powder for smooth, glowing skin.',
			'post-treatment-recovery-balm' => 'Soothes and protects skin while it heals.',
			'glow-kit'                     => 'Cleanse, brighten and protect. Save $27.',
		];
		$slides = '';
		$thumbs = '';
		$i      = 0;
		foreach ( $keys as $k ) {
			$p = wc_get_product( skynco_shop_id( $k ) );
			if ( ! $p ) {
				continue;
			}
			[ $brand, $name ] = skynco_brand_of( $p->get_name() );
			$img     = wp_get_attachment_image_url( $p->get_image_id(), 'large' );
			$thumb   = wp_get_attachment_image_url( $p->get_image_id(), 'thumbnail' );
			$link    = get_permalink( $p->get_id() );
			$add     = add_query_arg( 'add-to-cart', $p->get_id(), wc_get_cart_url() );
			$rating  = $p->get_review_count() ? skynco_stars_html( $p->get_average_rating(), $p->get_review_count() ) : '';
			$slides .= '<article class="skh-slide' . ( 0 === $i ? ' is-on' : '' ) . '" data-i="' . $i . '" aria-roledescription="slide" aria-label="' . esc_attr( ( $i + 1 ) . ' of ' . count( $keys ) ) . '">'
				. '<a class="skh-stage" href="' . esc_url( $link ) . '"><img src="' . esc_url( $img ) . '" alt="' . esc_attr( $p->get_name() ) . '"' . ( $i ? ' loading="lazy"' : '' ) . '></a>'
				. '<div class="skh-info"><p class="skh-brand">' . esc_html( $brand ) . '</p><h2 class="skh-name"><a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a></h2><p class="skh-line">' . esc_html( $lines[ $k ] ?? '' ) . '</p>'
				. ( $rating ? '<div class="skh-rating">' . $rating . '</div>' : '' )
				. '<div class="skh-buy"><span class="skh-price">' . $p->get_price_html() . '</span><a class="skh-add" href="' . esc_url( $add ) . '" rel="nofollow">Add to bag</a><a class="skh-more" href="' . esc_url( $link ) . '">Details</a></div></div></article>';
			$thumbs .= '<button type="button" class="skh-thumb' . ( 0 === $i ? ' is-on' : '' ) . '" data-go="' . $i . '" aria-label="' . esc_attr( 'Show ' . $p->get_name() ) . '"><img src="' . esc_url( $thumb ) . '" alt=""><i></i></button>';
			$i++;
		}
		ob_start();
		?>
<section class="skh" aria-label="Featured products">
	<div class="skh-wrap">
		<div class="skh-copy">
			<p class="skh-eyebrow">Shop home care</p>
			<h1>Studio results, <em>at home.</em></h1>
			<p class="skh-lead">The professional skincare Hana uses in the treatment room: Dermalogica, Image Skincare, iS Clinical, EltaMD and Alastin. Free shipping over $75, or free pickup at the studio.</p>
			<div class="skh-cta"><a class="skh-btn" href="#kits">Shop the kits</a><a class="skh-btn skh-btn--ghost" href="#routine">Find your routine</a></div>
			<ul class="skh-trust"><li>Authentic professional brands</li><li>Chosen by your esthetician</li><li>10% off with GLOW10</li></ul>
		</div>
		<div class="skh-show" tabindex="0">
			<div class="skh-slides" aria-live="polite"><?php echo $slides; // phpcs:ignore ?></div>
			<div class="skh-nav">
				<button type="button" class="skh-arrow" data-dir="-1" aria-label="Previous product"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg></button>
				<div class="skh-thumbs"><?php echo $thumbs; // phpcs:ignore ?></div>
				<button type="button" class="skh-arrow" data-dir="1" aria-label="Next product"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></button>
			</div>
		</div>
	</div>
</section>
<script>
(function(){
	var s=document.currentScript.previousElementSibling, sl=s.querySelectorAll('.skh-slide'), th=s.querySelectorAll('.skh-thumb'), n=sl.length, i=0, t, show=s.querySelector('.skh-show');
	if(!n) return;
	function go(k){ i=(k+n)%n; sl.forEach(function(e,j){e.classList.toggle('is-on',j===i);}); th.forEach(function(e,j){e.classList.toggle('is-on',j===i);}); restart(); }
	function restart(){ clearInterval(t); if(!window.matchMedia('(prefers-reduced-motion: reduce)').matches){ t=setInterval(function(){go(i+1);},5200);} s.style.setProperty('--skh-run',''); void s.offsetWidth; s.style.setProperty('--skh-run','running'); }
	s.querySelectorAll('.skh-arrow').forEach(function(b){b.addEventListener('click',function(){go(i+ +b.dataset.dir);});});
	th.forEach(function(b){b.addEventListener('click',function(){go(+b.dataset.go);});});
	show.addEventListener('mouseenter',function(){clearInterval(t);s.classList.add('is-paused');});
	show.addEventListener('mouseleave',function(){s.classList.remove('is-paused');restart();});
	show.addEventListener('keydown',function(e){if(e.key==='ArrowRight')go(i+1);if(e.key==='ArrowLeft')go(i-1);});
	var x0=null; show.addEventListener('touchstart',function(e){x0=e.touches[0].clientX;},{passive:true});
	show.addEventListener('touchend',function(e){if(x0===null)return;var dx=e.changedTouches[0].clientX-x0;if(Math.abs(dx)>40)go(i+(dx<0?1:-1));x0=null;});
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
.skh{position:relative;overflow:hidden;background:radial-gradient(120% 120% at 85% 10%,#6A2453 0%,#3B1530 45%,#26101F 100%);color:#F7EAF0;font-family:Manrope,sans-serif}
.skh::before{content:"";position:absolute;right:-12%;top:-30%;width:70%;aspect-ratio:1;border-radius:50%;background:radial-gradient(circle,rgba(255,140,200,.22),transparent 65%);pointer-events:none}
.skh *{box-sizing:border-box}.skh p{margin:0}
.skh-wrap{position:relative;max-width:1200px;margin:0 auto;padding:72px 24px 64px;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.08fr);gap:48px;align-items:center}
.skh-eyebrow{font:700 12px/1 Manrope,sans-serif;letter-spacing:.16em;text-transform:uppercase;color:#FFB3D9}
.skh h1{margin:14px 0 16px!important;font:400 clamp(40px,5vw,62px)/1.04 Fraunces,serif!important;color:#fff!important;letter-spacing:-.01em}
.skh h1 em{font-style:italic;color:#FFB3D9}
.skh-lead{max-width:470px;font-size:16.5px;line-height:1.65;color:#EBD7E1}
.skh-cta{display:flex;gap:10px;flex-wrap:wrap;margin-top:26px}
.skh-btn{display:inline-flex;align-items:center;padding:14px 24px;border-radius:999px;background:#D1127E;color:#fff!important;font:700 15px Manrope,sans-serif;text-decoration:none!important;transition:transform .2s,background .2s}
.skh-btn:hover{background:#B00F6A;transform:translateY(-2px)}
.skh-btn--ghost{background:transparent;border:1px solid rgba(255,214,236,.5)}
.skh-btn--ghost:hover{background:rgba(255,255,255,.08)}
.skh-trust{list-style:none;margin:28px 0 0;padding:0;display:flex;flex-wrap:wrap;gap:8px 18px}
.skh-trust li{position:relative;padding-left:18px;font-size:13px;color:#EBD7E1}
.skh-trust li::before{content:"";position:absolute;left:0;top:5px;width:9px;height:5px;border-left:2px solid #FFB3D9;border-bottom:2px solid #FFB3D9;transform:rotate(-45deg)}
.skh-show{position:relative;outline:0;min-width:0}.skh-copy,.skh-wrap>*{min-width:0}.skh-thumbs{min-width:0}
.skh-slides{position:relative;aspect-ratio:1.18/1;min-height:420px}
.skh-slide{position:absolute;inset:0;display:grid;grid-template-columns:1.05fr .95fr;align-items:center;gap:0;background:#fff;border-radius:30px;overflow:hidden;box-shadow:0 40px 80px -40px rgba(0,0,0,.6);opacity:0;visibility:hidden;transform:translateY(14px) scale(.985);transition:opacity .7s ease,transform .7s ease,visibility 0s .7s}
.skh-slide.is-on{opacity:1;visibility:visible;transform:none;transition:opacity .7s ease,transform .7s ease}
.skh-stage{display:block;height:100%;background:#F4E7EB}
.skh-stage img{width:100%;height:100%;object-fit:cover;display:block;transform:scale(1.04);transition:transform 6s ease}
.skh-slide.is-on .skh-stage img{transform:scale(1)}
.skh-info{padding:30px 30px 30px 28px;color:#2A1A24}
.skh-brand{font:700 11px/1.2 Manrope,sans-serif;letter-spacing:.16em;text-transform:uppercase;color:#D1127E}
.skh-name{margin:8px 0 10px!important;font:400 28px/1.15 Fraunces,serif!important}
.skh-name a{color:#3B1530!important;text-decoration:none!important}
.skh-line{font-size:14.5px;line-height:1.55;color:#6E5A66}
.skh-rating{margin-top:12px}
.skh-buy{display:flex;align-items:center;flex-wrap:wrap;gap:10px 14px;margin-top:20px}
.skh-price{font:700 20px Manrope,sans-serif;color:#2A1A24;width:100%}
.skh-price del{font-size:15px;color:#9A8791;font-weight:500;margin-right:6px}.skh-price ins{text-decoration:none;color:#D1127E}
.skh-add{padding:12px 20px;border-radius:999px;background:#3B1530;color:#fff!important;font:700 14px Manrope,sans-serif;text-decoration:none!important}
.skh-add:hover{background:#D1127E}
.skh-more{font:700 14px Manrope,sans-serif;color:#D1127E!important;text-decoration:none!important}
.skh-nav{display:flex;align-items:center;gap:12px;margin-top:18px}
.skh-arrow{flex:0 0 42px;width:42px;height:42px;border-radius:50%!important;border:1px solid rgba(255,214,236,.4)!important;background:transparent!important;color:#fff!important;display:grid;place-items:center;cursor:pointer;padding:0!important}
.skh-arrow:hover{background:rgba(255,255,255,.1)!important}
.skh-thumbs{flex:1;display:flex;gap:8px;justify-content:center;overflow-x:auto;scrollbar-width:none}
.skh-thumb{position:relative;flex:0 0 54px;width:54px;height:54px;padding:0!important;border-radius:14px!important;overflow:hidden;border:2px solid transparent!important;background:#F4E7EB!important;cursor:pointer;opacity:.55;transition:opacity .2s,border-color .2s}
.skh-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.skh-thumb.is-on{opacity:1;border-color:#FFB3D9!important}
.skh-thumb i{position:absolute;left:0;bottom:0;height:3px;width:0;background:#D1127E}
.skh-thumb.is-on i{animation:skhbar 5.2s linear forwards;animation-play-state:var(--skh-run,running)}
.skh.is-paused .skh-thumb.is-on i{animation-play-state:paused}
@keyframes skhbar{to{width:100%}}
.sk-stars{position:relative;display:inline-block;font-size:15px;line-height:1;letter-spacing:2px;vertical-align:middle}
.sk-stars__bg{color:#E9D3DB}.sk-stars__fg{position:absolute;left:0;top:0;overflow:hidden;white-space:nowrap;color:#D1127E}
.sk-stars__n{margin-left:6px;font:700 13px Manrope,sans-serif;color:#2A1A24;vertical-align:middle}.sk-stars__n span{color:#9A8791;font-weight:600}
@media(max-width:960px){.skh-wrap{grid-template-columns:minmax(0,1fr);gap:36px;padding:56px 20px 48px}.skh-slides{aspect-ratio:auto;min-height:0;height:520px}}
@media(max-width:600px){
.skh-wrap{padding:44px 16px 40px}
.skh-lead{font-size:15.5px}
.skh-trust{display:none}
.skh-slides{height:auto;aspect-ratio:auto;min-height:0;display:grid}
.skh-slide{position:relative;inset:auto;grid-area:1/1;grid-template-columns:minmax(0,1fr);min-width:0;border-radius:24px}
.skh-stage{height:auto;aspect-ratio:1/0.8}
.skh-info{padding:20px}
.skh-name{font-size:24px!important}
.skh-thumb{flex-basis:46px;width:46px;height:46px}
}
</style>
		<?php
	}
);
