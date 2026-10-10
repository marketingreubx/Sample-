<?php
/**
 * Sales boosters for the shop.
 *
 * 1. Routine savings: 2 products 10% off, 3 or more 15% off (singles only; kits are
 *    already 20% off). Never stacks with a code: the client gets whichever is bigger.
 * 2. Rewards bar: free shipping at $75 and a free LED add-on at the next facial at $120.
 * 3. Complete the kit: two items from a kit in the bag → one-tap upgrade to the kit.
 * 4. Frequently bought together on product pages, added to the bag in one click.
 * 5. Thank-you page: add one product to a pay-later order at 20% off, shipped together.
 * 6. Refill reminder email 45 days after an order, with a one-tap reorder (REFILL10).
 *
 * @package SkyncoStudio
 */

defined( 'ABSPATH' ) || exit;

const SKYNCO_GIFT_MIN   = 120;
const SKYNCO_PP_OFF     = 0.20;
const SKYNCO_REFILL_DAYS = 45;

function skynco_routine_tiers() {
	return [ 3 => 0.15, 2 => 0.10 ];
}

/** Single products that count towards routine savings (not kits, gift cards, treatments or the free gift). */
function skynco_routine_eligible( $product ) {
	if ( ! $product || (float) $product->get_regular_price() <= 0 ) {
		return false;
	}
	$id = $product->get_parent_id() ?: $product->get_id();
	return ! has_term( [ 'kits', 'gift-cards', 'treatments' ], 'product_cat', $id ) && (int) get_option( 'skynco_gift_product' ) !== (int) $id;
}

/** [ eligible units, eligible subtotal, rate ] for the current cart. */
function skynco_routine_state() {
	$units = 0;
	$sum   = 0.0;
	if ( WC()->cart ) {
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( empty( $item['skynco_bump'] ) && empty( $item['skynco_pp'] ) && skynco_routine_eligible( $item['data'] ) ) {
				$units += (int) $item['quantity'];
				$sum   += (float) $item['line_subtotal'];
			}
		}
	}
	$rate = 0;
	foreach ( skynco_routine_tiers() as $n => $r ) {
		if ( $units >= $n ) {
			$rate = $r;
			break;
		}
	}
	return [ $units, $sum, $rate ];
}

/* 1. Routine savings as a cart discount line, topped up over any code (best one wins). */
add_action(
	'woocommerce_cart_calculate_fees',
	function ( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		[ $units, $sum, $rate ] = skynco_routine_state();
		if ( ! $rate ) {
			return;
		}
		$save  = round( $sum * $rate, 2 );
		$codes = (float) $cart->get_discount_total();
		$extra = round( $save - $codes, 2 );
		if ( $extra > 0 ) {
			$cart->add_fee( sprintf( 'Routine savings (%d%% off %d products)', $rate * 100, $units ), -$extra, false );
		}
	}
);

/** Total that counts towards the rewards (after codes and routine savings). */
function skynco_rewards_total() {
	$c     = WC()->cart;
	$total = (float) $c->get_displayed_subtotal() - (float) $c->get_discount_total();
	foreach ( $c->get_fees() as $fee ) {
		$total += (float) $fee->amount;
	}
	return max( 0, $total );
}

