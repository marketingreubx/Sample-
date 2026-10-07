<?php
/**
 * Dev helper: self-contained HTML snapshot of a page (inlined CSS, data-URI images, no scripts)
 * for offline visual review. Not used by the live site.
 */
if ( ! defined( 'ABSPATH' ) || function_exists( 'lux_snap' ) ) {
	return;
}

function lux_snap( $url, $img_max = 420, $menu_open = false ) {
	$html = wp_remote_retrieve_body( wp_remote_get( add_query_arg( 'snap', time(), $url ), [ 'timeout' => 40, 'sslverify' => false ] ) );
	$site = home_url();
	$html = preg_replace_callback(
		'#<link[^>]+href=[\'"](' . preg_quote( $site, '#' ) . '[^\'"]+\.css)(\?[^\'"]*)?[\'"][^>]*>#',
		function ( $m ) use ( $site ) {
			$css = @file_get_contents( ABSPATH . ltrim( substr( $m[1], strlen( $site ) ), '/' ) );
			return false === $css ? '' : '<style>' . $css . '</style>';
		},
		$html
	);
	$html = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $html );
	$html = preg_replace( '#\s(srcset|sizes|data-src)="[^"]*"#', '', $html );
	$html = str_replace( [ 'loading="lazy"', 'elementor-invisible' ], [ '', '' ], $html );
	$html = preg_replace( '/class="elementor-element /', 'class="e-lazyloaded elementor-element ', $html );
	if ( $menu_open ) {
		$html = preg_replace( '/<body class="/', '<body class="ast-main-header-nav-open ast-popup-nav-open ', $html, 1 );
		$html = str_replace( 'class="ast-mobile-popup-drawer', 'class="ast-mobile-popup-drawer active show', $html );
	}
	$ids = array_merge( array_values( get_option( 'skynco_media_ids', [] ) ), array_values( get_option( 'skynco_shop_media', [] ) ) );
	foreach ( (array) get_option( 'skynco_before_after', [] ) as $pairs ) {
		foreach ( $pairs as $p ) {
			$ids[] = $p['before'];
			$ids[] = $p['after'];
		}
	}
	foreach ( array_unique( array_filter( $ids ) ) as $id ) {
		$file = get_attached_file( $id );
		$u    = wp_get_attachment_url( $id );
		if ( ! $file || ! $u || ! preg_match( '/\.(png|jpe?g)$/i', $file ) ) {
			continue;
		}
		$base = preg_replace( '/\.(png|jpe?g)$/i', '', $u );
		if ( false === strpos( $html, basename( $base ) ) ) {
			continue;
		}
		$im = @imagecreatefromstring( file_get_contents( $file ) );
		if ( ! $im ) {
			continue;
		}
		$w   = imagesx( $im );
		$h   = imagesy( $im );
		$sc  = min( 1, ( preg_match( '/hero|poster/', $file ) ? 1000 : $img_max ) / max( $w, $h ) );
		$n   = imagecreatetruecolor( max( 1, (int) ( $w * $sc ) ), max( 1, (int) ( $h * $sc ) ) );
		$png = (bool) preg_match( '/\.png$/i', $file );
		if ( $png ) {
			imagealphablending( $n, false );
			imagesavealpha( $n, true );
		}
		imagecopyresampled( $n, $im, 0, 0, 0, 0, imagesx( $n ), imagesy( $n ), $w, $h );
		ob_start();
		$png ? imagepng( $n, null, 9 ) : imagejpeg( $n, null, 62 );
		$data = 'data:image/' . ( $png ? 'png' : 'jpeg' ) . ';base64,' . base64_encode( ob_get_clean() );
		$html = preg_replace( '#' . preg_quote( $base, '#' ) . '(-\d+x\d+)?\.(png|jpe?g)#i', $data, $html );
	}
	return base64_encode( gzencode( $html, 9 ) );
}
