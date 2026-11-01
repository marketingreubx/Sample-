"""Video-only composite for Style B (audio is built separately in audio8.py)."""
import json, subprocess, sys, os, glob
from common8 import *
OUT = sys.argv[1]; N = round(T * FPS)
wins = json.load(open(f"{V}/wins.json"))
def run(c): subprocess.run(["ffmpeg", "-v", "error", "-y"] + c, check=True)
os.makedirs(f"{V}/pre", exist_ok=True)
kb = sorted(glob.glob(f"{V}/kb/*.mp4")); assert len(kb) == len(wins)
pre = {}
for i, x in enumerate(wins):
    if x["lay"] != "S": continue
    d = x["b"] - x["a"]; out = f"{V}/pre/s{i:02d}.mp4"; pre[i] = out
    fc = (f"[0:v]crop=960:886:{x['x0']}:{x['y0']},scale=1032:952:flags=lanczos,format=rgba[s0];[3:v]format=gray[mb];[s0][mb]alphamerge[spk];"
          "[2:v]format=gray[mt];[1:v]format=rgba,setpts=PTS-STARTPTS[t0];[t0][mt]alphamerge[top];"
          f"color=c=0xEEF2FB:s=1080x1920:r=30:d={d:.3f}[bgc];[bgc][spk]overlay=24:944[b1];"
          "[b1][top]overlay=x=24:y='24-940*pow(max(0,1-t/0.4),3)':eof_action=pass,format=yuv420p[o]")
    run(["-ss", f"{x['a']:.3f}", "-t", f"{d:.3f}", "-i", f"{V}/base_full.mp4", "-i", kb[i],
         "-loop", "1", "-framerate", "30", "-t", f"{d:.3f}", "-i", f"{V}/mask_top.png", "-loop", "1", "-framerate", "30", "-t", f"{d:.3f}", "-i", f"{V}/mask_bot.png",
         "-filter_complex", fc, "-map", "[o]", "-t", f"{d:.3f}", "-r", "30", "-c:v", "libx264", "-preset", "veryfast", "-crf", "16", "-pix_fmt", "yuv420p", out])
inp = ["-i", f"{V}/base_full.mp4", "-framerate", "30", "-start_number", "0", "-i", f"{V}/seq_front/f%05d.png", "-framerate", "30", "-start_number", "0", "-i", f"{V}/seq_wipe/f%05d.png"]
clips = [(x, pre.get(i, kb[i])) for i, x in enumerate(wins)]
for x, f in clips: inp += ["-i", f]
vf = []; cur = "[0:v]"
for n, (x, f) in enumerate(clips):
    vf.append(f"[{3 + n}:v]setpts=PTS+{x['a']:.3f}/TB[c{n}]")
    vf.append(f"{cur}[c{n}]overlay=0:0:eof_action=pass:enable='between(t,{x['a']:.3f},{x['b']:.3f})'[o{n}]"); cur = f"[o{n}]"
vf.append("[1:v]format=rgba[g1];[2:v]format=rgba[g2]")
vf.append(f"{cur}[g1]overlay=0:0:eof_action=pass[c1]")
vf.append("[c1][g2]overlay=0:0:eof_action=pass,format=yuv420p[vout]")
run(inp + ["-filter_complex", ";".join(vf), "-map", "[vout]", "-frames:v", str(N), "-an", "-r", "30",
           "-c:v", "libx264", "-profile:v", "high", "-preset", "medium", "-crf", "18", OUT])
print("video done", OUT, N)
