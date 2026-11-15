import { Ionicons } from '@expo/vector-icons';
import { CameraView, useCameraPermissions } from 'expo-camera';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Modal,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';

/**
 * ONE reusable QR scanner for the mobile app.
 *
 * Used by administrator stock receiving, seller sale cart, fridge/freezer
 * transfers and the global order scanner. It only reads a code and hands the
 * token to `onScan`; the screen calls the matching API and decides what a
 * successful scan means. The component NEVER changes a quantity by itself: the
 * caller updates its numbers from the backend response.
 *
 * A camera detection is not proof that the operation succeeded, so counters
 * only move when `onScan` resolves `{ ok: true }`.
 */
export interface ScanOutcome {
  ok: boolean;
  message?: string;
}

interface Props {
  visible: boolean;
  title: string;
  hint?: string;
  finishLabel?: string;
  cancelLabel?: string;
  /** Send the token to the backend. Resolve ok:true only after confirmation. */
  onScan: (token: string) => Promise<ScanOutcome>;
  /** Called when the user presses the finish button (e.g. commit a session). */
  onFinish?: () => Promise<void> | void;
  /** Called for every close (finish or cancel). */
  onClose: (counts: { scanned: number; rejected: number }) => void;
}

const REPEAT_MS = 1500;

export function normalizeQrToken(raw: string | null | undefined): string {
  if (!raw) return '';
  let token = String(raw).trim();
  if (!token) return '';

  const lower = token.toLowerCase();
  for (const prefix of ['dmk1:', 'dmk:', 'dukamkononi:']) {
    if (lower.startsWith(prefix)) {
      token = token.slice(prefix.length).trim();
      break;
    }
  }

  if (/^https?:\/\//i.test(token)) {
    const parts = token.split('?')[0].split('/').filter(Boolean);
    const last = parts[parts.length - 1];
    if (last) token = decodeURIComponent(last);
  }

  return token.replace(/\s+/g, '');
}

