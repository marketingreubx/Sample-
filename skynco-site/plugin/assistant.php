<?php
/**
 * Skyn&Co. Skin Assistant: an on-site virtual skin consultation.
 *
 * - With an Anthropic API key (Studio → Skin Assistant) it is a full AI skin
 *   specialist that knows the treatment menu, products, FAQs and live availability,
 *   recommends a treatment and home care, and sends clients straight to booking
 *   with the service, date and time already chosen.
 * - Without a key it runs a guided consultation (concern → skin type → visit)
 *   with the same recommendations, live times and booking buttons.
 *
 * @package SkyncoStudio
 */

defined( 'ABSPATH' ) || exit;

function skynco_ai_settings() {
	return wp_parse_args(
		(array) get_option( 'skynco_ai', [] ),
		[
			'enabled'   => '1',
			'key'       => '',
			'model'     => 'claude-sonnet-5-5',
			'daily_cap' => '400',
		]
	);
}

function skynco_ai_live() {
	$s = skynco_ai_settings();
	return '1' === $s['enabled'] && $s['key'];
}

/* ---------------------------------------------------------------------------
 * Knowledge: services, products, FAQs, policies.
 * ------------------------------------------------------------------------ */
function skynco_ai_services() {
	if ( ! class_exists( 'OsServiceModel' ) ) {
		return [];
	}
	$out = [];
	foreach ( ( new OsServiceModel() )->where( [ 'status' => 'active' ] )->get_results_as_models() as $s ) {
		if ( 'hidden' === ( $s->visibility ?? '' ) ) {
			continue;
		}
		$slug        = function_exists( 'lux_tslug' ) ? lux_tslug( $s->name ) : sanitize_title( $s->name );
		$out[ $s->id ] = [
			'id'       => (int) $s->id,
			'name'     => $s->name,
			'slug'     => $slug,
			'duration' => (int) $s->duration,
			'price'    => (float) $s->charge_amount,
			'url'      => $slug && function_exists( 'lux_treatment_url' ) ? lux_treatment_url( $slug ) : home_url( '/services/' ),
		];
	}
	return $out;
}

function skynco_ai_knowledge() {
	$cached = get_transient( 'skynco_ai_kb' );
	if ( $cached ) {
		return $cached;
	}
	$details = function_exists( 'lux_treatment_details' ) ? lux_treatment_details() : [];
	$k       = "TREATMENTS (id | name | minutes | price USD | includes | best for | notes):\n";
	foreach ( skynco_ai_services() as $s ) {
		$d  = $details[ $s['slug'] ] ?? [ [], [], '' ];
		$k .= "- {$s['id']} | {$s['name']} | {$s['duration']} | \${$s['price']} | " . implode( '; ', (array) $d[0] ) . ' | ' . implode( '; ', (array) $d[1] ) . ' | ' . ( $d[2] ?? '' ) . "\n";
	}
	$k .= "\nADD-ONS (chosen in the booking form, paid in studio): LED Light Therapy \$25 (+15 min, calms redness, clears breakouts); Hydro-Jelly Mask \$15 (+10 min, cooling hydration); Upper Lip Wax \$10 (+5 min).\n";
	if ( function_exists( 'lux_shop_catalog' ) ) {
		$k .= "\nHOME-CARE PRODUCTS (slug | name | price USD | what it does):\n";
		foreach ( lux_shop_catalog() as $slug => $p ) {
			$price = ! empty( $p[3] ) ? $p[3] : $p[2];
			$k    .= "- {$slug} | {$p[0]} | \${$price} | {$p[4]} {$p[5]}\n";
		}
	}
	if ( function_exists( 'lux_faq_data' ) ) {
		$k .= "\nFAQ:\n";
		foreach ( lux_faq_data() as $items ) {
			foreach ( (array) $items as $qa ) {
				if ( is_array( $qa ) && isset( $qa[0], $qa[1] ) ) {
					$k .= '- Q: ' . wp_strip_all_tags( $qa[0] ) . ' A: ' . wp_strip_all_tags( $qa[1] ) . "\n";
				}
			}
		}
	}
	set_transient( 'skynco_ai_kb', $k, 6 * HOUR_IN_SECONDS );
	return $k;
}

function skynco_ai_system_prompt() {
	$today = wp_date( 'l, F j, Y' );
	return <<<TXT
You are the virtual skin specialist for Skyn&Co. Skincare & Wellness, the Watertown, MA studio of licensed esthetician Hana Rahim. You run friendly virtual skin consultations on the website, recommend the right treatment and home care, and help clients book.

Today is {$today} (studio time zone America/New_York).
Studio: 150 Arsenal St, Suite 210, Watertown, MA 02472. Call or text (857) 228-4708.
Hours: Mon 11am-6pm, Tue closed, Wed-Thu 2pm-8pm, Fri closed, Sat 10am-4pm, Sun 12pm-7pm.
Policies: a \$25 to \$50 deposit holds most facials when booking online; waxing is paid at the studio; free changes up to 24 hours before; late cancellations may lose the deposit. New clients should usually start with the New Client Facial & Consultation (id 1).

How to consult:
- Be warm, expert and concise: 2 to 4 short sentences, or a few short "- " bullets. No emojis. No markdown headings or bold.
- Ask one or two questions at a time. Cover: main concern and goal, skin type, sensitivity or allergies, current actives (retinoids, acids, acne medication), recent sun or tanning, pregnancy or breastfeeding when relevant (avoid recommending peels or microneedling then), first visit or returning.
- Then recommend ONE best treatment (plus one alternative if useful), saying in a sentence why it fits, and 2 to 3 home-care products. Use only treatments and products from the lists below, with their exact prices.
- When you recommend a treatment, call suggest_booking. When you recommend products, call recommend_products. When the client wants a day or time, or says yes to booking, call check_availability (use YYYY-MM-DD; for "this weekend" or "next week" pick the nearest open day from the hours above). Show times, then tell them to tap a time to finish booking (it opens the booking form with everything filled in; they only add their details).
- You cannot take payments or create bookings yourself; the buttons do that.
- You are not a doctor. Do not diagnose. For painful cysts, sudden rashes, infections, changing moles, severe or scarring acne, or anything that worries them, suggest seeing a dermatologist or doctor, and offer a gentle in-studio consultation.
- If asked something you do not know (for example a product not listed), say so and suggest texting Hana.
- Stay on skincare, the studio, treatments, products and booking.

KNOWLEDGE
{$GLOBALS['skynco_ai_kb_tmp']}
TXT;
}

