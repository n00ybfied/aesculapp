import type { CapacitorConfig } from '@capacitor/cli';

const pharmacyBuild = process.env['AESCULAPP_NATIVE_TARGET'] === 'apotheke';

const config: CapacitorConfig = {
  appId: pharmacyBuild ? 'at.stadtapotheke.trofaiach' : 'at.floatbox.aesculapp.dev',
  appName: pharmacyBuild ? 'Stadtapotheke Trofaiach' : 'AesculApp Dev',
  webDir: 'dist/aesculapp/browser',
  android: { path: pharmacyBuild ? 'android-apotheke' : 'android' },
  // HTTP und Cleartext bleiben ausschließlich im lokalen USB-Entwicklungsbuild.
  ...(pharmacyBuild ? {} : { server: { androidScheme: 'http', cleartext: true } }),
  plugins: {
    PushNotifications: {
      presentationOptions: ['alert', 'sound'],
    },
  },
};

export default config;
