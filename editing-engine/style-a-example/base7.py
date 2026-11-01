import subprocess, json, numpy as np
from common7 import *
G = "eq=brightness=0.025:contrast=1.05:saturation=1.05:gamma=1.03,colorbalance=rm=0.02:bm=-0.012,unsharp=5:5:0.45"
F = np.array(json.load(open(f"{V5}/faces.json")), float)
parts = []
for k, (a, b, fr) in enumerate(SHOTS):
    s0, d = src(a), b - a
    if fr == "Tt":
        f = F[(F[:, 0] >= s0 - .3) & (F[:, 0] <= s0 + d + .3) & (F[:, 2] < 450)]
        cx = np.median(f[:, 1] + f[:, 3] / 2); cy = np.median(f[:, 2] + f[:, 4] / 2)
        z = 1.28; cw, ch = 480 / z, 854 / z
        x0 = min(max(cx - cw / 2, 0), 480 - cw); y0 = min(max(cy - ch * 0.56, 0), 854 - ch)
        crop = f"crop={cw:.0f}:{ch:.0f}:{x0:.0f}:{y0:.0f},"
    elif fr == "W": crop = "crop=480:854:0:0,"
    else: crop = "crop=480:854:0:0,"
    out = f"{V7}/clips/b{k:02d}.mp4"; parts.append(out)
    subprocess.run(["ffmpeg", "-v", "error", "-y", "-ss", f"{s0:.3f}", "-t", f"{d + 0.2:.3f}", "-i", RAW,
                    "-vf", crop + f"scale={W}:{H}:flags=lanczos,{G},setsar=1", "-an", "-r", str(FPS), "-frames:v", str(round(b * FPS) - round(a * FPS)),
                    "-c:v", "libx264", "-preset", "veryfast", "-crf", "15", "-pix_fmt", "yuv420p", out], check=True)
    print(k, fr, round(a, 2), round(b, 2), crop, flush=True)
open(f"{V7}/clips/list.txt", "w").write("".join(f"file '{p}'\n" for p in parts))
subprocess.run(["ffmpeg", "-v", "error", "-y", "-f", "concat", "-safe", "0", "-i", f"{V7}/clips/list.txt", "-c", "copy", f"{V7}/base_full.mp4"], check=True)
print("base done", subprocess.run(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", f"{V7}/base_full.mp4"], capture_output=True, text=True).stdout)
