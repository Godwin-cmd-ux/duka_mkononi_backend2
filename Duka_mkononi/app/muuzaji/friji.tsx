import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useFocusEffect } from 'expo-router';
import React, { useCallback, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Modal,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { API_BASE_URL } from '../../constants/api';
import { useFeatures } from '../../context/FeatureContext';
import { useLang } from '../../context/LanguageContext';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';
import { useTheme, ThemeColors } from '../../lib/theme';

interface Fridge {
  id: string;
  name: string;
  status: string;
  total_units: number;
  product_count: number;
  low_stock_count: number;
}

interface AlertRow {
  id?: string;
  alert_type: 'business_low' | 'outside_low' | 'fridge_low';
  product_id: string;
  product_name?: string | null;
  fridge_id?: string | null;
  quantity: number;
  threshold: number;
}

interface Movement {
  id: string;
  movement_type: string;
  quantity: number;
  product_name?: string | null;
  product_id?: string | null;
  fridge_name?: string | null;
  source_location: string;
  created_at?: string;
}

interface FridgeStockRow {
  product_id: string;
  product_name?: string | null;
  quantity: number;
  business_stock: number;
  min_stock: number;
  category?: string | null;
}

interface AvailableProduct {
  product_id: string;
  name: string;
  stock: number;
  inside_stock: number;
  outside_stock: number;
  fridges: { fridge_id: string; name: string; quantity: number; min_stock: number }[];
}

export default function FrijiScreen() {
  const { colors: tc } = useTheme();
  const styles = useMemo(() => makeStyles(tc), [tc]);
  const { t } = useLang();
  const { isEnabled } = useFeatures();
  const fridgeEnabled = isEnabled('fridge');

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [fridges, setFridges] = useState<Fridge[]>([]);
  const [summary, setSummary] = useState<any>({});
  const [alerts, setAlerts] = useState<AlertRow[]>([]);
  const [movements, setMovements] = useState<Movement[]>([]);

  const [nameModal, setNameModal] = useState(false);
  const [editId, setEditId] = useState<string | null>(null);
  const [nameValue, setNameValue] = useState('');
  const [saving, setSaving] = useState(false);

  const [detailId, setDetailId] = useState<string | null>(null);
  const [detailName, setDetailName] = useState('');
  const [detailStock, setDetailStock] = useState<FridgeStockRow[]>([]);
  const [detailMovements, setDetailMovements] = useState<Movement[]>([]);
  const [detailLoading, setDetailLoading] = useState(false);

  const [transferOpen, setTransferOpen] = useState(false);
  const [transferDirection, setTransferDirection] = useState<'in' | 'out'>('in');
  const [transferFridge, setTransferFridge] = useState<Fridge | null>(null);
  const [products, setProducts] = useState<AvailableProduct[]>([]);
  const [selectedProduct, setSelectedProduct] = useState<string>('');
  const [scanCode, setScanCode] = useState('');
  const [quantity, setQuantity] = useState('1');
  const [minStock, setMinStock] = useState('0');
  const [notes, setNotes] = useState('');
  const [transferring, setTransferring] = useState(false);

  const [thresholdOpen, setThresholdOpen] = useState(false);
  const [thresholdValue, setThresholdValue] = useState('0');
  const [thresholdTarget, setThresholdTarget] = useState<{ fridgeId: string; productId: string } | null>(null);

  const tr = useCallback((key: string) => {
    const out = t('muuzaji_friji.' + key);
    return out === 'muuzaji_friji.' + key ? key : out;
  }, [t]);

  const api = useCallback(async (path: string, options: any = {}) => {
    const token = await AsyncStorage.getItem('userToken');
    const response = await fetchWithTimeout(`${API_BASE_URL}${path}`, {
      ...options,
      headers: {
        Authorization: `Bearer ${token || ''}`,
        'Content-Type': 'application/json',
        'ngrok-skip-browser-warning': 'true',
        ...(options.headers || {}),
      },
    });
    const text = await response.text();
    let data: any = {};
    try { data = text ? JSON.parse(text) : {}; } catch { data = { error: text }; }
    if (!response.ok || data.success === false) {
      throw new Error(data.error || `Server error ${response.status}`);
    }
    return data;
  }, []);

  const load = useCallback(async (showSpinner = true) => {
    // The tab is hidden when the module is off; never call the API from a
    // stale screen (the backend would answer 403 anyway).
    if (!fridgeEnabled) {
      setLoading(false);
      setRefreshing(false);
      return;
    }

    if (showSpinner) setLoading(true);
    try {
      await requireNetwork();
      const data = await api('/api/fridges');
      setFridges(data.fridges || []);
      setSummary(data.summary || {});
      setAlerts(data.alerts || []);
      setMovements(data.recent_movements || []);
    } catch (error: any) {
      Alert.alert(tr('title'), error?.message || tr('load_error'));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [api, tr, fridgeEnabled]);

  useFocusEffect(useCallback(() => { load(); }, [load]));

  const openDetail = async (fridge: Fridge) => {
    setDetailId(fridge.id);
    setDetailName(fridge.name);
    setDetailStock([]);
    setDetailMovements([]);
    setDetailLoading(true);
    try {
      const data = await api(`/api/fridges/${encodeURIComponent(fridge.id)}`);
      setDetailName(data.fridge?.name || fridge.name);
      setDetailStock(data.stock || []);
      setDetailMovements(data.movements || []);
    } catch (error: any) {
      Alert.alert(tr('title'), error?.message || tr('load_error'));
      setDetailId(null);
    } finally {
      setDetailLoading(false);
    }
  };

  const openAdd = () => {
    setEditId(null);
    setNameValue('');
    setNameModal(true);
  };

  const openRename = () => {
    if (!detailId) return;
    setEditId(detailId);
    setNameValue(detailName);
    setNameModal(true);
  };

  const saveName = async () => {
    const name = nameValue.trim();
    if (!name) { Alert.alert(tr('title'), tr('name_required')); return; }
    setSaving(true);
    try {
      if (editId) {
        await api(`/api/fridges/${encodeURIComponent(editId)}`, { method: 'PUT', body: JSON.stringify({ name }) });
      } else {
        await api('/api/fridges', { method: 'POST', body: JSON.stringify({ name }) });
      }
      setNameModal(false);
      if (editId) setDetailName(name);
      await load(false);
    } catch (error: any) {
      Alert.alert(tr('title'), error?.message || tr('load_error'));
    } finally {
      setSaving(false);
    }
  };

  const archiveFridge = () => {
    if (!detailId) return;
    Alert.alert(tr('archive'), tr('archive_confirm'), [
      { text: tr('cancel'), style: 'cancel' },
      {
        text: tr('archive'),
        style: 'destructive',
        onPress: async () => {
          try {
            await api(`/api/fridges/${encodeURIComponent(detailId)}`, { method: 'DELETE' });
            setDetailId(null);
            await load(false);
          } catch (error: any) {
            Alert.alert(tr('title'), error?.message || tr('load_error'));
          }
        },
      },
    ]);
  };

  const openTransfer = async (fridge: Fridge, direction: 'in' | 'out') => {
    setTransferFridge(fridge);
    setTransferDirection(direction);
    setSelectedProduct('');
    setQuantity('1');
    setMinStock('0');
    setNotes('');
    setTransferOpen(true);
    try {
      const data = await api('/api/fridges/availability');
      const list: AvailableProduct[] = data.products || [];
      setProducts(list.filter(p => direction === 'in' ? Number(p.outside_stock) > 0 : p.fridges.some(f => f.fridge_id === fridge.id && f.quantity > 0)));
    } catch (error: any) {
      Alert.alert(tr('title'), error?.message || tr('load_error'));
    }
  };

  // Scan a barcode into the transfer form. It only selects an EXISTING product
  // from the current branch; an unknown code never creates inventory.
  const scanTransferCode = async (raw: string) => {
    const code = String(raw || '').trim();
    if (!code) return;
    setScanCode('');
    try {
      const data = await api(`/api/products/lookup?barcode=${encodeURIComponent(code)}&purpose=fridge_transfer`);
      const product = products.find((p) => String(p.product_id) === String(data.product?.id));
      if (!product) {
        Alert.alert(tr('title'), 'Bidhaa hii haipatikani kwenye stock ya tawi lako.');
        return;
      }
      setSelectedProduct(product.product_id);
      setQuantity('1');
    } catch (error: any) {
      Alert.alert(tr('title'), error?.message || 'Bidhaa haijapatikana kwa barcode hii');
    }
  };

  const submitTransfer = async () => {
    if (!transferFridge || !selectedProduct || !(Number(quantity) > 0)) {
      Alert.alert(tr('title'), tr('invalid'));
      return;
    }
    setTransferring(true);
    try {
      const path = `/api/fridges/${encodeURIComponent(transferFridge.id)}/stock${transferDirection === 'out' ? '/remove' : ''}`;
      await api(path, {
        method: 'POST',
        body: JSON.stringify({
          product_id: selectedProduct,
          quantity: Number(quantity),
          min_stock: Number(minStock || 0),
          notes: notes,
        }),
      });
      setTransferOpen(false);
      await load(false);
      if (detailId) {
        const fridge = fridges.find(f => f.id === detailId) || transferFridge;
        if (fridge) await openDetail(fridge);
      }
    } catch (error: any) {
      Alert.alert(tr('title'), error?.message || tr('load_error'));
    } finally {
      setTransferring(false);
    }
  };

  const openThreshold = (fridgeId: string, productId: string, current: number) => {
    setThresholdTarget({ fridgeId, productId });
    setThresholdValue(String(current || 0));
    setThresholdOpen(true);
  };

  const saveThreshold = async () => {
    if (!thresholdTarget) return;
    try {
      await api(`/api/fridges/${encodeURIComponent(thresholdTarget.fridgeId)}/stock/${encodeURIComponent(thresholdTarget.productId)}/threshold`, {
        method: 'PUT',
        body: JSON.stringify({ min_stock: Number(thresholdValue || 0) }),
      });
      setThresholdOpen(false);
      if (detailId) {
        const fridge = fridges.find(f => f.id === detailId);
        if (fridge) await openDetail(fridge);
      }
    } catch (error: any) {
      Alert.alert(tr('title'), error?.message || tr('load_error'));
    }
  };

  const inside = Number(summary.inside_units || 0);
  const outside = Number(summary.outside_units || 0);
  const business = Number(summary.business_units || 0);

  if (!fridgeEnabled) {
    return (
      <SafeAreaView style={styles.safe} edges={['top']}>
        <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center', padding: 28 }}>
          <Ionicons name="snow-outline" size={46} color={tc.muted2} />
          <Text style={{ fontSize: 17, fontWeight: '800', color: tc.ink, marginTop: 14, textAlign: 'center' }}>
            {tr('title')}
          </Text>
          <Text style={{ fontSize: 13, color: tc.muted2, marginTop: 8, textAlign: 'center', lineHeight: 20 }}>
            Kipengele cha Friji/Freezer kimezimwa na msimamizi wa biashara. Stock na historia yote imehifadhiwa.
          </Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(false); }} tintColor={tc.primary} />}
      >
        <View style={styles.header}>
          <View style={{ flex: 1 }}>
            <Text style={styles.title}>{tr('title')}</Text>
            <Text style={styles.subtitle}>{tr('subtitle')}</Text>
          </View>
          <TouchableOpacity style={styles.iconBtn} onPress={() => load()}>
            <Ionicons name="refresh" size={20} color={tc.primary} />
          </TouchableOpacity>
          <TouchableOpacity style={styles.addBtn} onPress={openAdd}>
            <Ionicons name="add" size={20} color="#fff" />
            <Text style={styles.addBtnText}>{tr('add')}</Text>
          </TouchableOpacity>
        </View>

        {loading ? (
          <View style={styles.loading}><ActivityIndicator color={tc.primary} /><Text style={styles.muted}>{tr('loading')}</Text></View>
        ) : (
          <>
            <View style={styles.statsRow}>
              <Stat label={tr('business')} value={business} color={tc.ink} styles={styles} />
              <Stat label={tr('outside')} value={outside} color="#2563eb" styles={styles} />
              <Stat label={tr('inside')} value={inside} color="#15803d" styles={styles} />
            </View>
            <View style={styles.statsRow}>
              <Stat label={tr('fridges')} value={Number(summary.fridge_count || 0)} color={tc.ink} styles={styles} />
              <Stat label={tr('products')} value={Number(summary.product_count || 0)} color={tc.ink} styles={styles} />
              <Stat label={tr('alerts')} value={alerts.length} color={alerts.length ? '#d97706' : '#15803d'} styles={styles} />
            </View>

            {fridges.length === 0 ? (
              <View style={styles.emptyCard}>
                <Ionicons name="snow-outline" size={44} color="#94a3b8" />
                <Text style={styles.emptyTitle}>{tr('empty')}</Text>
                <Text style={styles.muted}>{tr('empty_hint')}</Text>
              </View>
            ) : fridges.map(fridge => (
              <TouchableOpacity key={fridge.id} style={styles.card} onPress={() => openDetail(fridge)} activeOpacity={0.85}>
                <View style={styles.cardTop}>
                  <View style={styles.fridgeIcon}><Ionicons name="snow" size={22} color="#2563eb" /></View>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.cardTitle}>{fridge.name}</Text>
                    <Text style={styles.muted}>{fridge.status === 'archived' ? tr('archived') : tr('active')}</Text>
                  </View>
                  {fridge.low_stock_count > 0 && <View style={styles.warnPill}><Text style={styles.warnText}>{fridge.low_stock_count} {tr('low')}</Text></View>}
                </View>
                <View style={styles.cardNumbers}>
                  <Mini label={tr('units')} value={fridge.total_units} />
                  <Mini label={tr('products')} value={fridge.product_count} />
                  <Mini label={tr('low')} value={fridge.low_stock_count} />
                </View>
                <View style={styles.cardActions}>
                  <TouchableOpacity style={styles.smallBtn} onPress={() => openTransfer(fridge, 'in')}>
                    <Ionicons name="arrow-down-circle-outline" size={16} color={tc.primary} /><Text style={styles.smallBtnText}>{tr('transfer_in')}</Text>
                  </TouchableOpacity>
                  <TouchableOpacity style={styles.smallBtn} onPress={() => openTransfer(fridge, 'out')}>
                    <Ionicons name="arrow-up-circle-outline" size={16} color="#c2410c" /><Text style={styles.smallBtnText}>{tr('transfer_out')}</Text>
                  </TouchableOpacity>
                </View>
              </TouchableOpacity>
            ))}

            <Text style={styles.sectionTitle}>{tr('alerts')}</Text>
            <View style={styles.panel}>
              {alerts.length === 0 ? <Text style={styles.muted}>{tr('no_alerts')}</Text> : alerts.map((alert, index) => (
                <View key={`${alert.alert_type}-${alert.product_id}-${alert.fridge_id || 'x'}-${index}`} style={[styles.alertRow, alert.alert_type === 'business_low' && styles.alertRed, alert.alert_type === 'outside_low' && styles.alertBlue]}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.alertName}>{alert.product_name || alert.product_id}</Text>
                    <Text style={styles.muted}>{alert.alert_type === 'business_low' ? tr('alert_business') : alert.alert_type === 'outside_low' ? tr('alert_outside') : tr('alert_fridge')}</Text>
                  </View>
                  <Text style={styles.alertValue}>{alert.quantity} / {alert.threshold}</Text>
                </View>
              ))}
            </View>

            <Text style={styles.sectionTitle}>{tr('recent')}</Text>
            <View style={styles.panel}>
              {movements.length === 0 ? <Text style={styles.muted}>{tr('no_activity')}</Text> : movements.slice(0, 20).map(m => (
                <View key={m.id} style={styles.movementRow}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.alertName}>{m.product_name || m.product_id || ''}</Text>
                    <Text style={styles.muted}>{m.fridge_name || tr('outside')} · {m.movement_type}</Text>
                  </View>
                  <Text style={{ fontWeight: '800', color: m.source_location === 'fridge' ? '#c2410c' : '#15803d' }}>
                    {m.movement_type === 'transfer_out' ? '-' : '+'}{m.quantity}
                  </Text>
                </View>
              ))}
            </View>
          </>
        )}
      </ScrollView>

      {/* Add / rename fridge */}
      <Modal visible={nameModal} transparent animationType="fade" onRequestClose={() => setNameModal(false)}>
        <View style={styles.modalBackdrop}>
          <View style={[styles.modalCard, { backgroundColor: tc.card }]}>
            <Text style={styles.modalTitle}>{editId ? tr('rename') : tr('add')}</Text>
            <TextInput
              value={nameValue}
              onChangeText={setNameValue}
              placeholder={tr('ask_name')}
              placeholderTextColor={tc.muted}
              style={styles.input}
              autoFocus
            />
            <View style={styles.modalActions}>
              <TouchableOpacity style={styles.cancelBtn} onPress={() => setNameModal(false)}><Text style={styles.cancelText}>{tr('cancel')}</Text></TouchableOpacity>
              <TouchableOpacity style={styles.primaryBtn} onPress={saveName} disabled={saving}>
                <Text style={styles.primaryText}>{saving ? '...' : tr('save')}</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Detail */}
      <Modal visible={!!detailId} animationType="slide" onRequestClose={() => setDetailId(null)}>
        <SafeAreaView style={styles.safe}>
          <View style={styles.modalHead}>
            <TouchableOpacity onPress={() => setDetailId(null)}><Ionicons name="close" size={24} color={tc.ink} /></TouchableOpacity>
            <Text style={styles.modalTitle}>{detailName}</Text>
            <TouchableOpacity onPress={openRename}><Ionicons name="pencil" size={20} color={tc.primary} /></TouchableOpacity>
          </View>
          <ScrollView contentContainerStyle={styles.content}>
            {detailLoading ? <ActivityIndicator color={tc.primary} style={{ marginTop: 30 }} /> : (
              <>
                <View style={styles.cardActions}>
                  <TouchableOpacity style={styles.primaryBtn} onPress={() => { const f = fridges.find(x => x.id === detailId); if (f) openTransfer(f, 'in'); }}>
                    <Text style={styles.primaryText}>{tr('transfer_in')}</Text>
                  </TouchableOpacity>
                  <TouchableOpacity style={styles.cancelBtn} onPress={() => { const f = fridges.find(x => x.id === detailId); if (f) openTransfer(f, 'out'); }}>
                    <Text style={styles.cancelText}>{tr('transfer_out')}</Text>
                  </TouchableOpacity>
                  <TouchableOpacity style={[styles.cancelBtn, { borderColor: '#fecaca' }]} onPress={archiveFridge}>
                    <Text style={[styles.cancelText, { color: '#dc2626' }]}>{tr('archive')}</Text>
                  </TouchableOpacity>
                </View>

                <Text style={styles.sectionTitle}>{tr('products')}</Text>
                {detailStock.length === 0 ? <View style={styles.panel}><Text style={styles.muted}>{tr('no_stock')}</Text></View> : detailStock.map(row => (
                  <View key={row.product_id} style={styles.stockRow}>
                    <View style={{ flex: 1 }}>
                      <Text style={styles.alertName}>{row.product_name || row.product_id}</Text>
                      <Text style={styles.muted}>{tr('inside')}: {row.quantity} · {tr('business')}: {row.business_stock} · {tr('min_stock')}: {row.min_stock}</Text>
                    </View>
                    <TouchableOpacity style={styles.smallBtn} onPress={() => openThreshold(detailId!, row.product_id, row.min_stock)}>
                      <Text style={styles.smallBtnText}>{tr('threshold')}</Text>
                    </TouchableOpacity>
                  </View>
                ))}

                <Text style={styles.sectionTitle}>{tr('movements')}</Text>
                <View style={styles.panel}>
                  {detailMovements.length === 0 ? <Text style={styles.muted}>{tr('no_activity')}</Text> : detailMovements.map(m => (
                    <View key={m.id} style={styles.movementRow}>
                      <View style={{ flex: 1 }}>
                        <Text style={styles.alertName}>{m.product_name || ''}</Text>
                        <Text style={styles.muted}>{m.movement_type} · {(m.created_at || '').replace('T', ' ').slice(0, 16)}</Text>
                      </View>
                      <Text style={{ fontWeight: '800', color: m.source_location === 'fridge' ? '#c2410c' : '#15803d' }}>
                        {m.movement_type === 'transfer_out' ? '-' : '+'}{m.quantity}
                      </Text>
                    </View>
                  ))}
                </View>
              </>
            )}
          </ScrollView>
        </SafeAreaView>
      </Modal>

      {/* Transfer */}
      <Modal visible={transferOpen} animationType="slide" onRequestClose={() => setTransferOpen(false)}>
        <SafeAreaView style={styles.safe}>
          <View style={styles.modalHead}>
            <TouchableOpacity onPress={() => setTransferOpen(false)}><Ionicons name="close" size={24} color={tc.ink} /></TouchableOpacity>
            <Text style={styles.modalTitle}>{transferDirection === 'in' ? tr('transfer_in') : tr('transfer_out')}</Text>
            <View style={{ width: 24 }} />
          </View>
          <ScrollView contentContainerStyle={styles.content}>
            {isEnabled('scanner') && (
              <>
                <Text style={styles.label}>Scan barcode / QR</Text>
                <TextInput
                  value={scanCode}
                  onChangeText={setScanCode}
                  onSubmitEditing={() => scanTransferCode(scanCode)}
                  returnKeyType="done"
                  autoCapitalize="none"
                  style={styles.input}
                  placeholder="Changanua barcode kisha Enter"
                  placeholderTextColor={tc.muted}
                />
                <Text style={[styles.muted, { marginTop: 6, marginBottom: 12 }]}>Scan huchagua bidhaa iliyopo tu; haiongezi stock mpya.</Text>
              </>
            )}
            <Text style={styles.label}>{tr('product')}</Text>
            {products.length === 0 ? <Text style={styles.muted}>{tr('no_products')}</Text> : products.map(p => {
              const available = transferDirection === 'in' ? p.outside_stock : (p.fridges.find(f => f.fridge_id === transferFridge?.id)?.quantity || 0);
              return (
                <TouchableOpacity
                  key={p.product_id}
                  style={[styles.productOption, selectedProduct === p.product_id && { borderColor: tc.primary, backgroundColor: tc.primary + '12' }]}
                  onPress={() => setSelectedProduct(p.product_id)}
                >
                  <View style={{ flex: 1 }}>
                    <Text style={styles.alertName}>{p.name}</Text>
                    <Text style={styles.muted}>{transferDirection === 'in' ? tr('outside') : tr('inside')}: {available}</Text>
                  </View>
                  {selectedProduct === p.product_id && <Ionicons name="checkmark-circle" size={20} color={tc.primary} />}
                </TouchableOpacity>
              );
            })}
            <Text style={styles.label}>{tr('quantity')}</Text>
            <TextInput value={quantity} onChangeText={setQuantity} keyboardType="decimal-pad" style={styles.input} />
            {transferDirection === 'in' && (
              <>
                <Text style={styles.label}>{tr('min_stock')}</Text>
                <TextInput value={minStock} onChangeText={setMinStock} keyboardType="decimal-pad" style={styles.input} />
              </>
            )}
            <Text style={styles.label}>{tr('notes')}</Text>
            <TextInput value={notes} onChangeText={setNotes} style={styles.input} placeholder={tr('notes')} placeholderTextColor={tc.muted} />
            <Text style={[styles.muted, { marginTop: 8 }]}>
              {transferDirection === 'in' ? tr('transfer_in_hint') : tr('transfer_out_hint')}
            </Text>
            <TouchableOpacity style={[styles.primaryBtn, { marginTop: 18 }]} onPress={submitTransfer} disabled={transferring}>
              <Text style={styles.primaryText}>{transferring ? '...' : tr('confirm')}</Text>
            </TouchableOpacity>
          </ScrollView>
        </SafeAreaView>
      </Modal>

      {/* Threshold */}
      <Modal visible={thresholdOpen} transparent animationType="fade" onRequestClose={() => setThresholdOpen(false)}>
        <View style={styles.modalBackdrop}>
          <View style={[styles.modalCard, { backgroundColor: tc.card }]}>
            <Text style={styles.modalTitle}>{tr('threshold')}</Text>
            <TextInput value={thresholdValue} onChangeText={setThresholdValue} keyboardType="decimal-pad" style={styles.input} autoFocus />
            <View style={styles.modalActions}>
              <TouchableOpacity style={styles.cancelBtn} onPress={() => setThresholdOpen(false)}><Text style={styles.cancelText}>{tr('cancel')}</Text></TouchableOpacity>
              <TouchableOpacity style={styles.primaryBtn} onPress={saveThreshold}><Text style={styles.primaryText}>{tr('save')}</Text></TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

