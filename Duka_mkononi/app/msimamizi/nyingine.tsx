import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useFocusEffect } from 'expo-router';
import React, { useCallback, useState } from 'react';
import {
  Alert,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import SmartCartLoader from '../../components/SmartCartLoader';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLang } from '../../context/LanguageContext';
import { fetchWithTimeout } from '../../lib/network';

import { API_BASE_URL } from '../../constants/api';

/**
 * Capability #6 - Anomaly & Leak Detection (manager mobile screen, "Nyingine").
 *
 * GET /api/ai/anomalies runs the deterministic rule engine over this
 * business's own rows and returns localized review signals plus an optional
 * AI-written summary. This screen only renders them: figures, titles and
 * verify steps are computed server-side, and a dismissal is recorded as an
 * audited decision (POST /api/ai/anomalies/dismiss).
 */

interface AnomalyEvidence {
  ref?: string;
  date?: string;
  value?: number | string;
}

interface AnomalyAlert {
  key: string;
  rule: string;
  severity: 'high' | 'medium' | 'low';
  subject: string;
  title: string;
  detail: string;
  verify_steps: string;
  evidence: AnomalyEvidence[];
}

interface HistoryRow {
  key: string;
  subject?: string;
  rule?: string;
  reason?: string | null;
  at?: string;
}

interface ScanResponse {
  success: boolean;
  locale: string;
  detected_count: number;
  open_count: number;
  dismissed_count: number;
  alerts: AnomalyAlert[];
  history: HistoryRow[];
  summary: string;
  ai: { available: boolean; degraded: boolean; code?: string | null; reason?: string | null };
  limitations: string[];
  generated_at: string;
}

const SEVERITY_COLORS: Record<string, string> = {
  high: '#e74c3c',
  medium: '#f39c12',
  low: '#3498db',
};

