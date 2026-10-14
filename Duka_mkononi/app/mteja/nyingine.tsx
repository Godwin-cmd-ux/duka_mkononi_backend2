import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import SmartCartLoader from '../../components/SmartCartLoader';
import OnboardingAssistant from '../../components/OnboardingAssistant';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLang } from '../../context/LanguageContext';
import { fetchWithTimeout } from '../../lib/network';

import { API_BASE_URL } from '../../constants/api';

/**
 * Capability #13 - Order & Delivery Q&A (customer mobile screen, "Nyingine").
 *
 * POST /api/ai/orders/ask answers questions about the authenticated
 * customer's own orders: ownership is enforced server-side against the
 * account's phone number, and every figure shown (totals, timeline, status
 * labels) comes from recorded fields. The model only words the answer; when
 * it is unavailable the endpoint still returns a deterministic reply and
 * this screen labels it honestly with the localized ai_note.
 */

interface OrderItem {
  name: string;
  quantity: number;
  unit_price: number;
  line_total: number;
}

interface TimelineEntry {
  status: string;
  status_label: string;
  note: string | null;
  at: string;
}

interface Order {
  order_reference: string;
  order_date: string;
  status: string;
  status_label: string;
  subtotal: number;
  shipping_cost: number;
  total_amount: number;
  currency: string;
  business_name: string;
  items: OrderItem[];
  latest_note: string | null;
  timeline: TimelineEntry[];
}

interface AnswerBlock {
  text: string;
  available: boolean;
  degraded: boolean;
  code?: string | null;
  reason?: string | null;
}

interface QaResponse {
  success: boolean;
  locale: string;
  question: string;
  orders: Order[];
  focused_reference: string | null;
  answer: AnswerBlock;
  message?: string | null;
  status_guide: { status: string; label: string }[];
  limitations: string[];
  generated_at: string;
}

const groupDigits = (value: number): string =>
  Math.round(value)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, ',');

