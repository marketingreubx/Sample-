<?php
/**
 * Page heroes. skynco_hero_html() builds the hero every page uses; [skynco_shop_hero] is the shop's.
 * Shop page hero: one wide photo under an even dark overlay,
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

/**
 * Photo URL for a page hero. $key is a key in skynco_hero_imgs (page => attachment ID),
 * a skynco_media_ids name, or a full URL.
 */
function skynco_hero_img( $key ) {
	if ( 0 === strpos( (string) $key, 'http' ) ) {
		return $key;
	}
	foreach ( [ 'skynco_hero_imgs', 'skynco_media_ids' ] as $opt ) {
		$ids = (array) get_option( $opt, [] );
		if ( ! empty( $ids[ $key ] ) && ( $url = wp_get_attachment_image_url( $ids[ $key ], 'full' ) ) ) {
			return $url;
		}
	}
	return skynco_shop_hero_bg( 'shop-hero-wide.jpg' );
}

/**
 * Shared page hero: one photo under an even overlay, centred chip, headline, short
 * line and one button. Used on every page so they all match the shop.
 *
 * @param array $a img, chip ('reviews' or text), title (may hold <em>), lead,
 *                 btn [label, url], brands (bool), size ('tall' | 'page'), pos (object-position).
 */
function skynco_hero_html( array $a ) {
	$a    = array_merge( [ 'img' => '', 'chip' => 'reviews', 'title' => '', 'lead' => '', 'btn' => null, 'brands' => false, 'size' => 'page', 'pos' => '50% 35%', 'label' => '' ], $a );
	$chip = 'reviews' === $a['chip']
		? '<span class="skh-proof__stars" aria-hidden="true">★★★★★</span><b>5.0</b> from 90 client reviews'
		: esc_html( $a['chip'] );
	ob_start();
	?>
<section class="skh skh--<?php echo esc_attr( $a['size'] ); ?>"<?php echo $a['label'] ? ' aria-label="' . esc_attr( $a['label'] ) . '"' : ''; ?>>
	<img class="skh-bg" src="<?php echo esc_url( skynco_hero_img( $a['img'] ) ); ?>" alt="" fetchpriority="high" style="object-position:<?php echo esc_attr( $a['pos'] ); ?>">
	<div class="skh-inner">
		<?php if ( $a['chip'] ) : ?><p class="skh-proof"><?php echo $chip; // phpcs:ignore ?></p><?php endif; ?>
		<h1 class="skh-title"><?php echo wp_kses( $a['title'], [ 'em' => [], 'br' => [] ] ); ?></h1>
		<?php if ( $a['lead'] ) : ?><p class="skh-lead"><?php echo wp_kses( $a['lead'], [ 'a' => [ 'href' => [] ] ] ); ?></p><?php endif; ?>
		<?php if ( $a['btn'] ) : ?><a class="skh-btn" href="<?php echo esc_url( $a['btn'][1] ); ?>"><?php echo esc_html( $a['btn'][0] ); ?></a><?php endif; ?>
		<?php if ( $a['brands'] ) : ?><div class="skh-brands"><p>Professional brands we use and stock</p><div class="skh-marquee"><ul class="skh-track"><?php echo skynco_hero_brands(); // phpcs:ignore ?></ul></div></div><?php endif; ?>
	</div>
</section>
	<?php
	return ob_get_clean();
}

function skynco_hero_brands() {
	$list = '';
	foreach ( [ [ 'dermalogica', 'skh-b--derm' ], [ 'IMAGE', 'skh-b--image' ], [ 'iS CLINICAL', 'skh-b--is' ], [ 'eltaMD', 'skh-b--elta' ], [ 'ALASTIN', 'skh-b--alastin' ] ] as [ $name, $cls ] ) {
		$list .= '<li class="' . esc_attr( $cls ) . '">' . esc_html( $name ) . '</li>';
	}
	return $list . str_replace( '<li ', '<li aria-hidden="true" ', $list );
}

add_shortcode(
	'skynco_shop_hero',
	function () {
		return skynco_hero_html(
			[
				'img'    => skynco_shop_hero_bg( 'shop-hero-wide.jpg' ),
				'title'  => 'Studio results, <em>at home.</em>',
				'lead'   => 'The professional skincare Hana uses in the treatment room, chosen for your skin and delivered to your door.',
				'btn'    => [ 'Shop the kits', '#kits' ],
				'brands' => true,
				'size'   => 'tall',
				'pos'    => '50% 28%',
				'label'  => 'Shop home care',
			]
		);
	}
);