/* 2. Rewards bar (replaces the free-shipping bar). */
remove_action( 'woocommerce_before_cart', 'skynco_free_ship_bar', 5 );
remove_action( 'woocommerce_before_checkout_form', 'skynco_free_ship_bar', 5 );
remove_action( 'woocommerce_widget_shopping_cart_before_buttons', 'skynco_free_ship_bar' );
add_action( 'woocommerce_before_cart', 'skynco_rewards_bar', 5 );
add_action( 'woocommerce_before_checkout_form', 'skynco_rewards_bar', 5 );
add_action( 'woocommerce_widget_shopping_cart_before_buttons', 'skynco_rewards_bar' );
function skynco_rewards_bar() {
	if ( ! WC()->cart || WC()->cart->is_empty() ) {
		return;
	}
	$ship  = skynco_free_ship_min();
	$gift  = SKYNCO_GIFT_MIN;
	$total = skynco_rewards_total();
	$pct   = min( 100, round( $total / $gift * 100 ) );
	if ( $total < $ship ) {
		$msg = 'You’re <b>' . wc_price( $ship - $total ) . '</b> away from <b>free shipping</b>.';
	} elseif ( $total < $gift ) {
		$msg = 'Free shipping unlocked. Add <b>' . wc_price( $gift - $total ) . '</b> more for a <b>free LED add-on</b> at your next facial.';
	} else {
		$msg = 'You’ve unlocked <b>free shipping</b> and a <b>free LED add-on</b> at your next facial.';
	}
	[ $units, , $rate ] = skynco_routine_state();
	$tip = '';
	if ( 1 === $units ) {
		$tip = 'Add 1 more product to save <b>10%</b> on your routine.';
	} elseif ( 2 === $units ) {
		$tip = 'Add 1 more product to save <b>15%</b> on your routine.';
	} elseif ( $rate ) {
		$tip = 'You’re saving <b>' . (int) ( $rate * 100 ) . '%</b> on your routine.';
	}
	echo '<div class="sk-rw"><p>' . wp_kses_post( $msg ) . '</p><div class="sk-rw__bar"><span style="width:' . (int) $pct . '%"></span><i style="left:' . (int) round( $ship / $gift * 100 ) . '%" title="Free shipping"></i></div>'
		. '<div class="sk-rw__marks"><span style="left:' . (int) round( $ship / $gift * 100 ) . '%">Free shipping</span><span style="left:100%">Free LED add-on</span></div>'
		. ( $tip ? '<p class="sk-rw__tip">' . wp_kses_post( $tip ) . '</p>' : '' ) . '</div>';
}

/* Free gift product (virtual, hidden, $0), created once. */
function skynco_gift_product_id() {
	$id = (int) get_option( 'skynco_gift_product' );
	if ( $id && 'product' === get_post_type( $id ) ) {
		return $id;
	}
	if ( ! class_exists( 'WC_Product_Simple' ) ) {
		return 0;
	}
	$p = new WC_Product_Simple();
	$p->set_name( 'Free gift: LED Light Therapy add-on at your next facial' );
	$p->set_status( 'publish' );
	$p->set_catalog_visibility( 'hidden' );
	$p->set_regular_price( '0' );
	$p->set_price( '0' );
	$p->set_virtual( true );
	$p->set_sold_individually( true );
	$p->set_reviews_allowed( false );
	$p->set_short_description( 'A $25 LED light therapy add-on, free with orders over $120. Mention it when you book.' );
	$id  = $p->save();
	update_option( 'skynco_gift_product', $id, false );
	return $id;
}
add_action( 'admin_init', 'skynco_gift_product_id' );

/* Add or remove the free gift as the total crosses the threshold. */
add_action(
	'woocommerce_after_calculate_totals',
	function ( $cart ) {
		static $busy = false;
		if ( $busy || ( is_admin() && ! wp_doing_ajax() ) ) {
			return;
		}
		$gid = (int) get_option( 'skynco_gift_product' );
		if ( ! $gid ) {
			return;
		}
		$key = null;
		foreach ( $cart->get_cart() as $k => $item ) {
			if ( (int) $item['product_id'] === $gid ) {
				$key = $k;
			}
		}
		$qualifies = skynco_rewards_total() >= SKYNCO_GIFT_MIN;
		$busy      = true;
		if ( $qualifies && ! $key && ! WC()->session->get( 'skynco_gift_declined' ) ) {
			$cart->add_to_cart( $gid, 1, 0, [], [ 'skynco_gift' => 1 ] );
		} elseif ( ! $qualifies && $key ) {
			$cart->remove_cart_item( $key );
		}
		$busy = false;
	}
);
add_action(
	'woocommerce_remove_cart_item',
	function ( $key, $cart ) {
		if ( ! empty( $cart->cart_contents[ $key ]['skynco_gift'] ) && skynco_rewards_total() >= SKYNCO_GIFT_MIN ) {
			WC()->session->set( 'skynco_gift_declined', 1 );
		}
	},
	10,
	2
);
add_filter(
	'woocommerce_cart_item_quantity',
	function ( $qty, $key, $item ) {
		return empty( $item['skynco_gift'] ) ? $qty : '1';
	},
	20,
	3
);
add_filter(
	'woocommerce_cart_item_price',
	function ( $price, $item ) {
		return empty( $item['skynco_gift'] ) ? $price : '<span class="sk-free">Free</span>';
	},
	10,
	2
);
add_filter(
	'woocommerce_cart_item_subtotal',
	function ( $sub, $item ) {
		return empty( $item['skynco_gift'] ) ? $sub : '<span class="sk-free">Free</span>';
	},
	10,
	2
);

