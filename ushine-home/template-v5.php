<?php
/**
 * Template: Ushine Home (Redesign) - v5.
 * Conventional skincare storefront structure: announcement bar, sticky header, split hero,
 * benefits, all categories, results compare, bestsellers, shop by concern, before/after wall, footer.
 */

if (!defined('ABSPATH')) {
    exit;
}

$img = static function ($id, string $size = 'large', array $attr = []): string {
    return $id ? wp_get_attachment_image((int) $id, $size, false, $attr + ['loading' => 'lazy', 'decoding' => 'async']) : '';
};
$up          = wp_upload_dir()['baseurl'] . '/ushine-home/';
$shop_url    = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
$cart_url    = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');
$account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');
$cat_link    = static function (string $slug) use ($shop_url): string {
    $l = get_term_link($slug, 'product_cat');
    return is_wp_error($l) ? $shop_url : $l;
};
$cart_count = (function_exists('WC') && WC()->cart) ? WC()->cart->get_cart_contents_count() : 0;
$wa         = ushine_wa_link("Hi Ushine, I'd love help choosing products for my skin. My main concern is: ");
$arrow      = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$arrow_l    = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>';

if (!function_exists('ushine_card_v5')) {
    /** Product card: image, category, name, price, full-width add-to-cart. */
    function ushine_card_v5(WC_Product $p): string
    {
        $id    = $p->get_id();
        $link  = get_permalink($id);
        $name  = $p->get_name();
        $terms = get_the_terms($id, 'product_cat');
        $cat   = '';
        if ($terms && !is_wp_error($terms)) {
            $names = array_values(array_filter(wp_list_pluck($terms, 'name'), static fn($n) => $n !== 'Uncategorized'));
            $cat   = $names[0] ?? '';
        }
        // Standardised studio shot (same backdrop and scale for every product) when one exists.
        $std = wp_upload_dir();
        if (file_exists($std['basedir'] . '/ushine-home/products/' . $id . '.jpg')) {
            $image = '<img class="pc__img pc__img--std" src="' . esc_url($std['baseurl'] . '/ushine-home/products/' . $id . '.jpg') . '" alt="' . esc_attr($name) . '" width="900" height="900" loading="lazy" decoding="async">';
        } else {
            $image = $p->get_image_id()
                ? wp_get_attachment_image($p->get_image_id(), 'woocommerce_thumbnail', false, ['class' => 'pc__img', 'loading' => 'lazy', 'alt' => esc_attr($name)])
                : '';
        }
        $badge = '';
        if (!$p->is_in_stock()) {
            $badge = '<span class="pc__badge">Sold out</span>';
        } elseif ($p->is_on_sale() && !$p->is_type('variable') && (float) $p->get_regular_price() > 0) {
            $badge = '<span class="pc__badge pc__badge--sale">Save ' . (int) round((1 - (float) $p->get_price() / (float) $p->get_regular_price()) * 100) . '%</span>';
        }
        if (!$p->is_in_stock()) {
            $btn = '<span class="pc__btn is-disabled">Sold out</span>';
        } elseif ($p->is_type('simple') && $p->is_purchasable()) {
            $btn = sprintf(
                '<a href="%s" data-quantity="1" data-product_id="%d" data-product_sku="%s" class="pc__btn add_to_cart_button ajax_add_to_cart" aria-label="%s" rel="nofollow"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8h14l-1.2 11.1a2 2 0 0 1-2 1.9H8.2a2 2 0 0 1-2-1.9L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg><span>Add to cart</span></a>',
                esc_url($p->add_to_cart_url()),
                $id,
                esc_attr($p->get_sku()),
                esc_attr('Add ' . $name . ' to cart')
            );
        } else {
            $btn = '<a href="' . esc_url($link) . '" class="pc__btn" aria-label="' . esc_attr('Choose size for ' . $name) . '"><span>Choose size</span></a>';
        }
        return '<article class="pc ush-card">'
            . '<a class="pc__media" href="' . esc_url($link) . '" tabindex="-1" aria-hidden="true">' . $image . $badge . '</a>'
            . '<div class="pc__body">'
            . ($cat ? '<span class="pc__cat">' . esc_html($cat) . '</span>' : '')
            . '<h3 class="pc__name ush-card__name"><a href="' . esc_url($link) . '">' . esc_html($name) . '</a></h3>'
            . '<p class="pc__price">' . ushine_price_html($p) . '</p>'
            . $btn
            . '</div></article>';
    }
}

