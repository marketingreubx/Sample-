---
name: pdf-document-summarizer
description: "Summarizes long documents, PDFs, reports, contracts, policies, research papers, slide decks and transcripts for a specific reader and purpose: a one paragraph summary, key points, decisions and numbers, risks and red flags, deadlines and obligations, questions to ask and next steps, with page or section references. Use whenever the user uploads or pastes a long document and asks for a summary, key takeaways, TL;DR, briefing, or says 'what does this document say', 'summarise this report for my boss'."
---

# Document Summarizer

A good summary is written for someone and for a purpose. A CEO needs decisions and risks; a student needs concepts; a freelancer reading a brief needs deliverables and deadlines. This skill asks who it is for, then pulls out what matters with references so the user can verify.

## What to collect

1. The document.
2. Who the summary is for and what they will do with it (decide, sign, learn, act, present).
3. Desired length (one paragraph, one page, detailed).
4. Anything specific to look for.

## The method

1. **Skim the structure:** sections, headings, appendices.
2. **Extract:**
   - Purpose of the document.
   - Main points and conclusions.
   - Numbers that matter (costs, dates, targets, results).
   - Decisions required or made.
   - Obligations, deadlines and responsibilities (who must do what by when).
   - Risks, red flags, unusual terms or weak evidence.
   - What's missing or unclear.
3. **Write for the reader:** plain language, their priorities first.
4. **Reference** each point to a page, section or slide.
5. **Next steps:** what the reader should do, ask or check.

## Variants

- **TL;DR:** 3 bullets.
- **Executive summary:** one page.
- **Study notes:** concepts, definitions, examples, likely exam questions.
- **Action sheet:** tasks with owners and dates.

## Rules

- Only what the document says. Mark interpretations as such.
- Keep exact figures and dates; do not round without saying so.
- Quote short critical phrases exactly.
- If parts are unreadable (scanned images), say so.

## Output format

```
IN ONE PARAGRAPH
...

KEY POINTS
- ... (p. 4)

NUMBERS THAT MATTER
...

DECISIONS / OBLIGATIONS / DEADLINES
| What | Who | When | Ref |

RISKS AND RED FLAGS
...

UNCLEAR OR MISSING
...

NEXT STEPS
...
```

## Final check

- [ ] Written for the stated reader
- [ ] Every point referenced
- [ ] Numbers and dates exact
- [ ] Next steps included
