<?php
/**
 * Template: Ushine Home (Redesign) - v2, Elurea-style editorial layout in Ushine pink.
 * Self-contained canvas page: own header and footer, live WooCommerce data.
 */

if (!defined('ABSPATH')) {
    exit;
}

$img = static function ($id, string $size = 'large', array $attr = []): string {
    if (!$id) {
        return '';
    }
    return wp_get_attachment_image((int) $id, $size, false, $attr + ['loading' => 'lazy', 'decoding' => 'async']);
};

$stock       = (array) get_option('ushine_stock_media', []);
$s           = static fn(string $k): int => isset($stock[$k]) && is_numeric($stock[$k]) ? (int) $stock[$k] : 0;
$logo_base   = wp_upload_dir()['baseurl'] . '/ushine-home/';
$shop_url    = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
$cart_url    = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/cart/');
$account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');
$cat_url     = static function (string $slug) use ($shop_url): string {
    $link = get_term_link($slug, 'product_cat');
    return is_wp_error($link) ? $shop_url : $link;
};
$prod_url = static function (int $id) use ($shop_url): string {
    $link = get_permalink($id);
    return $link ?: $shop_url;
};
$cart_count = (function_exists('WC') && WC()->cart) ? WC()->cart->get_cart_contents_count() : 0;
$consult    = ushine_wa_link("Hi Ushine, I'd love a free skin consultation. My main concern is: ");
$arrow      = '<span class="ub-btn__chip" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>';

$featured = function_exists('wc_get_products') ? wc_get_products([
    'status'     => 'publish',
    'limit'      => 6,
    'orderby'    => 'popularity',
    'order'      => 'DESC',
    'visibility' => 'catalog',
    'stock_status' => 'instock',
]) : [];

$nav_left  = [['Shop', $shop_url], ['Face', $cat_url('face-products')], ['Body', $cat_url('body-lotions')]];
$nav_right = [['Sets', $cat_url('skincare-sets')], ['About', home_url('/about-us/')], ['Contact', home_url('/contact-us/')]];
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#FDF5F8">
<?php wp_head(); ?>
</head>
<body <?php body_class('ub-page'); ?>>
<?php wp_body_open(); ?>
<a class="ub-skip" href="#ub-main">Skip to content</a>

<header class="ub-header" data-header>
  <div class="ub-header__bar">
    <nav class="ub-nav ub-nav--left" aria-label="Shop">
      <?php foreach ($nav_left as $n) { echo '<a href="' . esc_url($n[1]) . '">' . esc_html($n[0]) . '</a>'; } ?>
    </nav>
    <a class="ub-logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?> home">
      <img class="ub-logo__light" src="<?php echo esc_url($logo_base . 'logo-white.png'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" width="556" height="182">
      <img class="ub-logo__dark" src="<?php echo esc_url($logo_base . 'logo-dark.png'); ?>" alt="" aria-hidden="true" width="556" height="182">
    </a>
    <nav class="ub-nav ub-nav--right" aria-label="Brand">
      <?php foreach ($nav_right as $n) { echo '<a href="' . esc_url($n[1]) . '">' . esc_html($n[0]) . '</a>'; } ?>
    </nav>
    <div class="ub-tools">
      <a class="ub-icon" href="<?php echo esc_url($account_url); ?>" aria-label="My account"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg></a>
      <a class="ub-icon" href="<?php echo esc_url($cart_url); ?>" aria-label="Cart"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8h14l-1.2 11.1a2 2 0 0 1-2 1.9H8.2a2 2 0 0 1-2-1.9L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg><span class="ush-cart-count" data-count="<?php echo (int) $cart_count; ?>"><?php echo (int) $cart_count; ?></span></a>
      <button class="ub-burger" type="button" aria-expanded="false" aria-controls="ub-drawer" data-burger><span></span><span></span><span class="ub-sr">Menu</span></button>
    </div>
  </div>
  <div class="ub-drawer" id="ub-drawer" hidden data-drawer>
    <?php foreach (array_merge($nav_left, $nav_right) as $n) { echo '<a href="' . esc_url($n[1]) . '">' . esc_html($n[0]) . '</a>'; } ?>
  </div>
</header>

