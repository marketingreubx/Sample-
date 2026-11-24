/* ================= Reference recipes: one per reference reel (study of 12 reels) =================
   Each recipe decides layout (full / split screen), how captions look and move, how the key word is shown, how a list is
   shown (headers, checklist, panel, boxes, HUD), full-screen word cards, camera behaviour, music and real sound effects. */
var REF = {
 'ref-chloe':    { cap: { pos: 'top', size: 54, w: 700, n: 3, emph: 'color', anim: 'up' }, hook: 'title', hdr: 'num', list: null, cards: 'paper', layout: null, cam: 'cuts', music: 'lofi_chill', sfx: { hdr: 'pop1', card: 'paper1', hook: 'swoosh1', key: null } },
 'ref-kallaway': { cap: { pos: 'center', size: 66, w: 800, n: 1, emph: 'none', anim: 'cut' }, hook: 'title', hdr: 'red', list: 'panel', cards: 'black', layout: 'kallaway', cam: 'cuts', music: 'synth_dark', sfx: { hdr: 'click1', card: 'whoosh2', hook: 'riser2', split: 'swoosh2' } },
 'ref-social':   { cap: { pos: 'chest', size: 60, w: 700, n: 3, emph: 'none', anim: 'pop' }, hook: null, hdr: 'box', list: null, cards: null, layout: null, cam: 'locked', music: 'lofi_beat', sfx: { hdr: 'pop2' } },
 'ref-wolf':     { cap: { pos: 'chest', size: 56, w: 700, n: 3, emph: 'none', anim: 'pop', upper: true }, stack: 'wolf', hdr: 'num', list: null, cards: 'black', layout: null, cam: 'push', music: 'warm_groove', sfx: { hdr: 'click1', card: 'whoosh3', stack: 'thud2' } },
 'ref-tumi':     { cap: { pos: 'chest', size: 52, w: 600, n: 3, emph: 'none', anim: 'up' }, stack: 'tumi', stackAll: true, hdr: null, list: 'checklist', cards: 'paper', layout: 'tumi', cam: 'cuts', music: 'synth_calm', sfx: { row: 'click2', card: 'paper2', split: 'paper1' } },
 'ref-millie':   { cap: { pos: 'chest', size: 58, w: 700, n: 3, emph: 'script', anim: 'up' }, hook: null, hdr: 'script', list: null, cards: null, layout: null, cam: 'locked', music: 'lofi_soft', sfx: { hdr: 'pop3' } },
 'ref-deshna':   { cap: { pos: 'chest', size: 58, w: 800, n: 3, emph: 'box', anim: 'pop' }, hook: null, hdr: null, list: null, cards: 'paper', layout: null, cam: 'cuts', music: 'lofi_chill', sfx: { card: 'paper3', key: null } },
 'ref-zach':     { cap: { pos: 'seam', size: 44, w: 700, n: 3, emph: 'none', anim: 'cut', pill: true }, hook: 'panel', hdr: null, list: 'panel-light', cards: null, layout: 'zach', cam: 'locked', music: 'lofi_beat', sfx: { row: 'pop1', split: 'swoosh3' } },
 'ref-sky':      { cap: { pos: 'chest', size: 56, w: 500, n: 3, emph: 'color', anim: 'up' }, hook: 'title', hdr: null, list: 'hud', cards: null, layout: null, cam: 'locked', music: 'synth_dark', sfx: { row: 'toggle1', hook: 'riser1' } },
 'ref-adam':     { cap: { pos: 'chest', size: 58, w: 800, n: 3, emph: 'color', anim: 'pop' }, hook: null, hdr: null, list: 'boxes', cards: null, layout: null, cam: 'cuts', music: 'synth_calm', sfx: { row: 'click1' } },
 'ref-cinematic':{ cap: { pos: 'chest', size: 50, w: 600, n: 3, emph: 'serif', anim: 'up' }, stack: 'serif', hdr: null, list: null, cards: 'black', layout: null, cam: 'push', music: 'synth_dark', sfx: { card: 'whoosh4', stack: 'whoosh5' } }
};
function isRef(st) { return !!REF[st.id]; }
/* display text for a word: AI fixes first, then local guards (times are never turned into big numbers) */
function dispWords(words, ai) { var out = words.map(function (x) { return { w: x.w, s: x.s, e: x.e }; }), fx = (ai && ai.fixes) || [];
 fx.forEach(function (f) { var i = +f.i; if (out[i] && f.text && String(f.text).length <= 24) { out[i].w = String(f.text) + (/[.,?!]$/.test(out[i].w) && !/[.,?!]$/.test(f.text) ? out[i].w.slice(-1) : ''); } });
 for (var i = 0; i + 1 < out.length; i++) { var a = out[i].w, b = out[i + 1].w.toLowerCase().replace(/[^a-z.]/g, '');
  if (/^\d{1,2}([:.]\d{2})?$/.test(a.replace(/[.,]$/, '')) && /^(am|pm|a\.m\.?|p\.m\.?)$/.test(b)) { out[i].w = a.replace(/[.,]$/, '') + ' ' + (b.charAt(0) === 'a' ? 'AM' : 'PM') + (/[.,?!]$/.test(out[i + 1].w) ? out[i + 1].w.slice(-1) : ''); out[i + 1].w = ''; } }
 return out; }
