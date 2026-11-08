// Duka_mkononi/app/system_admin/inquiries.tsx
//
// Mobile twin of resources/views/system_admin/inquiries.blade.php.
//
// Status filter + search + paged list of every customer inquiry. Tapping a row
// opens the detail sheet (customer contact, message), the AI triage panel
// (Capability #18: the server classifies the inquiry and drafts a reply from
// approved help content; the operator edits and approves before sending) and
// the publish-as-review controls. Nothing on this screen decides a severity or
// invents an answer, and a sent reply is never presented as a resolved issue.
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useRouter } from 'expo-router';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import {
  Alert,
  Modal,
  RefreshControl,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import SmartCartLoader from '../../components/SmartCartLoader';
import { API_BASE_URL } from '../../constants/api';
import { isSystemAdminRole } from '../../constants/session';
import { LOCALE_TAGS, useLang } from '../../context/LanguageContext';
import { useSession } from '../../context/SessionContext';
import { getCache, setCache } from '../../db/cache';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';
import { useTheme, ThemeColors } from '../../lib/theme';

const CACHE_LIST = 'system_admin:inquiries:list';
const CACHE_QUEUE = 'system_admin:inquiries:queue';

interface InquiryReview {
  display_name?: string | null;
  title?: string | null;
  body?: string | null;
  rating?: number | null;
  is_published?: boolean;
  published_at?: string | null;
}

interface Inquiry {
  id: string;
  email?: string | null;
  phone?: string | null;
  subject?: string | null;
  message?: string | null;
  status?: string | null;
  source?: string | null;
  locale?: string | null;
  created_at?: string | null;
  reviewed_at?: string | null;
  published_at?: string | null;
  is_published?: boolean;
  review?: InquiryReview | null;
}

interface QueueCounts {
  total: number;
  needs_human: number;
  has_approved_help: number;
}

interface TriageDetail {
  notice?: string;
  persisted?: boolean;
  auto_sent?: boolean;
  draft_locale?: string;
  triage: {
    category_label?: string;
    urgency?: string;
    urgency_label?: string;
    route_label?: string;
    needs_human?: boolean;
    needs_human_reasons?: string[];
    urgency_reasons?: string[];
    urgency_evidence?: string[];
    matched_topics?: { id: string; category: string }[];
  };
  draft: { subject?: string; body?: string; uses_approved_help?: boolean };
  approval: { required?: boolean; may_auto_send?: boolean; auto_send_policy?: boolean };
  sources?: string[];
  limitations?: string[];
}

const STATUS_STYLE: Record<string, { bg: string; fg: string }> = {
  new: { bg: '#fffbeb', fg: '#b45309' },
  reviewed: { bg: '#eff6ff', fg: '#1d4ed8' },
  published: { bg: '#ecfdf5', fg: '#047857' },
  archived: { bg: '#f1f5f9', fg: '#64748b' },
};

const URGENCY_COLOR: Record<string, string> = {
  critical: '#dc2626',
  high: '#d97706',
  normal: '#2563eb',
  low: '#64748b',
};

export default function SystemAdminInquiries() {
  const { colors: tc } = useTheme();
  const styles = useMemo(() => makeStyles(tc), [tc]);
  const router = useRouter();
  const { t, lang } = useLang();
  const { signOut } = useSession();

  const [inquiries, setInquiries] = useState<Inquiry[]>([]);
  const [counts, setCounts] = useState<QueueCounts | null>(null);
  const [autoSendPolicy, setAutoSendPolicy] = useState(false);
  const [statusFilter, setStatusFilter] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [listFailed, setListFailed] = useState(false);

  const [selected, setSelected] = useState<Inquiry | null>(null);
  const [detail, setDetail] = useState<Inquiry | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailFailed, setDetailFailed] = useState(false);
  const [publishName, setPublishName] = useState('');
  const [publishRating, setPublishRating] = useState('');
  const [acting, setActing] = useState<string | null>(null);

  const [triage, setTriage] = useState<TriageDetail | null>(null);
  const [triaging, setTriaging] = useState(false);
  const [sending, setSending] = useState(false);
  const [replySubject, setReplySubject] = useState('');
  const [replyBody, setReplyBody] = useState('');
  const [approved, setApproved] = useState(false);

  const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    init();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [lang]);

  useEffect(
    () => () => {
      if (searchTimer.current) clearTimeout(searchTimer.current);
    },
    [],
  );

  const init = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      const userDataStr = await AsyncStorage.getItem('userData');
      if (!token || !userDataStr) {
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
      await Promise.all([loadList({ reset: true }), loadStats()]);
    } catch (error) {
      console.error('Error initializing inquiries page:', error);
      setLoading(false);
    }
  };

  const loadList = async (opts: {
    reset?: boolean;
    pageNum?: number;
    statusValue?: string;
    searchValue?: string;
  } = {}) => {
    const reset = opts.reset !== false;
    const statusValue = opts.statusValue ?? statusFilter;
    const searchValue = opts.searchValue ?? search;
    const pageNum = opts.pageNum ?? (reset ? 1 : page + 1);
    if (reset) {
      setLoading(true);
    } else {
      setLoadingMore(true);
    }

    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;
      const qs =
        `?page=${pageNum}` +
        (statusValue ? `&status=${encodeURIComponent(statusValue)}` : '') +
        (searchValue ? `&search=${encodeURIComponent(searchValue)}` : '');
      const res = await fetchWithTimeout(`${API_BASE_URL}/api/system-admin/inquiries${qs}`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data?.error || `HTTP ${res.status}`);

      const rows: Inquiry[] = Array.isArray(data.data) ? data.data : [];
      const meta = data.meta || {};
      setInquiries((prev) => (reset ? rows : [...prev, ...rows]));
      setPage(Number(meta.page) || pageNum);
      setLastPage(Number(meta.last_page) || 1);
      setTotal(Number(meta.total) || (reset ? rows.length : inquiries.length + rows.length));
      setListFailed(false);
      if (reset) setCache(CACHE_LIST, { data: rows, meta }).catch(() => {});
    } catch {
      if (reset) {
        const cached = await getCache<{ data: Inquiry[]; meta?: any }>(CACHE_LIST);
        if (cached && Array.isArray(cached.data)) {
          setInquiries(cached.data);
          setPage(Number(cached.meta?.page) || 1);
          setLastPage(Number(cached.meta?.last_page) || 1);
          setTotal(Number(cached.meta?.total) || cached.data.length);
        } else {
          setListFailed(true);
        }
      }
    } finally {
      setLoading(false);
      setLoadingMore(false);
      setRefreshing(false);
    }
  };

  const loadStats = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;
      const res = await fetchWithTimeout(
        `${API_BASE_URL}/api/ai/inquiries/queue?locale=${encodeURIComponent(lang)}`,
        { headers: { Authorization: `Bearer ${token}` } },
      );
      if (!res.ok) return;
      const data = await res.json();
      setCounts(data.counts || null);
      setAutoSendPolicy(!!data.auto_send_policy);
      setCache(CACHE_QUEUE, {
        counts: data.counts,
        auto_send_policy: data.auto_send_policy,
      }).catch(() => {});
    } catch {
      const cached = await getCache<{ counts?: QueueCounts; auto_send_policy?: boolean }>(CACHE_QUEUE);
      if (cached) {
        setCounts(cached.counts || null);
        setAutoSendPolicy(!!cached.auto_send_policy);
      }
    }
  };

  const refreshAll = () => {
    setRefreshing(true);
    Promise.all([loadList({ reset: true }), loadStats()]);
  };

  const selectStatus = (value: string) => {
    if (statusFilter === value) return;
    setStatusFilter(value);
    loadList({ reset: true, statusValue: value, searchValue: search });
  };

  const onSearchChange = (value: string) => {
    setSearch(value);
    if (searchTimer.current) clearTimeout(searchTimer.current);
    searchTimer.current = setTimeout(() => {
      loadList({ reset: true, statusValue: statusFilter, searchValue: value });
    }, 400);
  };

  const loadMore = () => {
    if (loadingMore || page >= lastPage) return;
    loadList({ reset: false, pageNum: page + 1 });
  };

  const openDetail = async (inquiry: Inquiry) => {
    setSelected(inquiry);
    setDetail(null);
    setDetailFailed(false);
    setDetailLoading(true);
    setPublishName(inquiry.review?.display_name || '');
    setPublishRating(inquiry.review?.rating != null ? String(inquiry.review.rating) : '');
    setTriage(null);
    setReplySubject('');
    setReplyBody('');
    setApproved(false);

    try {
      const token = await AsyncStorage.getItem('userToken');
      const res = await fetchWithTimeout(
        `${API_BASE_URL}/api/system-admin/inquiries/${encodeURIComponent(inquiry.id)}`,
        { headers: { Authorization: `Bearer ${token}` } },
      );
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data?.inquiry) throw new Error(data?.error || 'detail failed');
      setDetail(data.inquiry);
      setPublishName(data.inquiry.review?.display_name || '');
      setPublishRating(
        data.inquiry.review?.rating != null ? String(data.inquiry.review.rating) : '',
      );
    } catch {
      setDetailFailed(true);
    } finally {
      setDetailLoading(false);
    }
  };

  const act = async (kind: 'reviewed' | 'publish' | 'unpublish') => {
    const target = detail || selected;
    if (!target || acting) return;
    if (!(await requireNetwork())) return;
    setActing(kind);
    try {
      const token = await AsyncStorage.getItem('userToken');
      const base = `${API_BASE_URL}/api/system-admin/inquiries/${encodeURIComponent(target.id)}`;
      const request =
        kind === 'reviewed'
          ? { url: `${base}/reviewed`, method: 'PUT', body: {} }
          : kind === 'unpublish'
            ? { url: `${base}/unpublish`, method: 'POST', body: {} }
            : {
                url: `${base}/publish`,
                method: 'POST',
                body: { display_name: publishName, rating: publishRating },
              };

      const res = await fetchWithTimeout(request.url, {
        method: request.method,
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(request.body),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        Alert.alert(t('app.error'), data?.error || t('system_admin.inq_err_generic'));
        return;
      }

      const msg =
        kind === 'publish'
          ? t('system_admin.inq_msg_published')
          : kind === 'unpublish'
            ? t('system_admin.inq_msg_unpublished')
            : t('system_admin.inq_msg_reviewed');
      Alert.alert(msg);

      setDetail((prev) => {
        const current = prev || target;
        if (kind === 'reviewed') return { ...current, status: 'reviewed' };
        if (kind === 'unpublish') {
          return {
            ...current,
            status: 'reviewed',
            is_published: false,
            review: current.review
              ? { ...current.review, is_published: false }
              : current.review,
          };
        }
        return {
          ...current,
          status: 'published',
          is_published: true,
          review: {
            ...(current.review || {}),
            display_name: publishName || null,
            rating: publishRating ? Number(publishRating) : null,
            is_published: true,
          },
        };
      });
      setSelected((prev) =>
        prev
          ? { ...prev, status: kind === 'unpublish' ? 'reviewed' : kind === 'publish' ? 'published' : 'reviewed' }
          : prev,
      );

      await Promise.all([
        loadList({ reset: true, statusValue: statusFilter, searchValue: search }),
        loadStats(),
      ]);
    } catch {
      Alert.alert(t('app.error'), t('system_admin.err_network'));
    } finally {
      setActing(null);
    }
  };

  const runTriage = async () => {
    const target = detail || selected;
    if (!target || triaging) return;
    if (!(await requireNetwork())) return;
    setTriaging(true);
    setTriage(null);
    try {
      const token = await AsyncStorage.getItem('userToken');
      const res = await fetchWithTimeout(
        `${API_BASE_URL}/api/ai/inquiries/${encodeURIComponent(target.id)}/triage`,
        {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ locale: lang }),
        },
      );
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        Alert.alert(t('app.error'), data?.error || t('inquiry_triage.error'));
        return;
      }
      setTriage(data);
      setReplySubject(data?.draft?.subject || '');
      setReplyBody(data?.draft?.body || '');
      setApproved(false);
    } catch {
      Alert.alert(t('app.error'), t('inquiry_triage.error'));
    } finally {
      setTriaging(false);
    }
  };

  const sendReply = async () => {
    const target = detail || selected;
    if (!target || !triage || sending) return;
    const needsApproval = triage.approval?.required !== false;
    if (needsApproval && !approved) {
      Alert.alert(t('app.error'), t('inquiry_triage.approve_required'));
      return;
    }
    if (!replyBody.trim()) {
      Alert.alert(t('app.error'), t('inquiry_triage.error'));
      return;
    }
    if (!(await requireNetwork())) return;

    setSending(true);
    try {
      const token = await AsyncStorage.getItem('userToken');
      const res = await fetchWithTimeout(
        `${API_BASE_URL}/api/ai/inquiries/${encodeURIComponent(target.id)}/reply`,
        {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            approved: true,
            subject: replySubject,
            body: replyBody,
            locale: lang,
          }),
        },
      );
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        Alert.alert(t('app.error'), data?.error || t('inquiry_triage.sent_fail'));
        return;
      }
      Alert.alert(t('inquiry_triage.sent_ok'), data?.notice || t('inquiry_triage.not_resolved'));
      setSelected(null);
      setDetail(null);
      setTriage(null);
      await Promise.all([loadList({ reset: true, statusValue: statusFilter, searchValue: search }), loadStats()]);
    } catch {
      Alert.alert(t('app.error'), t('inquiry_triage.sent_fail'));
    } finally {
      setSending(false);
    }
  };

  const formatDate = (value?: string | null) => {
    if (!value) return t('system_admin.not_set');
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return t('system_admin.not_set');
    return date.toLocaleString(LOCALE_TAGS[lang] || 'sw-TZ', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const statusBadge = (inquiry: Inquiry) => {
    const published = !!inquiry.is_published;
    const key = published ? 'published' : inquiry.status || 'archived';
    const style = STATUS_STYLE[key] || STATUS_STYLE.archived;
    const label = published
      ? t('system_admin.inq_published_badge')
      : inquiry.status || '-';
    return (
      <View style={[styles.badge, { backgroundColor: style.bg }]}>
        <Text style={[styles.badgeText, { color: style.fg }]}>{label}</Text>
      </View>
    );
  };

  const bullets = (title: string, rows?: string[]) => {
    if (!rows || rows.length === 0) return null;
    return (
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>{title}</Text>
        {rows.map((row, index) => (
          <Text key={`${title}-${index}`} style={styles.bullet}>
            {'• '}
            {row}
          </Text>
        ))}
      </View>
    );
  };

  const infoRow = (
    icon: keyof typeof Ionicons.glyphMap,
    label: string,
    value: string,
  ) => (
    <View style={[styles.detailRow, { borderBottomColor: tc.line }]}>
      <Ionicons name={icon} size={16} color={tc.muted} />
      <Text style={[styles.detailLabel, { color: tc.muted }]}>{label}</Text>
      <Text style={[styles.detailValue, { color: tc.ink }]}>{value}</Text>
    </View>
  );

  const renderTriage = () => {
    const tr = triage?.triage || {};
    const dr = triage?.draft || {};
    const ap = triage?.approval || {};
    return (
      <View>
        {triage?.notice ? <Text style={styles.infoNote}>{triage.notice}</Text> : null}
        {triage?.persisted === false ? (
          <Text style={styles.infoNote}>{t('inquiry_triage.persisted_no')}</Text>
        ) : null}

        <View style={styles.triGrid}>
          <View style={styles.triCell}>
            <Text style={styles.triLabel}>{t('inquiry_triage.label_category')}</Text>
            <Text style={styles.triValue}>{tr.category_label || '-'}</Text>
          </View>
          <View style={styles.triCell}>
            <Text style={styles.triLabel}>{t('inquiry_triage.label_urgency')}</Text>
            <View
              style={[
                styles.badge,
                { backgroundColor: URGENCY_COLOR[tr.urgency || ''] || '#64748b' },
              ]}
            >
              <Text style={styles.badgeTextWhite}>{tr.urgency_label || tr.urgency || '-'}</Text>
            </View>
          </View>
          <View style={styles.triCell}>
            <Text style={styles.triLabel}>{t('inquiry_triage.label_route')}</Text>
            <Text style={styles.triValue}>{tr.route_label || '-'}</Text>
          </View>
          <View style={styles.triCell}>
            <Text style={styles.triLabel}>{t('inquiry_triage.label_escalate')}</Text>
            <Text style={styles.triValue}>
              {tr.needs_human
                ? t('inquiry_triage.needs_human_yes')
                : t('inquiry_triage.needs_human_no')}
            </Text>
          </View>
        </View>

        {dr.uses_approved_help === false ? (
          <Text style={styles.errorNote}>{t('inquiry_triage.no_help')}</Text>
        ) : null}

        {bullets(t('inquiry_triage.reasons'), [
          ...(tr.needs_human_reasons || []),
          ...(tr.urgency_reasons || []),
        ])}
        {bullets(t('inquiry_triage.evidence'), tr.urgency_evidence)}
        {bullets(
          t('inquiry_triage.sources_title'),
          (tr.matched_topics || []).map((topic) => `${topic.id} (${topic.category})`),
        )}

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>{t('inquiry_triage.draft_title')}</Text>
          <Text style={styles.hint}>{t('inquiry_triage.draft_edited_note')}</Text>
          <Text style={styles.fieldLabel}>{t('inquiry_triage.draft_subject')}</Text>
          <TextInput
            style={[styles.input, { backgroundColor: tc.card, borderColor: tc.line2, color: tc.ink }]}
            value={replySubject}
            onChangeText={setReplySubject}
            placeholder={t('inquiry_triage.draft_subject')}
            placeholderTextColor={tc.muted2}
          />
          <Text style={styles.fieldLabel}>{t('inquiry_triage.draft_title')}</Text>
          <TextInput
            style={[
              styles.input,
              styles.textarea,
              { backgroundColor: tc.card, borderColor: tc.line2, color: tc.ink },
            ]}
            value={replyBody}
            onChangeText={setReplyBody}
            multiline
            textAlignVertical="top"
          />
        </View>

        {ap.required === false ? (
          <View>
            <Text style={styles.infoNote}>{t('inquiry_triage.auto_sent_note')}</Text>
            {triage?.auto_sent ? (
              <Text style={styles.okNote}>{t('inquiry_triage.sent_ok')}</Text>
            ) : null}
          </View>
        ) : (
          <View style={styles.section}>
            <View style={styles.approveRow}>
              <Switch value={approved} onValueChange={setApproved} />
              <Text style={[styles.approveText, { color: tc.ink }]}>
                {t('inquiry_triage.approve_confirm')}
              </Text>
            </View>
            <TouchableOpacity
              style={[styles.sendBtn, sending && styles.disabled]}
              disabled={sending}
              onPress={sendReply}
            >
              <Ionicons name="send" size={18} color="#fff" />
              <Text style={styles.sendBtnText}>
                {sending ? t('inquiry_triage.sending') : t('inquiry_triage.btn_reply')}
              </Text>
            </TouchableOpacity>
          </View>
        )}

        <Text style={styles.hint}>{t('inquiry_triage.not_resolved')}</Text>
        {bullets(t('inquiry_triage.sources_title'), triage?.sources)}
        {bullets(t('inquiry_triage.limitations_title'), triage?.limitations)}
      </View>
    );
  };

  const statusOptions = [
    { id: '', label: t('system_admin.inq_filter_all') },
    { id: 'new', label: t('system_admin.inq_filter_new') },
    { id: 'reviewed', label: t('system_admin.inq_filter_reviewed') },
    { id: 'published', label: t('system_admin.inq_filter_published') },
  ];

  const ratingOptions = ['', '5', '4', '3', '2', '1'];

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: tc.page }]}>
      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={refreshAll} />
        }
        showsVerticalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
      >
        <View style={styles.pageHead}>
          <View style={{ flex: 1 }}>
            <Text style={[styles.pageTitle, { color: tc.ink }]}>
              {t('system_admin.inq_title')}
            </Text>
            <Text style={[styles.pageSub, { color: tc.muted }]}>
              {t('system_admin.inq_subtitle')}
            </Text>
          </View>
          <TouchableOpacity
            onPress={refreshAll}
            style={[styles.refreshBtn, { borderColor: tc.line2, backgroundColor: tc.card }]}
          >
            <Ionicons name="refresh" size={15} color={tc.ink} />
            <Text style={[styles.refreshText, { color: tc.ink }]}>
              {t('system_admin.refresh')}
            </Text>
          </TouchableOpacity>
        </View>

        {counts ? (
          <View style={styles.statsRow}>
            <View style={[styles.stat, { backgroundColor: tc.card }]}>
              <Text style={[styles.statLabel, { color: tc.muted }]}>
                {t('inquiry_triage.stat_total')}
              </Text>
              <Text style={[styles.statValue, { color: tc.ink }]}>{counts.total}</Text>
            </View>
            <View style={[styles.stat, { backgroundColor: tc.card }]}>
              <Text style={[styles.statLabel, { color: tc.muted }]}>
                {t('inquiry_triage.stat_needs_human')}
              </Text>
              <Text style={[styles.statValue, { color: tc.ink }]}>{counts.needs_human}</Text>
            </View>
            <View style={[styles.stat, { backgroundColor: tc.card }]}>
              <Text style={[styles.statLabel, { color: tc.muted }]}>
                {t('inquiry_triage.stat_with_help')}
              </Text>
              <Text style={[styles.statValue, { color: tc.ink }]}>
                {counts.has_approved_help}
              </Text>
            </View>
          </View>
        ) : null}

        {autoSendPolicy ? (
          <Text style={styles.policyNote}>{t('inquiry_triage.auto_sent_note')}</Text>
        ) : null}

        <View style={[styles.searchBox, { backgroundColor: tc.card, borderColor: tc.line2 }]}>
          <Ionicons name="search" size={16} color={tc.muted2} />
          <TextInput
            value={search}
            onChangeText={onSearchChange}
            placeholder={t('system_admin.inq_search_placeholder')}
            placeholderTextColor={tc.muted2}
            style={[styles.searchInput, { color: tc.ink }]}
            autoCapitalize="none"
          />
          {search.length > 0 ? (
            <TouchableOpacity
              onPress={() => {
                setSearch('');
                loadList({ reset: true, statusValue: statusFilter, searchValue: '' });
              }}
            >
              <Ionicons name="close-circle" size={17} color={tc.muted2} />
            </TouchableOpacity>
          ) : null}
        </View>

        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chipRow}>
          {statusOptions.map((option) => {
            const active = statusFilter === option.id;
            return (
              <TouchableOpacity
                key={option.id || 'all'}
                onPress={() => selectStatus(option.id)}
                style={[
                  styles.chip,
                  {
                    backgroundColor: active ? tc.primary : tc.card,
                    borderColor: active ? tc.primary : tc.line2,
                  },
                ]}
              >
                <Text style={[styles.chipText, { color: active ? '#fff' : tc.muted }]}>
                  {option.label}
                </Text>
              </TouchableOpacity>
            );
          })}
        </ScrollView>

        <View style={[styles.listHead, { borderBottomColor: tc.line2 }]}>
          <Text style={[styles.listHeadText, { color: tc.muted }]}>
            {t('system_admin.inq_col_subject')}
          </Text>
          <Text style={[styles.listHeadText, { color: tc.muted }]}>
            {t('system_admin.inq_col_customer')}
          </Text>
          <Text style={[styles.listHeadText, { color: tc.muted }]}>
            {t('system_admin.inq_col_status')}
          </Text>
          <Text style={[styles.listHeadText, { textAlign: 'right', color: tc.muted }]}>
            {t('system_admin.inq_col_date')}
          </Text>
        </View>

        <View style={[styles.listCard, { backgroundColor: tc.card }]}>
          {loading && inquiries.length === 0 ? (
            <View style={styles.loadingBox}>
              <SmartCartLoader size="compact" color={tc.primary} />
            </View>
          ) : listFailed && inquiries.length === 0 ? (
            <View style={styles.emptyState}>
              <Ionicons name="cloud-offline-outline" size={36} color={tc.muted2} />
              <Text style={[styles.emptyText, { color: tc.muted }]}>
                {t('system_admin.inq_err_generic')}
              </Text>
              <TouchableOpacity
                style={[styles.retryBtn, { borderColor: tc.line2 }]}
                onPress={() => loadList({ reset: true })}
              >
                <Ionicons name="refresh" size={15} color={tc.primary} />
                <Text style={[styles.retryText, { color: tc.primary }]}>
                  {t('inquiry_triage.retry')}
                </Text>
              </TouchableOpacity>
            </View>
          ) : inquiries.length > 0 ? (
            inquiries.map((item) => (
              <View key={item.id} style={[styles.card, { borderBottomColor: tc.line }]}>
                <View style={styles.cardHead}>
                  <Text style={[styles.cardTitle, { color: tc.ink }]} numberOfLines={2}>
                    {item.subject || '-'}
                  </Text>
                  {statusBadge(item)}
                </View>
                <Text style={[styles.cardMeta, { color: tc.muted }]} numberOfLines={1}>
                  {item.email || '-'}
                </Text>
                {item.phone ? (
                  <Text style={[styles.cardMeta, { color: tc.muted2 }]} numberOfLines={1}>
                    {item.phone}
                  </Text>
                ) : null}
                <View style={styles.cardFoot}>
                  <Text style={[styles.cardDate, { color: tc.muted2 }]}>
                    {formatDate(item.created_at)}
                  </Text>
                  <TouchableOpacity
                    style={[styles.viewBtn, { borderColor: tc.line2 }]}
                    onPress={() => openDetail(item)}
                  >
                    <Text style={[styles.viewBtnText, { color: tc.primary }]}>
                      {t('system_admin.inq_view')}
                    </Text>
                  </TouchableOpacity>
                </View>
              </View>
            ))
          ) : (
            <View style={styles.emptyState}>
              <Ionicons name="mail-open-outline" size={36} color={tc.muted2} />
              <Text style={[styles.emptyText, { color: tc.muted2 }]}>
                {t('system_admin.inq_empty')}
              </Text>
            </View>
          )}
        </View>

        <View style={styles.footer}>
          {total > 0 ? (
            <Text style={[styles.footerText, { color: tc.muted2 }]}>
              {t('inquiry_triage.stat_total')}: {total}
            </Text>
          ) : null}
          {page < lastPage ? (
            <TouchableOpacity
              style={[styles.loadMoreBtn, { borderColor: tc.line2 }]}
              disabled={loadingMore}
              onPress={loadMore}
            >
              {loadingMore ? (
                <SmartCartLoader size="compact" color={tc.primary} />
              ) : (
                <Text style={[styles.loadMoreText, { color: tc.primary }]}>
                  {t('system_admin.inq_load_more')}
                </Text>
              )}
            </TouchableOpacity>
          ) : null}
        </View>
      </ScrollView>

      <Modal
        animationType="slide"
        transparent
        visible={!!selected}
        onRequestClose={() => setSelected(null)}
      >
        <View style={styles.modalOverlay}>
          <View style={[styles.modalContent, { backgroundColor: tc.card }]}>
            <View style={[styles.modalHeader, { borderBottomColor: tc.line2 }]}>
              <View style={{ flex: 1 }}>
                <Text style={[styles.modalTitle, { color: tc.ink }]} numberOfLines={2}>
                  {selected?.subject || t('system_admin.inq_detail_title')}
                </Text>
                <Text style={[styles.modalSub, { color: tc.muted }]}>
                  {t('system_admin.inq_detail_title')}
                </Text>
              </View>
              <TouchableOpacity onPress={() => setSelected(null)}>
                <Ionicons name="close" size={22} color={tc.muted} />
              </TouchableOpacity>
            </View>

            <ScrollView contentContainerStyle={styles.modalBody}>
              {detailLoading ? (
                <View style={styles.loadingBox}>
                  <SmartCartLoader size="compact" color={tc.primary} />
                </View>
              ) : detailFailed ? (
                <View style={styles.emptyState}>
                  <Ionicons name="alert-circle-outline" size={36} color={tc.danger} />
                  <Text style={[styles.emptyText, { color: tc.muted }]}>
                    {t('system_admin.inq_err_generic')}
                  </Text>
                  {selected ? (
                    <TouchableOpacity
                      style={[styles.retryBtn, { borderColor: tc.line2 }]}
                      onPress={() => openDetail(selected)}
                    >
                      <Ionicons name="refresh" size={15} color={tc.primary} />
                      <Text style={[styles.retryText, { color: tc.primary }]}>
                        {t('inquiry_triage.retry')}
                      </Text>
                    </TouchableOpacity>
                  ) : null}
                </View>
              ) : detail ? (
                <>
                  <View style={styles.detailBadgeRow}>{statusBadge(detail)}</View>
                  {infoRow('mail-outline', t('system_admin.inq_detail_email'), detail.email || '-')}
                  {infoRow('call-outline', t('system_admin.inq_detail_phone'), detail.phone || '-')}
                  {infoRow('calendar-outline', t('system_admin.inq_detail_date'), formatDate(detail.created_at))}

                  <View style={styles.section}>
                    <Text style={styles.sectionTitle}>{t('system_admin.inq_detail_message')}</Text>
                    <Text style={[styles.messageBox, { backgroundColor: tc.soft, color: tc.ink, borderColor: tc.line2 }]}>
                      {detail.message || '-'}
                    </Text>
                  </View>

                  {detail.is_published ? (
                    <Text style={styles.infoNote}>{t('system_admin.inq_published_hint')}</Text>
                  ) : null}

                  <View style={[styles.triageWrap, { borderTopColor: tc.line2 }]}>
                    <View style={styles.triageHead}>
                      <View style={styles.triageTitleRow}>
                        <Text style={styles.sectionTitle}>{t('inquiry_triage.title')}</Text>
                        <View style={styles.aiBadge}>
                          <Text style={styles.aiBadgeText}>{t('inquiry_triage.ai_badge')}</Text>
                        </View>
                      </View>
                      <TouchableOpacity
                        style={[styles.triageBtn, triaging && styles.disabled]}
                        disabled={triaging}
                        onPress={runTriage}
                      >
                        {triaging ? (
                          <SmartCartLoader size="compact" color="#fff" />
                        ) : (
                          <Text style={styles.triageBtnText}>{t('inquiry_triage.btn_triage')}</Text>
                        )}
                      </TouchableOpacity>
                    </View>
                    <Text style={styles.hint}>{t('inquiry_triage.subtitle')}</Text>
                    {triage ? renderTriage() : null}
                  </View>

                  <View style={[styles.publishSection, { borderTopColor: tc.line2 }]}>
                    <Text style={styles.sectionTitle}>{t('system_admin.inq_publish_title')}</Text>
                    <Text style={styles.hint}>{t('system_admin.inq_publish_subtitle')}</Text>

                    <Text style={styles.fieldLabel}>
                      {t('system_admin.inq_publish_display_name')}
                    </Text>
                    <TextInput
                      style={[styles.input, { backgroundColor: tc.card, borderColor: tc.line2, color: tc.ink }]}
                      value={publishName}
                      onChangeText={setPublishName}
                      placeholder={t('system_admin.without_name')}
                      placeholderTextColor={tc.muted2}
                    />

                    <Text style={styles.fieldLabel}>{t('system_admin.inq_publish_rating')}</Text>
                    <View style={styles.ratingRow}>
                      {ratingOptions.map((value) => {
                        const active = publishRating === value;
                        return (
                          <TouchableOpacity
                            key={value || 'none'}
                            onPress={() => setPublishRating(value)}
                            style={[
                              styles.ratingChip,
                              {
                                backgroundColor: active ? tc.primary : tc.card,
                                borderColor: active ? tc.primary : tc.line2,
                              },
                            ]}
                          >
                            <Text
                              style={[
                                styles.ratingChipText,
                                { color: active ? '#fff' : tc.muted },
                              ]}
                            >
                              {value || t('system_admin.inq_rating_none')}
                            </Text>
                          </TouchableOpacity>
                        );
                      })}
                    </View>

                    <View style={styles.actionRow}>
                      <TouchableOpacity
                        style={[styles.actionBtn, { borderColor: tc.line2 }, !!acting && styles.disabled]}
                        disabled={!!acting}
                        onPress={() => act('reviewed')}
                      >
                        {acting === 'reviewed' ? (
                          <SmartCartLoader size="compact" color={tc.ink} />
                        ) : (
                          <>
                            <Ionicons name="checkmark-circle-outline" size={16} color={tc.ink} />
                            <Text style={[styles.actionText, { color: tc.ink }]}>
                              {t('system_admin.inq_mark_reviewed')}
                            </Text>
                          </>
                        )}
                      </TouchableOpacity>
                      {detail.is_published ? (
                        <TouchableOpacity
                          style={[styles.actionBtn, styles.actionDanger, !!acting && styles.disabled]}
                          disabled={!!acting}
                          onPress={() => act('unpublish')}
                        >
                          {acting === 'unpublish' ? (
                            <SmartCartLoader size="compact" color="#dc2626" />
                          ) : (
                            <>
                              <Ionicons name="eye-off-outline" size={16} color="#dc2626" />
                              <Text style={[styles.actionText, { color: '#dc2626' }]}>
                                {t('system_admin.inq_unpublish')}
                              </Text>
                            </>
                          )}
                        </TouchableOpacity>
                      ) : (
                        <TouchableOpacity
                          style={[styles.actionBtn, styles.actionOk, !!acting && styles.disabled]}
                          disabled={!!acting}
                          onPress={() => act('publish')}
                        >
                          {acting === 'publish' ? (
                            <SmartCartLoader size="compact" color="#059669" />
                          ) : (
                            <>
                              <Ionicons name="megaphone-outline" size={16} color="#059669" />
                              <Text style={[styles.actionText, { color: '#059669' }]}>
                                {t('system_admin.inq_publish')}
                              </Text>
                            </>
                          )}
                        </TouchableOpacity>
                      )}
                    </View>
                  </View>
                </>
              ) : null}
            </ScrollView>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

