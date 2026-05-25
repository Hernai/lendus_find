---
name: mobile-deploy
description: Runbook para releases móviles white-label per-tenant (PWA + iOS + Android via Capacitor). Usar al agregar un SOFOM nuevo, hacer un release, configurar credenciales push, o debuggear builds nativos.
---

# Mobile Deploy — Runbook per-tenant

## Cuándo aplica

Esta skill cubre el ciclo end-to-end de:
1. Agregar un SOFOM nuevo como tenant white-label.
2. Hacer una release iOS/Android per-tenant.
3. Configurar push notifications (APNs + FCM) por tenant.
4. Debuggear builds nativos.

## Arquitectura resumen

- **Una sola base Vue 3**: `frontend/src/` corre idéntica en web, PWA y native (Capacitor).
- **Capa de plataforma** (`src/platform/`) abstrae storage, camera, push, navigator, etc. Web y native tienen implementaciones separadas; el factory elige en runtime vía `Capacitor.isNativePlatform()`.
- **White-label real**: una app por SOFOM en App Store y Play Store, con bundle ID, nombre, icono, splash y push propios. Configurados via `frontend/tenants/<slug>.tenant.ts`.
- **Backend único** (`/api/v2/*`): identifica tenant por `X-Tenant-ID` (header) o subdomain. Endpoint `GET /api/v2/public/manifest` sirve manifest PWA dinámico.

## Onboarding de un SOFOM nuevo

### 1. Crear su config

```bash
cd frontend/
cp tenants/_template.tenant.ts tenants/<slug>.tenant.ts
mkdir -p tenants/<slug>
```

Editar `<slug>.tenant.ts`. Campos críticos:
- `slug`: kebab-case, mismo valor que `X-Tenant-ID` y backend `tenants.slug`.
- `appId`: bundle ID iOS/applicationId Android. Ej: `mx.acme.lendus`.
- `appName`: nombre comercial (≤30 chars para no truncarse en iOS).
- `apiBaseUrl`: backend público (sin `/api`).
- `reverbHost`, `reverbPort`, `reverbScheme`, `reverbAppKey`: WebSocket.
- `theme.primary`: hex del color de marca.

### 2. Colocar assets

En `tenants/<slug>/`:
- `icon.png` — PNG cuadrado 1024×1024 (transparente o fondo del tenant).
- `splash.png` — PNG cuadrado 2732×2732, contenido centrado con respiración (zona segura ~1200×1200).
- `splash-dark.png` — opcional, para modo oscuro.

### 3. Construir el web del tenant

```bash
npm run tenant:build -- <slug>
```

Esto:
- Carga `tenants/<slug>.tenant.ts`.
- Exporta `VITE_API_URL`, `VITE_REVERB_*` derivadas.
- Ejecuta `npm run build-only` (Vite).
- Copia `icon.png`/`splash.png` a `frontend/assets/`.
- Escribe `capacitor.tenant.json` (efímero, ignorado por git).
- Si existen `ios/` y `android/`: ejecuta `npx cap sync`.

### 4. Primera vez: agregar proyectos nativos

Estos comandos requieren Xcode 15+ (iOS) y Android Studio (Android):

```bash
TENANT=<slug> npx cap add ios
TENANT=<slug> npx cap add android
git add ios/ android/
git commit -m "chore(<slug>): scaffold nativo iOS+Android"
```

Los proyectos `ios/` y `android/` se versionan. Solo se hace una vez por tenant; cambios futuros vienen via `cap sync`.

### 5. Configurar permisos iOS

Editar `ios/App/App/Info.plist`:

```xml
<key>NSCameraUsageDescription</key>
<string>Necesitamos acceso a la cámara para validar tu identidad.</string>
<key>NSPhotoLibraryUsageDescription</key>
<string>Necesitamos acceso a tus fotos para subir comprobantes.</string>
<key>NSMicrophoneUsageDescription</key>
<string>Acceso al micrófono (opcional para grabaciones de validación).</string>
```

Agregar capability "Push Notifications" en Xcode (Signing & Capabilities tab).

### 6. Configurar permisos Android

Verificar en `android/app/src/main/AndroidManifest.xml`:

```xml
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
```

## Configurar Push Notifications

### FCM (Android)

1. Crear proyecto Firebase para el tenant: https://console.firebase.google.com
2. Agregar app Android con el `appId` exacto del tenant.
3. Descargar `google-services.json` → colocar en `frontend/android/app/`.
4. Crear cuenta de servicio: Project Settings → Service Accounts → "Generate new private key" → descargar JSON.
5. En el admin de tenant (LendusFind), crear `TenantApiConfig`:
   - `provider`: `fcm`
   - `service_type`: `push`
   - `extra_config`: `{ "service_account_json": <contenido completo del JSON> }`
   - `is_active`: true

### APNs (iOS)

1. Apple Developer → Keys → Create key con capability "Apple Push Notifications service (APNs)".
2. Descargar el `.p8` (solo se puede una vez).
3. Anotar `Key ID` y `Team ID`.
4. En el admin de tenant, crear `TenantApiConfig`:
   - `provider`: `apns`
   - `service_type`: `push`
   - `extra_config`:
     ```json
     {
       "key_id": "ABCDE12345",
       "team_id": "TEAMID1234",
       "bundle_id": "mx.acme.lendus",
       "p8_key": "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----",
       "environment": "production"
     }
     ```
   - Usar `environment: "sandbox"` para builds Debug/TestFlight.

