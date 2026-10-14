<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Capability #24 - the OPTIONAL embeddings side of search.
 *
 * WHY THIS IS OFF BY DEFAULT AND WHY THAT IS THE HONEST CHOICE
 * -----------------------------------------------------------
 * The catalogue says to use embeddings "when justified by the actual
 * Supabase/PostgreSQL setup". The actual setup here has:
 *   * no `pgvector` extension installed (no vector column, no ANN index),
 *   * no embedding column and no embedding table,
 *   * no local embedding model (and one must never be shipped to the app).
 * So the justified default is the deterministic lexical hybrid in
 * ProductSearch, which always works. This adapter is the extension point: when
 * an operator enables `ai.search.embeddings.enabled` AND provides the provider
 * key AND applies the `ai_product_embeddings` migration, the query is embedded
 * server-side (the key never leaves the server) and blended with the lexical
 * score. Until then it returns null and search stays lexical - it never
 * silently degrades to an empty result set.
 *
 * PRIVACY / SCOPE
 * ---------------
 * Only PUBLIC catalogue fields (name, category, description) and the visitor's
 * own query text are ever sent. Control characters are stripped and the input
 * is length-bounded first, so a paste cannot smuggle a large blob out. No user
 * id, email, phone or token is ever embedded.
 *
 * VERSIONING
 * ----------
 * Every stored vector carries `version` (config) so changing the model can be
 * backfilled without mixing two embedding spaces; a query is compared only
 * against vectors of the CURRENT version.
 */
class Embeddings
{
    /** Hard ceiling on the text sent to the provider. */
    private const MAX_INPUT_CHARS = 2000;

    /** @return array<string, mixed> */
    public static function config(): array
    {
        return (array) config('ai.search.embeddings', []);
    }

    public static function isEnabled(): bool
    {
        return (bool) (self::config()['enabled'] ?? false);
    }

    public static function model(): string
    {
        $model = trim((string) (self::config()['model'] ?? 'text-embedding-004'));

        return $model !== '' ? $model : 'text-embedding-004';
    }

    public static function dimensions(): int
    {
        return max(1, (int) (self::config()['dimensions'] ?? 768));
    }

    public static function version(): string
    {
        $version = trim((string) (self::config()['version'] ?? 'v1'));

        return $version !== '' ? $version : 'v1';
    }

    public static function table(): string
    {
        $table = trim((string) (self::config()['table'] ?? 'ai_product_embeddings'));
        // Only a simple identifier may reach the data layer.
        return preg_match('/^[a-z0-9_]+$/', $table) === 1 ? $table : 'ai_product_embeddings';
    }

    /** Enabled AND a provider key is present. */
    public static function isConfigured(): bool
    {
        return self::isEnabled() && trim((string) config('ai.api_key')) !== '';
    }

    /**
     * Embed one piece of text. Returns the vector, or null when embeddings are
     * off, unconfigured, or the provider fails - callers MUST treat null as
     * "stay lexical", never as "no results".
     *
     * @return array<int, float>|null
     */
    public static function embed(string $text): ?array
    {
        if (! self::isConfigured()) {
            return null;
        }

        $clean = self::clean($text);
        if ($clean === '') {
            return null;
        }

        try {
            $baseUrl = rtrim((string) config('ai.base_url'), '/');
            $timeout = max(1, (int) (self::config()['timeout'] ?? 10));

            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post($baseUrl.'/models/'.rawurlencode(self::model()).':embedContent?key='.rawurlencode((string) config('ai.api_key')), [
                    'model' => 'models/'.self::model(),
                    'content' => ['parts' => [['text' => $clean]]],
                ]);

            if (! $response->successful()) {
                return null;
            }

            $values = $response->json('embedding.values');
            if (! is_array($values) || $values === []) {
                return null;
            }

            return array_map(static fn ($value): float => (float) $value, array_values($values));
        } catch (Throwable $e) {
            // Search must never break because the optional side is unreachable.
            return null;
        }
    }

    /**
     * Cosine similarity of two vectors. Length-independent and safe on
     * mismatched/zero vectors (returns 0.0 rather than dividing by zero).
     *
     * @param  array<int, float|int>  $a
     * @param  array<int, float|int>  $b
     */
    public static function cosine(array $a, array $b): float
    {
        $count = min(count($a), count($b));
        if ($count === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $x = (float) $a[$i];
            $y = (float) $b[$i];
            $dot += $x * $y;
            $normA += $x * $x;
            $normB += $y * $y;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /** Strip control characters and bound the length before anything leaves. */
    public static function clean(string $text): string
    {
        $value = (string) preg_replace('/[\p{C}]+/u', ' ', $text);
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return mb_substr($value, 0, self::MAX_INPUT_CHARS);
    }
}
