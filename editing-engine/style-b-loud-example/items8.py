import json, re, math
import numpy as np
from sched8 import *
BLUE, BLUE2, INK, GREEN, RED, GREY = "#3d7bff", "#9cc2ff", "#0e1630", "#22c07a", "#ff4b55", "#8a90a6"
ICD = f"{V6}/lucide_pkg/package/icons"; BRD = f"{V}/broll"
F = np.array(json.load(open(f"{V}/faces8.json")), float)
def chin(a, b):
    m = (F[:, 0] >= a - .3) & (F[:, 0] <= b + .3); f = F[m] if m.any() else F[[np.argmin(abs(F[:, 0] - a))]]
    return float(np.percentile(f[:, 2] + 1.1 * f[:, 4], 90))
_SY = {}
def shot_chin(t):
    a, b, fr = next((sh for sh in SHOTS if sh[0] <= t < sh[1]), SHOTS[-1])
    if a not in _SY: _SY[a] = chin(a, b)
    return _SY[a]
# split-screen geometry (shared with compose8)
for x in wins:
    if x["lay"] != "S": continue
    f = F[(F[:, 0] >= x["a"] - .2) & (F[:, 0] <= x["b"] + .2)]
    fx, fy, fw, fh = np.median(f[:, 1:], 0); yc, cx = fy + fh / 2, fx + fw / 2
    x["y0"] = int(min(max(yc - 0.42 * 886, 0), 1920 - 886)); x["x0"] = int(min(max(cx - 480, 0), 120))
    x["capy"] = int(min(944 + (fy + 1.1 * fh - x["y0"]) * 1.075 + 70, 1840))
def win_at(t): return next((x for x in wins if x["a"] <= t < x["b"]), None)
def icon(name, size, color="#fff", sw=2.2, fill="none"):
    s = re.sub(r"<!--.*?-->", "", open(f"{ICD}/{name}.svg").read(), flags=re.S)
    return (s.replace('width="24"', f'width="{size}"').replace('height="24"', f'height="{size}"').replace('stroke="currentColor"', f'stroke="{color}"')
             .replace('stroke-width="2"', f'stroke-width="{sw}"').replace('fill="none"', f'fill="{fill}"', 1).strip())
def dot(name, d, bg, col="#fff", sw=2.6):
    return f'<div style="width:{d}px;height:{d}px;border-radius:50%;background:{bg};display:grid;place-items:center;flex:none">{icon(name, int(d*.56), col, sw)}</div>'
from PIL import ImageFont
_FM = {}
def fit(text, size, font="/root/.fonts/Montserrat-Black.ttf", maxw=960, ls=-3, slant=0.22):
    t = re.sub(r"<[^>]+>", "", text).replace("&amp;", "&")
    longest = max(t.split("\n"), key=len) if "\n" in t else t
    while size > 40:
        f = _FM.setdefault((font, size), ImageFont.truetype(font, size))
        w_ = f.getlength(longest) + ls * len(longest) + slant * size + 40
        if w_ <= maxw: return size
        size -= 4
    return size
clean = lambda s: re.sub(r"[.,;:!?]+$", "", s)

