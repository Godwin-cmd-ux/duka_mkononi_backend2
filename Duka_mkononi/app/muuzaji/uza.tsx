import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    FlatList,
    KeyboardAvoidingView,
    Modal,
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
import { useLang } from '../../context/LanguageContext';
import { useSession } from '../../context/SessionContext';
import LogoutButton from '../../components/logout-button';

// ✅ API Base URL
import { API_BASE_URL } from '../../constants/api';
import { getCache, setCache } from '../../db/cache';
import { registerLive, syncNow } from '../../lib/syncer';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';

// ✅ INTERFACE YA BIDHAA KATIKA KIKAPU
interface CartItem {
  product_id: string;
  name: string;
  quantity: number;
  unit_price: number;
  total_price: number;
  original_stock: number; // Stock asili kabla ya kuongeza kikapuni
  seller_id: string;
  seller_name: string;
}

export default function UzaScreen() {
  const { t, lang } = useLang();
  const router = useRouter();
  const { signOut } = useSession();
  const [loading, setLoading] = useState(false);
  const [loadingProducts, setLoadingProducts] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [userData, setUserData] = useState<any>(null);
  const [businessData, setBusinessData] = useState<any>(null);
  const [products, setProducts] = useState<any[]>([]);
  const [filteredProducts, setFilteredProducts] = useState<any[]>([]);
  const [customers, setCustomers] = useState<any[]>([]);
  const [searchQuery, setSearchQuery] = useState('');
  
  // ✅ HII NI KIKAPU CHA BIDHAA
  const [cart, setCart] = useState<CartItem[]>([]);
  const [showCart, setShowCart] = useState(false);
  
  const [formData, setFormData] = useState({
    customer_name: '',
    customer_phone: '',
    sale_date: new Date().toISOString().split('T')[0],
  });

  const [selectedProduct, setSelectedProduct] = useState<any>(null);
  const [quantity, setQuantity] = useState<string>('1');
  const [validationErrors, setValidationErrors] = useState<{[key: string]: string}>({});

  // Load user data and products on component mount
  useEffect(() => {
    loadUserData();
  }, [lang]);

  useEffect(() => {
    if (userData) {
      loadBusinessData();
    }
  }, [userData]);

  useEffect(() => {
    if (businessData) {
      loadProducts();
      loadCustomers();
    }
  }, [businessData, lang]);

  useEffect(() => {
    const stop = registerLive<any>(
      'products:my',
      async () => {
        const token = await AsyncStorage.getItem('userToken');
        const response = await fetchWithTimeout(`${API_BASE_URL}/api/products/my`, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
            'ngrok-skip-browser-warning': 'true'
          },
        });
        if (!response.ok) throw new Error('sync failed');
        const productsData = await response.json();
        const activeProducts = (productsData || []).filter((product: any) => product && (product.is_active !== false));
        return activeProducts.map((product: any) => ({
          ...product,
          // A missing selling price stays null. Falling back to product.price
          // would show the BUYING price as the selling price and would record
          // the sale at cost.
          expected_selling_price: product.expected_selling_price ?? null,
          seller_name: product.seller_name || 'Muuza',
          seller_role: product.seller_role || 'unknown',
          seller_email: product.seller_email || null
        }));
      },
      (data) => { setProducts(data); setLoadingProducts(false); }
    );
    return stop;
  }, [lang, businessData]);

  // Filter products when search query changes
  useEffect(() => {
    if (searchQuery.trim() === '') {
      setFilteredProducts(products);
    } else {
      const filtered = products.filter(product =>
        product.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (product.category && product.category.toLowerCase().includes(searchQuery.toLowerCase())) ||
        (product.description && product.description.toLowerCase().includes(searchQuery.toLowerCase())) ||
        (product.seller_name && product.seller_name.toLowerCase().includes(searchQuery.toLowerCase()))
      );
      setFilteredProducts(filtered);
    }
  }, [searchQuery, products]);

  const loadUserData = async () => {
    try {
      const userToken = await AsyncStorage.getItem('userToken');
      const storedUserData = await AsyncStorage.getItem('userData');
      
      if (!userToken || !storedUserData) {
        Alert.alert(t('app.error'), t('seller_dashboard.error_auth'));
        router.back();
        return;
      }

      const user = JSON.parse(storedUserData);
      
      // ✅ HAKIKISHA user DATA IKO
      if (!user || !user.id) {
        Alert.alert(t('app.error'), t('seller_dashboard.error_network'));
        await signOut();
        router.replace('/(tabs)');
        return;
      }
      
      setUserData(user);
      
    } catch (error) {
      console.error('❌ Error loading user data:', error);
      Alert.alert(t('app.error'), t('seller_dashboard.error_network'));
      
      // Clear storage and redirect to login
      try {
        await signOut();
        router.replace('/(tabs)');
      } catch (clearError) {
        console.error('❌ Error clearing storage:', clearError);
      }
    }
  };

  const loadBusinessData = async () => {
    let cachedBiz: any = null;
    let businessOut: any = null;
    try {
      if (!userData || !userData.business_name) {
        console.log('⚠️ User has no business name');
        setBusinessData({ 
          id: userData?.id, 
          business_name: userData?.business_name || 'Personal',
          is_admin: userData?.role === 'admin' 
        });
        return;
      }

      console.log('🏢 Loading business data for:', userData.business_name);
      
      cachedBiz = await getCache<any>('business:by-name:' + userData.business_name);
      if (cachedBiz) {
        businessOut = cachedBiz;
        setBusinessData(cachedBiz);
      }

      try {
        const response = await fetchWithTimeout(
          `${API_BASE_URL}/api/business/by-name/${encodeURIComponent(userData.business_name)}`, 
          {
            method: 'GET',
            headers: {
              'Content-Type': 'application/json',
              'ngrok-skip-browser-warning': 'true'
            }
          },
          10000
        );

        if (response.ok) {
          const businessInfo = await response.json();
          console.log('✅ Business data loaded:', businessInfo);
          
          if (businessInfo.exists && businessInfo.hasAdmin) {
            businessOut = {
              id: businessInfo.admin.id,
              business_name: businessInfo.admin.business_name,
              is_admin: true,
              admin_email: businessInfo.admin.email
            };
            setBusinessData(businessOut);
          } else {
            businessOut = { 
              id: userData.id, 
              business_name: userData.business_name,
              is_admin: userData.role === 'admin' 
            };
            setBusinessData(businessOut);
          }
        } else {
          console.log('⚠️ Business not found, using personal data');
          if (!cachedBiz) {
            businessOut = { 
              id: userData.id, 
              business_name: userData.business_name || 'Personal',
              is_admin: userData.role === 'admin' 
            };
            setBusinessData(businessOut);
          }
        }
      } catch (fetchError) {
        console.log('⚠️ Business API failed, using personal data');
        if (!cachedBiz) {
          businessOut = { 
            id: userData.id, 
            business_name: userData.business_name || 'Personal',
            is_admin: userData.role === 'admin' 
          };
          setBusinessData(businessOut);
        }
      }

      if (businessOut) {
        setCache('business:by-name:' + userData.business_name, businessOut).catch(() => {});
      }

    } catch (error) {
      console.error('❌ Error loading business data:', error);
      if (!cachedBiz && !businessOut) {
        setBusinessData({ 
          id: userData?.id, 
          business_name: userData?.business_name || 'Personal',
          is_admin: userData?.role === 'admin' 
        });
      }
    }
  };

  const loadProducts = useCallback(async () => {
    try {
      setLoadingProducts(true);
      setValidationErrors({});
      
      console.log('📦 Loading products for sale...');
      
      const token = await AsyncStorage.getItem('userToken');
      
      if (!token) {
        Alert.alert(t('app.error'), t('seller_dashboard.error_auth'));
        router.back();
        return;
      }

      const cached = await getCache<any>('products:my');
      if (cached) { setProducts(cached); setFilteredProducts(cached); setLoadingProducts(false); }

      // Jaribu endpoint kuu
      let productsData = [];
      
      try {
        const response = await fetchWithTimeout(`${API_BASE_URL}/api/products/my`, {
          method: 'GET',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
            'ngrok-skip-browser-warning': 'true'
          },
        });

        if (response.ok) {
          productsData = await response.json();
          console.log('✅ Main API Response received:', productsData?.length || 0, 'products');
        } else {
          console.log('⚠️ API failed:', response.status);
          productsData = [];
        }
      } catch (apiError) {
        console.log('⚠️ API call failed:', apiError);
        const cached = await getCache<any>('products:my');
        if (cached) {
          productsData = cached;
        } else {
          productsData = [];
        }
      }
      
      // ✅ HAKIKISHA productsData NI ARRAY
      if (!Array.isArray(productsData)) {
        console.error('❌ API returned non-array data:', typeof productsData);
        productsData = [];
      }
      
      // Filter active products only (with stock > 0)
      const activeProducts = (productsData || []).filter((product: any) => 
        product && (product.is_active !== false)
      );
      
      // ✅ HAKIKISHA expected_selling_price IKO
      const processedProducts = activeProducts.map((product: any) => {
        // A missing selling price stays null. Falling back to product.price
        // would show the BUYING price as the selling price and would record
        // the sale at cost.
        const sellingPrice = product.expected_selling_price ?? null;
        return {
          ...product,
          expected_selling_price: sellingPrice,
          // Auto-fill seller info ikiwa hakuna
          seller_name: product.seller_name || 'Muuza',
          seller_role: product.seller_role || 'unknown',
          seller_email: product.seller_email || null
        };
      });
      
      setProducts(processedProducts);
      setFilteredProducts(processedProducts);
      setCache('products:my', processedProducts).catch(() => {});
      
      console.log(`✅ Loaded ${processedProducts.length} active products`);
      
      if (processedProducts.length === 0) {
        Alert.alert(
          t('seller_sell.no_products'), 
          t('seller_sell.no_results_text'),
          [{ text: t('app.ok') }]
        );
      }

    } catch (error: any) {
      console.error('❌ Error loading products:', error.message || error);
      
      const cached = await getCache<any>('products:my');
      if (cached) { setProducts(cached); setFilteredProducts(cached); setLoadingProducts(false); return; }

      setValidationErrors({
        products: t('seller_dashboard.error_edit')
      });
      
      setProducts([]);
      setFilteredProducts([]);
      
    } finally {
      setLoadingProducts(false);
      setRefreshing(false);
    }
  }, [router, businessData, userData]);

  const loadCustomers = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      
      if (!token) return;

      const cached = await getCache<any>('customers:my');
      if (cached) setCustomers(cached);

      const response = await fetchWithTimeout(`${API_BASE_URL}/api/customers/my`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'ngrok-skip-browser-warning': 'true'
        }
      }, 10000);

      if (response.ok) {
        const customersData = await response.json();
        setCustomers(customersData || []);
        setCache('customers:my', customersData || []).catch(() => {});
      }

    } catch (error) {
      console.error('❌ Error loading customers:', error);
    }
  };

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    loadProducts();
    loadCustomers();
  }, [loadProducts]);

  // ✅ FUNCTION: HESABU JUMLA YA BIDHAA ZILIZO KATIKA KIKAPU KWA PRODUCT
  const getTotalInCartForProduct = (productId: string) => {
    const cartItem = cart.find(item => item.product_id === productId);
    return cartItem ? cartItem.quantity : 0;
  };

  // ✅ FUNCTION: HESABU STOCK ILIYOBAAKI KWA UHAKIKA
  const calculateAvailableStock = (product: any) => {
    const inCart = getTotalInCartForProduct(product.id);
    return product.stock - inCart;
  };

  // ✅ FUNCTION: ONGEZA BIDHAA KATIKA KIKAPU - HAPANA KU-PUNGUZA STOCK HAPA
  const addToCart = () => {
    if (!selectedProduct) {
      Alert.alert(t('app.error'), t('seller_sell.select_product'));
      return;
    }

    const quantityNum = parseInt(quantity);
    if (isNaN(quantityNum) || quantityNum <= 0) {
      Alert.alert(t('app.error'), t('seller_dashboard.quantity_placeholder'));
      return;
    }

    // ✅ HESABU STOCK ILIYOBAAKI (stock asili - tayari kikapuni)
    const alreadyInCart = getTotalInCartForProduct(selectedProduct.id);
    const availableStock = selectedProduct.stock - alreadyInCart;

    if (quantityNum > availableStock) {
      Alert.alert(
        t('seller_dashboard.quantity'),
        t('seller_sell.insufficient_stock_msg', { name: selectedProduct.name }) + '\n\n' +
        t('seller_sell.stock_remaining', { stock: selectedProduct.stock }) + '\n' +
        t('seller_sell.already_in_cart_num', { n: alreadyInCart }) + '\n' +
        t('seller_sell.stock_available', { n: availableStock }) + '\n' +
        t('seller_sell.you_want', { n: quantityNum })
      );
      return;
    }

    // Usiue bidhaa kwa bei ya kununua: bei ya kuuzia lazima iwe imewekwa
    if (selectedProduct.expected_selling_price === null || selectedProduct.expected_selling_price === undefined) {
      Alert.alert(
        t('seller_sell.selling_price_not_set'),
        t('seller_sell.selling_price_not_set_msg', { name: selectedProduct.name })
      );
      return;
    }

    // Angalia ikiwa bidhaa tayari ipo kwenye kikapu
    const existingItemIndex = cart.findIndex(item => item.product_id === selectedProduct.id);
    
    if (existingItemIndex >= 0) {
      // Ongeza kiasi kwenye bidhaa iliyopo
      const updatedCart = [...cart];
      const newQuantity = updatedCart[existingItemIndex].quantity + quantityNum;
      
      updatedCart[existingItemIndex] = {
        ...updatedCart[existingItemIndex],
        quantity: newQuantity,
        total_price: newQuantity * updatedCart[existingItemIndex].unit_price
      };
      setCart(updatedCart);
    } else {
      // Ongeza bidhaa mpya kwenye kikapu
      const newItem: CartItem = {
        product_id: selectedProduct.id,
        name: selectedProduct.name,
        quantity: quantityNum,
        unit_price: selectedProduct.expected_selling_price,
        total_price: quantityNum * selectedProduct.expected_selling_price,
        original_stock: selectedProduct.stock, // Hifadhi stock asili
        seller_id: selectedProduct.seller_id,
        seller_name: selectedProduct.seller_name
      };
      setCart([...cart, newItem]);
    }

    // ✅ HAPANA KU-PUNGUZA STOCK HAPA! SERVER ITAPUNGUZA BAADAE
    // ✅ Futa sehemu za kuchagua bidhaa tu
    setSelectedProduct(null);
    setQuantity('1');
    
    Alert.alert(
      t('seller_sell.added_to_cart'),
      t('seller_sell.added_msg', { n: quantityNum, name: selectedProduct.name }) + '\n\n' + t('seller_sell.cart_count', { n: cart.length + 1 }),
      [{text: t('app.ok')}]
    );
  };

  // ✅ FUNCTION: ONDOA BIDHAA KUTOKA KIKAPUNI
  const removeFromCart = (productId: string) => {
    const itemToRemove = cart.find(item => item.product_id === productId);
    if (!itemToRemove) return;

    // Ondoa kutoka kikapu
    const updatedCart = cart.filter(item => item.product_id !== productId);
    setCart(updatedCart);
    
    Alert.alert(
      t('seller_sell.removed'),
      t('seller_sell.removed_msg', { name: itemToRemove.name }),
      [{text: t('app.ok')}]
    );
  };

  // ✅ FUNCTION: BADILISHA KASI KATIKA KIKAPU
  const updateCartQuantity = (productId: string, newQuantity: number) => {
    if (newQuantity < 1) {
      removeFromCart(productId);
      return;
    }

    const product = products.find(p => p.id === productId);
    if (!product) return;

    const cartItemIndex = cart.findIndex(item => item.product_id === productId);
    if (cartItemIndex < 0) return;

    // ✅ HESABU STOCK ILIYOBAAKI KWA UHAKIKA
    const alreadyInCart = getTotalInCartForProduct(productId);
    const currentItemQuantity = cart[cartItemIndex].quantity;
    const otherItemsInCart = alreadyInCart - currentItemQuantity;
    const availableStock = product.stock - otherItemsInCart;

    if (newQuantity > availableStock) {
      Alert.alert(
        t('seller_sell.insufficient_stock_title'),
        t('seller_sell.cannot_change', { n: newQuantity }) + '\n\n' +
        t('seller_sell.stock_remaining', { stock: product.stock }) + '\n' +
        t('seller_sell.others_in_cart', { n: otherItemsInCart }) + '\n' +
        t('seller_sell.max_possible', { n: availableStock })
      );
      return;
    }

    // Sasisha kikapu
    const updatedCart = [...cart];
    updatedCart[cartItemIndex] = {
      ...updatedCart[cartItemIndex],
      quantity: newQuantity,
      total_price: newQuantity * updatedCart[cartItemIndex].unit_price
    };
    setCart(updatedCart);
  };

  // ✅ HESABU JUMLA YA KIKAPU
  const calculateCartTotal = () => {
    return cart.reduce((total, item) => total + item.total_price, 0);
  };

  // ✅ VALIDATION YA FOMU - CUSTOMER NAME SI LAZIMA
  const validateForm = () => {
    const errors: {[key: string]: string} = {};
    
    if (cart.length === 0) {
      errors.cart = t('seller_dashboard.add_one_product');
    }
    
    // Validate date format (YYYY-MM-DD)
    const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
    if (formData.sale_date && !dateRegex.test(formData.sale_date)) {
      errors.sale_date = t('seller_dashboard.date_format');
    }
    
    setValidationErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleInputChange = (field: string, value: string) => {
    // Clear validation error for this field
    if (validationErrors[field]) {
      setValidationErrors(prev => {
        const newErrors = { ...prev };
        delete newErrors[field];
        return newErrors;
      });
    }
    
    setFormData(prev => ({
      ...prev,
      [field]: value
    }));
  };

  const handleTodayDate = () => {
    const today = new Date().toISOString().split('T')[0];
    handleInputChange('sale_date', today);
  };

  const getOrCreateCustomer = async (customerName: string, customerPhone?: string): Promise<string | null> => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) return null;

      if (!(await requireNetwork())) return null;

      const existingCustomer = customers.find(c => 
        c.name.toLowerCase() === customerName.trim().toLowerCase()
      );

      if (existingCustomer) {
        console.log('✅ Using existing customer:', existingCustomer.id);
        
        const uuidV4Pattern = /^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-4[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/;
        if (uuidV4Pattern.test(existingCustomer.id)) {
          return existingCustomer.id;
        } else {
          console.warn('⚠️ Existing customer ID is not valid UUID:', existingCustomer.id);
          return null;
        }
      }

      console.log('➕ Creating new customer:', customerName);
      
      const customerData = {
        name: customerName.trim(),
        phone: customerPhone?.trim() || "",
        email: ""
      };

      const response = await fetchWithTimeout(`${API_BASE_URL}/api/customers`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'ngrok-skip-browser-warning': 'true'
        },
        body: JSON.stringify(customerData)
      });

      if (response.ok) {
        const result = await response.json();
        const newCustomerId = result.customer?.id || result.id;
        
        const uuidV4Pattern = /^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-4[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/;
        
        if (newCustomerId && uuidV4Pattern.test(newCustomerId)) {
          console.log('✅ Customer created with valid UUID:', newCustomerId);
          loadCustomers();
          return newCustomerId;
        } else {
          console.warn('⚠️ Created customer ID is not valid UUID:', newCustomerId);
          return null;
        }
      } else {
        console.log('⚠️ Customer creation failed, continuing without customer_id');
        return null;
      }

    } catch (error) {
      console.error('❌ Error in getOrCreateCustomer:', error);
      return null;
    }
  };

  const handleSale = async () => {
    if (!validateForm()) {
      Alert.alert(t('app.error'), t('seller_sell.add_one_product'));
      return;
    }

    setLoading(true);

    try {
      const token = await AsyncStorage.getItem('userToken');
      
      if (!token) {
        Alert.alert(t('app.error'), t('seller_dashboard.error_auth'));
        router.back();
        return;
      }

      if (!(await requireNetwork())) return;

      // TENGENEZA ITEMS KUTOKA KIKAPUNI
      const items = cart.map(item => ({
        product_id: item.product_id,
        quantity: item.quantity,
        unit_price: item.unit_price
      }));

      let customerId: string | null = null;
      
      // ✅ CUSTOMER ID SI LAZIMA - TUNAWEZA KUENDELEA BILA YEYE
      if (formData.customer_name && formData.customer_name.trim()) {
        const customerResult = await getOrCreateCustomer(
          formData.customer_name.trim(),
          formData.customer_phone.trim()
        );
        
        if (customerResult) {
          const uuidV4Pattern = /^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-4[0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/;
          if (uuidV4Pattern.test(customerResult)) {
            customerId = customerResult;
          }
        }
      }

      // Idempotency key shared by every attempt of this sale. Laravel looks
      // for a sale whose notes start with `ai_dup_<key>` and returns the
      // ORIGINAL sale instead of creating a second one — this is what stops a
      // double-tap or a retried request from deducting the stock twice (same
      // fix as the Blade page: SaleController::store).
      const clientSaleKey = 'uza-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);

      const saleData: any = {
        items: items,
        sale_date: formData.sale_date,
        payment_method: 'cash',
        notes: `Muuzaji: ${userData.full_name || userData.email}`,
        clientSaleKey
      };

      if (customerId) {
        saleData.customer_id = customerId;
      }

      console.log('📤 Sending sale data for multiple items:', saleData);

      const sendSale = async () => {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 30000);
        try {
          return await fetch(`${API_BASE_URL}/api/sales`, {
            method: 'POST',
            headers: {
              'Authorization': `Bearer ${token}`,
              'Content-Type': 'application/json',
              'ngrok-skip-browser-warning': 'true'
            },
            body: JSON.stringify(saleData),
            signal: controller.signal
          });
        } finally {
          clearTimeout(timeoutId);
        }
      };

      // One automatic retry on a network hiccup. Safe from double charging
      // because the shared clientSaleKey makes the request idempotent.
      let response: any;
      try {
        response = await sendSale();
      } catch (networkError) {
        response = await sendSale();
      }

      const responseText = await response.text();
      console.log('📥 API Response:', {
        status: response.status,
        statusText: response.statusText,
        body: responseText
      });

      let result: any;
      try {
        result = JSON.parse(responseText);
      } catch (parseError) {
        console.error('❌ Failed to parse JSON response:', responseText);
        throw new Error(`${t('seller_sell.sale_error')}: ${response.status} ${response.statusText}`);
      }

      if (!response.ok) {
        let errorMessage = `${t('seller_sell.sale_error')} (${response.status})`;
        
        if (result?.error) errorMessage = result.error;
        if (result?.message) errorMessage = result.message;
        if (result?.details) errorMessage = `${errorMessage}\n\nDetails: ${result.details}`;

        console.error('❌ Server Error:', errorMessage);

        if (response.status === 400) {
          if (errorMessage.includes('stock') || errorMessage.includes('kiasi')) {
            throw new Error(`Kiasi Hakitoshi: ${errorMessage}`);
          } else if (errorMessage.includes('product_id') || errorMessage.includes('bidhaa')) {
            throw new Error(`Hitilafu ya Bidhaa: ${errorMessage}`);
          } else if (errorMessage.includes('customer_id') || errorMessage.includes('mteja')) {
            throw new Error(`Hitilafu ya Mteja: ${errorMessage}`);
          }
        } else if (response.status === 401) {
          throw new Error(t('seller_dashboard.error_auth'));
        } else if (response.status === 403) {
          throw new Error(t('seller_dashboard.error_network'));
        } else if (response.status === 404) {
          throw new Error(t('seller_sell.sale_error'));
        } else if (response.status === 500) {
          throw new Error(t('seller_sell.sale_error') + ': ' + errorMessage);
        }

        throw new Error(errorMessage);
      }

      console.log('✅ Sale successful:', result);

      if (result.success === false) {
        throw new Error(result.error || result.message || t('seller_sell.sale_error'));
      }

      const invoiceNumber = result.invoice_number || result.sale?.invoice_number || t('seller_sell.na_fallback');
      const totalAmount = calculateCartTotal();
      const customerName = formData.customer_name.trim() || t('seller_sell.missing_customer_name');
      const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
      
      // ✅ SASISHA PRODUCTS LIST BAADA YA MAUZO MAFANIKIO
      // Server amepunguza stock, sasa sisi pia tusasie kwenye UI
      const updatedProducts = products.map(product => {
        const cartItem = cart.find(item => item.product_id === product.id);
        if (cartItem) {
          return {
            ...product,
            stock: Math.max(0, product.stock - cartItem.quantity)
          };
        }
        return product;
      });
      
      setProducts(updatedProducts);
      setFilteredProducts(updatedProducts);
      
      Alert.alert(
        t('seller_dashboard.success_sale'),
        t('seller_sell.sale_success_msg', { n: totalItems, customer: customerName }) + '\n' +
        t('seller_sell.sale_total', { amount: formatCurrency(totalAmount) }) + '\n\n' +
        t('seller_sell.invoice', { n: invoiceNumber }),
        [
          {
            text: t('app.ok'),
            onPress: () => {
              resetForm();
            }
          },
          {
            text: t('seller_sell.sell_more'),
            onPress: () => {
              resetForm();
            }
          }
        ]
      );

    } catch (error: any) {
      console.error('❌ Error processing sale:', error);

      let errorTitle = t('app.error');
      let errorMessage = error.message || t('seller_sell.sale_error');

      if (error.name === 'AbortError') {
        errorTitle = t('seller_sell.timeout_title');
        errorMessage = t('seller_dashboard.timeout_error');
      } else if (error.message.includes('401')) {
        errorTitle = t('seller_sell.session_expired');
        errorMessage = t('seller_dashboard.error_auth');
        
        Alert.alert(
          errorTitle,
          errorMessage,
          [
            { 
              text: t('seller_sell.login_again_btn'), 
              onPress: async () => {
                await signOut();
                router.replace('/(tabs)');
              }
            }
          ]
        );
        return;
      } else if (error.message.includes('Kiasi Hakitoshi')) {
        errorTitle = t('seller_sell.insufficient_stock_title');
        // Refresh stock data kwa sababu labda stock imebadilika
        setTimeout(() => {
          loadProducts();
        }, 1000);
      }

      Alert.alert(errorTitle, errorMessage);

    } finally {
      setLoading(false);
    }
  };

  const resetForm = () => {
    setFormData({
      customer_name: '',
      customer_phone: '',
      sale_date: new Date().toISOString().split('T')[0],
    });
    setSelectedProduct(null);
    setQuantity('1');
    setCart([]);
    setValidationErrors({});
    setSearchQuery('');
    // LoadProducts() sio lazima tena kwa sababu tumesasishia products tayari
  };

  const formatCurrency = (amount: number) => {
    return `TZS ${amount.toLocaleString('en-TZ', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    })}`;
  };

  const refreshProducts = () => {
    setRefreshing(true);
    loadProducts();
  };

  const renderProductItem = ({ item }: { item: any }) => {
    const inCart = getTotalInCartForProduct(item.id);
    const availableStock = item.stock - inCart;
    const isOutOfStock = availableStock <= 0;
    const isLowStock = availableStock <= 5 && availableStock > 0;
    
    return (
      <TouchableOpacity
        style={[
          styles.productItem,
          selectedProduct?.id === item.id && styles.productItemSelected,
          isOutOfStock && styles.productItemOutOfStock
        ]}
        onPress={() => {
          if (!isOutOfStock) {
            setSelectedProduct({
              ...item,
              availableStock: availableStock
            });
            setQuantity('1');
          }
        }}
        disabled={isOutOfStock}
      >
        <View style={styles.productItemContent}>
          <View style={styles.productItemHeader}>
            <Text style={[
              styles.productName,
              selectedProduct?.id === item.id && styles.productNameSelected,
              isOutOfStock && styles.productNameOutOfStock
            ]}>
              {item.name}
            </Text>
            <View style={[
              styles.productStockBadge,
              isLowStock && styles.productStockBadgeLow,
              isOutOfStock && styles.productStockBadgeOut
            ]}>
              <Text style={styles.productStockBadgeText}>
                {availableStock} {t('seller_sell.remaining')}
                {inCart > 0 && ` (${inCart} ${t('seller_sell.in_cart')})`}
              </Text>
            </View>
          </View>
          
          <Text style={styles.productCategory}>              {item.category || t('seller_sell.no_category')}
          </Text>
          
          <View style={styles.productFooter}>
            <View style={styles.priceColumn}>
              {/* Always render the price row: a product whose selling price was
                  never recorded must say so, not silently show nothing. */}
              <Text style={styles.expectedPrice}>
                {t('seller_sell.sell_price')}{' '}
                {item.expected_selling_price === null || item.expected_selling_price === undefined
                  ? <Text style={{ color: '#f39c12' }}>{t('seller_sell.selling_price_not_set')}</Text>
                  : formatCurrency(item.expected_selling_price)}
              </Text>
            </View>
            
            {/* Onyesha owner ya bidhaa */}
            <View style={[
              styles.productOwnerBadge,
              item.seller_id === userData?.id ? styles.productOwnerBadgeOwn : 
              styles.productOwnerBadgeOther
            ]}>
              {item.seller_id === userData?.id ? (
                <>
                  <Ionicons name="person" size={12} color="#3498db" />
                  <Text style={styles.productOwnerText}>{t('seller_sell.your')}</Text>
                </>
              ) : (
                <>
                  <Ionicons name="business" size={12} color="#9b59b6" />
                  <Text style={styles.productOwnerText}>{t('seller_sell.business_txt')}</Text>
                </>
              )}
            </View>
          </View>
        </View>
        
        {selectedProduct?.id === item.id && (
          <View style={styles.selectedIndicator}>
            <Ionicons name="checkmark-circle" size={24} color="#2ecc71" />
          </View>
        )}
      </TouchableOpacity>
    );
  };

  // ✅ RENDER CART ITEM
  const renderCartItem = ({ item }: { item: CartItem }) => {
    const product = products.find(p => p.id === item.product_id);
    const availableStock = product ? product.stock - getTotalInCartForProduct(item.product_id) : 0;
    
    return (
      <View style={styles.cartItem}>
        <View style={styles.cartItemInfo}>
          <Text style={styles.cartItemName}>{item.name}</Text>
          <Text style={styles.cartItemDetails}>
            {item.quantity} × {formatCurrency(item.unit_price)} = {formatCurrency(item.total_price)}
          </Text>
          <Text style={styles.cartItemStock}>
            {t('seller_sell.stock_original_short', { n: item.original_stock })} • {t('seller_sell.remaining')}: {availableStock}
          </Text>
          <Text style={styles.cartItemSeller}>
            {t('seller_sell.muuzaji_prefix')} {item.seller_name}
          </Text>
        </View>
        <View style={styles.cartItemActions}>
          <View style={styles.quantityControls}>
            <TouchableOpacity 
              style={styles.quantityButton}
              onPress={() => updateCartQuantity(item.product_id, Math.max(1, item.quantity - 1))}
            >
              <Ionicons name="remove" size={18} color="#e74c3c" />
            </TouchableOpacity>
            <Text style={styles.quantityText}>{item.quantity}</Text>
            <TouchableOpacity 
              style={styles.quantityButton}
              onPress={() => updateCartQuantity(item.product_id, item.quantity + 1)}
            >
              <Ionicons name="add" size={18} color="#2ecc71" />
            </TouchableOpacity>
          </View>
          <TouchableOpacity 
            style={styles.removeButton}
            onPress={() => removeFromCart(item.product_id)}
          >
            <Ionicons name="trash" size={18} color="#fff" />
          </TouchableOpacity>
        </View>
      </View>
    );
  };

  if (!userData || !businessData) {
    return (
      <SafeAreaView style={styles.screenSafe} edges={['top']}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#2ecc71" />
          <Text style={styles.loadingText}>{t('profile.loading')}</Text>
          <Text style={styles.loadingSubtext}>
            {!userData ? t('profile.loading') : t('profile.business') + '...'}
          </Text>
        </View>
      </SafeAreaView>
    );
  }    return (
    <SafeAreaView style={styles.screenSafe} edges={['top']}>
    <KeyboardAvoidingView 
      style={styles.keyboardAvoid}
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView 
        style={styles.container}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        showsVerticalScrollIndicator={false}
      >
        {/* Header */}
        <View style={styles.header}>
          <View style={styles.headerTop}>
            <TouchableOpacity onPress={() => router.back()} style={styles.backButton}>
              <Ionicons name="arrow-back" size={24} color="#2c3e50" />
            </TouchableOpacity>
            <Text style={styles.title}>🛒 {t('seller_sell.title')}</Text>
            <View style={styles.headerActions}>
              <TouchableOpacity onPress={refreshProducts} style={styles.refreshButton}>
                <Ionicons name="refresh" size={20} color="#3498db" />
              </TouchableOpacity>
              <LogoutButton iconOnly />
            </View>
          </View>
          
          <Text style={styles.subtitle}>
            {t('seller_sell.business_label')} <Text style={styles.businessName}>{userData.business_name}</Text>
          </Text>
          <Text style={styles.userInfo}>
            {t('seller_sell.selling_as')} {userData.full_name || userData.email}
            {userData.role === 'admin' ? ' (' + t('seller_sell.manager_label') + ')' : ' (' + t('seller_sell.seller_label') + ')'}
          </Text>
        </View>


        <View style={styles.formContainer}>
          {/* Sehemu ya Kuchagua Bidhaa */}
          <View style={styles.inputGroup}>
            <View style={styles.sectionHeader}>
              <Text style={styles.label}>{t('seller_sell.select_product')}</Text>
              <TouchableOpacity onPress={refreshProducts} style={styles.smallRefreshButton}>
                <Ionicons name="refresh" size={16} color="#3498db" />
                <Text style={styles.refreshText}>{t('seller_sell.refresh')}</Text>
              </TouchableOpacity>
            </View>
            
            {validationErrors.products && (
              <View style={styles.errorContainer}>
                <Ionicons name="warning" size={16} color="#e74c3c" />
                <Text style={styles.errorText}>{validationErrors.products}</Text>
              </View>
            )}
            
            {/* Search Bar */}
            <View style={styles.searchContainer}>
              <Ionicons name="search" size={20} color="#95a5a6" style={styles.searchIcon} />
              <TextInput
                style={styles.searchInput}
                placeholder={t('seller_sell.search_placeholder')}
                value={searchQuery}
                onChangeText={setSearchQuery}
                placeholderTextColor="#95a5a6"
              />
              {searchQuery ? (
                <TouchableOpacity onPress={() => setSearchQuery('')}>
                  <Ionicons name="close-circle" size={20} color="#95a5a6" />
                </TouchableOpacity>
              ) : null}
            </View>
            
            {loadingProducts ? (
              <View style={styles.loadingProducts}>
                <ActivityIndicator size="small" color="#3498db" />
                <Text style={styles.loadingProductsText}>
                  {userData.role === 'admin' 
                    ? t('seller_sell.loading_own') 
                    : t('seller_sell.loading_biz', { name: userData.business_name })
                  }
                </Text>
              </View>
            ) : filteredProducts.length === 0 ? (
              <View style={styles.noProductsContainer}>
                <Ionicons name="cube-outline" size={48} color="#bdc3c7" />
                <Text style={styles.noProductsTitle}>
                  {searchQuery ? t('seller_sell.no_results') : t('seller_sell.no_products')}
                </Text>
                <Text style={styles.noProductsText}>
                  {searchQuery
                    ? t('seller_sell.no_results_text')
                    : userData.role === 'admin'
                    ? t('seller_sell.no_products_admin')
                    : t('seller_sell.no_products_biz', { name: userData.business_name })
                  }
                </Text>
                
                <TouchableOpacity 
                  style={styles.retryButton}
                  onPress={refreshProducts}
                >
                  <Ionicons name="refresh" size={16} color="white" />
                  <Text style={styles.retryButtonText}>{t('seller_sell.retry')}</Text>
                </TouchableOpacity>
                
                {searchQuery ? (
                  <TouchableOpacity 
                    style={styles.clearSearchButton}
                    onPress={() => setSearchQuery('')}
                  >
                    <Text style={styles.clearSearchButtonText}>{t('seller_sell.view_all')}</Text>
                  </TouchableOpacity>
                ) : userData.role === 'admin' ? (
                  <TouchableOpacity 
                    style={styles.addProductButton}
                    onPress={() => router.push('/muuzaji/bidhaa-mpya' as any)}
                  >
                    <Ionicons name="add-circle" size={20} color="white" />
                    <Text style={styles.addProductButtonText}>{t('seller_sell.add_product')}</Text>
                  </TouchableOpacity>
                ) : null}
              </View>
            ) : (
              <>
                <FlatList
                  data={filteredProducts}
                  renderItem={renderProductItem}
                  keyExtractor={(item) => item.id}
                  scrollEnabled={false}
                  showsVerticalScrollIndicator={false}
                  contentContainerStyle={styles.productsList}
                />
                
                <Text style={styles.productsCount}>
                  {filteredProducts.length} {t('seller_sell.products_count')} • 
                  {filteredProducts.filter(p => p.stock > 0).length} {t('seller_sell.products_in_stock')} •
                  {cart.length > 0 && ` ${cart.length} ${t('seller_sell.in_cart')}`}
                </Text>
              </>
            )}
          </View>

          {/* Taarifa za Bidhaa Iliyochaguliwa */}
          {selectedProduct && (
            <View style={styles.productInfo}>
              <Text style={styles.productInfoTitle}>{t('seller_sell.product_info_title')}</Text>
              <View style={styles.productDetails}>
                <View style={styles.productHeader}>
                  <Text style={styles.productName}>{selectedProduct.name}</Text>
                  <View style={[
                    styles.currentStockBadge,
                    selectedProduct.availableStock <= 5 && styles.currentStockBadgeLow
                  ]}>          <Text style={styles.currentStockBadgeText}>
                    {selectedProduct.availableStock} {t('seller_sell.remaining')}
          </Text>
                  </View>
                </View>
                
                <Text style={styles.productCategory}>
                  <Ionicons name="pricetag" size={14} color="#7f8c8d" /> {selectedProduct.category || t('seller_sell.no_category2')}
                </Text>
                
                {/* ✅ Onyesha bei ya kuuzia (au "haijawekwa" ikiwa haipo) */}
                <View style={styles.expectedPriceInfo}>
                  <Ionicons name="cash" size={14} color="#27ae60" />
                  <Text style={styles.expectedPriceInfoText}>
                    {t('seller_sell.sell_price')}{' '}
                    {selectedProduct.expected_selling_price === null || selectedProduct.expected_selling_price === undefined
                      ? <Text style={{ color: '#f39c12' }}>{t('seller_sell.selling_price_not_set')}</Text>
                      : formatCurrency(selectedProduct.expected_selling_price)}
                  </Text>
                </View>
                
                {/* ✅ Kiasi cha Kuongeza */}
                <View style={styles.addToCartSection}>
                  <Text style={styles.quantityLabel}>{t('seller_sell.quantity_label')}</Text>
                  <View style={styles.quantityInputContainer}>
                    <TouchableOpacity 
                      style={styles.quantityButtonSmall}
                      onPress={() => setQuantity(Math.max(1, parseInt(quantity) - 1).toString())}
                    >
                      <Ionicons name="remove" size={18} color="#e74c3c" />
                    </TouchableOpacity>
                    <TextInput
                      style={styles.quantityInput}
                      value={quantity}
                      onChangeText={(value) => setQuantity(value.replace(/[^0-9]/g, ''))}
                      keyboardType="numeric"
                      placeholder="1"
                      maxLength={4}
                    />
                    <TouchableOpacity 
                      style={styles.quantityButtonSmall}
                      onPress={() => {
                        const current = parseInt(quantity) || 0;
                        setQuantity((current + 1).toString());
                      }}
                    >
                      <Ionicons name="add" size={18} color="#2ecc71" />
                    </TouchableOpacity>
                  </View>
                  <TouchableOpacity 
                    style={[
                      styles.addToCartButton,
                      selectedProduct.availableStock === 0 && styles.addToCartButtonDisabled
                    ]}
                    onPress={addToCart}
                    disabled={selectedProduct.availableStock === 0}
                  >
                    <Ionicons name="add-circle" size={18} color="white" />
                    <Text style={styles.addToCartButtonText}>
                      {selectedProduct.availableStock === 0 ? t('seller_sell.no_stock') : t('seller_sell.add_to_cart')}
                    </Text>
                  </TouchableOpacity>
                </View>
                
                <View style={styles.stockInfo}>
                  <Text style={styles.stockInfoText}>
                    <Ionicons name="information-circle" size={14} color="#3498db" />
                    {t('seller_sell.stock_original')}: {selectedProduct.stock} • {t('seller_sell.already_in_cart')}: {getTotalInCartForProduct(selectedProduct.id)} • {t('seller_sell.remaining')}: {selectedProduct.availableStock}
                  </Text>
                </View>
                
                {selectedProduct.description && (
                  <Text style={styles.productDescription}>
                    <Ionicons name="document-text" size={14} color="#3498db" /> {selectedProduct.description}
                  </Text>
                )}
              </View>
            </View>
          )}

          {/* ✅ JINA LA MTEAJA - SI LAZIMA */}
          <View style={styles.inputGroup}>
            <View style={styles.labelRow}>
              <Text style={styles.label}>{t('seller_sell.customer_name')}</Text>
              <View style={styles.optionalBadge}>
                <Text style={styles.optionalText}>{t('seller_sell.optional')}</Text>
              </View>
            </View>
            <TextInput
              style={styles.input}
              placeholder={t('seller_sell.customer_placeholder')}
              value={formData.customer_name}
              onChangeText={(value) => handleInputChange('customer_name', value)}
              placeholderTextColor="#95a5a6"
            />
            {customers.length > 0 && (
              <Text style={styles.customerHint}>
                {'💡 ' + t('seller_sell.customer_hint', { n: customers.length })}
              </Text>
            )}
          </View>

          {/* Namba ya Simu ya Mteja */}
          <View style={styles.inputGroup}>
            <Text style={styles.label}>{t('seller_sell.customer_phone')}</Text>
            <TextInput
              style={styles.input}
              placeholder={t('seller_sell.phone_placeholder')}
              value={formData.customer_phone}
              onChangeText={(value) => handleInputChange('customer_phone', value)}
              keyboardType="phone-pad"
              placeholderTextColor="#95a5a6"
            />
          </View>

          {/* Tarehe ya Mauzo */}
          <View style={styles.inputGroup}>
            <View style={styles.dateHeader}>
              <Text style={styles.label}>{t('seller_sell.sale_date')}</Text>
              <TouchableOpacity onPress={handleTodayDate} style={styles.todayButton}>
                <Ionicons name="today" size={14} color="#3498db" />
                <Text style={styles.todayButtonText}>{t('seller_sell.today')}</Text>
              </TouchableOpacity>
            </View>
            <TextInput
              style={[styles.input, validationErrors.sale_date && styles.inputError]}
              value={formData.sale_date}
              onChangeText={(value) => handleInputChange('sale_date', value)}
              placeholder="YYYY-MM-DD"
              placeholderTextColor="#95a5a6"
            />
            {validationErrors.sale_date && (
              <View style={styles.errorContainer}>
                <Ionicons name="warning" size={16} color="#e74c3c" />
                <Text style={styles.errorText}>{validationErrors.sale_date}</Text>
              </View>
            )}
          </View>

          {/* ✅ Maelekezo */}
          <View style={styles.instructions}>
            <Text style={styles.instructionsTitle}>{t('seller_sell.instructions_title')}</Text>
            <Text style={styles.instructionsText}>
              {t('seller_sell.instruction_1')}{'\n'}
              {t('seller_sell.instruction_2')}{'\n'}
              {t('seller_sell.instruction_3')}{'\n'}
              {t('seller_sell.instruction_4')}{'\n'}
              {t('seller_sell.instruction_5')}{'\n'}
              {t('seller_sell.instruction_6')}{'\n'}
              {t('seller_sell.instruction_7')}
            </Text>
            <Text style={styles.cartNote}>
              {t('seller_sell.cart_note')}
            </Text>
          </View>
        </View>

        <View style={styles.bottomSpacing} />
      </ScrollView>

      {/* FLOATING CART — pinned to the bottom so the cart is always at hand.
          It appears the moment a product is tapped (row 1 = the selected
          product + quantity + Ongeza), so the seller never has to scroll to
          the product panel to keep selling. */}
      {(selectedProduct || cart.length > 0) && (
        <View style={styles.floatingDock}>
          {selectedProduct && (
            <View style={styles.dockRow}>
              <View style={styles.dockProd}>
                <Text style={styles.dockName} numberOfLines={1}>
                  {selectedProduct.name}
                </Text>
                <Text style={styles.dockPrice} numberOfLines={1}>
                  {selectedProduct.expected_selling_price === null || selectedProduct.expected_selling_price === undefined
                    ? t('seller_sell.selling_price_not_set')
                    : formatCurrency(selectedProduct.expected_selling_price)}
                  {' • '}{t('seller_sell.remaining')}: {selectedProduct.availableStock}
                </Text>
              </View>
              <View style={styles.dockQty}>
                <TouchableOpacity
                  style={styles.dockQtyBtn}
                  onPress={() => setQuantity(String(Math.max(1, (parseInt(quantity, 10) || 1) - 1)))}
                >
                  <Ionicons name="remove" size={16} color="#e74c3c" />
                </TouchableOpacity>
                <TextInput
                  style={styles.dockQtyInput}
                  value={quantity}
                  onChangeText={(v) => setQuantity(v.replace(/[^0-9]/g, ''))}
                  keyboardType="numeric"
                  maxLength={4}
                />
                <TouchableOpacity
                  style={styles.dockQtyBtn}
                  onPress={() => setQuantity(String((parseInt(quantity, 10) || 0) + 1))}
                >
                  <Ionicons name="add" size={16} color="#2ecc71" />
                </TouchableOpacity>
              </View>
              <TouchableOpacity
                style={[styles.dockAdd, selectedProduct.availableStock === 0 && styles.dockAddDisabled]}
                onPress={addToCart}
                disabled={selectedProduct.availableStock === 0}
              >
                <Ionicons name="add" size={15} color="white" />
                <Text style={styles.dockAddText}>
                  {selectedProduct.availableStock === 0 ? t('seller_sell.no_stock') : t('seller_sell.add_to_cart')}
                </Text>
              </TouchableOpacity>
            </View>
          )}

          <View style={styles.dockRow}>
            <TouchableOpacity
              style={styles.dockCartInfo}
              onPress={() => setShowCart(true)}
              activeOpacity={0.85}
            >
              <View style={styles.dockCartBadge}>
                <Text style={styles.dockCartBadgeText}>
                  {cart.reduce((sum, item) => sum + item.quantity, 0)}
                </Text>
              </View>
              <Text style={styles.dockCartTotal}>{formatCurrency(calculateCartTotal())}</Text>
              <Text style={styles.dockCartHint} numberOfLines={1}>
                {cart.length} {t('seller_sell.products_count')} • {t('seller_sell.cart_summary')} ›
              </Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.dockCheckout, (loading || cart.length === 0) && styles.dockCheckoutDisabled]}
              onPress={handleSale}
              disabled={loading || cart.length === 0}
            >
              {loading ? (
                <ActivityIndicator color="white" size="small" />
              ) : (
                <>
                  <Ionicons name="checkmark-circle" size={18} color="white" />
                  <Text style={styles.dockCheckoutText}>{t('seller_sell.complete_sale')}</Text>
                </>
              )}
            </TouchableOpacity>
          </View>
        </View>
      )}

      {/* MODAL YA KIKAPU */}
      <Modal
        visible={showCart}
        animationType="slide"
        transparent={true}
        onRequestClose={() => setShowCart(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>{t('seller_sell.cart_title')}</Text>
              <TouchableOpacity onPress={() => setShowCart(false)}>
                <Ionicons name="close" size={24} color="#2c3e50" />
              </TouchableOpacity>
            </View>
            
            {cart.length === 0 ? (
              <View style={styles.emptyCart}>
                <Ionicons name="cart-outline" size={60} color="#bdc3c7" />
                <Text style={styles.emptyCartText}>{t('seller_sell.cart_empty')}</Text>
                <Text style={styles.emptyCartSubtext}>{t('seller_sell.cart_empty_sub')}</Text>
              </View>
            ) : (
              <>
                <FlatList
                  data={cart}
                  renderItem={renderCartItem}
                  keyExtractor={(item) => item.product_id}
                  style={styles.cartList}
                  showsVerticalScrollIndicator={false}
                />
                
                <View style={styles.cartSummary}>
                  <View style={styles.summaryRow}>
                    <Text style={styles.summaryLabel}>{t('seller_sell.cart_products')}</Text>
                    <Text style={styles.summaryValue}>{cart.length}</Text>
                  </View>
                  <View style={styles.summaryRow}>
                    <Text style={styles.summaryLabel}>{t('seller_sell.cart_total_qty')}</Text>
                    <Text style={styles.summaryValue}>
                      {cart.reduce((total, item) => total + item.quantity, 0)}
                    </Text>
                  </View>
                  <View style={styles.divider} />
                  <View style={styles.summaryRow}>
                    <Text style={styles.summaryTotalLabel}>{t('seller_sell.cart_total_pay')}</Text>
                    <Text style={styles.summaryTotal}>
                      {formatCurrency(calculateCartTotal())}
                    </Text>
                  </View>
                </View>
                
                <TouchableOpacity 
                  style={styles.checkoutButton}
                  onPress={() => {
                    setShowCart(false);
                    Alert.alert(
                      t('seller_sell.ready_to_sell_title'),
                      t('seller_sell.ready_to_sell_msg'),
                      [{text: t('app.ok')}]
                    );
                  }}
                >
                  <Text style={styles.checkoutButtonText}>{t('seller_sell.proceed_sale')}</Text>
                </TouchableOpacity>
              </>
            )}
          </View>
        </View>
      </Modal>
    </KeyboardAvoidingView>
      </SafeAreaView>
  );
}

