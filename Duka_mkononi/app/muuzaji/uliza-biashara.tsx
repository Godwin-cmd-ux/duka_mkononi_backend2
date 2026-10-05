import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Keyboard,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLang } from '../../context/LanguageContext';
import { fetchWithTimeout } from '../../lib/network';

import { API_BASE_URL } from '../../constants/api';

/**
 * Capability #4 - Natural-Language Business Reporting (seller mobile screen).
 *
 * The seller types an ordinary-language question; POST /api/ai/report/ask maps
 * it to an allow-listed read-only report computed in trusted PHP over this
 * business's own data. The screen only renders the returned figures.
 */

interface ReportTable {
  columns: string[];
  rows: (string | number | null)[][];
}

interface ReportChart {
  type: string;
  labels: string[];
  values: number[];
  metric?: string;
}

interface ReportResponse {
  success: boolean;
  locale: string;
  generated_at: string;
  question: string;
  currency: string;
  understanding: {
    tool: string | null;
    metric: string;
    range: { from: string; to: string } | null;
    range_key: string | null;
    confidence: string;
  };
  answer: { summary_text: string };
  data: Record<string, unknown>;
  table: ReportTable | null;
  chart: ReportChart | null;
  sources: string[];
  limitations: string[];
  ai: {
    available: boolean;
    degraded: boolean;
    code: string | null;
    reason?: string | null;
    explanation: string;
  };
}

const EXAMPLE_KEYS = ['example_1', 'example_2', 'example_3', 'example_4', 'example_5', 'example_6'];

const METRIC_KEY: Record<string, string> = {
  revenue: 'metric_revenue',
  profit: 'metric_profit',
  units: 'metric_units',
};

const COLUMN_KEY: Record<string, string> = {
  name: 'col_name',
  units: 'col_units',
  revenue: 'col_revenue',
  profit: 'col_profit',
  category: 'col_category',
  amount: 'col_amount',
  total_purchases: 'col_total_purchases',
  purchases_count: 'col_purchases',
};

const MONEY_COLUMNS = ['amount', 'revenue', 'profit', 'total_purchases'];