/* 3. Complete the kit. */
function skynco_kit_offers() {
	if ( ! WC()->cart ) {
		return [];
	}
	$in = [];
	foreach ( WC()->cart->get_cart() as $item ) {
		if ( empty( $item['skynco_bump'] ) && empty( $item['skynco_gift'] ) ) {
			$in[ (int) $item['product_id'] ] = true;
		}
	}
	$offers = [];
	foreach ( [ 'glow-kit', 'clear-skin-kit', 'recovery-kit' ] as $kit_slug ) {
		$kid = skynco_shop_id( $kit_slug );
		$kit = $kid ? wc_get_product( $kid ) : null;
		if ( ! $kit || isset( $in[ $kid ] ) ) {
			continue;
		}
		$parts = (array) get_post_meta( $kid, '_skynco_kit', true );
		$have  = [];
		$miss  = [];
		$worth = 0.0;
		foreach ( $parts as $s ) {
			$pid = skynco_shop_id( $s );
			$pp  = wc_get_product( $pid );
			if ( ! $pp ) {
				continue 2;
			}
			$worth += (float) $pp->get_regular_price();
			if ( isset( $in[ $pid ] ) ) {
				$have[] = $pid;
			} else {
				$miss[] = $pp;
			}
		}
		if ( count( $have ) >= 2 && $miss ) {
			$offers[] = [ $kit, $have, $miss, $worth - (float) $kit->get_price() ];
		} elseif ( ! $miss && count( $have ) === count( $parts ) ) {
			$offers[] = [ $kit, $have, [], round( $worth * ( 1 - 0.15 ) - (float) $kit->get_price(), 2 ) ];
		}
	}
	usort( $offers, fn( $a, $b ) => count( $b[1] ) <=> count( $a[1] ) );
	return array_slice( $offers, 0, 1 );
}

add_action(
	'woocommerce_before_cart_table',
	function () {
		foreach ( skynco_kit_offers() as [ $kit, $have, $miss, $save ] ) {
			$url = wp_nonce_url( add_query_arg( 'skynco_kit_upgrade', $kit->get_id(), wc_get_cart_url() ), 'skynco_kit_upgrade' );
			$img = wp_get_attachment_image_url( $kit->get_image_id(), 'woocommerce_thumbnail' );
			if ( $save <= 0 ) {
				continue;
			}
			$title = $miss
				? 'Add ' . esc_html( implode( ' and ', array_map( fn( $p ) => $p->get_name(), $miss ) ) ) . ' and get <b>' . esc_html( $kit->get_name() ) . '</b> for ' . wp_kses_post( wc_price( $kit->get_price() ) ) . '.'
				: 'These three make <b>' . esc_html( $kit->get_name() ) . '</b>. Switch and pay ' . wp_kses_post( wc_price( $kit->get_price() ) ) . ' for the set.';
			$save_txt = $miss ? 'You save ' . wc_price( $save ) . ' on the full routine.' : 'That’s ' . wc_price( $save ) . ' less than buying them separately.';
			echo '<div class="sk-kitup"><img src="' . esc_url( $img ) . '" alt=""><div><p class="sk-kitup__eyebrow">' . ( $miss ? 'You’re one step from a kit' : 'Better price available' ) . '</p><p class="sk-kitup__title">' . $title . '</p><p class="sk-kitup__save">' . wp_kses_post( $save_txt ) . '</p></div><a class="sk-kitup__btn" href="' . esc_url( $url ) . '">' . ( $miss ? 'Upgrade to the kit' : 'Switch to the kit' ) . '</a></div>'; // phpcs:ignore
		}
	}
);

