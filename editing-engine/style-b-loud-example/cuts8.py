"""Cut list v6: her original order, only retakes / filler / restarts removed, cut points inside real silences."""
import json, re
P = "/tmp/claude-0/-home-user-Sample-/1667514b-ba1c-53c3-9a16-c019b68c5923/scratchpad"
V6 = f"{P}/v8"
RAW = "/root/.claude/uploads/1667514b-ba1c-53c3-9a16-c019b68c5923/5a9325cd-copy_35F340E5-EA4B-4F09-A912-4C2D067E46FA.mov"
w = json.load(open(f"{V6}/transcript.json"))
for i, t in {4: 'Jessi', 5: 'Jean', 9: '$700K', 17: 'her', 23: 'Claude.', 39: '$500K', 54: 'Meg', 68: 'her', 75: 'Claude.', 85: 'Claude', 107: 'Claude', 121: 'Claude,', 126: 'Claude', 144: 'Caption', 145: 'Writer', 146: '', 155: 'Caption', 156: 'Writer', 157: '', 209: 'Viral', 223: 'talking', 274: 'slop.', 277: 'CLAUDE', 284: 'Claude', 190: 'using', 191: 'it.'}.items(): w[i]["w"] = t
SIL = json.load(open(f"{V6}/silences.json"))["sil"]
# removed: repeated takes of "there's nothing stopping you/me" (50-64), "you know" (135-136),
# the restart "you have constantly. Taking" (142-145), and the unfinished last words "you can be." (211-213)
REMOVE = set(range(138, 149))
KEEP = [i for i in range(len(w)) if i not in REMOVE]
PAD_IN, PAD_OUT, MAXGAP, KEEPGAP = 0.22, 0.16, 0.45, 0.30

def sil_before(t):   # silence that ends just before time t (the word's onset)
    c = [s for s in SIL if s[1] <= t + 0.06 and s[1] >= t - 0.6]
    return c[-1] if c else None
def sil_after(t):    # first silence that starts after time t (the word's offset)
    c = [s for s in SIL if s[0] >= t + 0.05]
    return c[0] if c else None

import numpy as np
ENV = np.load(f"{V6}/env_db.npy")
def valley(t0, t1):   # quietest 10 ms point between t0 and t1
    i0, i1 = max(int(t0 * 100), 0), max(int(t1 * 100), int(t0 * 100) + 1)
    return (i0 + int(np.argmin(ENV[i0:i1]))) / 100

runs, cur = [], [KEEP[0]]
for i in KEEP[1:]:
    if i == cur[-1] + 1: cur.append(i)
    else: runs.append(cur); cur = [i]
runs.append(cur)
segs = []
for r in runs:
    i0, i1 = r[0], r[-1]
    sb = sil_before(w[i0]["s"])
    if i0 == 0: start = 0.0
    elif sb and sb[1] > w[i0 - 1]["s"] + 0.15: start = max(sb[1] - PAD_IN, sb[0])
    else: start = valley(w[i0]["s"] - 0.28, w[i0]["s"] + 0.02)
    sa = sil_after(w[i1]["s"])
    nxt = w[i1 + 1]["s"] if i1 + 1 < len(w) else 1e9
    if sa and sa[0] < nxt: end = min(sa[0] + PAD_OUT, sa[1], nxt - 0.04)
    elif nxt < 1e9: end = valley(nxt - 0.28, nxt + 0.02)
    else: end = w[i1]["e"]
    # shorten long pauses inside the run to KEEPGAP, cutting in the middle of the silence
    pieces = [[start, end]]
    for s0, s1 in SIL:
        if s1 - s0 < MAXGAP or s0 <= start or s1 >= end: continue
        last = pieces[-1]
        if last[0] < s0 and s1 < last[1]:
            pieces[-1] = [last[0], s0 + PAD_OUT]; pieces.append([max(s1 - PAD_IN, s0 + PAD_OUT + 0.01), last[1]])
    for p in pieces: segs.append({"s": round(p[0], 3), "e": round(p[1], 3), "run": (i0, i1)})
o = 0
for sg in segs: sg["o"] = round(o, 3); o += sg["e"] - sg["s"]
T = o
def out_t(src):
    for sg in segs:
        if sg["s"] - 0.001 <= src <= sg["e"] + 0.001: return sg["o"] + src - sg["s"]
    return None
words = []
for i in KEEP:
    t = out_t(w[i]["s"])
    if t is None:   # onset inside a trimmed pause: snap to the next segment start
        t = next(sg["o"] for sg in segs if sg["s"] > w[i]["s"])
    words.append({"i": i, "w": w[i]["w"], "o": round(t, 3)})
words.sort(key=lambda x: x["o"])
for k, x in enumerate(words):
    nx = words[k + 1]["o"] if k + 1 < len(words) else T
    x["oe"] = round(min(nx, x["o"] + 0.7), 3)
WO = {x["i"]: x for x in words}
def t_of(i): return WO[i]["o"]
def te_of(i): return WO[i]["oe"]
if __name__ == "__main__":
    json.dump({"segs": segs, "T": T, "words": words}, open(f"{V6}/cut.json", "w"), indent=1)
    print(len(segs), "segments, runtime", round(T, 2), "s (raw 90 s)")
    for sg in segs: print(f'{sg["s"]:7.2f}-{sg["e"]:7.2f}  run {sg["run"]}')
