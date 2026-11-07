// Duka_mkononi/app/system_admin/logs.tsx
//
// Mobile twin of resources/views/system_admin/logs.blade.php.
//
// Four views on one page, exactly like the website: monitoring Timeline,
// Security alerts, Sessions and the legacy user_logs records. Each view has its
// own filters / pagination, event / session / alert detail sheets, alert status
// updates and CSV export.
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useEffect, useMemo, useState } from 'react';
import {
  Alert,
  Modal,
  RefreshControl,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import SmartCartLoader from '../../components/SmartCartLoader';
import { LOCALE_TAGS, useLang } from '../../context/LanguageContext';
import { useSession } from '../../context/SessionContext';
import { isSystemAdminRole } from '../../constants/session';
import { API_BASE_URL } from '../../constants/api';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';
import { useTheme, ThemeColors } from '../../lib/theme';

type LogsView = 'timeline' | 'security' | 'sessions' | 'legacy';

interface Paged<T> {
  items: T[];
  total: number;
  page: number;
  perPage: number;
}

interface Filters {
  range: 'today' | '7' | '30' | '';
  q: string;
  user_id: string;
  category: string;
  severity: string;
  outcome: string;
  source: string;
  security: boolean;
  ip: string;
}

const EMPTY_FILTERS: Filters = {
  range: '7',
  q: '',
  user_id: '',
  category: '',
  severity: '',
  outcome: '',
  source: '',
  security: false,
  ip: '',
};

const SEVERITIES = ['INFO', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];
const OUTCOMES = ['success', 'failed', 'rejected', 'denied', 'observed'];
const SOURCES = ['server', 'browser'];
const CATEGORIES = [
  'authentication',
  'authorization',
  'account',
  'admin',
  'business',
  'ai',
  'system',
  'page',
  'navigation',
  'input',
  'control',
  'search',
  'form',
  'file',
  'session',
  'security',
];

function severityColor(sev?: string) {
  return (
    {
      INFO: '#3498db',
      LOW: '#16a085',
      MEDIUM: '#f39c12',
      HIGH: '#e67e22',
      CRITICAL: '#e74c3c',
    } as Record<string, string>
  )[String(sev || '').toUpperCase()] || '#95a5a6';
}

function outcomeColor(outcome?: string) {
  return (
    {
      success: '#27ae60',
      failed: '#e74c3c',
      rejected: '#e67e22',
      denied: '#c0392b',
      observed: '#3498db',
    } as Record<string, string>
  )[outcome || ''] || '#95a5a6';
}

function legacyStatusColor(status?: string) {
  return (
    {
      success: '#27ae60',
      failed: '#e74c3c',
      approved: '#27ae60',
      pending: '#f39c12',
      rejected: '#e74c3c',
      suspended: '#e67e22',
      inactive: '#95a5a6',
    } as Record<string, string>
  )[status || ''] || '#95a5a6';
}

export default function SystemAdminLogs() {
  const { colors: tc } = useTheme();
  const styles = useMemo(() => makeStyles(tc), [tc]);
  const { t, lang } = useLang();
  const { signOut } = useSession();
  const router = useRouter();
  const params = useLocalSearchParams<{ user?: string }>();
  const requestedUser = Array.isArray(params.user) ? params.user[0] : params.user;

  const [view, setView] = useState<LogsView>('timeline');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [unavailable, setUnavailable] = useState(false);

  const [users, setUsers] = useState<any[]>([]);
  const [userLogs, setUserLogs] = useState<any[]>([]);
  const [summary, setSummary] = useState<any>(null);

  const [timeline, setTimeline] = useState<Paged<any>>({ items: [], total: 0, page: 1, perPage: 25 });
  const [alerts, setAlerts] = useState<any>({ items: [], total: 0, page: 1, perPage: 25, status: 'open' });
  const [sessions, setSessions] = useState<Paged<any>>({ items: [], total: 0, page: 1, perPage: 25 });

  const [filters, setFilters] = useState<Filters>({ ...EMPTY_FILTERS });
  const [legacyStatus, setLegacyStatus] = useState<'all' | 'success' | 'failed'>('all');
  const [legacyRange, setLegacyRange] = useState<'today' | 'week' | 'month'>('today');
  const [legacySearch, setLegacySearch] = useState('');
  const [legacyLogs, setLegacyLogs] = useState<any[]>([]);

  const [filterOpen, setFilterOpen] = useState(false);
  const [draftFilters, setDraftFilters] = useState<Filters>({ ...EMPTY_FILTERS });
  const [search, setSearch] = useState('');

  const [detail, setDetail] = useState<
    { mode: 'event' | 'session' | 'alert'; title: string; data: any } | null
  >(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [alertNote, setAlertNote] = useState('');
  const [alertResolution, setAlertResolution] = useState('');

  useEffect(() => {
    init();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [lang]);

  useEffect(() => {
    if (!loading) loadAlerts();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [alerts.status]);

  const token = async () => AsyncStorage.getItem('userToken');

  const init = async () => {
    try {
      const userToken = await AsyncStorage.getItem('userToken');
      const userDataStr = await AsyncStorage.getItem('userData');
      if (!userToken || !userDataStr) {
        Alert.alert(t('system_admin.auth_required'), t('system_admin.login_again'));
        await signOut();
        router.replace('/(tabs)');
        return;
      }
      const userData = JSON.parse(userDataStr);
      if (!isSystemAdminRole(userData.role)) {
        Alert.alert(t('system_admin.access_denied'), t('system_admin.err_no_permission'));
        router.replace('/(tabs)');
        return;
      }
      if (requestedUser) {
        setFilters((prev) => ({ ...prev, user_id: requestedUser }));
        setDraftFilters((prev) => ({ ...prev, user_id: requestedUser }));
      }
      await Promise.all([fetchUsers(), fetchRecentLogs(), fetchLegacyLogs()]);
      setLoading(false);
      const available = await loadCurrent(
        requestedUser ? { ...EMPTY_FILTERS, user_id: requestedUser } : filters,
      );
      if (available === false) setView('legacy');
    } catch (error) {
      console.error('Error initializing logs page:', error);
      setLoading(false);
    }
  };

  const fetchUsers = async () => {
    try {
      const userToken = await token();
      if (!userToken) return;
      const res = await fetchWithTimeout(`${API_BASE_URL}/api/admin/users?scope=platform`, {
        headers: { Authorization: `Bearer ${userToken}` },
      });
      if (!res.ok) return;
      const data = await res.json();
      setUsers(data.users || (Array.isArray(data) ? data : []));
    } catch (error) {
      console.error('Error fetching users:', error);
    }
  };

  const fetchRecentLogs = async () => {
    try {
      const userToken = await token();
      if (!userToken) return;
      const res = await fetchWithTimeout(`${API_BASE_URL}/api/admin/logs`, {
        headers: { Authorization: `Bearer ${userToken}` },
      });
      if (!res.ok) return;
      const data = await res.json();
      setUserLogs(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching legacy logs:', error);
    }
  };

  const legacyRangeStart = (range: 'today' | 'week' | 'month') => {
    const now = new Date();
    if (range === 'week') return new Date(now.getTime() - 7 * 86400000);
    if (range === 'month') return new Date(now.getTime() - 30 * 86400000);
    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
  };

  const fetchLegacyLogs = async (opts?: {
    status?: 'all' | 'success' | 'failed';
    range?: 'today' | 'week' | 'month';
    q?: string;
  }) => {
    try {
      const userToken = await token();
      if (!userToken) return;
      const status = opts?.status ?? legacyStatus;
      const range = opts?.range ?? legacyRange;
      const q = opts?.q ?? legacySearch;
      const params = new URLSearchParams();
      params.set('start_date', legacyRangeStart(range).toISOString());
      params.set('limit', '500');
      if (status !== 'all') params.set('status', status);
      if (q) params.set('q', q);
      const res = await fetchWithTimeout(
        `${API_BASE_URL}/api/admin/logs/search?${params.toString()}`,
        { headers: { Authorization: `Bearer ${userToken}` } },
      );
      if (!res.ok) return;
      const data = await res.json();
      setLegacyLogs(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching legacy logs:', error);
    }
  };

  const monParams = (f: Filters, extra?: Record<string, any>) => {
    const out: Record<string, any> = {};
    const now = Date.now();
    if (f.range === 'today') {
      const d = new Date();
      out.from = new Date(d.getFullYear(), d.getMonth(), d.getDate()).toISOString();
    } else if (f.range === '7') {
      out.from = new Date(now - 7 * 86400000).toISOString();
    } else if (f.range === '30') {
      out.from = new Date(now - 30 * 86400000).toISOString();
    }
    (['q', 'user_id', 'category', 'severity', 'outcome', 'source', 'ip'] as const).forEach((k) => {
      if (f[k]) out[k] = f[k];
    });
    if (f.security) out.is_security = 'true';
    return Object.assign(out, extra || {});
  };

  const monGet = async (path: string, query: Record<string, any>) => {
    const userToken = await token();
    const qs = Object.keys(query)
      .filter((k) => query[k] !== '' && query[k] !== null && query[k] !== undefined)
      .map((k) => `${encodeURIComponent(k)}=${encodeURIComponent(String(query[k]))}`)
      .join('&');
    const res = await fetchWithTimeout(
      `${API_BASE_URL}/api/${path}${qs ? `?${qs}` : ''}`,
      { headers: { Authorization: `Bearer ${userToken}` } },
    );
    if (!res.ok) throw new Error(`http_${res.status}`);
    return res.json();
  };

  const loadSummary = async (f: Filters) => {
    try {
      const data = await monGet('admin/monitoring/summary', monParams(f));
      setSummary(data);
      setUnavailable(!!(data && data.unavailable));
    } catch {
      setSummary(null);
    }
  };

  const loadTimeline = async (f: Filters, page: number = timeline.page) => {
    try {
      const data = await monGet(
        'admin/monitoring/timeline',
        monParams(f, { page, per_page: timeline.perPage }),
      );
      if (data.unavailable) {
        setUnavailable(true);
        setTimeline((s) => ({ ...s, items: [], total: 0 }));
        return false;
      }
      setUnavailable(false);
      setTimeline((s) => ({ ...s, items: data.items || [], total: data.total || 0 }));
      return true;
    } catch (error) {
      console.error('Error loading timeline:', error);
      return false;
    }
  };

  const loadAlerts = async () => {
    try {
      const data = await monGet('admin/monitoring/alerts', {
        status: alerts.status,
        page: alerts.page,
        per_page: alerts.perPage,
      });
      if (data.unavailable) {
        setUnavailable(true);
        setAlerts((s: any) => ({ ...s, items: [], total: 0 }));
        return;
      }
      setUnavailable(false);
      setAlerts((s: any) => ({ ...s, items: data.items || [], total: data.total || 0 }));
    } catch (error) {
      console.error('Error loading alerts:', error);
    }
  };

  const loadSessions = async (f: Filters, page: number = sessions.page) => {
    try {
      const data = await monGet(
        'admin/monitoring/sessions',
        monParams(f, { page, per_page: sessions.perPage }),
      );
      if (data.unavailable) {
        setUnavailable(true);
        setSessions((s) => ({ ...s, items: [], total: 0 }));
        return;
      }
      setUnavailable(false);
      setSessions((s) => ({ ...s, items: data.items || [], total: data.total || 0 }));
    } catch (error) {
      console.error('Error loading sessions:', error);
    }
  };

  const loadCurrent = async (
    f: Filters = filters,
    v: LogsView = view,
    resetPage = false,
  ) => {
    if (v === 'legacy') return true;
    setLoading(true);
    try {
      if (v === 'timeline') {
        const page = resetPage ? 1 : timeline.page;
        if (resetPage) setTimeline((s) => ({ ...s, page: 1 }));
        const results = await Promise.all([loadSummary(f), loadTimeline(f, page)]);
        return results[1];
      } else if (v === 'security') {
        if (resetPage) setAlerts((s: any) => ({ ...s, page: 1 }));
        await loadAlerts();
      } else if (v === 'sessions') {
        const page = resetPage ? 1 : sessions.page;
        if (resetPage) setSessions((s) => ({ ...s, page: 1 }));
        await loadSessions(f, page);
      }
      return true;
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const applyFilters = () => {
    const next = { ...draftFilters, q: search.trim().slice(0, 64) };
    setFilters(next);
    setFilterOpen(false);
    const targetView: LogsView = view === 'security' && next.user_id ? 'timeline' : view;
    if (targetView !== view) setView(targetView);
    loadCurrent(next, targetView, true);
  };

  const resetFilters = () => {
    setDraftFilters({ ...EMPTY_FILTERS });
    setSearch('');
    setFilters({ ...EMPTY_FILTERS });
    loadCurrent({ ...EMPTY_FILTERS }, view, true);
  };

  const runSetup = async () => {
    try {
      if (!(await requireNetwork())) return;
      const userToken = await token();
      const res = await fetchWithTimeout(`${API_BASE_URL}/api/admin/monitoring/setup`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${userToken}` },
      });
      if (!res.ok) throw new Error('failed');
      Alert.alert(t('app.success') || 'Success', t('system_admin.mon_setup_done'));
      await loadCurrent();
    } catch {
      Alert.alert(t('app.error'), t('system_admin.mon_setup_failed'));
    }
  };

  const exportEvents = async () => {
    try {
      const userToken = await token();
      const query = monParams(filters, { limit: 2000 });
      const qs = Object.keys(query)
        .map((k) => `${encodeURIComponent(k)}=${encodeURIComponent(String(query[k]))}`)
        .join('&');
      const res = await fetchWithTimeout(
        `${API_BASE_URL}/api/admin/monitoring/export?${qs}`,
        { headers: { Authorization: `Bearer ${userToken}` } },
      );
      if (!res.ok) throw new Error('failed');
      const csv = await res.text();
      const FileSystem = await import('expo-file-system/legacy');
      const Sharing = await import('expo-sharing');
      const uri = `${FileSystem.cacheDirectory}monitoring-${Date.now()}.csv`;
      await FileSystem.writeAsStringAsync(uri, csv, {
        encoding: FileSystem.EncodingType.UTF8,
      });
      if (await Sharing.isAvailableAsync()) {
        await Sharing.shareAsync(uri, { mimeType: 'text/csv' });
      } else {
        Alert.alert(t('system_admin.mon_export'), uri);
      }
    } catch {
      Alert.alert(t('app.error'), t('system_admin.err_network'));
    }
  };

  const formatEventTime = (value?: string) => {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString(LOCALE_TAGS[lang] || 'sw-TZ', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const formatTimeAgo = (value?: string) => {
    if (!value) return t('system_admin.never');
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return t('system_admin.never');
    const mins = Math.floor((Date.now() - date.getTime()) / 60000);
    if (mins < 1) return 'Sasa hivi';
    if (mins < 60) return t('system_admin.time_mins_ago', { mins });
    const hours = Math.floor(mins / 60);
    if (hours < 24) return t('system_admin.time_hours_ago', { hours });
    return t('system_admin.time_days_ago', { days: Math.floor(hours / 24) });
  };

  const formatDuration = (seconds?: number) => {
    if (!seconds || seconds < 0) return '-';
    const d = Math.floor(seconds / 86400);
    const h = Math.floor((seconds % 86400) / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    const parts: string[] = [];
    if (d) parts.push(`${d}${t('system_admin.mon_d')}`);
    if (h) parts.push(`${h}${t('system_admin.mon_h')}`);
    if (m) parts.push(`${m}${t('system_admin.mon_m')}`);
    if (!d && !h && !m) parts.push(`${s}${t('system_admin.mon_s')}`);
    return parts.join(' ');
  };

  const sourceLabel = (source?: string) =>
    source === 'browser'
      ? t('system_admin.mon_source_browser')
      : source === 'server'
        ? t('system_admin.mon_source_server')
        : source || '-';

  const deviceText = (device: any) => {
    if (!device) return t('system_admin.mon_device_unknown');
    return device.display || device.model || t('system_admin.mon_device_unknown');
  };

  const openEventDetail = async (id: string) => {
    setDetail({ mode: 'event', title: t('system_admin.mon_detail_title'), data: null });
    setDetailLoading(true);
    try {
      const data = await monGet(`admin/monitoring/events/${encodeURIComponent(id)}`, {});
      setDetail({ mode: 'event', title: t('system_admin.mon_detail_title'), data });
    } catch {
      setDetail({
        mode: 'event',
        title: t('system_admin.mon_detail_title'),
        data: { error: true },
      });
    } finally {
      setDetailLoading(false);
    }
  };

  const openSessionDetail = async (ref: string) => {
    setDetail({ mode: 'session', title: t('system_admin.mon_detail_session'), data: null });
    setDetailLoading(true);
    try {
      const data = await monGet(
        `admin/monitoring/sessions/${encodeURIComponent(ref)}`,
        {},
      );
      setDetail({ mode: 'session', title: t('system_admin.mon_detail_session'), data });
    } catch {
      setDetail({
        mode: 'session',
        title: t('system_admin.mon_detail_session'),
        data: { error: true },
      });
    } finally {
      setDetailLoading(false);
    }
  };

  const openAlertDetail = (id: string) => {
    const alert = (alerts.items || []).find((a: any) => String(a.id) === String(id));
    setAlertNote('');
    setAlertResolution('');
    setDetail({
      mode: 'alert',
      title: t('system_admin.mon_security_title'),
      data: alert || null,
    });
  };

  const updateAlertStatus = async (id: string, status: string) => {
    try {
      if (!(await requireNetwork())) return;
      const userToken = await token();
      const res = await fetchWithTimeout(
        `${API_BASE_URL}/api/admin/monitoring/alerts/${encodeURIComponent(id)}/status`,
        {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${userToken}`,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            status,
            note: alertNote.trim() || undefined,
            resolution: alertResolution.trim() || undefined,
          }),
        },
      );
      if (!res.ok) throw new Error('failed');
      const data = await res.json().catch(() => ({}));
      Alert.alert(t('app.success') || 'Success', t('system_admin.mon_alert_saved'));
      if (data.alert) setDetail((d) => (d ? { ...d, data: data.alert } : d));
      setAlertNote('');
      setAlertResolution('');
      await loadAlerts();
    } catch {
      Alert.alert(t('app.error'), t('system_admin.mon_alert_save_failed'));
    }
  };

  const investigateAlert = (alert: any) => {
    const next: Filters = {
      ...EMPTY_FILTERS,
      security: true,
      user_id: alert?.subject_user_id ? String(alert.subject_user_id) : '',
      ip: !alert?.subject_user_id && alert?.context?.ip ? String(alert.context.ip) : '',
    };
    setFilters(next);
    setDraftFilters(next);
    setDetail(null);
    setView('timeline');
    setTimeline((s) => ({ ...s, page: 1 }));
    loadCurrent(next, 'timeline', true);
  };

  const investigateUser = (userId: string) => {
    const next: Filters = { ...filters, user_id: userId };
    setFilters(next);
    setDraftFilters(next);
    setDetail(null);
    setView('timeline');
    setTimeline((s) => ({ ...s, page: 1 }));
    loadCurrent(next, 'timeline', true);
  };

  const changePage = (kind: 'timeline' | 'alerts' | 'sessions', page: number) => {
    const next = Math.max(1, page);
    if (kind === 'timeline') {
      setTimeline((s) => ({ ...s, page: next }));
      loadTimeline(filters, next);
    } else if (kind === 'alerts') {
      setAlerts((s: any) => ({ ...s, page: next }));
      setTimeout(() => loadAlerts(), 0);
    } else {
      setSessions((s) => ({ ...s, page: next }));
      loadSessions(filters, next);
    }
  };

  const legacyFiltered = useMemo(() => legacyLogs.slice(0, 500), [legacyLogs]);

  const lastPruneMessage = useMemo(() => {
    for (const log of userLogs) {
      if (log.action !== 'LOGS_AUTO_PRUNED') continue;
      let details = log.details;
      if (typeof details === 'string') {
        try {
          details = JSON.parse(details);
        } catch {
          details = null;
        }
      }
      const message = details && typeof details.message === 'string' ? details.message : '';
      if (message) return message;
    }
    return '';
  }, [userLogs]);

  const selectedUserLabel = useMemo(() => {
    if (!filters.user_id) return t('system_admin.mon_filter_all');
    const u = users.find((x) => String(x.id) === String(filters.user_id));
    return u ? u.full_name || u.email : filters.user_id;
  }, [filters.user_id, users, t]);

  const activeFilterCount = useMemo(() => {
    let n = 0;
    if (filters.user_id) n++;
    if (filters.category) n++;
    if (filters.severity) n++;
    if (filters.outcome) n++;
    if (filters.source) n++;
    if (filters.security) n++;
    if (filters.ip) n++;
    return n;
  }, [filters]);

  const summaryCards = useMemo(() => {
    const s = summary;
    if (!s || s.unavailable) return [];
    return [
      { value: s.total_events, label: t('system_admin.mon_summary_events'), color: '#3498db' },
      { value: s.logins, label: t('system_admin.mon_summary_logins'), color: '#27ae60' },
      { value: s.failed_logins, label: t('system_admin.mon_summary_failed_logins'), color: '#e67e22' },
      { value: s.high_risk_events, label: t('system_admin.mon_summary_high_risk'), color: '#e67e22' },
      { value: s.critical_events, label: t('system_admin.mon_summary_critical'), color: '#e74c3c' },
      { value: s.failed_operations, label: t('system_admin.mon_summary_failed_ops'), color: '#c0392b' },
      { value: s.admin_changes, label: t('system_admin.mon_summary_admin_changes'), color: '#9b59b6' },
      { value: s.active_users, label: t('system_admin.mon_summary_sessions'), color: '#16a085' },
      { value: s.open_alerts, label: t('system_admin.mon_summary_open_alerts'), color: '#f39c12' },
    ];
  }, [summary, t]);

  if (loading && timeline.items.length === 0 && alerts.items.length === 0 && sessions.items.length === 0) {
    return (
      <View style={[styles.loadingContainer, { backgroundColor: tc.page }]}>
        <SmartCartLoader size="large" color={tc.primary} />
        <Text style={[styles.loadingText, { color: tc.muted }]}>
          {t('system_admin.mon_loading')}
        </Text>
      </View>
    );
  }

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: tc.page }]}>
      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => {
              setRefreshing(true);
              if (view === 'legacy') {
                Promise.all([fetchRecentLogs(), fetchLegacyLogs()]).finally(() =>
                  setRefreshing(false),
                );
              } else {
                loadCurrent();
              }
            }}
          />
        }
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.pageHead}>
          <View style={{ flex: 1 }}>
            <Text style={[styles.pageTitle, { color: tc.ink }]}>
              {t('system_admin.logs_title')}
            </Text>
            <Text style={[styles.pageSub, { color: tc.muted }]}>
              {t('system_admin.logs_subtitle')}
            </Text>
          </View>
          <TouchableOpacity
            onPress={() =>
              view === 'legacy'
                ? Promise.all([fetchRecentLogs(), fetchLegacyLogs()])
                : loadCurrent()
            }
            style={[styles.refreshBtn, { borderColor: tc.line2, backgroundColor: tc.card }]}
          >
            <Ionicons name="refresh" size={15} color={tc.ink} />
            <Text style={[styles.refreshText, { color: tc.ink }]}>
              {t('system_admin.refresh')}
            </Text>
          </TouchableOpacity>
        </View>

        <View style={[styles.sectionCard, { backgroundColor: tc.card }]}>
          <View style={styles.sectionHeader}>
            <Text style={[styles.sectionTitle, { color: tc.ink }]}>
              {t('system_admin.mon_timeline_title')}
            </Text>
            {view !== 'legacy' ? (
              <TouchableOpacity
                onPress={exportEvents}
                style={[styles.outlineChip, { borderColor: tc.line2 }]}
              >
                <Text style={[styles.outlineChipText, { color: tc.ink }]}>
                  {t('system_admin.mon_export')}
                </Text>
              </TouchableOpacity>
            ) : null}
          </View>

          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.viewChips}>
            {(
              [
                ['timeline', t('system_admin.tab_activity')],
                ['security', t('system_admin.tab_security')],
                ['sessions', t('system_admin.tab_sessions')],
                ['legacy', t('system_admin.tab_legacy')],
              ] as [LogsView, string][]
            ).map(([id, label]) => {
              const active = view === id;
              return (
                <TouchableOpacity
                  key={id}
                  onPress={() => {
                    setView(id);
                    if (id === 'legacy') {
                      fetchLegacyLogs();
                    } else {
                      loadCurrent(filters, id, false);
                    }
                  }}
                  style={[
                    styles.chip,
                    {
                      backgroundColor: active ? tc.primary : tc.page,
                      borderColor: active ? tc.primary : tc.line2,
                    },
                  ]}
                >
                  <Text style={[styles.chipText, { color: active ? '#fff' : tc.muted }]}>
                    {label}
                  </Text>
                </TouchableOpacity>
              );
            })}
          </ScrollView>

          {unavailable && view !== 'legacy' ? (
            <View style={styles.setupBanner}>
              <Text style={styles.setupBannerText}>{t('system_admin.mon_setup_banner')}</Text>
              <TouchableOpacity onPress={runSetup} style={styles.setupBtn}>
                <Text style={styles.setupBtnText}>{t('system_admin.mon_setup_btn')}</Text>
              </TouchableOpacity>
            </View>
          ) : null}

          {view === 'timeline' ? (
            <>
              {summaryCards.length > 0 ? (
                <View style={styles.statsGrid}>
                  {summaryCards.map((card, idx) => (
                    <View
                      key={idx}
                      style={[styles.statCard, { borderLeftColor: card.color }]}
                    >
                      <Text style={[styles.statValue, { color: card.color }]}>
                        {card.value ?? 0}
                      </Text>
                      <Text style={[styles.statLabel, { color: tc.muted }]}>
                        {card.label}
                      </Text>
                    </View>
                  ))}
                </View>
              ) : null}

              <FilterBar
                search={search}
                setSearch={setSearch}
                onOpenFilters={() => {
                  setDraftFilters({ ...filters });
                  setFilterOpen(true);
                }}
                activeCount={activeFilterCount}
                range={filters.range}
                setRange={(r) => {
                  const next = { ...filters, range: r };
                  setFilters(next);
                  setDraftFilters(next);
                  setTimeline((s) => ({ ...s, page: 1 }));
                  loadCurrent(next, view, true);
                }}
                userLabel={selectedUserLabel}
                security={filters.security}
                t={t}
                styles={styles}
                tc={tc}
              />

              {timeline.items.length > 0 ? (
                timeline.items.map((ev: any) => (
                  <TimelineCard
                    key={String(ev.id)}
                    ev={ev}
                    onPress={() => openEventDetail(ev.id)}
                    t={t}
                    styles={styles}
                    tc={tc}
                    formatEventTime={formatEventTime}
                    sourceLabel={sourceLabel}
                    deviceText={deviceText}
                  />
                ))
              ) : (
                <Empty text={t('system_admin.mon_no_events')} tc={tc} styles={styles} />
              )}

              <Pager
                state={timeline}
                onPage={(p) => changePage('timeline', p)}
                t={t}
                styles={styles}
                tc={tc}
              />
            </>
          ) : null}

          {view === 'security' ? (
            <>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.viewChips}>
                {(
                  [
                    ['open', t('system_admin.mon_security_open')],
                    ['acknowledged', t('system_admin.mon_security_ack')],
                    ['resolved', t('system_admin.mon_security_resolved')],
                    ['', t('system_admin.mon_security_all')],
                  ] as [string, string][]
                ).map(([id, label]) => {
                  const active = alerts.status === id;
                  return (
                    <TouchableOpacity
                      key={id || 'all'}
                      onPress={() => {
                        setAlerts((s: any) => ({ ...s, status: id, page: 1 }));
                      }}
                      style={[
                        styles.chip,
                        {
                          backgroundColor: active ? tc.primary : tc.page,
                          borderColor: active ? tc.primary : tc.line2,
                        },
                      ]}
                    >
                      <Text style={[styles.chipText, { color: active ? '#fff' : tc.muted }]}>
                        {label}
                      </Text>
                    </TouchableOpacity>
                  );
                })}
              </ScrollView>

              {alerts.items.length > 0 ? (
                alerts.items.map((a: any) => (
                  <AlertCard
                    key={String(a.id)}
                    alert={a}
                    onPress={() => openAlertDetail(a.id)}
                    t={t}
                    styles={styles}
                    tc={tc}
                    formatEventTime={formatEventTime}
                  />
                ))
              ) : (
                <Empty text={t('system_admin.mon_no_alerts')} tc={tc} styles={styles} />
              )}

              <Pager
                state={alerts}
                onPage={(p) => changePage('alerts', p)}
                t={t}
                styles={styles}
                tc={tc}
              />
            </>
          ) : null}

          {view === 'sessions' ? (
            <>
              <FilterBar
                search={search}
                setSearch={setSearch}
                onOpenFilters={() => {
                  setDraftFilters({ ...filters });
                  setFilterOpen(true);
                }}
                activeCount={activeFilterCount}
                range={filters.range}
                setRange={(r) => {
                  const next = { ...filters, range: r };
                  setFilters(next);
                  setDraftFilters(next);
                  setSessions((s) => ({ ...s, page: 1 }));
                  loadCurrent(next, view, true);
                }}
                userLabel={selectedUserLabel}
                security={filters.security}
                t={t}
                styles={styles}
                tc={tc}
              />

              {sessions.items.length > 0 ? (
                sessions.items.map((s: any) => (
                  <SessionCard
                    key={String(s.session_ref || s.id)}
                    session={s}
                    onPress={() => openSessionDetail(s.session_ref)}
                    t={t}
                    styles={styles}
                    tc={tc}
                    formatEventTime={formatEventTime}
                    formatDuration={formatDuration}
                    deviceText={deviceText}
                  />
                ))
              ) : (
                <Empty text={t('system_admin.mon_no_sessions')} tc={tc} styles={styles} />
              )}

              <Pager
                state={sessions}
                onPage={(p) => changePage('sessions', p)}
                t={t}
                styles={styles}
                tc={tc}
              />
            </>
          ) : null}

          {view === 'legacy' ? (
            <>
              <Text style={[styles.legacyNote, { color: tc.muted }]}>
                {t('system_admin.mon_legacy_note')}
              </Text>
              {lastPruneMessage ? (
                <View style={[styles.setupBanner, { backgroundColor: '#eef7ee', borderColor: '#27ae60' }]}>
                  <Text style={[styles.setupBannerText, { color: '#1e6b3a' }]}>
                    {lastPruneMessage}
                  </Text>
                </View>
              ) : null}
              <Text style={[styles.sectionTitle, { color: tc.ink }]}>
                {t('system_admin.system_logs_title', { count: legacyFiltered.length })}
              </Text>

              <View style={[styles.searchRow, { marginTop: 10 }]}>
                <View style={[styles.searchBox, { backgroundColor: tc.page, borderColor: tc.line2 }]}>
                  <Ionicons name="search" size={16} color={tc.muted2} />
                  <TextInput
                    value={legacySearch}
                    onChangeText={setLegacySearch}
                    onSubmitEditing={() => fetchLegacyLogs({ q: legacySearch })}
                    placeholder={t('system_admin.mon_search')}
                    placeholderTextColor={tc.muted2}
                    style={[styles.searchInput, { color: tc.ink }]}
                    autoCapitalize="none"
                  />
                </View>
                <TouchableOpacity
                  onPress={() => fetchLegacyLogs({ q: legacySearch })}
                  style={[styles.outlineChip, { borderColor: tc.line2 }]}
                >
                  <Text style={[styles.outlineChipText, { color: tc.ink }]}>
                    {t('system_admin.mon_apply')}
                  </Text>
                </TouchableOpacity>
              </View>

              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.viewChips}>
                {(
                  [
                    ['all', t('system_admin.filter_all_status')],
                    ['success', t('system_admin.filter_success')],
                    ['failed', t('system_admin.filter_failed')],
                  ] as ['all' | 'success' | 'failed', string][]
                ).map(([id, label]) => {
                  const active = legacyStatus === id;
                  return (
                    <TouchableOpacity
                      key={id}
                      onPress={() => {
                        setLegacyStatus(id);
                        fetchLegacyLogs({ status: id });
                      }}
                      style={[
                        styles.chip,
                        {
                          backgroundColor: active ? tc.primary : tc.page,
                          borderColor: active ? tc.primary : tc.line2,
                        },
                      ]}
                    >
                      <Text style={[styles.chipText, { color: active ? '#fff' : tc.muted }]}>
                        {label}
                      </Text>
                    </TouchableOpacity>
                  );
                })}
                <View style={{ width: 10 }} />
                {(
                  [
                    ['today', t('system_admin.filter_today')],
                    ['week', t('system_admin.filter_week')],
                    ['month', t('system_admin.filter_month')],
                  ] as ['today' | 'week' | 'month', string][]
                ).map(([id, label]) => {
                  const active = legacyRange === id;
                  return (
                    <TouchableOpacity
                      key={id}
                      onPress={() => {
                        setLegacyRange(id);
                        fetchLegacyLogs({ range: id });
                      }}
                      style={[
                        styles.chip,
                        {
                          backgroundColor: active ? tc.primary : tc.page,
                          borderColor: active ? tc.primary : tc.line2,
                        },
                      ]}
                    >
                      <Text style={[styles.chipText, { color: active ? '#fff' : tc.muted }]}>
                        {label}
                      </Text>
                    </TouchableOpacity>
                  );
                })}
              </ScrollView>

              {legacyFiltered.length > 0 ? (
                legacyFiltered.map((log: any) => {
                  const color = legacyStatusColor(log.status);
                  return (
                    <View key={String(log.id)} style={[styles.logCard, { borderColor: tc.line2 }]}>
                      <View style={styles.logTop}>
                        <Text style={[styles.logAction, { color: tc.ink }]}>{log.action}</Text>
                        <Text style={[styles.logTime, { color: tc.muted2 }]}>
                          {formatTimeAgo(log.created_at)}
                        </Text>
                      </View>
                      <Text style={[styles.logEndpoint, { color: tc.primary }]}>
                        {log.endpoint}
                      </Text>
                      <View style={styles.logBottom}>
                        <View style={[styles.pill, { backgroundColor: `${color}20` }]}>
                          <Text style={[styles.pillText, { color }]}>{log.status}</Text>
                        </View>
                        <Text style={[styles.logMeta, { color: tc.muted }]}>
                          👤 {log.user_email || log.users?.full_name || log.users?.email || t('system_admin.guest')}
                        </Text>
                      </View>
                    </View>
                  );
                })
              ) : (
                <Empty text={t('system_admin.no_records_found')} tc={tc} styles={styles} />
              )}
            </>
          ) : null}
        </View>
      </ScrollView>

      <Modal
        animationType="slide"
        transparent
        visible={filterOpen}
        onRequestClose={() => setFilterOpen(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={[styles.modalContent, { backgroundColor: tc.card }]}>
            <View style={[styles.modalHeader, { borderBottomColor: tc.line2 }]}>
              <Text style={[styles.modalTitle, { color: tc.ink }]}>
                {t('system_admin.mon_filter_range')}
              </Text>
              <TouchableOpacity onPress={() => setFilterOpen(false)}>
                <Ionicons name="close" size={22} color={tc.muted} />
              </TouchableOpacity>
            </View>
            <ScrollView contentContainerStyle={styles.modalBody}>
              <FilterGroup
                label={t('system_admin.mon_filter_range')}
                options={[
                  ['today', t('system_admin.mon_range_today')],
                  ['7', t('system_admin.mon_range_7')],
                  ['30', t('system_admin.mon_range_30')],
                  ['', t('system_admin.mon_range_all')],
                ]}
                value={draftFilters.range}
                onChange={(v) => setDraftFilters((f) => ({ ...f, range: v as Filters['range'] }))}
                styles={styles}
                tc={tc}
              />
              <FilterGroup
                label={t('system_admin.mon_filter_severity')}
                options={[['', t('system_admin.mon_filter_all')], ...SEVERITIES.map((s) => [s, s] as [string, string])]}
                value={draftFilters.severity}
                onChange={(v) => setDraftFilters((f) => ({ ...f, severity: v }))}
                styles={styles}
                tc={tc}
              />
              <FilterGroup
                label={t('system_admin.mon_filter_outcome')}
                options={[['', t('system_admin.mon_filter_all')], ...OUTCOMES.map((s) => [s, s] as [string, string])]}
                value={draftFilters.outcome}
                onChange={(v) => setDraftFilters((f) => ({ ...f, outcome: v }))}
                styles={styles}
                tc={tc}
              />
              <FilterGroup
                label={t('system_admin.mon_filter_source')}
                options={[
                  ['', t('system_admin.mon_filter_all')],
                  ...SOURCES.map((s) => [s, sourceLabel(s)] as [string, string]),
                ]}
                value={draftFilters.source}
                onChange={(v) => setDraftFilters((f) => ({ ...f, source: v }))}
                styles={styles}
                tc={tc}
              />
              <FilterGroup
                label={t('system_admin.mon_filter_category')}
                options={[['', t('system_admin.mon_filter_all')], ...CATEGORIES.map((s) => [s, s] as [string, string])]}
                value={draftFilters.category}
                onChange={(v) => setDraftFilters((f) => ({ ...f, category: v }))}
                styles={styles}
                tc={tc}
              />
              <FilterGroup
                label={t('system_admin.mon_filter_user')}
                options={[
                  ['', t('system_admin.mon_filter_all')],
                  ...users.map((u) => [String(u.id), u.full_name || u.email] as [string, string]),
                ]}
                value={draftFilters.user_id}
                onChange={(v) => setDraftFilters((f) => ({ ...f, user_id: v }))}
                styles={styles}
                tc={tc}
              />
              <TouchableOpacity
                onPress={() =>
                  setDraftFilters((f) => ({ ...f, security: !f.security }))
                }
                style={[
                  styles.securityToggle,
                  {
                    borderColor: draftFilters.security ? tc.primary : tc.line2,
                    backgroundColor: draftFilters.security ? `${tc.primary}18` : tc.page,
                  },
                ]}
              >
                <Ionicons
                  name={draftFilters.security ? 'checkbox' : 'square-outline'}
                  size={18}
                  color={draftFilters.security ? tc.primary : tc.muted}
                />
                <Text style={[styles.securityText, { color: tc.ink }]}>
                  {t('system_admin.mon_filter_security_only')}
                </Text>
              </TouchableOpacity>

              <View style={styles.filterActions}>
                <TouchableOpacity
                  onPress={applyFilters}
                  style={[styles.applyBtn, { backgroundColor: tc.primary }]}
                >
                  <Text style={styles.applyText}>{t('system_admin.mon_apply')}</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  onPress={resetFilters}
                  style={[styles.resetBtn, { borderColor: tc.line2 }]}
                >
                  <Text style={[styles.resetText, { color: tc.muted }]}>
                    {t('system_admin.mon_reset')}
                  </Text>
                </TouchableOpacity>
              </View>
            </ScrollView>
          </View>
        </View>
      </Modal>

      <Modal
        animationType="slide"
        transparent
        visible={!!detail}
        onRequestClose={() => setDetail(null)}
      >
        <View style={styles.modalOverlay}>
          <View style={[styles.modalContent, { backgroundColor: tc.card }]}>
            <View style={[styles.modalHeader, { borderBottomColor: tc.line2 }]}>
              <Text style={[styles.modalTitle, { color: tc.ink }]}>
                {detail?.title || t('system_admin.mon_detail_title')}
              </Text>
              <TouchableOpacity onPress={() => setDetail(null)}>
                <Ionicons name="close" size={22} color={tc.muted} />
              </TouchableOpacity>
            </View>
            <ScrollView contentContainerStyle={styles.modalBody}>
              {detailLoading ? (
                <View style={styles.detailLoading}>
                  <SmartCartLoader size="compact" color={tc.primary} />
                </View>
              ) : detail?.data?.error ? (
                <Text style={[styles.emptyText, { color: tc.muted2 }]}>
                  {t('system_admin.mon_unavailable')}
                </Text>
              ) : detail?.mode === 'event' && detail.data ? (
                <EventDetail
                  data={detail.data}
                  onEvent={openEventDetail}
                  onSession={openSessionDetail}
                  t={t}
                  styles={styles}
                  tc={tc}
                  formatEventTime={formatEventTime}
                  sourceLabel={sourceLabel}
                  deviceText={deviceText}
                />
              ) : detail?.mode === 'session' && detail.data ? (
                <SessionDetail
                  data={detail.data}
                  onEvent={openEventDetail}
                  onInvestigate={investigateUser}
                  t={t}
                  styles={styles}
                  tc={tc}
                  formatEventTime={formatEventTime}
                  formatDuration={formatDuration}
                  deviceText={deviceText}
                />
              ) : detail?.mode === 'alert' && detail.data ? (
                <AlertDetail
                  alert={detail.data}
                  note={alertNote}
                  resolution={alertResolution}
                  setNote={setAlertNote}
                  setResolution={setAlertResolution}
                  onStatus={updateAlertStatus}
                  onInvestigate={() => investigateAlert(detail.data)}
                  t={t}
                  styles={styles}
                  tc={tc}
                  formatEventTime={formatEventTime}
                />
              ) : (
                <Text style={[styles.emptyText, { color: tc.muted2 }]}>
                  {t('system_admin.mon_no_alerts')}
                </Text>
              )}
            </ScrollView>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

/* ------------------------------- sub views ------------------------------- */

function FilterBar({
  search,
  setSearch,
  onOpenFilters,
  activeCount,
  range,
  setRange,
  userLabel,
  security,
  t,
  styles,
  tc,
}: any) {
  const ranges: [Filters['range'], string][] = [
    ['today', t('system_admin.mon_range_today')],
    ['7', t('system_admin.mon_range_7')],
    ['30', t('system_admin.mon_range_30')],
    ['', t('system_admin.mon_range_all')],
  ];
  return (
    <View style={styles.filterBar}>
      <View style={styles.searchRow}>
        <View style={[styles.searchBox, { backgroundColor: tc.page, borderColor: tc.line2 }]}>
          <Ionicons name="search" size={16} color={tc.muted2} />
          <TextInput
            value={search}
            onChangeText={setSearch}
            placeholder={t('system_admin.mon_search')}
            placeholderTextColor={tc.muted2}
            style={[styles.searchInput, { color: tc.ink }]}
            autoCapitalize="none"
          />
        </View>
        <TouchableOpacity
          onPress={onOpenFilters}
          style={[styles.filterBtn, { borderColor: activeCount ? tc.primary : tc.line2 }]}
        >
          <Ionicons
            name="options-outline"
            size={17}
            color={activeCount ? tc.primary : tc.muted}
          />
          {activeCount > 0 ? (
            <View style={[styles.filterCount, { backgroundColor: tc.primary }]}>
              <Text style={styles.filterCountText}>{activeCount}</Text>
            </View>
          ) : null}
        </TouchableOpacity>
      </View>

      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.viewChips}>
        {ranges.map(([id, label]) => {
          const active = range === id;
          return (
            <TouchableOpacity
              key={id || 'all'}
              onPress={() => setRange(id)}
              style={[
                styles.chip,
                {
                  backgroundColor: active ? tc.primary : tc.page,
                  borderColor: active ? tc.primary : tc.line2,
                },
              ]}
            >
              <Text style={[styles.chipText, { color: active ? '#fff' : tc.muted }]}>
                {label}
              </Text>
            </TouchableOpacity>
          );
        })}
        <View style={[styles.chip, { borderColor: tc.line2, backgroundColor: tc.page }]}>
          <Text style={[styles.chipText, { color: tc.muted }]}>👤 {userLabel}</Text>
        </View>
        {security ? (
          <View style={[styles.chip, { borderColor: tc.primary, backgroundColor: `${tc.primary}18` }]}>
            <Text style={[styles.chipText, { color: tc.primary }]}>
              🛡️ {t('system_admin.mon_filter_security_only')}
            </Text>
          </View>
        ) : null}
      </ScrollView>
    </View>
  );
}

function TimelineCard({ ev, onPress, t, styles, tc, formatEventTime, sourceLabel, deviceText }: any) {
  const user = ev.user ? ev.user.full_name || ev.user.email : t('system_admin.mon_guest');
  const module = ev.page || ev.route || '';
  return (
    <TouchableOpacity
      onPress={onPress}
      style={[styles.logCard, { borderColor: tc.line2, borderLeftColor: severityColor(ev.severity) }]}
    >
      <View style={styles.logTop}>
        <Text style={[styles.logAction, { color: tc.ink }]}>
          {ev.event_name || ''} {ev.is_security ? '🛡️' : ''}
        </Text>
        <Text style={[styles.logTime, { color: tc.muted2 }]}>
          {formatEventTime(ev.created_at)}
        </Text>
      </View>
      <Text style={[styles.logDesc, { color: tc.ink }]}>{ev.description || ''}</Text>
      <View style={styles.logBottom}>
        <View style={styles.logBadges}>
          <Badge text={ev.severity || '-'} color={severityColor(ev.severity)} styles={styles} />
          <Badge text={ev.outcome || '-'} color={outcomeColor(ev.outcome)} styles={styles} />
          <Text style={[styles.logMeta, { color: tc.muted }]}>👤 {user}</Text>
          {module ? (
            <Text style={[styles.logMeta, { color: tc.primary }]}>{module}</Text>
          ) : null}
        </View>
        <Text style={[styles.logMetaSmall, { color: tc.muted2 }]}>
          {sourceLabel(ev.source)}
          {ev.ip_address ? ` · 🌐 ${ev.ip_address}` : ''}
          {ev.device ? ` · 🖥️ ${deviceText(ev.device)}` : ''}
        </Text>
      </View>
    </TouchableOpacity>
  );
}

function AlertCard({ alert, onPress, t, styles, tc, formatEventTime }: any) {
  const subject = alert.subject_user
    ? alert.subject_user.full_name || alert.subject_user.email
    : String(alert.subject || '').replace(/^(user|acct|ip):/, '');
  const statusLabel =
    {
      open: t('system_admin.mon_security_open'),
      acknowledged: t('system_admin.mon_security_ack'),
      resolved: t('system_admin.mon_security_resolved'),
    }[alert.status as string] || alert.status;
  const color =
    { open: '#e74c3c', acknowledged: '#f39c12', resolved: '#27ae60' }[
      alert.status as string
    ] || '#95a5a6';
  return (
    <TouchableOpacity
      onPress={onPress}
      style={[styles.logCard, { borderColor: tc.line2, borderLeftColor: severityColor(alert.severity) }]}
    >
      <View style={styles.logTop}>
        <View style={styles.logBadges}>
          <Badge text={alert.severity || '-'} color={severityColor(alert.severity)} styles={styles} />
          <Text style={[styles.logAction, { color: tc.ink }]}>{alert.rule || ''}</Text>
        </View>
        <Text style={[styles.logTime, { color: tc.muted2 }]}>
          {formatEventTime(alert.last_seen)}
        </Text>
      </View>
      <Text style={[styles.logDesc, { color: tc.ink }]}>{alert.summary || ''}</Text>
      <View style={styles.logBottom}>
        <Text style={[styles.logMeta, { color: tc.muted }]}>
          {t('system_admin.mon_alert_subject')}: <Text style={{ fontWeight: '700' }}>{subject}</Text>{' '}
          · {t('system_admin.mon_alert_count', { count: alert.occurrence_count })}
        </Text>
        <Badge text={statusLabel} color={color} styles={styles} filled />
      </View>
    </TouchableOpacity>
  );
}

function SessionCard({
  session,
  onPress,
  t,
  styles,
  tc,
  formatEventTime,
  formatDuration,
  deviceText,
}: any) {
  const user = session.user
    ? session.user.full_name || session.user.email
    : t('system_admin.mon_guest');
  const statusLabel =
    {
      active: t('system_admin.mon_session_status_active'),
      ended: t('system_admin.mon_session_status_ended'),
      expired: t('system_admin.mon_session_status_expired'),
    }[session.status as string] || session.status;
  const color =
    { active: '#27ae60', ended: '#95a5a6', expired: '#e67e22' }[
      session.status as string
    ] || '#95a5a6';
  return (
    <TouchableOpacity
      onPress={onPress}
      style={[styles.logCard, { borderColor: tc.line2, borderLeftColor: color }]}
    >
      <View style={styles.logTop}>
        <Text style={[styles.logAction, { color: tc.ink }]}>🔐 {user}</Text>
        <Text style={[styles.logTime, { color: tc.muted2 }]}>
          {formatEventTime(session.last_seen_at)}
        </Text>
      </View>
      <Text style={[styles.logDesc, { color: tc.muted }]}>
        {t('system_admin.mon_session_started')}: {formatEventTime(session.started_at)} ·{' '}
        {t('system_admin.mon_session_duration')}: {formatDuration(session.duration_seconds)}
        {session.ip_address ? ` · 🌐 ${session.ip_address}` : ''}
        {session.device ? ` · 🖥️ ${deviceText(session.device)}` : ''}
      </Text>
      <View style={styles.logBottom}>
        <Badge text={statusLabel} color={color} styles={styles} filled />
        <Text style={[styles.logMetaSmall, { color: tc.muted2 }]}>
          {String(session.session_ref || '').slice(0, 8)}…
        </Text>
      </View>
    </TouchableOpacity>
  );
}

function Badge({ text, color, styles, filled }: any) {
  return (
    <View
      style={[
        styles.pill,
        { backgroundColor: filled ? `${color}22` : `${color}18`, borderColor: `${color}33` },
      ]}
    >
      <Text style={[styles.pillText, { color }]}>{text}</Text>
    </View>
  );
}

function Pager({ state, onPage, t, styles, tc }: any) {
  const pages = Math.max(1, Math.ceil((state.total || 0) / (state.perPage || 25)));
  return (
    <View style={styles.pager}>
      <Text style={[styles.pagerInfo, { color: tc.muted }]}>
        {t('system_admin.mon_total', { count: state.total || 0 })}
      </Text>
      {pages > 1 ? (
        <View style={styles.pagerControls}>
          <TouchableOpacity
            disabled={state.page <= 1}
            onPress={() => onPage(state.page - 1)}
            style={[
              styles.pagerBtn,
              { borderColor: tc.line2, opacity: state.page <= 1 ? 0.4 : 1 },
            ]}
          >
            <Ionicons name="chevron-back" size={15} color={tc.ink} />
          </TouchableOpacity>
          <Text style={[styles.pagerInfo, { color: tc.ink }]}>
            {t('system_admin.mon_page_of', { page: state.page, pages })}
          </Text>
          <TouchableOpacity
            disabled={state.page >= pages}
            onPress={() => onPage(state.page + 1)}
            style={[
              styles.pagerBtn,
              { borderColor: tc.line2, opacity: state.page >= pages ? 0.4 : 1 },
            ]}
          >
            <Ionicons name="chevron-forward" size={15} color={tc.ink} />
          </TouchableOpacity>
        </View>
      ) : null}
    </View>
  );
}

function Empty({ text, tc, styles }: any) {
  return (
    <View style={styles.emptyState}>
      <Ionicons name="time-outline" size={34} color={tc.muted2} />
      <Text style={[styles.emptyText, { color: tc.muted2 }]}>{text}</Text>
    </View>
  );
}

function FilterGroup({ label, options, value, onChange, styles, tc }: any) {
  return (
    <View style={styles.filterGroup}>
      <Text style={[styles.filterGroupLabel, { color: tc.muted }]}>{label}</Text>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.viewChips}>
        {options.map(([id, text]: [string, string]) => {
          const active = value === id;
          return (
            <TouchableOpacity
              key={id || 'all'}
              onPress={() => onChange(id)}
              style={[
                styles.chip,
                {
                  backgroundColor: active ? tc.primary : tc.page,
                  borderColor: active ? tc.primary : tc.line2,
                },
              ]}
            >
              <Text style={[styles.chipText, { color: active ? '#fff' : tc.muted }]}>
                {text}
              </Text>
            </TouchableOpacity>
          );
        })}
      </ScrollView>
    </View>
  );
}

