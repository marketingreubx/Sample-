"""Instant first-cut plan with no AI step: retakes removed, framing cuts, punch-ins on numbers/names/strong words,
keyword blocks or accent words, CTA detection. Claude only refines it (or applies the user's notes) when asked."""
import json, re
from core import retake_hints, norm

STOP = set("a an the and or but so to of in on at for with from by is are was were be been being am i you he she it we they me my your our their this that these those "
           "there here just really very actually basically like um uh okay ok yeah well now then also too can could would should will do does did have has had not no "
           "what who when where why how if because about into out up down over than as all any some more most".split())
STRONG = set("never always best worst biggest secret free money million thousand mistake truth stop start everything nothing simple easy fast "
             "guaranteed proven results growth sales clients bookings dms".split())


def sentences(words):
    out, cur = [], []
    for i, x in enumerate(words):
        cur.append(i)
        gap = words[i + 1]["s"] - x["s"] if i + 1 < len(words) else 9
        if x["w"][-1:] in ".?!" or gap > 0.9 or len(cur) >= 22: out.append(cur); cur = []
    if cur: out.append(cur)
    return out


def clean(w): return re.sub(r"[^\w$%'.,-]", "", w).strip(".,")


def autoplan(words, style="b-loud", look=None):
    plan = {"style": style, "fix": {}, "remove": retake_hints(words), "look": look or {}}
    removed = {i for a, b in plan["remove"] for i in range(a, b + 1)}
    sents = [[i for i in s if i not in removed] for s in sentences(words)]
    sents = [s for s in sents if s]
    # framing changes every ~5 s at sentence starts; punch-ins on numbers / strong words
    shots, last = [], 0.0
    for s in sents[1:]:
        if words[s[0]]["s"] - last >= 5: shots.append(s[0]); last = words[s[0]]["s"]
    plan["shots"] = shots
    def score(i):
        w = clean(words[i]["w"]).lower()
        if not w or w in STOP: return 0
        return (5 if re.search(r"\d|\$", w) else 0) + (3 if w in STRONG else 0) + (2 if words[i]["w"][:1].isupper() and i and words[i - 1]["w"][-1:] not in ".?!" else 0) + min(len(w), 9) / 3
    punch, lastp = [], -9
    for s in sents:
        best = max(s, key=score)
        if score(best) >= 3 and words[best]["s"] - lastp >= 4: punch.append(best); lastp = words[best]["s"]
    plan["punch"] = punch
    # call to action ("comment X")
    cta = None
    for i, x in enumerate(words):
        if clean(x["w"]).lower() == "comment" and i + 1 < len(words):
            cta = {"w": [i, min(i + 12, len(words) - 1)], "keyword": clean(words[i + 1]["w"]).upper()}
    if style.startswith("a"):
        accent = []
        for s in sents:
            cand = [i for i in s if score(i) >= 2.3]
            accent += cand[: max(1, len(s) // 6)]
        plan["accent"] = accent
        first = sents[0]
        hook = []
        for i in first:                          # short hook phrase: up to 5 words, stop at the first comma
            hook.append(i)
            if len(hook) >= 5 or words[i]["w"][-1:] in ",.?!": break
        if len(hook) >= 3:
            plan["header"] = {"w": [hook[0], hook[-1]], "main": " ".join(clean(words[i]["w"]) for i in hook).lower()}
        if cta: plan["end"] = {"text": f"Comment {cta['keyword']}.", "sub": "and I'll send it to you"}
    else:
        blocks, counts, lastb = [], [], -9
        for s in sents:
            if words[s[0]]["s"] - lastb < 4.5 or len(s) < 3: continue
            if cta and s[0] >= cta["w"][0]: break
            num = [i for i in s if re.search(r"\d", words[i]["w"])]
            if num:
                i = num[0]; to = clean(words[i]["w"])
                if i + 1 in s and clean(words[i + 1]["w"]).lower() in ("million", "thousand", "k", "percent", "%"):
                    to += {"million": "M", "thousand": "K", "k": "K", "percent": "%", "%": "%"}[clean(words[i + 1]["w"]).lower()]
                lead = " ".join(clean(words[j]["w"]) for j in s[max(0, s.index(i) - 3): s.index(i)]).lower()
                counts.append({"w": [i, min(i + 2, s[-1])], "to": to.upper(), "lead": lead}); lastb = words[i]["s"]; continue
            runs, cur = [], []
            for i in s:                       # runs of content words = natural keyword phrases
                if score(i) > 0: cur.append(i)
                else:
                    if cur: runs.append(cur)
                    cur = []
            if cur: runs.append(cur)
            runs = [r[:3] for r in runs if sum(score(i) for i in r) >= 2.5]
            if not runs: continue
            r = max(runs, key=lambda r: sum(score(i) for i in r) + 0.5 * len(r))
            big = " ".join(clean(words[j]["w"]) for j in r).upper()
            k0 = s.index(r[0]); pre = s[max(0, k0 - 4):k0]
            lead = " ".join(clean(words[j]["w"]) for j in pre).lower()
            blocks.append({"w": [pre[0] if pre else r[0], r[0], r[-1]], "lead": lead, "text": big}); lastb = words[r[0]]["s"]
        plan["blocks"], plan["counts"] = blocks, counts
        plan["key"] = sorted({clean(words[i]["w"]).lower() for i in punch})
        if cta: plan["cta"] = cta
    return plan


if __name__ == "__main__":
    import sys
    w = json.load(open(sys.argv[1])); print(json.dumps(autoplan(w, sys.argv[2] if len(sys.argv) > 2 else "b-loud"), indent=1)[:3000])
