"""'5 looks' picker: renders five typography looks on a real frame of the video (a few seconds, no full render)."""
import asyncio, json, os, re, subprocess, sys
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import gfx

LOOKS_B = [
    ("Reference Blue", {"font_main": "Montserrat", "font_lead": "Poppins", "font_cap": "Poppins", "grad": "linear-gradient(180deg,#fff 0%,#fff 48%,#d3ebff 70%,#8fcaff 100%)"}, "#2F6BFF", "#9cc2ff"),
    ("Gold Luxe", {"font_main": "Playfair Display", "font_lead": "Poppins", "font_cap": "DM Sans", "grad": "linear-gradient(180deg,#fff 0%,#fff3d6 50%,#f2c46b 100%)"}, "#c9962b", "#f2d38a"),
    ("Poster Anton", {"font_main": "Anton", "font_lead": "Inter", "font_cap": "Inter", "grad": "linear-gradient(180deg,#fff 0%,#fff 55%,#ffe04a 100%)"}, "#ffcc00", "#ffe680"),
    ("Mint Modern", {"font_main": "Bricolage Grotesque", "font_lead": "Manrope", "font_cap": "Manrope", "grad": "linear-gradient(180deg,#fff 0%,#e9fff6 50%,#7ff0c4 100%)"}, "#12b886", "#9ff3d4"),
    ("Blush Editorial", {"font_main": "DM Serif Display", "font_lead": "DM Sans", "font_cap": "DM Sans", "grad": "linear-gradient(180deg,#fff 0%,#ffeef4 50%,#ff9cc0 100%)"}, "#ff5a93", "#ffc2d7"),
]
LOOKS_A = [
    ("Kilgore Classic", {"font_cap": "Poppins", "font_accent": "Instrument Serif", "font_hand": "Gochi", "accent_text": "#F3E37C"}, "#ff5a93", "#F3E37C"),
    ("Cormorant Gold", {"font_cap": "Inter", "font_accent": "Cormorant Garamond", "font_hand": "Caveat", "accent_text": "#f2c46b"}, "#c9962b", "#f2c46b"),
    ("Soft Pink", {"font_cap": "DM Sans", "font_accent": "DM Serif Display", "font_hand": "Reenie Beanie", "accent_text": "#ffb3cf"}, "#ff5a93", "#ffb3cf"),
    ("Lilac Pop", {"font_cap": "Bricolage Grotesque", "font_accent": "Playfair Display", "font_hand": "Caveat", "accent_text": "#c9b6ff"}, "#7c5cff", "#c9b6ff"),
    ("Minimal White", {"font_cap": "Manrope", "font_accent": "Instrument Serif", "font_hand": "Gochi", "accent_text": "#ffffff"}, "#111111", "#ffffff"),
]


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
        html = gfx.page([], acc, acc2, look).replace("<body>", f"<body style=\"background:url('{gfx.uri(bg)}') center/cover\">")
        if fam == "b":
            body = (f'<div style="position:absolute;left:0;right:0;top:1180px;display:flex;justify-content:center"><div class="stack">'
                    f'<div class="ln s-lead" style="font-size:46px;margin:0 0 -2px 18px">{lead}</div><div class="ln"><span class="s-glow"><span class="s-main" style="font-size:130px">{big[:16]}</span></span></div>'
                    f'<div class="ln s-tail" style="font-size:46px">for you</div></div></div>'
                    f'<div class="cap" style="position:absolute;left:0;right:0;top:1560px;text-align:center">{cap}</div>')
        else:
            w = cap.split()
            body = (f'<div style="position:absolute;left:0;right:0;top:300px;text-align:center"><span class="hand" style="font-size:66px;color:var(--c-accent)">{lead}</span></div>'
                    f'<div style="position:absolute;left:0;right:0;top:1440px;text-align:center"><span class="sans" style="font-size:96px">{w[0] if w else "the"}</span> '
                    f'<span class="serif" style="font-size:128px">{w[1] if len(w) > 1 else "secret"}</span></div>')
        p = os.path.join(work, f"look_{len(pages)}.html"); open(p, "w").write(html.replace("</body>", body + "</body>"))
        pages.append((name, p, dict(look, accent=acc, accent2=acc2)))
    outs = asyncio.run(_shoot([p for _, p, _ in pages], work))
    return [(n, o, lk) for (n, _, lk), o in zip(pages, outs)]


async def _shoot(paths, work):
    from playwright.async_api import async_playwright
    outs = []
    async with async_playwright() as p:
        b = await (p.chromium.launch(executable_path=gfx.CHROMIUM, args=["--no-sandbox"]) if gfx.CHROMIUM else p.chromium.launch(args=["--no-sandbox"]))
        pg = await b.new_page(viewport={"width": 1080, "height": 1920})
        for k, path in enumerate(paths):
            await pg.goto(gfx.uri(path)); await pg.evaluate("document.fonts.ready"); await pg.wait_for_timeout(250)
            o = os.path.join(work, f"look_{k}.jpg"); await pg.screenshot(path=o, type="jpeg", quality=80); outs.append(o)
        await b.close()
    return outs
