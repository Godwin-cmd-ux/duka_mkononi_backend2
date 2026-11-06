// Duka_mkononi/app/system_admin/businesses.tsx
// Mobile twin of the website's /system_admin/businesses page.
//
// Super admins can see every registered business and use the same four
// actions as the Users page: view, activity, block/unblock and delete. The
// server owns the rules (blocking every member, deleting staff and their
// data); this screen only presents the real API response.
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  Modal,
  RefreshControl,
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
import { isSystemAdminRole } from '../../constants/session';
import { useLang, LOCALE_TAGS } from '../../context/LanguageContext';
import { useSession } from '../../context/SessionContext';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';
import { useTheme, ThemeColors } from '../../lib/theme';

interface BusinessUser {
  id: string;
  full_name?: string;
  email?: string;
  role?: string;
  status?: string;
  is_online?: boolean;
}

interface BusinessCounts {
  users?: number;
  staff?: number;
  online?: number;
  products?: number | null;
  customers?: number | null;
  sales?: number | null;
  sales_total?: number | null;
  expenses?: number | null;
  expenses_total?: number | null;
  orders?: number | null;
}

interface Business {
  id: string;
  business_name?: string;
  business_location?: string;
  business_type?: string;
  business_logo_url?: string;
  is_certified?: boolean;
  is_public?: boolean;
  is_blocked?: boolean;
  status?: string;
  created_at?: string;
  owner_name?: string;
  owner_email?: string;
  users_count?: number;
  staff_count?: number;
  online_count?: number;
  counts?: BusinessCounts;
  users?: BusinessUser[];
}

interface BusinessLog {
  id: string;
  action?: string;
  endpoint?: string;
  status?: string;
  created_at?: string;
  user?: { full_name?: string; email?: string } | null;
}

type Filter = 'all' | 'active' | 'blocked' | 'certified';

