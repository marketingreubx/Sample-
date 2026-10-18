#!/usr/bin/env python3
"""Hormozi-style short-form editor.

Two-phase workflow so Claude can make the creative calls in between:

  1. analyze  raw.mp4 --work DIR
       Transcribes with word timestamps, removes dead air and filler words,
       and writes DIR/plan.json (segments, zooms, captions, emphasis words, SFX).
  2. (Claude reviews and edits plan.json: hook, retakes, emphasis, B-roll, SFX.)
  3. render   raw.mp4 --plan DIR/plan.json --out final.mp4 [--music bed.mp3]
       Cuts, reframes to 9:16, punch-in zooms, burns animated captions,
       mixes SFX and ducked music, normalises loudness, exports for Reels/TikTok/Shorts.

Only needs ffmpeg (with libass) and faster-whisper.
"""
import argparse
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile

HERE = os.path.dirname(os.path.abspath(__file__))
SKILL = os.path.dirname(HERE)
FONTS_DIR = os.path.join(SKILL, "assets", "fonts")
SFX_DIR = os.path.join(SKILL, "assets", "sfx")

FILLERS = {"um", "uh", "erm", "er", "ah", "hmm", "mm", "uhm", "like,", "um,", "uh,"}
POWER_WORDS = {
    "never", "always", "stop", "free", "secret", "mistake", "money", "worst", "wrong",
    "nobody", "everyone", "double", "half", "zero", "instead", "results", "truth",
}

# ASS colours are &HAABBGGRR
COLORS = {
    "white": "&H00FFFFFF", "yellow": "&H0000E5FF", "green": "&H0033FF33",
    "red": "&H003333FF", "black": "&H00000000", "blue": "&H00FF9933",
}


def run(cmd, quiet=True):
    p = subprocess.run(cmd, capture_output=True, text=True)
    if p.returncode != 0:
        sys.stderr.write(p.stderr[-4000:])
        raise SystemExit(f"command failed: {' '.join(cmd[:6])} ...")
    return p.stdout


def probe(path):
    out = run(["ffprobe", "-v", "error", "-print_format", "json", "-show_streams", "-show_format", path])
    info = json.loads(out)
    v = next(s for s in info["streams"] if s["codec_type"] == "video")
    has_audio = any(s["codec_type"] == "audio" for s in info["streams"])
    w, h = int(v["width"]), int(v["height"])
    rot = 0
    for sd in v.get("side_data_list", []) or []:
        if "rotation" in sd:
            rot = abs(int(sd["rotation"]))
    if rot in (90, 270):
        w, h = h, w
    num, den = v.get("avg_frame_rate", "30/1").split("/")
    fps = float(num) / float(den) if float(den) else 30.0
    return {"w": w, "h": h, "fps": fps, "dur": float(info["format"]["duration"]), "audio": has_audio}


# ---------------------------------------------------------------- analyze

def transcribe(src, model_size, language):
    from faster_whisper import WhisperModel
    tmp = tempfile.mkdtemp()
    wav = os.path.join(tmp, "a.wav")
    run(["ffmpeg", "-y", "-i", src, "-vn", "-ac", "1", "-ar", "16000", wav])
    model = WhisperModel(model_size, device="auto", compute_type="int8")
    segs, info = model.transcribe(wav, word_timestamps=True, language=language,
                                  vad_filter=True, beam_size=5)
    words = []
    for s in segs:
        for w in s.words or []:
            t = w.word.strip()
            if t:
                words.append({"w": t, "s": round(w.start, 3), "e": round(w.end, 3), "p": round(w.probability, 3)})
    shutil.rmtree(tmp, ignore_errors=True)
    return words, info.language


def clean(word):
    return re.sub(r"[^\w$%']", "", word.lower())


def build_segments(words, max_gap, pad, drop_fillers):
    """Group kept words into continuous segments, cutting every pause > max_gap."""
    kept = [w for w in words if not (drop_fillers and clean(w["w"]) in {clean(f) for f in FILLERS})]
    segs = []
    for w in kept:
        if segs and w["s"] - segs[-1]["words"][-1]["e"] <= max_gap:
            segs[-1]["words"].append(w)
        else:
            segs.append({"words": [w]})
    out = []
    for i, s in enumerate(segs):
        start = max(0.0, s["words"][0]["s"] - pad)
        end = s["words"][-1]["e"] + pad
        if out and start < out[-1]["end"]:
            start = out[-1]["end"]
        out.append({
            "id": i, "start": round(start, 3), "end": round(end, 3),
            "text": " ".join(w["w"] for w in s["words"]),
            "keep": True, "zoom": 1.0,
        })
    return out


