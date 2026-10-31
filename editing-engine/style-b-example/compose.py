import json, re, subprocess, sys, os, glob
from sched import *

OUT = sys.argv[1]
PREVIEW = len(sys.argv) > 2 and sys.argv[2] == "preview"       # preview = no audio mix, faster
faces = json.load(open(f"{V5}/faces.json"))
def run(c): subprocess.run(["ffmpeg", "-v", "error", "-y"] + c, check=True)
def at(t): t = max(0, t); return f"{int(t // 3600)}:{int(t % 3600 // 60):02d}:{t % 60:05.2f}"
cw = lambda s: re.sub(r"[^a-z0-9]", "", s.lower())
PINK_ASS, WHITE_ASS = "&H00935AFF&", "&H00FFFFFF&"
T0m = starts[11] - 0.4                      # start of the depth-text matte window
S_WINS = [x for x in wins if x["lay"] == "S"]

# ---------------------------------------------------------------- face tracking for the 2-up crop
def win_face(x):
    ys, hs = [], []
    for u in subs:
        d = u["e"] - u["s"]; oa = max(x["a"], u["o"]); ob = min(x["b"], u["o"] + d)
        if oa >= ob: continue
        s0 = u["s"] + (oa - u["o"]); s1 = u["s"] + (ob - u["o"]); z = u["z"]; fy = u["f"][1]
        for f in faces:
            if s0 <= f[0] <= s1:
                ys.append(((f[2] + 0.5 * f[4]) - (854 - 854 / z) * fy) * (1920 / (854 / z))); hs.append(f[4] * z * 2.25)
    if not ys: return 760, 520
    return float(np.median(ys)), float(np.median(hs))
import numpy as np
fs_ = json.load(open(f"{V}/face_s.json"))
for wi, x in enumerate(wins):
    if x["lay"] != "S": continue
    yc, hh, cxx = fs_[str(wi)]
    y0 = int(min(max(yc - 0.40 * 886, 0), 1920 - 886)); x["y0"] = y0; x["x0"] = int(min(max(cxx - 480, 0), 120))
    x["capy"] = int(min(944 + (yc + 0.5 * hh - y0) * 1.075 + 95, 1850))
    print("S window", x["name"], round(x["a"], 1), "face yc", int(yc), "h", int(hh), "crop y0", y0, "caption y", x["capy"], flush=True)

# ---------------------------------------------------------------- captions (ASS)
def chin_raw(t):
    near = [f for f in faces if abs(f[0] - t) <= 0.35] or [min(faces, key=lambda f: abs(f[0] - t))]
    return max(f[2] + 1.12 * f[4] for f in near)
def sub_at_src(t): return next((u for u in subs if u["s"] - 0.05 <= t <= u["e"] + 0.05), subs[0])
def in_win(t, kind):
    return next((x for x in wins if x["lay"] == kind and x["a"] <= t < x["b"]), None)
BLOCK_IDX = set(range(65, 74)) | set(range(74, 80)) | set(range(169, 174)) | set(range(176, 182))
SERIF = {"fabulous", "strong", "able", "gifts", "kids", "memorable", "excellent", "inwardly", "constantly", "humans", "comfort", "old", "bond"}
KEY = {"home", "gym", "busy", "body", "walk", "bedridden", "care", "yourself", "30", "40", "best", "schedule", "work", "loved"}
ev = []
def E(a, b, txt, layer=5):
    if b > a: ev.append(f"Dialogue: {layer},{at(a)},{at(b)},D,,0,0,0,,{txt}")
chunks, cur = [], []
for x in words:
    if x["i"] in BLOCK_IDX:
        if cur: chunks.append(cur); cur = []
        continue
    if cur and x["o"] - cur[-1]["oe"] > 0.25: chunks.append(cur); cur = []
    cur.append(x)
    t2 = " ".join(re.sub(r"[.,;:!?]+$", "", y["w"]) for y in cur)
    if len(cur) >= 3 or len(t2) >= 15 or x["w"][-1] in ".,?!": chunks.append(cur); cur = []