/* ---------------------------------------------------------------------------
 * Availability and UI cards.
 * ------------------------------------------------------------------------ */
function skynco_ai_slots( $service_id, $date ) {
	if ( ! class_exists( 'OsResourceHelper' ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		return [];
	}
	$svc = new OsServiceModel( (int) $service_id );
	if ( empty( $svc->id ) ) {
		return [];
	}
	try {
		$req  = new \LatePoint\Misc\BookingRequest( [ 'service_id' => (int) $svc->id, 'agent_id' => 1, 'location_id' => 1, 'duration' => (int) $svc->duration ] );
		$days = OsResourceHelper::get_resources_grouped_by_day( $req, new OsWpDateTime( $date ) );
	} catch ( \Throwable $e ) {
		return [];
	}
	$now   = time();
	$times = [];
	foreach ( OsResourceHelper::get_ordered_booking_slots_from_resources( (array) ( $days[ $date ] ?? [] ) ) as $slot ) {
		$ts = date_create( $date . ' 00:00', wp_timezone() )->getTimestamp() + $slot->start_time * 60;
		if ( $slot->available_capacity() > 0 && $ts > $now + HOUR_IN_SECONDS ) {
			$times[] = (int) $slot->start_time;
		}
	}
	return array_values( array_unique( $times ) );
}

function skynco_ai_book_url( $service_id, $date = '', $time = null ) {
	$args = [ 'service' => (int) $service_id ];
	if ( $date ) {
		$args['date'] = $date;
	}
	if ( null !== $time && '' !== $time ) {
		$args['time'] = (int) $time;
	}
	return add_query_arg( $args, home_url( '/book/' ) );
}

function skynco_ai_fmt_time( $min ) {
	return gmdate( 'g:i a', (int) $min * 60 );
}

/** Times for a day (trimmed to an even spread) plus the next open days when it is full. */
function skynco_ai_availability_card( $service_id, $date ) {
	$services = skynco_ai_services();
	$svc      = $services[ (int) $service_id ] ?? null;
	if ( ! $svc ) {
		return [ 'error' => 'Unknown service id' ];
	}
	$times = skynco_ai_slots( $svc['id'], $date );
	$alt   = [];
	if ( ! $times ) {
		$d = date_create( $date, wp_timezone() ) ?: date_create( 'now', wp_timezone() );
		for ( $i = 1; $i <= 14 && count( $alt ) < 3; $i++ ) {
			$d->modify( '+1 day' );
			$t = skynco_ai_slots( $svc['id'], $d->format( 'Y-m-d' ) );
			if ( $t ) {
				$alt[] = [ 'date' => $d->format( 'Y-m-d' ), 'label' => wp_date( 'D, M j', $d->getTimestamp() ), 'times' => array_slice( $t, 0, 4 ) ];
			}
		}
	}
	if ( count( $times ) > 8 ) {
		$step  = count( $times ) / 8;
		$pick  = [];
		for ( $i = 0; $i < 8; $i++ ) {
			$pick[] = $times[ (int) floor( $i * $step ) ];
		}
		$times = array_values( array_unique( $pick ) );
	}
	$mk   = function ( $dt, $ts ) use ( $svc ) {
		return array_map( fn( $m ) => [ 'label' => skynco_ai_fmt_time( $m ), 'url' => skynco_ai_book_url( $svc['id'], $dt, $m ) ], $ts );
	};
	$card = [ 'type' => 'slots', 'service' => $svc['name'], 'days' => [] ];
	if ( $times ) {
		$card['days'][] = [ 'label' => wp_date( 'l, M j', strtotime( $date . ' 12:00' ) ), 'times' => $mk( $date, $times ) ];
	}
	foreach ( $alt as $a ) {
		$card['days'][] = [ 'label' => $a['label'], 'times' => $mk( $a['date'], $a['times'] ) ];
	}
	$card['more'] = skynco_ai_book_url( $svc['id'] );
	$summary      = $times
		? $svc['name'] . ' on ' . $date . ': open at ' . implode( ', ', array_map( 'skynco_ai_fmt_time', $times ) ) . '.'
		: $svc['name'] . ' is fully booked or closed on ' . $date . '. Next openings: ' . ( $alt ? implode( '; ', array_map( fn( $a ) => $a['label'] . ' ' . implode( ', ', array_map( 'skynco_ai_fmt_time', $a['times'] ) ), $alt ) ) : 'none in the next 2 weeks, suggest texting Hana' ) . '.';
	return [ 'card' => $card, 'summary' => $summary ];
}

function skynco_ai_service_card( $service_id ) {
	$services = skynco_ai_services();
	$svc      = $services[ (int) $service_id ] ?? null;
	if ( ! $svc ) {
		return null;
	}
	return [ 'type' => 'service', 'id' => $svc['id'], 'name' => $svc['name'], 'meta' => $svc['duration'] . ' min · $' . rtrim( rtrim( number_format( $svc['price'], 2 ), '0' ), '.' ), 'book' => skynco_ai_book_url( $svc['id'] ), 'info' => $svc['url'] ];
}

function skynco_ai_product_card( array $slugs ) {
	$items = [];
	foreach ( array_slice( $slugs, 0, 3 ) as $slug ) {
		$pid = function_exists( 'skynco_shop_id' ) ? skynco_shop_id( sanitize_title( $slug ) ) : 0;
		$p   = $pid ? wc_get_product( $pid ) : null;
		if ( ! $p ) {
			continue;
		}
		$img     = wp_get_attachment_image_url( $p->get_image_id(), 'woocommerce_thumbnail' );
		$items[] = [ 'name' => $p->get_name(), 'price' => html_entity_decode( wp_strip_all_tags( wc_price( $p->get_price() ) ) ), 'img' => $img ?: '', 'url' => $p->get_permalink(), 'add' => add_query_arg( 'add-to-cart', $p->get_id(), wc_get_cart_url() ) ];
	}
	return $items ? [ 'type' => 'products', 'items' => $items ] : null;
}

/* ---------------------------------------------------------------------------
 * AI chat endpoint.
 * ------------------------------------------------------------------------ */
function skynco_ai_tools() {
	return [
		[
			'name'         => 'check_availability',
			'description'  => 'Look up open appointment times for a treatment on a date and show them to the client as tappable buttons. If the day is full or closed it also returns the next open days.',
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'service_id' => [ 'type' => 'integer', 'description' => 'Treatment id from the list' ],
					'date'       => [ 'type' => 'string', 'description' => 'YYYY-MM-DD' ],
				],
				'required'   => [ 'service_id', 'date' ],
			],
		],
		[
			'name'         => 'suggest_booking',
			'description'  => 'Show a treatment card with Book and Details buttons for the treatment you recommend.',
			'input_schema' => [
				'type'       => 'object',
				'properties' => [ 'service_id' => [ 'type' => 'integer' ] ],
				'required'   => [ 'service_id' ],
			],
		],
		[
			'name'         => 'recommend_products',
			'description'  => 'Show 1 to 3 home-care product cards with Add to bag buttons.',
			'input_schema' => [
				'type'       => 'object',
				'properties' => [ 'slugs' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Product slugs from the list' ] ],
				'required'   => [ 'slugs' ],
			],
		],
	];
}

