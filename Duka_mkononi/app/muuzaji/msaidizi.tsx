import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useRef, useState, useMemo } from 'react';
import {
  Alert,
  Keyboard,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import SmartCartLoader from '../../components/SmartCartLoader';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLang } from '../../context/LanguageContext';
import { fetchWithTimeout } from '../../lib/network';

import { API_BASE_URL } from '../../constants/api';
import { useTheme, ThemeColors } from '../../lib/theme';

/**
 * Capability #10 - Seller Copilot Q&A (seller mobile screen).
 *
 * A persistent, multi-turn assistant: POST /api/ai/copilot/chat routes each
 * message to an allow-listed READ-ONLY tool (report / restock / health /
 * anomalies / expense draft / help) computed in trusted PHP over this
 * business's own rows, and the screen only renders the returned figures.
 * Conversation history is resumed and deleted through
 * /api/ai/copilot/conversations - nothing here writes a business record.
 */

interface QuickAction {
  key: string;
  href: string;
  label: string;
}

interface Highlight {
  label: string;
  value: number | string;
  kind?: string;
}

interface ToolResult {
  tool: string;
  period: { from?: string; to?: string; window_days?: number } | null;
  data: Record<string, unknown>;
  table: { columns: string[]; rows: (string | number | null)[][] } | null;
  chart: { type: string; labels: string[]; values: number[]; metric?: string } | null;
  highlights: Highlight[];
  sources: string[];
  limitations: string[];
  summary_text: string;
}

interface AiBlock {
  available: boolean;
  degraded: boolean;
  code: string | null;
  reason?: string | null;
}

interface ChatMessage {
  role: 'user' | 'assistant';
  content: string;
  toolLabel?: string;
  periodLabel?: string;
  toolResult?: ToolResult;
  nextSteps?: string[];
  actions?: QuickAction[];
  ai?: AiBlock | null;
}

interface StoredMessage {
  role: string;
  content: string;
  tool?: string | null;
  tool_label?: string;
}

interface ConversationRow {
  id: string;
  title: string;
  message_count: number;
  updated_at: string;
}

const EXAMPLE_KEYS = ['example_1', 'example_2', 'example_3', 'example_4', 'example_5', 'example_6'];

const COLUMN_KEY: Record<string, string> = {
  name: 'business_report.col_name',
  units: 'business_report.col_units',
  revenue: 'business_report.col_revenue',
  profit: 'business_report.col_profit',
  category: 'business_report.col_category',
  amount: 'business_report.col_amount',
  total_purchases: 'business_report.col_total_purchases',
  purchases_count: 'business_report.col_purchases',
  stock: 'restock_planner.label_stock',
  days: 'restock_planner.label_days_left',
  proposed: 'restock_planner.label_proposed',
  component: 'muuzaji_msaidizi.col_component',
  score: 'muuzaji_msaidizi.col_score',
};

const SOURCE_KEY: Record<string, string> = {
  sales: 'src_sales',
  sale_items: 'src_sales',
  products: 'src_products',
  office_expenses: 'src_expenses',
  customers: 'src_customers',
};

const MONEY_COLUMNS = ['amount', 'revenue', 'profit', 'total_purchases'];

