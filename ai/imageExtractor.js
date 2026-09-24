// =============================================
// IMAGE / TEXT EXTRACTION LAYER
// This is the OCR-style layer between uploaded images and reasoning.
// It is intentionally isolated behind one function so the OCR provider
// (currently Gemini vision) can be swapped later without touching the
// rest of the pipeline.
// =============================================

const { generateContent, extractTextFromResponse, imagePart } = require('./geminiService');

const EXTRACTION_SYSTEM_INSTRUCTION = `You are an OCR / handwriting transcription engine for a business inventory mobile app called "Duka Mkononi".

Given one or more photos of inventory lists, order sheets, stock sheets or handwritten notes, your ONLY job is to transcribe the readable text EXACTLY as it appears — do NOT interpret, correct, fix spelling, add prices, guess missing data, or convert to a product catalog.

Rules:
1. Transcribe words, numbers, prices and quantities exactly as written.
2. Preserve layout as much as possible (columns separated by spaces/tabs, rows on separate lines).
3. If handwriting is hard to read, write down your best literal reading of the characters; do not "improve" the words.
4. Keep currency symbols such as TZS, Tsh, $ if present.
5. If you cannot read something, write [?] for the unreadable part.
6. Do NOT summarize or translate the text.
7. Return STRICT JSON only, with this exact shape:
   {
     "images": [
       { "image": "<image file name>", "extractedText": "..." }
     ]
   }`;

// Per-image transcript. Each image becomes one entry so Gemini can later
// reason about where information originated.
async function extractTextFromImages(images) {
  if (!Array.isArray(images) || images.length === 0) {
    throw Object.assign(new Error('Hakuna picha zilizotumwa'), { code: 'NO_IMAGES' });
  }

  const parts = images.map((img) => imagePart(img.base64));

  const promptText = [
    'Transcribe all readable text from the attached inventory images.',
    'There are ' + images.length + ' image(s) in this order: ' +
      images.map((img, i) => `${i + 1}. ${img.name || ('image' + (i + 1))}`).join(' | ') + '.',
    'For each image return its transcribed text under its exact file name.',
  ].join('\n');

  parts.push({ text: promptText });

  const payload = await generateContent({
    systemInstruction: EXTRACTION_SYSTEM_INSTRUCTION,
    parts,
    extra: { temperature: 0.1, maxOutputTokens: 8192 },
  });

  let raw = extractTextFromResponse(payload);
  let parsed = safelyParseJson(raw);

  // Repair: even if the JSON is malformed, keep the raw text so downstream
  // has something to work with. Best-effort recovery.
  if (!parsed || !Array.isArray(parsed.images)) {
    parsed = { images: images.map((img) => ({ image: img.name, extractedText: raw })) };
  }

  const out = [];
  for (const img of images) {
    const name = img.name;
    const entry = (parsed.images || []).find((e) => e && e.image === name);
    out.push({
      image: name,
      extractedText: entry?.extractedText || '',
    });
  }

  return { images: out };
}

// Minimal JSON extractor (handles markdown fences) — used for the extraction
// stage output; the main interpretation output is validated by responseValidator.
function safelyParseJson(text) {
  if (!text) return null;
  try {
    return JSON.parse(text);
  } catch (_) {}
  // Strip ```json ... ``` fences
  const fenced = text.match(/```(?:json)?\s*([\s\S]*?)```/);
  if (fenced) {
    try {
      return JSON.parse(fenced[1]);
    } catch (_) {}
  }
  // Grab the first balanced { ... } block
  const firstBrace = text.indexOf('{');
  const lastBrace = text.lastIndexOf('}');
  if (firstBrace !== -1 && lastBrace > firstBrace) {
    try {
      return JSON.parse(text.slice(firstBrace, lastBrace + 1));
    } catch (_) {}
  }
  return null;
}

module.exports = { extractTextFromImages, safelyParseJson };