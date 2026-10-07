<?php
/**
 * Lux esthetician site – Elementor page builder helpers.
 * Functions only; nothing runs on load. Build scripts call these via Novamira.
 */
if ( ! defined( 'ABSPATH' ) || function_exists( 'lux_id' ) ) {
	return;
}

function lux_colors() {
	return [
		'primary'   => [ 'Plum', '#3B1530' ],
		'secondary' => [ 'Blush', '#E8B4B8' ],
		'text'      => [ 'Ink', '#2A1A24' ],
		'accent'    => [ 'Brand Pink', '#D1127E' ],
		'forestdk'  => [ 'Plum Deep', '#26101F' ],
		'cream'     => [ 'Cream', '#FFF8F6' ],
		'sagemist'  => [ 'Blush Mist', '#FBEFF0' ],
		'sagelt'    => [ 'Blush Light', '#F6DDE1' ],
		'muted'     => [ 'Muted Text', '#6E5A66' ],
		'gold'      => [ 'Pink Light', '#FF8CC8' ],
		'line'      => [ 'Line', '#EADDE0' ],
		'white'     => [ 'White', '#FFFFFF' ],
		'forestln'  => [ 'Plum Line', '#5A2E4C' ],
		'misttext'  => [ 'Blush Text', '#F3DCE8' ],
	];
}

function lux_id() {
	return substr( md5( uniqid( '', true ) . wp_rand() ), 0, 7 );
}

function lux_u( $size, $unit = 'px' ) {
	return [ 'unit' => $unit, 'size' => $size, 'sizes' => [] ];
}

function lux_box( $t, $r = null, $b = null, $l = null, $unit = 'px' ) {
	$r = null === $r ? $t : $r;
	$b = null === $b ? $t : $b;
	$l = null === $l ? $r : $l;
	return [
		'unit'     => $unit,
		'top'      => (string) $t,
		'right'    => (string) $r,
		'bottom'   => (string) $b,
		'left'     => (string) $l,
		'isLinked' => ( $t == $r && $r == $b && $b == $l ),
	];
}

function lux_gap( $col, $row = null ) {
	$row = null === $row ? $col : $row;
	return [ 'unit' => 'px', 'size' => $col, 'column' => (string) $col, 'row' => (string) $row, 'isLinked' => $col == $row ];
}

/** Set a colour control to a kit global (keeps raw value as fallback). */
function lux_color( array &$s, $key, $id ) {
	$c                         = lux_colors();
	$s[ $key ]                 = $c[ $id ][1];
	$s['__globals__'][ $key ] = 'globals/colors?id=' . $id;
}

/** Typography group. $t keys: f family, s size, st tablet, sm mobile, w weight, lh, lhu, ls (em), tt, fs. */
function lux_typo( array &$s, $prefix, array $t ) {
	$p                    = $prefix . 'typography_';
	$s[ $p . 'typography' ] = 'custom';
	if ( isset( $t['f'] ) ) {
		$s[ $p . 'font_family' ] = $t['f'];
	}
	if ( isset( $t['s'] ) ) {
		$s[ $p . 'font_size' ] = lux_u( $t['s'] );
	}
	if ( isset( $t['st'] ) ) {
		$s[ $p . 'font_size_tablet' ] = lux_u( $t['st'] );
	}
	if ( isset( $t['sm'] ) ) {
		$s[ $p . 'font_size_mobile' ] = lux_u( $t['sm'] );
	}
	if ( isset( $t['w'] ) ) {
		$s[ $p . 'font_weight' ] = (string) $t['w'];
	}
	if ( isset( $t['lh'] ) ) {
		$s[ $p . 'line_height' ] = lux_u( $t['lh'], isset( $t['lhu'] ) ? $t['lhu'] : 'em' );
	}
	if ( isset( $t['ls'] ) ) {
		$s[ $p . 'letter_spacing' ] = lux_u( $t['ls'], 'em' );
	}
	if ( isset( $t['tt'] ) ) {
		$s[ $p . 'text_transform' ] = $t['tt'];
	}
	if ( isset( $t['fs'] ) ) {
		$s[ $p . 'font_style' ] = $t['fs'];
	}
}

