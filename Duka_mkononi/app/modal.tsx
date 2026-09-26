import { Link } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';
import { useLang } from '../context/LanguageContext';

export default function ModalScreen() {
  const { t } = useLang();
  return (
    <View style={styles.container}>
      <Text style={styles.title}>{t('modal.title')}</Text>
      <Link href="/" dismissTo style={styles.link}>
        <Text style={styles.linkText}>{t('modal.go_home')}</Text>
      </Link>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 20,
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  link: {
    marginTop: 15,
    paddingVertical: 15,
  },
  linkText: {
    fontSize: 16,
    color: '#0a7ea4',
  },
});