function DetailRow({ label, value, styles, tc }: any) {
  return (
    <View style={[styles.detailRow, { borderBottomColor: tc.line }]}>
      <Text style={[styles.detailLabel, { color: tc.muted }]}>{label}</Text>
      <Text style={[styles.detailValue, { color: tc.ink }]}>{value}</Text>
    </View>
  );
}

function EventDetail({
  data,
  onEvent,
  onSession,
  t,
  styles,
  tc,
  formatEventTime,
  sourceLabel,
  deviceText,
}: any) {
  const ev = data?.event || {};
  const related = data?.related || [];
  const user = ev.user
    ? (ev.user.full_name || ev.user.email) + (ev.user.role ? ` · ${ev.user.role}` : '')
    : t('system_admin.mon_guest');
  const metaJson = ev.metadata ? JSON.stringify(ev.metadata, null, 2) : null;
  return (
    <>
      <View style={styles.logBadges}>
        <Badge text={ev.severity || '-'} color={severityColor(ev.severity)} styles={styles} />
        <Badge text={ev.outcome || '-'} color={outcomeColor(ev.outcome)} styles={styles} />
        <Badge text={sourceLabel(ev.source)} color="#3498db" styles={styles} />
        {ev.is_security ? <Badge text="🛡️ security" color="#e74c3c" styles={styles} /> : null}
      </View>
      <DetailRow label={t('system_admin.mon_detail_desc')} value={ev.description || ev.event_name || '-'} styles={styles} tc={tc} />
      <DetailRow label={t('system_admin.mon_col_event')} value={ev.event_name || '-'} styles={styles} tc={tc} />
      <DetailRow label={t('system_admin.mon_detail_actor')} value={user} styles={styles} tc={tc} />
      <DetailRow label={t('system_admin.mon_detail_time')} value={formatEventTime(ev.created_at)} styles={styles} tc={tc} />
      <DetailRow
        label={t('system_admin.mon_detail_module')}
        value={[ev.page, ev.route, ev.element].filter(Boolean).join(' · ') || '-'}
        styles={styles}
        tc={tc}
      />
      <DetailRow label={t('system_admin.mon_detail_device')} value={deviceText(ev.device)} styles={styles} tc={tc} />
      <DetailRow
        label={t('system_admin.mon_detail_ip')}
        value={`${ev.ip_address || '-'}${ev.metadata && ev.metadata.forwarded_for_untrusted ? ` ${t('system_admin.mon_detail_ip_note')}` : ''}`}
        styles={styles}
        tc={tc}
      />
      <DetailRow label={t('system_admin.mon_detail_outcome')} value={ev.outcome || '-'} styles={styles} tc={tc} />
      <DetailRow
        label={t('system_admin.mon_detail_severity')}
        value={`${ev.severity || '-'}${ev.severity_reason ? ` · ${ev.severity_reason}` : ''}`}
        styles={styles}
        tc={tc}
      />
      {ev.entity_type ? (
        <DetailRow
          label={t('system_admin.mon_detail_entity')}
          value={`${ev.entity_type}${ev.entity_id ? ` · ${ev.entity_id}` : ''}`}
          styles={styles}
          tc={tc}
        />
      ) : null}
      {ev.correlation_id ? (
        <DetailRow label={t('system_admin.mon_correlation')} value={ev.correlation_id} styles={styles} tc={tc} />
      ) : null}
      {ev.session_ref ? (
        <TouchableOpacity
          onPress={() => onSession(ev.session_ref)}
          style={[styles.outlineChip, { borderColor: tc.line2, marginTop: 8, alignSelf: 'flex-start' }]}
        >
          <Text style={[styles.outlineChipText, { color: tc.primary }]}>
            {t('system_admin.mon_open_session')} {String(ev.session_ref).slice(0, 10)}…
          </Text>
        </TouchableOpacity>
      ) : null}
      {metaJson ? (
        <View style={styles.metaBox}>
          <Text style={[styles.filterGroupLabel, { color: tc.muted }]}>
            {t('system_admin.mon_detail_meta')}
          </Text>
          <Text style={[styles.metaText, { color: tc.ink }]}>{metaJson}</Text>
        </View>
      ) : null}

      <Text style={[styles.subHeading, { color: tc.ink }]}>
        {t('system_admin.mon_detail_related')}
      </Text>
      {related.length > 0 ? (
        related.map((r: any) => (
          <TouchableOpacity
            key={String(r.id)}
            onPress={() => onEvent(r.id)}
            style={[styles.relatedCard, { borderColor: tc.line2 }]}
          >
            <View style={styles.logTop}>
              <Text style={[styles.relatedName, { color: tc.ink }]}>{r.event_name || ''}</Text>
              <Text style={[styles.logMetaSmall, { color: tc.muted2 }]}>
                {formatEventTime(r.created_at)}
              </Text>
            </View>
            <Text style={[styles.relatedDesc, { color: tc.muted }]}>
              {r.description || ''}
            </Text>
          </TouchableOpacity>
        ))
      ) : (
        <Text style={[styles.emptyText, { color: tc.muted2 }]}>
          {t('system_admin.mon_related_empty')}
        </Text>
      )}
    </>
  );
}

