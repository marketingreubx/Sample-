# Style A ("Calm Cards") example build — home-workout reel, 72.8s

Order: `base7.py` (framing plan: hard cuts that switch wide <-> tight 1.28x crop, frame-exact with `-frames:v`)
-> `face7.py` (face boxes on the finished base) -> `gfx7.py` (all text + cards as one HTML layer rendered with
Playwright; `items7.py` holds the design) -> `compose7.py` (video only, + 2.4s black end card)
-> `audio7.py` (separate audio pass, then mux with `-c:v copy`).
Depends on the v6 cut (`cuts.py`, `common6.py`, `voice_C.wav`) from the Style B build.