async function apiRequest(path: string, init: RequestInit = {}): Promise<any> {
  const token = await AsyncStorage.getItem('userToken');
  if (!token) throw new Error('AUTH_REQUIRED');

  const response = await fetchWithTimeout(`${API_BASE_URL}${path}`, {
    ...init,
    headers: {
      Authorization: `Bearer ${token}`,
      ...(init.headers || {}),
    },
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(data?.error || `HTTP ${response.status}`);
  }
  return data;
}

export default function SystemAdminBusinessesScreen() {
  const { colors: tc } = useTheme();
  const styles = useMemo(() => makeStyles(tc), [tc]);
  const { t, lang } = useLang();
  const { signOut } = useSession();
  const router = useRouter();

  const [ready, setReady] = useState(false);
  const [businesses, setBusinesses] = useState<Business[]>([]);
  const [search, setSearch] = useState('');
  const [filter, setFilter] = useState<Filter>('all');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  const [detail, setDetail] = useState<Business | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [detailError, setDetailError] = useState('');

  const [activity, setActivity] = useState<Business | null>(null);
  const [logs, setLogs] = useState<BusinessLog[]>([]);
  const [activityLoading, setActivityLoading] = useState(false);
  const [activityError, setActivityError] = useState('');

  useEffect(() => {
    (async () => {
      try {
        const raw = await AsyncStorage.getItem('userData');
        const user = raw ? JSON.parse(raw) : null;
        if (!user || !isSystemAdminRole(user.role)) {
          Alert.alert(t('system_admin.access_denied'), t('system_admin.admin_required'));
          await signOut();
          router.replace('/(tabs)' as any);
          return;
        }
        setReady(true);
      } catch {
        await signOut();
        router.replace('/(tabs)' as any);
      }
    })();
  }, [router, signOut, t]);

  const loadBusinesses = useCallback(async () => {
    setError('');
    try {
      const data = await apiRequest('/api/system-admin/businesses');
      setBusinesses(Array.isArray(data) ? data : data?.businesses || []);
    } catch (err: any) {
      if (err?.message === 'AUTH_REQUIRED') {
        await signOut();
        router.replace('/(tabs)' as any);
        return;
      }
      setError(err?.message || t('system_admin.biz_load_failed'));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [router, signOut, t]);

  useEffect(() => {
    if (ready) loadBusinesses();
  }, [ready, loadBusinesses]);

  const openDetail = async (business: Business) => {
    setDetail(business);
    setDetailError('');
    setDetailLoading(true);
    try {
      const data = await apiRequest(
        `/api/system-admin/businesses/${encodeURIComponent(business.id)}`,
      );
      setDetail(data);
    } catch (err: any) {
      setDetailError(err?.message || t('system_admin.biz_load_failed'));
    } finally {
      setDetailLoading(false);
    }
  };

  const openActivity = async (business: Business) => {
    setDetail(null);
    setActivity(business);
    setLogs([]);
    setActivityError('');
    setActivityLoading(true);
    try {
      const data = await apiRequest(
        `/api/system-admin/businesses/${encodeURIComponent(business.id)}/activity`,
      );
      setLogs(Array.isArray(data?.logs) ? data.logs : []);
    } catch (err: any) {
      setActivityError(err?.message || t('system_admin.biz_load_failed'));
    } finally {
      setActivityLoading(false);
    }
  };

  const toggleBlock = (business: Business) => {
    const nextBlocked = !business.is_blocked;
    const name = business.business_name || t('system_admin.biz_title');
    Alert.alert(
      t(nextBlocked ? 'system_admin.biz_action_block' : 'system_admin.biz_action_unblock'),
      t(
        nextBlocked
          ? 'system_admin.biz_confirm_block'
          : 'system_admin.biz_confirm_unblock',
      ).replace('{name}', name),
      [
        { text: t('app.cancel'), style: 'cancel' },
        {
          text: t(
            nextBlocked
              ? 'system_admin.biz_action_block'
              : 'system_admin.biz_action_unblock',
          ),
          style: nextBlocked ? 'destructive' : 'default',
          onPress: async () => {
            try {
              if (!(await requireNetwork())) return;
              await apiRequest(
                `/api/system-admin/businesses/${encodeURIComponent(business.id)}/status`,
                {
                  method: 'PUT',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ blocked: nextBlocked }),
                },
              );
              Alert.alert(
                t('app.success'),
                t(
                  nextBlocked
                    ? 'system_admin.biz_blocked'
                    : 'system_admin.biz_unblocked',
                ).replace('{name}', name),
              );
              setDetail(null);
              setActivity(null);
              await loadBusinesses();
            } catch (err: any) {
              Alert.alert(
                t('app.error'),
                err?.message || t('system_admin.biz_status_failed'),
              );
            }
          },
        },
      ],
    );
  };

  const deleteBusiness = (business: Business) => {
    const name = business.business_name || t('system_admin.biz_title');
    Alert.alert(
      t('system_admin.biz_action_delete'),
      t('system_admin.biz_delete_confirm').replace('{name}', name),
      [
        { text: t('app.cancel'), style: 'cancel' },
        {
          text: t('system_admin.biz_action_delete'),
          style: 'destructive',
          onPress: async () => {
            try {
              if (!(await requireNetwork())) return;
              const data = await apiRequest(
                `/api/system-admin/businesses/${encodeURIComponent(business.id)}`,
                { method: 'DELETE' },
              );
              const count =
                Number(data?.deleted?.users) || Number(business.users_count) || 0;
              Alert.alert(
                t('app.success'),
                t('system_admin.biz_deleted')
                  .replace('{name}', name)
                  .replace('{n}', String(count)),
              );
              setDetail(null);
              setActivity(null);
              await loadBusinesses();
            } catch (err: any) {
              Alert.alert(
                t('app.error'),
                err?.message || t('system_admin.biz_delete_failed'),
              );
            }
          },
        },
      ],
    );
  };

  const statusOf = (business: Business) => {
    if (business.is_blocked) {
      return { label: t('system_admin.biz_status_blocked'), color: '#dc2626' };
    }
    if (business.is_certified) {
      return { label: t('system_admin.biz_status_certified'), color: '#059669' };
    }
    return { label: t('system_admin.biz_status_active'), color: '#2563eb' };
  };

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return businesses.filter((business) => {
      if (filter === 'blocked' && !business.is_blocked) return false;
      if (filter === 'active' && business.is_blocked) return false;
      if (filter === 'certified' && !business.is_certified) return false;
      if (!q) return true;
      return [
        business.business_name,
        business.business_location,
        business.owner_name,
        business.owner_email,
      ].some((value) => String(value || '').toLowerCase().includes(q));
    });
  }, [businesses, filter, search]);

  const blockedCount = useMemo(
    () => businesses.filter((business) => business.is_blocked).length,
    [businesses],
  );

  const memberCount = useMemo(
    () =>
      businesses.reduce(
        (sum, business) => sum + (Number(business.users_count) || 0),
        0,
      ),
    [businesses],
  );

  const formatDate = (value?: string | null): string => {
    if (!value) return t('system_admin.biz_not_set');
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return t('system_admin.biz_not_set');
    return date.toLocaleDateString(LOCALE_TAGS[lang] || 'sw-TZ', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  };

  const formatTimeAgo = (value?: string): string => {
    if (!value) return t('system_admin.log_time_never');
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return t('system_admin.log_time_never');
    const mins = Math.floor((Date.now() - date.getTime()) / 60000);
    if (mins < 1) return '—';
    if (mins < 60) {
      return t('system_admin.log_time_ago').replace('{n}', String(mins));
    }
    const hours = Math.floor(mins / 60);
    if (hours < 24) {
      return t('system_admin.log_hours_ago').replace('{n}', String(hours));
    }
    return t('system_admin.log_days_ago').replace(
      '{n}',
      String(Math.floor(hours / 24)),
    );
  };

  const formatMoney = (value?: number | null): string => {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
      return '—';
    }
    try {
      return new Intl.NumberFormat(LOCALE_TAGS[lang] || 'sw-TZ', {
        style: 'currency',
        currency: 'TZS',
        maximumFractionDigits: 0,
      }).format(Number(value));
    } catch {
      return `TZS ${Number(value).toLocaleString()}`;
    }
  };

  const loadingView = (
    <View style={[styles.loadingWrap, { backgroundColor: tc.page }]}>
      <SmartCartLoader size={64} />
      <Text style={[styles.loadingText, { color: tc.muted }]}>
        {t('system_admin.biz_loading')}
      </Text>
    </View>
  );

  if (!ready || (loading && businesses.length === 0)) {
    return loadingView;
  }

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: tc.page }]}>
      <StatusBar barStyle="dark-content" />

      <View
        style={[
          styles.header,
          { backgroundColor: tc.card, borderBottomColor: tc.line },
        ]}
      >
        <View style={styles.headerText}>
          <Text style={[styles.title, { color: tc.ink }]}>
            {t('system_admin.biz_title')}
          </Text>
          <Text style={[styles.subtitle, { color: tc.muted }]}>
            {loading && businesses.length === 0
              ? t('system_admin.biz_loading')
              : t('system_admin.biz_subtitle_count', {
                  total: businesses.length,
                  blocked: blockedCount,
                  users: memberCount,
                })}
          </Text>
        </View>
        <TouchableOpacity
          style={[styles.refreshBtn, { borderColor: tc.line }]}
          onPress={() => {
            setRefreshing(true);
            loadBusinesses();
          }}
          accessibilityRole="button"
          accessibilityLabel={t('app.refresh')}
        >
          <Ionicons name="refresh" size={20} color={tc.primary} />
        </TouchableOpacity>
      </View>

      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => {
              setRefreshing(true);
              loadBusinesses();
            }}
          />
        }
        showsVerticalScrollIndicator={false}
      >
        <View
          style={[
            styles.searchBox,
            { backgroundColor: tc.card, borderColor: tc.line },
          ]}
        >
          <Ionicons name="search" size={18} color={tc.muted} />
          <TextInput
            value={search}
            onChangeText={setSearch}
            placeholder={t('system_admin.biz_search_placeholder')}
            placeholderTextColor={tc.muted2}
            style={[styles.searchInput, { color: tc.ink }]}
          />
          {search.length > 0 ? (
            <TouchableOpacity onPress={() => setSearch('')}>
              <Ionicons name="close-circle" size={18} color={tc.muted2} />
            </TouchableOpacity>
          ) : null}
        </View>

        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.filters}
        >
          {(
            [
              ['all', t('system_admin.biz_filter_all')],
              ['active', t('system_admin.biz_filter_active')],
              ['blocked', t('system_admin.biz_filter_blocked')],
              ['certified', t('system_admin.biz_filter_certified')],
            ] as [Filter, string][]
          ).map(([key, label]) => {
            const active = filter === key;
            return (
              <TouchableOpacity
                key={key}
                onPress={() => setFilter(key)}
                style={[
                  styles.filterChip,
                  { borderColor: tc.line, backgroundColor: tc.card },
                  active && { backgroundColor: tc.primary, borderColor: tc.primary },
                ]}
              >
                <Text
                  style={[
                    styles.filterText,
                    { color: active ? '#fff' : tc.muted },
                  ]}
                >
                  {label}
                </Text>
              </TouchableOpacity>
            );
          })}
        </ScrollView>

        {error ? (
          <View style={styles.errorBox}>
            <Ionicons name="cloud-offline-outline" size={36} color={tc.muted2} />
            <Text style={[styles.errorText, { color: tc.muted }]}>{error}</Text>
            <TouchableOpacity onPress={loadBusinesses}>
              <Text style={[styles.retryText, { color: tc.primary }]}>
                {t('app.retry')}
              </Text>
            </TouchableOpacity>
          </View>
        ) : filtered.length === 0 ? (
          <View style={styles.empty}>
            <Ionicons name="storefront-outline" size={44} color={tc.muted2} />
            <Text style={[styles.emptyText, { color: tc.muted }]}>
              {t('system_admin.biz_empty')}
            </Text>
          </View>
        ) : (
          filtered.map((business) => {
            const status = statusOf(business);
            return (
              <View
                key={business.id}
                style={[
                  styles.card,
                  { backgroundColor: tc.card, borderColor: tc.line },
                ]}
              >
                <View style={styles.cardHead}>
                  <View style={[styles.avatar, { backgroundColor: tc.tint }]}>
                    {business.business_logo_url ? (
                      <Image
                        source={{ uri: business.business_logo_url }}
                        style={styles.avatarImage}
                      />
                    ) : (
                      <Text style={[styles.avatarText, { color: tc.primary }]}>
                        {String(business.business_name || '?')
                          .charAt(0)
                          .toUpperCase()}
                      </Text>
                    )}
                  </View>
                  <View style={styles.cardTitleWrap}>
                    <Text
                      style={[styles.cardTitle, { color: tc.ink }]}
                      numberOfLines={1}
                    >
                      {business.business_name || '—'}
                    </Text>
                    <Text
                      style={[styles.cardMeta, { color: tc.muted }]}
                      numberOfLines={1}
                    >
                      {business.business_location ||
                        business.business_type ||
                        t('system_admin.biz_not_set')}
                    </Text>
                  </View>
                  <View
                    style={[
                      styles.statusBadge,
                      { backgroundColor: `${status.color}18` },
                    ]}
                  >
                    <View
                      style={[styles.statusDot, { backgroundColor: status.color }]}
                    />
                    <Text style={[styles.statusText, { color: status.color }]}>
                      {status.label}
                    </Text>
                  </View>
                </View>

                <View style={styles.ownerRow}>
                  <Text style={[styles.ownerLabel, { color: tc.muted }]}>
                    {t('system_admin.biz_owner')}
                  </Text>
                  <View style={styles.ownerMain}>
                    <Text
                      style={[styles.ownerValue, { color: tc.ink }]}
                      numberOfLines={1}
                    >
                      {business.owner_name ||
                        business.owner_email ||
                        t('system_admin.biz_no_owner')}
                    </Text>
                    {business.owner_name && business.owner_email ? (
                      <Text
                        style={[styles.ownerEmail, { color: tc.muted }]}
                        numberOfLines={1}
                      >
                        {business.owner_email}
                      </Text>
                    ) : null}
                  </View>
                  <Text style={[styles.usersCount, { color: tc.muted }]}>
                    {t('system_admin.biz_users', {
                      n: Number(business.users_count) || 0,
                    })}
                  </Text>
                </View>

                <View style={styles.actions}>
                  <TouchableOpacity
                    style={[styles.action, { borderColor: tc.line }]}
                    onPress={() => openDetail(business)}
                  >
                    <Ionicons name="eye-outline" size={16} color={tc.muted} />
                    <Text style={[styles.actionText, { color: tc.muted }]}>
                      {t('system_admin.biz_action_view')}
                    </Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.action, { borderColor: tc.line }]}
                    onPress={() => openActivity(business)}
                  >
                    <Ionicons name="pulse-outline" size={16} color="#2563eb" />
                    <Text style={[styles.actionText, { color: '#2563eb' }]}>
                      {t('system_admin.biz_action_activity')}
                    </Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[
                      styles.action,
                      { borderColor: business.is_blocked ? '#a7f3d0' : '#fecaca' },
                    ]}
                    onPress={() => toggleBlock(business)}
                  >
                    <Ionicons
                      name={business.is_blocked ? 'lock-open-outline' : 'lock-closed-outline'}
                      size={16}
                      color={business.is_blocked ? '#047857' : '#dc2626'}
                    />
                    <Text
                      style={[
                        styles.actionText,
                        { color: business.is_blocked ? '#047857' : '#dc2626' },
                      ]}
                    >
                      {t(
                        business.is_blocked
                          ? 'system_admin.biz_action_unblock'
                          : 'system_admin.biz_action_block',
                      )}
                    </Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.action, { borderColor: '#f5c6c0' }]}
                    onPress={() => deleteBusiness(business)}
                  >
                    <Ionicons name="trash-outline" size={16} color="#c0392b" />
                    <Text style={[styles.actionText, { color: '#c0392b' }]}>
                      {t('system_admin.biz_action_delete')}
                    </Text>
                  </TouchableOpacity>
                </View>
              </View>
            );
          })
        )}
      </ScrollView>

      <Modal
        visible={detail !== null}
        transparent
        animationType="slide"
        onRequestClose={() => setDetail(null)}
      >
        <View style={styles.modalOverlay}>
          <View style={[styles.modalSheet, { backgroundColor: tc.card }]}>
            <View style={[styles.modalHeader, { borderBottomColor: tc.line }]}>
              <Text style={[styles.modalTitle, { color: tc.ink }]}>
                {t('system_admin.biz_detail_title')}
              </Text>
              <TouchableOpacity onPress={() => setDetail(null)}>
                <Ionicons name="close" size={24} color={tc.muted} />
              </TouchableOpacity>
            </View>

            {detailLoading ? (
              <View style={styles.modalLoading}>
                <ActivityIndicator size="large" color={tc.primary} />
              </View>
            ) : detailError ? (
              <View style={styles.modalLoading}>
                <Text style={[styles.errorText, { color: tc.muted }]}>
                  {detailError}
                </Text>
              </View>
            ) : detail ? (
              <ScrollView contentContainerStyle={styles.modalBody}>
                <View style={styles.detailIdentity}>
                  <View style={[styles.detailAvatar, { backgroundColor: tc.tint }]}>
                    {detail.business_logo_url ? (
                      <Image
                        source={{ uri: detail.business_logo_url }}
                        style={styles.avatarImage}
                      />
                    ) : (
                      <Text style={[styles.detailAvatarText, { color: tc.primary }]}>
                        {String(detail.business_name || '?').charAt(0).toUpperCase()}
                      </Text>
                    )}
                  </View>
                  <Text style={[styles.detailName, { color: tc.ink }]}>
                    {detail.business_name || '—'}
                  </Text>
                  <Text style={[styles.detailMeta, { color: tc.muted }]}>
                    {detail.business_location || t('system_admin.biz_not_set')}
                    {detail.business_type ? ` · ${detail.business_type}` : ''}
                  </Text>
                  <View style={styles.detailBadges}>
                    <View
                      style={[
                        styles.statusBadge,
                        { backgroundColor: `${statusOf(detail).color}14` },
                      ]}
                    >
                      <View
                        style={[
                          styles.statusDot,
                          { backgroundColor: statusOf(detail).color },
                        ]}
                      />
                      <Text
                        style={[
                          styles.statusText,
                          { color: statusOf(detail).color },
                        ]}
                      >
                        {statusOf(detail).label}
                      </Text>
                    </View>
                    <View
                      style={[styles.statusBadge, { backgroundColor: tc.tint }]}
                    >
                      <Text style={[styles.statusText, { color: tc.primary }]}>
                        {t('system_admin.biz_users', {
                          n:
                            detail.counts?.users ??
                            detail.users_count ??
                            (detail.users || []).length,
                        })}
                      </Text>
                    </View>
                  </View>
                </View>

                <View style={styles.statGrid}>
                  {[
                    [
                      t('system_admin.biz_users').replace('{n}', '').trim(),
                      detail.counts?.users ?? detail.users_count ?? 0,
                    ],
                    [t('system_admin.biz_products'), detail.counts?.products],
                    [t('system_admin.biz_sales'), detail.counts?.sales],
                    [
                      t('system_admin.biz_sales_total'),
                      formatMoney(detail.counts?.sales_total),
                    ],
                    [t('system_admin.biz_expenses'), detail.counts?.expenses],
                    [
                      t('system_admin.biz_expenses_total'),
                      formatMoney(detail.counts?.expenses_total),
                    ],
                  ].map(([label, value], index) => (
                    <View
                      key={`${label}-${index}`}
                      style={[styles.stat, { borderColor: tc.line }]}
                    >
                      <Text style={[styles.statLabel, { color: tc.muted }]}>
                        {label}
                      </Text>
                      {value !== '' ? (
                        <Text style={[styles.statValue, { color: tc.ink }]}>
                          {value === null || value === undefined
                            ? '—'
                            : String(value)}
                        </Text>
                      ) : null}
                    </View>
                  ))}
                </View>

                <View style={[styles.detailRow, { borderBottomColor: tc.line }]}>
                  <Text style={[styles.detailLabel, { color: tc.muted }]}>
                    {t('system_admin.biz_owner')}
                  </Text>
                  <Text style={[styles.detailValue, { color: tc.ink }]}>
                    {detail.owner_name ||
                      detail.owner_email ||
                      t('system_admin.biz_no_owner')}
                    {detail.owner_name && detail.owner_email
                      ? ` · ${detail.owner_email}`
                      : ''}
                  </Text>
                </View>
                <View style={[styles.detailRow, { borderBottomColor: tc.line }]}>
                  <Text style={[styles.detailLabel, { color: tc.muted }]}>
                    {t('system_admin.biz_location')}
                  </Text>
                  <Text style={[styles.detailValue, { color: tc.ink }]}>
                    {detail.business_location || t('system_admin.biz_not_set')}
                  </Text>
                </View>
                <View style={[styles.detailRow, { borderBottomColor: tc.line }]}>
                  <Text style={[styles.detailLabel, { color: tc.muted }]}>
                    {t('system_admin.biz_type')}
                  </Text>
                  <Text style={[styles.detailValue, { color: tc.ink }]}>
                    {detail.business_type || t('system_admin.biz_not_set')}
                  </Text>
                </View>
                <View style={[styles.detailRow, { borderBottomColor: tc.line }]}>
                  <Text style={[styles.detailLabel, { color: tc.muted }]}>
                    {t('system_admin.biz_created')}
                  </Text>
                  <Text style={[styles.detailValue, { color: tc.ink }]}>
                    {formatDate(detail.created_at)}
                  </Text>
                </View>
                <View style={[styles.detailRow, { borderBottomColor: tc.line }]}>
                  <Text style={[styles.detailLabel, { color: tc.muted }]}>
                    {t('system_admin.biz_public')}
                  </Text>
                  <Text style={[styles.detailValue, { color: tc.ink }]}>
                    {detail.is_public
                      ? t('system_admin.biz_yes')
                      : t('system_admin.biz_no')}
                  </Text>
                </View>

                <View style={styles.sectionHead}>
                  <Text style={[styles.sectionTitle, { color: tc.ink }]}>
                    {t('system_admin.biz_users').replace(
                      '{n}',
                      String((detail.users || []).length),
                    )}
                  </Text>
                </View>

                {(detail.users || []).length === 0 ? (
                  <Text style={[styles.emptyText, { color: tc.muted }]}>
                    {t('system_admin.biz_no_users')}
                  </Text>
                ) : (
                  (detail.users || []).map((user) => (
                    <View
                      key={user.id}
                      style={[styles.userRow, { borderColor: tc.line }]}
                    >
                      <View style={[styles.userAvatar, { backgroundColor: tc.tint }]}>
                        <Text style={[styles.userAvatarText, { color: tc.primary }]}>
                          {String(user.full_name || user.email || '?')
                            .charAt(0)
                            .toUpperCase()}
                        </Text>
                      </View>
                      <View style={styles.userMain}>
                        <Text
                          style={[styles.userName, { color: tc.ink }]}
                          numberOfLines={1}
                        >
                          {user.full_name || user.email || '—'}
                        </Text>
                        <Text
                          style={[styles.userEmail, { color: tc.muted }]}
                          numberOfLines={1}
                        >
                          {user.email || ''}
                        </Text>
                      </View>
                      <View style={styles.userBadges}>
                        <Text style={[styles.userRole, { color: tc.muted }]}>
                          {user.role || '—'}
                        </Text>
                        <Text style={[styles.userStatus, { color: tc.muted }]}>
                          {user.status || '—'}
                        </Text>
                      </View>
                    </View>
                  ))
                )}

                <View style={styles.modalActions}>
                  <TouchableOpacity
                    style={[styles.modalAction, { borderColor: '#bbdefb' }]}
                    onPress={() => openActivity(detail)}
                  >
                    <Ionicons name="pulse-outline" size={17} color="#1976d2" />
                    <Text style={[styles.modalActionText, { color: '#1976d2' }]}>
                      {t('system_admin.biz_action_activity')}
                    </Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[
                      styles.modalAction,
                      { borderColor: detail.is_blocked ? '#a7f3d0' : '#fecaca' },
                    ]}
                    onPress={() => toggleBlock(detail)}
                  >
                    <Ionicons
                      name={detail.is_blocked ? 'lock-open-outline' : 'lock-closed-outline'}
                      size={17}
                      color={detail.is_blocked ? '#047857' : '#dc2626'}
                    />
                    <Text
                      style={[
                        styles.modalActionText,
                        { color: detail.is_blocked ? '#047857' : '#dc2626' },
                      ]}
                    >
                      {t(
                        detail.is_blocked
                          ? 'system_admin.biz_action_unblock'
                          : 'system_admin.biz_action_block',
                      )}
                    </Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.modalAction, { borderColor: '#f5c6c0' }]}
                    onPress={() => deleteBusiness(detail)}
                  >
                    <Ionicons name="trash-outline" size={17} color="#c0392b" />
                    <Text style={[styles.modalActionText, { color: '#c0392b' }]}>
                      {t('system_admin.biz_action_delete')}
                    </Text>
                  </TouchableOpacity>
                </View>
              </ScrollView>
            ) : null}
          </View>
        </View>
      </Modal>

      <Modal
        visible={activity !== null}
        transparent
        animationType="slide"
        onRequestClose={() => setActivity(null)}
      >
        <View style={styles.modalOverlay}>
          <View style={[styles.modalSheet, { backgroundColor: tc.card }]}>
            <View style={[styles.modalHeader, { borderBottomColor: tc.line }]}>
              <View style={styles.activityTitleWrap}>
                <Text style={[styles.modalTitle, { color: tc.ink }]}>
                  {t('system_admin.biz_activity_title')}
                </Text>
                <Text
                  style={[styles.modalSubtitle, { color: tc.muted }]}
                  numberOfLines={1}
                >
                  {activity?.business_name || ''}
                </Text>
              </View>
              <TouchableOpacity onPress={() => setActivity(null)}>
                <Ionicons name="close" size={24} color={tc.muted} />
              </TouchableOpacity>
            </View>

            {activityLoading ? (
              <View style={styles.modalLoading}>
                <ActivityIndicator size="large" color={tc.primary} />
              </View>
            ) : activityError ? (
              <View style={styles.modalLoading}>
                <Text style={[styles.errorText, { color: tc.muted }]}>
                  {activityError}
                </Text>
              </View>
            ) : logs.length === 0 ? (
              <View style={styles.modalLoading}>
                <Ionicons name="time-outline" size={40} color={tc.muted2} />
                <Text style={[styles.emptyText, { color: tc.muted }]}>
                  {t('system_admin.biz_activity_empty')}
                </Text>
              </View>
            ) : (
              <ScrollView contentContainerStyle={styles.modalBody}>
                {logs.map((log) => (
                  <View
                    key={log.id}
                    style={[styles.logCard, { borderColor: tc.line }]}
                  >
                    <View style={styles.logHead}>
                      <Text
                        style={[styles.logAction, { color: tc.ink }]}
                        numberOfLines={1}
                      >
                        {log.action || '—'}
                      </Text>
                      <Text style={[styles.logTime, { color: tc.muted2 }]}>
                        {formatTimeAgo(log.created_at)}
                      </Text>
                    </View>
                    {log.endpoint ? (
                      <Text
                        style={[styles.logEndpoint, { color: tc.primary }]}
                        numberOfLines={1}
                      >
                        {log.endpoint}
                      </Text>
                    ) : null}
                    <Text style={[styles.logMeta, { color: tc.muted }]}>
                      {log.user?.full_name || log.user?.email || ''}
                      {log.user ? ' · ' : ''}
                      {log.status || '—'}
                    </Text>
                  </View>
                ))}
              </ScrollView>
            )}
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

