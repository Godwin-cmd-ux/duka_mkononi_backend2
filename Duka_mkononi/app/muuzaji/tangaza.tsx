import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as WebBrowser from 'expo-web-browser';
import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { useLang } from '../../context/LanguageContext';
import { fetchWithTimeout } from '../../lib/network';
import { API_BASE_URL } from '../../constants/api';

/**
 * Capability #8 - Advertisement & Product-Copy Studio (seller mobile screen).
 *
 * Backed by POST /api/ai/ads/generate, a READ-ONLY drafting endpoint grounded
 * in the seller's own product rows. Nothing is published automatically: the
 * copy becomes an advertisement only when the seller taps Publish, which runs
 * the existing authorized workflow (POST /api/matangazo with payment pending,
 * then PesaPal initiate -> open checkout -> poll status). Drafts are stored
 * locally on the device.
 */

interface Product {
  id: string;
  name: string;
  category?: string;
  price?: number;
  stock?: number;
}

interface GenFlag {
  code: string;
  message: string;
}

interface GenerateResponse {
  success?: boolean;
  locale?: string;
  generated_at?: string;
  product?: {
    id: string;
    name: string;
    category?: string;
    price?: number;
    stock?: number;
    image_url?: string;
  };
  limits?: { headline: number; body: number };
  copy?: { headline?: string; body?: string };
  flags?: GenFlag[];
  ai?: { available?: boolean; degraded?: boolean; code?: string | null; reason?: string | null };
  limitations?: string[];
  error?: string;
}

interface Controls {
  contentType: string;
  tone: string;
  length: string;
  audience: string;
  objective: string;
  locale: string;
}

interface Draft {
  id: string;
  ts: number;
  productId: string;
  productName: string;
  image_url: string;
  controls: Controls;
  headline: string;
  body: string;
}

interface StoreUser {
  phone?: string;
  email?: string;
  full_name?: string;
  name?: string;
  business_name?: string;
}

const AD_PRICE = 3000; // mirrors config('pesapal.matangazo_price') default
const DRAFTS_KEY = 'dm_ad_drafts';

const CONTENT_TYPES = ['headline', 'description', 'product_description', 'promo'] as const;
const TONES = ['friendly', 'professional', 'urgent', 'playful'] as const;
const LENGTHS = ['short', 'medium', 'long'] as const;
const LANGS = [
  { code: 'sw', label: 'SW' },
  { code: 'en', label: 'EN' },
  { code: 'fr', label: 'FR' },
  { code: 'hi', label: 'HI' },
  { code: 'es', label: 'ES' },
  { code: 'ur', label: 'UR' },
  { code: 'de', label: 'DE' },
  { code: 'zh', label: 'ZH' },
];

