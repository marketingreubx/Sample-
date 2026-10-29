# Realism Prompting

How to write image prompts that look like real photography, not "AI art".

## 1. The master formula

```
[Shot type + angle] of [subject: age range, ethnicity/skin tone, hair, expression, pose, hands],
wearing [wardrobe, fabric, colour], [action / interaction with prop],
in [setting / background, with where the empty space is],
[lighting: source, direction, quality, colour temperature],
shot on [camera body] with [lens mm] at [aperture], [depth of field],
[colour grade / film stock], [texture & realism cues],
[composition note: subject placement + negative space for text], [aspect ratio]
```

Order matters: models weight the first ~40 words most. Put subject + angle first, composition and aspect last.

## 2. Realism cues (always include 3–5 for people)

- natural skin texture, visible pores, fine peach fuzz, subtle under-eye texture
- slight skin imperfections, freckles / faint blemishes (as appropriate)
- realistic hands with five fingers, natural nail detail
- flyaway hairs, individual hair strands catching light
- fabric texture/weave visible, natural creases
- catchlights in eyes from [softbox/window]
- unretouched editorial / "minimal retouching, real skin"
- shot on film / candid, slight motion of hair
- true-to-life colour, no HDR, no oversharpening

## 3. Camera & lens vocabulary

| Want | Write |
|---|---|
| Classic portrait | Canon EOS R5, 85mm f/1.4, shallow depth of field |
| Beauty close-up | Sony A7R V, 100mm macro f/2.8, ring light catchlights |
| Fashion editorial | Hasselblad X2D, 80mm, f/4, medium format detail |
| Lifestyle / UGC | iPhone 15 Pro front camera, natural daylight, slightly imperfect framing |
| Environmental | Fujifilm GFX 100S, 35mm, f/5.6 |
| Low-angle power | 24mm wide, camera at hip height looking up |
| Film look | Kodak Portra 400, fine grain / Fujifilm Pro 400H pastel tones / Cinestill 800T night |

## 4. Lighting vocabulary

- **Soft beauty**: large octabox front-high, white reflector under chin (clamshell), even glowing skin
- **Window light**: soft directional daylight from left window, gentle falloff
- **Golden hour**: warm low sun backlight, rim light on hair, lens flare
- **Studio dramatic**: single hard key light 45° side, deep shadows (Rembrandt)
- **Rim/separation**: hair light from behind, glowing outline on dark background
- **Clinical clean**: bright high-key white, soft shadows, spa treatment room
- **Coloured gel**: magenta and teal gels, neon reflections (night/edgy)
- **Flash editorial**: direct on-camera flash, hard shadow behind subject, glossy skin

## 5. Composition phrases that leave room for text

- "subject positioned on the right third, large clean negative space on the left"
- "empty seamless backdrop above the head for headline"
- "centred subject, plain background, room at top and bottom"
- "isolated on solid [colour] background" (easy cut-out)
- "shot against plain light grey studio backdrop for easy background removal"
- "headroom: generous empty space above"

## 6. Per-model syntax

**Midjourney v7**
```
<prompt> --ar 4:5 --style raw --v 7 --s 100
```
Use `--style raw` for realism, `--s 50–150` (low = more literal), `--no plastic skin, cgi, illustration`. Use `--cref <url>` / omni-reference for consistent person, `--sref <url>` to copy style of a flyer.

**Higgsfield (Soul / photoreal presets)** — natural sentences, include camera + light; pick a realistic preset; aspect 4:5 or 9:16. Use reference image upload for consistent character/product.

**Flux (Pro/Dev/Kontext)** — long natural-language descriptions work best; no negative prompt field; state realism positively ("real skin texture, unretouched"). Kontext: upload image + instruction for edits ("remove all text, keep everything else").

**GPT-Image / DALL·E / Nano Banana (Gemini)** — describe like briefing a photographer; they follow layout instructions well ("leave the top 35% empty"). Best for removing text from a reference flyer and for exact edits.

**Ideogram** — best when the image must include legible text/typography; put text in quotes: `headline text "GLOW SEASON" in bold serif`.

**Leonardo / SDXL** — use negative prompt field (below), PhotoReal mode on.

## 7. Universal negative prompt

```
plastic skin, airbrushed, waxy, doll-like, cgi, 3d render, illustration, cartoon, painting, oversaturated, hdr, over-sharpened, extra fingers, deformed hands, distorted face, asymmetrical eyes, blurry, lowres, watermark, text, logo, jpeg artifacts, uncanny
```

## 8. Asset types and template prompts

**Cut-out person (for type-behind-subject)**
```
Waist-up portrait of a confident [description], arms crossed, slight smile looking directly at camera, wearing [wardrobe], isolated on a plain light grey seamless studio backdrop, soft octabox key light from front-left with subtle rim light on hair, shot on Canon EOS R5 85mm f/2, natural skin texture with visible pores, flyaway hairs, true-to-life colour, centred with headroom, --ar 4:5 --style raw
```

**Product hero**
```
Studio product photograph of a [frosted glass serum bottle with gold dropper], standing on a [travertine stone pedestal], [water droplets on surface], soft diffused light from top-left, gentle reflection, [beige] seamless background, product on lower right third, empty space top-left, shot on Phase One 100mm macro f/8, ultra-sharp label edges, photorealistic commercial photography --ar 4:5 --style raw
```

**Texture / background**
```
Close-up macro of [creamy moisturiser swatch / serum gel texture / rose quartz surface], soft natural light, [blush pink] tones, shallow depth of field, seamless, usable as flyer background, photorealistic --ar 4:5
```

**Floating elements**
```
[Skincare products / leaves / pearls] floating in mid-air at different depths, soft shadows on [pastel] background below, studio lighting, motion-frozen, photorealistic product photography, clean empty centre for text --ar 4:5
```

**Hands in action (service shots)**
```
Over-the-shoulder close-up of an esthetician's gloved hands applying a [mask] with a brush to a client's face, client lying relaxed eyes closed, clean white treatment room, soft window light, shot on Sony A7 IV 50mm f/2, real skin texture, candid editorial, subject on left, negative space right --ar 4:5
```

## 9. Consistency & quality workflow

1. Generate 4 variations; pick on angle + empty space first, face second.
2. Lock the look with a seed / character reference / reference image.
3. Fix hands and small defects with inpainting / "vary region".
4. Upscale ×2 (×4 for print).
5. Remove background (for cut-out archetypes) and add matching shadow.
6. Unify in design with the same grain + gradient map.
