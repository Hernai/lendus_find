---
name: dev-ops
description: Scripts de desarrollo, deploy local y herramientas de testing para LendusFind. Usar al iniciar servidores, instalar dependencias, debuggear o configurar entorno.
---

# Dev Ops & Local Development

## Cuándo aplica
Seguir esta guía al iniciar/detener servidores de desarrollo, instalar dependencias, configurar entorno, limpiar caches, debuggear conectividad, o realizar pruebas manuales.

## dev.sh — Script principal

Ubicación: `./dev.sh` (raíz del proyecto)

```bash
./dev.sh start      # Inicia backend (:8000) + frontend (:5173) en background
./dev.sh stop       # Detiene ambos servidores (mata procesos en puertos 8000 y 5173)
./dev.sh backend    # Solo Laravel (foreground)
./dev.sh frontend   # Solo Vue.js (foreground)
./dev.sh install    # composer install + npm install
./dev.sh refresh    # Limpia caches Laravel (cache, config, route, view)
```

### Cómo funciona
- `start` mata procesos previos, lanza ambos en background, guarda PIDs en `.backend.pid` / `.frontend.pid`
- `stop` lee PIDs + hace `lsof -ti:PORT | xargs kill -9` como fallback
- Backend: `php artisan serve --host=localhost --port=8000`
- Frontend: `npm run dev -- --host --port 5173`

### Verificar que está corriendo
```bash
# Verificar puertos
lsof -ti:8000  # Backend PID
lsof -ti:5173  # Frontend PID

# Test backend API
curl -s http://localhost:8000/api/v2/config -H "X-Tenant-ID: demo" | head -c 200

# Test frontend
curl -s -o /dev/null -w "%{http_code}" http://localhost:5173
```

## Puertos y URLs

| Servicio | Puerto | URL |
|----------|--------|-----|
| Backend (Laravel) | 8000 | http://localhost:8000 |
| Frontend (Vite) | 5173 | http://localhost:5173 |
| WebSocket (Reverb) | 8081 | ws://localhost:8081 |
| PostgreSQL | 5432 | localhost |
| Redis | 6379 | localhost |

## Configuración de entorno

### Backend (`backend/.env`)
```env
# Mínimo para desarrollo local
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=lendusfind
DB_USERNAME=postgres
DB_PASSWORD=

QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=127.0.0.1

# Twilio (opcional para SMS/WhatsApp)
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_FROM_NUMBER=

# Mail (default: log, no envía realmente)
MAIL_MAILER=log
```

### Frontend (`frontend/.env`)
```env
VITE_API_URL=http://localhost:8000/api
VITE_TENANT_ID=demo
VITE_APP_NAME=LendusFind
VITE_APP_URL=http://localhost:5173
```

**Nota**: `VITE_TENANT_ID=demo` se usa como tenant por defecto en desarrollo.

## Comandos frecuentes

### Base de datos
```bash
cd backend
php artisan migrate                    # Ejecutar migraciones
php artisan migrate:fresh --seed       # Reset completo + seed (DESTRUCTIVO)
php artisan db:seed                    # Solo seed (requiere tablas existentes)
php artisan db:seed --class=StaffAccountSeeder  # Seeder específico
php artisan migrate:status             # Ver estado de migraciones
```

### Caches
```bash
cd backend
php artisan cache:clear     # Cache de aplicación
php artisan config:clear    # Cache de configuración
php artisan route:clear     # Cache de rutas
php artisan view:clear      # Cache de vistas
# O todo junto:
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan view:clear
# O vía dev.sh:
./dev.sh refresh
```

### Queue worker
```bash
cd backend
php artisan queue:work redis              # Worker en foreground
php artisan queue:work redis --tries=3    # Con reintentos
php artisan queue:restart                 # Reiniciar workers
php artisan queue:failed                  # Ver jobs fallidos
php artisan queue:retry all               # Reintentar fallidos
```

### Testing
```bash
# Backend
cd backend
php artisan test                          # Todos los tests
php artisan test --filter=TestClassName   # Test específico
php artisan test --filter=methodName      # Método específico

# Frontend
cd frontend
npm run type-check    # vue-tsc --noEmit (verificación de tipos)
npm run lint          # ESLint
npm run format        # Prettier
npm run build         # Build de producción (valida errores)
```

### Tinker (REPL de Laravel)
```bash
cd backend
php artisan tinker

# Ejemplos útiles:
\App\Models\Tenant::pluck('slug', 'id');
\App\Models\StaffAccount::where('role', 'ADMIN')->first();
\App\Models\Application::count();
\App\Models\TenantApiConfig::where('provider', 'smtp')->get();
```

## Scripts auxiliares

### ngrok — Túneles para testing remoto
```bash
./scripts/ngrok-start.sh
```
Crea túneles para backend (:8000), frontend (:5173) y WebSocket (:8081).
Requiere `ngrok config add-authtoken YOUR_TOKEN` previo.

### test-otp-sms.sh — Prueba de flujo OTP
```bash
./test-otp-sms.sh 5512345678
```
Prueba el flujo completo: solicitar OTP → verificar código → obtener token.
Usa tenant `lendusdemosii` por defecto.

## Troubleshooting

### Puerto ya en uso
```bash
# Ver qué proceso usa el puerto
lsof -i :8000
lsof -i :5173

# Matar proceso en puerto específico
lsof -ti:8000 | xargs kill -9
lsof -ti:5173 | xargs kill -9

# O usar dev.sh stop que hace esto automáticamente
./dev.sh stop
```

### Backend no responde
```bash
# 1. Verificar que PostgreSQL está corriendo
pg_isready

# 2. Verificar .env tiene DB configurada
cd backend && grep DB_ .env

# 3. Verificar migraciones
cd backend && php artisan migrate:status

# 4. Limpiar caches
./dev.sh refresh

# 5. Reiniciar
./dev.sh stop && ./dev.sh start
```

### Frontend no compila
```bash
# 1. Limpiar node_modules
cd frontend && rm -rf node_modules && npm install

# 2. Verificar tipos
npm run type-check

# 3. Ver errores de build
npm run build
```

### Tenant not found (API 404)
```bash
# Ver tenants disponibles
cd backend && php artisan tinker --execute="echo \App\Models\Tenant::pluck('slug')->implode(', ');"

# Usar el slug correcto en el header
curl -H "X-Tenant-ID: demo" http://localhost:8000/api/v2/config
```

### Redis no conecta
```bash
# Verificar que Redis está corriendo
redis-cli ping   # Debe responder PONG

# Si no está corriendo
brew services start redis   # macOS
```

## Tenants de prueba (after seed)

```bash
cd backend && php artisan tinker --execute="echo \App\Models\Tenant::pluck('slug')->implode(', ');"
```

Tenants típicos después de seed: `demo`, `test-tenant`

## Credenciales de prueba

| Rol | Email | Password |
|-----|-------|----------|
| Admin | admin@lendus.mx | password |
| Analyst | patricia.moreno@lendus.mx | password |
| Supervisor | carlos.ramirez@lendus.mx | password |

Login staff: http://localhost:5173/admin/login
