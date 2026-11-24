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
var B_LOOK = { cap_size: 70, font_main: 'Montserrat', font_lead: 'Poppins', font_cap: 'Poppins', main_color: '#ffffff', hl: '#FFD60A', stroke: '#0B0B0F', outline: 0.35, cap_w: 800, cap_pos: 'chest', emph: 'color', cap_bg: 'none', font_emph: 'Instrument Serif', accent: '#FFD60A', accent2: '#FFE680', sfx: 1.0, zoom: 0.08 };
var SOLID = { main_color: '#ffffff', hl: '#FFD60A', stroke: '#0B0B0F' };
var IOS = /iP(hone|ad|od)/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1), MAXS = IOS ? 360 : 600;
function ext(a, b) { var o = {}, k; for (k in a) { o[k] = a[k]; } for (k in b) { o[k] = b[k]; } return o; }
var STYLES = [
 { id: 'ref-chloe', name: 'Fast numbered list', who: 'chloe.shih \"1 million in 4 months\"', fam: 'b', ref: true, desc: 'Fastest pace (a cut every 3 s). Small captions above your head, yellow key words, numbered headers, word cards.', look: ext(B_LOOK, { font_main: 'Inter', font_lead: 'Inter', font_cap: 'Inter', cap_w: 700, cap_size: 58, cap_pos: 'top', main_color: '#FFE600', hl: '#FFE600', accent: '#FFE600', accent2: '#FFF27A', outline: 0.2, sfx: 0.8, zoom: 0.08, hdr: 'num' }) },
 { id: 'ref-kallaway', name: 'Dark authority', who: 'kallawaymarketing \"60 days of content\"', fam: 'b', ref: true, desc: 'Dark and moody. White bold words, red label headers, centred captions, calm cuts.', look: ext(B_LOOK, { font_main: 'Inter', font_lead: 'Inter', font_cap: 'Inter', cap_w: 800, cap_size: 62, cap_pos: 'center', main_color: '#ffffff', hl: '#ffffff', accent: '#E10600', accent2: '#FF6B6B', outline: 0, sfx: 0.6, zoom: 0.05, hdr: 'red' }) },
 { id: 'ref-social', name: 'Clean explainer', who: 'socialinfluencestudios \"4 ads that convert\"', fam: 'b', ref: true, desc: 'One steady shot, white box headers like \"1. TRANSFORMATION\", bold white captions.', look: ext(B_LOOK, { font_main: 'Inter', font_lead: 'Inter', font_cap: 'Inter', cap_w: 700, cap_size: 60, main_color: '#ffffff', hl: '#ffffff', accent: '#111111', accent2: '#ffffff', outline: 0, sfx: 0.4, zoom: 0.0, hdr: 'box' }) },
 { id: 'ref-wolf', name: 'Gold keyword', who: 'wolfofwallst \"3 things people buy\"', fam: 'b', ref: true, desc: 'Podcast feel. Small white lead + big yellow uppercase keyword, slow push-ins.', look: ext(B_LOOK, { font_main: 'Montserrat', font_lead: 'Poppins', font_cap: 'Poppins', cap_w: 800, cap_size: 64, main_color: '#FFD400', hl: '#FFD400', accent: '#FFD400', accent2: '#FFE680', outline: 0.2, sfx: 0.8, zoom: 0.08, hdr: 'num' }) },
 { id: 'ref-tumi', name: 'Warm gold stacks', who: 'tumiifebogun \"launch before year end\"', fam: 'b', ref: true, desc: 'Small lead, one huge gold word, small tail. Light word cards like \"6 Months\".', look: ext(B_LOOK, { font_main: 'Bricolage Grotesque', font_lead: 'Inter', font_cap: 'Inter', cap_w: 700, cap_size: 56, main_color: '#E3B14A', hl: '#E3B14A', accent: '#D8A73A', accent2: '#F6D77A', outline: 0, sfx: 0.6, zoom: 0.07 }) },
 { id: 'ref-millie', name: 'Script emphasis', who: 'itsmodernmillie \"why you\'re not growing\"', fam: 'b', ref: true, desc: 'One locked shot. Bold captions with handwritten-style key words and \"Mistake #3:\" headers.', look: ext(B_LOOK, { font_main: 'Inter', font_lead: 'Inter', font_cap: 'Inter', cap_w: 700, cap_size: 58, emph: 'script', main_color: '#ffffff', hl: '#ffffff', accent: '#ffffff', accent2: '#ffffff', outline: 0, sfx: 0.4, zoom: 0.0, hdr: 'script' }) },
 { id: 'ref-deshna', name: 'Red box emphasis', who: 'bytheway.deshna \"scripting\"', fam: 'b', ref: true, desc: 'Wide and tight hard cuts. Key phrase sits in a red box, script italic words.', look: ext(B_LOOK, { font_main: 'Montserrat', font_lead: 'Inter', font_cap: 'Montserrat', cap_w: 800, cap_size: 58, emph: 'box', main_color: '#ffffff', hl: '#ffffff', accent: '#D7262E', accent2: '#FF8A8F', outline: 0, sfx: 0.6, zoom: 0.1 }) },
 { id: 'ref-zach', name: 'Caption pill', who: 'zachdoesai_ \"marketing machine\"', fam: 'b', ref: true, desc: 'Small white captions on a dark pill, steady camera, calm cuts.', look: ext(B_LOOK, { font_main: 'Inter', font_lead: 'Inter', font_cap: 'Inter', cap_w: 700, cap_size: 50, cap_bg: 'pill', main_color: '#ffffff', hl: '#ffffff', accent: '#D97757', accent2: '#F3C2AE', outline: 0, sfx: 0.8, zoom: 0.04 }) },
 { id: 'ref-sky', name: 'Green HUD', who: 'realskytan \"perfect storytelling\"', fam: 'b', ref: true, desc: 'One dark steady shot, regular-weight white captions, bright green key words.', look: ext(B_LOOK, { font_main: 'Inter', font_lead: 'Inter', font_cap: 'Inter', cap_w: 500, cap_size: 58, main_color: '#7CFF4F', hl: '#7CFF4F', accent: '#4CD137', accent2: '#9CFF7A', outline: 0, sfx: 0.9, zoom: 0.03 }) },
 { id: 'ref-adam', name: 'Tutorial', who: 'adam.kelada.media \"6 settings\"', fam: 'b', ref: true, desc: 'Numbered steps, white captions with gold key words. Good for how-to reels.', look: ext(B_LOOK, { font_main: 'Inter', font_lead: 'Inter', font_cap: 'Inter', cap_w: 800, cap_size: 60, main_color: '#ffffff', hl: '#F5C518', accent: '#F5C518', accent2: '#FFE27A', outline: 0, sfx: 0.7, zoom: 0.06, hdr: 'num' }) },
 { id: 'ref-glass', name: 'Glass', who: 'frosted glass cards', fam: 'b', ref: true, desc: 'Frosted-glass cards and lists over your video, white captions with ice-blue key words.', look: ext(B_LOOK, { font_main: 'Inter', font_lead: 'Inter', font_cap: 'Inter', cap_w: 800, cap_size: 58, main_color: '#ffffff', hl: '#8FD3FF', accent: '#8FD3FF', accent2: '#CFEAFF', outline: 0.1, sfx: 0.8, zoom: 0.05 }) },
 { id: 'ref-cinematic', name: 'Cinematic', who: 'kreatize_ \"definition of success\"', fam: 'b', ref: true, ed: true, desc: 'Dark, desaturated, gold serif keywords.', look: ext(B_LOOK, { font_main: 'Instrument Serif', font_lead: 'Inter', font_cap: 'Inter', cap_size: 54, main_color: '#E9C46A', hl: '#E9C46A', accent: '#E9C46A', accent2: '#FFF3C4', outline: 0, sfx: 0.9, zoom: 0.05 }) },
 { id: 'kilgore-list', name: 'Feature list', who: 'Kilgore "15 ways"', fam: 'a', desc: 'Calm, one word at a time, yellow serif accents. Best for tips and lists.', look: ext(A_LOOK, { cap_size: 70, sfx: 0.8, zoom: 0.06 }) },
 { id: 'kilgore-story', name: 'Story', who: 'Kilgore "say it out loud"', fam: 'a', desc: 'Gentle and emotional. Few cuts, soft zooms, typed ending.', look: ext(A_LOOK, { cap_size: 66, sfx: 0.5, zoom: 0.05 }) },
 { id: 'kilgore-community', name: 'Big numbers', who: 'Kilgore "build the room"', fam: 'a', desc: 'Results and numbers stand out. Great for wins and case studies.', look: ext(A_LOOK, { cap_size: 70, sfx: 0.7, zoom: 0.06 }) },
 { id: 'kilgore-skit', name: 'Fast skit', who: 'Kilgore laundry skit', fam: 'a', desc: 'Tight hook, quick cuts, punch-in on every beat.', look: ext(A_LOOK, { cap_size: 74, sfx: 1.0, zoom: 0.1, start_tight: true }) },
 { id: 'kilgore-flip', name: 'Hook flip', who: 'Kilgore "just kidding"', fam: 'a', desc: 'Bold hook, energetic zooms, yellow accent words.', look: ext(A_LOOK, { cap_size: 70, sfx: 0.9, zoom: 0.08, start_tight: true }) },
 { id: 'edita-lawyer', name: 'Loud keywords', who: 'edita "home paid off"', fam: 'b', desc: 'Big bold white keyword stacks with a yellow highlight and hits.', look: ext(B_LOOK, { sfx: 1.0, zoom: 0.1 }) },
 { id: 'gallo-promo', name: 'Premium promo', who: 'Anthony Gallo SaaS promo', fam: 'b', ed: false, desc: 'Clean premium look: white words with an orange highlight.', look: ext(B_LOOK, { font_main: 'Bricolage Grotesque', font_lead: 'Manrope', font_cap: 'Manrope', main_color: '#ffffff', hl: '#FF5A1F', accent: '#FF5A1F', accent2: '#FFC4A8', sfx: 0.9, zoom: 0.08 }) },
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
 'gallo-promo': { tr: ['rebound', 'swing', 'slide', 'flash'], trV: 0.9, cut: 4, punch: 3, cards: 'light', pill: true, moves: 'Full-screen lavender step cards, CTA pill' },
 'edita-interview': { tr: ['fade', 'leak'], trV: 0.6, trI: 0.6, cut: 6, punch: 5, quote: true, moves: 'Quote card on the key line' },
 'edita-podcast': { tr: ['fade', 'slide'], trV: 0.6, trI: 0.6, cut: 5, punch: 4, quote: true, photoItems: true, moves: 'Quote card, your photos on each point' },
 'edita-blueprint': { tr: ['slide', 'zoomrush', 'stamp', 'superzoom'], trV: 0.9, cut: 5, punch: 4, cards: 'ui', recap: 'The Blueprint', moves: 'Point cards plus a Blueprint recap card' },
 'ref-chloe': { tr: ['rebound', 'stamp', 'swing', 'flash', 'zoomrush'], trV: 0.9, cut: 3, punch: 4, hdr: true, wcards: true, pace: 'tight', moves: 'Numbered headers, word cards, cut every 3 s' },
 'ref-kallaway': { tr: ['zoomrush', 'slices', 'superzoom'], trV: 0.85, cut: 6, punch: 5, hdr: true, wcards: true, pace: 'tight', moves: 'Red headers, word cards' },
 'ref-social': { tr: ['fade', 'zoomrush'], trV: 0.6, trI: 0.6, cut: 60, punch: 99, hdr: true, wcards: true, pace: 'tight', moves: 'Steady shot, box headers' },
 'ref-wolf': { tr: ['zoomrush', 'fade', 'leak'], trV: 0.7, trI: 0.7, cut: 7, punch: 4, hdr: true, wcards: true, pace: 'tight', moves: 'Numbered headers, gold keywords' },
 'ref-tumi': { tr: ['leak', 'fade', 'zoomrush', 'slide'], trV: 0.7, trI: 0.8, cut: 4, punch: 4, wcards: true, pace: 'tight', moves: 'Paper word cards, gold stacks' },
 'ref-millie': { tr: ['fade', 'slide'], trV: 0.55, trI: 0.6, cut: 90, punch: 99, hdr: true, pace: 'tight', moves: 'Locked shot, script headers' },
 'ref-deshna': { tr: ['stamp', 'swing', 'rebound', 'flash'], trV: 0.9, cut: 4, punch: 3, wcards: true, pace: 'tight', moves: 'Wide/tight cuts, red box words' },
 'ref-zach': { tr: ['slide', 'fade'], trV: 0.6, trI: 0.7, cut: 8, punch: 6, wcards: true, pace: 'tight', moves: 'Caption pill, calm cuts' },
 'ref-sky': { tr: ['zoomrush', 'flash', 'slices'], trV: 0.8, cut: 90, punch: 99, pace: 'tight', moves: 'Locked shot, green key words' },
 'ref-adam': { tr: ['slide', 'zoomrush', 'stamp'], trV: 0.8, cut: 7, punch: 5, hdr: true, wcards: true, pace: 'tight', moves: 'Numbered steps' },
 'ref-glass': { tr: ['fade', 'slide', 'zoomrush'], trV: 0.7, trI: 0.7, cut: 6, punch: 5, wcards: true, pace: 'tight', moves: 'Glass cards and lists' },
 'ref-cinematic': { tr: ['fade', 'leak', 'zoomrush'], trV: 0.7, trI: 0.7, cut: 5, punch: 4, wcards: true, pace: 'tight', moves: 'Dark cinematic, gold serif' }
};
function proFor(id, pace, on) { var b = PRO[id] || {}, o = {}, k; for (k in b) { o[k] = b[k]; } o.cut = (o.cut || 5) * pace; o.punch = (o.punch || 4) * pace;
 if (!on) { ['hdr', 'wcards', 'cards', 'nums', 'sticker', 'story', 'chat', 'flip', 'bar', 'pill', 'quote', 'recap', 'photoItems', 'sub'].forEach(function (x) { delete o[x]; }); } return o; }
function styleById(id) { for (var i = 0; i < STYLES.length; i++) { if (STYLES[i].id === id) { return STYLES[i]; } } return STYLES[0]; }
var GRADE = { a: { b: 0.025, c: 1.05, s: 1.05, g: 1.03, rm: 0.02, bm: -0.012 }, b: { b: 0.03, c: 1.06, s: 1.07, g: 1.04, rm: 0, bm: 0 } };

/* ---------------- fonts ---------------- */
var FONTS = [['Inter', 'fonts/inter600.woff2', '400 699', 'normal'], ['Inter', 'fonts/inter.woff2', '700 900', 'normal'], ['Instrument Serif', 'assets/InstrumentSerif-Italic.ttf', '400', 'italic'], ['Gochi', 'assets/GochiHand-Regular.ttf', '400', 'normal'],
 ['Montserrat', 'assets/Montserrat-ExtraBold.ttf', '800', 'normal'], ['Montserrat', 'assets/Montserrat-Black.ttf', '900', 'normal'], ['Poppins', 'assets/Poppins-SemiBold.ttf', '600', 'normal'],
 ['Poppins', 'assets/Poppins-Bold.ttf', '700', 'normal'], ['Poppins', 'assets/Poppins-ExtraBold.ttf', '800', 'normal'], ['Poppins', 'assets/Poppins-MediumItalic.ttf', '500', 'italic'],
 ['Manrope', 'fonts/manrope.woff2', '600', 'normal'], ['Caveat', 'fonts/caveat.woff2', '400 700', 'normal'], ['Bricolage Grotesque', 'fonts/bricolage.woff2', '800', 'normal']];
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
 var t = setTimeout(function () { try { w.terminate(); } catch (e) {} rej(new Error('slow')); }, Math.max(900000, a16.length / 16000 * 4000));
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
  var P = st.pro || {}, fam = st.fam, plan = { remove: [], shots: [], punch: [], accent: [], blocks: [], counts: [], key: {} };
 plan.tight = !!(st.pro && st.pro.pace === 'tight'); plan.remove = retakeHints(words); var rem = {}; plan.remove.forEach(function (r) { for (var i = r[0]; i <= r[1]; i++) { rem[i] = 1; } }); (removed || []).forEach(function (i) { rem[i] = 1; }); plan.rem = rem;
 var sents = sentences(words).map(function (s) { return s.filter(function (i) { return !rem[i]; }); }).filter(function (s) { return s.length; });
  var last = 0; sents.slice(1).forEach(function (s) { if (words[s[0]].s - last >= (P.cut || 5)) { plan.shots.push(s[0]); last = words[s[0]].s; } });
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

  /* numbered headers ('Number one, ...', 'Step two', 'The third mistake') and number+unit word cards (from the 12-reel study) */
  var NW2 = { one: 1, two: 2, three: 3, four: 4, five: 5, six: 6, seven: 7, eight: 8, nine: 9, ten: 10, first: 1, second: 2, third: 3, fourth: 4, fifth: 5, sixth: 6, seventh: 7, eighth: 8, ninth: 9, tenth: 10 }, LAB = { step: 1, mistake: 1, tip: 1, rule: 1, lesson: 1, reason: 1, way: 1, hook: 1, sign: 1, secret: 1, habit: 1 }, UNITS = { months: 1, month: 1, days: 1, day: 1, weeks: 1, week: 1, years: 1, year: 1, hours: 1, minutes: 1, million: 1, thousand: 1, followers: 1, views: 1, clients: 1 };
  var numOf = function (w) { w = cleanW(w).toLowerCase(); return NW2[w] || (/^\d+$/.test(w) && +w < 50 ? +w : 0); };
  plan.headers = []; plan.wcards = [];
  if (P.hdr || REF[st.id]) { sents.forEach(function (s) { var ws = s.map(function (i) { return cleanW(words[i].w).toLowerCase(); }), n = 0, lab = '', start = 0;
    for (var k = 0; k < Math.min(4, ws.length - 1); k++) {
     if (ws[k] === 'number' && numOf(ws[k + 1])) { n = numOf(ws[k + 1]); start = k + 2; }
     else if (LAB[ws[k]] && numOf(ws[k + 1])) { n = numOf(ws[k + 1]); lab = ws[k]; start = k + 2; }
     else if (/^(first|second|third|fourth|fifth)$/.test(ws[k]) && LAB[ws[k + 1]]) { n = NW2[ws[k]]; lab = ws[k + 1]; start = k + 2; }
     else if (k === 0 && numOf(ws[0]) && /[,.]$/.test(words[s[0]].w)) { n = numOf(ws[0]); start = 1; }
     if (n) { break; } }
    if (!n) { return; } var rest = []; for (var j = start; j < s.length; j++) { var cw = cleanW(words[s[j]].w); if (!cw) { continue; } if (!rest.length && /^(is|are|the|a|an|to)$/i.test(cw)) { continue; } rest.push(cw); if (rest.length >= 4 || /[,.?!]$/.test(words[s[j]].w)) { break; } }
    while (rest.length && STOP[rest[rest.length - 1].toLowerCase()]) { rest.pop(); } if (!rest.length) { return; }
    plan.headers.push({ w: [s[0], s[s.length - 1]], n: n, lab: lab, text: rest.join(' ') }); }); }
  if (P.wcards || REF[st.id]) { var lastW = -99; sents.forEach(function (s) { for (var k = 0; k + 1 < s.length; k++) { var i = s[k], nn = numOf(words[i].w) || (/^[\d,.]+$/.test(cleanW(words[i].w)) ? cleanW(words[i].w) : 0), u = cleanW(words[s[k + 1]].w).toLowerCase();
    if (nn && UNITS[u] && words[i].s - lastW >= 14 && words[i].s > 2 && !plan.headers.some(function (h) { return h.w[0] === s[0]; })) { plan.wcards.push({ w: [i, s[k + 1]], text: nn + ' ' + u.charAt(0).toUpperCase() + u.slice(1), lead: s.slice(Math.max(0, k - 3), k).map(function (j) { return cleanW(words[j].w).toLowerCase(); }).join(' ') }); lastW = words[i].s; break; } } }); }
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
 var tight = plan.tight, PAD_IN = tight ? 0.16 : 0.22, PAD_OUT = tight ? 0.12 : 0.16, MAXGAP = tight ? 0.32 : 0.45;
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
function renderSfx(events, total, off) { rs = 7; off = off || 0; var n = Math.ceil(SR * total - 1e-6), L = new Float32Array(n), R = new Float32Array(n);
 events.forEach(function (ev, k) { var x = sampleOf(ev[1], Math.round(ev[0] * 10)); if (!x) { return; } var s = Math.floor((off ? ev[0] - off : Math.max(ev[0], 0)) * SR); for (var i = Math.max(0, -s); i < x[0].length && s + i < n; i++) { L[s + i] += x[0][i] * ev[3]; R[s + i] += x[1][i] * ev[3]; } });
 var pk = 0, i; for (i = 0; i < n; i++) { pk = Math.max(pk, Math.abs(L[i]), Math.abs(R[i])); } if (pk > 0.98) { var k = 0.98 / pk; for (i = 0; i < n; i++) { L[i] *= k; R[i] *= k; } } return [L, R]; }

/* ---------------- graphics layer (port of style_a.py / style_b.py typography) ---------------- */

var LUMC = null;
function bgLum(c, x, y, w, h) { try { var m = c.getTransform(), cv = c.canvas, sx = Math.max(0, Math.floor(m.e + x * m.a)), sy = Math.max(0, Math.floor(m.f + y * m.d)), ex = Math.min(cv.width, Math.ceil(m.e + (x + w) * m.a)), ey = Math.min(cv.height, Math.ceil(m.f + (y + h) * m.d)); if (ex - sx < 4 || ey - sy < 4) { return 0.4; }
  if (!LUMC) { LUMC = document.createElement('canvas'); LUMC.width = 8; LUMC.height = 3; } var g = LUMC.getContext('2d', { willReadFrequently: true }); g.drawImage(cv, sx, sy, ex - sx, ey - sy, 0, 0, 8, 3); var d = g.getImageData(0, 0, 8, 3).data, t = 0, i; for (i = 0; i < d.length; i += 4) { t += 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2]; } return t / (24 * 255); } catch (e) { return 0.4; } }
