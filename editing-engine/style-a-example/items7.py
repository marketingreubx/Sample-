import json, re, math
import numpy as np
from common7 import *
INK, YEL, PINK, GREEN, RED, GREY = "#17171c", "#F3E37C", "#ff5a93", "#22c07a", "#ff4b55", "#8a8a98"
ICD = f"{V6}/lucide_pkg/package/icons"; BR = f"{V6}/broll"
F = np.array(json.load(open(f"{V7}/faces7.json")), float)
def chin(a, b):
    m = (F[:, 0] >= a - .3) & (F[:, 0] <= b + .3); f = F[m] if m.any() else F[[np.argmin(abs(F[:, 0] - a))]]
    return float(np.percentile(f[:, 2] + 1.08 * f[:, 4], 90))
_SY = {}
def shot_y(t):
    a, b, fr = next(sh for sh in SHOTS if sh[0] <= t < sh[1]) if t < T else SHOTS[-1]
    if a not in _SY: _SY[a] = int(min(max(chin(a, b) + 120, 1150), 1730))
    return _SY[a]
def icon(name, size, color="#fff", sw=2.2, fill="none"):
    s = re.sub(r"<!--.*?-->", "", open(f"{ICD}/{name}.svg").read(), flags=re.S)
    return (s.replace('width="24"', f'width="{size}"').replace('height="24"', f'height="{size}"').replace('stroke="currentColor"', f'stroke="{color}"')
             .replace('stroke-width="2"', f'stroke-width="{sw}"').replace('fill="none"', f'fill="{fill}"', 1).strip())
def dot(name, d, bg, sw=3):
    return f'<div style="width:{d}px;height:{d}px;border-radius:50%;background:{bg};display:grid;place-items:center;flex:none">{icon(name, int(d*.6), "#fff", sw)}</div>'
clean = lambda s: re.sub(r"[.,;:!?]+$", "", s).lower()
ACC = {24, 32, 39, 47, 73, 74, 76, 103, 105, 111, 115, 118, 125, 130, 148, 155, 178, 187, 195}
FUNC = {"a", "the", "to", "of", "your", "my", "and", "in", "for", "so", "i", "it's", "is", "be", "if", "can", "you"}
HOOK = set(range(0, 16))
STORY = [(80, 84, "one of the best gifts", "best gifts"), (85, 90, "that you can give your kids", "kids"),
         (91, 94, "and your loved ones", "loved ones"), (95, 102, "is to ensure that you|look after yourself", "look after yourself"),
         (159, 167, "i mean, we can try|our best as humans", "our best"), (168, 173, "and leave the rest|for God", "for God")]
STORY_IDX = {i for a, b, *_ in STORY for i in KEEP if a <= i <= b}
NUMS = {200: "30", 207: "40"}; FAB = {201: (201, 203), 208: (208, 210)}
NUM_IDX = {200, 201, 202, 203, 207, 208, 209, 210}
W_ = {x["i"]: x for x in words}

