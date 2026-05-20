import React, { useState } from 'react';
import { View, Text, StyleSheet, TextInput, TouchableOpacity, Alert, PermissionsAndroid, Platform } from 'react-native';
import { API_BASE } from '../config';

export default function LoginScreen({ navigation }) {
  const [mobile, setMobile] = useState('');
  const [otpSent, setOtpSent] = useState(false);
  const [otp, setOtp] = useState('');
  const [loading, setLoading] = useState(false);

  async function requestLocation() {
    if (Platform.OS !== 'android') return;
    try {
      const granted = await PermissionsAndroid.request(PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION);
      if (granted !== PermissionsAndroid.RESULTS.GRANTED) {
        Alert.alert('GPS Needed', 'Please allow location for better service experience.');
      }
    } catch (e) {
      console.warn(e);
    }
  }

  const sendOtp = async () => {
    if (mobile.length < 8) { Alert.alert('Enter valid mobile'); return; }
    setLoading(true);
    try {
      const res = await fetch(`${API_BASE}/api/auth/login-otp`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `mobile=${encodeURIComponent(mobile)}`
      });
      const json = await res.json();
      if (!json.success) throw new Error(json.message || 'OTP send failed');
      setOtpSent(true);
      Alert.alert('OTP Sent', 'Check SMS/Email for OTP');
    } catch (e) {
      Alert.alert('Error', e.message);
    } finally { setLoading(false); }
  };

  const verifyOtp = async () => {
    if (!otp) { Alert.alert('Enter OTP'); return; }
    setLoading(true);
    try {
      const res = await fetch(`${API_BASE}/api/auth/verify-otp`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `mobile=${encodeURIComponent(mobile)}&otp=${encodeURIComponent(otp)}`
      });
      const json = await res.json();
      if (!json.success || !json.data?.token) throw new Error(json.message || 'OTP verify failed');
      await requestLocation();
      navigation.replace('Dashboard', { token: json.data.token });
    } catch (e) {
      Alert.alert('Error', e.message);
    } finally { setLoading(false); }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Sign In</Text>
      <Text style={styles.subtitle}>OTP based login</Text>
      <TextInput
        placeholder="Mobile"
        keyboardType="phone-pad"
        style={styles.input}
        value={mobile}
        onChangeText={setMobile}
      />
      {otpSent && (
        <TextInput
          placeholder="Enter OTP"
          keyboardType="numeric"
          style={styles.input}
          value={otp}
          onChangeText={setOtp}
        />
      )}
      <TouchableOpacity style={styles.btn} onPress={otpSent ? verifyOtp : sendOtp} disabled={loading}>
        <Text style={styles.btnText}>{otpSent ? 'Verify OTP' : 'Send OTP'}</Text>
      </TouchableOpacity>
      <TouchableOpacity onPress={() => navigation.replace('Register')}>
        <Text style={styles.link}>New user? Register</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: 24, backgroundColor: '#0b1221', justifyContent: 'center' },
  title: { fontSize: 26, fontWeight: '800', color: '#fff', marginBottom: 6 },
  subtitle: { color: '#cbd5e1', marginBottom: 20 },
  input: { backgroundColor: '#1f2a44', color: '#fff', padding: 12, borderRadius: 10, marginBottom: 12 },
  btn: { backgroundColor: '#2c7be5', padding: 14, borderRadius: 12, alignItems: 'center' },
  btnText: { color: '#fff', fontWeight: '700' }
});
