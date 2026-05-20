import React from 'react';
import { View, StyleSheet } from 'react-native';
import WebView from 'react-native-webview';
import { API_BASE } from '../config';

export default function MapScreen() {
  return (
    <View style={styles.container}>
      <WebView source={{ uri: `${API_BASE}/survey` }} startInLoadingState />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex:1 }
});