function capText(c, txt, x, y, fill, lum) { c.save(); var hi = lum > 0.55; c.shadowColor = 'rgba(0,0,0,' + (hi ? 0.5 : 0.28) + ')'; c.shadowBlur = hi ? 22 : 18; c.shadowOffsetY = 3; c.fillStyle = fill; c.fillText(txt, x, y); c.restore(); }
function calmSfx(ev) { var ok = /^(pop|click|toggle|paper|typing|tick|chime)/, out = [], last = -9; ev.slice().sort(function (a, b) { return a[0] - b[0]; }).forEach(function (e) { if (!ok.test(e[1])) { return; } if (e[1] !== 'typing' && e[0] - last < 1.8) { return; } last = e[0]; out.push([e[0], e[1], e[2], Math.min(e[3], 0.3)]); }); return out; }
function shadowText(c, txt, x, y, fill, blur1) { c.save(); c.shadowColor = 'rgba(0,0,0,0.34)'; c.shadowBlur = Math.max(16, blur1 || 16); c.shadowOffsetY = 2; c.fillStyle = fill; c.fillText(txt, x, y); c.restore(); }
function outline(c, txt, x, y, sz) { var lk = OUTL; if (!lk || !(lk.outline > 0)) { return; } c.save(); c.lineJoin = 'round'; c.lineWidth = Math.max(2, sz * 0.07 * lk.outline * 2); c.strokeStyle = lk.stroke || '#000'; void 0 && c.strokeText(txt, x, y); c.restore(); }
var OUTL = null;
function scrim(c, w, h, a) { var g = c.createRadialGradient(0, 0, 0, 0, 0, Math.max(w, h) / 2); g.addColorStop(0, 'rgba(0,0,0,' + a + ')'); g.addColorStop(1, 'rgba(0,0,0,0)'); c.save(); c.scale(1, h / Math.max(w, h)); c.fillStyle = g; c.beginPath(); c.arc(0, 0, Math.max(w, h) / 2, 0, Math.PI * 2); c.fill(); c.restore(); }
function fitSize(c, txt, font, size, maxW) { c.font = font(size); while (size > 30 && c.measureText(txt).width > maxW) { size = Math.floor(size * 0.94); c.font = font(size); } return size; }
/* an item = { t0, t1, kind:'pop'|'rise'|'slam'|'fade'|'zoom', dur, out:'cut'|'blur', outd, x, y, draw(c, t) } */
function itemAlpha(it, t) { var a = 1, sc = 1, dy = 0, din = (it.dur || 200) / 1000, u = (t - it.t0) / din;
 if (it.kind === 'pop') { a = clamp(u * 2, 0, 1); sc = 0.86 + 0.14 * easeOutBack(u); } else if (it.kind === 'rise') { a = clamp(u, 0, 1); dy = 30 * (1 - easeOut(u)); } else if (it.kind === 'slam') { a = clamp(u * 3, 0, 1); sc = 1 + 0.4 * (1 - easeOut(u)); } else if (it.kind === 'zoom') { a = clamp(u * 2, 0, 1); sc = 0.6 + 0.4 * easeOutBack(u); } else { a = clamp(u, 0, 1); }
 if (it.out !== 'cut') { var od = (it.outd || 160) / 1000; a *= clamp((it.t1 - t) / od, 0, 1); } return { a: a, sc: sc, dy: dy }; }
function buildLayer(cut, plan, st, shots, photos) {
  var L = [], EV = [], look = st.look, WO = {}, T = cut.T; OUTL = look; cut.words.forEach(function (x) { WO[x.i] = x; });
 function t_of(i) { return WO[i] ? WO[i].o : null; } function te_of(i) { return WO[i] ? WO[i].oe : null; }
 function shotAt(t) { for (var k = 0; k < shots.length; k++) { if (shots[k][0] <= t && t < shots[k][1]) { return shots[k]; } } return shots[shots.length - 1]; }
 function capY(t) { var z = shotAt(t)[2] === 'T' ? 1.16 : 1.0; if (look.cap_pos === 'top') { var y0 = faceY(z).head - 100; return y0 >= 250 ? y0 : chestY(z); } if (look.cap_pos === 'center') { return 1000; } return chestY(z); }
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
   add({ t0: a, t1: b, kind: 'pop', dur: 200, out: 'cut', x: W / 2, y: capY(a + 0.05), draw: function (c) { c.textBaseline = 'middle'; var tot = 0, gap = 0, ws = parts.map(function (p) { c.font = p.acc ? serif(Math.round(132 * cs)) : sans(Math.round(88 * cs)); var m = c.measureText(p.t).width; tot += m; return m; }); c.font = sans(Math.round(88 * cs)); gap = c.measureText(' ').width * 0.7; tot += gap * (parts.length - 1);
    scrim(c, tot + 110, 180, 0.28); var xx = -tot / 2; c.textAlign = 'left'; parts.forEach(function (p, j) { c.font = p.acc ? serif(Math.round(132 * cs)) : sans(Math.round(88 * cs)); c.save(); c.lineJoin = 'round'; c.lineWidth = p.acc ? 14 : 9; c.strokeStyle = 'rgba(0,0,0,0.62)'; void 0 && c.strokeText(p.t, xx, p.acc ? 5 : 0); c.restore(); shadowText(c, p.t, xx, p.acc ? 5 : 0, p.acc ? YEL : '#fff', 20, 3); xx += ws[j] + gap; }); } }); });
  if (plan.end) { END = 2.4; var e0 = T + 0.02, msg = plan.end.text;
   add({ t0: e0, t1: T + END + 1, kind: 'fade', dur: 150, out: 'cut', x: 0, y: 0, draw: function (c, t) { c.fillStyle = '#000'; c.fillRect(0, 0, W, H); var nch = clamp(Math.floor((t - e0 - 0.25) / 0.075), 0, msg.length); c.font = 'italic 400 120px "Instrument Serif", serif'; c.textAlign = 'center'; c.textBaseline = 'middle'; c.fillStyle = '#fff'; c.fillText(msg.slice(0, nch) + (nch < msg.length || Math.floor(t * 2) % 2 ? '|' : ''), W / 2, 920);
    if (plan.end.sub && t > e0 + 0.4 + 0.075 * msg.length) { c.save(); c.translate(W / 2, 1070); c.rotate(-2 * Math.PI / 180); c.font = hand(62); shadowText(c, plan.end.sub, 0, 0, YEL); c.restore(); } } });
   EV.push([e0 + 0.25, 'typing', 0.075 * msg.length + 0.1, 0.3 * sfxG]); }
 } else {
  var acc = look.accent, acc2 = look.accent2, ed = !!st.ed;
  var mainFont = function (sz) { return ed ? 'italic 400 ' + sz + 'px "Instrument Serif", serif' : (look.font_main === 'Montserrat' ? 'italic 800 ' : '800 ') + sz + 'px "' + look.font_main + '", sans-serif'; };
  var leadFont = function (sz) { return (look.font_lead === 'Poppins' ? 'italic 500 ' : '600 ') + sz + 'px "' + look.font_lead + '", sans-serif'; };
  var BUSY = [];
  function fillMain(c, txt, sz, w) { if (ed) { return look.main_color && look.main_color !== '#ffffff' ? look.main_color : acc; } if (look.main_color && !look.grad) { return look.main_color; } var g = look.gradH ? c.createLinearGradient(-w / 2, 0, w / 2, 0) : c.createLinearGradient(0, -sz * 0.5, 0, sz * 0.5), cols = look.grad; cols.forEach(function (col, i) { g.addColorStop(cols.length > 1 ? i / (cols.length - 1) : 0, col); }); return g; }
  function stack(lead, big, tl, tb, t1, ev) { var sz0 = ed ? 172 : 150; BUSY.push([tl - 0.05, t1]);
   add({ t0: tl, t1: t1, kind: 'fade', dur: 120, out: 'blur', outd: 200, x: W / 2, y: 1330, draw: function (c, t) { var sz = fitSize(c, big, mainFont, sz0, 820), lsz = clamp(Math.floor(sz * 0.32), 38, 62); c.font = mainFont(sz); var bw = c.measureText(big).width;
     scrim(c, Math.max(bw, lead.length * 0.52 * lsz) + 160, sz + lsz + 120, 0.32); c.textBaseline = 'middle';
     if (lead) { c.font = leadFont(lsz); c.textAlign = 'left'; shadowText(c, lead, -bw / 2 + 0.1 * sz, -sz * 0.55, '#fff', 12, 2); }
     if (t >= tb) { var u = (t - tb) / 0.2, s = 0.6 + 0.4 * easeOutBack(u); c.save(); c.translate(0, lsz * 0.3); c.scale(s, s); c.globalAlpha *= clamp(u * 2, 0, 1); c.font = mainFont(sz); c.textAlign = 'center'; c.shadowColor = 'rgba(0,0,0,0.45)'; c.shadowBlur = 24; c.shadowOffsetY = 6; c.fillStyle = fillMain(c, big, sz, bw); outline(c, big, 0, 0, sz); c.fillText(big, 0, 0); c.restore(); } } });
   if (lead) { EV.push([tl, 'swish', 0.22, 0.22 * sfxG]); } EV.push([tb, 'hit', 0.7, (ed ? 0.16 : 0.3) * sfxG]); }
  plan.blocks.forEach(function (b) { var tl = t_of(b.w[0]), tb = t_of(b.w[1]), t1 = te_of(b.w[2]); if (tl == null || tb == null || t1 == null) { return; } stack(b.lead, b.text, b.lead ? tl : tb, tb, t1 + 0.25); });
  plan.counts.forEach(function (cn) { var t0 = t_of(cn.w[0]), t1 = te_of(cn.w[1]); if (t0 == null || t1 == null) { return; } t1 += 0.2; var target = cn.to, num = parseFloat(target.replace(/[^\d.]/g, '')) || 0, pre = (target.match(/^[^\d]*/) || [''])[0], suf = (target.match(/[^\d.]*$/) || [''])[0], steps = 12; BUSY.push([t0 - 0.35, t1]);
   add({ t0: t0 - 0.3, t1: t1, kind: 'fade', dur: 120, out: 'blur', outd: 200, x: W / 2, y: 1330, draw: function (c, t) { var k2 = clamp(Math.floor((t - t0) / 0.055), 0, steps), v = k2 >= steps ? target : pre + Math.round(num * easeOut(k2 / steps)).toLocaleString() + suf, sz = fitSize(c, target, mainFont, 200, 820), lsz = clamp(Math.floor(sz * 0.32), 38, 62); c.textBaseline = 'middle';
     scrim(c, Math.max(sz * 0.66 * target.length, 400) + 160, sz + 140, 0.32); if (cn.lead) { c.font = leadFont(lsz); c.textAlign = 'center'; shadowText(c, cn.lead, 0, -sz * 0.62, '#fff', 12, 2); }
     if (t >= t0) { c.font = mainFont(sz); c.textAlign = 'center'; c.shadowColor = 'rgba(0,0,0,0.45)'; c.shadowBlur = 24; c.shadowOffsetY = 6; c.fillStyle = fillMain(c, v, sz, c.measureText(v).width); outline(c, v, 0, lsz * 0.3, sz); c.fillText(v, 0, lsz * 0.3); } } });
   EV.push([t0 - 0.4, 'riser', 0.4, 0.2 * sfxG], [t0 + steps * 0.055, 'chime', 0, 0.2 * sfxG]); });
  var seq2 = cut.words.filter(function (x) { return cleanL(x.w); }), chunks2 = [], cur2 = [];
  seq2.forEach(function (x) { if (cur2.length && (x.o - cur2[cur2.length - 1].oe > 0.25 || cur2.length >= 3 || cur2.concat([x]).map(function (y) { return cleanL(y.w); }).join(' ').length > 16)) { chunks2.push(cur2); cur2 = []; } cur2.push(x); if (/[.,?!]$/.test(x.w)) { chunks2.push(cur2); cur2 = []; } }); if (cur2.length) { chunks2.push(cur2); }
  chunks2.forEach(function (ch, n) { var a = ch[0].o - 0.03, b = Math.min(n + 1 < chunks2.length ? chunks2[n + 1][0].o - 0.03 : T, ch[ch.length - 1].oe + 0.35);
   if (BUSY.some(function (e) { return a < e[1] && b > e[0]; })) { return; }
   var parts = ch.map(function (x) { var t = cleanL(x.w).toLowerCase(); return { t: t, k: !!plan.key[t] }; });
   add({ t0: a, t1: b, kind: 'pop', dur: 260, out: 'blur', outd: 60, x: W / 2, y: capY(a + 0.05) + (look.cap_pos === 'chest' ? 40 : 0), draw: function (c) { var sz = Math.round(66 * cs), em = look.emph || 'color', HL = look.hl || acc2, cw = look.cap_w || 700;
     var f = function (k) { if (k && em === 'color') { return '900 ' + Math.round(sz * 1.18) + 'px "' + look.font_cap + '", sans-serif'; } if (k && em === 'script') { return 'italic 400 ' + Math.round(sz * 1.3) + 'px \"' + (look.font_emph || 'Instrument Serif') + '\", serif'; } return (k ? Math.max(cw, 800) : cw) + ' ' + sz + 'px \"' + look.font_cap + '\", sans-serif'; };
     c.textBaseline = 'middle'; var tot = 0, gap = 0, pad = em === 'box' ? 22 : 0, ws = parts.map(function (p) { c.font = f(p.k); var m = c.measureText(p.t).width + (p.k && em === 'box' ? pad * 2 : 0); tot += m; return m; }); c.font = f(false); gap = c.measureText(' ').width * 0.75; tot += gap * (parts.length - 1);
     if (look.cap_bg === 'pill') { c.save(); c.fillStyle = 'rgba(18,18,22,0.76)'; rrect(c, -tot / 2 - 28, -sz * 0.78, tot + 56, sz * 1.56, 22); c.fill(); c.restore(); } else { scrim(c, tot + 110, 135, 0.28); }
     var xx = -tot / 2; c.textAlign = 'left'; parts.forEach(function (p, j) { c.font = f(p.k); var col = p.k ? (em === 'script' ? '#fff' : (em === 'box' ? '#fff' : HL)) : '#fff';
      if (p.k && em === 'box') { c.save(); c.fillStyle = acc; rrect(c, xx, -sz * 0.62, ws[j], sz * 1.24, 8); c.fill(); c.restore(); shadowText(c, p.t, xx + pad, 0, col, 4, 1); }
      else { if (look.cap_bg !== 'pill') { c.save(); c.lineJoin = 'round'; c.lineWidth = Math.round(sz * (p.k ? 0.15 : 0.09)); c.strokeStyle = 'rgba(0,0,0,0.72)'; void 0 && c.strokeText(p.t, xx, p.k && em === 'script' ? 4 : 0); c.restore(); } shadowText(c, p.t, xx, p.k && em === 'script' ? 4 : 0, col, 18, 2); } xx += ws[j] + gap; }); } }); });
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

  /* ---- section headers + word cards (study of 12 reference reels) ---- */
  if (st.fam === 'b' && P.hdr && plan.headers && plan.headers.length) { var hs = plan.headers, hstyle = look.hdr || 'box';
   hs.forEach(function (h, k) { var a0 = t_of(h.w[0]); if (a0 == null) { return; } var nx = hs[k + 1] ? t_of(hs[k + 1].w[0]) : null, a1 = Math.min(nx != null ? nx - 0.1 : T, (te_of(h.w[1]) || a0 + 2) + 5.5); if (a1 - a0 < 1) { return; }
    add({ t0: a0 - 0.05, t1: a1, kind: 'pop', dur: 320, out: 'blur', outd: 140, x: W / 2, y: look.cap_pos === 'top' ? 170 : 250, draw: function (c) { c.textBaseline = 'middle'; c.textAlign = 'center'; var txt = h.text.toUpperCase();
     if (hstyle === 'num') { var lab = (h.lab ? cap1(h.lab) + ' ' : '') + '#' + h.n + '  ' + (h.lab ? cap1(h.text) : cap1(h.text)); c.font = '800 54px \"' + look.font_cap + '\", sans-serif'; var w2 = c.measureText(lab).width; scrim(c, w2 + 120, 130, 0.3); shadowText(c, lab, 0, 0, '#fff', 14, 3); }
     else if (hstyle === 'script' && h.lab) { var a = cap1(h.lab) + ' #' + h.n + ':', b2 = ' ' + h.text.toLowerCase(); c.font = '700 54px \"' + look.font_cap + '\", sans-serif'; var wa = c.measureText(a).width; c.font = 'italic 400 72px \"Instrument Serif\", serif'; var wb = c.measureText(b2).width, x0 = -(wa + wb) / 2; c.textAlign = 'left'; c.font = '700 54px \"' + look.font_cap + '\", sans-serif'; shadowText(c, a, x0, 0, '#fff', 14, 3); c.font = 'italic 400 72px \"Instrument Serif\", serif'; shadowText(c, b2, x0 + wa, 0, '#fff', 14, 3); }
     else { var tx = h.n + '. ' + txt; c.font = '800 50px \"' + look.font_cap + '\", sans-serif'; var wt = c.measureText(tx).width + 56, red = hstyle === 'red'; c.save(); c.shadowColor = 'rgba(0,0,0,0.35)'; c.shadowBlur = 26; c.shadowOffsetY = 8; c.fillStyle = red ? (look.accent || '#E10600') : '#fff'; rrect(c, -wt / 2, -42, wt, 84, red ? 8 : 4); c.fill(); c.restore(); c.fillStyle = red ? '#fff' : '#0b0b0f'; c.fillText(tx, 0, 3); } } });
    EV.push([a0, 'pop', 0, 0.3 * sfxG]); }); }
  if (st.fam === 'b' && P.wcards && plan.wcards && plan.wcards.length) { plan.wcards.forEach(function (wc) { var sp = span(wc.w, 1.1, 1.7); if (!claim(sp)) { return; } var dark = look.ed || st.ed, sz0 = Math.max(110, Math.min(220, Math.floor(1500 / Math.max(wc.text.length, 4))));
    add({ t0: sp[0], t1: sp[1], kind: 'fade', dur: 260, out: 'cut', x: 0, y: 0, draw: function (c, t) { var g = c.createRadialGradient(W * 0.3, H * 0.25, 60, W * 0.5, H * 0.5, 1300); if (dark) { g.addColorStop(0, '#1c1c20'); g.addColorStop(1, '#09090b'); } else { g.addColorStop(0, '#ffffff'); g.addColorStop(0.55, '#ececec'); g.addColorStop(1, '#d6d6d6'); } c.fillStyle = g; c.fillRect(0, 0, W, H);
     var u = clamp((t - sp[0]) / 0.3, 0, 1), ink = dark ? '#fff' : '#0b0b0f'; c.textBaseline = 'middle'; c.textAlign = 'center'; var sz = fitSize(c, wc.text, function (s) { return '900 ' + s + 'px \"' + look.font_main + '\", sans-serif'; }, sz0, 940);
     c.save(); c.translate(W / 2, 930); c.scale(0.8 + 0.2 * easeOutBack(u), 0.8 + 0.2 * easeOutBack(u)); c.globalAlpha = clamp(u * 2, 0, 1); if (wc.lead) { c.font = '500 44px \"' + look.font_cap + '\", sans-serif'; c.textAlign = 'left'; c.fillStyle = dark ? 'rgba(255,255,255,0.7)' : 'rgba(0,0,0,0.6)'; c.font = '800 ' + sz + 'px \"' + look.font_main + '\", sans-serif'; var ww = c.measureText(wc.text).width; c.font = '500 44px \"' + look.font_cap + '\", sans-serif'; c.fillText(wc.lead, -ww / 2 + 6, -sz * 0.62); }
     c.textAlign = 'center'; c.font = '900 ' + sz + 'px \"' + look.font_main + '\", sans-serif'; c.fillStyle = dark ? (look.main_color || '#fff') : ink; c.shadowColor = 'rgba(0,0,0,' + (dark ? 0.5 : 0.18) + ')'; c.shadowBlur = 30; c.shadowOffsetY = 10; c.fillText(wc.text, 0, 0); c.restore(); } });
    EV.push([sp[0] - 0.05, 'swish', 0.3, 0.3 * sfxG]); }); }
  plan.punch.forEach(function (i) { if (WO[i]) { EV.push([WO[i].o - 0.12, 'camera', 0.3, 0.35 * sfxG]); } });
 /* the member's own photos: shown at the strongest moments (punch-ins), a tilted photo card above the head */
 if (photos && photos.length) { var slots = (P.photoItems && pr.items.length ? pr.items.map(function (it) { return it.w[0]; }) : plan.punch).filter(function (i) { return WO[i]; }).map(function (i) { return WO[i].o; }), used = 0, lastP = -9;
  slots.forEach(function (t) { if (used >= photos.length || t - lastP < 3 || !free(t - 0.05, t + 1.9)) { return; } var img = photos[used++], rot = (used % 2 ? -4 : 4); lastP = t;
   add({ t0: t - 0.05, t1: Math.min(t + 1.9, T), kind: 'pop', dur: 260, out: 'blur', outd: 200, x: W / 2, y: 560, draw: function (c) { var bw = 620, bh = 620 * Math.min(1.25, img.height / img.width); c.rotate(rot * Math.PI / 180); c.shadowColor = 'rgba(0,0,0,0.45)'; c.shadowBlur = 40; c.fillStyle = '#fff'; c.fillRect(-bw / 2 - 18, -bh / 2 - 18, bw + 36, bh + 80); c.shadowColor = 'transparent'; var s = Math.max(bw / img.width, bh / img.height), dw = img.width * s, dh = img.height * s; c.save(); c.beginPath(); c.rect(-bw / 2, -bh / 2, bw, bh); c.clip(); c.drawImage(img, -dw / 2, -bh / 2 - (dh - bh) / 2, dw, dh); c.restore(); } });
   EV.push([t - 0.05, 'swish', 0.22, 0.25 * sfxG]); }); }
 return { items: L, events: EV, END: END, cards: CARDS.length, cardSpans: CARDS.slice() }; }


/* ================= Reference recipes: one per reference reel (study of 12 reels) =================
   Each recipe decides layout (full / split screen), how captions look and move, how the key word is shown, how a list is
   shown (headers, checklist, panel, boxes, HUD), full-screen word cards, camera behaviour, music and real sound effects. */
