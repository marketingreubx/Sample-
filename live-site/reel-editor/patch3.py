import re
D='/tmp/claude-0/-home-user-Sample-/dcbfdb8a-deea-534a-ad26-61641deae3be/scratchpad/live/'
src=open(D+'rx.stage2.js').read()
def R(a,b,cnt=1):
    global src
    if src.count(a)==0: a=re.sub(r'(^|\n)  ', r'\1 ', a)
    assert src.count(a)==cnt,(src.count(a),a[:90]); src=src.replace(a,b)

# 1. style cards: thumbnail of the member's own video + name only
a=src.index("function styleCards() {"); b=src.index("function loadVid(f)")
src=src[:a]+r"""function styleCards() { $('#rxStyles').innerHTML = STYLES.map(function (s) { var th = S.th && S.th[s.id];
  return '<button type="button" class="rx-st' + (s.id === S.style ? ' on' : '') + '" data-id="' + s.id + '" title="' + esc(s.desc) + '"><span class="th"' + (th ? ' style="background-image:url(' + th + ')"' : '') + '>' + (th ? '' : '<i>' + esc(s.name) + '</i>') + '</span><b>' + esc(s.name) + '</b></button>'; }).join(''); }
"""+src[b:]

# 2. thumbnails: render each style on the member's own graded frame
a=src.index("/* ---------------- style preview on the member's own video ---------------- */"); b=src.index("function renderVideo(v, job, onp) {")
src=src[:a]+r"""/* ---------------- every style card shows the member's own video in that style ---------------- */
var PVW = 'Number one, posting without a hook. Most estheticians post every single day and still get no bookings. Fixing this took six months of work for one client.'.split(' ');
function drawItems(c, items, t) { items.forEach(function (it) { if (t < it.t0 || t >= it.t1) { return; } var p = itemAlpha(it, t); if (p.a <= 0.001) { return; } c.save(); c.globalAlpha = p.a; c.translate(it.x, it.y + p.dy); c.scale(p.sc, p.sc); it.draw(c, t); c.restore(); }); }
function thumbFor(sid, graded, ui) { var st = styleById(sid); st = ext(st, { look: ext(st.look, sid === S.style && st.fam === 'b' ? ui.look : {}), pro: proFor(sid, 1, true) });
 var words = [], t = 0.2; PVW.forEach(function (w) { var len = 0.22 + Math.min(w.length, 9) * 0.02; words.push({ w: w, s: t, e: t + len }); t += len + 0.04; }); var T = t + 0.5, plan = autoplan(words, st, []);
 var cut = { segs: [{ s: 0, e: T, o: 0 }], T: T, words: words.map(function (x, i) { return { i: i, w: x.w, o: x.s, oe: x.e }; }), keep: words.map(function (x, i) { return i; }) }, shots = [[0, T, 'W']], layer = buildLayer(cut, plan, st, shots, []);
 var hd = plan.headers && plan.headers[0], bl = plan.blocks && plan.blocks[0], tt = hd ? cut.words[hd.w[1]].o - 0.1 : (bl ? cut.words[bl.w[1]].o + 0.6 : cut.words[3].o + 0.3);
 var big = document.createElement('canvas'); big.width = W; big.height = H; var bc = big.getContext('2d'); bc.drawImage(graded, 0, 0); drawItems(bc, layer.items, tt);
 var sm = document.createElement('canvas'); sm.width = 360; sm.height = 640; sm.getContext('2d').drawImage(big, 0, 0, 360, 640); return sm.toDataURL('image/jpeg', 0.82); }
var pvBusy = false, pvAgain = null;
function refreshPreview(all) { if (!S.v) { return; } if (pvBusy) { pvAgain = pvAgain || all; if (all) { pvAgain = true; } return; } pvBusy = true; var v = S.v; S.th = S.th || {};
 loadFonts().then(function () { return S.stats !== undefined ? null : measureStats(v).then(function (s) { S.stats = s; }); }).then(function () { return all || !S.gradedF ? seekV(v, v.duration * 0.35) : null; }).then(function () {
  var ui = uiVals(), g = S.gl || (S.gl = makeGL(W, H)); if (all || !S.gradedF) { var gr = buildGrade(S.stats, ui.grade), gc = document.createElement('canvas'); gc.width = W; gc.height = H; var x = gc.getContext('2d'); if (g) { g.draw(v, cropRect(v.videoWidth, v.videoHeight, 1.0, 0.42), gr); x.drawImage(g.cv, 0, 0); } else { x.drawImage(v, 0, 0, W, H); } S.gradedF = gc; S.gradeNotes = gr.notes; }
  var ids = all ? STYLES.map(function (s) { return s.id; }) : [S.style], k = 0;
  function nx() { if (k >= ids.length) { return Promise.resolve(); } var id = ids[k++]; try { S.th[id] = thumbFor(id, S.gradedF, ui); var el = $('.rx-st[data-id="' + id + '"] .th'); if (el) { el.style.backgroundImage = 'url(' + S.th[id] + ')'; el.innerHTML = ''; } } catch (e) {} return tick().then(nx); }
  return nx(); }).catch(function () {}).then(function () { pvBusy = false; var a = pvAgain; pvAgain = null; if (a) { refreshPreview(a === true); } }); }
"""+src[b:]

# 3. callers: video loaded -> all styles; grade change -> all; other controls -> selected style only
R("loadVid(f).then(function (v) { if (S.file === f) { S.v = v; refreshPreview(); } })","loadVid(f).then(function (v) { if (S.file === f) { S.v = v; S.th = {}; S.gradedF = null; refreshPreview(true); } })")
R("syncControls(); refreshPreview(); });\n","syncControls(); refreshPreview(false); });\n")
R("if (el) { el.addEventListener('change', refreshPreview);","if (el) { el.addEventListener('change', function () { refreshPreview(/^rxG/.test(id)); });")
R("S.file = f; S.words = null; S.removed = []; S.stats = undefined;","S.file = f; S.words = null; S.removed = []; S.stats = undefined; S.th = {}; S.gradedF = null;")
open(D+'rx.stage3.js','w').write(src); print('ok',len(src))
