// Duka_mkononi/components/OnboardingAssistant.tsx
// Capability #19 (onboarding half) - the "getting started" panel.
//
// WHAT THIS SHOWS
// ---------------
// The setup checklist for the signed-in user's OWN account, in the chosen
// language, together with an ask box whose answers come from the approved
// onboarding content. Every step, status and evidence string is computed by the
// server from the real `users.*` / `businesses.*` / product rows - this screen
// only renders them, so it can never claim a step is done because the user said
// so. A step whose record could not be read is shown in its own "not read"
// state.
//
// PRIVACY: the only token ever sent is the caller's own (the register setup
// token while onboarding, otherwise the login token), and the server reads the
// account from that token - never from anything typed here. Nothing internal is
// shown: no password, no verification code, no other account's data, and no
// certification decision (an administrator decides that).
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Modal,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { API_BASE_URL } from '../constants/api';
import { useLang } from '../context/LanguageContext';
import { fetchWithTimeout } from '../lib/network';

interface Step {
  id: string;
  label: string;
  how: string;
  done: boolean;
  required: boolean;
  status: string;
  status_label: string;
  source: string;
  evidence: string;
}

interface Guide {
  role: string;
  role_label: string;
  steps: Step[];
  next_step: { id: string; label: string; how: string } | null;
  counts: { total: number; required: number; done: number; remaining: number };
  progress: number;
  summary: string[];
  signup: { role: string; web: string; app: string; needs_approval: boolean; note: string };
  sources: string[];
  limitations: string[];
}

interface AskResult {
  matched: boolean;
  acknowledgement: string | null;
  answers: string[];
  topics: { id: string; label: string; answer: string; evidence: string[] }[];
  sources: string[];
  limitations: string[];
}

const STATUS_COLOR: Record<string, string> = {
  done: '#059669',
  todo: '#d97706',
  waiting: '#2563eb',
  rejected: '#dc2626',
  unknown: '#64748b',
};

const statusKey = (status: string): string => {
  switch (status) {
    case 'done': return 'done_label';
    case 'todo': return 'todo_label';
    case 'waiting': return 'waiting_label';
    case 'rejected': return 'rejected_label';
    case 'unknown': return 'not_read_label';
    default: return 'recommended_label';
  }
};

interface Props {
  visible: boolean;
  onClose: () => void;
  /** The register setup token while onboarding; the login token otherwise. */
  setupToken?: string;
}

