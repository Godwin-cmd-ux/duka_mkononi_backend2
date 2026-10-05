import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLang } from '../../context/LanguageContext';
import { getCache, setCache } from '../../db/cache';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';

import { API_BASE_URL } from '../../constants/api';

/**
 * Capability #2 - Pricing & Margin Advisor (seller mobile screen).
 *
 * Backed by POST /api/ai/price/suggest. Every number shown here is computed by
 * the trusted PHP engine on the server; this screen only renders it. When the
 * AI narrative is unavailable (disabled, unconfigured or upstream error) the
 * response still carries the real margins and the screen shows them with an
 * honest "AI advice unavailable" banner instead of inventing text.
 */

interface AiInsight {
  product_id: string | null;
  severity: 'high' | 'medium' | 'low';
  title: string;
  action: string;
}

interface AdvisorProduct {
  id: string;
  name: string;
  category: string;
  stock: number;
  buying_price: number | null;
  selling_price: number | null;
  margin: number | null;
  margin_pct: number | null;
  status: string;
  units_sold_window: number;
  revenue_window: number;
  suggested_price: number | null;
  floor_price: number | null;
  expected_margin_pct_at_suggested: number | null;
  ai: AiInsight | null;
}

interface AdvisorSummary {
  currency: string;
  window_days: number;
  product_count: number;
  at_risk_count: number;
  inventory_value_cost: number;
  revenue_window: number;
  gross_margin_window: number;
  margin_pct_window: number | null;
  weighted_margin_pct: number | null;
  missing_cost_count: number;
  missing_selling_count: number;
}

interface AdvisorResponse {
  success: boolean;
  capability: string;
  locale: string;
  generated_at: string;
  currency: string;
  summary: AdvisorSummary;
  products: AdvisorProduct[];
  insights: AiInsight[];
  watchouts: string[];
  limitations: string[];
  ai: {
    available: boolean;
    degraded: boolean;
    code: string | null;
    reason: string | null;
    headline: string;
  };
}

const STATUS_KEY: Record<string, string> = {
  LOSS: 'status_loss',
  ZERO_MARGIN: 'status_zero',
  THIN: 'status_thin',
  HEALTHY: 'status_healthy',
  MISSING_COST: 'status_missing_cost',
  MISSING_SELLING_PRICE: 'status_missing_selling',
};

const STATUS_COLOR: Record<string, string> = {
  LOSS: '#e74c3c',
  ZERO_MARGIN: '#e74c3c',
  THIN: '#f39c12',
  HEALTHY: '#27ae60',
  MISSING_COST: '#95a5a6',
  MISSING_SELLING_PRICE: '#95a5a6',
};

const SEVERITY_COLOR: Record<string, string> = {
  high: '#e74c3c',
  medium: '#f39c12',
  low: '#3498db',
};