function SessionDetail({
  data,
  onEvent,
  onInvestigate,
  t,
  styles,
  tc,
  formatEventTime,
  formatDuration,
  deviceText,
}: any) {
  const s = data?.session || {};
  const events = data?.events || [];
  const user = s.user
    ? (s.user.full_name || s.user.email) + (s.user.role ? ` · ${s.user.role}` : '')
    : t('system_admin.mon_guest');
  return (
    <>
      <DetailRow label={t('system_admin.mon_detail_actor')} value={user} styles={styles} tc={tc} />
      <DetailRow label={t('system_admin.mon_session_started')} value={formatEventTime(s.started_at)} styles={styles} tc={tc} />
      <DetailRow label={t('system_admin.mon_session_last_seen')} value={formatEventTime(s.last_seen_at)} styles={styles} tc={tc} />
      <DetailRow label={t('system_admin.mon_session_duration')} value={formatDuration(s.duration_seconds)} styles={styles} tc={tc} />
      <DetailRow
        label={t('system_admin.mon_session_status')}
        value={`${sessionStatusLabel(s.status, t)}${s.end_reason ? ` · ${s.end_reason}` : ''}`}
        styles={styles}
        tc={tc}
      />
      <DetailRow label={t('system_admin.mon_detail_ip')} value={s.ip_address || '-'} styles={styles} tc={tc} />
      <DetailRow label={t('system_admin.mon_detail_device')} value={deviceText(s.device)} styles={styles} tc={tc} />
      <Text style={[styles.legacyNote, { color: tc.muted2 }]}>
        {t('system_admin.mon_session_client_note')}
      </Text>

      <Text style={[styles.subHeading, { color: tc.ink }]}>
        {t('system_admin.mon_session_events')}
      </Text>
      {events.length > 0 ? (
        events.map((ev: any) => (
          <TouchableOpacity
            key={String(ev.id)}
            onPress={() => onEvent(ev.id)}
            style={[styles.relatedCard, { borderColor: tc.line2 }]}
          >
            <View style={styles.logTop}>
              <Text style={[styles.relatedName, { color: tc.ink }]}>{ev.event_name || ''}</Text>
              <Text style={[styles.logMetaSmall, { color: tc.muted2 }]}>
                {formatEventTime(ev.created_at)}
              </Text>
            </View>
            <Text style={[styles.relatedDesc, { color: tc.muted }]}>
              {ev.description || ''}
            </Text>
          </TouchableOpacity>
        ))
      ) : (
        <Text style={[styles.emptyText, { color: tc.muted2 }]}>
          {t('system_admin.mon_session_no_events')}
        </Text>
      )}

      {s.user_id ? (
        <TouchableOpacity
          onPress={() => onInvestigate(String(s.user_id))}
          style={[styles.outlineChip, { borderColor: tc.primary, marginTop: 12, alignSelf: 'flex-start' }]}
        >
          <Text style={[styles.outlineChipText, { color: tc.primary }]}>
            {t('system_admin.mon_investigate')}
          </Text>
        </TouchableOpacity>
      ) : null}
    </>
  );
}

