import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Platform,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import LogoutButton from '../../components/logout-button';
import { useLang } from '../../context/LanguageContext';
import { Service, errorMessage, fetchServices, hudumaApi, money } from '../../lib/huduma';
import { ThemeColors, useTheme } from '../../lib/theme';

/**
 * Uza Huduma (mobile).
 *
 * The seller picks a service, adjusts the quantities actually consumed and
 * types the price charged. Revenue/cost/profit are previewed live, but the
 * authoritative figures (and the stock deduction) are computed by the server
 * inside one database transaction.
 */
export default function UzaHudumaScreen() {
  const { colors: tc } = useTheme();
  const styles = useMemo(() => makeStyles(tc), [tc]);
  const router = useRouter();
  const { t } = useLang();

  const [services, setServices] = useState<Service[]>([]);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [qtys, setQtys] = useState<Record<string, string>>({});
  const [units, setUnits] = useState('1');
  const [price, setPrice] = useState('');
  const [date, setDate] = useState(new Date().toISOString().split('T')[0]);
  const [notes, setNotes] = useState('');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    const list = await fetchServices();
    setServices(list.filter((service) => service.status !== 'inactive'));
    setLoading(false);
    setRefreshing(false);
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const selected = useMemo(
    () => services.find((service) => service.id === selectedId) || null,
    [services, selectedId]
  );

  const unitCount = Math.max(1, parseInt(units, 10) || 1);

  const quantityFor = (productId: string): number => {
    const draft = qtys[selectedId ? `${selectedId}:${productId}` : productId];
    if (draft !== undefined) {
      const parsed = Number(draft);
      return Number.isFinite(parsed) && parsed >= 0 ? parsed : 0;
    }
    const item = (selected?.consumables || []).find((consumable) => consumable.product_id === productId);
    return Number(item?.required_quantity || 0) * unitCount;
  };

  const lines = (selected?.consumables || []).map((item) => ({
    item,
    quantity: quantityFor(item.product_id),
  }));

  const cost = lines.reduce(
    (sum, line) => sum + line.quantity * Number(line.item.buying_price || 0),
    0
  );

  const priceValue = Number(price);
  const revenue = Number.isFinite(priceValue) && priceValue >= 0 ? priceValue : 0;
  const profit = revenue - cost;
  const hasShortage = lines.some(
    (line) => line.quantity > Number(line.item.stock || 0)
  );

  const pick = (service: Service) => {
    setSelectedId(service.id);
    setQtys({});
    setUnits('1');
    setPrice('');
  };

  const setQuantity = (productId: string, value: string) => {
    if (!selectedId) return;
    setQtys((previous) => ({ ...previous, [`${selectedId}:${productId}`]: value }));
  };

  const changeUnits = (value: string) => {
    setUnits(value);
    // Defaults are the saved recipe per service unit; changing the unit count
    // resets the adjusted quantities so the preview follows the new default.
    setQtys({});
  };

  const submit = async () => {
    if (busy || !selected) return;

    if (!Number.isFinite(priceValue) || priceValue < 0) {
      Alert.alert(t('huduma.sell_title'), t('huduma.err_price'));
      return;
    }

    const consumables = lines
      .filter((line) => line.quantity > 0)
      .map((line) => ({ product_id: line.item.product_id, quantity: line.quantity }));

    if (consumables.length === 0) {
      Alert.alert(t('huduma.sell_title'), t('huduma.err_empty'));
      return;
    }

    setBusy(true);

    const payload = {
      service_id: selected.id,
      service_quantity: unitCount,
      selling_price: priceValue,
      consumables,
      payment_method: 'cash',
      sale_date: date,
      notes: notes.trim(),
      clientSaleKey: `srv-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    };

    const send = () =>
      hudumaApi('/api/service-sales', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

    let response = await send();
    if (!response.ok && response.network) {
      response = await send();
    }

    setBusy(false);

    if (!response.ok) {
      const message =
        response.data && (response.data as any).code === 'STOCK_ISSUES'
          ? t('huduma.err_stock')
          : errorMessage(response, t('huduma.err_save'));
      Alert.alert(t('huduma.sell_title'), message);
      return;
    }

    const sale = (response.data && (response.data as any).sale) || {};
    Alert.alert(
      t('huduma.sale_ok'),
      `${sale.invoice_number || ''}\n${t('huduma.expected_profit')}: ${money(sale.profit)}`
    );

    setSelectedId(null);
    setQtys({});
    setUnits('1');
    setPrice('');
    setNotes('');
    await load();
  };

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.content}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => {
                setRefreshing(true);
                load();
              }}
            />
          }
        >
          <View style={styles.header}>
            <TouchableOpacity onPress={() => router.back()} style={styles.iconButton}>
              <Ionicons name="arrow-back" size={24} color={tc.ink} />
            </TouchableOpacity>
            <Text style={styles.headerTitle}>✂️ {t('huduma.sell_title')}</Text>
            <LogoutButton iconOnly />
          </View>

          <Text style={styles.subtitle}>{t('huduma.sell_subtitle')}</Text>

          {loading ? (
            <View style={styles.center}>
              <ActivityIndicator size="large" color={tc.primary} />
            </View>
          ) : services.length === 0 ? (
            <View style={styles.emptyBox}>
              <Text style={styles.emptyTitle}>{t('huduma.no_services')}</Text>
              <Text style={styles.emptyText}>{t('huduma.no_services_hint')}</Text>
            </View>
          ) : (
            <>
              <Text style={styles.label}>{t('huduma.select_service')}</Text>
              <View style={styles.grid}>
                {services.map((service) => {
                  const active = service.id === selectedId;
                  return (
                    <TouchableOpacity
                      key={service.id}
                      style={[styles.pick, active && styles.pickActive]}
                      onPress={() => pick(service)}
                      activeOpacity={0.85}
                    >
                      <Text style={styles.pickName} numberOfLines={2}>{service.name}</Text>
                      <Text style={styles.pickMeta}>
                        {(service.consumables || []).length} {t('huduma.consumables')}
                      </Text>
                      <Text style={styles.pickCost}>{money(service.estimated_cost)}</Text>
                    </TouchableOpacity>
                  );
                })}
              </View>

              {selected && (
                <View style={styles.card}>
                  <View style={styles.serviceHead}>
                    <Text style={styles.serviceName}>{selected.name}</Text>
                    <View style={styles.unitsBox}>
                      <Text style={styles.unitsLabel}>{t('huduma.units')}</Text>
                      <TextInput
                        style={styles.unitsInput}
                        value={units}
                        onChangeText={changeUnits}
                        keyboardType="numeric"
                      />
                    </View>
                  </View>

                  {lines.map((line) => {
                    const short = line.quantity > Number(line.item.stock || 0);
                    return (
                      <View key={line.item.product_id} style={[styles.lineRow, short && styles.lineShort]}>
                        <View style={styles.flex}>
                          <Text style={styles.productName}>{line.item.product_name || '—'}</Text>
                          <Text style={styles.productMeta}>
                            {t('huduma.available')}: {line.item.stock ?? 0} · {money(line.item.buying_price)}
                          </Text>
                          {short && <Text style={styles.warnText}>{t('huduma.shortage')}</Text>}
                        </View>
                        <TextInput
                          style={styles.qtyInput}
                          value={
                            qtys[`${selected.id}:${line.item.product_id}`] ?? String(line.quantity)
                          }
                          onChangeText={(value) => setQuantity(line.item.product_id, value)}
                          keyboardType="numeric"
                        />
                      </View>
                    );
                  })}

                  <Text style={styles.label}>{t('huduma.service_price')}</Text>
                  <TextInput
                    style={styles.priceInput}
                    value={price}
                    onChangeText={setPrice}
                    keyboardType="numeric"
                    placeholder="0"
                    placeholderTextColor={tc.muted2}
                  />

                  <View style={styles.previewRow}>
                    <View style={styles.previewBox}>
                      <Text style={styles.previewLabel}>{t('huduma.cost')}</Text>
                      <Text style={styles.previewValue}>{money(cost)}</Text>
                    </View>
                    <View style={[styles.previewBox, profit >= 0 ? styles.profitBox : styles.lossBox]}>
                      <Text style={styles.previewLabel}>{t('huduma.expected_profit')}</Text>
                      <Text style={[styles.previewValue, { color: profit >= 0 ? tc.success : tc.danger }]}>
                        {money(profit)}
                      </Text>
                    </View>
                  </View>

                  <Text style={styles.label}>{t('huduma.notes')}</Text>
                  <TextInput
                    style={styles.input}
                    value={notes}
                    onChangeText={setNotes}
                    placeholder={t('huduma.notes_ph')}
                    placeholderTextColor={tc.muted2}
                    maxLength={500}
                  />

                  <TouchableOpacity
                    style={[styles.primaryButton, (busy || hasShortage) && styles.disabled]}
                    onPress={submit}
                    disabled={busy || hasShortage}
                  >
                    {busy ? (
                      <ActivityIndicator color="#fff" />
                    ) : (
                      <Text style={styles.primaryButtonText}>{t('huduma.sell_btn')}</Text>
                    )}
                  </TouchableOpacity>

                  <Text style={styles.hint}>{t('huduma.shortage')}</Text>
                </View>
              )}
            </>
          )}
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const makeStyles = (tc: ThemeColors) =>
  StyleSheet.create({
    safe: { flex: 1, backgroundColor: tc.page },
    flex: { flex: 1 },
    screen: { flex: 1 },
    content: { padding: 16, paddingBottom: 40 },
    header: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      marginBottom: 8,
    },
    iconButton: { padding: 4 },
    headerTitle: { fontSize: 19, fontWeight: '800', color: tc.ink, flex: 1, marginLeft: 8 },
    subtitle: { fontSize: 12, color: tc.muted, lineHeight: 18, marginBottom: 14 },
    label: { fontSize: 12, fontWeight: '700', color: tc.muted, marginBottom: 6, marginTop: 12 },
    grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
    pick: {
      width: '47%',
      borderWidth: 2,
      borderColor: tc.line,
      backgroundColor: tc.card,
      borderRadius: 14,
      padding: 12,
    },
    pickActive: { borderColor: tc.primary, backgroundColor: tc.tint },
    pickName: { fontSize: 14, fontWeight: '800', color: tc.ink },
    pickMeta: { fontSize: 10, color: tc.muted, marginTop: 4 },
    pickCost: { fontSize: 12, fontWeight: '800', color: tc.primaryDark, marginTop: 6 },
    card: {
      backgroundColor: tc.card,
      borderRadius: 18,
      padding: 16,
      marginTop: 16,
      borderWidth: 1,
      borderColor: tc.line,
    },
    serviceHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', gap: 10 },
    serviceName: { fontSize: 16, fontWeight: '800', color: tc.ink, flex: 1 },
    unitsBox: { alignItems: 'center' },
    unitsLabel: { fontSize: 10, color: tc.muted, marginBottom: 4 },
    unitsInput: {
      width: 64,
      borderWidth: 1,
      borderColor: tc.line2,
      borderRadius: 9,
      paddingVertical: 7,
      textAlign: 'center',
      fontSize: 14,
      fontWeight: '700',
      color: tc.ink,
    },
    lineRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 8,
      paddingVertical: 9,
      borderBottomWidth: 1,
      borderBottomColor: tc.line,
    },
    lineShort: { backgroundColor: `${tc.danger}10`, borderRadius: 10, paddingHorizontal: 8 },
    productName: { fontSize: 14, fontWeight: '700', color: tc.ink },
    productMeta: { fontSize: 11, color: tc.muted2, marginTop: 2 },
    warnText: { fontSize: 10, color: tc.danger, fontWeight: '700', marginTop: 2 },
    qtyInput: {
      width: 64,
      borderWidth: 1,
      borderColor: tc.line2,
      borderRadius: 9,
      paddingVertical: 7,
      textAlign: 'center',
      fontSize: 13,
      fontWeight: '700',
      color: tc.ink,
      backgroundColor: tc.card,
    },
    priceInput: {
      borderWidth: 1,
      borderColor: tc.line2,
      borderRadius: 11,
      paddingHorizontal: 14,
      paddingVertical: 13,
      fontSize: 18,
      fontWeight: '800',
      color: tc.ink,
      backgroundColor: tc.soft,
    },
    input: {
      borderWidth: 1,
      borderColor: tc.line2,
      borderRadius: 11,
      paddingHorizontal: 12,
      paddingVertical: 11,
      fontSize: 14,
      color: tc.ink,
      backgroundColor: tc.soft,
    },
    previewRow: { flexDirection: 'row', gap: 10, marginTop: 14 },
    previewBox: {
      flex: 1,
      backgroundColor: tc.soft,
      borderRadius: 11,
      padding: 12,
      borderWidth: 1,
      borderColor: tc.line,
    },
    profitBox: { backgroundColor: `${tc.success}12`, borderColor: `${tc.success}44` },
    lossBox: { backgroundColor: `${tc.danger}12`, borderColor: `${tc.danger}44` },
    previewLabel: { fontSize: 10, fontWeight: '800', color: tc.muted, textTransform: 'uppercase' },
    previewValue: { fontSize: 16, fontWeight: '800', color: tc.ink, marginTop: 3 },
    primaryButton: {
      backgroundColor: tc.primary,
      borderRadius: 12,
      paddingVertical: 15,
      alignItems: 'center',
      marginTop: 16,
    },
    disabled: { opacity: 0.55 },
    primaryButtonText: { color: '#fff', fontSize: 15, fontWeight: '800' },
    hint: { fontSize: 11, color: tc.muted2, marginTop: 10, lineHeight: 16 },
    emptyBox: {
      borderWidth: 1,
      borderColor: tc.line,
      borderStyle: 'dashed',
      borderRadius: 12,
      padding: 20,
      alignItems: 'center',
      marginTop: 10,
    },
    emptyTitle: { fontSize: 14, fontWeight: '800', color: tc.ink, marginBottom: 4 },
    emptyText: { fontSize: 12, color: tc.muted, textAlign: 'center', lineHeight: 18 },
    center: { paddingVertical: 50, alignItems: 'center' },
  });
