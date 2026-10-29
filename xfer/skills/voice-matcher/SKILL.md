---
name: voice-matcher
description: "Learns how the user writes from 3 or more samples (sentence length, rhythm, vocabulary, humour, punctuation, slang, openings and sign-offs), builds a personal voice profile, then rewrites new drafts or AI-written text so they sound like the user wrote them, and flags lines that still sound generic. Use whenever the user wants content to sound like them, says 'this sounds like AI', 'make it sound like me', 'write in my voice', or shares samples of their writing."
---

# Voice Matcher

People can tell when text is not written by the person who posted it. This skill studies how the user actually writes and edits new text to match, removing the polished, generic patterns of AI writing while keeping the message.

## What to collect

1. 3 to 10 samples of the user's own writing (captions, emails, DMs, posts), ideally unedited.
2. The draft to rewrite.
3. Where it will be published.

## The method

### 1. Build the voice profile
- **Sentence length and rhythm:** short and punchy, long and flowing, mixed.
- **Openings:** how they start (questions, statements, stories, "So...").
- **Vocabulary:** favourite words, phrases, slang, local expressions, words they never use.
- **Punctuation and formatting:** emoji use, exclamation marks, lowercase, line breaks, lists.
- **Person and perspective:** "I", "we", "you".
- **Humour and attitude:** dry, playful, serious, blunt.
- **Sign-offs and calls to action.**

### 2. Spot generic patterns in the draft
Common AI tells: "In today's fast-paced world", "Let's dive in", "game-changer", "unlock", "elevate", "it's not just X, it's Y", perfectly balanced lists of three, em dashes everywhere, overly formal transitions, summarising endings.

### 3. Rewrite
Keep the meaning and facts. Change rhythm, word choice, openings and endings to match the profile. Add the user's natural phrases sparingly, not as a caricature.

### 4. Flag
Lines that still feel off, and facts the user should check.

## Rules

- Do not exaggerate the user's quirks.
- Never add claims or stories that are not in the draft or samples.
- Keep platform norms (length limits).

## Output format

```
YOUR VOICE PROFILE
- Rhythm: ...
- Openings: ...
- Words you use: ...
- Words you avoid: ...
- Punctuation and emoji: ...
- Attitude: ...

REWRITE
...

WHAT I CHANGED
...

STILL CHECK
...
```

## Final check

- [ ] Profile based on samples
- [ ] Generic AI patterns removed
- [ ] Meaning unchanged
- [ ] Sounds like the samples
