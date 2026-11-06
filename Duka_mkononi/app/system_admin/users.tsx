// Duka_mkononi/app/system_admin/users.tsx
//
// Mobile twin of resources/views/system_admin/users.blade.php.
//
// Header (title, live subtitle, refresh), search + role chips, then one row per
// user with identity / role / status / icon actions. Tapping the eye opens the
// same detail sheet as the website (phone, business, location, registered,
// last seen, status) with activity / block-enable / delete actions.
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useRouter } from 'expo-router';
import React, { useEffect, useMemo, useState } from 'react';
import {
  Alert,
  FlatList,
  Image,
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
import { getCache, setCache } from '../../db/cache';
import { requireNetwork, fetchWithTimeout } from '../../lib/network';
import { useTheme, ThemeColors } from '../../lib/theme';

interface User {
  id: string;
  email: string;
  role: string;
  status?: string;
  full_name?: string;
  phone?: string;
  business_name?: string;
  business_location?: string;
  business_logo_url?: string;
  created_at?: string;
  last_seen?: string;
}

const ROLE_COLORS: Record<string, string> = {
  admin: '#9b59b6',
  super_admin: '#7c3aed',
  system_admin: '#7c3aed',
  seller: '#2563eb',
  client: '#059669',
  customer: '#059669',
};

const STATUS_COLORS: Record<string, string> = {
  approved: '#059669',
  pending: '#b45309',
  rejected: '#dc2626',
  suspended: '#ea580c',
  inactive: '#64748b',
};

export default function SystemAdminUsers() {
  const { colors: tc } = useTheme();
  const styles = useMemo(() => makeStyles(tc), [tc]);
  const { t, lang } = useLang();
  const { signOut } = useSession();
  const router = useRouter();

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [users, setUsers] = useState<User[]>([]);
  const [onlineIds, setOnlineIds] = useState<Set<string>>(new Set());
  const [searchQuery, setSearchQuery] = useState('');
  const [roleFilter, setRoleFilter] = useState<'all' | 'admin' | 'seller' | 'client'>('all');
  const [selectedUser, setSelectedUser] = useState<User | null>(null);

  useEffect(() => {
    init();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [lang]);

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
      await loadUsers();
    } catch (error) {
      console.error('Error initializing users page:', error);
      setLoading(false);
    }
  };

  const loadUsers = async () => {
    setLoading(true);
    await Promise.all([fetchUsers(), fetchOnlineUsers()]);
    setLoading(false);
    setRefreshing(false);
  };

  const fetchUsers = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;
      const cached = await getCache<User[]>('system:users');
      if (cached) setUsers(cached);

      const res = await fetchWithTimeout(`${API_BASE_URL}/api/admin/users?scope=platform`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      const list = data.users || (Array.isArray(data) ? data : []);
      setUsers(Array.isArray(list) ? list : []);
      setCache('system:users', Array.isArray(list) ? list : []).catch(() => {});
    } catch {
      const cached = await getCache<User[]>('system:users');
      if (cached) setUsers(cached);
    }
  };

  const fetchOnlineUsers = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;
      const cached = await getCache<any[]>('admin:online-users');
      if (cached) setOnlineIds(new Set(cached.map((u) => String(u.id))));

      const res = await fetchWithTimeout(`${API_BASE_URL}/api/admin/online-users`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (!res.ok) return;
      const data = await res.json();
      const list = data.online_users || [];
      setOnlineIds(new Set(list.map((u: any) => String(u.id))));
      setCache('admin:online-users', list).catch(() => {});
    } catch {
      // keep the previous online list
    }
  };

  const filteredUsers = useMemo(() => {
    const q = searchQuery.trim().toLowerCase();
    return users.filter((u) => {
      if (roleFilter !== 'all' && u.role !== roleFilter) return false;
      if (!q) return true;
      return (
        String(u.full_name || '').toLowerCase().includes(q) ||
        String(u.email || '').toLowerCase().includes(q)
      );
    });
  }, [users, roleFilter, searchQuery]);

  const roleCounts = useMemo(
    () => ({
      all: users.length,
      admin: users.filter((u) => u.role === 'admin').length,
      seller: users.filter((u) => u.role === 'seller').length,
      client: users.filter((u) => u.role === 'client').length,
    }),
    [users],
  );

  const roleColor = (role?: string) => ROLE_COLORS[role || ''] || '#64748b';
  const statusColor = (status?: string) => STATUS_COLORS[status || ''] || '#64748b';

  const formatDate = (value?: string) => {
    if (!value) return t('system_admin.not_set');
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return t('system_admin.not_set');
    return date.toLocaleDateString(LOCALE_TAGS[lang] || 'sw-TZ', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
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

  const toggleStatus = (user: User) => {
    const nextStatus = user.status === 'approved' ? 'pending' : 'approved';
    const enabling = nextStatus === 'approved';
    const name = user.full_name || user.email || t('system_admin.user_generic');
    Alert.alert(
      enabling ? t('system_admin.action_enable') : t('system_admin.action_block'),
      t(enabling ? 'system_admin.user_confirm_enable' : 'system_admin.user_confirm_block', {
        name,
      }),
      [
        { text: t('app.cancel'), style: 'cancel' },
        {
          text: enabling ? t('system_admin.action_enable') : t('system_admin.action_block'),
          style: enabling ? 'default' : 'destructive',
          onPress: async () => {
            try {
              if (!(await requireNetwork())) return;
              const token = await AsyncStorage.getItem('userToken');
              const res = await fetchWithTimeout(
                `${API_BASE_URL}/api/admin/users/${encodeURIComponent(user.id)}/status`,
                {
                  method: 'PUT',
                  headers: {
                    Authorization: `Bearer ${token}`,
                    'Content-Type': 'application/json',
                  },
                  body: JSON.stringify({ status: nextStatus }),
                },
              );
              if (res.ok) {
                Alert.alert(
                  t('app.success') || 'Success',
                  t(
                    enabling
                      ? 'system_admin.user_status_enabled'
                      : 'system_admin.user_status_blocked',
                    { name },
                  ),
                );
                setSelectedUser(null);
                await loadUsers();
              } else {
                Alert.alert(t('app.error'), t('system_admin.user_status_failed'));
              }
            } catch {
              Alert.alert(t('app.error'), t('system_admin.err_network'));
            }
          },
        },
      ],
    );
  };

  const deleteUser = (user: User) => {
    const name = user.full_name || user.email || t('system_admin.user_generic');
    Alert.alert(
      t('system_admin.delete_user_title'),
      t('system_admin.delete_user_confirm', { name }),
      [
        { text: t('app.cancel'), style: 'cancel' },
        {
          text: t('system_admin.action_delete'),
          style: 'destructive',
          onPress: async () => {
            try {
              if (!(await requireNetwork())) return;
              const token = await AsyncStorage.getItem('userToken');
              const res = await fetchWithTimeout(
                `${API_BASE_URL}/api/admin/users/${encodeURIComponent(user.id)}`,
                {
                  method: 'DELETE',
                  headers: { Authorization: `Bearer ${token}` },
                },
              );
              const data = await res.json().catch(() => ({}));
              if (res.ok) {
                Alert.alert(
                  t('app.success') || 'Success',
                  t('system_admin.delete_user_success', { name }),
                );
                setSelectedUser(null);
                await loadUsers();
              } else {
                Alert.alert(
                  t('app.error'),
                  data.error || t('system_admin.delete_user_failed'),
                );
              }
            } catch {
              Alert.alert(t('app.error'), t('system_admin.err_network'));
            }
          },
        },
      ],
    );
  };

  const openActivity = (user: User) => {
    setSelectedUser(null);
    router.push({
      pathname: '/system_admin/logs' as any,
      params: { user: user.id },
    });
  };

  const avatarInner = (user: User) => {
    if (user.business_logo_url) {
      return <Image source={{ uri: user.business_logo_url }} style={styles.avatarImage} />;
    }
    const source = user.full_name || user.email || '?';
    return (
      <Text style={[styles.avatarText, { color: '#4338ca' }]}>
        {source.charAt(0).toUpperCase()}
      </Text>
    );
  };

  const roleFilterOptions = useMemo(
    () => [
      { id: 'all' as const, label: t('system_admin.filter_all'), count: roleCounts.all },
      { id: 'admin' as const, label: t('system_admin.administrators'), count: roleCounts.admin },
      { id: 'seller' as const, label: t('system_admin.sellers_label'), count: roleCounts.seller },
      { id: 'client' as const, label: t('system_admin.customers_label'), count: roleCounts.client },
    ],
    [roleCounts, t],
  );

  const renderRow = ({ item }: { item: User }) => {
    const isOnline = onlineIds.has(String(item.id));
    const color = roleColor(item.role);
    const sColor = statusColor(item.status);
    const canBlock = item.status === 'approved';
    return (
      <View style={[styles.row, { borderBottomColor: tc.line2 }]}>
        <View style={styles.rowMain}>
          <View style={[styles.avatar, { backgroundColor: '#e0e7ff' }]}>
            {avatarInner(item)}
            <View
              style={[
                styles.presence,
                { backgroundColor: isOnline ? tc.success : '#cbd5e1', borderColor: tc.card },
              ]}
            />
          </View>
          <View style={styles.identity}>
            <View style={styles.nameRow}>
              <Text numberOfLines={1} style={[styles.name, { color: tc.ink }]}>
                {item.full_name || t('system_admin.without_name')}
              </Text>
              {isOnline ? (
                <View style={styles.onlineBadge}>
                  <Text style={styles.onlineBadgeText}>{t('system_admin.online')}</Text>
                </View>
              ) : null}
            </View>
            <Text numberOfLines={1} style={[styles.email, { color: tc.muted }]}>
              {item.email}
            </Text>
          </View>
        </View>

        <View style={styles.badgeRow}>
          <View
            style={[
              styles.badge,
              { backgroundColor: `${color}18`, borderColor: `${color}33` },
            ]}
          >
            <Text style={[styles.badgeText, { color }]}>{item.role || '-'}</Text>
          </View>
          <View
            style={[
              styles.badge,
              { backgroundColor: `${sColor}14`, borderColor: `${sColor}33` },
            ]}
          >
            <View style={[styles.statusDot, { backgroundColor: sColor }]} />
            <Text style={[styles.badgeText, { color: sColor }]}>{item.status || '-'}</Text>
          </View>
        </View>

        <View style={styles.rowActions}>
          <TouchableOpacity
            onPress={() => setSelectedUser(item)}
            style={[styles.iconBtn, { borderColor: tc.line2 }]}
            accessibilityLabel={t('system_admin.action_details')}
          >
            <Ionicons name="eye-outline" size={17} color={tc.muted} />
          </TouchableOpacity>
          <TouchableOpacity
            onPress={() => openActivity(item)}
            style={[styles.iconBtn, { borderColor: tc.line2 }]}
            accessibilityLabel={t('system_admin.action_activity')}
          >
            <Ionicons name="pulse-outline" size={17} color={tc.primary} />
          </TouchableOpacity>
          <TouchableOpacity
            onPress={() => toggleStatus(item)}
            style={[
              styles.iconBtn,
              { borderColor: canBlock ? '#fecaca' : '#bbf7d0' },
            ]}
            accessibilityLabel={
              canBlock
                ? t('system_admin.action_block')
                : t('system_admin.action_enable')
            }
          >
            <Ionicons
              name={canBlock ? 'lock-closed-outline' : 'lock-open-outline'}
              size={17}
              color={canBlock ? tc.danger : tc.success}
            />
          </TouchableOpacity>
          <TouchableOpacity
            onPress={() => deleteUser(item)}
            style={[styles.iconBtn, { borderColor: '#fecaca' }]}
            accessibilityLabel={t('system_admin.action_delete')}
          >
            <Ionicons name="trash-outline" size={17} color={tc.danger} />
          </TouchableOpacity>
        </View>
      </View>
    );
  };

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: tc.page }]}>
      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); loadUsers(); }} />}
        showsVerticalScrollIndicator={false}
        keyboardShouldPersistTaps="handled"
      >
        <View style={styles.pageHead}>
          <View style={{ flex: 1 }}>
            <Text style={[styles.pageTitle, { color: tc.ink }]}>
              {t('system_admin.nav_users')}
            </Text>
            <Text style={[styles.pageSub, { color: tc.muted }]}>
              {loading
                ? t('system_admin.loading_users')
                : t('system_admin.users_subtitle_count', {
                    total: users.length,
                    online: onlineIds.size,
                  })}
            </Text>
          </View>
          <TouchableOpacity
            onPress={() => loadUsers()}
            style={[styles.refreshBtn, { borderColor: tc.line2, backgroundColor: tc.card }]}
          >
            <Ionicons name="refresh" size={15} color={tc.ink} />
            <Text style={[styles.refreshText, { color: tc.ink }]}>
              {t('system_admin.refresh')}
            </Text>
          </TouchableOpacity>
        </View>

        <View style={styles.toolbar}>
          <View style={[styles.searchBox, { backgroundColor: tc.card, borderColor: tc.line2 }]}>
            <Ionicons name="search" size={16} color={tc.muted2} />
            <TextInput
              value={searchQuery}
              onChangeText={setSearchQuery}
              placeholder={t('system_admin.search_users')}
              placeholderTextColor={tc.muted2}
              style={[styles.searchInput, { color: tc.ink }]}
              autoCapitalize="none"
            />
            {searchQuery.length > 0 ? (
              <TouchableOpacity onPress={() => setSearchQuery('')}>
                <Ionicons name="close-circle" size={17} color={tc.muted2} />
              </TouchableOpacity>
            ) : null}
          </View>
        </View>

        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chipRow}>
          {roleFilterOptions.map((option) => {
            const active = roleFilter === option.id;
            return (
              <TouchableOpacity
                key={option.id}
                onPress={() => setRoleFilter(option.id)}
                style={[
                  styles.chip,
                  {
                    backgroundColor: active ? tc.primary : tc.card,
                    borderColor: active ? tc.primary : tc.line2,
                  },
                ]}
              >
                <Text style={[styles.chipText, { color: active ? '#fff' : tc.muted }]}>
                  {option.label} ({option.count})
                </Text>
              </TouchableOpacity>
            );
          })}
        </ScrollView>

        <View style={[styles.listCard, { backgroundColor: tc.card }]}>
          <View style={[styles.listHead, { borderBottomColor: tc.line2 }]}>
            <Text style={[styles.listHeadText, { color: tc.muted }]}>
              {t('system_admin.nav_users')}
            </Text>
            <Text style={[styles.listHeadText, { color: tc.muted }]}>
              {t('system_admin.role_label')}
            </Text>
            <Text style={[styles.listHeadText, { color: tc.muted }]}>
              {t('system_admin.status_label').replace(':', '')}
            </Text>
            <Text style={[styles.listHeadText, { textAlign: 'right', color: tc.muted }]}>
              {t('system_admin.select_action') || 'Actions'}
            </Text>
          </View>

          {loading && users.length === 0 ? (
            <View style={styles.loadingBox}>
              <SmartCartLoader size="compact" color={tc.primary} />
            </View>
          ) : filteredUsers.length > 0 ? (
            <FlatList
              data={filteredUsers}
              renderItem={renderRow}
              keyExtractor={(item) => String(item.id)}
              scrollEnabled={false}
            />
          ) : (
            <View style={styles.emptyState}>
              <Ionicons name="people-outline" size={36} color={tc.muted2} />
              <Text style={[styles.emptyText, { color: tc.muted2 }]}>
                {t('system_admin.no_users_found')}
              </Text>
            </View>
          )}
        </View>
      </ScrollView>

      <Modal
        animationType="slide"
        transparent
        visible={!!selectedUser}
        onRequestClose={() => setSelectedUser(null)}
      >
        <View style={styles.modalOverlay}>
          <View style={[styles.modalContent, { backgroundColor: tc.card }]}>
            {selectedUser ? (
              <>
                <View style={[styles.modalHeader, { borderBottomColor: tc.line2 }]}>
                  <Text style={[styles.modalTitle, { color: tc.ink }]}>
                    {t('system_admin.user_details')}
                  </Text>
                  <TouchableOpacity onPress={() => setSelectedUser(null)}>
                    <Ionicons name="close" size={22} color={tc.muted} />
                  </TouchableOpacity>
                </View>
                <ScrollView contentContainerStyle={styles.modalBody}>
                  <View style={[styles.modalAvatar, { backgroundColor: '#e0e7ff' }]}>
                    {avatarInner(selectedUser)}
                  </View>
                  <View style={styles.modalIdentity}>
                    <Text style={[styles.modalName, { color: tc.ink }]}>
                      {selectedUser.full_name || t('system_admin.without_name')}
                      {onlineIds.has(String(selectedUser.id)) ? (
                        <Text style={styles.onlineBadgeText}>
                          {'  '}
                          {t('system_admin.online')}
                        </Text>
                      ) : null}
                    </Text>
                    <Text style={[styles.modalEmail, { color: tc.muted }]}>
                      {selectedUser.email}
                    </Text>
                    <View style={styles.modalBadges}>
                      <View
                        style={[
                          styles.badge,
                          {
                            backgroundColor: `${roleColor(selectedUser.role)}18`,
                            borderColor: `${roleColor(selectedUser.role)}33`,
                          },
                        ]}
                      >
                        <Text style={[styles.badgeText, { color: roleColor(selectedUser.role) }]}>
                          {selectedUser.role || '-'}
                        </Text>
                      </View>
                      <View
                        style={[
                          styles.badge,
                          {
                            backgroundColor: `${statusColor(selectedUser.status)}14`,
                            borderColor: `${statusColor(selectedUser.status)}33`,
                          },
                        ]}
                      >
                        <Text
                          style={[styles.badgeText, { color: statusColor(selectedUser.status) }]}
                        >
                          {selectedUser.status || '-'}
                        </Text>
                      </View>
                    </View>
                  </View>

                  <DetailRow
                    icon="call-outline"
                    label={t('system_admin.phone_label')}
                    value={selectedUser.phone || t('system_admin.not_set')}
                    styles={styles}
                    tc={tc}
                  />
                  <DetailRow
                    icon="business-outline"
                    label={t('system_admin.business_label')}
                    value={selectedUser.business_name || t('system_admin.not_set')}
                    styles={styles}
                    tc={tc}
                  />
                  <DetailRow
                    icon="location-outline"
                    label={t('system_admin.location_label')}
                    value={selectedUser.business_location || t('system_admin.not_set')}
                    styles={styles}
                    tc={tc}
                  />
                  <DetailRow
                    icon="calendar-outline"
                    label={t('system_admin.created_label')}
                    value={formatDate(selectedUser.created_at)}
                    styles={styles}
                    tc={tc}
                  />
                  <DetailRow
                    icon="time-outline"
                    label={t('system_admin.last_seen_label')}
                    value={formatTimeAgo(selectedUser.last_seen)}
                    styles={styles}
                    tc={tc}
                  />
                  <DetailRow
                    icon="wifi-outline"
                    label={t('system_admin.status_label')}
                    value={
                      onlineIds.has(String(selectedUser.id))
                        ? t('system_admin.online')
                        : t('system_admin.offline')
                    }
                    styles={styles}
                    tc={tc}
                  />

                  <View style={styles.modalActions}>
                    <TouchableOpacity
                      onPress={() => openActivity(selectedUser)}
                      style={[styles.modalActionBtn, { borderColor: tc.line2 }]}
                    >
                      <Ionicons name="pulse-outline" size={16} color={tc.primary} />
                      <Text style={[styles.modalActionText, { color: tc.primary }]}>
                        {t('system_admin.action_activity')}
                      </Text>
                    </TouchableOpacity>
                    <TouchableOpacity
                      onPress={() => toggleStatus(selectedUser)}
                      style={[
                        styles.modalActionBtn,
                        {
                          borderColor:
                            selectedUser.status === 'approved' ? '#fecaca' : '#bbf7d0',
                        },
                      ]}
                    >
                      <Ionicons
                        name={
                          selectedUser.status === 'approved'
                            ? 'lock-closed-outline'
                            : 'lock-open-outline'
                        }
                        size={16}
                        color={
                          selectedUser.status === 'approved' ? tc.danger : tc.success
                        }
                      />
                      <Text
                        style={[
                          styles.modalActionText,
                          {
                            color:
                              selectedUser.status === 'approved'
                                ? tc.danger
                                : tc.success,
                          },
                        ]}
                      >
                        {selectedUser.status === 'approved'
                          ? t('system_admin.action_block')
                          : t('system_admin.action_enable')}
                      </Text>
                    </TouchableOpacity>
                    <TouchableOpacity
                      onPress={() => deleteUser(selectedUser)}
                      style={[styles.modalActionBtn, { borderColor: '#fecaca' }]}
                    >
                      <Ionicons name="trash-outline" size={16} color={tc.danger} />
                      <Text style={[styles.modalActionText, { color: tc.danger }]}>
                        {t('system_admin.action_delete')}
                      </Text>
                    </TouchableOpacity>
                  </View>
                </ScrollView>
              </>
            ) : null}
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

