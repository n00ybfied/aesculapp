import type { CapacitorConfig } from '@capacitor/cli';

const nativeTarget = process.env['AESCULAPP_NATIVE_TARGET'];
const pharmacyBuild = nativeTarget === 'apotheke';
const iosTestBuild = nativeTarget === 'ios-test';
const onlineBuild = pharmacyBuild || iosTestBuild;

const config: CapacitorConfig = {
  appId: iosTestBuild
    ? 'at.floatbox.aesculapp.sta.test'
    : pharmacyBuild
      ? 'at.stadtapotheke.trofaiach'
      : 'at.floatbox.aesculapp.dev',
  appName: iosTestBuild
    ? 'Stadtapotheke Trofaiach Test'
    : pharmacyBuild
      ? 'Stadtapotheke Trofaiach'
      : 'AesculApp Dev',
  webDir: 'dist/aesculapp/browser',
  android: { path: pharmacyBuild ? 'android-apotheke' : 'android' },
  // HTTP und Cleartext bleiben ausschließlich im lokalen USB-Entwicklungsbuild.
  ...(onlineBuild ? {} : { server: { androidScheme: 'http', cleartext: true } }),
  plugins: {
    PushNotifications: {
      presentationOptions: ['alert', 'sound'],
    },
  },
};

export default config;
