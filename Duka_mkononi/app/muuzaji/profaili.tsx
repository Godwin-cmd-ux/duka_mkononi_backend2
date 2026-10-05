import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as ImagePicker from 'expo-image-picker';
import { useRouter } from 'expo-router';
import React, { useEffect, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    Modal,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import ZoomableImage from '../../components/zoomable-image';
import { useLang } from '../../context/LanguageContext';
import { useSession } from '../../context/SessionContext';
import { uploadToCloudinary } from '../../utils/cloudinary';

// ✅ UPDATED: Changed to ngrok URL
import { API_BASE_URL } from '../../constants/api';
import { getCache, setCache } from '../../db/cache';
import { registerLive, syncNow } from '../../lib/syncer';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';

export default function ProfailiScreen() {
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
  const [userData, setUserData] = useState<any>(null);
  const [businessData, setBusinessData] = useState<any>(null);
  const [stats, setStats] = useState({
    totalProducts: 0,
    totalSales: 0,
    totalCustomers: 0,
    totalRevenue: 0,
  });

  // Hali ya modali ya kuhariri wasifu
  const [editModalVisible, setEditModalVisible] = useState(false);
  // A seller may only edit their OWN details. Business fields (name, location,
  // type, description, logo, coordinates) are read-only and are managed by the
  // msimamizi.
  const [editFormData, setEditFormData] = useState({
    full_name: '',
    phone: ''
  });
  const [updatingProfile, setUpdatingProfile] = useState(false);
  const [photoUploading, setPhotoUploading] = useState(false);

  // Badilisha nenosiri modal
  const [passwordModalVisible, setPasswordModalVisible] = useState(false);
  const [passwordForm, setPasswordForm] = useState({
    current: '',
    newPass: '',
    confirm: ''
  });
  const [changingPassword, setChangingPassword] = useState(false);

  // Pakua data ya mtumiaji na takwimu wakati komponenti inapopakuliwa
  useEffect(() => {
    loadUserData();
  }, [lang]);

  useEffect(() => {
    const stop = registerLive<any>(
      'user:profile',
      async () => {
        const userToken = await AsyncStorage.getItem('userToken');
        const res = await fetchWithTimeout(`${API_BASE_URL}/api/user/profile`, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${userToken}`,
            'Content-Type': 'application/json',
            'ngrok-skip-browser-warning': 'true'
          },
        });
        if (!res.ok) throw new Error('sync failed');
        return await res.json();
      },
      (data) => { setUserData(data); setLoading(false); }
    );
    return stop;
  }, [lang]);

  useEffect(() => {
    if (userData) {
      loadBusinessData();
    }
  }, [userData]);

  useEffect(() => {
    if (businessData) {
      loadUserStats();
    }
  }, [businessData]);

  const loadUserData = async () => {
    try {
      const userToken = await AsyncStorage.getItem('userToken');
      
      if (!userToken) {
        Alert.alert(t('app.error'), t('seller_dashboard.error_auth'));
        router.back();
        return;
      }

      const cached = await getCache<any>('user:profile');
      if (cached) { setUserData(cached); setLoading(false); }

      // Pakua data mpya ya mtumiaji kutoka kwa API
      const response = await fetchWithTimeout(`${API_BASE_URL}/api/user/profile`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${userToken}`,
          'Content-Type': 'application/json',
          'ngrok-skip-browser-warning': 'true'
        },
      });

      const responseText = await response.text();
      console.log('📨 Profile API Response:', {
        status: response.status,
        ok: response.ok,
        text: responseText
      });

      if (!response.ok) {
        throw new Error(`Server imerudisha ${response.status}: ${responseText}`);
      }

      const user = JSON.parse(responseText);
      setUserData(user);
      setCache('user:profile', user).catch(() => {});
      
      // Sasisha AsyncStorage kwa data mpya
      await AsyncStorage.setItem('userData', JSON.stringify(user));
      
      // Weka data ya awali ya fomu
      setEditFormData({
        full_name: user.full_name || '',
        phone: user.phone || ''
      });
      
    } catch (error: any) {
      console.error('❌ Kosa wakati wa upakuaji wa data ya mtumiaji:', error);
      
      const cachedUser = await getCache<any>('user:profile');
      if (cachedUser) { setUserData(cachedUser); setLoading(false); return; }

      // Rudi kwenye data iliyohifadhiwa ikiwa API imeshindwa
      try {
        const storedUserData = await AsyncStorage.getItem('userData');
        if (storedUserData) {
          const user = JSON.parse(storedUserData);
          setUserData(user);
          setEditFormData({
            full_name: user.full_name || '',
            phone: user.phone || ''
          });
          console.log('📄 Inatumia data iliyohifadhiwa ya mtumiaji kama nyongeza');
        } else {
          Alert.alert(t('app.error'), t('seller_dashboard.error_network'));
        }
      } catch (fallbackError) {
        Alert.alert(t('app.error'), t('seller_dashboard.error_network'));
      }
    } finally {
      setLoading(false);
    }
  };

  const loadBusinessData = async () => {
    let cachedBiz: any = null;
    let businessOut: any = null;
    try {
      if (!userData || !userData.business_name) {
        console.log('⚠️ User has no business name');
        setBusinessData({ 
          id: userData.id, 
          business_name: userData.business_name || 'Personal',
          is_admin: userData.role === 'admin' 
        });
        return;
      }

      console.log('🏢 Loading business data for:', userData.business_name);
      
      cachedBiz = await getCache<any>('business:by-name:' + userData.business_name);
      if (cachedBiz) {
        businessOut = cachedBiz;
        setBusinessData(cachedBiz);
      }

      const token = await AsyncStorage.getItem('userToken');
      const response = await fetchWithTimeout(
        `${API_BASE_URL}/api/business/by-name/${encodeURIComponent(userData.business_name)}`, 
        {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
            'ngrok-skip-browser-warning': 'true'
          },
        }
      );

      if (response.ok) {
        const businessInfo = await response.json();
        console.log('✅ Business data loaded:', businessInfo);
        
        if (businessInfo.exists && businessInfo.hasAdmin) {
          businessOut = {
            id: businessInfo.admin.id,
            business_name: businessInfo.admin.business_name,
            is_admin: true,
            admin_email: businessInfo.admin.email
          };
          setBusinessData(businessOut);
        } else {
          businessOut = { 
            id: userData.id, 
            business_name: userData.business_name,
            is_admin: userData.role === 'admin' 
          };
          setBusinessData(businessOut);
        }
      } else {
        console.log('⚠️ Business not found, using personal data');
        if (!cachedBiz) {
          businessOut = { 
            id: userData.id, 
            business_name: userData.business_name || 'Personal',
            is_admin: userData.role === 'admin' 
          };
          setBusinessData(businessOut);
        }
      }

      if (businessOut) {
        setCache('business:by-name:' + userData.business_name, businessOut).catch(() => {});
      }

    } catch (error) {
      console.error('❌ Error loading business data:', error);
      if (!cachedBiz && !businessOut) {
        setBusinessData({ 
          id: userData.id, 
          business_name: userData.business_name || 'Personal',
          is_admin: userData.role === 'admin' 
        });
      }
    }
  };

  const loadUserStats = async () => {
    try {
      console.log('📊 Inapakua takwimu za biashara...');
      const token = await AsyncStorage.getItem('userToken');
      
      if (!token) {
        console.error('❌ No token found');
        return;
      }

      const cachedStats = await getCache<any>('user:stats');
      if (cachedStats) { setStats(cachedStats); }

      console.log('📊 Loading TODAY stats (same contract as the Blade page)');

      let totalProducts = 0;
      let totalSales = 0;
      let totalRevenue = 0;
      let totalCustomers = 0;

      // Bidhaa za biashara: /api/products/my already scopes itself server-side
      // (admin -> the admin's own rows, seller -> the business's products), so
      // the old per-seller fan-out is gone. Both Node endpoints it used,
      // /api/business/{name}/sellers and /api/products/seller/{id}, are 404 on
      // Laravel - the Blade page never called them either.
      const productsResponse = await fetchWithTimeout(`${API_BASE_URL}/api/products/my`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'ngrok-skip-browser-warning': 'true'
        },
      });
      if (productsResponse.ok) {
        const productsData = await productsResponse.json();
        totalProducts = Array.isArray(productsData) ? productsData.length : 0;
      }

      // Mauzo ya LEO pekee: filtered by the database through date_from/date_to,
      // exactly like resources/views/muuzaji/profaili.blade.php.
      const todayStr = new Date().toISOString().split('T')[0];
      const salesResponse = await fetchWithTimeout(
        `${API_BASE_URL}/api/sales/my?date_from=${todayStr}&date_to=${todayStr}`,
        {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${token}`,
            'ngrok-skip-browser-warning': 'true'
          },
        }
      );
      if (salesResponse.ok) {
        const salesData = await salesResponse.json();
        const list = Array.isArray(salesData) ? salesData : [];
        totalSales = list.length;
        totalRevenue = list.reduce((sum: number, sale: any) =>
          sum + (parseFloat(sale.total_amount) || 0), 0);
        // Wateja wa Leo = distinct customer_id recorded in today's sales, plus
        // one "unknown customer" for every sale that has no customer data.
        const customerIds = new Set<string>();
        let unknownCustomers = 0;
        list.forEach((sale: any) => {
          if (sale.customer_id) customerIds.add(sale.customer_id);
          else unknownCustomers += 1;
        });
        totalCustomers = customerIds.size + unknownCustomers;
        console.log(`💰 Loaded ${totalSales} sales today, Revenue: ${totalRevenue}, Customers: ${totalCustomers}`);
      }

      // Weka takwimu
      setStats({
        totalProducts,
        totalSales,
        totalCustomers,
        totalRevenue,
      });
      setCache('user:stats', {
        totalProducts,
        totalSales,
        totalCustomers,
        totalRevenue,
      }).catch(() => {});

      console.log('📊 Final Stats:', {
        totalProducts,
        totalSales,
        totalCustomers,
        totalRevenue,
      });

    } catch (error) {
      console.error('❌ Kosa wakati wa upakuaji wa takwimu:', error);
      const cachedStats = await getCache<any>('user:stats');
      if (cachedStats) { setStats(cachedStats); }
    }
  };

  // Kazi za Kuhariri Wasifu
  const handleEditProfile = () => {
    setEditModalVisible(true);
  };

  const handleUpdateProfile = async () => {
    if (!editFormData.full_name.trim()) {
      Alert.alert(t('app.error'), t('profile.error_update'));
      return;
    }

    setUpdatingProfile(true);
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        Alert.alert(t('app.error'), t('seller_dashboard.error_auth'));
        return;
      }

      if (!(await requireNetwork())) return;

      console.log('📤 Updating profile with data:', editFormData);
      
      const response = await fetchWithTimeout(`${API_BASE_URL}/api/user/profile`, {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'ngrok-skip-browser-warning': 'true'
        },
        // Only the seller's own fields. Sending business fields here would let
        // a seller overwrite the business profile, which is not allowed.
        body: JSON.stringify({
          full_name: editFormData.full_name,
          phone: editFormData.phone
        }),
      });

      const responseText = await response.text();
      console.log('📨 Update profile response:', {
        status: response.status,
        ok: response.ok,
        text: responseText
      });

      if (response.ok) {
        const updatedData = JSON.parse(responseText);
        
        // Sasisha data ya mtumiaji kwenye hali
        setUserData((prev: any) => ({
          ...prev,
          full_name: updatedData.user?.full_name || editFormData.full_name,
          phone: updatedData.user?.phone || editFormData.phone
        }));

        // Sasisha AsyncStorage
        const currentUserData = await AsyncStorage.getItem('userData');
        if (currentUserData) {
          const user = JSON.parse(currentUserData);
          const updatedUserData = {
            ...user,
            full_name: updatedData.user?.full_name || editFormData.full_name,
            phone: updatedData.user?.phone || editFormData.phone
          };
          await AsyncStorage.setItem('userData', JSON.stringify(updatedUserData));
        }

        setEditModalVisible(false);
        Alert.alert(t('app.success'), t('profile.success_update'));
      } else {
        throw new Error(responseText || t('profile.error_update_failed'));
      }
    } catch (error: any) {
      console.error('❌ Kosa wakati wa usasishaji wa wasifu:', error);
      Alert.alert(t('app.error'), t('profile.error_update_failed') + ': ' + error.message);
    } finally {
      setUpdatingProfile(false);
    }
  };

  const handleChangePassword = async () => {
    const { current, newPass, confirm } = passwordForm;

    if (!current || !newPass || !confirm) {
      Alert.alert(t('app.error'), t('profile.password_fields_required'));
      return;
    }

    if (newPass !== confirm) {
      Alert.alert(t('app.error'), t('profile.password_mismatch'));
      return;
    }

    if (newPass.length < 6) {
      Alert.alert(t('app.error'), t('profile.password_too_short'));
      return;
    }

    setChangingPassword(true);
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        Alert.alert(t('app.error'), t('seller_dashboard.error_auth'));
        return;
      }

      if (!(await requireNetwork())) return;

      const response = await fetchWithTimeout(`${API_BASE_URL}/api/user/password`, {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'ngrok-skip-browser-warning': 'true',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          current_password: current,
          new_password: newPass,
          confirm_password: confirm
        }),
      });

      const responseText = await response.text();
      let data: any = {};
      try { data = JSON.parse(responseText); } catch { /* non-JSON error body */ }

      if (response.ok) {
        setPasswordModalVisible(false);
        setPasswordForm({ current: '', newPass: '', confirm: '' });
        Alert.alert(t('profile.password_success_title'), t('profile.password_success'));
      } else {
        Alert.alert(t('app.error'), data.error || t('profile.password_change_failed'));
      }
    } catch (error: any) {
      console.error('❌ Error changing password:', error);
      Alert.alert(t('app.error'), error?.message || t('profile.password_change_failed'));
    } finally {
      setChangingPassword(false);
    }
  };

  const closePasswordModal = () => {
    setPasswordModalVisible(false);
    setPasswordForm({ current: '', newPass: '', confirm: '' });
  };

  const handleLogout = async () => {
    Alert.alert(
      t('profile.logout_title'),
      t('profile.logout_confirm'),
      [
        { text: t('profile.cancel'), style: 'cancel' },
        { 
          text: t('profile.logout_yes'), 
          style: 'destructive',
          onPress: async () => {
            try {
              await signOut();
              router.replace('/(tabs)');
            } catch (error) {
              console.error('❌ Kosa wakati wa kutoka:', error);
            }
          }
        }
      ]
    );
  };

  const handleRefresh = async () => {
    setLoading(true);
    await loadUserData();
  };

  const formatCurrency = (amount: number) => {
    return `TZS ${amount.toLocaleString()}`;
  };

  const getRoleDisplayName = (role: string) => {
    switch (role) {
      case 'customer': return t('user_roles.mteja').toUpperCase();
      case 'seller': return t('user_roles.muuzaji').toUpperCase();
      case 'admin': return t('user_roles.msimamizi').toUpperCase();
      default: return role.toUpperCase();
    }
  };

  const getRoleColor = (role: string) => {
    switch (role) {
      case 'customer': return '#3498db';
      case 'seller': return '#2ecc71';
      case 'admin': return '#e74c3c';
      default: return '#2c3e50';
    }
  };

  const getInitials = (name: string) => {
    return name
      ?.split(' ')
      .map(word => word.charAt(0))
      .join('')
      .toUpperCase()
      .substring(0, 2) || 'UM';
  };

  // 🖼️ Upload a profile photo (picks → Cloudinary → saves the URL to the server)
  // The seller owns their photo even though the column is shared with the
  // business row (`business_logo_url`).
  const handleChangePhoto = async () => {
    try {
      const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!perm.granted) {
        Alert.alert(t('app.error'), t('profile.photo_permission_denied'));
        return;
      }

      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ImagePicker.MediaTypeOptions.Images,
        quality: 0.8,
        allowsEditing: true,
        aspect: [1, 1],
      });

      if (result.canceled || !result.assets?.[0]) return;

      setPhotoUploading(true);
      const cloudinaryResult = await uploadToCloudinary(result.assets[0].uri, 'image');

      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        Alert.alert(t('app.error'), t('seller_dashboard.error_auth'));
        return;
      }

      if (!(await requireNetwork())) return;

      const response = await fetchWithTimeout(`${API_BASE_URL}/api/user/profile`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
          'ngrok-skip-browser-warning': 'true',
        },
        body: JSON.stringify({ business_logo_url: cloudinaryResult.secure_url }),
      });

      if (!response.ok) {
        throw new Error(t('profile.photo_error'));
      }

      const updated = await response.json();
      const newLogo = updated.user?.business_logo_url;

      setUserData((prev: any) => (prev ? { ...prev, business_logo_url: newLogo } : prev));

      const cached = await AsyncStorage.getItem('userData');
      if (cached) {
        const user = JSON.parse(cached);
        await AsyncStorage.setItem('userData', JSON.stringify({ ...user, business_logo_url: newLogo }));
      }

      Alert.alert(t('profile.photo_success_title'), t('profile.photo_success'));
    } catch (error: any) {
      console.error('❌ Photo upload error:', error);
      Alert.alert(t('app.error'), error?.message || t('profile.photo_error'));
    } finally {
      setPhotoUploading(false);
    }
  };

  const formatDate = (dateString: string) => {
    if (!dateString) return t('profile.not_set');
    try {
      const date = new Date(dateString);
      return date.toLocaleDateString(localeMap[lang] || 'sw-TZ', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    } catch {
      return dateString;
    }
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.screenSafe} edges={['top']}>
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#2ecc71" />
        <Text style={styles.loadingText}>{t('profile.loading')}</Text>
      </View>
    </SafeAreaView>
    );
  }

  if (!userData) {
    return (
      <SafeAreaView style={styles.screenSafe} edges={['top']}>
      <View style={styles.errorContainer}>
        <Ionicons name="alert-circle" size={64} color="#e74c3c" />
        <Text style={styles.errorText}>{t('profile.error_load')}</Text>
        <TouchableOpacity style={styles.retryButton} onPress={loadUserData}>
          <Text style={styles.retryButtonText}>{t('profile.retry')}</Text>
        </TouchableOpacity>
      </View>
    </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.screenSafe} edges={['top']}>
    <ScrollView style={styles.container} showsVerticalScrollIndicator={false}>
      {/* Sehemu ya Kichwa na Taarifa za Wasifu */}
      <View style={styles.header}>
        <View style={styles.headerTop}>
          <Text style={styles.title}>{t('profile.title')}</Text>
          <View style={styles.headerActions}>
            <TouchableOpacity onPress={handleRefresh} style={styles.headerButton}>
              <Ionicons name="refresh" size={20} color="#3498db" />
            </TouchableOpacity>
            <TouchableOpacity onPress={handleEditProfile} style={styles.headerButton}>
              <Ionicons name="create-outline" size={20} color="#3498db" />
            </TouchableOpacity>
          </View>
        </View>

        <View style={styles.profileCard}>
          <View style={styles.avatarSection}>
            <View style={styles.avatarWrap}>
              {userData.business_logo_url ? (
                <ZoomableImage
                  uri={userData.business_logo_url}
                  style={[styles.avatar, styles.avatarImage]}
                  name={userData.full_name || userData.business_name}
                />
              ) : (
                <View style={[styles.avatar, { backgroundColor: getRoleColor(userData.role) }]}>
                  <Text style={styles.avatarText}>
                    {getInitials(userData.full_name || userData.business_name || userData.email)}
                  </Text>
                </View>
              )}
              {/* Camera badge — the seller may change their own photo */}
              <TouchableOpacity
                style={[styles.cameraBadge, { backgroundColor: getRoleColor(userData.role) }]}
                onPress={handleChangePhoto}
                disabled={photoUploading}
                activeOpacity={0.8}
              >
                {photoUploading ? (
                  <ActivityIndicator size="small" color="white" />
                ) : (
                  <Ionicons name="camera" size={16} color="white" />
                )}
              </TouchableOpacity>
            </View>
            <View style={styles.roleBadge}>
              <Text style={styles.roleText}>{getRoleDisplayName(userData.role)}</Text>
            </View>
          </View>
          
          <View style={styles.userInfo}>
            <Text style={styles.userName}>
              {userData.full_name || userData.business_name || userData.email}
            </Text>
            <Text style={styles.userEmail}>{userData.email}</Text>
            
            {businessData && businessData.business_name && (
              <View style={styles.businessInfo}>
                <Ionicons name="business" size={16} color="#2ecc71" />
                <Text style={styles.businessText}>
                  {businessData.business_name}
                  {businessData.is_admin && ' (' + t('profile.manager_badge') + ')'}
                </Text>
              </View>
            )}
            
            {userData.business_location && (
              <View style={styles.businessInfo}>
                <Ionicons name="location" size={16} color="#7f8c8d" />
                <Text style={styles.businessText}>{userData.business_location}</Text>
              </View>
            )}

            {/* Kitufe cha Kuhariri Wasifu */}
            <TouchableOpacity style={styles.editProfileButton} onPress={handleEditProfile}>
              <Ionicons name="create-outline" size={16} color="#3498db" />
              <Text style={styles.editProfileButtonText}>{t('profile.edit_title')}</Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>

      {/* Kadi za Takwimu - ZA BIASHARA HUSIKA TU */}
      <View style={styles.statsSection}>
        <Text style={styles.sectionTitle}>
          {t('profile.stats_for')} {businessData?.business_name || t('profile.your_business')}
          <Text style={styles.statsSubtitle}>
            {businessData?.is_admin ? t('profile.business_all') : t('profile.your_personal')}
          </Text>
        </Text>
        
        <View style={styles.statsGrid}>
          <View style={styles.statCard}>
            <View style={[styles.statIcon, { backgroundColor: '#e8f4fd' }]}>
              <Ionicons name="cube" size={24} color="#3498db" />
            </View>
            <Text style={styles.statValue}>{stats.totalProducts}</Text>
            <Text style={styles.statLabel}>
              {businessData?.is_admin ? t('profile.stats_biz_products') : t('profile.stats_your_products')}
            </Text>
          </View>

          <View style={styles.statCard}>
            <View style={[styles.statIcon, { backgroundColor: '#f0f8f0' }]}>
              <Ionicons name="cart" size={24} color="#27ae60" />
            </View>
            <Text style={styles.statValue}>{stats.totalSales}</Text>
            <Text style={styles.statLabel}>
              {businessData?.is_admin ? t('profile.stats_biz_sales') : t('profile.stats_your_sales')}
            </Text>
          </View>

          <View style={styles.statCard}>
            <View style={[styles.statIcon, { backgroundColor: '#fff8e1' }]}>
              <Ionicons name="people" size={24} color="#e67e22" />
            </View>
            <Text style={styles.statValue}>{stats.totalCustomers}</Text>
            <Text style={styles.statLabel}>
              {businessData?.is_admin ? t('profile.stats_biz_customers') : t('profile.stats_your_customers')}
            </Text>
          </View>

          <View style={styles.statCard}>
            <View style={[styles.statIcon, { backgroundColor: '#fce4ec' }]}>
              <Ionicons name="cash" size={24} color="#9b59b6" />
            </View>
            <Text style={styles.statValue}>{formatCurrency(stats.totalRevenue)}</Text>
            <Text style={styles.statLabel}>
              {businessData?.is_admin ? t('profile.stats_biz_income') : t('profile.stats_your_income')}
            </Text>
          </View>
        </View>

        <Text style={styles.statsNote}>
          {businessData?.is_admin 
            ? t('profile.stats_note_admin') 
            : t('profile.stats_note_seller')}
        </Text>
      </View>

      {/* Taarifa za Akaunti */}
      <View style={styles.infoSection}>
        <Text style={styles.sectionTitle}>{t('profile.account_info')}</Text>
        
        <View style={styles.infoCard}>
          <View style={styles.infoRow}>
            <View style={styles.infoLabelContainer}>
              <Ionicons name="person" size={18} color="#7f8c8d" />
              <Text style={styles.infoLabel}>{t('profile.full_name')}:</Text>
            </View>
            <Text style={styles.infoValue}>
              {userData.full_name || t('profile.not_set')}
            </Text>
          </View>

          <View style={styles.infoRow}>
            <View style={styles.infoLabelContainer}>
              <Ionicons name="mail" size={18} color="#7f8c8d" />
              <Text style={styles.infoLabel}>{t('profile.email')}:</Text>
            </View>
            <Text style={styles.infoValue}>{userData.email}</Text>
          </View>

          <View style={styles.infoRow}>
            <View style={styles.infoLabelContainer}>
              <Ionicons name="call" size={18} color="#7f8c8d" />
              <Text style={styles.infoLabel}>{t('profile.phone_namba')}:</Text>
            </View>
            <Text style={styles.infoValue}>
              {userData.phone || t('profile.not_set')}
            </Text>
          </View>

          <View style={styles.infoRow}>
            <View style={styles.infoLabelContainer}>
              <Ionicons name="card" size={18} color="#7f8c8d" />
              <Text style={styles.infoLabel}>{t('profile.account_id')}:</Text>
            </View>
            <Text style={styles.infoValue}>#{userData.id || 'N/A'}</Text>
          </View>

          {businessData && (
            <View style={styles.infoRow}>
              <View style={styles.infoLabelContainer}>
                <Ionicons name="business" size={18} color="#7f8c8d" />
                <Text style={styles.infoLabel}>{t('profile.business')}:</Text>
              </View>
              <Text style={styles.infoValue}>
                {businessData.business_name || t('profile.not_set')}
              </Text>
            </View>
          )}

          {userData.business_type && (
            <View style={styles.infoRow}>
              <View style={styles.infoLabelContainer}>
                <Ionicons name="pricetag" size={18} color="#7f8c8d" />
                <Text style={styles.infoLabel}>{t('profile.business_type_label')}:</Text>
              </View>
              <Text style={styles.infoValue}>
                {t('business_types.' + userData.business_type)}
              </Text>
            </View>
          )}

          {userData.business_description && (
            <View style={styles.infoRow}>
              <View style={styles.infoLabelContainer}>
                <Ionicons name="document-text" size={18} color="#7f8c8d" />
                <Text style={styles.infoLabel}>{t('profile.business_description_label')}:</Text>
              </View>
              <Text style={[styles.infoValue, styles.bizDescValue]}>
                {userData.business_description}
              </Text>
            </View>
          )}

          <View style={styles.infoRow}>
            <View style={styles.infoLabelContainer}>
              <Ionicons name="location" size={18} color="#7f8c8d" />
              <Text style={styles.infoLabel}>{t('profile.business_location')}:</Text>
            </View>
            <Text style={styles.infoValue}>
              {userData.business_location || t('profile.not_set')}
            </Text>
          </View>

          <View style={styles.infoRow}>
            <View style={styles.infoLabelContainer}>
              <Ionicons name="time" size={18} color="#7f8c8d" />
              <Text style={styles.infoLabel}>{t('profile.reg_date')}:</Text>
            </View>
            <Text style={styles.infoValue}>
              {formatDate(userData.created_at)}
            </Text>
          </View>

          <View style={styles.infoRow}>
            <View style={styles.infoLabelContainer}>
              <Ionicons name="shield-checkmark" size={18} color="#7f8c8d" />
              <Text style={styles.infoLabel}>{t('profile.account_status')}:</Text>
            </View>
            <View style={[
              styles.statusBadge, 
              { 
                backgroundColor: userData.status === 'approved' ? '#e8f6f3' : 
                                userData.status === 'pending' ? '#fef9e7' : '#fdedec'
              }
            ]}>
              <Text style={[
                styles.statusText, 
                { 
                  color: userData.status === 'approved' ? '#27ae60' : 
                         userData.status === 'pending' ? '#f39c12' : '#e74c3c'
                }
              ]}>
                {userData.status === 'approved' ? t('profile.status_approved') : 
                 userData.status === 'pending' ? t('profile.status_pending') : t('profile.status_rejected')}
              </Text>
            </View>
          </View>
        </View>
      </View>

      {/* Modali ya Kuhariri Wasifu */}
      <Modal
        animationType="slide"
        transparent={true}
        visible={editModalVisible}
        onRequestClose={() => setEditModalVisible(false)}
      >
        <View style={styles.modalContainer}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>{t('profile.edit_modal_title')}</Text>
            <Text style={styles.modalSubtitle}>{t('profile.edit_modal_subtitle')}</Text>

            <ScrollView style={styles.modalScroll} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
              <Text style={styles.inputLabel}>{t('profile.full_name')} *</Text>
              <TextInput
                style={styles.input}
                placeholder={t('profile.name_placeholder')}
                value={editFormData.full_name}
                onChangeText={(text) => setEditFormData(prev => ({ ...prev, full_name: text }))}
              />
              
              <Text style={styles.inputLabel}>{t('profile.phone_namba')}</Text>
              <TextInput
                style={styles.input}
                placeholder={t('profile.phone_placeholder')}
                value={editFormData.phone}
                onChangeText={(text) => setEditFormData(prev => ({ ...prev, phone: text }))}
                keyboardType="phone-pad"
              />

              {/* Badilisha nenosiri */}
              <TouchableOpacity
                style={styles.changePasswordRow}
                onPress={() => setPasswordModalVisible(true)}
              >
                <Ionicons name="lock-closed-outline" size={18} color="#3498db" />
                <Text style={styles.changePasswordText}>{t('profile.change_password')}</Text>
                <Ionicons name="chevron-forward" size={18} color="#bdc3c7" />
              </TouchableOpacity>

            </ScrollView>

            <View style={styles.modalButtons}>
              <TouchableOpacity 
                style={[styles.modalButton, styles.cancelButton]} 
                onPress={() => setEditModalVisible(false)}
                disabled={updatingProfile}
              >
                <Text style={styles.cancelButtonText}>{t('profile.cancel')}</Text>
              </TouchableOpacity>
              <TouchableOpacity 
                style={[styles.modalButton, styles.saveButton]} 
                onPress={handleUpdateProfile}
                disabled={updatingProfile || !editFormData.full_name.trim()}
              >
                {updatingProfile ? (
                  <ActivityIndicator size="small" color="white" />
                ) : (
                  <Text style={styles.saveButtonText}>{t('profile.save')}</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Modali ya Kubadilisha Nenosiri */}
      <Modal
        animationType="slide"
        transparent={true}
        visible={passwordModalVisible}
        onRequestClose={closePasswordModal}
      >
        <View style={styles.modalContainer}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>{t('profile.change_password_title')}</Text>
            <Text style={styles.modalSubtitle}>{t('profile.change_password_subtitle')}</Text>

            <ScrollView style={styles.modalScroll} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
              <Text style={styles.inputLabel}>{t('profile.current_password')} *</Text>
              <TextInput
                style={styles.input}
                placeholder={t('profile.current_password_placeholder')}
                secureTextEntry
                value={passwordForm.current}
                onChangeText={(text) => setPasswordForm(prev => ({ ...prev, current: text }))}
              />

              <Text style={styles.inputLabel}>{t('profile.new_password')} *</Text>
              <TextInput
                style={styles.input}
                placeholder={t('profile.new_password_placeholder')}
                secureTextEntry
                value={passwordForm.newPass}
                onChangeText={(text) => setPasswordForm(prev => ({ ...prev, newPass: text }))}
              />

              <Text style={styles.inputLabel}>{t('profile.confirm_password')} *</Text>
              <TextInput
                style={styles.input}
                placeholder={t('profile.confirm_password_placeholder')}
                secureTextEntry
                value={passwordForm.confirm}
                onChangeText={(text) => setPasswordForm(prev => ({ ...prev, confirm: text }))}
              />
            </ScrollView>

            <View style={styles.modalButtons}>
              <TouchableOpacity
                style={[styles.modalButton, styles.cancelButton]}
                onPress={closePasswordModal}
                disabled={changingPassword}
              >
                <Text style={styles.cancelButtonText}>{t('profile.cancel')}</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalButton, styles.saveButton]}
                onPress={handleChangePassword}
                disabled={changingPassword || !passwordForm.current || !passwordForm.newPass || !passwordForm.confirm}
              >
                {changingPassword ? (
                  <ActivityIndicator size="small" color="white" />
                ) : (
                  <Text style={styles.saveButtonText}>{t('profile.change_password_save')}</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Sehemu ya Kutoka */}
      <View style={styles.logoutSection}>
        <TouchableOpacity style={styles.logoutButton} onPress={handleLogout}>
          <Ionicons name="log-out" size={20} color="white" />
          <Text style={styles.logoutButtonText}>{t('profile.logout')}</Text>
        </TouchableOpacity>
      </View>

      {/* Kijachini */}
      <View style={styles.footer}>
        <Text style={styles.footerText}>
          Duka Mkononi • {new Date().getFullYear()}
        </Text>
        <Text style={styles.footerSubText}>
          {t('profile.footer_for')} {userData.role === 'seller' ? t('profile.footer_role_seller') : userData.role === 'customer' ? t('profile.footer_role_customer') : t('profile.footer_role_admin')}
          {businessData?.business_name && ` • ${businessData.business_name}`}
        </Text>
      </View>
    </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screenSafe: {
    flex: 1,
    backgroundColor: '#f8f9fa',
  },
  container: {
    flex: 1,
    backgroundColor: '#f8f9fa',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f8f9fa',
    padding: 20,
  },
  loadingText: {
    marginTop: 10,
    fontSize: 16,
    color: '#2c3e50',
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f8f9fa',
    padding: 20,
  },
  errorText: {
    fontSize: 16,
    color: '#2c3e50',
    textAlign: 'center',
    marginTop: 16,
    marginBottom: 12,
  },
  retryButton: {
    backgroundColor: '#3498db',
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 8,
    marginTop: 16,
  },
  retryButtonText: {
    color: 'white',
    fontSize: 14,
    fontWeight: 'bold',
  },
  header: {
    backgroundColor: 'white',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#ecf0f1',
  },
  headerTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 20,
  },
  headerActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  headerButton: {
    padding: 8,
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  profileCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
  },
  avatarSection: {
    alignItems: 'center',
  },
  avatarWrap: {
    position: 'relative',
    marginBottom: 8,
  },
  avatar: {
    width: 80,
    height: 80,
    borderRadius: 40,
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  avatarImage: {
    backgroundColor: '#e8eef4',
  },
  cameraBadge: {
    position: 'absolute',
    right: -4,
    bottom: 0,
    width: 28,
    height: 28,
    borderRadius: 14,
    borderWidth: 2,
    borderColor: 'white',
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.2,
    shadowRadius: 3,
    elevation: 3,
  },
  avatarText: {
    color: 'white',
    fontSize: 28,
    fontWeight: 'bold',
  },
  roleBadge: {
    backgroundColor: '#f8f9fa',
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#ecf0f1',
  },
  roleText: {
    fontSize: 12,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  userInfo: {
    flex: 1,
  },
  userName: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 4,
  },
  userEmail: {
    fontSize: 14,
    color: '#7f8c8d',
    marginBottom: 8,
  },
  businessInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginBottom: 4,
  },
  businessText: {
    fontSize: 14,
    color: '#2c3e50',
    fontWeight: '500',
  },
  editProfileButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 12,
    padding: 10,
    backgroundColor: '#e8f4fd',
    borderRadius: 8,
    alignSelf: 'flex-start',
  },
  editProfileButtonText: {
    fontSize: 12,
    color: '#3498db',
    fontWeight: '500',
  },
  changePasswordRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginTop: 18,
    paddingVertical: 12,
    paddingHorizontal: 4,
    borderTopWidth: 1,
    borderTopColor: '#f0f0f0',
  },
  changePasswordText: {
    flex: 1,
    fontSize: 15,
    fontWeight: '600',
    color: '#3498db',
  },
  statsSection: {
    padding: 20,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 16,
  },
  statsSubtitle: {
    fontSize: 14,
    color: '#7f8c8d',
    fontWeight: 'normal',
  },
  statsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  statCard: {
    backgroundColor: 'white',
    padding: 16,
    borderRadius: 12,
    alignItems: 'center',
    flex: 1,
    minWidth: '45%',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 3,
    elevation: 2,
  },
  statIcon: {
    width: 48,
    height: 48,
    borderRadius: 24,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 8,
  },
  statValue: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 4,
  },
  statLabel: {
    fontSize: 12,
    color: '#7f8c8d',
    textAlign: 'center',
  },
  statsNote: {
    fontSize: 12,
    color: '#95a5a6',
    textAlign: 'center',
    marginTop: 12,
    fontStyle: 'italic',
  },
  infoSection: {
    padding: 20,
  },
  infoCard: {
    backgroundColor: 'white',
    padding: 16,
    borderRadius: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 3,
    elevation: 2,
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f8f9fa',
  },
  infoLabelContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    flex: 1,
  },
  infoLabel: {
    fontSize: 14,
    color: '#7f8c8d',
    fontWeight: '500',
  },
  infoValue: {
    fontSize: 14,
    color: '#2c3e50',
    fontWeight: '500',
    flex: 1,
    textAlign: 'right',
  },
  bizDescValue: {
    color: '#7f8c8d',
    fontWeight: '400',
  },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
  },
  statusText: {
    fontSize: 12,
    fontWeight: 'bold',
  },
  // Mitindo ya Modali
  modalContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: 'rgba(0,0,0,0.5)',
    padding: 20,
  },
  modalContent: {
    backgroundColor: 'white',
    padding: 24,
    borderRadius: 16,
    width: '100%',
    maxWidth: 400,
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 8,
    textAlign: 'center',
  },
  modalSubtitle: {
    fontSize: 14,
    color: '#7f8c8d',
    textAlign: 'center',
    marginBottom: 20,
  },
  inputLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#2c3e50',
    marginBottom: 6,
    marginTop: 12,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ddd',
    padding: 12,
    borderRadius: 8,
    fontSize: 16,
    backgroundColor: '#f8f9fa',
  },
  modalScroll: {
    maxHeight: '70%',
  },
  modalButtons: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 24,
    gap: 12,
  },
  modalButton: {
    flex: 1,
    padding: 14,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cancelButton: {
    backgroundColor: '#95a5a6',
  },
  saveButton: {
    backgroundColor: '#3498db',
  },
  cancelButtonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: '600',
  },
  saveButtonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: '600',
  },
  logoutSection: {
    padding: 20,
    paddingTop: 10,
  },
  logoutButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#e74c3c',
    padding: 16,
    borderRadius: 12,
    gap: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.25,
    shadowRadius: 3.84,
    elevation: 5,
  },
  logoutButtonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: 'bold',
  },
  footer: {
    padding: 20,
    alignItems: 'center',
    backgroundColor: 'white',
    marginTop: 10,
    borderTopWidth: 1,
    borderTopColor: '#ecf0f1',
  },
  footerText: {
    fontSize: 14,
    color: '#7f8c8d',
    marginBottom: 4,
  },
  footerSubText: {
    fontSize: 12,
    color: '#bdc3c7',
  },
});