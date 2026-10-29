# Reverse Engineering a Flyer

When the user pastes a flyer/ad/poster, read it like a forensic photographer and a designer at once. Look carefully before writing; zoom mentally into the face, hands, background edges, shadows and type.

## Step 1 — Design DNA
- Name the archetype (layout-playbook §1) or the mix of two.
- Draw the 3×3 grid map: what sits in each cell.
- Eye path: number the first 4 things the eye hits.
- One line on *why* it works (contrast, scale, depth, emotion).

## Step 2 — Text map
For each text element:
| # | Copy | Grid cell | Approx size (% of width) | Font (closest match → free alt) | Colour hex | Effects (stroke, shadow, behind subject, rotation) |

Font identification cues: serif vs sans, contrast between thick/thin strokes, x-height, terminals, condensed vs wide. Suggest the user confirm with WhatTheFont / Font Squirrel Matcherator for exact matches.

## Step 3 — Palette
4–6 hex codes with roles: background, primary, accent, text, highlight. Sample from the largest flat areas and from the accent detail.

## Step 4 — Photo forensics checklist
- **Subject**: age range, skin tone, hair style/colour/texture, makeup, expression, gaze direction.
- **Pose**: body orientation, head tilt, hands (where, doing what), crop line.
- **Wardrobe & props**: fabric, colour, jewellery, products held.
- **Camera angle**: eye level / low / high / top-down / dutch; how high the camera is relative to the eyes.
- **Lens**: wide (distortion, big foreground, lots of background) vs telephoto (compressed, blurry background). Estimate mm.
- **Depth of field**: background blur amount → aperture estimate.
- **Lighting**: where are the catchlights (key light position & shape: round = ring/beauty dish, square = softbox/window)? Shadow hardness, rim light, colour temperature, fill.
- **Background**: studio seamless, real location, gradient, composited? Was the subject cut out and placed on a colour (look for edge halos and mismatched light)?
- **Grade**: warm/cool, contrast, saturation, film grain, skin retouch level.
- **Is it a composite?** Many flyers = cut-out photo + flat colour bg + added shadow. If so, prompt for the subject on plain backdrop and rebuild the bg in design, which is MORE accurate than prompting the whole flyer.

## Step 5 — Write the exact hero prompt
Use the master formula from realism-prompting.md. Fill every slot with what you observed. Then:
- Main version for the user's tool (default Midjourney v7 `--style raw` + Higgsfield/Flux natural-language version).
- State the aspect ratio of the original.
- If the subject looks like a known person, do not name them; describe features generically.

## Step 6 — Clean plate prompt
Two options, give both:
1. **Edit the original** (best fidelity): upload it to Flux Kontext / GPT-Image / Nano Banana / Canva Magic Eraser with: `Remove all text, logos and graphic elements from this image. Keep the person, lighting, colours and background exactly the same. Fill removed areas naturally.`
2. **Regenerate**: the hero prompt + "clean background with empty space at [where text was], no text".

Also offer: Midjourney `--sref <flyer url>` to borrow the style, and `--cref`/omni-reference with their own photo to put themself/their client in it.

## Step 7 — Supporting assets
List every non-text element (products, flowers, splashes, shapes, textures, 3D icons, phone mockups, stickers) with a prompt or where to get it (Canva elements, Freepik, Envato, remove.bg).

## Step 8 — Make it yours
- Swap colours: map their brand hex to each role.
- Swap copy: keep the same character count roughly so the layout holds.
- Swap subject: their own photo shot with the same angle/light (give a 3-line phone-shoot guide), or AI with reference.
- Keep the one trick that makes it work (e.g. type behind head) — change everything else.

## Honesty rules
- Say "approx." for measurements and "closest match" for fonts.
- No prompt reproduces an image pixel-perfect; promise "as close as possible", recommend 4–8 generations and the edit-the-original route when fidelity matters.
- Remind once (briefly) that copying someone's flyer outright can infringe their copyright/trademark; use it as inspiration and rebrand.