if cur: chunks.append(cur)
BASE = "\\fnPoppins\\b1\\fs82\\fsp-1"
OUTL = "\\bord6\\3c&H141414&\\shad3\\4c&H000000&\\4a&H78&\\blur1"
for ci, ch in enumerate(chunks):
    a = ch[0]["o"]; nxt = chunks[ci + 1][0]["o"] if ci + 1 < len(chunks) else T
    b = min(nxt, ch[-1]["oe"] + 0.35)
    if a < HOOK_END: y = 1495
    elif in_win(a + 0.05, "S"): y = in_win(a + 0.05, "S")["capy"]
    elif in_win(a + 0.05, "F"): y = 1560
    else:
        src_ts = [w[x["i"]]["s"] for x in ch]
        def co(st):
            u = sub_at_src(st); z = u["z"]; fy = u["f"][1]
            return (chin_raw(st) - (854 - 854 / z) * fy) * (1920 / (854 / z))
        y = int(min(max(max(co(s) for s in src_ts) + 85, 1230), 1800))
    style = {}
    for x in ch:
        c = cw(x["w"])
        if c in SERIF and not style: style[x["i"]] = "serif"
        elif c in KEY and not style: style[x["i"]] = "key"
    for k, x in enumerate(ch):
        ka = x["o"]; kb = ch[k + 1]["o"] if k + 1 < len(ch) else b
        if kb <= ka: continue
        parts = []
        for j, yv in enumerate(ch):
            s = re.sub(r"[.,;:!?]+$", "", yv["w"])
            st = style.get(yv["i"])
            if st == "serif": parts.append(f"{{\\fnInstrument Serif\\b0\\i1\\fs114\\fsp0\\c{PINK_ASS}}}{s}{{{BASE}\\i0\\c{WHITE_ASS}}}")
            elif j == k or st == "key": parts.append(f"{{\\c{PINK_ASS}}}{s}{{\\c{WHITE_ASS}}}")
            else: parts.append(s)
        pop = "\\fscx86\\fscy86\\t(0,90,\\fscx104\\fscy104)\\t(90,150,\\fscx100\\fscy100)" if k == 0 else ""
        E(ka, kb, f"{{\\an5\\pos({W // 2},{y}){BASE}{OUTL}\\c{WHITE_ASS}{pop}}}" + " ".join(parts), 10)
# progress bar + closing fade
E(0, T, f"{{\\an7\\pos(0,0)\\p1\\bord0\\shad0\\c{PINK_ASS}\\fscx0\\t(0,{int(T * 1000)},\\fscx100)}}m 0 0 l 1080 0 1080 10 0 10{{\\p0}}", 30)
E(T - 0.5, T, "{\\an7\\pos(0,0)\\p1\\bord0\\shad0\\c&H000000&\\alpha&HFF&\\t(0,500,\\alpha&H00&)}m 0 0 l 1080 0 1080 1920 0 1920{\\p0}", 31)
head = ["[Script Info]", "ScriptType: v4.00+", f"PlayResX: {W}", f"PlayResY: {H}", "WrapStyle: 2", "ScaledBorderAndShadow: yes", "",
        "[V4+ Styles]", "Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding",
        "Style: D,Poppins,60,&H00FFFFFF,&H00FFFFFF,&H00000000,&H00000000,-1,0,0,0,100,100,0,0,1,0,0,5,0,0,0,1", "",
        "[Events]", "Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text"]
open(f"{V}/captions.ass", "w").write("\n".join(head + ev) + "\n")