// Top sellers by actual sales (each has a standardised studio photo).
$products = function_exists('wc_get_products') ? wc_get_products([
    'status' => 'publish', 'include' => [788, 790, 763, 783], 'orderby' => 'include', 'limit' => 4,
]) : [];

// Every category, each with a photo that shows what is inside it.
$category_photos = [
    'skincare-sets' => 1080, 'face-products' => 1224, 'serums' => 1072, 'face-creams' => 1237,
    'toners' => 1163, 'treatments' => 1183, 'sunscreens' => 1191, 'body-lotions' => 827,
    'body-scrubs' => 1166, 'oils' => 1214, 'soaps' => 1209, 'face-sets' => 1193, 'full-sets' => 1248,
];
$categories = [];
foreach ($category_photos as $slug => $photo) {
    $term = get_term_by('slug', $slug, 'product_cat');
    if (!$term || !$term->count) {
        continue;
    }
    $categories[] = ['name' => ucfirst(strtolower($term->name)), 'count' => (int) $term->count, 'url' => $cat_link($slug), 'img' => $photo];
}

$compare = [
    [854, 'Acne and dark marks'],
    [853, 'Breakouts and uneven texture'],
    [852, 'Hyperpigmentation'],
    [851, 'Dark patches'],
    [1041, 'Dull, uneven tone'],
];

$concerns = [
    ['Dark spots and hyperpigmentation', 'Fade marks and even out your tone.', get_permalink(1060) ?: $shop_url, 733],
    ['Acne and breakouts', 'Clear congestion and the marks it leaves.', get_permalink(1073) ?: $shop_url, 900],
    ['Dark knuckles, elbows and knees', 'Target stubborn dark areas.', get_permalink(782) ?: $shop_url, 1183],
    ['Dull, dry body skin', 'Soft, even, glowing skin from neck to toe.', $cat_link('oils'), 719],
];

$nav = [['Shop all', $shop_url], ['Face', $cat_link('face-products')], ['Body', $cat_link('body-lotions')], ['Sets', $cat_link('skincare-sets')], ['Results', '#results'], ['Contact', home_url('/contact-us/')]];
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#FFFFFF">
<?php wp_head(); ?>
</head>
<body <?php body_class('u4'); ?>>
<?php wp_body_open(); ?>
<a class="u4-skip" href="#u4-main">Skip to content</a>

<div class="u4-announce">
  <a href="<?php echo esc_url($wa); ?>" target="_blank" rel="noopener"><span class="u4-lg">Not sure what to buy? Get a free skin consultation on WhatsApp</span><span class="u4-sm">Free skin consultation on WhatsApp</span> <?php echo $arrow; ?></a>
</div>