// Styles zinafanana na zilizopo, ziko kwenye code yako ya awali
// Nimeondoa styles ili kuokoa nafasi, lakini zipo sawa na code yako ya awali

const styles = StyleSheet.create({
  screenSafe: {
    flex: 1,
    backgroundColor: '#f8f9fa',
  },
  keyboardAvoid: {
    flex: 1,
  },
  container: {
    flex: 1,
    backgroundColor: '#f8f9fa',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f8f9fa',
    padding: 30,
  },
  loadingText: {
    marginTop: 15,
    fontSize: 18,
    color: '#2c3e50',
    fontWeight: '600',
  },
  loadingSubtext: {
    marginTop: 8,
    fontSize: 14,
    color: '#7f8c8d',
  },
  header: {
    backgroundColor: 'white',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#ecf0f1',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 3,
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
    padding: 5,
  },
  title: {
    fontSize: 22,
    fontWeight: 'bold',
    color: '#2c3e50',
    textAlign: 'center',
    flex: 1,
  },
  refreshButton: {
    padding: 5,
  },
  subtitle: {
    fontSize: 14,
    color: '#7f8c8d',
    marginBottom: 4,
  },
  businessName: {
    fontWeight: 'bold',
    color: '#2ecc71',
    fontSize: 15,
  },
  userInfo: {
    fontSize: 13,
    color: '#95a5a6',
    fontStyle: 'italic',
    marginBottom: 6,
  },
  formContainer: {
    padding: 20,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  label: {
    fontSize: 16,
    fontWeight: '600',
    color: '#2c3e50',
  },
  labelRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 4,
  },
  optionalBadge: {
    backgroundColor: '#f0f0f0',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 4,
  },
  optionalText: {
    fontSize: 10,
    color: '#95a5a6',
    fontStyle: 'italic',
  },
  smallRefreshButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#f8f9fa',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 8,
  },
  refreshText: {
    fontSize: 12,
    color: '#3498db',
    fontWeight: '500',
  },
  errorContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 6,
    padding: 10,
    backgroundColor: '#ffeaea',
    borderRadius: 8,
  },
  errorText: {
    color: '#e74c3c',
    fontSize: 12,
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'white',
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 12,
    paddingHorizontal: 12,
    marginBottom: 12,
  },
  searchIcon: {
    marginRight: 8,
  },
  searchInput: {
    flex: 1,
    paddingVertical: 12,
    fontSize: 14,
    color: '#2c3e50',
  },
  loadingProducts: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 25,
    gap: 10,
    backgroundColor: '#f8f9fa',
    borderRadius: 12,
  },
  loadingProductsText: {
    fontSize: 14,
    color: '#7f8c8d',
    textAlign: 'center',
  },
  noProductsContainer: {
    alignItems: 'center',
    padding: 30,
    backgroundColor: '#f8f9fa',
    borderRadius: 12,
    borderWidth: 2,
    borderColor: '#e9ecef',
    borderStyle: 'dashed',
  },
  noProductsTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginTop: 15,
    marginBottom: 8,
  },
  noProductsText: {
    fontSize: 14,
    color: '#6c757d',
    textAlign: 'center',
    lineHeight: 20,
    marginBottom: 20,
  },
  retryButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#3498db',
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 8,
    gap: 8,
    marginTop: 10,
    marginBottom: 10,
  },
  retryButtonText: {
    color: 'white',
    fontSize: 14,
    fontWeight: 'bold',
  },
  clearSearchButton: {
    padding: 10,
    marginTop: 10,
  },
  clearSearchButtonText: {
    color: '#3498db',
    fontSize: 14,
    fontWeight: '600',
  },
  addProductButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#2ecc71',
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 8,
    gap: 8,
    marginBottom: 10,
  },
  addProductButtonText: {
    color: 'white',
    fontSize: 14,
    fontWeight: 'bold',
  },
  productsList: {
    paddingBottom: 8,
  },
  productItem: {
    backgroundColor: 'white',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#ddd',
    marginBottom: 10,
    padding: 15,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 2,
  },
  productItemSelected: {
    backgroundColor: '#f0f8f0',
    borderColor: '#2ecc71',
    borderWidth: 2,
  },
  productItemOutOfStock: {
    backgroundColor: '#f8f9fa',
    borderColor: '#e9ecef',
    opacity: 0.7,
  },
  productItemContent: {
    flex: 1,
  },
  productItemHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  productName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#2c3e50',
    flex: 1,
    marginRight: 10,
  },
  inCartIndicator: {
    fontSize: 12,
    color: '#3498db',
    fontStyle: 'italic',
  },
  productNameSelected: {
    color: '#27ae60',
    fontWeight: 'bold',
  },
  productNameOutOfStock: {
    color: '#6c757d',
    textDecorationLine: 'line-through',
  },
  productStockBadge: {
    backgroundColor: '#e8f4fd',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 6,
  },
  productStockBadgeLow: {
    backgroundColor: '#fff3cd',
  },
  productStockBadgeOut: {
    backgroundColor: '#f8d7da',
  },
  productStockBadgeText: {
    fontSize: 11,
    fontWeight: '600',
    color: '#3498db',
  },
  productCategory: {
    fontSize: 13,
    color: '#7f8c8d',
    marginBottom: 8,
  },
  productFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  priceColumn: {
    alignItems: 'flex-start',
  },
  expectedPrice: {
    fontSize: 12,
    color: '#27ae60',
    fontWeight: '500',
    marginTop: 2,
  },
  productOwnerBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#f8f9fa',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 6,
  },
  productOwnerBadgeOwn: {
    backgroundColor: '#e8f4fd',
  },
  productOwnerBadgeOther: {
    backgroundColor: '#f3e8ff',
  },
  productOwnerText: {
    fontSize: 11,
    color: '#6c757d',
  },
  selectedIndicator: {
    marginLeft: 10,
  },
  productsCount: {
    fontSize: 12,
    color: '#6c757d',
    textAlign: 'center',
    fontStyle: 'italic',
    marginTop: 10,
  },
  productInfo: {
    backgroundColor: '#e8f4fd',
    padding: 15,
    borderRadius: 12,
    marginBottom: 20,
    borderLeftWidth: 4,
    borderLeftColor: '#3498db',
  },
  productInfoTitle: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 10,
  },
  productDetails: {
    backgroundColor: 'white',
    padding: 15,
    borderRadius: 8,
  },
  productHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  currentStockBadge: {
    backgroundColor: '#e8f4fd',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 8,
  },
  currentStockBadgeLow: {
    backgroundColor: '#fff3cd',
  },
  currentStockBadgeText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#3498db',
  },
  expectedPriceInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 8,
    backgroundColor: '#e8f8f0',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 6,
    alignSelf: 'flex-start',
  },
  expectedPriceInfoText: {
    fontSize: 13,
    color: '#27ae60',
    fontWeight: '500',
  },
  addToCartSection: {
    marginTop: 15,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  quantityLabel: {
    fontSize: 14,
    color: '#2c3e50',
    fontWeight: '600',
  },
  quantityInputContainer: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  quantityButtonSmall: {
    padding: 8,
    backgroundColor: '#f8f9fa',
    borderRadius: 6,
  },
  quantityInput: {
    width: 50,
    textAlign: 'center',
    padding: 8,
    marginHorizontal: 5,
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 6,
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  addToCartButton: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#2ecc71',
    paddingHorizontal: 15,
    paddingVertical: 10,
    borderRadius: 8,
    gap: 8,
  },
  addToCartButtonDisabled: {
    backgroundColor: '#95a5a6',
    opacity: 0.6,
  },
  addToCartButtonText: {
    color: 'white',
    fontSize: 14,
    fontWeight: 'bold',
  },
  stockInfo: {
    marginTop: 10,
    padding: 8,
    backgroundColor: '#f8f9fa',
    borderRadius: 6,
  },
  stockInfoText: {
    fontSize: 12,
    color: '#6c757d',
    fontStyle: 'italic',
  },
  productSellerInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginVertical: 8,
  },
  productSellerInfoText: {
    fontSize: 13,
    color: '#6c757d',
    fontStyle: 'italic',
  },
  inputGroup: {
    marginBottom: 20,
  },
  input: {
    backgroundColor: 'white',
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 12,
    padding: 15,
    fontSize: 16,
    color: '#2c3e50',
    marginTop: 6,
  },
  inputError: {
    borderColor: '#e74c3c',
    backgroundColor: '#fff5f5',
  },
  customerHint: {
    fontSize: 12,
    color: '#3498db',
    marginTop: 6,
    fontStyle: 'italic',
  },
  dateHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  todayButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#e8f4fd',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 8,
  },
  todayButtonText: {
    fontSize: 12,
    color: '#3498db',
    fontWeight: '500',
  },
  productDescription: {
    fontSize: 13,
    color: '#6c757d',
    marginTop: 8,
    fontStyle: 'italic',
  },
  instructions: {
    backgroundColor: '#fff8e1',
    padding: 15,
    borderRadius: 12,
    marginTop: 10,
    borderLeftWidth: 4,
    borderLeftColor: '#f1c40f',
  },
  instructionsTitle: {
    fontSize: 14,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 8,
  },
  instructionsText: {
    fontSize: 13,
    color: '#7f8c8d',
    lineHeight: 20,
  },
  cartNote: {
    fontSize: 12,
    color: '#e67e22',
    fontStyle: 'italic',
    marginTop: 8,
  },
  bottomSpacing: {
    // Room for the two-row floating dock so the last controls are never hidden.
    height: 210,
  },
  // Floating cart dock (fixed to the bottom of the screen, above the list).
  floatingDock: {
    position: 'absolute',
    left: 12,
    right: 12,
    bottom: 12,
    flexDirection: 'column',
    gap: 8,
    backgroundColor: 'white',
    borderRadius: 18,
    padding: 10,
    shadowColor: '#0f172a',
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.22,
    shadowRadius: 14,
    elevation: 10,
  },
  dockRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    flexWrap: 'wrap',
  },
  dockProd: {
    flex: 1,
    minWidth: 120,
  },
  dockName: {
    fontWeight: '700',
    fontSize: 14,
    color: '#2c3e50',
  },
  dockPrice: {
    fontSize: 12,
    color: '#27ae60',
    fontWeight: '600',
    marginTop: 2,
  },
  dockQty: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  dockQtyBtn: {
    width: 32,
    height: 32,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#f3f4f6',
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  dockQtyInput: {
    width: 48,
    textAlign: 'center',
    paddingVertical: 6,
    paddingHorizontal: 4,
    borderWidth: 1,
    borderColor: '#e5e7eb',
    borderRadius: 10,
    fontSize: 15,
    color: '#2c3e50',
  },
  dockAdd: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    backgroundColor: '#2ecc71',
    borderRadius: 12,
    paddingVertical: 11,
    paddingHorizontal: 14,
  },
  dockAddDisabled: {
    backgroundColor: '#95a5a6',
  },
  dockAddText: {
    color: 'white',
    fontWeight: '700',
    fontSize: 13,
  },
  dockCartInfo: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: '#e8f8f0',
    borderRadius: 12,
    paddingVertical: 10,
    paddingHorizontal: 12,
  },
  dockCartBadge: {
    minWidth: 26,
    height: 26,
    borderRadius: 13,
    backgroundColor: '#2ecc71',
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 6,
  },
  dockCartBadgeText: {
    color: 'white',
    fontWeight: '700',
    fontSize: 13,
  },
  dockCartTextWrap: {
    flex: 1,
    minWidth: 0,
  },
  dockCartTotal: {
    color: '#1e8449',
    fontWeight: '800',
    fontSize: 14,
  },
  dockCartHint: {
    color: '#5d6d7e',
    fontSize: 11,
    marginLeft: 'auto',
  },
  dockCheckout: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: '#2ecc71',
    borderRadius: 12,
    paddingVertical: 12,
    paddingHorizontal: 16,
  },
  dockCheckoutDisabled: {
    backgroundColor: '#95a5a6',
  },
  dockCheckoutText: {
    color: 'white',
    fontWeight: '800',
    fontSize: 13,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: 'white',
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    maxHeight: '80%',
    paddingBottom: 20,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#ecf0f1',
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  emptyCart: {
    alignItems: 'center',
    padding: 50,
  },
  emptyCartText: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginTop: 20,
    marginBottom: 10,
  },
  emptyCartSubtext: {
    fontSize: 14,
    color: '#7f8c8d',
    textAlign: 'center',
    lineHeight: 20,
  },
  cartList: {
    maxHeight: 300,
    paddingHorizontal: 20,
  },
  cartItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 15,
    borderBottomWidth: 1,
    borderBottomColor: '#ecf0f1',
  },
  cartItemInfo: {
    flex: 1,
  },
  cartItemName: {
    fontSize: 16,
    fontWeight: '600',
    color: '#2c3e50',
    marginBottom: 4,
  },
  cartItemDetails: {
    fontSize: 14,
    color: '#27ae60',
    marginBottom: 4,
  },
  cartItemStock: {
    fontSize: 12,
    color: '#3498db',
    marginBottom: 4,
    fontStyle: 'italic',
  },
  cartItemSeller: {
    fontSize: 12,
    color: '#95a5a6',
  },
  cartItemActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  quantityControls: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  quantityButton: {
    padding: 5,
    backgroundColor: '#f8f9fa',
    borderRadius: 6,
  },
  quantityText: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    minWidth: 30,
    textAlign: 'center',
  },
  removeButton: {
    backgroundColor: '#e74c3c',
    padding: 8,
    borderRadius: 6,
  },
  cartSummary: {
    padding: 20,
    borderTopWidth: 1,
    borderTopColor: '#ecf0f1',
    backgroundColor: '#f8f9fa',
  },
  summaryRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  summaryLabel: {
    fontSize: 14,
    color: '#7f8c8d',
  },
  summaryValue: {
    fontSize: 14,
    color: '#2c3e50',
    fontWeight: '500',
  },
  summaryTotalLabel: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  summaryTotal: {
    fontSize: 18,
    color: '#27ae60',
    fontWeight: 'bold',
  },
  divider: {
    height: 1,
    backgroundColor: '#ecf0f1',
    marginVertical: 10,
  },
  checkoutButton: {
    backgroundColor: '#2ecc71',
    marginHorizontal: 20,
    marginTop: 10,
    padding: 16,
    borderRadius: 12,
    alignItems: 'center',
  },
  checkoutButtonText: {
    color: 'white',
    fontSize: 16,
    fontWeight: 'bold',
  },
});