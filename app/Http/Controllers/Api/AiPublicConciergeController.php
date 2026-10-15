<?php

namespace App\Http\Controllers\Api;

use App\Services\Ai\AiException;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\PublicConcierge;
use App\Services\PublicCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Capability #21 - Public AI Concierge.
 *
 * POST /api/ai/concierge/ask   - one public question
 * GET  /api/ai/concierge/help  - the approved topics, links and examples
 *
 * Both routes are PUBLIC: a visitor who has never signed in must be able to ask
 * what the marketplace is or where to download the app. They are therefore the
 * most exposed AI surface in the platform, and everything about them is bounded:
 *
 *  - ABUSE PROTECTION. Two independent limits: a per-IP minute throttle on the
 *    route (`throttle:20,1` / `throttle:60,1`) and a per-IP HOURLY budget
 *    enforced here (`ai.concierge.max_per_hour`), which answers 429 with the
 *    remaining-time hint instead of doing more work. A question longer than
 *    `ai.concierge.max_question_chars` is truncated, never rejected with a
 *    stack trace.
 *  - PUBLIC DATA ONLY. Answers come from the approved knowledge base
 *    (PublicConcierge::MESSAGES) and from `PublicCatalog`, the single source of
 *    public visibility truth the shop pages already use. Search results are
 *    re-cut to the public field whitelist, so a future catalogue change cannot
 *    leak a seller's phone through this path.
 *  - NO INTERNALS. Questions about administration, credentials, technical
 *    internals or other people's private data are refused with a polite answer
 *    and the fallback links; they never read anything.
 *  - NO INVENTED LINKS. Every URL in a response is from
 *    PublicConcierge::SECTION_LINKS / DEFAULT_EXTERNAL_LINKS (the links this
 *    website itself publishes). Neither a question nor the optional model step
 *    can add or change one.
 *  - The optional model step may only pick an approved topic when the
 *    deterministic keywords found nothing, and add ONE sentence that passes the
 *    sanitiser. It never supplies a fact, a count or a URL, and when the
 *    provider is unavailable the answer is still correct (`ai.degraded: true`).
 */
class AiPublicConciergeController extends BaseController
{
    private const RATE_PREFIX = 'ai.concierge.rate.';

    private const ANSWER_CACHE_PREFIX = 'ai.concierge.answer.';

    private const NARRATION_CACHE_PREFIX = 'ai.concierge.narration.';

    /* ------------------------------------------------------------------ */
    /* Ask                                                                 */
    /* ------------------------------------------------------------------ */

