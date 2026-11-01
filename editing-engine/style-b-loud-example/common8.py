import json, sys
sys.path.insert(0, "/tmp/claude-0/-home-user-Sample-/1667514b-ba1c-53c3-9a16-c019b68c5923/scratchpad/v8")
from cuts8 import P, V6 as V8, RAW, w, segs, words, WO, t_of, te_of, T, KEEP
V = V8; V5 = f"{P}/v5"; V6 = f"{P}/v6"; W, H, FPS = 1080, 1920, 30
SFXD = "/root/.claude/skills/synced/0db188dc-decc-43a9-9600-ccf5ff2eb3d7_c745dd7e-6786-4901-8d8c-8474de87d3a4/hormozi-shorts-editor/assets/sfx"
def cut_before(i):
    j = KEEP.index(i); a = te_of(KEEP[j - 1]) if j else 0; t = (a + t_of(i)) / 2
    near = [s["o"] for s in segs if abs(s["o"] - t) < 0.7 and s["o"] > 0]
    return near[0] if near else t
SHOT_AT = [(0, "W"), (24, "Tt"), (50, "W"), (76, "Tt"), (89, "W"), (115, "Tt"), (149, "W"), ("seg2", "Tt"), (205, "W"), (236, "Tt"), ("seg3", "W"), (275, "Tt")]
PLAN = []
for k, f in SHOT_AT:
    t = 0.0 if k == 0 else segs[int(k[3:])]["o"] if isinstance(k, str) else cut_before(k)
    PLAN.append((t, f))
PLAN.sort()
SHOTS = [(a, PLAN[k + 1][0] if k + 1 < len(PLAN) else T, f) for k, (a, f) in enumerate(PLAN)]
def seg_of(t): return max(k for k, s in enumerate(segs) if s["o"] <= t + 1e-6)