# ---------------------------------------------------------------- sound effects
sfx = []
S = lambda t, f, v: sfx.append((max(t, 0), f, v))
S(0.05, "boom.wav", 0.18); S(0.28, "pop.wav", 0.28); S(0.55, "whoosh.wav", 0.15)
for i in (200, 207): S(t_of(i), "pop.wav", 0.3); S(t_of(i) + 0.3, "ding.wav", 0.16)
F_entries = [x for x in wins if x["lay"] == "F"]
for x in F_entries: S(x["a"] - 0.2, "whoosh.wav", 0.3)
for x in S_WINS: S(x["a"], "whoosh.wav", 0.2); S(x["a"] + 0.25, "pop.wav", 0.14)
busy_end = [x for x in wins if x["name"] == "busy"][0]["b"]
c0 = max(t_of(24) - 0.05, busy_end + 0.06)
S(c0, "whoosh.wav", 0.16)
for k in range(5): S(c0 + 0.22 + k * 0.13, "pop.wav", 0.12)
S(t_of(32) - 0.12, "boom.wav", 0.22); S(t_of(32) - 0.12, "pop.wav", 0.25)
S(t_of(37), "pop.wav", 0.22); S(t_of(37) + .2, "ding.wav", 0.15)
for i in (65, 69): S(t_of(i), "whoosh.wav", 0.16)
S(t_of(66), "pop.wav", 0.26); S(t_of(72) - 0.05, "pop.wav", 0.26); S(t_of(73), "boom.wav", 0.24)
S(t_of(93), "pop.wav", 0.25)
S(t_of(125) - 0.1, "boom.wav", 0.22); S(t_of(125) - 0.1, "pop.wav", 0.25)
bd = [x for x in wins if x["name"] == "bed"][0]
k0 = bd["b"] + 0.06
S(k0, "whoosh.wav", 0.16); S(k0 + 0.2, "ding.wav", 0.15); S(t_of(130) - 0.1, "ding.wav", 0.15); S(t_of(140) - 0.2, "ding.wav", 0.15)
S(t_of(152), "pop.wav", 0.26); S(t_of(155), "ding.wav", 0.26)
S(t_of(169), "whoosh.wav", 0.16); S(t_of(172) - 0.05, "boom.wav", 0.22)
S(t_of(176), "whoosh.wav", 0.14); S(t_of(177), "boom.wav", 0.22); S(t_of(179), "pop.wav", 0.2)
S(t_of(74) - 0.05, "boom.wav", 0.3); S(t_of(75), "whoosh.wav", 0.2); S(t_of(77), "pop.wav", 0.24)
for r in (2, 3, 6, 10): S(starts[r] - 0.05, "whoosh.wav", 0.12)

# ---------------------------------------------------------------- pre-render the split-screens and the ending as opaque clips
os.makedirs(f"{V}/pre", exist_ok=True)
kb = sorted(glob.glob(f"{V}/kb/*.mp4")); assert len(kb) == len(wins)
pre = {}
for i, x in enumerate(wins):
    if x["lay"] != "S": continue
    d = x["b"] - x["a"]; out = f"{V}/pre/s{i:02d}.mp4"; pre[i] = out
    fc = (f"[0:v]crop=960:886:{x['x0']}:{x['y0']},scale=1032:952:flags=lanczos,format=rgba[s0];[3:v]format=gray[mb];[s0][mb]alphamerge[spk];"
          "[2:v]format=gray[mt];[1:v]format=rgba,setpts=PTS-STARTPTS[t0];[t0][mt]alphamerge[top];"
          f"color=c=0xF4F4F7:s=1080x1920:r=30:d={d:.3f}[bgc];[bgc][spk]overlay=24:944[b1];"
          "[b1][top]overlay=x=24:y='24-940*pow(max(0,1-t/0.4),3)':eof_action=pass,format=yuv420p[o]")
    run(["-ss", f"{x['a']:.3f}", "-t", f"{d:.3f}", "-i", f"{V5}/base_full.mp4", "-i", kb[i],
         "-loop", "1", "-framerate", "30", "-t", f"{d:.3f}", "-i", f"{V}/mask_top.png", "-loop", "1", "-framerate", "30", "-t", f"{d:.3f}", "-i", f"{V}/mask_bot.png",
         "-filter_complex", fc, "-map", "[o]", "-t", f"{d:.3f}", "-r", "30", "-c:v", "libx264", "-preset", "veryfast", "-crf", "16", "-pix_fmt", "yuv420p", out])
endc = f"{V}/pre/end.mp4"; k0 = int(T0m * FPS)
run(["-ss", f"{T0m:.3f}", "-t", f"{T - T0m:.3f}", "-i", f"{V5}/base_full.mp4", "-framerate", "30", "-start_number", str(k0), "-i", f"{V}/seq_depth/f%05d.png",
     
     "-filter_complex", "[0:v][1:v]overlay=0:0:eof_action=pass,format=yuv420p[o]",
     "-map", "[o]", "-t", f"{T - T0m:.3f}", "-r", "30", "-c:v", "libx264", "-preset", "veryfast", "-crf", "16", "-pix_fmt", "yuv420p", endc])
