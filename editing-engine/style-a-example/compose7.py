import subprocess, sys
from common7 import *
OUT = sys.argv[1]; N = round(TT * FPS)
nb = round(T * FPS)
subprocess.run(["ffmpeg", "-v", "error", "-y", "-f", "lavfi", "-i", f"color=c=black:s={W}x{H}:r={FPS}", "-frames:v", str(N - nb + 15), "-c:v", "libx264", "-preset", "veryfast", "-crf", "15", "-pix_fmt", "yuv420p", f"{V7}/clips/black.mp4"], check=True)
open(f"{V7}/clips/list2.txt", "w").write(f"file '{V7}/base_full.mp4'\nfile '{V7}/clips/black.mp4'\n")
subprocess.run(["ffmpeg", "-v", "error", "-y", "-f", "concat", "-safe", "0", "-i", f"{V7}/clips/list2.txt", "-c", "copy", f"{V7}/base_ext.mp4"], check=True)
subprocess.run(["ffmpeg", "-v", "error", "-y", "-i", f"{V7}/base_ext.mp4", "-framerate", "30", "-start_number", "0", "-i", f"{V7}/seq_front/f%05d.png",
    "-filter_complex", f"[1:v]format=rgba[g];[0:v][g]overlay=0:0:eof_action=pass,format=yuv420p[v]",
    "-map", "[v]", "-frames:v", str(N), "-r", "30", "-an", "-c:v", "libx264", "-profile:v", "high", "-preset", "medium", "-crf", "18", OUT], check=True)
print("video", OUT, N, "frames")
