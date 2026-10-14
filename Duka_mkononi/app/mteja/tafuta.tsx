import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Image,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import SmartCartLoader from '../../components/SmartCartLoader';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLang } from '../../context/LanguageContext';
import { fetchWithTimeout } from '../../lib/network';

import { API_BASE_URL } from '../../constants/api';

/**
 * Capability #11 - Conversational Shopping Assistant (customer screen, "Tafuta").
 *
 * POST /api/ai/shopping/ask interprets a natural-language request (Swahili,
 * English or mixed) against the PUBLIC catalogue only: every product and
 * business shown here comes from PublicCatalog, so public-visibility and
 * availability rules are already applied server-side. The screen adds no
 * data of its own - it renders the answer, the real clarification options
 * the server found, and the matched rows with price, stock and business
 * identity. GET /api/ai/shopping/suggestions powers the example chips.
 *
 * The route is public (anonymous shoppers can search too); when a customer
 * JWT is stored we attach it so the server can remember the last turn for
 * short follow-ups like "chini ya 20000".
 */

interface BusinessInfo {
  id: string | null;
  name: string | null;
  location: string | null;
  avatar: string | null;
  certified: boolean;
}

interface ProductResult {
  id: string | null;
  name: string;
  category: string | null;
  description: string | null;
  selling_price: number;
  currency: string;
  unit: string | null;
  image: string | null;
  available: boolean;
  business: BusinessInfo;
  match: { score: number; reason: string | null; type: string };
}

interface BusinessResult {
  id: string | null;
  name: string;
  location: string | null;
  type: string | null;
  description: string | null;
  avatar: string | null;
  certified: boolean;
  product_count: number;
  match: { score: number; reason: string | null };
}

interface ClarifyOption {
  type: string;
  label: string;
  category?: string;
  business_id?: string;
  count?: number;
}

interface Interpreted {
  terms: string[];
  phrase: string | null;
  category: string | null;
  location: string | null;
  price_min: number | null;
  price_max: number | null;
  in_stock: boolean | null;
  sort: string;
  source: string;
  merged_with_previous: boolean;
  business_id: string | null;
}

interface AskResponse {
  success: boolean;
  locale: string;
  currency: string;
  generated_at: string;
  query: string;
  interpreted: Interpreted;
  answer: { role: string; kind: string; text: string; at: string };
  products: ProductResult[];
  businesses: BusinessResult[];
  clarification: { question: string; options: ClarifyOption[] } | null;
  categories: { category: string; count: number }[];
  limitations: string[];
  notes: string[];
}

type Refine = { category?: string; business_id?: string };
type Sort = 'relevance' | 'price_asc' | 'price_desc';

const groupDigits = (value: number): string =>
  Math.round(value)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, ',');

/* ---------------- capability #14 - recommendations ---------------- */

interface RecoReason {
  code: string;
  text: string;
}

interface RecoItem {
  id: string;
  name?: string;
  business_name?: string;
  selling_price?: number;
  unit?: string;
  image?: string | null;
  business_location?: string | null;
  reason?: RecoReason;
}

interface RecoPayload {
  success: boolean;
  products: RecoItem[];
  businesses: RecoItem[];
  meta?: {
    personalized?: boolean;
    note?: string | null;
    privacy?: string | null;
  };
}

/**
 * Capability #14 - Personalised Recommendations.
 *
 * GET /api/ai/recommendations is public: with a token the feed is
 * personalised from THIS customer's own history (never anyone else's);
 * without one it is the same honest cold-start feed the website shows.
 * Dismiss and feedback require the caller's token and are per-account
 * server-side (dismissals expire; feedback feeds the relevance metrics).
 */