function lux_t( $name ) {
	$t = [
		'h1'    => [ 'f' => 'Fraunces', 's' => 60, 'st' => 48, 'sm' => 40, 'w' => '500', 'lh' => 1.1, 'ls' => -0.01 ],
		'h2'    => [ 'f' => 'Fraunces', 's' => 42, 'st' => 36, 'sm' => 30, 'w' => '500', 'lh' => 1.19, 'ls' => -0.01 ],
		'h3'    => [ 'f' => 'Fraunces', 's' => 22, 'w' => '400', 'lh' => 30, 'lhu' => 'px' ],
		'eye'   => [ 'f' => 'Manrope', 's' => 13, 'w' => '600', 'lh' => 1.2, 'ls' => 0.14, 'tt' => 'uppercase' ],
		'body'  => [ 'f' => 'Manrope', 's' => 17, 'w' => '400', 'lh' => 28, 'lhu' => 'px' ],
		'small' => [ 'f' => 'Manrope', 's' => 15, 'w' => '400', 'lh' => 24, 'lhu' => 'px' ],
		'ui'    => [ 'f' => 'Manrope', 's' => 15, 'w' => '600', 'lh' => 1.3 ],
	];
	return $t[ $name ];
}

/* ---------- element constructors ---------- */

/** Inner container (full width, no default padding). */
function lux_con( array $s, array $children = [], $title = '' ) {
	$s += [ 'content_width' => 'full', 'padding' => lux_box( 0 ) ];
	if ( $title ) {
		$s['_title'] = $title;
	}
	return [ 'id' => lux_id(), 'elType' => 'container', 'isInner' => true, 'settings' => $s, 'elements' => $children ];
}

/** Top-level section container, boxed. */
function lux_section( $title, array $s, array $children, $boxed = 1200 ) {
	$s += [
		'content_width'  => 'boxed',
		'boxed_width'    => lux_u( $boxed ),
		'flex_direction' => 'column',
		'padding'        => lux_box( 120, 24, 120, 24 ),
		'padding_mobile' => lux_box( 72, 16, 72, 16 ),
	];
	$s['_title'] = $title;
	return [ 'id' => lux_id(), 'elType' => 'container', 'isInner' => false, 'settings' => $s, 'elements' => $children ];
}

function lux_w( $type, array $s, $title = '' ) {
	if ( $title ) {
		$s['_title'] = $title;
	}
	return [ 'id' => lux_id(), 'elType' => 'widget', 'widgetType' => $type, 'isInner' => false, 'settings' => $s, 'elements' => [] ];
}

function lux_heading( $title, $tag, $typo, $color = 'primary', array $x = [] ) {
	$s = [ 'title' => $title, 'header_size' => $tag ];
	lux_typo( $s, '', is_array( $typo ) ? $typo : lux_t( $typo ) );
	lux_color( $s, 'title_color', $color );
	return lux_w( 'heading', array_replace( $s, $x ) );
}

function lux_text( $html, $typo = 'body', $color = 'muted', array $x = [] ) {
	$s = [ 'editor' => $html ];
	lux_typo( $s, '', is_array( $typo ) ? $typo : lux_t( $typo ) );
	lux_color( $s, 'text_color', $color );
	return lux_w( 'text-editor', array_replace( $s, $x ) );
}

/** Eyebrow pill. $variant: light | dark | mist | plain | none (no pill, just label). */
function lux_eyebrow( $text, $variant = 'light', array $x = [] ) {
	$color = 'dark' === $variant || 'none-gold' === $variant ? 'gold' : 'primary';
	$class = 'none' === $variant || 'none-gold' === $variant ? '' : 'lux-pill lux-pill--' . $variant;
	return lux_heading( $text, 'div', 'eye', $color, array_replace( [ '_css_classes' => $class ], $x ) );
}

