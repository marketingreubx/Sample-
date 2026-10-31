<?php
/**
 * Template: Ushine Home (Redesign)
 * Self-contained canvas page: own header and footer, live WooCommerce data.
 */

if (!defined('ABSPATH')) {
    exit;
}

$img = static function (int $id, string $size = 'large', array $attr = []): string {
    return wp_get_attachment_image($id, $size, false, $attr + ['loading' => 'lazy', 'decoding' => 'async']);
};

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
$logo_id    = (int) get_theme_mod('custom_logo');
$consult    = ushine_wa_link("Hi Ushine, I'd love a free skin consultation. My main concern is: ");

// Product rails.
$rails = [
    'face' => ['label' => 'Face', 'cats' => ['face-products', 'face-creams', 'serums', 'toners', 'sunscreens']],
    'body' => ['label' => 'Body', 'cats' => ['body-lotions', 'body-scrubs', 'oils', 'soaps']],
    'sets' => ['label' => 'Sets', 'cats' => ['skincare-sets', 'face-sets', 'full-sets']],
];
foreach ($rails as $key => $rail) {
    $rails[$key]['products'] = function_exists('wc_get_products') ? wc_get_products([
        'status'   => 'publish',
        'limit'    => 12,
        'category' => $rail['cats'],
        'orderby'  => 'popularity',
        'order'    => 'DESC',
        'visibility' => 'catalog',
    ]) : [];
}

$luxury    = function_exists('wc_get_product') ? wc_get_product(1247) : null;
$luxury2   = function_exists('wc_get_product') ? wc_get_product(1249) : null;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#F8F2EF">
<?php wp_head(); ?>
</head>
<body <?php body_class('ush-page'); ?>>
<?php wp_body_open(); ?>
<a class="ush-skip" href="#ush-main">Skip to content</a>

<header class="ush-header" data-header>
  <div class="ush-header__bar">
    <a class="ush-logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?> home">
      <?php
      if ($logo_id) {
          echo wp_get_attachment_image($logo_id, 'medium', false, ['class' => 'ush-logo__img', 'alt' => esc_attr(get_bloginfo('name')), 'loading' => 'eager']);
      } else {
          echo '<span class="ush-logo__text">' . esc_html(get_bloginfo('name')) . '</span>';
      }
      ?>
    </a>
    <nav class="ush-nav" aria-label="Main">
      <a href="<?php echo esc_url($shop_url); ?>">Shop all</a>
      <a href="<?php echo esc_url($cat_url('face-products')); ?>">Face</a>
      <a href="<?php echo esc_url($cat_url('body-lotions')); ?>">Body</a>
      <a href="<?php echo esc_url($cat_url('skincare-sets')); ?>">Sets</a>
      <a href="<?php echo esc_url(home_url('/about-us/')); ?>">About</a>
      <a href="<?php echo esc_url(home_url('/contact-us/')); ?>">Contact</a>
    </nav>
    <div class="ush-header__tools">
      <a class="ush-icon" href="<?php echo esc_url($account_url); ?>" aria-label="My account">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
      </a>
      <a class="ush-icon ush-icon--cart" href="<?php echo esc_url($cart_url); ?>" aria-label="Cart">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8h14l-1.2 11.1a2 2 0 0 1-2 1.9H8.2a2 2 0 0 1-2-1.9L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
        <span class="ush-cart-count" data-count="<?php echo (int) $cart_count; ?>"><?php echo (int) $cart_count; ?></span>
      </a>
      <button class="ush-burger" type="button" aria-expanded="false" aria-controls="ush-drawer" data-burger>
        <span></span><span></span><span class="ush-sr">Menu</span>
      </button>
    </div>
  </div>
  <div class="ush-drawer" id="ush-drawer" hidden data-drawer>
    <a href="<?php echo esc_url($shop_url); ?>">Shop all</a>
    <a href="<?php echo esc_url($cat_url('face-products')); ?>">Face</a>
    <a href="<?php echo esc_url($cat_url('body-lotions')); ?>">Body</a>
    <a href="<?php echo esc_url($cat_url('skincare-sets')); ?>">Sets</a>
    <a href="<?php echo esc_url(home_url('/about-us/')); ?>">About</a>
    <a href="<?php echo esc_url(home_url('/contact-us/')); ?>">Contact</a>
  </div>
</header>