function RecommendationsSection({ lang, t }: { lang: string; t: (key: string) => string }) {
  const [data, setData] = useState<RecoPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [token, setToken] = useState<string | null>(null);
  const [dismissed, setDismissed] = useState<Record<string, boolean>>({});
  const [feedbackGiven, setFeedbackGiven] = useState<Record<string, string>>({});

  useEffect(() => {
    let mounted = true;
    (async () => {
      try {
        const stored = await AsyncStorage.getItem('userToken');
        if (!mounted) return;
        setToken(stored);

        const headers: Record<string, string> = { Accept: 'application/json' };
        if (stored) headers.Authorization = `Bearer ${stored}`;

        const res = await fetchWithTimeout(`${API_BASE_URL}/api/ai/recommendations?locale=${lang}`, {
          headers,
        });
        const json = (await res.json()) as RecoPayload;
        if (mounted && json && json.success) setData(json);
      } catch {
        // Recommendations are a nicety; the search box stays fully usable.
      } finally {
        if (mounted) setLoading(false);
      }
    })();
    return () => {
      mounted = false;
    };
  }, [lang]);

  const dismiss = async (type: 'product' | 'business', id: string) => {
    // Local hide first so it feels instant; the durable list needs a token.
    setDismissed((prev) => ({ ...prev, [id]: true }));
    if (!token) return;
    try {
      await fetchWithTimeout(`${API_BASE_URL}/api/ai/recommendations/dismiss`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
        body: JSON.stringify({ type, id }),
      });
    } catch {
      // The local hide is enough even if the durable record failed.
    }
  };

  const sendFeedback = async (rating: 'helpful' | 'not_helpful', id: string) => {
    if (!token) return;
    setFeedbackGiven((prev) => ({ ...prev, [id]: rating }));
    try {
      await fetchWithTimeout(`${API_BASE_URL}/api/ai/recommendations/feedback`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
        body: JSON.stringify({ rating, item_id: id }),
      });
    } catch {
      // Feedback is best-effort; it never blocks the shopper.
    }
  };

  if (loading || !data) return null;

  const products = (data.products || []).filter((p) => !dismissed[p.id]);
  const businesses = (data.businesses || []).filter((b) => !dismissed[b.id]);
  if (!products.length && !businesses.length) return null;

  return (
    <View style={styles.recoCard}>
      <View style={styles.recoHeadRow}>
        <Ionicons name="sparkles" size={14} color="#667eea" />
        <Text style={styles.recoTitle}>{t('mteja_reco.title')}</Text>
      </View>

      {data.meta?.note ? <Text style={styles.recoNote}>{data.meta.note}</Text> : null}

      {products.length > 0 ? (
        <Text style={styles.recoGroupLabel}>{t('mteja_reco.products_title')}</Text>
      ) : null}
      {products.map((p) => (
        <View key={p.id} style={styles.recoItem}>
          <View style={{ flex: 1 }}>
            <Text style={styles.recoName}>{p.name}</Text>
            {p.business_name ? <Text style={styles.recoBiz}>{p.business_name}</Text> : null}
            {p.reason?.text ? <Text style={styles.recoReason}>{p.reason.text}</Text> : null}
          </View>
          <View style={{ alignItems: 'flex-end', gap: 6 }}>
            <Text style={styles.recoPrice}>
              {p.selling_price != null ? `TZS ${Number(p.selling_price).toLocaleString('en-US')}` : ''}
              {p.unit ? ` / ${p.unit}` : ''}
            </Text>
            {token ? (
              <View style={styles.recoActions}>
                <TouchableOpacity accessibilityRole="button" onPress={() => void sendFeedback('helpful', p.id)} disabled={!!feedbackGiven[p.id]}>
                  <Ionicons name="thumbs-up" size={16} color={feedbackGiven[p.id] === 'helpful' ? '#27ae60' : '#95a5a6'} />
                </TouchableOpacity>
                <TouchableOpacity accessibilityRole="button" onPress={() => void sendFeedback('not_helpful', p.id)} disabled={!!feedbackGiven[p.id]}>
                  <Ionicons name="thumbs-down" size={16} color={feedbackGiven[p.id] === 'not_helpful' ? '#c0392b' : '#95a5a6'} />
                </TouchableOpacity>
                <TouchableOpacity accessibilityRole="button" onPress={() => void dismiss('product', p.id)}>
                  <Ionicons name="close" size={16} color="#95a5a6" />
                </TouchableOpacity>
              </View>
            ) : null}
          </View>
        </View>
      ))}

      {businesses.length > 0 ? (
        <Text style={styles.recoGroupLabel}>{t('mteja_reco.businesses_title')}</Text>
      ) : null}
      {businesses.map((b) => (
        <View key={b.id} style={styles.recoItem}>
          <View style={{ flex: 1 }}>
            <Text style={styles.recoName}>{b.business_name}</Text>
            {b.reason?.text ? <Text style={styles.recoReason}>{b.reason.text}</Text> : null}
          </View>
          {token ? (
            <TouchableOpacity accessibilityRole="button" onPress={() => void dismiss('business', b.id)} style={{ paddingLeft: 8 }}>
              <Ionicons name="close" size={16} color="#95a5a6" />
            </TouchableOpacity>
          ) : null}
        </View>
      ))}

      {data.meta?.privacy ? <Text style={styles.recoPrivacy}>{data.meta.privacy}</Text> : null}
    </View>
  );
}

