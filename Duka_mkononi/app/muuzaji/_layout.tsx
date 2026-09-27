import { Tabs } from 'expo-router';
import React from 'react';
import { Text } from 'react-native';
import TabSwipe from '../../components/tab-swipe';
import { useLang } from '../../context/LanguageContext';

// Tab order — used by the swipe gesture to pick the neighbouring page.
const MUUZAJI_TABS = ['/muuzaji/profaili', '/muuzaji/mauzo', '/muuzaji/matumizi', '/muuzaji/uza'];

export default function MuuzajiLayout() {
  const { t } = useLang();

  return (
    <TabSwipe tabs={MUUZAJI_TABS}>
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: '#2ecc71',
      }}>
      <Tabs.Screen
        name="profaili"
        options={() => ({
          title: t('tabs_seller.profile'),
          tabBarIcon: ({ color }) => <Text style={{ color, fontSize: 20 }}>👤</Text>,
        })}
      />
      <Tabs.Screen
        name="mauzo"
        options={() => ({
          title: t('tabs_seller.sales'),
          tabBarIcon: ({ color }) => <Text style={{ color, fontSize: 20 }}>💰</Text>,
        })}
      />
      <Tabs.Screen
        name="matumizi"
        options={() => ({
          title: t('tabs_seller.expenses'),
          tabBarIcon: ({ color }) => <Text style={{ color, fontSize: 20 }}>📊</Text>,
        })}
      />
      <Tabs.Screen
        name="uza"
        options={() => ({
          title: t('tabs_seller.sell'),
          tabBarIcon: ({ color }) => <Text style={{ color, fontSize: 20 }}>🛒</Text>,
        })}
      />
    </Tabs>
    </TabSwipe>
  );
}