import type { CapacitorConfig } from '@capacitor/cli'

/**
 * Capacitor base config.
 *
 * En Fase 4 este archivo se vuelve dinámico por tenant (lee
 * `process.env.TENANT` y sobreescribe `appId`, `appName`, splash color
 * y push). Por ahora es un placeholder que permite probar localmente con
 * un tenant genérico.
 */
const config: CapacitorConfig = {
  appId: 'mx.lendus.app',
  appName: 'LendusFind',
  webDir: 'dist',
  server: {
    androidScheme: 'https',
    iosScheme: 'https',
  },
  plugins: {
    PushNotifications: {
      presentationOptions: ['badge', 'sound', 'alert'],
    },
    SplashScreen: {
      launchShowDuration: 1500,
      backgroundColor: '#FFFFFF',
      showSpinner: false,
    },
  },
  ios: {
    contentInset: 'always',
    limitsNavigationsToAppBoundDomains: true,
  },
  android: {
    allowMixedContent: false,
  },
}

export default config
