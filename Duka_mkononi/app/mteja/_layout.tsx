import { Tabs } from 'expo-router';
import React from 'react';
import { Text } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import TabSwipe from '../../components/tab-swipe';
import { useLang } from '../../context/LanguageContext';

// Tab order — used by the swipe gesture to pick the neighbouring page.
const MTEJA_TABS = ['/mteja/biashara', '/mteja/matangazo', '/mteja/profaili'];

export default function MtejaLayout() {
  const { t } = useLang();
  const insets = useSafeAreaInsets();

  return (
    <TabSwipe tabs={MTEJA_TABS}>
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: '#667eea',
        tabBarStyle: {
          backgroundColor: '#fff',
          borderTopWidth: 1,
          borderTopColor: '#e0e0e0',
          height: 60 + insets.bottom,
          paddingBottom: 10 + insets.bottom,
          paddingTop: 8,
        },
        tabBarLabelStyle: {
          fontSize: 12,
          fontWeight: '500',
        },
      }}>
      <Tabs.Screen
        name="biashara"
        options={() => ({
          title: t('tabs_mteja.business'),
          tabBarIcon: ({ color, focused }) => (
            <Text style={{ color, fontSize: focused ? 22 : 20 }}>
              🏪
            </Text>
          ),
        })}
      />
      <Tabs.Screen
        name="matangazo"
        options={() => ({
          title: t('tabs_mteja.advertisements'),
          tabBarIcon: ({ color, focused }) => (
            <Text style={{ color, fontSize: focused ? 22 : 20 }}>
              📢
            </Text>
          ),
        })}
      />
      <Tabs.Screen
        name="profaili"
        options={() => ({
          title: t('tabs_mteja.profile'),
          tabBarIcon: ({ color, focused }) => (
            <Text style={{ color, fontSize: focused ? 22 : 20 }}>
              👤
            </Text>
          ),
        })}
      />
    </Tabs>
    </TabSwipe>
  );
}