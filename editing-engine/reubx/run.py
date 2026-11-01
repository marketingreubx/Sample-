#!/usr/bin/env python3
"""Reubx editing engine CLI.
  python3 run.py prep   <video> --work <dir>     transcribe + analyse; prints the numbered transcript used to write plan.json
  python3 run.py render <video> --work <dir> --style <a-list|a-story|a-skit|b-loud|b-editorial|b-showcase> [--out file.mp4]
Plan: <work>/plan.json (schema in PLANS.md). B-roll images: <work>/broll/. Re-renders after a correction reuse cached steps."""
import argparse, hashlib, json, os, sys, time
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from core import Job, retake_hints, ff, W, H
import gfx

GRADE = {"a": "eq=brightness=0.025:contrast=1.05:saturation=1.05:gamma=1.03,colorbalance=rm=0.02:bm=-0.012,unsharp=5:5:0.45",
         "b": "eq=brightness=0.03:contrast=1.06:saturation=1.07:gamma=1.04,unsharp=5:5:0.5"}


def h(obj): return hashlib.md5(json.dumps(obj, sort_keys=True).encode()).hexdigest()[:10]


def prep(job):
    w = job.transcribe(); job.analyse()
    lines, cur, t0 = [], [], 0
    for i, x in enumerate(w):
        if not cur: t0 = x["s"]
        cur.append(f"{i}:{x['w']}")
        if len(cur) >= 14 or x["w"][-1:] in ".?!": lines.append(f"[{t0:5.1f}] " + " ".join(cur)); cur = []
    if cur: lines.append(f"[{t0:5.1f}] " + " ".join(cur))
    open(job.p("transcript.txt"), "w").write("\n".join(lines))
    print("\n".join(lines))
    print("\nRETAKE HINTS (earlier take of a repeated phrase -> candidate 'remove' range):", retake_hints(w) or "none")


def showcase(job, edited, out, cta):
    from PIL import Image, ImageDraw, ImageFilter, ImageFont
    bg = Image.new("RGB", (W, H), (242, 242, 245))
    blob = Image.new("L", (W, H), 0); ImageDraw.Draw(blob).ellipse([200, 350, 1100, 1300], fill=110)
    bg = Image.composite(Image.new("RGB", (W, H), (226, 220, 245)), bg, blob.filter(ImageFilter.GaussianBlur(160))); d = ImageDraw.Draw(bg)
    fp = ImageFont.truetype("/root/.fonts/Poppins-SemiBold.ttf", 40); fb = ImageFont.truetype("/root/.fonts/Poppins-Bold.ttf", 46)
    d.rounded_rectangle([390, 200, 690, 270], 35, fill=(28, 28, 32))
    d.text((540, 235), "Edited by Reubx", font=ImageFont.truetype("/root/.fonts/Poppins-SemiBold.ttf", 28), fill="white", anchor="mm")
    a, _, b = cta.partition(" for ")
    d.text((540, 1500), a, font=fb, fill=(40, 40, 46), anchor="mm")
    if b: d.text((540, 1562), "for " + b, font=fp, fill=(40, 40, 46), anchor="mm")
    bg.save(job.p("sc_bg.png"))
    def rmask(w, hh, r, n):
        m = Image.new("L", (w, hh), 0); ImageDraw.Draw(m).rounded_rectangle([0, 0, w - 1, hh - 1], r, fill=255); m.save(job.p(n))
    rmask(560, 996, 46, "sc_m1.png"); rmask(300, 534, 30, "sc_m2.png")
    fc = ("[1:v]scale=560:996,format=rgba[e0];[3:v]format=gray[m1];[e0][m1]alphamerge[e];"
          "[2:v]scale=300:534,hue=s=0,format=rgba[r0];[4:v]format=gray[m2];[r0][m2]alphamerge[r];"
          "[0:v][e]overlay=410:330[o1];[o1][r]overlay=120:560,format=yuv420p[v]")
    ff(["-loop", "1", "-i", job.p("sc_bg.png"), "-i", edited, "-i", job.video, "-loop", "1", "-i", job.p("sc_m1.png"), "-loop", "1", "-i", job.p("sc_m2.png"),
        "-filter_complex", fc, "-map", "[v]", "-map", "1:a", "-shortest", "-c:v", "libx264", "-preset", "medium", "-crf", "18", "-c:a", "copy", out])


def render(job, style, out):
    t = time.time(); fam = style[0]
    plan = json.load(open(job.p("plan.json"))); plan["style"] = style
    st = json.load(open(job.p("state.json"))) if os.path.exists(job.p("state.json")) else {}
    kc = h([plan.get("fix"), plan.get("remove")]); kb = h([kc, plan.get("shots"), plan.get("punch"), plan.get("start_tight"), fam])
    if st.get("cut") != kc or not os.path.exists(job.p("voice.wav")):
        cut = job.cut(plan); job.voice(cut); st["cut"] = kc; print(f"cut {cut['T']:.2f}s, voice cleaned", flush=True)
    else: cut = json.load(open(job.p("cut.json")))
    if st.get("base") != kb or not os.path.exists(job.p("base.mp4")):
        job.base(cut, plan, GRADE[fam], 0.5 if fam == "a" else 0.42); st["base"] = kb; print("camera moves + base done", flush=True)
    json.dump(st, open(job.p("state.json"), "w"))
    wins = []
    if fam == "a":
        import style_a; L, EV, END = style_a.build(job, plan, cut, wins); acc, acc2 = plan.get("accent_color", "#ff5a93"), "#F3E37C"
    else:
        import style_b; L, EV = style_b.build(job, plan, cut, wins); END = 0.0; acc, acc2 = plan.get("accent", "#2F6BFF"), plan.get("accent2", "#9cc2ff")
    total = cut["T"] + END
    for name, items in L.items():
        if items: gfx.render(items, name, total, job.dir, acc, acc2)
    if wins: gfx.broll_clips(job.dir, wins, job.broll)
    gfx.compose(job.dir, cut["T"], wins, [n for n, v in L.items() if v], job.p("video_only.mp4"), END)
    job.audio(total, EV)
    final = out if style != "b-showcase" else job.p("edit_inner.mp4")
    job.mux(job.p("video_only.mp4"), final)
    if style == "b-showcase": showcase(job, final, out, plan.get("showcase_cta", 'Follow & comment "CLAUDE" for the full guide'))
    rep = job.qa(final, cut); rep.update(seconds=round(time.time() - t), out=out, contact_sheet=job.p("contact_sheet.png"))
    print(json.dumps(rep, indent=1))


if __name__ == "__main__":
    ap = argparse.ArgumentParser(); ap.add_argument("cmd", choices=["prep", "render"]); ap.add_argument("video")
    ap.add_argument("--work", required=True); ap.add_argument("--style", default="b-loud"); ap.add_argument("--out")
    a = ap.parse_args(); job = Job(a.video, a.work)
    prep(job) if a.cmd == "prep" else render(job, a.style, a.out or job.p(f"final_{a.style}.mp4"))
