from common import *
# (name, layout F=full-bleed / S=2-up split, first word idx, last word idx, pad_before, pad_after)
BR = [
 ("dumbbell_smile", "F", 2, 3, 0.05, 0.30),
 ("squat",          "S", 12, 15, 0.05, 0.10),
 ("busy",           "F", 18, 19, 0.05, 0.55),
 ("gym",            "F", 28, 32, 0.05, 0.40),
 ("yoga",           "S", 35, 39, 0.05, 0.10),
 ("elder_lunch",    "S", 84, 90, 0.05, 0.10),
 ("stretch",        "F", 103, 105, 0.05, 0.35),
 ("couple_walk",    "F", 109, 111, 0.05, 0.40),
 ("grandparents_play", "S", 115, 116, 0.05, 0.10),
 ("grandma_kids",   "S", 117, 122, 0.05, 0.05),
 ("bed",            "F", 124, 125, 0.05, 0.40),
 ("pray",           "F", 169, 173, 0.05, 0.30),
 ("squat",          "S", 191, 195, 0.05, 0.05),
]
wins = []
for name, lay, i0, i1, pb, pa in BR:
    a = t_of(i0) - pb; b = te_of(i1) + pa
    wins.append({"name": name, "lay": lay, "a": a, "b": b})
for k in range(len(wins) - 1):
    if wins[k]["b"] > wins[k + 1]["a"] - 0.12: wins[k]["b"] = wins[k + 1]["a"] - 0.12
rs = sorted(starts.items())
for x in wins:
    nxt = [v for r, v in rs if v > x["a"]]
    if nxt: x["b"] = min(x["b"], nxt[0] - 0.05)
if __name__ == "__main__":
    for x in wins: print(f'{x["name"]:18s} {x["lay"]}  {x["a"]:6.2f} -> {x["b"]:6.2f}  ({x["b"]-x["a"]:.2f}s)')
