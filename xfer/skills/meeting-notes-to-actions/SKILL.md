---
name: meeting-notes-to-actions
description: "Turns meeting notes, call transcripts, voice note text or chat threads into a clean summary, decisions made, action items with owners and due dates, open questions, risks, and a ready-to-send follow up message for the team or client, flagging anything unclear or unassigned. Use whenever the user shares notes or a transcript and asks for a summary, minutes, action items, next steps, or says 'what did we agree', 'write the follow up email from this call'."
---

# Meeting Notes to Actions

Meetings only matter if they produce decisions and actions that happen. This skill extracts exactly that from messy notes, makes ownership and deadlines explicit, and writes the follow up so nothing is forgotten.

## What to collect

1. The notes or transcript.
2. Meeting purpose, date and attendees (if not in the notes).
3. Who the follow up goes to and the channel.

## The method

1. **Summary:** 3 to 5 sentences on what the meeting was about and the outcome.
2. **Decisions:** what was agreed, stated clearly. Only what was actually decided.
3. **Action items:** task, owner, due date, dependencies. If no owner or date was mentioned, mark [OWNER?] or [DATE?] and suggest one.
4. **Open questions:** things raised but not resolved, with who should answer.
5. **Risks and concerns:** anything that could block progress.
6. **Key numbers and facts:** prices, dates, metrics mentioned.
7. **Parking lot:** ideas for later.
8. **Follow up message:** short, structured, ready to send.

## Rules

- Don't invent decisions or commitments. If unclear, list it as an open question.
- Use names as given.
- Action items start with a verb.
- Keep the follow up scannable.
- For transcripts, ignore small talk and repetition.

## Output format

```
SUMMARY
...

DECISIONS
1. ...

ACTION ITEMS
| # | Action | Owner | Due | Notes |

OPEN QUESTIONS
...

RISKS
...

KEY FACTS
...

FOLLOW UP MESSAGE
Subject: ...
...
```

## Final check

- [ ] Every action has an owner and date (or a flag)
- [ ] Decisions are only what was decided
- [ ] Follow up ready to send
