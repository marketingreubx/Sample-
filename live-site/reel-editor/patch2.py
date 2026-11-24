import re
D='/tmp/claude-0/-home-user-Sample-/dcbfdb8a-deea-534a-ad26-61641deae3be/scratchpad/live/'
src=open(D+'rx.stage1.js').read()
def R(a,b,cnt=1):
    global src
    if src.count(a)==0:
        a=re.sub(r'(^|\n)  ', r'\1 ', a)
    assert src.count(a)==cnt, (src.count(a), a[:90])
    src=src.replace(a,b)

# fonts: Inter 800 (static file on the site)
R("var FONTS = [['Inter', 'fonts/inter600.woff2', '600', 'normal'],","var FONTS = [['Inter', 'fonts/inter600.woff2', '400 699', 'normal'], ['Inter', 'fonts/inter.woff2', '700 900', 'normal'],")

# ---------- adaptive grade (grade.py ported) ----------
GR = r"""
/* ---------------- colour grade: the owner's base grade, adapted to each video (port of grade.py) ---------------- */
var GBASE = { brightness: 2, contrast: 4, saturation: 6, brilliance: 3.5, highlights: -13, shadows: -23, whites: -7.5, hue: 9, sharpen: 50, clarity: 7 };
function seekV(v, t) { return new Promise(function (r) { var d = false; function ok() { if (d) { return; } d = true; v.removeEventListener('seeked', ok); r(); } v.addEventListener('seeked', ok); v.currentTime = t; setTimeout(ok, 4000); }); }
function measureStats(v) { var n = 6, k = 0, cv = document.createElement('canvas'); cv.width = 72; cv.height = 128; var cx = cv.getContext('2d', { willReadFrequently: true }), A = { y: 0, lo: 0, hi: 0, s: 0 };
 function one() { if (k >= n) { return Promise.resolve({ y: A.y / n, lo: A.lo / n, hi: A.hi / n, s: A.s / n }); } var t = v.duration * (k + 0.5) / n; k++;
  return seekV(v, t).then(function () { cx.drawImage(v, 0, 0, 72, 128); var d = cx.getImageData(0, 0, 72, 128).data, ls = [], sm = 0, i; for (i = 0; i < d.length; i += 4) { var r = d[i], g = d[i + 1], b = d[i + 2]; ls.push(0.299 * r + 0.587 * g + 0.114 * b); var u = -0.169 * r - 0.331 * g + 0.5 * b, w = 0.5 * r - 0.419 * g - 0.081 * b; sm += Math.sqrt(u * u + w * w); }
   ls.sort(function (a, b) { return a - b; }); var m = ls.length; A.y += ls.reduce(function (a, b) { return a + b; }, 0) / m; A.lo += ls[Math.floor(m * 0.1)]; A.hi += ls[Math.floor(m * 0.9)]; A.s += sm / m; return one(); }); }
 return one().catch(function () { return null; }); }
function buildGrade(stats, ui) { var s = {}, k, notes = []; for (k in GBASE) { s[k] = GBASE[k]; }
 if (ui.auto && stats) { var y = stats.y, lo = stats.lo, hi = stats.hi, sat = stats.s;
  if (y < 50) { s.brightness += Math.min((60 - y) * 0.5, 12); s.shadows *= 0.4; notes.push('very dark clip: lifted a little, mood kept'); } else if (y > 175) { s.brightness -= Math.min((y - 170) * 0.35, 10); s.highlights -= 8; notes.push('very bright clip: exposure pulled down'); }
  if (lo < 18) { s.shadows *= 0.5; } else if (lo > 48) { s.shadows *= 1.25; s.contrast += 4; }
  if (hi > 238) { s.highlights -= 7; s.whites -= 4; } else if (hi < 175) { s.whites = Math.abs(s.whites) * 0.6; s.highlights *= 0.5; }
  if (sat > 22) { s.saturation -= Math.min((sat - 18) * 0.6, 14); notes.push('colourful phone footage toned down'); } else if (sat > 14) { s.saturation *= 0.3; } else if (sat < 4) { s.saturation += 4; } }
 var I = clamp(ui.strength, 0, 1.5); for (k in s) { s[k] *= I; } s.notes = notes; return s; }
"""
R("/* ---------------- colour grade on the GPU (eq + colorbalance of the engine) ---------------- */", GR + "/* ---------------- colour grade on the GPU ---------------- */")
OLDFS = "var fs = 'precision mediump float;varying vec2 v;uniform sampler2D t;uniform vec4 g;uniform vec2 cb;void main(){vec3 c=texture2D(t,v).rgb;c=(c-.5)*g.y+.5+g.x;float l=dot(c,vec3(.299,.587,.114));c=mix(vec3(l),c,g.z);c=pow(max(c,0.),vec3(1./g.w));float m=1.-abs(l-.5)*2.;c.r+=cb.x*m;c.b+=cb.y*m;gl_FragColor=vec4(clamp(c,0.,1.),1.);}';"
NEWFS = r"""var fs = 'precision mediump float;varying vec2 v;uniform sampler2D t;uniform vec4 g1;uniform vec4 g2;uniform vec2 g3;uniform vec2 px;vec3 S(vec2 o){return texture2D(t,v+o*px).rgb;}void main(){vec3 c=S(vec2(0.));vec3 b=(S(vec2(1.,0.))+S(vec2(-1.,0.))+S(vec2(0.,1.))+S(vec2(0.,-1.)))*.25;c+=(c-b)*g3.x*1.3;vec3 b2=(S(vec2(5.,5.))+S(vec2(-5.,5.))+S(vec2(5.,-5.))+S(vec2(-5.,-5.)))*.25;c+=(c-b2)*g3.y*.9;c+=g1.x*.2;c=(c-.5)*g1.y+.5;float l=dot(c,vec3(.299,.587,.114));c+=g2.x*.22*(1.-smoothstep(0.,.5,l));c+=g2.y*.22*smoothstep(.45,1.,l);c+=g2.z*.14*smoothstep(.8,1.,l);c+=g2.w*.2*max(0.,1.-abs(l-.45)*2.);l=dot(c,vec3(.299,.587,.114));float sat=max(c.r,max(c.g,c.b))-min(c.r,min(c.g,c.b));c=mix(vec3(l),c,g1.z*(1.+(1.-sat)*.2));float h=g1.w,cs=cos(h),sn=sin(h);float Y=dot(c,vec3(.299,.587,.114)),I=dot(c,vec3(.596,-.274,-.322)),Q=dot(c,vec3(.211,-.523,.312));float I2=I*cs-Q*sn,Q2=I*sn+Q*cs;c=vec3(Y+.956*I2+.621*Q2,Y-.272*I2-.647*Q2,Y-1.106*I2+1.703*Q2);gl_FragColor=vec4(clamp(c,0.,1.),1.);}';"""
R(OLDFS, NEWFS)
R("var uR = gl.getUniformLocation(pr, 'r'), uG = gl.getUniformLocation(pr, 'g'), uC = gl.getUniformLocation(pr, 'cb');","var uR = gl.getUniformLocation(pr, 'r'), uG1 = gl.getUniformLocation(pr, 'g1'), uG2 = gl.getUniformLocation(pr, 'g2'), uG3 = gl.getUniformLocation(pr, 'g3'), uPx = gl.getUniformLocation(pr, 'px');")
R("gl.uniform4f(uG, gr.b, gr.c, gr.s, gr.g); gl.uniform2f(uC, gr.rm, gr.bm);","var vw = src.videoWidth || src.width || 1080, vh = src.videoHeight || src.height || 1920; gl.uniform4f(uG1, gr.brightness / 100, 1 + gr.contrast / 100 * 0.6, 1 + gr.saturation / 100 * 0.6, gr.hue * 0.2 * Math.PI / 180); gl.uniform4f(uG2, gr.shadows / 100, gr.highlights / 100, gr.whites / 100, gr.brilliance / 100); gl.uniform2f(uG3, gr.sharpen / 100 * 0.6, gr.clarity / 100 * 1.2); gl.uniform2f(uPx, 1 / vw, 1 / vh);")
R("var gr = GRADE[job.st.fam], anchor","var gr = job.grade, anchor")
R("  var job = { st: styleById(S.style) }, extra","  var job = { st: styleById(S.style) }, extra") if False else None

