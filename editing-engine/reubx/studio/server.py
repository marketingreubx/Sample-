#!/usr/bin/env python3
"""Reubx Studio — local editing app. Watches a video folder, makes an automatic first edit of every new video,
and serves a browser UI (fonts, colours, looks, sound, camera, transcript cuts, notes for Claude).
  python3 server.py --folder ~/Videos/reels [--port 8765] [--auto]"""
import argparse, json, mimetypes, os, queue, re, subprocess, sys, threading, time, traceback
from http.server import ThreadingHTTPServer, BaseHTTPRequestHandler
from urllib.parse import urlparse, parse_qs, unquote

ENGINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, ENGINE); sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
VIDEO_EXT = (".mp4", ".mov", ".m4v", ".mkv", ".webm")
PRESETS = json.load(open(os.path.join(ENGINE, "presets.json")))
STYLES = [k for k in PRESETS if not k.startswith("_")] + ["a-list", "a-story", "a-skit", "b-loud", "b-editorial", "b-showcase"]

ap = argparse.ArgumentParser(); ap.add_argument("--folder", required=True); ap.add_argument("--port", type=int, default=8765); ap.add_argument("--auto", action="store_true")
A = ap.parse_args()
FOLDER = os.path.abspath(os.path.expanduser(A.folder)); STUDIO = os.path.join(FOLDER, "_reubx"); OUT = os.path.join(FOLDER, "edited")
os.makedirs(STUDIO, exist_ok=True); os.makedirs(OUT, exist_ok=True)
SET_P, NOTES_P = os.path.join(STUDIO, "settings.json"), os.path.join(STUDIO, "notes.json")
LOCK = threading.Lock(); RQ = queue.Queue(); STATUS = {}


def jload(p, d):
    try: return json.load(open(p))
    except Exception: return d


def jsave(p, obj):
    tmp = p + ".tmp"; json.dump(obj, open(tmp, "w"), indent=1); os.replace(tmp, p)


def settings():
    return jload(SET_P, {"style": "edita-lawyer", "look": {}, "auto": A.auto, "ai_layer": True})


def job_id(name): return re.sub(r"[^A-Za-z0-9_-]+", "_", os.path.splitext(name)[0])[:60]


def jdir(jid): return os.path.join(STUDIO, jid)


def set_status(jid, st, **kw):
    with LOCK:
        s = jload(os.path.join(jdir(jid), "status.json"), {}); s.update(status=st, updated=time.time(), **kw)
        jsave(os.path.join(jdir(jid), "status.json"), s); STATUS[jid] = s


# ---------------------------------------------------------------------- watcher: new video -> transcript -> automatic plan
def prepare(name):
    from core import Job
    from autoplan import autoplan
    jid = job_id(name); d = jdir(jid); os.makedirs(d, exist_ok=True)
    set_status(jid, "transcribing", name=name)
    import run
    job = Job(os.path.join(FOLDER, name), d); run.prep(job); words = json.load(open(os.path.join(d, "transcript.json")))
    if not os.path.exists(os.path.join(d, "plan.json")):
        st = settings(); plan = autoplan(words, PRESETS.get(st["style"], {}).get("base", st["style"]), st.get("look", {})); plan["ai"] = False; plan["style"] = st["style"]
        jsave(os.path.join(d, "plan.json"), plan)
    set_status(jid, "ready", name=name)
    if settings().get("auto"): RQ.put(jid)


def watcher():
    seen = set()
    while True:
        try:
            for name in sorted(os.listdir(FOLDER)):
                if not name.lower().endswith(VIDEO_EXT) or name.startswith("."): continue
                p = os.path.join(FOLDER, name); jid = job_id(name)
                if jid in seen: continue
                s1 = os.path.getsize(p); time.sleep(1.0)
                if os.path.getsize(p) != s1: continue          # still copying
                seen.add(jid)
                if jload(os.path.join(jdir(jid), "status.json"), {}).get("status") in ("ready", "done", "rendering", "queued", "failed"):
                    STATUS[jid] = jload(os.path.join(jdir(jid), "status.json"), {}); continue
                threading.Thread(target=lambda n=name: safe(prepare, n), daemon=True).start()
        except Exception: traceback.print_exc()
        time.sleep(3)


def safe(fn, *a):
    try: fn(*a)
    except Exception as e:
        traceback.print_exc()
        if a: set_status(job_id(a[0]) if isinstance(a[0], str) and "." in a[0] else a[0], "failed", error=str(e)[-400:])


def renderer():
    while True:
        jid = RQ.get(); st = jload(os.path.join(jdir(jid), "status.json"), {}); plan = jload(os.path.join(jdir(jid), "plan.json"), {})
        style = plan.get("style", settings()["style"]); out = os.path.join(OUT, f"{jid}_{style}.mp4")
        set_status(jid, "rendering", started=time.time())
        log = open(os.path.join(jdir(jid), "render.log"), "w")
        r = subprocess.run([sys.executable, os.path.join(ENGINE, "run.py"), "render", os.path.join(FOLDER, st["name"]), "--work", jdir(jid), "--style", style, "--out", out],
                           stdout=log, stderr=subprocess.STDOUT); log.close()
        if r.returncode == 0 and os.path.exists(out): set_status(jid, "done", out=os.path.relpath(out, FOLDER), qa=jload(os.path.join(jdir(jid), "qa.json"), {}))
        else: set_status(jid, "failed", error=open(os.path.join(jdir(jid), "render.log")).read()[-600:])


