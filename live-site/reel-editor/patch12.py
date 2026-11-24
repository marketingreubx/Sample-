import re
D='/tmp/claude-0/-home-user-Sample-/dcbfdb8a-deea-534a-ad26-61641deae3be/scratchpad/live/'
s=open(D+'rx.stage10.js').read()
def R(a,b,cnt=1):
    global s
    assert s.count(a)==cnt,(s.count(a),a[:110]); s=s.replace(a,b)

# ================= 1. face detection (skin blobs) + face-aware helpers =================
FACE = r"""
/* ---------------- where is the face? skin-colour blobs on a few sample frames (captions and cards never cover it) ---------------- */
var FACEN = null;
function detectFace(d, w, h) { var m = new Uint8Array(w * h), i, x, y, n = 0;
 for (y = 0; y < h; y++) { for (x = 0; x < w; x++) { i = (y * w + x) * 4; var r = d[i], g = d[i + 1], b = d[i + 2], Y = 0.299 * r + 0.587 * g + 0.114 * b, cb = 128 - 0.1687 * r - 0.3313 * g + 0.5 * b, cr = 128 + 0.5 * r - 0.4187 * g - 0.0813 * b; if (Y > 40 && cb >= 77 && cb <= 132 && cr >= 133 && cr <= 178) { m[y * w + x] = 1; n++; } } }
 var lab = new Int16Array(w * h), best = null, id = 0, stack = [];
 for (i = 0; i < w * h; i++) { if (!m[i] || lab[i]) { continue; } id++; var area = 0, x0 = w, x1 = 0, y0 = h, y1 = 0; stack.length = 0; stack.push(i); lab[i] = id;
  while (stack.length) { var p = stack.pop(), px = p % w, py = (p - px) / w; area++; if (px < x0) { x0 = px; } if (px > x1) { x1 = px; } if (py < y0) { y0 = py; } if (py > y1) { y1 = py; }
   if (px > 0 && m[p - 1] && !lab[p - 1]) { lab[p - 1] = id; stack.push(p - 1); } if (px < w - 1 && m[p + 1] && !lab[p + 1]) { lab[p + 1] = id; stack.push(p + 1); } if (py > 0 && m[p - w] && !lab[p - w]) { lab[p - w] = id; stack.push(p - w); } if (py < h - 1 && m[p + w] && !lab[p + w]) { lab[p + w] = id; stack.push(p + w); } }
  var fr = area / (w * h), cx = (x0 + x1) / 2 / w, bw = (x1 - x0 + 1) / w, bh = (y1 - y0 + 1) / h;
  if (fr < 0.008 || fr > 0.26 || cx < 0.2 || cx > 0.8 || y0 > h * 0.6 || bh / bw < 0.7 || bh / bw > 2.8) { continue; }
  var score = area * (1 - Math.abs(cx - 0.5)) * (1 - y0 / h * 0.6); if (!best || score > best.score) { best = { score: score, x0: x0, x1: x1, y0: y0, y1: y1, id: id }; } }
 if (!best) { return null; }
 var rows = [], mx = 0, my = best.y0; for (y = best.y0; y <= best.y1; y++) { var c = 0; for (x = best.x0; x <= best.x1; x++) { if (lab[y * w + x] === best.id) { c++; } } rows.push(c); if (c > mx) { mx = c; my = y; } }
 var chin = best.y1; for (y = my; y <= best.y1; y++) { if (rows[y - best.y0] < mx * 0.6) { chin = y; break; } }
 var top = best.y0, fh = Math.max(chin - top, 4); return { top: top / h, chin: (chin + 1) / h, head: Math.max(0, (top - fh * 0.38) / h), cx: (best.x0 + best.x1 + 1) / 2 / w }; }
function medianOf(a) { a = a.slice().sort(function (x, y) { return x - y; }); return a[Math.floor(a.length / 2)]; }
function faceY(z) { var f = FACEN || { top: 0.2, chin: 0.42, head: 0.13, cx: 0.5 }, v = S.v, vw = (v && v.videoWidth) || 1080, vh = (v && v.videoHeight) || 1920, rc = cropRect(vw, vh, z, 0.45, (f.top + f.chin) / 2, f.cx), k = H / (rc[3] - rc[1]), m = function (y) { return (y - rc[1]) * k; }; return { head: m(f.head), top: m(f.top), chin: m(f.chin) }; }
function chestY(z) { var fy = faceY(z); return clamp(fy.chin + 150, 960, 1540); }
"""
a=s.index("function measureStats(v) {"); b=s.index("function buildGrade(")
s=s[:a]+r"""function measureStats(v) { var n = 6, k = 0, cv = document.createElement('canvas'); cv.width = 72; cv.height = 128; var cx = cv.getContext('2d', { willReadFrequently: true }), A = { y: 0, lo: 0, hi: 0, s: 0 }, faces = [];
 function one() { if (k >= n) { var fc = null; if (faces.length >= 2) { fc = { top: medianOf(faces.map(function (f) { return f.top; })), chin: medianOf(faces.map(function (f) { return f.chin; })), head: medianOf(faces.map(function (f) { return f.head; })), cx: medianOf(faces.map(function (f) { return f.cx; })) }; } return Promise.resolve({ y: A.y / n, lo: A.lo / n, hi: A.hi / n, s: A.s / n, face: fc }); } var t = v.duration * (k + 0.5) / n; k++;
  return seekV(v, t).then(function () { cx.drawImage(v, 0, 0, 72, 128); var d = cx.getImageData(0, 0, 72, 128).data, ls = [], sm = 0, i; for (i = 0; i < d.length; i += 4) { var r = d[i], g = d[i + 1], b = d[i + 2]; ls.push(0.299 * r + 0.587 * g + 0.114 * b); var u = -0.169 * r - 0.331 * g + 0.5 * b, w = 0.5 * r - 0.419 * g - 0.081 * b; sm += Math.sqrt(u * u + w * w); }
   ls.sort(function (a, b) { return a - b; }); var m = ls.length; A.y += ls.reduce(function (a, b) { return a + b; }, 0) / m; A.lo += ls[Math.floor(m * 0.1)]; A.hi += ls[Math.floor(m * 0.9)]; A.s += sm / m; try { var f = detectFace(d, 72, 128); if (f) { faces.push(f); } } catch (e) {} return one(); }); }
 return one().then(function (st) { if (st) { FACEN = st.face; } return st; }).catch(function () { return null; }); }
"""+s[b:]
R("/* ---------------- colour grade: the owner's base grade, adapted to each video (port of grade.py) ---------------- */", FACE+"\n/* ---------------- colour grade: the owner's base grade, adapted to each video (port of grade.py) ---------------- */")
# cropRect: face-aware vertical centre (and horizontal for landscape sources)
R("function cropRect(vw, vh, z, anchor) { var tw = W / H, sw, shh; if (vw / vh > tw) { shh = vh; sw = vh * tw; } else { sw = vw; shh = vw / tw; } sw /= z; shh /= z; var cx = vw * 0.5, cy = vh * 0.42,",
  "function cropRect(vw, vh, z, anchor, fcy, fcx) { var tw = W / H, sw, shh; if (vw / vh > tw) { shh = vh; sw = vh * tw; } else { sw = vw; shh = vw / tw; } sw /= z; shh /= z; var cx = vw * (vw / vh > tw && fcx != null ? fcx : 0.5), cy = vh * (fcy != null ? fcy : 0.42),")