export default function MtejaNyingineScreen() {
  const { t, lang } = useLang();
  const [question, setQuestion] = useState('');
  const [sending, setSending] = useState(false);
  const [loadError, setLoadError] = useState(false);
  const [data, setData] = useState<QaResponse | null>(null);
  const [askedQuestion, setAskedQuestion] = useState('');
  // Capability #19 - getting-started checklist + approved onboarding answers.
  const [guideOpen, setGuideOpen] = useState(false);

  const ask = useCallback(
    async (rawQuestion?: string) => {
      const q = (rawQuestion ?? question).trim();
      if (q === '' || sending) return;

      setSending(true);
      setLoadError(false);
      setAskedQuestion(q);
      try {
        const token = await AsyncStorage.getItem('userToken');
        if (!token) {
          setLoadError(true);
          setSending(false);
          return;
        }
        const res = await fetchWithTimeout(
          `${API_BASE_URL}/api/ai/orders/ask`,
          {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              Accept: 'application/json',
              Authorization: `Bearer ${token}`,
            },
            body: JSON.stringify({ question: q, locale: lang }),
          },
          45000
        );
        if (res.status === 401) {
          setLoadError(true);
        } else if (res.ok) {
          const json = (await res.json()) as QaResponse;
          setData(json);
        } else {
          setLoadError(true);
        }
      } catch (e) {
        setLoadError(true);
      } finally {
        setSending(false);
      }
    },
    [lang, question, sending]
  );

  const renderAnswer = () => {
    if (sending && askedQuestion === '') {
      return (
        <View style={styles.stateBox}>
          <SmartCartLoader size="large" color="#667eea" label={t('mteja_nyingine.loading')} />
          <Text style={styles.stateText}>{t('mteja_nyingine.loading')}</Text>
        </View>
      );
    }

    if (loadError && !data) {
      return (
        <View style={styles.stateBox}>
          <Ionicons name="alert-circle-outline" size={40} color="#e74c3c" />
          <Text style={styles.stateTitle}>{t('mteja_nyingine.error_title')}</Text>
          <Text style={styles.stateText}>{t('mteja_nyingine.error_text')}</Text>
          <TouchableOpacity style={styles.retryBtn} onPress={() => ask(askedQuestion)}>
            <Text style={styles.retryBtnText}>{t('mteja_nyingine.error_title')}</Text>
          </TouchableOpacity>
        </View>
      );
    }

    if (!data) {
      return (
        <View style={styles.stateBox}>
          <Ionicons name="chatbubble-ellipses-outline" size={40} color="#667eea" />
          <Text style={styles.stateTitle}>{t('mteja_nyingine.empty_title')}</Text>
          <Text style={styles.stateText}>{t('mteja_nyingine.empty_text')}</Text>
        </View>
      );
    }

    const orders = data.orders || [];
    const focused = orders.find((o) => o.order_reference === data.focused_reference) || orders[0];
    const answer = data.answer;

    return (
      <View>
        {data.message ? (
          <View style={styles.messageCard}>
            <Text style={styles.messageLabel}>{t('mteja_nyingine.message_label')}</Text>
            <Text style={styles.messageText}>{data.message}</Text>
          </View>
        ) : null}

        {answer?.text ? (
          <View style={styles.answerCard}>
            <View style={styles.answerHead}>
              <Ionicons name="sparkles" size={15} color="#667eea" />
              <Text style={styles.answerLabel}>{t('mteja_nyingine.answer_label')}</Text>
              {data.focused_reference ? (
                <View style={styles.focusChip}>
                  <Text style={styles.focusChipText}>
                    {t('mteja_nyingine.focused')}: {data.focused_reference}
                  </Text>
                </View>
              ) : null}
            </View>
            <Text style={styles.answerText}>{answer.text}</Text>
            {answer.degraded ? (
              <Text style={styles.aiNote}>{answer.reason || t('mteja_nyingine.ai_note')}</Text>
            ) : null}
          </View>
        ) : null}

        {focused ? (
          <View style={styles.orderCard}>
            <View style={styles.orderHead}>
              <Text style={styles.orderRef}>{focused.order_reference}</Text>
              <View style={styles.statusChip}>
                <Text style={styles.statusChipText}>{focused.status_label}</Text>
              </View>
            </View>
            <Text style={styles.orderMeta}>
              {focused.business_name ? `${focused.business_name} · ` : ''}
              {String(focused.order_date || '').slice(0, 10)}
            </Text>

            {focused.items.length > 0 ? (
              <View style={styles.block}>
                <Text style={styles.blockLabel}>{t('mteja_nyingine.items_label')}</Text>
                {focused.items.map((item, index) => (
                  <View key={index} style={styles.itemRow}>
                    <Text style={styles.itemName} numberOfLines={2}>
                      {item.name} × {item.quantity}
                    </Text>
                    <Text style={styles.itemTotal}>
                      {groupDigits(item.line_total)} {focused.currency}
                    </Text>
                  </View>
                ))}
              </View>
            ) : null}

            <Text style={styles.totalRow}>
              {t('mteja_nyingine.total_label')} {groupDigits(focused.total_amount)}{' '}
              {focused.currency}
            </Text>

            {focused.timeline.length > 0 ? (
              <View style={styles.block}>
                <Text style={styles.blockLabel}>{t('mteja_nyingine.timeline_label')}</Text>
                {focused.timeline.map((entry, index) => (
                  <View key={index} style={styles.timelineRow}>
                    <View style={styles.timelineDot} />
                    <View style={{ flex: 1 }}>
                      <Text style={styles.timelineLabel}>
                        {entry.status_label}
                        <Text style={styles.timelineAt}>
                          {'  '}
                          {String(entry.at || '').slice(0, 16).replace('T', ' ')}
                        </Text>
                      </Text>
                      {entry.note ? <Text style={styles.timelineNote}>{entry.note}</Text> : null}
                    </View>
                  </View>
                ))}
              </View>
            ) : null}
          </View>
        ) : null}

        {orders.length > 1 ? (
          <View style={styles.ordersCard}>
            <Text style={styles.blockLabel}>{t('mteja_nyingine.orders_title')}</Text>
            {orders.slice(0, 5).map((order, index) => (
              <View key={index} style={styles.miniOrderRow}>
                <Text style={styles.itemName} numberOfLines={1}>
                  {order.order_reference}
                </Text>
                <Text style={styles.miniOrderStatus}>{order.status_label}</Text>
              </View>
            ))}
          </View>
        ) : null}

        {(data.status_guide || []).length > 0 ? (
          <View style={styles.guideCard}>
            <Text style={styles.blockLabel}>{t('mteja_nyingine.guide_title')}</Text>
            <View style={styles.guideWrap}>
              {data.status_guide.map((row) => (
                <View key={row.status} style={styles.guideChip}>
                  <Text style={styles.guideChipText}>{row.label}</Text>
                </View>
              ))}
            </View>
          </View>
        ) : null}

        {(data.limitations || []).length > 0 ? (
          <View style={styles.limitationsBox}>
            <Text style={styles.limitationsTitle}>{t('mteja_nyingine.limitations_title')}</Text>
            {data.limitations.map((line, index) => (
              <Text key={index} style={styles.limitationsText}>
                {line}
              </Text>
            ))}
            <Text style={styles.generated}>
              {t('mteja_nyingine.generated', {
                date: String(data.generated_at || '').slice(0, 16).replace('T', ' '),
              })}
            </Text>
          </View>
        ) : null}
      </View>
    );
  };

  return (
    <SafeAreaView style={styles.screen} edges={['top']}>
      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          <View style={styles.titleRow}>
            <Text style={styles.title}>{t('mteja_nyingine.title')}</Text>
            <View style={styles.aiChip}>
              <Ionicons name="sparkles" size={11} color="#667eea" />
              <Text style={styles.aiChipText}>{t('mteja_nyingine.ai_badge')}</Text>
            </View>
          </View>
          <Text style={styles.subtitle}>{t('mteja_nyingine.subtitle')}</Text>

          <TouchableOpacity style={styles.guideEntry} onPress={() => setGuideOpen(true)}>
            <Ionicons name="help-circle-outline" size={16} color="#667eea" />
            <Text style={styles.guideEntryText}>{t('onboarding_ai.title')}</Text>
          </TouchableOpacity>

          <View style={styles.askCard}>
            <TextInput
              style={styles.input}
              value={question}
              onChangeText={setQuestion}
              placeholder={t('mteja_nyingine.ask_placeholder')}
              placeholderTextColor="#95a5a6"
              multiline
              maxLength={500}
              editable={!sending}
            />
            <TouchableOpacity
              style={[styles.askBtn, (sending || question.trim() === '') && styles.askBtnDisabled]}
              disabled={sending || question.trim() === ''}
              onPress={() => ask()}
            >
              {sending ? (
                <ActivityIndicator color="#fff" size="small" />
              ) : (
                <>
                  <Ionicons name="send" size={15} color="#fff" />
                  <Text style={styles.askBtnText}>{t('mteja_nyingine.ask_btn')}</Text>
                </>
              )}
            </TouchableOpacity>
            <Text style={styles.exampleHint}>{t('mteja_nyingine.example_hint')}</Text>
          </View>

          {renderAnswer()}
        </ScrollView>

        <OnboardingAssistant visible={guideOpen} onClose={() => setGuideOpen(false)} />
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: '#f8f9fa' },
  content: { padding: 16, paddingBottom: 40 },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
  title: { fontSize: 22, fontWeight: '800', color: '#2c3e50' },
  subtitle: { fontSize: 13, color: '#7f8c8d', marginTop: 3, marginBottom: 14 },
  guideEntry: {
    flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: '#eef1ff',
    borderColor: '#d6ddff', borderWidth: 1, borderRadius: 12,
    paddingHorizontal: 14, paddingVertical: 11, marginBottom: 14,
  },
  guideEntryText: { flex: 1, fontSize: 13.5, fontWeight: '700', color: '#667eea' },
  aiChip: {
    flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: '#eef1ff',
    borderColor: '#d6ddff', borderWidth: 1, borderRadius: 999, paddingHorizontal: 9, paddingVertical: 3,
  },
  aiChipText: { fontSize: 10.5, fontWeight: '800', color: '#667eea', letterSpacing: 0.4 },
  askCard: {
    backgroundColor: '#fff', borderColor: '#ecf0f1', borderWidth: 1, borderRadius: 16,
    padding: 14, marginBottom: 16,
  },
  input: {
    minHeight: 74, fontSize: 14.5, color: '#2c3e50', lineHeight: 20,
    backgroundColor: '#f8f9fa', borderRadius: 12, paddingHorizontal: 12, paddingVertical: 10,
    textAlignVertical: 'top',
  },
  askBtn: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 7,
    marginTop: 10, backgroundColor: '#667eea', borderRadius: 12, paddingVertical: 12,
  },
  askBtnDisabled: { opacity: 0.5 },
  askBtnText: { color: '#fff', fontWeight: '700', fontSize: 14.5 },
  exampleHint: { fontSize: 12, color: '#95a5a6', marginTop: 9, textAlign: 'center' },
  stateBox: { alignItems: 'center', paddingVertical: 40, paddingHorizontal: 18 },
  stateTitle: { fontSize: 16, fontWeight: '700', color: '#2c3e50', marginTop: 10 },
  stateText: { fontSize: 13.5, color: '#7f8c8d', textAlign: 'center', marginTop: 5, lineHeight: 19 },
  retryBtn: {
    marginTop: 14, backgroundColor: '#667eea', borderRadius: 12, paddingHorizontal: 20, paddingVertical: 10,
  },
  retryBtnText: { fontWeight: '700', color: '#fff', fontSize: 14 },
  messageCard: {
    backgroundColor: '#fff8e8', borderColor: '#f5dfa8', borderWidth: 1,
    borderRadius: 14, padding: 13, marginBottom: 14,
  },
  messageLabel: { fontSize: 10.5, fontWeight: '800', color: '#b7861f', letterSpacing: 0.6, textTransform: 'uppercase' },
  messageText: { fontSize: 13.5, color: '#7a5c14', marginTop: 4, lineHeight: 19 },
  answerCard: {
    backgroundColor: '#fff', borderColor: '#e3e8ff', borderWidth: 1, borderRadius: 16,
    padding: 15, marginBottom: 14,
  },
  answerHead: { flexDirection: 'row', alignItems: 'center', gap: 7, flexWrap: 'wrap' },
  answerLabel: { fontSize: 14.5, fontWeight: '800', color: '#2c3e50' },
  focusChip: { backgroundColor: '#eef1ff', borderRadius: 999, paddingHorizontal: 9, paddingVertical: 3 },
  focusChipText: { fontSize: 11, fontWeight: '700', color: '#5566d4' },
  answerText: { fontSize: 14.5, color: '#34495e', lineHeight: 22, marginTop: 9 },
  aiNote: { fontSize: 12, color: '#95a5a6', marginTop: 9, fontStyle: 'italic' },
  orderCard: {
    backgroundColor: '#fff', borderColor: '#ecf0f1', borderWidth: 1, borderRadius: 16,
    padding: 15, marginBottom: 14,
  },
  orderHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 },
  orderRef: { fontSize: 15, fontWeight: '800', color: '#2c3e50', flex: 1 },
  statusChip: { backgroundColor: '#eafaf1', borderRadius: 999, paddingHorizontal: 10, paddingVertical: 4 },
  statusChipText: { fontSize: 11.5, fontWeight: '800', color: '#27ae60' },
  orderMeta: { fontSize: 12.5, color: '#7f8c8d', marginTop: 4 },
  block: { marginTop: 11 },
  blockLabel: { fontSize: 10.5, fontWeight: '800', color: '#95a5a6', letterSpacing: 0.6, textTransform: 'uppercase', marginBottom: 6 },
  itemRow: {
    flexDirection: 'row', justifyContent: 'space-between', gap: 10,
    backgroundColor: '#f8f9fa', borderRadius: 9, paddingHorizontal: 10, paddingVertical: 7, marginBottom: 5,
  },
  itemName: { flex: 1, fontSize: 13, color: '#34495e' },
  itemTotal: { fontSize: 13, fontWeight: '700', color: '#2c3e50' },
  totalRow: { fontSize: 14, fontWeight: '800', color: '#2c3e50', marginTop: 9 },
  timelineRow: { flexDirection: 'row', gap: 9, marginBottom: 8, alignItems: 'flex-start' },
  timelineDot: { width: 8, height: 8, borderRadius: 4, backgroundColor: '#667eea', marginTop: 5 },
  timelineLabel: { fontSize: 13, fontWeight: '700', color: '#34495e' },
  timelineAt: { fontSize: 11.5, fontWeight: '400', color: '#95a5a6' },
  timelineNote: { fontSize: 12.5, color: '#7f8c8d', marginTop: 2, lineHeight: 17 },
  ordersCard: {
    backgroundColor: '#fff', borderColor: '#ecf0f1', borderWidth: 1, borderRadius: 16,
    padding: 15, marginBottom: 14,
  },
  miniOrderRow: {
    flexDirection: 'row', justifyContent: 'space-between', gap: 10,
    borderTopWidth: 1, borderTopColor: '#f4f6f9', paddingVertical: 7,
  },
  miniOrderStatus: { fontSize: 12.5, fontWeight: '700', color: '#667eea' },
  guideCard: {
    backgroundColor: '#fff', borderColor: '#ecf0f1', borderWidth: 1, borderRadius: 16,
    padding: 15, marginBottom: 14,
  },
  guideWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 7 },
  guideChip: { backgroundColor: '#f1f3f8', borderRadius: 999, paddingHorizontal: 11, paddingVertical: 5 },
  guideChipText: { fontSize: 12, fontWeight: '600', color: '#44546a' },
  limitationsBox: { marginTop: 4 },
  limitationsTitle: { fontSize: 12, fontWeight: '800', color: '#7f8c8d' },
  limitationsText: { fontSize: 12, color: '#95a5a6', lineHeight: 17, marginTop: 3 },
  generated: { fontSize: 11.5, color: '#aeb6bf', marginTop: 6 },
});
