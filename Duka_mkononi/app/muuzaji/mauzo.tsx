import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as Print from 'expo-print';
import { useRouter } from 'expo-router';
import { shareAsync } from 'expo-sharing';
import React, { useEffect, useState } from 'react';
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
import { useSession } from '../../context/SessionContext';
import LogoutButton from '../../components/logout-button';

import { API_BASE_URL } from '../../constants/api';
import { getCache, setCache } from '../../db/cache';
import { registerLive, syncNow } from '../../lib/syncer';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';

export default function MauzoScreen() {
  const router = useRouter();
  const { t, lang } = useLang();
  const { signOut } = useSession();

  const localeMap: Record<string, string> = {
    sw: 'sw-TZ',
    en: 'en',
    fr: 'fr-FR',
    hi: 'hi-IN',
    ur: 'ur-PK',
    es: 'es-ES',
    de: 'de-DE',
    zh: 'zh-CN',
  };
  const [salesData, setSalesData] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [editModalVisible, setEditModalVisible] = useState(false);
  const [selectedSale, setSelectedSale] = useState<any>(null);
  const [editForm, setEditForm] = useState({
    quantity: '',
    unit_price: '',
    customer_name: '',
    product_name: '',
    sale_item_id: '',
  });
  const [closeSalesModalVisible, setCloseSalesModalVisible] = useState(false);
  const [todayClosed, setTodayClosed] = useState(false);
  // "Hifadhi Mabadiliko" shows a spinner and locks while the PUT is in
  // flight, so a slow save is visible and cannot be double-submitted.
  const [savingSale, setSavingSale] = useState(false);
  
  const today = new Date().toISOString().split('T')[0];

  // Live edit total = quantity × unit price. The summary total follows the
  // quantity the seller types (the server recomputes the same value on save).
  // Falls back to the recorded total when there is no unit price.
  // Same quantity resolution as handleUpdateSale, so the live total always
  // matches what saving would produce.
  const editUnitPriceNum = parseFloat(editForm.unit_price) || 0;
  const editQuantityNum = parseInt(editForm.quantity, 10) || (selectedSale?.sale_items?.[0]?.quantity || 1);
  const editTotalAmount = editUnitPriceNum > 0
    ? editQuantityNum * editUnitPriceNum
    : (selectedSale?.total_amount || 0);

  // Format currency function
  const formatCurrency = (amount: number) => {
    return `TZS ${amount.toLocaleString('en-TZ', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    })}`;
  };

  // Function to extract customer name from sale object - IMPROVED VERSION
  const getCustomerName = (sale: any): string => {
    // Jinsi ya kwanza: Angalia moja kwa moja kwenye sale object
    if (sale.customer_name && sale.customer_name.trim() !== '') {
      return sale.customer_name.trim();
    }
    
    // Jinsi ya pili: Angalia kwenye notes ikiwa kuna jina la mteja
    if (sale.notes) {
      if (sale.notes.includes('Mteja:')) {
        const match = sale.notes.match(/Mteja:\s*(.+)/);
        if (match && match[1]) {
          return match[1].trim();
        }
      }
      // Angalia pattern nyingine za kuhifadhi jina la mteja
      if (sale.notes.includes('Customer:')) {
        const match = sale.notes.match(/Customer:\s*(.+)/);
        if (match && match[1]) {
          return match[1].trim();
        }
      }
    }
    
    // Jinsi ya tatu: Angalia kwenye customer object
    if (sale.customer && typeof sale.customer === 'object') {
      if (sale.customer.name) return sale.customer.name;
      if (sale.customer.full_name) return sale.customer.full_name;
    }
    
    // Jinsi ya nne: Angalia kwenye customer_data
    if (sale.customer_data && typeof sale.customer_data === 'object') {
      if (sale.customer_data.name) return sale.customer_data.name;
      if (sale.customer_data.full_name) return sale.customer_data.full_name;
    }
    
    // Jinsi ya tano: Angalia kwenye customers (plural)
    if (sale.customers && typeof sale.customers === 'object') {
      if (sale.customers.name) return sale.customers.name;
      if (sale.customers.full_name) return sale.customers.full_name;
    }
    
    // Mwishowe: Rudi kwenye default
    return t('seller_dashboard.unknown_customer');
  };

  // Customer count = distinct known customers + one "unknown customer" per
  // sale that has no customer data (same rule as the report pages).
  const countTodayCustomers = () => {
    const unknown = t('seller_dashboard.unknown_customer');
    const known = new Set(
      salesData.map(s => getCustomerName(s)).filter(n => n && n !== unknown)
    ).size;
    const unknownSales = salesData.filter(s => {
      const n = getCustomerName(s);
      return !n || n === unknown;
    }).length;
    return known + unknownSales;
  };

  // Sales created with an idempotency key store their notes as
  // "ai_dup_<clientSaleKey> | <real notes>". That marker is an internal
  // double-submit guard written by SaleController::store / commitAiSale, never
  // something the seller should read, so strip it for display only.
  const stripInternalNotes = (notes: any): string => {
    if (!notes || typeof notes !== 'string') return '';
    if (!notes.startsWith('ai_dup_')) return notes;
    const separator = notes.indexOf(' | ');
    return separator >= 0 ? notes.slice(separator + 3) : '';
  };

  // Load sales data from database - IMPROVED VERSION
  const loadSalesData = async () => {
    try {
      setLoading(true);
      const token = await AsyncStorage.getItem('userToken');
      
      if (!token) {
        Alert.alert(t('app.error'), t('seller_dashboard.error_auth'));
        await signOut();
        router.replace('/(tabs)');
        return;
      }

      const cached = await getCache<any>('sales:my');
      if (cached) { setSalesData(cached); setLoading(false); }

      // Fetch sales data with better error handling
      console.log('📥 Inapakua data ya mauzo...');
      const response = await fetchWithTimeout(`${API_BASE_URL}/api/sales/my`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'ngrok-skip-browser-warning': 'true',
        },
      });

      if (!response.ok) {
        console.error('❌ API Error:', response.status, response.statusText);
        throw new Error(`API Error: ${response.status}`);
      }

      const data = await response.json();
      console.log('📊 Sales API response structure:', Object.keys(data));
      
      // Check if data is array
      if (!Array.isArray(data)) {
        console.error('❌ API did not return array:', typeof data);
        Alert.alert(t('app.error'), t('seller_dashboard.no_sales'));
        setSalesData([]);
        return;
      }
      
      console.log('✅ Sales data received:', data.length, 'sales');
      
      // Debug: Check structure of first sale
      if (data.length > 0) {
        console.log('🔍 Sample sale structure:', {
          id: data[0].id,
          keys: Object.keys(data[0]),
          customer_name: data[0].customer_name,
          notes: data[0].notes,
          customer: data[0].customer,
          customer_data: data[0].customer_data,
          customers: data[0].customers
        });
      }

      // Process the data
      const processedData = data.map((sale: any) => {
        const customerName = getCustomerName(sale);
        console.log(`📝 Sale ${sale.id}: customer_name="${sale.customer_name}", extracted="${customerName}"`);
        
        return {
          ...sale,
          display_customer_name: customerName,
        };
      });
      
      // Filter today's sales
      const todaySales = processedData.filter((sale: any) => {
        const saleDate = sale.sale_date || sale.created_at?.split('T')[0];
        return saleDate === today;
      });
      
      console.log('📅 Today sales:', todaySales.length);
      
      setSalesData(todaySales);
      setCache('sales:my', todaySales).catch(() => {});

      // Debug: Check customer names in today's sales
      if (todaySales.length > 0) {
        console.log('👥 Customer names in today sales:');
        todaySales.forEach((sale: any, index: number) => {
          console.log(`${index + 1}. Sale #${sale.invoice_number || sale.id}: "${sale.display_customer_name}"`);
          console.log('   Notes:', sale.notes);
          console.log('   Customer object:', sale.customer);
        });
      }

      // Check if today's sales are closed
      checkIfTodaySalesClosed();

    } catch (error) {
      console.error('❌ Error loading sales:', error);
      const cached = await getCache<any>('sales:my');
      if (cached) { setSalesData(cached); setLoading(false); return; }
      Alert.alert(t('app.error'), t('seller_dashboard.error_network'));
    } finally {
      setLoading(false);
    }
  };

  // Check if today's sales are closed
  const checkIfTodaySalesClosed = async () => {
    try {
      const closedSalesKey = `closed_sales_${today}`;
      const closedData = await AsyncStorage.getItem(closedSalesKey);
      
      if (closedData) {
        console.log('🔒 Today sales are closed (local)');
        setTodayClosed(true);
      }
    } catch (error) {
      console.error('Error checking closed sales:', error);
    }
  };

  // Handle refresh
  const onRefresh = () => {
    loadSalesData();
  };

  // Open edit modal - IMPROVED VERSION
  const openEditModal = (sale: any) => {
    if (todayClosed) {
      Alert.alert(t('seller_dashboard.closed'), t('seller_dashboard.close_sales_warning'));
      return;
    }
    
    setSelectedSale(sale);
    
    // Find the first sale item
    const saleItem = sale.sale_items?.[0];
    const product = saleItem?.products;
    
    console.log('📝 Opening edit modal for sale:', {
      sale_id: sale.id,
      invoice_number: sale.invoice_number,
      customer_name: sale.customer_name,
      display_customer_name: sale.display_customer_name,
      notes: sale.notes,
    });
    
    // Get customer name from various sources
    let customerName = '';
    
    // Priority 1: Direct customer_name field
    if (sale.customer_name && sale.customer_name.trim() !== '') {
      customerName = sale.customer_name.trim();
    }
    // Priority 2: From notes
    else if (sale.notes && sale.notes.includes('Mteja:')) {
      const match = sale.notes.match(/Mteja:\s*(.+)/);
      if (match && match[1]) {
        customerName = match[1].trim();
      }
    }
    // Priority 3: Use display_customer_name
    else if (sale.display_customer_name) {
      customerName = sale.display_customer_name;
    }
    // Priority 4: Extract from customer object
    else if (sale.customer && typeof sale.customer === 'object') {
      customerName = sale.customer.name || sale.customer.full_name || '';
    }
    
    setEditForm({
      quantity: saleItem?.quantity?.toString() || '1',
      unit_price: saleItem?.unit_price?.toString() || '',
      customer_name: customerName,
      product_name: product?.name || t('seller_dashboard.unknown_product'),
      sale_item_id: saleItem?.id || '',
    });
    
    setEditModalVisible(true);
  };

  // Update sale - IMPROVED VERSION
  const updateSale = async () => {
    if (!selectedSale) return;

    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        Alert.alert(t('app.error'), t('seller_dashboard.error_auth'));
        return;
      }

      if (!(await requireNetwork())) return;

      const saleItem = selectedSale.sale_items?.[0];
      if (!saleItem) {
        Alert.alert(t('app.error'), t('seller_dashboard.unknown_product'));
        return;
      }

      const quantity = parseInt(editForm.quantity) || saleItem.quantity;
      const unit_price = parseFloat(editForm.unit_price) || saleItem.unit_price;
      
      // Validate inputs
      if (quantity <= 0) {
        Alert.alert(t('app.error'), t('seller_dashboard.quantity_placeholder'));
        return;
      }
      
      if (unit_price <= 0) {
        Alert.alert(t('app.error'), t('seller_dashboard.quantity_placeholder'));
        return;
      }

      // Calculate new total
      const newTotal = quantity * unit_price;

      // Prepare update data for sale
      const updateData: any = {
        // Sale level updates
        customer_name: editForm.customer_name.trim(),
        notes: `Mteja: ${editForm.customer_name.trim()}`,
        total_amount: newTotal
      };

      // Also update the sale item if sale_item_id exists
      if (editForm.sale_item_id) {
        updateData.sale_items = [{
          id: editForm.sale_item_id,
          quantity: quantity,
          unit_price: unit_price,
          total_price: newTotal
        }];
      }

      console.log('📤 Sending sale update:', {
        sale_id: selectedSale.id,
        update_data: updateData
      });

      setSavingSale(true);
      const response = await fetch(`${API_BASE_URL}/api/sales/${selectedSale.id}`, {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          // Ask Laravel for JSON on every error status too, so a not-found
          // response is parseable instead of an HTML error page.
          'Accept': 'application/json',
        },
        body: JSON.stringify(updateData),
      });

      if (response.ok) {
        Alert.alert(t('app.success'), t('seller_dashboard.success_edit'));
        setEditModalVisible(false);
        loadSalesData(); // Reload data
      } else if (response.status === 404) {
        // Two very different 404s arrive here:
        //  * the sale is missing or belongs to another business - the API
        //    answers with `error` (code SALE_NOT_FOUND), and
        //  * PUT /api/sales/{id} is not on the deployed server at all -
        //    Laravel answers "The route api/sales/<id> could not be found."
        //    with no `error` field.
        // Surface the real reason in both cases; never the stale "editing is
        // not available yet" text, and never the bare English route sentence.
        let jsonError: any = null;
        try { jsonError = await response.json(); } catch { jsonError = null; }
        const routeMissing = !!jsonError && !jsonError.error &&
          typeof jsonError.message === 'string' &&
          /could not be found/i.test(jsonError.message);
        console.warn('❌ Sale update rejected:', response.status, jsonError);
        Alert.alert(
          t('app.error'),
          routeMissing
            ? t('seller_dashboard.server_not_updated')
            : (jsonError && (jsonError.error || jsonError.message))
              || t('seller_dashboard.error_edit'),
          [{ text: t('app.ok') }]
        );
        setEditModalVisible(false);
      } else {
        const errorText = await response.text();
        console.error('❌ Update error:', errorText);
        try {
          const errorData = JSON.parse(errorText);
          Alert.alert(t('app.error'), errorData.error || errorData.message || t('seller_dashboard.error_edit'));
        } catch {
          Alert.alert(t('app.error'), t('seller_dashboard.error_edit'));
        }
      }
    } catch (error) {
      console.error('Error updating sale:', error);
      Alert.alert(t('app.error'), t('seller_dashboard.error_network'));
    } finally {
      setSavingSale(false);
    }
  };

  // Close today's sales
  const closeTodaySales = async () => {
    Alert.alert(
      t('seller_dashboard.close_sales_confirm'),
      t('seller_dashboard.close_sales_warning'),
      [
        { text: t('seller_dashboard.cancel'), style: 'cancel' },
        { 
          text: t('seller_dashboard.close_confirm_yes'), 
          style: 'destructive',
          onPress: async () => {
            try {
              // Mark sales as closed locally
              const closedSalesKey = `closed_sales_${today}`;
              await AsyncStorage.setItem(closedSalesKey, 'true');
              
              setTodayClosed(true);
              setCloseSalesModalVisible(false);
              
              Alert.alert(
                t('seller_dashboard.closed'), 
                t('seller_dashboard.success_close'),
                [{ text: t('app.ok') }]
              );
            } catch (error) {
              console.error('Error closing sales:', error);
              Alert.alert(t('app.error'), t('seller_dashboard.error_edit'));
            }
          }
        },
      ]
    );
  };

  // Generate customer receipt - NEW FUNCTION
  const generateReceipt = async (sale: any) => {
    try {
      const saleItem = sale.sale_items?.[0];
      const product = saleItem?.products;
      const customerName = sale.display_customer_name || getCustomerName(sale);
      
      // Format date and time
      const saleDate = new Date(sale.sale_date || sale.created_at);
      const formattedDate = saleDate.toLocaleDateString(localeMap[lang] || 'sw-TZ', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      });
      const formattedTime = saleDate.toLocaleTimeString(localeMap[lang] || 'sw-TZ', {
        hour: '2-digit',
        minute: '2-digit',
      });

      // HTML template for receipt
      const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title>Risiti - ${sale.invoice_number || sale.id}</title>
          <style>
            body {
              font-family: 'Arial', sans-serif;
              max-width: 400px;
              margin: 0 auto;
              padding: 20px;
              color: #333;
            }
            .header {
              text-align: center;
              margin-bottom: 20px;
              border-bottom: 2px solid #2196F3;
              padding-bottom: 15px;
            }
            .company-name {
              font-size: 24px;
              font-weight: bold;
              color: #2196F3;
              margin-bottom: 5px;
            }
            .receipt-title {
              font-size: 18px;
              font-weight: bold;
              margin: 10px 0;
            }
            .receipt-info {
              margin-bottom: 20px;
              background: #f8f9fa;
              padding: 15px;
              border-radius: 8px;
            }
            .info-row {
              display: flex;
              justify-content: space-between;
              margin-bottom: 8px;
              font-size: 14px;
            }
            .info-label {
              font-weight: bold;
              color: #555;
            }
            .info-value {
              color: #333;
            }
            .items-table {
              width: 100%;
              border-collapse: collapse;
              margin: 20px 0;
            }
            .items-table th {
              background-color: #f0f7ff;
              padding: 10px;
              text-align: left;
              border-bottom: 2px solid #3498db;
              font-size: 14px;
            }
            .items-table td {
              padding: 10px;
              border-bottom: 1px solid #eee;
              font-size: 14px;
            }
            .total-section {
              margin-top: 20px;
              padding: 15px;
              background: #f8f9fa;
              border-radius: 8px;
              border-left: 4px solid #27ae60;
            }
            .total-row {
              display: flex;
              justify-content: space-between;
              font-size: 16px;
              margin-bottom: 8px;
            }
            .total-amount {
              font-size: 20px;
              font-weight: bold;
              color: #27ae60;
            }
            .footer {
              margin-top: 30px;
              text-align: center;
              font-size: 12px;
              color: #666;
              border-top: 1px solid #eee;
              padding-top: 15px;
            }
            .thank-you {
              font-style: italic;
              margin: 15px 0;
              color: #2196F3;
            }
          </style>
        </head>
        <body>
          <div class="header">
            <div class="company-name">DUKAMKONONI</div>
            <div class="receipt-title">RISITI YA MAUZO</div>
            <div>Nambari ya Ankra: <strong>${sale.invoice_number || sale.id.substring(0, 8)}</strong></div>
          </div>
          
          <div class="receipt-info">
            <div class="info-row">
              <span class="info-label">Tarehe:</span>
              <span class="info-value">${formattedDate}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Muda:</span>
              <span class="info-value">${formattedTime}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Mteja:</span>
              <span class="info-value">${customerName}</span>
            </div>
            ${sale.phone_number ? `
            <div class="info-row">
              <span class="info-label">Namba ya Simu:</span>
              <span class="info-value">${sale.phone_number}</span>
            </div>
            ` : ''}
          </div>
          
          <table class="items-table">
            <thead>
              <tr>
                <th>Bidhaa</th>
                <th>Kiasi</th>
                <th>Bei</th>
                <th>Jumla</th>
              </tr>
            </thead>
            <tbody>
              ${sale.sale_items?.map((item: any) => `
                <tr>
                  <td>${item.products?.name || 'Bidhaa'}</td>
                  <td>${item.quantity || 0}</td>
                  <td>TZS ${(item.unit_price || 0).toLocaleString('en-TZ')}</td>
                  <td>TZS ${((item.quantity || 0) * (item.unit_price || 0)).toLocaleString('en-TZ')}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
          
          <div class="total-section">
            <div class="info-row">
              <span class="info-label">Njia ya Malipo:</span>
              <span class="info-value">${sale.payment_method === 'cash' ? 'Fedha Taslimu' : 'Malipo ya Kadi'}</span>
            </div>
            <div class="total-row">
              <span>Jumla ya Mauzo:</span>
              <span class="total-amount">TZS ${(sale.total_amount || 0).toLocaleString('en-TZ')}</span>
            </div>
          </div>
          
          <div class="thank-you">Asante kwa Kununua Nasi!</div>
          
          <div class="footer">
            <div>Dukamkononi - Mfumo wa Uhasibu wa Biashara</div>
            <div>Simu: +255 XXX XXX XXX | Email: info@dukamkononi.com</div>
            <div>www.dukamkononi.com</div>
            <div style="margin-top: 10px;">Risiti hii ni ya kisheria na inatumika kama hati ya malipo</div>
          </div>
        </body>
        </html>
      `;

      // Generate PDF
      const { uri } = await Print.printToFileAsync({
        html: htmlContent,
        base64: false,
      });

      // Share/Open the PDF
      await shareAsync(uri, {
        UTI: '.pdf',
        mimeType: 'application/pdf',
        dialogTitle: `Risiti - ${sale.invoice_number || sale.id}`,
      });

    } catch (error) {
      console.error('Error generating receipt:', error);
      Alert.alert(t('app.error'), t('seller_dashboard.error_edit'));
    }
  };

  // Calculate total for today
  const calculateTodayTotal = () => {
    return salesData.reduce((total, sale) => total + (sale.total_amount || 0), 0);
  };

  // Format date to readable format
  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString(localeMap[lang] || 'sw-TZ', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    });
  };

  // Render sale item - IMPROVED VERSION
  const renderSaleItem = ({ item }: { item: any }) => {
    const saleItem = item.sale_items?.[0];
    const product = saleItem?.products;
    
    // Always use display_customer_name which we set during loading
    const customerName = item.display_customer_name || getCustomerName(item);
    
    return (
      <View style={[
        styles.saleItem,
        todayClosed && styles.saleItemClosed
      ]}>
        <View style={styles.saleItemHeader}>
          <View style={styles.saleInfo}>
            <Text style={styles.invoiceNumber}>
              #{item.invoice_number || item.id.substring(0, 8)}
            </Text>
            <Text style={styles.saleTime}>
              {new Date(item.created_at || item.sale_date).toLocaleTimeString(localeMap[lang] || 'sw-TZ', {
                hour: '2-digit',
                minute: '2-digit',
              })}
            </Text>
          </View>
          
          <Text style={styles.saleTotal}>
            {formatCurrency(item.total_amount || 0)}
          </Text>
        </View>
        
        <View style={styles.saleDetails}>
          <View style={styles.productRow}>
            <Ionicons name="cube" size={16} color="#666" />
            <Text style={styles.productName} numberOfLines={1}>
              {product?.name || t('seller_dashboard.unknown_product')}
            </Text>
            <Text style={styles.quantity}>
              {saleItem?.quantity || 0} × {formatCurrency(saleItem?.unit_price || 0)}
            </Text>
          </View>
          
          {/* ALWAYS SHOW CUSTOMER NAME */}
          <View style={styles.customerRow}>
            <Ionicons name="person" size={16} color="#666" />
            <Text style={styles.customerName}>
              {customerName || t('seller_dashboard.unknown_customer')}
            </Text>
          </View>
          
          {item.payment_method && (
            <View style={styles.paymentRow}>
              <Ionicons name="card" size={14} color="#3498db" />
              <Text style={styles.paymentText}>
                {item.payment_method === 'cash' ? t('seller_dashboard.cash') : t('seller_dashboard.card')}
              </Text>
            </View>
          )}
          
          {/* Show notes if available (without the internal idempotency marker) */}
          {stripInternalNotes(item.notes) ? (
            <View style={styles.notesRow}>
              <Ionicons name="document-text" size={14} color="#95a5a6" />
              <Text style={styles.notesText} numberOfLines={2}>
                {stripInternalNotes(item.notes)}
              </Text>
            </View>
          ) : null}
        </View>
        
        <View style={styles.saleFooter}>
          <Text style={styles.dateText}>
            {formatDate(item.sale_date || item.created_at)}
          </Text>
          
          <View style={styles.saleActions}>
            {!todayClosed && (
              <TouchableOpacity 
                style={styles.editButton}
                onPress={() => openEditModal(item)}
              >
                <Ionicons name="create-outline" size={18} color="#3498db" />
                <Text style={styles.editButtonText}>{t('seller_dashboard.edit')}</Text>
              </TouchableOpacity>
            )}
            
            {/* NEW: RECEIPT BUTTON */}
            <TouchableOpacity 
              style={styles.receiptButton}
              onPress={() => generateReceipt(item)}
            >
              <Ionicons name="receipt-outline" size={18} color="#27ae60" />
              <Text style={styles.receiptButtonText}>{t('seller_dashboard.get_receipt')}</Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    );
  };

  // Load data on component mount
  useEffect(() => {
    loadSalesData();
  }, [lang]);

  useEffect(() => {
    const stop = registerLive<any>(
      'sales:my',
      async () => {
        const token = await AsyncStorage.getItem('userToken');
        const res = await fetchWithTimeout(`${API_BASE_URL}/api/sales/my`, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
            'ngrok-skip-browser-warning': 'true',
          },
        });
        if (!res.ok) throw new Error('sync failed');
        const data = await res.json();
        const todaySales = data.map((sale: any) => ({
          ...sale,
          display_customer_name: getCustomerName(sale),
        }));
        return todaySales.filter((sale: any) => {
          const saleDate = sale.sale_date || sale.created_at?.split('T')[0];
          return saleDate === today;
        });
      },
      (data) => { setSalesData(data); setLoading(false); }
    );
    return stop;
  }, [lang]);

  if (loading) {
    return (
      <SafeAreaView style={styles.screenSafe} edges={['top']}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#2196F3" />
          <Text style={styles.loadingText}>{t('seller_dashboard.loading_sales')}</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.screenSafe} edges={['top']}>
    <View style={styles.container}>
      {/* HEADER SECTION */}
      <View style={styles.header}>
        <View style={styles.headerTop}>
          <TouchableOpacity onPress={() => router.back()} style={styles.backButton}>
            <Ionicons name="arrow-back" size={24} color="#2c3e50" />
          </TouchableOpacity>
          <Text style={styles.title}>{t('seller_dashboard.today_sales')}</Text>
          <View style={styles.headerActions}>
            <TouchableOpacity onPress={onRefresh} style={styles.refreshButton}>
              <Ionicons name="refresh-outline" size={22} color="#2196F3" />
            </TouchableOpacity>
            <LogoutButton iconOnly />
          </View>
        </View>
        
        <Text style={styles.dateTitle}>
          {formatDate(today)}
          {todayClosed && (
            <Text style={styles.closedBadge}> • {t('seller_dashboard.closed')}</Text>
          )}
        </Text>
        
        {/* STATS SECTION */}
        <View style={styles.statsContainer}>
          <View style={styles.statItem}>
            <Text style={styles.statValue}>{salesData.length}</Text>
            <Text style={styles.statLabel}>{t('seller_dashboard.today_sales')}</Text>
          </View>
          
          <View style={styles.statItem}>
            <Text style={styles.statValue}>
              {formatCurrency(calculateTodayTotal())}
            </Text>
            <Text style={styles.statLabel}>{t('seller_dashboard.total_today')}</Text>
          </View>
          
          <View style={styles.statItem}>
            <Text style={styles.statValue}>
              {new Set(salesData.map(s => getCustomerName(s).trim())).size}
            </Text>
            <Text style={styles.statLabel}>{t('seller_dashboard.customers')}</Text>
          </View>
        </View>
      </View>

      {/* SALES LIST */}
      <View style={styles.salesListContainer}>
        {salesData.length === 0 ? (
          <View style={styles.emptyContainer}>
            <Ionicons name="receipt-outline" size={64} color="#bdc3c7" />
            <Text style={styles.emptyTitle}>{t('seller_dashboard.no_sales_today')}</Text>
            <Text style={styles.emptyText}>{t('seller_dashboard.no_sales_text')}</Text>
            <TouchableOpacity 
              style={styles.goToSellButton}
              onPress={() => router.push('/muuzaji/uza' as any)}
            >
              <Ionicons name="cart" size={18} color="white" />
              <Text style={styles.goToSellButtonText}>{t('seller_dashboard.go_sell')}</Text>
            </TouchableOpacity>
          </View>
        ) : (
          <FlatList
            data={salesData}
            renderItem={renderSaleItem}
            keyExtractor={(item) => item.id}
            contentContainerStyle={styles.listContent}
            showsVerticalScrollIndicator={false}
            refreshing={loading}
            onRefresh={onRefresh}
          />
        )}
      </View>

      {/* CLOSE SALES BUTTON - Only show if there are sales and not already closed */}
      {salesData.length > 0 && !todayClosed && (
        <TouchableOpacity 
          style={styles.closeSalesButton}
          onPress={() => setCloseSalesModalVisible(true)}
        >
          <Ionicons name="lock-closed" size={20} color="white" />
          <Text style={styles.closeSalesButtonText}>{t('seller_dashboard.close_sales')}</Text>
        </TouchableOpacity>
      )}

      {/* EDIT MODAL - IMPROVED VERSION */}
      <Modal
        visible={editModalVisible}
        animationType="slide"
        transparent={true}
        onRequestClose={() => setEditModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>{t('seller_dashboard.edit_sale')}</Text>
              <TouchableOpacity onPress={() => setEditModalVisible(false)}>
                <Ionicons name="close" size={24} color="#666" />
              </TouchableOpacity>
            </View>
            
            <ScrollView style={styles.modalBody}>
              <View style={styles.formGroup}>
                <Text style={styles.formLabel}>{t('seller_dashboard.invoice_no')}</Text>
                <TextInput
                  style={[styles.formInput, styles.readOnlyInput]}
                  value={selectedSale?.invoice_number || selectedSale?.id}
                  editable={false}
                />
              </View>
              
              <View style={styles.formGroup}>
                <Text style={styles.formLabel}>{t('seller_dashboard.product_name')}</Text>
                <TextInput
                  style={[styles.formInput, styles.readOnlyInput]}
                  value={editForm.product_name}
                  editable={false}
                />
              </View>
              
              <View style={styles.formGroup}>
                <Text style={styles.formLabel}>{t('seller_dashboard.quantity')}</Text>
                <TextInput
                  style={styles.formInput}
                  value={editForm.quantity}
                  onChangeText={(text) => setEditForm({...editForm, quantity: text.replace(/[^0-9]/g, '')})}
                  keyboardType="numeric"
                  placeholder={t('seller_dashboard.quantity_placeholder')}
                />
                {selectedSale?.sale_items?.[0] && (
                  <Text style={styles.fieldHint}>
                    {t('seller_dashboard.previous_qty')}: {selectedSale.sale_items[0].quantity}
                  </Text>
                )}
              </View>
              
              <View style={styles.formGroup}>
                <Text style={styles.formLabel}>{t('seller_dashboard.price')}</Text>
                <TextInput
                  style={[styles.formInput, styles.readOnlyInput]}
                  value={editForm.unit_price}
                  editable={false}
                />
                <Text style={styles.fieldHint}>
                  {t('seller_dashboard.previous_price')}: {formatCurrency(parseFloat(editForm.unit_price) || 0)}
                </Text>
              </View>
              
              <View style={styles.formGroup}>
                <Text style={styles.formLabel}>{t('seller_dashboard.customer_name')} *</Text>
                <TextInput
                  style={styles.formInput}
                  value={editForm.customer_name}
                  onChangeText={(text) => setEditForm({...editForm, customer_name: text})}
                  placeholder={t('seller_dashboard.customer_name_placeholder')}
                />
                <Text style={styles.fieldHint}>
                  {editForm.customer_name ? 
                    `${t('seller_dashboard.customer_name')}: ${editForm.customer_name}` : 
                    t('seller_dashboard.customer_name_placeholder')
                  }
                </Text>
              </View>
              
              {selectedSale && (
                <View style={styles.summaryBox}>
                  <Text style={styles.summaryTitle}>{t('seller_dashboard.edit_summary')}</Text>
                  
                  <View style={styles.summaryRow}>
                    <Text style={styles.summaryLabel}>{t('seller_dashboard.product_name')}:</Text>
                    <Text style={styles.summaryValue}>{editForm.product_name}</Text>
                  </View>
                  
                  <View style={styles.summaryRow}>
                    <Text style={styles.summaryLabel}>{t('seller_dashboard.quantity')} ({t('seller_dashboard.old')}):</Text>
                    <Text style={styles.summaryValue}>{selectedSale.sale_items?.[0]?.quantity || 0}</Text>
                  </View>
                  
                  <View style={styles.summaryRow}>
                    <Text style={styles.summaryLabel}>{t('seller_dashboard.quantity')} ({t('seller_dashboard.new')}):</Text>
                    <Text style={[styles.summaryValue, styles.changedValue]}>
                      {editForm.quantity || '0'}
                    </Text>
                  </View>
                  
                  <View style={styles.summaryRow}>
                    <Text style={styles.summaryLabel}>{t('seller_dashboard.price')}:</Text>
                    <Text style={styles.summaryValue}>
                      {formatCurrency(parseFloat(editForm.unit_price) || 0)}
                    </Text>
                  </View>
                  
                  <View style={styles.summaryRow}>
                    <Text style={styles.summaryLabel}>{t('seller_dashboard.customer_name')}:</Text>
                    <Text style={styles.summaryValue} numberOfLines={2}>
                      {editForm.customer_name || t('seller_dashboard.no_name')}
                    </Text>
                  </View>
                  
                  <View style={styles.summaryDivider} />
                  
                  <View style={styles.summaryRow}>
                    <Text style={styles.totalLabel}>{t('seller_dashboard.total')}:</Text>
                    <Text style={styles.totalValue}>
                      {formatCurrency(editTotalAmount)}
                    </Text>
                  </View>

                  <Text style={styles.fieldHint}>
                    {t('seller_dashboard.stock_sync_note')}
                  </Text>

                  </View>
              
              )}
            </ScrollView>
            
            <View style={styles.modalFooter}>
              <TouchableOpacity 
                style={styles.cancelButton}
                onPress={() => setEditModalVisible(false)}
                disabled={savingSale}
              >
                <Text style={styles.cancelButtonText}>{t('seller_dashboard.cancel')}</Text>
              </TouchableOpacity>
              
              <TouchableOpacity 
                style={[styles.saveButton, savingSale && styles.saveButtonBusy]}
                onPress={updateSale}
                disabled={savingSale}
              >
                {savingSale ? (
                  <ActivityIndicator size="small" color="white" />
                ) : null}
                <Text style={styles.saveButtonText}>{t('seller_dashboard.save')}</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* CLOSE SALES CONFIRMATION MODAL */}
      <Modal
        visible={closeSalesModalVisible}
        animationType="fade"
        transparent={true}
        onRequestClose={() => setCloseSalesModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.confirmModalContent}>
            <Ionicons name="warning" size={48} color="#f39c12" style={styles.warningIcon} />
            <Text style={styles.confirmTitle}>{t('seller_dashboard.close_sales')}?</Text>
            <Text style={styles.confirmText}>
              {t('seller_dashboard.close_sales_warning')}
              {'\n\n'}
              {t('seller_dashboard.total_today')}: {formatCurrency(calculateTodayTotal())}
              {'\n\n'}
              {t('seller_dashboard.customers')}: {countTodayCustomers()}
            </Text>
            
            <View style={styles.confirmButtons}>
              <TouchableOpacity 
                style={styles.confirmCancelButton}
                onPress={() => setCloseSalesModalVisible(false)}
              >
                <Text style={styles.confirmCancelText}>{t('seller_dashboard.cancel')}</Text>
              </TouchableOpacity>
              
              <TouchableOpacity 
                style={styles.confirmCloseButton}
                onPress={closeTodaySales}
              >
                <Ionicons name="lock-closed" size={18} color="white" />
                <Text style={styles.confirmCloseText}>{t('seller_dashboard.close_confirm_yes')}</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screenSafe: {
    flex: 1,
    backgroundColor: '#f8f9fa',
  },
  container: {
    flex: 1,
    backgroundColor: '#f8f9fa',
  },
  
  // LOADING STYLES
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f8f9fa',
    padding: 30,
  },
  
  loadingText: {
    marginTop: 15,
    fontSize: 16,
    color: '#666',
  },
  
  // HEADER STYLES
  header: {
    backgroundColor: 'white',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  
  headerTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  headerActions: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  
  backButton: {
    padding: 8,
  },
  
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#2c3e50',
    textAlign: 'center',
    flex: 1,
  },
  
  refreshButton: {
    padding: 8,
    backgroundColor: '#f0f7ff',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e3f2fd',
  },
  
  dateTitle: {
    fontSize: 16,
    color: '#666',
    textAlign: 'center',
    marginBottom: 15,
  },
  
  closedBadge: {
    color: '#e74c3c',
    fontWeight: 'bold',
  },
  
  // STATS STYLES
  statsContainer: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    backgroundColor: '#f8f9fa',
    padding: 16,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#e9ecef',
  },
  
  statItem: {
    alignItems: 'center',
    flex: 1,
    paddingHorizontal: 8,
  },
  
  statValue: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#2196F3',
    marginBottom: 4,
  },
  
  statLabel: {
    fontSize: 14,
    color: '#666',
    fontWeight: '500',
  },
  
  // SALES LIST STYLES
  salesListContainer: {
    flex: 1,
    padding: 15,
  },
  
  listContent: {
    paddingBottom: 20,
  },
  
  saleItem: {
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 15,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#e9ecef',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 1,
  },
  
  saleItemClosed: {
    backgroundColor: '#f8f9fa',
    borderColor: '#dee2e6',
  },
  
  saleItemHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  
  saleInfo: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  
  invoiceNumber: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginRight: 10,
  },
  
  saleTime: {
    fontSize: 14,
    color: '#95a5a6',
  },
  
  saleTotal: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#27ae60',
  },
  
  saleDetails: {
    marginBottom: 10,
  },
  
  productRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
  },
  
  productName: {
    flex: 1,
    fontSize: 16,
    color: '#2c3e50',
    marginLeft: 8,
    marginRight: 10,
  },
  
  quantity: {
    fontSize: 14,
    color: '#7f8c8d',
    fontWeight: '500',
  },
  
  customerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 6,
  },
  
  customerName: {
    fontSize: 14,
    color: '#2c3e50',
    marginLeft: 8,
    fontWeight: '500',
  },
  
  paymentRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 6,
  },
  
  paymentText: {
    fontSize: 13,
    color: '#3498db',
    marginLeft: 6,
  },
  
  notesRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginTop: 4,
  },
  
  notesText: {
    fontSize: 12,
    color: '#95a5a6',
    marginLeft: 6,
    flex: 1,
    fontStyle: 'italic',
  },
  
  saleFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: '#ecf0f1',
  },
  
  saleActions: {
    flexDirection: 'row',
    gap: 10,
  },
  
  dateText: {
    fontSize: 13,
    color: '#95a5a6',
  },
  
  editButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#e8f4fd',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
    gap: 6,
  },
  
  editButtonText: {
    color: '#3498db',
    fontSize: 14,
    fontWeight: '500',
  },
  
  receiptButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#e8f7ef',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
    gap: 6,
  },
  
  receiptButtonText: {
    color: '#27ae60',
    fontSize: 14,
    fontWeight: '500',
  },
  
  // EMPTY STATE
  emptyContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  
  emptyTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginTop: 20,
    marginBottom: 10,
  },
  
  emptyText: {
    fontSize: 16,
    color: '#7f8c8d',
    textAlign: 'center',
    lineHeight: 24,
    marginBottom: 20,
  },
  
  goToSellButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#2ecc71',
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 8,
    gap: 8,
  },
  
  goToSellButtonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: 'bold',
  },
  
  // CLOSE SALES BUTTON
  closeSalesButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#e74c3c',
    margin: 15,
    paddingVertical: 16,
    paddingHorizontal: 20,
    borderRadius: 12,
    gap: 12,
    shadowColor: '#e74c3c',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.2,
    shadowRadius: 4,
    elevation: 3,
  },
  
  closeSalesButtonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: 'bold',
  },
  
  // MODAL STYLES
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  
  modalContent: {
    backgroundColor: 'white',
    borderRadius: 16,
    width: '90%',
    maxHeight: '80%',
  },
  
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#e9ecef',
  },
  
  modalTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  
  modalBody: {
    padding: 20,
  },
  
  formGroup: {
    marginBottom: 20,
  },
  
  formLabel: {
    fontSize: 16,
    fontWeight: '600',
    color: '#2c3e50',
    marginBottom: 8,
  },
  
  formInput: {
    backgroundColor: '#f8f9fa',
    borderWidth: 1,
    borderColor: '#dee2e6',
    borderRadius: 10,
    padding: 15,
    fontSize: 16,
    color: '#2c3e50',
  },
  
  readOnlyInput: {
    backgroundColor: '#f0f0f0',
    color: '#666',
    borderColor: '#ccc',
  },
  
  fieldHint: {
    fontSize: 12,
    color: '#888',
    marginTop: 5,
    fontStyle: 'italic',
  },
  
  summaryBox: {
    backgroundColor: '#f8f9fa',
    padding: 15,
    borderRadius: 10,
    marginTop: 10,
    borderWidth: 1,
    borderColor: '#e9ecef',
  },
  
  summaryTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 10,
  },
  
  summaryRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  
  summaryLabel: {
    color: '#666',
    fontSize: 14,
  },
  
  summaryValue: {
    color: '#2c3e50',
    fontSize: 14,
    fontWeight: '500',
  },
  
  changedValue: {
    color: '#e74c3c',
    fontWeight: 'bold',
  },
  
  summaryDivider: {
    height: 1,
    backgroundColor: '#dee2e6',
    marginVertical: 10,
  },
  
  totalLabel: {
    fontWeight: 'bold',
    fontSize: 15,
  },
  
  totalValue: {
    fontWeight: 'bold',
    color: '#27ae60',
    fontSize: 16,
  },
  
  modalFooter: {
    flexDirection: 'row',
    padding: 20,
    borderTopWidth: 1,
    borderTopColor: '#e9ecef',
    gap: 10,
  },
  
  cancelButton: {
    flex: 1,
    paddingVertical: 14,
    alignItems: 'center',
    backgroundColor: '#f8f9fa',
    borderRadius: 10,
  },
  
  cancelButtonText: {
    color: '#666',
    fontSize: 16,
    fontWeight: '500',
  },
  
  saveButton: {
    flex: 1,
    flexDirection: 'row',
    justifyContent: 'center',
    paddingVertical: 14,
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#2196F3',
    borderRadius: 10,
  },
  
  saveButtonBusy: {
    opacity: 0.7,
  },
  
  saveButtonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: 'bold',
  },
  
  // CONFIRMATION MODAL
  confirmModalContent: {
    backgroundColor: 'white',
    borderRadius: 16,
    width: '85%',
    padding: 25,
    alignItems: 'center',
  },
  
  warningIcon: {
    marginBottom: 15,
  },
  
  confirmTitle: {
    fontSize: 22,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 15,
    textAlign: 'center',
  },
  
  confirmText: {
    fontSize: 16,
    color: '#666',
    textAlign: 'center',
    lineHeight: 24,
    marginBottom: 25,
  },
  
  confirmButtons: {
    flexDirection: 'row',
    gap: 10,
    width: '100%',
  },
  
  confirmCancelButton: {
    flex: 1,
    paddingVertical: 14,
    alignItems: 'center',
    backgroundColor: '#f8f9fa',
    borderRadius: 10,
  },
  
  confirmCancelText: {
    color: '#666',
    fontSize: 16,
    fontWeight: '500',
  },
  
  confirmCloseButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 14,
    backgroundColor: '#e74c3c',
    borderRadius: 10,
    gap: 8,
  },
  
  confirmCloseText: {
    color: 'white',
    fontSize: 16,
    fontWeight: 'bold',
  },
});