<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * The single shared Gemini provider adapter (instruction section 3.1).
 *
 * Every AI capability calls this instead of talking to the provider directly.
 * It centralises: model selection, bounded retries with exponential backoff +
 * jitter, 429/503 handling, timeouts, request-size limits, structured-JSON
 * enforcement with safe parsing, token/cost accounting, correlation ids, safe
 * logging and honest typed errors.
 *
 * The API key is used server-side only and is never logged or returned.
 */
class GeminiClient
{
    public const PROMPT_VERSION = 'v1';

    /** HTTP statuses that are worth retrying. */
    private const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    public function isConfigured(): bool
    {
        return trim((string) config('ai.api_key')) !== '';
    }

    public function isEnabled(): bool
    {
        return (bool) config('ai.enabled', true);
    }

    /**
     * Call the provider and return a validated structured-JSON result.
     *
     * @param  array<int, array<string, mixed>>  $userParts  Gemini parts (text / inlineData).
     * @param  array<string, mixed>  $options  capability, user_id, business_id, locale, ip,
     *                                         temperature, maxOutputTokens, timeout, retries.
     */
    public function generateJson(array $userParts, ?string $systemInstruction = null, array $options = []): AiResult
    {
        $requestId = (string) Str::uuid();
        $startedAt = microtime(true);
        $capability = (string) ($options['capability'] ?? 'generic');

        if (! $this->isEnabled()) {
            throw AiException::disabled();
        }
        if (! $this->isConfigured()) {
            throw AiException::notConfigured();
        }

        $system = $systemInstruction ?: null;
        $this->assertWithinSizeLimit($userParts, $system);

        $model = (string) config('ai.model');
        $timeout = (int) ($options['timeout'] ?? config('ai.timeout', 45));
        $maxRetries = max(0, (int) ($options['retries'] ?? config('ai.retries', 3)));

        $body = [
            'contents' => [['role' => 'user', 'parts' => array_values($userParts)]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => (float) ($options['temperature'] ?? config('ai.temperature', 0.2)),
                'maxOutputTokens' => (int) ($options['maxOutputTokens'] ?? config('ai.max_output_tokens', 4096)),
            ],
        ];
        if ($system && trim($system) !== '') {
            $body['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        $url = rtrim((string) config('ai.base_url'), '/')
            .'/models/'.rawurlencode($model).':generateContent';

        $attempts = 0;
        $lastError = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $attempts = $attempt + 1;
            if ($attempt > 0) {
                $this->backoff($attempt);
            }

            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => (string) config('ai.api_key'),
                ])->timeout($timeout)->post($url, $body);
            } catch (ConnectionException) {
                $lastError = AiException::timedOut();

                continue;
            } catch (Throwable $e) {
                $lastError = new AiException('AI provider request failed: '.$e->getMessage(), 'AI_UPSTREAM', 502, true);

                continue;
            }

            if ($response->successful()) {
                $payload = is_array($response->json()) ? $response->json() : [];

                try {
                    $result = $this->parseSuccess($payload, $model, $requestId, $startedAt, $attempts);
                } catch (AiException $e) {
                    $this->audit($options, $capability, $requestId, $model, $startedAt, $attempts, null, 'invalid', $e->errorCode);
                    throw $e;
                }

                $this->audit($options, $capability, $requestId, $model, $startedAt, $attempts, $result->usage, 'success', null, $result->cost);

                return $result;
            }

            $status = $response->status();
            $detail = $this->errorDetail($response->json(), $response->body());

            if (in_array($status, self::RETRYABLE_STATUSES, true)) {
                $lastError = $status === 429 ? AiException::rateLimited($detail) : AiException::upstream($status, $detail);

                continue;
            }

