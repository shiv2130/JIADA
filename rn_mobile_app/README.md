# JIADA Mobile (React Native CLI)

## Prereqs
- Node 18+, Java JDK 17, Android SDK/Platform-Tools, Android device/emulator.
- React Native CLI environment set up (`npx react-native doctor`).

## Install & Build (Debug APK)
```bash
cd rn_mobile_app
npm install
# generate native folders if not present
npx react-native init temp --version 0.73.6 --skip-install --package com.jiada.app
# copy android/ and ios/ from temp into rn_mobile_app OR run init directly in this folder (will overwrite)
# then:
npx react-native run-android --variant debug
# APK: android/app/build/outputs/apk/debug/app-debug.apk
```
Replace `API_BASE` in `App.js` with your backend (e.g., http://10.0.2.2/JIADA for emulator).

## Features (current)
- Tabs: Home, Applications, Grievances, Utilities, Payments.
- Search bar per tab; REST fetch to portal APIs; card list rendering.

## Next steps
- Add auth (JWT/OTP) and secure storage.
- Add forms (tickets/grievances/payments), push notifications, offline cache, map screen.