var REF = {
 'ref-chloe':    { cap: { pos: 'top', size: 54, w: 700, n: 3, emph: 'color', anim: 'up' }, hook: 'title', hdr: 'num', list: null, cards: 'paper', layout: null, cam: 'cuts', music: 'lofi_chill', sfx: { hdr: 'pop1', card: 'paper1', hook: 'swoosh1', key: null } },
 'ref-kallaway': { cap: { pos: 'center', size: 66, w: 800, n: 1, emph: 'none', anim: 'cut' }, hook: 'title', hdr: null, list: 'panel', cards: 'black', layout: 'kallaway', cam: 'cuts', music: 'synth_dark', sfx: { hdr: 'click1', card: 'whoosh2', hook: 'riser2', split: 'swoosh2' } },
 'ref-social':   { cap: { pos: 'chest', size: 60, w: 700, n: 3, emph: 'none', anim: 'pop' }, hook: null, hdr: 'box', list: null, cards: null, layout: null, cam: 'locked', music: 'lofi_beat', sfx: { hdr: 'pop2' } },
 'ref-wolf':     { cap: { pos: 'chest', size: 56, w: 700, n: 3, emph: 'none', anim: 'pop', upper: true }, stack: 'wolf', hdr: 'num', list: null, cards: 'black', layout: null, cam: 'push', music: 'warm_groove', sfx: { hdr: 'click1', card: 'drama2', stack: 'boom' } },
 'ref-tumi':     { cap: { pos: 'chest', size: 52, w: 600, n: 3, emph: 'none', anim: 'up' }, stack: 'tumi', stackAll: true, hdr: null, list: 'checklist', cards: 'paper', layout: 'tumi', cam: 'cuts', music: 'synth_calm', sfx: { row: 'click2', card: 'paper2', split: 'paper1' } },
 'ref-millie':   { cap: { pos: 'chest', size: 58, w: 700, n: 3, emph: 'script', anim: 'up' }, hook: null, hdr: 'script', list: null, cards: null, layout: null, cam: 'locked', music: 'lofi_soft', sfx: { hdr: 'pop3' } },
 'ref-deshna':   { cap: { pos: 'chest', size: 58, w: 800, n: 3, emph: 'box', anim: 'pop' }, hook: null, hdr: null, list: null, cards: 'paper', layout: null, cam: 'cuts', music: 'lofi_chill', sfx: { card: 'paper3', key: null } },
 'ref-zach':     { cap: { pos: 'seam', size: 44, w: 700, n: 3, emph: 'none', anim: 'cut', pill: true }, hook: 'panel', hdr: null, list: 'panel-light', cards: null, layout: 'zach', cam: 'locked', music: 'lofi_beat', sfx: { row: 'pop1', split: 'swoosh3' } },
 'ref-sky':      { cap: { pos: 'chest', size: 56, w: 500, n: 3, emph: 'color', anim: 'up' }, hook: 'title', hdr: null, list: 'hud', cards: null, layout: null, cam: 'locked', music: 'synth_dark', sfx: { row: 'toggle1', hook: 'riser1' } },
 'ref-adam':     { cap: { pos: 'chest', size: 58, w: 800, n: 3, emph: 'color', anim: 'pop' }, hook: null, hdr: null, list: 'boxes', cards: null, layout: null, cam: 'cuts', music: 'synth_calm', sfx: { row: 'click1' } },
 'ref-glass':   { cap: { pos: 'chest', size: 58, w: 800, n: 3, emph: 'color', anim: 'pop' }, hook: 'title', hdr: null, list: 'glass', cards: 'glass', layout: null, cam: 'push', music: 'synth_calm', sfx: { row: 'pop1', card: 'pop2', hook: 'swoosh1' } },
 'ref-cinematic':{ cap: { pos: 'chest', size: 66, w: 600, n: 3, emph: 'serif', anim: 'up' }, stack: 'serif', hdr: null, list: null, cards: 'black', layout: null, cam: 'push', film: true, music: 'synth_dark', sfx: { card: 'whoosh4', stack: 'whoosh5' } },
 'edita-interview': { cap: { pos: 'chest', size: 64, w: 600, n: 3, emph: 'serif', anim: 'up' }, stack: 'editorial', hdr: null, list: null, cards: 'black', layout: null, cam: 'push', film: true, music: 'lofi_soft', sfx: { stack: 'swoosh3', card: 'paper2' } },
 'edita-podcast': { cap: { pos: 'chest', size: 64, w: 600, n: 3, emph: 'serif', anim: 'up' }, stack: 'editorial', hdr: null, list: null, cards: 'paper', layout: null, cam: 'push', music: 'warm_groove', sfx: { stack: 'swoosh3', card: 'paper1' } },
 'edita-blueprint': { cap: { pos: 'chest', size: 56, w: 800, n: 3, emph: 'box', anim: 'pop' }, stack: 'block', hdr: null, list: 'cards', theme: 'blueprint', cards: 'black', layout: null, cam: 'push', music: 'synth_calm', sfx: { stack: 'boom', card: 'drama1', row: 'whoosh2' } },
 'gallo-promo': { cap: { pos: 'chest', size: 58, w: 800, n: 3, emph: 'color', anim: 'pop' }, stack: 'block', hdr: null, list: 'cards', theme: 'lavender', cards: 'paper', layout: null, cam: 'push', music: 'lofi_beat', sfx: { stack: 'punch', card: 'whoosh1', row: 'whoosh3' } }
};
function isRef(st) { return !!REF[st.id]; }
var NZC = null;
function noiseTile() { if (NZC) { return NZC; } var cv = document.createElement('canvas'); cv.width = 192; cv.height = 192; var x = cv.getContext('2d'), d = x.createImageData(192, 192), i, v; for (i = 0; i < d.data.length; i += 4) { v = 100 + Math.floor(Math.random() * 156); d.data[i] = d.data[i + 1] = d.data[i + 2] = v; d.data[i + 3] = 255; } x.putImageData(d, 0, 0); NZC = cv; return cv; }

/* display text for a word: AI fixes first, then local guards (times are never turned into big numbers) */
function dispWords(words, ai) { var out = words.map(function (x) { return { w: x.w, s: x.s, e: x.e }; }), fx = (ai && ai.fixes) || [];
 fx.forEach(function (f) { var i = +f.i; if (out[i] && f.text && String(f.text).length <= 24) { out[i].w = String(f.text) + (/[.,?!]$/.test(out[i].w) && !/[.,?!]$/.test(f.text) ? out[i].w.slice(-1) : ''); } });
 for (var i = 0; i + 1 < out.length; i++) { var a = out[i].w, b = out[i + 1].w.toLowerCase().replace(/[^a-z.]/g, '');
  if (/^\d{1,2}([:.]\d{2})?$/.test(a.replace(/[.,]$/, '')) && /^(am|pm|a\.m\.?|p\.m\.?)$/.test(b)) { out[i].w = a.replace(/[.,]$/, '') + ' ' + (b.charAt(0) === 'a' ? 'AM' : 'PM') + (/[.,?!]$/.test(out[i + 1].w) ? out[i + 1].w.slice(-1) : ''); out[i + 1].w = ''; } }
 return out; }
/* the AI plan (site AI reads the transcript); null when unavailable, the editor then plans by itself */
function aiPlan(words, sid) { return aiPlan1(words, sid).then(function (a) { return a || aiPlan1(words, sid); }); }
function aiPlan1(words, sid) { if (!C.aj || !C.n) { return Promise.resolve(null); }
 var fd = new FormData(); fd.append('action', 'ir_rx_plan'); fd.append('n', C.n); fd.append('style', sid); fd.append('words', JSON.stringify(words.map(function (x) { return x.w; })));
 return Promise.race([fetch(C.aj, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { return j && j.ok && j.plan ? j.plan : null; }).catch(function () { return null; }), new Promise(function (r) { setTimeout(function () { r(null); }, 40000); })]); }
/* normalise AI plan or heuristic plan into one shape */
function refPlan(words, plan, ai) { var n = words.length, ok = function (i) { return typeof i === 'number' && i >= 0 && i < n; }, P = { keys: {}, stacks: [], items: [], cards: [], hook: null, title: '', cta: null };
 if (ai) {
  (ai.keywords || []).forEach(function (i) { if (ok(+i)) { P.keys[+i] = 1; } });
  (ai.stacks || []).forEach(function (s) { if (ok(+s.from) && ok(+s.to) && s.big) { P.stacks.push({ a: +s.from, b: +s.to, lead: String(s.lead || ''), big: String(s.big), tail: String(s.tail || '') }); } });
  ((ai.list && ai.list.items) || []).forEach(function (it, k) { if (ok(+it.from) && ok(+it.to) && it.label) { P.items.push({ a: +it.from, b: Math.max(+it.to, +it.from), n: +it.n || k + 1, label: String(it.label) }); } });
  P.title = ai.list && ai.list.title ? String(ai.list.title) : (ai.topic ? String(ai.topic) : '');
  (ai.cards || []).forEach(function (c) { if (ok(+c.from) && ok(+c.to) && c.text) { P.cards.push({ a: +c.from, b: +c.to, lead: String(c.lead || ''), text: String(c.text) }); } });
  if (ai.hook && ok(+ai.hook.from) && ai.hook.text) { P.hook = { a: +ai.hook.from, b: ok(+ai.hook.to) ? +ai.hook.to : +ai.hook.from, text: String(ai.hook.text) }; }
  var ck = ai.cta && ok(+ai.cta.from) ? ctaKw(words, +ai.cta.from, ai.cta.keyword) : ''; if (ck) { P.cta = { a: +ai.cta.from, keyword: ck }; }
 } else {
  Object.keys(plan.key || {}).forEach(function () {}); words.forEach(function (x, i) { if (plan.key && plan.key[cleanW(x.w).toLowerCase()]) { P.keys[i] = 1; } });
  (plan.punch || []).forEach(function (i) { P.keys[i] = 1; });
  (plan.blocks || []).forEach(function (b) { P.stacks.push({ a: b.w[0], b: b.w[2], lead: b.lead, big: b.text, tail: '' }); });
  (plan.headers || []).forEach(function (h) { P.items.push({ a: h.w[0], b: h.w[1], n: h.n, label: cap1(h.text) }); });
  (plan.wcards || []).forEach(function (c) { P.cards.push({ a: c.w[0], b: c.w[1], lead: c.lead, text: c.text }); });
  if (plan.cta) { P.cta = { a: plan.cta.w ? plan.cta.w[0] : 0, keyword: plan.cta.keyword }; }
  var s0 = sentences(words)[0] || []; if (s0.length >= 3) { P.hook = { a: s0[0], b: s0[Math.min(s0.length - 1, 7)], text: s0.slice(0, 7).map(function (i) { return cleanW(words[i].w); }).join(' ') }; } }
 P.used = {}; P.imgs = {}; P.viral = ai ? viralPlan(ai, ok) : null; P.pics = []; if (ai && ai.pictures) { ai.pictures.forEach(function (p) { if (p && ok(+p.from) && p.query) { P.pics.push({ a: +p.from, b: ok(+p.to) ? Math.max(+p.to, +p.from) : +p.from, query: String(p.query).slice(0, 60) }); } }); }
 P.noun = nounFor(words, ai); P.items.sort(function (x, y) { return x.a - y.a; }); for (var k = 0; k < P.items.length; k++) { if (P.items[k + 1] && P.items[k].b >= P.items[k + 1].a) { P.items[k].b = P.items[k + 1].a - 1; } }
 return P; }
function ctaKw(words, from, kw) { var st = Math.max(0, from - 8), win = words.slice(st, from + 10).map(function (x) { return cleanW(x.w).toLowerCase().replace(/[^a-z0-9]/g, ''); }), ci = win.indexOf('comment'); if (ci < 0) { return ''; } var k = String(kw || '').toLowerCase().replace(/[^a-z0-9]/g, ''), after = win.slice(ci + 1);
 if (k && k !== 'comment' && after.indexOf(k) >= 0) { return k.toUpperCase().slice(0, 20); } var bad = { below: 1, the: 1, a: 1, on: 1, and: 1, this: 1, to: 1, me: 1, now: 1, word: 1, '': 1 }, nx = after[0] === 'the' && after[1] === 'word' ? after[2] : after[0]; return nx && !bad[nx] && nx.length >= 2 ? nx.toUpperCase().slice(0, 20) : ''; }
function cap1(x) { x = String(x || ''); return x.charAt(0).toUpperCase() + x.slice(1); }
function nounFor(words, ai) { var n = ai && ai.list && ai.list.noun ? String(ai.list.noun).toLowerCase().replace(/[^a-z]/g, '').replace(/s$/, '') : ''; if (n && n.length >= 3 && n.length <= 12) { return n; }
 var cnt = {}, keys = { sign: /^signs?$/, step: /^steps?$/, tip: /^tips?$/, mistake: /^mistakes?$/, reason: /^reasons?$/, rule: /^rules?$/, habit: /^habits?$/, myth: /^myths?$/, hack: /^hacks?$/, lesson: /^lessons?$/, way: /^ways?$/, question: /^questions?$/, symptom: /^symptoms?$/, secret: /^secrets?$/ }; words.forEach(function (x) { var w = cleanW(x.w).toLowerCase(); for (var k in keys) { if (keys[k].test(w)) { cnt[k] = (cnt[k] || 0) + 1; } } });
 var best = 'point', bc = 1; for (var q in cnt) { if (cnt[q] > bc) { bc = cnt[q]; best = q; } } return best; }
function rrectP(c, x, y, w, h, r) { c.beginPath(); c.moveTo(x + r, y); c.arcTo(x + w, y, x + w, y + h, r); c.arcTo(x + w, y + h, x, y + h, r); c.arcTo(x, y + h, x, y, r); c.arcTo(x, y, x + w, y, r); c.closePath(); }
function softText(c, txt, x, y, fill, blur) { c.save(); c.shadowColor = 'rgba(0,0,0,0.32)'; c.shadowBlur = blur == null ? 18 : Math.max(14, blur + 6); c.shadowOffsetY = 2; c.fillStyle = fill; c.fillText(txt, x, y); c.restore(); }
/* ================= glass effect + viral formats (ranking, worth it / not worth it, listicle, yes / no) + online pictures ================= */
var GLS = null, GLS2 = null;
/* frosted-glass panel: the video behind it is blurred, tinted, edge-lit and given a soft sweep of light */
function glassPanel(c, x, y, w, h, r, o) { o = o || {}; var m = c.getTransform(), cv = c.canvas, X = m.e + x * m.a, Y = m.f + y * m.d, sx = Math.max(0, Math.floor(X)), sy = Math.max(0, Math.floor(Y)), ex = Math.min(cv.width, Math.ceil(X + w * m.a)), ey = Math.min(cv.height, Math.ceil(Y + h * m.d)), sw = ex - sx, sh = ey - sy;
 c.save(); c.shadowColor = 'rgba(0,0,0,' + (o.sh == null ? 0.3 : o.sh) + ')'; c.shadowBlur = 34; c.shadowOffsetY = 12; c.fillStyle = '#000'; rrectP(c, x, y, w, h, r); c.fill(); c.restore();
 if (sw >= 8 && sh >= 8) { var a = GLS || (GLS = document.createElement('canvas')), b = GLS2 || (GLS2 = document.createElement('canvas')); a.width = Math.max(2, Math.round(sw / 7)); a.height = Math.max(2, Math.round(sh / 7)); var ga = a.getContext('2d'); ga.imageSmoothingQuality = 'high'; ga.drawImage(cv, sx, sy, sw, sh, 0, 0, a.width, a.height);
  b.width = Math.max(2, Math.round(a.width / 3)); b.height = Math.max(2, Math.round(a.height / 3)); var gb = b.getContext('2d'); gb.imageSmoothingQuality = 'high'; gb.drawImage(a, 0, 0, b.width, b.height);
  c.save(); rrectP(c, x, y, w, h, r); c.clip(); c.imageSmoothingQuality = 'high'; c.drawImage(b, x, y, w, h); c.restore(); }
 c.save(); rrectP(c, x, y, w, h, r); c.clip(); c.fillStyle = o.tint || (o.dark ? 'rgba(16,20,32,0.34)' : 'rgba(255,255,255,0.22)'); c.fillRect(x, y, w, h);
 var lg = c.createLinearGradient(x, y, x + w * 0.55, y + h); lg.addColorStop(0, 'rgba(255,255,255,0.38)'); lg.addColorStop(0.45, 'rgba(255,255,255,0.05)'); lg.addColorStop(1, 'rgba(255,255,255,0)'); c.fillStyle = lg; c.fillRect(x, y, w, h);
 if (o.t != null) { var p = ((o.t * 0.7) % 2.4) - 0.5, bx = x + p * w; c.save(); c.translate(bx, y); c.transform(1, 0, -0.35, 1, 0, 0); var sg = c.createLinearGradient(0, 0, w * 0.22, 0); sg.addColorStop(0, 'rgba(255,255,255,0)'); sg.addColorStop(0.5, 'rgba(255,255,255,0.20)'); sg.addColorStop(1, 'rgba(255,255,255,0)'); c.fillStyle = sg; c.fillRect(0, 0, w * 0.22, h); c.restore(); }
 c.restore(); c.save(); c.strokeStyle = 'rgba(255,255,255,0.62)'; c.lineWidth = 2.2; rrectP(c, x + 1.1, y + 1.1, w - 2.2, h - 2.2, Math.max(2, r - 1)); c.stroke(); c.strokeStyle = 'rgba(255,255,255,0.14)'; c.lineWidth = 1; rrectP(c, x + 4, y + 4, w - 8, h - 8, Math.max(2, r - 4)); c.stroke(); c.restore(); }
function coverImg(c, img, x, y, w, h, z) { var s = Math.max(w / img.width, h / img.height) * (z || 1), dw = img.width * s, dh = img.height * s; c.drawImage(img, x + (w - dw) / 2, y + (h - dh) / 2, dw, dh); }
function parseScore(s) { var m = String(s || '').match(/(\d+(?:\.\d+)?)\s*\/\s*(\d+)/); return m ? { n: parseFloat(m[1]), d: parseInt(m[2], 10) } : null; }
function viralPlan(ai, ok) { var v = ai && ai.viral; if (!v) { return null; } var fm = String(v.format || 'plain').toLowerCase(); if (['ranking', 'worth', 'listicle', 'yesno'].indexOf(fm) < 0) { fm = 'plain'; }
 var en = (v.entries || []).filter(function (e) { return e && ok(+e.from) && (e.label || e.question); }).map(function (e) { var a = +e.from, b = ok(+e.to) ? Math.max(+e.to, a) : a; return { a: a, b: b, label: String(e.label || '').slice(0, 44), result: String(e.result || '').slice(0, 44), query: String(e.query || '').slice(0, 60), score: String(e.score || '').slice(0, 10), side: String(e.side || '').toLowerCase() === 'left' ? 'left' : 'right', question: String(e.question || '').slice(0, 110) }; }).sort(function (x, y) { return x.a - y.a; });
 var sd = v.sides || {}; return { format: fm, title: String(v.title || '').slice(0, 64), left: String(sd.left || 'NOT WORTH IT').slice(0, 20).toUpperCase(), right: String(sd.right || 'WORTH IT').slice(0, 20).toUpperCase(), entries: en }; }
function picQueries(ai, fmt) { var q = []; if (ai && ai.viral && ai.viral.format !== 'plain' && fmt !== 'off') { (ai.viral.entries || []).forEach(function (e) { if (e && e.query) { q.push(String(e.query).slice(0, 60)); } }); } if (fmt !== 'off' && ai && ai.pictures) { ai.pictures.slice(0, 5).forEach(function (p) { if (p && p.query) { q.push(String(p.query).slice(0, 60)); } }); } return q; }
function fetchPics(qs) { qs = qs.filter(function (q, i) { return q && qs.indexOf(q) === i; }).slice(0, 10); if (!qs.length || !C.aj || !C.n) { return Promise.resolve({}); }
 var out = {};
 function one(q) { var fd = new FormData(); fd.append('action', 'ir_rx_img'); fd.append('n', C.n); fd.append('q', JSON.stringify([q]));
  return Promise.race([fetch(C.aj, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }), new Promise(function (r) { setTimeout(function () { r(null); }, 50000); })]).then(function (j) { if (!j || !j.ok || !j.imgs || !j.imgs[q]) { return; } var d = j.imgs[q]; return new Promise(function (res) { var im = new Image(); im.onload = function () { out[q] = { img: im, credit: d.credit, license: d.license }; res(); }; im.onerror = function () { res(); }; im.src = d.url; }); }).catch(function () {}); }
 return Promise.all(qs.map(one)).then(function () { return out; }); }
