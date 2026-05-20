import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import SplashScreen from './src/screens/SplashScreen';
import LoginScreen from './src/screens/LoginScreen';
import RegisterScreen from './src/screens/RegisterScreen';
import DashboardScreen from './src/screens/DashboardScreen';
import ModuleScreen from './src/screens/ModuleScreen';
import MapScreen from './src/screens/MapScreen';
import FormScreen from './src/screens/FormScreen';

const Stack = createNativeStackNavigator();

export default function App() {
  return (
    <NavigationContainer>
      <Stack.Navigator initialRouteName="Splash" screenOptions={{ headerShown: false }}>
        <Stack.Screen name="Splash" component={SplashScreen} />
        <Stack.Screen name="Login" component={LoginScreen} />
        <Stack.Screen name="Register" component={RegisterScreen} />
        <Stack.Screen name="Dashboard" component={DashboardScreen} />
        <Stack.Screen name="Module" component={ModuleScreen} options={{ headerShown: true, title: 'Service' }} />
        <Stack.Screen name="Map" component={MapScreen} options={{ headerShown: true, title: 'Survey Map' }} />
        <Stack.Screen name="Form" component={FormScreen} options={{ headerShown: true, title: 'Submit' }} />
      </Stack.Navigator>
    </NavigationContainer>
  );
}
