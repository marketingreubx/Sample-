<?php
/**
 * Skyn&Co. Studio – growth features:
 * email list (popup + welcome email, synced to Hostinger Reach), contact form inbox,
 * and before/after comparison sliders for treatment pages.
 */
defined( 'ABSPATH' ) || exit;

/* ===========================================================================
 * Storage
 * ======================================================================== */
function skynco_subscribers_table() {
	global $wpdb;
	return $wpdb->prefix . 'skynco_subscribers';
}

add_action(
	'init',
	function () {
		if ( get_option( 'skynco_growth_db' ) !== '1' ) {
			global $wpdb;
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta(
				'CREATE TABLE ' . skynco_subscribers_table() . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				email varchar(190) NOT NULL,
				name varchar(190) NOT NULL DEFAULT '',
				source varchar(40) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY email (email)
			) {$wpdb->get_charset_collate()};"
			);
			update_option( 'skynco_growth_db', '1' );
		}
		register_post_type(
			'skynco_message',
			[
				'label'         => 'Messages',
				'labels'        => [ 'name' => 'Messages', 'singular_name' => 'Message', 'all_items' => 'Messages' ],
				'public'        => false,
				'show_ui'       => true,
				'show_in_menu'  => 'skynco-studio',
				'supports'      => [ 'title', 'editor' ],
				'capability_type' => 'post',
				'capabilities'  => [ 'create_posts' => 'do_not_allow' ],
				'map_meta_cap'  => true,
			]
		);
	}
);

/** Add someone to the list (idempotent). Returns true when they are new. */
function skynco_subscribe( $email, $name = '', $source = 'popup' ) {
	global $wpdb;
	$email = sanitize_email( $email );
	if ( ! is_email( $email ) ) {
		return false;
	}
	$new = (bool) $wpdb->query(
		$wpdb->prepare(
			'INSERT IGNORE INTO ' . skynco_subscribers_table() . ' (email, name, source, created_at) VALUES (%s, %s, %s, %s)',
			$email,
			sanitize_text_field( $name ),
			sanitize_key( $source ),
			current_time( 'mysql' )
		)
	);
	// Hostinger Reach picks this up when it is connected (Reach → Contacts).
	$parts = explode( ' ', trim( (string) $name ), 2 );
	do_action( 'hostinger_reach_submit', [ 'group' => 'skynco-' . sanitize_key( $source ), 'email' => $email, 'name' => $parts[0] ?? '', 'surname' => $parts[1] ?? '', 'metadata' => [ 'plugin' => 'skynco-studio' ] ] );
	if ( $new ) {
		skynco_send_welcome( $email, $parts[0] ?? '' );
	}
	return $new;
}