# ---------- user controls read in make(); stats measured once ----------
R("var job = { st: styleById(S.style) }, extra = { cap: +$('#rxCap').value / 100,","var job = { st: styleById(S.style) }, ui = uiVals(), extra = { cap: +$('#rxCap').value / 100,")
R("job.st = ext(job.st, { look: ext(job.st.look, { cap_size: job.st.look.cap_size * extra.cap, zoom: job.st.look.zoom * extra.zoom, sfx: job.st.look.sfx * extra.sfx }) });",
  "job.st = ext(job.st, { look: ext(job.st.look, { cap_size: job.st.look.cap_size * extra.cap, zoom: job.st.look.zoom * extra.zoom, sfx: job.st.look.sfx * extra.sfx }) }); if (job.st.fam === 'b') { job.st.look = ext(job.st.look, ui.look); }")
R("prepare().then(function () {\n   show('Planning the edit like Reubx...', 32, '');","prepare().then(function () { return S.stats !== undefined ? null : measureStats(S.v).then(function (s) { S.stats = s; }); }).then(function () { job.grade = buildGrade(S.stats, ui.grade);\n   show('Planning the edit like Reubx...', 32, '');")
R("function make() {","""function uiVals() { var g = function (id, d) { var el = $('#' + id); return el ? el.value : d; }, ck = function (id, d) { var el = $('#' + id); return el ? el.checked : d; };
 return { grade: { auto: ck('rxGAuto', true), strength: +g('rxGStr', 100) / 100 }, look: { main_color: g('rxMain', '#ffffff'), hl: g('rxHl', '#FFD60A'), stroke: g('rxOut', '#0B0B0F'), cap_pos: g('rxCapPos', 'chest'), emph: g('rxEmph', 'color'), cap_bg: g('rxCapBg', 'none') } }; }
function make() {""")
R("S.file = f; S.words = null; S.removed = []; if (S.v) { try { S.v.remove(); } catch (e) {} S.v = null; }","S.file = f; S.words = null; S.removed = []; S.stats = undefined; if (S.v) { try { S.v.remove(); } catch (e) {} S.v = null; }")
R("$('#rxGo').disabled = false; $('#rxErr').hidden = true; });\n","$('#rxGo').disabled = false; $('#rxErr').hidden = true; loadVid(f).then(function (v) { if (S.file === f) { S.v = v; refreshPreview(); } }).catch(function () {}); });\n")
R("Promise.all([loadVid(S.file), loadFonts(), loadFF()])","Promise.all([S.v ? Promise.resolve(S.v) : loadVid(S.file), loadFonts(), loadFF()])")

