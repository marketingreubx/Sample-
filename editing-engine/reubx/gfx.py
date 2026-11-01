"""HTML motion-graphics layer (Playwright) + compositor. Premium motion kit: mask reveals, tilt-ups, slides, slams with
blur-to-sharp, custom easing, staggered children, light sweeps on cards, grain on full-frame cards."""
import asyncio, json, os, re, shutil, subprocess, time
from multiprocessing import Process
import numpy as np
from core import W, H, FPS, HERE, CACHE, ff

ICD = f"{CACHE}/lucide/package/icons"


def icon(name, size, color="#fff", sw=2.2, fill="none"):
    s = re.sub(r"<!--.*?-->", "", open(f"{ICD}/{name}.svg").read(), flags=re.S)
    return (s.replace('width="24"', f'width="{size}"').replace('height="24"', f'height="{size}"').replace('stroke="currentColor"', f'stroke="{color}"')
             .replace('stroke-width="2"', f'stroke-width="{sw}"').replace('fill="none"', f'fill="{fill}"', 1).strip())


def dot(name, d, bg, col="#fff", sw=2.6):
    return f'<div style="width:{d}px;height:{d}px;border-radius:50%;background:{bg};display:grid;place-items:center;flex:none">{icon(name, int(d * .56), col, sw)}</div>'


GRAIN = ("url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='300' height='300'><filter id='n'><feTurbulence type='fractalNoise' "
         "baseFrequency='.9' numOctaves='2' stitchTiles='stitch'/></filter><rect width='100%' height='100%' filter='url(%23n)' opacity='.5'/></svg>\")")

CSS = """
@font-face{font-family:'Gochi';src:url('file:///root/.fonts/GochiHand-Regular.ttf')}
html,body{margin:0;width:1080px;height:1920px;background:transparent;overflow:hidden}
:root{--acc:#2F6BFF;--acc2:#9cc2ff}
.sans{font-family:'Poppins';font-weight:600;color:#fff;white-space:nowrap;letter-spacing:-.5px;text-shadow:0 3px 14px rgba(0,0,0,.55),0 1px 3px rgba(0,0,0,.65)}
.serif{font-family:'Instrument Serif';font-style:italic;font-weight:400;color:#F3E37C;white-space:nowrap;text-shadow:0 3px 14px rgba(0,0,0,.5),0 1px 3px rgba(0,0,0,.6)}
.hand{font-family:'Gochi';white-space:nowrap;letter-spacing:2px;text-shadow:0 2px 10px rgba(0,0,0,.6),0 1px 2px rgba(0,0,0,.7)}
.lead{font-family:'Poppins';font-weight:600;font-size:52px;color:#fff;white-space:nowrap;text-shadow:0 3px 14px rgba(0,0,0,.6),0 1px 3px rgba(0,0,0,.6)}
.kbw{display:flex;flex-direction:column;align-items:center;gap:12px}
.kbl{position:relative;overflow:hidden;display:inline-flex;align-items:center;gap:.18em;font-family:'Montserrat';font-weight:900;font-style:italic;color:#fff;letter-spacing:1px;line-height:1;white-space:nowrap;background:var(--acc);padding:.13em .3em .15em;border-radius:.16em;box-shadow:0 14px 34px rgba(0,0,0,.38),inset 0 -.06em 0 rgba(0,0,0,.18);text-shadow:0 2px 0 rgba(0,0,0,.25)}
.kser{font-family:'Instrument Serif';font-style:italic;color:#fff;line-height:.95;text-align:center;white-space:nowrap;text-shadow:0 4px 20px rgba(0,0,0,.55)}
.cap{font-family:'Poppins';font-weight:600;font-size:66px;color:#fff;white-space:nowrap;letter-spacing:-1px;text-shadow:0 3px 14px rgba(0,0,0,.6),0 1px 3px rgba(0,0,0,.7)}
.card{position:relative;background:#fff;border-radius:30px;box-shadow:0 26px 60px rgba(15,12,40,.30),0 2px 6px rgba(0,0,0,.08);overflow:hidden}
.glass{position:relative;overflow:hidden;background:linear-gradient(180deg,rgba(255,255,255,.20),rgba(255,255,255,.06)),rgba(12,16,32,.78);border:1.5px solid rgba(255,255,255,.28);box-shadow:0 24px 60px rgba(0,0,0,.38),inset 0 1px 0 rgba(255,255,255,.35);box-sizing:border-box}
.lab{font-family:'Poppins';font-weight:600;color:#17171c;white-space:nowrap}
.gl{font-family:'Poppins';font-weight:500;font-size:40px;color:#fff;white-space:nowrap}
.gl b{font-weight:800;color:var(--acc2)}
.gs{font-family:'Poppins';font-weight:500;font-size:26px;color:rgba(255,255,255,.7)}
.h1{font-family:'Poppins';font-weight:800;font-size:92px;line-height:1.02;letter-spacing:-2px}
.kick{font-family:'Poppins';font-weight:600;font-size:40px;letter-spacing:6px}
.row{display:flex;align-items:center;gap:26px;padding:26px 30px;border-radius:34px;background:#fff;box-shadow:0 18px 40px rgba(20,30,80,.18)}
.mono{width:86px;height:86px;border-radius:50%;background:var(--acc);display:grid;place-items:center;font:800 34px Poppins;color:#fff;flex:none}
.wn{font:700 44px Poppins;color:#0e1630;white-space:nowrap}
.ws{font:500 28px Poppins;color:#8a90a6;display:flex;align-items:center;gap:8px}
.wv{font:900 italic 58px Montserrat;color:var(--acc);letter-spacing:0}
.grain{position:absolute;inset:0;opacity:.07;mix-blend-mode:overlay;background-image:""" + GRAIN + """}
.sheen{position:absolute;top:-20%;bottom:-20%;width:38%;left:-45%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.55),transparent);pointer-events:none}
"""

