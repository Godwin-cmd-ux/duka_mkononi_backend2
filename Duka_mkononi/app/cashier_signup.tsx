import { Ionicons } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import {
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
import SmartCartLoader from '../components/SmartCartLoader';
import { useLang } from '../context/LanguageContext';
import OtpVerificationModal from '../components/otp-verification-modal';

import { API_BASE_URL } from '../constants/api';
import { requireNetwork, fetchWithTimeout } from '../lib/network';

type SignupStep = 1 | 2 | 3;

type SellerFormData = {
    email: string;
    password: string;
    confirmPassword: string;
    full_name: string;
    phone: string;
};

type Invitation = {
    code: string;
    business: {
        id: string;
        business_name: string;
        business_structure: string;
        business_location?: string | null;
    };
    branch: {
        id: string;
        branch_name: string;
        location?: string | null;
    } | null;
};

const JSON_HEADERS = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'ngrok-skip-browser-warning': 'true',
};

async function readResponse(response: Response): Promise<any> {
    try {
        return await response.json();
    } catch {
        return {};
    }
}

function StepIndicator({ step, labels }: { step: number; labels: string[] }) {
    return (
        <View style={styles.stepIndicator} accessibilityLabel={`${step}/${labels.length}`}>
            {labels.map((label, index) => {
                const stepNumber = index + 1;
                const isActive = stepNumber === step;
                const isComplete = stepNumber < step;
                return (
                    <View key={label} style={styles.stepItem}>
                        <View
                            style={[
                                styles.stepCircle,
                                isActive && styles.stepCircleActive,
                                isComplete && styles.stepCircleComplete,
                            ]}
                        >
                            <Text
                                style={[
                                    styles.stepNumber,
                                    (isActive || isComplete) && styles.stepNumberActive,
                                ]}
                            >
                                {stepNumber}
                            </Text>
                        </View>
                        <Text
                            style={[styles.stepLabel, isActive && styles.stepLabelActive]}
                            numberOfLines={2}
                        >
                            {label}
                        </Text>
                    </View>
                );
            })}
        </View>
    );
}

