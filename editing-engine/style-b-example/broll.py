import subprocess, os, sys
from concurrent.futures import ThreadPoolExecutor
from PIL import Image, ImageDraw
import numpy as np
from sched import *
os.makedirs(f"{V}/kb", exist_ok=True)
# assets: rounded masks for the 2-up cards and a bottom gradient for full-bleed photos
def mask(w, h, r, name):
    m = Image.new("L", (w, h), 0); ImageDraw.Draw(m).rounded_rectangle([0, 0, w - 1, h - 1], radius=r, fill=255); m.save(f"{V}/{name}")
mask(1032, 900, 44, "mask_top.png"); mask(1032, 952, 44, "mask_bot.png")
g = np.zeros((H, W, 4), np.uint8)
yy = np.linspace(0, 1, H)[:, None]
a = np.clip((yy - 0.52) / 0.48, 0, 1) ** 1.6 * 0.78
g[..., 3] = (a * np.ones((1, W)) * 255).astype(np.uint8)
Image.fromarray(g).save(f"{V}/grad.png")
def src(name):
    for e in ("webp", "jpg"):
        p = f"{V}/broll/{name}.{e}"
        if os.path.exists(p): return p
def render(i):
    x = wins[i]; d = x["b"] - x["a"] + 0.2; n = int(d * 30) + 2
    out = f"{V}/kb/{i:02d}_{x['name']}.mp4"
    if x["lay"] == "F":
        vf = (f"scale=-2:3840:flags=lanczos,crop=2160:3840:(iw-2160)/2:0,zoompan=z='1+0.09*on/{n}':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=1:s=1080x1920:fps=30,"
              "eq=contrast=1.03:saturation=1.04,format=yuva420p[p];[p][1:v]overlay=0:0,format=yuv420p")
        cmd = ["ffmpeg", "-v", "error", "-y", "-loop", "1", "-i", src(x["name"]), "-loop", "1", "-i", f"{V}/grad.png", "-t", f"{d:.3f}",
               "-filter_complex", "[0:v]" + vf, "-r", "30", "-c:v", "libx264", "-preset", "veryfast", "-crf", "16", "-pix_fmt", "yuv420p", out]
    else:
        vf = (f"scale=2064:-2:flags=lanczos,crop=2064:1800:0:(ih-1800)*0.3,zoompan=z='1+0.08*on/{n}':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=1:s=1032x900:fps=30,"
              "eq=contrast=1.03:saturation=1.04")
        cmd = ["ffmpeg", "-v", "error", "-y", "-loop", "1", "-i", src(x["name"]), "-t", f"{d:.3f}", "-vf", vf, "-r", "30",
               "-c:v", "libx264", "-preset", "veryfast", "-crf", "16", "-pix_fmt", "yuv420p", out]
    subprocess.run(cmd, check=True); return out
with ThreadPoolExecutor(3) as ex: outs = list(ex.map(render, range(len(wins))))
print("\n".join(os.path.basename(o) for o in outs))
