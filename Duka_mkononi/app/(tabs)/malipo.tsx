import { 
  View, 
  Text, 
  StyleSheet, 
  ScrollView, 
  TouchableOpacity, 
  SafeAreaView,
  TextInput,
  Alert
} from 'react-native';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useState } from 'react';
import { useLang } from '../../context/LanguageContext';
import { getCache, setCache } from '../../db/cache';
import { registerLive, syncNow } from '../../lib/syncer';
import { fetchWithTimeout, requireNetwork } from '../../lib/network';


export default function MalipoScreen() {
  const router = useRouter();
  const { t } = useLang();
  const [kiasi, setKiasi] = useState('');
  const [nambariYaSimu, setNambariYaSimu] = useState('');

  const njiaZaMalipo = [
    { id: 1, name: t('mpesa'), icon: 'phone-portrait-outline', rangi: '#00A859' },
    { id: 2, name: t('tigoPesa'), icon: 'flash-outline', rangi: '#FF0000' },
    { id: 3, name: t('airtelMoney'), icon: 'cellular-outline', rangi: '#FF0000' },
    { id: 4, name: t('halotelPesa'), icon: 'cash-outline', rangi: '#800080' },
    { id: 5, name: t('bankCard'), icon: 'card-outline', rangi: '#3498db' },
    { id: 6, name: t('ezyPesa'), icon: 'wallet-outline', rangi: '#FF6B00' },
  ];

  const fanyaMalipo = () => {
    if (!kiasi || !nambariYaSimu) {
      Alert.alert(t('hitilafu'), t('jazaSehemuZote'));
      return;
    }

    Alert.alert(
      t('thibitishaMalipo'),
      t('maelezoYaMalipo', { kiasi, nambariYaSimu }),
      [
        { text: t('ghairi'), style: 'cancel' },
        { 
          text: t('thibitisha'), 
          onPress: async () => {
            if (!(await requireNetwork())) return;
            Alert.alert(t('mafanikio'), t('malipoYamefanikiwa'));
            setTimeout(() => {
              router.back();
            }, 1500);
          }
        }
      ]
    );
  };

  return (
    <SafeAreaView style={styles.safeArea}>
      <ScrollView style={styles.container}>
        <View style={styles.header}>
          <TouchableOpacity 
            style={styles.kituoChaKurudi}
            onPress={() => router.back()}
          >
            <Ionicons name="arrow-back" size={24} color="#2c3e50" />
          </TouchableOpacity>
          <Text style={styles.kichwaChaKichwa}>{t('kichwaChaMalipo')}</Text>
          <View style={styles.kuliaChaKichwa} />
        </View>

        {/* Sehemu ya Kiasi */}
        <View style={styles.sehemu}>
          <Text style={styles.kichwaChaSehemu}>{t('kiasiChaKulipa')}</Text>
          <View style={styles.kundilaKiasi}>
            <Text style={styles.sarafu}>{t('sarafu')}</Text>
            <TextInput
              style={styles.ingizoLaKiasi}
              placeholder={t('ingizoLaKiasi')}
              keyboardType="numeric"
              value={kiasi}
              onChangeText={setKiasi}
              placeholderTextColor="#95a5a6"
            />
          </View>
        </View>

        {/* Sehemu ya Nambari ya Simu */}
        <View style={styles.sehemu}>
          <Text style={styles.kichwaChaSehemu}>{t('nambariYaSimu')}</Text>
          <View style={styles.kundilaSimu}>
            <Text style={styles.msimboWaNchi}>{t('msimboWaNchi')}</Text>
            <TextInput
              style={styles.ingizoLaSimu}
              placeholder={t('ingizoLaSimu')}
              keyboardType="phone-pad"
              value={nambariYaSimu}
              onChangeText={setNambariYaSimu}
              maxLength={9}
              placeholderTextColor="#95a5a6"
            />
          </View>
        </View>

        {/* Sehemu ya Njia za Malipo */}
        <View style={styles.sehemu}>
          <Text style={styles.kichwaChaSehemu}>{t('chaguaNjiaYaMalipo')}</Text>
          <View style={styles.njiaZaMalipo}>
            {njiaZaMalipo.map((njia) => (
              <TouchableOpacity
                key={njia.id}
                style={[styles.njiaYaMalipo, { borderColor: njia.rangi }]}
                onPress={() => Alert.alert(njia.name, `Umechagua ${njia.name}`)}
              >
                <Ionicons 
                  name={njia.icon as any} 
                  size={28} 
                  color={njia.rangi} 
                />
                <Text style={styles.maandishiYaNjiaYaMalipo}>{njia.name}</Text>
              </TouchableOpacity>
            ))}
          </View>
        </View>

        {/* Maelezo ya Malipo */}
        <View style={styles.sandukuLaTaarifa}>
          <Ionicons name="information-circle" size={22} color="#3498db" />
          <View style={styles.maudhuiYaTaarifa}>
            <Text style={styles.kichwaChaTaarifa}>{t('maelezoYaMalipo')}</Text>
            <Text style={styles.maandishiYaTaarifa}>
              {t('maelezoYaMalipo1')}{"\n"}
              {t('maelezoYaMalipo2')}{"\n"}
              {t('maelezoYaMalipo3')}{"\n"}
              {t('maelezoYaMalipo4')}
            </Text>
          </View>
        </View>

        {/* Kitufe cha Kulipa */}
        <TouchableOpacity 
          style={styles.kitufeChaKulipa}
          onPress={fanyaMalipo}
        >
          <Ionicons name="lock-closed" size={22} color="white" />
          <Text style={styles.maandishiYaKitufeChaKulipa}>{t('endeleaKulipa')}</Text>
        </TouchableOpacity>

        {/* Tahadhari */}
        <View style={styles.sandukuLaTahadhari}>
          <Ionicons name="shield-checkmark" size={18} color="#856404" />
          <Text style={styles.maandishiYaTahadhari}>
            {t('taarifaYaUsalama')}
          </Text>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
  container: {
    flex: 1,
    padding: 20,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 30,
    paddingTop: 10,
  },
  kituoChaKurudi: {
    padding: 8,
  },
  kichwaChaKichwa: {
    fontSize: 22,
    fontWeight: 'bold',
    color: '#2c3e50',
  },
  kuliaChaKichwa: {
    width: 40,
  },
  sehemu: {
    marginBottom: 25,
  },
  kichwaChaSehemu: {
    fontSize: 16,
    fontWeight: '600',
    color: '#2c3e50',
    marginBottom: 12,
  },
  kundilaKiasi: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 2,
    borderColor: '#3498db',
    borderRadius: 12,
    paddingHorizontal: 15,
    height: 60,
    backgroundColor: '#f8f9fa',
  },
  sarafu: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#3498db',
    marginRight: 10,
  },
  ingizoLaKiasi: {
    flex: 1,
    fontSize: 28,
    fontWeight: 'bold',
    color: '#2c3e50',
    height: '100%',
  },
  kundilaSimu: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: 12,
    height: 60,
    backgroundColor: '#f8f9fa',
  },
  msimboWaNchi: {
    fontSize: 18,
    fontWeight: '600',
    color: '#2c3e50',
    paddingHorizontal: 15,
    borderRightWidth: 1,
    borderRightColor: '#ddd',
    lineHeight: 60,
  },
  ingizoLaSimu: {
    flex: 1,
    fontSize: 18,
    paddingHorizontal: 15,
    color: '#2c3e50',
    height: '100%',
  },
  njiaZaMalipo: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    gap: 12,
  },
  njiaYaMalipo: {
    width: '48%',
    height: 90,
    borderWidth: 1.5,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 10,
    backgroundColor: '#f8f9fa',
  },
  maandishiYaNjiaYaMalipo: {
    fontSize: 14,
    fontWeight: '600',
    color: '#2c3e50',
    marginTop: 8,
  },
  sandukuLaTaarifa: {
    flexDirection: 'row',
    backgroundColor: '#e8f4fc',
    padding: 15,
    borderRadius: 12,
    marginVertical: 20,
    alignItems: 'flex-start',
  },
  maudhuiYaTaarifa: {
    flex: 1,
    marginLeft: 12,
  },
  kichwaChaTaarifa: {
    fontSize: 16,
    fontWeight: 'bold',
    color: '#2c3e50',
    marginBottom: 5,
  },
  maandishiYaTaarifa: {
    fontSize: 14,
    color: '#5d6d7e',
    lineHeight: 20,
  },
  kitufeChaKulipa: {
    backgroundColor: '#2ecc71',
    height: 60,
    borderRadius: 12,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginVertical: 15,
    shadowColor: '#2ecc71',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 8,
    elevation: 6,
  },
  maandishiYaKitufeChaKulipa: {
    fontSize: 18,
    fontWeight: 'bold',
    color: 'white',
    marginLeft: 10,
    letterSpacing: 0.5,
  },
  sandukuLaTahadhari: {
    flexDirection: 'row',
    backgroundColor: '#fff3cd',
    padding: 15,
    borderRadius: 12,
    borderLeftWidth: 4,
    borderLeftColor: '#ffc107',
    alignItems: 'flex-start',
    marginTop: 10,
  },
  maandishiYaTahadhari: {
    flex: 1,
    fontSize: 14,
    color: '#856404',
    marginLeft: 10,
    lineHeight: 20,
  },
});