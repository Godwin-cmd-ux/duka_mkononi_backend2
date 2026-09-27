import { Ionicons } from '@expo/vector-icons';
import { Tabs } from 'expo-router';
import React from 'react';
import { StyleSheet } from 'react-native';
import TabSwipe from '../../components/tab-swipe';
import { useLang } from '../../context/LanguageContext';

// Tab order — used by the swipe gesture to pick the neighbouring page.
const MSIMAMIZI_TABS = [
  '/msimamizi',
  '/msimamizi/ripoti',
  '/msimamizi/preview',
  '/msimamizi/bidhaa-mpya',
  '/msimamizi/tangaza',
];

export default function MsimamiziLayout() {
  const { t } = useLang();
  
  return (
    <TabSwipe tabs={MSIMAMIZI_TABS}>
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: '#e74c3c',
        tabBarInactiveTintColor: '#95a5a6',
        headerShown: false,
        tabBarStyle: styles.tabBar,
      }}
    >
      <Tabs.Screen
        name="index"
        options={() => ({
          title: t('tabs.home'),
          tabBarIcon: ({ color, size }) => (
            <Ionicons name="home" size={size} color={color} />
          ),
        })}
      />
      <Tabs.Screen
        name="ripoti"
        options={() => ({
          title: t('tabs.reports'),
          tabBarIcon: ({ color, size }) => (
            <Ionicons name="document-text" size={size} color={color} />
          ),
        })}
      />
      <Tabs.Screen
        name="preview"
        options={() => ({
          title: t('tabs.preview'),
          tabBarIcon: ({ color, size }) => (
            <Ionicons name="calendar" size={size} color={color} />
          ),
        })}
      />
      <Tabs.Screen
        name="bidhaa-mpya"
        options={() => ({
          title: t('tabs.new_product'),
          tabBarIcon: ({ color, size }) => (
            <Ionicons name="add-circle" size={size} color={color} />
          ),
        })}
      />
      <Tabs.Screen
        name="tangaza"
        options={() => ({
          title: t('tabs.advertise'),
          tabBarIcon: ({ color, size }) => (
            <Ionicons name="megaphone" size={size} color={color} />
          ),
        })}
      />
    </Tabs>
    </TabSwipe>
  );
}

const styles = StyleSheet.create({
  tabBar: {
    backgroundColor: '#ffffff',
    borderTopWidth: 1,
    borderTopColor: '#ecf0f1',
  },
});