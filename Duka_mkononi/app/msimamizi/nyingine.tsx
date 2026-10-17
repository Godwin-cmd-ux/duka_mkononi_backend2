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
import ThemeSelector from '../../components/theme-selector';
import SmartCartLoader from '../../components/SmartCartLoader';
import OnboardingAssistant from '../../components/OnboardingAssistant';
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
 *
 * Capability #17 - Team Activity Digest.
 *
 * GET /api/ai/activity/digest summarises what this business's OWN members
 * recorded in the existing activity log (`user_logs`) over a daily or weekly
 * window. Every count, member, day series and the narrative are computed
 * server-side from real rows; the model may only REWORD the same figures. The
 * digest never ranks or judges staff - it reports recorded events only - and it
 * names the members with no recorded events so missing coverage stays visible.
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

interface ActivityCategory {
  key: string;
  label: string;
  count: number;
  failed: number;
}

interface ActivityMemberRow {
  id: string;
  name: string;
  role?: string;
  count: number;
  last_at?: string | null;
}

interface ActivityEvent {
  id?: string;
  action: string;
  category: string;
  category_label: string;
  status: string;
  actor_id?: string;
  actor_name: string;
  at: string;
  endpoint?: string;
  refs?: { field: string; value: string }[];
}

interface ActivityResponse {
  success: boolean;
  period: string;
  period_label: string;
  windows: {
    current: { from: string; to: string };
    previous: { from: string; to: string } | null;
    days: number;
    partial: boolean;
  };
  totals: { events: number; success: number; failed: number; actors: number; members: number };
  comparison: { direction: string; previous_events: number; change: number };
  categories: ActivityCategory[];
  members: ActivityMemberRow[];
  silent_members: string[];
  day_series: { date: string; count: number }[];
  noteworthy: ActivityEvent[];
  narrative: string[];
  digest_text: string;
  sources: string[];
  limitations: string[];
  truncated: boolean;
  generated_at: string;
  ai?: { available: boolean; degraded: boolean; code?: string | null; reason?: string | null };
}

const SEVERITY_COLORS: Record<string, string> = {
  high: '#e74c3c',
  medium: '#f39c12',
  low: '#3498db',
};

/* Capability #17: the reporting windows the digest endpoint accepts. */
const ACT_PERIODS: string[] = ['today', 'yesterday', 'this_week', 'last_week', 'last_7_days'];

const shortStamp = (value?: string | null) => String(value || '').slice(0, 16).replace('T', ' ');

