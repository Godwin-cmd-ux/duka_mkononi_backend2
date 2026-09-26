import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import React, { useEffect, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    Modal,
    RefreshControl,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLang } from '../../context/LanguageContext';
import LogoutButton from '../../components/logout-button';
import { getCache, setCache } from '../../db/cache';
import { fetchWithTimeout } from '../../lib/network';

// ✅ BADILISHA HII IWE URL YA SERVER YAKO
import { API_BASE_URL } from '../../constants/api';

interface Sale {
  id: number;
  product_id: number;
  product_name: string;
  quantity: number;
  unit_price: number;
  total_amount: number;
  sale_date: string;
  customer_name: string;
  seller_name: string;
  business_name: string;
  user_id: number;
  invoice_number?: string;
  cost_price?: number;
}

interface Product {
  id: number;
  name: string;
  price: number;
  category: string;
  stock: number;
  cost_price?: number;
  seller_id: number;
  business_name?: string;
  created_at?: string;
}

interface Seller {
  id: number;
  email: string;
  full_name: string;
  business_name: string;
  role: string;
  status: string;
}

interface Expense {
  id: string;
  amount: number;
  description: string;
  category: string;
  expense_date: string;
  notes?: string;
  created_at: string;
}

interface DailySummary {
  date: string;
  totalSales: number;
  totalProducts: number;
  totalProfit: number;
  totalExpenses: number;
  netProfit: number;
  sales: Sale[];
  customers: string[];
  sellers: Seller[];
  expenses: Expense[];
}

interface BusinessEvent {
  id: number;
  type: 'sale' | 'product_added' | 'product_updated' | 'seller_joined' | 'low_stock' | 'payment' | 'announcement';
  title: string;
  description: string;
  amount?: number;
  seller_name?: string;
  customer_name?: string;
  product_name?: string;
  event_date: string;
  business_name: string;
  metadata?: Record<string, any>;
}

interface DebugBusinessData {
  user: {
    id: string;
    email: string;
    role: string;
    business_name: string;
    status: string;
  };
  all_expenses_count: number;
  today_expenses_count: number;
  today_expenses_total: number;
  sample_expenses: Expense[];
}