const makeStyles = (tc: ThemeColors) =>
  StyleSheet.create({
    container: { flex: 1 },
    content: { padding: 16, paddingBottom: 40, gap: 12 },
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
    statsRow: { flexDirection: 'row', gap: 8 },
    stat: { flex: 1, borderRadius: 12, padding: 12, borderWidth: 1, borderColor: tc.line2 },
    statLabel: { fontSize: 10, fontWeight: '700', textTransform: 'uppercase', letterSpacing: 0.3 },
    statValue: { fontSize: 20, fontWeight: '800', marginTop: 4 },
    policyNote: {
      backgroundColor: '#eff6ff',
      color: '#1e40af',
      padding: 10,
      borderRadius: 10,
      fontSize: 12,
    },
    searchBox: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 8,
      borderWidth: 1,
      borderRadius: 10,
      paddingHorizontal: 12,
    },
    searchInput: { flex: 1, paddingVertical: 10, fontSize: 13.5 },
    chipRow: { gap: 8 },
    chip: { borderWidth: 1, borderRadius: 999, paddingHorizontal: 14, paddingVertical: 7 },
    chipText: { fontSize: 12.5, fontWeight: '700' },
    listHead: {
      flexDirection: 'row',
      gap: 8,
      paddingHorizontal: 14,
      paddingBottom: 6,
    },
    listHeadText: {
      flex: 1,
      fontSize: 10,
      fontWeight: '800',
      textTransform: 'uppercase',
      letterSpacing: 0.4,
    },
    listCard: { borderRadius: 16, overflow: 'hidden' },
    card: { padding: 14, gap: 4, borderBottomWidth: 1 },
    cardHead: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
    cardTitle: { flex: 1, fontSize: 14.5, fontWeight: '700' },
    cardMeta: { fontSize: 12 },
    cardFoot: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      marginTop: 6,
      gap: 8,
    },
    cardDate: { fontSize: 11.5, flex: 1 },
    viewBtn: { borderWidth: 1, borderRadius: 8, paddingHorizontal: 12, paddingVertical: 6 },
    viewBtnText: { fontSize: 12, fontWeight: '700' },
    badge: {
      borderRadius: 999,
      paddingHorizontal: 9,
      paddingVertical: 3,
      alignSelf: 'flex-start',
    },
    badgeText: { fontSize: 10.5, fontWeight: '700' },
    badgeTextWhite: { fontSize: 10.5, fontWeight: '700', color: '#fff' },
    loadingBox: { paddingVertical: 30, alignItems: 'center' },
    emptyState: { paddingVertical: 34, alignItems: 'center', gap: 8 },
    emptyText: { fontSize: 13, textAlign: 'center' },
    retryBtn: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 6,
      borderWidth: 1,
      borderRadius: 9,
      paddingHorizontal: 12,
      paddingVertical: 7,
    },
    retryText: { fontSize: 12.5, fontWeight: '700' },
    footer: { alignItems: 'center', gap: 8, marginTop: 2 },
    footerText: { fontSize: 11.5 },
    loadMoreBtn: {
      borderWidth: 1,
      borderRadius: 10,
      paddingHorizontal: 18,
      paddingVertical: 9,
      minWidth: 120,
      alignItems: 'center',
    },
    loadMoreText: { fontSize: 13, fontWeight: '700' },
    modalOverlay: {
      flex: 1,
      backgroundColor: 'rgba(15,23,42,0.55)',
      justifyContent: 'flex-end',
    },
    modalContent: { maxHeight: '90%', borderTopLeftRadius: 20, borderTopRightRadius: 20 },
    modalHeader: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 10,
      padding: 16,
      borderBottomWidth: 1,
    },
    modalTitle: { fontSize: 15.5, fontWeight: '800' },
    modalSub: { fontSize: 11.5, marginTop: 2 },
    modalBody: { padding: 18, paddingBottom: 36 },
    detailBadgeRow: { flexDirection: 'row', marginBottom: 8 },
    detailRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 10,
      paddingVertical: 11,
      borderBottomWidth: 1,
    },
    detailLabel: { width: 104, fontSize: 12.5 },
    detailValue: { flex: 1, fontSize: 13, fontWeight: '500' },
    section: { marginTop: 16, gap: 6 },
    sectionTitle: { fontSize: 13.5, fontWeight: '800', color: tc.ink },
    bullet: { fontSize: 12.5, color: tc.muted },
    messageBox: {
      borderRadius: 10,
      borderWidth: 1,
      padding: 12,
      fontSize: 13,
      lineHeight: 19,
    },
    hint: { fontSize: 12, color: tc.muted },
    infoNote: {
      backgroundColor: '#eff6ff',
      color: '#1e40af',
      padding: 10,
      borderRadius: 10,
      fontSize: 12,
      marginTop: 10,
    },
    okNote: {
      backgroundColor: '#ecfdf5',
      color: '#065f46',
      padding: 10,
      borderRadius: 10,
      fontSize: 12,
      marginTop: 10,
    },
    errorNote: {
      backgroundColor: '#fef2f2',
      color: '#991b1b',
      padding: 10,
      borderRadius: 10,
      fontSize: 12,
      marginTop: 10,
    },
    triageWrap: { marginTop: 18, paddingTop: 14, borderTopWidth: 1 },
    triageHead: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: 10,
      marginBottom: 4,
    },
    triageTitleRow: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 8 },
    aiBadge: {
      backgroundColor: tc.primary,
      borderRadius: 999,
      paddingHorizontal: 8,
      paddingVertical: 2,
    },
    aiBadgeText: { color: '#fff', fontSize: 10, fontWeight: '800' },
    triageBtn: {
      backgroundColor: tc.primary,
      borderRadius: 9,
      paddingHorizontal: 14,
      paddingVertical: 8,
    },
    triageBtnText: { color: '#fff', fontSize: 12.5, fontWeight: '700' },
    triGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 12 },
    triCell: {
      flexGrow: 1,
      flexBasis: '44%',
      backgroundColor: tc.soft,
      borderWidth: 1,
      borderColor: tc.line2,
      borderRadius: 10,
      padding: 10,
      gap: 4,
    },
    triLabel: { fontSize: 10.5, color: tc.muted, textTransform: 'uppercase' },
    triValue: { fontSize: 13, fontWeight: '700', color: tc.ink },
    fieldLabel: {
      fontSize: 12.5,
      fontWeight: '600',
      color: tc.ink,
      marginTop: 10,
      marginBottom: 4,
    },
    input: {
      borderWidth: 1,
      borderRadius: 10,
      paddingHorizontal: 12,
      paddingVertical: 10,
      fontSize: 14,
    },
    textarea: { minHeight: 160 },
    approveRow: { flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 12 },
    approveText: { flex: 1, fontSize: 12.5 },
    sendBtn: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 8,
      backgroundColor: '#059669',
      borderRadius: 12,
      paddingVertical: 13,
    },
    sendBtnText: { color: '#fff', fontWeight: '700', fontSize: 14.5 },
    disabled: { opacity: 0.6 },
    publishSection: { marginTop: 18, paddingTop: 14, borderTopWidth: 1 },
    ratingRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 4 },
    ratingChip: { borderWidth: 1, borderRadius: 999, paddingHorizontal: 14, paddingVertical: 7 },
    ratingChipText: { fontSize: 12.5, fontWeight: '700' },
    actionRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 16 },
    actionBtn: {
      flexGrow: 1,
      flexBasis: '45%',
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 6,
      borderWidth: 1,
      borderRadius: 10,
      paddingVertical: 11,
      paddingHorizontal: 10,
    },
    actionOk: { borderColor: '#bbf7d0', backgroundColor: '#f0fdf4' },
    actionDanger: { borderColor: '#fecaca', backgroundColor: '#fef2f2' },
    actionText: { fontSize: 12.5, fontWeight: '700' },
  });
