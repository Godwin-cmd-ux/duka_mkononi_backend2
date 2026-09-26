import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import {
    ActivityIndicator,
    Alert,
    KeyboardAvoidingView,
    Platform,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View
} from 'react-native';
import { useLang } from '../context/LanguageContext';
import OtpVerificationModal from '../components/otp-verification-modal';

import { API_BASE_URL } from '../constants/api';
import { requireNetwork, fetchWithTimeout } from '../lib/network';

export default function CashierSignup() {
    const router = useRouter();
    const params = useLocalSearchParams();
    const lang = (params.lang as string) || 'sw';
    const { t } = useLang();
    const [loading, setLoading] = useState(false);
    // OTP email verification state
    const [otpVisible, setOtpVisible] = useState(false);
    const [pendingOtp, setPendingOtp] = useState<{
        email: string;
        role: string;
    } | null>(null);
    const [formData, setFormData] = useState({
        email: '',
        password: '',
        confirmPassword: '',
        full_name: '',
        phone: '',
        business_name: '',
        business_location: ''
    });

    const handleSignup = async () => {
        if (!(await requireNetwork())) return;

        // 1. Validation ya msingi
        if (!formData.email || !formData.password || !formData.full_name || 
            !formData.business_name || !formData.business_location) {
            Alert.alert(t('app.error'), t('seller_signup.error_required'));
            return;
        }

        // 2. Validate email format
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(formData.email)) {
            Alert.alert(t('app.error'), t('seller_signup.error_invalid_email'));
            return;
        }

        // 3. Angalia kama nenosiri zinapatana
        if (formData.password !== formData.confirmPassword) {
            Alert.alert(t('app.error'), t('seller_signup.error_password_mismatch'));
            return;
        }

        // 4. Angalia urefu wa nenosiri
        if (formData.password.length < 6) {
            Alert.alert(t('app.error'), t('seller_signup.error_password_mismatch'));
            return;
        }

        setLoading(true);

        try {
            // 5. Kwanza angalia kama biashara ipo na ina msimamizi
            console.log(`🔍 Checking business: ${formData.business_name}`);
            
            const checkResponse = await fetchWithTimeout(`${API_BASE_URL}/api/check-business`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    business_name: formData.business_name.trim()
                }),
            });

            const checkData = await checkResponse.json();
            console.log('📊 Business check result:', checkData);

            if (!checkResponse.ok) {
                Alert.alert(t('app.error'), checkData.error || t('seller_signup.error_business_check'));
                setLoading(false);
                return;
            }

            // Kama biashara haipo kabisa
            if (!checkData.exists) {
                Alert.alert(
                    t('seller_signup.business_not_found_title'), 
                    `Biashara "${formData.business_name}" ${t('seller_signup.error_business_not_found')}`
                );
                setLoading(false);
                return;
            }

            // Kama biashara ipo lakini haina msimamizi aliyethibitishwa
            if (!checkData.hasAdmin) {
                Alert.alert(
                    t('seller_signup.business_no_admin_title'), 
                    `Biashara "${formData.business_name}" ${t('seller_signup.error_business_no_admin')}`
                );
                setLoading(false);
                return;
            }

            // 6. Endelea na usajili wa muuzaji (biashara ipo na ina msimamizi)
            console.log(`✅ Business check passed, proceeding with seller registration...`);
            
            const response = await fetchWithTimeout(`${API_BASE_URL}/api/register/initiate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: formData.email,
                    password: formData.password,
                    role: 'seller',
                    full_name: formData.full_name,
                    phone: formData.phone,
                    business_name: formData.business_name,
                    business_location: formData.business_location,
                    language: lang // Pass selected language
                }),
            });

            const data = await response.json();
            console.log('📨 Registration response:', data);

            if (response.ok && data.requiresOtp) {
                // ✅ OTP step — a 6-digit code was emailed to the user.
                // The account is created only after the code is verified.
                setPendingOtp({
                    email: formData.email,
                    role: 'seller'
                });
                setOtpVisible(true);
                if (data.email_sent === false) {
                    Alert.alert(t('otp.sent_title'), t('otp.email_failed'));
                }
            } else {
                // 7. Kukagua hitilafu maalum kutoka kwa backend
                const errorMessage = data.error || 'Hitilafu imetokea wakati wa kujisajili';
                
                if (errorMessage.includes('Biashara hii haina msimamizi')) {
                    Alert.alert(
                        t('seller_signup.business_no_admin_title'), 
                        t('seller_signup.error_business_no_admin')
                    );
                } else if (errorMessage.includes('Akaunti na barua pepe hii tayari ipo')) {
                    Alert.alert(t('app.error'), t('seller_signup.error_email_taken'));
                } else if (errorMessage.includes('Jina la biashara tayari limeshasajiliwa')) {
                    Alert.alert(t('app.error'), t('seller_signup.error_business_check'));
                } else {
                    Alert.alert(t('app.error'), errorMessage);
                }
            }
        } catch (error: any) {
            console.error('❌ Signup error:', error);
            
            if (error.message && error.message.includes('Network request failed')) {
                Alert.alert(t('app.error'), t('seller_signup.error_network'));
            } else {
                Alert.alert(t('app.error'), t('seller_signup.error_general'));
            }
        } finally {
            setLoading(false);
        }
    };

    // ✅ Called when the email OTP verifies — creates the account with the
    // verified token minted by /api/register/verify-otp. On failure it throws
    // so the modal can show the error and let the user retry.
    const handleOtpVerified = async (data: any) => {
        if (!(await requireNetwork())) return;

        const token = data?.registrationToken;
        if (!token) {
            throw new Error(t('seller_signup.error_general'));
        }

        try {
            const response = await fetchWithTimeout(`${API_BASE_URL}/api/register`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'ngrok-skip-browser-warning': 'true'
                },
                body: JSON.stringify({
                    verificationToken: token,
                    email: formData.email // Cross-check with the verified email
                }),
            });

            const result = await response.json().catch(() => ({}));

            if (!response.ok) {
                let errorMessage = result?.error || t('seller_signup.error_general');
                if (String(errorMessage).includes('tayari ipo')) {
                    errorMessage = t('seller_signup.error_email_taken');
                } else if (String(errorMessage).includes('Biashara hii haina msimamizi')) {
                    errorMessage = t('seller_signup.error_business_no_admin');
                }
                throw new Error(errorMessage);
            }

            // ✅ Account created — close the modal and open the optional
            // onboarding slideshow (profile photo) before sending the seller to login.
            setOtpVisible(false);

            router.replace({
                pathname: '/onboarding',
                params: {
                    email: formData.email,
                    role: 'seller',
                    setupToken: result?.setupToken || '',
                    lang,
                    business_name: formData.business_name,
                    business_location: formData.business_location,
                },
            } as any);
        } catch (error: any) {
            console.error('❌ Account creation error:', error);
            if (error?.message?.includes('Network request failed')) {
                throw new Error(t('seller_signup.error_network'));
            }
            throw error;
        }
    };

    const updateFormData = (field: string, value: string) => {
        setFormData(prev => ({
            ...prev,
            [field]: value
        }));
    };

    return (
        <KeyboardAvoidingView 
            style={styles.keyboardAvoid}
            behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        >
            <ScrollView 
                contentContainerStyle={styles.container}
                showsVerticalScrollIndicator={false}
                keyboardShouldPersistTaps="handled"
            >
                <View style={styles.headerContainer}>
                    <TouchableOpacity 
                        style={styles.topBackButton}
                        onPress={() => router.back()}
                    >
                        <Ionicons name="arrow-back" size={24} color="#2c3e50" />
                    </TouchableOpacity>
                    <Text style={styles.header}>{t('seller_signup.title')}</Text>
                    <Text style={styles.subtitle}>{t('seller_signup.subtitle')}</Text>
                </View>

                <View style={styles.formContainer}>
                    <TextInput
                        style={styles.input}
                        placeholder={t('seller_signup.full_name_placeholder')}
                        value={formData.full_name}
                        onChangeText={(value) => updateFormData('full_name', value)}
                        placeholderTextColor="#95a5a6"
                        cursorColor="#2ecc71"
                        selectionColor="rgba(46, 204, 113, 0.3)"
                        textAlignVertical="center"
                    />

                    <TextInput
                        style={styles.input}
                        placeholder={t('seller_signup.business_name_placeholder')}
                        value={formData.business_name}
                        onChangeText={(value) => updateFormData('business_name', value)}
                        placeholderTextColor="#95a5a6"
                        cursorColor="#2ecc71"
                        selectionColor="rgba(46, 204, 113, 0.3)"
                        textAlignVertical="center"
                    />

                    <TextInput
                        style={styles.input}
                        placeholder={t('seller_signup.business_location_placeholder')}
                        value={formData.business_location}
                        onChangeText={(value) => updateFormData('business_location', value)}
                        placeholderTextColor="#95a5a6"
                        cursorColor="#2ecc71"
                        selectionColor="rgba(46, 204, 113, 0.3)"
                        textAlignVertical="center"
                    />

                    <TextInput
                        style={styles.input}
                        placeholder={t('seller_signup.email_placeholder')}
                        value={formData.email}
                        onChangeText={(value) => updateFormData('email', value)}
                        keyboardType="email-address"
                        autoCapitalize="none"
                        autoComplete="email"
                        placeholderTextColor="#95a5a6"
                        cursorColor="#2ecc71"
                        selectionColor="rgba(46, 204, 113, 0.3)"
                        textAlignVertical="center"
                    />

                    <TextInput
                        style={styles.input}
                        placeholder={t('seller_signup.phone_placeholder')}
                        value={formData.phone}
                        onChangeText={(value) => updateFormData('phone', value)}
                        keyboardType="phone-pad"
                        placeholderTextColor="#95a5a6"
                        cursorColor="#2ecc71"
                        selectionColor="rgba(46, 204, 113, 0.3)"
                        textAlignVertical="center"
                    />

                    <TextInput
                        style={styles.input}
                        placeholder={t('seller_signup.password_placeholder')}
                        value={formData.password}
                        onChangeText={(value) => updateFormData('password', value)}
                        secureTextEntry
                        placeholderTextColor="#95a5a6"
                        cursorColor="#2ecc71"
                        selectionColor="rgba(46, 204, 113, 0.3)"
                        textAlignVertical="center"
                    />

                    <TextInput
                        style={styles.input}
                        placeholder={t('seller_signup.confirm_password_placeholder')}
                        value={formData.confirmPassword}
                        onChangeText={(value) => updateFormData('confirmPassword', value)}
                        secureTextEntry
                        placeholderTextColor="#95a5a6"
                        cursorColor="#2ecc71"
                        selectionColor="rgba(46, 204, 113, 0.3)"
                        textAlignVertical="center"
                    />

                    <TouchableOpacity 
                        style={[styles.signupButton, { backgroundColor: '#2ecc71' }]}
                        onPress={handleSignup}
                        disabled={loading}
                    >
                        {loading ? (
                            <ActivityIndicator color="white" />
                        ) : (
                            <Text style={styles.signupButtonText}>{t('seller_signup.signup_button')}</Text>
                        )}
                    </TouchableOpacity>

                    <TouchableOpacity 
                        style={styles.backButton}
                        onPress={() => router.back()}
                    >
                        <Text style={styles.backButtonText}>{t('seller_signup.back_button')}</Text>
                    </TouchableOpacity>
                </View>

                <View style={styles.infoContainer}>
                    <Text style={styles.infoText}>
                        🔒 {t('seller_signup.info_title')}
                    </Text>
                    <Text style={styles.infoPoint}>
                        • {t('seller_signup.info_point1')}
                    </Text>
                    <Text style={styles.infoPoint}>
                        • {t('seller_signup.info_point2')}
                    </Text>
                    <Text style={styles.infoPoint}>
                        • {t('seller_signup.info_point3')}
                    </Text>
                    <Text style={styles.infoPoint}>
                        • {t('seller_signup.info_point4')}
                    </Text>
                </View>
            </ScrollView>

            {/* Email OTP verification modal */}
            <OtpVerificationModal
                visible={otpVisible}
                email={pendingOtp?.email || ''}
                role={pendingOtp?.role || 'seller'}
                accentColor="#2ecc71"
                onVerified={handleOtpVerified}
                onClose={() => setOtpVisible(false)}
            />
        </KeyboardAvoidingView>
    );
}

const styles = StyleSheet.create({
    keyboardAvoid: {
        flex: 1,
    },
    container: {
        flexGrow: 1,
        justifyContent: 'center',
        alignItems: 'center',
        backgroundColor: '#f8f9fa',
        padding: 20,
        minHeight: 900,
    },
    topBackButton: {
        position: 'absolute',
        left: 0,
        top: 0,
        width: 40,
        height: 40,
        justifyContent: 'center',
        alignItems: 'center',
        borderRadius: 20,
        backgroundColor: '#f5f5f5',
        zIndex: 10,
    },
    headerContainer: {
        position: 'relative',
        width: '100%',
        alignItems: 'center',
        marginBottom: 30,
        paddingTop: 10,
    },
    header: {
        fontSize: 24,
        fontWeight: 'bold',
        textAlign: 'center',
        marginBottom: 5,
        color: '#2ecc71',
        textTransform: 'uppercase',
    },
    subtitle: {
        fontSize: 12,
        color: '#7f8c8d',
        marginBottom: 30,
        textAlign: 'center',
    },
    formContainer: {
        width: '100%',
        alignItems: 'center',
        gap: 15,
    },
    input: {
        width: '100%',
        height: 50,
        backgroundColor: 'white',
        borderRadius: 12,
        paddingHorizontal: 15,
        fontSize: 16,
        borderWidth: 1,
        borderColor: '#ddd',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.1,
        shadowRadius: 2,
        elevation: 2,
    },
    signupButton: {
        width: '100%',
        height: 60,
        justifyContent: 'center',
        alignItems: 'center',
        borderRadius: 12,
        marginTop: 10,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.25,
        shadowRadius: 3.84,
        elevation: 5,
    },
    signupButtonText: {
        fontSize: 18,
        fontWeight: 'bold',
        color: 'white',
        textTransform: 'uppercase',
    },
    backButton: {
        width: '100%',
        height: 50,
        justifyContent: 'center',
        alignItems: 'center',
        borderRadius: 12,
        borderWidth: 2,
        borderColor: '#3498db',
        backgroundColor: 'transparent',
    },
    backButtonText: {
        fontSize: 16,
        fontWeight: 'bold',
        color: '#3498db',
        textTransform: 'uppercase',
    },
    infoContainer: {
        marginTop: 20,
        padding: 15,
        backgroundColor: '#e8f6ef',
        borderRadius: 12,
        width: '100%',
    },
    infoText: {
        fontSize: 14,
        fontWeight: 'bold',
        color: '#27ae60',
        marginBottom: 8,
    },
    infoPoint: {
        fontSize: 12,
        color: '#27ae60',
        marginBottom: 4,
    },
});