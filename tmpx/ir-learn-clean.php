<?php
/**
 * Plugin Name: IR Learn Clean
 * Description: Course lesson, quiz and assignment pages show only the course player (no website header, footer or WhatsApp button).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ir_learn_is_player() {
	static $is = null;
	if ( null !== $is ) { return $is; }
	$types = [ 'lesson', 'tutor_quiz', 'tutor_assignments', 'tutor_zoom_meeting', 'tutor-google-meet' ];
	if ( function_exists( 'tutor' ) ) {
		foreach ( [ 'lesson_post_type', 'quiz_post_type', 'assignment_post_type' ] as $k ) {
			if ( ! empty( tutor()->$k ) ) { $types[] = tutor()->$k; }
		}
	}
	$is = is_singular( array_unique( $types ) );
	return $is;
}

/* Skip the Elementor header and footer templates on player pages. */
add_filter( 'elementor/theme/get_location_templates/template_id', function ( $id, $location = '' ) {
	if ( in_array( $location, [ 'header', 'footer' ], true ) && did_action( 'wp' ) && ir_learn_is_player() ) { return 0; }
	return $id;
}, 10, 2 );

/* Astra would print its own header/footer when Elementor has none, so drop those too. */
add_action( 'wp', function () {
	if ( ! ir_learn_is_player() ) { return; }
	remove_all_actions( 'astra_header' );
	remove_all_actions( 'astra_footer' );
	remove_all_actions( 'astra_masthead' );
}, 20 );

add_filter( 'body_class', function ( $c ) {
	if ( ir_learn_is_player() ) { $c[] = 'ir-player'; }
	return $c;
} );

add_action( 'wp_head', function () {
	if ( ! ir_learn_is_player() ) { return; }
	?>
<style id="ir-learn-clean">
body.ir-player .elementor-location-header,body.ir-player .elementor-location-footer,body.ir-player #masthead,body.ir-player .site-header,body.ir-player .site-footer,body.ir-player #colophon,body.ir-player .ast-above-header-wrap,body.ir-player .ast-below-header-wrap,body.ir-player .ast-mobile-header-wrap,body.ir-player .ir-wa,body.ir-player #ast-scroll-top{display:none!important}
body.ir-player{padding-top:0!important;margin-top:0!important}
</style>
	<?php
}, 99 );
