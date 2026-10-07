<?php
/**
 * Skyn&Co. – LatePoint data: location, hours, esthetician, categories and treatments.
 * Idempotent: rows are matched by name and updated in place. Functions only.
 */
if ( ! defined( 'ABSPATH' ) || function_exists( 'lux_lp_treatments' ) ) {
	return;
}

/** Treatment catalogue: [slug, name, category, minutes, price, deposit, short description, image]. */
function lux_lp_treatments() {
	return [
		[ 'new-client-facial', 'New Client Facial & Consultation', 'Start Here', 60, 155, 30, 'A full Signature Facial plus a 15-minute consultation, written skin analysis and home regimen. For first visits or after 12+ months.', 'skynco-new-client-facial.jpg' ],
		[ 'signature-facial', 'Signature Facial', 'Facials', 50, 150, 30, 'Fully customized: double cleanse, skin analysis, steam and exfoliation, extractions, light therapy and a custom treatment.', 'skynco-peel-mask.png' ],
		[ 'acne-facial', 'Acne Facial', 'Facials', 60, 150, 30, 'Deep cleanse, exfoliation and purifying mask with Blue LED and argon high frequency to target breakout bacteria.', 'skynco-facial-treatment-room.png' ],
		[ 'brightening-facial', 'Brightening Facial', 'Facials', 60, 150, 30, 'Powerful enzymes smooth the skin and soften the look of sun and age spots for an even, glowing tone.', 'skynco-peel-mask.png' ],
		[ 'exfoliating-facial', 'Exfoliating Facial', 'Facials', 50, 150, 30, 'A professional peel solution for even tone and clearer skin, with no visible peeling and no downtime.', 'skynco-new-client-facial.jpg' ],
		[ 'gentlemans-facial', 'Gentleman’s Facial', 'Facials', 60, 155, 30, 'Made for men’s skin: deep cleanse, scrub, extractions, cooling hydro-jelly mask, beard treatment and high frequency.', 'skynco-facial-treatment-room.png' ],
		[ 'express-facial', 'Express Facial', 'Facials', 30, 115, 25, 'Cleanse, deep exfoliation and a replenishing mask for a quick, healthy glow.', 'skynco-new-client-facial.jpg' ],
		[ 'teen-facial', 'Teen Facial (ages 14 to 17)', 'Facials', 50, 120, 25, 'Exfoliation and deep pore cleansing for hormonal breakouts. Guardian consent form required.', 'skynco-facial-treatment-room.png' ],
		[ 'glo2-facial', 'Glo2 Facial', 'Advanced Treatments', 60, 200, 50, 'Patented oxygenating OxFoliation, LUX ultrasound infusion and lymphatic massage, with Celluma light therapy.', 'skynco-glo2-facial.jpg' ],
		[ 'oxy-facial', 'Oxy Facial', 'Advanced Treatments', 75, 175, 40, 'Concentrated oxygen mist and serums plus 10 to 15 minutes in the Oxygen Dome.', 'skynco-oxygen-facial.jpg' ],
		[ 'microneedling', 'Microneedling', 'Advanced Treatments', 90, 185, 50, 'Collagen induction for texture, fine lines and scarring. About 3 days of redness, full recovery in roughly 7.', 'skynco-microneedling-pen.png' ],
		[ 'derma-facial', 'Derma Facial (Dermaplaning)', 'Advanced Treatments', 75, 150, 30, 'Removes dead skin and peach fuzz for smoother, brighter skin that absorbs products better.', 'skynco-peel-mask.png' ],
		[ 'chemical-peel', 'Chemical Peel (no visible peeling)', 'Advanced Treatments', 45, 140, 30, '30% glycolic with phytic acid and prickly pear to fade dark marks and even tone. No downtime.', 'skynco-new-client-facial.jpg' ],
		[ 'tca-peel', 'Chemical Peel (visible peeling, TCA)', 'Advanced Treatments', 45, 155, 30, 'Strong resurfacing for acne and hyperpigmentation. Skin peels for about 7 to 10 days.', 'skynco-facial-treatment-room.png' ],
		[ 'upper-lip-wax', 'Upper Lip Wax', 'Waxing', 15, 10, 0, 'Quick, clean upper lip waxing.', '' ],
		[ 'chin-wax', 'Chin Wax', 'Waxing', 15, 15, 0, 'Quick, clean chin waxing.', '' ],
		[ 'underarm-wax', 'Underarm Wax', 'Waxing', 15, 20, 0, 'Smooth underarm waxing.', '' ],
		[ 'stomach-wax', 'Stomach Wax', 'Waxing', 20, 30, 0, 'Stomach waxing.', '' ],
		[ 'arm-wax', 'Arm Wax', 'Waxing', 30, 50, 0, 'Full arm waxing.', '' ],
		[ 'leg-wax', 'Leg Wax', 'Waxing', 45, 60, 0, 'Full leg waxing.', '' ],
	];
}

