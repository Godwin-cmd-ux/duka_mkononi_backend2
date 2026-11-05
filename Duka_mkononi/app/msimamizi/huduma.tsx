import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Modal,
  Platform,
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
import {
  Service,
  errorMessage,
  fetchBusinessProducts,
  fetchServices,
  hudumaApi,
  money,
} from '../../lib/huduma';
import { ThemeColors, useTheme } from '../../lib/theme';

/**
 * Huduma Zilizopo / Huduma Mpya (mobile).
 *
 * Same contract as the website module: a relational recipe per service, edits
 * that never require recreating the service, and no product row is ever
 * deleted when it is removed from a recipe. Every mutation is authorized and
 * business-scoped by the API.
 */
export default function HudumaScreen() {
  const { colors: tc } = useTheme();
  const styles = useMemo(() => makeStyles(tc), [tc]);
  const router = useRouter();
  const { t } = useLang();

  const [tab, setTab] = useState<'new' | 'list'>('new');
  const [services, setServices] = useState<Service[]>([]);
  const [products, setProducts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);

  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [selected, setSelected] = useState<Record<string, number>>({});
  const [search, setSearch] = useState('');

  const [qtyDraft, setQtyDraft] = useState<Record<string, string>>({});
  const [edit, setEdit] = useState<Service | null>(null);
  const [editName, setEditName] = useState('');
  const [editDescription, setEditDescription] = useState('');
  const [editStatus, setEditStatus] = useState('active');

  const [addFor, setAddFor] = useState<Service | null>(null);
  const [addProductId, setAddProductId] = useState('');
  const [addQty, setAddQty] = useState('0');

  const refresh = useCallback(async () => {
    setLoading(true);
    const [serviceList, productList] = await Promise.all([fetchServices(), fetchBusinessProducts()]);
    setServices(serviceList);
    setProducts(productList);
    setLoading(false);
  }, []);

  useEffect(() => {
    refresh();
  }, [refresh]);

  useEffect(() => {
    const next: Record<string, string> = {};
    services.forEach((service) => {
      (service.consumables || []).forEach((item) => {
        next[`${service.id}:${item.product_id}`] = String(item.required_quantity);
      });
    });
    setQtyDraft(next);
  }, [services]);

  const filteredProducts = useMemo(() => {
    const needle = search.trim().toLowerCase();
    return needle
      ? products.filter((product) => String(product.name || '').toLowerCase().includes(needle))
      : products;
  }, [products, search]);

  const selectedRows = useMemo(
    () =>
      Object.keys(selected)
        .map((productId) => ({ product: products.find((p) => p.id === productId), quantity: selected[productId] }))
        .filter((row) => row.product),
    [selected, products]
  );

  const estimatedCost = selectedRows.reduce(
    (sum, row) => sum + Number(row.product?.price || 0) * row.quantity,
    0
  );

  const toggleProduct = (productId: string) => {
    setSelected((previous) => {
      const next = { ...previous };
      if (next[productId] !== undefined) {
        delete next[productId];
      } else {
        next[productId] = 0;
      }
      return next;
    });
  };

  const setDraftQty = (productId: string, value: string) => {
    const parsed = Number(value);
    setSelected((previous) => ({
      ...previous,
      [productId]: Number.isFinite(parsed) && parsed >= 0 ? parsed : 0,
    }));
  };

  const createService = async () => {
    if (busy) return;

    if (!name.trim()) {
      Alert.alert(t('huduma.title'), t('huduma.err_name'));
      return;
    }
    if (selectedRows.length === 0) {
      Alert.alert(t('huduma.title'), t('huduma.err_products'));
      return;
    }

    setBusy(true);
    const response = await hudumaApi('/api/services', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: name.trim(),
        description: description.trim(),
        consumables: selectedRows.map((row) => ({
          product_id: row.product.id,
          required_quantity: row.quantity,
        })),
      }),
    });
    setBusy(false);

    if (!response.ok) {
      Alert.alert(t('huduma.title'), errorMessage(response, t('huduma.err_save')));
      return;
    }

    setName('');
    setDescription('');
    setSelected({});
    setSearch('');
    setTab('list');
    await refresh();
    Alert.alert(t('huduma.title'), t('huduma.created'));
  };

  const saveQty = async (serviceId: string, productId: string, value: string) => {
    const quantity = Number(value);

    if (!Number.isFinite(quantity) || quantity < 0) {
      Alert.alert(t('huduma.title'), t('huduma.err_qty'));
      return;
    }

    const response = await hudumaApi(`/api/services/${serviceId}/consumables/${productId}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ required_quantity: quantity }),
    });

    if (!response.ok) {
      Alert.alert(t('huduma.title'), errorMessage(response, t('huduma.err_save')));
      return;
    }

    await refresh();
  };

  const removeProduct = (service: Service, productId: string, productName: string | null) => {
    Alert.alert(t('huduma.confirm_remove_title'), t('huduma.confirm_remove_msg'), [
      { text: t('huduma.cancel'), style: 'cancel' },
      {
        text: t('huduma.delete'),
        style: 'destructive',
        onPress: async () => {
          const response = await hudumaApi(`/api/services/${service.id}/consumables/${productId}`, {
            method: 'DELETE',
          });
          if (!response.ok) {
            Alert.alert(t('huduma.title'), errorMessage(response, t('huduma.err_save')));
            return;
          }
          await refresh();
        },
      },
    ]);
  };

  const deleteService = (service: Service) => {
    Alert.alert(t('huduma.confirm_delete_title'), t('huduma.confirm_delete_msg'), [
      { text: t('huduma.cancel'), style: 'cancel' },
      {
        text: t('huduma.delete'),
        style: 'destructive',
        onPress: async () => {
          const response = await hudumaApi(`/api/services/${service.id}`, { method: 'DELETE' });
          if (!response.ok) {
            Alert.alert(t('huduma.title'), errorMessage(response, t('huduma.err_delete')));
            return;
          }
          await refresh();
        },
      },
    ]);
  };

  const openEdit = (service: Service) => {
    setEdit(service);
    setEditName(service.name);
    setEditDescription(service.description || '');
    setEditStatus(service.status === 'inactive' ? 'inactive' : 'active');
  };

  const saveEdit = async () => {
    if (!edit) return;
    if (!editName.trim()) {
      Alert.alert(t('huduma.title'), t('huduma.err_name'));
      return;
    }

    const response = await hudumaApi(`/api/services/${edit.id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: editName.trim(),
        description: editDescription.trim(),
        status: editStatus,
      }),
    });

    if (!response.ok) {
      Alert.alert(t('huduma.title'), errorMessage(response, t('huduma.err_save')));
      return;
    }

    setEdit(null);
    await refresh();
  };

  const addAvailable = addFor
    ? products.filter(
        (product) =>
          !(addFor.consumables || []).some((item) => item.product_id === product.id)
      )
    : [];

  const addConsumable = async () => {
    if (!addFor || !addProductId) {
      Alert.alert(t('huduma.title'), t('huduma.select_product'));
      return;
    }

    const quantity = Number(addQty);
    if (!Number.isFinite(quantity) || quantity < 0) {
      Alert.alert(t('huduma.title'), t('huduma.err_qty'));
      return;
    }

    const response = await hudumaApi(`/api/services/${addFor.id}/consumables`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ product_id: addProductId, required_quantity: quantity }),
    });

    if (!response.ok) {
      Alert.alert(t('huduma.title'), errorMessage(response, t('huduma.err_save')));
      return;
    }

    setAddFor(null);
    setAddProductId('');
    setAddQty('0');
    await refresh();
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
        >
          <View style={styles.header}>
            <TouchableOpacity onPress={() => router.back()} style={styles.iconButton}>
              <Ionicons name="arrow-back" size={24} color={tc.ink} />
            </TouchableOpacity>
            <Text style={styles.headerTitle}>✂️ {t('huduma.title')}</Text>
            <LogoutButton iconOnly />
          </View>

          {/* Top-level tabs: Bidhaa (this screen) and Huduma (active here). */}
          <View style={{ flexDirection: 'row', backgroundColor: tc.soft, borderRadius: 16, padding: 5, marginBottom: 14, gap: 6 }}>
            <TouchableOpacity
              style={{ flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, paddingVertical: 11, borderRadius: 12 }}
              onPress={() => router.back()}
              activeOpacity={0.85}
            >
              <Ionicons name="cube-outline" size={16} color={tc.muted} />
              <Text style={{ fontSize: 14, fontWeight: '700', color: tc.muted }}>{t('huduma.tab_products')}</Text>
            </TouchableOpacity>
            <View style={{ flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, paddingVertical: 11, borderRadius: 12, backgroundColor: tc.card }}>
              <Ionicons name="cut-outline" size={16} color={tc.primary} />
              <Text style={{ fontSize: 14, fontWeight: '800', color: tc.primary }}>{t('huduma.tab_services')}</Text>
            </View>
          </View>

          <View style={styles.tabs}>
            <TouchableOpacity
              style={[styles.tab, tab === 'new' && styles.tabActive]}
              onPress={() => setTab('new')}
            >
              <Text style={[styles.tabText, tab === 'new' && styles.tabTextActive]}>
                {t('huduma.new_service')}
              </Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={[styles.tab, tab === 'list' && styles.tabActive]}
              onPress={() => setTab('list')}
            >
              <Text style={[styles.tabText, tab === 'list' && styles.tabTextActive]}>
                {t('huduma.existing_services')}
              </Text>
            </TouchableOpacity>
          </View>

          {loading ? (
            <View style={styles.center}>
              <ActivityIndicator size="large" color={tc.primary} />
            </View>
          ) : tab === 'new' ? (
            <View style={styles.card}>
              <Text style={styles.label}>{t('huduma.name')}</Text>
              <TextInput
                style={styles.input}
                value={name}
                onChangeText={setName}
                placeholder={t('huduma.name_ph')}
                placeholderTextColor={tc.muted2}
                maxLength={120}
              />

              <Text style={styles.label}>{t('huduma.description')}</Text>
              <TextInput
                style={styles.input}
                value={description}
                onChangeText={setDescription}
                placeholder={t('huduma.description_ph')}
                placeholderTextColor={tc.muted2}
                maxLength={500}
              />

              <Text style={styles.label}>{t('huduma.consumables')}</Text>

              {products.length === 0 ? (
                <View style={styles.emptyBox}>
                  <Text style={styles.emptyTitle}>{t('huduma.no_products')}</Text>
                  <Text style={styles.emptyText}>{t('huduma.no_products_hint')}</Text>
                </View>
              ) : (
                <>
                  <View style={styles.searchRow}>
                    <Ionicons name="search" size={18} color={tc.muted2} />
                    <TextInput
                      style={styles.searchInput}
                      value={search}
                      onChangeText={setSearch}
                      placeholder={t('huduma.select_product')}
                      placeholderTextColor={tc.muted2}
                    />
                  </View>

                  {filteredProducts.map((product) => {
                    const active = selected[product.id] !== undefined;
                    return (
                      <View key={product.id} style={[styles.productRow, active && styles.productRowActive]}>
                        <TouchableOpacity style={styles.productMain} onPress={() => toggleProduct(product.id)}>
                          <Ionicons
                            name={active ? 'checkbox' : 'square-outline'}
                            size={22}
                            color={active ? tc.primary : tc.muted2}
                          />
                          <View style={styles.flex}>
                            <Text style={styles.productName} numberOfLines={1}>{product.name}</Text>
                            <Text style={styles.productMeta}>
                              {t('huduma.buying_price')}: {money(product.price)} · {t('huduma.stock')}: {product.stock ?? 0}
                            </Text>
                          </View>
                        </TouchableOpacity>
                        {active && (
                          <TextInput
                            style={styles.qtyInput}
                            value={String(selected[product.id])}
                            onChangeText={(value) => setDraftQty(product.id, value)}
                            keyboardType="numeric"
                          />
                        )}
                      </View>
                    );
                  })}
                </>
              )}

              <View style={styles.summary}>
                <Text style={styles.summaryLabel}>
                  {selectedRows.length} {t('huduma.product')}
                </Text>
                <Text style={styles.summaryValue}>{money(estimatedCost)}</Text>
              </View>

              <TouchableOpacity
                style={[styles.primaryButton, busy && styles.disabled]}
                onPress={createService}
                disabled={busy}
              >
                {busy ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <Text style={styles.primaryButtonText}>{t('huduma.create')}</Text>
                )}
              </TouchableOpacity>
            </View>
          ) : (
            <View>
              {services.length === 0 ? (
                <View style={styles.emptyBox}>
                  <Text style={styles.emptyTitle}>{t('huduma.no_services')}</Text>
                  <Text style={styles.emptyText}>{t('huduma.no_services_hint')}</Text>
                </View>
              ) : (
                services.map((service) => (
                  <View key={service.id} style={styles.card}>
                    <View style={styles.serviceHead}>
                      <View style={styles.flex}>
                        <Text style={styles.serviceName}>{service.name}</Text>
                        <Text style={styles.serviceSub}>
                          {service.description || t('huduma.no_description')}
                        </Text>
                      </View>
                      <View style={[styles.chip, service.status === 'inactive' && styles.chipOff]}>
                        <Text style={[styles.chipText, service.status === 'inactive' && styles.chipTextOff]}>
                          {service.status === 'inactive' ? t('huduma.status_inactive') : t('huduma.status_active')}
                        </Text>
                      </View>
                    </View>

                    <Text style={styles.serviceCost}>
                      {t('huduma.default_cost')}: {money(service.estimated_cost)}
                    </Text>

                    {(service.consumables || []).map((item) => (
                      <View key={item.product_id} style={styles.consumableRow}>
                        <View style={styles.flex}>
                          <Text style={styles.productName}>{item.product_name || '—'}</Text>
                          <Text style={styles.productMeta}>
                            {t('huduma.buying_price')}: {money(item.buying_price)} · {t('huduma.stock')}: {item.stock ?? 0}
                          </Text>
                        </View>
                        <TextInput
                          style={styles.qtyInput}
                          value={qtyDraft[`${service.id}:${item.product_id}`] ?? String(item.required_quantity)}
                          onChangeText={(value) =>
                            setQtyDraft((previous) => ({
                              ...previous,
                              [`${service.id}:${item.product_id}`]: value,
                            }))
                          }
                          onEndEditing={(event) =>
                            saveQty(service.id, item.product_id, event.nativeEvent.text)
                          }
                          keyboardType="numeric"
                        />
                        <TouchableOpacity
                          style={styles.removeButton}
                          onPress={() => removeProduct(service, item.product_id, item.product_name)}
                        >
                          <Ionicons name="close" size={18} color={tc.danger} />
                        </TouchableOpacity>
                      </View>
                    ))}

                    <View style={styles.actions}>
                      <TouchableOpacity style={styles.smallButton} onPress={() => setAddFor(service)}>
                        <Ionicons name="add" size={16} color={tc.primary} />
                        <Text style={styles.smallButtonText}>{t('huduma.add_product')}</Text>
                      </TouchableOpacity>
                      <TouchableOpacity style={styles.smallButton} onPress={() => openEdit(service)}>
                        <Ionicons name="create-outline" size={16} color={tc.primary} />
                        <Text style={styles.smallButtonText}>{t('huduma.edit')}</Text>
                      </TouchableOpacity>
                      <TouchableOpacity style={[styles.smallButton, styles.dangerButton]} onPress={() => deleteService(service)}>
                        <Ionicons name="trash-outline" size={16} color={tc.danger} />
                        <Text style={[styles.smallButtonText, { color: tc.danger }]}>{t('huduma.delete')}</Text>
                      </TouchableOpacity>
                    </View>
                  </View>
                ))
              )}
            </View>
          )}
        </ScrollView>
      </KeyboardAvoidingView>

      {/* Edit service */}
      <Modal visible={!!edit} transparent animationType="fade" onRequestClose={() => setEdit(null)}>
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>{t('huduma.edit')}</Text>
            <Text style={styles.label}>{t('huduma.name')}</Text>
            <TextInput style={styles.input} value={editName} onChangeText={setEditName} maxLength={120} />
            <Text style={styles.label}>{t('huduma.description')}</Text>
            <TextInput
              style={styles.input}
              value={editDescription}
              onChangeText={setEditDescription}
              maxLength={500}
            />
            <View style={styles.statusRow}>
              <TouchableOpacity
                style={[styles.statusButton, editStatus === 'active' && styles.statusActive]}
                onPress={() => setEditStatus('active')}
              >
                <Text style={[styles.statusText, editStatus === 'active' && styles.statusTextActive]}>
                  {t('huduma.status_active')}
                </Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.statusButton, editStatus === 'inactive' && styles.statusActive]}
                onPress={() => setEditStatus('inactive')}
              >
                <Text style={[styles.statusText, editStatus === 'inactive' && styles.statusTextActive]}>
                  {t('huduma.status_inactive')}
                </Text>
              </TouchableOpacity>
            </View>
            <View style={styles.modalActions}>
              <TouchableOpacity style={styles.ghostButton} onPress={() => setEdit(null)}>
                <Text style={styles.ghostButtonText}>{t('huduma.cancel')}</Text>
              </TouchableOpacity>
              <TouchableOpacity style={styles.primaryButton} onPress={saveEdit}>
                <Text style={styles.primaryButtonText}>{t('huduma.save')}</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Add consumable */}
      <Modal visible={!!addFor} transparent animationType="slide" onRequestClose={() => setAddFor(null)}>
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.modalTitle}>{t('huduma.add_product')}</Text>

            {addAvailable.length === 0 ? (
              <Text style={styles.emptyText}>{t('huduma.no_products')}</Text>
            ) : (
              <ScrollView style={{ maxHeight: 260 }}>
                {addAvailable.map((product) => {
                  const active = addProductId === product.id;
                  return (
                    <TouchableOpacity
                      key={product.id}
                      style={[styles.addProductRow, active && styles.productRowActive]}
                      onPress={() => setAddProductId(product.id)}
                    >
                      <Ionicons
                        name={active ? 'radio-button-on' : 'radio-button-off'}
                        size={20}
                        color={active ? tc.primary : tc.muted2}
                      />
                      <View style={styles.flex}>
                        <Text style={styles.productName}>{product.name}</Text>
                        <Text style={styles.productMeta}>{money(product.price)}</Text>
                      </View>
                    </TouchableOpacity>
                  );
                })}
              </ScrollView>
            )}

            <View style={styles.qtyRow}>
              <Text style={styles.label}>{t('huduma.qty')}</Text>
              <TextInput
                style={[styles.input, styles.qtyWide]}
                value={addQty}
                onChangeText={setAddQty}
                keyboardType="numeric"
              />
            </View>

            <View style={styles.modalActions}>
              <TouchableOpacity style={styles.ghostButton} onPress={() => setAddFor(null)}>
                <Text style={styles.ghostButtonText}>{t('huduma.cancel')}</Text>
              </TouchableOpacity>
              <TouchableOpacity style={styles.primaryButton} onPress={addConsumable}>
                <Text style={styles.primaryButtonText}>{t('huduma.add_product')}</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
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
      marginBottom: 16,
    },
    iconButton: { padding: 4 },
    headerTitle: { fontSize: 19, fontWeight: '800', color: tc.ink, flex: 1, marginLeft: 8 },
    tabs: {
      flexDirection: 'row',
      backgroundColor: tc.soft,
      borderRadius: 14,
      padding: 4,
      marginBottom: 16,
    },
    tab: { flex: 1, paddingVertical: 10, borderRadius: 11, alignItems: 'center' },
    tabActive: { backgroundColor: tc.card },
    tabText: { fontSize: 13, fontWeight: '700', color: tc.muted },
    tabTextActive: { color: tc.primary },
    card: {
      backgroundColor: tc.card,
      borderRadius: 18,
      padding: 16,
      marginBottom: 14,
      borderWidth: 1,
      borderColor: tc.line,
    },
    label: { fontSize: 12, fontWeight: '700', color: tc.muted, marginBottom: 6, marginTop: 10 },
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
    searchRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 8,
      borderWidth: 1,
      borderColor: tc.line2,
      borderRadius: 11,
      paddingHorizontal: 11,
      backgroundColor: tc.soft,
      marginBottom: 8,
    },
    searchInput: { flex: 1, paddingVertical: 10, fontSize: 14, color: tc.ink },
    productRow: {
      flexDirection: 'row',
      alignItems: 'center',
      paddingVertical: 9,
      borderBottomWidth: 1,
      borderBottomColor: tc.line,
      gap: 10,
    },
    productRowActive: { backgroundColor: tc.tint, borderRadius: 10, paddingHorizontal: 8 },
    productMain: { flexDirection: 'row', alignItems: 'center', gap: 10, flex: 1 },
    productName: { fontSize: 14, fontWeight: '700', color: tc.ink },
    productMeta: { fontSize: 11, color: tc.muted2, marginTop: 2 },
    qtyInput: {
      width: 64,
      borderWidth: 1,
      borderColor: tc.line2,
      borderRadius: 9,
      paddingVertical: 7,
      paddingHorizontal: 6,
      textAlign: 'center',
      fontSize: 13,
      fontWeight: '700',
      color: tc.ink,
      backgroundColor: tc.card,
    },
    summary: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'center',
      backgroundColor: tc.tint,
      borderRadius: 11,
      padding: 12,
      marginTop: 12,
    },
    summaryLabel: { fontSize: 12, fontWeight: '700', color: tc.primaryDark },
    summaryValue: { fontSize: 16, fontWeight: '800', color: tc.primaryDark },
    primaryButton: {
      backgroundColor: tc.primary,
      borderRadius: 12,
      paddingVertical: 14,
      alignItems: 'center',
      marginTop: 14,
      flex: 1,
    },
    disabled: { opacity: 0.6 },
    primaryButtonText: { color: '#fff', fontSize: 14, fontWeight: '800' },
    emptyBox: {
      borderWidth: 1,
      borderColor: tc.line,
      borderStyle: 'dashed',
      borderRadius: 12,
      padding: 18,
      alignItems: 'center',
      marginTop: 8,
    },
    emptyTitle: { fontSize: 14, fontWeight: '800', color: tc.ink, marginBottom: 4 },
    emptyText: { fontSize: 12, color: tc.muted, textAlign: 'center', lineHeight: 18 },
    center: { paddingVertical: 50, alignItems: 'center' },
    serviceHead: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
    serviceName: { fontSize: 16, fontWeight: '800', color: tc.ink },
    serviceSub: { fontSize: 12, color: tc.muted, marginTop: 2 },
    serviceCost: { fontSize: 12, fontWeight: '700', color: tc.primaryDark, marginTop: 6, marginBottom: 4 },
    chip: { backgroundColor: `${tc.success}22`, borderRadius: 20, paddingHorizontal: 9, paddingVertical: 3 },
    chipOff: { backgroundColor: `${tc.danger}22` },
    chipText: { fontSize: 10, fontWeight: '800', color: tc.success },
    chipTextOff: { color: tc.danger },
    consumableRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 8,
      paddingVertical: 8,
      borderBottomWidth: 1,
      borderBottomColor: tc.line,
    },
    removeButton: { padding: 6 },
    actions: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 12 },
    smallButton: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 4,
      backgroundColor: tc.soft,
      borderRadius: 9,
      paddingHorizontal: 10,
      paddingVertical: 8,
    },
    smallButtonText: { fontSize: 12, fontWeight: '700', color: tc.primary },
    dangerButton: { backgroundColor: `${tc.danger}12` },
    modalBackdrop: {
      flex: 1,
      backgroundColor: 'rgba(0,0,0,.45)',
      justifyContent: 'center',
      padding: 18,
    },
    modalCard: { backgroundColor: tc.card, borderRadius: 18, padding: 18, maxHeight: '86%' },
    modalTitle: { fontSize: 17, fontWeight: '800', color: tc.ink, marginBottom: 6 },
    modalActions: { flexDirection: 'row', gap: 10, marginTop: 16 },
    ghostButton: {
      flex: 1,
      backgroundColor: tc.soft,
      borderRadius: 12,
      paddingVertical: 14,
      alignItems: 'center',
    },
    ghostButtonText: { fontSize: 14, fontWeight: '700', color: tc.muted },
    statusRow: { flexDirection: 'row', gap: 8, marginTop: 12 },
    statusButton: {
      flex: 1,
      borderWidth: 1,
      borderColor: tc.line2,
      borderRadius: 10,
      paddingVertical: 10,
      alignItems: 'center',
    },
    statusActive: { borderColor: tc.primary, backgroundColor: tc.tint },
    statusText: { fontSize: 13, fontWeight: '700', color: tc.muted },
    statusTextActive: { color: tc.primary },
    addProductRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 10,
      paddingVertical: 10,
      borderBottomWidth: 1,
      borderBottomColor: tc.line,
    },
    qtyRow: { flexDirection: 'row', alignItems: 'center', gap: 12, marginTop: 6 },
    qtyWide: { width: 90, textAlign: 'center' },
  });
