import React, { useState } from 'react';
import { View, Text, StyleSheet, TextInput, TouchableOpacity, Alert, ScrollView, Image } from 'react-native';
import { API_BASE } from '../config';
import { launchImageLibrary, launchCamera } from 'react-native-image-picker';

export default function FormScreen({ route, navigation }) {
  const { formType, token } = route.params;
  const [form, setForm] = useState({});
  const [loading, setLoading] = useState(false);
  const [photos, setPhotos] = useState([]);
  const headers = {
    'Content-Type': 'application/x-www-form-urlencoded',
    ...(token ? { Authorization: `Bearer ${token}` } : {})
  };

  const fieldsByType = {
    utility: [
      ['utility_type', 'Utility Type (water/electricity/road)', 'text'],
      ['priority', 'Priority (low/medium/high/critical)', 'text'],
      ['subject', 'Subject', 'text'],
      ['description', 'Description', 'text']
    ],
    grievance: [
      ['category', 'Category (service/billing/...)', 'text'],
      ['department', 'Department', 'text'],
      ['subject', 'Subject', 'text'],
      ['description', 'Description', 'text']
    ],
    plugplay: [
      ['unit_id', 'Unit ID', 'number'],
      ['company_name', 'Company Name', 'text'],
      ['industry_type', 'Industry Type', 'text'],
      ['investment_amount', 'Investment Amount', 'number']
    ],
    transfer: [
      ['lease_no', 'Lease No', 'text'],
      ['to_user_name', 'Transferee Name', 'text'],
      ['to_user_mobile', 'Transferee Mobile', 'text'],
      ['to_user_email', 'Transferee Email', 'text'],
      ['transfer_reason', 'Reason', 'text'],
      ['consideration_amount', 'Consideration Amount', 'number'],
      ['is_msme', 'Is MSME? 1/0', 'number']
    ],
    mortgage: [
      ['lease_no', 'Lease No', 'text'],
      ['bank_name', 'Bank Name', 'text'],
      ['bank_branch', 'Bank Branch', 'text'],
      ['loan_amount', 'Loan Amount', 'number'],
      ['loan_tenure', 'Loan Tenure (months)', 'number'],
      ['purpose', 'Purpose', 'text']
    ],
    surrender: [
      ['lease_no', 'Lease No', 'text'],
      ['reason', 'Reason', 'text'],
      ['notice_date', 'Notice Date (YYYY-MM-DD)', 'text']
    ],
    renewal: [
      ['lease_no', 'Lease No', 'text'],
      ['renewal_period', 'Renewal Period (years)', 'number'],
      ['reason', 'Reason', 'text']
    ],
    merge: [
      ['operation', 'Operation (merge/demerge/amalgamate)', 'text'],
      ['primary_lease_no', 'Primary Lease No', 'text'],
      ['secondary_lease_nos', 'Secondary Lease Nos (comma)', 'text'],
      ['plot_ids', 'Plot IDs (comma)', 'text'],
      ['reason', 'Reason', 'text'],
      ['is_msme', 'Is MSME? 1/0', 'number']
    ]
  };

  const endpointByType = {
    utility: '/api/utilities/tickets',
    grievance: '/api/grievances/submit',
    plugplay: '/api/plugplay/apply',
    transfer: '/api/lease/transfer',
    mortgage: '/api/lease/mortgage',
    surrender: '/api/lease/surrender',
    renewal: '/api/renewal/apply',
    merge: '/api/lease/modify'
  };

  const submit = async () => {
    if (!endpointByType[formType]) { Alert.alert('Unsupported form'); return; }
    setLoading(true);
    try {
      // For simplicity, URL-encode fields; attachments not sent except survey; use server-side to handle if needed.
      const body = new URLSearchParams();
      Object.entries(form).forEach(([k,v])=> body.append(k, v ?? ''));
      const res = await fetch(API_BASE + endpointByType[formType], { method:'POST', headers, body: body.toString() });
      const json = await res.json();
      if (!json.success) throw new Error(json.message || 'Submit failed');
      Alert.alert('Success', json.message || 'Submitted');
      navigation.goBack();
    } catch (e) {
      Alert.alert('Error', e.message);
    } finally { setLoading(false); }
  };

  const fields = fieldsByType[formType] || [];

  const pickImage = () => {
    launchImageLibrary({ mediaType:'photo', quality:0.6 }, res=>{
      if (res.didCancel || res.errorCode) return;
      if (res.assets && res.assets.length) setPhotos([...photos, ...res.assets]);
    });
  };
  const captureImage = () => {
    launchCamera({ mediaType:'photo', quality:0.6 }, res=>{
      if (res.didCancel || res.errorCode) return;
      if (res.assets && res.assets.length) setPhotos([...photos, ...res.assets]);
    });
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={{paddingBottom:30}}>
      <Text style={styles.title}>New {formType}</Text>
      {fields.map(([key,label,type])=>(
        <TextInput
          key={key}
          placeholder={label}
          placeholderTextColor="#8da2c6"
          keyboardType={type === 'number' ? 'numeric' : 'default'}
          style={styles.input}
          onChangeText={v => setForm({...form, [key]: v})}
        />
      ))}
      <View style={styles.rowBtns}>
        <TouchableOpacity style={styles.secondaryBtn} onPress={pickImage}><Text style={styles.secondaryText}>Pick Photo</Text></TouchableOpacity>
        <TouchableOpacity style={styles.secondaryBtn} onPress={captureImage}><Text style={styles.secondaryText}>Capture</Text></TouchableOpacity>
      </View>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{marginVertical:6}}>
        {photos.map((p,idx)=>(
          <Image key={idx} source={{ uri:p.uri }} style={{ width:80, height:80, marginRight:8, borderRadius:8 }} />
        ))}
      </ScrollView>
      <TouchableOpacity style={styles.btn} onPress={submit} disabled={loading}>
        <Text style={styles.btnText}>{loading ? 'Submitting...' : 'Submit'}</Text>
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex:1, padding:16, backgroundColor:'#0b1221' },
  title: { color:'#fff', fontSize:20, fontWeight:'800', marginBottom:12 },
  input: { backgroundColor:'#162033', color:'#fff', padding:12, borderRadius:10, marginBottom:10 },
  btn: { backgroundColor:'#22c55e', padding:14, borderRadius:12, alignItems:'center', marginTop:6 },
  btnText: { color:'#fff', fontWeight:'700' },
  rowBtns: { flexDirection:'row', justifyContent:'space-between', marginVertical:6 },
  secondaryBtn: { backgroundColor:'#1f2a44', padding:12, borderRadius:10, alignItems:'center', flex:1, marginHorizontal:4 },
  secondaryText: { color:'#93c5fd', fontWeight:'700' }
});
