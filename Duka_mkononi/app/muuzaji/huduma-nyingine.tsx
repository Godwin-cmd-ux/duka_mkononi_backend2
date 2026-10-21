import { useRouter } from 'expo-router';
import React, { useState, useMemo } from 'react';
import {
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import ThemeSelector from '../../components/theme-selector';
import { SafeAreaView } from 'react-native-safe-area-context';
import NlEntry from '../../components/NlEntry';
import OnboardingAssistant from '../../components/OnboardingAssistant';
import { useLang } from '../../context/LanguageContext';
import { useTheme, ThemeColors } from '../../lib/theme';

/**
 * "Other Services" hub (seller mobile screen).
 *
 * Groups the AI capabilities behind a single tab so the tab bar stays
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
  { key: 'copilot', icon: '🧭', route: '/muuzaji/msaidizi', color: '#3f51b5', tint: '#eef0fb' },
  { key: 'price', icon: '💹', route: '/muuzaji/ushauri-bei', color: '#2980b9', tint: '#eaf4fc' },
  { key: 'restock', icon: '📦', route: '/muuzaji/mpango-stock', color: '#e67e22', tint: '#fdf1e3' },
  { key: 'ask', icon: '💬', route: '/muuzaji/uliza-biashara', color: '#8e44ad', tint: '#f5ecfa' },
  { key: 'health', icon: '🩺', route: '/muuzaji/afya-biashara', color: '#16a085', tint: '#e6f7f3' },
  { key: 'ads', icon: '📣', route: '/muuzaji/tangaza', color: '#c0392b', tint: '#fdecea' },
];

export default function HudumaNyingine() {
  const { colors: tc } = useTheme();
  const styles = useMemo(() => makeStyles(tc), [tc]);
  const { t } = useLang();
  const router = useRouter();
  // Capability #19 - getting-started checklist + approved onboarding answers.
  const [guideOpen, setGuideOpen] = useState(false);

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <ThemeSelector />
        <View style={styles.header}>
          <Text style={styles.title}>{t('other_services.title')}</Text>
          <Text style={styles.subtitle}>{t('other_services.subtitle')}</Text>
        </View>

        <View style={styles.grid}>
          {/* Capability #19 - the getting-started guide, always available */}
          <TouchableOpacity
            key="getting-started"
            activeOpacity={0.85}
            accessibilityRole="button"
            style={[styles.card, { backgroundColor: '#eef2ff', borderColor: '#4f46e5' }]}
            onPress={() => setGuideOpen(true)}
          >
            <View style={[styles.iconWrap, { backgroundColor: '#4f46e5' }]}>
              <Text style={styles.icon}>🧭</Text>
            </View>
            <View style={styles.cardBody}>
              <Text style={[styles.cardTitle, { color: '#4f46e5' }]}>
                {t('onboarding_ai.title')}
              </Text>
              <Text style={styles.cardDesc}>{t('onboarding_ai.subtitle')}</Text>
            </View>
            <Text style={[styles.chevron, { color: '#4f46e5' }]}>›</Text>
          </TouchableOpacity>

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

        {/* Capability #25 - one sentence in, an editable preview out. */}
        <NlEntry />
      </ScrollView>

      <OnboardingAssistant visible={guideOpen} onClose={() => setGuideOpen(false)} />
    </SafeAreaView>
  );
}

const makeStyles = (tc: ThemeColors) => StyleSheet.create({
  safe: {
    flex: 1,
    backgroundColor: tc.page,
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
    color: tc.ink,
  },
  subtitle: {
    marginTop: 8,
    fontSize: 14,
    lineHeight: 20,
    color: tc.muted,
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
    color: tc.muted,
  },
  chevron: {
    fontSize: 26,
    fontWeight: '700',
  },
});
