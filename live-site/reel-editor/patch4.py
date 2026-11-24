import re
D='/tmp/claude-0/-home-user-Sample-/dcbfdb8a-deea-534a-ad26-61641deae3be/scratchpad/live/'
src=open(D+'rx.stage3.js').read()
def R(a,b,cnt=1):
    global src
    if src.count(a)==0: a=re.sub(r'(^|\n)  ', r'\1 ', a)
    assert src.count(a)==cnt,(src.count(a),a[:100]); src=src.replace(a,b)

# ---- device limits: longer videos, memory-safe ----
R("function ext(a, b) {","var IOS = /iP(hone|ad|od)/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1), MAXS = IOS ? 360 : 600;\nfunction ext(a, b) {")
R("if (S.v.duration > 185) { throw new Error('This video is ' + Math.round(S.v.duration) + ' seconds. The editor takes up to 3 minutes. Trim it and try again.'); }",
  "if (S.v.duration > MAXS) { throw new Error('This video is ' + Math.round(S.v.duration / 6) / 10 + ' minutes. ' + (IOS ? 'On iPhone the editor takes up to 6 minutes (10 minutes on a computer). Trim it and try again.' : 'The editor takes up to 10 minutes. Trim it and try again.')); }")
R("var t = setTimeout(function () { try { w.terminate(); } catch (e) {} rej(new Error('slow')); }, 900000);","var t = setTimeout(function () { try { w.terminate(); } catch (e) {} rej(new Error('slow')); }, Math.max(900000, a16.length / 16000 * 4000));")

# ---- bitrate scaled to length so long videos stay small in memory ----
R("enc.configure({ codec: codec, width: W, height: H, bitrate: 9e6, framerate: FPS, latencyMode: 'quality' });","enc.configure({ codec: codec, width: W, height: H, bitrate: Math.round(clamp(6.5e6 * Math.sqrt(60 / Math.max(TT, 60)), 3.2e6, 6.5e6)), framerate: FPS, latencyMode: 'quality' });")

# ---- GL contexts are released (iOS allows very few) ----
R("return { cv: cv, draw: function (src, rect, gr) {","return { cv: cv, lose: function () { try { var e = gl.getExtension('WEBGL_lose_context'); if (e) { e.loseContext(); } } catch (x) {} }, draw: function (src, rect, gr) {")
R("mux.finalize(); return new Uint8Array(mux.target.buffer); }); }\n","mux.finalize(); if (gl) { gl.lose(); } return new Uint8Array(mux.target.buffer); }); }\n",2) if False else None
src=src.replace("mux.finalize(); return new Uint8Array(mux.target.buffer);","mux.finalize(); if (gl) { gl.lose(); } return new Uint8Array(mux.target.buffer);")
assert src.count("gl.lose()")==2

# ---- preview thumbnails: small canvases, one reused context ----
a=src.index("function thumbFor(sid, graded, ui) {"); b=src.index("function renderVideo(v, job, onp) {")
src=src[:a]+r"""var TW = 360, TH = 640, TCV = null;
function thumbFor(sid, graded, ui) { var st = styleById(sid); st = ext(st, { look: ext(st.look, sid === S.style && st.fam === 'b' ? ui.look : {}), pro: proFor(sid, 1, true) });
 var words = [], t = 0.2; PVW.forEach(function (w) { var len = 0.22 + Math.min(w.length, 9) * 0.02; words.push({ w: w, s: t, e: t + len }); t += len + 0.04; }); var T = t + 0.5, plan = autoplan(words, st, []);
 var cut = { segs: [{ s: 0, e: T, o: 0 }], T: T, words: words.map(function (x, i) { return { i: i, w: x.w, o: x.s, oe: x.e }; }), keep: words.map(function (x, i) { return i; }) }, shots = [[0, T, 'W']], layer = buildLayer(cut, plan, st, shots, []);
 var hd = plan.headers && plan.headers[0], bl = plan.blocks && plan.blocks[0], tt = hd ? cut.words[hd.w[1]].o - 0.1 : (bl ? cut.words[bl.w[1]].o + 0.6 : cut.words[3].o + 0.3);
 if (!TCV) { TCV = document.createElement('canvas'); TCV.width = TW; TCV.height = TH; } var bc = TCV.getContext('2d'); bc.setTransform(1, 0, 0, 1, 0, 0); bc.globalAlpha = 1; bc.drawImage(graded, 0, 0, TW, TH); bc.setTransform(TW / W, 0, 0, TH / H, 0, 0); drawItems(bc, layer.items, tt); bc.setTransform(1, 0, 0, 1, 0, 0); return TCV.toDataURL('image/jpeg', 0.8); }
var pvBusy = false, pvAgain = null;
function refreshPreview(all) { if (!S.v) { return; } if (pvBusy) { pvAgain = pvAgain || all; if (all) { pvAgain = true; } return; } pvBusy = true; var v = S.v; S.th = S.th || {};
 loadFonts().then(function () { return S.stats !== undefined ? null : measureStats(v).then(function (s) { S.stats = s; }); }).then(function () { return all || !S.gradedF ? seekV(v, v.duration * 0.35) : null; }).then(function () {
  var ui = uiVals(); if (all || !S.gradedF) { var gr = buildGrade(S.stats, ui.grade), g = makeGL(TW, TH), gc = S.gradedF || document.createElement('canvas'); gc.width = TW; gc.height = TH; var x = gc.getContext('2d'); if (g) { g.draw(v, cropRect(v.videoWidth, v.videoHeight, 1.0, 0.42), gr); x.drawImage(g.cv, 0, 0); g.lose(); } else { x.drawImage(v, 0, 0, TW, TH); } S.gradedF = gc; S.gradeNotes = gr.notes; }
  var ids = all ? STYLES.map(function (s) { return s.id; }) : [S.style], k = 0;
  function nx() { if (k >= ids.length) { return Promise.resolve(); } var id = ids[k++]; try { S.th[id] = thumbFor(id, S.gradedF, ui); var el = $('.rx-st[data-id="' + id + '"] .th'); if (el) { el.style.backgroundImage = 'url(' + S.th[id] + ')'; el.innerHTML = ''; } } catch (e) {} return tick().then(nx); }
  return nx(); }).catch(function () {}).then(function () { pvBusy = false; var a = pvAgain; pvAgain = null; if (a) { refreshPreview(a === true); } }); }
"""+src[b:]