export default function UshauriBeiScreen() {
  const { t, lang } = useLang();

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [data, setData] = useState<AdvisorResponse | null>(null);
  const [token, setToken] = useState<string | null>(null);

  const cacheKey = 'ai:price:advice:' + lang;

  const formatCurrency = (amount: number | null | undefined) => {
    if (typeof amount !== 'number' || !Number.isFinite(amount)) return '-';
    const formatted = new Intl.NumberFormat('en-TZ', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount);
    return `${data?.currency || 'TZS'} ${formatted}`;
  };

  const formatPercent = (value: number | null | undefined) =>
    typeof value === 'number' && Number.isFinite(value) ? `${value.toFixed(1)}%` : '-';

  const load = useCallback(async (isRefresh = false) => {
    try {
      if (isRefresh) setRefreshing(true);
      else setLoading(true);
      setError(null);

      const storedToken = token || (await AsyncStorage.getItem('userToken'));
      if (!storedToken) {
        setError(t('price_advisor.error_auth'));
        return;
      }
      setToken(storedToken);

      if (!isRefresh) {
        const cached = await getCache<AdvisorResponse>(cacheKey);
        if (cached) {
          setData(cached);
          setLoading(false);
        }
      }

      // Offline guard only when a refresh is explicitly requested; the first
      // load falls back to cache above.
      if (isRefresh && !(await requireNetwork())) {
        setError(t('price_advisor.error_network'));
        return;
      }

      const response = await fetchWithTimeout(
        `${API_BASE_URL}/api/ai/price/suggest`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Authorization: `Bearer ${storedToken}`,
            'ngrok-skip-browser-warning': 'true',
          },
          body: JSON.stringify({ locale: lang, window_days: 30 }),
        },
        45000
      );

      const payload = await response.json().catch(() => null);

      if (!response.ok || !payload) {
        setError(t('price_advisor.error_load'));
        return;
      }

      setData(payload as AdvisorResponse);
      setCache(cacheKey, payload).catch(() => {});
    } catch (err) {
      console.error('Error loading price advice:', err);
      const cached = await getCache<AdvisorResponse>(cacheKey);
      if (cached) setData(cached);
      setError(t('price_advisor.error_network'));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [cacheKey, lang, token, t]);

  useEffect(() => {
    load(false);
    // Re-request in the visitor's language whenever it changes.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [lang]);

  const summary = data?.summary;
  const products = data?.products ?? [];
  const needsAttention = products.filter((p) => p.status !== 'HEALTHY');
  const shown = needsAttention.length > 0 ? needsAttention : products;

  const renderBanner = () => {
    if (!data) return null;
    const ai = data.ai;
    if (ai.available && !ai.degraded) {
      return (
        <View style={[styles.banner, { backgroundColor: '#e8f8f0' }]}>
          <Ionicons name="sparkles" size={16} color="#27ae60" />
          <Text style={[styles.bannerText, { color: '#1e8449' }]}>{t('price_advisor.ai_ready')}</Text>
        </View>
      );
    }
    return (
      <View style={[styles.banner, { backgroundColor: '#fef5e7' }]}>
        <Ionicons name="information-circle" size={16} color="#e67e22" />
        <Text style={[styles.bannerText, { color: '#b9770e' }]}>
          {t('price_advisor.ai_degraded_title')}
          {ai.reason ? ` — ${ai.reason}` : ''}
        </Text>
      </View>
    );
  };

  const renderSummary = () => {
    if (!summary) return null;
    return (
      <View style={styles.card}>
        <Text style={styles.cardTitle}>{t('price_advisor.summary_title')}</Text>
        <View style={styles.statsGrid}>
          <View style={styles.statBox}>
            <Text style={styles.statNumber}>{summary.product_count}</Text>
            <Text style={styles.statLabel}>{t('price_advisor.stat_products')}</Text>
          </View>
          <View style={styles.statBox}>
            <Text style={[styles.statNumber, { color: '#e74c3c' }]}>{summary.at_risk_count}</Text>
            <Text style={styles.statLabel}>{t('price_advisor.stat_at_risk')}</Text>
          </View>
          <View style={styles.statBox}>
            <Text style={[styles.statNumber, { color: '#27ae60' }]}>
              {formatPercent(summary.weighted_margin_pct ?? summary.margin_pct_window)}
            </Text>
            <Text style={styles.statLabel}>{t('price_advisor.stat_margin')}</Text>
          </View>
        </View>
        <View style={styles.divider} />
        <View style={styles.row}>
          <Text style={styles.rowLabel}>{t('price_advisor.stat_inventory')}</Text>
          <Text style={styles.rowValue}>{formatCurrency(summary.inventory_value_cost)}</Text>
        </View>
        <View style={styles.row}>
          <Text style={styles.rowLabel}>{t('price_advisor.stat_revenue')}</Text>
          <Text style={styles.rowValue}>{formatCurrency(summary.revenue_window)}</Text>
        </View>
        <View style={styles.row}>
          <Text style={styles.rowLabel}>{t('price_advisor.stat_gross')}</Text>
          <Text
            style={[
              styles.rowValue,
              { color: summary.gross_margin_window < 0 ? '#e74c3c' : '#27ae60' },
            ]}
          >
            {formatCurrency(summary.gross_margin_window)}
          </Text>
        </View>
        <Text style={styles.muted}>
          {t('price_advisor.window_label', { days: summary.window_days })}
        </Text>
      </View>
    );
  };

  const renderProducts = () => {
    if (shown.length === 0) return null;
    return (
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>{t('price_advisor.products_title')}</Text>
        {shown.map((product) => {
          const statusKey = STATUS_KEY[product.status] || 'status_healthy';
          const statusColor = STATUS_COLOR[product.status] || '#95a5a6';
          return (
            <View key={product.id} style={styles.productCard}>
              <View style={styles.productHeader}>
                <Text style={styles.productName} numberOfLines={2}>
                  {product.name}
                </Text>
                <View style={[styles.badge, { backgroundColor: statusColor + '20' }]}>
                  <Text style={[styles.badgeText, { color: statusColor }]}>
                    {t('price_advisor.' + statusKey)}
                  </Text>
                </View>
              </View>

              <View style={styles.productMetaRow}>
                <Text style={styles.productMeta}>
                  {t('price_advisor.label_buying')}: {formatCurrency(product.buying_price)}
                </Text>
                <Text style={styles.productMeta}>
                  {t('price_advisor.label_selling')}: {formatCurrency(product.selling_price)}
                </Text>
              </View>
              <View style={styles.productMetaRow}>
                <Text style={styles.productMeta}>
                  {t('price_advisor.label_margin')}:{' '}
                  {typeof product.margin_pct === 'number'
                    ? t('price_advisor.margin_pct', { percent: product.margin_pct.toFixed(1) })
                    : formatCurrency(product.margin)}
                </Text>
                <Text style={styles.productMeta}>
                  {t('price_advisor.label_stock')}: {product.stock}
                </Text>
              </View>

              {product.suggested_price !== null && (
                <View style={styles.suggestBox}>
                  <View style={styles.row}>
                    <Text style={styles.suggestLabel}>{t('price_advisor.label_suggested')}</Text>
                    <Text style={styles.suggestValue}>
                      {formatCurrency(product.suggested_price)}
                    </Text>
                  </View>
                  <View style={styles.row}>
                    <Text style={styles.suggestLabel}>{t('price_advisor.label_floor')}</Text>
                    <Text style={styles.suggestValue}>{formatCurrency(product.floor_price)}</Text>
                  </View>
                </View>
              )}

              {product.ai && (
                <View
                  style={[
                    styles.aiNote,
                    { borderLeftColor: SEVERITY_COLOR[product.ai.severity] || '#3498db' },
                  ]}
                >
                  <Text style={styles.aiNoteTitle}>
                    <Ionicons name="sparkles" size={12} /> {product.ai.title}
                  </Text>
                  <Text style={styles.aiNoteText}>{product.ai.action}</Text>
                </View>
              )}
            </View>
          );
        })}
      </View>
    );
  };

  const renderList = (title: string, items: string[], color: string) => {
    if (!items || items.length === 0) return null;
    return (
      <View style={styles.section}>
        <Text style={[styles.sectionTitle, { color }]}>{title}</Text>
        {items.map((item, index) => (
          <View key={index} style={styles.listRow}>
            <Text style={[styles.listBullet, { color }]}>•</Text>
            <Text style={styles.listText}>{item}</Text>
          </View>
        ))}
      </View>
    );
  };

  const renderBody = () => {
    if (loading) {
      return (
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#2ecc71" />
          <Text style={styles.centerText}>{t('price_advisor.loading')}</Text>
        </View>
      );
    }

    if (error && !data) {
      return (
        <View style={styles.center}>
          <Ionicons name="cloud-offline" size={48} color="#bdc3c7" />
          <Text style={styles.centerText}>{error}</Text>
          <TouchableOpacity style={styles.retryBtn} onPress={() => load(true)}>
            <Text style={styles.retryText}>{t('app.retry')}</Text>
          </TouchableOpacity>
        </View>
      );
    }

    if (!summary || products.length === 0) {
      return (
        <View style={styles.center}>
          <Ionicons name="pricetags" size={48} color="#bdc3c7" />
          <Text style={styles.emptyTitle}>{t('price_advisor.empty_title')}</Text>
          <Text style={styles.centerText}>{t('price_advisor.empty_text')}</Text>
        </View>
      );
    }

    return (
      <>
        {renderBanner()}
        {data?.ai?.headline ? (
          <View style={[styles.card, { backgroundColor: '#f4f9ff' }]}>
            <Text style={styles.headline}>{data.ai.headline}</Text>
          </View>
        ) : null}
        {renderSummary()}
        {renderProducts()}
        {renderList(t('price_advisor.watchouts_title'), data?.watchouts ?? [], '#e67e22')}
        {renderList(t('price_advisor.limitations_title'), data?.limitations ?? [], '#95a5a6')}
      </>
    );
  };

  return (
    <SafeAreaView style={styles.container} edges={['top', 'left', 'right']}>
      <View style={styles.header}>
        <Text style={styles.title}>{t('price_advisor.title')}</Text>
        <TouchableOpacity style={styles.refreshBtn} onPress={() => load(true)} disabled={refreshing}>
          {refreshing ? (
            <ActivityIndicator size="small" color="#2ecc71" />
          ) : (
            <Ionicons name="refresh" size={20} color="#2ecc71" />
          )}
        </TouchableOpacity>
      </View>

      <ScrollView
        contentContainerStyle={styles.scroll}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={() => load(true)} colors={['#2ecc71']} />
        }
      >
        {renderBody()}
        {data?.generated_at ? (
          <Text style={styles.generated}>
            {t('price_advisor.generated_at', { date: new Date(data.generated_at).toLocaleString() })}
          </Text>
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f8f9fa' },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingVertical: 14,
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: '#ecf0f1',
  },
  title: { fontSize: 18, fontWeight: '700', color: '#2c3e50', flex: 1 },
  refreshBtn: { padding: 8 },
  scroll: { padding: 16, paddingBottom: 40 },
  center: { alignItems: 'center', paddingVertical: 60, paddingHorizontal: 20 },
  centerText: { marginTop: 12, color: '#7f8c8d', fontSize: 14, textAlign: 'center' },
  emptyTitle: { marginTop: 12, fontSize: 18, fontWeight: '700', color: '#2c3e50' },
  retryBtn: {
    marginTop: 16,
    backgroundColor: '#2ecc71',
    paddingHorizontal: 24,
    paddingVertical: 10,
    borderRadius: 10,
  },
  retryText: { color: '#ffffff', fontWeight: '700' },
  banner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    padding: 12,
    borderRadius: 12,
    marginBottom: 12,
  },
  bannerText: { flex: 1, fontSize: 13, fontWeight: '600' },
  card: {
    backgroundColor: '#ffffff',
    borderRadius: 16,
    padding: 16,
    marginBottom: 14,
  },
  cardTitle: { fontSize: 15, fontWeight: '700', color: '#2c3e50', marginBottom: 12 },
  statsGrid: { flexDirection: 'row', justifyContent: 'space-between' },
  statBox: { flex: 1, alignItems: 'center' },
  statNumber: { fontSize: 20, fontWeight: '700', color: '#2c3e50' },
  statLabel: { fontSize: 11, color: '#7f8c8d', textAlign: 'center', marginTop: 2 },
  divider: { height: 1, backgroundColor: '#ecf0f1', marginVertical: 12 },
  row: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 3 },
  rowLabel: { fontSize: 13, color: '#7f8c8d', flex: 1, paddingRight: 8 },
  rowValue: { fontSize: 13, fontWeight: '600', color: '#2c3e50' },
  muted: { fontSize: 11, color: '#95a5a6', marginTop: 8 },
  headline: { fontSize: 14, color: '#2c3e50', fontWeight: '600', lineHeight: 20 },
  section: { marginBottom: 16 },
  sectionTitle: { fontSize: 15, fontWeight: '700', color: '#2c3e50', marginBottom: 10 },
  productCard: {
    backgroundColor: '#ffffff',
    borderRadius: 14,
    padding: 14,
    marginBottom: 10,
  },
  productHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: 8 },
  productName: { flex: 1, fontSize: 15, fontWeight: '700', color: '#2c3e50' },
  badge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 20 },
  badgeText: { fontSize: 11, fontWeight: '700' },
  productMetaRow: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 8, gap: 8 },
  productMeta: { fontSize: 12, color: '#5d6d7e', flex: 1 },
  suggestBox: {
    marginTop: 10,
    padding: 10,
    borderRadius: 10,
    backgroundColor: '#f0f7ff',
  },
  suggestLabel: { fontSize: 12, color: '#2980b9', flex: 1 },
  suggestValue: { fontSize: 13, fontWeight: '700', color: '#1f618d' },
  aiNote: {
    marginTop: 10,
    paddingLeft: 10,
    borderLeftWidth: 3,
  },
  aiNoteTitle: { fontSize: 12, fontWeight: '700', color: '#2c3e50' },
  aiNoteText: { fontSize: 12, color: '#5d6d7e', marginTop: 3, lineHeight: 17 },
  listRow: { flexDirection: 'row', gap: 8, marginBottom: 6 },
  listBullet: { fontSize: 14, fontWeight: '700' },
  listText: { flex: 1, fontSize: 12, color: '#5d6d7e', lineHeight: 17 },
  generated: { textAlign: 'center', fontSize: 11, color: '#bdc3c7', marginTop: 8 },
});
