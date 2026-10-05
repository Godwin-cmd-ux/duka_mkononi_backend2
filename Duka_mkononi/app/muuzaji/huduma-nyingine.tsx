import { useRouter } from 'expo-router';
import React from 'react';
import {
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLang } from '../../context/LanguageContext';

/**
 * "Other Services" hub (seller mobile screen).
 *
 * Groups the four AI capabilities behind a single tab so the tab bar stays
 * tidy. Each card is a distinct emoji + colour so the four services are easier
 * to tell apart, and routes to its existing dedicated screen.
 */

interface ServiceCard {
  key: string;
  icon: string;
  route: string;
  color: string;
  tint: string;
}

const SERVICES: ServiceCard[] = [
  { key: 'price', icon: '💹', route: '/muuzaji/ushauri-bei', color: '#2980b9', tint: '#eaf4fc' },
  { key: 'restock', icon: '📦', route: '/muuzaji/mpango-stock', color: '#e67e22', tint: '#fdf1e3' },
  { key: 'ask', icon: '💬', route: '/muuzaji/uliza-biashara', color: '#8e44ad', tint: '#f5ecfa' },
  { key: 'health', icon: '🩺', route: '/muuzaji/afya-biashara', color: '#16a085', tint: '#e6f7f3' },
];

export default function HudumaNyingine() {
  const { t } = useLang();
  const router = useRouter();

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.header}>
          <Text style={styles.title}>{t('other_services.title')}</Text>
          <Text style={styles.subtitle}>{t('other_services.subtitle')}</Text>
        </View>

        <View style={styles.grid}>
          {SERVICES.map((service) => (
            <TouchableOpacity
              key={service.key}
              activeOpacity={0.85}
              accessibilityRole="button"
              style={[styles.card, { backgroundColor: service.tint, borderColor: service.color }]}
              onPress={() => router.push(service.route as any)}
            >
              <View style={[styles.iconWrap, { backgroundColor: service.color }]}>
                <Text style={styles.icon}>{service.icon}</Text>
              </View>
              <View style={styles.cardBody}>
                <Text style={[styles.cardTitle, { color: service.color }]}>
                  {t(`other_services.${service.key}_title`)}
                </Text>
                <Text style={styles.cardDesc}>
                  {t(`other_services.${service.key}_desc`)}
                </Text>
              </View>
              <Text style={[styles.chevron, { color: service.color }]}>›</Text>
            </TouchableOpacity>
          ))}
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: '#f8f9fa',
  },
  content: {
    padding: 20,
    paddingBottom: 40,
  },
  header: {
    marginBottom: 22,
  },
  title: {
    fontSize: 24,
    fontWeight: '800',
    color: '#2c3e50',
  },
  subtitle: {
    marginTop: 8,
    fontSize: 14,
    lineHeight: 20,
    color: '#7f8c8d',
  },
  grid: {
    gap: 14,
  },
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    padding: 16,
    borderRadius: 18,
    borderWidth: 1.5,
  },
  iconWrap: {
    width: 52,
    height: 52,
    borderRadius: 15,
    alignItems: 'center',
    justifyContent: 'center',
  },
  icon: {
    fontSize: 26,
  },
  cardBody: {
    flex: 1,
  },
  cardTitle: {
    fontSize: 16,
    fontWeight: '700',
  },
  cardDesc: {
    marginTop: 4,
    fontSize: 13,
    lineHeight: 18,
    color: '#5d6d7e',
  },
  chevron: {
    fontSize: 26,
    fontWeight: '700',
  },
});