export default function UlizaBiasharaScreen() {
  const { t, lang } = useLang();

  const [question, setQuestion] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [result, setResult] = useState<ReportResponse | null>(null);

  const formatMoney = (amount: number | null | undefined) => {
    if (typeof amount !== 'number' || !Number.isFinite(amount)) return '-';
    const formatted = new Intl.NumberFormat('en-TZ', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount);
    return `${result?.currency || 'TZS'} ${formatted}`;
  };

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
        setError(t('business_report.error_question'));
        return;
      }

      Keyboard.dismiss();
      setQuestion(text);
      setLoading(true);
      setError(null);

      try {
        const token = await AsyncStorage.getItem('userToken');
        if (!token) {
          setError(t('business_report.error_auth'));
          return;
        }

        const response = await fetchWithTimeout(
          `${API_BASE_URL}/api/ai/report/ask`,
          {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              Authorization: `Bearer ${token}`,
              'ngrok-skip-browser-warning': 'true',
            },
            body: JSON.stringify({ question: text, locale: lang }),
          },
          45000
        );

        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload) {
          setError(t('business_report.error_load'));
          return;
        }

        setResult(payload as ReportResponse);
      } catch (err) {
        console.error('Error asking business report:', err);
        setError(t('business_report.error_network'));
      } finally {
        setLoading(false);
      }
    },
    [lang, question, t]
  );

  const renderExamples = () => (
    <View style={styles.section}>
      <Text style={styles.examplesTitle}>{t('business_report.examples_title')}</Text>
      {EXAMPLE_KEYS.map((key) => (
        <TouchableOpacity key={key} style={styles.chip} onPress={() => ask(t('business_report.' + key))}>
          <Ionicons name="chatbubble-ellipses-outline" size={14} color="#2ecc71" />
          <Text style={styles.chipText}>{t('business_report.' + key)}</Text>
        </TouchableOpacity>
      ))}
    </View>
  );

  const renderUnderstanding = () => {
    if (!result) return null;
    const u = result.understanding;
    if (u.tool === null) {
      return (
        <View style={[styles.card, { backgroundColor: '#fef5e7' }]}>
          <Text style={[styles.cardTitle, { color: '#b9770e' }]}>
            {t('business_report.unknown_title')}
          </Text>
          <Text style={styles.bodyText}>{result.answer.summary_text}</Text>
        </View>
      );
    }

    return (
      <View style={styles.metaRow}>
        {u.range && (
          <Text style={styles.metaText}>
            {t('business_report.range_label')}: {u.range.from} – {u.range.to}
          </Text>
        )}
        {u.metric && METRIC_KEY[u.metric] && (
          <Text style={styles.metaText}>
            {t('business_report.metric_' + u.metric)}
          </Text>
        )}
      </View>
    );
  };

  const renderTable = () => {
    if (!result?.table || result.table.rows.length === 0) return null;
    return (
      <View style={styles.card}>
        <Text style={styles.cardTitle}>{t('business_report.table_title')}</Text>
        <View style={styles.tableHeader}>
          {result.table.columns.map((c) => (
            <Text key={c} style={[styles.tableHeaderCell, { flex: c === 'name' || c === 'category' ? 2 : 1 }]}>
              {t('business_report.' + (COLUMN_KEY[c] || 'col_name'))}
            </Text>
          ))}
        </View>
        {result.table.rows.map((row, rowIndex) => (
          <View key={rowIndex} style={styles.tableRow}>
            {row.map((cell, cellIndex) => {
              const column = result.table!.columns[cellIndex];
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

  const renderChart = () => {
    if (!result?.chart || result.chart.values.length === 0) return null;
    const max = Math.max(...result.chart.values.map((v) => Math.abs(v)), 1);
    return (
      <View style={styles.card}>
        <Text style={styles.cardTitle}>{t('business_report.chart_title')}</Text>
        {result.chart.labels.map((label, index) => {
          const value = result.chart!.values[index] ?? 0;
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
                {result.chart!.metric === 'expenses' || result.chart!.metric === 'revenue'
                  ? formatMoney(value)
                  : String(value)}
              </Text>
            </View>
          );
        })}
      </View>
    );
  };

  const renderResult = () => {
    if (!result) return null;
    return (
      <>
        {renderUnderstanding()}
        <View style={[styles.card, styles.answerCard]}>
          <Text style={styles.cardTitle}>{t('business_report.answer_title')}</Text>
          <Text style={styles.answerText}>{result.answer.summary_text}</Text>
        </View>
        {result.ai.explanation ? (
          <View style={styles.aiCard}>
            <Text style={styles.aiCardTitle}>
              <Ionicons name="sparkles" size={13} /> {t('business_report.ai_ready')}
            </Text>
            <Text style={styles.aiCardText}>{result.ai.explanation}</Text>
          </View>
        ) : result.ai.code && result.ai.code !== 'AI_CLASSIFIER_ONLY' ? (
          <Text style={styles.muted}>
            {t('business_report.ai_degraded_title')}
            {result.ai.reason ? ` — ${result.ai.reason}` : ''}
          </Text>
        ) : null}
        {renderChart()}
        {renderTable()}
        {result.limitations.length > 0 && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>{t('business_report.limitations_title')}</Text>
            {result.limitations.map((item, index) => (
              <View key={index} style={styles.listRow}>
                <Text style={styles.listBullet}>•</Text>
                <Text style={styles.listText}>{item}</Text>
              </View>
            ))}
          </View>
        )}
      </>
    );
  };

  return (
    <SafeAreaView style={styles.container} edges={['top', 'left', 'right']}>
      <View style={styles.header}>
        <Text style={styles.title}>{t('business_report.title')}</Text>
      </View>

      <View style={styles.inputBar}>
        <TextInput
          style={styles.input}
          value={question}
          onChangeText={setQuestion}
          placeholder={t('business_report.placeholder')}
          placeholderTextColor="#95a5a6"
          returnKeyType="send"
          onSubmitEditing={() => ask()}
          editable={!loading}
        />
        <TouchableOpacity style={styles.askBtn} onPress={() => ask()} disabled={loading}>
          {loading ? (
            <ActivityIndicator size="small" color="#ffffff" />
          ) : (
            <Ionicons name="send" size={18} color="#ffffff" />
          )}
        </TouchableOpacity>
      </View>

      <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
        {loading && (
          <View style={styles.center}>
            <ActivityIndicator size="large" color="#2ecc71" />
            <Text style={styles.centerText}>{t('business_report.loading')}</Text>
          </View>
        )}

        {!loading && error && (
          <View style={styles.center}>
            <Ionicons name="alert-circle" size={44} color="#e74c3c" />
            <Text style={styles.centerText}>{error}</Text>
          </View>
        )}

        {!loading && !error && !result && (
          <View style={styles.center}>
            <Ionicons name="chatbubbles-outline" size={48} color="#bdc3c7" />
            <Text style={styles.emptyTitle}>{t('business_report.empty_title')}</Text>
            <Text style={styles.centerText}>{t('business_report.empty_hint')}</Text>
          </View>
        )}

        {!loading && !error && result && renderResult()}
        {!loading && renderExamples()}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f8f9fa' },
  header: {
    paddingHorizontal: 20,
    paddingVertical: 14,
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: '#ecf0f1',
  },
  title: { fontSize: 18, fontWeight: '700', color: '#2c3e50' },
  inputBar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingHorizontal: 16,
    paddingVertical: 10,
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: '#ecf0f1',
  },
  input: {
    flex: 1,
    backgroundColor: '#f4f6f7',
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 10,
    fontSize: 14,
    color: '#2c3e50',
  },
  askBtn: {
    width: 44,
    height: 44,
    borderRadius: 12,
    backgroundColor: '#2ecc71',
    alignItems: 'center',
    justifyContent: 'center',
  },
  scroll: { padding: 16, paddingBottom: 40 },
  center: { alignItems: 'center', paddingVertical: 40, paddingHorizontal: 20 },
  centerText: { marginTop: 10, color: '#7f8c8d', fontSize: 14, textAlign: 'center' },
  emptyTitle: { marginTop: 12, fontSize: 18, fontWeight: '700', color: '#2c3e50' },
  section: { marginBottom: 16 },
  sectionTitle: { fontSize: 15, fontWeight: '700', color: '#2c3e50', marginBottom: 8 },
  examplesTitle: { fontSize: 13, fontWeight: '600', color: '#7f8c8d', marginBottom: 8 },
  chip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#ffffff',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginBottom: 8,
  },
  chipText: { flex: 1, fontSize: 13, color: '#2c3e50' },
  card: { backgroundColor: '#ffffff', borderRadius: 16, padding: 16, marginBottom: 14 },
  answerCard: { backgroundColor: '#f4f9ff' },
  cardTitle: { fontSize: 14, fontWeight: '700', color: '#2c3e50', marginBottom: 8 },
  bodyText: { fontSize: 13, color: '#5d6d7e', lineHeight: 19 },
  answerText: { fontSize: 15, color: '#2c3e50', lineHeight: 22, fontWeight: '600' },
  aiCard: {
    backgroundColor: '#e8f8f0',
    borderRadius: 14,
    padding: 14,
    marginBottom: 14,
  },
  aiCardTitle: { fontSize: 12, fontWeight: '700', color: '#1e8449', marginBottom: 4 },
  aiCardText: { fontSize: 13, color: '#1e8449', lineHeight: 19 },
  metaRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 12, marginBottom: 10 },
  metaText: { fontSize: 12, color: '#95a5a6' },
  muted: { fontSize: 12, color: '#95a5a6', marginBottom: 12 },
  tableHeader: { flexDirection: 'row', borderBottomWidth: 1, borderBottomColor: '#ecf0f1', paddingBottom: 6 },
  tableHeaderCell: { fontSize: 12, fontWeight: '700', color: '#2c3e50', paddingRight: 6 },
  tableRow: { flexDirection: 'row', paddingVertical: 6, borderBottomWidth: 1, borderBottomColor: '#f4f6f7' },
  tableCell: { fontSize: 12, color: '#5d6d7e', paddingRight: 6 },
  barRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 8 },
  barLabel: { width: 90, fontSize: 11, color: '#5d6d7e' },
  barTrack: { flex: 1, height: 10, backgroundColor: '#ecf0f1', borderRadius: 5, overflow: 'hidden' },
  barFill: { height: 10, backgroundColor: '#2ecc71', borderRadius: 5 },
  barValue: { width: 90, fontSize: 11, color: '#2c3e50', textAlign: 'right' },
  listRow: { flexDirection: 'row', gap: 8, marginBottom: 6 },
  listBullet: { fontSize: 14, fontWeight: '700', color: '#95a5a6' },
  listText: { flex: 1, fontSize: 12, color: '#5d6d7e', lineHeight: 17 },
});
