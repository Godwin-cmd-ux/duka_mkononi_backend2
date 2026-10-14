// Duka_mkononi/components/PublicConcierge.tsx
// Capability #21 - Public AI Concierge (mobile).
//
// WHAT THIS IS
// ------------
// The logged-out landing screen is the app's public homepage: someone who has
// not signed in yet can read what Duka Mkononi is and how to start, but had
// nobody to ask. This component adds that ask box, from a card on the landing
// screen, and it is the mobile twin of the website's floating concierge.
//
// Everything factual comes from the two public endpoints:
//   GET  /api/ai/concierge/help   -> title, notices, approved topics, examples, links
//   POST /api/ai/concierge/ask    -> a localized answer, or real public
//                                    products/businesses, or an honest refusal,
//                                    always with the published fallback links
// The screen renders that payload and adds nothing of its own: no answer text,
// no statistics, no link and no phone number is invented here. The only thing
// it sends is the question the visitor typed plus the app's current locale.
//
// PRIVACY / SCOPE: no token is attached (the visitor may be anonymous), the
// payload is bounded to public fields server-side, and when the optional model
// step is unavailable the answer is still complete - the payload's `ai` block is
// used only to label that honestly in the UI.
import { Ionicons } from '@expo/vector-icons';
import { openBrowserAsync, WebBrowserPresentationStyle } from 'expo-web-browser';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Linking,
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
import { MicButton, SpeakButton, VoiceNote } from './VoiceControls';

interface ConciergeLink {
  id: string;
  label: string;
  url: string;
}

interface ConciergeTopic {
  id: string;
  label: string;
  title: string;
}

interface ConciergeAnswer {
  title: string;
  body: string;
  bullets: string[];
  links: ConciergeLink[];
}

interface ConciergeProduct {
  id: string;
  name: string;
  category: string | null;
  selling_price: number;
  currency: string;
  available: boolean;
  business_name: string | null;
  business_location: string | null;
}

interface ConciergeBusiness {
  id: string;
  business_name: string;
  business_location: string | null;
  is_certified: boolean;
  product_count: number;
}

interface AskPayload {
  success: boolean;
  intent: 'kb' | 'search' | 'refused' | 'unknown';
  topic: string | null;
  answer: ConciergeAnswer | null;
  results: { products: ConciergeProduct[]; businesses: ConciergeBusiness[] };
  notice: string | null;
  notice_public: string | null;
  notice_scope: string | null;
  fallback_links: ConciergeLink[];
  acknowledgement: string | null;
  ai: { degraded: boolean; code: string | null; reason: string | null };
  error?: string;
}

interface HelpPayload {
  title: string;
  subtitle: string;
  ask_placeholder: string;
  ask_button: string;
  notice_public: string;
  notice_scope: string;
  topics: ConciergeTopic[];
  examples: string[];
  fallback_links: ConciergeLink[];
}

/**
 * Opens one of the payload's links. Links published by the concierge API are
 * either internal website sections ("/shop") or fully-formed external targets
 * (the APK, WhatsApp, mailto:, tel:), and the browser is the right place for
 * both: the visitor reads the real page the answer referred to.
 */
const openLink = async (url: string) => {
  const target = url.startsWith('/') ? `${API_BASE_URL}${url}` : url;
  try {
    if (/^(mailto:|tel:)/i.test(target)) {
      await Linking.openURL(target);
      return;
    }
    await openBrowserAsync(target, { presentationStyle: WebBrowserPresentationStyle.AUTOMATIC });
  } catch {
    // A link that cannot open must never break the answer around it.
  }
};

