"""'5 looks' picker: renders five typography looks on a real frame of the video (a few seconds, no full render)."""
import asyncio, json, os, re, subprocess, sys
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import gfx

LOOKS_B = [   # solid, high-contrast palettes (no AI gradients): main word colour / highlight / outline
    ("Clean White", {"font_main": "Montserrat", "font_lead": "Poppins", "font_cap": "Poppins", "main_color": "#FFFFFF", "highlight": "#FFD60A", "stroke": "#0B0B0F", "main_style": "solid"}, "#FFD60A", "#FFE680"),
    ("Signal Yellow", {"font_main": "Anton", "font_lead": "Inter", "font_cap": "Inter", "main_color": "#FFD60A", "highlight": "#FFD60A", "stroke": "#000000", "main_style": "solid"}, "#FFD60A", "#FFE680"),
    ("Red Box", {"font_main": "Archivo Black", "font_lead": "Inter", "font_cap": "Inter", "main_color": "#FFFFFF", "highlight": "#FF3B30", "box_color": "#FF3B30", "stroke": "#000000", "main_style": "box"}, "#FF3B30", "#FF8A80"),
    ("Lime Punch", {"font_main": "Bricolage Grotesque", "font_lead": "Manrope", "font_cap": "Manrope", "main_color": "#C6FF3D", "highlight": "#C6FF3D", "stroke": "#0B0B0F", "main_style": "solid"}, "#C6FF3D", "#E4FFA0"),
    ("Luxe Cream", {"font_main": "Playfair Display", "font_lead": "DM Sans", "font_cap": "DM Sans", "main_color": "#F6E7C8", "highlight": "#E8B04B", "stroke": "#1A1208", "main_style": "solid"}, "#E8B04B", "#F6E7C8"),
]
LOOKS_A = [
    ("Kilgore Classic", {"font_cap": "Poppins", "font_accent": "Instrument Serif", "font_hand": "Gochi", "accent_text": "#F3E37C"}, "#ff5a93", "#F3E37C"),
    ("Cormorant Gold", {"font_cap": "Inter", "font_accent": "Cormorant Garamond", "font_hand": "Caveat", "accent_text": "#f2c46b"}, "#c9962b", "#f2c46b"),
    ("Soft Pink", {"font_cap": "DM Sans", "font_accent": "DM Serif Display", "font_hand": "Reenie Beanie", "accent_text": "#ffb3cf"}, "#ff5a93", "#ffb3cf"),
    ("Lilac Pop", {"font_cap": "Bricolage Grotesque", "font_accent": "Playfair Display", "font_hand": "Caveat", "accent_text": "#c9b6ff"}, "#7c5cff", "#c9b6ff"),
    ("Minimal White", {"font_cap": "Manrope", "font_accent": "Instrument Serif", "font_hand": "Gochi", "accent_text": "#ffffff"}, "#111111", "#ffffff"),
]


def _words(t, n=14):
    out = ""
    for w in t.split():
        if out and len(out) + 1 + len(w) > n: break
        out = (out + " " + w).strip()
    return out or t[:n]


def _fit(t): return int(max(70, min(130, 900 / (0.64 * max(len(_words(t)), 1)))))


def names(): return {"a": [n for n, *_ in LOOKS_A], "b": [n for n, *_ in LOOKS_B]}


def sample_text(plan, transcript):
    b = (plan.get("blocks") or [{}])[0]
    lead, big = b.get("lead") or "you probably heard of", b.get("text") or "THE BIG IDEA"
    words = [w["w"] for w in json.load(open(transcript))] if os.path.exists(transcript) else []
    cap = " ".join(re.sub(r"[.,!?]", "", w).lower() for w in words[3:6]) or "this changes everything"
    return lead, big.replace("<br>", " "), cap


