import React, { useEffect, useState } from 'react';
import { View, Text, ActivityIndicator, StyleSheet, Alert, TouchableOpacity } from 'react-native';
import { PermissionsAndroid, Platform } from 'react-native';

export default function SplashScreen({ navigation }) {
  const [checking, setChecking] = useState(true);

  useEffect(() => {
    checkPermissions();
  }, []);

  const checkPermissions = async () => {
    if (Platform.OS !== 'android') {
      navigation.replace('Login');
      return;
    }
    setChecking(true);
    try {
      const perms = [
        PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION,
        PermissionsAndroid.PERMISSIONS.CAMERA
      ];
      const results = await PermissionsAndroid.requestMultiple(perms);
      const allGranted = perms.every(p => results[p] === PermissionsAndroid.RESULTS.GRANTED);
      if (allGranted) {
        navigation.replace('Login');
      } else {
        Alert.alert('Permissions required', 'Please allow Location and Camera to continue.');
      }
    } catch (e) {
      Alert.alert('Error', e.message);
    } finally {
      setChecking(false);
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.logo}>JIADA Portal</Text>
      <Text style={styles.subtitle}>Single Window | Mobile</Text>
      {checking ? (
        <ActivityIndicator size="large" color="#2c7be5" style={{ marginTop: 20 }} />
      ) : (
        <TouchableOpacity style={styles.btn} onPress={checkPermissions}>
          <Text style={styles.btnText}>Retry Permissions</Text>
        </TouchableOpacity>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: '#0f172a' },
  logo: { fontSize: 28, fontWeight: '800', color: '#fff' },
  subtitle: { color: '#cbd5e1', marginTop: 6 },
  btn: { marginTop: 20, paddingHorizontal: 18, paddingVertical: 12, backgroundColor: '#2c7be5', borderRadius: 10 },
  btnText: { color: '#fff', fontWeight: '700' }
});
