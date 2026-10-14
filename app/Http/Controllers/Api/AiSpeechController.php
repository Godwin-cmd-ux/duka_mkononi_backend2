<?php

namespace App\Http\Controllers\Api;

use App\Services\Ai\Speech;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Capability #23 - Voice Input & Voice Answers.
 *
 * This controller exposes ONE public, throttled endpoint: the capability
 * contract the voice controls need before they render a microphone button.
 *
 * Why there is no "transcribe" endpoint here: recognition happens on the
 * visitor's own device (browser Web Speech API / native engine), so no audio
 * ever reaches this server. Adding an upload endpoint would be dead code at
 * best and a liability at worst - the privacy promise is precisely that no
 * audio is transmitted. If a server-side engine is ever configured
 * (`ai.speech.provider`), the transcription call belongs here, server-side,
 * with the key never leaving the server; until then the honest contract is
 * this metadata endpoint.
 *
 * The endpoint is public because the public concierge - the only AI input a
 * visitor without an account has - is where the mic is offered. It is
 * supported by the route throttle.
 */
class AiSpeechController extends BaseController
{
    /**
     * GET /api/ai/speech/capabilities
     *
     * Returns, localized: which locales have verified speech coverage, the
     * privacy facts, the single-utterance limits, and every UI string the
     * controls show. `enabled: false` (with FEATURE_DISABLED) means the client
     * must hide the controls and keep the keyboard path - it is a 200, not an
     * error, so the page never breaks when an operator switches voice off.
     */
    public function capabilities(Request $request): JsonResponse
    {
        $locale = $this->resolveLocale($request);
        $payload = Speech::capabilities($locale);

        if (! $payload['enabled']) {
            $payload['code'] = 'FEATURE_DISABLED';
            $payload['error'] = Speech::msg('unsupported', $locale);
        }

        return $this->json($payload);
    }
}
