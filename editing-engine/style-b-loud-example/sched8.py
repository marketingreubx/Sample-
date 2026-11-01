from common8 import *
BR = [("product", "F", 16, 19, 0.0, 0.15), ("laptop_man", "S", 30, 35, 0.05, 0.1), ("money", "F", 61, 64, 0.05, 0.25),
      ("phone_typing", "S", 161, 168, 0.05, 0.1), ("crowd", "F", 183, 191, 0.05, 0.2), ("filming", "F", 229, 235, 0.05, 0.05),
      ("website", "F", 262, 267, 0.05, 0.0)]
wins = [{"name": n, "lay": l, "a": t_of(i0) - pb, "b": te_of(i1) + pa} for n, l, i0, i1, pb, pa in BR]
for x in wins:   # never run across a shot change except the website cut-away, which hides the seg-3 jump
    for a, b, f in SHOTS:
        if x["a"] < a < x["b"] and x["name"] != "website": x["b"] = a - 0.04
    if x["name"] == "website": x["b"] = segs[3]["o"] + 0.3
if __name__ == "__main__":
    for x in wins: print(f'{x["name"]:13s} {x["lay"]} {x["a"]:6.2f}-{x["b"]:6.2f}')
