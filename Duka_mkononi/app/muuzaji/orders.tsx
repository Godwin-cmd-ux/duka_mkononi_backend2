import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { useCallback, useEffect, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    FlatList,
    Modal,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLang } from '../../context/LanguageContext';
import LogoutButton from '../../components/logout-button';

import { API_BASE_URL } from '../../constants/api';
import { getCache, setCache } from '../../db/cache';
import { registerLive } from '../../lib/syncer';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';

// Allowed forward transitions — mirrors OrderService::TRANSITIONS on the server.
// The server is the source of truth; this only decides which buttons to show.
const TRANSITIONS: Record<string, string[]> = {
  pending: ['confirmed', 'cancelled'],
  confirmed: ['processing', 'cancelled'],
  processing: ['ready', 'cancelled'],
  ready: ['out_for_delivery', 'delivered', 'cancelled'],
  out_for_delivery: ['delivered', 'cancelled'],
  delivered: ['completed'],
  completed: [],
  cancelled: [],
};

const FILTERS = [
  '', 'pending', 'confirmed', 'processing', 'ready',
  'out_for_delivery', 'delivered', 'completed', 'cancelled',
];

const STATUS_COLORS: Record<string, { bg: string; fg: string }> = {
  pending: { bg: '#fffbeb', fg: '#b45309' },
  confirmed: { bg: '#eff6ff', fg: '#1d4ed8' },
  processing: { bg: '#eff6ff', fg: '#1d4ed8' },
  ready: { bg: '#eff6ff', fg: '#1d4ed8' },
  out_for_delivery: { bg: '#eff6ff', fg: '#1d4ed8' },
  delivered: { bg: '#ecfdf5', fg: '#047857' },
  completed: { bg: '#ecfdf5', fg: '#047857' },
  cancelled: { bg: '#fef2f2', fg: '#b91c1c' },
};