function DetailRow({
  icon,
  label,
  value,
  styles,
  tc,
}: {
  icon: keyof typeof Ionicons.glyphMap;
  label: string;
  value: string;
  styles: ReturnType<typeof makeStyles>;
  tc: ThemeColors;
}) {
  return (
    <View style={[styles.detailRow, { borderBottomColor: tc.line }]}>
      <Ionicons name={icon} size={16} color={tc.muted} />
      <Text style={[styles.detailLabel, { color: tc.muted }]}>{label}</Text>
      <Text style={[styles.detailValue, { color: tc.ink }]}>{value}</Text>
    </View>
  );
}

const makeStyles = (tc: ThemeColors) =>
  StyleSheet.create({
    container: { flex: 1 },
    content: { padding: 16, paddingBottom: 40, gap: 14 },
    pageHead: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 12,
    },
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
    toolbar: { flexDirection: 'row', gap: 10 },
    searchBox: {
      flex: 1,
      flexDirection: 'row',
      alignItems: 'center',
      gap: 8,
      borderWidth: 1,
      borderRadius: 10,
      paddingHorizontal: 12,
    },
    searchInput: { flex: 1, paddingVertical: 10, fontSize: 13.5 },
    chipRow: { gap: 8 },
    chip: {
      borderWidth: 1,
      borderRadius: 999,
      paddingHorizontal: 14,
      paddingVertical: 7,
    },
    chipText: { fontSize: 12.5, fontWeight: '700' },
    listCard: { borderRadius: 16, overflow: 'hidden' },
    listHead: {
      flexDirection: 'row',
      alignItems: 'center',
      paddingHorizontal: 14,
      paddingVertical: 10,
      borderBottomWidth: 1,
      gap: 8,
    },
    listHeadText: {
      flex: 1,
      fontSize: 10.5,
      fontWeight: '800',
      textTransform: 'uppercase',
      letterSpacing: 0.4,
    },
    row: {
      paddingHorizontal: 14,
      paddingVertical: 12,
      borderBottomWidth: 1,
      gap: 10,
    },
    rowMain: { flexDirection: 'row', alignItems: 'center', gap: 10 },
    avatar: {
      width: 40,
      height: 40,
      borderRadius: 20,
      alignItems: 'center',
      justifyContent: 'center',
      overflow: 'hidden',
    },
    avatarImage: { width: '100%', height: '100%' },
    avatarText: { fontSize: 15, fontWeight: '800' },
    presence: {
      position: 'absolute',
      right: -1,
      bottom: -1,
      width: 11,
      height: 11,
      borderRadius: 6,
      borderWidth: 2,
    },
    identity: { flex: 1, minWidth: 0 },
    nameRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
    name: { flexShrink: 1, fontSize: 13.5, fontWeight: '700' },
    email: { fontSize: 11.5, marginTop: 2 },
    onlineBadge: {
      backgroundColor: '#dcfce7',
      borderWidth: 1,
      borderColor: '#86efac',
      borderRadius: 999,
      paddingHorizontal: 7,
      paddingVertical: 1,
    },
    onlineBadgeText: {
      fontSize: 9,
      fontWeight: '800',
      color: '#047857',
      textTransform: 'uppercase',
    },
    badgeRow: { flexDirection: 'row', gap: 6, flexWrap: 'wrap' },
    badge: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 4,
      borderWidth: 1,
      borderRadius: 999,
      paddingHorizontal: 8,
      paddingVertical: 2,
      alignSelf: 'flex-start',
    },
    badgeText: { fontSize: 10.5, fontWeight: '700' },
    statusDot: { width: 6, height: 6, borderRadius: 3 },
    rowActions: { flexDirection: 'row', gap: 8, justifyContent: 'flex-end' },
    iconBtn: {
      width: 34,
      height: 34,
      borderRadius: 9,
      borderWidth: 1,
      alignItems: 'center',
      justifyContent: 'center',
    },
    loadingBox: { paddingVertical: 30, alignItems: 'center' },
    emptyState: { paddingVertical: 34, alignItems: 'center', gap: 8 },
    emptyText: { fontSize: 13, textAlign: 'center' },
    modalOverlay: {
      flex: 1,
      backgroundColor: 'rgba(15,23,42,0.55)',
      justifyContent: 'flex-end',
    },
    modalContent: {
      maxHeight: '88%',
      borderTopLeftRadius: 20,
      borderTopRightRadius: 20,
    },
    modalHeader: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      padding: 16,
      borderBottomWidth: 1,
    },
    modalTitle: { fontSize: 15, fontWeight: '800' },
    modalBody: { padding: 18, paddingBottom: 32 },
    modalAvatar: {
      width: 76,
      height: 76,
      borderRadius: 38,
      alignItems: 'center',
      justifyContent: 'center',
      alignSelf: 'center',
      overflow: 'hidden',
    },
    modalIdentity: { alignItems: 'center', marginTop: 12, marginBottom: 12 },
    modalName: { fontSize: 17, fontWeight: '800', textAlign: 'center' },
    modalEmail: { fontSize: 13, marginTop: 2 },
    modalBadges: {
      flexDirection: 'row',
      gap: 6,
      marginTop: 8,
      flexWrap: 'wrap',
      justifyContent: 'center',
    },
    detailRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 10,
      paddingVertical: 11,
      borderBottomWidth: 1,
    },
    detailLabel: { width: 118, fontSize: 12.5 },
    detailValue: { flex: 1, fontSize: 13, fontWeight: '500' },
    modalActions: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      gap: 8,
      marginTop: 16,
    },
    modalActionBtn: {
      flexGrow: 1,
      flexBasis: '30%',
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 6,
      borderWidth: 1,
      borderRadius: 10,
      paddingVertical: 10,
      paddingHorizontal: 10,
    },
    modalActionText: { fontSize: 12.5, fontWeight: '700' },
  });
