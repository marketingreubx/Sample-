"""Detect her face on the final 1080x1920 base every 0.2 s -> faces6.json [t, x, y, w, h] (output time, output pixels)."""
import cv2, json, numpy as np, subprocess
from common7 import *
cc = cv2.CascadeClassifier(f"{V5}/face.xml")
fps_s = 5
raw = subprocess.run(["ffmpeg", "-v", "error", "-i", f"{V7}/base_full.mp4", "-vf", f"fps={fps_s},scale=540:960,format=gray", "-f", "rawvideo", "-"],
                     capture_output=True, check=True).stdout
fr = np.frombuffer(raw, np.uint8).reshape(-1, 960, 540)
out = []
for k, g in enumerate(fr):
    d = cc.detectMultiScale(g, 1.1, 6, minSize=(70, 70))
    d = [b for b in d if b[1] < 480 and 90 < b[2] < 400]          # sanity: face in upper part, not huge
    if not len(d): continue
    x, y, w_, h_ = max(d, key=lambda b: b[2] * b[3])
    out.append([round(k / fps_s, 2), int(x * 2), int(y * 2), int(w_ * 2), int(h_ * 2)])
# reject outliers vs rolling median
a = np.array(out, float); keep = []
for i, f in enumerate(a):
    nb = a[max(0, i - 5): i + 6]
    if abs(f[2] - np.median(nb[:, 2])) < 160 and abs(f[3] - np.median(nb[:, 3])) < 120: keep.append(out[i])
json.dump(keep, open(f"{V7}/faces7.json", "w"))
print("frames", len(fr), "faces", len(out), "kept", len(keep))
for s in subs:
    fs = [f for f in keep if s["o"] <= f[0] < s["o"] + 1.5]
    if fs: print(s["o"], "median face", np.median(np.array(fs)[:, 1:], 0).astype(int))