/** The free gift: Hana's at-home glow routine, plus the first-order code. */
function skynco_send_welcome( $email, $first = '' ) {
	$hi    = $first ? 'Hi ' . esc_html( $first ) . ',' : 'Hi there,';
	$shop  = home_url( '/shop/' );
	$book  = home_url( '/book/?service=new-client-facial' );
	$steps = [
		[ 'Cleanse gently, twice a day', 'Use a low-foam gel morning and night. Skin should feel soft, never tight or squeaky.' ],
		[ 'Treat in the morning with vitamin C', 'A few drops after cleansing brightens dull skin and helps fade dark marks over time.' ],
		[ 'Hydrate while skin is damp', 'Hyaluronic serum works best on damp skin, so apply it within a minute of washing.' ],
		[ 'Seal it in with a barrier moisturizer', 'Ceramides keep water in and irritants out. Your other products work better on a healthy barrier.' ],
		[ 'Finish with SPF 30+, every single day', 'Sun damage undoes facial results faster than anything else. Reapply when you are outdoors.' ],
	];
	$list = '';
	foreach ( $steps as $i => $s ) {
		$list .= '<tr><td style="vertical-align:top;padding:10px 14px 10px 0;font:500 26px Georgia,serif;color:#D1127E">' . ( $i + 1 ) . '</td><td style="padding:10px 0;border-bottom:1px solid #EADDE0"><b style="color:#3B1530">' . $s[0] . '</b><br><span style="color:#6E5A66">' . $s[1] . '</span></td></tr>';
	}
	$body = '<div style="background:#FFF8F6;padding:32px 16px;font-family:Arial,sans-serif;color:#2A1A24"><div style="max-width:560px;margin:0 auto;background:#fff;border-radius:20px;padding:32px">'
		. '<p style="margin:0 0 4px;color:#D1127E;font-weight:bold;letter-spacing:.1em;font-size:12px">SKYN&amp;CO. SKINCARE &amp; WELLNESS</p>'
		. '<h1 style="font:500 28px Georgia,serif;color:#3B1530;margin:0 0 16px">Your free at-home glow routine</h1>'
		. '<p>' . $hi . '</p><p>Thank you for joining the Skyn&amp;Co. list. Here is the 5-step routine Hana recommends to most clients between facials:</p>'
		. '<table style="width:100%;border-collapse:collapse;margin:12px 0 20px">' . $list . '</table>'
		. '<div style="background:#3B1530;color:#F3DCE8;border-radius:16px;padding:20px;text-align:center"><p style="margin:0 0 6px">Your welcome gift</p><p style="margin:0 0 6px;font:500 26px Georgia,serif;color:#fff">10% off your first order</p><p style="margin:0 0 14px">Use code <b style="color:#FF8CC8">GLOW10</b> at checkout.</p>'
		. '<a href="' . esc_url( $shop ) . '" style="display:inline-block;background:#D1127E;color:#fff;text-decoration:none;padding:12px 22px;border-radius:999px;font-weight:bold">Shop home care</a></div>'
		. '<p style="margin-top:20px">Want a routine written for your skin? <a href="' . esc_url( $book ) . '" style="color:#D1127E">Book the New Client Facial &amp; Consultation</a> and Hana will build one with you.</p>'
		. '<p style="color:#6E5A66;font-size:12px;margin-top:24px">150 Arsenal St, Suite 210, Watertown, MA 02472 · (857) 228-4708<br>You are receiving this because you signed up at ' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '. Reply "unsubscribe" to be removed.</p>'
		. '</div></div>';
	return wp_mail( $email, 'Your free glow routine (and 10% off) from Skyn&Co.', $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
}

/* ===========================================================================
 * Popup: free glow routine + 10% off in exchange for an email
 * ======================================================================== */
function skynco_rate_ok( $key ) {
	$ip = preg_replace( '/[^0-9a-f:.]/i', '', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$t  = 'skynco_rl_' . $key . '_' . md5( $ip );
	$n  = (int) get_transient( $t );
	if ( $n >= 5 ) {
		return false;
	}
	set_transient( $t, $n + 1, HOUR_IN_SECONDS );
	return true;
}

add_action( 'wp_ajax_skynco_join', 'skynco_ajax_join' );
add_action( 'wp_ajax_nopriv_skynco_join', 'skynco_ajax_join' );
function skynco_ajax_join() {
	// No nonce: pages are cached. Honeypot + per-IP rate limit instead.
	if ( ! empty( $_POST['website'] ) ) {
		wp_send_json_success();
	}
	if ( ! skynco_rate_ok( 'join' ) ) {
		wp_send_json_error( [ 'message' => 'Too many tries. Please try again later.' ], 429 );
	}
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	if ( ! is_email( $email ) ) {
		wp_send_json_error( [ 'message' => 'Please enter a valid email address.' ], 400 );
	}
	skynco_subscribe( $email, sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ), sanitize_key( $_POST['source'] ?? 'popup' ) );
	wp_send_json_success( [ 'message' => 'You’re in! Check your inbox for your free glow routine and your 10% code.' ] );
}

add_action(
	'wp_footer',
	function () {
		if ( is_admin() || ( function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() || is_account_page() ) ) || is_page( [ 'book', 'my-bookings' ] ) ) {
			return;
		}
		$img = wp_get_attachment_image_url( (int) ( get_option( 'skynco_media_ids', [] )['skynco-peel-mask.png'] ?? 0 ), 'medium_large' );
		?>
<div class="skp" id="skp" role="dialog" aria-modal="true" aria-labelledby="skp-title" hidden>
	<div class="skp__backdrop" data-skp-close></div>
	<div class="skp__box">
		<button class="skp__x" type="button" aria-label="Close" data-skp-close>&times;</button>
		<?php if ( $img ) : ?><div class="skp__img" style="background-image:url('<?php echo esc_url( $img ); ?>')"></div><?php endif; ?>
		<div class="skp__body">
			<p class="skp__eyebrow">Free for new subscribers</p>
			<h2 id="skp-title">Get Hana’s at-home glow routine, <em>free.</em></h2>
			<p class="skp__text">The 5-step routine our esthetician gives clients between facials, plus <b>10% off</b> your first order.</p>
			<form class="skp__form" novalidate>
				<input type="text" name="name" placeholder="First name" autocomplete="given-name" aria-label="First name">
				<input type="email" name="email" placeholder="Email address" autocomplete="email" aria-label="Email address" required>
				<input type="text" name="website" tabindex="-1" autocomplete="off" class="skp__hp" aria-hidden="true">
				<button type="submit">Send me the free routine</button>
				<p class="skp__msg" role="status" aria-live="polite"></p>
			</form>
			<p class="skp__fine">No spam. Unsubscribe any time.</p>
		</div>
	</div>
</div>
<script>
(function(){
	var el=document.getElementById('skp'); if(!el) return;
	var KEY='skynco_popup', ajax=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	function get(){try{return JSON.parse(localStorage.getItem(KEY)||'{}')}catch(e){return {}}}
	function set(v){try{localStorage.setItem(KEY,JSON.stringify(v))}catch(e){}}
	var st=get(); if(st.joined||(st.closed&&Date.now()-st.closed<14*864e5)) return;
	var shown=false, last=null;
	function open(){ if(shown) return; shown=true; last=document.activeElement; el.hidden=false; requestAnimationFrame(function(){el.classList.add('is-open')}); var f=el.querySelector('input[name=name]'); setTimeout(function(){f&&f.focus()},250); }
	function close(){ el.classList.remove('is-open'); setTimeout(function(){el.hidden=true},250); set({closed:Date.now()}); last&&last.focus&&last.focus(); }
	el.addEventListener('click',function(e){ if(e.target.closest('[data-skp-close]')) close(); });
	document.addEventListener('keydown',function(e){ if(e.key==='Escape'&&!el.hidden) close(); });
	setTimeout(open, 12000);
	window.addEventListener('scroll',function(){ var d=document.documentElement; if((d.scrollTop+innerHeight)/d.scrollHeight>.55) open(); },{passive:true});
	document.addEventListener('mouseout',function(e){ if(!e.relatedTarget&&e.clientY<8) open(); });
	document.querySelectorAll('[data-skp-open]').forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();open();})});
	el.querySelector('form').addEventListener('submit',function(e){
		e.preventDefault(); var f=e.target, msg=f.querySelector('.skp__msg'), btn=f.querySelector('button');
		if(!/^\S+@\S+\.\S+$/.test(f.email.value)){ msg.textContent='Please enter a valid email address.'; f.email.focus(); return; }
		btn.disabled=true; btn.textContent='Sending…';
		var d=new FormData(f); d.append('action','skynco_join'); d.append('source','popup');
		fetch(ajax,{method:'POST',body:d,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){
			msg.textContent=(r.data&&r.data.message)||'Thank you!';
			if(r.success){ set({joined:1}); f.querySelectorAll('input,button').forEach(function(i){i.hidden=true}); setTimeout(close,4000); }
			else { btn.disabled=false; btn.textContent='Send me the free routine'; }
		}).catch(function(){ msg.textContent='Something went wrong. Please try again.'; btn.disabled=false; btn.textContent='Send me the free routine'; });
	});
})();
</script>
		<?php
	},
	50
);

