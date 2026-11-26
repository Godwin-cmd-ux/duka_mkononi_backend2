import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import {
    Alert,
    KeyboardAvoidingView,
    Modal,
    Platform,
    ScrollView,
    StyleSheet,
    Text,
    TextInput,
    TouchableOpacity,
    View
} from 'react-native';
import * as Location from 'expo-location';
import SmartCartLoader from '../components/SmartCartLoader';
import { useLang } from '../context/LanguageContext';
import OtpVerificationModal from '../components/otp-verification-modal';
import { reverseGeocode } from '../utils/location';

import { API_BASE_URL } from '../constants/api';
import { requireNetwork, fetchWithTimeout } from '../lib/network';

type SignupStep = 1 | 2 | 3 | 4;
type BusinessStructure = 'non_branch' | 'branch';

type Branch = {
    branch_name: string;
    location: string;
    latitude: number | null;
    longitude: number | null;
};

type AdminFormData = {
    email: string;
    password: string;
    confirmPassword: string;
    full_name: string;
    phone: string;
    business_name: string;
    business_location: string;
    business_type: string;
};

const BUSINESS_TYPES = [
    'spare_parts',
    'motorcycle_spares',
    'pharmacy',
    'supermarket',
    'clothing',
    'electronics',
    'restaurant',
    'hardware',
    'cosmetics',
    'perfume',
    'mobile_accessories',
    'furniture',
    'stationery',
    'agriculture',
    'construction_materials',
    'beauty_salon',
    'barbershop',
    'auto_repair',
    'phone_shop',
    'computer_shop',
    'general_retail',
    'wholesale',
    'other',
];

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

function ReviewSection({
    title,
    onEdit,
    children,
}: {
    title: string;
    onEdit?: () => void;
    children: React.ReactNode;
}) {
    const { t } = useLang();

    return (
        <View style={styles.reviewCard}>
            <View style={styles.reviewCardHeader}>
                <Text style={styles.reviewCardTitle}>{title}</Text>
                {onEdit ? (
                    <TouchableOpacity onPress={onEdit} accessibilityRole="button">
                        <Text style={styles.editButtonText}>{t('app.edit')}</Text>
                    </TouchableOpacity>
                ) : null}
            </View>
            {children}
        </View>
    );
}

