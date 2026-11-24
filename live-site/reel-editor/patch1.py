import re,sys
src=open('/tmp/claude-0/-home-user-Sample-/dcbfdb8a-deea-534a-ad26-61641deae3be/scratchpad/live/rx.orig.js').read()
def R(a,b,cnt=1):
    global src
    if src.count(a)==0:
        a=re.sub(r'(^|\n)  ', r'\1 ', a)
        a=re.sub(r'(^|\n)    ', r'\1 ', a) if src.count(a)==0 else a
    assert src.count(a)==cnt, (src.count(a), a[:80])
    src=src.replace(a,b)

# ---------- 1. looks: solid high-contrast text, new look fields ----------
R("var B_LOOK = { cap_size: 70, font_main: 'Montserrat', font_lead: 'Poppins', font_cap: 'Poppins', grad: ['#ffffff', '#ffffff', '#d3ebff', '#8fcaff'], accent: '#2F6BFF', accent2: '#9cc2ff', sfx: 1.0, zoom: 0.1 };",
"var B_LOOK = { cap_size: 70, font_main: 'Montserrat', font_lead: 'Poppins', font_cap: 'Poppins', main_color: '#ffffff', hl: '#FFD60A', stroke: '#0B0B0F', outline: 0.35, cap_w: 800, cap_pos: 'chest', emph: 'color', cap_bg: 'none', font_emph: 'Instrument Serif', accent: '#FFD60A', accent2: '#FFE680', sfx: 1.0, zoom: 0.08 };\nvar SOLID = { main_color: '#ffffff', hl: '#FFD60A', stroke: '#0B0B0F' };")
R("look: ext(B_LOOK, { font_main: 'Bricolage Grotesque', font_lead: 'Manrope', font_cap: 'Manrope', grad: ['#7aa2ff', '#b48cff', '#ff9a62'], gradH: true, accent: '#8b7cff', accent2: '#c9b6ff', sfx: 0.9, zoom: 0.08 }) },",
"look: ext(B_LOOK, { font_main: 'Bricolage Grotesque', font_lead: 'Manrope', font_cap: 'Manrope', main_color: '#ffffff', hl: '#FF5A1F', accent: '#FF5A1F', accent2: '#FFC4A8', sfx: 0.9, zoom: 0.08 }) },")
R("desc: 'Clean premium look with blue-purple-orange gradient words.'","desc: 'Clean premium look: white words with an orange highlight.'")
R("desc: 'Big bold keyword stacks with a light-blue glow and hits.'","desc: 'Big bold white keyword stacks with a yellow highlight and hits.'")

# ---------- 2. reference styles (from the 12-reel study) ----------
REF = r"""
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
 { id: 'ref-cinematic', name: 'Cinematic', who: 'kreatize_ \"definition of success\"', fam: 'b', ref: true, ed: true, desc: 'Dark, desaturated, gold serif keywords.', look: ext(B_LOOK, { font_main: 'Instrument Serif', font_lead: 'Inter', font_cap: 'Inter', cap_size: 54, main_color: '#E9C46A', hl: '#E9C46A', accent: '#E9C46A', accent2: '#FFF3C4', outline: 0, sfx: 0.9, zoom: 0.05 }) },
"""
R("var STYLES = [\n", "var STYLES = [" + REF)
R(" 'edita-blueprint': { cut: 5, punch: 4, cards: 'ui', recap: 'The Blueprint', moves: 'Point cards plus a Blueprint recap card' }\n};",
""" 'edita-blueprint': { cut: 5, punch: 4, cards: 'ui', recap: 'The Blueprint', moves: 'Point cards plus a Blueprint recap card' },
 'ref-chloe': { cut: 3, punch: 4, hdr: true, wcards: true, pace: 'tight', moves: 'Numbered headers, word cards, cut every 3 s' },
 'ref-kallaway': { cut: 6, punch: 5, hdr: true, wcards: true, pace: 'tight', moves: 'Red headers, word cards' },
 'ref-social': { cut: 60, punch: 99, hdr: true, wcards: true, pace: 'tight', moves: 'Steady shot, box headers' },
 'ref-wolf': { cut: 7, punch: 4, hdr: true, wcards: true, pace: 'tight', moves: 'Numbered headers, gold keywords' },
 'ref-tumi': { cut: 4, punch: 4, wcards: true, pace: 'tight', moves: 'Paper word cards, gold stacks' },
 'ref-millie': { cut: 90, punch: 99, hdr: true, pace: 'tight', moves: 'Locked shot, script headers' },
 'ref-deshna': { cut: 4, punch: 3, wcards: true, pace: 'tight', moves: 'Wide/tight cuts, red box words' },
 'ref-zach': { cut: 8, punch: 6, wcards: true, pace: 'tight', moves: 'Caption pill, calm cuts' },
 'ref-sky': { cut: 90, punch: 99, pace: 'tight', moves: 'Locked shot, green key words' },
 'ref-adam': { cut: 7, punch: 5, hdr: true, wcards: true, pace: 'tight', moves: 'Numbered steps' },
 'ref-cinematic': { cut: 5, punch: 4, wcards: true, pace: 'tight', moves: 'Dark cinematic, gold serif' }
};""")
R("if (!on) { ['cards',","if (!on) { ['hdr', 'wcards', 'cards',")