export default function PublicConcierge() {
  const { lang, t } = useLang();

  // Urdu is right-to-left: the header, the ask box and the answers all read
  // from the right, the way the rest of the Urdu UI is expected to.
  const isRtl = lang === 'ur';

  const [visible, setVisible] = useState(false);
  const [help, setHelp] = useState<HelpPayload | null>(null);
  const [loadingHelp, setLoadingHelp] = useState(false);
  const [question, setQuestion] = useState('');
  const [asking, setAsking] = useState(false);
  const [answer, setAnswer] = useState<AskPayload | null>(null);
  const [error, setError] = useState<string | null>(null);
  // Capability #23: localized voice messages (unsupported / reading) - kept
  // apart from `error` so a voice note never hides a real answer problem.
  const [voiceNote, setVoiceNote] = useState<string | null>(null);
  const mounted = useRef(true);

  useEffect(() => {
    mounted.current = true;
    return () => {
      mounted.current = false;
    };
  }, []);

  const loadHelp = useCallback(async () => {
    setLoadingHelp(true);
    try {
      const res = await fetchWithTimeout(`${API_BASE_URL}/api/ai/concierge/help?locale=${lang}`, {
        headers: { Accept: 'application/json' },
      });
      if (!res.ok) return;
      const json = (await res.json()) as HelpPayload;
      if (mounted.current && json && json.topics) setHelp(json);
    } catch {
      // The ask box stays usable without the topic and example lists.
    } finally {
      if (mounted.current) setLoadingHelp(false);
    }
  }, [lang]);

  // Open the panel, and refetch the server-supplied content whenever the
  // language changes: topics, examples, notices and link labels are localized
  // by the API, so they are never cached across a switch.
  useEffect(() => {
    if (!visible) return;
    setHelp(null);
    setAnswer(null);
    setError(null);
    setVoiceNote(null);
    loadHelp();
  }, [visible, lang, loadHelp]);

  const ask = useCallback(
    async (value: string) => {
      const text = value.trim();
      if (text.length < 3 || asking) return;
      setQuestion(text);
      setAsking(true);
      setError(null);
      setAnswer(null);
      setVoiceNote(null);
      try {
        const res = await fetchWithTimeout(`${API_BASE_URL}/api/ai/concierge/ask`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ locale: lang, question: text }),
        });
        const json = (await res.json()) as AskPayload;
        if (!mounted.current) return;
        if (!res.ok || !json.success) {
          setError(json.error || t('public_concierge.error'));
          setAnswer(null);
          return;
        }
        setAnswer(json);
      } catch {
        if (mounted.current) setError(t('public_concierge.error'));
      } finally {
        if (mounted.current) setAsking(false);
      }
    },
    [asking, lang, t]
  );

  const align = { textAlign: isRtl ? ('right' as const) : ('left' as const) };
  const row = { flexDirection: isRtl ? ('row-reverse' as const) : ('row' as const) };

  const renderLinks = (links: ConciergeLink[] | undefined) => {
    if (!links || links.length === 0) return null;
    return (
      <View style={styles.linkWrap}>
        {links.map((link) => (
          <TouchableOpacity
            key={`${link.id}-${link.url}`}
            style={styles.linkButton}
            onPress={() => openLink(link.url)}
            activeOpacity={0.8}>
            <Ionicons name="open-outline" size={15} color="#4f46e5" />
            <Text style={styles.linkText}>{link.label}</Text>
          </TouchableOpacity>
        ))}
      </View>
    );
  };

  const renderAnswer = () => {
    if (asking) {
      return (
        <View style={[styles.loadingRow, row]}>
          <ActivityIndicator size="small" color="#4f46e5" />
          <Text style={[styles.loadingText, align]}>{t('public_concierge.loading')}</Text>
        </View>
      );
    }

    if (error) {
      return (
        <View style={styles.errorBox}>
          <Text style={[styles.errorText, align]}>{error}</Text>
        </View>
      );
    }

    if (!answer) return null;

    const declined = answer.intent === 'refused' || answer.intent === 'unknown';
    const products = answer.results?.products ?? [];
    const businesses = answer.results?.businesses ?? [];
    // Capability #23: read aloud the SAME localized answer shown on screen -
    // never a second, generated one.
    const spoken = answer.answer
      ? [answer.answer.title, answer.answer.body, ...(answer.answer.bullets || [])]
          .filter(Boolean)
          .join('. ')
      : '';

    return (
      <View style={styles.answerBox}>
        {declined ? (
          <Text style={[styles.body, align]}>{answer.notice || t('public_concierge.unknown')}</Text>
        ) : (
          <>
            {answer.answer ? (
              <>
                <Text style={[styles.answerTitle, align]}>{answer.answer.title}</Text>
                <Text style={[styles.body, align]}>{answer.answer.body}</Text>
                {(answer.answer.bullets || []).map((bullet, index) => (
                  <View key={index} style={[styles.bulletRow, row]}>
                    <Text style={styles.bulletDot}>•</Text>
                    <Text style={[styles.bulletText, align]}>{bullet}</Text>
                  </View>
                ))}
                {answer.acknowledgement ? (
                  <Text style={[styles.ack, align]}>{answer.acknowledgement}</Text>
                ) : null}
                {renderLinks(answer.answer.links)}
              </>
            ) : null}

            {products.length > 0 ? (
              <>
                <Text style={[styles.section, align]}>{t('public_concierge.results_title')}</Text>
                {products.map((product) => (
                  <View key={product.id} style={styles.itemRow}>
                    <Text style={[styles.itemName, align]}>{product.name}</Text>
                    <Text style={[styles.itemMeta, align]}>
                      {[product.business_name, `${product.selling_price} ${product.currency}`]
                        .filter(Boolean)
                        .join(' · ')}
                    </Text>
                  </View>
                ))}
              </>
            ) : null}

            {businesses.length > 0 ? (
              <>
                <Text style={[styles.section, align]}>{t('public_concierge.businesses_title')}</Text>
                {businesses.map((business) => (
                  <View key={business.id} style={styles.itemRow}>
                    <Text style={[styles.itemName, align]}>
                      {business.business_name}
                      {business.is_certified ? ' ✓' : ''}
                    </Text>
                    <Text style={[styles.itemMeta, align]}>{business.business_location || ''}</Text>
                  </View>
                ))}
              </>
            ) : null}

            {answer.notice && !answer.answer ? (
              <Text style={[styles.body, align]}>{answer.notice}</Text>
            ) : null}
          </>
        )}

        {/* The model step only ever adds one sentence: say so when it was not
            available, so the answer is never mistaken for a live model reply. */}
        {answer.ai?.degraded ? (
          <Text style={[styles.degraded, align]}>{answer.ai.reason || answer.notice_scope}</Text>
        ) : null}

        {renderLinks(answer.fallback_links)}

        {spoken ? <SpeakButton locale={lang} text={spoken} onMessage={setVoiceNote} /> : null}
      </View>
    );
  };

  return (
    <>
      <TouchableOpacity
        style={styles.card}
        onPress={() => setVisible(true)}
        activeOpacity={0.85}
        accessibilityRole="button">
        <View style={[styles.cardRow, row]}>
          <View style={styles.cardIcon}>
            <Ionicons name="chatbubbles-outline" size={26} color="#4f46e5" />
          </View>
          <View style={styles.cardTextWrap}>
            <Text style={[styles.cardTitle, align]}>{t('public_concierge.title')}</Text>
            <Text style={[styles.cardSubtitle, align]}>{t('public_concierge.subtitle')}</Text>
          </View>
          <View style={styles.cardCta}>
            <Text style={styles.cardCtaText}>{t('public_concierge.open')}</Text>
          </View>
        </View>
      </TouchableOpacity>

      <Modal visible={visible} animationType="slide" transparent onRequestClose={() => setVisible(false)}>
        <SafeAreaView style={styles.modalBackdrop}>
          <KeyboardAvoidingView
            style={styles.modalCard}
            behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
            <View style={[styles.modalHeader, row]}>
              <View style={styles.modalHeaderText}>
                <Text style={[styles.modalTitle, align]}>
                  {help?.title || t('public_concierge.title')}
                </Text>
                <Text style={[styles.modalSubtitle, align]}>
                  {help?.subtitle || t('public_concierge.subtitle')}
                </Text>
              </View>
              <TouchableOpacity
                onPress={() => setVisible(false)}
                style={styles.closeButton}
                accessibilityRole="button"
                accessibilityLabel={t('public_concierge.title')}>
                <Ionicons name="close" size={22} color="#64748b" />
              </TouchableOpacity>
            </View>

            <ScrollView contentContainerStyle={styles.modalBody} keyboardShouldPersistTaps="handled">
              <Text style={[styles.notice, align]}>
                {help?.notice_public || t('public_concierge.help_note')}
              </Text>

              <View style={[styles.askRow, row]}>
                <TextInput
                  style={[styles.input, align]}
                  value={question}
                  onChangeText={setQuestion}
                  placeholder={help?.ask_placeholder || t('public_concierge.ask_placeholder')}
                  placeholderTextColor="#94a3b8"
                  returnKeyType="send"
                  onSubmitEditing={() => ask(question)}
                  editable={!asking}
                />
                {/* Capability #23: the record-and-type alternative in one tap.
                    Recognised text (when a recognizer exists) lands in the
                    input above and is never submitted by itself. */}
                <MicButton locale={lang} onMessage={setVoiceNote} disabled={asking} />
                <TouchableOpacity
                  style={[styles.sendButton, asking && styles.sendButtonDisabled]}
                  onPress={() => ask(question)}
                  disabled={asking}
                  activeOpacity={0.85}>
                  <Text style={styles.sendButtonText}>
                    {help?.ask_button || t('public_concierge.ask_button')}
                  </Text>
                </TouchableOpacity>
              </View>

              <VoiceNote message={voiceNote} align={align} />

              {loadingHelp && !help ? (
                <View style={[styles.loadingRow, row]}>
                  <ActivityIndicator size="small" color="#4f46e5" />
                  <Text style={[styles.loadingText, align]}>{t('public_concierge.loading')}</Text>
                </View>
              ) : null}

              {help && help.topics.length > 0 ? (
                <>
                  <Text style={[styles.section, align]}>{t('public_concierge.topics_title')}</Text>
                  <View style={styles.chipWrap}>
                    {help.topics.map((topic) => (
                      <TouchableOpacity
                        key={topic.id}
                        style={styles.chip}
                        onPress={() => ask(topic.title)}
                        activeOpacity={0.85}>
                        <Text style={styles.chipText}>{topic.label}</Text>
                      </TouchableOpacity>
                    ))}
                  </View>
                </>
              ) : null}

              {help && help.examples.length > 0 ? (
                <>
                  <Text style={[styles.section, align]}>{t('public_concierge.examples_title')}</Text>
                  <View style={styles.chipWrap}>
                    {help.examples.map((example) => (
                      <TouchableOpacity
                        key={example}
                        style={[styles.chip, styles.chipQuiet]}
                        onPress={() => ask(example)}
                        activeOpacity={0.85}>
                        <Text style={[styles.chipText, styles.chipQuietText]}>{example}</Text>
                      </TouchableOpacity>
                    ))}
                  </View>
                </>
              ) : null}

              {!answer && !asking && !error && help ? (
                <Text style={[styles.empty, align]}>{t('public_concierge.empty_state')}</Text>
              ) : null}

              {renderAnswer()}
            </ScrollView>
          </KeyboardAvoidingView>
        </SafeAreaView>
      </Modal>
    </>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    shadowColor: '#0f172a',
    shadowOpacity: 0.08,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 6 },
    elevation: 2,
  },
  cardRow: { alignItems: 'center', gap: 12 },
  cardIcon: {
    width: 46,
    height: 46,
    borderRadius: 23,
    backgroundColor: '#eef2ff',
    alignItems: 'center',
    justifyContent: 'center',
  },
  cardTextWrap: { flex: 1 },
  cardTitle: { fontSize: 15, fontWeight: '700', color: '#0f172a' },
  cardSubtitle: { fontSize: 12.5, color: '#64748b', marginTop: 3, lineHeight: 17 },
  cardCta: { backgroundColor: '#4f46e5', borderRadius: 999, paddingVertical: 7, paddingHorizontal: 12 },
  cardCtaText: { color: '#fff', fontSize: 12.5, fontWeight: '700' },

  modalBackdrop: { flex: 1, backgroundColor: 'rgba(15,23,42,0.45)', justifyContent: 'flex-end' },
  modalCard: {
    backgroundColor: '#fff',
    borderTopLeftRadius: 22,
    borderTopRightRadius: 22,
    maxHeight: '88%',
    overflow: 'hidden',
  },
  modalHeader: {
    alignItems: 'flex-start',
    gap: 10,
    paddingHorizontal: 18,
    paddingTop: 16,
    paddingBottom: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#eef2f7',
  },
  modalHeaderText: { flex: 1 },
  modalTitle: { fontSize: 16, fontWeight: '800', color: '#0f172a' },
  modalSubtitle: { fontSize: 12.5, color: '#64748b', marginTop: 3, lineHeight: 17 },
  closeButton: { padding: 4 },
  modalBody: { paddingHorizontal: 18, paddingTop: 14, paddingBottom: 26 },
  notice: { fontSize: 12, color: '#1e40af', backgroundColor: '#eff6ff', borderRadius: 10, padding: 10, lineHeight: 17 },
  askRow: { alignItems: 'center', gap: 8, marginTop: 12 },
  input: {
    flex: 1,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    color: '#0f172a',
    backgroundColor: '#fff',
  },
  sendButton: { backgroundColor: '#4f46e5', borderRadius: 10, paddingHorizontal: 15, paddingVertical: 11 },
  sendButtonDisabled: { opacity: 0.6 },
  sendButtonText: { color: '#fff', fontSize: 14, fontWeight: '700' },
  section: {
    fontSize: 11.5,
    fontWeight: '700',
    letterSpacing: 0.4,
    textTransform: 'uppercase',
    color: '#334155',
    marginTop: 16,
    marginBottom: 7,
  },
  chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  chip: { backgroundColor: '#eef2ff', borderRadius: 999, paddingVertical: 7, paddingHorizontal: 11 },
  chipText: { color: '#3730a3', fontSize: 12.5, fontWeight: '600' },
  chipQuiet: { backgroundColor: '#f8fafc', borderWidth: 1, borderColor: '#e2e8f0' },
  chipQuietText: { color: '#334155', fontWeight: '500' },
  loadingRow: { alignItems: 'center', gap: 8, marginTop: 14 },
  loadingText: { fontSize: 13, color: '#64748b', flex: 1 },
  empty: { fontSize: 13, color: '#64748b', marginTop: 14 },
  errorBox: { backgroundColor: '#fef2f2', borderRadius: 10, padding: 10, marginTop: 14 },
  errorText: { color: '#991b1b', fontSize: 13 },
  answerBox: { marginTop: 14, borderWidth: 1, borderColor: '#eef2f7', borderRadius: 14, padding: 13, backgroundColor: '#fcfdff' },
  answerTitle: { fontSize: 14.5, fontWeight: '800', color: '#0f172a', marginBottom: 6 },
  body: { fontSize: 13.5, color: '#334155', lineHeight: 20, marginBottom: 8 },
  bulletRow: { alignItems: 'flex-start', gap: 6, marginBottom: 4 },
  bulletDot: { color: '#4f46e5', fontSize: 13 },
  bulletText: { flex: 1, fontSize: 13, color: '#475569', lineHeight: 19 },
  ack: { fontSize: 12.5, color: '#1e40af', backgroundColor: '#eff6ff', borderRadius: 9, padding: 8, marginTop: 6, lineHeight: 17 },
  itemRow: { paddingVertical: 7, borderBottomWidth: 1, borderBottomColor: '#f1f5f9' },
  itemName: { fontSize: 13.5, fontWeight: '700', color: '#0f172a' },
  itemMeta: { fontSize: 12, color: '#64748b', marginTop: 2 },
  degraded: { fontSize: 11.5, color: '#92400e', marginTop: 8, lineHeight: 16 },
  linkWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 10 },
  linkButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    backgroundColor: '#fff',
    borderRadius: 10,
    paddingVertical: 8,
    paddingHorizontal: 11,
  },
  linkText: { color: '#4f46e5', fontSize: 13, fontWeight: '600' },
});