function lux_link( $url ) {
	return [ 'url' => $url, 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' ];
}

/** Pill button. $v: primary | outline | lime | outline-light */
function lux_button( $text, $url, $v = 'primary', array $x = [] ) {
	$s = [
		'text'                              => $text,
		'link'                              => lux_link( $url ),
		'border_radius'                     => lux_box( 999 ),
		'text_padding'                      => lux_box( 18, 28, 18, 28 ),
		'background_background'             => 'classic',
		'button_background_hover_background' => 'classic',
	];
	lux_typo( $s, '', [ 'f' => 'Manrope', 's' => 15, 'w' => '600', 'lh' => 1 ] );
	switch ( $v ) {
		case 'outline':
			$s['background_color'] = 'rgba(0,0,0,0)';
			$s['border_border']    = 'solid';
			$s['border_width']     = lux_box( 1 );
			$s['text_padding']     = lux_box( 17, 26, 17, 26 );
			lux_color( $s, 'border_color', 'primary' );
			lux_color( $s, 'button_text_color', 'primary' );
			lux_color( $s, 'button_background_hover_color', 'white' );
			lux_color( $s, 'hover_color', 'primary' );
			break;
		case 'lime':
			lux_color( $s, 'background_color', 'accent' );
			lux_color( $s, 'button_text_color', 'white' );
			$s['button_background_hover_color'] = '#B00E6A';
			lux_color( $s, 'hover_color', 'white' );
			break;
		case 'outline-light':
			$s['background_color'] = 'rgba(0,0,0,0)';
			$s['border_border']    = 'solid';
			$s['border_width']     = lux_box( 1 );
			$s['text_padding']     = lux_box( 17, 26, 17, 26 );
			lux_color( $s, 'border_color', 'secondary' );
			lux_color( $s, 'button_text_color', 'cream' );
			lux_color( $s, 'button_background_hover_color', 'forestln' );
			lux_color( $s, 'hover_color', 'cream' );
			break;
		default:
			lux_color( $s, 'background_color', 'primary' );
			lux_color( $s, 'button_text_color', 'cream' );
			lux_color( $s, 'button_background_hover_color', 'forestdk' );
			lux_color( $s, 'hover_color', 'white' );
	}
	return lux_w( 'button', array_replace( $s, $x ) );
}

/** Image widget with Elementor placeholder; $desc becomes the Navigator label (shot description). */
function lux_img( $desc, array $x = [] ) {
	$s = [
		'image'      => [ 'url' => \Elementor\Utils::get_placeholder_image_src(), 'id' => '', 'alt' => '', 'source' => 'library' ],
		'image_size' => 'full',
		'width'      => lux_u( 100, '%' ),
	];
	return lux_w( 'image', array_replace( $s, $x ), 'Image: ' . $desc );
}

function lux_icon( $icon, array $x = [] ) {
	$s = [ 'selected_icon' => [ 'value' => $icon, 'library' => 'fa-solid' ] ];
	return lux_w( 'icon', array_replace( $s, $x ) );
}

function lux_icon_list( array $items, $icon, array $x = [] ) {
	$list = [];
	foreach ( $items as $i ) {
		$list[] = [ 'text' => $i, 'selected_icon' => [ 'value' => $icon, 'library' => 'fa-solid' ], '_id' => lux_id() ];
	}
	return lux_w( 'icon-list', array_replace( [ 'icon_list' => $list ], $x ) );
}

/** Header row: left (eyebrow + heading), right (button). */
function lux_head_row( array $left, $button = null, $left_width = null ) {
	$l = [ 'flex_direction' => 'column', 'flex_align_items' => 'flex-start', 'flex_gap' => lux_gap( 20 ) ];
	if ( $left_width ) {
		$l['width']        = lux_u( $left_width );
		$l['width_mobile'] = lux_u( 100, '%' );
	}
	$kids = [ lux_con( $l, $left ) ];
	if ( $button ) {
		$kids[] = $button;
	}
	return lux_con(
		[
			'flex_direction'       => 'row',
			'flex_wrap'            => 'wrap',
			'flex_justify_content' => 'space-between',
			'flex_align_items'     => 'flex-end',
			'flex_gap'             => lux_gap( 24 ),
		],
		$kids,
		'Section header'
	);
}

/** Shared stylesheet stored in Customizer > Additional CSS between markers. */
function lux_install_css( $css ) {
	$current = (string) wp_get_custom_css();
	$block   = "/* LUX-START (managed by builder) */\n" . trim( $css ) . "\n/* LUX-END */";
	if ( false !== strpos( $current, '/* LUX-START' ) ) {
		// Callback, not a replacement string: the CSS contains backslashes (e.g. "\2192") that would be read as backreferences.
		$current = preg_replace_callback( '#/\* LUX-START.*?/\* LUX-END \*/#s', fn() => $block, $current );
	} else {
		$current = trim( $current . "\n\n" . $block );
	}
	return wp_update_custom_css_post( $current );
}


/** Entrance animations: section children fade up, grid items stagger. Hero animates without delay offset. */
function lux_set_anim( array &$el, $name, $delay ) {
	if ( 'widget' === $el['elType'] ) {
		$el['settings']['_animation']       = $name;
		$el['settings']['_animation_delay'] = $delay;
	} else {
		$el['settings']['animation']       = $name;
		$el['settings']['animation_delay'] = $delay;
	}
}
function lux_animate( array $sections ) {
	// Motion is used sparingly: only card grids (3+ items) get a soft, staggered fade.
	// Text, headings and heroes stay still. Counters animate on their own.
	foreach ( $sections as &$sec ) {
		foreach ( $sec['elements'] as &$child ) {
			lux_animate_grids( $child );
		}
		unset( $child );
	}
	unset( $sec );
	return $sections;
}
function lux_animate_grids( array &$el ) {
	if ( 'container' !== $el['elType'] ) {
		return;
	}
	if ( 'grid' === ( $el['settings']['container_type'] ?? '' ) && count( $el['elements'] ) >= 3 ) {
		$j = 0;
		foreach ( $el['elements'] as &$item ) {
			if ( empty( $item['settings']['animation'] ) && empty( $item['settings']['_animation'] ) ) {
				lux_set_anim( $item, 'fadeIn', min( 320, $j * 80 ) );
			}
			$j++;
		}
		unset( $item );
		return;
	}
	foreach ( $el['elements'] as &$c ) {
		lux_animate_grids( $c );
	}
	unset( $c );
}

/** Animated number (Elementor counter widget). */
function lux_counter( $end, $suffix, $title, $dark = false, array $x = [] ) {
	$s = [
		'starting_number'    => 0,
		'ending_number'      => $end,
		'suffix'             => $suffix,
		'duration'           => 1600,
		'thousand_separator' => '',
		'title'              => $title,
		'title_tag'          => 'div',
		'title_position'     => 'after',
		'number_alignment'   => 'start',
		'title_horizontal_alignment' => 'start',
		'_css_classes'       => 'lux-counter',
	];
	$s['number_color'] = $dark ? '#FFF8F6' : '#3B1530';
	$s['title_color']  = $dark ? '#F3DCE8' : '#6E5A66';
	// Counter typography groups are named typography_number / typography_title.
	foreach ( [ 'number' => [ 'f' => 'Fraunces', 's' => 52, 'sm' => 42, 'w' => '500', 'lh' => 1 ], 'title' => [ 'f' => 'Manrope', 's' => 15, 'w' => '500', 'lh' => 1.4 ] ] as $g => $t ) {
		$tmp = [];
		lux_typo( $tmp, '', $t );
		foreach ( $tmp as $key => $v ) {
			$s[ 'typography_' . $g . substr( $key, 10 ) ] = $v;
		}
	}
	return lux_w( 'counter', $s + $x, 'Counter: ' . $title );
}

/** Create or update an Elementor page by slug. */
function lux_save_page( $slug, $title, array $elements, $template = 'elementor_header_footer' ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( $page ) {
		$id = $page->ID;
		wp_update_post( [ 'ID' => $id, 'post_title' => $title, 'post_status' => 'publish' ] );
	} else {
		$id = wp_insert_post( [ 'post_type' => 'page', 'post_title' => $title, 'post_name' => $slug, 'post_status' => 'publish' ] );
	}
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', 'wp-page' );
	update_post_meta( $id, '_wp_page_template', $template );
	update_post_meta( $id, 'site-sidebar-layout', 'no-sidebar' );
	update_post_meta( $id, 'site-content-layout', 'page-builder' );
	update_post_meta( $id, 'ast-site-content-layout', 'full-width-container' );
	update_post_meta( $id, 'site-post-title', 'disabled' );
	$doc = \Elementor\Plugin::$instance->documents->get( $id, false );
	$elements = lux_animate( $elements );
	$doc->save( [ 'elements' => $elements, 'settings' => [ 'hide_title' => 'yes', 'template' => $template ] ] );
	return $id;
}