function fitLines(c, txt, fn, sz, min, maxW, nl) { var ln; for (;;) { c.font = fn(sz); ln = wrapP(c, txt, maxW); if ((ln.length <= nl && ln.every(function (l) { return c.measureText(l).width <= maxW; })) || sz <= min) { break; } sz = Math.floor(sz * 0.93); } return { sz: sz, lines: ln.slice(0, nl) }; }
function withImgs(P, imgs, fmt) { P.imgs = imgs || {}; if (fmt === 'off') { P.viral = null; P.pics = []; } return P; }

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
 /* ---- free zone for cards: below the chin (never over the face); captions move above the head while a card is up ---- */
 var ZN, VCAPY = null; (function () { if (SPLIT) { ZN = [110, SPLIT.seam - 50]; } else { var fa = faceY(1.0), fb = faceY(1.2), chinZ = Math.max(fa.chin, fb.chin), headZ = Math.min(fa.head, fb.head), zy0 = clamp(chinZ + 70, 980, 1230), above = headZ - 100 >= 270; VCAPY = above ? headZ - 100 : 1790; ZN = [zy0, above ? 1800 : 1700]; } })();
 function zy(h) { return ZN[0] + clamp((ZN[1] - ZN[0] - h) / 2, 0, SPLIT ? 999 : 50); }
 function vwrap(c, t, ta, tb, y, h, fn) { var k = clamp((t - ta) / 0.5, 0, 1), u = easeOutBack(k), a = clamp((t - ta) / 0.22, 0, 1) * clamp((tb - t) / 0.22, 0, 1), sc = 0.92 + 0.08 * u; c.save(); c.globalAlpha *= a; c.translate(W / 2, y + h / 2); c.scale(sc, sc); c.translate(-W / 2, -(y + h / 2) + 46 * (1 - easeOut(k))); fn(); c.restore(); }
 function vimg(q) { var o = P.imgs && q && P.imgs[q]; if (o) { P.used[q] = 1; } return o ? o.img : null; }
 function cover(c, img, x, y, w, h, r, z) { c.save(); rrectP(c, x, y, w, h, r); c.clip(); coverImg(c, img, x, y, w, h, z); c.restore(); }
 function drawList(c, t, rows, y, hmax) { var x = 60, w = 960, rh = 96, K = Math.max(1, Math.min(5, Math.floor((hmax - 40) / rh))), cnt = 0; rows.forEach(function (r) { cnt += clamp((t - r.ta) / 0.35, 0, 1); }); var shown = Math.min(cnt, K), h = 40 + shown * rh, off = Math.max(0, cnt - K) * rh, last = -1; rows.forEach(function (r, i) { if (t >= r.ta) { last = i; } });
  glassPanel(c, x, y, w, h, 40, { dark: true, t: t }); c.save(); rrectP(c, x, y, w, h, 40); c.clip(); c.textBaseline = 'middle';
  rows.forEach(function (r, i) { var u = clamp((t - r.ta) / 0.35, 0, 1); if (u <= 0) { return; } var ry = y + 20 + i * rh - off, cur = i === last, e = easeOut(u); c.save(); c.globalAlpha *= e * (cur ? 1 : 0.85); c.translate(30 * (1 - e), 0);
   c.fillStyle = cur ? HL : 'rgba(255,255,255,0.26)'; c.beginPath(); c.arc(x + 62, ry + rh / 2, 30, 0, Math.PI * 2); c.fill(); c.textAlign = 'center'; c.font = F(900, 32); c.fillStyle = cur ? '#101010' : '#fff'; c.fillText(String(r.n), x + 62, ry + rh / 2 + 2);
   var fn = function (s) { return '700 ' + s + 'px "' + FONT + '", sans-serif'; }, tw = w - 150, fl = fitLines(c, r.label, fn, 40, 26, r.result ? tw * 0.6 : tw, 1); c.font = fn(fl.sz); c.textAlign = 'left'; var lw = c.measureText(fl.lines[0] || '').width; softText(c, fl.lines[0] || '', x + 112, ry + rh / 2 + 2, '#fff', 6);
   if (r.result) { c.font = F(800, fl.sz * 0.9); c.fillStyle = HL; var rx = x + 112 + lw + 22; c.fillText('→', rx, ry + rh / 2 + 2); var rem = x + w - 40 - (rx + 52), rs = fitSize(c, r.result, function (s) { return '700 ' + s + 'px "' + FONT + '", sans-serif'; }, Math.round(fl.sz * 0.92), Math.max(120, rem)); c.font = '700 ' + rs + 'px "' + FONT + '", sans-serif'; softText(c, r.result, rx + 52, ry + rh / 2 + 2, HL, 6); }
   c.restore(); }); c.restore(); }

 /* ---- viral formats: ranking / worth it / listicle / yes-no ---- */
 var V = P.viral, VON = false, VA = 1e9, VB = 0, ve = [];
 if (V && V.format === 'listicle' && ((R.list && R.list !== 'glass') || R.hdr)) { V = null; } /* styles that have their own numbered-list design keep it */
 if (V && V.format !== 'plain') { ve = V.entries.filter(function (e) { return t0(e.a) != null; }); var span = V.format === 'worth' || V.format === 'listicle';
  ve.forEach(function (e, k) { var nx = ve[k + 1]; e.ta = Math.max(0, t0(e.a) - 0.05); e.tb = Math.min(T, Math.max(e.ta + 1.6, (t1(e.b) || e.ta + 1.6) + 0.8)); if (!span && nx) { e.tb = Math.min(e.tb, t0(nx.a) - 0.1); } e.tsc = (e.b > e.a && t0(e.b) != null) ? Math.max(e.ta + 0.25, t0(e.b) - 0.1) : e.ta + 0.7; e.tsc = Math.min(e.tsc, Math.max(e.ta + 0.2, e.tb - 0.45)); });
  if (V.format === 'yesno') { ve.forEach(function (e, k) { var nx = ve[k + 1], hit = -1, i0 = Math.max(e.a + 2, e.b - 2); for (var q = i0; q <= e.b + 8 && hit < 0; q++) { if (WO[q] && /^(yes|yeah|yep|yup|no|nope|nah|absolutely|definitely|never)$/i.test(cleanW(WO[q].w))) { hit = q; } }
    if (hit >= 0) { e.pos = /^(yes|yeah|yep|yup|absolutely|definitely)$/i.test(cleanW(WO[hit].w)); e.tsc = Math.max(e.ta + 0.3, WO[hit].o - 0.05); e.tb = Math.min(T, Math.max(e.tb, WO[hit].oe + 1.3)); } else { e.tsc = Math.max(e.ta + 0.3, (t1(e.b) || e.ta + 1) + 0.05); e.tb = Math.min(T, Math.max(e.tb, e.tsc + 1.2)); }
    if (nx) { e.tb = Math.min(e.tb, t0(nx.a) - 0.1); } if (e.tsc > e.tb - 0.35) { e.tsc = Math.max(e.ta + 0.2, e.tb - 0.35); } }); }
  if (!span) { ve = ve.filter(function (e) { return e.tb - e.ta >= 0.8; }); } if (ve.length >= (span ? 2 : 1)) { VON = true; VA = ve[0].ta; VB = Math.min(T, ve[ve.length - 1].tb + (span ? 0.9 : 0)); } }
 /* ---- list: headers / checklist / panel / boxes / HUD ---- */
 var items = P.items.filter(function (it) { return t0(it.a) != null; }), lend = items.length ? (t1(items[items.length - 1].b) || T) + 0.3 : 0;
 items.forEach(function (it, k) { var a = t0(it.a) - 0.05, b = Math.min(k + 1 < items.length ? t0(items[k + 1].a) - 0.05 : T, (t1(it.b) || a + 2) + 0.35); if (b - a < 0.8) { b = a + 0.8; } it.ta = a; it.tb = b; });
 if (!VON && (items.length >= 2 || (items.length === 1 && R.hdr))) {
  if (R.hdr) { items.forEach(function (it) { var label = it.label, n = it.n, sty = R.hdr;
    add({ t0: it.ta, t1: it.tb, kind: 'pop', dur: 280, out: 'blur', outd: 120, x: W / 2, y: sty === 'red' ? 160 : (R.cap.pos === 'top' ? 170 : 240), draw: function (c) { c.textBaseline = 'middle'; c.textAlign = 'center';
     if (sty === 'num') { var tx = cap1(label); c.font = F(800, 52); var tw = c.measureText(tx).width, nw = 70, x0 = -(tw + nw + 14) / 2; c.save(); c.strokeStyle = HL; c.lineWidth = 4; c.beginPath(); c.ellipse(x0 + nw / 2, 0, nw / 2, 34, -0.15, 0, Math.PI * 2); c.stroke(); c.restore(); c.font = F(800, 40); softText(c, '#' + n, x0 + nw / 2, 2, HL, 8); c.font = F(800, 52); c.textAlign = 'left'; softText(c, tx, x0 + nw + 14, 0, '#fff', 14); }
     else if (sty === 'box') { var t2 = n + '. ' + label.toUpperCase(); c.font = F(800, 48); var w2 = c.measureText(t2).width + 44; c.fillStyle = '#fff'; c.fillRect(-w2 / 2, -40, w2, 80); c.fillStyle = '#0b0b0f'; c.fillText(t2, 0, 3); }
     else if (sty === 'red') { var t3 = label; c.font = F(800, 44); var w3 = c.measureText(t3).width + 50; c.save(); c.shadowColor = 'rgba(0,0,0,0.4)'; c.shadowBlur = 20; c.fillStyle = ACC; rrectP(c, -w3 / 2, -38, w3, 76, 6); c.fill(); c.restore(); c.fillStyle = '#fff'; c.fillText(t3, 0, 2); }
     else { var lab = (P.noun && P.noun !== 'point' && P.noun !== 'step' ? cap1(P.noun) + ' ' : '') + '#' + n + ':'; c.font = F(700, 50); var wa = c.measureText(lab + ' ').width; c.font = 'italic 400 70px "Instrument Serif", serif'; var wb = c.measureText(label.toLowerCase()).width, x1 = -(wa + wb) / 2; c.textAlign = 'left'; c.font = F(700, 50); softText(c, lab, x1, 0, '#fff'); c.font = 'italic 400 70px "Instrument Serif", serif'; softText(c, label.toLowerCase(), x1 + wa, 2, '#fff'); } } });
    ev(it.ta, R.sfx.hdr, 0.45); }); }
  if (R.list === 'panel' || R.list === 'panel-light') { var dark = R.list === 'panel';
   items.forEach(function (it) { if (dark) { split(it.ta, it.tb); } var lab = it.label, n = it.n;
    add({ t0: it.ta, t1: it.tb, kind: 'fade', dur: 140, out: 'cut', x: W / 2, y: dark ? 560 : 470, draw: function (c, t) { var u = clamp((t - it.ta) / 0.35, 0, 1); c.textAlign = 'center'; c.textBaseline = 'middle';
     c.font = '900 120px "' + FONT + '", sans-serif'; var lines = wrapP(c, dark ? lab.toUpperCase() : cap1(lab), 940).slice(0, 3), lh = 118, sz = lines.length > 2 ? 96 : 120;
     c.font = (dark ? '900 ' : '800 ') + sz + 'px "' + FONT + '", sans-serif'; c.globalAlpha *= easeOut(u);
     if (dark) { c.font = '900 ' + sz + 'px "' + FONT + '", sans-serif'; }
     else { c.font = '800 ' + sz + 'px "' + FONT + '", sans-serif'; }
     lines.forEach(function (l, k) { c.fillStyle = dark ? '#fff' : '#111'; c.fillText(l, 0, (k - (lines.length - 1) / 2) * lh + 30 * (1 - easeOut(u))); }); } });
    ev(it.ta - 0.1, dark ? R.sfx.split : R.sfx.row, 0.45); }); }
  if (R.list === 'checklist') { var A0 = items[0].ta, A1 = lend; split(A0, A1);
   add({ t0: A0, t1: A1, kind: 'fade', dur: 1, out: 'cut', x: W / 2, y: 0, draw: function (c, t) { c.textBaseline = 'middle'; c.textAlign = 'center';
     items.forEach(function (it, k) { if (t < it.ta) { return; } var u = clamp((t - it.ta) / 0.3, 0, 1), y = 130 + k * 104, cur = t < it.tb; c.save(); c.globalAlpha *= easeOut(u); c.translate(0, 20 * (1 - easeOut(u)));
      c.font = F(600, 38); var lab2 = cap1(it.label).slice(0, 34), w4 = c.measureText(lab2).width + 130; c.shadowColor = 'rgba(0,0,0,0.12)'; c.shadowBlur = 14; c.fillStyle = cur ? '#ffffff' : '#f4f4f5'; rrectP(c, -w4 / 2, y - 38, w4, 76, 16); c.fill(); c.shadowColor = 'transparent';
      c.fillStyle = cur ? '#D8A73A' : '#9a9aa2'; c.beginPath(); c.arc(-w4 / 2 + 46, y, 17, 0, Math.PI * 2); c.fill(); c.strokeStyle = '#fff'; c.lineWidth = 4; c.beginPath(); c.moveTo(-w4 / 2 + 38, y); c.lineTo(-w4 / 2 + 44, y + 7); c.lineTo(-w4 / 2 + 55, y - 7); c.stroke();
      c.textAlign = 'left'; c.fillStyle = cur ? '#141414' : '#55555c'; c.fillText(lab2, -w4 / 2 + 82, y + 2); c.textAlign = 'center'; c.restore(); }); } });
   ev(A0 - 0.1, R.sfx.split, 0.5); items.forEach(function (it) { ev(it.ta, R.sfx.row, 0.5); }); }
  if (R.list === 'cards') { var th = R.theme, light = th === 'lavender', nI = items.length;
   items.forEach(function (it, k) { var a = it.ta, b = Math.min(it.tb, a + 3.0); if (b - a < 1.2) { return; } BUSY.push([a, b, 'icard']); var lab = it.label, n = it.n;
    add({ t0: a, t1: b, kind: 'fade', dur: 1, out: 'cut', x: 0, y: 0, draw: function (c, t) { var u = clamp((t - a) / 0.4, 0, 1), e = easeOut(u); c.fillStyle = light ? '#ECE9FB' : '#0F0C24'; c.fillRect(0, 0, W, H);
      c.fillStyle = light ? 'rgba(255,255,255,0.7)' : 'rgba(255,255,255,0.05)'; c.fillRect(0, 0, W, 8); var ink = light ? '#17132e' : '#ffffff', ac = light ? '#FF5A1F' : (look.accent || '#2F6BFF');
      c.save(); c.globalAlpha *= e; c.translate(0, 40 * (1 - e)); c.textAlign = 'left'; c.textBaseline = 'middle'; c.fillStyle = ac; c.font = '900 380px "' + (look.font_main || 'Montserrat') + '", sans-serif'; c.fillText(String(n).padStart(2, '0'), 90, 560);
      
      c.font = '900 ' + 96 + 'px "' + (look.font_main || 'Montserrat') + '", sans-serif'; var lines = wrapP(c, lab, 880).slice(0, 4); c.fillStyle = ink; lines.forEach(function (l, q) { c.fillText(l, 90, 860 + q * 112); });
      c.fillStyle = ac; c.fillRect(90, 800, 130, 10); c.restore();
       } });
    ev(a - 0.08, R.sfx.card, 0.55); ev(a, R.sfx.row, 0.3); }); }
  if (R.list === 'boxes') { var B0 = items[0].ta;
   add({ t0: B0, t1: lend, kind: 'fade', dur: 220, out: 'blur', outd: 200, x: W / 2, y: 120, draw: function (c, t) { c.textAlign = 'center'; c.textBaseline = 'middle'; items.slice(0, 7).forEach(function (it, k) { var y = k * 74, cur = t >= it.ta && t < it.tb, done = t >= it.tb, lab3 = it.label.toUpperCase().slice(0, 26); c.font = F(800, 36); var w5 = 560;
      c.fillStyle = cur ? 'rgba(20,20,22,0.85)' : 'rgba(20,20,22,0.55)'; rrectP(c, -w5 / 2, y - 30, w5, 60, 6); c.fill(); c.strokeStyle = cur ? '#F5C518' : 'rgba(255,255,255,0.35)'; c.lineWidth = cur ? 3 : 2; rrectP(c, -w5 / 2, y - 30, w5, 60, 6); c.stroke();
      c.fillStyle = cur ? '#ffffff' : (done ? 'rgba(255,255,255,0.75)' : 'rgba(255,255,255,0.45)'); c.fillText(lab3, 0, y + 2); }); } });
   items.forEach(function (it) { ev(it.ta, R.sfx.row, 0.45); }); }

  if (R.list === 'glass') { var gA = items[0].ta, gB = Math.min(T, lend + 0.4), gr = items.map(function (it) { return { n: it.n, label: cap1(it.label), ta: it.ta, result: '' }; }); BUSY.push([gA, gB, 'vcard']);
   add({ t0: gA, t1: gB, kind: 'fade', dur: 1, out: 'cut', x: 0, y: 0, draw: function (c, t) { var hm = ZN[1] - ZN[0]; vwrap(c, t, gA, gB, zy(Math.min(hm, 520)), Math.min(hm, 520), function () { drawList(c, t, gr, zy(Math.min(hm, 520)), hm); }); } });
   items.forEach(function (it) { ev(it.ta, R.sfx.row, 0.4); }); }
  if (R.list === 'hud') { var H0 = items[0].ta;
   add({ t0: H0, t1: lend, kind: 'rise', dur: 300, out: 'blur', outd: 200, x: W / 2, y: 470, draw: function (c, t) { var nI = Math.min(items.length, 6), bw = 860, seg = bw / nI; c.save(); c.fillStyle = 'rgba(20,40,22,0.55)'; rrectP(c, -bw / 2 - 16, -26, bw + 32, 52, 26); c.fill(); c.restore();
     items.slice(0, nI).forEach(function (it, k) { var x = -bw / 2 + k * seg, on = t >= it.ta, cur = on && t < it.tb, f = cur ? clamp((t - it.ta) / Math.max(0.5, it.tb - it.ta), 0, 1) : (t >= it.tb ? 1 : 0); c.fillStyle = 'rgba(124,255,79,0.25)'; rrectP(c, x + 6, -12, seg - 12, 24, 12); c.fill(); if (f > 0) { c.fillStyle = '#7CFF4F'; rrectP(c, x + 6, -12, Math.max(24, (seg - 12) * f), 24, 12); c.fill(); }
      if (cur) { c.font = F(700, 34); c.textAlign = 'center'; c.textBaseline = 'middle'; var lb = cap1(it.label).slice(0, 26), lw = c.measureText(lb).width + 40, cx = clamp(x + seg / 2, -bw / 2 + lw / 2, bw / 2 - lw / 2); c.fillStyle = 'rgba(15,30,16,0.85)'; rrectP(c, cx - lw / 2, -90, lw, 54, 14); c.fill(); c.strokeStyle = '#7CFF4F'; c.lineWidth = 2; rrectP(c, cx - lw / 2, -90, lw, 54, 14); c.stroke(); c.fillStyle = '#eaffdf'; c.fillText(lb, cx, -62); } }); } });
   items.forEach(function (it) { ev(it.ta, R.sfx.row, 0.4); }); } }

 if (VON) { var fnW = function (s) { return '800 ' + s + 'px "' + FONT + '", sans-serif'; }, hm = ZN[1] - ZN[0];
  if (SPLIT && R.layout !== 'zach') { split(VA, VB); } BUSY.push([VA, VB, 'vcard']);
  if (V.format === 'ranking') { var rh = Math.min(400, hm); ve.forEach(function (e) { add({ t0: e.ta, t1: e.tb, kind: 'fade', dur: 1, out: 'cut', x: 0, y: 0, draw: function (c, t) { var y = zy(rh); vwrap(c, t, e.ta, e.tb, y, rh, function () { drawRank(c, t, e, y, rh); }); } }); ev(e.ta - 0.04, 'pop2', 0.45); if (parseScore(e.score)) { ev(e.tsc, 'toggle1', 0.4); } }); }
  else if (V.format === 'yesno') { var yh = Math.min(380, hm); ve.forEach(function (e) { add({ t0: e.ta, t1: e.tb, kind: 'fade', dur: 1, out: 'cut', x: 0, y: 0, draw: function (c, t) { var y = zy(yh); vwrap(c, t, e.ta, e.tb, y, yh, function () { drawYN(c, t, e, y, yh); }); } }); ev(e.ta - 0.04, 'pop2', 0.45); ev(e.tsc, 'toggle1', 0.45); }); }
  else if (V.format === 'worth') { var wh = Math.min(600, hm); add({ t0: VA, t1: VB, kind: 'fade', dur: 1, out: 'cut', x: 0, y: 0, draw: function (c, t) { var y = zy(wh); vwrap(c, t, VA, VB, y, wh, function () { drawWorth(c, t, y, wh); }); } }); ve.forEach(function (e) { ev(e.ta, 'pop1', 0.4); }); }
  else { var lr = ve.map(function (e, i) { return { n: i + 1, label: cap1(e.label), result: e.result, ta: e.ta }; }); add({ t0: VA, t1: VB, kind: 'fade', dur: 1, out: 'cut', x: 0, y: 0, draw: function (c, t) { var h2 = Math.min(hm, 40 + 5 * 96); vwrap(c, t, VA, VB, zy(h2), h2, function () { drawList(c, t, lr, zy(h2), hm); }); } }); ve.forEach(function (e) { ev(e.ta, 'pop1', 0.4); }); } }
 function drawRank(c, t, e, y, h) { var x = 60, w = 960, pad = 30, img = vimg(e.query), th = h - pad * 2, sc = parseScore(e.score), frac = sc ? clamp(sc.n / sc.d, 0, 1) : 0, tx = x + pad;
  glassPanel(c, x, y, w, h, 46, { dark: true, t: t });
  if (img) { cover(c, img, x + pad, y + pad, th, th, 30, 1 + 0.06 * clamp((t - e.ta) / 3, 0, 1)); c.save(); c.strokeStyle = 'rgba(255,255,255,0.55)'; c.lineWidth = 2; rrectP(c, x + pad, y + pad, th, th, 30); c.stroke(); c.restore(); tx = x + pad + th + 34; }
  var ringR = 82, rcx = x + w - pad - ringR - 14, rcy = y + h / 2, right = sc ? rcx - ringR - 36 : x + w - pad, tw = right - tx, fn = function (s) { return '800 ' + s + 'px "' + FONT + '", sans-serif'; }, fl = fitLines(c, cap1(e.label), fn, 76, 38, tw, 2), lh = fl.sz * 1.08, sub = e.result ? 1 : 0, y0 = y + h / 2 - ((fl.lines.length - 1) * lh + sub * 52) / 2;
  c.textBaseline = 'middle'; c.textAlign = 'left'; c.font = fn(fl.sz); fl.lines.forEach(function (l, q) { softText(c, l, tx, y0 + q * lh, '#fff', 10); });
  if (sub) { var ss = fitSize(c, e.result, function (s) { return '600 ' + s + 'px "' + FONT + '", sans-serif'; }, 34, tw); c.font = '600 ' + ss + 'px "' + FONT + '", sans-serif'; c.fillStyle = 'rgba(255,255,255,0.78)'; c.fillText(e.result, tx, y0 + (fl.lines.length - 1) * lh + lh * 0.62 + 18); }
  if (sc) { var p = easeOut(clamp((t - e.tsc) / 0.8, 0, 1)), col = frac >= 0.7 ? '#34D399' : (frac >= 0.4 ? '#FBBF24' : '#F87171'), shown = sc.n * p; c.save(); c.lineWidth = 16; c.lineCap = 'round'; c.strokeStyle = 'rgba(255,255,255,0.2)'; c.beginPath(); c.arc(rcx, rcy, ringR, 0, Math.PI * 2); c.stroke(); if (p > 0.002 && frac > 0) { c.strokeStyle = col; c.beginPath(); c.arc(rcx, rcy, ringR, -Math.PI / 2, -Math.PI / 2 + Math.PI * 2 * frac * p); c.stroke(); } c.restore();
   c.textAlign = 'center'; c.font = F(900, 80); softText(c, Math.abs(sc.n % 1) < 1e-9 ? String(Math.round(shown)) : shown.toFixed(1), rcx, rcy - 8, '#fff', 8); c.font = F(700, 30); c.fillStyle = 'rgba(255,255,255,0.78)'; c.fillText('/' + sc.d, rcx, rcy + 48); } }
 function drawYN(c, t, e, y, h) { var x = 60, w = 960, pad = 40, ans = String(e.result || e.label || '').toUpperCase(), pos = e.pos != null ? e.pos : !/^(NO|NOT|DON|DONT|AVOID|NEVER|BAD|FALSE|SKIP|STOP)/.test(ans) && /^(YES|WORTH|DO|GOOD|SAFE|TRUE|KEEP|USE|OK|YEAH)/.test(ans) || /^YES/.test(ans), q = e.question || e.label;
  glassPanel(c, x, y, w, h, 46, { dark: true, t: t }); var fn = function (s) { return '800 ' + s + 'px "' + FONT + '", sans-serif'; }, fl = fitLines(c, q, fn, 62, 36, w - pad * 2, 3), lh = fl.sz * 1.12;
  c.textBaseline = 'middle'; c.textAlign = 'left'; c.font = fn(fl.sz); fl.lines.forEach(function (l, i) { softText(c, l, x + pad, y + pad + 20 + lh / 2 + i * lh, '#fff', 8); });
  var py = y + h - pad - 84, pw = 300, k = easeOutBack(clamp((t - e.tsc) / 0.4, 0, 1)), shown = t >= e.tsc;
  [{ txt: 'NO', col: '#F87171', ch: shown && !pos, x: x + pad }, { txt: 'YES', col: '#34D399', ch: shown && pos, x: x + w - pad - pw }].forEach(function (b) { c.save(); var cx = b.x + pw / 2, cy = py + 42, sc = b.ch ? 0.9 + 0.1 * k + 0.06 * Math.sin(clamp((t - e.tsc) / 0.4, 0, 1) * Math.PI) : 1; c.translate(cx, cy); c.scale(sc, sc); c.translate(-cx, -cy);
   c.fillStyle = b.ch ? b.col : 'rgba(255,255,255,0.16)'; rrectP(c, b.x, py, pw, 84, 42); c.fill(); if (!b.ch) { c.strokeStyle = 'rgba(255,255,255,0.4)'; c.lineWidth = 2; rrectP(c, b.x, py, pw, 84, 42); c.stroke(); } c.textAlign = 'center'; c.font = F(900, 40); c.fillStyle = b.ch ? '#fff' : (shown ? 'rgba(255,255,255,0.4)' : 'rgba(255,255,255,0.85)'); c.fillText(b.txt, cx, cy + 2); c.restore(); }); }
 function drawWorth(c, t, y, hFull) { var cw = 465, xs = [60, 555], heads = [V.left, V.right], cols = ['#F87171', '#34D399'], Kf = Math.max(1, Math.floor((hFull - 110) / 88)), cnt = [0, 0]; ve.forEach(function (e) { cnt[e.side === 'left' ? 0 : 1] += clamp((t - e.ta) / 0.35, 0, 1); }); var h = 112 + Math.max(2, Math.min(Kf, Math.max(cnt[0], cnt[1]))) * 88;
  [0, 1].forEach(function (s) { var x = xs[s]; glassPanel(c, x, y, cw, h, 38, { dark: true, t: t + s * 0.8 }); c.save(); c.globalAlpha *= 0.92; c.fillStyle = cols[s]; rrectP(c, x + 16, y + 16, cw - 32, 66, 24); c.fill(); c.restore();
   var hs = fitSize(c, heads[s], function (q) { return '800 ' + q + 'px "' + FONT + '", sans-serif'; }, 34, cw - 80); c.font = '800 ' + hs + 'px "' + FONT + '", sans-serif'; c.textAlign = 'center'; c.textBaseline = 'middle'; c.fillStyle = '#fff'; c.fillText(heads[s], x + cw / 2, y + 50);
   var rows = ve.filter(function (e) { return (e.side === 'left' ? 0 : 1) === s && t >= e.ta; }), K = Kf, vis = rows.slice(Math.max(0, rows.length - K));
   vis.forEach(function (e, i) { var u = easeOutBack(clamp((t - e.ta) / 0.4, 0, 1)), ry = y + 100 + i * 88, cur = e === rows[rows.length - 1], img = vimg(e.query); c.save(); c.globalAlpha *= clamp((t - e.ta) / 0.2, 0, 1); c.translate(x + cw / 2, ry + 36); c.scale(0.9 + 0.1 * u, 0.9 + 0.1 * u); c.translate(-(x + cw / 2), -(ry + 36));
    c.fillStyle = cur ? 'rgba(255,255,255,0.3)' : 'rgba(255,255,255,0.15)'; rrectP(c, x + 16, ry, cw - 32, 72, 22); c.fill(); if (cur) { c.strokeStyle = cols[s]; c.lineWidth = 3; rrectP(c, x + 16, ry, cw - 32, 72, 22); c.stroke(); }
    var tx = x + 36, tw = cw - 72; if (img) { cover(c, img, x + 26, ry + 8, 56, 56, 14, 1); tx = x + 98; tw = cw - 126; }
    var fn = function (q) { return '700 ' + q + 'px "' + FONT + '", sans-serif'; }, fl = fitLines(c, cap1(e.label), fn, 34, 22, tw, 2); c.font = fn(fl.sz); c.textAlign = 'left'; c.textBaseline = 'middle'; fl.lines.forEach(function (l, q) { softText(c, l, tx, ry + 37 + (q - (fl.lines.length - 1) / 2) * fl.sz * 1.1, '#fff', 6); }); c.restore(); }); }); }
 /* ---- hook title ---- */
 var hcut = VON ? VA - 0.08 : (items.length ? items[0].ta - 0.08 : 1e9); if (R.hook && P.hook && t0(P.hook.a) != null && t0(P.hook.a) < hcut - 1.2) { var ha = Math.max(0, t0(P.hook.a) - 0.05), hb = Math.min(hcut, Math.max(ha + 1.6, Math.min((t1(P.hook.b) || ha + 2) + 0.4, ha + 3.2))), ht = P.hook.text;
  if (R.hook === 'title') { BUSY.push([ha, hb, 'hook']);
   add({ t0: ha, t1: hb, kind: 'rise', dur: 260, out: 'blur', outd: 180, x: W / 2, y: clamp(faceY(1.0).head - 170, 200, 320), draw: function (c) { c.textAlign = 'center'; c.textBaseline = 'middle'; var ws = ht.split(' '), mid = Math.floor(ws.length / 2), l1 = ws.slice(0, mid).join(' '), l2 = ws.slice(mid).join(' ');
    var big = function (s) { return '900 ' + s + 'px "' + FONT + '", sans-serif'; }, s2 = fitSize(c, l2.toUpperCase(), big, 104, 940); c.font = F(700, 52); softText(c, l1, 0, -s2 * 0.86, '#fff'); c.font = big(s2); var col = st.id === 'ref-sky' ? '#7CFF4F' : (st.id === 'ref-kallaway' ? '#fff' : HL); softText(c, l2.toUpperCase(), 0, 8, col, 20);
    if (st.id === 'ref-kallaway') { c.font = big(s2); var w2 = c.measureText(l2.toUpperCase()).width; c.fillStyle = ACC; c.fillRect(-w2 / 2, s2 * 0.5, w2, 8); } } });
   ev(ha - 0.15, R.sfx.hook, 0.35); }
  else if (R.hook === 'panel') { L.push({ t0: ha, t1: hb, kind: 'fade', dur: 200, out: 'cut', x: W / 2, y: 470, draw: function (c) { c.textAlign = 'center'; c.textBaseline = 'middle'; c.font = '800 76px "Instrument Serif", serif'; var lines = wrapP(c, cap1(ht), 900).slice(0, 3); lines.forEach(function (l, k) { c.fillStyle = '#121212'; c.font = 'italic 400 92px "Instrument Serif", serif'; c.fillText(l, 0, (k - (lines.length - 1) / 2) * 100); }); } }); } }

 /* ---- pictures found online for things the speaker names (B-roll) ---- */
 var lastPic = -99; P.pics.forEach(function (p) { var a = t0(p.a); if (a == null || !P.imgs[p.query] || a - lastPic < 6) { return; } a = Math.max(0, a - 0.05); var b = Math.min(T, Math.min(a + 3.2, Math.max(a + 1.6, (t1(p.b) || a) + 0.8)));
  if (b - a < 1.2 || busy(a - 0.1, b, 'split') || busy(a - 0.1, b, 'vcard') || busy(a - 0.1, b, 'icard') || busy(a - 0.1, b, 'hook') || busy(a - 0.1, b, 'pic') || items.some(function (it) { return a < it.tb && b > it.ta; })) { return; }
  var img = vimg(p.query); lastPic = a; BUSY.push([a, b, 'pic']);
  add({ t0: a, t1: b, kind: 'fade', dur: 1, out: 'cut', x: 0, y: 0, draw: function (c, t) { var hz = Math.min(ZN[1] - ZN[0], 560) - 28, pw = clamp(img.width / img.height * hz, 440, 900 - 28), ph = hz, ar = img.width / img.height; if (pw / ph > ar * 1.0 && ar * ph > 440) { pw = Math.min(900 - 28, ar * ph); } var x = (W - (pw + 28)) / 2, y = zy(ph + 28);
    vwrap(c, t, a, b, y, ph + 28, function () { glassPanel(c, x, y, pw + 28, ph + 28, 40, { dark: true, t: t }); cover(c, img, x + 14, y + 14, pw, ph, 28, 1 + 0.07 * clamp((t - a) / 3, 0, 1)); }); } });
  ev(a - 0.05, 'swoosh1', 0.4); });
 /* ---- full-screen word cards ---- */
 var lastCard = -99; if (R.cards) { P.cards.forEach(function (cd) { var a = t0(cd.a); if (a == null || a - lastCard < 10 || busy(a - 0.1, a + 1.6, 'split') || busy(a - 0.1, a + 1.6, 'vcard') || busy(a - 0.1, a + 1.6, 'pic')) { return; } if (items.some(function (it) { return a >= it.ta && a < it.tb; })) { return; }
   var b = Math.min(T, Math.max(a + 1.1, Math.min((t1(cd.b) || a) + 0.45, a + 1.9))); lastCard = a; BUSY.push([a, b, 'card']); var dark = R.cards === 'black', txt = cd.text, lead = cd.lead;
   if (R.cards === 'glass') { add({ t0: a, t1: b, kind: 'fade', dur: 1, out: 'cut', x: 0, y: 0, draw: function (c, t) { var gh = Math.min(300, ZN[1] - ZN[0]), gy = zy(gh); vwrap(c, t, a, b, gy, gh, function () { glassPanel(c, 60, gy, 960, gh, 50, { dark: true, t: t }); var fnG = function (s) { return '900 ' + s + 'px "' + FONT + '", sans-serif'; }, sz = fitSize(c, txt, fnG, 150, 860); c.textAlign = 'center'; c.textBaseline = 'middle'; if (lead) { c.font = F(600, 38); c.fillStyle = 'rgba(255,255,255,0.78)'; c.fillText(lead, W / 2, gy + 58); } c.font = fnG(sz); softText(c, txt, W / 2, gy + gh / 2 + (lead ? 26 : 0), '#fff', 14); }); } }); ev(a - 0.08, R.sfx.card, 0.55); return; }
   add({ t0: a, t1: b, kind: 'fade', dur: 1, out: 'cut', x: 0, y: 0, draw: function (c, t) { var g = c.createRadialGradient(W * 0.32, H * 0.28, 60, W * 0.5, H * 0.5, 1300); if (dark) { g.addColorStop(0, '#17171a'); g.addColorStop(1, '#050506'); } else { g.addColorStop(0, '#ffffff'); g.addColorStop(0.55, '#ececec'); g.addColorStop(1, '#d4d4d6'); } c.fillStyle = g; c.fillRect(0, 0, W, H);
     var u = clamp((t - a) / 0.32, 0, 1), sz = fitSize(c, txt, function (s) { return '900 ' + s + 'px "' + (look.font_main || FONT) + '", sans-serif'; }, Math.max(110, Math.min(210, Math.floor(1500 / Math.max(txt.length, 4)))), 940);
     c.save(); c.translate(W / 2, 950); var s = 0.9 + 0.1 * easeOut(u); c.scale(s, s); c.globalAlpha *= easeOut(u); c.textBaseline = 'middle'; c.font = '900 ' + sz + 'px "' + (look.font_main || FONT) + '", sans-serif'; var ww = c.measureText(txt).width;
     if (lead) { c.font = F(500, 42); c.textAlign = 'left'; c.fillStyle = dark ? 'rgba(255,255,255,0.7)' : 'rgba(0,0,0,0.55)'; c.fillText(lead, -ww / 2 + 6, -sz * 0.62); }
     c.textAlign = 'center'; c.font = '900 ' + sz + 'px "' + (look.font_main || FONT) + '", sans-serif'; c.fillStyle = dark ? (st.id === 'ref-wolf' ? '#FFD400' : '#ffffff') : '#0b0b0f'; c.shadowColor = 'rgba(0,0,0,' + (dark ? 0.6 : 0.16) + ')'; c.shadowBlur = 30; c.shadowOffsetY = 10; c.fillText(txt, 0, 0); c.restore(); } });
   ev(a - 0.08, R.sfx.card, 0.55); }); }
 /* ---- CTA: comment keyword pill with typing ---- */
 if (P.cta && P.cta.keyword && t0(P.cta.a) != null && !busy(t0(P.cta.a), t0(P.cta.a) + 2.5, 'vcard') && !busy(t0(P.cta.a), t0(P.cta.a) + 2.5, 'pic')) { var ca = t0(P.cta.a), kw = P.cta.keyword; add({ t0: ca, t1: T, kind: 'rise', dur: 260, out: 'cut', x: W / 2, y: 1560, draw: function (c, t) { var nch = clamp(Math.floor((t - ca - 0.3) / 0.09), 0, kw.length), s = 'Comment ' + kw.slice(0, nch) + (nch < kw.length ? '|' : ''); c.font = F(800, 52); c.textAlign = 'center'; c.textBaseline = 'middle'; var w = c.measureText('Comment ' + kw).width + 110; c.save(); c.shadowColor = 'rgba(0,0,0,0.35)'; c.shadowBlur = 24; c.fillStyle = '#fff'; rrectP(c, -w / 2, -52, w, 104, 52); c.fill(); c.restore(); c.fillStyle = '#111'; c.fillText(s, 0, 3); } });
  EV.push([ca + 0.3, 'typing', 0, 0.35 * sg]); BUSY.push([ca, T, 'stack']); }
 /* ---- stacks: lead small / big key words / tail (wolf, tumi, cinematic) ---- */
 function stack(a, b, lead, big, tail, kind) { BUSY.push([a, b, 'stack']); var u0 = a, bigT = (kind === 'wolf') ? big.toUpperCase() : big;
  add({ t0: a, t1: b, kind: 'fade', dur: 1, out: 'blur', outd: 160, x: W / 2, y: 1330, draw: function (c, t) { var u = clamp((t - u0) / 0.22, 0, 1), ub = clamp((t - u0 - 0.08) / 0.24, 0, 1); c.textBaseline = 'middle';
    var bf = kind === 'wolf' ? function (s) { return '900 ' + s + 'px "Montserrat", sans-serif'; } : kind === 'block' ? function (s) { return 'italic 900 ' + s + 'px "Montserrat", sans-serif'; } : (kind === 'serif' || kind === 'editorial') ? function (s) { return 'italic 400 ' + s + 'px "Instrument Serif", serif'; } : function (s) { return '800 ' + s + 'px "Bricolage Grotesque", sans-serif'; };
    var sz = fitSize(c, bigT, bf, kind === 'wolf' ? 128 : (kind === 'block' ? 118 : (kind === 'editorial' ? 190 : 160)), 880); c.font = bf(sz); var bw = c.measureText(bigT).width;
    if (lead) { c.save(); c.globalAlpha *= easeOut(u); c.font = F(kind === 'wolf' ? 700 : 500, kind === 'wolf' ? 46 : 42); c.textAlign = kind === 'wolf' ? 'center' : 'left'; softText(c, kind === 'wolf' ? lead.toUpperCase() : lead, kind === 'wolf' ? -40 * (1 - easeOut(u)) : -bw / 2 + 8 - 40 * (1 - easeOut(u)), -sz * 0.62, '#fff', 12); c.restore(); }
    c.save(); c.globalAlpha *= easeOut(ub); var sc = 1.12 - 0.12 * easeOut(ub); c.scale(sc, sc); c.font = bf(sz); c.textAlign = 'center'; var fill;
    if (kind === 'tumi') { fill = c.createLinearGradient(0, -sz * 0.5, 0, sz * 0.45); fill.addColorStop(0, '#F8DC86'); fill.addColorStop(0.55, '#D9A63C'); fill.addColorStop(1, '#A8761F'); } else if (kind === 'serif') { fill = '#E9C46A'; } else if (kind === 'editorial') { fill = look.accent || '#d6f35a'; } else if (kind === 'block') { fill = '#fff'; } else { fill = '#FFD400'; }
    if (kind === 'block') { c.fillStyle = look.accent || '#2F6BFF'; c.shadowColor = 'rgba(0,0,0,0.4)'; c.shadowBlur = 30; c.shadowOffsetY = 10; rrectP(c, -bw / 2 - 34, -sz * 0.66, bw + 68, sz * 1.3, 16); c.fill(); c.shadowColor = 'transparent'; c.fillStyle = '#fff'; c.fillText(bigT, 0, 6); } else { c.lineJoin = 'round'; c.lineWidth = Math.round(sz * 0.06); c.strokeStyle = 'rgba(0,0,0,0.45)'; void 0 && c.strokeText(bigT, 0, 6); c.shadowColor = 'rgba(0,0,0,0.3)'; c.shadowBlur = 24; c.shadowOffsetY = 3; c.fillStyle = fill; c.fillText(bigT, 0, 6); } c.restore();
    if (tail) { c.save(); c.globalAlpha *= easeOut(clamp((t - u0 - 0.2) / 0.2, 0, 1)); c.font = F(500, 40); c.textAlign = 'right'; softText(c, tail, bw / 2 - 6 + 30 * (1 - easeOut(u)), sz * 0.6, '#fff', 12); c.restore(); } } });
  ev(a, R.sfx.stack, 0.35); }
 if (R.stack && !R.stackAll) { var lastS = -99; P.stacks.forEach(function (s) { var a = t0(s.a), b = t1(s.b); if (a == null || b == null || a - lastS < 5 || busy(a - 0.05, b + 0.3)) { return; } lastS = a; stack(a - 0.05, Math.max(a + 0.9, b + 0.3), s.lead, s.big, s.tail, R.stack); }); }
 /* ---- captions (and Tumi's stack-everything captions) ---- */
 var cl = function (s) { return String(s).replace(/[.,;:!?]+$/, ''); }, NUMW = /^(one|two|three|four|five|six|seven|eight|nine|ten|first|second|third|fourth|fifth|\d+)$/i, NOUNW = /^(number|no|point|tip|step|mistake|sign|reason|rule|habit|myth|hack|lesson|way|secret)$/i, HIDE = {}, seq = cut.words.filter(function (x) { return cl(x.w) && !HIDE[x.i]; }), chunks = [], cur = [], n = R.cap.n; if (R.hdr || R.list || VON) { P.items.forEach(function (it) { for (var q = it.a; q <= it.a + 1; q++) { var w1 = WO[q], w2 = WO[q + 1]; if (w1 && w2 && NOUNW.test(cl(w1.w)) && NUMW.test(cl(w2.w))) { HIDE[q] = 1; HIDE[q + 1] = 1; break; } } }); } seq = seq.filter(function (x) { return !HIDE[x.i]; });
 seq.forEach(function (x) { var txt = cur.concat([x]).map(function (y) { return cl(y.w); }).join(' '); if (cur.length && (x.o - cur[cur.length - 1].oe > 0.25 || cur.length >= n || txt.length > (n === 1 ? 99 : 18))) { chunks.push(cur); cur = []; } cur.push(x); if (/[.,?!]$/.test(x.w)) { chunks.push(cur); cur = []; } }); if (cur.length) { chunks.push(cur); }
 if (R.stackAll) { var ph = [], cp = []; seq.forEach(function (x) { cp.push(x); if (/[.,?!]$/.test(x.w) || cp.length >= 6) { ph.push(cp); cp = []; } }); if (cp.length) { ph.push(cp); }
  var used = {}; ph.forEach(function (p) { var ki = -1; p.forEach(function (x, k) { if (P.keys[x.i] && ki < 0) { ki = k; } }); if (ki < 0) { var bs = -1; p.forEach(function (x, k) { var sc = cl(x.w).length; if (!STOP[cl(x.w).toLowerCase()] && sc > bs) { bs = sc; ki = k; } }); }
   if (ki < 0) { return; } var a = p[0].o - 0.04, b = p[p.length - 1].oe + 0.2; if (busy(a, b, 'card') || busy(a, b, 'split') || busy(a, b, 'stack')) { return; } p.forEach(function (x) { used[x.i] = 1; });
   stack(a, b, p.slice(Math.max(0, ki - 4), ki).map(function (x) { return cl(x.w); }).join(' '), cl(p[ki].w), p.slice(ki + 1, ki + 4).map(function (x) { return cl(x.w); }).join(' '), 'tumi'); }); }
 var capY = function (tt) { if (busy(tt, tt + 0.01, 'split')) { return R.layout === 'tumi' ? 1520 : SPLIT.seam; } var s = shots[0]; shots.forEach(function (q) { if (q[0] <= tt) { s = q; } }); var z = s[2] === 'T' ? 1.16 : 1.0, fy = faceY(z);
  if (R.cap.pos === 'top') { var y0 = fy.head - 100; return y0 >= 250 ? y0 : chestY(z); } if (R.cap.pos === 'center' || R.cap.pos === 'seam') { return fy.top < 1010 && fy.chin + 40 > 1010 ? chestY(z) : 1010; } return chestY(z); };
 chunks.forEach(function (ch, k) { var a = ch[0].o - 0.03, b = Math.min(k + 1 < chunks.length ? chunks[k + 1][0].o - 0.03 : T, ch[ch.length - 1].oe + 0.3);
  if (busy(a, b, 'card') || busy(a + 0.02, b - 0.02, 'stack') || busy(a, b, 'hook')) { return; }
  var onC = busy(a, b, 'icard'), parts = ch.map(function (x) { var w = cl(x.w); return { t: R.cap.upper ? w.toUpperCase() : w, k: !!P.keys[x.i], o: x.o }; }), y = onC ? 1640 : ((!SPLIT && VCAPY != null && (busy(a, b, 'vcard') || busy(a, b, 'pic'))) ? VCAPY : capY(a + 0.05)), em = R.cap.emph, sz = Math.round(R.cap.size * (look.cap_size ? look.cap_size / 70 : 1)), wt = R.cap.w;
  var noAnim = R.cap.anim === 'cut', clum = 0.4, clk = -1;
  add({ t0: a, t1: b, kind: 'fade', dur: 1, out: 'cut', x: W / 2, y: y, draw: function (c, t) { c.textBaseline = 'middle'; try { c.letterSpacing = '-0.4px'; } catch (e) {}
    var KS = em === 'color' ? 1.14 : (em === 'none' ? 1 : 1.1), f = function (p) { if (p.k && em === 'script') { return 'italic 400 ' + Math.round(sz * 1.35) + 'px "' + (st.id === 'ref-millie' ? 'Caveat' : 'Instrument Serif') + '", cursive'; } if (p.k && em === 'serif') { return 'italic 400 ' + Math.round(sz * 1.3) + 'px "Instrument Serif", serif'; } return (p.k && em !== 'none' ? 900 : wt) + ' ' + Math.round(p.k && em !== 'none' ? sz * KS : sz) + 'px "' + FONT + '", sans-serif'; };
    var pad = 18, gap, tot = 0, ws = parts.map(function (p) { c.font = f(p); var m = c.measureText(p.t).width + (p.k && em === 'box' ? pad * 2 : 0); tot += m; return m; }); c.font = f({ k: false }); gap = c.measureText(' ').width * 0.8; tot += gap * (parts.length - 1);
    var ck = Math.floor(t * 3); if (ck !== clk) { clk = ck; clum = bgLum(c, -tot / 2 - 30, -sz * 0.8, tot + 60, sz * 1.6); }
    if (R.cap.pill) { c.fillStyle = 'rgba(16,16,20,0.78)'; rrectP(c, -tot / 2 - 26, -sz * 0.78, tot + 52, sz * 1.56, 18); c.fill(); }
    var xx = -tot / 2, lightC = onC && R.theme === 'lavender'; parts.forEach(function (p, j) { c.font = f(p); var col = lightC ? '#17132e' : '#fff'; if (p.k && em === 'color') { col = HL; } if (p.k && em === 'serif') { col = look.accent && st.id !== 'ref-cinematic' ? look.accent : '#E9C46A'; }
     var u = noAnim ? 1 : clamp((t - p.o + 0.03) / 0.2, 0, 1), al = noAnim ? 1 : clamp((t - p.o + 0.05) / 0.09, 0, 1); if (al <= 0) { xx += ws[j] + gap; return; }
     c.save(); c.globalAlpha *= al; c.translate(xx + ws[j] / 2, 0); var sc2 = noAnim ? 1 : 0.86 + 0.14 * easeOutBack(u); c.scale(sc2, sc2); c.translate(0, noAnim ? 0 : 10 * (1 - easeOut(u))); c.textAlign = 'center';
     if (p.k && em === 'box') { c.save(); c.shadowColor = 'rgba(0,0,0,0.28)'; c.shadowBlur = 18; c.fillStyle = ACC; rrectP(c, -ws[j] / 2, -sz * 0.64, ws[j], sz * 1.28, 8); c.fill(); c.restore(); c.fillStyle = '#fff'; c.fillText(p.t, 0, 2); }
     else if (R.cap.pill) { c.fillStyle = col; c.fillText(p.t, 0, 2); } else { capText(c, p.t, 0, p.k && (em === 'script' || em === 'serif') ? 4 : 1, col, clum); }
     c.restore(); xx += ws[j] + gap; }); } }); });
 /* ---- photos the member added: picture card over the blurred speaker (chloe) / tilted card ---- */
 if (photos && photos.length) { var used2 = 0, lastP = -9; Object.keys(P.keys).map(Number).filter(function (i) { return WO[i]; }).map(function (i) { return WO[i].o; }).sort(function (a, b) { return a - b; }).forEach(function (t) { if (used2 >= photos.length || t - lastP < 4 || busy(t - 0.1, t + 2, 'card') || busy(t - 0.1, t + 2, 'split') || busy(t - 0.1, t + 2, 'vcard') || busy(t - 0.1, t + 2, 'pic')) { return; } var img = photos[used2++]; lastP = t; BUSY.push([t - 0.05, t + 1.9, 'card']);
   add({ t0: t - 0.05, t1: Math.min(T, t + 1.9), kind: 'rise', dur: 260, out: 'blur', outd: 180, x: W / 2, y: 820, draw: function (c) { var bw = 760, bh = Math.min(1100, 760 * img.height / img.width); c.save(); c.shadowColor = 'rgba(0,0,0,0.45)'; c.shadowBlur = 50; c.fillStyle = '#fff'; rrectP(c, -bw / 2, -bh / 2, bw, bh, 34); c.fill(); c.restore(); c.save(); rrectP(c, -bw / 2, -bh / 2, bw, bh, 34); c.clip(); var s = Math.max(bw / img.width, bh / img.height); c.drawImage(img, -img.width * s / 2, -img.height * s / 2, img.width * s, img.height * s); c.restore(); } });
   ev(t - 0.1, 'swoosh1', 0.4); }); }
 /* split screens (kallaway) also on punchlines when there is no list */
 if (R.layout === 'kallaway' && items.length < 2 && !VON) { var lastK = -99; P.stacks.forEach(function (s) { var a = t0(s.a), b = t1(s.b); if (a == null || b == null || a - lastK < 7 || busy(a, b + 0.3, 'card')) { return; } lastK = a; var bb = Math.max(a + 1.6, b + 0.4); split(a, bb); var big = String(s.big).toUpperCase();
   add({ t0: a, t1: bb, kind: 'fade', dur: 140, out: 'cut', x: W / 2, y: 520, draw: function (c) { c.textAlign = 'center'; c.textBaseline = 'middle'; c.font = F(600, 44); c.fillStyle = 'rgba(255,255,255,0.7)'; c.fillText(cap1(s.lead || ''), 0, -150); var sz = fitSize(c, big, function (q) { return '900 ' + q + 'px "' + FONT + '", sans-serif'; }, 150, 940); c.font = '900 ' + sz + 'px "' + FONT + '", sans-serif'; c.fillStyle = '#fff'; c.fillText(big, 0, 0); var w = c.measureText(big).width; c.fillStyle = ACC; c.fillRect(-w / 2, sz * 0.55, w, 10); } });
   ev(a - 0.1, R.sfx.split, 0.45); }); }
 if (R.film) { add({ t0: 0, t1: T, kind: 'fade', dur: 1, out: 'cut', x: 0, y: 0, draw: function (c, t) { var g = c.createRadialGradient(W / 2, H * 0.46, H * 0.28, W / 2, H * 0.46, H * 0.78); g.addColorStop(0, 'rgba(0,0,0,0)'); g.addColorStop(1, 'rgba(0,0,0,0.5)'); c.fillStyle = g; c.fillRect(0, 0, W, H); var n = noiseTile(), ox = Math.floor(Math.random() * 192), oy = Math.floor(Math.random() * 192); c.save(); c.globalAlpha *= 0.075; c.globalCompositeOperation = 'overlay'; for (var yy = -oy; yy < H; yy += 192) { for (var xx2 = -ox; xx2 < W; xx2 += 192) { c.drawImage(n, xx2, yy); } } c.restore(); } }); }
 L.sort(function (a, b) { return (a.y === 0 ? 0 : 1) - (b.y === 0 ? 0 : 1); });
 return { items: L, events: EV, END: 0, cards: BUSY.filter(function (e) { return e[2] === 'card' || e[2] === 'split'; }).length, lay: LAY, marks: items.map(function (it) { return it.ta; }), cardSpans: BUSY.filter(function (e) { return e[2] === 'card' || e[2] === 'icard'; }).map(function (e) { return [e[0], e[1]]; }), pRef: P, vfmt: VON ? V.format : '' }; }
