// Duka_mkononi/app/system_admin/dashboard.tsx
//
// Mobile twin of resources/views/system_admin/dashboard.blade.php.
//
// Same order as the website: header (title, subtitle, refresh / home / logout),
// the four headline stats, "Users in the last 24 hours", then the system-status
// breakdown. Navigation lives in the shared SystemAdminShell drawer, so this
// screen only owns the dashboard content.
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useRouter } from 'expo-router';
import React, { useEffect, useMemo, useState } from 'react';
import {
  Alert,
  FlatList,
  RefreshControl,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import SmartCartLoader from '../../components/SmartCartLoader';
import { useLang } from '../../context/LanguageContext';
import { useSession } from '../../context/SessionContext';
import { isSystemAdminRole } from '../../constants/session';
import { API_BASE_URL } from '../../constants/api';
import { getCache, setCache } from '../../db/cache';
import { registerLive } from '../../lib/syncer';
import { fetchWithTimeout } from '../../lib/network';
import { useTheme, ThemeColors } from '../../lib/theme';

interface User {
  id: string;
  email: string;
  role: string;
  full_name?: string;
  business_logo_url?: string;
  last_seen?: string;
}

interface SystemStats {
  totalUsers: number;
  totalAdmins: number;
  totalSellers: number;
  totalClients: number;
  activeUsers: number;
  pendingUsers: number;
  todayActivities: number;
  todayRevenue: number;
  onlineUsers: number;
  connectedNow: number;
  timestamp: string;
}

const EMPTY_STATS: SystemStats = {
  totalUsers: 0,
  totalAdmins: 0,
  totalSellers: 0,
  totalClients: 0,
  activeUsers: 0,
  pendingUsers: 0,
  todayActivities: 0,
  todayRevenue: 0,
  onlineUsers: 0,
  connectedNow: 0,
  timestamp: new Date().toISOString(),
};

const ROLE_COLORS: Record<string, string> = {
  admin: '#9b59b6',
  super_admin: '#7c3aed',
  system_admin: '#7c3aed',
  seller: '#2563eb',
  client: '#059669',
  customer: '#059669',
};

export default function SystemAdminDashboard() {
  const { colors: tc } = useTheme();
  const styles = useMemo(() => makeStyles(tc), [tc]);
  const { t, lang } = useLang();
  const { signOut } = useSession();
  const router = useRouter();

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [users, setUsers] = useState<User[]>([]);
  const [onlineUsers, setOnlineUsers] = useState<any[]>([]);
  const [systemStats, setSystemStats] = useState<SystemStats>(EMPTY_STATS);
  const [todayExpanded, setTodayExpanded] = useState(false);

  useEffect(() => {
    initializeDashboard();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [lang]);

  useEffect(() => {
    const stopStats = registerLive<any>(
      'system:stats',
      async () => {
        const token = await AsyncStorage.getItem('userToken');
        if (!token) throw new Error('no token');
        const res = await fetchWithTimeout(`${API_BASE_URL}/api/admin/stats`, {
          headers: {
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
          },
        });
        if (!res.ok) throw new Error('sync failed');
        const data = await res.json();
        return normalizeStats(data);
      },
      (data) => {
        setSystemStats(data);
        setLoading(false);
      },
    );
    const stopOnline = registerLive<any>(
      'admin:online-users',
      async () => {
        const token = await AsyncStorage.getItem('userToken');
        if (!token) throw new Error('no token');
        const res = await fetchWithTimeout(`${API_BASE_URL}/api/admin/online-users`, {
          headers: {
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
          },
        });
        if (!res.ok) throw new Error('sync failed');
        const data = await res.json();
        return data.online_users || [];
      },
      (data) => {
        setOnlineUsers(data);
        setLoading(false);
      },
    );
    return () => {
      stopStats();
      stopOnline();
    };
  }, [lang]);

  const normalizeStats = (data: any): SystemStats => ({
    totalUsers: data.totalUsers || 0,
    totalAdmins: data.totalAdmins || 0,
    totalSellers: data.totalSellers || 0,
    totalClients: data.totalClients || 0,
    activeUsers: data.activeUsers || 0,
    pendingUsers: data.pendingUsers || 0,
    todayActivities: data.todayActivities || 0,
    todayRevenue: data.todaySales || data.todayRevenue || 0,
    onlineUsers: data.onlineUsers || 0,
    connectedNow: data.connectedNow || 0,
    timestamp: data.timestamp || new Date().toISOString(),
  });

  const initializeDashboard = async () => {
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
        Alert.alert(t('system_admin.access_denied'), t('system_admin.admin_required'));
        router.back();
        return;
      }

      await loadAllData();
    } catch (error) {
      console.error('Error initializing dashboard:', error);
      Alert.alert(t('app.error'), t('system_admin.init_error'));
      setLoading(false);
    }
  };

  const loadAllData = async () => {
    setLoading(true);
    await Promise.all([fetchUsers(), fetchSystemStats(), fetchOnlineUsers()]);
    setLoading(false);
    setRefreshing(false);
  };

  const fetchUsers = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;

      const cached = await getCache<any[]>('system:users');
      if (cached) setUsers(cached);

      const response = await fetchWithTimeout(`${API_BASE_URL}/api/admin/users?scope=platform`, {
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.json();
      const list = data.users || (Array.isArray(data) ? data : []);
      setUsers(Array.isArray(list) ? list : []);
      setCache('system:users', list).catch(() => {});
    } catch (error) {
      console.error('Error fetching users:', error);
      const cached = await getCache<any[]>('system:users');
      if (cached) setUsers(cached);
    }
  };

  const fetchSystemStats = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;

      const cached = await getCache<SystemStats>('system:stats');
      if (cached) setSystemStats(cached);

      const response = await fetchWithTimeout(`${API_BASE_URL}/api/admin/stats`, {
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.json();
      const safeData = normalizeStats(data);
      setSystemStats(safeData);
      setCache('system:stats', safeData).catch(() => {});
    } catch (error) {
      console.error('Error fetching system stats:', error);
      const cached = await getCache<SystemStats>('system:stats');
      if (cached) setSystemStats(cached);
    }
  };

  const fetchOnlineUsers = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;

      const cached = await getCache<any[]>('admin:online-users');
      if (cached) setOnlineUsers(cached);

      const response = await fetchWithTimeout(`${API_BASE_URL}/api/admin/online-users`, {
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      });
      if (!response.ok) {
        setOnlineUsers([]);
        return;
      }
      const data = await response.json();
      setOnlineUsers(data.online_users || []);
      setCache('admin:online-users', data.online_users || []).catch(() => {});
    } catch (error) {
      console.error('Error fetching online users:', error);
      const cached = await getCache<any[]>('admin:online-users');
      if (cached) setOnlineUsers(cached);
    }
  };

  const onlineIds = useMemo(
    () => new Set(onlineUsers.map((u) => String(u.id))),
    [onlineUsers],
  );

  const todayUsers = useMemo(() => {
    const start = Date.now() - 24 * 60 * 60 * 1000;
    return users
      .filter((u) => {
        if (!u.last_seen) return false;
        const seen = new Date(u.last_seen).getTime();
        return !Number.isNaN(seen) && seen >= start;
      })
      .sort(
        (a, b) =>
          new Date(b.last_seen as string).getTime() -
          new Date(a.last_seen as string).getTime(),
      );
  }, [users]);

  const visibleTodayUsers = todayExpanded ? todayUsers : todayUsers.slice(0, 5);

  const formatTimeAgo = (dateString?: string): string => {
    if (!dateString) return t('system_admin.log_time_never');
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return t('system_admin.log_time_never');
    const diffMins = Math.floor((Date.now() - date.getTime()) / 60000);
    if (diffMins < 1) return 'Sasa hivi';
    if (diffMins < 60) {
      return t('system_admin.log_time_ago').replace('{n}', String(diffMins));
    }
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) {
      return t('system_admin.log_hours_ago').replace('{n}', String(diffHours));
    }
    return t('system_admin.log_days_ago').replace(
      '{n}',
      String(Math.floor(diffHours / 24)),
    );
  };

  const formatCurrency = (amount: number) =>
    `TZS ${(amount || 0).toLocaleString('en-TZ')}`;

  const roleColor = (role: string) => ROLE_COLORS[role] || '#64748b';

  const handleLogout = () => {
    Alert.alert(t('system_admin.logout_confirm'), undefined, [
      { text: t('app.cancel'), style: 'cancel' },
      {
        text: t('navigation.logout'),
        style: 'destructive',
        onPress: async () => {
          await signOut();
          router.replace('/(tabs)');
        },
      },
    ]);
  };

  const onRefresh = () => {
    setRefreshing(true);
    loadAllData();
  };

  const renderTodayRow = ({ item }: { item: User }) => {
    const isOnline = onlineIds.has(String(item.id));
    const color = roleColor(item.role);
    const letter = (item.full_name || item.email || '?').charAt(0).toUpperCase();
    return (
      <View style={[styles.todayRow, { borderBottomColor: tc.line2 }]}>
        <View style={[styles.todayAvatar, { backgroundColor: '#e0e7ff' }]}>
          <Text style={[styles.todayAvatarText, { color: '#4338ca' }]}>{letter}</Text>
          <View
            style={[
              styles.presence,
              { backgroundColor: isOnline ? '#10b981' : '#cbd5e1', borderColor: tc.card },
            ]}
          />
        </View>
        <View style={styles.todayMeta}>
          <View style={styles.todayNameRow}>
            <Text numberOfLines={1} style={[styles.todayName, { color: tc.ink }]}>
              {item.full_name || item.email}
            </Text>
            {isOnline ? (
              <View style={styles.onlineBadge}>
                <Text style={styles.onlineBadgeText}>{t('system_admin.online')}</Text>
              </View>
            ) : null}
          </View>
          <Text numberOfLines={1} style={[styles.todayEmail, { color: tc.muted }]}>
            {item.email}
          </Text>
        </View>
        <View style={styles.todaySide}>
          <View style={[styles.roleBadge, { backgroundColor: `${color}18`, borderColor: `${color}33` }]}>
            <Text style={[styles.roleText, { color }]}>{item.role || '-'}</Text>
          </View>
          <Text style={[styles.todayLastSeen, { color: tc.muted2 }]}>
            {t('system_admin.last_seen_label')} {formatTimeAgo(item.last_seen)}
          </Text>
        </View>
      </View>
    );
  };

  if (loading && !refreshing) {
    return (
      <View style={[styles.loadingContainer, { backgroundColor: tc.page }]}>
        <SmartCartLoader size="large" color={tc.primary} />
        <Text style={[styles.loadingText, { color: tc.muted }]}>
          {t('system_admin.loading_dashboard')}
        </Text>
      </View>
    );
  }

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: tc.page }]}>
      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        showsVerticalScrollIndicator={false}
      >
        <View style={[styles.header, { backgroundColor: tc.card }]}>
          <View style={styles.headerText}>
            <Text style={[styles.headerTitle, { color: tc.ink }]}>
              {t('system_admin.admin_dashboard_title')}
            </Text>
            <Text style={[styles.headerSubtitle, { color: tc.muted }]}>
              {t('system_admin.real_data')}
            </Text>
          </View>
          <View style={styles.headerActions}>
            <TouchableOpacity
              onPress={onRefresh}
              style={[styles.headerButton, { backgroundColor: tc.page }]}
              accessibilityRole="button"
              accessibilityLabel={t('system_admin.real_data')}
            >
              <Ionicons name="refresh" size={20} color={tc.primary} />
            </TouchableOpacity>
            <TouchableOpacity
              onPress={() => router.navigate('/msimamizi')}
              style={[styles.headerButton, { backgroundColor: tc.page }]}
              accessibilityRole="button"
              accessibilityLabel={t('navigation.home')}
            >
              <Ionicons name="home-outline" size={20} color={tc.ink} />
            </TouchableOpacity>
            <TouchableOpacity
              onPress={handleLogout}
              style={[styles.headerButton, { backgroundColor: tc.page }]}
              accessibilityRole="button"
              accessibilityLabel={t('navigation.logout')}
            >
              <Ionicons name="log-out-outline" size={20} color={tc.danger} />
            </TouchableOpacity>
          </View>
        </View>

        <View style={styles.statsGrid}>
          <View style={[styles.statCard, { backgroundColor: tc.card, borderLeftColor: '#3498db' }]}>
            <View style={[styles.statIcon, { backgroundColor: '#3498db20' }]}>
              <Ionicons name="people" size={18} color="#3498db" />
            </View>
            <Text style={[styles.statValue, { color: tc.ink }]}>{systemStats.totalUsers}</Text>
            <Text style={[styles.statLabel, { color: tc.muted }]}>{t('system_admin.total_users')}</Text>
            <Text style={[styles.statSub, { color: tc.muted2 }]}>
              {systemStats.activeUsers} {t('system_admin.active_users')}
            </Text>
          </View>

          <View style={[styles.statCard, { backgroundColor: tc.card, borderLeftColor: '#2ecc71' }]}>
            <View style={[styles.statIcon, { backgroundColor: '#2ecc7120' }]}>
              <Ionicons name="globe-outline" size={18} color="#2ecc71" />
            </View>
            <Text style={[styles.statValue, { color: tc.ink }]}>{systemStats.onlineUsers}</Text>
            <Text style={[styles.statLabel, { color: tc.muted }]}>{t('system_admin.online_now')}</Text>
            <Text style={[styles.statSub, { color: tc.muted2 }]}>
              {t('system_admin.connected_now').replace('{n}', String(systemStats.connectedNow))}
            </Text>
          </View>

          <View style={[styles.statCard, { backgroundColor: tc.card, borderLeftColor: '#f39c12' }]}>
            <View style={[styles.statIcon, { backgroundColor: '#f39c1220' }]}>
              <Ionicons name="cash-outline" size={18} color="#f39c12" />
            </View>
            <Text style={[styles.statValue, { color: tc.ink }]}>
              {formatCurrency(systemStats.todayRevenue)}
            </Text>
            <Text style={[styles.statLabel, { color: tc.muted }]}>{t('system_admin.today_revenue')}</Text>
            <Text style={[styles.statSub, { color: tc.muted2 }]}>
              {t('system_admin.today_activities').replace('{n}', String(systemStats.todayActivities))}
            </Text>
          </View>

          <View style={[styles.statCard, { backgroundColor: tc.card, borderLeftColor: '#e74c3c' }]}>
            <View style={[styles.statIcon, { backgroundColor: '#e74c3c20' }]}>
              <Ionicons name="hourglass-outline" size={18} color="#e74c3c" />
            </View>
            <Text style={[styles.statValue, { color: tc.ink }]}>{systemStats.pendingUsers}</Text>
            <Text style={[styles.statLabel, { color: tc.muted }]}>{t('system_admin.pending_users')}</Text>
            <Text style={[styles.statSub, { color: tc.muted2 }]}>{t('system_admin.awaiting_approval')}</Text>
          </View>
        </View>

        <View style={[styles.sectionCard, { backgroundColor: tc.card }]}>
          <View style={styles.sectionHeader}>
            <View style={{ flex: 1 }}>
              <View style={styles.sectionTitleRow}>
                <Text style={[styles.sectionTitle, { color: tc.ink }]}>
                  {t('system_admin.today_users_title')}
                </Text>
                <View style={styles.sectionCount}>
                  <Text style={styles.sectionCountText}>{todayUsers.length}</Text>
                </View>
              </View>
              <Text style={[styles.sectionSubtitle, { color: tc.muted }]}>
                {t('system_admin.today_users_sub')}
              </Text>
            </View>
            {todayUsers.length > 5 ? (
              <TouchableOpacity
                onPress={() => setTodayExpanded((v) => !v)}
                style={[styles.viewMoreBtn, { borderColor: tc.line }]}
              >
                <Text style={[styles.viewMoreText, { color: tc.ink }]}>
                  {todayExpanded
                    ? t('system_admin.show_less')
                    : `${t('system_admin.view_more')} (${todayUsers.length - 5})`}
                </Text>
              </TouchableOpacity>
            ) : null}
          </View>

          {visibleTodayUsers.length > 0 ? (
            <FlatList
              data={visibleTodayUsers}
              renderItem={renderTodayRow}
              keyExtractor={(item) => String(item.id)}
              scrollEnabled={false}
            />
          ) : (
            <View style={styles.emptyState}>
              <Text style={[styles.emptyText, { color: tc.muted2 }]}>
                {t('system_admin.today_users_empty')}
              </Text>
            </View>
          )}
        </View>

        <View style={[styles.sectionCard, { backgroundColor: tc.card }]}>
          <Text style={[styles.sectionTitle, { color: tc.ink }]}>
            {t('system_admin.system_status')}
          </Text>
          <View style={styles.statusGrid}>
            <View style={styles.statusCell}>
              <Text style={[styles.statusLabel, { color: tc.muted }]}>
                {t('system_admin.all_people')}
              </Text>
              <Text style={[styles.statusValue, { color: tc.ink }]}>{systemStats.totalUsers}</Text>
            </View>
            <View style={styles.statusCell}>
              <Text style={[styles.statusLabel, { color: tc.muted }]}>
                {t('system_admin.sellers_label')}
              </Text>
              <Text style={[styles.statusValue, { color: tc.ink }]}>{systemStats.totalSellers}</Text>
            </View>
            <View style={styles.statusCell}>
              <Text style={[styles.statusLabel, { color: tc.muted }]}>
                {t('system_admin.customers_label')}
              </Text>
              <Text style={[styles.statusValue, { color: tc.ink }]}>{systemStats.totalClients}</Text>
            </View>
            <View style={styles.statusCell}>
              <Text style={[styles.statusLabel, { color: tc.muted }]}>
                {t('system_admin.administrators')}
              </Text>
              <Text style={[styles.statusValue, { color: tc.ink }]}>{systemStats.totalAdmins}</Text>
            </View>
          </View>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const makeStyles = (tc: ThemeColors) =>
  StyleSheet.create({
    container: { flex: 1 },
    content: { padding: 16, paddingBottom: 40, gap: 16 },
    loadingContainer: {
      flex: 1,
      alignItems: 'center',
      justifyContent: 'center',
      padding: 20,
    },
    loadingText: { marginTop: 12, fontSize: 14, fontWeight: '600' },
    header: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 12,
      borderRadius: 18,
      padding: 16,
    },
    headerText: { flex: 1 },
    headerTitle: { fontSize: 20, fontWeight: '800' },
    headerSubtitle: { fontSize: 12.5, marginTop: 3 },
    headerActions: { flexDirection: 'row', gap: 8 },
    headerButton: {
      width: 38,
      height: 38,
      borderRadius: 19,
      alignItems: 'center',
      justifyContent: 'center',
    },
    statsGrid: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      justifyContent: 'space-between',
      gap: 12,
    },
    statCard: {
      width: '48%',
      borderRadius: 16,
      padding: 14,
      borderLeftWidth: 4,
    },
    statIcon: {
      width: 34,
      height: 34,
      borderRadius: 17,
      alignItems: 'center',
      justifyContent: 'center',
      marginBottom: 10,
    },
    statValue: { fontSize: 22, fontWeight: '800', marginBottom: 2 },
    statLabel: { fontSize: 12, fontWeight: '600' },
    statSub: { fontSize: 10.5, marginTop: 3 },
    sectionCard: { borderRadius: 16, padding: 16 },
    sectionHeader: {
      flexDirection: 'row',
      alignItems: 'flex-start',
      gap: 10,
      marginBottom: 12,
    },
    sectionTitleRow: { flexDirection: 'row', alignItems: 'center' },
    sectionTitle: { fontSize: 15, fontWeight: '800' },
    sectionCount: {
      marginLeft: 8,
      minWidth: 24,
      paddingHorizontal: 8,
      paddingVertical: 1,
      borderRadius: 999,
      backgroundColor: '#eef2ff',
      alignItems: 'center',
      justifyContent: 'center',
    },
    sectionCountText: { fontSize: 11, fontWeight: '800', color: '#4338ca' },
    sectionSubtitle: { fontSize: 12, marginTop: 4 },
    viewMoreBtn: {
      borderWidth: 1,
      borderRadius: 10,
      paddingHorizontal: 12,
      paddingVertical: 7,
    },
    viewMoreText: { fontSize: 12, fontWeight: '700' },
    todayRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 10,
      paddingVertical: 10,
      borderBottomWidth: 1,
    },
    todayAvatar: {
      width: 38,
      height: 38,
      borderRadius: 19,
      alignItems: 'center',
      justifyContent: 'center',
    },
    todayAvatarText: { fontSize: 14, fontWeight: '800' },
    presence: {
      position: 'absolute',
      right: -1,
      bottom: -1,
      width: 11,
      height: 11,
      borderRadius: 6,
      borderWidth: 2,
    },
    todayMeta: { flex: 1, minWidth: 0 },
    todayNameRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
    todayName: { flexShrink: 1, fontSize: 13, fontWeight: '700' },
    todayEmail: { fontSize: 11.5, marginTop: 2 },
    todaySide: { alignItems: 'flex-end', gap: 3 },
    roleBadge: {
      borderWidth: 1,
      borderRadius: 999,
      paddingHorizontal: 8,
      paddingVertical: 2,
    },
    roleText: { fontSize: 10, fontWeight: '700' },
    todayLastSeen: { fontSize: 10.5 },
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
    emptyState: { paddingVertical: 24, alignItems: 'center' },
    emptyText: { fontSize: 13, textAlign: 'center' },
    statusGrid: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      justifyContent: 'space-between',
      marginTop: 12,
      gap: 12,
    },
    statusCell: { width: '46%' },
    statusLabel: { fontSize: 12, fontWeight: '600' },
    statusValue: { fontSize: 20, fontWeight: '800', marginTop: 2 },
  });
