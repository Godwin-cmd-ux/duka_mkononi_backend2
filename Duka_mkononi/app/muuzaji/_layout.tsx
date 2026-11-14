import { Ionicons } from '@expo/vector-icons';
import { Tabs, useFocusEffect } from 'expo-router';
import React, { useCallback } from 'react';
import { Text } from 'react-native';
import TabSwipe from '../../components/tab-swipe';
import { useFeatures } from '../../context/FeatureContext';
import { useLang } from '../../context/LanguageContext';
import { useTheme } from '../../lib/theme';

// Tab order — used by the swipe gesture to pick the neighbouring page. The
// Fridge entry follows the business's switch, so the gesture never lands on a
// tab that is not rendered.
const BASE_TABS = ['/muuzaji/profaili', '/muuzaji/mauzo', '/muuzaji/matumizi', '/muuzaji/uza'];
const TRAILING_TABS = ['/muuzaji/orders', '/muuzaji/huduma-nyingine'];

export default function MuuzajiLayout() {
  const { t } = useLang();
  const { colors } = useTheme();
  const { isEnabled, refresh } = useFeatures();
  const fridgeEnabled = isEnabled('fridge');
  const productManagementEnabled = isEnabled('product_management');

  // Re-read the business switches whenever the seller area gains focus, so a
  // change made on the admin Settings screen (or on the web) is reflected
  // without waiting for the next cold launch.
  useFocusEffect(useCallback(() => { refresh(); }, [refresh]));
  const tabs = [...BASE_TABS];
  if (productManagementEnabled) tabs.push('/muuzaji/bidhaa-mpya');
  if (fridgeEnabled) tabs.push('/muuzaji/friji');
  tabs.push(...TRAILING_TABS);

  return (
    <TabSwipe tabs={tabs}>
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: colors.primary,
      }}>
      <Tabs.Screen
        name="profaili"
        options={{
          title: t('tabs_seller.profile'),
          tabBarIcon: ({ color }) => <Text style={{ color, fontSize: 20 }}>👤</Text>,
        }}
      />
      <Tabs.Screen
        name="mauzo"
        options={{
          title: t('tabs_seller.sales'),
          tabBarIcon: ({ color }) => <Text style={{ color, fontSize: 20 }}>💰</Text>,
        }}
      />
      <Tabs.Screen
        name="matumizi"
        options={{
          title: t('tabs_seller.expenses'),
          tabBarIcon: ({ color }) => <Text style={{ color, fontSize: 20 }}>📊</Text>,
        }}
      />
      <Tabs.Screen
        name="uza"
        options={{
          title: t('tabs_seller.sell'),
          tabBarIcon: ({ color }) => <Ionicons name="cart" size={20} color={color} />,
        }}
      />
      {productManagementEnabled ? (
        <Tabs.Screen
          name="bidhaa-mpya"
          options={{
            title: t('tabs.new_product'),
            tabBarIcon: ({ color }) => <Ionicons name="add-circle" size={20} color={color} />,
          }}
        />
      ) : (
        <Tabs.Screen name="bidhaa-mpya" options={{ href: null }} />
      )}
      {fridgeEnabled ? (
        <Tabs.Screen
          name="friji"
          options={{
            title: t('tabs_seller.fridge'),
            tabBarIcon: ({ color }) => <Ionicons name="snow" size={20} color={color} />,
          }}
        />
      ) : (
        <Tabs.Screen name="friji" options={{ href: null }} />
      )}
      <Tabs.Screen
        name="orders"
        options={{
          title: t('tabs_seller.orders'),
          tabBarIcon: ({ color }) => <Text style={{ color, fontSize: 20 }}>📦</Text>,
        }}
      />
      <Tabs.Screen
        name="huduma-nyingine"
        options={{
          title: t('tabs_seller.other'),
          tabBarIcon: ({ color }) => <Text style={{ color, fontSize: 20 }}>🧩</Text>,
        }}
      />
      {/* The AI capabilities are opened from the "Other Services" hub,
          so they stay reachable as routes but are hidden from the tab bar. */}
      <Tabs.Screen name="ushauri-bei" options={{ href: null }} />
      <Tabs.Screen name="mpango-stock" options={{ href: null }} />
      <Tabs.Screen name="uliza-biashara" options={{ href: null }} />
      <Tabs.Screen name="msaidizi" options={{ href: null }} />
      <Tabs.Screen name="afya-biashara" options={{ href: null }} />
      <Tabs.Screen name="tangaza" options={{ href: null }} />
      {/* Uza Huduma is opened from the Uza page. */}
      <Tabs.Screen name="uza-huduma" options={{ href: null }} />
    </Tabs>
    </TabSwipe>
  );
}