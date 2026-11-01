"""Style A "Calm Cards" (@itsmegkilgore): one-word captions, butter-yellow serif accents, handwritten lines,
small realistic white UI cards above the head, black typed end card. Builds the graphics layer + sound events from plan.json."""
import json, re
import numpy as np
from gfx import icon, dot

INK, YEL, GREEN, RED, GREY = "#17171c", "#F3E37C", "#22c07a", "#ff4b55", "#8a8a98"
FUNC = {"a", "the", "to", "of", "your", "my", "and", "in", "for", "so", "i", "it's", "is", "be", "if", "can", "you", "on", "at"}


def build(job, plan, cut, wins_out):
    WO = {x["i"]: x for x in cut["words"]}; T = cut["T"]; KEEP = cut["keep"]
    t_of = lambda i: WO[i]["o"]; te_of = lambda i: WO[i]["oe"]
    F = np.array(json.load(open(job.p("faces.json"))), float); shots = json.load(open(job.p("shots.json")))
    END = 2.4 if plan.get("end") else 0.0; TT = T + END
    PINK = plan.get("accent", "#ff5a93")
    L = {"front": []}; EV = []; SKIP = set()
    _sy = {}
    def shot_y(t):
        a, b, _ = next((s for s in shots if s[0] <= t < s[1]), shots[-1])
        if a not in _sy:
            m = (F[:, 0] >= a - .3) & (F[:, 0] <= b + .3); f = F[m] if m.any() else F[[np.argmin(abs(F[:, 0] - t))]]
            _sy[a] = int(min(max(np.percentile(f[:, 2] + 1.08 * f[:, 4], 90) + 120, 1150), 1730))
        return _sy[a]
    def add(html, css, kind, t0, t1, dur=None, out="blur", outd=160, kf=None, ease=None):
        it = {"html": html, "css": css, "kind": kind, "t0": round(t0, 3), "t1": round(t1, 3), "out": out, "outd": outd}
        if dur: it["dur"] = dur
        if kf: it["kf"] = kf
        if ease: it["ease"] = ease
        L["front"].append(it)
    def text(html, yc, t0, t1, kind="pop", dur=None, out="blur", outd=90, h=170, rot=0):
        add(f'<div style="transform:rotate({rot}deg)">{html}</div>', f"left:0;top:{int(yc - h / 2)}px;width:1080px;height:{h}px;display:flex;align-items:center;justify-content:center", kind, t0, t1, dur, out, outd)
    sans = lambda s, sz=94: f'<span class="sans" style="font-size:{sz}px">{s}</span>'
    serif = lambda s, sz=128: f'<span class="serif" style="font-size:{sz}px">{s}</span>'
    hand = lambda s, sz=64, col="#fff": f'<span class="hand" style="font-size:{sz}px;color:{col}">{s}</span>'
    ids = lambda a, b: [i for i in KEEP if a <= i <= b]
    # ---- hook header (above the head)
    if plan.get("header"):
        h = plan["header"]; a, b = h["w"]; he = te_of(b) + 0.12; SKIP.update(ids(a, b))
        if h.get("top"): text(hand(h["top"], 66, YEL), 215, t_of(a), he, "rise", rot=-2); EV.append((t_of(a), "swish", 0.25, 0.25))
        if h.get("main"): text(sans(h["main"], 100), 322, t_of(a) + 0.5, he, "rise", 500); EV.append((t_of(a) + 0.5, "pop", None, 0.25))
        if h.get("sub"): text(hand(h["sub"], 60), 425, t_of(a) + 1.0, he, "rise", rot=-1)
    # ---- handwritten story lines (key phrase in yellow)
    for s in plan.get("story", []):
        a_i, b_i = s["w"]; ii = ids(a_i, b_i); SKIP.update(ii)
        a = t_of(ii[0]) - 0.05; nx = [i for i in KEEP if i > ii[-1]]
        b = min(te_of(ii[-1]) + 0.5, t_of(nx[0]) - 0.03) if nx else te_of(ii[-1]) + 0.5
        lines = [ln.replace(s.get("key", "\0"), f'<span style="color:{YEL}">{s.get("key", "")}</span>') for ln in s["text"].split("|")]
        y = int(min(max(shot_y(a + 0.05) + 40, 1180), 1680))
        text(f'<div style="text-align:center;line-height:1.15">{"<br>".join(hand(ln, 80) for ln in lines)}</div>', y, a, b, "rise", 520, h=260)
    # ---- big numbers (yellow serif) + handwritten sub
    for n in plan.get("numbers", []):
        i = n["w"]; SKIP.update(ids(i, n.get("sub_w", i))); a = t_of(i) - 0.04
        b = t_of(n["until"]) - 0.05 if n.get("until") is not None else TT - END + 0.3
        y = shot_y(a + 0.05) - 60
        text(serif(n["text"], 300), y, a, b, "slam", h=330); EV.append((a, "hit", 0.6, 0.22))
        if n.get("sub"): text(hand(n["sub"].replace("*", ""), 84, YEL), y + 195, t_of(n.get("sub_w", i)) - 0.05, b, "rise", rot=-3, h=140)
    # ---- one-word captions (2 when a small word leads), fixed height per shot, instant swap
    accent = set(plan.get("accent", [])) if isinstance(plan.get("accent"), list) else set()
    clean = lambda s: re.sub(r"[.,;:!?]+$", "", s).lower()
    seq = [x for x in cut["words"] if x["i"] not in SKIP and clean(x["w"])]; chunks, k = [], 0
    while k < len(seq):
        x = seq[k]; ch = [x]
        if k + 1 < len(seq) and clean(x["w"]) in FUNC and KEEP.index(seq[k + 1]["i"]) - KEEP.index(x["i"]) == 1 and seq[k + 1]["o"] - x["oe"] < 0.3 and len(clean(seq[k + 1]["w"])) <= 9:
            ch.append(seq[k + 1]); k += 1
        chunks.append(ch); k += 1
    for n, ch in enumerate(chunks):
        a = ch[0]["o"] - 0.03; b = min(chunks[n + 1][0]["o"] - 0.03 if n + 1 < len(chunks) else TT, ch[-1]["oe"] + 0.45)
        parts = [serif(clean(x["w"])) if x["i"] in accent else sans(clean(x["w"])) for x in ch]
        html = "".join(p.replace('style="', 'style="margin-right:0.28em;', 1) if j < len(parts) - 1 else p for j, p in enumerate(parts))
        text(html, shot_y(a + 0.05), a, b, "pop", 200, "cut", 1)
    # ---- floating white UI cards
    def card(inner, left, top, w, h, t0, t1, rot=0):
        add(f'<div class="card" style="width:{w}px;height:{h}px;transform:rotate({rot}deg)">{inner}<i class="sheen"></i></div>', f"left:{left}px;top:{top}px;width:{w}px;height:{h}px", "tilt", t0, t1, out="blur", outd=200)
        EV.append((t0, "swish", 0.25, 0.3)); EV.append((t0 + 0.1, "pop", None, 0.18))
    lab = lambda s, sz=31, col=INK, wt=600: f'<span class="lab" style="font-size:{sz}px;color:{col};font-weight:{wt}">{s}</span>'
    for c in plan.get("cards", []):
        a, b = c["w"]; t0, t1 = t_of(a) - 0.05, te_of(b) + 0.3; ty = c["type"]
        if ty == "calendar":
            days = "".join(f'<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:10px">{lab(d, 24, GREY)}<div style="width:100%;height:56px;border-radius:14px;background:{PINK};opacity:.92"></div>'
                           f'<div style="width:100%;height:40px;border-radius:12px;background:{PINK}33"></div><div style="width:100%;height:30px;border-radius:10px;background:{PINK};opacity:.7"></div></div>' for d in c.get("days", "MTWTF"))
            card(f'<div style="padding:26px 30px;display:flex;flex-direction:column;gap:18px"><div style="display:flex;align-items:center;gap:12px">{icon("calendar-days", 34, INK, 2.2)}{lab(c.get("title", "This week"), 30)}'
                 f'<span style="margin-left:auto">{lab(c.get("badge", ""), 24, PINK)}</span></div><div style="display:flex;gap:12px">{days}</div></div>', 250, 110, 580, 330, t0, t1)
        elif ty == "app":
            thumb = f'<div style="width:180px;height:220px;border-radius:20px;background:url(file://{job.broll}/{c["img"]}) center/cover;display:grid;place-items:center"><div style="width:64px;height:64px;border-radius:50%;background:rgba(255,255,255,.92);display:grid;place-items:center">{icon("play", 30, INK, 2.4, INK)}</div></div>' if c.get("img") else ""
            lines = "".join(f'<div style="display:flex;align-items:center;gap:8px">{icon(ic, 26, GREY, 2.2)}{lab(t, 24, GREY, 500)}</div>' for ic, t in c.get("lines", []))
            btn = f'<div style="margin-top:6px;height:52px;border-radius:26px;background:{INK};display:flex;align-items:center;justify-content:center;gap:10px;color:#fff;font:600 24px Poppins">{icon("play", 20, "#fff", 2.4, "#fff")}{c["button"]}</div>' if c.get("button") else ""
            card(f'<div style="padding:22px;display:flex;gap:20px;height:100%;box-sizing:border-box;align-items:center">{thumb}<div style="flex:1;display:flex;flex-direction:column;gap:12px">{lab(c["title"])}{lines}{btn}</div></div>', 250, 110, 580, 264, t0, t1)
        elif ty == "checklist":
            n = len(c["rows"]); h = 120 + n * 90
            card(f'<div style="padding:30px 36px">{lab(c.get("kicker", ""), 26, GREY)}</div>', 110, 80, 860, h, t0, t1)
            for r, (txt, wi) in enumerate(c["rows"]):
                y = 170 + r * 90; tt = t_of(wi) if wi is not None and wi in WO else t0 + 0.3
                add(lab(txt, 38), f"left:240px;top:{y + 8}px", "rise", t0 + .1 + r * .08, t1)
                add('<div style="width:58px;height:58px;border-radius:50%;border:4px solid #d9d9e3;box-sizing:border-box"></div>', f"left:156px;top:{y + 2}px;width:58px;height:58px", "fade", t0 + .1 + r * .08, t1)
                add(dot("check", 58, GREEN, "#fff", 3.4), f"left:156px;top:{y + 2}px;width:58px;height:58px", "tick", tt, t1); EV.append((tt, "tick", None, 0.4))
        elif ty == "streak":
            wk = "".join(f'<div style="display:flex;flex-direction:column;align-items:center;gap:8px">{lab(d, 22, GREY)}{dot("check", 50, PINK, "#fff", 3.2)}</div>' for d in c.get("days", "MTWTFSS"))
            card(f'<div style="padding:26px 30px;display:flex;flex-direction:column;gap:20px"><div style="display:flex;align-items:center;gap:12px">{icon("flame", 38, "#ff7a45", 2.2, "#ffb38f")}{lab(c["title"])}'
                 f'<span style="margin-left:auto">{lab(c.get("value", ""), 30, PINK)}</span></div><div style="display:flex;justify-content:space-between">{wk}</div></div>', 210, 110, 660, 230, t0, t1)
        elif ty == "notification":
            card(f'<div style="padding:22px 26px;display:flex;align-items:center;gap:20px;height:100%;box-sizing:border-box"><div style="width:76px;height:76px;border-radius:20px;background:{PINK};display:grid;place-items:center;flex:none">{icon(c.get("icon", "bell"), 40, "#fff", 2.2)}</div>'
                 f'<div style="flex:1"><div style="display:flex">{lab(c.get("kicker", "REMINDER"), 24, GREY, 500)}<span style="margin-left:auto">{lab("now", 22, GREY, 500)}</span></div>{lab(c["title"], 30)}<div>{lab(c.get("sub", ""), 24, GREY, 500)}</div></div></div>', 170, 80, 740, 160, t0, t1)
        elif ty == "photo":
            left = 70 if c.get("side", "left") == "left" else 680; rot = -6 if left == 70 else 5
            card(f'<div style="padding:14px 14px 0;height:100%;box-sizing:border-box;display:flex;flex-direction:column"><div style="flex:1;border-radius:14px;background:url(file://{job.broll}/{c["img"]}) center/cover"></div>'
                 f'<div class="hand" style="font-size:34px;color:{INK};text-align:center;padding:8px 0 10px;text-shadow:none">{c.get("caption", "")}</div></div>', left, 90, 330, 360, t0, t1, rot)
        elif ty == "stat":
            pct = float(c.get("pct", 0.8))
            ring = (f'<svg width="150" height="150" viewBox="0 0 150 150"><circle cx="75" cy="75" r="58" stroke="{PINK}33" stroke-width="20" fill="none"/>'
                    f'<circle cx="75" cy="75" r="58" stroke="{PINK}" stroke-width="20" fill="none" stroke-linecap="round" stroke-dasharray="364" stroke-dashoffset="{364 * (1 - pct):.0f}" transform="rotate(-90 75 75)"/></svg>')
            card(f'<div style="padding:22px 30px;display:flex;align-items:center;gap:26px;height:100%;box-sizing:border-box">{ring}<div>{lab(c.get("label", ""), 24, GREY, 500)}<div>{lab(c.get("value", ""), 46)}</div>'
                 f'<div>{lab(c.get("sub", ""), 22, PINK)}</div></div></div>', 290, 120, 500, 200, t0, t1)
        else:  # list
            rows = "".join(f'<div style="display:flex;align-items:center;gap:14px">{dot(ic, 44, PINK, "#fff", 2.6)}{lab(t, 30)}</div>' for ic, t in c.get("rows", []))
            card(f'<div style="padding:28px 32px;display:flex;flex-direction:column;gap:16px">{lab(c.get("title", ""), 26, GREY)}{rows}</div>', 200, 90, 680, 110 + 64 * len(c.get("rows", [])), t0, t1)
    for s in plan.get("asides", []):
        a, b = s["w"]; text(hand(s["text"], 58, YEL), s.get("y", 470), t_of(a), te_of(b) + 0.2, "rise", rot=s.get("rot", -3)); EV.append((t_of(a), "swish", 0.2, 0.18))
    # ---- black end card with typed serif line
    if plan.get("end"):
        e0 = T + 0.02; msg = plan["end"]["text"]
        add('<div style="width:1080px;height:1920px;background:#000"></div>', "left:0;top:0;width:1080px;height:1920px", "fade", e0, TT + 1)
        for k in range(len(msg) + 1):
            a = e0 + 0.25 + k * 0.075; b = e0 + 0.25 + (k + 1) * 0.075 if k < len(msg) else TT + 1
            add(f'<div class="serif" style="font-size:120px;color:#fff;text-shadow:none">{msg[:k]}<span style="font-family:Poppins;font-style:normal;font-weight:300">|</span></div>',
                "left:0;top:820px;width:1080px;height:200px;display:flex;align-items:center;justify-content:center", "fade", a, b, out="cut", outd=1)
        EV.append((e0 + 0.25, "typing", 0.075 * len(msg) + 0.1, 0.3))
        if plan["end"].get("sub"): text(hand(plan["end"]["sub"], 62, YEL), 1070, e0 + 0.4 + 0.075 * len(msg), TT + 1, "rise", rot=-2)
    for i in plan.get("punch", []):
        if i in WO: EV.append((t_of(i) - 0.12, "camera", 0.3, 0.35))
    return L, EV, END
