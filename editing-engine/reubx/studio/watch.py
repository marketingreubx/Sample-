#!/usr/bin/env python3
"""Claude's side of the loop. Blocks until there is something for Claude to do, prints ONE JSON event, exits.
  python3 watch.py --folder <dir>                 -> {"type":"note",...} | {"type":"plan",...}
  python3 watch.py --folder <dir> --done <id>     mark a note done
  python3 watch.py --folder <dir> --planned <job> mark a job's plan as refined by Claude
Events: 'note' = the user typed a correction in the app; 'plan' = a new video has an automatic plan that Claude
may upgrade (keyword wording, cards, B-roll) when settings.ai_layer is on."""
import argparse, json, os, sys, time

ap = argparse.ArgumentParser(); ap.add_argument("--folder", required=True); ap.add_argument("--done", type=int); ap.add_argument("--planned"); ap.add_argument("--timeout", type=int, default=3000)
a = ap.parse_args(); S = os.path.join(os.path.abspath(os.path.expanduser(a.folder)), "_reubx")
J = lambda p, d: json.load(open(p)) if os.path.exists(p) else d


def save(p, o): tmp = p + ".tmp"; json.dump(o, open(tmp, "w"), indent=1); os.replace(tmp, p)


if a.done:
    n = J(f"{S}/notes.json", []); [x.update(status="done") for x in n if x["id"] == a.done]; save(f"{S}/notes.json", n); sys.exit()
if a.planned:
    p = f"{S}/{a.planned}/plan.json"; pl = J(p, {}); pl["ai"] = True; save(p, pl); sys.exit()
t0 = time.time()
while time.time() - t0 < a.timeout:
    for n in J(f"{S}/notes.json", []):
        if n.get("status") == "open":
            print(json.dumps({"type": "note", "note": n, "job_dir": f"{S}/{n['job']}", "plan": f"{S}/{n['job']}/plan.json", "transcript": f"{S}/{n['job']}/transcript.txt"})); sys.exit()
    if J(f"{S}/settings.json", {}).get("ai_layer", True):
        for d in sorted(os.listdir(S)) if os.path.isdir(S) else []:
            st, pl = J(f"{S}/{d}/status.json", {}), J(f"{S}/{d}/plan.json", None)
            if pl is not None and st.get("status") in ("ready", "queued", "rendering", "done") and not pl.get("ai"):
                print(json.dumps({"type": "plan", "job": d, "job_dir": f"{S}/{d}", "plan": f"{S}/{d}/plan.json", "transcript": f"{S}/{d}/transcript.txt", "style": pl.get("style")})); sys.exit()
    time.sleep(3)
print(json.dumps({"type": "idle"}))