/* ===========================================================================
 * Contact form: [skynco_contact_form]
 * ======================================================================== */
add_shortcode(
	'skynco_contact_form',
	function () {
		$topics = [ 'Choosing a treatment', 'An existing booking', 'Products and orders', 'Gift cards', 'Something else' ];
		$sent   = isset( $_GET['sent'] ) ? sanitize_key( $_GET['sent'] ) : '';
		ob_start();
		?>
<form class="skc" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php if ( 'ok' === $sent ) : ?><p class="skc__ok" role="status">Thank you! Your message is on its way. Hana will reply within one business day.</p><?php elseif ( 'err' === $sent ) : ?><p class="skc__err" role="alert">Please add your name, a valid email and a message, then try again.</p><?php endif; ?>
	<input type="hidden" name="action" value="skynco_contact">
	<div class="skc__row">
		<label>Name<input type="text" name="name" required autocomplete="name"></label>
		<label>Email<input type="email" name="email" required autocomplete="email"></label>
	</div>
	<div class="skc__row">
		<label>Phone (optional)<input type="tel" name="phone" autocomplete="tel"></label>
		<label>What is it about?<select name="topic"><?php foreach ( $topics as $t ) : ?><option><?php echo esc_html( $t ); ?></option><?php endforeach; ?></select></label>
	</div>
	<label>Message<textarea name="message" rows="5" required></textarea></label>
	<input type="text" name="website" tabindex="-1" autocomplete="off" class="skp__hp" aria-hidden="true">
	<label class="skc__check"><input type="checkbox" name="join" value="1" checked> Send me Hana’s free glow routine and occasional offers</label>
	<button type="submit">Send message</button>
</form>
		<?php
		return ob_get_clean();
	}
);

