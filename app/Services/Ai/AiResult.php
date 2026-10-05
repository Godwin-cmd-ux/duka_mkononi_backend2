<?php

namespace App\Services\Ai;

/**
 * The outcome of one shared-provider AI call.
 *
 * `data` is the decoded, structured JSON payload the capability asked for.
 * Usage / cost fields feed the AI request log so operators can see spend and
 * latency per capability without inspecting prompts.
 */
class AiResult
{
    public function __construct(
        public readonly array $data,
        public readonly string $text,
        public readonly string $model,
        public readonly string $requestId,
        public readonly int $durationMs,
        public readonly int $attempts,
        public readonly array $usage = [],
        public readonly float $cost = 0.0,
        public readonly string $costCurrency = 'USD',
    ) {}

    public function promptTokens(): int
    {
        return (int) ($this->usage['prompt_tokens'] ?? 0);
    }

    public function outputTokens(): int
    {
        return (int) ($this->usage['output_tokens'] ?? 0);
    }

    public function totalTokens(): int
    {
        return (int) ($this->usage['total_tokens'] ?? ($this->promptTokens() + $this->outputTokens()));
    }

    public function meta(): array
    {
        return [
            'model' => $this->model,
            'request_id' => $this->requestId,
            'duration_ms' => $this->durationMs,
            'attempts' => $this->attempts,
            'tokens' => $this->usage,
            'cost' => $this->cost,
            'cost_currency' => $this->costCurrency,
        ];
    }
}
