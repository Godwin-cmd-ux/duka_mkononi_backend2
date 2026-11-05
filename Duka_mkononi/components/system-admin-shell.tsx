import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { usePathname, useRouter } from 'expo-router';
import React, {
  useEffect,
  useMemo,
  useRef,
  useState,
  type ComponentProps,
  type ReactNode,
} from 'react';
import {
  Alert,
  Animated,
  Dimensions,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useLang } from '../context/LanguageContext';
import { useSession } from '../context/SessionContext';
import { useTheme } from '../lib/theme';

type IconName = ComponentProps<typeof Ionicons>['name'];

interface NavItem {
  key: string;
  labelKey: string;
  icon: IconName;
  route: string;
}

/**
 * Nav order mirrors the website's super-admin sidebar
 * (resources/views/system_admin/partials/sidebar.blade.php):
 * Dashboard, Users, Businesses, Logs, Inquiries, Certification, Analytics,
 * Notify, Backups, Home.
 */
const PLATFORM_NAV: NavItem[] = [
  { key: 'dashboard', labelKey: 'system_admin.dashboard', icon: 'grid-outline', route: '/system_admin/dashboard' },
  { key: 'users', labelKey: 'system_admin.nav_users', icon: 'people-outline', route: '/system_admin/users' },
  { key: 'businesses', labelKey: 'system_admin.nav_businesses', icon: 'storefront-outline', route: '/system_admin/businesses' },
  { key: 'logs', labelKey: 'system_admin.nav_logs', icon: 'list-outline', route: '/system_admin/logs' },
  { key: 'inquiries', labelKey: 'inquiry_triage.tab_inquiries', icon: 'mail-unread-outline', route: '/system_admin/inquiries' },
  { key: 'certification', labelKey: 'certification_ai.nav_certification', icon: 'shield-checkmark-outline', route: '/system_admin/certification' },
  { key: 'analytics', labelKey: 'admin_analytics.nav_analytics', icon: 'stats-chart-outline', route: '/system_admin/analytics' },
  { key: 'notify', labelKey: 'system_admin.notify', icon: 'notifications-outline', route: '/system_admin/notify' },
];

const SYSTEM_NAV: NavItem[] = [
  { key: 'backup', labelKey: 'system_admin.nav_backups', icon: 'cloud-download-outline', route: '/system_admin/backup' },
  { key: 'home', labelKey: 'navigation.home', icon: 'home-outline', route: '/msimamizi' },
];

interface StoredUser {
  full_name?: string;
  email?: string;
}