add_action( 'admin_post_nopriv_skynco_contact', 'skynco_handle_contact' );
add_action( 'admin_post_skynco_contact', 'skynco_handle_contact' );
function skynco_handle_contact() {
	$back  = wp_get_referer() ? remove_query_arg( 'sent', wp_get_referer() ) : home_url( '/contact/' );
	$name  = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$msg   = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
	if ( ! empty( $_POST['website'] ) ) {
		wp_safe_redirect( add_query_arg( 'sent', 'ok', $back ) . '#contact-form' );
		exit;
	}
	if ( ! $name || ! is_email( $email ) || ! $msg || ! skynco_rate_ok( 'contact' ) ) {
		wp_safe_redirect( add_query_arg( 'sent', 'err', $back ) . '#contact-form' );
		exit;
	}
	$phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
	$topic = sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) );
	$id    = wp_insert_post(
		[
			'post_type'    => 'skynco_message',
			'post_status'  => 'private',
			'post_title'   => $name . ' · ' . $topic,
			'post_content' => $msg . "\n\n— " . $name . ' · ' . $email . ( $phone ? ' · ' . $phone : '' ),
		]
	);
	update_post_meta( $id, '_email', $email );
	update_post_meta( $id, '_phone', $phone );
	wp_mail( get_option( 'admin_email' ), 'New website message: ' . $topic, $msg . "\n\nFrom: $name <$email> $phone\n\nView all messages: " . admin_url( 'edit.php?post_type=skynco_message' ), [ 'Reply-To: ' . $name . ' <' . $email . '>' ] );
	if ( ! empty( $_POST['join'] ) ) {
		skynco_subscribe( $email, $name, 'contact' );
	}
	wp_safe_redirect( add_query_arg( 'sent', 'ok', $back ) . '#contact-form' );
	exit;
}

/* ===========================================================================
 * Before & after: [skynco_before_after slug="microneedling"]
 * Real client photos only. Nothing shows publicly until photos are added.
 * ======================================================================== */
function skynco_ba_pairs( $slug ) {
	$all = get_option( 'skynco_before_after', [] );
	return array_values( array_filter( (array) ( $all[ $slug ] ?? [] ), fn( $p ) => ! empty( $p['before'] ) && ! empty( $p['after'] ) ) );
}

