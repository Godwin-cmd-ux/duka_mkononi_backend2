import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
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

import { API_BASE_URL } from '../constants/api';
import { requireNetwork, fetchWithTimeout } from '../lib/network';

// Security: Input validation
const validateEmail = (email: string): boolean => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
};

const sanitizeInput = (input: string): string => {
    return input.trim().replace(/[<>]/g, '');
};

enum ResetStep {
    ENTER_EMAIL = 1,
    ENTER_CODE = 2,
    ENTER_NEW_PASSWORD = 3,
    SUCCESS = 4
}

export default function ForgotPasswordScreen() {
    const router = useRouter();
    const params = useLocalSearchParams();
    const role = params.role as string || 'mteja';
    const lang = (params.lang as string) || 'sw';
    const { t } = useLang();

    const [step, setStep] = useState<ResetStep>(ResetStep.ENTER_EMAIL);
    const [loading, setLoading] = useState(false);
    const [countdown, setCountdown] = useState(0);
    
    // Form states
    const [email, setEmail] = useState('');
    const [resetCode, setResetCode] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [verificationToken, setVerificationToken] = useState('');
    const [resetUserId, setResetUserId] = useState('');

    // Countdown timer for resend code
    useEffect(() => {
        let timer: ReturnType<typeof setTimeout>;
        if (countdown > 0) {
            timer = setTimeout(() => setCountdown(countdown - 1), 1000);
        }
        return () => clearTimeout(timer);
    }, [countdown]);

    const getRoleTitle = () => {
        switch (role) {
            case 'mteja': return t('user_roles.mteja').toUpperCase();
            case 'muuzaji': return t('user_roles.muuzaji').toUpperCase();
            case 'msimamizi': return t('user_roles.msimamizi').toUpperCase();
            default: return t('user_roles.customer').toUpperCase();
        }
    };

    const getRoleColor = () => {
        switch (role) {
            case 'mteja': return '#3498db';
            case 'muuzaji': return '#2ecc71';
            case 'msimamizi': return '#e74c3c';
            default: return '#2c3e50';
        }
    };

    const getBackendRole = (frontendRole: string) => {
        switch (frontendRole) {
            case 'mteja': return 'client';
            case 'muuzaji': return 'seller';
            case 'msimamizi': return 'admin';
            default: return frontendRole;
        }
    };

    // Test connection to API
    const testAPIConnection = async () => {
        try {
            console.log('🔗 Testing connection to:', `${API_BASE_URL}/api/test`);
            const response = await fetchWithTimeout(`${API_BASE_URL}/api/test`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                },
            });
            
            if (response.ok) {
                const data = await response.json();
                console.log('✅ API Connection successful:', data.message);
                return true;
            }
            return false;
        } catch (error) {
            console.error('❌ API Connection failed:', error);
            return false;
        }
    };

    // Step 1: Request password reset code
    const handleRequestResetCode = async () => {
        if (!(await requireNetwork())) return;

        const sanitizedEmail = sanitizeInput(email);

        if (!sanitizedEmail) {
            Alert.alert(t('app.error'), t('forgot_password.error_required'));
            return;
        }

        if (!validateEmail(sanitizedEmail)) {
            Alert.alert(t('app.error'), t('forgot_password.error_invalid_email'));
            return;
        }

        setLoading(true);

        try {
            // Test connection first
            const isConnected = await testAPIConnection();
            if (!isConnected) {
                throw new Error(t('forgot_password.error_network'));
            }

            const backendRole = getBackendRole(role);

            console.log('📨 Sending reset request for:', sanitizedEmail, 'Role:', backendRole);

            const response = await fetchWithTimeout(`${API_BASE_URL}/api/password-reset/request`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    email: sanitizedEmail,
                    role: backendRole
                }),
            });

            const responseText = await response.text();
            console.log('📥 Response status:', response.status);
            console.log('📥 Response body:', responseText);

            if (!response.ok) {
                let errorMessage = t('forgot_password.error_general');
                try {
                    const data = JSON.parse(responseText);
                    errorMessage = data.error || errorMessage;
                } catch (e) {
                    errorMessage = responseText || errorMessage;
                }
                throw new Error(errorMessage);
            }

            const data = JSON.parse(responseText);
            
            if (data.success) {
                // Success - move to code verification step
                setStep(ResetStep.ENTER_CODE);
                setCountdown(60); // 60 seconds countdown for resend
                Alert.alert(t('app.success'), data.message || t('forgot_password.success_message'));
            } else {
                throw new Error(data.error || t('app.error'));
            }

        } catch (error: any) {
            console.error('❌ Reset Request Error:', error);
            
            let errorMessage = t('forgot_password.error_general');
            
            if (error.message.includes('Network request failed') || error.message.includes('fetch failed')) {
                errorMessage = t('forgot_password.error_network');
            } else if (error.message.includes('timeout')) {
                errorMessage = t('forgot_password.error_network');
            } else {
                errorMessage = error.message || errorMessage;
            }

            Alert.alert(t('app.error'), errorMessage);
        } finally {
            setLoading(false);
        }
    };

    // Resend code
    const handleResendCode = async () => {
        if (countdown > 0) {
            Alert.alert(t('app.loading'), `${t('forgot_password.resend_code')} ${countdown}s`);
            return;
        }

        await handleRequestResetCode();
    };

    // Step 2: Verify reset code
    const handleVerifyResetCode = async () => {
        if (!(await requireNetwork())) return;

        if (!resetCode || resetCode.length !== 6) {
            Alert.alert(t('app.error'), t('forgot_password.error_code_required'));
            return;
        }

        setLoading(true);

        try {
            const backendRole = getBackendRole(role);

            console.log('🔍 Verifying code:', resetCode, 'for email:', email);

            const response = await fetchWithTimeout(`${API_BASE_URL}/api/password-reset/verify-code`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    role: backendRole,
                    resetCode: resetCode
                }),
            });

            const responseText = await response.text();
            console.log('📥 Verification response:', response.status, responseText);

            if (!response.ok) {
                let errorMessage = t('forgot_password.error_code_invalid');
                try {
                    const data = JSON.parse(responseText);
                    errorMessage = data.error || errorMessage;
                } catch (e) {}
                throw new Error(errorMessage);
            }

            const data = JSON.parse(responseText);
            
            if (data.success && data.verified) {
                setVerificationToken(data.verificationToken);
                setStep(ResetStep.ENTER_NEW_PASSWORD);
                Alert.alert(t('app.success'), data.message || t('forgot_password.success_message'));
            } else {
                throw new Error(data.error || t('forgot_password.error_code_invalid'));
            }

        } catch (error: any) {
            console.error('❌ Verify Code Error:', error);
            Alert.alert(t('app.error'), error.message || t('forgot_password.error_code_invalid'));
        } finally {
            setLoading(false);
        }
    };

    // Step 3: Set new password
    const handleSetNewPassword = async () => {
        if (!(await requireNetwork())) return;

        if (!newPassword || !confirmPassword) {
            Alert.alert(t('app.error'), t('forgot_password.error_password_required'));
            return;
        }

        if (newPassword.length < 6) {
            Alert.alert(t('app.error'), t('forgot_password.error_password_length'));
            return;
        }

        if (newPassword !== confirmPassword) {
            Alert.alert(t('app.error'), t('forgot_password.error_password_mismatch'));
            return;
        }

        setLoading(true);

        try {
            console.log('🔑 Setting new password with token:', verificationToken ? 'Token exists' : 'No token');

            const response = await fetchWithTimeout(`${API_BASE_URL}/api/password-reset/confirm`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    verificationToken: verificationToken,
                    newPassword: newPassword,
                    confirmPassword: confirmPassword
                }),
            });

            const responseText = await response.text();
            console.log('📥 Set password response:', response.status, responseText);

            if (!response.ok) {
                let errorMessage = t('forgot_password.error_general');
                try {
                    const data = JSON.parse(responseText);
                    errorMessage = data.error || errorMessage;
                } catch (e) {
                    // Not JSON
                }
                throw new Error(errorMessage);
            }

            const data = JSON.parse(responseText);
            
            if (data.success && data.passwordChanged) {
                setStep(ResetStep.SUCCESS);
                Alert.alert(t('app.success'), data.message || t('forgot_password.success_message'));
            } else {
                throw new Error(data.error || t('forgot_password.error_network'));
            }

        } catch (error: any) {
            console.error('❌ Set Password Error:', error);                if (error.message.includes('Token imeisha') || error.message.includes('Token si sahihi')) {
                    Alert.alert(t('app.error'), t('forgot_password.error_code_invalid'));
                    setStep(ResetStep.ENTER_EMAIL);
                } else {
                    Alert.alert(t('app.error'), error.message || t('forgot_password.error_network'));
                }
        } finally {
            setLoading(false);
        }
    };

    const handleBackToLogin = () => {
        router.push({
            pathname: '/login',
            params: { role }
        });
    };

    const handleResetProcess = () => {
        setStep(ResetStep.ENTER_EMAIL);
        setEmail('');
        setResetCode('');
        setNewPassword('');
        setConfirmPassword('');
        setVerificationToken('');
        setResetUserId('');
        setCountdown(0);
    };

    const formatTime = (seconds: number) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
    };

    const renderStepContent = () => {
        switch (step) {
            case ResetStep.ENTER_EMAIL:
                return (
                    <>
                        <View style={styles.iconContainer}>
                            <Ionicons name="key-outline" size={50} color={getRoleColor()} />
                        </View>
                        
                        <Text style={styles.instructionText}>
                            {t('forgot_password.instruction_email')}
                        </Text>
                        
                        <View style={styles.inputContainer}>
                            <Ionicons name="mail-outline" size={20} color="#7f8c8d" style={styles.inputIcon} />
                            <TextInput
                                style={styles.input}
                                placeholder={t('forgot_password.placeholder_email')}
                                value={email}
                                onChangeText={setEmail}
                                keyboardType="email-address"
                                autoCapitalize="none"
                                placeholderTextColor="#95a5a6"
                                autoComplete="email"
                            />
                        </View>

                        <TouchableOpacity 
                            style={[styles.actionButton, { backgroundColor: getRoleColor() }]}
                            onPress={handleRequestResetCode}
                            disabled={loading}
                        >
                            {loading ? (
                                <ActivityIndicator color="white" />
                            ) : (
                                <>
                                    <Ionicons name="send-outline" size={20} color="white" />
                                    <Text style={styles.actionButtonText}>{t('forgot_password.button_send_code')}</Text>
                                </>
                            )}
                        </TouchableOpacity>
                    </>
                );

            case ResetStep.ENTER_CODE:
                return (
                    <>
                        <View style={styles.iconContainer}>
                            <Ionicons name="mail-outline" size={50} color={getRoleColor()} />
                        </View>
                        
                        <Text style={styles.instructionText}>
                            {t('forgot_password.instruction_code')}
                        </Text>
                        <Text style={styles.emailText}>{email}</Text>
                        
                        <Text style={styles.subInstruction}>
                            {t('forgot_password.instruction_code_sub')}
                        </Text>
                        
                        <View style={styles.inputContainer}>
                            <Ionicons name="lock-closed-outline" size={20} color="#7f8c8d" style={styles.inputIcon} />
                            <TextInput
                                style={styles.input}
                                placeholder={t('forgot_password.placeholder_code')}
                                value={resetCode}
                                onChangeText={(text) => setResetCode(text.replace(/[^0-9]/g, '').slice(0, 6))}
                                keyboardType="number-pad"
                                maxLength={6}
                                placeholderTextColor="#95a5a6"
                            />
                        </View>

                        <TouchableOpacity 
                            style={[styles.actionButton, { backgroundColor: getRoleColor() }]}
                            onPress={handleVerifyResetCode}
                            disabled={loading}
                        >
                            {loading ? (
                                <ActivityIndicator color="white" />
                            ) : (
                                <>
                                    <Ionicons name="checkmark-circle-outline" size={20} color="white" />
                                    <Text style={styles.actionButtonText}>{t('forgot_password.button_verify_code')}</Text>
                                </>
                            )}
                        </TouchableOpacity>

                        <View style={styles.resendContainer}>
                            <Text style={styles.resendText}>
                                {t('forgot_password.not_received_code')} 
                            </Text>
                            <TouchableOpacity 
                                onPress={handleResendCode}
                                disabled={countdown > 0}
                            >
                                <Text style={[styles.resendButton, { color: getRoleColor() }]}>
                                    {countdown > 0 ? `${t('forgot_password.button_resend')} (${formatTime(countdown)})` : t('forgot_password.button_resend')}
                                </Text>
                            </TouchableOpacity>
                        </View>

                        <TouchableOpacity 
                            style={styles.secondaryButton}
                            onPress={() => setStep(ResetStep.ENTER_EMAIL)}
                        >
                            <Ionicons name="arrow-back-outline" size={16} color="#7f8c8d" />
                            <Text style={styles.secondaryButtonText}>Badilisha Barua Pepe</Text>
                        </TouchableOpacity>
                    </>
                );

            case ResetStep.ENTER_NEW_PASSWORD:
                return (
                    <>
                        <View style={styles.iconContainer}>
                            <Ionicons name="lock-closed-outline" size={50} color={getRoleColor()} />
                        </View>                            <Text style={styles.instructionText}>
                            {t('forgot_password.instruction_new_password')}
                        </Text>
                        
                        <View style={styles.inputContainer}>
                            <Ionicons name="key-outline" size={20} color="#7f8c8d" style={styles.inputIcon} />
                            <TextInput
                                style={styles.input}
                                placeholder={t('forgot_password.placeholder_new_password')}
                                value={newPassword}
                                onChangeText={setNewPassword}
                                secureTextEntry
                                placeholderTextColor="#95a5a6"
                            />
                        </View>

                        <View style={styles.inputContainer}>
                            <Ionicons name="key-outline" size={20} color="#7f8c8d" style={styles.inputIcon} />
                            <TextInput
                                style={styles.input}
                                placeholder={t('forgot_password.placeholder_confirm_password')}
                                value={confirmPassword}
                                onChangeText={setConfirmPassword}
                                secureTextEntry
                                placeholderTextColor="#95a5a6"
                            />
                        </View>

                        <TouchableOpacity 
                            style={[styles.actionButton, { backgroundColor: getRoleColor() }]}
                            onPress={handleSetNewPassword}
                            disabled={loading}
                        >
                            {loading ? (
                                <ActivityIndicator color="white" />
                            ) : (
                                <>
                                    <Ionicons name="refresh-outline" size={20} color="white" />
                                    <Text style={styles.actionButtonText}>{t('forgot_password.button_reset_password')}</Text>
                                </>
                            )}
                        </TouchableOpacity>
                    </>
                );

            case ResetStep.SUCCESS:
                return (
                    <>
                        <View style={styles.successIconContainer}>
                            <View style={[styles.successCircle, { borderColor: getRoleColor() }]}>
                                <Ionicons name="checkmark" size={50} color={getRoleColor()} />
                            </View>
                        </View>
                        
                        <View style={styles.successContainer}>
                            <Text style={styles.successTitle}>{t('forgot_password.success_title')}</Text>
                            <Text style={styles.successText}>
                                {t('forgot_password.success_message_complete')}
                            </Text>
                        </View>

                        <TouchableOpacity 
                            style={[styles.actionButton, { backgroundColor: getRoleColor() }]}
                            onPress={handleBackToLogin}
                        >
                            <Ionicons name="log-in-outline" size={20} color="white" />
                            <Text style={styles.actionButtonText}>{t('forgot_password.button_login_now')}</Text>
                        </TouchableOpacity>

                        <TouchableOpacity 
                            style={styles.secondaryButton}
                            onPress={handleResetProcess}
                        >
                            <Ionicons name="refresh-outline" size={16} color="#7f8c8d" />
                            <Text style={styles.secondaryButtonText}>{t('forgot_password.button_reset_again')}</Text>
                        </TouchableOpacity>
                    </>
                );
        }
    };

    const renderStepIndicator = () => {
        const steps = [
            { number: 1, label: t('forgot_password.step_email'), active: step >= 1 },
            { number: 2, label: t('forgot_password.step_code'), active: step >= 2 },
            { number: 3, label: t('forgot_password.step_password'), active: step >= 3 },
            { number: 4, label: t('forgot_password.step_ready'), active: step >= 4 }
        ];

        return (
            <View style={styles.stepIndicator}>
                {steps.map((stepItem, index) => (
                    <View key={stepItem.number} style={styles.stepItem}>
                        <View style={[
                            styles.stepCircle,
                            { 
                                backgroundColor: stepItem.active ? getRoleColor() : '#ecf0f1',
                                borderColor: stepItem.active ? getRoleColor() : '#bdc3c7'
                            }
                        ]}>
                            <Text style={[
                                styles.stepNumber,
                                { color: stepItem.active ? 'white' : '#7f8c8d' }
                            ]}>
                                {stepItem.number}
                            </Text>
                        </View>
                        <Text style={[
                            styles.stepLabel,
                            { color: stepItem.active ? getRoleColor() : '#95a5a6' }
                        ]}>
                            {stepItem.label}
                        </Text>
                        {index < steps.length - 1 && (
                            <View style={[
                                styles.stepLine,
                                { backgroundColor: step >= index + 2 ? getRoleColor() : '#ecf0f1' }
                            ]} />
                        )}
                    </View>
                ))}
            </View>
        );
    };

    return (
        <KeyboardAvoidingView 
            style={styles.keyboardAvoid}
            behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
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
                            style={styles.backButton}
                            onPress={handleBackToLogin}
                        >
                            <Ionicons name="arrow-back" size={24} color={getRoleColor()} />
                        </TouchableOpacity>
                        <Text style={[styles.header, { color: getRoleColor() }]}>
                            {t('forgot_password.title')}
                        </Text>
                        <View style={styles.roleBadge}>
                            <Text style={[styles.roleText, { color: getRoleColor() }]}>
                                {getRoleTitle()}
                            </Text>
                        </View>
                    </View>

                    {/* Step Indicator */}
                    {renderStepIndicator()}

                    {/* Form Content */}
                    <View style={styles.formContainer}>
                        {renderStepContent()}
                    </View>

                    {/* Help Info */}
                    {step === ResetStep.ENTER_CODE && (
                        <View style={styles.helpContainer}>
                            <View style={styles.helpHeader}>
                                <Ionicons name="information-circle-outline" size={20} color="#ff9800" />
                                <Text style={styles.helpTitle}>{t('forgot_password.help_title')}</Text>
                            </View>
                            <View style={styles.helpPoint}>
                                <Ionicons name="time-outline" size={14} color="#ff9800" />
                                <Text style={styles.helpText}>{t('forgot_password.help_code_expiry')}</Text>
                            </View>
                            <View style={styles.helpPoint}>
                                <Ionicons name="warning-outline" size={14} color="#ff9800" />
                                <Text style={styles.helpText}>{t('forgot_password.help_check_spam')}</Text>
                            </View>
                            <View style={styles.helpPoint}>
                                <Ionicons name="keypad-outline" size={14} color="#ff9800" />
                                <Text style={styles.helpText}>{t('forgot_password.help_code_format')}</Text>
                            </View>
                        </View>
                    )}

                    {/* Footer */}
                    <View style={styles.footer}>
                        <Text style={styles.footerText}>
                            DukaMkononi © {new Date().getFullYear()}
                        </Text>
                        <Text style={styles.footerSubText}>
                            {t('forgot_password.footer_title')}
                        </Text>
                    </View>
                </View>
            </ScrollView>
        </KeyboardAvoidingView>
    );
}

