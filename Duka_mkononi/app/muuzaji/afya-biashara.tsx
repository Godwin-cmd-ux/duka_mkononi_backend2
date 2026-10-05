import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useFocusEffect } from 'expo-router';
import React, { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLang } from '../../context/LanguageContext';
import { fetchWithTimeout } from '../../lib/network';

import { API_BASE_URL } from '../../constants/api';

/**
 * Capability #5 - Business Health Score and Weekly Coaching (seller mobile screen).
 *
 * GET /api/ai/reports/weekly returns the latest stored weekly digest (generated
 * if none exists); POST .../weekly regenerates it. The score and every figure
 * are computed server-side from this business's own rows - the screen only
 * renders them.
 */

interface HealthComponent {
  key: string;
  label: string;
  value: number | null;
  score: number;
  status: string;
  status_label: string;
  evidence: string;
  approximate: boolean;
}

interface HealthChange {
  key: string;
  direction: string;
  change_pct: number;
  text: string;
}

interface HealthAction {
  key: string;
  title: string;
  body: string;
}

interface HealthDigest {
  success: boolean;
  locale: string;
  currency: string;
  stored: boolean;
  reused: boolean;
  storage_error?: string | null;
  period: { start: string; end: string; days: number; previous?: { start: string; end: string } | null };
  score: number | null;
  band: string;
  band_label: string;
  band_interpretation: string;
  sufficient: boolean;
  coverage: number;
  headline: string;
  summary_text: string;
  components: HealthComponent[];
  missing_components: string[];
  changes: HealthChange[];
  actions: HealthAction[];
  limitations: string[];
  ai: { available: boolean; degraded: boolean; code: string | null; interpretation: string };
}

interface HistoryRow {
  id: string;
  period_start: string;
  period_end: string;
  score: number | null;
  band: string;
  band_label: string | null;
  sufficient: boolean;
}

const BAND_COLORS: Record<string, string> = {
  strong: '#2ecc71',
  steady: '#3498db',
  watch: '#f39c12',
  attention: '#e74c3c',
  insufficient_evidence: '#95a5a6',
};