<main id="ub-main">

  <!-- Hero: full-bleed photo card, centred message -->
  <section class="ub-hero">
    <div class="ub-hero__card">
      <div class="ub-hero__media"><?php echo $img($s('hero'), 'full', ['class' => 'ub-cover', 'loading' => 'eager', 'fetchpriority' => 'high', 'alt' => 'Woman smoothing cream onto her cheek during her morning routine']); ?></div>
      <div class="ub-hero__shade" aria-hidden="true"></div>
      <div class="ub-hero__copy">
        <h1 class="ub-hero__title ub-rise" style="--d:.15s">Glow Naturally,<br>Shine Boldly</h1>
        <a class="ub-btn ub-rise" style="--d:.35s" href="<?php echo esc_url($shop_url); ?>">Shop the collection<?php echo $arrow; ?></a>
        <p class="ub-hero__sub ub-rise" style="--d:.5s">Skincare that fades dark spots, clears breakouts and brings back your glow, so you never have to hide behind filters.</p>
      </div>
    </div>
  </section>

  <!-- Portrait row -->
  <section class="ub-section ub-tint ub-faces">
    <h2 class="ub-h2 ub-center ub-reveal">Made for Melanin-Rich Skin,<br>Every Shade of It</h2>
    <div class="ub-faces__row">
      <?php
      foreach (['p1', 'p2', 'p3', 'p4', 'p5'] as $i => $k) {
          echo '<figure class="ub-face ub-face--' . ($i + 1) . ' ub-reveal" style="--d:' . esc_attr((string) ($i * 0.08)) . 's">' . $img($s($k), 'large', ['alt' => 'Woman with glowing skin']) . '</figure>';
      }
      ?>
    </div>
    <p class="ub-lead ub-center ub-reveal">Our formulas fade dark spots and even out your tone gently, without bleaching, so the glow that comes through is entirely yours.</p>
    <div class="ub-center ub-reveal"><a class="ub-btn" href="<?php echo esc_url($shop_url); ?>">Explore all products<?php echo $arrow; ?></a></div>
  </section>

  <!-- Results story -->
  <section class="ub-section ub-story">
    <div class="ub-wrap">
      <div class="ub-story__head">
        <h2 class="ub-h2 ub-reveal">Real Results You Can<br>See in Days</h2>
        <p class="ub-small ub-reveal">Customers send us their progress every week. This is what consistent use looks like, unfiltered and straight from their phones.</p>
      </div>
      <div class="ub-story__stage">
        <div class="ub-story__photo ub-reveal"><?php echo $img($s('story'), 'large', ['alt' => 'Woman applying moisturiser from a jar']); ?></div>
        <article class="ub-float ub-float--a ub-reveal" style="--d:.15s">
          <span class="ub-float__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 2c.6 4.8 2.7 7.4 8 8-5.3.6-7.4 3.2-8 10-.6-6.8-2.7-9.4-8-10 5.3-.6 7.4-3.2 8-8Z"/></svg></span>
          <h3>4-Day Review</h3>
          <p>"No peeling, it just fades the dark knuckles off."</p>
          <figure><?php echo $img(1182, 'medium', ['alt' => 'Customer knuckles before and after four days']); ?></figure>
          <a class="ub-btn ub-btn--sm" href="<?php echo esc_url($prod_url(782)); ?>">Knuckle Fade Cream<?php echo $arrow; ?></a>
        </article>
        <article class="ub-float ub-float--b ub-reveal" style="--d:.3s">
          <figure><?php echo $img(1180, 'medium', ['alt' => 'Customer hand before and after three days']); ?></figure>
          <h3>3 Days Progress</h3>
          <p>Sent in by a customer, no filters.</p>
        </article>
      </div>
    </div>
  </section>

  <!-- Products -->
  <section class="ub-section ub-tint ub-products">
    <h2 class="ub-h2 ub-center ub-reveal">Targeted Treatments,<br>Powered by Proven Actives</h2>
    <div class="ub-wrap ub-products__grid">
      <div class="ub-products__photo ub-reveal"><?php echo $img($s('wide'), 'large', ['alt' => 'Woman in a turban touching her glowing cheek']); ?></div>
      <div class="ub-products__list">
        <?php foreach ($featured as $i => $product) { echo str_replace('<article class="ush-card">', '<article class="ush-card ub-reveal" style="--d:' . esc_attr((string) ($i * 0.06)) . 's">', ushine_product_card($product)); } ?>
      </div>
    </div>
    <p class="ub-lead ub-center ub-reveal">Niacinamide, Vitamin C, Kojic acid and skin-loving botanicals, chosen for melanin-rich skin and the concerns it actually has.</p>
    <div class="ub-center ub-reveal"><a class="ub-btn" href="<?php echo esc_url($shop_url); ?>">Explore more products<?php echo $arrow; ?></a></div>
  </section>

  <!-- Outcomes -->
  <section class="ub-section ub-outcomes" id="results">
    <h2 class="ub-h2 ub-center ub-reveal">Authentic Skin,<br>Genuine Outcomes</h2>
    <div class="ub-outcomes__rail" data-drag>
      <?php
      $results = [
          [1180, 'Before and 3 days of progress'],
          [1182, 'Day 4, dark knuckles fading with no peeling'],
          [1178, 'Hands, before and after'],
          [1179, 'Feet, before and after'],
      ];
      foreach ($results as $i => $r) {
          echo '<figure class="ub-result ub-reveal" style="--d:' . esc_attr((string) ($i * 0.08)) . 's">' . $img($r[0], 'large', ['alt' => esc_attr($r[1]), 'draggable' => 'false']) . '<figcaption><strong>' . esc_html($r[1]) . '</strong><span>Sent by a customer via WhatsApp</span></figcaption></figure>';
      }
      ?>
    </div>
    <div class="ub-wrap ub-quotes">
      <blockquote class="ub-quote ub-reveal"><p>“Four months ago I had a serious breakout. I used the products you recommended diligently and they cleared my face and gave me back my confidence.”</p><footer>Ushine customer</footer></blockquote>
      <blockquote class="ub-quote ub-reveal" style="--d:.08s"><p>“In just a few days my face is already glowing and the hyperpigmentation is disappearing drastically.”</p><footer>Ushine customer</footer></blockquote>
      <blockquote class="ub-quote ub-reveal" style="--d:.16s"><p>“Omo, this body scrub na bomb. My skin has never felt like this before.”</p><footer>Ushine customer</footer></blockquote>
    </div>
  </section>

  <!-- Consultation -->
  <section class="ub-cta">
    <div class="ub-cta__card">
      <div class="ub-cta__copy">
        <h2 class="ub-h2 ub-reveal">Not Sure Where to Start?<br>Get Your Routine in One Chat</h2>
        <div class="ub-cta__actions ub-reveal">
          <a class="ub-btn" href="<?php echo esc_url($consult); ?>" target="_blank" rel="noopener">Free skin consultation<?php echo $arrow; ?></a>
          <a class="ub-cta__tel" href="<?php echo esc_url(ushine_wa_link()); ?>" target="_blank" rel="noopener">+234 902 626 2772</a>
        </div>
      </div>
      <div class="ub-cta__media" data-parallax-y><?php echo $img($s('rest'), 'full', ['alt' => 'Relaxed woman lying down with glowing skin']); ?></div>
    </div>
  </section>