/* draw the video into a split-screen layout (used by the renderer and the style previews) */
function layAt(lay, t) { if (!lay) { return null; } for (var k = 0; k < lay.length; k++) { if (t >= lay[k].t0 && t < lay[k].t1) { return lay[k]; } } return null; }
function drawLay(c, ly, src, fy) { var sw = src.width, sh = src.height, v = ly.vid, x = v[0], y = v[1], w = v[2], h = v[3], r = v[4];
 c.fillStyle = ly.bg; c.fillRect(0, 0, W, H); var ar = w / h, cw = sw, ch = sw / ar; if (ch > sh) { ch = sh; cw = sh * ar; } var fo = fy || { head: 330, top: 520, chin: 900 }, kk = sh / H, fc = (fo.top + fo.chin) / 2 * kk, cx = (sw - cw) / 2, cy = clamp(Math.min(fc - 0.44 * ch, fo.head * kk - 0.05 * ch), 0, sh - ch);
 c.save(); if (r) { rrectP(c, x, y, w, h, r); c.clip(); } c.drawImage(src, cx, cy, cw, ch, x, y, w, h); c.restore(); }
function wrapP(c, t, maxW) { var out = [], cur = ''; String(t).split(' ').forEach(function (w) { var nx = cur ? cur + ' ' + w : w; if (cur && c.measureText(nx).width > maxW) { out.push(cur); cur = w; } else { cur = nx; } }); if (cur) { out.push(cur); } return out; }
/* ---------------- real recorded sound effects (CC0: Kenney, OpenGameArt) + music beds ---------------- */
var SFXB = {}, MUSB = {}, KMAP = { whoosh: ['whoosh1', 'whoosh2', 'whoosh3', 'whoosh4', 'whoosh5'], swish: ['swoosh3'], tick: ['click1'], pop: ['pop1', 'pop2'], hit: ['thud2'], riser: ['riser2'], chime: ['tick1'], typing: ['typing'], glitch: ['glitch1'] };
function decodeA(buf) { var ac = new (window.OfflineAudioContext || window.webkitOfflineAudioContext)(2, 48000, 48000); return new Promise(function (res, rej) { var p = ac.decodeAudioData(buf, res, rej); if (p && p.then) { p.then(res, rej); } }); }
function loadSfx(names) { return Promise.all(names.filter(function (n) { return n && !SFXB[n]; }).map(function (n) { return fetch(C.ed + 'sfx/' + n + '.mp3').then(function (r) { if (!r.ok) { throw new Error('sfx'); } return r.arrayBuffer(); }).then(decodeA).then(function (b) { SFXB[n] = [b.getChannelData(0), b.numberOfChannels > 1 ? b.getChannelData(1) : b.getChannelData(0)]; }).catch(function () {}); })); }
function loadMusic(name) { if (!name || name === 'none') { return Promise.resolve(null); } if (MUSB[name]) { return Promise.resolve(MUSB[name]); } return fetch(C.ed + 'music/' + name + '.mp3').then(function (r) { if (!r.ok) { throw new Error('music'); } return r.arrayBuffer(); }).then(decodeA).then(function (b) { MUSB = {}; MUSB[name] = [b.getChannelData(0), b.numberOfChannels > 1 ? b.getChannelData(1) : b.getChannelData(0)]; return MUSB[name]; }).catch(function () { return null; }); }
function sampleOf(kind, k) { if (SFXB[kind]) { return SFXB[kind]; } var m = KMAP[kind]; if (!m) { return null; } var nm = m[k % m.length]; return SFXB[nm] || null; }
function sfxNames(events) { var s = {}; events.forEach(function (e, k) { if (KMAP[e[1]]) { s[KMAP[e[1]][k % KMAP[e[1]].length]] = 1; } else { s[e[1]] = 1; } }); return Object.keys(s); }


