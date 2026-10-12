<?php
/**
 * Plugin Name: IR Cards
 * Description: Compact course and product cards (all link to the normal course or shop page) ([ir_cards set="courses|digital|all"]) and the moving reviews slider (.ir-reviews).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Extra card details per product: where the button goes, label and the short line under the title. */
function ir_cards_meta() {
	return [
		2826 => [ 'tag' => 'Playbook', 'sub' => '34 lessons · Members dashboard' ],
		1643 => [ 'tag' => 'Prompt pack', 'sub' => '3,000+ prompts · Instant download' ],
	];
}

function ir_cards_money( $n ) { return '₦' . number_format( (float) $n ); }

function ir_cards_items( $set ) {
	$items = [];
	$course_products = [];
	if ( in_array( $set, [ 'courses', 'all' ], true ) ) {
		foreach ( get_posts( [ 'post_type' => 'courses', 'post_status' => 'publish', 'numberposts' => 12, 'orderby' => 'date', 'order' => 'DESC' ] ) as $c ) {
			$pid = (int) get_post_meta( $c->ID, '_tutor_course_product_id', true );
			$p   = $pid ? wc_get_product( $pid ) : null;
			$course_products[] = $pid;
			$n = function_exists( 'tutor_utils' ) ? (int) tutor_utils()->get_lesson_count_by_course( $c->ID ) : 0;
			$items[] = [
				'title' => $c->post_title,
				'url'   => get_permalink( $c->ID ),
				'img'   => get_post_thumbnail_id( $c->ID ) ?: ( $p ? $p->get_image_id() : 0 ),
				'tag'   => 'Course',
				'sub'   => ( $n ? $n . ' lessons · ' : '' ) . 'Lifetime access',
				'reg'   => $p ? $p->get_regular_price() : '',
				'sale'  => $p ? $p->get_sale_price() : '',
			];
		}
	} else {
		foreach ( get_posts( [ 'post_type' => 'courses', 'post_status' => 'publish', 'numberposts' => 50, 'fields' => 'ids' ] ) as $cid ) { $course_products[] = (int) get_post_meta( $cid, '_tutor_course_product_id', true ); }
	}
	if ( in_array( $set, [ 'digital', 'all' ], true ) ) {
		$meta = ir_cards_meta();
		$q = wc_get_products( [ 'status' => 'publish', 'limit' => 12, 'category' => [ 'digital-products' ], 'orderby' => 'date', 'order' => 'DESC' ] );
		foreach ( $q as $p ) {
			if ( in_array( $p->get_id(), $course_products, true ) ) { continue; }
			$m = $meta[ $p->get_id() ] ?? [];
			$items[] = [
				'title' => trim( preg_replace( '/\s*\(.*$/s', '', $p->get_name() ) ),
				'url'   => ! empty( $m['url'] ) ? home_url( $m['url'] ) : get_permalink( $p->get_id() ),
				'img'   => $p->get_image_id(),
				'tag'   => $m['tag'] ?? 'Digital product',
				'sub'   => $m['sub'] ?? 'Instant access',
				'reg'   => $p->get_regular_price(),
				'sale'  => $p->get_sale_price(),
			];
		}
	}
	return $items;
}

add_shortcode( 'ir_cards', function ( $a ) {
	if ( ! function_exists( 'wc_get_products' ) ) { return ''; }
	$a = shortcode_atts( [ 'set' => 'all' ], $a );
	$items = ir_cards_items( $a['set'] );
	if ( ! $items ) { return ''; }
	$h = '<div class="irc-grid">';
	foreach ( $items as $it ) {
		$sale = $it['sale'] !== '' && (float) $it['sale'] < (float) $it['reg'];
		$off  = $sale && (float) $it['reg'] > 0 ? round( ( 1 - $it['sale'] / $it['reg'] ) * 100 ) : 0;
		$img  = $it['img'] ? wp_get_attachment_image( $it['img'], 'large', false, [ 'loading' => 'lazy', 'class' => 'irc-img', 'sizes' => '(max-width:767px) 50vw, 300px' ] ) : '';
		$h .= '<a class="irc-card" href="' . esc_url( $it['url'] ) . '">'
			. '<div class="irc-cover">' . $img . '<span class="irc-tag">' . esc_html( $it['tag'] ) . '</span>' . ( $off ? '<span class="irc-off">-' . (int) $off . '%</span>' : '' ) . '</div>'
			. '<div class="irc-body"><h3 class="irc-title">' . esc_html( $it['title'] ) . '</h3><p class="irc-sub">' . esc_html( $it['sub'] ) . '</p>'
			. '<div class="irc-foot"><span class="irc-price">' . ( $sale ? '<s>' . ir_cards_money( $it['reg'] ) . '</s>' : '' ) . '<b>' . ir_cards_money( $sale ? $it['sale'] : $it['reg'] ) . '</b></span>'
			. '<span class="irc-btn">Get Access<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span></div></div></a>';
	}
	return $h . '</div>';
} );

