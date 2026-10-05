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
}