/* the AI plan (site AI reads the transcript); null when unavailable, the editor then plans by itself */
function aiPlan(words, sid) { if (!C.aj || !C.n) { return Promise.resolve(null); }
 var fd = new FormData(); fd.append('action', 'ir_rx_plan'); fd.append('n', C.n); fd.append('style', sid); fd.append('words', JSON.stringify(words.map(function (x) { return x.w; })));
 return Promise.race([fetch(C.aj, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { return j && j.ok && j.plan ? j.plan : null; }).catch(function () { return null; }), new Promise(function (r) { setTimeout(function () { r(null); }, 30000); })]); }
/* normalise AI plan or heuristic plan into one shape */
function refPlan(words, plan, ai) { var n = words.length, ok = function (i) { return typeof i === 'number' && i >= 0 && i < n; }, P = { keys: {}, stacks: [], items: [], cards: [], hook: null, title: '', cta: null };
 if (ai) {
  (ai.keywords || []).forEach(function (i) { if (ok(+i)) { P.keys[+i] = 1; } });
  (ai.stacks || []).forEach(function (s) { if (ok(+s.from) && ok(+s.to) && s.big) { P.stacks.push({ a: +s.from, b: +s.to, lead: String(s.lead || ''), big: String(s.big), tail: String(s.tail || '') }); } });
  ((ai.list && ai.list.items) || []).forEach(function (it, k) { if (ok(+it.from) && ok(+it.to) && it.label) { P.items.push({ a: +it.from, b: Math.max(+it.to, +it.from), n: +it.n || k + 1, label: String(it.label) }); } });
  P.title = ai.list && ai.list.title ? String(ai.list.title) : (ai.topic ? String(ai.topic) : '');
  (ai.cards || []).forEach(function (c) { if (ok(+c.from) && ok(+c.to) && c.text) { P.cards.push({ a: +c.from, b: +c.to, lead: String(c.lead || ''), text: String(c.text) }); } });
  if (ai.hook && ok(+ai.hook.from) && ai.hook.text) { P.hook = { a: +ai.hook.from, b: ok(+ai.hook.to) ? +ai.hook.to : +ai.hook.from, text: String(ai.hook.text) }; }
  if (ai.cta && ok(+ai.cta.from) && ai.cta.keyword) { P.cta = { a: +ai.cta.from, keyword: String(ai.cta.keyword).toUpperCase().replace(/[^\w-]/g, '').slice(0, 20) }; }
 } else {
  Object.keys(plan.key || {}).forEach(function () {}); words.forEach(function (x, i) { if (plan.key && plan.key[cleanW(x.w).toLowerCase()]) { P.keys[i] = 1; } });
  (plan.punch || []).forEach(function (i) { P.keys[i] = 1; });
  (plan.blocks || []).forEach(function (b) { P.stacks.push({ a: b.w[0], b: b.w[2], lead: b.lead, big: b.text, tail: '' }); });
  (plan.headers || []).forEach(function (h) { P.items.push({ a: h.w[0], b: h.w[1], n: h.n, label: cap1(h.text) }); });
  (plan.wcards || []).forEach(function (c) { P.cards.push({ a: c.w[0], b: c.w[1], lead: c.lead, text: c.text }); });
  if (plan.cta) { P.cta = { a: plan.cta.w ? plan.cta.w[0] : 0, keyword: plan.cta.keyword }; }
  var s0 = sentences(words)[0] || []; if (s0.length >= 3) { P.hook = { a: s0[0], b: s0[Math.min(s0.length - 1, 7)], text: s0.slice(0, 7).map(function (i) { return cleanW(words[i].w); }).join(' ') }; } }
 P.items.sort(function (x, y) { return x.a - y.a; }); for (var k = 0; k < P.items.length; k++) { if (P.items[k + 1] && P.items[k].b >= P.items[k + 1].a) { P.items[k].b = P.items[k + 1].a - 1; } }
 return P; }
