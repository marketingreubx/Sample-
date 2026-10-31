import sys, glob, os, time
sys.path.insert(0, "/tmp/claude-0/-home-user-Sample-/1667514b-ba1c-53c3-9a16-c019b68c5923/scratchpad/vb")
from common import *
import subprocess, numpy as np, cv2
from PIL import Image
from rembg import new_session, remove
T0 = starts[11] - 0.4
n = int((T - T0) * FPS) + 1
subprocess.run(["ffmpeg", "-v", "error", "-y", "-ss", f"{T0:.3f}", "-i", f"{V5}/base_full.mp4", "-frames:v", str(n), f"{V}/matte_src/f%05d.png"], check=True)
print("T0", T0, "n", n, flush=True)
s = new_session("u2net_human_seg"); t0 = time.time()
k = np.ones((3, 3), np.uint8)
for i in range(1, n + 1):
    im = Image.open(f"{V}/matte_src/f{i:05d}.png").convert("RGB")
    m = np.array(remove(im, session=s, only_mask=True)).astype(np.uint8)
    m = cv2.morphologyEx(m, cv2.MORPH_OPEN, k)
    m = cv2.GaussianBlur(m, (5, 5), 0)
    Image.fromarray(m).save(f"{V}/matte/m{i - 1:05d}.png")
    if i % 25 == 0: print(i, n, round(time.time() - t0), "s", flush=True)
print("DONE", n)