<header class="u4-header" data-header>
  <div class="u4-header__in">
    <button class="u4-burger" type="button" aria-expanded="false" aria-controls="u4-menu" data-burger><span></span><span></span><span class="u4-sr">Menu</span></button>
    <a class="u4-logo" href="<?php echo esc_url(home_url('/')); ?>"><img src="<?php echo esc_url($up . 'logo-dark.png'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" width="556" height="182"></a>
    <nav class="u4-nav" aria-label="Main"><?php foreach ($nav as $n) { echo '<a href="' . esc_url($n[1]) . '">' . esc_html($n[0]) . '</a>'; } ?></nav>
    <div class="u4-tools">
      <a class="u4-icon u4-hide-sm" href="<?php echo esc_url($account_url); ?>" aria-label="My account"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg></a>
      <a class="u4-icon" href="<?php echo esc_url($cart_url); ?>" aria-label="Cart"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8h14l-1.2 11.1a2 2 0 0 1-2 1.9H8.2a2 2 0 0 1-2-1.9L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg><span class="ush-cart-count" data-count="<?php echo (int) $cart_count; ?>"><?php echo (int) $cart_count; ?></span></a>
    </div>
  </div>
  <div class="u4-menu" id="u4-menu" hidden data-menu>
    <nav aria-label="Mobile">
      <?php foreach ($nav as $n) { echo '<a href="' . esc_url($n[1]) . '">' . esc_html($n[0]) . '<span aria-hidden="true">' . $arrow . '</span></a>'; } ?>
      <a href="<?php echo esc_url($account_url); ?>">My account<span aria-hidden="true"><?php echo $arrow; ?></span></a>
    </nav>
    <a class="u4-btn u4-btn--block" href="<?php echo esc_url($wa); ?>" target="_blank" rel="noopener">Chat with us on WhatsApp</a>
  </div>
</header>

