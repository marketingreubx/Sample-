"""Style B "Motion Pop" (@edita.visuals): a new visual every 1-4 s. Builds the graphics layer + sound-design events from plan.json."""
import json, re
import numpy as np
from PIL import ImageFont
from gfx import icon, dot, FONTDIR
from core import uri

RED, GREEN, INK, GREY = "#ff4b55", "#22c07a", "#0e1630", "#8a90a6"
_FM = {}


def fit(text, size, font=FONTDIR + "/Montserrat-Black.ttf", maxw=1000, ls=1, extra=0.62):
    t = re.sub(r"<[^>]+>", "", text).replace("&amp;", "&")
    longest = max(t.split("\n"), key=len)
    while size > 40:
        f = _FM.setdefault((font, size), ImageFont.truetype(font, size))
        if f.getlength(longest) + ls * len(longest) + extra * size + 30 <= maxw: return size
        size -= 4
    return size


def num_frames(to, steps=8):
    m = re.match(r"^([^\d]*)([\d.,]+)(.*)$", to)
    if not m: return [to]
    pre, n, suf = m.group(1), float(m.group(2).replace(",", "")), m.group(3)
    dec = len(m.group(2).split(".")[1]) if "." in m.group(2) else 0
    fr = [0.08, 0.22, 0.4, 0.56, 0.7, 0.82, 0.92, 1.0][-steps:]
    out = []
    for f in fr:
        v = n * f
        s = f"{v:,.{dec}f}" if dec else (f"{int(round(v)):,}" if "," in m.group(2) or n >= 1000 else f"{int(round(v))}")
        out.append(pre + s + suf)
    return out


