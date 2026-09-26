// C:\Users\HP\myApp\Duka_mkononi\app\system_admin\dashboard.tsx
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useRouter } from 'expo-router';
import React, { useEffect, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    FlatList,
    Modal,
    RefreshControl,
    SafeAreaView,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View
} from 'react-native';
import { useLang } from '../../context/LanguageContext';
import { useSession } from '../../context/SessionContext';

import { API_BASE_URL } from '../../constants/api';
import { getCache, setCache } from '../../db/cache';
import { registerLive, syncNow } from '../../lib/syncer';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';

// Types
interface User {
  id: string;
  email: string;
  role: 'admin' | 'seller' | 'client';
  full_name: string;
  phone: string;
  business_name?: string;
  business_location?: string;
  status: 'pending' | 'approved' | 'rejected' | 'inactive' | 'suspended';
  created_at: string;
  last_seen?: string;
  is_online?: boolean;
}

interface UserLog {
  id: string;
  user_id: string;
  user_email?: string;
  user_full_name?: string;
  action: string;
  endpoint: string;
  details: any;
  ip_address: string;
  status: 'success' | 'failed' | 'partial';
  created_at: string;
  users?: {
    email: string;
    full_name: string;
    role: string;
  };
}

interface SystemStats {
  totalUsers: number;
  totalAdmins: number;
  totalSellers: number;
  totalClients: number;
  activeUsers: number;
  pendingUsers: number;
  reportedPosts: number;
  todayActivities: number;
  todayRevenue: number;
  onlineUsers: number;
  connectedNow: number;
  adminConnected: number;
  webSocketConnected: number;
  timestamp: string;
}

interface OnlineUser {
  id: string;
  email: string;
  full_name: string;
  role: string;
  business_name?: string;
  last_seen: string;
  is_online: boolean;
  has_live_connection?: boolean;
}

