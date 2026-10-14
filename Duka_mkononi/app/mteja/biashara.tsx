import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import React, { useCallback, useEffect, useState } from 'react';
import {
    Alert,
    Linking,
    Platform,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View
} from 'react-native';
import SmartCartLoader from '../../components/SmartCartLoader';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLang } from '../../context/LanguageContext';
import LogoutButton from '../../components/logout-button';
import ZoomableImage from '../../components/zoomable-image';

// 🔥 UBADILISHO MUHIMU: Tumia URL yako ya ngrok
import { API_BASE_URL } from '../../constants/api';
import { getCache, setCache } from '../../db/cache';
import { registerLive, syncNow } from '../../lib/syncer';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';

interface Business {
  // NB: business/user ids are UUID STRINGS (Blade makes the same point in
  // biashara.blade.php's bindCardEvents()). Typing this as `number` was wrong.
  id: string;
  email: string;
  role: string;
  full_name: string;
  phone: string;
  business_name: string;
  business_location: string;
  business_logo_url?: string | null;
  business_latitude?: number | null;
  business_longitude?: number | null;
  status: string;
}

/**
 * Capability #12 - trust summary payload (public GET /api/ai/trust/summary).
 * Everything shown here is either a verified platform record (facts) or a
 * published-review statistic/opinion (opinions) - the split mirrors the
 * server contract so the UI can never present an opinion as a fact.
 */
interface TrustReview {
  body: string;
  rating: number | null;
  display_name: string | null;
  published_at: string | null;
}

interface TrustSummaryPayload {
  success: boolean;
  summary: { text: string; source: string };
  facts: string[];
  opinions: string[];
  sources: string[];
  limitations: string[];
  ai: { available: boolean; degraded: boolean; reason?: string | null };
  reviews: {
    scope: string;
    sample_size: number;
    rating_average: number | null;
    items: TrustReview[];
  };
}

