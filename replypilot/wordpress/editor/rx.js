/* Reubx Web: the Reubx editing engine (auto mode) running in the member's browser.
   Same rules as the desktop engine: retakes + silences cut, voice chain with the sh model, shots wide/tight at every cut,
   eased punch-ins, grade, Calm Cards / Motion Pop typography, procedural sound design, -14 LUFS.
   Frames are rendered one by one (WebCodecs) so the result never stutters, then muxed with ffmpeg.wasm. */
(function () {
'use strict';
var C = window.RX, $ = function (s, r) { return (r || document).querySelector(s); }, $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
var W = 1080, H = 1920, FPS = 30, SR = 48000;
function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
function clamp(x, a, b) { return Math.max(a, Math.min(b, x)); }
function sstep(u) { u = clamp(u, 0, 1); return u * u * (3 - 2 * u); }
function easeOutBack(x) { x = clamp(x, 0, 1); var c1 = 1.70158, c3 = c1 + 1; return 1 + c3 * Math.pow(x - 1, 3) + c1 * Math.pow(x - 1, 2); }
function easeOut(x) { x = clamp(x, 0, 1); return 1 - Math.pow(1 - x, 3); }
function tick() { return new Promise(function (r) { setTimeout(r, 0); }); }

/* ---------------- styles (from presets.json + the six generic looks) ---------------- */
var A_LOOK = { font_cap: 'Inter', font_accent: 'Instrument Serif', font_hand: 'Gochi', accent_text: '#F3E37C', cap_size: 70, sfx: 0.8, zoom: 0.07 };
var B_LOOK = { cap_size: 70, font_main: 'Montserrat', font_lead: 'Poppins', font_cap: 'Poppins', grad: ['#ffffff', '#ffffff', '#d3ebff', '#8fcaff'], accent: '#2F6BFF', accent2: '#9cc2ff', sfx: 1.0, zoom: 0.1 };
function ext(a, b) { var o = {}, k; for (k in a) { o[k] = a[k]; } for (k in b) { o[k] = b[k]; } return o; }
var STYLES = [
 { id: 'kilgore-list', name: 'Feature list', who: 'Kilgore "15 ways"', fam: 'a', desc: 'Calm, one word at a time, yellow serif accents. Best for tips and lists.', look: ext(A_LOOK, { cap_size: 70, sfx: 0.8, zoom: 0.06 }) },
 { id: 'kilgore-story', name: 'Story', who: 'Kilgore "say it out loud"', fam: 'a', desc: 'Gentle and emotional. Few cuts, soft zooms, typed ending.', look: ext(A_LOOK, { cap_size: 66, sfx: 0.5, zoom: 0.05 }) },
 { id: 'kilgore-community', name: 'Big numbers', who: 'Kilgore "build the room"', fam: 'a', desc: 'Results and numbers stand out. Great for wins and case studies.', look: ext(A_LOOK, { cap_size: 70, sfx: 0.7, zoom: 0.06 }) },
 { id: 'kilgore-skit', name: 'Fast skit', who: 'Kilgore laundry skit', fam: 'a', desc: 'Tight hook, quick cuts, punch-in on every beat.', look: ext(A_LOOK, { cap_size: 74, sfx: 1.0, zoom: 0.1, start_tight: true }) },
 { id: 'kilgore-flip', name: 'Hook flip', who: 'Kilgore "just kidding"', fam: 'a', desc: 'Bold hook, energetic zooms, yellow accent words.', look: ext(A_LOOK, { cap_size: 70, sfx: 0.9, zoom: 0.08, start_tight: true }) },
 { id: 'edita-lawyer', name: 'Loud keywords', who: 'edita "home paid off"', fam: 'b', desc: 'Big bold keyword stacks with a light-blue glow and hits.', look: ext(B_LOOK, { sfx: 1.0, zoom: 0.1 }) },
 { id: 'gallo-promo', name: 'Premium promo', who: 'Anthony Gallo SaaS promo', fam: 'b', ed: false, desc: 'Clean premium look with blue-purple-orange gradient words.', look: ext(B_LOOK, { font_main: 'Bricolage Grotesque', font_lead: 'Manrope', font_cap: 'Manrope', grad: ['#7aa2ff', '#b48cff', '#ff9a62'], gradH: true, accent: '#8b7cff', accent2: '#c9b6ff', sfx: 0.9, zoom: 0.08 }) },
 { id: 'edita-interview', name: 'Editorial', who: 'edita interview', fam: 'b', ed: true, desc: 'Elegant lime serif keywords, calm camera. Interviews, podcasts.', look: ext(B_LOOK, { font_main: 'Instrument Serif', accent: '#d6f35a', accent2: '#ecffa3', sfx: 0.7, zoom: 0.05 }) },
 { id: 'edita-podcast', name: 'Warm podcast', who: 'edita herbalism podcast', fam: 'b', ed: true, desc: 'Warm terracotta serif keywords, relaxed pace.', look: ext(B_LOOK, { font_main: 'Instrument Serif', accent: '#e0894f', accent2: '#f6c39a', sfx: 0.6, zoom: 0.05 }) },
 { id: 'edita-blueprint', name: 'Blueprint', who: 'edita "attention economy"', fam: 'b', desc: 'Blue keyword blocks, small captions, steady zooms.', look: ext(B_LOOK, { accent: '#2F6BFF', accent2: '#9cc2ff', sfx: 0.8, zoom: 0.06 }) }
];
/* ---------------- PRO: the signature moves of each reference reel ---------------- */
var PRO = {
 'kilgore-list': { cut: 6, punch: 4, cards: 'ui', sub: 'save this for later', tightEnd: true, moves: 'List cards, handwritten hook, tight close' },
 'kilgore-story': { cut: 8, punch: 6, story: true, sub: 'just wait for the ending', moves: 'Handwritten story lines, curiosity hook' },
 'kilgore-community': { cut: 5, punch: 4, nums: true, sticker: '🤯', story: true, moves: 'Big yellow numbers, reaction sticker' },
 'kilgore-skit': { cut: 3, punch: 2.5, chat: true, tightEnd: true, moves: 'Cuts every 3 s, chat bubble punchline' },
 'kilgore-flip': { cut: 4, punch: 3, cards: 'ui', flip: true, moves: '"Just kidding" flip sticker, feature cards' },
 'edita-lawyer': { cut: 4, punch: 3, bar: true, pill: true, moves: 'Glass progress bar, CTA pill' },
 'gallo-promo': { cut: 4, punch: 3, cards: 'light', pill: true, moves: 'Full-screen lavender step cards, CTA pill' },
 'edita-interview': { cut: 6, punch: 5, quote: true, moves: 'Quote card on the key line' },
 'edita-podcast': { cut: 5, punch: 4, quote: true, photoItems: true, moves: 'Quote card, your photos on each point' },
 'edita-blueprint': { cut: 5, punch: 4, cards: 'ui', recap: 'The Blueprint', moves: 'Point cards plus a Blueprint recap card' }
};
function proFor(id, pace, on) { var b = PRO[id] || {}, o = {}, k; for (k in b) { o[k] = b[k]; } o.cut = (o.cut || 5) * pace; o.punch = (o.punch || 4) * pace;
 if (!on) { ['cards', 'nums', 'sticker', 'story', 'chat', 'flip', 'bar', 'pill', 'quote', 'recap', 'photoItems', 'sub'].forEach(function (x) { delete o[x]; }); } return o; }
function styleById(id) { for (var i = 0; i < STYLES.length; i++) { if (STYLES[i].id === id) { return STYLES[i]; } } return STYLES[0]; }
var GRADE = { a: { b: 0.025, c: 1.05, s: 1.05, g: 1.03, rm: 0.02, bm: -0.012 }, b: { b: 0.03, c: 1.06, s: 1.07, g: 1.04, rm: 0, bm: 0 } };

/* ---------------- fonts ---------------- */
var FONTS = [['Inter', 'fonts/inter600.woff2', '600', 'normal'], ['Instrument Serif', 'assets/InstrumentSerif-Italic.ttf', '400', 'italic'], ['Gochi', 'assets/GochiHand-Regular.ttf', '400', 'normal'],
 ['Montserrat', 'assets/Montserrat-ExtraBold.ttf', '800', 'normal'], ['Montserrat', 'assets/Montserrat-Black.ttf', '900', 'normal'], ['Poppins', 'assets/Poppins-SemiBold.ttf', '600', 'normal'],
 ['Poppins', 'assets/Poppins-Bold.ttf', '700', 'normal'], ['Poppins', 'assets/Poppins-ExtraBold.ttf', '800', 'normal'], ['Poppins', 'assets/Poppins-MediumItalic.ttf', '500', 'italic'],
 ['Manrope', 'fonts/manrope.woff2', '600', 'normal'], ['Bricolage Grotesque', 'fonts/bricolage.woff2', '800', 'normal']];
var fontsP = null;
function loadFonts() { if (fontsP) { return fontsP; } fontsP = Promise.all(FONTS.map(function (f) { try { var ff = new FontFace(f[0], 'url(' + C.ed + f[1] + ')', { weight: f[2], style: f[3] }); return ff.load().then(function (x) { document.fonts.add(x); }).catch(function () {}); } catch (e) { return Promise.resolve(); } })); return fontsP; }

/* ---------------- ffmpeg ---------------- */
var FF = null, ffLog = [];
function loadFF() { if (FF) { return Promise.resolve(FF); } if (!window.FFmpegWASM) { return Promise.reject(new Error('The video engine did not load. Refresh the page.')); }
 var x = new FFmpegWASM.FFmpeg(); x.on('log', function (e) { ffLog.push(String(e.message || '')); if (ffLog.length > 400) { ffLog.shift(); } });
 return x.load({ coreURL: C.st + 'ffmpeg-core.js', wasmURL: C.st + 'ffmpeg-core.wasm' }).then(function () { FF = x; return x; }); }
function ex(a) { var mark = ffLog.length; return FF.exec(['-hide_banner', '-y'].concat(a)).then(function (c) { if (c !== 0) { if (window.console) { console.warn('ffmpeg failed', a.join(' ').slice(0, 200), ffLog.slice(-12).join(' | ')); } throw new Error('ff:' + a.join(' ').slice(0, 80)); } }); }
function rdF(n) { return FF.readFile(n).then(function (d) { FF.deleteFile(n).catch(function () {}); return new Float32Array(d.buffer.slice(d.byteOffset, d.byteOffset + (d.byteLength & ~3))); }); }
function del(n) { return FF.deleteFile(n).catch(function () {}); }

/* ---------------- speech: a time for every word ---------------- */
function transcribe(a16, onp) { return new Promise(function (res, rej) { var w; try { w = new Worker(C.asr + 'asr-worker.js', { type: 'module' }); } catch (e) { rej(e); return; }
 var t = setTimeout(function () { try { w.terminate(); } catch (e) {} rej(new Error('slow')); }, 900000);
 w.onmessage = function (e) { var m = e.data; if (m.type === 'progress') { var p = m.p || {}; if (p.status === 'progress' && p.progress != null) { onp('Loading the speech model (first time only)...', p.progress); } } else if (m.type === 'status') { onp('Listening to every word...', null); } else if (m.type === 'done') { clearTimeout(t); w.terminate(); res(m.out); } else if (m.type === 'error') { clearTimeout(t); w.terminate(); rej(new Error(m.msg)); } };
 w.onerror = function () { clearTimeout(t); rej(new Error('worker')); }; w.postMessage({ audio: a16, model: 'base' }, [a16.buffer]); }); }
function nw(x) { return String(x).toLowerCase().replace(/[^a-z0-9]/g, ''); }
function align(W, G, dur) { var n = W.length, m = G.length; if (!n || !m || n * m > 1500000) { return null; }
 var a = W.map(function (x) { return nw(x.w); }), b = G.map(nw), D = new Float32Array((n + 1) * (m + 1)), M = m + 1, i, j, E = 1e-4;
 /* a new word costs less where Whisper left a silence long enough to hold it */
 var IC = []; for (i = 0; i <= n; i++) { IC.push(((i < n ? W[i].s : (dur || 0)) - (i > 0 ? W[i - 1].e : 0)) > 0.5 ? 1 : 1.5); }
 for (i = 0; i <= n; i++) { D[i * M] = i; } for (j = 1; j <= m; j++) { D[j] = D[j - 1] + IC[0]; }
 for (i = 1; i <= n; i++) { for (j = 1; j <= m; j++) { var c = a[i - 1] === b[j - 1] ? 0 : 1; D[i * M + j] = Math.min(D[(i - 1) * M + j - 1] + c, D[(i - 1) * M + j] + 1, D[i * M + j - 1] + IC[i]); } }
 var ops = []; i = n; j = m; /* ops: [type, wi, gj] type p=pair, w=whisper only, g=new word */
 while (i > 0 || j > 0) { if (i > 0 && j > 0 && Math.abs(D[i * M + j] - D[(i - 1) * M + j - 1] - (a[i - 1] === b[j - 1] ? 0 : 1)) < E) { ops.push(['p', i - 1, j - 1]); i--; j--; }
  else if (j > 0 && (i === 0 || Math.abs(D[i * M + j] - D[i * M + j - 1] - IC[i]) < E)) { ops.push(['g', -1, j - 1]); j--; } else { ops.push(['w', i - 1, -1]); i--; } }
 ops.reverse(); var pairs = 0, subs = 0; ops.forEach(function (o) { if (o[0] === 'p') { pairs++; if (a[o[1]] !== b[o[2]]) { subs++; } } });
 if (pairs < 0.5 * n || subs > Math.max(3, 0.4 * pairs)) { return null; } /* too different: keep Whisper */
 var out = [], k = 0, pend = '';
 while (k < ops.length) { var o = ops[k];
  if (o[0] !== 'g') { out.push({ w: pend + (o[0] === 'p' ? G[o[2]] : W[o[1]].w), s: W[o[1]].s, e: W[o[1]].e }); pend = ''; k++; continue; }
  var run = []; while (k < ops.length && ops[k][0] === 'g') { run.push(G[ops[k][2]]); k++; }
  var prev = out.length ? out[out.length - 1] : null, nxt = k < ops.length ? W[ops[k][1]] : null, g0 = prev ? prev.e : 0, g1 = nxt ? nxt.s : (dur || g0);
  if (g1 - g0 >= 0.14 * run.length) { /* words Whisper missed: spread them over the silence it left */ var st = (g1 - g0) / run.length; run.forEach(function (x, q) { out.push({ w: x, s: g0 + q * st + 0.02, e: g0 + (q + 1) * st - 0.02 }); }); }
  else if (prev) { prev.w += ' ' + run.join(' '); } else { pend = run.join(' ') + ' '; } }
 return out; }
function aiFix(W) { if (!S.mp3 || !C.aj || !C.n) { return Promise.resolve(W); }
 var f = new FormData(); f.append('action', 'ir_rx_fix'); f.append('n', C.n); f.append('draft', W.map(function (x) { return x.w; }).join(' ')); f.append('a', new Blob([S.mp3], { type: 'audio/mpeg' }), 'a.mp3');
 var ctl = window.AbortController ? new AbortController() : null, to = setTimeout(function () { if (ctl) { ctl.abort(); } }, 50000);
 return fetch(C.aj, { method: 'POST', body: f, credentials: 'same-origin', signal: ctl ? ctl.signal : undefined }).then(function (r) { return r.json(); }).then(function (j) { clearTimeout(to);
  if (!j || !j.ok || !j.text) { return W; } var G = String(j.text).replace(/[\[\(][^\]\)]*[\]\)]/g, ' ').split(/\s+/).filter(function (x) { return nw(x); }); var A = align(W, G, S.v && S.v.duration); return A && A.length >= 3 ? A : W; })
  .catch(function () { clearTimeout(to); return W; }); }
