---
name: dev-startup
description: Comandos de inicio (boot) de todos los componentes de LendusFind — backend Laravel, Reverb WebSocket, worker de colas, frontend web/PWA, emuladores iOS y Android, y app Capacitor. Usar cuando se quiera arrancar o detener cualquier servicio del stack.
---

# Dev Startup — Comandos de inicio

## Cuándo aplica

Esta skill es el **manual de boot/halt** de LendusFind. Cubre cómo iniciar y detener cada componente del stack en local, en orden, con variables de entorno requeridas. Para conventions de Git, deploy a stores, KYC, etc. usa las skills específicas.

## Componentes del stack

```
┌────────────────────────────────────────────────────────┐
│  Frontend                                              │
│  ├─ Web/PWA: Vite dev server (localhost:5173)          │
│  └─ Native: Capacitor app (iOS sim / Android emulator) │
├────────────────────────────────────────────────────────┤
│  Backend                                               │
│  ├─ Laravel API: php artisan serve (localhost:8000)    │
│  ├─ Reverb WebSocket: php artisan reverb:start (:8080) │
│  └─ Queue worker: php artisan queue:work redis         │
├────────────────────────────────────────────────────────┤
│  Infra                                                 │
│  ├─ PostgreSQL: macOS service                          │
│  └─ Redis: macOS service                               │
└────────────────────────────────────────────────────────┘
```

---

## 0. Pre-flight (una sola vez por sesión)

```bash
# Asegúrate que PostgreSQL y Redis estén corriendo
brew services list | grep -E "postgresql|redis"
# Si no están: brew services start postgresql@15 ; brew services start redis

# Verificar conectividad rápida
redis-cli ping              # → PONG
psql -h localhost -U $USER -l | head -3
```

---

## 1. Atajo: arrancar todo lo web (`./dev.sh`)

```bash
cd /Users/hgcelaya/LendusFind
./dev.sh start              # backend :8000 + frontend :5173 en background
./dev.sh stop               # detener ambos
./dev.sh refresh            # limpiar caches Laravel
./dev.sh backend            # solo backend, foreground (logs visibles)
./dev.sh frontend           # solo frontend, foreground
```

> Este script **NO** levanta Reverb ni queue:work. Si necesitas notificaciones realtime o procesamiento de jobs (email, push, etc.), lánzalos aparte (secciones 3 y 4).

---

## 2. Backend Laravel (manual)

```bash
cd backend

# Primera vez tras pull
composer install
cp .env.example .env       # solo si no existe
php artisan key:generate   # solo si APP_KEY vacía
php artisan migrate
php artisan db:seed        # opcional: crea admin@lendus.mx / password

# Arrancar
php artisan serve --port=8000
# → http://localhost:8000

# Limpiar caches (cuando cambias config/.env)
php artisan cache:clear && php artisan config:clear && php artisan route:clear
```

**Credenciales seed**:

| Rol | Email | Password |
|---|---|---|
| Admin | admin@lendus.mx | password |
| Analyst | patricia.moreno@lendus.mx | password |
| Supervisor | carlos.ramirez@lendus.mx | password |

---

## 3. Reverb WebSocket (realtime in-app)

```bash
cd backend
php artisan reverb:start    # foreground en :8080
```

Necesario solo si quieres ver notificaciones realtime (cambios de status, documents.uploaded, etc.) en el frontend. Sin Reverb, la app web/PWA funciona pero los eventos en vivo no llegan; al recargar la página sí se ve el estado actualizado.

---

## 4. Queue worker (jobs en background)

```bash
cd backend
php artisan queue:work redis           # foreground, polling continuo
php artisan queue:work redis --once    # procesa 1 job y sale (debug)
php artisan queue:listen redis         # reinicia worker cada job (dev)
```

Necesario para:
- Envío de emails (Mailgun/SendGrid/SMTP)
- Envío de SMS/WhatsApp (Twilio)
- Push notifications (FCM/APNs)
- Webhooks salientes

**Si ves cientos de FAIL en cascada**: hay backlog de jobs serializados de clases que ya no existen. Limpia con:

```bash
php artisan queue:flush     # borra failed_jobs
php artisan queue:clear redis --queue=default   # vacía cola pendiente
```

---

## 5. Frontend Web/PWA

```bash
cd frontend

# Primera vez tras pull
npm install

# Dev server (hot reload)
npm run dev
# → http://localhost:5173
# El PWA manifest se sirve por el backend: http://localhost:8000/api/v2/public/manifest
# (necesita el header X-Tenant-ID o subdomain del tenant)

# Build producción (PWA con service worker)
npm run build               # type-check + build
npm run build-only          # build sin type-check (más rápido)
npm run preview             # servir el build local

# Calidad
npm run type-check          # vue-tsc --noEmit
npm run lint                # eslint --fix
npm run format              # prettier
```