export default function SystemAdminDashboard() {
  const { t, lang } = useLang();

  const localeMap: Record<string, string> = {
    sw: 'sw-TZ',
    en: 'en',
    fr: 'fr-FR',
    hi: 'hi-IN',
    ur: 'ur-PK',
    es: 'es-ES',
    de: 'de-DE',
    zh: 'zh-CN',
  };
  const router = useRouter();
  const { signOut } = useSession();
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [user, setUser] = useState<any>(null);
  
  // Tabs state
  const [activeTab, setActiveTab] = useState<'dashboard' | 'users' | 'online' | 'logs'>('dashboard');
  
  // Data states
  const [users, setUsers] = useState<User[]>([]);
  const [userLogs, setUserLogs] = useState<UserLog[]>([]);
  const [systemStats, setSystemStats] = useState<SystemStats>({
    totalUsers: 0,
    totalAdmins: 0,
    totalSellers: 0,
    totalClients: 0,
    activeUsers: 0,
    pendingUsers: 0,
    reportedPosts: 0,
    todayActivities: 0,
    todayRevenue: 0,
    onlineUsers: 0,
    connectedNow: 0,
    adminConnected: 0,
    webSocketConnected: 0,
    timestamp: new Date().toISOString()
  });
  const [onlineUsers, setOnlineUsers] = useState<OnlineUser[]>([]);
  
  // Filter states
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedUserType, setSelectedUserType] = useState<'all' | 'admin' | 'seller' | 'client'>('all');
  const [selectedLogStatus, setSelectedLogStatus] = useState<'all' | 'success' | 'failed'>('all');
  const [selectedTimeRange, setSelectedTimeRange] = useState<'today' | 'week' | 'month'>('today');
  
  // Modal states
  const [userDetailModal, setUserDetailModal] = useState(false);
  const [selectedUser, setSelectedUser] = useState<User | null>(null);
  const [userLogsModal, setUserLogsModal] = useState(false);
  const [userSpecificLogs, setUserSpecificLogs] = useState<UserLog[]>([]);
  
  // WebSocket state
  const [webSocketStatus, setWebSocketStatus] = useState({
    total_connections: 0,
    admin_connections: 0,
    connected: false
  });

  useEffect(() => {
    initializeDashboard();
  }, [lang]);

  useEffect(() => {
    const stopStats = registerLive<any>(
      'system:stats',
      async () => {
        const token = await AsyncStorage.getItem('userToken');
        if (!token) throw new Error('no token');
        const res = await fetchWithTimeout(`${API_BASE_URL}/api/admin/stats`, {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          }
        });
        if (!res.ok) throw new Error('sync failed');
        const data = await res.json();
        return {
          totalUsers: data.totalUsers || 0,
          totalAdmins: data.totalAdmins || 0,
          totalSellers: data.totalSellers || 0,
          totalClients: data.totalClients || 0,
          activeUsers: data.activeUsers || 0,
          pendingUsers: data.pendingUsers || 0,
          reportedPosts: data.reportedPosts || 0,
          todayActivities: data.todayActivities || 0,
          todayRevenue: data.todayRevenue || 0,
          onlineUsers: data.onlineUsers || 0,
          connectedNow: data.connectedNow || 0,
          adminConnected: data.adminConnected || 0,
          webSocketConnected: data.webSocketConnected || 0,
          timestamp: data.timestamp || new Date().toISOString()
        };
      },
      (data) => { setSystemStats(data); setLoading(false); }
    );
    const stopOnline = registerLive<any>(
      'admin:online-users',
      async () => {
        const token = await AsyncStorage.getItem('userToken');
        if (!token) throw new Error('no token');
        const res = await fetchWithTimeout(`${API_BASE_URL}/api/admin/online-users`, {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          }
        });
        if (!res.ok) throw new Error('sync failed');
        const data = await res.json();
        return data.online_users || [];
      },
      (data) => { setOnlineUsers(data); setLoading(false); }
    );
    return () => { stopStats(); stopOnline(); };
  }, [lang]);

  const initializeDashboard = async () => {
    try {
      console.log('📊 System Admin Dashboard Initializing...');
      
      // Check authentication
      const token = await AsyncStorage.getItem('userToken');
      const userDataStr = await AsyncStorage.getItem('userData');
      
      if (!token || !userDataStr) {
        Alert.alert(t('system_admin.auth_required'), t('system_admin.login_again'));
        await signOut();
        router.replace('/(tabs)');
        return;
      }

      const userData = JSON.parse(userDataStr);
      
      // Check if user is admin
      if (userData.role !== 'admin') {
        Alert.alert(t('system_admin.access_denied'), t('system_admin.admin_required'));
        router.back();
        return;
      }
      
      setUser(userData);
      await loadAllData();
      
    } catch (error) {
      console.error('Error initializing dashboard:', error);
      Alert.alert(t('app.error'), t('system_admin.init_error'));
      setLoading(false);
    }
  };

  const loadAllData = async () => {
    setLoading(true);
    try {
      console.log('🔄 Loading all data from server...');
      
      await Promise.all([
        fetchUsers(),
        fetchUserLogs(),
        fetchSystemStats(),
        fetchOnlineUsers()
      ]);
      
      console.log('✅ All data loaded successfully');
    } catch (error) {
      console.error('Error loading data:', error);
      Alert.alert(t('app.info'), t('system_admin.sample_data'));
      loadSampleData();
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const fetchUsers = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;

      const cached = await getCache<any>('system:users');
      if (cached) { setUsers(cached); setLoading(false); }

      console.log('👥 Fetching users...');
      
      const response = await fetchWithTimeout(`${API_BASE_URL}/api/admin/users`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.ok) {
        const data = await response.json();
        console.log('📊 Users data received');
        
        // Handle both response formats
        const usersArray = data.users || data || [];
        setUsers(Array.isArray(usersArray) ? usersArray : []);
        setCache('system:users', Array.isArray(usersArray) ? usersArray : []).catch(() => {});
      } else {
        console.error('Failed to fetch users:', response.status);
        throw new Error(`HTTP ${response.status}`);
      }
    } catch (error) {
      console.error('Error fetching users:', error);
      const cached = await getCache<any>('system:users');
      if (cached) { setUsers(cached); setLoading(false); return; }
      throw error;
    }
  };

  const fetchSystemStats = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;

      const cached = await getCache<any>('system:stats');
      if (cached) { setSystemStats(cached); setLoading(false); }

      console.log('📊 Fetching system stats...');
      
      // First try /api/admin/stats
      const response = await fetchWithTimeout(`${API_BASE_URL}/api/admin/stats`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.ok) {
        const data = await response.json();
        console.log('📈 System stats received:', data);
        
        // Ensure all required fields exist
        const safeData: SystemStats = {
          totalUsers: data.totalUsers || 0,
          totalAdmins: data.totalAdmins || 0,
          totalSellers: data.totalSellers || 0,
          totalClients: data.totalClients || 0,
          activeUsers: data.activeUsers || 0,
          pendingUsers: data.pendingUsers || 0,
          reportedPosts: data.reportedPosts || 0,
          todayActivities: data.todayActivities || 0,
          todayRevenue: data.todayRevenue || 0,
          onlineUsers: data.onlineUsers || 0,
          connectedNow: data.connectedNow || 0,
          adminConnected: data.adminConnected || 0,
          webSocketConnected: data.webSocketConnected || 0,
          timestamp: data.timestamp || new Date().toISOString()
        };
        
        setSystemStats(safeData);
        setCache('system:stats', safeData).catch(() => {});
      } else {
        // Fallback to real-time stats
        await fetchRealTimeStats();
      }
    } catch (error) {
      console.error('Error fetching system stats:', error);
      const cached = await getCache<any>('system:stats');
      if (cached) { setSystemStats(cached); setLoading(false); return; }
      await fetchRealTimeStats();
    }
  };

  const fetchRealTimeStats = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;

      const cached = await getCache<any>('d:admin:real-time-stats');
      if (cached) { setSystemStats(cached); setLoading(false); }

      console.log('📊 Fetching real-time stats...');
      
      const response = await fetchWithTimeout(`${API_BASE_URL}/api/admin/real-time-stats`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.ok) {
        const data = await response.json();
        console.log('📈 Real-time stats received');
        
        const safeData: SystemStats = {
          totalUsers: data.totalUsers || 0,
          totalAdmins: data.totalAdmins || 0,
          totalSellers: data.totalSellers || 0,
          totalClients: data.totalClients || 0,
          activeUsers: data.activeUsers || 0,
          pendingUsers: data.pendingUsers || 0,
          reportedPosts: data.reportedPosts || 0,
          todayActivities: data.todayActivities || 0,
          todayRevenue: data.todayRevenue || 0,
          onlineUsers: data.onlineUsers || 0,
          connectedNow: data.connectedNow || 0,
          adminConnected: data.adminConnected || 0,
          webSocketConnected: data.webSocketConnected || 0,
          timestamp: data.timestamp || new Date().toISOString()
        };
        
        setSystemStats(safeData);
        setCache('d:admin:real-time-stats', safeData).catch(() => {});
      } else {
        throw new Error('Failed to fetch real-time stats');
      }
    } catch (error) {
      console.error('Error fetching real-time stats:', error);
      const cached = await getCache<any>('d:admin:real-time-stats');
      if (cached) { setSystemStats(cached); setLoading(false); }
      // Keep default values
    }
  };

  const fetchOnlineUsers = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;

      const cached = await getCache<any>('admin:online-users');
      if (cached) { setOnlineUsers(cached); setLoading(false); }

      console.log('🌐 Fetching online users...');
      
      const response = await fetchWithTimeout(`${API_BASE_URL}/api/admin/online-users`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.ok) {
        const data = await response.json();
        console.log('👥 Online users received:', data.online_users?.length || 0);
        setOnlineUsers(data.online_users || []);
        setCache('admin:online-users', data.online_users || []).catch(() => {});
      } else {
        console.log('⚠️ Could not fetch online users');
        setOnlineUsers([]);
      }
    } catch (error) {
      console.error('Error fetching online users:', error);
      const cached = await getCache<any>('admin:online-users');
      if (cached) { setOnlineUsers(cached); setLoading(false); return; }
      setOnlineUsers([]);
    }
  };

  const fetchUserLogs = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;

      const cached = await getCache<any>('d:admin:logs');
      if (cached) { setUserLogs(cached); setLoading(false); }

      console.log('📝 Fetching user logs...');
      
      const response = await fetchWithTimeout(`${API_BASE_URL}/api/admin/logs`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });

      if (response.ok) {
        const data = await response.json();
        console.log('📋 User logs received:', data.length || 0);
        setUserLogs(Array.isArray(data) ? data : []);
        setCache('d:admin:logs', Array.isArray(data) ? data : []).catch(() => {});
      } else {
        console.log('⚠️ Could not fetch logs');
        setUserLogs([]);
      }
    } catch (error) {
      console.error('Error fetching user logs:', error);
      const cached = await getCache<any>('d:admin:logs');
      if (cached) { setUserLogs(cached); setLoading(false); return; }
      setUserLogs([]);
    }
  };

  const fetchUserSpecificLogs = async (userId: string) => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;

      console.log(`📝 Fetching logs for user ${userId}...`);
      
      // Filter logs for this user
      const filteredLogs = userLogs.filter(log => log.user_id === userId);
      setUserSpecificLogs(filteredLogs);
      
    } catch (error) {
      console.error('Error fetching user specific logs:', error);
      setUserSpecificLogs([]);
    }
  };

  // ✅ NEW FUNCTION: Disable User (Set status to 'pending')
  const disableUser = async (userId: string, userName: string) => {
    try {
        const token = await AsyncStorage.getItem('userToken');
        if (!token) return;

        Alert.alert(
            'Disable User',
            `Are you sure you want to disable ${userName}?`,
            [
                { text: 'Cancel', style: 'cancel' },
                {
                    text: 'Disable',
                    style: 'destructive',
                    onPress: async () => {
                        try {
                            if (!(await requireNetwork())) return;

                            console.log('🔧 DISABLING USER:', userId);
                            console.log('📤 Sending status: pending');
                            
                            const response = await fetchWithTimeout(`${API_BASE_URL}/api/admin/users/${userId}/status`, {
                                method: 'PUT',
                                headers: {
                                    'Authorization': `Bearer ${token}`,
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({ status: 'pending' })
                            });

                            console.log('📥 Response status:', response.status);
                            const responseData = await response.json();
                            console.log('📥 Response data:', responseData);

                            if (response.ok) {
                                Alert.alert('Success', `${userName} has been disabled successfully!`);
                                // Force refresh users
                                await fetchUsers();
                                // Also refresh stats
                                await fetchSystemStats();
                                // Also refresh online users
                                await fetchOnlineUsers();
                            } else {
                                Alert.alert('Error', responseData.error || 'Failed to disable user');
                            }
                        } catch (error) {
                            console.error('Error disabling user:', error);
                            Alert.alert('Error', 'Network error. Please try again.');
                        }
                    }
                }
            ]
        );
    } catch (error) {
        console.error('Error in disableUser:', error);
        Alert.alert('Error', 'Failed to disable user');
    }
};

  // ✅ NEW FUNCTION: Re-enable User (Set status to 'approved')
  const enableUser = async (userId: string, userName: string) => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;

      Alert.alert(
        'Enable User',
        `Are you sure you want to enable ${userName}?\n\nThis will set their status to "Approved" and they will regain access.`,
        [
          { text: 'Cancel', style: 'cancel' },
          {
            text: 'Enable',
            onPress: async () => {
              try {
                if (!(await requireNetwork())) return;

                const response = await fetchWithTimeout(`${API_BASE_URL}/api/admin/users/${userId}/status`, {
                  method: 'PUT',
                  headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                  },
                  body: JSON.stringify({ status: 'approved' })
                });

                if (response.ok) {
                  Alert.alert('Success', `${userName} has been enabled successfully!`);
                  await loadAllData(); // Refresh the users list
                } else {
                  const errorData = await response.json();
                  Alert.alert('Error', errorData.error || 'Failed to enable user');
                }
              } catch (error) {
                console.error('Error enabling user:', error);
                Alert.alert('Error', 'Network error. Please try again.');
              }
            }
          }
        ]
      );
    } catch (error) {
      console.error('Error in enableUser:', error);
      Alert.alert('Error', 'Failed to enable user');
    }
  };

  // Updated updateUserStatus function to include enable/disable options
  const updateUserStatus = async (userId: string, status: 'approved' | 'rejected' | 'suspended' | 'pending') => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;

      const statusMessages = {
        approved: 'Approve',
        rejected: 'Reject',
        suspended: 'Suspend',
        pending: 'Disable'
      };

      const confirmMessages = {
        approved: 'Are you sure you want to approve this user? They will gain full access.',
        rejected: 'Are you sure you want to reject this user? They will not be able to access the system.',
        suspended: 'Are you sure you want to suspend this user? Their access will be temporarily blocked.',
        pending: 'Are you sure you want to disable this user? They will lose access until re-enabled.'
      };

      Alert.alert(
        statusMessages[status],
        confirmMessages[status],
        [
          { text: 'Cancel', style: 'cancel' },
          {
            text: statusMessages[status],
            style: status === 'pending' ? 'destructive' : 'default',
            onPress: async () => {
              if (!(await requireNetwork())) return;

              const response = await fetchWithTimeout(`${API_BASE_URL}/api/admin/users/${userId}/status`, {
                method: 'PUT',
                headers: {
                  'Authorization': `Bearer ${token}`,
                  'Content-Type': 'application/json'
                },
                body: JSON.stringify({ status })
              });

              if (response.ok) {
                Alert.alert('Success', `User status updated to ${status.toUpperCase()}`);
                await loadAllData();
              } else {
                Alert.alert('Error', 'Failed to update status');
              }
            }
          }
        ]
      );
    } catch (error) {
      console.error('Error updating user status:', error);
      Alert.alert('Error', 'Failed to update status');
    }
  };

  const getFilteredUsers = () => {
    let filtered = [...users];
    
    // Filter by type
    if (selectedUserType !== 'all') {
      filtered = filtered.filter(u => u.role === selectedUserType);
    }
    
    // Filter by search
    if (searchQuery.trim()) {
      const query = searchQuery.toLowerCase();
      filtered = filtered.filter(u => 
        u.email?.toLowerCase().includes(query) ||
        u.full_name?.toLowerCase().includes(query) ||
        u.phone?.includes(query) ||
        u.business_name?.toLowerCase().includes(query)
      );
    }
    
    return filtered;
  };

  const getFilteredLogs = () => {
    let filtered = [...userLogs];
    
    // Filter by status
    if (selectedLogStatus !== 'all') {
      filtered = filtered.filter(log => log.status === selectedLogStatus);
    }
    
    // Filter by time range
    const now = new Date();
    switch (selectedTimeRange) {
      case 'today':
        const today = now.toISOString().split('T')[0];
        filtered = filtered.filter(a => a.created_at?.startsWith(today));
        break;
      case 'week':
        const weekAgo = new Date(now);
        weekAgo.setDate(weekAgo.getDate() - 7);
        filtered = filtered.filter(a => new Date(a.created_at) >= weekAgo);
        break;
      case 'month':
        const monthAgo = new Date(now);
        monthAgo.setMonth(monthAgo.getMonth() - 1);
        filtered = filtered.filter(a => new Date(a.created_at) >= monthAgo);
        break;
    }
    
    return filtered.slice(0, 20);
  };

  const getStatusColor = (status: string): string => {
    switch (status) {
      case 'approved':
      case 'success':
        return '#27ae60';
      case 'pending':
        return '#f39c12';
      case 'suspended':
        return '#e67e22';
      case 'rejected':
      case 'failed':
        return '#e74c3c';
      case 'inactive':
        return '#95a5a6';
      default:
        return '#95a5a6';
    }
  };

  const getRoleColor = (role: string): string => {
    switch (role) {
      case 'admin': return '#9b59b6';
      case 'seller': return '#3498db';
      case 'client': return '#2ecc71';
      default: return '#95a5a6';
    }
  };

  const formatDate = (dateString: string): string => {
    if (!dateString) return 'N/A';
    try {
      const date = new Date(dateString);
      return date.toLocaleDateString(localeMap[lang] || 'sw-TZ', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
      });
    } catch {
      return 'N/A';
    }
  };

  const formatTime = (dateString: string): string => {
    if (!dateString) return 'N/A';
    try {
      const date = new Date(dateString);
      return date.toLocaleTimeString(localeMap[lang] || 'sw-TZ', {
        hour: '2-digit',
        minute: '2-digit'
      });
    } catch {
      return 'N/A';
    }
  };

  const formatDateTime = (dateString: string): string => {
    if (!dateString) return 'N/A';
    try {
      const date = new Date(dateString);
      return date.toLocaleString(localeMap[lang] || 'sw-TZ', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    } catch {
      return 'N/A';
    }
  };

  const formatTimeAgo = (dateString: string): string => {
    if (!dateString) return t('system_admin.log_time_never');
    try {
      const date = new Date(dateString);
      const now = new Date();
      const diffMs = now.getTime() - date.getTime();
      const diffMins = Math.floor(diffMs / 60000);
      const diffHours = Math.floor(diffMs / 3600000);
      const diffDays = Math.floor(diffMs / 86400000);

      if (diffMins < 60) {
        return t('system_admin.log_time_ago').replace('{n}', diffMins.toString());
      } else if (diffHours < 24) {
        return t('system_admin.log_hours_ago').replace('{n}', diffHours.toString());
      } else {
        return t('system_admin.log_days_ago').replace('{n}', diffDays.toString());
      }
    } catch {
      return t('system_admin.log_time_never');
    }
  };

  const formatCurrency = (amount: number): string => {
    return `TZS ${amount?.toLocaleString('en-TZ') || '0'}`;
  };

  const loadSampleData = () => {
    console.log('📋 Loading sample data...');
    
    // Sample users
    const sampleUsers: User[] = [
      {
        id: '1',
        email: 'admin@example.com',
        role: 'admin',
        full_name: 'System Admin',
        phone: '0712345678',
        business_name: 'Headquarters',
        status: 'approved',
        created_at: new Date().toISOString(),
        is_online: true
      },
      {
        id: '2',
        email: 'seller@example.com',
        role: 'seller',
        full_name: 'John Seller',
        phone: '0756789123',
        business_name: 'Clothing Store',
        business_location: 'Dar es Salaam',
        status: 'approved',
        created_at: new Date().toISOString(),
        is_online: false
      },
      {
        id: '3',
        email: 'client@example.com',
        role: 'client',
        full_name: 'Mary Client',
        phone: '0778912345',
        status: 'approved',
        created_at: new Date().toISOString(),
        is_online: true
      }
    ];
    
    setUsers(sampleUsers);
    
    // Sample logs
    const sampleLogs: UserLog[] = [
      {
        id: '1',
        user_id: '1',
        user_email: 'admin@example.com',
        action: 'LOGIN',
        endpoint: '/api/login',
        details: { role: 'admin', success: true },
        ip_address: '192.168.1.1',
        status: 'success',
        created_at: new Date().toISOString()
      },
      {
        id: '2',
        user_id: '2',
        user_email: 'seller@example.com',
        action: 'CREATE_PRODUCT',
        endpoint: '/api/products',
        details: { product_name: 'T-Shirt' },
        ip_address: '192.168.1.2',
        status: 'success',
        created_at: new Date(Date.now() - 3600000).toISOString()
      }
    ];
    
    setUserLogs(sampleLogs);
    
    // Sample online users
    setOnlineUsers([
      {
        id: '1',
        email: 'admin@example.com',
        full_name: 'System Admin',
        role: 'admin',
        last_seen: new Date().toISOString(),
        is_online: true,
        has_live_connection: true
      }
    ]);
    
    // Set WebSocket status
    setWebSocketStatus({
      total_connections: 15,
      admin_connections: 1,
      connected: true
    });
  };

  const onRefresh = () => {
    setRefreshing(true);
    loadAllData();
  };

  const handleLogout = async () => {
    Alert.alert(
      t('profile.logout'),
      t('profile.logout_confirm'),
      [
        { text: t('app.cancel'), style: 'cancel' },
        {
          text: t('profile.logout'),
          style: 'destructive',
          onPress: async () => {
            try {
              await signOut();
              router.replace('/(tabs)');
            } catch (error) {
              console.error('Error during logout:', error);
              Alert.alert(t('app.error'), t('profile.error_update'));
            }
          }
        }
      ]
    );
  };

  const viewUserDetails = (userData: User) => {
    setSelectedUser(userData);
    setUserDetailModal(true);
  };

  const viewUserLogs = async (userData: User) => {
    setSelectedUser(userData);
    await fetchUserSpecificLogs(userData.id);
    setUserLogsModal(true);
  };

  // ========================== RENDERING FUNCTIONS ==========================

  const renderTabs = () => {
    const tabs = [
      { id: 'dashboard', label: t('system_admin.dashboard'), icon: 'grid' },
      { id: 'users', label: t('system_admin.all_users').replace(' ({n})', ''), icon: 'people' },
      { id: 'online', label: t('system_admin.online_now'), icon: 'wifi' },
      { id: 'logs', label: t('system_admin.system_logs').replace(' ({n})', ''), icon: 'time' },
    ];

    return (
      <View style={styles.tabsContainer}>
        {tabs.map(tab => (
          <TouchableOpacity
            key={tab.id}
            style={[
              styles.tabButton,
              activeTab === tab.id && styles.tabButtonActive
            ]}
            onPress={() => setActiveTab(tab.id as any)}
          >
            <Ionicons 
              name={tab.icon as any} 
              size={20} 
              color={activeTab === tab.id ? '#3498db' : '#95a5a6'} 
            />
            <Text style={[
              styles.tabText,
              activeTab === tab.id && styles.tabTextActive
            ]}>
              {tab.label}
            </Text>
          </TouchableOpacity>
        ))}
      </View>
    );
  };

  const renderDashboardTab = () => {
    return (
      <>
        {/* Welcome Card */}
        <View style={styles.welcomeCard}>
          <View style={styles.welcomeText}>
            <Text style={styles.welcomeTitle}>{t('system_admin.welcome_title').replace('{name}', user?.full_name || 'Admin')}</Text>
            <Text style={styles.welcomeMessage}>
              {t('system_admin.dashboard_subtitle')}
            </Text>
                  <Text style={styles.welcomeTimestamp}>
              {t('system_admin.last_updated').replace('{time}', formatTime(new Date().toISOString()))}
            </Text>
          </View>
          <View style={styles.welcomeIcon}>
            <MaterialCommunityIcons name="shield-crown" size={40} color="#3498db" />
          </View>
        </View>

        {/* Stats Cards */}
        <View style={styles.section}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>{t('system_admin.system_overview')}</Text>
            <Text style={styles.sectionSubtitle}>
              {formatTime(systemStats.timestamp)}
            </Text>
          </View>
          
          <View style={styles.statsGrid}>
            {/* Total Users */}
            <View style={[styles.statCard, { borderLeftColor: '#3498db' }]}>
              <View style={styles.statHeader}>
                <View style={[styles.statIconContainer, { backgroundColor: '#3498db20' }]}>
                  <Ionicons name="people" size={20} color="#3498db" />
                </View>
              </View>
              <Text style={styles.statValue}>{systemStats.totalUsers}</Text>
              <Text style={styles.statLabel}>{t('system_admin.total_users')}</Text>
              <Text style={styles.statSubtitle}>{t('system_admin.active_users').replace('{n}', systemStats.activeUsers.toString())}</Text>
            </View>
            
            {/* Online Users */}
            <View style={[styles.statCard, { borderLeftColor: '#2ecc71' }]}>
              <View style={styles.statHeader}>
                <View style={[styles.statIconContainer, { backgroundColor: '#2ecc7120' }]}>
                  <Ionicons name="wifi" size={20} color="#2ecc71" />
                </View>
              </View>
              <Text style={styles.statValue}>{systemStats.onlineUsers}</Text>
              <Text style={styles.statLabel}>{t('system_admin.online_now')}</Text>
              <Text style={styles.statSubtitle}>{t('system_admin.connected_now').replace('{n}', systemStats.connectedNow.toString())}</Text>
            </View>
            
            {/* Today's Revenue */}
            <View style={[styles.statCard, { borderLeftColor: '#f39c12' }]}>
              <View style={styles.statHeader}>
                <View style={[styles.statIconContainer, { backgroundColor: '#f39c1220' }]}>
                  <Ionicons name="cash" size={20} color="#f39c12" />
                </View>
              </View>
              <Text style={styles.statValue}>{formatCurrency(systemStats.todayRevenue)}</Text>
              <Text style={styles.statLabel}>{t('system_admin.today_revenue')}</Text>
              <Text style={styles.statSubtitle}>{t('system_admin.today_activities').replace('{n}', systemStats.todayActivities.toString())}</Text>
            </View>
            
            {/* Pending Users */}
            <View style={[styles.statCard, { borderLeftColor: '#e74c3c' }]}>
              <View style={styles.statHeader}>
                <View style={[styles.statIconContainer, { backgroundColor: '#e74c3c20' }]}>
                  <Ionicons name="time" size={20} color="#e74c3c" />
                </View>
              </View>
              <Text style={styles.statValue}>{systemStats.pendingUsers}</Text>
              <Text style={styles.statLabel}>{t('system_admin.pending_users')}</Text>
              <Text style={styles.statSubtitle}>{t('system_admin.awaiting_approval')}</Text>
            </View>
          </View>
        </View>

        {/* System Status */}
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>{t('system_admin.system_status')}</Text>
          
          <View style={styles.systemStatusGrid}>
            <View style={styles.statusItem}>
              <View style={[styles.statusIndicator, { backgroundColor: '#2ecc71' }]} />
              <Text style={styles.statusLabel}>{t('system_admin.database')}</Text>
              <Text style={[styles.statusValue, { color: '#2ecc71' }]}>{t('system_admin.online')}</Text>
            </View>
            
            <View style={styles.statusItem}>
              <View style={[styles.statusIndicator, { backgroundColor: '#2ecc71' }]} />
              <Text style={styles.statusLabel}>{t('system_admin.api_server')}</Text>
              <Text style={[styles.statusValue, { color: '#2ecc71' }]}>{t('system_admin.online')}</Text>
            </View>
            
            <View style={styles.statusItem}>
              <View style={[styles.statusIndicator, { backgroundColor: '#2ecc71' }]} />
              <Text style={styles.statusLabel}>{t('system_admin.email_service')}</Text>
              <Text style={[styles.statusValue, { color: '#2ecc71' }]}>{t('system_admin.online')}</Text>
            </View>
            
            <View style={styles.statusItem}>
              <View style={[styles.statusIndicator, 
                { backgroundColor: webSocketStatus.connected ? '#2ecc71' : '#e74c3c' }
              ]} />
              <Text style={styles.statusLabel}>{t('system_admin.websocket')}</Text>
              <Text style={[styles.statusValue, 
                { color: webSocketStatus.connected ? '#2ecc71' : '#e74c3c' }
              ]}>
                {webSocketStatus.connected ? t('system_admin.online') : t('system_admin.offline')}
              </Text>
            </View>
          </View>
          
          {webSocketStatus.connected && (
            <View style={styles.webSocketStats}>
              <View style={styles.webSocketStat}>
                <Text style={styles.webSocketStatValue}>{webSocketStatus.total_connections}</Text>
                <Text style={styles.webSocketStatLabel}>{t('system_admin.connections')}</Text>
              </View>
              <View style={styles.webSocketDivider} />
              <View style={styles.webSocketStat}>
                <Text style={styles.webSocketStatValue}>{webSocketStatus.admin_connections}</Text>
                <Text style={styles.webSocketStatLabel}>{t('system_admin.admins')}</Text>
              </View>
              <View style={styles.webSocketDivider} />
              <View style={styles.webSocketStat}>
                <Text style={styles.webSocketStatValue}>{onlineUsers.length}</Text>
                <Text style={styles.webSocketStatLabel}>{t('system_admin.online_users')}</Text>
              </View>
            </View>
          )}
        </View>
      </>
    );
  };

  const renderUsersTab = () => {
    const filteredUsers = getFilteredUsers();
    
    return (
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>{t('system_admin.all_users').replace('{n}', users.length.toString())}</Text>
        
        {/* Filters */}
        <View style={styles.filterRow}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false}>
            <View style={styles.filterContainer}>
              {[
                { id: 'all', label: t('system_admin.all_logs'), count: users.length },
                { id: 'admin', label: t('system_admin.admins'), count: users.filter(u => u.role === 'admin').length },
                { id: 'seller', label: t('system_admin.sellers_count').replace('{n}', ''), count: users.filter(u => u.role === 'seller').length },
                { id: 'client', label: t('system_admin.clients_count').replace('{n}', ''), count: users.filter(u => u.role === 'client').length },
              ].map(filter => (
                <TouchableOpacity
                  key={filter.id}
                  style={[
                    styles.filterButton,
                    selectedUserType === filter.id && styles.filterButtonActive
                  ]}
                  onPress={() => setSelectedUserType(filter.id as any)}
                >
                  <Text style={[
                    styles.filterButtonText,
                    selectedUserType === filter.id && styles.filterButtonTextActive
                  ]}>
                    {filter.label} ({filter.count})
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
          </ScrollView>
        </View>
        
        {/* Search */}
        <View style={styles.searchContainer}>
          <Ionicons name="search" size={20} color="#666" />
          <TextInput
            style={styles.searchInput}
            placeholder={t('system_admin.search_placeholder')}
            value={searchQuery}
            onChangeText={setSearchQuery}
            placeholderTextColor="#999"
          />
          {searchQuery.length > 0 && (
            <TouchableOpacity onPress={() => setSearchQuery('')}>
              <Ionicons name="close-circle" size={20} color="#666" />
            </TouchableOpacity>
          )}
        </View>
        
        {/* Users List */}
        {filteredUsers.length > 0 ? (
          <FlatList
            data={filteredUsers}
            renderItem={({ item }) => (
              <View style={styles.userCard}>
                <View style={styles.userHeader}>
                  <View style={styles.userAvatar}>
                    <Text style={styles.userAvatarText}>
                      {item.full_name?.charAt(0) || item.email.charAt(0)}
                    </Text>
                    {item.is_online && <View style={styles.onlineIndicator} />}
                  </View>
                  <View style={styles.userInfo}>
                    <Text style={styles.userName}>{item.full_name || item.email}</Text>
                    <Text style={styles.userEmail}>{item.email}</Text>
                    <View style={styles.userMeta}>
                      <View style={[styles.roleBadge, { backgroundColor: getRoleColor(item.role) + '20' }]}>
                        <Text style={[styles.roleText, { color: getRoleColor(item.role) }]}>
                          {item.role}
                        </Text>
                      </View>
                      <View style={[styles.statusBadge, { backgroundColor: getStatusColor(item.status) + '20' }]}>
                        <Text style={[styles.statusText, { color: getStatusColor(item.status) }]}>
                          {item.status}
                        </Text>
                      </View>
                    </View>
                  </View>
                </View>
                
                <View style={styles.userFooter}>
                  <View style={styles.userDetail}>
                    <Ionicons name="call-outline" size={14} color="#7f8c8d" />
                    <Text style={styles.userDetailText}>{item.phone || t('system_admin.no_phone')}</Text>
                  </View>
                  <Text style={styles.userDate}>{formatDate(item.created_at)}</Text>
                </View>
                
                {/* ✅ UPDATED: Three buttons - Details, View Logs, and Disable */}
                <View style={styles.userActions}>
                  <TouchableOpacity 
                    style={[styles.userActionButton, styles.detailsButton]}
                    onPress={() => viewUserDetails(item)}
                  >
                    <Ionicons name="information-circle-outline" size={14} color="#7f8c8d" />
                    <Text style={styles.detailsButtonText}>{t('system_admin.details')}</Text>
                  </TouchableOpacity>
                  
                  <TouchableOpacity 
                    style={[styles.userActionButton, styles.trackButton]}
                    onPress={() => viewUserLogs(item)}
                  >
                    <Ionicons name="eye-outline" size={14} color="#3498db" />
                    <Text style={styles.trackButtonText}>{t('system_admin.view_logs')}</Text>
                  </TouchableOpacity>
                  
                  {/* ✅ NEW: Disable Button */}
                  {item.status === 'approved' ? (
                    <TouchableOpacity 
                      style={[styles.userActionButton, styles.disableButton]}
                      onPress={() => disableUser(item.id, item.full_name || item.email)}
                    >
                      <Ionicons name="ban-outline" size={14} color="#e74c3c" />
                      <Text style={styles.disableButtonText}>{t('system_admin.disable')}</Text>
                    </TouchableOpacity>
                  ) : (
                    <TouchableOpacity 
                      style={[styles.userActionButton, styles.enableButton]}
                      onPress={() => enableUser(item.id, item.full_name || item.email)}
                    >
                      <Ionicons name="checkmark-circle-outline" size={14} color="#27ae60" />
                      <Text style={styles.enableButtonText}>{t('system_admin.enable')}</Text>
                    </TouchableOpacity>
                  )}
                </View>
              </View>
            )}
            keyExtractor={item => item.id}
            scrollEnabled={false}
          />
        ) : (
          <View style={styles.emptyState}>
            <Ionicons name="people-outline" size={40} color="#bdc3c7" />
            <Text style={styles.emptyStateText}>{t('system_admin.no_users_found')}</Text>
          </View>
        )}
      </View>
    );
  };

  const renderOnlineTab = () => {
    return (
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>{t('system_admin.online_users')} ({onlineUsers.length})</Text>
          <TouchableOpacity onPress={fetchOnlineUsers}>
            <Ionicons name="refresh" size={20} color="#3498db" />
          </TouchableOpacity>
        </View>
        
        <Text style={styles.sectionSubtitle}>
          {webSocketStatus.connected ? 
            t('system_admin.connected_now').replace('{n}', webSocketStatus.total_connections.toString()) : 
            t('system_admin.offline')}
        </Text>
        
        {onlineUsers.length > 0 ? (
          <FlatList
            data={onlineUsers}
            renderItem={({ item }) => (
              <View style={styles.userCard}>
                <View style={styles.userHeader}>
                  <View style={styles.userAvatar}>
                    <Text style={styles.userAvatarText}>
                      {item.full_name?.charAt(0) || item.email.charAt(0)}
                    </Text>
                    <View style={[styles.onlineIndicator, 
                      { backgroundColor: item.has_live_connection ? '#2ecc71' : '#f39c12' }
                    ]} />
                  </View>
                  <View style={styles.userInfo}>
                    <Text style={styles.userName}>{item.full_name || item.email}</Text>
                    <Text style={styles.userEmail}>{item.email}</Text>
                    <View style={styles.userMeta}>
                      <View style={[styles.roleBadge, { backgroundColor: getRoleColor(item.role) + '20' }]}>
                        <Text style={[styles.roleText, { color: getRoleColor(item.role) }]}>
                          {item.role}
                        </Text>
                      </View>
                      <View style={[styles.statusBadge, { backgroundColor: '#2ecc7120' }]}>
                        <Ionicons name="wifi" size={10} color="#2ecc71" />
                        <Text style={[styles.statusText, { color: '#2ecc71' }]}>
                          {item.has_live_connection ? 'Live' : t('system_admin.online')}
                        </Text>
                      </View>
                    </View>
                  </View>
                </View>
                
                <View style={styles.userFooter}>
                  <Text style={styles.userLastSeen}>
                    {t('system_admin.last_seen_label')} {formatTimeAgo(item.last_seen)}
                  </Text>
                </View>
              </View>
            )}
            keyExtractor={item => item.id}
            scrollEnabled={false}
          />
        ) : (
          <View style={styles.emptyState}>
            <Ionicons name="wifi-outline" size={40} color="#bdc3c7" />
            <Text style={styles.emptyStateText}>{t('system_admin.no_users_online')}</Text>
            <Text style={styles.emptyStateSubtext}>
              {t('system_admin.connect_websocket')}
            </Text>
          </View>
        )}
      </View>
    );
  };

  const renderLogsTab = () => {
    const filteredLogs = getFilteredLogs();
    
    return (
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>{t('system_admin.system_logs').replace('{n}', userLogs.length.toString())}</Text>
          <View style={styles.timeFilter}>
            {[
              { id: 'today', label: t('system_admin.today_filter') },
              { id: 'week', label: t('system_admin.week_filter') },
              { id: 'month', label: t('system_admin.month_filter') },
            ].map(filter => (
              <TouchableOpacity
                key={filter.id}
                style={[
                  styles.timeFilterButton,
                  selectedTimeRange === filter.id && styles.timeFilterButtonActive
                ]}
                onPress={() => setSelectedTimeRange(filter.id as any)}
              >
                <Text style={[
                  styles.timeFilterText,
                  selectedTimeRange === filter.id && styles.timeFilterTextActive
                ]}>
                  {filter.label}
                </Text>
              </TouchableOpacity>
            ))}
          </View>
        </View>
        
        {/* Log Status Filter */}
        <View style={styles.logFilterContainer}>
          {[
            { id: 'all', label: t('system_admin.all_logs'), icon: 'list' },
            { id: 'success', label: t('system_admin.success_logs'), icon: 'checkmark-circle' },
            { id: 'failed', label: t('system_admin.failed_logs'), icon: 'close-circle' },
          ].map(filter => (
            <TouchableOpacity
              key={filter.id}
              style={[
                styles.logFilterButton,
                selectedLogStatus === filter.id && styles.logFilterButtonActive
              ]}
              onPress={() => setSelectedLogStatus(filter.id as any)}
            >
              <Ionicons 
                name={filter.icon as any} 
                size={16} 
                color={selectedLogStatus === filter.id ? '#fff' : '#666'} 
              />
              <Text style={[
                styles.logFilterText,
                selectedLogStatus === filter.id && styles.logFilterTextActive
              ]}>
                {filter.label}
              </Text>
            </TouchableOpacity>
          ))}
        </View>
        
        {/* Logs List */}
        {filteredLogs.length > 0 ? (
          <FlatList
            data={filteredLogs}
            renderItem={({ item }) => (
              <View style={styles.logCard}>
                <View style={styles.logHeader}>
                  <View style={styles.logUser}>
                    <View style={[styles.logDot, { backgroundColor: getStatusColor(item.status) }]} />
                    <View>
                      <Text style={styles.logUserName}>
                        {item.users?.full_name || item.user_email || 'Guest'}
                      </Text>
                      <Text style={styles.logAction}>{item.action}</Text>
                    </View>
                  </View>
                  <Text style={styles.logTime}>{formatTimeAgo(item.created_at)}</Text>
                </View>
                
                <View style={styles.logBody}>
                  <Text style={styles.logEndpoint}>{item.endpoint}</Text>
                  {item.details && (
                    <Text style={styles.logDetails}>
                      {JSON.stringify(item.details).substring(0, 100)}...
                    </Text>
                  )}
                  <Text style={styles.logIp}>IP: {item.ip_address}</Text>
                </View>
                
                <View style={styles.logFooter}>
                  <View style={[styles.logStatusBadge, { backgroundColor: getStatusColor(item.status) + '20' }]}>
                    <Ionicons 
                      name={item.status === 'success' ? 'checkmark-circle' : 'close-circle'} 
                      size={12} 
                      color={getStatusColor(item.status)} 
                    />
                    <Text style={[styles.logStatusText, { color: getStatusColor(item.status) }]}>
                      {item.status}
                    </Text>
                  </View>
                  <Text style={styles.logDateTime}>{formatDateTime(item.created_at)}</Text>
                </View>
              </View>
            )}
            keyExtractor={item => item.id}
            scrollEnabled={false}
          />
        ) : (
          <View style={styles.emptyState}>
            <Ionicons name="time-outline" size={40} color="#bdc3c7" />
            <Text style={styles.emptyStateText}>{t('system_admin.no_logs')}</Text>
            <Text style={styles.emptyStateSubtext}>
              {t('system_admin.try_different_filter')}
            </Text>
          </View>
        )}
      </View>
    );
  };

  if (loading && !refreshing) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#3498db" />
        <Text style={styles.loadingText}>{t('system_admin.loading_dashboard')}</Text>
        <Text style={styles.loadingSubtext}>
          {t('system_admin.connecting_db')}
        </Text>
      </View>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <View style={styles.headerTitle}>
          <Text style={styles.headerTitleText}>{t('system_admin.admin_dashboard_title')}</Text>
          <Text style={styles.headerSubtitle}>{t('system_admin.real_data')}</Text>
        </View>
        
        <View style={styles.headerActions}>
          <TouchableOpacity onPress={onRefresh} style={styles.headerButton}>
            <Ionicons name="refresh" size={24} color="#3498db" />
          </TouchableOpacity>
          <TouchableOpacity onPress={handleLogout} style={styles.headerButton}>
            <Ionicons name="log-out-outline" size={24} color="#e74c3c" />
          </TouchableOpacity>
        </View>
      </View>

      {/* Tabs Navigation */}
      {renderTabs()}

      <ScrollView 
        style={styles.content}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        showsVerticalScrollIndicator={false}
      >
        {/* Render Active Tab Content */}
        {activeTab === 'dashboard' && renderDashboardTab()}
        {activeTab === 'users' && renderUsersTab()}
        {activeTab === 'online' && renderOnlineTab()}
        {activeTab === 'logs' && renderLogsTab()}
      </ScrollView>

      {/* User Detail Modal */}
      <Modal
        animationType="slide"
        transparent={true}
        visible={userDetailModal}
        onRequestClose={() => setUserDetailModal(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            {selectedUser && (
              <>
                <View style={styles.modalHeader}>
                  <Text style={styles.modalTitle}>{t('system_admin.user_details')}</Text>
                  <TouchableOpacity onPress={() => setUserDetailModal(false)}>
                    <Ionicons name="close" size={24} color="#333" />
                  </TouchableOpacity>
                </View>
                
                <ScrollView style={styles.modalBody}>
                  <View style={styles.modalUserHeader}>
                    <View style={styles.modalAvatar}>
                      <Text style={styles.modalAvatarText}>
                        {selectedUser.full_name?.charAt(0) || selectedUser.email.charAt(0)}
                      </Text>
                      {selectedUser.is_online && <View style={styles.modalOnlineIndicator} />}
                    </View>
                    <Text style={styles.modalUserName}>{selectedUser.full_name}</Text>
                    <Text style={styles.modalUserEmail}>{selectedUser.email}</Text>
                    
                    <View style={styles.modalBadges}>
                      <View style={[styles.modalRoleBadge, { backgroundColor: getRoleColor(selectedUser.role) + '20' }]}>
                        <Text style={[styles.modalRoleText, { color: getRoleColor(selectedUser.role) }]}>
                          {selectedUser.role}
                        </Text>
                      </View>
                      <View style={[styles.modalStatusBadge, { backgroundColor: getStatusColor(selectedUser.status) + '20' }]}>
                        <Text style={[styles.modalStatusText, { color: getStatusColor(selectedUser.status) }]}>
                          {selectedUser.status}
                        </Text>
                      </View>
                    </View>
                  </View>
                  
                  <View style={styles.modalSection}>
                    <Text style={styles.modalSectionTitle}>{t('system_admin.basic_info')}</Text>
                    
                    <View style={styles.modalDetailRow}>
                      <Ionicons name="call-outline" size={18} color="#666" />
                      <Text style={styles.modalDetailLabel}>{t('system_admin.phone_label')}</Text>
                      <Text style={styles.modalDetailValue}>{selectedUser.phone || 'No phone'}</Text>
                    </View>
                    
                    {selectedUser.business_name && (
                      <View style={styles.modalDetailRow}>
                        <Ionicons name="business-outline" size={18} color="#666" />
                        <Text style={styles.modalDetailLabel}>{t('system_admin.business_label')}</Text>
                        <Text style={styles.modalDetailValue}>{selectedUser.business_name}</Text>
                      </View>
                    )}
                    
                    {selectedUser.business_location && (
                      <View style={styles.modalDetailRow}>
                        <Ionicons name="location-outline" size={18} color="#666" />
                        <Text style={styles.modalDetailLabel}>{t('system_admin.location_label')}</Text>
                        <Text style={styles.modalDetailValue}>{selectedUser.business_location}</Text>
                      </View>
                    )}
                    
                    <View style={styles.modalDetailRow}>
                      <Ionicons name="calendar-outline" size={18} color="#666" />
                      <Text style={styles.modalDetailLabel}>{t('system_admin.created_label')}</Text>
                      <Text style={styles.modalDetailValue}>{formatDate(selectedUser.created_at)}</Text>
                    </View>
                    
                    {selectedUser.last_seen && (
                      <View style={styles.modalDetailRow}>
                        <Ionicons name="time-outline" size={18} color="#666" />
                        <Text style={styles.modalDetailLabel}>{t('system_admin.last_seen_label')}</Text>
                        <Text style={styles.modalDetailValue}>
                          {formatTimeAgo(selectedUser.last_seen)}
                        </Text>
                      </View>
                    )}
                    
                    <View style={styles.modalDetailRow}>
                      <Ionicons name="wifi-outline" size={18} color="#666" />
                      <Text style={styles.modalDetailLabel}>{t('system_admin.status_label')}</Text>
                      <Text style={[
                        styles.modalDetailValue,
                        { color: selectedUser.is_online ? '#2ecc71' : '#e74c3c' }
                      ]}>
                        {selectedUser.is_online ? t('system_admin.online') : t('system_admin.offline')}
                      </Text>
                    </View>
                  </View>
                  
                  <View style={styles.modalActions}>
                    <TouchableOpacity 
                      style={[styles.modalActionButton, styles.trackModalButton]}
                      onPress={() => {
                        setUserDetailModal(false);
                        viewUserLogs(selectedUser);
                      }}
                    >
                      <Ionicons name="eye-outline" size={18} color="#3498db" />
                      <Text style={styles.trackModalButtonText}>{t('system_admin.view_logs')}</Text>
                    </TouchableOpacity>
                    
                    {/* ✅ Updated Modal Actions */}
                    {selectedUser.status === 'approved' ? (
                      <TouchableOpacity 
                        style={[styles.modalActionButton, styles.disableModalButton]}
                        onPress={() => {
                          setUserDetailModal(false);
                          disableUser(selectedUser.id, selectedUser.full_name || selectedUser.email);
                        }}
                      >
                        <Ionicons name="ban-outline" size={18} color="#e74c3c" />
                        <Text style={styles.disableModalButtonText}>{t('system_admin.disable')}</Text>
                      </TouchableOpacity>
                    ) : (
                      <TouchableOpacity 
                        style={[styles.modalActionButton, styles.enableModalButton]}
                        onPress={() => {
                          setUserDetailModal(false);
                          enableUser(selectedUser.id, selectedUser.full_name || selectedUser.email);
                        }}
                      >
                        <Ionicons name="checkmark-circle-outline" size={18} color="#27ae60" />
                        <Text style={styles.enableModalButtonText}>{t('system_admin.enable')}</Text>
                      </TouchableOpacity>
                    )}
                  </View>
                </ScrollView>
              </>
            )}
          </View>
        </View>
      </Modal>

      {/* User Logs Modal */}
      <Modal
        animationType="slide"
        transparent={true}
        visible={userLogsModal}
        onRequestClose={() => setUserLogsModal(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.logsModalContent}>
            {selectedUser && (
              <>
                <View style={styles.modalHeader}>
                  <View style={styles.modalHeaderLeft}>
                    <View style={styles.logsUserAvatar}>
                      <Text style={styles.logsUserAvatarText}>
                        {selectedUser.full_name?.charAt(0) || selectedUser.email.charAt(0)}
                      </Text>
                    </View>
                    <View>
                      <Text style={styles.logsModalTitle}>{t('system_admin.user_logs_title').replace('{name}', selectedUser.full_name)}</Text>
                      <Text style={styles.logsModalSubtitle}>{selectedUser.email}</Text>
                    </View>
                  </View>
                  <TouchableOpacity onPress={() => setUserLogsModal(false)}>
                    <Ionicons name="close" size={24} color="#333" />
                  </TouchableOpacity>
                </View>
                
                <View style={styles.logsCount}>
                  <Ionicons name="list" size={16} color="#3498db" />
                  <Text style={styles.logsCountText}>
                    {t('system_admin.total_logs').replace('{n}', userSpecificLogs.length.toString())}
                  </Text>
                </View>
                
                {userSpecificLogs.length > 0 ? (
                  <FlatList
                    data={userSpecificLogs}
                    renderItem={({ item }) => (
                      <View style={styles.logItem}>
                        <View style={styles.logItemHeader}>
                          <Text style={styles.logItemAction}>{item.action}</Text>
                          <Text style={styles.logItemTime}>{formatTimeAgo(item.created_at)}</Text>
                        </View>
                        <Text style={styles.logItemEndpoint}>{item.endpoint}</Text>
                        {item.details && (
                          <Text style={styles.logItemDetails}>
                            {t('system_admin.details_prefix')} {JSON.stringify(item.details).substring(0, 80)}...
                          </Text>
                        )}
                        <View style={styles.logItemFooter}>
                          <View style={[
                            styles.logItemStatus,
                            { backgroundColor: getStatusColor(item.status) + '20' }
                          ]}>
                            <Text style={[styles.logItemStatusText, { color: getStatusColor(item.status) }]}>
                              {item.status}
                            </Text>
                          </View>
                          <Text style={styles.logItemIp}>IP: {item.ip_address}</Text>
                        </View>
                      </View>
                    )}
                    keyExtractor={item => item.id}
                    contentContainerStyle={styles.logsList}
                  />
                ) : (
                  <View style={styles.emptyState}>
                    <Ionicons name="time-outline" size={40} color="#bdc3c7" />
                    <Text style={styles.emptyStateText}>{t('system_admin.no_logs')}</Text>
                    <Text style={styles.emptyStateSubtext}>
                      {t('system_admin.no_user_logs')}
                    </Text>
                  </View>
                )}
              </>
            )}
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

// Styles
const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8f9fa',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f5f5f5',
    padding: 20,
  },
  loadingText: {
    marginTop: 10,
    fontSize: 16,
    color: '#666',
    fontWeight: '500',
  },
  loadingSubtext: {
    marginTop: 5,
    fontSize: 12,
    color: '#999',
    textAlign: 'center',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 15,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  headerTitle: {
    flex: 1,
  },
  headerTitleText: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  headerSubtitle: {
    fontSize: 12,
    color: '#7f8c8d',
    marginTop: 2,
  },
  headerActions: {
    flexDirection: 'row',
  },
  headerButton: {
    padding: 8,
    marginLeft: 8,
  },
  tabsContainer: {
    flexDirection: 'row',
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  tabButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 15,
    borderBottomWidth: 3,
    borderBottomColor: 'transparent',
    gap: 8,
  },
  tabButtonActive: {
    borderBottomColor: '#3498db',
  },
  tabText: {
    fontSize: 14,
    color: '#95a5a6',
    fontWeight: '500',
  },
  tabTextActive: {
    color: '#3498db',
    fontWeight: '600',
  },
  content: {
    flex: 1,
  },
  welcomeCard: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: '#fff',
    margin: 16,
    padding: 20,
    borderRadius: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 6,
    elevation: 3,
  },
  welcomeText: {
    flex: 1,
  },
  welcomeTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 4,
  },
  welcomeMessage: {
    fontSize: 14,
    color: '#7f8c8d',
    marginBottom: 8,
  },
  welcomeTimestamp: {
    fontSize: 11,
    color: '#95a5a6',
  },
  welcomeIcon: {
    marginLeft: 10,
  },
  section: {
    backgroundColor: '#fff',
    marginHorizontal: 16,
    marginBottom: 16,
    padding: 20,
    borderRadius: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 6,
    elevation: 3,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 15,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  sectionSubtitle: {
    fontSize: 12,
    color: '#7f8c8d',
    marginBottom: 15,
  },
  statsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  statCard: {
    width: '48%',
    backgroundColor: '#f8f9fa',
    padding: 15,
    borderRadius: 12,
    marginBottom: 12,
    borderLeftWidth: 4,
  },
  statHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  statIconContainer: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  statValue: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 4,
  },
  statLabel: {
    fontSize: 12,
    color: '#2c3e50',
    fontWeight: '600',
  },
  statSubtitle: {
    fontSize: 10,
    color: '#7f8c8d',
  },
  systemStatusGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  statusItem: {
    width: '48%',
    alignItems: 'center',
    marginBottom: 15,
  },
  statusIndicator: {
    width: 12,
    height: 12,
    borderRadius: 6,
    marginBottom: 6,
  },
  statusLabel: {
    fontSize: 12,
    color: '#7f8c8d',
    marginBottom: 4,
  },
  statusValue: {
    fontSize: 12,
    fontWeight: '600',
  },
  webSocketStats: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: '#f8f9fa',
    padding: 15,
    borderRadius: 10,
    marginTop: 10,
  },
  webSocketStat: {
    alignItems: 'center',
    flex: 1,
  },
  webSocketDivider: {
    width: 1,
    height: 30,
    backgroundColor: '#e0e0e0',
  },
  webSocketStatValue: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 2,
  },
  webSocketStatLabel: {
    fontSize: 10,
    color: '#7f8c8d',
    textAlign: 'center',
  },
  filterRow: {
    marginBottom: 15,
  },
  filterContainer: {
    flexDirection: 'row',
    paddingVertical: 5,
  },
  filterButton: {
    paddingHorizontal: 15,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: '#f8f9fa',
    borderWidth: 1,
    borderColor: '#e0e0e0',
    marginRight: 10,
  },
  filterButtonActive: {
    backgroundColor: '#3498db',
    borderColor: '#3498db',
  },
  filterButtonText: {
    fontSize: 12,
    color: '#666',
  },
  filterButtonTextActive: {
    color: 'white',
    fontWeight: '600',
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f8f9fa',
    paddingHorizontal: 15,
    paddingVertical: 12,
    borderRadius: 25,
    borderWidth: 1,
    borderColor: '#e0e0e0',
    marginBottom: 15,
  },
  searchInput: {
    flex: 1,
    marginLeft: 10,
    fontSize: 14,
    color: '#333',
  },
  logFilterContainer: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 15,
  },
  logFilterButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: '#f8f9fa',
    borderWidth: 1,
    borderColor: '#e0e0e0',
    marginHorizontal: 4,
    gap: 6,
  },
  logFilterButtonActive: {
    backgroundColor: '#3498db',
    borderColor: '#3498db',
  },
  logFilterText: {
    fontSize: 12,
    color: '#666',
  },
  logFilterTextActive: {
    color: 'white',
    fontWeight: '600',
  },
  emptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 30,
  },
  emptyStateText: {
    marginTop: 10,
    color: '#95a5a6',
    fontSize: 14,
    fontWeight: '500',
  },
  emptyStateSubtext: {
    marginTop: 5,
    color: '#bdc3c7',
    fontSize: 12,
    textAlign: 'center',
  },
  userCard: {
    backgroundColor: '#f8f9fa',
    borderRadius: 12,
    padding: 15,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#e0e0e0',
  },
  userHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  userAvatar: {
    width: 45,
    height: 45,
    borderRadius: 22.5,
    backgroundColor: '#3498db',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12,
    position: 'relative',
  },
  userAvatarText: {
    color: 'white',
    fontSize: 18,
    fontWeight: 'bold',
  },
  onlineIndicator: {
    position: 'absolute',
    bottom: 0,
    right: 0,
    width: 12,
    height: 12,
    borderRadius: 6,
    backgroundColor: '#2ecc71',
    borderWidth: 2,
    borderColor: '#fff',
  },
  userInfo: {
    flex: 1,
  },
  userName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#2c3e50',
    marginBottom: 2,
  },
  userEmail: {
    fontSize: 12,
    color: '#7f8c8d',
    marginBottom: 6,
  },
  userMeta: {
    flexDirection: 'row',
    gap: 6,
  },
  roleBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
  },
  roleText: {
    fontSize: 10,
    fontWeight: 'bold',
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
    gap: 4,
  },
  statusText: {
    fontSize: 10,
    fontWeight: 'bold',
  },
  userFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: '#e0e0e0',
    marginBottom: 10,
  },
  userDetail: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  userDetailText: {
    fontSize: 12,
    color: '#7f8c8d',
    marginLeft: 6,
  },
  userDate: {
    fontSize: 11,
    color: '#95a5a6',
  },
  userLastSeen: {
    fontSize: 11,
    color: '#95a5a6',
  },
  userActions: {
    flexDirection: 'row',
    gap: 10,
  },
  userActionButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 8,
    borderRadius: 8,
    gap: 6,
  },
  trackButton: {
    backgroundColor: '#e3f2fd',
    borderWidth: 1,
    borderColor: '#bbdefb',
  },
  detailsButton: {
    backgroundColor: '#f8f9fa',
    borderWidth: 1,
    borderColor: '#e0e0e0',
  },
  // ✅ NEW: Disable Button Styles
  disableButton: {
    backgroundColor: '#fdeaea',
    borderWidth: 1,
    borderColor: '#f5c6cb',
  },
  disableButtonText: {
    color: '#e74c3c',
    fontSize: 12,
    fontWeight: '600',
  },
  enableButton: {
    backgroundColor: '#e8f6ef',
    borderWidth: 1,
    borderColor: '#c8e6c9',
  },
  enableButtonText: {
    color: '#27ae60',
    fontSize: 12,
    fontWeight: '600',
  },
  trackButtonText: {
    color: '#1976d2',
    fontSize: 12,
    fontWeight: '600',
  },
  detailsButtonText: {
    color: '#666',
    fontSize: 12,
    fontWeight: '600',
  },
  approveButtonText: {
    color: '#27ae60',
    fontSize: 12,
    fontWeight: '600',
  },
  timeFilter: {
    flexDirection: 'row',
    backgroundColor: '#f8f9fa',
    borderRadius: 20,
    padding: 4,
  },
  timeFilterButton: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
  },
  timeFilterButtonActive: {
    backgroundColor: '#3498db',
  },
  timeFilterText: {
    fontSize: 12,
    color: '#666',
  },
  timeFilterTextActive: {
    color: 'white',
    fontWeight: '600',
  },
  logCard: {
    backgroundColor: '#f8f9fa',
    borderRadius: 12,
    padding: 15,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#e0e0e0',
  },
  logHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 10,
  },
  logUser: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  logDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    marginRight: 10,
  },
  logUserName: {
    fontSize: 14,
    fontWeight: '600',
    color: '#2c3e50',
    marginBottom: 2,
  },
  logAction: {
    fontSize: 12,
    color: '#3498db',
    fontWeight: '500',
  },
  logTime: {
    fontSize: 11,
    color: '#95a5a6',
  },
  logBody: {
    marginBottom: 10,
  },
  logEndpoint: {
    fontSize: 11,
    color: '#7f8c8d',
    fontFamily: 'monospace',
    marginBottom: 6,
  },
  logDetails: {
    fontSize: 10,
    color: '#95a5a6',
    fontFamily: 'monospace',
    backgroundColor: '#fff',
    padding: 8,
    borderRadius: 6,
    marginBottom: 6,
  },
  logIp: {
    fontSize: 10,
    color: '#3498db',
    fontFamily: 'monospace',
  },
  logFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: '#e0e0e0',
  },
  logStatusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
    gap: 4,
  },
  logStatusText: {
    fontSize: 10,
    fontWeight: 'bold',
  },
  logDateTime: {
    fontSize: 10,
    color: '#95a5a6',
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: '#fff',
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    maxHeight: '85%',
  },
  logsModalContent: {
    backgroundColor: '#fff',
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    maxHeight: '90%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  modalHeaderLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    flex: 1,
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  logsModalTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  logsModalSubtitle: {
    fontSize: 12,
    color: '#7f8c8d',
    marginTop: 2,
  },
  modalBody: {
    padding: 20,
  },
  modalUserHeader: {
    alignItems: 'center',
    marginBottom: 20,
  },
  modalAvatar: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: '#3498db',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 15,
    position: 'relative',
  },
  modalAvatarText: {
    color: 'white',
    fontSize: 32,
    fontWeight: 'bold',
  },
  modalOnlineIndicator: {
    position: 'absolute',
    bottom: 5,
    right: 5,
    width: 16,
    height: 16,
    borderRadius: 8,
    backgroundColor: '#2ecc71',
    borderWidth: 2,
    borderColor: '#fff',
  },
  modalUserName: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 5,
    textAlign: 'center',
  },
  modalUserEmail: {
    fontSize: 16,
    color: '#7f8c8d',
    marginBottom: 15,
    textAlign: 'center',
  },
  modalBadges: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 10,
  },
  modalRoleBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 15,
  },
  modalRoleText: {
    fontSize: 12,
    fontWeight: 'bold',
  },
  modalStatusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 15,
  },
  modalStatusText: {
    fontSize: 12,
    fontWeight: 'bold',
  },
  modalSection: {
    marginBottom: 20,
  },
  modalSectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#2c3e50',
    marginBottom: 15,
  },
  modalDetailRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: '#f0f0f0',
  },
  modalDetailLabel: {
    fontSize: 14,
    color: '#666',
    marginLeft: 10,
    marginRight: 10,
    width: 120,
  },
  modalDetailValue: {
    fontSize: 14,
    color: '#2c3e50',
    flex: 1,
    fontWeight: '500',
  },
  modalActions: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 10,
    marginTop: 20,
  },
  modalActionButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 12,
    borderRadius: 8,
    gap: 8,
  },
  trackModalButton: {
    backgroundColor: '#e3f2fd',
    borderWidth: 1,
    borderColor: '#bbdefb',
  },
  trackModalButtonText: {
    color: '#1976d2',
    fontSize: 14,
    fontWeight: '600',
  },
  // ✅ NEW: Modal disable/enable button styles
  disableModalButton: {
    backgroundColor: '#fdeaea',
    borderWidth: 1,
    borderColor: '#f5c6cb',
  },
  disableModalButtonText: {
    color: '#e74c3c',
    fontSize: 14,
    fontWeight: '600',
  },
  enableModalButton: {
    backgroundColor: '#e8f6ef',
    borderWidth: 1,
    borderColor: '#c8e6c9',
  },
  enableModalButtonText: {
    color: '#27ae60',
    fontSize: 14,
    fontWeight: '600',
  },
  logsUserAvatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#3498db',
    alignItems: 'center',
    justifyContent: 'center',
  },
  logsUserAvatarText: {
    color: 'white',
    fontSize: 18,
    fontWeight: 'bold',
  },
  logsCount: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    backgroundColor: '#f8f9fa',
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
    gap: 8,
  },
  logsCountText: {
    fontSize: 14,
    color: '#2c3e50',
    fontWeight: '500',
  },
  logsList: {
    padding: 16,
  },
  logItem: {
    backgroundColor: '#f8f9fa',
    padding: 15,
    borderRadius: 12,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#e0e0e0',
  },
  logItemHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  logItemAction: {
    fontSize: 14,
    fontWeight: '600',
    color: '#2c3e50',
  },
  logItemTime: {
    fontSize: 11,
    color: '#95a5a6',
  },
  logItemEndpoint: {
    fontSize: 12,
    color: '#7f8c8d',
    fontFamily: 'monospace',
    marginBottom: 8,
  },
  logItemDetails: {
    fontSize: 11,
    color: '#95a5a6',
    marginBottom: 8,
  },
  logItemFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  logItemStatus: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
  },
  logItemStatusText: {
    fontSize: 10,
    fontWeight: 'bold',
  },
  logItemIp: {
    fontSize: 10,
    color: '#3498db',
    fontFamily: 'monospace',
  },
});