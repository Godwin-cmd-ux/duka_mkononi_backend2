// Duka_mkononi/app/system_admin/analytics.tsx
// Capability #20 - Natural-Language Admin Analytics (Super Admin).
//
// A system administrator types a question about PLATFORM OPERATIONS ("How many
// sellers joined last month?") and sees the answer the server computed: a
// number, a short explanation, a table and a simple chart, plus the sources and
// the honest limitations. The screen never computes a number itself and never
// sends anything but the question text and the active locale - the server
// decides which allow-listed metric it may run.
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { useCallback, useEffect, useState, useMemo } from 'react';
import {
  ActivityIndicator,
  SafeAreaView,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import SmartCartLoader from '../../components/SmartCartLoader';
import { API_BASE_URL } from '../../constants/api';
import { getCache, setCache } from '../../db/cache';
import { useLang } from '../../context/LanguageContext';
import { fetchWithTimeout } from '../../lib/network';
import { useTheme, ThemeColors } from '../../lib/theme';

const CACHE_KEY = 'system_admin:analytics:last';

interface TableShape {
  columns: string[];
  rows: (string | number)[][];
}

interface ChartShape {
  type: 'bar' | 'line';
  labels: string[];
  values: number[];
  metric?: string;
}

interface Answer {
  tool: string;
  tool_label: string;
  live: boolean;
  range_label: string;
  value: number | string | null;
  value_label: string;
  explanation: string;
  table: TableShape | null;
  chart: ChartShape | null;
  sources: string[];
  limitations: string[];
}

interface AskResponse {
  success: boolean;
  locale: string;
  scope: string;
  question: string;
  parsed: {
    tool: string | null;
    tool_label: string | null;
    range_key: string | null;
    range_source: string;
    live: boolean;
    confidence: string;
  };
  result: Answer | null;
  notice: string;
  notice_no_sql: string;
  scope_note: string;
  examples: string[];
  acknowledgement: string | null;
  truncated: boolean;
  rows_read: number;
}

export default function SystemAdminAnalytics() {
  const { colors: tc } = useTheme();
  const styles = useMemo(() => makeStyles(tc), [tc]);
  const { t, lang } = useLang();

  const [question, setQuestion] = useState('');
  const [examples, setExamples] = useState<string[]>([]);
  const [answer, setAnswer] = useState<AskResponse | null>(null);
  const [asking, setAsking] = useState(false);
  const [error, setError] = useState('');

  const token = useCallback(() => AsyncStorage.getItem('userToken'), []);

  const loadExamples = useCallback(async () => {
    try {
      const cachedToken = await token();
      const res = await fetchWithTimeout(
        `${API_BASE_URL}/api/ai/platform-analytics/examples?locale=${encodeURIComponent(lang)}`,
        { headers: { Authorization: `Bearer ${cachedToken}` } },
      );
      if (!res.ok) return;
      const data = await res.json();
      setExamples(Array.isArray(data.examples) ? data.examples : []);
    } catch (err) {
      // Examples are a convenience; the ask form still works.
    }
  }, [lang, token]);

  useEffect(() => {
    loadExamples();
  }, [loadExamples]);

  useEffect(() => {
    (async () => {
      const cached = await getCache<AskResponse>(CACHE_KEY);
      if (cached && cached.result) setAnswer(cached);
    })();
  }, []);

  const ask = async (raw: string) => {
    const q = String(raw || '').trim();
    if (q.length < 3) {
      setError(t('admin_analytics.error'));
      return;
    }
    setQuestion(q);
    setAsking(true);
    setError('');
    try {
      const cachedToken = await token();
      const res = await fetchWithTimeout(`${API_BASE_URL}/api/ai/platform-analytics/ask`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${cachedToken}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ locale: lang, question: q }),
      });
      const data = await res.json();
      if (!res.ok) {
        setError(data?.error || t('admin_analytics.error'));
        return;
      }
      setAnswer(data);
      setCache(CACHE_KEY, data).catch(() => {});
    } catch (err) {
      setError(t('admin_analytics.error'));
    } finally {
      setAsking(false);
    }
  };

  const renderChart = (chart: ChartShape) => {
    const max = Math.max(...(chart.values || [0]), 1);
    return (
      <View style={styles.chart}>
        <Text style={styles.sectionTitle}>
          {t('admin_analytics.chart_title')}{chart.metric ? ` - ${chart.metric}` : ''}
        </Text>
        {(chart.labels || []).map((label, index) => {
          const value = Number(chart.values?.[index] || 0);
          const pct = Math.max(0, Math.min(100, (value / max) * 100));
          return (
            <View key={`c-${index}`} style={styles.chartRow}>
              <Text style={styles.chartLabel} numberOfLines={1}>{label}</Text>
              <View style={styles.chartTrack}>
                <View style={[styles.chartFill, { width: `${pct}%` }]} />
              </View>
              <Text style={styles.chartValue}>{value}</Text>
            </View>
          );
        })}
      </View>
    );
  };

  const renderAnswer = (data: AskResponse) => {
    const r = data.result;
    if (!r) {
      return (
        <View style={styles.noteBox}>
          <Text style={styles.noteText}>{data.notice || t('admin_analytics.error')}</Text>
        </View>
      );
    }

    return (
      <View style={styles.card}>
        <View style={styles.cardHead}>
          <View style={{ flex: 1 }}>
            <Text style={styles.valueLabel}>{r.value_label}</Text>
            <Text style={styles.value}>{r.value === null ? '-' : String(r.value)}</Text>
            <Text style={styles.muted}>{r.range_label}</Text>
          </View>
          <Text style={[styles.badge, r.live ? styles.badgeOk : styles.badgeMuted]}>
            {r.live ? t('admin_analytics.live_badge') : t('admin_analytics.period_badge')}
          </Text>
        </View>

        <Text style={styles.explain}>{r.explanation}</Text>
        {data.acknowledgement ? <Text style={styles.ack}>{data.acknowledgement}</Text> : null}

        {r.chart && r.chart.labels && r.chart.labels.length > 0 ? renderChart(r.chart) : null}

        {r.table && r.table.rows && r.table.rows.length > 0 ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>{t('admin_analytics.table_title')}</Text>
            {r.table.rows.map((row, index) => (
              <View key={`r-${index}`} style={styles.tableRow}>
                {(row || []).map((cell, cellIndex) => (
                  <Text key={`c-${index}-${cellIndex}`} style={styles.tableCell} numberOfLines={2}>
                    <Text style={styles.tableKey}>{r.table?.columns?.[cellIndex] || ''}: </Text>
                    {String(cell)}
                  </Text>
                ))}
              </View>
            ))}
          </View>
        ) : null}

        {r.sources && r.sources.length > 0 ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>{t('admin_analytics.sources_title')}</Text>
            {r.sources.map((s, index) => (
              <Text key={`s-${index}`} style={styles.bullet}>{'• '}{s}</Text>
            ))}
          </View>
        ) : null}

        {r.limitations && r.limitations.length > 0 ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>{t('admin_analytics.limitations_title')}</Text>
            {r.limitations.map((l, index) => (
              <Text key={`l-${index}`} style={styles.bullet}>{'• '}{l}</Text>
            ))}
          </View>
        ) : null}

        <Text style={styles.muted}>{data.scope_note} {data.notice_no_sql}</Text>
        {data.truncated ? (
          <Text style={styles.muted}>{t('admin_analytics.limitations_title')}: {data.rows_read}</Text>
        ) : null}
      </View>
    );
  };

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar barStyle="dark-content" />
      <View style={styles.header}>
        <View style={{ flex: 1 }}>
          <Text style={styles.title}>{t('admin_analytics.title')}</Text>
          <Text style={styles.subtitle}>{t('admin_analytics.subtitle')}</Text>
        </View>
      </View>

      <ScrollView contentContainerStyle={styles.list} keyboardShouldPersistTaps="handled">
        <Text style={[styles.badge, styles.badgeInfo]}>{t('admin_analytics.scope_badge')}</Text>
        <View style={styles.noteBox}>
          <Text style={styles.noteText}>{t('admin_analytics.no_sql_note')}</Text>
        </View>
        <View style={styles.noteBox}>
          <Text style={styles.noteText}>{t('admin_analytics.privacy_note')}</Text>
        </View>

        <View style={styles.askCard}>
          <TextInput
            style={styles.input}
            value={question}
            onChangeText={setQuestion}
            placeholder={t('admin_analytics.ask_placeholder')}
            placeholderTextColor="#94a3b8"
            returnKeyType="send"
            onSubmitEditing={() => ask(question)}
          />
          <View style={styles.askButtons}>
            <TouchableOpacity
              style={[styles.primaryBtn, asking && styles.btnDisabled]}
              onPress={() => ask(question)}
              disabled={asking}
            >
              {asking ? (
                <ActivityIndicator color="#fff" size="small" />
              ) : (
                <Ionicons name="paper-plane" size={18} color="#fff" />
              )}
              <Text style={styles.primaryBtnText}>{t('admin_analytics.ask_button')}</Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={styles.ghostBtn}
              onPress={() => { setQuestion(''); setAnswer(null); setError(''); }}
            >
              <Text style={styles.ghostBtnText}>{t('admin_analytics.clear')}</Text>
            </TouchableOpacity>
          </View>
        </View>

        {examples.length > 0 ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>{t('admin_analytics.examples_title')}</Text>
            <View style={styles.chips}>
              {examples.map((ex, index) => (
                <TouchableOpacity key={`e-${index}`} style={styles.chip} onPress={() => ask(ex)}>
                  <Text style={styles.chipText}>{ex}</Text>
                </TouchableOpacity>
              ))}
            </View>
          </View>
        ) : null}

        {error ? (
          <View style={styles.errorBox}>
            <Text style={styles.errorText}>{error}</Text>
            <TouchableOpacity style={styles.ghostBtn} onPress={() => ask(question)}>
              <Text style={styles.ghostBtnText}>{t('admin_analytics.retry')}</Text>
            </TouchableOpacity>
          </View>
        ) : null}

        {asking && !answer ? (
          <View style={styles.center}>
            <SmartCartLoader size={56} />
            <Text style={styles.muted}>{t('admin_analytics.loading')}</Text>
          </View>
        ) : null}

        {answer ? renderAnswer(answer) : !asking && !error ? (
          <View style={styles.center}>
            <Ionicons name="stats-chart" size={44} color="#cbd5e1" />
            <Text style={styles.muted}>{t('admin_analytics.empty_state')}</Text>
          </View>
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

const makeStyles = (tc: ThemeColors) => StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f8fafc' },
  center: { alignItems: 'center', justifyContent: 'center', padding: 24 },
  header: {
    flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 16, paddingVertical: 14,
    backgroundColor: tc.card, borderBottomWidth: 1, borderBottomColor: '#e2e8f0',
  },
  title: { fontSize: 18, fontWeight: '800', color: '#0f172a' },
  subtitle: { fontSize: 12.5, color: '#64748b', marginTop: 2 },
  list: { padding: 16, paddingBottom: 40, gap: 10 },
  askCard: { backgroundColor: tc.card, borderWidth: 1, borderColor: '#e2e8f0', borderRadius: 14, padding: 14, gap: 10 },
  input: {
    borderWidth: 1, borderColor: '#e2e8f0', borderRadius: 11, paddingHorizontal: 14, paddingVertical: 12,
    fontSize: 15, color: '#0f172a', backgroundColor: tc.card,
  },
  askButtons: { flexDirection: 'row', gap: 10 },
  primaryBtn: {
    flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8,
    backgroundColor: '#4f46e5', borderRadius: 12, paddingVertical: 13, minHeight: 46,
  },
  primaryBtnText: { color: '#fff', fontWeight: '700', fontSize: 15 },
  btnDisabled: { opacity: 0.7 },
  ghostBtn: {
    alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: '#e2e8f0',
    borderRadius: 12, paddingVertical: 13, paddingHorizontal: 16, backgroundColor: tc.card, minHeight: 46,
  },
  ghostBtnText: { color: '#334155', fontWeight: '600', fontSize: 14 },
  card: { backgroundColor: tc.card, borderWidth: 1, borderColor: '#e2e8f0', borderRadius: 14, padding: 14, gap: 8 },
  cardHead: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
  valueLabel: { fontSize: 12, color: '#64748b', textTransform: 'uppercase', letterSpacing: 0.4 },
  value: { fontSize: 30, fontWeight: '800', color: '#0f172a' },
  explain: { fontSize: 14.5, color: '#334155', lineHeight: 21 },
  ack: { fontSize: 13, color: '#1e40af', backgroundColor: '#eff6ff', borderRadius: 10, padding: 10 },
  chart: { marginTop: 6, gap: 6 },
  chartRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  chartLabel: { width: 92, fontSize: 12, color: '#475569' },
  chartTrack: { flex: 1, height: 10, borderRadius: 999, backgroundColor: '#eef2f7', overflow: 'hidden' },
  chartFill: { height: 10, backgroundColor: '#4f46e5', borderRadius: 999 },
  chartValue: { width: 56, textAlign: 'right', fontSize: 12.5, fontWeight: '600', color: '#334155' },
  tableRow: { borderBottomWidth: 1, borderBottomColor: '#f1f5f9', paddingVertical: 6, gap: 2 },
  tableCell: { fontSize: 13, color: '#0f172a' },
  tableKey: { color: '#64748b' },
  section: { marginTop: 10, gap: 4 },
  sectionTitle: { fontSize: 12.5, fontWeight: '700', color: '#334155', textTransform: 'uppercase', letterSpacing: 0.4 },
  bullet: { fontSize: 13, color: '#475569' },
  badge: {
    alignSelf: 'flex-start', overflow: 'hidden', borderRadius: 999, paddingHorizontal: 10, paddingVertical: 4,
    fontSize: 11.5, fontWeight: '700',
  },
  badgeInfo: { backgroundColor: '#eff6ff', color: '#1e40af' },
  badgeOk: { backgroundColor: '#ecfdf5', color: '#047857' },
  badgeMuted: { backgroundColor: '#f1f5f9', color: '#64748b' },
  noteBox: { backgroundColor: '#eff6ff', borderRadius: 10, padding: 10 },
  noteText: { fontSize: 12.5, color: '#1e40af' },
  errorBox: { backgroundColor: '#fef2f2', borderRadius: 10, padding: 12, gap: 8 },
  errorText: { fontSize: 13, color: '#991b1b' },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginTop: 4 },
  chip: { backgroundColor: '#eef2ff', borderWidth: 1, borderColor: '#e0e7ff', borderRadius: 999, paddingHorizontal: 12, paddingVertical: 7 },
  chipText: { fontSize: 12.5, color: '#3730a3' },
  muted: { fontSize: 12.5, color: '#64748b', marginTop: 4 },
});
