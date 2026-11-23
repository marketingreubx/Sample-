/*
 * Keyword matching. Normalization approach (emoji stripping, diacritic folding,
 * Arabic-script folding, numeric homoglyphs, Unicode-aware whole-word match)
 * adapted from OpenReply (https://github.com/diwenne/openreply),
 * Copyright (c) 2026 Anish Raj, Copyright (c) 2026 Diwen Huang. MIT License.
 * See THIRD_PARTY_NOTICES.md.
 */

export interface KeywordMatch {
  matched: boolean;
  keyword: string | null;
}

const ARABIC_FOLDING: Array<[RegExp, string]> = [
  [/[يىے]/gu, "ی"],
  [/ك/gu, "ک"],
  [/ة/gu, "ه"],
  [/[آأإٱ]/gu, "ا"],
  [/[ً-ْٰ]/gu, ""],
  [/ـ/gu, ""],
  [/[‌‎‏]/gu, ""],
];

export function normalizeArabicScript(text: string): string {
  let out = text;
  for (const [re, rep] of ARABIC_FOLDING) out = out.replace(re, rep);
  return out.replace(/[۰-۹٠-٩]/gu, (d) => {
    const code = d.codePointAt(0)!;
    return String(code - (code >= 0x06f0 ? 0x06f0 : 0x0660));
  });
}

export function stripSpecialCharacters(text: string): string {
  return text
    .replace(/\p{Extended_Pictographic}|[\u{1F1E0}-\u{1F1FF}\u{FE00}-\u{FE0F}\u{200D}\u{20E3}]/gu, "")
    .replace(/[^\p{L}\p{N}\p{M}\s]/gu, " ")
    .replace(/\s+/g, " ")
    .trim();
}

/** Drops accents on Latin letters only (é -> e) so marks that change meaning in other scripts survive. */
export function foldDiacritics(text: string): string {
  let out = "";
  let baseIsLatin = false;
  for (const ch of text.normalize("NFD")) {
    if (/\p{M}/u.test(ch)) {
      if (!baseIsLatin) out += ch;
      continue;
    }
    baseIsLatin = /\p{Script=Latin}/u.test(ch);
    out += ch;
  }
  return out.normalize("NFC");
}

export function normalizeForMatch(text: string): string {
  return foldDiacritics(stripSpecialCharacters(normalizeArabicScript(text))).toLowerCase();
}

const escapeRe = (s: string) => s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

export function matchKeywords(text: string, keywords: string[], wholeWord = true): KeywordMatch {
  if (!text || !keywords.length) return { matched: false, keyword: null };
  const cleaned = normalizeForMatch(text);
  if (!cleaned) return { matched: false, keyword: null };

  for (const keyword of keywords) {
    const k = normalizeForMatch(keyword);
    if (!k) continue;
    // "1O0" style keywords: people type the letter O for zero.
    const numeric = /^[0-9o]+$/.test(k);
    const hay = numeric ? cleaned.replace(/o/g, "0") : cleaned;
    const needle = numeric ? k.replace(/o/g, "0") : k;
    const hit = wholeWord
      ? new RegExp(`(?<![\\p{L}\\p{N}])${escapeRe(needle)}(?![\\p{L}\\p{N}])`, "u").test(hay)
      : hay.includes(needle);
    if (hit) return { matched: true, keyword };
  }
  return { matched: false, keyword: null };
}
