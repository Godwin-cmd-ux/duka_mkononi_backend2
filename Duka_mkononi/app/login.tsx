import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState, useRef } from 'react';
import {
    ActivityIndicator,
    Alert,
    Dimensions,
    KeyboardAvoidingView,
    Platform,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View
} from 'react-native';
import { useLang, Lang, ALL_LANGUAGES } from '../context/LanguageContext';
import { useSession } from '../context/SessionContext';

// ✅ BASE URL YA BACKEND YAKO
import { API_BASE_URL } from '../constants/api';
import {
    fetchApiWithFallback,
    isAbortError,
    API_FETCH_TIMEOUT_MS,
} from '../lib/network';
import { getDashboardRouteForRole } from '../constants/session';

const { width } = Dimensions.get('window');

// Security: Input validation and sanitization
const validateEmail = (email: string): boolean => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
};

const sanitizeInput = (input: string): string => {
    return input.trim().replace(/[<>]/g, '');
};

// ✅ FUNCTION YA KU-CONVERT ROLES ZA FRONTEND KWENYA BACKEND
const getBackendRole = (frontendRole: string): string => {
    switch (frontendRole) {
        case 'mteja': return 'customer';
        case 'muuzaji': return 'seller';
        case 'msimamizi': return 'admin';
        case 'washa': return 'customer'; // Kwa ajili ya wash button
        default: return frontendRole;
    }
};

// ✅ FUNCTION YA KU-PATA SIGNUP ROUTE KULINGANA NA ROLE
const getSignupRoute = (role: string): string => {
    switch (role) {
        case 'mteja':
            return '/client_signup';
        case 'muuzaji':
            return '/cashier_signup';
        case 'msimamizi':
            return '/admin_signup';
        case 'washa':
            return '/client_signup'; // Kwa ajili ya washa, tumia client_signup pia
        default:
            return '/client_signup';
    }
};

// ✅ HELPER FUNCTION YA KUSOMA DATA KUTOKA ASYNC STORAGE
const getAsyncStorageItem = async (key: string): Promise<string | null> => {
    try {
        const value = await AsyncStorage.getItem(key);
        // Convert any value to string to avoid type casting errors
        return value ? String(value) : null;
    } catch (error) {
        console.error(`Error getting ${key} from AsyncStorage:`, error);
        return null;
    }
};

// ✅ HELPER FUNCTION YA KUONDOA DATA ZOTE ZA LOGIN
const clearLoginData = async (role: string) => {
    try {
        const keys = await AsyncStorage.getAllKeys();
        const loginKeys = keys.filter(key => 
            key.includes(`savedEmail_`) || 
            key.includes(`savedPassword_`) || 
            key.includes(`rememberMe_`)
        );
        
        if (loginKeys.length > 0) {
            await AsyncStorage.multiRemove(loginKeys);
            console.log('✅ Cleared all login data');
        }
    } catch (error) {
        console.error('Error clearing login data:', error);
    }
};