def build(job, plan, cut, wins_out):
    WO = {x["i"]: x for x in cut["words"]}; T = cut["T"]
    t_of = lambda i: WO[i]["o"]; te_of = lambda i: WO[i]["oe"]
    F = np.array(json.load(open(job.p("faces.json"))), float); shots = json.load(open(job.p("shots.json")))
    editorial = plan.get("style", "b-loud") == "b-editorial"
    import subprocess
    _raw = subprocess.run(["ffmpeg", "-v", "error", "-i", job.p("base.mp4"), "-vf", "fps=5,scale=108:192,format=gray", "-f", "rawvideo", "-"], capture_output=True, check=True).stdout
    LUM = np.frombuffer(_raw, np.uint8).reshape(-1, 192, 108).astype(np.float32) / 255
    L = {"front": [], "wipe": []}; EV = []; BUSY = []
    def chin_at(t):
        a, b, _ = next((s for s in shots if s[0] <= t < s[1]), shots[-1])
        m = (F[:, 0] >= a - .3) & (F[:, 0] <= b + .3)
        f = F[m] if m.any() else F[[np.argmin(abs(F[:, 0] - t))]]
        return float(np.percentile(f[:, 2] + 1.1 * f[:, 4], 90))
    G = {"id": None}
    def add(html, css, kind, t0, t1, dur=None, out="blur", outd=180, kf=None, ease=None, layer="front"):
        it = {"html": html, "css": css, "kind": kind, "t0": round(t0, 3), "t1": round(t1, 3), "out": out, "outd": outd}
        if G["id"] is not None and layer == "front": it["g"] = G["id"]
        if dur: it["dur"] = dur
        if kf: it["kf"] = kf
        if ease: it["ease"] = ease
        L[layer].append(it)
    WINS_REF = []
    def scrim_alpha(t, yc, w, h):
        """Contrast check: brighter / busier background behind the text -> stronger feathered shadow."""
        win = next((x for x in WINS_REF if x["a"] <= t < x["b"]), None)
        if win and win["lay"] == "F": return 0.62
        k = min(int(t * 5), len(LUM) - 1); y0, y1 = int(max(yc - h / 2, 0) / 10), int(min(yc + h / 2, 1919) / 10) + 1
        x0, x1 = int(max(540 - w / 2, 0) / 10), int(min(540 + w / 2, 1079) / 10) + 1
        reg = LUM[k, y0:y1, x0:x1]
        if reg.size == 0: return 0.4
        return float(np.clip(0.28 + (reg.mean() - 0.30) * 1.1 + reg.std() * 0.8, 0.28, 0.72))
    def row(html, yc, t0, t1, kind="slam", h=260, scrim=None, **k):
        if scrim:
            sw, shh = scrim; a = scrim_alpha(t0 + 0.1, yc, sw, shh)
            html = (f'<div style="position:relative;isolation:isolate;display:flex;align-items:center;justify-content:center">'
                    f'<div class="scrim" style="width:{sw:.0f}px;height:{shh:.0f}px;opacity:{a:.2f}"></div>{html}</div>')
        add(html, f"left:0;top:{int(yc - h / 2)}px;width:1080px;height:{h}px;display:flex;align-items:center;justify-content:center", kind, t0, t1, **k)
    KB = lambda big: "".join(f'<span class="kbl">{ln}<i class="sheen"></i></span>' for ln in big.split("<br>"))
    # ---------------- b-roll windows
    wins = []
    for b in plan.get("broll", []):
        i0, i1 = b["w"]; x = {"img": b["img"], "lay": b["lay"], "a": t_of(i0) - 0.05, "b": te_of(i1) + 0.12, "archive": b.get("archive", editorial)}
        wins.append(x)
    for x in wins:   # S windows need the speaker crop; never run past a hard shot change (except to hide one)
        if x["lay"] == "S":
            f = F[(F[:, 0] >= x["a"] - .2) & (F[:, 0] <= x["b"] + .2)]
            fx, fy, fw, fh = np.median(f[:, 1:], 0) if len(f) else (300, 500, 480, 480)
            x["y0"] = int(min(max(fy + fh / 2 - 0.42 * 886, 0), 1920 - 886)); x["x0"] = int(min(max(fx + fw / 2 - 480, 0), 120))
            x["capy"] = int(min(944 + (fy + 1.1 * fh - x["y0"]) * 1.075 + 70, 1840))
        BUSY.append((x["a"], x["b"], "win"))
    wins_out.extend(wins); WINS_REF.extend(wins)
    # ---------------- keyword blocks
    ED = ' ed' if editorial else ''
    def stack_html(lead, mains, tail, size, lsz, d_lead, d_main, d_tail, hide_main=False):
        """Reference typography: small italic lead hugging the top-left of a heavy italic main word, tail hugging bottom-right."""
        out = f'<div class="stack{ED}">'
        if lead: out += f'<div class="ln s-lead" data-a="left" data-d="{d_lead:.2f}" style="font-size:{lsz}px;margin:0 0 {-0.015 * size:.0f}px {0.10 * size:.0f}px;position:relative;z-index:2">{lead}</div>'
        for k, m in enumerate(mains):
            vis = "visibility:hidden;" if hide_main else ""
            out += f'<div class="ln" data-a="zoom" data-d="{d_main + 0.07 * k:.2f}" style="{vis}"><span class="s-glow"><span class="s-main" style="font-size:{size}px">{m}</span></span></div>'
        if tail: out += f'<div class="ln s-tail" data-a="right" data-d="{d_tail:.2f}" style="font-size:{lsz}px;margin:{-0.05 * size:.0f}px {0.06 * size:.0f}px 0 0">{tail}</div>'
        return out + '</div>'
    def stack_geo(lead, mains, tail, size):
        lsz = max(38, min(62, int(size * 0.32)))
        h = (lsz - 0.015 * size if lead else 0) + 0.96 * size * len(mains) + (lsz - 0.05 * size if tail else 0)
        return lsz, h
    def block(lead, big, size, tl, tb, t1, small=None, xbadge=False):
        mains = big.split("<br>")
        font = FONTDIR + "/InstrumentSerif-Italic.ttf" if editorial else FONTDIR + "/Montserrat-ExtraBold.ttf"
        size = fit("\n".join(mains), int(size * (1.15 if editorial else 1.0)), font, 820 - (170 if xbadge else 0), 0 if editorial else -1, 0.25)
        lsz, hh = stack_geo(lead, mains, small, size)
        t0 = tl if lead else tb; BUSY.append((t0 - 0.05, t1, "blk"))
        yc = chin_at(tb) + 70 + hh / 2
        xb = f'<div data-a="zoom" data-d="{tb - t0 + 0.12:.2f}" style="margin-left:26px">{dot("x", 130, RED, "#fff", 3.2)}</div>' if xbadge else ""
        html = f'<div style="display:flex;align-items:center">{stack_html(lead, mains, small, size, lsz, 0, tb - t0, tb - t0 + 0.14)}{xb}</div>'
        sw = max(max(len(re.sub(r"<[^>]+>", "", m)) for m in mains) * 0.66 * size, len(lead) * 0.52 * lsz) + 160
        row(html, yc, t0, t1, "fade", int(hh + 80), scrim=(sw, hh + 90), outd=200)
        if lead: EV.append((t0, "swish", 0.22, 0.22))
        EV.append((tb, "hit", 0.7, 0.30 if not editorial else 0.16))
    for b in plan.get("blocks", []):
        G["id"] = "blk%d" % len(L["front"])
        il, ib, ie = b["w"]
        block(b.get("lead", ""), b["text"], b.get("size", 150), t_of(il), t_of(ib), te_of(ie) + 0.25, b.get("small") or b.get("tail"), b.get("x", False))
    # ---------------- counters (same typography; the number counts up)
    for c in plan.get("counts", []):
        G["id"] = "cnt%d" % len(L["front"])
        i0, i1 = c["w"]; t0, t1 = t_of(i0), te_of(i1) + 0.2
        font = FONTDIR + "/InstrumentSerif-Italic.ttf" if editorial else FONTDIR + "/Montserrat-ExtraBold.ttf"
        size = fit(c["to"], 200, font, 820, -1, 0.25); lead, tail = c.get("lead", ""), c.get("small") or c.get("tail")
        lsz, hh = stack_geo(lead, [c["to"]], tail, size); top = chin_at(t0) + 70; yc = top + hh / 2; tl = t0 - 0.3
        BUSY.append((tl - 0.05, t1, "blk"))
        row(stack_html(lead, [c["to"]], tail, size, lsz, 0, 0, 0.3 + 0.44, hide_main=True), yc, tl, t1, "fade", int(hh + 80), scrim=(max(len(c["to"]) * 0.66 * size, len(lead) * 0.52 * lsz) + 160, hh + 90), outd=200)
        ym = top + (lsz - 0.015 * size if lead else 0) + 0.48 * size
        fr = num_frames(c["to"])
        for k, v in enumerate(fr):
            a = t0 + k * 0.055; b = t0 + (k + 1) * 0.055 if k + 1 < len(fr) else t1
            inner = f'<div class="stack{ED}"><span class="s-glow"><span class="s-main" style="font-size:{size}px">{v}</span></span></div>'
            row(inner, ym, a, b, "slam" if k == 0 else "fade", int(size * 1.4), out="cut" if k + 1 < len(fr) else "blur", outd=1 if k + 1 < len(fr) else 200)
        EV += [(t0 - 0.4, "riser", 0.4, 0.2), (t0 + len(fr) * 0.055, "chime", None, 0.2)]
    # ---------------- glass pills, chips, progress bars, toggles
    def glass(inner, left, top, w, h, t0, t1, radius=40, kind="glide", cls="glass"):
        add(f'<div class="{cls}" style="width:{w}px;height:{h}px;border-radius:{radius}px">{inner}<i class="sheen"></i></div>', f"left:{left}px;top:{top}px;width:{w}px;height:{h}px", kind, t0, t1)
        EV.append((t0, "swish", 0.22, 0.28)); EV.append((t0 + 0.12, "tick", None, 0.18))
    for p in plan.get("pills", []):
        G["id"] = "pill%d" % len(L["front"])
        i0, i1 = p["w"]; t0, t1 = t_of(i0), te_of(i1) + 0.2; BUSY.append((t0, t1, "ui", int(chin_at(t0) + 140 + 130)))
        glass(f'<div style="display:flex;align-items:center;gap:20px;height:100%;padding:0 34px">{dot(p.get("icon", "sparkles"), 70, p.get("color", "var(--acc)"))}<span class="gl">{p["text"]}</span></div>',
              140, int(chin_at(t0) + 140), 800, 130, t0, t1)
    for c in plan.get("chips", []):
        G["id"] = "chip%d" % len(L["front"])
        i0, i1 = c["w"]; t0, t1 = t_of(i0), te_of(i1) + 0.2; top = int(chin_at(t0) + 120); BUSY.append((t0, t1, "ui", top + 146))
        n = len(c["items"]); wch = min(320, (1000 - 20 * (n - 1)) // n)
        for k, (ic, lab) in enumerate(c["items"]):
            glass(f'<div style="display:flex;align-items:center;gap:12px;height:100%;padding:0 22px">{dot(ic, 52, "var(--acc)")}<span class="gl" style="font-size:34px">{lab}</span></div>',
                  40 + k * (wch + 20), top + (k % 2) * 46, wch, 100, t0 + k * 0.2, t1, radius=50)
    for b in plan.get("bars", []):
        G["id"] = "bar%d" % len(L["front"])
        i0, i1 = b["w"]; t0, t1 = t_of(i0), te_of(i1) + 0.2; top = int(chin_at(t0) + 120); BUSY.append((t0, t1, "ui", top + 210))
        ticks = b.get("ticks", ["", ""])
        glass(f'<div style="padding:30px 38px;display:flex;flex-direction:column;gap:20px;height:100%;box-sizing:border-box"><div style="display:flex;align-items:center;gap:14px">{icon(b.get("icon", "rocket"), 40, "#fff", 2.2)}'
              f'<span class="gl">{b["label"]}</span><span class="gl" style="margin-left:auto"><b>{b.get("value", "")}</b></span></div>'
              f'<div style="height:22px;border-radius:11px;background:rgba(255,255,255,.16);overflow:hidden"><div data-w="0.35" data-l="1500" style="height:100%;background:linear-gradient(90deg,var(--acc),var(--acc2));transform-origin:0 50%"></div></div>'
              f'<div style="display:flex;justify-content:space-between"><span class="gs">{ticks[0]}</span><span class="gs">{ticks[-1]}</span></div></div>', 140, top, 800, 210, t0, t1, cls="glass-l")
    for g in plan.get("toggles", []):
        G["id"] = "tog%d" % len(L["front"])
        i0, i1 = g["w"]; t0, t1 = t_of(i0), te_of(i1) + 0.2; top = int(chin_at(t0) + 120); BUSY.append((t0, t1, "ui", top + 140))
        glass(f'<div style="display:flex;align-items:center;gap:20px;height:100%;padding:0 30px">{dot(g.get("icon", "check"), 64, "var(--acc)")}<div style="flex:1"><div class="gl">{g["label"]}</div><div class="gs">{g.get("sub", "")}</div></div>'
              f'<div style="width:116px;height:66px;border-radius:33px;background:rgba(255,255,255,.22)"></div></div>', 180, top, 720, 140, t0, t1)
        add(f'<div style="width:116px;height:66px;border-radius:33px;background:{GREEN}"></div>', f"left:754px;top:{top + 37}px;width:116px;height:66px", "fade", t0 + 0.45, t1)
        add('<div style="width:56px;height:56px;border-radius:50%;background:#fff;box-shadow:0 3px 8px rgba(0,0,0,.3)"></div>', f"left:759px;top:{top + 42}px;width:56px;height:56px",
            "wipe", t0 + 0.4, t1, dur=320, ease="back", kf=[{"transform": "translateX(0)"}, {"transform": "translateX(50px)"}])
        EV.append((t0 + 0.42, "tick", None, 0.35))
    G["id"] = None
    # ---------------- full-frame interstitial cards
    def full(inner, dark, t0, t1):
        BUSY.append((t0, t1, "card", dark))
        bg = "linear-gradient(165deg,#070f2b 0%,#14306f 55%,var(--acc) 130%)" if dark else "linear-gradient(170deg,#f7f8fc 0%,#e7ecf8 100%)"
        add(f'<div style="width:1080px;height:1920px;background:{bg};position:relative;overflow:hidden">'
            f'<div style="position:absolute;inset:0;background:radial-gradient(900px 700px at 85% 8%,rgba(255,255,255,{.18 if dark else .9}),transparent 60%)"></div>{inner}<div class="grain"></div></div>',
            "left:0;top:0;width:1080px;height:1920px", "slide", t0, t1, out="slide", outd=420)
        EV.append((t0 - 0.12, "whoosh", 0.62, 0.5)); EV.append((t1 - 0.1, "whoosh", 0.5, 0.35))
    for c in plan.get("cards", []):
        i0, i1 = c["w"]; t0, t1 = t_of(i0) - 0.05, te_of(i1) + 0.25; dark = c.get("dark", True)
        fg, kc = ("#fff", "var(--acc2)") if dark else (INK, "var(--acc)")
        head = (f'<div class="kick" style="color:{kc}" data-d="0.05">{c.get("kicker", "")}</div>'
                f'<div class="h1" style="color:{fg}" data-d="0.12">{c.get("title", "")}</div>')
        if c["type"] == "list":
            rows = "".join(f'<div class="row" data-d="{0.3 + k * 0.16:.2f}"><div class="mono">{r[0]}</div><div style="flex:1"><div class="wn">{r[1]}</div><div class="ws">{r[2]}</div></div><div class="wv">{r[3]}</div></div>' for k, r in enumerate(c["rows"]))
            body = head + rows + (f'<div data-d="0.9" style="align-self:flex-start;display:flex;align-items:center;gap:16px;padding:18px 30px;border-radius:60px;background:rgba(255,255,255,.14);border:1.5px solid rgba(255,255,255,.4)">{dot(c.get("badge_icon", "sparkles"), 58, "#d97757")}<span class="gl">{c["badge"]}</span></div>' if c.get("badge") else "")
        elif c["type"] == "steps":
            rows = "".join(f'<div class="row" data-d="{0.3 + k * 0.18:.2f}"><div style="font:900 italic 60px Montserrat;color:var(--acc);width:56px">{k + 1}</div>{dot(r[0], 72, "var(--acc)")}<div class="wn">{r[1]}</div></div>' for k, r in enumerate(c["rows"]))
            body = head + rows
        elif c["type"] == "flow":
            a, lines = c["from"], c["lines"]
            ln = "".join(f'<div data-d="{0.9 + k * 0.35:.2f}" style="display:flex;align-items:center;gap:22px"><span style="font:800 26px Poppins;color:var(--acc);background:rgba(61,123,255,.12);padding:10px 18px;border-radius:14px;width:160px;text-align:center">{t}</span><span style="height:22px;border-radius:11px;background:#dfe4f2;width:{320 + (k * 53) % 180}px"></span></div>' for k, t in enumerate(lines))
            body = (head + f'<div class="row" data-d="0.3" style="align-self:flex-start">{dot(a[0], 84, "#ffb020")}<div><div class="wn">{a[1]}</div><div class="ws">{a[2]}</div></div></div>'
                    f'<div data-d="0.6" style="align-self:center">{icon("arrow-down", 100, "var(--acc)" if False else "#3d7bff", 2.4)}</div>'
                    f'<div class="row" data-d="0.75" style="flex-direction:column;align-items:stretch;gap:28px;padding:40px"><div style="display:flex;align-items:center;gap:16px">{icon(c.get("to_icon", "file-text"), 52, "#3d7bff", 2.2)}<span class="wn">{c["to"]}</span></div>{ln}'
                    f'<div data-d="{0.9 + len(lines) * 0.35:.2f}" style="align-self:flex-start;display:flex;align-items:center;gap:12px;font:600 30px Poppins;color:#fff;background:{GREEN};padding:16px 28px;border-radius:40px">{icon(c.get("done_icon", "check"), 32, "#fff", 2.6)} {c.get("done", "done")}</div></div>')
        elif c["type"] == "browser":
            img = uri(job.broll + "/" + c["img"]) if c.get("img") else ""
            tiles = "".join(f'<div style="flex:1;height:200px;border-radius:22px;background:{col};display:flex;align-items:flex-end;padding:18px;box-sizing:border-box;color:#fff;font:600 24px Poppins">{t}</div>' for col, t in zip(("var(--acc)", "#ff8a5b", GREEN), c.get("tiles", ["", "", ""])))
            body = (head + '<div style="position:relative;height:1000px">'
                    '<div data-d="0.3" style="position:absolute;inset:0;border-radius:32px;background:#fff;overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,.45)"><div style="height:70px;background:#eef1f8;display:flex;align-items:center;gap:12px;padding:0 26px"><i style="width:18px;height:18px;border-radius:50%;background:#c9cfdd"></i><i style="width:18px;height:18px;border-radius:50%;background:#c9cfdd"></i><span style="margin-left:20px;font:500 24px Poppins;color:#8a90a6">' + c.get("url", "yourbrand.com") + '</span></div>'
                    '<div style="padding:40px;display:flex;flex-direction:column;gap:26px">' + "".join(f'<div style="height:{h}px;width:{w_}%;border-radius:18px;background:repeating-linear-gradient(45deg,#e9edf6,#e9edf6 14px,#f3f5fa 14px,#f3f5fa 28px)"></div>' for h, w_ in ((300, 100), (46, 70), (46, 50), (200, 100))) + '</div></div>'
                    f'<div data-d="1.6" style="position:absolute;inset:0;border-radius:32px;background:#fff;overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,.45)"><div style="height:70px;background:#eef1f8;display:flex;align-items:center;padding:0 26px"><span style="font:500 24px Poppins;color:#8a90a6">{c.get("url", "yourbrand.com")}</span></div>'
                    f'<div style="height:420px;background:url({img}) center/cover,var(--acc);position:relative"><div style="position:absolute;inset:0;background:linear-gradient(90deg,rgba(7,15,43,.88),rgba(7,15,43,.15))"></div>'
                    f'<div style="position:absolute;left:48px;top:90px;color:#fff;font:800 66px Poppins;line-height:1.05">{c.get("brand", "Your Brand")}<div style="font:500 30px Poppins;opacity:.9;margin-top:14px">{c.get("tagline", "")}</div>'
                    f'<div style="margin-top:26px;display:inline-block;padding:16px 34px;border-radius:40px;background:var(--acc);font:600 28px Poppins">{c.get("button", "Get started")}</div></div></div>'
                    f'<div style="padding:40px;display:flex;gap:24px">{tiles}</div></div></div>')
        else:  # quote
            body = head + f'<div class="kser" data-d="0.3" style="font-size:120px;white-space:normal;color:{fg};text-align:left">{c.get("quote", "")}</div>'
        full(f'<div style="position:absolute;left:90px;right:90px;top:140px;bottom:240px;display:flex;flex-direction:column;justify-content:center;gap:30px">{body}</div>', dark, t0, t1)
        if c["type"] == "browser": EV.append((t0 + 1.6, "shimmer", 0.7, 0.3))
    # ---------------- CTA comment box
    if plan.get("cta"):
        G["id"] = "cta"
        c = plan["cta"]; i0, i1 = c["w"]; t0 = t_of(i0); top = int(chin_at(t0) + 150); BUSY.append((t0, T, "blk"))
        glass(f'<div style="display:flex;align-items:center;gap:20px;height:100%;padding:0 30px"><div style="width:74px;height:74px;border-radius:50%;background:linear-gradient(135deg,#ff8a5b,#d62976,#4f5bd5);flex:none"></div>'
              f'<div style="flex:1"></div>{dot("send", 70, "var(--acc)")}</div>', 90, top, 900, 130, t0, T + 1, radius=66)
        add('<div style="font:500 36px Poppins;color:rgba(255,255,255,.6)">Add a comment…</div>', f"left:214px;top:{top + 42}px", "fade", t0 + 0.1, t0 + 0.34, out="cut", outd=1)
        kw = c["keyword"]
        for k in range(1, len(kw) + 1):
            a = t0 + 0.25 + k * 0.1; b = t0 + 0.25 + (k + 1) * 0.1 if k < len(kw) else T + 1
            add(f'<div style="font:700 40px Poppins;color:#fff">{kw[:k]}<span style="font-weight:300">|</span></div>', f"left:214px;top:{top + 40}px", "fade", a, b, out="cut", outd=1)
        EV.append((t0 + 0.3, "typing", 0.1 * len(kw) + 0.1, 0.35))
        if c.get("line"): row(f'<div class="lead" style="font-size:50px">{c["line"]}</div>', top + 230, te_of(i0) + 0.6, T + 1, "rise", 90)
    G["id"] = None
    # ---------------- chest elements never overlap: each one hands off when the next arrives
    groups = {}
    for it in L["front"]:
        if "g" in it: groups.setdefault(it["g"], []).append(it)
    seq_g = sorted(groups.values(), key=lambda its: min(i["t0"] for i in its))
    for cur_g, nxt_g in zip(seq_g, seq_g[1:]):
        cut_t = min(i["t0"] for i in nxt_g) - 0.03
        for i in cur_g:
            if i["t1"] > cut_t: i["t1"] = round(max(cut_t, i["t0"] + 0.1), 3)
        for k, e in enumerate(BUSY):
            if e[2] in ("blk", "ui") and abs(e[1] - max(i["t1"] for i in cur_g)) < 5 and e[0] <= min(i["t0"] for i in cur_g) + 0.4 and e[1] > cut_t:
                BUSY[k] = (e[0], cut_t) + tuple(e[2:])
    # ---------------- captions (small, chest) where nothing else is on screen
    key = {k.lower() for k in plan.get("key", [])}
    over = lambda a, b, kind: [e for e in BUSY if e[2] == kind and a < e[1] and b > e[0]]
    clean = lambda s: re.sub(r"[.,;:!?]+$", "", s)
    seq = [x for x in cut["words"] if clean(x["w"])]; chunks, cur = [], []
    for x in seq:
        if cur and (x["o"] - cur[-1]["oe"] > 0.25 or len(cur) >= 3 or len(" ".join(clean(y["w"]) for y in cur + [x])) > 16): chunks.append(cur); cur = []
        cur.append(x)
        if x["w"][-1] in ".,?!": chunks.append(cur); cur = []
    if cur: chunks.append(cur)
    for n, ch in enumerate(chunks):
        a = ch[0]["o"] - 0.03; b = min(chunks[n + 1][0]["o"] - 0.03 if n + 1 < len(chunks) else T, ch[-1]["oe"] + 0.35)
        if over(a, b, "blk"): continue          # the keyword block on screen already shows these words
        txt = " ".join(clean(x["w"]).lower() for x in ch)
        parts = [f'<span style="color:var(--acc2);font-weight:800">{clean(x["w"]).lower()}</span>' if clean(x["w"]).lower() in key else clean(x["w"]).lower() for x in ch]
        cards, uis = over(a, b, "card"), over(a, b, "ui")
        if cards:
            y = 1730
            if not cards[0][3]:                  # light card: dark text, no shadow needed
                row(f'<div class="cap" style="color:#0e1630;text-shadow:none">{" ".join(parts)}</div>', y, a, b, "pop", 120, dur=260, outd=60); continue
        elif uis: y = min(max(e[3] for e in uis) + 80, 1800)
        else:
            wv = next((x for x in wins if x["a"] <= a + 0.05 < x["b"]), None)
            y = wv["capy"] if wv and wv["lay"] == "S" else 1560 if wv else int(min(chin_at(a + 0.05) + 110, 1700))
        row(f'<div class="cap">{" ".join(parts)}</div>', y, a, b, "pop", 140, scrim=(len(txt) * 36 + 140, 150), dur=260, outd=60)
    # ---------------- wipes into full-screen photos; swish into split screens
    for k, x in enumerate([x for x in wins if x["lay"] == "F"]):
        t = x["a"] - 0.3
        if k % 2 == 0:
            add('<div style="width:1500px;height:2100px;border-radius:140px 0 0 140px;background:linear-gradient(90deg,#fff 0%,#fff 90%,#e2e9ff 100%);box-shadow:-60px 0 110px rgba(40,80,200,.35)"></div>',
                "left:0;top:-90px;width:1500px;height:2100px", "wipe", t, t + .62, dur=600, out="cut", outd=1, layer="wipe", kf=[{"transform": "translateX(1120px)"}, {"transform": "translateX(-1650px)"}])
        else:
            add('<div style="width:560px;height:2600px;background:linear-gradient(90deg,rgba(255,255,255,0),rgba(255,255,255,.96) 50%,rgba(255,255,255,0));filter:blur(6px)"></div>',
                "left:0;top:-340px;width:560px;height:2600px", "wipe", t, t + .62, dur=600, out="cut", outd=1, layer="wipe", kf=[{"transform": "translateX(-700px) rotate(16deg)"}, {"transform": "translateX(1300px) rotate(16deg)"}])
        EV.append((t - 0.05, "whoosh", 0.6, 0.55))
    for x in wins:
        if x["lay"] == "S": EV.append((x["a"] - 0.05, "whoosh", 0.45, 0.35))
    for i in plan.get("punch", []):
        if i in WO: EV.append((t_of(i) - 0.12, "camera", 0.3, 0.45))
    return L, EV
