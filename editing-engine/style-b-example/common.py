import json, re
P = "/tmp/claude-0/-home-user-Sample-/1667514b-ba1c-53c3-9a16-c019b68c5923/scratchpad"
V = f"{P}/vb"; V5 = f"{P}/v5"
RAW = "/root/.claude/uploads/1667514b-ba1c-53c3-9a16-c019b68c5923/c5af1cb6-copy_4D059C7E-5E14-45E4-8435-8A748FBD8CAE.mov"
SFXD = "/root/.claude/skills/synced/0db188dc-decc-43a9-9600-ccf5ff2eb3d7_c745dd7e-6786-4901-8d8c-8474de87d3a4/hormozi-shorts-editor/assets/sfx"
W, H = 1080, 1920
w = json.load(open(f"{V5}/work/transcript.json"))
for i, t in {1: "while", 4: "while", 35: "work", 125: "bedridden.", 134: "with", 200: "30", 207: "40"}.items():
    w[i]["w"] = t
sil = [tuple(map(float, l.split())) for l in open(f"{V5}/sil45.txt") if len(l.split()) == 2]
ORDER = [(197, 203), (204, 210), (1, 15), (16, 39), (65, 68), (69, 73), (80, 134), (137, 141),
         (146, 158), (161, 173), (175, 195), (74, 79)]
ZOOM = {0: 1.0, 1: 1.18, 2: 1.0, 3: 1.12, 4: 1.0, 5: 1.3, 7: 1.12, 8: 1.0, 9: 1.15, 10: 1.0, 11: 1.0}
FOCUS = {0: (0.5, 0.0), 1: (0.5, 0.0), 5: (0.62, 0.32)}
START_PAD = {1: 0.04, 146: 0.0, 161: 0.0}
subs = []
for ri, (a, b) in enumerate(ORDER):
    s = w[a]["s"] - START_PAD.get(a, 0.08)
    e = (w[b + 1]["s"] - 0.03) if b + 1 < len(w) else w[b]["e"] + 0.3
    e = min(e, w[b]["s"] + 1.2)
    pieces = [(s, e)]
    for x0, x1 in sil:
        if x1 - x0 < 0.45: continue
        nxt = []
        for p, q in pieces:
            c0, c1 = x0 + 0.1, x1 - 0.1
            if c0 < q and c1 > p:
                if c0 - p > 0.2: nxt.append((p, c0))
                if q - c1 > 0.2: nxt.append((c1, q))
            else: nxt.append((p, q))
        pieces = nxt
    for k, (p, q) in enumerate(pieces):
        z = ZOOM.get(ri, 1.0 if (len(subs) % 2 == 0) else 1.12)
        if ri == 6: z = 1.0 if k % 2 == 0 else 1.12
        subs.append({"r": ri, "s": p, "e": q, "z": z, "f": FOCUS.get(ri, (0.5, 0.42))})
o = 0
for u in subs: u["o"] = o; o += u["e"] - u["s"]
T = o
words = []
for i, x in enumerate(w):
    for u in subs:
        if u["s"] - 0.12 <= x["s"] < u["e"]:
            words.append({"i": i, "w": x["w"], "o": u["o"] + max(x["s"], u["s"]) - u["s"], "r": u["r"]}); break
words.sort(key=lambda x: x["o"])
for k, x in enumerate(words):
    nx = words[k + 1]["o"] if k + 1 < len(words) else T
    x["oe"] = min(nx, x["o"] + 0.7)
WO = {x["i"]: x for x in words}
def t_of(i): return WO[i]["o"]
def te_of(i): return WO[i]["oe"]
starts = {}
for u in subs: starts.setdefault(u["r"], u["o"])
HOOK_END = starts[2]
FPS = 30
