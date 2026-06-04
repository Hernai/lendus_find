# Despliegue del Frontend

El frontend de **LendusFind** es **un solo proyecto Vue 3 + Vite** que se construye una vez y se distribuye en tres targets:

| Target | Cómo se sirve | Audiencia |
|--------|---------------|-----------|
| **Web** (backoffice + portal applicant + landings) | Nginx sirve `dist/` estático | Analistas, supervisores, admins, solicitantes desde navegador |
| **Android** | `.aab` firmado en Play Store, una app por SOFOM | Solicitantes en celular |
| **iOS** | `.ipa` firmado en App Store, una app por SOFOM | Solicitantes en celular |

> **Concepto white-label**: cada SOFOM tiene su propia config en `frontend/tenants/<slug>.tenant.ts` que define identidad (`appId`, `appName`), branding (theme, assets, landing), métodos de auth y push keys. **La URL del backend (`VITE_API_URL`) y la del WebSocket (`VITE_REVERB_*`) NO van en el tenant config** — son infraestructura compartida y se leen de `.env`/`.env.production`. El build acepta `TENANT=<slug>` para producir un artifact específico.

---

## Requisitos

| Herramienta | Versión | Notas |
|-------------|---------|-------|
| **Node.js** | 20.19+ o 22.12+ | Solo para *buildear* — no se necesita en el servidor que sirve los estáticos |
| **npm** | 10+ | |
| **Xcode** | 15+ | Solo para iOS (macOS) |
| **Android Studio** | Hedgehog+ | Solo para Android |
| **Capacitor CLI** | viene en `devDependencies` | `npx cap` |

```bash
cd frontend
npm install
```

---

## Web (backoffice + portal + landings)

El build de Vite produce HTML + JS + CSS estáticos en `frontend/dist/`. Nginx los sirve directamente; no hay Node.js en el servidor de producción.

### 1. Configurar `.env` del build

```env
# frontend/.env.production (no commiteado — ver .env.production.example)
VITE_API_URL=https://apifind.lendus.app/api
VITE_APP_URL=https://lendus.app
# NO setear VITE_TENANT_ID en prod: el subdominio (moneycapital.lendus.app)
# lo resuelve automáticamente con detectTenantSlug().

VITE_REVERB_HOST=apifind.lendus.app
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
VITE_REVERB_APP_KEY=<tomar de backend/.env REVERB_APP_KEY>
```

> Estos valores son los mismos para **todos los tenants**: con arquitectura B
> hay un único backend (`apifind.lendus.app`) que distingue tenants por el
> header `X-Tenant-ID` que el interceptor agrega a cada request.

### 2. Build

```bash
cd frontend

# Build multi-tenant (sirve todos los tenants desde el mismo bundle)
npm run build

# Build per-tenant (white-label con bundle dedicado)
npm run tenant:build -- <slug>     # ej: npm run tenant:build -- moneycapital
```

El bundle queda en `frontend/dist/`.

### 3. Publicar en el servidor

Copia `dist/` al servidor web (mismo AlmaLinux 9 del backend o uno dedicado):

```bash
rsync -avz --delete dist/ usuario@servidor:/var/www/lendusfind-web/
```

### 4. Server block de Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name app.tudominio.mx;

    root /var/www/lendusfind-web;
    index index.html;

    # SPA fallback: todas las rutas resuelven a index.html
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Cache largo para assets versionados (Vite les pone hash)
    location /assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # SSL (Certbot lo agrega automáticamente)
    ssl_certificate     /etc/letsencrypt/live/app.tudominio.mx/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/app.tudominio.mx/privkey.pem;
}