export default function SystemAdminShell({ children }: { children: ReactNode }) {
  const { t } = useLang();
  const { colors } = useTheme();
  const { signOut } = useSession();
  const router = useRouter();
  const pathname = usePathname();
  const insets = useSafeAreaInsets();

  const drawerWidth = useMemo(
    () => Math.min(Dimensions.get('window').width * 0.86, 340),
    [],
  );
  const slide = useRef(new Animated.Value(-drawerWidth)).current;

  const [open, setOpen] = useState(false);
  const [user, setUser] = useState<StoredUser | null>(null);

  useEffect(() => {
    (async () => {
      try {
        const raw = await AsyncStorage.getItem('userData');
        if (raw) setUser(JSON.parse(raw));
      } catch {
        // The sidebar still works without the cached profile.
      }
    })();
  }, []);

  useEffect(() => {
    if (!open) return;
    slide.setValue(-drawerWidth);
    Animated.timing(slide, {
      toValue: 0,
      duration: 220,
      useNativeDriver: true,
    }).start();
  }, [open, drawerWidth, slide]);

  const closeDrawer = () => {
    Animated.timing(slide, {
      toValue: -drawerWidth,
      duration: 180,
      useNativeDriver: true,
    }).start(({ finished }) => {
      if (finished) setOpen(false);
    });
  };

  const go = (route: string) => {
    closeDrawer();
    if (route !== pathname) router.navigate(route as any);
  };

  const confirmLogout = () => {
    closeDrawer();
    Alert.alert(t('system_admin.logout_confirm'), undefined, [
      { text: t('app.cancel'), style: 'cancel' },
      {
        text: t('navigation.logout'),
        style: 'destructive',
        onPress: async () => {
          await signOut();
          router.replace('/(tabs)');
        },
      },
    ]);
  };

  const displayName =
    user?.full_name || user?.email?.split('@')[0] || 'Super Admin';
  const initial = displayName.charAt(0).toUpperCase();

  const isActive = (route: string) =>
    pathname === route || pathname.startsWith(`${route}/`);

  const renderNavItem = (item: NavItem) => {
    const active = isActive(item.route);
    return (
      <TouchableOpacity
        key={item.key}
        onPress={() => go(item.route)}
        activeOpacity={0.8}
        accessibilityRole="button"
        accessibilityState={{ selected: active }}
        style={[
          styles.navItem,
          active && { backgroundColor: colors.tint },
        ]}
      >
        <Ionicons
          name={item.icon}
          size={20}
          color={active ? colors.primary : colors.muted}
        />
        <Text
          style={[
            styles.navLabel,
            { color: active ? colors.primary : colors.ink },
            active && styles.navLabelActive,
          ]}
        >
          {t(item.labelKey)}
        </Text>
        {active ? (
          <View style={[styles.activeDot, { backgroundColor: colors.primary }]} />
        ) : null}
      </TouchableOpacity>
    );
  };

  return (
    <View style={[styles.root, { backgroundColor: colors.page }]}>
      {/* Top bar — the menu button collapses/expands the sidebar. */}
      <View
        style={[
          styles.appBar,
          {
            paddingTop: insets.top,
            backgroundColor: colors.card,
            borderBottomColor: colors.line,
          },
        ]}
      >
        <TouchableOpacity
          onPress={() => setOpen(true)}
          style={styles.menuButton}
          accessibilityRole="button"
          accessibilityLabel={t('system_admin.menu')}
        >
          <Ionicons name="menu" size={25} color={colors.ink} />
        </TouchableOpacity>

        <View style={styles.barBrand}>
          <Text style={[styles.barTitle, { color: colors.ink }]}>
            DukaMkononi
          </Text>
          <Text style={[styles.barSubtitle, { color: colors.muted }]}>
            System Admin
          </Text>
        </View>

        <View style={[styles.topAvatar, { backgroundColor: colors.tint }]}>
          <Text style={[styles.topAvatarText, { color: colors.primary }]}>
            {initial}
          </Text>
        </View>
      </View>

      <View style={styles.content}>{children}</View>

      <Modal
        visible={open}
        transparent
        animationType="none"
        statusBarTranslucent
        onRequestClose={closeDrawer}
      >
        <View style={styles.overlay}>
          <Pressable
            style={styles.backdrop}
            onPress={closeDrawer}
            accessibilityRole="button"
          />

          <Animated.View
            style={[
              styles.drawer,
              {
                width: drawerWidth,
                backgroundColor: colors.card,
                paddingTop: insets.top + 12,
                paddingBottom: insets.bottom + 10,
                transform: [{ translateX: slide }],
              },
            ]}
          >
            <View style={styles.drawerHead}>
              <View style={[styles.brandBadge, { backgroundColor: colors.primary }]}>
                <Text style={styles.brandBadgeText}>D</Text>
              </View>
              <View style={styles.brandText}>
                <Text style={[styles.brandName, { color: colors.ink }]}>
                  DukaMkononi
                </Text>
                <Text style={[styles.brandTagline, { color: colors.muted }]}>
                  System Admin
                </Text>
              </View>
              <TouchableOpacity
                onPress={closeDrawer}
                accessibilityRole="button"
                accessibilityLabel={t('app.close')}
              >
                <Ionicons name="close" size={22} color={colors.muted} />
              </TouchableOpacity>
            </View>

            <ScrollView
              style={styles.navScroll}
              contentContainerStyle={styles.navContent}
              showsVerticalScrollIndicator={false}
            >
              <Text style={[styles.sectionLabel, { color: colors.muted2 }]}>
                Platform
              </Text>
              {PLATFORM_NAV.map(renderNavItem)}

              <Text
                style={[
                  styles.sectionLabel,
                  styles.sectionLabelGap,
                  { color: colors.muted2 },
                ]}
              >
                System
              </Text>
              {SYSTEM_NAV.map(renderNavItem)}
            </ScrollView>

            <View style={[styles.footer, { borderTopColor: colors.line }]}>
              <View style={[styles.avatar, { backgroundColor: colors.tint }]}>
                <Text style={[styles.avatarText, { color: colors.primary }]}>
                  {initial}
                </Text>
              </View>
              <View style={styles.footerText}>
                <Text
                  style={[styles.footerName, { color: colors.ink }]}
                  numberOfLines={1}
                >
                  {displayName}
                </Text>
                <Text style={[styles.footerRole, { color: colors.muted }]}>
                  Super Admin
                </Text>
              </View>
              <TouchableOpacity
                onPress={confirmLogout}
                style={styles.logoutButton}
                accessibilityRole="button"
                accessibilityLabel={t('navigation.logout')}
              >
                <Ionicons name="log-out-outline" size={22} color={colors.danger} />
              </TouchableOpacity>
            </View>
          </Animated.View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
  },
  appBar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 14,
    paddingBottom: 10,
    borderBottomWidth: 1,
    minHeight: 56,
    elevation: 2,
    shadowColor: '#000',
    shadowOpacity: 0.06,
    shadowRadius: 4,
    shadowOffset: { width: 0, height: 2 },
    zIndex: 10,
  },
  menuButton: {
    width: 38,
    height: 38,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  barBrand: {
    flex: 1,
  },
  barTitle: {
    fontSize: 15,
    fontWeight: '800',
  },
  barSubtitle: {
    fontSize: 10.5,
    fontWeight: '600',
    letterSpacing: 0.4,
    textTransform: 'uppercase',
  },
  topAvatar: {
    width: 34,
    height: 34,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
  },
  topAvatarText: {
    fontWeight: '800',
    fontSize: 14,
  },
  content: {
    flex: 1,
  },
  overlay: {
    flex: 1,
    flexDirection: 'row',
    backgroundColor: 'rgba(15, 23, 42, 0.5)',
  },
  backdrop: {
    flex: 1,
  },
  drawer: {
    position: 'absolute',
    left: 0,
    top: 0,
    bottom: 0,
    borderTopRightRadius: 22,
    borderBottomRightRadius: 22,
    overflow: 'hidden',
  },
  drawerHead: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 11,
    paddingHorizontal: 16,
    paddingBottom: 14,
  },
  brandBadge: {
    width: 38,
    height: 38,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  brandBadgeText: {
    color: '#fff',
    fontSize: 18,
    fontWeight: '800',
  },
  brandText: {
    flex: 1,
  },
  brandName: {
    fontSize: 15,
    fontWeight: '800',
  },
  brandTagline: {
    fontSize: 10.5,
    fontWeight: '600',
    letterSpacing: 0.5,
    textTransform: 'uppercase',
  },
  navScroll: {
    flex: 1,
  },
  navContent: {
    paddingHorizontal: 10,
    paddingBottom: 14,
  },
  sectionLabel: {
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 1.1,
    textTransform: 'uppercase',
    paddingHorizontal: 12,
    paddingTop: 8,
    paddingBottom: 7,
  },
  sectionLabelGap: {
    marginTop: 12,
  },
  navItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 11,
    paddingHorizontal: 12,
    borderRadius: 12,
    marginBottom: 2,
  },
  navLabel: {
    flex: 1,
    fontSize: 14,
    fontWeight: '600',
  },
  navLabelActive: {
    fontWeight: '800',
  },
  activeDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
  },
  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderTopWidth: 1,
    paddingHorizontal: 16,
    paddingTop: 12,
  },
  avatar: {
    width: 38,
    height: 38,
    borderRadius: 19,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    fontSize: 15,
    fontWeight: '800',
  },
  footerText: {
    flex: 1,
  },
  footerName: {
    fontSize: 13.5,
    fontWeight: '700',
  },
  footerRole: {
    fontSize: 10.5,
    fontWeight: '700',
    letterSpacing: 0.5,
    textTransform: 'uppercase',
    marginTop: 1,
  },
  logoutButton: {
    width: 38,
    height: 38,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
