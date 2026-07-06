# Tenants — Configuración white-label

Cada SOFOM tiene un archivo `<slug>.tenant.ts` que parametriza el build
nativo con su identidad (bundle ID, nombre, iconos, splash, push).

> La URL del backend (`VITE_API_URL`) y la del WebSocket (`VITE_REVERB_*`)
> **no se declaran aquí**: son infraestructura compartida en la arquitectura
> B (un solo backend `apifind.lendus.app` que distingue tenants por header
> `X-Tenant-ID`). Esos valores se leen de `frontend/.env*` durante el build.
> Ver [`../.env.production.example`](../.env.production.example).

## Estructura

```
tenants/
├── _types.ts                # Tipo TenantConfig
├── _template.tenant.ts      # Plantilla — copiar y renombrar
├── README.md                # Esto
├── demo.tenant.ts           # Tenant de prueba
└── <slug>/
    ├── icon.png             # 1024×1024
    ├── splash.png           # 2732×2732
    ├── splash-dark.png      # opcional
    ├── google-services.json # opcional (FCM Android)
    └── GoogleService-Info.plist  # opcional (Firebase iOS, si aplica)
```

## Agregar un nuevo tenant

1. **Copia la plantilla**:
   ```bash
   cp tenants/_template.tenant.ts tenants/acme.tenant.ts
   ```

2. **Llena los campos** (`appId`, `appName`, `theme.primary`, `assets`, `landingComponent`, `auth.methods`, `push`, `deepLinkHost`).

3. **Coloca los assets** en `tenants/acme/`:
   - `icon.png` — PNG cuadrado 1024×1024
   - `splash.png` — PNG cuadrado 2732×2732 (centrado, con respiración)

4. **Construye**:
   ```bash
   npm run tenant:build -- acme        # build web parametrizado
   npm run tenant:ios -- acme          # build + sync + abre Xcode
   npm run tenant:android -- acme      # build + sync + abre Android Studio
   ```

5. **Primera vez** (si aún no existen `ios/` y `android/`):
   ```bash
   TENANT=acme npx cap add ios
   TENANT=acme npx cap add android
   ```

   Estos proyectos se versionan en el repo. Los archivos sensibles
   (`google-services.json`, `Info.plist` con permisos) se sobreescriben
   en cada build.

## Push notifications

Cuando configures push (Fase 6):

1. **FCM (Android)** — descarga `google-services.json` de Firebase Console y
   ponlo en `tenants/<slug>/`. El build lo copia a `android/app/`.

2. **APNs (iOS)** — agrega los identificadores Apple a `push.apnsTeamId` y
   `push.apnsBundleId`. Las credenciales `.p8` se guardan en
   `TenantApiConfig` del backend bajo `push.apns.p8_key`.

## Convenciones de nombres

- `appId` (bundle ID): `mx.<sofom>.lendus` o `mx.<sofom>.app`
- `deepLinkHost`: `app.<sofom>.mx`
- Slug: kebab-case, ≤ 16 caracteres

## Estrategia de distribución

Cada tenant produce **una app independiente** en App Store y Play Store.
La cuenta Apple Developer/Play Console puede ser:
- LendusFind central (más rápido para piloto) — todas las apps bajo `mx.lendus.*`
- Por SOFOM (cuando lo pidan) — cada uno gestiona la suya

La decisión de cuenta no afecta el código; solo cambia quién firma y sube.

## Onboarding: variantes y pasos custom por tenant

El flujo de onboarding es **config-driven**: la lista de pasos vive en
`products.onboarding_steps` (JSONB en backend, sembrado por tenant) y la recorre
`DynamicOnboardingView`. Cada paso tiene un `type` que el registry
(`src/components/onboarding/stepRegistry.ts`) resuelve a un componente-renderer.
Los pasos son **compartidos**: los tenants difieren por config/branding, no copiando
pantallas. Hay tres niveles de personalización, todos aditivos y sin `if (tenant === …)`:

**1. Reordenar / togglear pasos** — reordena o quita entradas del array
`onboarding_steps` del producto. Sin código.

**2. Variante de diseño de un paso existente** (p.ej. otra pantalla de `personal_data`):
- Crea el componente en `src/components/onboarding/steps/<type>/<Variant>.vue` cumpliendo
  el **contrato de renderer** (props `step`/`modelValue`/`formData?`; emite
  `update:modelValue` y `update:valid`; ver `src/types/v2/onboardingStep.ts`).
- Regístralo como variante del tipo en `stepRegistry.ts` (o vía el registro por tenant, abajo).
- En el producto, marca el paso con `"variant": "<nombre>"`. El registry resuelve
  variante → default.

**3. Paso completamente custom de un tenant** (p.ej. `liquidity_check` de MoneyCapital):
- Crea la pantalla en `tenants/<slug>/onboarding/<Screen>.vue` cumpliendo el mismo contrato.
- Crea `tenants/<slug>/onboarding.register.ts`:
  ```ts
  import { registerTenantSteps } from '@/components/onboarding/stepRegistry'
  registerTenantSteps('<slug>', {
    liquidity_check: { default: () => import('./onboarding/LiquidityCheck.vue') },
    // o una variante de un tipo base:
    // personal_data: { default: ..., variants: { wizard: () => import('./onboarding/PersonalWizard.vue') } },
  })
  ```
  `tenants/registerAll.ts` lo descubre e importa solo (glob eager) — no edites nada más.
- En el producto, referencia el paso: `{ "id": "liquidity", "type": "liquidity_check", "config": { … } }`.

**Validación:** cada renderer es dueño de la suya (emite `update:valid`). Un paso custom
que no emita el contrato cae a un fallback (`stepValidation.ts`: exige un valor no vacío).

El nombre del tenant aparece SOLO en la ruta del archivo + el 1er argumento de
`registerTenantSteps`. El core (registry, runner) nunca conoce tenants.