# ---------------------------------------------------------------------- HTTP API
class H(BaseHTTPRequestHandler):
    def log_message(self, *a): pass

    def send(self, obj, code=200, ctype="application/json"):
        b = obj if isinstance(obj, (bytes, bytearray)) else json.dumps(obj).encode()
        self.send_response(code); self.send_header("Content-Type", ctype); self.send_header("Content-Length", str(len(b))); self.end_headers(); self.wfile.write(b)

    def body(self):
        n = int(self.headers.get("Content-Length", 0) or 0); return json.loads(self.rfile.read(n) or b"{}")

    def do_GET(self):
        u = urlparse(self.path); q = parse_qs(u.query)
        if u.path == "/": return self.send(open(os.path.join(os.path.dirname(__file__), "app.html"), "rb").read(), ctype="text/html; charset=utf-8")
        if u.path == "/api/state":
            vids = []
            for name in sorted(os.listdir(FOLDER)):
                if name.lower().endswith(VIDEO_EXT) and not name.startswith("."):
                    jid = job_id(name); s = jload(os.path.join(jdir(jid), "status.json"), {"status": "new"}); s.update(id=jid, name=name); vids.append(s)
            import gfx, looks
            return self.send({"folder": FOLDER, "settings": settings(), "videos": vids, "styles": STYLES, "fonts": sorted(gfx.FONTS), "looks": looks.names(), "notes": jload(NOTES_P, [])})
        m = re.match(r"^/api/job/([\w-]+)$", u.path)
        if m:
            d = jdir(m.group(1)); log = os.path.join(d, "render.log")
            return self.send({"transcript": jload(os.path.join(d, "transcript.json"), []), "plan": jload(os.path.join(d, "plan.json"), {}),
                              "status": jload(os.path.join(d, "status.json"), {}), "log": open(log).read()[-1500:] if os.path.exists(log) else ""})
        if u.path == "/file":
            p = os.path.abspath(os.path.join(FOLDER, unquote(q.get("p", [""])[0])))
            if not p.startswith(FOLDER) or not os.path.isfile(p): return self.send({"error": "not found"}, 404)
            return self.file(p)
        self.send({"error": "not found"}, 404)

    def file(self, p):
        size = os.path.getsize(p); ctype = mimetypes.guess_type(p)[0] or "application/octet-stream"; rng = self.headers.get("Range")
        a, b = 0, size - 1
        if rng:
            m = re.match(r"bytes=(\d*)-(\d*)", rng); a = int(m.group(1) or 0); b = int(m.group(2) or size - 1)
            self.send_response(206); self.send_header("Content-Range", f"bytes {a}-{b}/{size}")
        else: self.send_response(200)
        self.send_header("Content-Type", ctype); self.send_header("Accept-Ranges", "bytes"); self.send_header("Content-Length", str(b - a + 1)); self.end_headers()
        with open(p, "rb") as f:
            f.seek(a); left = b - a + 1
            while left > 0:
                chunk = f.read(min(1 << 20, left))
                if not chunk: break
                try: self.wfile.write(chunk)
                except (BrokenPipeError, ConnectionResetError): return
                left -= len(chunk)

    def do_POST(self):
        u = urlparse(self.path); data = self.body()
        if u.path == "/api/settings":
            s = settings(); s.update({k: v for k, v in data.items() if k in ("style", "look", "auto", "ai_layer")}); jsave(SET_P, s); return self.send(s)
        m = re.match(r"^/api/job/([\w-]+)/(plan|render|note|looks)$", u.path)
        if not m: return self.send({"error": "not found"}, 404)
        jid, act = m.groups(); d = jdir(jid); pp = os.path.join(d, "plan.json")
        if act == "plan":
            with LOCK:
                plan = jload(pp, {})
                for k, v in data.items():
                    if k == "look": plan["look"] = dict(plan.get("look", {}), **v)
                    else: plan[k] = v
                jsave(pp, plan)
            return self.send(plan)
        if act == "render":
            set_status(jid, "queued"); RQ.put(jid); return self.send({"ok": True})
        if act == "note":
            with LOCK:
                notes = jload(NOTES_P, []); n = {"id": len(notes) + 1, "job": jid, "text": data.get("text", "").strip(), "time": time.time(), "status": "open"}
                if n["text"]: notes.append(n); jsave(NOTES_P, notes)
            return self.send(n)
        if act == "looks":
            import looks
            st = jload(os.path.join(d, "status.json"), {}); plan = jload(pp, {})
            files = looks.render(os.path.join(FOLDER, st["name"]), d, plan.get("style", "b-loud"), plan, os.path.join(d, "transcript.json"))
            return self.send({"looks": [{"name": n, "img": os.path.relpath(f, FOLDER), "look": lk} for n, f, lk in files]})


if __name__ == "__main__":
    for t in (watcher, renderer): threading.Thread(target=t, daemon=True).start()
    print(f"Reubx Studio: http://localhost:{A.port}  (watching {FOLDER})", flush=True)
    ThreadingHTTPServer(("127.0.0.1", A.port), H).serve_forever()