---

## 6. Frontend móvil — Android

### 6.1 Variables de entorno (siempre)

Capacitor 8.x necesita JDK 21 (no 17, no 8) para algunos plugins.

```bash
export ANDROID_HOME=~/Library/Android/sdk
export ANDROID_SDK_ROOT=~/Library/Android/sdk
export PATH=$ANDROID_HOME/platform-tools:$ANDROID_HOME/emulator:$PATH
export JAVA_HOME=/opt/homebrew/opt/openjdk@21/libexec/openjdk.jdk/Contents/Home
```

> Considera ponerlo en tu `~/.zshrc` para no repetirlo en cada sesión.

### 6.2 Listar / arrancar emulador

```bash
emulator -list-avds
# → Pixel_3a_API_34_extension_level_7_arm64-v8a  (o el que tengas)

# Lanzar emulador en background
nohup emulator -avd Pixel_3a_API_34_extension_level_7_arm64-v8a \
  -no-snapshot-save -no-boot-anim > /tmp/emulator.log 2>&1 &

# Esperar a que arranque (booted)
adb wait-for-device
adb shell getprop sys.boot_completed   # → 1 cuando esté listo

# Ver dispositivos
adb devices
# → emulator-5554  device
```

Si no tienes AVD, créalo desde Android Studio → Device Manager → Create Device → Pixel 6, System Image API 34 (recomendado).

### 6.3 Build + instalar app demo

```bash
cd frontend

# 1. Build del web del tenant (genera dist/ y sincroniza con Capacitor)
npm run tenant:build -- demo

# 2. Compilar APK debug (gradle)
cd android
./gradlew assembleDebug
# APK queda en: app/build/outputs/apk/debug/app-debug.apk

# 3. Instalar en el emulador
adb install -r app/build/outputs/apk/debug/app-debug.apk

# 4. Lanzar la app
adb shell am start -n mx.lendus.demo/mx.lendus.demo.MainActivity

# Verificar
adb shell pidof mx.lendus.demo   # → PID si está corriendo
```

### 6.4 Logs de la app Android

```bash
adb logcat -s Capacitor:V *:E | head -40    # filtrado Capacitor + errores
adb logcat | grep mx.lendus.demo            # solo tu app
```

### 6.5 Atajo Android Studio (UI)

```bash
TENANT=demo npm run tenant:android
# → corre tenant:build + abre Android Studio en la carpeta android/
# Una vez abierto: ▶ Run (verde) en la toolbar
```

### 6.6 Detener emulador

```bash
adb -s emulator-5554 emu kill
# o cierra la ventana del emulador
```

---

## 7. Frontend móvil — iOS

### 7.1 Pre-requisitos

- Xcode 15+ (`xcodebuild -version`)
- CocoaPods (`pod --version`)

```bash
# Si no tienes CocoaPods
sudo gem install cocoapods
```

### 7.2 Build + simulator

```bash
cd frontend

# 1. Build del web del tenant
npm run tenant:build -- demo

# 2. Instalar pods (solo primera vez o cuando cambien plugins)
cd ios/App
pod install --repo-update
cd ../..

# 3. Listar simuladores
xcrun simctl list devices available | grep -E "iPhone (15|16)"

# 4. Arrancar simulador (ejemplo iPhone 15 Pro)
xcrun simctl boot "iPhone 15 Pro" 2>&1 || true
open -a Simulator

# 5. Build + instalar
cd ios/App
xcodebuild -workspace App.xcworkspace -scheme App \
  -configuration Debug -destination 'platform=iOS Simulator,name=iPhone 15 Pro' \
  -derivedDataPath build clean build

# 6. Instalar y lanzar
xcrun simctl install booted build/Build/Products/Debug-iphonesimulator/App.app
xcrun simctl launch booted mx.lendus.demo
```

### 7.3 Atajo Xcode (UI)

```bash
TENANT=demo npm run tenant:ios
# → corre tenant:build + abre Xcode en App.xcworkspace
# Una vez abierto: selecciona simulador → ▶ Run (Cmd+R)
```

### 7.4 Logs iOS

```bash
xcrun simctl spawn booted log stream --predicate 'subsystem contains "mx.lendus.demo"'
```

---

## 8. Recetas combinadas

### "Quiero ver la app web con realtime"