export default function LoginScreen() {
    const router = useRouter();
    const { signIn } = useSession();
    const params = useLocalSearchParams();
    const role = params.role as string || 'mteja';
    const { lang, changeLang, t } = useLang();

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [loading, setLoading] = useState(false);
    const [rememberMe, setRememberMe] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [formErrors, setFormErrors] = useState<{
        email?: string;
        password?: string;
    }>({});

    const emailInputRef = useRef<TextInput>(null);
    const passwordInputRef = useRef<TextInput>(null);

    // Get storage keys for current role
    const getStorageKeys = () => {
        return {
            savedEmail: `savedEmail_${role}`,
            savedPassword: `savedPassword_${role}`,
            rememberMe: `rememberMe_${role}`
        };
    };

    // Set language from params if provided (coming from home page)
    useEffect(() => {
        const langParam = params.lang as string;
        if (langParam && ALL_LANGUAGES.includes(langParam as Lang)) {
            changeLang(langParam as Lang);
        }
    }, [params.lang]);

    // Check for saved credentials on component mount
    useEffect(() => {
        checkSavedCredentials();
    }, [role]);

    // 🔒 If the user is already logged in (cached session), skip login entirely
    useEffect(() => {
        let cancelled = false;

        const checkExistingSession = async () => {
            try {
                const [isLoggedIn, userRole, userToken] = await Promise.all([
                    getAsyncStorageItem('isLoggedIn'),
                    getAsyncStorageItem('userRole'),
                    getAsyncStorageItem('userToken'),
                ]);

                if (cancelled || isLoggedIn !== 'true' || !userRole || !userToken) {
                    return;
                }

                router.replace(getDashboardRouteForRole(userRole) as any);
            } catch (error) {
                console.warn('⚠️ Existing session check failed:', error);
            }
        };

        checkExistingSession();
        return () => {
            cancelled = true;
        };
    }, [router]);

    const checkSavedCredentials = async () => {
        try {
            const keys = getStorageKeys();
            
            // Use helper function to avoid type casting errors
            const savedEmail = await getAsyncStorageItem(keys.savedEmail);
            const savedPassword = await getAsyncStorageItem(keys.savedPassword);
            const savedRememberMe = await getAsyncStorageItem(keys.rememberMe);
            
            // Check if rememberMe is enabled and we have credentials
            if (savedRememberMe === 'true' && savedEmail && savedPassword) {
                setEmail(savedEmail);
                setPassword(savedPassword);
                setRememberMe(true);
                
                console.log(`✅ Loaded saved credentials for ${role}:`, savedEmail);
            } else {
                console.log(`ℹ️ No saved credentials found for ${role}`);
            }
        } catch (error) {
            console.error('Error loading saved credentials:', error);
            // Clear any corrupt data
            await clearLoginData(role);
        }
    };

    // ✅ VALIDATE FORM
    const validateForm = (): boolean => {
        const errors: { email?: string; password?: string } = {};
        
        if (!email.trim()) {
            errors.email = t('login.error_email_required');
        } else if (!validateEmail(email)) {
            errors.email = t('login.error_email_invalid');
        }
        
        if (!password) {
            errors.password = t('login.error_password_required');
        } else if (password.length < 6) {
            errors.password = t('login.error_password_length');
        }
        
        setFormErrors(errors);
        return Object.keys(errors).length === 0;
    };

    // ✅ MAIN LOGIN FUNCTION - KUUNGANA NA BACKEND YAKO
    const handleLogin = async () => {
        // Clear previous errors
        setFormErrors({});
        
        // Validate form
        if (!validateForm()) {
            return;
        }

        setLoading(true);

        try {
            const sanitizedEmail = sanitizeInput(email);
            const backendRole = getBackendRole(role);

            console.log('📤 Sending login request to backend...', {
                email: sanitizedEmail,
                role: backendRole,
                endpoint: `${API_BASE_URL}/api/login`
            });

            // ✅ KU-TUMIA BACKEND YAKO DIRECTLY - include language
            // Tries the primary host first, then the IPv4-only fallback host, so a
            // carrier that mishandles IPv6 (e.g. Halotel) can still reach the API.
            const response = await fetchApiWithFallback('/api/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    email: sanitizedEmail,
                    password: password,
                    role: backendRole,
                    language: lang
                }),
            }, API_FETCH_TIMEOUT_MS);

            // Check if response is OK
            if (!response.ok) {
                let errorMessage = t('login.error_general');
                
                try {
                    const errorData = await response.json();
                    errorMessage = errorData.error || `${t('login.error_general')}: ${response.status}`;
                } catch {
                    errorMessage = `${t('login.error_server')}`;
                }
                
                throw new Error(errorMessage);
            }

            // Parse response
            const data = await response.json();
            console.log('✅ Login response:', data);

            // Validate response structure
            if (!data.token || !data.user) {
                throw new Error(t('login.error_response_invalid'));
            }

            const user = data.user;
            const token = data.token;

            // Check user status
            if (user.status && user.status !== 'approved') {
                let statusMessage = '';
                switch (user.status) {
                    case 'pending':
                        statusMessage = t('login.account_pending_message');
                        break;
                    case 'rejected':
                        statusMessage = t('login.account_rejected_message');
                        break;
                    default:
                        statusMessage = t('login.account_pending_message');
                }
                
                Alert.alert(t('login.account_pending_title'), statusMessage);
                setLoading(false);
                return;
            }

            // ✅ SAVE USER DATA TO ASYNC STORAGE - ENSURING ALL VALUES ARE STRINGS
            const userDataToSave = {
                userToken: token,
                userData: JSON.stringify(user),
                userId: String(user.id), // CONVERT TO STRING
                userEmail: user.email,
                userRole: user.role,
                userStatus: user.status || 'approved',
                userName: user.full_name || user.email.split('@')[0],
                userLanguage: lang, // Save selected language
                isLoggedIn: 'true', // 🔒 Persistent login: stays logged in until Logout
                ...(user.business_name && { userBusiness: user.business_name })
            };

            // Save all data as key-value pairs
            const storageEntries = Object.entries(userDataToSave);
            await AsyncStorage.multiSet(storageEntries as [string, string][]);

            // 🔐 Tell the session gate the user is now logged in, so the role
            // dashboards become accessible and the home screen is locked out.
            signIn(user.role);

            // ✅ HANDLE REMEMBER ME
            const keys = getStorageKeys();
            if (rememberMe) {
                await AsyncStorage.setItem(keys.savedEmail, sanitizedEmail);
                await AsyncStorage.setItem(keys.savedPassword, password);
                await AsyncStorage.setItem(keys.rememberMe, 'true');
                console.log(`✅ Saved credentials for ${role}`);
            } else {
                // Remove only for current role
                await AsyncStorage.multiRemove([keys.savedEmail, keys.savedPassword, keys.rememberMe]);
                console.log(`✅ Removed saved credentials for ${role}`);
            }

            // ✅ DETERMINE DASHBOARD ROUTE (explicit per-role screen, e.g. /muuzaji/profaili)
            const dashboardRoute = getDashboardRouteForRole(user.role);
            const userName = user.full_name || user.business_name || user.email.split('@')[0];

            // Show success message
            Alert.alert(t('login.success_title'), t('login.success_message', { userName }), [
                {
                    text: t('login.success_button'),
                    onPress: () => {
                        // Navigate to appropriate dashboard
                        router.replace(dashboardRoute as any);
                    }
                }
            ]);

        } catch (error: any) {
            console.error('❌ Login Error:', error);
            
            let errorMessage = t('login.error_general');
            const rawMessage =
                typeof error?.message === 'string' ? error.message : String(error ?? '');

            // Specific error handling
            if (isAbortError(error)) {
                // The request never completed: the connection stalled until it timed
                // out. React Native surfaces this as a bare "Aborted" message.
                errorMessage = t('login.error_timeout');
            } else if (rawMessage.includes('Akaunti haipo')) {
                errorMessage = t('login.error_account_not_found');
            } else if (rawMessage.includes('Password si sahihi')) {
                errorMessage = t('login.error_password_wrong');
            } else if (rawMessage.includes('401')) {
                errorMessage = t('login.error_account_not_found');
            } else if (rawMessage.includes('403')) {
                errorMessage = t('login.error_unauthorized');
            } else if (rawMessage.includes('404')) {
                errorMessage = t('login.error_service_unavailable');
            } else if (rawMessage.includes('500')) {
                errorMessage = t('login.error_server');
            } else if (rawMessage.includes('network')) {
                errorMessage = t('login.error_network');
            } else {
                errorMessage = rawMessage || t('login.error_general');
            }

            // Debug aid: record the host that was attempted so a carrier-specific
            // failure can be diagnosed from device logs.
            console.warn('Login failed against', API_BASE_URL, errorMessage);

            Alert.alert(t('app.error'), errorMessage);
        } finally {
            setLoading(false);
        }
    };

    const handleForgotPassword = () => {
        router.push({
            pathname: '/forgot',
            params: { role }
        });
    };

    const handleSignup = () => {
        const signupRoute = getSignupRoute(role);
        router.push({
            pathname: signupRoute as any,
            params: { role, lang }  // Pass language to signup
        });
    };

    const handleBack = () => {
        router.back();
    };

    const getRoleTitle = (): string => {
        switch (role) {
            case 'mteja': return t('login.role_badge_title');
            case 'muuzaji': return t('login.role_badge_seller');
            case 'msimamizi': return t('login.role_badge_admin');
            case 'washa': return t('login.role_badge_washa');
            default: return t('user_roles.customer').toUpperCase();
        }
    };

    const getRoleColor = (): string => {
        switch (role) {
            case 'mteja': return '#3498db';
            case 'muuzaji': return '#2ecc71';
            case 'msimamizi': return '#e74c3c';
            case 'washa': return '#9b59b6';
            default: return '#2c3e50';
        }
    };

    const getRoleDescription = (): string => {
        switch (role) {
            case 'mteja': return t('login.role_customer_description');
            case 'muuzaji': return t('login.role_seller_description');
            case 'msimamizi': return t('login.role_admin_description');
            case 'washa': return t('login.role_washa_description');
            default: return t('login.title');
        }
    };

    const getSignupButtonText = (): string => {
        switch (role) {
            case 'mteja': return t('login.signup_customer');
            case 'muuzaji': return t('login.signup_seller');
            case 'msimamizi': return t('login.signup_admin');
            case 'washa': return t('login.signup_washa');
            default: return t('login.signup_customer');
        }
    };

    return (
        <KeyboardAvoidingView 
            style={styles.keyboardAvoid}
            behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        >
            <ScrollView 
                contentContainerStyle={styles.scrollContainer}
                showsVerticalScrollIndicator={false}
                keyboardShouldPersistTaps="handled"
            >
                <View style={styles.container}>
                    {/* Header Section */}
                    <View style={styles.headerSection}>
                        <TouchableOpacity 
                            style={styles.backButton}
                            onPress={handleBack}
                        >
                            <Ionicons name="arrow-back" size={24} color="#2c3e50" />
                        </TouchableOpacity>
                        
                        <View style={styles.roleBadge}>
                            <Text style={[styles.roleBadgeText, { color: getRoleColor() }]}>
                                {getRoleTitle()}
                            </Text>
                        </View>
                    </View>

                    {/* Main Content */}
                    <View style={styles.content}>
                        <View style={styles.avatarContainer}>
                            <View style={[styles.avatar, { backgroundColor: getRoleColor() }]}>
                                <Ionicons 
                                    name={
                                        role === 'mteja' ? 'person' :
                                        role === 'muuzaji' ? 'cart' :
                                        role === 'msimamizi' ? 'shield' :
                                        'flash'
                                    } 
                                    size={40} 
                                    color="white" 
                                />
                            </View>
                            <Text style={styles.welcomeText}>{t('login.welcome_back')}</Text>
                            <Text style={styles.roleDescription}>{getRoleDescription()}</Text>
                        </View>

                        {/* Login Form */}
                        <View style={styles.formContainer}>
                            {/* Email Input */}
                            <View style={styles.inputContainer}>
                                <TouchableOpacity 
                                    style={styles.inputLabel}
                                    activeOpacity={0.9}
                                    onPress={() => emailInputRef.current?.focus()}
                                >
                                    <Ionicons name="mail-outline" size={20} color="#7f8c8d" />
                                    <Text style={styles.labelText}>{t('login.email_label')}</Text>
                                </TouchableOpacity>
                                <TextInput
                                    ref={emailInputRef}
                                    style={[styles.input, formErrors.email && styles.inputError]}
                                    placeholder={t('login.email_placeholder')}
                                    value={email}
                                    onChangeText={(text) => {
                                        setEmail(text);
                                        if (formErrors.email) setFormErrors({...formErrors, email: undefined});
                                    }}
                                    keyboardType="email-address"
                                    autoCapitalize="none"
                                    autoCorrect={false}
                                    autoComplete="email"
                                    cursorColor="#2c3e50"
                                    selectionColor="rgba(52, 152, 219, 0.3)"
                                    textAlignVertical="center"
                                    placeholderTextColor="#95a5a6"
                                    returnKeyType="next"
                                    onSubmitEditing={() => passwordInputRef.current?.focus()}
                                    blurOnSubmit={false}
                                />
                                {formErrors.email && (
                                    <Text style={styles.errorText}>{formErrors.email}</Text>
                                )}
                            </View>

                            {/* Password Input */}
                            <View style={styles.inputContainer}>
                                <TouchableOpacity 
                                    style={styles.inputLabel}
                                    activeOpacity={0.9}
                                    onPress={() => passwordInputRef.current?.focus()}
                                >
                                    <Ionicons name="lock-closed-outline" size={20} color="#7f8c8d" />
                                    <Text style={styles.labelText}>{t('login.password_label')}</Text>
                                </TouchableOpacity>
                                <View style={styles.passwordContainer}>
                                    <TextInput
                                        ref={passwordInputRef}
                                        style={[styles.input, styles.passwordInput, formErrors.password && styles.inputError]}
                                        placeholder={t('login.password_placeholder')}
                                        value={password}
                                        onChangeText={(text) => {
                                            setPassword(text);
                                            if (formErrors.password) setFormErrors({...formErrors, password: undefined});
                                        }}
                                        secureTextEntry={!showPassword}
                                        autoCapitalize="none"
                                        autoCorrect={false}
                                        autoComplete="password"
                                        cursorColor="#2c3e50"
                                        selectionColor="rgba(52, 152, 219, 0.3)"
                                        textAlignVertical="center"
                                        placeholderTextColor="#95a5a6"
                                        returnKeyType="done"
                                        onSubmitEditing={handleLogin}
                                    />
                                    <TouchableOpacity 
                                        style={styles.eyeButton}
                                        onPress={() => setShowPassword(!showPassword)}
                                    >
                                        <Ionicons 
                                            name={showPassword ? 'eye-off-outline' : 'eye-outline'} 
                                            size={22} 
                                            color="#7f8c8d" 
                                        />
                                    </TouchableOpacity>
                                </View>
                                {formErrors.password && (
                                    <Text style={styles.errorText}>{formErrors.password}</Text>
                                )}
                            </View>

                            {/* Remember Me & Forgot Password */}
                            <View style={styles.rememberForgotContainer}>
                                <TouchableOpacity 
                                    style={styles.rememberMeButton}
                                    onPress={() => setRememberMe(!rememberMe)}
                                >
                                    <View style={[styles.checkbox, rememberMe && styles.checkboxChecked]}>
                                        {rememberMe && (
                                            <Ionicons name="checkmark" size={16} color="white" />
                                        )}
                                    </View>
                                    <Text style={styles.rememberMeText}>{t('login.remember_me')}</Text>
                                </TouchableOpacity>

                                <TouchableOpacity onPress={handleForgotPassword}>
                                    <Text style={[styles.forgotPasswordText, { color: getRoleColor() }]}>
                                        {t('login.forgot_password')}
                                    </Text>
                                </TouchableOpacity>
                            </View>

                            {/* Login Button */}
                            <TouchableOpacity 
                                style={[styles.loginButton, { backgroundColor: getRoleColor() }]}
                                onPress={handleLogin}
                                disabled={loading}
                                activeOpacity={0.8}
                            >
                                {loading ? (
                                    <ActivityIndicator size="small" color="white" />
                                ) : (
                                    <>
                                        <Ionicons name="log-in-outline" size={22} color="white" />
                                        <Text style={styles.loginButtonText}>{t('login.title')}</Text>
                                    </>
                                )}
                            </TouchableOpacity>

                            {/* Signup Link */}
                            <View style={styles.signupContainer}>
                                <Text style={styles.signupText}>{t('login.no_account')}</Text>
                                <TouchableOpacity onPress={handleSignup}>
                                    <Text style={[styles.signupLink, { color: getRoleColor() }]}>
                                        {getSignupButtonText()}
                                    </Text>
                                </TouchableOpacity>
                            </View>
                        </View>

                        {/* Security Info */}
                        <View style={styles.securityInfo}>
                            <Ionicons name="shield-checkmark-outline" size={18} color="#3498db" />
                            <Text style={styles.securityText}>
                                {t('login.security_note')}
                            </Text>
                        </View>
                    </View>
                </View>
            </ScrollView>
        </KeyboardAvoidingView>
    );
}

