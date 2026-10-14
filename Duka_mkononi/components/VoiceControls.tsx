// Duka_mkononi/components/VoiceControls.tsx
// Capability #23 - Voice Input & Voice Answers (mobile controls).
//
// Two small, reusable controls for any AI input in the app:
//
//   <SpeakButton locale={lang} text={answerText} />   reads an answer aloud
//   <MicButton locale={lang} onMessage={setNote} />   the microphone action
//
// WHY THE MIC REPORTS A LIMITATION INSTEAD OF LISTENING
// ----------------------------------------------------
// Speech RECOGNITION would need an on-device recognizer module that this app
// does not ship (it is not part of the dependency set and would require a
// custom native build). Rather than render a button that silently does nothing,
// the mic explains - in the user's own language, via the shared `voice.*`
// strings - that voice input is unavailable on this build, and points at the
// keyboard. Spoken ANSWERS are fully supported through `expo-speech`.
//
// Nothing here records audio, keeps a buffer or talks to the network.
import { Ionicons } from '@expo/vector-icons';
import React, { useCallback, useEffect, useState } from 'react';
import { StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useLang } from '../context/LanguageContext';
import {
  fetchSpeechCapabilities,
  isSpeaking,
  speak,
  sttAvailable,
  stopSpeaking,
  ttsAvailable,
  type SpeechCapabilities,
} from '../lib/speech';

interface MicButtonProps {
  locale: string;
  /** Called with a localized explanation when voice input cannot run here. */
  onMessage: (message: string | null) => void;
  disabled?: boolean;
}

/**
 * The microphone action for an AI input. When (and only when) a recognizer is
 * available it would feed recognised text into the caller's editable input;
 * on this build it reports the honest limitation instead.
 */
export function MicButton({ locale, onMessage, disabled }: MicButtonProps) {
  const { t } = useLang();
  const [caps, setCaps] = useState<SpeechCapabilities | null>(null);

  useEffect(() => {
    let alive = true;
    fetchSpeechCapabilities(locale).then((data) => {
      if (alive) setCaps(data);
    });
    return () => {
      alive = false;
    };
  }, [locale]);

  const onPress = useCallback(() => {
    if (disabled) return;
    // Feature flag off, or no recognizer engine in this build: both mean "type
    // instead", and both are said in the user's language.
    if (caps && caps.enabled === false) {
      onMessage(t('voice.unsupported'));
      return;
    }
    if (!sttAvailable()) {
      onMessage(t('voice.unsupported'));
      return;
    }
  }, [caps, disabled, onMessage, t]);

  return (
    <TouchableOpacity
      style={[styles.mic, disabled && styles.micDisabled]}
      onPress={onPress}
      activeOpacity={0.85}
      accessibilityRole="button"
      accessibilityLabel={t('voice.listen')}
      accessibilityState={{ disabled: !!disabled }}>
      <Ionicons name="mic-outline" size={18} color={disabled ? '#94a3b8' : '#4f46e5'} />
    </TouchableOpacity>
  );
}

interface SpeakButtonProps {
  locale: string;
  /** The answer to read aloud - the same localized text shown on screen. */
  text: string;
  /** BCP-47 tag (from the capability endpoint); falls back to the locale code. */
  tag?: string;
  maxChars?: number;
  onMessage?: (message: string | null) => void;
}

/** "Listen" - reads an answer aloud with the device speech engine. */
export function SpeakButton({ locale, text, tag, maxChars, onMessage }: SpeakButtonProps) {
  const { t } = useLang();
  const [speaking, setSpeaking] = useState(false);
  const available = ttsAvailable();

  // Never leave the device talking after the screen goes away.
  useEffect(() => {
    return () => {
      stopSpeaking();
    };
  }, []);

  const onPress = useCallback(async () => {
    if (!available) {
      onMessage?.(t('voice.unsupported'));
      return;
    }
    if (await isSpeaking()) {
      await stopSpeaking();
      setSpeaking(false);
      return;
    }
    setSpeaking(true);
    await speak(text, {
      locale,
      tag,
      maxChars,
      onEnd: () => setSpeaking(false),
      onError: () => {
        setSpeaking(false);
        onMessage?.(t('voice.error'));
      },
    });
  }, [available, locale, maxChars, onMessage, t, tag, text]);

  if (!text || !text.trim()) return null;

  return (
    <TouchableOpacity
      style={[styles.speak, speaking && styles.speakActive]}
      onPress={onPress}
      activeOpacity={0.85}
      accessibilityRole="button"
      accessibilityLabel={speaking ? t('voice.stop_speaking') : t('voice.speak')}>
      <Ionicons
        name={speaking ? 'stop-circle-outline' : 'volume-high-outline'}
        size={16}
        color="#3730a3"
      />
      <Text style={styles.speakText}>{speaking ? t('voice.speaking') : t('voice.speak')}</Text>
    </TouchableOpacity>
  );
}

/** A small localized status line for the voice messages above. */
export function VoiceNote({ message, align }: { message: string | null; align?: object }) {
  if (!message) return null;
  return (
    <View style={styles.note}>
      <Text style={[styles.noteText, align]}>{message}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  mic: {
    width: 42,
    height: 42,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 10,
    backgroundColor: '#fff',
  },
  micDisabled: { backgroundColor: '#f8fafc' },
  speak: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    alignSelf: 'flex-start',
    marginTop: 10,
    paddingVertical: 7,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: '#e0e7ff',
    borderRadius: 10,
    backgroundColor: '#eef2ff',
  },
  speakActive: { backgroundColor: '#e0e7ff' },
  speakText: { color: '#3730a3', fontSize: 12.5, fontWeight: '600' },
  note: {
    marginTop: 8,
    paddingVertical: 8,
    paddingHorizontal: 10,
    borderRadius: 10,
    backgroundColor: '#f8fafc',
  },
  noteText: { color: '#475569', fontSize: 12.5, lineHeight: 17 },
});