```bash
# Terminal 1
cd backend && php artisan serve

# Terminal 2
cd backend && php artisan reverb:start

# Terminal 3
cd backend && php artisan queue:work redis

# Terminal 4
cd frontend && npm run dev

# → http://localhost:5173
```

### "Quiero probar la app Android demo"

```bash
# Terminal 1: backend
cd backend && php artisan serve

# Terminal 2: emulador (una vez)
export ANDROID_HOME=~/Library/Android/sdk
export PATH=$ANDROID_HOME/emulator:$ANDROID_HOME/platform-tools:$PATH
emulator -avd Pixel_3a_API_34_extension_level_7_arm64-v8a &
adb wait-for-device

# Terminal 3: build + install + run
export JAVA_HOME=/opt/homebrew/opt/openjdk@21/libexec/openjdk.jdk/Contents/Home
cd frontend
npm run tenant:build -- demo
cd android && ./gradlew assembleDebug
adb install -r app/build/outputs/apk/debug/app-debug.apk
adb shell am start -n mx.lendus.demo/mx.lendus.demo.MainActivity
```

> Nota: el demo apunta a `apiBaseUrl: http://localhost:8000`. Desde el emulador Android, `localhost` es **el emulador**, no tu Mac. Para que conecte al backend cambia a `http://10.0.2.2:8000` en `tenants/demo.tenant.ts` y rebuild, o usa ngrok.

### "Limpiar todo y arrancar de cero"

```bash
# Detener servidores
./dev.sh stop
adb -s emulator-5554 emu kill 2>/dev/null
pkill -f "queue:work\|reverb:start" 2>/dev/null

# Limpiar caches backend
cd backend
php artisan cache:clear && php artisan config:clear && php artisan route:clear
php artisan queue:flush       # si hay failed_jobs viejos

# Limpiar build frontend
cd ../frontend
rm -rf dist/ node_modules/.vite/

# Limpiar build Android (si se corrompió)
cd android && ./gradlew clean && cd ..
```

---

## 9. Variables de entorno persistentes (recomendado)

Agrega a `~/.zshrc`:

```bash
# Android SDK + JDK 21 para LendusFind
export ANDROID_HOME=$HOME/Library/Android/sdk
export ANDROID_SDK_ROOT=$HOME/Library/Android/sdk
export PATH=$ANDROID_HOME/platform-tools:$ANDROID_HOME/emulator:$PATH
export JAVA_HOME=/opt/homebrew/opt/openjdk@21/libexec/openjdk.jdk/Contents/Home
```

Aplicar: `source ~/.zshrc`.

---

## 10. Troubleshooting rápido

| Síntoma | Causa probable | Fix |
|---|---|---|
| `queue:work` muestra `FAIL` en bucle por eventos viejos | Backlog de jobs serializados de clases removidas | `php artisan queue:flush && php artisan queue:clear redis` |
| Gradle: `Cannot find a Java installation matching languageVersion=21` | JAVA_HOME apunta a JDK 17 u 8 | `export JAVA_HOME=/opt/homebrew/opt/openjdk@21/libexec/openjdk.jdk/Contents/Home` |
| iOS build falla en `pod install` | Pods desactualizados | `cd ios/App && pod repo update && pod install` |
| Emulador Android no aparece en `adb devices` | adb daemon descoordinado | `adb kill-server && adb start-server && adb devices` |
| App Capacitor no carga (pantalla blanca) | `dist/` no se copió a `android/app/src/main/assets/public/` | `npm run tenant:build -- <slug>` (hace `cap sync`) |
| App Android no conecta a backend `localhost` | El emulador es otro host | Cambia `apiBaseUrl` a `http://10.0.2.2:8000` o usa ngrok |
| Reverb no conecta desde la app móvil | `VITE_REVERB_HOST=localhost` | Cambia a IP de tu Mac o ngrok HTTPS |
| CORS error desde Capacitor | `capacitor://localhost` no permitido | Ya está en `backend/config/cors.php`; verifica que no haya cache |
| `force_update: true` en `/api/v2/public/version` | App con versión < `MOBILE_*_MIN_VERSION` | Sube `X-App-Version` o baja el mínimo en `config/app.php` |

---

## 11. Archivos clave

| Comando | Archivo |
|---|---|
| `./dev.sh` | Raíz del repo |
| Tenant configs | `frontend/tenants/<slug>.tenant.ts` |
| Build per-tenant | `frontend/scripts/build-tenant.mjs` |
| Capacitor config | `frontend/capacitor.config.ts` |
| Routes API | `backend/routes/api.php` |
| Routes WS | `backend/routes/channels.php` |
| Cola y push services | `backend/app/Services/Notifications/` |