export default function TangazaScreen() {
  const { t } = useLang();
  const router = useRouter();

  const [products, setProducts] = useState<Product[]>([]);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [result, setResult] = useState<GenerateResponse | null>(null);
  const [headline, setHeadline] = useState('');
  const [body, setBody] = useState('');
  const [controls, setControls] = useState<Controls>({
    contentType: 'description',
    tone: 'friendly',
    length: 'medium',
    audience: '',
    objective: '',
    locale: 'sw',
  });
  const [drafts, setDrafts] = useState<Draft[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [generating, setGenerating] = useState(false);
  const [publishing, setPublishing] = useState(false);

  const limits = result?.limits || { headline: 90, body: 480 };
  const copy = result?.copy || {};
  const hasCopy = !!(copy.headline || copy.body);
  const selectedProduct = products.find((p) => String(p.id) === String(selectedId)) || null;

  const readDrafts = useCallback(async (): Promise<Draft[]> => {
    try {
      const raw = await AsyncStorage.getItem(DRAFTS_KEY);
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }, []);

  const loadProducts = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        setLoadError(t('ad_studio.err_auth'));
        return;
      }
      const response = await fetchWithTimeout(
        `${API_BASE_URL}/api/products/my`,
        { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } },
        30000
      );
      if (response.status === 401) {
        setLoadError(t('ad_studio.err_auth'));
        return;
      }
      if (!response.ok) throw new Error('http ' + response.status);
      const data = await response.json();
      const list: Product[] = Array.isArray(data) ? data : (data?.products ?? []);
      setProducts(list);
      if (list.length > 0) {
        setSelectedId((prev) => prev ?? String(list[0].id));
      }
    } catch {
      setLoadError(t('ad_studio.err_load_failed'));
    } finally {
      setLoading(false);
    }
  }, [router, t]);

  useEffect(() => {
    let mounted = true;
    (async () => {
      const stored = await readDrafts();
      if (mounted) setDrafts(stored);
      const userData = await AsyncStorage.getItem('userData');
      let locale = 'sw';
      try {
        const parsed = userData ? JSON.parse(userData) : null;
        if (parsed && parsed.locale) locale = String(parsed.locale);
      } catch {
        // Keep the default locale.
      }
      if (mounted) setControls((prev) => ({ ...prev, locale }));
      await loadProducts();
    })();
    return () => {
      mounted = false;
    };
  }, [loadProducts, readDrafts]);

  const persistDrafts = useCallback(async (next: Draft[]) => {
    setDrafts(next);
    await AsyncStorage.setItem(DRAFTS_KEY, JSON.stringify(next.slice(0, 20)));
  }, []);

  const generateCopy = useCallback(async () => {
    if (generating) return;
    if (!selectedId) {
      Alert.alert(t('app.error'), t('ad_studio.err_pick_product'));
      return;
    }
    setGenerating(true);
    setResult(null);
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        Alert.alert(t('app.error'), t('ad_studio.err_auth'));
        return;
      }
      const response = await fetchWithTimeout(
        `${API_BASE_URL}/api/ai/ads/generate`,
        {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify({
            locale: controls.locale,
            product_id: String(selectedId),
            content_type: controls.contentType,
            tone: controls.tone,
            length: controls.length,
            audience: controls.audience.slice(0, 60),
            objective: controls.objective.slice(0, 140),
          }),
        },
        60000
      );
      if (response.status === 401) {
        Alert.alert(t('app.error'), t('ad_studio.err_auth'));
        return;
      }
      const data: GenerateResponse = await response.json().catch(() => ({}));
      if (!response.ok || !data.success) {
        Alert.alert(t('app.error'), data.error || t('ad_studio.err_network'));
        return;
      }
      setResult(data);
      setHeadline(data.copy?.headline || '');
      setBody(data.copy?.body || '');
      if (!data.copy?.headline && !data.copy?.body) {
        Alert.alert(t('ad_studio.ai_degraded_title'), data.ai?.reason || t('ad_studio.err_no_copy'));
      }
    } catch {
      Alert.alert(t('app.error'), t('ad_studio.err_network'));
    } finally {
      setGenerating(false);
    }
  }, [controls, generating, router, selectedId, t]);

  const buildDescription = useCallback(() => {
    const h = headline.trim();
    const b = body.trim();
    if (h && b) return `${h}\n\n${b}`;
    return h || b || '';
  }, [body, headline]);

  const saveDraft = useCallback(() => {
    const text = buildDescription();
    if (!text) {
      Alert.alert(t('app.error'), t('ad_studio.publish_no_copy'));
      return;
    }
    const draft: Draft = {
      id: String(Date.now()),
      ts: Date.now(),
      productId: String(selectedId || ''),
      productName: selectedProduct?.name || result?.product?.name || '',
      image_url: result?.product?.image_url || '',
      controls: { ...controls },
      headline: headline.trim(),
      body: body.trim(),
    };
    persistDrafts([draft, ...drafts]);
    Alert.alert(t('ad_studio.btn_save_draft'), t('ad_studio.draft_saved'));
  }, [buildDescription, controls, drafts, headline, body, persistDrafts, result, selectedId, selectedProduct, t]);

  const openDraft = useCallback(
    (index: number) => {
      const draft = drafts[index];
      if (!draft) return;
      setSelectedId(draft.productId);
      setControls({ ...controls, ...draft.controls });
      setHeadline(draft.headline);
      setBody(draft.body);
      setResult({
        success: true,
        product: {
          id: draft.productId,
          name: draft.productName,
          image_url: draft.image_url,
        },
        copy: { headline: draft.headline, body: draft.body },
        flags: [],
        ai: { available: true, degraded: false },
        limitations: [],
        generated_at: new Date(draft.ts).toISOString(),
      });
    },
    [controls, drafts]
  );

  const deleteDraft = useCallback(
    (index: number) => {
      const next = drafts.filter((_, i) => i !== index);
      persistDrafts(next);
    },
    [drafts, persistDrafts]
  );

  const publishCopy = useCallback(() => {
    const text = buildDescription();
    if (!text) {
      Alert.alert(t('app.error'), t('ad_studio.publish_no_copy'));
      return;
    }
    const imageUrl = result?.product?.image_url || '';
    if (!imageUrl) {
      Alert.alert(t('app.error'), t('ad_studio.publish_no_image'));
      return;
    }
    Alert.alert(t('ad_studio.btn_publish'), t('ad_studio.publish_confirm'), [
      { text: t('ad_studio.cancel'), style: 'cancel' },
      { text: t('ad_studio.btn_publish'), onPress: () => void doPublish(text, imageUrl) },
    ]);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [buildDescription, result, t]);

  const doPublish = async (text: string, imageUrl: string) => {
    if (publishing) return;
    setPublishing(true);
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        Alert.alert(t('app.error'), t('ad_studio.err_auth'));
        return;
      }
      const rawUser = await AsyncStorage.getItem('userData');
      let user: StoreUser = {};
      try {
        user = rawUser ? JSON.parse(rawUser) : {};
      } catch {
        user = {};
      }

      const response = await fetchWithTimeout(
        `${API_BASE_URL}/api/matangazo`,
        {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify({
            description: text,
            media_url: imageUrl,
            media_type: 'image',
            thumbnail_url: imageUrl,
            payment_status: 'pending',
          }),
        },
        30000
      );
      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data?.data?.id) {
        Alert.alert(t('app.error'), t('ad_studio.publish_failed'));
        return;
      }

      const matangazoId = String(data.data.id);
      try {
        const payResponse = await fetchWithTimeout(
          `${API_BASE_URL}/api/payments/pesapal/initiate`,
          {
            method: 'POST',
            headers: {
              Authorization: `Bearer ${token}`,
              'Content-Type': 'application/json',
              Accept: 'application/json',
            },
            body: JSON.stringify({
              amount: AD_PRICE,
              description: text.slice(0, 900),
              matangazo_id: matangazoId,
              phone: user.phone || '',
              email: user.email || '',
              name: user.name || user.full_name || user.business_name || '',
            }),
          },
          30000
        );
        const payData = await payResponse.json().catch(() => ({}));
        const orderTrackingId = payData?.payment?.order_tracking_id || payData?.order_tracking_id;
        if (payResponse.ok && payData.redirect_url && orderTrackingId) {
          await WebBrowser.openBrowserAsync(String(payData.redirect_url), { enableBarCollapsing: true });
          const finalStatus = await pollPaymentStatus(String(orderTrackingId), token);
          if (finalStatus === 'completed') {
            Alert.alert(t('ad_studio.btn_publish'), t('ad_studio.publish_paid'));
          } else {
            Alert.alert(t('ad_studio.btn_publish'), t('ad_studio.publish_pending'));
          }
          return;
        }
      } catch {
        // Payment could not start - the pending ad still exists; say so.
      }
      Alert.alert(t('ad_studio.btn_publish'), t('ad_studio.publish_pending'));
    } catch {
      Alert.alert(t('app.error'), t('ad_studio.publish_failed'));
    } finally {
      setPublishing(false);
    }
  };

  const pollPaymentStatus = async (orderTrackingId: string, token: string): Promise<string> => {
    for (let attempt = 0; attempt < 8; attempt++) {
      try {
        const response = await fetchWithTimeout(
          `${API_BASE_URL}/api/payments/pesapal/status/${orderTrackingId}`,
          {
            method: 'GET',
            headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
          },
          20000
        );
        if (response.ok) {
          const data = await response.json();
          const status = data?.payment?.status || 'pending';
          if (status === 'completed' || status === 'failed') return String(status);
        }
      } catch {
        // Transient status check - retry.
      }
      await new Promise((resolve) => setTimeout(resolve, 3000));
    }
    return 'pending';
  };

  const chipRow = (
    labelKey: string,
    options: readonly string[],
    labelFor: (value: string) => string,
    active: string,
    onSelect: (value: string) => void
  ) => (
    <View style={styles.fieldBlock}>
      <Text style={styles.fieldLabel}>{t(`ad_studio.${labelKey}`)}</Text>
      <View style={styles.chipRow}>
        {options.map((option) => {
          const selected = option === active;
          return (
            <TouchableOpacity
              key={option}
              activeOpacity={0.8}
              style={[styles.chip, selected && styles.chipSelected]}
              onPress={() => onSelect(option)}
            >
              <Text style={[styles.chipText, selected && styles.chipTextSelected]}>{labelFor(option)}</Text>
            </TouchableOpacity>
          );
        })}
      </View>
    </View>
  );

  const renderPreview = () => {
    if (!result) return null;
    const ai = result.ai || {};
    const flags = result.flags || [];
    const imageUrl = result.product?.image_url;

    return (
      <View style={styles.card}>
        <Text style={styles.cardTitle}>{t('ad_studio.section_preview')}</Text>
        <Text style={styles.sectionHint}>{t('ad_studio.preview_hint')}</Text>

        <View style={[styles.banner, ai.available && hasCopy ? styles.bannerOk : styles.bannerWarn]}>
          <Ionicons name="sparkles" size={14} color={ai.available && hasCopy ? '#1e8449' : '#b9770e'} />
          <Text style={[styles.bannerText, { color: ai.available && hasCopy ? '#1e8449' : '#b9770e' }]}>
            {ai.available && hasCopy
              ? t('ad_studio.ai_ready')
              : `${t('ad_studio.ai_degraded_title')}${ai.reason ? ' — ' + ai.reason : ''}`}
          </Text>
        </View>

        {imageUrl ? <Image source={{ uri: imageUrl }} style={styles.previewMedia} resizeMode="cover" /> : null}
        <Text style={styles.prodMeta}>
          <Text style={styles.prodName}>{result.product?.name || ''}</Text>
          {' · '}
          {t('ad_studio.label_price')}: {Number(result.product?.price || 0).toLocaleString('en-TZ')} ·{' '}
          {t('ad_studio.label_stock')}: {String(result.product?.stock ?? '-')}
        </Text>

        {flags.map((flag) => (
          <View key={flag.code} style={styles.flagRow}>
            <Ionicons name="warning" size={14} color="#b9770e" />
            <Text style={styles.flagText}>{flag.message || flag.code}</Text>
          </View>
        ))}

        {controls.contentType !== 'product_description' ? (
          <View style={styles.fieldBlock}>
            <View style={styles.labelRow}>
              <Text style={styles.fieldLabel}>{t('ad_studio.lbl_headline')}</Text>
              <Text style={[styles.counter, headline.length > limits.headline && styles.counterOver]}>
                {headline.length}/{limits.headline}
              </Text>
            </View>
            <TextInput
              style={[styles.previewInput, styles.headlineInput]}
              value={headline}
              onChangeText={setHeadline}
              multiline
              placeholder={t('ad_studio.ph_headline')}
              placeholderTextColor="#bdc3c7"
            />
          </View>
        ) : null}

        {controls.contentType !== 'headline' ? (
          <View style={styles.fieldBlock}>
            <View style={styles.labelRow}>
              <Text style={styles.fieldLabel}>{t('ad_studio.lbl_body')}</Text>
              <Text style={[styles.counter, body.length > limits.body && styles.counterOver]}>
                {body.length}/{limits.body}
              </Text>
            </View>
            <TextInput
              style={[styles.previewInput, styles.bodyInput]}
              value={body}
              onChangeText={setBody}
              multiline
              placeholder={t('ad_studio.ph_body')}
              placeholderTextColor="#bdc3c7"
            />
          </View>
        ) : null}

        <TouchableOpacity
          style={[styles.btn, styles.btnPrimary, (!hasCopy || publishing) && styles.btnDisabled]}
          disabled={!hasCopy || publishing}
          onPress={publishCopy}
          activeOpacity={0.85}
        >
          <Ionicons name="megaphone" size={16} color="#fff" />
          <Text style={styles.btnText}>{t('ad_studio.btn_publish')}</Text>
        </TouchableOpacity>

        <View style={styles.actionRow}>
          <TouchableOpacity style={[styles.btn, styles.btnAccent]} disabled={!hasCopy} onPress={saveDraft} activeOpacity={0.85}>
            <Ionicons name="save" size={16} color="#fff" />
            <Text style={styles.btnText}>{t('ad_studio.btn_save_draft')}</Text>
          </TouchableOpacity>
          <TouchableOpacity style={[styles.btn, styles.btnGhost]} disabled={generating} onPress={generateCopy} activeOpacity={0.85}>
            <Ionicons name="refresh" size={16} color="#2c3e50" />
            <Text style={[styles.btnText, { color: '#2c3e50' }]}>{t('ad_studio.btn_regenerate')}</Text>
          </TouchableOpacity>
        </View>

        {result.generated_at ? (
          <Text style={styles.generated}>
            {t('ad_studio.generated_at', { date: new Date(result.generated_at).toLocaleString() })}
          </Text>
        ) : null}

        {(result.limitations || []).length > 0 ? (
          <View style={styles.limitBox}>
            <Text style={styles.cardTitle}>{t('ad_studio.limitations_title')}</Text>
            {(result.limitations || []).map((line) => (
              <View key={line} style={styles.limitItem}>
                <Ionicons name="information-circle-outline" size={14} color="#bdc3c7" />
                <Text style={styles.limitText}>{line}</Text>
              </View>
            ))}
          </View>
        ) : null}
      </View>
    );
  };

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <View style={styles.header}>
        <TouchableOpacity style={styles.backBtn} onPress={() => router.push('/muuzaji/huduma-nyingine')} accessibilityRole="button">
          <Ionicons name="arrow-back" size={22} color="#2c3e50" />
        </TouchableOpacity>
        <View style={styles.headerText}>
          <Text style={styles.title}>{t('ad_studio.title')}</Text>
          <Text style={styles.subtitle}>{t('ad_studio.subtitle')}</Text>
        </View>
      </View>

      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
        {/* Capability #16 - content review: findings + a recorded human decision */}
        <ContentReviewCard />
        {loading ? (
          <View style={styles.centerBox}>
            <ActivityIndicator size="large" color="#2ecc71" />
            <Text style={styles.loadingText}>{t('ad_studio.loading')}</Text>
          </View>
        ) : loadError && products.length === 0 ? (
          <View style={styles.centerBox}>
            <Ionicons name="cloud-offline-outline" size={44} color="#bdc3c7" />
            <Text style={styles.emptyTitle}>{loadError}</Text>
            <TouchableOpacity style={[styles.btn, styles.btnPrimary]} onPress={loadProducts}>
              <Text style={styles.btnText}>{t('ad_studio.btn_retry')}</Text>
            </TouchableOpacity>
          </View>
        ) : products.length === 0 ? (
          <View style={styles.centerBox}>
            <Ionicons name="cube-outline" size={44} color="#bdc3c7" />
            <Text style={styles.emptyTitle}>{t('ad_studio.no_products_title')}</Text>
            <Text style={styles.emptyText}>{t('ad_studio.no_products_text')}</Text>
            <TouchableOpacity style={[styles.btn, styles.btnPrimary]} onPress={() => router.push('/muuzaji/uza')}>
              <Text style={styles.btnText}>{t('ad_studio.btn_go_sell')}</Text>
            </TouchableOpacity>
          </View>
        ) : (
          <>
            <View style={styles.card}>
              <Text style={styles.cardTitle}>{t('ad_studio.section_product')}</Text>
              <Text style={styles.sectionHint}>{t('ad_studio.product_pick_hint')}</Text>
              {products.map((product) => {
                const selected = String(product.id) === String(selectedId);
                const out = (Number(product.stock) || 0) <= 0;
                return (
                  <TouchableOpacity
                    key={String(product.id)}
                    activeOpacity={0.8}
                    style={[styles.productOption, selected && styles.productSelected]}
                    onPress={() => setSelectedId(String(product.id))}
                  >
                    <View style={[styles.radio, selected && styles.radioOn]}>{selected ? <View style={styles.radioDot} /> : null}</View>
                    <View style={styles.productMain}>
                      <View style={styles.productRow}>
                        <Text style={styles.productName}>{product.name}</Text>
                        <Text style={[styles.stockPill, out && styles.stockOut]}>
                          {out ? t('ad_studio.label_out_of_stock') : `${t('ad_studio.label_stock')}: ${String(product.stock ?? 0)}`}
                        </Text>
                      </View>
                      <Text style={styles.productMeta}>
                        {t('ad_studio.label_category')}: {product.category || '-'}
                      </Text>
                    </View>
                    <Text style={styles.productPrice}>
                      {Number(product.price || 0).toLocaleString('en-TZ')}
                    </Text>
                  </TouchableOpacity>
                );
              })}
            </View>

            <View style={styles.card}>
              <Text style={styles.cardTitle}>{t('ad_studio.section_controls')}</Text>
              {chipRow(
                'label_content_type',
                CONTENT_TYPES,
                (value) => t(`ad_studio.ct_${value}`),
                controls.contentType,
                (value) => setControls((prev) => ({ ...prev, contentType: value }))
              )}
              {chipRow(
                'label_tone',
                TONES,
                (value) => t(`ad_studio.tone_${value}`),
                controls.tone,
                (value) => setControls((prev) => ({ ...prev, tone: value }))
              )}
              {chipRow(
                'label_length',
                LENGTHS,
                (value) => t(`ad_studio.len_${value}`),
                controls.length,
                (value) => setControls((prev) => ({ ...prev, length: value }))
              )}
              <View style={styles.fieldBlock}>
                <Text style={styles.fieldLabel}>{t('ad_studio.label_language')}</Text>
                <View style={styles.chipRow}>
                  {LANGS.map((lang) => {
                    const selected = lang.code === controls.locale;
                    return (
                      <TouchableOpacity
                        key={lang.code}
                        activeOpacity={0.8}
                        style={[styles.chip, selected && styles.chipSelected]}
                        onPress={() => setControls((prev) => ({ ...prev, locale: lang.code }))}
                      >
                        <Text style={[styles.chipText, selected && styles.chipTextSelected]}>{lang.label}</Text>
                      </TouchableOpacity>
                    );
                  })}
                </View>
              </View>
              <View style={styles.fieldBlock}>
                <Text style={styles.fieldLabel}>{t('ad_studio.label_audience')}</Text>
                <TextInput
                  style={styles.input}
                  value={controls.audience}
                  onChangeText={(value) => setControls((prev) => ({ ...prev, audience: value }))}
                  maxLength={60}
                  placeholder={t('ad_studio.ph_audience')}
                  placeholderTextColor="#bdc3c7"
                />
              </View>
              <View style={styles.fieldBlock}>
                <Text style={styles.fieldLabel}>{t('ad_studio.label_objective')}</Text>
                <TextInput
                  style={styles.input}
                  value={controls.objective}
                  onChangeText={(value) => setControls((prev) => ({ ...prev, objective: value }))}
                  maxLength={140}
                  placeholder={t('ad_studio.ph_objective')}
                  placeholderTextColor="#bdc3c7"
                />
              </View>
              <TouchableOpacity
                style={[styles.btn, styles.btnPrimary, generating && styles.btnDisabled]}
                disabled={generating}
                onPress={generateCopy}
                activeOpacity={0.85}
              >
                {generating ? <ActivityIndicator size="small" color="#fff" /> : <Ionicons name="sparkles" size={16} color="#fff" />}
                <Text style={styles.btnText}>{generating ? t('ad_studio.btn_generating') : t('ad_studio.btn_generate')}</Text>
              </TouchableOpacity>
            </View>

            {renderPreview()}

            <View style={styles.card}>
              <Text style={styles.cardTitle}>{t('ad_studio.section_drafts')}</Text>
              {drafts.length === 0 ? (
                <Text style={styles.sectionHint}>{t('ad_studio.drafts_empty')}</Text>
              ) : (
                drafts.map((draft, index) => (
                  <View key={draft.id} style={styles.draftRow}>
                    <View style={styles.draftMain}>
                      <Text style={styles.draftTitle} numberOfLines={1}>
                        {draft.headline || draft.body || ''}
                      </Text>
                      <Text style={styles.draftMeta}>
                        {draft.productName} · {new Date(draft.ts).toLocaleString()}
                      </Text>
                    </View>
                    <TouchableOpacity style={[styles.miniBtn, styles.miniLoad]} onPress={() => openDraft(index)}>
                      <Text style={styles.miniLoadText}>{t('ad_studio.draft_load')}</Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={[styles.miniBtn, styles.miniDel]} onPress={() => deleteDraft(index)}>
                      <Text style={styles.miniDelText}>{t('ad_studio.draft_delete')}</Text>
                    </TouchableOpacity>
                  </View>
                ))
              )}
            </View>
          </>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#f8f9fa' },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#ecf0f1',
  },
  backBtn: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#f5f5f5',
    alignItems: 'center',
    justifyContent: 'center',
  },
  headerText: { flex: 1 },
  title: { fontSize: 18, fontWeight: '800', color: '#2c3e50' },
  subtitle: { fontSize: 12, color: '#7f8c8d', marginTop: 2, lineHeight: 17 },
  content: { padding: 16, paddingBottom: 40 },
  centerBox: { alignItems: 'center', paddingVertical: 50, paddingHorizontal: 12 },
  loadingText: { marginTop: 12, color: '#7f8c8d', fontSize: 14 },
  emptyTitle: { marginTop: 12, fontSize: 17, fontWeight: '700', color: '#2c3e50', textAlign: 'center' },
  emptyText: { marginTop: 8, fontSize: 14, color: '#7f8c8d', textAlign: 'center', lineHeight: 20 },
  card: {
    backgroundColor: '#fff',
    borderRadius: 18,
    padding: 16,
    marginBottom: 14,
  },
  cardTitle: { fontSize: 15, fontWeight: '800', color: '#2c3e50', marginBottom: 8 },
  sectionHint: { fontSize: 12, color: '#95a5a6', marginBottom: 10, lineHeight: 17 },
  productOption: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderWidth: 2,
    borderColor: '#ecf0f1',
    borderRadius: 14,
    padding: 12,
    marginBottom: 8,
    backgroundColor: '#fff',
  },
  productSelected: { borderColor: '#2ecc71', backgroundColor: '#e8f8f0' },
  radio: {
    width: 18,
    height: 18,
    borderRadius: 9,
    borderWidth: 2,
    borderColor: '#bdc3c7',
    alignItems: 'center',
    justifyContent: 'center',
  },
  radioOn: { borderColor: '#2ecc71' },
  radioDot: { width: 8, height: 8, borderRadius: 4, backgroundColor: '#2ecc71' },
  productMain: { flex: 1, minWidth: 0 },
  productRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  productName: { fontSize: 14, fontWeight: '700', color: '#2c3e50', flexShrink: 1 },
  productMeta: { fontSize: 12, color: '#7f8c8d', marginTop: 2 },
  productPrice: { fontSize: 14, fontWeight: '800', color: '#27ae60' },
  stockPill: {
    fontSize: 10,
    fontWeight: '800',
    color: '#1e8449',
    backgroundColor: '#e8f8f0',
    borderRadius: 20,
    paddingHorizontal: 8,
    paddingVertical: 2,
    overflow: 'hidden',
  },
  stockOut: { color: '#c0392b', backgroundColor: '#fdeaea' },
  fieldBlock: { marginBottom: 14 },
  labelRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 },
  fieldLabel: { fontSize: 12, fontWeight: '800', color: '#5d6d7e', marginBottom: 6 },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: {
    borderWidth: 1.5,
    borderColor: '#e0e0e0',
    borderRadius: 20,
    paddingHorizontal: 14,
    paddingVertical: 8,
    backgroundColor: '#fff',
  },
  chipSelected: { backgroundColor: '#2ecc71', borderColor: '#2ecc71' },
  chipText: { fontSize: 13, fontWeight: '600', color: '#5d6d7e' },
  chipTextSelected: { color: '#fff' },
  input: {
    borderWidth: 1.5,
    borderColor: '#e0e0e0',
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    color: '#2c3e50',
    backgroundColor: '#fff',
  },
  btn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    borderRadius: 12,
    paddingVertical: 12,
    paddingHorizontal: 18,
    marginTop: 4,
  },
  btnPrimary: { backgroundColor: '#2ecc71' },
  btnAccent: { backgroundColor: '#2980b9' },
  btnGhost: { backgroundColor: '#f5f5f5' },
  btnDisabled: { opacity: 0.55 },
  btnText: { fontSize: 14, fontWeight: '800', color: '#fff' },
  actionRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginTop: 10 },
  banner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    borderRadius: 12,
    padding: 10,
    marginBottom: 12,
  },
  bannerOk: { backgroundColor: '#e8f8f0' },
  bannerWarn: { backgroundColor: '#fef5e7' },
  bannerText: { fontSize: 12.5, fontWeight: '700', flex: 1 },
  previewMedia: { width: '100%', height: 190, borderRadius: 14, marginBottom: 12, backgroundColor: '#ecf0f1' },
  prodMeta: { fontSize: 12.5, color: '#7f8c8d', marginBottom: 10 },
  prodName: { fontWeight: '800', color: '#2c3e50' },
  flagRow: {
    flexDirection: 'row',
    gap: 8,
    backgroundColor: '#fef5e7',
    borderRadius: 10,
    padding: 9,
    marginBottom: 8,
  },
  flagText: { flex: 1, fontSize: 12.5, fontWeight: '600', color: '#b9770e', lineHeight: 17 },
  previewInput: {
    borderWidth: 1.5,
    borderColor: '#e0e0e0',
    borderRadius: 12,
    padding: 12,
    fontSize: 14,
    color: '#2c3e50',
    backgroundColor: '#fdfefe',
    textAlignVertical: 'top',
  },
  headlineInput: { fontSize: 16, fontWeight: '700', minHeight: 54 },
  bodyInput: { minHeight: 130, lineHeight: 21 },
  counter: { fontSize: 11, color: '#95a5a6', fontVariant: ['tabular-nums'] },
  counterOver: { color: '#e74c3c', fontWeight: '800' },
  generated: { fontSize: 11, color: '#bdc3c7', textAlign: 'center', marginTop: 10 },
  limitBox: { borderTopWidth: 1, borderTopColor: '#ecf0f1', marginTop: 14, paddingTop: 12 },
  limitItem: { flexDirection: 'row', gap: 8, marginBottom: 6, alignItems: 'flex-start' },
  limitText: { flex: 1, fontSize: 12.5, color: '#7f8c8d', lineHeight: 18 },
  draftRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    borderWidth: 1,
    borderColor: '#ecf0f1',
    borderRadius: 14,
    padding: 10,
    marginBottom: 8,
  },
  draftMain: { flex: 1, minWidth: 0 },
  draftTitle: { fontSize: 13, fontWeight: '700', color: '#2c3e50' },
  draftMeta: { fontSize: 11, color: '#95a5a6', marginTop: 2 },
  miniBtn: { borderRadius: 9, paddingHorizontal: 10, paddingVertical: 7 },
  miniLoad: { backgroundColor: '#eaf4fc' },
  miniLoadText: { fontSize: 12, fontWeight: '800', color: '#2980b9' },
  miniDel: { backgroundColor: '#fdeaea' },
  miniDelText: { fontSize: 12, fontWeight: '800', color: '#c0392b' },
});

