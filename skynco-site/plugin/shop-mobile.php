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

  /* Shop hero: a real photo on top, copy below on plum */
  .skh-slides{min-height:0!important}
  .skh-slide{display:block!important}
  .skh-bg{position:relative!important;inset:auto!important;display:block;width:100%!important;height:min(62vh,440px)!important}
  .skh-slide::before{inset:auto 0 auto 0!important;top:calc(min(62vh,440px) - 160px)!important;height:160px!important;background:linear-gradient(180deg,rgba(38,16,31,0),#26101F 92%)!important}
  .skh-wrap{padding:0 16px 28px!important;margin-top:-56px!important;background:transparent}
  .skh .skh-title{font-size:34px!important;line-height:1.08!important;margin:0 0 10px!important}
  .skh-lead{font-size:15px!important;line-height:1.6!important}
  .skh-cta{margin-top:18px!important;gap:8px!important}
  .skh-cta .skh-btn{flex:1;justify-content:center;padding:13px 12px!important;font-size:14.5px!important}
  .skh-card{margin-top:16px!important;padding:10px!important;border-radius:18px!important;gap:12px!important}
  .skh-card__img{flex-basis:64px!important;height:64px!important;border-radius:12px!important}
  .skh-card__name{font-size:15px!important}

  /* Offers: one swipeable strip instead of a tall stacked box */
  .elementor-widget-html .sk-offers{display:flex!important;overflow-x:auto;scroll-snap-type:x mandatory;gap:10px;margin:0 -16px;padding:2px 16px 6px;background:none!important;border:0!important;box-shadow:none!important;border-radius:0!important;scrollbar-width:none}
  .sk-offers::-webkit-scrollbar{display:none}
  .sk-offers>div{flex:0 0 76%;scroll-snap-align:start;padding:14px 16px!important;border:1px solid #EADDE0!important;border-radius:18px;background:#fff;box-shadow:0 10px 24px -18px rgba(59,21,48,.35)}
  .sk-offers b{font-size:15px!important}.sk-offers span{font-size:13px!important}

  /* Product grids: two neat columns, no half-cut swipe rows */
  .sk-grid:not(.sk-grid--feature){display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:10px!important;overflow:visible!important;margin:0!important;padding:0!important}
  .sk-grid:not(.sk-grid--feature)>.sk-card{flex:none!important;width:auto!important;border-radius:18px}
  .sk-grid:not(.sk-grid--feature) .sk-card__img{aspect-ratio:1/1}
  .sk-grid:not(.sk-grid--feature) .sk-badge{top:8px;left:8px;padding:5px 8px;font-size:10px}
  .sk-grid:not(.sk-grid--feature) .sk-card__body{padding:10px 12px 12px!important;gap:5px!important}
  .sk-grid:not(.sk-grid--feature) .sk-card__title{font-size:15px!important;line-height:1.25!important;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
  .sk-grid:not(.sk-grid--feature) .sk-card__short{display:none!important}
  .sk-grid:not(.sk-grid--feature) .sk-card__rating .sk-stars{font-size:11px;letter-spacing:1px}
  .sk-grid:not(.sk-grid--feature) .sk-card__rating .sk-stars__n{font-size:11px}
  .sk-grid:not(.sk-grid--feature) .sk-card__foot{flex-direction:column;align-items:stretch;gap:8px;padding-top:6px}
  .sk-grid:not(.sk-grid--feature) .sk-price{font-size:15px}
  .sk-grid:not(.sk-grid--feature) .sk-add{width:100%!important;justify-content:center;padding:10px 8px!important;font-size:13px!important}

  /* Kits: compact horizontal cards stacked */
  .sk-grid.sk-grid--feature{display:flex!important;flex-direction:column;gap:12px!important;overflow:visible!important;margin:0!important;padding:0!important}
  .sk-grid--feature>.sk-card{flex:none!important;width:auto!important;display:grid!important;grid-template-columns:118px minmax(0,1fr);border-radius:20px}
  .sk-grid--feature .sk-card__img{aspect-ratio:auto;height:100%;min-height:150px}
  .sk-grid--feature .sk-badge{top:8px;left:8px;padding:5px 8px;font-size:10px}
  .sk-grid--feature .sk-card__body{padding:12px 14px!important;gap:5px!important}
  .sk-grid--feature .sk-card__title{font-size:18px!important}
  .sk-grid--feature .sk-card__short{font-size:13px!important;line-height:1.45!important}
  .sk-grid--feature .sk-card__kit{display:none!important}
  .sk-grid--feature .sk-card__foot{padding-top:4px;gap:8px}
  .sk-grid--feature .sk-add{padding:10px 14px!important;font-size:13px!important}

  /* Reviews band: swipe through */
  .skrb-grid{display:flex!important;overflow-x:auto;scroll-snap-type:x mandatory;gap:12px!important;margin:0 -16px;padding:0 16px 6px;scrollbar-width:none}
  .skrb-grid::-webkit-scrollbar{display:none}
  .skrb-card{flex:0 0 84%;scroll-snap-align:center;padding:20px!important}
  .e-con.e-parent:has(.sk-offers){--margin-top:16px;margin-top:16px!important}
}
</style>
		<?php
	},
	100
);
