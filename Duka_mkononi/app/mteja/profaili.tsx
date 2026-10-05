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
    Switch,
    Text,
    TextInput,
    TouchableOpacity,
    View
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import ZoomableImage from '../../components/zoomable-image';
import { useLang } from '../../context/LanguageContext';
import { useSession } from '../../context/SessionContext';
import { uploadToCloudinary } from '../../utils/cloudinary';

// ✅ KUBADILISHWA: Tumia ngrok URL mpya
import { API_BASE_URL } from '../../constants/api';
import { getCache, setCache } from '../../db/cache';
import { registerLive, syncNow } from '../../lib/syncer';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';

interface UserProfile {
  // NB: user ids are UUID STRINGS (same finding as mteja/biashara and
  // mteja/matangazo). Typing this as `number` was wrong.
  id: string;
  email: string;
  role: string;
  full_name: string;
  phone: string;
  business_name: string;
  business_location: string;
  business_logo_url?: string | null;
  status: string;
  created_at: string;
}

export default function ProfailiScreen() {
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

  const formatDate = (dateStr: string): string => {
    try {
      const d = new Date(dateStr);
      return d.toLocaleDateString(localeMap[lang] || 'sw-TZ', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    } catch {
      return dateStr;
    }
  };

  const [profile, setProfile] = useState<UserProfile | null>(null);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState(false);
  const [saving, setSaving] = useState(false);
  const [formData, setFormData] = useState({
    full_name: '',
    phone: ''
  });
  const [notifications, setNotifications] = useState(true);
  const [photoUploading, setPhotoUploading] = useState(false);

  // Change-password modal
  const [passwordModalVisible, setPasswordModalVisible] = useState(false);
  const [passwordForm, setPasswordForm] = useState({
    current: '',
    newPass: '',
    confirm: ''
  });
  const [changingPassword, setChangingPassword] = useState(false);
  
  const router = useRouter();

  useEffect(() => {
    loadUserProfile();
    loadNotificationSettings();
  }, [lang]);

  useEffect(() => {
    const stop = registerLive<UserProfile>(
      'user:profile',
      async () => {
        const token = await AsyncStorage.getItem('userToken');
        const res = await fetchWithTimeout(`${API_BASE_URL}/api/user/profile`, {
          headers: {
            'Authorization': `Bearer ${token}`,
            'ngrok-skip-browser-warning': 'true',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
        });
        if (!res.ok) throw new Error('sync failed');
        return await res.json();
      },
      (data) => { setProfile(data); setLoading(false); }
    );
    return stop;
  }, [lang]);

  const loadNotificationSettings = async () => {
    try {
      const settings = await AsyncStorage.getItem('notificationSettings');
      if (settings) {
        setNotifications(JSON.parse(settings));
      }
    } catch (error) {
      console.error('Error loading notification settings:', error);
    }
  };

  const saveNotificationSettings = async (value: boolean) => {
    try {
      await AsyncStorage.setItem('notificationSettings', JSON.stringify(value));
    } catch (error) {
      console.error('Error saving notification settings:', error);
    }
  };

  const loadUserProfile = async () => {
    try {
      setLoading(true);
      const token = await AsyncStorage.getItem('userToken');
      
      if (!token) {
        Alert.alert(t('app.error'), t('profile.login_required'));
        setLoading(false);
        return;
      }

      const cached = await getCache<UserProfile>('user:profile');
      if (cached) {
        setProfile(cached);
        setFormData({
          full_name: cached.full_name || '',
          phone: cached.phone || ''
        });
        setLoading(false);
      }

      // ✅ KUBADILISHWA: Tumia URL mpya ya ngrok
      const response = await fetchWithTimeout(`${API_BASE_URL}/api/user/profile`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'ngrok-skip-browser-warning': 'true',
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        },
      });

      console.log('📡 Fetching profile from:', `${API_BASE_URL}/api/user/profile`);
      console.log('🔑 Token present:', !!token);

      if (response.ok) {
        const userData = await response.json();
        console.log('✅ Profile data received:', userData);
        setProfile(userData);
        setCache('user:profile', userData).catch(() => {});
        setFormData({
          full_name: userData.full_name || '',
          phone: userData.phone || ''
        });
        // Keep the cached display name in sync (the Blade page does the same
        // with localStorage.setItem('userName', ...)); other screens show it.
        if (userData.full_name) {
          await AsyncStorage.setItem('userName', userData.full_name);
        }
      } else {
        const errorText = await response.text();
        console.error('❌ Profile fetch error:', response.status, errorText);
        throw new Error(t('profile.error_update'));
      }
    } catch (error) {
      console.error('❌ Error loading profile:', error);
      const cached = await getCache<UserProfile>('user:profile');
      if (cached) { setProfile(cached); setLoading(false); return; }
      Alert.alert(t('app.error'), t('profile.error_update'));
    } finally {
      setLoading(false);
    }
  };

  const validateForm = () => {
    if (!formData.full_name.trim()) {
      Alert.alert(t('app.error'), t('profile.name_required'));
      return false;
    }

    if (formData.phone && !/^[0-9+-\s()]{10,}$/.test(formData.phone)) {
      Alert.alert(t('app.error'), t('profile.invalid_phone'));
      return false;
    }

    return true;
  };

  const handleSaveProfile = async () => {
    if (!validateForm()) return;

    try {
      setSaving(true);
      const token = await AsyncStorage.getItem('userToken');
      
      if (!token) {
        Alert.alert(t('app.error'), t('profile.login_required'));
        return;
      }

      if (!(await requireNetwork())) return;

      // ✅ KUBADILISHWA: Tumia URL mpya ya ngrok
      const response = await fetchWithTimeout(`${API_BASE_URL}/api/user/profile`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
          'ngrok-skip-browser-warning': 'true',
          'Accept': 'application/json'
        },
        body: JSON.stringify(formData),
      });

      console.log('📡 Updating profile to:', `${API_BASE_URL}/api/user/profile`);
      console.log('📝 Data being sent:', formData);

      if (response.ok) {
        const result = await response.json();
        console.log('✅ Profile update successful:', result);
        setProfile(result.user);
        setEditing(false);
        Alert.alert(t('profile.success_update'), t('profile.success_message'));
        
        if (result.user.full_name) {
          await AsyncStorage.setItem('userName', result.user.full_name);
        }
      } else {
        const errorData = await response.json().catch(() => ({}));
        console.error('❌ Profile update failed:', response.status, errorData);
        throw new Error(errorData.error || t('profile.error_update'));
      }
    } catch (error: any) {
      console.error('❌ Error updating profile:', error);
      Alert.alert(t('app.error'), error.message || t('profile.error_update'));
    } finally {
      setSaving(false);
    }
  };

  const handleEditToggle = () => {
    if (editing) {
      setFormData({
        full_name: profile?.full_name || '',
        phone: profile?.phone || ''
      });
    }
    setEditing(!editing);
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
        Alert.alert(t('app.error'), t('profile.login_required'));
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
      t('profile.logout'),
      t('profile.logout_confirm'),
      [
        { text: t('profile.cancel'), style: 'cancel' },
        { 
          text: t('profile.logout'), 
          style: 'destructive',
          onPress: async () => {
            try {
              // Futa data ya mtumiaji na session yake
              await signOut();
              // Rudi kwenye skrini ya nyumbani (logout imekamilika)
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

  const toggleNotifications = (value: boolean) => {
    setNotifications(value);
    saveNotificationSettings(value);
  };

  // 🖼️ Upload a profile photo (picks → Cloudinary → saves URL to the server)
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
        Alert.alert(t('app.error'), t('profile.login_required'));
        return;
      }

      if (!(await requireNetwork())) return;

      const response = await fetchWithTimeout(`${API_BASE_URL}/api/user/profile`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
          'ngrok-skip-browser-warning': 'true',
          'Accept': 'application/json',
        },
        body: JSON.stringify({ business_logo_url: cloudinaryResult.secure_url }),
      });

      if (!response.ok) {
        throw new Error(t('profile.photo_error'));
      }

      const updated = await response.json();
      setProfile((prev) => (prev ? { ...prev, business_logo_url: updated.user?.business_logo_url } : prev));

      // Keep the cached copy in sync
      const cached = await AsyncStorage.getItem('userData');
      if (cached) {
        const user = JSON.parse(cached);
        await AsyncStorage.setItem('userData', JSON.stringify({ ...user, business_logo_url: updated.user?.business_logo_url }));
      }

      Alert.alert(t('profile.photo_success_title'), t('profile.photo_success'));
    } catch (error: any) {
      console.error('❌ Photo upload error:', error);
      Alert.alert(t('app.error'), error?.message || t('profile.photo_error'));
    } finally {
      setPhotoUploading(false);
    }
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.screenSafe} edges={['top']}>
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#007AFF" />
          <Text style={styles.loadingText}>{t('app.loading')}</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.screenSafe} edges={['top']}>
    <ScrollView style={styles.container} showsVerticalScrollIndicator={false}>
      {/* Sehemu ya Kichwa */}
      <View style={styles.header}>
        <View style={styles.avatarWrap}>
          {profile?.business_logo_url ? (
            <ZoomableImage
              uri={profile.business_logo_url}
              style={styles.avatar}
              name={profile?.full_name || profile?.business_name}
            />
          ) : (
            <View style={styles.avatar}>
              <Text style={styles.avatarText}>
                {profile?.full_name?.charAt(0)?.toUpperCase() || 
                 profile?.email?.charAt(0)?.toUpperCase() || 'U'}
              </Text>
            </View>
          )}
          {/* Camera badge — change photo anytime */}
          <TouchableOpacity
            style={styles.cameraBadge}
            onPress={handleChangePhoto}
            disabled={photoUploading}
            activeOpacity={0.8}
          >
            {photoUploading ? (
              <ActivityIndicator size="small" color="white" />
            ) : (
              <Ionicons name="camera" size={18} color="white" />
            )}
          </TouchableOpacity>
        </View>
        <Text style={styles.greeting}>
          {profile?.full_name ? `${t('profile.greeting')} ${profile.full_name}!` : t('profile.greeting')}
        </Text>
        <Text style={styles.role}>
          {profile?.role === 'customer' ? t('user_roles.customer') : 
           profile?.role === 'admin' ? t('user_roles.admin') : 
           profile?.role || t('user_roles.customer')}
        </Text>
      </View>

      {/* Sehemu ya Taarifa za Wasifu */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>{t('profile.title')}</Text>
        
        <View style={styles.infoCard}>
          {/* Jina Kamili */}
          <View style={styles.field}>
            <Text style={styles.label}>{t('profile.full_name')}</Text>
            {editing ? (
              <TextInput
                style={styles.input}
                value={formData.full_name}
                onChangeText={(text) => setFormData(prev => ({ ...prev, full_name: text }))}
                placeholder={t('profile.name_placeholder')}
                placeholderTextColor="#999"
              />
            ) : (
              <Text style={styles.value}>
                {profile?.full_name || t('profile.not_set')}
              </Text>
            )}
          </View>

          {/* Barua Pepe */}
          <View style={styles.field}>
            <Text style={styles.label}>{t('profile.email')}</Text>
            <Text style={styles.value}>{profile?.email}</Text>
          </View>

          {/* Nambari ya Simu */}
          <View style={styles.field}>
            <Text style={styles.label}>{t('profile.phone')}</Text>
            {editing ? (
              <TextInput
                style={styles.input}
                value={formData.phone}
                onChangeText={(text) => setFormData(prev => ({ ...prev, phone: text }))}
                placeholder={t('profile.phone_placeholder')}
                placeholderTextColor="#999"
                keyboardType="phone-pad"
              />
            ) : (
              <Text style={styles.value}>
                {profile?.phone || t('profile.not_set')}
              </Text>
            )}
          </View>

          {/* Wadhifa */}
          <View style={styles.field}>
            <Text style={styles.label}>{t('profile.role')}</Text>
            <Text style={[styles.value, styles.roleBadge]}>
              {profile?.role === 'customer' ? t('user_roles.customer') : 
               profile?.role === 'admin' ? t('user_roles.admin') : 
               profile?.role || t('user_roles.customer')}
            </Text>
          </View>

          {/* Hali */}
          <View style={styles.field}>
            <Text style={styles.label}>{t('profile.status')}</Text>
            <Text style={[
              styles.value, 
              styles.statusBadge,
              profile?.status === 'approved' ? styles.approved : 
              profile?.status === 'pending' ? styles.pending : 
              styles.inactive
            ]}>
              {profile?.status === 'approved' ? t('profile.status_approved') : 
               profile?.status === 'pending' ? t('profile.status_pending') : 
               profile?.status || t('profile.status_pending')}
            </Text>
          </View>

          {/* Mwanachama Tangu */}
          <View style={styles.field}>
            <Text style={styles.label}>{t('profile.member_since')}</Text>
            <Text style={styles.value}>
              {profile?.created_at ? formatDate(profile.created_at) : t('profile.not_set')}
            </Text>
          </View>
        </View>
      </View>

      {/* Sehemu ya Mipangilio */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>{t('profile.settings')}</Text>
        
        <View style={styles.infoCard}>
          {/* Arifa */}
          <View style={styles.settingRow}>
            <View style={styles.settingInfo}>
              <Text style={styles.settingLabel}>{t('profile.notification_label')}</Text>
              <Text style={styles.settingDescription}>
                {t('profile.notification_desc')}
              </Text>
            </View>
            <Switch
              value={notifications}
              onValueChange={toggleNotifications}
              trackColor={{ false: '#767577', true: '#81b0ff' }}
              thumbColor={notifications ? '#007AFF' : '#f4f3f4'}
            />
          </View>

          {/* Badilisha Nenosiri */}
          <TouchableOpacity
            style={styles.changePasswordRow}
            onPress={() => setPasswordModalVisible(true)}
          >
            <Ionicons name="lock-closed-outline" size={18} color="#007AFF" />
            <Text style={styles.changePasswordText}>{t('profile.change_password')}</Text>
            <Ionicons name="chevron-forward" size={18} color="#bdc3c7" />
          </TouchableOpacity>

          {/* Vitufe vya Hariri/Hifadhi */}
          <View style={styles.buttonRow}>
            <TouchableOpacity 
              style={[styles.button, editing ? styles.cancelButton : styles.editButton]}
              onPress={handleEditToggle}
              disabled={saving}
            >
              <Text style={styles.buttonText}>
                {editing ? t('profile.cancel') : t('profile.edit_title')}
              </Text>
            </TouchableOpacity>

            {editing && (
              <TouchableOpacity 
                style={[styles.button, styles.saveButton, saving && styles.disabledButton]}
                onPress={handleSaveProfile}
                disabled={saving}
              >
                {saving ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <Text style={styles.buttonText}>{t('profile.save')}</Text>
                )}
              </TouchableOpacity>
            )}
          </View>
        </View>
      </View>

      {/* Sehemu ya Vitendo vya Akaunti */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>{t('profile.account_actions')}</Text>
        
        <View style={styles.infoCard}>
          <TouchableOpacity 
            style={[styles.actionButton, styles.logoutButton]} 
            onPress={handleLogout}
          >
            <Text style={styles.logoutButtonText}>🚪 {t('profile.logout')}</Text>
          </TouchableOpacity>
        </View>
      </View>

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
                <Text style={styles.buttonText}>{t('profile.cancel')}</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalButton, styles.saveButton]}
                onPress={handleChangePassword}
                disabled={changingPassword || !passwordForm.current || !passwordForm.newPass || !passwordForm.confirm}
              >
                {changingPassword ? (
                  <ActivityIndicator size="small" color="#fff" />
                ) : (
                  <Text style={styles.buttonText}>{t('profile.change_password_save')}</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Sehemu ya Mwisho */}
      <View style={styles.footer}>
        <Text style={styles.footerText}>
          {t('app.title')} © {new Date().getFullYear()}
        </Text>
        <Text style={styles.footerSubtext}>
          {t('app.version')}
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
  },
  center: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f8f9fa',
  },
  loadingText: {
    marginTop: 10,
    fontSize: 16,
    color: '#666',
  },
  header: {
    backgroundColor: 'white',
    padding: 30,
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  avatarWrap: {
    marginBottom: 15,
  },
  avatar: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: '#007AFF',
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarText: {
    color: 'white',
    fontSize: 32,
    fontWeight: 'bold',
  },
  cameraBadge: {
    position: 'absolute',
    right: -4,
    bottom: 0,
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: '#007AFF',
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
  greeting: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 5,
  },
  role: {
    fontSize: 16,
    color: '#666',
    textTransform: 'capitalize',
  },
  section: {
    padding: 20,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#333',
    marginBottom: 15,
  },
  infoCard: {
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  field: {
    marginBottom: 20,
  },
  label: {
    fontSize: 14,
    fontWeight: '600',
    color: '#666',
    marginBottom: 5,
  },
  value: {
    fontSize: 16,
    color: '#333',
    paddingVertical: 8,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
    backgroundColor: '#f9f9f9',
  },
  roleBadge: {
    backgroundColor: '#e3f2fd',
    color: '#1976d2',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
    fontSize: 14,
    fontWeight: '600',
    alignSelf: 'flex-start',
  },
  statusBadge: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 12,
    fontSize: 14,
    fontWeight: '600',
    alignSelf: 'flex-start',
    textTransform: 'capitalize',
  },
  approved: {
    backgroundColor: '#e8f5e8',
    color: '#2e7d32',
  },
  pending: {
    backgroundColor: '#fff3e0',
    color: '#ef6c00',
  },
  inactive: {
    backgroundColor: '#ffebee',
    color: '#c62828',
  },
  settingRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 20,
  },
  settingInfo: {
    flex: 1,
    marginRight: 15,
  },
  settingLabel: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333',
    marginBottom: 4,
  },
  settingDescription: {
    fontSize: 12,
    color: '#666',
    lineHeight: 16,
  },
  buttonRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 10,
  },
  button: {
    flex: 1,
    padding: 15,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  editButton: {
    backgroundColor: '#007AFF',
  },
  saveButton: {
    backgroundColor: '#27ae60',
  },
  cancelButton: {
    backgroundColor: '#95a5a6',
  },
  disabledButton: {
    backgroundColor: '#bdc3c7',
    opacity: 0.6,
  },
  buttonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: '600',
  },
  actionButton: {
    padding: 15,
    borderRadius: 8,
    alignItems: 'center',
  },
  logoutButton: {
    backgroundColor: '#e74c3c',
  },
  logoutButtonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: '600',
  },
  footer: {
    padding: 30,
    alignItems: 'center',
    backgroundColor: '#f1f2f6',
  },
  footerText: {
    fontSize: 14,
    color: '#666',
    marginBottom: 5,
  },
  footerSubtext: {
    fontSize: 12,
    color: '#999',
  },
  changePasswordRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 14,
    paddingHorizontal: 4,
    marginBottom: 8,
    borderTopWidth: 1,
    borderTopColor: '#f0f0f0',
  },
  changePasswordText: {
    flex: 1,
    fontSize: 15,
    fontWeight: '600',
    color: '#007AFF',
  },
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
});