// ===================== CONTENT REVIEW (capability #16) =====================
// A moderation AID, not an authority: the findings come from the server's
// deterministic checks (with an optional advisory AI note on flagged claims),
// and every approve / reject / request-edits press is a HUMAN decision that
// the server re-verifies and records.
type ReviewFinding = {
  category: string;
  severity: string;
  message: string;
  excerpt?: string;
  ai?: { verdict: string; reason: string };
};

type ReviewItem = {
  type: 'ad' | 'product';
  id: string;
  title: string;
  subtitle: string;
  is_active: boolean;
  findings: ReviewFinding[];
  violations: number;
  concerns: number;
};

function ContentReviewCard() {
  const { t, lang } = useLang();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(false);
  const [tab, setTab] = useState<'ads' | 'products'>('ads');
  const [decisions, setDecisions] = useState<Record<string, string>>({});
  const [notes, setNotes] = useState<Record<string, string>>({});
  const [reviewed, setReviewed] = useState<Record<string, boolean>>({});
  const [submitting, setSubmitting] = useState(false);

  const runCheck = useCallback(async () => {
    const token = await AsyncStorage.getItem('userToken');
    if (!token) return;
    setLoading(true);
    try {
      const res = await fetchWithTimeout(
        `${API_BASE_URL}/api/ai/reviews/check`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'ngrok-skip-browser-warning': 'true'
          },
          body: JSON.stringify({ target: 'all', locale: lang })
        },
        25000
      );
      if (!res.ok) throw new Error('review failed');
      const json = await res.json();
      if (!json || !json.success) throw new Error('review failed');
      setData(json);
    } catch {
      setData(null);
    } finally {
      setLoading(false);
    }
  }, [lang]);

  useEffect(() => {
    runCheck();
    // First open only - the check button re-runs it.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const submitDecision = async (item: ReviewItem) => {
    const decision = decisions[item.id];
    if (!decision) {
      Alert.alert(t('content_review.alert'), t('content_review.dec_pick'));
      return;
    }
    const token = await AsyncStorage.getItem('userToken');
    if (!token) return;
    setSubmitting(true);
    try {
      const res = await fetchWithTimeout(
        `${API_BASE_URL}/api/ai/reviews/decision`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'ngrok-skip-browser-warning': 'true'
          },
          body: JSON.stringify({
            target_type: item.type,
            target_id: item.id,
            decision,
            note: (notes[item.id] || '').trim(),
            reviewed: !!reviewed[item.id]
          })
        },
        20000
      );
      const json = await res.json().catch(() => null);
      if (!res.ok) {
        Alert.alert(t('content_review.alert'), (json && json.error) || t('content_review.err_check'));
        return;
      }
      setData((prev: any) => prev ? {
        ...prev,
        items: (prev.items || []).map((i: ReviewItem) =>
          i.id === item.id && json.target ? { ...i, is_active: json.target.is_active } : i)
      } : prev);
      setDecisions((prev) => ({ ...prev, [item.id]: '' }));
      Alert.alert(t('content_review.alert'), t('content_review.dec_done'));
    } catch {
      Alert.alert(t('content_review.alert'), t('content_review.err_check'));
    } finally {
      setSubmitting(false);
    }
  };

  const ads = ((data && data.items) || []).filter((i: ReviewItem) => i.type === 'ad');
  const products = ((data && data.items) || []).filter((i: ReviewItem) => i.type === 'product');
  const items = tab === 'ads' ? ads : products;

  const renderFinding = (f: ReviewFinding, index: number) => {
    const violation = f.severity === 'violation';
    return (
      <View key={index} style={revStyles.findingBox}>
        <View style={revStyles.findingHead}>
          <View style={[revStyles.sevChip, { backgroundColor: violation ? '#e74c3c18' : '#f39c1218' }]}>
            <Text style={[revStyles.sevText, { color: violation ? '#e74c3c' : '#b9770e' }]}>
              {violation ? t('content_review.sev_violation') : t('content_review.sev_concern')}
            </Text>
          </View>
          <Text style={revStyles.findingMsg}>{f.message}</Text>
        </View>
        {!!f.excerpt && <Text style={revStyles.excerpt}>\u201c{f.excerpt}\u201d</Text>}
        {!!f.ai && (
          <Text style={revStyles.aiNote}>
            {t('content_review.verdict_label')}: {t(`content_review.verdict_${f.ai.verdict}`)}{f.ai.reason ? ` \u2014 ${f.ai.reason}` : ''}
          </Text>
        )}
      </View>
    );
  };

  const renderDecisionItem = (item: ReviewItem) => {
    const decision = decisions[item.id] || '';
    const choice = (val: string, label: string, color: string) => (
      <TouchableOpacity
        key={val}
        style={[revStyles.choiceChip, decision === val && { borderColor: color, backgroundColor: color + '18' }]}
        disabled={submitting}
        onPress={() => setDecisions((prev) => ({ ...prev, [item.id]: val }))}
      >
        <Text style={[revStyles.choiceText, decision === val && { color }]}>{label}</Text>
      </TouchableOpacity>
    );
    const submitLabel = decision === 'approve'
      ? t('content_review.dec_approve')
      : decision === 'reject'
        ? t('content_review.dec_reject')
        : decision === 'request_edit'
          ? t('content_review.dec_edit')
          : t('content_review.dec_title');

    return (
      <View key={item.id} style={revStyles.itemCard}>
        <View style={revStyles.itemHead}>
          <Text style={revStyles.itemTitle} numberOfLines={1}>{item.title || '-'}</Text>
          <View style={[revStyles.stateChip, { backgroundColor: item.is_active ? '#27ae6018' : '#95a5a622' }]}>
            <Text style={[revStyles.stateText, { color: item.is_active ? '#27ae60' : '#7f8c8d' }]}>
              {item.is_active ? t('content_review.active_label') : t('content_review.inactive_label')}
            </Text>
          </View>
        </View>
        {!!item.subtitle && <Text style={revStyles.itemSub}>{item.subtitle}</Text>}
        <View style={revStyles.countRow}>
          <Text style={[revStyles.countBadge, { backgroundColor: '#e74c3c12', color: '#e74c3c' }]}>
            {t('content_review.stat_violations')}: {item.violations}
          </Text>
          <Text style={[revStyles.countBadge, { backgroundColor: '#f39c1215', color: '#b9770e' }]}>
            {t('content_review.stat_concerns')}: {item.concerns}
          </Text>
        </View>

        {item.findings.length > 0
          ? item.findings.map(renderFinding)
          : <Text style={revStyles.noFindings}>\u2713 {t('content_review.no_findings')}</Text>}

        <View style={revStyles.choiceRow}>
          {choice('approve', t('content_review.dec_approve'), '#27ae60')}
          {choice('reject', t('content_review.dec_reject'), '#e74c3c')}
          {choice('request_edit', t('content_review.dec_edit'), '#f39c12')}
        </View>
        <TextInput
          style={revStyles.noteInput}
          value={notes[item.id] || ''}
          onChangeText={(v) => setNotes((prev) => ({ ...prev, [item.id]: v }))}
          placeholder={t('content_review.dec_note_placeholder')}
          placeholderTextColor="#95a5a6"
          maxLength={300}
        />
        <TouchableOpacity
          style={revStyles.checkRow}
          onPress={() => setReviewed((prev) => ({ ...prev, [item.id]: !prev[item.id] }))}
        >
          <View style={[revStyles.checkbox, reviewed[item.id] && revStyles.checkboxOn]}>
            {reviewed[item.id] ? <Text style={revStyles.checkMark}>\u2713</Text> : null}
          </View>
          <Text style={revStyles.checkLabel}>{t('content_review.reviewed_confirm')}</Text>
        </TouchableOpacity>
        <TouchableOpacity style={revStyles.submitBtn} disabled={submitting} onPress={() => submitDecision(item)}>
          <Text style={revStyles.submitText}>{submitLabel}</Text>
        </TouchableOpacity>
      </View>
    );
  };

  return (
    <View style={revStyles.card}>
      <View style={revStyles.headerRow}>
        <Text style={revStyles.sparkle}>✨</Text>
        <Text style={revStyles.title}>{t('content_review.title')}</Text>
        <TouchableOpacity style={revStyles.checkBtn} disabled={loading} onPress={runCheck}>
          <Text style={revStyles.checkBtnText}>{loading ? t('content_review.checking') : t('content_review.btn_check')}</Text>
        </TouchableOpacity>
      </View>
      <Text style={revStyles.subtitle}>{t('content_review.subtitle')}</Text>

      {loading ? (
        <View style={revStyles.centerBox}>
          <ActivityIndicator size="small" color="#2980b9" />
          <Text style={revStyles.muted}>{t('content_review.checking')}</Text>
        </View>
      ) : !data ? (
        <View style={revStyles.centerBox}>
          <Text style={revStyles.errorText}>{t('content_review.err_check')}</Text>
        </View>
      ) : ((data.items || []).length === 0) ? (
        <Text style={revStyles.muted}>{t('content_review.no_items')}</Text>
      ) : (
        <>
          <View style={revStyles.statsRow}>
            {([
              [data.stats?.items_checked ?? 0, t('content_review.stat_checked'), '#2c3e50'],
              [data.stats?.violations ?? 0, t('content_review.stat_violations'), '#e74c3c'],
              [data.stats?.concerns ?? 0, t('content_review.stat_concerns'), '#b9770e'],
              [data.stats?.clean ?? 0, t('content_review.stat_clean'), '#27ae60']
            ] as [number, string, string][]).map(([value, label, color]) => (
              <View key={label} style={revStyles.statBox}>
                <Text style={[revStyles.statValue, { color }]}>{String(value)}</Text>
                <Text style={revStyles.statLabel}>{label}</Text>
              </View>
            ))}
          </View>

          <View style={revStyles.tabRow}>
            {([
              ['ads', t('content_review.tab_ads', { count: ads.length })],
              ['products', t('content_review.tab_products', { count: products.length })]
            ] as [string, string][]).map(([key, label]) => (
              <TouchableOpacity key={key} style={[revStyles.tabChip, tab === key && revStyles.tabChipActive]} onPress={() => setTab(key as 'ads' | 'products')}>
                <Text style={[revStyles.tabText, tab === key && revStyles.tabTextActive]}>{label}</Text>
              </TouchableOpacity>
            ))}
          </View>

          {items.length > 0 ? items.map(renderDecisionItem) : <Text style={revStyles.muted}>{t('content_review.no_items')}</Text>}
          <Text style={revStyles.footnote}>{t('content_review.advisory_note')}</Text>
        </>
      )}
    </View>
  );
}

