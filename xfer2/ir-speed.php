<?php
/**
 * Plugin Name: IR Speed
 * Description: Keeps shop files off pages that are not part of the shop, and re-warms the page cache after every purge so visitors never wait for a cold page.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* WooCommerce styles and scripts only where the shop is used. */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || ! function_exists( 'is_woocommerce' ) ) { return; }
	if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) { return; }
	$post = get_post();
	if ( $post && preg_match( '/\[(products|product_page|add_to_cart|woocommerce_|product_category)|wp:woocommerce\/|"widgetType":"woocommerce-|woocommerce-product/', (string) $post->post_content . (string) get_post_meta( $post->ID, '_elementor_data', true ) ) ) { return; }
	foreach ( [ 'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-general', 'wc-blocks-style' ] as $h ) { wp_dequeue_style( $h ); }
	foreach ( [ 'wc-add-to-cart', 'wc-jquery-blockui', 'jquery-blockui', 'woocommerce', 'wc-cart-fragments' ] as $h ) { wp_dequeue_script( $h ); }
}, 99 );

/* Warm the most visited pages after the cache is cleared. */
function ir_speed_schedule_warm() { if ( ! wp_next_scheduled( 'ir_speed_warm' ) ) { wp_schedule_single_event( time() + 20, 'ir_speed_warm' ); } }
add_action( 'litespeed_purged_all', 'ir_speed_schedule_warm' );
add_action( 'ir_speed_warm', function () {
	$urls = [ home_url( '/' ), home_url( '/academy/' ), home_url( '/members/' ) ];
	foreach ( get_posts( [ 'post_type' => [ 'page', 'product', 'courses' ], 'post_status' => 'publish', 'numberposts' => 40, 'orderby' => 'menu_order date', 'fields' => 'ids' ] ) as $id ) { $urls[] = get_permalink( $id ); }
	$urls = array_slice( array_unique( $urls ), 0, 45 );
	foreach ( $urls as $u ) {
		wp_remote_get( $u, [ 'timeout' => 15, 'blocking' => true, 'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36 lscache_runner' ] );
		wp_remote_get( $u, [ 'timeout' => 15, 'blocking' => true, 'user-agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 lscache_runner' ] );
	}
	update_option( 'ir_speed_warmed', [ time(), count( $urls ) ], false );
} );
