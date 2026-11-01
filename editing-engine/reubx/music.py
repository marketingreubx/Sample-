import numpy as np, wave, sys
SR = 44100; BPM = 100; beat = 60 / BPM; dur = float(sys.argv[2])
n = int(SR * (dur + 2)); L = np.zeros(n); R = np.zeros(n)
t_all = np.arange(n) / SR
def note(f): return 440 * 2 ** ((f - 69) / 12)
prog = [[48, 52, 55, 60], [43, 47, 50, 55], [45, 48, 52, 57], [41, 45, 48, 53]]   # Fmaj7 Em7 Dm7 Cmaj7
bar = 4 * beat
rng = np.random.default_rng(1)
for b in range(int(dur / bar) + 2):
    ch = prog[b % 4]; s0 = int(b * bar * SR); m = int(bar * SR)
    if s0 + m > n: break
    t = np.arange(m) / SR
    env = np.minimum(t / 0.6, 1) * np.exp(-t / 9)
    for i, nt in enumerate(ch):
        f = note(nt + 12)
        tone = (np.sin(2 * np.pi * f * t) + 0.25 * np.sin(2 * np.pi * 2 * f * t) + 0.08 * np.sin(2 * np.pi * 3 * f * t)) * env * 0.05
        e = min(s0 + m, n) - s0
        pan = 0.35 + 0.1 * i
        L[s0:s0 + e] += tone[:e] * (1 - pan); R[s0:s0 + e] += tone[:e] * pan
    # soft sub bass on the root
    fb = note(ch[0] - 12); be = np.exp(-t / 1.4) * np.minimum(t / 0.02, 1)
    bass = np.sin(2 * np.pi * fb * t) * be * 0.10; e = min(s0 + m, n) - s0
    L[s0:s0 + e] += bass[:e]; R[s0:s0 + e] += bass[:e]
    for k in range(4):           # kick on 1 and 3, hats on off-beats
        if k % 2 == 0:
            ks = s0 + int(k * beat * SR); kt = np.arange(int(0.25 * SR)) / SR
            kick = np.sin(2 * np.pi * (50 + 60 * np.exp(-kt * 30)) * kt) * np.exp(-kt * 14) * 0.22
            e = min(ks + len(kick), n) - ks; L[ks:ks + e] += kick[:e]; R[ks:ks + e] += kick[:e]
        hs = s0 + int((k + 0.5) * beat * SR); ht = np.arange(int(0.05 * SR)) / SR
        hat = rng.normal(0, 1, len(ht)) * np.exp(-ht * 90) * 0.025
        hat = np.diff(np.concatenate([[0], hat]))
        e = min(hs + len(hat), n) - hs; L[hs:hs + e] += hat[:e] * 0.8; R[hs:hs + e] += hat[:e]
st = np.stack([L, R], 1)[: int(SR * dur)]
fade = np.minimum(1, np.minimum(np.arange(len(st)) / (SR * 1.0), (len(st) - np.arange(len(st))) / (SR * 2.0)))[:, None]
st = st * fade; st = st / np.abs(st).max() * 0.8
with wave.open(sys.argv[1], "wb") as w:
    w.setnchannels(2); w.setsampwidth(2); w.setframerate(SR); w.writeframes((st * 32767).astype("<i2").tobytes())
