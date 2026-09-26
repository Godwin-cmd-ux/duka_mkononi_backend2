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

export default function ClientSignup() {
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
        phone: ''
    });

    const handleSignup = async () => {
        if (!(await requireNetwork())) return;

        // Validate form
        if (!formData.email || !formData.password || !formData.full_name) {
            Alert.alert(t('app.error'), t('client_signup.error_required_fields'));
            return;
        }

        if (formData.password !== formData.confirmPassword) {
            Alert.alert(t('app.error'), t('client_signup.error_password_mismatch'));
            return;
        }

        if (formData.password.length < 6) {
            Alert.alert(t('app.error'), t('client_signup.error_password_length'));
            return;
        }

        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(formData.email)) {
            Alert.alert(t('app.error'), t('client_signup.error_invalid_email'));
            return;
        }

        setLoading(true);

        try {
            console.log('📤 Sending registration request to:', `${API_BASE_URL}/api/register`);
            console.log('Registration data:', {
                email: formData.email,
                password: '********', // Don't log actual password
                role: 'customer',
                full_name: formData.full_name,
                phone: formData.phone
            });

            const response = await fetchWithTimeout(`${API_BASE_URL}/api/register/initiate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'ngrok-skip-browser-warning': 'true'
                },
                body: JSON.stringify({
                    email: formData.email,
                    password: formData.password,
                    role: 'customer',
                    full_name: formData.full_name,
                    phone: formData.phone || null,
                    language: lang, // Pass selected language
                }),
            });

            console.log('Response status:', response.status);

            const data = await response.json();
            console.log('Response data:', data);

            if (response.ok && data.requiresOtp) {
                // ✅ OTP step — a 6-digit code was emailed to the user.
                // The account is created only after the code is verified.
                setPendingOtp({
                    email: formData.email,
                    role: 'customer'
                });
                setOtpVisible(true);
                if (data.email_sent === false) {
                    Alert.alert(t('otp.sent_title'), t('otp.email_failed'));
                }
            } else {
                let errorMessage = data.error || t('client_signup.error_general');
                
                // Handle specific errors
                if (errorMessage.includes('tayari ipo')) {
                    errorMessage = t('client_signup.error_account_exists');
                } else if (errorMessage.includes('Email')) {
                    errorMessage = t('client_signup.error_invalid_email');
                }
                
                Alert.alert(t('app.error'), errorMessage);
            }
        } catch (error: any) {
            console.error('❌ Signup error:', error);
            
            let errorMessage = t('client_signup.error_general');
            
            if (error.message.includes('Network request failed')) {
                errorMessage = t('client_signup.error_network');
            } else if (error.message.includes('timeout')) {
                errorMessage = t('client_signup.error_general');
            }
            
            Alert.alert(t('app.error'), errorMessage);
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
            throw new Error(t('client_signup.error_general'));
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
                let errorMessage = result?.error || t('client_signup.error_general');
                if (String(errorMessage).includes('tayari ipo')) {
                    errorMessage = t('client_signup.error_account_exists');
                }
                throw new Error(errorMessage);
            }

            // ✅ Account created — close the modal and open the optional
            // onboarding slideshow (profile photo) before sending the user to login.
            setOtpVisible(false);

            // Reset form
            setFormData({
                email: '',
                password: '',
                confirmPassword: '',
                full_name: '',
                phone: ''
            });

            router.replace({
                pathname: '/onboarding',
                params: {
                    email: formData.email,
                    role: 'customer',
                    setupToken: result?.setupToken || '',
                    lang,
                },
            } as any);
        } catch (error: any) {
            console.error('❌ Account creation error:', error);
            if (error?.message?.includes('Network request failed')) {
                throw new Error(t('client_signup.error_network'));
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

    const handleBack = () => {
        router.push({
            pathname: '/login',
            params: { role: 'mteja', lang }
        });
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
                    {/* Header */}
                    <View style={styles.headerContainer}>
                        <TouchableOpacity 
                            style={styles.topBackButton}
                            onPress={handleBack}
                        >
                            <Ionicons name="arrow-back" size={24} color="#2c3e50" />
                        </TouchableOpacity>
                        <Text style={styles.header}>{t('client_signup.title')}</Text>
                        <Text style={styles.subHeader}>{t('client_signup.subtitle')}</Text>
                    </View>

                    {/* Form Container */}
                    <View style={styles.formContainer}>
                        {/* Full Name */}
                        <View style={styles.inputGroup}>
                            <Text style={styles.label}>{t('client_signup.full_name')} *</Text>
                            <TextInput
                                style={styles.input}
                                placeholder={t('client_signup.full_name_placeholder')}
                                value={formData.full_name}
                                onChangeText={(value) => updateFormData('full_name', value)}
                                placeholderTextColor="#95a5a6"
                                autoCapitalize="words"
                                cursorColor="#3498db"
                                selectionColor="rgba(52, 152, 219, 0.3)"
                                textAlignVertical="center"
                            />
                        </View>

                        {/* Email */}
                        <View style={styles.inputGroup}>
                            <Text style={styles.label}>{t('client_signup.email_label')} *</Text>
                            <TextInput
                                style={styles.input}
                                placeholder={t('client_signup.email_placeholder')}
                                value={formData.email}
                                onChangeText={(value) => updateFormData('email', value)}
                                keyboardType="email-address"
                                autoCapitalize="none"
                                autoComplete="email"
                                placeholderTextColor="#95a5a6"
                                cursorColor="#3498db"
                                selectionColor="rgba(52, 152, 219, 0.3)"
                                textAlignVertical="center"
                            />
                        </View>

                        {/* Phone */}
                        <View style={styles.inputGroup}>
                            <Text style={styles.label}>{t('client_signup.phone_label')}</Text>
                            <TextInput
                                style={styles.input}
                                placeholder={t('client_signup.phone_placeholder')}
                                value={formData.phone}
                                onChangeText={(value) => updateFormData('phone', value)}
                                keyboardType="phone-pad"
                                placeholderTextColor="#95a5a6"
                                cursorColor="#3498db"
                                selectionColor="rgba(52, 152, 219, 0.3)"
                                textAlignVertical="center"
                            />
                        </View>

                        {/* Password */}
                        <View style={styles.inputGroup}>
                            <Text style={styles.label}>{t('client_signup.password_label')} *</Text>
                            <TextInput
                                style={styles.input}
                                placeholder={t('client_signup.password_placeholder')}
                                value={formData.password}
                                onChangeText={(value) => updateFormData('password', value)}
                                secureTextEntry
                                placeholderTextColor="#95a5a6"
                                cursorColor="#3498db"
                                selectionColor="rgba(52, 152, 219, 0.3)"
                                textAlignVertical="center"
                            />
                        </View>

                        {/* Confirm Password */}
                        <View style={styles.inputGroup}>
                            <Text style={styles.label}>{t('client_signup.confirm_password_label')} *</Text>
                            <TextInput
                                style={styles.input}
                                placeholder={t('client_signup.confirm_password_placeholder')}
                                value={formData.confirmPassword}
                                onChangeText={(value) => updateFormData('confirmPassword', value)}
                                secureTextEntry
                                placeholderTextColor="#95a5a6"
                                cursorColor="#3498db"
                                selectionColor="rgba(52, 152, 219, 0.3)"
                                textAlignVertical="center"
                            />
                        </View>

                        {/* Signup Button */}
                        <TouchableOpacity 
                            style={[styles.signupButton, loading && styles.signupButtonDisabled]}
                            onPress={handleSignup}
                            disabled={loading}
                            activeOpacity={0.8}
                        >
                            {loading ? (
                                <ActivityIndicator color="white" size="small" />
                            ) : (
                                <Text style={styles.signupButtonText}>{t('client_signup.signup_button')}</Text>
                            )}
                        </TouchableOpacity>

                        {/* Terms & Conditions */}
                        <View style={styles.termsContainer}>
                            <Text style={styles.termsText}>
                                {t('client_signup.terms')}
                            </Text>
                        </View>

                        {/* Back to Login */}
                        <TouchableOpacity 
                            style={styles.backButton}
                            onPress={handleBack}
                        >
                            <Text style={styles.backButtonText}>
                                ← {t('client_signup.back_to_login')}
                            </Text>
                        </TouchableOpacity>
                    </View>

                    {/* Footer Info */}
                    <View style={styles.footerInfo}>
                        <Text style={styles.footerText}>
                            {t('client_signup.footer_title')}
                        </Text>
                        <Text style={styles.footerBullet}>• {t('client_signup.footer_point1')}</Text>
                        <Text style={styles.footerBullet}>• {t('client_signup.footer_point2')}</Text>
                        <Text style={styles.footerBullet}>• {t('client_signup.footer_point3')}</Text>
                    </View>
                </View>
            </ScrollView>

            {/* Email OTP verification modal */}
            <OtpVerificationModal
                visible={otpVisible}
                email={pendingOtp?.email || ''}
                role={pendingOtp?.role || 'customer'}
                accentColor="#3498db"
                onVerified={handleOtpVerified}
                onClose={() => setOtpVisible(false)}
            />
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
        paddingHorizontal: 25,
        paddingTop: Platform.OS === 'android' ? 40 : 50,
        paddingBottom: 40,
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
        fontSize: 28,
        fontWeight: 'bold',
        textAlign: 'center',
        marginBottom: 10,
        color: '#3498db',
        textTransform: 'uppercase',
    },
    subHeader: {
        fontSize: 16,
        color: '#7f8c8d',
        textAlign: 'center',
    },
    formContainer: {
        width: '100%',
        marginBottom: 30,
    },
    inputGroup: {
        marginBottom: 20,
    },
    label: {
        fontSize: 14,
        fontWeight: '600',
        color: '#2c3e50',
        marginBottom: 8,
        marginLeft: 5,
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
    signupButton: {
        width: '100%',
        height: 58,
        justifyContent: 'center',
        alignItems: 'center',
        borderRadius: 12,
        backgroundColor: '#3498db',
        marginTop: 10,
        marginBottom: 20,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.15,
        shadowRadius: 8,
        elevation: 5,
    },
    signupButtonDisabled: {
        backgroundColor: '#85c1e9',
    },
    signupButtonText: {
        fontSize: 18,
        fontWeight: 'bold',
        color: 'white',
        textTransform: 'uppercase',
        letterSpacing: 0.5,
    },
    termsContainer: {
        alignItems: 'center',
        marginBottom: 25,
    },
    termsText: {
        fontSize: 12,
        color: '#7f8c8d',
        textAlign: 'center',
        lineHeight: 18,
    },
    backButton: {
        width: '100%',
        paddingVertical: 15,
        alignItems: 'center',
    },
    backButtonText: {
        fontSize: 16,
        color: '#3498db',
        fontWeight: '600',
    },
    footerInfo: {
        backgroundColor: '#f0f8ff',
        padding: 20,
        borderRadius: 12,
        marginTop: 20,
    },
    footerText: {
        fontSize: 16,
        fontWeight: 'bold',
        color: '#2c3e50',
        marginBottom: 10,
    },
    footerBullet: {
        fontSize: 14,
        color: '#3498db',
        marginLeft: 10,
        marginBottom: 5,
    },
});