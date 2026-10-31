import asyncio, json, os, sys, time, shutil
from multiprocessing import Process
from sched import *

PINK, CORAL, INK, GREEN, RED = "#ff5a93", "#ff8a5b", "#15151f", "#22c07a", "#ff4b55"
CSS = """
html,body{margin:0;width:1080px;height:1920px;background:transparent;overflow:hidden}
.box{display:flex;align-items:center;justify-content:center}
.t{font-family:'Montserrat';font-weight:900;color:#fff;white-space:nowrap;text-align:center;line-height:1;letter-spacing:-2px;
   -webkit-text-stroke:10px #15151f;paint-order:stroke fill;filter:drop-shadow(0 8px 14px rgba(0,0,0,.35))}
.pink{color:#ff5a93}
.serif{font-family:'Instrument Serif';font-style:italic;font-weight:400;letter-spacing:-1px;-webkit-text-stroke:8px #15151f}
.pp{font-family:'Poppins';font-weight:800;letter-spacing:0;-webkit-text-stroke:7px #15151f}
.card{background:#fff;border-radius:42px;box-shadow:0 34px 70px rgba(25,20,60,.38),0 0 0 2px rgba(255,255,255,.95) inset}
.lab{font-family:'Poppins';font-weight:600;color:#15151f;white-space:nowrap}
"""
JS = """
const EASE={spring:'cubic-bezier(.2,1.5,.3,1)',out:'cubic-bezier(.16,1,.3,1)',inout:'cubic-bezier(.45,0,.55,1)',lin:'linear'};
const FROM={pop:['scale(.5)',14],slam:['scale(1.8)',18],up:['translateY(90px) scale(.95)',10],left:['translateX(-110px)',8],
  stamp:['scale(2.8) rotate(-18deg)',10],tick:['scale(0)',0],fade:['none',0],drop:['translateY(-140px)',6]};
for(const it of ITEMS){
  const el=document.createElement('div'); el.style.cssText='position:absolute;opacity:0;'+it.css; el.innerHTML=it.html; document.body.appendChild(el);
  const d=it.t0*1000;
  if(it.kind==='wipe'){ el.style.opacity=1; el.animate(it.kf,{delay:d,duration:it.dur,easing:EASE[it.ease||'inout'],fill:'both'}); continue; }
  const f=FROM[it.kind]||FROM.pop;
  el.animate([{transform:f[0]},{transform:it.tf1||'none'}],{delay:d,duration:it.dur||450,easing:EASE[it.ease||'spring'],fill:'both'});
  el.animate([{opacity:0,filter:'blur('+f[1]+'px)'},{opacity:1,filter:'blur(0px)'}],{delay:d,duration:(it.dur||450)*0.55,easing:EASE.out,fill:'both'});
  el.animate([{opacity:1,filter:'blur(0px)'},{opacity:0,filter:'blur(6px)'}],{delay:it.t1*1000,duration:150,fill:'forwards'});
}
"""

