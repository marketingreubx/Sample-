import asyncio, json, os, sys, time, shutil
from multiprocessing import Process
from common8 import *
from items8 import build_items

PINK, CORAL, INK, GREEN, RED = "#ff5a93", "#ff8a5b", "#15151f", "#22c07a", "#ff4b55"
CSS = """
html,body{margin:0;width:1080px;height:1920px;background:transparent;overflow:hidden}
.lead{font-family:'Poppins';font-weight:600;font-size:52px;color:#fff;white-space:nowrap;text-shadow:0 3px 14px rgba(0,0,0,.6),0 1px 3px rgba(0,0,0,.6)}
.kbw{display:flex;flex-direction:column;align-items:center;gap:12px}
.kbl{display:inline-flex;align-items:center;gap:.18em;font-family:'Montserrat';font-weight:900;font-style:italic;color:#fff;letter-spacing:1px;line-height:1;white-space:nowrap;background:#2F6BFF;padding:.13em .3em .15em;border-radius:.16em;box-shadow:0 14px 34px rgba(0,0,0,.38),inset 0 -.06em 0 rgba(0,0,0,.18);text-shadow:0 2px 0 rgba(0,20,90,.35)}
.glass{background:linear-gradient(180deg,rgba(40,60,120,.82),rgba(14,22,48,.86));border:2px solid rgba(255,255,255,.32);box-shadow:0 20px 50px rgba(0,0,0,.35),inset 0 1px 0 rgba(255,255,255,.35);box-sizing:border-box}
.gl{font-family:'Poppins';font-weight:500;font-size:40px;color:#fff;white-space:nowrap}
.gl b{font-weight:800;color:#9cc2ff}
.gs{font-family:'Poppins';font-weight:500;font-size:26px;color:rgba(255,255,255,.7)}
.h1{font-family:'Poppins';font-weight:800;font-size:92px;line-height:1.02;color:#fff;letter-spacing:-2px;text-shadow:0 6px 24px rgba(0,0,0,.25)}
.wrow{display:flex;align-items:center;gap:26px;padding:26px 30px;border-radius:34px;background:rgba(255,255,255,.96);box-shadow:0 18px 40px rgba(0,0,0,.25)}
.wrow.light{background:#fff;box-shadow:0 16px 36px rgba(40,60,140,.15)}
.mono{width:86px;height:86px;border-radius:50%;background:linear-gradient(135deg,#3d7bff,#9cc2ff);display:grid;place-items:center;font:800 34px Poppins;color:#fff;flex:none}
.num{font:900 italic 64px Montserrat;color:#3d7bff;width:60px}
.wn{font:700 44px Poppins;color:#0e1630;white-space:nowrap}
.ws{font:500 28px Poppins;color:#8a90a6;display:flex;align-items:center;gap:8px}
.wv{font:900 italic 60px Montserrat;color:#3d7bff;letter-spacing:-2px}
.cap{font-family:'Poppins';font-weight:600;font-size:66px;color:#fff;white-space:nowrap;letter-spacing:-1px;text-shadow:0 3px 14px rgba(0,0,0,.6),0 1px 3px rgba(0,0,0,.7)}
.bub{position:absolute;display:flex;align-items:center;gap:24px;padding:28px 40px;border-radius:40px;background:#fff;box-shadow:0 18px 40px rgba(40,60,140,.18)}
.arr{position:absolute}
.doc{position:absolute;width:900px;padding:44px;border-radius:40px;background:#fff;box-shadow:0 24px 60px rgba(40,60,140,.22);box-sizing:border-box;display:flex;flex-direction:column;gap:30px}
.sline{display:flex;align-items:center;gap:24px}
.tag{font:800 26px Poppins;color:#3d7bff;background:#e6eeff;padding:10px 18px;border-radius:14px;width:150px;text-align:center}
.bar{height:22px;border-radius:11px;background:#dfe4f2;display:block}
.ready{align-self:flex-start;display:flex;align-items:center;gap:12px;font:600 30px Poppins;color:#fff;background:#22c07a;padding:16px 28px;border-radius:40px}
.browser{position:absolute;width:900px;border-radius:32px;background:#fff;overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,.45)}
.bbar{height:70px;background:#eef1f8;display:flex;align-items:center;gap:12px;padding:0 26px}
.bbar i{width:18px;height:18px;border-radius:50%;background:#c9cfdd;display:block}
.bbar span{margin-left:20px;font:500 24px Poppins;color:#8a90a6;background:#fff;padding:8px 24px;border-radius:20px}
.wf{border-radius:18px;background:repeating-linear-gradient(45deg,#e9edf6,#e9edf6 14px,#f3f5fa 14px,#f3f5fa 28px)}
"""
JS = """
const EASE={spring:'cubic-bezier(.2,1.5,.3,1)',out:'cubic-bezier(.16,1,.3,1)',inout:'cubic-bezier(.45,0,.55,1)',lin:'linear'};
const FROM={pop:['scale(.5)',14],slam:['scale(1.8)',18],up:['translateY(90px) scale(.95)',10],left:['translateX(-110px)',8],
  stamp:['scale(2.8) rotate(-18deg)',10],right:['translateX(1100px)',0],tick:['scale(0)',0],fade:['none',0],drop:['translateY(-140px)',6]};
for(const it of ITEMS){
  const el=document.createElement('div'); el.style.cssText='position:absolute;opacity:0;'+it.css; el.innerHTML=it.html; document.body.appendChild(el);
  const d=it.t0*1000;
  if(it.kind==='wipe'){ el.animate([{opacity:0},{opacity:1}],{delay:d,duration:1,fill:'both'}); el.animate(it.kf,{delay:d,duration:it.dur,easing:EASE[it.ease||'inout'],fill:'both'}); el.animate([{opacity:1},{opacity:0}],{delay:it.t1*1000,duration:(it.out||1),fill:'forwards'}); continue; }
  const f=FROM[it.kind]||FROM.pop;
  el.animate([{transform:f[0]},{transform:it.tf1||'none'}],{delay:d,duration:it.dur||450,easing:EASE[it.ease||'spring'],fill:'both'});
  el.animate([{opacity:0,filter:'blur('+f[1]+'px)'},{opacity:1,filter:'blur(0px)'}],{delay:d,duration:(it.dur||450)*0.55,easing:EASE.out,fill:'both'});
  el.animate([{opacity:1,filter:'blur(0px)'},{opacity:0,filter:'blur(6px)'}],{delay:it.t1*1000,duration:(it.out===undefined?150:it.out)||1,fill:'forwards'});
  el.querySelectorAll('[data-d]').forEach(c=>{const dd=d+parseFloat(c.dataset.d)*1000; c.animate([{opacity:0,transform:'translateY(40px) scale(.96)'},{opacity:1,transform:'none'}],{delay:dd,duration:420,easing:EASE.spring,fill:'both'});});
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
    which = sys.argv[1:] or ["wipe", "front"]
    for lay in which: render(lay, L[lay])