const styles = StyleSheet.create({
    keyboardAvoid: {
        flex: 1,
        backgroundColor: '#ffffff',
    },
    scrollContainer: {
        flexGrow: 1,
    },
    container: {
        flex: 1,
        backgroundColor: '#ffffff',
    },
    headerSection: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        paddingHorizontal: 20,
        paddingTop: Platform.OS === 'android' ? 40 : 50,
        paddingBottom: 15,
    },
    backButton: {
        width: 40,
        height: 40,
        justifyContent: 'center',
        alignItems: 'center',
        borderRadius: 20,
        backgroundColor: '#f5f5f5',
    },
    roleBadge: {
        paddingHorizontal: 15,
        paddingVertical: 8,
        borderRadius: 20,
        backgroundColor: '#f5f5f5',
    },
    roleBadgeText: {
        fontSize: 14,
        fontWeight: 'bold',
        textTransform: 'uppercase',
    },
    content: {
        flex: 1,
        paddingHorizontal: 25,
        paddingTop: 10,
    },
    avatarContainer: {
        alignItems: 'center',
        marginBottom: 20,
    },
    avatar: {
        width: 80,
        height: 80,
        borderRadius: 40,
        justifyContent: 'center',
        alignItems: 'center',
        marginBottom: 15,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.1,
        shadowRadius: 8,
        elevation: 5,
    },
    welcomeText: {
        fontSize: 28,
        fontWeight: 'bold',
        color: '#2c3e50',
        marginBottom: 8,
    },
    roleDescription: {
        fontSize: 16,
        color: '#7f8c8d',
        textAlign: 'center',
        lineHeight: 22,
    },
    formContainer: {
        width: '100%',
        marginBottom: 30,
    },
    inputContainer: {
        marginBottom: 20,
    },
    inputLabel: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 8,
    },
    labelText: {
        fontSize: 14,
        fontWeight: '600',
        color: '#2c3e50',
        marginLeft: 8,
    },
    input: {
        width: '100%',
        height: 56,
        backgroundColor: '#f8f9fa',
        borderRadius: 12,
        paddingHorizontal: 20,
        fontSize: 16,
        color: '#2c3e50',
        borderWidth: 1,
        borderColor: '#e0e0e0',
    },
    inputError: {
        borderColor: '#e74c3c',
        borderWidth: 2,
        backgroundColor: '#fff5f5',
    },
    errorText: {
        fontSize: 12,
        color: '#e74c3c',
        marginTop: 5,
        marginLeft: 5,
    },
    passwordContainer: {
        position: 'relative',
    },
    passwordInput: {
        paddingRight: 50,
    },
    eyeButton: {
        position: 'absolute',
        right: 15,
        top: 17,
    },
    rememberForgotContainer: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 25,
    },
    rememberMeButton: {
        flexDirection: 'row',
        alignItems: 'center',
    },
    checkbox: {
        width: 22,
        height: 22,
        borderRadius: 6,
        borderWidth: 2,
        borderColor: '#ddd',
        marginRight: 10,
        justifyContent: 'center',
        alignItems: 'center',
    },
    checkboxChecked: {
        backgroundColor: '#3498db',
        borderColor: '#3498db',
    },
    rememberMeText: {
        fontSize: 14,
        color: '#2c3e50',
    },
    forgotPasswordText: {
        fontSize: 14,
        fontWeight: '600',
    },
    loginButton: {
        width: '100%',
        height: 58,
        flexDirection: 'row',
        justifyContent: 'center',
        alignItems: 'center',
        borderRadius: 12,
        marginBottom: 20,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.15,
        shadowRadius: 8,
        elevation: 5,
    },
    loginButtonText: {
        fontSize: 18,
        fontWeight: 'bold',
        color: 'white',
        marginLeft: 10,
        letterSpacing: 0.5,
    },
    signupContainer: {
        flexDirection: 'row',
        justifyContent: 'center',
        alignItems: 'center',
        marginBottom: 30,
    },
    signupText: {
        fontSize: 15,
        color: '#7f8c8d',
        marginRight: 5,
    },
    signupLink: {
        fontSize: 15,
        fontWeight: '600',
    },
    securityInfo: {
        flexDirection: 'row',
        justifyContent: 'center',
        alignItems: 'center',
        padding: 15,
        backgroundColor: '#f0f8ff',
        borderRadius: 12,
        marginBottom: 30,
    },
    securityText: {
        fontSize: 14,
        color: '#3498db',
        marginLeft: 10,
    },
});