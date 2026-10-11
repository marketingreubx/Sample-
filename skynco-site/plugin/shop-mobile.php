<?php
/**
 * Phone layout for the shop and every product grid: a two-column product grid,
 * compact kit cards, a swipeable offers strip and reviews, a taller hero photo,
 * and fewer floating buttons. Loaded after the other styles so it wins.
 *
 * @package SkyncoStudio
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_head',
	function () {
		?>
<style id="skynco-shop-mobile">
@media (max-width:767px){
  /* Floating clutter */
  #ast-scroll-top{display:none!important}
  .skb{right:12px!important;bottom:12px!important}
  .skb-launch{padding:6px!important}.skb-launch__t{display:none!important}

  /* Shop hero: photo fills the background under a plum overlay (like the homepage) */
  .skh-slides{min-height:0!important}
  .skh-slide{display:flex!important;flex-direction:column;justify-content:flex-end;min-height:min(78vh,600px);background:#26101F}
  .skh-bg{position:absolute!important;inset:0!important;width:100%!important;height:100%!important;object-fit:cover;object-position:84% 30%!important}
  .skh-slide::before{inset:0!important;height:auto!important;background:linear-gradient(180deg,rgba(38,16,31,.35) 0%,rgba(38,16,31,.55) 40%,rgba(38,16,31,.88) 72%,rgba(38,16,31,.96) 100%)!important}
  .skh-wrap{padding:120px 16px 32px!important;margin:0!important}
  .skh .skh-title{font-size:34px!important;line-height:1.08!important;margin:0 0 12px!important}
  .skh-lead{font-size:15.5px!important;line-height:1.6!important;max-width:none!important;color:#F3E3EA!important}
  .skh-cta{margin-top:20px!important;gap:10px!important;flex-direction:column}
  .skh-cta .skh-btn{width:100%;justify-content:center;padding:15px 12px!important;font-size:15px!important}
  .skh-card{display:none!important}
  /* One still image on phones: no rotating slides, no jumping */
  .skh-slide:not(:first-child){display:none!important}
  .skh-slide:first-child{opacity:1!important;visibility:visible!important;transition:none!important}
  .skh-slide:first-child .skh-copy>*{opacity:1!important;transform:none!important}

  /* Offers: one swipeable strip instead of a tall stacked box */
  .elementor-widget-html .sk-offers{display:flex!important;overflow-x:auto;scroll-snap-type:x mandatory;gap:10px;margin:0 -16px;padding:2px 16px 6px;background:none!important;border:0!important;box-shadow:none!important;border-radius:0!important;scrollbar-width:none}
  .sk-offers::-webkit-scrollbar{display:none}
  .sk-offers>div{flex:0 0 76%;scroll-snap-align:start;padding:14px 16px!important;border:1px solid #EADDE0!important;border-radius:18px;background:#fff;box-shadow:0 10px 24px -18px rgba(59,21,48,.35)}
  .sk-offers b{font-size:15px!important}.sk-offers span{font-size:13px!important}

  /* Product grids: two neat columns, no half-cut swipe rows */
  body .sk-grid.sk-grid:not(.sk-grid--feature){display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:10px!important;overflow:visible!important;margin:0!important;padding:0!important}
  body .sk-grid.sk-grid:not(.sk-grid--feature)>.sk-card{flex:none!important;width:auto!important;border-radius:18px}
  body .sk-grid.sk-grid:not(.sk-grid--feature) .sk-card__img{aspect-ratio:1/1}
  body .sk-grid.sk-grid:not(.sk-grid--feature) .sk-badge{top:8px;left:8px;padding:5px 8px;font-size:10px}
  body .sk-grid.sk-grid:not(.sk-grid--feature) .sk-card__body{padding:10px 12px 12px!important;gap:5px!important}
  body .sk-grid.sk-grid:not(.sk-grid--feature) .sk-card__title{font-size:15px!important;line-height:1.25!important;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
  body .sk-grid.sk-grid:not(.sk-grid--feature) .sk-card__short{display:none!important}
  body .sk-grid.sk-grid:not(.sk-grid--feature) .sk-card__rating .sk-stars{font-size:11px;letter-spacing:1px}
  body .sk-grid.sk-grid:not(.sk-grid--feature) .sk-card__rating .sk-stars__n{font-size:11px}
  body .sk-grid.sk-grid:not(.sk-grid--feature) .sk-card__foot{flex-direction:column;align-items:stretch;gap:8px;padding-top:6px}
  body .sk-grid.sk-grid:not(.sk-grid--feature) .sk-price{font-size:15px}
  body .sk-grid.sk-grid:not(.sk-grid--feature) .sk-add{width:100%!important;justify-content:center;padding:10px 8px!important;font-size:13px!important}

  /* Kits: swipeable carousel, equal cards, next one peeking */
  body .sk-grid.sk-grid.sk-grid--feature{display:flex!important;flex-direction:row!important;overflow-x:auto!important;scroll-snap-type:x mandatory;gap:12px!important;margin:0 -16px!important;padding:2px 16px 8px!important;scroll-padding:0 16px;scrollbar-width:none;-webkit-overflow-scrolling:touch}
  body .sk-grid.sk-grid--feature::-webkit-scrollbar{display:none}
  body .sk-grid.sk-grid--feature>.sk-card{flex:0 0 82%!important;width:82%!important;display:flex!important;flex-direction:column;scroll-snap-align:start;border-radius:22px}
  .sk-grid--feature .sk-card__img{aspect-ratio:4/3!important;height:auto!important;min-height:0!important}
  .sk-grid--feature .sk-badge{top:10px;left:10px;padding:6px 10px;font-size:11px}
  .sk-grid--feature .sk-card__body{padding:14px 16px 16px!important;gap:6px!important}
  .sk-grid--feature .sk-card__title{font-size:21px!important}
  .sk-grid--feature .sk-card__short{font-size:13.5px!important;line-height:1.45!important;display:-webkit-box!important;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
  .sk-grid--feature .sk-card__kit{display:none!important}
  .sk-grid--feature .sk-card__foot{padding-top:6px;gap:8px;flex-wrap:nowrap}
  .sk-grid--feature .sk-add{padding:11px 16px!important;font-size:14px!important;white-space:nowrap}
  .sk-cdots{display:flex;justify-content:center;gap:6px;margin-top:12px}
  .sk-cdots i{width:7px;height:7px;border-radius:99px;background:#E6C9D2;transition:width .25s,background .25s}
  .sk-cdots i.is-on{width:22px;background:#D1127E}

  /* Reviews band: swipe through */
  body .skrb-grid.skrb-grid{display:flex!important;overflow-x:auto;scroll-snap-type:x mandatory;gap:12px!important;margin:0 -16px;padding:0 16px 6px;scrollbar-width:none}
  .skrb-grid::-webkit-scrollbar{display:none}
  .skrb-card{flex:0 0 84%;scroll-snap-align:center;padding:20px!important}
  .e-con.e-parent:has(.sk-offers){--margin-top:16px;margin-top:16px!important}
}
</style>
		<?php
	},
	100
);

/* Dots under the kit carousel on phones. */
add_action(
	'wp_footer',
	function () {
		?>
<script>
(function(){
	if(!window.matchMedia('(max-width:767px)').matches)return;
	document.querySelectorAll('.sk-grid--feature').forEach(function(g){
		var cards=g.querySelectorAll('.sk-card');if(cards.length<2)return;
		var d=document.createElement('div');d.className='sk-cdots';d.setAttribute('aria-hidden','true');
		cards.forEach(function(){d.appendChild(document.createElement('i'));});
		g.parentNode.insertBefore(d,g.nextSibling);
		function upd(){var w=cards[0].offsetWidth+12,k=Math.round(g.scrollLeft/w);d.querySelectorAll('i').forEach(function(x,j){x.classList.toggle('is-on',j===Math.min(k,cards.length-1));});}
		g.addEventListener('scroll',function(){window.requestAnimationFrame(upd);},{passive:true});upd();
	});
})();
</script>
		<?php
	},
	99
);