# drawLay: crop the card around the face
R("var ar = w / h, cw = sw, ch = sw / ar; if (ch > sh) { ch = sh; cw = sh * ar; } var cx = (sw - cw) / 2, cy = clamp(sh * 0.42 - ch * 0.45, 0, sh - ch);",
  "var ar = w / h, cw = sw, ch = sw / ar; if (ch > sh) { ch = sh; cw = sh * ar; } var fo = fy || { head: 330, top: 520, chin: 900 }, kk = sh / H, fc = (fo.top + fo.chin) / 2 * kk, cx = (sw - cw) / 2, cy = clamp(Math.min(fc - 0.44 * ch, fo.head * kk - 0.05 * ch), 0, sh - ch);")
R("function drawLay(c, ly, src) {","function drawLay(c, ly, src, fy) {")
# callers of drawLay
R("var ly = layAt(job.layer.lay, t); if (ly) { drawLay(c, ly, gl.cv); } else { c.drawImage(gl.cv, 0, 0); }","var ly = layAt(job.layer.lay, t); if (ly) { drawLay(c, ly, gl.cv, faceY(z)); } else { c.drawImage(gl.cv, 0, 0); }")
R("if (ly) { drawLay(bc, ly, graded); }","if (ly) { drawLay(bc, ly, graded, faceY(1.0)); }")
# caption positions: under the chin, never on the face
R("var capY = function (tt) { if (busy(tt, tt + 0.01, 'split')) { return R.layout === 'tumi' ? 1520 : SPLIT.seam; } if (R.cap.pos === 'top') { return 330; } if (R.cap.pos === 'center' || R.cap.pos === 'seam') { return 1010; } var s = shots[0]; shots.forEach(function (q) { if (q[0] <= tt) { s = q; } }); return s[2] === 'T' ? 1250 : 1190; };",
  "var capY = function (tt) { if (busy(tt, tt + 0.01, 'split')) { return R.layout === 'tumi' ? 1520 : SPLIT.seam; } var s = shots[0]; shots.forEach(function (q) { if (q[0] <= tt) { s = q; } }); var z = s[2] === 'T' ? 1.16 : 1.0, fy = faceY(z);\n  if (R.cap.pos === 'top') { var y0 = fy.head - 100; return y0 >= 250 ? y0 : chestY(z); } if (R.cap.pos === 'center' || R.cap.pos === 'seam') { return fy.top < 1010 && fy.chin + 40 > 1010 ? chestY(z) : 1010; } return chestY(z); };")