export default function MsaidiziScreen() {
  const { colors: tc } = useTheme();
  const styles = useMemo(() => makeStyles(tc), [tc]);
  const { t, lang } = useLang();
  const router = useRouter();

  const [question, setQuestion] = useState('');
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [showHistory, setShowHistory] = useState(false);
  const [history, setHistory] = useState<ConversationRow[]>([]);
  const [currency, setCurrency] = useState('TZS');
  const scrollRef = useRef<ScrollView | null>(null);

  useEffect(() => {
    const timer = setTimeout(() => {
      scrollRef.current?.scrollToEnd({ animated: true });
    }, 120);
    return () => clearTimeout(timer);
  }, [messages.length, sending]);

  const formatMoney = useCallback(
    (amount: number | null | undefined) => {
      if (typeof amount !== 'number' || !Number.isFinite(amount)) return '-';
      const formatted = new Intl.NumberFormat('en-TZ', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(amount);
      return `${currency} ${formatted}`;
    },
    [currency]
  );

  const formatCell = (column: string, value: string | number | null) => {
    if (value === null || value === undefined || value === '') return '-';
    if (MONEY_COLUMNS.includes(column)) {
      return typeof value === 'number' ? formatMoney(value) : String(value);
    }
    return String(value);
  };

  const ask = useCallback(
    async (raw?: string) => {
      const text = (raw ?? question).trim();
      if (text === '') {
        setError(t('muuzaji_msaidizi.error_empty'));
        return;
      }

      Keyboard.dismiss();
      setQuestion('');
      setError(null);
      setSending(true);
      setMessages((prev) => [...prev, { role: 'user', content: text }]);

      try {
        const token = await AsyncStorage.getItem('userToken');
        if (!token) {
          setError(t('muuzaji_msaidizi.error_auth'));
          return;
        }

        const body: Record<string, unknown> = { question: text, locale: lang };
        if (conversationId) body.conversation_id = conversationId;

        const response = await fetchWithTimeout(
          `${API_BASE_URL}/api/ai/copilot/chat`,
          {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              Authorization: `Bearer ${token}`,
              'ngrok-skip-browser-warning': 'true',
            },
            body: JSON.stringify(body),
          },
          45000
        );

        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload) {
          setError((payload && payload.error) || t('muuzaji_msaidizi.error_load'));
          return;
        }

        setCurrency(payload.currency || 'TZS');
        setConversationId(payload.conversation.id);

        const tr: ToolResult | undefined = payload.tool_result;
        let periodLabel = '';
        if (tr?.period) {
          if (tr.period.from) periodLabel = `${tr.period.from} – ${tr.period.to}`;
          else if (tr.period.window_days) periodLabel = `${tr.period.window_days}d`;
        }

        setMessages((prev) => [
          ...prev,
          {
            role: 'assistant',
            content: payload.message.content,
            toolLabel: payload.message.tool_label,
            periodLabel,
            toolResult: tr,
            nextSteps: payload.next_steps || [],
            actions: payload.quick_actions || [],
            ai: payload.ai || null,
          },
        ]);
      } catch (err) {
        console.error('Error asking copilot:', err);
        setError(t('muuzaji_msaidizi.error_network'));
      } finally {
        setSending(false);
      }
    },
    [conversationId, lang, question, t]
  );

  const loadHistory = useCallback(async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;
      const response = await fetchWithTimeout(
        `${API_BASE_URL}/api/ai/copilot/conversations`,
        {
          method: 'GET',
          headers: { Authorization: `Bearer ${token}`, 'ngrok-skip-browser-warning': 'true' },
        },
        20000
      );
      const payload = await response.json().catch(() => null);
      setHistory(payload && payload.conversations ? payload.conversations : []);
    } catch (err) {
      console.error('Error loading copilot history:', err);
      setHistory([]);
    }
  }, []);

  const openHistoryPanel = useCallback(() => {
    setShowHistory(true);
    loadHistory();
  }, [loadHistory]);

  const openConversation = useCallback(
    async (id: string) => {
      try {
        const token = await AsyncStorage.getItem('userToken');
        if (!token) return;
        const response = await fetchWithTimeout(
          `${API_BASE_URL}/api/ai/copilot/conversations/${encodeURIComponent(id)}`,
          {
            method: 'GET',
            headers: { Authorization: `Bearer ${token}`, 'ngrok-skip-browser-warning': 'true' },
          },
          20000
        );
        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload) {
          setError(t('muuzaji_msaidizi.error_load'));
          return;
        }
        setConversationId(id);
        setError(null);
        setMessages(
          ((payload.messages || []) as StoredMessage[]).map((row) => ({
            role: row.role === 'assistant' ? 'assistant' : 'user',
            content: row.content,
            toolLabel: row.tool_label || '',
          })) as ChatMessage[]
        );
        setShowHistory(false);
      } catch (err) {
        console.error('Error opening conversation:', err);
        setError(t('muuzaji_msaidizi.error_network'));
      }
    },
    [t]
  );

  const deleteConversation = useCallback(
    async (id: string) => {
      try {
        const token = await AsyncStorage.getItem('userToken');
        if (!token) return;
        await fetchWithTimeout(
          `${API_BASE_URL}/api/ai/copilot/conversations/${encodeURIComponent(id)}`,
          {
            method: 'DELETE',
            headers: { Authorization: `Bearer ${token}`, 'ngrok-skip-browser-warning': 'true' },
          },
          20000
        );
        if (conversationId === id) {
          setConversationId(null);
          setMessages([]);
        }
        loadHistory();
      } catch (err) {
        console.error('Error deleting conversation:', err);
        setError(t('muuzaji_msaidizi.error_network'));
      }
    },
    [conversationId, loadHistory, t]
  );

  const confirmDelete = useCallback(
    (id: string | null) => {
      if (!id) {
        setMessages([]);
        setConversationId(null);
        return;
      }
      Alert.alert(t('muuzaji_msaidizi.delete_confirm'), undefined, [
        { text: t('muuzaji_msaidizi.btn_cancel'), style: 'cancel' },
        {
          text: t('muuzaji_msaidizi.btn_confirm'),
          style: 'destructive',
          onPress: () => deleteConversation(id),
        },
      ]);
    },
    [deleteConversation, t]
  );

  const newConversation = useCallback(() => {
    setMessages([]);
    setConversationId(null);
    setError(null);
    setShowHistory(false);
  }, []);

  /* ---------------- rendering ---------------- */

  const renderTable = (tr: ToolResult) => {
    if (!tr.table || tr.table.rows.length === 0) return null;
    return (
      <View style={styles.toolCard}>
        <Text style={styles.toolCardTitle}>{t('business_report.table_title')}</Text>
        <View style={styles.tableHeader}>
          {tr.table.columns.map((c) => (
            <Text
              key={c}
              style={[styles.tableHeaderCell, { flex: c === 'name' || c === 'category' ? 2 : 1 }]}
              numberOfLines={1}
            >
              {t(COLUMN_KEY[c] || 'business_report.col_name')}
            </Text>
          ))}
        </View>
        {tr.table.rows.map((row, rowIndex) => (
          <View key={rowIndex} style={styles.tableRow}>
            {row.map((cell, cellIndex) => {
              const column = tr.table!.columns[cellIndex];
              return (
                <Text
                  key={cellIndex}
                  style={[styles.tableCell, { flex: column === 'name' || column === 'category' ? 2 : 1 }]}
                  numberOfLines={2}
                >
                  {formatCell(column, cell)}
                </Text>
              );
            })}
          </View>
        ))}
      </View>
    );
  };

  const renderChart = (tr: ToolResult) => {
    if (!tr.chart || tr.chart.values.length === 0) return null;
    const max = Math.max(...tr.chart.values.map((v) => Math.abs(v)), 1);
    return (
      <View style={styles.toolCard}>
        <Text style={styles.toolCardTitle}>{t('business_report.chart_title')}</Text>
        {tr.chart.labels.map((label, index) => {
          const value = tr.chart!.values[index] ?? 0;
          const widthPct = Math.max(4, Math.round((Math.abs(value) / max) * 100));
          return (
            <View key={index} style={styles.barRow}>
              <Text style={styles.barLabel} numberOfLines={1}>
                {label}
              </Text>
              <View style={styles.barTrack}>
                <View style={[styles.barFill, { width: `${widthPct}%` }]} />
              </View>
              <Text style={styles.barValue}>
                {tr.chart!.metric === 'expenses' || tr.chart!.metric === 'revenue'
                  ? formatMoney(value)
                  : String(value)}
              </Text>
            </View>
          );
        })}
      </View>
    );
  };

  const renderAssistant = (m: ChatMessage, index: number) => {
    const tr = m.toolResult;
    const sourceTags: string[] = [];
    if (tr?.sources) {
      tr.sources.forEach((s) => {
        const key = SOURCE_KEY[s];
        const label = key ? t(`muuzaji_msaidizi.${key}`) : s;
        if (sourceTags.indexOf(label) === -1) sourceTags.push(label);
      });
    }

    return (
      <View key={index} style={[styles.bubble, styles.assistantBubble]}>
        {(m.toolLabel || m.periodLabel) && (
          <View style={styles.msgMeta}>
            {m.toolLabel ? (
              <View style={styles.badge}>
                <Ionicons name="sparkles" size={11} color="#1e8449" />
                <Text style={styles.badgeText}>{m.toolLabel}</Text>
              </View>
            ) : null}
            {m.periodLabel ? (
              <Text style={styles.periodText}>
                {t('business_report.range_label')}: {m.periodLabel}
              </Text>
            ) : null}
          </View>
        )}

        <Text style={styles.assistantText}>{m.content}</Text>

        {tr && tr.highlights && tr.highlights.length > 0 && (
          <View style={styles.highlights}>
            {tr.highlights.map((h, hi) => (
              <View key={hi} style={styles.highlight}>
                <Text style={styles.highlightLabel}>{h.label}: </Text>
                <Text style={styles.highlightValue}>
                  {h.kind === 'percent'
                    ? `${h.value}%`
                    : typeof h.value === 'number'
                      ? h.value.toLocaleString('en-TZ')
                      : String(h.value)}
                </Text>
              </View>
            ))}
          </View>
        )}

        {tr ? renderTable(tr) : null}
        {tr ? renderChart(tr) : null}

        {sourceTags.length > 0 && (
          <View style={styles.metaBlock}>
            <Text style={styles.metaTitle}>{t('muuzaji_msaidizi.sources_title')}</Text>
            <View style={styles.tags}>
              {sourceTags.map((label, si) => (
                <View key={si} style={styles.tag}>
                  <Text style={styles.tagText}>{label}</Text>
                </View>
              ))}
            </View>
          </View>
        )}

        {tr && tr.limitations.length > 0 && (
          <View style={styles.metaBlock}>
            {tr.limitations.map((item, li) => (
              <View key={li} style={styles.listRow}>
                <Text style={styles.listBullet}>•</Text>
                <Text style={styles.listText}>{item}</Text>
              </View>
            ))}
          </View>
        )}

        {m.nextSteps && m.nextSteps.length > 0 && (
          <View style={styles.metaBlock}>
            <Text style={styles.metaTitle}>{t('muuzaji_msaidizi.next_steps_title')}</Text>
            {m.nextSteps.map((step, si) => (
              <View key={si} style={styles.listRow}>
                <Ionicons name="arrow-forward" size={12} color="#1e8449" />
                <Text style={styles.stepText}>{step}</Text>
              </View>
            ))}
          </View>
        )}

        {m.actions && m.actions.length > 0 && (
          <View style={styles.actions}>
            {m.actions.map((action) => (
              <TouchableOpacity
                key={action.key}
                style={styles.actionChip}
                onPress={() => router.push(action.href as any)}
              >
                <Ionicons name="open-outline" size={13} color="#27ae60" />
                <Text style={styles.actionText}>{action.label}</Text>
              </TouchableOpacity>
            ))}
          </View>
        )}

        {m.ai && m.ai.degraded ? (
          <View style={styles.degraded}>
            <Ionicons name="information-circle" size={14} color="#b9770e" />
            <Text style={styles.degradedText}>
              {t('muuzaji_msaidizi.degraded_title')}
              {m.ai.reason ? ` — ${m.ai.reason}` : ''}
            </Text>
          </View>
        ) : null}
      </View>
    );
  };

  const renderExamples = () => (
    <View style={styles.section}>
      <Text style={styles.examplesTitle}>{t('business_report.examples_title')}</Text>
      <View style={styles.chips}>
        {EXAMPLE_KEYS.map((key) => (
          <TouchableOpacity
            key={key}
            style={styles.chip}
            onPress={() => ask(t('business_report.' + key))}
            disabled={sending}
          >
            <Ionicons name="chatbubble-ellipses-outline" size={14} color="#2ecc71" />
            <Text style={styles.chipText}>{t('business_report.' + key)}</Text>
          </TouchableOpacity>
        ))}
      </View>
    </View>
  );

  const renderHistory = () => (
    <View style={styles.historyPanel}>
      <View style={styles.historyHead}>
        <Text style={styles.historyTitle}>{t('muuzaji_msaidizi.history_title')}</Text>
        <TouchableOpacity onPress={() => setShowHistory(false)}>
          <Ionicons name="close" size={20} color="#7f8c8d" />
        </TouchableOpacity>
      </View>
      {history.length === 0 ? (
        <Text style={styles.centerText}>{t('muuzaji_msaidizi.no_history')}</Text>
      ) : (
        history.map((row) => (
          <View key={row.id} style={styles.historyRow}>
            <TouchableOpacity style={styles.historyMain} onPress={() => openConversation(row.id)}>
              <Text style={styles.historyRowTitle} numberOfLines={1}>
                {row.title || row.id.slice(0, 8)}
              </Text>
              <Text style={styles.historyRowMeta}>
                {(row.updated_at || '').slice(0, 10)} · {row.message_count}
              </Text>
            </TouchableOpacity>
            <TouchableOpacity onPress={() => confirmDelete(row.id)} style={styles.historyDel}>
              <Ionicons name="trash-outline" size={17} color="#e74c3c" />
            </TouchableOpacity>
          </View>
        ))
      )}
      <TouchableOpacity style={styles.newBtn} onPress={newConversation}>
        <Ionicons name="add-circle-outline" size={16} color="#2ecc71" />
        <Text style={styles.newBtnText}>{t('muuzaji_msaidizi.btn_new')}</Text>
      </TouchableOpacity>
    </View>
  );

  return (
    <SafeAreaView style={styles.container} edges={['top', 'left', 'right']}>
      <View style={styles.header}>
        <View style={styles.headerMain}>
          <Text style={styles.title}>{t('muuzaji_msaidizi.title')}</Text>
          <Text style={styles.subtitle}>{t('muuzaji_msaidizi.subtitle')}</Text>
        </View>
        <View style={styles.headerActions}>
          <TouchableOpacity style={styles.headerBtn} onPress={openHistoryPanel}>
            <Ionicons name="time-outline" size={19} color="#5d6d7e" />
          </TouchableOpacity>
          <TouchableOpacity style={styles.headerBtn} onPress={newConversation}>
            <Ionicons name="add" size={20} color="#5d6d7e" />
          </TouchableOpacity>
          <TouchableOpacity style={styles.headerBtn} onPress={() => confirmDelete(conversationId)}>
            <Ionicons name="trash-outline" size={18} color="#e74c3c" />
          </TouchableOpacity>
        </View>
      </View>

      {showHistory ? (
        renderHistory()
      ) : (
        <ScrollView ref={scrollRef} contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
          {messages.length === 0 && !sending && !error && (
            <View style={styles.center}>
              <Ionicons name="chatbubbles-outline" size={48} color="#bdc3c7" />
              <Text style={styles.emptyTitle}>{t('muuzaji_msaidizi.empty_title')}</Text>
              <Text style={styles.centerText}>{t('muuzaji_msaidizi.empty_hint')}</Text>
            </View>
          )}

          {messages.map((m, index) =>
            m.role === 'user' ? (
              <View key={index} style={[styles.bubble, styles.userBubble]}>
                <Text style={styles.userText}>{m.content}</Text>
              </View>
            ) : (
              renderAssistant(m, index)
            )
          )}

          {sending && (
            <View style={styles.center}>
              <SmartCartLoader size="standard" color="#2ecc71" />
              <Text style={styles.centerText}>{t('muuzaji_msaidizi.loading')}</Text>
            </View>
          )}

          {error ? (
            <View style={styles.degraded}>
              <Ionicons name="alert-circle" size={14} color="#b9770e" />
              <Text style={styles.degradedText}>{error}</Text>
            </View>
          ) : null}

          {messages.length === 0 && !sending ? renderExamples() : null}
        </ScrollView>
      )}

      <View style={styles.inputBar}>
        <TextInput
          style={styles.input}
          value={question}
          onChangeText={setQuestion}
          placeholder={t('muuzaji_msaidizi.placeholder')}
          placeholderTextColor="#95a5a6"
          returnKeyType="send"
          onSubmitEditing={() => ask()}
          editable={!sending}
          maxLength={400}
        />
        <TouchableOpacity style={styles.askBtn} onPress={() => ask()} disabled={sending}>
          {sending ? (
            <SmartCartLoader size="compact" color="#ffffff" />
          ) : (
            <Ionicons name="send" size={18} color="#ffffff" />
          )}
        </TouchableOpacity>
      </View>
    </SafeAreaView>
  );
}

