import { Ionicons } from '@expo/vector-icons';
import React from 'react';
import { StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { useLang } from '../context/LanguageContext';
import { PALETTES, THEME_IDS, useTheme, type ThemeId } from '../lib/theme';

/**
 * 🎨 In-app theme selector (mobile twin of the website's paintbrush panel).
 *
 * Five cards — White Professional (default), Royal Champagne Cream, Midnight
 * Royal Gold, Emerald Bazaar and Oceanic Sapphire — with a live-color preview
 * built from each theme's REAL palette values, plus a check mark AND a
 * "Selected" text label so selection never relies on colour alone. Inserted
 * into each role's settings surface.
 */

/** Preview dots per theme — real hexes from PALETTES (kept literal so the
    card always previews BOTH themes regardless of the active one). */
const PREVIEW_DOTS: Record<ThemeId, string[]> = {
  'white-professional': ['#f8fafc', '#ffffff', '#667eea', '#2c3e50', '#e2e8f0'],
  'royal-champagne-cream': ['#f4e8d0', '#fcfbf7', '#8b6f23', '#171717', '#d9d8cf'],
  'midnight-royal-gold': ['#282213', '#101010', '#ff9f1c', '#f8f8f8', '#303030'],
  'emerald-bazaar': ['#dcefe5', '#ffffff', '#0e7a5f', '#0f241d', '#cfdfd7'],
  'oceanic-sapphire': ['#eaf3ff', '#ffffff', '#2563eb', '#102a43', '#d7e3f0'],
};

const THEME_NAME: Record<ThemeId, string> = {
  'white-professional': 'White Professional',
  'royal-champagne-cream': 'Royal Champagne Cream',
  'midnight-royal-gold': 'Midnight Royal Gold',
  'emerald-bazaar': 'Emerald Bazaar',
  'oceanic-sapphire': 'Oceanic Sapphire',
};

const DESC_KEY: Record<ThemeId, string> = {
  'white-professional': 'theme.white_desc',
  'royal-champagne-cream': 'theme.royal_desc',
  'midnight-royal-gold': 'theme.midnight_desc',
  'emerald-bazaar': 'theme.emerald_desc',
  'oceanic-sapphire': 'theme.oceanic_desc',
};

export default function ThemeSelector() {
  const { t } = useLang();
  const { theme, colors, setTheme } = useTheme();

  return (
    <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.line }]}>
      <Text style={[styles.title, { color: colors.ink }]}>{t('theme.title')}</Text>

      {THEME_IDS.map((id) => {
        const selected = theme === id;
        const desc = t(DESC_KEY[id]);
        return (
          <TouchableOpacity
            key={id}
            activeOpacity={0.85}
            onPress={() => setTheme(id)}
            accessibilityRole="radio"
            accessibilityState={{ selected }}
            style={[
              styles.option,
              {
                backgroundColor: selected ? colors.tint : colors.soft,
                borderColor: selected ? colors.primary : colors.line,
              },
            ]}
          >
            <View style={styles.dots}>
              {PREVIEW_DOTS[id].map((hex, i) => (
                <View key={i} style={[styles.dot, { backgroundColor: hex }]} />
              ))}
            </View>

            <View style={styles.body}>
              <Text style={[styles.name, { color: colors.ink }]}>{THEME_NAME[id]}</Text>
              <Text style={[styles.desc, { color: colors.muted }]}>{desc}</Text>
              {selected ? (
                <View style={styles.selectedRow}>
                  <Ionicons name="checkmark-circle" size={15} color={colors.primary} />
                  <Text style={[styles.selectedText, { color: colors.primary }]}>
                    {t('theme.selected')}
                  </Text>
                </View>
              ) : null}
            </View>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: 16,
    borderWidth: 1,
    padding: 14,
    marginBottom: 16,
  },
  title: {
    fontSize: 15,
    fontWeight: '800',
    marginBottom: 10,
  },
  option: {
    flexDirection: 'row',
    gap: 12,
    borderRadius: 14,
    borderWidth: 1.5,
    padding: 12,
    marginBottom: 10,
  },
  dots: {
    flexDirection: 'row',
    gap: 4,
    paddingTop: 3,
  },
  dot: {
    width: 12,
    height: 12,
    borderRadius: 6,
  },
  body: {
    flex: 1,
  },
  name: {
    fontSize: 14.5,
    fontWeight: '800',
  },
  desc: {
    fontSize: 12.5,
    lineHeight: 18,
    marginTop: 2,
  },
  selectedRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    marginTop: 6,
  },
  selectedText: {
    fontSize: 11.5,
    fontWeight: '800',
    letterSpacing: 0.4,
    textTransform: 'uppercase',
  },
});
