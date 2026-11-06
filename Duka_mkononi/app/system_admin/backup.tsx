// Duka_mkononi/app/system_admin/backup.tsx
// Mobile twin of the website's /system_admin/backup page.
//
// Both read the same Cloudinary snapshots: the nightly one per date and the
// induced ones per date+time. A backup is stored as a constant schema plus a
// freshly fetched seed, so each row can download either file. Deletion removes
// both files and the database row, exactly like the website.
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Alert,
  RefreshControl,
  SafeAreaView,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import SmartCartLoader from '../../components/SmartCartLoader';
import { API_BASE_URL } from '../../constants/api';
import { isSystemAdminRole } from '../../constants/session';
import { getCache, setCache } from '../../db/cache';
import { LOCALE_TAGS, useLang } from '../../context/LanguageContext';
import { useSession } from '../../context/SessionContext';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';
import { useTheme, ThemeColors } from '../../lib/theme';

interface BackupDay {
  date: string;
  stamp: string;
  kind: string;
  time: string | null;
  label: string;
  folder: string;
  schema_url: string;
  seed_url: string;
  schema_bytes: number;
  seed_bytes: number;
  rows_count: number;
  tables_count: number;
  seconds: number | null;
  status: string;
  created_at: string | null;
}

interface BackupPayload {
  success: boolean;
  days?: BackupDay[];
  total_stored?: number;
  total_bytes?: number;
  matching_bytes?: number;
  latest?: string | null;
  imported?: number;
  cloudinary_warning?: string | null;
  table_warning?: string | null;
  cloudinary_folder?: string;
  schedule_timezone?: string;
  filters?: { date_from?: string | null; date_to?: string | null };
  filtered?: boolean;
}

const human = (bytes: number): string => {
  if (!bytes || bytes <= 0) return '—';
  if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`;
  if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${bytes} B`;
};

const tagFor = (lang: string) => LOCALE_TAGS[lang] || 'sw-TZ';

const dayLabel = (stamp: string, lang: string): string => {
  const date = new Date(`${stamp.slice(0, 10)}T00:00:00`);
  if (Number.isNaN(date.getTime())) return stamp.slice(0, 10) || '—';
  return date.toLocaleDateString(tagFor(lang), {
    weekday: 'short',
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  });
};

const filedLabel = (value: string | null, lang: string): string => {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleString(tagFor(lang), {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'Africa/Dar_es_Salaam',
  });
};