JS = """
const E={quint:'cubic-bezier(.22,1,.36,1)',expo:'cubic-bezier(.16,1,.3,1)',back:'cubic-bezier(.34,1.45,.64,1)',inout:'cubic-bezier(.65,0,.35,1)',lin:'linear'};
const IN={
 rise:{kf:[{clipPath:'inset(0 0 100% 0)',transform:'translateY(28%)',filter:'blur(6px)'},{clipPath:'inset(0 0 0% 0)',transform:'none',filter:'blur(0)'}],e:'quint',d:520},
 pop:{kf:[{transform:'scale(.86)',opacity:0,filter:'blur(8px)'},{transform:'scale(1)',opacity:1,filter:'blur(0)'}],e:'back',d:420},
 slam:{kf:[{transform:'scale(1.32)',opacity:0,filter:'blur(14px)'},{transform:'scale(1)',opacity:1,filter:'blur(0)'}],e:'expo',d:360},
 tilt:{kf:[{transform:'perspective(1400px) rotateX(28deg) translateY(90px)',opacity:0,filter:'blur(6px)'},{transform:'none',opacity:1,filter:'blur(0)'}],e:'quint',d:650},
 slide:{kf:[{transform:'translateX(1150px)'},{transform:'none'}],e:'quint',d:620},
 drop:{kf:[{transform:'translateY(-120%)',opacity:0},{transform:'none',opacity:1}],e:'quint',d:560},
 fade:{kf:[{opacity:0},{opacity:1}],e:'lin',d:1},
 tick:{kf:[{transform:'scale(0)'},{transform:'scale(1)'}],e:'back',d:360},
};
const OUT={blur:[{opacity:1,filter:'blur(0)',transform:'none'},{opacity:0,filter:'blur(8px)',transform:'scale(.97)'}],
 cut:[{opacity:1},{opacity:0}], slide:[{transform:'none'},{transform:'translateX(-1150px)'}], sink:[{clipPath:'inset(0 0 0 0)'},{clipPath:'inset(0 0 100% 0)'}]};
for(const it of ITEMS){
  const el=document.createElement('div'); el.style.cssText='position:absolute;'+it.css; el.innerHTML=it.html; document.body.appendChild(el);
  const d=it.t0*1000;
  el.animate([{visibility:'hidden'},{visibility:'visible'}],{delay:d,duration:1,fill:'both'});
  if(it.kind==='wipe'){ el.animate(it.kf,{delay:d,duration:it.dur,easing:E[it.ease||'inout'],fill:'both'}); }
  else { const k=IN[it.kind]||IN.pop; el.animate(k.kf,{delay:d,duration:it.dur||k.d,easing:E[it.ease||k.e],fill:'both'}); }
  const oo=it.out||'blur', od=it.outd===undefined?180:it.outd;
  el.animate(OUT[oo]||OUT.blur,{delay:it.t1*1000,duration:Math.max(od,1),easing:E.inout,fill:'forwards'});
  el.querySelectorAll('[data-d]').forEach(c=>{const dd=d+parseFloat(c.dataset.d)*1000; c.animate([{opacity:0,transform:'translateY(36px)',filter:'blur(6px)'},{opacity:1,transform:'none',filter:'blur(0)'}],{delay:dd,duration:520,easing:E.quint,fill:'both'});});
  el.querySelectorAll('[data-w]').forEach(c=>{const dd=d+parseFloat(c.dataset.w)*1000; c.animate([{transform:'scaleX(0)'},{transform:'scaleX(1)'}],{delay:dd,duration:parseFloat(c.dataset.l||900),easing:E.quint,fill:'both'});});
  el.querySelectorAll('.sheen').forEach(c=>{c.animate([{left:'-45%'},{left:'120%'}],{delay:d+(it.dur||500)*0.6,duration:900,easing:E.inout,fill:'both'});});
}
"""