/* ---------------- where is the face? skin-colour blobs on a few sample frames (captions and cards never cover it) ---------------- */
var FACEN = null;
var ortP = null;
function loadOrt() { if (ortP) { return ortP; } ortP = new Promise(function (res, rej) { if (window.ort) { res(window.ort); return; } var sc = document.createElement('script'); sc.src = C.st + 'ort/ort.wasm.min.js'; sc.onload = function () { window.ort ? res(window.ort) : rej(new Error('ort')); }; sc.onerror = function () { rej(new Error('ort')); }; document.head.appendChild(sc); }); return ortP; }
function faceNet() { if (S.fnet) { return S.fnet; } S.fnet = loadOrt().then(function (ort) { ort.env.wasm.wasmPaths = C.st + 'ort/'; ort.env.wasm.numThreads = 1; return ort.InferenceSession.create(C.ed + 'models/face320.onnx', { executionProviders: ['wasm'] }).then(function (ss) { return { ort: ort, ss: ss }; }); }); return S.fnet; }
/* one face detector pass (Ultra-Light RFB-320, MIT) on a window of the frame; returns the best face in source-normalised coordinates */
function faceWin(net, src, vw, vh, wx, wy, ww, wh) { var cv = faceWin.cv || (faceWin.cv = document.createElement('canvas')); cv.width = 320; cv.height = 240; var c = cv.getContext('2d', { willReadFrequently: true }); c.fillStyle = '#777'; c.fillRect(0, 0, 320, 240);
 var ar = (ww * vw) / (wh * vh), dw = 320, dh = 240, ox = 0, oy = 0; if (ar > 320 / 240) { dh = 320 / ar; oy = (240 - dh) / 2; } else { dw = 240 * ar; ox = (320 - dw) / 2; }
 c.drawImage(src, wx * vw, wy * vh, ww * vw, wh * vh, ox, oy, dw, dh); var d = c.getImageData(0, 0, 320, 240).data, f = new Float32Array(3 * 320 * 240), i, n = 320 * 240; for (i = 0; i < n; i++) { f[i] = (d[i * 4] - 127) / 128; f[n + i] = (d[i * 4 + 1] - 127) / 128; f[2 * n + i] = (d[i * 4 + 2] - 127) / 128; }
 var feeds = {}; feeds[net.ss.inputNames[0]] = new net.ort.Tensor('float32', f, [1, 3, 240, 320]);
 return net.ss.run(feeds).then(function (o) { var sc = o.scores ? o.scores.data : o[net.ss.outputNames[0]].data, bx = o.boxes ? o.boxes.data : o[net.ss.outputNames[1]].data, best = null, k, N = sc.length / 2;
  for (k = 0; k < N; k++) { var cf = sc[2 * k + 1]; if (cf < 0.72) { continue; } var x1 = bx[4 * k], y1 = bx[4 * k + 1], x2 = bx[4 * k + 2], y2 = bx[4 * k + 3], score = cf * (x2 - x1) * (y2 - y1); if (!best || score > best.score) { best = { score: score, cf: cf, x1: x1, y1: y1, x2: x2, y2: y2 }; } }
  if (!best) { return null; } var m = function (v, o0, dd, w0, wl) { return wl + ((v * 320 - o0) / dd) * w0; };
  var X1 = wx + ((best.x1 * 320 - ox) / dw) * ww, X2 = wx + ((best.x2 * 320 - ox) / dw) * ww, Y1 = wy + ((best.y1 * 240 - oy) / dh) * wh, Y2 = wy + ((best.y2 * 240 - oy) / dh) * wh; return { x1: X1, x2: X2, y1: Y1, y2: Y2, cf: best.cf, area: (X2 - X1) * (Y2 - Y1) * vw * vh }; }); }
function detectFaceNN(net, src) { var vw = src.videoWidth || src.naturalWidth || src.width, vh = src.videoHeight || src.naturalHeight || src.height, wins; if (vw / vh < 1.2) { var hh = Math.min(1, 0.75 * vw / vh); wins = [[0, 0, 1, 1]]; [0, 0.2, 0.4].forEach(function (y0) { if (y0 + hh <= 1.001) { wins.push([0, y0, 1, hh]); } }); } else { wins = [[0, 0, 1, 1]]; }
 var i = 0, best = null; return (function nx() { if (i >= wins.length) { return Promise.resolve(best); } var w = wins[i++]; return faceWin(net, src, vw, vh, w[0], w[1], w[2], w[3]).then(function (r) { if (r && (!best || r.cf * r.area > best.cf * best.area)) { best = r; } return nx(); }); })()
  .then(function (r) { if (!r) { return null; } var fh = Math.max(r.y2 - r.y1, 0.02); return { top: r.y1 + fh * 0.08, chin: r.y2, head: Math.max(0, r.y1 - fh * 0.32), cx: (r.x1 + r.x2) / 2, cf: r.cf }; }); }
/* find the speaker's face on 6 frames (runs in the background right after the video is chosen) */
function findFace(v) { var n = 6, k = 0, faces = []; return faceNet().then(function (net) { return (function one() { if (k >= n) { return Promise.resolve(); } var t = v.duration * (k + 0.5) / n; k++; return seekV(v, t).then(function () { return detectFaceNN(net, v); }).then(function (f) { if (f) { faces.push(f); } return one(); }); })(); })
 .then(function () { if (faces.length >= 2) { FACEN = { top: medianOf(faces.map(function (f) { return f.top; })), chin: medianOf(faces.map(function (f) { return f.chin; })), head: medianOf(faces.map(function (f) { return f.head; })), cx: medianOf(faces.map(function (f) { return f.cx; })) }; } return FACEN; }).catch(function () { return null; }); }
function medianOf(a) { a = a.slice().sort(function (x, y) { return x - y; }); return a[Math.floor(a.length / 2)]; }
function faceY(z) { var f = FACEN || { top: 0.2, chin: 0.42, head: 0.13, cx: 0.5 }, v = S.v, vw = (v && v.videoWidth) || 1080, vh = (v && v.videoHeight) || 1920, rc = cropRect(vw, vh, z, 0.45, (f.top + f.chin) / 2, f.cx), k = H / (rc[3] - rc[1]), m = function (y) { return (y - rc[1]) * k; }; return { head: m(f.head), top: m(f.top), chin: m(f.chin) }; }
function chestY(z) { var fy = faceY(z); return clamp(fy.chin + 150, 960, 1540); }

/* ---------------- colour grade: the owner's base grade, adapted to each video (port of grade.py) ---------------- */
var GBASE = { brightness: 2, contrast: 4, saturation: 6, brilliance: 3.5, highlights: -13, shadows: -23, whites: -7.5, hue: 9, sharpen: 50, clarity: 7 };
function seekV(v, t) { return new Promise(function (r) { var d = false; function ok() { if (d) { return; } d = true; v.removeEventListener('seeked', ok); r(); } v.addEventListener('seeked', ok); v.currentTime = t; setTimeout(ok, 4000); }); }
function measureStats(v) { var n = 8, k = 0, W0 = 72, H0 = 128, cv = document.createElement('canvas'); cv.width = W0; cv.height = H0; var cx = cv.getContext('2d', { willReadFrequently: true }), A = { y: 0, lo: 0, hi: 0, s: 0, p5: 0, p50: 0, p95: 0, yf: 0, nf: 0, r: 0, g: 0, b: 0, nb: 0 }, F = FACEN;
 function one() { if (k >= n) { return Promise.resolve({ y: A.y / n, lo: A.lo / n, hi: A.hi / n, s: A.s / n, p5: A.p5 / n, p50: A.p50 / n, p95: A.p95 / n, yf: A.nf ? A.yf / A.nf : null, bg: A.nb ? [A.r / A.nb, A.g / A.nb, A.b / A.nb] : null }); } var t = v.duration * (k + 0.5) / n; k++;
  return seekV(v, t).then(function () { cx.drawImage(v, 0, 0, W0, H0); var d = cx.getImageData(0, 0, W0, H0).data, ls = [], sm = 0, i, fx0 = -1, fx1 = -1, fy0 = -1, fy1 = -1, fs = 0, fn = 0, br = 0, bg = 0, bb = 0, bn = 0;
   if (F) { fx0 = (F.cx - 0.09) * W0; fx1 = (F.cx + 0.09) * W0; fy0 = (F.top + 0.03) * H0; fy1 = (F.chin - 0.02) * H0; }
   for (i = 0; i < d.length; i += 4) { var r = d[i], g = d[i + 1], b = d[i + 2], l = 0.299 * r + 0.587 * g + 0.114 * b, px = (i / 4) % W0, py = Math.floor(i / 4 / W0), inF = F && px >= fx0 && px <= fx1 && py >= fy0 && py <= fy1; ls.push(l); var u = -0.169 * r - 0.331 * g + 0.5 * b, w = 0.5 * r - 0.419 * g - 0.081 * b; sm += Math.sqrt(u * u + w * w);
    if (inF) { fs += l; fn++; } else if (l > 30 && l < 225) { br += r; bg += g; bb += b; bn++; } }
   ls.sort(function (a, b) { return a - b; }); var m = ls.length; A.y += ls.reduce(function (a, b) { return a + b; }, 0) / m; A.lo += ls[Math.floor(m * 0.1)]; A.hi += ls[Math.floor(m * 0.9)]; A.p5 += ls[Math.floor(m * 0.05)]; A.p50 += ls[Math.floor(m * 0.5)]; A.p95 += ls[Math.floor(m * 0.95)]; A.s += sm / m; if (fn > 8) { A.yf += fs / fn; A.nf++; } if (bn > 40) { A.r += br / bn; A.g += bg / bn; A.b += bb / bn; A.nb++; } return one(); }); }
 return one().catch(function () { return null; }); }
/* the grade is worked out from THIS video: exposure (face first), white balance, contrast range, colour strength, sharpening */
function buildGrade(stats, ui) { var s = {}, k, notes = [], wb = [1, 1, 1]; for (k in GBASE) { s[k] = GBASE[k]; }
 if (ui.auto && stats) { var y = stats.y, sat = stats.s, p5 = stats.p5, p50 = stats.p50, p95 = stats.p95, yf = stats.yf, spread = p95 - p5;
  var g = clamp(118 / Math.max(p50, 20), 0.72, 1.5); if (yf != null) { if (yf * g > 190) { g = 185 / yf; } else if (yf * g < 80 && p50 > 90) { g = Math.min(1.15, 88 / yf); } } g = clamp(1 + (g - 1) * 0.9, 0.74, 1.45); if (g > 1.2) { s.shadows *= 0.3; } if (Math.abs(g - 1) < 0.03) { g = 1; } else { notes.push('exposure ' + (g > 1 ? 'lifted ' : 'lowered ') + Math.round(Math.abs(g - 1) * 100) + '%'); }
  var bgc = stats.bg, gains = [1, 1, 1]; if (bgc) { var av = (bgc[0] + bgc[1] + bgc[2]) / 3; gains = bgc.map(function (c) { return clamp(1 + (av / Math.max(c, 8) - 1) * 0.5, 0.9, 1.1); }); if (Math.max.apply(null, gains) - Math.min.apply(null, gains) > 0.04) { notes.push(gains[0] < gains[2] ? 'warm cast reduced' : 'cool cast reduced'); } }
  wb = [gains[0] * g, gains[1] * g, gains[2] * g];
  s.contrast = GBASE.contrast + clamp((150 - spread) * 0.08, -5, 8); if (spread < 120) { notes.push('flat footage given more contrast'); } else if (spread > 215) { notes.push('harsh contrast softened'); }
  if (p5 < 10) { s.shadows *= 0.4; notes.push('shadows protected'); } else if (p5 > 45) { s.shadows *= 1.25; }
  if (p95 > 245) { s.highlights -= 9; s.whites -= 4; notes.push('highlights recovered'); } else if (p95 < 170) { s.whites = Math.abs(s.whites) * 0.5; s.highlights *= 0.4; }
  s.saturation = GBASE.saturation + clamp((15 - sat) * 0.8, -16, 10); if (sat > 24) { notes.push('colour toned down'); } else if (sat < 9) { notes.push('colour brought up'); }
  if (y < 70) { s.sharpen *= 0.65; s.clarity *= 0.7; notes.push('sharpening reduced for noise'); } }
 var I = clamp(ui.strength, 0, 1.5); for (k in s) { s[k] *= I; } s.wb = [1 + (wb[0] - 1) * I, 1 + (wb[1] - 1) * I, 1 + (wb[2] - 1) * I]; s.notes = notes; return s; }
