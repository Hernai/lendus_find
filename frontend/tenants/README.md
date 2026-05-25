# Tenants — Configuración white-label

Cada SOFOM tiene un archivo `<slug>.tenant.ts` que parametriza el build
nativo para esa marca (bundle ID, nombre, iconos, splash, push, Reverb).

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

2. **Llena los campos** (`appId`, `appName`, `reverbAppKey`, etc.).

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
