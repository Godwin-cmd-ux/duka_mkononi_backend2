import AsyncStorage from '@react-native-async-storage/async-storage';
import { ResizeMode, Video } from 'expo-av';
import * as ImagePicker from 'expo-image-picker';
import * as WebBrowser from 'expo-web-browser';
import { useRouter } from 'expo-router';
import React, { useEffect, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    Dimensions,
    Image,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLang } from '../../context/LanguageContext';
import { useSession } from '../../context/SessionContext';
import LogoutButton from '../../components/logout-button';

const { width } = Dimensions.get('window');
import { API_BASE_URL } from '../../constants/api';
import { uploadToCloudinary } from '../../utils/cloudinary';
import { getCache, setCache } from '../../db/cache';
import { registerLive } from '../../lib/syncer';
import { requireNetwork } from '../../lib/network';

// 🔥 MAX FILE SIZES (in bytes)
const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB
const MAX_VIDEO_SIZE = 20 * 1024 * 1024; // 20MB

// 💰 ADVERTISEMENT PRICING (matches the server: TZS 3,000 per 30 days)
const PAYMENT_AMOUNT = 3000;
const PAYMENT_DURATION_DAYS = 30;
const DAY_IN_MS = 24 * 60 * 60 * 1000;

// 🔥 Locale map for date formatting
const localeMap: Record<string, string> = {
  sw: 'sw-TZ',
  en: 'en-US',
  fr: 'fr-FR',
  hi: 'hi-IN',
  ur: 'ur-PK',
  es: 'es-ES',
  de: 'de-DE',
  zh: 'zh-CN',
};

// 🔥 FETCH WITH TIMEOUT FUNCTION
const fetchWithTimeout = async (url: string, options: any, timeout = 30000) => {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeout);

  try {
    const response = await fetch(url, {
      ...options,
      signal: controller.signal,
    });
    
    clearTimeout(timeoutId);
    return response;
  } catch (error) {
    clearTimeout(timeoutId);
    throw error;
  }
};

// 🔥 CHECK FILE SIZE
const checkFileSize = async (fileUri: string, type: 'image' | 'video'): Promise<boolean> => {
  try {
    const response = await fetch(fileUri);
    const blob = await response.blob();
    const size = blob.size;
    
    const maxSize = type === 'image' ? MAX_IMAGE_SIZE : MAX_VIDEO_SIZE;
    if (size > maxSize) {
      const maxSizeMB = maxSize / (1024 * 1024);
      throw new Error(`Faili ni kubwa sana! Kiwango cha juu: ${maxSizeMB}MB`);
    }
    return true;
  } catch (error) {
    throw error;
  }
};


interface UserMatangazo {
  id: number;
  user_id: number;
  title: string;
  description: string;
  media_url: string;
  media_type: 'image' | 'video';
  thumbnail_url: string;
  created_at: string;
  expires_at: string;
  payment_status: string;
  is_free?: boolean;
  is_active?: boolean;
  order_tracking_id?: string | null;
  like_count: number;
  report_count: number;
  users: {
    business_name: string;
    full_name: string;
    phone: string;
    email: string;
  };
}

