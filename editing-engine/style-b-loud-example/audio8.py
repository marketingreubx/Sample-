import json, subprocess
from common8 import *
def ff(c, cap=False): return subprocess.run(["ffmpeg", "-hide_banner", "-y"] + c, check=True, capture_output=cap, text=True)
L = json.load(open(f"{V}/items.json")); wins = json.load(open(f"{V}/wins.json"))
sfx = []; S = lambda t, f, v: sfx.append((max(t, 0), f, v))
seen = set()
for it in L["front"]:
    h = it["html"]; t = it["t0"]
    if 'class="kbw"' in h and it["kind"] == "slam": S(t, f"{SFXD}/pop.wav", 0.16)
    elif 'class="glass"' in h: S(t, f"{SFXD}/pop.wav", 0.10)
    elif it["kind"] == "right": S(t - 0.15, f"{SFXD}/whoosh.wav", 0.18)
for x in wins:
    S(x["a"] - 0.2 if x["lay"] == "F" else x["a"], f"{SFXD}/whoosh.wav", 0.2 if x["lay"] == "F" else 0.14)
for i in (13, 42): S(t_of(i) - 0.05, f"{SFXD}/ding.wav", 0.12)
S(t_of(61) - 0.3, f"{SFXD}/ding.wav", 0.12)
S(t_of(196) + 0.4, f"{P}/sfx/click.wav", 0.4)
S(t_of(280) + 0.3, f"{P}/sfx/typing.wav", 0.2)
inp = ["-i", f"{V}/voice_C.wav", "-i", f"{V6}/music_long.wav"]
af = [f"[0:a]aformat=sample_rates=48000:channel_layouts=stereo,apad=whole_dur={T:.3f},asplit=2[voice][key]",
      f"[1:a]aformat=sample_rates=48000:channel_layouts=stereo,atrim=0:{T:.3f},volume=0.15,afade=t=in:d=0.4,afade=t=out:st={T - 1.5:.2f}:d=1.5[m0]",
      "[m0][key]sidechaincompress=threshold=0.03:ratio=6:attack=30:release=450[mus]"]
mix = ["[voice]", "[mus]"]
for k, (t, f, v) in enumerate(sfx):
    inp += ["-i", f]; ms = int(t * 1000)
    af.append(f"[{2 + k}:a]aformat=sample_rates=48000:channel_layouts=stereo,adelay={ms}|{ms},volume={v}[s{k}]"); mix.append(f"[s{k}]")
af.append("".join(mix) + f"amix=inputs={len(mix)}:normalize=0:duration=first,atrim=0:{T:.3f}[mx]")
ff(["-v", "error"] + inp + ["-filter_complex", ";".join(af), "-map", "[mx]", "-c:a", "pcm_s24le", f"{V}/mix_pre.wav"])
m = ff(["-i", f"{V}/mix_pre.wav", "-af", "loudnorm=I=-14:TP=-2.0:LRA=9:print_format=json", "-f", "null", "-"], cap=True).stderr
j = json.loads(m[m.rindex("{"):m.rindex("}") + 1])
ln = (f"loudnorm=I=-14:TP=-2.0:LRA=9:measured_I={j['input_i']}:measured_TP={j['input_tp']}:measured_LRA={j['input_lra']}:measured_thresh={j['input_thresh']}:offset={j['target_offset']}:linear=true")
ff(["-v", "error", "-i", f"{V}/mix_pre.wav", "-af", ln + ",alimiter=limit=0.76:attack=2:release=60:level=false,aresample=48000", "-t", f"{T:.3f}", "-c:a", "pcm_s16le", f"{V}/mix_final.wav"])
print("audio done, sfx", len(sfx))