export default function BiasharaScreen() {
  const { t, lang } = useLang();
  const [searchQuery, setSearchQuery] = useState('');
  const [businesses, setBusinesses] = useState<Business[]>([]);
  const [loading, setLoading] = useState(true);

  // Capability #12 - one open trust panel at a time, per-business cache.
  const [trustOpen, setTrustOpen] = useState<string | null>(null);
  const [trustData, setTrustData] = useState<Record<string, TrustSummaryPayload>>({});
  const [trustLoading, setTrustLoading] = useState<string | null>(null);
  const [trustError, setTrustError] = useState<string | null>(null);

  const toggleTrust = useCallback(
    async (business: Business) => {
      const id = business.id;
      if (trustOpen === id) {
        setTrustOpen(null);
        return;
      }
      setTrustOpen(id);
      setTrustError(null);
      if (trustData[id]) return; // already fetched; cached payload is version-bumped server-side

      setTrustLoading(id);
      try {
        const res = await fetchWithTimeout(
          `${API_BASE_URL}/api/ai/trust/summary?business_id=${encodeURIComponent(id)}&locale=${lang}`
        );
        let json: (TrustSummaryPayload & { error?: string }) | null = null;
        try {
          json = (await res.json()) as TrustSummaryPayload & { error?: string };
        } catch {
          json = null;
        }
        if (!res.ok || !json || json.success !== true) {
          setTrustError(json?.error || t('mteja_trust.error_text'));
          setTrustOpen(null);
          return;
        }
        setTrustData((prev) => ({ ...prev, [id]: json as TrustSummaryPayload }));
      } catch {
        setTrustError(t('mteja_trust.error_text'));
        setTrustOpen(null);
      } finally {
        setTrustLoading(null);
      }
    },
    [lang, t, trustData, trustOpen]
  );

  const stars = (rating: number | null): string => {
    if (!rating || rating < 1) return '';
    return '★'.repeat(Math.min(5, Math.round(rating)));
  };

  useEffect(() => {
    fetchBusinesses();
  }, [lang]);

  useEffect(() => {
    const stop = registerLive<any>(
      'businesses',
      async () => {
        const res = await fetchWithTimeout(`${API_BASE_URL}/api/businesses`);
        if (!res.ok) throw new Error('sync failed');
        return await res.json();
      },
      (data) => { setBusinesses(data); setLoading(false); }
    );
    return stop;
  }, [lang]);

  // 🔥 FUNCTION MPYA: TUMIA ENDPOINT YA /api/businesses
  const fetchBusinesses = async () => {
    try {
      setLoading(true);
      console.log('🔍 Inatafuta biashara...');
      console.log('📍 URL:', `${API_BASE_URL}/api/businesses`);
      
      const cached = await getCache<any>('businesses');
      if (cached) { setBusinesses(cached); setLoading(false); }
      
      const response = await fetchWithTimeout(`${API_BASE_URL}/api/businesses`);
      
      if (!response.ok) {
        const errorText = await response.text();
        console.error('❌ Server error:', errorText);
        
        // Iwapo endpoint haipo, jaribu kutumia /api/admin/users (labda na token)
        if (response.status === 404) {
          throw new Error('Endpoint ya biashara haipo kwenye server. Tafadhali hakikisha server ina endpoint ya /api/businesses');
        }
        
        throw new Error(`Server error: ${response.status}`);
      }

      const businessesData = await response.json();
      console.log('🏢 Biashara zilizopatikana:', businessesData?.length || 0);
      
      if (!businessesData || businessesData.length === 0) {
        console.log('ℹ️ Hakuna biashara zilizopatikana.');
        Alert.alert(
          t('customer_dashboard.no_businesses'),
          t('customer_dashboard.no_businesses_registered'),
          [{ text: t('app.ok') }]
        );
      }
      
      setBusinesses(businessesData || []);
      setCache('businesses', businessesData || []).catch(() => {});

    } catch (error: any) {
      console.error('❌ Hitilafu:', error);
      const cached = await getCache<any>('businesses');
      if (cached) { setBusinesses(cached); setLoading(false); return; }
      Alert.alert(
        t('app.error'), 
        `${t('customer_dashboard.error_fetch')}\n\n${error.message}\n\n${t('customer_dashboard.contact_admin')}`,
        [
          { text: t('app.ok') },
          { text: t('customer_dashboard.retry'), onPress: fetchBusinesses }
        ]
      );
    } finally {
      setLoading(false);
    }
  };

  // 🔥 REAL PHONE CALL FUNCTION
  const makePhoneCall = (phoneNumber: string) => {
    if (!phoneNumber || phoneNumber === 'null' || phoneNumber === 'undefined' || phoneNumber === 'Haijajazwa') {
      Alert.alert(t('app.info'), t('customer_dashboard.no_phone'));
      return;
    }
    
    const cleanPhone = phoneNumber.replace(/[\s\-\(\)]/g, '');
    
    // Format namba kwa Tanzania
    let formattedPhone = cleanPhone;
    if (!cleanPhone.startsWith('+255') && !cleanPhone.startsWith('255')) {
      if (cleanPhone.length === 9) {
        formattedPhone = `255${cleanPhone}`;
      } else if (cleanPhone.length === 10 && cleanPhone.startsWith('0')) {
        formattedPhone = `255${cleanPhone.substring(1)}`;
      }
    }
    
    const phoneURL = `tel:${formattedPhone}`;
    
    Linking.canOpenURL(phoneURL)
      .then((supported) => {
        if (supported) {
          Linking.openURL(phoneURL)
            .then(() => console.log('📞 Phone call initiated:', formattedPhone))
            .catch((err) => {
              console.error('Error opening phone dialer:', err);
              Alert.alert(t('app.error'), `Error: ${err.message}`);
            });
        } else {
          Alert.alert(t('app.error'), t('customer_dashboard.no_phone'));
        }
      })
      .catch((err) => {
        console.error('Error checking phone call support:', err);
        Alert.alert(t('app.error'), t('customer_dashboard.no_phone'));
      });
  };

  // 🔥 REAL SMS FUNCTION
  const sendSMS = (phoneNumber: string, businessName: string) => {
    if (!phoneNumber || phoneNumber === 'null' || phoneNumber === 'undefined' || phoneNumber === 'Haijajazwa') {
      Alert.alert(t('app.info'), t('customer_dashboard.no_phone'));
      return;
    }
    
    const cleanPhone = phoneNumber.replace(/[\s\-\(\)]/g, '');
    
    // Format namba
    let formattedPhone = cleanPhone;
    if (!cleanPhone.startsWith('+255') && !cleanPhone.startsWith('255')) {
      if (cleanPhone.length === 9) {
        formattedPhone = `255${cleanPhone}`;
      } else if (cleanPhone.length === 10 && cleanPhone.startsWith('0')) {
        formattedPhone = `255${cleanPhone.substring(1)}`;
      }
    }
    
    let smsURL;
    // Same message body the Blade page sends (biashara.blade.php sendSMS()):
    // "Habari <business>, naomba kufahamu zaidi kuhusu huduma zako."
    const message = t('customer_dashboard.sms_message').replace('{name}', businessName);
    
    if (Platform.OS === 'ios') {
      smsURL = `sms:${formattedPhone}&body=${encodeURIComponent(message)}`;
    } else {
      smsURL = `sms:${formattedPhone}?body=${encodeURIComponent(message)}`;
    }

    Linking.canOpenURL(smsURL)
      .then((supported) => {
        if (supported) {
          Linking.openURL(smsURL)
            .then(() => console.log('💬 SMS app opened'))
            .catch((err) => {
              console.error('Error opening SMS app:', err);
              Alert.alert(t('app.error'), `Error: ${err.message}`);
            });
        } else {
          Alert.alert(t('app.error'), t('customer_dashboard.no_phone'));
        }
      })
      .catch((err) => {
        console.error('Error checking SMS support:', err);
        Alert.alert(t('app.error'), `Error: ${err.message}`);
      });
  };

  // 🧭 TWENDE DUKANI — track the customer's live location and open Google Maps
  // directions to the business (GPS pin if set, otherwise search by address).
  const handleTwendeDukani = async (business: Business) => {
    const hasCoords =
      typeof business.business_latitude === 'number' &&
      typeof business.business_longitude === 'number';
    const hasAddress = !!business.business_location && business.business_location !== 'null';

    if (!hasCoords && !hasAddress) {
      Alert.alert(t('app.info'), t('customer_dashboard.twende_no_location'));
      return;
    }

    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') {
        Alert.alert(t('app.info'), t('customer_dashboard.twende_location_permission'));
        return;
      }

      Alert.alert(t('customer_dashboard.twende_finding_title'), t('customer_dashboard.twende_finding_message'));

      const position = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
      const origin = `${position.coords.latitude},${position.coords.longitude}`;

      let destination: string;
      if (hasCoords) {
        destination = `${business.business_latitude},${business.business_longitude}`;
      } else {
        destination = encodeURIComponent(business.business_location as string);
      }

      const mapsUrl = `https://www.google.com/maps/dir/?api=1&origin=${origin}&destination=${destination}&travelmode=driving`;
      console.log('🧭 Opening Google Maps directions:', mapsUrl);

      const supported = await Linking.canOpenURL(mapsUrl);
      if (!supported) {
        Alert.alert(t('app.error'), t('customer_dashboard.twende_open_error'));
        return;
      }
      await Linking.openURL(mapsUrl);
    } catch (error: any) {
      console.error('❌ Twende Dukani error:', error);
      Alert.alert(t('app.error'), t('customer_dashboard.twende_location_failed'));
    }
  };

  // 🔥 ORDER FUNCTION FOR BUSINESSES
  const handleWasiliana = (business: Business) => {
    if (!business.phone || business.phone === 'null' || business.phone === 'undefined' || business.phone === 'Haijajazwa') {
      Alert.alert(t('app.info'), t('customer_dashboard.no_phone'));
      return;
    }

    const bizName = business.business_name || '';
    Alert.alert(
      `${t('customer_dashboard.contact')} ${bizName}`,
      `${t('customer_dashboard.choose_method')} ${bizName}:`,
      [
        { text: t('app.cancel'), style: 'cancel' },
        { 
          text: `📞 ${t('customer_dashboard.call')}`, 
          onPress: () => makePhoneCall(business.phone)
        },
        { 
          text: `💬 ${t('customer_dashboard.sms')}`, 
          onPress: () => sendSMS(business.phone, bizName || t('customer_dashboard.business'))
        }
      ]
    );
  };

  // Filter businesses kwa utafutaji
  const filteredBusinesses = businesses.filter(business =>
    (business.business_name && business.business_name.toLowerCase().includes(searchQuery.toLowerCase())) ||
    (business.business_location && business.business_location.toLowerCase().includes(searchQuery.toLowerCase())) ||
    (business.full_name && business.full_name.toLowerCase().includes(searchQuery.toLowerCase())) ||
    (business.phone && business.phone.includes(searchQuery)) ||
    (business.email && business.email.toLowerCase().includes(searchQuery.toLowerCase()))
  );

  if (loading) {
    return (
      <SafeAreaView style={styles.screenSafe} edges={['top']}>
        <View style={styles.center}>
          <SmartCartLoader size="large" color="#3498db" />
          <Text style={styles.loadingText}>{t('customer_dashboard.loading_businesses')}</Text>
        </View>
      </SafeAreaView>
    );
  }

  const renderTrust = (businessId: string) => {
    if (trustLoading === businessId) {
      return (
        <View style={styles.trustPanel}>
          <SmartCartLoader size="compact" color="#667eea" label={t('mteja_trust.loading')} />
          <Text style={styles.trustMuted}>{t('mteja_trust.loading')}</Text>
        </View>
      );
    }

    if (trustError) {
      return (
        <View style={styles.trustPanel}>
          <Text style={styles.trustError}>{trustError}</Text>
        </View>
      );
    }

    const data = trustData[businessId];
    if (!data) return null;

    return (
      <View style={styles.trustPanel}>
        <Text style={styles.trustAnswer}>{data.summary?.text || ''}</Text>
        {data.ai?.degraded && data.ai?.reason ? (
          <Text style={styles.trustMuted}>{data.ai.reason}</Text>
        ) : null}

        {data.facts?.length ? (
          <View style={styles.trustBlock}>
            <Text style={styles.trustLabel}>{t('mteja_trust.facts_label')}</Text>
            {data.facts.map((line, index) => (
              <Text key={`f${index}`} style={styles.trustLine}>• {line}</Text>
            ))}
          </View>
        ) : null}

        {data.opinions?.length ? (
          <View style={styles.trustBlock}>
            <Text style={styles.trustLabel}>{t('mteja_trust.opinions_label')}</Text>
            {data.opinions.map((line, index) => (
              <Text key={`o${index}`} style={styles.trustLine}>• {line}</Text>
            ))}
          </View>
        ) : null}

        {data.reviews?.items?.length ? (
          <View style={styles.trustBlock}>
            <Text style={styles.trustLabel}>
              {t('mteja_trust.reviews_label')} ({data.reviews.sample_size})
            </Text>
            {data.reviews.items.map((review, index) => (
              <View key={`r${index}`} style={styles.trustReview}>
                <View style={styles.trustReviewHead}>
                  <Text style={styles.trustReviewer}>
                    {review.display_name || t('mteja_trust.anonymous')}
                  </Text>
                  <Text style={styles.trustStars}>{stars(review.rating)}</Text>
                  <Text style={styles.trustDate}>
                    {String(review.published_at || '').slice(0, 10)}
                  </Text>
                </View>
                <Text style={styles.trustBody}>{review.body}</Text>
              </View>
            ))}
          </View>
        ) : null}

        <View style={styles.trustBlock}>
          {(data.limitations || []).map((line, index) => (
            <Text key={`l${index}`} style={styles.trustLimit}>{line}</Text>
          ))}
        </View>
      </View>
    );
  };

  return (
    <SafeAreaView style={styles.screenSafe} edges={['top']}>
    <View style={styles.container}>
      {/* Logout */}
      <View style={styles.topBar}>
        <LogoutButton iconOnly />
      </View>
      {/* Search Bar */}
      <View style={styles.searchContainer}>
        <TextInput
          style={styles.searchInput}
          placeholder={t('customer_dashboard.search_placeholder')}
          value={searchQuery}
          onChangeText={setSearchQuery}
          placeholderTextColor="#95a5a6"
        />
      </View>

      {/* Orodha ya Biashara */}
      <ScrollView style={styles.scrollView} showsVerticalScrollIndicator={false}>
        <Text style={styles.sectionTitle}>
          {t('customer_dashboard.registered_businesses')} ({filteredBusinesses.length})
        </Text>

        {filteredBusinesses.length === 0 ? (
          <View style={styles.emptyContainer}>
            <Text style={styles.emptyTitle}>
              {searchQuery ? t('customer_dashboard.no_businesses_found') : t('customer_dashboard.no_businesses')}
            </Text>
            <Text style={styles.emptyText}>
              {searchQuery 
                ? `${t('customer_dashboard.search_results')}: "${searchQuery}".\n${t('customer_dashboard.try_different')}`
                : `${t('customer_dashboard.no_businesses_registered')}`
              }
            </Text>
            <TouchableOpacity style={styles.refreshButton} onPress={fetchBusinesses}>
              <Text style={styles.refreshText}>{t('customer_dashboard.refresh')}</Text>
            </TouchableOpacity>
          </View>
        ) : (
          filteredBusinesses.map(business => (
            <View key={business.id} style={styles.businessCard}>
              {/* Header with Logo, Business Name and Contact Button */}
              <View style={styles.cardHeader}>
                {business.business_logo_url ? (
                  <ZoomableImage
                    uri={business.business_logo_url}
                    style={styles.businessLogo}
                    name={business.business_name}
                  />
                ) : (
                  <View style={[styles.businessLogo, styles.businessLogoPlaceholder]}>
                    <Ionicons name="storefront-outline" size={22} color="#3498db" />
                  </View>
                )}
                <Text style={styles.businessName}>
                  {business.business_name || t('customer_dashboard.unnamed_business')}
                </Text>
                
                {/* WASILIANA BUTTON */}
                <TouchableOpacity 
                  style={styles.contactButton}
                  onPress={() => handleWasiliana(business)}
                >
                  <Text style={styles.contactButtonText}>{t('customer_dashboard.contact')}</Text>
                </TouchableOpacity>
              </View>

              {/* 🧭 TWENDE DUKANI BUTTON */}
              <TouchableOpacity
                style={styles.twendeButton}
                onPress={() => handleTwendeDukani(business)}
                activeOpacity={0.85}
              >
                <Ionicons name="navigate" size={16} color="white" />
                <Text style={styles.twendeButtonText}>{t('customer_dashboard.twende_dukani')}</Text>
              </TouchableOpacity>

              {/* Maelezo ya Biashara */}
              <View style={styles.detailsContainer}>
                <View style={styles.detailRow}>
                  <Text style={styles.detailLabel}>{t('customer_dashboard.manager_name')}</Text>
                  <Text style={styles.detailValue}>
                    {business.full_name || t('customer_dashboard.not_provided')}
                  </Text>
                </View>

                <View style={styles.detailRow}>
                  <Text style={styles.detailLabel}>{t('customer_dashboard.location')}</Text>
                  <Text style={styles.detailValue}>
                    {business.business_location || t('customer_dashboard.not_provided')}
                  </Text>
                </View>

                <View style={styles.detailRow}>
                  <Text style={styles.detailLabel}>{t('customer_dashboard.phone')}</Text>
                  <TouchableOpacity onPress={() => makePhoneCall(business.phone)}>
                    <Text style={[styles.detailValue, styles.phoneLink]}>
                      {business.phone || t('customer_dashboard.not_provided')}
                    </Text>
                  </TouchableOpacity>
                </View>

                <View style={styles.detailRow}>
                  <Text style={styles.detailLabel}>{t('customer_dashboard.email')}</Text>
                  <Text style={styles.detailValue}>
                    {business.email || t('customer_dashboard.not_provided')}
                  </Text>
                </View>

                {/* Status */}
                <View style={styles.detailRow}>
                  <Text style={styles.detailLabel}>{t('customer_dashboard.status')}</Text>
                  <Text style={[
                    styles.detailValue,
                    styles.approvedText
                  ]}>
                    ✅ {t('customer_dashboard.verified')}
                  </Text>
                </View>
              </View>

              {/* Capability #12 - trust summary (verified facts vs customer opinions) */}
              <TouchableOpacity
                style={styles.trustBtn}
                onPress={() => toggleTrust(business)}
                activeOpacity={0.85}
                disabled={trustLoading === business.id}
              >
                <Ionicons name="sparkles" size={15} color="#667eea" />
                <Text style={styles.trustBtnText}>{t('mteja_trust.btn_trust')}</Text>
              </TouchableOpacity>
              {trustOpen === business.id ? renderTrust(business.id) : null}
            </View>
          ))
        )}
      </ScrollView>
    </View>
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
    padding: 16,
  },
  center: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 10,
    fontSize: 16,
    color: '#7f8c8d',
  },
  searchContainer: {
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  topBar: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    paddingHorizontal: 16,
    marginBottom: 8,
  },
  searchInput: {
    height: 50,
    backgroundColor: 'white',
    borderRadius: 12,
    paddingHorizontal: 16,
    fontSize: 16,
    borderWidth: 1,
    borderColor: '#ddd',
  },
  scrollView: {
    flex: 1,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 16,
    textAlign: 'center',
  },
  businessCard: {
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 16,
    marginBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
    gap: 10,
  },
  businessLogo: {
    width: 44,
    height: 44,
    borderRadius: 10,
    backgroundColor: '#e8f4fd',
  },
  businessLogoPlaceholder: {
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#d0e8f7',
    borderStyle: 'dashed',
  },
  businessName: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
    flex: 1,
  },
  twendeButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#27ae60',
    paddingVertical: 10,
    borderRadius: 10,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.15,
    shadowRadius: 4,
    elevation: 3,
  },
  twendeButtonText: {
    color: 'white',
    fontWeight: 'bold',
    fontSize: 14,
    marginLeft: 6,
    letterSpacing: 0.3,
  },
  contactButton: {
    backgroundColor: '#3498db',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  contactButtonText: {
    color: 'white',
    fontWeight: 'bold',
    fontSize: 12,
  },
  detailsContainer: {
    gap: 10,
  },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 4,
  },
  detailLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#34495e',
    flex: 1,
  },
  detailValue: {
    fontSize: 14,
    color: '#7f8c8d',
    flex: 1,
    textAlign: 'right',
  },
  phoneLink: {
    color: '#3498db',
    fontWeight: '500',
    textDecorationLine: 'underline',
  },
  approvedText: {
    color: '#27ae60',
    fontWeight: 'bold',
  },
  trustBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 7,
    marginTop: 12,
    paddingVertical: 10,
    borderRadius: 10,
    backgroundColor: '#eef1ff',
    borderWidth: 1,
    borderColor: '#d6ddff',
  },
  trustBtnText: {
    color: '#5566d4',
    fontWeight: '800',
    fontSize: 13.5,
  },
  trustPanel: {
    marginTop: 10,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: '#f1f3f8',
  },
  trustAnswer: {
    fontSize: 14,
    color: '#34495e',
    lineHeight: 20,
    backgroundColor: '#f8f9fa',
    borderRadius: 10,
    padding: 11,
    borderWidth: 1,
    borderColor: '#ecf0f1',
    marginBottom: 4,
  },
  trustBlock: { marginTop: 10 },
  trustLabel: {
    fontSize: 10.5,
    fontWeight: '800',
    color: '#95a5a6',
    letterSpacing: 0.6,
    textTransform: 'uppercase',
    marginBottom: 5,
  },
  trustLine: { fontSize: 13, color: '#34495e', lineHeight: 19, marginBottom: 2 },
  trustMuted: { fontSize: 12.5, color: '#95a5a6', marginTop: 6, textAlign: 'center' },
  trustError: { fontSize: 13, color: '#e74c3c', textAlign: 'center' },
  trustReview: {
    backgroundColor: '#f8f9fa',
    borderRadius: 9,
    padding: 9,
    marginBottom: 6,
    borderWidth: 1,
    borderColor: '#ecf0f1',
  },
  trustReviewHead: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
  trustReviewer: { fontSize: 13, fontWeight: '800', color: '#2c3e50' },
  trustStars: { fontSize: 12, color: '#f39c12' },
  trustDate: { fontSize: 11.5, color: '#95a5a6' },
  trustBody: { fontSize: 13, color: '#5d6d7e', lineHeight: 18, marginTop: 3 },
  trustLimit: { fontSize: 11.5, color: '#aeb6bf', lineHeight: 16, marginTop: 2 },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    padding: 40,
    backgroundColor: 'white',
    borderRadius: 12,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 8,
    textAlign: 'center',
  },
  emptyText: {
    fontSize: 14,
    color: '#7f8c8d',
    textAlign: 'center',
    lineHeight: 20,
    marginBottom: 16,
  },
  refreshButton: {
    backgroundColor: '#3498db',
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 8,
  },
  refreshText: {
    color: 'white',
    fontWeight: 'bold',
    fontSize: 14,
  },
});