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

/* Tutor LMS loads its course player (React, Moment and more) on every page. Keep it to course pages. */
function ir_speed_tutor_page() {
	if ( is_singular( [ 'courses', 'lesson', 'tutor_quiz', 'tutor_assignments', 'tutor_zoom_meeting', 'tutor-google-meet' ] ) || is_post_type_archive( 'courses' ) || is_tax( [ 'course-category', 'course-tag' ] ) ) { return true; }
	if ( function_exists( 'tutor_utils' ) && is_page() ) {
		$id = get_queried_object_id();
		foreach ( [ 'tutor_dashboard_page_id', 'instructor_register_page', 'student_register_page', 'tutor_checkout_page_id', 'tutor_cart_page_id', 'course_archive_page' ] as $k ) { if ( $id && (int) tutor_utils()->get_option( $k ) === $id ) { return true; } }
	}
	$post = get_post();
	if ( $post && preg_match( '/\[tutor|tutor_|"widgetType":"(tutor|course|etlms)/i', (string) $post->post_content . (string) get_post_meta( $post->ID, '_elementor_data', true ) ) ) { return true; }
	return false;
}
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || is_user_logged_in() || ir_speed_tutor_page() ) { return; }
	global $wp_scripts, $wp_styles;
	foreach ( (array) $wp_scripts->queue as $h ) { $src = (string) ( $wp_scripts->registered[ $h ]->src ?? '' ); if ( preg_match( '#/plugins/tutor(-pro)?/#', $src ) ) { wp_dequeue_script( $h ); } }
	foreach ( (array) $wp_styles->queue as $h ) { $src = (string) ( $wp_styles->registered[ $h ]->src ?? '' ); if ( preg_match( '#/plugins/tutor(-pro)?/#', $src ) ) { wp_dequeue_style( $h ); } }
}, 999 );

/* Warm the most visited pages after the cache is cleared. */
function ir_speed_schedule_warm() { if ( ! wp_next_scheduled( 'ir_speed_warm' ) ) { wp_schedule_single_event( time() + 20, 'ir_speed_warm' ); } }
add_action( 'litespeed_purged_all', 'ir_speed_schedule_warm' );
add_action( 'litespeed_purge_all', 'ir_speed_schedule_warm', 99 );
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
