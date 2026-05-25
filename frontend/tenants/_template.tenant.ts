import type { TenantConfig } from './_types'

/**
 * Plantilla para nuevos tenants.
 *
 * Pasos para registrar un SOFOM:
 * 1. Copia este archivo como `<slug>.tenant.ts` (ej. `acme.tenant.ts`).
 * 2. Llena todos los campos.
 * 3. Coloca los assets (icon.png 1024×1024, splash.png 2732×2732) en
 *    `frontend/tenants/<slug>/`.
 * 4. Si usas push: agrega `google-services.json` y/o `GoogleService-Info.plist`
 *    en `frontend/tenants/<slug>/` y referéncialos en `push`.
 * 5. Construye con `npm run tenant:build -- <slug>`.
 */
const config: TenantConfig = {
  slug: 'template',
  appId: 'mx.template.lendus',
  appName: 'Template Créditos',
  apiBaseUrl: 'https://api.lendus.mx',
  reverbHost: 'reverb.lendus.mx',
  reverbPort: 443,
  reverbScheme: 'https',
  reverbAppKey: 'CHANGE_ME',
  assets: {
    icon: 'tenants/template/icon.png',
    splash: 'tenants/template/splash.png',
    splashDark: 'tenants/template/splash-dark.png',
    splashBackgroundColor: '#FFFFFF',
  },
  theme: {
    primary: '#1E40AF',
    statusBar: 'dark',
  },
  push: {
    fcmGoogleServicesPath: 'tenants/template/google-services.json',
    fcmInfoPlistPath: 'tenants/template/GoogleService-Info.plist',
    apnsTeamId: 'XXXXXXXXXX',
    apnsBundleId: 'mx.template.lendus',
  },
  deepLinkHost: 'app.template.mx',
}

export default config