add_shortcode(
	'skynco_before_after',
	function ( $atts ) {
		$slug  = sanitize_title( $atts['slug'] ?? '' );
		$pairs = skynco_ba_pairs( $slug );
		if ( ! $pairs ) {
			if ( current_user_can( SKYNCO_STUDIO_CAP ) ) {
				return '<div class="skba-block"><div class="skba-empty"><b>Only you can see this:</b> add real before &amp; after photos for this treatment in <a href="' . esc_url( admin_url( 'admin.php?page=skynco-before-after#' . $slug ) ) . '">Studio → Before &amp; After</a> and the slider will appear here.</div></div>';
			}
			return '';
		}
		$out = '<div class="skba-grid skba-n' . count( $pairs ) . '">';
		foreach ( $pairs as $p ) {
			$b    = wp_get_attachment_image_url( (int) $p['before'], 'large' );
			$a    = wp_get_attachment_image_url( (int) $p['after'], 'large' );
			$out .= '<figure class="skba"><div class="skba__frame" style="--pos:50%">'
				. '<img class="skba__after" src="' . esc_url( $a ) . '" alt="After treatment" loading="lazy">'
				. '<img class="skba__before" src="' . esc_url( $b ) . '" alt="Before treatment" loading="lazy">'
				. '<span class="skba__tag skba__tag--b">Before</span><span class="skba__tag skba__tag--a">After</span>'
				. '<span class="skba__handle" aria-hidden="true"></span>'
				. '<input class="skba__range" type="range" min="0" max="100" value="50" aria-label="Drag to compare before and after">'
				. '</div>' . ( ! empty( $p['caption'] ) ? '<figcaption>' . esc_html( $p['caption'] ) . '</figcaption>' : '' ) . '</figure>';
		}
		$out .= '</div><p class="skba-note">Real client results, shared with permission. Individual results vary.</p>';
		return '<div class="skba-block"><p class="skba-eyebrow">Real results</p><h2 class="skba-title">Drag to see the <em>difference.</em></h2>' . $out . '</div>';
	}
);

add_action(
	'wp_footer',
	function () {
		?>
<script>document.addEventListener('input',function(e){if(e.target.classList&&e.target.classList.contains('skba__range')){e.target.parentNode.style.setProperty('--pos',e.target.value+'%');}});</script>
		<?php
	}
);

/* ===========================================================================
 * Admin: Subscribers, Before & After
 * ======================================================================== */
add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'skynco-studio', 'Email list', 'Email list', SKYNCO_STUDIO_CAP, 'skynco-subscribers', 'skynco_page_subscribers' );
		add_submenu_page( 'skynco-studio', 'Before & After', 'Before & After', SKYNCO_STUDIO_CAP, 'skynco-before-after', 'skynco_page_before_after' );
	},
	20
);

function skynco_page_subscribers() {
	global $wpdb;
	$t     = skynco_subscribers_table();
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
	$week  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE created_at >= %s", gmdate( 'Y-m-d', strtotime( '-7 days' ) ) ) );
	$rows  = $wpdb->get_results( "SELECT * FROM $t ORDER BY created_at DESC LIMIT 200" );
	echo '<div class="wrap sk-wrap">';
	skynco_head( 'Email list', 'People who joined from the popup and the contact form. They also sync to Hostinger Reach once it is connected.', '<a class="sk-btn sk-btn--pink" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=skynco_export_subscribers' ), 'skynco_export_subscribers' ) ) . '">Export CSV</a> <a class="sk-btn sk-btn--ghost" href="' . esc_url( admin_url( 'admin.php?page=hostinger-reach' ) ) . '">Open Hostinger Reach</a>' );
	echo '<div class="sk-kpis"><div class="sk-kpi sk-kpi--dark"><span>Subscribers</span><b>' . (int) $total . '</b></div><div class="sk-kpi"><span>New this week</span><b>' . (int) $week . '</b></div></div>';
	echo '<div class="sk-card"><table class="sk-table"><thead><tr><th>Email</th><th>Name</th><th>Source</th><th>Joined</th></tr></thead><tbody>';
	if ( ! $rows ) {
		echo '<tr><td colspan="4" class="sk-empty">No subscribers yet. The popup shows to visitors after 12 seconds, on exit, or halfway down a page.</td></tr>';
	}
	foreach ( $rows as $r ) {
		echo '<tr><td>' . esc_html( $r->email ) . '</td><td>' . esc_html( $r->name ) . '</td><td><span class="sk-pill">' . esc_html( $r->source ) . '</span></td><td>' . esc_html( mysql2date( 'M j, Y g:ia', $r->created_at ) ) . '</td></tr>';
	}
	echo '</tbody></table></div></div>';
}

