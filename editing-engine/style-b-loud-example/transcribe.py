import sherpa_onnx, numpy as np, subprocess, json, sys
P = "/tmp/claude-0/-home-user-Sample-/1667514b-ba1c-53c3-9a16-c019b68c5923/scratchpad"
M = f"{P}/sherpa-onnx-nemo-parakeet-tdt-0.6b-v2-int8"
r = sherpa_onnx.OfflineRecognizer.from_transducer(encoder=f"{M}/encoder.int8.onnx", decoder=f"{M}/decoder.int8.onnx", joiner=f"{M}/joiner.int8.onnx", tokens=f"{M}/tokens.txt", model_type="nemo_transducer", num_threads=4)
a = np.frombuffer(subprocess.run(["ffmpeg", "-v", "error", "-i", sys.argv[1], "-ac", "1", "-ar", "16000", "-f", "s16le", "-"], capture_output=True).stdout, np.int16).astype(np.float32) / 32768
# chunk at the quietest 50ms near every 20s
env = np.sqrt(np.convolve(a ** 2, np.ones(800) / 800, "same"))
cuts = [0]
while cuts[-1] + 16000 * 24 < len(a):
    lo, hi = cuts[-1] + 16000 * 16, cuts[-1] + 16000 * 22
    cuts.append(lo + int(np.argmin(env[lo:hi])))
cuts.append(len(a))
words = []
for s, e in zip(cuts[:-1], cuts[1:]):
    st = r.create_stream(); st.accept_waveform(16000, a[s:e]); r.decode_stream(st)
    res = st.result; off = s / 16000
    for tok, t in zip(res.tokens, res.timestamps):
        if tok.startswith(" ") or not words or words[-1].get("closed"):
            words.append({"w": tok.strip(), "s": round(off + t, 3)})
        else: words[-1]["w"] += tok
    if words: words[-1]["closed"] = True
for w in words: w.pop("closed", None)
for i, w in enumerate(words): w["e"] = round(min(words[i + 1]["s"] if i + 1 < len(words) else len(a) / 16000, w["s"] + 0.9), 3)
json.dump(words, open(sys.argv[2], "w"), indent=0)
print(len(words), "words"); print(" ".join(f'{i}:{w["w"]}@{w["s"]:.2f}' for i, w in enumerate(words)))