add_action(
	'wp_loaded',
	function () {
		if ( empty( $_GET['skynco_kit_upgrade'] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'skynco_kit_upgrade' ) ) {
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}
		$kid   = absint( $_GET['skynco_kit_upgrade'] );
		$parts = array_map( 'skynco_shop_id', (array) get_post_meta( $kid, '_skynco_kit', true ) );
		if ( $parts ) {
			foreach ( WC()->cart->get_cart() as $key => $item ) {
				if ( in_array( (int) $item['product_id'], $parts, true ) && empty( $item['skynco_bump'] ) ) {
					WC()->cart->set_quantity( $key, (int) $item['quantity'] - 1 );
				}
			}
			WC()->cart->add_to_cart( $kid );
			wc_add_notice( 'Upgraded to ' . get_the_title( $kid ) . '. You’re getting the full routine at our best price.' );
		}
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}
);

/* 4. Frequently bought together (product page). */
function skynco_fbt_items( $product ) {
	$items = [ $product ];
	$seen  = [ $product->get_id() => true ];
	$cats  = [ implode( ',', wc_get_product_cat_ids( $product->get_id() ) ) => true ];
	$pool  = array_merge( $product->get_cross_sell_ids(), array_map( 'skynco_shop_id', [ 'gentle-cleansing-gel', 'vitamin-c-brightening-serum', 'daily-mineral-spf-40', 'barrier-repair-moisturizer', 'enzyme-exfoliating-mask' ] ) );
	foreach ( $pool as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p || isset( $seen[ $id ] ) || ! skynco_routine_eligible( $p ) || ! $p->is_purchasable() ) {
			continue;
		}
		$cat = implode( ',', wc_get_product_cat_ids( $id ) );
		if ( isset( $cats[ $cat ] ) ) {
			continue; // One product per routine step.
		}
		$items[]      = $p;
		$seen[ $id ]  = true;
		$cats[ $cat ] = true;
		if ( count( $items ) >= 3 ) {
			break;
		}
	}
	return $items;
}

add_action(
	'woocommerce_after_single_product_summary',
	function () {
		global $product;
		if ( ! $product || ! skynco_routine_eligible( $product ) ) {
			return;
		}
		$items = skynco_fbt_items( $product );
		if ( count( $items ) < 3 ) {
			return;
		}
		$sum  = 0;
		$html = '';
		foreach ( $items as $i => $p ) {
			$price = (float) $p->get_price();
			$sum  += $price;
			$html .= ( $i ? '<span class="sk-fbt__plus">+</span>' : '' ) . '<label class="sk-fbt__item"><input type="checkbox" name="skynco_fbt[]" value="' . (int) $p->get_id() . '" data-price="' . esc_attr( $price ) . '" checked' . ( 0 === $i ? ' disabled' : '' ) . '><img src="' . esc_url( wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' ) ) . '" alt=""><span class="sk-fbt__name">' . ( 0 === $i ? '<em>This item</em>' : '' ) . esc_html( $p->get_name() ) . '</span><span class="sk-fbt__price">' . wp_kses_post( wc_price( $price ) ) . '</span></label>';
		}
		$cur   = get_woocommerce_currency_symbol();
		$ids   = array_map( fn( $p ) => $p->get_id(), $items );
		$match = null;
		foreach ( [ 'glow-kit', 'clear-skin-kit', 'recovery-kit' ] as $ks ) {
			$parts = array_map( 'skynco_shop_id', (array) get_post_meta( skynco_shop_id( $ks ), '_skynco_kit', true ) );
			if ( $parts && ! array_diff( $parts, $ids ) && ! array_diff( $ids, $parts ) ) {
				$match = wc_get_product( skynco_shop_id( $ks ) );
			}
		}
		$kit_line = $match ? '<p class="sk-fbt__kit">Best price: get all three as <a href="' . esc_url( get_permalink( $match->get_id() ) ) . '">' . esc_html( $match->get_name() ) . '</a> for <b>' . wp_kses_post( wc_price( $match->get_price() ) ) . '</b> (20% off). <a class="sk-fbt__kitbtn" href="' . esc_url( add_query_arg( 'add-to-cart', $match->get_id(), wc_get_cart_url() ) ) . '" rel="nofollow">Add the kit</a></p>' : '';
		echo '<section class="sk-fbt"><h2>Complete the routine</h2><p class="sk-fbt__sub">Clients who buy this usually pair it with these. Buy 2 and save 10%, buy 3 and save 15%.</p>'
			. '<form method="post" action="' . esc_url( wc_get_cart_url() ) . '"><input type="hidden" name="skynco_fbt[]" value="' . (int) $product->get_id() . '">' . wp_nonce_field( 'skynco_fbt', '_skfbt', false, false )
			. '<div class="sk-fbt__row">' . $html . '</div><div class="sk-fbt__foot"><div><span class="sk-fbt__was"><s data-was>' . esc_html( $cur . number_format( $sum, 2 ) ) . '</s></span> <b class="sk-fbt__now" data-now>' . esc_html( $cur . number_format( $sum * 0.85, 2 ) ) . '</b> <span class="sk-fbt__off" data-off>15% off</span></div><button type="submit" class="sk-fbt__btn" data-btn>Add all 3 to bag</button></div></form>' . $kit_line . '</section>'; // phpcs:ignore
		echo '<script>(function(){var f=document.querySelector(".sk-fbt form");if(!f)return;var c="' . esc_js( $cur ) . '";function u(){var b=f.querySelectorAll("input[type=checkbox]"),n=0,s=0;b.forEach(function(x){if(x.checked){n++;s+=+x.dataset.price;}});var r=n>=3?.15:n>=2?.10:0;f.querySelector("[data-was]").style.display=r?"":"none";f.querySelector("[data-was]").textContent=c+s.toFixed(2);f.querySelector("[data-now]").textContent=c+(s*(1-r)).toFixed(2);f.querySelector("[data-off]").textContent=r?Math.round(r*100)+"% off":"";f.querySelector("[data-btn]").textContent=n>1?"Add all "+n+" to bag":"Add to bag";}f.addEventListener("change",u);})();</script>';
	},
	12
);

add_action(
	'wp_loaded',
	function () {
		if ( empty( $_POST['skynco_fbt'] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['_skfbt'] ?? '' ), 'skynco_fbt' ) ) {
			return;
		}
		$n = 0;
		foreach ( array_unique( array_map( 'absint', (array) wp_unslash( $_POST['skynco_fbt'] ) ) ) as $id ) {
			$p = wc_get_product( $id );
			if ( $p && skynco_routine_eligible( $p ) && WC()->cart->add_to_cart( $id ) ) {
				$n++;
			}
		}
		if ( $n ) {
			wc_add_notice( $n . ' products added to your bag. Routine savings are applied automatically.' );
		}
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}
);

