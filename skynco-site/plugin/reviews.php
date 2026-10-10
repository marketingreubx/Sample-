<?php
/**
 * Product reviews: star ratings on product cards, a designed reviews section on
 * product pages (summary, rating bars, verified badge, review form), a review
 * request email after an order is completed, and labelled sample reviews for
 * previews that can be removed in one click (Studio → Product reviews).
 *
 * @package SkyncoStudio
 */

defined( 'ABSPATH' ) || exit;

function skynco_reviews_have_samples( $product_id = 0 ) {
	$args = [ 'type' => 'review', 'status' => 'approve', 'count' => true, 'meta_key' => 'skynco_sample', 'meta_value' => '1' ]; // phpcs:ignore
	if ( $product_id ) {
		$args['post_id'] = $product_id;
	}
	return (int) get_comments( $args ) > 0;
}

/* Product page: our reviews section replaces the default tab. */
add_filter(
	'woocommerce_product_tabs',
	function ( $tabs ) {
		unset( $tabs['reviews'] );
		return $tabs;
	},
	98
);

add_action( 'woocommerce_after_single_product_summary', 'skynco_reviews_section', 14 );
function skynco_reviews_section() {
	global $product;
	if ( ! $product || ! comments_open( $product->get_id() ) ) {
		return;
	}
	$pid     = $product->get_id();
	$reviews = get_comments( [ 'post_id' => $pid, 'type' => 'review', 'status' => 'approve', 'orderby' => 'comment_date_gmt', 'order' => 'DESC' ] );
	$count   = count( $reviews );
	$avg     = $count ? (float) $product->get_average_rating() : 0;
	$dist    = array_fill( 1, 5, 0 );
	foreach ( $reviews as $r ) {
		$dist[ max( 1, min( 5, (int) get_comment_meta( $r->comment_ID, 'rating', true ) ) ) ]++;
	}
	echo '<section class="skr" id="reviews"><div class="skr-head"><div><p class="skr-eyebrow">Reviews</p><h2>What clients say</h2></div><a class="skr-write" href="#skr-form">Write a review</a></div>';
	echo '<div class="skr-grid"><aside class="skr-sum">';
	if ( $count ) {
		echo '<p class="skr-avg">' . esc_html( number_format( $avg, 1 ) ) . '</p>' . skynco_stars_html( $avg ) . '<p class="skr-based">Based on ' . (int) $count . ' review' . ( 1 === $count ? '' : 's' ) . '</p><div class="skr-bars">'; // phpcs:ignore
		for ( $s = 5; $s >= 1; $s-- ) {
			$pc = $count ? round( $dist[ $s ] / $count * 100 ) : 0;
			echo '<div class="skr-bar"><span>' . (int) $s . '★</span><i><b style="width:' . (int) $pc . '%"></b></i><em>' . (int) $dist[ $s ] . '</em></div>';
		}
		echo '</div>';
		if ( skynco_reviews_have_samples( $pid ) ) {
			echo '<p class="skr-note">Sample reviews shown for preview.</p>';
		}
	} else {
		echo '<p class="skr-avg">–</p><p class="skr-based">No reviews yet. Be the first to share your results.</p>';
	}
	echo '</aside><div class="skr-list">';
	foreach ( array_slice( $reviews, 0, 12 ) as $r ) {
		$rating   = (int) get_comment_meta( $r->comment_ID, 'rating', true );
		$sample   = '1' === get_comment_meta( $r->comment_ID, 'skynco_sample', true );
		$verified = ! $sample && function_exists( 'wc_review_is_from_verified_owner' ) && wc_review_is_from_verified_owner( $r->comment_ID );
		$skin     = get_comment_meta( $r->comment_ID, 'skynco_skin', true );
		$title    = get_comment_meta( $r->comment_ID, 'skynco_title', true );
		$initial  = strtoupper( mb_substr( $r->comment_author, 0, 1 ) );
		echo '<article class="skr-item"><div class="skr-item__top">' . skynco_stars_html( $rating ) . '<time>' . esc_html( human_time_diff( strtotime( $r->comment_date_gmt . ' UTC' ) ) . ' ago' ) . '</time></div>'; // phpcs:ignore
		if ( $title ) {
			echo '<h3>' . esc_html( $title ) . '</h3>';
		}
		echo '<p class="skr-text">' . esc_html( wp_strip_all_tags( $r->comment_content ) ) . '</p>';
		echo '<div class="skr-who"><span class="skr-av">' . esc_html( $initial ) . '</span><b>' . esc_html( $r->comment_author ) . '</b>' . ( $skin ? '<span class="skr-skin">' . esc_html( $skin ) . '</span>' : '' ) . ( $verified ? '<span class="skr-ver">Verified buyer</span>' : '' ) . ( $sample ? '<span class="skr-sample">Sample</span>' : '' ) . '</div></article>';
	}
	echo '</div></div>';

	// Review form (WooCommerce saves the rating from the "rating" field).
	echo '<div class="skr-form" id="skr-form"><h3>Share your results</h3>';
	$user = wp_get_current_user();
	echo '<form action="' . esc_url( site_url( '/wp-comments-post.php' ) ) . '" method="post">';
	echo '<div class="skr-rate" role="radiogroup" aria-label="Your rating">';
	for ( $s = 5; $s >= 1; $s-- ) {
		echo '<input type="radio" id="skr-r' . (int) $s . '" name="rating" value="' . (int) $s . '" required><label for="skr-r' . (int) $s . '" title="' . (int) $s . ' stars">★</label>';
	}
	echo '</div>';
	if ( ! $user->ID ) {
		echo '<div class="skr-row"><label>Name<input name="author" required autocomplete="given-name"></label><label>Email (not published)<input type="email" name="email" required autocomplete="email"></label></div>';
	}
	echo '<label>Headline<input name="skynco_title" maxlength="80" placeholder="Sum it up in a few words"></label>';
	echo '<label>Your review<textarea name="comment" rows="4" required placeholder="How did it work for your skin?"></textarea></label>';
	echo '<input type="hidden" name="comment_post_ID" value="' . (int) $pid . '"><input type="hidden" name="comment_parent" value="0">';
	wp_comment_form_unfiltered_html_nonce();
	echo '<button type="submit" class="skr-submit">Submit review</button><p class="skr-small">Reviews are checked before they appear.</p></form></div></section>';
}

