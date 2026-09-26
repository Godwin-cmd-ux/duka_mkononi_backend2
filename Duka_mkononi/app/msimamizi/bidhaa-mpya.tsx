import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as ImageManipulator from 'expo-image-manipulator';
import * as ImagePicker from 'expo-image-picker';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    Image,
    Keyboard,
    KeyboardAvoidingView,
    Modal,
    Platform,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    TouchableWithoutFeedback,
    View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLang } from '../../context/LanguageContext';
import LogoutButton from '../../components/logout-button';
import { getCache, setCache } from '../../db/cache';
import { registerLive } from '../../lib/syncer';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';

import { API_BASE_URL } from '../../constants/api';

export default function BidhaaMpyaScreen() {
  const { t } = useLang();
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [loadingProducts, setLoadingProducts] = useState(false);
  const [userData, setUserData] = useState<any>(null);
  const [formData, setFormData] = useState({
    name: '',
    category: '',
    price: '',
    expected_selling_price: '',
    stock: '',
  });
  const [showCustomCategory, setShowCustomCategory] = useState(false);
  const [customCategory, setCustomCategory] = useState('');
  const [existingProducts, setExistingProducts] = useState<any[]>([]);
  const [showProductModal, setShowProductModal] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedProduct, setSelectedProduct] = useState<any>(null);
  const [isOwnerOfSelectedProduct, setIsOwnerOfSelectedProduct] = useState(false);
  const [modalMode, setModalMode] = useState<'add' | 'edit' | 'delete'>('add');
  
  // AI inventory import state
  const [mode, setMode] = useState<'manual' | 'ai'>('manual');
  const [aiImages, setAiImages] = useState<any[]>([]);
  const [processingAI, setProcessingAI] = useState(false);
  const [dotActive, setDotActive] = useState(0);
  const [aiItems, setAiItems] = useState<any[]>([]);
  const [aiMeta, setAiMeta] = useState<any>(null);
  const [verifyingId, setVerifyingId] = useState<string | null>(null);
  const [verifiedIds, setVerifiedIds] = useState<Record<string, boolean>>({});
  const [aiError, setAiError] = useState<string | null>(null);

  const AI_IMPORT_MAX_IMAGES = 6;
  
  // Refs for input focus management
  const nameInputRef = useRef<TextInput>(null);
  const customCategoryInputRef = useRef<TextInput>(null);
  const priceInputRef = useRef<TextInput>(null);
  const expectedPriceInputRef = useRef<TextInput>(null);
  const stockInputRef = useRef<TextInput>(null);
  const searchInputRef = useRef<TextInput>(null);

  // Kategoria za bidhaa - structured with key for translation
  const categoryData = [
    { key: 'vyakula', sw: 'Vyakula' },
    { key: 'vinywaji', sw: 'Vinywaji' },
    { key: 'matunda', sw: 'Matunda' },
    { key: 'mboga', sw: 'Mboga' },
    { key: 'nguo', sw: 'Nguo' },
    { key: 'viatu', sw: 'Viatu' },
    { key: 'vifaa_nyumbani', sw: 'Vifaa vya Nyumbani' },
    { key: 'vifaa_umeme', sw: 'Vifaa vya Umeme' },
    { key: 'simu_na_vifaa', sw: 'Simu na Vifaa' },
    { key: 'matibabu', sw: 'Matibabu' },
    { key: 'vifaa_usafi', sw: 'Vifaa vya Usafi' },
    { key: 'engine', sw: 'Engine' },
    { key: 'sehemu_gari', sw: 'Sehemu za Gari' },
    { key: 'vifaa_ujenzi', sw: 'Vifaa vya Ujenzi' },
    { key: 'vifaa_kilimo', sw: 'Vifaa vya Kilimo' },
    { key: 'vifaa_ofisi', sw: 'Vifaa vya Ofisi' },
    { key: 'vitabu_na_uelimisha', sw: 'Vitabu na Vifaa vya Kuelimisha' },
    { key: 'bidhaa_watoto', sw: 'Bidhaa za Watoto' },
    { key: 'bidhaa_urembo', sw: 'Bidhaa za Urembo' },
    { key: 'bidhaa_kijamii', sw: 'Bidhaa za Kijamii' },
    { key: 'michezo_na_burudani', sw: 'Michezo na Burudani' },
    { key: 'wanyama_wa_kufugwa', sw: 'Wanyama wa Kufugwa' },
    { key: 'vifaa_kusafiri', sw: 'Vifaa vya Kusafiri' },
    { key: 'vifaa_teknolojia', sw: 'Vifaa vya Teknolojia' },
    { key: 'vifaa_kudumisha_usalama', sw: 'Vifaa vya Kudumisha Usalama' },
    { key: 'vifaa_redio_tv', sw: 'Vifaa vya Redio na TV' },
    { key: 'vifaa_muziki', sw: 'Vifaa vya Muziki' },
    { key: 'vifaa_pikipiki', sw: 'Vifaa vya Pikipiki' },
    { key: 'vifaa_baiskeli', sw: 'Vifaa vya Baiskeli' },
    { key: 'vifaa_ushonaji', sw: 'Vifaa vya Ushonaji' },
    { key: 'vifaa_uchoraji', sw: 'Vifaa vya Uchoraji' },
    { key: 'vifaa_ufundi', sw: 'Vifaa vya Ufundi' },
    { key: 'vifaa_umeme_nyumbani', sw: 'Vifaa vya Umeme wa Nyumbani' },
    { key: 'vifaa_jikoni', sw: 'Vifaa vya Jikoni' },
    { key: 'vifaa_kupimia', sw: 'Vifaa vya Kupimia' },
    { key: 'vifaa_kukarabati', sw: 'Vifaa vya Kukarabati' },
    { key: 'vifaa_usalama', sw: 'Vifaa vya Usalama' },
    { key: 'vifaa_biashara', sw: 'Vifaa vya Biashara' },
    { key: 'vifaa_hotelini', sw: 'Vifaa vya Hotelini' },
    { key: 'vifaa_huduma', sw: 'Vifaa vya Huduma' },
    { key: 'vifaa_viwanda', sw: 'Vifaa vya Viwanda' },
    { key: 'nyingine', sw: 'Nyingine' },
  ];
  const categories = categoryData.map(c => c.sw); // Keep for backwards compat
  const getCategoryKey = (swahili: string) => categoryData.find(c => c.sw === swahili)?.key || '';
  const categoryLabel = (swahili: string) => {
    const key = getCategoryKey(swahili);
    return key ? t('categories.' + key) : swahili;
  };

  useEffect(() => {
    loadUserData();
  }, []);

  useEffect(() => {
    if (!userData?.business_name) return;
    const stop = registerLive<any[]>(
      'products',
      async () => {
        const token = await AsyncStorage.getItem('userToken');
        if (!token) throw new Error('sync failed');
        const res = await fetchWithTimeout(`${API_BASE_URL}/api/business/${encodeURIComponent(userData.business_name)}/all-products`, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'ngrok-skip-browser-warning': 'true'
          }
        }, 20000);
        if (!res.ok) throw new Error('sync failed');
        const data = await res.json();
        return data;
      },
      (data) => { setExistingProducts(data); setLoadingProducts(false); }
    );
    return stop;
  }, [userData?.business_name]);

  useEffect(() => {
    if (!processingAI) return;
    const id = setInterval(() => setDotActive(a => (a + 1) % 4), 400);
    return () => clearInterval(id);
  }, [processingAI]);

  // ---------- AI INVENTORY IMPORT ----------
  const compressImageToBase64 = async (uri: string) => {
    try {
      const result = await ImageManipulator.manipulateAsync(
        uri,
        [{ resize: { width: 1200 } }],
        { compress: 0.7, format: ImageManipulator.SaveFormat.JPEG, base64: true }
      );
      return result.base64 || '';
    } catch (error) {
      console.warn('⚠️ Image compression failed:', error);
      return '';
    }
  };

  const addAiImages = useCallback(async (picked: ImagePicker.ImagePickerAsset[]) => {
    const fresh = await Promise.all(
      (picked || []).map(async (asset, index) => ({
        id: `${Date.now()}_${index}`,
        uri: asset.uri,
        name: `image_${Date.now()}_${index}.jpg`,
        base64: '',
      }))
    );
    const withB64 = await Promise.all(
      fresh.map(async (item) => ({ ...item, base64: await compressImageToBase64(item.uri) }))
    );
    const valid = withB64.filter((i) => i.base64);
    setAiImages(prev => {
      const combined = [...prev, ...valid];
      if (combined.length > AI_IMPORT_MAX_IMAGES) {
        Alert.alert(t('app.error'), t('aiImport.error_too_many'));
        return combined.slice(0, AI_IMPORT_MAX_IMAGES);
      }
      return combined;
    });
  }, [t]);

  const pickGalleryImages = useCallback(async () => {
    if (aiImages.length >= AI_IMPORT_MAX_IMAGES) {
      Alert.alert(t('app.error'), t('aiImport.error_too_many'));
      return;
    }
    try {
      const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!permission.granted) {
        Alert.alert(t('app.error'), t('aiImport.permission'));
        return;
      }
      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ['images'],
        allowsMultipleSelection: true,
        selectionLimit: AI_IMPORT_MAX_IMAGES - aiImages.length,
        quality: 0.9,
      });
      if (!result.canceled && result.assets?.length) {
        await addAiImages(result.assets);
      }
    } catch (error) {
      console.warn('⚠️ Gallery error:', error);
    }
  }, [aiImages.length, t, addAiImages]);

  const takeCameraPhoto = useCallback(async () => {
    if (aiImages.length >= AI_IMPORT_MAX_IMAGES) {
      Alert.alert(t('app.error'), t('aiImport.error_too_many'));
      return;
    }
    try {
      const permission = await ImagePicker.requestCameraPermissionsAsync();
      if (!permission.granted) {
        Alert.alert(t('app.error'), t('aiImport.permission'));
        return;
      }
      const result = await ImagePicker.launchCameraAsync({
        mediaTypes: ['images'],
        quality: 0.9,
      });
      if (!result.canceled && result.assets?.length) {
        await addAiImages(result.assets);
      }
    } catch (error) {
      console.warn('⚠️ Camera error:', error);
    }
  }, [aiImages.length, t, addAiImages]);

  const removeAiImage = useCallback((id: string) => {
    setAiImages(prev => prev.filter(img => img.id !== id));
  }, []);

  const resetAI = useCallback(() => {
    setAiImages([]);
    setAiItems([]);
    setAiMeta(null);
    setAiError(null);
    setVerifiedIds({});
  }, []);

  const handleAIError = useCallback((data: any, status: number) => {
    const code = data?.code || '';
    const msg = data?.error || '';
    if (code === 'GEMINI_KEY_MISSING' || code === 'AI_IMPORT_ERROR' && msg?.includes('GEMINI_API_KEY')) {
      setAiError(t('aiImport.error_key'));
    } else if (code === 'GEMINI_RATE_LIMIT') {
      setAiError(t('aiImport.error_rate_limit'));
    } else if (code === 'GEMINI_TIMEOUT' || status === 503) {
      setAiError(t('aiImport.error_timeout'));
    } else if (code === 'NO_IMAGES') {
      setAiError(t('aiImport.error_no_images'));
    } else if (code === 'TOO_MANY_IMAGES') {
      setAiError(t('aiImport.error_too_many'));
    } else if (code === 'IMAGE_TOO_LARGE' || code === 'TOTAL_TOO_LARGE') {
      setAiError(t('aiImport.error_image_large'));
    } else if (code === 'NO_VALID_ITEMS' || status === 422) {
      setAiError(t('aiImport.error_no_valid'));
    } else if (code === 'GEMINI_NETWORK') {
      setAiError(t('aiImport.error_network'));
    } else {
      setAiError(msg || t('aiImport.error_generic'));
    }
  }, [t]);

  const processAIImages = async () => {
    if (aiImages.length === 0 || processingAI) return;
    Keyboard.dismiss();
    if (!(await requireNetwork())) return;

    setProcessingAI(true);
    setAiItems([]);
    setAiMeta(null);
    setAiError(null);
    setVerifiedIds({});
    setDotActive(0);

    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        Alert.alert(t('app.error'), t('aiImport.error_auth'));
        setProcessingAI(false);
        return;
      }

      const payload = {
        images: aiImages.map(img => ({ name: img.name, base64: img.base64 })),
      };

      const response = await fetchWithTimeout(
        `${API_BASE_URL}/api/inventory/ai-import`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'ngrok-skip-browser-warning': 'true',
          },
          body: JSON.stringify(payload),
        },
        180000
      );

      const text = await response.text();
      let data: any = {};
      try { data = JSON.parse(text); } catch (_) {}

      if (!response.ok) {
        handleAIError(data, response.status);
        setProcessingAI(false);
        return;
      }

      if (!data?.success || !Array.isArray(data.items)) {
        setAiError(t('aiImport.error_generic'));
        setProcessingAI(false);
        return;
      }

      // Map server rows to editable display rows.
      const editable = (data.items || []).map((item: any) => ({
        ...item,
        name: String(item.name || ''),
        category: String(item.category || ''),
        quantity: item.quantity !== null && item.quantity !== undefined ? String(item.quantity) : '',
        buyingPrice: item.buyingPrice !== null && item.buyingPrice !== undefined ? String(item.buyingPrice) : '',
        sellingPrice: item.sellingPrice !== null && item.sellingPrice !== undefined ? String(item.sellingPrice) : '',
      }));

      setAiItems(editable);
      setAiMeta(data.meta || null);
      setProcessingAI(false);

      if (editable.length === 0) {
        setAiError(t('aiImport.error_no_valid'));
      }
    } catch (error: any) {
      console.error('❌ Kosa la AI import:', error);
      const isTimeout = error?.name === 'AbortError';
      handleAIError({ code: isTimeout ? 'GEMINI_TIMEOUT' : '' , error: error?.message }, 0);
      setProcessingAI(false);
    }
  };

  const updateAIItem = useCallback((id: string, field: string, value: string) => {
    setAiItems(prev => prev.map(item =>
      item.id === id ? { ...item, [field]: value } : item
    ));
  }, []);

  const verifyAIItem = async (item: any) => {
    if (verifyingId) return;

    if (!String(item.name || '').trim()) {
      Alert.alert(t('app.error'), t('aiImport.error_missing_name'));
      return;
    }
    const qty = Number(item.quantity);
    if (!Number.isInteger(qty) || qty <= 0) {
      Alert.alert(t('app.error'), t('aiImport.error_missing_quantity'));
      return;
    }

    setVerifyingId(item.id);

    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        Alert.alert(t('app.error'), t('aiImport.error_auth'));
        setVerifyingId(null);
        return;
      }

      const payload = {
        item: {
          verificationToken: item.verificationToken,
          action: String(item.status || 'NEW').toUpperCase(),
          name: String(item.name || '').trim(),
          category: String(item.category || '').trim(),
          description: String(item.description || '').trim(),
          quantity: qty,
          buyingPrice: Number(item.buyingPrice) > 0 ? Number(item.buyingPrice) : undefined,
          sellingPrice: Number(item.sellingPrice) > 0 ? Number(item.sellingPrice) : undefined,
          price: Number(item.price) > 0 ? Number(item.price) : undefined,
          matchedExistingItemId: item.matchedExistingItemId || null,
        },
      };

      const response = await fetchWithTimeout(
        `${API_BASE_URL}/api/inventory/ai-import/verify`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'ngrok-skip-browser-warning': 'true',
          },
          body: JSON.stringify(payload),
        },
        60000
      );

      const text = await response.text();
      let data: any = {};
      try { data = JSON.parse(text); } catch (_) {}

      if (!response.ok) {
        if (data?.code === 'PRODUCT_EXISTS') {
          setVerifyingId(null);
          Alert.alert(
            t('app.error'),
            t('aiImport.error_product_exists'),
            [
              { text: t('app.cancel'), style: 'cancel' },
              {
                text: t('aiImport.ok_understand'),
                onPress: () => setAiItems(prev => prev.filter(p => p.id !== item.id)),
              },
            ]
          );
          return;
        }
        if (data?.code === 'MISSING_PRICE') {
          Alert.alert(t('app.error'), t('aiImport.error_missing_price'));
          setVerifyingId(null);
          return;
        }
        Alert.alert(t('app.error'), data?.error || t('aiImport.error_generic'));
        setVerifyingId(null);
        return;
      }

      setVerifiedIds(prev => ({ ...prev, [item.id]: true }));

      const successMsg = String(item.status).toUpperCase() === 'EXISTING'
        ? t('aiImport.verify_success_existing', {
            name: item.name,
            quantity: String(qty),
            total: String((Number(item.currentStock) || 0) + qty),
          })
        : t('aiImport.verify_success_new', { name: item.name });

      Alert.alert(t('app.success'), successMsg);

      if (userData?.business_name) {
        const t2 = await AsyncStorage.getItem('userToken');
        if (t2) fetchExistingProducts(userData.business_name, t2);
      }
    } catch (error: any) {
      console.error('❌ Kosa la verify:', error);
      Alert.alert(t('app.error'), t('aiImport.error_network'));
    } finally {
      setVerifyingId(null);
    }
  };

  const aiStageText = useCallback(() => {
    const cycle = [t('aiImport.stage_extract'), t('aiImport.stage_match'), t('aiImport.stage_interpret')];
    return cycle[dotActive % cycle.length];
  }, [dotActive, t]);

  const loadUserData = async () => {
    try {
      const userToken = await AsyncStorage.getItem('userToken');
      const storedUserData = await AsyncStorage.getItem('userData');
      
      if (!userToken || !storedUserData) {
        Alert.alert(t('app.error'), t('products.error_auth'));
        router.back();
        return;
      }

      const user = JSON.parse(storedUserData);
      setUserData(user);
      
      if (user.business_name) {
        fetchExistingProducts(user.business_name, userToken);
      }
      
    } catch (error) {
      console.error('❌ Kosa wakati wa upakuaji wa data ya mtumiaji:', error);
      Alert.alert(t('app.error'), t('products.error_network'));
    }
  };

  const fetchExistingProducts = async (businessName: string, token: string) => {
    try {
      setLoadingProducts(true);
      
      const cachedProducts = await getCache<any[]>('products');
      if (cachedProducts) { setExistingProducts(cachedProducts); }

      const response = await fetchWithTimeout(
        `${API_BASE_URL}/api/business/${encodeURIComponent(businessName)}/all-products`,
        {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'ngrok-skip-browser-warning': 'true'
          }
        },
        20000
      );

      if (response.ok) {
        const products = await response.json();
        setExistingProducts(products);
        setCache('products', products).catch(() => {});
        console.log(`✅ Bidhaa ${products.length} zimepakuliwa kikamilifu`);
      } else {
        console.warn('⚠️ Imeshindwa kupakua bidhaa zilizopo');
      }
    } catch (error) {
      console.error('❌ Kosa wakati wa kupakua bidhaa zilizopo:', error);
      const cachedProducts = await getCache<any[]>('products');
      if (cachedProducts) { setExistingProducts(cachedProducts); }
    } finally {
      setLoadingProducts(false);
    }
  };

  const handleInputChange = useCallback((field: string, value: string) => {
    setFormData(prev => ({
      ...prev,
      [field]: value
    }));
  }, []);

  const handleCategorySelect = useCallback((category: string) => {
    if (category === 'Nyingine') {
      setShowCustomCategory(true);
      setFormData(prev => ({ ...prev, category: '' }));
      // Focus custom category input after it's rendered
      setTimeout(() => {
        customCategoryInputRef.current?.focus();
      }, 100);
    } else {
      setShowCustomCategory(false);
      setFormData(prev => ({ ...prev, category }));
      handleQuickFill(category);
    }
  }, []);

  const handleCustomCategoryChange = useCallback((value: string) => {
    setCustomCategory(value);
    setFormData(prev => ({ ...prev, category: value }));
  }, []);

  const validateForm = useCallback(() => {
    const errors = [];

    if (!formData.name.trim()) {
      errors.push('• ' + t('products.validate_name'));
    }

    if (!formData.category) {
      errors.push('• ' + t('products.validate_category'));
    }

    if (!formData.price || parseFloat(formData.price) <= 0) {
      errors.push('• ' + t('products.validate_price'));
    }

    if (!formData.expected_selling_price || parseFloat(formData.expected_selling_price) <= 0) {
      errors.push('• ' + t('products.validate_selling_price'));
    }

    if (!formData.stock || parseInt(formData.stock) < 0) {
      errors.push('• ' + t('products.validate_stock'));
    }

    const currentPrice = parseFloat(formData.price || '0');
    const expectedPrice = parseFloat(formData.expected_selling_price || '0');
    
    if (expectedPrice < currentPrice) {
      errors.push('• ' + t('products.validate_price_error'));
    }

    return errors;
  }, [formData]);

  const addStockToProduct = async (product: any, quantityToAdd: number) => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      
      if (!token) {
        Alert.alert(t('app.error'), t('products.error_auth'));
        return false;
      }

      if (!(await requireNetwork())) return false;

      const newStock = parseInt(product.stock || '0') + quantityToAdd;
      
      const updateData = {
        name: product.name,
        category: product.category,
        price: parseFloat(product.price),
        stock: newStock,
        expected_selling_price: parseFloat(product.expected_selling_price || product.price)
      };

      const response = await fetch(`${API_BASE_URL}/api/products/${product.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
          'ngrok-skip-browser-warning': 'true'
        },
        body: JSON.stringify(updateData),
      });

      if (!response.ok) {
        throw new Error(`Server imerudisha ${response.status}`);
      }

      const result = await response.json();
      console.log('✅ Stock updated:', result);
      
      if (userData?.business_name) {
        await fetchExistingProducts(userData.business_name, token);
      }
      
      return true;
    } catch (error) {
      console.error('❌ Kosa wakati wa kuongeza stock:', error);
      Alert.alert(t('app.error'), t('products.error_add'));
      return false;
    }
  };

  const editProduct = async (product: any, updatedData: any) => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      
      if (!token) {
        Alert.alert(t('app.error'), t('products.error_auth'));
        return false;
      }

      if (!(await requireNetwork())) return false;

      const response = await fetch(`${API_BASE_URL}/api/products/${product.id}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
          'ngrok-skip-browser-warning': 'true'
        },
        body: JSON.stringify(updatedData),
      });

      if (!response.ok) {
        throw new Error(`Server imerudisha ${response.status}`);
      }

      const result = await response.json();
      console.log('✅ Product updated:', result);
      
      if (userData?.business_name) {
        await fetchExistingProducts(userData.business_name, token);
      }
      
      return true;
    } catch (error) {
      console.error('❌ Kosa wakati wa kuhariri bidhaa:', error);
      Alert.alert(t('app.error'), t('products.error_edit'));
      return false;
    }
  };

  const deleteProduct = async (product: any) => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      
      if (!token) {
        Alert.alert(t('app.error'), t('products.error_auth'));
        return false;
      }

      if (!(await requireNetwork())) return false;

      const response = await fetch(`${API_BASE_URL}/api/products/${product.id}`, {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
          'ngrok-skip-browser-warning': 'true'
        },
      });

      if (!response.ok) {
        throw new Error(`Server imerudisha ${response.status}`);
      }

      const result = await response.json();
      console.log('✅ Product deleted:', result);
      
      if (userData?.business_name) {
        await fetchExistingProducts(userData.business_name, token);
      }
      
      return true;
    } catch (error) {
      console.error('❌ Kosa wakati wa kufuta bidhaa:', error);
      Alert.alert(t('app.error'), t('products.error_delete'));
      return false;
    }
  };

  const handleAddStock = useCallback((product: any) => {
    Keyboard.dismiss();
    setSelectedProduct(product);
    setIsOwnerOfSelectedProduct(product.seller_id === userData?.id);
    setModalMode('add');
    setFormData({
      name: product.name,
      category: product.category || '',
      price: product.price?.toString() || '',
      expected_selling_price: product.expected_selling_price?.toString() || '',
      stock: '',
    });
    setShowProductModal(false);
    // Focus stock input after state update
    setTimeout(() => {
      stockInputRef.current?.focus();
    }, 100);
  }, [userData]);

  const handleEditProduct = useCallback((product: any) => {
    Keyboard.dismiss();
    setSelectedProduct(product);
    setIsOwnerOfSelectedProduct(product.seller_id === userData?.id);
    
    if (product.seller_id !== userData?.id) {
      Alert.alert(
        t('products.not_authorized_title'),
        t('products.not_authorized')
      );
      return;
    }
    
    setModalMode('edit');
    setFormData({
      name: product.name,
      category: product.category || '',
      price: product.price?.toString() || '',
      expected_selling_price: product.expected_selling_price?.toString() || '',
      stock: product.stock?.toString() || '',
    });
    setShowProductModal(false);
    // Focus name input after state update
    setTimeout(() => {
      nameInputRef.current?.focus();
    }, 100);
  }, [userData]);

  const handleDeleteProduct = useCallback((product: any) => {
    if (product.seller_id !== userData?.id) {
      Alert.alert(
        t('products.not_authorized_title'),
        t('products.not_authorized')
      );
      return;
    }
    
    Alert.alert(
      t('products.confirm_delete'),
      t('products.confirm_delete_text', { name: product.name }),
      [
        { text: t('app.cancel'), style: 'cancel' },
        { 
          text: t('app.delete'), 
          style: 'destructive',
          onPress: async () => {
            const success = await deleteProduct(product);
            if (success) {
              Alert.alert(t('app.success'), t('products.success_delete'));
            }
          }
        }
      ]
    );
  }, [userData]);

  const handleAddProduct = async () => {
    Keyboard.dismiss();
    const validationErrors = validateForm();
    
    if (validationErrors.length > 0) {
      Alert.alert(t('app.error'), validationErrors.join('\n'));
      return;
    }

    setLoading(true);

    try {
      const token = await AsyncStorage.getItem('userToken');
      
      if (!token) {
        Alert.alert(t('app.error'), t('products.error_auth'));
        router.back();
        return;
      }

      if (!(await requireNetwork())) { setLoading(false); return; }

      // Check if we're in add stock mode for existing product
      if (selectedProduct && modalMode === 'add') {
        const quantityToAdd = parseInt(formData.stock || '0');
        
        if (quantityToAdd <= 0) {
          Alert.alert(t('app.error'), t('products.validate_stock'));
          setLoading(false);
          return;
        }
        
        const success = await addStockToProduct(selectedProduct, quantityToAdd);
        
        if (success) {
          Alert.alert(
            t('products.stock_add_success_title'),
            t('products.stock_add_success_message', {name: selectedProduct.name, quantity: String(quantityToAdd)}) + '\n\n' +
            t('products.stock_add_success_stock') + ' ' + String(parseInt(selectedProduct.stock || '0') + quantityToAdd),
            [{ text: t('products.stock_add_success_ok'), onPress: () => resetForm() }]
          );
        }
        setLoading(false);
        return;
      }

      // Check if we're in edit mode
      if (selectedProduct && modalMode === 'edit') {
        const updateData = {
          name: formData.name.trim(),
          category: formData.category,
          price: parseFloat(formData.price),
          stock: parseInt(formData.stock),
          expected_selling_price: parseFloat(formData.expected_selling_price)
        };
        
        const success = await editProduct(selectedProduct, updateData);
        
        if (success) {
          Alert.alert(
            t('products.edit_success_title'),
            t('products.edit_success_message', {name: formData.name}) + '\n\n' +
            t('products.add_success_price') + ' TZS ' + parseInt(formData.price).toLocaleString() + '\n' +
            t('products.add_success_selling') + ' TZS ' + parseInt(formData.expected_selling_price).toLocaleString() + '\n' +
            t('products.add_success_stock') + ' ' + formData.stock,
            [{ text: t('products.add_success_ok'), onPress: () => resetForm() }]
          );
        }
        setLoading(false);
        return;
      }

      // Regular new product addition
      const existingProduct = existingProducts.find(
        p => p.name.toLowerCase() === formData.name.toLowerCase().trim()
      );

      let productData;
      let isUpdate = false;
      
      if (existingProduct && selectedProduct && isOwnerOfSelectedProduct) {
        isUpdate = true;
        productData = {
          name: formData.name.trim(),
          category: formData.category,
          price: parseFloat(formData.price),
          stock: parseInt(formData.stock) + parseInt(existingProduct.stock || '0'),
          expected_selling_price: parseFloat(formData.expected_selling_price)
        };
      } else {
        productData = {
          name: formData.name.trim(),
          category: formData.category,
          price: parseFloat(formData.price),
          stock: parseInt(formData.stock),
          expected_selling_price: parseFloat(formData.expected_selling_price)
        };
      }

      console.log('📦 Sending product data:', productData);
      
      let response;
      if (isUpdate) {
        response = await fetch(`${API_BASE_URL}/api/products/${existingProduct.id}`, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'ngrok-skip-browser-warning': 'true'
          },
          body: JSON.stringify(productData),
        });
      } else {
        response = await fetch(`${API_BASE_URL}/api/products`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'ngrok-skip-browser-warning': 'true'
          },
          body: JSON.stringify(productData),
        });
      }

      const responseText = await response.text();

      if (!response.ok) {
        throw new Error(`Server imerudisha ${response.status}: ${responseText}`);
      }

      const result = JSON.parse(responseText);
      
      Alert.alert(
        t('products.add_success_title'),
        t('products.add_success_message', {name: formData.name}) + '\n\n' +
        t('products.add_success_price') + ' TZS ' + parseInt(formData.price).toLocaleString() + '\n' +
        t('products.add_success_selling') + ' TZS ' + parseInt(formData.expected_selling_price).toLocaleString() + '\n' +
        t('products.add_success_stock') + ' ' + productData.stock,
        [
          { text: t('products.add_success_ok'), onPress: () => resetForm() },
          { text: t('products.add_success_view'), onPress: () => router.back() }
        ]
      );

      if (userData?.business_name) {
        await fetchExistingProducts(userData.business_name, token);
      }

    } catch (error: any) {
      console.error('❌ Kosa wakati wa kuongeza bidhaa:', error);
      
      if (error.message.includes('403')) {
        Alert.alert(
          t('products.conflict_title'),
          t('products.conflict_message', {name: formData.name}) + '\n\n' +
          t('products.conflict_cannot_edit') + '\n\n' +
          t('products.conflict_add_new'),
          [
            { text: t('products.conflict_no'), style: 'cancel' },
            { text: t('products.conflict_yes'), onPress: () => addAsNewProduct() }
          ]
        );
      } else if (error.message.includes('404') || error.message.includes('Failed to fetch')) {
        Alert.alert(
          t('products.connection_error_title'),
          t('products.connection_error_message') + '\n\nURL: ' + API_BASE_URL,
          [
            { text: t('products.connection_back'), onPress: () => router.back() },
            { text: t('products.connection_retry') }
          ]
        );
      } else {
        Alert.alert(t('app.error'), t('products.error_add_new') + ': ' + error.message);
      }
    } finally {
      setLoading(false);
    }
  };

  const addAsNewProduct = async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      
      if (!(await requireNetwork())) { setLoading(false); return; }

      const productData = {
        name: formData.name.trim(),
        category: formData.category,
        price: parseFloat(formData.price),
        stock: parseInt(formData.stock),
        expected_selling_price: parseFloat(formData.expected_selling_price)
      };

      console.log('🆕 Adding as new product:', productData);
      
      const response = await fetch(`${API_BASE_URL}/api/products`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
          'ngrok-skip-browser-warning': 'true'
        },
        body: JSON.stringify(productData),
      });

      const responseText = await response.text();
      
      if (!response.ok) {
        throw new Error(`Server imerudisha ${response.status}: ${responseText}`);
      }

      const result = JSON.parse(responseText);
      
      Alert.alert(
        t('products.add_new_success_title'),
        t('products.add_new_success_message', {name: formData.name}) + '\n\n' +
        t('products.add_new_note'),
        [{ text: t('products.add_success_ok'), onPress: () => resetForm() }]
      );
      
      if (userData?.business_name) {
        await fetchExistingProducts(userData.business_name, token);
      }
    } catch (error) {
      console.error('❌ Kosa wakati wa kuongeza bidhaa mpya:', error);
      Alert.alert(t('app.error'), t('products.error_add_new'));
    }
  };

  const resetForm = useCallback(() => {
    setFormData({
      name: '',
      category: '',
      price: '',
      expected_selling_price: '',
      stock: '',
    });
    setCustomCategory('');
    setShowCustomCategory(false);
    setSelectedProduct(null);
    setIsOwnerOfSelectedProduct(false);
    setModalMode('add');
  }, []);

  const handleQuickFill = useCallback((category: string) => {
    const sampleProducts: { [key: string]: any } = {
      'Vyakula': { name: 'Mchele Super', price: '2500', expected_selling_price: '3000', stock: '50' },
      'Vinywaji': { name: 'Maji ya Kunywa', price: '500', expected_selling_price: '700', stock: '100' },
      'Matunda': { name: 'Maembe Dodo', price: '800', expected_selling_price: '1000', stock: '30' },
      'Mboga': { name: 'Nyanya Fresh', price: '1200', expected_selling_price: '1500', stock: '25' },
      'Nguo': { name: 'T-Shirt Rangi', price: '8000', expected_selling_price: '10000', stock: '15' },
      'Viatu': { name: 'Viatu vya Kawaida', price: '25000', expected_selling_price: '30000', stock: '10' },
    };

    const sample = sampleProducts[category] || { 
      name: '', 
      price: '', 
      expected_selling_price: '', 
      stock: '' 
    };
    
    setFormData(prev => ({
      ...prev,
      name: sample.name || prev.name,
      price: sample.price || prev.price,
      expected_selling_price: sample.expected_selling_price || prev.expected_selling_price,
      stock: sample.stock || prev.stock,
    }));
  }, []);

  const formatCurrency = useCallback((amount: string) => {
    const num = parseInt(amount || '0');
    return `TZS ${num.toLocaleString()}`;
  }, []);

  const calculatePriceDifference = useCallback(() => {
    const currentPrice = parseFloat(formData.price || '0');
    const expectedPrice = parseFloat(formData.expected_selling_price || '0');
    
    if (currentPrice > 0 && expectedPrice > 0) {
      const difference = expectedPrice - currentPrice;
      const percentage = (difference / currentPrice) * 100;
      
      return {
        difference,
        percentage,
        formattedDifference: `${difference >= 0 ? '+' : ''}TZS ${Math.abs(difference).toLocaleString()}`,
        formattedPercentage: `${percentage >= 0 ? '+' : ''}${percentage.toFixed(2)}%`
      };
    }
    return null;
  }, [formData.price, formData.expected_selling_price]);

  const openProductModal = useCallback(() => {
    Keyboard.dismiss();
    setShowProductModal(true);
  }, []);

  const selectExistingProduct = useCallback((product: any) => {
    const currentUserId = userData?.id;
    const isOwner = product.seller_id === currentUserId;
    
    setSelectedProduct(product);
    setIsOwnerOfSelectedProduct(isOwner);
    setModalMode('add');
    
    setFormData({
      name: product.name,
      category: product.category || '',
      price: product.price?.toString() || '',
      expected_selling_price: product.expected_selling_price?.toString() || '',
      stock: '',
    });
    
    setShowProductModal(false);
    
    // Focus stock input after selection
    setTimeout(() => {
      stockInputRef.current?.focus();
    }, 100);
  }, [userData]);

  const filteredProducts = existingProducts.filter(product =>
    product.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    product.category?.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const renderProductItem = useCallback((product: any) => {
    const isOwner = product.seller_id === userData?.id;
    
    return (
      <View key={product.id} style={[styles.productItem, isOwner && styles.ownerProductItem]}>
        <TouchableOpacity 
          style={styles.productItemContent}
          onPress={() => selectExistingProduct(product)}
          activeOpacity={0.7}
        >
          <View style={styles.productItemInfo}>
            <View style={styles.productItemHeader}>
              <Text style={styles.productItemName}>
                {product.name}
              </Text>
              {isOwner && (
                <View style={styles.ownerBadge}>
                  <Text style={styles.ownerBadgeText}>{t('products.product_owner_badge')}</Text>
                </View>
              )}
            </View>
            <Text style={styles.productItemCategory}>
              {product.category || t('products.product_unknown_category')}
            </Text>
            <View style={styles.productItemDetails}>
              <Text style={styles.productItemStock}>
                {t('products.product_stock_label')} {product.stock || '0'}
              </Text>
              <Text style={styles.productItemPrice}>
                {t('products.product_price_label')} {formatCurrency(product.price?.toString() || '0')}
              </Text>
            </View>
            {product.expected_selling_price && (
              <Text style={styles.productItemExpectedPrice}>
                {t('products.product_selling_price_label')} {formatCurrency(product.expected_selling_price.toString())}
              </Text>
            )}
          </View>
          
          <View style={styles.productActions}>
            <TouchableOpacity
              style={styles.actionButton}
              onPress={(e) => {
                e.stopPropagation();
                handleAddStock(product);
              }}
            >
              <Ionicons name="add-circle" size={28} color="#2ecc71" />
            </TouchableOpacity>
            
            {isOwner && (
              <>
                <TouchableOpacity
                  style={styles.actionButton}
                  onPress={(e) => {
                    e.stopPropagation();
                    handleEditProduct(product);
                  }}
                >
                  <Ionicons name="create-outline" size={28} color="#3498db" />
                </TouchableOpacity>
                
                <TouchableOpacity
                  style={styles.actionButton}
                  onPress={(e) => {
                    e.stopPropagation();
                    handleDeleteProduct(product);
                  }}
                >
                  <Ionicons name="trash-outline" size={28} color="#e74c3c" />
                </TouchableOpacity>
              </>
            )}
          </View>
        </TouchableOpacity>
      </View>
    );
  }, [userData, formatCurrency, selectExistingProduct, handleAddStock, handleEditProduct, handleDeleteProduct]);

  const renderExistingProductSelector = useCallback(() => (
    <View style={styles.existingProductsContainer}>
      <TouchableOpacity 
        style={styles.existingProductsButton}
        onPress={openProductModal}
        activeOpacity={0.8}
      >
        <View style={styles.existingProductsButtonContent}>
          <Ionicons name="list" size={24} color="#2ecc71" />
          <View style={styles.existingProductsButtonTextContainer}>                  <Text style={styles.existingProductsButtonTitle}>
                    {t('products.existing_title')}
                  </Text>                  <Text style={styles.existingProductsButtonSubtitle}>
                    {t('products.existing_subtitle')}
                  </Text>
          </View>
          <Ionicons name="chevron-forward" size={24} color="#95a5a6" />
        </View>
      </TouchableOpacity>

      {selectedProduct && modalMode !== 'add' && (
        <View style={[
          styles.selectedProductCard,
          isOwnerOfSelectedProduct ? styles.ownerProductCard : styles.nonOwnerProductCard
        ]}>
          <View style={styles.selectedProductHeader}>
            <Text style={styles.selectedProductTitle}>
              {modalMode === 'edit' ? t('products.page_title_edit') : t('products.button_add_stock')}
            </Text>
            <TouchableOpacity onPress={() => {
              setSelectedProduct(null);
              setIsOwnerOfSelectedProduct(false);
              setModalMode('add');
            }}>
              <Ionicons name="close-circle" size={24} color="#e74c3c" />
            </TouchableOpacity>
          </View>
          
          <View style={styles.selectedProductInfo}>
            <Text style={styles.selectedProductName}>
              {selectedProduct.name}
            </Text>
            <View style={styles.selectedProductDetails}>
              <Text style={styles.selectedProductDetail}>
                {t('products.preview_category')} {selectedProduct.category || t('products.product_unknown_category')}
              </Text>
              <Text style={styles.selectedProductDetail}>
                {t('products.selected_product_owner')} {selectedProduct.seller_name || t('products.selected_product_other')}
              </Text>
              <Text style={styles.selectedProductDetail}>
                {t('products.selected_product_current_stock')} {selectedProduct.stock || '0'}
              </Text>
              <Text style={styles.selectedProductDetail}>
                {t('products.selected_product_current_price')} {formatCurrency(selectedProduct.price?.toString() || '0')}
              </Text>
              {selectedProduct.expected_selling_price && (
                <Text style={styles.selectedProductDetail}>
                  {t('products.selected_product_selling_price')} {formatCurrency(selectedProduct.expected_selling_price.toString())}
                </Text>
              )}
            </View>
          </View>
        </View>
      )}
    </View>
  ), [openProductModal, selectedProduct, modalMode, isOwnerOfSelectedProduct, formatCurrency]);

  const renderProductModal = useCallback(() => (
    <Modal
      visible={showProductModal}
      animationType="slide"
      transparent={true}
      onRequestClose={() => setShowProductModal(false)}
    >
      <TouchableWithoutFeedback onPress={() => setShowProductModal(false)}>
        <View style={styles.modalContainer}>
          <TouchableWithoutFeedback onPress={(e) => e.stopPropagation()}>
            <View style={styles.modalContent}>
              <View style={styles.modalHeader}>
                <Text style={styles.modalTitle}>{t('products.modal_title')}</Text>
                <TouchableOpacity 
                  onPress={() => setShowProductModal(false)}
                  style={styles.modalCloseButton}
                >
                  <Ionicons name="close" size={28} color="#2c3e50" />
                </TouchableOpacity>
              </View>

              <TextInput
                ref={searchInputRef}
                style={styles.modalSearchInput}
                placeholder={t('products.modal_search')}
                value={searchQuery}
                onChangeText={setSearchQuery}
                placeholderTextColor="#95a5a6"
              />

              {loadingProducts ? (
                <View style={styles.modalLoadingContainer}>
                  <ActivityIndicator size="large" color="#2ecc71" />
                  <Text style={styles.modalLoadingText}>{t('products.modal_loading')}</Text>
                </View>
              ) : (
                <ScrollView 
                  style={styles.productsList}
                  keyboardShouldPersistTaps="handled"
                  showsVerticalScrollIndicator={false}
                >
                  {filteredProducts.length > 0 ? (
                    filteredProducts.map(product => renderProductItem(product))
                  ) : (
                    <View style={styles.noProductsContainer}>
                      <Ionicons name="archive-outline" size={64} color="#bdc3c7" />
                      <Text style={styles.noProductsText}>
                        {t('products.modal_no_products')}
                      </Text>
                      <Text style={styles.noProductsSubtext}>
                        {t('products.modal_no_products_sub')}
                      </Text>
                    </View>
                  )}
                </ScrollView>
              )}
            </View>
          </TouchableWithoutFeedback>
        </View>
      </TouchableWithoutFeedback>
    </Modal>
  ), [showProductModal, searchQuery, loadingProducts, filteredProducts, renderProductItem]);

  if (!userData) {
    return (
      <SafeAreaView style={styles.screenSafe} edges={['top']}>
        <View style={styles.loadingContainer}>
          <ActivityIndicator size="large" color="#2ecc71" />
          <Text style={styles.loadingText}>{t('products.modal_loading')}</Text>
        </View>
      </SafeAreaView>
    );
  }

  const priceDiff = calculatePriceDifference();
  const isFormValid = formData.name && formData.category && formData.price && 
                      formData.expected_selling_price && formData.stock;

  // Determine if price fields should be editable
  const isPriceEditable = () => {
    // If no product is selected (adding new product) - editable
    if (!selectedProduct) return true;
    // If editing own product - editable
    if (modalMode === 'edit' && isOwnerOfSelectedProduct) return true;
    // If adding stock - not editable
    if (modalMode === 'add' && selectedProduct) return false;
    // Default - not editable
    return false;
  };

  return (
    <SafeAreaView style={styles.screenSafe} edges={['top']}>
    <KeyboardAvoidingView 
      style={styles.keyboardAvoid}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      keyboardVerticalOffset={Platform.OS === 'ios' ? 64 : 0}
    >
      <TouchableWithoutFeedback onPress={Keyboard.dismiss}>
        <ScrollView 
          style={styles.container} 
          showsVerticalScrollIndicator={false}
          keyboardShouldPersistTaps="handled"
          contentContainerStyle={styles.scrollContent}
        >
          {/* Header Section */}
          <View style={styles.header}>
            <View style={styles.headerTop}>
              <TouchableOpacity onPress={() => router.back()} style={styles.backButton}>
                <Ionicons name="arrow-back" size={24} color="#2c3e50" />
              </TouchableOpacity>
              <Text style={styles.title}>
                {modalMode === 'edit' ? t('products.page_title_edit') : t('products.page_title')}
              </Text>
              <LogoutButton iconOnly />
            </View>
            
<Text style={styles.subtitle}>
              {t('products.business_label')} <Text style={styles.businessName}>{userData.businessName || userData.business_name || t('products.product_unknown_category')}</Text>
            </Text>
<Text style={styles.userInfo}>
              {t('products.user_label')} {userData.fullName || userData.full_name || userData.email}
            </Text>
          </View>

          {/* Form Section */}
          <View style={styles.formContainer}>

            {/* Mode toggle: manual vs AI import */}
            <View style={styles.modeToggle}>
              <TouchableOpacity
                style={[styles.modeCard, mode === 'manual' && styles.modeCardActive]}
                onPress={() => setMode('manual')}
                activeOpacity={0.8}
              >
                <View style={[styles.modeIconCircle, mode === 'manual' && styles.modeIconCircleActive]}>
                  <Ionicons name="create-outline" size={26} color={mode === 'manual' ? '#ffffff' : '#2ecc71'} />
                </View>
                <Text style={[styles.modeCardTitle, mode === 'manual' && styles.modeCardTitleActive]}>
                  {t('aiImport.mode_manual')}
                </Text>
                <Text style={styles.modeCardDesc}>{t('aiImport.mode_manual_desc')}</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={[styles.modeCard, mode === 'ai' && styles.modeCardActive]}
                onPress={() => setMode('ai')}
                activeOpacity={0.8}
              >
                <View style={[styles.modeIconCircle, mode === 'ai' && styles.modeIconCircleActive]}>
                  <Ionicons name="sparkles" size={26} color={mode === 'ai' ? '#ffffff' : '#8e44ad'} />
                </View>
                <Text style={[styles.modeCardTitle, mode === 'ai' && styles.modeCardTitleActive]}>
                  {t('aiImport.mode_ai')}
                </Text>
                <Text style={styles.modeCardDesc}>{t('aiImport.mode_ai_desc')}</Text>
              </TouchableOpacity>
            </View>

            {mode === 'ai' && (
              <View style={styles.aiPanel}>
                <View style={styles.aiHowBox}>
                  <Text style={styles.aiHowTitle}>{t('aiImport.how_title')}</Text>
                  <Text style={styles.aiHowStep}>{t('aiImport.how_1')}</Text>
                  <Text style={styles.aiHowStep}>{t('aiImport.how_2')}</Text>
                  <Text style={styles.aiHowStep}>{t('aiImport.how_3')}</Text>
                  <Text style={styles.aiHowStep}>{t('aiImport.how_4')}</Text>
                </View>

                <View style={styles.aiPickRow}>
                  <TouchableOpacity style={styles.aiPickButton} onPress={pickGalleryImages} activeOpacity={0.8}>
                    <Ionicons name="images-outline" size={24} color="#2ecc71" />
                    <Text style={styles.aiPickButtonText}>{t('aiImport.library_button')}</Text>
                  </TouchableOpacity>
                  <TouchableOpacity style={styles.aiPickButton} onPress={takeCameraPhoto} activeOpacity={0.8}>
                    <Ionicons name="camera-outline" size={24} color="#3498db" />
                    <Text style={styles.aiPickButtonText}>{t('aiImport.camera_button')}</Text>
                  </TouchableOpacity>
                </View>

                {aiImages.length > 0 && (
                  <>
                    <Text style={styles.aiImagesHint}>
                      {t('aiImport.images_selected', {count: String(aiImages.length)})}
                    </Text>
                    <ScrollView
                      horizontal
                      showsHorizontalScrollIndicator={false}
                      style={styles.aiThumbsRow}
                      contentContainerStyle={styles.aiThumbsContent}
                    >
                      {aiImages.map((img) => (
                        <View key={img.id} style={styles.aiThumbWrap}>
                          <Image source={{ uri: img.uri }} style={styles.aiThumb} />
                          <TouchableOpacity
                            style={styles.aiThumbRemove}
                            onPress={() => removeAiImage(img.id)}
                          >
                            <Ionicons name="close" size={16} color="#ffffff" />
                          </TouchableOpacity>
                        </View>
                      ))}
                    </ScrollView>
                  </>
                )}

                {aiImages.length === 0 && !processingAI && (
                  <Text style={styles.aiNoImages}>{t('aiImport.no_images')}</Text>
                )}

                {aiError && !processingAI && (
                  <View style={styles.aiErrorBox}>
                    <Ionicons name="alert-circle-outline" size={20} color="#e74c3c" />
                    <Text style={styles.aiErrorText}>{aiError}</Text>
                  </View>
                )}

                <TouchableOpacity
                  style={[
                    styles.aiProcessButton,
                    (aiImages.length === 0 || processingAI) && styles.aiProcessButtonDisabled,
                  ]}
                  onPress={processAIImages}
                  disabled={aiImages.length === 0 || processingAI}
                  activeOpacity={0.8}
                >
                  {processingAI ? (
                    <ActivityIndicator color="white" />
                  ) : (
                    <View style={styles.submitButtonContent}>
                      <Ionicons name="sparkles" size={24} color="white" />
                      <Text style={styles.aiProcessButtonText}>{t('aiImport.process_button')}</Text>
                    </View>
                  )}
                </TouchableOpacity>

                {processingAI && (
                  <View style={styles.aiProgress}>
                    <View style={styles.aiDotsRow}>
                      {[0, 1, 2, 3].map(i => (
                        <View key={i} style={[styles.aiDot, dotActive === i && styles.aiDotActive]} />
                      ))}
                    </View>
                    <Text style={styles.aiProgressText}>{aiStageText()}</Text>
                    <Text style={styles.aiProgressNote}>{t('aiImport.processing_note')}</Text>
                  </View>
                )}

                {aiItems.length > 0 && (
                  <View style={styles.aiResultsHeader}>
                    <Text style={styles.aiResultsTitle}>
                      {t('aiImport.results_title')} ({aiItems.length})
                    </Text>
                    <Text style={styles.aiDisclaimer}>{t('aiImport.disclaimer')}</Text>
                  </View>
                )}

                {aiItems.map((item) => {
                  const isVerified = !!verifiedIds[item.id];
                  const isExisting = String(item.status).toUpperCase() === 'EXISTING';
                  return (
                    <View key={item.id} style={[styles.aiItemCard, isVerified && styles.aiItemCardVerified]}>
                      <View style={styles.aiItemTop}>
                        <View style={styles.aiBadgeRow}>
                          {isExisting ? (
                            <View style={[styles.aiBadge, styles.aiBadgeExisting]}>
                              <Text style={styles.aiBadgeText}>{t('aiImport.badge_existing')}</Text>
                            </View>
                          ) : (
                            <View style={[styles.aiBadge, styles.aiBadgeNew]}>
                              <Text style={styles.aiBadgeText}>{t('aiImport.badge_new')}</Text>
                            </View>
                          )}
                          {item.needsReview && (
                            <View style={[styles.aiBadge, styles.aiBadgeReview]}>
                              <Text style={styles.aiBadgeText}>{t('aiImport.badge_review')}</Text>
                            </View>
                          )}
                          {isVerified && (
                            <View style={[styles.aiBadge, styles.aiBadgeOk]}>
                              <Text style={[styles.aiBadgeText, styles.aiBadgeOkText]}>{t('aiImport.verified')}</Text>
                            </View>
                          )}
                        </View>
                        <Text style={styles.aiConfidence}>
                          {Math.round((Number(item.confidence) || 0) * 100)}%
                        </Text>
                      </View>

                      {isExisting && item.currentStock !== null && item.currentStock !== undefined && (
                        <Text style={styles.aiCurrentStock}>
                          {t('aiImport.current_stock', {current: String(item.currentStock)})}
                        </Text>
                      )}

                      <View style={styles.aiField}>
                        <Text style={styles.aiLabel}>{t('aiImport.col_name')}</Text>
                        <TextInput
                          style={styles.aiInput}
                          value={item.name}
                          onChangeText={(v) => updateAIItem(item.id, 'name', v)}
                          placeholderTextColor="#95a5a6"
                        />
                      </View>

                      <View style={styles.aiField}>
                        <Text style={styles.aiLabel}>{t('aiImport.col_category')}</Text>
                        <TextInput
                          style={styles.aiInput}
                          value={item.category}
                          onChangeText={(v) => updateAIItem(item.id, 'category', v)}
                          placeholderTextColor="#95a5a6"
                        />
                      </View>

                      <View style={styles.aiRowTwo}>
                        <View style={[styles.aiField, styles.aiFlex]}>
                          <Text style={styles.aiLabel}>{t('aiImport.col_quantity')}</Text>
                          <TextInput
                            style={styles.aiInput}
                            value={item.quantity}
                            onChangeText={(v) => updateAIItem(item.id, 'quantity', v.replace(/[^0-9]/g, ''))}
                            keyboardType="numeric"
                            placeholderTextColor="#95a5a6"
                          />
                        </View>
                        <View style={[styles.aiField, styles.aiFlex]}>
                          <Text style={styles.aiLabel}>{t('aiImport.col_selling')}</Text>
                          <TextInput
                            style={styles.aiInput}
                            value={item.sellingPrice}
                            onChangeText={(v) => updateAIItem(item.id, 'sellingPrice', v.replace(/[^0-9]/g, ''))}
                            keyboardType="numeric"
                            placeholderTextColor="#95a5a6"
                          />
                        </View>
                      </View>

                      <View style={styles.aiField}>
                        <Text style={styles.aiLabel}>{t('aiImport.col_buying')}</Text>
                        <TextInput
                          style={styles.aiInput}
                          value={item.buyingPrice}
                          onChangeText={(v) => updateAIItem(item.id, 'buyingPrice', v.replace(/[^0-9]/g, ''))}
                          keyboardType="numeric"
                          placeholderTextColor="#95a5a6"
                        />
                      </View>

                      {item.sourceImage ? (
                        <Text style={styles.aiSource}>{t('aiImport.source', {name: item.sourceImage})}</Text>
                      ) : null}

                      {Array.isArray(item.warnings) && item.warnings.length > 0 && (
                        <View style={styles.aiWarnings}>
                          {item.warnings.slice(0, 4).map((w: string, wi: number) => (
                            <Text key={wi} style={styles.aiWarningText}>• {w}</Text>
                          ))}
                        </View>
                      )}

                      <TouchableOpacity
                        style={[
                          styles.aiVerifyButton,
                          isVerified && styles.aiVerifyButtonDone,
                          verifyingId === item.id && styles.aiVerifyButtonBusy,
                        ]}
                        onPress={() => verifyAIItem(item)}
                        disabled={isVerified || !!verifyingId}
                        activeOpacity={0.8}
                      >
                        {verifyingId === item.id ? (
                          <ActivityIndicator color="white" />
                        ) : (
                          <View style={styles.submitButtonContent}>
                            <Ionicons
                              name={isVerified ? 'checkmark-circle' : 'shield-checkmark-outline'}
                              size={22}
                              color="white"
                            />
                            <Text style={styles.aiVerifyButtonText}>
                              {isVerified ? t('aiImport.verified') : t('aiImport.verify_button')}
                            </Text>
                          </View>
                        )}
                      </TouchableOpacity>
                    </View>
                  );
                })}

                {aiItems.length > 0 && (
                  <TouchableOpacity style={styles.aiClearButton} onPress={resetAI} activeOpacity={0.8}>
                    <Ionicons name="refresh" size={20} color="#7f8c8d" />
                    <Text style={styles.aiClearButtonText}>{t('aiImport.clear_button')}</Text>
                  </TouchableOpacity>
                )}
              </View>
            )}

            {mode === 'manual' && (
              <>
            {renderExistingProductSelector()}

            {/* Product Name */}
            <View style={styles.inputGroup}>
              <Text style={styles.label}>{t('products.name_label')}</Text>
              <TextInput
                ref={nameInputRef}
                style={styles.input}
                placeholder={t('products.name_placeholder')}
                value={formData.name}
                onChangeText={(value) => handleInputChange('name', value)}
                placeholderTextColor="#95a5a6"
                editable={!selectedProduct || modalMode === 'edit'}
              />
            </View>

            {/* Category */}
            <View style={styles.inputGroup}>
              <Text style={styles.label}>{t('products.category_label')}</Text>
              
              <ScrollView 
                horizontal 
                showsHorizontalScrollIndicator={false}
                style={styles.categoriesContainer}
                contentContainerStyle={styles.categoriesContent}
                keyboardShouldPersistTaps="handled"
              >
                {categoryData.map((cat) => (
                  <TouchableOpacity
                    key={cat.sw}
                    style={[
                      styles.categoryChip,
                      formData.category === cat.sw && styles.categoryChipSelected,
                      cat.sw === 'Nyingine' && showCustomCategory && styles.categoryChipSelected
                    ]}
                    onPress={() => handleCategorySelect(cat.sw)}
                  >
                    <Text style={[
                      styles.categoryText,
                      formData.category === cat.sw && styles.categoryTextSelected,
                      cat.sw === 'Nyingine' && showCustomCategory && styles.categoryTextSelected
                    ]}>
                      {categoryLabel(cat.sw)}
                    </Text>
                  </TouchableOpacity>
                ))}
              </ScrollView>
              
              {showCustomCategory && (
                <View style={styles.customCategoryContainer}>
                  <Text style={styles.label}>{t('products.custom_category_label')}</Text>
                  <TextInput
                    ref={customCategoryInputRef}
                    style={styles.input}
                    placeholder={t('products.custom_category_placeholder')}
                    value={customCategory}
                    onChangeText={handleCustomCategoryChange}
                    placeholderTextColor="#95a5a6"
                  />
                </View>
              )}

              {formData.category && !showCustomCategory && (
                <Text style={styles.selectedCategory}>
                  {t('products.category_selected')} <Text style={styles.selectedCategoryText}>{categoryLabel(formData.category)}</Text>
                </Text>
              )}
            </View>

            {/* Current Price */}
            <View style={styles.inputGroup}>
              <Text style={styles.label}>{t('products.price_label')}</Text>
              <View style={styles.priceContainer}>
                <TextInput
                  ref={priceInputRef}
                  style={[styles.input, styles.priceInput]}
                  placeholder={t('products.price_placeholder')}
                  value={formData.price}
                  onChangeText={(value) => handleInputChange('price', value.replace(/[^0-9]/g, ''))}
                  keyboardType="numeric"
                  placeholderTextColor="#95a5a6"
                  editable={isPriceEditable()}
                />
                {formData.price ? (
                  <View style={styles.currencyPreview}>
                    <Text style={styles.currencyText}>TZS</Text>
                    <Text style={[styles.pricePreview, styles.currentPrice]}>
                      {parseInt(formData.price || '0').toLocaleString()}
                    </Text>
                  </View>
                ) : null}
              </View>
              {!isPriceEditable() && selectedProduct && (
                <Text style={styles.readOnlyHint}>
                  {t('products.price_readonly_hint')}
                </Text>
              )}
            </View>

            {/* Expected Selling Price */}
            <View style={styles.inputGroup}>
              <Text style={styles.label}>{t('products.expected_price_label')}</Text>
              <View style={styles.priceContainer}>
                <TextInput
                  ref={expectedPriceInputRef}
                  style={[styles.input, styles.priceInput]}
                  placeholder={t('products.expected_price_placeholder')}
                  value={formData.expected_selling_price}
                  onChangeText={(value) => handleInputChange('expected_selling_price', value.replace(/[^0-9]/g, ''))}
                  keyboardType="numeric"
                  placeholderTextColor="#95a5a6"
                  editable={isPriceEditable()}
                />
                {formData.expected_selling_price ? (
                  <View style={styles.currencyPreview}>
                    <Text style={styles.currencyText}>TZS</Text>
                    <Text style={[styles.pricePreview, styles.expectedPrice]}>
                      {parseInt(formData.expected_selling_price || '0').toLocaleString()}
                    </Text>
                  </View>
                ) : null}
              </View>
              {!isPriceEditable() && selectedProduct && (
                <Text style={styles.readOnlyHint}>
                  {t('products.price_readonly_hint')}
                </Text>
              )}
            </View>

            {/* Price Difference Indicator */}
            {priceDiff && (
              <View style={[
                styles.priceDiffContainer,
                priceDiff.difference >= 0 ? styles.priceDiffPositive : styles.priceDiffNegative
              ]}>
                <Text style={styles.priceDiffTitle}>
                  {priceDiff.difference >= 0 ? t('products.price_diff_title') : t('products.price_diff_title_negative')}
                </Text>
                <View style={styles.priceDiffRow}>
                  <Text style={styles.priceDiffLabel}>{t('products.price_diff_label')}</Text>
                  <Text style={[
                    styles.priceDiffValue,
                    priceDiff.difference >= 0 ? styles.diffPositive : styles.diffNegative
                  ]}>
                    {priceDiff.formattedDifference}
                  </Text>
                </View>
                <View style={styles.priceDiffRow}>
                  <Text style={styles.priceDiffLabel}>{t('products.price_diff_percent_label')}</Text>
                  <Text style={[
                    styles.priceDiffValue,
                    priceDiff.percentage >= 0 ? styles.diffPositive : styles.diffNegative
                  ]}>
                    {priceDiff.formattedPercentage}
                  </Text>
                </View>
              </View>
            )}

            {/* Stock Quantity */}
            <View style={styles.inputGroup}>
              <Text style={styles.label}>
                {modalMode === 'edit' ? t('products.stock_edit_label') : 
                 selectedProduct && modalMode === 'add' ? t('products.stock_add_label') : 
                 t('products.stock_label')}
              </Text>
              <TextInput
                ref={stockInputRef}
                style={styles.input}
                placeholder={selectedProduct && modalMode === 'add' ? t('products.stock_add_placeholder') : t('products.stock_placeholder')}
                value={formData.stock}
                onChangeText={(value) => handleInputChange('stock', value.replace(/[^0-9]/g, ''))}
                keyboardType="numeric"
                placeholderTextColor="#95a5a6"
              />
              {selectedProduct && modalMode === 'add' && (
                <Text style={styles.currentStockInfo}>
                  {t('products.stock_current_info', {current: selectedProduct.stock || '0', add: formData.stock || '0', total: String(parseInt(selectedProduct.stock || '0') + parseInt(formData.stock || '0'))})}
                </Text>
              )}
              {selectedProduct && modalMode === 'edit' && (
                <Text style={styles.currentStockInfo}>
                  {t('products.stock_edit_info', {new: formData.stock || '0', old: selectedProduct.stock || '0'})}
                </Text>
              )}
            </View>

            {/* Submit Button */}
            <TouchableOpacity 
              style={[
                styles.submitButton,
                modalMode === 'edit' ? styles.updateButton : 
                (selectedProduct && modalMode === 'add') ? styles.addStockButton : styles.addButton,
                !isFormValid && styles.submitButtonDisabled
              ]}
              onPress={handleAddProduct}
              disabled={loading || !isFormValid}
              activeOpacity={0.8}
            >
              {loading ? (
                <ActivityIndicator color="white" />
              ) : (
                <View style={styles.submitButtonContent}>
                  <Ionicons 
                    name={
                      modalMode === 'edit' ? "refresh-circle" : 
                      (selectedProduct && modalMode === 'add') ? "add-circle" : 
                      "add-circle"
                    } 
                    size={24} 
                    color="white" 
                  />
                  <Text style={styles.submitButtonText}>
                    {modalMode === 'edit' ? t('products.button_update') : 
                     (selectedProduct && modalMode === 'add') ? t('products.button_add_stock') : 
                     t('products.button_add')}
                  </Text>
                </View>
              )}
            </TouchableOpacity>

            {/* Product Preview */}
            {(formData.name || formData.category || formData.price) && (
              <View style={styles.previewContainer}>
                <Text style={styles.previewTitle}>{t('products.preview_title')}</Text>
                <View style={styles.previewCard}>
                  <View style={styles.previewHeader}>
                    <Text style={styles.previewName}>
                      {formData.name || t('products.preview_name')}
                    </Text>
                    {selectedProduct && (
                      <View style={[
                        styles.previewBadge,
                        isOwnerOfSelectedProduct ? styles.ownerPreviewBadge : styles.nonOwnerPreviewBadge
                      ]}>
                        <Text style={styles.previewBadgeText}>
                          {modalMode === 'edit' ? t('products.status_editing') : 
                           (modalMode === 'add' && selectedProduct) ? t('products.status_adding_stock') : 
                           isOwnerOfSelectedProduct ? t('products.status_owner') : t('products.status_other')}
                        </Text>
                      </View>
                    )}
                  </View>
                  <View style={styles.previewDetails}>
                    <Text style={styles.previewCategory}>
                      {t('products.preview_category')} {formData.category || t('products.product_unknown_category')}
                    </Text>
                    <View style={styles.previewPriceRow}>
                      <View style={styles.priceColumn}>
                        <Text style={styles.priceLabel}>{t('products.preview_current_price')}</Text>
                        <Text style={styles.currentPricePreview}>
                          {formatCurrency(formData.price)}
                        </Text>
                      </View>
                      <View style={styles.priceColumn}>
                        <Text style={styles.priceLabel}>{t('products.preview_selling_price')}</Text>
                        <Text style={styles.expectedPricePreview}>
                          {formData.expected_selling_price ? formatCurrency(formData.expected_selling_price) : t('products.product_unknown_category')}
                        </Text>
                      </View>
                    </View>
                    {priceDiff && (
                      <View style={styles.previewPriceDiff}>
                        <Text style={styles.priceDiffLabel}>{t('products.preview_price_diff')}</Text>
                        <Text style={[
                          styles.priceDiffPreview,
                          priceDiff.difference >= 0 ? styles.diffPreviewPositive : styles.diffPreviewNegative
                        ]}>
                          {priceDiff.formattedDifference} ({priceDiff.formattedPercentage})
                        </Text>
                      </View>
                    )}
                    <Text style={styles.previewStock}>
                      {selectedProduct && modalMode === 'add'
                        ? t('products.preview_stock_add', {current: selectedProduct.stock || '0', add: formData.stock || '0', total: String(parseInt(selectedProduct.stock || '0') + parseInt(formData.stock || '0'))})
                        : selectedProduct && modalMode === 'edit'
                        ? t('products.preview_stock_edit', {new: formData.stock || '0', old: selectedProduct.stock || '0'})
                        : `${t('products.preview_stock')} ${formData.stock || '0'} ${t('products.preview_units')}`
                      }
                    </Text>
                  </View>
                </View>
              </View>
            )}
              </>
            )}
          </View>

          <View style={styles.bottomSpacing} />
        </ScrollView>
      </TouchableWithoutFeedback>

      {renderProductModal()}
    </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

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
  scrollContent: {
    flexGrow: 1,
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
  },
  header: {
    backgroundColor: 'white',
    padding: 25,
    borderBottomWidth: 1,
    borderBottomColor: '#ecf0f1',
  },
  headerTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 15,
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
  subtitle: {
    fontSize: 16,
    color: '#7f8c8d',
    marginBottom: 8,
  },
  businessName: {
    fontWeight: 'bold',
    color: '#2ecc71',
    fontSize: 16,
  },
  userInfo: {
    fontSize: 14,
    color: '#95a5a6',
    fontStyle: 'italic',
    marginBottom: 4,
  },
  formContainer: {
    padding: 25,
  },
  inputGroup: {
    marginBottom: 25,
  },
  label: {
    fontSize: 18,
    fontWeight: '600',
    color: '#2c3e50',
    marginBottom: 8,
  },
  input: {
    backgroundColor: 'white',
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 12,
    padding: 18,
    fontSize: 16,
    color: '#2c3e50',
  },
  priceContainer: {
    position: 'relative',
  },
  priceInput: {
    paddingRight: 120,
  },
  currencyPreview: {
    position: 'absolute',
    right: 18,
    top: 0,
    bottom: 0,
    justifyContent: 'center',
    alignItems: 'flex-end',
  },
  currencyText: {
    fontSize: 14,
    color: '#7f8c8d',
    fontWeight: 'bold',
  },
  pricePreview: {
    fontSize: 16,
    fontWeight: 'bold',
  },
  currentPrice: {
    color: '#3498db',
  },
  expectedPrice: {
    color: '#27ae60',
  },
  readOnlyHint: {
    fontSize: 12,
    color: '#e74c3c',
    marginTop: 5,
    fontStyle: 'italic',
    paddingLeft: 10,
  },
  priceDiffContainer: {
    padding: 15,
    borderRadius: 12,
    marginTop: 10,
    marginBottom: 15,
  },
  priceDiffPositive: {
    backgroundColor: '#e8f6f3',
    borderLeftWidth: 4,
    borderLeftColor: '#27ae60',
  },
  priceDiffNegative: {
    backgroundColor: '#fdeaea',
    borderLeftWidth: 4,
    borderLeftColor: '#e74c3c',
  },
  priceDiffTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 10,
  },
  priceDiffRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 5,
  },
  priceDiffLabel: {
    fontSize: 14,
    color: '#7f8c8d',
  },
  priceDiffValue: {
    fontSize: 16,
    fontWeight: 'bold',
  },
  diffPositive: {
    color: '#27ae60',
  },
  diffNegative: {
    color: '#e74c3c',
  },
  categoriesContainer: {
    marginBottom: 15,
  },
  categoriesContent: {
    paddingVertical: 5,
  },
  categoryChip: {
    backgroundColor: 'white',
    paddingHorizontal: 20,
    paddingVertical: 12,
    borderRadius: 25,
    borderWidth: 1,
    borderColor: '#ddd',
    marginRight: 12,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 1,
  },
  categoryChipSelected: {
    backgroundColor: '#2ecc71',
    borderColor: '#27ae60',
    shadowColor: '#2ecc71',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.3,
    shadowRadius: 3,
    elevation: 3,
  },
  categoryText: {
    color: '#7f8c8d',
    fontSize: 14,
    fontWeight: '500',
  },
  categoryTextSelected: {
    color: 'white',
    fontWeight: 'bold',
  },
  customCategoryContainer: {
    marginTop: 15,
    padding: 15,
    backgroundColor: '#e8f4fd',
    borderRadius: 12,
    borderLeftWidth: 4,
    borderLeftColor: '#3498db',
  },
  selectedCategory: {
    fontSize: 16,
    color: '#7f8c8d',
    marginTop: 12,
    padding: 12,
    backgroundColor: '#f0f8f0',
    borderRadius: 8,
    borderLeftWidth: 4,
    borderLeftColor: '#2ecc71',
  },
  selectedCategoryText: {
    fontWeight: 'bold',
    color: '#2ecc71',
  },
  submitButton: {
    padding: 20,
    borderRadius: 15,
    alignItems: 'center',
    marginTop: 20,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 5,
    elevation: 6,
  },
  addButton: {
    backgroundColor: '#2ecc71',
  },
  updateButton: {
    backgroundColor: '#3498db',
  },
  addStockButton: {
    backgroundColor: '#f39c12',
  },
  submitButtonContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  submitButtonDisabled: {
    backgroundColor: '#95a5a6',
    opacity: 0.6,
  },
  submitButtonText: {
    color: 'white',
    fontSize: 18,
    fontWeight: 'bold',
    textTransform: 'uppercase',
  },
  previewContainer: {
    marginTop: 35,
    padding: 20,
    backgroundColor: '#e8f4fd',
    borderRadius: 15,
    borderLeftWidth: 5,
    borderLeftColor: '#3498db',
  },
  previewTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 15,
  },
  previewCard: {
    backgroundColor: 'white',
    padding: 20,
    borderRadius: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 3,
    elevation: 2,
  },
  previewHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 15,
  },
  previewName: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#2c3e50',
    flex: 1,
    marginRight: 10,
  },
  previewBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  ownerPreviewBadge: {
    backgroundColor: '#d5f4e6',
  },
  nonOwnerPreviewBadge: {
    backgroundColor: '#fde8d9',
  },
  previewBadgeText: {
    fontSize: 12,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  previewDetails: {
    gap: 10,
  },
  previewCategory: {
    fontSize: 15,
    color: '#7f8c8d',
    marginBottom: 10,
  },
  previewPriceRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  priceColumn: {
    flex: 1,
  },
  priceLabel: {
    fontSize: 14,
    color: '#7f8c8d',
    marginBottom: 5,
  },
  currentPricePreview: {
    fontSize: 16,
    color: '#3498db',
    fontWeight: 'bold',
  },
  expectedPricePreview: {
    fontSize: 16,
    color: '#27ae60',
    fontWeight: 'bold',
  },
  previewPriceDiff: {
    marginTop: 10,
    padding: 10,
    backgroundColor: '#f8f9fa',
    borderRadius: 8,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  priceDiffPreview: {
    fontSize: 15,
    fontWeight: 'bold',
  },
  diffPreviewPositive: {
    color: '#27ae60',
  },
  diffPreviewNegative: {
    color: '#e74c3c',
  },
  previewStock: {
    fontSize: 16,
    color: '#e67e22',
    fontWeight: '500',
    marginTop: 10,
  },
  bottomSpacing: {
    height: 40,
  },
  existingProductsContainer: {
    marginBottom: 30,
  },
  existingProductsButton: {
    backgroundColor: 'white',
    borderRadius: 15,
    padding: 20,
    marginBottom: 15,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
    borderWidth: 2,
    borderColor: '#e8f6f3',
    borderStyle: 'dashed',
  },
  existingProductsButtonContent: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 15,
  },
  existingProductsButtonTextContainer: {
    flex: 1,
  },
  existingProductsButtonTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 5,
  },
  existingProductsButtonSubtitle: {
    fontSize: 14,
    color: '#7f8c8d',
  },
  selectedProductCard: {
    backgroundColor: '#f0f8f0',
    borderRadius: 15,
    padding: 20,
    borderLeftWidth: 5,
    borderLeftColor: '#2ecc71',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 2,
  },
  ownerProductCard: {
    borderLeftColor: '#2ecc71',
    backgroundColor: '#f0f8f0',
  },
  nonOwnerProductCard: {
    borderLeftColor: '#f39c12',
    backgroundColor: '#fff9e6',
  },
  selectedProductHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 15,
  },
  selectedProductTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#27ae60',
  },
  selectedProductInfo: {
    gap: 10,
  },
  selectedProductName: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  selectedProductDetails: {
    gap: 5,
  },
  selectedProductDetail: {
    fontSize: 14,
    color: '#7f8c8d',
  },
  stockAdditionInfo: {
    marginTop: 15,
    padding: 15,
    backgroundColor: '#fff9e6',
    borderRadius: 10,
    borderLeftWidth: 4,
    borderLeftColor: '#f39c12',
  },
  stockAdditionLabel: {
    fontSize: 14,
    color: '#7f8c8d',
    marginBottom: 5,
  },
  stockAdditionValue: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#e67e22',
  },
  editInfo: {
    marginTop: 15,
    padding: 15,
    backgroundColor: '#e8f4fd',
    borderRadius: 10,
    borderLeftWidth: 4,
    borderLeftColor: '#3498db',
  },
  editInfoLabel: {
    fontSize: 14,
    color: '#2c3e50',
    fontWeight: '500',
  },
  currentStockInfo: {
    fontSize: 14,
    color: '#27ae60',
    marginTop: 8,
    fontStyle: 'italic',
    paddingLeft: 10,
  },
  modalContainer: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'flex-end',
  },
  modalContent: {
    backgroundColor: 'white',
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    maxHeight: '80%',
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
  modalCloseButton: {
    padding: 5,
  },
  modalSearchInput: {
    backgroundColor: '#f8f9fa',
    margin: 20,
    padding: 15,
    borderRadius: 12,
    fontSize: 16,
    color: '#2c3e50',
    borderWidth: 1,
    borderColor: '#ddd',
  },
  modalLoadingContainer: {
    padding: 40,
    alignItems: 'center',
  },
  modalLoadingText: {
    marginTop: 15,
    fontSize: 16,
    color: '#7f8c8d',
  },
  productsList: {
    maxHeight: 400,
  },
  productItem: {
    paddingHorizontal: 20,
    paddingVertical: 15,
    borderBottomWidth: 1,
    borderBottomColor: '#ecf0f1',
  },
  ownerProductItem: {
    backgroundColor: '#f0f8f0',
  },
  productItemContent: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  productItemInfo: {
    flex: 1,
    marginRight: 15,
  },
  productItemHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  productItemName: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    flex: 1,
  },
  ownerBadge: {
    backgroundColor: '#d5f4e6',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 8,
    marginLeft: 10,
  },
  ownerBadgeText: {
    fontSize: 12,
    color: '#27ae60',
    fontWeight: 'bold',
  },
  productItemCategory: {
    fontSize: 14,
    color: '#7f8c8d',
    marginBottom: 8,
  },
  productItemDetails: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  productItemStock: {
    fontSize: 14,
    color: '#3498db',
    fontWeight: '500',
  },
  productItemPrice: {
    fontSize: 14,
    color: '#27ae60',
    fontWeight: '500',
  },
  productItemExpectedPrice: {
    fontSize: 12,
    color: '#f39c12',
    marginTop: 2,
  },
  productActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  actionButton: {
    padding: 5,
  },
  noProductsContainer: {
    padding: 50,
    alignItems: 'center',
  },
  noProductsText: {
    fontSize: 16,
    color: '#7f8c8d',
    marginTop: 20,
    fontWeight: '500',
  },
  noProductsSubtext: {
    fontSize: 14,
    color: '#bdc3c7',
    marginTop: 8,
    textAlign: 'center',
  },
  // ---------- AI IMPORT ----------
  modeToggle: {
    flexDirection: 'row',
    gap: 12,
    marginBottom: 25,
  },
  modeCard: {
    flex: 1,
    backgroundColor: 'white',
    borderRadius: 15,
    padding: 16,
    alignItems: 'center',
    borderWidth: 2,
    borderColor: '#e0e0e0',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.08,
    shadowRadius: 3,
    elevation: 1,
  },
  modeCardActive: {
    borderColor: '#2ecc71',
    backgroundColor: '#f0fdf4',
  },
  modeIconCircle: {
    width: 52,
    height: 52,
    borderRadius: 26,
    backgroundColor: '#eafaf1',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 10,
  },
  modeIconCircleActive: {
    backgroundColor: '#2ecc71',
  },
  modeCardTitle: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 4,
  },
  modeCardTitleActive: {
    color: '#27ae60',
  },
  modeCardDesc: {
    fontSize: 12,
    color: '#7f8c8d',
    textAlign: 'center',
  },
  aiPanel: {
    marginBottom: 10,
  },
  aiHowBox: {
    backgroundColor: '#fdf2e9',
    borderRadius: 12,
    padding: 15,
    borderLeftWidth: 4,
    borderLeftColor: '#e67e22',
    marginBottom: 20,
  },
  aiHowTitle: {
    fontSize: 15,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 8,
  },
  aiHowStep: {
    fontSize: 13,
    color: '#7f8c8d',
    marginBottom: 4,
    paddingLeft: 4,
  },
  aiPickRow: {
    flexDirection: 'row',
    gap: 12,
    marginBottom: 12,
  },
  aiPickButton: {
    flex: 1,
    backgroundColor: 'white',
    borderRadius: 12,
    paddingVertical: 14,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    borderWidth: 1,
    borderColor: '#ddd',
  },
  aiPickButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#2c3e50',
  },
  aiImagesHint: {
    fontSize: 13,
    color: '#27ae60',
    fontWeight: '600',
    marginBottom: 8,
  },
  aiThumbsRow: {
    marginBottom: 16,
  },
  aiThumbsContent: {
    gap: 10,
  },
  aiThumbWrap: {
    position: 'relative',
  },
  aiThumb: {
    width: 88,
    height: 88,
    borderRadius: 10,
    backgroundColor: '#ecf0f1',
  },
  aiThumbRemove: {
    position: 'absolute',
    top: -6,
    right: -6,
    backgroundColor: '#e74c3c',
    borderRadius: 12,
    width: 24,
    height: 24,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 2,
    borderColor: 'white',
  },
  aiNoImages: {
    fontSize: 13,
    color: '#95a5a6',
    fontStyle: 'italic',
    textAlign: 'center',
    marginTop: 6,
  },
  aiErrorBox: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 8,
    backgroundColor: '#fdeaea',
    borderRadius: 10,
    padding: 12,
    marginTop: 10,
  },
  aiErrorText: {
    flex: 1,
    fontSize: 14,
    color: '#c0392b',
  },
  aiProcessButton: {
    backgroundColor: '#8e44ad',
    padding: 18,
    borderRadius: 15,
    alignItems: 'center',
    marginTop: 16,
    shadowColor: '#8e44ad',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 5,
    elevation: 6,
  },
  aiProcessButtonDisabled: {
    backgroundColor: '#bdc3c7',
    opacity: 0.7,
  },
  aiProcessButtonText: {
    color: 'white',
    fontSize: 17,
    fontWeight: 'bold',
    textTransform: 'uppercase',
  },
  aiProgress: {
    alignItems: 'center',
    marginTop: 22,
    padding: 18,
    backgroundColor: '#f4f6f8',
    borderRadius: 12,
  },
  aiDotsRow: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 12,
  },
  aiDot: {
    width: 12,
    height: 12,
    borderRadius: 6,
    backgroundColor: '#d5dbdb',
  },
  aiDotActive: {
    backgroundColor: '#8e44ad',
  },
  aiProgressText: {
    fontSize: 15,
    fontWeight: '600',
    color: '#2c3e50',
  },
  aiProgressNote: {
    fontSize: 12,
    color: '#95a5a6',
    marginTop: 6,
  },
  aiResultsHeader: {
    marginTop: 22,
    marginBottom: 12,
  },
  aiResultsTitle: {
    fontSize: 18,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 6,
  },
  aiDisclaimer: {
    fontSize: 12,
    color: '#e67e22',
    fontStyle: 'italic',
  },
  aiItemCard: {
    backgroundColor: 'white',
    borderRadius: 14,
    padding: 16,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: '#e0e0e0',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.08,
    shadowRadius: 3,
    elevation: 1,
  },
  aiItemCardVerified: {
    borderColor: '#27ae60',
    borderWidth: 2,
  },
  aiItemTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
    flexWrap: 'wrap',
  },
  aiBadgeRow: {
    flexDirection: 'row',
    gap: 8,
    alignItems: 'center',
    flexWrap: 'wrap',
  },
  aiBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 10,
  },
  aiBadgeExisting: {
    backgroundColor: '#d5f4e6',
  },
  aiBadgeNew: {
    backgroundColor: '#d6eaf8',
  },
  aiBadgeReview: {
    backgroundColor: '#fde8d9',
  },
  aiBadgeOk: {
    backgroundColor: '#27ae60',
  },
  aiBadgeText: {
    fontSize: 12,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  aiBadgeOkText: {
    color: 'white',
  },
  aiConfidence: {
    fontSize: 13,
    fontWeight: '600',
    color: '#7f8c8d',
  },
  aiCurrentStock: {
    fontSize: 13,
    color: '#3498db',
    marginBottom: 8,
  },
  aiField: {
    marginBottom: 10,
  },
  aiLabel: {
    fontSize: 13,
    fontWeight: '600',
    color: '#2c3e50',
    marginBottom: 5,
  },
  aiInput: {
    backgroundColor: '#f8f9fa',
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 10,
    padding: 12,
    fontSize: 15,
    color: '#2c3e50',
  },
  aiRowTwo: {
    flexDirection: 'row',
    gap: 12,
  },
  aiFlex: {
    flex: 1,
  },
  aiSource: {
    fontSize: 12,
    color: '#95a5a6',
    fontStyle: 'italic',
    marginBottom: 8,
  },
  aiWarnings: {
    backgroundColor: '#fff9e6',
    borderRadius: 8,
    padding: 10,
    marginTop: 4,
    marginBottom: 10,
  },
  aiWarningText: {
    fontSize: 12,
    color: '#b7791f',
    marginBottom: 2,
  },
  aiVerifyButton: {
    backgroundColor: '#f39c12',
    borderRadius: 12,
    padding: 14,
    alignItems: 'center',
    marginTop: 4,
  },
  aiVerifyButtonDone: {
    backgroundColor: '#27ae60',
  },
  aiVerifyButtonBusy: {
    backgroundColor: '#95a5a6',
  },
  aiVerifyButtonText: {
    color: 'white',
    fontSize: 15,
    fontWeight: 'bold',
  },
  aiClearButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 14,
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 12,
    marginTop: 6,
  },
  aiClearButtonText: {
    fontSize: 15,
    color: '#7f8c8d',
    fontWeight: '600',
  },
});