function lux_lp_setup() {
	global $wpdb;
	$p   = $wpdb->prefix . 'latepoint_';
	$now = current_time( 'mysql' );
	$out = [];

	// Location.
	$loc_id = (int) $wpdb->get_var( "SELECT id FROM {$p}locations ORDER BY id LIMIT 1" );
	$wpdb->update( "{$p}locations", [ 'name' => 'Skyn&Co. Studio', 'full_address' => '150 Arsenal St, Suite 210, Watertown, MA 02472', 'updated_at' => $now ], [ 'id' => $loc_id ] );
	$out['location'] = $loc_id;

	// Weekly hours (minutes from midnight). Equal start and end marks a day off.
	$hours = [ 1 => [ 660, 1080 ], 2 => [ 0, 0 ], 3 => [ 840, 1200 ], 4 => [ 840, 1200 ], 5 => [ 0, 0 ], 6 => [ 600, 960 ], 7 => [ 720, 1140 ] ];
	$wpdb->query( "DELETE FROM {$p}work_periods WHERE agent_id = 0 AND service_id = 0 AND location_id = 0 AND custom_date IS NULL" );
	foreach ( $hours as $day => $h ) {
		$wpdb->insert( "{$p}work_periods", [ 'agent_id' => 0, 'service_id' => 0, 'location_id' => 0, 'start_time' => $h[0], 'end_time' => $h[1], 'week_day' => $day, 'created_at' => $now, 'updated_at' => $now ] );
	}

	// Esthetician.
	$agent_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}agents WHERE first_name = %s AND last_name = %s", 'Hana', 'Rahim' ) );
	$agent    = [ 'first_name' => 'Hana', 'last_name' => 'Rahim', 'display_name' => 'Hana', 'title' => 'Licensed Esthetician, Founder', 'bio' => 'Licensed esthetician with over 10 years in the beauty industry and more than 8 years of hands-on practice.', 'email' => get_option( 'admin_email' ), 'phone' => '+18572284708', 'status' => 'active', 'updated_at' => $now ];
	if ( $agent_id ) {
		$wpdb->update( "{$p}agents", $agent, [ 'id' => $agent_id ] );
	} else {
		$wpdb->insert( "{$p}agents", $agent + [ 'created_at' => $now ] );
		$agent_id = (int) $wpdb->insert_id;
	}
	$out['agent'] = $agent_id;

	// Categories.
	$cats = [];
	foreach ( [ 'Start Here', 'Facials', 'Advanced Treatments', 'Waxing' ] as $i => $c ) {
		$cid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}service_categories WHERE name = %s", $c ) );
		if ( ! $cid ) {
			$wpdb->insert( "{$p}service_categories", [ 'name' => $c, 'parent_id' => 0, 'order_number' => $i + 1, 'created_at' => $now, 'updated_at' => $now ] );
			$cid = (int) $wpdb->insert_id;
		}
		$cats[ $c ] = $cid;
	}

	// Services.
	$media = get_option( 'skynco_media_ids', [] );
	$ids   = [];
	foreach ( lux_lp_treatments() as $i => $t ) {
		$row = [
			'name'                  => $t[1],
			'short_description'     => $t[6],
			'is_price_variable'     => 0,
			'price_min'             => $t[4],
			'price_max'             => $t[4],
			'charge_amount'         => $t[4],
			'deposit_amount'        => $t[5],
			'is_deposit_required'   => $t[5] > 0 ? 1 : 0,
			'duration'              => $t[3],
			'buffer_before'         => 0,
			'buffer_after'          => 'Waxing' === $t[2] ? 5 : 15,
			'category_id'           => $cats[ $t[2] ],
			'order_number'          => $i + 1,
			'selection_image_id'    => $t[7] && isset( $media[ $t[7] ] ) ? (int) $media[ $t[7] ] : 0,
			'description_image_id'  => $t[7] && isset( $media[ $t[7] ] ) ? (int) $media[ $t[7] ] : 0,
			'bg_color'              => 'Waxing' === $t[2] ? '#E8B4B8' : ( 'Advanced Treatments' === $t[2] ? '#3B1530' : '#D1127E' ),
			'timeblock_interval'    => 15,
			'capacity_min'          => 1,
			'capacity_max'          => 1,
			'status'                => 'active',
			'visibility'            => 'visible',
			'updated_at'            => $now,
		];
		$sid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}services WHERE name = %s", $t[1] ) );
		if ( $sid ) {
			$wpdb->update( "{$p}services", $row, [ 'id' => $sid ] );
		} else {
			$wpdb->insert( "{$p}services", $row + [ 'created_at' => $now ] );
			$sid = (int) $wpdb->insert_id;
		}
		$ids[ $t[0] ] = $sid;
		$has = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$p}agents_services WHERE agent_id = %d AND service_id = %d AND location_id = %d", $agent_id, $sid, $loc_id ) );
		if ( ! $has ) {
			$wpdb->insert( "{$p}agents_services", [ 'agent_id' => $agent_id, 'service_id' => $sid, 'location_id' => $loc_id, 'is_custom_hours' => 0, 'is_custom_price' => 0, 'is_custom_duration' => 0, 'created_at' => $now, 'updated_at' => $now ] );
		}
	}
	update_option( 'skynco_lp_service_ids', $ids, false );
	$out['services'] = $ids;
	return $out;
}