export default function OnboardingAssistant({ visible, onClose, setupToken }: Props) {
  const { t, lang } = useLang();

  const [loading, setLoading] = useState(false);
  const [failed, setFailed] = useState(false);
  const [guide, setGuide] = useState<Guide | null>(null);

  const [question, setQuestion] = useState('');
  const [asking, setAsking] = useState(false);
  const [asked, setAsked] = useState('');
  const [answer, setAnswer] = useState<AskResult | null>(null);
  const [askNotice, setAskNotice] = useState('');

  const token = useCallback(async (): Promise<string | null> => {
    if (setupToken) return setupToken;
    return AsyncStorage.getItem('userToken');
  }, [setupToken]);

  const load = useCallback(async () => {
    setLoading(true);
    setFailed(false);
    try {
      const bearer = await token();
      if (!bearer) {
        setFailed(true);
        return;
      }
      const res = await fetchWithTimeout(
        `${API_BASE_URL}/api/ai/onboarding/guide?locale=${encodeURIComponent(lang)}`,
        { headers: { Authorization: `Bearer ${bearer}`, Accept: 'application/json' } },
      );
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data?.guide) {
        setFailed(true);
        return;
      }
      setGuide(data.guide as Guide);
    } catch (error) {
      setFailed(true);
    } finally {
      setLoading(false);
    }
  }, [lang, token]);

  useEffect(() => {
    if (visible) {
      setAnswer(null);
      setAskNotice('');
      load();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visible, lang]);

  const ask = async () => {
    const text = question.trim();
    if (text === '' || asking) return;

    setAsking(true);
    setAnswer(null);
    setAskNotice('');
    setAsked(text);
    try {
      const bearer = await token();
      if (!bearer) {
        setFailed(true);
        return;
      }
      const res = await fetchWithTimeout(`${API_BASE_URL}/api/ai/onboarding/ask`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${bearer}`,
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({ question: text, locale: lang }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data?.answer) {
        setAskNotice(t('onboarding_ai.error'));
        return;
      }
      setAnswer({
        matched: !!data.answer.matched,
        acknowledgement: data.answer.acknowledgement || null,
        answers: data.answer.answers || [],
        topics: data.answer.topics || [],
        sources: data.answer.sources || [],
        limitations: data.answer.limitations || [],
      });
      setAskNotice(data.answer.matched ? '' : t('onboarding_ai.no_match'));
      if (data.answer.guide) setGuide(data.answer.guide as Guide);
    } catch (error) {
      setAskNotice(t('onboarding_ai.error'));
    } finally {
      setAsking(false);
    }
  };

  const progress = Math.max(0, Math.min(100, guide?.progress ?? 0));
  const counts = guide?.counts;

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose}>
      <SafeAreaView style={styles.screen} edges={['top', 'bottom']}>
        <KeyboardAvoidingView
          style={{ flex: 1 }}
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        >
          <View style={styles.head}>
            <View style={{ flex: 1 }}>
              <Text style={styles.title}>{t('onboarding_ai.title')}</Text>
              <Text style={styles.subtitle}>{t('onboarding_ai.subtitle')}</Text>
            </View>
            <TouchableOpacity onPress={onClose} style={styles.closeBtn}>
              <Ionicons name="close" size={22} color="#334155" />
            </TouchableOpacity>
          </View>

          <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
            {loading ? (
              <View style={styles.center}>
                <ActivityIndicator color="#4f46e5" />
                <Text style={styles.muted}>{t('onboarding_ai.loading')}</Text>
              </View>
            ) : null}

            {!loading && failed ? (
              <View style={styles.center}>
                <Ionicons name="cloud-offline-outline" size={40} color="#94a3b8" />
                <Text style={styles.muted}>{t('onboarding_ai.error')}</Text>
                <TouchableOpacity style={styles.primaryBtn} onPress={load}>
                  <Ionicons name="refresh" size={16} color="#fff" />
                  <Text style={styles.primaryBtnText}>{t('onboarding_ai.retry')}</Text>
                </TouchableOpacity>
              </View>
            ) : null}

            {!loading && !failed && guide ? (
              <View>
                <View style={styles.card}>
                  <View style={styles.rowBetween}>
                    <Text style={styles.cardLabel}>{t('onboarding_ai.progress')}</Text>
                    <Text style={styles.progressValue}>{progress}%</Text>
                  </View>
                  <View style={styles.progressTrack}>
                    <View style={[styles.progressFill, { width: `${progress}%` }]} />
                  </View>
                  <Text style={styles.muted}>
                    {t('onboarding_ai.steps_done', {
                      done: String(counts?.done ?? 0),
                      total: String(counts?.required ?? 0),
                    })}
                  </Text>
                  {guide.next_step ? (
                    <View style={styles.nextBox}>
                      <Text style={styles.nextLabel}>{t('onboarding_ai.next_step')}</Text>
                      <Text style={styles.nextTitle}>{guide.next_step.label}</Text>
                      <Text style={styles.nextHow}>
                        {t('onboarding_ai.how_to')}: {guide.next_step.how}
                      </Text>
                    </View>
                  ) : null}
                </View>

                <Text style={styles.sectionTitle}>{t('onboarding_ai.guide_title')}</Text>
                {(guide.steps || []).map((step) => (
                  <View key={step.id} style={styles.step}>
                    <View style={styles.stepHead}>
                      <Text style={styles.stepTitle}>{step.label}</Text>
                      <View
                        style={[
                          styles.statusChip,
                          { backgroundColor: STATUS_COLOR[step.status] || '#64748b' },
                        ]}
                      >
                        <Text style={styles.statusChipText}>
                          {step.status_label || t(`onboarding_ai.${statusKey(step.status)}` as any)}
                        </Text>
                      </View>
                    </View>
                    <Text style={styles.stepHow}>
                      {t('onboarding_ai.how_to')}: {step.how}
                    </Text>
                    {step.evidence ? <Text style={styles.evidence}>{step.evidence}</Text> : null}
                    <Text style={styles.source}>{step.source}</Text>
                  </View>
                ))}

                {guide.signup ? (
                  <View style={styles.card}>
                    <Text style={styles.cardLabel}>{t('onboarding_ai.signup_title')}</Text>
                    <Text style={styles.signupRole}>{guide.role_label}</Text>
                    <Text style={styles.signupLine}>
                      {t('onboarding_ai.signup_web')}: {guide.signup.web}
                    </Text>
                    <Text style={styles.signupLine}>
                      {t('onboarding_ai.signup_app')}: {guide.signup.app}
                    </Text>
                    <Text style={styles.muted}>{guide.signup.note}</Text>
                  </View>
                ) : null}

                <Text style={styles.sectionTitle}>{t('onboarding_ai.ask_title')}</Text>
                <View style={styles.card}>
                  <TextInput
                    style={styles.input}
                    value={question}
                    onChangeText={setQuestion}
                    placeholder={t('onboarding_ai.ask_placeholder')}
                    placeholderTextColor="#94a3b8"
                    multiline
                    maxLength={500}
                    editable={!asking}
                  />
                  <TouchableOpacity
                    style={[styles.primaryBtn, (asking || question.trim() === '') && styles.btnDisabled]}
                    disabled={asking || question.trim() === ''}
                    onPress={ask}
                  >
                    {asking ? (
                      <ActivityIndicator color="#fff" size="small" />
                    ) : (
                      <>
                        <Ionicons name="send" size={15} color="#fff" />
                        <Text style={styles.primaryBtnText}>{t('onboarding_ai.btn_ask')}</Text>
                      </>
                    )}
                  </TouchableOpacity>
                  {asking ? <Text style={styles.muted}>{t('onboarding_ai.asking')}</Text> : null}
                  <Text style={styles.security}>{t('onboarding_ai.security_note')}</Text>
                </View>

                {asked !== '' ? (
                  <View style={styles.card}>
                    <Text style={styles.cardLabel}>{t('onboarding_ai.answer_title')}</Text>
                    <Text style={styles.asked}>{asked}</Text>
                    {answer?.acknowledgement ? (
                      <Text style={styles.ack}>{answer.acknowledgement}</Text>
                    ) : null}
                    {(answer?.answers || []).map((text, index) => (
                      <Text key={`a-${index}`} style={styles.answerText}>
                        {text}
                      </Text>
                    ))}
                    {answer && !answer.matched ? (
                      <Text style={styles.muted}>{t('onboarding_ai.no_match')}</Text>
                    ) : null}
                    {askNotice !== '' && answer?.matched ? (
                      <Text style={styles.muted}>{askNotice}</Text>
                    ) : null}

                    {(answer?.sources || []).length > 0 ? (
                      <>
                        <Text style={styles.miniTitle}>{t('onboarding_ai.sources_title')}</Text>
                        {(answer?.sources || []).map((line, index) => (
                          <Text key={`s-${index}`} style={styles.source}>{line}</Text>
                        ))}
                      </>
                    ) : null}
                  </View>
                ) : null}

                {(answer?.limitations || guide.limitations || []).length > 0 ? (
                  <View style={styles.limitBox}>
                    <Text style={styles.miniTitle}>{t('onboarding_ai.limitations_title')}</Text>
                    {(answer?.limitations || guide.limitations || []).map((line, index) => (
                      <Text key={`l-${index}`} style={styles.limitText}>
                        {'• '}{line}
                      </Text>
                    ))}
                  </View>
                ) : null}
              </View>
            ) : null}
          </ScrollView>
        </KeyboardAvoidingView>
      </SafeAreaView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: '#f8fafc' },
  head: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 14,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  title: { fontSize: 17, fontWeight: '800', color: '#0f172a' },
  subtitle: { fontSize: 12.5, color: '#64748b', marginTop: 2 },
  closeBtn: {
    width: 40,
    height: 40,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    alignItems: 'center',
    justifyContent: 'center',
  },
  content: { padding: 16, paddingBottom: 40 },
  center: { alignItems: 'center', padding: 24, gap: 8 },
  card: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 14,
    padding: 14,
    gap: 8,
    marginBottom: 14,
  },
  cardLabel: { fontSize: 12, fontWeight: '800', color: '#334155', textTransform: 'uppercase' },
  rowBetween: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  progressValue: { fontSize: 16, fontWeight: '800', color: '#4f46e5' },
  progressTrack: { height: 8, borderRadius: 999, backgroundColor: '#e2e8f0', overflow: 'hidden' },
  progressFill: { height: 8, borderRadius: 999, backgroundColor: '#4f46e5' },
  nextBox: { backgroundColor: '#eef2ff', borderRadius: 10, padding: 10, gap: 2, marginTop: 4 },
  nextLabel: { fontSize: 11, fontWeight: '800', color: '#4338ca', textTransform: 'uppercase' },
  nextTitle: { fontSize: 14, fontWeight: '700', color: '#1e1b4b' },
  nextHow: { fontSize: 12.5, color: '#3730a3' },
  sectionTitle: { fontSize: 13.5, fontWeight: '800', color: '#0f172a', marginBottom: 8 },
  step: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#eef2f7',
    borderRadius: 12,
    padding: 12,
    gap: 4,
    marginBottom: 10,
  },
  stepHead: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  stepTitle: { flex: 1, fontSize: 14, fontWeight: '700', color: '#0f172a' },
  statusChip: { borderRadius: 999, paddingHorizontal: 10, paddingVertical: 4 },
  statusChipText: { color: '#fff', fontSize: 11, fontWeight: '700' },
  stepHow: { fontSize: 12.5, color: '#475569' },
  evidence: { fontSize: 12.5, color: '#0f172a', backgroundColor: '#f1f5f9', borderRadius: 8, padding: 8 },
  source: { fontSize: 11, color: '#94a3b8' },
  signupRole: { fontSize: 14, fontWeight: '700', color: '#0f172a' },
  signupLine: { fontSize: 12.5, color: '#475569' },
  input: {
    minHeight: 60,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 12,
    padding: 12,
    fontSize: 14,
    color: '#0f172a',
    textAlignVertical: 'top',
  },
  primaryBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#4f46e5',
    borderRadius: 12,
    paddingVertical: 13,
    paddingHorizontal: 16,
  },
  btnDisabled: { opacity: 0.5 },
  primaryBtnText: { color: '#fff', fontWeight: '700', fontSize: 14.5 },
  security: { fontSize: 11.5, color: '#64748b' },
  asked: { fontSize: 13, fontWeight: '700', color: '#334155' },
  ack: { fontSize: 13, color: '#3730a3' },
  answerText: {
    fontSize: 13.5,
    color: '#0f172a',
    backgroundColor: '#f8fafc',
    borderLeftWidth: 3,
    borderLeftColor: '#4f46e5',
    borderRadius: 8,
    padding: 10,
  },
  miniTitle: { fontSize: 11.5, fontWeight: '800', color: '#475569', marginTop: 6 },
  limitBox: {
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 14,
    padding: 14,
    gap: 2,
  },
  limitText: { fontSize: 12, color: '#475569' },
  muted: { fontSize: 12.5, color: '#64748b' },
});
