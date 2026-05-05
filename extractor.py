"""
Video metadata and transcript extractor using yt-dlp.
Supports Instagram Reels and TikTok videos.
"""

import re
import subprocess
import json
import tempfile
import os
from dataclasses import dataclass, field
from typing import Optional


@dataclass
class VideoData:
    url: str
    platform: str
    title: str
    description: str
    uploader: str
    view_count: Optional[int]
    like_count: Optional[int]
    comment_count: Optional[int]
    share_count: Optional[int]
    duration: Optional[float]
    hashtags: list[str]
    transcript: Optional[str]
    thumbnail_url: Optional[str]
    upload_date: Optional[str]
    raw_metadata: dict = field(default_factory=dict)


def detect_platform(url: str) -> str:
    if "instagram.com" in url or "instagr.am" in url:
        return "instagram"
    if "tiktok.com" in url or "vm.tiktok.com" in url:
        return "tiktok"
    if "youtube.com" in url or "youtu.be" in url:
        return "youtube"
    raise ValueError(f"Unsupported platform. Only Instagram, TikTok, and YouTube URLs are supported. Got: {url}")


def extract_hashtags(text: str) -> list[str]:
    if not text:
        return []
    return re.findall(r"#\w+", text)


def extract_video_data(url: str) -> VideoData:
    platform = detect_platform(url)

    # Build attempt list — YouTube works fine without a spoofed UA;
    # Instagram/TikTok sometimes need the mobile UA as a fallback.
    base_cmd = ["yt-dlp", "--dump-json", "--no-download", "--no-warnings"]
    attempt_cmds = [base_cmd + [url]]
    if platform != "youtube":
        attempt_cmds.append(
            base_cmd + [
                "--user-agent",
                "Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) "
                "AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1",
                url,
            ]
        )

    # Try without cookies first, then with cookies if it fails
    result = None
    for attempt_cmd in attempt_cmds:
        try:
            proc = subprocess.run(
                attempt_cmd,
                capture_output=True,
                text=True,
                timeout=60,
            )
            if proc.returncode == 0 and proc.stdout.strip():
                result = proc.stdout.strip()
                break
        except subprocess.TimeoutExpired:
            continue

    if not result:
        raise RuntimeError(
            "Could not fetch video data. The video may be private, age-restricted, "
            "or the platform may require authentication. Try logging in via your browser first."
        )

    # yt-dlp may return multiple JSON lines; take the last valid one
    metadata = None
    for line in reversed(result.splitlines()):
        line = line.strip()
        if line.startswith("{"):
            try:
                metadata = json.loads(line)
                break
            except json.JSONDecodeError:
                continue

    if not metadata:
        raise RuntimeError("Failed to parse video metadata.")

    # Extract transcript from subtitles/captions if available
    transcript = _extract_transcript(metadata, url)

    # Combine description + caption fields (vary by platform)
    description = (
        metadata.get("description")
        or metadata.get("title")
        or ""
    )

    hashtags = extract_hashtags(description)
    # Also grab structured hashtags if yt-dlp provides them
    if metadata.get("tags"):
        for tag in metadata["tags"]:
            ht = f"#{tag}" if not tag.startswith("#") else tag
            if ht not in hashtags:
                hashtags.append(ht)

    return VideoData(
        url=url,
        platform=platform,
        title=metadata.get("title") or "",
        description=description,
        uploader=metadata.get("uploader") or metadata.get("channel") or "Unknown",
        view_count=metadata.get("view_count"),
        like_count=metadata.get("like_count"),
        comment_count=metadata.get("comment_count"),
        share_count=metadata.get("repost_count") or metadata.get("share_count"),
        duration=metadata.get("duration"),
        hashtags=hashtags,
        transcript=transcript,
        thumbnail_url=metadata.get("thumbnail"),
        upload_date=metadata.get("upload_date"),
        raw_metadata=metadata,
    )


def _extract_transcript(metadata: dict, url: str) -> Optional[str]:
    """Try to extract transcript from auto-generated captions."""
    # Check if subtitles are available in metadata
    subtitles = metadata.get("subtitles") or {}
    auto_captions = metadata.get("automatic_captions") or {}

    has_subs = bool(subtitles or auto_captions)
    if not has_subs:
        return None

    try:
        with tempfile.TemporaryDirectory() as tmpdir:
            cmd = [
                "yt-dlp",
                "--write-auto-subs",
                "--write-subs",
                "--sub-format", "vtt/best",
                "--skip-download",
                "--no-warnings",
                "-o", os.path.join(tmpdir, "video"),
                url,
            ]
            subprocess.run(cmd, capture_output=True, timeout=30)

            # Find any .vtt file written
            for fname in os.listdir(tmpdir):
                if fname.endswith(".vtt") or fname.endswith(".srt"):
                    fpath = os.path.join(tmpdir, fname)
                    with open(fpath, "r", encoding="utf-8", errors="ignore") as f:
                        raw = f.read()
                    return _clean_subtitle_text(raw)
    except Exception:
        pass

    return None


def _clean_subtitle_text(raw: str) -> str:
    """Strip VTT/SRT formatting tags and timestamps, return plain text."""
    # Remove WEBVTT header
    raw = re.sub(r"WEBVTT.*?\n\n", "", raw, flags=re.DOTALL)
    # Remove timestamps like 00:00:01.000 --> 00:00:03.000
    raw = re.sub(r"\d{2}:\d{2}:\d{2}[.,]\d{3}\s*-->\s*\d{2}:\d{2}:\d{2}[.,]\d{3}[^\n]*", "", raw)
    # Remove cue identifiers (numbers or IDs)
    raw = re.sub(r"^\d+\s*$", "", raw, flags=re.MULTILINE)
    # Remove HTML-like tags
    raw = re.sub(r"<[^>]+>", "", raw)
    # Collapse whitespace
    lines = [l.strip() for l in raw.splitlines() if l.strip()]
    # Deduplicate consecutive identical lines (common in auto-captions)
    deduped = []
    for line in lines:
        if not deduped or line != deduped[-1]:
            deduped.append(line)
    return " ".join(deduped).strip()
