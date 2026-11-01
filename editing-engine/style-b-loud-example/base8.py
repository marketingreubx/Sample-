import subprocess, json, numpy as np, cv2
from common8 import *
# raw face track (480x854, 5 fps)
cc = cv2.CascadeClassifier(f"{V5}/face.xml")
raw = subprocess.run(["ffmpeg", "-v", "error", "-i", RAW, "-vf", "fps=5,format=gray", "-f", "rawvideo", "-"], capture_output=True, check=True).stdout
fr = np.frombuffer(raw, np.uint8).reshape(-1, 854, 480)
RF = []
for k, g in enumerate(fr):
    d = [b for b in cc.detectMultiScale(g, 1.1, 6, minSize=(90, 90)) if b[1] < 400]
    if d: x, y, w_, h_ = max(d, key=lambda b: b[2] * b[3]); RF.append([k / 5, int(x), int(y), int(w_), int(h_)])
json.dump(RF, open(f"{V}/faces_raw.json", "w")); RF = np.array(RF, float)
print("raw faces", len(RF), "median", np.median(RF[:, 1:], 0))
G = "eq=brightness=0.03:contrast=1.05:saturation=1.06:gamma=1.04,colorbalance=rm=0.01:bm=0.01,unsharp=5:5:0.45"
parts = []
for k, (a, b, fr_) in enumerate(SHOTS):
    s = segs[seg_of(a + 0.01)]; s0 = s["s"] + (a - s["o"]); n = round(b * FPS) - round(a * FPS)
    if fr_ == "Tt":
        f = RF[(RF[:, 0] >= s0 - .3) & (RF[:, 0] <= s0 + (b - a) + .3)]
        cx = np.median(f[:, 1] + f[:, 3] / 2); cy = np.median(f[:, 2] + f[:, 4] / 2)
        z = 1.14; cw, ch = 480 / z, 854 / z
        x0 = min(max(cx - cw / 2, 0), 480 - cw); y0 = min(max(cy - ch * 0.40, 0), 854 - ch)
        crop = f"crop={cw:.0f}:{ch:.0f}:{x0:.0f}:{y0:.0f},"
    else: crop = ""
    out = f"{V}/clips/b{k:02d}.mp4"; parts.append(out)
    subprocess.run(["ffmpeg", "-v", "error", "-y", "-ss", f"{s0:.3f}", "-t", f"{b - a + 0.2:.3f}", "-i", RAW, "-vf", crop + f"scale={W}:{H}:flags=lanczos,{G},setsar=1",
                    "-an", "-r", str(FPS), "-frames:v", str(n), "-c:v", "libx264", "-preset", "veryfast", "-crf", "15", "-pix_fmt", "yuv420p", out], check=True)
open(f"{V}/clips/list.txt", "w").write("".join(f"file '{p}'\n" for p in parts))
subprocess.run(["ffmpeg", "-v", "error", "-y", "-f", "concat", "-safe", "0", "-i", f"{V}/clips/list.txt", "-c", "copy", f"{V}/base_full.mp4"], check=True)
print("frames", subprocess.run(["ffprobe", "-v", "error", "-count_frames", "-show_entries", "stream=nb_read_frames", "-of", "csv=p=0", f"{V}/base_full.mp4"], capture_output=True, text=True).stdout.strip(), "expected", round(T * FPS))
