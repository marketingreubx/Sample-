"""Colour grade: phone-style sliders (-100..100, like CapCut / iPhone Edit) -> one ffmpeg filter chain.

The owner's base grade works on most talking-head footage. Before applying it the engine measures the clip
(exposure, black/white levels, saturation) and nudges the sliders so dark clips are lifted, bright clips are held back,
already-saturated clips are not pushed further and flat clips get a little more punch. Nothing is overdone: every
slider is clamped to a safe range and `intensity` scales the whole grade (0 = off, 1 = full).

plan.look.grade overrides any slider, e.g. {"contrast": 6, "auto": false, "intensity": 0.8}.
"""
import json, re, subprocess

# Owner's base grade (works for most videos). Whites -6..-9 -> -7.5, brilliance 3..4 -> 3.5.
BASE = {"brightness": 2, "contrast": 4, "saturation": 6, "brilliance": 3.5, "highlights": -13, "shadows": -23,
        "whites": -7.5, "hue": 9, "sharpen": 50, "clarity": 7, "temp": 0, "tint": 0}
SLIDERS = list(BASE)
LIMITS = {k: (-60, 60) for k in SLIDERS}; LIMITS.update(sharpen=(0, 100), clarity=(-30, 40))
# Screen recordings: keep UI text crisp and true to colour (no crushed shadows, no clarity halos).
SCREEN = {"brightness": 0, "contrast": 2, "saturation": 2, "brilliance": 0, "highlights": 0, "shadows": 0, "whites": 0, "hue": 0,
          "sharpen": 25, "clarity": 0, "temp": 0, "tint": 0}


def measure(video, n=8):
    """Average exposure stats of n frames spread over the clip (0-255 scale)."""
    try:
        dur = float(subprocess.run(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", video],
                                   capture_output=True, text=True).stdout or 10)
        out = subprocess.run(["ffmpeg", "-hide_banner", "-v", "error", "-i", video, "-vf",
                              f"fps={n / max(dur, 1):.4f},scale=270:-2,signalstats,metadata=print:file=-", "-an", "-f", "null", "-"],
                             capture_output=True, text=True).stdout
    except Exception:
        return {}
    acc = {}
    for k, v in re.findall(r"lavfi\.signalstats\.(YAVG|YLOW|YHIGH|SATAVG|HUEAVG)=([\d.]+)", out): acc.setdefault(k, []).append(float(v))
    return {k: round(sum(v) / len(v), 1) for k, v in acc.items()}


def adapt(s, st):
    """Nudge sliders from the measured clip. Returns (sliders, list of notes)."""
    s, notes = dict(s), []
    if not st: return s, notes
    y, lo, hi, sat = st.get("YAVG", 125), st.get("YLOW", 30), st.get("YHIGH", 210), st.get("SATAVG", 30)
    if y < 105:   # dark clip: lift, crush less
        s["brightness"] += min((110 - y) * 0.45, 14); s["shadows"] *= 0.45; notes.append(f"dark clip (avg {y:.0f}): lifted exposure, kept shadows open")
    elif y > 150:  # bright clip: hold back, protect highlights
        s["brightness"] -= min((y - 145) * 0.35, 10); s["highlights"] -= 8; notes.append(f"bright clip (avg {y:.0f}): pulled exposure and highlights down")
    if lo < 18:
        s["shadows"] *= 0.5; notes.append("blacks already deep: softer shadow pull")
    elif lo > 48:
        s["shadows"] *= 1.25; s["contrast"] += 4; notes.append("flat / washed blacks: a little more contrast")
    if hi > 238:
        s["highlights"] -= 7; s["whites"] -= 4; notes.append("clipping highlights: recovered")
    elif hi < 175:
        s["whites"] = abs(s["whites"]) * 0.6; s["highlights"] *= 0.5; notes.append("dull whites: let them breathe")
    if sat > 48:
        s["saturation"] *= 0.3; notes.append(f"already colourful (sat {sat:.0f}): barely any saturation added")
    elif sat < 18:
        s["saturation"] += 5; notes.append(f"muted colours (sat {sat:.0f}): a touch more colour")
    return s, notes


def clamp(s):
    return {k: max(LIMITS[k][0], min(LIMITS[k][1], float(s.get(k, 0)))) for k in SLIDERS}


def curve(s):
    """Tone curve points for shadows / highlights / whites / brilliance (monotonic)."""
    sh, hl, wt, br = s["shadows"] / 100, s["highlights"] / 100, s["whites"] / 100, s["brilliance"] / 100
    pts = [(0, 0), (0.08, 0.08 + sh * 0.045), (0.2, 0.2 + sh * 0.09 + br * 0.03), (0.35, 0.35 + br * 0.06 + sh * 0.04),
           (0.5, 0.5 + br * 0.03), (0.75, 0.75 + hl * 0.09 - br * 0.02), (0.92, 0.92 + hl * 0.04 + wt * 0.06), (1, 1 + min(wt, 0) * 0.1)]
    out, last = [], -1
    for x, y in pts:
        y = max(min(y, 1.0), last + 0.004, 0.0); out.append(f"{x:.3f}/{y:.4f}"); last = y
    return " ".join(out)


def chain(s, intensity=1.0):
    s = {k: v * intensity for k, v in s.items()}
    f = [f"curves=m='{curve(s)}'",
         f"eq=brightness={s['brightness'] / 100 * 0.2:.4f}:contrast={1 + s['contrast'] / 100 * 0.6:.4f}:saturation={1 + s['saturation'] / 100 * 0.55:.4f}"]
    if s["saturation"]: f.append(f"vibrance=intensity={s['saturation'] / 100 * 0.6:.4f}")   # skin-safe colour (affects muted colours more)
    if abs(s["hue"]) > 0.5: f.append(f"hue=h={s['hue'] * 0.2:.2f}")
    if s["temp"] or s["tint"]:
        t, g = s["temp"] / 100, s["tint"] / 100
        f.append(f"colorbalance=rm={t * 0.12:.3f}:bm={-t * 0.12:.3f}:gm={-g * 0.1:.3f}:rh={t * 0.06:.3f}:bh={-t * 0.06:.3f}")
    if s["clarity"]: f.append(f"unsharp=lx=13:ly=13:la={s['clarity'] / 100 * 1.6:.3f}:cx=3:cy=3:ca=0")   # local contrast (midtone pop)
    if s["sharpen"]: f.append(f"unsharp=lx=5:ly=5:la={s['sharpen'] / 100 * 0.85:.3f}:cx=3:cy=3:ca=0")
    return ",".join(f)


def build(video, look=None, screen=False):
    """-> (ffmpeg filter chain, report). look = plan['look']; look['grade'] holds slider overrides / auto / intensity."""
    g = dict((look or {}).get("grade") or {})
    if g.get("off"): return "null", {"off": True}
    base = dict(SCREEN if screen else BASE)
    user = {k: g[k] for k in SLIDERS if k in g}
    st = measure(video) if g.get("auto", True) else {}
    s, notes = adapt(dict(base, **user), st) if not screen else (dict(base, **user), [])
    for k, v in user.items(): s[k] = v           # a slider the user set by hand is never auto-changed
    s = clamp(s); inten = max(0.0, min(1.5, float(g.get("intensity", 1.0))))
    return chain(s, inten), {"measured": st, "sliders": {k: round(v, 1) for k, v in s.items()}, "intensity": inten, "notes": notes}


if __name__ == "__main__":
    import sys
    c, r = build(sys.argv[1], json.loads(sys.argv[2]) if len(sys.argv) > 2 else None)
    print(c); print(json.dumps(r, indent=1))
