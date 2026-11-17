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
  const { lang, changeLang, t } = useLang();
  const L = useCallback((key: string, fallback: string) => {
    const value = t(key);
    return value === key ? fallback : value;
  }, [t]);
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
  const [passwordForm, setPasswordForm] = useState({ current: '', newPass: '', confirm: '' });
  const [changingPassword, setChangingPassword] = useState(false);

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
      Alert.alert(L('settings_screen.alert_settings_title', 'Mipangilio'), error?.message || L('settings_screen.alert_load_error', 'Imeshindikana kupakia mipangilio'));
    } finally {
      setLoading(false);
    }
  }, [api, L]);

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
      Alert.alert(L('settings_screen.alert_saved_title', 'Imehifadhiwa'), L('settings_screen.alert_user_saved', 'Taarifa zako zimehifadhiwa.'));
    } catch (error: any) {
      Alert.alert(L('settings_screen.alert_error_title', 'Hitilafu'), error?.message || L('settings_screen.alert_user_save_error', 'Imeshindikana kuhifadhi taarifa zako.'));
    } finally {
      setSaving(null);
    }
  };

  const handleChangePassword = async () => {
    const { current, newPass, confirm } = passwordForm;
    if (!current || !newPass || !confirm) {
      Alert.alert(L('settings_screen.alert_error_title', 'Hitilafu'), L('settings_screen.alert_password_fields', 'Jaza sehemu zote za nenosiri.'));
      return;
    }
    if (newPass !== confirm) {
      Alert.alert(L('settings_screen.alert_error_title', 'Hitilafu'), L('settings_screen.alert_password_mismatch', 'Nenosiri jipya na uthibitisho havifanani.'));
      return;
    }
    if (newPass.length < 6) {
      Alert.alert(L('settings_screen.alert_error_title', 'Hitilafu'), L('settings_screen.alert_password_short', 'Nenosiri jipya lazima liwe na angalau herufi 6.'));
      return;
    }
    setChangingPassword(true);
    try {
      await api('/api/user/password', {
        method: 'PUT',
        body: JSON.stringify({ current_password: current, new_password: newPass, confirm_password: confirm }),
      });
      setPasswordForm({ current: '', newPass: '', confirm: '' });
      Alert.alert(L('settings_screen.alert_success_title', 'Imefanikiwa'), L('settings_screen.alert_password_changed', 'Nenosiri lako limebadilishwa.'));
    } catch (error: any) {
      Alert.alert(L('settings_screen.alert_error_title', 'Hitilafu'), error?.message || L('settings_screen.alert_password_change_error', 'Imeshindikana kubadilisha nenosiri.'));
    } finally {
      setChangingPassword(false);
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
      Alert.alert(L('settings_screen.alert_saved_title', 'Imehifadhiwa'), L('settings_screen.alert_business_saved', 'Taarifa za biashara zimehifadhiwa.'));
    } catch (error: any) {
      Alert.alert(L('settings_screen.alert_error_title', 'Hitilafu'), error?.message || L('settings_screen.alert_business_save_error', 'Imeshindikana kuhifadhi taarifa za biashara.'));
    } finally {
      setSaving(null);
    }
  };

  const updateFeature = async (key: string, value: boolean) => {
    if (!value && key === 'fridge_enabled') {
      Alert.alert(
        L('settings_screen.alert_disable_fridge_title', 'Kuzima Friji?'),
        L('settings_screen.alert_disable_fridge_msg', 'Kichupo cha Friji hataonekana kwa wauzaji na matumizi yake yatazuiwa. Stock na historia hazitafutwa.'),
        [
          { text: L('settings_screen.cancel', 'Ghairi'), style: 'cancel' },
          { text: L('settings_screen.alert_disable_fridge_confirm', 'Zima'), style: 'destructive', onPress: () => persistFeature(key, false) },
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
      Alert.alert(L('settings_screen.alert_error_title', 'Hitilafu'), error?.message || L('settings_screen.alert_feature_save_error', 'Imeshindikana kuhifadhi mipangilio.'));
    }
  };

  const chooseLanguage = async (next: Lang) => {
    try {
      await api('/api/user/language', { method: 'PUT', body: JSON.stringify({ language: next }) });
      await changeLang(next);
      setData((prev: any) => ({ ...prev, user: { ...(prev?.user || {}), language: next } }));
    } catch (error: any) {
      Alert.alert(L('settings_screen.alert_error_title', 'Hitilafu'), error?.message || L('settings_screen.alert_language_save_error', 'Imeshindikana kuhifadhi lugha.'));
    }
  };

  const openBranch = (branch: any | null) => {
    setBranchEditId(branch ? String(branch.id) : null);
    setBranchForm({ branch_name: branch?.branch_name || '', location: branch?.location || '' });
    setBranchModal(true);
  };

  const saveBranch = async () => {
    if (branchForm.branch_name.trim().length < 2) {
      Alert.alert(L('settings_screen.alert_branch_title', 'Tawi'), L('settings_screen.alert_branch_name_required', 'Jina la tawi linahitajika.'));
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
      Alert.alert(L('settings_screen.alert_error_title', 'Hitilafu'), error?.message || L('settings_screen.alert_branch_save_error', 'Imeshindikana kuhifadhi tawi.'));
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
        Alert.alert(L('settings_screen.alert_error_title', 'Hitilafu'), error?.message || L('settings_screen.alert_branch_toggle_error', 'Imeshindikana kubadili tawi.'));
      } finally {
        setSaving(null);
      }
    };

    if (status === 'inactive') {
      Alert.alert(
        L('settings_screen.alert_deactivate_branch_title', 'Kuzima tawi?'),
        L('settings_screen.alert_deactivate_branch_msg', 'Wauzaji wake hawataweza kuingia hadi ulirudishe. Data yote inabaki.'),
        [
          { text: L('settings_screen.cancel', 'Ghairi'), style: 'cancel' },
          { text: L('settings_screen.branch_deactivate', 'Zima'), style: 'destructive', onPress: apply },
        ]
      );
    } else {
      apply();
    }
  };

  const submitStructure = () => {
    if (!structureModal) return;

    const branchful = structureModal === 'branchful';
    const title = branchful
      ? L('settings_screen.alert_structure_branchful_title', 'Badilisha kuwa biashara ya matawi?')
      : L('settings_screen.alert_structure_branchless_title', 'Badilisha kuwa biashara ya eneo moja?');
    const message = branchful
      ? L('settings_screen.alert_structure_branchful_msg', 'Biashara itaanza kufanya kazi kupitia matawi. Bidhaa, mauzo, huduma na ripoti zilizopo zitahamishiwa tawini la kwanza.')
      : L('settings_screen.alert_structure_branchless_msg', 'Matawi yatunganishwe kuwa biashara moja. Historia ya mauzo, malipo, bidhaa, stock na ripoti inabaki. Bidhaa zenye jina/SKU sawa kwenye matawi zaidi ya moja zitazuia mabadiliko.');

    Alert.alert(title, message, [
      { text: L('settings_screen.cancel', 'Ghairi'), style: 'cancel' },
      {
        text: L('settings_screen.confirm', 'Thibitisha'),
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
            Alert.alert(
              L('settings_screen.alert_success_title', 'Imefanikiwa'),
              branchful
                ? L('settings_screen.alert_structure_branchful_success', 'Biashara sasa inafanya kazi kwa matawi.')
                : L('settings_screen.alert_structure_branchless_success', 'Biashara sasa inafanya kazi kwenye eneo moja.')
            );
          } catch (error: any) {
            Alert.alert(L('settings_screen.alert_error_title', 'Hitilafu'), error?.message || L('settings_screen.alert_structure_error', 'Imeshindikana kubadili muundo wa biashara.'));
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
    { key: 'user', label: L('settings_screen.tab_user', 'Mimi'), icon: 'person-outline' },
    { key: 'business', label: L('settings_screen.tab_business', 'Biashara'), icon: 'storefront-outline' },
    { key: 'branches', label: L('settings_screen.tab_branches', 'Matawi'), icon: 'business-outline' },
    { key: 'other', label: L('settings_screen.tab_other', 'Mengine'), icon: 'options-outline' },
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
        <TouchableOpacity onPress={() => router.back()} style={styles.backButton} accessibilityLabel={L('settings_screen.back', 'Rudi')}>
          <Ionicons name="chevron-back" size={24} color={tc.ink} />
        </TouchableOpacity>
        <View style={{ flex: 1 }}>
          <Text style={styles.headerTitle}>{L('settings_screen.header_title', 'Mipangilio')}</Text>
          <Text style={styles.headerSub}>{L('settings_screen.header_sub', 'Taarifa zako, biashara na huduma')}</Text>
        </View>
      </View>

      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator size="large" color={tc.primary} />
          <Text style={styles.loadingText}>{L('settings_screen.loading', 'Inapakia mipangilio...')}</Text>
        </View>
      ) : (
        <>
          <View style={styles.tabsGrid}>
            {tabs.map((item) => (
              <TouchableOpacity
                key={item.key}
                onPress={() => setTab(item.key)}
                style={[styles.tabCard, tab === item.key && styles.tabCardActive]}
                accessibilityRole="button"
                accessibilityState={{ selected: tab === item.key }}
              >
                <View style={[styles.tabIconWrap, tab === item.key && styles.tabIconWrapActive]}>
                  <Ionicons name={item.icon} size={18} color={tab === item.key ? '#ffffff' : tc.primary} />
                </View>
                <Text style={[styles.tabCardLabel, tab === item.key && styles.tabCardLabelActive]}>{item.label}</Text>
              </TouchableOpacity>
            ))}
          </View>

          <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
            {tab === 'user' && (
              <>
                <View style={styles.card}>
                  <Text style={styles.cardTitle}>{L('settings_screen.user_card_title', 'Taarifa Zako')}</Text>
                  <Text style={styles.cardDesc}>{L('settings_screen.user_card_desc', 'Jina na simu yako. Jukumu, hali ya akaunti na biashara haziwezi kubadilishwa hapa.')}</Text>

                  <Text style={styles.label}>{L('settings_screen.label_email', 'Barua pepe')}</Text>
                  <View style={styles.readonly}><Text style={styles.readonlyText}>{user.email || '—'}</Text></View>

                  <Text style={styles.label}>{L('settings_screen.label_full_name', 'Jina kamili')}</Text>
                  <TextInput
                    style={styles.input}
                    value={userForm.full_name}
                    onChangeText={(value) => setUserForm((prev) => ({ ...prev, full_name: value }))}
                    maxLength={120}
                    placeholder={L('settings_screen.full_name_placeholder', 'Jina lako kamili')}
                    placeholderTextColor={tc.muted2}
                  />

                  <Text style={styles.label}>{L('settings_screen.label_phone', 'Namba ya simu')}</Text>
                  <TextInput
                    style={styles.input}
                    value={userForm.phone}
                    onChangeText={(value) => setUserForm((prev) => ({ ...prev, phone: value }))}
                    keyboardType="phone-pad"
                    maxLength={30}
                    placeholder={L('settings_screen.phone_placeholder', '0757 000 000')}
                    placeholderTextColor={tc.muted2}
                  />

                  <TouchableOpacity style={styles.primaryBtn} onPress={saveUser} disabled={saving === 'user'}>
                    {saving === 'user' ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryBtnText}>{L('settings_screen.save_user', 'Hifadhi Taarifa Zako')}</Text>}
                  </TouchableOpacity>
                </View>

                <View style={styles.card}>
                  <Text style={styles.cardTitle}>{L('settings_screen.change_password_title', 'Badilisha Nenosiri')}</Text>
                  <Text style={styles.cardDesc}>{L('settings_screen.change_password_desc', 'Badilisha nenosiri la akaunti yako. Nenosiri jipya lazima liwe na angalau herufi 6.')}</Text>

                  <Text style={styles.label}>{L('settings_screen.current_password', 'Nenosiri la sasa')}</Text>
                  <TextInput
                    style={styles.input}
                    value={passwordForm.current}
                    onChangeText={(value) => setPasswordForm((prev) => ({ ...prev, current: value }))}
                    secureTextEntry
                    placeholder={L('settings_screen.current_password_placeholder', 'Nenosiri la sasa')}
                    placeholderTextColor={tc.muted2}
                  />

                  <Text style={styles.label}>{L('settings_screen.new_password', 'Nenosiri jipya')}</Text>
                  <TextInput
                    style={styles.input}
                    value={passwordForm.newPass}
                    onChangeText={(value) => setPasswordForm((prev) => ({ ...prev, newPass: value }))}
                    secureTextEntry
                    placeholder={L('settings_screen.new_password_placeholder', 'Nenosiri jipya')}
                    placeholderTextColor={tc.muted2}
                  />

                  <Text style={styles.label}>{L('settings_screen.confirm_password', 'Thibitisha nenosiri jipya')}</Text>
                  <TextInput
                    style={styles.input}
                    value={passwordForm.confirm}
                    onChangeText={(value) => setPasswordForm((prev) => ({ ...prev, confirm: value }))}
                    secureTextEntry
                    placeholder={L('settings_screen.confirm_password_placeholder', 'Thibitisha nenosiri jipya')}
                    placeholderTextColor={tc.muted2}
                  />

                  <TouchableOpacity style={styles.primaryBtn} onPress={handleChangePassword} disabled={changingPassword}>
                    {changingPassword ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryBtnText}>{L('settings_screen.change_password_button', 'Badilisha Nenosiri')}</Text>}
                  </TouchableOpacity>
                </View>

                <View style={styles.card}>
                  <Text style={styles.cardTitle}>{L('settings_screen.language_title', 'Lugha')}</Text>
                  <Text style={styles.cardDesc}>{L('settings_screen.language_desc', 'Lugha ni upendeleo wako binafsi na huhifadhiwa kwenye akaunti yako.')}</Text>
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
                  <Text style={styles.cardTitle}>{L('settings_screen.theme_title', 'Mandhari')}</Text>
                  <Text style={styles.cardDesc}>{L('settings_screen.theme_desc', 'Mandhari ni upendeleo wako binafsi.')}</Text>
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
                <Text style={styles.cardTitle}>{L('settings_screen.business_card_title', 'Taarifa za Biashara')}</Text>
                <Text style={styles.cardDesc}>{L('settings_screen.business_card_desc', 'Mabadiliko hayahamishi wauzaji, bidhaa, mauzo wala matawi; kitambulisho cha biashara kinabaki kimoja.')}</Text>

                <Text style={styles.label}>{L('settings_screen.label_business_name', 'Jina la biashara')}</Text>
                <TextInput
                  style={styles.input}
                  value={businessForm.business_name}
                  onChangeText={(value) => setBusinessForm((prev) => ({ ...prev, business_name: value }))}
                  maxLength={150}
                />

                <Text style={styles.label}>{L('settings_screen.label_business_type', 'Aina ya biashara')}</Text>
                <TextInput
                  style={styles.input}
                  value={businessForm.business_type}
                  onChangeText={(value) => setBusinessForm((prev) => ({ ...prev, business_type: value }))}
                  maxLength={100}
                  placeholder={L('settings_screen.business_type_placeholder', 'Mfano: Duka la jumla')}
                  placeholderTextColor={tc.muted2}
                />

                <Text style={styles.label}>{L('settings_screen.label_location', 'Eneo')}</Text>
                <TextInput
                  style={styles.input}
                  value={businessForm.business_location}
                  onChangeText={(value) => setBusinessForm((prev) => ({ ...prev, business_location: value }))}
                  maxLength={255}
                />

                <Text style={styles.label}>{L('settings_screen.label_description', 'Maelezo')}</Text>
                <TextInput
                  style={[styles.input, styles.multiline]}
                  value={businessForm.business_description}
                  onChangeText={(value) => setBusinessForm((prev) => ({ ...prev, business_description: value }))}
                  maxLength={1000}
                  multiline
                />

                <Text style={styles.label}>{L('settings_screen.label_logo_url', 'URL ya nembo')}</Text>
                <TextInput
                  style={styles.input}
                  value={businessForm.business_logo_url}
                  onChangeText={(value) => setBusinessForm((prev) => ({ ...prev, business_logo_url: value }))}
                  autoCapitalize="none"
                  placeholder="https://..."
                  placeholderTextColor={tc.muted2}
                />

                <TouchableOpacity style={styles.primaryBtn} onPress={saveBusiness} disabled={saving === 'business'}>
                  {saving === 'business' ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryBtnText}>{L('settings_screen.save_business', 'Hifadhi Taarifa za Biashara')}</Text>}
                </TouchableOpacity>
              </View>
            )}

            {tab === 'branches' && (
              <View style={styles.card}>
                <Text style={styles.cardTitle}>{L('settings_screen.branches_title', 'Matawi')}</Text>
                {!isBranchBusiness ? (
                  <Text style={styles.cardDesc}>{L('settings_screen.branches_single_location', 'Biashara hii inafanya kazi kwenye eneo moja. Usimamizi wa matawi unapatikana kwa biashara zilizosajiliwa na matawi.')}</Text>
                ) : (
                  <>
                    <Text style={styles.cardDesc}>{L('settings_screen.branches_desc', 'Ongeza au hariri matawi. Kuzima tawi hakuondoi wauzaji, bidhaa, mauzo wala ripoti.')}</Text>

                    {branches.length === 0 ? (
                      <Text style={styles.empty}>{L('settings_screen.branches_empty', 'Hakuna matawi bado.')}</Text>
                    ) : (
                      branches.map((branch) => (
                        <View key={branch.id} style={styles.branchRow}>
                          <View style={{ flex: 1 }}>
                            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                              <View style={[styles.dot, branch.status === 'inactive' && { backgroundColor: tc.muted2 }]} />
                              <Text style={styles.branchName}>{branch.branch_name}</Text>
                              {branch.status === 'inactive' ? <Text style={styles.badgeMuted}>{L('settings_screen.branch_inactive', 'Limezimwa')}</Text> : null}
                            </View>
                            <Text style={styles.branchMeta}>{branch.location || L('settings_screen.branch_no_location', 'Eneo halijawekwa')}</Text>
                          </View>

                          <View style={{ flexDirection: 'row', gap: 6 }}>
                            <TouchableOpacity style={styles.smallBtn} onPress={() => openBranch(branch)}>
                              <Text style={styles.smallBtnText}>{L('settings_screen.branch_edit', 'Hariri')}</Text>
                            </TouchableOpacity>
                            <TouchableOpacity
                              style={[styles.smallBtn, branch.status === 'inactive' && { backgroundColor: tc.tint }]}
                              onPress={() => setBranchStatus(branch, branch.status === 'inactive' ? 'active' : 'inactive')}
                              disabled={saving === `branch-${branch.id}`}
                            >
                              <Text style={styles.smallBtnText}>{branch.status === 'inactive' ? L('settings_screen.branch_reactivate', 'Rudisha') : L('settings_screen.branch_deactivate', 'Zima')}</Text>
                            </TouchableOpacity>
                          </View>
                        </View>
                      ))
                    )}

                    <TouchableOpacity style={[styles.primaryBtn, { marginTop: 14 }]} onPress={() => openBranch(null)}>
                      <Text style={styles.primaryBtnText}>{L('settings_screen.btn_add_branch', '+ Ongeza Tawi')}</Text>
                    </TouchableOpacity>
                  </>
                )}
              </View>
            )}

            {tab === 'other' && (
              <>
                <View style={styles.card}>
                  <Text style={styles.cardTitle}>{L('settings_screen.structure_title', 'Muundo wa Biashara')}</Text>
                  <Text style={styles.cardDesc}>
                    {isBranchBusiness
                      ? L('settings_screen.structure_desc_branchful', 'Biashara inafanya kazi kwa matawi. Unaweza kuunganisha kuwa biashara moja ya eneo moja; historia yote inabaki.')
                      : L('settings_screen.structure_desc_branchless', 'Biashara inafanya kazi kwenye eneo moja. Unaweza kuiwezesha kufanya kazi kwa matawi kwa kuandikisha tawi la kwanza.')}
                  </Text>

                  <View style={[styles.readonly, { marginTop: 8 }]}>
                    <Text style={styles.readonlyText}>{isBranchBusiness ? L('settings_screen.structure_branchful', 'Biashara ya matawi') : L('settings_screen.structure_branchless', 'Biashara ya eneo moja')}</Text>
                  </View>

                  <TouchableOpacity
                    style={[styles.primaryBtn, !isBranchBusiness && { backgroundColor: tc.primary }]}
                    onPress={() => {
                      setStructureForm({
                        branch_name: `${business.business_name || ''} - ${L('settings_screen.branch_main', 'Tawi Kuu')}`,
                        location: business.business_location || '',
                        business_name: business.business_name || '',
                        business_location: business.business_location || '',
                        business_description: business.business_description || '',
                      });
                      setStructureModal(isBranchBusiness ? 'branchless' : 'branchful');
                    }}
                  >
                    <Text style={styles.primaryBtnText}>
                      {isBranchBusiness
                        ? L('settings_screen.btn_to_branchless', 'Badilisha kuwa biashara ya eneo moja')
                        : L('settings_screen.btn_to_branchful', 'Badilisha kuwa biashara ya matawi')}
                    </Text>
                  </TouchableOpacity>
                </View>

                <View style={styles.card}>
                  <Text style={styles.cardTitle}>{L('settings_screen.optional_services_title', 'Huduma za Hiari')}</Text>
                  <Text style={styles.cardDesc}>{L('settings_screen.optional_services_desc', 'Mipangilio hii inatumika kwa biashara yote, web na app. Kipengele kilichozimwa hakionyeshwi na maombi yake hukataliwa na seva. Data haifutwi.')}</Text>

                  {renderSwitch('fridge_enabled', L('settings_screen.fridge_title', 'Friji / Freezer'), L('settings_screen.fridge_desc', 'Washa ili wauzaji wasimamie stock ya ndani ya friji na kuihamisha.'))}
                  {renderSwitch('scanner_enabled', L('settings_screen.scanner_title', 'Scanner'), L('settings_screen.scanner_desc', 'Huwasha uwanja wa barcode kwa msimamizi kupokea stock na kwa muuzaji kuchagua bidhaa wakati wa kuuza au kuhamisha stock. Scanner haiongezi stock yenyewe; msimamizi pekee anayeruhusiwa kupokea stock.'))}
                  {renderSwitch('services_enabled', L('settings_screen.services_title', 'Huduma (Services)'), L('settings_screen.services_desc', 'Washa ili kuunda huduma na kuziuza. Historia ya huduma inabaki hata ikizimwa.'))}
                  {renderSwitch('product_management_enabled', L('settings_screen.product_mgmt_title', 'Muuzaji anaweza kusimamia bidhaa'), L('settings_screen.product_mgmt_desc', 'Kikwashwa, muuzaji anaweza kufungua ukurasa wa Bidhaa Mpya, kusajili bidhaa, kupokea stock na kubadilisha bei/stock. Kikizimwa (chaguo-msingi), ni msimamizi pekee anayeweza kusimamia bidhaa.'))}
                </View>

                <View style={styles.card}>
                  <Text style={styles.cardTitle}>{L('settings_screen.stock_alerts_title', 'Tahadhari za Stock')}</Text>
                  <Text style={styles.cardDesc}>{L('settings_screen.stock_alerts_desc', 'Kuzima tahadhari hakuachi kufuatilia stock; kunazuia tu arifa mpya za aina hiyo.')}</Text>

                  {renderSwitch('notify_low_stock', L('settings_screen.low_stock_title', 'Stock ndogo ya biashara'), L('settings_screen.low_stock_desc', 'Bidhaa iliyo nje ya friji inafikia kiwango cha chini.'))}
                  {renderSwitch('notify_fridge_stock', L('settings_screen.fridge_stock_title', 'Stock ndogo ndani ya friji'), L('settings_screen.fridge_stock_desc', 'Stock ya ndani ya friji inafikia kiwango chake cha chini.'))}
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
              {structureModal === 'branchful'
                ? L('settings_screen.structure_modal_branchful_title', 'Tawi la Kwanza')
                : L('settings_screen.structure_modal_branchless_title', 'Taarifa ya Biashara Moja')}
            </Text>
            <Text style={styles.cardDesc}>
              {structureModal === 'branchful'
                ? L('settings_screen.structure_modal_branchful_desc', 'Andikisha tawi la kwanza. Records zote zilizopo zitahamishiwa tawini hili, bila kubadili kiasi cha stock wala historia.')
                : L('settings_screen.structure_modal_branchless_desc', 'Weka taarifa ya biashara moja. Matawi yatageuzwa kuwa historia; bidhaa na mauzo yote yatabaki.')}
            </Text>

            {structureModal === 'branchful' ? (
              <>
                <Text style={styles.label}>{L('settings_screen.label_branch_name', 'Jina la tawi')}</Text>
                <TextInput
                  style={styles.input}
                  value={structureForm.branch_name}
                  onChangeText={(value) => setStructureForm((prev) => ({ ...prev, branch_name: value }))}
                  maxLength={120}
                  placeholder={L('settings_screen.branch_name_placeholder_modal', 'Mfano: Tawi Kuu')}
                  placeholderTextColor={tc.muted2}
                />
                <Text style={styles.label}>{L('settings_screen.label_branch_location', 'Eneo la tawi')}</Text>
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
                <Text style={styles.label}>{L('settings_screen.label_business_name', 'Jina la biashara')}</Text>
                <TextInput
                  style={styles.input}
                  value={structureForm.business_name}
                  onChangeText={(value) => setStructureForm((prev) => ({ ...prev, business_name: value }))}
                  maxLength={150}
                />
                <Text style={styles.label}>{L('settings_screen.label_location', 'Eneo')}</Text>
                <TextInput
                  style={styles.input}
                  value={structureForm.business_location}
                  onChangeText={(value) => setStructureForm((prev) => ({ ...prev, business_location: value }))}
                  maxLength={255}
                />
                <Text style={styles.label}>{L('settings_screen.label_description', 'Maelezo')}</Text>
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
                {saving === 'structure' ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryBtnText}>{L('settings_screen.confirm', 'Thibitisha')}</Text>}
              </TouchableOpacity>
              <TouchableOpacity style={[styles.ghostBtn, { flex: 1 }]} onPress={() => setStructureModal(null)}>
                <Text style={styles.ghostBtnText}>{L('settings_screen.cancel', 'Ghairi')}</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      <Modal visible={branchModal} transparent animationType="fade" onRequestClose={() => setBranchModal(false)}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>{branchEditId ? L('settings_screen.branch_modal_edit', 'Hariri Tawi') : L('settings_screen.branch_modal_add', 'Ongeza Tawi')}</Text>

            <Text style={styles.label}>{L('settings_screen.label_branch_name', 'Jina la tawi')}</Text>
            <TextInput
              style={styles.input}
              value={branchForm.branch_name}
              onChangeText={(value) => setBranchForm((prev) => ({ ...prev, branch_name: value }))}
              maxLength={120}
              placeholder={L('settings_screen.branch_name_placeholder', 'Mfano: Tawi la Mlimani')}
              placeholderTextColor={tc.muted2}
            />

            <Text style={styles.label}>{L('settings_screen.label_location', 'Eneo')}</Text>
            <TextInput
              style={styles.input}
              value={branchForm.location}
              onChangeText={(value) => setBranchForm((prev) => ({ ...prev, location: value }))}
              maxLength={255}
              placeholder={L('settings_screen.branch_location_placeholder', 'Mfano: Mlimani City')}
              placeholderTextColor={tc.muted2}
            />

            <View style={{ flexDirection: 'row', gap: 10, marginTop: 18 }}>
              <TouchableOpacity style={[styles.primaryBtn, { flex: 1 }]} onPress={saveBranch} disabled={saving === 'branch'}>
                {saving === 'branch' ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryBtnText}>{L('settings_screen.save', 'Hifadhi')}</Text>}
              </TouchableOpacity>
              <TouchableOpacity style={[styles.ghostBtn, { flex: 1 }]} onPress={() => setBranchModal(false)}>
                <Text style={styles.ghostBtnText}>{L('settings_screen.cancel', 'Ghairi')}</Text>
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
  tabsGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, paddingHorizontal: 12, paddingVertical: 12 },
  tabCard: {
    flexBasis: '47%', flexGrow: 1, flexDirection: 'row', alignItems: 'center', gap: 10,
    paddingHorizontal: 12, paddingVertical: 12, borderRadius: 14,
    borderWidth: 1, borderColor: tc.line, backgroundColor: tc.card,
  },
  tabCardActive: { borderColor: tc.primary, backgroundColor: tc.tint },
  tabIconWrap: {
    width: 34, height: 34, borderRadius: 10, alignItems: 'center', justifyContent: 'center',
    backgroundColor: tc.soft,
  },
  tabIconWrapActive: { backgroundColor: tc.primary },
  tabCardLabel: { fontSize: 13, fontWeight: '800', color: tc.ink },
  tabCardLabelActive: { color: tc.primary },
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
