"""B-roll suggestions from the transcript: what to film / grab, where it goes, and a search query for stock sites.

One suggestion every ~6-9 s on the most concrete thing being said. Types:
  screen  - a screen recording / screenshot (apps, websites, tools, DMs, dashboards)
  stock   - a stock / filmed clip (objects, places, actions)
  photo   - a personal photo or receipt (results, money, clients, before/after)
  graphic - no footage needed, the engine animates it (numbers, lists, comparisons)
Writes <work>/broll_suggestions.json and .md; the studio shows them in the B-roll tab. Drop the chosen file into
<work>/broll/ with the suggested file name and it is used on the next render.
"""
import json, re
from autoplan import STOP, sentences, clean

SCREEN = set("app apps website site page dashboard instagram tiktok youtube linkedin facebook whatsapp email inbox dm dms message messages "
             "chatgpt claude gemini canva capcut notion google analytics stripe shopify calendar booking link bio comments post posts reel reels "
             "story stories prompt prompts tool tools software ai template templates spreadsheet sheet funnel checkout".split())
PHOTO = set("result results revenue sales client clients customer customers testimonial review reviews before after money paid payment "
            "invoice income profit bookings booked transformation".split())
VERBISH = re.compile(r"(ing|ed|ly)$")
CONCRETE = set("phone laptop camera coffee office desk car house home gym city street money cash card book notebook kitchen food water "
               "skin face hands team meeting studio light mirror product products bottle cream salon spa client clients computer keyboard "
               "calendar clock time door bed plane airport beach night morning".split())


def _phrase(words, i):
    """Noun phrase around word i (adjective + noun)."""
    j = i
    while j - 1 >= 0 and clean(words[j - 1]["w"]).lower() not in STOP and i - j < 2: j -= 1
    return " ".join(clean(words[k]["w"]).lower() for k in range(j, i + 1))


def suggest(words, every=6.5):
    out, last = [], -99.0
    for s in sentences(words):
        if not s or words[s[0]]["s"] - last < every: continue
        best, bs, kind = None, 0, "stock"
        for i in s:
            w = clean(words[i]["w"]).lower()
            if not w or w in STOP or len(w) < 3: continue
            sc, k = 0, "stock"
            if w in SCREEN: sc, k = 6, "screen"
            elif w in PHOTO: sc, k = 5, "photo"
            elif w in CONCRETE: sc, k = 5, "stock"
            elif re.search(r"\d", w): sc, k = 4, "graphic"
            elif words[i]["w"][:1].isupper() and i and words[i - 1]["w"][-1:] not in ".?!": sc, k = 4, "screen"
            elif not VERBISH.search(w) and len(w) > 4: sc = 1.5 + len(w) / 6
            if sc > bs: best, bs, kind = i, sc, k
        if best is None or bs < 2.5: continue
        ph = _phrase(words, best); line = " ".join(words[i]["w"] for i in s)
        idea = {"screen": f"Screen recording / screenshot of {ph}", "photo": f"Real photo or screenshot of your {ph} (proof)",
                "graphic": f"Animated number / graphic for '{ph}' (engine does this, nothing to film)", "stock": f"Clip of {ph}"}[kind]
        out.append({"t": words[s[0]]["s"], "w": [s[0], s[-1]], "word": best, "kind": kind, "idea": idea, "line": line,
                    "query": f"{ph} vertical video" if kind == "stock" else ph, "file": re.sub(r"[^a-z0-9]+", "-", ph).strip("-")[:40] + (".mp4" if kind in ("stock", "screen") else ".jpg")})
        last = words[s[0]]["s"]
    return out


def write(work, words):
    sug = suggest(words)
    json.dump(sug, open(f"{work}/broll_suggestions.json", "w"), indent=1)
    md = ["# B-roll suggestions", "", "| at | type | what to add | file name | line |", "|---|---|---|---|---|"]
    md += [f"| {x['t']:.1f}s | {x['kind']} | {x['idea']} | `{x['file']}` | {x['line'][:70]} |" for x in sug]
    open(f"{work}/broll_suggestions.md", "w").write("\n".join(md) + "\n")
    return sug