/* Ratings under the price on single product pages. */
add_action(
	'woocommerce_single_product_summary',
	function () {
		global $product;
		if ( $product && $product->get_review_count() ) {
			echo '<a class="skr-inline" href="#reviews">' . skynco_stars_html( $product->get_average_rating(), $product->get_review_count() ) . '</a>'; // phpcs:ignore
		}
	},
	6
);
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );

/* Review request email five days after an order is completed. */
add_action(
	'woocommerce_order_status_completed',
	function ( $order_id ) {
		if ( ! wp_next_scheduled( 'skynco_review_request', [ (int) $order_id ] ) ) {
			wp_schedule_single_event( time() + 5 * DAY_IN_SECONDS, 'skynco_review_request', [ (int) $order_id ] );
		}
	}
);
add_action(
	'skynco_review_request',
	function ( $order_id ) {
		$o = wc_get_order( $order_id );
		if ( ! $o || $o->get_meta( '_skynco_review_asked' ) ) {
			return;
		}
		$rows = '';
		foreach ( $o->get_items() as $it ) {
			$p = $it->get_product();
			if ( ! $p || has_term( 'gift-cards', 'product_cat', $p->get_id() ) ) {
				continue;
			}
			$img   = wp_get_attachment_image_url( $p->get_image_id(), 'thumbnail' );
			$rows .= '<tr><td style="padding:10px 0;width:72px"><img src="' . esc_url( $img ) . '" width="60" height="60" style="border-radius:10px;display:block" alt=""></td><td style="padding:10px 12px;font-family:Arial,sans-serif;font-size:15px;color:#2A1A24">' . esc_html( $p->get_name() ) . '</td><td style="padding:10px 0;text-align:right"><a href="' . esc_url( get_permalink( $p->get_id() ) . '#skr-form' ) . '" style="background:#D1127E;color:#fff;text-decoration:none;padding:9px 16px;border-radius:999px;font-family:Arial,sans-serif;font-weight:bold;font-size:13px">Review</a></td></tr>';
		}
		if ( ! $rows ) {
			return;
		}
		$body = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#2A1A24"><h2 style="font-family:Georgia,serif;color:#3B1530;font-weight:500">How is your skin loving it, ' . esc_html( $o->get_billing_first_name() ) . '?</h2><p>It has been a few days since your Skyn&amp;Co. order arrived. A quick review helps other clients choose the right products, and Hana reads every one.</p><table style="width:100%;border-collapse:collapse;margin:18px 0">' . $rows . '</table><p style="font-size:13px;color:#6E5A66">Thank you for supporting a small, local studio.</p></div>';
		wp_mail( $o->get_billing_email(), 'How is your skin loving it?', $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
		$o->update_meta_data( '_skynco_review_asked', time() );
		$o->save();
	}
);

/* Studio → Product reviews: overview and one-click removal of sample reviews. */
add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'skynco-studio', 'Product reviews', 'Product reviews', SKYNCO_STUDIO_CAP, 'skynco-reviews', 'skynco_page_reviews' );
	},
	33
);
add_action(
	'admin_post_skynco_remove_sample_reviews',
	function () {
		if ( ! current_user_can( SKYNCO_STUDIO_CAP ) || ! check_admin_referer( 'skynco_remove_samples' ) ) {
			wp_die( 'Not allowed' );
		}
		$ids = get_comments( [ 'type' => 'review', 'meta_key' => 'skynco_sample', 'meta_value' => '1', 'fields' => 'ids', 'status' => 'all' ] ); // phpcs:ignore
		$pids = [];
		foreach ( $ids as $id ) {
			$c      = get_comment( $id );
			$pids[] = (int) $c->comment_post_ID;
			wp_delete_comment( $id, true );
		}
		foreach ( array_unique( $pids ) as $pid ) {
			WC_Comments::clear_transients( $pid );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=skynco-reviews&removed=' . count( $ids ) ) );
		exit;
	}
);
function skynco_page_reviews() {
	$all     = (int) get_comments( [ 'type' => 'review', 'status' => 'approve', 'count' => true ] );
	$samples = (int) get_comments( [ 'type' => 'review', 'meta_key' => 'skynco_sample', 'meta_value' => '1', 'count' => true ] ); // phpcs:ignore
	$pending = (int) get_comments( [ 'type' => 'review', 'status' => 'hold', 'count' => true ] );
	echo '<div class="wrap skynco-wrap"><h1>Product reviews</h1>';
	if ( isset( $_GET['removed'] ) ) {
		echo '<div class="notice notice-success"><p>' . (int) $_GET['removed'] . ' sample reviews removed.</p></div>';
	}
	echo '<div class="sk-card"><p><b>' . ( $all - $samples ) . '</b> real reviews · <b>' . $samples . '</b> sample reviews · <b>' . $pending . '</b> waiting for approval. <a href="' . esc_url( admin_url( 'edit.php?post_type=product&page=product-reviews' ) ) . '">Manage reviews</a></p>';
	echo '<p>Clients are emailed a review request five days after their order is completed. New reviews wait for your approval before they appear.</p>';
	if ( $samples ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'skynco_remove_samples' );
		echo '<input type="hidden" name="action" value="skynco_remove_sample_reviews"><p><button class="button button-primary">Remove all sample reviews</button> <span class="description">Do this before the site goes live.</span></p></form>';
	}
	echo '</div></div>';
}

