# Viral Video Analyzer

Paste an Instagram Reel or TikTok link, enter your content niche, and get:

- **Script & Structure** — hook breakdown, body sections, CTA/ending
- **Virality Factor Scores** — scored 1–10 with explanations
- **Why It Went Viral** — emotional triggers, algorithm signals, psychology
- **Content DNA** — format, pacing, tone, production level
- **10 Content Ideas** — tailored to your niche, each adapting a viral element

## Setup

### 1. Install dependencies

```bash
pip install -r requirements.txt
```

Also install `yt-dlp` (used for video metadata extraction):
```bash
pip install yt-dlp
```

### 2. Configure environment

```bash
cp .env.example .env
# Edit .env and add your ANTHROPIC_API_KEY
```

### 3. Run

```bash
python -m uvicorn app:app --reload --port 8000
```

Open `http://localhost:8000` in your browser.

## How It Works

1. **Paste a link** — Instagram Reel or TikTok URL
2. **Enter your niche** — e.g. "fitness for busy moms", "personal finance Gen Z"
3. **Click Analyze** — the app:
   - Uses `yt-dlp` to extract metadata, stats, hashtags, and captions
   - Sends everything to Claude (claude-opus-4-6) for deep analysis
   - Returns structured JSON rendered in the UI

## Notes

- **Private videos** cannot be analyzed (no authentication support)
- **Transcripts** are extracted from auto-generated captions when available
- **Analysis quality** improves when captions are available; metadata-only analysis still works well
- Some videos may require you to be logged in via your browser for `yt-dlp` to access them

## Requirements

- Python 3.11+
- Anthropic API key
- `yt-dlp` installed