function wordsFrom(out) { var r = []; ((out && out.chunks) || []).forEach(function (c) { var raw = String(c.text || '').trim(); if (!raw || /^[\[\(].*[\]\)]$/.test(raw) || /^[\W_]+$/.test(raw)) { return; } var s = c.timestamp && c.timestamp[0], e = c.timestamp && c.timestamp[1]; if (s == null) { return; } if (e == null || e <= s) { e = s + 0.3; } r.push({ w: raw, s: s, e: e }); }); return r; }

/* ---------------- engine: analyse, autoplan, cut (ports of core.py / autoplan.py) ---------------- */
function envDb(a) { var n = Math.floor(a.length / 480), db = new Float32Array(n), i, j; for (i = 0; i < n; i++) { var s = 0; for (j = 0; j < 480; j++) { var v = a[i * 480 + j]; s += v * v; } db[i] = 20 * Math.log10(Math.sqrt(s / 480) + 1e-9); } return db; }
function silences(db) { var n = db.length, so = Array.prototype.slice.call(db).sort(function (a, b) { return a - b; }), thr = so[Math.floor(n * 0.1)] + 10, sp = new Uint8Array(n), spx, i, k;
 for (i = 0; i < n; i++) { sp[i] = db[i] >= thr ? 1 : 0; } spx = sp.slice(); for (k = 1; k < 7; k++) { for (i = k; i < n; i++) { if (sp[i - k]) { spx[i] = 1; } } for (i = 0; i < n - k; i++) { if (sp[i + k]) { spx[i] = 1; } } }
 var out = []; i = 0; while (i < n) { if (!spx[i]) { var j = i; while (j < n && !spx[j]) { j++; } if (j - i >= 12) { out.push([i / 100, j / 100]); } i = j; } else { i++; } } return out; }
