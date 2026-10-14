// Duka_mkononi/lib/speech.ts
// Capability #23 - Voice Input & Voice Answers (mobile).
//
// WHAT THIS IS
// ------------
// The mobile half of the voice capability. Spoken ANSWERS are produced by the
// device engine through `expo-speech` (the Expo wrapper over iOS
// AVSpeechSynthesizer / Android TextToSpeech) - no key, no network, no cost.
//
// WHAT IT DELIBERATELY DOES NOT DO
// --------------------------------
// It does not pretend to offer speech RECOGNITION. This app ships no on-device
// recognizer module (that would need an extra native dependency and a custom
// build), so `sttAvailable()` is honestly `false` and the mic tells the user so
// in their language instead of failing silently. The platform keyboard's own
// dictation remains the way to speak on a phone, and it needs nothing from us.
// That is exactly the catalogue rule: "Do not claim a language is supported by
// the speech engine until its actual capabilities have been verified."
//
// PRIVACY: no audio is recorded, buffered by us or uploaded. Speech synthesis
// happens locally; when we later add a recognizer, audio must stay on-device or
// go to a server-side engine - never through this client with a key in it.
import * as Speech from 'expo-speech';
import { API_BASE_URL } from '../constants/api';
import { fetchWithTimeout } from './network';

export interface SpeechCapabilities {
  success: boolean;
  capability: string;
  enabled: boolean;
  provider: string;
  credentials: string;
  locale: string;
  direction: 'rtl' | 'ltr';
  tag: string;
  stt: {
    available: boolean;
    support: string;
    verified_locales: string[];
    mode: string;
    max_seconds: number;
  };
  tts: {
    available: boolean;
    support: string;
    verified_locales: string[];
    max_chars: number;
  };
  privacy: {
    audio_sent_to_server: boolean;
    audio_retained: boolean;
    continuous_listening: boolean;
    text_alternative: boolean;
  };
  limits: { max_seconds: number; max_chars: number };
  messages: Record<string, string>;
  code?: string;
  error?: string;
}

let cache: { locale: string; data: SpeechCapabilities | null } | null = null;

/**
 * The capability contract for the active locale: which locales have verified
 * coverage, the BCP-47 tag to synthesize with, the limits and the privacy
 * facts. Cached per locale; call `clearSpeechCache()` after a language switch.
 */
export async function fetchSpeechCapabilities(locale: string): Promise<SpeechCapabilities | null> {
  if (cache && cache.locale === locale) return cache.data;
  try {
    const res = await fetchWithTimeout(
      `${API_BASE_URL}/api/ai/speech/capabilities?locale=${encodeURIComponent(locale)}`,
      { headers: { Accept: 'application/json' } }
    );
    if (!res.ok) return null;
    const json = (await res.json()) as SpeechCapabilities;
    if (!json || json.success !== true) return null;
    cache = { locale, data: json };
    return json;
  } catch {
    return null;
  }
}

export function clearSpeechCache(): void {
  cache = null;
}

/** No recognizer module is bundled - report that honestly. */
export function sttAvailable(): boolean {
  return false;
}

/** `expo-speech` is always present, so synthesis is available on every build. */
export function ttsAvailable(): boolean {
  return true;
}

export interface SpeakOptions {
  locale: string;
  /** BCP-47 tag from the capability endpoint; falls back to the locale code. */
  tag?: string;
  maxChars?: number;
  onStart?: () => void;
  onEnd?: () => void;
  onError?: (code: string) => void;
}

/**
 * Read `text` aloud with the device engine. Stops any previous utterance first,
 * so answers never talk over each other. Never throws: a failure is reported
 * through `onError` and the caller keeps its visual answer.
 */
export async function speak(text: string, options: SpeakOptions): Promise<boolean> {
  const value = String(text ?? '').trim();
  if (!value) return false;

  const maxChars = options.maxChars && options.maxChars > 0 ? options.maxChars : 600;

  try {
    await Speech.stop();
    Speech.speak(value.slice(0, maxChars), {
      language: options.tag || options.locale,
      onStart: () => options.onStart?.(),
      onDone: () => options.onEnd?.(),
      onStopped: () => options.onEnd?.(),
      onError: () => {
        options.onEnd?.();
        options.onError?.('error');
      },
    });
    return true;
  } catch {
    options.onEnd?.();
    options.onError?.('error');
    return false;
  }
}

export async function stopSpeaking(): Promise<void> {
  try {
    await Speech.stop();
  } catch {
    // Nothing to stop.
  }
}

export async function isSpeaking(): Promise<boolean> {
  try {
    return await Speech.isSpeakingAsync();
  } catch {
    return false;
  }
}
