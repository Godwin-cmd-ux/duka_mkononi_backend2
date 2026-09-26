import { Ionicons } from '@expo/vector-icons';
import { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Modal,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { useLang } from '../context/LanguageContext';
import { API_BASE_URL } from '../constants/api';
import { requireNetwork, fetchWithTimeout } from '../lib/network';

interface OtpVerificationModalProps {
  visible: boolean;
  email: string;
  /** Backend role used by the OTP endpoints: 'customer' | 'seller' | 'admin' */
  role: string;
  /** Brand color of the signup screen (used for the icon + button). */
  accentColor?: string;
  /** Called with the verify-otp success response when the code is correct.
   * May be async; if it rejects, the error is shown in the modal so the user
   * can retry with the already-verified token. */
  onVerified: (data: any) => Promise<void> | void;
  /** Called when the user cancels and wants to go back to the form. */
  onClose: () => void;
}

const OTP_LENGTH = 6;
const RESEND_COOLDOWN = 30;

// 📧 OTP entry modal — shown at the end of registration. The user enters the
// 6-digit code emailed to them; only after it verifies is the account created.
export default function OtpVerificationModal({
  visible,
  email,
  role,
  accentColor = '#3498db',
  onVerified,
  onClose,
}: OtpVerificationModalProps) {
  const { t } = useLang();
  const [digits, setDigits] = useState<string[]>(Array(OTP_LENGTH).fill(''));
  const [error, setError] = useState('');
  const [verifying, setVerifying] = useState(false);
  const [creatingAccount, setCreatingAccount] = useState(false);
  const [resending, setResending] = useState(false);
  const [countdown, setCountdown] = useState(RESEND_COOLDOWN);
  const inputsRef = useRef<(TextInput | null)[]>([]);
  // After the OTP is confirmed, the server hands back a short-lived token that
  // creates the account. We cache it so a transient network failure can be
  // retried without re-entering the code.
  const verifiedTokenRef = useRef<string | null>(null);

  // Reset the modal every time it opens
  useEffect(() => {
    if (visible) {
      setDigits(Array(OTP_LENGTH).fill(''));
      setError('');
      setVerifying(false);
      setCreatingAccount(false);
      setResending(false);
      setCountdown(RESEND_COOLDOWN);
      verifiedTokenRef.current = null;
    }
  }, [visible]);

  // Auto-focus the first box when the modal opens
  useEffect(() => {
    if (!visible) return;
    const timer = setTimeout(() => inputsRef.current[0]?.focus(), 350);
    return () => clearTimeout(timer);
  }, [visible]);

  // Resend countdown
  useEffect(() => {
    if (!visible || countdown <= 0) return;
    const timer = setTimeout(() => setCountdown((c) => c - 1), 1000);
    return () => clearTimeout(timer);
  }, [visible, countdown]);

  const focusInput = (index: number) => {
    const next = inputsRef.current[index];
    if (next) {
      next.focus();
    }
  };

  const handleDigitChange = (index: number, value: string) => {
    setError('');
    const cleaned = value.replace(/\D/g, '');
    if (cleaned.length === 0) {
      setDigits((prev) => {
        const next = [...prev];
        next[index] = '';
        return next;
      });
      if (index > 0) focusInput(index - 1);
      return;
    }
    // Take only the last typed character per box (pasted codes flow across boxes)
    const lastChar = cleaned.slice(-1);
    setDigits((prev) => {
      const next = [...prev];
      next[index] = lastChar;
      return next;
    });
    if (index < OTP_LENGTH - 1) focusInput(index + 1);
  };

  const handleKeyPress = (index: number, key: string) => {
    if (key === 'Backspace' && index > 0 && digits[index] === '') {
      focusInput(index - 1);
    }
  };

  // Create the account with the verified token. Any failure is surfaced in the
  // modal so the user can press the button again (the token is reused).
  const finalizeRegistration = async (token: string) => {
    setCreatingAccount(true);
    setError('');
    try {
      await onVerified({ registrationToken: token });
      // Success: the parent closes the modal and shows the confirmation.
    } catch (err: any) {
      console.error('❌ Account creation failed:', err);
      setError(err?.message || t('otp.error_general'));
    } finally {
      setCreatingAccount(false);
    }
  };

  const verifyOtp = async () => {
    if (!(await requireNetwork())) return;

    // Already verified (e.g. retrying after a transient network failure)?
    // Skip the OTP check and go straight to account creation.
    if (verifiedTokenRef.current) {
      await finalizeRegistration(verifiedTokenRef.current);
      return;
    }

    const code = digits.join('');
    if (code.length !== OTP_LENGTH) {
      setError(t('otp.error_required'));
      return;
    }

    setVerifying(true);
    setError('');

    try {
      const response = await fetchWithTimeout(`${API_BASE_URL}/api/register/verify-otp`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'ngrok-skip-browser-warning': 'true',
        },
        body: JSON.stringify({ email, role, otp: code }),
      });

      const data = await response.json();

      if (!response.ok) {
        setError(data?.error || t('otp.error_invalid'));
        return;
      }

      const token = data?.registrationToken;
      if (!token) {
        setError(t('otp.error_general'));
        return;
      }

      verifiedTokenRef.current = token;
      await finalizeRegistration(token);
    } catch (err: any) {
      console.error('❌ OTP verify error:', err);
      setError(
        err?.message?.includes('Network request failed')
          ? t('otp.error_network')
          : t('otp.error_general')
      );
    } finally {
      setVerifying(false);
    }
  };

  const resendOtp = async () => {
    if (!(await requireNetwork())) return;

    setResending(true);
    setError('');

    try {
      const response = await fetchWithTimeout(`${API_BASE_URL}/api/register/resend-otp`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'ngrok-skip-browser-warning': 'true',
        },
        body: JSON.stringify({ email, role }),
      });

      const data = await response.json();

      if (response.ok) {
        setDigits(Array(OTP_LENGTH).fill(''));
        setCountdown(RESEND_COOLDOWN);
        Alert.alert(t('otp.sent_title'), t('otp.sent_message', { email }));
      } else {
        setError(data?.error || t('otp.error_general'));
      }
    } catch (err: any) {
      console.error('❌ OTP resend error:', err);
      setError(
        err?.message?.includes('Network request failed')
          ? t('otp.error_network')
          : t('otp.error_general')
      );
    } finally {
      setResending(false);
    }
  };

  const canResend = countdown <= 0 && !resending;
  const busy = verifying || creatingAccount;

  return (
    <Modal
      visible={visible}
      transparent
      animationType="fade"
      statusBarTranslucent
      onRequestClose={() => {
        // Don't allow dismissing mid-flight — a failure could otherwise be lost.
        if (!busy) onClose();
      }}
    >
      <View style={styles.overlay}>
        <View style={styles.card}>
          {/* Close */}
          <TouchableOpacity
            style={[styles.closeButton, busy && styles.closeButtonDisabled]}
            onPress={onClose}
            disabled={busy}
            accessibilityLabel={t('app.funga') || 'Close'}
          >
            <Ionicons name="close" size={22} color="#95a5a6" />
          </TouchableOpacity>

          {/* Icon */}
          <View style={[styles.iconCircle, { backgroundColor: `${accentColor}1a` }]}>
            <Ionicons name="mail-unread-outline" size={34} color={accentColor} />
          </View>

          <Text style={styles.title}>{t('otp.title')}</Text>
          <Text style={styles.subtitle}>{t('otp.subtitle', { email })}</Text>

          {/* Code boxes */}
          <View style={styles.boxesRow}>
            {digits.map((digit, index) => (
              <TextInput
                key={index}
                ref={(ref) => {
                  inputsRef.current[index] = ref;
                }}
                style={[
                  styles.box,
                  { borderColor: digit ? accentColor : '#d5dbe1' },
                  error && styles.boxError,
                ]}
                value={digit}
                onChangeText={(value) => handleDigitChange(index, value)}
                onKeyPress={({ nativeEvent }) => handleKeyPress(index, nativeEvent.key)}
                keyboardType="number-pad"
                maxLength={2}
                selectTextOnFocus
                placeholder="•"
                placeholderTextColor="#c9cfd6"
                editable={!verifying && !creatingAccount}
              />
            ))}
          </View>

          {error ? (
            <View style={styles.errorBox}>
              <Ionicons name="alert-circle" size={16} color="#e74c3c" />
              <Text style={styles.errorText}>{error}</Text>
            </View>
          ) : null}

          {/* Verify button */}
          <TouchableOpacity
            style={[styles.verifyButton, { backgroundColor: accentColor }]}
            onPress={verifyOtp}
            disabled={verifying || creatingAccount}
            activeOpacity={0.8}
          >
            {verifying || creatingAccount ? (
              <>
                <ActivityIndicator color="white" size="small" />
                <Text style={styles.verifyButtonText}>
                  {creatingAccount ? t('otp.creating_account') : t('otp.verifying')}
                </Text>
              </>
            ) : (
              <>
                <Ionicons name="checkmark-circle-outline" size={20} color="white" />
                <Text style={styles.verifyButtonText}>{t('otp.verify_button')}</Text>
              </>
            )}
          </TouchableOpacity>

          {/* Resend */}
          <View style={styles.resendRow}>
            <Text style={styles.resendText}>
              {t('otp.didnt_receive')}{' '}
            </Text>
            {canResend ? (
              <TouchableOpacity onPress={resendOtp} disabled={resending}>
                {resending ? (
                  <ActivityIndicator size="small" color={accentColor} />
                ) : (
                  <Text style={[styles.resendLink, { color: accentColor }]}>
                    {t('otp.resend')}
                  </Text>
                )}
              </TouchableOpacity>
            ) : (
              <Text style={styles.resendCountdown}>
                {t('otp.resend_in', { seconds: String(countdown) })}
              </Text>
            )}
          </View>

          {/* Change email */}
          <TouchableOpacity style={styles.changeEmailButton} onPress={onClose} disabled={busy}>
            <Ionicons name="arrow-back" size={14} color="#7f8c8d" />
            <Text style={[styles.changeEmailText, busy && styles.changeEmailDisabled]}>
              {t('otp.change_email')}
            </Text>
          </TouchableOpacity>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.55)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
  },
  card: {
    width: '100%',
    maxWidth: 400,
    backgroundColor: '#ffffff',
    borderRadius: 24,
    paddingHorizontal: 24,
    paddingTop: 20,
    paddingBottom: 26,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: 0.18,
    shadowRadius: 24,
    elevation: 14,
  },
  closeButton: {
    position: 'absolute',
    top: 14,
    right: 14,
    padding: 6,
    borderRadius: 18,
    backgroundColor: '#f4f6f8',
  },
  closeButtonDisabled: {
    opacity: 0.4,
  },
  iconCircle: {
    width: 64,
    height: 64,
    borderRadius: 32,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 14,
  },
  title: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 6,
    textAlign: 'center',
  },
  subtitle: {
    fontSize: 13,
    color: '#7f8c8d',
    textAlign: 'center',
    lineHeight: 19,
    marginBottom: 22,
    paddingHorizontal: 6,
  },
  boxesRow: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 18,
  },
  box: {
    width: 46,
    height: 56,
    borderRadius: 12,
    borderWidth: 1.5,
    backgroundColor: '#f8fafc',
    textAlign: 'center',
    fontSize: 22,
    fontWeight: '700',
    color: '#2c3e50',
    padding: 0,
  },
  boxError: {
    borderColor: '#e74c3c',
  },
  errorBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fdecea',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
    marginBottom: 14,
    alignSelf: 'stretch',
  },
  errorText: {
    fontSize: 13,
    color: '#c0392b',
    fontWeight: '500',
    marginLeft: 6,
    flex: 1,
  },
  verifyButton: {
    width: '100%',
    height: 54,
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: 14,
    marginBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.15,
    shadowRadius: 8,
    elevation: 4,
  },
  verifyButtonText: {
    fontSize: 16,
    fontWeight: 'bold',
    color: 'white',
    marginLeft: 8,
    letterSpacing: 0.3,
  },
  resendRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 14,
  },
  resendText: {
    fontSize: 13,
    color: '#7f8c8d',
  },
  resendLink: {
    fontSize: 13,
    fontWeight: '700',
  },
  resendCountdown: {
    fontSize: 13,
    fontWeight: '600',
    color: '#95a5a6',
  },
  changeEmailButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 4,
  },
  changeEmailText: {
    fontSize: 13,
    color: '#7f8c8d',
    marginLeft: 5,
    textDecorationLine: 'underline',
  },
  changeEmailDisabled: {
    opacity: 0.4,
  },
});