export default function TangazaScreen() {
  const { t, lang } = useLang();
  const router = useRouter();
  const { signOut } = useSession();
  const [activeTab, setActiveTab] = useState<'post' | 'myPosts'>('post');
  const [description, setDescription] = useState('');
  const [media, setMedia] = useState<string | null>(null);
  const [mediaType, setMediaType] = useState<'image' | 'video' | null>(null);
  const [processPhase, setProcessPhase] = useState<'idle' | 'uploading' | 'redirecting' | 'checking'>('idle');
  const uploading = processPhase !== 'idle';
  const [isOnline, setIsOnline] = useState(true);
  
  const [currentUser, setCurrentUser] = useState<{
    id: number;
    email: string;
    name: string;
    role: string;
    phone?: string;
    business_name?: string;
  } | null>(null);
  const [loadingUser, setLoadingUser] = useState(true);
  const [myAdvertisements, setMyAdvertisements] = useState<UserMatangazo[]>([]);
  const [loadingAds, setLoadingAds] = useState(false);

  useEffect(() => {
    const checkNetwork = async () => {
      try {
        const response = await fetch('https://www.google.com', { method: 'HEAD' });
        setIsOnline(response.ok);
      } catch {
        setIsOnline(false);
      }
    };
    
    checkNetwork();
    const interval = setInterval(checkNetwork, 15000);
    return () => clearInterval(interval);
  }, []);

  const loadUserData = async () => {
    try {
      setLoadingUser(true);
      
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        Alert.alert(t('app.error'), t('adverts.error_auth'));
        await signOut();
        router.replace('/(tabs)');
        return;
      }

      const userDataString = await AsyncStorage.getItem('userData');
      const userId = await AsyncStorage.getItem('userId');
      const userEmail = await AsyncStorage.getItem('userEmail');

      if (userDataString && userId) {
        const userData = JSON.parse(userDataString);
        setCurrentUser({
          id: parseInt(userId),
          email: userEmail || userData.email,
          name: userData.full_name || userData.business_name || 'Mtumiaji',
          role: userData.role,
          phone: userData.phone || '',
          business_name: userData.business_name || ''
        });
      }
    } catch (error) {
      console.error('❌ Hitilafu ya kupakia taarifa za mtumiaji:', error);
    } finally {
      setLoadingUser(false);
    }
  };

  const loadMyAdvertisements = async () => {
    try {
      setLoadingAds(true);
      const token = await AsyncStorage.getItem('userToken');
      
      if (!token) {
        console.log('Hakuna token');
        return;
      }

      const cached = await getCache<any[]>('matangazo:my');
      if (cached) { setMyAdvertisements(cached); }

      console.log('📡 Kupakia matangazo yangu...');
      // Longer timeout: hosted servers (e.g. Render free tier) can take 30-60s to cold-start
      const response = await fetchWithTimeout(
        `${API_BASE_URL}/api/matangazo/my`,
        {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
          },
        },
        45000
      );

      console.log('📊 Matangazo response status:', response.status);
      
      if (response.ok) {
        const data = await response.json();
        console.log('📊 Matangazo yangu yamepakuliwa:', data.length);
        setMyAdvertisements(data);
        setCache('matangazo:my', data).catch(() => {});
      } else {
        const errorText = await response.text();
        console.error('❌ Hitilafu ya kupakia matangazo:', response.status, errorText);
        Alert.alert(t('app.error'), t('adverts.error_load'));
      }
    } catch (error: any) {
      console.error('❌ Hitilafu katika kupakia matangazo:', error);
      const cachedAd = await getCache<any[]>('matangazo:my');
      if (cachedAd) { setMyAdvertisements(cachedAd); setLoadingAds(false); return; }
      if (error?.name === 'AbortError') {
        Alert.alert(t('app.error'), t('adverts.error_server_slow'));
      } else {
        Alert.alert(t('app.error'), t('adverts.error_load'));
      }
    } finally {
      setLoadingAds(false);
    }
  };

  useEffect(() => {
    loadUserData();
  }, [lang]);

  useEffect(() => {
    if (activeTab === 'myPosts') {
      loadMyAdvertisements();
    }
  }, [activeTab, lang]);

  const pickMedia = async () => {
    try {
      const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (status !== 'granted') {
        Alert.alert(t('adverts.permission_denied'), t('adverts.permission_text'));
        return;
      }

      let result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ImagePicker.MediaTypeOptions.All,
        allowsEditing: false,
        quality: 0.7,
        videoMaxDuration: 60,
      });

      if (!result.canceled && result.assets && result.assets[0]) {
        const selectedMedia = result.assets[0];
        
        try {
          if (selectedMedia.type === 'video') {
            await checkFileSize(selectedMedia.uri, 'video');
          } else {
            await checkFileSize(selectedMedia.uri, 'image');
          }
          
          setMedia(selectedMedia.uri);
          setMediaType(selectedMedia.type as 'image' | 'video');
        } catch (error: any) {
          Alert.alert(t('app.error'), t('adverts.error_select_media'));
        }
      }
    } catch (error) {
      console.error('Hitilafu ya kuchagua media:', error);
      Alert.alert(t('app.error'), t('adverts.error_select_media'));
    }
  };

  const clearMedia = () => {
    setMedia(null);
    setMediaType(null);
  };

  // 💳 START PESAPAL PAYMENT FOR AN EXISTING MATANGAZO (TZS 3,000 / 30 days)
  const initiatePayment = async (matangazoId: number, description: string): Promise<{ orderTrackingId: string; redirectUrl: string }> => {
    const token = await AsyncStorage.getItem('userToken');
    if (!token) {
      throw new Error(t('adverts.error_auth'));
    }

    const response = await fetchWithTimeout(
      `${API_BASE_URL}/api/payments/pesapal/initiate`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({
          amount: PAYMENT_AMOUNT,
          description: description,
          matangazo_id: matangazoId,
          phone: currentUser?.phone || '',
          email: currentUser?.email || '',
          name: currentUser?.name || '',
        }),
      },
      30000
    );

    const payJson = await response.json().catch(() => ({}));
    if (!response.ok) {
      const paymentError: any = new Error(payJson.error || t('adverts.payment_error_message'));
      if (payJson.code === 'PESAPAL_NOT_CONFIGURED') {
        paymentError.pesapalNotConfigured = true;
      }
      throw paymentError;
    }
    if (!payJson.redirect_url) {
      throw new Error(t('adverts.payment_error_message'));
    }

    return {
      orderTrackingId: payJson.payment?.order_tracking_id || payJson.order_tracking_id,
      redirectUrl: payJson.redirect_url,
    };
  };

  // 🔍 CHECK PESAPAL PAYMENT STATUS (single call)
  const checkPaymentStatus = async (orderTrackingId: string): Promise<string> => {
    const token = await AsyncStorage.getItem('userToken');
    if (!token) return 'pending';

    const response = await fetchWithTimeout(
      `${API_BASE_URL}/api/payments/pesapal/status/${orderTrackingId}`,
      {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
      },
      20000
    );
    if (!response.ok) return 'pending';
    const data = await response.json();
    return data.payment?.status || 'pending';
  };

  // 🔁 POLL PAYMENT STATUS UNTIL CONFIRMED (or timeout)
  const checkPaymentStatusWithRetry = async (orderTrackingId: string, retries = 8): Promise<string> => {
    for (let i = 0; i < retries; i++) {
      try {
        const status = await checkPaymentStatus(orderTrackingId);
        if (status === 'completed' || status === 'failed') {
          return status;
        }
      } catch (error) {
        console.warn('⚠️ Status check attempt failed:', error);
      }
      await new Promise(resolve => setTimeout(resolve, 3000));
    }
    return 'pending';
  };

  // 💳 OPEN PESAPAL PAGE + CONFIRM PAYMENT (shared by new posts and renewals)
  const runPaymentForMatangazo = async (matangazoId: number, description: string) => {
    setProcessPhase('redirecting');
    try {
      const { orderTrackingId, redirectUrl } = await initiatePayment(matangazoId, description);
      if (!orderTrackingId) {
        throw new Error(t('adverts.payment_error_message'));
      }

      // Fungua ukurasa wa malipo wa PesaPal
      await WebBrowser.openBrowserAsync(redirectUrl, { enableBarCollapsing: true });

      // Angalia hali ya malipo baada ya kurudi
      setProcessPhase('checking');
      const finalStatus = await checkPaymentStatusWithRetry(orderTrackingId);
      setProcessPhase('idle');

      if (finalStatus === 'completed') {
        Alert.alert(t('adverts.payment_success_title'), t('adverts.payment_success_message'));
      } else if (finalStatus === 'failed') {
        Alert.alert(t('adverts.payment_failed_title'), t('adverts.payment_failed_message'));
      } else {
        Alert.alert(t('adverts.payment_pending_title'), t('adverts.payment_pending_message'));
      }

      loadMyAdvertisements();
    } catch (error: any) {
      setProcessPhase('idle');
      console.error('❌ Hitilafu ya malipo:', error);
      if (error?.pesapalNotConfigured) {
        Alert.alert(t('adverts.payment_unavailable_title'), t('adverts.payment_unavailable_message'));
      } else {
        Alert.alert(t('app.error'), error?.message || t('adverts.payment_error_message'));
      }
    }
  };

  // 🚀 SUBMIT NEW ADVERTISEMENT (upload → create pending ad → PesaPal payment)
  const handleUpload = async () => {
    if (!currentUser) {
      Alert.alert(t('app.error'), t('adverts.error_auth'));
      return;
    }

    if (!description.trim()) {
      Alert.alert(t('app.error'), t('adverts.error_description'));
      return;
    }

    if (!media) {
      Alert.alert(t('app.error'), t('adverts.error_no_media'));
      return;
    }

    if (!isOnline) {
      Alert.alert(t('adverts.no_connection'), t('adverts.error_network'));
      return;
    }

    if (!(await requireNetwork())) return;

    setProcessPhase('uploading');

    try {
      console.log('🚀 Anzisha upakiaji wa matangazo...');

      // 1. Pakia media kwenye Cloudinary
      const cloudinaryResult = await uploadToCloudinary(media!, mediaType || 'image');
      console.log('✅ Media imepakiwa kikamilifu kwenye Cloudinary');

      // 2. Tuma matangazo (hali: pending malipo)
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        throw new Error('Hakuna token ya kuthibitisha. Tafadhali ingia tena.');
      }

      const matangazoData = {
        description: description.trim(),
        media_url: cloudinaryResult.secure_url,
        media_type: mediaType || 'image',
        thumbnail_url: cloudinaryResult.secure_url,
        payment_status: 'pending',
        expires_at: null,
      };

      console.log('📤 Kutuma matangazo kwenye backend...', matangazoData);

      const response = await fetchWithTimeout(
        `${API_BASE_URL}/api/matangazo`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
          },
          body: JSON.stringify(matangazoData),
        },
        30000
      );

      console.log('📄 Backend response status:', response.status);

      if (!response.ok) {
        const errorText = await response.text();
        console.error('❌ Hitilafu ya kuhifadhi matangazo:', response.status, errorText);

        let errorMessage = 'Imeshindikana kuhifadhi matangazo.';
        try {
          const errorData = JSON.parse(errorText);
          errorMessage = errorData.error || errorData.details || errorText;
        } catch {
          errorMessage = errorText || `Hitilafu ya server: ${response.status}`;
        }

        throw new Error(errorMessage);
      }

      const matangazoJson = await response.json();
      const matangazoId = matangazoJson?.data?.id;
      if (!matangazoId) {
        throw new Error('Matangazo ID haijapatikana');
      }
      console.log('✅ Matangazo limeundwa (pending malipo):', matangazoId);

      // 3. Fungua ukurasa wa PesaPal kukamilisha malipo ya TZS 3,000
      await runPaymentForMatangazo(matangazoId, description.trim());

      // Futa fomu
      setDescription('');
      setMedia(null);
      setMediaType(null);

      // Pakia matangazo mapya na ubadili tab
      loadMyAdvertisements();
      setActiveTab('myPosts');

    } catch (error: any) {
      setProcessPhase('idle');
      console.error('❌ Hitilafu ya upakiaji:', error);
      Alert.alert(t('app.error'), error?.message || t('adverts.error_load'));
    }
  };

  // 🔄 RENEW / COMPLETE PAYMENT FOR AN EXISTING AD
  const handleRenewPayment = (ad: UserMatangazo) => {
    if (!isOnline) {
      Alert.alert(t('adverts.no_connection'), t('adverts.error_network'));
      return;
    }

    Alert.alert(
      t('adverts.renew_confirm_title'),
      t('adverts.renew_confirm_message'),
      [
        { text: t('adverts.delete_confirm_cancel'), style: 'cancel' },
        {
          text: t('adverts.renew_confirm_yes'),
          onPress: async () => {
            if (!(await requireNetwork())) return;
            const adDescription = ad.description || ad.title || t('adverts.renew_confirm_title');
            runPaymentForMatangazo(ad.id, adDescription);
          },
        },
      ]
    );
  };

  // 🏷️ STATUS LOGIC FOR MY ADS
  const getAdStatusInfo = (ad: UserMatangazo) => {
    const isFree = ad.is_free === true || ad.payment_status === 'free';
    if (isFree) {
      return { label: t('adverts.ad_status_free'), bg: '#d4edda', fg: '#155724', action: 'none', note: '' };
    }
    if (ad.payment_status === 'pending' || ad.payment_status === 'failed') {
      return {
        label: t('adverts.ad_status_pending'),
        bg: '#fff3cd',
        fg: '#856404',
        action: 'pay',
        note: t('adverts.ad_pending_note'),
      };
    }
    // payment_status === 'completed'
    const expired = ad.expires_at && new Date(ad.expires_at).getTime() <= Date.now();
    if (expired) {
      return {
        label: t('adverts.ad_status_expired'),
        bg: '#f8d7da',
        fg: '#721c24',
        action: 'renew',
        note: t('adverts.ad_expired_note'),
      };
    }
    return { label: t('adverts.ad_status_paid'), bg: '#d4edda', fg: '#155724', action: 'live', note: '' };
  };

  const deleteAdvertisement = async (id: number) => {
    try {
      Alert.alert(
        t('adverts.delete_confirm_title'),
        t('adverts.delete_confirm_message'),
        [
          { text: t('adverts.delete_confirm_cancel'), style: 'cancel' },
          { 
            text: t('adverts.delete_confirm_delete'), 
            style: 'destructive',
            onPress: async () => {
              if (!(await requireNetwork())) return;
              const token = await AsyncStorage.getItem('userToken');
              
              const response = await fetchWithTimeout(
                `${API_BASE_URL}/api/matangazo/${id}`,
                {
                  method: 'DELETE',
                  headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                  },
                },
                15000
              );

              if (response.ok) {
                Alert.alert(t('app.success'), t('adverts.success_delete'));
                loadMyAdvertisements();
              } else {
                const errorText = await response.text();
                Alert.alert(t('app.error'), t('adverts.error_delete'));
              }
            }
          }
        ]
      );
    } catch (error) {
      console.error('❌ Hitilafu ya kufuta matangazo:', error);
      Alert.alert(t('app.error'), t('adverts.error_delete'));
    }
  };

  const renderMediaPreview = () => {
    if (!media) return null;

    return (
      <View style={styles.mediaPreview}>
        {mediaType === 'image' ? (
          <Image source={{ uri: media }} style={styles.media} />
        ) : (
          <Video
            source={{ uri: media }}
            style={styles.media}
            useNativeControls
            resizeMode={ResizeMode.COVER}
            shouldPlay={false}
          />
        )}
        <TouchableOpacity style={styles.clearButton} onPress={clearMedia}>
          <Text style={styles.clearButtonText}>{t('adverts.media_clear_button')}</Text>
        </TouchableOpacity>
      </View>
    );
  };

  const renderPostNew = () => {
    if (loadingUser) {
      return (
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#FF6B35" />
          <Text style={styles.loadingText}>{t('adverts.loading_refresh')}</Text>
        </View>
      );
    }

    return (
      <ScrollView style={styles.tabContent} showsVerticalScrollIndicator={false}>
        <View style={styles.header}>
          <View style={styles.headerTopRow}>
            <LogoutButton iconOnly />
          </View>
          <Text style={styles.title}>{t('adverts.title')}</Text>
          <Text style={styles.subtitle}>{t('adverts.subtitle')}</Text>
          
          <View style={styles.paymentBanner}>
            <Text style={styles.paymentBannerTitle}>{t('adverts.payment_banner_title')}</Text>
            <Text style={styles.paymentBannerText}>
              {t('adverts.payment_banner_point1')}{'\n'}
              {t('adverts.payment_banner_point2')}{'\n'}
              {t('adverts.payment_banner_point3')}
            </Text>
            <View style={styles.paymentPriceRow}>
              <Text style={styles.paymentPrice}>{`TZS ${PAYMENT_AMOUNT.toLocaleString()}`}</Text>
              <Text style={styles.paymentDuration}>{t('adverts.payment_duration', { days: PAYMENT_DURATION_DAYS })}</Text>
            </View>
          </View>

          {!isOnline && (
            <View style={styles.networkWarning}>
              <Text style={styles.networkWarningText}>
                ⚠️ {t('adverts.error_network')}
              </Text>
            </View>
          )}
        </View>

        <View style={styles.mediaSection}>
          <Text style={styles.sectionTitle}>
            {mediaType === 'video' ? t('adverts.media_section_title_video') : t('adverts.media_section_title_image')}
          </Text>
          
          {media ? (
            renderMediaPreview()
          ) : (
            <TouchableOpacity 
              style={styles.mediaPicker} 
              onPress={pickMedia}
              disabled={!isOnline || uploading}
            >
              <Text style={styles.mediaPickerIcon}>📁</Text>
              <Text style={styles.mediaPickerText}>{t('adverts.media_picker_text')}</Text>
              <Text style={styles.fileSizeHint}>
                {mediaType === 'video' ? t('adverts.file_size_hint_video') : t('adverts.file_size_hint_image')}
              </Text>
              {!isOnline && (
                <Text style={styles.offlineHint}>
                  {t('adverts.offline_hint')}
                </Text>
              )}
            </TouchableOpacity>
          )}
        </View>

        <View style={styles.descriptionSection}>
          <Text style={styles.sectionTitle}>{t('adverts.description_label')}</Text>
          <TextInput
            style={styles.textInput}
            placeholder={t('adverts.description_placeholder')}
            value={description}
            onChangeText={setDescription}
            multiline
            numberOfLines={4}
            textAlignVertical="top"
            maxLength={500}
            editable={isOnline && !uploading}
          />
          <Text style={styles.charCount}>
            {t('adverts.char_count', { length: description.length })}
          </Text>
        </View>

        <TouchableOpacity 
          style={[
            styles.uploadButton, 
            (uploading || !isOnline) && styles.uploadButtonDisabled
          ]} 
          onPress={handleUpload}
          disabled={uploading || !isOnline}
        >
          {uploading ? (
            <View style={styles.uploadingContainer}>
              <ActivityIndicator color="white" size="small" />
              <Text style={styles.uploadingText}>
                {processPhase === 'uploading' && t('adverts.uploading_phase')}
                {processPhase === 'redirecting' && t('adverts.payment_redirect_phase')}
                {processPhase === 'checking' && t('adverts.payment_checking_phase')}
              </Text>
            </View>
          ) : !isOnline ? (
            <Text style={styles.uploadButtonText}>{t('adverts.upload_button_offline')}</Text>
          ) : (
            <Text style={styles.uploadButtonText}>{t('adverts.upload_button')}</Text>
          )}
        </TouchableOpacity>

        <View style={styles.instructions}>
          <Text style={styles.instructionsTitle}>{t('adverts.instructions_title')}</Text>
          <Text style={styles.instruction}>{t('adverts.instruction_image')}</Text>
          <Text style={styles.instruction}>{t('adverts.instruction_video')}</Text>
          <Text style={styles.instruction}>{t('adverts.instruction_network')}</Text>
          <Text style={styles.instruction}>{t('adverts.instruction_wait')}</Text>
        </View>
      </ScrollView>
    );
  };

  const renderMyPosts = () => {
    return (
      <ScrollView style={styles.tabContent} showsVerticalScrollIndicator={false}>
        <View style={styles.mainHeader}>
          <Text style={styles.mainHeaderTitle}>{t('adverts.my_posts_title')}</Text>
          <TouchableOpacity 
            style={styles.refreshButton}
            onPress={loadMyAdvertisements}
            disabled={loadingAds}
          >
            <Text style={styles.refreshButtonText}>
              {loadingAds ? t('adverts.loading_refresh') : '🔄 ' + t('adverts.refresh_button')}
            </Text>
          </TouchableOpacity>
        </View>

        {loadingAds ? (
          <View style={styles.loadingContainer}>
            <ActivityIndicator size="large" color="#FF6B35" />
            <Text style={styles.loadingText}>{t('adverts.loading_refresh')}</Text>
          </View>
        ) : myAdvertisements.length > 0 ? (
          <View style={styles.adsList}>
            {myAdvertisements.map((ad) => {
              const statusInfo = getAdStatusInfo(ad);
              const daysLeft = ad.expires_at
                ? Math.max(0, Math.ceil((new Date(ad.expires_at).getTime() - Date.now()) / DAY_IN_MS))
                : 0;
              return (
              <View key={ad.id} style={styles.adCard}>
                <View style={styles.adHeader}>
                  <Text style={styles.adTitle}>{ad.title || t('adverts.ad_title_prefix', { id: ad.id })}</Text>
                  <Text style={styles.adDate}>
                    {statusInfo.action === 'live' && daysLeft > 0
                      ? t('adverts.ad_days_left', { days: daysLeft })
                      : new Date(ad.created_at).toLocaleDateString(localeMap[lang] || 'sw-TZ')}
                  </Text>
                </View>
                
                <Text style={styles.adDescription} numberOfLines={3}>
                  {ad.description}
                </Text>
                
                <View style={styles.adMediaPreview}>
                  {ad.media_type === 'video' ? (
                    <View style={styles.videoPlaceholder}>
                      <Text style={styles.mediaTypeIcon}>🎥</Text>
                      <Text style={styles.mediaTypeText}>{t('adverts.media_section_title_video')}</Text>
                    </View>
                  ) : (
                    <Image 
                      source={{ uri: ad.media_url }} 
                      style={styles.adImage}
                      resizeMode="cover"
                    />
                  )}
                </View>
                
                {/* STATISTICS SECTION WITH LIKES AND REPORTS */}
                <View style={styles.adStatsSection}>
                  <View style={styles.reactionStats}>
                    <View style={styles.statItem}>
                      <Text style={styles.statIcon}>👍</Text>
                      <Text style={styles.statText}>{t('adverts.ad_stats_likes', { count: ad.like_count || 0 })}</Text>
                    </View>
                    
                    <View style={styles.statItem}>
                      <Text style={styles.statIconWarning}>⚠️</Text>
                      <Text style={styles.statTextWarning}>{t('adverts.ad_stats_reports', { count: ad.report_count || 0 })}</Text>
                    </View>
                  </View>
                  
                  <View style={[
                    styles.adStatus,
                    { backgroundColor: statusInfo.bg }
                  ]}>
                    <Text style={[
                      styles.adStatusText,
                      { color: statusInfo.fg }
                    ]}>
                      {statusInfo.label}
                    </Text>
                  </View>
                </View>

                {statusInfo.note ? (
                  <Text style={styles.adNote}>{statusInfo.note}</Text>
                ) : null}

                {statusInfo.action === 'renew' && (
                  <TouchableOpacity
                    style={styles.renewButton}
                    onPress={() => handleRenewPayment(ad)}
                  >
                    <Text style={styles.renewButtonText}>{t('adverts.renew_button')}</Text>
                  </TouchableOpacity>
                )}

                {statusInfo.action === 'pay' && (
                  <TouchableOpacity
                    style={styles.payNowButton}
                    onPress={() => handleRenewPayment(ad)}
                  >
                    <Text style={styles.payNowButtonText}>{t('adverts.pay_now_button')}</Text>
                  </TouchableOpacity>
                )}
                
                <TouchableOpacity 
                  style={styles.deleteButton}
                  onPress={() => deleteAdvertisement(ad.id)}
                >
                  <Text style={styles.deleteButtonText}>{t('adverts.delete_button')}</Text>
                </TouchableOpacity>
              </View>
              );
            })}
          </View>
        ) : (
          <View style={styles.emptyPosts}>
            <Text style={styles.emptyPostsIcon}>{t('adverts.empty_posts_icon')}</Text>
            <Text style={styles.emptyPostsText}>{t('adverts.empty_posts_text')}</Text>
            <Text style={styles.emptyPostsSubtext}>
              {t('adverts.empty_posts_subtext')}
            </Text>
            <TouchableOpacity 
              style={styles.createFirstButton}
              onPress={() => setActiveTab('post')}
            >
              <Text style={styles.createFirstButtonText}>{t('adverts.create_first_button')}</Text>
            </TouchableOpacity>
          </View>
        )}
      </ScrollView>
    );
  };

  if (loadingUser) {
    return (
      <SafeAreaView style={styles.screenSafe} edges={['top']}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#FF6B35" />
          <Text style={styles.loadingText}>{t('adverts.loading_refresh')}</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.screenSafe} edges={['top']}>
    <View style={styles.container}>
      <View style={styles.tabContainer}>
        <TouchableOpacity 
          style={[styles.tab, activeTab === 'post' && styles.activeTab]}
          onPress={() => setActiveTab('post')}
        >
          <Text style={[styles.tabText, activeTab === 'post' && styles.activeTabText]}>
            {t('adverts.tab_create')}
          </Text>
        </TouchableOpacity>
        
        <TouchableOpacity 
          style={[styles.tab, activeTab === 'myPosts' && styles.activeTab]}
          onPress={() => setActiveTab('myPosts')}
        >
          <Text style={[styles.tabText, activeTab === 'myPosts' && styles.activeTabText]}>
            {t('adverts.tab_my_posts')}
          </Text>
        </TouchableOpacity>
      </View>

      {activeTab === 'post' ? renderPostNew() : renderMyPosts()}

      {processPhase !== 'idle' && (
        <View style={styles.processingOverlay}>
          <View style={styles.processingCard}>
            <ActivityIndicator size="large" color="#FF6B35" />
            <Text style={styles.processingTitle}>
              {processPhase === 'uploading' && t('adverts.uploading_phase')}
              {processPhase === 'redirecting' && t('adverts.payment_redirect_phase')}
              {processPhase === 'checking' && t('adverts.payment_checking_phase')}
            </Text>
            <Text style={styles.processingSubtext}>
              {t('adverts.payment_terms_note')}
            </Text>
          </View>
        </View>
      )}
    </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screenSafe: {
    flex: 1,
    backgroundColor: '#f8f8f8',
  },
  container: {
    flex: 1,
  },
  tabContainer: {
    flexDirection: 'row',
    backgroundColor: 'white',
    borderBottomWidth: 1,
    borderBottomColor: '#ddd',
  },
  tab: {
    flex: 1,
    paddingVertical: 16,
    alignItems: 'center',
    borderBottomWidth: 3,
    borderBottomColor: 'transparent',
  },
  activeTab: {
    borderBottomColor: '#FF6B35',
  },
  tabText: {
    fontSize: 14,
    fontWeight: '700',
    color: '#666',
  },
  activeTabText: {
    color: '#FF6B35',
  },
  tabContent: {
    flex: 1,
  },
  header: {
    marginBottom: 25,
    padding: 16,
    backgroundColor: 'white',
    borderRadius: 12,
  },
  headerTopRow: {
    alignSelf: 'flex-end',
    marginBottom: 8,
  },
  title: {
    fontSize: 22,
    fontWeight: 'bold',
    color: '#1a1a1a',
    marginBottom: 6,
  },
  subtitle: {
    fontSize: 14,
    color: '#666',
    marginBottom: 15,
  },
  paymentBanner: {
    backgroundColor: '#fff3cd',
    padding: 15,
    borderRadius: 8,
    borderLeftWidth: 4,
    borderLeftColor: '#f0ad4e',
    marginTop: 10,
  },
  paymentBannerTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#856404',
    marginBottom: 8,
  },
  paymentBannerText: {
    fontSize: 14,
    color: '#856404',
    lineHeight: 20,
  },
  paymentPriceRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 10,
    gap: 8,
  },
  paymentPrice: {
    backgroundColor: '#f0ad4e',
    color: 'white',
    fontWeight: 'bold',
    fontSize: 14,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 6,
    overflow: 'hidden',
  },
  paymentDuration: {
    fontSize: 12,
    color: '#856404',
    fontWeight: '600',
  },
  networkWarning: {
    backgroundColor: '#fff3cd',
    padding: 12,
    borderRadius: 8,
    marginTop: 10,
  },
  networkWarningText: {
    color: '#856404',
  },
  mediaSection: {
    marginBottom: 20,
    padding: 16,
    backgroundColor: 'white',
    borderRadius: 12,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#1a1a1a',
    marginBottom: 12,
  },
  mediaPreview: {
    alignItems: 'center',
  },
  media: {
    width: '100%',
    height: 250,
    borderRadius: 12,
    marginBottom: 12,
  },
  clearButton: {
    backgroundColor: '#FF3B30',
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 8,
  },
  clearButtonText: {
    color: 'white',
    fontWeight: 'bold',
  },
  mediaPicker: {
    borderWidth: 2,
    borderColor: '#28a745',
    borderStyle: 'dashed',
    borderRadius: 12,
    padding: 20,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#f8fafc',
  },
  mediaPickerIcon: {
    fontSize: 48,
    marginBottom: 8,
  },
  mediaPickerText: {
    color: '#28a745',
    fontSize: 16,
    fontWeight: '700',
    textAlign: 'center',
    marginBottom: 4,
  },
  fileSizeHint: {
    fontSize: 12,
    color: '#6c757d',
    textAlign: 'center',
    marginTop: 4,
  },
  offlineHint: {
    color: '#dc3545',
    fontSize: 12,
    marginTop: 6,
  },
  descriptionSection: {
    marginBottom: 20,
    padding: 16,
    backgroundColor: 'white',
    borderRadius: 12,
  },
  textInput: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 10,
    padding: 16,
    fontSize: 16,
    backgroundColor: 'white',
    minHeight: 120,
    textAlignVertical: 'top',
  },
  charCount: {
    textAlign: 'right',
    fontSize: 12,
    color: '#95a5a6',
    marginTop: 6,
  },
  uploadButton: {
    backgroundColor: '#28a745',
    paddingVertical: 16,
    borderRadius: 12,
    alignItems: 'center',
    marginBottom: 20,
    marginHorizontal: 16,
  },
  uploadButtonDisabled: {
    backgroundColor: '#95a5a6',
  },
  uploadButtonText: {
    color: 'white',
    fontSize: 18,
    fontWeight: 'bold',
  },
  uploadingContainer: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  uploadingText: {
    color: 'white',
    fontSize: 16,
    marginLeft: 8,
  },
  instructions: {
    backgroundColor: '#e8f4fd',
    padding: 16,
    borderRadius: 12,
    marginHorizontal: 16,
    marginBottom: 20,
  },
  instructionsTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#007AFF',
    marginBottom: 8,
  },
  instruction: {
    fontSize: 14,
    color: '#2c3e50',
    marginBottom: 6,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 12,
    fontSize: 16,
    color: '#666',
  },
  mainHeader: {
    backgroundColor: 'white',
    paddingVertical: 20,
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  mainHeaderTitle: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#1a1a1a',
  },
  refreshButton: {
    backgroundColor: '#FF6B35',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 6,
  },
  refreshButtonText: {
    color: 'white',
    fontWeight: '600',
    fontSize: 12,
  },
  adsList: {
    padding: 16,
  },
  adCard: {
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 16,
    marginBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 2,
  },
  adHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  adTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  adDate: {
    fontSize: 12,
    color: '#95a5a6',
  },
  adDescription: {
    fontSize: 14,
    color: '#34495e',
    lineHeight: 20,
    marginBottom: 12,
  },
  adMediaPreview: {
    height: 150,
    borderRadius: 8,
    backgroundColor: '#f8f9fa',
    marginBottom: 12,
    overflow: 'hidden',
  },
  videoPlaceholder: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#e9ecef',
  },
  mediaTypeIcon: {
    fontSize: 32,
    marginBottom: 8,
  },
  mediaTypeText: {
    fontSize: 14,
    color: '#6c757d',
  },
  adImage: {
    width: '100%',
    height: '100%',
  },
  // NEW STYLES FOR STATISTICS SECTION
  adStatsSection: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 12,
    marginBottom: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: '#eee',
  },
  reactionStats: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 16,
  },
  statItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  statIcon: {
    fontSize: 16,
  },
  statText: {
    fontSize: 12,
    color: '#3498db',
    fontWeight: '500',
  },
  statIconWarning: {
    fontSize: 16,
  },
  statTextWarning: {
    fontSize: 12,
    color: '#e74c3c',
    fontWeight: '500',
  },
  adStatus: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  adStatusText: {
    fontSize: 11,
    fontWeight: '600',
  },
  deleteButton: {
    backgroundColor: '#FF6B35',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 6,
    alignSelf: 'flex-end',
  },
  deleteButtonText: {
    color: 'white',
    fontWeight: '600',
    fontSize: 12,
  },
  adNote: {
    fontSize: 12,
    color: '#856404',
    backgroundColor: '#fff8e1',
    padding: 10,
    borderRadius: 8,
    marginBottom: 12,
    lineHeight: 18,
  },
  renewButton: {
    backgroundColor: '#f0ad4e',
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 8,
    alignItems: 'center',
    marginBottom: 12,
  },
  renewButtonText: {
    color: 'white',
    fontWeight: 'bold',
    fontSize: 13,
  },
  payNowButton: {
    backgroundColor: '#FF6B35',
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 8,
    alignItems: 'center',
    marginBottom: 12,
  },
  payNowButtonText: {
    color: 'white',
    fontWeight: 'bold',
    fontSize: 13,
  },
  processingOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0, 0, 0, 0.45)',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 50,
    elevation: 50,
  },
  processingCard: {
    width: width * 0.8,
    backgroundColor: 'white',
    borderRadius: 16,
    padding: 24,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.25,
    shadowRadius: 12,
    elevation: 8,
  },
  processingTitle: {
    marginTop: 14,
    fontSize: 15,
    fontWeight: '700',
    color: '#1a1a1a',
    textAlign: 'center',
  },
  processingSubtext: {
    marginTop: 8,
    fontSize: 12,
    color: '#666',
    textAlign: 'center',
    lineHeight: 18,
  },
  emptyPosts: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  emptyPostsIcon: {
    fontSize: 64,
    marginBottom: 20,
    opacity: 0.5,
  },
  emptyPostsText: {
    fontSize: 18,
    color: '#666',
    textAlign: 'center',
    marginBottom: 8,
  },
  emptyPostsSubtext: {
    fontSize: 14,
    color: '#999',
    textAlign: 'center',
    marginBottom: 20,
  },
  createFirstButton: {
    backgroundColor: '#FF6B35',
    paddingVertical: 12,
    paddingHorizontal: 20,
    borderRadius: 8,
  },
  createFirstButtonText: {
    color: 'white',
    fontWeight: 'bold',
  },
});