/* ---------------- colour grade on the GPU ---------------- */
function makeGL(w, h) { var cv = document.createElement('canvas'); cv.width = w; cv.height = h; var gl = cv.getContext('webgl', { premultipliedAlpha: false, preserveDrawingBuffer: true }); if (!gl) { return null; }
  var vs = 'attribute vec2 p;varying vec2 v;varying vec2 o;uniform vec4 r;uniform vec2 rt;void main(){vec2 q=p;q.x*=rt.y;float a=rt.x;q=vec2(cos(a)*q.x-sin(a)*q.y,sin(a)*q.x+cos(a)*q.y);q.x/=rt.y;o=p*.5+.5;v=vec2(r.x+(q.x*.5+.5)*(r.z-r.x),r.y+(.5-q.y*.5)*(r.w-r.y));gl_Position=vec4(p,0.,1.);}';
  var fs = '#ifdef GL_FRAGMENT_PRECISION_HIGH\nprecision highp float;\n#else\nprecision mediump float;\n#endif\nvarying vec2 v;varying vec2 o;uniform sampler2D t;uniform vec4 g1;uniform vec4 g2;uniform vec2 g3;uniform vec2 px;uniform vec3 wb;uniform vec4 f1;uniform vec4 f2;uniform vec2 vc;uniform float sd;float hh(float n){return fract(sin(n*91.3458)*47453.5453);}vec3 S(vec2 u){return texture2D(t,u).rgb;}void main(){vec2 uv=v;if(f2.y>.001){float row=floor(o.y*16.);uv.x+=(hh(row+sd)-.5)*f2.y;}float ds=f2.w;vec3 c;if(f1.x>.001||f2.z>.001){vec3 acc=vec3(0.);for(int i=0;i<8;i++){float f=float(i)/7.;vec2 u2=uv-(uv-vc)*f1.x*f+vec2((f-.5)*f2.z,0.);acc+=S(u2);}c=acc/8.;}else if(ds>1.15){vec2 e=px*ds*.25;c=(S(uv+vec2(-e.x,-e.y))+S(uv+vec2(e.x,-e.y))+S(uv+vec2(-e.x,e.y))+S(uv+vec2(e.x,e.y)))*.25;}else{c=S(uv);}float k=max(1.,ds);vec3 b=(S(uv+vec2(px.x*k,0.))+S(uv-vec2(px.x*k,0.))+S(uv+vec2(0.,px.y*k))+S(uv-vec2(0.,px.y*k)))*.25;c+=(c-b)*g3.x*1.3;vec3 b2=(S(uv+vec2(5.,5.)*px*k)+S(uv+vec2(-5.,5.)*px*k)+S(uv+vec2(5.,-5.)*px*k)+S(uv+vec2(-5.,-5.)*px*k))*.25;c+=(c-b2)*g3.y*.9;c*=wb;c+=g1.x*.2;c=(c-.5)*g1.y+.5;float l=dot(c,vec3(.299,.587,.114));c+=g2.x*.22*(1.-smoothstep(0.,.5,l));c+=g2.y*.22*smoothstep(.45,1.,l);c+=g2.z*.14*smoothstep(.8,1.,l);c+=g2.w*.2*max(0.,1.-abs(l-.45)*2.);l=dot(c,vec3(.299,.587,.114));float sat=max(c.r,max(c.g,c.b))-min(c.r,min(c.g,c.b));c=mix(vec3(l),c,g1.z*(1.+(1.-sat)*.2));float h=g1.w,cs=cos(h),sn=sin(h);float Y=dot(c,vec3(.299,.587,.114)),I=dot(c,vec3(.596,-.274,-.322)),Q=dot(c,vec3(.211,-.523,.312));float I2=I*cs-Q*sn,Q2=I*sn+Q*cs;c=vec3(Y+.956*I2+.621*Q2,Y-.272*I2-.647*Q2,Y-1.106*I2+1.703*Q2);c=mix(c,vec3(1.),f1.y);c*=1.-f1.z;if(f1.w>.001){float gx=exp(-pow((o.x-f2.x)*2.6,2.));c+=vec3(1.,.55,.2)*(gx*.9+.12)*f1.w;}c+=(hh(gl_FragCoord.x*7.31+gl_FragCoord.y*13.7+sd)-.5)*.009;gl_FragColor=vec4(clamp(c,0.,1.),1.);}';
  function sh(type, src) { var s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return s; }
  var pr = gl.createProgram(); gl.attachShader(pr, sh(gl.VERTEX_SHADER, vs)); gl.attachShader(pr, sh(gl.FRAGMENT_SHADER, fs)); gl.linkProgram(pr); gl.useProgram(pr);
  var b = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, b); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW); var lp = gl.getAttribLocation(pr, 'p'); gl.enableVertexAttribArray(lp); gl.vertexAttribPointer(lp, 2, gl.FLOAT, false, 0, 0);
  var tx = gl.createTexture(); gl.bindTexture(gl.TEXTURE_2D, tx); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
  var U = function (n) { return gl.getUniformLocation(pr, n); }, uR = U('r'), uG1 = U('g1'), uG2 = U('g2'), uG3 = U('g3'), uPx = U('px'), uRt = U('rt'), uF1 = U('f1'), uF2 = U('f2'), uVc = U('vc'), uSd = U('sd'), uWb = U('wb');
  return { cv: cv, lose: function () { try { var e = gl.getExtension('WEBGL_lose_context'); if (e) { e.loseContext(); } } catch (x) {} }, draw: function (src, rect, gr, fx) { fx = fx || {}; gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, src); gl.uniform4f(uR, rect[0], rect[1], rect[2], rect[3]); var vw = src.videoWidth || src.width || 1080, vh = src.videoHeight || src.height || 1920,
    ds = (rect[2] - rect[0]) * vw / w, up = ds < 1 ? Math.min(1.9, 1 + 0.9 * (1 / Math.max(ds, 0.01) - 1)) : 1;
    gl.uniform4f(uG1, gr.brightness / 100, 1 + gr.contrast / 100 * 0.6, 1 + gr.saturation / 100 * 0.6, gr.hue * 0.2 * Math.PI / 180); gl.uniform4f(uG2, gr.shadows / 100, gr.highlights / 100, gr.whites / 100, gr.brilliance / 100); var rsz = Math.min(vw, vh), srcK = rsz < 1000 ? 1 + Math.min(0.5, (1080 / rsz - 1) * 0.6) : 1, wbv = gr.wb || [1, 1, 1]; gl.uniform3f(uWb, wbv[0], wbv[1], wbv[2]); gl.uniform2f(uG3, gr.sharpen / 100 * 0.6 * up * srcK, gr.clarity / 100 * 1.2); gl.uniform2f(uPx, 1 / vw, 1 / vh);
    gl.uniform2f(uRt, fx.rot || 0, w / h); gl.uniform4f(uF1, fx.rb || 0, fx.flash || 0, fx.fade || 0, fx.leak || 0); gl.uniform4f(uF2, fx.leakPos || 0, fx.slice || 0, fx.bx || 0, ds); gl.uniform2f(uVc, (rect[0] + rect[2]) / 2, (rect[1] + rect[3]) / 2); gl.uniform1f(uSd, Math.random() * 40);
    gl.viewport(0, 0, w, h); gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4); } }; }

/* ---------------- CapCut-style clip transitions (zoom rush, rebound, swing, flash, stamp, slide, fade, heat-leak, slices, super-zoom) ---------------- */
var TR_DUR = { zoomrush: 0.30, rebound: 0.36, swing: 0.34, flash: 0.26, stamp: 0.34, slide: 0.30, fade: 0.34, leak: 0.55, slices: 0.28, superzoom: 0.46 };
var TR_SFX = { zoomrush: [['whoosh3', -0.14, 0.55]], rebound: [['pop1', 0, 0.55], ['thud2', 0, 0.3]], swing: [['swoosh1', -0.12, 0.6]], flash: [['shutter', -0.02, 0.6]], stamp: [['thud1', 0, 0.5], ['click1', 0.03, 0.4]], slide: [['swoosh2', -0.12, 0.6]], fade: [['paper1', -0.06, 0.32]], leak: [['whoosh5', -0.22, 0.5], ['rise1', -0.3, 0.3]], slices: [['glitch1', 0, 0.5], ['flicker1', 0.02, 0.4]], superzoom: [['riser2', -0.55, 0.45], ['drama2', 0, 0.6]] };
function fxAt(kind, u, I) { var e = 1 - u, f = { z: 0, dx: 0, dy: 0, rot: 0, rb: 0, bx: 0, flash: 0, fade: 0, leak: 0, leakPos: 0, slice: 0 };
 if (kind === 'zoomrush') { f.z = 0.30 * e * e; f.rb = 0.30 * e * e; } else if (kind === 'rebound') { f.z = 0.24 * Math.exp(-5 * u) * Math.cos(11 * u); } else if (kind === 'swing') { f.rot = 0.11 * Math.exp(-4.5 * u) * Math.cos(9 * u); f.z = 0.12 * e; f.dx = 0.05 * e; }
 else if (kind === 'flash') { f.flash = 0.92 * e * e; f.z = 0.07 * e; } else if (kind === 'stamp') { f.z = 0.36 * Math.pow(e, 2.2); f.dx = 0.014 * Math.sin(u * 55) * e; f.dy = 0.01 * Math.cos(u * 48) * e; }
 else if (kind === 'slide') { f.dx = 0.17 * Math.pow(e, 2); f.bx = 0.06 * e; } else if (kind === 'fade') { f.fade = 0.8 * Math.pow(e, 1.4); f.z = 0.03 * e; }
 else if (kind === 'leak') { f.leak = Math.sin(Math.PI * u); f.leakPos = -0.3 + 1.6 * u; f.z = 0.04 * e; } else if (kind === 'slices') { f.slice = 0.1 * e; f.bx = 0.02 * e; f.flash = 0.14 * e; }
 else if (kind === 'superzoom') { f.z = 0.75 * e * e; f.rb = 0.6 * e * e; f.flash = 0.25 * Math.pow(e, 3); }
 var k = I == null ? 1 : I; f.z *= k; f.dx *= k; f.dy *= k; f.rot *= k; f.rb *= k; f.bx *= k; f.flash *= k; f.fade *= k; f.leak *= k; f.slice *= k; return f; }
function trAt(list, t, I) { if (!list) { return null; } for (var k = list.length - 1; k >= 0; k--) { var d = TR_DUR[list[k].kind] || 0.3; if (t >= list[k].t && t < list[k].t + d) { return fxAt(list[k].kind, (t - list[k].t) / d, I); } if (list[k].t < t - 1) { break; } } return null; }
function planTrans(job, R, ui) { if (ui.trans === 'off') { return []; } var T = job.cut.T, pts = [], out = [], lastT = -99, cards = job.layer.cardSpans || [];
 job.shots.forEach(function (sh, i) { if (i > 0 && sh[0] > 2 && sh[0] < T - 1.2) { pts.push(sh[0]); } }); pts.sort(function (a, b) { return a - b; });
 pts.forEach(function (t) { if (t - lastT < 9) { return; } if (cards.some(function (c) { return t >= c[0] - 0.05 && t < c[1] + 0.05; })) { return; } lastT = t; out.push({ t: t, kind: out.length % 2 ? 'slide' : 'fade' }); }); return out; }

/* ---------------- the camera: shots wide/tight, slow push-in, eased punch-ins (port of core.base) ---------------- */
function camAt(t, shots, punches, look, anchor) { var sh = shots[0]; for (var k = 0; k < shots.length; k++) { if (shots[k][0] <= t && t < shots[k][1]) { sh = shots[k]; break; } } var a = sh[0], b = sh[1], n = Math.max(1, Math.round((b - a) * FPS)), on = (t - a) * FPS;
 var z = (sh[2] === 'T' ? 1.16 : 1.0) * (1 + 0.035 * on / n);
 punches.forEach(function (p) { if (a <= p[0] && p[0] < b) { var f0 = (p[0] - a) * FPS - 3, f1 = Math.min((p[1] - a) * FPS + 12, (b - a) * FPS - 1), up = clamp((on - f0) / 6, 0, 1), dn = clamp((on - f1) / 10, 0, 1); z += look.zoom * sstep(up) * (1 - sstep(dn)); } });
 return z; }
function cropRect(vw, vh, z, anchor, fcy, fcx) { var tw = W / H, sw, shh; if (vw / vh > tw) { shh = vh; sw = vh * tw; } else { sw = vw; shh = vw / tw; } sw /= z; shh /= z; var cx = vw * (vw / vh > tw && fcx != null ? fcx : 0.5), cy = vh * (fcy != null ? fcy : 0.42), x = clamp(cx - sw / 2, 0, vw - sw), y = clamp(cy - shh * anchor, 0, vh - shh); return [x / vw, y / vh, (x + sw) / vw, (y + shh) / vh]; }

/* ---------------- render frames with WebCodecs, mux, add sound ---------------- */
function brFor(TT) { return Math.round(clamp((IOS ? 200 : 280) * 1048576 * 8 / Math.max(TT, 1), 6e6, 14e6)); }
function pickCodec() { var list = ['avc1.640028', 'avc1.4d0028', 'avc1.42e028', 'vp09.00.40.08']; var i = 0; return new Promise(function (res) { (function nx() { if (i >= list.length || !window.VideoEncoder) { res(null); return; } var c = list[i++]; VideoEncoder.isConfigSupported({ codec: c, width: W, height: H, bitrate: 12e6, framerate: FPS }).then(function (r) { if (r.supported) { res(c); } else { nx(); } }).catch(nx); })(); }); }

/* ---------------- every style card shows the member's own video in that style ---------------- */
var PVW = 'Number one, posting without a hook. Most estheticians post every single day and still get no bookings. Number two, hiding your prices. Fixing this took six months of work for one client.'.split(' ');
function drawItems(c, items, t) { items.forEach(function (it) { if (t < it.t0 || t >= it.t1) { return; } var p = itemAlpha(it, t); if (p.a <= 0.001) { return; } c.save(); c.globalAlpha = p.a; c.translate(it.x, it.y + p.dy); c.scale(p.sc, p.sc); it.draw(c, t); c.restore(); }); }
var TW = 360, TH = 640, TCV = null;
function thumbFor(sid, graded, ui) { var st = styleById(sid); st = ext(st, { look: ext(st.look, sid === S.style && st.fam === 'b' ? ui.look : {}), pro: proFor(sid, 1, true) });
 var words = [], t = 0.2; PVW.forEach(function (w) { var len = 0.22 + Math.min(w.length, 9) * 0.02; words.push({ w: w, s: t, e: t + len }); t += len + 0.04; }); var T = t + 0.5, plan = autoplan(words, st, []);
 var cut = { segs: [{ s: 0, e: T, o: 0 }], T: T, words: words.map(function (x, i) { return { i: i, w: x.w, o: x.s, oe: x.e }; }), keep: words.map(function (x, i) { return i; }) }, shots = [[0, T, 'W']], layer = buildLayer(cut, plan, st, shots, []);
 var Rr = REF[sid], ly = null, tt;
 if (Rr) { st.look = ext(st.look, { zoom: 0 }); var P = refPlan(words, plan, null), lr = buildRef(cut, plan, st, shots, [], P), it0 = P.items[0], sk = P.stacks[0];
  tt = it0 && (Rr.list || Rr.hdr) ? cut.words[it0.a].o + 0.9 : ((Rr.stackAll || (Rr.stack && sk)) ? cut.words[sk ? sk.a : 12].o + 0.6 : cut.words[14].o + 0.1); layer = lr; ly = layAt(lr.lay, tt); }
 else { var hd = plan.headers && plan.headers[0], bl = plan.blocks && plan.blocks[0]; tt = hd ? cut.words[hd.w[1]].o - 0.1 : (bl ? cut.words[bl.w[1]].o + 0.6 : cut.words[3].o + 0.3); }
 if (!TCV) { TCV = document.createElement('canvas'); TCV.width = TW; TCV.height = TH; } var bc = TCV.getContext('2d'); bc.setTransform(TW / W, 0, 0, TH / H, 0, 0); bc.globalAlpha = 1; if (ly) { drawLay(bc, ly, graded, faceY(1.0)); } else { bc.drawImage(graded, 0, 0, W, H); } drawItems(bc, layer.items, tt); bc.setTransform(1, 0, 0, 1, 0, 0); return TCV.toDataURL('image/jpeg', 0.8); }
var pvBusy = false, pvAgain = null;
function refreshPreview(all) { if (!S.v) { return; } if (pvBusy) { pvAgain = pvAgain || all; if (all) { pvAgain = true; } return; } pvBusy = true; var v = S.v; S.th = S.th || {};
 loadFonts().then(function () { return S.stats !== undefined ? null : measureStats(v).then(function (s) { S.stats = s; }); }).then(function () { return all || !S.gradedF ? seekV(v, v.duration * 0.35) : null; }).then(function () {
  var ui = uiVals(); if (all || !S.gradedF) { var gr = buildGrade(S.stats, ui.grade), g = makeGL(TW, TH), gc = S.gradedF || document.createElement('canvas'); gc.width = TW; gc.height = TH; var x = gc.getContext('2d'); if (g) { g.draw(v, cropRect(v.videoWidth, v.videoHeight, 1.0, 0.45, FACEN ? (FACEN.top + FACEN.chin) / 2 : null, FACEN ? FACEN.cx : null), gr); x.drawImage(g.cv, 0, 0); g.lose(); } else { x.drawImage(v, 0, 0, TW, TH); } S.gradedF = gc; S.gradeNotes = gr.notes; }
  var ids = all ? STYLES.map(function (s) { return s.id; }) : [S.style], k = 0;
  function nx() { if (k >= ids.length) { return Promise.resolve(); } var id = ids[k++]; try { S.th[id] = thumbFor(id, S.gradedF, ui); var el = $('.rx-st[data-id="' + id + '"] .th'); if (el) { el.style.backgroundImage = 'url(' + S.th[id] + ')'; el.innerHTML = ''; } } catch (e) {} return tick().then(nx); }
  return nx(); }).catch(function () {}).then(function () { pvBusy = false; var a = pvAgain; pvAgain = null; if (a) { refreshPreview(a === true); } }); }
function renderVideo(v, job, onp) {
 var cut = job.cut, layer = job.layer, TT = cut.T + layer.END, N = Math.round(TT * FPS), gl = makeGL(W, H), out = document.createElement('canvas'); out.width = W; out.height = H; var c = out.getContext('2d');
 var gr = job.grade, anchor = 0.45, WO = {}; cut.words.forEach(function (x) { WO[x.i] = x; });
 var punches = job.plan.punch.filter(function (i) { return WO[i]; }).map(function (i) { return [WO[i].o, WO[i].oe]; });
 var items = layer.items;
 function compose(t, hasVideo) { c.setTransform(1, 0, 0, 1, 0, 0); c.globalAlpha = 1; if (hasVideo && gl) { var z = camAt(t, job.shots, punches, job.st.look, anchor); var fxo = trAt(job.trans, t, job.trI), rc = cropRect(v.videoWidth, v.videoHeight, z * (1 + (fxo ? fxo.z : 0)), anchor, FACEN ? (FACEN.top + FACEN.chin) / 2 : null, FACEN ? FACEN.cx : null); if (fxo && (fxo.dx || fxo.dy)) { var rw = rc[2] - rc[0], rh = rc[3] - rc[1]; rc = [rc[0] + fxo.dx * rw, rc[1] + fxo.dy * rh, rc[2] + fxo.dx * rw, rc[3] + fxo.dy * rh]; } gl.draw(v, rc, gr, fxo); var ly = layAt(job.layer.lay, t); if (ly) { drawLay(c, ly, gl.cv, faceY(z)); } else { c.drawImage(gl.cv, 0, 0); } } else if (!hasVideo) { c.fillStyle = '#000'; c.fillRect(0, 0, W, H); }
  items.forEach(function (it) { if (t < it.t0 || t >= it.t1) { return; } var p = itemAlpha(it, t); if (p.a <= 0.001) { return; } c.save(); c.globalAlpha = p.a; c.translate(it.x, it.y + p.dy); c.scale(p.sc, p.sc); it.draw(c, t); c.restore(); }); }
 return pickCodec().then(function (codec) { if (!codec || !window.Mp4Muxer) { throw new Error('nocodec'); }
  var isAvc = /^avc/.test(codec), mux = new Mp4Muxer.Muxer({ target: new Mp4Muxer.ArrayBufferTarget(), video: { codec: isAvc ? 'avc' : 'vp9', width: W, height: H }, fastStart: 'in-memory' }), err = null;
  var enc = new VideoEncoder({ output: function (ch, meta) { mux.addVideoChunk(ch, meta); }, error: function (e) { err = e; } });
  enc.configure({ codec: codec, width: W, height: H, bitrate: brFor(TT), framerate: FPS, latencyMode: 'quality', bitrateMode: 'constant', avc: { format: 'avc' } });
  var f = 0;
  function emit(t, hasVideo) { compose(t, hasVideo); var vf = new VideoFrame(out, { timestamp: Math.round(f * 1e6 / FPS), duration: Math.round(1e6 / FPS) }); enc.encode(vf, { keyFrame: f % 60 === 0 }); vf.close(); f++; if (f % 15 === 0) { onp(f / N); } }
  function waitQ() { return enc.encodeQueueSize > 6 ? new Promise(function (r) { setTimeout(r, 15); }).then(waitQ) : Promise.resolve(); }
  function seek(t) { return new Promise(function (r) { var d = false; function ok() { if (d) { return; } d = true; v.removeEventListener('seeked', ok); r(); } v.addEventListener('seeked', ok); v.currentTime = t; setTimeout(ok, 4000); }); }
  /* each output frame needs the source frame at segment.s + (t - segment.o); frames are pulled by seeking in order (exact, never stutters) */
  var segs = cut.segs;
  /* playback-driven capture: each segment plays once; every output frame takes the source frame showing at its time (timestamps come from the edit, never from the clock) */
  function srcOf(t) { var sg = segs[0]; for (var k = 0; k < segs.length; k++) { if (segs[k].o <= t + 1e-6) { sg = segs[k]; } } return sg.s + (t - sg.o); }
  var prate = 1, clean = 0, d0 = 0.5;
   function playSeg(k) { var lastM = null, sg = segs[k], end = k + 1 < segs.length ? segs[k + 1].o : cut.T;
   return seek(sg.s).then(function () { return new Promise(function (res, rej) { var done = false, stall = null;
    function fin() { if (done) { return; } done = true; clearTimeout(stall); v.pause(); res(); }
    function step(now, meta) { if (done) { return; } if (err) { done = true; v.pause(); rej(err); return; } var m = meta && meta.mediaTime != null ? meta.mediaTime : v.currentTime;
      if (lastM != null && m > lastM) { var dl = m - lastM; if (dl < d0) { d0 = dl; } var rmax = d0 < 0.025 ? 1.5 : 2; if (dl > Math.max(1.45 / FPS, 1.5 * d0)) { prate = Math.max(0.5, prate * 0.75); clean = 0; } else if (++clean >= 20 && prate < rmax) { prate = Math.min(rmax, prate * 1.25); clean = 0; } var want = m > sg.e - 0.3 ? Math.min(prate, 1) : prate; if (Math.abs(v.playbackRate - want) > 0.01) { try { v.playbackRate = want; } catch (x) {} } } lastM = m;
     while (f < N && f / FPS < end - 1e-6 && srcOf(f / FPS) <= m + 0.6 / FPS) { emit(f / FPS, true); }
     if (f >= N || f / FPS >= end - 1e-6 || m >= sg.e - 0.5 / FPS || v.ended) { while (f < N && f / FPS < end - 1e-6) { emit(f / FPS, true); } fin(); return; }
     clearTimeout(stall); stall = setTimeout(function () { while (f < N && f / FPS < end - 1e-6) { emit(f / FPS, true); } fin(); }, 4000);
     if (enc.encodeQueueSize > 8) { v.pause(); waitQ().then(function () { v.play().catch(function () {}); v.requestVideoFrameCallback(step); }); } else { v.requestVideoFrameCallback(step); } }
    v.muted = true; v.playbackRate = Math.min(prate, 1); v.requestVideoFrameCallback(step); v.play().catch(function (e) { done = true; rej(e); }); }); }); }
  function viaPlayback() { var k = 0; return (function nx() { if (k >= segs.length) { return Promise.resolve(); } return playSeg(k++).then(nx); })().then(function endCard() { if (f >= N) { return; } emit(f / FPS, false); return (f % 10 ? Promise.resolve() : tick()).then(waitQ).then(endCard); }); }
  var si = 0;
  function nextFrame() { if (err) { return Promise.reject(err); } if (f >= N) { return Promise.resolve(); } var t = f / FPS;
   if (t >= cut.T) { emit(t, false); return (f % 10 ? Promise.resolve() : tick()).then(waitQ).then(nextFrame); }
   while (si + 1 < segs.length && segs[si + 1].o <= t + 1e-6) { si++; } var sg = segs[si], src = sg.s + (t - sg.o) + 0.5 / FPS;
   return seek(src).then(function () { emit(t, true); return waitQ(); }).then(nextFrame); }
  if ('requestVideoFrameCallback' in HTMLVideoElement.prototype) { return viaPlayback().then(function () { return enc.flush(); }).then(function () { if (err) { throw err; } mux.finalize(); if (gl) { gl.lose(); } return new Uint8Array(mux.target.buffer); }); }
  return seek(0.01).then(nextFrame).then(function () { return enc.flush(); }).then(function () { if (err) { throw err; } mux.finalize(); if (gl) { gl.lose(); } return new Uint8Array(mux.target.buffer); }); }); }