export default function OrdersScreen() {
  const { t, lang } = useLang();

  const localeMap: Record<string, string> = {
    sw: 'sw-TZ', en: 'en', fr: 'fr-FR', hi: 'hi-IN',
    ur: 'ur-PK', es: 'es-ES', de: 'de-DE', zh: 'zh-CN',
  };

  const [orders, setOrders] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [statusFilter, setStatusFilter] = useState('');
  const [search, setSearch] = useState('');

  const [detailVisible, setDetailVisible] = useState(false);
  const [selectedOrder, setSelectedOrder] = useState<any>(null);
  const [history, setHistory] = useState<any[]>([]);
  const [detailLoading, setDetailLoading] = useState(false);
  const [updating, setUpdating] = useState(false);
  const [note, setNote] = useState('');

  const formatCurrency = (amount: number) =>
    `TZS ${(Number(amount) || 0).toLocaleString('en-TZ', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    })}`;

  const formatDate = (value?: string) => {
    if (!value) return '-';
    const date = new Date(value);
    if (isNaN(date.getTime())) return String(value);
    return date.toLocaleString(localeMap[lang] || 'sw-TZ', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    });
  };

  const statusLabel = (status: string) => t('orders.status_' + status);
  const statusColor = (status: string) =>
    STATUS_COLORS[status] || { bg: '#f1f5f9', fg: '#64748b' };

  const loadOrders = useCallback(async (isRefresh = false) => {
    try {
      if (isRefresh) setRefreshing(true);
      else setLoading(true);

      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        Alert.alert(t('app.error'), t('orders.error_auth'));
        setLoading(false);
        setRefreshing(false);
        return;
      }

      const cached = await getCache<any[]>('seller:orders');
      if (cached) setOrders(cached);

      const response = await fetchWithTimeout(`${API_BASE_URL}/api/seller/orders`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'ngrok-skip-browser-warning': 'true',
        },
      });

      if (!response.ok) throw new Error(`API ${response.status}`);

      const data = await response.json();
      const list = Array.isArray(data?.data) ? data.data : [];
      setOrders(list);
      setCache('seller:orders', list).catch(() => {});
    } catch (error) {
      console.error('❌ Error loading orders:', error);
      const cached = await getCache<any[]>('seller:orders');
      if (!cached) Alert.alert(t('app.error'), t('orders.error_load'));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [t]);

  useEffect(() => {
    loadOrders();
  }, [loadOrders]);

  useEffect(() => {
    const stop = registerLive<any[]>(
      'seller:orders',
      async () => {
        const token = await AsyncStorage.getItem('userToken');
        const res = await fetchWithTimeout(`${API_BASE_URL}/api/seller/orders`, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'ngrok-skip-browser-warning': 'true',
          },
        });
        if (!res.ok) throw new Error('sync failed');
        const data = await res.json();
        return Array.isArray(data?.data) ? data.data : [];
      },
      (data) => { setOrders(data); setLoading(false); }
    );
    return stop;
  }, []);

  const openOrder = useCallback(async (order: any) => {
    setSelectedOrder(order);
    setHistory([]);
    setNote('');
    setDetailVisible(true);
    setDetailLoading(true);
    try {
      const token = await AsyncStorage.getItem('userToken');
      const res = await fetchWithTimeout(`${API_BASE_URL}/api/seller/orders/${order.id}`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'ngrok-skip-browser-warning': 'true',
        },
      });
      if (res.ok) {
        const data = await res.json();
        if (data?.order) setSelectedOrder(data.order);
        setHistory(Array.isArray(data?.history) ? data.history : []);
      }
    } catch (error) {
      console.error('❌ Error loading order detail:', error);
    } finally {
      setDetailLoading(false);
    }
  }, []);

  const changeStatus = async (orderId: string, status: string) => {
    try {
      if (!(await requireNetwork())) return;
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        Alert.alert(t('app.error'), t('orders.error_auth'));
        return;
      }

      setUpdating(true);
      const res = await fetchWithTimeout(`${API_BASE_URL}/api/seller/orders/${orderId}/status`, {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'ngrok-skip-browser-warning': 'true',
        },
        body: JSON.stringify({ status }),
      });

      const text = await res.text();
      if (!res.ok) {
        let message = t('orders.error_update');
        try {
          const parsed = JSON.parse(text);
          message = parsed?.error || parsed?.message || message;
        } catch {}
        throw new Error(message);
      }

      const parsed = JSON.parse(text);
      Alert.alert(t('app.success'), t('orders.status_updated'));
      if (parsed?.order) setSelectedOrder((prev: any) => ({ ...prev, ...parsed.order }));
      await loadOrders(true);
      // Refresh history for the modal.
      setDetailVisible(false);
      setTimeout(() => openOrder({ id: orderId }), 50);
    } catch (error: any) {
      console.error('❌ Error updating order status:', error);
      Alert.alert(t('app.error'), error?.message || t('orders.error_update'));
    } finally {
      setUpdating(false);
    }
  };

  const saveNote = async () => {
    if (!selectedOrder || !note.trim()) return;
    try {
      if (!(await requireNetwork())) return;
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        Alert.alert(t('app.error'), t('orders.error_auth'));
        return;
      }

      setUpdating(true);
      const res = await fetchWithTimeout(`${API_BASE_URL}/api/seller/orders/${selectedOrder.id}/notes`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'ngrok-skip-browser-warning': 'true',
        },
        body: JSON.stringify({ note: note.trim() }),
      });

      if (!res.ok) throw new Error(t('orders.error_update'));

      setNote('');
      Alert.alert(t('app.success'), t('orders.note_saved'));
      const currentId = selectedOrder.id;
      setDetailVisible(false);
      setTimeout(() => openOrder({ id: currentId }), 50);
    } catch (error: any) {
      console.error('❌ Error saving note:', error);
      Alert.alert(t('app.error'), error?.message || t('orders.error_update'));
    } finally {
      setUpdating(false);
    }
  };

  const filtered = orders.filter((o) => {
    if (statusFilter && o.status !== statusFilter) return false;
    if (!search.trim()) return true;
    const needle = search.trim().toLowerCase();
    const haystack = `${o.order_reference || ''} ${o.customer_name || ''} ${o.customer_phone || ''}`.toLowerCase();
    return haystack.includes(needle);
  });

  const totalValue = filtered.reduce((sum, o) => sum + (Number(o.total_amount) || 0), 0);

  const renderOrder = ({ item }: { item: any }) => {
    const color = statusColor(item.status);
    return (
      <TouchableOpacity style={styles.orderCard} onPress={() => openOrder(item)} activeOpacity={0.85}>
        <View style={styles.orderHead}>
          <Text style={styles.reference}>#{item.order_reference || (item.id || '').slice(0, 8)}</Text>
          <View style={[styles.badge, { backgroundColor: color.bg }]}>
            <Text style={[styles.badgeText, { color: color.fg }]}>{statusLabel(item.status)}</Text>
          </View>
        </View>

        <View style={styles.orderRow}>
          <Ionicons name="person" size={15} color="#7f8c8d" />
          <Text style={styles.orderRowText} numberOfLines={1}>
            {item.customer_name || t('orders.unknown_customer')}
          </Text>
        </View>

        {item.customer_phone ? (
          <View style={styles.orderRow}>
            <Ionicons name="call" size={14} color="#7f8c8d" />
            <Text style={styles.orderRowText}>{item.customer_phone}</Text>
          </View>
        ) : null}

        <View style={styles.orderFooter}>
          <Text style={styles.orderDate}>{formatDate(item.placed_at)}</Text>
          <Text style={styles.orderTotal}>{formatCurrency(item.total_amount)}</Text>
        </View>
      </TouchableOpacity>
    );
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.screenSafe} edges={['top']}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#2ecc71" />
          <Text style={styles.loadingText}>{t('orders.loading')}</Text>
        </View>
      </SafeAreaView>
    );
  }

  const nextStatuses = selectedOrder ? (TRANSITIONS[selectedOrder.status] || []) : [];

  return (
    <SafeAreaView style={styles.screenSafe} edges={['top']}>
      <View style={styles.container}>
        {/* HEADER */}
        <View style={styles.header}>
          <View style={styles.headerTop}>
            <Text style={styles.title}>{t('orders.title')}</Text>
            <View style={styles.headerActions}>
              <TouchableOpacity onPress={() => loadOrders(true)} style={styles.refreshButton}>
                <Ionicons name="refresh-outline" size={22} color="#2ecc71" />
              </TouchableOpacity>
              <LogoutButton iconOnly />
            </View>
          </View>

          <TextInput
            style={styles.searchInput}
            placeholder={t('orders.search_placeholder')}
            value={search}
            onChangeText={setSearch}
            placeholderTextColor="#95a5a6"
          />

          <View style={styles.summaryRow}>
            <Text style={styles.summaryText}>
              {t('orders.count_label')}: <Text style={styles.summaryStrong}>{filtered.length}</Text>
            </Text>
            <Text style={styles.summaryText}>
              {t('orders.total_label')}: <Text style={styles.summaryStrong}>{formatCurrency(totalValue)}</Text>
            </Text>
          </View>
        </View>

        {/* FILTER CHIPS */}
        <View>
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            style={styles.filtersScroll}
            contentContainerStyle={styles.filtersContent}
            keyboardShouldPersistTaps="handled"
          >
            {FILTERS.map((f) => {
              const active = statusFilter === f;
              return (
                <TouchableOpacity
                  key={f || 'all'}
                  style={[styles.filterChip, active && styles.filterChipActive]}
                  onPress={() => setStatusFilter(f)}
                  activeOpacity={0.8}
                >
                  <Text style={[styles.filterText, active && styles.filterTextActive]}>
                    {f === '' ? t('orders.filter_all') : statusLabel(f)}
                  </Text>
                </TouchableOpacity>
              );
            })}
          </ScrollView>
        </View>

        {/* LIST */}
        {filtered.length === 0 ? (
          <View style={styles.emptyContainer}>
            <Ionicons name="receipt-outline" size={64} color="#bdc3c7" />
            <Text style={styles.emptyTitle}>{t('orders.empty')}</Text>
            <Text style={styles.emptyText}>{t('orders.empty_hint')}</Text>
          </View>
        ) : (
          <FlatList
            data={filtered}
            renderItem={renderOrder}
            keyExtractor={(item, index) => item.id || String(index)}
            contentContainerStyle={styles.listContent}
            showsVerticalScrollIndicator={false}
            refreshing={refreshing}
            onRefresh={() => loadOrders(true)}
          />
        )}

        {/* DETAIL MODAL */}
        <Modal
          visible={detailVisible}
          animationType="slide"
          transparent
          onRequestClose={() => setDetailVisible(false)}
        >
          <View style={styles.modalOverlay}>
            <View style={styles.modalContent}>
              <View style={styles.modalHeader}>
                <Text style={styles.modalTitle}>{t('orders.detail_title')}</Text>
                <TouchableOpacity onPress={() => setDetailVisible(false)}>
                  <Ionicons name="close" size={24} color="#666" />
                </TouchableOpacity>
              </View>

              {detailLoading ? (
                <View style={styles.modalLoading}>
                  <ActivityIndicator size="large" color="#2ecc71" />
                </View>
              ) : (
                <ScrollView style={styles.modalBody} keyboardShouldPersistTaps="handled">
                  {selectedOrder ? (
                    <>
                      <View style={styles.detailTop}>
                        <Text style={styles.detailRef}>#{selectedOrder.order_reference || (selectedOrder.id || '').slice(0, 8)}</Text>
                        <View style={[styles.badge, { backgroundColor: statusColor(selectedOrder.status).bg }]}>
                          <Text style={[styles.badgeText, { color: statusColor(selectedOrder.status).fg }]}>
                            {statusLabel(selectedOrder.status)}
                          </Text>
                        </View>
                      </View>

                      <View style={styles.detailRow}>
                        <Text style={styles.detailLabel}>{t('orders.customer')}</Text>
                        <Text style={styles.detailValue}>{selectedOrder.customer_name || t('orders.unknown_customer')}</Text>
                      </View>
                      <View style={styles.detailRow}>
                        <Text style={styles.detailLabel}>{t('orders.phone')}</Text>
                        <Text style={styles.detailValue}>{selectedOrder.customer_phone || '-'}</Text>
                      </View>
                      <View style={styles.detailRow}>
                        <Text style={styles.detailLabel}>{t('orders.placed_at')}</Text>
                        <Text style={styles.detailValue}>{formatDate(selectedOrder.placed_at)}</Text>
                      </View>
                      {selectedOrder.customer_note ? (
                        <View style={styles.noteBox}>
                          <Text style={styles.noteBoxLabel}>{t('orders.customer_note')}</Text>
                          <Text style={styles.noteBoxText}>{selectedOrder.customer_note}</Text>
                        </View>
                      ) : null}

                      {/* ITEMS */}
                      <Text style={styles.sectionTitle}>{t('orders.items')}</Text>
                      {Array.isArray(selectedOrder.items) && selectedOrder.items.length > 0 ? (
                        selectedOrder.items.map((item: any, idx: number) => (
                          <View key={item.id || idx} style={styles.itemRow}>
                            <Text style={styles.itemName} numberOfLines={1}>
                              {item.product_name || item.name || t('orders.unknown_product')}
                            </Text>
                            <Text style={styles.itemQty}>
                              {item.quantity} × {formatCurrency(item.unit_price)}
                            </Text>
                          </View>
                        ))
                      ) : (
                        <Text style={styles.detailValue}>-</Text>
                      )}

                      <View style={styles.totalRow}>
                        <Text style={styles.totalRowLabel}>{t('orders.total')}</Text>
                        <Text style={styles.totalRowValue}>{formatCurrency(selectedOrder.total_amount)}</Text>
                      </View>

                      {/* STATUS ACTIONS */}
                      {nextStatuses.length > 0 && (
                        <>
                          <Text style={styles.sectionTitle}>{t('orders.change_status')}</Text>
                          <View style={styles.statusButtons}>
                            {nextStatuses.map((s) => (
                              <TouchableOpacity
                                key={s}
                                style={[styles.statusButton, { backgroundColor: statusColor(s).bg }]}
                                onPress={() => changeStatus(selectedOrder.id, s)}
                                disabled={updating}
                                activeOpacity={0.8}
                              >
                                <Text style={[styles.statusButtonText, { color: statusColor(s).fg }]}>
                                  {statusLabel(s)}
                                </Text>
                              </TouchableOpacity>
                            ))}
                          </View>
                        </>
                      )}

                      {/* ADD NOTE */}
                      <Text style={styles.sectionTitle}>{t('orders.add_note')}</Text>
                      <TextInput
                        style={styles.noteInput}
                        placeholder={t('orders.note_placeholder')}
                        value={note}
                        onChangeText={setNote}
                        multiline
                        numberOfLines={3}
                        textAlignVertical="top"
                        placeholderTextColor="#95a5a6"
                      />
                      <TouchableOpacity
                        style={[styles.saveNoteButton, (!note.trim() || updating) && styles.buttonDisabled]}
                        onPress={saveNote}
                        disabled={!note.trim() || updating}
                        activeOpacity={0.85}
                      >
                        {updating ? (
                          <ActivityIndicator size="small" color="white" />
                        ) : (
                          <Text style={styles.saveNoteButtonText}>{t('orders.save_note')}</Text>
                        )}
                      </TouchableOpacity>

                      {/* HISTORY */}
                      {history.length > 0 && (
                        <>
                          <Text style={styles.sectionTitle}>{t('orders.history')}</Text>
                          {history.map((h, idx) => (
                            <View key={idx} style={styles.historyRow}>
                              <View style={styles.historyDot} />
                              <View style={styles.historyBody}>
                                <Text style={styles.historyStatus}>{statusLabel(h.status)}</Text>
                                <Text style={styles.historyDate}>{formatDate(h.created_at)}</Text>
                                {h.note ? <Text style={styles.historyNote}>{h.note}</Text> : null}
                              </View>
                            </View>
                          ))}
                        </>
                      )}
                    </>
                  ) : null}
                </ScrollView>
              )}
            </View>
          </View>
        </Modal>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screenSafe: { flex: 1, backgroundColor: '#f8f9fa' },
  container: { flex: 1, backgroundColor: '#f8f9fa' },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 30 },
  loadingText: { marginTop: 15, fontSize: 16, color: '#666' },

  header: {
    backgroundColor: 'white',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#e9ecef',
  },
  headerTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  headerActions: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  title: { fontSize: 22, fontWeight: 'bold', color: '#2c3e50', flex: 1 },
  refreshButton: {
    padding: 8,
    backgroundColor: '#eafaf1',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#d5f5e3',
  },
  searchInput: {
    backgroundColor: '#f8f9fa',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 10,
    paddingHorizontal: 14,
    paddingVertical: 11,
    fontSize: 15,
    color: '#2c3e50',
  },
  summaryRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 10,
  },
  summaryText: { fontSize: 13, color: '#7f8c8d' },
  summaryStrong: { color: '#2c3e50', fontWeight: '700' },

  filtersScroll: { maxHeight: 52, backgroundColor: 'white' },
  filtersContent: { paddingHorizontal: 12, paddingVertical: 10, gap: 8 },
  filterChip: {
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderRadius: 999,
    backgroundColor: '#f1f5f9',
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  filterChipActive: { backgroundColor: '#2ecc71', borderColor: '#2ecc71' },
  filterText: { fontSize: 13, color: '#5d6d7e', fontWeight: '600' },
  filterTextActive: { color: 'white' },

  listContent: { padding: 15, paddingBottom: 30 },
  orderCard: {
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 15,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#e9ecef',
  },
  orderHead: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  reference: { fontSize: 15, fontWeight: 'bold', color: '#2c3e50' },
  badge: { paddingHorizontal: 10, paddingVertical: 4, borderRadius: 999 },
  badgeText: { fontSize: 12, fontWeight: '700' },
  orderRow: { flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 5 },
  orderRowText: { fontSize: 14, color: '#2c3e50', flex: 1 },
  orderFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 8,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: '#f1f5f9',
  },
  orderDate: { fontSize: 13, color: '#95a5a6' },
  orderTotal: { fontSize: 16, fontWeight: 'bold', color: '#27ae60' },

  emptyContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
  emptyTitle: { fontSize: 20, fontWeight: 'bold', color: '#2c3e50', marginTop: 16 },
  emptyText: { fontSize: 15, color: '#7f8c8d', textAlign: 'center', marginTop: 8, lineHeight: 22 },

  modalOverlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.5)', justifyContent: 'center', alignItems: 'center' },
  modalContent: { backgroundColor: 'white', borderRadius: 16, width: '92%', maxHeight: '85%' },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 18,
    borderBottomWidth: 1,
    borderBottomColor: '#e9ecef',
  },
  modalTitle: { fontSize: 19, fontWeight: 'bold', color: '#2c3e50' },
  modalLoading: { padding: 40, alignItems: 'center' },
  modalBody: { padding: 18 },

  detailTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 14,
  },
  detailRef: { fontSize: 17, fontWeight: 'bold', color: '#2c3e50' },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 6,
    borderBottomWidth: 1,
    borderBottomColor: '#f8f9fa',
  },
  detailLabel: { fontSize: 14, color: '#7f8c8d' },
  detailValue: { fontSize: 14, color: '#2c3e50', fontWeight: '500', flexShrink: 1, textAlign: 'right' },
  noteBox: {
    marginTop: 12,
    padding: 12,
    backgroundColor: '#f8f9fa',
    borderRadius: 10,
    borderLeftWidth: 3,
    borderLeftColor: '#2ecc71',
  },
  noteBoxLabel: { fontSize: 12, color: '#7f8c8d', fontWeight: '700', marginBottom: 4 },
  noteBoxText: { fontSize: 14, color: '#2c3e50' },

  sectionTitle: { fontSize: 15, fontWeight: '700', color: '#2c3e50', marginTop: 18, marginBottom: 8 },
  itemRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 7,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
  },
  itemName: { fontSize: 14, color: '#2c3e50', flex: 1, marginRight: 8 },
  itemQty: { fontSize: 13, color: '#7f8c8d' },
  totalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 2,
    borderTopColor: '#ecf0f1',
  },
  totalRowLabel: { fontSize: 16, fontWeight: '700', color: '#2c3e50' },
  totalRowValue: { fontSize: 18, fontWeight: 'bold', color: '#27ae60' },

  statusButtons: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  statusButton: { paddingHorizontal: 16, paddingVertical: 10, borderRadius: 10 },
  statusButtonText: { fontSize: 14, fontWeight: '700' },

  noteInput: {
    backgroundColor: '#f8f9fa',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: 10,
    padding: 12,
    fontSize: 15,
    color: '#2c3e50',
    minHeight: 80,
  },
  saveNoteButton: {
    marginTop: 12,
    backgroundColor: '#2ecc71',
    paddingVertical: 14,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  saveNoteButtonText: { color: 'white', fontSize: 15, fontWeight: '700' },
  buttonDisabled: { opacity: 0.5 },

  historyRow: { flexDirection: 'row', marginBottom: 12 },
  historyDot: {
    width: 10, height: 10, borderRadius: 5, backgroundColor: '#2ecc71',
    marginTop: 4, marginRight: 10,
  },
  historyBody: { flex: 1 },
  historyStatus: { fontSize: 14, fontWeight: '600', color: '#2c3e50' },
  historyDate: { fontSize: 12, color: '#95a5a6', marginTop: 2 },
  historyNote: { fontSize: 13, color: '#7f8c8d', marginTop: 4, fontStyle: 'italic' },
});
