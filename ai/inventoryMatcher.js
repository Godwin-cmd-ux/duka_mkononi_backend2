// =============================================
// INVENTORY MATCHER
// Picks the relevant existing products for a business to send to Gemini.
// Scalable: for big inventories we first derive candidate tokens from the
// OCR text and search the DB instead of shipping the entire catalogue.
// Every product returned here belongs to the authenticated business.
// =============================================

const MAX_CANDIDATES = 400;
const SMALL_CATALOGUE_LIMIT = 200;

function tokenize(text) {
  if (!text) return [];
  const words = text
    .toLowerCase()
    .replace(/[^a-z0-9\u0600-\u06FF\u0900-\u097F\u4e00-\u9fff\s]/g, ' ')
    .split(/\s+/)
    .filter((w) => w.length >= 3);
  const seen = new Set();
  const unique = [];
  for (const w of words) {
    if (!seen.has(w)) {
      seen.add(w);
      unique.push(w);
    }
  }
  return unique;
}

// Read the full active product set that belongs to the given sellerIds.
async function fetchBusinessCatalogue(supabase, sellerIds) {
  const { data, error } = await supabase
    .from('products')
    .select('id, name, category, description, price, expected_selling_price, cost_price, stock, seller_id')
    .in('seller_id', sellerIds)
    .eq('is_active', true)
    .order('name', { ascending: true })
    .limit(20000);

  if (error) throw error;
  return data || [];
}

// Candidate search using the most distinctive OCR tokens.
async function searchCandidates(supabase, sellerIds, tokens) {
  if (!tokens || tokens.length === 0) return [];
  const like = tokens.slice(0, 10).map((tok) => `%${tok}%`);
  const { data, error } = await supabase
    .from('products')
    .select('id, name, category, description, price, expected_selling_price, cost_price, stock, seller_id')
    .in('seller_id', sellerIds)
    .eq('is_active', true)
    .or(like.map((l) => `name.ilike.${escapeOr(l)}`).join(','))
    .limit(MAX_CANDIDATES);

  if (error) {
    console.warn('⚠️ Candidate token search failed, falling back to full catalogue:', error.message);
    return null;
  }
  return data || [];
}

// Escape a LIKE pattern value for Supabase .or(...) filter syntax.
function escapeOr(value) {
  // Supabase .or() takes url-encoded values.
  return encodeURIComponent(value);
}

async function findExistingProducts(supabase, sellerIds, extractedSections) {
  if (!sellerIds || sellerIds.length === 0) return [];

  // Cheap full-name candidate search first when the catalogue is small.
  const catalogue = await fetchBusinessCatalogue(supabase, sellerIds);

  if (catalogue.length <= SMALL_CATALOGUE_LIMIT) {
    // Keep every product — small businesses deserve full accuracy.
    return catalogue.slice(0, SMALL_CATALOGUE_LIMIT);
  }

  // Large catalogue: derive tokens from the OCR text and search candidates.
  const allText = (Array.isArray(extractedSections) ? extractedSections : [])
    .map((s) => (s && s.extractedText) || '')
    .join(' ');
  const tokens = tokenize(allText);

  const candidates = await searchCandidates(supabase, sellerIds, tokens);
  if (candidates !== null && candidates.length > 0) {
    return candidates.slice(0, MAX_CANDIDATES);
  }

  // If nothing matched (e.g. empty OCR), fall back to a deterministic sample
  // so Gemini still has reference points without shipping tens of thousands.
  return catalogue.slice(0, 100);
}

// Enrich each candidate with a normalized display map used by the interpreter.
function presentAsGeminiList(products) {
  return products.map((p) => ({
    id: String(p.id),
    name: p.name,
    category: p.category || null,
    expected_selling_price: p.expected_selling_price || p.price || null,
    cost_price: p.cost_price ?? null,
    price: p.price ?? null,
    stock: p.stock ?? null,
  }));
}

module.exports = {
  findExistingProducts,
  presentAsGeminiList,
  tokenize,
  MAX_CANDIDATES,
  SMALL_CATALOGUE_LIMIT,
};