export default function PreviewScreen() {
  const { t, lang } = useLang();
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [dailySummaries, setDailySummaries] = useState<DailySummary[]>([]);
  const [selectedDay, setSelectedDay] = useState<DailySummary | null>(null);
  const [modalVisible, setModalVisible] = useState(false);
  const [products, setProducts] = useState<Product[]>([]);
  const [sellers, setSellers] = useState<Seller[]>([]);
  const [debugData, setDebugData] = useState<DebugBusinessData | null>(null);
  const [showDebug, setShowDebug] = useState(false);
  
  const [userData, setUserData] = useState({
    businessName: '',
    businessLocation: '',
    userId: '',
    userRole: ''
  });
  
  const [businessData, setBusinessData] = useState({
    totalSellers: 0,
    totalProducts: 0,
    totalSalesAmount: 0,
    totalProfit: 0,
    totalNetProfit: 0
  });

  // ✅ Expenses state
  const [dailyExpenses, setDailyExpenses] = useState<{[key: string]: Expense[]}>({});
  const [expensesLoading, setExpensesLoading] = useState(false);

  // ✅ Tabs state
  const [activeTab, setActiveTab] = useState<'days' | 'events'>('days');
  
  // ✅ Events state
  const [businessEvents, setBusinessEvents] = useState<BusinessEvent[]>([]);
  const [eventsLoading, setEventsLoading] = useState(false);

  useEffect(() => {
    loadUserData();
  }, [lang]);

  // ✅ DEBUG: Check business data
  const checkBusinessData = async (token: string) => {
    try {
      console.log('🔍 Running business debug check...');
      const cachedDebug = await getCache<any>('d:debug:check-business');
      if (cachedDebug) { setDebugData(cachedDebug); }

      const response = await fetchWithTimeout(`${API_BASE_URL}/api/debug/check-business`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        }
      });

      if (response.ok) {
        const data = await response.json();
        console.log('✅ DEBUG BUSINESS DATA:', JSON.stringify(data, null, 2));
        setDebugData(data);
        setCache('d:debug:check-business', data).catch(() => {});
        
        if (data.all_expenses_count === 0) {
          console.log('⚠️ Hakuna expenses kabisa kwa business hii!');
          Alert.alert(
            'Taarifa ya Debug',
            `Business: ${data.user.business_name}\n` +
            `Status: ${data.user.status}\n` +
            `Jumla ya Expenses: ${data.all_expenses_count}\n` +
            `Leo: ${data.today_expenses_count} (${data.today_expenses_total} TZS)\n\n` +
            `Kama unaona 0 lakini unajua kuna expenses, angalia:\n` +
            `1. Business name inalingana?\n` +
            `2. User ameapproved?`
          );
        } else {
          console.log(`✅ Business ina expenses ${data.all_expenses_count} kwa jumla`);
          console.log(`✅ Leo ina expenses ${data.today_expenses_count} zenye thamani ${data.today_expenses_total}`);
        }
      } else {
        console.log('❌ Debug endpoint failed:', response.status);
      }
    } catch (error) {
      console.error('❌ Debug error:', error);
      const cachedDebug = await getCache<any>('d:debug:check-business');
      if (cachedDebug) { setDebugData(cachedDebug); }
    }
  };

  const loadUserData = async () => {
    try {
      const userDataStr = await AsyncStorage.getItem('userData');
      const token = await AsyncStorage.getItem('userToken');
      
      console.log('📱 AsyncStorage - userData exists:', !!userDataStr);
      console.log('📱 AsyncStorage - token exists:', !!token);
      
      if (!userDataStr || !token) {
        Alert.alert(t('app.error'), t('admin_dashboard.error_auth'));
        return;
      }

      const user = JSON.parse(userDataStr);
      console.log('👤 Parsed user:', user);
      
      const businessName = user.businessName || user.business_name;
      console.log('🏢 Business Name from storage:', businessName);
      
      if (!businessName) {
        Alert.alert(t('app.error'), t('admin_dashboard.error_network'));
        return;
      }

      setUserData({
        businessName: businessName,
        businessLocation: user.businessLocation || user.business_location || t('preview.default_location'),
        userId: user.id || user.userId || '',
        userRole: user.role || ''
      });

      // ✅ Run debug check first
      await checkBusinessData(token);

      // Load all data for the business
      await loadBusinessData(businessName, token);
      
    } catch (error) {
      console.error('Error loading user data:', error);
      Alert.alert(t('app.error'), t('admin_dashboard.error_update'));
    }
  };

  // ✅ FIXED: Load daily expenses and RETURN the data with proper debugging
  const loadDailyExpenses = async (dates: string[], token: string) => {
    try {
      setExpensesLoading(true);
      const expensesByDate: {[key: string]: Expense[]} = {};
      
      console.log('🔍 Inatafuta matumizi kwa siku:', dates);
      
      for (const date of dates) {
        try {
          console.log(`📅 Inatafuta matumizi ya tarehe: ${date}`);

          const cachedDate = await getCache<any[]>(`d:office-expenses:range:${date}`);
          if (cachedDate) { expensesByDate[date] = cachedDate; continue; }

          const response = await fetchWithTimeout(
            `${API_BASE_URL}/api/office-expenses/range?start_date=${date}&end_date=${date}`,
            {
              headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json',
              }
            },
            20000
          );

          console.log(`📊 Status code kwa ${date}:`, response.status);

          if (response.ok) {
            const data = await response.json();
            console.log(`📦 Response kwa ${date}:`, {
              success: data.success,
              count: data.count,
              total: data.total,
              expensesCount: data.expenses?.length
            });
            
            if (data.success && data.expenses) {
              console.log(`💰 Expenses raw kwa ${date}:`, JSON.stringify(data.expenses, null, 2));
              
              // Filter to ensure expenses match the date
              const validExpenses = data.expenses.filter((exp: Expense) => {
                const expDate = exp.expense_date.split('T')[0];
                return expDate === date;
              });
              
              expensesByDate[date] = validExpenses;
              setCache(`d:office-expenses:range:${date}`, validExpenses).catch(() => {});
              console.log(`✅ Matumizi ${validExpenses.length} yamepatikana kwa tarehe ${date}, jumla: ${validExpenses.reduce((sum, e) => sum + e.amount, 0)}`);
            } else {
              console.log(`ℹ️ Hakuna matumizi kwa tarehe ${date}`);
              expensesByDate[date] = [];
            }
          } else {
            console.warn(`⚠️ Failed to fetch expenses for ${date}: ${response.status}`);
            expensesByDate[date] = [];
          }
        } catch (error) {
          console.error(`❌ Error loading expenses for ${date}:`, error);
          const cachedDate = await getCache<any[]>(`d:office-expenses:range:${date}`);
          if (cachedDate) { expensesByDate[date] = cachedDate; } else { expensesByDate[date] = []; }
        }
      }
      
      setDailyExpenses(expensesByDate);
      console.log('💰 Matumizi ya kila siku yamepakuliwa:', Object.keys(expensesByDate).length);
      
      // Log summary
      Object.keys(expensesByDate).forEach(date => {
        const total = expensesByDate[date].reduce((sum, e) => sum + e.amount, 0);
        console.log(`📊 ${date}: ${expensesByDate[date].length} expenses, total=${total}`);
      });
      
      // ✅ CRITICAL: Log the final data being returned
      console.log('🔥 FINAL expensesData before return:', JSON.stringify(expensesByDate, null, 2));
      
      // ✅ RETURN the data for immediate use
      return expensesByDate;
      
    } catch (error) {
      console.error('Error loading daily expenses:', error);
      return {};
    } finally {
      setExpensesLoading(false);
    }
  };

  const loadBusinessData = async (businessName: string, token: string) => {
    try {
      setLoading(true);
      console.log('🏢 Inapakua data ya biashara:', businessName);

      // ✅ 1. PATA WAUZAJI WOTE WA BIASHARA HII
      console.log('🔍 Inatafuta wauzaji wa biashara:', businessName);
      
      let allSellers: Seller[] = [];
      
      try {
        const cachedSellers = await getCache<any[]>('admin:users');
        if (cachedSellers) {
          allSellers = cachedSellers.filter((user: any) => 
            (user.role === 'seller' || user.role === 'admin') && 
            user.business_name === businessName &&
            user.status === 'approved'
          );
        }

        const sellersResponse = await fetchWithTimeout(
          `${API_BASE_URL}/api/admin/users?business=${encodeURIComponent(businessName)}`,
          {
            method: 'GET',
            headers: {
              'Authorization': `Bearer ${token}`,
              'Content-Type': 'application/json',
            },
          },
          20000
        );

        if (sellersResponse.ok) {
          const responseData = await sellersResponse.json();
          
          if (responseData && Array.isArray(responseData)) {
            allSellers = responseData.filter((user: any) => 
              (user.role === 'seller' || user.role === 'admin') && 
              user.business_name === businessName &&
              user.status === 'approved'
            );
            setCache('admin:users', allSellers).catch(() => {});
          } else if (responseData && responseData.users && Array.isArray(responseData.users)) {
            allSellers = responseData.users.filter((user: any) => 
              (user.role === 'seller' || user.role === 'admin') && 
              user.business_name === businessName &&
              user.status === 'approved'
            );
            setCache('admin:users', allSellers).catch(() => {});
          }
        }
      } catch (error) {
        console.error('Error fetching sellers:', error);
        const cachedSellers = await getCache<any[]>('admin:users');
        if (cachedSellers) {
          allSellers = cachedSellers.filter((user: any) => 
            (user.role === 'seller' || user.role === 'admin') && 
            user.business_name === businessName &&
            user.status === 'approved'
          );
        }
      }

      // If no sellers found, use current user
      if (allSellers.length === 0) {
        console.log('⚠️ Hakuna wauzaji walipatikana, tumia mtumiaji wa sasa');
        const currentUserId = userData.userId || '';
        allSellers = [{
          id: currentUserId as any,
          email: userData.userId ? `user_${currentUserId}@example.com` : '',
          full_name: userData.businessName,
          business_name: businessName,
          role: userData.userRole || 'seller',
          status: 'approved'
        }];
      }
      
      console.log('👥 Wauzaji wa biashara walipatikana:', allSellers.length);
      setSellers(allSellers);

      // ✅ 2. PATA BIDHAA ZOTE ZA BIASHARA HII
      console.log('📦 Inapata bidhaa za biashara nzima...');
      let allProducts: Product[] = [];
      
      try {
        const cachedProducts = await getCache<any[]>('admin:products');
        if (cachedProducts) {
          allProducts = cachedProducts.filter((product: any) => {
            const productSeller = allSellers.find(s => s.id === product.seller_id);
            return productSeller && productSeller.business_name === businessName;
          });
        }

        const productsResponse = await fetchWithTimeout(`${API_BASE_URL}/api/admin/products`, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
          },
        }, 20000);

        if (productsResponse.ok) {
          const productsData = await productsResponse.json();
          let productsArray: any[] = [];
          
          if (Array.isArray(productsData)) {
            productsArray = productsData;
          } else if (productsData && Array.isArray(productsData.products)) {
            productsArray = productsData.products;
          }
          
          setCache('admin:products', productsArray).catch(() => {});

          allProducts = productsArray.filter((product: any) => {
            const productSeller = allSellers.find(s => s.id === product.seller_id);
            return productSeller && productSeller.business_name === businessName;
          });
          console.log('📦 Bidhaa za biashara:', allProducts.length);
        }
      } catch (error) {
        console.error('Error fetching products:', error);
        const cachedProducts = await getCache<any[]>('admin:products');
        if (cachedProducts) {
          allProducts = cachedProducts.filter((product: any) => {
            const productSeller = allSellers.find(s => s.id === product.seller_id);
            return productSeller && productSeller.business_name === businessName;
          });
        }
      }

      setProducts(allProducts);

      // ✅ 3. PATA MAUZO YOTE YA BIASHARA HII
      console.log('💰 Inapata mauzo ya biashara nzima...');
      let allSales: Sale[] = [];

      const cachedSales = await getCache<Sale[]>('d:preview:sales');
      if (cachedSales) { allSales = cachedSales; }
      
      try {
        const salesResponse = await fetchWithTimeout(`${API_BASE_URL}/api/admin/sales`, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
          },
        }, 20000);

        if (salesResponse.ok) {
          const salesData = await salesResponse.json();
          let salesArray: any[] = [];
          
          if (Array.isArray(salesData)) {
            salesArray = salesData;
          } else if (salesData && Array.isArray(salesData.sales)) {
            salesArray = salesData.sales;
          }
          
          allSales = [];
          salesArray.forEach((sale: any) => {
            const saleSeller = allSellers.find(s => s.id === sale.seller_id);
            if (saleSeller && saleSeller.business_name === businessName) {
              let customerName = t('preview.default_customer');
              if (sale.customers && sale.customers.name) {
                customerName = sale.customers.name;
              }
              
              if (sale.sale_items && sale.sale_items.length > 0) {
                sale.sale_items.forEach((item: any) => {
                  const product = allProducts.find(p => p.id === item.product_id);
                  const costPrice = product?.cost_price || product?.price || 0;
                  
                  const unitPrice = item.unit_price || 0;
                  const quantity = item.quantity || 1;
                  const totalAmount = item.total_price || unitPrice * quantity;
                  
                  allSales.push({
                    id: sale.id,
                    product_id: item.product_id || 0,
                    product_name: product?.name || item.products?.name || t('preview.default_product'),
                    quantity: quantity,
                    unit_price: unitPrice,
                    total_amount: totalAmount,
                    sale_date: sale.sale_date || new Date().toISOString().split('T')[0],
                    customer_name: customerName,
                    seller_name: saleSeller.full_name || saleSeller.email,
                    business_name: businessName,
                    user_id: sale.seller_id,
                    invoice_number: sale.invoice_number,
                    cost_price: costPrice
                  });
                });
              } else {
                let productName = t('preview.default_product');
                let productId = 0;
                let quantity = 1;
                let unitPrice = sale.total_amount || 0;
                
                const product = allProducts.find(p => p.seller_id === sale.seller_id);
                const costPrice = product?.cost_price || product?.price || 0;
                
                if (product) {
                  productName = product.name;
                  productId = product.id;
                  unitPrice = product.price || unitPrice;
                }

                allSales.push({
                  id: sale.id,
                  product_id: productId,
                  product_name: productName,
                  quantity: quantity,
                  unit_price: unitPrice,
                  total_amount: sale.total_amount || 0,
                  sale_date: sale.sale_date || new Date().toISOString().split('T')[0],
                  customer_name: customerName,
                  seller_name: saleSeller.full_name || saleSeller.email,
                  business_name: businessName,
                  user_id: sale.seller_id,
                  invoice_number: sale.invoice_number,
                  cost_price: costPrice
                });
              }
            }
          });
          setCache('d:preview:sales', allSales).catch(() => {});
          console.log('💰 Mauzo ya biashara:', allSales.length);
        }
      } catch (error) {
        console.error('Error fetching sales:', error);
      }

      // ✅ 4. ANDAA DATA KWA KILA SIKU - WITH EXPENSES
      if (allSales.length > 0) {
        // Get unique dates from sales
        const uniqueDates = [...new Set(allSales.map(sale => 
          sale.sale_date.split('T')[0]
        ))];
        
        console.log('📅 Unique dates from sales:', uniqueDates);
        
        // ✅ Wait for expenses and use the returned data
        const expensesData = await loadDailyExpenses(uniqueDates, token);
        
        // ✅ CRITICAL: Log the data received
        console.log('🔥 expensesData kutoka loadDailyExpenses:', JSON.stringify(expensesData, null, 2));
        console.log('🔥 expensesData["2026-03-01"]:', expensesData['2026-03-01']);
        
        // ✅ Process with the actual data
        processDailyData(allSales, allProducts, allSellers, expensesData);
        
        const totalSalesAmount = allSales.reduce((sum, sale) => sum + sale.total_amount, 0);
        
        // Calculate total gross profit
        const totalGrossProfit = allSales.reduce((sum, sale) => {
          const costPrice = sale.cost_price || 0;
          const sellingPrice = sale.unit_price || 0;
          const quantity = sale.quantity || 0;
          const profitPerUnit = sellingPrice - costPrice;
          const itemProfit = profitPerUnit * quantity;
          return sum + Math.max(0, itemProfit);
        }, 0);
        
        // ✅ Calculate total expenses using expensesData, not state
        let totalExpensesAllDays = 0;
        Object.values(expensesData).forEach(dayExpenses => {
          dayExpenses.forEach(exp => {
            totalExpensesAllDays += exp.amount;
          });
        });
        
        // Calculate total net profit
        const totalNetProfit = totalGrossProfit - totalExpensesAllDays;
        
        setBusinessData({
          totalSellers: allSellers.length,
          totalProducts: allProducts.length,
          totalSalesAmount: totalSalesAmount,
          totalProfit: totalGrossProfit,
          totalNetProfit: totalNetProfit
        });
        
        // ✅ 5. PATA MATUKIO (EVENTS) YA BIASHARA
        await loadBusinessEvents(businessName, token, allSales, allProducts, allSellers);
        
      } else {
        console.log('⚠️ Hakuna mauzo ya biashara yaliyopatikana');
        setDailySummaries([]);
        
        setBusinessData({
          totalSellers: allSellers.length,
          totalProducts: allProducts.length,
          totalSalesAmount: 0,
          totalProfit: 0,
          totalNetProfit: 0
        });
        
        await loadBusinessEvents(businessName, token, [], allProducts, allSellers);
      }

    } catch (error) {
      console.error('Hitilafu ya kupakua data ya biashara:', error);
      Alert.alert(t('app.error'), t('admin_dashboard.error_sellers'));
    } finally {
      setLoading(false);
    }
  };

  // ✅ FIXED: processDailyData with better debugging
  const processDailyData = (sales: Sale[], products: Product[], sellers: Seller[], expensesByDate: {[key: string]: Expense[]} = {}) => {
    if (sales.length === 0) {
      console.log('⚠️ Hakuna mauzo ya kuandaa');
      setDailySummaries([]);
      return;
    }

    console.log('💰 processDailyData received expenses for dates:', Object.keys(expensesByDate));
    console.log('💰 processDailyData PARAMETER expensesByDate:', JSON.stringify(expensesByDate, null, 2));
    console.log('💰 expensesByDate["2026-03-01"]:', expensesByDate['2026-03-01']);

    const salesByDate: { [key: string]: Sale[] } = {};
    
    sales.forEach(sale => {
      let date = '';
      try {
        if (sale.sale_date) {
          date = sale.sale_date.split('T')[0];
        } else {
          date = new Date().toISOString().split('T')[0];
        }
      } catch (error) {
        date = new Date().toISOString().split('T')[0];
      }
      
      if (!salesByDate[date]) {
        salesByDate[date] = [];
      }
      salesByDate[date].push(sale);
    });

    console.log('📅 Tarehe zilizopatikana:', Object.keys(salesByDate).length);

    const summaries: DailySummary[] = Object.keys(salesByDate).map(date => {
      const daySales = salesByDate[date];
      const dayExpenses = expensesByDate[date] || [];
      
      // ✅ DEBUG: Angalia kama expenses zimepita
      if (date === '2026-03-01') {
        console.log('🔍 DEBUG - 2026-03-01 expenses from parameter:', dayExpenses);
        console.log('🔍 DEBUG - 2026-03-01 expenses count:', dayExpenses.length);
        console.log('🔍 DEBUG - 2026-03-01 expenses total:', dayExpenses.reduce((sum, e) => sum + e.amount, 0));
      }
      
      const totalSales = daySales.reduce((sum, sale) => {
        return sum + (Number(sale.total_amount) || 0);
      }, 0);

      const totalProducts = daySales.reduce((sum, sale) => {
        return sum + (Number(sale.quantity) || 0);
      }, 0);

      // GROSS PROFIT
      const totalProfit = daySales.reduce((sum, sale) => {
        try {
          const costPrice = sale.cost_price || 0;
          const sellingPrice = Number(sale.unit_price) || 0;
          const quantity = Number(sale.quantity) || 0;
          
          if (isNaN(costPrice) || isNaN(sellingPrice) || isNaN(quantity)) {
            return sum;
          }

          const profitPerUnit = sellingPrice - costPrice;
          const totalItemProfit = profitPerUnit * quantity;
          const finalProfit = Math.max(0, totalItemProfit);
          
          return sum + finalProfit;
        } catch (error) {
          return sum;
        }
      }, 0);

      // ✅ TOTAL EXPENSES - Ensure it's calculated properly
      const totalExpenses = dayExpenses.reduce((sum, exp) => sum + (exp.amount || 0), 0);

      // NET PROFIT
      const netProfit = totalProfit - totalExpenses;

      const customers = [...new Set(daySales.map(sale => sale.customer_name))];
      
      const daySellers = sellers.filter(seller => 
        daySales.some(sale => sale.user_id === seller.id)
      );

      console.log(`📈 Siku: ${date}, Mauzo: ${totalSales}, Faida Ghafi: ${totalProfit}, Matumizi: ${totalExpenses}, Faida Halisi: ${netProfit}`);

      return {
        date,
        totalSales,
        totalProducts,
        totalProfit,
        totalExpenses,
        netProfit,
        sales: daySales,
        customers,
        sellers: daySellers,
        expenses: dayExpenses
      };
    });

    summaries.sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime());
    
    setDailySummaries(summaries);
    console.log('✅ Muhtasari wa siku ulioandaliwa:', summaries.length);
    
    // ✅ DEBUG: Angalia muhtasari wa 2026-03-01
    const march1Summary = summaries.find(s => s.date === '2026-03-01');
    if (march1Summary) {
      console.log('🔍 FINAL SUMMARY for 2026-03-01:', {
        date: march1Summary.date,
        totalExpenses: march1Summary.totalExpenses,
        expensesCount: march1Summary.expenses.length,
        expenses: march1Summary.expenses
      });
    }
  };

  const loadBusinessEvents = async (
    businessName: string, 
    token: string, 
    sales: Sale[], 
    products: Product[], 
    sellers: Seller[]
  ) => {
    try {
      setEventsLoading(true);
      const events: BusinessEvent[] = [];
      
      // 1. MATUKIO YA MAUZO
      const recentSales = sales.slice(0, 20);
      recentSales.forEach(sale => {
        events.push({
          id: sale.id,
          type: 'sale',
          title: t('preview.event_sale'),
          description: t('preview.event_sale_desc', {qty: String(sale.quantity), product: sale.product_name, customer: sale.customer_name}),
          amount: sale.total_amount,
          seller_name: sale.seller_name,
          customer_name: sale.customer_name,
          product_name: sale.product_name,
          event_date: sale.sale_date,
          business_name: businessName,
          metadata: {
            invoice_number: sale.invoice_number,
            quantity: sale.quantity,
            unit_price: sale.unit_price
          }
        });
      });
      
      // 2. MATUKIO YA BIDHAA MPYA
      const recentProducts = products.slice(0, 10);
      recentProducts.forEach(product => {
        const seller = sellers.find(s => s.id === product.seller_id);
        events.push({
          id: product.id + 1000,
          type: 'product_added',
          title: t('preview.event_product_added'),
          description: t('preview.event_product_added_desc', {product: product.name}),
          amount: product.price,
          seller_name: seller?.full_name || seller?.email || t('preview.default_seller_fallback'),
          product_name: product.name,
          event_date: product.created_at || new Date().toISOString().split('T')[0],
          business_name: businessName,
          metadata: {
            stock: product.stock,
            category: product.category
          }
        });
      });
      
      // 3. MATUKIO YA STOCK CHINI
      const lowStockProducts = products.filter(p => p.stock < 5);
      lowStockProducts.forEach(product => {
        const seller = sellers.find(s => s.id === product.seller_id);
        events.push({
          id: product.id + 2000,
          type: 'low_stock',
          title: t('preview.event_low_stock'),
          description: t('preview.event_low_stock_desc', {product: product.name, stock: String(product.stock)}),
          seller_name: seller?.full_name || seller?.email || t('preview.default_seller_fallback'),
          product_name: product.name,
          event_date: new Date().toISOString().split('T')[0],
          business_name: businessName,
          metadata: {
            stock: product.stock,
            threshold: 5
          }
        });
      });
      
      // 4. MATUKIO YA WAUZAJI WAPYA
      const recentSellers = sellers.slice(0, 5);
      recentSellers.forEach(seller => {
        events.push({
          id: seller.id + 3000,
          type: 'seller_joined',
          title: t('preview.event_seller_joined'),
          description: t('preview.event_seller_joined_desc', {name: seller.full_name || seller.email}),
          seller_name: seller.full_name || seller.email,
          event_date: new Date().toISOString().split('T')[0],
          business_name: businessName,
          metadata: {
            role: seller.role,
            email: seller.email
          }
        });
      });
      
      events.sort((a, b) => new Date(b.event_date).getTime() - new Date(a.event_date).getTime());
      
      setBusinessEvents(events);
      console.log('📢 Matukio yamepakuliwa:', events.length);
      
    } catch (error) {
      console.error('Hitilafu ya kupakua matukio:', error);
    } finally {
      setEventsLoading(false);
    }
  };

  const handleRefresh = async () => {
    setRefreshing(true);
    await loadUserData();
    setRefreshing(false);
  };

  const handleDayPress = (day: DailySummary) => {
    console.log('📱 Kubonyeza siku:', day.date, 'na expenses:', day.expenses?.length || 0);
    setSelectedDay(day);
    setModalVisible(true);
  };

  const formatCurrency = (amount: number | undefined | null) => {
    if (amount === undefined || amount === null) return 'TSh 0';
    if (isNaN(amount)) return 'TSh 0';
    if (amount === 0) return 'TSh 0';
    const formatted = new Intl.NumberFormat('en-TZ', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(amount);
    return `TSh ${formatted}`;
  };

  const formatDate = (dateString: string) => {
    try {
      const dateObj = new Date(dateString + 'T00:00:00');
      const localeMap: Record<string, string> = { sw: 'sw-TZ', en: 'en-US', fr: 'fr-FR', hi: 'hi-IN', ur: 'ur-PK', es: 'es-ES', de: 'de-DE', zh: 'zh-CN' };
      return dateObj.toLocaleDateString(localeMap[lang] || 'sw-TZ', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
      });
    } catch (error) {
      return dateString;
    }
  };

  const getShortDate = (dateString: string) => {
    try {
      const dateObj = new Date(dateString + 'T00:00:00');
      const localeMap: Record<string, string> = { sw: 'sw-TZ', en: 'en-US', fr: 'fr-FR', hi: 'hi-IN', ur: 'ur-PK', es: 'es-ES', de: 'de-DE', zh: 'zh-CN' };
      return dateObj.toLocaleDateString(localeMap[lang] || 'sw-TZ', {
        weekday: 'short',
        month: 'short',
        day: 'numeric'
      });
    } catch (error) {
      return dateString;
    }
  };

  const getEventIcon = (type: BusinessEvent['type']) => {
    switch (type) {
      case 'sale':
        return { name: 'cash', color: '#27ae60' };
      case 'product_added':
        return { name: 'cube', color: '#3498db' };
      case 'product_updated':
        return { name: 'refresh', color: '#f39c12' };
      case 'seller_joined':
        return { name: 'person-add', color: '#9b59b6' };
      case 'low_stock':
        return { name: 'warning', color: '#e74c3c' };
      case 'payment':
        return { name: 'card', color: '#2ecc71' };
      case 'announcement':
        return { name: 'megaphone', color: '#e67e22' };
      default:
        return { name: 'information-circle', color: '#95a5a6' };
    }
  };

  const getEventTitle = (type: BusinessEvent['type']) => {
    switch (type) {
      case 'sale':
        return t('preview.event_sale');
      case 'product_added':
        return t('preview.event_product_added');
      case 'product_updated':
        return t('preview.event_updated');
      case 'seller_joined':
        return t('preview.event_seller_joined');
      case 'low_stock':
        return t('preview.event_low_stock');
      case 'payment':
        return t('preview.event_payment');
      case 'announcement':
        return t('preview.event_announcement');
      default:
        return t('preview.event_default');
    }
  };

  const renderDaysTab = () => (
    <View style={styles.daysContainer}>
      <Text style={styles.sectionTitle}>{t('preview.section_daily')}</Text>
      
      {dailySummaries.length > 0 ? (
        dailySummaries.map((day, index) => (
          <TouchableOpacity 
            key={day.date + index} 
            style={styles.dayCard}
            onPress={() => handleDayPress(day)}
            activeOpacity={0.7}
          >
            {/* Date Header */}
            <View style={styles.dateHeader}>
              <View style={styles.dateBadge}>
                <Ionicons name="calendar" size={16} color="white" />
                <Text style={styles.dateBadgeText}>{getShortDate(day.date)}</Text>
              </View>
              <Text style={styles.dayStats}>
                {day.sales.length} {t('preview.summary_mauzo')} • {day.sellers.length} {t('preview.summary_wauzaji')}
              </Text>
            </View>

            {/* Summary Grid */}
            <View style={styles.summaryGrid}>
              <View style={styles.summaryBox}>
                <Ionicons name="cube" size={20} color="#2ecc71" />
                <Text style={styles.summaryNumber}>{day.totalProducts}</Text>
                <Text style={styles.summaryLabel}>{t('preview.summary_products')}</Text>
              </View>
              
              <View style={styles.summaryBox}>
                <Ionicons name="cash" size={20} color="#f39c12" />
                <Text style={styles.summaryNumber}>{formatCurrency(day.totalSales)}</Text>
                <Text style={styles.summaryLabel}>{t('preview.summary_sales')}</Text>
              </View>
              
              <View style={styles.summaryBox}>
                <Ionicons name="trending-up" size={20} color="#e74c3c" />
                <Text style={styles.summaryNumber}>{formatCurrency(day.totalProfit)}</Text>
                <Text style={styles.summaryLabel}>{t('preview.summary_gross')}</Text>
              </View>
            </View>

            {/* Net Profit Row */}
            <View style={styles.netProfitRow}>
              <View style={styles.netProfitBox}>
                <Ionicons name="calculator" size={16} color="#9b59b6" />
                <Text style={styles.netProfitLabel}>{t('preview.summary_net')}</Text>
                <Text style={[
                  styles.netProfitValue,
                  { color: day.netProfit >= 0 ? '#27ae60' : '#e74c3c' }
                ]}>
                  {formatCurrency(day.netProfit)}
                </Text>
              </View>
              
              {/* Show expenses if any */}
              {day.expenses && day.expenses.length > 0 && (
                <View style={styles.expensesBadge}>
                  <Ionicons name="receipt" size={12} color="#e67e22" />
                  <Text style={styles.expensesBadgeText}>
                    {t('preview.summary_expenses', { amount: formatCurrency(day.totalExpenses) })}
                  </Text>
                </View>
              )}
            </View>

            {/* Customers */}
            <View style={styles.customersSection}>
              <View style={styles.sectionLabel}>
                <Ionicons name="people" size={14} color="#3498db" />
                <Text style={styles.sectionLabelText}>{t('preview.customers_title', { n: day.customers.length })}</Text>
              </View>
              <Text style={styles.customersText} numberOfLines={1}>
                {day.customers.join(', ')}
              </Text>
            </View>

            {/* Sellers */}
            <View style={styles.sellersSection}>
              <View style={styles.sectionLabel}>
                <Ionicons name="person" size={14} color="#9b59b6" />
                <Text style={styles.sectionLabelText}>{t('preview.sellers_title', { n: day.sellers.length })}</Text>
              </View>
              <Text style={styles.sellersText} numberOfLines={1}>
                {day.sellers.map(s => s.full_name || s.email).join(', ')}
              </Text>
            </View>

            {/* More Info */}
            <View style={styles.moreInfo}>
              <Text style={styles.moreInfoText}>{t('preview.more_info')}</Text>
              <Ionicons name="chevron-forward" size={16} color="#3498db" />
            </View>
          </TouchableOpacity>
        ))
      ) : (
        <View style={styles.noData}>
          <Ionicons name="business-outline" size={64} color="#bdc3c7" />
          <Text style={styles.noDataText}>{t('preview.no_sales')}</Text>
          <Text style={styles.noDataSubtext}>
            {t('preview.no_sales_sub', { name: userData.businessName })}
          </Text>
          <TouchableOpacity onPress={handleRefresh} style={styles.tryAgainButton}>
            <Text style={styles.tryAgainText}>{t('preview.try_again')}</Text>
          </TouchableOpacity>
        </View>
      )}
    </View>
  );

  const renderEventsTab = () => (
    <View style={styles.eventsContainer}>
      <View style={styles.eventsHeader}>
        <Ionicons name="notifications" size={24} color="#3498db" />
        <Text style={styles.eventsTitle}>{t('preview.events_title')}</Text>
        <Text style={styles.eventsCount}>({businessEvents.length})</Text>
      </View>
      
      {eventsLoading ? (
        <View style={styles.eventsLoading}>
          <ActivityIndicator size="large" color="#3498db" />
          <Text style={styles.eventsLoadingText}>{t('preview.events_loading')}</Text>
        </View>
      ) : businessEvents.length > 0 ? (
        <ScrollView style={styles.eventsList}>
          {businessEvents.map((event, index) => {
            const icon = getEventIcon(event.type);
            return (
              <View key={`${event.id}-${index}`} style={styles.eventCard}>
                <View style={styles.eventHeader}>
                  <View style={[styles.eventIconContainer, { backgroundColor: icon.color + '20' }]}>
                    <Ionicons name={icon.name as any} size={20} color={icon.color} />
                  </View>
                  <View style={styles.eventTitleContainer}>
                    <Text style={styles.eventType}>{getEventTitle(event.type)}</Text>
                    <Text style={styles.eventDate}>
                      {(function() {
                        const localeMap: Record<string, string> = { sw: 'sw-TZ', en: 'en-US', fr: 'fr-FR', hi: 'hi-IN', ur: 'ur-PK', es: 'es-ES', de: 'de-DE', zh: 'zh-CN' };
                        return new Date(event.event_date).toLocaleDateString(localeMap[lang] || 'sw-TZ', {
                          day: 'numeric',
                          month: 'short',
                          year: 'numeric'
                        });
                      })()}
                    </Text>
                  </View>
                  {event.amount && (
                    <Text style={styles.eventAmount}>{formatCurrency(event.amount)}</Text>
                  )}
                </View>
                
                <Text style={styles.eventDescription}>{event.description}</Text>
                
                {(event.seller_name || event.customer_name || event.product_name) && (
                  <View style={styles.eventDetails}>
                    {event.seller_name && (
                      <View style={styles.eventDetail}>
                        <Ionicons name="person" size={14} color="#7f8c8d" />
                        <Text style={styles.eventDetailText}>{event.seller_name}</Text>
                      </View>
                    )}
                    {event.customer_name && (
                      <View style={styles.eventDetail}>
                        <Ionicons name="people" size={14} color="#3498db" />
                        <Text style={styles.eventDetailText}>{event.customer_name}</Text>
                      </View>
                    )}
                    {event.product_name && (
                      <View style={styles.eventDetail}>
                        <Ionicons name="cube" size={14} color="#2ecc71" />
                        <Text style={styles.eventDetailText}>{event.product_name}</Text>
                      </View>
                    )}
                  </View>
                )}
              </View>
            );
          })}
        </ScrollView>
      ) : (
        <View style={styles.noEvents}>
          <Ionicons name="notifications-off-outline" size={64} color="#bdc3c7" />
          <Text style={styles.noEventsText}>{t('preview.no_events')}</Text>
          <Text style={styles.noEventsSubtext}>
            {t('preview.no_events_sub')}
          </Text>
        </View>
      )}
    </View>
  );

  const renderDebugPanel = () => {
    if (!debugData) return null;
    
    return (
      <TouchableOpacity 
        style={styles.debugPanel}
        onPress={() => setShowDebug(!showDebug)}
      >
        <View style={styles.debugHeader}>
          <Ionicons name="bug" size={20} color="#f39c12" />
          <Text style={styles.debugTitle}>{t('preview.debug_info')}</Text>
          <Ionicons name={showDebug ? 'chevron-up' : 'chevron-down'} size={20} color="#7f8c8d" />
        </View>
        
        {showDebug && (
          <View style={styles.debugContent}>
            <Text style={styles.debugText}>Business: {debugData.user.business_name}</Text>
            <Text style={styles.debugText}>Status: {debugData.user.status}</Text>
            <Text style={styles.debugText}>Role: {debugData.user.role}</Text>
            <View style={styles.debugDivider} />
            <Text style={styles.debugText}>Total Expenses: {debugData.all_expenses_count}</Text>
            <Text style={styles.debugText}>Today's Expenses: {debugData.today_expenses_count}</Text>
            <Text style={styles.debugText}>Today's Total: {formatCurrency(debugData.today_expenses_total)}</Text>
            
            {debugData.sample_expenses.length > 0 && (
              <>
                <Text style={styles.debugSubtitle}>Sample Expenses:</Text>
                {debugData.sample_expenses.map((exp, idx) => (
                  <Text key={idx} style={styles.debugSample}>
                    • {exp.expense_date}: {exp.category} - {formatCurrency(exp.amount)} ({exp.description})
                  </Text>
                ))}
              </>
            )}
          </View>
        )}
      </TouchableOpacity>
    );
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.screenSafe} edges={['top']}>
        <View style={styles.center}>
          <ActivityIndicator size="large" color="#3498db" />
          <Text style={styles.loadingText}>{t('preview.loading')}</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.screenSafe} edges={['top']}>
    <ScrollView 
      style={styles.container}
      refreshControl={
        <RefreshControl
          refreshing={refreshing}
          onRefresh={handleRefresh}
          colors={['#3498db']}
          tintColor="#3498db"
        />
      }
    >
      {/* Header */}
      <View style={styles.header}>
        <View style={styles.headerContent}>
          <Text style={styles.title}>{t('preview.title')}</Text>
          <Text style={styles.businessName}>{userData.businessName}</Text>
          <Text style={styles.businessLocation}>{userData.businessLocation}</Text>
          
          <View style={styles.businessStats}>
            <View style={styles.statItem}>
              <Ionicons name="people" size={14} color="#3498db" />
              <Text style={styles.statText}>{businessData.totalSellers} {t('preview.stats_sellers')}</Text>
            </View>
            <View style={styles.statItem}>
              <Ionicons name="cube" size={14} color="#2ecc71" />
              <Text style={styles.statText}>{businessData.totalProducts} {t('preview.stats_products')}</Text>
            </View>
            <View style={styles.statItem}>
              <Ionicons name="cash" size={14} color="#f39c12" />
              <Text style={styles.statText}>{formatCurrency(businessData.totalSalesAmount)} {t('preview.stats_sales_ammount')}</Text>
            </View>
            <View style={styles.statItem}>
              <Ionicons name="trending-up" size={14} color="#e74c3c" />
              <Text style={styles.statText}>{formatCurrency(businessData.totalProfit)} {t('preview.stats_gross_profit')}</Text>
            </View>
            <View style={styles.statItem}>
              <Ionicons name="calculator" size={14} color="#9b59b6" />
              <Text style={styles.statText}>{formatCurrency(businessData.totalNetProfit)} {t('preview.stats_net_profit')}</Text>
            </View>
          </View>
        </View>
        <View style={styles.headerActions}>
          <TouchableOpacity onPress={handleRefresh} style={styles.refreshButton}>
            <Ionicons name="refresh" size={24} color="#3498db" />
          </TouchableOpacity>
          <LogoutButton iconOnly />
        </View>
      </View>

      {/* Business Info */}
      <View style={styles.businessInfo}>
        <Text style={styles.infoTitle}>{t('preview.business_review')}</Text>
        <Text style={styles.infoText}>
          {t('preview.info_text', { name: userData.businessName, sellers: sellers.length > 1 ? t('preview.info_sellers', { n: sellers.length }) : '' })}
          {' '}{t('preview.info_note')}
        </Text>
      </View>

      {/* Debug Panel */}
      {renderDebugPanel()}

      {/* Tabs */}
      <View style={styles.tabsContainer}>
        <View style={styles.tabsHeader}>
          <TouchableOpacity 
            style={[styles.tabButton, activeTab === 'days' && styles.activeTabButton]}
            onPress={() => setActiveTab('days')}
          >
            <Ionicons 
              name="calendar" 
              size={20} 
              color={activeTab === 'days' ? '#3498db' : '#7f8c8d'} 
            />
            <Text style={[
              styles.tabButtonText, 
              activeTab === 'days' && styles.activeTabButtonText
            ]}>
              {t('preview.tab_days')}
            </Text>
          </TouchableOpacity>
          
          <TouchableOpacity 
            style={[styles.tabButton, activeTab === 'events' && styles.activeTabButton]}
            onPress={() => setActiveTab('events')}
          >
            <Ionicons 
              name="notifications" 
              size={20} 
              color={activeTab === 'events' ? '#3498db' : '#7f8c8d'} 
            />
            <Text style={[
              styles.tabButtonText, 
              activeTab === 'events' && styles.activeTabButtonText
            ]}>
              {t('preview.tab_events')} ({businessEvents.length})
            </Text>
          </TouchableOpacity>
        </View>
        
        <View style={styles.tabContent}>
          {activeTab === 'days' ? renderDaysTab() : renderEventsTab()}
        </View>
      </View>

      {/* Modal - Daily Summary */}
      <Modal
        animationType="slide"
        transparent={true}
        visible={modalVisible}
        onRequestClose={() => setModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            {selectedDay && (
              <>
                {/* Modal Header */}
                <View style={styles.modalHeader}>
                  <View style={styles.modalTitleContainer}>
                    <Ionicons name="calendar" size={24} color="#3498db" />
                    <View>
                      <Text style={styles.modalTitle}>{formatDate(selectedDay.date)}</Text>
                      <Text style={styles.modalSubtitle}>{userData.businessName}</Text>
                    </View>
                  </View>
                  <TouchableOpacity 
                    onPress={() => setModalVisible(false)}
                    style={styles.closeButton}
                  >
                    <Ionicons name="close" size={24} color="#7f8c8d" />
                  </TouchableOpacity>
                </View>

                <ScrollView style={styles.modalBody}>
                  {/* Stats Cards */}
                  <View style={styles.statsContainer}>
                    <View style={[styles.statCard, { backgroundColor: '#e8f6ef' }]}>
                      <View style={styles.statIconContainer}>
                        <Ionicons name="cube" size={28} color="#27ae60" />
                      </View>
                      <Text style={styles.statNumber}>{selectedDay.totalProducts}</Text>
                      <Text style={styles.statLabel}>{t('preview.modal_stat_products')}</Text>
                    </View>
                    
                    <View style={[styles.statCard, { backgroundColor: '#e8f4fd' }]}>
                      <View style={styles.statIconContainer}>
                        <Ionicons name="cash" size={28} color="#2980b9" />
                      </View>
                      <Text style={styles.statNumber}>{formatCurrency(selectedDay.totalSales)}</Text>
                      <Text style={styles.statLabel}>{t('preview.modal_stat_sales')}</Text>
                    </View>
                    
                    <View style={[styles.statCard, { backgroundColor: '#fdedec' }]}>
                      <View style={styles.statIconContainer}>
                        <Ionicons name="trending-up" size={28} color="#e74c3c" />
                      </View>
                      <Text style={styles.statNumber}>{formatCurrency(selectedDay.totalProfit)}</Text>
                      <Text style={styles.statLabel}>{t('preview.modal_stat_gross')}</Text>
                    </View>

                    <View style={[styles.statCard, { backgroundColor: '#f4ecf7' }]}>
                      <View style={styles.statIconContainer}>
                        <Ionicons name="calculator" size={28} color="#9b59b6" />
                      </View>
                      <Text style={styles.statNumber}>{formatCurrency(selectedDay.netProfit)}</Text>
                      <Text style={styles.statLabel}>{t('preview.modal_stat_net')}</Text>
                      {selectedDay.expenses && selectedDay.expenses.length > 0 && (
                        <Text style={styles.statDescription}>
                          {t('preview.modal_stat_expenses', { amount: formatCurrency(selectedDay.totalExpenses) })}
                        </Text>
                      )}
                    </View>
                  </View>

                  {/* Show expenses if any */}
                  {selectedDay.expenses && selectedDay.expenses.length > 0 && (
                    <View style={styles.section}>
                      <View style={styles.sectionHeader}>
                        <Ionicons name="receipt" size={20} color="#e67e22" />
                        <Text style={styles.sectionTitle}>{t('preview.modal_section_office_expenses')}</Text>
                        <Text style={styles.sectionCount}>({selectedDay.expenses.length})</Text>
                      </View>
                      
                      <View style={styles.expensesList}>
                        {selectedDay.expenses.map((expense, index) => (
                          <View key={expense.id} style={styles.expenseItem}>
                            <View style={styles.expenseInfo}>
                              <Text style={styles.expenseCategory}>{expense.category}</Text>
                              <Text style={styles.expenseDescription}>{expense.description}</Text>
                            </View>
                            <Text style={styles.expenseAmount}>{formatCurrency(expense.amount)}</Text>
                          </View>
                        ))}
                      </View>
                    </View>
                  )}

                  {/* Customers */}
                  <View style={styles.section}>
                    <View style={styles.sectionHeader}>
                      <Ionicons name="people" size={20} color="#3498db" />
                      <Text style={styles.sectionTitle}>{t('preview.modal_section_customers')}</Text>
                      <Text style={styles.sectionCount}>({selectedDay.customers.length})</Text>
                    </View>
                    
                    <View style={styles.customersList}>
                      {selectedDay.customers.length > 0 ? (
                        selectedDay.customers.map((customer, index) => (
                          <View key={index} style={styles.listItem}>
                            <Ionicons name="person-circle" size={20} color="#3498db" />
                            <Text style={styles.listItemText}>{customer}</Text>
                          </View>
                        ))
                      ) : (
                        <Text style={styles.emptyText}>{t('preview.modal_empty_customers')}</Text>
                      )}
                    </View>
                  </View>

                  {/* Sellers */}
                  <View style={styles.section}>
                    <View style={styles.sectionHeader}>
                      <Ionicons name="person" size={20} color="#9b59b6" />
                      <Text style={styles.sectionTitle}>{t('preview.modal_section_sellers')}</Text>
                      <Text style={styles.sectionCount}>({selectedDay.sellers.length})</Text>
                    </View>
                    
                    <View style={styles.sellersList}>
                      {selectedDay.sellers.length > 0 ? (
                        selectedDay.sellers.map((seller, index) => (
                          <View key={index} style={styles.listItem}>
                            <Ionicons name="person-outline" size={18} color="#9b59b6" />
                            <View style={styles.sellerInfo}>
                              <Text style={styles.sellerName}>{seller.full_name || seller.email}</Text>
                              <Text style={styles.sellerRole}>{seller.role === 'admin' ? t('preview.modal_role_admin') : t('preview.modal_role_seller')}</Text>
                            </View>
                          </View>
                        ))
                      ) : (
                        <Text style={styles.emptyText}>{t('preview.modal_empty_sellers')}</Text>
                      )}
                    </View>
                  </View>

                  {/* Sales List */}
                  <View style={styles.section}>
                    <View style={styles.sectionHeader}>
                      <Ionicons name="list" size={20} color="#f39c12" />
                      <Text style={styles.sectionTitle}>{t('preview.modal_section_sales')}</Text>
                      <Text style={styles.sectionCount}>({selectedDay.sales.length})</Text>
                    </View>
                    
                    <View style={styles.salesList}>
                      {selectedDay.sales.slice(0, 5).map((sale, index) => {
                        const costPrice = sale.cost_price || 0;
                        const sellingPrice = sale.unit_price || 0;
                        const quantity = sale.quantity || 0;
                        const profitPerUnit = sellingPrice - costPrice;
                        const totalItemProfit = profitPerUnit * quantity;
                        const finalProfit = Math.max(0, totalItemProfit);
                        
                        return (
                          <View key={index} style={styles.saleItem}>
                            <View style={styles.saleInfo}>
                              <Text style={styles.saleProduct}>{sale.product_name}</Text>
                              <Text style={styles.saleDetails}>
                                {t('preview.sale_label_customer')} {sale.customer_name} • {t('preview.sale_label_seller')} {sale.seller_name}
                              </Text>
                              {sale.invoice_number && (
                                <Text style={styles.saleInvoice}>{t('preview.sale_label_invoice')} {sale.invoice_number}</Text>
                              )}
                              <Text style={styles.saleCostPrice}>
                                {t('preview.sale_label_cost_price', {cost: formatCurrency(costPrice)})} • {t('preview.sale_label_sell_price', {sell: formatCurrency(sellingPrice)})}
                              </Text>
                            </View>
                            <View style={styles.saleAmount}>
                              <Text style={styles.saleQuantity}>{sale.quantity} x {formatCurrency(sale.unit_price)}</Text>
                              <Text style={styles.saleTotal}>{formatCurrency(sale.total_amount)}</Text>
                              <Text style={[styles.saleProfit, { color: finalProfit >= 0 ? '#27ae60' : '#e74c3c' }]}>
                                {t('preview.sale_label_profit', {amount: formatCurrency(finalProfit)})}
                              </Text>
                            </View>
                          </View>
                        );
                      })}
                      
                      {selectedDay.sales.length > 5 && (
                        <Text style={styles.moreSalesText}>
                          {t('preview.sale_more', {n: String(selectedDay.sales.length - 5)})}
                        </Text>
                      )}
                    </View>
                  </View>

                  {/* Final Summary */}
                  <View style={styles.finalSummary}>
                    <Text style={styles.finalSummaryTitle}>{t('preview.modal_final_title')}</Text>
                    
                    <View style={styles.summaryItemRow}>
                      <View style={styles.summaryItemLeft}>
                        <Ionicons name="cube" size={16} color="#2ecc71" />
                        <Text style={styles.summaryItemLabel}>{t('preview.modal_total_products')}</Text>
                      </View>
                      <Text style={styles.summaryItemValue}>{selectedDay.totalProducts}</Text>
                    </View>
                    
                    <View style={styles.summaryItemRow}>
                      <View style={styles.summaryItemLeft}>
                        <Ionicons name="cash" size={16} color="#f39c12" />
                        <Text style={styles.summaryItemLabel}>{t('preview.modal_total_sales')}</Text>
                      </View>
                      <Text style={styles.summaryItemValue}>{formatCurrency(selectedDay.totalSales)}</Text>
                    </View>
                    
                    <View style={styles.summaryItemRow}>
                      <View style={styles.summaryItemLeft}>
                        <Ionicons name="trending-up" size={16} color="#e74c3c" />
                        <Text style={styles.summaryItemLabel}>{t('preview.modal_total_gross')}</Text>
                      </View>
                      <Text style={[styles.summaryItemValue, { color: selectedDay.totalProfit >= 0 ? '#27ae60' : '#e74c3c' }]}>
                        {formatCurrency(selectedDay.totalProfit)}
                      </Text>
                    </View>

                    <View style={styles.summaryItemRow}>
                      <View style={styles.summaryItemLeft}>
                        <Ionicons name="receipt" size={16} color="#e67e22" />
                        <Text style={styles.summaryItemLabel}>{t('preview.modal_total_expenses')}</Text>
                      </View>
                      <Text style={[styles.summaryItemValue, { color: '#e67e22' }]}>
                        {formatCurrency(selectedDay.totalExpenses || 0)}
                      </Text>
                    </View>

                    <View style={styles.summaryItemRow}>
                      <View style={styles.summaryItemLeft}>
                        <Ionicons name="calculator" size={16} color="#9b59b6" />
                        <Text style={styles.summaryItemLabel}>{t('preview.modal_total_net')}</Text>
                      </View>
                      <Text style={[
                        styles.summaryItemValue,
                        { color: (selectedDay.netProfit || 0) >= 0 ? '#27ae60' : '#e74c3c', fontWeight: 'bold' }
                      ]}>
                        {formatCurrency(selectedDay.netProfit || 0)}
                      </Text>
                    </View>
                    
                    <View style={styles.summaryItemRow}>
                      <View style={styles.summaryItemLeft}>
                        <Ionicons name="people" size={16} color="#3498db" />
                        <Text style={styles.summaryItemLabel}>{t('preview.modal_total_customers')}</Text>
                      </View>
                      <Text style={styles.summaryItemValue}>{selectedDay.customers.length}</Text>
                    </View>
                    
                    <View style={styles.summaryItemRow}>
                      <View style={styles.summaryItemLeft}>
                        <Ionicons name="person" size={16} color="#9b59b6" />
                        <Text style={styles.summaryItemLabel}>{t('preview.modal_total_sellers')}</Text>
                      </View>
                      <Text style={styles.summaryItemValue}>{selectedDay.sellers.length}</Text>
                    </View>
                  </View>
                </ScrollView>
              </>
            )}
          </View>
        </View>
      </Modal>
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
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    padding: 20,
    backgroundColor: 'white',
    borderBottomWidth: 1,
    borderBottomColor: '#ecf0f1',
  },
  headerContent: {
    flex: 1,
  },
  headerActions: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  businessName: {
    fontSize: 18,
    color: '#3498db',
    marginTop: 4,
    fontWeight: '600',
  },
  businessLocation: {
    fontSize: 14,
    color: '#7f8c8d',
    marginTop: 2,
  },
  businessStats: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    marginTop: 10,
    gap: 8,
  },
  statItem: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f8f9fa',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 6,
  },
  statText: {
    fontSize: 12,
    color: '#2c3e50',
    marginLeft: 4,
  },
  refreshButton: {
    padding: 8,
  },
  businessInfo: {
    backgroundColor: '#e8f4fd',
    margin: 16,
    padding: 16,
    borderRadius: 12,
    borderLeftWidth: 4,
    borderLeftColor: '#3498db',
  },
  infoTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 8,
  },
  infoText: {
    fontSize: 14,
    color: '#2c3e50',
    lineHeight: 20,
  },
  
  // Debug Panel
  debugPanel: {
    backgroundColor: '#fff3e0',
    marginHorizontal: 16,
    marginBottom: 16,
    padding: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#f39c12',
  },
  debugHeader: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  debugTitle: {
    flex: 1,
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginLeft: 8,
  },
  debugContent: {
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: '#f39c12',
  },
  debugText: {
    fontSize: 13,
    color: '#2c3e50',
    marginBottom: 4,
  },
  debugDivider: {
    height: 1,
    backgroundColor: '#f39c12',
    marginVertical: 8,
  },
  debugSubtitle: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginTop: 8,
    marginBottom: 4,
  },
  debugSample: {
    fontSize: 12,
    color: '#7f8c8d',
    marginBottom: 2,
  },
  
  // TABS
  tabsContainer: {
    marginHorizontal: 16,
    marginBottom: 16,
  },
  tabsHeader: {
    flexDirection: 'row',
    backgroundColor: 'white',
    borderRadius: 10,
    marginBottom: 16,
    padding: 4,
    borderWidth: 1,
    borderColor: '#ecf0f1',
  },
  tabButton: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    paddingHorizontal: 8,
    borderRadius: 8,
  },
  activeTabButton: {
    backgroundColor: '#3498db10',
    borderWidth: 1,
    borderColor: '#3498db30',
  },
  tabButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#7f8c8d',
    marginLeft: 6,
  },
  activeTabButtonText: {
    color: '#3498db',
  },
  tabContent: {
    flex: 1,
  },
  
  // Days Tab
  daysContainer: {
    flex: 1,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 16,
  },
  sectionCount: {
    fontSize: 14,
    fontWeight: '600',
    color: '#7f8c8d',
    marginBottom: 16,
    marginLeft: 8,
  },
  dayCard: {
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
    borderWidth: 1,
    borderColor: '#ecf0f1',
  },
  dateHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  dateBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#3498db',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 20,
  },
  dateBadgeText: {
    fontSize: 12,
    fontWeight: 'bold',
    color: 'white',
    marginLeft: 6,
  },
  dayStats: {
    fontSize: 12,
    color: '#7f8c8d',
  },
  summaryGrid: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 12,
    gap: 8,
  },
  summaryBox: {
    flex: 1,
    alignItems: 'center',
    backgroundColor: '#f8f9fa',
    padding: 12,
    borderRadius: 8,
  },
  summaryNumber: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginTop: 4,
  },
  summaryLabel: {
    fontSize: 12,
    color: '#7f8c8d',
    marginTop: 2,
  },
  
  // Net Profit Styles
  netProfitRow: {
    marginTop: 4,
    marginBottom: 12,
    paddingVertical: 8,
    paddingHorizontal: 12,
    backgroundColor: '#f0f0f0',
    borderRadius: 8,
    borderLeftWidth: 3,
    borderLeftColor: '#9b59b6',
  },
  netProfitBox: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  netProfitLabel: {
    flex: 1,
    fontSize: 14,
    fontWeight: '600',
    color: '#2c3e50',
    marginLeft: 8,
  },
  netProfitValue: {
    fontSize: 16,
    fontWeight: 'bold',
  },
  expensesBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 4,
    paddingTop: 4,
    borderTopWidth: 1,
    borderTopColor: '#e0e0e0',
  },
  expensesBadgeText: {
    fontSize: 12,
    color: '#e67e22',
    marginLeft: 4,
  },
  
  customersSection: {
    marginBottom: 12,
  },
  sellersSection: {
    marginBottom: 12,
  },
  sectionLabel: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 6,
  },
  sectionLabelText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#2c3e50',
    marginLeft: 6,
  },
  customersText: {
    fontSize: 13,
    color: '#7f8c8d',
    lineHeight: 18,
  },
  sellersText: {
    fontSize: 13,
    color: '#7f8c8d',
    lineHeight: 18,
  },
  moreInfo: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: '#ecf0f1',
  },
  moreInfoText: {
    fontSize: 12,
    color: '#3498db',
    fontStyle: 'italic',
  },
  noData: {
    alignItems: 'center',
    justifyContent: 'center',
    padding: 40,
    backgroundColor: 'white',
    borderRadius: 12,
  },
  noDataText: {
    fontSize: 18,
    color: '#95a5a6',
    marginTop: 16,
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
  tryAgainButton: {
    marginTop: 16,
    paddingHorizontal: 20,
    paddingVertical: 10,
    backgroundColor: '#3498db',
    borderRadius: 8,
  },
  tryAgainText: {
    color: 'white',
    fontWeight: '600',
  },
  
  // Events Tab
  eventsContainer: {
    flex: 1,
  },
  eventsHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 16,
    paddingHorizontal: 8,
  },
  eventsTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginLeft: 8,
  },
  eventsCount: {
    fontSize: 14,
    color: '#7f8c8d',
    marginLeft: 4,
    fontWeight: '500',
  },
  eventsLoading: {
    alignItems: 'center',
    justifyContent: 'center',
    padding: 40,
    backgroundColor: 'white',
    borderRadius: 12,
  },
  eventsLoadingText: {
    marginTop: 10,
    fontSize: 14,
    color: '#7f8c8d',
  },
  eventsList: {
    flex: 1,
  },
  eventCard: {
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
    borderWidth: 1,
    borderColor: '#ecf0f1',
  },
  eventHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  eventIconContainer: {
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  eventTitleContainer: {
    flex: 1,
  },
  eventType: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  eventDate: {
    fontSize: 12,
    color: '#7f8c8d',
    marginTop: 2,
  },
  eventAmount: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#27ae60',
  },
  eventDescription: {
    fontSize: 14,
    color: '#2c3e50',
    lineHeight: 20,
    marginBottom: 12,
  },
  eventDetails: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  eventDetail: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f8f9fa',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 6,
  },
  eventDetailText: {
    fontSize: 12,
    color: '#2c3e50',
    marginLeft: 4,
  },
  noEvents: {
    alignItems: 'center',
    justifyContent: 'center',
    padding: 40,
    backgroundColor: 'white',
    borderRadius: 12,
  },
  noEventsText: {
    fontSize: 18,
    color: '#95a5a6',
    marginTop: 16,
    fontWeight: '500',
    textAlign: 'center',
  },
  noEventsSubtext: {
    fontSize: 14,
    color: '#bdc3c7',
    marginTop: 8,
    textAlign: 'center',
    lineHeight: 20,
  },
  
  // MODAL
  modalOverlay: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: 'rgba(0,0,0,0.5)',
    padding: 20,
  },
  modalContent: {
    backgroundColor: 'white',
    borderRadius: 16,
    width: '100%',
    maxWidth: 500,
    maxHeight: '85%',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 5,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#ecf0f1',
  },
  modalTitleContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  modalTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginLeft: 8,
  },
  modalSubtitle: {
    fontSize: 14,
    color: '#3498db',
    marginLeft: 8,
    marginTop: 2,
  },
  closeButton: {
    padding: 4,
  },
  modalBody: {
    padding: 20,
  },
  statsContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    marginBottom: 20,
    gap: 10,
  },
  statCard: {
    flex: 1,
    minWidth: '45%',
    alignItems: 'center',
    padding: 16,
    borderRadius: 12,
  },
  statIconContainer: {
    width: 50,
    height: 50,
    borderRadius: 25,
    backgroundColor: 'white',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 10,
  },
  statNumber: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 4,
    textAlign: 'center',
  },
  statLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#2c3e50',
    marginBottom: 2,
    textAlign: 'center',
  },
  statDescription: {
    fontSize: 12,
    color: '#7f8c8d',
    textAlign: 'center',
  },
  section: {
    marginBottom: 20,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
    paddingBottom: 8,
    borderBottomWidth: 2,
    borderBottomColor: '#ecf0f1',
  },
  expensesList: {
    backgroundColor: '#f8f9fa',
    borderRadius: 8,
    padding: 12,
  },
  expenseItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#ecf0f1',
  },
  expenseInfo: {
    flex: 1,
    marginRight: 12,
  },
  expenseCategory: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#e67e22',
    marginBottom: 2,
  },
  expenseDescription: {
    fontSize: 13,
    color: '#2c3e50',
  },
  expenseAmount: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#e67e22',
  },
  customersList: {
    backgroundColor: '#f8f9fa',
    borderRadius: 8,
    padding: 12,
  },
  sellersList: {
    backgroundColor: '#f8f9fa',
    borderRadius: 8,
    padding: 12,
  },
  listItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#ecf0f1',
  },
  listItemText: {
    fontSize: 14,
    color: '#2c3e50',
    marginLeft: 10,
  },
  sellerInfo: {
    marginLeft: 10,
  },
  sellerName: {
    fontSize: 14,
    color: '#2c3e50',
  },
  sellerRole: {
    fontSize: 12,
    color: '#7f8c8d',
    marginTop: 2,
  },
  salesList: {
    backgroundColor: '#f8f9fa',
    borderRadius: 8,
    padding: 12,
  },
  saleItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: '#ecf0f1',
  },
  saleInfo: {
    flex: 1,
    marginRight: 12,
  },
  saleProduct: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 4,
  },
  saleDetails: {
    fontSize: 12,
    color: '#7f8c8d',
    marginBottom: 2,
  },
  saleInvoice: {
    fontSize: 11,
    color: '#3498db',
    fontStyle: 'italic',
    marginBottom: 2,
  },
  saleCostPrice: {
    fontSize: 11,
    color: '#7f8c8d',
    fontStyle: 'italic',
  },
  saleAmount: {
    alignItems: 'flex-end',
    minWidth: 120,
  },
  saleQuantity: {
    fontSize: 12,
    color: '#7f8c8d',
    marginBottom: 2,
  },
  saleTotal: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#27ae60',
    marginBottom: 2,
  },
  saleProfit: {
    fontSize: 12,
    fontWeight: '600',
  },
  moreSalesText: {
    fontSize: 12,
    color: '#95a5a6',
    fontStyle: 'italic',
    textAlign: 'center',
    marginTop: 8,
  },
  finalSummary: {
    backgroundColor: '#e8f4fd',
    padding: 16,
    borderRadius: 12,
    borderLeftWidth: 4,
    borderLeftColor: '#3498db',
  },
  finalSummaryTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 12,
    textAlign: 'center',
  },
  summaryItemRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#d6eaf8',
  },
  summaryItemLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  summaryItemLabel: {
    fontSize: 14,
    color: '#2c3e50',
    marginLeft: 8,
    flex: 1,
  },
  summaryItemValue: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginLeft: 8,
  },
  emptyText: {
    fontSize: 14,
    color: '#95a5a6',
    textAlign: 'center',
    fontStyle: 'italic',
    padding: 10,
  },
});