export default function NyingineScreen() {
  const { t, lang } = useLang();
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadError, setLoadError] = useState(false);
  const [data, setData] = useState<ScanResponse | null>(null);
  const [dismissing, setDismissing] = useState<string | null>(null);

  const load = useCallback(
    async (showSpinner: boolean) => {
      if (showSpinner) setLoading(true);
      setLoadError(false);
      try {
        const token = await AsyncStorage.getItem('userToken');
        if (!token) {
          setLoadError(true);
          setLoading(false);
          return;
        }
        const res = await fetchWithTimeout(
          `${API_BASE_URL}/api/ai/anomalies?locale=${encodeURIComponent(lang)}`,
          { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } },
          45000
        );
        if (res.status === 401) {
          Alert.alert(t('msimamizi_nyingine.error_title'), t('msimamizi_nyingine.sign_in_again'));
          setLoadError(true);
        } else if (res.ok) {
          const json = (await res.json()) as ScanResponse;
          setData(json);
        } else {
          setLoadError(true);
        }
      } catch (e) {
        setLoadError(true);
      } finally {
        setLoading(false);
        setRefreshing(false);
      }
    },
    [lang, t]
  );

  useFocusEffect(
    useCallback(() => {
      load(true);
    }, [load])
  );

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    load(false);
  }, [load]);

  const dismissAlert = useCallback(
    (alert: AnomalyAlert) => {
      Alert.alert(t('msimamizi_nyingine.dismiss_btn'), t('msimamizi_nyingine.dismiss_confirm'), [
        { text: t('app.cancel'), style: 'cancel' },
        {
          text: t('app.confirm'),
          onPress: async () => {
            setDismissing(alert.key);
            try {
              const token = await AsyncStorage.getItem('userToken');
              if (!token) return;
              const res = await fetchWithTimeout(
                `${API_BASE_URL}/api/ai/anomalies/dismiss`,
                {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${token}`,
                  },
                  body: JSON.stringify({ key: alert.key, locale: lang }),
                },
                30000
              );
              if (res.ok) await load(false);
            } catch (e) {
              // Transient network failure: the card stays, the manager retries.
            } finally {
              setDismissing(null);
            }
          },
        },
      ]);
    },
    [lang, load, t]
  );

  const renderSeverity = (severity: string) => {
    const label =
      severity === 'high'
        ? t('msimamizi_nyingine.severity_high')
        : severity === 'medium'
          ? t('msimamizi_nyingine.severity_medium')
          : t('msimamizi_nyingine.severity_low');
    return (
      <View style={[styles.severity, { backgroundColor: SEVERITY_COLORS[severity] || '#3498db' }]}>
        <Text style={styles.severityText}>{label}</Text>
      </View>
    );
  };

  const renderBody = () => {
    if (loading) {
      return (
        <View style={styles.stateBox}>
          <SmartCartLoader size="large" color="#e74c3c" label={t('msimamizi_nyingine.loading')} />
          <Text style={styles.stateText}>{t('msimamizi_nyingine.loading')}</Text>
        </View>
      );
    }
    if (loadError || !data) {
      return (
        <View style={styles.stateBox}>
          <Ionicons name="alert-circle-outline" size={40} color="#e74c3c" />
          <Text style={styles.stateTitle}>{t('msimamizi_nyingine.error_title')}</Text>
          <Text style={styles.stateText}>{t('msimamizi_nyingine.error_text')}</Text>
          <TouchableOpacity style={styles.retryBtn} onPress={() => load(true)}>
            <Text style={styles.retryBtnText}>{t('msimamizi_nyingine.retry')}</Text>
          </TouchableOpacity>
        </View>
      );
    }

    const alerts = data.alerts || [];
    return (
      <View>
        <View style={styles.statsRow}>
          <View style={styles.statChip}>
            <Text style={styles.statLabel}>{t('msimamizi_nyingine.stat_detected')}</Text>
            <Text style={styles.statValue}>{data.detected_count}</Text>
          </View>
          <View style={styles.statChip}>
            <Text style={styles.statLabel}>{t('msimamizi_nyingine.stat_open')}</Text>
            <Text style={styles.statValue}>{data.open_count}</Text>
          </View>
          <View style={styles.statChip}>
            <Text style={styles.statLabel}>{t('msimamizi_nyingine.stat_dismissed')}</Text>
            <Text style={styles.statValue}>{data.dismissed_count}</Text>
          </View>
        </View>

        {data.summary ? (
          <View style={styles.summaryCard}>
            <Text style={styles.summaryLabel}>{t('msimamizi_nyingine.section_summary')}</Text>
            <Text style={styles.summaryText}>{data.summary}</Text>
            {data.ai?.degraded && data.ai?.reason ? (
              <Text style={styles.summaryNote}>{data.ai.reason}</Text>
            ) : null}
          </View>
        ) : null}

        {alerts.length === 0 ? (
          <View style={styles.stateBox}>
            <Ionicons name="shield-checkmark-outline" size={40} color="#27ae60" />
            <Text style={styles.stateTitle}>{t('msimamizi_nyingine.empty_title')}</Text>
            <Text style={styles.stateText}>{t('msimamizi_nyingine.empty_text')}</Text>
          </View>
        ) : (
          alerts.map((alert) => (
            <View key={alert.key} style={styles.alertCard}>
              <View style={styles.alertTop}>
                <Text style={styles.alertTitle}>{alert.title}</Text>
                {renderSeverity(alert.severity)}
              </View>
              <Text style={styles.alertDetail}>{alert.detail}</Text>
              {(alert.evidence || []).length > 0 ? (
                <View style={styles.evidenceBox}>
                  <Text style={styles.evidenceLabel}>{t('msimamizi_nyingine.evidence')}</Text>
                  {alert.evidence.map((ev, index) => (
                    <Text key={index} style={styles.evidenceRow}>
                      {[ev.ref, ev.date, ev.value != null ? String(ev.value) : '']
                        .filter(Boolean)
                        .join(' · ')}
                    </Text>
                  ))}
                </View>
              ) : null}
              {alert.verify_steps ? (
                <Text style={styles.verifyText}>
                  {t('msimamizi_nyingine.verify')}: {alert.verify_steps}
                </Text>
              ) : null}
              <TouchableOpacity
                style={styles.dismissBtn}
                disabled={dismissing === alert.key}
                onPress={() => dismissAlert(alert)}
              >
                {dismissing === alert.key ? (
                  <SmartCartLoader size="compact" color="#27ae60" label={t('msimamizi_nyingine.dismiss_btn')} />
                ) : (
                  <>
                    <Ionicons name="checkmark-circle-outline" size={16} color="#27ae60" />
                    <Text style={styles.dismissBtnText}>{t('msimamizi_nyingine.dismiss_btn')}</Text>
                  </>
                )}
              </TouchableOpacity>
            </View>
          ))
        )}

        {(data.history || []).length > 0 ? (
          <View style={styles.historyCard}>
            <Text style={styles.historyTitle}>{t('msimamizi_nyingine.history_title')}</Text>
            {data.history.map((row, index) => (
              <View key={index} style={styles.historyRow}>
                <Text style={styles.historySubject}>{row.subject || row.key}</Text>
                <Text style={styles.historyMeta}>
                  {row.reason || t('msimamizi_nyingine.no_reason')} ·{' '}
                  {String(row.at || '').slice(0, 16).replace('T', ' ')}
                </Text>
              </View>
            ))}
          </View>
        ) : null}

        {(data.limitations || []).length > 0 ? (
          <View style={styles.limitationsBox}>
            <Text style={styles.limitationsTitle}>{t('msimamizi_nyingine.limitations_title')}</Text>
            {data.limitations.map((line, index) => (
              <Text key={index} style={styles.limitationsText}>
                {line}
              </Text>
            ))}
            <Text style={styles.generated}>
              {t('msimamizi_nyingine.generated', {
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
      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
      >
        <View style={styles.headerRow}>
          <View style={styles.headerText}>
            <View style={styles.titleRow}>
              <Text style={styles.title}>{t('msimamizi_nyingine.title')}</Text>
              <View style={styles.aiChip}>
                <Ionicons name="sparkles" size={11} color="#7c5cbf" />
                <Text style={styles.aiChipText}>{t('msimamizi_nyingine.ai_badge')}</Text>
              </View>
            </View>
            <Text style={styles.subtitle}>{t('msimamizi_nyingine.subtitle')}</Text>
          </View>
          <TouchableOpacity style={styles.refreshBtn} onPress={() => load(true)} disabled={loading}>
            <Ionicons name="refresh" size={18} color="#fff" />
            <Text style={styles.refreshBtnText}>{t('msimamizi_nyingine.refresh')}</Text>
          </TouchableOpacity>
        </View>

        {renderBody()}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: '#f8f9fa' },
  content: { padding: 16, paddingBottom: 40 },
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12, marginBottom: 16 },
  headerText: { flex: 1 },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
  title: { fontSize: 22, fontWeight: '800', color: '#2c3e50' },
  subtitle: { fontSize: 13, color: '#7f8c8d', marginTop: 3 },
  aiChip: {
    flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: '#f3eeff',
    borderColor: '#e3d9ff', borderWidth: 1, borderRadius: 999, paddingHorizontal: 9, paddingVertical: 3,
  },
  aiChipText: { fontSize: 10.5, fontWeight: '800', color: '#7c5cbf', letterSpacing: 0.4 },
  refreshBtn: {
    flexDirection: 'row', alignItems: 'center', gap: 6, backgroundColor: '#e74c3c',
    borderRadius: 12, paddingHorizontal: 14, paddingVertical: 10,
  },
  refreshBtnText: { color: '#fff', fontWeight: '700', fontSize: 13 },
  statsRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 14 },
  statChip: {
    backgroundColor: '#fff', borderColor: '#ecf0f1', borderWidth: 1, borderRadius: 12,
    paddingHorizontal: 14, paddingVertical: 8, alignItems: 'center',
  },
  statLabel: { fontSize: 11, color: '#7f8c8d', fontWeight: '600' },
  statValue: { fontSize: 16, fontWeight: '800', color: '#2c3e50' },
  summaryCard: { backgroundColor: '#1e2a4a', borderRadius: 16, padding: 16, marginBottom: 16 },
  summaryLabel: { fontSize: 10.5, fontWeight: '800', color: '#ffffffb3', letterSpacing: 0.8, textTransform: 'uppercase', marginBottom: 5 },
  summaryText: { fontSize: 14, color: '#fff', lineHeight: 21 },
  summaryNote: { fontSize: 11.5, color: '#ffffffb8', marginTop: 7 },
  stateBox: { alignItems: 'center', paddingVertical: 44, paddingHorizontal: 18 },
  stateTitle: { fontSize: 16, fontWeight: '700', color: '#2c3e50', marginTop: 10 },
  stateText: { fontSize: 13.5, color: '#7f8c8d', textAlign: 'center', marginTop: 5, lineHeight: 19 },
  retryBtn: {
    marginTop: 14, backgroundColor: '#fff', borderColor: '#dfe4ea', borderWidth: 1,
    borderRadius: 12, paddingHorizontal: 20, paddingVertical: 10,
  },
  retryBtnText: { fontWeight: '700', color: '#2c3e50', fontSize: 14 },
  alertCard: {
    backgroundColor: '#fff', borderColor: '#ecf0f1', borderWidth: 1, borderRadius: 16,
    padding: 15, marginBottom: 12,
  },
  alertTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 },
  alertTitle: { flex: 1, fontSize: 15, fontWeight: '800', color: '#2c3e50' },
  severity: { borderRadius: 999, paddingHorizontal: 9, paddingVertical: 3 },
  severityText: { color: '#fff', fontSize: 10.5, fontWeight: '800' },
  alertDetail: { fontSize: 13.5, color: '#44546a', lineHeight: 20, marginTop: 7 },
  evidenceBox: { marginTop: 9 },
  evidenceLabel: { fontSize: 10, fontWeight: '800', color: '#95a5a6', letterSpacing: 0.5, textTransform: 'uppercase', marginBottom: 4 },
  evidenceRow: { fontSize: 12, color: '#5d6d7e', backgroundColor: '#f8f9fa', borderRadius: 8, paddingHorizontal: 9, paddingVertical: 5, marginBottom: 4 },
  verifyText: { fontSize: 12.5, color: '#7f8c8d', lineHeight: 18, marginTop: 9, borderLeftWidth: 3, borderLeftColor: '#f39c12', paddingLeft: 9 },
  dismissBtn: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6,
    marginTop: 12, backgroundColor: '#eafaf1', borderColor: '#c8f0dc', borderWidth: 1,
    borderRadius: 12, paddingVertical: 10,
  },
  dismissBtnText: { color: '#27ae60', fontWeight: '700', fontSize: 13.5 },
  historyCard: {
    backgroundColor: '#fff', borderColor: '#ecf0f1', borderWidth: 1, borderRadius: 16,
    padding: 15, marginTop: 12,
  },
  historyTitle: { fontSize: 14.5, fontWeight: '800', color: '#2c3e50', marginBottom: 6 },
  historyRow: { borderTopWidth: 1, borderTopColor: '#f4f6f9', paddingVertical: 7 },
  historySubject: { fontSize: 13, fontWeight: '700', color: '#2c3e50' },
  historyMeta: { fontSize: 12, color: '#7f8c8d', marginTop: 2 },
  limitationsBox: { marginTop: 16 },
  limitationsTitle: { fontSize: 12, fontWeight: '800', color: '#7f8c8d' },
  limitationsText: { fontSize: 12, color: '#95a5a6', lineHeight: 17, marginTop: 3 },
  generated: { fontSize: 11.5, color: '#aeb6bf', marginTop: 6 },
});
