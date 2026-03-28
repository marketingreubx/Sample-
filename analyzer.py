"""
Claude-powered viral video analyzer.
Produces script breakdown, virality analysis, and 10 content ideas.
"""

import os
import re
import json
import anthropic
from extractor import VideoData


def _format_stats(data: VideoData) -> str:
    parts = []
    if data.view_count is not None:
        parts.append(f"{data.view_count:,} views")
    if data.like_count is not None:
        parts.append(f"{data.like_count:,} likes")
    if data.comment_count is not None:
        parts.append(f"{data.comment_count:,} comments")
    if data.share_count is not None:
        parts.append(f"{data.share_count:,} shares/reposts")
    if data.duration is not None:
        mins, secs = divmod(int(data.duration), 60)
        parts.append(f"{mins}m {secs}s duration" if mins else f"{secs}s duration")
    return " | ".join(parts) if parts else "Stats unavailable"


def _build_analysis_prompt(data: VideoData, niche: str) -> str:
    hashtag_str = " ".join(data.hashtags[:20]) if data.hashtags else "None detected"
    transcript_section = (
        f"\n\nTRANSCRIPT / CAPTIONS:\n{data.transcript[:3000]}"
        if data.transcript
        else "\n\n(No transcript available — analyze based on metadata, description, and hashtags)"
    )

    return f"""You are an expert viral content strategist and social media analyst with deep knowledge of {data.platform.title()} algorithm behavior, content psychology, and short-form video trends.

Analyze the following {data.platform.title()} video and provide a comprehensive breakdown.

---
PLATFORM: {data.platform.title()}
CREATOR: {data.uploader}
TITLE/CAPTION: {data.title}
DESCRIPTION: {data.description[:1000] if data.description else "N/A"}
STATS: {_format_stats(data)}
HASHTAGS: {hashtag_str}
UPLOAD DATE: {data.upload_date or "Unknown"}
TARGET NICHE FOR NEW IDEAS: {niche}
{transcript_section}
---

Provide your analysis in the following JSON structure. Be specific, actionable, and data-driven:

{{
  "video_summary": "2-3 sentence overview of what this video is about",

  "script_structure": {{
    "hook": {{
      "timestamp": "0-3s",
      "technique": "Name of hook technique used",
      "description": "What exactly happens in the hook and why it grabs attention"
    }},
    "body": [
      {{
        "section": "Section name (e.g., Problem Setup, Story, Demo, etc.)",
        "timestamp": "approximate time range",
        "content": "What happens and what narrative/emotional function it serves"
      }}
    ],
    "cta_or_ending": {{
      "timestamp": "last few seconds",
      "technique": "Type of ending/CTA used",
      "description": "How it ends and what it compels viewers to do"
    }}
  }},

  "virality_factors": [
    {{
      "factor": "Factor name",
      "score": 1-10,
      "explanation": "Why this specific element contributed to virality"
    }}
  ],

  "why_it_went_viral": {{
    "primary_reason": "The single most important reason",
    "emotional_trigger": "Core emotion it activates (curiosity, awe, humor, outrage, inspiration, etc.)",
    "algorithm_advantages": ["List of specific algorithm signals this video likely triggered"],
    "audience_psychology": "How this leverages human psychology/social behavior",
    "timing_or_trend": "Any trend, cultural moment, or timing advantage"
  }},

  "content_dna": {{
    "format": "Video format name (talking head, POV, tutorial, reaction, etc.)",
    "pacing": "slow/medium/fast/variable",
    "tone": "Primary tone (educational, entertaining, inspirational, controversial, etc.)",
    "production_level": "minimal/moderate/high",
    "key_elements": ["Visual or audio elements core to its success"]
  }},

  "content_ideas": [
    {{
      "idea_number": 1,
      "title": "Catchy working title",
      "hook": "Opening line or visual hook (first 3 seconds)",
      "format": "Video format to use",
      "structure": "Brief outline: intro → body → ending",
      "why_it_will_work": "Specific reason this will perform well for the {niche} niche",
      "viral_element_borrowed": "Which element from the original video you're adapting"
    }}
  ]
}}

Generate exactly 10 content ideas in the content_ideas array, all tailored specifically to the [{niche}] niche.
Each idea must borrow and adapt at least one specific viral element from the analyzed video but apply it freshly.
Return ONLY the JSON object, no markdown fences, no extra text."""


def analyze_video(data: VideoData, niche: str) -> dict:
    api_key = os.getenv("ANTHROPIC_API_KEY")
    if not api_key:
        raise EnvironmentError("ANTHROPIC_API_KEY environment variable is not set.")

    client = anthropic.Anthropic(api_key=api_key)

    prompt = _build_analysis_prompt(data, niche)

    message = client.messages.create(
        model="claude-opus-4-6",
        max_tokens=4096,
        messages=[{"role": "user", "content": prompt}],
    )

    raw = message.content[0].text.strip()

    # Strip markdown fences if Claude wraps anyway
    if raw.startswith("```"):
        raw = re.sub(r"^```[a-z]*\n?", "", raw)
        raw = re.sub(r"\n?```$", "", raw)

    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        # Return structured error with raw response for debugging
        return {
            "error": "Failed to parse Claude response as JSON",
            "raw_response": raw,
        }
