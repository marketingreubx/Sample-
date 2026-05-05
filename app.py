"""
Viral Video Analyzer — FastAPI backend
Analyzes Instagram Reels, TikTok, and YouTube videos for script, structure,
virality factors, generates 10 niche content ideas, and writes full scripts.
"""

import os
from dotenv import load_dotenv
load_dotenv()

from fastapi import FastAPI, HTTPException, Request
from fastapi.responses import HTMLResponse, JSONResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from pydantic import BaseModel, field_validator

from extractor import extract_video_data, detect_platform
from analyzer import analyze_video, generate_script

app = FastAPI(
    title="Viral Video Analyzer",
    description="Analyze Instagram Reels, TikTok & YouTube videos to extract scripts, virality factors, content ideas, and write full scripts.",
    version="2.0.0",
)

templates = Jinja2Templates(directory="templates")


# ── Request / Response models ────────────────────────────────────────────────

class AnalyzeRequest(BaseModel):
    url: str
    niche: str

    @field_validator("url")
    @classmethod
    def validate_url(cls, v: str) -> str:
        v = v.strip()
        if not (v.startswith("http://") or v.startswith("https://")):
            raise ValueError("URL must start with http:// or https://")
        supported = (
            "instagram.com", "instagr.am",
            "tiktok.com", "vm.tiktok.com",
            "youtube.com", "youtu.be",
        )
        if not any(s in v for s in supported):
            raise ValueError("Only Instagram, TikTok, and YouTube URLs are supported.")
        return v

    @field_validator("niche")
    @classmethod
    def validate_niche(cls, v: str) -> str:
        v = v.strip()
        if not v:
            raise ValueError("Niche cannot be empty.")
        if len(v) > 200:
            raise ValueError("Niche description too long (max 200 chars).")
        return v


class ScriptRequest(BaseModel):
    niche: str
    idea_title: str
    idea_hook: str
    idea_format: str
    idea_structure: str
    viral_structure: dict
    content_dna: dict
    video_summary: str

    @field_validator("niche", "idea_title")
    @classmethod
    def not_empty(cls, v: str) -> str:
        v = v.strip()
        if not v:
            raise ValueError("Field cannot be empty.")
        return v


# ── Routes ───────────────────────────────────────────────────────────────────

@app.get("/", response_class=HTMLResponse)
async def index(request: Request):
    return templates.TemplateResponse("index.html", {"request": request})


@app.post("/analyze")
async def analyze(body: AnalyzeRequest):
    try:
        video_data = extract_video_data(body.url)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except RuntimeError as e:
        raise HTTPException(status_code=422, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Video extraction failed: {str(e)}")

    try:
        analysis = analyze_video(video_data, body.niche)
    except EnvironmentError as e:
        raise HTTPException(status_code=500, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Analysis failed: {str(e)}")

    return JSONResponse(content={
        "platform": video_data.platform,
        "uploader": video_data.uploader,
        "title": video_data.title,
        "stats": {
            "views": video_data.view_count,
            "likes": video_data.like_count,
            "comments": video_data.comment_count,
            "shares": video_data.share_count,
            "duration_seconds": video_data.duration,
        },
        "thumbnail": video_data.thumbnail_url,
        "hashtags": video_data.hashtags[:15],
        "analysis": analysis,
    })


@app.post("/generate-script")
async def generate_script_endpoint(body: ScriptRequest):
    try:
        script = generate_script(body.model_dump())
    except EnvironmentError as e:
        raise HTTPException(status_code=500, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Script generation failed: {str(e)}")

    return JSONResponse(content={"script": script})


@app.get("/health")
async def health():
    return {"status": "ok"}
