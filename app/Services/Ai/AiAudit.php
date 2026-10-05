<?php

namespace App\Services\Ai;

use App\Services\AuditLogger;

/**
 * Audit + observability for AI calls (instruction section 3.6).
 *
 * Writes one row per AI request to the existing `user_logs` table, so no
 * migration is required. Only operational metadata is stored: capability,
 * correlation id, model/prompt version, status, duration, retry count, token
 * usage, cost, validation outcome and error code.
 *
 * Never logged: API keys, passwords, OTPs, payment credentials, or raw prompt
 * / response text (which can contain untrusted or personal content).
 */
class AiAudit
{
    public const ACTION = 'AI_REQUEST';

    public static function request(array $context): void
    {
        $capability = (string) ($context['capability'] ?? 'generic');

        $details = array_filter([
            'capability' => $capability,
            'request_id' => $context['request_id'] ?? null,
            'model' => $context['model'] ?? null,
            'prompt_version' => $context['prompt_version'] ?? null,
            'locale' => $context['locale'] ?? null,
            'business_id' => $context['business_id'] ?? null,
            'status' => $context['status'] ?? 'unknown',
            'duration_ms' => $context['duration_ms'] ?? null,
            'attempts' => $context['attempts'] ?? 1,
            'tokens' => $context['tokens'] ?? null,
            'cost' => $context['cost'] ?? null,
            'cost_currency' => $context['cost_currency'] ?? null,
            'validation_failures' => $context['validation_failures'] ?? null,
            'error_code' => $context['error_code'] ?? null,
            'records' => $context['records'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        AuditLogger::log(
            $context['user_id'] ?? null,
            self::ACTION,
            '/api/ai/'.$capability,
            $details,
            $context['ip'] ?? null,
            (string) ($context['status'] ?? 'unknown'),
        );
    }
}
