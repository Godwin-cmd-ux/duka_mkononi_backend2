// Duka_mkononi/app/system_admin/certification.tsx
// Capability #19 - business certification review (Super Admin).
//
// This screen only RENDERS the evidence checklist the server computed from the
// real business / member / product rows. It never certifies anything: the
// decision stays with the existing authorized administrator workflow, and the
// screen says so. A submitted document is always shown as "a person must check"
// because the platform stores no documents.
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useState } from 'react';
import {
    FlatList,
    Modal,
    SafeAreaView,
    ScrollView,
    StatusBar,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from 'react-native';
import SmartCartLoader from '../../components/SmartCartLoader';
import { API_BASE_URL } from '../../constants/api';
import { getCache, setCache } from '../../db/cache';
import { useLang } from '../../context/LanguageContext';
import { useSession } from '../../context/SessionContext';
import { fetchWithTimeout } from '../../lib/network';

const CACHE_KEY = 'system_admin:certification:queue';

interface QueueItem {
  id: string;
  business_name: string;
  business_location: string;
  business_type: string;
  completeness: number;
  missing: string[];
  missing_labels: { id: string; label: string; severity: string }[];
  human_check_ids: string[];
  human_check_labels: string[];
  summary: string[];
}

interface RequirementLine {
  id: string;
  label: string;
  severity: string;
  severity_label: string;
  status: string;
  status_label: string;
  source: string;
  evidence: string;
  why: string | null;
}

interface ReviewDetail {
  business: {
    id: string;
    business_name: string;
    business_location: string;
    business_type: string;
    is_certified: boolean;
    certified_at?: string | null;
    is_public?: unknown;
  };
  review: {
    requirements: RequirementLine[];
    missing: string[];
    human_checks: { id: string; label: string; why: string | null }[];
    completeness: number;
    summary: string[];
    sources: string[];
    limitations: string[];
  };
  checklist_hash?: string;
  notice?: string;
  reads_capped?: boolean;
}

const STATUS_COLOR: Record<string, string> = {
  met: '#059669',
  missing: '#d97706',
  unknown: '#64748b',
  human_required: '#2563eb',
};

export default function SystemAdminCertification() {
  const router = useRouter();
  const { t, lang } = useLang();
  const { signOut } = useSession();

  const [loading, setLoading] = useState(true);
  const [failed, setFailed] = useState(false);
  const [items, setItems] = useState<QueueItem[]>([]);
  const [counts, setCounts] = useState<Record<string, number> | null>(null);
  const [truncated, setTruncated] = useState(false);
  const [capped, setCapped] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  const [selected, setSelected] = useState<QueueItem | null>(null);
  const [detail, setDetail] = useState<ReviewDetail | null>(null);
  const [reviewing, setReviewing] = useState(false);

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
        `${API_BASE_URL}/api/ai/certification/queue?locale=${encodeURIComponent(lang)}`,
        { headers: { Authorization: `Bearer ${token}` } },
      );
      if (!res.ok) throw new Error('queue failed');
      const data = await res.json();

      setFailed(false);
      setItems(Array.isArray(data.items) ? data.items : []);
      setCounts(data.counts || null);
      setTruncated(!!data.truncated);
      setCapped(!!data.reads_capped);
      setCache(CACHE_KEY, { items: data.items || [], counts: data.counts }).catch(() => {});
    } catch (error) {
      const cached = await getCache<any>(CACHE_KEY);
      if (cached && Array.isArray(cached.items)) {
        setItems(cached.items);
        setCounts(cached.counts || null);
      } else {
        setFailed(true);
      }
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [lang, router, signOut]);

  useEffect(() => {
    load();
  }, [load]);

  const openReview = async (item: QueueItem) => {
    setSelected(item);
    setDetail(null);
    setReviewing(true);
    try {
      const token = await AsyncStorage.getItem('userToken');
      const res = await fetchWithTimeout(`${API_BASE_URL}/api/ai/certification/${encodeURIComponent(item.id)}/review`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ locale: lang }),
      });
      const data = await res.json();
      if (!res.ok) {
        setSelected(null);
        return;
      }
      setDetail(data);
    } catch (error) {
      setSelected(null);
    } finally {
      setReviewing(false);
    }
  };

  const renderItem = ({ item }: { item: QueueItem }) => (
    <TouchableOpacity style={styles.card} onPress={() => openReview(item)}>
      <View style={styles.cardHead}>
        <Text style={styles.cardTitle} numberOfLines={2}>{item.business_name}</Text>
        <View style={[styles.badge, { backgroundColor: (item.completeness || 0) >= 100 ? '#059669' : '#d97706' }]}>
          <Text style={styles.badgeText}>{item.completeness}%</Text>
        </View>
      </View>
      <Text style={styles.cardMeta} numberOfLines={1}>{item.business_location || '-'}</Text>
      <View style={styles.cardFoot}>
        {item.missing_labels && item.missing_labels.length > 0 ? (
          item.missing_labels.slice(0, 4).map((m, index) => (
            <Text key={`${item.id}-m-${index}`} style={[styles.chip, styles.chipWarn]}>{m.label}</Text>
          ))
        ) : (
          <Text style={[styles.chip, styles.chipOk]}>{t('certification_ai.no_missing')}</Text>
        )}
        {item.human_check_labels && item.human_check_labels.length > 0 ? (
          <Text style={[styles.chip, styles.chipInfo]}>
            {t('certification_ai.col_human')}: {item.human_check_labels.length}
          </Text>
        ) : null}
      </View>
    </TouchableOpacity>
  );

  if (loading) {
    return (
      <View style={styles.center}>
        <SmartCartLoader size={64} />
        <Text style={styles.muted}>{t('certification_ai.loading')}</Text>
      </View>
    );
  }

  if (failed && items.length === 0) {
    return (
      <SafeAreaView style={styles.container}>
        <View style={styles.center}>
          <Ionicons name="cloud-offline-outline" size={48} color="#94a3b8" />
          <Text style={styles.muted}>{t('certification_ai.error')}</Text>
          <TouchableOpacity style={styles.primaryBtn} onPress={() => { setLoading(true); setFailed(false); load(); }}>
            <Ionicons name="refresh" size={18} color="#fff" />
            <Text style={styles.primaryBtnText}>{t('certification_ai.retry')}</Text>
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
          <Text style={styles.title}>{t('certification_ai.title')}</Text>
          <Text style={styles.subtitle}>{t('certification_ai.subtitle')}</Text>
        </View>
        <TouchableOpacity style={styles.refreshBtn} onPress={() => { setRefreshing(true); load(); }}>
          <Ionicons name="refresh" size={20} color="#4f46e5" />
        </TouchableOpacity>
      </View>

      <View style={styles.statsRow}>
        <View style={styles.stat}>
          <Text style={styles.statLabel}>{t('certification_ai.stat_pending')}</Text>
          <Text style={styles.statValue}>{counts?.pending ?? items.length}</Text>
        </View>
        <View style={styles.stat}>
          <Text style={styles.statLabel}>{t('certification_ai.stat_ready')}</Text>
          <Text style={styles.statValue}>{counts?.ready ?? 0}</Text>
        </View>
        <View style={styles.stat}>
          <Text style={styles.statLabel}>{t('certification_ai.stat_certified')}</Text>
          <Text style={styles.statValue}>{counts?.certified ?? 0}</Text>
        </View>
        <View style={styles.stat}>
          <Text style={styles.statLabel}>{t('certification_ai.stat_human')}</Text>
          <Text style={styles.statValue}>{counts?.with_human_checks ?? 0}</Text>
        </View>
      </View>

      {truncated ? <Text style={styles.infoNote}>{t('certification_ai.truncated_note')}</Text> : null}
      {capped ? <Text style={styles.infoNote}>{t('certification_ai.reads_capped_note')}</Text> : null}
      <Text style={styles.infoNote}>{t('certification_ai.notice_no_decision')}</Text>

      <FlatList
        data={items}
        keyExtractor={(item) => item.id}
        renderItem={renderItem}
        contentContainerStyle={styles.list}
        refreshing={refreshing}
        onRefresh={() => { setRefreshing(true); load(); }}
        ListHeaderComponent={<Text style={styles.sectionTitle}>{t('certification_ai.queue_title')}</Text>}
        ListEmptyComponent={<Text style={styles.muted}>{t('certification_ai.empty')}</Text>}
      />

      <Modal visible={!!selected} animationType="slide" onRequestClose={() => setSelected(null)}>
        <SafeAreaView style={styles.container}>
          <View style={styles.header}>
            <Text style={[styles.title, { flex: 1 }]} numberOfLines={2}>{selected?.business_name || ''}</Text>
            <TouchableOpacity onPress={() => setSelected(null)}>
              <Ionicons name="close" size={24} color="#334155" />
            </TouchableOpacity>
          </View>

          <ScrollView contentContainerStyle={styles.list}>
            {reviewing ? (
              <View style={styles.center}>
                <SmartCartLoader size={56} />
                <Text style={styles.muted}>{t('certification_ai.reviewing')}</Text>
              </View>
            ) : null}

            {detail ? (
              <View>
                <Text style={[styles.badgeText, styles.aiBadge]}>{t('certification_ai.ai_badge')}</Text>
                <Text style={styles.infoNote}>{detail.notice || t('certification_ai.notice_no_decision')}</Text>
                <Text style={styles.infoNote}>{t('certification_ai.notice_decision_endpoint')}</Text>
                <Text style={styles.infoNote}>{t('certification_ai.notice_documents')}</Text>

                <View style={styles.detailRow}>
                  <Text style={styles.detailLabel}>{t('certification_ai.col_location')}</Text>
                  <Text style={styles.detailValue}>{detail.business.business_location || '-'}</Text>
                </View>
                <View style={styles.detailRow}>
                  <Text style={styles.detailLabel}>{t('certification_ai.label_type')}</Text>
                  <Text style={styles.detailValue}>{detail.business.business_type || '-'}</Text>
                </View>
                <View style={styles.detailRow}>
                  <Text style={styles.detailLabel}>{t('certification_ai.label_status')}</Text>
                  <Text style={styles.detailValue}>
                    {detail.business.is_certified ? t('certification_ai.badge_certified') : t('certification_ai.badge_pending')}
                  </Text>
                </View>
                <View style={styles.detailRow}>
                  <Text style={styles.detailLabel}>{t('certification_ai.col_completeness')}</Text>
                  <Text style={styles.detailValue}>{detail.review.completeness}%</Text>
                </View>

                <Text style={[styles.sectionTitle, { marginTop: 16 }]}>{t('certification_ai.section_requirements')}</Text>
                {(detail.review.requirements || []).map((line) => (
                  <View key={line.id} style={styles.req}>
                    <View style={styles.reqHead}>
                      <Text style={styles.reqTitle}>{line.label}</Text>
                      <View style={[styles.badge, { backgroundColor: STATUS_COLOR[line.status] || '#64748b' }]}>
                        <Text style={styles.badgeText}>{line.status_label}</Text>
                      </View>
                    </View>
                    <Text style={styles.reqMeta}>{line.severity_label}</Text>
                    <Text style={styles.reqEvidence}>{line.evidence}</Text>
                    <Text style={styles.reqSource}>{line.source}</Text>
                    {line.why ? <Text style={styles.reqWhy}>{line.why}</Text> : null}
                  </View>
                ))}

                {detail.review.human_checks && detail.review.human_checks.length > 0 ? (
                  <View style={styles.section}>
                    <Text style={styles.sectionTitle}>{t('certification_ai.section_human_checks')}</Text>
                    {detail.review.human_checks.map((h) => (
                      <Text key={`h-${h.id}`} style={styles.bullet}>
                        {'• '}{h.label}{h.why ? ` - ${h.why}` : ''}
                      </Text>
                    ))}
                  </View>
                ) : null}

                {(detail.review.sources || []).length > 0 ? (
                  <View style={styles.section}>
                    <Text style={styles.sectionTitle}>{t('certification_ai.section_sources')}</Text>
                    {detail.review.sources.map((s, index) => (
                      <Text key={`s-${index}`} style={styles.reqSource}>{s}</Text>
                    ))}
                  </View>
                ) : null}

                {(detail.review.limitations || []).length > 0 ? (
                  <View style={styles.section}>
                    <Text style={styles.sectionTitle}>{t('certification_ai.section_limitations')}</Text>
                    {detail.review.limitations.map((l, index) => (
                      <Text key={`l-${index}`} style={styles.bullet}>{'• '}{l}</Text>
                    ))}
                  </View>
                ) : null}

                {detail.checklist_hash ? <Text style={styles.reqSource}>{detail.checklist_hash}</Text> : null}
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
    flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 16, paddingVertical: 14,
    backgroundColor: '#fff', borderBottomWidth: 1, borderBottomColor: '#e2e8f0',
  },
  title: { fontSize: 18, fontWeight: '800', color: '#0f172a' },
  subtitle: { fontSize: 12.5, color: '#64748b', marginTop: 2 },
  refreshBtn: { width: 42, height: 42, borderRadius: 12, borderWidth: 1, borderColor: '#e2e8f0', alignItems: 'center', justifyContent: 'center', backgroundColor: '#fff' },
  statsRow: { flexDirection: 'row', gap: 8, paddingHorizontal: 16, paddingTop: 14 },
  stat: { flex: 1, backgroundColor: '#fff', borderWidth: 1, borderColor: '#e2e8f0', borderRadius: 12, padding: 10 },
  statLabel: { fontSize: 10.5, color: '#64748b', textTransform: 'uppercase' },
  statValue: { fontSize: 18, fontWeight: '800', color: '#0f172a', marginTop: 4 },
  list: { padding: 16, paddingBottom: 40, gap: 12 },
  card: { backgroundColor: '#fff', borderWidth: 1, borderColor: '#e2e8f0', borderRadius: 14, padding: 14, gap: 6 },
  cardHead: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
  cardTitle: { flex: 1, fontSize: 15, fontWeight: '700', color: '#0f172a' },
  cardMeta: { fontSize: 12.5, color: '#64748b' },
  cardFoot: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginTop: 2 },
  chip: { fontSize: 11.5, color: '#334155', backgroundColor: '#f1f5f9', borderRadius: 999, paddingHorizontal: 10, paddingVertical: 4, overflow: 'hidden' },
  chipWarn: { backgroundColor: '#fff7ed', color: '#9a3412' },
  chipOk: { backgroundColor: '#ecfdf5', color: '#047857' },
  chipInfo: { backgroundColor: '#eff6ff', color: '#1e40af' },
  badge: { borderRadius: 999, paddingHorizontal: 10, paddingVertical: 4 },
  badgeText: { color: '#fff', fontSize: 11.5, fontWeight: '700' },
  aiBadge: { alignSelf: 'flex-start', backgroundColor: '#4f46e5', marginBottom: 10, overflow: 'hidden' },
  detailRow: { flexDirection: 'row', gap: 10, paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: '#eef2f7' },
  detailLabel: { width: 120, fontSize: 12.5, color: '#64748b', fontWeight: '600' },
  detailValue: { flex: 1, fontSize: 14, color: '#0f172a' },
  section: { marginTop: 16, gap: 6 },
  sectionTitle: { fontSize: 13, fontWeight: '700', color: '#334155' },
  bullet: { fontSize: 13, color: '#475569' },
  req: { backgroundColor: '#fff', borderWidth: 1, borderColor: '#eef2f7', borderRadius: 12, padding: 12, marginTop: 10, gap: 4 },
  reqHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  reqTitle: { flex: 1, fontSize: 14, fontWeight: '700', color: '#0f172a' },
  reqMeta: { fontSize: 11.5, color: '#64748b' },
  reqEvidence: { fontSize: 13, color: '#475569' },
  reqSource: { fontSize: 11.5, color: '#94a3b8' },
  reqWhy: { fontSize: 12.5, color: '#1e40af', backgroundColor: '#eff6ff', borderRadius: 8, padding: 8, marginTop: 4 },
  primaryBtn: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: '#4f46e5', borderRadius: 12, paddingVertical: 14, paddingHorizontal: 18, marginTop: 14 },
  primaryBtnText: { color: '#fff', fontWeight: '700', fontSize: 15 },
  infoNote: { backgroundColor: '#eff6ff', color: '#1e40af', padding: 10, borderRadius: 10, fontSize: 12.5, marginHorizontal: 16, marginTop: 10 },
  muted: { fontSize: 12.5, color: '#64748b', marginTop: 8 },
});