export default function SystemAdminBackupScreen() {
  const { colors: tc } = useTheme();
  const styles = useMemo(() => makeStyles(tc), [tc]);
  const router = useRouter();
  const { t, lang } = useLang();
  const { signOut } = useSession();

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [inducing, setInducing] = useState(false);
  const [downloading, setDownloading] = useState<string | null>(null);
  const [deleting, setDeleting] = useState<string | null>(null);
  const [failed, setFailed] = useState(false);
  const [days, setDays] = useState<BackupDay[]>([]);
  const [totalStored, setTotalStored] = useState(0);
  const [totalBytes, setTotalBytes] = useState(0);
  const [matchingBytes, setMatchingBytes] = useState(0);
  const [latest, setLatest] = useState<string | null>(null);
  const [imported, setImported] = useState(0);
  const [cloudinaryWarning, setCloudinaryWarning] = useState<string | null>(null);
  const [tableWarning, setTableWarning] = useState<string | null>(null);
  const [folder, setFolder] = useState('dm-backups');
  const [scheduleTimezone, setScheduleTimezone] = useState('Africa/Dar_es_Salaam');
  const [filtered, setFiltered] = useState(false);
  const [appliedFrom, setAppliedFrom] = useState('');
  const [appliedTo, setAppliedTo] = useState('');
  const [fromInput, setFromInput] = useState('');
  const [toInput, setToInput] = useState('');

  const cacheKey = useCallback(
    (from: string, to: string) => `system_admin:backups:${from || ''}:${to || ''}`,
    [],
  );

  const applyPayload = useCallback((data: BackupPayload) => {
    setDays(Array.isArray(data.days) ? data.days : []);
    setTotalStored(Number(data.total_stored) || 0);
    setTotalBytes(Number(data.total_bytes) || 0);
    setMatchingBytes(Number(data.matching_bytes) || 0);
    setLatest(data.latest ?? null);
    setImported(Number(data.imported) || 0);
    setCloudinaryWarning(data.cloudinary_warning ?? null);
    setTableWarning(data.table_warning ?? null);
    setFolder(data.cloudinary_folder || 'dm-backups');
    setScheduleTimezone(data.schedule_timezone || 'Africa/Dar_es_Salaam');
    setFiltered(Boolean(data.filtered));
    setAppliedFrom(data.filters?.date_from || '');
    setAppliedTo(data.filters?.date_to || '');
  }, []);

  const load = useCallback(
    async (from?: string, to?: string) => {
      const fromValue = (from ?? '').trim();
      const toValue = (to ?? '').trim();

      try {
        const token = await AsyncStorage.getItem('userToken');
        const userDataStr = await AsyncStorage.getItem('userData');
        if (!token || !userDataStr) {
          await signOut();
          router.replace('/(tabs)');
          return;
        }

        const userData = JSON.parse(userDataStr);
        if (!isSystemAdminRole(userData.role)) {
          Alert.alert(t('system_admin.access_denied'), t('system_admin.err_no_permission'));
          router.replace('/(tabs)');
          return;
        }

        const params = new URLSearchParams();
        if (fromValue) params.append('date_from', fromValue);
        if (toValue) params.append('date_to', toValue);
        const query = params.toString();

        const res = await fetchWithTimeout(
          `${API_BASE_URL}/api/system-admin/backups${query ? `?${query}` : ''}`,
          { headers: { Authorization: `Bearer ${token}` } },
          45_000,
        );

        if (res.status === 403) {
          Alert.alert(t('system_admin.access_denied'), t('system_admin.err_no_permission'));
          router.replace('/(tabs)');
          return;
        }
        if (res.status === 404) {
          setDays([]);
          setTotalStored(0);
          setTableWarning(t('system_admin.backups_server_missing'));
          setFailed(false);
          return;
        }
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data: BackupPayload = await res.json();
        if (!data?.success) throw new Error('backups failed');

        applyPayload(data);
        setFailed(false);
        await setCache(cacheKey(fromValue, toValue), data);
      } catch {
        const cached = await getCache<BackupPayload>(cacheKey(fromValue, toValue));
        if (cached?.success) {
          applyPayload(cached);
        }
        setFailed(true);
      } finally {
        setLoading(false);
        setRefreshing(false);
      }
    },
    [applyPayload, cacheKey, router, signOut, t],
  );

  useEffect(() => {
    load();
  }, [load]);

  const applyFilter = () => {
    setRefreshing(true);
    load(fromInput, toInput);
  };

  const resetFilter = () => {
    setFromInput('');
    setToInput('');
    setRefreshing(true);
    load('', '');
  };

  const induce = () => {
    Alert.alert(t('system_admin.backups_title'), t('system_admin.backups_confirm'), [
      { text: t('app.cancel'), style: 'cancel' },
      {
        text: t('system_admin.backups_induce'),
        onPress: async () => {
          if (!(await requireNetwork())) return;
          setInducing(true);
          try {
            const token = await AsyncStorage.getItem('userToken');
            // A full dump can take a while; don't let the default timeout abort it.
            const res = await fetchWithTimeout(
              `${API_BASE_URL}/api/system-admin/backups/induce`,
              {
                method: 'POST',
                headers: {
                  Authorization: `Bearer ${token}`,
                  'Content-Type': 'application/json',
                },
              },
              600_000,
            );
            if (res.status === 404) {
              Alert.alert(t('app.error'), t('system_admin.backups_server_missing'));
              return;
            }
            const text = await res.text();
            let data: { ok?: boolean; message?: string } = {};
            try {
              data = text ? (JSON.parse(text) as { ok?: boolean; message?: string }) : {};
            } catch {
              data = {};
            }
            if (!res.ok || !data.ok) {
              Alert.alert(
                t('app.error'),
                data.message || `${t('system_admin.backups_induce_failed')} (HTTP ${res.status})`,
              );
              return;
            }
            Alert.alert(t('app.success'), data.message || t('system_admin.backups_done'));
            await load(appliedFrom, appliedTo);
          } catch {
            Alert.alert(t('app.error'), t('system_admin.backups_induce_failed'));
          } finally {
            setInducing(false);
          }
        },
      },
    ]);
  };

  const downloadFile = async (stamp: string, file: 'schema' | 'seed') => {
    const key = `${stamp}:${file}`;
    setDownloading(key);
    try {
      const token = await AsyncStorage.getItem('userToken');
      const FileSystem = await import('expo-file-system/legacy');
      const Sharing = await import('expo-sharing');
      const uri = `${FileSystem.cacheDirectory}${file}_${stamp}.sql`;
      const result = await FileSystem.downloadAsync(
        `${API_BASE_URL}/api/system-admin/backups/${stamp}/download/${file}`,
        uri,
        { headers: { Authorization: `Bearer ${token}` } },
      );
      if (result.status !== 200) throw new Error('download failed');
      if (await Sharing.isAvailableAsync()) {
        await Sharing.shareAsync(uri, { mimeType: 'application/sql' });
      } else {
        Alert.alert(t('system_admin.backups_download'), uri);
      }
    } catch {
      Alert.alert(t('app.error'), t('system_admin.backups_download_failed'));
    } finally {
      setDownloading(null);
    }
  };

  const remove = (day: BackupDay) => {
    Alert.alert(
      t('system_admin.backups_delete'),
      t('system_admin.backups_delete_confirm', { stamp: day.stamp }),
      [
        { text: t('app.cancel'), style: 'cancel' },
        {
          text: t('system_admin.backups_delete'),
          style: 'destructive',
          onPress: async () => {
            if (!(await requireNetwork())) return;
            setDeleting(day.stamp);
            try {
              const token = await AsyncStorage.getItem('userToken');
              const res = await fetchWithTimeout(
                `${API_BASE_URL}/api/system-admin/backups/${day.stamp}`,
                { method: 'DELETE', headers: { Authorization: `Bearer ${token}` } },
              );
              const data = await res.json();
              if (!res.ok || !data?.ok) {
                Alert.alert(
                  t('app.error'),
                  data?.message || t('system_admin.backups_delete_failed'),
                );
                return;
              }
              Alert.alert(t('app.success'), data.message || t('system_admin.backups_deleted'));
              await load(appliedFrom, appliedTo);
            } catch {
              Alert.alert(t('app.error'), t('system_admin.backups_delete_failed'));
            } finally {
              setDeleting(null);
            }
          },
        },
      ],
    );
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

  const filteredNotice = filtered
    ? t('system_admin.backups_showing', {
        shown: days.length,
        total: totalStored,
      })
    : null;

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: tc.page }]}>
      <StatusBar barStyle="dark-content" />
      <View style={[styles.header, { backgroundColor: tc.card, borderBottomColor: tc.line }]}>
        <View style={styles.headerText}>
          <Text style={[styles.title, { color: tc.ink }]}>
            {t('system_admin.backups_title')}
          </Text>
          <Text style={[styles.subtitle, { color: tc.muted }]}>
            {t('system_admin.backups_subtitle')}
          </Text>
        </View>
        <View style={styles.headerActions}>
          <TouchableOpacity
            style={[styles.headerBtnPrimary, inducing && styles.induceBtnBusy]}
            onPress={induce}
            disabled={inducing}
            activeOpacity={0.85}
          >
            <Ionicons
              name={inducing ? 'hourglass-outline' : 'flash-outline'}
              size={17}
              color="#fff"
            />
            <Text style={styles.headerBtnPrimaryText}>
              {inducing ? t('system_admin.backups_running') : t('system_admin.backups_induce')}
            </Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={styles.headerBtnGhost}
            onPress={() => {
              setRefreshing(true);
              load(appliedFrom, appliedTo);
            }}
            accessibilityRole="button"
            accessibilityLabel={t('app.refresh')}
          >
            <Ionicons name="refresh" size={17} color={tc.primary} />
            <Text style={[styles.headerBtnGhostText, { color: tc.primary }]}>
              {t('app.refresh')}
            </Text>
          </TouchableOpacity>
        </View>
      </View>

      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => {
              setRefreshing(true);
              load(appliedFrom, appliedTo);
            }}
          />
        }
        showsVerticalScrollIndicator={false}
      >
        {tableWarning ? (
          <View style={[styles.alert, styles.alertError]}>
            <Text style={styles.alertTitle}>{t('system_admin.backups_table_warning')}</Text>
            <Text style={styles.alertText}>{tableWarning}</Text>
            <Text style={styles.alertHint}>{t('system_admin.backups_schema_hint')}</Text>
          </View>
        ) : null}

        {cloudinaryWarning ? (
          <View style={[styles.alert, styles.alertError]}>
            <Text style={styles.alertTitle}>{t('system_admin.backups_cloudinary_warning')}</Text>
            <Text style={styles.alertText}>{cloudinaryWarning}</Text>
            <Text style={styles.alertHint}>
              {t('system_admin.backups_cloudinary_hint')}
            </Text>
          </View>
        ) : null}

        {imported > 0 ? (
          <View style={[styles.alert, styles.alertInfo]}>
            <Text style={styles.alertInfoText}>
              {t('system_admin.backups_imported_notice', { n: imported })}
            </Text>
          </View>
        ) : null}

        <View style={styles.statsRow}>
          <View style={[styles.stat, { backgroundColor: tc.card, borderColor: tc.line }]}>
            <Text style={[styles.statValue, { color: tc.ink }]}>{days.length}</Text>
            <Text style={[styles.statLabel, { color: tc.muted }]}>
              {filtered
                ? t('system_admin.backups_in_range')
                : t('system_admin.backups_stored')}
            </Text>
          </View>
          <View style={[styles.stat, styles.statWide, { backgroundColor: tc.card, borderColor: tc.line }]}>
            <Text style={styles.statDate} numberOfLines={1}>
              {latest ? dayLabel(latest, lang) : '—'}
            </Text>
            <Text style={styles.statLabel}>{t('system_admin.backups_latest')}</Text>
          </View>
          <View style={[styles.stat, { backgroundColor: tc.card, borderColor: tc.line }]}>
            <Text style={styles.statValue}>
              {human(filtered ? matchingBytes : totalBytes)}
            </Text>
            <Text style={styles.statLabel}>{t('system_admin.backups_cloudinary')}</Text>
          </View>
        </View>

        <View style={[styles.filterCard, { backgroundColor: tc.card, borderColor: tc.line }]}>
          <View style={styles.filterRow}>
            <View style={styles.filterField}>
              <Text style={styles.filterLabel}>{t('system_admin.backups_from')}</Text>
              <TextInput
                value={fromInput}
                onChangeText={setFromInput}
                placeholder="YYYY-MM-DD"
                placeholderTextColor={tc.muted2}
                autoCapitalize="none"
                autoCorrect={false}
                style={[styles.input, { color: tc.ink, borderColor: tc.line }]}
              />
            </View>
            <View style={styles.filterField}>
              <Text style={styles.filterLabel}>{t('system_admin.backups_to')}</Text>
              <TextInput
                value={toInput}
                onChangeText={setToInput}
                placeholder="YYYY-MM-DD"
                placeholderTextColor={tc.muted2}
                autoCapitalize="none"
                autoCorrect={false}
                style={[styles.input, { color: tc.ink, borderColor: tc.line }]}
              />
            </View>
          </View>
          <View style={styles.filterActions}>
            <TouchableOpacity style={styles.filterBtn} onPress={applyFilter}>
              <Text style={styles.filterBtnText}>{t('system_admin.backups_filter')}</Text>
            </TouchableOpacity>
            {filtered ? (
              <TouchableOpacity style={styles.resetBtn} onPress={resetFilter}>
                <Text style={[styles.resetText, { color: tc.primary }]}>
                  {t('system_admin.backups_reset')}
                </Text>
              </TouchableOpacity>
            ) : null}
            {filteredNotice ? (
              <Text style={[styles.filterNote, { color: tc.muted }]}>{filteredNotice}</Text>
            ) : null}
          </View>
        </View>

        {failed ? (
          <View style={styles.empty}>
            <Ionicons name="cloud-offline-outline" size={44} color={tc.muted2} />
            <Text style={[styles.emptyText, { color: tc.muted }]}>
              {t('system_admin.backups_failed')}
            </Text>
            <TouchableOpacity
              style={styles.retryBtn}
              onPress={() => {
                setRefreshing(true);
                load(appliedFrom, appliedTo);
              }}
            >
              <Text style={[styles.retryText, { color: tc.primary }]}>{t('app.retry')}</Text>
            </TouchableOpacity>
          </View>
        ) : (
          <View style={[styles.listCard, { backgroundColor: tc.card, borderColor: tc.line }]}>
            <View style={[styles.listHead, { borderBottomColor: tc.line }]}>
              <Text style={[styles.listTitle, { color: tc.ink }]}>
                {t('system_admin.backups_by_date')}
              </Text>
              <Text style={[styles.listHint, { color: tc.muted }]}>
                {`${t('system_admin.backups_folder_nightly')} ${folder}/backup_<date>/ · ${t('system_admin.backups_folder_induced')} ${folder}/induced/backup_<date>_<time>/`}
              </Text>
            </View>

            {days.length === 0 ? (
              <View style={styles.emptyInline}>
                <Text style={[styles.emptyTitle, { color: tc.muted }]}>
                  {filtered
                    ? t('system_admin.backups_empty_filtered')
                    : t('system_admin.backups_empty')}
                </Text>
                {!filtered ? (
                  <Text style={[styles.emptyHint, { color: tc.muted }]}>
                    {t('system_admin.backups_empty_hint', { timezone: scheduleTimezone })}
                  </Text>
                ) : (
                  <TouchableOpacity style={styles.retryBtn} onPress={resetFilter}>
                    <Text style={[styles.retryText, { color: tc.primary }]}>
                      {t('system_admin.backups_reset')}
                    </Text>
                  </TouchableOpacity>
                )}
              </View>
            ) : (
              <ScrollView horizontal showsHorizontalScrollIndicator={false}>
                <View style={styles.table}>
                  <View style={[styles.tableHeadRow, { borderBottomColor: tc.line }]}>
                    <Text style={[styles.th, styles.colDate, { color: tc.muted }]}>
                      {t('system_admin.backups_col_date')}
                    </Text>
                    <Text style={[styles.th, styles.colSchema, { color: tc.muted }]}>
                      {t('system_admin.backups_col_schema')}
                    </Text>
                    <Text style={[styles.th, styles.colSeed, { color: tc.muted }]}>
                      {t('system_admin.backups_col_seed')}
                    </Text>
                    <Text style={[styles.th, styles.colRows, styles.thRight, { color: tc.muted }]}>
                      {t('system_admin.backups_col_rows')}
                    </Text>
                    <Text style={[styles.th, styles.colTook, styles.thRight, { color: tc.muted }]}>
                      {t('system_admin.backups_col_took')}
                    </Text>
                    <Text style={[styles.th, styles.colActions, styles.thRight, { color: tc.muted }]}>
                      {t('system_admin.backups_col_actions')}
                    </Text>
                  </View>

                  {days.map((day) => {
                    const busy = deleting === day.stamp;
                    return (
                      <View
                        key={day.stamp}
                        style={[styles.tableRow, { borderBottomColor: tc.line }]}
                      >
                        <View style={styles.colDate}>
                          <Text style={[styles.dayTitle, { color: tc.ink }]}>
                            {dayLabel(day.stamp, lang)}
                            {day.time ? ` ${day.time}` : ''}
                          </Text>
                          <Text style={[styles.dayFiled, { color: tc.muted }]}>
                            {day.created_at
                              ? `${t('system_admin.backups_filed')} ${filedLabel(day.created_at, lang)} EAT`
                              : t('system_admin.backups_found_cloudinary')}
                          </Text>
                          <View style={styles.badgeRow}>
                            {day.kind === 'induced' ? (
                              <View style={[styles.badge, { backgroundColor: tc.tint }]}>
                                <Text style={[styles.badgeText, { color: tc.primary }]}>
                                  {t('system_admin.backups_induced_badge')}
                                </Text>
                              </View>
                            ) : null}
                            {day.status === 'imported' ? (
                              <View style={[styles.badge, { backgroundColor: '#fef3c7' }]}>
                                <Text style={[styles.badgeText, { color: '#92400e' }]}>
                                  {t('system_admin.backups_imported_badge')}
                                </Text>
                              </View>
                            ) : null}
                          </View>
                        </View>

                        <View style={styles.colSchema}>
                          {day.schema_bytes > 0 ? (
                            <>
                              <Text style={[styles.cellValue, { color: tc.ink }]}>
                                {human(day.schema_bytes)}
                              </Text>
                              {downloading === `${day.stamp}:schema` ? (
                                <SmartCartLoader size="compact" />
                              ) : (
                                <TouchableOpacity
                                  onPress={() => downloadFile(day.stamp, 'schema')}
                                >
                                  <Text style={[styles.linkText, { color: tc.primary }]}>
                                    {t('system_admin.backups_download')}
                                  </Text>
                                </TouchableOpacity>
                              )}
                            </>
                          ) : (
                            <Text style={[styles.cellValue, { color: tc.muted }]}>—</Text>
                          )}
                        </View>

                        <View style={styles.colSeed}>
                          {day.seed_bytes > 0 ? (
                            <>
                              <Text style={[styles.cellValue, { color: tc.ink }]}>
                                {human(day.seed_bytes)} gz
                              </Text>
                              {downloading === `${day.stamp}:seed` ? (
                                <SmartCartLoader size="compact" />
                              ) : (
                                <TouchableOpacity
                                  onPress={() => downloadFile(day.stamp, 'seed')}
                                >
                                  <Text style={[styles.linkText, { color: tc.primary }]}>
                                    {t('system_admin.backups_download')}
                                  </Text>
                                </TouchableOpacity>
                              )}
                            </>
                          ) : (
                            <Text style={[styles.cellValue, { color: tc.muted }]}>—</Text>
                          )}
                        </View>

                        <View style={[styles.colRows, styles.cellRight]}>
                          <Text style={[styles.cellValue, { color: tc.ink }]}>
                            {day.rows_count > 0 ? day.rows_count.toLocaleString() : '—'}
                          </Text>
                          {day.tables_count > 0 ? (
                            <Text style={[styles.metaHint, { color: tc.muted }]}>
                              {t('system_admin.backups_tables_count', { n: day.tables_count })}
                            </Text>
                          ) : null}
                        </View>

                        <View style={[styles.colTook, styles.cellRight]}>
                          <Text style={[styles.cellValue, { color: tc.ink }]}>
                            {day.seconds !== null && day.seconds !== undefined
                              ? `${day.seconds.toFixed(1)}s`
                              : '—'}
                          </Text>
                        </View>

                        <View style={[styles.colActions, styles.actionsCell]}>
                          <TouchableOpacity
                            style={styles.actionBtn}
                            onPress={() => downloadFile(day.stamp, 'seed')}
                            disabled={day.seed_bytes <= 0}
                          >
                            <Ionicons
                              name="download-outline"
                              size={18}
                              color={day.seed_bytes > 0 ? tc.primary : tc.muted2}
                            />
                          </TouchableOpacity>
                          <TouchableOpacity
                            style={styles.actionBtn}
                            onPress={() => remove(day)}
                            disabled={busy}
                          >
                            {busy ? (
                              <SmartCartLoader size="compact" />
                            ) : (
                              <Ionicons name="trash-outline" size={18} color={tc.danger} />
                            )}
                          </TouchableOpacity>
                        </View>
                      </View>
                    );
                  })}
                </View>
              </ScrollView>
            )}

            {days.length > 0 ? (
              <Text style={[styles.restoreNote, { color: tc.muted, borderTopColor: tc.line }]}>
                {t('system_admin.backups_restore_note', { stamp: latest || days[0].stamp })}
              </Text>
            ) : null}
          </View>
        )}
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
      flexWrap: 'wrap',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: 12,
      paddingHorizontal: 16,
      paddingVertical: 14,
      borderBottomWidth: 1,
    },
    headerText: { flexGrow: 1, flexShrink: 1, minWidth: 200 },
    title: { fontSize: 18, fontWeight: '800' },
    subtitle: { fontSize: 12.5, marginTop: 2, lineHeight: 17 },
    headerActions: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
    headerBtnPrimary: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 7,
      backgroundColor: '#4f46e5',
      borderRadius: 10,
      paddingHorizontal: 14,
      paddingVertical: 10,
    },
    headerBtnPrimaryText: { color: '#fff', fontWeight: '800', fontSize: 13 },
    headerBtnGhost: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 6,
      borderWidth: 1,
      borderColor: tc.line,
      borderRadius: 10,
      paddingHorizontal: 12,
      paddingVertical: 9,
      backgroundColor: tc.card,
    },
    headerBtnGhostText: { fontWeight: '800', fontSize: 12.5 },
    content: { padding: 16, paddingBottom: 40, gap: 12 },
    induceBtnBusy: { opacity: 0.7 },
    alert: { borderWidth: 1, borderRadius: 14, padding: 13, gap: 4 },
    alertError: { backgroundColor: '#fef2f2', borderColor: '#fecaca' },
    alertInfo: { backgroundColor: '#eef2ff', borderColor: '#c7d2fe' },
    alertTitle: { fontSize: 12.5, fontWeight: '800', color: '#991b1b' },
    alertText: { fontSize: 12, color: '#991b1b', lineHeight: 17 },
    alertHint: { fontSize: 11, color: '#b91c1c', lineHeight: 16 },
    alertInfoText: { fontSize: 12, color: '#3730a3', lineHeight: 17 },
    statsRow: { flexDirection: 'row', gap: 8 },
    stat: {
      flex: 1,
      borderWidth: 1,
      borderRadius: 14,
      padding: 12,
      justifyContent: 'center',
    },
    statWide: { flex: 1.4 },
    statLabel: {
      fontSize: 10,
      fontWeight: '700',
      letterSpacing: 0.3,
      textTransform: 'uppercase',
      color: tc.muted,
      marginTop: 4,
    },
    statValue: { fontSize: 19, fontWeight: '800', color: tc.ink },
    statDate: { fontSize: 12.5, fontWeight: '800', color: '#047857' },
    filterCard: { borderWidth: 1, borderRadius: 14, padding: 13, gap: 10 },
    filterRow: { flexDirection: 'row', gap: 10 },
    filterField: { flex: 1 },
    filterLabel: {
      fontSize: 10.5,
      fontWeight: '700',
      letterSpacing: 0.6,
      textTransform: 'uppercase',
      color: tc.muted,
      marginBottom: 5,
    },
    input: {
      borderWidth: 1,
      borderRadius: 10,
      paddingHorizontal: 10,
      paddingVertical: 9,
      fontSize: 13,
    },
    filterActions: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 12,
      flexWrap: 'wrap',
    },
    filterBtn: {
      backgroundColor: '#4f46e5',
      borderRadius: 10,
      paddingHorizontal: 16,
      paddingVertical: 9,
    },
    filterBtnText: { color: '#fff', fontWeight: '800', fontSize: 12.5 },
    resetBtn: { paddingVertical: 9, paddingHorizontal: 4 },
    resetText: { fontWeight: '800', fontSize: 12.5 },
    filterNote: { fontSize: 11.5, flex: 1, minWidth: 150 },
    empty: { alignItems: 'center', paddingVertical: 36, gap: 8 },
    emptyText: { fontSize: 13, textAlign: 'center' },
    retryBtn: { paddingVertical: 8, paddingHorizontal: 14 },
    retryText: { fontSize: 13, fontWeight: '800' },
    listCard: { borderWidth: 1, borderRadius: 16, overflow: 'hidden' },
    listHead: {
      paddingHorizontal: 14,
      paddingVertical: 12,
      borderBottomWidth: 1,
      gap: 4,
    },
    listTitle: { fontSize: 13, fontWeight: '800' },
    listHint: { fontSize: 10.5, lineHeight: 15 },
    emptyInline: { paddingHorizontal: 16, paddingVertical: 34, alignItems: 'center', gap: 5 },
    emptyTitle: { fontSize: 13.5, fontWeight: '700', textAlign: 'center' },
    emptyHint: { fontSize: 11.5, lineHeight: 16, textAlign: 'center' },
    table: { minWidth: 700 },
    tableHeadRow: {
      flexDirection: 'row',
      alignItems: 'center',
      paddingHorizontal: 12,
      paddingVertical: 9,
      borderBottomWidth: 1,
    },
    tableRow: {
      flexDirection: 'row',
      alignItems: 'flex-start',
      paddingHorizontal: 12,
      paddingVertical: 12,
      borderBottomWidth: 1,
    },
    th: { fontSize: 9.5, fontWeight: '800', letterSpacing: 0.5, textTransform: 'uppercase' },
    thRight: { textAlign: 'right' },
    cellRight: { alignItems: 'flex-end' },
    cellValue: { fontSize: 12.5, fontWeight: '700' },
    linkText: { fontSize: 11, fontWeight: '800', marginTop: 2 },
    colDate: { width: 190, paddingRight: 10 },
    colSchema: { width: 100, paddingRight: 10 },
    colSeed: { width: 115, paddingRight: 10 },
    colRows: { width: 85, paddingRight: 10 },
    colTook: { width: 70, paddingRight: 10 },
    colActions: { width: 90 },
    actionsCell: { flexDirection: 'row', gap: 6, justifyContent: 'flex-end' },
    dayTitle: { fontSize: 13, fontWeight: '800' },
    badgeRow: { flexDirection: 'row', gap: 5, marginTop: 4 },
    badge: { borderRadius: 6, paddingHorizontal: 6, paddingVertical: 2 },
    badgeText: { fontSize: 9.5, fontWeight: '800', textTransform: 'uppercase' },
    dayFiled: { fontSize: 10.5, marginTop: 2 },
    metaHint: { fontSize: 10.5, marginTop: 1 },
    actionBtn: {
      width: 34,
      height: 34,
      borderRadius: 10,
      alignItems: 'center',
      justifyContent: 'center',
    },
    restoreNote: {
      fontSize: 11,
      lineHeight: 16,
      paddingHorizontal: 14,
      paddingVertical: 11,
      borderTopWidth: 1,
    },
  });