<main id="ush-main">

  <!-- Hero -->
  <section class="ush-hero">
    <div class="ush-hero__frame">
      <div class="ush-hero__copy">
        <h1 class="ush-hero__title" data-split>Glow naturally. <em>Shine boldly.</em></h1>
        <p class="ush-hero__sub ush-in" style="--d:.35s">Skincare that fades dark spots, clears breakouts and brings back your glow, so you never have to hide behind filters.</p>
        <div class="ush-hero__ctas ush-in" style="--d:.5s">
          <a class="ush-btn" href="<?php echo esc_url($shop_url); ?>">Shop the collection<span class="ush-btn__chip" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span></a>
          <a class="ush-btn ush-btn--ghost" href="#results">See real results</a>
        </div>
      </div>
      <div class="ush-hero__art">
        <div class="ush-hero__main ush-in ush-in--scale" style="--d:.15s">
          <?php echo $img(1248, 'large', ['class' => 'ush-cover', 'loading' => 'eager', 'fetchpriority' => 'high', 'alt' => 'Ushine Luxury skincare set with lotion, soaps, scrubs and Half Cast Oil']); ?>
        </div>
        <figure class="ush-float ush-float--serum ush-in" style="--d:.6s" data-parallax="18">
          <?php echo $img(1090, 'medium', ['alt' => 'Half Cast Serum']); ?>
        </figure>
        <figure class="ush-float ush-float--result ush-in" style="--d:.75s" data-parallax="-12">
          <?php echo $img(1180, 'medium', ['alt' => 'Customer hand before and after 3 days of use']); ?>
          <figcaption>Before and 3 days later, unfiltered</figcaption>
        </figure>
      </div>
    </div>
  </section>

  <!-- Benefits marquee -->
  <section class="ush-marquee" aria-label="What our skincare does">
    <div class="ush-marquee__track" data-marquee>
      <?php
      $benefits = ['Fades dark spots', 'Clears breakouts', 'Evens skin tone', 'Brightens without bleaching', 'Softens knuckles and elbows', 'Made for melanin-rich skin'];
      for ($loop = 0; $loop < 2; $loop++) {
          echo '<ul class="ush-marquee__list"' . ($loop ? ' aria-hidden="true"' : '') . '>';
          foreach ($benefits as $b) {
              echo '<li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2c.6 4.8 2.7 7.4 8 8-5.3.6-7.4 3.2-8 10-.6-6.8-2.7-9.4-8-10 5.3-.6 7.4-3.2 8-8Z"/></svg>' . esc_html($b) . '</li>';
          }
          echo '</ul>';
      }
      ?>
    </div>
  </section>

  <!-- Shop by concern -->
  <section class="ush-section ush-concern">
    <div class="ush-wrap">
      <h2 class="ush-h2 ush-reveal">What is your skin <em>asking for?</em></h2>
      <div class="ush-bento">
        <a class="ush-tile ush-tile--a ush-reveal" href="<?php echo esc_url($prod_url(1060)); ?>">
          <div class="ush-tile__text">
            <h3>Dark spots and hyperpigmentation</h3>
            <p>A complete routine with niacinamide, depigment cream and daily SPF to fade marks and even your tone.</p>
            <span class="ush-link">Shop the Hyperpigmentation Set</span>
          </div>
          <div class="ush-tile__img"><?php echo $img(1193, 'large', ['alt' => 'Hyperpigmentation Set']); ?></div>
        </a>
        <a class="ush-tile ush-tile--b ush-reveal" style="--d:.08s" href="<?php echo esc_url($prod_url(1073)); ?>">
          <div class="ush-tile__text">
            <h3>Acne and breakouts</h3>
            <p>Clear congestion and fade the marks breakouts leave behind, at the same time.</p>
            <span class="ush-link">Shop the Acne Set</span>
          </div>
          <div class="ush-tile__img"><?php echo $img(1068, 'large', ['alt' => 'Hyperpigmentation and Acne Set']); ?></div>
        </a>
        <a class="ush-tile ush-tile--c ush-reveal" style="--d:.16s" href="<?php echo esc_url($prod_url(782)); ?>">
          <div class="ush-tile__img"><?php echo $img(1081, 'medium', ['alt' => 'Knuckle Fade Cream']); ?></div>
          <div class="ush-tile__text">
            <h3>Dark knuckles, elbows and knees</h3>
            <span class="ush-link">Knuckle Fade Cream</span>
          </div>
        </a>
        <a class="ush-tile ush-tile--d ush-reveal" style="--d:.24s" href="<?php echo esc_url($cat_url('oils')); ?>">
          <div class="ush-tile__img"><?php echo $img(1220, 'medium', ['alt' => 'Whitening body oil']); ?></div>
          <div class="ush-tile__text">
            <h3>All-over body glow</h3>
            <span class="ush-link">Shop body oils</span>
          </div>
        </a>
      </div>
    </div>
  </section>

  <!-- Bestsellers rail -->
  <section class="ush-section ush-shop" data-tabs>
    <div class="ush-wrap">
      <div class="ush-shop__head">
        <div class="ush-tabs" role="tablist" aria-label="Product categories">
          <?php $first = true; foreach ($rails as $key => $rail) : ?>
            <button class="ush-tab<?php echo $first ? ' is-active' : ''; ?>" role="tab" type="button" id="ush-tab-<?php echo esc_attr($key); ?>" aria-controls="ush-panel-<?php echo esc_attr($key); ?>" aria-selected="<?php echo $first ? 'true' : 'false'; ?>" tabindex="<?php echo $first ? '0' : '-1'; ?>" data-tab="<?php echo esc_attr($key); ?>">
              <?php echo esc_html($rail['label']); ?><sup><?php echo count($rail['products']); ?></sup>
            </button>
          <?php $first = false; endforeach; ?>
        </div>
        <div class="ush-rail-nav">
          <button type="button" class="ush-round" data-rail-prev aria-label="Previous products"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6"/></svg></button>
          <button type="button" class="ush-round" data-rail-next aria-label="Next products"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
        </div>
      </div>
      <?php $first = true; foreach ($rails as $key => $rail) : ?>
        <div class="ush-rail" role="tabpanel" id="ush-panel-<?php echo esc_attr($key); ?>" aria-labelledby="ush-tab-<?php echo esc_attr($key); ?>" data-panel="<?php echo esc_attr($key); ?>"<?php echo $first ? '' : ' hidden'; ?>>
          <?php foreach ($rail['products'] as $product) { echo ushine_product_card($product); } ?>
          <a class="ush-card ush-card--more" href="<?php echo esc_url($cat_url($rail['cats'][0])); ?>">
            <span>See all <?php echo esc_html(strtolower($rail['label'])); ?> products</span>
            <span class="ush-round" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
          </a>
        </div>
      <?php $first = false; endforeach; ?>
    </div>
  </section>

  <!-- Real results -->
  <section class="ush-results" id="results">
    <div class="ush-results__panel">
      <div class="ush-results__head">
        <h2 class="ush-h2 ush-reveal">Real skin. Real results. <em>Zero filters.</em></h2>
        <p class="ush-reveal" style="--d:.1s">Sent to us by customers, straight from their phones. Drag to see more.</p>
      </div>
      <div class="ush-results__rail" data-drag>
        <?php
        $results = [
            [1180, 'Before and 3 days of progress'],
            [1182, 'Day 4, dark knuckles fading with no peeling'],
            [1178, 'Hands, before and after'],
            [1179, 'Feet, before and after'],
        ];
        foreach ($results as $i => $r) {
            echo '<figure class="ush-result ush-reveal" style="--d:' . esc_attr((string) ($i * 0.08)) . 's">' . $img($r[0], 'large', ['alt' => esc_attr($r[1]), 'draggable' => 'false']) . '<figcaption>' . esc_html($r[1]) . '</figcaption></figure>';
        }
        ?>
      </div>
    </div>
  </section>

  <!-- Spotlight -->
  <?php if ($luxury) : ?>
  <section class="ush-section ush-spot">
    <div class="ush-wrap ush-spot__grid">
      <div class="ush-spot__img ush-reveal ush-reveal--left">
        <?php echo $img(1246, 'large', ['alt' => 'The Ushine Luxury Set']); ?>
      </div>
      <div class="ush-spot__copy">
        <h2 class="ush-h2 ush-reveal">The Luxury Set, <em>your full glow-up</em></h2>
        <p class="ush-reveal" style="--d:.08s">A head-to-toe routine curated to soften skin, clear blemishes and uneven tone, and brighten gradually, without bleaching.</p>
        <ul class="ush-checks ush-reveal" style="--d:.16s">
          <li>Targets dull, uneven skin and blemishes</li>
          <li>Supports collagen for a youthful bounce</li>
          <li>Lightens gradually and gently</li>
        </ul>
        <div class="ush-spot__buy ush-reveal" style="--d:.24s">
          <p class="ush-price ush-price--lg"><?php echo ushine_price_html($luxury); ?></p>
          <a class="ush-btn" href="<?php echo esc_url($prod_url(1247)); ?>">Shop the Luxury Set<span class="ush-btn__chip" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span></a>
        </div>
        <?php if ($luxury2) : ?>
          <a class="ush-link ush-reveal" style="--d:.3s" href="<?php echo esc_url($prod_url(1249)); ?>">Or view Luxury Set 2</a>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- Testimonials -->
  <section class="ush-section ush-love">
    <div class="ush-wrap">
      <h2 class="ush-h2 ush-reveal">Messages that <em>make our day</em></h2>
      <div class="ush-love__grid">
        <blockquote class="ush-quote ush-quote--big ush-reveal">
          <p>“Four months ago I had a serious skin breakout. I reached out, you recommended products and I used them diligently. Your products did the magic. It cleared my face and gave me back my confidence.”</p>
          <footer>Ushine customer, via WhatsApp</footer>
        </blockquote>
        <figure class="ush-phone ush-reveal" style="--d:.08s"><?php echo $img(1188, 'large', ['alt' => 'Customer message: in just a few days my face is already glowing']); ?></figure>
        <blockquote class="ush-quote ush-reveal" style="--d:.12s">
          <p>“In just a few days, my face is already glowing and the hyperpigmentation is disappearing drastically. I'm so happy and confident now.”</p>
        </blockquote>
        <blockquote class="ush-quote ush-quote--rose ush-reveal" style="--d:.16s">
          <p>“Your knuckle cream is bomb. I have seen tremendous change already.”</p>
        </blockquote>
        <figure class="ush-phone ush-phone--tilt ush-reveal" style="--d:.2s"><?php echo $img(1184, 'large', ['alt' => 'Customer message: this body scrub na bomb']); ?></figure>
        <blockquote class="ush-quote ush-reveal" style="--d:.24s">
          <p>“Omo, this body scrub na bomb. My skin has never felt like this before.”</p>
        </blockquote>
      </div>
    </div>
  </section>

  <!-- Consultation CTA -->
  <section class="ush-cta">
    <div class="ush-cta__panel ush-reveal">
      <div class="ush-cta__glow" aria-hidden="true"></div>
      <div class="ush-cta__copy">
        <h2>Not sure where to start?</h2>
        <p>Tell us your skin concern and we will build a routine that fits your skin and your budget.</p>
        <a class="ush-btn ush-btn--light" href="<?php echo esc_url($consult); ?>" target="_blank" rel="noopener">Get a free skin consultation<span class="ush-btn__chip" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span></a>
      </div>
      <div class="ush-cta__art" aria-hidden="true">
        <?php echo $img(1201, 'medium', ['alt' => '']); ?>
      </div>
    </div>
  </section>

