// =============================================
// RESPONSE VALIDATOR
// Never trust Gemini's raw output. Parse, sanitise, coerce and drop
// malformed entries before anything is sent to the client or the DB.
// =============================================

const MAX_ITEMS = 500;

function parseNullableNumber(value) {
  if (value === null || value === undefined || value === '') return null;
  const str = String(value).replace(/[,\s]/g, '');
  const cleaned = str.replace(/[^\d.\-]/g, '');
  if (cleaned === '' || cleaned === '-' || cleaned === '.') return null;
  const num = Number(cleaned);
  return Number.isFinite(num) ? num : null;
}

function cleanItemName(raw) {
  if (typeof raw !== 'string') return '';
  return raw
    .replace(/\s+/g, ' ')
    .replace(/^\s+|\s+$/g, '')
    .slice(0, 200);
}

// Remove markdown fences / trim to the JSON payload embedded in the text.
function stripMarkdownJson(text) {
  if (typeof text !== 'string') return text;
  const fenced = text.match(/```(?:json)?\s*([\s\S]*?)```/);
  if (fenced) return fenced[1].trim();
  const firstBrace = text.indexOf('{');
  const lastBrace = text.lastIndexOf('}');
  if (firstBrace !== -1 && lastBrace > firstBrace) {
    return text.slice(firstBrace, lastBrace + 1);
  }
  return text;
}

function safeParse(text) {
  // Prefer the raw text verbatim — it may already be valid JSON (incl. a bare array).
  try {
    return JSON.parse(text);
  } catch (_) {}

  let candidate = text;
  if (typeof candidate === 'string') candidate = stripMarkdownJson(candidate);
  try {
    return JSON.parse(candidate);
  } catch (_) {
    return null;
  }
}

function validateItem(raw, index) {
  if (!raw || typeof raw !== 'object') return null;

  const name = cleanItemName(raw.name);
  if (!name) return null; // A row without a name is unusable — drop it.

  const quantity = parseNullableNumber(raw.quantity);
  const buyingPrice = parseNullableNumber(raw.buyingPrice);
  const sellingPrice = parseNullableNumber(raw.sellingPrice);
  const price = raw.price !== undefined ? parseNullableNumber(raw.price) : sellingPrice;

  const rawStatus = String(raw.status || 'NEW').toUpperCase();
  const status = rawStatus === 'EXISTING' ? 'EXISTING' : 'NEW';

  let matchedExistingItemId = null;
  if (raw.matchedExistingItemId !== null && raw.matchedExistingItemId !== undefined && raw.matchedExistingItemId !== '') {
    matchedExistingItemId = String(raw.matchedExistingItemId);
  }

  let confidence = parseNullableNumber(raw.confidence);
  if (confidence === null) confidence = 0.5;
  confidence = Math.max(0, Math.min(1, confidence));

  const needsReview = raw.needsReview === true || raw.needsReview === 'true';

  const category = typeof raw.category === 'string' && raw.category.trim()
    ? raw.category.trim().slice(0, 100)
    : '';

  const description = typeof raw.description === 'string' && raw.description.trim()
    ? raw.description.trim().slice(0, 500)
    : '';

  const warnings = Array.isArray(raw.warnings)
    ? raw.warnings.map((w) => String(w).slice(0, 300))
    : [];

  // A status of EXISTING without an id is contradictory — repair it to NEW
  // (never let a dangling id slip through to the verification step).
  if (status === 'EXISTING' && !matchedExistingItemId) {
    return {
      name,
      category,
      description,
      quantity,
      buyingPrice,
      sellingPrice,
      price,
      status: 'NEW',
      matchedExistingItemId: null,
      confidence,
      needsReview: true,
      sourceImage: String(raw.sourceImage || ''),
      warnings: [...warnings, 'Gemini marked the item as existing but gave no matching product id — treated as a new item for review.'],
    };
  }

  if (status === 'NEW') matchedExistingItemId = null;

  return {
    name,
    category,
    description,
    quantity,
    buyingPrice,
    sellingPrice,
    price,
    status,
    matchedExistingItemId,
    confidence,
    needsReview,
    sourceImage: String(raw.sourceImage || ''),
    warnings,
  };
}

// Full validation pipeline: parse → validate each row → drop unusable rows.
function validateAndRepair(rawText) {
  const parsed = safeParse(rawText);
  if (!parsed) {
    return { valid: false, items: [], raw: typeof rawText === 'string' ? rawText.slice(0, 2000) : '' };
  }

  let rows = Array.isArray(parsed) ? parsed : (Array.isArray(parsed.items) ? parsed.items : null);

  if (!rows) {
    return { valid: false, items: [], raw: typeof rawText === 'string' ? rawText.slice(0, 2000) : '' };
  }

  const items = [];
  for (let i = 0; i < rows.length && items.length < MAX_ITEMS; i++) {
    const cleaned = validateItem(rows[i], i);
    if (cleaned) items.push(cleaned);
  }

  return { valid: true, items };
}

module.exports = { validateAndRepair, stripMarkdownJson, parseNullableNumber };