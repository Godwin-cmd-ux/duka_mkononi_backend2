// C:\Users\HP\myApp\Duka_mkononi\app\system_admin\notify.tsx
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as Notifications from 'expo-notifications';
import { useRouter } from 'expo-router';
import React, { useEffect, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    FlatList,
    KeyboardAvoidingView,
    Modal,
    Platform,
    SafeAreaView,
    ScrollView,
    StatusBar,
    StyleSheet,
    Switch,
    Text,
    TextInput,
    TouchableOpacity,
    View
} from 'react-native';
import { ALL_LANGUAGES, LANGUAGE_FLAGS, LANGUAGE_NAMES, useLang, type Lang } from '../../context/LanguageContext';
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
  status: 'approved' | 'pending' | 'rejected' | 'inactive';
  business_name?: string;
}

interface Notification {
  id: string;
  title: string;
  message: string;
  notification_type: 'all' | 'admins' | 'sellers' | 'clients' | 'specific';
  recipients: any[]; // Array of { id, email, name }
  sent_at: string;
  status: 'sent' | 'failed' | 'pending';
  sender_name?: string;
  sender_id?: string;
  delivery_method?: string;
  email_sent?: boolean;
  push_sent?: boolean;
}

// Language-specific translation structure
interface LanguageContent {
  title: string;
  message: string;
}

type Translations = Record<Lang, LanguageContent>;

const createEmptyTranslations = (): Translations => {
  const translations: any = {};
  ALL_LANGUAGES.forEach(lang => {
    translations[lang] = { title: '', message: '' };
  });
  return translations as Translations;
};