### Probar push end-to-end

```bash
# 1. Asegurar que la app móvil registra su token
#    (al hacer login, useNotificationPermission.ensureRegistered() lo manda)

# 2. Verificar que llegó a la BD
php artisan tinker --execute="echo App\Models\DeviceToken::active()->count();"

# 3. Enviar test desde Tinker
php artisan tinker
> $svc = app(App\Services\Notifications\PushService::class);
> $svc->sendTo('<tenant_uuid>', App\Models\ApplicantAccount::class, '<user_id>', 'Prueba', 'Hola desde Tinker', []);
```

## Release per-tenant (manual local)

### iOS

```bash
TENANT=<slug> npm run tenant:ios
# abre Xcode con el archivo del tenant
# Xcode → Product → Archive → Distribute App → App Store Connect
```

### Android

```bash
TENANT=<slug> npm run tenant:android
# abre Android Studio
# Build → Generate Signed Bundle/APK → Android App Bundle → Release
```

## Release per-tenant (GitHub Actions)

El workflow `.github/workflows/mobile-build.yml` produce builds firmados.

### Secrets requeridos en GitHub

Settings → Secrets and variables → Actions:

| Secret | Origen |
|---|---|
| `ANDROID_KEYSTORE_BASE64` | `base64 -i release.keystore | pbcopy` |
| `ANDROID_KEYSTORE_PASSWORD` | Contraseña del keystore |
| `ANDROID_KEY_ALIAS` | Alias dentro del keystore |
| `ANDROID_KEY_PASSWORD` | Contraseña de la key |
| `IOS_CERTIFICATE_BASE64` | `base64 -i Certificates.p12 | pbcopy` |
| `IOS_CERTIFICATE_PASSWORD` | Contraseña del .p12 |

### Disparar build

GitHub → Actions → "Mobile builds (per tenant)" → Run workflow
- `tenant`: `demo` | `acme` | `all`
- `platform`: `ios` | `android` | `both`

Los AAB/.xcarchive quedan como artifacts descargables.

## Health-checks

Endpoints útiles para validar deploy:

```bash
# Status DB + Redis
curl https://api.lendus.mx/api/v2/public/health

# Versión cliente vs server
curl -H "X-App-Version: 1.0.0" -H "X-Platform: ios" \
  https://api.lendus.mx/api/v2/public/version

# Manifest PWA del tenant (probar en navegador)
curl -H "X-Tenant-ID: demo" https://api.lendus.mx/api/v2/public/manifest
```

## Forzar update obligatorio

En `backend/config/app.php` (o `.env`):

```bash
MOBILE_IOS_MIN_VERSION=1.2.0    # cualquier app < 1.2.0 verá force_update=true
MOBILE_IOS_LATEST_VERSION=1.3.0
MOBILE_ANDROID_MIN_VERSION=1.2.0
MOBILE_ANDROID_LATEST_VERSION=1.3.0
```

La app móvil consulta `/api/v2/public/version` al arrancar y muestra un bloqueo si `force_update: true`.

## Debugging

### El push no llega
1. Verificar token registrado: `SELECT * FROM device_tokens WHERE owner_id = '<user>' AND revoked_at IS NULL;`
2. Logs Laravel: `tail -f storage/logs/laravel.log | grep -i push`
3. Probar FCM directo: en Firebase Console → Cloud Messaging → Send test message con el token.
4. Si `revoked_at` se llenó solo: el token está inválido (app desinstalada o cambió de cuenta).

### Build iOS falla con "code signing"
- Verificar que el `.p12` esté importado en el keychain (`security find-identity -v -p codesigning`).
- En CI: validar que `IOS_CERTIFICATE_BASE64` no tenga saltos de línea raros.

### Build Android falla con "duplicate class"
- `cd android && ./gradlew clean`
- `npx cap sync android`

### Cámara nativa no abre en iOS
- Verificar `NSCameraUsageDescription` en `Info.plist`.
- Reiniciar app después de aceptar permiso (iOS cachea agresivamente).

### WebSocket no conecta en native
- Confirmar que `reverbHost` apunta a un dominio público (no `localhost` ni IP local).
- Verificar `reverbScheme: 'https'` y `forceTLS: true`.

## Convenciones de versionado mobile

- Backend `api_version`: SemVer. Solo cambia mayor cuando hay breaking change real.
- `MOBILE_IOS_LATEST_VERSION`: SemVer X.Y.Z, sincronizada con el `CFBundleShortVersionString` del último build subido.
- `min_version`: la versión mínima donde TODOS los endpoints que la app llama existen. Si rompes contrato, sube el mínimo.

## Archivos clave

- `frontend/tenants/_types.ts` — schema TenantConfig
- `frontend/tenants/_template.tenant.ts` — plantilla
- `frontend/tenants/<slug>.tenant.ts` — configs reales
- `frontend/scripts/build-tenant.mjs` — orquestador
- `frontend/capacitor.config.ts` — parametrizado por `capacitor.tenant.json`
- `frontend/src/platform/` — adapters web + native
- `backend/app/Services/Notifications/PushService.php` — dispatcher push
- `backend/app/Services/Notifications/Push/{Fcm,Apns}Client.php` — clientes
- `.github/workflows/mobile-build.yml` — CI release per-tenant
