"""Procedural sound design: every effect is generated to the exact length of the motion it sits under.
Subtle by design (premium edits are felt, not heard). All return stereo float32 at 48 kHz."""
import numpy as np
SR = 48000
_rng = np.random.default_rng(7)


def _t(d): return np.arange(int(SR * d)) / SR


def _bandnoise(n, f_lo, f_hi):
    """Noise whose spectral centre sweeps from f_lo to f_hi (simple one-pole bank, cheap and smooth)."""
    x = _rng.normal(0, 1, n)
    fc = np.geomspace(max(f_lo, 40), max(f_hi, 40), n)
    a = np.exp(-2 * np.pi * fc / SR)
    y = np.zeros(n); lp = 0.0; lp2 = 0.0
    for i in range(n):              # 2-pole low-pass at fc, then subtract a slower low-pass -> band
        lp = (1 - a[i]) * x[i] + a[i] * lp
        lp2 = (1 - a[i] ** 0.25) * lp + a[i] ** 0.25 * lp2
        y[i] = lp - 0.7 * lp2
    return y / (np.abs(y).max() + 1e-9)


def _stereo(m, pan0=0.0, pan1=0.0):
    p = np.linspace(pan0, pan1, len(m))
    return np.stack([m * np.sqrt((1 - p) / 2), m * np.sqrt((1 + p) / 2)], 1).astype(np.float32)


def whoosh(d=0.45, up=True):
    """Air swoosh matched to a transition: rises to the cut point (70%) then decays, pans across."""
    n = int(SR * d); t = np.linspace(0, 1, n)
    env = np.where(t < 0.7, (t / 0.7) ** 2.2, np.exp(-(t - 0.7) * 12))
    m = _bandnoise(n, 250, 3200 if up else 900) * env
    return _stereo(m * 0.9, -0.6, 0.6)


def swish(d=0.22):
    """Short soft swish for a card / text entering."""
    n = int(SR * d); t = np.linspace(0, 1, n)
    env = np.sin(np.pi * np.clip(t * 1.3, 0, 1)) ** 2
    return _stereo(_bandnoise(n, 1800, 5000) * env * 0.55, 0.3, -0.2)


def tick(f=2200):
    """UI tick (checkbox, chip, toggle)."""
    t = _t(0.06)
    m = np.sin(2 * np.pi * f * t) * np.exp(-t * 90) + _rng.normal(0, 1, len(t)) * np.exp(-t * 400) * 0.3
    return _stereo(m * 0.7)


def pop():
    """Soft bubble pop for small elements (pitch drop, no click)."""
    t = _t(0.09); f = 1100 * np.exp(-t * 28) + 260
    m = np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t * 38) * np.minimum(t / 0.002, 1)
    return _stereo(m * 0.8)


def hit(d=0.9):
    """Low cinematic hit for a punchline keyword: sub drop + short noise transient."""
    t = _t(d); f = 42 + 70 * np.exp(-t * 18)
    sub = np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-t * 4.5)
    trans = _bandnoise(len(t), 900, 300) * np.exp(-t * 35) * 0.35
    return _stereo((sub + trans) * 0.9)


def riser(d=0.8):
    """Tension riser into a reveal (ends exactly at the reveal)."""
    t = _t(d); u = t / d
    tone = np.sin(2 * np.pi * np.cumsum(220 + 880 * u ** 2) / SR) * 0.25
    m = (_bandnoise(len(t), 300, 6000) * 0.6 + tone) * u ** 2.5
    return _stereo(m * 0.7, -0.3, 0.3)


def chime():
    """Bright soft chime for a number landing / success."""
    t = _t(1.0)
    m = sum(a * np.sin(2 * np.pi * f * t) * np.exp(-t * k) for f, a, k in ((1318.5, 1, 4.5), (1975.5, .55, 6), (2637, .25, 8)))
    return _stereo(m * np.minimum(t / 0.004, 1) * 0.35)


def shimmer(d=0.7):
    """Sparkle tail for glow / premium reveals."""
    t = _t(d)
    m = sum(np.sin(2 * np.pi * (2400 + 900 * k) * t + k) * np.exp(-t * (5 + k)) for k in range(5)) * 0.06
    return _stereo(m * (0.5 + 0.5 * np.sin(2 * np.pi * 18 * t)), -0.4, 0.4)


def typing(d=0.8, rate=11):
    """Keyboard typing for a typed text field."""
    n = int(SR * d); m = np.zeros(n)
    for k in range(int(d * rate)):
        s = int((k / rate + _rng.uniform(0, 0.03)) * SR); c = tick(_rng.uniform(1500, 3200))[:, 0] * _rng.uniform(.5, 1)
        e = min(n, s + len(c)); m[s:e] += c[:e - s]
    return _stereo(m * 0.6)


def camera(d=0.25):
    """Very soft 'zoom' air for a camera punch-in."""
    n = int(SR * d); t = np.linspace(0, 1, n)
    return _stereo(_bandnoise(n, 120, 700) * np.sin(np.pi * t) ** 2 * 0.5)


KINDS = {"whoosh": whoosh, "swish": swish, "tick": tick, "pop": pop, "hit": hit, "riser": riser,
         "chime": chime, "shimmer": shimmer, "typing": typing, "camera": camera}


def render(events, total, out_wav):
    """events: list of (t, kind, dur_or_None, gain). Writes a stereo 48 kHz float mix of all effects."""
    import wave
    buf = np.zeros((int(SR * (total + 2)), 2), np.float32)
    for t, kind, d, g in events:
        fn = KINDS[kind]
        x = fn(d) if d and kind in ("whoosh", "swish", "hit", "riser", "shimmer", "typing", "camera") else fn()
        s = int(max(t, 0) * SR); e = min(len(buf), s + len(x)); buf[s:e] += x[:e - s] * g
    buf = buf[: int(SR * total)]
    pk = np.abs(buf).max()
    if pk > 0.98: buf *= 0.98 / pk
    with wave.open(out_wav, "wb") as w:
        w.setnchannels(2); w.setsampwidth(2); w.setframerate(SR); w.writeframes((buf * 32767).astype("<i2").tobytes())
