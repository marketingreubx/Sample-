"""Style A audio, its own pass: cleaned voice + low ducked bed + quiet UI sounds (pop / click / typing), no whooshes."""
import json, subprocess
from common7 import *
P_ = V7.rsplit("/", 1)[0]
def ff(c, cap=False): return subprocess.run(["ffmpeg", "-hide_banner", "-y"] + c, check=True, capture_output=cap, text=True)
L = json.load(open(f"{V7}/items.json"))["front"]
sfx = []
S = lambda t, f, v: sfx.append((max(t, 0), f, v))
for it in L:
    if 'class="card"' in it["html"]: S(it["t0"], f"{SFXD}/pop.wav", 0.11)
    if it["kind"] == "tick": S(it["t0"], f"{P_}/sfx/click.wav", 0.35)
S(t_of(7), f"{SFXD}/pop.wav", 0.08)
for i in (200, 207): S(t_of(i) - 0.04, f"{SFXD}/pop.wav", 0.14)
S(T + 0.25, f"{P_}/sfx/typing.wav", 0.22)
inp = ["-i", f"{V6}/voice_C.wav", "-i", f"{V6}/music_long.wav"]
af = [f"[0:a]aformat=sample_rates=48000:channel_layouts=stereo,apad=whole_dur={TT:.3f},asplit=2[voice][key]",
      f"[1:a]aformat=sample_rates=48000:channel_layouts=stereo,atrim=0:{TT:.3f},volume=0.13,afade=t=in:d=0.5,afade=t=out:st={TT - 1.8:.2f}:d=1.8[m0]",
      "[m0][key]sidechaincompress=threshold=0.03:ratio=5:attack=30:release=500[mus]"]
mix = ["[voice]", "[mus]"]
for k, (t, f, v) in enumerate(sfx):
    inp += ["-i", f]; ms = int(t * 1000)
    trim = ",atrim=0:1.0,afade=t=out:st=0.8:d=0.2" if "typing" in f else ""
    af.append(f"[{2 + k}:a]aformat=sample_rates=48000:channel_layouts=stereo{trim},adelay={ms}|{ms},volume={v}[s{k}]"); mix.append(f"[s{k}]")
af.append("".join(mix) + f"amix=inputs={len(mix)}:normalize=0:duration=first,atrim=0:{TT:.3f}[mx]")
ff(["-v", "error"] + inp + ["-filter_complex", ";".join(af), "-map", "[mx]", "-c:a", "pcm_s24le", f"{V7}/mix_pre.wav"])
m = ff(["-i", f"{V7}/mix_pre.wav", "-af", "loudnorm=I=-14:TP=-2.0:LRA=9:print_format=json", "-f", "null", "-"], cap=True).stderr
j = json.loads(m[m.rindex("{"):m.rindex("}") + 1])
ln = (f"loudnorm=I=-14:TP=-2.0:LRA=9:measured_I={j['input_i']}:measured_TP={j['input_tp']}:measured_LRA={j['input_lra']}:"
      f"measured_thresh={j['input_thresh']}:offset={j['target_offset']}:linear=true")
ff(["-v", "error", "-i", f"{V7}/mix_pre.wav", "-af", ln + ",alimiter=limit=0.76:attack=2:release=60:level=false,aresample=48000", "-t", f"{TT:.3f}", "-c:a", "pcm_s16le", f"{V7}/mix_final.wav"])
print("audio done, sfx", len(sfx))
