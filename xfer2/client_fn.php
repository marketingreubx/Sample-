/** A copy of their answers for the person who filled the form, in the member's brand. Replies go to the member. */
function ir_form_client_mail( $f, $name, $email, $a ) {
	$biz   = trim( (string) ( $f['biz'] ?: ( $f['name'] ?? '' ) ) ) ?: 'us';
	$col   = preg_match( '/^#[0-9a-f]{6}$/i', (string) ( $f['color'] ?? '' ) ) ? $f['color'] : '#1660E8';
	$first = explode( ' ', trim( $name ) )[0];
	$ff    = 'font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif';
	$onb   = ( $f['type'] ?? '' ) === 'onboarding';
	$thanks = trim( (string) ( $f['thanks'] ?? '' ) );
	if ( $thanks === '' ) { $thanks = $onb ? 'Thank you for filling in the onboarding form. We will use your answers to plan your first month.' : 'Thank you for reaching out. We will get back to you shortly.'; }
	$rows = '';
	foreach ( $a as $r ) {
		if ( in_array( $r[0], [ 'Email' ], true ) ) { continue; }
		$rows .= '<tr><td style="padding:10px 0;border-top:1px solid #E6EBF5;color:#6B7A99;font-size:13px;width:38%;vertical-align:top">' . esc_html( $r[0] ) . '</td><td style="padding:10px 0;border-top:1px solid #E6EBF5;font-size:15px;color:#0A1633;white-space:pre-wrap">' . esc_html( $r[1] !== '' ? $r[1] : '-' ) . '</td></tr>';
	}
	$html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><style>u + .body .gm-s{background:#000;mix-blend-mode:screen}u + .body .gm-d{background:#000;mix-blend-mode:difference}</style></head>'
		. '<body class="body" style="margin:0;padding:0;background:#EEF2FB"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EEF2FB"><tr><td align="center" style="padding:28px 12px">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;border-radius:20px;overflow:hidden;background:#ffffff">'
		. '<tr><td bgcolor="' . esc_attr( $col ) . '" style="background-color:' . esc_attr( $col ) . ';padding:26px 28px;' . $ff . '"><div style="font-size:20px;font-weight:800;color:#ffffff"><span class="gm-s" style="display:inline-block"><span class="gm-d" style="display:inline-block">' . esc_html( $biz ) . '</span></span></div>'
		. '<div style="margin-top:14px;font-size:26px;line-height:1.2;font-weight:800;color:#ffffff"><span class="gm-s" style="display:inline-block"><span class="gm-d" style="display:inline-block">' . ( $onb ? 'We have your answers' : 'Thanks for your message' ) . ( $first !== '' ? ', ' . esc_html( $first ) : '' ) . '</span></span></div></td></tr>'
		. '<tr><td style="padding:26px 28px 8px;' . $ff . ';font-size:16px;line-height:1.6;color:#33415F"><p style="margin:0 0 14px">' . nl2br( esc_html( $thanks ) ) . '</p>'
		. '<p style="margin:18px 0 2px;font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#8A97B4">Your answers</p>'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 10px">' . $rows . '</table>'
		. '<p style="margin:18px 0 0;font-size:15px">Want to add or change something? Just reply to this email.</p></td></tr>'
		. '<tr><td style="padding:18px 28px 26px;' . $ff . '"><div style="border-top:1px solid #E6EBF5;padding-top:14px;font-size:13px;color:#8A97B4">Sent on behalf of ' . esc_html( $biz ) . '</div></td></tr>'
		. '</table></td></tr></table></body></html>';
	$h = [ 'Content-Type: text/html; charset=UTF-8', 'From: ' . str_replace( [ "\r", "\n", '"', '<', '>' ], '', $biz ) . ' <' . ( function_exists( 'ir_mail_from' ) ? ir_mail_from() : 'hello@' . wp_parse_url( home_url(), PHP_URL_HOST ) ) . '>' ];
	if ( is_email( $f['email'] ?? '' ) ) { $h[] = 'Reply-To: ' . str_replace( [ "\r", "\n", '"', '<', '>' ], '', $biz ) . ' <' . $f['email'] . '>'; }
	return wp_mail( $email, ( $onb ? 'Your onboarding answers for ' : 'Thanks for contacting ' ) . $biz, $html, $h );
}