R("function capY(t) { if (look.cap_pos === 'top') { return 330; } if (look.cap_pos === 'center') { return 1000; } return shotAt(t)[2] === 'T' ? 1250 : 1190; }",
  "function capY(t) { var z = shotAt(t)[2] === 'T' ? 1.16 : 1.0; if (look.cap_pos === 'top') { var y0 = faceY(z).head - 100; return y0 >= 250 ? y0 : chestY(z); } if (look.cap_pos === 'center') { return 1000; } return chestY(z); }")
# camera anchored on the face
R("var gr = job.grade, anchor = job.st.fam === 'a' ? 0.5 : 0.42,","var gr = job.grade, anchor = 0.45,")
R("gl.draw(v, cropRect(v.videoWidth, v.videoHeight, z, anchor), gr);","var fxo = trAt(job.trans, t, job.trI), rc = cropRect(v.videoWidth, v.videoHeight, z * (1 + (fxo ? fxo.z : 0)), anchor, FACEN ? (FACEN.top + FACEN.chin) / 2 : null, FACEN ? FACEN.cx : null); if (fxo && (fxo.dx || fxo.dy)) { var rw = rc[2] - rc[0], rh = rc[3] - rc[1]; rc = [rc[0] + fxo.dx * rw, rc[1] + fxo.dy * rh, rc[2] + fxo.dx * rw, rc[3] + fxo.dy * rh]; } gl.draw(v, rc, gr, fxo);")
R("g.draw(v, cropRect(v.videoWidth, v.videoHeight, 1.0, 0.42), gr);","g.draw(v, cropRect(v.videoWidth, v.videoHeight, 1.0, 0.45, FACEN ? (FACEN.top + FACEN.chin) / 2 : null, FACEN ? FACEN.cx : null), gr);")