add_action(
	'wp_head',
	function () {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		?>
<style id="skynco-reviews">
.skr{clear:both;max-width:1200px;margin:56px auto 24px;font-family:Manrope,sans-serif;color:#2A1A24}
.skr *{box-sizing:border-box}.skr p{margin:0}
.skr-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:22px}
.skr-eyebrow{font:700 11px/1 Manrope,sans-serif;letter-spacing:.16em;text-transform:uppercase;color:#D1127E}
.skr h2{margin:8px 0 0!important;font:400 34px/1.15 Fraunces,serif!important;color:#3B1530!important}
.skr-write{padding:12px 20px;border-radius:999px;border:1px solid #3B1530;color:#3B1530!important;font:700 14px Manrope,sans-serif;text-decoration:none!important}
.skr-write:hover{background:#3B1530;color:#fff!important}
.skr-grid{display:grid;grid-template-columns:280px minmax(0,1fr);gap:24px;align-items:start}
.skr-sum{position:sticky;top:110px;background:#fff;border:1px solid #EFE2E6;border-radius:24px;padding:24px}
.skr-avg{font:400 56px/1 Fraunces,serif;color:#3B1530;margin-bottom:6px!important}
.skr-sum .sk-stars{font-size:20px}
.skr-based{margin-top:8px!important;font-size:13.5px;color:#6E5A66}
.skr-bars{margin-top:16px;display:flex;flex-direction:column;gap:7px}
.skr-bar{display:grid;grid-template-columns:26px 1fr 22px;align-items:center;gap:8px;font:600 12.5px Manrope,sans-serif;color:#6E5A66}
.skr-bar i{height:7px;border-radius:99px;background:#F4E7EB;overflow:hidden}.skr-bar b{display:block;height:100%;background:#D1127E;border-radius:99px}
.skr-bar em{font-style:normal;text-align:right}
.skr-note{margin-top:14px!important;font-size:11.5px;color:#9A8791}
.skr-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.skr-item{background:#fff;border:1px solid #EFE2E6;border-radius:20px;padding:20px;display:flex;flex-direction:column;gap:8px}
.skr-item__top{display:flex;justify-content:space-between;align-items:center}
.skr-item__top time{font-size:12px;color:#9A8791}
.skr-item h3{margin:2px 0 0!important;font:600 16px/1.35 Manrope,sans-serif!important;color:#2A1A24!important}
.skr-text{font-size:14.5px;line-height:1.6;color:#4A3843}
.skr-who{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin-top:auto;padding-top:6px;font-size:13px}
.skr-av{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(135deg,#D1127E,#7A1E5E);color:#fff;font:600 13px Fraunces,serif}
.skr-skin,.skr-ver,.skr-sample{padding:3px 9px;border-radius:99px;font-size:11.5px;font-weight:700}
.skr-skin{background:#FBF1F4;color:#6E5A66}.skr-ver{background:#E9F5EE;color:#24704A}.skr-sample{background:#F3F0F1;color:#9A8791}
.skr-form{margin-top:24px;background:#FBF6F4;border:1px solid #EFE2E6;border-radius:24px;padding:24px}
.skr-form h3{margin:0 0 12px!important;font:400 24px Fraunces,serif!important;color:#3B1530!important}
.skr-form form{display:flex;flex-direction:column;gap:12px}
.skr-form label{display:flex;flex-direction:column;gap:6px;font:700 12.5px Manrope,sans-serif;color:#3B1530}
.skr-form input:not([type=radio]),.skr-form textarea{padding:12px 14px;border:1px solid #E2CCD4;border-radius:14px;font:500 15px Manrope,sans-serif;background:#fff}
.skr-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.skr-rate{display:inline-flex;flex-direction:row-reverse;justify-content:flex-end;gap:4px}
.skr-rate input{position:absolute;opacity:0;width:1px;height:1px}
.skr-rate label{font-size:30px!important;line-height:1;color:#E2CCD4!important;cursor:pointer}
.skr-rate input:checked~label,.skr-rate label:hover,.skr-rate label:hover~label{color:#D1127E!important}
.skr-submit{align-self:flex-start;padding:13px 24px;border-radius:999px;border:0;background:#D1127E;color:#fff;font:700 14.5px Manrope,sans-serif;cursor:pointer}
.skr-small{font-size:12px;color:#9A8791}
.skr-inline{display:inline-flex;align-items:center;margin:4px 0 10px;text-decoration:none!important}
@media(max-width:900px){.skr-grid{grid-template-columns:1fr}.skr-sum{position:static}.skr-list{grid-template-columns:1fr}}
@media(max-width:600px){.skr{margin-top:36px}.skr h2{font-size:28px!important}.skr-row{grid-template-columns:1fr}.skr-head{flex-direction:column;align-items:flex-start}}
</style>
		<?php
	}
);

/* [skynco_review_band] — latest reviews across the shop. */
add_shortcode(
	'skynco_review_band',
	function ( $a ) {
		$a       = shortcode_atts( [ 'limit' => 3 ], $a );
		$reviews = get_comments( [ 'type' => 'review', 'status' => 'approve', 'post_type' => 'product', 'number' => 30, 'orderby' => 'comment_date_gmt', 'order' => 'DESC' ] );
		$picked  = [];
		$seen    = [];
		foreach ( $reviews as $r ) {
			if ( (int) get_comment_meta( $r->comment_ID, 'rating', true ) < 5 || isset( $seen[ $r->comment_post_ID ] ) ) {
				continue;
			}
			$seen[ $r->comment_post_ID ] = 1;
			$picked[]                    = $r;
			if ( count( $picked ) >= (int) $a['limit'] ) {
				break;
			}
		}
		if ( ! $picked ) {
			return '';
		}
		$total = (int) get_comments( [ 'type' => 'review', 'status' => 'approve', 'post_type' => 'product', 'count' => true ] );
		$html  = '<div class="skrb"><div class="skrb-grid">';
		$sample = false;
		foreach ( $picked as $r ) {
			$p = wc_get_product( $r->comment_post_ID );
			if ( ! $p ) {
				continue;
			}
			$sample = $sample || '1' === get_comment_meta( $r->comment_ID, 'skynco_sample', true );
			$title  = get_comment_meta( $r->comment_ID, 'skynco_title', true );
			$skin   = get_comment_meta( $r->comment_ID, 'skynco_skin', true );
			$img    = wp_get_attachment_image( $p->get_image_id(), 'thumbnail', false, [ 'loading' => 'lazy', 'alt' => '' ] );
			$html  .= '<figure class="skrb-card">' . skynco_stars_html( 5 ) . ( $title ? '<h3>' . esc_html( $title ) . '</h3>' : '' ) . '<blockquote>' . esc_html( wp_trim_words( wp_strip_all_tags( $r->comment_content ), 34 ) ) . '</blockquote>'
				. '<figcaption><b>' . esc_html( $r->comment_author ) . '</b>' . ( $skin ? ' · ' . esc_html( $skin ) : '' ) . '</figcaption>'
				. '<a class="skrb-prod" href="' . esc_url( get_permalink( $p->get_id() ) . '#reviews' ) . '">' . $img . '<span>' . esc_html( $p->get_name() ) . '</span></a></figure>';
		}
		$html .= '</div><p class="skrb-foot">' . (int) $total . ' product reviews' . ( $sample ? ' · sample reviews shown for preview' : '' ) . '</p></div>';
		return $html;
	}
);

/**
 * Seeds clearly labelled sample reviews so the design can be previewed.
 * Every sample carries skynco_sample=1, shows a "Sample" tag and can be removed
 * from Studio → Product reviews.
 */
function skynco_seed_sample_reviews() {
	$data = [
		'gentle-cleansing-gel'          => [ [ 'Amara O.', 5, 'Oily, sensitive', 'Gentle but actually cleans', 'Takes off sunscreen without that tight feeling. My skin feels calm after, even on retinol nights.' ], [ 'Jess M.', 5, 'Combination', 'My forever cleanser', 'Hana recommended this after my first facial. Three bottles in and my T-zone is so much clearer.' ], [ 'Priya S.', 4, 'Normal', 'Lovely, lasts ages', 'A little goes a long way. Fresh scent, not overpowering.' ] ],
		'clarifying-spot-treatment'     => [ [ 'Tasha R.', 5, 'Acne-prone', 'Fewer breakouts in a month', 'I use it every evening and my chin breakouts have calmed down a lot. Not drying at all.' ], [ 'Nora K.', 4, 'Oily', 'Works, start slowly', 'Great for congestion. I use it every other day at first and that was perfect.' ] ],
		'vitamin-c-brightening-serum'   => [ [ 'Leila H.', 5, 'Dull, uneven tone', 'My skin glows', 'Dark spots from old breakouts are fading and my skin looks brighter in photos. Worth every penny.' ], [ 'Grace W.', 5, 'Dry', 'Hydrating and bright', 'Not sticky at all, layers nicely under SPF. Friends keep asking what I changed.' ], [ 'Dana P.', 5, 'Combination', 'Best vitamin C I have tried', 'No sting, no orange smell. I saw a difference within two weeks.' ], [ 'Mia L.', 4, 'Normal', 'Pricey but effective', 'Results are real. I just wish the bottle were bigger.' ] ],
		'hydrating-hyaluronic-serum'    => [ [ 'Sofia A.', 5, 'Dehydrated', 'Like a drink of water', 'Cooling the second it goes on. I keep it for after peels and it calms the redness straight away.' ], [ 'Ruth B.', 5, 'Sensitive', 'Soothing after treatments', 'Hana used this after my facial and I had to buy it. My skin never feels tight now.' ] ],
		'barrier-repair-moisturizer'    => [ [ 'Chloe D.', 5, 'Dry', 'Soft skin all day', 'Rich without being greasy. My dry patches disappeared in a week.' ], [ 'Hannah F.', 4, 'Combination', 'Great for winter', 'I use it at night in summer and day and night in winter. Very comforting.' ] ],
		'daily-mineral-spf-40'          => [ [ 'Yasmin T.', 5, 'Acne-prone', 'An SPF I actually wear', 'No white cast, no breakouts, sits well under makeup. Finally found the one.' ], [ 'Kelly N.', 5, 'Rosacea', 'Calms my redness', 'Lightweight and it never stings. My cheeks look less red with it on.' ], [ 'Ava G.', 5, 'Oily', 'No shine', 'Dries down matte and lasts. I reapply over makeup with no pilling.' ] ],
		'enzyme-exfoliating-mask'       => [ [ 'Zoe C.', 5, 'Congested', 'Instantly smoother', 'Gentle enough to use daily and my pores look smaller. Skin feels so soft.' ], [ 'Isabel R.', 4, 'Sensitive', 'Gentle exfoliation', 'I use it three times a week. No irritation and my makeup goes on better.' ] ],
		'post-treatment-recovery-balm'  => [ [ 'Maya J.', 5, 'Sensitive', 'A must after peels', 'Healed so much faster after my chemical peel. Hana was right about this one.' ], [ 'Olivia E.', 5, 'Dry', 'Rescue in a tube', 'Also amazing on dry patches and my lips. Comforting and not greasy.' ] ],
		'glow-kit'                      => [ [ 'Rachel V.', 5, 'Dull skin', 'Everything I needed', 'Took the guesswork out of my routine. Saved money compared to buying each one.' ], [ 'Imani S.', 5, 'Combination', 'Lovely gift', 'Bought it for my sister and then got one for myself. Beautifully packed.' ] ],
		'clear-skin-kit'                => [ [ 'Ella M.', 5, 'Acne-prone', 'Clearer in six weeks', 'Simple routine, real results. My breakouts are mostly gone.' ] ],
		'recovery-kit'                  => [ [ 'Nadia F.', 5, 'Post-treatment', 'Perfect after microneedling', 'Exactly what my skin needed between treatments. Hana put it together for me.' ] ],
	];
	$n = 0;
	foreach ( $data as $slug => $rows ) {
		$pid = function_exists( 'skynco_shop_id' ) ? skynco_shop_id( $slug ) : 0;
		if ( ! $pid || skynco_reviews_have_samples( $pid ) ) {
			continue;
		}
		foreach ( $rows as $i => $r ) {
			$when = gmdate( 'Y-m-d H:i:s', time() - ( 6 + $i * 11 + $n % 7 ) * DAY_IN_SECONDS );
			$cid  = wp_insert_comment(
				[
					'comment_post_ID'      => $pid,
					'comment_author'       => $r[0],
					'comment_author_email' => 'sample-' . $n . '@example.invalid',
					'comment_content'      => $r[4],
					'comment_type'         => 'review',
					'comment_approved'     => 1,
					'comment_date'         => get_date_from_gmt( $when ),
					'comment_date_gmt'     => $when,
					'comment_meta'         => [ 'rating' => $r[1], 'skynco_sample' => '1', 'skynco_skin' => $r[2], 'skynco_title' => $r[3], 'verified' => 0 ],
				]
			);
			$n += $cid ? 1 : 0;
		}
		WC_Comments::clear_transients( $pid );
	}
	return $n;
}

/* Saves a review title / skin type submitted from the review form. */
add_action(
	'comment_post',
	function ( $cid ) {
		if ( 'review' === get_comment_type( $cid ) && ! empty( $_POST['skynco_title'] ) ) { // phpcs:ignore
			update_comment_meta( $cid, 'skynco_title', sanitize_text_field( wp_unslash( $_POST['skynco_title'] ) ) ); // phpcs:ignore
		}
	}
);
