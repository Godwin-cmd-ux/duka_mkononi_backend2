import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { useCallback, useEffect, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    RefreshControl,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLang } from '../../context/LanguageContext';
import LogoutButton from '../../components/logout-button';
import { getCache, setCache } from '../../db/cache';
import { registerLive } from '../../lib/syncer';
import { fetchWithTimeout } from '../../lib/network';

import { API_BASE_URL } from '../../constants/api';

interface Sale {
  // sales.id / sale_items.product_id / sales.customer_id are UUIDs. They were
  // typed `number` back when ids were sequential; nothing caught it because the
  // API JSON arrives as `any`. See the same fix in tangaza.tsx.
  id: string;
  product_id: string;
  product_name: string;
  quantity: number;
  unit_price: number;
  total_amount: number;
  sale_date: string;
  customer_id: string | null;
  customer_name: string;
  seller_name: string;
  business_name: string;
  user_id: string;
  invoice_number?: string;
  // `cost_price` here is the BUYING price, copied from products.price, which
  // is NOT NULL. It stays nullable in the type only as a defensive guard.
  cost_price?: number | null;
  profit?: number | null;
  profit_margin?: number | null;
}

interface Product {
  id: string;
  name: string;
  // price = BUYING price ("Bei ya Kununua"), NOT NULL
  price: number;
  // expected_selling_price = SELLING price ("Bei ya Kuuzia"), may be null
  expected_selling_price: number | null;
  category: string | null;
  stock: number;
  cost_price?: number | null;
  seller_id: string;
  description: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  total_sold?: number;
  total_revenue?: number;
  total_profit?: number;
  has_sales?: boolean;
}

interface Customer {
  id: string;
  name: string;
  phone: string | null;
  email: string | null;
  seller_id: string;
  total_purchases: number;
  purchases_count: number;
  last_purchase_date: string | null;
  created_at: string;
  updated_at: string;
  seller_name?: string;
  actual_purchases?: number;
  actual_purchase_count?: number;
}

interface User {
  id: string;
  email: string;
  role: string;
  full_name: string | null;
  phone: string | null;
  business_name: string | null;
  business_location: string | null;
  status: string;
  created_at: string;
  updated_at: string;
}

interface BusinessStats {
  totalSales: number;
  totalCustomers: number;
  totalProducts: number;
  totalSellers: number;
  todaySales: number;
  todayProfit: number;
  totalProfit: number;
  averageProfitMargin: number;
  // How many sales were excluded from totalProfit / todayProfit because their
  // product has no recorded buying price.
  unknownCostSales: number;
  unknownCostSalesToday: number;
}