# ================= 2. noun: sign / step / tip / mistake ... =================
R("function cap1(x) { x = String(x || ''); return x.charAt(0).toUpperCase() + x.slice(1); }\nfunction rrectP",
  """function cap1(x) { x = String(x || ''); return x.charAt(0).toUpperCase() + x.slice(1); }
function nounFor(words, ai) { var n = ai && ai.list && ai.list.noun ? String(ai.list.noun).toLowerCase().replace(/[^a-z]/g, '').replace(/s$/, '') : ''; if (n && n.length >= 3 && n.length <= 12) { return n; }
 var cnt = {}, keys = { sign: /^signs?$/, step: /^steps?$/, tip: /^tips?$/, mistake: /^mistakes?$/, reason: /^reasons?$/, rule: /^rules?$/, habit: /^habits?$/, myth: /^myths?$/, hack: /^hacks?$/, lesson: /^lessons?$/, way: /^ways?$/, question: /^questions?$/, symptom: /^symptoms?$/, secret: /^secrets?$/ }; words.forEach(function (x) { var w = cleanW(x.w).toLowerCase(); for (var k in keys) { if (keys[k].test(w)) { cnt[k] = (cnt[k] || 0) + 1; } } });
 var best = 'point', bc = 1; for (var q in cnt) { if (cnt[q] > bc) { bc = cnt[q]; best = q; } } return best; }
function rrectP""")
R(" P.items.sort(function (x, y) { return x.a - y.a; });"," P.noun = nounFor(words, ai); P.items.sort(function (x, y) { return x.a - y.a; });")
R("var st1 = 'STEP ' + n;","var st1 = (P.noun || 'step').toUpperCase() + ' ' + n;")
R("var lab = 'Point #' + n + ':';","var lab = cap1(P.noun || 'point') + ' #' + n + ':';")
R("c.fillText((th === 'blueprint' ? 'THE BLUEPRINT  ' : 'STEP  ') + n + ' / ' + nI, 96, 330);","c.fillText((th === 'blueprint' ? 'THE BLUEPRINT  ' : (P.noun || 'step').toUpperCase() + '  ') + n + ' / ' + nI, 96, 330);")