function Stat({ label, value, color, styles }: { label: string; value: number; color: string; styles: any }) {
  return (
    <View style={styles.statCard}>
      <Text style={styles.statLabel}>{label}</Text>
      <Text style={[styles.statValue, { color }]}>{value}</Text>
    </View>
  );
}

function Mini({ label, value }: { label: string; value: number }) {
  return (
    <View style={{ alignItems: 'center', flex: 1 }}>
      <Text style={{ fontWeight: '800', fontSize: 16 }}>{value}</Text>
      <Text style={{ fontSize: 10, color: '#94a3b8' }}>{label}</Text>
    </View>
  );
}

const makeStyles = (tc: ThemeColors) => StyleSheet.create({
  safe: { flex: 1, backgroundColor: tc.page },
  content: { padding: 16, paddingBottom: 48 },
  header: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 16 },
  title: { fontSize: 24, fontWeight: '800', color: tc.ink },
  subtitle: { fontSize: 12, color: tc.muted, marginTop: 3 },
  iconBtn: { width: 40, height: 40, borderRadius: 12, backgroundColor: tc.card, alignItems: 'center', justifyContent: 'center' },
  addBtn: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: tc.primary, paddingHorizontal: 12, height: 40, borderRadius: 12 },
  addBtnText: { color: '#fff', fontWeight: '800', fontSize: 12 },
  loading: { padding: 30, alignItems: 'center', gap: 10 },
  statsRow: { flexDirection: 'row', gap: 8, marginBottom: 8 },
  statCard: { flex: 1, backgroundColor: tc.card, borderRadius: 14, padding: 12, borderWidth: 1, borderColor: tc.line },
  statLabel: { fontSize: 10, color: tc.muted, fontWeight: '700', textTransform: 'uppercase' },
  statValue: { fontSize: 20, fontWeight: '800', marginTop: 5 },
  card: { backgroundColor: tc.card, borderRadius: 16, padding: 14, borderWidth: 1, borderColor: tc.line, marginTop: 10 },
  cardTop: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  fridgeIcon: { width: 42, height: 42, borderRadius: 13, backgroundColor: '#eef5ff', alignItems: 'center', justifyContent: 'center' },
  cardTitle: { fontWeight: '800', fontSize: 15, color: tc.ink },
  muted: { fontSize: 11, color: tc.muted, marginTop: 2 },
  warnPill: { backgroundColor: '#fff7ed', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 99 },
  warnText: { color: '#c2410c', fontSize: 10, fontWeight: '800' },
  cardNumbers: { flexDirection: 'row', borderTopWidth: 1, borderBottomWidth: 1, borderColor: tc.line, paddingVertical: 9, marginTop: 12, marginBottom: 10 },
  cardActions: { flexDirection: 'row', gap: 8, flexWrap: 'wrap' },
  smallBtn: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: tc.page, borderRadius: 9, paddingHorizontal: 10, paddingVertical: 8, borderWidth: 1, borderColor: tc.line },
  smallBtnText: { fontSize: 11, fontWeight: '700', color: tc.ink },
  sectionTitle: { fontSize: 14, fontWeight: '800', color: tc.ink, marginTop: 22, marginBottom: 8 },
  panel: { backgroundColor: tc.card, borderRadius: 14, padding: 13, borderWidth: 1, borderColor: tc.line },
  emptyCard: { alignItems: 'center', padding: 32, backgroundColor: tc.card, borderRadius: 16, borderWidth: 1, borderColor: tc.line, marginTop: 12, gap: 8 },
  emptyTitle: { fontWeight: '800', color: tc.ink, fontSize: 15 },
  alertRow: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: '#fff8f1', borderRadius: 10, padding: 10, marginBottom: 6, borderWidth: 1, borderColor: '#ffedd5' },
  alertRed: { backgroundColor: '#fef2f2', borderColor: '#fee2e2' },
  alertBlue: { backgroundColor: '#eff6ff', borderColor: '#dbeafe' },
  alertName: { fontWeight: '700', color: tc.ink, fontSize: 12 },
  alertValue: { fontWeight: '800', fontSize: 12, color: tc.ink },
  movementRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: tc.line },
  modalBackdrop: { flex: 1, backgroundColor: 'rgba(15,23,42,.5)', justifyContent: 'center', padding: 18 },
  modalCard: { borderRadius: 18, padding: 18 },
  modalTitle: { fontSize: 17, fontWeight: '800', color: tc.ink, marginBottom: 12 },
  input: { borderWidth: 1, borderColor: tc.line, borderRadius: 11, paddingHorizontal: 12, paddingVertical: 10, color: tc.ink, backgroundColor: tc.page, marginTop: 4 },
  modalActions: { flexDirection: 'row', justifyContent: 'flex-end', gap: 8, marginTop: 16 },
  cancelBtn: { paddingHorizontal: 14, paddingVertical: 10, borderRadius: 10, borderWidth: 1, borderColor: tc.line, alignItems: 'center', justifyContent: 'center' },
  cancelText: { fontWeight: '700', color: tc.ink, fontSize: 12 },
  primaryBtn: { paddingHorizontal: 14, paddingVertical: 10, borderRadius: 10, backgroundColor: tc.primary, alignItems: 'center', justifyContent: 'center' },
  primaryText: { fontWeight: '800', color: '#fff', fontSize: 12 },
  modalHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 16, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: tc.line },
  label: { fontSize: 12, fontWeight: '800', color: tc.ink, marginTop: 14, marginBottom: 5 },
  productOption: { flexDirection: 'row', alignItems: 'center', borderWidth: 1, borderColor: tc.line, borderRadius: 11, padding: 11, marginBottom: 7, backgroundColor: tc.card },
  stockRow: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: tc.card, borderRadius: 12, padding: 12, marginBottom: 7, borderWidth: 1, borderColor: tc.line },
});