add_action( 'wp_ajax_skynco_chat', 'skynco_ai_chat' );
add_action( 'wp_ajax_nopriv_skynco_chat', 'skynco_ai_chat' );
function skynco_ai_chat() {
	check_ajax_referer( 'skynco_chat', 'nonce' );
	if ( ! skynco_ai_live() ) {
		wp_send_json_error( [ 'msg' => 'offline' ] );
	}
	$s  = skynco_ai_settings();
	$ip = md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$k1 = 'skynco_ai_ip_' . $ip;
	$k2 = 'skynco_ai_day_' . wp_date( 'Ymd' );
	if ( (int) get_transient( $k1 ) >= 40 || (int) get_transient( $k2 ) >= (int) $s['daily_cap'] ) {
		wp_send_json_success( [ 'text' => 'I have answered a lot of questions today. For anything else, text Hana at (857) 228-4708 and she will get back to you personally.', 'cards' => [] ] );
	}
	set_transient( $k1, (int) get_transient( $k1 ) + 1, HOUR_IN_SECONDS );
	set_transient( $k2, (int) get_transient( $k2 ) + 1, DAY_IN_SECONDS );

	$raw  = json_decode( wp_unslash( $_POST['messages'] ?? '[]' ), true );
	$msgs = [];
	foreach ( array_slice( (array) $raw, -16 ) as $m ) {
		$role = ( $m['role'] ?? '' ) === 'assistant' ? 'assistant' : 'user';
		$text = trim( mb_substr( wp_strip_all_tags( (string) ( $m['content'] ?? '' ) ), 0, 1500 ) );
		if ( '' === $text ) {
			continue;
		}
		if ( $msgs && end( $msgs )['role'] === $role ) {
			$msgs[ count( $msgs ) - 1 ]['content'] .= "\n" . $text;
		} else {
			$msgs[] = [ 'role' => $role, 'content' => $text ];
		}
	}
	while ( $msgs && 'user' !== $msgs[0]['role'] ) {
		array_shift( $msgs );
	}
	if ( ! $msgs || 'user' !== end( $msgs )['role'] ) {
		wp_send_json_error( [ 'msg' => 'empty' ] );
	}

	$GLOBALS['skynco_ai_kb_tmp'] = skynco_ai_knowledge();
	$system                      = skynco_ai_system_prompt();
	$cards                       = [];
	$notes                       = [];
	$text                        = '';
	for ( $round = 0; $round < 5; $round++ ) {
		$res  = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			[
				'timeout' => 45,
				'headers' => [ 'x-api-key' => $s['key'], 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json' ],
				'body'    => wp_json_encode( [ 'model' => $s['model'], 'max_tokens' => 700, 'system' => $system, 'tools' => skynco_ai_tools(), 'messages' => $msgs ] ),
			]
		);
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( is_wp_error( $res ) || empty( $body['content'] ) ) {
			update_option( 'skynco_ai_last_error', [ time(), is_wp_error( $res ) ? $res->get_error_message() : ( $body['error']['message'] ?? 'HTTP ' . wp_remote_retrieve_response_code( $res ) ) ], false );
			wp_send_json_success( [ 'text' => 'Sorry, I could not answer just now. Please try again, or text Hana at (857) 228-4708.', 'cards' => [] ] );
		}
		$results = [];
		foreach ( $body['content'] as $block ) {
			if ( 'text' === $block['type'] ) {
				$text .= ( $text ? "\n\n" : '' ) . trim( $block['text'] );
			} elseif ( 'tool_use' === $block['type'] ) {
				$in  = (array) $block['input'];
				$out = 'ok';
				if ( 'check_availability' === $block['name'] ) {
					$a = skynco_ai_availability_card( (int) ( $in['service_id'] ?? 0 ), (string) ( $in['date'] ?? '' ) );
					if ( isset( $a['card'] ) ) {
						$cards[] = $a['card'];
						$notes[] = '[Showed times] ' . $a['summary'];
						$out     = $a['summary'] . ' The times are now shown to the client as buttons.';
					} else {
						$out = $a['error'];
					}
				} elseif ( 'suggest_booking' === $block['name'] ) {
					$c = skynco_ai_service_card( (int) ( $in['service_id'] ?? 0 ) );
					if ( $c ) {
						$cards[] = $c;
						$notes[] = '[Showed booking card: ' . $c['name'] . ']';
						$out     = 'Card shown for ' . $c['name'] . '.';
					} else {
						$out = 'Unknown service id';
					}
				} elseif ( 'recommend_products' === $block['name'] ) {
					$c = skynco_ai_product_card( (array) ( $in['slugs'] ?? [] ) );
					if ( $c ) {
						$cards[] = $c;
						$notes[] = '[Showed products: ' . implode( ', ', wp_list_pluck( $c['items'], 'name' ) ) . ']';
						$out     = 'Product cards shown.';
					} else {
						$out = 'No matching products';
					}
				}
				$results[] = [ 'type' => 'tool_result', 'tool_use_id' => $block['id'], 'content' => $out ];
			}
		}
		if ( 'tool_use' !== ( $body['stop_reason'] ?? '' ) || ! $results ) {
			break;
		}
		$msgs[] = [ 'role' => 'assistant', 'content' => $body['content'] ];
		$msgs[] = [ 'role' => 'user', 'content' => $results ];
	}
	wp_send_json_success( [ 'text' => $text ?: 'Here you go.', 'cards' => $cards, 'memo' => implode( ' ', $notes ) ] );
}