def page(items, acc="#2F6BFF", acc2="#9cc2ff"):
    css = CSS.replace("--acc:#2F6BFF;--acc2:#9cc2ff", f"--acc:{acc};--acc2:{acc2}")
    return f"<!doctype html><html><head><meta charset='utf-8'><style>{css}</style></head><body><script>const ITEMS={json.dumps(items)};{JS}</script></body></html>"


async def _worker(html, frames, outdir):
    from playwright.async_api import async_playwright
    async with async_playwright() as p:
        b = await p.chromium.launch(executable_path="/opt/pw-browsers/chromium", args=["--no-sandbox"])
        pg = await b.new_page(viewport={"width": W, "height": H}, device_scale_factor=1)
        await pg.goto("file://" + html); await pg.evaluate("document.fonts.ready"); await pg.wait_for_timeout(400)
        for k in frames:
            await pg.evaluate("t=>document.getAnimations().forEach(a=>{a.pause();a.currentTime=t})", k * 1000 / FPS)
            await pg.screenshot(path=f"{outdir}/f{k:05d}.png", omit_background=True)
        await b.close()


def _run(*a): asyncio.run(_worker(*a))


def render(items, name, total, work, acc, acc2, nproc=4):
    outdir = f"{work}/seq_{name}"; shutil.rmtree(outdir, ignore_errors=True); os.makedirs(outdir)
    N = round(total * FPS); html = f"{work}/layer_{name}.html"; open(html, "w").write(page(items, acc, acc2))
    act = set()
    for it in items:
        a = int((it["t0"] - 0.04) * FPS); b = int((it["t1"] + 0.3) * FPS) + 1
        act.update(range(max(a, 0), min(b, N)))
    act = sorted(act); aset = set(act)
    from PIL import Image
    Image.new("RGBA", (W, H), (0, 0, 0, 0)).save(f"{work}/blank.png")
    for k in range(N):
        if k not in aset: os.link(f"{work}/blank.png", f"{outdir}/f{k:05d}.png")
    t0 = time.time(); ps = [Process(target=_run, args=(html, act[i::nproc], outdir)) for i in range(nproc) if act[i::nproc]]
    for p in ps: p.start()
    for p in ps: p.join()
    print(f"  layer {name}: {len(act)} frames in {round(time.time() - t0)}s", flush=True)


# ---------------------------------------------------------------------- b-roll windows + composite
def broll_clips(work, wins, broll_dir):
    from PIL import Image, ImageDraw
    def mask(w, h, r, n):
        m = Image.new("L", (w, h), 0); ImageDraw.Draw(m).rounded_rectangle([0, 0, w - 1, h - 1], radius=r, fill=255); m.save(f"{work}/{n}")
    mask(1032, 900, 44, "mask_top.png"); mask(1032, 952, 44, "mask_bot.png")
    g = np.zeros((H, W, 4), np.uint8); yy = np.linspace(0, 1, H)[:, None]
    g[..., 3] = (np.clip((yy - 0.52) / 0.48, 0, 1) ** 1.6 * 0.78 * np.ones((1, W)) * 255).astype(np.uint8)
    Image.fromarray(g).save(f"{work}/grad.png")
    for i, x in enumerate(wins):
        src = f"{broll_dir}/{x['img']}"; d = x["b"] - x["a"] + 0.2; n = int(d * 30) + 2; out = f"{work}/kb{i:02d}.mp4"; x["clip"] = out
        desat = ",hue=s=0.15,eq=contrast=1.08" if x.get("archive") else ""
        if x["lay"] == "F":
            vf = (f"scale=-2:3840:flags=lanczos,crop=2160:3840:(iw-2160)/2:0,zoompan=z='1+0.10*on/{n}':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=1:s=1080x1920:fps=30,"
                  f"eq=contrast=1.03:saturation=1.04{desat},format=yuva420p[p];[p][1:v]overlay=0:0,format=yuv420p")
            ff(["-loop", "1", "-i", src, "-loop", "1", "-i", f"{work}/grad.png", "-t", f"{d:.3f}", "-filter_complex", "[0:v]" + vf, "-r", "30", "-c:v", "libx264", "-preset", "veryfast", "-crf", "16", "-pix_fmt", "yuv420p", out])
        else:
            vf = (f"scale=2064:-2:flags=lanczos,crop=2064:1800:0:(ih-1800)*0.3,zoompan=z='1+0.08*on/{n}':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=1:s=1032x900:fps=30,eq=contrast=1.03:saturation=1.04{desat}")
            ff(["-loop", "1", "-i", src, "-t", f"{d:.3f}", "-vf", vf, "-r", "30", "-c:v", "libx264", "-preset", "veryfast", "-crf", "16", "-pix_fmt", "yuv420p", out])