function cap1(x) { x = String(x || ''); return x.charAt(0).toUpperCase() + x.slice(1); }
function rrectP(c, x, y, w, h, r) { c.beginPath(); c.moveTo(x + r, y); c.arcTo(x + w, y, x + w, y + h, r); c.arcTo(x + w, y + h, x, y + h, r); c.arcTo(x, y + h, x, y, r); c.arcTo(x, y, x + w, y, r); c.closePath(); }
function softText(c, txt, x, y, fill, blur) { c.save(); c.shadowColor = 'rgba(0,0,0,0.55)'; c.shadowBlur = blur == null ? 16 : blur; c.shadowOffsetY = 3; c.fillStyle = fill; c.fillText(txt, x, y); c.restore(); }
function buildRef(cut, plan, st, shots, photos, P) {
 var R = REF[st.id], look = st.look, L = [], EV = [], LAY = [], WO = {}, T = cut.T, BUSY = [], sg = look.sfx == null ? 1 : look.sfx; cut.words.forEach(function (x) { WO[x.i] = x; });
 var HL = look.hl || '#FFD60A', ACC = look.accent || '#E10600', FONT = look.font_cap || 'Inter';
 function t0(i) { var k = i; while (k < i + 6) { if (WO[k]) { return WO[k].o; } k++; } return null; }
 function t1(i) { var k = i; while (k > i - 6) { if (WO[k]) { return WO[k].oe; } k--; } return null; }
 function add(it) { L.push(it); } function ev(t, name, g) { if (name) { EV.push([Math.max(0, t), name, 0, (g == null ? 0.5 : g) * sg]); } }
 function busy(a, b, k) { return BUSY.some(function (e) { return (!k || e[2] === k) && a < e[1] && b > e[0]; }); }
 var F = function (w, sz) { return w + ' ' + sz + 'px "' + FONT + '", sans-serif'; };
 /* ---- layout (split screens) ---- */
 var SPLIT = R.layout === 'kallaway' ? { bg: '#050505', vid: [40, 1050, 1000, 830, 34], seam: 990 } : R.layout === 'zach' ? { bg: '#f6f6f4', vid: [0, 960, 1080, 960, 0], seam: 960 } : R.layout === 'tumi' ? { bg: '#e8e8ea', vid: [0, 760, 1080, 1160, 0], seam: 760 } : null;
 function split(a, b) { if (!SPLIT || b - a < 0.6) { return; } LAY.push({ t0: a, t1: b, bg: SPLIT.bg, vid: SPLIT.vid }); BUSY.push([a, b, 'split']); }
 if (R.layout === 'zach') { split(0, T); }
 /* ---- hook title ---- */
 if (R.hook && P.hook && t0(P.hook.a) != null) { var ha = Math.max(0, t0(P.hook.a) - 0.05), hb = Math.max(ha + 1.6, Math.min((t1(P.hook.b) || ha + 2) + 0.4, ha + 3.2)), ht = P.hook.text;
  if (R.hook === 'title') { BUSY.push([ha, hb, 'hook']);
   add({ t0: ha, t1: hb, kind: 'rise', dur: 260, out: 'blur', outd: 180, x: W / 2, y: R.cap.pos === 'top' ? 250 : 300, draw: function (c) { c.textAlign = 'center'; c.textBaseline = 'middle'; var ws = ht.split(' '), mid = Math.floor(ws.length / 2), l1 = ws.slice(0, mid).join(' '), l2 = ws.slice(mid).join(' ');
    var big = function (s) { return '900 ' + s + 'px "' + FONT + '", sans-serif'; }, s2 = fitSize(c, l2.toUpperCase(), big, 104, 940); c.font = F(700, 52); softText(c, l1, 0, -s2 * 0.62, '#fff'); c.font = big(s2); var col = st.id === 'ref-sky' ? '#7CFF4F' : (st.id === 'ref-kallaway' ? '#fff' : HL); softText(c, l2.toUpperCase(), 0, 8, col, 20);
    if (st.id === 'ref-kallaway') { c.font = big(s2); var w2 = c.measureText(l2.toUpperCase()).width; c.fillStyle = ACC; c.fillRect(-w2 / 2, s2 * 0.5, w2, 8); } } });
   ev(ha - 0.15, R.sfx.hook, 0.35); }
  else if (R.hook === 'panel') { L.push({ t0: ha, t1: hb, kind: 'fade', dur: 200, out: 'cut', x: W / 2, y: 470, draw: function (c) { c.textAlign = 'center'; c.textBaseline = 'middle'; c.font = '800 76px "Instrument Serif", serif'; var lines = wrap(c, cap1(ht), 900).slice(0, 3); lines.forEach(function (l, k) { c.fillStyle = '#121212'; c.font = 'italic 400 92px "Instrument Serif", serif'; c.fillText(l, 0, (k - (lines.length - 1) / 2) * 100); }); } }); } }
 /* ---- list: headers / checklist / panel / boxes / HUD ---- */
 var items = P.items.filter(function (it) { return t0(it.a) != null; }), lend = items.length ? (t1(items[items.length - 1].b) || T) + 0.3 : 0;
 items.forEach(function (it, k) { var a = t0(it.a) - 0.05, b = Math.min(k + 1 < items.length ? t0(items[k + 1].a) - 0.05 : T, (t1(it.b) || a + 2) + 0.35); if (b - a < 0.8) { b = a + 0.8; } it.ta = a; it.tb = b; });
 if (items.length >= 2 || (items.length === 1 && R.hdr)) {
  if (R.hdr) { items.forEach(function (it) { var label = it.label, n = it.n, sty = R.hdr;
    add({ t0: it.ta, t1: it.tb, kind: 'pop', dur: 280, out: 'blur', outd: 120, x: W / 2, y: sty === 'red' ? 160 : (R.cap.pos === 'top' ? 170 : 240), draw: function (c) { c.textBaseline = 'middle'; c.textAlign = 'center';
     if (sty === 'num') { var tx = cap1(label); c.font = F(800, 52); var tw = c.measureText(tx).width, nw = 70, x0 = -(tw + nw + 14) / 2; c.save(); c.strokeStyle = HL; c.lineWidth = 4; c.beginPath(); c.ellipse(x0 + nw / 2, 0, nw / 2, 34, -0.15, 0, Math.PI * 2); c.stroke(); c.restore(); c.font = F(800, 40); softText(c, '#' + n, x0 + nw / 2, 2, HL, 8); c.font = F(800, 52); c.textAlign = 'left'; softText(c, tx, x0 + nw + 14, 0, '#fff', 14); }
     else if (sty === 'box') { var t2 = n + '. ' + label.toUpperCase(); c.font = F(800, 48); var w2 = c.measureText(t2).width + 44; c.fillStyle = '#fff'; c.fillRect(-w2 / 2, -40, w2, 80); c.fillStyle = '#0b0b0f'; c.fillText(t2, 0, 3); }
     else if (sty === 'red') { var t3 = label; c.font = F(800, 44); var w3 = c.measureText(t3).width + 50; c.save(); c.shadowColor = 'rgba(0,0,0,0.4)'; c.shadowBlur = 20; c.fillStyle = ACC; rrectP(c, -w3 / 2, -38, w3, 76, 6); c.fill(); c.restore(); c.fillStyle = '#fff'; c.fillText(t3, 0, 2); }
     else { var lab = 'Point #' + n + ':'; c.font = F(700, 50); var wa = c.measureText(lab + ' ').width; c.font = 'italic 400 70px "Instrument Serif", serif'; var wb = c.measureText(label.toLowerCase()).width, x1 = -(wa + wb) / 2; c.textAlign = 'left'; c.font = F(700, 50); softText(c, lab, x1, 0, '#fff'); c.font = 'italic 400 70px "Instrument Serif", serif'; softText(c, label.toLowerCase(), x1 + wa, 2, '#fff'); } } });
    ev(it.ta, R.sfx.hdr, 0.45); }); }
  if (R.list === 'panel' || R.list === 'panel-light') { var dark = R.list === 'panel';
   items.forEach(function (it) { if (dark) { split(it.ta, it.tb); } var lab = it.label, n = it.n;
    add({ t0: it.ta, t1: it.tb, kind: 'fade', dur: 140, out: 'cut', x: W / 2, y: dark ? 560 : 470, draw: function (c, t) { var u = clamp((t - it.ta) / 0.35, 0, 1); c.textAlign = 'center'; c.textBaseline = 'middle';
     c.font = '900 120px "' + FONT + '", sans-serif'; var lines = wrap(c, dark ? lab.toUpperCase() : cap1(lab), 940).slice(0, 3), lh = 118, sz = lines.length > 2 ? 96 : 120;
     c.font = (dark ? '900 ' : '800 ') + sz + 'px "' + FONT + '", sans-serif'; c.globalAlpha *= easeOut(u);
     if (dark) { c.font = F(800, 46); var st1 = 'STEP ' + n; var w1 = c.measureText(st1).width + 40; c.fillStyle = ACC; rrectP(c, -w1 / 2, -330, w1, 70, 6); c.fill(); c.fillStyle = '#fff'; c.fillText(st1, 0, -294); c.font = '900 ' + sz + 'px "' + FONT + '", sans-serif'; }
     else { c.fillStyle = ACC; c.font = F(800, 40); c.fillText(String(n).padStart(2, '0'), 0, -280); c.font = '800 ' + sz + 'px "' + FONT + '", sans-serif'; }
     lines.forEach(function (l, k) { c.fillStyle = dark ? '#fff' : '#111'; c.fillText(l, 0, (k - (lines.length - 1) / 2) * lh + 30 * (1 - easeOut(u))); }); } });
    ev(it.ta - 0.1, dark ? R.sfx.split : R.sfx.row, 0.45); }); }
  if (R.list === 'checklist') { var A0 = items[0].ta, A1 = lend; split(A0, A1);
   add({ t0: A0, t1: A1, kind: 'fade', dur: 1, out: 'cut', x: W / 2, y: 0, draw: function (c, t) { var ttl = (P.title || 'The list').slice(0, 28); c.textBaseline = 'middle'; c.textAlign = 'center'; c.font = F(700, 34); var tw = c.measureText(ttl).width + 50; c.fillStyle = '#D8A73A'; rrectP(c, -tw / 2, 70, tw, 58, 29); c.fill(); c.fillStyle = '#2a1d05'; c.fillText(ttl, 0, 100);
     items.forEach(function (it, k) { if (t < it.ta) { return; } var u = clamp((t - it.ta) / 0.3, 0, 1), y = 190 + k * 104, cur = t < it.tb; c.save(); c.globalAlpha *= easeOut(u); c.translate(0, 20 * (1 - easeOut(u)));
      c.font = F(600, 38); var lab2 = cap1(it.label).slice(0, 34), w4 = c.measureText(lab2).width + 130; c.shadowColor = 'rgba(0,0,0,0.12)'; c.shadowBlur = 14; c.fillStyle = cur ? '#ffffff' : '#f4f4f5'; rrectP(c, -w4 / 2, y - 38, w4, 76, 16); c.fill(); c.shadowColor = 'transparent';
      c.fillStyle = cur ? '#D8A73A' : '#9a9aa2'; c.beginPath(); c.arc(-w4 / 2 + 46, y, 17, 0, Math.PI * 2); c.fill(); c.strokeStyle = '#fff'; c.lineWidth = 4; c.beginPath(); c.moveTo(-w4 / 2 + 38, y); c.lineTo(-w4 / 2 + 44, y + 7); c.lineTo(-w4 / 2 + 55, y - 7); c.stroke();
      c.textAlign = 'left'; c.fillStyle = cur ? '#141414' : '#55555c'; c.fillText(lab2, -w4 / 2 + 82, y + 2); c.textAlign = 'center'; c.restore(); }); } });
   ev(A0 - 0.1, R.sfx.split, 0.5); items.forEach(function (it) { ev(it.ta, R.sfx.row, 0.5); }); }
  if (R.list === 'boxes') { var B0 = items[0].ta;
   add({ t0: B0, t1: lend, kind: 'fade', dur: 220, out: 'blur', outd: 200, x: W / 2, y: 120, draw: function (c, t) { c.textAlign = 'center'; c.textBaseline = 'middle'; items.slice(0, 7).forEach(function (it, k) { var y = k * 74, cur = t >= it.ta && t < it.tb, done = t >= it.tb, lab3 = it.label.toUpperCase().slice(0, 26); c.font = F(800, 36); var w5 = 560;
      c.fillStyle = cur ? 'rgba(20,20,22,0.85)' : 'rgba(20,20,22,0.55)'; rrectP(c, -w5 / 2, y - 30, w5, 60, 6); c.fill(); c.strokeStyle = cur ? '#F5C518' : 'rgba(255,255,255,0.35)'; c.lineWidth = cur ? 3 : 2; rrectP(c, -w5 / 2, y - 30, w5, 60, 6); c.stroke();
      c.fillStyle = cur ? '#ffffff' : (done ? 'rgba(255,255,255,0.75)' : 'rgba(255,255,255,0.45)'); c.fillText(lab3, 0, y + 2); }); } });
   items.forEach(function (it) { ev(it.ta, R.sfx.row, 0.45); }); }
  if (R.list === 'hud') { var H0 = items[0].ta;
   add({ t0: H0, t1: lend, kind: 'rise', dur: 300, out: 'blur', outd: 200, x: W / 2, y: 470, draw: function (c, t) { var nI = Math.min(items.length, 6), bw = 860, seg = bw / nI; c.save(); c.fillStyle = 'rgba(20,40,22,0.55)'; rrectP(c, -bw / 2 - 16, -26, bw + 32, 52, 26); c.fill(); c.restore();
     items.slice(0, nI).forEach(function (it, k) { var x = -bw / 2 + k * seg, on = t >= it.ta, cur = on && t < it.tb, f = cur ? clamp((t - it.ta) / Math.max(0.5, it.tb - it.ta), 0, 1) : (t >= it.tb ? 1 : 0); c.fillStyle = 'rgba(124,255,79,0.25)'; rrectP(c, x + 6, -12, seg - 12, 24, 12); c.fill(); if (f > 0) { c.fillStyle = '#7CFF4F'; rrectP(c, x + 6, -12, Math.max(24, (seg - 12) * f), 24, 12); c.fill(); }
      if (cur) { c.font = F(700, 34); c.textAlign = 'center'; c.textBaseline = 'middle'; var lb = cap1(it.label).slice(0, 26), lw = c.measureText(lb).width + 40, cx = clamp(x + seg / 2, -bw / 2 + lw / 2, bw / 2 - lw / 2); c.fillStyle = 'rgba(15,30,16,0.85)'; rrectP(c, cx - lw / 2, -90, lw, 54, 14); c.fill(); c.strokeStyle = '#7CFF4F'; c.lineWidth = 2; rrectP(c, cx - lw / 2, -90, lw, 54, 14); c.stroke(); c.fillStyle = '#eaffdf'; c.fillText(lb, cx, -62); } }); } });
   items.forEach(function (it) { ev(it.ta, R.sfx.row, 0.4); }); } }
 /* ---- full-screen word cards ---- */
 var lastCard = -99; if (R.cards) { P.cards.forEach(function (cd) { var a = t0(cd.a); if (a == null || a - lastCard < 10 || busy(a - 0.1, a + 1.6, 'split')) { return; } if (items.some(function (it) { return a >= it.ta && a < it.tb; })) { return; }
   var b = Math.min(T, Math.max(a + 1.1, Math.min((t1(cd.b) || a) + 0.45, a + 1.9))); lastCard = a; BUSY.push([a, b, 'card']); var dark = R.cards === 'black', txt = cd.text, lead = cd.lead;
   add({ t0: a, t1: b, kind: 'fade', dur: 1, out: 'cut', x: 0, y: 0, draw: function (c, t) { var g = c.createRadialGradient(W * 0.32, H * 0.28, 60, W * 0.5, H * 0.5, 1300); if (dark) { g.addColorStop(0, '#17171a'); g.addColorStop(1, '#050506'); } else { g.addColorStop(0, '#ffffff'); g.addColorStop(0.55, '#ececec'); g.addColorStop(1, '#d4d4d6'); } c.fillStyle = g; c.fillRect(0, 0, W, H);
     var u = clamp((t - a) / 0.32, 0, 1), sz = fitSize(c, txt, function (s) { return '900 ' + s + 'px "' + (look.font_main || FONT) + '", sans-serif'; }, Math.max(110, Math.min(210, Math.floor(1500 / Math.max(txt.length, 4)))), 940);
     c.save(); c.translate(W / 2, 950); var s = 0.9 + 0.1 * easeOut(u); c.scale(s, s); c.globalAlpha *= easeOut(u); c.textBaseline = 'middle'; c.font = '900 ' + sz + 'px "' + (look.font_main || FONT) + '", sans-serif'; var ww = c.measureText(txt).width;
     if (lead) { c.font = F(500, 42); c.textAlign = 'left'; c.fillStyle = dark ? 'rgba(255,255,255,0.7)' : 'rgba(0,0,0,0.55)'; c.fillText(lead, -ww / 2 + 6, -sz * 0.62); }
     c.textAlign = 'center'; c.font = '900 ' + sz + 'px "' + (look.font_main || FONT) + '", sans-serif'; c.fillStyle = dark ? (st.id === 'ref-wolf' ? '#FFD400' : '#ffffff') : '#0b0b0f'; c.shadowColor = 'rgba(0,0,0,' + (dark ? 0.6 : 0.16) + ')'; c.shadowBlur = 30; c.shadowOffsetY = 10; c.fillText(txt, 0, 0); c.restore(); } });
   ev(a - 0.08, R.sfx.card, 0.55); }); }
 /* ---- stacks: lead small / big key words / tail (wolf, tumi, cinematic) ---- */
 function stack(a, b, lead, big, tail, kind) { BUSY.push([a, b, 'stack']); var u0 = a, bigT = (kind === 'wolf') ? big.toUpperCase() : big;
  add({ t0: a, t1: b, kind: 'fade', dur: 1, out: 'blur', outd: 160, x: W / 2, y: 1330, draw: function (c, t) { var u = clamp((t - u0) / 0.22, 0, 1), ub = clamp((t - u0 - 0.08) / 0.24, 0, 1); c.textBaseline = 'middle';
    var bf = kind === 'wolf' ? function (s) { return '900 ' + s + 'px "Montserrat", sans-serif'; } : kind === 'serif' ? function (s) { return 'italic 400 ' + s + 'px "Instrument Serif", serif'; } : function (s) { return '800 ' + s + 'px "Bricolage Grotesque", sans-serif'; };
    var sz = fitSize(c, bigT, bf, kind === 'wolf' ? 128 : 160, 900); c.font = bf(sz); var bw = c.measureText(bigT).width;
    if (lead) { c.save(); c.globalAlpha *= easeOut(u); c.font = F(kind === 'wolf' ? 700 : 500, kind === 'wolf' ? 46 : 42); c.textAlign = kind === 'wolf' ? 'center' : 'left'; softText(c, kind === 'wolf' ? lead.toUpperCase() : lead, kind === 'wolf' ? -40 * (1 - easeOut(u)) : -bw / 2 + 8 - 40 * (1 - easeOut(u)), -sz * 0.62, '#fff', 12); c.restore(); }
    c.save(); c.globalAlpha *= easeOut(ub); var sc = 1.12 - 0.12 * easeOut(ub); c.scale(sc, sc); c.font = bf(sz); c.textAlign = 'center'; var fill;
    if (kind === 'tumi') { fill = c.createLinearGradient(0, -sz * 0.5, 0, sz * 0.45); fill.addColorStop(0, '#F8DC86'); fill.addColorStop(0.55, '#D9A63C'); fill.addColorStop(1, '#A8761F'); } else if (kind === 'serif') { fill = '#E9C46A'; } else { fill = '#FFD400'; }
    c.shadowColor = 'rgba(0,0,0,0.55)'; c.shadowBlur = 26; c.shadowOffsetY = 6; c.fillStyle = fill; c.fillText(bigT, 0, 6); c.restore();
    if (tail) { c.save(); c.globalAlpha *= easeOut(clamp((t - u0 - 0.2) / 0.2, 0, 1)); c.font = F(500, 40); c.textAlign = 'right'; softText(c, tail, bw / 2 - 6 + 30 * (1 - easeOut(u)), sz * 0.6, '#fff', 12); c.restore(); } } });
  ev(a, R.sfx.stack, 0.35); }
 if (R.stack && !R.stackAll) { var lastS = -99; P.stacks.forEach(function (s) { var a = t0(s.a), b = t1(s.b); if (a == null || b == null || a - lastS < 5 || busy(a - 0.05, b + 0.3)) { return; } lastS = a; stack(a - 0.05, Math.max(a + 0.9, b + 0.3), s.lead, s.big, s.tail, R.stack); }); }
 /* ---- captions (and Tumi's stack-everything captions) ---- */
 var cl = function (s) { return String(s).replace(/[.,;:!?]+$/, ''); }, seq = cut.words.filter(function (x) { return cl(x.w); }), chunks = [], cur = [], n = R.cap.n;
 seq.forEach(function (x) { var txt = cur.concat([x]).map(function (y) { return cl(y.w); }).join(' '); if (cur.length && (x.o - cur[cur.length - 1].oe > 0.25 || cur.length >= n || txt.length > (n === 1 ? 99 : 18))) { chunks.push(cur); cur = []; } cur.push(x); if (/[.,?!]$/.test(x.w)) { chunks.push(cur); cur = []; } }); if (cur.length) { chunks.push(cur); }
 if (R.stackAll) { var ph = [], cp = []; seq.forEach(function (x) { cp.push(x); if (/[.,?!]$/.test(x.w) || cp.length >= 6) { ph.push(cp); cp = []; } }); if (cp.length) { ph.push(cp); }
  var used = {}; ph.forEach(function (p) { var ki = -1; p.forEach(function (x, k) { if (P.keys[x.i] && ki < 0) { ki = k; } }); if (ki < 0) { var bs = -1; p.forEach(function (x, k) { var sc = cl(x.w).length; if (!STOP[cl(x.w).toLowerCase()] && sc > bs) { bs = sc; ki = k; } }); }
   if (ki < 0) { return; } var a = p[0].o - 0.04, b = p[p.length - 1].oe + 0.2; if (busy(a, b, 'card') || busy(a, b, 'split')) { return; } p.forEach(function (x) { used[x.i] = 1; });
   stack(a, b, p.slice(Math.max(0, ki - 4), ki).map(function (x) { return cl(x.w); }).join(' '), cl(p[ki].w), p.slice(ki + 1, ki + 4).map(function (x) { return cl(x.w); }).join(' '), 'tumi'); }); }
 var capY = function (tt) { if (busy(tt, tt + 0.01, 'split')) { return SPLIT.seam + (R.layout === 'zach' ? 0 : 0); } if (R.cap.pos === 'top') { return 330; } if (R.cap.pos === 'center' || R.cap.pos === 'seam') { return 1010; } var s = shots[0]; shots.forEach(function (q) { if (q[0] <= tt) { s = q; } }); return s[2] === 'T' ? 1480 : 1420; };
 chunks.forEach(function (ch, k) { var a = ch[0].o - 0.03, b = Math.min(k + 1 < chunks.length ? chunks[k + 1][0].o - 0.03 : T, ch[ch.length - 1].oe + 0.3);
  if (busy(a, b, 'card') || busy(a + 0.02, b - 0.02, 'stack') || busy(a, b, 'hook')) { return; }
  var parts = ch.map(function (x) { var w = cl(x.w); return { t: R.cap.upper ? w.toUpperCase() : w, k: !!P.keys[x.i] }; }), y = capY(a + 0.05), em = R.cap.emph, sz = Math.round(R.cap.size * (look.cap_size ? look.cap_size / 70 : 1)), wt = R.cap.w;
  var kind = R.cap.anim === 'up' ? 'rise' : (R.cap.anim === 'cut' ? 'fade' : 'pop');
  add({ t0: a, t1: b, kind: kind, dur: kind === 'fade' ? 1 : (kind === 'rise' ? 170 : 150), out: 'cut', x: W / 2, y: y, draw: function (c) { c.textBaseline = 'middle';
    var f = function (p) { if (p.k && em === 'script') { return 'italic 400 ' + Math.round(sz * 1.35) + 'px "' + (st.id === 'ref-millie' ? 'Caveat' : 'Instrument Serif') + '", cursive'; } if (p.k && em === 'serif') { return 'italic 400 ' + Math.round(sz * 1.3) + 'px "Instrument Serif", serif'; } return (p.k && em !== 'none' ? Math.max(wt, 800) : wt) + ' ' + sz + 'px "' + FONT + '", sans-serif'; };
    var pad = 18, gap, tot = 0, ws = parts.map(function (p) { c.font = f(p); var m = c.measureText(p.t).width + (p.k && em === 'box' ? pad * 2 : 0); tot += m; return m; }); c.font = f({ k: false }); gap = c.measureText(' ').width * 0.8; tot += gap * (parts.length - 1);
    if (R.cap.pill) { c.fillStyle = 'rgba(16,16,20,0.78)'; rrectP(c, -tot / 2 - 26, -sz * 0.78, tot + 52, sz * 1.56, 18); c.fill(); }
    var xx = -tot / 2; c.textAlign = 'left'; parts.forEach(function (p, j) { c.font = f(p); var col = '#fff'; if (p.k && em === 'color') { col = HL; } if (p.k && em === 'serif') { col = '#E9C46A'; }
     if (p.k && em === 'box') { c.save(); c.shadowColor = 'rgba(0,0,0,0.35)'; c.shadowBlur = 18; c.fillStyle = ACC; rrectP(c, xx, -sz * 0.64, ws[j], sz * 1.28, 6); c.fill(); c.restore(); c.fillStyle = '#fff'; c.fillText(p.t, xx + pad, 2); }
     else if (R.cap.pill) { c.fillStyle = col; c.fillText(p.t, xx, 2); } else { softText(c, p.t, xx, p.k && (em === 'script' || em === 'serif') ? 4 : 0, col, 16); } xx += ws[j] + gap; }); } }); });
 /* ---- photos the member added: picture card over the blurred speaker (chloe) / tilted card ---- */
 if (photos && photos.length) { var used2 = 0, lastP = -9; Object.keys(P.keys).map(Number).filter(function (i) { return WO[i]; }).map(function (i) { return WO[i].o; }).sort(function (a, b) { return a - b; }).forEach(function (t) { if (used2 >= photos.length || t - lastP < 4 || busy(t - 0.1, t + 2, 'card') || busy(t - 0.1, t + 2, 'split')) { return; } var img = photos[used2++]; lastP = t; BUSY.push([t - 0.05, t + 1.9, 'card']);
   add({ t0: t - 0.05, t1: Math.min(T, t + 1.9), kind: 'rise', dur: 260, out: 'blur', outd: 180, x: W / 2, y: 820, draw: function (c) { var bw = 760, bh = Math.min(1100, 760 * img.height / img.width); c.save(); c.shadowColor = 'rgba(0,0,0,0.45)'; c.shadowBlur = 50; c.fillStyle = '#fff'; rrectP(c, -bw / 2, -bh / 2, bw, bh, 34); c.fill(); c.restore(); c.save(); rrectP(c, -bw / 2, -bh / 2, bw, bh, 34); c.clip(); var s = Math.max(bw / img.width, bh / img.height); c.drawImage(img, -img.width * s / 2, -img.height * s / 2, img.width * s, img.height * s); c.restore(); } });
   ev(t - 0.1, 'swoosh1', 0.4); }); }
 /* ---- CTA: comment keyword pill with typing ---- */
 if (P.cta && P.cta.keyword && t0(P.cta.a) != null) { var ca = t0(P.cta.a), kw = P.cta.keyword; add({ t0: ca, t1: T, kind: 'rise', dur: 260, out: 'cut', x: W / 2, y: 1560, draw: function (c, t) { var nch = clamp(Math.floor((t - ca - 0.3) / 0.09), 0, kw.length), s = 'Comment ' + kw.slice(0, nch) + (nch < kw.length ? '|' : ''); c.font = F(800, 52); c.textAlign = 'center'; c.textBaseline = 'middle'; var w = c.measureText('Comment ' + kw).width + 110; c.save(); c.shadowColor = 'rgba(0,0,0,0.35)'; c.shadowBlur = 24; c.fillStyle = '#fff'; rrectP(c, -w / 2, -52, w, 104, 52); c.fill(); c.restore(); c.fillStyle = '#111'; c.fillText(s, 0, 3); } });
  EV.push([ca + 0.3, 'typing', 0, 0.35 * sg]); BUSY.push([ca, T, 'stack']); }
 /* split screens (kallaway) also on punchlines when there is no list */
 if (R.layout === 'kallaway' && items.length < 2) { var lastK = -99; P.stacks.forEach(function (s) { var a = t0(s.a), b = t1(s.b); if (a == null || b == null || a - lastK < 7 || busy(a, b + 0.3, 'card')) { return; } lastK = a; var bb = Math.max(a + 1.6, b + 0.4); split(a, bb); var big = String(s.big).toUpperCase();
   add({ t0: a, t1: bb, kind: 'fade', dur: 140, out: 'cut', x: W / 2, y: 520, draw: function (c) { c.textAlign = 'center'; c.textBaseline = 'middle'; c.font = F(600, 44); c.fillStyle = 'rgba(255,255,255,0.7)'; c.fillText(cap1(s.lead || ''), 0, -150); var sz = fitSize(c, big, function (q) { return '900 ' + q + 'px "' + FONT + '", sans-serif'; }, 150, 940); c.font = '900 ' + sz + 'px "' + FONT + '", sans-serif'; c.fillStyle = '#fff'; c.fillText(big, 0, 0); var w = c.measureText(big).width; c.fillStyle = ACC; c.fillRect(-w / 2, sz * 0.55, w, 10); } });
   ev(a - 0.1, R.sfx.split, 0.45); }); }
 L.sort(function (a, b) { return (a.y === 0 ? 0 : 1) - (b.y === 0 ? 0 : 1); });
 return { items: L, events: EV, END: 0, cards: BUSY.filter(function (e) { return e[2] === 'card' || e[2] === 'split'; }).length, lay: LAY }; }
/* draw the video into a split-screen layout (used by the renderer and the style previews) */
function layAt(lay, t) { if (!lay) { return null; } for (var k = 0; k < lay.length; k++) { if (t >= lay[k].t0 && t < lay[k].t1) { return lay[k]; } } return null; }
function drawLay(c, ly, src) { var sw = src.width, sh = src.height, v = ly.vid, x = v[0], y = v[1], w = v[2], h = v[3], r = v[4];
 c.fillStyle = ly.bg; c.fillRect(0, 0, W, H); var ar = w / h, cw = sw, ch = sw / ar; if (ch > sh) { ch = sh; cw = sh * ar; } var cx = (sw - cw) / 2, cy = clamp(sh * 0.42 - ch * 0.45, 0, sh - ch);
 c.save(); if (r) { rrectP(c, x, y, w, h, r); c.clip(); } c.drawImage(src, cx, cy, cw, ch, x, y, w, h); c.restore(); }
