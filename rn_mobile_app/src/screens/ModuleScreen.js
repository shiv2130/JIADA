import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, FlatList, ActivityIndicator, TextInput, TouchableOpacity, Alert, ScrollView, Image } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { launchCamera, launchImageLibrary } from 'react-native-image-picker';
import { API_BASE } from '../config';

/**
 * ModuleScreen now has two tabs: View (API + cache) and Create (delegates to FormScreen)
 * This component focuses on viewing with offline cache; creation handled in FormScreen.
 */

export default function ModuleScreen({ route, navigation }) {
  const { route: apiRoute, token, title, formType } = route.params;
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [tab, setTab] = useState('view');
  const headers = token ? { Authorization: `Bearer ${token}` } : {};

  const load = async () => {
    setLoading(true);
    try {
      const res = await fetch(API_BASE + apiRoute + (search ? `?q=${encodeURIComponent(search)}` : ''), { headers });
      const json = await res.json();
      const data = json.data?.tickets || json.data?.applications || json.data?.dues || json.data?.features || json.data || [];
      setItems(data);
      await AsyncStorage.setItem(cacheKey(), JSON.stringify(data));
    } catch (e) {
      const cached = await AsyncStorage.getItem(cacheKey());
      if (cached) setItems(JSON.parse(cached));
      Alert.alert('Offline', 'Showing cached data');
    } finally { setLoading(false); }
  };

  useEffect(() => { load(); }, []);

  const cacheKey = () => `cache_${apiRoute}`;

  const renderItem = ({ item }) => (
    <View style={styles.card}>
      {Object.keys(item).slice(0, 4).map(k => (
        <Text key={k} style={styles.row}><Text style={styles.label}>{k}:</Text> {String(item[k])}</Text>
      ))}
    </View>
  );

  const goToForm = () => {
    if (!formType) { Alert.alert('Form not available for this module'); return; }
    navigation.navigate('Form', { formType, token });
  };

  const pickImage = () => {
    launchCamera({ mediaType: 'photo', quality: 0.6 }, (res) => {
      if (res.didCancel || res.errorCode) return;
      Alert.alert('Image captured', 'Stored locally (not uploaded in this view).');
    });
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{title}</Text>
      <View style={styles.tabRow}>
        <TouchableOpacity style={[styles.tab, tab==='view'&&styles.tabActive]} onPress={()=>setTab('view')}><Text style={styles.tabText}>View</Text></TouchableOpacity>
        <TouchableOpacity style={[styles.tab, tab==='create'&&styles.tabActive]} onPress={()=>setTab('create')}><Text style={styles.tabText}>Create</Text></TouchableOpacity>
      </View>

      {tab === 'view' && (
        <>
          <View style={styles.searchRow}>
            <TextInput style={styles.input} placeholder="Search..." placeholderTextColor="#8da2c6" value={search} onChangeText={setSearch} />
            <TouchableOpacity style={styles.btn} onPress={load}><Text style={styles.btnText}>Go</Text></TouchableOpacity>
          </View>
          {loading && <ActivityIndicator color="#2c7be5" />}
          {!loading && (
            <FlatList data={items} keyExtractor={(it, idx)=>String(it.id||it.ticket_id||it.application_id||idx)} renderItem={renderItem}/>
          )}
        </>
      )}

      {tab === 'create' && (
        <ScrollView style={{flex:1}}>
          <TouchableOpacity style={styles.bigBtn} onPress={goToForm}><Text style={styles.bigBtnText}>Open {formType || 'Form'}</Text></TouchableOpacity>
          <TouchableOpacity style={styles.secondaryBtn} onPress={pickImage}><Text style={styles.secondaryText}>Capture Photo</Text></TouchableOpacity>
          <Text style={styles.note}>Images captured here are local only; upload via the specific form that supports attachments (utility/grievance/plugplay/transfer/survey) in FormScreen.</Text>
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex:1, padding:12, backgroundColor:'#0b1221'},
  title: { color:'#fff', fontSize:20, fontWeight:'800', marginBottom:8},
  tabRow: { flexDirection:'row', marginBottom:10 },
  tab: { flex:1, padding:10, backgroundColor:'#162033', alignItems:'center', borderRadius:10, marginHorizontal:4 },
  tabActive: { backgroundColor:'#2c7be5' },
  tabText: { color:'#fff', fontWeight:'700' },
  searchRow: { flexDirection:'row', marginBottom:10},
  input: { flex:1, backgroundColor:'#162033', color:'#fff', padding:10, borderRadius:10},
  btn: { marginLeft:8, paddingHorizontal:14, backgroundColor:'#2c7be5', borderRadius:10, justifyContent:'center'},
  btnText: { color:'#fff', fontWeight:'700'},
  card: { backgroundColor:'#162033', padding:12, borderRadius:10, marginVertical:6, borderWidth:1, borderColor:'#1f2a44'},
  row: { color:'#cbd5e1', marginBottom:4},
  label: { fontWeight:'700', color:'#fff'},
  bigBtn: { backgroundColor:'#22c55e', padding:16, borderRadius:12, alignItems:'center', marginVertical:10 },
  bigBtnText: { color:'#fff', fontWeight:'800' },
  secondaryBtn: { backgroundColor:'#1f2a44', padding:12, borderRadius:10, alignItems:'center', marginVertical:6 },
  secondaryText: { color:'#93c5fd', fontWeight:'700' },
  note: { color:'#cbd5e1', marginTop:8, lineHeight:18 }
});