def is_emphasis(word):
    c = clean(word)
    return bool(re.search(r"\d|\$|%", word)) or c in POWER_WORDS


def build_captions(words, segments, max_words, max_chars):
    """Chunk words into 1-3 word caption groups that never cross a cut."""
    chunks = []
    seg_bounds = [(s["start"], s["end"]) for s in segments]

    def seg_of(t):
        for i, (a, b) in enumerate(seg_bounds):
            if a - 0.01 <= t <= b + 0.01:
                return i
        return None

    cur = []
    for w in words:
        if clean(w["w"]) in {clean(f) for f in FILLERS}:
            continue
        si = seg_of(w["s"])
        if si is None:
            continue
        text_len = sum(len(x["w"]) + 1 for x in cur) + len(w["w"])
        ends_sentence = cur and re.search(r"[.!?]$", cur[-1]["w"])
        if cur and (len(cur) >= max_words or text_len > max_chars or cur[-1]["seg"] != si or ends_sentence):
            chunks.append(cur)
            cur = []
        cur.append({**w, "seg": si, "emph": is_emphasis(w["w"])})
    if cur:
        chunks.append(cur)
    for c in chunks:  # one emphasis word per caption max, keeps it punchy
        seen = False
        for x in c:
            if x["emph"] and seen:
                x["emph"] = False
            seen = seen or x["emph"]
    return [{"words": [{"w": x["w"], "s": x["s"], "e": x["e"], "emph": x["emph"]} for x in c]} for c in chunks]


def cmd_analyze(a):
    os.makedirs(a.work, exist_ok=True)
    info = probe(a.input)
    if not info["audio"]:
        raise SystemExit("input has no audio track, nothing to transcribe")
    print(f"probe: {info['w']}x{info['h']} {info['fps']:.2f}fps {info['dur']:.1f}s")
    words, lang = transcribe(a.input, a.model, a.language)
    print(f"transcribed {len(words)} words ({lang})")
    with open(os.path.join(a.work, "transcript.json"), "w") as f:
        json.dump(words, f, indent=1)

    segs = build_segments(words, a.max_gap, a.pad, not a.keep_fillers)
    # Hormozi rhythm: alternate wide and punched-in framing on every cut
    for i, s in enumerate(segs):
        s["zoom"] = 1.0 if i % 2 == 0 else a.punch
    caps = build_captions(words, segs, a.max_words, a.max_chars)
    raw_len = sum(s["end"] - s["start"] for s in segs)

    plan = {
        "version": 1,
        "source": os.path.abspath(a.input),
        "source_info": info,
        "language": lang,
        "output": {"width": 1080, "height": 1920, "fps": 30, "lufs": -14},
        "framing": {"focus_x": 0.5, "focus_y": 0.42},
        "style": {
            "font": "Montserrat Black", "size": 112, "base_color": "white",
            "active_color": "yellow", "emphasis_color": "green", "outline": 7,
            "shadow": 3, "uppercase": True, "position_y": 0.68, "pop": True,
        },
        "segments": segs,
        "captions": caps,
        "sfx": [],
        "overlays": [],
        "notes": "Edit me: set keep=false on retakes/rambles, move the strongest line first, "
                 "toggle emph on caption words, add sfx/overlays. Times are SOURCE times.",
    }
    path = os.path.join(a.work, "plan.json")
    with open(path, "w") as f:
        json.dump(plan, f, indent=1)
    print(f"segments: {len(segs)}  kept runtime ~{raw_len:.1f}s of {info['dur']:.1f}s  -> {path}")
    print("\nTRANSCRIPT BY SEGMENT")
    for s in segs:
        print(f"[{s['id']:>3}] {s['start']:7.2f}-{s['end']:7.2f}  {s['text']}")


# ---------------------------------------------------------------- render