# ---------- live style preview (same drawing code as the render) ----------
PV = r"""
/* ---------------- style preview on the member's own video ---------------- */
var PVW = 'Number one, posting without a hook. Most estheticians post every single day and still get no bookings. Fixing this took six months of work for one client.'.split(' ');
function drawItems(c, items, t) { items.forEach(function (it) { if (t < it.t0 || t >= it.t1) { return; } var p = itemAlpha(it, t); if (p.a <= 0.001) { return; } c.save(); c.globalAlpha = p.a; c.translate(it.x, it.y + p.dy); c.scale(p.sc, p.sc); it.draw(c, t); c.restore(); }); }
var pvBusy = false, pvAgain = false;
function refreshPreview() { var cv = $('#rxPrev'); if (!cv || !S.v) { return; } if (pvBusy) { pvAgain = true; return; } pvBusy = true; var v = S.v;
 loadFonts().then(function () { return S.stats !== undefined ? null : measureStats(v).then(function (s) { S.stats = s; }); }).then(function () { return seekV(v, v.duration * 0.35); }).then(function () {
  var st = styleById(S.style), ui = uiVals(); st = ext(st, { look: ext(st.look, st.fam === 'b' ? ui.look : {}), pro: proFor(st.id, 1, true) }); var gr = buildGrade(S.stats, ui.grade);
  var words = [], t = 0.2; PVW.forEach(function (w, i) { var len = 0.22 + Math.min(w.length, 9) * 0.02; words.push({ w: w, s: t, e: t + len }); t += len + 0.04; }); var T = t + 0.5;
  var plan = autoplan(words, st, []), cut = { segs: [{ s: 0, e: T, o: 0 }], T: T, words: words.map(function (x, i) { return { i: i, w: x.w, o: x.s, oe: x.e }; }), keep: words.map(function (x, i) { return i; }) }, shots = [[0, T, 'W']], layer = buildLayer(cut, plan, st, shots, []);
  var times = [], hd = plan.headers && plan.headers[0]; if (hd) { times.push(cut.words[hd.w[1]].o - 0.1); } var bl = plan.blocks && plan.blocks[0]; if (bl) { times.push(cut.words[bl.w[1]].o + 0.55); } else { times.push(cut.words[6].o + 0.1); } var wc = plan.wcards && plan.wcards[0]; if (wc) { times.push(cut.words[wc.w[0]].o + 0.7); } else { times.push(cut.words[14].o); }
  var g = makeGL(W, H), pw = 360, ph = 640; cv.width = pw * times.length; cv.height = ph; var cx = cv.getContext('2d'), big = document.createElement('canvas'); big.width = W; big.height = H; var bc = big.getContext('2d');
  times.forEach(function (tt, k) { if (g) { g.draw(v, cropRect(v.videoWidth, v.videoHeight, 1.0, st.fam === 'a' ? 0.5 : 0.42), gr); bc.setTransform(1, 0, 0, 1, 0, 0); bc.drawImage(g.cv, 0, 0); } else { bc.drawImage(v, 0, 0, W, H); } drawItems(bc, layer.items, tt); cx.drawImage(big, k * pw, 0, pw, ph); });
  var nt = $('#rxPrevN'); if (nt) { nt.textContent = 'This is how your video will look in "' + st.name + '"' + (gr.notes.length ? ' (colour: ' + gr.notes.join(', ') + ')' : '') + '.'; } $('#rxPrevW').hidden = false; }).catch(function () {}).then(function () { pvBusy = false; if (pvAgain) { pvAgain = false; refreshPreview(); } }); }
"""
R("function renderVideo(v, job, onp) {", PV + "function renderVideo(v, job, onp) {")
R("$('#rxStyles').addEventListener('click', function (e) { var b = e.target.closest('.rx-st'); if (!b) { return; } S.style = b.getAttribute('data-id'); styleCards(); });",
  "$('#rxStyles').addEventListener('click', function (e) { var b = e.target.closest('.rx-st'); if (!b) { return; } S.style = b.getAttribute('data-id'); styleCards(); syncControls(); refreshPreview(); });\n  ['rxMain', 'rxHl', 'rxOut', 'rxCapPos', 'rxEmph', 'rxCapBg', 'rxGAuto', 'rxGStr', 'rxCap'].forEach(function (id) { var el = $('#' + id); if (el) { el.addEventListener('change', refreshPreview); if (el.type === 'range') { el.addEventListener('input', function () { var o = $('#' + id + 'V'); if (o) { o.textContent = el.value + '%'; } }); } } }); syncControls();")
R("function init() { styleCards();","""function syncControls() { var s = styleById(S.style), l = s.look, set = function (id, v) { var el = $('#' + id); if (el && v != null) { el.value = v; } }; set('rxMain', (l.main_color || '#ffffff').slice(0, 7)); set('rxHl', (l.hl || '#FFD60A').slice(0, 7)); set('rxOut', (l.stroke || '#0B0B0F').slice(0, 7)); set('rxCapPos', l.cap_pos || 'chest'); set('rxEmph', l.emph || 'color'); set('rxCapBg', l.cap_bg || 'none'); var w = $('#rxTxt'); if (w) { w.hidden = s.fam !== 'b'; } }
function init() { styleCards();""")
open(D+'rx.stage2.js','w').write(src); print('ok',len(src))
