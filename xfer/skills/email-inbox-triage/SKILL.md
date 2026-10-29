---
name: email-inbox-triage
description: "Sorts a pasted batch of emails or messages into Reply now, Schedule, Delegate, Read later and Ignore or archive, ranks the urgent ones by deadline and importance, drafts short replies for the Reply now group, extracts tasks and dates, and suggests filters, templates and habits to keep the inbox under control. Use whenever the user is overwhelmed by email or messages, pastes several emails, wants inbox zero, or says 'help me deal with my inbox', 'what needs a reply first'."
---

# Email Inbox Triage

An inbox is someone else's to-do list for you. Triage decides quickly what deserves attention now, what can wait, and what can go, then clears the urgent replies in one batch.

## What to collect

1. The emails or messages (subject, sender, body or summary).
2. The user's role, current priorities and key clients.
3. Who they can delegate to.

## The method

### 1. Sort each message
| Bucket | Meaning |
|---|---|
| Reply now | Under 5 minutes or urgent and important |
| Schedule | Needs real work; becomes a task with a time |
| Delegate | Someone else should handle it |
| Read later | Useful but not urgent (newsletters, updates) |
| Ignore or archive | No action needed |

### 2. Rank the urgent ones
By deadline, money involved (payments, new clients), relationships (key clients), and consequences.

### 3. Draft replies
For Reply now: short, clear drafts (2 to 5 lines) the user can send after a quick check.

### 4. Extract
Tasks with due dates, calendar items, payments to make or chase.

### 5. Prevent
Filters and labels to create, unsubscribes, templates for repeated replies, a twice-a-day email schedule, an auto-reply if needed.

## Rules

- Never send on the user's behalf; drafts only.
- Flag anything that looks like phishing or a scam (urgent payment requests, password resets they didn't ask for, odd sender addresses).
- Keep replies short.

## Output format

```
REPLY NOW (in order)
1. [Sender] Subject: ... Why: ...
   Draft: ...

SCHEDULE
| Email | Task | When |

DELEGATE
| Email | To | Note to send |

READ LATER
...
ARCHIVE
...

TASKS AND DATES
...

POSSIBLE SCAMS
...

KEEP IT CLEAN
Filters: ... Templates: ... Habit: ...
```

## Final check

- [ ] Every message sorted
- [ ] Urgent ones ranked with reasons
- [ ] Drafts short and ready
- [ ] Suspicious messages flagged
