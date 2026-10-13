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
		'search'=> '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
		'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
		'pen'   => '<path d="M4 20h4L19 9a2.8 2.8 0 00-4-4L4 16v4zM13.5 6.5l4 4"/>',
		'star'  => '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/>',
		'down'  => '<path d="M6 9l6 6 6-6"/>',
		'list'  => '<path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/>',
		'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/>',
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
/* ===== v2 ===== */
body{background:radial-gradient(60% 40% at 100% 0%,rgba(22,96,232,.07),transparent 60%),radial-gradient(40% 30% at 0% 10%,rgba(255,122,26,.06),transparent 60%),var(--bg);background-attachment:fixed}
.pb-top{gap:10px}
.pb-search-btn{display:flex;align-items:center;gap:10px;flex:0 1 420px;margin-left:18px;padding:10px 12px 10px 14px;border-radius:12px;border:1px solid var(--line);background:var(--surface);color:var(--muted);font-size:14.5px;text-align:left;transition:border-color .15s,box-shadow .15s}
.pb-search-btn:hover{border-color:var(--acc);box-shadow:0 0 0 4px color-mix(in srgb,var(--acc) 12%,transparent)}
.pb-search-btn .t{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
kbd{font:600 12px/1 ui-monospace,monospace;padding:4px 7px;border-radius:6px;border:1px solid var(--line);background:var(--soft);color:var(--muted)}
.pb-me{position:relative}.pb-av{border:0;cursor:pointer}
.pb-me-menu{position:absolute;right:0;top:48px;width:240px;background:var(--surface);border:1px solid var(--line);border-radius:16px;box-shadow:0 20px 50px rgba(10,22,51,.16);padding:8px;z-index:60}
.pb-me-h{padding:10px 12px 12px;border-bottom:1px solid var(--line);margin-bottom:6px}.pb-me-h b{display:block}.pb-me-h small{color:var(--muted);word-break:break-all}
.pb-me-menu a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:var(--text);text-decoration:none;font-size:14.5px;font-weight:600}.pb-me-menu a:hover{background:var(--soft)}
.pb-side-top{display:none}
.pb-prog{display:flex;align-items:center;gap:12px}
.pb-ring{width:52px;height:52px;flex:none;transform:rotate(-90deg)}.pb-ring circle{fill:none;stroke:var(--soft);stroke-width:6}.pb-ring .v{stroke:var(--acc);stroke-linecap:round;transition:stroke-dashoffset .5s}
.pb-side-search{display:flex;align-items:center;gap:10px;width:100%;padding:11px 12px;margin:0 0 6px;border-radius:12px;border:1px dashed var(--line);background:transparent;color:var(--muted);font-size:14.5px}
.pb-side-search:hover{border-color:var(--acc);color:var(--acc)}
.pb-count{margin-left:auto;font-size:12px;font-weight:700;color:var(--muted);background:var(--soft);padding:2px 8px;border-radius:999px}
.pb-grp{margin:6px 0;border-radius:14px}
.pb-grp summary{list-style:none;display:flex;align-items:center;gap:10px;padding:10px;border-radius:12px;cursor:pointer;font-weight:700;font-size:14.5px;user-select:none}
.pb-grp summary::-webkit-details-marker{display:none}.pb-grp summary:hover{background:var(--soft)}
.pb-grp summary .dot{width:10px;height:10px;border-radius:4px;background:var(--c);box-shadow:0 0 0 4px color-mix(in srgb,var(--c) 18%,transparent)}
.pb-grp summary .pb-i{width:16px;height:16px;color:var(--muted);transition:transform .2s}.pb-grp[open] summary .pb-i{transform:rotate(180deg)}
.pb-grp summary .pb-count{margin-left:auto}
.pb-grp a{margin-left:6px;border-left:2px solid var(--line);border-radius:0 10px 10px 0;padding-left:12px}
.pb-grp a.on{border-left-color:var(--c);color:var(--c);background:color-mix(in srgb,var(--c) 10%,transparent)}
.pb-nav a.done .tk{background:var(--ok);border-color:var(--ok)}
.pb-nav-home{font-weight:600}
.pb-read.wide{max-width:980px}
/* hero */
.pb-hero{display:flex;align-items:center;gap:28px;padding:40px}
.pb-hero-l{flex:1;min-width:0}.pb-hero-tag{display:inline-block;font-size:12.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#FFB27A;margin-bottom:10px}
.pb-hero-search{display:flex;align-items:center;gap:12px;width:100%;max-width:560px;padding:15px 18px;border-radius:16px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.1);-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);color:#D6DEF0;font-size:16px;text-align:left;margin:0 0 4px;transition:background .15s}
.pb-hero-search:hover{background:rgba(255,255,255,.16)}
.pb-hero-acts{margin-top:18px}.pb-hero-acts .pb-btn .pb-i{width:16px;height:16px;fill:currentColor;stroke:none}
.pb-hero-r{position:relative;width:170px;height:170px;flex:none}
.pb-ring.big{width:170px;height:170px}.pb-ring.big circle{stroke:rgba(255,255,255,.14);stroke-width:5}.pb-ring.big .v{stroke:#FF9A52}
.pb-ring-t{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}.pb-ring-t b{font-size:38px;letter-spacing:-1px;line-height:1}.pb-ring-t small{color:#B9C5DE;font-size:13px;margin-top:4px}
.pb-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:18px 0 0}
.pb-stats div{background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:14px 16px;box-shadow:var(--shadow)}
.pb-stats b{display:block;font-size:26px;letter-spacing:-.8px;line-height:1.1;background:linear-gradient(90deg,var(--acc),#4FA3FF);-webkit-background-clip:text;background-clip:text;color:transparent}
.pb-stats span{font-size:13.5px;color:var(--muted)}
.pb-h2{margin:38px 0 4px}.pb-h2-sub{margin:0 0 16px;color:var(--muted)}
.pb-sits{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
.pb-sit{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:16px;background:var(--surface);border:1px solid var(--line);color:var(--text);text-decoration:none;font-weight:600;font-size:15.5px;box-shadow:var(--shadow);transition:transform .2s,border-color .2s}
.pb-sit:hover{transform:translateY(-2px);border-color:var(--acc)}.pb-sit .e{display:grid;place-items:center;width:40px;height:40px;border-radius:12px;background:var(--soft);font-size:20px;flex:none}
.pb-sit>span:nth-child(2){flex:1}.pb-sit .pb-i{color:var(--muted);width:18px;height:18px}
.pb-saved{display:grid;gap:8px}
.pb-row{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:14px;background:var(--surface);border:1px solid var(--line);text-decoration:none;color:var(--text)}
.pb-row:hover{border-color:var(--acc)}.pb-row .emo{font-size:20px;width:36px;text-align:center}.pb-row .tt{flex:1;display:flex;flex-direction:column}.pb-row small{color:var(--muted)}.pb-row .pb-i{color:var(--muted)}
.pb-secs{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px}
.pb-sec{position:relative;display:flex;gap:14px;align-items:flex-start;padding:18px;border-radius:20px;background:var(--surface);border:1px solid var(--line);text-decoration:none;color:var(--text);box-shadow:var(--shadow);overflow:hidden;transition:transform .2s,box-shadow .2s}
.pb-sec::before{content:"";position:absolute;inset:0 0 auto 0;height:4px;background:var(--c)}
.pb-sec:hover{transform:translateY(-3px);box-shadow:0 22px 44px rgba(10,22,51,.1)}
.pb-sec-ic{display:grid;place-items:center;width:48px;height:48px;border-radius:14px;font-size:24px;flex:none;background:color-mix(in srgb,var(--c) 14%,transparent)}
.pb-sec-b{flex:1;display:flex;flex-direction:column;gap:2px;min-width:0}.pb-sec-b b{font-size:17px;letter-spacing:-.2px}.pb-sec-b small{color:var(--muted);font-size:13.5px}
.pb-sec .pb-bar{margin:10px 0 4px}.pb-sec .pb-bar i{background:var(--c)}.pb-sec small.n{font-size:12.5px;font-weight:600}
.pb-support{display:flex;align-items:center;gap:16px;margin:34px 0 10px;padding:20px;border-radius:20px;background:linear-gradient(135deg,color-mix(in srgb,var(--acc) 10%,var(--surface)),color-mix(in srgb,var(--orange) 10%,var(--surface)));border:1px solid var(--line);text-decoration:none;color:var(--text)}
.pb-support .e{font-size:30px}.pb-support>span:nth-child(2){flex:1;display:flex;flex-direction:column}.pb-support small{color:var(--muted);font-size:14.5px}
/* lesson */
.pb-lesson .pb-crumb .sec{color:var(--c);font-weight:700}
.pb-lh{align-items:flex-start}.pb-lh>div{flex:1;min-width:0}
.pb-lh .emo{background:color-mix(in srgb,var(--c) 14%,transparent)}
.pb-meta{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}.pb-meta span{font-size:13px;font-weight:600;color:var(--muted);background:var(--surface);border:1px solid var(--line);padding:4px 10px;border-radius:999px}.pb-meta .ok{color:var(--ok);border-color:color-mix(in srgb,var(--ok) 35%,transparent)}
.pb-save{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--line);background:var(--surface);color:var(--text);border-radius:12px;padding:9px 13px;font-weight:700;font-size:14px;flex:none}
.pb-save .pb-i{width:18px;height:18px}.pb-save.on{color:#CA8A04;border-color:#F3D27A;background:#FFF8E1}.pb-save.on .pb-i{fill:#EAB308;stroke:#CA8A04}
html[data-theme=dark] .pb-save.on{background:#2A2208}
.pb-toc{display:flex;gap:8px;overflow-x:auto;scrollbar-width:none;margin:-8px 0 22px;padding:2px 0 4px;align-items:center}.pb-toc::-webkit-scrollbar{display:none}
.pb-toc span{font-size:12.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);flex:none;margin-right:2px}
.pb-toc a{flex:none;font-size:13.5px;font-weight:600;padding:7px 12px;border-radius:999px;background:var(--surface);border:1px solid var(--line);color:var(--text);text-decoration:none;white-space:nowrap}
.pb-toc a:hover{border-color:var(--c);color:var(--c)}
.pb-content [id^="s"]{scroll-margin-top:84px}
.pb-script{border-left:4px solid var(--c)}
.pb-content blockquote{border-left-color:var(--c);background:color-mix(in srgb,var(--c) 8%,var(--surface))}
/* bottom tabs */
.pb-tabs{display:none}
/* search modal */
.pb-modal{position:fixed;inset:0;z-index:80;background:rgba(5,11,34,.5);-webkit-backdrop-filter:blur(4px);backdrop-filter:blur(4px);display:flex;justify-content:center;align-items:flex-start;padding:10vh 16px 16px}
.pb-modal[hidden]{display:none}
.pb-modal-box{width:100%;max-width:640px;background:var(--surface);border:1px solid var(--line);border-radius:20px;box-shadow:0 30px 80px rgba(5,11,34,.35);overflow:hidden;display:flex;flex-direction:column;max-height:76vh}
.pb-modal-in{display:flex;align-items:center;gap:12px;padding:16px 18px;border-bottom:1px solid var(--line)}
.pb-modal-in input{flex:1;border:0;outline:0;background:transparent;color:var(--text);font:inherit;font-size:18px;min-width:0}
.pb-modal-in input::-webkit-search-cancel-button{display:none}
.pb-esc{border:1px solid var(--line);background:var(--soft);color:var(--muted);border-radius:8px;padding:5px 9px;font-size:12px;font-weight:700}
.pb-res{overflow:auto;padding:8px}
.pb-res-h{padding:8px 10px 6px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
.pb-hit{display:flex;gap:12px;align-items:flex-start;padding:12px;border-radius:12px;text-decoration:none;color:var(--text)}
.pb-hit:hover,.pb-hit.sel{background:var(--soft)}
.pb-hit .e{display:grid;place-items:center;width:38px;height:38px;border-radius:11px;background:var(--soft);font-size:19px;flex:none}
.pb-hit>span:last-child{display:flex;flex-direction:column;gap:2px;min-width:0}.pb-hit small{color:var(--muted);font-size:13.5px;line-height:1.45}.pb-hit small.g{color:var(--acc);font-weight:700;font-size:12px}
.pb-hit mark{background:#FFE4CC;color:inherit;border-radius:3px;padding:0 2px}html[data-theme=dark] .pb-hit mark{background:#5A3415}
.pb-res-none{padding:24px 14px;color:var(--muted)}
body.modal-open{overflow:hidden}
.pb-setpass .ic{font-size:24px}
@media (max-width:980px){
 .pb-search-btn{flex:1;margin-left:4px}.pb-search-btn .t,.pb-search-btn kbd{display:none}.pb-search-btn{flex:0 0 40px;width:40px;height:40px;padding:0;justify-content:center;margin-left:auto;order:3}
 .pb-top .grow{display:none}.pb-hide-m{display:none!important}.pb-top .pb-logo{flex:1}
 #pbTheme{order:4}.pb-me{order:5}.pb-menu-btn#pbMenu{order:0}
 .pb-side-top{display:flex;align-items:center;justify-content:space-between;margin:0 0 14px;font-size:18px}
 .pb-hero{flex-direction:column-reverse;align-items:stretch;padding:24px 20px;gap:16px}
 .pb-hero-r{width:100%;height:auto;display:flex;align-items:center;gap:14px}.pb-ring.big{width:84px;height:84px}.pb-ring-t{position:static;align-items:flex-start}.pb-ring-t b{font-size:28px}.pb-ring-t small{color:#B9C5DE}
 .pb-hero-search{font-size:14.5px;padding:13px 14px}
 .pb-hero-acts .pb-btn{width:100%;white-space:normal;text-align:left;justify-content:flex-start}
 .pb-stats{grid-template-columns:repeat(2,1fr);gap:10px}.pb-stats b{font-size:22px}
 .pb-sits{grid-template-columns:1fr}.pb-secs{grid-template-columns:1fr}
 .pb-support{flex-wrap:wrap}.pb-support .pb-btn{width:100%}
 .pb-lh{gap:12px}.pb-save{padding:10px;border-radius:12px}.pb-save span{display:none}
 .pb-main{padding-bottom:110px}
 .pb-tabs{display:grid;grid-template-columns:repeat(4,1fr);position:fixed;left:12px;right:12px;bottom:12px;z-index:45;background:color-mix(in srgb,var(--surface) 86%,transparent);-webkit-backdrop-filter:blur(18px) saturate(170%);backdrop-filter:blur(18px) saturate(170%);border:1px solid var(--line);border-radius:20px;padding:6px;box-shadow:0 14px 40px rgba(10,22,51,.18)}
 .pb-tabs a,.pb-tabs button{display:flex;flex-direction:column;align-items:center;gap:3px;padding:8px 4px;border:0;background:transparent;color:var(--muted);font-size:11.5px;font-weight:700;text-decoration:none;border-radius:14px}
 .pb-tabs .on{color:var(--acc);background:var(--soft)}
 .pb-modal{padding:12px}.pb-modal-box{max-height:86vh}
}
.pb-count.due{background:#E8336B;color:#fff}.pb-tabs a{position:relative}.pb-tabs .dot{position:absolute;top:6px;right:calc(50% - 16px);width:8px;height:8px;border-radius:50%;background:#E8336B}
@media (max-width:980px){.pb-tabs{grid-template-columns:repeat(5,1fr)}}
/* ===== v3: calm editorial layout ===== */
:root{--bg:#F6F6F3;--surface:#FFFFFF;--text:#14161B;--muted:#5D6470;--soft:#EFEFEA;--line:#E3E3DD;--acc:#1660E8;--acc2:#0F4ACB;--orange:#E8650E;--orange-soft:#FDF0E6;--ok:#15803D;--shadow:none;--r:14px}
html[data-theme=dark]{--bg:#0C0E12;--surface:#14171D;--text:#ECEEF2;--muted:#A1A8B4;--soft:#1C2028;--line:#262B35;--acc:#6B9BFF;--acc2:#8CB2FF;--orange:#FF9A52;--orange-soft:#2A1C10;--ok:#4ADE80}
body{background:var(--bg)!important;font-size:17px;line-height:1.6}
.pb-btn{box-shadow:none!important;border-radius:10px;font-size:16px}
.pb-btn.orange{background:var(--text);color:var(--bg)!important}.pb-btn.orange:hover{background:var(--acc)}
.pb-top{background:var(--surface)!important;-webkit-backdrop-filter:none!important;backdrop-filter:none!important;padding:12px 22px}
.pb-logo{font-size:21px}
.pb-search-btn{border-radius:10px;background:var(--bg);font-size:15.5px;box-shadow:none!important}
.pb-pill,.pb-iconbtn{border-radius:10px}
.pb-av{background:var(--text)!important;color:var(--bg)!important;font-size:15px}
/* sidebar */
.pb-side{padding:22px 18px 40px;background:var(--bg)}
.pb-prog{display:block!important;background:transparent!important;border:0!important;border-bottom:1px solid var(--line)!important;border-radius:0!important;padding:0 6px 16px!important;margin:0 0 12px!important;box-shadow:none!important}
.pb-prog strong{font-size:16px}.pb-prog small{display:block;font-size:14.5px;color:var(--muted);margin:2px 0 10px}
.pb-side-search{border-style:solid;border-radius:10px;background:var(--surface);font-size:15.5px;padding:11px 12px}
.pb-nav a{font-size:15.5px;padding:9px 10px;border-radius:8px;line-height:1.4}
.pb-nav .em{display:none}
.pb-nav .pb-nav-home .em{display:inline-flex;width:22px;color:var(--muted)}
.pb-nav a.on .em{color:inherit}
.pb-grp{margin:2px 0}
.pb-grp summary{font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);padding:16px 10px 6px;border-radius:0}
.pb-grp summary:hover{background:transparent;color:var(--text)}
.pb-grp summary .dot{display:none}
.pb-grp a{margin-left:0;border-left:0;border-radius:8px;padding-left:10px}
.pb-grp a.on{background:var(--surface);color:var(--text);font-weight:700;box-shadow:inset 3px 0 0 var(--c)}
.pb-nav a.done{color:var(--muted)}
.pb-nav a.done .tk{background:transparent;border-color:transparent;color:var(--ok)}
.pb-count{font-size:13px;background:transparent;padding:0;color:var(--muted)}
.pb-count.due{background:var(--orange);color:#fff;padding:1px 8px;border-radius:999px}
/* shared bits */
.pv-line{display:block;height:3px;background:var(--line);border-radius:3px;overflow:hidden}.pv-line i{display:block;height:100%;background:var(--acc);border-radius:3px;transition:width .4s}
.pb-main{padding:44px 48px 90px}
.pb-read.wide{max-width:880px}
.pv-head{margin:0 0 30px}
.pv-date{margin:0 0 6px;font-size:14.5px;font-weight:600;color:var(--muted);letter-spacing:.02em}
.pv-head h1{margin:0;font-size:44px;line-height:1.08;letter-spacing:-1.6px;font-weight:700}
.pv-lead{margin:12px 0 0;font-size:19px;line-height:1.55;color:var(--muted);max-width:620px}
.pv-head.small h1{font-size:36px;letter-spacing:-1.1px}
.pv-continue{display:grid;grid-template-columns:1fr auto;gap:4px 24px;align-items:end;padding:24px 26px;border-radius:var(--r);background:var(--surface);border:1px solid var(--line);text-decoration:none;color:var(--text);transition:border-color .2s}
.pv-continue:hover{border-color:var(--text)}
.pv-continue .k{grid-column:1/-1;font-size:13.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--orange)}
.pv-continue .t{font-size:26px;font-weight:700;letter-spacing:-.6px;line-height:1.2}
.pv-continue .s{grid-column:1;font-size:15.5px;color:var(--muted)}
.pv-continue .go{grid-column:2;grid-row:2/4;align-self:center;font-weight:700;font-size:16px;color:var(--acc);white-space:nowrap}
.pv-continue .pv-prog{grid-column:1/-1;display:flex;align-items:center;gap:14px;margin-top:16px;font-size:14.5px;color:var(--muted)}.pv-continue .pv-prog .pv-line{flex:1}
.pv-search{display:flex;align-items:center;gap:12px;width:100%;margin:14px 0 0;padding:15px 16px;border-radius:var(--r);border:1px solid var(--line);background:var(--surface);color:var(--muted);font-size:16.5px;text-align:left}
.pv-search:hover{border-color:var(--text)}.pv-search span{flex:1}
.pv-tools{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:14px 0 0}
.pv-tool{display:flex;flex-direction:column;gap:4px;padding:18px 20px;border-radius:var(--r);border:1px solid var(--line);background:var(--surface);text-decoration:none;color:var(--text);transition:border-color .2s}
.pv-tool:hover{border-color:var(--text)}
.pv-tool .n{font-size:13.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
.pv-tool b{font-size:18px;font-weight:600;line-height:1.35}.pv-tool b b{font-weight:800}.pv-tool em{font-style:normal;color:var(--orange);font-weight:700}
.pv-sec{margin:52px 0 0}
.pv-sec-h{display:flex;align-items:baseline;justify-content:space-between;gap:16px;flex-wrap:wrap;border-bottom:1px solid var(--line);padding-bottom:12px;margin-bottom:4px}
.pv-sec-h h2{margin:0;font-size:24px;letter-spacing:-.5px}.pv-sec-h p{margin:0;color:var(--muted);font-size:15.5px}
.pv-list{display:grid}
.pv-list.two{grid-template-columns:1fr 1fr;column-gap:36px}
.pv-item{display:flex;align-items:center;gap:12px;padding:16px 2px;border-bottom:1px solid var(--line);text-decoration:none;color:var(--text);font-size:17px;font-weight:500}
.pv-item:hover{color:var(--acc)}.pv-item span{flex:1}.pv-item small{display:block;font-size:14.5px;color:var(--muted);font-weight:400}
.pv-item .pb-i{color:var(--muted);width:18px;height:18px}
.pv-index{list-style:none;margin:0;padding:0}
.pv-index a{display:grid;grid-template-columns:52px 1fr auto;gap:2px 16px;align-items:center;padding:18px 2px;border-bottom:1px solid var(--line);text-decoration:none;color:var(--text)}
.pv-index a:hover b{color:var(--acc)}
.pv-index .no{grid-row:1/3;font-size:15px;font-weight:700;color:var(--muted);font-variant-numeric:tabular-nums}
.pv-index b{font-size:19px;font-weight:700;letter-spacing:-.3px}
.pv-index small{grid-column:2;font-size:15px;color:var(--muted)}
.pv-index .st{grid-row:1/3;grid-column:3;font-size:14.5px;font-weight:600;color:var(--muted);white-space:nowrap}
.pv-index .st.ok{color:var(--ok)}
.pv-foot{margin:40px 0 0;padding:20px 0 0;border-top:1px solid var(--line);font-size:16.5px;color:var(--muted)}.pv-foot a{font-weight:700}
.pb-lib{grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;margin-top:14px}
.pb-libc{box-shadow:none;border-radius:var(--r);padding:14px}.pb-libc b{font-size:16.5px}.pb-libc small{font-size:14px}
.pb-libc .pb-bar{height:3px;background:var(--line)}
.pb-libc .pb-bar i{background:var(--acc)}
/* lesson */
.pb-crumb{font-size:15px;margin:0 0 22px}
.pv-lh{margin:0 0 30px;padding-bottom:22px;border-bottom:1px solid var(--line)}
.pv-kicker{margin:0 0 10px;font-size:14px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--c,var(--acc))}
.pv-lh h1{margin:0;font-size:40px;line-height:1.12;letter-spacing:-1.3px}
.pv-meta{display:flex;align-items:center;flex-wrap:wrap;gap:14px;margin-top:14px;font-size:15px;color:var(--muted)}
.pv-meta .ok{color:var(--ok);font-weight:600}
.pb-save{margin-left:auto;border-radius:10px;font-size:15px;padding:8px 12px}
.pb-toc{margin:0 0 26px}.pb-toc a{border-radius:8px;font-size:14.5px;padding:8px 12px;background:var(--surface)}.pb-toc span{font-size:13px}
.pb-content{font-size:18px;line-height:1.75}
.pb-content h2{font-size:25px;margin:40px 0 14px}
.pb-content blockquote{border-left:3px solid var(--c);background:transparent;border-radius:0;padding:4px 0 4px 18px;color:var(--text)}
.pb-script{border:1px solid var(--line)!important;border-left:1px solid var(--line)!important;border-radius:var(--r);box-shadow:none}
.pb-script-h{background:transparent;border-bottom:1px solid var(--line);font-size:14px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);padding:13px 18px}
.pb-script-body{padding:18px 20px 6px}.pb-script>.pb-copy{margin:0 20px 18px}
.pb-copy{border-radius:8px;font-size:14.5px;padding:8px 13px}
.pb-ph{background:var(--orange-soft);border-bottom:1.5px solid var(--orange);border-radius:3px;padding:0 3px}
.pb-callout{background:var(--surface);border:1px solid var(--line);border-radius:var(--r)}
.pb-table{border-radius:var(--r)}.pb-table th{background:var(--soft);font-size:15px}.pb-table td{font-size:16px}
.pb-prompt{border-radius:var(--r)}
.pb-done-row{border-radius:var(--r);box-shadow:none;font-size:16px}
.pb-mark{border-radius:10px;font-size:16px}
.pb-pn a{border-radius:var(--r)}.pb-pn small{font-size:14px}.pb-pn b{font-size:16.5px}
.pbt-embed{border-radius:var(--r);background:var(--surface)!important;border:1px solid var(--line)!important}
.pbt-embed>summary .e{display:none}.pbt-embed>summary b{font-size:18px}.pbt-embed>summary small{font-size:15px}.pbt-embed>summary .go{color:var(--acc)}
/* search modal */
.pb-modal-box{border-radius:16px}.pb-modal-in input{font-size:19px}.pb-hit b{font-size:16.5px}.pb-hit small{font-size:14.5px}.pb-hit .e{background:transparent;width:28px;font-size:18px}
/* tools */
.pbt-today{background:var(--surface)!important;color:var(--text)!important;border:1px solid var(--line);box-shadow:none!important;border-radius:var(--r)}
.pbt-k{color:var(--orange)!important;font-size:13.5px}
.pbt-today h2{font-size:28px}
.pbt-goal{background:var(--bg)!important;border-color:var(--line)!important;color:var(--muted)!important;font-size:15.5px}.pbt-goal b{color:var(--text)!important}
.pbt-goal button{background:var(--surface)!important;color:var(--text)!important;border:1px solid var(--line)!important}
.pbt-streak{color:var(--muted)!important;font-size:15.5px}.pbt-streak b{color:var(--text)!important}
.pbt-ring circle{stroke:var(--line)!important}.pbt-ring .v{stroke:var(--acc)!important}.pbt-ringw small{color:var(--muted)!important;font-size:14px}
.pbt-c,.pbt-card,.pb-tool,.pbt-out,.pbt-me,.pbt-box{box-shadow:none!important;border-radius:var(--r)!important}
.pbt-c .e{display:none}.pbt-c b{font-size:32px}.pbt-c small{font-size:14.5px}
.pbt-pm button{border-radius:8px;height:38px}
.pbt-card-h b{font-size:18px}.pbt-card-h small{font-size:14px}
.pbt-kpis div{border-radius:10px}.pbt-kpis small{font-size:14px}.pbt-kpis em{font-size:13px}
.pbt-due{font-size:16px}.pbt-due .w{font-size:15px}
.pbt-lead-meta{font-size:14.5px}.pbt-lead textarea{font-size:15.5px}
.pbt-chip{border-radius:8px;font-size:14.5px}
.pbt-f{font-size:14.5px}.pbt-f input,.pbt-f select,.pbt-f textarea{font-size:16px}
.pbt-msg-b{font-size:16.5px}.pbt-msg-h{font-size:14px}
.pbt-fill{background:color-mix(in srgb,var(--acc) 10%,transparent)}
.pbt-page-h .emo{display:none}
/* bottom tab bar: docked, never floats */
@media (max-width:980px){
 .pb-top{padding:10px 14px}
 .pb-main{padding:26px 18px calc(100px + env(safe-area-inset-bottom))}
 .pv-head h1{font-size:34px;letter-spacing:-1.1px}.pv-lead{font-size:17.5px}
 .pv-continue{grid-template-columns:1fr;padding:20px}.pv-continue .t{font-size:22px}.pv-continue .go{grid-column:1;grid-row:auto;margin-top:10px}
 .pv-tools{grid-template-columns:1fr}
 .pv-list.two{grid-template-columns:1fr}
 .pv-index a{grid-template-columns:40px 1fr auto}
 .pv-lh h1{font-size:31px;letter-spacing:-.9px}
 .pb-content{font-size:17.5px}
 .pb-save span{display:inline!important}
 .pb-side{background:var(--bg)}
 .pb-tabs{left:0!important;right:0!important;bottom:0!important;border-radius:0!important;border:0!important;border-top:1px solid var(--line)!important;background:var(--surface)!important;-webkit-backdrop-filter:none!important;backdrop-filter:none!important;box-shadow:none!important;padding:6px 4px calc(6px + env(safe-area-inset-bottom))!important;transform:translateZ(0)}
 .pb-tabs a,.pb-tabs button{font-size:13px!important;gap:4px!important;padding:7px 2px!important;border-radius:10px!important;font-weight:600!important}
 .pb-tabs .pb-i{width:24px;height:24px}
 .pb-tabs .on{background:transparent!important;color:var(--acc)!important}
 .pb-tabs .on span{font-weight:800}
 .pbt-toast{bottom:calc(84px + env(safe-area-inset-bottom))}
 .pbt-c b{font-size:28px}
}
/* ===== v4: brand blue + navy, larger type ===== */
:root{--bg:#F2F5FC;--surface:#FFFFFF;--text:#0A1633;--muted:#4A5878;--soft:#E7EDFB;--line:#D9E2F4;--acc:#1660E8;--acc2:#0F4ACB;--orange:#E8650E;--orange-soft:#FFF0E4;--navy:#0A1A4A;--navy2:#071233}
html[data-theme=dark]{--bg:#071233;--surface:#0D1D4D;--text:#EEF3FF;--muted:#AEBBD8;--soft:#132760;--line:#1F3577;--acc:#6EA8FE;--acc2:#9CC2FF;--orange:#FF9A52;--orange-soft:#33244A;--ok:#4ADE80;--navy:#10245E;--navy2:#0A1A4A}
body{font-size:18px;background:var(--bg)!important}
/* buttons + header */
.pb-btn,.pb-btn.orange{background:#1660E8!important;color:#fff!important;font-size:17px;padding:14px 22px;border-radius:12px}
.pb-btn:hover,.pb-btn.orange:hover{background:#0F4ACB!important}
.pb-btn.ghost{background:transparent!important;color:var(--text)!important;border:1px solid var(--line)}
html[data-theme=dark] .pb-btn,html[data-theme=dark] .pb-btn.orange{background:#1660E8!important}
.pb-top{background:var(--surface)!important;border-bottom:1px solid var(--line)}
html[data-theme=dark] .pb-top{background:var(--navy2)!important}
.pb-logo{font-size:23px}.pb-logo span{color:#1660E8}html[data-theme=dark] .pb-logo span{color:#6EA8FE}
.pb-av{background:#1660E8!important;color:#fff!important;width:40px;height:40px;font-size:16px}
.pb-iconbtn{width:44px;height:44px}.pb-i{width:22px;height:22px}
.pb-search-btn{font-size:16.5px;padding:11px 14px}
.pb-pill{font-size:15.5px;padding:10px 15px}
/* sidebar */
.pb-side{background:var(--bg)}
html[data-theme=dark] .pb-side{background:var(--navy2)}
.pb-prog strong{font-size:17px}.pb-prog small{font-size:15.5px}
.pb-side-search{font-size:16.5px;padding:12px 14px}
.pb-nav a{font-size:16.5px;padding:10px 12px}
.pb-grp summary{font-size:14px;padding:18px 12px 8px}
.pb-grp a.on{background:var(--soft);color:var(--acc);box-shadow:inset 3px 0 0 var(--acc)}
.pb-nav-home.on{background:var(--soft)!important;color:var(--acc)!important}
.pv-line i{background:var(--acc)}
/* home */
.pv-date{font-size:16px;color:var(--acc)}
.pv-head h1{font-size:50px;letter-spacing:-1.8px}
.pv-lead{font-size:20.5px}
.pv-head.small h1{font-size:40px}
.pv-continue{background:var(--navy)!important;border:0!important;color:#fff!important;padding:28px 30px}
.pv-continue:hover{background:#0F2366!important}
.pv-continue .k{color:#FFB27A;font-size:14.5px}
.pv-continue .t{font-size:30px;color:#fff}
.pv-continue .s{color:#AEBBD8;font-size:17px}
.pv-continue .go{color:#fff;background:#1660E8;padding:12px 18px;border-radius:12px;font-size:17px}
.pv-continue .pv-prog{color:#AEBBD8;font-size:16px}
.pv-continue .pv-line{background:rgba(255,255,255,.18)}.pv-continue .pv-line i{background:#FF9A52}
.pv-search{font-size:18px;padding:17px 18px}
.pv-tool{padding:20px 22px}.pv-tool .n{font-size:14.5px;color:var(--acc)}.pv-tool b{font-size:19.5px}
.pv-tool:hover,.pv-search:hover{border-color:var(--acc)}
.pv-sec-h h2{font-size:27px}.pv-sec-h p{font-size:16.5px}
.pv-item{font-size:19px;padding:18px 2px}.pv-item small{font-size:15.5px}
.pv-index b{font-size:21px}.pv-index small{font-size:16px}.pv-index .no{font-size:16px;color:var(--acc)}.pv-index .st{font-size:15.5px}
.pv-foot{font-size:17.5px}
.pb-libc b{font-size:17.5px}.pb-libc small{font-size:15px}
/* lesson */
.pb-crumb{font-size:16px}.pb-crumb .sec{color:var(--acc)!important}
.pv-kicker{font-size:15px;color:var(--acc)!important}
.pv-lh h1{font-size:44px}
.pv-meta{font-size:16.5px}
.pb-save{font-size:16px;padding:10px 14px}
.pb-toc a{font-size:15.5px;padding:9px 14px}.pb-toc span{font-size:14px}
.pb-content{font-size:19.5px;line-height:1.75}
.pb-content h2{font-size:27px}.pb-content h3{font-size:22px}
.pb-content blockquote{border-left-color:var(--acc)}
.pb-script-h{font-size:15px;color:var(--acc)}
.pb-copy{font-size:16px;padding:9px 15px}
.pb-table th{font-size:16px}.pb-table td{font-size:17px}
.pb-prompt pre{font-size:16px}
.pb-done-row{font-size:17.5px}.pb-mark{font-size:17px;padding:12px 18px}
.pb-mark.on{background:var(--ok);border-color:var(--ok);color:#fff}
.pb-pn small{font-size:15px}.pb-pn b{font-size:18px}
.pbt-embed>summary b{font-size:19.5px}.pbt-embed>summary small{font-size:16px}.pbt-embed>summary .go{font-size:17px}
/* search */
.pb-modal-in input{font-size:20px}.pb-hit b{font-size:18px}.pb-hit small{font-size:15.5px}.pb-res-h{font-size:13.5px}
/* tracker + writer */
.pbt-today{background:var(--navy)!important;color:#fff!important;border:0!important}
.pbt-today h2{font-size:30px;color:#fff}
.pbt-k{color:#FFB27A!important;font-size:14.5px}
.pbt-goal{background:rgba(255,255,255,.08)!important;border-color:rgba(255,255,255,.18)!important;color:#C7D1E8!important;font-size:16.5px}.pbt-goal b{color:#fff!important;font-size:18px}
.pbt-goal button{background:rgba(255,255,255,.12)!important;border-color:transparent!important;color:#fff!important;width:34px;height:34px}
.pbt-streak{color:#C7D1E8!important;font-size:16.5px}.pbt-streak b{color:#fff!important}
.pbt-ring circle{stroke:rgba(255,255,255,.16)!important}.pbt-ring .v{stroke:#FF9A52!important}.pbt-ringw b{color:#fff;font-size:42px}.pbt-ringw small{color:#C7D1E8!important;font-size:15px}
.pbt-c b{font-size:38px}.pbt-c small{font-size:16px}
.pbt-pm button{height:44px;font-size:22px}
.pbt-pm button:last-child{background:#1660E8;border-color:#1660E8}
.pbt-card-h b{font-size:20px}.pbt-card-h small{font-size:15px}
.pbt-kpis b{font-size:28px}.pbt-kpis small{font-size:15px}
.pbt-due{font-size:17.5px}.pbt-due .w{font-size:16px}
.pbt-add input,.pbt-add select,.pbt-filters input{font-size:17px}
.pbt-lead-top .h{font-size:18px}.pbt-lead-meta{font-size:15.5px}.pbt-lead textarea{font-size:16.5px}.pbt-lead select{font-size:15.5px}
.pbt-chip{font-size:15.5px;padding:10px 15px}.pbt-chip.on{background:#1660E8;border-color:#1660E8}
.pbt-me summary span,.pbt-box-h{font-size:18px}.pbt-me summary small{font-size:15.5px}
.pbt-f{font-size:15.5px}.pbt-f input,.pbt-f select,.pbt-f textarea{font-size:17.5px;padding:12px 14px}
.pbt-out-h b{font-size:17.5px}
.pbt-msg-h{font-size:15px}.pbt-msg-h .n{background:#1660E8;width:26px;height:26px;font-size:13px}
.pbt-msg-b{font-size:18px}
.pbt-lab{font-size:13.5px}
.pbt-lead-note{font-size:16.5px}
/* login */
.pb-card h2{font-size:30px}.pb-card .sub{font-size:17.5px}.pb-f label{font-size:16px}.pb-f input{font-size:17.5px;padding:15px 16px}.pb-links{font-size:16px}.pb-buy{font-size:16.5px}.pb-alert{font-size:16.5px}
.pb-auth-side h1{font-size:50px}.pb-auth-side p{font-size:19px}.pb-auth-list li{font-size:17px}
.pb-auth-main{background:var(--bg)}
@media (max-width:980px){
 body{font-size:17.5px}
 .pv-head h1{font-size:38px}.pv-lead{font-size:19px}
 .pv-head.small h1{font-size:34px}
 .pv-continue{padding:22px}.pv-continue .t{font-size:25px}.pv-continue .go{justify-self:start}
 .pv-item{font-size:18.5px}.pv-index b{font-size:19.5px}
 .pv-lh h1{font-size:34px}
 .pb-content{font-size:19px}
 .pbt-today h2{font-size:25px}.pbt-c b{font-size:32px}
 .pb-tabs{padding:8px 4px calc(8px + env(safe-area-inset-bottom))!important}
 .pb-tabs a,.pb-tabs button{font-size:14px!important;gap:5px!important}
 .pb-tabs .pb-i{width:26px;height:26px}
 .pb-tabs .on{color:#1660E8!important}
 html[data-theme=dark] .pb-tabs{background:var(--navy2)!important}
 html[data-theme=dark] .pb-tabs .on{color:#6EA8FE!important}
 .pb-main{padding-bottom:calc(110px + env(safe-area-inset-bottom))}
 .pb-auth-side h1{font-size:36px}
}
/* ===== v5: deeper, calmer blue ===== */
:root{--acc:#1450C2;--acc2:#0E3F9E;--navy:#081536;--navy2:#060F28}
html[data-theme=dark]{--bg:#040A1C;--surface:#0A1430;--soft:#0F1C40;--line:#18264C;--text:#E9EEF8;--muted:#A3AECA;--acc:#7FA3E6;--acc2:#A5BFF0;--navy:#0B1838;--navy2:#060E26;--orange-soft:#2A2030}
.pb-btn,.pb-btn.orange,html[data-theme=dark] .pb-btn,html[data-theme=dark] .pb-btn.orange,.pv-continue .go,.pb-av,.pbt-pm button:last-child,.pbt-chip.on,.pbt-msg-h .n{background:#1450C2!important;border-color:#1450C2!important}
.pb-btn:hover,.pb-btn.orange:hover{background:#0E3F9E!important}
.pb-btn.ghost{background:transparent!important;border-color:var(--line)!important}
.pb-logo span{color:#1450C2}html[data-theme=dark] .pb-logo span{color:#7FA3E6}
.pv-continue:hover{background:#0B1C46!important}
.pv-continue .pv-line{background:rgba(255,255,255,.14)}
@media (max-width:980px){.pb-tabs .on{color:#1450C2!important}html[data-theme=dark] .pb-tabs .on{color:#7FA3E6!important}}
/* contrast */.pb-buy a{color:#B4490A!important}html[data-theme=dark] .pb-buy a{color:#FFB27A!important}
CSS;
}

function ir_pb_head( $title ) {
	?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $title ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Host+Grotesk:wght@400..800&display=swap">
<link rel="icon" href="<?php echo esc_url( get_site_icon_url( 64 ) ); ?>">
<script>(function(){try{var t=localStorage.getItem('ir-theme');if(t==='dark'||(!t&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.setAttribute('data-theme','dark')}catch(e){}})();</script>
<style><?php echo ir_pb_css(); ?></style></head><body><?php
}

function ir_pb_render( $view, $msg = '', $err = '' ) {
	if ( $view === 'app' ) { ir_pb_render_app( $err ); return; }
	ir_pb_head( 'Student Login | Igho Reuben' );
	$alert = ( $err ? '<div class="pb-alert err">' . esc_html( $err ) . '</div>' : '' ) . ( $msg ? '<div class="pb-alert ok">' . esc_html( $msg ) . '</div>' : '' );
	$cur = wp_get_current_user();
	?>
<div class="pb-auth">
 <aside class="pb-auth-side">
  <a class="pb-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">Igho Reuben<span>.</span></a>
  <div>
   <h1>Student <em>Dashboard</em></h1>
   <p>One login for everything you bought: your courses, playbooks, downloads and DM tools.</p>
   <ul class="pb-auth-list">
    <li><b>🎓</b>Your courses and progress</li>
    <li><b>📘</b>Playbooks, templates and downloads</li>
    <li><b>📈</b>DM tracker and DM writer</li>
    <li><b>💬</b>Support from Reuben</li>
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
  <h2>Your dashboard is empty for now</h2>
  <p class="sub">You are logged in as <b><?php echo esc_html( $cur->user_email ); ?></b>. Courses and playbooks you buy with this email show up here.</p>
  <a class="pb-btn orange" style="width:100%" href="<?php echo esc_url( ir_pb_sales_url() ); ?>">Get the Cold DM Playbook for ₦4,500</a>
  <a class="pb-btn ghost" style="width:100%;margin-top:10px" href="<?php echo esc_url( home_url( '/academy/' ) ); ?>">Browse courses</a>
  <div class="pb-sep">Paid with another email?</div>
  <form class="pb-f" method="post"><input type="hidden" name="pb_act" value="claim">
   <label for="cemail">Email you paid with</label><input id="cemail" type="email" name="email" required placeholder="you@email.com">
   <button class="pb-btn ghost">Send my access link</button></form>
  <div class="pb-links"><a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'logout', '1', ir_pb_url() ), 'ir_pb_logout' ) ); ?>">Log out</a><a href="https://wa.me/2348104699214" target="_blank" rel="noopener">WhatsApp support</a></div>
<?php else : ?>
  <div class="pb-pane" data-pane="login">
   <h2>Welcome back</h2><p class="sub">Log in to your student dashboard.</p>
   <?php echo $alert; ?>
   <?php if ( ! empty( $GLOBALS['ir_pb_force'] ) ) : ?>
   <form class="pb-f" method="post" style="margin-bottom:18px"><input type="hidden" name="pb_act" value="force"><input type="hidden" name="tok" value="<?php echo esc_attr( $GLOBALS['ir_pb_force'] ); ?>"><button class="pb-btn orange">Log out the other device and continue</button></form>
   <?php endif; ?>
   <form class="pb-f" method="post"><input type="hidden" name="pb_act" value="login">
    <label for="log">Email</label><input id="log" type="text" name="log" required autocomplete="username" placeholder="you@email.com" value="<?php echo esc_attr( wp_unslash( $_POST['log'] ?? '' ) ); ?>">
    <label for="pwd">Password</label><input id="pwd" type="password" name="pwd" required autocomplete="current-password">
    <button class="pb-btn">Log in</button></form>
   <div class="pb-links"><a href="#forgot" data-go="forgot">Forgot password?</a><a href="#claim" data-go="claim">Bought something but no login?</a></div>
   <div class="pb-sep">New here?</div>
   <div class="pb-buy"><span>Not a student yet?</span><a href="<?php echo esc_url( home_url( '/academy/' ) ); ?>">Browse courses →</a></div>
  </div>
  <div class="pb-pane" data-pane="forgot" hidden>
   <h2>Reset your password</h2><p class="sub">Enter your email and I'll send you a link to choose a new password.</p>
   <form class="pb-f" method="post"><input type="hidden" name="pb_act" value="forgot">
    <label for="femail">Email</label><input id="femail" type="email" name="email" required placeholder="you@email.com">
    <button class="pb-btn">Send reset link</button></form>
   <div class="pb-links"><a href="#login" data-go="login">← Back to log in</a></div>
  </div>
  <div class="pb-pane" data-pane="claim" hidden>
   <h2>Get your login</h2><p class="sub">Paid for a course or playbook but never got your login? Enter the email you paid with.</p>
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

function ir_pb_sec_meta() {
	return [
		'start'      => [ '#1660E8', '🚀', 'Read these first' ],
		'before'     => [ '#7C3AED', '🧲', 'Offer, prospects and AI' ],
		'scripts'    => [ '#FF7A1A', '✍️', '7 copy-and-send openers' ],
		'objections' => [ '#E8336B', '🛡️', 'What to say when they push back' ],
		'followup'   => [ '#0891B2', '📅', 'Day 2 to day 30' ],
		'closing'    => [ '#16A34A', '🤝', 'From reply to paying client' ],
		'bonuses'    => [ '#CA8A04', '🎁', 'Extra strategies and limits' ],
	];
}

function ir_pb_situations() {
	return [
		[ '✍️', 'I want to send my first DM', 'cps-framework' ],
		[ '🔍', 'I need people to message', 'how-to-find-20-ideal-prospects-in-20-minutes' ],
		[ '🤖', 'Personalise a script fast', '4-ai-prompt-fill-in-the-blanks-fast' ],
		[ '👀', 'They haven\'t opened my DM', 'not-seen' ],
		[ '👻', 'They saw it and went quiet', 'seen-but-no-response-ghosting' ],
		[ '💸', 'They said it\'s too expensive', 'it-s-too-expensive' ],
		[ '🤔', 'They want to think about it', 'i-will-think-about-it' ],
		[ '📅', 'When should I follow up?', 'follow-up-strategies' ],
		[ '📞', 'I got their WhatsApp. Now what?', 'discovery-call-script-from-call-to-closed-client' ],
		[ '📉', 'My DMs aren\'t getting replies', 'outreach-audit-diagnose-why-your-dms-aren-t-working' ],
	];
}

function ir_pb_render_app( $err ) {
	$u       = wp_get_current_user();
	$lib     = ir_pb_library( $u->ID );
	if ( ! ir_pb_has_access() ) { ir_pb_render_library( $u, $lib ); return; }
	$lessons = ir_pb_lessons();
	$data    = ir_pb_data();
	$slugs   = wp_list_pluck( $lessons, 'slug' );
	$by      = array_combine( $slugs, $lessons );
	$done    = array_values( array_intersect( (array) get_user_meta( $u->ID, 'ir_pb_done', true ), $slugs ) );
	$saved   = array_values( array_intersect( (array) get_user_meta( $u->ID, 'ir_pb_saved', true ), $slugs ) );
	$total   = count( $lessons );
	$pct     = $total ? round( count( $done ) / $total * 100 ) : 0;
	$cur     = get_query_var( 'ir_pb_l' );
	$idx     = $cur ? array_search( $cur, $slugs, true ) : false;
	$lesson  = $idx !== false ? $lessons[ $idx ] : null;
	$tool    = in_array( $cur, [ 'tracker', 'writer' ], true ) ? $cur : '';
	if ( $cur && ! $lesson && ! $tool ) { wp_safe_redirect( ir_pb_url() ); exit; }
	$trk     = get_user_meta( $u->ID, 'ir_pb_trk', true ); $trk = is_array( $trk ) ? $trk : [];
	$due     = 0; foreach ( (array) ( $trk['leads'] ?? [] ) as $ld ) { if ( ! empty( $ld['next'] ) && $ld['next'] <= wp_date( 'Y-m-d' ) ) { $due++; } }
	$last    = get_user_meta( $u->ID, 'ir_pb_last', true );
	if ( $lesson ) { update_user_meta( $u->ID, 'ir_pb_last', $lesson['slug'] ); }
	$first   = $u->first_name ?: $u->display_name;
	$next    = null;
	foreach ( $lessons as $l ) { if ( ! in_array( $l['slug'], $done, true ) ) { $next = $l; break; } }
	$resume  = ( $last && isset( $by[ $last ] ) && ! in_array( $last, $done, true ) ) ? $by[ $last ] : ( $next ?: $lessons[0] );
	$needs   = get_user_meta( $u->ID, 'ir_pb_needs_pass', true );
	$logout  = wp_nonce_url( add_query_arg( 'logout', '1', ir_pb_url() ), 'ir_pb_logout' );
	$sm      = ir_pb_sec_meta();
	$cur_key = $lesson ? $lesson['skey'] : '';
	$circ    = 2 * M_PI * 26;

	ir_pb_head( ( $lesson ? $lesson['title'] . ' | ' : ( $tool ? ( $tool === 'tracker' ? 'DM Tracker | ' : 'DM Writer | ' ) : '' ) ) . 'The Cold DM Playbook' );
	?>
<header class="pb-top">
 <button class="pb-iconbtn pb-menu-btn" id="pbMenu" aria-label="Open contents"><?php echo ir_pb_icon( 'menu' ); ?></button>
 <a class="pb-logo" href="<?php echo esc_url( ir_pb_url() ); ?>">Igho Reuben<span>.</span></a>
 <button class="pb-search-btn" data-search type="button"><?php echo ir_pb_icon( 'search' ); ?><span class="t">Search scripts, objections, follow-ups…</span><kbd>/</kbd></button>
 <div class="grow"></div>
 <a class="pb-pill pb-hide-m" href="<?php echo esc_url( $data['support'] ); ?>" target="_blank" rel="noopener"><?php echo ir_pb_icon( 'chat' ); ?><span class="t">Support group</span></a>
 <button class="pb-iconbtn" id="pbTheme" aria-label="Switch light or dark"><?php echo ir_pb_icon( 'moon' ); ?></button>
 <div class="pb-me"><button class="pb-av" id="pbMe" aria-label="Account"><?php echo esc_html( strtoupper( mb_substr( $first, 0, 1 ) ) ); ?></button>
  <div class="pb-me-menu" id="pbMeMenu" hidden><div class="pb-me-h"><b><?php echo esc_html( $first ); ?></b><small><?php echo esc_html( $u->user_email ); ?></small></div>
   <a href="<?php echo esc_url( $data['support'] ); ?>" target="_blank" rel="noopener"><?php echo ir_pb_icon( 'chat' ); ?>Support group</a>
   <a href="<?php echo esc_url( home_url( '/dashboard/account/settings/' ) ); ?>"><?php echo ir_pb_icon( 'home' ); ?>Account settings</a>
   <a href="https://wa.me/2348104699214" target="_blank" rel="noopener"><?php echo ir_pb_icon( 'phone' ); ?>WhatsApp Reuben</a>
   <a href="<?php echo esc_url( $logout ); ?>"><?php echo ir_pb_icon( 'out' ); ?>Log out</a></div></div>
</header>
<div class="pb-scrim" id="pbScrim"></div>
<div class="pb-shell">
 <aside class="pb-side" id="pbSide">
  <div class="pb-side-top"><b>Contents</b><button class="pb-iconbtn pb-menu-btn" id="pbClose" aria-label="Close contents"><?php echo ir_pb_icon( 'close' ); ?></button></div>
  <div class="pb-prog"><strong>The Cold DM Playbook</strong><small><span id="pbDoneN"><?php echo count( $done ); ?></span> of <?php echo (int) $total; ?> lessons done</small><span class="pv-line"><i id="pbBarS" style="width:<?php echo (int) $pct; ?>%"></i></span></div>
  <button class="pb-side-search" data-search type="button"><?php echo ir_pb_icon( 'search' ); ?>Search the playbook</button>
  <nav class="pb-nav">
   <a href="<?php echo esc_url( ir_pb_url() ); ?>" class="pb-nav-home<?php echo ( $lesson || $tool ) ? '' : ' on'; ?>"><span class="em"><?php echo ir_pb_icon( 'home' ); ?></span>Dashboard</a>
   <a href="<?php echo esc_url( ir_pb_url( 'tracker' ) ); ?>" class="pb-nav-home<?php echo $tool === 'tracker' ? ' on' : ''; ?>"><span class="em"><?php echo ir_pb_icon( 'chart' ); ?></span>DM Tracker<?php if ( $due ) : ?><span class="pb-count due"><?php echo (int) $due; ?> due</span><?php endif; ?></a>
   <a href="<?php echo esc_url( ir_pb_url( 'writer' ) ); ?>" class="pb-nav-home<?php echo $tool === 'writer' ? ' on' : ''; ?>"><span class="em"><?php echo ir_pb_icon( 'pen' ); ?></span>DM Writer</a>
   <?php if ( $saved ) : ?><a href="<?php echo esc_url( ir_pb_url() ); ?>#saved" class="pb-nav-home"><span class="em"><?php echo ir_pb_icon( 'star' ); ?></span>Saved <span class="pb-count"><?php echo count( $saved ); ?></span></a><?php endif; ?>
   <?php foreach ( $data['sections'] as $s ) :
    $c = $sm[ $s['key'] ][0] ?? '#1660E8'; $ss = wp_list_pluck( $s['lessons'], 'slug' ); $sd = count( array_intersect( $ss, $done ) ); ?>
    <details class="pb-grp" style="--c:<?php echo esc_attr( $c ); ?>" <?php echo ( ! $lesson || $cur_key === $s['key'] ) ? 'open' : ''; ?>>
     <summary><span class="dot"></span><?php echo esc_html( $s['title'] ); ?><span class="pb-count"><?php echo (int) $sd; ?>/<?php echo count( $ss ); ?></span><?php echo ir_pb_icon( 'down' ); ?></summary>
     <?php foreach ( $s['lessons'] as $l ) : $cl = ( $lesson && $lesson['slug'] === $l['slug'] ? 'on ' : '' ) . ( in_array( $l['slug'], $done, true ) ? 'done' : '' ); ?>
      <a href="<?php echo esc_url( ir_pb_url( $l['slug'] ) ); ?>" class="<?php echo esc_attr( trim( $cl ) ); ?>" data-slug="<?php echo esc_attr( $l['slug'] ); ?>"><span class="em"><?php echo esc_html( $l['icon'] ); ?></span><span class="tt"><?php echo esc_html( $l['title'] ); ?></span><span class="tk"><?php echo ir_pb_icon( 'check' ); ?></span></a>
     <?php endforeach; ?>
    </details>
   <?php endforeach; ?>
   <?php if ( $lib['courses'] || $lib['downloads'] ) : ?>
    <details class="pb-grp" style="--c:#0F4ACB" open><summary><span class="dot"></span>My courses &amp; files<span class="pb-count"><?php echo count( $lib['courses'] ) + count( $lib['downloads'] ); ?></span><?php echo ir_pb_icon( 'down' ); ?></summary>
     <?php foreach ( $lib['courses'] as $c ) : ?><a href="<?php echo esc_url( $c['url'] ); ?>"><span class="em">🎓</span><span class="tt"><?php echo esc_html( $c['t'] ); ?></span><span class="pb-count"><?php echo (int) $c['pct']; ?>%</span></a><?php endforeach; ?>
     <?php foreach ( $lib['downloads'] as $d ) : ?><a href="<?php echo esc_url( $d['url'] ); ?>"><span class="em">📥</span><span class="tt"><?php echo esc_html( $d['t'] ); ?></span></a><?php endforeach; ?>
    </details>
   <?php endif; ?>
  </nav>
 </aside>
 <main class="pb-main"><div class="pb-read<?php echo $lesson ? '' : ' wide'; ?>">
  <?php if ( $needs ) : ?>
   <div class="pb-setpass"><span class="ic">🔐</span><p><b>Create a password</b> so you can log in on any device. Your login email is <?php echo esc_html( $u->user_email ); ?>.</p>
    <form method="post"><input type="hidden" name="pb_act" value="newpass"><?php wp_nonce_field( 'ir_pb_newpass' ); ?><input type="password" name="pass" minlength="8" required placeholder="New password (8+ characters)" autocomplete="new-password"><button class="pb-btn">Save</button></form>
    <?php if ( $err ) : ?><p style="flex-basis:100%;color:#B42318"><?php echo esc_html( $err ); ?></p><?php endif; ?></div>
  <?php elseif ( isset( $_GET['saved'] ) || isset( $_GET['welcome'] ) ) : ?>
   <div class="pb-alert ok">Password saved. You can now log in with <?php echo esc_html( $u->user_email ); ?> on any device.</div>
  <?php endif; ?>

  <?php if ( $tool === 'tracker' ) : ?>
   <header class="pv-head small"><p class="pv-date">Tools</p><h1>DM Tracker</h1><p class="pv-lead">Count every DM, keep your streak and never miss a follow-up.</p></header>
   <div id="pbTracker"></div>
  <?php elseif ( $tool === 'writer' ) : ?>
   <header class="pv-head small"><p class="pv-date">Tools</p><h1>DM Writer</h1><p class="pv-lead">Pick a script, add what you do and what you noticed on their page. You get every message, in the right order.</p></header>
   <div id="pbWriter"></div>
  <?php elseif ( ! $lesson ) : ?>
   <?php $hr = (int) wp_date( 'G' ); $greet = $hr < 12 ? 'Good morning' : ( $hr < 17 ? 'Good afternoon' : 'Good evening' ); ?>
   <header class="pv-head">
    <p class="pv-date"><?php echo esc_html( wp_date( 'l, j F' ) ); ?></p>
    <h1><?php echo esc_html( $greet . ', ' . $first ); ?>.</h1>
    <p class="pv-lead"><?php echo count( $done ) ? 'Pick up where you stopped. One DM sent today beats ten saved for later.' : 'Start with the CPS Framework. It takes three minutes, and every script here is built on it.'; ?></p>
   </header>

   <a class="pv-continue" href="<?php echo esc_url( ir_pb_url( $resume['slug'] ) ); ?>">
    <span class="k"><?php echo count( $done ) ? 'Continue reading' : 'Start here'; ?></span>
    <span class="t"><?php echo esc_html( $resume['title'] ); ?></span>
    <span class="s"><?php echo esc_html( $resume['section'] ); ?></span>
    <span class="go">Open lesson →</span>
    <span class="pv-prog"><span class="pv-line"><i style="width:<?php echo (int) $pct; ?>%"></i></span><span><?php echo count( $done ); ?> of <?php echo (int) $total; ?> lessons</span></span>
   </a>
   <button class="pv-search" data-search type="button"><?php echo ir_pb_icon( 'search' ); ?><span>Search scripts, objections and follow-ups</span><kbd>/</kbd></button>
   <div class="pv-tools">
    <a class="pv-tool" href="<?php echo esc_url( ir_pb_url( 'tracker' ) ); ?>"><span class="n">DM Tracker</span><b id="pbTrkMini">Count your DMs and follow-ups</b></a>
    <a class="pv-tool" href="<?php echo esc_url( ir_pb_url( 'writer' ) ); ?>"><span class="n">DM Writer</span><b>Turn your notes into a ready-to-send DM</b></a>
   </div>

   <?php if ( $lib['courses'] || $lib['downloads'] ) : ?>
    <section class="pv-sec"><div class="pv-sec-h"><h2>My courses &amp; files</h2><p>Everything you've bought.</p></div>
    <div class="pb-lib">
     <?php foreach ( $lib['courses'] as $c ) : ?><a class="pb-libc" href="<?php echo esc_url( $c['url'] ); ?>"><?php echo $c['img'] ? '<img src="' . esc_url( $c['img'] ) . '" alt="" loading="lazy">' : '<span class="ph"></span>'; ?><span><b><?php echo esc_html( $c['t'] ); ?></b><small>Course · <?php echo (int) $c['pct']; ?>% complete</small><span class="pb-bar"><i style="width:<?php echo (int) $c['pct']; ?>%"></i></span></span></a><?php endforeach; ?>
     <?php foreach ( $lib['downloads'] as $d ) : ?><a class="pb-libc" href="<?php echo esc_url( $d['url'] ); ?>"><?php echo $d['img'] ? '<img src="' . esc_url( $d['img'] ) . '" alt="" loading="lazy">' : '<span class="ph"></span>'; ?><span><b><?php echo esc_html( $d['t'] ); ?></b><small>Download · <?php echo esc_html( $d['f'] ); ?></small></span></a><?php endforeach; ?>
    </div></section>
   <?php endif; ?>

   <section class="pv-sec"><div class="pv-sec-h"><h2>Find the right script</h2><p>Pick what's happening right now.</p></div>
    <div class="pv-list two">
     <?php foreach ( ir_pb_situations() as $si ) : if ( ! isset( $by[ $si[2] ] ) ) { continue; } ?>
      <a class="pv-item" href="<?php echo esc_url( ir_pb_url( $si[2] ) ); ?>"><span><?php echo esc_html( $si[1] ); ?></span><?php echo ir_pb_icon( 'right' ); ?></a>
     <?php endforeach; ?>
    </div>
   </section>

   <div id="saved"></div>
   <?php if ( $saved ) : ?>
    <section class="pv-sec"><div class="pv-sec-h"><h2>Saved</h2><p>Your shortcuts.</p></div>
     <div class="pv-list two">
      <?php foreach ( $saved as $sl ) : $l = $by[ $sl ]; ?>
       <a class="pv-item" href="<?php echo esc_url( ir_pb_url( $sl ) ); ?>"><span><?php echo esc_html( $l['title'] ); ?><small><?php echo esc_html( $l['section'] ); ?></small></span><?php echo ir_pb_icon( 'right' ); ?></a>
      <?php endforeach; ?>
     </div>
    </section>
   <?php endif; ?>

   <section class="pv-sec"><div class="pv-sec-h"><h2>The playbook</h2><p>7 sections · <?php echo (int) $total; ?> lessons</p></div>
    <ol class="pv-index">
     <?php $no = 0; foreach ( $data['sections'] as $s ) :
      $no++; $m = $sm[ $s['key'] ] ?? [ '', '', '' ]; $ss = wp_list_pluck( $s['lessons'], 'slug' ); $n = count( $ss ); $d = count( array_intersect( $ss, $done ) );
      $first_open = $s['lessons'][0]['slug']; foreach ( $s['lessons'] as $l ) { if ( ! in_array( $l['slug'], $done, true ) ) { $first_open = $l['slug']; break; } } ?>
      <li><a href="<?php echo esc_url( ir_pb_url( $first_open ) ); ?>"><span class="no"><?php echo esc_html( sprintf( '%02d', $no ) ); ?></span><b><?php echo esc_html( $s['title'] ); ?></b><small><?php echo esc_html( $m[2] . ' · ' . $n . ' lesson' . ( $n > 1 ? 's' : '' ) ); ?></small><span class="st<?php echo $d === $n ? ' ok' : ''; ?>"><?php echo $d === $n ? 'Done' : (int) $d . '/' . (int) $n; ?></span></a></li>
     <?php endforeach; ?>
    </ol>
   </section>

   <p class="pv-foot">Stuck on a reply? <a href="<?php echo esc_url( $data['support'] ); ?>" target="_blank" rel="noopener">Post it in the support group</a> and get feedback before you send.</p>

  <?php else :
   $isdone = in_array( $lesson['slug'], $done, true );
   $issaved = in_array( $lesson['slug'], $saved, true );
   $prev = $idx > 0 ? $lessons[ $idx - 1 ] : null; $nxt = $idx < $total - 1 ? $lessons[ $idx + 1 ] : null;
   $pos = 0; $cnt = 0; foreach ( $lessons as $l ) { if ( $l['skey'] === $lesson['skey'] ) { $cnt++; if ( $l['slug'] === $lesson['slug'] ) { $pos = $cnt; } } }
   $words = str_word_count( wp_strip_all_tags( $lesson['html'] ) ); $mins = max( 1, (int) round( $words / 200 ) );
   $c = $sm[ $lesson['skey'] ][0] ?? '#1660E8';
   ?>
   <div class="pb-lesson" style="--c:<?php echo esc_attr( $c ); ?>">
    <div class="pb-crumb"><a href="<?php echo esc_url( ir_pb_url() ); ?>">Dashboard</a><?php echo ir_pb_icon( 'right' ); ?><span class="sec"><?php echo esc_html( $lesson['section'] ); ?></span></div>
    <header class="pv-lh"><p class="pv-kicker"><?php echo esc_html( $lesson['section'] . ' · ' . $pos . ' of ' . $cnt ); ?></p><h1><?php echo esc_html( $lesson['title'] ); ?></h1>
     <div class="pv-meta"><span><?php echo (int) $mins; ?> min read</span><?php if ( $isdone ) : ?><span class="ok">✓ Completed</span><?php endif; ?>
      <button class="pb-save<?php echo $issaved ? ' on' : ''; ?>" id="pbSave" data-slug="<?php echo esc_attr( $lesson['slug'] ); ?>" aria-label="Save lesson"><?php echo ir_pb_icon( 'star' ); ?><span><?php echo $issaved ? 'Saved' : 'Save'; ?></span></button></div></header>
    <nav class="pb-toc" id="pbToc" hidden><span>Jump to</span></nav>
    <?php if ( in_array( $lesson['slug'], ir_pb_writer_lessons(), true ) ) : ?>
     <details class="pbt-embed"><summary><span class="e"></span><span><b>Write this DM for me</b><small>Add your details and what you noticed. Get the messages in order, ready to copy.</small></span><span class="go">Open →</span></summary><div class="pbt-embed-in" id="pbWriterEmbed" data-lesson="<?php echo esc_attr( $lesson['slug'] ); ?>"></div></details>
    <?php endif; ?>
    <article class="pb-content" id="pbContent"><?php echo $lesson['html']; // Built from the owner's own Notion pages. ?></article>
    <div class="pb-done-row"><span><?php echo $isdone ? 'Nice work. This one is done.' : 'Read it? Mark it done to track your progress.'; ?></span>
     <button class="pb-mark<?php echo $isdone ? ' on' : ''; ?>" id="pbMark" data-slug="<?php echo esc_attr( $lesson['slug'] ); ?>" data-next="<?php echo $nxt ? esc_url( ir_pb_url( $nxt['slug'] ) ) : ''; ?>"><?php echo ir_pb_icon( 'check' ); ?><span><?php echo $isdone ? 'Completed' : 'Mark as complete'; ?></span></button></div>
    <nav class="pb-pn">
     <?php if ( $prev ) : ?><a href="<?php echo esc_url( ir_pb_url( $prev['slug'] ) ); ?>"><small><?php echo ir_pb_icon( 'left' ); ?>Previous</small><b><?php echo esc_html( $prev['title'] ); ?></b></a><?php else : ?><span></span><?php endif; ?>
     <?php if ( $nxt ) : ?><a class="nx" href="<?php echo esc_url( ir_pb_url( $nxt['slug'] ) ); ?>"><small>Next<?php echo ir_pb_icon( 'right' ); ?></small><b><?php echo esc_html( $nxt['title'] ); ?></b></a><?php endif; ?>
    </nav>
   </div>
  <?php endif; ?>
 </div></main>
</div>

<nav class="pb-tabs">
 <a href="<?php echo esc_url( ir_pb_url() ); ?>" class="<?php echo ( $lesson || $tool ) ? '' : 'on'; ?>"><?php echo ir_pb_icon( 'home' ); ?><span>Home</span></a>
 <a href="<?php echo esc_url( ir_pb_url( 'tracker' ) ); ?>" class="<?php echo $tool === 'tracker' ? 'on' : ''; ?>"><?php echo ir_pb_icon( 'chart' ); ?><span>Tracker</span><?php if ( $due ) : ?><i class="dot"></i><?php endif; ?></a>
 <a href="<?php echo esc_url( ir_pb_url( 'writer' ) ); ?>" class="<?php echo $tool === 'writer' ? 'on' : ''; ?>"><?php echo ir_pb_icon( 'pen' ); ?><span>Writer</span></a>
 <button type="button" id="pbTabMenu" class="<?php echo $lesson ? 'on' : ''; ?>"><?php echo ir_pb_icon( 'list' ); ?><span>Lessons</span></button>
 <button type="button" data-search><?php echo ir_pb_icon( 'search' ); ?><span>Search</span></button>
</nav>

<div class="pb-modal" id="pbSearch" hidden>
 <div class="pb-modal-box" role="dialog" aria-label="Search the playbook">
  <div class="pb-modal-in"><?php echo ir_pb_icon( 'search' ); ?><input id="pbQ" type="search" placeholder="Search scripts, objections, follow-ups…" autocomplete="off"><button class="pb-esc" id="pbQx" type="button">Esc</button></div>
  <div class="pb-res" id="pbRes"></div>
 </div>
</div>

<style><?php echo file_get_contents( IR_PB_DIR . '/tools.css' ); // phpcs:ignore ?></style>
<script>window.PBT=<?php echo wp_json_encode( [ 'aj' => admin_url( 'admin-ajax.php' ), 'n' => wp_create_nonce( 'ir_pb' ), 'base' => ir_pb_url(), 'state' => $trk ?: new stdClass() ] ); ?>;</script>
<script><?php echo file_get_contents( IR_PB_DIR . '/tools.js' ); // phpcs:ignore ?></script>
<script>
(function(){
 var AJ=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,N=<?php echo wp_json_encode( wp_create_nonce( 'ir_pb' ) ); ?>,BASE=<?php echo wp_json_encode( ir_pb_url() ); ?>,CIRC=<?php echo round( $circ, 3 ); ?>;
 var SIT=<?php echo wp_json_encode( array_values( array_filter( array_map( function ( $s ) use ( $by ) { return isset( $by[ $s[2] ] ) ? [ 'e' => $s[0], 't' => $s[1], 's' => $s[2] ] : null; }, ir_pb_situations() ) ) ) ); ?>;
 var b=document.body;
 function openNav(){b.classList.add('nav-open')}function closeNav(){b.classList.remove('nav-open')}
 ['pbMenu','pbTabMenu'].forEach(function(id){var e=document.getElementById(id);if(e)e.onclick=openNav});
 ['pbScrim','pbClose'].forEach(function(id){var e=document.getElementById(id);if(e)e.onclick=closeNav});
 var on=document.querySelector('.pb-nav a.on');if(on&&on.scrollIntoView)on.scrollIntoView({block:'center'});
 document.getElementById('pbTheme').onclick=function(){var d=document.documentElement,dark=d.getAttribute('data-theme')!=='dark';if(dark)d.setAttribute('data-theme','dark');else d.removeAttribute('data-theme');try{localStorage.setItem('ir-theme',dark?'dark':'light')}catch(e){}};
 var me=document.getElementById('pbMe'),mm=document.getElementById('pbMeMenu');me.onclick=function(e){e.stopPropagation();mm.hidden=!mm.hidden};document.addEventListener('click',function(e){if(!mm.contains(e.target))mm.hidden=true});
 function post(data){var f=new FormData();f.append('n',N);for(var k in data)f.append(k,data[k]);return fetch(AJ,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json()})}
 /* copy buttons */
 function copyText(t,btn,label){var done=function(){btn.classList.add('ok');btn.textContent='Copied ✓';setTimeout(function(){btn.classList.remove('ok');btn.textContent=label},1600)};
  if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(t).then(done)}else{var a=document.createElement('textarea');a.value=t;document.body.appendChild(a);a.select();try{document.execCommand('copy')}catch(e){}a.remove();done()}}
 document.querySelectorAll('.pb-content .pb-copy').forEach(function(btn){var label=btn.textContent;btn.addEventListener('click',function(){var box=btn.closest('.pb-script,.pb-prompt');if(!box)return;var src=box.querySelector('.pb-script-body,pre');copyText((src.innerText||src.textContent).replace(/\n{3,}/g,'\n\n').trim(),btn,label)})});
 /* jump-to chips */
 var ct=document.getElementById('pbContent'),toc=document.getElementById('pbToc');
 if(ct&&toc){var hs=ct.querySelectorAll('h2,.pb-script-h');if(hs.length>2){hs.forEach(function(h,i){var t=h.textContent.replace(/^[^\w"'(]+/,'').trim();if(!t)return;var id='s'+(i+1);(h.classList.contains('pb-script-h')?h.parentNode:h).id=id;var a=document.createElement('a');a.href='#'+id;a.textContent=t.length>34?t.slice(0,32)+'…':t;toc.appendChild(a)});toc.hidden=false}}
 /* mark complete */
 var mk=document.getElementById('pbMark');
 if(mk)mk.addEventListener('click',function(){var now=!mk.classList.contains('on');mk.classList.toggle('on',now);mk.querySelector('span').textContent=now?'Completed':'Mark as complete';
  var link=document.querySelector('.pb-nav a[data-slug="'+mk.dataset.slug+'"]');if(link)link.classList.toggle('done',now);
  post({action:'ir_pb_progress',slug:mk.dataset.slug,done:now?'1':''}).then(function(j){if(j&&j.ok){document.getElementById('pbDoneN').textContent=j.done;document.getElementById('pbBarS').style.width=Math.round(j.done/j.total*100)+'%'}});
  if(now&&mk.dataset.next)setTimeout(function(){location.href=mk.dataset.next},700)});
 /* save */
 var sv=document.getElementById('pbSave');
 if(sv)sv.addEventListener('click',function(){var now=!sv.classList.contains('on');sv.classList.toggle('on',now);sv.querySelector('span').textContent=now?'Saved':'Save';post({action:'ir_pb_save',slug:sv.dataset.slug,on:now?'1':''})});
 /* search */
 var modal=document.getElementById('pbSearch'),q=document.getElementById('pbQ'),res=document.getElementById('pbRes'),IDX=null,sel=0;
 function esc(s){return s.replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]})}
 var PEND=null;function load(){if(IDX)return Promise.resolve(IDX);if(PEND)return PEND;try{var c=sessionStorage.getItem('ir_pb_idx');if(c){IDX=JSON.parse(c);return Promise.resolve(IDX)}}catch(e){}
  PEND=post({action:'ir_pb_index'}).then(function(j){IDX=j||[];try{sessionStorage.setItem('ir_pb_idx',JSON.stringify(IDX))}catch(e){}return IDX});return PEND}
 function empty(){res.innerHTML='<div class="pb-res-h">Popular</div>'+SIT.slice(0,6).map(function(s){return '<a class="pb-hit" href="'+BASE+s.s+'/"><span class="e">'+s.e+'</span><span><b>'+esc(s.t)+'</b></span></a>'}).join('');sel=-1}
 function run(){var v=q.value.trim().toLowerCase();if(!v){empty();return}
  load().then(function(ix){if(q.value.trim().toLowerCase()!==v)return;var words=v.split(/\s+/).filter(Boolean);var out=[];
   ix.forEach(function(l){var t=l.t.toLowerCase(),x=l.x.toLowerCase(),g=l.g.toLowerCase(),sc=0,ok=true;
    words.forEach(function(w){var a=t.indexOf(w),c=x.indexOf(w),d=g.indexOf(w);if(a<0&&c<0&&d<0){ok=false;return}sc+=(a>=0?(a===0?14:9):0)+(d>=0?3:0)+(c>=0?Math.min(6,1+x.split(w).length-1):0)});
    if(ok)out.push({l:l,sc:sc})});
   out.sort(function(a,b){return b.sc-a.sc});
   if(!out.length){res.innerHTML='<div class="pb-res-none">No lessons match “'+esc(v)+'”. Try a simpler word like <b>price</b>, <b>ghost</b> or <b>follow</b>.</div>';return}
   res.innerHTML='<div class="pb-res-h">'+out.length+' result'+(out.length>1?'s':'')+'</div>'+out.slice(0,12).map(function(o){var l=o.l,x=l.x,i=x.toLowerCase().indexOf(words[0]),sn='';
    if(i>=0){var s=Math.max(0,i-50);sn=(s?'… ':'')+x.slice(s,i+110).trim()+' …'}else{sn=x.slice(0,140)+'…'}
    sn=esc(sn);words.forEach(function(w){if(w.length>1)sn=sn.replace(new RegExp('('+w.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')','ig'),'<mark>$1</mark>')});
    return '<a class="pb-hit" href="'+BASE+l.s+'/"><span class="e">'+l.i+'</span><span><b>'+esc(l.t)+'</b><small class="g">'+esc(l.g)+'</small><small>'+sn+'</small></span></a>'}).join('');sel=-1})}
 function openS(){modal.hidden=false;b.classList.add('modal-open');closeNav();q.value='';empty();setTimeout(function(){q.focus()},30);load()}
 function closeS(){modal.hidden=true;b.classList.remove('modal-open')}
 document.querySelectorAll('[data-search]').forEach(function(e){e.addEventListener('click',openS)});
 document.getElementById('pbQx').onclick=closeS;modal.addEventListener('click',function(e){if(e.target===modal)closeS()});
 q.addEventListener('input',run);
 q.addEventListener('keydown',function(e){var hits=res.querySelectorAll('.pb-hit');if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();sel=Math.max(0,Math.min(hits.length-1,sel+(e.key==='ArrowDown'?1:-1)));hits.forEach(function(h,i){h.classList.toggle('sel',i===sel)});if(hits[sel])hits[sel].scrollIntoView({block:'nearest'})}else if(e.key==='Enter'){var h=hits[sel>=0?sel:0];if(h)location.href=h.href}});
 document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeS();closeNav();mm.hidden=true}else if((e.key==='/'&&!/input|textarea/i.test(document.activeElement.tagName))||(e.key.toLowerCase()==='k'&&(e.metaKey||e.ctrlKey))){e.preventDefault();openS()}});
})();
</script>
</body></html><?php
}

function ir_pb_writer_lessons() {
	return [ 'the-problem-focused-approach', 'result-oriented-approach', 'value-based-approach', 'compliment-quick-audit-approach', 'the-direct-approach', 'soft-question-based-opener', 'niche-expertise-opener', 'not-seen', 'after-2-days', 'after-5-days', 'after-7-days', 'after-a-month' ];
}

/** Members who own courses or downloads but not the playbook. */
function ir_pb_render_library( $u, $lib ) {
	$first  = $u->first_name ?: $u->display_name;
	$logout = wp_nonce_url( add_query_arg( 'logout', '1', ir_pb_url() ), 'ir_pb_logout' );
	ir_pb_head( 'My Dashboard | Igho Reuben' );
	?>
<header class="pb-top">
 <a class="pb-logo" href="<?php echo esc_url( ir_pb_url() ); ?>">Igho Reuben<span>.</span></a>
 <div class="grow"></div>
 <button class="pb-iconbtn" id="pbTheme" aria-label="Switch light or dark"><?php echo ir_pb_icon( 'moon' ); ?></button>
 <a class="pb-iconbtn" href="<?php echo esc_url( $logout ); ?>" aria-label="Log out" title="Log out"><?php echo ir_pb_icon( 'out' ); ?></a>
 <span class="pb-av"><?php echo esc_html( strtoupper( mb_substr( $first, 0, 1 ) ) ); ?></span>
</header>
<main class="pb-main"><div class="pb-read wide">
 <header class="pv-head"><p class="pv-date"><?php echo esc_html( wp_date( 'l, j F' ) ); ?></p><h1>Welcome, <?php echo esc_html( $first ); ?>.</h1><p class="pv-lead">Everything you've bought lives here. Pick up where you stopped.</p></header>
 <h2 class="pb-h2">My courses &amp; files</h2><p class="pb-h2-sub">One login for everything.</p>
 <div class="pb-lib">
  <?php foreach ( $lib['courses'] as $c ) : ?><a class="pb-libc" href="<?php echo esc_url( $c['url'] ); ?>"><?php echo $c['img'] ? '<img src="' . esc_url( $c['img'] ) . '" alt="" loading="lazy">' : '<span class="ph">🎓</span>'; ?><span><b><?php echo esc_html( $c['t'] ); ?></b><small>Course · <?php echo (int) $c['pct']; ?>% complete</small><span class="pb-bar"><i style="width:<?php echo (int) $c['pct']; ?>%"></i></span></span></a><?php endforeach; ?>
  <?php foreach ( $lib['downloads'] as $d ) : ?><a class="pb-libc" href="<?php echo esc_url( $d['url'] ); ?>"><?php echo $d['img'] ? '<img src="' . esc_url( $d['img'] ) . '" alt="" loading="lazy">' : '<span class="ph">📥</span>'; ?><span><b><?php echo esc_html( $d['t'] ); ?></b><small>Download · <?php echo esc_html( $d['f'] ); ?></small></span></a><?php endforeach; ?>
  <a class="pb-libc locked" href="<?php echo esc_url( ir_pb_sales_url() ); ?>"><span class="ph">🔒</span><span><b>The Cold DM Playbook</b><small>Scripts, DM writer and tracker · ₦4,500</small><small style="color:var(--orange);font-weight:700">Unlock it →</small></span></a>
 </div>
</div></main>
<style><?php echo file_get_contents( IR_PB_DIR . '/tools.css' ); // phpcs:ignore ?></style>
<script>document.getElementById('pbTheme').onclick=function(){var d=document.documentElement,dark=d.getAttribute('data-theme')!=='dark';if(dark)d.setAttribute('data-theme','dark');else d.removeAttribute('data-theme');try{localStorage.setItem('ir-theme',dark?'dark':'light')}catch(e){}};</script>
</body></html><?php
}