<main id="u4-main">

  <!-- Hero: full-cover banner on every screen -->
  <section class="u4-hero u5-hero">
    <img class="u5-hero__img" src="<?php echo esc_url(wp_get_attachment_image_url(693, 'full')); ?>" alt="Model with radiant, even skin" fetchpriority="high" decoding="async" width="1408" height="768">
    <div class="u5-hero__copy">
      <p class="u4-hero__kicker u4-up" style="--d:.05s">Skincare for melanin-rich skin</p>
      <h1 class="u4-hero__title u4-up" style="--d:.15s">Glow naturally.<br>Shine boldly.</h1>
      <p class="u4-hero__sub u4-up" style="--d:.28s">Fade dark spots, clear breakouts and even out your tone, without bleaching.</p>
      <div class="u4-hero__ctas u4-up" style="--d:.4s">
        <a class="u4-btn" href="<?php echo esc_url($shop_url); ?>">Shop now</a>
        <a class="u4-btn u4-btn--line" href="#results">See results</a>
      </div>
    </div>
  </section>

  <!-- Benefits: moving strip -->
  <section class="u5-ticker" aria-label="Why Ushine">
    <div class="u5-ticker__track">
      <?php
      $benefits = [
          ['<path d="M12 3c3 4 6 6.5 6 10a6 6 0 0 1-12 0c0-3.5 3-6 6-10Z"/>', 'Made for melanin-rich skin'],
          ['<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/>', 'Fades dark spots without bleaching'],
          ['<rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="12" cy="12" r="3.5"/>', 'Real, unfiltered customer results'],
          ['<path d="M4 20l1.3-3.9A8 8 0 1 1 8 19.1L4 20Z"/>', 'Free skin consultation on WhatsApp'],
      ];
      for ($loop = 0; $loop < 2; $loop++) {
          echo '<ul class="u5-ticker__list"' . ($loop ? ' aria-hidden="true"' : '') . '>';
          foreach ($benefits as $b) {
              echo '<li><svg viewBox="0 0 24 24" aria-hidden="true">' . $b[0] . '</svg>' . esc_html($b[1]) . '</li>';
          }
          echo '</ul>';
      }
      ?>
    </div>
  </section>

  <!-- Categories: four, then view all -->
  <section class="u4-sec u4-cats" data-more>
    <div class="u4-wrap">
      <div class="u4-head"><h2 class="u4-h2">Shop by category</h2><p>Find what your skin needs.</p></div>
      <div class="u5-cats">
        <?php foreach ($categories as $i => $c) : ?>
          <a class="u4-cat<?php echo $i >= 4 ? ' u5-extra' : ''; ?>" href="<?php echo esc_url($c['url']); ?>">
            <span class="u4-cat__img"><?php echo $img($c['img'], 'medium_large', ['alt' => '']); ?></span>
            <span class="u4-cat__name"><?php echo esc_html($c['name']); ?></span>
            <span class="u4-cat__count"><?php echo (int) $c['count']; ?> product<?php echo $c['count'] === 1 ? '' : 's'; ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="u5-more"><button type="button" class="u4-btn u4-btn--line" data-more-btn data-less="Show fewer categories">View all categories</button></div>
    </div>
  </section>

  <!-- Results: drag to compare -->
  <section class="u4-sec u4-tint u4-results" id="results">
    <div class="u4-wrap u4-results__grid">
      <div class="u4-results__copy">
        <h2 class="u4-h2">Real customers, real results</h2>
        <p>Drag the handle to compare. Every photo was sent to us by a customer, exactly as taken.</p>
        <div class="u4-results__nav">
          <button type="button" class="u4-round" data-cmp-prev aria-label="Previous result"><?php echo $arrow_l; ?></button>
          <span class="u4-results__count"><b data-cmp-index>1</b> / <?php echo count($compare); ?></span>
          <button type="button" class="u4-round" data-cmp-next aria-label="Next result"><?php echo $arrow; ?></button>
          <span class="u4-results__concern" data-cmp-label><?php echo esc_html($compare[0][1]); ?></span>
        </div>
      </div>
      <div class="u4-cmp" data-cmp style="--pos:50%">
        <?php foreach ($compare as $i => $c) : ?>
          <div class="u4-cmp__slide<?php echo $i === 0 ? ' is-on' : ''; ?>" data-label="<?php echo esc_attr($c[1]); ?>">
            <img class="u4-cmp__after" src="<?php echo esc_url($up . 'ba/' . $c[0] . '-after.jpg'); ?>" alt="After: <?php echo esc_attr($c[1]); ?>" loading="lazy" draggable="false">
            <div class="u4-cmp__before"><img src="<?php echo esc_url($up . 'ba/' . $c[0] . '-before.jpg'); ?>" alt="Before: <?php echo esc_attr($c[1]); ?>" loading="lazy" draggable="false"></div>
          </div>
        <?php endforeach; ?>
        <span class="u4-cmp__tag u4-cmp__tag--b">Before</span>
        <span class="u4-cmp__tag u4-cmp__tag--a">After</span>
        <input class="u4-cmp__range" type="range" min="0" max="100" value="50" aria-label="Drag to compare before and after" data-cmp-range>
        <span class="u4-cmp__handle" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 6l-6 6 6 6M15 6l6 6-6 6"/></svg></span>
      </div>
    </div>
  </section>

  <!-- Brand banner -->
  <section class="u5-banner-wrap">
    <div class="u5-banner">
      <?php echo $img(715, 'full', ['class' => 'u5-banner__img', 'alt' => '']); ?>
      <div class="u5-banner__copy">
        <h2>Beautiful skin starts <em>here.</em></h2>
        <p>Treatments for dark spots, breakouts and uneven tone, made for melanin-rich skin and backed by real customer results.</p>
        <a class="u4-btn" href="<?php echo esc_url($shop_url); ?>">Explore the collection</a>
      </div>
    </div>
  </section>

  <!-- Bestsellers -->
  <section class="u4-sec u4-shop">
    <div class="u4-wrap">
      <div class="u4-head u4-head--row">
        <div><h2 class="u4-h2">Bestsellers</h2><p>The products our customers come back for.</p></div>
        <a class="u4-link u4-hide-sm" href="<?php echo esc_url($shop_url); ?>">View all products <?php echo $arrow; ?></a>
      </div>
      <div class="u4-grid">
        <?php foreach ($products as $p) { echo ushine_card_v5($p); } ?>
      </div>
      <div class="u4-shop__cta"><a class="u4-btn u4-btn--lg" href="<?php echo esc_url($shop_url); ?>">View all products</a></div>
    </div>
  </section>

  <!-- Shop by concern -->
  <section class="u4-sec u4-tint u4-concerns" data-more>
    <div class="u4-wrap">
      <div class="u4-head"><h2 class="u4-h2">Shop by concern</h2><p>Tell us what is bothering your skin and start there.</p></div>
      <div class="u4-concerns__grid">
        <?php foreach ($concerns as $ci => $c) : ?>
          <a class="u4-concern<?php echo $ci >= 2 ? ' u5-extra-sm' : ''; ?>" href="<?php echo esc_url($c[2]); ?>">
            <span class="u4-concern__img"><?php echo $img($c[3], 'medium_large', ['alt' => '']); ?></span>
            <span class="u4-concern__text"><strong><?php echo esc_html($c[0]); ?></strong><em><?php echo esc_html($c[1]); ?></em><span class="u4-concern__go">Shop now <?php echo $arrow; ?></span></span>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="u5-more u5-more--sm"><button type="button" class="u4-btn u4-btn--line" data-more-btn data-less="Show fewer">View all concerns</button></div>
    </div>
  </section>

  <!-- All before and afters -->
  <section class="u4-sec u4-wall">
    <div class="u4-wrap u4-head"><h2 class="u4-h2">Before and after</h2><p>More results from our customers. Swipe to see them all.</p></div>
    <div class="u5-wall" data-auto>
      <div class="u5-wall__track">
      <?php foreach ($compare as $c) : ?>
        <figure class="u4-ba">
          <div class="u4-ba__pair">
            <div><img src="<?php echo esc_url($up . 'ba/' . $c[0] . '-before.jpg'); ?>" alt="Before" loading="lazy" draggable="false"><span>Before</span></div>
            <div><img src="<?php echo esc_url($up . 'ba/' . $c[0] . '-after.jpg'); ?>" alt="After" loading="lazy" draggable="false"><span>After</span></div>
          </div>
          <figcaption><?php echo esc_html($c[1]); ?></figcaption>
        </figure>
      <?php endforeach; ?>
      </div>
    </div>
  </section>