def build_items():
    L = {"front": [], "wipe": []}; BUSY = []
    def add(html, css, kind, t0, t1, dur=450, ease="spring", tf1=None, kf=None, out=150, layer="front"):
        L[layer].append({"html": html, "css": css, "kind": kind, "t0": round(t0, 3), "t1": round(t1, 3), "dur": dur, "ease": ease, "tf1": tf1, "kf": kf, "out": out})
    def row(html, yc, t0, t1, kind="slam", dur=420, h=None, out=140):
        h = h or 260
        add(html, f"left:0;top:{int(yc - h/2)}px;width:1080px;height:{h}px;display:flex;align-items:center;justify-content:center", kind, t0, t1, dur, "spring", None, None, out)
    def block(lead, big, size, t0_lead, t0_big, t1, small=None, extra=""):
        c = shot_chin(t0_big); y = c + 95
        size = fit(big.replace("<br>", "\n"), size, maxw=980 - (190 if extra else 0))
        lsz = fit(lead, 52, "/root/.fonts/Poppins-SemiBold.ttf", 1000, 0, 0) if lead else 52
        BUSY.append((t0_lead - 0.05, t1))
        if lead: row(f'<div class="lead" style="font-size:{lsz}px">{lead}</div>', y, t0_lead, t1, "up", 360, 90); y += 60
        y += size * 0.62 * (2.0 if "<br>" in big else 1)
        row(f'<div class="kbw"><div class="kb" style="font-size:{size}px">{big}</div></div>{extra}', y, t0_big, t1, "slam", 420, int(size * (2.5 if "<br>" in big else 1.4)))
        if small: row(f'<div class="lead" style="font-size:44px;letter-spacing:4px">{small}</div>', y + size * 0.62 + 30, t0_big + 0.12, t1, "up", 360, 80)
    def count(vals, size, yc, t0, t1, step=0.05, lead=None, small=None):
        BUSY.append((t0 - 0.35, t1))
        if lead: row(f'<div class="lead">{lead}</div>', yc - size * 0.62 - 50, t0 - 0.3, t1, "up", 360, 90)
        for k, v in enumerate(vals):
            a = t0 + k * step; b = t0 + (k + 1) * step if k + 1 < len(vals) else t1
            kind = "pop" if k == 0 else "fade"
            row(f'<div class="kbw"><div class="kb" style="font-size:{size}px">{v}</div></div>', yc, a, b, kind, 300 if k == 0 else 1, int(size * 1.4), 0 if k + 1 < len(vals) else 140)
        if small: row(f'<div class="lead" style="font-size:44px;letter-spacing:4px">{small}</div>', yc + size * 0.62 + 30, t0 + 0.2, t1, "up", 360, 80)
    def glass(inner, left, top, w, h, t0, t1, kind="pop", radius=40):
        add(f'<div class="glass" style="width:{w}px;height:{h}px;border-radius:{radius}px">{inner}</div>', f"left:{left}px;top:{top}px;width:{w}px;height:{h}px", kind, t0, t1, 460, "spring", None, None, 150)
    def full(inner, bg, t0, t1):
        BUSY.append((t0, t1))
        add(f'<div style="width:1080px;height:1920px;background:{bg};position:relative;overflow:hidden">{inner}</div>', "left:0;top:0;width:1080px;height:1920px", "right", t0, t1, 420, "out", None, None, 160)
    def money(n, suffix=""):   # count-up frames
        return [f"${int(n*f):,}{suffix}" if n < 1000 else f"${n*f/1000:.0f}K" for f in (0.1, 0.25, 0.42, 0.58, 0.72, 0.84, 0.93, 1.0)]
    # ================= 1. three creators
    block("you probably heard of", "JESSI JEAN", 150, t_of(0), t_of(4), te_of(5) + 0.25)
    cy = shot_chin(3.2) + 95 + 60 + 150 * 0.62
    count([f"${v}K" for v in (60, 150, 260, 380, 490, 590, 660, 700)], 190, cy, t_of(9), t_of(13) - 0.05, 0.05, "who made about")
    glass(f'<div style="display:flex;align-items:center;gap:20px;height:100%;padding:0 34px">{dot("calendar-days", 70, BLUE)}<span class="gl">in the first <b>3 weeks</b></span></div>',
          190, int(cy - 70), 700, 130, t_of(13), wins[0]["a"] - 0.04); BUSY.append((t_of(13), wins[0]["a"]))
    glass(f'<div style="display:flex;align-items:center;gap:20px;height:100%;padding:0 34px">{dot("sparkles", 70, "#d97757")}<span class="gl">all created with <b>Claude</b></span></div>',
          190, int(shot_chin(7.1) + 150), 700, 130, t_of(20), te_of(23) + 0.2); BUSY.append((t_of(20), te_of(23) + 0.2))
    block("you've probably seen", "JESSICA CARTER", 118, t_of(24), t_of(27), wins[1]["a"] - 0.05)
    cy = shot_chin(14) + 95 + 60 + 190 * 0.62
    count([f"${v}K" for v in (40, 110, 200, 290, 370, 430, 480, 500)], 190, cy, t_of(39), t_of(42) - 0.05, 0.05, "and made about", "US DOLLARS")
    # launch slider (week 2)
    s0, s1 = t_of(42), cut_before(50) - 0.05; top = int(shot_chin(17) + 120); BUSY.append((s0, s1))
    glass(f'<div style="padding:30px 38px;display:flex;flex-direction:column;gap:20px;height:100%;box-sizing:border-box"><div style="display:flex;align-items:center;gap:14px">{icon("rocket", 40, BLUE2, 2.2)}'
          f'<span class="gl">Launch revenue</span><span class="gl" style="margin-left:auto;color:{BLUE2}"><b>$500K</b></span></div>'
          f'<div style="height:22px;border-radius:11px;background:rgba(255,255,255,.18)"></div><div style="display:flex;justify-content:space-between"><span class="gs">week 1</span><span class="gs">week 2</span></div></div>',
          140, top, 800, 210, s0, s1)
    add(f'<div style="width:724px;height:22px;border-radius:11px;background:linear-gradient(90deg,{BLUE},{BLUE2});transform-origin:0 50%"></div>', f"left:178px;top:{top + 104}px;width:724px;height:22px",
        "wipe", s0 + 0.25, s1, 1400, "out", None, [{"transform": "scaleX(0)"}, {"transform": "scaleX(1)"}], 150)
    block("and then there is", "MEG", 190, t_of(50), t_of(54), te_of(54) + 0.9)
    cy = shot_chin(22) + 95 + 60 + 170 * 0.62
    count([f"${v:.1f}M" for v in (0.1, 0.3, 0.5, 0.7, 0.85, 0.95, 1.05, 1.1)], 210, cy, t_of(59), wins[2]["a"] - 0.05, 0.06, "who has made about", "WITHIN THE FIRST MONTH")
    # leaderboard interstitial
    i0, i1 = t_of(66) - 0.05, cut_before(76) - 0.02
    rows = [("JJ", "Jessi Jean", "3 weeks", "$700K"), ("JC", "Jessica Carter", "2 weeks", "$500K"), ("M", "Meg", "1 month", "$1.1M")]
    rh = "".join(f'<div class="wrow" data-d="{0.15 + k * 0.18}"><div class="mono">{a}</div><div style="flex:1"><div class="wn">{n}</div><div class="ws">{icon("clock", 24, GREY, 2.2)} first {d}</div></div><div class="wv">{v}</div></div>' for k, (a, n, d, v) in enumerate(rows))
    full(f'<div style="position:absolute;inset:0;background:radial-gradient(900px 700px at 80% 10%,rgba(156,194,255,.55),transparent 60%)"></div>'
         f'<div style="position:absolute;left:90px;right:90px;top:430px;display:flex;flex-direction:column;gap:28px">'
         f'<div class="lead" style="font-size:44px;letter-spacing:6px;opacity:.85">DIGITAL PRODUCT LAUNCHES</div><div class="h1">One tool.<br>Three big launches.</div>{rh}'
         f'<div style="align-self:flex-start;margin-top:16px;display:flex;align-items:center;gap:16px;padding:18px 30px;border-radius:60px;background:rgba(255,255,255,.14);border:2px solid rgba(255,255,255,.4)">{dot("sparkles", 58, "#d97757")}<span class="gl">all created with <b>Claude</b></span></div></div>',
         f"linear-gradient(165deg,#081640 0%,#173a9e 55%,#3d7bff 100%)", i0, i1)
    # ================= 2. the question
    q0 = t_of(77)
    block("what do they know about Claude", "THAT YOU DON'T?", 116, q0, t_of(86), cut_before(89) - 0.03)
    block("the truth is, they're", "NOT SMARTER", 150, t_of(90), t_of(95), te_of(99) + 0.1, "THAN YOU")
    block("they just have access to", "THE RIGHT SKILL", 124, t_of(100), t_of(105), te_of(108) + 0.15,
          extra="")
    # task chips
    c0, c1 = t_of(110), cut_before(115) - 0.04; top = int(shot_chin(37.5) + 120); BUSY.append((c0, c1))
    for k, (ic, lab) in enumerate([("pen-line", "captions"), ("file-text", "scripts"), ("globe", "websites")]):
        glass(f'<div style="display:flex;align-items:center;gap:14px;height:100%;padding:0 26px">{dot(ic, 56, BLUE)}<span class="gl">{lab}</span></div>',
              60 + k * 330, top + (k % 2) * 40, 300, 104, c0 + k * 0.22, c1, radius=52)
    # 3-skills interstitial
    i0, i1 = t_of(122) - 0.05, t_of(128) - 0.03
    sk = [("pen-line", "Caption Writer"), ("file-text", "Viral Script Engine"), ("globe", "Website Build Architect")]
    rh = "".join(f'<div class="wrow light" data-d="{0.2 + k * 0.2}"><div class="num">{k+1}</div>{dot(ic, 72, BLUE)}<div class="wn" style="color:{INK}">{n}</div></div>' for k, (ic, n) in enumerate(sk))
    full(f'<div style="position:absolute;left:90px;right:90px;top:520px;display:flex;flex-direction:column;gap:30px"><div class="lead" style="color:{BLUE};font-size:44px;letter-spacing:6px;text-shadow:none">TOP CLAUDE SKILLS</div>'
         f'<div class="h1" style="color:{INK};text-shadow:none">3 skills to install<br>right now</div>{rh}</div>', "linear-gradient(170deg,#f6f8ff 0%,#e4ebff 100%)", i0, i1)
    # install button
    b0, b1 = t_of(134), cut_before(149) - 0.04; top = int(shot_chin(44) + 130); BUSY.append((b0 - 0.1, b1))
    glass(f'<div style="display:flex;align-items:center;gap:18px;height:100%;padding:0 34px">{dot("download", 64, BLUE)}<span class="gl">Installing skills…</span></div>', 240, top, 600, 120, b0, b1, radius=60)
    add(f'<div style="width:520px;height:10px;border-radius:5px;background:linear-gradient(90deg,{BLUE},{BLUE2});transform-origin:0 50%"></div>', f"left:280px;top:{top + 132}px;width:520px;height:10px",
        "wipe", b0 + 0.15, b1, 800, "out", None, [{"transform": "scaleX(0)"}, {"transform": "scaleX(1)"}], 150)
    # ================= 3. skill 1
    block("#1 · the first one is the", "CAPTION WRITER", 124, t_of(149), t_of(155), wins[3]["a"] - 0.05)
    k0, k1 = t_of(173), t_of(181) - 0.06; BUSY.append((k0, k1 + 0.9))
    tags = [("#keywords", 90, 0), ("SEO", 640, 1), ("#reels", 120, 2), ("#contentcreator", 520, 3), ("#trending", 260, 4)]
    base = int(shot_chin(51) + 110)
    for t, x, k in tags:
        add(f'<div class="glass" style="padding:20px 34px;border-radius:50px"><span class="gl" style="color:#fff">{t}</span></div>', f"left:{x}px;top:{base + (k % 3) * 120}px", "pop", k0 + k * 0.28, k1, 420, "spring", None, None, 140)
    block("", f'RANK {icon("trending-up", 120, "#9cc2ff", 3)}', 170, t_of(181), t_of(181), wins[4]["a"] - 0.05)
    # skill installed toggle + 'it writes for you'
    g0, g1 = t_of(196) - 0.05, t_of(199) + 0.0; top = int(shot_chin(58) + 120); BUSY.append((g0, g1))
    glass(f'<div style="display:flex;align-items:center;gap:20px;height:100%;padding:0 30px">{dot("pen-line", 64, BLUE)}<div style="flex:1"><div class="gl">Caption Writer</div><div class="gs">Claude skill</div></div>'
          f'<div style="width:116px;height:66px;border-radius:33px;background:rgba(255,255,255,.25);position:relative"></div></div>', 180, top, 720, 140, g0, g1)
    add(f'<div style="width:116px;height:66px;border-radius:33px;background:{GREEN}"></div>', f"left:754px;top:{top + 37}px;width:116px;height:66px", "fade", g0 + 0.45, g1, 200, "out", None, None, 150)
    add('<div style="width:56px;height:56px;border-radius:50%;background:#fff;box-shadow:0 3px 8px rgba(0,0,0,.3)"></div>', f"left:759px;top:{top + 42}px;width:56px;height:56px",
        "wipe", g0 + 0.4, g1, 300, "spring", None, [{"transform": "translateX(0)"}, {"transform": "translateX(50px)"}], 150)
    block("and then", "IT WRITES FOR YOU", 116, t_of(199), t_of(202), cut_before(205) - 0.04)
    # ================= 4. skill 2
    block("#2 · second one is the", "VIRAL SCRIPT ENGINE", 104, t_of(205), t_of(209), t_of(214) - 0.08)
    i0, i1 = t_of(214) - 0.05, te_of(224) + 0.25
    lines = [("HOOK", 0.9), ("PROBLEM", 1.3), ("SOLUTION", 1.7), ("CTA", 2.1)]
    lh = "".join(f'<div class="sline" data-d="{d}"><span class="tag">{t}</span><span class="bar" style="width:{300 + (k*53)%180}px"></span></div>' for k, (t, d) in enumerate(lines))
    full(f'<div style="position:absolute;left:90px;top:360px" class="lead" ><span style="color:{BLUE};font-size:44px;letter-spacing:6px;text-shadow:none">VIRAL SCRIPT ENGINE</span></div>'
         f'<div class="bub" style="left:90px;top:470px">{dot("lightbulb", 84, "#ffb020")}<div><div class="wn" style="color:{INK}">your idea</div><div class="ws">"skincare myths"</div></div></div>'
         f'<div style="position:absolute;left:500px;top:720px" class="arr">{icon("arrow-down", 110, BLUE, 2.4)}</div>'
         f'<div class="doc" style="left:90px;top:880px"><div style="display:flex;align-items:center;gap:16px">{icon("file-text", 54, BLUE, 2.2)}<span class="wn" style="color:{INK}">Full talking script</span></div>{lh}'
         f'<div class="ready" data-d="2.5">{icon("video", 34, "#fff", 2.4)} ready to shoot</div></div>', "linear-gradient(170deg,#f3f0ff 0%,#e2e6ff 100%)", i0, i1)
    # ================= 5. skill 3
    block("#3 · the third one is the", "WEBSITE BUILD<br>ARCHITECT", 112, t_of(236), t_of(240), t_of(245))
    i0, i1 = t_of(246) - 0.05, te_of(254) + 0.4; sw = t_of(251)
    wire = (f'<div class="browser" style="left:90px;top:600px"><div class="bbar"><i></i><i></i><i></i><span>yourbrand.com</span></div>'
            f'<div style="padding:40px;display:flex;flex-direction:column;gap:26px"><div class="wf" style="height:240px"></div><div class="wf" style="height:46px;width:70%"></div><div class="wf" style="height:46px;width:50%"></div>'
            f'<div style="display:flex;gap:24px"><div class="wf" style="height:180px;flex:1"></div><div class="wf" style="height:180px;flex:1"></div><div class="wf" style="height:180px;flex:1"></div></div></div></div>')
    full(f'<div style="position:absolute;left:90px;top:440px" class="lead"><span style="font-size:44px;letter-spacing:6px">DESIGN IDEA → FULL WEBSITE</span></div>{wire}',
         "linear-gradient(165deg,#081640 0%,#122e7a 100%)", i0, i1)
    site = (f'<div class="browser" style="left:90px;top:600px"><div class="bbar"><i></i><i></i><i></i><span>yourbrand.com</span></div>'
            f'<div style="height:330px;background:url(file://{BRD}/website.webp) center/cover;position:relative"><div style="position:absolute;inset:0;background:linear-gradient(90deg,rgba(8,22,64,.85),rgba(8,22,64,.2))"></div>'
            f'<div style="position:absolute;left:44px;top:70px;color:#fff;font:800 58px Poppins;line-height:1.05">Glow Studio<div style="font:500 28px Poppins;opacity:.9;margin-top:12px">Skin that speaks for you</div>'
            f'<div style="margin-top:22px;display:inline-block;padding:14px 30px;border-radius:40px;background:{BLUE};font:600 26px Poppins">Book now</div></div></div>'
            f'<div style="padding:34px 40px;display:flex;gap:24px">' + "".join(f'<div style="flex:1;height:200px;border-radius:22px;background:{c};display:flex;align-items:flex-end;padding:18px;box-sizing:border-box;color:#fff;font:600 24px Poppins">{t}</div>' for c, t in (("#3d7bff", "Facials"), ("#ff8a5b", "Peels"), ("#22c07a", "Glow kits"))) + '</div></div>')
    add(site, "left:0;top:0;width:1080px;height:1920px", "up", sw, i1, 520, "spring", None, None, 160)
    block("it's actually", "UNIQUE", 170, t_of(256), t_of(258), wins[6]["a"] - 0.05)
    block("that does not look like", f'SLOP', 190, t_of(268), t_of(274), cut_before(275) - 0.04,
          extra=f'<div style="margin-left:30px">{dot("x", 150, RED, "#fff", 3.2)}</div>')
    # ================= 6. CTA
    block("just comment", '"CLAUDE"', 170, t_of(275), t_of(277), t_of(280))
    c0 = t_of(280); top = int(shot_chin(85) + 150); BUSY.append((c0, T))
    glass(f'<div style="display:flex;align-items:center;gap:20px;height:100%;padding:0 30px"><div style="width:74px;height:74px;border-radius:50%;background:linear-gradient(135deg,#ff8a5b,#d62976,#4f5bd5);flex:none"></div>'
          f'<div style="flex:1"></div>{dot("send", 70, BLUE)}</div>', 90, top, 900, 130, c0, T + 1, radius=66)
    add('<div style="font:500 36px Poppins;color:rgba(255,255,255,.6)">Add a comment…</div>', f"left:214px;top:{top + 42}px", "fade", c0 + 0.1, c0 + 0.34, 120, "out", None, None, 0)
    word = "CLAUDE"
    for k in range(1, len(word) + 1):
        a = c0 + 0.25 + k * 0.1; b = c0 + 0.25 + (k + 1) * 0.1 if k < len(word) else T + 1
        add(f'<div style="font:700 40px Poppins;color:#fff">{word[:k]}<span style="font-weight:300">|</span></div>', f"left:214px;top:{top + 40}px", "fade", a, b, 1, "lin", None, None, 0)
    row(f'<div class="lead" style="font-size:50px">I\'ll send you these <span style="color:{BLUE2}">3 skills free</span></div>', top + 230, t_of(283), T + 1, "up", 380, 90)
    # ================= captions (small, chest) outside blocks / cards
    def busy(a, b): return any(a < y and b > x for x, y in BUSY)
    seq = [x for x in words if clean(x["w"])]
    chunks, cur = [], []
    for x in seq:
        if cur and (x["o"] - cur[-1]["oe"] > 0.25 or len(cur) >= 3 or len(" ".join(clean(y["w"]) for y in cur + [x])) > 16): chunks.append(cur); cur = []
        cur.append(x)
        if x["w"][-1] in ".,?!": chunks.append(cur); cur = []
    if cur: chunks.append(cur)
    KEY = {"claude", "skill", "skills", "product", "keywords", "rank", "script", "website", "idea", "free", "smarter", "launching", "caption", "video"}
    for n, ch in enumerate(chunks):
        a = ch[0]["o"] - 0.03; b = min(chunks[n+1][0]["o"] - 0.03 if n + 1 < len(chunks) else T, ch[-1]["oe"] + 0.35)
        if busy(a, b): continue
        wv = win_at(a + 0.05)
        y = wv["capy"] if wv and wv["lay"] == "S" else 1560 if wv else int(min(shot_chin(a + 0.05) + 110, 1700))
        parts = [f'<span style="color:{BLUE2};font-weight:800">{clean(x["w"]).lower()}</span>' if clean(x["w"]).lower() in KEY else clean(x["w"]).lower() for x in ch]
        row(f'<div class="cap">{" ".join(parts)}</div>', y, a, b, "pop", 200, 120, 60)
    # ================= wipes at full-screen photo entries (cut under the midpoint)
    for k, x in enumerate([x for x in wins if x["lay"] == "F"]):
        t = x["a"] - 0.28
        if k % 2 == 0:
            add('<div style="width:1500px;height:2100px;border-radius:140px 0 0 140px;background:linear-gradient(90deg,#fff 0%,#fff 90%,#dfe9ff 100%);box-shadow:-60px 0 110px rgba(61,123,255,.38)"></div>',
                "left:0;top:-90px;width:1500px;height:2100px", "wipe", t, t + .6, 560, "inout", None, [{"transform": "translateX(1120px)"}, {"transform": "translateX(-1650px)"}], 1, "wipe")
        else:
            add('<div style="width:560px;height:2600px;background:linear-gradient(90deg,rgba(255,255,255,0),rgba(255,255,255,.96) 50%,rgba(255,255,255,0));filter:blur(6px)"></div>',
                "left:0;top:-340px;width:560px;height:2600px", "wipe", t, t + .6, 560, "inout", None, [{"transform": "translateX(-700px) rotate(16deg)"}, {"transform": "translateX(1300px) rotate(16deg)"}], 1, "wipe")
    json.dump(wins, open(f"{V}/wins.json", "w"))
    return L