</main>

<footer class="ush-footer">
  <div class="ush-footer__panel">
    <div class="ush-footer__top">
      <div class="ush-footer__brand">
        <?php if ($logo_id) { echo wp_get_attachment_image($logo_id, 'medium', false, ['class' => 'ush-logo__img', 'alt' => esc_attr(get_bloginfo('name'))]); } ?>
        <p class="ush-footer__tag"><?php echo esc_html(get_bloginfo('description')); ?></p>
      </div>
      <div class="ush-footer__cols">
        <div>
          <h3>Shop</h3>
          <a href="<?php echo esc_url($cat_url('face-products')); ?>">Face</a>
          <a href="<?php echo esc_url($cat_url('body-lotions')); ?>">Body</a>
          <a href="<?php echo esc_url($cat_url('skincare-sets')); ?>">Sets</a>
          <a href="<?php echo esc_url($cat_url('sunscreens')); ?>">Sunscreens</a>
          <a href="<?php echo esc_url($shop_url); ?>">All products</a>
        </div>
        <div>
          <h3>Help</h3>
          <a href="<?php echo esc_url(get_permalink(738) ?: home_url('/faq/')); ?>">FAQ</a>
          <a href="<?php echo esc_url(home_url('/contact-us/')); ?>">Contact</a>
          <a href="<?php echo esc_url(get_permalink(731) ?: home_url('/')); ?>">Terms and conditions</a>
          <a href="<?php echo esc_url(home_url('/about-us/')); ?>">About us</a>
        </div>
        <div>
          <h3>Account</h3>
          <a href="<?php echo esc_url($account_url); ?>">My account</a>
          <a href="<?php echo esc_url($cart_url); ?>">Cart</a>
          <a href="<?php echo esc_url(ushine_wa_link()); ?>" target="_blank" rel="noopener">WhatsApp +234 902 626 2772</a>
        </div>
      </div>
    </div>
    <div class="ush-footer__base">
      <p>&copy; <?php echo esc_html(gmdate('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?></p>
      <p class="ush-footer__big" aria-hidden="true">Ushine</p>
    </div>
  </div>
</footer>

<div class="ush-toast" role="status" aria-live="polite" data-toast></div>
<?php wp_footer(); ?>
</body>
</html>