def render(video, work, style, plan, transcript):
    dur = float(subprocess.run(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", video], capture_output=True, text=True).stdout or 10)
    bg = os.path.join(work, "look_bg.jpg")
    subprocess.run(["ffmpeg", "-v", "error", "-y", "-ss", f"{dur * 0.35:.2f}", "-i", video, "-frames:v", "1", "-vf",
                    "scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920", bg], check=True)
    lead, big, cap = sample_text(plan, transcript)
    fam = "a" if style.startswith(("a", "kilgore")) else "b"
    pages = []
    for name, look, acc, acc2 in (LOOKS_A if fam == "a" else LOOKS_B):
        html = gfx.page([], acc, acc2, look).replace("<body", f"<body style=\"background:url('{gfx.uri(bg)}') center/cover\"", 1)
        if fam == "b":
            body = (f'<div style="position:absolute;left:0;right:0;top:1180px;display:flex;justify-content:center"><div class="stack">'
                    f'<div class="ln s-lead" style="font-size:46px;margin:0 0 -2px 18px">{lead}</div><div class="ln"><span class="s-glow"><span class="s-main" style="font-size:{_fit(big)}px">{_words(big)}</span></span></div>'
                    f'<div class="ln s-tail" style="font-size:46px">for you</div></div></div>'
                    f'<div class="cap" style="position:absolute;left:0;right:0;top:1560px;text-align:center">{cap}</div>')
        else:
            w = cap.split()
            body = (f'<div style="position:absolute;left:0;right:0;top:300px;text-align:center"><span class="hand" style="font-size:66px;color:var(--c-accent)">{lead}</span></div>'
                    f'<div style="position:absolute;left:0;right:0;top:1440px;text-align:center"><span class="sans" style="font-size:96px">{w[0] if w else "the"}</span> '
                    f'<span class="serif" style="font-size:128px;margin-left:18px">{w[1] if len(w) > 1 else "secret"}</span></div>')
        p = os.path.join(work, f"look_{len(pages)}.html"); open(p, "w").write(html.replace("</body>", body + "</body>"))
        pages.append((name, p, dict(look, accent=acc, accent2=acc2)))
    outs = asyncio.run(_shoot([p for _, p, _ in pages], work))
    return [(n, o, lk) for (n, _, lk), o in zip(pages, outs)]


async def _shoot(paths, work, prefix="look_"):
    from playwright.async_api import async_playwright
    outs = []
    async with async_playwright() as p:
        b = await (p.chromium.launch(executable_path=gfx.CHROMIUM, args=["--no-sandbox"]) if gfx.CHROMIUM else p.chromium.launch(args=["--no-sandbox"]))
        pg = await b.new_page(viewport={"width": 1080, "height": 1920})
        for k, path in enumerate(paths):
            await pg.goto(gfx.uri(path)); await pg.evaluate("document.fonts.ready"); await pg.wait_for_timeout(250)
            o = os.path.join(work, f"{prefix}{k}.jpg"); await pg.screenshot(path=o, type="jpeg", quality=78); outs.append(o)
        await b.close()
    return outs


# ---------------------------------------------------------------------- style previews + grade preview
def _frame(video, work, look, name="style_bg.jpg"):
    """A graded 1080x1920 frame from 35% into the video (same grade the render will use)."""
    import grade
    dur = float(subprocess.run(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", video], capture_output=True, text=True).stdout or 10)
    chain, rep = grade.build(video, look)
    out = os.path.join(work, name)
    subprocess.run(["ffmpeg", "-v", "error", "-y", "-ss", f"{dur * 0.35:.2f}", "-i", video, "-frames:v", "1", "-vf",
                    f"scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,{chain}", out], check=True)
    return out, rep


def grade_preview(video, work, look):
    """Side-by-side: original | graded (same frame)."""
    g, rep = _frame(video, work, look, "grade_after.jpg")
    dur = float(subprocess.run(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", video], capture_output=True, text=True).stdout or 10)
    raw = os.path.join(work, "grade_before.jpg"); out = os.path.join(work, "grade_compare.jpg")
    subprocess.run(["ffmpeg", "-v", "error", "-y", "-ss", f"{dur * 0.35:.2f}", "-i", video, "-frames:v", "1", "-vf",
                    "scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920", raw], check=True)
    subprocess.run(["ffmpeg", "-v", "error", "-y", "-i", raw, "-i", g, "-filter_complex",
                    "[0:v]scale=540:960,drawtext=text='Before':x=24:y=24:fontsize=40:fontcolor=white:box=1:boxcolor=black@0.5:boxborderw=10[a];"
                    "[1:v]scale=540:960,drawtext=text='After':x=24:y=24:fontsize=40:fontcolor=white:box=1:boxcolor=black@0.5:boxborderw=10[b];[a][b]hstack", out], check=True)
    return out, rep


def _sample_body(fam, lead, big, cap, hl):
    if fam == "b":
        c = cap.split(); hlw = c[-1] if c else ""
        capx = " ".join(c[:-1]) + (f' <span class="hl">{hlw}</span>' if hlw else "")
        return (f'<div style="position:absolute;left:0;right:0;top:1180px;display:flex;justify-content:center"><div class="stack">'
                f'<div class="ln s-lead" style="font-size:46px;margin:0 0 -2px 18px">{lead}</div><div class="ln"><span class="s-glow"><span class="s-main" style="font-size:{_fit(big)}px">{_words(big)}</span></span></div>'
                f'<div class="ln s-tail" style="font-size:46px">for you</div></div></div>'
                f'<div class="cap" style="position:absolute;left:0;right:0;top:1560px;text-align:center">{capx}</div>')
    w = cap.split()
    return (f'<div style="position:absolute;left:0;right:0;top:300px;text-align:center"><span class="hand" style="font-size:66px;color:var(--c-accent)">{lead}</span></div>'
            f'<div style="position:absolute;left:0;right:0;top:1440px;text-align:center"><span class="sans" style="font-size:96px">{w[0] if w else "the"}</span> '
            f'<span class="serif" style="font-size:128px;margin-left:18px">{w[1] if len(w) > 1 else "secret"}</span></div>')


def styles(video, work, plan, transcript, presets):
    """One preview image per style preset, on the user's own (graded) frame, with that style's text look."""
    bg, _ = _frame(video, work, plan.get("look", {}))
    lead, big, cap = sample_text(plan, transcript)
    user = {k: v for k, v in plan.get("look", {}).items() if k in ("main_color", "highlight", "text", "stroke", "main_style", "box_color")}
    pages = []
    for name, p in presets.items():
        if name.startswith("_"): continue
        fam = p["base"][0]; pl = p.get("plan", {})
        acc = pl.get("accent_color", "#ff5a93") if fam == "a" else pl.get("accent", "#2F6BFF"); acc2 = "#F3E37C" if fam == "a" else pl.get("accent2", "#9cc2ff")
        look = dict(p.get("look", {}), **user)
        html = gfx.page([], acc, acc2, look).replace("<body", f"<body style=\"background:url('{gfx.uri(bg)}') center/cover\"", 1)
        if fam == "b" and p["base"] == "b-editorial": html = html.replace('class="stack"', 'class="stack ed"')
        body = _sample_body(fam, lead, big, cap, look.get("highlight"))
        if p["base"] == "b-editorial": body = body.replace('class="stack"', 'class="stack ed"')
        hp = os.path.join(work, f"style_{name}.html"); open(hp, "w").write(html.replace("</body>", body + "</body>"))
        pages.append((name, hp, p.get("guide", "")))
    outs = asyncio.run(_shoot([hp for _, hp, _ in pages], work, prefix="style_"))
    return [(n, o, g) for (n, _, g), o in zip(pages, outs)]
