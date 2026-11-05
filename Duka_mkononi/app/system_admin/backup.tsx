// Duka_mkononi/app/system_admin/backup.tsx
// Mobile twin of the website's /system_admin/backup page.
//
// The mobile API exposes backup/status, backup and backup/download, so this
// screen can list the snapshots kept on the server, induce a new one and open
// a snapshot in the browser. Deletion stays on the web because the mobile API
// has no delete endpoint.
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as WebBrowser from 'expo-web-browser';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Alert,
  RefreshControl,
  SafeAreaView,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import SmartCartLoader from '../../components/SmartCartLoader';
import { API_BASE_URL } from '../../constants/api';
import { useLang } from '../../context/LanguageContext';
import { useSession } from '../../context/SessionContext';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';
import { useTheme, ThemeColors } from '../../lib/theme';

interface BackupFile {
  filename: string;
  fileSize: number;
  createdAt: string;
}

interface StatusPayload {
  success: boolean;
  backupCount?: number;
  lastBackup?: string | null;
  recentFiles?: BackupFile[];
  storage?: string;
}

const LOCALE_TAGS: Record<string, string> = {
  sw: 'sw-TZ',
  en: 'en-GB',
  fr: 'fr-FR',
  hi: 'hi-IN',
  es: 'es-ES',
  ur: 'ur-PK',
  de: 'de-DE',
  zh: 'zh-CN',
};

export default function SystemAdminBackupScreen() {
  const { colors: tc } = useTheme();
  const styles = useMemo(() => makeStyles(tc), [tc]);
  const { t, lang } = useLang();
  const { signOut } = useSession();

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [failed, setFailed] = useState(false);
  const [count, setCount] = useState(0);
  const [lastBackup, setLastBackup] = useState<string | null>(null);
  const [files, setFiles] = useState<BackupFile[]>([]);

  const load = useCallback(async () => {
    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        await signOut();
        return;
      }

      const res = await fetchWithTimeout(`${API_BASE_URL}/api/backup/status`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      const data: StatusPayload = await res.json();
      if (!data.success) throw new Error(data.storage || 'status failed');

      setCount(data.backupCount || 0);
      setLastBackup(data.lastBackup || null);
      setFiles(Array.isArray(data.recentFiles) ? data.recentFiles : []);
      setFailed(false);
    } catch {
      setFailed(true);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [signOut]);

  useEffect(() => {
    load();
  }, [load]);

  const induce = () => {
    Alert.alert(
      t('system_admin.backups_title'),
      t('system_admin.backups_confirm'),
      [
        { text: t('app.cancel'), style: 'cancel' },
        {
          text: t('system_admin.backups_induce'),
          onPress: async () => {
            if (!(await requireNetwork())) return;
            setBusy(true);
            try {
              const token = await AsyncStorage.getItem('userToken');
              // A full dump can take a while; don't let the default 15s abort it.
              const res = await fetchWithTimeout(
                `${API_BASE_URL}/api/backup`,
                {
                  method: 'POST',
                  headers: {
                    Authorization: `Bearer ${token}`,
                    'Content-Type': 'application/json',
                  },
                },
                120_000,
              );
              const data = await res.json();
              if (!res.ok || !data.success) throw new Error(data.error || 'failed');
              Alert.alert(t('app.success'), t('system_admin.backups_done'));
              await load();
            } catch {
              Alert.alert(t('app.error'), t('system_admin.backups_failed'));
            } finally {
              setBusy(false);
            }
          },
        },
      ],
    );
  };

  const download = async (index: number) => {
    try {
      await WebBrowser.openBrowserAsync(
        `${API_BASE_URL}/api/backup/download/${index}`,
      );
    } catch {
      Alert.alert(t('app.error'), t('system_admin.backups_failed'));
    }
  };

  const formatBytes = (bytes: number): string => {
    if (!bytes || bytes <= 0) return '—';
    if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`;
    if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${bytes} B`;
  };

  const formatDate = (value: string | null): string => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString(LOCALE_TAGS[lang] || 'sw-TZ', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  if (loading) {
    return (
      <SafeAreaView style={[styles.container, { backgroundColor: tc.page }]}>
        <View style={styles.center}>
          <SmartCartLoader size={64} />
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: tc.page }]}>
      <StatusBar barStyle="dark-content" />
      <View style={styles.header}>
        <View style={styles.headerText}>
          <Text style={styles.title}>{t('system_admin.backups_title')}</Text>
          <Text style={styles.subtitle}>{t('system_admin.backups_subtitle')}</Text>
        </View>
        <TouchableOpacity
          style={styles.refreshBtn}
          onPress={() => {
            setRefreshing(true);
            load();
          }}
          accessibilityRole="button"
          accessibilityLabel={t('app.refresh')}
        >
          <Ionicons name="refresh" size={20} color={tc.primary} />
        </TouchableOpacity>
      </View>

      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => {
              setRefreshing(true);
              load();
            }}
          />
        }
        showsVerticalScrollIndicator={false}
      >
        <TouchableOpacity
          style={[styles.induceBtn, busy && styles.induceBtnBusy]}
          onPress={induce}
          disabled={busy}
          activeOpacity={0.85}
        >
          <Ionicons
            name={busy ? 'hourglass-outline' : 'flash-outline'}
            size={19}
            color="#fff"
          />
          <Text style={styles.induceText}>
            {busy
              ? t('system_admin.backups_running')
              : t('system_admin.backups_induce')}
          </Text>
        </TouchableOpacity>

        <View style={styles.statsRow}>
          <View style={[styles.stat, { backgroundColor: tc.card, borderColor: tc.line }]}>
            <Text style={styles.statLabel}>{t('system_admin.backups_stored')}</Text>
            <Text style={styles.statValue}>{count}</Text>
          </View>
          <View style={[styles.stat, styles.statWide, { backgroundColor: tc.card, borderColor: tc.line }]}>
            <Text style={styles.statLabel}>{t('system_admin.backups_latest')}</Text>
            <Text style={styles.statDate} numberOfLines={1}>
              {formatDate(lastBackup)}
            </Text>
          </View>
        </View>

        {failed ? (
          <View style={styles.empty}>
            <Ionicons name="cloud-offline-outline" size={44} color={tc.muted2} />
            <Text style={[styles.emptyText, { color: tc.muted }]}>
              {t('system_admin.backups_failed')}
            </Text>
            <TouchableOpacity style={styles.retryBtn} onPress={load}>
              <Text style={[styles.retryText, { color: tc.primary }]}>
                {t('app.retry')}
              </Text>
            </TouchableOpacity>
          </View>
        ) : files.length === 0 ? (
          <View style={styles.empty}>
            <Ionicons name="archive-outline" size={44} color={tc.muted2} />
            <Text style={[styles.emptyText, { color: tc.muted }]}>
              {t('system_admin.backups_empty')}
            </Text>
          </View>
        ) : (
          files.map((file, index) => (
            <View
              key={`${file.filename}-${index}`}
              style={[styles.card, { backgroundColor: tc.card, borderColor: tc.line }]}
            >
              <View style={styles.cardIcon}>
                <Ionicons name="document-text-outline" size={20} color={tc.primary} />
              </View>
              <View style={styles.cardBody}>
                <Text style={[styles.filename, { color: tc.ink }]} numberOfLines={1}>
                  {file.filename}
                </Text>
                <Text style={[styles.cardMeta, { color: tc.muted }]}>
                  {formatDate(file.createdAt)} · {t('system_admin.backups_size')}{' '}
                  {formatBytes(file.fileSize)}
                </Text>
              </View>
              <TouchableOpacity
                style={[styles.downloadBtn, { backgroundColor: tc.tint }]}
                onPress={() => download(index)}
                accessibilityRole="button"
                accessibilityLabel={t('system_admin.backups_download')}
              >
                <Ionicons name="download-outline" size={19} color={tc.primary} />
              </TouchableOpacity>
            </View>
          ))
        )}

        <Text style={[styles.note, { color: tc.muted }]}>
          {t('system_admin.backups_note')}
        </Text>
      </ScrollView>
    </SafeAreaView>
  );
}

