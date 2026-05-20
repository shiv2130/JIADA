# Build Debug APK (React Native CLI 0.73)

## Prerequisites
- Node 18+
- JDK 17
- Android SDK / platform-tools on PATH
- Android device/emulator enabled for debugging

## One-time native project setup
If `android/` is not present:
```bash
npx react-native init temp --version 0.73.6 --skip-install --package com.jiada.app
cp -r temp/android rn_mobile_app/
rm -rf temp
```
(Or init directly in `rn_mobile_app` then restore `App.js`, `index.js`, `package.json` from repo.)

## Configure API endpoint
Edit `src/config.js`:
```js
export const API_BASE = 'http://10.0.2.2/JIADA'; // or your server IP
```

## Install dependencies
```bash
cd rn_mobile_app
npm install
```

## Android permissions (Manifest)
Ensure `android/app/src/main/AndroidManifest.xml` includes:
```xml
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION"/>
<uses-permission android:name="android.permission.CAMERA"/>
<uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE"/>
<uses-permission android:name="android.permission.WRITE_EXTERNAL_STORAGE"/>
```

## Build debug APK
```bash
cd rn_mobile_app/android
./gradlew assembleDebug
```
APK output:
```
android/app/build/outputs/apk/debug/app-debug.apk
```

## Optional: run on emulator/device
```bash
cd rn_mobile_app
npx react-native run-android --variant debug
```

## Common issues
- SDK not found: set ANDROID_HOME / ANDROID_SDK_ROOT and add `platform-tools` to PATH.
- Metro not running: in another shell `npm start`.
- API not reachable: update `API_BASE` (emulator → 10.0.2.2, device → host LAN IP). 