add_action(
	'admin_post_skynco_export_subscribers',
	function () {
		if ( ! current_user_can( SKYNCO_STUDIO_CAP ) || ! check_admin_referer( 'skynco_export_subscribers' ) ) {
			wp_die( 'Not allowed' );
		}
		global $wpdb;
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=skynco-email-list-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'email', 'name', 'source', 'joined' ] );
		foreach ( $wpdb->get_results( 'SELECT email, name, source, created_at FROM ' . skynco_subscribers_table() . ' ORDER BY created_at', ARRAY_N ) as $r ) {
			fputcsv( $out, $r );
		}
		exit;
	}
);

function skynco_page_before_after() {
	wp_enqueue_media();
	$all = get_option( 'skynco_before_after', [] );
	echo '<div class="wrap sk-wrap">';
	skynco_head( 'Before & After', 'Add real client photos (with their written consent). Each treatment page shows a drag-to-compare slider once a pair is added.' );
	if ( isset( $_GET['saved'] ) ) {
		echo '<div class="notice notice-success"><p>Saved.</p></div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'skynco_ba' );
	echo '<input type="hidden" name="action" value="skynco_save_ba">';
	foreach ( function_exists( 'lux_lp_treatments' ) ? lux_lp_treatments() : [] as $t ) {
		echo '<div class="sk-card" id="' . esc_attr( $t[0] ) . '"><h2>' . esc_html( $t[1] ) . ' <a class="sk-muted" style="font-size:13px" href="' . esc_url( home_url( '/services/' . $t[0] . '/' ) ) . '" target="_blank">view page ↗</a></h2><table class="sk-table"><thead><tr><th>Before</th><th>After</th><th>Caption (optional)</th></tr></thead><tbody>';
		for ( $i = 0; $i < 3; $i++ ) {
			$p = $all[ $t[0] ][ $i ] ?? [];
			echo '<tr>';
			foreach ( [ 'before', 'after' ] as $side ) {
				$id  = (int) ( $p[ $side ] ?? 0 );
				$src = $id ? wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
				echo '<td><div class="skba-pick"><img src="' . esc_url( $src ) . '" style="width:72px;height:72px;object-fit:cover;border-radius:10px;background:#FBEFF0;' . ( $src ? '' : 'display:none' ) . '"> <input type="hidden" name="ba[' . esc_attr( $t[0] ) . '][' . $i . '][' . $side . ']" value="' . ( $id ?: '' ) . '"><button type="button" class="sk-btn sk-btn--ghost skba-choose">Choose</button> <button type="button" class="button-link skba-clear">Remove</button></div></td>';
			}
			echo '<td><input type="text" class="regular-text" name="ba[' . esc_attr( $t[0] ) . '][' . $i . '][caption]" value="' . esc_attr( $p['caption'] ?? '' ) . '" placeholder="e.g. After 3 sessions, 6 weeks apart"></td></tr>';
		}
		echo '</tbody></table></div>';
	}
	echo '<p><button class="sk-btn sk-btn--pink" type="submit">Save before &amp; after photos</button></p></form></div>';
	?>
<script>
jQuery(function($){
	$(document).on('click','.skba-choose',function(){
		var box=$(this).closest('.skba-pick'), frame=wp.media({title:'Choose photo',multiple:false,library:{type:'image'}});
		frame.on('select',function(){ var a=frame.state().get('selection').first().toJSON(); box.find('input').val(a.id); box.find('img').attr('src',(a.sizes&&a.sizes.thumbnail?a.sizes.thumbnail.url:a.url)).show(); });
		frame.open();
	});
	$(document).on('click','.skba-clear',function(){ var box=$(this).closest('.skba-pick'); box.find('input').val(''); box.find('img').hide(); });
});
</script>
	<?php
}

add_action(
	'admin_post_skynco_save_ba',
	function () {
		if ( ! current_user_can( SKYNCO_STUDIO_CAP ) || ! check_admin_referer( 'skynco_ba' ) ) {
			wp_die( 'Not allowed' );
		}
		$clean = [];
		foreach ( (array) ( $_POST['ba'] ?? [] ) as $slug => $pairs ) {
			foreach ( (array) $pairs as $p ) {
				if ( ! empty( $p['before'] ) && ! empty( $p['after'] ) ) {
					$clean[ sanitize_title( $slug ) ][] = [ 'before' => absint( $p['before'] ), 'after' => absint( $p['after'] ), 'caption' => sanitize_text_field( wp_unslash( $p['caption'] ?? '' ) ) ];
				}
			}
		}
		update_option( 'skynco_before_after', $clean, false );
		if ( function_exists( 'litespeed_purge_all' ) || has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=skynco-before-after&saved=1' ) );
		exit;
	}
);

/* ===========================================================================
 * Front-end styles
 * ======================================================================== */
add_action(
	'wp_head',
	function () {
		?>
<style id="skynco-growth">
.skp[hidden]{display:none}
.skp{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px}
.skp__backdrop{position:absolute;inset:0;background:rgba(38,16,31,.6);opacity:0;transition:opacity .25s}
.skp__box{position:relative;display:grid;grid-template-columns:42% 1fr;width:min(820px,100%);background:#FFF8F6;border-radius:28px;overflow:hidden;box-shadow:0 30px 80px rgba(38,16,31,.35);transform:translateY(16px) scale(.98);opacity:0;transition:transform .3s ease,opacity .3s ease}
.skp.is-open .skp__backdrop{opacity:1}.skp.is-open .skp__box{transform:none;opacity:1}
.skp__img{background:#F6DDE1 center/cover no-repeat;min-height:100%}
.skp__body{padding:40px 36px 30px}
.skp__x{position:absolute;top:12px;right:14px;width:38px;height:38px;border-radius:50%;border:0;background:#fff;color:#3B1530;font-size:24px;line-height:1;cursor:pointer;z-index:2}
.skp__eyebrow{margin:0 0 10px;font:700 12px/1 Manrope,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#D1127E}
.skp h2{margin:0 0 12px;font:500 32px/1.12 Fraunces,serif;color:#3B1530}
.skp h2 em{color:#D1127E}
.skp__text{margin:0 0 18px;font:400 15px/1.55 Manrope,sans-serif;color:#6E5A66}
.skp__form{display:flex;flex-direction:column;gap:10px}
.skp__form input{width:100%;padding:14px 16px;border:1px solid #EADDE0;border-radius:14px;background:#fff;font:400 15px Manrope,sans-serif}
.skp__form input:focus{outline:2px solid #D1127E;outline-offset:1px}
.skp__form button{padding:15px 18px;border:0;border-radius:999px;background:#D1127E;color:#fff;font:700 15px Manrope,sans-serif;cursor:pointer}
.skp__form button:hover{background:#B00E6A}
.skp__msg{margin:2px 0 0;font:600 14px/1.4 Manrope,sans-serif;color:#3B1530;min-height:1px}
.skp__fine{margin:12px 0 0;font:400 12px Manrope,sans-serif;color:#9A8791}
.skp__hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;opacity:0}
@media(max-width:700px){.skp{align-items:flex-end;padding:0}.skp__box{grid-template-columns:1fr;border-radius:24px 24px 0 0;max-height:92vh;overflow:auto}.skp__img{min-height:140px}.skp__body{padding:26px 20px 22px}.skp h2{font-size:26px}}
@media(prefers-reduced-motion:reduce){.skp__box,.skp__backdrop{transition:none}}
.skc{display:flex;flex-direction:column;gap:14px;font-family:Manrope,sans-serif}
.skc__row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.skc label{display:flex;flex-direction:column;gap:6px;font:600 14px Manrope,sans-serif;color:#3B1530}
.skc label span{font-weight:400;color:#9A8791}
.skc input:not([type=checkbox]),.skc select,.skc textarea{width:100%;min-height:50px;height:auto;line-height:1.4;padding:13px 15px;border:1px solid #EADDE0;border-radius:14px;background:#fff;font:400 15px Manrope,sans-serif;color:#2A1A24}
.skc input:focus,.skc select:focus,.skc textarea:focus{outline:2px solid #D1127E;outline-offset:1px;border-color:transparent}
.skc .skc__check{flex-direction:row;align-items:center;gap:10px;font-weight:500;color:#6E5A66}
.skc__check input{width:18px;height:18px;accent-color:#D1127E}
.skc button{align-self:flex-start;padding:16px 28px;border:0;border-radius:999px;background:#D1127E;color:#fff;font:700 15px Manrope,sans-serif;cursor:pointer}
.skc button:hover{background:#B00E6A}
.skc__ok,.skc__err{margin:0;padding:14px 16px;border-radius:14px;font-weight:600}
.skc__ok{background:#E5F4EA;color:#1D6B3A}.skc__err{background:#FDE8E8;color:#8F1F1F}
@media(max-width:640px){.skc__row{grid-template-columns:1fr}.skc button{align-self:stretch}}
.skba-block{max-width:1200px;margin:0 auto;padding:96px 24px 0}
@media(max-width:767px){.skba-block{padding:56px 16px 0}}
.skba-eyebrow{margin:0 0 12px;font:700 13px Manrope,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#D1127E}
.skba-title{margin:0 0 28px;font:500 42px/1.15 Fraunces,serif;color:#3B1530}
.skba-title em{color:#D1127E}
@media(max-width:767px){.skba-title{font-size:30px}}
.skba-grid{display:grid;gap:16px;grid-template-columns:repeat(3,minmax(0,1fr))}
.skba-n1{grid-template-columns:minmax(0,640px);justify-content:center}.skba-n2{grid-template-columns:repeat(2,minmax(0,1fr))}
@media(max-width:767px){.skba-grid{grid-template-columns:1fr}}
.skba{margin:0}
.skba__frame{position:relative;aspect-ratio:4/5;border-radius:24px;overflow:hidden;background:#FBEFF0;user-select:none}
.skba__frame img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;max-width:none}
.skba__before{clip-path:inset(0 calc(100% - var(--pos)) 0 0)}
.skba__handle{position:absolute;top:0;bottom:0;left:var(--pos);width:3px;margin-left:-1.5px;background:#fff;pointer-events:none}
.skba__handle::after{content:"\2194";position:absolute;top:50%;left:50%;width:44px;height:44px;margin:-22px 0 0 -22px;border-radius:50%;background:#fff;color:#3B1530;display:flex;align-items:center;justify-content:center;font-size:20px;box-shadow:0 6px 18px rgba(0,0,0,.25)}
.skba__range{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:ew-resize;margin:0}
.skba__range:focus-visible + *{outline:none}
.skba__frame:focus-within .skba__handle::after{outline:3px solid #D1127E}
.skba__tag{position:absolute;top:14px;padding:6px 12px;border-radius:999px;background:rgba(38,16,31,.75);color:#fff;font:700 12px Manrope,sans-serif;pointer-events:none}
.skba__tag--b{left:14px}.skba__tag--a{right:14px}
.skba figcaption{margin-top:10px;font:500 14px Manrope,sans-serif;color:#6E5A66;text-align:center}
.skba-note{margin:12px 0 0;font:400 13px Manrope,sans-serif;color:#9A8791;text-align:center}
.skba-empty{padding:18px 20px;border:2px dashed #D1127E;border-radius:18px;background:#FFF8F6;font:400 15px Manrope,sans-serif;color:#3B1530}
.skba-empty a{color:#D1127E}
</style>
		<?php
	}
);