print("pre-rendered", len(pre), "split clips + ending", flush=True)

# ---------------------------------------------------------------- main graph: base + opaque window clips + captions + graphics + wipes
inp = ["-i", f"{V5}/base_full.mp4", "-i", f"{V5}/base_voice.wav", "-i", f"{V5}/music.wav",
       "-framerate", "30", "-start_number", "0", "-i", f"{V}/seq_front/f%05d.png", "-framerate", "30", "-start_number", "0", "-i", f"{V}/seq_wipe/f%05d.png",
       "-i", endc]
clips = [(x, pre.get(i, kb[i])) for i, x in enumerate(wins)]
for x, f in clips: inp += ["-i", f]
r5a, r5b = starts[5], starts[6]
vf = [f"[0:v]hue=s=0:enable='between(t,{r5a:.3f},{r5b:.3f})'[bw]"]; cur = "[bw]"
for n, (x, f) in enumerate(clips):
    a, b2 = x["a"], x["b"]
    vf.append(f"[{6 + n}:v]setpts=PTS+{a:.3f}/TB[c{n}]")
    vf.append(f"{cur}[c{n}]overlay=0:0:eof_action=pass:enable='between(t,{a:.3f},{b2:.3f})'[o{n}]"); cur = f"[o{n}]"
vf.append(f"[5:v]setpts=PTS+{T0m:.3f}/TB[ec]")
vf.append(f"{cur}[ec]overlay=0:0:eof_action=pass:enable='gte(t,{T0m:.3f})'[oe]")
vf.append(f"[oe]ass={V}/captions.ass:fontsdir=/root/.fonts[c0]")
vf.append("[c0][3:v]overlay=0:0:eof_action=pass[c1]")
vf.append("[c1][4:v]overlay=0:0:eof_action=pass,format=yuv420p[vout]")

# ---------------------------------------------------------------- audio graph
af = [f"[1:a]arnndn=m={V5}/sh.rnnn,highpass=f=90,volume=15dB,afftdn=nr=6:nf=-48,equalizer=f=160:t=q:w=1:g=1.5,"
      "equalizer=f=320:t=q:w=1.2:g=-2.5,equalizer=f=3500:t=q:w=1.2:g=3,highshelf=f=9000:g=2,deesser=i=0.4,"
      "acompressor=threshold=-22dB:ratio=4:attack=6:release=100:makeup=2,alimiter=limit=0.9,asplit=2[voice][key]",
      f"[2:a]volume=0.20,aformat=sample_rates=48000:channel_layouts=stereo,afade=t=out:st={T - 1.5:.2f}:d=1.5[mus0]",
      "[mus0][key]sidechaincompress=threshold=0.02:ratio=8:attack=25:release=400[mus]"]
mix = ["[voice]", "[mus]"]
ainp = []
base_cnt = sum(1 for z in inp if z == "-i")
for k, (t, f, v) in enumerate(sfx):
    ainp += ["-i", f if f.startswith("/") else f"{SFXD}/{f}"]
    idx = base_cnt + k; ms = int(t * 1000)
    af.append(f"[{idx}:a]aformat=sample_rates=48000:channel_layouts=stereo,adelay={ms}|{ms},volume={v}[s{k}]"); mix.append(f"[s{k}]")
af.append("".join(mix) + f"amix=inputs={len(mix)}:normalize=0:duration=first,loudnorm=I=-14:TP=-1:LRA=9[aout]")
cmd = inp + ainp + ["-filter_complex", ";".join(vf + af), "-map", "[vout]", "-map", "[aout]", "-t", f"{T:.2f}",
                    "-c:v", "libx264", "-profile:v", "high", "-preset", "veryfast" if PREVIEW else "medium", "-crf", "20" if PREVIEW else "18",
                    "-c:a", "aac", "-b:a", "192k", "-ar", "48000", "-movflags", "+faststart", OUT]
run(cmd)
json.dump({"T": T, "wins": wins, "starts": starts, "sfx": len(sfx)}, open(f"{V}/timeline.json", "w"), indent=1)
print("done", OUT, round(T, 2), "sfx", len(sfx), "captions", len(ev))
