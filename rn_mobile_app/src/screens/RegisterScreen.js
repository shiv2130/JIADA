import React, { useState } from 'react';
import { View, Text, StyleSheet, TextInput, TouchableOpacity, Alert } from 'react-native';
import { API_BASE } from '../config';

export default function RegisterScreen({ navigation }) {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [mobile, setMobile] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);

  const submit = async () => {
    if (!name || !email || mobile.length < 8 || password.length < 4) {
      Alert.alert('Please fill all fields correctly'); return;
    }
    setLoading(true);
    try {
      const res = await fetch(`${API_BASE}/api/auth/register`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `name=${encodeURIComponent(name)}&email=${encodeURIComponent(email)}&mobile=${encodeURIComponent(mobile)}&password=${encodeURIComponent(password)}`
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.message || 'Register failed');
      Alert.alert('Registered', 'Please login with OTP.');
      navigation.replace('Login');
    } catch (e) {
      Alert.alert('Error', e.message);
    } finally { setLoading(false); }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Create Account</Text>
      <TextInput placeholder="Full Name" style={styles.input} value={name} onChangeText={setName} />
      <TextInput placeholder="Email" keyboardType="email-address" style={styles.input} value={email} onChangeText={setEmail} />
      <TextInput placeholder="Mobile" keyboardType="phone-pad" style={styles.input} value={mobile} onChangeText={setMobile} />
      <TextInput placeholder="Password" secureTextEntry style={styles.input} value={password} onChangeText={setPassword} />
      <TouchableOpacity style={styles.btn} onPress={submit} disabled={loading}><Text style={styles.btnText}>Sign Up</Text></TouchableOpacity>
      <TouchableOpacity onPress={() => navigation.replace('Login')}><Text style={styles.link}>Back to Login</Text></TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex:1, padding:24, backgroundColor:'#0b1221', justifyContent:'center'},
  title: { color:'#fff', fontSize:24, fontWeight:'800', marginBottom:14 },
  input: { backgroundColor:'#162033', color:'#fff', padding:12, borderRadius:10, marginBottom:10 },
  btn: { backgroundColor:'#22c55e', padding:14, borderRadius:12, alignItems:'center', marginTop:4 },
  btnText: { color:'#fff', fontWeight:'700' },
  link: { color:'#93c5fd', marginTop:12, textAlign:'center' }
});