add_action( 'wp_head', function () {
	if ( is_admin() ) { return; }
	?>
<style id="ir-cards">
.irc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:20px;width:100%}
.irc-card{display:flex;flex-direction:column;background:var(--e-global-color-irsurface,#fff);border:1px solid var(--e-global-color-irborder,#E5EBF6);border-radius:20px;overflow:hidden;text-decoration:none!important;color:var(--e-global-color-irtext,#0A1633)!important;box-shadow:0 1px 2px rgba(10,22,51,.04),0 12px 32px rgba(10,22,51,.07);transition:transform .25s ease,box-shadow .25s ease}
@media(hover:hover){.irc-card:hover{transform:translateY(-4px);box-shadow:0 2px 4px rgba(10,22,51,.05),0 24px 48px rgba(10,22,51,.12)}.irc-card:hover .irc-img{transform:scale(1.04)}.irc-card:hover .irc-btn svg{transform:translateX(3px)}}
.irc-cover{position:relative;aspect-ratio:4/5;overflow:hidden;background:#0A1A4A}
.irc-cover .irc-img{width:100%!important;height:100%!important;object-fit:cover;display:block;transition:transform .5s ease;border-radius:0!important}
.irc-tag{position:absolute;left:10px;top:10px;padding:5px 10px;border-radius:999px;background:rgba(255,255,255,.88);-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);color:#0A1633;font-size:12px;font-weight:700;letter-spacing:.01em}
.irc-off{position:absolute;right:10px;top:10px;padding:5px 9px;border-radius:999px;background:#FF7A1A;color:#fff;font-size:12px;font-weight:800}
.irc-body{display:flex;flex-direction:column;gap:6px;padding:16px 16px 18px;flex:1}
.irc-title{margin:0!important;font-size:17px!important;line-height:1.3!important;font-weight:700!important;letter-spacing:-.2px;color:inherit!important;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.irc-sub{margin:0!important;font-size:13.5px;line-height:1.4;color:var(--e-global-color-irmuted,#4F5D78)}
.irc-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:auto;padding-top:10px}
.irc-price{display:flex;flex-direction:column;line-height:1.15}.irc-price s{font-size:12.5px;color:var(--e-global-color-irmuted,#4F5D78);opacity:.8}.irc-price b{font-size:18px;font-weight:800;letter-spacing:-.3px}
.irc-btn{display:inline-flex;align-items:center;gap:6px;background:#1660E8;color:#fff;font-weight:700;font-size:14px;padding:10px 14px;border-radius:12px;white-space:nowrap;box-shadow:0 8px 18px rgba(22,96,232,.28)}
.irc-btn svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round;transition:transform .2s}
html[data-theme="dark"] .irc-card{box-shadow:0 14px 34px rgba(0,0,0,.35)}
@media(max-width:767px){
 .irc-grid{grid-template-columns:1fr 1fr;gap:12px}
 .irc-card{border-radius:16px}
 .irc-body{padding:11px 11px 12px;gap:4px}
 .irc-title{font-size:14.5px!important}
 .irc-sub{font-size:12px}
 .irc-foot{flex-direction:column;align-items:stretch;gap:8px;padding-top:6px}
 .irc-price{flex-direction:row;align-items:baseline;gap:6px;flex-wrap:wrap}.irc-price b{font-size:16px}.irc-price s{font-size:11.5px}
 .irc-btn{justify-content:center;font-size:13px;padding:9px 10px;border-radius:10px}
 .irc-tag{left:8px;top:8px;font-size:10.5px;padding:4px 8px}.irc-off{right:8px;top:8px;font-size:10.5px;padding:4px 7px}
}
/* Reviews slider */
.ir-reviews.irr-on{display:block!important;overflow-x:auto;overflow-y:hidden;scrollbar-width:none;-webkit-mask-image:linear-gradient(90deg,transparent,#000 6%,#000 94%,transparent);mask-image:linear-gradient(90deg,transparent,#000 6%,#000 94%,transparent);cursor:grab}
.ir-reviews.irr-on::-webkit-scrollbar{display:none}
.ir-reviews .irr-track{display:flex;gap:16px;width:max-content;padding:4px 0}
.ir-reviews .irr-track>.e-con{width:380px!important;max-width:none!important;flex:none!important}
@media(max-width:767px){.ir-reviews .irr-track>.e-con{width:min(270px,74vw)!important}.ir-reviews.irr-on{-webkit-mask-image:linear-gradient(90deg,transparent,#000 3%,#000 97%,transparent);mask-image:linear-gradient(90deg,transparent,#000 3%,#000 97%,transparent)}}
</style>
	<?php
}, 32 );

add_action( 'wp_footer', function () {
	?>
<script id="ir-reviews-js">
(function(){
 document.querySelectorAll('.ir-reviews').forEach(function(box){
  var kids=[].slice.call(box.children);if(kids.length<2)return;
  var track=document.createElement('div');track.className='irr-track';
  kids.forEach(function(k){track.appendChild(k)});
  kids.forEach(function(k){var c=k.cloneNode(true);c.setAttribute('aria-hidden','true');track.appendChild(c)});
  box.appendChild(track);box.classList.add('irr-on');
  if(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches)return;
  var pausedUntil=0,pos=0,last=0;
  function pause(){pausedUntil=Date.now()+3500}
  ['pointerdown','touchstart','wheel'].forEach(function(e){box.addEventListener(e,pause,{passive:true})});
  box.addEventListener('mouseenter',function(){pausedUntil=Infinity});box.addEventListener('mouseleave',function(){pausedUntil=0});
  box.addEventListener('scroll',function(){if(Date.now()<pausedUntil)pos=box.scrollLeft});
  function step(t){var dt=last?Math.min(t-last,50):16;last=t;var half=track.scrollWidth/2;
   if(Date.now()>=pausedUntil&&half>box.clientWidth){pos+=dt*0.035;if(pos>=half)pos-=half;box.scrollLeft=pos}
   else if(box.scrollLeft>=half){box.scrollLeft-=half;pos=box.scrollLeft}
   requestAnimationFrame(step)}
  requestAnimationFrame(step);
 });
})();
</script>
	<?php
}, 40 );
