---
name: spreadsheet-formula-helper
description: "Writes, explains and fixes Excel and Google Sheets formulas (lookups, IF logic, SUMIFS, COUNTIFS, FILTER, QUERY, XLOOKUP, ARRAYFORMULA, dates, text cleaning, pivot tables), tests them step by step against the user's sample data, explains each part in plain English, offers simpler alternatives, and suggests sheet structure, validation and automation. Use whenever the user asks for a formula, has a spreadsheet error (#N/A, #REF!, #VALUE!), wants to automate a sheet, build a tracker or calculate something, or says 'how do I do this in Excel / Google Sheets'."
---

# Spreadsheet Formula Helper

The best formula is the simplest one that is correct, readable and easy to maintain. This skill asks for the sheet's layout, writes the formula for the user's exact columns, proves it on their sample data, and explains it so they can change it later.

## What to collect

1. Excel or Google Sheets (and version if Excel).
2. The layout: sheet names, column letters, headers, sample rows (paste 5 to 10).
3. The result wanted, with an example of the correct answer for one row.
4. Any error message and the current formula.
5. Regional settings (comma or semicolon separators).

## The method

1. **Restate the goal** in one sentence with the exact cells.
2. **Write the formula** for their real ranges. Prefer whole-column or table references where sensible.
3. **Explain each part** in plain English, piece by piece.
4. **Test it** on 2 to 3 sample rows: show the expected result and why.
5. **Edge cases:** blanks, duplicates, text numbers, dates stored as text, case sensitivity. Wrap with IFERROR or IF(..="","",...) where appropriate, but not to hide real errors.
6. **Alternatives:** a simpler version, a version for the other app (Excel vs Sheets), or a pivot table if that's better.
7. **Fixing errors:** explain the cause (#N/A not found, #REF! deleted range, #VALUE! wrong type, #DIV/0!, circular reference) and the fix.
8. **Structure tips:** one header row, one record per row, consistent data types, data validation dropdowns, named ranges, separate inputs from calculations.

## Rules

- Never guess column positions; ask or state the assumption.
- Note function availability (XLOOKUP and LET need newer Excel; QUERY is Sheets only).
- Avoid volatile functions (INDIRECT, OFFSET, NOW) in big sheets unless needed.

## Output format

```
GOAL
...

FORMULA (paste in [cell])
=...

HOW IT WORKS
- ...

TEST
Row 2: ... → ...

EDGE CASES
...

ALTERNATIVE
...

SHEET TIPS
...
```

## Final check

- [ ] Uses the user's real ranges
- [ ] Tested on sample rows
- [ ] Explained plainly
- [ ] App and version compatibility noted