export default function AfyaBiasharaScreen() {
  const { t, lang } = useLang();

  const [digest, setDigest] = useState<HealthDigest | null>(null);
  const [history, setHistory] = useState<HistoryRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [generating, setGenerating] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(
    async (refresh = false) => {
      if (refresh) {
        setGenerating(true);
      } else {
        setLoading(true);
      }
      setError(null);

      try {
        const token = await AsyncStorage.getItem('userToken');
        if (!token) {
          setError(t('health_digest.error_auth'));
          return;
        }

        const headers = {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`,
          'ngrok-skip-browser-warning': 'true',
        };

        const weeklyRequest = refresh
          ? fetchWithTimeout(
              `${API_BASE_URL}/api/ai/reports/weekly`,
              { method: 'POST', headers, body: JSON.stringify({ locale: lang, refresh: true }) },
              45000
            )
          : fetchWithTimeout(`${API_BASE_URL}/api/ai/reports/weekly`, { headers }, 45000);

        const [weeklyResponse, historyResponse] = await Promise.all([
          weeklyRequest,
          fetchWithTimeout(`${API_BASE_URL}/api/ai/reports/weekly/history?limit=12`, { headers }, 30000),
        ]);

        const weeklyPayload = await weeklyResponse.json().catch(() => null);
        if (!weeklyResponse.ok || !weeklyPayload) {
          setError(t('health_digest.error_load'));
          return;
        }
        setDigest(weeklyPayload as HealthDigest);

        if (historyResponse.ok) {
          const historyPayload = await historyResponse.json().catch(() => null);
          setHistory((historyPayload?.digests as HistoryRow[]) || []);
        }
      } catch (err) {
        console.error('Error loading health digest:', err);
        setError(t('health_digest.error_network'));
      } finally {
        setLoading(false);
        setGenerating(false);
      }
    },
    [lang, t]
  );

  useFocusEffect(
    useCallback(() => {
      load(false);
    }, [load])
  );

  const renderScore = () => {
    if (!digest) return null;
    const color = BAND_COLORS[digest.band] || '#95a5a6';
    const scoreText = digest.sufficient && digest.score !== null ? String(Math.round(digest.score)) : '–';

    return (
      <View style={styles.scoreCard}>
        <View style={[styles.scoreRing, { backgroundColor: color }]}>
          <Text style={styles.scoreValue}>{scoreText}</Text>
          <Text style={styles.scoreMax}>/ 100</Text>
        </View>
        <View style={styles.scoreMeta}>
          <Text style={styles.bandLabel}>{digest.band_label}</Text>
          <Text style={styles.scoreSummary}>{digest.summary_text}</Text>
          {digest.period && (
            <Text style={styles.periodText}>
              {t('health_digest.period_label')}: {digest.period.start} – {digest.period.end}
            </Text>
          )}
        </View>
      </View>
    );
  };

  const renderComponents = () => {
    if (!digest?.components?.length) return null;
    return (
      <View style={styles.card}>
        <Text style={styles.cardTitle}>{t('health_digest.components_title')}</Text>
        {digest.components.map((component) => {
          const color = BAND_COLORS[component.status] || '#95a5a6';
          const widthPct = Math.max(4, Math.round(component.score));
          return (
            <View key={component.key} style={styles.component}>
              <View style={styles.componentHead}>
                <Text style={styles.componentName}>{component.label}</Text>
                <Text style={[styles.componentStatus, { backgroundColor: `${color}22`, color }]}>
                  {component.status_label}
                </Text>
              </View>
              <View style={styles.componentTrack}>
                <View style={[styles.componentFill, { width: `${widthPct}%`, backgroundColor: color }]} />
              </View>
              <Text style={styles.componentEvidence}>{component.evidence}</Text>
            </View>
          );
        })}
      </View>
    );
  };

  const renderChanges = () => {
    if (!digest?.changes?.length) return null;
    const icons: Record<string, string> = { up: '▲', down: '▼', flat: '■' };
    const colors: Record<string, string> = { up: '#2ecc71', down: '#e74c3c', flat: '#95a5a6' };
    return (
      <View style={styles.card}>
        <Text style={styles.cardTitle}>{t('health_digest.changes_title')}</Text>
        {digest.changes.map((change) => (
          <View key={change.key} style={styles.changeRow}>
            <Text style={[styles.changeIcon, { color: colors[change.direction] || '#95a5a6' }]}>
              {icons[change.direction] || '■'}
            </Text>
            <Text style={styles.changeText}>{change.text}</Text>
          </View>
        ))}
      </View>
    );
  };

  const renderActions = () => {
    if (!digest?.actions?.length) return null;
    return (
      <View style={styles.card}>
        <Text style={styles.cardTitle}>{t('health_digest.actions_title')}</Text>
        {digest.actions.map((action, index) => (
          <View key={action.key} style={styles.action}>
            <View style={styles.actionNum}>
              <Text style={styles.actionNumText}>{index + 1}</Text>
            </View>
            <View style={styles.actionBody}>
              <Text style={styles.actionTitle}>{action.title}</Text>
              <Text style={styles.actionText}>{action.body}</Text>
            </View>
          </View>
        ))}
      </View>
    );
  };

  const renderHistory = () => (
    <View style={styles.card}>
      <Text style={styles.cardTitle}>{t('health_digest.history_title')}</Text>
      {history.length === 0 ? (
        <Text style={styles.muted}>{t('health_digest.no_history')}</Text>
      ) : (
        history.map((row) => (
          <View key={row.id || row.period_start} style={styles.historyRow}>
            <Text style={styles.historyPeriod}>
              {row.period_start} – {row.period_end}
            </Text>
            <Text style={styles.historyBadge}>{row.band_label || ''}</Text>
            <Text style={styles.historyScore}>
              {row.sufficient && row.score !== null ? Math.round(row.score) : '–'}/100
            </Text>
          </View>
        ))
      )}
    </View>
  );

  const renderResult = () => {
    if (!digest) return null;
    return (
      <>
        {digest.stored === false && (
          <View style={styles.notice}>
            <Text style={styles.noticeText}>{t('health_digest.store_warning')}</Text>
          </View>
        )}
        {renderScore()}
        {!digest.sufficient && (
          <View style={styles.card}>
            <Text style={styles.cardTitle}>{t('health_digest.insufficient_title')}</Text>
            <Text style={styles.componentEvidence}>{digest.band_interpretation}</Text>
          </View>
        )}
        {digest.ai?.interpretation ? (
          <View style={styles.aiCard}>
            <Text style={styles.aiCardTitle}>
              <Ionicons name="sparkles" size={13} /> {t('health_digest.ai_title')}
            </Text>
            <Text style={styles.aiCardText}>{digest.ai.interpretation}</Text>
          </View>
        ) : null}
        {renderComponents()}
        {renderChanges()}
        {renderActions()}
        {renderHistory()}
        {digest.limitations.length > 0 && (
          <View style={styles.card}>
            <Text style={styles.cardTitle}>{t('health_digest.limitations_title')}</Text>
            {digest.limitations.map((item, index) => (
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
        <View style={styles.headerText}>
          <Text style={styles.title}>{t('health_digest.title')}</Text>
          <Text style={styles.subtitle}>{t('health_digest.subtitle')}</Text>
        </View>
        <TouchableOpacity
          style={styles.refreshBtn}
          onPress={() => load(true)}
          disabled={loading || generating}>
          {generating ? (
            <ActivityIndicator size="small" color="#ffffff" />
          ) : (
            <Text style={styles.refreshBtnText}>{t('health_digest.btn_refresh')}</Text>
          )}
        </TouchableOpacity>
      </View>

      <ScrollView
        contentContainerStyle={styles.scroll}
        refreshControl={
          <RefreshControl refreshing={loading} onRefresh={() => load(false)} tintColor="#2ecc71" />
        }>
        {loading && (
          <View style={styles.center}>
            <ActivityIndicator size="large" color="#2ecc71" />
            <Text style={styles.centerText}>{t('health_digest.loading')}</Text>
          </View>
        )}

        {!loading && error && (
          <View style={styles.center}>
            <Ionicons name="alert-circle" size={44} color="#e74c3c" />
            <Text style={styles.centerText}>{error}</Text>
          </View>
        )}

        {!loading && !error && !digest && (
          <View style={styles.center}>
            <Ionicons name="heart" size={48} color="#bdc3c7" />
            <Text style={styles.emptyTitle}>{t('health_digest.empty_title')}</Text>
            <Text style={styles.centerText}>{t('health_digest.empty_hint')}</Text>
          </View>
        )}

        {!loading && !error && digest && renderResult()}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f8f9fa' },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    paddingHorizontal: 20,
    paddingVertical: 14,
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: '#ecf0f1',
  },
  headerText: { flex: 1 },
  title: { fontSize: 18, fontWeight: '700', color: '#2c3e50' },
  subtitle: { fontSize: 12, color: '#95a5a6', marginTop: 2 },
  refreshBtn: {
    backgroundColor: '#2ecc71',
    borderRadius: 12,
    paddingHorizontal: 16,
    paddingVertical: 10,
    minWidth: 84,
    alignItems: 'center',
    justifyContent: 'center',
  },
  refreshBtnText: { color: '#ffffff', fontWeight: '700', fontSize: 13 },
  scroll: { padding: 16, paddingBottom: 40 },
  center: { alignItems: 'center', paddingVertical: 40, paddingHorizontal: 20 },
  centerText: { marginTop: 10, color: '#7f8c8d', fontSize: 14, textAlign: 'center' },
  emptyTitle: { marginTop: 12, fontSize: 18, fontWeight: '700', color: '#2c3e50' },
  scoreCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 18,
    backgroundColor: '#ffffff',
    borderRadius: 18,
    padding: 20,
    marginBottom: 14,
  },
  scoreRing: {
    width: 96,
    height: 96,
    borderRadius: 48,
    alignItems: 'center',
    justifyContent: 'center',
  },
  scoreValue: { fontSize: 30, fontWeight: '800', color: '#ffffff', lineHeight: 34 },
  scoreMax: { fontSize: 11, color: '#ffffff', opacity: 0.9 },
  scoreMeta: { flex: 1 },
  bandLabel: { fontSize: 18, fontWeight: '800', color: '#2c3e50', marginBottom: 4 },
  scoreSummary: { fontSize: 13, color: '#5d6d7e', lineHeight: 19 },
  periodText: { fontSize: 11, color: '#95a5a6', marginTop: 8 },
  card: { backgroundColor: '#ffffff', borderRadius: 16, padding: 16, marginBottom: 14 },
  cardTitle: { fontSize: 14, fontWeight: '700', color: '#2c3e50', marginBottom: 10 },
  component: { paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: '#f4f6f7' },
  componentHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 },
  componentName: { fontSize: 14, fontWeight: '700', color: '#2c3e50', flex: 1 },
  componentStatus: { fontSize: 11, fontWeight: '700', paddingHorizontal: 10, paddingVertical: 3, borderRadius: 20, overflow: 'hidden' },
  componentTrack: { height: 8, backgroundColor: '#ecf0f1', borderRadius: 4, overflow: 'hidden', marginBottom: 8 },
  componentFill: { height: 8, borderRadius: 4 },
  componentEvidence: { fontSize: 12.5, color: '#7f8c8d', lineHeight: 18 },
  changeRow: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 8 },
  changeIcon: { width: 24, textAlign: 'center', fontSize: 14 },
  changeText: { flex: 1, fontSize: 13.5, color: '#5d6d7e' },
  action: { flexDirection: 'row', gap: 12, paddingVertical: 10 },
  actionNum: {
    width: 26,
    height: 26,
    borderRadius: 13,
    backgroundColor: '#e8f8f0',
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionNumText: { color: '#1e8449', fontWeight: '800', fontSize: 12 },
  actionBody: { flex: 1 },
  actionTitle: { fontSize: 14, fontWeight: '700', color: '#2c3e50', marginBottom: 3 },
  actionText: { fontSize: 13, color: '#5d6d7e', lineHeight: 19 },
  historyRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#f4f6f7',
  },
  historyPeriod: { flex: 1, fontSize: 12.5, color: '#5d6d7e' },
  historyBadge: {
    fontSize: 11,
    fontWeight: '700',
    paddingHorizontal: 10,
    paddingVertical: 3,
    borderRadius: 20,
    backgroundColor: '#f4f6f7',
    color: '#5d6d7e',
    overflow: 'hidden',
  },
  historyScore: { fontSize: 13, fontWeight: '700', color: '#2c3e50' },
  aiCard: { backgroundColor: '#e8f8f0', borderRadius: 14, padding: 14, marginBottom: 14 },
  aiCardTitle: { fontSize: 12, fontWeight: '700', color: '#1e8449', marginBottom: 4 },
  aiCardText: { fontSize: 13, color: '#1e8449', lineHeight: 19 },
  notice: { backgroundColor: '#fef5e7', borderRadius: 14, padding: 12, marginBottom: 14 },
  noticeText: { fontSize: 12.5, color: '#b9770e' },
  muted: { fontSize: 13, color: '#95a5a6' },
  listRow: { flexDirection: 'row', gap: 8, marginBottom: 6 },
  listBullet: { fontSize: 14, fontWeight: '700', color: '#95a5a6' },
  listText: { flex: 1, fontSize: 12, color: '#5d6d7e', lineHeight: 17 },
});
