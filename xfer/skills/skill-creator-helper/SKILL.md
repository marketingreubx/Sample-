---
name: skill-creator-helper
description: "Helps the user turn a task they repeat into their own Claude skill: interviews them about the task, inputs, expert method, quality bar and output, then writes a complete SKILL.md with a precise name, a trigger-rich description, a step-by-step process, rules, an output template, an example and a final checklist, plus test prompts and install steps for Claude.ai and Claude Code. Use whenever the user wants to create a skill, build a custom Claude skill, save a workflow as a skill, or says 'make this a skill', 'I do this every week, can Claude remember how'."
---

# Skill Creator Helper

A skill is a reusable set of instructions Claude loads when a task matches its description. Good skills capture the user's expert method and quality standards so results are consistent every time. The description decides when the skill triggers; the body decides how well it works.

## What to collect (interview, a few questions at a time)

1. The task and how often it happens.
2. A real example: the input they start with and the output they want.
3. Their method: the steps they follow, in order, and why.
4. What makes the output good versus mediocre (their quality bar).
5. Common mistakes to avoid.
6. Words or phrases they use when asking for this task (for triggers).
7. Any files, templates or brand rules that should be included.

## The method

### 1. Name
Lowercase, hyphenated, specific: `client-report-writer`, not `helper`.

### 2. Description (the trigger)
One or two sentences: what it does in detail, then "Use whenever..." with the situations and phrases people actually say. Specific enough to trigger reliably, not so broad it fires for unrelated tasks.

### 3. Body
- Title and purpose (the standard it holds).
- What to collect (inputs) and what to do if something is missing.
- The method: numbered steps with the reasoning.
- Rules (quality bar, things to never do).
- Output format (an exact template).
- A short example.
- Final checklist.
Keep it focused: under about 500 lines; move long reference material into separate files in the skill folder if needed.

### 4. Test
5 realistic test prompts (including one that should NOT trigger it) and what a good result looks like.

### 5. Install
- **Claude.ai:** put SKILL.md in a folder named after the skill, zip the folder, upload in Settings → Capabilities → Skills.
- **Claude Code:** place the folder in `~/.claude/skills/` (personal) or `.claude/skills/` in a project.

## Rules

- Capture the user's method, not generic advice.
- Write instructions as direct, clear steps.
- Include an output template; it drives consistency.

## Output format

```
SKILL PLAN
Name: ... | Triggers: ...

SKILL.md
---
name: ...
description: "..."
---
# ...
...

TEST PROMPTS
1. ... (should trigger)
5. ... (should not trigger)

HOW TO INSTALL
...
```

## Final check

- [ ] Specific name and trigger-rich description
- [ ] Method in the user's own steps
- [ ] Output template
- [ ] Test prompts and install steps