const makeStyles = (tc: ThemeColors) =>
  StyleSheet.create({
    container: { flex: 1 },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    header: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 12,
      paddingHorizontal: 16,
      paddingVertical: 14,
      backgroundColor: tc.card,
      borderBottomWidth: 1,
      borderBottomColor: tc.line,
    },
    headerText: { flex: 1 },
    title: { fontSize: 18, fontWeight: '800', color: tc.ink },
    subtitle: { fontSize: 12.5, color: tc.muted, marginTop: 2 },
    refreshBtn: {
      width: 42,
      height: 42,
      borderRadius: 12,
      borderWidth: 1,
      borderColor: tc.line,
      alignItems: 'center',
      justifyContent: 'center',
      backgroundColor: tc.card,
    },
    content: { padding: 16, paddingBottom: 40, gap: 12 },
    induceBtn: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 8,
      backgroundColor: '#4f46e5',
      borderRadius: 14,
      paddingVertical: 14,
    },
    induceBtnBusy: { opacity: 0.7 },
    induceText: { color: '#fff', fontWeight: '800', fontSize: 14.5 },
    statsRow: { flexDirection: 'row', gap: 10 },
    stat: {
      flex: 1,
      borderWidth: 1,
      borderRadius: 14,
      padding: 13,
    },
    statWide: { flex: 1.7 },
    statLabel: {
      fontSize: 10.5,
      fontWeight: '700',
      letterSpacing: 0.4,
      textTransform: 'uppercase',
      color: tc.muted,
    },
    statValue: { fontSize: 22, fontWeight: '800', color: tc.ink, marginTop: 4 },
    statDate: { fontSize: 12.5, fontWeight: '700', color: tc.ink, marginTop: 6 },
    card: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 11,
      borderWidth: 1,
      borderRadius: 14,
      padding: 13,
    },
    cardIcon: {
      width: 38,
      height: 38,
      borderRadius: 11,
      alignItems: 'center',
      justifyContent: 'center',
      backgroundColor: tc.tint,
    },
    cardBody: { flex: 1 },
    filename: { fontSize: 13, fontWeight: '700' },
    cardMeta: { fontSize: 11.5, marginTop: 3 },
    downloadBtn: {
      width: 38,
      height: 38,
      borderRadius: 11,
      alignItems: 'center',
      justifyContent: 'center',
    },
    empty: { alignItems: 'center', paddingVertical: 36, gap: 8 },
    emptyText: { fontSize: 13, textAlign: 'center' },
    retryBtn: { paddingVertical: 8, paddingHorizontal: 14 },
    retryText: { fontSize: 13, fontWeight: '800' },
    note: { fontSize: 11.5, lineHeight: 17, marginTop: 4 },
  });