def compose(work, T, wins, layers, out, end=0.0):
    N = round((T + end) * FPS)
    base = f"{work}/base.mp4"
    if end:
        ff(["-f", "lavfi", "-i", f"color=c=black:s={W}x{H}:r={FPS}", "-frames:v", str(round(end * FPS) + 15), "-c:v", "libx264", "-preset", "veryfast", "-crf", "15", "-pix_fmt", "yuv420p", f"{work}/black.mp4"])
        open(f"{work}/ext.txt", "w").write(f"file '{base}'\nfile '{work}/black.mp4'\n")
        ff(["-f", "concat", "-safe", "0", "-i", f"{work}/ext.txt", "-c", "copy", f"{work}/base_ext.mp4"]); base = f"{work}/base_ext.mp4"
    clips = []
    for i, x in enumerate(wins):
        if x["lay"] == "S":
            d = x["b"] - x["a"]; o = f"{work}/pre{i:02d}.mp4"
            fc = (f"[0:v]crop=960:886:{x['x0']}:{x['y0']},scale=1032:952:flags=lanczos,format=rgba[s0];[3:v]format=gray[mb];[s0][mb]alphamerge[spk];"
                  "[2:v]format=gray[mt];[1:v]format=rgba,setpts=PTS-STARTPTS[t0];[t0][mt]alphamerge[top];"
                  f"color=c=0xEEF1F8:s=1080x1920:r=30:d={d:.3f}[bgc];[bgc][spk]overlay=24:944[b1];"
                  "[b1][top]overlay=x=24:y='24-940*pow(max(0,1-t/0.45),3)':eof_action=pass,format=yuv420p[o]")
            ff(["-ss", f"{x['a']:.3f}", "-t", f"{d:.3f}", "-i", f"{work}/base.mp4", "-i", x["clip"], "-loop", "1", "-framerate", "30", "-t", f"{d:.3f}", "-i", f"{work}/mask_top.png",
                "-loop", "1", "-framerate", "30", "-t", f"{d:.3f}", "-i", f"{work}/mask_bot.png", "-filter_complex", fc, "-map", "[o]", "-t", f"{d:.3f}", "-r", "30",
                "-c:v", "libx264", "-preset", "veryfast", "-crf", "16", "-pix_fmt", "yuv420p", o]); clips.append((x, o))
        else: clips.append((x, x["clip"]))
    inp = ["-i", base]
    for L in layers: inp += ["-framerate", "30", "-start_number", "0", "-i", f"{work}/seq_{L}/f%05d.png"]
    for x, f in clips: inp += ["-i", f]
    vf, cur, nl = [], "[0:v]", len(layers)
    for n, (x, f) in enumerate(clips):
        vf.append(f"[{1 + nl + n}:v]setpts=PTS+{x['a']:.3f}/TB[c{n}]")
        vf.append(f"{cur}[c{n}]overlay=0:0:eof_action=pass:enable='between(t,{x['a']:.3f},{x['b']:.3f})'[o{n}]"); cur = f"[o{n}]"
    for k in range(nl):
        vf.append(f"[{1 + k}:v]format=rgba[g{k}]"); vf.append(f"{cur}[g{k}]overlay=0:0:eof_action=pass[l{k}]"); cur = f"[l{k}]"
    vf.append(f"{cur}format=yuv420p[vout]")
    ff(inp + ["-filter_complex", ";".join(vf), "-map", "[vout]", "-frames:v", str(N), "-an", "-r", "30", "-c:v", "libx264", "-profile:v", "high", "-preset", "medium", "-crf", "18", out])
