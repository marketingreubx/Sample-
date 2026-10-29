---
name: freelance-finance-planner
description: "Builds a simple money system for freelancers with irregular income: separate accounts and the percentage split for tax, business costs, profit and pay, a salary you pay yourself from an income buffer, an emergency fund target, monthly budget, invoice and cash flow tracker, slow month plan and a 30 minute monthly money review. Use whenever the user wants to manage freelance money, plan for taxes, handle irregular income, budget as a self-employed person, or says 'I never know how much I can spend', 'I'm always broke between clients'."
---

# Freelance Finance Planner

Irregular income feels chaotic when every payment goes straight into spending. A simple system (separate pots, fixed percentages, a buffer and a steady personal salary) turns feast and famine into a predictable monthly income. This is education, not financial or tax advice.

## What to collect

1. Average monthly income over the last 6 to 12 months (and the lowest month).
2. Business costs per month.
3. Personal essential costs per month.
4. Country (tax rules differ) and current savings and debts.
5. Goals (emergency fund, equipment, travel, investing).

## The system

### 1. Accounts (or sub-accounts)
- **Income:** all client payments land here.
- **Tax:** a fixed percentage of every payment.
- **Business costs:** tools, subscriptions, equipment.
- **Profit or savings:** a small fixed percentage.
- **Pay:** your monthly salary to your personal account.

### 2. Percentages
Suggest a starting split based on their numbers (for example Tax 15% to 25% as an estimate to confirm with a local accountant, Business 10% to 20%, Profit 5% to 10%, Pay the rest). Show the math with their average income.

### 3. Pay yourself a salary
Set a fixed monthly salary from the average (or lowest good month), not from this month's income.

### 4. Income buffer
Build 1 to 3 months of salary in the income account before raising the salary.

### 5. Emergency fund
3 to 6 months of essential personal costs, built gradually.

### 6. Trackers
Invoice tracker (client, amount, sent, due, paid), monthly cash flow (in, out, balance), expense categories.

### 7. Slow month plan
What to cut, which pot to use, how to fill the pipeline.

### 8. Monthly money review (30 minutes)
Income vs target, unpaid invoices, pot balances, adjust salary or percentages.

## Rules

- Not financial or tax advice: recommend a local accountant for tax rates and registration.
- Use the user's numbers; show calculations.
- Simple enough to run monthly.

## Output format

```
YOUR NUMBERS
...
ACCOUNTS AND SPLIT
| Pot | % | From an average month |
YOUR SALARY
...
BUFFER AND EMERGENCY FUND
Target ... Monthly contribution ... Reached by ...
TRACKERS (columns)
...
SLOW MONTH PLAN
...
MONTHLY REVIEW CHECKLIST
...
```

## Final check

- [ ] Percentages based on real numbers
- [ ] Fixed salary from the average
- [ ] Buffer and emergency fund targets
- [ ] Disclaimer included
