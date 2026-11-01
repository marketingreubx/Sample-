import asyncio, json, os, sys, time, shutil
from multiprocessing import Process
from common7 import *
from items7 import build_items
V = V7
T = TT

PINK, CORAL, INK, GREEN, RED = "#ff5a93", "#ff8a5b", "#15151f", "#22c07a", "#ff4b55"
CSS = """
@font-face{font-family:'Gochi';src:url('file:///root/.fonts/GochiHand-Regular.ttf')}
.sans{font-family:'Poppins';font-weight:600;color:#fff;white-space:nowrap;letter-spacing:-1px;text-shadow:0 3px 14px rgba(0,0,0,.55),0 1px 3px rgba(0,0,0,.65)}
.serif{font-family:'Instrument Serif';font-style:italic;font-weight:400;color:#F3E37C;white-space:nowrap;text-shadow:0 3px 14px rgba(0,0,0,.5),0 1px 3px rgba(0,0,0,.6)}
.hand{font-family:'Gochi';white-space:nowrap;letter-spacing:2px;text-shadow:0 2px 10px rgba(0,0,0,.6),0 1px 2px rgba(0,0,0,.7)}
.card{background:#fff;border-radius:30px;box-shadow:0 22px 50px rgba(20,15,40,.28),0 2px 6px rgba(0,0,0,.08);overflow:hidden}
.lab{font-family:'Poppins';font-weight:600;color:#17171c;white-space:nowrap}

html,body{margin:0;width:1080px;height:1920px;background:transparent;overflow:hidden}
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
  el.animate([{opacity:1,filter:'blur(0px)'},{opacity:0,filter:'blur(6px)'}],{delay:it.t1*1000,duration:(it.out===undefined?150:it.out)||1,fill:'forwards'});
}
"""

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
    act = sorted(act); actset = set(act)
    from PIL import Image
    Image.new("RGBA", (W, H), (0, 0, 0, 0)).save(f"{V}/blank.png")
    for k in range(N):
        if k not in actset: os.link(f"{V}/blank.png", f"{outdir}/f{k:05d}.png")
    chunks = [act[i::nproc] for i in range(nproc)]
    t0 = time.time()
    ps = [Process(target=run_worker, args=(layer, html, c, outdir)) for c in chunks if c]
    for p in ps: p.start()
    for p in ps: p.join()
    print(layer, "active frames", len(act), "of", N, "in", round(time.time() - t0), "s", flush=True)

if __name__ == "__main__":
    L = build_items()
    json.dump(L, open(f"{V}/items.json", "w"))
    which = sys.argv[1:] or ["front"]
    for lay in which: render(lay, L[lay])
