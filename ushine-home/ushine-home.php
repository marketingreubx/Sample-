<?php
/**
 * Ushine Home redesign: registers the "Ushine Home (Redesign)" page template,
 * loads its assets only on pages that use it, and keeps the header cart count live.
 * Template + assets live in wp-content/novamira-sandbox/ushine-home/.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('USHINE_HOME_DIR', __DIR__ . '/ushine-home/');
define('USHINE_HOME_TPL', 'ushine-home');
define('USHINE_WHATSAPP', '2349026262772');

add_filter('theme_page_templates', static function ($templates) {
    $templates[USHINE_HOME_TPL] = 'Ushine Home (Redesign)';
    return $templates;
});

function ushine_is_home_template(): bool
{
    if (!is_singular('page')) {
        return false;
    }
    return get_page_template_slug(get_queried_object_id()) === USHINE_HOME_TPL;
}

add_filter('template_include', static function ($template) {
    if (ushine_is_home_template() && file_exists(USHINE_HOME_DIR . 'template-v4.php')) {
        return USHINE_HOME_DIR . 'template-v4.php';
    }
    return $template;
}, 99);

add_action('wp_enqueue_scripts', static function () {
    if (!ushine_is_home_template()) {
        return;
    }
    $url = content_url('novamira-sandbox/ushine-home/');

    wp_enqueue_style(
        'ushine-fonts',
        'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,300;12..96,400;12..96,500&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap',
        [],
        null
    );
    wp_enqueue_style('ushine-home', $url . 'home-v4.css', ['ushine-fonts'], (string) filemtime(USHINE_HOME_DIR . 'home-v4.css'));
    wp_enqueue_script('ushine-home', $url . 'home-v4.js', [], (string) filemtime(USHINE_HOME_DIR . 'home-v4.js'), ['in_footer' => true, 'strategy' => 'defer']);

    if (function_exists('WC')) {
        wp_enqueue_script('wc-add-to-cart');
        wp_enqueue_script('wc-cart-fragments');
    }

}, 100);

// The template draws its own header and footer, so the theme's layout CSS only gets in the way.
// Other styles depend on Astra's handle, so a dequeue alone still prints it: empty the handle instead
// (no file, no inline CSS) so dependencies stay satisfied.
add_action('wp_print_styles', static function () {
    if (!ushine_is_home_template()) {
        return;
    }
    $styles = wp_styles();
    foreach (['astra-theme-css', 'astra-google-fonts', 'astra-menu-animation', 'astra-addon-css'] as $handle) {
        wp_dequeue_style($handle);
        if (isset($styles->registered[$handle])) {
            $styles->registered[$handle]->src = false;
            $styles->registered[$handle]->extra['after'] = [];
        }
    }
}, 1);

// Keep the header cart badge in sync after AJAX add-to-cart.
add_filter('woocommerce_add_to_cart_fragments', static function ($fragments) {
    $count = (function_exists('WC') && WC()->cart) ? WC()->cart->get_cart_contents_count() : 0;
    $fragments['span.ush-cart-count'] = '<span class="ush-cart-count" data-count="' . (int) $count . '">' . (int) $count . '</span>';
    return $fragments;
});

/** WhatsApp chat link with a pre-filled message. */
function ushine_wa_link(string $message = ''): string
{
    $url = 'https://wa.me/' . USHINE_WHATSAPP;
    return $message !== '' ? $url . '?text=' . rawurlencode($message) : $url;
}

/** Price markup: sale price in accent with the regular price struck, or "From" for variable products. */
function ushine_price_html(WC_Product $product): string
{
    if ($product->is_type('variable')) {
        $min = $product->get_variation_price('min', true);
        $max = $product->get_variation_price('max', true);
        if ($min === '') {
            return '';
        }
        $prefix = ($min !== $max) ? '<span class="ush-price__from">From</span> ' : '';
        return $prefix . '<span class="ush-price__now">' . wc_price($min) . '</span>';
    }
    $regular = $product->get_regular_price();
    $price   = $product->get_price();
    if ($price === '') {
        return '';
    }
    if ($product->is_on_sale() && $regular !== '' && (float) $regular > (float) $price) {
        return '<span class="ush-price__now is-sale">' . wc_price($price) . '</span> <del class="ush-price__was">' . wc_price($regular) . '</del>';
    }
    return '<span class="ush-price__now">' . wc_price($price) . '</span>';
}

/** One product card for the shop rail. */
function ushine_product_card(WC_Product $product): string
{
    $id    = $product->get_id();
    $link  = get_permalink($id);
    $name  = $product->get_name();
    $image = $product->get_image_id()
        ? wp_get_attachment_image($product->get_image_id(), 'woocommerce_thumbnail', false, ['class' => 'ush-card__img', 'loading' => 'lazy', 'alt' => esc_attr($name)])
        : '<span class="ush-card__img ush-card__img--empty" aria-hidden="true"></span>';

    $badge = '';
    if (!$product->is_in_stock()) {
        $badge = '<span class="ush-card__flag">Sold out</span>';
    } elseif ($product->is_on_sale() && !$product->is_type('variable')) {
        $regular = (float) $product->get_regular_price();
        $price   = (float) $product->get_price();
        if ($regular > 0 && $price < $regular) {
            $badge = '<span class="ush-card__flag ush-card__flag--sale">-' . (int) round((1 - $price / $regular) * 100) . '%</span>';
        }
    }

    $can_ajax = $product->is_type('simple') && $product->is_purchasable() && $product->is_in_stock();
    if ($can_ajax) {
        $button = sprintf(
            '<a href="%1$s" data-quantity="1" data-product_id="%2$d" data-product_sku="%3$s" class="ush-card__add add_to_cart_button ajax_add_to_cart" aria-label="%4$s" rel="nofollow"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></a>',
            esc_url($product->add_to_cart_url()),
            $id,
            esc_attr($product->get_sku()),
            esc_attr(sprintf('Add %s to cart', $name))
        );
    } else {
        $button = sprintf(
            '<a href="%1$s" class="ush-card__add ush-card__add--view" aria-label="%2$s"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>',
            esc_url($link),
            esc_attr(sprintf('View %s', $name))
        );
    }

    return '<article class="ush-card">'
        . '<a class="ush-card__media" href="' . esc_url($link) . '" tabindex="-1" aria-hidden="true">' . $image . $badge . '</a>'
        . '<div class="ush-card__body">'
        . '<h3 class="ush-card__name"><a href="' . esc_url($link) . '">' . esc_html($name) . '</a></h3>'
        . '<div class="ush-card__row"><p class="ush-price">' . ushine_price_html($product) . '</p>' . $button . '</div>'
        . '</div></article>';
}