add_action(
	'wp_head',
	function () {
		?>
<style id="skynco-shop-hero">
.skh{position:relative;overflow:hidden;display:flex;align-items:center;justify-content:center;min-height:min(62vh,560px);background:#1E0C18;color:#fff;font-family:Manrope,sans-serif;text-align:center}
.skh *{box-sizing:border-box}.skh p{margin:0}
.skh--tall{min-height:min(86vh,760px)}
.skh .skh-bg{position:absolute;inset:0;width:100%!important;height:100%!important;max-width:none!important;object-fit:cover}
.skh--page .skh-inner{padding:96px 24px 88px}
.skh::before{content:"";position:absolute;inset:0;z-index:1;background:linear-gradient(180deg,rgba(26,10,22,.55) 0%,rgba(26,10,22,.5) 45%,rgba(26,10,22,.72) 100%)}
.skh-inner{position:relative;z-index:2;width:100%;max-width:820px;padding:110px 24px 90px;display:flex;flex-direction:column;align-items:center}
.skh-proof{display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:999px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.22);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);font:500 13.5px Manrope,sans-serif;color:#F7EAF0}
.skh-proof__stars{letter-spacing:2px;color:#FF8FCB;font-size:13px}.skh-proof b{color:#fff}
.skh .skh-title{margin:22px 0 16px!important;font:500 clamp(40px,5.6vw,72px)/1.05 Fraunces,serif!important;color:#fff!important;letter-spacing:-.015em}
.skh-title em{font-style:italic;color:#FF8FCB}
.skh-lead{max-width:560px;font-size:18px;line-height:1.65;color:#F1DFE8}
.skh-btn{margin-top:30px;display:inline-flex;align-items:center;justify-content:center;padding:16px 34px;border-radius:999px;background:#D1127E;color:#fff!important;font:700 16px Manrope,sans-serif;text-decoration:none!important;box-shadow:0 14px 30px -12px rgba(209,18,126,.8);transition:transform .2s,background .2s}
.skh-btn:hover{background:#B00F6A;transform:translateY(-2px)}
.skh-brands{margin-top:56px;width:100%;max-width:760px}
.skh-brands p{font:600 12.5px Manrope,sans-serif;letter-spacing:.06em;color:rgba(255,255,255,.75)}
.skh-marquee{margin-top:18px;overflow:hidden;width:100%;-webkit-mask-image:linear-gradient(90deg,transparent,#000 12%,#000 88%,transparent);mask-image:linear-gradient(90deg,transparent,#000 12%,#000 88%,transparent)}
.skh-track{list-style:none;margin:0;padding:0;display:flex;align-items:center;width:max-content;color:#fff;opacity:.92;animation:skhmarq 22s linear infinite}
.skh-track li{line-height:1;padding:0 28px;white-space:nowrap}
.skh-marquee:hover .skh-track{animation-play-state:paused}
@keyframes skhmarq{to{transform:translateX(-50%)}}
@media(prefers-reduced-motion:reduce){.skh-track{animation:none;flex-wrap:wrap;justify-content:center;width:auto}.skh-track li[aria-hidden]{display:none}}
.skh-b--derm{font:700 22px/1 Manrope,sans-serif;letter-spacing:-.02em}
.skh-b--image{font:600 19px/1 Fraunces,serif;letter-spacing:.32em}
.skh-b--is{font:800 16px/1 Manrope,sans-serif;letter-spacing:.12em}
.skh-b--elta{font:600 22px/1 Fraunces,serif;font-style:italic;letter-spacing:-.01em}
.skh-b--alastin{font:500 17px/1 Fraunces,serif;letter-spacing:.28em}
.sk-stars{position:relative;display:inline-block;font-size:15px;line-height:1;letter-spacing:2px;vertical-align:middle}
.sk-stars__bg{color:#E9D3DB}.sk-stars__fg{position:absolute;left:0;top:0;overflow:hidden;white-space:nowrap;color:#D1127E}
.sk-stars__n{margin-left:6px;font:700 13px Manrope,sans-serif;color:#2A1A24;vertical-align:middle}.sk-stars__n span{color:#9A8791;font-weight:600}
@media(max-width:767px){
.skh{min-height:500px}.skh--tall{min-height:640px}
.skh--page .skh-inner{padding:64px 18px 56px}
.skh::before{background:linear-gradient(180deg,rgba(26,10,22,.45) 0%,rgba(26,10,22,.6) 50%,rgba(26,10,22,.82) 100%)}
.skh-inner{padding:72px 18px 56px}
.skh .skh-title{font-size:40px!important;margin:18px 0 12px!important}
.skh-lead{font-size:15.5px}
.skh-btn{margin-top:24px;width:100%;max-width:320px;padding:15px 20px}
.skh-brands{margin-top:40px}
.skh-track li{padding:0 18px}.skh-track{animation-duration:16s}
.skh-b--derm,.skh-b--elta{font-size:18px}.skh-b--image{font-size:15px}.skh-b--is{font-size:13px}.skh-b--alastin{font-size:14px}
}
</style>
		<?php
	}
);