# ================= 3. CapCut-style transitions =================
TR = r"""
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
function planTrans(job, R, ui) { if (ui.trans === 'off') { return []; } var st = job.st, set = (R && R.tr) || (st.fam === 'a' ? ['fade', 'zoomrush', 'rebound'] : ['zoomrush', 'swing', 'flash', 'slide']), pts = [], out = [], T = job.cut.T, lastT = -9, k = 0, sgv = st.look.sfx == null ? 1 : st.look.sfx, cards = job.layer.cardSpans || [];
 job.shots.forEach(function (sh, i) { if (i > 0 && sh[0] > 0.6 && sh[0] < T - 0.4) { pts.push(sh[0]); } }); if (R && R.cam === 'locked' && job.layer.marks) { job.layer.marks.forEach(function (t) { if (t > 0.8 && t < T - 0.5) { pts.push(t); } }); }
 pts.sort(function (a, b) { return a - b; }); pts.forEach(function (t) { if (t - lastT < 1.4) { return; } if (cards.some(function (c) { return t >= c[0] - 0.05 && t < c[1] + 0.05; })) { return; } var kind = set[k % set.length]; k++; lastT = t; out.push({ t: t, kind: kind });
  (TR_SFX[kind] || []).forEach(function (q) { job.layer.events.push([Math.max(0, t + q[1]), q[0], 0, q[2] * sgv * (R && R.trV != null ? R.trV : 0.85)]); }); }); return out; }
"""
R("/* ---------------- the camera: shots wide/tight, slow push-in, eased punch-ins (port of core.base) ---------------- */", TR+"\n/* ---------------- the camera: shots wide/tight, slow push-in, eased punch-ins (port of core.base) ---------------- */")
# GL: transitions + supersampling + upscale sharpening
a=s.index("function makeGL(w, h) {"); b=s.index("/* ---------------- CapCut-style clip transitions")
GL = r"""function makeGL(w, h) { var cv = document.createElement('canvas'); cv.width = w; cv.height = h; var gl = cv.getContext('webgl', { premultipliedAlpha: false, preserveDrawingBuffer: true }); if (!gl) { return null; }
  var vs = 'attribute vec2 p;varying vec2 v;varying vec2 o;uniform vec4 r;uniform vec2 rt;void main(){vec2 q=p;q.x*=rt.y;float a=rt.x;q=vec2(cos(a)*q.x-sin(a)*q.y,sin(a)*q.x+cos(a)*q.y);q.x/=rt.y;o=p*.5+.5;v=vec2(r.x+(q.x*.5+.5)*(r.z-r.x),r.y+(.5-q.y*.5)*(r.w-r.y));gl_Position=vec4(p,0.,1.);}';
  var fs = '#ifdef GL_FRAGMENT_PRECISION_HIGH\\nprecision highp float;\\n#else\\nprecision mediump float;\\n#endif\\nvarying vec2 v;varying vec2 o;uniform sampler2D t;uniform vec4 g1;uniform vec4 g2;uniform vec2 g3;uniform vec2 px;uniform vec4 f1;uniform vec4 f2;uniform vec2 vc;uniform float sd;float hh(float n){return fract(sin(n*91.3458)*47453.5453);}vec3 S(vec2 u){return texture2D(t,u).rgb;}void main(){vec2 uv=v;if(f2.y>.001){float row=floor(o.y*16.);uv.x+=(hh(row+sd)-.5)*f2.y;}float ds=f2.w;vec3 c;if(f1.x>.001||f2.z>.001){vec3 acc=vec3(0.);for(int i=0;i<8;i++){float f=float(i)/7.;vec2 u2=uv-(uv-vc)*f1.x*f+vec2((f-.5)*f2.z,0.);acc+=S(u2);}c=acc/8.;}else if(ds>1.15){vec2 e=px*ds*.25;c=(S(uv+vec2(-e.x,-e.y))+S(uv+vec2(e.x,-e.y))+S(uv+vec2(-e.x,e.y))+S(uv+vec2(e.x,e.y)))*.25;}else{c=S(uv);}float k=max(1.,ds);vec3 b=(S(uv+vec2(px.x*k,0.))+S(uv-vec2(px.x*k,0.))+S(uv+vec2(0.,px.y*k))+S(uv-vec2(0.,px.y*k)))*.25;c+=(c-b)*g3.x*1.3;vec3 b2=(S(uv+vec2(5.,5.)*px*k)+S(uv+vec2(-5.,5.)*px*k)+S(uv+vec2(5.,-5.)*px*k)+S(uv+vec2(-5.,-5.)*px*k))*.25;c+=(c-b2)*g3.y*.9;c+=g1.x*.2;c=(c-.5)*g1.y+.5;float l=dot(c,vec3(.299,.587,.114));c+=g2.x*.22*(1.-smoothstep(0.,.5,l));c+=g2.y*.22*smoothstep(.45,1.,l);c+=g2.z*.14*smoothstep(.8,1.,l);c+=g2.w*.2*max(0.,1.-abs(l-.45)*2.);l=dot(c,vec3(.299,.587,.114));float sat=max(c.r,max(c.g,c.b))-min(c.r,min(c.g,c.b));c=mix(vec3(l),c,g1.z*(1.+(1.-sat)*.2));float h=g1.w,cs=cos(h),sn=sin(h);float Y=dot(c,vec3(.299,.587,.114)),I=dot(c,vec3(.596,-.274,-.322)),Q=dot(c,vec3(.211,-.523,.312));float I2=I*cs-Q*sn,Q2=I*sn+Q*cs;c=vec3(Y+.956*I2+.621*Q2,Y-.272*I2-.647*Q2,Y-1.106*I2+1.703*Q2);c=mix(c,vec3(1.),f1.y);c*=1.-f1.z;if(f1.w>.001){float gx=exp(-pow((o.x-f2.x)*2.6,2.));c+=vec3(1.,.55,.2)*(gx*.9+.12)*f1.w;}gl_FragColor=vec4(clamp(c,0.,1.),1.);}';
  function sh(type, src) { var s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s); return s; }
  var pr = gl.createProgram(); gl.attachShader(pr, sh(gl.VERTEX_SHADER, vs)); gl.attachShader(pr, sh(gl.FRAGMENT_SHADER, fs)); gl.linkProgram(pr); gl.useProgram(pr);
  var b = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, b); gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW); var lp = gl.getAttribLocation(pr, 'p'); gl.enableVertexAttribArray(lp); gl.vertexAttribPointer(lp, 2, gl.FLOAT, false, 0, 0);
  var tx = gl.createTexture(); gl.bindTexture(gl.TEXTURE_2D, tx); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
  var U = function (n) { return gl.getUniformLocation(pr, n); }, uR = U('r'), uG1 = U('g1'), uG2 = U('g2'), uG3 = U('g3'), uPx = U('px'), uRt = U('rt'), uF1 = U('f1'), uF2 = U('f2'), uVc = U('vc'), uSd = U('sd');
  return { cv: cv, lose: function () { try { var e = gl.getExtension('WEBGL_lose_context'); if (e) { e.loseContext(); } } catch (x) {} }, draw: function (src, rect, gr, fx) { fx = fx || {}; gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, src); gl.uniform4f(uR, rect[0], rect[1], rect[2], rect[3]); var vw = src.videoWidth || src.width || 1080, vh = src.videoHeight || src.height || 1920,
    ds = (rect[2] - rect[0]) * vw / w, up = ds < 1 ? Math.min(1.9, 1 + 0.9 * (1 / Math.max(ds, 0.01) - 1)) : 1;
    gl.uniform4f(uG1, gr.brightness / 100, 1 + gr.contrast / 100 * 0.6, 1 + gr.saturation / 100 * 0.6, gr.hue * 0.2 * Math.PI / 180); gl.uniform4f(uG2, gr.shadows / 100, gr.highlights / 100, gr.whites / 100, gr.brilliance / 100); gl.uniform2f(uG3, gr.sharpen / 100 * 0.6 * up, gr.clarity / 100 * 1.2); gl.uniform2f(uPx, 1 / vw, 1 / vh);
    gl.uniform2f(uRt, fx.rot || 0, w / h); gl.uniform4f(uF1, fx.rb || 0, fx.flash || 0, fx.fade || 0, fx.leak || 0); gl.uniform4f(uF2, fx.leakPos || 0, fx.slice || 0, fx.bx || 0, ds); gl.uniform2f(uVc, (rect[0] + rect[2]) / 2, (rect[1] + rect[3]) / 2); gl.uniform1f(uSd, Math.random() * 40);
    gl.viewport(0, 0, w, h); gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4); } }; }

"""
s=s[:a]+GL+s[b:]

