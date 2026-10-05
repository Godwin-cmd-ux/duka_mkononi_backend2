import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useRouter } from 'expo-router';
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

type Expense = {
    id: string;
    amount: number;
    description: string;
    category: string;
    notes?: string;
    expense_date: string;
    created_at: string;
};

type DailyProfit = {
    date: string;
    revenue: { gross: number; cost_of_goods: number };
    gross_profit: number;
    expenses: { total: number; by_category: any; list: Expense[] };
    net_profit: number;
    sales_count: number;
    expenses_count: number;
};

export default function MatumiziScreen() {
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
    const [loading, setLoading] = useState(true);
    const [expenses, setExpenses] = useState<Expense[]>([]);
    const [dailyProfit, setDailyProfit] = useState<DailyProfit | null>(null);
    const [addModalVisible, setAddModalVisible] = useState(false);
    const [categories, setCategories] = useState<string[]>([]);
    const [selectedDate, setSelectedDate] = useState(new Date().toISOString().split('T')[0]);
    
    // Form state
    const [formData, setFormData] = useState({
        amount: '',
        description: '',
        category: '',
        notes: ''
    });

    const today = new Date().toISOString().split('T')[0];
    const isToday = selectedDate === today;

    // Format currency
    const formatCurrency = (amount: number) => {
        return `TZS ${amount.toLocaleString('en-TZ', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        })}`;
    };

    // Load categories
    const loadCategories = async () => {
        try {
            const token = await AsyncStorage.getItem('userToken');
            const response = await fetchWithTimeout(`${API_BASE_URL}/api/office-expenses/categories`, {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            const data = await response.json();
            if (data.success) {
                setCategories(data.categories);
                // Set default category
                if (data.categories.length > 0) {
                    setFormData(prev => ({ ...prev, category: data.categories[0] }));
                }
            }
        } catch (error) {
            console.error('Error loading categories:', error);
        }
    };

    // Load today's expenses and profit
    const loadData = async () => {
        try {
            setLoading(true);
            const token = await AsyncStorage.getItem('userToken');
            
            if (!token) {
                Alert.alert(t('app.error'), t('seller_dashboard.error_auth'));
                await signOut();
                router.replace('/(tabs)');
                return;
            }

            const cachedProfit = await getCache<any>('profit:daily:' + selectedDate);
            if (cachedProfit) { setDailyProfit(cachedProfit); setLoading(false); }
            const cachedExpenses = await getCache<any>('expenses:range:' + selectedDate + ':' + selectedDate);
            if (cachedExpenses) { setExpenses(cachedExpenses); }

            // Load expenses for selected date
            const expensesResponse = await fetchWithTimeout(
                `${API_BASE_URL}/api/office-expenses/range?start_date=${selectedDate}&end_date=${selectedDate}`,
                {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                    }
                }
            );

            // Load daily profit
            const profitResponse = await fetchWithTimeout(
                `${API_BASE_URL}/api/profit/daily/${selectedDate}`,
                {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                    }
                }
            );

            if (expensesResponse.ok) {
                const expensesData = await expensesResponse.json();
                setExpenses(expensesData.expenses || []);
                setCache('expenses:range:' + selectedDate + ':' + selectedDate, expensesData.expenses || []).catch(() => {});
            }

            if (profitResponse.ok) {
                const profitData = await profitResponse.json();
                setDailyProfit(profitData);
                setCache('profit:daily:' + selectedDate, profitData).catch(() => {});
            }

        } catch (error) {
            console.error('Error loading data:', error);
            const cachedProfit = await getCache<any>('profit:daily:' + selectedDate);
            const cachedExpenses = await getCache<any>('expenses:range:' + selectedDate + ':' + selectedDate);
            if (cachedProfit) { setDailyProfit(cachedProfit); setExpenses(cachedExpenses || []); setLoading(false); return; }
            if (cachedExpenses) { setExpenses(cachedExpenses); setLoading(false); return; }
            Alert.alert(t('app.error'), t('seller_dashboard.error_network'));
        } finally {
            setLoading(false);
        }
    };

    // Add new expense
    const addExpense = async () => {
        try {
            // Validate
            if (!formData.amount || parseFloat(formData.amount) <= 0) {
                Alert.alert(t('app.error'), t('seller_dashboard.expense_amount_required'));
                return;
            }
            if (!formData.description.trim()) {
                Alert.alert(t('app.error'), t('seller_dashboard.expense_description_required'));
                return;
            }
            if (!formData.category) {
                Alert.alert(t('app.error'), t('seller_dashboard.select_category'));
                return;
            }

            const token = await AsyncStorage.getItem('userToken');
            if (!(await requireNetwork())) return;
            
            const response = await fetchWithTimeout(`${API_BASE_URL}/api/office-expenses`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    amount: parseFloat(formData.amount),
                    description: formData.description.trim(),
                    category: formData.category,
                    notes: formData.notes.trim() || null
                })
            });

            const data = await response.json();

            if (data.success) {
                Alert.alert(t('app.success'), t('seller_dashboard.success_add'));
                setAddModalVisible(false);
                resetForm();
                loadData(); // Reload data
            } else {
                Alert.alert(t('app.error'), data.error || t('seller_dashboard.error_add'));
            }

        } catch (error) {
            console.error('Error adding expense:', error);
            Alert.alert(t('app.error'), t('seller_dashboard.error_network'));
        }
    };

    // Delete expense
    const deleteExpense = (expenseId: string, amount: number) => {
        Alert.alert(
            t('seller_dashboard.delete_expense_title'),
            t('seller_dashboard.delete_expense_confirm').replace('{amount}', formatCurrency(amount)),
            [
                { text: t('seller_dashboard.cancel'), style: 'cancel' },
                {
                    text: t('app.delete'),
                    style: 'destructive',
                    onPress: async () => {
                        if (!(await requireNetwork())) return;
                        try {
                            const token = await AsyncStorage.getItem('userToken');
                            const response = await fetchWithTimeout(`${API_BASE_URL}/api/office-expenses/${expenseId}`, {
                                method: 'DELETE',
                                headers: { 'Authorization': `Bearer ${token}` }
                            });

                            const data = await response.json();

                            if (data.success) {
                                Alert.alert(t('app.success'), t('seller_dashboard.delete_expense_success'));
                                loadData(); // Reload
                            } else {
                                Alert.alert(t('app.error'), data.error || t('seller_dashboard.delete_expense_error'));
                            }
                        } catch (error) {
                            Alert.alert(t('app.error'), t('seller_dashboard.error_network'));
                        }
                    }
                }
            ]
        );
    };

    // Change date
    const changeDate = (days: number) => {
        const date = new Date(selectedDate);
        date.setDate(date.getDate() + days);
        setSelectedDate(date.toISOString().split('T')[0]);
    };

    const resetForm = () => {
        setFormData({
            amount: '',
            description: '',
            category: categories[0] || '',
            notes: ''
        });
    };

    useEffect(() => {
        loadCategories();
    }, [lang]);

    useEffect(() => {
        loadData();
    }, [selectedDate, lang]);

    useEffect(() => {
        const stop = registerLive<any>(
            'profit:daily:' + selectedDate,
            async () => {
                const token = await AsyncStorage.getItem('userToken');
                const res = await fetchWithTimeout(
                    `${API_BASE_URL}/api/profit/daily/${selectedDate}`,
                    {
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json',
                        }
                    }
                );
                if (!res.ok) throw new Error('sync failed');
                return await res.json();
            },
            (data) => { setDailyProfit(data); setLoading(false); }
        );
        return stop;
    }, [selectedDate, lang]);

    const renderExpenseItem = ({ item }: { item: Expense }) => (
        <View style={styles.expenseItem}>
            <View style={styles.expenseHeader}>
                <View style={styles.expenseCategory}>
                    <Ionicons name="pricetag" size={16} color="#3498db" />
                    <Text style={styles.categoryText}>{item.category}</Text>
                </View>
                <TouchableOpacity onPress={() => deleteExpense(item.id, item.amount)}>
                    <Ionicons name="trash-outline" size={20} color="#e74c3c" />
                </TouchableOpacity>
            </View>
            
            <Text style={styles.expenseDescription}>{item.description}</Text>
            
            <View style={styles.expenseFooter}>
                <Text style={styles.expenseAmount}>{formatCurrency(item.amount)}</Text>
                <Text style={styles.expenseTime}>
                    {new Date(item.created_at).toLocaleTimeString(localeMap[lang] || 'sw-TZ', {
                        hour: '2-digit',
                        minute: '2-digit'
                    })}
                </Text>
            </View>
            
            {item.notes && (
                <View style={styles.notesContainer}>
                    <Ionicons name="document-text" size={14} color="#95a5a6" />
                    <Text style={styles.notesText}>{item.notes}</Text>
                </View>
            )}
        </View>
    );

    if (loading && !dailyProfit) {
        return (
            <SafeAreaView style={styles.screenSafe} edges={['top']}>
                <View style={styles.loadingContainer}>
                    <ActivityIndicator size="large" color="#2ecc71" />
                    <Text style={styles.loadingText}>{t('app.loading')}</Text>
                </View>
            </SafeAreaView>
        );
    }

    return (
        <SafeAreaView style={styles.screenSafe} edges={['top']}>
            <View style={styles.container}>
            {/* Header */}
            <View style={styles.header}>
                <View style={styles.headerTop}>
                    <TouchableOpacity onPress={() => router.back()} style={styles.backButton}>
                        <Ionicons name="arrow-back" size={24} color="#2c3e50" />
                    </TouchableOpacity>
                    <Text style={styles.title}>{t('seller_dashboard.expenses')}</Text>
                    <View style={styles.headerActions}>
                        <TouchableOpacity onPress={loadData} style={styles.refreshButton}>
                            <Ionicons name="refresh-outline" size={22} color="#2ecc71" />
                        </TouchableOpacity>
                        <LogoutButton iconOnly />
                    </View>
                </View>
            </View>

            {/* Date Selector */}
            <View style={styles.dateSelector}>
                <TouchableOpacity onPress={() => changeDate(-1)} style={styles.dateNav}>
                    <Ionicons name="chevron-back" size={20} color="#2ecc71" />
                </TouchableOpacity>
                
                <View style={styles.dateDisplay}>
                    <Ionicons name="calendar" size={18} color="#666" />
                    <Text style={styles.dateText}>
                        {new Date(selectedDate).toLocaleDateString(localeMap[lang] || 'sw-TZ', {
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric'
                        })}
                    </Text>
                    {isToday && <Text style={styles.todayBadge}>{t('seller_dashboard.today')}</Text>}
                </View>
                
                <TouchableOpacity 
                    onPress={() => changeDate(1)} 
                    style={styles.dateNav}
                    disabled={isToday}
                >
                    <Ionicons 
                        name="chevron-forward" 
                        size={20} 
                        color={isToday ? '#bdc3c7' : '#2ecc71'} 
                    />
                </TouchableOpacity>
            </View>

            {/* Profit Summary Card */}
            {dailyProfit && (
                <View style={styles.profitCard}>
                    <Text style={styles.profitTitle}>{t('seller_dashboard.profit_summary')}</Text>
                    
                    <View style={styles.profitRow}>
                        <Text style={styles.profitLabel}>{t('seller_dashboard.total_sales')}:</Text>
                        <Text style={styles.profitValue}>
                            {formatCurrency(dailyProfit.revenue.gross)}
                        </Text>
                    </View>
                    
                    <View style={styles.profitRow}>
                        <Text style={styles.profitLabel}>{t('seller_dashboard.cost_of_goods')}:</Text>
                        <Text style={[styles.profitValue, styles.costText]}>
                            -{formatCurrency(dailyProfit.revenue.cost_of_goods)}
                        </Text>
                    </View>
                    
                    <View style={styles.profitDivider} />
                    
                    <View style={styles.profitRow}>
                        <Text style={styles.profitLabel}>{t('seller_dashboard.gross_profit')}:</Text>
                        <Text style={[styles.profitValue, styles.grossProfitText]}>
                            {formatCurrency(dailyProfit.gross_profit)}
                        </Text>
                    </View>
                    
                    <View style={styles.profitRow}>
                        <Text style={styles.profitLabel}>{t('seller_dashboard.office_expenses')}:</Text>
                        <Text style={[styles.profitValue, styles.expenseText]}>
                            -{formatCurrency(dailyProfit.expenses.total)}
                        </Text>
                    </View>
                    
                    <View style={styles.profitDivider} />
                    
                    <View style={styles.profitRow}>
                        <Text style={[styles.profitLabel, styles.netLabel]}>
                            {t('seller_dashboard.net_profit')}:
                        </Text>
                        <Text style={[
                            styles.profitValue, 
                            styles.netValue,
                            dailyProfit.net_profit < 0 && styles.netLoss
                        ]}>
                            {formatCurrency(dailyProfit.net_profit)}
                            {dailyProfit.net_profit < 0 && t('seller_dashboard.loss')}
                        </Text>
                    </View>
                    
                    <View style={styles.statsRow}>
                        <View style={styles.statBox}>
                            <Text style={styles.statNumber}>{dailyProfit.sales_count}</Text>
                            <Text style={styles.statLabel}>{t('seller_dashboard.sales_count')}</Text>
                        </View>
                        <View style={styles.statBox}>
                            <Text style={styles.statNumber}>{dailyProfit.expenses_count}</Text>
                            <Text style={styles.statLabel}>{t('seller_dashboard.expenses_count')}</Text>
                        </View>
                    </View>
                </View>
            )}

            {/* Add Expense Button */}
            {isToday && (
                <TouchableOpacity 
                    style={styles.addButton}
                    onPress={() => setAddModalVisible(true)}
                >
                    <Ionicons name="add-circle" size={22} color="white" />
                    <Text style={styles.addButtonText}>{t('seller_dashboard.add_expense')}</Text>
                </TouchableOpacity>
            )}

            {/* Expenses List */}
            <View style={styles.expensesContainer}>
                <View style={styles.expensesHeader}>
                    <Text style={styles.expensesTitle}>
                        {isToday ? t('seller_dashboard.expenses_today') : t('seller_dashboard.expenses_date_label')}
                    </Text>
                    {expenses.length > 0 && (
                        <Text style={styles.expensesTotal}>
                            {t('seller_dashboard.total_expenses').replace('{amount}', formatCurrency(expenses.reduce((sum, e) => sum + e.amount, 0)))}
                        </Text>
                    )}
                </View>

                {expenses.length === 0 ? (
                    <View style={styles.emptyContainer}>
                        <Ionicons name="receipt-outline" size={48} color="#bdc3c7" />
                        <Text style={styles.emptyTitle}>{t('seller_dashboard.no_expenses')}</Text>
                        <Text style={styles.emptyText}>
                            {isToday 
                                ? t('seller_dashboard.no_expenses_today') 
                                : t('seller_dashboard.no_expenses_date')}
                        </Text>
                        {isToday && (
                            <TouchableOpacity 
                                style={styles.emptyAddButton}
                                onPress={() => setAddModalVisible(true)}
                            >
                                <Text style={styles.emptyAddButtonText}>{t('seller_dashboard.add_expense_action')}</Text>
                            </TouchableOpacity>
                        )}
                    </View>
                ) : (
                    <FlatList
                        data={expenses}
                        renderItem={renderExpenseItem}
                        keyExtractor={(item) => item.id}
                        contentContainerStyle={styles.listContent}
                        showsVerticalScrollIndicator={false}
                    />
                )}
            </View>

            {/* Add Expense Modal */}
            <Modal
                visible={addModalVisible}
                animationType="slide"
                transparent={true}
                onRequestClose={() => setAddModalVisible(false)}
            >
                <View style={styles.modalOverlay}>
                    <View style={styles.modalContent}>
                        <View style={styles.modalHeader}>
                            <Text style={styles.modalTitle}>{t('seller_dashboard.add_expense_title')}</Text>
                            <TouchableOpacity onPress={() => setAddModalVisible(false)}>
                                <Ionicons name="close" size={24} color="#666" />
                            </TouchableOpacity>
                        </View>

                        <ScrollView style={styles.modalBody}>
                            {/* Amount Input */}
                            <View style={styles.formGroup}>
                                <Text style={styles.formLabel}>
                                    {t('seller_dashboard.amount_label')}
                                </Text>
                                <TextInput
                                    style={styles.formInput}
                                    value={formData.amount}
                                    onChangeText={(text) => setFormData({
                                        ...formData, 
                                        amount: text.replace(/[^0-9]/g, '')
                                    })}
                                    keyboardType="numeric"
                                    placeholder="0"
                                    placeholderTextColor="#999"
                                />
                            </View>

                            {/* Category Picker - FIXED VERSION */}
                            <View style={styles.formGroup}>
                                <Text style={styles.formLabel}>
                                    {t('seller_dashboard.category_label')}
                                </Text>
                                
                                <View style={styles.categoryContainer}>
                                    {categories.length === 0 ? (
                                        <ActivityIndicator size="small" color="#2ecc71" />
                                    ) : (
                                        <>
                                            <ScrollView 
                                                horizontal 
                                                showsHorizontalScrollIndicator={false}
                                                style={styles.categoryScroll}
                                                contentContainerStyle={styles.categoryScrollContent}
                                            >
                                                {categories.map((cat) => (
                                                    <TouchableOpacity
                                                        key={cat}
                                                        style={[
                                                            styles.categoryChip,
                                                            formData.category === cat && styles.categoryChipSelected
                                                        ]}
                                                        onPress={() => setFormData({...formData, category: cat})}
                                                    >
                                                        <Text style={[
                                                            styles.categoryChipText,
                                                            formData.category === cat && styles.categoryChipTextSelected
                                                        ]}>
                                                            {cat}
                                                        </Text>
                                                    </TouchableOpacity>
                                                ))}
                                            </ScrollView>
                                            
                                            {/* Selected category indicator */}
                                            <View style={styles.selectedCategoryContainer}>
                                                <Ionicons 
                                                    name="checkmark-circle" 
                                                    size={18} 
                                                    color={formData.category ? "#2ecc71" : "#ccc"} 
                                                />
                                                <Text style={styles.selectedCategoryText}>
                                                    {formData.category 
                                                        ? t('seller_dashboard.category_selected').replace('{category}', formData.category)
                                                        : t('seller_dashboard.category_not_selected')}
                                                </Text>
                                            </View>
                                        </>
                                    )}
                                </View>
                            </View>

                            {/* Description */}
                            <View style={styles.formGroup}>
                                <Text style={styles.formLabel}>
                                    {t('seller_dashboard.description_label')}
                                </Text>
                                <TextInput
                                    style={styles.formInput}
                                    value={formData.description}
                                    onChangeText={(text) => setFormData({...formData, description: text})}
                                    placeholder={t('seller_dashboard.example_description')}
                                    placeholderTextColor="#999"
                                />
                            </View>

                            {/* Notes */}
                            <View style={styles.formGroup}>
                                <Text style={styles.formLabel}>{t('seller_dashboard.notes_label')}</Text>
                                <TextInput
                                    style={[styles.formInput, styles.textArea]}
                                    value={formData.notes}
                                    onChangeText={(text) => setFormData({...formData, notes: text})}
                                    placeholder={t('seller_dashboard.notes_placeholder')}
                                    placeholderTextColor="#999"
                                    multiline
                                    numberOfLines={3}
                                />
                            </View>

                            {/* Preview */}
                            {formData.amount && formData.category && (
                                <View style={styles.previewBox}>
                                    <Text style={styles.previewTitle}>{t('seller_dashboard.preview_title')}</Text>
                                    <Text style={styles.previewText}>
                                        {formData.category}: {formatCurrency(parseFloat(formData.amount) || 0)}
                                    </Text>
                                    <Text style={styles.previewDesc}>{formData.description}</Text>
                                </View>
                            )}
                        </ScrollView>

                        <View style={styles.modalFooter}>
                            <TouchableOpacity 
                                style={styles.cancelButton}
                                onPress={() => {
                                    setAddModalVisible(false);
                                    resetForm();
                                }}
                            >
                                <Text style={styles.cancelButtonText}>{t('seller_dashboard.cancel')}</Text>
                            </TouchableOpacity>
                            
                            <TouchableOpacity 
                                style={[
                                    styles.saveButton,
                                    (!formData.amount || !formData.description || !formData.category) && styles.saveButtonDisabled
                                ]}
                                onPress={addExpense}
                                disabled={!formData.amount || !formData.description || !formData.category}
                            >
                                <Text style={styles.saveButtonText}>{t('seller_dashboard.save')}</Text>
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
    
    loadingContainer: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
        backgroundColor: '#f8f9fa',
    },
    
    loadingText: {
        marginTop: 15,
        fontSize: 16,
        color: '#666',
    },
    
    // Header Styles
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
    },
    headerActions: {
        flexDirection: 'row',
        alignItems: 'center',
    },
    
    backButton: {
        padding: 8,
    },
    
    title: {
        fontSize: 22,
        fontWeight: 'bold',
        color: '#2c3e50',
        textAlign: 'center',
        flex: 1,
    },
    
    refreshButton: {
        padding: 8,
        backgroundColor: '#f0f7ff',
        borderRadius: 8,
    },
    
    // Date Selector
    dateSelector: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        backgroundColor: 'white',
        paddingVertical: 15,
        paddingHorizontal: 10,
        borderBottomWidth: 1,
        borderBottomColor: '#e9ecef',
    },
    
    dateNav: {
        padding: 10,
    },
    
    dateDisplay: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 8,
        backgroundColor: '#f8f9fa',
        paddingHorizontal: 15,
        paddingVertical: 8,
        borderRadius: 20,
        borderWidth: 1,
        borderColor: '#e0e0e0',
    },
    
    dateText: {
        fontSize: 15,
        color: '#2c3e50',
        fontWeight: '500',
    },
    
    todayBadge: {
        backgroundColor: '#2ecc71',
        color: 'white',
        fontSize: 12,
        fontWeight: 'bold',
        paddingHorizontal: 8,
        paddingVertical: 3,
        borderRadius: 12,
        overflow: 'hidden',
    },
    
    // Profit Card
    profitCard: {
        backgroundColor: 'white',
        margin: 15,
        padding: 20,
        borderRadius: 16,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
        shadowRadius: 4,
        elevation: 3,
    },
    
    profitTitle: {
        fontSize: 18,
        fontWeight: 'bold',
        color: '#2c3e50',
        marginBottom: 15,
    },
    
    profitRow: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        marginBottom: 8,
    },
    
    profitLabel: {
        fontSize: 15,
        color: '#7f8c8d',
    },
    
    profitValue: {
        fontSize: 15,
        fontWeight: '600',
        color: '#2c3e50',
    },
    
    costText: {
        color: '#e74c3c',
    },
    
    grossProfitText: {
        color: '#27ae60',
    },
    
    expenseText: {
        color: '#e67e22',
    },
    
    profitDivider: {
        height: 1,
        backgroundColor: '#ecf0f1',
        marginVertical: 12,
    },
    
    netLabel: {
        fontWeight: 'bold',
        fontSize: 16,
    },
    
    netValue: {
        fontWeight: 'bold',
        fontSize: 18,
        color: '#27ae60',
    },
    
    netLoss: {
        color: '#e74c3c',
    },
    
    statsRow: {
        flexDirection: 'row',
        justifyContent: 'space-around',
        marginTop: 15,
        paddingTop: 15,
        borderTopWidth: 1,
        borderTopColor: '#ecf0f1',
    },
    
    statBox: {
        alignItems: 'center',
    },
    
    statNumber: {
        fontSize: 20,
        fontWeight: 'bold',
        color: '#2c3e50',
    },
    
    statLabel: {
        fontSize: 13,
        color: '#7f8c8d',
        marginTop: 4,
    },
    
    // Add Button
    addButton: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: '#27ae60',
        marginHorizontal: 15,
        marginBottom: 15,
        paddingVertical: 14,
        borderRadius: 12,
        gap: 10,
        shadowColor: '#27ae60',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.2,
        shadowRadius: 4,
        elevation: 3,
    },
    
    addButtonText: {
        color: 'white',
        fontSize: 16,
        fontWeight: 'bold',
    },
    
    // Expenses Container
    expensesContainer: {
        flex: 1,
        paddingHorizontal: 15,
    },
    
    expensesHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 10,
    },
    
    expensesTitle: {
        fontSize: 18,
        fontWeight: 'bold',
        color: '#2c3e50',
    },
    
    expensesTotal: {
        fontSize: 16,
        fontWeight: '600',
        color: '#27ae60',
    },
    
    listContent: {
        paddingBottom: 20,
    },
    
    // Expense Item
    expenseItem: {
        backgroundColor: 'white',
        borderRadius: 12,
        padding: 15,
        marginBottom: 10,
        borderWidth: 1,
        borderColor: '#e9ecef',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.05,
        shadowRadius: 2,
        elevation: 1,
    },
    
    expenseHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 8,
    },
    
    expenseCategory: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 6,
    },
    
    categoryText: {
        fontSize: 14,
        fontWeight: '600',
        color: '#3498db',
    },
    
    expenseDescription: {
        fontSize: 16,
        color: '#2c3e50',
        marginBottom: 10,
    },
    
    expenseFooter: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
    },
    
    expenseAmount: {
        fontSize: 18,
        fontWeight: 'bold',
        color: '#2c3e50',
    },
    
    expenseTime: {
        fontSize: 13,
        color: '#95a5a6',
    },
    
    notesContainer: {
        flexDirection: 'row',
        alignItems: 'flex-start',
        marginTop: 10,
        paddingTop: 10,
        borderTopWidth: 1,
        borderTopColor: '#ecf0f1',
        gap: 6,
    },
    
    notesText: {
        flex: 1,
        fontSize: 13,
        color: '#7f8c8d',
        fontStyle: 'italic',
    },
    
    // Empty State
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
        marginTop: 15,
        marginBottom: 8,
    },
    
    emptyText: {
        fontSize: 15,
        color: '#7f8c8d',
        textAlign: 'center',
        lineHeight: 22,
        marginBottom: 20,
    },
    
    emptyAddButton: {
        backgroundColor: '#f0f7ff',
        paddingHorizontal: 20,
        paddingVertical: 12,
        borderRadius: 8,
        borderWidth: 1,
        borderColor: '#2ecc71',
    },
    
    emptyAddButtonText: {
        color: '#2ecc71',
        fontSize: 15,
        fontWeight: '600',
    },
    
    // Modal Styles
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
    
    requiredStar: {
        color: '#e74c3c',
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
    
    textArea: {
        minHeight: 80,
        textAlignVertical: 'top',
    },
    
    // Category Picker Styles - NEW
    categoryContainer: {
        marginBottom: 5,
    },
    
    categoryScroll: {
        flexDirection: 'row',
        maxHeight: 50,
        marginBottom: 10,
    },
    
    categoryScrollContent: {
        paddingVertical: 5,
        paddingRight: 20,
    },
    
    categoryChip: {
        backgroundColor: '#f8f9fa',
        paddingHorizontal: 16,
        paddingVertical: 10,
        borderRadius: 25,
        marginRight: 10,
        borderWidth: 1,
        borderColor: '#dee2e6',
    },
    
    categoryChipSelected: {
        backgroundColor: '#2ecc71',
        borderColor: '#2ecc71',
    },
    
    categoryChipText: {
        color: '#2c3e50',
        fontSize: 14,
        fontWeight: '500',
    },
    
    categoryChipTextSelected: {
        color: 'white',
    },
    
    selectedCategoryContainer: {
        flexDirection: 'row',
        alignItems: 'center',
        marginTop: 5,
        padding: 8,
        backgroundColor: '#f0f7ff',
        borderRadius: 8,
    },
    
    selectedCategoryText: {
        marginLeft: 6,
        fontSize: 14,
        color: '#2c3e50',
    },
    
    previewBox: {
        backgroundColor: '#f0f7ff',
        padding: 15,
        borderRadius: 10,
        marginTop: 10,
        borderWidth: 1,
        borderColor: '#d4e6f1',
    },
    
    previewTitle: {
        fontSize: 15,
        fontWeight: 'bold',
        color: '#2c3e50',
        marginBottom: 8,
    },
    
    previewText: {
        fontSize: 16,
        color: '#2ecc71',
        fontWeight: '600',
        marginBottom: 4,
    },
    
    previewDesc: {
        fontSize: 14,
        color: '#666',
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
        paddingVertical: 14,
        alignItems: 'center',
        backgroundColor: '#2ecc71',
        borderRadius: 10,
    },
    
    saveButtonDisabled: {
        backgroundColor: '#bdc3c7',
    },
    
    saveButtonText: {
        color: 'white',
        fontSize: 16,
        fontWeight: 'bold',
    },
});