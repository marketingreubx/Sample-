"""Transitions and camera effects, rendered inside each shot's own ffmpeg pass (no overlap, so audio sync never moves).

A transition is split in two halves: the outgoing shot's last frames (motion blur / zoom out / flash build) and the
incoming shot's first frames (whip settle, zoom-through, glitch, spin...). Kinds:
  cut    hard cut with framing change (jump cuts inside a sentence; what pro editors do most of the time)
  whip   fast whip pan with horizontal motion blur on both sides
  zoom   zoom-through: blur-zoom out of shot A, land zoomed-in on shot B and ease back
  flash  white flash at the cut (bright, energetic)
  glitch RGB split + noise for 3-4 frames (tech / AI topics)
  spin   small rotational whip with blur
  shake  impact shake on landing (punchlines)
  blur   soft defocus dissolve (calm / editorial)
  dip    quick dip through black (section change, story)
  leak   warm light leak sweeping across the cut (lifestyle / emotional)
Effects on punch words (dynamic mode): micro shake + chromatic split for 3 frames.
"""
import os

DYNAMIC = ["whip", "zoom", "flash", "glitch", "spin", "shake", "leak", "whip", "zoom", "blur"]
MINIMAL = ["zoom", "blur", "dip", "whip", "leak"]
SFX = {"whip": ("whoosh", 0.40, 0.42), "spin": ("whoosh", 0.45, 0.40), "zoom": ("whoosh", 0.35, 0.32), "flash": ("hit", 0.6, 0.22),
       "glitch": ("glitch", 0.25, 0.35), "shake": ("hit", 0.7, 0.28), "blur": ("swish", 0.3, 0.2), "dip": ("swish", 0.3, 0.15), "leak": ("shimmer", 0.6, 0.18)}


def mode_for(style, look):
    m = (look or {}).get("transitions", "auto")
    if m != "auto": return m
    if style.startswith("b-loud") or style.startswith("b-showcase") or style.startswith("a-skit"): return "dynamic"
    return "minimal"


def assign(shots, section_starts, style, look, overrides=None):
    """shots: [[a, b, frame], ...] -> transition kind for the START of each shot (index 0 = none)."""
    mode = mode_for(style, look); pool = DYNAMIC if mode == "dynamic" else MINIMAL
    out, k, last = ["none"], 0, None
    for a, b, fr in shots[1:]:
        kind = "cut"
        if mode != "off" and (any(abs(a - s) < 0.05 for s in section_starts) or mode == "dynamic" and (b - a) > 3.5 and k % 3 == 2):
            kind = pool[k % len(pool)]
            if kind == last: kind = pool[(k + 1) % len(pool)]
            k += 1
        elif mode == "dynamic": k += 1
        out.append(kind); last = kind
    for t, kind in (overrides or {}).items():
        j = min(range(len(shots)), key=lambda i: abs(shots[i][0] - t))
        if j: out[j] = kind
    return out


def ease_out(v): return f"pow(max(0,1-({v})),2)"


def zoom_terms(tin, tout, n):
    """Extra zoompan terms (zoom, x shift, y shift) for the shot."""
    z, dx, dy = [], [], []
    e_in = lambda d: ease_out(f"on/{d}")
    e_out = lambda d: f"pow(clip((on-{n - d})/{d},0,1),2)"
    if tin == "zoom": z.append(f"0.28*{e_in(8)}")
    if tin in ("whip", "spin"): z.append(f"0.22*{e_in(7)}")
    if tin == "whip": dx.append(f"iw*0.16*{e_in(6)}")
    if tin == "shake":
        z.append(f"0.06*{e_in(10)}"); dx.append(f"iw*0.012*sin(on*2.7)*{e_in(10)}"); dy.append(f"ih*0.01*cos(on*3.1)*{e_in(10)}")
    if tout == "zoom": z.append(f"0.18*{e_out(4)}")
    if tout in ("whip", "spin"): z.append(f"0.16*{e_out(4)}")
    if tout == "whip": dx.append(f"-iw*0.12*{e_out(4)}")
    return z, dx, dy