# ================= 4. quality: 10 Mbps, hardware encoder, keyframes =================
R("bitrate: Math.round(clamp(6.5e6 * Math.sqrt(60 / Math.max(TT, 60)), 3.2e6, 6.5e6)), framerate: FPS, latencyMode: 'quality' });","bitrate: brFor(TT), framerate: FPS, latencyMode: 'quality', bitrateMode: 'variable', avc: { format: 'avc' } });")
R("function pickCodec() {","function brFor(TT) { return Math.round(clamp((IOS ? 200 : 280) * 1048576 * 8 / Math.max(TT, 1), 5e6, 10e6)); }\nfunction pickCodec() {")
R("bitrate: 9e6, framerate: FPS }).then(function (r) { if (r.supported)","bitrate: 10e6, framerate: FPS }).then(function (r) { if (r.supported)")
R("'-c:a', 'aac', '-b:a', '192k', '-shortest'","'-c:a', 'aac', '-b:a', '256k', '-shortest'")

# ================= 5. wire it into make(): face, transitions, UI =================
R("return { music: g('rxMusic', 'auto'),","return { trans: g('rxTrans', 'auto'), music: g('rxMusic', 'auto'),")
R("   var mname = ui.music === 'auto' ? (R ? R.music : 'lofi_chill') : ui.music; job.musicVol = ui.musicVol;","   job.trI = R && R.trI != null ? R.trI : 1; job.trans = planTrans(job, R, ui);\n   var mname = ui.music === 'auto' ? (R ? R.music : 'lofi_chill') : ui.music; job.musicVol = ui.musicVol;")
R("return { items: L, events: EV, END: 0, cards: BUSY.filter(function (e) { return e[2] === 'card' || e[2] === 'split'; }).length, lay: LAY }; }","return { items: L, events: EV, END: 0, cards: BUSY.filter(function (e) { return e[2] === 'card' || e[2] === 'split'; }).length, lay: LAY, marks: items.map(function (it) { return it.ta; }), cardSpans: BUSY.filter(function (e) { return e[2] === 'card' || e[2] === 'icard'; }).map(function (e) { return [e[0], e[1]]; }) }; }")
R("return { items: L, events: EV, END: END, cards: CARDS.length }; }","return { items: L, events: EV, END: END, cards: CARDS.length, cardSpans: CARDS.slice() }; }")