# ---------- 3. cut pace from the references: tight pauses ----------
R("var PAD_IN = 0.22, PAD_OUT = 0.16, MAXGAP = 0.45;","var tight = plan.tight, PAD_IN = tight ? 0.16 : 0.22, PAD_OUT = tight ? 0.12 : 0.16, MAXGAP = tight ? 0.32 : 0.45;")
R("plan.remove = retakeHints(words);","plan.tight = !!(st.pro && st.pro.pace === 'tight'); plan.remove = retakeHints(words);")

# ---------- 4. headers + word cards detection ----------
HDR = r"""
  /* numbered headers ('Number one, ...', 'Step two', 'The third mistake') and number+unit word cards (from the 12-reel study) */
  var NW2 = { one: 1, two: 2, three: 3, four: 4, five: 5, six: 6, seven: 7, eight: 8, nine: 9, ten: 10, first: 1, second: 2, third: 3, fourth: 4, fifth: 5, sixth: 6, seventh: 7, eighth: 8, ninth: 9, tenth: 10 }, LAB = { step: 1, mistake: 1, tip: 1, rule: 1, lesson: 1, reason: 1, way: 1, hook: 1, sign: 1, secret: 1, habit: 1 }, UNITS = { months: 1, month: 1, days: 1, day: 1, weeks: 1, week: 1, years: 1, year: 1, hours: 1, minutes: 1, million: 1, thousand: 1, followers: 1, views: 1, clients: 1 };
  var numOf = function (w) { w = cleanW(w).toLowerCase(); return NW2[w] || (/^\d+$/.test(w) && +w < 50 ? +w : 0); };
  plan.headers = []; plan.wcards = [];
  if (P.hdr) { sents.forEach(function (s) { var ws = s.map(function (i) { return cleanW(words[i].w).toLowerCase(); }), n = 0, lab = '', start = 0;
    for (var k = 0; k < Math.min(4, ws.length - 1); k++) {
     if (ws[k] === 'number' && numOf(ws[k + 1])) { n = numOf(ws[k + 1]); start = k + 2; }
     else if (LAB[ws[k]] && numOf(ws[k + 1])) { n = numOf(ws[k + 1]); lab = ws[k]; start = k + 2; }
     else if (/^(first|second|third|fourth|fifth)$/.test(ws[k]) && LAB[ws[k + 1]]) { n = NW2[ws[k]]; lab = ws[k + 1]; start = k + 2; }
     else if (k === 0 && numOf(ws[0]) && /[,.]$/.test(words[s[0]].w)) { n = numOf(ws[0]); start = 1; }
     if (n) { break; } }
    if (!n) { return; } var rest = []; for (var j = start; j < s.length; j++) { var cw = cleanW(words[s[j]].w); if (!cw) { continue; } if (!rest.length && /^(is|are|the|a|an|to)$/i.test(cw)) { continue; } rest.push(cw); if (rest.length >= 4 || /[,.?!]$/.test(words[s[j]].w)) { break; } }
    while (rest.length && STOP[rest[rest.length - 1].toLowerCase()]) { rest.pop(); } if (!rest.length) { return; }
    plan.headers.push({ w: [s[0], s[s.length - 1]], n: n, lab: lab, text: rest.join(' ') }); }); }
  if (P.wcards) { var lastW = -99; sents.forEach(function (s) { for (var k = 0; k + 1 < s.length; k++) { var i = s[k], nn = numOf(words[i].w) || (/^[\d,.]+$/.test(cleanW(words[i].w)) ? cleanW(words[i].w) : 0), u = cleanW(words[s[k + 1]].w).toLowerCase();
    if (nn && UNITS[u] && words[i].s - lastW >= 14 && words[i].s > 2 && !plan.headers.some(function (h) { return h.w[0] === s[0]; })) { plan.wcards.push({ w: [i, s[k + 1]], text: nn + ' ' + u.charAt(0).toUpperCase() + u.slice(1), lead: s.slice(Math.max(0, k - 3), k).map(function (j) { return cleanW(words[j].w).toLowerCase(); }).join(' ') }); lastW = words[i].s; break; } } }); }
"""
R(" var pr = plan.pro = { items: [], nums: [], flips: [], chats: [], story: [], quote: null };", HDR + " var pr = plan.pro = { items: [], nums: [], flips: [], chats: [], story: [], quote: null };")
R("  var P = st.pro || {}, last = 0; sents.slice(1)","  var last = 0; sents.slice(1)")
R("  var fam = st.fam, plan = { remove: [],","  var P = st.pro || {}, fam = st.fam, plan = { remove: [],")
# STOP map exists? ensure it is referenced as STOP in score()
assert "STOP[w]" in src

