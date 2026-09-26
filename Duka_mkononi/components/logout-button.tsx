import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { Alert, StyleSheet, Text, TouchableOpacity, ViewStyle, TextStyle } from 'react-native';
import { useLang } from '../context/LanguageContext';
import { useSession } from '../context/SessionContext';

interface LogoutButtonProps {
  iconOnly?: boolean;
  size?: number;
  color?: string;
  style?: ViewStyle | ViewStyle[];
  textStyle?: TextStyle | TextStyle[];
}

// 🚪 Reusable logout button — signs the user out everywhere and returns home.
// Used on every page of the app so users can always sign out.
export default function LogoutButton({
  iconOnly = false,
  size = 20,
  color = '#e74c3c',
  style,
  textStyle,
}: LogoutButtonProps) {
  const router = useRouter();
  const { t } = useLang();
  const { signOut } = useSession();

  const handleLogout = () => {
    Alert.alert(
      t('profile.logout'),
      t('profile.logout_confirm'),
      [
        { text: t('profile.cancel'), style: 'cancel' },
        {
          text: t('profile.logout'),
          style: 'destructive',
          onPress: async () => {
            try {
              // Wipe the cached session and flip the route guards to logged-out.
              // The router then falls back to the home screen automatically.
              await signOut();
              router.replace('/(tabs)');
            } catch (error) {
              console.error('❌ Logout error:', error);
              Alert.alert(t('app.error'), t('profile.error_update'));
            }
          },
        },
      ]
    );
  };

  return (
    <TouchableOpacity
      onPress={handleLogout}
      style={[styles.button, style]}
      activeOpacity={0.7}
      accessibilityLabel={t('profile.logout')}
    >
      <Ionicons name="log-out-outline" size={size} color={color} />
      {!iconOnly && (
        <Text style={[styles.text, { color }, textStyle]}>{t('profile.logout')}</Text>
      )}
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  button: {
    padding: 8,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
  },
  text: {
    fontSize: 14,
    fontWeight: '600',
    marginLeft: 6,
  },
});
