import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, FlatList, TouchableOpacity, ActivityIndicator, RefreshControl } from 'react-native';
import { API_BASE } from '../config';

const modules = [
  { key: 'Land', route: '/api/land/plots', desc: 'Search land bank and plots' },
  { key: 'Plug & Play', route: '/api/plugplay/units', desc: 'Units, apply, pay', formType: 'plugplay' },
  { key: 'Grievances', route: '/api/grievances/list', desc: 'File & track grievances', formType: 'grievance' },
  { key: 'Utilities', route: '/api/utilities/tickets', desc: 'Utility tickets', formType: 'utility' },
  { key: 'Payments', route: '/api/payment/dues', desc: 'Dues & payments' },
  { key: 'BRAP', route: '/api/brap/items', desc: 'Compliance tracker' },
  { key: 'Survey', route: '/api/survey/parcels', desc: 'View parcels (GIS)', map: true },
  { key: 'HRMS', route: '/api/hrms/employees', desc: 'Employees & payroll' },
  { key: 'Transfer', route: '/api/lease/transfer', desc: 'Lease transfer', formType: 'transfer' },
  { key: 'Court', route: '/api/court-cases/list', desc: 'Court cases' }
];

export default function DashboardScreen({ navigation, route }) {
  const token = route.params?.token || '';
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => { preload(); }, []);

  const headers = token ? { Authorization: `Bearer ${token}` } : {};

  const preload = async () => {
    setLoading(true);
    try {
      await fetch(API_BASE + '/api/dashboard/summary', { headers });
    } catch (e) { /* ignore */ }
    setLoading(false);
  };

  const openModule = (item) => {
    if (item.map) { navigation.navigate('Map'); return; }
    navigation.navigate('Module', { title: item.key, route: item.route, token });
  };
  const openForm = (item) => {
    if (!item.formType) return;
    navigation.navigate('Form', { formType: item.formType, token });
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Dashboard</Text>
      {loading && <ActivityIndicator color="#2c7be5" style={{ marginVertical: 10 }} />}
      <FlatList
        data={modules}
        keyExtractor={item => item.key}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={preload} />}
        renderItem={({ item }) => (
          <TouchableOpacity style={styles.card} onPress={() => openModule(item)}>
            <Text style={styles.cardTitle}>{item.key}</Text>
            <Text style={styles.cardDesc}>{item.desc}</Text>
            {item.formType && (
              <TouchableOpacity style={styles.secondaryBtn} onPress={() => openForm(item)}>
                <Text style={styles.secondaryText}>New {item.key}</Text>
              </TouchableOpacity>
            )}
          </TouchableOpacity>
        )}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: 16, backgroundColor: '#0b1221' },
  title: { color: '#fff', fontSize: 24, fontWeight: '800', marginBottom: 12 },
  card: { backgroundColor: '#162033', padding: 14, borderRadius: 12, marginBottom: 10, borderWidth: 1, borderColor: '#1f2a44' },
  cardTitle: { color: '#fff', fontSize: 16, fontWeight: '700', marginBottom: 4 },
  cardDesc: { color: '#cbd5e1', fontSize: 13 }
});