def punch_terms(frames):
    """Micro shake on punch words (dynamic mode)."""
    dx, dy = [], []
    for f in frames:
        w = f"between(on,{f},{f + 5})"
        dx.append(f"iw*0.006*sin(on*3.3)*{w}"); dy.append(f"ih*0.005*cos(on*2.9)*{w}")
    return dx, dy


def post_filters(tin, tout, n, punch_frames=(), leak=None):
    """Filters after zoompan (timeline-gated on the shot's own frame numbers)."""
    f = []
    if tin == "whip" or tout == "whip":
        f.append(f"avgblur=sizeX=38:sizeY=1:enable='{'lt(n,3)' if tin == 'whip' else 'gte(n,' + str(n - 3) + ')'}'")
        if tin == "whip" and tout == "whip": f.append(f"avgblur=sizeX=38:sizeY=1:enable='gte(n,{n - 3})'")
    if tin == "spin": f.append("rotate=a='0.16*pow(max(0,1-n/7),2)':c=black,avgblur=sizeX=16:sizeY=16:enable='lt(n,3)'")
    if tout == "spin": f.append(f"rotate=a='-0.12*pow(clip((n-{n - 4})/4,0,1),2)':c=black,avgblur=sizeX=16:sizeY=16:enable='gte(n,{n - 3})'")
    if tin == "zoom": f.append("gblur=sigma=14:enable='lt(n,2)',gblur=sigma=6:enable='between(n,2,4)'")
    if tout == "zoom": f.append(f"gblur=sigma=10:enable='gte(n,{n - 2})'")
    if tin == "blur": f.append("gblur=sigma=22:enable='lt(n,3)',gblur=sigma=10:enable='between(n,3,6)',gblur=sigma=4:enable='between(n,7,9)'")
    if tout == "blur": f.append(f"gblur=sigma=8:enable='between(n,{n - 6},{n - 4})',gblur=sigma=18:enable='gte(n,{n - 3})'")
    if tin == "glitch":
        f.append("rgbashift=rh=-22:bh=22:gv=6:enable='lt(n,2)',rgbashift=rh=10:bh=-10:enable='between(n,2,3)',noise=alls=28:allf=t:enable='lt(n,4)'")
    if tout == "glitch": f.append(f"rgbashift=rh=14:bh=-14:enable='gte(n,{n - 2})'")
    if tin == "flash": f.append("fade=t=in:st=0:d=0.22:color=white")
    if tout == "flash": f.append(f"fade=t=out:st={(n - 3) / 30:.3f}:d=0.1:color=white")
    if tin == "dip": f.append("fade=t=in:st=0:d=0.18:color=black")
    if tout == "dip": f.append(f"fade=t=out:st={(n - 5) / 30:.3f}:d=0.17:color=black")
    if tin == "shake": f.append("gblur=sigma=5:enable='lt(n,2)'")
    for p in punch_frames: f.append(f"rgbashift=rh=-7:bh=7:enable='between(n,{p},{p + 2})'")
    return f


def leak_png(path):
    """Warm light-leak overlay (transparent PNG), created once."""
    if os.path.exists(path): return path
    import numpy as np
    from PIL import Image
    W, H = 1080, 1920; yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
    a = np.exp(-(((xx - 980) / 520) ** 2 + ((yy - 300) / 900) ** 2)) * 0.95 + np.exp(-(((xx - 120) / 380) ** 2 + ((yy - 1500) / 700) ** 2)) * 0.55
    img = np.zeros((H, W, 4), np.uint8); img[..., 0] = 255; img[..., 1] = (150 + 80 * a.clip(0, 1)).astype(np.uint8); img[..., 2] = 60
    img[..., 3] = (a.clip(0, 1) * 235).astype(np.uint8); Image.fromarray(img).save(path); return path


def sfx_events(shots, trans):
    ev = []
    for (a, b, fr), kind in zip(shots, trans):
        if kind in SFX:
            k, d, g = SFX[kind]; ev.append((max(a - (0.28 if k == "whoosh" else 0.02), 0), k, d, g))
    return ev
