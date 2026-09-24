// =============================================
// GEMINI SERVICE
// Low-level, injectable wrapper around the Google Gemini REST API.
// The API key lives ONLY here / in process.env — never on the client.
// =============================================

const GEMINI_BASE_URL =
  process.env.GEMINI_BASE_URL ||
  'https://generativelanguage.googleapis.com/v1beta';

const GEMINI_MODEL = process.env.GEMINI_MODEL || 'gemini-2.5-flash';

function getApiKey() {
  const key = process.env.GEMINI_API_KEY;
  if (!key) {
    const error = new Error('GEMINI_API_KEY haijawekwa kwenye server');
    error.code = 'GEMINI_KEY_MISSING';
    throw error;
  }
  return key;
}

// Build an inline_data part from a base64 image (accepted formats: png/jpeg/webp/heic/heif/bmp/tiff/gif)
function imagePart(base64Data) {
  return {
    inlineData: {
      mimeType: detectMimeType(base64Data),
      data: base64Data,
    },
  };
}

function detectMimeType(base64Data) {
  // Lightweight signature sniffing on the decoded prefix; default to JPEG.
  const head = Buffer.from(base64Data || '', 'base64').subarray(0, 12);
  if (head.length >= 4) {
    const sig = head.toString('hex');
    // PNG: 89504e47
    if (sig.startsWith('89504e47')) return 'image/png';
    // WEBP: 52494646 .... 57454250
    if (sig.includes('57454250')) return 'image/webp';
    // GIF: 47494638
    if (sig.startsWith('47494638')) return 'image/gif';
    // BMP: 424d
    if (sig.startsWith('424d')) return 'image/bmp';
    // TIFF: 49492a00 or 4d4d002a
    if (sig.startsWith('49492a00') || sig.startsWith('4d4d002a')) return 'image/tiff';
    // HEIC/HEIF: ftypheic / ftypheif (after 4-byte box size)
    if (sig.includes('66747970')) return 'image/heic';
  }
  return 'image/jpeg';
}

function textPart(text) {
  return { text };
}

// Call generateContent with a system instruction + optional image/ text parts.
// Returns the full JSON payload from the API.
async function generateContent({ systemInstruction, parts, extra = {} }) {
  const key = getApiKey();
  const url = `${GEMINI_BASE_URL}/models/${encodeURIComponent(GEMINI_MODEL)}:generateContent?key=${encodeURIComponent(key)}`;

  const requestBody = {
    contents: [
      {
        role: 'user',
        parts,
      },
    ],
  };

  if (systemInstruction && typeof systemInstruction === 'string' && systemInstruction.trim()) {
    requestBody.systemInstruction = {
      parts: [{ text: systemInstruction }],
    };
  }

  // Structured output when available (keeps Gemini honest about JSON).
  if (extra.responseSchema) {
    requestBody.generationConfig = {
      responseMimeType: 'application/json',
      responseSchema: extra.responseSchema,
      temperature: extra.temperature !== undefined ? extra.temperature : 0.2,
      maxOutputTokens: extra.maxOutputTokens || 8192,
    };
  } else {
    requestBody.generationConfig = {
      responseMimeType: 'application/json',
      temperature: extra.temperature !== undefined ? extra.temperature : 0.2,
      maxOutputTokens: extra.maxOutputTokens || 8192,
    };
  }

  const controller = new AbortController();
  const timeoutMs = extra.timeoutMs || 120000;
  const timeout = setTimeout(() => controller.abort(), timeoutMs);

  let response;
  try {
    response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(requestBody),
      signal: controller.signal,
    });
  } catch (networkError) {
    clearTimeout(timeout);
    if (networkError.name === 'AbortError') {
      const err = new Error('Gemini timeout - server ilichukua muda mrefu kupata majibu');
      err.code = 'GEMINI_TIMEOUT';
      throw err;
    }
    const err = new Error('Network error calling Gemini: ' + networkError.message);
    err.code = 'GEMINI_NETWORK';
    throw err;
  }
  clearTimeout(timeout);

  if (!response.ok) {
    let detail = '';
    try {
      const body = await response.json();
      detail = body?.error?.message || JSON.stringify(body);
    } catch (_) {
      detail = await response.text().catch(() => '');
    }
    const err = new Error(`Gemini API error ${response.status}: ${detail}`);
    err.code = response.status === 429 ? 'GEMINI_RATE_LIMIT' : 'GEMINI_API_ERROR';
    err.status = response.status;
    throw err;
  }

  try {
    return await response.json();
  } catch (parseError) {
    const err = new Error('Gemini returned invalid payload: ' + parseError.message);
    err.code = 'GEMINI_BAD_RESPONSE';
    throw err;
  }
}

// Pull the concatenated text out of a generateContent response.
function extractTextFromResponse(payload) {
  if (!payload || !Array.isArray(payload.candidates) || payload.candidates.length === 0) {
    if (payload?.promptFeedback?.blockReason) {
      const err = new Error('Gemini blocked the request: ' + payload.promptFeedback.blockReason);
      err.code = 'GEMINI_BLOCKED';
      throw err;
    }
    const err = new Error('Gemini returned no candidates');
    err.code = 'GEMINI_EMPTY';
    throw err;
  }
  const parts = payload.candidates[0]?.content?.parts || [];
  return parts
    .map((p) => (typeof p.text === 'string' ? p.text : ''))
    .join('\n')
    .trim();
}

module.exports = {
  generateContent,
  extractTextFromResponse,
  imagePart,
  textPart,
  GEMINI_MODEL,
  GEMINI_BASE_URL,
};