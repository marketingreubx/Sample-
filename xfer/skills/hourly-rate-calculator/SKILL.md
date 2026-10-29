---
name: hourly-rate-calculator
description: "Calculates a freelancer's minimum and target hourly, day, monthly retainer and project rates from income goals, business costs, taxes, savings, realistic billable hours and profit margin, then converts them into package prices and checks them against the market. Use whenever the user asks what hourly rate to charge, how to calculate freelance rates, day rates, project pricing from hours, or says 'how much should I charge per hour', 'am I charging enough', 'convert my salary to a freelance rate'."
---

# Freelance Rate Calculator

Most freelancers undercharge because they divide their desired salary by 40 hours a week. Real billable hours are much lower, and the rate has to cover costs, taxes, savings, unpaid admin and time off. This skill shows the full math, step by step, so the user trusts the number.

## What to collect

1. Desired take-home income per month (after tax).
2. Monthly business costs: software, internet, phone, equipment, workspace, subscriptions, contractors, courses.
3. Tax rate or estimate (ask for country, and label estimates).
4. Savings goals: emergency fund, pension, equipment replacement.
5. Working weeks per year (minus holidays, sick days, public holidays).
6. Hours worked per week and what share is billable (typical: 50% to 65%).
7. Currency.

## The calculation (show every step)

1. **Annual take-home goal** = monthly × 12.
2. **Add annual business costs.**
3. **Add savings and buffers** (for example 10% for slow months).
4. **Gross up for tax:** required revenue = (take-home + costs + savings) ÷ (1 − tax rate).
5. **Add profit margin** (10% to 20%) so the business can grow.
6. **Billable hours per year** = working weeks × hours per week × billable share.
7. **Minimum hourly rate** = required revenue ÷ billable hours.
8. **Day rate** = hourly × billable hours per day (usually 6).
9. **Monthly retainer** = hours per month for the client × hourly rate, rounded up.
10. **Project rate** = estimated hours × hourly × 1.2 to 1.3 risk buffer.

## Then go beyond hours

- Compare with the market range the user knows. If the minimum is above the market, the user needs a higher value niche, better positioning or more efficiency.
- Recommend moving to package and value pricing once the floor is known. Hourly rates cap income.
- Show what happens if billable hours drop by 20% (resilience test).

## Rules

- Show the math in a table so it can be checked.
- Label tax figures as estimates and recommend checking with a local accountant.
- Round prices sensibly for presenting to clients.
- Never recommend a rate below the calculated minimum without an explicit reason.

## Output format

```
YOUR INPUTS
...

THE MATH
| Step | Calculation | Result |

YOUR RATES
Minimum hourly: ...
Recommended hourly (with margin): ...
Day rate: ...
Monthly retainer example (X hours): ...
Project example (Y hours): ...

STRESS TEST
If billable hours drop 20%: ...

NEXT STEP
Turn this into packages: ...
```

## Final check

- [ ] Realistic billable hours, not 40 a week
- [ ] Tax, costs, savings and margin included
- [ ] Every step shown
- [ ] Package recommendation given