server {
    listen 80;
    server_name app.tudominio.mx;
    return 301 https://$host$request_uri;
}
```

```bash
sudo certbot --nginx -d app.tudominio.mx
sudo nginx -t && sudo systemctl reload nginx
```

### 5. Multi-tenant por subdominio

Para SOFOM con dominio propio (ej. `app.moneycapital.mx`), basta con apuntar el subdominio al mismo servidor y duplicar el `server_name`. El frontend resuelve el tenant por hostname y el backend por `X-Tenant-ID` que el frontend envía.

```nginx
server_name app.tudominio.mx app.moneycapital.mx app.sofom2.mx;
```

### 6. PWA (instalable como app)

El backend sirve el manifest dinámico por tenant en `GET /api/v2/public/manifest` (header `X-Tenant-ID`). No hay configuración adicional del lado del frontend web — el service worker se registra automáticamente.

---

## Android

Build per-tenant via Capacitor. Cada SOFOM tiene su propia app en Play Store con bundle ID, icono y push propios.

### Pre-requisitos

- Android Studio (Hedgehog o superior)
- JDK 17+
- Keystore para firma (uno por tenant o uno compartido — depende del esquema)

### Primera vez para un tenant

```bash
cd frontend

# 1. Construir el bundle web del tenant
TENANT=<slug> npm run tenant:build -- <slug>

# 2. Agregar la plataforma Android (solo la primera vez)
TENANT=<slug> npx cap add android

# 3. Commit del proyecto Android
git add android/
git commit -m "chore(<slug>): scaffold android"
```

Permisos requeridos en `android/app/src/main/AndroidManifest.xml`:

```xml
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.POST_NOTIFICATIONS" />
```

### Release (manual local)

```bash
cd frontend
TENANT=<slug> npm run tenant:android
# Abre Android Studio
# Build → Generate Signed Bundle/APK → Android App Bundle → Release
# El .aab firmado queda en android/app/release/
```

Sube el `.aab` a Google Play Console → Production → Release.

### Release (CI con GitHub Actions)

Workflow: `.github/workflows/mobile-build.yml`

Secrets necesarios:

| Secret | Cómo se genera |
|--------|----------------|
| `ANDROID_KEYSTORE_BASE64` | `base64 -i release.keystore \| pbcopy` |
| `ANDROID_KEYSTORE_PASSWORD` | Contraseña del keystore |
| `ANDROID_KEY_ALIAS` | Alias dentro del keystore |
| `ANDROID_KEY_PASSWORD` | Contraseña de la key |

Disparo: GitHub → Actions → "Mobile builds (per tenant)" → Run workflow → `tenant=<slug>` `platform=android`. El `.aab` queda como artifact descargable.

---

## iOS

Build per-tenant via Capacitor. Una app por SOFOM en App Store.

### Pre-requisitos

- macOS con **Xcode 15+**
- Cuenta Apple Developer (paid)
- Certificado de distribución `.p12`
- Provisioning profile App Store para el `appId` del tenant

### Primera vez para un tenant

```bash
cd frontend

# 1. Construir el bundle web del tenant
TENANT=<slug> npm run tenant:build -- <slug>

# 2. Agregar la plataforma iOS (solo la primera vez)
TENANT=<slug> npx cap add ios