function norm(w) { return w.toLowerCase().replace(/[^a-z0-9']/g, ''); }
function retakeHints(words, n, span) { n = n || 4; span = span || 25; var toks = words.map(function (x) { return norm(x.w); }), hints = [], i = 0;
 while (i < toks.length - n) { var g = toks.slice(i, i + n).join(' '), j = -1; for (var jj = i + n; jj < Math.min(toks.length - n, i + span); jj++) { if (toks.slice(jj, jj + n).join(' ') === g) { j = jj; break; } } if (j > 0) { hints.push([i, j - 1]); i = j; } else { i++; } } return hints; }
var STOP = {}; 'a an the and or but so to of in on at for with from by is are was were be been being am i you he she it we they me my your our their this that these those there here just really very actually basically like um uh okay ok yeah well now then also too can could would should will do does did have has had not no what who when where why how if because about into out up down over than as all any some more most'.split(' ').forEach(function (w) { STOP[w] = 1; });
var STRONG = {}; 'never always best worst biggest secret free money million thousand mistake truth stop start everything nothing simple easy fast guaranteed proven results growth sales clients bookings dms'.split(' ').forEach(function (w) { STRONG[w] = 1; });
function cleanW(w) { return w.replace(/[^\w$%'.,-]/g, '').replace(/^[.,]+|[.,]+$/g, ''); }
function sentences(words) { var out = [], cur = []; words.forEach(function (x, i) { cur.push(i); var gap = i + 1 < words.length ? words[i + 1].s - x.s : 9; if (/[.?!]$/.test(x.w) || gap > 0.9 || cur.length >= 22) { out.push(cur); cur = []; } }); if (cur.length) { out.push(cur); } return out; }
function autoplan(words, st, removed) {
 var fam = st.fam, plan = { remove: [], shots: [], punch: [], accent: [], blocks: [], counts: [], key: {} };
 plan.remove = retakeHints(words); var rem = {}; plan.remove.forEach(function (r) { for (var i = r[0]; i <= r[1]; i++) { rem[i] = 1; } }); (removed || []).forEach(function (i) { rem[i] = 1; }); plan.rem = rem;
 var sents = sentences(words).map(function (s) { return s.filter(function (i) { return !rem[i]; }); }).filter(function (s) { return s.length; });
 var P = st.pro || {}, last = 0; sents.slice(1).forEach(function (s) { if (words[s[0]].s - last >= (P.cut || 5)) { plan.shots.push(s[0]); last = words[s[0]].s; } });
 function score(i) { var w = cleanW(words[i].w).toLowerCase(); if (!w || STOP[w]) { return 0; } return (/\d|\$/.test(w) ? 5 : 0) + (STRONG[w] ? 3 : 0) + (/^[A-Z]/.test(words[i].w) && i && !/[.?!]$/.test(words[i - 1].w) ? 2 : 0) + Math.min(w.length, 9) / 3; }
 var lastp = -9; sents.forEach(function (s) { var best = s.reduce(function (a, b) { return score(b) > score(a) ? b : a; }, s[0]); if (score(best) >= 3 && words[best].s - lastp >= (P.punch || 4)) { plan.punch.push(best); lastp = words[best].s; } });
 var cta = null; words.forEach(function (x, i) { if (cleanW(x.w).toLowerCase() === 'comment' && i + 1 < words.length) { cta = { w: [i, Math.min(i + 12, words.length - 1)], keyword: cleanW(words[i + 1].w).toUpperCase() }; } });
 if (fam === 'a') {
  sents.forEach(function (s) { var cand = s.filter(function (i) { return score(i) >= 2.3; }); plan.accent = plan.accent.concat(cand.slice(0, Math.max(1, Math.floor(s.length / 6)))); });
  var first = sents[0] || [], hook = []; for (var k = 0; k < first.length; k++) { hook.push(first[k]); if (hook.length >= 5 || /[,.?!]$/.test(words[first[k]].w)) { break; } }
  if (hook.length >= 3) { plan.header = { w: [hook[0], hook[hook.length - 1]], main: hook.map(function (i) { return cleanW(words[i].w); }).join(' ').toLowerCase() }; }
  if (cta) { plan.end = { text: 'Comment ' + cta.keyword + '.', sub: "and I'll send it to you" }; }
 } else {
  var lastb = -9; for (var si = 0; si < sents.length; si++) { var s = sents[si]; if (words[s[0]].s - lastb < 4.5 || s.length < 3) { continue; } if (cta && s[0] >= cta.w[0]) { break; }
   var num = s.filter(function (i) { return /\d/.test(words[i].w); });
   if (num.length) { var ni = num[0], to = cleanW(words[ni].w), nx = s.indexOf(ni + 1) >= 0 ? cleanW(words[ni + 1].w).toLowerCase() : ''; var mp = { million: 'M', thousand: 'K', k: 'K', percent: '%', '%': '%' }; if (mp[nx]) { to += mp[nx]; }
    var pos = s.indexOf(ni); plan.counts.push({ w: [ni, Math.min(ni + 2, s[s.length - 1])], to: to.toUpperCase(), lead: s.slice(Math.max(0, pos - 3), pos).map(function (j) { return cleanW(words[j].w); }).join(' ').toLowerCase() }); lastb = words[ni].s; continue; }
   var runs = [], cur = []; s.forEach(function (i) { if (score(i) > 0) { cur.push(i); } else { if (cur.length) { runs.push(cur); } cur = []; } }); if (cur.length) { runs.push(cur); }
   runs = runs.map(function (r) { return r.slice(0, 3); }).filter(function (r) { return r.reduce(function (a, i) { return a + score(i); }, 0) >= 2.5; }); if (!runs.length) { continue; }
   var r = runs.reduce(function (a, b) { var sa = a.reduce(function (x, i) { return x + score(i); }, 0) + 0.5 * a.length, sb = b.reduce(function (x, i) { return x + score(i); }, 0) + 0.5 * b.length; return sb > sa ? b : a; });
   var k0 = s.indexOf(r[0]), pre = s.slice(Math.max(0, k0 - 4), k0);
   plan.blocks.push({ w: [pre.length ? pre[0] : r[0], r[0], r[r.length - 1]], lead: pre.map(function (j) { return cleanW(words[j].w); }).join(' ').toLowerCase(), text: r.map(function (j) { return cleanW(words[j].w); }).join(' ').toUpperCase() }); lastb = words[r[0]].s; }
  plan.punch.forEach(function (i) { plan.key[cleanW(words[i].w).toLowerCase()] = 1; });
 }
 plan.cta = cta;
 if (st.hook && sents[0] && fam === 'a') { var f0 = sents[0]; plan.header = { w: [f0[0], f0[Math.min(f0.length - 1, 4)]], main: st.hook.toLowerCase() }; }
 if (st.cta) { plan.cta = { keyword: st.cta.toUpperCase() }; if (fam === 'a') { plan.end = { text: 'Comment ' + plan.cta.keyword + '.', sub: "and I'll send it to you" }; } }
 var pr = plan.pro = { items: [], nums: [], flips: [], chats: [], story: [], quote: null };
 var txt = function (a) { return a.map(function (i) { return cleanW(words[i].w).replace(/[.,]+$/, ''); }).filter(Boolean).join(' '); };
 var NUMW = { one: 1, two: 2, three: 3, four: 4, five: 5, six: 6, seven: 7, eight: 8, nine: 9, ten: 10, first: 1, second: 2, third: 3, fourth: 4, fifth: 5, sixth: 6, seventh: 7 };
 var LIST = /^(?:(?:and|so|okay|now|then) )?(?:number (\w+)|(first|second|third|fourth|fifth|sixth|seventh)(?:ly)?|(?:step|tip|way|rule|mistake|reason|hack) (?:number )?(\w+)|next(?: up| one)?|finally|lastly|(?:the )?last one)\b/;
 var EMO = {}; 'feel felt scared afraid dream dreams love loved cried cry alone lonely remember broke proud hurt ashamed quit lost finally grateful heart nervous doubt hope'.split(' ').forEach(function (w) { EMO[w] = 1; });
 var li = 0, lastN = -9, lastS = -9, bestQ = 0;
 sents.forEach(function (s, si) { var low = txt(s).toLowerCase(), m = low.match(LIST);
  if (m && s.length >= 3) { var nn = NUMW[m[1] || m[2] || m[3]] || parseInt(m[1] || m[3], 10) || li + 1; li = nn; var used = m[0].split(' ').length, body = s.slice(used, used + 7); if (body.length) { pr.items.push({ w: [s[0], s[s.length - 1]], n: nn, label: txt(body) }); } }
  s.forEach(function (i, k) { var w = cleanW(words[i].w).replace(/[.,]+$/, ''), v = parseFloat(w.replace(/[^\d.]/g, '')) || 0; if (/^\$?\d[\d,.]*k?%?$/i.test(w) && (/[$,k%]/i.test(w) || v >= 100) && words[i].s - lastN >= 3) { var nx = k + 1 < s.length ? cleanW(words[s[k + 1]].w).toLowerCase().replace(/[.,]+$/, '') : '', mp = { million: 'M', thousand: 'K', percent: '%' }; pr.nums.push({ i: i, txt: (w + (mp[nx] || '')).toUpperCase() }); lastN = words[i].s; } });
  var fm = low.match(/\b(just kidding|i'?m kidding|i'?m joking|not really|plot twist)\b/); if (fm) { pr.flips.push({ w: [s[0], s[s.length - 1]], text: fm[1].charAt(0).toUpperCase() + fm[1].slice(1) + '.' }); }
  for (var k = 0; k < s.length - 2; k++) { if (/^(texted|messaged|dmed|wrote|said|replied)$/i.test(cleanW(words[s[k]].w).replace(/[.,:]+$/, ''))) { var bt = s.slice(k + 1, k + 10); if (bt.length >= 2) { pr.chats.push({ w: [s[k], bt[bt.length - 1]], text: txt(bt) }); } break; } }
  var ek = s.filter(function (i) { return EMO[cleanW(words[i].w).toLowerCase().replace(/[.,]+$/, '')]; }); if (ek.length && s.length >= 4 && s.length <= 14 && words[s[0]].s - lastS >= 6) { pr.story.push({ w: [s[0], s[s.length - 1]], text: low, key: cleanW(words[ek[0]].w).toLowerCase().replace(/[.,]+$/, '') }); lastS = words[s[0]].s; }
  if (si > 0 && s.length >= 7 && s.length <= 16) { var q = s.reduce(function (a, i) { return a + score(i); }, 0) / Math.sqrt(s.length); if (q > bestQ) { bestQ = q; pr.quote = { w: [s[0], s[s.length - 1]], text: txt(s) }; } } });
 return plan; }
function doCut(words, plan, sil, db, dur) {
 var keep = []; words.forEach(function (x, i) { if (!plan.rem[i]) { keep.push(i); } }); if (!keep.length) { return null; }
 var PAD_IN = 0.22, PAD_OUT = 0.16, MAXGAP = 0.45;
 function valley(t0, t1) { var i0 = Math.max(Math.floor(t0 * 100), 0), i1 = Math.max(Math.floor(t1 * 100), i0 + 1), best = i0; for (var i = i0; i < Math.min(i1, db.length); i++) { if (db[i] < db[best]) { best = i; } } return best / 100; }
 var runs = [], cur = [keep[0]]; keep.slice(1).forEach(function (i) { if (i === cur[cur.length - 1] + 1) { cur.push(i); } else { runs.push(cur); cur = [i]; } }); runs.push(cur);
 var segs = [];
 runs.forEach(function (r) { var i0 = r[0], i1 = r[r.length - 1], start, end;
  var sb = sil.filter(function (s) { return words[i0].s - 0.6 <= s[1] && s[1] <= words[i0].s + 0.06; });
  if (i0 === 0) { start = Math.max(0, words[0].s - 0.25); } else if (sb.length && sb[sb.length - 1][1] > words[i0 - 1].s + 0.15) { start = Math.max(sb[sb.length - 1][1] - PAD_IN, sb[sb.length - 1][0]); } else { start = valley(words[i0].s - 0.28, words[i0].s + 0.02); }
  var nxt = i1 + 1 < words.length ? words[i1 + 1].s : 1e9, sa = sil.filter(function (s) { return s[0] >= words[i1].s + 0.05; });
  if (sa.length && sa[0][0] < nxt) { end = Math.min(sa[0][0] + PAD_OUT, sa[0][1], nxt - 0.04); } else if (nxt < 1e9) { end = valley(nxt - 0.28, nxt + 0.02); } else { end = Math.min(dur, Math.max(words[i1].e, words[i1].s) + 0.5); }
  var pieces = [[start, end]];
  sil.forEach(function (sl) { var s0 = sl[0], s1 = sl[1]; if (s1 - s0 < MAXGAP || s0 <= start || s1 >= end) { return; } var lp = pieces[pieces.length - 1]; if (lp[0] < s0 && s1 < lp[1]) { pieces[pieces.length - 1] = [lp[0], s0 + PAD_OUT]; pieces.push([Math.max(s1 - PAD_IN, s0 + PAD_OUT + 0.01), lp[1]]); } });
  pieces.forEach(function (p) { if (p[1] - p[0] > 0.05) { segs.push({ s: p[0], e: p[1] }); } }); });
 var o = 0; segs.forEach(function (sg) { sg.o = o; o += sg.e - sg.s; }); var T = o;
 function outT(src) { for (var k = 0; k < segs.length; k++) { var sg = segs[k]; if (sg.s - 0.001 <= src && src <= sg.e + 0.001) { return sg.o + src - sg.s; } } for (k = 0; k < segs.length; k++) { if (segs[k].s > src) { return segs[k].o; } } return T; }
 var ws = keep.map(function (i) { return { i: i, w: words[i].w, o: outT(words[i].s) }; }).sort(function (a, b) { return a.o - b.o; });
 ws.forEach(function (x, k) { x.oe = Math.min(k + 1 < ws.length ? ws[k + 1].o : T, x.o + 0.7); });
 return { segs: segs, T: T, words: ws, keep: keep }; }
function cutBefore(cut, i) { var ws = cut.words, k = -1; for (var j = 0; j < ws.length; j++) { if (ws[j].i === i) { k = j; break; } } if (k < 0) { return null; } var t = k ? (ws[k - 1].oe + ws[k].o) / 2 : 0, near = cut.segs.filter(function (s) { return Math.abs(s.o - t) < 0.7 && s.o > 0; }); return near.length ? near[0].o : t; }
function makeShots(cut, plan, st) { var b = {}, T = cut.T; cut.segs.forEach(function (sg) { b[sg.o.toFixed(3)] = sg.o; }); plan.shots.forEach(function (i) { var t = cutBefore(cut, i); if (t != null) { b[t.toFixed(3)] = t; } });
 var bounds = Object.keys(b).map(function (k) { return b[k]; }).filter(function (x) { return x < T - 0.3; }).sort(function (x, y) { return x - y; }); if (!bounds.length || bounds[0] > 0.01) { bounds.unshift(0); }
 var shots = [], tight = !!st.look.start_tight; bounds.forEach(function (a, k) { shots.push([a, k + 1 < bounds.length ? bounds[k + 1] : T, tight ? 'T' : 'W']); tight = !tight; }); if (st.pro && st.pro.tightEnd && shots.length > 1) { shots[shots.length - 1][2] = 'T'; } return shots; }

/* ---------------- sound design (port of sfx.py) ---------------- */
var rs = 7; function rnd() { rs = (rs * 1103515245 + 12345) & 0x7fffffff; return rs / 0x7fffffff; } function gauss() { var u = rnd() || 1e-9, v = rnd(); return Math.sqrt(-2 * Math.log(u)) * Math.cos(2 * Math.PI * v); }
function bandnoise(n, lo, hi) { var y = new Float32Array(n), lp = 0, lp2 = 0, mx = 1e-9, i; lo = Math.max(lo, 40); hi = Math.max(hi, 40); for (i = 0; i < n; i++) { var fc = lo * Math.pow(hi / lo, n > 1 ? i / (n - 1) : 0), a = Math.exp(-2 * Math.PI * fc / SR), a4 = Math.pow(a, 0.25), x = gauss(); lp = (1 - a) * x + a * lp; lp2 = (1 - a4) * lp + a4 * lp2; y[i] = lp - 0.7 * lp2; mx = Math.max(mx, Math.abs(y[i])); } for (i = 0; i < n; i++) { y[i] /= mx; } return y; }
function stereo(m, p0, p1) { var n = m.length, L = new Float32Array(n), R = new Float32Array(n); for (var i = 0; i < n; i++) { var p = (p0 || 0) + ((p1 || 0) - (p0 || 0)) * (n > 1 ? i / (n - 1) : 0); L[i] = m[i] * Math.sqrt((1 - p) / 2); R[i] = m[i] * Math.sqrt((1 + p) / 2); } return [L, R]; }
var SFX = {
 whoosh: function (d) { d = d || 0.45; var n = Math.floor(SR * d), m = bandnoise(n, 250, 3200); for (var i = 0; i < n; i++) { var t = i / (n - 1), env = t < 0.7 ? Math.pow(t / 0.7, 2.2) : Math.exp(-(t - 0.7) * 12); m[i] *= env * 0.9; } return stereo(m, -0.6, 0.6); },
 swish: function (d) { d = d || 0.22; var n = Math.floor(SR * d), m = bandnoise(n, 1800, 5000); for (var i = 0; i < n; i++) { var t = i / (n - 1), e = Math.pow(Math.sin(Math.PI * Math.min(t * 1.3, 1)), 2); m[i] *= e * 0.55; } return stereo(m, 0.3, -0.2); },
 tick: function (f) { f = f || 2200; var n = Math.floor(SR * 0.06), m = new Float32Array(n); for (var i = 0; i < n; i++) { var t = i / SR; m[i] = (Math.sin(2 * Math.PI * f * t) * Math.exp(-t * 90) + gauss() * Math.exp(-t * 400) * 0.3) * 0.7; } return stereo(m); },
 pop: function () { var n = Math.floor(SR * 0.09), m = new Float32Array(n), ph = 0; for (var i = 0; i < n; i++) { var t = i / SR, f = 1100 * Math.exp(-t * 28) + 260; ph += 2 * Math.PI * f / SR; m[i] = Math.sin(ph) * Math.exp(-t * 38) * Math.min(t / 0.002, 1) * 0.8; } return stereo(m); },
 hit: function (d) { d = d || 0.9; var n = Math.floor(SR * d), m = new Float32Array(n), tr = bandnoise(n, 900, 300), ph = 0; for (var i = 0; i < n; i++) { var t = i / SR, f = 42 + 70 * Math.exp(-t * 18); ph += 2 * Math.PI * f / SR; m[i] = (Math.sin(ph) * Math.exp(-t * 4.5) + tr[i] * Math.exp(-t * 35) * 0.35) * 0.9; } return stereo(m); },
 riser: function (d) { d = d || 0.8; var n = Math.floor(SR * d), m = bandnoise(n, 300, 6000), ph = 0; for (var i = 0; i < n; i++) { var u = i / n; ph += 2 * Math.PI * (220 + 880 * u * u) / SR; m[i] = (m[i] * 0.6 + Math.sin(ph) * 0.25) * Math.pow(u, 2.5) * 0.7; } return stereo(m, -0.3, 0.3); },
 chime: function () { var n = SR, m = new Float32Array(n); for (var i = 0; i < n; i++) { var t = i / SR; m[i] = (Math.sin(2 * Math.PI * 1318.5 * t) * Math.exp(-t * 4.5) + 0.55 * Math.sin(2 * Math.PI * 1975.5 * t) * Math.exp(-t * 6) + 0.25 * Math.sin(2 * Math.PI * 2637 * t) * Math.exp(-t * 8)) * Math.min(t / 0.004, 1) * 0.35; } return stereo(m); },
 typing: function (d) { d = d || 0.8; var n = Math.floor(SR * d), m = new Float32Array(n), rate = 11; for (var k = 0; k < Math.floor(d * rate); k++) { var s = Math.floor((k / rate + rnd() * 0.03) * SR), c = SFX.tick(1500 + rnd() * 1700)[0], g = 0.5 + rnd() * 0.5; for (var j = 0; j < c.length && s + j < n; j++) { m[s + j] += c[j] * g * 1.41; } } for (var i = 0; i < n; i++) { m[i] *= 0.6; } return stereo(m); },
 camera: function (d) { d = d || 0.25; var n = Math.floor(SR * d), m = bandnoise(n, 120, 700); for (var i = 0; i < n; i++) { m[i] *= Math.pow(Math.sin(Math.PI * i / (n - 1)), 2) * 0.5; } return stereo(m); }
};
function renderSfx(events, total) { rs = 7; var n = Math.floor(SR * total), L = new Float32Array(n), R = new Float32Array(n);
 events.forEach(function (ev) { var fn = SFX[ev[1]]; if (!fn) { return; } var x = fn(ev[2] || undefined), s = Math.floor(Math.max(ev[0], 0) * SR); for (var i = 0; i < x[0].length && s + i < n; i++) { L[s + i] += x[0][i] * ev[3]; R[s + i] += x[1][i] * ev[3]; } });
 var pk = 0, i; for (i = 0; i < n; i++) { pk = Math.max(pk, Math.abs(L[i]), Math.abs(R[i])); } if (pk > 0.98) { var k = 0.98 / pk; for (i = 0; i < n; i++) { L[i] *= k; R[i] *= k; } } return [L, R]; }

/* ---------------- graphics layer (port of style_a.py / style_b.py typography) ---------------- */
function shadowText(c, txt, x, y, fill, blur1, blur2) { c.save(); c.shadowColor = 'rgba(0,0,0,0.6)'; c.shadowBlur = blur1 || 14; c.shadowOffsetY = 3; c.fillStyle = fill; c.fillText(txt, x, y); c.shadowColor = 'rgba(0,0,0,0.65)'; c.shadowBlur = blur2 || 3; c.shadowOffsetY = 1; c.fillText(txt, x, y); c.restore(); }
function scrim(c, w, h, a) { var g = c.createRadialGradient(0, 0, 0, 0, 0, Math.max(w, h) / 2); g.addColorStop(0, 'rgba(0,0,0,' + a + ')'); g.addColorStop(1, 'rgba(0,0,0,0)'); c.save(); c.scale(1, h / Math.max(w, h)); c.fillStyle = g; c.beginPath(); c.arc(0, 0, Math.max(w, h) / 2, 0, Math.PI * 2); c.fill(); c.restore(); }
function fitSize(c, txt, font, size, maxW) { c.font = font(size); while (size > 30 && c.measureText(txt).width > maxW) { size = Math.floor(size * 0.94); c.font = font(size); } return size; }
/* an item = { t0, t1, kind:'pop'|'rise'|'slam'|'fade'|'zoom', dur, out:'cut'|'blur', outd, x, y, draw(c, t) } */
function itemAlpha(it, t) { var a = 1, sc = 1, dy = 0, din = (it.dur || 200) / 1000, u = (t - it.t0) / din;
 if (it.kind === 'pop') { a = clamp(u * 2, 0, 1); sc = 0.86 + 0.14 * easeOutBack(u); } else if (it.kind === 'rise') { a = clamp(u, 0, 1); dy = 30 * (1 - easeOut(u)); } else if (it.kind === 'slam') { a = clamp(u * 3, 0, 1); sc = 1 + 0.4 * (1 - easeOut(u)); } else if (it.kind === 'zoom') { a = clamp(u * 2, 0, 1); sc = 0.6 + 0.4 * easeOutBack(u); } else { a = clamp(u, 0, 1); }
 if (it.out !== 'cut') { var od = (it.outd || 160) / 1000; a *= clamp((it.t1 - t) / od, 0, 1); } return { a: a, sc: sc, dy: dy }; }
function buildLayer(cut, plan, st, shots, photos) {
 var L = [], EV = [], look = st.look, WO = {}, T = cut.T; cut.words.forEach(function (x) { WO[x.i] = x; });
 function t_of(i) { return WO[i] ? WO[i].o : null; } function te_of(i) { return WO[i] ? WO[i].oe : null; }
 function shotAt(t) { for (var k = 0; k < shots.length; k++) { if (shots[k][0] <= t && t < shots[k][1]) { return shots[k]; } } return shots[shots.length - 1]; }
 function capY(t) { return shotAt(t)[2] === 'T' ? 1500 : 1420; }
 function add(it) { L.push(it); } var SKIP = {}, END = 0, cs = look.cap_size / 70, sfxG = look.sfx;
 var cleanL = function (s) { return s.replace(/[.,;:!?]+$/, ''); };
 if (st.fam === 'a') {
  var YEL = look.accent_text;
  var sans = function (sz) { return '600 ' + sz + 'px "' + look.font_cap + '", sans-serif'; }, serif = function (sz) { return 'italic 400 ' + sz + 'px "Instrument Serif", serif'; }, hand = function (sz) { return '400 ' + sz + 'px "Gochi", cursive'; };
  if (plan.header && t_of(plan.header.w[0]) != null) { var h = plan.header, a0 = t_of(h.w[0]), he = (te_of(h.w[1]) || a0 + 2) + 0.12, mainTxt = h.main;
   for (var q = h.w[0]; q <= h.w[1]; q++) { SKIP[q] = 1; }
   add({ t0: a0 + 0.05, t1: he, kind: 'rise', dur: 500, out: 'blur', outd: 160, x: W / 2, y: 322, draw: function (c) { var sz = clamp(Math.floor(1900 / Math.max(mainTxt.length, 1)), 56, 100); c.font = sans(sz); var w = c.measureText(mainTxt).width; scrim(c, w + 200, sz * 2.2, 0.35); c.textAlign = 'center'; c.textBaseline = 'middle'; shadowText(c, mainTxt, 0, 0, '#fff'); } });
   EV.push([a0 + 0.05, 'pop', 0, 0.25 * sfxG]); }
  var accent = {}; plan.accent.forEach(function (i) { accent[i] = 1; });
  var FUNC = { a: 1, the: 1, to: 1, of: 1, your: 1, my: 1, and: 1, 'in': 1, 'for': 1, so: 1, i: 1, "it's": 1, is: 1, be: 1, 'if': 1, can: 1, you: 1, on: 1, at: 1 };
  var seq = cut.words.filter(function (x) { return !SKIP[x.i] && cleanL(x.w); }), chunks = [], k = 0;
  while (k < seq.length) { var x = seq[k], ch = [x]; if (k + 1 < seq.length && FUNC[cleanL(x.w).toLowerCase()] && seq[k + 1].i === x.i + 1 && seq[k + 1].o - x.oe < 0.3 && cleanL(seq[k + 1].w).length <= 9) { ch.push(seq[k + 1]); k++; } chunks.push(ch); k++; }
  chunks.forEach(function (ch, n) { var a = ch[0].o - 0.03, b = Math.min(n + 1 < chunks.length ? chunks[n + 1][0].o - 0.03 : T, ch[ch.length - 1].oe + 0.45);
   var parts = ch.map(function (x) { return { t: cleanL(x.w).toLowerCase(), acc: !!accent[x.i] }; });
   add({ t0: a, t1: b, kind: 'pop', dur: 200, out: 'cut', x: W / 2, y: capY(a + 0.05), draw: function (c) { c.textBaseline = 'middle'; var tot = 0, gap = 0, ws = parts.map(function (p) { c.font = p.acc ? serif(Math.round(118 * cs)) : sans(Math.round(88 * cs)); var m = c.measureText(p.t).width; tot += m; return m; }); c.font = sans(Math.round(88 * cs)); gap = c.measureText(' ').width * 0.7; tot += gap * (parts.length - 1);
    scrim(c, tot + 110, 180, 0.28); var xx = -tot / 2; c.textAlign = 'left'; parts.forEach(function (p, j) { c.font = p.acc ? serif(Math.round(118 * cs)) : sans(Math.round(88 * cs)); shadowText(c, p.t, xx, p.acc ? 5 : 0, p.acc ? YEL : '#fff'); xx += ws[j] + gap; }); } }); });
  if (plan.end) { END = 2.4; var e0 = T + 0.02, msg = plan.end.text;
   add({ t0: e0, t1: T + END + 1, kind: 'fade', dur: 150, out: 'cut', x: 0, y: 0, draw: function (c, t) { c.fillStyle = '#000'; c.fillRect(0, 0, W, H); var nch = clamp(Math.floor((t - e0 - 0.25) / 0.075), 0, msg.length); c.font = 'italic 400 120px "Instrument Serif", serif'; c.textAlign = 'center'; c.textBaseline = 'middle'; c.fillStyle = '#fff'; c.fillText(msg.slice(0, nch) + (nch < msg.length || Math.floor(t * 2) % 2 ? '|' : ''), W / 2, 920);
    if (plan.end.sub && t > e0 + 0.4 + 0.075 * msg.length) { c.save(); c.translate(W / 2, 1070); c.rotate(-2 * Math.PI / 180); c.font = hand(62); shadowText(c, plan.end.sub, 0, 0, YEL); c.restore(); } } });
   EV.push([e0 + 0.25, 'typing', 0.075 * msg.length + 0.1, 0.3 * sfxG]); }
 } else {
  var acc = look.accent, acc2 = look.accent2, ed = !!st.ed;
  var mainFont = function (sz) { return ed ? 'italic 400 ' + sz + 'px "Instrument Serif", serif' : (look.font_main === 'Montserrat' ? 'italic 800 ' : '800 ') + sz + 'px "' + look.font_main + '", sans-serif'; };
  var leadFont = function (sz) { return (look.font_lead === 'Poppins' ? 'italic 500 ' : '600 ') + sz + 'px "' + look.font_lead + '", sans-serif'; };
  var BUSY = [];
  function fillMain(c, txt, sz, w) { if (ed) { return acc; } var g = look.gradH ? c.createLinearGradient(-w / 2, 0, w / 2, 0) : c.createLinearGradient(0, -sz * 0.5, 0, sz * 0.5), cols = look.grad; cols.forEach(function (col, i) { g.addColorStop(cols.length > 1 ? i / (cols.length - 1) : 0, col); }); return g; }
  function stack(lead, big, tl, tb, t1, ev) { var sz0 = ed ? 172 : 150; BUSY.push([tl - 0.05, t1]);
   add({ t0: tl, t1: t1, kind: 'fade', dur: 120, out: 'blur', outd: 200, x: W / 2, y: 1330, draw: function (c, t) { var sz = fitSize(c, big, mainFont, sz0, 820), lsz = clamp(Math.floor(sz * 0.32), 38, 62); c.font = mainFont(sz); var bw = c.measureText(big).width;
     scrim(c, Math.max(bw, lead.length * 0.52 * lsz) + 160, sz + lsz + 120, 0.32); c.textBaseline = 'middle';
     if (lead) { c.font = leadFont(lsz); c.textAlign = 'left'; shadowText(c, lead, -bw / 2 + 0.1 * sz, -sz * 0.55, '#fff', 12, 2); }
     if (t >= tb) { var u = (t - tb) / 0.2, s = 0.6 + 0.4 * easeOutBack(u); c.save(); c.translate(0, lsz * 0.3); c.scale(s, s); c.globalAlpha *= clamp(u * 2, 0, 1); c.font = mainFont(sz); c.textAlign = 'center'; c.shadowColor = 'rgba(0,0,0,0.45)'; c.shadowBlur = 24; c.shadowOffsetY = 6; c.fillStyle = fillMain(c, big, sz, bw); c.fillText(big, 0, 0); c.restore(); } } });
   if (lead) { EV.push([tl, 'swish', 0.22, 0.22 * sfxG]); } EV.push([tb, 'hit', 0.7, (ed ? 0.16 : 0.3) * sfxG]); }
  plan.blocks.forEach(function (b) { var tl = t_of(b.w[0]), tb = t_of(b.w[1]), t1 = te_of(b.w[2]); if (tl == null || tb == null || t1 == null) { return; } stack(b.lead, b.text, b.lead ? tl : tb, tb, t1 + 0.25); });
  plan.counts.forEach(function (cn) { var t0 = t_of(cn.w[0]), t1 = te_of(cn.w[1]); if (t0 == null || t1 == null) { return; } t1 += 0.2; var target = cn.to, num = parseFloat(target.replace(/[^\d.]/g, '')) || 0, pre = (target.match(/^[^\d]*/) || [''])[0], suf = (target.match(/[^\d.]*$/) || [''])[0], steps = 12; BUSY.push([t0 - 0.35, t1]);
   add({ t0: t0 - 0.3, t1: t1, kind: 'fade', dur: 120, out: 'blur', outd: 200, x: W / 2, y: 1330, draw: function (c, t) { var k2 = clamp(Math.floor((t - t0) / 0.055), 0, steps), v = k2 >= steps ? target : pre + Math.round(num * easeOut(k2 / steps)).toLocaleString() + suf, sz = fitSize(c, target, mainFont, 200, 820), lsz = clamp(Math.floor(sz * 0.32), 38, 62); c.textBaseline = 'middle';
     scrim(c, Math.max(sz * 0.66 * target.length, 400) + 160, sz + 140, 0.32); if (cn.lead) { c.font = leadFont(lsz); c.textAlign = 'center'; shadowText(c, cn.lead, 0, -sz * 0.62, '#fff', 12, 2); }
     if (t >= t0) { c.font = mainFont(sz); c.textAlign = 'center'; c.shadowColor = 'rgba(0,0,0,0.45)'; c.shadowBlur = 24; c.shadowOffsetY = 6; c.fillStyle = fillMain(c, v, sz, c.measureText(v).width); c.fillText(v, 0, lsz * 0.3); } } });
   EV.push([t0 - 0.4, 'riser', 0.4, 0.2 * sfxG], [t0 + steps * 0.055, 'chime', 0, 0.2 * sfxG]); });
  var seq2 = cut.words.filter(function (x) { return cleanL(x.w); }), chunks2 = [], cur2 = [];
  seq2.forEach(function (x) { if (cur2.length && (x.o - cur2[cur2.length - 1].oe > 0.25 || cur2.length >= 3 || cur2.concat([x]).map(function (y) { return cleanL(y.w); }).join(' ').length > 16)) { chunks2.push(cur2); cur2 = []; } cur2.push(x); if (/[.,?!]$/.test(x.w)) { chunks2.push(cur2); cur2 = []; } }); if (cur2.length) { chunks2.push(cur2); }
  chunks2.forEach(function (ch, n) { var a = ch[0].o - 0.03, b = Math.min(n + 1 < chunks2.length ? chunks2[n + 1][0].o - 0.03 : T, ch[ch.length - 1].oe + 0.35);
   if (BUSY.some(function (e) { return a < e[1] && b > e[0]; })) { return; }
   var parts = ch.map(function (x) { var t = cleanL(x.w).toLowerCase(); return { t: t, k: !!plan.key[t] }; });
   add({ t0: a, t1: b, kind: 'pop', dur: 260, out: 'blur', outd: 60, x: W / 2, y: capY(a + 0.05) + 40, draw: function (c) { var sz = Math.round(66 * cs), f = function (bold) { return (bold ? '800 ' : '600 ') + sz + 'px "' + look.font_cap + '", sans-serif'; }; c.textBaseline = 'middle'; var tot = 0, gap = 0, ws = parts.map(function (p) { c.font = f(p.k); var m = c.measureText(p.t).width; tot += m; return m; }); c.font = f(false); gap = c.measureText(' ').width * 0.75; tot += gap * (parts.length - 1);
    scrim(c, tot + 110, 135, 0.28); var xx = -tot / 2; c.textAlign = 'left'; parts.forEach(function (p, j) { c.font = f(p.k); shadowText(c, p.t, xx, 0, p.k ? acc2 : '#fff', 14, 2); xx += ws[j] + gap; }); } }); });
 }
 /* ---- PRO moves ---- */
 var P = st.pro || {}, pr = plan.pro || { items: [], nums: [], flips: [], chats: [], story: [] }, CARDS = [], HI = st.fam === 'a' ? look.accent_text : (look.accent2 || '#9cc2ff'), ACC = look.accent || '#d63a3a';
 var PF = { sans: function (sz, wt) { return (wt || 700) + ' ' + sz + 'px "' + (look.font_cap || 'Inter') + '", sans-serif'; }, serif: function (sz) { return 'italic 400 ' + sz + 'px "Instrument Serif", serif'; }, hand: function (sz) { return '400 ' + sz + 'px "Gochi", cursive'; } };
 function cap1(x) { x = String(x || ''); return x.charAt(0).toUpperCase() + x.slice(1); }
 function rrect(c, x, y, w, h, r) { c.beginPath(); c.moveTo(x + r, y); c.arcTo(x + w, y, x + w, y + h, r); c.arcTo(x + w, y + h, x, y + h, r); c.arcTo(x, y + h, x, y, r); c.arcTo(x, y, x + w, y, r); c.closePath(); }
 function wrapW(c, ws, maxW) { var lines = [], cur = []; ws.forEach(function (w) { var nx = cur.concat([w]).join(' '); if (c.measureText(nx).width > maxW && cur.length) { lines.push(cur); cur = [w]; } else { cur.push(w); } }); if (cur.length) { lines.push(cur); } return lines; }
 function wrap(c, t, maxW) { return wrapW(c, String(t).split(' '), maxW).map(function (l) { return l.join(' '); }); }
 function free(a, b) { return !CARDS.some(function (e) { return a < e[1] && b > e[0]; }); }
 function span(w, minD, maxD) { var a = null, b = null; for (var i = w[0]; i <= w[1]; i++) { if (WO[i]) { if (a == null) { a = WO[i].o; } b = WO[i].oe; } } if (a == null) { return null; } b = clamp((b || a) + 0.3, a + minD, a + maxD); return [a, Math.min(b, T)]; }
 function claim(sp) { if (!sp || sp[1] - sp[0] < 0.5 || !free(sp[0], sp[1])) { return false; } CARDS.push(sp); return true; }
 function card(c, w, h, fill, r) { c.save(); c.shadowColor = 'rgba(0,0,0,0.35)'; c.shadowBlur = 40; c.shadowOffsetY = 12; c.fillStyle = fill; rrect(c, -w / 2, -h / 2, w, h, r || 36); c.fill(); c.restore(); }
 if (P.bar) { add({ t0: 0, t1: T, kind: 'fade', dur: 300, out: 'cut', x: W / 2, y: 110, draw: function (c, t) { var w = 760, u = clamp(t / T, 0, 1); c.fillStyle = 'rgba(255,255,255,0.28)'; rrect(c, -w / 2, -9, w, 18, 9); c.fill(); var g = c.createLinearGradient(-w / 2, 0, w / 2, 0); g.addColorStop(0, '#d3ebff'); g.addColorStop(1, '#8fcaff'); c.fillStyle = g; rrect(c, -w / 2, -9, Math.max(18, w * u), 18, 9); c.fill(); } }); }
 if (plan.header && P.sub && st.fam === 'a') { var hs = t_of(plan.header.w[0]), he2 = (te_of(plan.header.w[1]) || hs + 2) + 0.12, subT = st.sub || P.sub; if (hs != null) { add({ t0: hs + 0.35, t1: he2 + 0.4, kind: 'rise', dur: 400, out: 'blur', outd: 160, x: W / 2, y: 440, draw: function (c) { c.rotate(-2 * Math.PI / 180); c.font = PF.hand(70); c.textAlign = 'center'; c.textBaseline = 'middle'; shadowText(c, subT, 0, 0, HI); } }); } }
 if (st.hook && st.fam === 'b' && cut.words.length) { var h0 = cut.words[0].o, h1 = Math.min(T, h0 + 2.4), hk = st.hook; add({ t0: h0, t1: h1, kind: 'slam', dur: 260, out: 'blur', outd: 200, x: W / 2, y: 330, draw: function (c) { var sz = fitSize(c, hk.toUpperCase(), function (z) { return '800 ' + z + 'px "' + look.font_main + '", sans-serif'; }, 110, 900); c.font = '800 ' + sz + 'px "' + look.font_main + '", sans-serif'; c.textAlign = 'center'; c.textBaseline = 'middle'; scrim(c, 1000, sz * 2.4, 0.35); shadowText(c, hk.toUpperCase(), 0, 0, '#fff', 20, 3); } }); EV.push([h0, 'hit', 0.7, 0.3 * sfxG]); }
 if (P.cards === 'ui') { pr.items.forEach(function (it) { var sp = span(it.w, 1.6, 3); if (!claim(sp)) { return; }
  add({ t0: sp[0], t1: sp[1], kind: 'pop', dur: 260, out: 'blur', outd: 160, x: W / 2, y: 560, draw: function (c) { c.font = PF.sans(52, 700); var lines = wrap(c, cap1(it.label), 600).slice(0, 2), h = 90 + lines.length * 62, w = 860; card(c, w, h, '#fff');
   c.fillStyle = ACC; c.beginPath(); c.arc(-w / 2 + 80, 0, 46, 0, Math.PI * 2); c.fill(); c.fillStyle = '#fff'; c.font = PF.sans(50, 800); c.textAlign = 'center'; c.textBaseline = 'middle'; c.fillText(String(it.n), -w / 2 + 80, 2);
   c.fillStyle = '#111'; c.font = PF.sans(52, 700); c.textAlign = 'left'; lines.forEach(function (l, k) { c.fillText(l, -w / 2 + 160, (k - (lines.length - 1) / 2) * 62); }); } });
  EV.push([sp[0], 'pop', 0, 0.3 * sfxG]); }); }
 if (P.cards === 'light') { pr.items.forEach(function (it) { var sp = span(it.w, 1.4, 2.2); if (!claim(sp)) { return; }
  add({ t0: sp[0], t1: sp[1], kind: 'fade', dur: 140, out: 'blur', outd: 160, x: 0, y: 0, draw: function (c) { var g = c.createLinearGradient(0, 0, 0, H); g.addColorStop(0, '#f6f3ff'); g.addColorStop(1, '#e4dbff'); c.fillStyle = g; c.fillRect(0, 0, W, H); c.textAlign = 'center'; c.textBaseline = 'middle'; c.fillStyle = ACC; c.font = PF.sans(44, 700); c.fillText('STEP ' + it.n, W / 2, 720);
   var mf = '800 100px "' + (look.font_main || 'Montserrat') + '", sans-serif'; c.font = mf; var lines = wrap(c, cap1(it.label), 880).slice(0, 3), gr = c.createLinearGradient(100, 0, W - 100, 0), cols = look.grad && look.grad.length > 1 ? look.grad : ['#7aa2ff', '#b48cff', '#ff9a62']; cols.forEach(function (col, i) { gr.addColorStop(i / (cols.length - 1), col); });
   lines.forEach(function (l, k) { c.fillStyle = k === lines.length - 1 ? gr : '#1b1530'; c.fillText(l, W / 2, 880 + k * 118); }); } });
  EV.push([sp[0], 'swish', 0.22, 0.3 * sfxG]); }); }
 if (P.recap && pr.items.length >= 2) { var lastIt = pr.items[pr.items.length - 1], le = span(lastIt.w, 0.5, 9), rc = le ? [le[1] + 0.1, Math.min(T, le[1] + 3)] : null, labs = pr.items.slice(0, 5).map(function (it) { return it.label.split(' ').slice(0, 4).join(' '); });
  if (rc && claim(rc)) { add({ t0: rc[0], t1: rc[1], kind: 'zoom', dur: 320, out: 'blur', outd: 200, x: W / 2, y: 640, draw: function (c) { var h = 150 + labs.length * 84; card(c, 860, h, '#2a1650', 40); c.textAlign = 'left'; c.textBaseline = 'middle'; c.fillStyle = '#c9b6ff'; c.font = PF.sans(46, 800); c.fillText(P.recap, -370, -h / 2 + 70); c.font = PF.sans(48, 600); labs.forEach(function (l, k) { c.fillStyle = '#fff'; c.fillText((k + 1) + '.  ' + cap1(l), -370, -h / 2 + 160 + k * 84); }); } }); EV.push([rc[0], 'swish', 0.22, 0.3 * sfxG]); } }
 if (P.nums) { pr.nums.forEach(function (nm, k) { var sp = WO[nm.i] ? [WO[nm.i].o - 0.05, Math.min(T, WO[nm.i].o + 1.9)] : null; if (!claim(sp)) { return; }
  add({ t0: sp[0], t1: sp[1], kind: 'zoom', dur: 300, out: 'blur', outd: 200, x: W / 2, y: 560, draw: function (c) { var sz = fitSize(c, nm.txt, PF.serif, 230, 900); c.font = PF.serif(sz); c.textAlign = 'center'; c.textBaseline = 'middle'; scrim(c, c.measureText(nm.txt).width + 200, sz * 1.6, 0.35); shadowText(c, nm.txt, 0, 0, HI, 24, 3); } });
  EV.push([sp[0], 'hit', 0.7, 0.28 * sfxG]); if (P.sticker && k === pr.nums.length - 1) { var s0 = sp[0] + 0.3; add({ t0: s0, t1: sp[1], kind: 'slam', dur: 240, out: 'blur', outd: 150, x: W * 0.78, y: 420, draw: function (c) { c.rotate(12 * Math.PI / 180); c.font = '150px "Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif'; c.textAlign = 'center'; c.textBaseline = 'middle'; c.fillText(P.sticker, 0, 0); } }); EV.push([s0, 'pop', 0, 0.3 * sfxG]); } }); }
 if (P.flip) { pr.flips.forEach(function (fl) { var sp = span(fl.w, 1.5, 2.6); if (!claim(sp)) { return; }
  add({ t0: sp[0], t1: sp[1], kind: 'rise', dur: 300, out: 'blur', outd: 160, x: W / 2, y: 620, draw: function (c) { c.rotate(-3 * Math.PI / 180); c.font = PF.hand(96); c.textAlign = 'center'; c.textBaseline = 'middle'; shadowText(c, fl.text, 0, 0, HI, 18, 3); } });
  add({ t0: sp[0] + 0.15, t1: sp[1], kind: 'slam', dur: 240, out: 'blur', outd: 150, x: W * 0.78, y: 430, draw: function (c) { c.rotate(10 * Math.PI / 180); c.font = '150px "Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji",sans-serif'; c.textAlign = 'center'; c.textBaseline = 'middle'; c.fillText('😜', 0, 0); } });
  EV.push([sp[0] + 0.15, 'pop', 0, 0.35 * sfxG]); }); }
 if (P.chat) { pr.chats.slice(0, 3).forEach(function (ch) { var sp = span(ch.w, 1.8, 3.2); if (!claim(sp)) { return; }
  add({ t0: sp[0], t1: sp[1], kind: 'pop', dur: 260, out: 'blur', outd: 160, x: W / 2, y: 560, draw: function (c) { c.font = PF.sans(50, 600); var lines = wrap(c, cap1(ch.text), 620).slice(0, 3), bw = Math.max.apply(null, lines.map(function (l) { return c.measureText(l).width; })) + 80, bh = lines.length * 62 + 56, x0 = 380 - bw;
   c.save(); c.translate(x0 + bw / 2, 0); card(c, bw, bh, '#1f8bff', 44); c.restore(); c.fillStyle = '#fff'; c.textAlign = 'left'; c.textBaseline = 'middle'; lines.forEach(function (l, k) { c.fillText(l, x0 + 40, (k - (lines.length - 1) / 2) * 62); });
   c.font = PF.sans(30, 600); c.textAlign = 'right'; c.fillStyle = 'rgba(255,255,255,0.85)'; c.fillText('Delivered', 380, bh / 2 + 34); } });
  EV.push([sp[0], 'pop', 0, 0.3 * sfxG]); }); }
 if (P.story) { pr.story.slice(0, 4).forEach(function (sl) { var sp = span(sl.w, 2, 4); if (!claim(sp)) { return; }
  add({ t0: sp[0], t1: sp[1], kind: 'rise', dur: 450, out: 'blur', outd: 200, x: W / 2, y: 600, draw: function (c) { c.font = PF.hand(74); c.textAlign = 'left'; c.textBaseline = 'middle'; var lines = wrapW(c, sl.text.split(' '), 860).slice(0, 3), sp2 = c.measureText(' ').width;
   lines.forEach(function (ws, k) { var lw = c.measureText(ws.join(' ')).width, xx = -lw / 2, y = (k - (lines.length - 1) / 2) * 92; ws.forEach(function (w) { var hit = w.replace(/[^a-z']/g, '') === sl.key; shadowText(c, w, xx, y, hit ? HI : '#fff', 16, 3); xx += c.measureText(w).width + sp2; }); }); } }); }); }
 if (P.quote && pr.quote) { var qs = span(pr.quote.w, 2.2, 4.5); if (claim(qs)) { var qt = pr.quote.text; add({ t0: qs[0], t1: qs[1], kind: 'rise', dur: 400, out: 'blur', outd: 220, x: W / 2, y: 600, draw: function (c) { c.font = PF.serif(70); var lines = wrap(c, '“' + cap1(qt) + '”', 780).slice(0, 4), h = lines.length * 84 + 110; card(c, 900, h, 'rgba(12,12,14,0.78)', 34); c.textAlign = 'center'; c.textBaseline = 'middle'; c.fillStyle = '#fff'; lines.forEach(function (l, k) { c.fillStyle = k === lines.length - 1 ? ACC : '#fff'; c.fillText(l, 0, (k - (lines.length - 1) / 2) * 84); }); } }); EV.push([qs[0], 'swish', 0.22, 0.25 * sfxG]); } }
 if (P.pill && plan.cta && T > 3) { var pt = 'Comment ' + plan.cta.keyword + ' 👇', p0 = Math.max(0, T - 2.6); add({ t0: p0, t1: T, kind: 'pop', dur: 300, out: 'cut', x: W / 2, y: 560, draw: function (c) { c.font = PF.sans(56, 800); var w = c.measureText(pt).width + 120; card(c, w, 120, '#fff', 60); c.fillStyle = ACC; c.textAlign = 'center'; c.textBaseline = 'middle'; c.fillText(pt, 0, 3); } }); EV.push([p0, 'pop', 0, 0.35 * sfxG]); }
 plan.punch.forEach(function (i) { if (WO[i]) { EV.push([WO[i].o - 0.12, 'camera', 0.3, 0.35 * sfxG]); } });
 /* the member's own photos: shown at the strongest moments (punch-ins), a tilted photo card above the head */
 if (photos && photos.length) { var slots = (P.photoItems && pr.items.length ? pr.items.map(function (it) { return it.w[0]; }) : plan.punch).filter(function (i) { return WO[i]; }).map(function (i) { return WO[i].o; }), used = 0, lastP = -9;
  slots.forEach(function (t) { if (used >= photos.length || t - lastP < 3 || !free(t - 0.05, t + 1.9)) { return; } var img = photos[used++], rot = (used % 2 ? -4 : 4); lastP = t;
   add({ t0: t - 0.05, t1: Math.min(t + 1.9, T), kind: 'pop', dur: 260, out: 'blur', outd: 200, x: W / 2, y: 560, draw: function (c) { var bw = 620, bh = 620 * Math.min(1.25, img.height / img.width); c.rotate(rot * Math.PI / 180); c.shadowColor = 'rgba(0,0,0,0.45)'; c.shadowBlur = 40; c.fillStyle = '#fff'; c.fillRect(-bw / 2 - 18, -bh / 2 - 18, bw + 36, bh + 80); c.shadowColor = 'transparent'; var s = Math.max(bw / img.width, bh / img.height), dw = img.width * s, dh = img.height * s; c.save(); c.beginPath(); c.rect(-bw / 2, -bh / 2, bw, bh); c.clip(); c.drawImage(img, -dw / 2, -bh / 2 - (dh - bh) / 2, dw, dh); c.restore(); } });
   EV.push([t - 0.05, 'swish', 0.22, 0.25 * sfxG]); }); }
 return { items: L, events: EV, END: END, cards: CARDS.length }; }

/* ---------------- colour grade on the GPU (eq + colorbalance of the engine) ---------------- */
function makeGL(w, h) { var cv = document.createElement('canvas'); cv.width = w; cv.height = h; var gl = cv.getContext('webgl', { premultipliedAlpha: false, preserveDrawingBuffer: true }); if (!gl) { return null; }
 var vs = 'attribute vec2 p;varying vec2 v;uniform vec4 r;void main(){v=vec2(r.x+(p.x*.5+.5)*(r.z-r.x),r.y+(.5-p.y*.5)*(r.w-r.y));gl_Position=vec4(p,0.,1.);}';
 var fs = 'precision mediump float;varying vec2 v;uniform sampler2D t;uniform vec4 g;uniform vec2 cb;void main(){vec3 c=texture2D(t,v).rgb;c=(c-.5)*g.y+.5+g.x;float l=dot(c,vec3(.299,.587,.114));c=mix(vec3(l),c,g.z);c=pow(max(c,0.),vec3(1./g.w));float m=1.-abs(l-.5)*2.;c.r+=cb.x*m;c.b+=cb.y*m;gl_FragColor=vec4(clamp(c,0.,1.),1.);}';
 function sh(type, src) { var s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return s; }
 var pr = gl.createProgram(); gl.attachShader(pr, sh(gl.VERTEX_SHADER, vs)); gl.attachShader(pr, sh(gl.FRAGMENT_SHADER, fs)); gl.linkProgram(pr); gl.useProgram(pr);
 var b = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, b); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW); var lp = gl.getAttribLocation(pr, 'p'); gl.enableVertexAttribArray(lp); gl.vertexAttribPointer(lp, 2, gl.FLOAT, false, 0, 0);
 var tx = gl.createTexture(); gl.bindTexture(gl.TEXTURE_2D, tx); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
 var uR = gl.getUniformLocation(pr, 'r'), uG = gl.getUniformLocation(pr, 'g'), uC = gl.getUniformLocation(pr, 'cb');
 return { cv: cv, draw: function (src, rect, gr) { gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, src); gl.uniform4f(uR, rect[0], rect[1], rect[2], rect[3]); gl.uniform4f(uG, gr.b, gr.c, gr.s, gr.g); gl.uniform2f(uC, gr.rm, gr.bm); gl.viewport(0, 0, w, h); gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4); } }; }

/* ---------------- the camera: shots wide/tight, slow push-in, eased punch-ins (port of core.base) ---------------- */
function camAt(t, shots, punches, look, anchor) { var sh = shots[0]; for (var k = 0; k < shots.length; k++) { if (shots[k][0] <= t && t < shots[k][1]) { sh = shots[k]; break; } } var a = sh[0], b = sh[1], n = Math.max(1, Math.round((b - a) * FPS)), on = (t - a) * FPS;
 var z = (sh[2] === 'T' ? 1.16 : 1.0) * (1 + 0.035 * on / n);
 punches.forEach(function (p) { if (a <= p[0] && p[0] < b) { var f0 = (p[0] - a) * FPS - 3, f1 = Math.min((p[1] - a) * FPS + 12, (b - a) * FPS - 1), up = clamp((on - f0) / 6, 0, 1), dn = clamp((on - f1) / 10, 0, 1); z += look.zoom * sstep(up) * (1 - sstep(dn)); } });
 return z; }
function cropRect(vw, vh, z, anchor) { var tw = W / H, sw, shh; if (vw / vh > tw) { shh = vh; sw = vh * tw; } else { sw = vw; shh = vw / tw; } sw /= z; shh /= z; var cx = vw * 0.5, cy = vh * 0.42, x = clamp(cx - sw / 2, 0, vw - sw), y = clamp(cy - shh * anchor, 0, vh - shh); return [x / vw, y / vh, (x + sw) / vw, (y + shh) / vh]; }

/* ---------------- render frames with WebCodecs, mux, add sound ---------------- */
function pickCodec() { var list = ['avc1.640028', 'avc1.4d0028', 'avc1.42e028', 'vp09.00.40.08']; var i = 0; return new Promise(function (res) { (function nx() { if (i >= list.length || !window.VideoEncoder) { res(null); return; } var c = list[i++]; VideoEncoder.isConfigSupported({ codec: c, width: W, height: H, bitrate: 9e6, framerate: FPS }).then(function (r) { if (r.supported) { res(c); } else { nx(); } }).catch(nx); })(); }); }
function renderVideo(v, job, onp) {
 var cut = job.cut, layer = job.layer, TT = cut.T + layer.END, N = Math.round(TT * FPS), gl = makeGL(W, H), out = document.createElement('canvas'); out.width = W; out.height = H; var c = out.getContext('2d');
 var gr = GRADE[job.st.fam], anchor = job.st.fam === 'a' ? 0.5 : 0.42, WO = {}; cut.words.forEach(function (x) { WO[x.i] = x; });
 var punches = job.plan.punch.filter(function (i) { return WO[i]; }).map(function (i) { return [WO[i].o, WO[i].oe]; });
 var items = layer.items;
 function compose(t, hasVideo) { c.setTransform(1, 0, 0, 1, 0, 0); c.globalAlpha = 1; if (hasVideo && gl) { var z = camAt(t, job.shots, punches, job.st.look, anchor); gl.draw(v, cropRect(v.videoWidth, v.videoHeight, z, anchor), gr); c.drawImage(gl.cv, 0, 0); } else if (!hasVideo) { c.fillStyle = '#000'; c.fillRect(0, 0, W, H); }
  items.forEach(function (it) { if (t < it.t0 || t >= it.t1) { return; } var p = itemAlpha(it, t); if (p.a <= 0.001) { return; } c.save(); c.globalAlpha = p.a; c.translate(it.x, it.y + p.dy); c.scale(p.sc, p.sc); it.draw(c, t); c.restore(); }); }
 return pickCodec().then(function (codec) { if (!codec || !window.Mp4Muxer) { throw new Error('nocodec'); }
  var isAvc = /^avc/.test(codec), mux = new Mp4Muxer.Muxer({ target: new Mp4Muxer.ArrayBufferTarget(), video: { codec: isAvc ? 'avc' : 'vp9', width: W, height: H }, fastStart: 'in-memory' }), err = null;
  var enc = new VideoEncoder({ output: function (ch, meta) { mux.addVideoChunk(ch, meta); }, error: function (e) { err = e; } });
  enc.configure({ codec: codec, width: W, height: H, bitrate: 9e6, framerate: FPS, latencyMode: 'quality' });
  var f = 0;
  function emit(t, hasVideo) { compose(t, hasVideo); var vf = new VideoFrame(out, { timestamp: Math.round(f * 1e6 / FPS), duration: Math.round(1e6 / FPS) }); enc.encode(vf, { keyFrame: f % 60 === 0 }); vf.close(); f++; if (f % 15 === 0) { onp(f / N); } }
  function waitQ() { return enc.encodeQueueSize > 6 ? new Promise(function (r) { setTimeout(r, 15); }).then(waitQ) : Promise.resolve(); }
  function seek(t) { return new Promise(function (r) { var d = false; function ok() { if (d) { return; } d = true; v.removeEventListener('seeked', ok); r(); } v.addEventListener('seeked', ok); v.currentTime = t; setTimeout(ok, 4000); }); }
  /* each output frame needs the source frame at segment.s + (t - segment.o); frames are pulled by seeking in order (exact, never stutters) */
  var segs = cut.segs;
  /* playback-driven capture: each segment plays once; every output frame takes the source frame showing at its time (timestamps come from the edit, never from the clock) */
  function srcOf(t) { var sg = segs[0]; for (var k = 0; k < segs.length; k++) { if (segs[k].o <= t + 1e-6) { sg = segs[k]; } } return sg.s + (t - sg.o); }
  function playSeg(k) { var sg = segs[k], end = k + 1 < segs.length ? segs[k + 1].o : cut.T;
   return seek(sg.s).then(function () { return new Promise(function (res, rej) { var done = false, stall = null;
    function fin() { if (done) { return; } done = true; clearTimeout(stall); v.pause(); res(); }
    function step(now, meta) { if (done) { return; } if (err) { done = true; v.pause(); rej(err); return; } var m = meta && meta.mediaTime != null ? meta.mediaTime : v.currentTime;
     while (f < N && f / FPS < end - 1e-6 && srcOf(f / FPS) <= m + 0.6 / FPS) { emit(f / FPS, true); }
     if (f >= N || f / FPS >= end - 1e-6 || m >= sg.e - 0.5 / FPS || v.ended) { while (f < N && f / FPS < end - 1e-6) { emit(f / FPS, true); } fin(); return; }
     clearTimeout(stall); stall = setTimeout(function () { while (f < N && f / FPS < end - 1e-6) { emit(f / FPS, true); } fin(); }, 4000);
     if (enc.encodeQueueSize > 8) { v.pause(); waitQ().then(function () { v.play().catch(function () {}); v.requestVideoFrameCallback(step); }); } else { v.requestVideoFrameCallback(step); } }
    v.muted = true; v.playbackRate = 1; v.requestVideoFrameCallback(step); v.play().catch(function (e) { done = true; rej(e); }); }); }); }
  function viaPlayback() { var k = 0; return (function nx() { if (k >= segs.length) { return Promise.resolve(); } return playSeg(k++).then(nx); })().then(function endCard() { if (f >= N) { return; } emit(f / FPS, false); return (f % 10 ? Promise.resolve() : tick()).then(waitQ).then(endCard); }); }
  var si = 0;
  function nextFrame() { if (err) { return Promise.reject(err); } if (f >= N) { return Promise.resolve(); } var t = f / FPS;
   if (t >= cut.T) { emit(t, false); return (f % 10 ? Promise.resolve() : tick()).then(waitQ).then(nextFrame); }
   while (si + 1 < segs.length && segs[si + 1].o <= t + 1e-6) { si++; } var sg = segs[si], src = sg.s + (t - sg.o) + 0.5 / FPS;
   return seek(src).then(function () { emit(t, true); return waitQ(); }).then(nextFrame); }
  if ('requestVideoFrameCallback' in HTMLVideoElement.prototype) { return viaPlayback().then(function () { return enc.flush(); }).then(function () { if (err) { throw err; } mux.finalize(); return new Uint8Array(mux.target.buffer); }); }
  return seek(0.01).then(nextFrame).then(function () { return enc.flush(); }).then(function () { if (err) { throw err; } mux.finalize(); return new Uint8Array(mux.target.buffer); }); }); }

/* ---------------- voice (the engine's exact chain) + sound effects + -14 LUFS ---------------- */
function buildAudio(a48, job, onp) { var segs = job.cut.segs, TT = job.cut.T + job.layer.END, n = Math.floor(TT * SR), vcut = new Float32Array(n), o = 0;
 segs.forEach(function (sg) { var s0 = Math.floor(sg.s * SR), s1 = Math.min(a48.length, Math.floor(sg.e * SR)), len = s1 - s0, fi = Math.floor(0.008 * SR), fo = Math.floor(0.012 * SR); for (var i = 0; i < len && o + i < n; i++) { var g = Math.min(1, i / fi, (len - i) / fo); vcut[o + i] = a48[s0 + i] * g; } o += Math.max(0, len); });
 var mean = 0; for (var i = 0; i < n; i++) { mean += vcut[i] * vcut[i]; } mean = 10 * Math.log10(mean / Math.max(1, n) + 1e-12); var gain = (-24 - mean).toFixed(1);
 var tail = ',equalizer=f=250:t=q:w=1.1:g=-1.5,equalizer=f=3500:t=q:w=1.4:g=1.5,deesser=i=0.18,acompressor=threshold=-20dB:ratio=2:attack=12:release=180:makeup=1.5,alimiter=limit=0.9:level=false', head = 'highpass=f=70,volume=' + gain + 'dB,';
 var chain = head + 'arnndn=m=sh.rnnn:mix=0.75' + tail, chain2 = head + 'arnndn=m=sh.rnnn' + tail;
 onp('Cleaning your voice...');
 return FF.writeFile('vc.raw', new Uint8Array(vcut.buffer)).then(function () { return fetch(C.ed + 'assets/sh.rnnn').then(function (r) { return r.arrayBuffer(); }).then(function (b) { return FF.writeFile('sh.rnnn', new Uint8Array(b)); }); })
  .then(function () { var go = function (ch) { return ex(['-f', 'f32le', '-ar', '48000', '-ac', '1', '-i', 'vc.raw', '-af', ch, '-f', 'f32le', '-ac', '1', '-ar', '48000', 'voice.raw']); }; return go(chain).catch(function () { return go(chain2); }); })
  .then(function () { del('vc.raw'); return rdF('voice.raw'); })
  .then(function (vo) { onp('Adding sound design...'); var fx = renderSfx(job.layer.events, TT), mix = new Float32Array(n * 2); for (var i = 0; i < n; i++) { var x = i < vo.length ? vo[i] : 0; mix[2 * i] = x + fx[0][i] * 0.55; mix[2 * i + 1] = x + fx[1][i] * 0.55; } return FF.writeFile('mix.raw', new Uint8Array(mix.buffer)); })
  .then(function () { ffLog = []; return ex(['-f', 'f32le', '-ar', '48000', '-ac', '2', '-i', 'mix.raw', '-af', 'aformat=channel_layouts=stereo,loudnorm=I=-14:TP=-2:LRA=9:print_format=json', '-f', 'null', '-']); })
  .then(function () { var s = ffLog.join('\n'), j = null; try { j = JSON.parse(s.slice(s.lastIndexOf('{'), s.lastIndexOf('}') + 1)); } catch (e) {}
   var ln = j ? 'loudnorm=I=-14:TP=-2:LRA=9:measured_I=' + j.input_i + ':measured_TP=' + j.input_tp + ':measured_LRA=' + j.input_lra + ':measured_thresh=' + j.input_thresh + ':offset=' + j.target_offset + ':linear=true' : 'loudnorm=I=-14:TP=-2:LRA=9';
   return ex(['-f', 'f32le', '-ar', '48000', '-ac', '2', '-i', 'mix.raw', '-af', 'aformat=channel_layouts=stereo,' + ln + ',alimiter=limit=0.89:attack=3:release=80:level=false,aresample=48000,aformat=channel_layouts=stereo', '-t', TT.toFixed(3), '-c:a', 'pcm_s16le', 'mix.wav']); })
  .then(function () { del('mix.raw'); }); }

/* ---------------- the page ---------------- */
var S = { file: null, photos: [], style: 'kilgore-list', v: null, a16: null, a48: null, words: null, removed: [], busy: false, url: null };
function show(t, p, n) { $('#rxStep').textContent = t; if (p != null) { $('#rxBar').style.width = clamp(Math.round(p), 2, 100) + '%'; } if (n != null) { $('#rxNote').textContent = n; } }
function fail(m) { var e = $('#rxErr'); e.textContent = m; e.hidden = false; $('#rxWork').hidden = true; S.busy = false; $('#rxGo').disabled = !S.file; e.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
function mb(n) { return (n / 1048576).toFixed(1) + ' MB'; }
function styleCards() { $('#rxStyles').innerHTML = STYLES.map(function (s) { var sw = s.fam === 'a' ? '<i style="background:#111"></i><i style="background:' + s.look.accent_text + '"></i><i style="background:#fff"></i>' : '<i style="background:' + s.look.accent + '"></i><i style="background:' + s.look.accent2 + '"></i><i style="background:#fff"></i>';
 return '<button type="button" class="rx-st' + (s.id === S.style ? ' on' : '') + '" data-id="' + s.id + '"><span class="sw">' + sw + '</span><b>' + esc(s.name) + '</b><small>' + esc(s.desc) + '</small>' + (PRO[s.id] ? '<em style="color:var(--k1);opacity:1">★ ' + esc(PRO[s.id].moves) + '</em>' : '') + '<em>Like ' + esc(s.who) + '</em></button>'; }).join(''); }
function loadVid(f) { return new Promise(function (res, rej) { var v = document.createElement('video'); v.muted = true; v.playsInline = true; v.setAttribute('playsinline', ''); v.preload = 'auto'; v.crossOrigin = 'anonymous'; v.style.cssText = 'position:fixed;left:-9999px;top:0;width:2px;height:2px;opacity:0'; document.body.appendChild(v); v.src = URL.createObjectURL(f); v.onloadeddata = function () { res(v); }; v.onerror = function () { rej(new Error('This video could not be opened. Try an MP4 or MOV from your phone.')); }; }); }
function prepare() { /* once per video: open it, pull the sound, hear every word */
 if (S.words) { return Promise.resolve(); }
 show('Opening your video...', 3, ''); return Promise.all([loadVid(S.file), loadFonts(), loadFF()]).then(function (r) { S.v = r[0]; if (!isFinite(S.v.duration) || S.v.duration < 2) { throw new Error('This video is too short.'); } if (S.v.duration > 185) { throw new Error('This video is ' + Math.round(S.v.duration) + ' seconds. The editor takes up to 3 minutes. Trim it and try again.'); }
  show('Reading your sound...', 6); var nm = 'in.' + ((S.file.name.match(/\.([a-z0-9]{2,4})$/i) || [0, 'mp4'])[1].toLowerCase()), inp = nm, mounted = false;
  /* Read the video straight from the file (no copy in memory). Older engines: fall back to copying. */
  var mount = function () { return FF.unmount('/in').catch(function () {}).then(function () { return FF.createDir('/in').catch(function () {}); }).then(function () { return FF.mount('WORKERFS', { files: [S.file] }, '/in'); }).then(function () { mounted = true; inp = '/in/' + S.file.name; }); };
  var copy = function () { inp = nm; return S.file.arrayBuffer().then(function (b) { return FF.writeFile(nm, new Uint8Array(b)); }); };
  return mount().catch(copy).then(function () { return ex(['-i', inp, '-vn', '-ac', '1', '-ar', '16000', '-f', 'f32le', 'a16.raw', '-vn', '-ac', '1', '-ar', '48000', '-f', 'f32le', 'a48.raw']); })
   .then(function () { return ex(['-f', 'f32le', '-ar', '16000', '-ac', '1', '-i', 'a16.raw', '-c:a', 'libmp3lame', '-b:a', '32k', 'fix.mp3']).then(function () { return FF.readFile('fix.mp3'); }).then(function (d) { S.mp3 = d; del('fix.mp3'); }).catch(function () { S.mp3 = null; }); })
   .then(function () { return rdF('a16.raw'); }).then(function (a) { S.a16 = a; return rdF('a48.raw'); }).then(function (a) { S.a48 = a; })
   .then(function () { return mounted ? FF.unmount('/in').catch(function () {}) : del(nm); }); })
 .then(function () { show('Loading the speech model (first time only)...', 10, 'About 75 MB the first time, then it is quick.'); var a = S.a16, pk = 0, i; for (i = 0; i < a.length; i += 7) { pk = Math.max(pk, Math.abs(a[i])); } var k = pk > 0.02 && pk < 0.8 ? 0.85 / pk : 1, aa = new Float32Array(a.length); for (i = 0; i < a.length; i++) { aa[i] = a[i] * k; }
  return transcribe(aa, function (t, p) { show(t, p != null ? 10 + p * 0.15 : 28, p != null ? '' : 'This takes about as long as your video.'); }); })
 .then(function (out) { S.a16 = null; S.words = wordsFrom(out); if (S.words.length < 3) { throw new Error('I could not hear clear talking in this video. The Reel Editor needs someone speaking.'); } show('Checking every word...', 30, ''); return aiFix(S.words).then(function (w) { S.words = w; }); }); }
function make() { if (S.busy || !S.file) { return; } S.busy = true; $('#rxGo').disabled = true; $('#rxErr').hidden = true; $('#rxWork').hidden = false; $('#rxOut').hidden = true; $('#rxWork').scrollIntoView({ behavior: 'smooth', block: 'center' });
 var job = { st: styleById(S.style) }, extra = { cap: +$('#rxCap').value / 100, zoom: +$('#rxZoom').value / 100, sfx: +$('#rxSfx').value / 100 };
 job.st = ext(job.st, { look: ext(job.st.look, { cap_size: job.st.look.cap_size * extra.cap, zoom: job.st.look.zoom * extra.zoom, sfx: job.st.look.sfx * extra.sfx }) });
 var gv = function (id) { var el = $('#' + id); return el ? String(el.value || '').trim() : ''; };
 job.st.pro = proFor(job.st.id, +(gv('rxPace') || 1), !$('#rxPro') || $('#rxPro').checked); job.st.hook = gv('rxHook').slice(0, 60); job.st.sub = gv('rxSub').slice(0, 50); job.st.cta = gv('rxCta').replace(/[^\w-]/g, '').slice(0, 20);
 prepare().then(function () {
  show('Planning the edit like Reubx...', 32, ''); var db = envDb(S.a48), sil = silences(db); job.plan = autoplan(S.words, job.st, S.removed); job.cut = doCut(S.words, job.plan, sil, db, S.v.duration); if (!job.cut) { throw new Error('Nothing left to edit after the cuts.'); }
  job.shots = makeShots(job.cut, job.plan, job.st); job.layer = buildLayer(job.cut, job.plan, job.st, job.shots, S.photos);
  return buildAudio(S.a48, job, function (t) { show(t, 36); }); })
 .then(function () { show('Rendering every frame...', 40, 'Keep this page open. It takes about 1 to 2 times the length of your video.'); return renderVideo(S.v, job, function (q) { show('Rendering every frame...', 40 + q * 52); }); })
 .then(function (mp4) { show('Putting it together...', 94); return FF.writeFile('v.mp4', mp4).then(function () { return ex(['-i', 'v.mp4', '-i', 'mix.wav', '-map', '0:v:0', '-map', '1:a:0', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '192k', '-shortest', '-movflags', '+faststart', 'final.mp4']); }).then(function () { return FF.readFile('final.mp4'); }); })
 .then(function (d) { del('v.mp4'); del('mix.wav'); del('final.mp4'); if (S.url) { URL.revokeObjectURL(S.url); } var blob = new Blob([d.buffer], { type: 'video/mp4' }); S.url = URL.createObjectURL(blob); S.job = job; S.busy = false; $('#rxGo').disabled = false; $('#rxWork').hidden = true;
  var nm = (S.file.name.replace(/\.[^.]+$/, '') || 'reel') + '-' + job.st.id + '.mp4', o = $('#rxOut'); o.hidden = false;
  $('#rxVid').src = S.url; $('#rxDl').href = S.url; $('#rxDl').setAttribute('download', nm); $('#rxSize').textContent = mb(blob.size) + ' · ' + Math.round(job.cut.T + job.layer.END) + ' s';
  var cutS = (S.v.duration - job.cut.T).toFixed(1); $('#rxSum').textContent = 'Style: ' + job.st.name + '. Cut ' + cutS + ' s of pauses and retakes. ' + job.plan.punch.length + ' punch-ins' + (job.st.fam === 'b' ? ', ' + job.plan.blocks.length + ' keyword blocks' : ', ' + job.plan.accent.length + ' accent words') + (job.layer.cards ? ', ' + job.layer.cards + ' pro cards' : '') + '.';
  words(); o.scrollIntoView({ behavior: 'smooth', block: 'start' }); })
 .catch(function (e) { var m = String(e && e.message || e); if (/nocodec/.test(m)) { m = 'This browser cannot build videos. Please use Chrome on a computer or Android, or Safari on iPhone with iOS 17 or newer.'; } else if (/memory|OOM|allocation|RangeError/i.test(m)) { m = 'This video is too big for your device. Try a shorter clip (under 90 seconds) or use a computer.'; } else if (/^ff:/.test(m)) { m = 'The video engine stopped. Refresh the page and try again.'; } else if (/worker|slow/.test(m)) { m = 'The speech model could not run. Refresh the page and try again, ideally on Wi-Fi.'; } fail(m); }); }
function words() { var cutI = {}; S.removed.forEach(function (i) { cutI[i] = 1; }); $('#rxWords').innerHTML = S.words.map(function (w, i) { return '<button type="button" data-i="' + i + '" class="' + (cutI[i] ? 'off' : '') + '">' + esc(w.w) + '</button>'; }).join(' '); }
function init() { styleCards();
 $('#rxStyles').addEventListener('click', function (e) { var b = e.target.closest('.rx-st'); if (!b) { return; } S.style = b.getAttribute('data-id'); styleCards(); });
 $('#rxFile').addEventListener('change', function () { var f = this.files[0]; if (!f) { return; } if (f.size > 600 * 1048576) { fail('Choose a video under 600 MB.'); return; } S.file = f; S.words = null; S.removed = []; if (S.v) { try { S.v.remove(); } catch (e) {} S.v = null; } $('#rxPicked').innerHTML = '<div class="rx-got">✓ <b>' + esc(f.name) + '</b> <small>' + mb(f.size) + '</small></div>'; $('#rxGo').disabled = false; $('#rxErr').hidden = true; });
 $('#rxPh').addEventListener('change', function () { S.photos = []; var fs = [].slice.call(this.files, 0, 6); Promise.all(fs.map(function (f) { return new Promise(function (r) { var im = new Image(); im.onload = function () { r(im); }; im.onerror = function () { r(null); }; im.src = URL.createObjectURL(f); }); })).then(function (ims) { S.photos = ims.filter(Boolean); $('#rxPhN').textContent = S.photos.length ? S.photos.length + ' photo' + (S.photos.length > 1 ? 's' : '') + ' added' : ''; }); });
 $('#rxGo').addEventListener('click', make); $('#rxAgain').addEventListener('click', make);
 var fb = document.createElement('div'); fb.style.cssText = 'display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 10px'; fb.innerHTML = '<button type="button" id="rxFixM" class="v-go" style="padding:8px 14px;font-size:14px">✏️ Fix words</button><small class="v-muted" id="rxFixH">Tap a word to cut it out.</small>'; $('#rxWords').parentNode.insertBefore(fb, $('#rxWords'));
 $('#rxFixM').addEventListener('click', function () { S.fix = !S.fix; this.textContent = S.fix ? '✓ Done fixing' : '✏️ Fix words'; $('#rxFixH').textContent = S.fix ? 'Tap a word to correct its spelling. Then tap Re-edit.' : 'Tap a word to cut it out.'; });
 $('#rxWords').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) { return; } if (S.fix) { var ii = +b.getAttribute('data-i'), nv = window.prompt('Correct this word:', S.words[ii].w); if (nv != null && nv.trim()) { S.words[ii].w = nv.trim().slice(0, 40); b.textContent = S.words[ii].w; } return; } var i = +b.getAttribute('data-i'), k = S.removed.indexOf(i); if (k >= 0) { S.removed.splice(k, 1); } else { S.removed.push(i); } b.classList.toggle('off'); });
 ['rxCap', 'rxZoom', 'rxSfx'].forEach(function (id) { var el = $('#' + id); el.addEventListener('input', function () { $('#' + id + 'V').textContent = el.value + '%'; }); }); }
if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
