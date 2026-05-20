/**
 * JIADA Mobile App
 * React Native companion app for JIADA Portal
 * 
 * Features:
 * - User authentication (JWT + OTP)
 * - Land bank browsing with GIS
 * - Application status tracking
 * - Payment integration
 * - Utility tickets & grievances
 * - Push notifications
 */

import React, { useState, useEffect } from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createStackNavigator } from '@react-navigation/stack';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Ionicons } from '@expo/vector-icons';

// Import screens
import HomeScreen from './src/screens/HomeScreen';
import LoginScreen from './src/screens/LoginScreen';
import DashboardScreen from './src/screens/DashboardScreen';
import LandBankScreen from './src/screens/LandBankScreen';
import ApplicationScreen from './src/screens/ApplicationScreen';
import PaymentScreen from './src/screens/PaymentScreen';
import TicketScreen from './src/screens/TicketScreen';
import GrievanceScreen from './src/screens/GrievanceScreen';
import NotificationScreen from './src/screens/NotificationScreen';
import ProfileScreen from './src/screens/ProfileScreen';

// API Configuration
const API_BASE_URL = 'http://10.0.2.2/JIADA/api'; // Android emulator (use localhost for iOS)
// const API_BASE_URL = 'https://jiada.in/api'; // Production

const Tab = createBottomTabNavigator();
const Stack = createStackNavigator();

// Auth Context
export const AuthContext = React.createContext();

export default function App() {
  const [isLoading, setIsLoading] = useState(true);
  const [userToken, setUserToken] = useState(null);
  const [userInfo, setUserInfo] = useState(null);

  useEffect(() => {
    // Check if user is already logged in
    const loadToken = async () => {
      try {
        const token = await AsyncStorage.getItem('userToken');
        const user = await AsyncStorage.getItem('userInfo');
        if (token && user) {
          setUserToken(token);
          setUserInfo(JSON.parse(user));
        }
      } catch (error) {
        console.error('Error loading token:', error);
      }
      setIsLoading(false);
    };
    loadToken();
  }, []);

  const authContext = React.useMemo(() => ({
    signIn: async (data) => {
      try {
        await AsyncStorage.setItem('userToken', data.token);
        await AsyncStorage.setItem('userInfo', JSON.stringify(data.user));
        setUserToken(data.token);
        setUserInfo(data.user);
      } catch (error) {
        console.error('Sign in error:', error);
      }
    },
    signOut: async () => {
      try {
        await AsyncStorage.removeItem('userToken');
        await AsyncStorage.removeItem('userInfo');
        setUserToken(null);
        setUserInfo(null);
      } catch (error) {
        console.error('Sign out error:', error);
      }
    }
  }), []);

  if (isLoading) {
    return null; // Or splash screen
  }

  return (
    <AuthContext.Provider value={authContext}>
      <NavigationContainer>
        <Stack.Navigator screenOptions={{ headerShown: false }}>
          {userToken ? (
            <Stack.Screen name="Main" component={MainTabs} />
          ) : (
            <Stack.Screen name="Auth" component={AuthStack} />
          )}
        </Stack.Navigator>
      </NavigationContainer>
    </AuthContext.Provider>
  );
}

// Auth Stack
function AuthStack() {
  return (
    <Stack.Navigator>
      <Stack.Screen name="Login" component={LoginScreen} />
    </Stack.Navigator>
  );
}

// Main Tabs
function MainTabs() {
  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        headerShown: true,
        tabBarActiveTintColor: '#1a5276',
        tabBarInactiveTintColor: 'gray',
        tabBarIcon: ({ focused, color, size }) => {
          let iconName;
          if (route.name === 'Home') iconName = focused ? 'home' : 'home-outline';
          else if (route.name === 'Land Bank') iconName = focused ? 'map' : 'map-outline';
          else if (route.name === 'Applications') iconName = focused ? 'documents' : 'documents-outline';
          else if (route.name === 'Services') iconName = focused ? 'build' : 'build-outline';
          else if (route.name === 'Profile') iconName = focused ? 'person' : 'person-outline';
          return <Ionicons name={iconName} size={size} color={color} />;
        },
      })}
    >
      <Tab.Screen name="Home" component={DashboardScreen} />
      <Tab.Screen name="Land Bank" component={LandBankScreen} />
      <Tab.Screen name="Applications" component={ApplicationScreen} />
      <Tab.Screen name="Services" component={ServicesStack} />
      <Tab.Screen name="Profile" component={ProfileScreen} />
    </Tab.Navigator>
  );
}

// Services Stack
function ServicesStack() {
  return (
    <Stack.Navigator>
      <Stack.Screen name="Services" component={HomeScreen} />
      <Stack.Screen name="Payment" component={PaymentScreen} />
      <Stack.Screen name="Utility Ticket" component={TicketScreen} />
      <Stack.Screen name="Grievance" component={GrievanceScreen} />
      <Stack.Screen name="Notifications" component={NotificationScreen} />
    </Stack.Navigator>
  );
}

// API Helper
export async function apiCall(endpoint, method = 'GET', data = null) {
  const token = await AsyncStorage.getItem('userToken');
  
  const options = {
    method,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  };
  
  if (data && method !== 'GET') {
    options.body = JSON.stringify(data);
  }
  
  try {
    const response = await fetch(`${API_BASE_URL}${endpoint}`, options);
    return await response.json();
  } catch (error) {
    console.error('API Error:', error);
    return { success: false, message: 'Network error' };
  }
}