# 3. Commit del proyecto iOS
git add ios/
git commit -m "chore(<slug>): scaffold ios"
```

Permisos requeridos en `ios/App/App/Info.plist`:

```xml
<key>NSCameraUsageDescription</key>
<string>Necesitamos acceso a la cámara para validar tu identidad.</string>
<key>NSPhotoLibraryUsageDescription</key>
<string>Necesitamos acceso a tus fotos para subir comprobantes.</string>
<key>NSMicrophoneUsageDescription</key>
<string>Acceso al micrófono (opcional para grabaciones de validación).</string>
```

En Xcode → **Signing & Capabilities**, agregar capability **Push Notifications**.

### Release (manual local)

```bash
cd frontend
TENANT=<slug> npm run tenant:ios
# Abre Xcode
# Product → Archive → Distribute App → App Store Connect
```

### Release (CI con GitHub Actions)

Secrets necesarios:

| Secret | Cómo se genera |
|--------|----------------|
| `IOS_CERTIFICATE_BASE64` | `base64 -i Certificates.p12 \| pbcopy` |
| `IOS_CERTIFICATE_PASSWORD` | Contraseña del .p12 |

Disparo: GitHub → Actions → "Mobile builds (per tenant)" → `platform=ios`. El `.xcarchive` queda como artifact.

---

## Push Notifications (FCM + APNs)

Las credenciales push **no van en el código** — se guardan por tenant en `TenantApiConfig` (tabla del backend) y `PushService` resuelve el cliente correcto al momento de enviar.

### FCM (Android)

1. Firebase Console → crear proyecto del tenant.
2. Agregar app Android con el `appId` exacto del tenant.
3. Descargar `google-services.json` → colocar en `frontend/android/app/`.
4. Project Settings → Service Accounts → **Generate new private key** → descargar JSON.
5. En el backoffice del tenant, crear `TenantApiConfig`:
   ```json
   {
     "provider": "fcm",
     "service_type": "push",
     "extra_config": { "service_account_json": "<contenido del JSON>" },
     "is_active": true
   }
   ```

### APNs (iOS)

1. Apple Developer → **Keys** → crear key con capability "Apple Push Notifications service (APNs)".
2. Descargar el `.p8` (solo se puede una vez). Anotar **Key ID** y **Team ID**.
3. En el backoffice del tenant, crear `TenantApiConfig`:
   ```json
   {
     "provider": "apns",
     "service_type": "push",
     "extra_config": {
       "key_id": "ABCDE12345",
       "team_id": "TEAMID1234",
       "bundle_id": "mx.acme.lendus",
       "p8_key": "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----",
       "environment": "production"
     },
     "is_active": true
   }
   ```
   Usar `environment: "sandbox"` para builds Debug / TestFlight.

### Probar push end-to-end

```bash
# Backend
php artisan tinker
> $svc = app(App\Services\Notifications\PushService::class);
> $svc->sendTo('<tenant_uuid>', App\Models\ApplicantAccount::class, '<user_id>', 'Prueba', 'Hola', []);
```

---

## Forzar update obligatorio de la app móvil

En `backend/.env`:

```env
MOBILE_IOS_MIN_VERSION=1.2.0       # apps < 1.2.0 verán force_update=true
MOBILE_IOS_LATEST_VERSION=1.3.0
MOBILE_ANDROID_MIN_VERSION=1.2.0
MOBILE_ANDROID_LATEST_VERSION=1.3.0
```

La app consulta `/api/v2/public/version` al arrancar y bloquea si `force_update: true`.

---

## Health-checks

```bash
# Status backend
curl https://api.tudominio.mx/api/v2/public/health

# Versión cliente vs server
curl -H "X-App-Version: 1.0.0" -H "X-Platform: ios" \
  https://api.tudominio.mx/api/v2/public/version

# Manifest PWA del tenant
curl -H "X-Tenant-ID: demo" https://api.tudominio.mx/api/v2/public/manifest
```

---

## Debugging común

### El push no llega
1. `SELECT * FROM device_tokens WHERE owner_id = '<user>' AND revoked_at IS NULL;`
2. `tail -f backend/storage/logs/laravel.log | grep -i push`
3. Test directo en Firebase Console → Cloud Messaging → Send test message.
4. Si `revoked_at` se llenó solo: token inválido (app desinstalada o cambió de cuenta).

### Build iOS falla con "code signing"
- Verificar que el `.p12` esté en el keychain: `security find-identity -v -p codesigning`.
- En CI: validar que `IOS_CERTIFICATE_BASE64` no tenga saltos de línea raros.

### Build Android falla con "duplicate class"
```bash
cd frontend/android && ./gradlew clean
cd .. && npx cap sync android
```

### Cámara nativa no abre en iOS
- Verificar `NSCameraUsageDescription` en `Info.plist`.
- Reiniciar app después de aceptar permiso (iOS cachea agresivo).

### WebSocket no conecta en native
- `VITE_REVERB_HOST` (en `.env`/`.env.production`) debe apuntar a un
  dominio público (no `localhost` ni IP local).
- `VITE_REVERB_SCHEME=https` + `forceTLS: true` en el cliente Echo.

---

## Archivos clave

- `frontend/tenants/_types.ts` — schema TenantConfig
- `frontend/tenants/_template.tenant.ts` — plantilla
- `frontend/tenants/<slug>.tenant.ts` — configs reales por SOFOM
- `frontend/scripts/build-tenant.mjs` — orquestador del build per-tenant
- `frontend/capacitor.config.ts` — parametrizado por `capacitor.tenant.json`
- `frontend/src/platform/` — adapters web vs native
- `.github/workflows/mobile-build.yml` — CI de releases móviles