def build_items():
    L = {"front": []}
    def add(html, css, kind, t0, t1, dur=420, ease="spring", tf1=None, out=150):
        L["front"].append({"html": html, "css": css, "kind": kind, "t0": round(t0, 3), "t1": round(t1, 3), "dur": dur, "ease": ease, "tf1": tf1, "kf": None, "out": out})
    def text(html, yc, t0, t1, kind="pop", dur=170, out=60, h=170, rot=0):
        add(f'<div style="transform:rotate({rot}deg)">{html}</div>',
            f"left:0;top:{int(yc - h/2)}px;width:1080px;height:{h}px;display:flex;align-items:center;justify-content:center", kind, t0, t1, dur, "spring", None, out)
    sans = lambda s, sz=94: f'<span class="sans" style="font-size:{sz}px">{s}</span>'
    serif = lambda s, sz=128: f'<span class="serif" style="font-size:{sz}px">{s}</span>'
    hand = lambda s, sz=64, col="#fff": f'<span class="hand" style="font-size:{sz}px;color:{col}">{s}</span>'
    # ---- hook header above her head (words 0-15)
    hook_end = te_of(15) + 0.12
    text(hand("while you're strong &amp; able,", 66, YEL), 215, t_of(1), hook_end, "up", 320, 120, rot=-2)
    text(sans("look after yourself", 100), 322, t_of(7), hook_end, "pop", 260, 120)
    text(hand("from the comfort of your home", 60), 425, t_of(10), hook_end, "up", 320, 120, rot=-1)
    # ---- one-word captions (2 when a small word leads), on the chest under the chin
    seq = [x for x in words if x["i"] not in HOOK | STORY_IDX | NUM_IDX]
    chunks, k = [], 0
    while k < len(seq):
        x = seq[k]; ch = [x]
        if k + 1 < len(seq) and clean(x["w"]) in FUNC and KEEP.index(seq[k+1]["i"]) - KEEP.index(x["i"]) == 1 and seq[k+1]["o"] - x["oe"] < 0.3 and len(clean(seq[k+1]["w"])) <= 9:
            ch.append(seq[k+1]); k += 1
        chunks.append(ch); k += 1
    for n, ch in enumerate(chunks):
        a = ch[0]["o"] - 0.03; nxt = chunks[n+1][0]["o"] - 0.03 if n + 1 < len(chunks) else TT
        b = min(nxt, ch[-1]["oe"] + 0.45)
        y = shot_y(a + 0.05)
        parts = [serif(clean(x["w"])) if x["i"] in ACC else sans(clean(x["w"])) for x in ch]
        html = "".join(p.replace('style="', 'style="margin-right:0.28em;', 1) if j < len(parts) - 1 else p for j, p in enumerate(parts))
        text(html, y, a, b, "pop", 170, 0)
    # ---- handwritten story lines (key phrase in yellow)
    for a_i, b_i, s, key in STORY:
        ids = [i for i in KEEP if a_i <= i <= b_i]
        a = t_of(ids[0]) - 0.05; nx = [i for i in KEEP if i > ids[-1]]
        b = min(te_of(ids[-1]) + 0.5, t_of(nx[0]) - 0.03) if nx else te_of(ids[-1]) + .5
        lines = [ln.replace(key, f'<span style="color:{YEL}">{key}</span>') for ln in s.split("|")]
        y = int(min(max(shot_y(a + 0.05) + 40, 1180), 1680))
        html = "<br>".join(hand(ln, 80) for ln in lines)
        text(f'<div style="text-align:center;line-height:1.15">{html}</div>', y, a, b, "up", 300, 120, h=260)
    # ---- 30 / 40 and fabulous (standing: chin ~580)
    for i, n_ in NUMS.items():
        a = t_of(i) - 0.04; b = (t_of(204) - 0.05) if i == 200 else TT - END + 0.3
        text(serif(n_, 300), 790, a, b, "pop", 260, 120, h=330)
        fa, fb = FAB[i + 1]
        text(hand("&amp; <span style='color:%s'>fabulous</span>" % YEL, 84), 985, t_of(fb) - 0.05, b, "up", 300, 120, h=140, rot=-3)
    # ---- floating UI cards (white, rounded, soft shadow) above her head
    def card(inner, left, top, w, h, t0, t1, rot=0):
        add(f'<div class="card" style="width:{w}px;height:{h}px;transform:rotate({rot}deg)">{inner}</div>', f"left:{left}px;top:{top}px;width:{w}px;height:{h}px", "pop", t0, t1, 460, "spring", None, 160)
    # calendar
    days = "".join(f'<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:10px"><div class="lab" style="font-size:24px;color:{GREY}">{d}</div>'
                   f'<div style="width:100%;height:56px;border-radius:14px;background:{PINK};opacity:.92"></div><div style="width:100%;height:40px;border-radius:12px;background:#ffd1e1"></div>'
                   f'<div style="width:100%;height:30px;border-radius:10px;background:{PINK};opacity:.75"></div></div>' for d in "MTWTF")
    card(f'<div style="padding:26px 30px;display:flex;flex-direction:column;gap:18px;height:100%;box-sizing:border-box">'
         f'<div style="display:flex;align-items:center;gap:12px">{icon("calendar-days", 34, INK, 2.2)}<span class="lab" style="font-size:30px">This week</span>'
         f'<span class="lab" style="margin-left:auto;font-size:24px;color:{PINK}">fully booked</span></div><div style="display:flex;gap:12px">{days}</div></div>',
         250, 110, 580, 330, t_of(18) - 0.1, te_of(25) + 0.3)
    # nearest gym
    card(f'<div style="padding:24px 28px;display:flex;align-items:center;gap:20px;height:100%;box-sizing:border-box">{dot("map-pin", 74, "#5b8def", 2.6)}'
         f'<div style="flex:1"><div class="lab" style="font-size:31px">Nearest gym</div><div class="lab" style="font-size:24px;color:{GREY};font-weight:500">25 min drive · 6am-9pm</div></div>{dot("x", 56, RED, 3.2)}</div>',
         290, 200, 500, 150, t_of(28), 10.15)
    # home workout
    card(f'<div style="padding:22px;display:flex;gap:20px;height:100%;box-sizing:border-box;align-items:center">'
         f'<div style="width:180px;height:220px;border-radius:20px;background:url(file://{BR}/stretch.jpg) center/cover;display:grid;place-items:center">'
         f'<div style="width:64px;height:64px;border-radius:50%;background:rgba(255,255,255,.92);display:grid;place-items:center">{icon("play", 30, INK, 2.4, INK)}</div></div>'
         f'<div style="flex:1;display:flex;flex-direction:column;gap:12px"><div class="lab" style="font-size:31px">Home workout</div>'
         f'<div style="display:flex;align-items:center;gap:8px">{icon("clock", 26, GREY, 2.2)}<span class="lab" style="font-size:24px;color:{GREY};font-weight:500">20 min · no equipment</span></div>'
         f'<div style="display:flex;align-items:center;gap:8px">{icon("house", 26, GREY, 2.2)}<span class="lab" style="font-size:24px;color:{GREY};font-weight:500">living room</span></div>'
         f'<div style="margin-top:6px;height:52px;border-radius:26px;background:{INK};display:flex;align-items:center;justify-content:center;gap:10px;color:#fff;font:600 24px Poppins">{icon("play", 20, "#fff", 2.4, "#fff")}Start</div></div></div>',
         250, 110, 580, 264, t_of(35) - 0.05, cut_before(46) - 0.05)
    text(hand("no gym needed!!", 58, YEL), 450, t_of(36), cut_before(46) - 0.05, "up", 300, 120, rot=-4)
    # move ring
    ring = (f'<svg width="150" height="150" viewBox="0 0 150 150"><circle cx="75" cy="75" r="58" stroke="#ffe0ea" stroke-width="20" fill="none"/>'
            f'<circle cx="75" cy="75" r="58" stroke="{PINK}" stroke-width="20" fill="none" stroke-linecap="round" stroke-dasharray="364" stroke-dashoffset="70" transform="rotate(-90 75 75)"/></svg>')
    card(f'<div style="padding:22px 30px;display:flex;align-items:center;gap:26px;height:100%;box-sizing:border-box">{ring}'
         f'<div><div class="lab" style="font-size:24px;color:{GREY};font-weight:500">Move</div><div class="lab" style="font-size:46px">320<span style="font-size:26px;color:{GREY}">/400 kcal</span></div>'
         f'<div style="display:flex;align-items:center;gap:8px;margin-top:6px">{icon("flame", 26, PINK, 2.2)}<span class="lab" style="font-size:22px;color:{PINK}">keep going</span></div></div></div>',
         290, 120, 500, 200, t_of(74) - 0.05, te_of(79) + 0.25)
    # tilted photo cards (top corners)
    def photo(img, left, top, rot, t0, t1, cap):
        card(f'<div style="padding:14px 14px 0;height:100%;box-sizing:border-box;display:flex;flex-direction:column"><div style="flex:1;border-radius:14px;background:url(file://{BR}/{img}) center/cover"></div>'
             f'<div class="hand" style="font-size:34px;color:{INK};text-align:center;padding:8px 0 10px;text-shadow:none">{cap}</div></div>', left, top, 330, 360, t0, t1, rot)
    photo("elder_lunch.jpg", 70, 90, -6, t_of(84) - 0.05, cut_before(91) - 0.02, "the best gift")
    photo("couple_walk.webp", 680, 90, 5, t_of(108), t_of(115) - 0.1, "growing old, strong")
    photo("grandma_kids.jpg", 70, 90, -5, t_of(117) - 0.05, te_of(122) + 0.3, "memories")
    # checklist
    k0 = t_of(123) - 0.05; k1 = te_of(141) + 0.15
    rows = [("Not bedridden", t_of(125)), ("Not struggling to walk", t_of(130)), ("Not struggling with a lot of things", t_of(140) - 0.15)]
    card(f'<div style="padding:30px 36px"><div class="lab" style="font-size:26px;color:{GREY};letter-spacing:3px">WHEN YOU GET OLDER</div></div>', 110, 80, 860, 390, k0, k1)
    for r, (lab, tt) in enumerate(rows):
        y = 170 + r * 90
        add(f'<div class="lab" style="font-size:38px">{lab}</div>', f"left:240px;top:{y + 8}px", "left", k0 + .1 + r * .08, k1, 420, "spring", None, 160)
        add('<div style="width:58px;height:58px;border-radius:50%;border:4px solid #d9d9e3;box-sizing:border-box"></div>', f"left:156px;top:{y + 2}px;width:58px;height:58px", "fade", k0 + .1 + r * .08, k1, 200, "spring", None, 160)
        add(dot("check", 58, GREEN, 3.4), f"left:156px;top:{y + 2}px;width:58px;height:58px", "tick", tt, k1, 360, "spring", None, 160)
    # self-care streak
    wk = "".join(f'<div style="display:flex;flex-direction:column;align-items:center;gap:8px"><span class="lab" style="font-size:22px;color:{GREY}">{d}</span>{dot("check", 50, PINK, 3.2)}</div>' for d in "MTWTFSS")
    card(f'<div style="padding:26px 30px;display:flex;flex-direction:column;gap:20px;height:100%;box-sizing:border-box"><div style="display:flex;align-items:center;gap:12px">'
         f'{icon("flame", 38, "#ff7a45", 2.2, "#ffb38f")}<span class="lab" style="font-size:31px">Self-care streak</span><span class="lab" style="margin-left:auto;font-size:30px;color:{PINK}">365 days</span></div>'
         f'<div style="display:flex;justify-content:space-between">{wk}</div></div>', 210, 110, 660, 230, t_of(148) - 0.05, te_of(158) + 0.35)
    # 'no excuse' aside + reminder notification
    text(hand("this is your sign", 60, YEL), 470, t_of(176), t_of(182) - 0.05, "up", 300, 120, rot=-3)
    card(f'<div style="padding:22px 26px;display:flex;align-items:center;gap:20px;height:100%;box-sizing:border-box">'
         f'<div style="width:76px;height:76px;border-radius:20px;background:linear-gradient(150deg,{PINK},#ff8a5b);display:grid;place-items:center;flex:none">{icon("heart", 40, "#fff", 2.2, "#fff")}</div>'
         f'<div style="flex:1"><div style="display:flex"><span class="lab" style="font-size:24px;color:{GREY};font-weight:500">REMINDER</span><span class="lab" style="margin-left:auto;font-size:22px;color:{GREY};font-weight:500">now</span></div>'
         f'<div class="lab" style="font-size:30px;margin-top:4px">Time to move</div><div class="lab" style="font-size:24px;color:{GREY};font-weight:500">20 min workout · at home</div></div></div>',
         170, 80, 740, 160, t_of(186) - 0.05, te_of(195) + 0.3)
    # ---- end card: black, serif typed with caret, handwritten line
    e0 = T + 0.02
    add('<div style="width:1080px;height:1920px;background:#000"></div>', "left:0;top:0;width:1080px;height:1920px", "fade", e0, TT + 1, 160, "out", None, 0)
    msg = "Start today."
    for k in range(len(msg) + 1):
        a = e0 + 0.25 + k * 0.075; b = e0 + 0.25 + (k + 1) * 0.075 if k < len(msg) else TT + 1
        add(f'<div class="serif" style="font-size:120px;text-shadow:none;color:#fff">{msg[:k]}<span style="font-family:Poppins;font-style:normal;font-weight:300;color:#fff">|</span></div>',
            "left:0;top:820px;width:1080px;height:200px;display:flex;align-items:center;justify-content:center", "fade", a, b, 1, "lin", None, 0)
    text(hand("from the comfort of your home", 62, YEL), 1070, e0 + 1.35, TT + 1, "up", 360, 0, rot=-2)
    return L