function sessionStatusLabel(status: string, t: any) {
  return (
    {
      active: t('system_admin.mon_session_status_active'),
      ended: t('system_admin.mon_session_status_ended'),
      expired: t('system_admin.mon_session_status_expired'),
    }[status] || status || ''
  );
}

function AlertDetail({
  alert,
  note,
  resolution,
  setNote,
  setResolution,
  onStatus,
  onInvestigate,
  t,
  styles,
  tc,
  formatEventTime,
}: any) {
  const notes = Array.isArray(alert.notes) ? alert.notes : [];
  const contextJson = alert.context ? JSON.stringify(alert.context, null, 2) : null;
  const color =
    { open: '#e74c3c', acknowledged: '#f39c12', resolved: '#27ae60' }[
      alert.status as string
    ] || '#95a5a6';
  const actions: [string, string][] = [];
  if (alert.status !== 'acknowledged') {
    actions.push(['acknowledged', t('system_admin.mon_alert_ack')]);
  }
  if (alert.status !== 'resolved') {
    actions.push(['resolved', t('system_admin.mon_alert_resolve')]);
  }
  if (alert.status === 'resolved') {
    actions.push(['open', t('system_admin.mon_alert_reopen')]);
  }
  return (
    <>
      <View style={styles.logBadges}>
        <Badge text={alert.severity || '-'} color={severityColor(alert.severity)} styles={styles} />
        <Badge text={alert.status || '-'} color={color} styles={styles} filled />
        <Badge
          text={t('system_admin.mon_alert_count', { count: alert.occurrence_count })}
          color="#64748b"
          styles={styles}
        />
      </View>
      <Text style={[styles.alertSummary, { color: tc.ink }]}>{alert.summary || ''}</Text>
      <DetailRow label={t('system_admin.mon_alert_rule')} value={alert.rule || '-'} styles={styles} tc={tc} />
      <DetailRow
        label={t('system_admin.mon_alert_subject')}
        value={
          alert.subject_user
            ? alert.subject_user.full_name || alert.subject_user.email
            : String(alert.subject || '').replace(/^(user|acct|ip):/, '')
        }
        styles={styles}
        tc={tc}
      />
      <DetailRow
        label={`${t('system_admin.mon_alert_first')} → ${t('system_admin.mon_alert_last')}`}
        value={`${formatEventTime(alert.first_seen)} → ${formatEventTime(alert.last_seen)}`}
        styles={styles}
        tc={tc}
      />
      {alert.resolution ? (
        <DetailRow label={t('system_admin.mon_alert_resolution')} value={alert.resolution} styles={styles} tc={tc} />
      ) : null}
      {contextJson ? (
        <View style={styles.metaBox}>
          <Text style={[styles.filterGroupLabel, { color: tc.muted }]}>
            {t('system_admin.mon_alert_context')}
          </Text>
          <Text style={[styles.metaText, { color: tc.ink }]}>{contextJson}</Text>
        </View>
      ) : null}

      <Text style={[styles.subHeading, { color: tc.ink }]}>
        {t('system_admin.mon_alert_notes')}
      </Text>
      {notes.length > 0 ? (
        notes.map((n: any, idx: number) => (
          <View key={idx} style={[styles.noteItem, { borderLeftColor: tc.primary }]}>
            <Text style={[styles.noteMeta, { color: tc.ink }]}>
              {formatEventTime(n.at)} · {n.action || ''}
            </Text>
            <Text style={[styles.relatedDesc, { color: tc.muted }]}>{n.text || ''}</Text>
          </View>
        ))
      ) : (
        <Text style={[styles.emptyText, { color: tc.muted2 }]}>
          {t('system_admin.mon_alert_no_notes')}
        </Text>
      )}

      <TextInput
        value={note}
        onChangeText={setNote}
        placeholder={t('system_admin.mon_alert_note')}
        placeholderTextColor={tc.muted2}
        multiline
        style={[styles.textArea, { borderColor: tc.line2, color: tc.ink, backgroundColor: tc.page }]}
      />
      <TextInput
        value={resolution}
        onChangeText={setResolution}
        placeholder={t('system_admin.mon_alert_resolution')}
        placeholderTextColor={tc.muted2}
        multiline
        style={[styles.textArea, { borderColor: tc.line2, color: tc.ink, backgroundColor: tc.page }]}
      />

      <View style={styles.filterActions}>
        {actions.map(([status, label]) => (
          <TouchableOpacity
            key={status}
            onPress={() => onStatus(alert.id, status)}
            style={[styles.applyBtn, { backgroundColor: tc.primary }]}
          >
            <Text style={styles.applyText}>{label}</Text>
          </TouchableOpacity>
        ))}
        <TouchableOpacity
          onPress={() => onStatus(alert.id, alert.status)}
          style={[styles.applyBtn, { backgroundColor: tc.success }]}
        >
          <Text style={styles.applyText}>{t('system_admin.mon_alert_save')}</Text>
        </TouchableOpacity>
      </View>

      <TouchableOpacity
        onPress={onInvestigate}
        style={[styles.outlineChip, { borderColor: tc.primary, marginTop: 12, alignSelf: 'flex-start' }]}
      >
        <Text style={[styles.outlineChipText, { color: tc.primary }]}>
          {t('system_admin.mon_open_full_timeline')}
        </Text>
      </TouchableOpacity>
    </>
  );
}

