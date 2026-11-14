import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
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
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { API_BASE_URL } from '../../constants/api';
import { useFeatures } from '../../context/FeatureContext';
import { ALL_LANGUAGES, LANGUAGE_FLAGS, useLang, type Lang } from '../../context/LanguageContext';
import { fetchWithTimeout } from '../../lib/network';
import { THEME_IDS, useTheme, type ThemeColors, type ThemeId } from '../../lib/theme';

/**
 * Mobile administrative Settings.
 *
 * Mirrors the web Settings module against the same endpoints, so a change made
 * on either surface applies to both. Writes are authorized server-side; this
 * screen only reflects that state.
 */

type Tab = 'user' | 'business' | 'branches' | 'other';

const THEME_LABELS: Record<ThemeId, string> = {
  'white-professional': 'White Professional',
  'royal-champagne-cream': 'Royal Champagne',
  'midnight-royal-gold': 'Midnight Gold',
  'emerald-bazaar': 'Emerald Bazaar',
  'oceanic-sapphire': 'Oceanic Sapphire',
};

export default function MipangilioScreen() {
  const router = useRouter();
  const { colors: tc, theme, setTheme } = useTheme();
  const { lang, changeLang } = useLang();
  const { refresh: refreshFeatures } = useFeatures();
  const styles = useMemo(() => makeStyles(tc), [tc]);

  const [loading, setLoading] = useState(true);
  const [tab, setTab] = useState<Tab>('user');
  const [data, setData] = useState<any>(null);

  const [userForm, setUserForm] = useState({ full_name: '', phone: '' });
  const [businessForm, setBusinessForm] = useState({
    business_name: '',
    business_type: '',
    business_location: '',
    business_description: '',
    business_logo_url: '',
  });
  const [saving, setSaving] = useState<string | null>(null);

  const [branchModal, setBranchModal] = useState(false);
  const [branchEditId, setBranchEditId] = useState<string | null>(null);
  const [branchForm, setBranchForm] = useState({ branch_name: '', location: '' });

  // Business-structure conversion. One modal serves both directions and shows
  // only the fields that direction needs.
  const [structureModal, setStructureModal] = useState<null | 'branchful' | 'branchless'>(null);
  const [structureForm, setStructureForm] = useState({
    branch_name: '',
    location: '',
    business_name: '',
    business_location: '',
    business_description: '',
  });

  const api = useCallback(async (path: string, options: any = {}) => {
    const token = await AsyncStorage.getItem('userToken');
    const response = await fetchWithTimeout(`${API_BASE_URL}${path}`, {
      ...options,
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token || ''}`,
        'Content-Type': 'application/json',
        'ngrok-skip-browser-warning': 'true',
        ...(options.headers || {}),
      },
    });

    const text = await response.text();
    let body: any = {};
    try { body = text ? JSON.parse(text) : {}; } catch { body = { error: text }; }

    if (response.status === 401) {
      await AsyncStorage.multiRemove(['userToken', 'userData', 'isLoggedIn', 'userRole']);
      router.replace('/login' as any);
      throw new Error('Session expired');
    }

    if (!response.ok || body.success === false) {
      throw new Error(body.error || body.message || `Server error ${response.status}`);
    }

    return body;
  }, [router]);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const body = await api('/api/settings');
      const user = body.user || {};
      const business = body.business || {};
      setData(body);
      setUserForm({ full_name: user.full_name || '', phone: user.phone || '' });
      setBusinessForm({
        business_name: business.business_name || '',
        business_type: business.business_type || '',
        business_location: business.business_location || '',
        business_description: business.business_description || '',
        business_logo_url: business.business_logo_url || '',
      });
    } catch (error: any) {
      Alert.alert('Mipangilio', error?.message || 'Imeshindikana kupakia mipangilio');
    } finally {
      setLoading(false);
    }
  }, [api]);

  useEffect(() => { load(); }, [load]);

  const saveUser = async () => {
    setSaving('user');
    try {
      const body = await api('/api/user/profile', {
        method: 'PUT',
        body: JSON.stringify({ full_name: userForm.full_name.trim(), phone: userForm.phone.trim() }),
      });
      setData((prev: any) => ({ ...prev, user: { ...(prev?.user || {}), ...(body.user || {}) } }));
      const raw = await AsyncStorage.getItem('userData');
      if (raw) {
        const stored = JSON.parse(raw);
        stored.full_name = body.user?.full_name ?? userForm.full_name;
        stored.phone = body.user?.phone ?? userForm.phone;
        await AsyncStorage.setItem('userData', JSON.stringify(stored));
      }
      Alert.alert('Imehifadhiwa', 'Taarifa zako zimehifadhiwa.');
    } catch (error: any) {
      Alert.alert('Hitilafu', error?.message || 'Imeshindikana kuhifadhi taarifa zako.');
    } finally {
      setSaving(null);
    }
  };

  const saveBusiness = async () => {
    setSaving('business');
    try {
      await api('/api/user/profile', {
        method: 'PUT',
        body: JSON.stringify({
          business_name: businessForm.business_name.trim(),
          business_type: businessForm.business_type.trim(),
          business_location: businessForm.business_location.trim(),
          business_description: businessForm.business_description.trim(),
          business_logo_url: businessForm.business_logo_url.trim(),
        }),
      });
      const raw = await AsyncStorage.getItem('userData');
      if (raw) {
        const stored = JSON.parse(raw);
        stored.business_name = businessForm.business_name.trim();
        stored.businessName = businessForm.business_name.trim();
        stored.business_location = businessForm.business_location.trim();
        await AsyncStorage.setItem('userData', JSON.stringify(stored));
      }
      setData((prev: any) => ({ ...prev, business: { ...(prev?.business || {}), ...businessForm } }));
      Alert.alert('Imehifadhiwa', 'Taarifa za biashara zimehifadhiwa.');
    } catch (error: any) {
      Alert.alert('Hitilafu', error?.message || 'Imeshindikana kuhifadhi taarifa za biashara.');
    } finally {
      setSaving(null);
    }
  };

  const updateFeature = async (key: string, value: boolean) => {
    if (!value && key === 'fridge_enabled') {
      Alert.alert(
        'Kuzima Friji?',
        'Kichupo cha Friji hataonekana kwa wauzaji na matumizi yake yatazuiwa. Stock na historia hazitafutwa.',
        [
          { text: 'Ghairi', style: 'cancel' },
          { text: 'Zima', style: 'destructive', onPress: () => persistFeature(key, false) },
        ]
      );
      return;
    }

    persistFeature(key, value);
  };

  const persistFeature = async (key: string, value: boolean) => {
    const previous = data?.settings || {};
    setData((prev: any) => ({ ...prev, settings: { ...(prev?.settings || {}), [key]: value } }));

    try {
      const body = await api('/api/settings/features', {
        method: 'PUT',
        body: JSON.stringify({ [key]: value }),
      });
      setData((prev: any) => ({ ...prev, settings: body.settings || prev?.settings, features: body.features || prev?.features }));
      // Propagate the switch to the tab bar / seller screens immediately.
      refreshFeatures();
    } catch (error: any) {
      setData((prev: any) => ({ ...prev, settings: previous }));
      Alert.alert('Hitilafu', error?.message || 'Imeshindikana kuhifadhi mipangilio.');
    }
  };

  const chooseLanguage = async (next: Lang) => {
    try {
      await api('/api/user/language', { method: 'PUT', body: JSON.stringify({ language: next }) });
      await changeLang(next);
      setData((prev: any) => ({ ...prev, user: { ...(prev?.user || {}), language: next } }));
    } catch (error: any) {
      Alert.alert('Hitilafu', error?.message || 'Imeshindikana kuhifadhi lugha.');
    }
  };

  const openBranch = (branch: any | null) => {
    setBranchEditId(branch ? String(branch.id) : null);
    setBranchForm({ branch_name: branch?.branch_name || '', location: branch?.location || '' });
    setBranchModal(true);
  };

  const saveBranch = async () => {
    if (branchForm.branch_name.trim().length < 2) {
      Alert.alert('Tawi', 'Jina la tawi linahitajika.');
      return;
    }

    setSaving('branch');
    try {
      const body = await api(branchEditId ? `/api/branches/${encodeURIComponent(branchEditId)}` : '/api/branches', {
        method: branchEditId ? 'PUT' : 'POST',
        body: JSON.stringify({
          branch_name: branchForm.branch_name.trim(),
          location: branchForm.location.trim(),
        }),
      });

      const saved = body.branch;
      setData((prev: any) => {
        const branches = Array.isArray(prev?.branches) ? [...prev.branches] : [];
        const index = branches.findIndex((b: any) => String(b.id) === String(saved.id));
        if (index >= 0) branches[index] = saved;
        else branches.push(saved);
        branches.sort((a: any, b: any) => String(a.branch_name).localeCompare(String(b.branch_name)));
        return { ...prev, branches };
      });

      setBranchModal(false);
    } catch (error: any) {
      Alert.alert('Hitilafu', error?.message || 'Imeshindikana kuhifadhi tawi.');
    } finally {
      setSaving(null);
    }
  };

  const setBranchStatus = (branch: any, status: 'active' | 'inactive') => {
    const apply = async () => {
      setSaving(`branch-${branch.id}`);
      try {
        let saved = { ...branch, status };
        if (status === 'inactive') {
          const body = await api(`/api/branches/${encodeURIComponent(branch.id)}`, { method: 'DELETE' });
          saved = body.branch || saved;
        } else {
          const body = await api(`/api/branches/${encodeURIComponent(branch.id)}`, {
            method: 'PUT',
            body: JSON.stringify({ status: 'active' }),
          });
          saved = body.branch || saved;
        }
        setData((prev: any) => ({
          ...prev,
          branches: (prev?.branches || []).map((b: any) => (String(b.id) === String(branch.id) ? saved : b)),
        }));
      } catch (error: any) {
        Alert.alert('Hitilafu', error?.message || 'Imeshindikana kubadili tawi.');
      } finally {
        setSaving(null);
      }
    };

    if (status === 'inactive') {
      Alert.alert('Kuzima tawi?', 'Wauzaji wake hawataweza kuingia hadi ulirudishe. Data yote inabaki.', [
        { text: 'Ghairi', style: 'cancel' },
        { text: 'Zima', style: 'destructive', onPress: apply },
      ]);
    } else {
      apply();
    }
  };

  const submitStructure = () => {
    if (!structureModal) return;

    const branchful = structureModal === 'branchful';
    const title = branchful ? 'Badilisha kuwa biashara ya matawi?' : 'Badilisha kuwa biashara ya eneo moja?';
    const message = branchful
      ? 'Biashara itaanza kufanya kazi kupitia matawi. Bidhaa, mauzo, huduma na ripoti zilizopo zitahamishiwa tawini la kwanza.'
      : 'Matawi yatunganishwe kuwa biashara moja. Historia ya mauzo, malipo, bidhaa, stock na ripoti inabaki. Bidhaa zenye jina/SKU sawa kwenye matawi zaidi ya moja zitazuia mabadiliko.';

    Alert.alert(title, message, [
      { text: 'Ghairi', style: 'cancel' },
      {
        text: 'Thibitisha',
        style: branchful ? 'default' : 'destructive',
        onPress: async () => {
          setSaving('structure');
          try {
            const body = branchful
              ? {
                  branch_name: structureForm.branch_name.trim(),
                  location: structureForm.location.trim(),
                }
              : {
                  business_name: structureForm.business_name.trim(),
                  business_location: structureForm.business_location.trim(),
                  business_description: structureForm.business_description.trim(),
                };

            const result = await api(
              branchful
                ? '/api/settings/business-structure/branchful'
                : '/api/settings/business-structure/branchless',
              { method: 'POST', body: JSON.stringify(body) }
            );

            if (result.token) {
              await AsyncStorage.setItem('userToken', result.token);
              const raw = await AsyncStorage.getItem('userData');
              if (raw) {
                const stored = JSON.parse(raw);
                stored.business_structure = result.business_structure;
                if (result.business_name) {
                  stored.business_name = result.business_name;
                  stored.businessName = result.business_name;
                }
                await AsyncStorage.setItem('userData', JSON.stringify(stored));
              }
            }

            setStructureModal(null);
            await refreshFeatures();
            await load();
            Alert.alert('Imefanikiwa', branchful ? 'Biashara sasa inafanya kazi kwa matawi.' : 'Biashara sasa inafanya kazi kwenye eneo moja.');
          } catch (error: any) {
            Alert.alert('Hitilafu', error?.message || 'Imeshindikana kubadili muundo wa biashara.');
          } finally {
            setSaving(null);
          }
        },
      },
    ]);
  };

  const settings = data?.settings || {};
  const business = data?.business || {};
  const user = data?.user || {};
  const branches: any[] = Array.isArray(data?.branches) ? data.branches : [];
  const isBranchBusiness = business.business_structure === 'branch';

  const tabs: { key: Tab; label: string; icon: any }[] = [
    { key: 'user', label: 'Mimi', icon: 'person-outline' },
    { key: 'business', label: 'Biashara', icon: 'storefront-outline' },
    { key: 'branches', label: 'Matawi', icon: 'business-outline' },
    { key: 'other', label: 'Mipangilio', icon: 'options-outline' },
  ];

  const renderSwitch = (key: string, title: string, description: string, badge?: string) => (
    <View style={styles.toggleRow} key={key}>
      <View style={{ flex: 1, paddingRight: 12 }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
          <Text style={styles.toggleTitle}>{title}</Text>
          {badge ? <Text style={styles.badge}>{badge}</Text> : null}
        </View>
        <Text style={styles.toggleDesc}>{description}</Text>
      </View>
      <Switch
        value={settings[key] !== false}
        onValueChange={(value) => updateFeature(key, value)}
        trackColor={{ false: tc.line2, true: tc.success }}
        thumbColor="#ffffff"
      />
    </View>
  );

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <View style={styles.header}>
        <TouchableOpacity onPress={() => router.back()} style={styles.backButton} accessibilityLabel="Rudi">
          <Ionicons name="chevron-back" size={24} color={tc.ink} />
        </TouchableOpacity>
        <View style={{ flex: 1 }}>
          <Text style={styles.headerTitle}>Mipangilio</Text>
          <Text style={styles.headerSub}>Taarifa zako, biashara na huduma</Text>
        </View>
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={tc.primary} />
          <Text style={styles.loadingText}>Inapakia mipangilio...</Text>
        </View>
      ) : (
        <>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.tabs}>
            {tabs.map((item) => (
              <TouchableOpacity
                key={item.key}
                onPress={() => setTab(item.key)}
                style={[styles.tab, tab === item.key && { backgroundColor: tc.tint, borderColor: tc.primary }]}
              >
                <Ionicons name={item.icon} size={15} color={tab === item.key ? tc.primary : tc.muted} />
                <Text style={[styles.tabLabel, tab === item.key && { color: tc.primary }]}>{item.label}</Text>
              </TouchableOpacity>
            ))}
          </ScrollView>

          <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
            {tab === 'user' && (
              <>
                <View style={styles.card}>
                  <Text style={styles.cardTitle}>Taarifa Zako</Text>
                  <Text style={styles.cardDesc}>Jina na simu yako. Jukumu, hali ya akaunti na biashara haziwezi kubadilishwa hapa.</Text>

                  <Text style={styles.label}>Barua pepe</Text>
                  <View style={styles.readonly}><Text style={styles.readonlyText}>{user.email || '—'}</Text></View>

                  <Text style={styles.label}>Jina kamili</Text>
                  <TextInput
                    style={styles.input}
                    value={userForm.full_name}
                    onChangeText={(value) => setUserForm((prev) => ({ ...prev, full_name: value }))}
                    maxLength={120}
                    placeholder="Jina lako kamili"
                    placeholderTextColor={tc.muted2}
                  />

                  <Text style={styles.label}>Namba ya simu</Text>
                  <TextInput
                    style={styles.input}
                    value={userForm.phone}
                    onChangeText={(value) => setUserForm((prev) => ({ ...prev, phone: value }))}
                    keyboardType="phone-pad"
                    maxLength={30}
                    placeholder="0757 000 000"
                    placeholderTextColor={tc.muted2}
                  />

                  <TouchableOpacity style={styles.primaryBtn} onPress={saveUser} disabled={saving === 'user'}>
                    {saving === 'user' ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryBtnText}>Hifadhi Taarifa Zako</Text>}
                  </TouchableOpacity>
                </View>

                <View style={styles.card}>
                  <Text style={styles.cardTitle}>Lugha</Text>
                  <Text style={styles.cardDesc}>Lugha ni upendeleo wako binafsi na huhifadhiwa kwenye akaunti yako.</Text>
                  <View style={styles.chipWrap}>
                    {ALL_LANGUAGES.map((code) => (
                      <TouchableOpacity
                        key={code}
                        onPress={() => chooseLanguage(code)}
                        style={[styles.chip, (data?.user?.language || lang) === code && styles.chipActive]}
                      >
                        <Text style={styles.chipText}>{LANGUAGE_FLAGS[code]} {code.toUpperCase()}</Text>
                      </TouchableOpacity>
                    ))}
                  </View>
                </View>

                <View style={styles.card}>
                  <Text style={styles.cardTitle}>Mandhari</Text>
                  <Text style={styles.cardDesc}>Mandhari ni upendeleo wako binafsi.</Text>
                  <View style={styles.chipWrap}>
                    {THEME_IDS.map((id) => (
                      <TouchableOpacity
                        key={id}
                        onPress={() => setTheme(id as ThemeId)}
                        style={[styles.chip, theme === id && styles.chipActive]}
                      >
                        <Text style={styles.chipText}>{THEME_LABELS[id as ThemeId]}</Text>
                      </TouchableOpacity>
                    ))}
                  </View>
                </View>
              </>
            )}

            {tab === 'business' && (
              <View style={styles.card}>
                <Text style={styles.cardTitle}>Taarifa za Biashara</Text>
                <Text style={styles.cardDesc}>Mabadiliko hayahamishi wauzaji, bidhaa, mauzo wala matawi; kitambulisho cha biashara kinabaki kimoja.</Text>

                <Text style={styles.label}>Jina la biashara</Text>
                <TextInput
                  style={styles.input}
                  value={businessForm.business_name}
                  onChangeText={(value) => setBusinessForm((prev) => ({ ...prev, business_name: value }))}
                  maxLength={150}
                />

                <Text style={styles.label}>Aina ya biashara</Text>
                <TextInput
                  style={styles.input}
                  value={businessForm.business_type}
                  onChangeText={(value) => setBusinessForm((prev) => ({ ...prev, business_type: value }))}
                  maxLength={100}
                  placeholder="Mfano: Duka la jumla"
                  placeholderTextColor={tc.muted2}
                />

                <Text style={styles.label}>Eneo</Text>
                <TextInput
                  style={styles.input}
                  value={businessForm.business_location}
                  onChangeText={(value) => setBusinessForm((prev) => ({ ...prev, business_location: value }))}
                  maxLength={255}
                />

                <Text style={styles.label}>Maelezo</Text>
                <TextInput
                  style={[styles.input, styles.multiline]}
                  value={businessForm.business_description}
                  onChangeText={(value) => setBusinessForm((prev) => ({ ...prev, business_description: value }))}
                  maxLength={1000}
                  multiline
                />

                <Text style={styles.label}>URL ya nembo</Text>
                <TextInput
                  style={styles.input}
                  value={businessForm.business_logo_url}
                  onChangeText={(value) => setBusinessForm((prev) => ({ ...prev, business_logo_url: value }))}
                  autoCapitalize="none"
                  placeholder="https://..."
                  placeholderTextColor={tc.muted2}
                />

                <TouchableOpacity style={styles.primaryBtn} onPress={saveBusiness} disabled={saving === 'business'}>
                  {saving === 'business' ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryBtnText}>Hifadhi Taarifa za Biashara</Text>}
                </TouchableOpacity>
              </View>
            )}

            {tab === 'branches' && (
              <View style={styles.card}>
                <Text style={styles.cardTitle}>Matawi</Text>
                {!isBranchBusiness ? (
                  <Text style={styles.cardDesc}>Biashara hii inafanya kazi kwenye eneo moja. Usimamizi wa matawi unapatikana kwa biashara zilizosajiliwa na matawi.</Text>
                ) : (
                  <>
                    <Text style={styles.cardDesc}>Ongeza au hariri matawi. Kuzima tawi hakuondoi wauzaji, bidhaa, mauzo wala ripoti.</Text>

                    {branches.length === 0 ? (
                      <Text style={styles.empty}>Hakuna matawi bado.</Text>
                    ) : (
                      branches.map((branch) => (
                        <View key={branch.id} style={styles.branchRow}>
                          <View style={{ flex: 1 }}>
                            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                              <View style={[styles.dot, branch.status === 'inactive' && { backgroundColor: tc.muted2 }]} />
                              <Text style={styles.branchName}>{branch.branch_name}</Text>
                              {branch.status === 'inactive' ? <Text style={styles.badgeMuted}>Limezimwa</Text> : null}
                            </View>
                            <Text style={styles.branchMeta}>{branch.location || 'Eneo halijawekwa'}</Text>
                          </View>

                          <View style={{ flexDirection: 'row', gap: 6 }}>
                            <TouchableOpacity style={styles.smallBtn} onPress={() => openBranch(branch)}>
                              <Text style={styles.smallBtnText}>Hariri</Text>
                            </TouchableOpacity>
                            <TouchableOpacity
                              style={[styles.smallBtn, branch.status === 'inactive' && { backgroundColor: tc.tint }]}
                              onPress={() => setBranchStatus(branch, branch.status === 'inactive' ? 'active' : 'inactive')}
                              disabled={saving === `branch-${branch.id}`}
                            >
                              <Text style={styles.smallBtnText}>{branch.status === 'inactive' ? 'Rudisha' : 'Zima'}</Text>
                            </TouchableOpacity>
                          </View>
                        </View>
                      ))
                    )}

                    <TouchableOpacity style={[styles.primaryBtn, { marginTop: 14 }]} onPress={() => openBranch(null)}>
                      <Text style={styles.primaryBtnText}>+ Ongeza Tawi</Text>
                    </TouchableOpacity>
                  </>
                )}
              </View>
            )}

            {tab === 'other' && (
              <>
                <View style={styles.card}>
                  <Text style={styles.cardTitle}>Muundo wa Biashara</Text>
                  <Text style={styles.cardDesc}>
                    {isBranchBusiness
                      ? 'Biashara inafanya kazi kwa matawi. Unaweza kuunganisha kuwa biashara moja ya eneo moja; historia yote inabaki.'
                      : 'Biashara inafanya kazi kwenye eneo moja. Unaweza kuiwezesha kufanya kazi kwa matawi kwa kuandikisha tawi la kwanza.'}
                  </Text>

                  <View style={[styles.readonly, { marginTop: 8 }]}>
                    <Text style={styles.readonlyText}>{isBranchBusiness ? 'Biashara ya matawi' : 'Biashara ya eneo moja'}</Text>
                  </View>

                  <TouchableOpacity
                    style={[styles.primaryBtn, !isBranchBusiness && { backgroundColor: tc.primary }]}
                    onPress={() => {
                      setStructureForm({
                        branch_name: `${business.business_name || ''} - Tawi Kuu`,
                        location: business.business_location || '',
                        business_name: business.business_name || '',
                        business_location: business.business_location || '',
                        business_description: business.business_description || '',
                      });
                      setStructureModal(isBranchBusiness ? 'branchless' : 'branchful');
                    }}
                  >
                    <Text style={styles.primaryBtnText}>
                      {isBranchBusiness ? 'Badilisha kuwa biashara ya eneo moja' : 'Badilisha kuwa biashara ya matawi'}
                    </Text>
                  </TouchableOpacity>
                </View>

                <View style={styles.card}>
                  <Text style={styles.cardTitle}>Huduma za Hiari</Text>
                  <Text style={styles.cardDesc}>Mipangilio hii inatumika kwa biashara yote, web na app. Kipengele kilichozimwa hakionyeshwi na maombi yake hukataliwa na seva. Data haifutwi.</Text>

                  {renderSwitch('fridge_enabled', 'Friji / Freezer', 'Washa ili wauzaji wasimamie stock ya ndani ya friji na kuihamisha.')}
                  {renderSwitch('scanner_enabled', 'Scanner', 'Huwasha uwanja wa barcode kwa msimamizi kupokea stock na kwa muuzaji kuchagua bidhaa wakati wa kuuza au kuhamisha stock. Scanner haiongezi stock yenyewe; msimamizi pekee anayeruhusiwa kupokea stock.')}
                  {renderSwitch('services_enabled', 'Huduma (Services)', 'Washa ili kuunda huduma na kuziuza. Historia ya huduma inabaki hata ikizimwa.')}
                  {renderSwitch('product_management_enabled', 'Muuzaji anaweza kusimamia bidhaa', 'Kikwashwa, muuzaji anaweza kufungua ukurasa wa Bidhaa Mpya, kusajili bidhaa, kupokea stock na kubadilisha bei/stock. Kikizimwa (chaguo-msingi), ni msimamizi pekee anayeweza kusimamia bidhaa.')}
                </View>

                <View style={styles.card}>
                  <Text style={styles.cardTitle}>Tahadhari za Stock</Text>
                  <Text style={styles.cardDesc}>Kuzima tahadhari hakuachi kufuatilia stock; kunazuia tu arifa mpya za aina hiyo.</Text>

                  {renderSwitch('notify_low_stock', 'Stock ndogo ya biashara', 'Bidhaa iliyo nje ya friji inafikia kiwango cha chini.')}
                  {renderSwitch('notify_fridge_stock', 'Stock ndogo ndani ya friji', 'Stock ya ndani ya friji inafikia kiwango chake cha chini.')}
                </View>
              </>
            )}

            <View style={{ height: 30 }} />
          </ScrollView>
        </>
      )}

      <Modal visible={structureModal !== null} transparent animationType="fade" onRequestClose={() => setStructureModal(null)}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>
              {structureModal === 'branchful' ? 'Tawi la Kwanza' : 'Taarifa ya Biashara Moja'}
            </Text>
            <Text style={styles.cardDesc}>
              {structureModal === 'branchful'
                ? 'Andikisha tawi la kwanza. Records zote zilizopo zitahamishiwa tawini hili, bila kubadili kiasi cha stock wala historia.'
                : 'Weka taarifa ya biashara moja. Matawi yatageuzwa kuwa historia; bidhaa na mauzo yote yatabaki.'}
            </Text>

            {structureModal === 'branchful' ? (
              <>
                <Text style={styles.label}>Jina la tawi</Text>
                <TextInput
                  style={styles.input}
                  value={structureForm.branch_name}
                  onChangeText={(value) => setStructureForm((prev) => ({ ...prev, branch_name: value }))}
                  maxLength={120}
                  placeholder="Mfano: Tawi Kuu"
                  placeholderTextColor={tc.muted2}
                />
                <Text style={styles.label}>Eneo la tawi</Text>
                <TextInput
                  style={styles.input}
                  value={structureForm.location}
                  onChangeText={(value) => setStructureForm((prev) => ({ ...prev, location: value }))}
                  maxLength={255}
                  placeholderTextColor={tc.muted2}
                />
              </>
            ) : (
              <>
                <Text style={styles.label}>Jina la biashara</Text>
                <TextInput
                  style={styles.input}
                  value={structureForm.business_name}
                  onChangeText={(value) => setStructureForm((prev) => ({ ...prev, business_name: value }))}
                  maxLength={150}
                />
                <Text style={styles.label}>Eneo</Text>
                <TextInput
                  style={styles.input}
                  value={structureForm.business_location}
                  onChangeText={(value) => setStructureForm((prev) => ({ ...prev, business_location: value }))}
                  maxLength={255}
                />
                <Text style={styles.label}>Maelezo</Text>
                <TextInput
                  style={[styles.input, styles.multiline]}
                  value={structureForm.business_description}
                  onChangeText={(value) => setStructureForm((prev) => ({ ...prev, business_description: value }))}
                  maxLength={1000}
                  multiline
                />
              </>
            )}

            <View style={{ flexDirection: 'row', gap: 10, marginTop: 18 }}>
              <TouchableOpacity style={[styles.primaryBtn, { flex: 1 }]} onPress={submitStructure} disabled={saving === 'structure'}>
                {saving === 'structure' ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryBtnText}>Thibitisha</Text>}
              </TouchableOpacity>
              <TouchableOpacity style={[styles.ghostBtn, { flex: 1 }]} onPress={() => setStructureModal(null)}>
                <Text style={styles.ghostBtnText}>Ghairi</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      <Modal visible={branchModal} transparent animationType="fade" onRequestClose={() => setBranchModal(false)}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>{branchEditId ? 'Hariri Tawi' : 'Ongeza Tawi'}</Text>

            <Text style={styles.label}>Jina la tawi</Text>
            <TextInput
              style={styles.input}
              value={branchForm.branch_name}
              onChangeText={(value) => setBranchForm((prev) => ({ ...prev, branch_name: value }))}
              maxLength={120}
              placeholder="Mfano: Tawi la Mlimani"
              placeholderTextColor={tc.muted2}
            />

            <Text style={styles.label}>Eneo</Text>
            <TextInput
              style={styles.input}
              value={branchForm.location}
              onChangeText={(value) => setBranchForm((prev) => ({ ...prev, location: value }))}
              maxLength={255}
              placeholder="Mfano: Mlimani City"
              placeholderTextColor={tc.muted2}
            />

            <View style={{ flexDirection: 'row', gap: 10, marginTop: 18 }}>
              <TouchableOpacity style={[styles.primaryBtn, { flex: 1 }]} onPress={saveBranch} disabled={saving === 'branch'}>
                {saving === 'branch' ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryBtnText}>Hifadhi</Text>}
              </TouchableOpacity>
              <TouchableOpacity style={[styles.ghostBtn, { flex: 1 }]} onPress={() => setBranchModal(false)}>
                <Text style={styles.ghostBtnText}>Ghairi</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

const makeStyles = (tc: ThemeColors) => StyleSheet.create({
  safe: { flex: 1, backgroundColor: tc.page },
  header: {
    flexDirection: 'row', alignItems: 'center', gap: 6,
    paddingHorizontal: 12, paddingVertical: 12,
    backgroundColor: tc.card, borderBottomWidth: 1, borderBottomColor: tc.line,
  },
  backButton: { width: 38, height: 38, alignItems: 'center', justifyContent: 'center' },
  headerTitle: { fontSize: 18, fontWeight: '800', color: tc.ink },
  headerSub: { fontSize: 11.5, color: tc.muted, marginTop: 1 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 10 },
  loadingText: { fontSize: 13, color: tc.muted },
  tabs: { paddingHorizontal: 12, paddingVertical: 12, gap: 8 },
  tab: {
    flexDirection: 'row', alignItems: 'center', gap: 6,
    paddingHorizontal: 13, paddingVertical: 8, borderRadius: 999,
    borderWidth: 1, borderColor: tc.line, backgroundColor: tc.card,
  },
  tabLabel: { fontSize: 12, fontWeight: '700', color: tc.muted },
  content: { paddingHorizontal: 12, paddingBottom: 20, gap: 12 },
  card: { backgroundColor: tc.card, borderRadius: 16, borderWidth: 1, borderColor: tc.line, padding: 16 },
  cardTitle: { fontSize: 15, fontWeight: '800', color: tc.ink },
  cardDesc: { fontSize: 11.5, lineHeight: 17, color: tc.muted, marginTop: 4, marginBottom: 6 },
  label: { fontSize: 11.5, fontWeight: '700', color: tc.ink, marginTop: 12, marginBottom: 5 },
  input: {
    borderWidth: 1, borderColor: tc.line, borderRadius: 10,
    paddingHorizontal: 12, paddingVertical: 10, fontSize: 13.5,
    color: tc.ink, backgroundColor: tc.card,
  },
  multiline: { minHeight: 80, textAlignVertical: 'top' },
  readonly: { borderWidth: 1, borderColor: tc.line, borderRadius: 10, paddingHorizontal: 12, paddingVertical: 11, backgroundColor: tc.soft },
  readonlyText: { fontSize: 13, color: tc.muted },
  primaryBtn: {
    marginTop: 16, backgroundColor: tc.primary, borderRadius: 11,
    paddingVertical: 12, alignItems: 'center', justifyContent: 'center',
  },
  primaryBtnText: { color: '#ffffff', fontSize: 13, fontWeight: '800' },
  ghostBtn: { marginTop: 16, borderWidth: 1, borderColor: tc.line, borderRadius: 11, paddingVertical: 12, alignItems: 'center' },
  ghostBtnText: { color: tc.ink, fontSize: 13, fontWeight: '700' },
  toggleRow: {
    flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between',
    paddingVertical: 12, borderTopWidth: 1, borderTopColor: tc.line,
  },
  toggleTitle: { fontSize: 13, fontWeight: '700', color: tc.ink },
  toggleDesc: { fontSize: 11, lineHeight: 16, color: tc.muted, marginTop: 3 },
  badge: {
    fontSize: 8.5, fontWeight: '800', textTransform: 'uppercase',
    color: '#b45309', backgroundColor: '#fff7ed', paddingHorizontal: 6, paddingVertical: 2, borderRadius: 999,
  },
  badgeMuted: {
    fontSize: 8.5, fontWeight: '800', textTransform: 'uppercase',
    color: tc.muted, backgroundColor: tc.soft, paddingHorizontal: 6, paddingVertical: 2, borderRadius: 999,
  },
  chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 7, marginTop: 10 },
  chip: { borderWidth: 1, borderColor: tc.line, borderRadius: 999, paddingHorizontal: 11, paddingVertical: 7, backgroundColor: tc.card },
  chipActive: { borderColor: tc.primary, backgroundColor: tc.tint },
  chipText: { fontSize: 11.5, fontWeight: '700', color: tc.ink },
  branchRow: {
    flexDirection: 'row', alignItems: 'center', gap: 10,
    borderWidth: 1, borderColor: tc.line, borderRadius: 12,
    padding: 12, marginTop: 10, backgroundColor: tc.soft,
  },
  dot: { width: 7, height: 7, borderRadius: 4, backgroundColor: tc.success },
  branchName: { fontSize: 13, fontWeight: '700', color: tc.ink },
  branchMeta: { fontSize: 11, color: tc.muted, marginTop: 3 },
  smallBtn: { backgroundColor: tc.card, borderWidth: 1, borderColor: tc.line, borderRadius: 8, paddingHorizontal: 10, paddingVertical: 7 },
  smallBtnText: { fontSize: 11, fontWeight: '700', color: tc.ink },
  empty: { fontSize: 12, color: tc.muted, textAlign: 'center', paddingVertical: 16 },
  modalOverlay: { flex: 1, backgroundColor: 'rgba(15,23,42,.55)', alignItems: 'center', justifyContent: 'center', padding: 18 },
  modalCard: { width: '100%', maxWidth: 460, backgroundColor: tc.card, borderRadius: 18, padding: 20 },
  modalTitle: { fontSize: 16, fontWeight: '800', color: tc.ink },
});
