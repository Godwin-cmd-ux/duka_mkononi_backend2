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
 * Capability #3 - Restock & Stock-Out Plan (seller mobile screen).
 *
 * Backed by POST /api/ai/restock/list. Velocity, days of cover and the proposed
 * order quantity are all computed by the trusted PHP engine on the server; this
 * screen only renders them. It never purchases or changes stock.
 */

interface AiInsight {
  product_id: string | null;
  severity: 'high' | 'medium' | 'low';
  title: string;
  action: string;
}

interface PlanProduct {
  id: string;
  name: string;
  category: string;
  stock: number;
  incoming_stock: number;
  effective_stock: number;
  status: string;
  buying_price: number | null;
  selling_price: number | null;
  daily_velocity: number;
  recent_daily_velocity: number;
  demand_trend: string;
  units_sold_window: number;
  days_until_stockout: number | null;
  min_stock_level: number;
  proposed_quantity: number;
  proposed_value: number | null;
  confidence: string;
  sparse_data: boolean;
  fast_moving: boolean;
  slow_moving: boolean;
  overstocked: boolean;
  ai: AiInsight | null;
}

interface PlanSummary {
  currency: string;
  window_days: number;
  lead_time_days: number;
  product_count: number;
  restock_count: number;
  out_of_stock_count: number;
  low_stock_count: number;
  total_proposed_units: number;
  estimated_restock_cost: number;
}

interface PlanResponse {
  success: boolean;
  capability: string;
  locale: string;
  generated_at: string;
  currency: string;
  summary: PlanSummary;
  products: PlanProduct[];
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
  OUT_OF_STOCK: 'status_out_of_stock',
  LOW_STOCK: 'status_low_stock',
  RESTOCK: 'status_restock',
  HEALTHY: 'status_healthy',
  OVERSTOCK: 'status_overstock',
  NO_DEMAND: 'status_no_demand',
};

const STATUS_COLOR: Record<string, string> = {
  OUT_OF_STOCK: '#e74c3c',
  LOW_STOCK: '#e67e22',
  RESTOCK: '#f39c12',
  HEALTHY: '#27ae60',
  OVERSTOCK: '#3498db',
  NO_DEMAND: '#95a5a6',
};

const SEVERITY_COLOR: Record<string, string> = {
  high: '#e74c3c',
  medium: '#f39c12',
  low: '#3498db',
};

const CONFIDENCE_KEY: Record<string, string> = {
  high: 'confidence_high',
  medium: 'confidence_medium',
  low: 'confidence_low',
  none: 'confidence_none',
};

const ACTIONABLE = ['OUT_OF_STOCK', 'LOW_STOCK', 'RESTOCK', 'OVERSTOCK'];

