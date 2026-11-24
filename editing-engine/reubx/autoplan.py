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


NUMW = {"one": 1, "two": 2, "three": 3, "four": 4, "five": 5, "six": 6, "seven": 7, "eight": 8, "nine": 9, "ten": 10,
        "first": 1, "second": 2, "third": 3, "fourth": 4, "fifth": 5, "sixth": 6, "seventh": 7, "eighth": 8, "ninth": 9, "tenth": 10}
LABEL = {"step", "mistake", "tip", "rule", "lesson", "reason", "way", "hook", "sign", "secret", "habit"}
UNITS = {"months", "month", "days", "day", "weeks", "week", "years", "year", "hours", "minutes", "million", "thousand", "k", "percent", "followers", "views", "clients"}


def _num(w):
    w = clean(w).lower(); return NUMW.get(w) or (int(w) if w.isdigit() and int(w) < 50 else None)


def headers(words, sents):
    """Numbered sections ('Number one, solutions.' / 'Step two...' / 'The third mistake is...') -> on-screen header."""
    out = []
    for s in sents:
        ws = [clean(words[i]["w"]).lower() for i in s]
        for k in range(min(4, len(ws) - 1)):
            n = None; lab = ""
            if ws[k] == "number" and k + 1 < len(ws): n = _num(ws[k + 1]); start = k + 2
            elif ws[k] in LABEL and k + 1 < len(ws) and _num(ws[k + 1]): n = _num(ws[k + 1]); lab = ws[k]; start = k + 2
            elif ws[k] in ("first", "second", "third", "fourth", "fifth") and k + 1 < len(ws) and ws[k + 1] in LABEL: n = NUMW[ws[k]]; lab = ws[k + 1]; start = k + 2
            elif k == 0 and _num(ws[0]) and words[s[0]]["w"][-1:] in ",." : n = _num(ws[0]); start = 1
            if n:
                rest = []
                for j in s[start:]:
                    c = clean(words[j]["w"])
                    if c.lower() in ("is", "are", "the", "a", "an", "to") and not rest: continue
                    if not c: continue
                    rest.append(c)
                    if len(rest) >= 4 or words[j]["w"][-1:] in ",.?!": break
                while rest and rest[-1].lower() in STOP: rest.pop()          # no dangling 'about' / 'your'
                if not rest: break
                title = " ".join(rest)
                txt = f"{lab.title()} #{n}: {title}" if lab else f"{n}. {title.upper()}"
                out.append({"w": [s[0], s[-1]], "text": txt.strip().rstrip(":"), "style": "script" if lab else "box"}); break
    return out


def word_cards(words, sents, skip, gap=14.0):
    """Short punchy number phrases ('six months', '1 million followers') -> full-screen word card (~1.2 s)."""
    out, last = [], -99.0
    for s in sents:
        for k, i in enumerate(s):
            n = _num(words[i]["w"]) or (clean(words[i]["w"]).replace(",", "").replace("$", "").isdigit() and clean(words[i]["w"]))
            if not n or k + 1 >= len(s) or i in skip: continue
            u = clean(words[s[k + 1]]["w"]).lower()
            if u in UNITS and words[i]["s"] - last >= gap and words[i]["s"] > 2.0:
                num = clean(words[i]["w"]); num = str(NUMW.get(num.lower(), num))
                lead = " ".join(clean(words[j]["w"]).lower() for j in s[max(0, k - 3):k])
                out.append({"type": "word", "w": [i, s[k + 1]], "lead": lead, "text": f"{num} {u.title()}", "dark": False}); last = words[i]["s"]
                break
    return out


def autoplan(words, style="b-loud", look=None, screen=False):
    plan = {"style": style, "fix": {}, "remove": [] if screen else retake_hints(words), "look": look or {}}
    if screen: plan["screen"] = True
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
        key = {clean(words[i]["w"]).lower() for i in punch}
        for s_ in sents:                                   # one highlighted word per sentence (references: yellow / script emphasis)
            b = max(s_, key=score)
            if score(b) >= 3: key.add(clean(words[b]["w"]).lower())
        plan["key"] = sorted(key)
        plan["headers"] = headers(words, sents)
        plan["cards"] = plan.get("cards", []) + word_cards(words, sents, {h["w"][0] for h in plan["headers"]})
        if cta: plan["cta"] = cta
    return plan


if __name__ == "__main__":
    import sys
    w = json.load(open(sys.argv[1])); print(json.dumps(autoplan(w, sys.argv[2] if len(sys.argv) > 2 else "b-loud"), indent=1)[:3000])
