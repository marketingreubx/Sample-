import re
D='/tmp/claude-0/-home-user-Sample-/dcbfdb8a-deea-534a-ad26-61641deae3be/scratchpad/live/'
src=open(D+'rx.stage5.js').read()
def R(a,b,cnt=1):
    global src
    if src.count(a)==0: a=re.sub(r'(^|\n)  ', r'\1 ', a)
    assert src.count(a)==cnt,(src.count(a),a[:100]); src=src.replace(a,b)
# adaptive playback: faster when the device keeps up, slower when it cannot (never skips a needed source frame)
R("function playSeg(k) { var sg = segs[k],","var prate = 1, clean = 0, d0 = 0.5;\n   function playSeg(k) { var lastM = null, sg = segs[k],")
R("function step(now, meta) { if (done) { return; } if (err) { done = true; v.pause(); rej(err); return; } var m = meta && meta.mediaTime != null ? meta.mediaTime : v.currentTime;",
  "function step(now, meta) { if (done) { return; } if (err) { done = true; v.pause(); rej(err); return; } var m = meta && meta.mediaTime != null ? meta.mediaTime : v.currentTime;\n      if (lastM != null && m > lastM) { var dl = m - lastM; if (dl < d0) { d0 = dl; } var rmax = d0 < 0.025 ? 1.5 : 2; if (dl > Math.max(1.45 / FPS, 1.5 * d0)) { prate = Math.max(0.5, prate * 0.75); clean = 0; } else if (++clean >= 20 && prate < rmax) { prate = Math.min(rmax, prate * 1.25); clean = 0; } var want = m > sg.e - 0.3 ? Math.min(prate, 1) : prate; if (Math.abs(v.playbackRate - want) > 0.01) { try { v.playbackRate = want; } catch (x) {} } } lastM = m;")
R("v.muted = true; v.playbackRate = 1; v.requestVideoFrameCallback(step);","v.muted = true; v.playbackRate = Math.min(prate, 1); v.requestVideoFrameCallback(step);")
open(D+'rx.stage6.js','w').write(src); print('ok',len(src))
