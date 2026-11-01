"""Reubx editing engine: shared pipeline (transcribe -> cut -> clean voice -> camera -> faces -> graphics -> audio -> QA).
Every step caches into the job folder, so a re-render after a correction only redoes what changed."""
import json, os, re, subprocess, difflib
import numpy as np

HERE = os.path.dirname(os.path.abspath(__file__))
CACHE = f"{HERE}/cache"
W, H, FPS = 1080, 1920, 30


def sh(cmd, cap=False):
    return subprocess.run(cmd, check=True, capture_output=cap, text=True)


def ff(args, cap=False):
    return sh(["ffmpeg", "-hide_banner", "-v", "error", "-y"] + args, cap)


def probe_dur(p):
    return float(sh(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", p], True).stdout)


class Job:
    def __init__(self, video, work):
        self.video, self.dir = os.path.abspath(video), os.path.abspath(work)
        os.makedirs(self.dir, exist_ok=True)
        self.p = lambda name: f"{self.dir}/{name}"
        self.broll = f"{self.dir}/broll"

    # ------------------------------------------------------------------ 1. transcribe (cached)
    def transcribe(self):
        out = self.p("transcript.json")
        if os.path.exists(out): return json.load(open(out))
        ff(["-i", self.video, "-ac", "1", "-ar", "48000", self.p("raw48.wav")])
        words = asr_words(self.p("raw48.wav"))
        json.dump(words, open(out, "w"))
        return words

    def analyse(self):
        """Silence map + raw face track (cheap, cached)."""
        if not os.path.exists(self.p("silences.json")):
            a = load_mono(self.p("raw48.wav"), 48000)
            n = len(a) // 480; db = 20 * np.log10(np.sqrt((a[:n * 480].reshape(n, 480) ** 2).mean(1)) + 1e-9)
            np.save(self.p("env_db.npy"), db)
            thr = np.percentile(db, 10) + 10; sp = db >= thr; spx = sp.copy()
            for k in range(1, 7): spx[k:] |= sp[:-k]; spx[:-k] |= sp[k:]
            sil, i = [], 0
            while i < n:
                if not spx[i]:
                    j = i
                    while j < n and not spx[j]: j += 1
                    if j - i >= 12: sil.append([i / 100, j / 100])
                    i = j
                else: i += 1
            json.dump(sil, open(self.p("silences.json"), "w"))
        if not os.path.exists(self.p("faces_raw.json")):
            json.dump(detect_faces(self.video, raw=True), open(self.p("faces_raw.json"), "w"))

    # ------------------------------------------------------------------ 2. cut
    def cut(self, plan):
        w = json.load(open(self.p("transcript.json")))
        for i, t in plan.get("fix", {}).items(): w[int(i)]["w"] = t
        rem = set()
        for a, b in plan.get("remove", []): rem |= set(range(a, b + 1))
        keep = [i for i in range(len(w)) if i not in rem]
        sil = json.load(open(self.p("silences.json"))); env = np.load(self.p("env_db.npy"))
        PAD_IN, PAD_OUT, MAXGAP = 0.22, 0.16, 0.45
        def valley(t0, t1):
            i0, i1 = max(int(t0 * 100), 0), max(int(t1 * 100), int(t0 * 100) + 1)
            return (i0 + int(np.argmin(env[i0:i1]))) / 100
        runs, cur = [], [keep[0]]
        for i in keep[1:]:
            if i == cur[-1] + 1: cur.append(i)
            else: runs.append(cur); cur = [i]
        runs.append(cur)
        dur = probe_dur(self.video); segs = []
        for r in runs:
            i0, i1 = r[0], r[-1]
            sb = [s for s in sil if w[i0]["s"] - 0.6 <= s[1] <= w[i0]["s"] + 0.06]
            if i0 == 0: start = max(0.0, w[0]["s"] - 0.25)
            elif sb and sb[-1][1] > w[i0 - 1]["s"] + 0.15: start = max(sb[-1][1] - PAD_IN, sb[-1][0])
            else: start = valley(w[i0]["s"] - 0.28, w[i0]["s"] + 0.02)
            nxt = w[i1 + 1]["s"] if i1 + 1 < len(w) else 1e9
            sa = [s for s in sil if s[0] >= w[i1]["s"] + 0.05]
            if sa and sa[0][0] < nxt: end = min(sa[0][0] + PAD_OUT, sa[0][1], nxt - 0.04)
            elif nxt < 1e9: end = valley(nxt - 0.28, nxt + 0.02)
            else: end = min(dur, w[i1]["s"] + 1.0)
            pieces = [[start, end]]
            for s0, s1 in sil:
                if s1 - s0 < MAXGAP or s0 <= start or s1 >= end: continue
                last = pieces[-1]
                if last[0] < s0 and s1 < last[1]:
                    pieces[-1] = [last[0], s0 + PAD_OUT]; pieces.append([max(s1 - PAD_IN, s0 + PAD_OUT + 0.01), last[1]])
            segs += [{"s": round(a, 3), "e": round(b, 3)} for a, b in pieces]
        o = 0
        for sg in segs: sg["o"] = round(o, 3); o += sg["e"] - sg["s"]
        T = round(o, 3)
        def out_t(src):
            for sg in segs:
                if sg["s"] - 0.001 <= src <= sg["e"] + 0.001: return sg["o"] + src - sg["s"]
            return next((sg["o"] for sg in segs if sg["s"] > src), T)
        words = sorted(({"i": i, "w": w[i]["w"], "o": round(out_t(w[i]["s"]), 3)} for i in keep), key=lambda x: x["o"])
        for k, x in enumerate(words): x["oe"] = round(min(words[k + 1]["o"] if k + 1 < len(words) else T, x["o"] + 0.7), 3)
        cut = {"segs": segs, "T": T, "words": words, "keep": keep}
        json.dump(cut, open(self.p("cut.json"), "w"))
        return cut

    # ------------------------------------------------------------------ 3. voice
    def voice(self, cut):
        parts = []
        for k, sg in enumerate(cut["segs"]):
            d = sg["e"] - sg["s"]; o = self.p(f"vseg{k:03d}.wav"); parts.append(o)
            ff(["-ss", f"{sg['s']:.3f}", "-t", f"{d:.3f}", "-i", self.p("raw48.wav"), "-af",
                f"afade=t=in:d=0.008,afade=t=out:st={max(d - 0.012, 0):.3f}:d=0.012", "-ar", "48000", "-ac", "1", o])
        open(self.p("vlist.txt"), "w").write("".join(f"file '{x}'\n" for x in parts))
        ff(["-f", "concat", "-safe", "0", "-i", self.p("vlist.txt"), "-c:a", "pcm_s16le", self.p("voice_cut_raw.wav")])
        for x in parts: os.remove(x)
        m = sh(["ffmpeg", "-hide_banner", "-i", self.p("voice_cut_raw.wav"), "-af", "volumedetect", "-f", "null", "-"], True).stderr
        gain = round(-24 - float(m.split("mean_volume:")[1].split("dB")[0]), 1)
        chain = (f"highpass=f=80,lowpass=f=15000,volume={gain}dB,arnndn=m={HERE}/assets/sh.rnnn,afftdn=nr=10:nf=-52,"
                 "equalizer=f=300:t=q:w=1.2:g=-2.5,equalizer=f=3400:t=q:w=1.3:g=2.5,highshelf=f=10000:g=1.5,deesser=i=0.35,"
                 "acompressor=threshold=-21dB:ratio=3:attack=5:release=90:makeup=2,"
                 "agate=threshold=0.008:ratio=2.5:attack=4:release=160:range=0.12:knee=3,alimiter=limit=0.89:level=false")
        ff(["-i", self.p("voice_cut_raw.wav"), "-af", chain, "-ar", "48000", self.p("voice.wav")])

    # ------------------------------------------------------------------ 4. camera: shots + keyframed zooms
    def base(self, cut, plan, grade, anchor=0.42):
        """Shots change framing (wide / tight) at every jump cut and at plan['shots']; inside each shot the camera moves:
        a slow push-in plus eased punch-ins on plan['punch'] words (ease in 0.2 s, hold, ease out 0.35 s)."""
        segs, T = cut["segs"], cut["T"]; WO = {x["i"]: x for x in cut["words"]}
        RF = np.array(json.load(open(self.p("faces_raw.json"))), float)
        rw, rh = video_size(self.video)
        bounds = {round(sg["o"], 3) for sg in segs}
        for i in plan.get("shots", []):
            if i in WO: bounds.add(round(cut_before(cut, i), 3))
        bounds = sorted(b for b in bounds if b < T - 0.3)
        shots, tight = [], plan.get("start_tight", False)
        for k, a in enumerate(bounds):
            b = bounds[k + 1] if k + 1 < len(bounds) else T
            shots.append([a, b, "T" if tight else "W"]); tight = not tight
        punches = [(WO[i]["o"], WO[i]["oe"]) for i in plan.get("punch", []) if i in WO]
        parts = []
        for k, (a, b, fr) in enumerate(shots):
            sg = max((s for s in segs if s["o"] <= a + 1e-6), key=lambda s: s["o"]); s0 = sg["s"] + (a - sg["o"])
            n = round(b * FPS) - round(a * FPS)
            f = RF[(RF[:, 0] >= s0 - .3) & (RF[:, 0] <= s0 + (b - a) + .3)] if len(RF) else RF
            cx, cy = (np.median(f[:, 1] + f[:, 3] / 2) / rw, np.median(f[:, 2] + f[:, 4] / 2) / rh) if len(f) else (0.5, 0.42)
            z0 = 1.16 if fr == "T" else 1.0
            terms = [f"{z0}*(1+0.035*on/{max(n, 1)})"]
            for pa, pb in punches:
                if a <= pa < b:
                    f0 = (pa - a) * FPS - 3; f1 = min((pb - a) * FPS + 12, (b - a) * FPS - 1)
                    up = f"clip((on-{f0:.1f})/6,0,1)"; dn = f"clip((on-{f1:.1f})/10,0,1)"
                    terms.append(f"0.11*({up}*{up}*(3-2*{up}))*(1-{dn}*{dn}*(3-2*{dn}))")
            z = "+".join(terms)
            x = f"clip({cx:.4f}*iw-iw/zoom/2,0,iw-iw/zoom)"; y = f"clip({cy:.4f}*ih-ih/zoom*{anchor},0,ih-ih/zoom)"
            out = self.p(f"shot{k:03d}.mp4"); parts.append(out)
            ff(["-ss", f"{s0:.3f}", "-t", f"{b - a + 0.2:.3f}", "-i", self.video, "-vf",
                f"scale=2160:3840:flags=lanczos,zoompan=z='{z}':x='{x}':y='{y}':d=1:s={W}x{H}:fps={FPS},{grade},setsar=1",
                "-an", "-frames:v", str(n), "-c:v", "libx264", "-preset", "veryfast", "-crf", "15", "-pix_fmt", "yuv420p", out])
        open(self.p("shots.txt"), "w").write("".join(f"file '{x}'\n" for x in parts))
        ff(["-f", "concat", "-safe", "0", "-i", self.p("shots.txt"), "-c", "copy", self.p("base.mp4")])
        for x in parts: os.remove(x)
        json.dump(shots, open(self.p("shots.json"), "w"))
        json.dump(detect_faces(self.p("base.mp4")), open(self.p("faces.json"), "w"))
        return shots

    # ------------------------------------------------------------------ 5. audio (separate pass, never in the video graph)
    def audio(self, T, events, music_gain=0.14):
        import sfx
        sfx.render(events, T, self.p("sfx.wav"))
        af = (f"[0:a]aformat=sample_rates=48000:channel_layouts=stereo,apad=whole_dur={T:.3f},asplit=2[v][k];"
              f"[1:a]aformat=sample_rates=48000:channel_layouts=stereo,atrim=0:{T:.3f},volume={music_gain},afade=t=in:d=0.5,afade=t=out:st={T - 1.6:.2f}:d=1.6[m0];"
              "[m0][k]sidechaincompress=threshold=0.03:ratio=6:attack=30:release=450[m];"
              "[2:a]aformat=sample_rates=48000:channel_layouts=stereo,volume=0.9[s];"
              f"[v][m][s]amix=inputs=3:normalize=0:duration=first,atrim=0:{T:.3f}[mx]")
        ff(["-i", self.p("voice.wav"), "-i", f"{CACHE}/music.wav", "-i", self.p("sfx.wav"), "-filter_complex", af, "-map", "[mx]", "-c:a", "pcm_s24le", self.p("mix_pre.wav")])
        m = sh(["ffmpeg", "-hide_banner", "-i", self.p("mix_pre.wav"), "-af", "loudnorm=I=-14:TP=-2:LRA=9:print_format=json", "-f", "null", "-"], True).stderr
        j = json.loads(m[m.rindex("{"):m.rindex("}") + 1])
        ln = (f"loudnorm=I=-14:TP=-2:LRA=9:measured_I={j['input_i']}:measured_TP={j['input_tp']}:measured_LRA={j['input_lra']}:"
              f"measured_thresh={j['input_thresh']}:offset={j['target_offset']}:linear=true")
        ff(["-i", self.p("mix_pre.wav"), "-af", ln + ",alimiter=limit=0.76:attack=2:release=60:level=false,aresample=48000", "-t", f"{T:.3f}", "-c:a", "pcm_s16le", self.p("mix.wav")])

    def mux(self, video, out):
        ff(["-i", video, "-i", self.p("mix.wav"), "-map", "0:v", "-map", "1:a", "-c:v", "copy", "-c:a", "aac", "-b:a", "256k", "-ar", "48000", "-movflags", "+faststart", out])

    # ------------------------------------------------------------------ 6. QA
    def qa(self, out, cut):
        hyp = norm(" ".join(x["w"] for x in asr_words(out)))
        ref = norm(" ".join(x["w"] for x in cut["words"]))
        raw = norm(" ".join(x["w"] for x in asr_words(self.p("voice_cut_raw.wav"))))
        rec = lambda h: sum(b.size for b in difflib.SequenceMatcher(a=ref, b=h, autojunk=False).get_matching_blocks()) / max(len(ref), 1)
        pk = sh(["ffprobe", "-v", "error", "-select_streams", "a", "-show_entries", "packet=pts_time,duration_time", "-of", "csv=p=0", out], True).stdout.split()
        p = [tuple(map(float, l.split(",")[:2])) for l in pk if "," in l]
        gap = max((p[i + 1][0] - (p[i][0] + p[i][1]) for i in range(len(p) - 1)), default=0)
        ld = sh(["ffmpeg", "-hide_banner", "-i", out, "-af", "ebur128=peak=true", "-f", "null", "-"], True).stderr
        I = re.findall(r"I:\s+(-?[\d.]+) LUFS", ld)[-1]; PK = re.findall(r"Peak:\s+(-?[\d.]+) dBFS", ld)[-1]
        T = cut["T"]; ts = [T * (k + 0.5) / 16 for k in range(16)]
        for k, t in enumerate(ts): ff(["-ss", f"{t:.2f}", "-i", out, "-frames:v", "1", "-vf", "scale=270:480", self.p(f"qa{k:02d}.png")])
        ff(["-i", self.p("qa%02d.png"), "-vf", "tile=8x2", self.p("contact_sheet.png")])
        for k in range(16): os.remove(self.p(f"qa{k:02d}.png"))
        rep = {"recall_final": round(rec(hyp), 4), "recall_raw_cut": round(rec(raw), 4), "max_audio_gap_s": round(gap, 4), "lufs": float(I), "peak_dbfs": float(PK)}
        rep["pass"] = rep["recall_final"] >= rep["recall_raw_cut"] - 0.02 and gap < 0.05 and abs(float(I) + 14) < 1 and float(PK) <= -1
        json.dump(rep, open(self.p("qa.json"), "w"), indent=1)
        return rep


# ---------------------------------------------------------------------- helpers
def norm(s): return [re.sub(r"[^a-z0-9']", "", x.lower()) for x in s.split() if re.sub(r"[^a-z0-9']", "", x.lower())]


def load_mono(path, sr):
    raw = subprocess.run(["ffmpeg", "-v", "error", "-i", path, "-ac", "1", "-ar", str(sr), "-f", "s16le", "-"], capture_output=True, check=True).stdout
    return np.frombuffer(raw, np.int16).astype(np.float32) / 32768


_REC = None
def asr_words(path):
    global _REC
    import sherpa_onnx
    M = f"{CACHE}/sherpa-onnx-nemo-parakeet-tdt-0.6b-v2-int8"
    if _REC is None:
        _REC = sherpa_onnx.OfflineRecognizer.from_transducer(encoder=f"{M}/encoder.int8.onnx", decoder=f"{M}/decoder.int8.onnx", joiner=f"{M}/joiner.int8.onnx",
                                                             tokens=f"{M}/tokens.txt", model_type="nemo_transducer", num_threads=4)
    a = load_mono(path, 16000)
    env = np.sqrt(np.convolve(a ** 2, np.ones(800) / 800, "same")); cuts = [0]
    while cuts[-1] + 16000 * 24 < len(a):
        lo, hi = cuts[-1] + 16000 * 16, cuts[-1] + 16000 * 22; cuts.append(lo + int(np.argmin(env[lo:hi])))
    cuts.append(len(a)); words = []
    for s, e in zip(cuts[:-1], cuts[1:]):
        st = _REC.create_stream(); st.accept_waveform(16000, a[s:e]); _REC.decode_stream(st); res = st.result
        for k, (tok, t) in enumerate(zip(res.tokens, res.timestamps)):
            if tok.startswith(" ") or k == 0 or not words: words.append({"w": tok.strip(), "s": round(s / 16000 + t, 3)})
            else: words[-1]["w"] += tok
    words = [x for x in words if x["w"]]
    for i, x in enumerate(words): x["e"] = round(min(words[i + 1]["s"] if i + 1 < len(words) else len(a) / 16000, x["s"] + 0.9), 3)
    return words


def video_size(p):
    w, h = sh(["ffprobe", "-v", "error", "-select_streams", "v:0", "-show_entries", "stream=width,height", "-of", "csv=p=0", p], True).stdout.strip().split(",")[:2]
    return int(w), int(h)


def detect_faces(path, raw=False):
    import cv2
    w, h = video_size(path); sc = 480 / w
    cc = cv2.CascadeClassifier(f"{HERE}/assets/face.xml")
    buf = subprocess.run(["ffmpeg", "-v", "error", "-i", path, "-vf", f"fps=5,scale=480:{int(h * sc) // 2 * 2},format=gray", "-f", "rawvideo", "-"], capture_output=True, check=True).stdout
    hh = int(h * sc) // 2 * 2; fr = np.frombuffer(buf, np.uint8).reshape(-1, hh, 480); out = []
    for k, g in enumerate(fr):
        d = [b for b in cc.detectMultiScale(g, 1.1, 6, minSize=(60, 60)) if b[1] < hh * 0.6 and b[2] < 420]
        if d:
            x, y, ww, hh2 = max(d, key=lambda b: b[2] * b[3])
            out.append([round(k / 5, 2)] + [int(v / sc) for v in (x, y, ww, hh2)])
    a = np.array(out, float); keep = []
    for i, f in enumerate(a):
        nb = a[max(0, i - 5): i + 6]
        if abs(f[2] - np.median(nb[:, 2])) < 0.15 * h and abs(f[3] - np.median(nb[:, 3])) < 0.12 * w: keep.append(out[i])
    return keep


def cut_before(cut, i):
    ws = cut["words"]; k = next(j for j, x in enumerate(ws) if x["i"] == i)
    t = (ws[k - 1]["oe"] + ws[k]["o"]) / 2 if k else 0.0
    near = [s["o"] for s in cut["segs"] if abs(s["o"] - t) < 0.7 and s["o"] > 0]
    return near[0] if near else t


def retake_hints(words, n=4, span=25):
    """Suggest retakes: a phrase of >= n words that restarts within `span` words -> the earlier take [i, j-1] is a removal candidate."""
    toks = norm(" ".join(x["w"] for x in words))
    if len(toks) != len(words): return []
    hints, i = [], 0
    while i < len(toks) - n:
        g = toks[i:i + n]; j = next((j for j in range(i + n, min(len(toks) - n, i + span)) if toks[j:j + n] == g), None)
        if j: hints.append([i, j - 1]); i = j
        else: i += 1
    return hints