export default function RipotiScreen() {
  const { t, lang } = useLang();
  const [userData, setUserData] = useState({
    id: '',
    email: '',
    businessName: '',
    businessLocation: '',
    role: ''
  });
  
  const [businessStats, setBusinessStats] = useState<BusinessStats | null>(null);
  const [allProducts, setAllProducts] = useState<Product[]>([]);
  const [soldProducts, setSoldProducts] = useState<Product[]>([]);
  const [unsoldProducts, setUnsoldProducts] = useState<Product[]>([]);
  const [customers, setCustomers] = useState<Customer[]>([]);
  // Sales with no customer data — each counts as one "unknown customer".
  const [unknownCustomers, setUnknownCustomers] = useState(0);
  const [sales, setSales] = useState<Sale[]>([]);
  const [sellers, setSellers] = useState<User[]>([]);
  
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [activeReport, setActiveReport] = useState<'overview' | 'sales' | 'products' | 'customers'>('overview');
  const [activeProductTab, setActiveProductTab] = useState<'sold' | 'unsold'>('sold');
  const [userToken, setUserToken] = useState<string | null>(null);
  const [dataSource, setDataSource] = useState<'admin' | 'seller'>('seller');
  const [searchTerm, setSearchTerm] = useState('');

  useEffect(() => {
    loadUserData();
  }, [lang]);

  useEffect(() => {
    if (userData.role !== 'admin' || !userData.businessName) return;
    const stop = registerLive<any[]>(
      'admin:users',
      async () => {
        const token = await AsyncStorage.getItem('userToken');
        if (!token) throw new Error('sync failed');
        // Server-side filtering: only this business's approved members.
        const res = await fetchWithTimeout(
          `${API_BASE_URL}/api/admin/users?business=${encodeURIComponent(userData.businessName || '')}&role=seller,admin`,
          {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'ngrok-skip-browser-warning': 'true'
          }
        }, 20000);
        if (!res.ok) throw new Error('sync failed');
        const data = await res.json();
        return Array.isArray(data) ? data : data.users || [];
      },
      (data) => {
        // Server already returns only this business's members; filter on the
        // approval rule only (see fetchBusinessData for why the business_name
        // compare was removed).
        const filtered = data.filter((u: any) => u.status === 'approved');
        setSellers(filtered);
      }
    );
    return stop;
  }, [lang, userData.role, userData.businessName]);

  const loadUserData = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      const userDataStr = await AsyncStorage.getItem('userData');
      
      if (token && userDataStr) {
        setUserToken(token);
        const user = JSON.parse(userDataStr);
        setUserData({
          id: user.id || user.userId || '',
          email: user.email || '',
          businessName: user.businessName || user.business_name || t('reports.business_report'),
          businessLocation: user.businessLocation || user.business_location || t('reports.no_category'),
          role: user.role || ''
        });
        await fetchAllReports(token, user);
      } else {
        Alert.alert(t('app.error'), t('reports.error_auth'));
        setLoading(false);
      }
    } catch (error) {
      console.error('Error loading user data:', error);
      Alert.alert(t('app.error'), t('reports.error_load'));
      setLoading(false);
    }
  };

  const adjustCustomerPurchases = (customer: Customer): Customer => {
    const adjustedPurchases = customer.total_purchases / 2;
    const adjustedCount = Math.round(customer.purchases_count / 2);
    
    return {
      ...customer,
      total_purchases: adjustedPurchases,
      purchases_count: adjustedCount,
      actual_purchases: adjustedPurchases,
      actual_purchase_count: adjustedCount
    };
  };

  const processProductsWithSalesData = (products: Product[], sales: Sale[]) => {
    // Create map of product sales
    const productSalesMap = new Map<string, {
      totalSold: number;
      totalRevenue: number;
      totalCost: number;
      totalProfit: number;
      salesCount: number;
    }>();
    
    // Calculate sales data for each product
    sales.forEach(sale => {
      if (!productSalesMap.has(sale.product_id)) {
        productSalesMap.set(sale.product_id, {
          totalSold: 0,
          totalRevenue: 0,
          totalCost: 0,
          totalProfit: 0,
          salesCount: 0
        });
      }
      
      const productData = productSalesMap.get(sale.product_id)!;
      productData.totalSold += sale.quantity || 0;
      productData.totalRevenue += sale.total_amount || 0;
      productData.totalCost += (sale.cost_price || 0) * (sale.quantity || 0);
      productData.totalProfit += sale.profit || 0;
      productData.salesCount += 1;
    });
    
    // Enrich products with sales data
    const enrichedProducts = products.map(product => {
      const salesData = productSalesMap.get(product.id);
      const hasSales = !!salesData && salesData.totalSold > 0;
      
      return {
        ...product,
        total_sold: salesData?.totalSold || 0,
        total_revenue: salesData?.totalRevenue || 0,
        total_profit: salesData?.totalProfit || 0,
        has_sales: hasSales
      };
    });
    
    // Split into sold and unsold
    const sold = enrichedProducts.filter(p => p.has_sales);
    const unsold = enrichedProducts.filter(p => !p.has_sales);
    
    return { allProducts: enrichedProducts, soldProducts: sold, unsoldProducts: unsold };
  };

  const buildBusinessSales = (rawSales: any[], allSellers: User[], rawProducts: Product[], businessName: string, customerNameById: Map<string, string> = new Map()): Sale[] => {
    const allSales: Sale[] = [];
    rawSales.forEach((sale: any) => {
      // Seller membership only — the server already scoped the payload to this
      // business, and comparing business_name here re-introduced the
      // canonical-vs-legacy empty-report bug.
      const saleSeller = allSellers.find(s => s.id === sale.seller_id);
      if (saleSeller) {
        // The slim sales payload has no embedded `customers` relation, so
        // resolve the name from the customers map by customer_id (same as
        // the Blade page). Full payloads still use the embedded relation.
        let customerName = 'Mteja';
        let customerId = sale.customer_id ?? null;
        if (sale.customers) {
          customerName = sale.customers.name;
          customerId = sale.customers.id;
        } else if (customerId && customerNameById.get(customerId)) {
          customerName = customerNameById.get(customerId)!;
        }

        let sellerName = saleSeller.full_name || saleSeller.email;

        if (sale.sale_items && sale.sale_items.length > 0) {
          sale.sale_items.forEach((item: any) => {
            const product = rawProducts.find(p => p.id === item.product_id);
            // PRICE RULES: products.price = BUYING price ("Bei ya Kununua"),
            // products.expected_selling_price = SELLING price, profit =
            // selling - buying. The buying price is NOT NULL so a realised
            // sale profit is always computable.
            const costPrice = product?.price ?? null;
            const sellingPrice = product?.expected_selling_price ?? null;

            const unitPrice = item.unit_price || sellingPrice || 0;
            const quantity = item.quantity || 1;
            const totalAmount = item.total_price || unitPrice * quantity;

            const profitPerUnit = costPrice === null ? null : unitPrice - costPrice;
            const itemProfit = profitPerUnit === null ? null : profitPerUnit * quantity;
            const profitMargin = profitPerUnit === null || !costPrice ? null : (profitPerUnit / costPrice) * 100;

            allSales.push({
              id: sale.id,
              product_id: item.product_id || '',
              product_name: product?.name || item.products?.name || 'Bidhaa',
              quantity: quantity,
              unit_price: unitPrice,
              total_amount: totalAmount,
              sale_date: sale.sale_date || new Date().toISOString().split('T')[0],
              customer_id: customerId,
              customer_name: customerName,
              seller_name: sellerName,
              business_name: businessName,
              user_id: sale.seller_id,
              invoice_number: sale.invoice_number,
              cost_price: costPrice,
              profit: itemProfit === null ? undefined : itemProfit,
              profit_margin: profitMargin === null ? undefined : profitMargin
            });
          });
        }
      }
    });
    return allSales;
  };

  const buildSellerSales = (rawSales: any[], rawProducts: Product[]): Sale[] => {
    const allSales: Sale[] = [];
    rawSales.forEach((sale: any) => {
      let customerName = 'Mteja';
      let customerId = null;
      if (sale.customers) {
        customerName = sale.customers.name;
        customerId = sale.customers.id;
      }

      let sellerName = userData.businessName;

      if (sale.sale_items && sale.sale_items.length > 0) {
        sale.sale_items.forEach((item: any) => {
          const product = rawProducts.find(p => p.id === item.product_id);
          const costPrice = product?.price || 0;
          const sellingPrice = product?.expected_selling_price ?? null;

          const unitPrice = item.unit_price || sellingPrice || 0;
          const quantity = item.quantity || 1;
          const totalAmount = item.total_price || unitPrice * quantity;

          const profitPerUnit = costPrice === null ? null : unitPrice - costPrice;
          const itemProfit = profitPerUnit === null ? null : profitPerUnit * quantity;
          const profitMargin = profitPerUnit === null || !costPrice ? null : (profitPerUnit / costPrice) * 100;

          allSales.push({
            id: sale.id,
            product_id: item.product_id || '',
            product_name: product?.name || item.products?.name || 'Bidhaa',
            quantity: quantity,
            unit_price: unitPrice,
            total_amount: totalAmount,
            sale_date: sale.sale_date || new Date().toISOString().split('T')[0],
            customer_id: customerId,
            customer_name: customerName,
            seller_name: sellerName,
            business_name: userData.businessName,
            user_id: userData.id,
            invoice_number: sale.invoice_number,
            cost_price: costPrice,
            profit: itemProfit === null ? undefined : itemProfit,
            profit_margin: profitMargin === null ? undefined : profitMargin
          });
        });
      }
    });
    return allSales;
  };

  const fetchAllReports = useCallback(async (token: string, user: any) => {
    try {
      setLoading(true);
      
      const headers = {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
        'ngrok-skip-browser-warning': 'true'
      };

      if (user.role === 'admin') {
        await fetchBusinessData(headers, user.business_name || user.businessName);
        setDataSource('admin');
      } else {
        await fetchSellerData(headers);
        setDataSource('seller');
      }
      
    } catch (error) {
      console.error('Hitilafu wakati wa kupakua ripoti:', error);
      Alert.alert(t('app.error'), t('reports.error_load'));
      
      setBusinessStats(null);
      setAllProducts([]);
      setSoldProducts([]);
      setUnsoldProducts([]);
      setCustomers([]);
      setSales([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  const fetchBusinessData = async (headers: any, businessName: string) => {
    try {
      console.log('🏢 Inapakua data ya biashara:', businessName);

      // 1. Pata wauzaji wote wa biashara
      let usersRaw: any[] | null = await getCache<any[]>('admin:users');
      try {
        // Server-side filtering — Laravel returns only this business's members.
        const sellersResponse = await fetchWithTimeout(
          `${API_BASE_URL}/api/admin/users?business=${encodeURIComponent(businessName)}&role=seller,admin`,
          {
          method: 'GET',
          headers: headers
        }, 20000);

        if (sellersResponse.ok) {
          const responseData = await sellersResponse.json();
          usersRaw = Array.isArray(responseData) ? responseData : responseData.users || [];
          setCache('admin:users', usersRaw).catch(() => {});
        }
      } catch (error) {
        console.warn('⚠️ Sellers fetch failed, using cache:', error);
        if (!usersRaw) throw error;
      }

      let allSellers: User[] = [];
      if (usersRaw) {
        // The server already scopes this to the caller's own business
        // (JWT business_id). Re-filtering on `business_name === businessName`
        // here was actively harmful: businessName comes from client storage,
        // which the profile screen now rewrites with the *canonical*
        // businesses.business_name ("Jerald Stationaria") while the users rows
        // still carry the legacy spelling ("Jerald Stationari"). The strict
        // compare then matched nobody, sellers became [], and because
        // products/customers/sales are all intersected with sellers, the entire
        // report rendered empty. Only the approval rule is applied here.
        allSellers = usersRaw.filter((user: User) => user.status === 'approved');

        setSellers(allSellers);
        console.log('👥 Wauzaji walipatikana:', allSellers.length);
      }

      // 2. Pata bidhaa zote za biashara
      let productsRaw: any[] | null = await getCache<any[]>('admin:products');
      try {
        // slim=1: report only needs the handful of columns it displays.
        const productsResponse = await fetchWithTimeout(
          `${API_BASE_URL}/api/admin/products?business_name=${encodeURIComponent(businessName)}&slim=1`,
          {
          method: 'GET',
          headers: headers
        }, 20000);

        if (productsResponse.ok) {
          const responseData = await productsResponse.json();
          productsRaw = Array.isArray(responseData) ? responseData : responseData.products || [];
          setCache('admin:products', productsRaw).catch(() => {});
        }
      } catch (error) {
        console.warn('⚠️ Products fetch failed, using cache:', error);
        if (!productsRaw) throw error;
      }

      let rawProducts: Product[] = [];
      if (productsRaw) {
        // Keep only rows owned by a seller of this business. `seller_id`
        // membership is now the test — the previous `business_name` compare
        // repeated the same fragility as the seller filter above.
        rawProducts = productsRaw.filter((product: any) =>
          allSellers.some((s) => s.id === product.seller_id)
        ).map((product: any) => ({
          id: product.id,
          name: product.name,
          price: product.price || 0,
          expected_selling_price: product.expected_selling_price ?? null,
          category: product.category || null,
          stock: product.stock || 0,
          cost_price: product.price,
          seller_id: product.seller_id,
          description: product.description || null,
          is_active: product.is_active !== false,
          created_at: product.created_at || new Date().toISOString(),
          updated_at: product.updated_at || new Date().toISOString()
        }));
        
        console.log('📦 Bidhaa za biashara:', rawProducts.length);
      }

      // 3. Pata wateja wote wa biashara (server-filtered by business_name).
      //    Fetched BEFORE sales so customer names can be resolved from the
      //    slim sales payload, which carries customer_id but no relation.
      let customersRaw: any[] | null = await getCache<any[]>('admin:customers');
      try {
        const customersResponse = await fetchWithTimeout(
          `${API_BASE_URL}/api/admin/customers?business_name=${encodeURIComponent(businessName)}`,
          {
          method: 'GET',
          headers: headers
        }, 20000);

        if (customersResponse.ok && allSellers.length > 0) {
          const responseData = await customersResponse.json();
          customersRaw = Array.isArray(responseData) ? responseData : responseData.customers || [];
          setCache('admin:customers', customersRaw).catch(() => {});
        }
      } catch (error) {
        console.warn('⚠️ Customers fetch failed, using cache:', error);
        if (!customersRaw) throw error;
      }

      const customerNameById = new Map<string, string>();
      let allCustomers: Customer[] = [];
      if (customersRaw) {
        const customersData = customersRaw;
        customersData.forEach((c: any) => {
          if (c && c.id != null) customerNameById.set(c.id, c.name);
        });
        allCustomers = customersData.filter((customer: any) =>
          allSellers.some((s) => s.id === customer.seller_id)
        ).map((customer: any) => ({
          ...customer,
          seller_name: allSellers.find(s => s.id === customer.seller_id)?.full_name || 
                      allSellers.find(s => s.id === customer.seller_id)?.email || 
                      'Hajulikani'
        }));
        
        allCustomers = allCustomers.map(customer => adjustCustomerPurchases(customer));
        
        setCustomers(allCustomers);
        console.log('👥 Wateja wa biashara:', allCustomers.length);
      }

      // 4. Pata mauzo yote ya biashara (server-filtered + slim=1).
      let salesRaw: any[] | null = await getCache<any[]>('admin:sales');
      try {
        const salesResponse = await fetchWithTimeout(
          `${API_BASE_URL}/api/admin/sales?business_name=${encodeURIComponent(businessName)}&slim=1`,
          {
          method: 'GET',
          headers: headers
        }, 20000);

        if (salesResponse.ok) {
          const responseData = await salesResponse.json();
          salesRaw = Array.isArray(responseData) ? responseData : responseData.sales || [];
          setCache('admin:sales', salesRaw).catch(() => {});
        }
      } catch (error) {
        console.warn('⚠️ Sales fetch failed, using cache:', error);
        if (!salesRaw) throw error;
      }

      let allSales: Sale[] = [];
      if (salesRaw) {
        allSales = buildBusinessSales(salesRaw, allSellers, rawProducts, businessName, customerNameById);
        console.log('💰 Mauzo ya biashara:', allSales.length);
        setSales(allSales);
      }
      // Each sale with no customer data counts as one "unknown customer".
      const unknownCustomerSales = new Set(allSales.filter(s => !s.customer_id).map(s => s.id)).size;
      setUnknownCustomers(unknownCustomerSales);

      // 5. Process products with sales data
      const { allProducts, soldProducts, unsoldProducts } = processProductsWithSalesData(rawProducts, allSales);
      setAllProducts(allProducts);
      setSoldProducts(soldProducts);
      setUnsoldProducts(unsoldProducts);
      console.log('📊 Bidhaa zimeuzwa:', soldProducts.length, '| Hazijauzwa:', unsoldProducts.length);

      // 6. Hesabu takwimu za biashara
      if (allSales.length > 0) {
        const totalSalesAmount = allSales.reduce((sum, sale) => sum + sale.total_amount, 0);
        const totalProfit = allSales.reduce((sum, sale) => sum + (sale.profit || 0), 0);
        const today = new Date().toISOString().split('T')[0];
        const todaySales = allSales.filter(s => s.sale_date === today);
        const todaySalesAmount = todaySales.reduce((sum, sale) => sum + sale.total_amount, 0);
        const todayProfit = todaySales.reduce((sum, sale) => sum + (sale.profit || 0), 0);
        
        const totalCost = allSales.reduce((sum, sale) => {
          const cost = sale.cost_price || 0;
          const quantity = sale.quantity || 0;
          return sum + (cost * quantity);
        }, 0);
        const averageProfitMargin = totalCost > 0 ? (totalProfit / totalCost) * 100 : 0;

        setBusinessStats({
          totalSales: totalSalesAmount,
          totalCustomers: allCustomers.length + unknownCustomerSales,
          totalProducts: allProducts.length,
          totalSellers: allSellers.length,
          todaySales: todaySalesAmount,
          todayProfit: todayProfit,
          totalProfit: totalProfit,
          averageProfitMargin: averageProfitMargin,
          unknownCostSales: allSales.filter(s => s.cost_price == null).length,
          unknownCostSalesToday: todaySales.filter(s => s.cost_price == null).length
        });
      }

    } catch (error) {
      console.error('Error fetching business data:', error);
      throw error;
    }
  };

  const fetchSellerData = async (headers: any) => {
    try {
      // 1. Pata bidhaa za seller
      let productsRaw: any[] | null = await getCache<any[]>('d:products:my');
      try {
        const productsResponse = await fetchWithTimeout(`${API_BASE_URL}/api/products/my`, {
          method: 'GET',
          headers: headers
        }, 20000);

        if (productsResponse.ok) {
          const responseData = await productsResponse.json();
          productsRaw = Array.isArray(responseData) ? responseData : responseData.products || [];
          setCache('d:products:my', productsRaw).catch(() => {});
        }
      } catch (error) {
        console.warn('⚠️ Seller products fetch failed, using cache:', error);
        if (!productsRaw) throw error;
      }

      let rawProducts: Product[] = [];
      if (productsRaw) {
        rawProducts = productsRaw.map((product: any) => ({
          id: product.id,
          name: product.name,
          price: product.price || 0,
          expected_selling_price: product.expected_selling_price ?? null,
          category: product.category || null,
          stock: product.stock || 0,
          cost_price: product.price,
          seller_id: userData.id,
          description: product.description || null,
          is_active: product.is_active !== false,
          created_at: product.created_at || new Date().toISOString(),
          updated_at: product.updated_at || new Date().toISOString()
        }));
        
        console.log('📦 Bidhaa za seller:', rawProducts.length);
      }

      // 2. Pata mauzo ya seller
      let salesRaw: any[] | null = await getCache<any[]>('d:sales:my');
      try {
        const salesResponse = await fetchWithTimeout(`${API_BASE_URL}/api/sales/my`, {
          method: 'GET',
          headers: headers
        }, 20000);

        if (salesResponse.ok) {
          const responseData = await salesResponse.json();
          salesRaw = Array.isArray(responseData) ? responseData : responseData.sales || [];
          setCache('d:sales:my', salesRaw).catch(() => {});
        }
      } catch (error) {
        console.warn('⚠️ Seller sales fetch failed, using cache:', error);
        if (!salesRaw) throw error;
      }

      let allSales: Sale[] = [];
      if (salesRaw) {
        allSales = buildSellerSales(salesRaw, rawProducts);
        console.log('💰 Mauzo ya seller:', allSales.length);
        setSales(allSales);
      }
      // Each sale with no customer data counts as one "unknown customer".
      const unknownSellerCustomerSales = new Set(allSales.filter(s => !s.customer_id).map(s => s.id)).size;
      setUnknownCustomers(unknownSellerCustomerSales);

      // 3. Process products with sales data
      const { allProducts, soldProducts, unsoldProducts } = processProductsWithSalesData(rawProducts, allSales);
      setAllProducts(allProducts);
      setSoldProducts(soldProducts);
      setUnsoldProducts(unsoldProducts);
      console.log('📊 Bidhaa zimeuzwa:', soldProducts.length, '| Hazijauzwa:', unsoldProducts.length);

      // 4. Pata wateja wa seller
      let customersRaw: any[] | null = await getCache<any[]>('d:customers:my');
      try {
        const customersResponse = await fetchWithTimeout(`${API_BASE_URL}/api/customers/my`, {
          method: 'GET',
          headers: headers
        }, 20000);

        if (customersResponse.ok) {
          const responseData = await customersResponse.json();
          customersRaw = Array.isArray(responseData) ? responseData : responseData.customers || [];
          setCache('d:customers:my', customersRaw).catch(() => {});
        }
      } catch (error) {
        console.warn('⚠️ Seller customers fetch failed, using cache:', error);
        if (!customersRaw) throw error;
      }

      let sellerCustomers: Customer[] = [];
      if (customersRaw) {
        sellerCustomers = customersRaw;
        
        sellerCustomers = sellerCustomers.map(customer => adjustCustomerPurchases(customer));
        
        setCustomers(sellerCustomers);
        console.log('👥 Wateja wa seller:', sellerCustomers.length);
      }

      // 5. Hesabu takwimu za seller
      if (allSales.length > 0) {
        const totalSalesAmount = allSales.reduce((sum, sale) => sum + sale.total_amount, 0);
        const totalProfit = allSales.reduce((sum, sale) => sum + (sale.profit || 0), 0);
        const today = new Date().toISOString().split('T')[0];
        const todaySales = allSales.filter(s => s.sale_date === today);
        const todaySalesAmount = todaySales.reduce((sum, sale) => sum + sale.total_amount, 0);
        const todayProfit = todaySales.reduce((sum, sale) => sum + (sale.profit || 0), 0);
        
        const totalCost = allSales.reduce((sum, sale) => {
          const cost = sale.cost_price || 0;
          const quantity = sale.quantity || 0;
          return sum + (cost * quantity);
        }, 0);
        const averageProfitMargin = totalCost > 0 ? (totalProfit / totalCost) * 100 : 0;

        setBusinessStats({
          totalSales: totalSalesAmount,
          totalCustomers: sellerCustomers.length + unknownSellerCustomerSales,
          totalProducts: allProducts.length,
          totalSellers: 1,
          todaySales: todaySalesAmount,
          todayProfit: todayProfit,
          totalProfit: totalProfit,
          averageProfitMargin: averageProfitMargin,
          unknownCostSales: allSales.filter(s => s.cost_price == null).length,
          unknownCostSalesToday: todaySales.filter(s => s.cost_price == null).length
        });
      }

    } catch (error) {
      console.error('Error fetching seller data:', error);
      throw error;
    }
  };

  const refreshData = async () => {
    if (!userToken) {
      Alert.alert(t('app.error'), t('reports.error_auth'));
      return;
    }

    setRefreshing(true);
    try {
      const userDataStr = await AsyncStorage.getItem('userData');
      if (userDataStr) {
        const user = JSON.parse(userDataStr);
        await fetchAllReports(userToken, user);
      }
    } catch (error) {
      console.error('Error refreshing data:', error);
      Alert.alert(t('app.error'), t('reports.error_network'));
    } finally {
      setRefreshing(false);
    }
  };

  // ============================== SEARCH ==============================
  // Client-side search across the mauzo / bidhaa / wateja tabs (parity with
  // the Blade report page). Search is independent of the API calls.
  const matchesSearch = (...values: any[]) => {
    const term = searchTerm.trim().toLowerCase();
    if (!term) return true;
    return values.some((v) => String(v ?? '').toLowerCase().includes(term));
  };

  const getFilteredSales = () => searchTerm
    ? sales.filter(s => matchesSearch(s.product_name, s.customer_name, s.seller_name, s.sale_date))
    : sales;
  const getFilteredSoldProducts = () => searchTerm
    ? soldProducts.filter(p => matchesSearch(p.name, p.category))
    : soldProducts;
  const getFilteredUnsoldProducts = () => searchTerm
    ? unsoldProducts.filter(p => matchesSearch(p.name, p.category))
    : unsoldProducts;
  const getFilteredCustomers = () => searchTerm
    ? customers.filter(c => matchesSearch(c.name, c.phone, c.email))
    : customers;
  const noResultsText = () => `Hakuna matokeo yanayolingana na "${searchTerm}"`;
  // Known customers + one entry per sale that has no customer data.
  const totalCustomerCount = customers.length + unknownCustomers;

      const formatCurrency = (amount: number) => {
      if (!amount && amount !== 0) return 'TSh 0';
      if (isNaN(amount)) return 'TSh 0';
      const formatted = new Intl.NumberFormat('en-TZ', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      }).format(amount);
      return `TSh ${formatted}`;
    };

    // Money derived from a cost_price that was never recorded is unknown, not
    // zero. formatCurrency(null) would print "TSh 0" and read like a real
    // total, so those cells get a dash instead.
    const unknownMoney = (v: number | null | undefined) =>
      (typeof v === 'number' && Number.isFinite(v) ? formatCurrency(v) : '-');

  const localeMap: Record<string, string> = {
    sw: 'sw-TZ',
    en: 'en-US',
    fr: 'fr-FR',
    hi: 'hi-IN',
    ur: 'ur-PK',
    es: 'es-ES',
    de: 'de-DE',
    zh: 'zh-CN',
  };

  const formatDate = (dateString: string) => {
    try {
      const locale = localeMap[lang] || 'sw-TZ';
      return new Date(dateString).toLocaleDateString(locale);
    } catch {
      return dateString;
    }
  };

  const handlePrintPDF = () => {      Alert.alert(t('reports.export'), t('reports.coming_soon'));
  };

  const handleExportExcel = () => {      Alert.alert(t('reports.export'), t('reports.coming_soon'));
  };

  const handlePrint = () => {      Alert.alert(t('reports.print'), t('reports.coming_soon'));
  };

  const renderOverview = () => {
    const isAdmin = userData.role === 'admin';
    
    return (
      <View>
        <View style={styles.businessHeader}>
          <Text style={styles.businessName}>{userData.businessName}</Text>
          <Text style={styles.businessLocation}>{userData.businessLocation}</Text>
          <Text style={styles.reportPeriod}>
            {isAdmin ? t('reports.business_report') : t('reports.personal_report')} - {formatDate(new Date().toISOString())}
          </Text>
          {isAdmin && (
            <Text style={styles.sellersCount}>
              {dataSource === 'admin' 
                ? t('reports.data_source_admin', { sellers: sellers.length, customers: businessStats?.totalCustomers || 0, products: businessStats?.totalProducts || 0 })
                : t('reports.data_source_seller')}
            </Text>
          )}
        </View>

        {businessStats && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>
              {isAdmin ? t('reports.business_stats') : t('reports.your_stats')}
            </Text>
            <View style={styles.statsGrid}>
              <View style={styles.statCard}>
                <View style={[styles.statIcon, { backgroundColor: '#3498db20' }]}>
                  <Ionicons name="cash" size={24} color="#3498db" />
                </View>
                <Text style={styles.statNumber}>
                  {formatCurrency(businessStats.totalSales)}
                </Text>
                <Text style={styles.statLabel}>
                  {isAdmin ? t('reports.total_sales') : t('reports.all_sales')}
                </Text>
              </View>
              
              <View style={styles.statCard}>
                <View style={[styles.statIcon, { backgroundColor: '#27ae6020' }]}>
                  <Ionicons name="trending-up" size={24} color="#27ae60" />
                </View>
                <Text style={[styles.statNumber, { color: '#27ae60' }]}>
                  {formatCurrency(businessStats.totalProfit)}
                </Text>
                <Text style={styles.statLabel}>
                  {t('reports.total_profit')}
                </Text>
                {businessStats.unknownCostSales > 0 && (
                  <Text style={{ color: '#f39c12', fontSize: 10, marginTop: 2 }}>
                    {businessStats.unknownCostSales} {t('reports.cost_not_recorded')}
                  </Text>
                )}
              </View>
              
              <View style={styles.statCard}>
                <View style={[styles.statIcon, { backgroundColor: '#2ecc7120' }]}>
                  <Ionicons name="people" size={24} color="#2ecc71" />
                </View>
                <Text style={styles.statNumber}>
                  {businessStats.totalCustomers}
                </Text>
                <Text style={styles.statLabel}>
                  {isAdmin ? t('reports.all_customers') : t('reports.your_customers')}
                </Text>
              </View>
              
              <View style={styles.statCard}>
                <View style={[styles.statIcon, { backgroundColor: '#e74c3c20' }]}>
                  <Ionicons name="cart" size={24} color="#e74c3c" />
                </View>
                <Text style={styles.statNumber}>
                  {businessStats.totalProducts}
                </Text>
                <Text style={styles.statLabel}>
                  {t('reports.all_products')}
                </Text>
              </View>
            </View>
            
            <View style={styles.extraStats}>
              <View style={styles.extraStat}>
                <Ionicons name="today" size={16} color="#f39c12" />
                <Text style={styles.extraStatText}>{t('reports.today_sales')}: {formatCurrency(businessStats.todaySales)}</Text>
              </View>
              <View style={styles.extraStat}>
                <Ionicons name="trending-up" size={16} color="#27ae60" />
                <Text style={styles.extraStatText}>{t('reports.today_profit')}: {formatCurrency(businessStats.todayProfit)}{businessStats.unknownCostSalesToday > 0 ? ` (${businessStats.unknownCostSalesToday} ${t('reports.cost_not_recorded')})` : ''}</Text>
              </View>
              <View style={styles.extraStat}>
                <Ionicons name="analytics" size={16} color="#9b59b6" />
                <Text style={styles.extraStatText}>{t('reports.avg_margin')}: {businessStats.averageProfitMargin.toFixed(1)}%</Text>
              </View>
            </View>
          </View>
        )}

        <View style={styles.section}>
          <Text style={styles.sectionTitle}>
            {isAdmin ? t('reports.recent_sales') : t('reports.your_recent_sales')}
          </Text>
          {sales && sales.length > 0 ? (
            sales.slice(0, 5).map((sale, index) => (
              <View key={`${sale.id}-${index}`} style={styles.saleItem}>
                <View style={styles.saleInfo}>
                  <Text style={styles.saleProduct}>{sale.product_name}</Text>
                  <Text style={styles.saleDate}>
                    {formatDate(sale.sale_date)} - {sale.customer_name}
                    {isAdmin && sale.seller_name && (
                      <Text style={styles.sellerName}> • {sale.seller_name}</Text>
                    )}
                  </Text>
                  <Text style={styles.saleMeta}>
                    {sale.quantity} x {formatCurrency(sale.unit_price)} • 
                    {t('reports.total_sales')}: {formatCurrency(sale.total_amount)} • 
                    {t('reports.profit_label')}: <Text style={{ color: typeof sale.profit === 'number' && sale.profit < 0 ? '#e74c3c' : '#27ae60', fontWeight: 'bold' }}>
                      {unknownMoney(sale.profit)}
                    </Text>
                  </Text>
                </View>
                <View style={styles.saleAmount}>
                  <Text style={styles.saleTotal}>{formatCurrency(sale.total_amount)}</Text>
                  <Text style={[styles.profitText, { color: typeof sale.profit === 'number' && sale.profit < 0 ? '#e74c3c' : '#27ae60' }]}>
                    {typeof sale.profit_margin === 'number'
                      ? t('reports.margin_percent', { percent: sale.profit_margin.toFixed(1) })
                      : t('reports.cost_not_recorded')}
                  </Text>
                </View>
              </View>
            ))
          ) : (
            <View style={styles.noData}>
              <Ionicons name="document-text" size={48} color="#bdc3c7" />
              <Text style={styles.noDataText}>
                {t('reports.no_sales')}
              </Text>
            </View>
          )}
        </View>
      </View>
    );
  };

  const renderSalesReport = () => {
    const isAdmin = userData.role === 'admin';
    
    return (
      <View>
        <Text style={styles.sectionTitle}>
          {t('reports.sales_report')} - {isAdmin ? userData.businessName : t('reports.personal_report')}
          {isAdmin && dataSource === 'admin' && ` (${t('reports.sellers_count', { n: sellers.length })}`}
        </Text>
        {sales.length > 0 ? (
          getFilteredSales().length > 0 ? (
          getFilteredSales().map((sale, index) => (
            <View key={`${sale.id}-${index}`} style={styles.reportItem}>
              <View style={styles.reportItemMain}>
                <Text style={styles.reportItemTitle}>{sale.product_name}</Text>
                <Text style={styles.reportItemSubtitle}>
                  {sale.customer_name} • {formatDate(sale.sale_date)}
                  {isAdmin && sale.seller_name && (
                    <Text style={styles.sellerName}> • {sale.seller_name}</Text>
                  )}
                </Text>
                <Text style={styles.reportItemMeta}>
                  {sale.quantity} x {formatCurrency(sale.unit_price)} • 
                  {t('reports.invoice_number', { number: sale.invoice_number || 'N/A' })}
                </Text>
              </View>
              <View style={styles.reportItemSide}>
                <Text style={styles.reportItemAmount}>{formatCurrency(sale.total_amount)}</Text>
                <Text style={[styles.profitText, { color: typeof sale.profit === 'number' && sale.profit < 0 ? '#e74c3c' : '#27ae60' }]}>
                  {t('reports.profit_label')} {unknownMoney(sale.profit)}
                </Text>
                <Text style={styles.reportItemMargin}>
                  {typeof sale.profit_margin === 'number' ? `${sale.profit_margin.toFixed(1)}% margin` : t('reports.cost_not_recorded')}
                </Text>
              </View>
            </View>
          ))
          ) : (
            <View style={styles.noData}>
              <Ionicons name="search" size={48} color="#bdc3c7" />
              <Text style={styles.noDataText}>{noResultsText()}</Text>
            </View>
          )
        ) : (
          <View style={styles.noData}>
            <Ionicons name="receipt" size={48} color="#bdc3c7" />
            <Text style={styles.noDataText}>
              {isAdmin ? 'Hakuna mauzo bado' : 'Hakuna mauzo bado'}
            </Text>
          </View>
        )}
      </View>
    );
  };

  const renderSoldProducts = () => {
    const isAdmin = userData.role === 'admin';
    
    return (
      <View>
        <Text style={styles.productTabHeader}>
          {t('reports.sold_products')} ({soldProducts.length})
          {isAdmin && dataSource === 'admin' && ` • ${t('reports.all_products_count', { total: allProducts.length })}`}
        </Text>
        
        {soldProducts.length > 0 ? (
          getFilteredSoldProducts().length > 0 ? (
          getFilteredSoldProducts().map((product) => {
            // is the selling price, so the report printed the selling price in
            // both columns and every profit figure came out as zero.
            const purchasePrice = product.price ?? null;
            const sellingPrice = product.expected_selling_price ?? null;
            const profitPerUnit = (purchasePrice === null || sellingPrice === null) ? null : sellingPrice - purchasePrice;
            const profitMargin = profitPerUnit === null || !purchasePrice ? null : (profitPerUnit / purchasePrice) * 100;

            return (
              <View key={product.id} style={styles.productItem}>
                <View style={styles.productItemMain}>
                  <Text style={styles.productItemTitle}>{product.name}</Text>
                  <Text style={styles.productItemSubtitle}>{product.category || t('reports.no_category')}</Text>
                  <Text style={styles.productItemMeta}>
                    {t('reports.stock')}: {product.stock} • 
                    {t('reports.purchase_price')}: <Text style={{color: '#e74c3c', fontWeight: 'bold'}}> {unknownMoney(purchasePrice)}</Text> • 
                    {t('reports.expected_selling_price')}: <Text style={{color: '#27ae60', fontWeight: 'bold'}}> {unknownMoney(sellingPrice)}</Text>
                  </Text>
                  
                  <Text style={styles.productSalesData}>
                    {t('reports.quantity_sold')}: <Text style={{color: '#3498db', fontWeight: 'bold'}}>{product.total_sold}</Text> • 
                    {t('reports.total_revenue')}: {formatCurrency(product.total_revenue || 0)} • 
                    {t('reports.total_profit_product')}: {formatCurrency(product.total_profit || 0)}
                  </Text>
                  
                  <Text style={[styles.profitText, { color: (product.total_profit || 0) >= 0 ? '#27ae60' : '#e74c3c', fontSize: 12 }]}>
                    {t('reports.product_profit')}: {unknownMoney(profitPerUnit)}{profitMargin === null ? '' : ' (' + profitMargin.toFixed(1) + '%)'}
                  </Text>
                  
                  {isAdmin && (
                    <Text style={styles.productItemSeller}>
                      {t('reports.seller_name')} {sellers.find(s => s.id === product.seller_id)?.full_name || product.seller_id}
                    </Text>
                  )}
                </View>
                <View style={styles.productItemSide}>
                  <Text style={styles.productItemAmount}>{formatCurrency(product.total_revenue || 0)}</Text>
                  <Text style={[styles.profitText, { color: (product.total_profit || 0) >= 0 ? '#27ae60' : '#e74c3c' }]}>
                    {t('reports.profit_label')} {formatCurrency(product.total_profit || 0)}
                  </Text>
                  <Text style={styles.productItemMargin}>
                    {t('reports.units_sold', { count: product.total_sold || 0 })}
                  </Text>
                  <View style={styles.successBadge}>
                    <Ionicons name="checkmark-circle" size={12} color="#27ae60" />
                    <Text style={styles.successBadgeText}>{t('reports.sold_status')}</Text>
                  </View>
                </View>
              </View>
            );
          })
          ) : (
            <View style={styles.noData}>
              <Ionicons name="search" size={48} color="#bdc3c7" />
              <Text style={styles.noDataText}>{noResultsText()}</Text>
            </View>
          )
        ) : (
          <View style={styles.noData}>
            <Ionicons name="checkmark-done" size={48} color="#bdc3c7" />
            <Text style={styles.noDataText}>
              {t('reports.no_products_sold')}
            </Text>
            <Text style={styles.noDataSubtext}>
              {t('reports.products_appear')}
            </Text>
          </View>
        )}
      </View>
    );
  };

  const renderUnsoldProducts = () => {
    const isAdmin = userData.role === 'admin';
    
    return (
      <View>
        <Text style={styles.productTabHeader}>
          {t('reports.unsold_products')} ({unsoldProducts.length})
          {isAdmin && dataSource === 'admin' && ` • ${t('reports.all_products_count', { total: allProducts.length })}`}
        </Text>
        
        {unsoldProducts.length > 0 ? (
          getFilteredUnsoldProducts().length > 0 ? (
          getFilteredUnsoldProducts().map((product) => {
            // is the selling price, so the report printed the selling price in
            // both columns and every profit figure came out as zero.
            const purchasePrice = product.price ?? null;
            const sellingPrice = product.expected_selling_price ?? null;
            const profitPerUnit = (purchasePrice === null || sellingPrice === null) ? null : sellingPrice - purchasePrice;
            const profitMargin = profitPerUnit === null || !purchasePrice ? null : (profitPerUnit / purchasePrice) * 100;

            return (
              <View key={product.id} style={[styles.productItem, { borderLeftWidth: 3, borderLeftColor: '#f39c12' }]}>
                <View style={styles.productItemMain}>
                  <Text style={styles.productItemTitle}>{product.name}</Text>
                  <Text style={styles.productItemSubtitle}>{product.category || t('reports.no_category')}</Text>
                  <Text style={styles.productItemMeta}>
                    Hisa: <Text style={{color: '#e74c3c', fontWeight: 'bold'}}>{product.stock}</Text> • 
                    Bei ya Ununuzi: <Text style={{color: '#e74c3c', fontWeight: 'bold'}}> {unknownMoney(purchasePrice)}</Text> • 
                    Bei ya Kuuzia: <Text style={{color: '#27ae60', fontWeight: 'bold'}}> {unknownMoney(sellingPrice)}</Text>
                  </Text>
                  
                  <Text style={styles.unsoldInfo}>
                    <Ionicons name="alert-circle" size={12} color="#f39c12" />
                    <Text style={{color: '#f39c12', marginLeft: 4}}>
                      {t('reports.unsold_status')} • {t('reports.expected_profit', { amount: unknownMoney(profitPerUnit) })}{profitMargin === null ? ' (' + t('reports.cost_not_recorded') + ')' : ' (' + profitMargin.toFixed(1) + '%)'}
                    </Text>
                  </Text>
                  
                  <Text style={styles.productPotential}>
                    {t('reports.expected_revenue', { stock: product.stock })}: 
                    <Text style={{color: '#27ae60', fontWeight: 'bold'}}> {formatCurrency(sellingPrice * product.stock)}</Text> mapato • 
                    {t('reports.profit_label')} <Text style={{color: '#27ae60', fontWeight: 'bold'}}> {unknownMoney(profitPerUnit === null ? null : profitPerUnit * product.stock)}</Text>
                  </Text>
                  
                  {isAdmin && (
                    <Text style={styles.productItemSeller}>
                      {t('reports.seller_name')} {sellers.find(s => s.id === product.seller_id)?.full_name || product.seller_id}
                    </Text>
                  )}
                </View>
                <View style={styles.productItemSide}>
                  <Text style={[styles.profitText, { color: (profitPerUnit ?? 0) >= 0 ? '#27ae60' : '#e74c3c' }]}>
                    {t('reports.product_profit')}: {unknownMoney(profitPerUnit)}
                  </Text>
                  <Text style={styles.productItemMargin}>
                    {profitMargin === null ? t('reports.cost_not_recorded') : t('reports.margin_percent', { percent: profitMargin.toFixed(1) })}
                  </Text>
                  <Text style={styles.productItemRevenue}>
                    {t('reports.unsold_status')}
                  </Text>
                  <View style={styles.warningBadge}>
                    <Ionicons name="time" size={12} color="#f39c12" />
                    <Text style={styles.warningBadgeText}>{t('reports.unsold_status')}</Text>
                  </View>
                </View>
              </View>
            );
          })
          ) : (
            <View style={styles.noData}>
              <Ionicons name="search" size={48} color="#bdc3c7" />
              <Text style={styles.noDataText}>{noResultsText()}</Text>
            </View>
          )
        ) : (
          <View style={styles.noData}>
            <Ionicons name="happy" size={48} color="#2ecc71" />
            <Text style={[styles.noDataText, { color: '#27ae60' }]}>
              {t('reports.all_sold_out')}
            </Text>
            <Text style={styles.noDataSubtext}>
              {t('reports.no_unsold')}
            </Text>
          </View>
        )}
      </View>
    );
  };

  const renderProductsReport = () => {
    const isAdmin = userData.role === 'admin';
    
    return (
      <View>
        <Text style={styles.sectionTitle}>
          {t('reports.product_report')} - {isAdmin ? userData.businessName : t('reports.personal_report')}
          {isAdmin && dataSource === 'admin' && ` (${t('reports.all_products_count', { total: allProducts.length })}`}
        </Text>
        
        {/* Tabs za bidhaa zimeuzwa/hazijauzwa */}
        <View style={styles.productTabs}>
          <TouchableOpacity 
            style={[styles.productTab, activeProductTab === 'sold' && styles.activeProductTab]}
            onPress={() => setActiveProductTab('sold')}
          >
            <Ionicons 
              name="checkmark-circle" 
              size={16} 
              color={activeProductTab === 'sold' ? 'white' : '#27ae60'} 
            />
            <Text style={[styles.productTabText, activeProductTab === 'sold' && styles.activeProductTabText]}>
              {t('reports.sold_products_count')} ({soldProducts.length})
            </Text>
          </TouchableOpacity>

          <TouchableOpacity 
            style={[styles.productTab, activeProductTab === 'unsold' && styles.activeProductTab]}
            onPress={() => setActiveProductTab('unsold')}
          >
            <Ionicons 
              name="time" 
              size={16} 
              color={activeProductTab === 'unsold' ? 'white' : '#f39c12'} 
            />
            <Text style={[styles.productTabText, activeProductTab === 'unsold' && styles.activeProductTabText]}>
              {t('reports.unsold_products_count')} ({unsoldProducts.length})
            </Text>
          </TouchableOpacity>
        </View>

        {/* Content kulingana na tab iliyochaguliwa */}
        {activeProductTab === 'sold' ? renderSoldProducts() : renderUnsoldProducts()}

        {/* Summary ya bidhaa */}
        <View style={styles.productSummary}>
          <Text style={styles.summaryTitle}>{t('reports.product_summary')}</Text>
          <View style={styles.summaryStats}>
            <View style={styles.summaryStat}>
              <Text style={styles.summaryStatValue}>{allProducts.length}</Text>
              <Text style={styles.summaryStatLabel}>{t('reports.total_products')}</Text>
            </View>
            <View style={styles.summaryStat}>
              <Text style={[styles.summaryStatValue, { color: '#27ae60' }]}>{soldProducts.length}</Text>
              <Text style={styles.summaryStatLabel}>{t('reports.sold_products_count')}</Text>
            </View>
            <View style={styles.summaryStat}>
              <Text style={[styles.summaryStatValue, { color: '#f39c12' }]}>{unsoldProducts.length}</Text>
              <Text style={styles.summaryStatLabel}>{t('reports.unsold_products_count')}</Text>
            </View>
            <View style={styles.summaryStat}>
              <Text style={styles.summaryStatValue}>
                {allProducts.length > 0 ? Math.round((soldProducts.length / allProducts.length) * 100) : 0}%
              </Text>
              <Text style={styles.summaryStatLabel}>{t('reports.sold_percentage')}</Text>
            </View>
          </View>
        </View>
      </View>
    );
  };

  const renderCustomersReport = () => {
    const isAdmin = userData.role === 'admin';
    
    return (
      <View>
        <Text style={styles.sectionTitle}>
          {t('reports.customer_report')} - {isAdmin ? userData.businessName : t('reports.personal_report')}
          {isAdmin && dataSource === 'admin' && ` (${totalCustomerCount} ${t('reports.customers')})`}
        </Text>
        
        {customers && customers.length > 0 ? (
          getFilteredCustomers().length > 0 ? (
          getFilteredCustomers().map((customer) => {
            const actualTotal = customer.actual_purchases || customer.total_purchases;
            const actualCount = customer.actual_purchase_count || customer.purchases_count;
            const lastPurchase = customer.last_purchase_date ? 
              formatDate(customer.last_purchase_date) : t('reports.no_sales');

            return (
              <View key={customer.id} style={styles.customerItem}>
                <View style={styles.customerAvatar}>
                  <Ionicons name="person-circle" size={40} color="#3498db" />
                </View>
                
                <View style={styles.customerInfo}>
                  <Text style={styles.customerName}>{customer.name}</Text>
                  
                  <View style={styles.customerContact}>
                    {customer.phone && (
                      <View style={styles.contactRow}>
                        <Ionicons name="call" size={14} color="#7f8c8d" />
                        <Text style={styles.contactText}>{customer.phone}</Text>
                      </View>
                    )}
                    
                    {customer.email && (
                      <View style={styles.contactRow}>
                        <Ionicons name="mail" size={14} color="#7f8c8d" />
                        <Text style={styles.contactText}>{customer.email}</Text>
                      </View>
                    )}
                  </View>
                  
                  <View style={styles.customerMeta}>
                    <Text style={styles.customerMetaText}>
                      <Ionicons name="receipt" size={12} color="#95a5a6" /> {actualCount} mauzo
                    </Text>
                    <Text style={styles.customerMetaText}>
                      <Ionicons name="calendar" size={12} color="#95a5a6" /> {lastPurchase}
                    </Text>
                    {isAdmin && customer.seller_name && (
                      <Text style={[styles.customerMetaText, { color: '#3498db' }]}>
                        <Ionicons name="person" size={12} color="#3498db" /> {customer.seller_name}
                      </Text>
                    )}
                  </View>
                </View>
                
                <View style={styles.customerStats}>
                  <Text style={styles.customerTotal}>
                    {formatCurrency(actualTotal)}
                  </Text>
                  <Text style={styles.customerLabel}>
                    {t('reports.total_purchases')}
                  </Text>
                  <Text style={styles.customerNote}>
                    <Text style={{color: '#27ae60', fontSize: 10, fontStyle: 'italic'}}>
                      {t('reports.adjusted_note')}
                    </Text>
                  </Text>
                </View>
              </View>
            );
          })
          ) : (
            <View style={styles.noData}>
              <Ionicons name="search" size={48} color="#bdc3c7" />
              <Text style={styles.noDataText}>{noResultsText()}</Text>
            </View>
          )
        ) : (
          <View style={styles.noData}>
            <Ionicons name="people" size={48} color="#bdc3c7" />
            <Text style={styles.noDataText}>
              {t('reports.no_customers')}
            </Text>
            <Text style={styles.noDataSubtext}>
              {t('reports.customers_appear')}
            </Text>
          </View>
        )}
      </View>
    );
  };

  const renderReportContent = () => {
    switch (activeReport) {
      case 'overview':
        return renderOverview();
      case 'sales':
        return renderSalesReport();
      case 'products':
        return renderProductsReport();
      case 'customers':
        return renderCustomersReport();
      default:
        return renderOverview();
    }
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.screenSafe} edges={['top']}>
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#3498db" />
          <Text style={styles.loadingText}>
            {t('reports.loading_full')}
          </Text>
          <Text style={styles.fallbackText}>
            {userData.role === 'admin' ? t('reports.loading_business') : t('reports.loading_personal')}
          </Text>
        </View>
      </SafeAreaView>
    );
  }

  const isAdmin = userData.role === 'admin';

  return (
    <SafeAreaView style={styles.screenSafe} edges={['top']}>
    <ScrollView 
      style={styles.container} 
      showsVerticalScrollIndicator={false}
      refreshControl={
        <RefreshControl 
          refreshing={refreshing} 
          onRefresh={refreshData}
          colors={['#3498db']}
          tintColor="#3498db"
        />
      }
    >
      <View style={styles.header}>
        <View>
          <Text style={styles.title}>{t('reports.title')}</Text>
          <Text style={styles.userEmail}>{userData.email}</Text>
          <Text style={styles.roleBadge}>
            {isAdmin ? t('reports.status_admin') : t('reports.status_seller')} • {dataSource === 'admin' ? t('reports.data_business') : t('reports.data_seller')}
          </Text>
          <Text style={styles.adjustmentNote}>
            {t('reports.purchase_price_note')}
          </Text>
        </View>
        <LogoutButton iconOnly />
      </View>

      <View style={styles.reportNav}>
        <TouchableOpacity 
          style={[styles.navButton, activeReport === 'overview' && styles.activeNavButton]}
          onPress={() => { setActiveReport('overview'); setSearchTerm(''); }}
        >
          <Ionicons 
            name="grid" 
            size={16} 
            color={activeReport === 'overview' ? 'white' : '#666'} 
          />
          <Text style={[styles.navButtonText, activeReport === 'overview' && styles.activeNavButtonText]}>
            {t('reports.overview')}
          </Text>
        </TouchableOpacity>

        <TouchableOpacity 
          style={[styles.navButton, activeReport === 'sales' && styles.activeNavButton]}
          onPress={() => { setActiveReport('sales'); setSearchTerm(''); }}
        >
          <Ionicons 
            name="receipt" 
            size={16} 
            color={activeReport === 'sales' ? 'white' : '#666'} 
          />
          <Text style={[styles.navButtonText, activeReport === 'sales' && styles.activeNavButtonText]}>
            {t('reports.sales')} ({sales.length})
          </Text>
        </TouchableOpacity>

        <TouchableOpacity 
          style={[styles.navButton, activeReport === 'products' && styles.activeNavButton]}
          onPress={() => { setActiveReport('products'); setSearchTerm(''); }}
        >
          <Ionicons 
            name="cube" 
            size={16} 
            color={activeReport === 'products' ? 'white' : '#666'} 
          />
          <Text style={[styles.navButtonText, activeReport === 'products' && styles.activeNavButtonText]}>
            {t('reports.products')} ({allProducts.length})
          </Text>
        </TouchableOpacity>

        <TouchableOpacity 
          style={[styles.navButton, activeReport === 'customers' && styles.activeNavButton]}
          onPress={() => { setActiveReport('customers'); setSearchTerm(''); }}
        >
          <Ionicons 
            name="people" 
            size={16} 
            color={activeReport === 'customers' ? 'white' : '#666'} 
          />
          <Text style={[styles.navButtonText, activeReport === 'customers' && styles.activeNavButtonText]}>
            {t('reports.customers')} ({totalCustomerCount})
          </Text>
        </TouchableOpacity>
      </View>

      {activeReport !== 'overview' && (
        <View style={styles.searchContainer}>
          <Ionicons name="search" size={18} color="#95a5a6" style={styles.searchIcon} />
          <TextInput
            style={styles.searchInput}
            placeholder={t('app.search')}
            placeholderTextColor="#95a5a6"
            value={searchTerm}
            onChangeText={setSearchTerm}
            autoCorrect={false}
            autoCapitalize="none"
          />
          {searchTerm.length > 0 && (
            <TouchableOpacity onPress={() => setSearchTerm('')} style={styles.searchClear}>
              <Ionicons name="close-circle" size={18} color="#95a5a6" />
            </TouchableOpacity>
          )}
        </View>
      )}

      <View style={styles.reportContent}>
        {renderReportContent()}
      </View>

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>{t('reports.export_options')}</Text>
        <View style={styles.exportOptions}>
          <TouchableOpacity style={styles.exportButton} onPress={handlePrintPDF}>
            <View style={[styles.exportIcon, { backgroundColor: '#e74c3c20' }]}>
              <Ionicons name="document-text" size={20} color="#e74c3c" />
            </View>
            <Text style={styles.exportButtonText}>PDF</Text>
          </TouchableOpacity>

          <TouchableOpacity style={styles.exportButton} onPress={handleExportExcel}>
            <View style={[styles.exportIcon, { backgroundColor: '#27ae6020' }]}>
              <Ionicons name="document" size={20} color="#27ae60" />
            </View>
            <Text style={styles.exportButtonText}>Excel</Text>
          </TouchableOpacity>

          <TouchableOpacity style={styles.exportButton} onPress={handlePrint}>
            <View style={[styles.exportIcon, { backgroundColor: '#3498db20' }]}>
              <Ionicons name="print" size={20} color="#3498db" />
            </View>
            <Text style={styles.exportButtonText}>Print</Text>
          </TouchableOpacity>
        </View>
      </View>

      {isAdmin && (
        <View style={styles.statusCard}>
          <Ionicons 
            name={dataSource === 'admin' ? "shield-checkmark" : "warning"} 
            size={24} 
            color={dataSource === 'admin' ? "#27ae60" : "#f39c12"} 
          />
          <Text style={styles.statusText}>
            {dataSource === 'admin' 
              ? t('reports.status_card_admin', { sellers: sellers.length, customers: totalCustomerCount, products: allProducts.length })
              : t('reports.status_card_seller')
            }
          </Text>
        </View>
      )}
    </ScrollView>
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
    padding: 16,
  },
  center: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  loadingText: {
    marginTop: 10,
    fontSize: 16,
    color: '#666',
    textAlign: 'center',
  },
  fallbackText: {
    marginTop: 8,
    fontSize: 14,
    color: '#f39c12',
    textAlign: 'center',
    fontStyle: 'italic',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 20,
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  userEmail: {
    fontSize: 14,
    color: '#7f8c8d',
    marginTop: 4,
  },
  roleBadge: {
    fontSize: 12,
    color: '#3498db',
    marginTop: 2,
    fontWeight: 'bold',
  },
  adjustmentNote: {
    fontSize: 11,
    color: '#27ae60',
    marginTop: 2,
    fontStyle: 'italic',
    fontWeight: '500',
  },
  businessHeader: {
    backgroundColor: 'white',
    padding: 20,
    borderRadius: 16,
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 6,
    elevation: 3,
  },
  businessName: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  businessLocation: {
    fontSize: 14,
    color: '#7f8c8d',
    marginTop: 4,
  },
  reportPeriod: {
    fontSize: 12,
    color: '#95a5a6',
    marginTop: 8,
    fontStyle: 'italic',
  },
  sellersCount: {
    fontSize: 12,
    color: '#3498db',
    marginTop: 4,
    fontWeight: '500',
  },
  reportNav: {
    flexDirection: 'row',
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 4,
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  navButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 10,
    paddingHorizontal: 8,
    borderRadius: 8,
    gap: 6,
  },
  activeNavButton: {
    backgroundColor: '#3498db',
  },
  navButtonText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#666',
  },
  activeNavButtonText: {
    color: 'white',
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'white',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#ecf0f1',
    paddingHorizontal: 12,
    marginBottom: 16,
  },
  searchIcon: {
    marginRight: 6,
  },
  searchInput: {
    flex: 1,
    paddingVertical: 12,
    fontSize: 14,
    color: '#2c3e50',
  },
  searchClear: {
    padding: 4,
  },
  reportContent: {
    marginBottom: 24,
  },
  section: {
    marginBottom: 24,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 16,
  },
  productTabs: {
    flexDirection: 'row',
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 4,
    marginBottom: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  productTab: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 10,
    paddingHorizontal: 8,
    borderRadius: 8,
    gap: 6,
  },
  activeProductTab: {
    backgroundColor: '#3498db',
  },
  productTabText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#666',
  },
  activeProductTabText: {
    color: 'white',
  },
  productTabHeader: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 16,
    textAlign: 'center',
  },
  productItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    backgroundColor: 'white',
    padding: 16,
    borderRadius: 12,
    marginBottom: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 2,
  },
  productItemMain: {
    flex: 1,
    marginRight: 12,
  },
  productItemTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  productItemSubtitle: {
    fontSize: 14,
    color: '#7f8c8d',
    marginTop: 2,
  },
  productItemMeta: {
    fontSize: 12,
    color: '#95a5a6',
    marginTop: 4,
  },
  productSalesData: {
    fontSize: 12,
    color: '#3498db',
    marginTop: 4,
    fontWeight: '500',
  },
  unsoldInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    fontSize: 12,
    marginTop: 4,
  },
  productPotential: {
    fontSize: 11,
    color: '#7f8c8d',
    marginTop: 4,
    fontStyle: 'italic',
  },
  productItemSeller: {
    fontSize: 11,
    color: '#3498db',
    marginTop: 2,
    fontStyle: 'italic',
  },
  productItemSide: {
    alignItems: 'flex-end',
    minWidth: 100,
  },
  productItemAmount: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#e74c3c',
  },
  productItemMargin: {
    fontSize: 12,
    color: '#7f8c8d',
    marginTop: 2,
  },
  productItemRevenue: {
    fontSize: 12,
    color: '#f39c12',
    marginTop: 4,
    fontWeight: '500',
  },
  successBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#27ae6020',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
    marginTop: 8,
    gap: 4,
  },
  successBadgeText: {
    fontSize: 10,
    color: '#27ae60',
    fontWeight: 'bold',
  },
  warningBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f39c1220',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
    marginTop: 8,
    gap: 4,
  },
  warningBadgeText: {
    fontSize: 10,
    color: '#f39c12',
    fontWeight: 'bold',
  },
  productSummary: {
    backgroundColor: 'white',
    padding: 16,
    borderRadius: 12,
    marginTop: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 2,
  },
  summaryTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 12,
    textAlign: 'center',
  },
  summaryStats: {
    flexDirection: 'row',
    justifyContent: 'space-around',
  },
  summaryStat: {
    alignItems: 'center',
  },
  summaryStatValue: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  summaryStatLabel: {
    fontSize: 11,
    color: '#95a5a6',
    marginTop: 2,
  },
  statsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
  },
  statCard: {
    width: '48%',
    backgroundColor: 'white',
    padding: 16,
    borderRadius: 12,
    alignItems: 'center',
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 2,
  },
  statIcon: {
    width: 48,
    height: 48,
    borderRadius: 24,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 8,
  },
  statNumber: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 4,
    textAlign: 'center',
  },
  statLabel: {
    fontSize: 12,
    color: '#7f8c8d',
    textAlign: 'center',
    marginBottom: 2,
  },
  extraStats: {
    backgroundColor: '#f8f9fa',
    padding: 12,
    borderRadius: 8,
    marginTop: 8,
  },
  extraStat: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 6,
  },
  extraStatText: {
    fontSize: 13,
    color: '#2c3e50',
    marginLeft: 8,
  },
  saleItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: 'white',
    padding: 16,
    borderRadius: 12,
    marginBottom: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 2,
  },
  saleInfo: {
    flex: 1,
  },
  saleProduct: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  saleDate: {
    fontSize: 14,
    color: '#7f8c8d',
    marginTop: 4,
  },
  saleMeta: {
    fontSize: 12,
    color: '#95a5a6',
    marginTop: 4,
  },
  sellerName: {
    fontSize: 12,
    color: '#3498db',
    fontWeight: '500',
  },
  saleAmount: {
    alignItems: 'flex-end',
  },
  saleTotal: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#27ae60',
    marginTop: 4,
  },
  profitText: {
    fontSize: 14,
    fontWeight: 'bold',
    marginTop: 4,
  },
  reportItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    backgroundColor: 'white',
    padding: 16,
    borderRadius: 12,
    marginBottom: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 2,
  },
  reportItemMain: {
    flex: 1,
    marginRight: 12,
  },
  reportItemTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  reportItemSubtitle: {
    fontSize: 14,
    color: '#7f8c8d',
    marginTop: 2,
  },
  reportItemMeta: {
    fontSize: 12,
    color: '#95a5a6',
    marginTop: 4,
  },
  reportItemCost: {
    fontSize: 12,
    color: '#e74c3c',
    marginTop: 2,
    fontWeight: '500',
  },
  reportItemSide: {
    alignItems: 'flex-end',
  },
  reportItemAmount: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#e74c3c',
  },
  reportItemMargin: {
    fontSize: 12,
    color: '#7f8c8d',
    marginTop: 2,
  },
  reportItemRevenue: {
    fontSize: 12,
    color: '#27ae60',
    marginTop: 4,
    fontWeight: '500',
  },
  reportItemQuantity: {
    fontSize: 11,
    color: '#95a5a6',
    marginTop: 2,
  },
  customerItem: {
    flexDirection: 'row',
    backgroundColor: 'white',
    padding: 16,
    borderRadius: 12,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
    borderWidth: 1,
    borderColor: '#ecf0f1',
  },
  customerAvatar: {
    marginRight: 12,
    justifyContent: 'center',
  },
  customerInfo: {
    flex: 1,
  },
  customerName: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 6,
  },
  customerContact: {
    marginBottom: 8,
  },
  contactRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 4,
  },
  contactText: {
    fontSize: 13,
    color: '#7f8c8d',
    marginLeft: 6,
  },
  customerMeta: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  customerMetaText: {
    fontSize: 12,
    color: '#95a5a6',
  },
  customerStats: {
    alignItems: 'flex-end',
    justifyContent: 'center',
    minWidth: 100,
  },
  customerTotal: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#27ae60',
    marginBottom: 2,
  },
  customerLabel: {
    fontSize: 11,
    color: '#95a5a6',
  },
  customerNote: {
    fontSize: 10,
    color: '#27ae60',
    marginTop: 2,
    fontStyle: 'italic',
  },
  noData: {
    alignItems: 'center',
    justifyContent: 'center',
    padding: 40,
    backgroundColor: 'white',
    borderRadius: 12,
    marginVertical: 8,
  },
  noDataText: {
    fontSize: 16,
    color: '#95a5a6',
    marginTop: 12,
    fontWeight: '500',
    textAlign: 'center',
  },
  noDataSubtext: {
    fontSize: 14,
    color: '#bdc3c7',
    marginTop: 8,
    textAlign: 'center',
    lineHeight: 20,
  },
  exportOptions: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    gap: 12,
  },
  exportButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'white',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderRadius: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
    gap: 8,
  },
  exportIcon: {
    width: 36,
    height: 36,
    borderRadius: 18,
    justifyContent: 'center',
    alignItems: 'center',
  },
  exportButtonText: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  statusCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'white',
    padding: 16,
    borderRadius: 12,
    marginTop: 16,
    marginBottom: 24,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },
  statusText: {
    flex: 1,
    fontSize: 14,
    color: '#2c3e50',
    marginLeft: 12,
    fontWeight: '500',
  },
});