export default function NotifyScreen() {
  const router = useRouter();
  const { t, lang } = useLang();
  const { signOut } = useSession();

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

  const [loading, setLoading] = useState(true);
  const [user, setUser] = useState<any>(null);
  const [users, setUsers] = useState<User[]>([]);
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [systemReady, setSystemReady] = useState(false);
  
  // Notification form state
  const [activeLanguage, setActiveLanguage] = useState<Lang>('sw');
  const [translations, setTranslations] = useState<Translations>(createEmptyTranslations());
  const [notificationType, setNotificationType] = useState<'all' | 'admins' | 'sellers' | 'clients' | 'specific'>('all');
  const [selectedUsers, setSelectedUsers] = useState<string[]>([]);
  const [showRecipientModal, setShowRecipientModal] = useState(false);
  const [sending, setSending] = useState(false);
  
  // Filter states
  const [searchQuery, setSearchQuery] = useState('');
  const [showAdmins, setShowAdmins] = useState(true);
  const [showSellers, setShowSellers] = useState(true);
  const [showClients, setShowClients] = useState(true);
  
  // Stats
  const [stats, setStats] = useState({
    totalSent: 0,
    sentToday: 0,
    recipientsCount: 0,
    successRate: 100,
    byType: {
      all: 0,
      admins: 0,
      sellers: 0,
      clients: 0,
      specific: 0
    }
  });

  // Computed translation helpers
  const currentTranslation = translations[activeLanguage];

  const getLanguageStatus = (lang: Lang): 'complete' | 'partial' | 'empty' => {
    const t = translations[lang];
    if (t.title.trim() && t.message.trim()) return 'complete';
    if (t.title.trim() || t.message.trim()) return 'partial';
    return 'empty';
  };

  const completedCount = ALL_LANGUAGES.filter(l => getLanguageStatus(l) === 'complete').length;
  const allLanguagesComplete = completedCount === ALL_LANGUAGES.length;

  const updateTranslation = (field: 'title' | 'message', value: string) => {
    setTranslations(prev => ({
      ...prev,
      [activeLanguage]: {
        ...prev[activeLanguage],
        [field]: value
      }
    }));
  };

  useEffect(() => {
    initializeScreen();
    setupPushNotifications();
  }, [lang]);

  useEffect(() => {
    const stop = registerLive<any>(
      'admin:notifications',
      async () => {
        const token = await AsyncStorage.getItem('userToken');
        if (!token) throw new Error('no token');
        const res = await fetchWithTimeout(`${API_BASE_URL}/api/admin/notifications`, {
          headers: { 'Authorization': `Bearer ${token}` }
        });
        if (!res.ok) throw new Error('sync failed');
        const data = await res.json();
        return data;
      },
      (data) => { setNotifications(data); setLoading(false); }
    );
    return stop;
  }, [lang]);

  const setupPushNotifications = async () => {
    try {
      const { status } = await Notifications.requestPermissionsAsync();
      if (status !== 'granted') {
        console.log('Push notifications permission denied');
      }
    } catch (error) {
      console.error('Error setting up push notifications:', error);
    }
  };

  const initializeScreen = async () => {
    try {
      console.log('📨 Inaanzisha skrini ya taarifa...');
      
      // Check authentication
      const token = await AsyncStorage.getItem('userToken');
      const userDataStr = await AsyncStorage.getItem('userData');
      
      if (!token || !userDataStr) {
        Alert.alert(t('app.error'), t('notify.error_auth'));
        await signOut();
        router.replace('/(tabs)');
        return;
      }

      const userData = JSON.parse(userDataStr);
      
      // Check if user is system admin
      const userEmail = userData.email || '';
      const isAdmin = userEmail === "cosmavictorini1994@gmail.com" || userData.role === 'admin';
      
      if (!isAdmin) {
        Alert.alert(
          t('notify.error_permission'),
          t('notify.error_permission_text'),
          [{ text: t('notify.back'), onPress: () => router.back() }]
        );
        return;
      }
      
      setUser(userData);
      
      // Check if notification system is ready
      await checkNotificationSystem();
      
      // Fetch data
      await Promise.all([
        fetchUsers(),
        fetchNotifications(),
        fetchNotificationStats()
      ]);
      
    } catch (error) {
      console.error('❌ Error initializing screen:', error);
      Alert.alert(t('app.error'), t('notify.error_init'));
      setLoading(false);
    }
  };

  const checkNotificationSystem = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      const cached = await getCache<any>('admin:notifications:check');
      if (cached?.exists) { setSystemReady(true); }
      const response = await fetchWithTimeout(`${API_BASE_URL}/api/admin/notifications/check`, {
        headers: {
          'Authorization': `Bearer ${token}`,
        }
      });

      if (response.ok) {
        const data = await response.json();
        setCache('admin:notifications:check', data).catch(() => {});
        if (data.exists) {
          setSystemReady(true);
          console.log('✅ Notification system is ready');
        } else {
          // Try to setup the system
          await setupNotificationSystem();
        }
      }
    } catch (error) {
      console.log('Notification system check failed:', error);
      const cached = await getCache<any>('admin:notifications:check');
      if (cached?.exists) { setSystemReady(true); }
      // Continue anyway - we'll handle errors when sending
    }
  };

  const setupNotificationSystem = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!(await requireNetwork())) return;
      const response = await fetchWithTimeout(`${API_BASE_URL}/api/admin/setup/notifications`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
        }
      });

      if (response.ok) {
        setSystemReady(true);
        console.log('✅ Notification system setup successful');
      }
    } catch (error) {
      console.error('Failed to setup notification system:', error);
    }
  };

  const fetchNotificationStats = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      const cached = await getCache<any>('d:admin:notifications:stats');
      if (cached) { setStats(cached); }
      const response = await fetchWithTimeout(`${API_BASE_URL}/api/admin/notifications/stats`, {
        headers: {
          'Authorization': `Bearer ${token}`,
        }
      });

      if (response.ok) {
        const data = await response.json();
        const nextStats = {
          totalSent: data.total_sent || 0,
          sentToday: data.sent_today || 0,
          recipientsCount: data.total_recipients || 0,
          successRate: data.success_rate || 100,
          byType: data.by_type || {
            all: 0,
            admins: 0,
            sellers: 0,
            clients: 0,
            specific: 0
          }
        };
        setStats(nextStats);
        setCache('d:admin:notifications:stats', nextStats).catch(() => {});
      }
    } catch (error) {
      console.error('Error fetching stats:', error);
      const cached = await getCache<any>('d:admin:notifications:stats');
      if (cached) { setStats(cached); }
    }
  };

  const fetchUsers = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;

      const cached = await getCache<any>('system:users');
      if (Array.isArray(cached)) { setUsers(cached.filter((u: User) => u.status === 'approved')); }

      console.log('📡 Inapakua watumiaji...');
      
      const response = await fetchWithTimeout(`${API_BASE_URL}/api/admin/users`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        }
      });

      if (response.ok) {
        const data = await response.json();
        const usersArray = data.users || data || [];
        setUsers(usersArray.filter((u: User) => u.status === 'approved'));
        setCache('system:users', Array.isArray(usersArray) ? usersArray : []).catch(() => {});
      } else {
        console.error('Failed to fetch users:', response.status);
        // Use sample data as fallback
        setUsers(getSampleUsers());
      }
    } catch (error) {
      console.error('❌ Error fetching users:', error);
      const cached = await getCache<any>('system:users');
      if (Array.isArray(cached)) { setUsers(cached.filter((u: User) => u.status === 'approved')); return; }
      setUsers(getSampleUsers());
    }
  };

  const getSampleUsers = (): User[] => {
    return [
      {
        id: '1',
        email: 'cosmavictorini1994@gmail.com',
        role: 'admin',
        full_name: 'Victorini Cosma',
        phone: '0712345678',
        status: 'approved'
      },
      {
        id: '2',
        email: 'godwinfranklin419@gmail.com',
        role: 'admin',
        full_name: 'Godwin Franklin',
        phone: '0756789123',
        status: 'approved'
      }
    ];
  };

  const fetchNotifications = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;

      const cached = await getCache<any>('admin:notifications');
      if (cached) { setNotifications(cached); }

      const response = await fetchWithTimeout(`${API_BASE_URL}/api/admin/notifications`, {
        headers: {
          'Authorization': `Bearer ${token}`,
        }
      });

      if (response.ok) {
        const data = await response.json();
        setNotifications(data);
        setCache('admin:notifications', data).catch(() => {});
        console.log(`✅ Loaded ${data.length} notifications from server`);
      } else {
        console.log('⚠️ Using sample notifications');
        setNotifications(getSampleNotifications());
      }
    } catch (error) {
      console.error('Error fetching notifications:', error);
      const cached = await getCache<any>('admin:notifications');
      if (cached) { setNotifications(cached); return; }
      setNotifications(getSampleNotifications());
    } finally {
      setLoading(false);
    }
  };

  const getSampleNotifications = (): Notification[] => {
    return [
      {
        id: '1',
        title: 'Mfumo wa Taarifa Umewashwa',
        message: 'Mfumo mpya wa kutuma taarifa kwa watumiaji wote umewashwa kikamilifu. Sasa unaweza kutuma taarifa kupitia mfumo huu.',
        notification_type: 'all',
        recipients: [{ id: 'all', email: 'all@dukamkononi.com', name: 'Watumiaji Wote' }],
        sent_at: new Date().toISOString(),
        status: 'sent',
        sender_name: 'System Admin'
      }
    ];
  };

  const handleSendNotification = async () => {
    // Validation: Check all languages have content
    const missingLanguages = ALL_LANGUAGES.filter(lang => {
      const t = translations[lang];
      return !t.title.trim() || !t.message.trim();
    });

    if (missingLanguages.length > 0) {
      const missingNames = missingLanguages.map(l => `${LANGUAGE_FLAGS[l]} ${LANGUAGE_NAMES[l]}`).join(', ');
      Alert.alert(
        t('notify.language_incomplete'),
        t('notify.language_incomplete_msg') + '\n\n' + missingNames
      );
      // Switch to first missing language
      setActiveLanguage(missingLanguages[0]);
      return;
    }

    if (notificationType === 'specific' && selectedUsers.length === 0) {
      Alert.alert(t('app.error'), t('notify.missing_recipients'));
      return;
    }

    setSending(true);
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        Alert.alert(t('app.error'), t('notify.error_auth_short'));
        return;
      }

      if (!(await requireNetwork())) return;

      // Prepare request body with all language translations
      const requestBody: any = {
        translations: translations,
        notification_type: notificationType,
        delivery_method: 'email'
      };

      if (notificationType === 'specific') {
        requestBody.recipient_ids = selectedUsers;
      }

      console.log('📤 Sending multilingual notification to server...', requestBody);

      // Send to backend API
      const response = await fetchWithTimeout(`${API_BASE_URL}/api/admin/notifications`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestBody),
      });

      if (!response.ok) {
        const errorText = await response.text();
        console.error('❌ Server response error:', response.status, errorText);
        throw new Error(`Server returned ${response.status}: ${errorText}`);
      }

      const result = await response.json();
      
      console.log('✅ Notification sent successfully:', result);

      // Also send local push notification
      await sendLocalNotification();

      // Refresh data
      await Promise.all([
        fetchNotifications(),
        fetchNotificationStats()
      ]);

      // Reset form
      setTranslations(createEmptyTranslations());
      setActiveLanguage('sw');
      setNotificationType('all');
      setSelectedUsers([]);

      Alert.alert(
        t('notify.success_title'),
        t('notify.success_sent', { n: ALL_LANGUAGES.length }),
        [{ text: t('notify.ok') }]
      );

    } catch (error: any) {
      console.error('❌ Error sending notification:', error.message || error);
      
      Alert.alert(
        t('app.error'),
        error.message || t('notify.error_send'),
        [{ text: t('notify.ok') }]
      );
    } finally {
      setSending(false);
    }
  };

  const sendLocalNotification = async () => {
    try {
      const firstTitle = translations.sw.title || translations.en.title || 'Notification';
      await Notifications.scheduleNotificationAsync({
        content: {
          title: '📢 Taarifa Imetumwa',
          body: `"${firstTitle}" imetumwa kikamilifu kwa lugha ${completedCount}/${ALL_LANGUAGES.length}`,
          data: { type: 'admin_notification_sent' },
        },
        trigger: null,
      });
    } catch (error) {
      console.error('Error scheduling notification:', error);
    }
  };

  const toggleUserSelection = (userId: string) => {
    setSelectedUsers(prev => {
      if (prev.includes(userId)) {
        return prev.filter(id => id !== userId);
      } else {
        return [...prev, userId];
      }
    });
  };

  const handleUserSelection = (user: User) => {
    toggleUserSelection(user.id);
  };

  const selectAllUsers = () => {
    const filteredUsers = getFilteredUsers();
    const allIds = filteredUsers.map(u => u.id);
    
    if (selectedUsers.length === allIds.length) {
      // Deselect all
      setSelectedUsers([]);
    } else {
      // Select all
      setSelectedUsers(allIds);
    }
  };

  const sendTestNotification = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        Alert.alert(t('app.error'), t('notify.error_auth'));
        return;
      }

      if (!(await requireNetwork())) return;

      Alert.prompt(
        t('notify.send_test'),
        t('notify.send_test_prompt'),
        [
          { text: t('notify.cancel'), style: 'cancel' },
          {
            text: t('notify.send'),
            onPress: async (email) => {
              if (!email || !email.includes('@')) {
                Alert.alert(t('app.error'), t('notify.invalid_email'));
                return;
              }

              try {
                const response = await fetchWithTimeout(`${API_BASE_URL}/api/admin/notifications/test`, {
                  method: 'POST',
                  headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                  },
                  body: JSON.stringify({ email }),
                });

                if (response.ok) {
                  Alert.alert(
                    t('notify.test_sent'),
                    t('notify.send_test_success', { email }),
                    [{ text: t('notify.ok') }]
                  );
                } else {
                  Alert.alert(t('app.error'), t('notify.send_test_error'));
                }
              } catch (error) {
                Alert.alert(t('app.error'), t('notify.error_network'));
              }
            }
          }
        ],
        'plain-text',
        user?.email || ''
      );
    } catch (error) {
      console.error('Error in test notification:', error);
    }
  };

  const handleLogout = async () => {
    Alert.alert(
      t('profile.logout_title'),
      t('notify.logout_confirm'),
      [
        { text: t('notify.cancel'), style: 'cancel' },
        {
          text: t('notify.logout'),
          style: 'destructive',
          onPress: async () => {
            try {
              await signOut();
              router.replace('/(tabs)');
            } catch (error) {
              console.error('Error during logout:', error);
              Alert.alert(t('app.error'), t('notify.error_logout'));
            }
          }
        }
      ]
    );
  };

  const getFilteredUsers = () => {
    return users.filter(user => {
      // Filter by search query
      const matchesSearch = 
        user.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
        user.full_name?.toLowerCase().includes(searchQuery.toLowerCase()) ||
        user.phone?.includes(searchQuery) ||
        user.business_name?.toLowerCase().includes(searchQuery.toLowerCase());
      
      // Filter by role toggles
      const matchesRole = 
        (showAdmins && user.role === 'admin') ||
        (showSellers && user.role === 'seller') ||
        (showClients && user.role === 'client');
      
      return matchesSearch && matchesRole;
    });
  };

  const getRecipientCount = () => {
    switch (notificationType) {
      case 'all':
        return users.length;
      case 'admins':
        return users.filter(u => u.role === 'admin').length;
      case 'sellers':
        return users.filter(u => u.role === 'seller').length;
      case 'clients':
        return users.filter(u => u.role === 'client').length;
      case 'specific':
        return selectedUsers.length;
      default:
        return 0;
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

  const getRoleText = (role: string): string => {
    switch (role) {
      case 'admin': return t('notify.role_admin');
      case 'seller': return t('notify.role_seller');
      case 'client': return t('notify.role_client');
      default: return role;
    }
  };

  const getTypeColor = (type: string): string => {
    switch (type) {
      case 'all': return '#3498db';
      case 'admins': return '#9b59b6';
      case 'sellers': return '#f39c12';
      case 'clients': return '#2ecc71';
      case 'specific': return '#e74c3c';
      default: return '#95a5a6';
    }
  };

  const getTypeText = (type: string): string => {
    switch (type) {
      case 'all': return t('notify.type_all');
      case 'admins': return t('notify.type_admins');
      case 'sellers': return t('notify.type_sellers');
      case 'clients': return t('notify.type_clients');
      case 'specific': return t('notify.type_specific');
      default: return type;
    }
  };

  const formatTime = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) {
      return t('notify.time_now');
    } else if (diffMins < 60) {
      return t('notify.time_minutes', { n: diffMins });
    } else if (diffHours < 24) {
      return t('notify.time_hours', { n: diffHours });
    } else if (diffDays === 1) {
      return t('notify.time_yesterday');
    } else if (diffDays < 7) {
      return t('notify.time_days', { n: diffDays });
    } else {
      return date.toLocaleDateString(localeMap[lang] || 'sw-TZ', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
      });
    }
  };

  const renderUserItem = ({ item }: { item: User }) => {
    const isSelected = selectedUsers.includes(item.id);
    
    return (
      <TouchableOpacity 
        style={[
          styles.userItem,
          isSelected && styles.userItemSelected
        ]}
        onPress={() => handleUserSelection(item)}
      >
        <View style={styles.userCheckbox}>
          {isSelected ? (
            <Ionicons name="checkbox" size={24} color="#3498db" />
          ) : (
            <Ionicons name="square-outline" size={24} color="#bdc3c7" />
          )}
        </View>
        
        <View style={styles.userInfo}>
          <Text style={styles.userName} numberOfLines={1}>                {item.full_name || item.email}
          </Text>
          <Text style={styles.userEmail} numberOfLines={1}>
            {item.email}
          </Text>
          {item.business_name && (
            <Text style={styles.userBusiness} numberOfLines={1}>
              {item.business_name}
            </Text>
          )}
        </View>
        
        <View style={[styles.userRoleBadge, { backgroundColor: getRoleColor(item.role) + '20' }]}>
          <Text style={[styles.userRoleText, { color: getRoleColor(item.role) }]}>
            {getRoleText(item.role)}
          </Text>
        </View>
      </TouchableOpacity>
    );
  };

  const renderNotificationItem = ({ item }: { item: Notification }) => {
    const recipientNames = Array.isArray(item.recipients) 
      ? item.recipients.map(r => r.name || r.email).join(', ')
      : t('notify.recipients_all');
    
    return (
      <View style={styles.notificationCard}>
        <View style={styles.notificationHeader}>
          <View style={styles.notificationTitleRow}>
            <Text style={styles.notificationTitle} numberOfLines={1}>
              {item.title}
            </Text>
            <View style={[styles.notificationTypeBadge, { backgroundColor: getTypeColor(item.notification_type) + '20' }]}>
              <Text style={[styles.notificationTypeText, { color: getTypeColor(item.notification_type) }]}>
                {getTypeText(item.notification_type)}
              </Text>
            </View>
          </View>
          
          <Text style={styles.notificationTime}>
            {formatTime(item.sent_at)}
          </Text>
        </View>
        
        <Text style={styles.notificationMessage} numberOfLines={3}>
          {item.message}
        </Text>
        
        <View style={styles.notificationFooter}>
          <View style={styles.recipientsContainer}>
            <Ionicons name="people" size={14} color="#7f8c8d" />
            <Text style={styles.recipientsText} numberOfLines={1}>
              {recipientNames.length > 50 
                ? t('notify.recipients_count', { n: item.recipients.length })
                : recipientNames
              }
            </Text>
          </View>
          
          <View style={styles.notificationMeta}>
            <View style={[
              styles.statusBadge,
              item.status === 'sent' && styles.statusSent,
              item.status === 'failed' && styles.statusFailed,
            ]}>
              <Ionicons 
                name={item.status === 'sent' ? 'checkmark-circle' : 'close-circle'} 
                size={12} 
                color={item.status === 'sent' ? '#27ae60' : '#e74c3c'} 
              />
              <Text style={[
                styles.statusText,
                item.status === 'sent' && styles.statusSentText,
                item.status === 'failed' && styles.statusFailedText,
              ]}>
                {item.status === 'sent' ? t('notify.status_sent') : t('notify.status_failed')}
              </Text>
            </View>
            {item.sender_name && (                <Text style={styles.senderText}>{t('notify.sender_by', { name: item.sender_name })}</Text>
            )}
          </View>
        </View>
      </View>
    );
  };

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#3498db" />
        <Text style={styles.loadingText}>{t('notify.loading')}</Text>
      </View>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar barStyle="dark-content" backgroundColor="#fff" />
      
      {/* Header */}
      <View style={styles.header}>
        <View style={styles.headerTitle}>
          <Text style={styles.headerTitleText}>{t('notify.title')}</Text>
          <Text style={styles.headerSubtitle}>{t('notify.subtitle')}</Text>
        </View>
        
        <View style={styles.headerActions}>
          <TouchableOpacity onPress={sendTestNotification} style={styles.testButton}>
            <Ionicons name="paper-plane-outline" size={20} color="#fff" />
          </TouchableOpacity>
          <TouchableOpacity onPress={handleLogout} style={styles.logoutButton}>
            <Ionicons name="log-out-outline" size={24} color="#e74c3c" />
          </TouchableOpacity>
        </View>
      </View>

      <KeyboardAvoidingView 
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        style={styles.keyboardAvoid}
      >
        <ScrollView style={styles.content} showsVerticalScrollIndicator={false}>
          {/* System Status */}
          {!systemReady && (
            <View style={styles.systemWarning}>
              <Ionicons name="warning" size={20} color="#f39c12" />
              <Text style={styles.systemWarningText}>
                {t('notify.system_warning')}
              </Text>
            </View>
          )}

          {/* Stats Summary */}
          <View style={styles.statsSummary}>
            <View style={styles.statItem}>
              <Ionicons name="paper-plane" size={20} color="#3498db" />
              <Text style={styles.statValue}>{stats.totalSent}</Text>
              <Text style={styles.statLabel}>{t('notify.stats_sent')}</Text>
            </View>
            <View style={styles.statItem}>
              <Ionicons name="today" size={20} color="#2ecc71" />
              <Text style={styles.statValue}>{stats.sentToday}</Text>
              <Text style={styles.statLabel}>{t('notify.stats_today')}</Text>
            </View>
            <View style={styles.statItem}>
              <Ionicons name="people" size={20} color="#9b59b6" />
              <Text style={styles.statValue}>{stats.recipientsCount}</Text>
              <Text style={styles.statLabel}>{t('notify.stats_recipients')}</Text>
            </View>
            <View style={styles.statItem}>
              <Ionicons name="trending-up" size={20} color="#f39c12" />
              <Text style={styles.statValue}>{stats.successRate}%</Text>
              <Text style={styles.statLabel}>{t('notify.stats_success')}</Text>
            </View>
          </View>

          {/* Create Notification Form */}
          <View style={styles.formSection}>
            <Text style={styles.sectionTitle}>{t('notify.section_new')}</Text>
            
            {/* Multi-language Status Bar */}
            <View style={styles.languageStatusBar}>
              <View style={styles.languageStatusInfo}>
                <Ionicons name="language-outline" size={18} color="#3498db" />
                <Text style={styles.languageStatusText}>
                  {t('notify.language_status', { n: completedCount, total: ALL_LANGUAGES.length })}
                </Text>
              </View>
              <View style={styles.languageProgressDots}>
                {ALL_LANGUAGES.map(lang => {
                  const status = getLanguageStatus(lang);
                  return (
                    <View
                      key={lang}
                      style={[
                        styles.languageDot,
                        status === 'complete' && styles.languageDotComplete,
                        status === 'partial' && styles.languageDotPartial,
                        activeLanguage === lang && styles.languageDotActive,
                      ]}
                    />
                  );
                })}
              </View>
            </View>
            
            {/* Language Tabs */}
            <ScrollView 
              horizontal 
              showsHorizontalScrollIndicator={false} 
              style={styles.languageTabsContainer}
            >
              {ALL_LANGUAGES.map(lang => {
                const status = getLanguageStatus(lang);
                const isActive = activeLanguage === lang;
                return (
                  <TouchableOpacity
                    key={lang}
                    style={[
                      styles.languageTab,
                      isActive && styles.languageTabActive,
                      status === 'complete' && !isActive && styles.languageTabComplete,
                    ]}
                    onPress={() => setActiveLanguage(lang)}
                    activeOpacity={0.7}
                  >
                    <Text style={styles.languageTabFlag}>{LANGUAGE_FLAGS[lang]}</Text>
                    <Text style={[
                      styles.languageTabLabel,
                      isActive && styles.languageTabLabelActive,
                    ]}>
                      {LANGUAGE_NAMES[lang].length > 8 
                        ? LANGUAGE_NAMES[lang].substring(0, 6) + '...' 
                        : LANGUAGE_NAMES[lang]
                      }
                    </Text>
                    {status === 'complete' && (
                      <Ionicons name="checkmark-circle" size={14} color="#27ae60" />
                    )}
                    {status === 'empty' && !isActive && (
                      <View style={styles.languageTabDot} />
                    )}
                  </TouchableOpacity>
                );
              })}
            </ScrollView>
            
            {/* Language-specific Title Input */}
            <View style={styles.inputContainer}>
              <Text style={styles.inputLabel}>
                {t('notify.title_label', { lang: LANGUAGE_NAMES[activeLanguage] })}
              </Text>
              <TextInput
                style={[
                  styles.textInput,
                  !currentTranslation.title.trim() && styles.textInputWarning
                ]}
                placeholder={t('notify.title_placeholder', { lang: LANGUAGE_NAMES[activeLanguage] })}
                value={currentTranslation.title}
                onChangeText={(val) => updateTranslation('title', val)}
                maxLength={100}
              />
              <Text style={styles.charCount}>
                {t('notify.char_count_title', { n: currentTranslation.title.length })}
              </Text>
            </View>
            
            {/* Language-specific Message Input */}
            <View style={styles.inputContainer}>
              <Text style={styles.inputLabel}>
                {t('notify.message_label', { lang: LANGUAGE_NAMES[activeLanguage] })}
              </Text>
              <TextInput
                style={[
                  styles.textInput, 
                  styles.messageInput,
                  !currentTranslation.message.trim() && styles.textInputWarning
                ]}
                placeholder={t('notify.message_placeholder', { lang: LANGUAGE_NAMES[activeLanguage] })}
                value={currentTranslation.message}
                onChangeText={(val) => updateTranslation('message', val)}
                multiline
                numberOfLines={4}
                textAlignVertical="top"
                maxLength={500}
              />
              <Text style={styles.charCount}>
                {t('notify.char_count_msg', { n: currentTranslation.message.length })}
              </Text>
            </View>
            
            {/* Recipient Type Selection */}
            <View style={styles.inputContainer}>
              <Text style={styles.inputLabel}>{t('notify.recipient_label')}</Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.typeSelector}>
                {[
                  { type: 'all', label: t('notify.recipient_all'), icon: 'people', color: '#3498db' },
                  { type: 'admins', label: t('notify.recipient_admins'), icon: 'shield', color: '#9b59b6' },
                  { type: 'sellers', label: t('notify.recipient_sellers'), icon: 'cart', color: '#f39c12' },
                  { type: 'clients', label: t('notify.recipient_clients'), icon: 'person', color: '#2ecc71' },
                  { type: 'specific', label: t('notify.recipient_specific'), icon: 'person-circle', color: '#e74c3c' },
                ].map((item) => (
                  <TouchableOpacity
                    key={item.type}
                    style={[
                      styles.typeButton,
                      notificationType === item.type && [styles.typeButtonActive, { backgroundColor: item.color }]
                    ]}
                    onPress={() => {
                      setNotificationType(item.type as any);
                      if (item.type !== 'specific') {
                        setSelectedUsers([]);
                      }
                    }}
                  >
                    <Ionicons 
                      name={item.icon as any} 
                      size={20} 
                      color={notificationType === item.type ? 'white' : item.color} 
                    />
                    <Text style={[
                      styles.typeButtonText,
                      notificationType === item.type && styles.typeButtonTextActive
                    ]}>
                      {item.label}
                    </Text>
                  </TouchableOpacity>
                ))}
              </ScrollView>
              
              <Text style={styles.recipientCount}>
                {t('notify.recipient_count', { n: getRecipientCount() })}
              </Text>
            </View>
            
            {/* Specific Recipients Selection */}
            {notificationType === 'specific' && (
              <View style={styles.inputContainer}>
                <TouchableOpacity 
                  style={styles.selectRecipientsButton}
                  onPress={() => setShowRecipientModal(true)}
                >
                  <Ionicons name="person-add" size={20} color="#3498db" />
                  <Text style={styles.selectRecipientsText}>
                    {selectedUsers.length > 0 
                      ? t('notify.selected_users', { n: selectedUsers.length })
                      : t('notify.select_users')
                    }
                  </Text>
                  <Ionicons name="chevron-forward" size={20} color="#95a5a6" />
                </TouchableOpacity>
              </View>
            )}
            
            {/* Send Button */}
            <TouchableOpacity 
              style={[
                styles.sendButton, 
                sending && styles.sendButtonDisabled,
                !allLanguagesComplete && styles.sendButtonWarning
              ]}
              onPress={handleSendNotification}
              disabled={sending}
            >
              {sending ? (
                <ActivityIndicator size="small" color="white" />
              ) : (
                <>
                  <Ionicons name="send" size={20} color="white" />
                  <Text style={styles.sendButtonText}>
                    {allLanguagesComplete 
                      ? t('notify.send_button', { n: ALL_LANGUAGES.length }) 
                      : t('notify.complete_languages', { n: ALL_LANGUAGES.filter(l => getLanguageStatus(l) !== 'complete').length })
                    }
                  </Text>
                </>
              )}
            </TouchableOpacity>
          </View>
          
          {/* Recent Notifications */}
          <View style={styles.notificationsSection}>
            <View style={styles.sectionHeader}>
              <Text style={styles.sectionTitle}>{t('notify.section_sent')}</Text>
              <TouchableOpacity onPress={fetchNotifications}>
                <Ionicons name="refresh" size={20} color="#3498db" />
              </TouchableOpacity>
            </View>
            
            {notifications.length > 0 ? (
              <FlatList
                data={notifications}
                renderItem={renderNotificationItem}
                keyExtractor={(item) => item.id}
                scrollEnabled={false}
                contentContainerStyle={styles.notificationsList}
              />
            ) : (
              <View style={styles.emptyNotifications}>
                <Ionicons name="notifications-off-outline" size={60} color="#bdc3c7" />
                <Text style={styles.emptyText}>{t('notify.empty_notifications')}</Text>
                <Text style={styles.emptySubtext}>{t('notify.empty_subtext')}</Text>
              </View>
            )}
          </View>
        </ScrollView>
      </KeyboardAvoidingView>

      {/* Recipient Selection Modal */}
      <Modal
        animationType="slide"
        transparent={true}
        visible={showRecipientModal}
        onRequestClose={() => setShowRecipientModal(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>{t('notify.modal_title')}</Text>
              <TouchableOpacity onPress={() => setShowRecipientModal(false)}>
                <Ionicons name="close" size={24} color="#333" />
              </TouchableOpacity>
            </View>
            
            {/* Filter Controls */}
            <View style={styles.filterControls}>
              <View style={styles.filterRow}>
                <Text style={styles.filterLabel}>{t('notify.user_types')}</Text>
                <View style={styles.toggleContainer}>
                  <View style={styles.toggleItem}>
                    <Switch
                      value={showAdmins}
                      onValueChange={setShowAdmins}
                      trackColor={{ false: '#767577', true: '#9b59b6' }}
                      thumbColor={showAdmins ? '#9b59b6' : '#f4f3f4'}
                    />
                    <Text style={styles.toggleLabel}>{t('notify.type_admins')}</Text>
                  </View>
                  
                  <View style={styles.toggleItem}>
                    <Switch
                      value={showSellers}
                      onValueChange={setShowSellers}
                      trackColor={{ false: '#767577', true: '#3498db' }}
                      thumbColor={showSellers ? '#3498db' : '#f4f3f4'}
                    />
                    <Text style={styles.toggleLabel}>{t('notify.type_sellers')}</Text>
                  </View>
                  
                  <View style={styles.toggleItem}>
                    <Switch
                      value={showClients}
                      onValueChange={setShowClients}
                      trackColor={{ false: '#767577', true: '#2ecc71' }}
                      thumbColor={showClients ? '#2ecc71' : '#f4f3f4'}
                    />
                    <Text style={styles.toggleLabel}>{t('notify.type_clients')}</Text>
                  </View>
                </View>
              </View>
              
              {/* Search Bar */}
              <View style={styles.modalSearchContainer}>
                <Ionicons name="search" size={18} color="#666" />
                <TextInput
                  style={styles.modalSearchInput}
                  placeholder={t('notify.search_placeholder')}
                  value={searchQuery}
                  onChangeText={setSearchQuery}
                  placeholderTextColor="#999"
                />
              </View>
              
              {/* Select All Button */}
              <TouchableOpacity 
                style={styles.selectAllButton}
                onPress={selectAllUsers}
              >
                <Ionicons 
                  name={selectedUsers.length === getFilteredUsers().length ? 'checkbox' : 'square-outline'} 
                  size={20} 
                  color="#3498db" 
                />
                <Text style={styles.selectAllText}>
                  {selectedUsers.length === getFilteredUsers().length 
                    ? t('notify.deselect_all') 
                    : t('notify.select_all')
                  }
                  <Text style={styles.selectCount}>
                    ({selectedUsers.length}/{getFilteredUsers().length})
                  </Text>
                </Text>
              </TouchableOpacity>
            </View>
            
            {/* Users List */}
            <FlatList
              data={getFilteredUsers()}
              renderItem={renderUserItem}
              keyExtractor={(item) => item.id}
              contentContainerStyle={styles.modalList}
              ListEmptyComponent={
                <View style={styles.modalEmptyState}>
                  <Ionicons name="people-outline" size={50} color="#bdc3c7" />
                  <Text style={styles.modalEmptyText}>{t('notify.empty_users')}</Text>
                </View>
              }
            />
            
            {/* Done Button */}
            <TouchableOpacity 
              style={styles.doneButton}
              onPress={() => setShowRecipientModal(false)}
            >
              <Text style={styles.doneButtonText}>{t('notify.done', { n: selectedUsers.length })}</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

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
  },
  loadingText: {
    marginTop: 10,
    fontSize: 16,
    color: '#666',
  },
  keyboardAvoid: {
    flex: 1,
  },
  systemWarning: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff3cd',
    padding: 12,
    marginHorizontal: 20,
    marginTop: 10,
    borderRadius: 8,
    borderLeftWidth: 4,
    borderLeftColor: '#f39c12',
  },
  systemWarningText: {
    marginLeft: 10,
    color: '#856404',
    fontSize: 14,
    flex: 1,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 20,
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
    alignItems: 'center',
    gap: 10,
  },
  testButton: {
    backgroundColor: '#2ecc71',
    padding: 8,
    borderRadius: 8,
  },
  logoutButton: {
    padding: 8,
  },
  content: {
    flex: 1,
  },
  statsSummary: {
    flexDirection: 'row',
    backgroundColor: '#fff',
    paddingVertical: 15,
    paddingHorizontal: 10,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  statItem: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: 10,
  },
  statValue: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginTop: 5,
  },
  statLabel: {
    fontSize: 11,
    color: '#7f8c8d',
    marginTop: 2,
  },
  formSection: {
    backgroundColor: '#fff',
    padding: 20,
    marginTop: 1,
    marginBottom: 16,
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
    marginBottom: 15,
  },
  // Language Status Bar
  languageStatusBar: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: '#e8f4fd',
    padding: 12,
    borderRadius: 10,
    marginBottom: 16,
  },
  languageStatusInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  languageStatusText: {
    fontSize: 13,
    color: '#2c3e50',
    fontWeight: '600',
  },
  languageProgressDots: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  languageDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: '#e0e0e0',
  },
  languageDotComplete: {
    backgroundColor: '#27ae60',
  },
  languageDotPartial: {
    backgroundColor: '#f39c12',
  },
  languageDotActive: {
    width: 14,
    height: 14,
    borderRadius: 7,
    borderWidth: 2,
    borderColor: '#3498db',
  },
  // Language Tabs
  languageTabsContainer: {
    marginBottom: 20,
  },
  languageTab: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 20,
    borderWidth: 1.5,
    borderColor: '#ddd',
    marginRight: 8,
    backgroundColor: '#f8f9fa',
    gap: 6,
  },
  languageTabActive: {
    backgroundColor: '#3498db',
    borderColor: '#3498db',
  },
  languageTabComplete: {
    borderColor: '#27ae60',
    backgroundColor: '#e8f6ef',
  },
  languageTabFlag: {
    fontSize: 16,
  },
  languageTabLabel: {
    fontSize: 12,
    fontWeight: '600',
    color: '#2c3e50',
  },
  languageTabLabelActive: {
    color: 'white',
  },
  languageTabDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: '#e74c3c',
  },
  // Warning input style
  textInputWarning: {
    borderColor: '#f39c12',
    borderWidth: 1.5,
  },
  refreshText: {
    fontSize: 14,
    color: '#3498db',
    fontWeight: '500',
  },
  inputContainer: {
    marginBottom: 20,
  },
  inputLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#2c3e50',
    marginBottom: 8,
  },
  textInput: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 10,
    padding: 15,
    fontSize: 16,
    backgroundColor: '#f8f9fa',
    color: '#333',
  },
  messageInput: {
    minHeight: 120,
    textAlignVertical: 'top',
  },
  charCount: {
    textAlign: 'right',
    fontSize: 12,
    color: '#95a5a6',
    marginTop: 4,
  },
  typeSelector: {
    marginBottom: 10,
  },
  typeButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 25,
    borderWidth: 1.5,
    borderColor: '#3498db',
    marginRight: 10,
    backgroundColor: 'white',
    gap: 8,
  },
  typeButtonActive: {
    borderWidth: 0,
  },
  typeButtonText: {
    fontSize: 14,
    fontWeight: '500',
    color: '#3498db',
  },
  typeButtonTextActive: {
    color: 'white',
    fontWeight: '600',
  },
  recipientCount: {
    fontSize: 13,
    color: '#7f8c8d',
    fontWeight: '500',
  },
  recipientCountValue: {
    color: '#3498db',
    fontWeight: 'bold',
  },
  selectRecipientsButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    padding: 15,
    backgroundColor: '#f8f9fa',
    borderRadius: 10,
    borderWidth: 1.5,
    borderColor: '#3498db',
    borderStyle: 'dashed',
  },
  selectRecipientsText: {
    flex: 1,
    marginLeft: 10,
    fontSize: 15,
    color: '#2c3e50',
    fontWeight: '500',
  },
  sendButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#3498db',
    padding: 18,
    borderRadius: 12,
    gap: 10,
    marginTop: 10,
  },
  sendButtonDisabled: {
    backgroundColor: '#95a5a6',
  },
  sendButtonWarning: {
    backgroundColor: '#f39c12',
  },
  sendButtonText: {
    color: 'white',
    fontSize: 17,
    fontWeight: '600',
  },
  notificationsSection: {
    backgroundColor: '#fff',
    padding: 20,
    marginBottom: 30,
  },
  notificationsList: {
    paddingBottom: 10,
  },
  notificationCard: {
    backgroundColor: '#f8f9fa',
    borderRadius: 12,
    padding: 18,
    marginBottom: 15,
    borderLeftWidth: 5,
    borderLeftColor: '#3498db',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 1,
  },
  notificationHeader: {
    marginBottom: 12,
  },
  notificationTitleRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  notificationTitle: {
    fontSize: 17,
    fontWeight: 'bold',
    color: '#2c3e50',
    flex: 1,
    marginRight: 10,
  },
  notificationTypeBadge: {
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 15,
  },
  notificationTypeText: {
    fontSize: 11,
    fontWeight: 'bold',
  },
  notificationTime: {
    fontSize: 12,
    color: '#95a5a6',
  },
  notificationMessage: {
    fontSize: 15,
    color: '#555',
    lineHeight: 22,
    marginBottom: 12,
  },
  notificationFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: '#e8e8e8',
  },
  recipientsContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    gap: 6,
  },
  recipientsText: {
    fontSize: 12,
    color: '#7f8c8d',
    flex: 1,
  },
  notificationMeta: {
    alignItems: 'flex-end',
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 12,
    gap: 5,
    marginBottom: 5,
  },
  statusSent: {
    backgroundColor: '#e8f6ef',
  },
  statusFailed: {
    backgroundColor: '#fdedec',
  },
  statusText: {
    fontSize: 11,
    fontWeight: 'bold',
  },
  statusSentText: {
    color: '#27ae60',
  },
  statusFailedText: {
    color: '#e74c3c',
  },
  senderText: {
    fontSize: 11,
    color: '#95a5a6',
  },
  emptyNotifications: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 50,
  },
  emptyText: {
    marginTop: 15,
    color: '#2c3e50',
    fontSize: 16,
    fontWeight: '500',
  },
  emptySubtext: {
    marginTop: 5,
    color: '#95a5a6',
    fontSize: 14,
  },
  // Modal Styles
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
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  filterControls: {
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  filterRow: {
    marginBottom: 15,
  },
  filterLabel: {
    fontSize: 15,
    fontWeight: '600',
    color: '#2c3e50',
    marginBottom: 12,
  },
  toggleContainer: {
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  toggleItem: {
    alignItems: 'center',
    gap: 6,
  },
  toggleLabel: {
    fontSize: 13,
    color: '#666',
  },
  modalSearchContainer: {
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
  modalSearchInput: {
    flex: 1,
    marginLeft: 10,
    fontSize: 15,
    color: '#333',
  },
  selectAllButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#e3f2fd',
    paddingVertical: 12,
    paddingHorizontal: 15,
    borderRadius: 10,
    alignSelf: 'flex-start',
    gap: 10,
  },
  selectAllText: {
    color: '#1976d2',
    fontSize: 14,
    fontWeight: '600',
  },
  selectCount: {
    color: '#666',
    fontSize: 12,
    marginLeft: 5,
  },
  modalList: {
    padding: 20,
  },
  userItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 15,
    backgroundColor: '#fff',
    borderRadius: 12,
    marginBottom: 12,
    borderWidth: 1.5,
    borderColor: '#e0e0e0',
  },
  userItemSelected: {
    borderColor: '#3498db',
    backgroundColor: '#e3f2fd',
  },
  userCheckbox: {
    marginRight: 12,
  },
  userInfo: {
    flex: 1,
  },
  userName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#2c3e50',
    marginBottom: 3,
  },
  userEmail: {
    fontSize: 13,
    color: '#7f8c8d',
    marginBottom: 3,
  },
  userBusiness: {
    fontSize: 12,
    color: '#3498db',
    fontStyle: 'italic',
  },
  userRoleBadge: {
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 12,
  },
  userRoleText: {
    fontSize: 11,
    fontWeight: 'bold',
  },
  modalEmptyState: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 40,
  },
  modalEmptyText: {
    marginTop: 15,
    color: '#95a5a6',
    fontSize: 16,
  },
  doneButton: {
    backgroundColor: '#3498db',
    margin: 20,
    padding: 18,
    borderRadius: 12,
    alignItems: 'center',
  },
  doneButtonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: '600',
  },
});