            $lastError = AiException::upstream($status, $detail);
            break;
        }

        $lastError ??= AiException::upstream(0, '');
        $this->audit($options, $capability, $requestId, $model, $startedAt, $attempts, null, 'failed', $lastError->errorCode);

        throw $lastError;
    }

    /* ------------------------------------------------------------------ */
    /* Internals */
    /* ------------------------------------------------------------------ */

    private function parseSuccess(array $payload, string $model, string $requestId, float $startedAt, int $attempts): AiResult
    {
        $candidates = $payload['candidates'] ?? null;
        if (! is_array($candidates) || $candidates === []) {
            $reason = (string) ($payload['promptFeedback']['blockReason'] ?? '');
            if ($reason !== '') {
                throw AiException::blocked($reason);
            }
            throw AiException::emptyResponse();
        }

        $texts = [];
        foreach (($candidates[0]['content']['parts'] ?? []) as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $texts[] = $part['text'];
            }
        }
        $text = trim(implode("\n", $texts));
        if ($text === '') {
            throw AiException::emptyResponse();
        }

        $data = $this->decodeJson($text);
        if ($data === null) {
            throw AiException::invalidJson();
        }

        $usage = $this->normalizeUsage($payload['usageMetadata'] ?? []);
        $cost = $this->estimateCost($usage);

        return new AiResult(
            data: $data,
            text: $text,
            model: $model,
            requestId: $requestId,
            durationMs: (int) round((microtime(true) - $startedAt) * 1000),
            attempts: $attempts,
            usage: $usage,
            cost: $cost['amount'],
            costCurrency: $cost['currency'],
        );
    }

    /**
     * Accept raw JSON, fenced JSON, or JSON embedded in prose. Returns null when
     * nothing usable can be decoded (never throws here).
     */
    public function decodeJson(string $text): ?array
    {
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $m)) {
            $decoded = json_decode(trim($m[1]), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $first = strpos($text, '{');
        $last = strrpos($text, '}');
        if ($first !== false && $last !== false && $last > $first) {
            $decoded = json_decode(substr($text, $first, $last - $first + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function assertWithinSizeLimit(array $userParts, ?string $system): void
    {
        $chars = mb_strlen((string) $system);
        foreach ($userParts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $chars += mb_strlen($part['text']);
            } elseif (isset($part['inlineData']['data']) && is_string($part['inlineData']['data'])) {
                // Base64 images count toward the provider's payload limit too.
                $chars += (int) (strlen($part['inlineData']['data']) / 4 * 3);
            }
        }

        $limit = (int) config('ai.max_prompt_chars', 120000);
        if ($limit > 0 && $chars > $limit) {
            throw AiException::promptTooLarge($chars, $limit);
        }
    }

    private function normalizeUsage(array $usageMetadata): array
    {
        $prompt = (int) ($usageMetadata['promptTokenCount'] ?? 0);
        $output = (int) ($usageMetadata['candidatesTokenCount'] ?? 0);
        $total = (int) ($usageMetadata['totalTokenCount'] ?? ($prompt + $output));

        return [
            'prompt_tokens' => $prompt,
            'output_tokens' => $output,
            'total_tokens' => $total,
        ];
    }

    /** @return array{amount: float, currency: string} */
    private function estimateCost(array $usage): array
    {
        $inRate = (float) config('ai.cost.input_per_million', 0);
        $outRate = (float) config('ai.cost.output_per_million', 0);

        $amount = $usage['prompt_tokens'] / 1_000_000 * $inRate
            + $usage['output_tokens'] / 1_000_000 * $outRate;

        return [
            'amount' => round($amount, 6),
            'currency' => (string) config('ai.cost.currency', 'USD'),
        ];
    }

    private function backoff(int $attempt): void
    {
        $base = (int) config('ai.retry_base_delay_ms', 800);
        $max = (int) config('ai.retry_max_delay_ms', 8000);
        if ($base <= 0) {
            return;
        }

        $delay = min($max, $base * (2 ** ($attempt - 1)));
        // Full jitter: avoids retry storms hitting the provider in lockstep.
        $delay += random_int(0, max(1, (int) ($base / 2)));

        usleep($delay * 1000);
    }

    private function errorDetail(?array $json, string $fallback): string
    {
        $message = $json['error']['message'] ?? null;
        if (is_string($message) && $message !== '') {
            return mb_substr($message, 0, 300);
        }

        return mb_substr(trim($fallback), 0, 300);
    }

    private function audit(
        array $options,
        string $capability,
        string $requestId,
        string $model,
        float $startedAt,
        int $attempts,
        ?array $usage,
        string $status,
        ?string $errorCode,
        float $cost = 0.0,
    ): void {
        AiAudit::request([
            'user_id' => $options['user_id'] ?? null,
            'business_id' => $options['business_id'] ?? null,
            'ip' => $options['ip'] ?? null,
            'capability' => $capability,
            'request_id' => $requestId,
            'model' => $model,
            'prompt_version' => self::PROMPT_VERSION,
            'locale' => $options['locale'] ?? null,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'attempts' => $attempts,
            'tokens' => $usage,
            'cost' => $cost > 0 ? $cost : null,
            'cost_currency' => $cost > 0 ? (string) config('ai.cost.currency', 'USD') : null,
            'status' => $status,
            'error_code' => $errorCode,
        ]);
    }
}
