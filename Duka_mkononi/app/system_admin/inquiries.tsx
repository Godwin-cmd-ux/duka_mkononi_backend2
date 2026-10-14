// Duka_mkononi/app/system_admin/inquiries.tsx
// Capability #18 - inquiry triage & auto-draft replies (Super Admin).
//
// This screen only RENDERS what the API decided: the topic, the urgency, the
// team to route to, whether the inquiry needs a human, and the reply the server
// composed from approved help content. The operator may edit the reply and must
// approve it before it is sent; when the narrow auto-send policy is on and the
// server decided the inquiry is harmless, the response says the reply already
// went out. A sent reply is never presented as a resolved issue.
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useState } from 'react';
import {
    Alert,
    FlatList,
    Modal,
    SafeAreaView,
    ScrollView,
    StatusBar,
    StyleSheet,
    Switch,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from 'react-native';
import SmartCartLoader from '../../components/SmartCartLoader';
import { API_BASE_URL } from '../../constants/api';
import { getCache, setCache } from '../../db/cache';
import { useLang } from '../../context/LanguageContext';
import { useSession } from '../../context/SessionContext';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';

const CACHE_KEY = 'system_admin:inquiries:queue';

interface QueueItem {
  id: string;
  subject: string;
  email: string;
  status: string;
  created_at: string;
  category: string;
  category_label: string;
  urgency: string;
  urgency_label: string;
  route: string;
  route_label: string;
  escalate: boolean;
  needs_human: boolean;
  has_approved_help: boolean;
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

const URGENCY_COLOR: Record<string, string> = {
  critical: '#dc2626',
  high: '#d97706',
  normal: '#2563eb',
  low: '#64748b',
};

export default function SystemAdminInquiries() {
  const router = useRouter();
  const { t, lang } = useLang();
  const { signOut } = useSession();

  const [loading, setLoading] = useState(true);
  const [failed, setFailed] = useState(false);
  const [items, setItems] = useState<QueueItem[]>([]);
  const [counts, setCounts] = useState<QueueCounts | null>(null);
  const [autoSendPolicy, setAutoSendPolicy] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  const [selected, setSelected] = useState<QueueItem | null>(null);
  const [detail, setDetail] = useState<TriageDetail | null>(null);
  const [triaging, setTriaging] = useState(false);
  const [sending, setSending] = useState(false);
  const [replySubject, setReplySubject] = useState('');
  const [replyBody, setReplyBody] = useState('');
  const [approved, setApproved] = useState(false);

  const authHeaders = useCallback(async (json = false) => {
    const token = await AsyncStorage.getItem('userToken');
    return json
      ? { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' }
      : { Authorization: `Bearer ${token}` };
  }, []);

  const load = useCallback(async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      const userData = await AsyncStorage.getItem('userData');
      if (!token || !userData) {
        await signOut();
        router.replace('/(tabs)');
        return;
      }

      const res = await fetchWithTimeout(
        `${API_BASE_URL}/api/ai/inquiries/queue?locale=${encodeURIComponent(lang)}`,
        { headers: { Authorization: `Bearer ${token}` } },
      );
      if (!res.ok) throw new Error('queue failed');
      const data = await res.json();

      setFailed(false);
      setItems(Array.isArray(data.items) ? data.items : []);
      setCounts(data.counts || { total: 0, needs_human: 0, has_approved_help: 0 });
      setAutoSendPolicy(!!data.auto_send_policy);
      setCache(CACHE_KEY, { items: data.items || [], counts: data.counts, auto_send_policy: data.auto_send_policy }).catch(() => {});
    } catch (error) {
      // Offline / server down: show the last queue we cached, never an empty lie.
      const cached = await getCache<any>(CACHE_KEY);
      if (cached && Array.isArray(cached.items)) {
        setItems(cached.items);
        setCounts(cached.counts || null);
        setAutoSendPolicy(!!cached.auto_send_policy);
      } else {
        // No cache either: say so on the screen and offer a retry instead of
        // showing an empty queue that looks like "nothing to do".
        setFailed(true);
      }
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [lang, router, signOut, t]);

  useEffect(() => {
    load();
  }, [load]);

  const openTriage = async (item: QueueItem) => {
    setSelected(item);
    setDetail(null);
    setApproved(false);
    setReplySubject('');
    setReplyBody('');
    setTriaging(true);
    try {
      const res = await fetchWithTimeout(`${API_BASE_URL}/api/ai/inquiries/${encodeURIComponent(item.id)}/triage`, {
        method: 'POST',
        headers: await authHeaders(true),
        body: JSON.stringify({ locale: lang }),
      });
      const data = await res.json();
      if (!res.ok) {
        Alert.alert(t('app.error'), data?.error || t('inquiry_triage.error'));
        setSelected(null);
        return;
      }
      setDetail(data);
      setReplySubject(data?.draft?.subject || '');
      setReplyBody(data?.draft?.body || '');
    } catch (error) {
      Alert.alert(t('app.error'), t('inquiry_triage.error'));
      setSelected(null);
    } finally {
      setTriaging(false);
    }
  };

  const sendReply = async () => {
    if (!selected || !detail) return;
    const needsApproval = detail.approval?.required !== false;
    if (needsApproval && !approved) {
      Alert.alert(t('app.error'), t('inquiry_triage.approve_required'));
      return;
    }
    if (!replyBody.trim()) {
      Alert.alert(t('app.error'), t('inquiry_triage.error'));
      return;
    }

    setSending(true);
    try {
      const res = await fetchWithTimeout(`${API_BASE_URL}/api/ai/inquiries/${encodeURIComponent(selected.id)}/reply`, {
        method: 'POST',
        headers: await authHeaders(true),
        body: JSON.stringify({ approved: true, subject: replySubject, body: replyBody, locale: lang }),
      });
      const data = await res.json();
      if (!res.ok) {
        Alert.alert(t('app.error'), data?.error || t('inquiry_triage.sent_fail'));
        return;
      }
      Alert.alert(t('inquiry_triage.sent_ok'), data?.notice || t('inquiry_triage.not_resolved'));
      setSelected(null);
      setDetail(null);
      await load();
    } catch (error) {
      Alert.alert(t('app.error'), t('inquiry_triage.sent_fail'));
    } finally {
      setSending(false);
    }
  };

  const line = (label: string, value: string) => (
    <View style={styles.detailRow}>
      <Text style={styles.detailLabel}>{label}</Text>
      <Text style={styles.detailValue}>{value}</Text>
    </View>
  );

  const bullets = (title: string, rows?: string[]) => {
    if (!rows || rows.length === 0) return null;
    return (
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>{title}</Text>
        {rows.map((row, index) => (
          <Text key={`${title}-${index}`} style={styles.bullet}>{'• '}{row}</Text>
        ))}
      </View>
    );
  };

  const renderItem = ({ item }: { item: QueueItem }) => (
    <TouchableOpacity style={styles.card} onPress={() => openTriage(item)}>
      <View style={styles.cardHead}>
        <Text style={styles.cardTitle} numberOfLines={2}>{item.subject || '-'}</Text>
        <View style={[styles.badge, { backgroundColor: URGENCY_COLOR[item.urgency] || '#64748b' }]}>
          <Text style={styles.badgeText}>{item.urgency_label || item.urgency}</Text>
        </View>
      </View>
      <Text style={styles.cardMeta} numberOfLines={1}>{item.email}</Text>
      <View style={styles.cardFoot}>
        <Text style={styles.chip}>{item.category_label || item.category}</Text>
        <Text style={styles.chip}>{item.route_label || item.route}</Text>
        <Text style={[styles.chip, item.needs_human ? styles.chipWarn : styles.chipOk]}>
          {item.needs_human ? t('inquiry_triage.needs_human_yes') : t('inquiry_triage.needs_human_no')}
        </Text>
      </View>
      {!item.has_approved_help ? (
        <Text style={styles.warnNote}>{t('inquiry_triage.no_help')}</Text>
      ) : null}
    </TouchableOpacity>
  );

  if (loading) {
    return (
      <View style={styles.center}>
        <SmartCartLoader size={64} />
        <Text style={styles.muted}>{t('inquiry_triage.loading')}</Text>
      </View>
    );
  }

  if (failed && items.length === 0) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.center}>
          <Ionicons name="cloud-offline-outline" size={48} color="#94a3b8" />
          <Text style={styles.muted}>{t('inquiry_triage.error')}</Text>
          <TouchableOpacity
            style={styles.sendBtn}
            onPress={() => { setLoading(true); setFailed(false); load(); }}
          >
            <Ionicons name="refresh" size={18} color="#fff" />
            <Text style={styles.sendBtnText}>{t('inquiry_triage.retry')}</Text>
          </TouchableOpacity>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar barStyle="dark-content" />
      <View style={styles.header}>
        <View style={{ flex: 1 }}>
          <Text style={styles.title}>{t('inquiry_triage.title')}</Text>
          <Text style={styles.subtitle}>{t('inquiry_triage.subtitle')}</Text>
        </View>
        <TouchableOpacity
          style={styles.refreshBtn}
          disabled={refreshing}
          onPress={() => { setRefreshing(true); load(); }}
        >
          <Ionicons name="refresh" size={20} color="#4f46e5" />
        </TouchableOpacity>
      </View>

      <View style={styles.statsRow}>
        <View style={styles.stat}>
          <Text style={styles.statLabel}>{t('inquiry_triage.stat_total')}</Text>
          <Text style={styles.statValue}>{counts ? counts.total : items.length}</Text>
        </View>
        <View style={styles.stat}>
          <Text style={styles.statLabel}>{t('inquiry_triage.stat_needs_human')}</Text>
          <Text style={styles.statValue}>{counts ? counts.needs_human : 0}</Text>
        </View>
        <View style={styles.stat}>
          <Text style={styles.statLabel}>{t('inquiry_triage.stat_with_help')}</Text>
          <Text style={styles.statValue}>{counts ? counts.has_approved_help : 0}</Text>
        </View>
      </View>

      {autoSendPolicy ? <Text style={styles.policyNote}>{t('inquiry_triage.auto_sent_note')}</Text> : null}

      <FlatList
        data={items}
        keyExtractor={(item) => item.id}
        renderItem={renderItem}
        contentContainerStyle={styles.list}
        refreshing={refreshing}
        onRefresh={() => { setRefreshing(true); load(); }}
        ListHeaderComponent={<Text style={styles.sectionTitle}>{t('inquiry_triage.queue_title')}</Text>}
        ListEmptyComponent={<Text style={styles.muted}>{t('inquiry_triage.queue_empty')}</Text>}
      />

      <Modal visible={!!selected} animationType="slide" onRequestClose={() => setSelected(null)}>
        <SafeAreaView style={styles.container}>
          <View style={styles.header}>
            <Text style={[styles.title, { flex: 1 }]} numberOfLines={2}>{selected?.subject || ''}</Text>
            <TouchableOpacity onPress={() => setSelected(null)}>
              <Ionicons name="close" size={24} color="#334155" />
            </TouchableOpacity>
          </View>

          <ScrollView contentContainerStyle={styles.list}>
            {triaging ? (
              <View style={styles.center}>
                <SmartCartLoader size={56} />
                <Text style={styles.muted}>{t('inquiry_triage.triaging')}</Text>
              </View>
            ) : null}

            {detail ? (
              <View>
                <Text style={[styles.badgeText, styles.aiBadge]}>{t('inquiry_triage.ai_badge')}</Text>
                {detail.notice ? <Text style={styles.infoNote}>{detail.notice}</Text> : null}
                {detail.persisted === false ? <Text style={styles.infoNote}>{t('inquiry_triage.persisted_no')}</Text> : null}

                {line(t('inquiry_triage.label_category'), detail.triage?.category_label || '-')}
                {line(t('inquiry_triage.label_urgency'), detail.triage?.urgency_label || '-')}
                {line(t('inquiry_triage.label_route'), detail.triage?.route_label || '-')}
                {line(
                  t('inquiry_triage.label_escalate'),
                  detail.triage?.needs_human ? t('inquiry_triage.needs_human_yes') : t('inquiry_triage.needs_human_no'),
                )}

                {detail.draft?.uses_approved_help === false ? (
                  <Text style={styles.errorNote}>{t('inquiry_triage.no_help')}</Text>
                ) : null}

                {bullets(t('inquiry_triage.reasons'), [
                  ...(detail.triage?.needs_human_reasons || []),
                  ...(detail.triage?.urgency_reasons || []),
                ])}
                {bullets(t('inquiry_triage.evidence'), detail.triage?.urgency_evidence)}
                {bullets(
                  t('inquiry_triage.sources_title'),
                  (detail.triage?.matched_topics || []).map((topic) => `${topic.id} (${topic.category})`),
                )}

                <View style={styles.section}>
                  <Text style={styles.sectionTitle}>{t('inquiry_triage.draft_title')}</Text>
                  <Text style={styles.muted}>{t('inquiry_triage.draft_edited_note')}</Text>
                  <Text style={styles.fieldLabel}>{t('inquiry_triage.draft_subject')}</Text>
                  <TextInput
                    style={styles.input}
                    value={replySubject}
                    onChangeText={setReplySubject}
                    placeholder={t('inquiry_triage.draft_subject')}
                    placeholderTextColor="#94a3b8"
                  />
                  <TextInput
                    style={[styles.input, styles.textarea]}
                    value={replyBody}
                    onChangeText={setReplyBody}
                    multiline
                    textAlignVertical="top"
                  />
                </View>

                {detail.approval?.required === false ? (
                  <View>
                    <Text style={styles.infoNote}>{t('inquiry_triage.auto_sent_note')}</Text>
                    {detail.auto_sent ? <Text style={styles.okNote}>{t('inquiry_triage.sent_ok')}</Text> : null}
                  </View>
                ) : (
                  <View style={styles.section}>
                    <View style={styles.approveRow}>
                      <Switch value={approved} onValueChange={setApproved} />
                      <Text style={styles.approveText}>{t('inquiry_triage.approve_confirm')}</Text>
                    </View>
                    <TouchableOpacity
                      style={[styles.sendBtn, sending && styles.sendBtnDisabled]}
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

                <Text style={styles.muted}>{t('inquiry_triage.not_resolved')}</Text>
                {bullets(t('inquiry_triage.sources_title'), detail.sources)}
                {bullets(t('inquiry_triage.limitations_title'), detail.limitations)}
              </View>
            ) : null}
          </ScrollView>
        </SafeAreaView>
      </Modal>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f8fafc' },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 14,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  title: { fontSize: 18, fontWeight: '800', color: '#0f172a' },
  subtitle: { fontSize: 12.5, color: '#64748b', marginTop: 2 },
  refreshBtn: { width: 42, height: 42, borderRadius: 12, borderWidth: 1, borderColor: '#e2e8f0', alignItems: 'center', justifyContent: 'center', backgroundColor: '#fff' },
  statsRow: { flexDirection: 'row', gap: 10, paddingHorizontal: 16, paddingTop: 14 },
  stat: { flex: 1, backgroundColor: '#fff', borderWidth: 1, borderColor: '#e2e8f0', borderRadius: 12, padding: 12 },
  statLabel: { fontSize: 11, color: '#64748b', textTransform: 'uppercase' },
  statValue: { fontSize: 20, fontWeight: '800', color: '#0f172a', marginTop: 4 },
  policyNote: { margin: 16, marginBottom: 0, backgroundColor: '#eff6ff', color: '#1e40af', padding: 10, borderRadius: 10, fontSize: 12.5 },
  list: { padding: 16, paddingBottom: 40, gap: 12 },
  card: { backgroundColor: '#fff', borderWidth: 1, borderColor: '#e2e8f0', borderRadius: 14, padding: 14, gap: 6 },
  cardHead: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
  cardTitle: { flex: 1, fontSize: 15, fontWeight: '700', color: '#0f172a' },
  cardMeta: { fontSize: 12.5, color: '#64748b' },
  cardFoot: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginTop: 2 },
  chip: { fontSize: 11.5, color: '#334155', backgroundColor: '#f1f5f9', borderRadius: 999, paddingHorizontal: 10, paddingVertical: 4, overflow: 'hidden' },
  chipWarn: { backgroundColor: '#fff7ed', color: '#9a3412' },
  chipOk: { backgroundColor: '#ecfdf5', color: '#047857' },
  warnNote: { fontSize: 12, color: '#b45309', marginTop: 4 },
  badge: { borderRadius: 999, paddingHorizontal: 10, paddingVertical: 4 },
  badgeText: { color: '#fff', fontSize: 11.5, fontWeight: '700' },
  aiBadge: { alignSelf: 'flex-start', backgroundColor: '#4f46e5', marginBottom: 10, overflow: 'hidden' },
  detailRow: { flexDirection: 'row', gap: 10, paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: '#eef2f7' },
  detailLabel: { width: 120, fontSize: 12.5, color: '#64748b', fontWeight: '600' },
  detailValue: { flex: 1, fontSize: 14, color: '#0f172a' },
  section: { marginTop: 16, gap: 6 },
  sectionTitle: { fontSize: 13, fontWeight: '700', color: '#334155' },
  bullet: { fontSize: 13, color: '#475569' },
  fieldLabel: { fontSize: 12.5, fontWeight: '600', color: '#334155', marginTop: 8 },
  input: { backgroundColor: '#fff', borderWidth: 1, borderColor: '#e2e8f0', borderRadius: 10, paddingHorizontal: 12, paddingVertical: 10, fontSize: 14.5, color: '#0f172a' },
  textarea: { minHeight: 180 },
  approveRow: { flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 12 },
  approveText: { flex: 1, fontSize: 13, color: '#334155' },
  sendBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, backgroundColor: '#059669', borderRadius: 12, paddingVertical: 14 },
  sendBtnDisabled: { opacity: 0.6 },
  sendBtnText: { color: '#fff', fontWeight: '700', fontSize: 15 },
  infoNote: { backgroundColor: '#eff6ff', color: '#1e40af', padding: 10, borderRadius: 10, fontSize: 12.5, marginTop: 10 },
  okNote: { backgroundColor: '#ecfdf5', color: '#065f46', padding: 10, borderRadius: 10, fontSize: 12.5, marginTop: 10 },
  errorNote: { backgroundColor: '#fef2f2', color: '#991b1b', padding: 10, borderRadius: 10, fontSize: 12.5, marginTop: 10 },
  muted: { fontSize: 12.5, color: '#64748b', marginTop: 8 },
});