/* Guided mode: live times without AI. */
add_action( 'wp_ajax_skynco_chat_slots', 'skynco_ai_slots_ajax' );
add_action( 'wp_ajax_nopriv_skynco_chat_slots', 'skynco_ai_slots_ajax' );
function skynco_ai_slots_ajax() {
	check_ajax_referer( 'skynco_chat', 'nonce' );
	$sid  = (int) ( $_POST['service'] ?? 0 );
	$date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) ) ?: wp_date( 'Y-m-d' );
	$a    = skynco_ai_availability_card( $sid, $date );
	wp_send_json_success( isset( $a['card'] ) ? [ 'card' => $a['card'] ] : [ 'card' => null ] );
}

/* Booking page: honour ?date= and ?time= from the assistant. */
add_filter(
	'do_shortcode_tag',
	function ( $output, $tag ) {
		if ( 'skynco_booking' !== $tag || empty( $_GET['date'] ) || ! shortcode_exists( 'latepoint_book_form' ) ) {
			return $output;
		}
		$date = sanitize_text_field( wp_unslash( $_GET['date'] ) );
		$time = isset( $_GET['time'] ) ? (int) $_GET['time'] : null;
		$sid  = isset( $_GET['service'] ) && function_exists( 'skynco_service_id' ) ? skynco_service_id( sanitize_text_field( wp_unslash( $_GET['service'] ) ) ) : 0;
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! $sid ) {
			return $output;
		}
		$attrs = ' selected_service="' . (int) $sid . '" selected_start_date="' . esc_attr( $date ) . '"' . ( null !== $time ? ' selected_start_time="' . (int) $time . '"' : '' ) . ' selected_agent="1" selected_location="1"';
		return '<div class="skynco-booking">' . do_shortcode( '[latepoint_book_form' . $attrs . ']' ) . '</div>';
	},
	10,
	2
);

/* ---------------------------------------------------------------------------
 * Admin: Studio → Skin Assistant
 * ------------------------------------------------------------------------ */
add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'skynco-studio', 'Skin Assistant', 'Skin Assistant', SKYNCO_STUDIO_CAP, 'skynco-assistant', 'skynco_page_assistant' );
	},
	32
);

add_action(
	'admin_post_skynco_ai_save',
	function () {
		if ( ! current_user_can( SKYNCO_STUDIO_CAP ) || ! check_admin_referer( 'skynco_ai' ) ) {
			wp_die( 'Not allowed' );
		}
		$old = skynco_ai_settings();
		$in  = wp_unslash( $_POST['ai'] ?? [] );
		$new = [
			'enabled'   => empty( $in['enabled'] ) ? '0' : '1',
			'key'       => trim( (string) ( $in['key'] ?? '' ) ) ?: $old['key'],
			'model'     => sanitize_text_field( $in['model'] ?? $old['model'] ) ?: 'claude-sonnet-5-5',
			'daily_cap' => (string) max( 10, (int) ( $in['daily_cap'] ?? 400 ) ),
		];
		if ( ! empty( $_POST['remove_key'] ) ) {
			$new['key'] = '';
		}
		update_option( 'skynco_ai', $new, false );
		delete_transient( 'skynco_ai_kb' );
		wp_safe_redirect( admin_url( 'admin.php?page=skynco-assistant&n=saved' ) );
		exit;
	}
);