export default function MpangoStockScreen() {
  const { t, lang } = useLang();

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [data, setData] = useState<PlanResponse | null>(null);
  const [token, setToken] = useState<string | null>(null);

  const cacheKey = 'ai:restock:plan:' + lang;

  const formatCurrency = (amount: number | null | undefined) => {
    if (typeof amount !== 'number' || !Number.isFinite(amount)) return '-';
    const formatted = new Intl.NumberFormat('en-TZ', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount);
    return `${data?.currency || 'TZS'} ${formatted}`;
  };

  const formatNumber = (value: number | null | undefined, digits = 1) =>
    typeof value === 'number' && Number.isFinite(value) ? value.toFixed(digits) : '-';

  const load = useCallback(async (isRefresh = false) => {
    try {
      if (isRefresh) setRefreshing(true);
      else setLoading(true);
      setError(null);

      const storedToken = token || (await AsyncStorage.getItem('userToken'));
      if (!storedToken) {
        setError(t('restock_planner.error_auth'));
        return;
      }
      setToken(storedToken);

      if (!isRefresh) {
        const cached = await getCache<PlanResponse>(cacheKey);
        if (cached) {
          setData(cached);
          setLoading(false);
        }
      }

      if (isRefresh && !(await requireNetwork())) {
        setError(t('restock_planner.error_network'));
        return;
      }

      const response = await fetchWithTimeout(
        `${API_BASE_URL}/api/ai/restock/list`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Authorization: `Bearer ${storedToken}`,
            'ngrok-skip-browser-warning': 'true',
          },
          body: JSON.stringify({ locale: lang }),
        },
        45000
      );

      const payload = await response.json().catch(() => null);

      if (!response.ok || !payload) {
        setError(t('restock_planner.error_load'));
        return;
      }

      setData(payload as PlanResponse);
      setCache(cacheKey, payload).catch(() => {});
    } catch (err) {
      console.error('Error loading restock plan:', err);
      const cached = await getCache<PlanResponse>(cacheKey);
      if (cached) setData(cached);
      setError(t('restock_planner.error_network'));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [cacheKey, lang, token, t]);

  useEffect(() => {
    load(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [lang]);

  const summary = data?.summary;
  const products = data?.products ?? [];
  const actionable = products.filter(
    (p) => p.proposed_quantity > 0 || ACTIONABLE.includes(p.status)
  );
  const shown = actionable.length > 0 ? actionable : products;

  const renderBanner = () => {
    if (!data) return null;
    const ai = data.ai;
    if (ai.available && !ai.degraded) {
      return (
        <View style={[styles.banner, { backgroundColor: '#e8f8f0' }]}>
          <Ionicons name="sparkles" size={16} color="#27ae60" />
          <Text style={[styles.bannerText, { color: '#1e8449' }]}>
            {t('restock_planner.ai_ready')}
          </Text>
        </View>
      );
    }
    return (
      <View style={[styles.banner, { backgroundColor: '#fef5e7' }]}>
        <Ionicons name="information-circle" size={16} color="#e67e22" />
        <Text style={[styles.bannerText, { color: '#b9770e' }]}>
          {t('restock_planner.ai_degraded_title')}
          {ai.reason ? ` — ${ai.reason}` : ''}
        </Text>
      </View>
    );
  };

  const renderSummary = () => {
    if (!summary) return null;
    return (
      <View style={styles.card}>
        <Text style={styles.cardTitle}>{t('restock_planner.summary_title')}</Text>
        <View style={styles.statsGrid}>
          <View style={styles.statBox}>
            <Text style={styles.statNumber}>{summary.product_count}</Text>
            <Text style={styles.statLabel}>{t('restock_planner.stat_products')}</Text>
          </View>
          <View style={styles.statBox}>
            <Text style={[styles.statNumber, { color: '#e67e22' }]}>{summary.restock_count}</Text>
            <Text style={styles.statLabel}>{t('restock_planner.stat_to_restock')}</Text>
          </View>
          <View style={styles.statBox}>
            <Text style={[styles.statNumber, { color: '#e74c3c' }]}>
              {summary.out_of_stock_count}
            </Text>
            <Text style={styles.statLabel}>{t('restock_planner.stat_out_of_stock')}</Text>
          </View>
        </View>
        <View style={styles.divider} />
        <View style={styles.row}>
          <Text style={styles.rowLabel}>{t('restock_planner.stat_proposed_units')}</Text>
          <Text style={styles.rowValue}>{formatNumber(summary.total_proposed_units, 0)}</Text>
        </View>
        <View style={styles.row}>
          <Text style={styles.rowLabel}>{t('restock_planner.stat_restock_cost')}</Text>
          <Text style={styles.rowValue}>{formatCurrency(summary.estimated_restock_cost)}</Text>
        </View>
        <Text style={styles.muted}>
          {t('restock_planner.window_label', { days: summary.window_days })} ·{' '}
          {t('restock_planner.lead_label', { days: summary.lead_time_days })}
        </Text>
      </View>
    );
  };

  const renderBadges = (product: PlanProduct) => {
    const badges: { key: string; color: string }[] = [];
    if (product.fast_moving) badges.push({ key: 'badge_fast_moving', color: '#27ae60' });
    if (product.slow_moving) badges.push({ key: 'badge_slow_moving', color: '#95a5a6' });
    if (product.sparse_data) badges.push({ key: 'badge_sparse', color: '#e67e22' });

    if (badges.length === 0) return null;
    return (
      <View style={styles.badgeRow}>
        {badges.map((b) => (
          <View key={b.key} style={[styles.miniBadge, { backgroundColor: b.color + '20' }]}>
            <Text style={[styles.miniBadgeText, { color: b.color }]}>
              {t('restock_planner.' + b.key)}
            </Text>
          </View>
        ))}
      </View>
    );
  };

  const renderProducts = () => {
    if (shown.length === 0) return null;
    return (
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>{t('restock_planner.products_title')}</Text>
        {shown.map((product) => {
          const statusKey = STATUS_KEY[product.status] || 'status_no_demand';
          const statusColor = STATUS_COLOR[product.status] || '#95a5a6';
          const confidenceKey = CONFIDENCE_KEY[product.confidence] || 'confidence_none';
          const needsOrder = product.proposed_quantity > 0;
          return (
            <View key={product.id} style={styles.productCard}>
              <View style={styles.productHeader}>
                <Text style={styles.productName} numberOfLines={2}>
                  {product.name}
                </Text>
                <View style={[styles.badge, { backgroundColor: statusColor + '20' }]}>
                  <Text style={[styles.badgeText, { color: statusColor }]}>
                    {t('restock_planner.' + statusKey)}
                  </Text>
                </View>
              </View>

              <View style={styles.productMetaRow}>
                <Text style={styles.productMeta}>
                  {t('restock_planner.label_stock')}: {formatNumber(product.stock, 0)}
                </Text>
                <Text style={styles.productMeta}>
                  {t('restock_planner.label_velocity')}: {formatNumber(product.daily_velocity, 2)}
                </Text>
                <Text style={styles.productMeta}>
                  {t('restock_planner.label_days_left')}:{' '}
                  {product.days_until_stockout === null
                    ? '∞'
                    : formatNumber(product.days_until_stockout, 0)}
                </Text>
              </View>

              {needsOrder && (
                <View style={styles.orderBox}>
                  <View style={styles.row}>
                    <Text style={styles.orderLabel}>{t('restock_planner.label_proposed')}</Text>
                    <Text style={styles.orderValue}>
                      {formatNumber(product.proposed_quantity, 0)}
                    </Text>
                  </View>
                  <View style={styles.row}>
                    <Text style={styles.orderLabel}>{t('restock_planner.label_min_stock')}</Text>
                    <Text style={styles.orderValue}>{formatNumber(product.min_stock_level, 0)}</Text>
                  </View>
                  <View style={styles.row}>
                    <Text style={styles.orderLabel}>{t('restock_planner.label_cost')}</Text>
                    <Text style={styles.orderValue}>{formatCurrency(product.proposed_value)}</Text>
                  </View>
                </View>
              )}

              <View style={styles.footerRow}>
                {renderBadges(product)}
                <Text style={styles.confidence}>{t('restock_planner.' + confidenceKey)}</Text>
              </View>

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
          <Text style={styles.centerText}>{t('restock_planner.loading')}</Text>
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
          <Ionicons name="cube" size={48} color="#bdc3c7" />
          <Text style={styles.emptyTitle}>{t('restock_planner.empty_title')}</Text>
          <Text style={styles.centerText}>{t('restock_planner.empty_text')}</Text>
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
        {renderList(t('restock_planner.watchouts_title'), data?.watchouts ?? [], '#e67e22')}
        {renderList(t('restock_planner.limitations_title'), data?.limitations ?? [], '#95a5a6')}
      </>
    );
  };

  return (
    <SafeAreaView style={styles.container} edges={['top', 'left', 'right']}>
      <View style={styles.header}>
        <Text style={styles.title}>{t('restock_planner.title')}</Text>
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
            {t('restock_planner.generated_at', {
              date: new Date(data.generated_at).toLocaleString(),
            })}
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
  card: { backgroundColor: '#ffffff', borderRadius: 16, padding: 16, marginBottom: 14 },
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
  productCard: { backgroundColor: '#ffffff', borderRadius: 14, padding: 14, marginBottom: 10 },
  productHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    gap: 8,
  },
  productName: { flex: 1, fontSize: 15, fontWeight: '700', color: '#2c3e50' },
  badge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 20 },
  badgeText: { fontSize: 11, fontWeight: '700' },
  productMetaRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 14, marginTop: 8 },
  productMeta: { fontSize: 12, color: '#5d6d7e' },
  orderBox: { marginTop: 10, padding: 10, borderRadius: 10, backgroundColor: '#f0f7ff' },
  orderLabel: { fontSize: 12, color: '#2980b9', flex: 1 },
  orderValue: { fontSize: 13, fontWeight: '700', color: '#1f618d' },
  footerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 10,
    flexWrap: 'wrap',
    gap: 6,
  },
  badgeRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  miniBadge: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 20 },
  miniBadgeText: { fontSize: 10, fontWeight: '700' },
  confidence: { fontSize: 11, color: '#95a5a6' },
  aiNote: { marginTop: 10, paddingLeft: 10, borderLeftWidth: 3 },
  aiNoteTitle: { fontSize: 12, fontWeight: '700', color: '#2c3e50' },
  aiNoteText: { fontSize: 12, color: '#5d6d7e', marginTop: 3, lineHeight: 17 },
  listRow: { flexDirection: 'row', gap: 8, marginBottom: 6 },
  listBullet: { fontSize: 14, fontWeight: '700' },
  listText: { flex: 1, fontSize: 12, color: '#5d6d7e', lineHeight: 17 },
  generated: { textAlign: 'center', fontSize: 11, color: '#bdc3c7', marginTop: 8 },
});
