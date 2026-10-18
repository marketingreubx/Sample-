#!/usr/bin/env python3
"""Measure the editing style of a reference video so it can be copied with numbers, not guesses.

    python3 study_reference.py REFERENCE.mp4 --dir OUTDIR [--threshold 0.30]

Writes OUTDIR/report.json and OUTDIR/report.md with:
  - every detected cut (scene change) and the average / median shot length
  - cuts per 10 seconds across the video (pacing curve, hook vs body vs ending)
  - silence gaps left in the edit (how tight the audio cutting is)
  - integrated loudness (LUFS) and true peak
  - a frame grabbed just after every cut, tiled into contact sheets, so Claude can
    look at framing, zoom level, caption font/colour/position, B-roll and overlays
Then read the contact sheets with the Read tool and fill in style notes.
"""
import argparse
import json
import os
import re
import statistics
import subprocess


def sh(cmd):
    return subprocess.run(cmd, capture_output=True, text=True)


def probe(path):
    out = json.loads(sh(["ffprobe", "-v", "error", "-print_format", "json", "-show_streams",
                         "-show_format", path]).stdout)
    v = next(s for s in out["streams"] if s["codec_type"] == "video")
    return float(out["format"]["duration"]), int(v["width"]), int(v["height"])


def cuts(path, threshold):
    p = sh(["ffmpeg", "-hide_banner", "-i", path, "-filter:v",
            f"select='gt(scene,{threshold})',showinfo", "-f", "null", "-"])
    return [float(m) for m in re.findall(r"pts_time:([\d.]+)", p.stderr)]


def silences(path, noise="-35dB", dur=0.25):
    p = sh(["ffmpeg", "-hide_banner", "-i", path, "-af", f"silencedetect=noise={noise}:d={dur}", "-f", "null", "-"])
    starts = [float(x) for x in re.findall(r"silence_start: ([\d.]+)", p.stderr)]
    lens = [float(x) for x in re.findall(r"silence_duration: ([\d.]+)", p.stderr)]
    return list(zip(starts, lens))


def loudness(path):
    p = sh(["ffmpeg", "-hide_banner", "-i", path, "-af", "loudnorm=print_format=json", "-f", "null", "-"])
    m = re.search(r"\{[^{}]*\"input_i\"[^{}]*\}", p.stderr, re.S)
    return json.loads(m.group(0)) if m else {}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("video")
    ap.add_argument("--dir", required=True)
    ap.add_argument("--threshold", type=float, default=0.30,
                    help="scene change sensitivity; lower catches punch-in zooms too (try 0.15)")
    ap.add_argument("--max-frames", type=int, default=48)
    a = ap.parse_args()
    os.makedirs(a.dir, exist_ok=True)

    dur, w, h = probe(a.video)
    c = cuts(a.video, a.threshold)
    bounds = [0.0] + c + [dur]
    shots = [round(b - a_, 3) for a_, b in zip(bounds, bounds[1:]) if b - a_ > 0.05]
    per10 = [sum(1 for t in c if i <= t < i + 10) for i in range(0, int(dur) + 1, 10)]
    sil = silences(a.video)
    loud = loudness(a.video)

    # one frame shortly after each cut (plus start), capped, then tiled
    picks = [0.2] + [t + 0.15 for t in c]
    if len(picks) > a.max_frames:
        step = len(picks) / a.max_frames
        picks = [picks[int(i * step)] for i in range(a.max_frames)]
    fdir = os.path.join(a.dir, "frames")
    os.makedirs(fdir, exist_ok=True)
    for i, t in enumerate(picks):
        sh(["ffmpeg", "-y", "-ss", f"{min(t, dur - 0.05):.2f}", "-i", a.video, "-frames:v", "1",
            "-vf", "scale=270:-2", os.path.join(fdir, f"f_{i:03d}.jpg")])
    sheets = []
    for k in range(0, len(picks), 16):
        out = os.path.join(a.dir, f"sheet_{k // 16:02d}.jpg")
        sh(["ffmpeg", "-y", "-start_number", str(k), "-i", os.path.join(fdir, "f_%03d.jpg"),
            "-frames:v", "1", "-vf", "tile=4x4:padding=4", out])
        sheets.append(out)

    report = {
        "duration_s": round(dur, 2), "resolution": f"{w}x{h}",
        "orientation": "vertical" if h > w else "horizontal",
        "cuts": len(c), "cut_times": [round(t, 2) for t in c],
        "avg_shot_s": round(statistics.mean(shots), 2) if shots else None,
        "median_shot_s": round(statistics.median(shots), 2) if shots else None,
        "shortest_shot_s": min(shots) if shots else None, "longest_shot_s": max(shots) if shots else None,
        "first_cut_s": round(c[0], 2) if c else None,
        "cuts_per_10s": per10,
        "silences_over_0_25s": len(sil), "longest_silence_s": round(max((l for _, l in sil), default=0), 2),
        "integrated_lufs": loud.get("input_i"), "true_peak_db": loud.get("input_tp"),
        "contact_sheets": sheets,
        "frame_times": [round(t, 2) for t in picks],
    }
    json.dump(report, open(os.path.join(a.dir, "report.json"), "w"), indent=1)
    md = [f"# Reference edit report: {os.path.basename(a.video)}", "",
          f"- Duration {report['duration_s']}s, {report['resolution']} ({report['orientation']})",
          f"- {report['cuts']} cuts, average shot {report['avg_shot_s']}s, median {report['median_shot_s']}s, "
          f"first cut at {report['first_cut_s'] if c else 'n/a'}s",
          f"- Cuts per 10s: {per10}",
          f"- Silences over 0.25s left in: {report['silences_over_0_25s']} (longest {report['longest_silence_s']}s)",
          f"- Loudness {report['integrated_lufs']} LUFS, true peak {report['true_peak_db']} dB",
          "", "Contact sheets (read these to note caption style, framing, zoom, B-roll, overlays):"]
    md += [f"- {s}" for s in sheets]
    open(os.path.join(a.dir, "report.md"), "w").write("\n".join(md) + "\n")
    print("\n".join(md))


if __name__ == "__main__":
    main()
