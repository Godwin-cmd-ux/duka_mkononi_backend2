<?php

namespace Tests\Feature;

use App\Services\Ai\AiException;
use App\Services\Ai\GeminiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The shared provider adapter must retry transient failures, return parsed JSON
 * with token usage, and turn bad/unconfigured responses into typed errors.
 */
class AiGeminiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.enabled' => true,
            'ai.api_key' => 'test-key',
            'ai.model' => 'gemini-test',
            'ai.base_url' => 'https://ai.test/v1beta',
            'ai.timeout' => 5,
            'ai.retries' => 3,
            'ai.retry_base_delay_ms' => 0,
            'ai.max_prompt_chars' => 120000,
        ]);
    }

    private function successBody(): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [['text' => '{"headline":"ok","insights":[]}']]],
            ]],
            'usageMetadata' => [
                'promptTokenCount' => 10,
                'candidatesTokenCount' => 5,
                'totalTokenCount' => 15,
            ],
        ];
    }

    public function test_it_parses_json_and_reports_token_usage(): void
    {
        Http::fake(['*' => Http::response($this->successBody(), 200)]);

        $result = (new GeminiClient)->generateJson([['text' => 'hello']], null, ['capability' => 'test']);

        $this->assertSame('ok', $result->data['headline']);
        $this->assertSame(15, $result->totalTokens());
        $this->assertSame(1, $result->attempts);
    }

    public function test_it_retries_a_transient_429_then_succeeds(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['error' => ['message' => 'quota exceeded']], 429)
                ->push($this->successBody(), 200),
        ]);

        $result = (new GeminiClient)->generateJson([['text' => 'hello']], null, ['capability' => 'test']);

        $this->assertSame('ok', $result->data['headline']);
        $this->assertSame(2, $result->attempts);
    }

    public function test_it_raises_a_typed_error_after_exhausting_retries(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'busy']], 503)]);

        try {
            (new GeminiClient)->generateJson([['text' => 'hello']], null, ['capability' => 'test', 'retries' => 1]);
            $this->fail('Expected an AiException.');
        } catch (AiException $e) {
            $this->assertSame('AI_UPSTREAM', $e->errorCode);
        }
    }

    public function test_it_rejects_invalid_json_from_the_provider(): void
    {
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'not json at all']]]]],
        ], 200)]);

        $this->expectException(AiException::class);

        (new GeminiClient)->generateJson([['text' => 'hello']], null, ['capability' => 'test']);
    }

    public function test_it_fails_cleanly_when_unconfigured(): void
    {
        config(['ai.api_key' => '']);

        try {
            (new GeminiClient)->generateJson([['text' => 'hello']]);
            $this->fail('Expected an AiException.');
        } catch (AiException $e) {
            $this->assertSame('AI_NOT_CONFIGURED', $e->errorCode);
        }
    }

    public function test_it_refuses_an_oversized_prompt_before_any_call(): void
    {
        Http::fake();
        config(['ai.max_prompt_chars' => 10]);

        try {
            (new GeminiClient)->generateJson([['text' => str_repeat('x', 100)]]);
            $this->fail('Expected an AiException.');
        } catch (AiException $e) {
            $this->assertSame('AI_PROMPT_TOO_LARGE', $e->errorCode);
            Http::assertNothingSent();
        }
    }
}