const makeStyles = (tc: ThemeColors) => StyleSheet.create({
  container: { flex: 1, backgroundColor: tc.page },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: tc.card,
    borderBottomWidth: 1,
    borderBottomColor: tc.line,
  },
  headerMain: { flex: 1 },
  title: { fontSize: 17, fontWeight: '700', color: tc.ink },
  subtitle: { fontSize: 11.5, color: tc.muted, marginTop: 2 },
  headerActions: { flexDirection: 'row', gap: 6 },
  headerBtn: {
    width: 38,
    height: 38,
    borderRadius: 11,
    backgroundColor: tc.page,
    alignItems: 'center',
    justifyContent: 'center',
  },
  scroll: { padding: 16, paddingBottom: 30 },
  center: { alignItems: 'center', paddingVertical: 34, paddingHorizontal: 20 },
  centerText: { marginTop: 10, color: tc.muted, fontSize: 13.5, textAlign: 'center' },
  emptyTitle: { marginTop: 12, fontSize: 17, fontWeight: '700', color: tc.ink },
  section: { marginTop: 18 },
  examplesTitle: { fontSize: 13, fontWeight: '600', color: tc.muted, marginBottom: 8 },
  chips: { gap: 8 },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: tc.card,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderWidth: 1,
    borderColor: tc.line,
  },
  chipText: { flex: 1, fontSize: 13, color: tc.ink },
  bubble: { maxWidth: '92%', borderRadius: 16, padding: 12, marginBottom: 12 },
  userBubble: { alignSelf: 'flex-end', backgroundColor: '#2ecc71', borderBottomRightRadius: 4 },
  assistantBubble: { alignSelf: 'flex-start', backgroundColor: '#f4f9ff', borderBottomLeftRadius: 4 },
  userText: { color: '#ffffff', fontSize: 14, lineHeight: 20 },
  assistantText: { color: tc.ink, fontSize: 14, lineHeight: 20 },
  msgMeta: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 6, flexWrap: 'wrap' },
  badge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#e8f8f0',
    borderRadius: 20,
    paddingHorizontal: 9,
    paddingVertical: 3,
  },
  badgeText: { fontSize: 10.5, fontWeight: '700', color: '#1e8449' },
  periodText: { fontSize: 10.5, color: tc.muted },
  highlights: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginTop: 9 },
  highlight: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: tc.card,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: tc.line,
    paddingHorizontal: 8,
    paddingVertical: 5,
  },
  highlightLabel: { fontSize: 11, color: tc.muted },
  highlightValue: { fontSize: 11, fontWeight: '700', color: tc.ink },
  toolCard: {
    backgroundColor: tc.card,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: tc.line,
    padding: 11,
    marginTop: 9,
  },
  toolCardTitle: { fontSize: 12.5, fontWeight: '700', color: tc.ink, marginBottom: 7 },
  tableHeader: { flexDirection: 'row', borderBottomWidth: 1, borderBottomColor: tc.line, paddingBottom: 5 },
  tableHeaderCell: { fontSize: 11, fontWeight: '700', color: tc.ink, paddingRight: 5 },
  tableRow: { flexDirection: 'row', paddingVertical: 6, borderBottomWidth: 1, borderBottomColor: '#f4f6f7' },
  tableCell: { fontSize: 11, color: tc.muted, paddingRight: 5 },
  barRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 7 },
  barLabel: { width: 80, fontSize: 10.5, color: tc.muted },
  barTrack: { flex: 1, height: 10, backgroundColor: '#ecf0f1', borderRadius: 5, overflow: 'hidden' },
  barFill: { height: 10, backgroundColor: '#2ecc71', borderRadius: 5 },
  barValue: { width: 80, fontSize: 10.5, color: tc.ink, textAlign: 'right' },
  metaBlock: { marginTop: 9 },
  metaTitle: { fontSize: 11.5, fontWeight: '700', color: tc.muted, marginBottom: 5 },
  tags: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  tag: { backgroundColor: '#eaf4fc', borderRadius: 7, paddingHorizontal: 8, paddingVertical: 3 },
  tagText: { fontSize: 10.5, fontWeight: '600', color: '#2980b9' },
  listRow: { flexDirection: 'row', gap: 7, marginBottom: 5, alignItems: 'flex-start' },
  listBullet: { fontSize: 13, fontWeight: '700', color: tc.muted2 },
  listText: { flex: 1, fontSize: 11.5, color: tc.muted, lineHeight: 16 },
  stepText: { flex: 1, fontSize: 12, color: '#1e8449', lineHeight: 17 },
  actions: { flexDirection: 'row', flexWrap: 'wrap', gap: 7, marginTop: 10 },
  actionChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: tc.card,
    borderWidth: 1,
    borderColor: '#c8f0da',
    borderRadius: 20,
    paddingHorizontal: 11,
    paddingVertical: 7,
  },
  actionText: { fontSize: 12, fontWeight: '600', color: '#27ae60' },
  degraded: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
    backgroundColor: '#fef5e7',
    borderRadius: 10,
    paddingHorizontal: 11,
    paddingVertical: 9,
    marginTop: 9,
  },
  degradedText: { flex: 1, fontSize: 11.5, color: '#b9770e', lineHeight: 16 },
  historyPanel: { flex: 1, padding: 16 },
  historyHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  historyTitle: { fontSize: 15, fontWeight: '700', color: tc.ink },
  historyRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: tc.card,
    borderRadius: 12,
    marginBottom: 8,
    paddingHorizontal: 12,
  },
  historyMain: { flex: 1, paddingVertical: 11 },
  historyRowTitle: { fontSize: 13.5, fontWeight: '600', color: tc.ink },
  historyRowMeta: { fontSize: 11, color: tc.muted2, marginTop: 2 },
  historyDel: { padding: 10 },
  newBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 7,
    backgroundColor: '#e8f8f0',
    borderRadius: 12,
    paddingVertical: 12,
    marginTop: 6,
  },
  newBtnText: { fontSize: 13.5, fontWeight: '700', color: '#27ae60' },
  inputBar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: 16,
    paddingVertical: 10,
    backgroundColor: tc.card,
    borderTopWidth: 1,
    borderBottomColor: tc.line,
  },
  input: {
    flex: 1,
    backgroundColor: tc.page,
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 10,
    fontSize: 14,
    color: tc.ink,
  },
  askBtn: {
    width: 44,
    height: 44,
    borderRadius: 12,
    backgroundColor: '#2ecc71',
    alignItems: 'center',
    justifyContent: 'center',
  },
});
