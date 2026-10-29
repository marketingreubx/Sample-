---
name: data-cleaner-analyst
description: "Cleans messy data (exports, CSVs, tables, survey results, analytics, sales or social media reports): profiles the columns, fixes duplicates, blanks, formats, dates, currencies and inconsistent labels, documents every change, then analyses it with the right summaries, comparisons and charts, showing the math, and explains what the numbers mean for a decision. Use whenever the user shares data and asks to clean it, analyse it, find insights, summarise numbers, compare periods, or says 'what does this data tell me', 'make sense of this export'."
---

# Data Cleaner and Analyst

Bad data leads to confident wrong decisions. This skill cleans first and documents what changed, then analyses only what the data can support, shows the math, and turns findings into decisions in plain language.

## What to collect

1. The data (paste, upload or describe the columns with sample rows).
2. The question or decision it should help with.
3. What each column means if not obvious.
4. Time period and any known issues.

## The method

### 1. Profile
Rows, columns, data types, missing values per column, duplicates, unusual values, date ranges, units.

### 2. Clean (and log every change)
- Remove exact duplicates; flag near duplicates.
- Standardise text (case, spelling, labels: "Lagos", "lagos", "LAG").
- Fix dates and numbers stored as text, currencies, units.
- Handle blanks (leave, fill with a stated rule, or exclude) and say which.
- Flag outliers (do not delete without reason).
- Split or combine columns where needed.

### 3. Analyse
Choose methods that fit the question:
- Totals, averages, medians, percentages, growth rates (show formulas).
- Comparisons by segment or period.
- Trends over time.
- Top and bottom performers.
- Simple correlations, clearly labelled as not proving cause.

### 4. Visualise
Recommend the right chart for each finding (line for trends, bar for comparisons, avoid pie charts with many slices) and what to highlight.

### 5. Interpret
3 to 5 findings in plain language, what they mean, how confident to be (sample size), and recommended actions.

## Rules

- Never invent or fill data silently.
- Show the math for every number.
- Small samples: say so.
- Correlation is not causation.

## Output format

```
DATA PROFILE
...
CLEANING LOG
| Issue | Rows affected | Fix |
CLEAN DATA (or how to apply the fixes)
...
ANALYSIS
1. Finding ... (math: ...)
CHARTS
...
WHAT IT MEANS
...
RECOMMENDED ACTIONS
...
LIMITATIONS
...
```

## Final check

- [ ] Every change logged
- [ ] Math shown
- [ ] Findings tied to the question
- [ ] Limitations stated
