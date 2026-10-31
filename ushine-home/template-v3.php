<?php
/**
 * Template: Ushine Home (Redesign) - v3.
 * Full-bleed hero, Elurea-shaped category row, before/after compare slider,
 * bestseller grid, before/after marquee. Own header + footer, live WooCommerce data.
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
$cat_count = static function (string $slug): int {
    $t = get_term_by('slug', $slug, 'product_cat');
    return $t ? (int) $t->count : 0;
};
$cart_count = (function_exists('WC') && WC()->cart) ? WC()->cart->get_cart_contents_count() : 0;
$wa         = ushine_wa_link("Hi Ushine, I'd love help choosing products for my skin. My main concern is: ");
$arrow      = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

// Product card (clean, product-first).
if (!function_exists('ushine_card_v3')) {
    function ushine_card_v3(WC_Product $p): string
    {
        $id    = $p->get_id();
        $link  = get_permalink($id);
        $terms = get_the_terms($id, 'product_cat');
        $cat   = '';
        if ($terms && !is_wp_error($terms)) {
            $names = array_values(array_filter(wp_list_pluck($terms, 'name'), static fn($n) => $n !== 'Uncategorized'));
            $cat   = $names[0] ?? '';
        }
        $image = $p->get_image_id()
            ? wp_get_attachment_image($p->get_image_id(), 'woocommerce_thumbnail', false, ['class' => 'pc__img', 'loading' => 'lazy', 'alt' => esc_attr($p->get_name())])
            : '';
        $badge = '';
        if (!$p->is_in_stock()) {
            $badge = '<span class="pc__badge">Sold out</span>';
        } elseif ($p->is_on_sale() && !$p->is_type('variable') && (float) $p->get_regular_price() > 0) {
            $badge = '<span class="pc__badge pc__badge--sale">-' . (int) round((1 - (float) $p->get_price() / (float) $p->get_regular_price()) * 100) . '%</span>';
        }
        if ($p->is_type('simple') && $p->is_purchasable() && $p->is_in_stock()) {
            $btn = sprintf('<a href="%s" data-quantity="1" data-product_id="%d" data-product_sku="%s" class="pc__add add_to_cart_button ajax_add_to_cart" aria-label="%s" rel="nofollow"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></a>', esc_url($p->add_to_cart_url()), $id, esc_attr($p->get_sku()), esc_attr('Add ' . $p->get_name() . ' to cart'));
        } else {
            $btn = sprintf('<a href="%s" class="pc__add" aria-label="%s"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>', esc_url($link), esc_attr('Choose options for ' . $p->get_name()));
        }
        return '<article class="pc ush-card">'
            . '<a class="pc__media" href="' . esc_url($link) . '" tabindex="-1" aria-hidden="true">' . $image . $badge . '</a>'
            . '<div class="pc__body">'
            . ($cat ? '<span class="pc__cat">' . esc_html($cat) . '</span>' : '')
            . '<h3 class="pc__name ush-card__name"><a href="' . esc_url($link) . '">' . esc_html($p->get_name()) . '</a></h3>'
            . '<div class="pc__row"><p class="pc__price">' . ushine_price_html($p) . '</p>' . $btn . '</div>'
            . '</div></article>';
    }
}

$products = function_exists('wc_get_products') ? wc_get_products([
    'status' => 'publish', 'limit' => 8, 'orderby' => 'popularity', 'order' => 'DESC', 'visibility' => 'catalog',
]) : [];

$categories = [
    ['Face Care', 'face-products', 1224],
    ['Serums', 'serums', 1072],
    ['Treatments', 'treatments', 1183],
    ['Body Oils', 'oils', 1214],
    ['Soaps', 'soaps', 1209],
];

$compare = [
    [854, 'Acne and dark marks'],
    [853, 'Breakouts and uneven texture'],
    [852, 'Hyperpigmentation'],
    [851, 'Dark patches'],
    [1041, 'Dull, uneven tone'],
];

$raw_body = [[1175, 'Hand, after'], [1177, 'Feet, after'], [1176, 'Legs, before meets after']];

$nav = [['Shop', $shop_url], ['Face', $cat_link('face-products')], ['Body', $cat_link('body-lotions')], ['Sets', $cat_link('skincare-sets')], ['Results', '#results'], ['Contact', home_url('/contact-us/')]];
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#FFFFFF">
<?php wp_head(); ?>
</head>
<body <?php body_class('u3'); ?>>
<?php wp_body_open(); ?>
<a class="u3-skip" href="#u3-main">Skip to content</a>

<header class="u3-header" data-header>
  <div class="u3-header__in">
    <button class="u3-burger" type="button" aria-expanded="false" aria-controls="u3-menu" data-burger><span></span><span></span><span class="u3-sr">Menu</span></button>
    <a class="u3-logo" href="<?php echo esc_url(home_url('/')); ?>"><img src="<?php echo esc_url($up . 'logo-dark.png'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" width="556" height="182"></a>
    <nav class="u3-nav" aria-label="Main">
      <?php foreach ($nav as $n) { echo '<a href="' . esc_url($n[1]) . '">' . esc_html($n[0]) . '</a>'; } ?>
    </nav>
    <div class="u3-tools">
      <a class="u3-icon u3-hide-sm" href="<?php echo esc_url($account_url); ?>" aria-label="My account"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg></a>
      <a class="u3-icon" href="<?php echo esc_url($cart_url); ?>" aria-label="Cart"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8h14l-1.2 11.1a2 2 0 0 1-2 1.9H8.2a2 2 0 0 1-2-1.9L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg><span class="ush-cart-count" data-count="<?php echo (int) $cart_count; ?>"><?php echo (int) $cart_count; ?></span></a>
    </div>
  </div>
</header>

<div class="u3-menu" id="u3-menu" hidden data-menu>
  <nav aria-label="Mobile">
    <?php foreach ($nav as $n) { echo '<a href="' . esc_url($n[1]) . '">' . esc_html($n[0]) . '</a>'; } ?>
    <a href="<?php echo esc_url($account_url); ?>">My account</a>
  </nav>
  <a class="u3-btn u3-btn--block" href="<?php echo esc_url($wa); ?>" target="_blank" rel="noopener">Chat with us on WhatsApp</a>
</div>

<main id="u3-main">

  <!-- 1. Hero: edge to edge -->
  <section class="u3-hero">
    <picture class="u3-hero__img">
      <source media="(max-width: 760px)" srcset="<?php echo esc_url(wp_get_attachment_image_url(898, 'large')); ?>">
      <img src="<?php echo esc_url(wp_get_attachment_image_url(693, 'full')); ?>" alt="Model with radiant, even skin" fetchpriority="high" decoding="async" width="1408" height="768">
    </picture>
    <div class="u3-hero__copy">
      <h1 class="u3-hero__title u3-up" style="--d:.1s">Glow naturally.<br>Shine boldly.</h1>
      <p class="u3-hero__sub u3-up" style="--d:.25s">Skincare for melanin-rich skin that fades dark spots, clears breakouts and evens your tone, without bleaching.</p>
      <div class="u3-hero__ctas u3-up" style="--d:.4s">
        <a class="u3-btn" href="<?php echo esc_url($shop_url); ?>">Shop now</a>
        <a class="u3-btn u3-btn--line" href="#results">See real results</a>
      </div>
    </div>
  </section>

  <!-- 2. Categories, in the reference's angled card row -->
  <section class="u3-sec u3-cats">
    <div class="u3-head u3-head--center">
      <h2 class="u3-h2 u3-reveal">Shop by category</h2>
      <p class="u3-reveal">Start with what your skin needs most.</p>
    </div>
    <div class="u3-cats__row" data-swipe>
      <?php foreach ($categories as $i => $c) :
          $count = $cat_count($c[1]); ?>
        <a class="u3-cat u3-cat--<?php echo (int) ($i + 1); ?> u3-reveal" style="--d:<?php echo esc_attr((string) ($i * 0.07)); ?>s" href="<?php echo esc_url($cat_link($c[1])); ?>">
          <?php echo $img($c[2], 'large', ['alt' => '']); ?>
          <span class="u3-cat__label"><strong><?php echo esc_html($c[0]); ?></strong><em><?php echo (int) $count; ?> product<?php echo $count === 1 ? '' : 's'; ?></em></span>
          <span class="u3-cat__go" aria-hidden="true"><?php echo $arrow; ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- 3. Results: drag to compare -->
  <section class="u3-sec u3-tint u3-results" id="results">
    <div class="u3-wrap u3-results__grid">
      <div class="u3-results__copy">
        <h2 class="u3-h2 u3-reveal">Real customers. <br>Real results.</h2>
        <p class="u3-reveal">Drag the handle to see the change. Every photo was sent to us by a customer, exactly as taken.</p>
        <div class="u3-results__nav u3-reveal">
          <button type="button" class="u3-round" data-cmp-prev aria-label="Previous result"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></button>
          <span class="u3-results__count"><b data-cmp-index>1</b> / <?php echo count($compare); ?></span>
          <button type="button" class="u3-round" data-cmp-next aria-label="Next result"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
        </div>
        <p class="u3-results__concern u3-reveal" data-cmp-label><?php echo esc_html($compare[0][1]); ?></p>
      </div>
      <div class="u3-cmp u3-reveal" data-cmp style="--pos:50%">
        <?php foreach ($compare as $i => $c) : ?>
          <div class="u3-cmp__slide<?php echo $i === 0 ? ' is-on' : ''; ?>" data-label="<?php echo esc_attr($c[1]); ?>">
            <img class="u3-cmp__after" src="<?php echo esc_url($up . 'ba/' . $c[0] . '-after.jpg'); ?>" alt="After: <?php echo esc_attr($c[1]); ?>" loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>" draggable="false">
            <div class="u3-cmp__before"><img src="<?php echo esc_url($up . 'ba/' . $c[0] . '-before.jpg'); ?>" alt="Before: <?php echo esc_attr($c[1]); ?>" loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>" draggable="false"></div>
          </div>
        <?php endforeach; ?>
        <span class="u3-cmp__tag u3-cmp__tag--b">Before</span>
        <span class="u3-cmp__tag u3-cmp__tag--a">After</span>
        <input class="u3-cmp__range" type="range" min="0" max="100" value="50" aria-label="Drag to compare before and after" data-cmp-range>
        <span class="u3-cmp__handle" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 6l-6 6 6 6M15 6l6 6-6 6"/></svg></span>
      </div>
    </div>
  </section>

  <!-- 4. Bestsellers -->
  <section class="u3-sec u3-shop">
    <div class="u3-wrap">
      <div class="u3-head u3-head--row">
        <h2 class="u3-h2 u3-reveal">Bestsellers</h2>
        <a class="u3-link u3-reveal" href="<?php echo esc_url($shop_url); ?>">View all <?php echo $arrow; ?></a>
      </div>
      <div class="u3-grid">
        <?php foreach ($products as $i => $p) { echo str_replace('<article class="pc ush-card">', '<article class="pc ush-card u3-reveal" style="--d:' . esc_attr((string) (($i % 4) * 0.06)) . 's">', ushine_card_v3($p)); } ?>
      </div>
      <div class="u3-shop__cta u3-reveal"><a class="u3-btn u3-btn--lg" href="<?php echo esc_url($shop_url); ?>">Shop all products</a></div>
    </div>
  </section>

  <!-- 5. All before and afters -->
  <section class="u3-sec u3-tint u3-wall">
    <div class="u3-head u3-head--center">
      <h2 class="u3-h2 u3-reveal">Before and after</h2>
      <p class="u3-reveal">More results from our customers. Swipe or drag to see them all.</p>
    </div>
    <div class="u3-wall__rail" data-drag data-auto>
      <div class="u3-wall__track">
        <?php
        foreach ($compare as $c) {
            echo '<figure class="u3-ba"><div class="u3-ba__pair"><div><img src="' . esc_url($up . 'ba/' . $c[0] . '-before.jpg') . '" alt="Before" loading="lazy" draggable="false"><span>Before</span></div><div><img src="' . esc_url($up . 'ba/' . $c[0] . '-after.jpg') . '" alt="After" loading="lazy" draggable="false"><span>After</span></div></div><figcaption>' . esc_html($c[1]) . '</figcaption></figure>';
        }
        foreach ($raw_body as $r) {
            echo '<figure class="u3-ba u3-ba--single">' . $img($r[0], 'large', ['alt' => esc_attr($r[1]), 'draggable' => 'false']) . '<figcaption>' . esc_html($r[1]) . '</figcaption></figure>';
        }
        ?>
      </div>
    </div>
  </section>

</main>

<footer class="u3-footer">
  <div class="u3-footer__help">
    <div class="u3-wrap u3-footer__help-in">
      <div>
        <h2>Not sure what your skin needs?</h2>
        <p>Tell us your concern on WhatsApp and we will recommend a routine.</p>
      </div>
      <a class="u3-btn" href="<?php echo esc_url($wa); ?>" target="_blank" rel="noopener">Chat on WhatsApp</a>
    </div>
  </div>
  <div class="u3-wrap u3-footer__main">
    <div class="u3-footer__brand">
      <img src="<?php echo esc_url($up . 'logo-dark.png'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" width="556" height="182" loading="lazy">
      <p>Corrective skincare for melanin-rich skin, made to fade dark spots, clear breakouts and bring back your glow.</p>
      <a class="u3-footer__wa" href="<?php echo esc_url(ushine_wa_link()); ?>" target="_blank" rel="noopener">WhatsApp: +234 902 626 2772</a>
    </div>
    <div class="u3-footer__col"><h3>Shop</h3><a href="<?php echo esc_url($shop_url); ?>">All products</a><a href="<?php echo esc_url($cat_link('face-products')); ?>">Face care</a><a href="<?php echo esc_url($cat_link('body-lotions')); ?>">Body care</a><a href="<?php echo esc_url($cat_link('skincare-sets')); ?>">Skincare sets</a></div>
    <div class="u3-footer__col"><h3>Help</h3><a href="<?php echo esc_url(get_permalink(738) ?: home_url('/faq/')); ?>">FAQ</a><a href="<?php echo esc_url(home_url('/contact-us/')); ?>">Contact us</a><a href="<?php echo esc_url(get_permalink(731) ?: home_url('/')); ?>">Terms and conditions</a></div>
    <div class="u3-footer__col"><h3>Company</h3><a href="<?php echo esc_url(home_url('/about-us/')); ?>">About us</a><a href="<?php echo esc_url($account_url); ?>">My account</a><a href="<?php echo esc_url($cart_url); ?>">Cart</a></div>
  </div>
  <div class="u3-wrap u3-footer__base"><span>&copy; <?php echo esc_html(gmdate('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?></span><span>Made for melanin-rich skin</span></div>
</footer>

<div class="ush-toast" role="status" aria-live="polite" data-toast></div>
<?php wp_footer(); ?>
</body>
</html>
