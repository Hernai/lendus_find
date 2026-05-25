import type { CapacitorConfig } from '@capacitor/cli'
import { readFileSync, existsSync } from 'node:fs'
import { join } from 'node:path'

/**
 * Capacitor config.
 *
 * Lee `capacitor.tenant.json` generado por `scripts/build-tenant.mjs`.
 * Si no existe (primer arranque, dev local) usa valores placeholder.
 *
 * Para construir un tenant específico:
 *   TENANT=<slug> npm run tenant:build
 *   (esto genera el JSON antes de invocar cualquier comando `cap`)
 */

interface TenantSnapshot {
  appId: string
  appName: string
  splashColor: string
}

let snapshot: TenantSnapshot = {
  appId: 'mx.lendus.app',
  appName: 'LendusFind',
  splashColor: '#FFFFFF',
}

const snapshotPath = join(process.cwd(), 'capacitor.tenant.json')
if (existsSync(snapshotPath)) {
  try {
    snapshot = { ...snapshot, ...JSON.parse(readFileSync(snapshotPath, 'utf-8')) }
  } catch {
    // Fallback silencioso a placeholders.
  }
}

const config: CapacitorConfig = {
  appId: snapshot.appId,
  appName: snapshot.appName,
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
      backgroundColor: snapshot.splashColor,
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