const revStyles = StyleSheet.create({
  card: { backgroundColor: 'white', borderRadius: 16, padding: 16, marginBottom: 16, borderLeftWidth: 4, borderLeftColor: '#2980b9' },
  headerRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  sparkle: { fontSize: 15 },
  title: { flex: 1, fontSize: 15.5, fontWeight: '800', color: '#2c3e50' },
  checkBtn: { paddingHorizontal: 14, paddingVertical: 7, borderRadius: 20, backgroundColor: '#2980b9' },
  checkBtnText: { color: 'white', fontSize: 12, fontWeight: '700' },
  subtitle: { fontSize: 11.5, color: '#7f8c8d', marginTop: 2 },
  centerBox: { alignItems: 'center', paddingVertical: 16, gap: 6 },
  muted: { fontSize: 12.5, color: '#7f8c8d', textAlign: 'center' },
  errorText: { fontSize: 13, color: '#e74c3c', textAlign: 'center' },
  statsRow: { flexDirection: 'row', gap: 8, marginTop: 12, flexWrap: 'wrap' },
  statBox: { flexGrow: 1, minWidth: 70, backgroundColor: '#f8f9fa', borderRadius: 12, paddingVertical: 8, paddingHorizontal: 6, alignItems: 'center' },
  statValue: { fontSize: 15, fontWeight: '800' },
  statLabel: { fontSize: 9.5, color: '#7f8c8d', marginTop: 1 },
  tabRow: { flexDirection: 'row', gap: 8, marginTop: 12 },
  tabChip: { paddingHorizontal: 14, paddingVertical: 7, borderRadius: 20, backgroundColor: '#f4f6f7' },
  tabChipActive: { backgroundColor: '#2980b9' },
  tabText: { fontSize: 12, fontWeight: '700', color: '#5d6d7e' },
  tabTextActive: { color: 'white' },
  itemCard: { backgroundColor: '#fafbfc', borderRadius: 14, padding: 12, marginTop: 10, borderWidth: 1, borderColor: '#f1f3f4' },
  itemHead: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  itemTitle: { flex: 1, fontSize: 13.5, fontWeight: '700', color: '#2c3e50' },
  stateChip: { paddingHorizontal: 9, paddingVertical: 3, borderRadius: 10 },
  stateText: { fontSize: 10.5, fontWeight: '700' },
  itemSub: { fontSize: 11.5, color: '#7f8c8d', marginTop: 2 },
  countRow: { flexDirection: 'row', gap: 6, marginTop: 6 },
  countBadge: { fontSize: 11, fontWeight: '700', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 10, overflow: 'hidden' },
  findingBox: { borderTopWidth: 1, borderTopColor: '#ecf0f1', borderStyle: 'dashed', marginTop: 8, paddingTop: 8 },
  findingHead: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
  sevChip: { paddingHorizontal: 9, paddingVertical: 3, borderRadius: 10 },
  sevText: { fontSize: 10.5, fontWeight: '700' },
  findingMsg: { flex: 1, fontSize: 12.5, color: '#2c3e50' },
  excerpt: { fontSize: 11, color: '#7f8c8d', backgroundColor: '#f8f9fa', borderLeftWidth: 3, borderLeftColor: '#dfe6e9', marginTop: 4, paddingHorizontal: 8, paddingVertical: 4, borderRadius: 8 },
  aiNote: { fontSize: 11, color: '#2980b9', marginTop: 4 },
  noFindings: { fontSize: 12, color: '#27ae60', marginTop: 8 },
  choiceRow: { flexDirection: 'row', gap: 8, marginTop: 10 },
  choiceChip: { flex: 1, alignItems: 'center', paddingVertical: 8, borderRadius: 10, borderWidth: 1.5, borderColor: '#ecf0f1', backgroundColor: 'white' },
  choiceText: { fontSize: 11.5, fontWeight: '700', color: '#7f8c8d', textAlign: 'center' },
  noteInput: { marginTop: 8, borderWidth: 1, borderColor: '#ecf0f1', borderRadius: 10, paddingHorizontal: 12, paddingVertical: 9, fontSize: 12.5, color: '#2c3e50', backgroundColor: 'white' },
  checkRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 10 },
  checkbox: { width: 18, height: 18, borderRadius: 5, borderWidth: 1.5, borderColor: '#bdc3c7', alignItems: 'center', justifyContent: 'center', backgroundColor: 'white' },
  checkboxOn: { backgroundColor: '#2980b9', borderColor: '#2980b9' },
  checkMark: { color: 'white', fontSize: 12, fontWeight: '800' },
  checkLabel: { flex: 1, fontSize: 12, color: '#5d6d7e' },
  submitBtn: { marginTop: 10, borderRadius: 12, backgroundColor: '#2980b9', paddingVertical: 11, alignItems: 'center' },
  submitText: { color: 'white', fontSize: 13, fontWeight: '700' },
  footnote: { fontSize: 10, color: '#95a5a6', marginTop: 10 }
});
