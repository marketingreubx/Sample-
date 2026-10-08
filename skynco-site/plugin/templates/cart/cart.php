<?php
/**
 * Skyn&Co. cart: item cards on the left, order summary on the right.
 * Keeps WooCommerce's form names and hooks so updates, coupons and removals work as usual.
 *
 * @package SkyncoStudio
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' );
?>
<div class="skc">
	<div class="skc-grid">
		<div class="skc-main">
			<form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
				<?php do_action( 'woocommerce_before_cart_table' ); ?>
				<h1 class="skc-h">Your bag <span><?php echo (int) WC()->cart->get_cart_contents_count(); ?> item<?php echo 1 === WC()->cart->get_cart_contents_count() ? '' : 's'; ?></span></h1>
				<ul class="skc-items shop_table cart">
					<?php
					do_action( 'woocommerce_before_cart_contents' );
					foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
						$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
						$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
						if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 || ! apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
							continue;
						}
						$link = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
						$name = $_product->get_name();
						$qty  = $_product->is_sold_individually()
							? sprintf( '1 <input type="hidden" name="cart[%s][qty]" value="1" />', $cart_item_key )
							: woocommerce_quantity_input(
								[
									'input_name'   => "cart[{$cart_item_key}][qty]",
									'input_value'  => $cart_item['quantity'],
									'max_value'    => $_product->get_max_purchase_quantity(),
									'min_value'    => '0',
									'product_name' => $name,
								],
								$_product,
								false
							);
						?>
						<li class="skc-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
							<a class="skc-img" href="<?php echo esc_url( $link ); ?>"><?php echo apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'woocommerce_thumbnail' ), $cart_item, $cart_item_key ); // phpcs:ignore ?></a>
							<div class="skc-info">
								<div class="skc-top">
									<h3><?php echo $link ? '<a href="' . esc_url( $link ) . '">' . wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $name, $cart_item, $cart_item_key ) ) . '</a>' : wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $name, $cart_item, $cart_item_key ) ); ?></h3>
									<span class="skc-price"><?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore ?></span>
								</div>
								<?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore ?>
								<?php if ( ! empty( $cart_item['skynco_bump'] ) ) : ?>
									<p class="skc-tag">Checkout offer · 25% off</p>
								<?php elseif ( $_product->get_short_description() ) : ?>
									<p class="skc-desc"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $_product->get_short_description() ), 14 ) ); ?></p>
								<?php endif; ?>
								<div class="skc-bottom">
									<?php $steps = ! $_product->is_sold_individually() && empty( $cart_item['skynco_bump'] ); ?>
									<div class="skc-qty<?php echo $steps ? ' has-steps' : ''; ?>"><?php echo $steps ? '<button type="button" class="skc-step" data-d="-1" aria-label="Decrease quantity">−</button>' : ''; ?><?php echo apply_filters( 'woocommerce_cart_item_quantity', $qty, $cart_item_key, $cart_item ); // phpcs:ignore ?><?php echo $steps ? '<button type="button" class="skc-step" data-d="1" aria-label="Increase quantity">+</button>' : ''; ?></div>
									<?php
									echo apply_filters( // phpcs:ignore
										'woocommerce_cart_item_remove_link',
										sprintf(
											'<a href="%s" class="skc-remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">Remove</a>',
											esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
											esc_attr( sprintf( 'Remove %s from your bag', wp_strip_all_tags( $name ) ) ),
											esc_attr( $product_id ),
											esc_attr( $_product->get_sku() )
										),
										$cart_item_key
									);
									?>
								</div>
							</div>
						</li>
						<?php
					}
					do_action( 'woocommerce_cart_contents' );
					?>
				</ul>
				<div class="skc-actions">
					<?php if ( wc_coupons_enabled() ) : ?>
						<div class="skc-coupon">
							<label for="coupon_code" class="screen-reader-text">Discount code</label>
							<input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="Discount code, e.g. GLOW10" />
							<button type="submit" class="button" name="apply_coupon" value="Apply">Apply</button>
							<?php do_action( 'woocommerce_cart_coupon' ); ?>
						</div>
					<?php endif; ?>
					<button type="submit" class="button skc-update" name="update_cart" value="Update cart">Update bag</button>
					<?php do_action( 'woocommerce_cart_actions' ); ?>
					<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
				</div>
				<?php do_action( 'woocommerce_after_cart_contents' ); ?>
				<?php do_action( 'woocommerce_after_cart_table' ); ?>
			</form>
			<div class="skc-cross"><?php woocommerce_cross_sell_display( 3, 3 ); ?></div>
		</div>
		<aside class="skc-side">
			<?php do_action( 'woocommerce_before_cart_collaterals' ); ?>
			<div class="cart-collaterals"><?php do_action( 'woocommerce_cart_collaterals' ); ?></div>
			<?php skynco_checkout_trust(); ?>
		</aside>
	</div>
</div>
<?php do_action( 'woocommerce_after_cart' ); ?>
