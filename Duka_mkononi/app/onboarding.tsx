import { Ionicons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import * as Location from 'expo-location';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Dimensions,
  Image,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLang } from '../context/LanguageContext';
import { API_BASE_URL } from '../constants/api';
import { uploadToCloudinary } from '../utils/cloudinary';
import { reverseGeocode } from '../utils/location';
import { requireNetwork, fetchWithTimeout } from '../lib/network';

const { width } = Dimensions.get('window');

// 🔑 Backend role → login screen role param
const loginRoleFor = (backendRole: string): string => {
  switch (backendRole) {
    case 'admin': return 'msimamizi';
    case 'seller': return 'muuzaji';
    case 'customer': return 'mteja';
    default: return 'mteja';
  }
};

// 🎨 Accent per role
const accentFor = (backendRole: string): string => {
  switch (backendRole) {
    case 'admin': return '#e74c3c';
    case 'seller': return '#2ecc71';
    default: return '#3498db';
  }
};

const iconFor = (backendRole: string): 'storefront' | 'cart' | 'person' => {
  switch (backendRole) {
    case 'admin': return 'storefront';
    case 'seller': return 'cart';
    default: return 'person';
  }
};

export default function OnboardingScreen() {
  const router = useRouter();
  const params = useLocalSearchParams<{
    email?: string;
    role?: string;
    setupToken?: string;
    lang?: string;
    business_location?: string;
    business_name?: string;
  }>();
  const { t } = useLang();

  const role = params.role || 'customer';
  const setupToken = params.setupToken || '';
  const isAdmin = role === 'admin';

  // Slides: photo first, then (admins only) the Google Map location slide.
  const slides = isAdmin ? ['photo', 'location'] : ['photo'];
  const accent = accentFor(role);
  const icon = iconFor(role);

  const scrollRef = useRef<ScrollView>(null);
  const [currentSlide, setCurrentSlide] = useState(0);

  // Photo slide state
  const [photoUri, setPhotoUri] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);
  const [photoSaved, setPhotoSaved] = useState(false);

  // Location slide state
  const [locating, setLocating] = useState(false);
  const [locationSaved, setLocationSaved] = useState(false);
  const [capturedCoords, setCapturedCoords] = useState<{ lat: number; lng: number } | null>(null);

  const goToLogin = () => {
    router.replace({
      pathname: '/login',
      params: { role: loginRoleFor(role), lang: params.lang || 'sw' },
    } as any);
  };

  const goToNextSlide = () => {
    if (currentSlide < slides.length - 1) {
      scrollRef.current?.scrollTo({ x: (currentSlide + 1) * width, animated: true });
      setCurrentSlide(currentSlide + 1);
    } else {
      goToLogin();
    }
  };

  const handleMomentumScrollEnd = (e: any) => {
    const index = Math.round(e.nativeEvent.contentOffset.x / width);
    setCurrentSlide(Math.max(0, Math.min(slides.length - 1, index)));
  };

  const saveProfilePhoto = async (secureUrl: string) => {
    if (!(await requireNetwork())) return;

    const response = await fetchWithTimeout(`${API_BASE_URL}/api/onboarding/profile`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${setupToken}`,
      },
      body: JSON.stringify({ business_logo_url: secureUrl }),
    });
    if (!response.ok) {
      const data = await response.json().catch(() => ({}));
      throw new Error(data?.error || t('onboarding.photo_save_error'));
    }
    return response.json();
  };

  const pickPhoto = async () => {
    Alert.alert(
      t('onboarding.photo_choose_title'),
      t('onboarding.photo_choose_message'),
      [
        { text: t('onboarding.photo_camera'), onPress: () => launchPicker(true) },
        { text: t('onboarding.photo_gallery'), onPress: () => launchPicker(false) },
        { text: t('app.cancel'), style: 'cancel' },
      ]
    );
  };

  const launchPicker = async (useCamera: boolean) => {
    try {
      if (useCamera) {
        const perm = await ImagePicker.requestCameraPermissionsAsync();
        if (!perm.granted) {
          Alert.alert(t('app.error'), t('onboarding.photo_permission_denied'));
          return;
        }
      } else {
        const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
        if (!perm.granted) {
          Alert.alert(t('app.error'), t('onboarding.photo_permission_denied'));
          return;
        }
      }

      const result = useCamera
        ? await ImagePicker.launchCameraAsync({
            mediaTypes: ImagePicker.MediaTypeOptions.Images,
            quality: 0.8,
            allowsEditing: true,
            aspect: [1, 1],
          })
        : await ImagePicker.launchImageLibraryAsync({
            mediaTypes: ImagePicker.MediaTypeOptions.Images,
            quality: 0.8,
            allowsEditing: true,
            aspect: [1, 1],
          });

      if (result.canceled || !result.assets?.[0]) return;

      setPhotoUri(result.assets[0].uri);
      await uploadPhoto(result.assets[0].uri);
    } catch (error: any) {
      console.error('❌ Photo pick error:', error);
      Alert.alert(t('app.error'), error?.message || t('onboarding.photo_upload_error'));
    }
  };

  const uploadPhoto = async (uri: string) => {
    if (!setupToken) {
      // No token edge case: still let the user finish (photo won't persist).
      setPhotoSaved(true);
      Alert.alert(t('app.info'), t('onboarding.photo_save_error'));
      goToNextSlide();
      return;
    }

    setUploading(true);
    try {
      const cloudinaryResult = await uploadToCloudinary(uri, 'image');
      await saveProfilePhoto(cloudinaryResult.secure_url);
      setPhotoSaved(true);
      Alert.alert(t('onboarding.photo_success_title'), t('onboarding.photo_success_message'));
      // Auto-advance to the next slide (admins) or finish (others)
      goToNextSlide();
    } catch (error: any) {
      console.error('❌ Photo upload error:', error);
      Alert.alert(t('app.error'), error?.message || t('onboarding.photo_upload_error'));
    } finally {
      setUploading(false);
    }
  };

  const captureLocation = async () => {
    try {
      setLocating(true);

      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        Alert.alert(t('onboarding.location_permission_denied_title'), t('onboarding.location_permission_denied'));
        return;
      }

      const position = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;
      setCapturedCoords({ lat, lng });

      // 🔍 Decode the real area name so we store the place, not just coordinates.
      const areaName = await reverseGeocode(lat, lng);
      setLocating(false);

      // 🔒 Confirm before saving — prevents accidental captures.
      Alert.alert(
        t('onboarding.location_confirm_title'),
        t('onboarding.location_confirm_message', {
          area: areaName || `${lat.toFixed(5)}, ${lng.toFixed(5)}`,
        }),
        [
          { text: t('app.cancel'), style: 'cancel' },
          {
            text: t('onboarding.location_confirm_yes'),
            onPress: () => saveOnboardingLocation(lat, lng, areaName),
          },
        ]
      );
    } catch (error: any) {
      console.error('❌ Location error:', error);
      setLocating(false);
      Alert.alert(t('app.error'), error?.message || t('onboarding.location_error'));
    }
  };

  // 💾 Saves the confirmed coordinates + decoded area name to the server.
  const saveOnboardingLocation = async (lat: number, lng: number, areaName: string) => {
    if (!(await requireNetwork())) return;

    setLocating(true);
    try {
      if (!setupToken) {
        setLocationSaved(true);
        Alert.alert(t('app.info'), t('onboarding.location_save_error'));
        goToLogin();
        return;
      }

      const response = await fetchWithTimeout(`${API_BASE_URL}/api/onboarding/profile`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': `Bearer ${setupToken}`,
        },
        body: JSON.stringify({
          business_location: areaName || params.business_location || null,
          business_latitude: lat,
          business_longitude: lng,
        }),
      });

      if (!response.ok) {
        const data = await response.json().catch(() => ({}));
        throw new Error(data?.error || t('onboarding.location_save_error'));
      }

      setLocationSaved(true);
      Alert.alert(t('onboarding.location_success_title'), t('onboarding.location_success_message'));
      goToLogin();
    } catch (error: any) {
      console.error('❌ Location error:', error);
      Alert.alert(t('app.error'), error?.message || t('onboarding.location_error'));
    } finally {
      setLocating(false);
    }
  };

  const handleSkipPhoto = () => {
    goToNextSlide();
  };

  const handleSkipLocation = () => {
    goToLogin();
  };

  return (
    <SafeAreaView style={styles.safeArea}>
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        {/* Progress dots */}
        <View style={styles.dotsRow}>
          {slides.map((_, index) => (
            <View
              key={index}
              style={[
                styles.dot,
                index === currentSlide && [styles.dotActive, { backgroundColor: accent }],
              ]}
            />
          ))}
        </View>

        <ScrollView
          ref={scrollRef}
          horizontal
          pagingEnabled
          showsHorizontalScrollIndicator={false}
          onMomentumScrollEnd={handleMomentumScrollEnd}
          style={styles.slides}
        >
          {/* ─────────── SLIDE 1: PROFILE PHOTO ─────────── */}
          <View style={[styles.slide, { width }]}>
            <View style={[styles.iconCircle, { backgroundColor: `${accent}1a` }]}>
              <Ionicons name={icon} size={44} color={accent} />
            </View>

            <Text style={styles.title}>{t('onboarding.photo_title')}</Text>
            <Text style={styles.subtitle}>{t('onboarding.photo_subtitle')}</Text>

            {/* Preview */}
            <View style={styles.avatarWrap}>
              {photoUri ? (
                <Image source={{ uri: photoUri }} style={styles.avatarImage} />
              ) : (
                <View style={[styles.avatarPlaceholder, { backgroundColor: `${accent}1a` }]}>
                  <Ionicons name="camera-outline" size={46} color={accent} />
                </View>
              )}
              {uploading && (
                <View style={styles.avatarOverlay}>
                  <ActivityIndicator size="large" color="#ffffff" />
                </View>
              )}
              {photoSaved && !uploading && (
                <View style={[styles.avatarOverlay, { backgroundColor: 'rgba(39,174,96,0.75)' }]}>
                  <Ionicons name="checkmark-circle" size={44} color="#ffffff" />
                </View>
              )}
            </View>

            {/* Choose photo */}
            <TouchableOpacity
              style={[styles.primaryButton, { backgroundColor: accent }]}
              onPress={pickPhoto}
              disabled={uploading}
              activeOpacity={0.8}
            >
              {uploading ? (
                <>
                  <ActivityIndicator size="small" color="white" />
                  <Text style={styles.primaryButtonText}>{t('onboarding.photo_uploading')}</Text>
                </>
              ) : (
                <>
                  <Ionicons name="cloud-upload-outline" size={20} color="white" />
                  <Text style={styles.primaryButtonText}>
                    {photoUri ? t('onboarding.photo_change') : t('onboarding.photo_upload')}
                  </Text>
                </>
              )}
            </TouchableOpacity>

            {/* Skip */}
            <TouchableOpacity style={styles.skipButton} onPress={handleSkipPhoto} disabled={uploading}>
              <Text style={[styles.skipButtonText, { color: accent }]}>{t('onboarding.skip')}</Text>
            </TouchableOpacity>

            <Text style={styles.swipeHint}>
              {slides.length > 1 ? t('onboarding.swipe_hint') : ''}
            </Text>
          </View>

          {/* ─────────── SLIDE 2 (ADMIN): GOOGLE MAP LOCATION ─────────── */}
          {isAdmin && (
            <View style={[styles.slide, { width }]}>
              <View style={[styles.iconCircle, { backgroundColor: `${accent}1a` }]}>
                <Ionicons name="location" size={44} color={accent} />
              </View>

              <Text style={styles.title}>{t('onboarding.location_title')}</Text>
              <Text style={styles.subtitle}>{t('onboarding.location_subtitle')}</Text>

              {/* Map preview */}
              <View style={styles.mapPlaceholder}>
                <Ionicons name="map-outline" size={54} color="#95a5a6" />
                <Text style={styles.mapPlaceholderText}>
                  {capturedCoords
                    ? `${capturedCoords.lat.toFixed(5)}, ${capturedCoords.lng.toFixed(5)}`
                    : t('onboarding.location_placeholder')}
                </Text>
              </View>

              {/* Capture location */}
              <TouchableOpacity
                style={[styles.primaryButton, { backgroundColor: accent }]}
                onPress={captureLocation}
                disabled={locating}
                activeOpacity={0.8}
              >
                {locating ? (
                  <>
                    <ActivityIndicator size="small" color="white" />
                    <Text style={styles.primaryButtonText}>{t('onboarding.location_saving')}</Text>
                  </>
                ) : locationSaved ? (
                  <>
                    <Ionicons name="checkmark-circle-outline" size={20} color="white" />
                    <Text style={styles.primaryButtonText}>{t('onboarding.location_saved')}</Text>
                  </>
                ) : (
                  <>
                    <Ionicons name="navigate-outline" size={20} color="white" />
                    <Text style={styles.primaryButtonText}>{t('onboarding.location_button')}</Text>
                  </>
                )}
              </TouchableOpacity>

              {/* Skip */}
              <TouchableOpacity style={styles.skipButton} onPress={handleSkipLocation} disabled={locating}>
                <Text style={[styles.skipButtonText, { color: accent }]}>{t('onboarding.skip')}</Text>
              </TouchableOpacity>

              <Text style={styles.locationNote}>{t('onboarding.location_note')}</Text>
            </View>
          )}
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
  flex: {
    flex: 1,
  },
  dotsRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    paddingTop: 18,
    paddingBottom: 6,
    gap: 8,
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#d5dbe1',
  },
  dotActive: {
    width: 24,
  },
  slides: {
    flex: 1,
  },
  slide: {
    flex: 1,
    paddingHorizontal: 28,
    paddingTop: 12,
    alignItems: 'center',
  },
  iconCircle: {
    width: 96,
    height: 96,
    borderRadius: 48,
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 24,
    marginBottom: 20,
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#2c3e50',
    textAlign: 'center',
    marginBottom: 10,
    lineHeight: 30,
  },
  subtitle: {
    fontSize: 15,
    color: '#7f8c8d',
    textAlign: 'center',
    lineHeight: 22,
    marginBottom: 28,
    paddingHorizontal: 8,
  },
  avatarWrap: {
    width: 150,
    height: 150,
    borderRadius: 75,
    marginBottom: 30,
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.12,
    shadowRadius: 14,
    elevation: 6,
  },
  avatarImage: {
    width: 150,
    height: 150,
    borderRadius: 75,
  },
  avatarPlaceholder: {
    width: 150,
    height: 150,
    borderRadius: 75,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 2,
    borderColor: '#e3e8ee',
    borderStyle: 'dashed',
  },
  avatarOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    borderRadius: 75,
    backgroundColor: 'rgba(0,0,0,0.4)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  primaryButton: {
    width: '100%',
    height: 56,
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: 14,
    marginBottom: 14,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.15,
    shadowRadius: 8,
    elevation: 4,
  },
  primaryButtonText: {
    fontSize: 16,
    fontWeight: 'bold',
    color: 'white',
    marginLeft: 8,
    letterSpacing: 0.3,
  },
  skipButton: {
    paddingVertical: 10,
    paddingHorizontal: 24,
  },
  skipButtonText: {
    fontSize: 15,
    fontWeight: '600',
    textDecorationLine: 'underline',
  },
  swipeHint: {
    fontSize: 12,
    color: '#bdc3c7',
    marginTop: 10,
  },
  mapPlaceholder: {
    width: '100%',
    height: 180,
    borderRadius: 16,
    backgroundColor: '#f0f4f8',
    borderWidth: 1,
    borderColor: '#e3e8ee',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 28,
  },
  mapPlaceholderText: {
    fontSize: 13,
    color: '#7f8c8d',
    marginTop: 8,
    fontFamily: Platform.OS === 'ios' ? 'Menlo' : 'monospace',
  },
  locationNote: {
    fontSize: 12,
    color: '#95a5a6',
    textAlign: 'center',
    marginTop: 12,
    lineHeight: 17,
    paddingHorizontal: 16,
  },
});