/* ---------------- voice (the engine's exact chain) + sound effects + -14 LUFS ---------------- */
function buildAudio(a48, job, onp) { var MIXIN = 'mix0.raw', MIXN = [], MUS = job.music || null, segs = job.cut.segs, TT = job.cut.T + job.layer.END, n = Math.floor(TT * SR), vcut = new Float32Array(n), o = 0;
 segs.forEach(function (sg) { var s0 = Math.floor(sg.s * SR), s1 = Math.min(a48.length, Math.floor(sg.e * SR)), len = s1 - s0, fi = Math.floor(0.008 * SR), fo = Math.floor(0.012 * SR); for (var i = 0; i < len && o + i < n; i++) { var g = Math.min(1, i / fi, (len - i) / fo); vcut[o + i] = a48[s0 + i] * g; } o += Math.max(0, len); });
 var mean = 0; for (var i = 0; i < n; i++) { mean += vcut[i] * vcut[i]; } mean = 10 * Math.log10(mean / Math.max(1, n) + 1e-12); var gain = (-24 - mean).toFixed(1);
 var tail = ',equalizer=f=250:t=q:w=1.1:g=-1.5,equalizer=f=3500:t=q:w=1.4:g=1.5,deesser=i=0.18,acompressor=threshold=-20dB:ratio=2:attack=12:release=180:makeup=1.5,alimiter=limit=0.9:level=false', head = 'highpass=f=70,volume=' + gain + 'dB,';
 var chain = head + 'arnndn=m=sh.rnnn:mix=0.75' + tail, chain2 = head + 'arnndn=m=sh.rnnn' + tail;
 onp('Cleaning your voice...');
 return FF.writeFile('vc.raw', new Uint8Array(vcut.buffer)).then(function () { vcut = null; return fetch(C.ed + 'assets/sh.rnnn').then(function (r) { return r.arrayBuffer(); }).then(function (b) { return FF.writeFile('sh.rnnn', new Uint8Array(b)); }); })
  .then(function () { var go = function (ch) { return ex(['-f', 'f32le', '-ar', '48000', '-ac', '1', '-i', 'vc.raw', '-af', ch, '-f', 'f32le', '-ac', '1', '-ar', '48000', 'voice.raw']); }; return go(chain).catch(function () { return go(chain2); }); })
  .then(function () { del('vc.raw'); return rdF('voice.raw'); })
  .then(function (vo) { onp('Adding sound design...'); var BLK = 60 * SR, b0 = 0, ev = job.layer.events, GW = 480, NG = Math.ceil(n / GW) + 1, mg = null;
    if (MUS) { mg = new Float32Array(NG); var g = 1, base = 0.17 * (job.musicVol == null ? 1 : job.musicVol), j; for (j = 0; j < NG; j++) { var s0 = j * GW, e = 0, q; for (q = s0; q < Math.min(s0 + GW, vo.length); q++) { e += vo[q] * vo[q]; } e = Math.sqrt(e / GW); var want = e > 0.02 ? 0.55 : 1; g += (want - g) * (want < g ? 0.25 : 0.02); var tt = j * GW / SR; mg[j] = base * g * Math.min(1, tt / 0.8) * Math.min(1, Math.max(0, (TT - tt) / 1.6)); } }
    function nxb() { if (b0 >= n) { return Promise.resolve(); } var len = Math.min(BLK, n - b0), off = b0 / SR, evs = ev.filter(function (e) { return e[0] >= off - 2 && e[0] < off + len / SR; }), fx = renderSfx(evs, len / SR, off), mix = new Float32Array(len * 2), i;
     var ML = MUS ? MUS[0].length : 0; for (i = 0; i < len; i++) { var p = b0 + i, x = p < vo.length ? vo[p] : 0, mL = 0, mR = 0; if (MUS) { var gq = mg[Math.floor(p / GW)], mp = p % ML; mL = MUS[0][mp] * gq; mR = MUS[1][mp] * gq; } mix[2 * i] = x + (fx[0][i] || 0) * 0.55 + mL; mix[2 * i + 1] = x + (fx[1][i] || 0) * 0.55 + mR; }
     var nm = 'mix' + MIXN.length + '.raw'; MIXN.push(nm); b0 += len; return FF.writeFile(nm, new Uint8Array(mix.buffer)).then(tick).then(nxb); }
    return nxb().then(function () { MIXIN = MIXN.length > 1 ? 'concat:' + MIXN.join('|') : MIXN[0]; }); })
  .then(function () { ffLog = []; return ex(['-f', 'f32le', '-ar', '48000', '-ac', '2', '-i', MIXIN, '-af', 'aformat=channel_layouts=stereo,loudnorm=I=-14:TP=-2:LRA=9:print_format=json', '-f', 'null', '-']); })
  .then(function () { var s = ffLog.join('\n'), j = null; try { j = JSON.parse(s.slice(s.lastIndexOf('{'), s.lastIndexOf('}') + 1)); } catch (e) {}
   var ln = j ? 'loudnorm=I=-14:TP=-2:LRA=9:measured_I=' + j.input_i + ':measured_TP=' + j.input_tp + ':measured_LRA=' + j.input_lra + ':measured_thresh=' + j.input_thresh + ':offset=' + j.target_offset + ':linear=true' : 'loudnorm=I=-14:TP=-2:LRA=9';
   return ex(['-f', 'f32le', '-ar', '48000', '-ac', '2', '-i', MIXIN, '-af', 'aformat=channel_layouts=stereo,' + ln + ',alimiter=limit=0.89:attack=3:release=80:level=false,aresample=48000,aformat=channel_layouts=stereo', '-t', TT.toFixed(3), '-c:a', 'pcm_s16le', 'mix.wav']); })
  .then(function () { MIXN.forEach(del); }); }

/* ---------------- the page ---------------- */
var S = { file: null, photos: [], style: 'ref-chloe', v: null, a16: null, a48: null, words: null, removed: [], busy: false, url: null };
function show(t, p, n) { $('#rxStep').textContent = t; if (p != null) { $('#rxBar').style.width = clamp(Math.round(p), 2, 100) + '%'; } if (n != null) { $('#rxNote').textContent = n; } }
function fail(m) { var e = $('#rxErr'); e.textContent = m; e.hidden = false; $('#rxWork').hidden = true; S.busy = false; $('#rxGo').disabled = !S.file; e.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
function mb(n) { return (n / 1048576).toFixed(1) + ' MB'; }
function styleCards() { $('#rxStyles').innerHTML = STYLES.map(function (s) { var th = S.th && S.th[s.id];
  return '<button type="button" class="rx-st' + (s.id === S.style ? ' on' : '') + '" data-id="' + s.id + '" title="' + esc(s.desc) + '"><span class="th"' + (th ? ' style="background-image:url(' + th + ')"' : '') + '>' + (th ? '' : '<i>' + esc(s.name) + '</i>') + '</span><b>' + esc(s.name) + '</b></button>'; }).join(''); }
function loadVid(f) { return new Promise(function (res, rej) { var v = document.createElement('video'); v.muted = true; v.playsInline = true; v.setAttribute('playsinline', ''); v.preload = f.size > 120 * 1048576 ? 'metadata' : 'auto'; v.crossOrigin = 'anonymous'; v.style.cssText = 'position:fixed;left:-9999px;top:0;width:2px;height:2px;opacity:0'; document.body.appendChild(v); v.src = URL.createObjectURL(f); v.onloadeddata = function () { res(v); }; v.onerror = function () { rej(new Error('This video could not be opened. Try an MP4 or MOV from your phone.')); }; }); }
function prepare() { /* once per video: open it, pull the sound, hear every word */
 if (S.words) { return Promise.resolve(); }
 show('Opening your video...', 3, ''); return Promise.all([S.v ? Promise.resolve(S.v) : loadVid(S.file), loadFonts(), loadFF()]).then(function (r) { S.v = r[0]; if (!isFinite(S.v.duration) || S.v.duration < 2) { throw new Error('This video is too short.'); } if (S.v.duration > MAXS) { throw new Error('This video is ' + Math.round(S.v.duration / 6) / 10 + ' minutes. ' + (IOS ? 'On iPhone the editor takes up to 6 minutes (10 minutes on a computer). Trim it and try again.' : 'The editor takes up to 10 minutes. Trim it and try again.')); }
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
function uiVals() { var g = function (id, d) { var el = $('#' + id); return el ? el.value : d; }, ck = function (id, d) { var el = $('#' + id); return el ? el.checked : d; };
 return { format: g('rxFormat', 'auto'), trans: g('rxTrans', 'auto'), music: g('rxMusic', 'auto'), musicVol: +g('rxMusicVol', 100) / 100, grade: { auto: ck('rxGAuto', true), strength: +g('rxGStr', 100) / 100 }, look: { main_color: g('rxMain', '#ffffff'), hl: g('rxHl', '#FFD60A'), stroke: g('rxOutl', '#0B0B0F'), cap_pos: g('rxCapPos', 'chest'), emph: g('rxEmph', 'color'), cap_bg: g('rxCapBg', 'none') } }; }
function make() { if (S.busy || !S.file) { return; } S.busy = true; $('#rxGo').disabled = true; $('#rxErr').hidden = true; $('#rxWork').hidden = false; $('#rxOut').hidden = true; $('#rxWork').scrollIntoView({ behavior: 'smooth', block: 'center' });
 var job = { st: styleById(S.style) }, ui = uiVals(), extra = { cap: +$('#rxCap').value / 100, zoom: +$('#rxZoom').value / 100, sfx: +$('#rxSfx').value / 100 };
 job.st = ext(job.st, { look: ext(job.st.look, { cap_size: job.st.look.cap_size * extra.cap, zoom: job.st.look.zoom * extra.zoom, sfx: job.st.look.sfx * extra.sfx }) }); if (job.st.fam === 'b') { job.st.look = ext(job.st.look, ui.look); }
 var gv = function (id) { var el = $('#' + id); return el ? String(el.value || '').trim() : ''; };
 job.st.pro = proFor(job.st.id, +(gv('rxPace') || 1), !$('#rxPro') || $('#rxPro').checked); job.st.hook = gv('rxHook').slice(0, 60); job.st.sub = gv('rxSub').slice(0, 50); job.st.cta = gv('rxCta').replace(/[^\w-]/g, '').slice(0, 20);
 prepare().then(function () { return S.facePromise || (S.v ? findFace(S.v) : null); }).then(function () { return S.stats !== undefined ? null : measureStats(S.v).then(function (s) { S.stats = s; }); }).then(function () { job.grade = buildGrade(S.stats, ui.grade);
   show('Understanding your video...', 31, 'Finding your hook, your list, key words and numbers.'); var sig = S.words.length + ':' + S.words.map(function (x) { return x.w; }).join(' ').length; return (S.aiSig === sig ? Promise.resolve(S.ai) : aiPlan(S.words, job.st.id).then(function (a) { S.ai = a; S.aiSig = sig; return a; })).then(function (ai) { show('Finding pictures...', 32, 'Looking for photos of the things you mention.'); return fetchPics(picQueries(ai, ui.format)).then(function (imgs) { job.imgs = imgs; return ai; }); }).then(function (ai) {
   show('Planning the edit like your reference...', 33, ''); var R = REF[job.st.id], dw = dispWords(S.words, ai), db = envDb(S.a48), sil = silences(db); if (R) { job.st.look = ext(job.st.look, { zoom: 0 }); }
   job.plan = autoplan(dw, job.st, S.removed); if (R) { job.plan.punch = []; if (R.cam === 'locked') { job.plan.shots = []; } } job.cut = doCut(dw, job.plan, sil, db, S.v.duration); if (!job.cut) { throw new Error('Nothing left to edit after the cuts.'); }
   job.shots = makeShots(job.cut, job.plan, job.st); if (R && R.cam !== 'cuts') { job.shots.forEach(function (s) { s[2] = 'W'; }); }
   job.layer = R ? buildRef(job.cut, job.plan, job.st, job.shots, S.photos, withImgs(refPlan(dw, job.plan, ai), job.imgs, ui.format)) : buildLayer(job.cut, job.plan, job.st, job.shots, S.photos);
   job.trI = R && R.trI != null ? R.trI : 1; job.trans = planTrans(job, R, ui); job.layer.events = calmSfx(job.layer.events);
   var mname = ui.music === 'auto' ? (R ? R.music : 'lofi_chill') : ui.music; job.musicVol = ui.musicVol;
   return Promise.all([loadSfx(sfxNames(job.layer.events)), loadMusic(mname).then(function (m) { job.music = m; })]); }).then(function () {
  job.audioP = buildAudio(S.a48, job, function () {}); job.audioP.catch(function () {}); }); })
  .then(function () { show('Rendering every frame...', 40, (S.v.duration > 180 ? 'Long video: keep this page open and your screen on. ' : 'Keep this page open. ') + 'Your voice is being cleaned at the same time, so this is faster.'); return renderVideo(S.v, job, function (q) { show('Rendering every frame...', 40 + q * 52); }); })
  .then(function (mp4) { show('Cleaning your voice...', 93); return job.audioP.then(function () { return mp4; }); })
  .then(function (mp4) { show('Putting it together...', 95); return FF.writeFile('v.mp4', mp4).then(function () { return ex(['-i', 'v.mp4', '-i', 'mix.wav', '-map', '0:v:0', '-map', '1:a:0', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '256k', '-shortest', '-movflags', '+faststart', 'final.mp4']); }).then(function () { return FF.readFile('final.mp4'); }); })
 .then(function (d) { del('v.mp4'); del('mix.wav'); del('final.mp4'); if (S.url) { URL.revokeObjectURL(S.url); } var blob = new Blob([d.buffer], { type: 'video/mp4' }); S.url = URL.createObjectURL(blob); S.job = job; S.busy = false; $('#rxGo').disabled = false; $('#rxWork').hidden = true;
  var nm = (S.file.name.replace(/\.[^.]+$/, '') || 'reel') + '-' + job.st.id + '.mp4', o = $('#rxOut'); o.hidden = false;
  $('#rxVid').src = S.url; $('#rxDl').href = S.url; $('#rxDl').setAttribute('download', nm); $('#rxSize').textContent = mb(blob.size) + ' · ' + Math.round(job.cut.T + job.layer.END) + ' s';
  var cutS = (S.v.duration - job.cut.T).toFixed(1); $('#rxSum').textContent = 'Style: ' + job.st.name + '. Cut ' + cutS + ' s of pauses and retakes. ' + job.plan.punch.length + ' punch-ins' + (job.st.fam === 'b' ? ', ' + job.plan.blocks.length + ' keyword blocks' : ', ' + job.plan.accent.length + ' accent words') + (job.layer.cards ? ', ' + job.layer.cards + ' pro cards' : '') + (job.layer.vfmt ? ', format: ' + job.layer.vfmt : '') + '.'; var cr = Object.keys((job.layer.pRef && job.layer.pRef.used) || {}).map(function (q) { return job.imgs && job.imgs[q] ? job.imgs[q].credit : ''; }).filter(Boolean); if (job.grade && job.grade.notes && job.grade.notes.length) { $('#rxSum').textContent += ' Colour for this video: ' + job.grade.notes.join(', ') + '.'; } if (cr.length) { $('#rxSum').textContent += ' Photo credits: ' + cr.join('; ') + '.'; }
  words(); o.scrollIntoView({ behavior: 'smooth', block: 'start' }); })
 .catch(function (e) { var m = String(e && e.message || e); if (/nocodec/.test(m)) { m = 'This browser cannot build videos. Please use Chrome on a computer or Android, or Safari on iPhone with iOS 17 or newer.'; } else if (/memory|OOM|allocation|RangeError/i.test(m)) { m = 'This video is too big for your device. Try a shorter clip (under 90 seconds) or use a computer.'; } else if (/^ff:/.test(m)) { m = 'The video engine stopped. Refresh the page and try again.'; } else if (/worker|slow/.test(m)) { m = 'The speech model could not run. Refresh the page and try again, ideally on Wi-Fi.'; } fail(m); }); }
function words() { var cutI = {}; S.removed.forEach(function (i) { cutI[i] = 1; }); $('#rxWords').innerHTML = S.words.map(function (w, i) { return '<button type="button" data-i="' + i + '" class="' + (cutI[i] ? 'off' : '') + '">' + esc(w.w) + '</button>'; }).join(' '); }
function syncControls() { var s = styleById(S.style), l = s.look, set = function (id, v) { var el = $('#' + id); if (el && v != null) { el.value = v; } }; set('rxMain', (l.main_color || '#ffffff').slice(0, 7)); set('rxHl', (l.hl || '#FFD60A').slice(0, 7)); set('rxOutl', (l.stroke || '#0B0B0F').slice(0, 7)); set('rxCapPos', l.cap_pos || 'chest'); set('rxEmph', l.emph || 'color'); set('rxCapBg', l.cap_bg || 'none'); var w = $('#rxTxt'); if (w) { w.hidden = s.fam !== 'b'; } }
function init() { styleCards();
 $('#rxStyles').addEventListener('click', function (e) { var b = e.target.closest('.rx-st'); if (!b) { return; } S.style = b.getAttribute('data-id'); styleCards(); syncControls(); refreshPreview(false); });
  ['rxMain', 'rxHl', 'rxOutl', 'rxCapPos', 'rxEmph', 'rxCapBg', 'rxGAuto', 'rxGStr', 'rxCap'].forEach(function (id) { var el = $('#' + id); if (el) { el.addEventListener('change', function () { refreshPreview(/^rxG/.test(id)); }); if (el.type === 'range') { el.addEventListener('input', function () { var o = $('#' + id + 'V'); if (o) { o.textContent = el.value + '%'; } }); } } }); syncControls();
 $('#rxFile').addEventListener('change', function () { var f = this.files[0]; if (!f) { return; } if (f.size > 600 * 1048576) { fail('Choose a video under 600 MB.'); return; } S.file = f; S.words = null; S.removed = []; S.stats = undefined; S.th = {}; S.gradedF = null; if (S.v) { try { S.v.remove(); } catch (e) {} S.v = null; } $('#rxPicked').innerHTML = '<div class="rx-got">✓ <b>' + esc(f.name) + '</b> <small>' + mb(f.size) + '</small></div>'; $('#rxGo').disabled = false; $('#rxErr').hidden = true; loadVid(f).then(function (v) { if (S.file === f) { S.v = v; S.th = {}; S.gradedF = null; FACEN = null; refreshPreview(true); S.facePromise = new Promise(function (r) { setTimeout(r, 25000); }); S.facePromise = Promise.race([findFace(v).then(function (fc) { if (S.v === v && fc) { S.gradedF = null; S.th = {}; refreshPreview(true); } }), S.facePromise]); } }).catch(function () {}); });
 $('#rxPh').addEventListener('change', function () { S.photos = []; var fs = [].slice.call(this.files, 0, 6); Promise.all(fs.map(function (f) { return new Promise(function (r) { var im = new Image(); im.onload = function () { r(im); }; im.onerror = function () { r(null); }; im.src = URL.createObjectURL(f); }); })).then(function (ims) { S.photos = ims.filter(Boolean); $('#rxPhN').textContent = S.photos.length ? S.photos.length + ' photo' + (S.photos.length > 1 ? 's' : '') + ' added' : ''; }); });
 $('#rxGo').addEventListener('click', make); $('#rxAgain').addEventListener('click', make);
 var fb = document.createElement('div'); fb.style.cssText = 'display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 10px'; fb.innerHTML = '<button type="button" id="rxFixM" class="v-go" style="padding:8px 14px;font-size:14px">✏️ Fix words</button><small class="v-muted" id="rxFixH">Tap a word to cut it out.</small>'; $('#rxWords').parentNode.insertBefore(fb, $('#rxWords'));
 $('#rxFixM').addEventListener('click', function () { S.fix = !S.fix; this.textContent = S.fix ? '✓ Done fixing' : '✏️ Fix words'; $('#rxFixH').textContent = S.fix ? 'Tap a word to correct its spelling. Then tap Re-edit.' : 'Tap a word to cut it out.'; });
 $('#rxWords').addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) { return; } if (S.fix) { var ii = +b.getAttribute('data-i'), nv = window.prompt('Correct this word:', S.words[ii].w); if (nv != null && nv.trim()) { S.words[ii].w = nv.trim().slice(0, 40); b.textContent = S.words[ii].w; } return; } var i = +b.getAttribute('data-i'), k = S.removed.indexOf(i); if (k >= 0) { S.removed.splice(k, 1); } else { S.removed.push(i); } b.classList.toggle('off'); });
 ['rxCap', 'rxZoom', 'rxSfx'].forEach(function (id) { var el = $('#' + id); el.addEventListener('input', function () { $('#' + id + 'V').textContent = el.value + '%'; }); }); }
if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