/* 5. Thank-you page: one-click add to a pay-later order. */
function skynco_pp_offer( WC_Order $order ) {
	if ( $order->get_meta( '_skynco_pp_done' ) || 'cod' !== $order->get_payment_method() || ! $order->has_status( [ 'pending', 'on-hold', 'processing' ] ) ) {
		return null;
	}
	if ( time() - $order->get_date_created()->getTimestamp() > HOUR_IN_SECONDS ) {
		return null;
	}
	$have = array_map( fn( $i ) => (int) $i->get_product_id(), $order->get_items() );
	foreach ( [ 'daily-mineral-spf-40', 'enzyme-exfoliating-mask', 'post-treatment-recovery-balm', 'barrier-repair-moisturizer' ] as $s ) {
		$pid = skynco_shop_id( $s );
		if ( $pid && ! in_array( $pid, $have, true ) ) {
			$p = wc_get_product( $pid );
			if ( $p && $p->is_purchasable() ) {
				return $p;
			}
		}
	}
	return null;
}

add_action(
	'woocommerce_thankyou',
	function ( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( isset( $_GET['sk_pp'] ) && $order->get_meta( '_skynco_pp_done' ) ) {
			echo '<div class="sk-pp sk-pp--done"><b>Added to your order.</b> It ships with the rest of your order, no extra shipping.</div>';
			return;
		}
		$p = skynco_pp_offer( $order );
		if ( ! $p ) {
			return;
		}
		$price = (float) $p->get_regular_price();
		$url   = wp_nonce_url( add_query_arg( [ 'action' => 'skynco_pp_add', 'order' => $order->get_id(), 'key' => $order->get_order_key() ], admin_url( 'admin-post.php' ) ), 'skynco_pp_' . $order->get_id() );
		echo '<div class="sk-pp"><img src="' . esc_url( wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' ) ) . '" alt=""><div><p class="sk-pp__eyebrow">One-time offer · this page only</p><p class="sk-pp__title">Add ' . esc_html( $p->get_name() ) . ' to this order for ' . wp_kses_post( wc_price( $price * ( 1 - SKYNCO_PP_OFF ) ) ) . ' <s>' . wp_kses_post( wc_price( $price ) ) . '</s></p><p class="sk-pp__text">' . esc_html( wp_strip_all_tags( $p->get_short_description() ) ) . ' Ships together, no extra shipping. Pay with the rest of your order.</p></div><a class="sk-pp__btn" href="' . esc_url( $url ) . '">Add to my order</a></div>';
	},
	4
);

add_action( 'admin_post_skynco_pp_add', 'skynco_pp_add' );
add_action( 'admin_post_nopriv_skynco_pp_add', 'skynco_pp_add' );
function skynco_pp_add() {
	$order = wc_get_order( absint( $_GET['order'] ?? 0 ) );
	if ( ! $order || ! hash_equals( $order->get_order_key(), sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) ) ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ?? '' ), 'skynco_pp_' . $order->get_id() ) ) {
		wp_safe_redirect( home_url( '/shop/' ) );
		exit;
	}
	$p = skynco_pp_offer( $order );
	if ( $p ) {
		$price = round( (float) $p->get_regular_price() * ( 1 - SKYNCO_PP_OFF ), 2 );
		$order->add_product( $p, 1, [ 'subtotal' => $price, 'total' => $price ] );
		$order->calculate_totals();
		$order->update_meta_data( '_skynco_pp_done', time() );
		$order->add_order_note( 'Client added ' . $p->get_name() . ' from the thank-you page offer (20% off).' );
		$order->save();
		if ( function_exists( 'skynco_notice_add' ) ) {
			skynco_notice_add( 'studio', '', 'order-upsell', $order->get_id(), 'Order #' . $order->get_order_number() . ' upgraded', $order->get_billing_first_name() . ' added ' . $p->get_name() . ' after checkout.', admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) );
		}
	}
	wp_safe_redirect( add_query_arg( 'sk_pp', 1, $order->get_checkout_order_received_url() ) );
	exit;
}

