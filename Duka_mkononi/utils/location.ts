// 🌍 Shared location helpers — reverse geocoding via OpenStreetMap Nominatim.
// Free service, no API key required. Turns GPS coordinates into a human-readable
// area name (e.g. "Kariakoo, Dar es Salaam") so admins don't have to type it manually.

const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/reverse';

/**
 * Reverse-geocodes a GPS coordinate into a short, human-readable area name.
 * Returns '' on failure so callers can gracefully fall back.
 */
export const reverseGeocode = async (latitude: number, longitude: number): Promise<string> => {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 10000);
  try {
    const url = `${NOMINATIM_URL}?format=jsonv2&lat=${latitude}&lon=${longitude}&zoom=16&addressdetails=1`;
    const response = await fetch(url, {
      signal: controller.signal,
      headers: {
        'Accept': 'application/json',
        // Best-effort only: native React Native fetch may override this header.
        'User-Agent': 'DukaMkononi/1.0 (mobile app)',
      },
    });
    if (!response.ok) return '';

    const data = await response.json();
    const address = data?.address;
    if (!address) return '';

    // Build a compact, comma-separated area name from the most useful parts.
    const parts = [
      address.road || address.pedestrian || '',
      address.suburb || address.neighbourhood || address.quarter || '',
      address.city || address.town || address.village || address.municipality || '',
      address.state || address.county || '',
      address.country || '',
    ].filter(Boolean);

    // Dedupe (e.g. city and state can repeat "Dar es Salaam") and cap at 3 parts.
    const unique = [...new Set(parts)].slice(0, 3);
    return unique.join(', ');
  } catch (error) {
    console.warn('⚠️ Reverse geocoding failed:', error);
    return '';
  } finally {
    clearTimeout(timeout);
  }
};