</main>

<footer class="ub-footer">
  <div class="ub-wrap ub-footer__top">
    <div class="ub-footer__brand">
      <img src="<?php echo esc_url($logo_base . 'logo-pink.png'); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>" width="556" height="182" loading="lazy">
      <p>Corrective skincare for melanin-rich skin. Fade dark spots, clear breakouts and glow, without bleaching.</p>
    </div>
    <div class="ub-footer__col"><h3>Shop</h3><a href="<?php echo esc_url($cat_url('face-products')); ?>">Face</a><a href="<?php echo esc_url($cat_url('body-lotions')); ?>">Body</a><a href="<?php echo esc_url($cat_url('skincare-sets')); ?>">Sets</a><a href="<?php echo esc_url($shop_url); ?>">All products</a></div>
    <div class="ub-footer__col"><h3>Help</h3><a href="<?php echo esc_url(get_permalink(738) ?: home_url('/faq/')); ?>">FAQ</a><a href="<?php echo esc_url(home_url('/contact-us/')); ?>">Contact</a><a href="<?php echo esc_url(get_permalink(731) ?: home_url('/')); ?>">Terms</a><a href="<?php echo esc_url(home_url('/about-us/')); ?>">About us</a></div>
    <div class="ub-footer__col"><h3>Account</h3><a href="<?php echo esc_url($account_url); ?>">My account</a><a href="<?php echo esc_url($cart_url); ?>">Cart</a><a href="<?php echo esc_url(ushine_wa_link()); ?>" target="_blank" rel="noopener">WhatsApp us</a></div>
  </div>
  <div class="ub-wrap ub-footer__base"><p>&copy; <?php echo esc_html(gmdate('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?>. All rights reserved.</p></div>
</footer>

<div class="ush-toast" role="status" aria-live="polite" data-toast></div>
<?php wp_footer(); ?>
</body>
</html>