def build_items():
    L = {"front": [], "wipe": [], "depth": []}
    def add(layer, html, css, kind, t0, t1, dur=450, ease="spring", tf1=None, kf=None):
        L[layer].append({"html": html, "css": css, "kind": kind, "t0": round(t0, 3), "t1": round(t1, 3), "dur": dur, "ease": ease, "tf1": tf1, "kf": kf})
    def line(txt, cls, size, yc, t0, t1, kind="slam", color="", extra="", layer="front", dur=450):
        h = int(size * 1.5)
        add(layer, f'<div class="t {cls}" style="font-size:{size}px;{("color:" + color + ";") if color else ""}{extra}">{txt}</div>',
            f"left:0;top:{int(yc - h / 2)}px;width:1080px;height:{h}px;display:flex;align-items:center;justify-content:center", kind, t0, t1, dur)
    # ---------------- hook: title + age cards
    he = HOOK_END - 0.06
    line("NO GYM?", "", 134, 1108, 0.05, he)
    line("NO EXCUSES", "pink", 134, 1250, 0.28, he)
    line("work out from home", "serif", 84, 1362, 0.55, he, "up")
    def age(n, x, t0):
        add("front", f'<div class="card" style="width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px">'
            f'<div style="font:900 104px Montserrat;color:{INK};line-height:1;letter-spacing:-3px">{n}</div>'
            f'<div style="display:flex;align-items:center;gap:10px;font:700 33px Poppins;color:{PINK}"><span style="display:inline-grid;place-items:center;width:38px;height:38px;border-radius:50%;background:{GREEN};color:#fff;font-size:24px">✓</span>fabulous</div></div>',
            f"left:{x}px;top:318px;width:272px;height:214px", "pop", t0, he, 520)
    age("30", 44, t_of(200)); age("40", 764, t_of(207))
    # ---------------- 'busy schedule' calendar card
    wb = {x["name"] + str(i): x for i, x in enumerate(wins)}
    busy_end = [x for x in wins if x["name"] == "busy"][0]["b"]
    c0 = max(t_of(24) - 0.05, busy_end + 0.06); c1 = te_of(27) + 0.35
    add("front", f'<div class="card" style="width:880px;height:340px"></div>', "left:100px;top:70px;width:880px;height:340px", "up", c0, c1, 480)
    add("front", f'<div class="lab" style="font-size:32px;color:#8a8aa0;letter-spacing:3px">THIS WEEK</div>', "left:146px;top:100px", "up", c0 + .05, c1, 480)
    for k, dname in enumerate("MTWTF"):
        x = 146 + k * 160
        add("front", f'<div style="width:138px;height:190px;border-radius:30px;background:linear-gradient(160deg,{PINK},{CORAL});display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;color:#fff;box-shadow:0 14px 28px rgba(255,90,147,.35)">'
            f'<div style="font:800 40px Poppins">{dname}</div><div style="font:600 27px Poppins;opacity:.95">busy</div></div>',
            f"left:{x}px;top:160px;width:138px;height:190px", "pop", c0 + .22 + k * .13, c1, 420)
    # ---------------- gym ✕ stamp, yoga ✓ chip
    g = [x for x in wins if x["name"] == "gym"][0]
    add("front", f'<div style="width:100%;height:100%;border-radius:50%;background:{RED};display:grid;place-items:center;color:#fff;font:900 190px Montserrat;box-shadow:0 20px 50px rgba(0,0,0,.45),0 0 0 14px rgba(255,255,255,.9)">✕</div>',
        "left:390px;top:640px;width:300px;height:300px", "stamp", t_of(32) - 0.12, g["b"], 420, tf1="rotate(-8deg)")
    y_ = [x for x in wins if x["name"] == "yoga"][0]
    add("front", f'<div style="display:flex;align-items:center;gap:16px;height:100%;padding:0 30px;border-radius:70px;background:#fff;box-shadow:0 18px 40px rgba(0,0,0,.35);font:700 42px Poppins;color:{INK}">'
        f'<span style="display:inline-grid;place-items:center;width:58px;height:58px;border-radius:50%;background:{GREEN};color:#fff;font-size:36px">✓</span>30 min at home</div>',
        "left:70px;top:740px;width:520px;height:116px", "pop", t_of(37), y_["b"], 420)
    # ---------------- 'nothing stopping you / me' kinetic blocks
    r4e = min(te_of(68) + 0.28, starts[5] - 0.03); r5e = min(te_of(73) + 0.3, starts[6] - 0.03)
    line("THERE'S", "pp", 62, 1180, t_of(65), r4e, "up", extra="color:#fff;")
    line("NOTHING", "pink", 190, 1300, t_of(66), r4e, "slam")
    line("stopping you", "serif", 122, 1460, t_of(67), r4e, "up")
    line("& THERE'S NOTHING", "pp", 56, 1170, t_of(69), r5e, "up")
    line("STOPPING", "", 176, 1290, t_of(72) - 0.05, r5e, "slam")
    line("me.", "serif pink", 190, 1470, t_of(73), r5e, "slam", extra="-webkit-text-stroke:8px #15151f;")
    # ---------------- heart on 'loved ones'
    h0 = t_of(93)
    add("front", f'<svg viewBox="0 0 140 130" width="190" height="178"><defs><linearGradient id="hg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ff4f8b"/><stop offset="1" stop-color="#ff8a5b"/></linearGradient></defs>'
        f'<path d="M70 126 C10 82 0 52 0 34 C0 12 18 0 36 0 C52 0 63 9 70 22 C77 9 88 0 104 0 C122 0 140 12 140 34 C140 52 130 82 70 126 Z" fill="url(#hg)" stroke="#fff" stroke-width="6"/></svg>',
        "left:790px;top:660px;width:190px;height:178px;filter:drop-shadow(0 14px 22px rgba(0,0,0,.35))", "pop", h0, te_of(94) + .9, 520, tf1="rotate(8deg)")
    # ---------------- bed ✕ stamp
    bd = [x for x in wins if x["name"] == "bed"][0]
    add("front", f'<div style="width:100%;height:100%;border-radius:50%;background:{RED};display:grid;place-items:center;color:#fff;font:900 190px Montserrat;box-shadow:0 20px 50px rgba(0,0,0,.45),0 0 0 14px rgba(255,255,255,.9)">✕</div>',
        "left:390px;top:640px;width:300px;height:300px", "stamp", t_of(125) - 0.1, bd["b"], 420, tf1="rotate(-8deg)")
    # ---------------- checklist card (rows tick as she says them) + final celebration pill
    k0 = bd["b"] + 0.06; k1 = te_of(141) + 0.12
    add("front", '<div class="card" style="width:900px;height:410px"></div>', "left:90px;top:40px;width:900px;height:410px", "up", k0, k1, 480)
    add("front", '<div class="lab" style="font-size:30px;color:#8a8aa0;letter-spacing:4px">WHEN YOU GET OLD</div>', "left:140px;top:70px", "up", k0 + .05, k1, 480)
    rows = [("Not bedridden", k0 + .2), ("Not struggling to walk", t_of(130) - 0.1), ("Not struggling with a lot of things", t_of(140) - 0.2)]
    for r, (lab, tt) in enumerate(rows):
        y = 135 + r * 98
        add("front", f'<div class="lab" style="font-size:42px">{lab}</div>', f"left:236px;top:{y + 8}px", "left", k0 + .12 + r * .1, k1, 420)
        add("front", f'<div style="width:62px;height:62px;border-radius:50%;border:5px solid #d6d6e2;box-sizing:border-box"></div>', f"left:140px;top:{y + 6}px;width:62px;height:62px", "fade", k0 + .12 + r * .1, k1, 200)
        add("front", f'<div style="width:62px;height:62px;border-radius:50%;background:{GREEN};display:grid;place-items:center;color:#fff;font:900 40px Poppins">✓</div>', f"left:140px;top:{y + 6}px;width:62px;height:62px", "tick", tt, k1, 380)
    f0 = t_of(152); f1 = te_of(158) + 0.7
    add("front", f'<div style="display:flex;align-items:center;gap:22px;height:100%;padding:0 44px;border-radius:80px;background:linear-gradient(100deg,{PINK},{CORAL});box-shadow:0 26px 54px rgba(255,90,147,.5);color:#fff;font:700 44px Poppins">'
        f'<span style="display:inline-grid;place-items:center;width:70px;height:70px;border-radius:50%;background:#fff;color:{PINK};font:900 46px Poppins">✓</span>Constantly took care of yourself</div>',
        "left:70px;top:150px;width:940px;height:150px", "pop", f0, f1, 520)
    for s in range(8):  # sparkle burst when she says 'excellent'
        import math
        ang = s / 8 * 2 * math.pi; dx, dy = int(math.cos(ang) * 170), int(math.sin(ang) * 120)
        add("front", f'<div style="width:100%;height:100%;border-radius:50%;background:#fff;box-shadow:0 0 18px {PINK}"></div>',
            f"left:{540 + 10}px;top:{215}px;width:22px;height:22px", "pop", t_of(155), t_of(155) + .5, 380, "out", tf1=f"translate({dx}px,{dy}px) scale(.3)")
    # ---------------- 'leave the rest for God', 'no excuse', final block
    r9e = min(te_of(173) + 0.3, starts[10] - 0.03)
    line("leave the rest", "serif", 124, 1280, t_of(169), r9e, "up")
    line("FOR GOD", "pink", 176, 1440, t_of(172) - 0.05, r9e, "slam")
    r10e = min(te_of(181) + 0.28, starts[11] - 0.03)
    line("THERE'S", "pp", 60, 1180, t_of(176), r10e, "up")
    line("NO EXCUSE", "pink", 158, 1296, t_of(177), r10e, "slam")
    line("why you cannot", "serif", 112, 1450, t_of(179), r10e, "up")
    fe = T - 0.45
    line("your body", "serif", 150, 1280, t_of(75), fe, "up")
    line("WHILE YOU CAN", "pp", 88, 1430, t_of(77), fe, "up", extra="color:#fff;")
    # ---------------- depth layer: MOVE behind her head (matte composited above)
    add("depth", f'<div style="font:900 262px Montserrat;color:{PINK};letter-spacing:-5px;white-space:nowrap;text-align:center;width:1080px">MOVE</div>',
        "left:0;top:210px;width:1080px;height:300px", "slam", t_of(74) - 0.05, T, 520)
    # ---------------- wipes at the full-bleed entries (cut happens at the sweep's midpoint)
    k = 0
    for x in wins:
        if x["lay"] != "F": continue
        t = x["a"] - 0.28
        if k % 2 == 0:
            add("wipe", f'<div style="width:1500px;height:2100px;border-radius:140px 0 0 140px;background:linear-gradient(90deg,#fff 0%,#fff 90%,#ffe1ee 100%);box-shadow:-60px 0 110px rgba(255,90,147,.38)"></div>',
                "left:0;top:-90px;width:1500px;height:2100px", "wipe", t, t + .6, 560, "inout",
                kf=[{"transform": "translateX(1120px)"}, {"transform": "translateX(-1650px)"}])
        else:
            add("wipe", f'<div style="width:560px;height:2600px;background:linear-gradient(90deg,rgba(255,255,255,0),rgba(255,255,255,.96) 50%,rgba(255,255,255,0));filter:blur(6px)"></div>',
                "left:0;top:-340px;width:560px;height:2600px;transform-origin:50% 50%", "wipe", t, t + .6, 560, "inout",
                kf=[{"transform": "translateX(-700px) rotate(16deg)"}, {"transform": "translateX(1300px) rotate(16deg)"}])
        k += 1
    return L

