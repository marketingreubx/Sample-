import sys, json
sys.path.insert(0, "/tmp/claude-0/-home-user-Sample-/1667514b-ba1c-53c3-9a16-c019b68c5923/scratchpad/v6")
import os; _cwd = os.getcwd(); os.chdir(sys.path[0])
from common6 import *          # w, segs, words, t_of, te_of, T, RAW, V5, SFXD, W, H, FPS
os.chdir(_cwd)
V6 = V; V7 = sys.path[0].replace("/v6", "/v7")
END = 2.4                      # Style A black end card after the last word
TT = T + END
def seg_of(t): return max(k for k, s in enumerate(segs) if s["o"] <= t + 1e-6)
def src(t): s = segs[seg_of(t)]; return s["s"] + (t - s["o"])
def cut_before(i):             # midpoint of the gap before word i (output time)
    j = KEEP.index(i); a = te_of(KEEP[j - 1]) if j else 0; return (a + t_of(i)) / 2
# Style A framing plan: hard cuts only, each one changes the framing (W = wide, Tt = tight)
PLAN = [(0.0, "Tt"), (segs[1]["o"], "W"), (cut_before(33), "Tt"), (cut_before(46), "W"), (segs[2]["o"], "Tt"),
        (segs[3]["o"], "W"), (cut_before(91), "Tt"), (segs[4]["o"], "W"), (cut_before(123), "Tt"), (segs[5]["o"], "W"),
        (segs[6]["o"], "Tt"), (cut_before(159), "W"), (cut_before(174), "Tt"), (cut_before(190), "W"), (segs[7]["o"], "S")]
SHOTS = [(a, PLAN[k + 1][0] if k + 1 < len(PLAN) else T, f) for k, (a, f) in enumerate(PLAN)]