export default function NyingineScreen() {
  const { t, lang } = useLang();
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadError, setLoadError] = useState(false);
  const [data, setData] = useState<ScanResponse | null>(null);
  const [dismissing, setDismissing] = useState<string | null>(null);
  const [actPeriod, setActPeriod] = useState('this_week');
  const [actData, setActData] = useState<ActivityResponse | null>(null);
  const [actError, setActError] = useState(false);
  const [actLoading, setActLoading] = useState(true);
  // Capability #19 - getting-started checklist + approved onboarding answers.
  const [guideOpen, setGuideOpen] = useState(false);

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

  /* Capability #17: the activity digest is its own request with its own state,
     so a failure here never blanks the anomaly scan above (and vice versa). */
  const loadActivity = useCallback(
    async (showSpinner: boolean, period: string) => {
      if (showSpinner) setActLoading(true);
      setActError(false);
      try {
        const token = await AsyncStorage.getItem('userToken');
        if (!token) {
          setActError(true);
          setActLoading(false);
          return;
        }
        const res = await fetchWithTimeout(
          `${API_BASE_URL}/api/ai/activity/digest?period=${encodeURIComponent(period)}&locale=${encodeURIComponent(lang)}`,
          { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } },
          45000
        );
        if (res.status === 401) {
          setActError(true);
        } else if (res.ok) {
          const json = (await res.json()) as ActivityResponse;
          if (json && json.success) {
            setActData(json);
          } else {
            setActError(true);
          }
        } else {
          setActError(true);
        }
      } catch (e) {
        setActError(true);
      } finally {
        setActLoading(false);
        setRefreshing(false);
      }
    },
    [lang]
  );

  useFocusEffect(
    useCallback(() => {
      load(true);
      loadActivity(true, actPeriod);
    }, [load, loadActivity, actPeriod])
  );

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    load(false);
    loadActivity(false, actPeriod);
  }, [load, loadActivity, actPeriod]);

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

  /**
   * Capability #17 - the Team Activity Digest block. Everything it prints is a
   * server-computed figure from this business's own activity-log rows.
   */
  const renderActivity = () => {
    const chips = (
      <View style={styles.actChips}>
        {ACT_PERIODS.map((p) => (
          <TouchableOpacity
            key={p}
            style={[styles.actChip, actPeriod === p ? styles.actChipActive : null]}
            onPress={() => setActPeriod(p)}
          >
            <Text style={[styles.actChipText, actPeriod === p ? styles.actChipTextActive : null]}>
              {t(`msimamizi_nyingine.act_period_${p}`)}
            </Text>
          </TouchableOpacity>
        ))}
      </View>
    );

    const head = (
      <View style={styles.actHead}>
        <Text style={styles.actTitle}>{t('msimamizi_nyingine.act_title')}</Text>
        <Text style={styles.actSubtitle}>{t('msimamizi_nyingine.act_subtitle')}</Text>
      </View>
    );

    if (actLoading) {
      return (
        <View style={styles.actSection}>
          {head}
          {chips}
          <View style={styles.stateBox}>
            <SmartCartLoader size="compact" color="#7c5cbf" label={t('msimamizi_nyingine.act_loading')} />
            <Text style={styles.stateText}>{t('msimamizi_nyingine.act_loading')}</Text>
          </View>
        </View>
      );
    }

    if (actError || !actData) {
      return (
        <View style={styles.actSection}>
          {head}
          {chips}
          <View style={styles.stateBox}>
            <Ionicons name="alert-circle-outline" size={34} color="#e74c3c" />
            <Text style={styles.stateTitle}>{t('msimamizi_nyingine.error_title')}</Text>
            <Text style={styles.stateText}>{t('msimamizi_nyingine.act_error')}</Text>
            <TouchableOpacity style={styles.retryBtn} onPress={() => loadActivity(true, actPeriod)}>
              <Text style={styles.retryBtnText}>{t('msimamizi_nyingine.retry')}</Text>
            </TouchableOpacity>
          </View>
        </View>
      );
    }

    const d = actData;
    const totals = d.totals;
    const comparison = d.comparison;
    const series = d.day_series || [];
    const maxCount = series.reduce((max, day) => Math.max(max, Number(day.count) || 0), 0) || 1;
    const prose = d.ai && !d.ai.degraded && d.digest_text ? d.digest_text : (d.narrative || []).join(' ');
    const activeMembers = (d.members || []).filter((m) => (Number(m.count) || 0) > 0);
    const directionColor =
      comparison.direction === 'up' ? '#27ae60' : comparison.direction === 'down' ? '#e67e22' : '#7c5cbf';
    const directionKey = ['up', 'down', 'from_zero'].includes(comparison.direction)
      ? comparison.direction
      : 'flat';

    return (
      <View style={styles.actSection}>
        {head}
        {chips}

        <View style={styles.statsRow}>
          <View style={styles.statChip}>
            <Text style={styles.statLabel}>{t('msimamizi_nyingine.act_stat_events')}</Text>
            <Text style={styles.statValue}>{totals.events}</Text>
          </View>
          <View style={styles.statChip}>
            <Text style={styles.statLabel}>{t('msimamizi_nyingine.act_stat_actors')}</Text>
            <Text style={styles.statValue}>{totals.actors}</Text>
          </View>
          <View style={styles.statChip}>
            <Text style={styles.statLabel}>{t('msimamizi_nyingine.act_stat_failed')}</Text>
            <Text style={styles.statValue}>{totals.failed}</Text>
          </View>
          <View style={styles.statChip}>
            <Text style={styles.statLabel}>{t('msimamizi_nyingine.act_stat_members')}</Text>
            <Text style={styles.statValue}>{totals.members}</Text>
          </View>
        </View>

        <View style={styles.summaryCard}>
          <Text style={styles.summaryLabel}>{t('msimamizi_nyingine.act_title')}</Text>
          <Text style={styles.summaryText}>{prose}</Text>
          <Text style={[styles.actCompare, { color: directionColor }]}>
            {t(`msimamizi_nyingine.act_dir_${directionKey}`)} ({t('msimamizi_nyingine.act_prev')}:{' '}
            {comparison.previous_events})
          </Text>
          <Text style={styles.summaryNote}>{t('msimamizi_nyingine.act_reword_note')}</Text>
        </View>

        {totals.events === 0 ? (
          <View style={styles.stateBox}>
            <Ionicons name="time-outline" size={34} color="#95a5a6" />
            <Text style={styles.stateTitle}>{t('msimamizi_nyingine.act_empty_title')}</Text>
            <Text style={styles.stateText}>{t('msimamizi_nyingine.act_empty_text')}</Text>
          </View>
        ) : null}

        <View style={styles.actPanel}>
          <Text style={styles.actPanelTitle}>{t('msimamizi_nyingine.act_days')}</Text>
          <View style={styles.actBars}>
            {series.map((day) => {
              const count = Number(day.count) || 0;
              const height = Math.max(3, Math.round((count / maxCount) * 54));
              return (
                <View key={day.date} style={styles.actBarCol}>
                  <View style={[styles.actBar, { height, backgroundColor: count ? '#7c5cbf' : '#dfe4ea' }]} />
                  <Text style={styles.actBarDay}>{String(day.date).slice(5)}</Text>
                </View>
              );
            })}
          </View>
        </View>

        {(d.categories || []).length > 0 ? (
          <View style={styles.actPanel}>
            <Text style={styles.actPanelTitle}>{t('msimamizi_nyingine.act_categories')}</Text>
            {(d.categories || []).map((row) => (
              <View key={row.key} style={styles.actRow}>
                <Text style={styles.actRowName}>{row.label}</Text>
                <Text style={styles.actRowValue}>
                  {row.count}
                  {row.failed ? ` / ${row.failed} ${t('msimamizi_nyingine.act_failed_tag')}` : ''}
                </Text>
              </View>
            ))}
          </View>
        ) : null}

        {activeMembers.length > 0 ? (
          <View style={styles.actPanel}>
            <Text style={styles.actPanelTitle}>{t('msimamizi_nyingine.act_members')}</Text>
            {activeMembers.map((member) => (
              <View key={member.id} style={styles.actRow}>
                <Text style={styles.actRowName}>{member.name}</Text>
                <Text style={styles.actRowValue}>
                  {member.count}
                  {member.last_at ? `  ${shortStamp(member.last_at)}` : ''}
                </Text>
              </View>
            ))}
          </View>
        ) : null}

        <View style={styles.actSilent}>
          <Text style={styles.actSilentText}>
            <Text style={styles.actSilentLabel}>{t('msimamizi_nyingine.act_silent')}: </Text>
            {(d.silent_members || []).length > 0
              ? (d.silent_members || []).join(', ')
              : t('msimamizi_nyingine.act_silent_none')}
          </Text>
        </View>

        <View style={styles.actPanel}>
          <Text style={styles.actPanelTitle}>{t('msimamizi_nyingine.act_noteworthy')}</Text>
          {(d.noteworthy || []).length === 0 ? (
            <Text style={styles.actEventMeta}>{t('msimamizi_nyingine.act_noteworthy_none')}</Text>
          ) : (
            (d.noteworthy || []).map((event) => (
              <View key={event.id || `${event.at}-${event.action}`} style={styles.actEvent}>
                <View style={styles.actEventTop}>
                  <Text style={styles.actEventAction}>{event.action}</Text>
                  <View
                    style={[
                      styles.actTag,
                      { backgroundColor: event.status === 'success' ? '#f3eeff' : '#fdeaea' },
                    ]}
                  >
                    <Text
                      style={[
                        styles.actTagText,
                        { color: event.status === 'success' ? '#7c5cbf' : '#e74c3c' },
                      ]}
                    >
                      {event.status === 'success'
                        ? event.category_label
                        : t('msimamizi_nyingine.act_failed_tag')}
                    </Text>
                  </View>
                </View>
                <Text style={styles.actEventMeta}>
                  {event.actor_name} - {shortStamp(event.at)}
                </Text>
                {(event.refs || []).length > 0 ? (
                  <Text style={styles.actRefs}>
                    {t('msimamizi_nyingine.act_refs')}:{' '}
                    {(event.refs || []).map((ref) => `${ref.field}: ${ref.value}`).join(' | ')}
                  </Text>
                ) : null}
              </View>
            ))
          )}
        </View>

        <View style={styles.limitationsBox}>
          <Text style={styles.limitationsTitle}>{t('msimamizi_nyingine.limitations_title')}</Text>
          {(d.limitations || []).map((line, index) => (
            <Text key={index} style={styles.limitationsText}>
              {line}
            </Text>
          ))}
          {(d.sources || []).length > 0 ? (
            <Text style={styles.limitationsText}>
              {t('msimamizi_nyingine.act_sources')}: {(d.sources || []).join(' ')}
            </Text>
          ) : null}
          <Text style={styles.generated}>
            {t('msimamizi_nyingine.generated', { date: shortStamp(d.generated_at) })}
          </Text>
        </View>
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
        <ThemeSelector />
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

        <TouchableOpacity style={styles.guideEntry} onPress={() => setGuideOpen(true)}>
          <Ionicons name="help-circle-outline" size={16} color="#e74c3c" />
          <Text style={styles.guideEntryText}>{t('onboarding_ai.title')}</Text>
        </TouchableOpacity>

        {renderBody()}

        {renderActivity()}
      </ScrollView>

      <OnboardingAssistant visible={guideOpen} onClose={() => setGuideOpen(false)} />
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
  guideEntry: {
    flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: '#fff',
    borderColor: '#ecf0f1', borderWidth: 1, borderRadius: 12,
    paddingHorizontal: 14, paddingVertical: 11, marginBottom: 14,
  },
  guideEntryText: { flex: 1, fontSize: 13.5, fontWeight: '700', color: '#e74c3c' },
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
  /* Team Activity Digest (capability #17) */
  actSection: { marginTop: 26, borderTopWidth: 1, borderTopColor: '#ecf0f1', paddingTop: 20 },
  actHead: { marginBottom: 10 },
  actTitle: { fontSize: 18, fontWeight: '800', color: '#2c3e50' },
  actSubtitle: { fontSize: 12.5, color: '#7f8c8d', marginTop: 3 },
  actChips: { flexDirection: 'row', flexWrap: 'wrap', gap: 7, marginBottom: 14 },
  actChip: {
    borderWidth: 1, borderColor: '#ecf0f1', backgroundColor: '#fff',
    borderRadius: 999, paddingHorizontal: 12, paddingVertical: 6,
  },
  actChipActive: { backgroundColor: '#f3eeff', borderColor: '#e3d9ff' },
  actChipText: { fontSize: 11.5, fontWeight: '700', color: '#5d6d7e' },
  actChipTextActive: { color: '#7c5cbf' },
  actCompare: { fontSize: 12, fontWeight: '700', marginTop: 8 },
  actPanel: {
    backgroundColor: '#fff', borderColor: '#ecf0f1', borderWidth: 1, borderRadius: 16,
    padding: 15, marginBottom: 12,
  },
  actPanelTitle: { fontSize: 13, fontWeight: '800', color: '#2c3e50', marginBottom: 8 },
  actBars: { flexDirection: 'row', alignItems: 'flex-end', gap: 4, height: 78 },
  actBarCol: { flex: 1, alignItems: 'center', justifyContent: 'flex-end' },
  actBar: { width: '100%', maxWidth: 24, borderTopLeftRadius: 5, borderTopRightRadius: 5 },
  actBarDay: { fontSize: 9, color: '#95a5a6', marginTop: 3 },
  actRow: {
    flexDirection: 'row', justifyContent: 'space-between', gap: 10,
    paddingVertical: 6, borderTopWidth: 1, borderTopColor: '#f4f6f9',
  },
  actRowName: { flexShrink: 1, fontSize: 13, fontWeight: '700', color: '#2c3e50' },
  actRowValue: { fontSize: 13, fontWeight: '700', color: '#2c3e50' },
  actSilent: {
    backgroundColor: '#fff8e1', borderColor: '#ffe9a8', borderWidth: 1,
    borderRadius: 12, padding: 11, marginBottom: 12,
  },
  actSilentText: { fontSize: 12.5, color: '#8d6e00', lineHeight: 18 },
  actSilentLabel: { fontWeight: '800' },
  actEvent: { borderTopWidth: 1, borderTopColor: '#f4f6f9', paddingVertical: 8 },
  actEventTop: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  actEventAction: { flex: 1, fontSize: 12.5, fontWeight: '700', color: '#2c3e50' },
  actEventMeta: { fontSize: 11.5, color: '#95a5a6', marginTop: 3 },
  actTag: { borderRadius: 999, paddingHorizontal: 9, paddingVertical: 3 },
  actTagText: { fontSize: 10.5, fontWeight: '800' },
  actRefs: { fontSize: 11.5, color: '#7f8c8d', marginTop: 4 },
});