const makeStyles = (tc: ThemeColors) =>
  StyleSheet.create({
    container: { flex: 1 },
    loadingWrap: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    loadingText: { fontSize: 13, marginTop: 12 },
    header: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 12,
      paddingHorizontal: 16,
      paddingVertical: 14,
      borderBottomWidth: 1,
    },
    headerText: { flex: 1 },
    title: { fontSize: 18, fontWeight: '800' },
    subtitle: { fontSize: 12.5, marginTop: 2 },
    refreshBtn: {
      width: 42,
      height: 42,
      borderRadius: 12,
      borderWidth: 1,
      alignItems: 'center',
      justifyContent: 'center',
    },
    content: { padding: 16, paddingBottom: 40, gap: 10 },
    searchBox: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 8,
      borderWidth: 1,
      borderRadius: 12,
      paddingHorizontal: 12,
    },
    searchInput: { flex: 1, paddingVertical: 11, fontSize: 14 },
    filters: { gap: 8, paddingVertical: 2 },
    filterChip: {
      borderWidth: 1,
      borderRadius: 999,
      paddingHorizontal: 13,
      paddingVertical: 7,
    },
    filterText: { fontSize: 12.5, fontWeight: '700' },
    errorBox: { alignItems: 'center', paddingVertical: 36, gap: 8 },
    errorText: { fontSize: 13, textAlign: 'center', lineHeight: 19 },
    retryText: { fontSize: 13, fontWeight: '800' },
    empty: { alignItems: 'center', paddingVertical: 40, gap: 8 },
    emptyText: { fontSize: 13, textAlign: 'center', lineHeight: 19 },
    card: { borderWidth: 1, borderRadius: 15, padding: 13, gap: 10 },
    cardHead: { flexDirection: 'row', alignItems: 'center', gap: 10 },
    avatar: {
      width: 42,
      height: 42,
      borderRadius: 13,
      alignItems: 'center',
      justifyContent: 'center',
      overflow: 'hidden',
    },
    avatarImage: { width: '100%', height: '100%' },
    avatarText: { fontSize: 17, fontWeight: '800' },
    cardTitleWrap: { flex: 1, minWidth: 0 },
    cardTitle: { fontSize: 14.5, fontWeight: '800' },
    cardMeta: { fontSize: 11.5, marginTop: 2 },
    statusBadge: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 4,
      borderRadius: 999,
      paddingHorizontal: 8,
      paddingVertical: 4,
    },
    statusDot: { width: 6, height: 6, borderRadius: 3 },
    statusText: { fontSize: 10.5, fontWeight: '800' },
    ownerRow: { flexDirection: 'row', alignItems: 'center', gap: 7 },
    ownerLabel: { fontSize: 11.5 },
    ownerMain: { flex: 1, minWidth: 0 },
    ownerValue: { fontSize: 12, fontWeight: '600' },
    ownerEmail: { fontSize: 10.5, marginTop: 1 },
    usersCount: { fontSize: 11.5 },
    actions: { flexDirection: 'row', flexWrap: 'wrap', gap: 7 },
    action: {
      flexGrow: 1,
      flexBasis: '45%',
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 6,
      borderWidth: 1,
      borderRadius: 9,
      paddingVertical: 8,
    },
    actionText: { fontSize: 11.5, fontWeight: '700' },
    modalOverlay: {
      flex: 1,
      backgroundColor: 'rgba(15, 23, 42, 0.5)',
      justifyContent: 'flex-end',
    },
    modalSheet: {
      maxHeight: '90%',
      borderTopLeftRadius: 22,
      borderTopRightRadius: 22,
    },
    modalHeader: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: 12,
      padding: 18,
      borderBottomWidth: 1,
    },
    modalTitle: { fontSize: 17, fontWeight: '800' },
    modalSubtitle: { fontSize: 12, marginTop: 2 },
    activityTitleWrap: { flex: 1, minWidth: 0 },
    modalLoading: { padding: 40, alignItems: 'center', gap: 8 },
    modalBody: { padding: 18, paddingBottom: 34, gap: 10 },
    detailIdentity: { alignItems: 'center', marginBottom: 4 },
    detailAvatar: {
      width: 72,
      height: 72,
      borderRadius: 20,
      alignItems: 'center',
      justifyContent: 'center',
      marginBottom: 10,
      overflow: 'hidden',
    },
    detailAvatarText: { fontSize: 28, fontWeight: '800' },
    detailName: { fontSize: 18, fontWeight: '800', textAlign: 'center' },
    detailMeta: { fontSize: 12.5, marginTop: 3, textAlign: 'center' },
    detailBadges: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 6,
      marginTop: 8,
      flexWrap: 'wrap',
    },
    statGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
    stat: {
      flexGrow: 1,
      flexBasis: '45%',
      borderWidth: 1,
      borderRadius: 11,
      padding: 10,
    },
    statLabel: {
      fontSize: 10,
      fontWeight: '800',
      letterSpacing: 0.3,
      textTransform: 'uppercase',
    },
    statValue: { fontSize: 14, fontWeight: '800', marginTop: 3 },
    detailRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 10,
      paddingVertical: 9,
      borderBottomWidth: 1,
    },
    detailLabel: { width: 110, fontSize: 12.5 },
    detailValue: { flex: 1, fontSize: 13, fontWeight: '600' },
    sectionHead: { marginTop: 8 },
    sectionTitle: { fontSize: 13, fontWeight: '800' },
    userRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 9,
      borderWidth: 1,
      borderRadius: 11,
      padding: 9,
    },
    userAvatar: {
      width: 34,
      height: 34,
      borderRadius: 17,
      alignItems: 'center',
      justifyContent: 'center',
    },
    userAvatarText: { fontSize: 13, fontWeight: '800' },
    userMain: { flex: 1, minWidth: 0 },
    userName: { fontSize: 13, fontWeight: '700' },
    userEmail: { fontSize: 11, marginTop: 1 },
    userBadges: { alignItems: 'flex-end', gap: 2 },
    userRole: { fontSize: 10.5, fontWeight: '700' },
    userStatus: { fontSize: 10 },
    modalActions: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 8 },
    modalAction: {
      flexGrow: 1,
      flexBasis: '45%',
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 6,
      borderWidth: 1,
      borderRadius: 10,
      paddingVertical: 10,
    },
    modalActionText: { fontSize: 12, fontWeight: '700' },
    logCard: { borderWidth: 1, borderRadius: 11, padding: 11, gap: 4 },
    logHead: { flexDirection: 'row', alignItems: 'center', gap: 8 },
    logAction: { flex: 1, fontSize: 12.5, fontWeight: '800' },
    logTime: { fontSize: 10.5 },
    logEndpoint: { fontSize: 10.5 },
    logMeta: { fontSize: 11 },
  });