</main>

<footer class="u4-footer">
  <div class="u4-wrap u4-footer__help">
    <div><h2>Not sure what your skin needs?</h2><p>Tell us your concern and we will recommend a routine for you.</p></div>
    <a class="u4-btn" href="<?php echo esc_url($wa); ?>" target="_blank" rel="noopener">Chat on WhatsApp</a>
  </div>
  <div class="u4-wrap u4-footer__main">
    <div class="u4-footer__brand">
      <img src="<?php echo esc_url($up . 'logo-white.png'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" width="556" height="182" loading="lazy">
      <p>Corrective skincare for melanin-rich skin.</p>
      <a href="<?php echo esc_url(ushine_wa_link()); ?>" target="_blank" rel="noopener">+234 902 626 2772</a>
    </div>
    <div class="u4-footer__col"><h3>Shop</h3><a href="<?php echo esc_url($shop_url); ?>">All products</a><a href="<?php echo esc_url($cat_link('face-products')); ?>">Face</a><a href="<?php echo esc_url($cat_link('body-lotions')); ?>">Body</a><a href="<?php echo esc_url($cat_link('skincare-sets')); ?>">Sets</a></div>
    <div class="u4-footer__col"><h3>Help</h3><a href="<?php echo esc_url(get_permalink(738) ?: home_url('/faq/')); ?>">FAQ</a><a href="<?php echo esc_url(home_url('/contact-us/')); ?>">Contact</a><a href="<?php echo esc_url(get_permalink(731) ?: home_url('/')); ?>">Terms</a></div>
    <div class="u4-footer__col"><h3>Account</h3><a href="<?php echo esc_url($account_url); ?>">My account</a><a href="<?php echo esc_url($cart_url); ?>">Cart</a><a href="<?php echo esc_url(home_url('/about-us/')); ?>">About us</a></div>
  </div>
  <div class="u4-wrap u4-footer__base">&copy; <?php echo esc_html(gmdate('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. All rights reserved.</div>
</footer>

<div class="ush-toast" role="status" aria-live="polite" data-toast></div>
<?php wp_footer(); ?>
</body>
</html>