/* 6. Refill reminder 45 days after an order is completed. */
add_action(
	'woocommerce_order_status_completed',
	function ( $order_id ) {
		if ( ! wp_next_scheduled( 'skynco_refill_reminder', [ (int) $order_id ] ) ) {
			wp_schedule_single_event( time() + SKYNCO_REFILL_DAYS * DAY_IN_SECONDS, 'skynco_refill_reminder', [ (int) $order_id ] );
		}
	}
);
add_action( 'skynco_refill_reminder', 'skynco_send_refill_reminder' );
function skynco_send_refill_reminder( $order_id ) {
	$o = wc_get_order( $order_id );
	if ( ! $o || $o->get_meta( '_skynco_refill_sent' ) ) {
		return false;
	}
	$rows = '';
	foreach ( $o->get_items() as $it ) {
		$p = $it->get_product();
		if ( ! $p || has_term( [ 'gift-cards', 'treatments' ], 'product_cat', $p->get_id() ) || (int) get_option( 'skynco_gift_product' ) === $p->get_id() ) {
			continue;
		}
		$rows .= '<tr><td style="padding:8px 0;width:64px"><img src="' . esc_url( wp_get_attachment_image_url( $p->get_image_id(), 'thumbnail' ) ) . '" width="56" height="56" style="border-radius:10px;display:block" alt=""></td><td style="padding:8px 12px;font-family:Arial,sans-serif;font-size:15px;color:#2A1A24">' . esc_html( $p->get_name() ) . '</td></tr>';
	}
	if ( ! $rows ) {
		return false;
	}
	$url  = add_query_arg( [ 'skynco_reorder' => $o->get_id(), 'k' => $o->get_order_key() ], home_url( '/' ) );
	$body = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#2A1A24"><h2 style="font-family:Georgia,serif;color:#3B1530;font-weight:500">Running low, ' . esc_html( $o->get_billing_first_name() ) . '?</h2><p>Most clients finish these in about six weeks. Keep your routine going without a gap: reorder in one tap and get <b>10% off</b>.</p><table style="width:100%;border-collapse:collapse;margin:14px 0">' . $rows . '</table><p style="margin:24px 0"><a href="' . esc_url( $url ) . '" style="background:#D1127E;color:#fff;text-decoration:none;padding:14px 26px;border-radius:999px;font-weight:bold">Reorder with 10% off</a></p><p style="font-size:13px;color:#6E5A66">Your code REFILL10 is applied automatically. Free shipping over $75, or free pickup at the studio.</p></div>';
	$ok   = wp_mail( $o->get_billing_email(), 'Time to restock your routine? 10% off inside', $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
	$o->update_meta_data( '_skynco_refill_sent', time() );
	$o->save();
	return $ok;
}

add_action(
	'wp_loaded',
	function () {
		if ( empty( $_GET['skynco_reorder'] ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		$o = wc_get_order( absint( $_GET['skynco_reorder'] ) );
		if ( ! $o || ! hash_equals( $o->get_order_key(), sanitize_text_field( wp_unslash( $_GET['k'] ?? '' ) ) ) ) {
			wp_safe_redirect( home_url( '/shop/' ) );
			exit;
		}
		foreach ( $o->get_items() as $it ) {
			$p = $it->get_product();
			if ( $p && $p->is_purchasable() && ! has_term( [ 'gift-cards', 'treatments' ], 'product_cat', $p->get_id() ) && (int) get_option( 'skynco_gift_product' ) !== $p->get_id() ) {
				WC()->cart->add_to_cart( $p->get_id(), max( 1, (int) $it->get_quantity() ) );
			}
		}
		if ( ! WC()->cart->has_discount( 'refill10' ) ) {
			WC()->cart->apply_coupon( 'REFILL10' );
		}
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}
);

/* Product page: show the routine offer under the add-to-cart button. */
add_action(
	'woocommerce_after_add_to_cart_form',
	function () {
		global $product;
		if ( $product && skynco_routine_eligible( $product ) ) {
			echo '<p class="sk-mix"><b>Mix &amp; match:</b> buy 2 products and save 10%, 3 or more and save 15%. Applied automatically.</p>';
		}
	},
	5
);

add_action(
	'wp_head',
	function () {
		?>
<style id="skynco-sales">
.sk-rw{background:#FBEFF0;border-radius:18px;padding:16px 20px 14px;margin:0 0 20px;font-family:Manrope,sans-serif}
.sk-rw p{margin:0;font:500 15px/1.45 Manrope,sans-serif;color:#2A1A24}
.sk-rw__bar{position:relative;height:8px;margin:12px 0 0;border-radius:99px;background:#F3D9E0}
.sk-rw__bar span{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,#D1127E,#FF8CC8);transition:width .6s ease}
.sk-rw__bar i{position:absolute;top:-3px;width:3px;height:14px;border-radius:2px;background:#3B1530;opacity:.35}
.sk-rw__marks{position:relative;height:18px;margin-top:6px;font:700 11px Manrope,sans-serif;color:#6E5A66}
.sk-rw__marks span{position:absolute;top:0;transform:translateX(-50%);white-space:nowrap}.sk-rw__marks span:last-child{transform:translateX(-100%)}
.sk-rw__tip{margin-top:10px!important;padding-top:10px;border-top:1px dashed #E6C9D2;font-size:14px!important;color:#3B1530!important}
.widget_shopping_cart .sk-rw{padding:12px 14px}.widget_shopping_cart .sk-rw__marks{display:none}
.sk-free{font-weight:700;color:#24704A}
.sk-kitup{display:flex;align-items:center;gap:16px;margin:0 0 18px;padding:16px;border-radius:20px;background:#fff;border:1.5px solid #D1127E;box-shadow:0 14px 30px -20px rgba(209,18,126,.6);font-family:Manrope,sans-serif}
.sk-kitup img{width:84px;height:84px;border-radius:14px;object-fit:cover;flex:none}
.sk-kitup p{margin:0}.sk-kitup__eyebrow{font:700 11px Manrope,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#D1127E}
.sk-kitup__title{margin:4px 0!important;font:500 15px/1.45 Manrope,sans-serif;color:#2A1A24}.sk-kitup__save{font:700 13.5px Manrope,sans-serif;color:#24704A}
.sk-kitup__btn{margin-left:auto;flex:none;padding:12px 20px;border-radius:999px;background:#D1127E;color:#fff!important;font:700 14px Manrope,sans-serif;text-decoration:none!important}
.sk-fbt{clear:both;max-width:1200px;margin:48px auto 0;padding:28px;border-radius:26px;background:#FBF6F4;border:1px solid #EFE2E6;font-family:Manrope,sans-serif}
.sk-fbt h2{margin:0!important;font:400 30px/1.2 Fraunces,serif!important;color:#3B1530!important}
.sk-fbt__sub{margin:6px 0 20px!important;color:#6E5A66;font-size:15px}
.sk-fbt__row{display:flex;align-items:stretch;gap:12px}
.sk-fbt__item{position:relative;flex:1;min-width:0;display:flex;flex-direction:column;gap:6px;padding:14px;border-radius:18px;background:#fff;border:1px solid #EADDE0;cursor:pointer}
.sk-fbt__item input{position:absolute;top:12px;left:12px;width:18px;height:18px;accent-color:#D1127E}
.sk-fbt__item img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:12px;background:#F6ECEF}
.sk-fbt__name{font:600 14px/1.35 Manrope,sans-serif;color:#2A1A24}.sk-fbt__name em{display:block;font-style:normal;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#D1127E}
.sk-fbt__price{font:700 14px Manrope,sans-serif;color:#3B1530;margin-top:auto}
.sk-fbt__plus{align-self:center;font:300 28px Manrope,sans-serif;color:#C9A9B5}
.sk-fbt__foot{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:18px;flex-wrap:wrap}
.sk-fbt__was{color:#9A8791;font-size:15px}.sk-fbt__now{font:700 24px Manrope,sans-serif;color:#2A1A24}.sk-fbt__off{margin-left:6px;padding:4px 10px;border-radius:99px;background:#E9F5EE;color:#24704A;font:700 12.5px Manrope,sans-serif}
.sk-fbt__btn{padding:14px 26px;border-radius:999px;border:0;background:#D1127E;color:#fff;font:700 15px Manrope,sans-serif;cursor:pointer}
.sk-fbt__kit{margin:16px 0 0!important;padding:14px 16px;border-radius:16px;background:#fff;border:1.5px solid #D1127E;font:500 14.5px/1.5 Manrope,sans-serif;color:#2A1A24;display:flex;align-items:center;gap:12px;flex-wrap:wrap}.sk-fbt__kit a{color:#D1127E;font-weight:700}.sk-fbt__kit .sk-fbt__kitbtn{margin-left:auto;padding:10px 18px;border-radius:999px;background:#3B1530;color:#fff!important;text-decoration:none}
.sk-mix{margin:12px 0 0;padding:10px 14px;border-radius:12px;background:#E9F5EE;color:#24704A;font:500 13.5px/1.45 Manrope,sans-serif}
.sk-pp{display:flex;align-items:center;gap:18px;margin:0 0 24px;padding:20px;border-radius:22px;background:#fff;border:2px dashed #D1127E;font-family:Manrope,sans-serif}
.sk-pp img{width:96px;height:96px;border-radius:16px;object-fit:cover;flex:none}
.sk-pp p{margin:0}.sk-pp__eyebrow{font:700 11px Manrope,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#D1127E}
.sk-pp__title{margin:4px 0!important;font:500 18px/1.35 Fraunces,serif;color:#3B1530}.sk-pp__title s{color:#9A8791;font-size:14px}
.sk-pp__text{font-size:13.5px;color:#6E5A66}
.sk-pp__btn{margin-left:auto;flex:none;padding:14px 22px;border-radius:999px;background:#D1127E;color:#fff!important;font:700 14.5px Manrope,sans-serif;text-decoration:none!important}
.sk-pp--done{display:block;border-style:solid;border-color:#24704A;color:#24704A}
@media(max-width:560px){.sk-rw__marks{display:none}}
@media(max-width:700px){.sk-kitup,.sk-pp{flex-wrap:wrap}.sk-kitup__btn,.sk-pp__btn{margin-left:0;width:100%;text-align:center}.sk-fbt{padding:20px}.sk-fbt__row{flex-direction:column}.sk-fbt__item{flex-direction:row;align-items:center}.sk-fbt__item img{width:64px;flex:none}.sk-fbt__item input{position:static}.sk-fbt__plus{display:none}.sk-fbt__price{margin:0 0 0 auto}}
</style>
		<?php
	}
);
