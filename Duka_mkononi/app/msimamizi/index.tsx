import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as ImagePicker from 'expo-image-picker';
import * as Location from 'expo-location';
import { useRouter } from 'expo-router';
import React, { useEffect, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    Image,
    Modal,
    RefreshControl,
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
import { uploadToCloudinary } from '../../utils/cloudinary';
import { reverseGeocode } from '../../utils/location';
import { getCache, setCache } from '../../db/cache';
import { registerLive } from '../../lib/syncer';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';

// ✅ KUBADILISHWA: Tumia ngrok URL mpya
import { API_BASE_URL } from '../../constants/api';

// Aina zinazoruhusiwa za biashara (normalized keys — the same list as the
// AI-import page and the Laravel BUSINESS_TYPES, so the stored value stays
// consistent across web and mobile).
const BIZ_TYPES = [
  'spare_parts', 'motorcycle_spares', 'pharmacy', 'supermarket', 'clothing', 'electronics',
  'restaurant', 'hardware', 'cosmetics', 'perfume', 'mobile_accessories',
  'furniture', 'stationery', 'agriculture', 'construction_materials',
  'beauty_salon', 'barbershop', 'auto_repair', 'phone_shop', 'computer_shop',
  'general_retail', 'wholesale', 'other',
];

export default function MsimamiziHomeScreen() {
  const { t, lang } = useLang();
  const router = useRouter();
  const { signOut } = useSession();
  const [userData, setUserData] = useState({
    id: '',
    email: '',
    businessName: '',
    businessLocation: '',
    phone: '',
    role: '',
    businessLogo: '',
    businessLatitude: null as number | null,
    businessLongitude: null as number | null
  });
  const [logoUploading, setLogoUploading] = useState(false);
  const [locationTracking, setLocationTracking] = useState(false);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [sellersData, setSellersData] = useState<any[]>([]);
  const [isSystemAdmin, setIsSystemAdmin] = useState(false); // ✅ KUBADILISHWA: Ongeza state mpya
  
  // States for edit profile modal
  const [editModalVisible, setEditModalVisible] = useState(false);
  const [editFormData, setEditFormData] = useState({
    name: '',
    phone: '',
    businessName: '',
    businessLocation: '',
    businessType: '',
    businessDescription: ''
  });
  const [updatingProfile, setUpdatingProfile] = useState(false);
  const [updatingSellerStatus, setUpdatingSellerStatus] = useState<string | null>(null);
  const [deletingSellerId, setDeletingSellerId] = useState<string | null>(null);

  useEffect(() => {
    loadUserData();
  }, [lang]);

  useEffect(() => {
    if (!userData.businessName) return;
    const stop = registerLive<any[]>(
      'admin:users',
      async () => {
        const token = await AsyncStorage.getItem('userToken');
        if (!token) throw new Error('sync failed');
        // Business scoping comes from the verified JWT (business_id) on the
        // server. The name used to be sent as ?business=..., but that matched
        // users.business_name byte-for-byte, so sellers whose spelling differed
        // from their admin's were silently dropped. Do not re-add a name filter
        // here: AdminController::users() already returns only this admin's
        // business.
        const res = await fetchWithTimeout(
          `${API_BASE_URL}/api/admin/users?role=seller`,
          {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
            'ngrok-skip-browser-warning': 'true',
            'Accept': 'application/json'
          },
        });
        if (!res.ok) throw new Error('sync failed');
        const data = await res.json();
        const usersArray = data.users || [];
        return usersArray.filter((user: any) =>
          user.role === 'seller' && user.status !== 'deleted'
        );
      },
      (data) => { setSellersData(data); setLoading(false); }
    );
    return stop;
  }, [lang, userData.businessName]);

  const loadUserData = async () => {
    try {
      const userDataStr = await AsyncStorage.getItem('userData');
      const userToken = await AsyncStorage.getItem('userToken');
      
      if (!userToken) {
        Alert.alert(t('app.error'), t('admin_dashboard.error_auth'));
        await signOut();
        router.replace('/(tabs)');
        return;
      }

      if (userDataStr) {
        const user = JSON.parse(userDataStr);
        const businessName = user.businessName || user.business_name || t('admin_dashboard.business');
        const businessLocation = user.businessLocation || user.business_location || t('admin_dashboard.business_location');
        
        setUserData({
          id: user.id || '',
          email: user.email || '',
          businessName: businessName,
          businessLocation: businessLocation,
          phone: user.phone || t('admin_dashboard.no_phone'),
          role: user.role || '',
          businessLogo: user.business_logo_url || '',
          businessLatitude: typeof user.business_latitude === 'number' ? user.business_latitude : null,
          businessLongitude: typeof user.business_longitude === 'number' ? user.business_longitude : null
        });

        // ✅ KUBADILISHWA: Angalia kama user ni system admin
        const userEmail = user.email || '';
        const isAdmin = userEmail === "cosmavictorini1994@gmail.com";
        setIsSystemAdmin(isAdmin);
        console.log('👑 System Admin Check:', { email: userEmail, isSystemAdmin: isAdmin });

        // Set initial form data
        setEditFormData({
          name: user.full_name || t('admin_dashboard.profile_full_name'),
          phone: user.phone || '',
          businessName: businessName,
          businessLocation: businessLocation,
          businessType: user.business_type || '',
          businessDescription: user.business_description || ''
        });

        // ✅ KUBADILISHWA: Pita business name kwa loadSellersData
        await loadSellersData();

        // Pull the authoritative profile so the edit form always shows the
        // stored business type/description. Runs in the background (it only
        // feeds the edit modal) so it never delays the first paint.
        loadProfileIntoEditForm();
      }
    } catch (error) {
      console.error('Error loading user data:', error);
      const errorMessage = error instanceof Error ? error.message : t('admin_dashboard.error_update');
      Alert.alert(t('app.error'), errorMessage);
    } finally {
      setLoading(false);
    }
  };

  // ✅ KUBADILISHWA: Function ya kusimamia mfumo (system admin)
  const handleSystemAdmin = () => {
    console.log('🚀 Navigating to System Admin');
    router.push('/system_admin');
  };

  // Business scoping is derived server-side from the JWT's business_id, so no
  // business name is passed in and none is compared here. Comparing
  // user.business_name against the admin's own string was what hid sellers
  // whose spelling differed (e.g. "Shirima Spare Part" vs "Shirima spare part").
  const loadSellersData = async () => {
    try {
        const token = await AsyncStorage.getItem('userToken');
        if (!token) return;

        const cachedSellers = await getCache<any[]>('admin:users');
        if (cachedSellers) { setSellersData(cachedSellers); }

        const response = await fetchWithTimeout(
            `${API_BASE_URL}/api/admin/users?role=seller`,
            {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json',
                'ngrok-skip-browser-warning': 'true',
                'Accept': 'application/json'
            },
        });

        console.log('📊 Sellers response status:', response.status);

        if (response.ok) {
            const data = await response.json();
            const usersArray = data.users || [];

            const sellers = usersArray.filter((user: any) => {
                const isSeller = user.role === 'seller';
                const isNotDeleted = user.status !== 'deleted';
                return isSeller && isNotDeleted;
            });

            console.log('🛍️ Wauzaji waliofilter:', sellers.length);
            setSellersData(sellers);
            setCache('admin:users', sellers).catch(() => {});
        } else {
            const errorText = await response.text();
            console.error('❌ Error loading sellers:', errorText);
            Alert.alert(t('app.error'), t('admin_dashboard.error_sellers'));
        }
    } catch (error) {
        console.error('❌ Error loading sellers data:', error);
        const cachedSellers = await getCache<any[]>('admin:users');
        if (cachedSellers) { setSellersData(cachedSellers); return; }
        Alert.alert(t('app.error'), t('admin_dashboard.error_network'));
    }
  };

  // Pull the authoritative profile (includes business_type and
  // business_description saved from the AI-import page or this modal) so
  // the edit form always prefills the stored values, and keep the local
  // cache in sync for other pages.
  const loadProfileIntoEditForm = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;

      const res = await fetchWithTimeout(`${API_BASE_URL}/api/user/profile`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'ngrok-skip-browser-warning': 'true',
          'Accept': 'application/json'
        },
      });
      if (!res.ok) return;
      const profile = await res.json();
      if (!profile) return;

      setEditFormData((prev) => ({
        ...prev,
        businessType: profile.business_type || '',
        businessDescription: profile.business_description || ''
      }));

      const cached = await AsyncStorage.getItem('userData');
      if (cached) {
        const user = JSON.parse(cached);
        await AsyncStorage.setItem('userData', JSON.stringify({
          ...user,
          business_type: profile.business_type || '',
          business_description: profile.business_description || ''
        }));
      }
    } catch (error) {
      // Offline: the modal falls back to the cached values.
      console.error('Error loading profile into edit form:', error);
    }
  };

  const handleRefresh = async () => {
    setRefreshing(true);
    try {
      await loadSellersData();
    } catch (error) {
      console.error('Error refreshing data:', error);
      const errorMessage = error instanceof Error ? error.message : t('admin_dashboard.error_update');
      Alert.alert(t('app.error'), errorMessage);
    } finally {
      setRefreshing(false);
    }
  };

  // Edit Profile Functions
  const handleEditProfile = () => {
    setEditModalVisible(true);
  };

  const handleUpdateProfile = async () => {
    if (!editFormData.businessName.trim() || !editFormData.businessLocation.trim()) {
      Alert.alert(t('app.error'), t('admin_dashboard.business_placeholder'));
      return;
    }

    setUpdatingProfile(true);
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;

      if (!(await requireNetwork())) return;

      console.log('📡 Updating profile to:', `${API_BASE_URL}/api/user/profile`);
      console.log('📝 Data being sent:', editFormData);

      const response = await fetch(`${API_BASE_URL}/api/user/profile`, {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'ngrok-skip-browser-warning': 'true',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          full_name: editFormData.name,
          phone: editFormData.phone,
          business_name: editFormData.businessName,
          business_location: editFormData.businessLocation,
          business_type: (editFormData.businessType || '').trim(),
          business_description: (editFormData.businessDescription || '').trim()
        }),
      });

      if (response.ok) {
        const updatedData = await response.json();
        console.log('✅ Profile update successful:', updatedData);
        
        // Update user data in state
        setUserData(prev => ({
          ...prev,
          businessName: editFormData.businessName,
          businessLocation: editFormData.businessLocation,
          phone: editFormData.phone
        }));

        // Update AsyncStorage
        const currentUserData = await AsyncStorage.getItem('userData');
        if (currentUserData) {
          const user = JSON.parse(currentUserData);
          const updatedUserData = {
            ...user,
            businessName: editFormData.businessName,
            businessLocation: editFormData.businessLocation,
            phone: editFormData.phone,
            full_name: editFormData.name,
            business_type: (editFormData.businessType || '').trim(),
            business_description: (editFormData.businessDescription || '').trim()
          };
          await AsyncStorage.setItem('userData', JSON.stringify(updatedUserData));
        }

        setEditModalVisible(false);
        Alert.alert(t('app.success'), t('admin_dashboard.success_update'));
        
        // ✅ KUBADILISHWA: Reload wauzaji baada ya kubadilisha jina la biashara
        await loadSellersData();
      } else {
        const errorText = await response.text();
        console.error('❌ Profile update failed:', response.status, errorText);
        throw new Error(t('admin_dashboard.error_update'));
      }
    } catch (error) {
      console.error('❌ Error updating profile:', error);
      Alert.alert(t('app.error'), t('admin_dashboard.error_update'));
    } finally {
      setUpdatingProfile(false);
    }
  };

  // Seller Management Functions
  const handleApproveSeller = async (sellerId: string) => {
    try {
      setUpdatingSellerStatus(sellerId);
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        Alert.alert(t('app.error'), t('admin_dashboard.error_auth'));
        return;
      }

      if (!(await requireNetwork())) return;

      console.log('✅ Inathibitisha muuzaji:', sellerId);
      
      const response = await fetch(`${API_BASE_URL}/api/admin/users/${sellerId}/status`, {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'ngrok-skip-browser-warning': 'true',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ status: 'approved' }),
      });

      console.log('📊 Response status:', response.status);
      
      if (response.ok) {
        const result = await response.json();
        console.log('✅ Seller approval successful:', result);
        Alert.alert(t('app.success'), t('admin_dashboard.success_approve'));
        await loadSellersData();
      } else {
        const errorText = await response.text();
        console.error('❌ Server error:', errorText);
        throw new Error(t('admin_dashboard.error_approve'));
      }
    } catch (error) {
      console.error('❌ Error approving seller:', error);
      Alert.alert(t('app.error'), t('admin_dashboard.error_approve'));
    } finally {
      setUpdatingSellerStatus(null);
    }
  };

  const handleRejectSeller = async (sellerId: string) => {
    try {
      setUpdatingSellerStatus(sellerId);
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        Alert.alert(t('app.error'), t('admin_dashboard.error_auth'));
        return;
      }

      if (!(await requireNetwork())) return;

      console.log('❌ Inabatilisha muuzaji:', sellerId);
      
      const response = await fetch(`${API_BASE_URL}/api/admin/users/${sellerId}/status`, {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'ngrok-skip-browser-warning': 'true',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ status: 'rejected' }),
      });

      console.log('📊 Response status:', response.status);
      
      if (response.ok) {
        const result = await response.json();
        console.log('✅ Seller rejection successful:', result);
        Alert.alert(t('app.success'), t('admin_dashboard.success_reject'));
        await loadSellersData();
      } else {
        const errorText = await response.text();
        console.error('❌ Server error:', errorText);
        throw new Error(t('admin_dashboard.error_network'));
      }
    } catch (error) {
      console.error('❌ Error rejecting seller:', error);
      Alert.alert(t('app.error'), t('admin_dashboard.error_network'));
    } finally {
      setUpdatingSellerStatus(null);
    }
  };

  // ✅ KUBADILISHWA: Function ya kufuta muuzaji kabisa kutumia DELETE endpoint
  const handleDeleteSellerPermanently = async (sellerId: string, sellerName: string) => {
    Alert.alert(
      t('admin_dashboard.delete_permanent'),
      `${t('admin_dashboard.confirm_delete', { name: sellerName })}\n\n⚠️ ${t('admin_dashboard.error_network')}`,
      [
        { text: t('admin_dashboard.cancel'), style: 'cancel' },
        {
          text: t('admin_dashboard.delete_permanent'),
          style: 'destructive',
          onPress: async () => {
            try {
              setDeletingSellerId(sellerId);
              const token = await AsyncStorage.getItem('userToken');
              if (!token) {
                Alert.alert(t('app.error'), t('admin_dashboard.error_auth'));
                return;
              }

              if (!(await requireNetwork())) return;

              console.log('🗑️ Inafuta muuzaji kabisa:', sellerId);
              
              // Tumia DELETE endpoint mpya
              const response = await fetch(`${API_BASE_URL}/api/admin/users/${sellerId}`, {
                method: 'DELETE',
                headers: {
                  'Authorization': `Bearer ${token}`,
                  'Content-Type': 'application/json',
                  'ngrok-skip-browser-warning': 'true',
                  'Accept': 'application/json'
                },
              });

              console.log('📊 Delete response status:', response.status);
              
              if (response.ok) {
                const result = await response.json();
                console.log('✅ Seller permanently deleted:', result);
                
                let warningExtra = '';
                if (result.warnings?.hadSales || result.warnings?.hadProducts) {
                  warningExtra = '\n\n' + t('admin_dashboard.delete_warning_products');
                }
                
                Alert.alert(
                  t('app.success'),
                  `${t('admin_dashboard.delete_success', { name: sellerName })}${warningExtra}`,
                  [{ 
                    text: t('app.ok'), 
                    onPress: () => loadSellersData()
                  }]
                );
              } else {
                const errorText = await response.text();
                console.error('❌ Delete server error:', errorText);
                
                // Jaribu fallback kama DELETE endpoint haipo
                if (response.status === 404 || response.status === 405) {
                  console.log('⚠️ DELETE endpoint not available, using fallback');
                  await handleDeleteSellerFallback(sellerId, sellerName);
                } else {
                  throw new Error(`HTTP ${response.status}: ${errorText}`);
                }
              }
            } catch (error) {
              console.error('❌ Error deleting seller permanently:', error);
              Alert.alert(t('app.error'), t('admin_dashboard.error_network'));
            } finally {
              setDeletingSellerId(null);
            }
          }
        }
      ]
    );
  };

  // Fallback function kama DELETE endpoint haipo
  const handleDeleteSellerFallback = async (sellerId: string, sellerName: string) => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return;

      if (!(await requireNetwork())) return;

      console.log('🔄 Using fallback delete method for seller:', sellerId);
      
      // Fallback: Weka status kuwa 'inactive' kwa sasa
      const response = await fetch(`${API_BASE_URL}/api/admin/users/${sellerId}/status`, {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'ngrok-skip-browser-warning': 'true',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ status: 'inactive' }),
      });

      if (response.ok) {
        const result = await response.json();                console.log('✅ Fallback delete successful:', result);
        
        Alert.alert(
          t('app.success'),
          `${t('admin_dashboard.delete_fallback_success', { name: sellerName })}\n\n${t('admin_dashboard.delete_fallback_contact')}`,
          [{ 
            text: t('app.ok'), 
            onPress: () => loadSellersData()
          }]
        );
      } else {
        const errorText = await response.text();
        throw new Error(`Fallback failed: HTTP ${response.status}: ${errorText}`);
      }
    } catch (error) {
      console.error('Fallback error:', error);
      Alert.alert(t('app.error'), `${t('admin_dashboard.error_network')}\n${t('admin_dashboard.delete_fallback_contact')}`);
      throw error;
    }
  };

  // Function ya kumfuta muuzaji (kwa pending sellers)
  const handleDeleteSeller = async (sellerId: string, sellerName: string) => {
    Alert.alert(
      t('admin_dashboard.delete_confirm'),
      t('admin_dashboard.delete_confirm_text', { name: sellerName }),
      [
        { text: t('admin_dashboard.cancel'), style: 'cancel' },
        {
          text: t('admin_dashboard.delete'),
          style: 'destructive',
          onPress: async () => {
            try {
              const token = await AsyncStorage.getItem('userToken');
              if (!token) {
                Alert.alert(t('app.error'), t('admin_dashboard.error_auth'));
                return;
              }

              if (!(await requireNetwork())) return;

              console.log('🗑️ Inafuta muuzaji (inactive):', sellerId);
              
              // Weka status ya 'inactive'
              const response = await fetch(`${API_BASE_URL}/api/admin/users/${sellerId}/status`, {
                method: 'PUT',
                headers: {
                  'Authorization': `Bearer ${token}`,
                  'Content-Type': 'application/json',
                  'ngrok-skip-browser-warning': 'true',
                  'Accept': 'application/json'
                },
                body: JSON.stringify({ status: 'inactive' }),
              });

              console.log('📊 Delete response status:', response.status);
              
              if (response.ok) {
                const result = await response.json();
                console.log('✅ Seller deactivated:', result);
                Alert.alert(t('app.success'), t('admin_dashboard.success_reject'));
                await loadSellersData();
              } else {
                const errorText = await response.text();
                console.error('❌ Server error:', errorText);
                throw new Error(`HTTP ${response.status}: ${errorText}`);
              }
            } catch (error) {
              console.error('❌ Error deactivating seller:', error);
              Alert.alert(t('app.error'), t('admin_dashboard.error_network'));
            }
          }
        }
      ]
    );
  };

  // 🧭 TRACK / UPDATE LOCATION — captures the admin's current GPS pin so customers
  // can reach the shop with Twende Dukani. The button NEVER disappears: after the
  // first capture it becomes "Badili Eneo" so the location can be updated anytime.
  const handleTrackLocation = async () => {
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        Alert.alert(t('app.error'), t('admin_dashboard.track_location_permission'));
        return;
      }

      setLocationTracking(true);
      const position = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;

      // 🔍 Decode the real area name so we store the place, not just coordinates.
      const areaName = await reverseGeocode(lat, lng);
      setLocationTracking(false);

      // 🔒 Confirm before saving — prevents accidental captures.
      Alert.alert(
        t('admin_dashboard.location_confirm_title'),
        t('admin_dashboard.location_confirm_message', {
          area: areaName || `${lat.toFixed(5)}, ${lng.toFixed(5)}`,
        }),
        [
          { text: t('admin_dashboard.cancel'), style: 'cancel' },
          {
            text: t('admin_dashboard.location_confirm_yes'),
            onPress: () => saveBusinessLocation(lat, lng, areaName),
          },
        ]
      );
    } catch (error: any) {
      console.error('❌ Track location error:', error);
      setLocationTracking(false);
      Alert.alert(t('app.error'), error?.message || t('admin_dashboard.track_location_error'));
    }
  };

  // 💾 Saves the confirmed coordinates + decoded area name to the server.
  const saveBusinessLocation = async (lat: number, lng: number, areaName: string) => {
    setLocationTracking(true);
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        Alert.alert(t('app.error'), t('admin_dashboard.error_auth'));
        return;
      }

      if (!(await requireNetwork())) { setLocationTracking(false); return; }

      const response = await fetch(`${API_BASE_URL}/api/user/profile`, {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'ngrok-skip-browser-warning': 'true',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          business_latitude: lat,
          business_longitude: lng,
          ...(areaName ? { business_location: areaName } : {}),
        }),
      });

      if (!response.ok) {
        throw new Error(t('admin_dashboard.track_location_error'));
      }

      const updated = await response.json();

      setUserData((prev) => ({
        ...prev,
        businessLatitude: updated.user?.business_latitude ?? lat,
        businessLongitude: updated.user?.business_longitude ?? lng,
        businessLocation: areaName || prev.businessLocation,
      }));

      const cached = await AsyncStorage.getItem('userData');
      if (cached) {
        const user = JSON.parse(cached);
        await AsyncStorage.setItem('userData', JSON.stringify({
          ...user,
          business_latitude: updated.user?.business_latitude ?? lat,
          business_longitude: updated.user?.business_longitude ?? lng,
          ...(areaName ? { business_location: areaName } : {}),
        }));
      }

      Alert.alert(t('admin_dashboard.track_location_success_title'), t('admin_dashboard.track_location_success'));
    } catch (error: any) {
      console.error('❌ Save location error:', error);
      Alert.alert(t('app.error'), error?.message || t('admin_dashboard.track_location_error'));
    } finally {
      setLocationTracking(false);
    }
  };

  // 🖼️ Upload the business logo (picks → Cloudinary → saves URL to the server)
  const handleChangeLogo = async () => {
    try {
      const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!perm.granted) {
        Alert.alert(t('app.error'), t('admin_dashboard.logo_permission_denied'));
        return;
      }

      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ImagePicker.MediaTypeOptions.Images,
        quality: 0.8,
        allowsEditing: true,
        aspect: [1, 1],
      });

      if (result.canceled || !result.assets?.[0]) return;

      setLogoUploading(true);
      if (!(await requireNetwork())) { setLogoUploading(false); return; }
      const cloudinaryResult = await uploadToCloudinary(result.assets[0].uri, 'image');

      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        Alert.alert(t('app.error'), t('admin_dashboard.error_auth'));
        return;
      }

      const response = await fetch(`${API_BASE_URL}/api/user/profile`, {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'ngrok-skip-browser-warning': 'true',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ business_logo_url: cloudinaryResult.secure_url }),
      });

      if (!response.ok) {
        throw new Error(t('admin_dashboard.logo_error'));
      }

      const updated = await response.json();
      const newLogo = updated.user?.business_logo_url;

      setUserData((prev) => ({ ...prev, businessLogo: newLogo }));

      const cached = await AsyncStorage.getItem('userData');
      if (cached) {
        const user = JSON.parse(cached);
        await AsyncStorage.setItem('userData', JSON.stringify({ ...user, business_logo_url: newLogo }));
      }

      Alert.alert(t('admin_dashboard.logo_success_title'), t('admin_dashboard.logo_success'));
    } catch (error: any) {
      console.error('❌ Logo upload error:', error);
      Alert.alert(t('app.error'), error?.message || t('admin_dashboard.logo_error'));
    } finally {
      setLogoUploading(false);
    }
  };

  const hasLocation =
    typeof userData.businessLatitude === 'number' &&
    typeof userData.businessLongitude === 'number';

  const handleLogout = async () => {
    Alert.alert(
      t('admin_dashboard.confirm_logout'),
      t('admin_dashboard.confirm_logout_text'),
      [
        { text: t('admin_dashboard.cancel'), style: 'cancel' },
        {
          text: t('admin_dashboard.confirm_logout'),
          style: 'destructive',
          onPress: async () => {
            try {
              await signOut();
              router.replace('/(tabs)');
            } catch (error) {
              console.error('Error during logout:', error);
              Alert.alert(t('app.error'), t('admin_dashboard.confirm_logout_error'));
            }
          }
        }
      ]
    );
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.screenSafe} edges={['top']}>
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#3498db" />
          <Text style={styles.loadingText}>{t('admin_dashboard.loading')}</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.screenSafe} edges={['top']}>
    <ScrollView 
      style={styles.container} 
      contentContainerStyle={styles.scrollContent}
      showsVerticalScrollIndicator={false}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          onRefresh={handleRefresh}
          colors={['#3498db']}
          tintColor="#3498db"
        />
      }
    >
      {/* Header */}
      <View style={styles.header}>
        <View style={styles.headerLeft}>
          <View style={styles.titleContainer}>
            <Text style={styles.title} numberOfLines={1}>{t('admin_dashboard.title')}</Text>
            <Text style={styles.subtitle} numberOfLines={1}>{userData.email}</Text>
          </View>
          
          {/* ✅ KUBADILISHWA: Ongeza button ya System Admin kama user ni admin */}
          {isSystemAdmin && (
            <TouchableOpacity 
              onPress={handleSystemAdmin} 
              style={styles.systemAdminButton}
            >
              <Ionicons name="settings" size={16} color="#9b59b6" />
              <Text style={styles.systemAdminText}>{t('admin_dashboard.manage_system')}</Text>
            </TouchableOpacity>
          )}
        </View>
        
        <View style={styles.headerRight}>
          <View style={styles.iconButtonsRow}>
            <TouchableOpacity onPress={handleRefresh} style={styles.iconButton}>
              <Ionicons name="refresh" size={22} color="#3498db" />
            </TouchableOpacity>
            <TouchableOpacity onPress={handleEditProfile} style={styles.iconButton}>
              <Ionicons name="create-outline" size={22} color="#3498db" />
            </TouchableOpacity>
          </View>
          
          <TouchableOpacity style={styles.userInfo} onPress={handleLogout}>
            <Ionicons name="person-circle" size={34} color="#3498db" />
            <Text style={styles.logoutText}>{t('admin_dashboard.logout')}</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Business Card */}
      <View style={styles.businessCard}>
        <View style={styles.businessCardHeader}>
          {userData.businessLogo ? (
            <Image source={{ uri: userData.businessLogo }} style={styles.businessLogo} />
          ) : (
            <View style={[styles.businessLogo, styles.businessLogoPlaceholder]}>
              <Ionicons name="storefront-outline" size={30} color="#3498db" />
            </View>
          )}
          <View style={styles.businessCardInfo}>
            <Text style={styles.businessName}>{userData.businessName}</Text>
            <Text style={styles.businessLocation}>{userData.businessLocation}</Text>
            <Text style={styles.businessInfo}>{userData.phone}</Text>
          </View>
          <TouchableOpacity style={styles.logoUploadButton} onPress={handleChangeLogo} disabled={logoUploading}>
            {logoUploading ? (
              <ActivityIndicator size="small" color="#3498db" />
            ) : (
              <Ionicons name="camera" size={18} color="#3498db" />
            )}
          </TouchableOpacity>
        </View>
        <View style={styles.statusIndicator}>
          <Ionicons name="shield-checkmark" size={14} color="#2ecc71" />
          <Text style={styles.statusText}>
            {isSystemAdmin ? t('admin_dashboard.system_admin') : t('admin_dashboard.admin')}
          </Text>
        </View>
        <View style={styles.filterInfo}>
          <Ionicons name="funnel-outline" size={12} color="#3498db" />
          <Text style={styles.filterText}>
            {t('admin_dashboard.filter_text', { name: userData.businessName })}
          </Text>
        </View>

        {/* 🧭 Track / Update Location — always visible. Becomes "Badili Eneo" once set. */}
        <TouchableOpacity
          style={styles.trackLocationButton}
          onPress={handleTrackLocation}
          disabled={locationTracking}
          activeOpacity={0.85}
        >
          {locationTracking ? (
            <>
              <ActivityIndicator size="small" color="white" />
              <Text style={styles.trackLocationButtonText}>{t('admin_dashboard.track_location_loading')}</Text>
            </>
          ) : (
            <>
              <Ionicons name={hasLocation ? 'refresh' : 'navigate'} size={16} color="white" />
              <Text style={styles.trackLocationButtonText}>
                {hasLocation ? t('admin_dashboard.change_location') : t('admin_dashboard.track_location')}
              </Text>
            </>
          )}
        </TouchableOpacity>
      </View>

      {/* Sellers List Section */}
      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <Text style={styles.sectionTitle}>
            {t('admin_dashboard.sellers')}
          </Text>
          <View style={styles.sellerCountBadge}>
            <Text style={styles.sellerCountText}>{sellersData.length}</Text>
          </View>
        </View>
        
        {sellersData.length > 0 ? (
          sellersData.map((seller) => (
            <View key={seller.id} style={styles.sellerCard}>
              <View style={styles.sellerInfo}>
                <View style={styles.sellerHeader}>
                  <View style={styles.sellerNameRow}>
                    {seller.business_logo_url ? (
                      <Image source={{ uri: seller.business_logo_url }} style={styles.sellerAvatar} />
                    ) : (
                      <View style={[styles.sellerAvatar, styles.sellerAvatarPlaceholder]}>
                        <Ionicons name="person" size={16} color="#3498db" />
                      </View>
                    )}
                    <Text style={styles.sellerName}>{seller.full_name || seller.email}</Text>
                  </View>
                  <View style={styles.statusContainer}>
                    {seller.status === 'approved' ? (
                      <View style={[styles.statusBadge, styles.approvedBadge]}>
                        <Ionicons name="checkmark-circle" size={12} color="#2ecc71" />
                        <Text style={styles.approvedText}>{t('admin_dashboard.approved')}</Text>
                      </View>
                    ) : seller.status === 'pending' ? (
                      <View style={[styles.statusBadge, styles.pendingBadge]}>
                        <Ionicons name="time-outline" size={12} color="#f39c12" />
                        <Text style={styles.pendingText}>{t('admin_dashboard.pending')}</Text>
                      </View>
                    ) : seller.status === 'rejected' ? (
                      <View style={[styles.statusBadge, styles.rejectedBadge]}>
                        <Ionicons name="close-circle" size={12} color="#e74c3c" />
                        <Text style={styles.rejectedText}>{t('admin_dashboard.rejected')}</Text>
                      </View>
                    ) : seller.status === 'inactive' ? (
                      <View style={[styles.statusBadge, styles.inactiveBadge]}>
                        <Ionicons name="eye-off-outline" size={12} color="#95a5a6" />
                        <Text style={styles.inactiveText}>{t('admin_dashboard.inactive')}</Text>
                      </View>
                    ) : (
                      <View style={[styles.statusBadge, styles.otherBadge]}>
                        <Ionicons name="help-circle-outline" size={12} color="#95a5a6" />
                        <Text style={styles.otherText}>{seller.status || t('admin_dashboard.status_unknown')}</Text>
                      </View>
                    )}
                  </View>
                </View>
                <Text style={styles.sellerEmail}>{seller.email}</Text>
                <Text style={styles.sellerPhone}>{seller.phone || t('admin_dashboard.no_phone')}</Text>
                <Text style={styles.sellerBusiness}>
                  {seller.business_name || t('admin_dashboard.business')} - {seller.business_location || t('admin_dashboard.business_location')}
                </Text>
              </View>
              
              <View style={styles.sellerActions}>
                {seller.status === 'approved' ? (
                  <TouchableOpacity 
                    style={[styles.actionButton, styles.rejectButton]}
                    onPress={() => handleRejectSeller(seller.id)}
                    disabled={updatingSellerStatus === seller.id}
                  >
                    {updatingSellerStatus === seller.id ? (
                      <ActivityIndicator size="small" color="white" />
                    ) : (
                      <>
                        <Ionicons name="close-circle" size={14} color="white" />
                        <Text style={styles.rejectButtonText}>{t('admin_dashboard.reject')}</Text>
                      </>
                    )}
                  </TouchableOpacity>
                ) : seller.status === 'pending' ? (
                  <>
                    <TouchableOpacity 
                      style={[styles.actionButton, styles.approveButton]}
                      onPress={() => handleApproveSeller(seller.id)}
                      disabled={updatingSellerStatus === seller.id}
                    >
                      {updatingSellerStatus === seller.id ? (
                        <ActivityIndicator size="small" color="white" />
                      ) : (
                        <>
                          <Ionicons name="checkmark-circle" size={14} color="white" />
                          <Text style={styles.approveButtonText}>{t('admin_dashboard.approve')}</Text>
                        </>
                      )}
                    </TouchableOpacity>
                    <TouchableOpacity 
                      style={[styles.actionButton, styles.deleteButton]}
                      onPress={() => handleDeleteSeller(seller.id, seller.full_name || seller.email)}
                      disabled={updatingSellerStatus === seller.id}
                    >
                      <Ionicons name="trash-outline" size={14} color="white" />
                      <Text style={styles.deleteButtonText}>{t('admin_dashboard.delete')}</Text>
                    </TouchableOpacity>
                  </>
                ) : seller.status === 'rejected' ? (
                  // ✅ KUBADILISHWA: Sasa kuna button mbili kwa rejected sellers
                  <>
                    <TouchableOpacity 
                      style={[styles.actionButton, styles.approveButton]}
                      onPress={() => handleApproveSeller(seller.id)}
                      disabled={updatingSellerStatus === seller.id}
                    >
                      {updatingSellerStatus === seller.id ? (
                        <ActivityIndicator size="small" color="white" />
                      ) : (
                        <>
                          <Ionicons name="refresh-outline" size={14} color="white" />
                          <Text style={styles.approveButtonText}>{t('admin_dashboard.restore')}</Text>
                        </>
                      )}
                    </TouchableOpacity>
                    <TouchableOpacity 
                      style={[styles.actionButton, styles.deletePermanentButton]}
                      onPress={() => handleDeleteSellerPermanently(seller.id, seller.full_name || seller.email)}
                      disabled={deletingSellerId === seller.id}
                    >
                      {deletingSellerId === seller.id ? (
                        <ActivityIndicator size="small" color="white" />
                      ) : (
                        <>
                          <Ionicons name="trash" size={14} color="white" />
                          <Text style={styles.deletePermanentButtonText}>{t('admin_dashboard.delete_permanent')}</Text>
                        </>
                      )}
                    </TouchableOpacity>
                  </>
                ) : seller.status === 'inactive' ? (
                  <TouchableOpacity 
                    style={[styles.actionButton, styles.deletePermanentButton]}
                    onPress={() => handleDeleteSellerPermanently(seller.id, seller.full_name || seller.email)}
                    disabled={deletingSellerId === seller.id}
                  >
                    {deletingSellerId === seller.id ? (
                      <ActivityIndicator size="small" color="white" />
                    ) : (
                      <>
                        <Ionicons name="trash" size={14} color="white" />
                        <Text style={styles.deletePermanentButtonText}>{t('admin_dashboard.delete_permanent')}</Text>
                      </>
                    )}
                  </TouchableOpacity>
                ) : null}
              </View>
            </View>
          ))
        ) : (
          <View style={styles.noSellers}>
            <Ionicons name="people-outline" size={48} color="#bdc3c7" />
            <Text style={styles.noSellersTitle}>{t('admin_dashboard.no_sellers')}</Text>
            <Text style={styles.noSellersText}>
              {t('admin_dashboard.no_sellers_text')}
            </Text>
          </View>
        )}
      </View>

      {/* Edit Profile Modal */}
      <Modal
        animationType="slide"
        transparent={true}
        visible={editModalVisible}
        onRequestClose={() => setEditModalVisible(false)}
      >
        <View style={styles.modalContainer}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>{t('admin_dashboard.edit_profile')}</Text>
              <TouchableOpacity 
                onPress={() => setEditModalVisible(false)}
                style={styles.closeButton}
              >
                <Ionicons name="close" size={22} color="#7f8c8d" />
              </TouchableOpacity>
            </View>
            
            <ScrollView
              style={styles.modalScroll}
              showsVerticalScrollIndicator={false}
              keyboardShouldPersistTaps="handled"
            >
            <View style={styles.inputGroup}>
              <Text style={styles.inputLabel}>{t('admin_dashboard.profile_full_name')}</Text>
              <TextInput
                style={styles.input}
                placeholder={t('admin_dashboard.profile_full_name')}
                value={editFormData.name}
                onChangeText={(text) => setEditFormData(prev => ({ ...prev, name: text }))}
              />
            </View>
            
            <View style={styles.inputGroup}>
              <Text style={styles.inputLabel}>{t('admin_dashboard.profile_phone')}</Text>
              <TextInput
                style={styles.input}
                placeholder={t('admin_dashboard.profile_phone')}
                value={editFormData.phone}
                onChangeText={(text) => setEditFormData(prev => ({ ...prev, phone: text }))}
                keyboardType="phone-pad"
              />
            </View>
            
            <View style={styles.inputGroup}>
              <Text style={styles.inputLabel}>{t('admin_dashboard.profile_business_name')}</Text>
              <TextInput
                style={styles.input}
                placeholder={t('admin_dashboard.profile_business_name')}
                value={editFormData.businessName}
                onChangeText={(text) => setEditFormData(prev => ({ ...prev, businessName: text }))}
              />
              <Text style={styles.inputHelper}>
                {t('admin_dashboard.profile_helper')}
              </Text>
            </View>
            
            <View style={styles.inputGroup}>
              <Text style={styles.inputLabel}>{t('admin_dashboard.profile_business_location')}</Text>
              <TextInput
                style={styles.input}
                placeholder={t('admin_dashboard.profile_business_location')}
                value={editFormData.businessLocation}
                onChangeText={(text) => setEditFormData(prev => ({ ...prev, businessLocation: text }))}
              />
            </View>

            {/* Aina ya Biashara — same option list as the AI-import page. */}
            <View style={styles.inputGroup}>
              <Text style={styles.inputLabel}>{t('profile.business_type_label')}</Text>
              <View style={styles.bizTypeWrap}>
                {BIZ_TYPES.map((btype) => (
                  <TouchableOpacity
                    key={btype}
                    style={[
                      styles.bizTypeChip,
                      editFormData.businessType === btype && styles.bizTypeChipActive,
                    ]}
                    onPress={() => setEditFormData(prev => ({ ...prev, businessType: btype }))}
                  >
                    <Text style={[
                      styles.bizTypeText,
                      editFormData.businessType === btype && styles.bizTypeTextActive,
                    ]}>
                      {t('business_types.' + btype)}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>
            </View>

            {/* Maelezo mafupi ya Biashara — feeds the AI import feature. */}
            <View style={styles.inputGroup}>
              <Text style={styles.inputLabel}>{t('profile.business_description_label')}</Text>
              <TextInput
                style={[styles.input, styles.descriptionTextArea]}
                placeholder={t('profile.business_description_placeholder')}
                value={editFormData.businessDescription}
                onChangeText={(text) => setEditFormData(prev => ({ ...prev, businessDescription: text }))}
                multiline
                numberOfLines={3}
                textAlignVertical="top"
              />
              <Text style={styles.descriptionHint}>{t('profile.business_description_hint')}</Text>
            </View>
            </ScrollView>

            <View style={styles.modalButtons}>
              <TouchableOpacity 
                style={[styles.modalButton, styles.cancelButton]} 
                onPress={() => setEditModalVisible(false)}
                disabled={updatingProfile}
              >
                <Text style={styles.cancelButtonText}>{t('admin_dashboard.modal_cancel')}</Text>
              </TouchableOpacity>
              <TouchableOpacity 
                style={[styles.modalButton, styles.saveButton]} 
                onPress={handleUpdateProfile}
                disabled={updatingProfile}
              >
                {updatingProfile ? (
                  <ActivityIndicator size="small" color="white" />
                ) : (
                  <Text style={styles.saveButtonText}>{t('admin_dashboard.modal_save')}</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
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
    paddingHorizontal: 14,
  },
  scrollContent: {
    flexGrow: 1,
    paddingBottom: 40,
  },
  center: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 15,
    color: '#666',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 18,
    paddingTop: 8,
  },
  headerLeft: {
    flex: 1,
    flexDirection: 'column',
    marginRight: 12,
  },
  titleContainer: {
    marginBottom: 8,
  },
  title: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#2c3e50',
    lineHeight: 24,
  },
  subtitle: {
    fontSize: 12,
    color: '#7f8c8d',
    marginTop: 2,
    lineHeight: 16,
  },
  headerRight: {
    alignItems: 'flex-end',
  },
  iconButtonsRow: {
    flexDirection: 'row',
    marginBottom: 6,
  },
  iconButton: {
    padding: 6,
    marginLeft: 8,
  },
  userInfo: {
    alignItems: 'center',
  },
  logoutText: {
    fontSize: 9,
    color: '#e74c3c',
    marginTop: 1,
    fontWeight: '500',
  },
  // ✅ KUBADILISHWA: Ongeza styles kwa system admin button
  systemAdminButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f3e8ff',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 6,
    borderWidth: 1,
    borderColor: '#d8b4fe',
    alignSelf: 'flex-start',
  },
  systemAdminText: {
    fontSize: 11,
    color: '#9b59b6',
    marginLeft: 4,
    fontWeight: '600',
  },
  businessCard: {
    backgroundColor: 'white',
    padding: 16,
    borderRadius: 12,
    marginBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.08,
    shadowRadius: 4,
    elevation: 2,
  },
  businessCardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
  },
  businessCardInfo: {
    flex: 1,
    marginLeft: 12,
  },
  businessLogo: {
    width: 56,
    height: 56,
    borderRadius: 12,
    backgroundColor: '#e8f4fd',
  },
  businessLogoPlaceholder: {
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#d0e8f7',
    borderStyle: 'dashed',
  },
  logoUploadButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: '#e8f4fd',
    justifyContent: 'center',
    alignItems: 'center',
  },
  businessName: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 2,
  },
  businessLocation: {
    fontSize: 13,
    color: '#7f8c8d',
    marginBottom: 2,
  },
  businessInfo: {
    fontSize: 13,
    color: '#95a5a6',
    marginBottom: 8,
  },
  statusIndicator: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 6,
    paddingHorizontal: 10,
    backgroundColor: '#e8f6ef',
    borderRadius: 6,
    alignSelf: 'flex-start',
    marginBottom: 8,
  },
  trackLocationButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#e67e22',
    paddingVertical: 11,
    paddingHorizontal: 12,
    borderRadius: 10,
    marginTop: 10,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.15,
    shadowRadius: 4,
    elevation: 3,
  },
  trackLocationButtonText: {
    color: 'white',
    fontWeight: 'bold',
    fontSize: 14,
    marginLeft: 7,
    letterSpacing: 0.3,
  },
  filterInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 6,
    paddingHorizontal: 10,
    backgroundColor: '#e3f2fd',
    borderRadius: 6,
    borderWidth: 1,
    borderColor: '#bbdefb',
  },
  filterText: {
    fontSize: 11,
    color: '#1976d2',
    marginLeft: 5,
    fontWeight: '500',
    flexShrink: 1,
    lineHeight: 14,
  },
  statusText: {
    fontSize: 11,
    color: '#27ae60',
    marginLeft: 5,
    fontWeight: '500',
  },
  section: {
    marginBottom: 24,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  sellerCountBadge: {
    backgroundColor: '#3498db',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
    minWidth: 26,
    alignItems: 'center',
  },
  sellerCountText: {
    fontSize: 12,
    fontWeight: 'bold',
    color: 'white',
  },
  // Seller Card Styles
  sellerCard: {
    backgroundColor: 'white',
    padding: 14,
    borderRadius: 10,
    marginBottom: 10,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 3,
    elevation: 1,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  sellerInfo: {
    flex: 1,
    marginRight: 12,
  },
  sellerHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 4,
  },
  sellerNameRow: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
    marginRight: 8,
  },
  sellerAvatar: {
    width: 30,
    height: 30,
    borderRadius: 15,
    marginRight: 8,
    backgroundColor: '#e8f4fd',
  },
  sellerAvatarPlaceholder: {
    justifyContent: 'center',
    alignItems: 'center',
  },
  sellerName: {
    fontSize: 15,
    fontWeight: 'bold',
    color: '#2c3e50',
    flex: 1,
    lineHeight: 18,
  },
  sellerEmail: {
    fontSize: 13,
    color: '#7f8c8d',
    marginBottom: 2,
  },
  sellerPhone: {
    fontSize: 13,
    color: '#7f8c8d',
    marginBottom: 2,
  },
  sellerBusiness: {
    fontSize: 11,
    color: '#95a5a6',
    marginTop: 4,
    lineHeight: 14,
  },
  statusContainer: {
    alignSelf: 'flex-start',
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 6,
    paddingVertical: 3,
    borderRadius: 10,
  },
  approvedBadge: {
    backgroundColor: '#e8f6ef',
  },
  pendingBadge: {
    backgroundColor: '#fff4e6',
  },
  rejectedBadge: {
    backgroundColor: '#fdedec',
  },
  inactiveBadge: {
    backgroundColor: '#f5f5f5',
  },
  otherBadge: {
    backgroundColor: '#f0f0f0',
  },
  approvedText: {
    fontSize: 10,
    color: '#27ae60',
    marginLeft: 3,
    fontWeight: '500',
  },
  pendingText: {
    fontSize: 10,
    color: '#f39c12',
    marginLeft: 3,
    fontWeight: '500',
  },
  rejectedText: {
    fontSize: 10,
    color: '#e74c3c',
    marginLeft: 3,
    fontWeight: '500',
  },
  inactiveText: {
    fontSize: 10,
    color: '#95a5a6',
    marginLeft: 3,
    fontWeight: '500',
  },
  otherText: {
    fontSize: 10,
    color: '#7f8c8d',
    marginLeft: 3,
    fontWeight: '500',
  },
  sellerActions: {
    flexDirection: 'column',
    gap: 6,
    minWidth: 90,
  },
  actionButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 7,
    borderRadius: 6,
    gap: 4,
    justifyContent: 'center',
  },
  approveButton: {
    backgroundColor: '#27ae60',
  },
  rejectButton: {
    backgroundColor: '#e74c3c',
  },
  deleteButton: {
    backgroundColor: '#95a5a6',
  },
  deletePermanentButton: {
    backgroundColor: '#c0392b',
  },
  approveButtonText: {
    color: 'white',
    fontSize: 11,
    fontWeight: '500',
  },
  rejectButtonText: {
    color: 'white',
    fontSize: 11,
    fontWeight: '500',
  },
  deleteButtonText: {
    color: 'white',
    fontSize: 11,
    fontWeight: '500',
  },
  deletePermanentButtonText: {
    color: 'white',
    fontSize: 11,
    fontWeight: '600',
  },
  noSellers: {
    alignItems: 'center',
    justifyContent: 'center',
    padding: 32,
    backgroundColor: 'white',
    borderRadius: 10,
  },
  noSellersTitle: {
    fontSize: 17,
    color: '#2c3e50',
    marginTop: 10,
    fontWeight: 'bold',
  },
  noSellersText: {
    fontSize: 13,
    color: '#95a5a6',
    marginTop: 6,
    textAlign: 'center',
    lineHeight: 18,
  },
  // Modal Styles
  modalContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: 'rgba(0,0,0,0.5)',
    padding: 16,
  },
  modalContent: {
    backgroundColor: 'white',
    padding: 20,
    borderRadius: 14,
    width: '100%',
    maxWidth: 380,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 16,
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  closeButton: {
    padding: 4,
  },
  inputGroup: {
    marginBottom: 14,
  },
  inputLabel: {
    fontSize: 13,
    fontWeight: '600',
    color: '#2c3e50',
    marginBottom: 5,
  },
  inputHelper: {
    fontSize: 10,
    color: '#f39c12',
    marginTop: 3,
    fontStyle: 'italic',
    lineHeight: 13,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ddd',
    padding: 10,
    borderRadius: 6,
    fontSize: 15,
    backgroundColor: '#f8f9fa',
  },
  modalScroll: {
    maxHeight: '70%',
  },
  bizTypeWrap: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  bizTypeChip: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#ddd',
    backgroundColor: 'white',
  },
  bizTypeChipActive: {
    backgroundColor: '#2ecc71',
    borderColor: '#27ae60',
  },
  bizTypeText: {
    fontSize: 13,
    color: '#7f8c8d',
  },
  bizTypeTextActive: {
    color: 'white',
    fontWeight: 'bold',
  },
  descriptionTextArea: {
    minHeight: 90,
  },
  descriptionHint: {
    fontSize: 12,
    color: '#95a5a6',
    fontStyle: 'italic',
    marginTop: 6,
  },
  modalButtons: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 20,
    gap: 10,
  },
  modalButton: {
    flex: 1,
    padding: 12,
    borderRadius: 6,
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
    fontSize: 15,
    fontWeight: '600',
  },
  saveButtonText: {
    color: 'white',
    fontSize: 15,
    fontWeight: '600',
  },
});