export default function MtejaTafutaScreen() {
  const { t, lang } = useLang();
  const [query, setQuery] = useState('');
  const [sending, setSending] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [data, setData] = useState<AskResponse | null>(null);
  const [samples, setSamples] = useState<string[]>([]);
  const [refine, setRefine] = useState<Refine>({});
  const [sort, setSort] = useState<Sort>('relevance');
  const [expandedId, setExpandedId] = useState<string | null>(null);

  // Example chips + sample questions (silent when the endpoint is down).
  useEffect(() => {
    let alive = true;
    (async () => {
      try {
        const res = await fetchWithTimeout(`${API_BASE_URL}/api/ai/shopping/suggestions?q=`);
        if (!res.ok || !alive) return;
        const json = (await res.json()) as { samples?: string[] };
        if (alive && Array.isArray(json.samples)) setSamples(json.samples);
      } catch {
        /* examples are a nicety, never an error state */
      }
    })();
    return () => {
      alive = false;
    };
  }, [lang]);

  const ask = useCallback(
    async (rawQuery?: string, extra?: Refine, sortOverride?: Sort) => {
      const q = (rawQuery ?? query).trim();
      if (q === '' || sending) return;

      setSending(true);
      setErrorMsg(null);
      const nextRefine = extra ?? {};
      setRefine(nextRefine);

      try {
        const body: Record<string, unknown> = {
          query: q,
          locale: lang,
          sort: sortOverride ?? sort,
          ...nextRefine,
        };
        const headers: Record<string, string> = {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        };
        // Optional: attaches the bounded follow-up memory for signed-in
        // customers. The route is public, so a missing token is fine.
        try {
          const token = await AsyncStorage.getItem('userToken');
          if (token) headers.Authorization = `Bearer ${token}`;
        } catch {
          /* storage unavailable - stay anonymous */
        }

        const res = await fetchWithTimeout(
          `${API_BASE_URL}/api/ai/shopping/ask`,
          { method: 'POST', headers, body: JSON.stringify(body) },
          45000
        );

        let json: (AskResponse & { error?: string }) | null = null;
        try {
          json = (await res.json()) as AskResponse & { error?: string };
        } catch {
          json = null;
        }

        if (!res.ok || !json || json.success !== true) {
          setData(null);
          setErrorMsg(json?.error || t('mteja_tafuta.error_text'));
          return;
        }
        setData(json);
      } catch {
        setErrorMsg(t('mteja_tafuta.error_text'));
      } finally {
        setSending(false);
      }
    },
    [lang, query, sending, sort, t]
  );

  // Sort chips re-ask the SAME question with an explicit override: ask()
  // closes over the previous sort until the next render.
  const askSort = useCallback(
    (next: Sort) => {
      setSort(next);
      if (data && data.query) void ask(data.query, refine, next);
    },
    [ask, data, refine]
  );

  const kindLabel = (kind: string): string => {
    switch (kind) {
      case 'relaxed':
        return t('mteja_tafuta.closest_badge');
      case 'businesses':
        return t('mteja_tafuta.businesses_label');
      case 'clarify':
        return t('mteja_tafuta.clarify_label');
      case 'none':
        return t('mteja_tafuta.results_label');
      default:
        return t('mteja_tafuta.exact_badge');
    }
  };

  const renderFilters = () => {
    if (!data) return null;
    const it = data.interpreted;
    const chips: string[] = [];
    (it.terms || []).forEach((term) => chips.push(term));
    if (it.phrase) chips.push(`"${it.phrase}"`);
    if (it.category) chips.push(it.category);
    if (it.price_min != null && it.price_max != null) {
      chips.push(
        t('mteja_tafuta.price_between', {
          min: groupDigits(it.price_min),
          max: groupDigits(it.price_max),
        })
      );
    } else if (it.price_max != null) {
      chips.push(t('mteja_tafuta.price_max_only', { max: groupDigits(it.price_max) }));
    } else if (it.price_min != null) {
      chips.push(t('mteja_tafuta.price_min_only', { min: groupDigits(it.price_min) }));
    }
    if (it.in_stock) chips.push(t('mteja_tafuta.stock_only'));
    if (it.location) chips.push(it.location);
    if (!chips.length) return null;

    return (
      <View style={styles.filterCard}>
        <Text style={styles.blockLabel}>{t('mteja_tafuta.interpreted')}</Text>
        <View style={styles.chipWrap}>
          {chips.map((chip, index) => (
            <View key={index} style={styles.filterChip}>
              <Text style={styles.filterChipText}>{chip}</Text>
            </View>
          ))}
        </View>
      </View>
    );
  };

  const renderClarification = () => {
    if (!data?.clarification?.options?.length) return null;
    return (
      <View style={styles.clarifyCard}>
        <Text style={styles.blockLabel}>{t('mteja_tafuta.clarify_label')}</Text>
        <View style={styles.chipWrap}>
          {data.clarification.options.map((option, index) => (
            <TouchableOpacity
              key={index}
              style={styles.actionChip}
              disabled={sending}
              onPress={() =>
                ask(
                  data.query,
                  option.type === 'business'
                    ? { business_id: option.business_id }
                    : { category: option.category }
                )
              }
            >
              <Text style={styles.actionChipText}>
                {option.label}
                {option.count ? ` (${option.count})` : ''}
              </Text>
            </TouchableOpacity>
          ))}
        </View>
      </View>
    );
  };

  const renderCategories = () => {
    if (!data?.categories?.length || data.clarification) return null;
    return (
      <View style={styles.clarifyCard}>
        <Text style={styles.blockLabel}>{t('mteja_tafuta.refine_label')}</Text>
        <View style={styles.chipWrap}>
          {data.categories.map((row, index) => (
            <TouchableOpacity
              key={index}
              style={styles.actionChip}
              disabled={sending}
              onPress={() => ask(data.query, { category: row.category })}
            >
              <Text style={styles.actionChipText}>
                {row.category} ({row.count})
              </Text>
            </TouchableOpacity>
          ))}
        </View>
      </View>
    );
  };

  const renderProduct = (product: ProductResult, index: number) => {
    const key = product.id || `p${index}`;
    const expanded = expandedId === key;
    const matchType = product.match?.type === 'relaxed' ? 'relaxed' : 'strict';
    const biz: BusinessInfo = product.business || ({} as BusinessInfo);

    return (
      <TouchableOpacity
        key={key}
        style={styles.productCard}
        activeOpacity={0.85}
        onPress={() => setExpandedId(expanded ? null : key)}
      >
        <View style={styles.productTop}>
          {product.image ? (
            <Image source={{ uri: product.image }} style={styles.productImage} />
          ) : (
            <View style={[styles.productImage, styles.productImagePh]}>
              <Ionicons name="image-outline" size={22} color="#94a3b8" />
            </View>
          )}
          <View style={styles.productHead}>
            <Text style={styles.productName} numberOfLines={2}>
              {product.name}
            </Text>
            <View style={styles.bizLine}>
              <Ionicons name="storefront-outline" size={12} color="#7f8c8d" />
              <Text style={styles.bizName} numberOfLines={1}>
                {biz.name || '—'}
              </Text>
              {biz.certified ? (
                <View style={styles.certChip}>
                  <Text style={styles.certChipText}>{t('mteja_tafuta.certified')}</Text>
                </View>
              ) : null}
            </View>
            {biz.location ? (
              <Text style={styles.bizLocation} numberOfLines={1}>
                {biz.location}
              </Text>
            ) : null}
          </View>
        </View>

        <View style={styles.badgeRow}>
          <View style={[styles.badge, product.available ? styles.badgeOk : styles.badgeDanger]}>
            <Text
              style={[
                styles.badgeText,
                { color: product.available ? '#27ae60' : '#e74c3c' },
              ]}
            >
              {product.available ? t('mteja_tafuta.in_stock') : t('mteja_tafuta.out_of_stock')}
            </Text>
          </View>
          <View style={[styles.badge, matchType === 'strict' ? styles.badgeOk : styles.badgeNeutral]}>
            <Text
              style={[styles.badgeText, { color: matchType === 'strict' ? '#27ae60' : '#b7861f' }]}
            >
              {matchType === 'strict'
                ? t('mteja_tafuta.exact_badge')
                : t('mteja_tafuta.closest_badge')}
            </Text>
          </View>
          {product.category ? (
            <View style={[styles.badge, styles.badgeNeutral]}>
              <Text style={[styles.badgeText, { color: '#5d6d7e' }]}>{product.category}</Text>
            </View>
          ) : null}
        </View>

        {product.description ? (
          <Text style={styles.productDesc} numberOfLines={expanded ? undefined : 2}>
            {product.description}
          </Text>
        ) : null}

        <View style={styles.priceRow}>
          <Text style={styles.productPrice}>
            {groupDigits(product.selling_price)} {product.currency}
          </Text>
          {product.unit ? <Text style={styles.productUnit}>/ {product.unit}</Text> : null}
        </View>
      </TouchableOpacity>
    );
  };

  const renderBusiness = (business: BusinessResult, index: number) => (
    <View key={business.id || `b${index}`} style={styles.businessCard}>
      {business.avatar ? (
        <Image source={{ uri: business.avatar }} style={styles.businessAvatar} />
      ) : (
        <View style={[styles.businessAvatar, styles.productImagePh]}>
          <Ionicons name="storefront-outline" size={20} color="#94a3b8" />
        </View>
      )}
      <View style={{ flex: 1 }}>
        <View style={styles.bizLine}>
          <Text style={styles.businessName} numberOfLines={1}>
            {business.name}
          </Text>
          {business.certified ? (
            <View style={styles.certChip}>
              <Text style={styles.certChipText}>{t('mteja_tafuta.certified')}</Text>
            </View>
          ) : null}
        </View>
        <Text style={styles.bizLocation} numberOfLines={1}>
          {[business.location, `${business.product_count}`].filter(Boolean).join(' · ')}
        </Text>
      </View>
    </View>
  );

  const renderResult = () => {
    if (sending && !data && !errorMsg) {
      return (
        <View style={styles.stateBox}>
          <SmartCartLoader size="large" color="#667eea" label={t('mteja_tafuta.loading')} />
          <Text style={styles.stateText}>{t('mteja_tafuta.loading')}</Text>
        </View>
      );
    }

    if (errorMsg && !data) {
      return (
        <View style={styles.stateBox}>
          <Ionicons name="alert-circle-outline" size={40} color="#e74c3c" />
          <Text style={styles.stateTitle}>{t('mteja_tafuta.error_title')}</Text>
          <Text style={styles.stateText}>{errorMsg}</Text>
          <TouchableOpacity
            style={styles.retryBtn}
            onPress={() => ask(query, refine)}
          >
            <Text style={styles.retryBtnText}>{t('mteja_tafuta.error_title')}</Text>
          </TouchableOpacity>
        </View>
      );
    }

    if (!data) {
      return (
        <View style={styles.stateBox}>
          <Ionicons name="search-outline" size={40} color="#667eea" />
          <Text style={styles.stateTitle}>{t('mteja_tafuta.empty_title')}</Text>
          <Text style={styles.stateText}>{t('mteja_tafuta.empty_text')}</Text>
        </View>
      );
    }

    return (
      <View>
        {errorMsg ? (
          <View style={styles.messageCard}>
            <Text style={styles.messageText}>{errorMsg}</Text>
          </View>
        ) : null}

        <View style={styles.answerCard}>
          <View style={styles.answerHead}>
            <Ionicons name="sparkles" size={15} color="#667eea" />
            <Text style={styles.answerLabel}>{kindLabel(data.answer.kind)}</Text>
          </View>
          <Text style={styles.answerText}>{data.answer.text}</Text>
          {sending ? <ActivityIndicator size="small" color="#667eea" style={{ marginTop: 8 }} /> : null}
        </View>

        {renderFilters()}
        {renderClarification()}
        {renderCategories()}

        {data.products.length > 0 ? (
          <View>
            <Text style={styles.sectionLabel}>
              {t('mteja_tafuta.results_label')} ({data.products.length})
            </Text>
            {data.products.map((product, index) => renderProduct(product, index))}
          </View>
        ) : null}

        {data.businesses.length > 0 ? (
          <View>
            <Text style={styles.sectionLabel}>{t('mteja_tafuta.businesses_label')}</Text>
            {data.businesses.map((business, index) => renderBusiness(business, index))}
          </View>
        ) : null}

        {data.notes.length > 0 || data.limitations.length > 0 ? (
          <View style={styles.limitationsBox}>
            {data.notes.map((line, index) => (
              <Text key={`n${index}`} style={styles.limitationsText}>
                {line}
              </Text>
            ))}
            {data.limitations.length > 0 ? (
              <Text style={styles.limitationsTitle}>{t('mteja_tafuta.limitations_title')}</Text>
            ) : null}
            {data.limitations.map((line, index) => (
              <Text key={`l${index}`} style={styles.limitationsText}>
                {line}
              </Text>
            ))}
            <Text style={styles.generated}>
              {t('mteja_tafuta.generated', {
                date: String(data.generated_at || '').slice(0, 16).replace('T', ' '),
              })}
            </Text>
          </View>
        ) : null}
      </View>
    );
  };

  const sortOptions: { value: Sort; label: string }[] = [
    { value: 'relevance', label: t('mteja_tafuta.sort_relevance') },
    { value: 'price_asc', label: t('mteja_tafuta.sort_low') },
    { value: 'price_desc', label: t('mteja_tafuta.sort_high') },
  ];

  return (
    <SafeAreaView style={styles.screen} edges={['top']}>
      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          <View style={styles.titleRow}>
            <Text style={styles.title}>{t('mteja_tafuta.title')}</Text>
            <View style={styles.aiChip}>
              <Ionicons name="sparkles" size={11} color="#667eea" />
              <Text style={styles.aiChipText}>{t('mteja_tafuta.ai_badge')}</Text>
            </View>
          </View>
          <Text style={styles.subtitle}>{t('mteja_tafuta.subtitle')}</Text>

          {/* Capability #14 - personalised recommendations (cold-start for guests) */}
          <RecommendationsSection lang={lang} t={t} />

          <View style={styles.askCard}>
            <TextInput
              style={styles.input}
              value={query}
              onChangeText={setQuery}
              placeholder={t('mteja_tafuta.ask_placeholder')}
              placeholderTextColor="#95a5a6"
              multiline
              maxLength={240}
              editable={!sending}
              returnKeyType="search"
              onSubmitEditing={() => ask()}
            />
            <TouchableOpacity
              style={[styles.askBtn, (sending || query.trim() === '') && styles.askBtnDisabled]}
              disabled={sending || query.trim() === ''}
              onPress={() => ask()}
            >
              {sending ? (
                <ActivityIndicator color="#fff" size="small" />
              ) : (
                <>
                  <Ionicons name="search" size={15} color="#fff" />
                  <Text style={styles.askBtnText}>{t('mteja_tafuta.ask_btn')}</Text>
                </>
              )}
            </TouchableOpacity>

            {samples.length > 0 ? (
              <View>
                <Text style={styles.examplesLabel}>{t('mteja_tafuta.examples_title')}</Text>
                <View style={styles.chipWrap}>
                  {samples.map((sample, index) => (
                    <TouchableOpacity
                      key={index}
                      style={styles.sampleChip}
                      disabled={sending}
                      onPress={() => {
                        setQuery(sample);
                        void ask(sample);
                      }}
                    >
                      <Text style={styles.sampleChipText}>{sample}</Text>
                    </TouchableOpacity>
                  ))}
                </View>
              </View>
            ) : null}

            <Text style={styles.examplesLabel}>{t('mteja_tafuta.sort_label')}</Text>
            <View style={styles.chipWrap}>
              {sortOptions.map((option) => (
                <TouchableOpacity
                  key={option.value}
                  style={[styles.sampleChip, sort === option.value && styles.sampleChipActive]}
                  disabled={sending}
                  onPress={() => askSort(option.value)}
                >
                  <Text
                    style={[
                      styles.sampleChipText,
                      sort === option.value && styles.sampleChipTextActive,
                    ]}
                  >
                    {option.label}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>
          </View>

          {renderResult()}
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: '#f8f9fa' },
  /* capability #14 - recommendations */
  recoCard: {
    backgroundColor: '#fff', borderColor: '#ecf0f1', borderWidth: 1, borderRadius: 16,
    padding: 14, marginBottom: 16,
  },
  recoHeadRow: { flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 6 },
  recoTitle: { fontSize: 14, fontWeight: '800', color: '#2c3e50' },
  recoNote: { fontSize: 12, color: '#7f8c8d', marginBottom: 8 },
  recoGroupLabel: { fontSize: 11.5, fontWeight: '800', color: '#7f8c8d', textTransform: 'uppercase', letterSpacing: 0.5, marginTop: 6, marginBottom: 4 },
  recoItem: {
    flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 8,
    borderBottomColor: '#f1f3f5', borderBottomWidth: 1,
  },
  recoName: { fontSize: 13.5, fontWeight: '700', color: '#2c3e50' },
  recoBiz: { fontSize: 12, color: '#7f8c8d', marginTop: 1 },
  recoReason: { fontSize: 12, color: '#667eea', marginTop: 2 },
  recoPrice: { fontSize: 12.5, fontWeight: '700', color: '#2c3e50' },
  recoActions: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  recoPrivacy: { fontSize: 11, color: '#95a5a6', marginTop: 10 },
  content: { padding: 16, paddingBottom: 40 },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
  title: { fontSize: 22, fontWeight: '800', color: '#2c3e50' },
  subtitle: { fontSize: 13, color: '#7f8c8d', marginTop: 3, marginBottom: 14 },
  aiChip: {
    flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: '#eef1ff',
    borderColor: '#d6ddff', borderWidth: 1, borderRadius: 999, paddingHorizontal: 9, paddingVertical: 3,
  },
  aiChipText: { fontSize: 10.5, fontWeight: '800', color: '#667eea', letterSpacing: 0.4 },
  askCard: {
    backgroundColor: '#fff', borderColor: '#ecf0f1', borderWidth: 1, borderRadius: 16,
    padding: 14, marginBottom: 16,
  },
  input: {
    minHeight: 70, fontSize: 14.5, color: '#2c3e50', lineHeight: 20,
    backgroundColor: '#f8f9fa', borderRadius: 12, paddingHorizontal: 12, paddingVertical: 10,
    textAlignVertical: 'top',
  },
  askBtn: {
    flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 7,
    marginTop: 10, backgroundColor: '#667eea', borderRadius: 12, paddingVertical: 12,
  },
  askBtnDisabled: { opacity: 0.5 },
  askBtnText: { color: '#fff', fontWeight: '700', fontSize: 14.5 },
  examplesLabel: {
    fontSize: 10.5, fontWeight: '800', color: '#95a5a6', letterSpacing: 0.6,
    textTransform: 'uppercase', marginTop: 12, marginBottom: 6,
  },
  chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 7 },
  sampleChip: {
    backgroundColor: '#f8f9fa', borderColor: '#e8ecf3', borderWidth: 1,
    borderRadius: 999, paddingHorizontal: 11, paddingVertical: 6,
  },
  sampleChipActive: { backgroundColor: '#eef1ff', borderColor: '#c7d2fe' },
  sampleChipText: { fontSize: 12, color: '#5d6d7e', fontWeight: '600' },
  sampleChipTextActive: { color: '#5566d4' },
  stateBox: { alignItems: 'center', paddingVertical: 40, paddingHorizontal: 18 },
  stateTitle: { fontSize: 16, fontWeight: '700', color: '#2c3e50', marginTop: 10 },
  stateText: { fontSize: 13.5, color: '#7f8c8d', textAlign: 'center', marginTop: 5, lineHeight: 19 },
  retryBtn: {
    marginTop: 14, backgroundColor: '#667eea', borderRadius: 12, paddingHorizontal: 20, paddingVertical: 10,
  },
  retryBtnText: { fontWeight: '700', color: '#fff', fontSize: 14 },
  messageCard: {
    backgroundColor: '#fff8e8', borderColor: '#f5dfa8', borderWidth: 1,
    borderRadius: 14, padding: 13, marginBottom: 14,
  },
  messageText: { fontSize: 13.5, color: '#7a5c14', lineHeight: 19 },
  answerCard: {
    backgroundColor: '#fff', borderColor: '#e3e8ff', borderWidth: 1, borderRadius: 16,
    padding: 15, marginBottom: 14,
  },
  answerHead: { flexDirection: 'row', alignItems: 'center', gap: 7, flexWrap: 'wrap' },
  answerLabel: { fontSize: 14.5, fontWeight: '800', color: '#2c3e50' },
  answerText: { fontSize: 14.5, color: '#34495e', lineHeight: 22, marginTop: 9 },
  filterCard: {
    backgroundColor: '#fff', borderColor: '#ecf0f1', borderWidth: 1, borderRadius: 16,
    padding: 14, marginBottom: 14,
  },
  blockLabel: {
    fontSize: 10.5, fontWeight: '800', color: '#95a5a6', letterSpacing: 0.6,
    textTransform: 'uppercase', marginBottom: 8,
  },
  filterChip: { backgroundColor: '#eef1ff', borderRadius: 999, paddingHorizontal: 10, paddingVertical: 4 },
  filterChipText: { fontSize: 12, fontWeight: '700', color: '#5566d4' },
  clarifyCard: {
    backgroundColor: '#f8f9ff', borderColor: '#d6ddff', borderWidth: 1, borderRadius: 16,
    padding: 14, marginBottom: 14,
  },
  actionChip: {
    backgroundColor: '#fff', borderColor: '#c7d2fe', borderWidth: 1,
    borderRadius: 999, paddingHorizontal: 12, paddingVertical: 7,
  },
  actionChipText: { fontSize: 12.5, fontWeight: '700', color: '#4338ca' },
  sectionLabel: {
    fontSize: 10.5, fontWeight: '800', color: '#95a5a6', letterSpacing: 0.6,
    textTransform: 'uppercase', marginTop: 4, marginBottom: 8,
  },
  productCard: {
    backgroundColor: '#fff', borderColor: '#ecf0f1', borderWidth: 1, borderRadius: 16,
    padding: 14, marginBottom: 12,
  },
  productTop: { flexDirection: 'row', gap: 12 },
  productImage: { width: 58, height: 58, borderRadius: 12, backgroundColor: '#f1f5f9' },
  productImagePh: { alignItems: 'center', justifyContent: 'center' },
  productHead: { flex: 1 },
  productName: { fontSize: 15, fontWeight: '800', color: '#2c3e50' },
  bizLine: { flexDirection: 'row', alignItems: 'center', gap: 5, marginTop: 4, flexWrap: 'wrap' },
  bizName: { fontSize: 12.5, color: '#7f8c8d', maxWidth: '65%' },
  bizLocation: { fontSize: 12, color: '#95a5a6', marginTop: 2 },
  certChip: { backgroundColor: '#eafaf1', borderRadius: 999, paddingHorizontal: 8, paddingVertical: 2 },
  certChipText: { fontSize: 10, fontWeight: '800', color: '#27ae60' },
  badgeRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginTop: 10 },
  badge: { borderRadius: 999, paddingHorizontal: 9, paddingVertical: 3 },
  badgeOk: { backgroundColor: '#eafaf1' },
  badgeDanger: { backgroundColor: '#fdeaea' },
  badgeNeutral: { backgroundColor: '#f1f3f8' },
  badgeText: { fontSize: 11, fontWeight: '800' },
  productDesc: { fontSize: 13, color: '#7f8c8d', lineHeight: 18, marginTop: 8 },
  priceRow: { flexDirection: 'row', alignItems: 'baseline', gap: 4, marginTop: 8 },
  productPrice: { fontSize: 16, fontWeight: '800', color: '#667eea' },
  productUnit: { fontSize: 12, color: '#95a5a6' },
  businessCard: {
    flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: '#fff',
    borderColor: '#ecf0f1', borderWidth: 1, borderRadius: 14, padding: 12, marginBottom: 10,
  },
  businessAvatar: { width: 44, height: 44, borderRadius: 10, backgroundColor: '#f1f5f9' },
  businessName: { fontSize: 14.5, fontWeight: '800', color: '#2c3e50', maxWidth: '70%' },
  limitationsBox: { marginTop: 4 },
  limitationsTitle: { fontSize: 12, fontWeight: '800', color: '#7f8c8d', marginTop: 8 },
  limitationsText: { fontSize: 12, color: '#95a5a6', lineHeight: 17, marginTop: 3 },
  generated: { fontSize: 11.5, color: '#aeb6bf', marginTop: 6 },
});
