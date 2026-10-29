---
name: hook-meme-remixer
description: "Remix viral hooks, memes, and formats into any niche while preserving the exact structure, rhythm, and word pattern of the original. Returns 5 remixes per request. Use whenever the user pastes a viral hook, meme template, or trending format and asks to remix it, adapt it, make it about their niche, or says 'remix this hook', 'turn this into my niche', 'adapt this meme', or 'give me versions of this for X'. Always use this skill whenever an existing hook or meme is provided for adaptation."
---

# Hook and Meme Remixer

The remix works only if the skeleton is identical and everything else changes. Get the skeleton wrong and the remix reads as a copy that broke, not a fresh version of something proven.

---

## RULE ZERO: EXTRACT THE SKELETON FIRST

Before writing a single remix, break the original down into its skeleton. Never skip this step, even for a hook that looks simple.

**Extract:**
1. **Word count and rhythm.** Count the words. Note where the pauses fall.
2. **Sentence count and length of each.** A hook with three short sentences needs three short sentences back, not one long one.
3. **The pattern words.** Words that carry the mechanical structure rather than the meaning: "the one where", "nobody talks about", "until you", "that one time". These get reused exactly. Only the subject changes.
4. **The turn.** Where does the hook pivot, contradict itself, or reveal something. Mark the exact word it happens on.
5. **The punctuation shape.** Question mark, period, trailing thought. Keep it identical.
6. **The tone register.** Deadpan, sarcastic, sincere, dramatic. This must transfer exactly or the joke or hook dies.

Write this extraction out for yourself before drafting a single remix. If you cannot name the pattern words and the turn, you have not actually understood the structure, and every remix will drift.

---

## RULE ONE: NO EM DASHES

Never use the em dash or en dash in any remix. Scan your output for both characters before delivering. If either appears, rewrite that line without changing the structure it sits in.

---

## WHAT COUNTS AS BROKEN STRUCTURE

A remix has failed if any of these are true:

- Word count is off by more than 20 percent from the original
- A short punchy original became a long explanatory remix
- The pattern words were paraphrased instead of reused
- The turn happens on a different beat than the original
- The tone shifted, for example a deadpan original became enthusiastic
- The remix explains the joke or the hook instead of just delivering it

If a remix fails any of these, rewrite it before including it in the batch. Do not deliver a broken remix and label it as close enough.

---

## HOW NICHE SUBSTITUTION WORKS

Only the following are allowed to change between the original and a remix:
- The subject or noun the hook is about
- The specific example or detail used
- The identity or role being addressed

Everything else, meaning the rhythm, the pattern words, the sentence lengths, the turn, and the tone, stays fixed across all 5 remixes and the original.

**Example extraction:**

Original meme format: "Nobody: Absolutely nobody: Me at 2am: [absurd specific action]"

Skeleton: three short lines, each its own beat, first two are near identical negation, third is the payoff with a highly specific and slightly absurd image.

Remix for social media managers: "Nobody: Absolutely nobody: Me refreshing my scheduled post at 6am to see if the algorithm liked it"

The structure did not move. Only the specific image changed, and it stayed specific and slightly absurd rather than becoming generic.

---

## THE 5 REMIXES

Every request returns exactly 5 remixes of the same original, all built from the same skeleton.

**Vary across the 5:**
- The specific example or angle used within the niche
- The exact noun or role being addressed
- Minor tonal intensity, for example one slightly more exaggerated than another

**Never vary across the 5:**
- Word count, beyond the 20 percent tolerance
- Sentence structure or count
- Pattern words
- Where the turn lands
- Overall tone register

If all 5 remixes feel structurally different from each other, something has drifted. They should feel like 5 doors into the same room.

---

## THE AI KILL LIST

**Banned punctuation:** em dash, en dash, semicolons.

**Banned words:** unlock, elevate, leverage, game changer, level up, robust, seamless, actionable.

**Banned moves:**
- Explaining why the remix is funny or relevant
- Softening a sharp original into something generic and safe
- Adding a moral or lesson to a joke format that never had one
- Turning a short punchy hook into a paragraph
- Using a niche buzzword so on the nose it reads as forced

---

## SELF AUDIT, RUN SILENTLY BEFORE DELIVERING

- [ ] Zero em dashes and en dashes across all 5 remixes, characters actually scanned
- [ ] Skeleton extraction done and pattern words identified before drafting
- [ ] Word count within 20 percent of original for every remix
- [ ] Turn lands on the same beat as the original in every remix
- [ ] Tone register matches the original in every remix
- [ ] Each of the 5 uses a different specific example or angle
- [ ] None of the 5 explain themselves
- [ ] Exactly 5 remixes delivered, no more, no fewer

---

## OUTPUT FORMAT

```
ORIGINAL
[paste of what was given]

SKELETON
[one line naming the pattern words, the turn, and the tone]

REMIX 1
[text]

REMIX 2
[text]

REMIX 3
[text]

REMIX 4
[text]

REMIX 5
[text]
```

Nothing else after remix 5. No ranking, no "which one is best", no explanation of why each works, unless asked.

---

## EDGE CASES

**No niche specified.** Ask which niche or audience before remixing. Do not guess and produce 5 remixes for the wrong audience.

**Original is already niche specific.** Ask what the target niche is for the remix, since the source and target must differ for this to be useful.

**Original has no clear turn or payoff.** Say so in one line and ask for a different hook or meme, since a structure with nothing to preserve cannot be remixed meaningfully.

**User wants more than 5.** Give 5 by default per the spec. If they explicitly ask for more, deliver the number requested, still from the same skeleton.

**Meme relies on an image or visual, not just text.** Note in the skeleton what the visual is doing structurally, then adapt the text to work with the same visual concept translated to the new niche.
