import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { API_BASE_URL } from '../constants/api';
import { useSession } from '../context/SessionContext';
import { requireNetwork } from '../lib/network';

type Branch = {
  id: string;
  branch_name: string;
  location: string | null;
};

// Branch-business administrators land here after login (and whenever they
// press "Badili Tawi"). The chosen branch is never sent with later API calls:
// POST /api/branch-context returns a new signed token that carries the
// branch, and every endpoint re-verifies that claim server-side.
export default function SelectBranchScreen() {
  const router = useRouter();
  const { signOut } = useSession();
  const [loading, setLoading] = useState(true);
  const [selectingId, setSelectingId] = useState<string | null>(null);
  const [branches, setBranches] = useState<Branch[]>([]);
  const [businessName, setBusinessName] = useState('');
  const [structure, setStructure] = useState('non_branch');
  const [error, setError] = useState('');

  const loadBranches = async () => {
    if (!(await requireNetwork())) {
      setLoading(false);
      setError('Hakuna mtandao. Angalia muunganisho wako.');
      return;
    }

    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        router.replace('/login?role=msimamizi' as any);
        return;
      }

      const response = await fetch(`${API_BASE_URL}/api/branches`, {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/json',
          'ngrok-skip-browser-warning': 'true',
        },
      });
      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || 'Imeshindikana kupata matawi');
      }

      setBusinessName(data.business?.business_name || '');
      setStructure(data.business?.business_structure || 'non_branch');
      setBranches(Array.isArray(data.branches) ? data.branches : []);

      if (data.business?.business_structure !== 'branch' || !data.can_select_branch) {
        // Non-branch businesses and sellers never choose a branch.
        router.replace('/msimamizi' as any);
      }
    } catch (err: any) {
      setError(err?.message || 'Hitilafu ya mtandao');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadBranches();
  }, []);

  const chooseBranch = async (branchId: string | null) => {
    if (!(await requireNetwork())) return;

    setSelectingId(branchId === null ? '__all__' : branchId);
    setError('');

    try {
      const token = await AsyncStorage.getItem('userToken');
      if (!token) {
        router.replace('/login?role=msimamizi' as any);
        return;
      }

      const response = await fetch(`${API_BASE_URL}/api/branch-context`, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'ngrok-skip-browser-warning': 'true',
        },
        body: JSON.stringify({ branch_id: branchId }),
      });
      const data = await response.json();

      if (!response.ok || !data.token) {
        throw new Error(data.error || 'Imeshindikana kuchagua tawi');
      }

      const entries: [string, string][] = [
        ['userToken', data.token],
        ['branchContext', data.context || 'cross_branch'],
        ['branchId', data.branch?.id || ''],
      ];
      await AsyncStorage.multiSet(entries);

      if (data.branch) {
        await AsyncStorage.setItem('branchData', JSON.stringify(data.branch));
      } else {
        await AsyncStorage.removeItem('branchData');
      }

      router.replace('/msimamizi' as any);
    } catch (err: any) {
      setError(err?.message || 'Hitilafu ya mtandao');
      setSelectingId(null);
    }
  };

  const handleLogout = async () => {
    Alert.alert('Toka', 'Unahakika unataka kutoka?', [
      { text: 'Hapana', style: 'cancel' },
      {
        text: 'Ndiyo',
        style: 'destructive',
        onPress: async () => {
          await signOut();
          router.replace('/(tabs)' as any);
        },
      },
    ]);
  };

  return (
    <SafeAreaView style={styles.safe}>
      <ScrollView contentContainerStyle={styles.container}>
        <View style={styles.brandRow}>
          <Text style={styles.brand}>DUKAMKONONI</Text>
          <TouchableOpacity onPress={handleLogout}>
            <Text style={styles.logout}>Toka</Text>
          </TouchableOpacity>
        </View>

        <Text style={styles.title}>Chagua Tawi</Text>
        <Text style={styles.business}>{businessName}</Text>
        <Text style={styles.subtitle}>
          Chagua tawi ili kuona mauzo, bidhaa, matumizi na wauzaji wa tawi hilo. Unaweza
          pia kuona muhtasari wa matawi yote.
        </Text>

        {loading ? (
          <View style={styles.loadingBox}>
            <ActivityIndicator size="large" color="#3498db" />
            <Text style={styles.loadingText}>Inapakia matawi...</Text>
          </View>
        ) : (
          <>
            {branches.length === 0 && structure === 'branch' && (
              <View style={styles.emptyBox}>
                <Text style={styles.emptyText}>
                  Biashara hii haina tawi hai. Wasiliana na msimamizi wa mfumo.
                </Text>
              </View>
            )}

            {branches.map((branch) => {
              const busy = selectingId === branch.id;
              return (
                <TouchableOpacity
                  key={branch.id}
                  style={styles.branchCard}
                  disabled={selectingId !== null}
                  onPress={() => chooseBranch(branch.id)}
                  activeOpacity={0.85}
                >
                  <View style={styles.branchIcon}>
                    <Ionicons name="business-outline" size={20} color="#3498db" />
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.branchName}>{branch.branch_name}</Text>
                    <Text style={styles.branchLocation}>
                      {branch.location || 'Eneo halijachukuliwa'}
                    </Text>
                  </View>
                  {busy ? (
                    <ActivityIndicator size="small" color="#3498db" />
                  ) : (
                    <Ionicons name="chevron-forward" size={18} color="#95a5a6" />
                  )}
                </TouchableOpacity>
              );
            })}

            {structure === 'branch' && (
              <TouchableOpacity
                style={[styles.branchCard, styles.allBranchesCard]}
                disabled={selectingId !== null}
                onPress={() => chooseBranch(null)}
                activeOpacity={0.85}
              >
                <View style={[styles.branchIcon, { backgroundColor: '#2c3e50' }]}>
                  <Ionicons name="stats-chart-outline" size={20} color="#ffffff" />
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={[styles.branchName, { color: '#ffffff' }]}>
                    DASHBOARD YA MATAWI YOTE
                  </Text>
                  <Text style={[styles.branchLocation, { color: '#c8d6e5' }]}>
                    Muhtasari wa biashara nzima na wauzaji wa matawi yote
                  </Text>
                </View>
                {selectingId === '__all__' ? (
                  <ActivityIndicator size="small" color="#ffffff" />
                ) : (
                  <Ionicons name="chevron-forward" size={18} color="#ffffff" />
                )}
              </TouchableOpacity>
            )}
          </>
        )}

        {!!error && (
          <View style={styles.errorBox}>
            <Text style={styles.errorText}>{error}</Text>
            <TouchableOpacity onPress={() => { setLoading(true); setError(''); loadBranches(); }}>
              <Text style={styles.retry}>Jaribu tena</Text>
            </TouchableOpacity>
          </View>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: '#f4f7fb' },
  container: { padding: 20, paddingBottom: 40 },
  brandRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 18 },
  brand: { fontSize: 12, fontWeight: '800', letterSpacing: 2, color: '#3498db' },
  logout: { fontSize: 12, fontWeight: '700', color: '#7a8ba0' },
  title: { fontSize: 24, fontWeight: '800', color: '#2c3e50' },
  business: { fontSize: 15, fontWeight: '700', color: '#34495e', marginTop: 4 },
  subtitle: { fontSize: 13, color: '#7a8ba0', marginTop: 6, marginBottom: 18, lineHeight: 19 },
  loadingBox: { paddingVertical: 40, alignItems: 'center' },
  loadingText: { marginTop: 10, fontSize: 13, color: '#7a8ba0' },
  branchCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    borderRadius: 14,
    padding: 14,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: '#dbe7f3',
    gap: 10,
  },
  branchIcon: {
    width: 40,
    height: 40,
    borderRadius: 10,
    backgroundColor: '#e8f4fd',
    alignItems: 'center',
    justifyContent: 'center',
  },
  branchName: { fontSize: 14, fontWeight: '700', color: '#2c3e50' },
  branchLocation: { fontSize: 12, color: '#7a8ba0', marginTop: 2 },
  allBranchesCard: { backgroundColor: '#2c3e50', borderColor: '#2c3e50', marginTop: 8 },
  emptyBox: { backgroundColor: '#fff4e6', borderRadius: 12, padding: 16, marginBottom: 10 },
  emptyText: { fontSize: 13, color: '#b35c00' },
  errorBox: { backgroundColor: '#fdeaea', borderRadius: 12, padding: 14, marginTop: 12 },
  errorText: { fontSize: 13, color: '#c0392b' },
  retry: { fontSize: 13, fontWeight: '700', color: '#c0392b', marginTop: 8 },
});
