#!/usr/bin/env python3
"""Builds the AI Profit System prompt bundle (JSON) from the plain-text batch files in this folder.

Batch file format:
  ## page-slug | Page Title | category-slug | One sentence about the page
  Prompt title | role | goal | field one; field two; field three | step one; step two; step three
  (10 prompt lines per page)
"""
import glob, json, os, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))

RULES = """RULES:
- Specific to my situation and the details I gave you, never generic advice
- Plain, human language a real person would say
- No em dashes, no "unlock", "elevate", "game-changer", "dive in", "in today's world"
- Never invent results, testimonials or numbers: mark anything I did not give you as [PLACEHOLDER]

BEFORE YOU START:
- If any detail I left in [brackets] is missing, make a sensible assumption, state it in one line at the top, and keep going. Only ask me questions if the answer would change the whole output (3 questions maximum).

QUALITY CHECK (run this before you reply):
- Is every recommendation specific to my situation, not generic advice?
- Could I use the output today without rewriting it?
- Are all claims, numbers and testimonials real or clearly marked [PLACEHOLDER]?
- Is the language plain and human, with no AI tells?
Fix anything that fails before you answer.

Start now."""


def article(role):
    return "an" if role[:1].lower() in "aeiou" else "a"


def body(title, role, goal, fields, steps):
    ctx = "\n".join("- %s: [%s]" % (f[:1].upper() + f[1:], f[:1].lower() + f[1:]) for f in fields)
    tsk = "\n".join("%d. %s" % (i + 1, s[:1].upper() + s[1:]) for i, s in enumerate(steps))
    return (
        "You are %s %s. You give practical, step-by-step answers based on real-world experience, and you tailor everything to the details I give you.\n\n"
        "%s\n\nGOAL: %s\n\nCONTEXT:\n%s\n\nYOUR TASK:\n%s\n\n"
        "FORMAT:\n- Clear headings, short paragraphs and tables where they make it easier to use\n\n%s"
    ) % (article(role), role, title.upper(), goal, ctx, tsk, RULES)


def parse():
    pages = []
    for f in sorted(glob.glob(os.path.join(HERE, "batch-*.txt"))):
        cur = None
        for ln, raw in enumerate(open(f, encoding="utf-8").read().splitlines(), 1):
            line = raw.strip()
            if not line or line.startswith("#!"):
                continue
            if line.startswith("## "):
                p = [x.strip() for x in line[3:].split("|")]
                assert len(p) == 4, "%s:%d bad page header" % (f, ln)
                cur = {"s": p[0], "t": p[1], "c": p[2], "about": p[3], "prompts": []}
                pages.append(cur)
                continue
            p = [x.strip() for x in line.split("|")]
            assert len(p) == 5 and cur is not None, "%s:%d expected 5 parts, got %d" % (f, ln, len(p))
            if len(p[2].split()) < 6:
                p[2] = "%s, written specifically for my business and ready to use today." % p[0]
            fields = [x.strip() for x in p[3].split(";") if x.strip()]
            steps = [x.strip() for x in p[4].split(";") if x.strip()]
            assert len(fields) >= 3 and len(steps) >= 1, "%s:%d needs 3+ fields and 1+ steps" % (f, ln)
            for extra in ("give 2 alternative versions in a different tone so I can choose", "end with a short checklist of the next steps I should take today"):
                if len(steps) < 4:
                    steps.append(extra)
            cur["prompts"].append({"t": p[0], "d": p[2], "x": body(p[0], p[1], p[2], fields, steps)})
    return pages


def main():
    pages = parse()
    bad = [p["s"] for p in pages if len(p["prompts"]) != 10]
    if bad:
        sys.exit("pages without exactly 10 prompts: %s" % bad)
    slugs = [p["s"] for p in pages]
    assert len(slugs) == len(set(slugs)), "duplicate page slugs"
    banned = re.compile(r"\bunlock\b|game-changer|—|–", re.I)
    for p in pages:
        for q in p["prompts"]:
            head = q["t"] + q["d"]
            assert not banned.search(head), "banned word in %s: %s" % (p["s"], q["t"])
    out = os.path.join(HERE, "bundle.json")
    json.dump(pages, open(out, "w", encoding="utf-8"), ensure_ascii=False)
    n = sum(len(p["prompts"]) for p in pages)
    cats = {}
    for p in pages:
        cats[p["c"]] = cats.get(p["c"], 0) + len(p["prompts"])
    print("pages:", len(pages), "prompts:", n, "bytes:", os.path.getsize(out))
    print(json.dumps(cats, indent=0))


if __name__ == "__main__":
    main()
