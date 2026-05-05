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


def _build_script_prompt(analysis: dict, selected_idea: dict, niche: str, platform: str) -> str:
    script_structure = analysis.get("script_structure", {})
    hook_info = script_structure.get("hook", {})
    body_info = script_structure.get("body", [])
    cta_info = script_structure.get("cta_or_ending", {})
    dna = analysis.get("content_dna", {})
    viral = analysis.get("why_it_went_viral", {})

    structure_summary = (
        f"Hook technique: {hook_info.get('technique', 'N/A')}\n"
        f"Body sections: {', '.join(s.get('section', '') for s in body_info)}\n"
        f"Ending type: {cta_info.get('technique', 'N/A')}\n"
        f"Format: {dna.get('format', 'N/A')} | Pacing: {dna.get('pacing', 'N/A')} | Tone: {dna.get('tone', 'N/A')}\n"
        f"Emotional trigger: {viral.get('emotional_trigger', 'N/A')}"
    )

    idea_summary = (
        f"Title: {selected_idea.get('title', '')}\n"
        f"Hook: {selected_idea.get('hook', '')}\n"
        f"Format: {selected_idea.get('format', '')}\n"
        f"Structure outline: {selected_idea.get('structure', '')}\n"
        f"Why it will work: {selected_idea.get('why_it_will_work', '')}"
    )

    platform_note = {
        "tiktok": "TikTok (15s–3min, punchy cuts, trending audio cues, direct-to-camera)",
        "instagram": "Instagram Reels (up to 90s, visually polished, caption-forward)",
        "youtube": "YouTube (can be 3–20min for standard, or 60s for Shorts — match the outline's format)",
    }.get(platform, platform)

    return f"""You are a professional short-form video scriptwriter who specialises in viral {platform_note} content.

Using the viral structure extracted from the reference video and the content idea below, write a complete, ready-to-record video script for the [{niche}] niche.

---
VIRAL STRUCTURE FROM REFERENCE VIDEO:
{structure_summary}

CONTENT IDEA TO SCRIPT:
{idea_summary}
---

Write the script following this exact format as a JSON object:

{{
  "title": "Final video title",
  "estimated_duration": "e.g. 45s or 3min",
  "platform_notes": "Specific tips for recording/posting on {platform}",

  "script": [
    {{
      "segment": "HOOK",
      "timestamp": "0-3s",
      "on_screen_text": "Text overlay or caption (if any)",
      "voiceover_or_dialogue": "Exact words to say out loud",
      "visual_direction": "What to show / how to frame the shot",
      "notes": "Any director/creator notes"
    }},
    {{
      "segment": "BODY — [section name]",
      "timestamp": "...",
      "on_screen_text": "...",
      "voiceover_or_dialogue": "...",
      "visual_direction": "...",
      "notes": "..."
    }}
  ],

  "b_roll_list": ["List of specific B-roll shots or visuals needed"],
  "audio_suggestions": ["Background music mood or specific sound suggestion"],
  "caption_or_description": "Full post caption with hashtags (ready to copy-paste)",
  "content_gaps_exploited": "What gap or underserved angle this fills in the {niche} niche"
}}

Rules:
- Include a HOOK segment, 2-4 BODY segments, and a CTA/ENDING segment in the script array.
- The voiceover_or_dialogue must be the EXACT words to say — no placeholders.
- Mirror the pacing and emotional arc of the reference video's viral structure.
- Make the hook impossible to scroll past.
- Return ONLY the JSON object, no markdown fences, no extra text."""


def generate_script(analysis: dict, selected_idea: dict, niche: str, platform: str) -> dict:
    api_key = os.getenv("ANTHROPIC_API_KEY")
    if not api_key:
        raise EnvironmentError("ANTHROPIC_API_KEY environment variable is not set.")

    client = anthropic.Anthropic(api_key=api_key)
    prompt = _build_script_prompt(analysis, selected_idea, niche, platform)

    message = client.messages.create(
        model="claude-opus-4-6",
        max_tokens=4096,
        messages=[{"role": "user", "content": prompt}],
    )

    raw = message.content[0].text.strip()
    if raw.startswith("```"):
        raw = re.sub(r"^```[a-z]*\n?", "", raw)
        raw = re.sub(r"\n?```$", "", raw)

    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        return {"error": "Failed to parse script response", "raw_response": raw}


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