# ---- audio built in 60 s blocks (a third of the memory), files joined by ffmpeg's concat protocol ----
R("function renderSfx(events, total) { rs = 7; var n = Math.floor(SR * total), L = new Float32Array(n), R = new Float32Array(n);\n events.forEach(function (ev) { var fn = SFX[ev[1]]; if (!fn) { return; } var x = fn(ev[2] || undefined), s = Math.floor(Math.max(ev[0], 0) * SR); for (var i = 0; i < x[0].length && s + i < n; i++) { L[s + i] += x[0][i] * ev[3]; R[s + i] += x[1][i] * ev[3]; } });",
  "function renderSfx(events, total, off) { rs = 7; off = off || 0; var n = Math.ceil(SR * total - 1e-6), L = new Float32Array(n), R = new Float32Array(n);\n events.forEach(function (ev) { var fn = SFX[ev[1]]; if (!fn) { return; } var x = fn(ev[2] || undefined), s = Math.floor((off ? ev[0] - off : Math.max(ev[0], 0)) * SR); for (var i = Math.max(0, -s); i < x[0].length && s + i < n; i++) { L[s + i] += x[0][i] * ev[3]; R[s + i] += x[1][i] * ev[3]; } });")
R("function buildAudio(a48, job, onp) { var segs","function buildAudio(a48, job, onp) { var MIXIN = 'mix0.raw', MIXN = [], segs")
R("return FF.writeFile('vc.raw', new Uint8Array(vcut.buffer)).then(function () { return fetch","return FF.writeFile('vc.raw', new Uint8Array(vcut.buffer)).then(function () { vcut = null; return fetch")
a=src.index("  .then(function (vo) { onp('Adding sound design...');"); b=src.index("  .then(function () { ffLog = []; return ex(['-f', 'f32le', '-ar', '48000', '-ac', '2', '-i', 'mix.raw'")
src=src[:a]+r"""  .then(function (vo) { onp('Adding sound design...'); var BLK = 60 * SR, b0 = 0, ev = job.layer.events;
    function nxb() { if (b0 >= n) { return Promise.resolve(); } var len = Math.min(BLK, n - b0), off = b0 / SR, evs = ev.filter(function (e) { return e[0] >= off - 2 && e[0] < off + len / SR; }), fx = renderSfx(evs, len / SR, off), mix = new Float32Array(len * 2), i;
     for (i = 0; i < len; i++) { var x = b0 + i < vo.length ? vo[b0 + i] : 0; mix[2 * i] = x + (fx[0][i] || 0) * 0.55; mix[2 * i + 1] = x + (fx[1][i] || 0) * 0.55; }
     var nm = 'mix' + MIXN.length + '.raw'; MIXN.push(nm); b0 += len; return FF.writeFile(nm, new Uint8Array(mix.buffer)).then(tick).then(nxb); }
    return nxb().then(function () { MIXIN = MIXN.length > 1 ? 'concat:' + MIXN.join('|') : MIXN[0]; }); })
"""+src[b:]
src=src.replace("'-ar', '48000', '-ac', '2', '-i', 'mix.raw', '-af'","'-ar', '48000', '-ac', '2', '-i', MIXIN, '-af'")
assert src.count("-i', MIXIN")==2
R(".then(function () { del('mix.raw'); }); }",".then(function () { MIXN.forEach(del); }); }")

# ---- audio is cleaned in the ffmpeg thread WHILE the video renders (they no longer wait for each other) ----
R("return buildAudio(S.a48, job, function (t) { show(t, 36); }); })\n  .then(function () { show('Rendering every frame...', 40, 'Keep this page open. It takes about 1 to 2 times the length of your video.'); return renderVideo(S.v, job, function (q) { show('Rendering every frame...', 40 + q * 52); }); })\n  .then(function (mp4) { show('Putting it together...', 94);",
  "job.audioP = buildAudio(S.a48, job, function () {}); job.audioP.catch(function () {}); S.a48 = null; })\n  .then(function () { show('Rendering every frame...', 40, (S.v.duration > 180 ? 'Long video: keep this page open and your screen on. ' : 'Keep this page open. ') + 'Your voice is being cleaned at the same time, so this is faster.'); return renderVideo(S.v, job, function (q) { show('Rendering every frame...', 40 + q * 52); }); })\n  .then(function (mp4) { show('Cleaning your voice...', 93); return job.audioP.then(function () { return mp4; }); })\n  .then(function (mp4) { show('Putting it together...', 95);")
# a48 is also needed for 'Re-edit': keep a copy reference only if short (re-edit rebuilds audio from S.a48)
src=src.replace("S.a48 = null; })\n  .then(function () { show('Rendering every frame...'","})\n  .then(function () { show('Rendering every frame...'")
open(D+'rx.stage4.js','w').write(src); print('ok',len(src))
