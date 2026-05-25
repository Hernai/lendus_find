import type { TenantConfig } from './_types'

/**
 * Tenant DEMO — usado para validar el pipeline white-label end-to-end.
 *
 * Los assets bajo `frontend/tenants/demo/` son placeholders. Reemplázalos
 * con los reales del tenant cuando se publique. El `reverbAppKey` debe
 * coincidir con el `.env` del backend para que el WebSocket funcione.
 */
const config: TenantConfig = {
  slug: 'demo',
  appId: 'mx.lendus.demo',
  appName: 'Lendus Demo',
  // 10.0.2.2 es la IP especial del emulador Android para llegar al host Mac.
  // En iOS simulator usa localhost (acepta también 127.0.0.1).
  // Para device físico cambia a la IP LAN de tu Mac o a un túnel ngrok HTTPS.
  apiBaseUrl: 'http://10.0.2.2:8000',
  reverbHost: '10.0.2.2',
  reverbPort: 8080,
  reverbScheme: 'http',
  reverbAppKey: 'local',
  assets: {
    icon: 'tenants/demo/icon.png',
    splash: 'tenants/demo/splash.png',
    splashBackgroundColor: '#1E40AF',
  },
  theme: {
    primary: '#1E40AF',
    statusBar: 'light',
  },
  // push se configurará en Fase 6 cuando haya credenciales reales.
  deepLinkHost: 'demo.lendus.mx',
}

export default config
