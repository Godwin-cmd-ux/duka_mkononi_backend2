<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * A failure raised by the shared AI layer.
 *
 * The code is stable and safe to return to clients / write to logs. The
 * message is human-readable and never contains secrets, keys or prompt text.
 */
class AiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 502,
        public readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }

    public static function disabled(): self
    {
        return new self('AI features are disabled on this server.', 'AI_DISABLED', 503);
    }

    public static function notConfigured(): self
    {
        return new self('The AI provider is not configured on this server.', 'AI_NOT_CONFIGURED', 503);
    }

    public static function promptTooLarge(int $chars, int $limit): self
    {
        return new self(
            "The AI request was too large ({$chars} characters, limit {$limit}). Reduce the data window and try again.",
            'AI_PROMPT_TOO_LARGE',
            413,
        );
    }

    public static function timedOut(): self
    {
        return new self('The AI provider timed out or was unreachable. Please try again.', 'AI_TIMEOUT', 503, true);
    }

    public static function rateLimited(string $detail = ''): self
    {
        return new self('The AI provider is busy right now. Please try again shortly.'.($detail ? " ({$detail})" : ''), 'AI_RATE_LIMIT', 503, true);
    }

    public static function blocked(string $reason): self
    {
        return new self('The AI provider declined to answer this request.'.($reason ? " ({$reason})" : ''), 'AI_BLOCKED', 422);
    }

    public static function upstream(int $status, string $detail = ''): self
    {
        return new self("The AI provider returned an error ({$status}).".($detail ? " {$detail}" : ''), 'AI_UPSTREAM', 502, true);
    }

    public static function emptyResponse(): self
    {
        return new self('The AI provider returned an empty response.', 'AI_EMPTY_RESPONSE', 502, true);
    }

    public static function invalidJson(): self
    {
        return new self('The AI provider did not return valid JSON.', 'AI_INVALID_JSON', 502, true);
    }

    public static function truncated(string $reason = ''): self
    {
        return new self(
            'The AI provider\'s answer was cut off before it finished'.($reason !== '' ? " ({$reason})" : '').'.',
            'AI_TRUNCATED',
            502,
            true,
        );
    }

    /**
     * A clear, non-technical, localized explanation of this failure that is
     * safe to show to a shop owner. The technical message stays in the logs via
     * the error code; this is what the seller-facing UI renders.
     */
    public function userReason(?string $locale = null): string
    {
        return self::reasonForCode($this->errorCode, $locale) ?? $this->getMessage();
    }

    /**
     * Localized, jargon-free text for a stable error code. Falls back to English
     * for codes/locales without a dedicated translation, then to null so callers
     * can substitute their own message.
     */
    public static function reasonForCode(?string $code, ?string $locale = null): ?string
    {
        $messages = self::USER_REASONS[(string) $code] ?? null;
        if ($messages === null) {
            return null;
        }

        $locale = strtolower(trim((string) $locale));

        return $messages[$locale] ?? $messages['en'] ?? (reset($messages) ?: null);
    }

    /**
     * Seller-facing explanations. Deliberately kept separate from the internal
     * messages above: internal text names endpoints and payloads, these never do.
     * Only `sw` and `en` are translated; other locales fall back to English.
     */
    private const USER_REASONS = [
        'AI_RATE_LIMIT' => [
            'sw' => 'Seva ya AI ina shughuli nyingi kwa sasa. Tafadhali jaribu tena baada ya dakika chache.',
            'en' => 'The AI service is busy right now. Please try again in a few minutes.',
        ],
        'AI_TIMEOUT' => [
            'sw' => 'Seva ya AI imechelewa kujibu au haipatikani. Tafadhali jaribu tena.',
            'en' => 'The AI service took too long to respond. Please try again.',
        ],
        'AI_UPSTREAM' => [
            'sw' => 'Seva ya AI haipatikani kwa sasa. Tafadhali jaribu tena baada ya dakika chache.',
            'en' => 'The AI service is temporarily unavailable. Please try again in a few minutes.',
        ],
        'AI_INVALID_JSON' => [
            'sw' => 'AI imejibu kwa muundo ambao haukusomeka. Namba zako ni sahihi; tafadhali jaribu tena.',
            'en' => 'The AI returned an unreadable answer. Your figures are still correct; please try again.',
        ],
        'AI_TRUNCATED' => [
            'sw' => 'Jibu la AI lilikatika kabla ya kukamilika. Tafadhali jaribu tena.',
            'en' => 'The AI answer was cut off before it finished. Please try again.',
        ],
        'AI_EMPTY_RESPONSE' => [
            'sw' => 'AI haikutoa jibu kwa sasa. Tafadhali jaribu tena.',
            'en' => 'The AI did not return an answer right now. Please try again.',
        ],
        'AI_BLOCKED' => [
            'sw' => 'AI imekataa kujibu ombi hili. Tafadhali badilisha swali au data.',
            'en' => 'The AI declined to answer this request. Please rephrase your question.',
        ],
        'AI_DISABLED' => [
            'sw' => 'AI imezimwa kwenye server.',
            'en' => 'The AI helper is switched off on the server.',
        ],
        'AI_NOT_CONFIGURED' => [
            'sw' => 'AI haijawekwa kwenye server.',
            'en' => 'The AI helper is not set up on the server.',
        ],
        'AI_PROMPT_TOO_LARGE' => [
            'sw' => 'Taarifa zilizotumwa kwa AI ni nyingi mno. Punguza kipindi au idadi ya bidhaa.',
            'en' => 'The data sent to the AI was too large. Reduce the period or the number of products.',
        ],
        'NO_PRODUCTS' => [
            'sw' => 'Hakuna bidhaa hai kuchambua.',
            'en' => 'There are no active products to analyse.',
        ],
        'NO_RESTOCK' => [
            'sw' => 'Hakuna bidhaa inayohitaji kuagizwa kwa sasa.',
            'en' => 'No product needs re-ordering right now.',
        ],
        'NO_TOOL' => [
            'sw' => 'Hakuna data ya kueleza.',
            'en' => 'There is no data to explain.',
        ],
        'AI_ERROR' => [
            'sw' => 'AI haipatikani kwa sasa. Tafadhali jaribu tena.',
            'en' => 'The AI helper is unavailable right now. Please try again.',
        ],
        'INSUFFICIENT_EVIDENCE' => [
            'sw' => 'Data haitoshi kutoa ushauri wa AI.',
            'en' => 'There is not enough data to give AI advice.',
        ],
    ];
}