const makeStyles = (tc: ThemeColors) =>
  StyleSheet.create({
    container: { flex: 1 },
    content: { padding: 16, paddingBottom: 40, gap: 14 },
    loadingContainer: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 20 },
    loadingText: { marginTop: 12, fontSize: 14, fontWeight: '600' },
    pageHead: { flexDirection: 'row', alignItems: 'center', gap: 12 },
    pageTitle: { fontSize: 22, fontWeight: '800' },
    pageSub: { fontSize: 12.5, marginTop: 3 },
    refreshBtn: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 6,
      borderWidth: 1,
      borderRadius: 10,
      paddingHorizontal: 12,
      paddingVertical: 8,
    },
    refreshText: { fontSize: 12.5, fontWeight: '700' },
    sectionCard: { borderRadius: 18, padding: 16 },
    sectionHeader: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: 10,
      marginBottom: 12,
    },
    sectionTitle: { fontSize: 14.5, fontWeight: '800' },
    outlineChip: {
      borderWidth: 1,
      borderRadius: 10,
      paddingHorizontal: 10,
      paddingVertical: 6,
    },
    outlineChipText: { fontSize: 11.5, fontWeight: '700' },
    viewChips: { gap: 8, paddingVertical: 2 },
    chip: {
      borderWidth: 1,
      borderRadius: 999,
      paddingHorizontal: 12,
      paddingVertical: 6,
    },
    chipText: { fontSize: 12, fontWeight: '700' },
    setupBanner: {
      backgroundColor: '#fff7e6',
      borderWidth: 1,
      borderColor: '#f39c12',
      borderRadius: 12,
      padding: 12,
      marginTop: 10,
      marginBottom: 6,
      gap: 10,
    },
    setupBannerText: { fontSize: 12.5, color: '#7d5a17' },
    setupBtn: {
      backgroundColor: '#f39c12',
      borderRadius: 10,
      paddingVertical: 8,
      alignItems: 'center',
    },
    setupBtnText: { color: '#fff', fontWeight: '800', fontSize: 12.5 },
    statsGrid: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      justifyContent: 'space-between',
      marginTop: 10,
      marginBottom: 6,
      gap: 10,
    },
    statCard: {
      width: '31%',
      backgroundColor: tc.page,
      borderRadius: 12,
      paddingVertical: 10,
      paddingHorizontal: 10,
      borderLeftWidth: 3,
    },
    statValue: { fontSize: 18, fontWeight: '800' },
    statLabel: { fontSize: 10.5, marginTop: 2 },
    filterBar: { gap: 10, marginTop: 10, marginBottom: 6 },
    searchRow: { flexDirection: 'row', gap: 8, alignItems: 'center' },
    searchBox: {
      flex: 1,
      flexDirection: 'row',
      alignItems: 'center',
      gap: 8,
      borderWidth: 1,
      borderRadius: 10,
      paddingHorizontal: 12,
    },
    searchInput: { flex: 1, paddingVertical: 9, fontSize: 13 },
    filterBtn: {
      width: 40,
      height: 40,
      borderRadius: 10,
      borderWidth: 1,
      alignItems: 'center',
      justifyContent: 'center',
    },
    filterCount: {
      position: 'absolute',
      top: -5,
      right: -5,
      minWidth: 17,
      height: 17,
      borderRadius: 9,
      alignItems: 'center',
      justifyContent: 'center',
      paddingHorizontal: 4,
    },
    filterCountText: { color: '#fff', fontSize: 9.5, fontWeight: '800' },
    logCard: {
      borderWidth: 1,
      borderLeftWidth: 4,
      borderRadius: 12,
      padding: 13,
      marginTop: 10,
      backgroundColor: tc.card,
      gap: 5,
    },
    logTop: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'flex-start',
      gap: 8,
    },
    logAction: { flexShrink: 1, fontSize: 13, fontWeight: '700' },
    logTime: { fontSize: 10.5 },
    logDesc: { fontSize: 12.5, lineHeight: 18 },
    logBottom: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'center',
      gap: 8,
      flexWrap: 'wrap',
      marginTop: 2,
    },
    logBadges: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 6,
      flexWrap: 'wrap',
    },
    logMeta: { fontSize: 10.5 },
    logMetaSmall: { fontSize: 9.5 },
    logEndpoint: { fontSize: 11, marginTop: 2 },
    pill: {
      borderWidth: 1,
      borderRadius: 999,
      paddingHorizontal: 8,
      paddingVertical: 2,
      alignSelf: 'flex-start',
    },
    pillText: { fontSize: 9.5, fontWeight: '700' },
    pager: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'center',
      gap: 10,
      marginTop: 14,
      flexWrap: 'wrap',
    },
    pagerInfo: { fontSize: 11.5, fontWeight: '600' },
    pagerControls: { flexDirection: 'row', alignItems: 'center', gap: 8 },
    pagerBtn: {
      width: 32,
      height: 32,
      borderRadius: 8,
      borderWidth: 1,
      alignItems: 'center',
      justifyContent: 'center',
    },
    emptyState: { paddingVertical: 30, alignItems: 'center', gap: 8 },
    emptyText: { fontSize: 12.5, textAlign: 'center' },
    legacyNote: { fontSize: 11.5, marginTop: 10, marginBottom: 6 },
    modalOverlay: {
      flex: 1,
      backgroundColor: 'rgba(15,23,42,0.55)',
      justifyContent: 'flex-end',
    },
    modalContent: { maxHeight: '90%', borderTopLeftRadius: 20, borderTopRightRadius: 20 },
    modalHeader: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      padding: 16,
      borderBottomWidth: 1,
    },
    modalTitle: { fontSize: 15, fontWeight: '800' },
    modalBody: { padding: 18, paddingBottom: 34 },
    detailLoading: { paddingVertical: 40, alignItems: 'center' },
    filterGroup: { marginBottom: 14 },
    filterGroupLabel: {
      fontSize: 11,
      fontWeight: '800',
      textTransform: 'uppercase',
      letterSpacing: 0.4,
      marginBottom: 6,
    },
    securityToggle: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 8,
      borderWidth: 1,
      borderRadius: 10,
      padding: 12,
      marginTop: 4,
    },
    securityText: { fontSize: 13, fontWeight: '600' },
    filterActions: {
      flexDirection: 'row',
      gap: 8,
      flexWrap: 'wrap',
      marginTop: 16,
    },
    applyBtn: {
      flexGrow: 1,
      flexBasis: '40%',
      borderRadius: 10,
      paddingVertical: 11,
      alignItems: 'center',
      paddingHorizontal: 12,
    },
    applyText: { color: '#fff', fontSize: 12.5, fontWeight: '800' },
    resetBtn: {
      flexGrow: 1,
      flexBasis: '30%',
      borderWidth: 1,
      borderRadius: 10,
      paddingVertical: 11,
      alignItems: 'center',
      paddingHorizontal: 12,
    },
    resetText: { fontSize: 12.5, fontWeight: '700' },
    detailRow: {
      flexDirection: 'row',
      gap: 10,
      paddingVertical: 10,
      borderBottomWidth: 1,
    },
    detailLabel: { width: 118, fontSize: 12 },
    detailValue: { flex: 1, fontSize: 12.5, fontWeight: '500' },
    metaBox: { marginTop: 10 },
    metaText: {
      fontFamily: 'monospace',
      fontSize: 10.5,
      backgroundColor: tc.page,
      borderRadius: 8,
      padding: 8,
      marginTop: 4,
    },
    subHeading: { fontSize: 13, fontWeight: '800', marginTop: 16, marginBottom: 8 },
    relatedCard: {
      borderWidth: 1,
      borderRadius: 10,
      padding: 10,
      marginBottom: 8,
      gap: 3,
    },
    relatedName: { fontSize: 12, fontWeight: '700', flexShrink: 1 },
    relatedDesc: { fontSize: 11.5, lineHeight: 16 },
    noteItem: {
      borderLeftWidth: 3,
      paddingLeft: 10,
      paddingVertical: 6,
      marginBottom: 6,
      backgroundColor: tc.page,
      borderTopRightRadius: 8,
      borderBottomRightRadius: 8,
    },
    noteMeta: { fontSize: 11, fontWeight: '700', marginBottom: 2 },
    alertSummary: { fontSize: 14, fontWeight: '600', marginTop: 12, marginBottom: 6 },
    textArea: {
      borderWidth: 1,
      borderRadius: 10,
      padding: 10,
      fontSize: 13,
      minHeight: 54,
      textAlignVertical: 'top',
      marginTop: 10,
    },
  });