# ---------- 5. text drawing: solid colour + outline + caption options ----------
R("function fillMain(c, txt, sz, w) { if (ed) { return acc; } var g =","function fillMain(c, txt, sz, w) { if (ed) { return look.main_color && look.main_color !== '#ffffff' ? look.main_color : acc; } if (look.main_color && !look.grad) { return look.main_color; } var g =")
R("c.fillStyle = fillMain(c, big, sz, bw); c.fillText(big, 0, 0); c.restore(); } } });","c.fillStyle = fillMain(c, big, sz, bw); outline(c, big, 0, 0, sz); c.fillText(big, 0, 0); c.restore(); } } });")
R("c.fillStyle = fillMain(c, v, sz, c.measureText(v).width); c.fillText(v, 0, lsz * 0.3); } } });","c.fillStyle = fillMain(c, v, sz, c.measureText(v).width); outline(c, v, 0, lsz * 0.3, sz); c.fillText(v, 0, lsz * 0.3); } } });")
R("function scrim(c, w, h, a) {","function outline(c, txt, x, y, sz) { var lk = OUTL; if (!lk || !(lk.outline > 0)) { return; } c.save(); c.lineJoin = 'round'; c.lineWidth = Math.max(2, sz * 0.07 * lk.outline * 2); c.strokeStyle = lk.stroke || '#000'; c.strokeText(txt, x, y); c.restore(); }\nvar OUTL = null;\nfunction scrim(c, w, h, a) {")
R("  var L = [], EV = [], look = st.look, WO = {}, T = cut.T;","  var L = [], EV = [], look = st.look, WO = {}, T = cut.T; OUTL = look;")
R("function capY(t) { return shotAt(t)[2] === 'T' ? 1500 : 1420; }","function capY(t) { if (look.cap_pos === 'top') { return 330; } if (look.cap_pos === 'center') { return 1000; } return shotAt(t)[2] === 'T' ? 1500 : 1420; }")
# b-family caption: weight, emphasis modes, pill
OLDCAP = "add({ t0: a, t1: b, kind: 'pop', dur: 260, out: 'blur', outd: 60, x: W / 2, y: capY(a + 0.05) + 40, draw: function (c) { var sz = Math.round(66 * cs), f = function (bold) { return (bold ? '800 ' : '600 ') + sz + 'px \"' + look.font_cap + '\", sans-serif'; }; c.textBaseline = 'middle'; var tot = 0, gap = 0, ws = parts.map(function (p) { c.font = f(p.k); var m = c.measureText(p.t).width; tot += m; return m; }); c.font = f(false); gap = c.measureText(' ').width * 0.75; tot += gap * (parts.length - 1);\n     scrim(c, tot + 110, 135, 0.28); var xx = -tot / 2; c.textAlign = 'left'; parts.forEach(function (p, j) { c.font = f(p.k); shadowText(c, p.t, xx, 0, p.k ? acc2 : '#fff', 14, 2); xx += ws[j] + gap; }); } }); });"
NEWCAP = r"""add({ t0: a, t1: b, kind: 'pop', dur: 260, out: 'blur', outd: 60, x: W / 2, y: capY(a + 0.05) + (look.cap_pos === 'chest' ? 40 : 0), draw: function (c) { var sz = Math.round(66 * cs), em = look.emph || 'color', HL = look.hl || acc2, cw = look.cap_w || 700;
     var f = function (k) { if (k && em === 'script') { return 'italic 400 ' + Math.round(sz * 1.3) + 'px \"' + (look.font_emph || 'Instrument Serif') + '\", serif'; } return (k ? Math.max(cw, 800) : cw) + ' ' + sz + 'px \"' + look.font_cap + '\", sans-serif'; };
     c.textBaseline = 'middle'; var tot = 0, gap = 0, pad = em === 'box' ? 22 : 0, ws = parts.map(function (p) { c.font = f(p.k); var m = c.measureText(p.t).width + (p.k && em === 'box' ? pad * 2 : 0); tot += m; return m; }); c.font = f(false); gap = c.measureText(' ').width * 0.75; tot += gap * (parts.length - 1);
     if (look.cap_bg === 'pill') { c.save(); c.fillStyle = 'rgba(18,18,22,0.76)'; rrect(c, -tot / 2 - 28, -sz * 0.78, tot + 56, sz * 1.56, 22); c.fill(); c.restore(); } else { scrim(c, tot + 110, 135, 0.28); }
     var xx = -tot / 2; c.textAlign = 'left'; parts.forEach(function (p, j) { c.font = f(p.k); var col = p.k ? (em === 'script' ? '#fff' : (em === 'box' ? '#fff' : HL)) : '#fff';
      if (p.k && em === 'box') { c.save(); c.fillStyle = acc; rrect(c, xx, -sz * 0.62, ws[j], sz * 1.24, 8); c.fill(); c.restore(); shadowText(c, p.t, xx + pad, 0, col, 4, 1); }
      else { if (look.cap_bg !== 'pill') { outline(c, p.t, xx, 0, sz * 0.8); } shadowText(c, p.t, xx, p.k && em === 'script' ? 4 : 0, col, 14, 2); } xx += ws[j] + gap; }); } }); });"""
R(OLDCAP, NEWCAP)

# ---------- 6. headers + word cards drawing ----------
DRAW = r"""
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
"""
R("  plan.punch.forEach(function (i) { if (WO[i]) { EV.push([WO[i].o - 0.12, 'camera', 0.3, 0.35 * sfxG]); } });", DRAW + "  plan.punch.forEach(function (i) { if (WO[i]) { EV.push([WO[i].o - 0.12, 'camera', 0.3, 0.35 * sfxG]); } });")
open('/tmp/claude-0/-home-user-Sample-/dcbfdb8a-deea-534a-ad26-61641deae3be/scratchpad/live/rx.stage1.js','w').write(src)
print("ok",len(src))
