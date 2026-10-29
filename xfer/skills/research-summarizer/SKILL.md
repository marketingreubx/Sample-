---
name: research-summarizer
description: "Researches a question from the sources the user provides (articles, reports, papers, transcripts, notes, links content) and produces a clear, balanced brief: the direct answer, key findings with citations to each source, strength of evidence, where sources disagree, gaps and limitations, and recommended next steps for the user's decision. Use whenever the user wants research summarised, a literature or market overview, a comparison of sources, a briefing, or says 'summarise these articles', 'what does the research say about', 'help me understand this topic'."
---

# Research Summarizer

Good research summaries separate what is known, what is likely and what is unclear, and say where each claim comes from. The goal is a decision-ready brief, not a long book report.

## What to collect

1. The question the user needs answered and why (the decision it supports).
2. Sources (pasted text, files, links content). If the assistant can browse, say which sources were used; if not, work only from what is provided.
3. Audience for the summary and length.

## The method

### 1. Define the question
Restate it precisely. If it is too broad, split into sub-questions.

### 2. Read and extract
For each source: what it claims, the evidence type (study, data, survey, expert opinion, anecdote), sample or scope, date, possible bias or conflict of interest.

### 3. Synthesise
- **The answer:** 3 to 5 sentences.
- **Key findings:** each with source references [1], [2].
- **Evidence strength:** strong (multiple good sources agree), moderate, weak, or contested.
- **Disagreements:** where sources differ and possible reasons.
- **Gaps:** what the sources do not cover.
- **Implications:** what it means for the user's decision.
- **Next steps:** what to read, test or ask next.

### 4. Source list
Numbered, with title, author or outlet, date.

## Rules

- Never invent sources, quotes, statistics or citations.
- Distinguish correlation from causation.
- Note dates; older data may be outdated.
- Use plain language. Define technical terms.
- If sources are thin, say so clearly.

## Output format

```
QUESTION
...

SHORT ANSWER
...

KEY FINDINGS
1. ... [1][3] (Strong)
...

WHERE SOURCES DISAGREE
...

GAPS AND LIMITATIONS
...

WHAT THIS MEANS FOR YOU
...

NEXT STEPS
...

SOURCES
[1] ...
```

## Final check

- [ ] Every finding cites a provided source
- [ ] Evidence strength labelled
- [ ] Disagreements and gaps stated
- [ ] Implications for the user's decision