const styles = StyleSheet.create({
    keyboardAvoid: {
        flex: 1,
        backgroundColor: '#f8f9fa',
    },
    scrollContainer: {
        flexGrow: 1,
    },
    container: {
        flex: 1,
        padding: 20,
        paddingTop: 50,
        paddingBottom: 30,
        minHeight: 800,
    },
    headerContainer: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: 30,
        paddingHorizontal: 10,
    },
    backButton: {
        padding: 8,
        borderRadius: 8,
        backgroundColor: '#f1f2f6',
    },
    header: {
        fontSize: 20,
        fontWeight: 'bold',
        textAlign: 'center',
        flex: 1,
    },
    roleBadge: {
        paddingHorizontal: 12,
        paddingVertical: 6,
        borderRadius: 20,
        backgroundColor: '#f1f2f6',
    },
    roleText: {
        fontSize: 12,
        fontWeight: '600',
    },
    stepIndicator: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 40,
        paddingHorizontal: 10,
    },
    stepItem: {
        alignItems: 'center',
        flex: 1,
    },
    stepCircle: {
        width: 36,
        height: 36,
        borderRadius: 18,
        borderWidth: 2,
        justifyContent: 'center',
        alignItems: 'center',
        marginBottom: 8,
    },
    stepNumber: {
        fontSize: 14,
        fontWeight: 'bold',
    },
    stepLabel: {
        fontSize: 11,
        fontWeight: '500',
        textAlign: 'center',
    },
    stepLine: {
        position: 'absolute',
        top: 18,
        right: -50,
        width: 50,
        height: 2,
        zIndex: -1,
    },
    formContainer: {
        backgroundColor: 'white',
        borderRadius: 20,
        padding: 25,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
        shadowRadius: 10,
        elevation: 5,
        marginBottom: 20,
    },
    iconContainer: {
        alignItems: 'center',
        marginBottom: 20,
    },
    successIconContainer: {
        alignItems: 'center',
        marginBottom: 30,
    },
    successCircle: {
        width: 100,
        height: 100,
        borderRadius: 50,
        borderWidth: 3,
        justifyContent: 'center',
        alignItems: 'center',
        backgroundColor: '#f8f9fa',
    },
    instructionText: {
        fontSize: 16,
        textAlign: 'center',
        color: '#2c3e50',
        marginBottom: 25,
        lineHeight: 22,
    },
    emailText: {
        fontSize: 16,
        fontWeight: 'bold',
        textAlign: 'center',
        color: '#e74c3c',
        marginBottom: 25,
        paddingHorizontal: 20,
    },
    subInstruction: {
        fontSize: 14,
        textAlign: 'center',
        color: '#7f8c8d',
        marginBottom: 20,
    },
    inputContainer: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 15,
        backgroundColor: '#f8f9fa',
        borderRadius: 12,
        borderWidth: 1,
        borderColor: '#e0e0e0',
        paddingHorizontal: 15,
    },
    inputIcon: {
        marginRight: 10,
    },
    input: {
        flex: 1,
        height: 50,
        fontSize: 16,
        color: '#2c3e50',
    },
    actionButton: {
        width: '100%',
        height: 56,
        flexDirection: 'row',
        justifyContent: 'center',
        alignItems: 'center',
        borderRadius: 12,
        marginTop: 10,
        marginBottom: 15,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 3 },
        shadowOpacity: 0.2,
        shadowRadius: 5,
        elevation: 5,
        gap: 10,
    },
    actionButtonText: {
        fontSize: 16,
        fontWeight: 'bold',
        color: 'white',
    },
    resendContainer: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        marginVertical: 15,
        gap: 5,
    },
    resendText: {
        fontSize: 14,
        color: '#7f8c8d',
    },
    resendButton: {
        fontSize: 14,
        fontWeight: '600',
        textDecorationLine: 'underline',
    },
    secondaryButton: {
        width: '100%',
        height: 48,
        flexDirection: 'row',
        justifyContent: 'center',
        alignItems: 'center',
        borderRadius: 12,
        borderWidth: 1,
        borderColor: '#ddd',
        backgroundColor: 'transparent',
        gap: 8,
    },
    secondaryButtonText: {
        fontSize: 14,
        color: '#7f8c8d',
        fontWeight: '500',
    },
    successContainer: {
        alignItems: 'center',
        padding: 20,
        backgroundColor: '#e8f6ef',
        borderRadius: 12,
        borderWidth: 2,
        borderColor: '#2ecc71',
        marginBottom: 25,
    },
    successTitle: {
        fontSize: 20,
        fontWeight: 'bold',
        color: '#27ae60',
        marginBottom: 10,
    },
    successText: {
        fontSize: 15,
        textAlign: 'center',
        color: '#2c3e50',
        lineHeight: 22,
    },
    helpContainer: {
        marginTop: 10,
        padding: 15,
        backgroundColor: '#fff8e1',
        borderRadius: 12,
        borderLeftWidth: 4,
        borderLeftColor: '#ff9800',
    },
    helpHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 10,
        gap: 8,
    },
    helpTitle: {
        fontSize: 14,
        fontWeight: 'bold',
        color: '#ff9800',
    },
    helpPoint: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 8,
        gap: 8,
    },
    helpText: {
        fontSize: 13,
        color: '#ff9800',
        flex: 1,
    },
    footer: {
        alignItems: 'center',
        marginTop: 30,
        paddingTop: 20,
        borderTopWidth: 1,
        borderTopColor: '#eee',
    },
    footerText: {
        fontSize: 12,
        color: '#95a5a6',
        marginBottom: 4,
    },
    footerSubText: {
        fontSize: 11,
        color: '#bdc3c7',
    },
});