import { Tabs } from 'expo-router';
import SystemAdminShell from '../../components/system-admin-shell';
import TabSwipe from '../../components/tab-swipe';

// Page order used by the swipe gesture; kept in step with the sidebar.
const SYSTEM_ADMIN_TABS = [
  '/system_admin/dashboard',
  '/system_admin/users',
  '/system_admin/businesses',
  '/system_admin/logs',
  '/system_admin/inquiries',
  '/system_admin/certification',
  '/system_admin/analytics',
  '/system_admin/notify',
  '/system_admin/backup',
];

export default function SystemAdminLayout() {
  return (
    <SystemAdminShell>
      <TabSwipe tabs={SYSTEM_ADMIN_TABS}>
        <Tabs
          screenOptions={{
            tabBarStyle: { display: 'none' },
            headerShown: false,
          }}
        >
          <Tabs.Screen name="dashboard" />
          <Tabs.Screen name="users" />
          <Tabs.Screen name="businesses" />
          <Tabs.Screen name="logs" />
          <Tabs.Screen name="inquiries" />
          <Tabs.Screen name="certification" />
          <Tabs.Screen name="analytics" />
          <Tabs.Screen name="notify" />
          <Tabs.Screen name="backup" />
        </Tabs>
      </TabSwipe>
    </SystemAdminShell>
  );
}
