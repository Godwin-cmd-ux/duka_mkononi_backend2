// =============================================
// PROMPT BUILDER
// Isolated, controlled prompt template for the Gemini interpretation step.
// Four categories of input: ROLE/TASK instructions, business context,
// existing inventory, and extracted text.
// =============================================

const BUSINESS_TYPES = {
  spare_parts: 'Spare Parts',
  pharmacy: 'Pharmacy',
  supermarket: 'Supermarket',
  clothing: 'Clothing',
  electronics: 'Electronics',
  restaurant: 'Restaurant',
  hardware: 'Hardware',
  cosmetics: 'Cosmetics',
  perfume: 'Perfume',
  mobile_accessories: 'Mobile Accessories',
  furniture: 'Furniture',
  stationery: 'Stationery',
  agriculture: 'Agriculture',
  construction_materials: 'Construction Materials',
  beauty_salon: 'Beauty Salon',
  barbershop: 'Barbershop',
  auto_repair: 'Auto Repair',
  phone_shop: 'Phone Shop',
  computer_shop: 'Computer Shop',
  general_retail: 'General Retail',
  wholesale: 'Wholesale',
  other: 'Other',
};

function businessTypeLabel(type) {
  if (!type) return 'Not specified';
  return BUSINESS_TYPES[type] || type;
}

// Serialize existing products as a compact controlled representation.
function serializeExistingItems(items) {
  if (!Array.isArray(items) || items.length === 0) {
    return '[] (no existing products found for this business)';
  }
  return JSON.stringify(
    items.map((p) => ({
      id: String(p.id),
      name: p.name,
      sellingPrice: p.expected_selling_price || p.price || null,
      buyingPrice: p.cost_price ?? null,
      quantity: p.stock ?? null,
      category: p.category || null,
    })),
    null,
    2
  );
}

// Serialize extracted text blocks, preserving image boundaries.
function serializeExtractedText(extracted) {
  const images = Array.isArray(extracted) && extracted.length > 0
    ? extracted
    : [{ image: 'image1', extractedText: extracted }];
  return JSON.stringify(
    images.map((img) => ({ image: img.image, extractedText: img.extractedText || '' })),
    null,
    2
  );
}

function buildSystemInstruction() {
  return `You are an inventory interpretation assistant for the Duka Mkononi business app.

TASK
Interpret inventory information that was extracted (via OCR) from one or more photos of paper lists, order sheets, screenshots or handwritten notes. Produce a clean, structured inventory interpretation.

CRITICAL HONESTY RULES
1. Never invent quantities or prices. If a value is missing or unreadable, return null.
2. Never invent a product that is not supported by the input.
3. If a term is ambiguous, use needsReview=true and keep your best literal guess rather than guessing confidently.
4. Use the business context ONLY to resolve plausible OCR/handwriting errors. Do not blindly rewrite words.
5. Correct obvious OCR mistakes when the context strongly supports it (e.g. "Radiatr" -> "Radiator" for a spare-parts business).
6. Live with uncertainty: an uncertain reading still maps to needsReview=true.
7. Normalize prices to plain numbers (e.g. "150,000", "150 000", "Tsh 150000", "150k" -> 150000). Keep the same currency.
8. Never combine two different products merely because their names look similar. Preserve meaningful product distinctions (brand, model, size, variant).
9. Do not invent a category when the input gives no clue; use a sensible business-appropriate default if the item is clearly part of the business's trade.
10. Respond with STRICT JSON only. No markdown fences, no commentary.

EXISTING vs NEW
- Compare every extracted item against the EXISTING INVENTORY list below.
- If an item clearly corresponds to an existing product: status="EXISTING", matchedExistingItemId=that product's id.
- Otherwise: status="NEW", matchedExistingItemId=null.
- A near match with medium confidence is still EXISTING but with a lower confidence and needsReview=true when you are not sure.
- Never return a matchedExistingItemId that does not appear in the existing inventory.

DUPLICATES ACROSS IMAGES
- If the same item appears on more than one image, return it as ONE row and SUM the quantities into that row (do not create two rows with the same matched id / same normalized name).
- If two rows look like they MIGHT be duplicates but you are not sure, keep them separate and set needsReview=true with a warning in the "warnings" array.

CONFIDENCE
- confidence is a number 0..1 reflecting how certain you are the interpretation (name + values) is correct.
- Strong readable data = high confidence (>= 0.90). Uncertain handwriting/OCR = lower.

OUTPUT SCHEMA (strict)
Return exactly:
{
  "items": [
    {
      "name": "...",
      "category": "...",
      "description": "...",
      "quantity": <number|null>,
      "buyingPrice": <number|null>,
      "sellingPrice": <number|null>,
      "status": "EXISTING" | "NEW",
      "matchedExistingItemId": <existing id string|null>,
      "confidence": <0..1>,
      "needsReview": <boolean>,
      "sourceImage": "<image file name>",
      "warnings": ["..."]
    }
  ]
}`;
}

function buildUserPrompt({ businessType, businessDescription, existingItems, extractedSections }) {
  return [
    'BUSINESS CONTEXT',
    'Business type: ' + businessTypeLabel(businessType),
    'Business description: ' + (businessDescription || '(not provided — rely on the product names and common trade terminology)'),
    '',
    'EXISTING INVENTORY (only this business\'s products — treat these ids as references, do NOT create new rows for them)',
    serializeExistingItems(existingItems),
    '',
    'EXTRACTED TEXT (grouped by source image — use image boundaries when reasoning)',
    serializeExtractedText(extractedSections),
    '',
    'INSTRUCTIONS',
    '1. Interpret the extracted inventory into individual line items.',
    '2. Correct obvious OCR/spelling errors only when the business context supports it.',
    '3. Carefully interpret handwriting; when in doubt mark needsReview=true.',
    '4. Use the business type and description as semantic context for ambiguous words.',
    '5. Compare each item to the existing inventory and decide EXISTING or NEW.',
    '6. If EXISTING, set matchedExistingItemId to the matching candidate id.',
    '7. Never return a matchedExistingItemId that is not in the provided list.',
    '8. Never invent missing quantities or prices — use null.',
    '9. Mark uncertain records with needsReview=true and a short warning.',
    '10. Merge the same item appearing on multiple images into one row, summing quantities.',
    '11. Normalize item names (spacing, capitalization) but preserve product distinctions.',
    '12. Return ONLY the JSON object described in your instructions.',
  ].join('\n');
}

module.exports = { buildSystemInstruction, buildUserPrompt, businessTypeLabel };