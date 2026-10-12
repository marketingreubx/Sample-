<?php
/** Views for the Cold DM Playbook members area. Loaded by mu-plugins/ir-playbook.php. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ir_pb_icon( $name ) {
	$p = [
		'menu'  => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
		'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
		'left'  => '<path d="M15 18l-6-6 6-6"/>',
		'right' => '<path d="M9 18l6-6-6-6"/>',
		'home'  => '<path d="M4 11l8-7 8 7v8a1 1 0 01-1 1h-4v-6H9v6H5a1 1 0 01-1-1z"/>',
		'chat'  => '<path d="M21 12a8 8 0 01-11.6 7.1L4 20l1-4.6A8 8 0 1121 12z"/>',
		'out'   => '<path d="M9 4H6a2 2 0 00-2 2v12a2 2 0 002 2h3M16 17l5-5-5-5M21 12H9"/>',
		'lock'  => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>',
		'moon'  => '<path d="M20 14.5A8 8 0 019.5 4 8 8 0 1020 14.5z"/>',
		'copy'  => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a1 1 0 011-1h10"/>',
		'play'  => '<path d="M7 5l12 7-12 7z"/>',
	];
	return '<svg class="pb-i" viewBox="0 0 24 24" aria-hidden="true">' . ( $p[ $name ] ?? '' ) . '</svg>';
}

function ir_pb_css() {
	return <<<'CSS'
:root{--bg:#F6F8FD;--surface:#fff;--text:#0A1633;--muted:#4F5D78;--soft:#EEF3FF;--line:#E3E9F5;--acc:#1660E8;--acc2:#0F4ACB;--orange:#FF7A1A;--orange-soft:#FFF1E6;--ok:#16A34A;--band:#071233;--shadow:0 1px 2px rgba(10,22,51,.04),0 12px 32px rgba(10,22,51,.06);--r:18px}
html[data-theme=dark]{--bg:#060C1C;--surface:#0D162B;--text:#EAF0FF;--muted:#A3B0CC;--soft:#13213F;--line:#1C2A48;--acc:#4C8DFF;--acc2:#6EA8FE;--orange-soft:#2A1A0E;--shadow:0 12px 32px rgba(0,0,0,.35)}
*{box-sizing:border-box}html,body{margin:0}body{background:var(--bg);color:var(--text);font:16px/1.6 'Host Grotesk',system-ui,-apple-system,Segoe UI,sans-serif;-webkit-font-smoothing:antialiased}
a{color:var(--acc)}button{font:inherit;cursor:pointer}
.pb-i{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex:none}
.pb-logo{white-space:nowrap;font-weight:800;font-size:20px;letter-spacing:-.6px;color:var(--text);text-decoration:none}.pb-logo span{color:var(--acc)}
.pb-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;background:var(--acc);color:#fff!important;text-decoration:none!important;font-weight:700;padding:13px 20px;border-radius:12px;border:0;transition:transform .15s,background .15s;box-shadow:0 8px 20px rgba(22,96,232,.25)}
.pb-btn:hover{background:var(--acc2);transform:translateY(-1px)}.pb-btn.ghost{background:transparent;color:var(--text)!important;border:1px solid var(--line);box-shadow:none}.pb-btn.ghost:hover{background:var(--soft)}
.pb-btn.orange{background:var(--orange);box-shadow:0 8px 20px rgba(255,122,26,.3)}
/* auth */
.pb-auth{min-height:100vh;display:grid;grid-template-columns:1.05fr 1fr}
.pb-auth-side{position:relative;overflow:hidden;color:#fff;padding:48px;display:flex;flex-direction:column;justify-content:space-between;background:radial-gradient(70% 60% at 100% 100%,rgba(29,94,255,.6),transparent 60%),radial-gradient(50% 45% at 0% 0%,rgba(255,122,26,.28),transparent 60%),linear-gradient(160deg,#0A1A4A,#050B22)}
.pb-auth-side .pb-logo{color:#fff}.pb-auth-side h1{font-size:44px;line-height:1.08;letter-spacing:-1.6px;margin:0 0 16px;max-width:520px}.pb-auth-side h1 em{font-style:normal;background:linear-gradient(90deg,#6EA8FE,#FF9A52);-webkit-background-clip:text;background-clip:text;color:transparent}
.pb-auth-side p{color:#A9B6D0;font-size:17px;max-width:480px;margin:0 0 28px}
.pb-auth-list{list-style:none;padding:0;margin:0;display:grid;gap:12px;max-width:460px}.pb-auth-list li{display:flex;gap:12px;align-items:center;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:12px 14px;backdrop-filter:blur(8px)}
.pb-auth-list b{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:rgba(255,255,255,.1);font-size:17px}
.pb-auth-main{display:flex;align-items:center;justify-content:center;padding:40px 20px}
.pb-card{width:100%;max-width:420px;background:var(--surface);border:1px solid var(--line);border-radius:22px;padding:32px 28px;box-shadow:var(--shadow)}
.pb-card h2{margin:0 0 6px;font-size:26px;letter-spacing:-.6px}.pb-card .sub{color:var(--muted);margin:0 0 22px}
.pb-f label{display:block;font-size:14px;font-weight:600;margin:0 0 6px}.pb-f input{width:100%;padding:13px 14px;border-radius:12px;border:1px solid var(--line);background:var(--bg);color:var(--text);font:inherit;margin:0 0 14px}.pb-f input:focus{outline:2px solid var(--acc);outline-offset:1px;border-color:transparent}
.pb-f .pb-btn{width:100%;padding:14px}.pb-links{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin:14px 0 0;font-size:14px}.pb-links a{text-decoration:none;font-weight:600}
.pb-alert{padding:12px 14px;border-radius:12px;margin:0 0 16px;font-size:15px}.pb-alert.err{background:#FEECEC;color:#9B1C1C}.pb-alert.ok{background:#E9F8EF;color:#14532D}
html[data-theme=dark] .pb-alert.err{background:#3A1414;color:#FFC9C9}html[data-theme=dark] .pb-alert.ok{background:#0F2E1C;color:#B7F0CC}
.pb-sep{display:flex;align-items:center;gap:12px;color:var(--muted);font-size:13px;margin:22px 0}.pb-sep:before,.pb-sep:after{content:"";flex:1;height:1px;background:var(--line)}
.pb-buy{display:flex;gap:12px;align-items:center;justify-content:space-between;background:var(--orange-soft);border-radius:14px;padding:14px 16px;font-size:15px}.pb-buy a{white-space:nowrap;font-weight:700;color:var(--orange);text-decoration:none}
.pb-pane[hidden]{display:none}
/* app shell */
.pb-top{position:sticky;top:0;z-index:30;display:flex;align-items:center;gap:12px;padding:12px 20px;background:color-mix(in srgb,var(--surface) 78%,transparent);backdrop-filter:blur(16px) saturate(160%);-webkit-backdrop-filter:blur(16px) saturate(160%);border-bottom:1px solid var(--line)}
.pb-top .grow{flex:1}.pb-iconbtn{display:inline-grid;place-items:center;width:40px;height:40px;border-radius:12px;border:1px solid var(--line);background:var(--surface);color:var(--text);text-decoration:none}
.pb-menu-btn{display:none}.pb-pill{display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border-radius:999px;border:1px solid var(--line);background:var(--surface);color:var(--text);text-decoration:none;font-weight:600;font-size:14px}
.pb-av{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(135deg,var(--acc),#4FA3FF);color:#fff;font-weight:700}
.pb-shell{display:grid;grid-template-columns:300px 1fr;max-width:1320px;margin:0 auto}
.pb-side{position:sticky;top:65px;height:calc(100vh - 65px);overflow:auto;padding:20px 16px 40px;border-right:1px solid var(--line)}
.pb-prog{background:var(--surface);border:1px solid var(--line);border-radius:var(--r);padding:16px;margin:0 0 18px;box-shadow:var(--shadow)}
.pb-prog strong{display:block;font-size:15px}.pb-prog small{color:var(--muted)}
.pb-bar{height:8px;border-radius:99px;background:var(--soft);overflow:hidden;margin:10px 0 0}.pb-bar i{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,var(--acc),#4FA3FF 70%,var(--orange));transition:width .4s}
.pb-nav h4{margin:18px 8px 6px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
.pb-nav a{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:10px;color:var(--text);text-decoration:none;font-size:14.5px;line-height:1.35}
.pb-nav a:hover{background:var(--soft)}.pb-nav a.on{background:var(--soft);color:var(--acc);font-weight:700}
.pb-nav .em{width:26px;text-align:center;flex:none}.pb-nav .tk{margin-left:auto;width:18px;height:18px;border-radius:50%;border:1.5px solid var(--line);display:grid;place-items:center;flex:none}
.pb-nav .tk .pb-i{opacity:0}.pb-nav a.done .tk{background:var(--ok);border-color:var(--ok);color:#fff}.pb-nav a.done .tk .pb-i{opacity:1}.pb-nav a.done .tk .pb-i{width:12px;height:12px;stroke-width:3}
.pb-main{padding:32px 40px 80px;min-width:0}.pb-read{max-width:760px;margin:0 auto}
/* home */
.pb-hero{position:relative;overflow:hidden;border-radius:26px;padding:36px;color:#fff;background:radial-gradient(70% 80% at 100% 100%,rgba(29,94,255,.65),transparent 60%),radial-gradient(45% 60% at 0% 0%,rgba(255,122,26,.3),transparent 60%),linear-gradient(160deg,#0A1A4A,#050B22);box-shadow:0 20px 50px rgba(7,18,51,.25)}
.pb-hero h1{font-size:36px;line-height:1.1;letter-spacing:-1.2px;margin:0 0 10px}.pb-hero p{color:#B9C5DE;margin:0 0 22px;font-size:17px;max-width:560px}
.pb-hero .pb-bar{background:rgba(255,255,255,.14);max-width:420px;margin:0 0 8px}.pb-hero small{color:#B9C5DE}
.pb-hero-acts{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}.pb-hero .pb-btn.ghost{color:#fff!important;border-color:rgba(255,255,255,.25)}.pb-hero .pb-btn.ghost:hover{background:rgba(255,255,255,.08)}
.pb-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;margin:26px 0}
.pb-tile{display:block;background:var(--surface);border:1px solid var(--line);border-radius:var(--r);padding:18px;text-decoration:none;color:var(--text);box-shadow:var(--shadow);transition:transform .2s,box-shadow .2s}
.pb-tile:hover{transform:translateY(-3px);box-shadow:0 22px 44px rgba(10,22,51,.1)}.pb-tile .n{font-size:13px;color:var(--muted)}.pb-tile h3{margin:6px 0 10px;font-size:18px;letter-spacing:-.3px}
.pb-tile .emo{display:grid;place-items:center;width:44px;height:44px;border-radius:13px;background:var(--soft);font-size:22px}
.pb-h2{font-size:22px;letter-spacing:-.4px;margin:34px 0 4px}
/* lesson */
.pb-crumb{display:flex;align-items:center;gap:6px;color:var(--muted);font-size:14px;margin:0 0 14px}.pb-crumb a{color:var(--muted);text-decoration:none}
.pb-lh{display:flex;gap:16px;align-items:center;margin:0 0 26px}.pb-lh .emo{display:grid;place-items:center;width:62px;height:62px;border-radius:18px;background:linear-gradient(135deg,var(--soft),var(--orange-soft));font-size:32px;flex:none}
.pb-lh h1{margin:0;font-size:34px;line-height:1.12;letter-spacing:-1.1px}
.pb-content{font-size:17px;line-height:1.75}.pb-content p{margin:0 0 14px}.pb-content h2{font-size:23px;letter-spacing:-.4px;line-height:1.25;margin:34px 0 12px}.pb-content h3{font-size:19px;margin:28px 0 10px}
.pb-content hr{border:0;height:1px;background:var(--line);margin:28px 0}.pb-content ul,.pb-content ol{padding-left:22px;margin:0 0 16px}.pb-content li{margin:4px 0}
.pb-content blockquote{margin:18px 0;padding:16px 18px;border-left:4px solid var(--acc);background:var(--soft);border-radius:0 14px 14px 0}
.pb-ph{background:var(--orange-soft);color:inherit;border-radius:6px;padding:1px 5px;box-decoration-break:clone;-webkit-box-decoration-break:clone;border-bottom:1.5px dashed var(--orange)}
.pb-script{background:var(--surface);border:1px solid var(--line);border-radius:var(--r);margin:16px 0;box-shadow:var(--shadow);overflow:hidden}
.pb-content .pb-script+hr{display:none}.pb-script-h{display:block;padding:12px 16px;font-weight:700;font-size:15px;background:var(--soft);border-bottom:1px solid var(--line)}
.pb-script-body{padding:16px 18px 4px}.pb-script>.pb-copy{margin:0 18px 16px}
.pb-copy{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--line);background:var(--surface);color:var(--text);border-radius:10px;padding:8px 12px;font-size:14px;font-weight:600}
.pb-copy:hover{border-color:var(--acc);color:var(--acc)}.pb-copy.ok{background:var(--ok);border-color:var(--ok);color:#fff}
.pb-prompt{border-radius:var(--r);overflow:hidden;margin:16px 0;background:#0B1430;border:1px solid #1C2A48}
.pb-prompt-bar{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#111C3D;color:#A9B6D0;font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
.pb-prompt .pb-copy{background:transparent;color:#fff;border-color:#2A3B66;text-transform:none;letter-spacing:0}
.pb-prompt pre{margin:0;padding:16px;color:#E6ECFF;white-space:pre-wrap;word-break:break-word;font:14.5px/1.65 ui-monospace,SFMono-Regular,Menlo,monospace}
.pb-table{overflow-x:auto;margin:16px 0;border:1px solid var(--line);border-radius:14px}.pb-table table{width:100%;border-collapse:collapse;font-size:15.5px;line-height:1.5}
.pb-table th{background:var(--soft);text-align:left;font-weight:700;padding:11px 14px}.pb-table td{padding:11px 14px;border-top:1px solid var(--line);vertical-align:top}
.pb-callout{display:flex;gap:12px;padding:16px 18px;border-radius:16px;background:var(--soft);margin:16px 0}.pb-callout>div{flex:1;min-width:0}.pb-callout-ic{font-size:20px;line-height:1.5}.pb-callout p:last-child{margin-bottom:0}
.pb-callout .pb-btn{margin-top:4px}
.pb-c-red{color:#DC2626;font-weight:700}.pb-c-pink{color:#DB2777}.pb-c-purple{color:#7C3AED}html[data-theme=dark] .pb-c-pink{color:#F472B6}html[data-theme=dark] .pb-c-purple{color:#A78BFA}
.pb-video{position:relative;padding-top:56.25%;border-radius:18px;overflow:hidden;background:#000;margin:18px 0}.pb-video iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
.pb-done-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:36px 0 18px;padding:18px;border:1px solid var(--line);border-radius:var(--r);background:var(--surface)}
.pb-mark{display:inline-flex;align-items:center;gap:8px;border:1.5px solid var(--line);background:var(--surface);color:var(--text);border-radius:12px;padding:11px 16px;font-weight:700}
.pb-mark.on{background:var(--ok);border-color:var(--ok);color:#fff}
.pb-pn{display:grid;grid-template-columns:1fr 1fr;gap:12px}.pb-pn a{display:block;padding:16px 18px;border:1px solid var(--line);border-radius:var(--r);background:var(--surface);text-decoration:none;color:var(--text)}
.pb-pn a:hover{border-color:var(--acc)}.pb-pn small{display:flex;align-items:center;gap:4px;color:var(--muted);font-size:13px}.pb-pn b{display:block;margin-top:4px;line-height:1.35}.pb-pn .nx{text-align:right}.pb-pn .nx small{justify-content:flex-end}
.pb-setpass{display:flex;gap:14px;align-items:center;flex-wrap:wrap;padding:16px 18px;border-radius:var(--r);background:var(--orange-soft);margin:0 0 22px}.pb-setpass p{margin:0;flex:1;min-width:220px}
.pb-setpass form{display:flex;gap:8px;flex-wrap:wrap}.pb-setpass input{padding:11px 12px;border-radius:10px;border:1px solid var(--line);background:var(--surface);color:var(--text);font:inherit;min-width:200px}
.pb-scrim{display:none}
@media (max-width:980px){
 .pb-auth{grid-template-columns:1fr}.pb-auth-side{padding:28px 20px}.pb-auth-side h1{font-size:32px}.pb-auth-list{display:none}.pb-auth-side p{margin:0}
 .pb-shell{grid-template-columns:1fr}.pb-menu-btn{display:inline-grid}
 .pb-side{position:fixed;z-index:50;left:0;top:0;height:100vh;width:300px;max-width:86vw;background:var(--bg);transform:translateX(-102%);transition:transform .25s;border-right:1px solid var(--line)}
 body.nav-open .pb-side{transform:none}body.nav-open .pb-scrim{display:block;position:fixed;inset:0;z-index:40;background:rgba(5,11,34,.45)}
 .pb-main{padding:22px 16px 70px}.pb-hero{padding:26px 20px;border-radius:22px}.pb-hero h1{font-size:28px}.pb-lh h1{font-size:27px}.pb-lh .emo{width:52px;height:52px;font-size:26px}
 .pb-top{gap:8px;padding:10px 14px}.pb-top .pb-pill span.t{display:none}.pb-top .pb-pill{padding:9px 11px}.pb-pn{grid-template-columns:1fr}.pb-content{font-size:16.5px}
}
CSS;
}

function ir_pb_head( $title ) {
	?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $title ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Host+Grotesk:wght@400..800&display=swap">
<link rel="icon" href="<?php echo esc_url( get_site_icon_url( 64 ) ); ?>">
<script>(function(){try{var t=localStorage.getItem('ir-theme');if(t==='dark'||(!t&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.setAttribute('data-theme','dark')}catch(e){}})();</script>
<style><?php echo ir_pb_css(); ?></style></head><body><?php
}

function ir_pb_render( $view, $msg = '', $err = '' ) {
	if ( $view === 'app' ) { ir_pb_render_app( $err ); return; }
	ir_pb_head( 'The Cold DM Playbook | Members' );
	$alert = ( $err ? '<div class="pb-alert err">' . esc_html( $err ) . '</div>' : '' ) . ( $msg ? '<div class="pb-alert ok">' . esc_html( $msg ) . '</div>' : '' );
	$cur = wp_get_current_user();
	?>
<div class="pb-auth">
 <aside class="pb-auth-side">
  <a class="pb-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">Igho Reuben<span>.</span></a>
  <div>
   <h1>The Cold DM <em>Playbook</em></h1>
   <p>Your private dashboard with every script, reply and follow-up you need to turn cold DMs into paying clients.</p>
   <ul class="pb-auth-list">
    <li><b>✍️</b>7 copy-and-send cold DM scripts</li>
    <li><b>🛡️</b>Replies for 11 objections and awkward messages</li>
    <li><b>📅</b>A follow-up plan from day 2 to day 30</li>
    <li><b>📞</b>Discovery call script to close the deal</li>
   </ul>
  </div>
  <small style="color:#7F8DAE">© <?php echo esc_html( gmdate( 'Y' ) ); ?> Socialreubx</small>
 </aside>
 <main class="pb-auth-main"><div class="pb-card">
<?php if ( $view === 'set' ) : $u = $GLOBALS['ir_pb_set_user']; ?>
  <h2>Create your password</h2><p class="sub">For <?php echo esc_html( $u->user_email ); ?>. You will use it to log in next time.</p>
  <?php echo $alert; ?>
  <form class="pb-f" method="post"><input type="hidden" name="pb_act" value="setpass">
   <label for="pass">New password</label><input id="pass" type="password" name="pass" minlength="8" required autocomplete="new-password" placeholder="At least 8 characters">
   <button class="pb-btn">Save and open my playbook</button></form>
<?php elseif ( $view === 'noaccess' ) : ?>
  <h2>No playbook on this account yet</h2>
  <p class="sub">You are logged in as <b><?php echo esc_html( $cur->user_email ); ?></b>, but this account has not bought the Cold DM Playbook.</p>
  <a class="pb-btn orange" style="width:100%" href="<?php echo esc_url( ir_pb_sales_url() ); ?>">Get the playbook for ₦4,500</a>
  <div class="pb-sep">Paid with another email?</div>
  <form class="pb-f" method="post"><input type="hidden" name="pb_act" value="claim">
   <label for="cemail">Email you paid with</label><input id="cemail" type="email" name="email" required placeholder="you@email.com">
   <button class="pb-btn ghost">Send my access link</button></form>
  <div class="pb-links"><a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'logout', '1', ir_pb_url() ), 'ir_pb_logout' ) ); ?>">Log out</a><a href="https://wa.me/2348104699214" target="_blank" rel="noopener">WhatsApp support</a></div>
<?php else : ?>
  <div class="pb-pane" data-pane="login">
   <h2>Welcome back</h2><p class="sub">Log in to open your playbook.</p>
   <?php echo $alert; ?>
   <form class="pb-f" method="post"><input type="hidden" name="pb_act" value="login">
    <label for="log">Email</label><input id="log" type="text" name="log" required autocomplete="username" placeholder="you@email.com" value="<?php echo esc_attr( wp_unslash( $_POST['log'] ?? '' ) ); ?>">
    <label for="pwd">Password</label><input id="pwd" type="password" name="pwd" required autocomplete="current-password">
    <button class="pb-btn">Log in</button></form>
   <div class="pb-links"><a href="#forgot" data-go="forgot">Forgot password?</a><a href="#claim" data-go="claim">Bought it but no login?</a></div>
   <div class="pb-sep">New here?</div>
   <div class="pb-buy"><span>Don't have the playbook yet?</span><a href="<?php echo esc_url( ir_pb_sales_url() ); ?>">Get it for ₦4,500 →</a></div>
  </div>
  <div class="pb-pane" data-pane="forgot" hidden>
   <h2>Reset your password</h2><p class="sub">Enter your email and I'll send you a link to choose a new password.</p>
   <form class="pb-f" method="post"><input type="hidden" name="pb_act" value="forgot">
    <label for="femail">Email</label><input id="femail" type="email" name="email" required placeholder="you@email.com">
    <button class="pb-btn">Send reset link</button></form>
   <div class="pb-links"><a href="#login" data-go="login">← Back to log in</a></div>
  </div>
  <div class="pb-pane" data-pane="claim" hidden>
   <h2>Get your login</h2><p class="sub">Bought the playbook before this dashboard existed, or never got your login? Enter the email you paid with.</p>
   <form class="pb-f" method="post"><input type="hidden" name="pb_act" value="claim">
    <label for="c2">Email you paid with</label><input id="c2" type="email" name="email" required placeholder="you@email.com">
    <button class="pb-btn">Send my access link</button></form>
   <div class="pb-links"><a href="#login" data-go="login">← Back to log in</a><a href="https://wa.me/2348104699214" target="_blank" rel="noopener">WhatsApp support</a></div>
  </div>
  <script>(function(){function go(p){document.querySelectorAll('.pb-pane').forEach(function(e){e.hidden=e.getAttribute('data-pane')!==p})}document.querySelectorAll('[data-go]').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();go(a.getAttribute('data-go'))})});var h=location.hash.slice(1);if(h==='forgot'||h==='claim')go(h)})();</script>
<?php endif; ?>
 </div></main>
</div></body></html><?php
}

function ir_pb_render_app( $err ) {
	$u       = wp_get_current_user();
	$lessons = ir_pb_lessons();
	$data    = ir_pb_data();
	$slugs   = wp_list_pluck( $lessons, 'slug' );
	$done    = array_values( array_intersect( (array) get_user_meta( $u->ID, 'ir_pb_done', true ), $slugs ) );
	$total   = count( $lessons );
	$pct     = $total ? round( count( $done ) / $total * 100 ) : 0;
	$cur     = get_query_var( 'ir_pb_l' );
	$idx     = $cur ? array_search( $cur, $slugs, true ) : false;
	$lesson  = $idx !== false ? $lessons[ $idx ] : null;
	if ( $cur && ! $lesson ) { wp_safe_redirect( ir_pb_url() ); exit; }
	if ( $lesson ) { update_user_meta( $u->ID, 'ir_pb_last', $lesson['slug'] ); }
	$first   = $u->first_name ?: $u->display_name;
	$next    = null;
	foreach ( $lessons as $l ) { if ( ! in_array( $l['slug'], $done, true ) ) { $next = $l; break; } }
	$last    = get_user_meta( $u->ID, 'ir_pb_last', true );
	$resume  = $next ?: $lessons[0];
	$needs   = get_user_meta( $u->ID, 'ir_pb_needs_pass', true );
	$logout  = wp_nonce_url( add_query_arg( 'logout', '1', ir_pb_url() ), 'ir_pb_logout' );

	ir_pb_head( ( $lesson ? $lesson['title'] . ' | ' : '' ) . 'The Cold DM Playbook' );
	?>
<header class="pb-top">
 <button class="pb-iconbtn pb-menu-btn" id="pbMenu" aria-label="Open contents"><?php echo ir_pb_icon( 'menu' ); ?></button>
 <a class="pb-logo" href="<?php echo esc_url( ir_pb_url() ); ?>">Igho Reuben<span>.</span></a>
 <div class="grow"></div>
 <a class="pb-pill" href="<?php echo esc_url( $data['support'] ); ?>" target="_blank" rel="noopener"><?php echo ir_pb_icon( 'chat' ); ?><span class="t">Support group</span></a>
 <button class="pb-iconbtn" id="pbTheme" aria-label="Switch light or dark"><?php echo ir_pb_icon( 'moon' ); ?></button>
 <a class="pb-iconbtn" href="<?php echo esc_url( $logout ); ?>" aria-label="Log out" title="Log out"><?php echo ir_pb_icon( 'out' ); ?></a>
 <span class="pb-av" title="<?php echo esc_attr( $u->user_email ); ?>"><?php echo esc_html( strtoupper( mb_substr( $first, 0, 1 ) ) ); ?></span>
</header>
<div class="pb-scrim" id="pbScrim"></div>
<div class="pb-shell">
 <aside class="pb-side" id="pbSide">
  <div class="pb-prog"><strong>The Cold DM Playbook</strong><small><span id="pbDoneN"><?php echo count( $done ); ?></span> of <?php echo (int) $total; ?> lessons done</small><div class="pb-bar"><i id="pbBar" style="width:<?php echo (int) $pct; ?>%"></i></div></div>
  <nav class="pb-nav">
   <a href="<?php echo esc_url( ir_pb_url() ); ?>" class="<?php echo $lesson ? '' : 'on'; ?>"><span class="em"><?php echo ir_pb_icon( 'home' ); ?></span>Dashboard</a>
   <?php foreach ( $data['sections'] as $s ) : ?>
    <h4><?php echo esc_html( $s['title'] ); ?></h4>
    <?php foreach ( $s['lessons'] as $l ) : $c = ( $lesson && $lesson['slug'] === $l['slug'] ? 'on ' : '' ) . ( in_array( $l['slug'], $done, true ) ? 'done' : '' ); ?>
     <a href="<?php echo esc_url( ir_pb_url( $l['slug'] ) ); ?>" class="<?php echo esc_attr( trim( $c ) ); ?>" data-slug="<?php echo esc_attr( $l['slug'] ); ?>"><span class="em"><?php echo esc_html( $l['icon'] ); ?></span><span><?php echo esc_html( $l['title'] ); ?></span><span class="tk"><?php echo ir_pb_icon( 'check' ); ?></span></a>
    <?php endforeach; ?>
   <?php endforeach; ?>
  </nav>
 </aside>
 <main class="pb-main"><div class="pb-read">
  <?php if ( $needs ) : ?>
   <div class="pb-setpass"><p><b>Create a password</b> so you can log in on any device. Your login email is <?php echo esc_html( $u->user_email ); ?>.</p>
    <form method="post"><input type="hidden" name="pb_act" value="newpass"><?php wp_nonce_field( 'ir_pb_newpass' ); ?><input type="password" name="pass" minlength="8" required placeholder="New password (8+ characters)" autocomplete="new-password"><button class="pb-btn">Save</button></form>
    <?php if ( $err ) : ?><p style="flex-basis:100%;color:#B42318"><?php echo esc_html( $err ); ?></p><?php endif; ?></div>
  <?php elseif ( isset( $_GET['saved'] ) || isset( $_GET['welcome'] ) ) : ?>
   <div class="pb-alert ok">Password saved. You can now log in with <?php echo esc_html( $u->user_email ); ?> on any device.</div>
  <?php endif; ?>

  <?php if ( ! $lesson ) : ?>
   <section class="pb-hero">
    <h1><?php echo count( $done ) ? 'Welcome back, ' : 'Welcome, '; ?><?php echo esc_html( $first ); ?> 👋</h1>
    <p><?php echo count( $done ) ? 'Pick up where you stopped. One script sent today beats ten saved for later.' : 'Start with the CPS Framework. It takes 3 minutes and every script in this playbook is built on it.'; ?></p>
    <div class="pb-bar"><i style="width:<?php echo (int) $pct; ?>%"></i></div><small><?php echo (int) $pct; ?>% complete · <?php echo count( $done ); ?> of <?php echo (int) $total; ?> lessons</small>
    <div class="pb-hero-acts"><a class="pb-btn orange" href="<?php echo esc_url( ir_pb_url( $resume['slug'] ) ); ?>"><?php echo count( $done ) ? 'Continue: ' : 'Start: '; ?><?php echo esc_html( $resume['title'] ); ?> →</a><a class="pb-btn ghost" href="<?php echo esc_url( $data['support'] ); ?>" target="_blank" rel="noopener">Join the support group</a></div>
   </section>
   <h2 class="pb-h2">Everything in your playbook</h2>
   <div class="pb-grid">
    <?php foreach ( $data['sections'] as $s ) : $n = count( $s['lessons'] ); $d = count( array_intersect( wp_list_pluck( $s['lessons'], 'slug' ), $done ) ); ?>
     <a class="pb-tile" href="<?php echo esc_url( ir_pb_url( $s['lessons'][0]['slug'] ) ); ?>"><span class="emo"><?php echo esc_html( $s['lessons'][0]['icon'] ); ?></span><h3><?php echo esc_html( $s['title'] ); ?></h3><div class="n"><?php echo (int) $d; ?> of <?php echo (int) $n; ?> done</div><div class="pb-bar"><i style="width:<?php echo $n ? round( $d / $n * 100 ) : 0; ?>%"></i></div></a>
    <?php endforeach; ?>
   </div>
   <h2 class="pb-h2">Quick tools</h2>
   <div class="pb-grid">
    <?php foreach ( [ '4-ai-prompt-fill-in-the-blanks-fast' => 'Personalise any script with AI in 60 seconds', 'how-to-find-20-ideal-prospects-in-20-minutes' => 'Your 20-minute prospecting routine', 'unexpected-reply-conversation-recovery-scripts' => 'They replied with something odd? Start here', 'discovery-call-script-from-call-to-closed-client' => 'Got their WhatsApp? Run this call' ] as $sl => $why ) : $i = array_search( $sl, $slugs, true ); if ( $i === false ) { continue; } $l = $lessons[ $i ]; ?>
     <a class="pb-tile" href="<?php echo esc_url( ir_pb_url( $sl ) ); ?>"><span class="emo"><?php echo esc_html( $l['icon'] ); ?></span><h3><?php echo esc_html( $l['title'] ); ?></h3><div class="n"><?php echo esc_html( $why ); ?></div></a>
    <?php endforeach; ?>
   </div>
  <?php else :
   $isdone = in_array( $lesson['slug'], $done, true );
   $prev = $idx > 0 ? $lessons[ $idx - 1 ] : null; $nxt = $idx < $total - 1 ? $lessons[ $idx + 1 ] : null;
   $pos = 0; $cnt = 0; foreach ( $lessons as $l ) { if ( $l['skey'] === $lesson['skey'] ) { $cnt++; if ( $l['slug'] === $lesson['slug'] ) { $pos = $cnt; } } }
   ?>
   <div class="pb-crumb"><a href="<?php echo esc_url( ir_pb_url() ); ?>">Dashboard</a><?php echo ir_pb_icon( 'right' ); ?><span><?php echo esc_html( $lesson['section'] ); ?> · <?php echo (int) $pos; ?> of <?php echo (int) $cnt; ?></span></div>
   <div class="pb-lh"><span class="emo"><?php echo esc_html( $lesson['icon'] ); ?></span><h1><?php echo esc_html( $lesson['title'] ); ?></h1></div>
   <article class="pb-content"><?php echo $lesson['html']; // Built from the owner's own Notion pages. ?></article>
   <div class="pb-done-row"><span><?php echo $isdone ? 'Nice work. This one is done.' : 'Read it? Mark it done to track your progress.'; ?></span>
    <button class="pb-mark<?php echo $isdone ? ' on' : ''; ?>" id="pbMark" data-slug="<?php echo esc_attr( $lesson['slug'] ); ?>" data-next="<?php echo $nxt ? esc_url( ir_pb_url( $nxt['slug'] ) ) : ''; ?>"><?php echo ir_pb_icon( 'check' ); ?><span><?php echo $isdone ? 'Completed' : 'Mark as complete'; ?></span></button></div>
   <nav class="pb-pn">
    <?php if ( $prev ) : ?><a href="<?php echo esc_url( ir_pb_url( $prev['slug'] ) ); ?>"><small><?php echo ir_pb_icon( 'left' ); ?>Previous</small><b><?php echo esc_html( $prev['icon'] . ' ' . $prev['title'] ); ?></b></a><?php else : ?><span></span><?php endif; ?>
    <?php if ( $nxt ) : ?><a class="nx" href="<?php echo esc_url( ir_pb_url( $nxt['slug'] ) ); ?>"><small>Next<?php echo ir_pb_icon( 'right' ); ?></small><b><?php echo esc_html( $nxt['icon'] . ' ' . $nxt['title'] ); ?></b></a><?php endif; ?>
   </nav>
  <?php endif; ?>
 </div></main>
</div>
<script>
(function(){
 var AJ=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,N=<?php echo wp_json_encode( wp_create_nonce( 'ir_pb' ) ); ?>,T=<?php echo (int) $total; ?>;
 var b=document.body,m=document.getElementById('pbMenu'),s=document.getElementById('pbScrim');
 if(m)m.onclick=function(){b.classList.add('nav-open')};if(s)s.onclick=function(){b.classList.remove('nav-open')};
 var on=document.querySelector('.pb-nav a.on');if(on&&on.scrollIntoView)on.scrollIntoView({block:'center'});
 document.getElementById('pbTheme').onclick=function(){var d=document.documentElement,dark=d.getAttribute('data-theme')!=='dark';if(dark)d.setAttribute('data-theme','dark');else d.removeAttribute('data-theme');try{localStorage.setItem('ir-theme',dark?'dark':'light')}catch(e){}};
 function copyText(t,btn,label){var done=function(){btn.classList.add('ok');btn.textContent='Copied ✓';setTimeout(function(){btn.classList.remove('ok');btn.textContent=label},1600)};
  if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(t).then(done)}else{var a=document.createElement('textarea');a.value=t;document.body.appendChild(a);a.select();try{document.execCommand('copy')}catch(e){}a.remove();done()}}
 document.querySelectorAll('.pb-copy').forEach(function(btn){var label=btn.textContent;btn.addEventListener('click',function(){var box=btn.closest('.pb-script,.pb-prompt');var src=box.querySelector('.pb-script-body,pre');var t=(src.innerText||src.textContent).replace(/\n{3,}/g,'\n\n').trim();copyText(t,btn,label)})});
 var mk=document.getElementById('pbMark');
 if(mk)mk.addEventListener('click',function(){var now=!mk.classList.contains('on');mk.classList.toggle('on',now);mk.querySelector('span').textContent=now?'Completed':'Mark as complete';
  var link=document.querySelector('.pb-nav a[data-slug="'+mk.dataset.slug+'"]');if(link)link.classList.toggle('done',now);
  var f=new FormData();f.append('action','ir_pb_progress');f.append('n',N);f.append('slug',mk.dataset.slug);f.append('done',now?'1':'');
  fetch(AJ,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){if(j&&j.ok){document.getElementById('pbDoneN').textContent=j.done;document.getElementById('pbBar').style.width=Math.round(j.done/j.total*100)+'%'}});
  if(now&&mk.dataset.next)setTimeout(function(){location.href=mk.dataset.next},700)});
})();
</script>
</body></html><?php
}
