import { Tabs } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { View, TouchableOpacity, Text, StyleSheet } from 'react-native';
import { useRouter, usePathname } from 'expo-router';
import TabSwipe from '../../components/tab-swipe';
import { useLang } from '../../context/LanguageContext';

// Tab order — used by the swipe gesture to pick the neighbouring page.
const SYSTEM_ADMIN_TABS = ['/system_admin/dashboard', '/system_admin/notify'];

export default function SystemAdminLayout() {
  const router = useRouter();
  const pathname = usePathname();
  const { t } = useLang();

  // Tabs mbili tu
  const tabs = [
    { id: 'dashboard', label: t('system_admin.dashboard'), icon: 'home', route: 'dashboard' },
    { id: 'notify', label: t('system_admin.notify'), icon: 'notifications', route: 'notify' },
  ];

  return (
    <View style={styles.container}>
      {/* TABS ZA JUU */}
      <View style={styles.topTabs}>
        {tabs.map((tab) => {
          const isActive = pathname === `/system_admin/${tab.route}` || 
                         (tab.route === 'dashboard' && pathname === '/system_admin');
          
          return (
            <TouchableOpacity
              key={tab.id}
              style={[styles.tab, isActive && styles.activeTab]}
              onPress={() => router.push(`/system_admin/${tab.route}` as any)}
            >
              <Ionicons 
                name={tab.icon as any} 
                size={24} 
                color={isActive ? '#3498db' : '#95a5a6'} 
              />
              <Text style={[styles.tabLabel, isActive && styles.activeTabLabel]}>
                {tab.label}
              </Text>
            </TouchableOpacity>
          );
        })}
      </View>

      {/* CONTENT — swipe left/right to move between the pages */}
      <View style={styles.content}>
        <TabSwipe tabs={SYSTEM_ADMIN_TABS}>
          <Tabs
            screenOptions={{
              tabBarStyle: { display: 'none' },
              headerShown: false,
            }}
          >
            <Tabs.Screen name="dashboard" />
            <Tabs.Screen name="notify" />
          </Tabs>
        </TabSwipe>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
  },
  topTabs: {
    flexDirection: 'row',
    backgroundColor: '#fff',
    paddingTop: 50,
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
    height: 100,
  },
  tab: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    borderBottomWidth: 3,
    borderBottomColor: 'transparent',
  },
  activeTab: {
    borderBottomColor: '#3498db',
  },
  tabLabel: {
    marginTop: 6,
    fontSize: 14,
    color: '#95a5a6',
    fontWeight: '500',
  },
  activeTabLabel: {
    color: '#3498db',
    fontWeight: '600',
  },
  content: {
    flex: 1,
  },
});