function skynco_page_assistant() {
	$s   = skynco_ai_settings();
	$err = get_option( 'skynco_ai_last_error' );
	echo '<div class="wrap skynco-wrap"><h1>Skin Assistant</h1>';
	if ( 'saved' === ( $_GET['n'] ?? '' ) ) {
		echo '<div class="notice notice-success"><p>Saved.</p></div>';
	}
	echo '<div class="sk-card"><p>Mode: <b>' . ( skynco_ai_live() ? 'AI skin specialist (live)' : ( '1' === $s['enabled'] ? 'Guided consultation (add an API key for the full AI specialist)' : 'Off' ) ) . '</b></p>';
	echo '<p>The assistant knows every treatment, price, product and FAQ on the site, checks live availability and sends clients to booking with the treatment and time filled in.</p>';
	if ( $err ) {
		echo '<p style="color:#b32d2e">Last AI error (' . esc_html( wp_date( 'M j, g:i a', $err[0] ) ) . '): ' . esc_html( $err[1] ) . '</p>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'skynco_ai' );
	echo '<input type="hidden" name="action" value="skynco_ai_save"><table class="form-table">';
	echo '<tr><th>Show on the website</th><td><label><input type="checkbox" name="ai[enabled]" value="1"' . checked( $s['enabled'], '1', false ) . '> Show the "Free skin consult" button on every page</label></td></tr>';
	echo '<tr><th>Anthropic API key</th><td><input type="password" class="regular-text" name="ai[key]" placeholder="' . ( $s['key'] ? 'Saved. Leave blank to keep it.' : 'sk-ant-…' ) . '" autocomplete="off"> ' . ( $s['key'] ? '<label><input type="checkbox" name="remove_key" value="1"> Remove key</label>' : '' ) . '<p class="description">Create one at console.anthropic.com → API keys. Usage is billed by Anthropic (a consultation typically costs a few cents).</p></td></tr>';
	echo '<tr><th>Model</th><td><select name="ai[model]">';
	foreach ( [ 'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (recommended)', 'claude-haiku-5-5' => 'Claude Haiku 5.5 (fastest, lowest cost)', 'claude-opus-5-5' => 'Claude Opus 5.5 (most capable)' ] as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['model'], $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><th>Daily message limit</th><td><input name="ai[daily_cap]" value="' . esc_attr( $s['daily_cap'] ) . '" size="6"> <span class="description">Protects your API budget. Each visitor is also limited to 40 messages an hour.</span></td></tr>';
	echo '</table><p><button class="button button-primary">Save</button></p></form></div></div>';
}

/* ---------------------------------------------------------------------------
 * Front-end widget
 * ------------------------------------------------------------------------ */
function skynco_ai_guided_config() {
	$svc  = skynco_ai_services();
	$recs = function_exists( 'lux_shop_recs_for' ) ? 'lux_shop_recs_for' : null;
	$p    = function ( $slug ) use ( $recs ) {
		$c = skynco_ai_product_card( $recs ? array_slice( $recs( $slug ), 0, 3 ) : [] );
		return $c ? $c['items'] : [];
	};
	$card = function ( $id ) {
		return skynco_ai_service_card( $id );
	};
	$map  = [
		'acne'   => [ 'Breakouts or acne', 3, 13, 'acne-facial', 'The Acne Facial deep-cleans and calms breakouts with a purifying mask, blue LED and high frequency to target bacteria.' ],
		'bright' => [ 'Dull skin or dark spots', 4, 13, 'brightening-facial', 'The Brightening Facial uses enzymes to smooth and even out tone, with no downtime. For stubborn dark marks, a Chemical Peel series works beautifully.' ],
		'dry'    => [ 'Dryness or dehydration', 9, 10, 'glo2-facial', 'The Glo2 Facial floods the skin with hydration (OxFoliation, ultrasound serum infusion and LED) for an instant, lasting glow.' ],
		'lines'  => [ 'Fine lines, scars or texture', 11, 12, 'microneedling', 'Microneedling stimulates collagen to soften lines, scarring and texture over a short series. Dermaplaning is a gentler, no-downtime option.' ],
		'red'    => [ 'Redness or sensitivity', 2, 10, 'signature-facial', 'A customized Signature Facial lets Hana choose calming products for your skin, and you can add LED Light Therapy to soothe redness.' ],
		'men'    => [ 'Men’s skin or shaving bumps', 6, 2, 'gentlemans-facial', 'The Gentleman’s Facial is built for men’s skin: deep cleanse, extractions, cooling mask and a beard treatment for shaving irritation.' ],
		'glow'   => [ 'A relaxing glow-up', 9, 2, 'glo2-facial', 'The Glo2 Facial is our most loved glow treatment: relaxing, results-driven and great for every skin type.' ],
	];
	$out = [];
	foreach ( $map as $k => $m ) {
		$out[ $k ] = [ 'label' => $m[0], 'why' => $m[4], 'main' => $card( $m[1] ), 'alt' => $card( $m[2] ), 'products' => $p( $m[3] ) ];
	}
	$out['wax'] = [ 'label' => 'Waxing', 'why' => 'We offer quick, gentle waxing for the face and body. Pick the area below.', 'main' => $card( 17 ), 'alt' => $card( 20 ), 'products' => $p( 'underarm-wax' ) ];
	return [ 'concerns' => $out, 'newClient' => $card( 1 ) ];
}

add_action(
	'wp_footer',
	function () {
		$s = skynco_ai_settings();
		if ( '1' !== $s['enabled'] || is_admin() || ( function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() ) ) ) {
			return;
		}
		$cfg = [
			'ajax'   => admin_url( 'admin-ajax.php' ),
			'nonce'  => wp_create_nonce( 'skynco_chat' ),
			'ai'     => skynco_ai_live(),
			'today'  => wp_date( 'Y-m-d' ),
			'guided' => skynco_ai_live() ? null : skynco_ai_guided_config(),
		];
		?>
<div id="skc-bot" class="skb" data-open="0">
	<button type="button" class="skb-launch" aria-controls="skb-panel" aria-expanded="false"><span class="skb-launch__i"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3.5c.7 4.3 2.2 5.8 6.5 6.5-4.3.7-5.8 2.2-6.5 6.5-.7-4.3-2.2-5.8-6.5-6.5 4.3-.7 5.8-2.2 6.5-6.5z"/></svg></span><span class="skb-launch__t">Free skin consult</span></button>
	<section id="skb-panel" class="skb-panel" role="dialog" aria-label="Skyn&amp;Co. skin specialist" hidden>
		<header class="skb-head"><span class="skb-av">S</span><div><p class="skb-name">Skyn&amp;Co. Skin Specialist</p><p class="skb-sub"><span class="skb-dot"></span><?php echo skynco_ai_live() ? 'Virtual consultation · replies instantly' : 'Virtual consultation'; ?></p></div><button type="button" class="skb-x" aria-label="Close">×</button></header>
		<div class="skb-log" aria-live="polite"></div>
		<div class="skb-chips"></div>
		<form class="skb-form"><input type="text" class="skb-in" placeholder="Describe your skin or ask a question…" aria-label="Message" maxlength="600" autocomplete="off"><button type="submit" class="skb-send" aria-label="Send"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button></form>
		<p class="skb-fine">Skincare guidance, not medical advice. For anything urgent, see a doctor.</p>
	</section>
</div>
<script>window.SKB=<?php echo wp_json_encode( $cfg ); ?>;</script>
<script>
(function(){
	var C=window.SKB, root=document.getElementById('skc-bot'); if(!root) return;
	var panel=root.querySelector('.skb-panel'), log=root.querySelector('.skb-log'), chips=root.querySelector('.skb-chips'), form=root.querySelector('.skb-form'), input=root.querySelector('.skb-in'), launch=root.querySelector('.skb-launch');
	var KEY='skb_hist_v1', hist=[], started=false, st={};
	try{hist=JSON.parse(sessionStorage.getItem(KEY)||'[]');}catch(e){hist=[];}
	function save(){try{sessionStorage.setItem(KEY,JSON.stringify(hist.slice(-30)));}catch(e){}}
	function esc(s){return String(s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
	function fmt(t){var h=esc(t).split(/\n{2,}/).map(function(p){var lines=p.split('\n');if(lines.every(function(l){return /^\s*[-•]\s+/.test(l);})){return '<ul>'+lines.map(function(l){return '<li>'+l.replace(/^\s*[-•]\s+/,'')+'</li>';}).join('')+'</ul>';}return '<p>'+p.replace(/\n/g,'<br>')+'</p>';}).join('');return h;}
	function scroll(){log.scrollTop=log.scrollHeight;}
	function bubble(role,html){var d=document.createElement('div');d.className='skb-msg skb-msg--'+role;d.innerHTML=html;log.appendChild(d);scroll();return d;}
	function typing(){return bubble('bot','<span class="skb-typing"><i></i><i></i><i></i></span>');}
	function card(c){
		if(!c) return '';
		if(c.type==='service'){return '<div class="skb-card"><p class="skb-card__k">Recommended treatment</p><p class="skb-card__t">'+esc(c.name)+'</p><p class="skb-card__m">'+esc(c.meta)+'</p><div class="skb-card__a"><a class="skb-btn" href="'+esc(c.book)+'">Book this</a><a class="skb-btn skb-btn--ghost" href="'+esc(c.info)+'">Details</a>'+(C.ai?'':'<button type="button" class="skb-btn skb-btn--ghost" data-times="'+c.id+'">See open times</button>')+'</div></div>';}
		if(c.type==='products'){return '<div class="skb-prods">'+c.items.map(function(p){return '<div class="skb-prod">'+(p.img?'<img src="'+esc(p.img)+'" alt="">':'')+'<p class="skb-prod__t">'+esc(p.name)+'</p><p class="skb-prod__p">'+esc(p.price)+'</p><a class="skb-mini" href="'+esc(p.add)+'">Add to bag</a></div>';}).join('')+'</div>';}
		if(c.type==='slots'){if(!c.days.length) return '<div class="skb-card"><p class="skb-card__t">No open times in the next two weeks</p><p class="skb-card__m">Text Hana at (857) 228-4708 and she will fit you in.</p></div>';return '<div class="skb-card"><p class="skb-card__k">Open times · '+esc(c.service)+'</p>'+c.days.map(function(d){return '<p class="skb-day">'+esc(d.label)+'</p><div class="skb-times">'+d.times.map(function(t){return '<a href="'+esc(t.url)+'">'+esc(t.label)+'</a>';}).join('')+'</div>';}).join('')+'<p class="skb-card__m">Tap a time to finish booking. You only add your details.</p><a class="skb-link" href="'+esc(c.more)+'">See all dates</a></div>';}
		return '';
	}
	function setChips(list){chips.innerHTML=list.map(function(c){return '<button type="button" data-v="'+esc(c[0])+'">'+esc(c[1])+'</button>';}).join('');}
	function render(){log.innerHTML='';hist.forEach(function(m){if(m.role==='user'){bubble('me',fmt(m.content));}else{bubble('bot',fmt(m.content)+(m.cards||[]).map(card).join(''));}});}
	function greet(){
		var g='Hi, I’m the Skyn&Co. skin specialist. I can do a quick virtual consultation, recommend the right treatment and products, and help you book with Hana.';
		hist.push({role:'assistant',content:g});save();bubble('bot',fmt(g));
		if(C.ai){setChips([['ai:Hi! I’d like a skin consultation.','Start my skin consultation'],['ai:Which facial is right for me?','Which facial is right for me?'],['ai:I want to book an appointment.','Book an appointment'],['ai:Can you recommend products for my skin?','Recommend products']]);}
		else{guidedStart();}
	}
	function open(){root.dataset.open='1';panel.hidden=false;launch.setAttribute('aria-expanded','true');if(!started){started=true;if(hist.length){render();if(!C.ai&&!st.step)guidedStart(true);}else{greet();}}setTimeout(function(){input.focus();},50);}
	function close(){root.dataset.open='0';panel.hidden=true;launch.setAttribute('aria-expanded','false');}
	launch.addEventListener('click',function(){root.dataset.open==='1'?close():open();});
	root.querySelector('.skb-x').addEventListener('click',close);
	document.addEventListener('click',function(e){var a=e.target.closest('a[href="#skin-consult"],[data-skin-consult]');if(a){e.preventDefault();open();}});

	/* ---- AI mode ---- */
	function sendAI(text){
		hist.push({role:'user',content:text});save();bubble('me',fmt(text));chips.innerHTML='';
		var t=typing(), fd=new FormData();
		fd.append('action','skynco_chat');fd.append('nonce',C.nonce);
		fd.append('messages',JSON.stringify(hist.map(function(m){return {role:m.role,content:m.content+(m.memo?'\n'+m.memo:'')};})));
		fetch(C.ajax,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(r){
			t.remove(); var d=(r&&r.data)||{}; var txt=d.text||'Sorry, something went wrong. Please try again.';
			hist.push({role:'assistant',content:txt,cards:d.cards||[],memo:d.memo||''});save();
			bubble('bot',fmt(txt)+(d.cards||[]).map(card).join(''));
		}).catch(function(){t.remove();bubble('bot',fmt('I lost the connection. Please try again.'));});
	}

	/* ---- Guided mode ---- */
	var G=C.guided;
	function botSay(text,cards){hist.push({role:'assistant',content:text,cards:cards||[]});save();bubble('bot',fmt(text)+(cards||[]).map(card).join(''));}
	function guidedStart(resume){st={step:'concern'};if(!resume)botSay('What would you most like help with today?');setChips(Object.keys(G.concerns).map(function(k){return ['c:'+k,G.concerns[k].label];}).concat([['c:unsure','Not sure yet']]));}
	function guided(v,label){
		if(label){hist.push({role:'user',content:label});save();bubble('me',fmt(label));}
		chips.innerHTML='';
		if(v.indexOf('c:')===0){st.concern=v.slice(2);if(st.concern==='unsure'){st.concern='glow';}st.step='type';setTimeout(function(){botSay('Got it. How would you describe your skin?');setChips([['t:oily','Oily'],['t:dry','Dry'],['t:combo','Combination'],['t:normal','Normal'],['t:sensitive','Sensitive'],['t:unsure','Not sure']]);},350);return;}
		if(v.indexOf('t:')===0){st.type=v.slice(2);st.step='visit';setTimeout(function(){botSay('Have you visited Skyn&Co. before?');setChips([['v:new','This is my first visit'],['v:back','I’ve been before']]);},350);return;}
		if(v.indexOf('v:')===0){st.visit=v.slice(2);st.step='done';setTimeout(recommend,400);return;}
		if(v==='restart'){guidedStart();return;}
	}
	function recommend(){
		var c=G.concerns[st.concern]||G.concerns.glow, cards=[], txt=c.why;
		if(st.type==='sensitive'&&st.concern!=='red'){txt+=' Since your skin is sensitive, Hana will keep products gentle, and you can add LED Light Therapy to calm it.';}
		if(st.visit==='new'&&st.concern!=='wax'&&G.newClient){txt='Here’s what I recommend. As a first-time client, start with the New Client Facial & Consultation: a full facial plus a skin analysis and a plan made for you. '+c.why;cards.push(G.newClient);cards.push(c.main);}
		else{cards.push(c.main);}
		botSay(txt,cards);
		if(c.products&&c.products.length){setTimeout(function(){botSay('To protect your results at home, these are a great match:',[{type:'products',items:c.products}]);setChips([['restart','Start over'],['times','See open times']]);},500);}
		st.svc=(cards[0]||{}).id;
	}
	function times(sid){
		var t=typing(),fd=new FormData();fd.append('action','skynco_chat_slots');fd.append('nonce',C.nonce);fd.append('service',sid);fd.append('date',C.today);
		fetch(C.ajax,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(r){t.remove();var c=r&&r.data&&r.data.card;botSay(c?'Here are the next open times:':'I could not load times just now.',c?[c]:[]);}).catch(function(){t.remove();});
	}
	function guidedText(text){
		hist.push({role:'user',content:text});save();bubble('me',fmt(text));
		var s=text.toLowerCase(), k=null;
		if(/acne|breakout|pimple|spot|blemish|oily/.test(s))k='acne';else if(/dark|pigment|dull|uneven|melasma/.test(s))k='bright';else if(/dry|dehydrat|flaky|tight/.test(s))k='dry';else if(/line|wrinkle|scar|texture|aging|ageing|pores/.test(s))k='lines';else if(/red|sensitiv|rosacea|irritat/.test(s))k='red';else if(/men|beard|shav|ingrown/.test(s))k='men';else if(/wax|hair/.test(s))k='wax';else if(/book|appointment|time|available|slot/.test(s)){setTimeout(function(){botSay('Happy to help you book. Which treatment would you like? If you are not sure, tell me your main skin concern.');guidedStart(true);},300);return;}
		if(k){st.concern=k;st.step='type';setTimeout(function(){botSay('Thanks, that helps. How would you describe your skin?');setChips([['t:oily','Oily'],['t:dry','Dry'],['t:combo','Combination'],['t:normal','Normal'],['t:sensitive','Sensitive'],['t:unsure','Not sure']]);},350);}
		else{setTimeout(function(){botSay('I can help with breakouts, dark spots, dryness, fine lines, redness, men’s skin and waxing. Pick the closest one below, or text Hana at (857) 228-4708 for anything else.');guidedStart(true);},300);}
	}

	chips.addEventListener('click',function(e){var b=e.target.closest('button');if(!b)return;var v=b.dataset.v;
		if(v.indexOf('ai:')===0){sendAI(v.slice(3));return;}
		if(v==='times'){chips.innerHTML='';if(st.svc)times(st.svc);return;}
		guided(v,b.textContent);});
	log.addEventListener('click',function(e){var b=e.target.closest('[data-times]');if(b){e.preventDefault();times(b.dataset.times);}});
	form.addEventListener('submit',function(e){e.preventDefault();var t=input.value.trim();if(!t)return;input.value='';C.ai?sendAI(t):guidedText(t);});
	if(/[?&]consult=1/.test(location.search)) open();
})();
</script>
		<?php
	},
	70
);

add_action(
	'wp_head',
	function () {
		if ( '1' !== skynco_ai_settings()['enabled'] || ( function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() ) ) ) {
			return;
		}
		?>
<style id="skynco-bot">
.skb{position:fixed;right:20px;bottom:20px;z-index:99990;font-family:Manrope,sans-serif;color:#2A1A24}
.skb *{box-sizing:border-box}
.skb p{margin:0}
#ast-scroll-top{bottom:88px!important}
.skb-launch{display:flex;align-items:center;gap:10px;padding:10px 18px 10px 10px;border:0;border-radius:999px;background:#3B1530;color:#fff;font:700 14px Manrope,sans-serif;cursor:pointer;box-shadow:0 14px 30px -12px rgba(38,16,31,.6);transition:transform .2s}
.skb-launch:hover{transform:translateY(-2px)}
.skb-launch__i{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(135deg,#D1127E,#FF8CC8)}
.skb[data-open="1"] .skb-launch{display:none}
.skb-panel{position:absolute;right:0;bottom:0;width:390px;max-width:calc(100vw - 24px);height:min(640px,calc(100vh - 110px));display:flex;flex-direction:column;background:#FBF6F4;border-radius:26px;overflow:hidden;box-shadow:0 30px 70px -20px rgba(38,16,31,.55);border:1px solid #EFE2E6}
.skb-panel[hidden]{display:none}
.skb-head{display:flex;align-items:center;gap:12px;padding:14px 16px;background:radial-gradient(120% 160% at 100% 0%,#6A2453 0%,#3B1530 55%,#26101F 100%);color:#fff}
.skb-av{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(135deg,#D1127E,#FF8CC8);font:500 18px Fraunces,serif}
.skb-name{font:500 16px/1.2 Fraunces,serif}
.skb-sub{display:flex;align-items:center;gap:6px;font-size:12px;color:#EBD7E1;margin-top:2px!important}
.skb-dot{width:7px;height:7px;border-radius:50%;background:#5BE49B}
.skb-x{margin-left:auto;width:34px!important;height:34px!important;min-width:0!important;padding:0!important;border-radius:50%!important;border:0!important;background:rgba(255,255,255,.12)!important;color:#fff!important;font:400 22px/34px Manrope,sans-serif!important;text-align:center;cursor:pointer;box-shadow:none!important}
.skb-chips button,.skb-launch,.skb-send{box-shadow:none}
.skb-log{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px}
.skb-msg{max-width:88%;padding:11px 14px;border-radius:18px;font-size:14px;line-height:1.5}
.skb-msg p+p{margin-top:8px!important}
.skb-msg ul{margin:6px 0 0;padding-left:18px}
.skb-msg--bot{align-self:flex-start;background:#fff;border:1px solid #EFE2E6;border-bottom-left-radius:6px}
.skb-msg--me{align-self:flex-end;background:#D1127E;color:#fff;border-bottom-right-radius:6px}
.skb-typing{display:inline-flex;gap:4px}.skb-typing i{width:7px;height:7px;border-radius:50%;background:#D9BFCB;animation:skb 1s infinite}.skb-typing i:nth-child(2){animation-delay:.15s}.skb-typing i:nth-child(3){animation-delay:.3s}
@keyframes skb{0%,80%,100%{opacity:.3;transform:translateY(0)}40%{opacity:1;transform:translateY(-3px)}}
.skb-chips{display:flex;flex-wrap:wrap;gap:6px;padding:0 16px 10px}
.skb-chips:empty{display:none}
.skb-chips button{padding:8px 13px;border-radius:999px;border:1px solid #E2CCD4;background:#fff;color:#3B1530;font:600 13px Manrope,sans-serif;cursor:pointer}
.skb-chips button:hover{border-color:#D1127E;color:#D1127E}
.skb-form{display:flex;gap:8px;padding:10px 12px;background:#fff;border-top:1px solid #EFE2E6}
.skb-in{flex:1;min-width:0;height:44px;padding:0 14px;border:1px solid #E2CCD4!important;border-radius:999px!important;font:500 14px Manrope,sans-serif!important;background:#FBF6F4!important;color:#2A1A24}
.skb-in:focus{outline:0;border-color:#D1127E!important}
.skb-send{width:44px;height:44px;flex:0 0 44px;border-radius:50%;border:0;background:#D1127E;color:#fff;display:grid;place-items:center;cursor:pointer}
.skb-fine{padding:0 14px 10px;background:#fff;font-size:11px;color:#9A8791;text-align:center}
.skb-card{margin-top:10px;padding:12px;border-radius:14px;background:#FBF6F4;border:1px solid #F1E6E9}
.skb-card__k{font:700 10.5px/1.2 Manrope,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#D1127E}
.skb-card__t{margin-top:4px!important;font:500 17px/1.25 Fraunces,serif;color:#3B1530}
.skb-card__m{margin-top:3px!important;font-size:12.5px;color:#6E5A66}
.skb-card__a{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.skb-btn{display:inline-flex;align-items:center;padding:8px 14px;border-radius:999px;background:#D1127E;color:#fff!important;font:700 12.5px Manrope,sans-serif;text-decoration:none!important;border:1px solid #D1127E;cursor:pointer}
.skb-btn--ghost{background:#fff;color:#3B1530!important;border-color:#E2CCD4}
.skb-day{margin-top:10px!important;font:700 12.5px Manrope,sans-serif;color:#3B1530}
.skb-times{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
.skb-times a{padding:7px 11px;border-radius:10px;background:#fff;border:1px solid #E2CCD4;color:#3B1530!important;font:700 12.5px Manrope,sans-serif;text-decoration:none!important}
.skb-times a:hover{border-color:#D1127E;color:#D1127E!important}
.skb-link{display:inline-block;margin-top:8px;color:#D1127E!important;font:700 12.5px Manrope,sans-serif;text-decoration:none!important}
.skb-prods{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;margin-top:10px}
.skb-prod{padding:8px;border-radius:12px;background:#FBF6F4;border:1px solid #F1E6E9;display:flex;flex-direction:column;gap:3px}
.skb-prod img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px;background:#FBEFF0}
.skb-prod__t{font:600 11.5px/1.3 Manrope,sans-serif;color:#2A1A24}
.skb-prod__p{font:700 12px Manrope,sans-serif;color:#D1127E}
.skb-mini{margin-top:auto;padding:5px 0;border-radius:999px;background:#3B1530;color:#fff!important;font:700 11px Manrope,sans-serif;text-align:center;text-decoration:none!important}
@media(max-width:600px){
.skb{right:12px;bottom:12px}
.skb-launch{padding:8px 14px 8px 8px;font-size:13px}
.skb-launch__i{width:32px;height:32px}
.skb[data-open="1"]{left:0;right:0;bottom:0;top:0}
.skb-panel{position:fixed;inset:0;width:100%;max-width:none;height:100%;border-radius:0;border:0}
#ast-scroll-top{bottom:76px!important}
}
</style>
		<?php
	}
);
