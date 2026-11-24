D='/tmp/claude-0/-home-user-Sample-/dcbfdb8a-deea-534a-ad26-61641deae3be/scratchpad/live/'
s=open(D+'rx.stage11.js').read()
a=s.index("function detectFace(d, w, h) {"); b=s.index("function medianOf(a)")
NN=r"""var ortP = null;
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
"""
s=s[:a]+NN+s[b:]
# measureStats: colour only (face found separately by the detector)
a=s.index("function measureStats(v) {"); b=s.index("function buildGrade(")
s=s[:a]+r"""function measureStats(v) { var n = 6, k = 0, cv = document.createElement('canvas'); cv.width = 72; cv.height = 128; var cx = cv.getContext('2d', { willReadFrequently: true }), A = { y: 0, lo: 0, hi: 0, s: 0 };
 function one() { if (k >= n) { return Promise.resolve({ y: A.y / n, lo: A.lo / n, hi: A.hi / n, s: A.s / n }); } var t = v.duration * (k + 0.5) / n; k++;
  return seekV(v, t).then(function () { cx.drawImage(v, 0, 0, 72, 128); var d = cx.getImageData(0, 0, 72, 128).data, ls = [], sm = 0, i; for (i = 0; i < d.length; i += 4) { var r = d[i], g = d[i + 1], b = d[i + 2]; ls.push(0.299 * r + 0.587 * g + 0.114 * b); var u = -0.169 * r - 0.331 * g + 0.5 * b, w = 0.5 * r - 0.419 * g - 0.081 * b; sm += Math.sqrt(u * u + w * w); }
   ls.sort(function (a, b) { return a - b; }); var m = ls.length; A.y += ls.reduce(function (a, b) { return a + b; }, 0) / m; A.lo += ls[Math.floor(m * 0.1)]; A.hi += ls[Math.floor(m * 0.9)]; A.s += sm / m; return one(); }); }
 return one().catch(function () { return null; }); }
"""+s[b:]
# background: find the face as soon as the video is chosen, redraw the style cards when found; make() waits for it (max 25 s)
a1="loadVid(f).then(function (v) { if (S.file === f) { S.v = v; S.th = {}; S.gradedF = null; refreshPreview(true); } })"
assert s.count(a1)==1
s=s.replace(a1,"loadVid(f).then(function (v) { if (S.file === f) { S.v = v; S.th = {}; S.gradedF = null; FACEN = null; refreshPreview(true); S.facePromise = new Promise(function (r) { setTimeout(r, 25000); }); S.facePromise = Promise.race([findFace(v).then(function (fc) { if (S.v === v && fc) { S.gradedF = null; S.th = {}; refreshPreview(true); } }), S.facePromise]); } })")
a2="prepare().then(function () { return S.stats !== undefined ? null :"
assert s.count(a2)==1
s=s.replace(a2,"prepare().then(function () { return S.facePromise || (S.v ? findFace(S.v) : null); }).then(function () { return S.stats !== undefined ? null :")
open(D+'rx.stage12.js','w').write(s); print('ok',len(s))