export default function CashierSignup() {
    const router = useRouter();
    const params = useLocalSearchParams();
    const { t, lang: currentLang } = useLang();
    const lang = (params.lang as string) || currentLang;
    const [loading, setLoading] = useState(false);
    const [invitationLoading, setInvitationLoading] = useState(false);
    const [invitationError, setInvitationError] = useState('');
    const [step, setStep] = useState<SignupStep>(1);
    const [invitationCode, setInvitationCode] = useState('');
    const [invitation, setInvitation] = useState<Invitation | null>(null);
    const [registrationComplete, setRegistrationComplete] = useState(false);
    const [otpVisible, setOtpVisible] = useState(false);
    const [pendingOtp, setPendingOtp] = useState<{
        email: string;
        role: string;
    } | null>(null);
    const [formData, setFormData] = useState<SellerFormData>({
        email: '',
        password: '',
        confirmPassword: '',
        full_name: '',
        phone: '',
    });

    const text = (key: string, fallback: string) => {
        const translated = t(key);
        return translated === key ? fallback : translated;
    };

    const stepLabels = [
        text('registration_wizard.invitation', 'Mwaliko'),
        text('registration_wizard.personal', 'Taarifa Binafsi'),
        text('registration_wizard.email_verification', 'Uthibitisho wa Barua'),
    ];

    const updateFormData = (field: keyof SellerFormData, value: string) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
    };

    const updateInvitationCode = (value: string) => {
        setInvitationCode(value.toUpperCase());
        setInvitation(null);
        setInvitationError('');
    };

    const lookupInvitation = async () => {
        const code = invitationCode.trim();
        if (!code) {
            setInvitationError(text('seller_signup.invitation_required', 'Weka msimbo wa mwaliko uliopokea kwa msimamizi.'));
            return;
        }
        if (!(await requireNetwork())) return;

        setInvitationLoading(true);
        setInvitation(null);
        setInvitationError('');
        try {
            const response = await fetchWithTimeout(
                `${API_BASE_URL}/api/seller-registration/invitations/${encodeURIComponent(code)}`,
                { headers: JSON_HEADERS }
            );
            const data = await readResponse(response);

            if (response.ok && data?.success === true && data?.business?.business_name) {
                setInvitation({
                    code: data?.invitation?.code || code,
                    business: data.business,
                    branch: data?.branch || null,
                });
            } else {
                const message = data?.error || text('seller_signup.invitation_invalid', 'Msimko wa mwaliko si sahihi, umeisha muda, au umetumika.');
                setInvitationError(message);
                Alert.alert(t('app.error'), message);
            }
        } catch (error) {
            const message = error instanceof Error ? error.message : '';
            const errorMessage = /Network request failed|Aborted|timeout/i.test(message)
                ? t('seller_signup.error_network')
                : t('seller_signup.error_general');
            setInvitationError(errorMessage);
            Alert.alert(t('app.error'), errorMessage);
        } finally {
            setInvitationLoading(false);
        }
    };

    const confirmInvitation = () => {
        if (!invitation || invitationLoading) return;
        setStep(2);
    };

    const handlePersonalContinue = async () => {
        if (!invitation) {
            setInvitationError(text('seller_signup.invitation_required', 'Weka msimbo wa mwaliko uliopokea kwa msimamizi.'));
            setStep(1);
            return;
        }

        if (!formData.email.trim() || !formData.password || !formData.full_name.trim()) {
            Alert.alert(t('app.error'), t('seller_signup.error_required'));
            return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(formData.email.trim())) {
            Alert.alert(t('app.error'), t('seller_signup.error_invalid_email'));
            return;
        }

        if (formData.password !== formData.confirmPassword) {
            Alert.alert(t('app.error'), t('seller_signup.error_password_mismatch'));
            return;
        }

        if (formData.password.length < 6) {
            Alert.alert(t('app.error'), t('seller_signup.error_password_mismatch'));
            return;
        }

        if (!(await requireNetwork())) return;

        setLoading(true);
        try {
            // The invitation is the only business assignment sent by the app.
            // The server resolves and revalidates the business and branch.
            const response = await fetchWithTimeout(`${API_BASE_URL}/api/register/initiate`, {
                method: 'POST',
                headers: JSON_HEADERS,
                body: JSON.stringify({
                    email: formData.email.trim(),
                    password: formData.password,
                    role: 'seller',
                    full_name: formData.full_name.trim(),
                    phone: formData.phone.trim() || null,
                    invitation_code: invitation.code,
                    language: lang,
                }),
            });
            const data = await readResponse(response);

            if (response.ok && data?.requiresOtp) {
                setRegistrationComplete(false);
                setStep(3);
                setPendingOtp({ email: formData.email.trim(), role: 'seller' });
                setOtpVisible(true);
                if (data.email_sent === false) {
                    Alert.alert(t('otp.sent_title'), t('otp.email_failed'));
                }
            } else {
                let errorMessage = data?.error || t('seller_signup.error_general');
                if (String(data?.code || '').startsWith('INVITATION_') || /mwaliko/i.test(String(errorMessage))) {
                    setInvitation(null);
                    setInvitationError(errorMessage);
                    setStep(1);
                } else if (String(data?.code) === 'EMAIL_TAKEN' || /tayari ipo/i.test(String(errorMessage))) {
                    errorMessage = t('seller_signup.error_email_taken');
                }
                Alert.alert(t('app.error'), errorMessage);
            }
        } catch (error) {
            const message = error instanceof Error ? error.message : '';
            Alert.alert(
                t('app.error'),
                /Network request failed|Aborted|timeout/i.test(message)
                    ? t('seller_signup.error_network')
                    : t('seller_signup.error_general')
            );
        } finally {
            setLoading(false);
        }
    };

    const handleOtpVerified = async (data: unknown) => {
        if (!(await requireNetwork())) return;

        const token = data && typeof data === 'object'
            ? (data as { registrationToken?: string }).registrationToken
            : undefined;
        if (!token) {
            throw new Error(t('seller_signup.error_general'));
        }

        try {
            const response = await fetchWithTimeout(`${API_BASE_URL}/api/register`, {
                method: 'POST',
                headers: JSON_HEADERS,
                body: JSON.stringify({
                    verificationToken: token,
                    email: pendingOtp?.email || formData.email.trim(),
                }),
            });
            const result = await readResponse(response);

            if (!response.ok) {
                let errorMessage = result?.error || t('seller_signup.error_general');
                if (String(result?.code) === 'EMAIL_TAKEN' || /tayari ipo/i.test(String(errorMessage))) {
                    errorMessage = t('seller_signup.error_email_taken');
                }
                throw new Error(errorMessage);
            }

            // Seller accounts are created with pending status. Keep the seller
            // on a clear approval-confirmation screen instead of onboarding.
            setOtpVisible(false);
            setRegistrationComplete(true);
            setFormData((prev) => ({ ...prev, password: '', confirmPassword: '' }));
        } catch (error) {
            const message = error instanceof Error ? error.message : '';
            if (/Network request failed|Aborted|timeout/i.test(message)) {
                throw new Error(t('seller_signup.error_network'));
            }
            throw error;
        }
    };

    const closeOtp = () => {
        setOtpVisible(false);
        if (!registrationComplete) setStep(2);
    };

    const handleBack = () => {
        if (registrationComplete) {
            goToLogin();
        } else if (step === 1) {
            router.back();
        } else {
            setStep((current) => (Math.max(1, current - 1) as SignupStep));
        }
    };

    const goToLogin = () => {
        router.replace({ pathname: '/login', params: { role: 'muuzaji', lang } } as any);
    };

    const renderInvitationCard = () => {
        if (!invitation) return null;
        return (
            <View style={styles.invitationCard}>
                <View style={styles.invitationCardHeader}>
                    <Ionicons name="checkmark-circle" size={22} color="#27ae60" />
                    <Text style={styles.invitationCardTitle}>
                        {text('seller_signup.invitation_confirmed', 'Mwaliko Umethibitishwa')}
                    </Text>
                </View>
                <View style={styles.invitationDetailRow}>
                    <Text style={styles.invitationDetailLabel}>
                        {text('seller_signup.invitation_business', 'Biashara')}
                    </Text>
                    <Text style={styles.invitationDetailValue}>{invitation.business.business_name}</Text>
                </View>
                <View style={styles.invitationDetailRow}>
                    <Text style={styles.invitationDetailLabel}>
                        {text('seller_signup.invitation_branch', 'Tawi')}
                    </Text>
                    <Text style={styles.invitationDetailValue}>
                        {invitation.branch
                            ? `${invitation.branch.branch_name}${invitation.branch.location ? ` — ${invitation.branch.location}` : ''}`
                            : text('seller_signup.invitation_no_branch', 'Eneo kuu (bila matawi)')}
                    </Text>
                </View>
                {invitation.business.business_location ? (
                    <View style={styles.invitationDetailRow}>
                        <Text style={styles.invitationDetailLabel}>
                            {text('registration_wizard.business_location', 'Eneo la Biashara')}
                        </Text>
                        <Text style={styles.invitationDetailValue}>{invitation.business.business_location}</Text>
                    </View>
                ) : null}
            </View>
        );
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
                        onPress={handleBack}
                        disabled={loading || invitationLoading}
                        accessibilityLabel={t('app.back')}
                    >
                        <Ionicons name="arrow-back" size={24} color="#2c3e50" />
                    </TouchableOpacity>
                    <Text style={styles.header}>{t('seller_signup.title')}</Text>
                    <Text style={styles.subtitle}>{t('seller_signup.subtitle')}</Text>
                </View>

                <StepIndicator step={step} labels={stepLabels} />
                <Text style={styles.stepCounter}>
                    {text('registration_wizard.step_counter', 'Hatua {current} ya {total}')
                        .replace('{current}', String(step))
                        .replace('{total}', String(stepLabels.length))}
                </Text>

                <View style={styles.formContainer}>
                    {step === 1 ? (
                        <>
                            <Text style={styles.sectionTitle}>
                                {text('seller_signup.invitation_title', 'Msimko wa Mwaliko')}
                            </Text>
                            <Text style={styles.sectionDescription}>
                                {text('seller_signup.invitation_only_info', 'Usajili wa muuzaji unahitaji mwaliko. Omba msimbo halali kwa msimamizi wa biashara yako.')}
                            </Text>
                            <TextInput
                                style={styles.input}
                                placeholder={text('seller_signup.invitation_placeholder', 'Weka msimbo wa mwaliko')}
                                value={invitationCode}
                                onChangeText={updateInvitationCode}
                                autoCapitalize="characters"
                                autoCorrect={false}
                                editable={!invitationLoading}
                                placeholderTextColor="#95a5a6"
                                cursorColor="#2ecc71"
                                selectionColor="rgba(46, 204, 113, 0.3)"
                                textAlignVertical="center"
                                onSubmitEditing={lookupInvitation}
                                returnKeyType="search"
                            />

                            <TouchableOpacity
                                style={[styles.lookupButton, invitationLoading && styles.disabledButton]}
                                onPress={lookupInvitation}
                                disabled={invitationLoading}
                            >
                                {invitationLoading ? (
                                    <SmartCartLoader size="compact" color="#2ecc71" />
                                ) : (
                                    <Text style={styles.lookupButtonText}>
                                        {text('seller_signup.invitation_lookup', 'Tafuta Mwaliko')}
                                    </Text>
                                )}
                            </TouchableOpacity>

                            {invitationError ? (
                                <View style={styles.errorBox}>
                                    <Ionicons name="alert-circle" size={16} color="#e74c3c" />
                                    <Text style={styles.errorText}>{invitationError}</Text>
                                </View>
                            ) : null}

                            {renderInvitationCard()}

                            <TouchableOpacity
                                style={[styles.signupButton, (!invitation || invitationLoading) && styles.disabledButton]}
                                onPress={confirmInvitation}
                                disabled={!invitation || invitationLoading}
                            >
                                <Text style={styles.signupButtonText}>
                                    {text('seller_signup.invitation_continue', 'Thibitisha na Endelea')}
                                </Text>
                            </TouchableOpacity>
                        </>
                    ) : null}

                    {step === 2 ? (
                        <>
                            {renderInvitationCard()}
                            <TextInput
                                style={styles.input}
                                placeholder={t('seller_signup.full_name_placeholder')}
                                value={formData.full_name}
                                onChangeText={(value) => updateFormData('full_name', value)}
                                autoCapitalize="words"
                                editable={!loading}
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
                                editable={!loading}
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
                                editable={!loading}
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
                                editable={!loading}
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
                                editable={!loading}
                                placeholderTextColor="#95a5a6"
                                cursorColor="#2ecc71"
                                selectionColor="rgba(46, 204, 113, 0.3)"
                                textAlignVertical="center"
                            />

                            <TouchableOpacity
                                style={[styles.signupButton, loading && styles.disabledButton]}
                                onPress={handlePersonalContinue}
                                disabled={loading}
                            >
                                {loading ? (
                                    <SmartCartLoader size="compact" color="#ffffff" />
                                ) : (
                                    <Text style={styles.signupButtonText}>
                                        {text('seller_signup.personal_continue', 'Endelea kwa Uthibitisho')}
                                    </Text>
                                )}
                            </TouchableOpacity>
                        </>
                    ) : null}

                    {step === 3 ? (
                        registrationComplete ? (
                            <View style={styles.successCard}>
                                <Ionicons name="time-outline" size={42} color="#2ecc71" />
                                <Text style={styles.successTitle}>{t('seller_signup.success_title')}</Text>
                                <Text style={styles.successMessage}>{t('seller_signup.success_message')}</Text>
                                <TouchableOpacity style={styles.signupButton} onPress={goToLogin}>
                                    <Text style={styles.signupButtonText}>
                                        {text('seller_signup.go_to_login', 'Nenda kwenye Ingia')}
                                    </Text>
                                </TouchableOpacity>
                            </View>
                        ) : (
                            <>
                                <Text style={styles.sectionTitle}>
                                    {text('registration_wizard.email_verification', 'Uthibitisho wa Barua Pepe')}
                                </Text>
                                <Text style={styles.sectionDescription}>
                                    {text('seller_signup.otp_step_description', 'Ingiza msimbo wa tarakimu 6 uliotumwa kwenye {email}.')
                                        .replace('{email}', formData.email.trim())}
                                </Text>
                                {renderInvitationCard()}
                                <TouchableOpacity
                                    style={styles.signupButton}
                                    onPress={() => setOtpVisible(true)}
                                    disabled={!pendingOtp}
                                >
                                    <Text style={styles.signupButtonText}>
                                        {text('seller_signup.otp_open', 'Fungua Msimko wa Uthibitisho')}
                                    </Text>
                                </TouchableOpacity>
                            </>
                        )
                    ) : null}

                    {!registrationComplete ? (
                        <TouchableOpacity
                            style={[styles.backButton, (loading || invitationLoading) && styles.disabledButton]}
                            onPress={handleBack}
                            disabled={loading || invitationLoading}
                        >
                            <Text style={styles.backButtonText}>{t('seller_signup.back_button')}</Text>
                        </TouchableOpacity>
                    ) : null}
                </View>

                {step <= 2 && !registrationComplete ? (
                    <View style={styles.infoContainer}>
                        <Text style={styles.infoText}>{t('seller_signup.info_title')}</Text>
                        <Text style={styles.infoPoint}>
                            • {text('seller_signup.invitation_only_info', 'Usajili wa muuzaji unahitaji mwaliko. Omba msimbo halali kwa msimamizi wa biashara yako.')}
                        </Text>
                        <Text style={styles.infoPoint}>• {t('seller_signup.info_point3')}</Text>
                        <Text style={styles.infoPoint}>• {t('seller_signup.info_point4')}</Text>
                    </View>
                ) : null}
            </ScrollView>

            <OtpVerificationModal
                visible={otpVisible}
                email={pendingOtp?.email || ''}
                role={pendingOtp?.role || 'seller'}
                accentColor="#2ecc71"
                onVerified={handleOtpVerified}
                onClose={closeOtp}
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
        marginBottom: 20,
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
        textAlign: 'center',
    },
    stepIndicator: {
        width: '100%',
        flexDirection: 'row',
        justifyContent: 'space-between',
        marginBottom: 8,
    },
    stepItem: {
        flex: 1,
        alignItems: 'center',
        paddingHorizontal: 3,
    },
    stepCircle: {
        width: 30,
        height: 30,
        borderRadius: 15,
        borderWidth: 1,
        borderColor: '#d5dbe1',
        backgroundColor: '#ffffff',
        alignItems: 'center',
        justifyContent: 'center',
    },
    stepCircleActive: {
        borderColor: '#2ecc71',
        backgroundColor: '#2ecc71',
    },
    stepCircleComplete: {
        borderColor: '#27ae60',
        backgroundColor: '#27ae60',
    },
    stepNumber: {
        color: '#95a5a6',
        fontSize: 12,
        fontWeight: '700',
    },
    stepNumberActive: {
        color: '#ffffff',
    },
    stepLabel: {
        marginTop: 5,
        color: '#95a5a6',
        fontSize: 10,
        fontWeight: '600',
        textAlign: 'center',
    },
    stepLabelActive: {
        color: '#27ae60',
    },
    stepCounter: {
        width: '100%',
        color: '#7f8c8d',
        fontSize: 11,
        textAlign: 'center',
        marginBottom: 20,
    },
    formContainer: {
        width: '100%',
        alignItems: 'center',
        gap: 15,
    },
    sectionTitle: {
        width: '100%',
        color: '#2c3e50',
        fontSize: 18,
        fontWeight: '800',
        marginBottom: 4,
    },
    sectionDescription: {
        width: '100%',
        color: '#7f8c8d',
        fontSize: 12,
        lineHeight: 18,
        marginBottom: 4,
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
    lookupButton: {
        width: '100%',
        height: 46,
        borderRadius: 12,
        borderWidth: 2,
        borderColor: '#2ecc71',
        backgroundColor: '#ffffff',
        alignItems: 'center',
        justifyContent: 'center',
    },
    lookupButtonText: {
        color: '#27ae60',
        fontSize: 14,
        fontWeight: '800',
        textTransform: 'uppercase',
    },
    invitationCard: {
        width: '100%',
        backgroundColor: '#e8f6ef',
        borderWidth: 1,
        borderColor: '#bde8cf',
        borderRadius: 14,
        padding: 14,
    },
    invitationCardHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 8,
    },
    invitationCardTitle: {
        color: '#27ae60',
        fontSize: 14,
        fontWeight: '800',
        marginLeft: 8,
    },
    invitationDetailRow: {
        flexDirection: 'row',
        alignItems: 'flex-start',
        paddingVertical: 6,
        borderTopWidth: StyleSheet.hairlineWidth,
        borderTopColor: '#ccebd9',
    },
    invitationDetailLabel: {
        width: '34%',
        color: '#6b8f7c',
        fontSize: 12,
        paddingRight: 8,
    },
    invitationDetailValue: {
        flex: 1,
        color: '#2c3e50',
        fontSize: 13,
        fontWeight: '600',
    },
    errorBox: {
        width: '100%',
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: '#fdecea',
        borderRadius: 10,
        paddingHorizontal: 12,
        paddingVertical: 10,
    },
    errorText: {
        flex: 1,
        color: '#c0392b',
        fontSize: 13,
        marginLeft: 6,
    },
    successCard: {
        width: '100%',
        backgroundColor: '#e8f6ef',
        borderWidth: 1,
        borderColor: '#bde8cf',
        borderRadius: 16,
        padding: 22,
        alignItems: 'center',
    },
    successTitle: {
        color: '#27ae60',
        fontSize: 20,
        fontWeight: '800',
        marginTop: 12,
        marginBottom: 8,
        textAlign: 'center',
    },
    successMessage: {
        color: '#2c3e50',
        fontSize: 14,
        lineHeight: 21,
        marginBottom: 20,
        textAlign: 'center',
    },
    signupButton: {
        width: '100%',
        height: 60,
        justifyContent: 'center',
        alignItems: 'center',
        borderRadius: 12,
        marginTop: 10,
        backgroundColor: '#2ecc71',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.25,
        shadowRadius: 3.84,
        elevation: 5,
    },
    signupButtonText: {
        fontSize: 16,
        fontWeight: 'bold',
        color: 'white',
        textAlign: 'center',
        textTransform: 'uppercase',
    },
    disabledButton: {
        opacity: 0.6,
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
