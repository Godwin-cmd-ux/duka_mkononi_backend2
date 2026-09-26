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

export default function AdminSignup() {
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
        full_name: '',      // 🔄 Kubadilishwa
        phone: '',
        business_name: '',  // 🔄 Kubadilishwa
        business_location: '', // 🔄 Kubadilishwa
        adminCode: ''
    });

    const handleSignup = async () => {
        if (!(await requireNetwork())) return;

        // Validation
        if (!formData.email || !formData.password || !formData.full_name || 
            !formData.business_name || !formData.business_location || !formData.adminCode) {
            Alert.alert(t('app.error'), t('admin_signup.error_required'));
            return;
        }

        // Validate email format
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(formData.email)) {
            Alert.alert(t('app.error'), t('admin_signup.error_invalid_email'));
            return;
        }

        if (formData.password !== formData.confirmPassword) {
            Alert.alert(t('app.error'), t('admin_signup.error_password_mismatch'));
            return;
        }

        if (formData.password.length < 6) {
            Alert.alert(t('app.error'), t('admin_signup.error_password_mismatch'));
            return;
        }

        // Check admin code
        if (formData.adminCode !== 'ADMIN2024') {
            Alert.alert(t('app.error'), t('admin_signup.error_wrong_admin_code'));
            return;
        }

        setLoading(true);

        try {
            const response = await fetchWithTimeout(`${API_BASE_URL}/api/register/initiate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: formData.email,
                    password: formData.password,
                    role: 'admin',
                    full_name: formData.full_name,
                    phone: formData.phone,
                    business_name: formData.business_name,
                    business_location: formData.business_location,
                    language: lang // Pass selected language
                }),
            });

            const data = await response.json();

            if (response.ok && data.requiresOtp) {
                // ✅ OTP step — a 6-digit code was emailed to the user.
                // The account is created only after the code is verified.
                setPendingOtp({
                    email: formData.email,
                    role: 'admin'
                });
                setOtpVisible(true);
                if (data.email_sent === false) {
                    Alert.alert(t('otp.sent_title'), t('otp.email_failed'));
                }
            } else {
                Alert.alert(t('app.error'), data.error || t('admin_signup.error_general'));
            }
        } catch (error: any) {
            console.error('Signup error:', error);
            
            if (error.message.includes('Network request failed')) {
                Alert.alert(t('app.error'), t('admin_signup.error_network'));
            } else {
                Alert.alert(t('app.error'), t('admin_signup.error_required'));
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
            throw new Error(t('admin_signup.error_general'));
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
                throw new Error(result?.error || t('admin_signup.error_general'));
            }

            // ✅ Account created — close the modal and open the optional
            // onboarding slideshow (profile photo → Google Map location) before
            // sending the admin to login.
            setOtpVisible(false);

            router.replace({
                pathname: '/onboarding',
                params: {
                    email: formData.email,
                    role: 'admin',
                    setupToken: result?.setupToken || '',
                    lang,
                    business_name: formData.business_name,
                    business_location: formData.business_location,
                },
            } as any);
        } catch (error: any) {
            console.error('❌ Account creation error:', error);
            if (error?.message?.includes('Network request failed')) {
                throw new Error(t('admin_signup.error_network'));
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
            behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        >
            <ScrollView contentContainerStyle={styles.container}>
                <Text style={styles.header}>{t('admin_signup.title')}</Text>
                <Text style={styles.subtitle}>{t('admin_signup.subtitle')}</Text>

                <View style={styles.formContainer}>
                    <TextInput
                        style={styles.input}
                        placeholder={t('admin_signup.full_name_placeholder')}
                        value={formData.full_name}
                        onChangeText={(value) => updateFormData('full_name', value)}
                        placeholderTextColor="#95a5a6"
                    />

                    <TextInput
                        style={styles.input}
                        placeholder={t('admin_signup.business_name_placeholder')}
                        value={formData.business_name}
                        onChangeText={(value) => updateFormData('business_name', value)}
                        placeholderTextColor="#95a5a6"
                    />

                    <TextInput
                        style={styles.input}
                        placeholder={t('admin_signup.business_location_placeholder')}
                        value={formData.business_location}
                        onChangeText={(value) => updateFormData('business_location', value)}
                        placeholderTextColor="#95a5a6"
                    />

                    <TextInput
                        style={styles.input}
                        placeholder={t('admin_signup.email_placeholder')}
                        value={formData.email}
                        onChangeText={(value) => updateFormData('email', value)}
                        keyboardType="email-address"
                        autoCapitalize="none"
                        placeholderTextColor="#95a5a6"
                    />

                    <TextInput
                        style={styles.input}
                        placeholder={t('admin_signup.phone_placeholder')}
                        value={formData.phone}
                        onChangeText={(value) => updateFormData('phone', value)}
                        keyboardType="phone-pad"
                        placeholderTextColor="#95a5a6"
                    />

                    <TextInput
                        style={styles.input}
                        placeholder={t('admin_signup.admin_code_placeholder')}
                        value={formData.adminCode}
                        onChangeText={(value) => updateFormData('adminCode', value)}
                        secureTextEntry
                        placeholderTextColor="#95a5a6"
                    />

                    <TextInput
                        style={styles.input}
                        placeholder={t('admin_signup.password_placeholder')}
                        value={formData.password}
                        onChangeText={(value) => updateFormData('password', value)}
                        secureTextEntry
                        placeholderTextColor="#95a5a6"
                    />

                    <TextInput
                        style={styles.input}
                        placeholder={t('admin_signup.confirm_password_placeholder')}
                        value={formData.confirmPassword}
                        onChangeText={(value) => updateFormData('confirmPassword', value)}
                        secureTextEntry
                        placeholderTextColor="#95a5a6"
                    />

                    <TouchableOpacity 
                        style={[styles.signupButton, { backgroundColor: '#e74c3c' }]}
                        onPress={handleSignup}
                        disabled={loading}
                    >
                        {loading ? (
                            <ActivityIndicator color="white" />
                        ) : (
                            <Text style={styles.signupButtonText}>{t('admin_signup.signup_button')}</Text>
                        )}
                    </TouchableOpacity>

                    <TouchableOpacity 
                        style={styles.backButton}
                        onPress={() => router.back()}
                    >
                        <Text style={styles.backButtonText}>{t('admin_signup.back_button')}</Text>
                    </TouchableOpacity>
                </View>

                <View style={styles.infoContainer}>
                    <Text style={styles.infoText}>
                        🔑 {t('admin_signup.info_title')}
                    </Text>
                    <Text style={styles.infoPoint}>
                        • {t('admin_signup.info_point1')}
                    </Text>
                    <Text style={styles.infoPoint}>
                        • {t('admin_signup.info_point2')}
                    </Text>
                    <Text style={styles.infoPoint}>
                        • {t('admin_signup.info_point3')}
                    </Text>
                    <Text style={styles.infoPoint}>
                        • {t('admin_signup.info_point4')}
                    </Text>
                </View>
            </ScrollView>

            {/* Email OTP verification modal */}
            <OtpVerificationModal
                visible={otpVisible}
                email={pendingOtp?.email || ''}
                role={pendingOtp?.role || 'admin'}
                accentColor="#e74c3c"
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
    header: {
        fontSize: 24,
        fontWeight: 'bold',
        textAlign: 'center',
        marginBottom: 10,
        color: '#e74c3c',
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
        backgroundColor: '#fdeaea',
        borderRadius: 12,
        width: '100%',
    },
    infoText: {
        fontSize: 14,
        fontWeight: 'bold',
        color: '#e74c3c',
        marginBottom: 8,
    },
    infoPoint: {
        fontSize: 12,
        color: '#e74c3c',
        marginBottom: 4,
    },
});