def page_html(items):
    return f"<!doctype html><html><head><meta charset='utf-8'><style>{CSS}</style></head><body><script>const ITEMS={json.dumps(items)};{JS}</script></body></html>"

async def worker(layer, html_path, frames, outdir):
    from playwright.async_api import async_playwright
    async with async_playwright() as p:
        b = await p.chromium.launch(executable_path="/opt/pw-browsers/chromium", args=["--no-sandbox"])
        pg = await b.new_page(viewport={"width": 1080, "height": 1920}, device_scale_factor=1)
        await pg.goto("file://" + html_path); await pg.wait_for_timeout(500)
        for k in frames:
            await pg.evaluate("t=>document.getAnimations().forEach(a=>{a.pause();a.currentTime=t})", k * 1000 / FPS)
            await pg.screenshot(path=f"{outdir}/f{k:05d}.png", omit_background=True)
        await b.close()

def run_worker(*a): asyncio.run(worker(*a))

def render(layer, items, nproc=4):
    outdir = f"{V}/seq_{layer}"; shutil.rmtree(outdir, ignore_errors=True); os.makedirs(outdir)
    N = int(T * FPS) + 2
    html = f"{V}/layer_{layer}.html"; open(html, "w").write(page_html(items))
    act = set()
    for it in items:
        a = int((it["t0"] - 0.03) * FPS); b = int((it["t1"] + 0.25) * FPS) + 1
        if it["kind"] == "wipe": b = int((it["t0"] + it["dur"] / 1000 + 0.05) * FPS) + 1
        act.update(range(max(a, 0), min(b, N)))
    act = sorted(act)
    from PIL import Image
    Image.new("RGBA", (W, H), (0, 0, 0, 0)).save(f"{V}/blank.png")
    for k in range(N):
        if k not in set(act): os.link(f"{V}/blank.png", f"{outdir}/f{k:05d}.png")
    chunks = [act[i::nproc] for i in range(nproc)]
    t0 = time.time()
    ps = [Process(target=run_worker, args=(layer, html, c, outdir)) for c in chunks if c]
    for p in ps: p.start()
    for p in ps: p.join()
    print(layer, "active frames", len(act), "of", N, "in", round(time.time() - t0), "s", flush=True)

if __name__ == "__main__":
    L = build_items()
    json.dump(L, open(f"{V}/items.json", "w"))
    which = sys.argv[1:] or ["wipe", "depth", "front"]
    for lay in which: render(lay, L[lay])