function ReviewRow({ label, value }: { label: string; value: string }) {
    return (
        <View style={styles.reviewRow}>
            <Text style={styles.reviewLabel}>{label}</Text>
            <Text style={styles.reviewValue}>{value}</Text>
        </View>
    );
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

export default function AdminSignup() {
    const router = useRouter();
    const params = useLocalSearchParams();
    const { t, lang: currentLang } = useLang();
    const lang = (params.lang as string) || currentLang;
    const [loading, setLoading] = useState(false);
    const [step, setStep] = useState<SignupStep>(1);
    const [businessTypeModalVisible, setBusinessTypeModalVisible] = useState(false);
    const [otpVisible, setOtpVisible] = useState(false);
    const [pendingOtp, setPendingOtp] = useState<{
        email: string;
        role: string;
    } | null>(null);
    const [formData, setFormData] = useState<AdminFormData>({
        email: '',
        password: '',
        confirmPassword: '',
        full_name: '',
        phone: '',
        business_name: '',
        business_location: '',
        business_type: '',
    });

    // Business structure is separate from business_type: it decides whether the
    // business has one location (non_branch) or several branches, each with its
    // own name and geographical location.
    const [businessStructure, setBusinessStructure] = useState<BusinessStructure>('non_branch');
    const [branches, setBranches] = useState<Branch[]>([]);

    const text = (key: string, fallback: string) => {
        const translated = t(key);
        return translated === key ? fallback : translated;
    };

    const businessTypeLabel = (value: string) => {
        const key = `business_types.${value}`;
        const translated = t(key);
        return translated === key ? value.replace(/_/g, ' ') : translated;
    };

    const stepLabels = [
        text('registration_wizard.business', 'Taarifa za Biashara'),
        text('registration_wizard.personal', 'Taarifa Binafsi'),
        text('registration_wizard.review', 'Hakiki Taarifa'),
        text('registration_wizard.verify', 'Uthibitisho'),
    ];

    const updateFormData = (field: keyof AdminFormData, value: string) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
    };

    const addBranch = () => {
        setBranches((prev) => [...prev, { branch_name: '', location: '', latitude: null, longitude: null }]);
    };

    const updateBranch = (index: number, field: 'branch_name' | 'location', value: string) => {
        setBranches((prev) => prev.map((branch, i) => (i === index ? { ...branch, [field]: value } : branch)));
    };

    const removeBranch = (index: number) => {
        setBranches((prev) => prev.filter((_, i) => i !== index));
    };

    const captureBranchLocation = async (index: number) => {
        try {
            const permission = await Location.requestForegroundPermissionsAsync();
            if (permission.status !== 'granted') {
                Alert.alert(t('app.error'), 'Ruhusa ya eneo imekataliwa.');
                return;
            }

            const position = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
            const { latitude, longitude } = position.coords;
            const areaName = await reverseGeocode(latitude, longitude);

            Alert.alert(
                'Tumia eneo hili?',
                areaName || `${latitude.toFixed(5)}, ${longitude.toFixed(5)}`,
                [
                    { text: 'Hapana', style: 'cancel' },
                    {
                        text: 'Ndiyo',
                        onPress: () => setBranches((prev) => prev.map((branch, i) => (
                            i === index
                                ? { ...branch, latitude, longitude, location: areaName || branch.location }
                                : branch
                        ))),
                    },
                ]
            );
        } catch {
            Alert.alert(t('app.error'), 'Imeshindikana kupata eneo la tawi.');
        }
    };

    const buildRegistrationPayload = () => ({
        role: 'admin',
        email: formData.email.trim(),
        password: formData.password,
        confirm_password: formData.confirmPassword,
        full_name: formData.full_name.trim(),
        phone: formData.phone.trim(),
        business_name: formData.business_name.trim(),
        business_location: formData.business_location.trim(),
        business_type: formData.business_type,
        business_structure: businessStructure,
        branches: businessStructure === 'branch'
            ? branches.map((branch) => ({
                branch_name: branch.branch_name.trim(),
                location: branch.location.trim(),
                latitude: branch.latitude,
                longitude: branch.longitude,
            }))
            : [],
        language: lang,
    });

    const validateBusinessFields = () => {
        if (!formData.business_name.trim() || !formData.business_location.trim() || !formData.business_type) {
            Alert.alert(t('app.error'), t('admin_signup.error_required'));
            return false;
        }

        if (businessStructure === 'branch') {
            if (branches.length === 0) {
                Alert.alert(t('app.error'), 'Ongeza tawi moja au zaidi kwa biashara yenye matawi.');
                return false;
            }
            if (branches.some((branch) => !branch.branch_name.trim() || !branch.location.trim())) {
                Alert.alert(t('app.error'), 'Jina na mahali pa kila tawi vinahitajika.');
                return false;
            }
        }

        return true;
    };

    const validatePersonalFields = () => {
        if (!formData.email.trim() || !formData.password || !formData.full_name.trim()) {
            Alert.alert(t('app.error'), t('admin_signup.error_required'));
            return false;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(formData.email.trim())) {
            Alert.alert(t('app.error'), t('admin_signup.error_invalid_email'));
            return false;
        }

        if (formData.password !== formData.confirmPassword) {
            Alert.alert(t('app.error'), t('admin_signup.error_password_mismatch'));
            return false;
        }

        if (formData.password.length < 6) {
            Alert.alert(t('app.error'), t('admin_signup.error_password_mismatch'));
            return false;
        }

        return true;
    };

    const handleBusinessContinue = async () => {
        if (!validateBusinessFields() || !(await requireNetwork())) return;

        const payload = buildRegistrationPayload();
        setLoading(true);
        try {
            const response = await fetchWithTimeout(`${API_BASE_URL}/api/register/check-business`, {
                method: 'POST',
                headers: JSON_HEADERS,
                body: JSON.stringify({
                    business_name: payload.business_name,
                    business_location: payload.business_location,
                    business_type: payload.business_type,
                    business_structure: payload.business_structure,
                    branches: payload.branches,
                }),
            });
            const data = await readResponse(response);

            if (response.ok && data?.success === true) {
                setStep(2);
            } else {
                Alert.alert(t('app.error'), data?.error || t('admin_signup.error_general'));
            }
        } catch (error) {
            const message = error instanceof Error ? error.message : '';
            Alert.alert(
                t('app.error'),
                /Network request failed|Aborted|timeout/i.test(message)
                    ? t('admin_signup.error_network')
                    : t('admin_signup.error_general')
            );
        } finally {
            setLoading(false);
        }
    };

    const handlePersonalPreview = async () => {
        if (!validatePersonalFields() || !(await requireNetwork())) return;

        const payload = buildRegistrationPayload();
        setLoading(true);
        try {
            const response = await fetchWithTimeout(`${API_BASE_URL}/api/register/check-personal`, {
                method: 'POST',
                headers: JSON_HEADERS,
                body: JSON.stringify(payload),
            });
            const data = await readResponse(response);

            if (response.ok && data?.success === true) {
                setStep(3);
            } else {
                Alert.alert(t('app.error'), data?.error || t('admin_signup.error_general'));
            }
        } catch (error) {
            const message = error instanceof Error ? error.message : '';
            Alert.alert(
                t('app.error'),
                /Network request failed|Aborted|timeout/i.test(message)
                    ? t('admin_signup.error_network')
                    : t('admin_signup.error_general')
            );
        } finally {
            setLoading(false);
        }
    };

    const handleVerify = async () => {
        if (!validateBusinessFields() || !validatePersonalFields() || !(await requireNetwork())) return;

        const payload = buildRegistrationPayload();
        setLoading(true);
        try {
            const precheckResponse = await fetchWithTimeout(`${API_BASE_URL}/api/register/precheck`, {
                method: 'POST',
                headers: JSON_HEADERS,
                body: JSON.stringify(payload),
            });
            const precheckData = await readResponse(precheckResponse);

            if (!precheckResponse.ok || precheckData?.success !== true || precheckData?.verified !== true) {
                Alert.alert(t('app.error'), precheckData?.error || t('admin_signup.error_general'));
                if (precheckData?.step === 'business') setStep(1);
                if (precheckData?.step === 'personal') setStep(2);
                return;
            }

            const response = await fetchWithTimeout(`${API_BASE_URL}/api/register/initiate`, {
                method: 'POST',
                headers: JSON_HEADERS,
                body: JSON.stringify(payload),
            });
            const data = await readResponse(response);

            if (response.ok && data?.requiresOtp) {
                setPendingOtp({ email: payload.email, role: 'admin' });
                setOtpVisible(true);
                if (data.email_sent === false) {
                    Alert.alert(t('otp.sent_title'), t('otp.email_failed'));
                }
            } else {
                Alert.alert(t('app.error'), data?.error || t('admin_signup.error_general'));
            }
        } catch (error) {
            const message = error instanceof Error ? error.message : '';
            Alert.alert(
                t('app.error'),
                /Network request failed|Aborted|timeout/i.test(message)
                    ? t('admin_signup.error_network')
                    : t('admin_signup.error_general')
            );
        } finally {
            setLoading(false);
        }
    };

    // The reusable OTP modal calls this only after the code is verified. The
    // verified token contains the complete registration payload.
    const handleOtpVerified = async (data: unknown) => {
        if (!(await requireNetwork())) return;

        const token = data && typeof data === 'object'
            ? (data as { registrationToken?: string }).registrationToken
            : undefined;
        if (!token) {
            throw new Error(t('admin_signup.error_general'));
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
                throw new Error(result?.error || t('admin_signup.error_general'));
            }

            setOtpVisible(false);
            router.replace({
                pathname: '/onboarding',
                params: {
                    email: pendingOtp?.email || formData.email.trim(),
                    role: 'admin',
                    setupToken: result?.setupToken || '',
                    lang,
                    business_name: formData.business_name.trim(),
                    business_location: formData.business_location.trim(),
                },
            } as any);
        } catch (error) {
            const message = error instanceof Error ? error.message : '';
            if (/Network request failed|Aborted|timeout/i.test(message)) {
                throw new Error(t('admin_signup.error_network'));
            }
            throw error;
        }
    };

    const handleBack = () => {
        if (step === 1) {
            router.back();
        } else {
            setStep((current) => (Math.max(1, current - 1) as SignupStep));
        }
    };

    const renderReview = (editable: boolean) => (
        <>
            <ReviewSection
                title={text('registration_wizard.business', 'Taarifa za Biashara')}
                onEdit={editable ? () => setStep(1) : undefined}
            >
                <ReviewRow
                    label={text('registration_wizard.business_name', 'Jina la Biashara')}
                    value={formData.business_name.trim()}
                />
                <ReviewRow
                    label={text('registration_wizard.business_type', 'Aina ya Biashara')}
                    value={formData.business_type ? businessTypeLabel(formData.business_type) : '-'}
                />
                <ReviewRow
                    label={text('registration_wizard.business_location', 'Eneo la Biashara')}
                    value={formData.business_location.trim()}
                />
                <ReviewRow
                    label={text('registration_wizard.business_structure', 'Muundo wa Biashara')}
                    value={businessStructure === 'branch'
                        ? text('registration_wizard.branch', 'Yenye Matawi')
                        : text('registration_wizard.non_branch', 'Bila Matawi')}
                />
                {businessStructure === 'branch' && branches.map((branch, index) => (
                    <View key={index} style={styles.branchReviewCard}>
                        <Text style={styles.branchReviewTitle}>
                            {text('registration_wizard.branch', 'Tawi')} {index + 1}
                        </Text>
                        <ReviewRow
                            label={text('registration_wizard.branch_name', 'Jina la Tawi')}
                            value={branch.branch_name.trim()}
                        />
                        <ReviewRow
                            label={text('registration_wizard.location', 'Mahali')}
                            value={branch.location.trim()}
                        />
                        {branch.latitude !== null && branch.longitude !== null ? (
                            <ReviewRow
                                label="GPS"
                                value={`${branch.latitude.toFixed(5)}, ${branch.longitude.toFixed(5)}`}
                            />
                        ) : null}
                    </View>
                ))}
            </ReviewSection>

            <ReviewSection
                title={text('registration_wizard.personal', 'Taarifa Binafsi')}
                onEdit={editable ? () => setStep(2) : undefined}
            >
                <ReviewRow
                    label={text('registration_wizard.full_name', 'Jina Kamili')}
                    value={formData.full_name.trim()}
                />
                <ReviewRow
                    label={text('registration_wizard.email', 'Barua Pepe')}
                    value={formData.email.trim()}
                />
                <ReviewRow
                    label={text('registration_wizard.phone', 'Nambari ya Simu')}
                    value={formData.phone.trim() || text('registration_wizard.not_provided', 'Haijatolewa')}
                />
                <ReviewRow
                    label={text('registration_wizard.password', 'Nenosiri')}
                    value={'•'.repeat(Math.max(8, Math.min(formData.password.length, 16)))}
                />
            </ReviewSection>
        </>
    );

    return (
        <KeyboardAvoidingView
            style={styles.keyboardAvoid}
            behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        >
            <ScrollView
                contentContainerStyle={styles.container}
                showsVerticalScrollIndicator={false}
                keyboardShouldPersistTaps="handled"
            >
                <Text style={styles.header}>{t('admin_signup.title')}</Text>
                <Text style={styles.subtitle}>{t('admin_signup.subtitle')}</Text>

                <StepIndicator step={step} labels={stepLabels} />
                <Text style={styles.stepCounter}>
                    {text('registration_wizard.step_counter', 'Hatua {current} ya {total}')
                        .replace('{current}', String(step))
                        .replace('{total}', String(stepLabels.length))}
                </Text>

                <View style={styles.formContainer}>
                    {step === 1 ? (
                        <>
                            <TextInput
                                style={styles.input}
                                placeholder={t('admin_signup.business_name_placeholder')}
                                value={formData.business_name}
                                onChangeText={(value) => updateFormData('business_name', value)}
                                editable={!loading}
                                placeholderTextColor="#95a5a6"
                            />

                            <TouchableOpacity
                                style={styles.input}
                                onPress={() => setBusinessTypeModalVisible(true)}
                                disabled={loading}
                                accessibilityRole="button"
                            >
                                <Text
                                    style={[
                                        styles.selectButtonText,
                                        formData.business_type && styles.selectButtonTextSelected,
                                    ]}
                                    numberOfLines={1}
                                >
                                    {formData.business_type
                                        ? businessTypeLabel(formData.business_type)
                                        : text('registration_wizard.business_type_placeholder', 'Chagua aina ya biashara *')}
                                </Text>
                            </TouchableOpacity>

                            <TextInput
                                style={styles.input}
                                placeholder={t('admin_signup.business_location_placeholder')}
                                value={formData.business_location}
                                onChangeText={(value) => updateFormData('business_location', value)}
                                editable={!loading}
                                placeholderTextColor="#95a5a6"
                            />

                            <Text style={styles.fieldLabel}>
                                {text('registration_wizard.business_structure', 'Muundo wa Biashara')} *
                            </Text>
                            <View style={styles.structureOptions}>
                                <TouchableOpacity
                                    style={[styles.structureButton, businessStructure === 'non_branch' && styles.structureButtonActive]}
                                    onPress={() => setBusinessStructure('non_branch')}
                                    disabled={loading}
                                >
                                    <Text style={[styles.structureButtonText, businessStructure === 'non_branch' && styles.structureButtonTextActive]}>
                                        {text('registration_wizard.non_branch', 'Bila Matawi')}
                                    </Text>
                                </TouchableOpacity>
                                <TouchableOpacity
                                    style={[styles.structureButton, businessStructure === 'branch' && styles.structureButtonActive]}
                                    onPress={() => {
                                        setBusinessStructure('branch');
                                        if (branches.length === 0) addBranch();
                                    }}
                                    disabled={loading}
                                >
                                    <Text style={[styles.structureButtonText, businessStructure === 'branch' && styles.structureButtonTextActive]}>
                                        {text('registration_wizard.branch', 'Yenye Matawi')}
                                    </Text>
                                </TouchableOpacity>
                            </View>

                            {businessStructure === 'branch' ? (
                                <View style={styles.branchesCard}>
                                    <Text style={styles.branchesTitle}>
                                        {text('registration_wizard.branches', 'MATAWI YA BIASHARA')} *
                                    </Text>
                                    <Text style={styles.branchesDescription}>
                                        Kila tawi linahitaji jina na eneo lake. Unaweza kuchukua eneo kwa GPS.
                                    </Text>

                                    {branches.map((branch, index) => (
                                        <View key={index} style={styles.branchCard}>
                                            <View style={styles.branchCardHeader}>
                                                <Text style={styles.branchCardTitle}>
                                                    {text('registration_wizard.branch', 'Tawi')} {index + 1}
                                                </Text>
                                                {branches.length > 1 ? (
                                                    <TouchableOpacity onPress={() => removeBranch(index)} disabled={loading}>
                                                        <Text style={styles.removeBranchText}>
                                                            {text('registration_wizard.remove', 'Ondoa')}
                                                        </Text>
                                                    </TouchableOpacity>
                                                ) : null}
                                            </View>
                                            <TextInput
                                                style={[styles.input, styles.branchInput]}
                                                placeholder={text('registration_wizard.branch_name_placeholder', 'Jina la Tawi *')}
                                                value={branch.branch_name}
                                                onChangeText={(value) => updateBranch(index, 'branch_name', value)}
                                                editable={!loading}
                                                placeholderTextColor="#95a5a6"
                                            />
                                            <TextInput
                                                style={[styles.input, styles.branchInput]}
                                                placeholder={text('registration_wizard.branch_location_placeholder', 'Mahali pa Tawi *')}
                                                value={branch.location}
                                                onChangeText={(value) => updateBranch(index, 'location', value)}
                                                editable={!loading}
                                                placeholderTextColor="#95a5a6"
                                            />
                                            <TouchableOpacity
                                                style={styles.gpsButton}
                                                onPress={() => captureBranchLocation(index)}
                                                disabled={loading}
                                            >
                                                <Text style={styles.gpsButtonText}>
                                                    {branch.latitude !== null
                                                        ? `GPS: ${branch.latitude.toFixed(5)}, ${branch.longitude?.toFixed(5)}`
                                                        : text('registration_wizard.capture_gps', 'Pata Eneo la Tawi (GPS)')}
                                                </Text>
                                            </TouchableOpacity>
                                        </View>
                                    ))}

                                    <TouchableOpacity
                                        style={styles.addBranchButton}
                                        onPress={addBranch}
                                        disabled={loading}
                                    >
                                        <Text style={styles.addBranchText}>
                                            {text('registration_wizard.add_branch', '+ Ongeza Tawi')}
                                        </Text>
                                    </TouchableOpacity>
                                </View>
                            ) : null}

                            <TouchableOpacity
                                style={[styles.signupButton, loading && styles.disabledButton]}
                                onPress={handleBusinessContinue}
                                disabled={loading}
                            >
                                {loading ? (
                                    <SmartCartLoader size="compact" color="#ffffff" />
                                ) : (
                                    <Text style={styles.signupButtonText}>
                                        {text('registration_wizard.continue', 'Endelea')}
                                    </Text>
                                )}
                            </TouchableOpacity>
                        </>
                    ) : null}

                    {step === 2 ? (
                        <>
                            <TextInput
                                style={styles.input}
                                placeholder={t('admin_signup.full_name_placeholder')}
                                value={formData.full_name}
                                onChangeText={(value) => updateFormData('full_name', value)}
                                autoCapitalize="words"
                                editable={!loading}
                                placeholderTextColor="#95a5a6"
                            />
                            <TextInput
                                style={styles.input}
                                placeholder={t('admin_signup.email_placeholder')}
                                value={formData.email}
                                onChangeText={(value) => updateFormData('email', value)}
                                keyboardType="email-address"
                                autoCapitalize="none"
                                autoComplete="email"
                                editable={!loading}
                                placeholderTextColor="#95a5a6"
                            />
                            <TextInput
                                style={styles.input}
                                placeholder={t('admin_signup.phone_placeholder')}
                                value={formData.phone}
                                onChangeText={(value) => updateFormData('phone', value)}
                                keyboardType="phone-pad"
                                editable={!loading}
                                placeholderTextColor="#95a5a6"
                            />
                            <TextInput
                                style={styles.input}
                                placeholder={t('admin_signup.password_placeholder')}
                                value={formData.password}
                                onChangeText={(value) => updateFormData('password', value)}
                                secureTextEntry
                                editable={!loading}
                                placeholderTextColor="#95a5a6"
                            />
                            <TextInput
                                style={styles.input}
                                placeholder={t('admin_signup.confirm_password_placeholder')}
                                value={formData.confirmPassword}
                                onChangeText={(value) => updateFormData('confirmPassword', value)}
                                secureTextEntry
                                editable={!loading}
                                placeholderTextColor="#95a5a6"
                            />

                            <TouchableOpacity
                                style={[styles.signupButton, loading && styles.disabledButton]}
                                onPress={handlePersonalPreview}
                                disabled={loading}
                            >
                                {loading ? (
                                    <SmartCartLoader size="compact" color="#ffffff" />
                                ) : (
                                    <Text style={styles.signupButtonText}>
                                        {text('registration_wizard.preview', 'Hakiki')}
                                    </Text>
                                )}
                            </TouchableOpacity>
                        </>
                    ) : null}

                    {step === 3 ? (
                        <>
                            <Text style={styles.sectionTitle}>
                                {text('registration_wizard.review', 'Hakiki Taarifa')}
                            </Text>
                            <Text style={styles.sectionDescription}>
                                {text('registration_wizard.review_description', 'Kagua taarifa zote kabla ya kuendelea. Tumia Hariri kubadilisha.')}
                            </Text>
                            {renderReview(true)}
                            <TouchableOpacity
                                style={styles.signupButton}
                                onPress={() => setStep(4)}
                            >
                                <Text style={styles.signupButtonText}>
                                    {text('registration_wizard.continue_to_verify', 'Endelea kuthibitisha')}
                                </Text>
                            </TouchableOpacity>
                        </>
                    ) : null}

                    {step === 4 ? (
                        <>
                            <Text style={styles.sectionTitle}>
                                {text('registration_wizard.verify', 'Uthibitisho')}
                            </Text>
                            <Text style={styles.sectionDescription}>
                                {text('registration_wizard.verify_description', 'Tutakagua taarifa zako na kutumia msimbo wa tarakimu 6 kwenye {email}.')
                                    .replace('{email}', formData.email.trim())}
                            </Text>
                            {renderReview(false)}
                            <TouchableOpacity
                                style={[styles.signupButton, loading && styles.disabledButton]}
                                onPress={handleVerify}
                                disabled={loading}
                            >
                                {loading ? (
                                    <SmartCartLoader size="compact" color="#ffffff" />
                                ) : (
                                    <Text style={styles.signupButtonText}>
                                        {text('registration_wizard.verify_button', 'Thibitisha')}
                                    </Text>
                                )}
                            </TouchableOpacity>
                        </>
                    ) : null}

                    <TouchableOpacity
                        style={[styles.backButton, loading && styles.disabledButton]}
                        onPress={handleBack}
                        disabled={loading}
                    >
                        <Text style={styles.backButtonText}>{t('admin_signup.back_button')}</Text>
                    </TouchableOpacity>
                </View>

                {step <= 2 ? (
                    <View style={styles.infoContainer}>
                        <Text style={styles.infoText}>{t('admin_signup.info_title')}</Text>
                        <Text style={styles.infoPoint}>• {t('admin_signup.info_point1')}</Text>
                        <Text style={styles.infoPoint}>• {t('admin_signup.info_point2')}</Text>
                        <Text style={styles.infoPoint}>• {t('admin_signup.info_point3')}</Text>
                    </View>
                ) : null}
            </ScrollView>

            <Modal
                visible={businessTypeModalVisible}
                transparent
                animationType="slide"
                onRequestClose={() => setBusinessTypeModalVisible(false)}
            >
                <View style={styles.pickerOverlay}>
                    <View style={styles.pickerSheet}>
                        <Text style={styles.pickerTitle}>
                            {text('registration_wizard.business_type', 'Aina ya Biashara')}
                        </Text>
                        <ScrollView keyboardShouldPersistTaps="handled">
                            {BUSINESS_TYPES.map((businessType) => (
                                <TouchableOpacity
                                    key={businessType}
                                    style={styles.pickerOption}
                                    onPress={() => {
                                        updateFormData('business_type', businessType);
                                        setBusinessTypeModalVisible(false);
                                    }}
                                >
                                    <Text style={styles.pickerOptionText}>{businessTypeLabel(businessType)}</Text>
                                    {formData.business_type === businessType ? (
                                        <Text style={styles.pickerSelected}>✓</Text>
                                    ) : null}
                                </TouchableOpacity>
                            ))}
                        </ScrollView>
                        <TouchableOpacity
                            style={styles.pickerClose}
                            onPress={() => setBusinessTypeModalVisible(false)}
                        >
                            <Text style={styles.pickerCloseText}>{t('app.close')}</Text>
                        </TouchableOpacity>
                    </View>
                </View>
            </Modal>

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
        marginBottom: 20,
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
        borderColor: '#e74c3c',
        backgroundColor: '#e74c3c',
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
        color: '#e74c3c',
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
    selectButtonText: {
        color: '#95a5a6',
        fontSize: 16,
    },
    selectButtonTextSelected: {
        color: '#2c3e50',
    },
    fieldLabel: {
        alignSelf: 'flex-start',
        color: '#34495e',
        fontSize: 13,
        fontWeight: '700',
        marginBottom: -7,
    },
    structureOptions: {
        width: '100%',
        flexDirection: 'row',
        gap: 8,
    },
    structureButton: {
        flex: 1,
        height: 44,
        borderRadius: 12,
        borderWidth: 1,
        borderColor: '#ddd',
        backgroundColor: '#ffffff',
        alignItems: 'center',
        justifyContent: 'center',
    },
    structureButtonActive: {
        borderColor: '#3498db',
        backgroundColor: '#3498db',
    },
    structureButtonText: {
        color: '#34495e',
        fontSize: 12,
        fontWeight: '700',
    },
    structureButtonTextActive: {
        color: '#ffffff',
    },
    branchesCard: {
        width: '100%',
        backgroundColor: '#eef4fa',
        borderRadius: 14,
        padding: 12,
    },
    branchesTitle: {
        color: '#2c3e50',
        fontSize: 13,
        fontWeight: '700',
        marginBottom: 4,
    },
    branchesDescription: {
        color: '#7a8ba0',
        fontSize: 11,
        marginBottom: 10,
    },
    branchCard: {
        backgroundColor: '#ffffff',
        borderRadius: 12,
        padding: 10,
        marginBottom: 8,
    },
    branchCardHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 6,
    },
    branchCardTitle: {
        color: '#34495e',
        fontSize: 12,
        fontWeight: '700',
    },
    removeBranchText: {
        color: '#e74c3c',
        fontSize: 12,
        fontWeight: '700',
    },
    branchInput: {
        marginBottom: 6,
    },
    gpsButton: {
        backgroundColor: '#e67e22',
        borderRadius: 9,
        padding: 10,
        alignItems: 'center',
    },
    gpsButtonText: {
        color: '#ffffff',
        fontSize: 12,
        fontWeight: '700',
    },
    addBranchButton: {
        borderWidth: 2,
        borderStyle: 'dashed',
        borderColor: '#3498db',
        borderRadius: 10,
        padding: 10,
        alignItems: 'center',
    },
    addBranchText: {
        color: '#3498db',
        fontWeight: '700',
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
    reviewCard: {
        width: '100%',
        backgroundColor: '#ffffff',
        borderWidth: 1,
        borderColor: '#e5e9ed',
        borderRadius: 14,
        padding: 14,
    },
    reviewCardHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: 8,
    },
    reviewCardTitle: {
        flex: 1,
        color: '#2c3e50',
        fontSize: 14,
        fontWeight: '800',
    },
    editButtonText: {
        color: '#e74c3c',
        fontSize: 13,
        fontWeight: '700',
    },
    reviewRow: {
        flexDirection: 'row',
        alignItems: 'flex-start',
        paddingVertical: 6,
        borderTopWidth: StyleSheet.hairlineWidth,
        borderTopColor: '#eef2f7',
    },
    reviewLabel: {
        width: '42%',
        color: '#7f8c8d',
        fontSize: 12,
        paddingRight: 8,
    },
    reviewValue: {
        flex: 1,
        color: '#2c3e50',
        fontSize: 13,
        fontWeight: '600',
    },
    branchReviewCard: {
        backgroundColor: '#f8fafc',
        borderRadius: 10,
        padding: 10,
        marginTop: 8,
    },
    branchReviewTitle: {
        color: '#34495e',
        fontSize: 12,
        fontWeight: '800',
        marginBottom: 4,
    },
    signupButton: {
        width: '100%',
        height: 60,
        justifyContent: 'center',
        alignItems: 'center',
        borderRadius: 12,
        marginTop: 10,
        backgroundColor: '#e74c3c',
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
    pickerOverlay: {
        flex: 1,
        backgroundColor: 'rgba(0,0,0,0.45)',
        justifyContent: 'flex-end',
    },
    pickerSheet: {
        backgroundColor: '#ffffff',
        borderTopLeftRadius: 20,
        borderTopRightRadius: 20,
        padding: 20,
        maxHeight: '75%',
    },
    pickerTitle: {
        color: '#2c3e50',
        fontSize: 17,
        fontWeight: '800',
        marginBottom: 12,
    },
    pickerOption: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        paddingVertical: 13,
        borderBottomWidth: 1,
        borderBottomColor: '#eef2f7',
    },
    pickerOptionText: {
        flex: 1,
        color: '#2c3e50',
        fontSize: 15,
        fontWeight: '600',
    },
    pickerSelected: {
        color: '#27ae60',
        fontSize: 16,
        fontWeight: '800',
    },
    pickerClose: {
        paddingVertical: 12,
        alignItems: 'center',
    },
    pickerCloseText: {
        color: '#e74c3c',
        fontWeight: '700',
    },
});