export default function QrScannerModal({
  visible,
  title,
  hint,
  finishLabel = 'Maliza',
  cancelLabel = 'Funga',
  onScan,
  onFinish,
  onClose,
}: Props) {
  const [permission, requestPermission] = useCameraPermissions();
  const [scanned, setScanned] = useState(0);
  const [rejected, setRejected] = useState(0);
  const [status, setStatus] = useState('');
  const [statusTone, setStatusTone] = useState<'muted' | 'ok' | 'error'>('muted');
  const [busy, setBusy] = useState(false);
  const [finishing, setFinishing] = useState(false);
  const [manual, setManual] = useState('');

  const lastRef = useRef<{ token: string; at: number }>({ token: '', at: 0 });
  const busyRef = useRef(false);
  const closedRef = useRef(false);

  useEffect(() => {
    if (!visible) return;
    setScanned(0);
    setRejected(0);
    setStatus('');
    setStatusTone('muted');
    setManual('');
    setBusy(false);
    setFinishing(false);
    busyRef.current = false;
    closedRef.current = false;
    lastRef.current = { token: '', at: 0 };
    if (permission && !permission.granted && permission.canAskAgain) {
      requestPermission().catch(() => {});
    }
  }, [visible, permission, requestPermission]);

  const submit = useCallback(
    async (raw: string) => {
      const token = normalizeQrToken(raw);
      if (!token) {
        setStatus('Code si sahihi. Jaribu tena.');
        setStatusTone('error');
        return;
      }
      if (busyRef.current || closedRef.current) return;

      const now = Date.now();
      if (token === lastRef.current.token && now - lastRef.current.at < REPEAT_MS) return;
      lastRef.current = { token, at: now };

      busyRef.current = true;
      setBusy(true);
      setStatus(`Inathibitisha ${token}...`);
      setStatusTone('muted');

      try {
        const result = await onScan(token);
        if (result && result.ok) {
          setScanned((n) => n + 1);
          setStatus(result.message || `Imefanikiwa: ${token}`);
          setStatusTone('ok');
        } else {
          setRejected((n) => n + 1);
          setStatus((result && result.message) || 'Kipande hakikubaliwa.');
          setStatusTone('error');
        }
      } catch (error: any) {
        setRejected((n) => n + 1);
        setStatus(error?.message || 'Hitilafu ya mtandao. Jaribu tena.');
        setStatusTone('error');
      } finally {
        busyRef.current = false;
        setBusy(false);
        setManual('');
      }
    },
    [onScan]
  );

  const close = useCallback(
    async (finish: boolean) => {
      if (closedRef.current) return;
      closedRef.current = true;
      if (finish && onFinish) {
        setFinishing(true);
        try {
          await onFinish();
        } catch (error: any) {
          setStatus(error?.message || 'Imeshindikana kukamilisha.');
          setStatusTone('error');
        } finally {
          setFinishing(false);
        }
      }
      onClose({ scanned, rejected });
    },
    [finishLabel, onClose, onFinish, rejected, scanned]
  );

  if (!visible) return null;

  const granted = permission?.granted;

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={() => close(false)}>
      <View style={styles.backdrop}>
        <View style={styles.card}>
          <View style={styles.head}>
            <Text style={styles.title}>{title}</Text>
            {hint ? <Text style={styles.hint}>{hint}</Text> : null}
            <View style={styles.counters}>
              <View style={[styles.badge, styles.badgeOk]}>
                <Text style={styles.badgeOkText}>Zilizoskaniwa: {scanned}</Text>
              </View>
              <View style={[styles.badge, styles.badgeBad]}>
                <Text style={styles.badgeBadText}>Zilizokataliwa: {rejected}</Text>
              </View>
            </View>
          </View>

          <View style={styles.stage}>
            {granted ? (
              <CameraView
                style={StyleSheet.absoluteFill}
                facing="back"
                barcodeScannerSettings={{ barcodeTypes: ['qr'] }}
                onBarcodeScanned={busy ? undefined : ({ data }) => submit(data)}
              />
            ) : (
              <View style={styles.permissionBox}>
                <Ionicons name="camera-outline" size={28} color="#cbd5e1" />
                <Text style={styles.permissionText}>
                  {permission && !permission.canAskAgain
                    ? 'Ruhusa ya kamera imezuiwa. Wasilisha kwenye mipangilio ya simu au tumia skana ya nje.'
                    : 'Inahitaji ruhusa ya kamera kuskani QR.'}
                </Text>
                {permission?.canAskAgain ? (
                  <TouchableOpacity style={styles.permissionBtn} onPress={() => requestPermission().catch(() => {})}>
                    <Text style={styles.permissionBtnText}>Ruhusu kamera</Text>
                  </TouchableOpacity>
                ) : null}
              </View>
            )}
            <View style={styles.frame} pointerEvents="none" />
          </View>

          <View style={styles.manualRow}>
            <TextInput
              style={styles.manualInput}
              value={manual}
              onChangeText={setManual}
              placeholder="Skana ya USB/Bluetooth au andika code"
              placeholderTextColor="#94a3b8"
              autoCapitalize="none"
              autoCorrect={false}
              onSubmitEditing={() => submit(manual)}
              returnKeyType="done"
            />
            <TouchableOpacity style={styles.sendBtn} onPress={() => submit(manual)} disabled={busy}>
              {busy ? <ActivityIndicator color="#fff" /> : <Ionicons name="arrow-forward" size={18} color="#fff" />}
            </TouchableOpacity>
          </View>

          <Text
            style={[
              styles.status,
              statusTone === 'ok' && { color: '#047857' },
              statusTone === 'error' && { color: '#b91c1c' },
            ]}
            numberOfLines={3}
          >
            {status || 'Kamera iko tayari. Skani kipande.'}
          </Text>

          <View style={styles.foot}>
            <TouchableOpacity
              style={[styles.finishBtn, finishing && { opacity: 0.6 }]}
              onPress={() => close(true)}
              disabled={finishing}
            >
              {finishing ? <ActivityIndicator color="#fff" /> : <Text style={styles.finishText}>{finishLabel}</Text>}
            </TouchableOpacity>
            <TouchableOpacity style={styles.cancelBtn} onPress={() => close(false)} disabled={finishing}>
              <Text style={styles.cancelText}>{cancelLabel}</Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(15,23,42,.72)',
    justifyContent: 'center',
    padding: 14,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: 20,
    overflow: 'hidden',
    maxHeight: '94%',
  },
  head: { paddingHorizontal: 18, paddingTop: 16, paddingBottom: 12 },
  title: { fontSize: 15, fontWeight: '800', color: '#0f172a' },
  hint: { fontSize: 12, color: '#64748b', marginTop: 5, lineHeight: 17 },
  counters: { flexDirection: 'row', gap: 8, marginTop: 12 },
  badge: { flex: 1, borderRadius: 10, paddingVertical: 8, paddingHorizontal: 10 },
  badgeOk: { backgroundColor: '#ecfdf5' },
  badgeBad: { backgroundColor: '#fef2f2' },
  badgeOkText: { fontSize: 12, fontWeight: '700', color: '#047857' },
  badgeBadText: { fontSize: 12, fontWeight: '700', color: '#b91c1c' },
  stage: {
    height: 260,
    backgroundColor: '#0f172a',
    marginHorizontal: 18,
    borderRadius: 14,
    overflow: 'hidden',
    alignItems: 'center',
    justifyContent: 'center',
  },
  frame: {
    position: 'absolute',
    width: 190,
    height: 190,
    borderWidth: 2,
    borderColor: 'rgba(255,255,255,.85)',
    borderRadius: 16,
  },
  permissionBox: { alignItems: 'center', paddingHorizontal: 22, gap: 10 },
  permissionText: { color: '#cbd5e1', fontSize: 12, textAlign: 'center', lineHeight: 17 },
  permissionBtn: { backgroundColor: '#1e3a8a', borderRadius: 10, paddingHorizontal: 14, paddingVertical: 9 },
  permissionBtnText: { color: '#fff', fontSize: 12, fontWeight: '700' },
  manualRow: { flexDirection: 'row', gap: 8, paddingHorizontal: 18, marginTop: 12 },
  manualInput: {
    flex: 1,
    borderWidth: 1,
    borderColor: '#dbe3ea',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 13,
    color: '#0f172a',
  },
  sendBtn: {
    width: 44,
    borderRadius: 10,
    backgroundColor: '#1e3a8a',
    alignItems: 'center',
    justifyContent: 'center',
  },
  status: { fontSize: 12, color: '#475569', paddingHorizontal: 18, marginTop: 10, minHeight: 16 },
  foot: { flexDirection: 'row', gap: 8, padding: 18, paddingTop: 12 },
  finishBtn: {
    flex: 1,
    backgroundColor: '#1e3a8a',
    borderRadius: 11,
    paddingVertical: 13,
    alignItems: 'center',
  },
  finishText: { color: '#fff', fontSize: 13, fontWeight: '800' },
  cancelBtn: {
    flex: 1,
    borderWidth: 1,
    borderColor: '#dbe3ea',
    borderRadius: 11,
    paddingVertical: 13,
    alignItems: 'center',
  },
  cancelText: { color: '#334155', fontSize: 13, fontWeight: '700' },
});