    public function ask(Request $request): JsonResponse
    {
        $startedAt = microtime(true);

        if (! config('ai.features.concierge', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $locale = $this->resolveLocale($request);
        $ip = $this->clientIp($request);
        $maxChars = max(20, (int) config('ai.concierge.max_question_chars', 300));
        $question = mb_substr(trim((string) $request->input('question', '')), 0, $maxChars);

        if (mb_strlen($question) < 3) {
            return $this->json([
                'error' => PublicConcierge::msg('unknown', $locale),
                'code' => 'QUESTION_REQUIRED',
                'examples' => PublicConcierge::supportedExamples($locale),
            ], 422);
        }

        /* Abuse protection #2: the per-IP hourly budget. Checked BEFORE any
         * work, so a flood costs one cache read and nothing else. */
        $limits = $this->consumeBudget($ip);
        if ($limits['remaining'] < 0) {
            $this->log(null, 'AI_CONCIERGE_RATE_LIMITED', '/api/ai/concierge/ask', [
                'used_this_hour' => $limits['used'],
                'per_hour' => $limits['per_hour'],
            ], $ip, 'failed');

            return $this->json([
                'error' => PublicConcierge::msg('error', $locale),
                'code' => 'RATE_LIMITED',
                'limits' => $limits,
                'fallback_links' => PublicConcierge::fallbackLinks($locale, PublicConcierge::externalLinks()),
            ], 429);
        }

        try {
            $parsed = PublicConcierge::parse($question, ['locale' => $locale]);

            $ai = ['available' => false, 'degraded' => true, 'code' => null, 'reason' => null];
            $ack = null;

            /* The model may only help when the deterministic parse found nothing
             * answerable AND the question is even about the marketplace. An
             * off-topic question ("what is the weather tomorrow") gets the
             * honest "not in our public information" answer instead of a model
             * picking a topic it has no business answering. */
            if ($parsed['intent'] === 'unknown' && PublicConcierge::mentionsCommerce($question)) {
                $modelled = $this->narrate($ip, $question, null, $locale, (bool) config('ai.concierge.ai_narrate', true));
                $ai = $modelled['ai'];
                if ($modelled['topic'] !== null) {
                    $parsed['topic'] = $modelled['topic'];
                    $parsed['intent'] = 'kb';
                    $parsed['confidence'] = 'medium';
                }
                $ack = $modelled['ack'];
            }

            $external = PublicConcierge::externalLinks();
            $answer = null;
            $results = ['products' => [], 'businesses' => []];
            $notice = null;

            if ($parsed['intent'] === 'refused') {
                $notice = PublicConcierge::msg('out_of_scope', $locale);
            } elseif ($parsed['intent'] === 'kb' && $parsed['topic'] !== null) {
                $answer = PublicConcierge::answer($parsed['topic'], $locale, $external);
            } elseif ($parsed['intent'] === 'search' && $parsed['query'] !== null) {
                /* PUBLIC catalogue only - the same rows /shop renders. */
                $term = (string) $parsed['query'];
                $results['products'] = PublicConcierge::searchProducts(PublicCatalog::products(null, $term), $term);
                $results['businesses'] = PublicConcierge::searchBusinesses(PublicCatalog::shopBusinesses(), $term);
                if ($results['products'] === [] && $results['businesses'] === []) {
                    $notice = PublicConcierge::msg('no_results', $locale);
                }
            } else {
                $notice = PublicConcierge::msg('unknown', $locale);
            }

            if ($ack === null && $answer !== null) {
                $narrated = $this->narrate($ip, $question, $parsed['topic'], $locale, (bool) config('ai.concierge.ai_narrate', true));
                $ack = $narrated['ack'];
                /* Report the optional step honestly on this path too, so a
                 * disabled or unreachable provider is visible in `ai` instead
                 * of reading as if it had contributed. */
                $ai = $narrated['ai'];
            }

            $payload = [
                'success' => true,
                'capability' => PublicConcierge::CAPABILITY,
                'locale' => $locale,
                'scope' => 'public',
                'intent' => $parsed['intent'],
                'topic' => $parsed['topic'],
                'topic_source' => $parsed['confidence'] === 'medium' && $parsed['intent'] === 'kb' ? 'model' : 'keyword',
                'answer' => $answer,
                'results' => $results,
                'notice' => $notice,
                'notice_public' => PublicConcierge::msg('notice_public', $locale),
                'notice_scope' => PublicConcierge::msg('notice_scope', $locale),
                'fallback_links' => PublicConcierge::fallbackLinks($locale, $external),
                'topics' => PublicConcierge::topics($locale),
                'examples' => PublicConcierge::supportedExamples($locale),
                'limits' => $limits,
                'ai' => $ai,
                'acknowledgement' => $ack,
                'generated_at' => now()->toISOString(true),
            ];

            /* Defence in depth: a public payload must never carry a private
             * field. If one ever appears, it is stripped AND reported. */
            $private = PublicConcierge::findPrivateFields($payload);
            if ($private !== []) {
                $this->log(null, 'AI_CONCIERGE_LEAK_BLOCKED', '/api/ai/concierge/ask', ['fields' => $private], $ip, 'failed');
                $payload = PublicConcierge::assertNoPrivateFields($payload);
            }

            $this->log(null, 'AI_CONCIERGE_ASK', '/api/ai/concierge/ask', [
                'intent' => $parsed['intent'],
                'topic' => $parsed['topic'],
                'question_chars' => mb_strlen($question),
                'products' => count($results['products']),
                'businesses' => count($results['businesses']),
                'ai_status' => $ai['degraded'] ? 'degraded' : 'ok',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ], $ip, 'success');

            return $this->json($payload);
        } catch (Throwable $error) {
            $this->log(null, 'AI_CONCIERGE_ASK_ERROR', '/api/ai/concierge/ask', [
                'error' => $error->getMessage(),
            ], $ip, 'failed');

            return $this->json([
                'error' => PublicConcierge::msg('error', $locale),
                'code' => 'CONCIERGE_ERROR',
                'fallback_links' => PublicConcierge::fallbackLinks($locale, PublicConcierge::externalLinks()),
            ], 500);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Help                                                                */
    /* ------------------------------------------------------------------ */

    public function help(Request $request): JsonResponse
    {
        if (! config('ai.features.concierge', true)) {
            return $this->json(['error' => 'Kipengele hiki kimezimwa.', 'code' => 'FEATURE_DISABLED'], 403);
        }

        $locale = $this->resolveLocale($request);
        $ip = $this->clientIp($request);
        $external = PublicConcierge::externalLinks();

        $this->log(null, 'AI_CONCIERGE_HELP', '/api/ai/concierge/help', ['locale' => $locale], $ip, 'success');

        return $this->json([
            'success' => true,
            'capability' => PublicConcierge::CAPABILITY,
            'locale' => $locale,
            'scope' => 'public',
            'title' => PublicConcierge::msg('title', $locale),
            'subtitle' => PublicConcierge::msg('subtitle', $locale),
            'ask_placeholder' => PublicConcierge::msg('ask_placeholder', $locale),
            'ask_button' => PublicConcierge::msg('ask_button', $locale),
            'notice_public' => PublicConcierge::msg('notice_public', $locale),
            'notice_scope' => PublicConcierge::msg('notice_scope', $locale),
            'topics' => PublicConcierge::topics($locale),
            'examples' => PublicConcierge::supportedExamples($locale),
            'fallback_links' => PublicConcierge::fallbackLinks($locale, $external),
            /* The `businesses` section is gone from here on purpose: the page
               is still live, but the website links to it nowhere any more, so
               the help payload must not advertise it either. */
            'links' => [
                'home' => PublicConcierge::SECTION_LINKS['home'],
                'shop' => PublicConcierge::SECTION_LINKS['shop'],
                'download_apk' => $external['download_apk'] ?? PublicConcierge::DEFAULT_EXTERNAL_LINKS['download_apk'],
            ],
            'limits' => [
                'per_hour' => $this->maxPerHour(),
                'max_question_chars' => max(20, (int) config('ai.concierge.max_question_chars', 300)),
            ],
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Abuse protection                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Spend one unit of the per-IP hourly budget.
     *
     * @return array{per_hour: int, used: int, remaining: int}
     */
    private function consumeBudget(string $ip): array
    {
        $perHour = $this->maxPerHour();
        $key = self::RATE_PREFIX.md5($ip).'.'.gmdate('YmdH');

        try {
            $used = (int) cache()->get($key, 0);
            if ($used >= $perHour) {
                return ['per_hour' => $perHour, 'used' => $used, 'remaining' => -1];
            }
            $used++;
            cache()->put($key, $used, 3600);
        } catch (Throwable $e) {
            /* A cache failure must not open the door: without a working counter
             * the route throttle is still in force, so allow the request. */
            return ['per_hour' => $perHour, 'used' => 0, 'remaining' => $perHour];
        }

        return ['per_hour' => $perHour, 'used' => $used, 'remaining' => max(0, $perHour - $used)];
    }

    private function maxPerHour(): int
    {
        return max(5, (int) config('ai.concierge.max_per_hour', 60));
    }

    private function clientIp(Request $request): string
    {
        $forwarded = (string) $request->header('x-forwarded-for', '');

        return $forwarded !== '' ? trim(explode(',', $forwarded)[0]) : (string) $request->ip();
    }

    /* ------------------------------------------------------------------ */
    /* Optional, gated model step                                          */
    /* ------------------------------------------------------------------ */

    /**
     * One sanitised acknowledgement sentence and, only when the deterministic
     * parse found no topic, one topic id from the SAME allow-list.
     *
     * @return array{ai: array{available: bool, degraded: bool, code: ?string, reason: ?string}, ack: ?string, topic: ?string}
     */
    private function narrate(string $ip, string $question, ?string $deterministicTopic, string $locale, bool $enabled): array
    {
        $fallback = [
            'ai' => ['available' => false, 'degraded' => true, 'code' => null, 'reason' => null],
            'ack' => null,
            'topic' => null,
        ];

        if (! $enabled) {
            $fallback['ai']['code'] = 'AI_DISABLED';
            $fallback['ai']['reason'] = AiException::reasonForCode('AI_DISABLED', $locale);

            return $fallback;
        }

        $cacheKey = self::NARRATION_CACHE_PREFIX.md5($ip.'|'.$locale.'|'.mb_strtolower($question).'|'.($deterministicTopic ?? 'none'));
        try {
            $cached = cache()->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        } catch (Throwable $e) {
            // A cache miss only costs one extra model call.
        }

        $client = new GeminiClient;
        if (! $client->isEnabled() || ! $client->isConfigured()) {
            $fallback['ai']['code'] = 'AI_DISABLED';
            $fallback['ai']['reason'] = AiException::reasonForCode('AI_DISABLED', $locale);

            return $fallback;
        }

        $instruction = implode("\n", [
            'You are the PUBLIC concierge of the Duka Mkononi marketplace website.',
            'Return STRICT JSON only: {"topic": string, "ack": string}.',
            '"topic" must be one of these ids or an empty string: '.implode(', ', PublicConcierge::TOPICS).'.',
            $deterministicTopic === null
                ? 'Choose the single topic id whose public explanation best answers the visitor question. If none fits, use an empty string.'
                : 'The topic is already decided as "'.$deterministicTopic.'": return it unchanged.',
            'The "ack" is ONE short sentence (no digits, no links, no email addresses, no phone numbers, no question marks) that says the public information below answers the question.',
            'You must NOT state any fact, number, price, date, business name or URL yourself, and must NOT mention any person or credential.',
            'Write the sentence in the language with locale code "'.$locale.'" (sw=Kiswahili, en=English, fr=French, es=Spanish, zh=Chinese, hi=Hindi, ur=Urdu, de=German).',
            'DATA: '.json_encode(['locale' => $locale, 'decided_topic' => $deterministicTopic], JSON_UNESCAPED_UNICODE),
        ]);

        try {
            $result = $client->generateJson(
                [['text' => 'Return the JSON for this public marketplace question.']],
                $instruction,
                [
                    'capability' => PublicConcierge::CAPABILITY.'_ack',
                    'ip' => $ip,
                    'locale' => $locale,
                    'temperature' => 0.0,
                    'maxOutputTokens' => 160,
                ],
            );

            $data = is_array($result->data) ? $result->data : [];
            $ack = isset($data['ack']) && is_string($data['ack']) ? PublicConcierge::sanitizeAck($data['ack']) : null;
            $topic = isset($data['topic']) && is_string($data['topic']) && in_array($data['topic'], PublicConcierge::TOPICS, true)
                ? $data['topic']
                : null;

            /* The model may not silently override a deterministic decision. */
            if ($deterministicTopic !== null) {
                $fallback['ai'] = ['available' => true, 'degraded' => false, 'code' => null, 'reason' => null];

                return ['ai' => $fallback['ai'], 'ack' => $ack, 'topic' => null];
            }

            if ($ack === null && $topic === null) {
                return [
                    'ai' => [
                        'available' => true,
                        'degraded' => true,
                        'code' => 'AI_UNSAFE_OUTPUT',
                        'reason' => AiException::reasonForCode('AI_UNSAFE_OUTPUT', $locale),
                    ],
                    'ack' => null,
                    'topic' => null,
                ];
            }

            $out = [
                'ai' => ['available' => true, 'degraded' => false, 'code' => null, 'reason' => null],
                'ack' => $ack,
                'topic' => $topic,
            ];
            try {
                cache()->put($cacheKey, $out, max(1, (int) config('ai.concierge.cache_minutes', 10)) * 60);
            } catch (Throwable $e) {
                // Caching is only a cost optimisation.
            }

            return $out;
        } catch (AiException $error) {
            $fallback['ai']['code'] = $error->errorCode;
            $fallback['ai']['reason'] = AiException::reasonForCode($error->errorCode, $locale);

            return $fallback;
        } catch (Throwable $error) {
            $fallback['ai']['code'] = 'AI_ERROR';
            $fallback['ai']['reason'] = AiException::reasonForCode('AI_ERROR', $locale);

            return $fallback;
        }
    }

    /** Mirrors the other AI controllers: an allow-list, never a free string. */}