# ================= 6. recipes: transition sets, bigger captions for serif styles, dramatic sounds =================
def rec(id, add):
    global s
    m=re.search(r"( '"+re.escape(id)+r"':\s*\{)", s); assert m, id
    s=s[:m.end()]+" "+add+","+s[m.end():]
rec('ref-chloe',"tr: ['rebound', 'stamp', 'swing', 'flash', 'zoomrush'], trV: 0.9")
rec('ref-kallaway',"tr: ['zoomrush', 'slices', 'superzoom'], trV: 0.85")
rec('ref-social',"tr: ['fade', 'zoomrush'], trV: 0.6, trI: 0.6")
rec('ref-wolf',"tr: ['zoomrush', 'fade', 'leak'], trV: 0.7, trI: 0.7")
rec('ref-tumi',"tr: ['leak', 'fade', 'zoomrush', 'slide'], trV: 0.7, trI: 0.8")
rec('ref-millie',"tr: ['fade', 'slide'], trV: 0.55, trI: 0.6")
rec('ref-deshna',"tr: ['stamp', 'swing', 'rebound', 'flash'], trV: 0.9")
rec('ref-zach',"tr: ['slide', 'fade'], trV: 0.6, trI: 0.7")
rec('ref-sky',"tr: ['zoomrush', 'flash', 'slices'], trV: 0.8")
rec('ref-adam',"tr: ['slide', 'zoomrush', 'stamp'], trV: 0.8")
rec('ref-cinematic',"tr: ['fade', 'leak', 'zoomrush'], trV: 0.7, trI: 0.7")
rec('edita-interview',"tr: ['fade', 'leak'], trV: 0.6, trI: 0.6")
rec('edita-podcast',"tr: ['fade', 'slide'], trV: 0.6, trI: 0.6")
rec('edita-blueprint',"tr: ['slide', 'zoomrush', 'stamp', 'superzoom'], trV: 0.9")
rec('gallo-promo',"tr: ['rebound', 'swing', 'slide', 'flash'], trV: 0.9")
s=s.replace("'ref-cinematic':{ cap: { pos: 'chest', size: 52,","'ref-cinematic':{ cap: { pos: 'chest', size: 66,")
s=s.replace("'edita-interview': { cap: { pos: 'chest', size: 50,","'edita-interview': { cap: { pos: 'chest', size: 64,")
s=s.replace("'edita-podcast': { cap: { pos: 'chest', size: 50,","'edita-podcast': { cap: { pos: 'chest', size: 64,")
s=s.replace("sfx: { card: 'whoosh2', hook: 'riser2', split: 'swoosh2' }","sfx: { card: 'drama2', hook: 'riser2', split: 'swoosh2' }") if "card: 'whoosh2', hook: 'riser2'" in s else s
s=s.replace("sfx: { hdr: 'click1', card: 'whoosh3', stack: 'thud2' }","sfx: { hdr: 'click1', card: 'drama2', stack: 'boom' }")
s=s.replace("sfx: { stack: 'thud2', card: 'whoosh3', row: 'whoosh2' }","sfx: { stack: 'boom', card: 'drama1', row: 'whoosh2' }")
s=s.replace("sfx: { stack: 'thud2', card: 'whoosh1', row: 'whoosh3' }","sfx: { stack: 'punch', card: 'whoosh1', row: 'whoosh3' }")
open(D+'rx.stage11.js','w').write(s); print('ok',len(s))
