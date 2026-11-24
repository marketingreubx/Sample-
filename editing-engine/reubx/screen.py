"""Screen-recording mode: the recording is presented, never chopped.

- detect(): landscape footage, or footage with a face in fewer than 15% of frames, is a screen recording.
- The whole take is kept (only the dead air before the first and after the last word is trimmed, plus ranges the
  user marks in plan.remove), so a tutorial never loses a step.
- Presentation: the recording sits on a soft blurred / tinted backdrop as a rounded card with a shadow, and a smart
  zoom follows the action (mouse / typing / UI changes found by frame differencing): it pushes in to the busy area when
  the change is local and eases back out to the full screen when the whole screen changes or nothing moves.
- A virtual face box is written to faces.json so captions and graphics land under / above the card automatically.
"""
import json, subprocess
import numpy as np
from core import W, H, FPS, video_size, ff

CARD_W = 1020


def detect(video, faces_raw):
    w, h = video_size(video)
    if w > h * 1.05: return True
    try:
        dur = float(subprocess.run(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", video], capture_output=True, text=True).stdout)
    except Exception: return False
    samples = max(dur * 5, 1)
    return len(faces_raw) / samples < 0.15


def layout(video):
    w, h = video_size(video); ar = w / h
    cw = CARD_W if ar >= 0.75 else int(min(CARD_W, 1440 * ar)) // 2 * 2
    ch = int(cw / ar) // 2 * 2; ch = min(ch, 1440)
    top = 330 if ar >= 1 else max(150, (H - ch) // 2 - 140)
    return {"src": [w, h], "cw": cw, "ch": ch, "x": (W - cw) // 2, "y": top}


def motion_path(video, cut, L):
    """Per output frame crop rect (cx, cy, zoom) in source-normalised units, eased."""
    w, h = L["src"]; sw = 160; shh = max(int(sw * h / w), 2)
    raw = subprocess.run(["ffmpeg", "-v", "error", "-i", video, "-vf", f"fps=10,scale={sw}:{shh},format=gray", "-f", "rawvideo", "-"],
                         capture_output=True, check=True).stdout
    fr = np.frombuffer(raw, np.uint8).reshape(-1, shh, sw).astype(np.float32)
    keys = []                                   # (src_time, cx, cy, zoom)
    for k in range(1, len(fr)):
        d = np.abs(fr[k] - fr[k - 1]) > 18; n = d.sum()
        if n < 6 or n > d.size * 0.35: keys.append((k / 10, 0.5, 0.5, 1.0)); continue
        ys, xs = np.nonzero(d); spread = max(np.percentile(xs, 90) - np.percentile(xs, 10), (np.percentile(ys, 90) - np.percentile(ys, 10)) * w / h)
        z = float(np.clip(sw / max(spread * 1.8, 1), 1.0, 1.75))
        keys.append((k / 10, float(xs.mean() / sw), float(ys.mean() / shh), z))
    if not keys: keys = [(0, .5, .5, 1.0)]
    K = np.array(keys)
    # hold each target ~1.2 s, then ease (critically damped follow)
    N = round(cut["T"] * FPS); out = np.zeros((N, 3)); cur = np.array([.5, .5, 1.0]); vel = np.zeros(3)
    segs = cut["segs"]
    for f in range(N):
        t = f / FPS; sg = max((s for s in segs if s["o"] <= t + 1e-6), key=lambda s: s["o"]); src = sg["s"] + t - sg["o"]
        win = K[(K[:, 0] >= src - 0.6) & (K[:, 0] <= src + 0.6)]
        tgt = np.array([np.median(win[:, 1]), np.median(win[:, 2]), np.median(win[:, 3])]) if len(win) else np.array([.5, .5, 1.0])
        if tgt[2] < 1.15: tgt = np.array([.5, .5, 1.0])
        acc = (tgt - cur) * 9.0 - vel * 6.0; vel += acc / FPS; cur = cur + vel / FPS; out[f] = cur
    out[:, 2] = np.clip(out[:, 2], 1.0, 1.75)
    return out


def base(job, cut, grade_chain, look=None):
    """Writes base.mp4 (1080x1920) + shots.json + faces.json for screen recordings."""
    import cv2
    L = layout(job.video); w, h = L["src"]; cw, ch, X, Y = L["cw"], L["ch"], L["x"], L["y"]
    path = motion_path(job.video, cut, L); N = len(path)
    tint = np.array(_hex((look or {}).get("screen_bg", "#101218")), np.float32)[::-1]
    # graded source frames, in output order, at 2x card size for sharp zooms
    sw2, sh2 = cw * 2 // 2 * 2, ch * 2 // 2 * 2
    sel = "+".join(f"between(t,{s['s']:.3f},{s['e']:.3f})" for s in cut["segs"])
    dec = subprocess.Popen(["ffmpeg", "-v", "error", "-i", job.video, "-vf",
                            f"select='{sel}',setpts=N/FRAME_RATE/TB,fps={FPS},scale={sw2}:{sh2}:flags=lanczos,{grade_chain},format=bgr24",
                            "-f", "rawvideo", "-"], stdout=subprocess.PIPE)
    enc = subprocess.Popen(["ffmpeg", "-v", "error", "-y", "-f", "rawvideo", "-pix_fmt", "bgr24", "-s", f"{W}x{H}", "-r", str(FPS), "-i", "-",
                            "-c:v", "libx264", "-preset", "veryfast", "-crf", "16", "-pix_fmt", "yuv420p", job.p("base.mp4")], stdin=subprocess.PIPE)
    mask = np.zeros((ch, cw), np.uint8); cv2.rectangle(mask, (0, 0), (cw - 1, ch - 1), 255, -1)
    r = 34; mask[:r, :r] = 0; mask[:r, -r:] = 0; mask[-r:, :r] = 0; mask[-r:, -r:] = 0
    for cx, cy in ((r, r), (cw - r - 1, r), (r, ch - r - 1), (cw - r - 1, ch - r - 1)): cv2.circle(mask, (cx, cy), r, 255, -1)
    m3 = (mask.astype(np.float32) / 255)[..., None]
    shadow = np.zeros((H, W), np.float32); cv2.rectangle(shadow, (X + 10, Y + 30), (X + cw - 10, Y + ch + 40), 1.0, -1)
    shadow = (cv2.GaussianBlur(shadow, (0, 0), 38) * 0.65)[..., None]
    size = sw2 * sh2 * 3; last = None
    for f in range(N):
        buf = dec.stdout.read(size)
        if len(buf) < size:
            if last is None: break
            src = last
        else: src = np.frombuffer(buf, np.uint8).reshape(sh2, sw2, 3); last = src
        cx, cy, z = path[f]; vw, vh = sw2 / z, sh2 / z
        x0 = float(np.clip(cx * sw2 - vw / 2, 0, sw2 - vw)); y0 = float(np.clip(cy * sh2 - vh / 2, 0, sh2 - vh))
        M = np.array([[cw / vw, 0, -x0 * cw / vw], [0, ch / vh, -y0 * ch / vh]], np.float32)
        card = cv2.warpAffine(src, M, (cw, ch), flags=cv2.INTER_AREA if z < 1.3 else cv2.INTER_LINEAR)
        small = cv2.resize(src, (108, 192), interpolation=cv2.INTER_AREA)
        bg = cv2.resize(cv2.GaussianBlur(small, (0, 0), 6), (W, H), interpolation=cv2.INTER_LINEAR).astype(np.float32)
        bg = bg * 0.35 + tint * 0.65; bg = bg * (1 - shadow)
        roi = bg[Y:Y + ch, X:X + cw]; bg[Y:Y + ch, X:X + cw] = roi * (1 - m3) + card.astype(np.float32) * m3
        enc.stdin.write(bg.clip(0, 255).astype(np.uint8).tobytes())
    enc.stdin.close(); enc.wait(); dec.stdout.close(); dec.wait()
    shots = [[0.0, cut["T"], "W"]]
    json.dump(shots, open(job.p("shots.json"), "w")); json.dump(["none"], open(job.p("trans.json"), "w"))
    fh = (Y + ch - 40 - Y) / 1.1                       # virtual face: captions under the card, extras above it
    json.dump([[round(t / 5, 2), 390, Y, 300, int(fh)] for t in range(int(cut["T"] * 5) + 1)], open(job.p("faces.json"), "w"))
    json.dump(L, open(job.p("screen.json"), "w"))
    return shots


def _hex(c):
    c = c.lstrip("#"); return [int(c[i:i + 2], 16) for i in (0, 2, 4)]
