---
name: decision-helper
description: "Helps the user make a hard decision (business, career, money, client, hiring, pricing, personal) by clarifying the real question, surfacing their priorities, listing options including ones they missed, scoring options against weighted criteria, testing the downside with a pre-mortem and reversibility check, and recommending a next step or a small experiment. Use whenever the user is stuck between options, asks 'should I', 'which is better', 'help me decide', 'I can't make up my mind'."
---

# Decision Helper

Most hard decisions are hard because the real question is unclear, the priorities are unspoken, or the fear of the downside is vague. This skill makes each of those explicit, so the decision becomes clear or the next small step does.

## What to collect

1. The decision and the options the user sees.
2. Why it matters and the deadline.
3. What they care about most (they may not know yet).
4. Constraints: money, time, people, obligations.

## The method

### 1. Clarify the real question
Restate it. Often "Should I quit my job?" is really "How can I replace my income safely in 6 months?"

### 2. Priorities
Ask the user to name and weight 4 to 6 criteria (income, stability, growth, time, enjoyment, risk, relationships). If they are unsure, suggest criteria and ask them to rank.

### 3. Options
Their options plus 1 to 3 they didn't mention (a hybrid, a smaller test, a delay with a condition).

### 4. Score
Weighted decision matrix with brief reasons.

### 5. Stress test
- **Pre-mortem:** "It's a year later and this went badly. Why?"
- **Reversibility:** two-way door (easy to undo) or one-way door (hard to undo)?
- **Worst realistic case** and how they would handle it.
- **10/10/10:** how will they feel in 10 days, 10 months, 10 years?

### 6. Recommendation
Clear recommendation with reasoning, or the smallest experiment to reduce uncertainty, with a date to decide.

## Rules

- It's the user's life. Recommend, don't command.
- Name biases gently (sunk cost, fear of missing out, status quo).
- For legal, medical or major financial decisions, suggest a qualified professional.

## Output format

```
THE REAL QUESTION
...
YOUR PRIORITIES (weighted)
...
OPTIONS
...
SCORES
| Option | Criterion 1 (w) | ... | Total |
STRESS TEST
Pre-mortem: ... Reversible: ... Worst case: ... 10/10/10: ...
RECOMMENDATION
...
NEXT STEP (by [date])
...
```

## Final check

- [ ] Real question restated
- [ ] Priorities weighted by the user
- [ ] Downside tested
- [ ] Concrete next step with a date
