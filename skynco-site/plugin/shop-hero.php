<?php
/**
 * [skynco_shop_hero] Shop page hero: one wide photo under an even dark overlay,
 * centred copy (reviews line, headline, short line, one button) and a row of the
 * professional brands the studio stocks along the bottom.
 * The photo's attachment ID is kept in the skynco_shop_hero_bg option (file => ID).
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
		$brands = [
			[ 'dermalogica', 'skh-b--derm' ],
			[ 'IMAGE', 'skh-b--image' ],
			[ 'iS CLINICAL', 'skh-b--is' ],
			[ 'eltaMD', 'skh-b--elta' ],
			[ 'ALASTIN', 'skh-b--alastin' ],
		];
		$list = '';
		foreach ( $brands as [ $name, $cls ] ) {
			$list .= '<li class="' . esc_attr( $cls ) . '">' . esc_html( $name ) . '</li>';
		}
		ob_start();
		?>
<section class="skh" aria-label="Shop home care">
	<img class="skh-bg" src="<?php echo esc_url( skynco_shop_hero_bg( 'shop-hero-wide.jpg' ) ); ?>" alt="" fetchpriority="high">
	<div class="skh-inner">
		<p class="skh-proof"><span class="skh-proof__stars" aria-hidden="true">★★★★★</span><b>5.0</b> from 90 client reviews</p>
		<h1 class="skh-title">Studio results, <em>at home.</em></h1>
		<p class="skh-lead">The professional skincare Hana uses in the treatment room, chosen for your skin and delivered to your door.</p>
		<a class="skh-btn" href="#kits">Shop the kits</a>
		<div class="skh-brands"><p>Professional brands we stock</p><ul><?php echo $list; // phpcs:ignore ?></ul></div>
	</div>
</section>
		<?php
		return ob_get_clean();
	}
);

add_action(
	'wp_head',
	function () {
		?>
<style id="skynco-shop-hero">
.skh{position:relative;overflow:hidden;display:flex;align-items:center;justify-content:center;min-height:min(86vh,760px);background:#1E0C18;color:#fff;font-family:Manrope,sans-serif;text-align:center}
.skh *{box-sizing:border-box}.skh p{margin:0}
.skh .skh-bg{position:absolute;inset:0;width:100%!important;height:100%!important;max-width:none!important;object-fit:cover;object-position:50% 28%}
.skh::before{content:"";position:absolute;inset:0;z-index:1;background:linear-gradient(180deg,rgba(26,10,22,.55) 0%,rgba(26,10,22,.5) 45%,rgba(26,10,22,.72) 100%)}
.skh-inner{position:relative;z-index:2;width:100%;max-width:820px;padding:110px 24px 130px;display:flex;flex-direction:column;align-items:center}
.skh-proof{display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:999px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.22);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);font:500 13.5px Manrope,sans-serif;color:#F7EAF0}
.skh-proof__stars{letter-spacing:2px;color:#FF8FCB;font-size:13px}.skh-proof b{color:#fff}
.skh .skh-title{margin:22px 0 16px!important;font:500 clamp(40px,5.6vw,72px)/1.05 Fraunces,serif!important;color:#fff!important;letter-spacing:-.015em}
.skh-title em{font-style:italic;color:#FF8FCB}
.skh-lead{max-width:560px;font-size:18px;line-height:1.65;color:#F1DFE8}
.skh-btn{margin-top:30px;display:inline-flex;align-items:center;justify-content:center;padding:16px 34px;border-radius:999px;background:#D1127E;color:#fff!important;font:700 16px Manrope,sans-serif;text-decoration:none!important;box-shadow:0 14px 30px -12px rgba(209,18,126,.8);transition:transform .2s,background .2s}
.skh-btn:hover{background:#B00F6A;transform:translateY(-2px)}
.skh-brands{margin-top:56px;width:100%}
.skh-brands p{font:600 12.5px Manrope,sans-serif;letter-spacing:.06em;color:rgba(255,255,255,.75)}
.skh-brands ul{list-style:none;margin:16px 0 0;padding:0;display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:14px 38px;color:#fff;opacity:.92}
.skh-brands li{line-height:1}
.skh-b--derm{font:700 22px/1 Manrope,sans-serif;letter-spacing:-.02em}
.skh-b--image{font:600 19px/1 Fraunces,serif;letter-spacing:.32em}
.skh-b--is{font:800 16px/1 Manrope,sans-serif;letter-spacing:.12em}
.skh-b--elta{font:600 22px/1 Fraunces,serif;font-style:italic;letter-spacing:-.01em}
.skh-b--alastin{font:500 17px/1 Fraunces,serif;letter-spacing:.28em}
.sk-stars{position:relative;display:inline-block;font-size:15px;line-height:1;letter-spacing:2px;vertical-align:middle}
.sk-stars__bg{color:#E9D3DB}.sk-stars__fg{position:absolute;left:0;top:0;overflow:hidden;white-space:nowrap;color:#D1127E}
.sk-stars__n{margin-left:6px;font:700 13px Manrope,sans-serif;color:#2A1A24;vertical-align:middle}.sk-stars__n span{color:#9A8791;font-weight:600}
@media(max-width:767px){
.skh{min-height:640px}
.skh .skh-bg{object-position:50% 22%}
.skh::before{background:linear-gradient(180deg,rgba(26,10,22,.45) 0%,rgba(26,10,22,.6) 50%,rgba(26,10,22,.82) 100%)}
.skh-inner{padding:72px 18px 96px}
.skh .skh-title{font-size:40px!important;margin:18px 0 12px!important}
.skh-lead{font-size:15.5px}
.skh-btn{margin-top:24px;width:100%;max-width:320px;padding:15px 20px}
.skh-brands{margin-top:40px}
.skh-brands ul{gap:12px 22px}
.skh-b--derm,.skh-b--elta{font-size:18px}.skh-b--image{font-size:15px}.skh-b--is{font-size:13px}.skh-b--alastin{font-size:14px}
}
</style>
		<?php
	}
);