def ass_time(t):
    t = max(0.0, t)
    h = int(t // 3600); m = int(t % 3600 // 60); s = t % 60
    return f"{h}:{m:02d}:{s:05.2f}"


def ass_escape(t):
    return t.replace("\\", "").replace("{", "(").replace("}", ")")


def source_to_output_map(segments):
    """Return function mapping a source time to output time (None if cut)."""
    kept = [s for s in segments if s.get("keep", True)]
    order = kept  # output order = list order (lets Claude move the hook first)
    table, t = [], 0.0
    for s in order:
        d = s["end"] - s["start"]
        table.append((s["start"], s["end"], t))
        t += d

    def f(x):
        for a, b, o in table:
            if a - 0.02 <= x <= b + 0.02:
                return o + min(max(x - a, 0), b - a)
        return None
    return f, t, order


def build_ass(plan, tmap, total):
    st = plan["style"]
    W, H = plan["output"]["width"], plan["output"]["height"]
    base = COLORS.get(st["base_color"], st["base_color"])
    act = COLORS.get(st["active_color"], st["active_color"])
    emp = COLORS.get(st["emphasis_color"], st["emphasis_color"])
    y = int(H * st["position_y"])
    lines = [
        "[Script Info]", "ScriptType: v4.00+", f"PlayResX: {W}", f"PlayResY: {H}",
        "WrapStyle: 2", "ScaledBorderAndShadow: yes", "",
        "[V4+ Styles]",
        "Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, "
        "Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, "
        "Alignment, MarginL, MarginR, MarginV, Encoding",
        f"Style: Cap,{st['font']},{st['size']},{base},{base},&H00000000,&H96000000,-1,0,0,0,100,100,1,0,1,"
        f"{st['outline']},{st['shadow']},5,60,60,0,1",
        "", "[Events]",
        "Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text",
    ]
    pos = f"\\an5\\pos({W // 2},{y})"
    for ci, c in enumerate(plan["captions"]):
        ws = c["words"]
        times = [(tmap(w["s"]), tmap(w["e"])) for w in ws]
        if any(a is None for a, _ in times):
            continue
        c_start = times[0][0]
        nxt = None
        if ci + 1 < len(plan["captions"]):
            nxt = tmap(plan["captions"][ci + 1]["words"][0]["s"])
        c_end = max(times[-1][1], c_start + 0.25)
        if nxt is not None and nxt > c_start:
            c_end = min(max(c_end, nxt), c_end + 0.6)
        c_end = min(c_end, total)
        for wi, w in enumerate(ws):
            a = times[wi][0] if wi else c_start
            b = times[wi + 1][0] if wi + 1 < len(ws) else c_end
            if b <= a:
                continue
            parts = []
            for wj, x in enumerate(ws):
                txt = ass_escape(x["w"].upper() if st.get("uppercase") else x["w"])
                txt = re.sub(r"[.,;:!]+$", "", txt)
                if x.get("emph"):
                    parts.append(f"{{\\c{emp}\\fscx112\\fscy112}}{txt}{{\\fscx100\\fscy100\\c{base}}}")
                elif wj == wi:
                    parts.append(f"{{\\c{act}}}{txt}{{\\c{base}}}")
                else:
                    parts.append(txt)
            pop = "\\fscx70\\fscy70\\t(0,90,\\fscx105\\fscy105)\\t(90,140,\\fscx100\\fscy100)" \
                if (st.get("pop") and wi == 0) else ""
            lines.append(f"Dialogue: 0,{ass_time(a)},{ass_time(b)},Cap,,0,0,0,,{{{pos}{pop}}}" + " ".join(parts))
    for o in plan.get("overlays", []):
        if o.get("type") != "text":
            continue
        a = tmap(o["t"])
        if a is None:
            continue
        b = a + o.get("dur", 1.5)
        oy = int(H * o.get("y", 0.2))
        size = o.get("size", 110)
        col = COLORS.get(o.get("color", "white"), o.get("color", "white"))
        lines.append(f"Dialogue: 1,{ass_time(a)},{ass_time(b)},Cap,,0,0,0,,"
                     f"{{\\an5\\pos({W // 2},{oy})\\fs{size}\\c{col}\\fscx70\\fscy70\\t(0,100,\\fscx100\\fscy100)}}"
                     f"{ass_escape(o['text'])}")
    return "\n".join(lines) + "\n"


def crop_expr(info, out_w, out_h, zoom, fx, fy):
    """Crop a centred-on-focus 9:16 window, tighter by `zoom`."""
    target = out_w / out_h
    sw, sh = info["w"], info["h"]
    if sw / sh > target:
        ch = sh; cw = sh * target
    else:
        cw = sw; ch = sw / target
    cw, ch = cw / zoom, ch / zoom
    cw, ch = int(cw) // 2 * 2, int(ch) // 2 * 2
    x = min(max(int(sw * fx - cw / 2), 0), sw - cw)
    y = min(max(int(sh * fy - ch / 2), 0), sh - ch)
    return f"crop={cw}:{ch}:{x}:{y},scale={out_w}:{out_h}:flags=lanczos,setsar=1"


def cmd_render(a):
    plan = json.load(open(a.plan))
    src = a.input or plan["source"]
    info = probe(src)
    out = plan["output"]
    W, H, FPS = out["width"], out["height"], out["fps"]
    fx, fy = plan["framing"]["focus_x"], plan["framing"]["focus_y"]
    tmap, total, order = source_to_output_map(plan["segments"])
    if not order:
        raise SystemExit("no segments kept")
    work = tempfile.mkdtemp(prefix="hz_")

    # pass 1: cut, reframe, punch-in, join
    fc, vlab, alab = [], [], []
    for i, s in enumerate(order):
        z = float(s.get("zoom", 1.0))
        fxs, fys = s.get("focus_x", fx), s.get("focus_y", fy)
        fc.append(f"[0:v]trim={s['start']}:{s['end']},setpts=PTS-STARTPTS,fps={FPS},"
                  f"{crop_expr(info, W, H, z, fxs, fys)}[v{i}]")
        fc.append(f"[0:a]atrim={s['start']}:{s['end']},asetpts=PTS-STARTPTS,"
                  f"afade=t=in:d=0.012,afade=t=out:st={max(s['end'] - s['start'] - 0.012, 0):.3f}:d=0.012[a{i}]")
        vlab.append(f"[v{i}][a{i}]")
    fc.append("".join(vlab) + f"concat=n={len(order)}:v=1:a=1[vc][ac]")
    script = os.path.join(work, "cut.txt")
    open(script, "w").write(";\n".join(fc))
    cut = os.path.join(work, "cut.mp4")
    print(f"pass 1: cutting {len(order)} segments -> {total:.1f}s")
    run(["ffmpeg", "-y", "-i", src, "-filter_complex_script", script, "-map", "[vc]", "-map", "[ac]",
         "-c:v", "libx264", "-preset", "veryfast", "-crf", "16", "-c:a", "pcm_s16le", cut.replace(".mp4", ".mov")])
    cut = cut.replace(".mp4", ".mov")

    # pass 2: captions + polish + audio mix
    ass = os.path.join(work, "caps.ass")
    open(ass, "w").write(build_ass(plan, tmap, total))
    inputs = ["-i", cut]
    afc = []
    vlabel = "[voice0]" if a.music else "[voice]"
    mix = ["[0:a]highpass=f=80,acompressor=threshold=-18dB:ratio=3:attack=5:release=80,"
           f"equalizer=f=3500:t=q:w=1:g=2{vlabel}"]
    mix_inputs = ["[voice]"]
    n = 1
    for k, sfx in enumerate(plan.get("sfx", [])):
        t = tmap(sfx["t"])
        f = sfx["file"] if os.path.isabs(sfx["file"]) else os.path.join(SFX_DIR, sfx["file"])
        if t is None or not os.path.exists(f):
            continue
        inputs += ["-i", f]
        ms = int(t * 1000)
        mix.append(f"[{n}:a]adelay={ms}|{ms},volume={sfx.get('vol', 0.6)}[s{k}]")
        mix_inputs.append(f"[s{k}]")
        n += 1
    if a.music:
        inputs += ["-stream_loop", "-1", "-i", a.music]
        mix.append(f"[{n}:a]volume={a.music_vol},atrim=0:{total:.3f},afade=t=out:st={max(total - 1.5, 0):.2f}:d=1.5[mus]")
        mix.append("[voice0]asplit=2[voice][vkey]")
        mix.append("[mus][vkey]sidechaincompress=threshold=0.03:ratio=8:attack=20:release=300[musd]")
        mix_inputs.append("[musd]")
        n += 1
    if len(mix_inputs) > 1:
        mix.append("".join(mix_inputs) + f"amix=inputs={len(mix_inputs)}:duration=first:normalize=0[mx]")
        last = "[mx]"
    else:
        last = "[voice]"
    mix.append(f"{last}loudnorm=I={out['lufs']}:TP=-1.0:LRA=9[aout]")
    grade = "eq=contrast=1.06:saturation=1.12:brightness=0.01,unsharp=5:5:0.6:5:5:0.0"
    fonts = FONTS_DIR.replace("\\", "/").replace(":", "\\:")
    ass_f = ass.replace("\\", "/").replace(":", "\\:")
    vf = f"[0:v]{grade},ass='{ass_f}':fontsdir='{fonts}'[vout]"
    fcs = os.path.join(work, "final.txt")
    open(fcs, "w").write(";\n".join([vf] + mix))
    print("pass 2: captions, grade, audio mix, loudness")
    run(["ffmpeg", "-y", *inputs, "-filter_complex_script", fcs, "-map", "[vout]", "-map", "[aout]",
         "-c:v", "libx264", "-preset", "slow", "-crf", "18", "-profile:v", "high", "-pix_fmt", "yuv420p",
         "-r", str(FPS), "-c:a", "aac", "-b:a", "192k", "-ar", "48000", "-movflags", "+faststart",
         "-t", f"{total:.3f}", a.out])
    if a.keep_work:
        print(f"work files: {work}")
    else:
        shutil.rmtree(work, ignore_errors=True)
    print(f"done -> {a.out}  ({total:.1f}s, {W}x{H})")


def cmd_preview(a):
    """Export contact-sheet frames so Claude can visually check framing and captions."""
    os.makedirs(a.dir, exist_ok=True)
    info = probe(a.video)
    n = a.frames
    for i in range(n):
        t = info["dur"] * (i + 0.5) / n
        run(["ffmpeg", "-y", "-ss", f"{t:.2f}", "-i", a.video, "-frames:v", "1", "-vf", "scale=360:-2",
             os.path.join(a.dir, f"frame_{i:02d}.jpg")])
    run(["ffmpeg", "-y", "-i", os.path.join(a.dir, "frame_%02d.jpg"), "-vf", f"tile={min(n, 4)}x{(n + 3) // 4}",
         os.path.join(a.dir, "contact_sheet.jpg")])
    print(os.path.join(a.dir, "contact_sheet.jpg"))


def main():
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = p.add_subparsers(dest="cmd", required=True)

    an = sub.add_parser("analyze", help="transcribe + auto-cut plan")
    an.add_argument("input")
    an.add_argument("--work", required=True)
    an.add_argument("--model", default="small", help="tiny|base|small|medium|large-v3")
    an.add_argument("--language", default=None)
    an.add_argument("--max-gap", type=float, default=0.28, help="cut any pause longer than this (s)")
    an.add_argument("--pad", type=float, default=0.06, help="breathing room kept around words (s)")
    an.add_argument("--punch", type=float, default=1.18, help="punch-in zoom for alternate cuts")
    an.add_argument("--max-words", type=int, default=3)
    an.add_argument("--max-chars", type=int, default=16)
    an.add_argument("--keep-fillers", action="store_true")
    an.set_defaults(fn=cmd_analyze)

    r = sub.add_parser("render", help="render final vertical short from plan.json")
    r.add_argument("input", nargs="?")
    r.add_argument("--plan", required=True)
    r.add_argument("--out", required=True)
    r.add_argument("--music")
    r.add_argument("--music-vol", type=float, default=0.12)
    r.add_argument("--keep-work", action="store_true")
    r.set_defaults(fn=cmd_render)

    pv = sub.add_parser("preview", help="contact sheet of the rendered video")
    pv.add_argument("video")
    pv.add_argument("--dir", required=True)
    pv.add_argument("--frames", type=int, default=8)
    pv.set_defaults(fn=cmd_preview)

    a = p.parse_args()
    a.fn(a)


if __name__ == "__main__":
    main()
