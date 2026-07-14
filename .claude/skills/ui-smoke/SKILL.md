---
name: ui-smoke
description: Smoke interactivo en navegador real (Playwright MCP) contra un stack desechable de LendusFind. Usar cuando el usuario pida "pruébalo en el navegador", "smoke la pantalla X", "confirma que el botón jala en vivo", "haz clic de verdad". NO es para regresión (no hay suite E2E aún; los feature tests de backend cubren eso) ni cuando basta un unit/feature test — es para verificar a mano un flujo vivo con ojos y clics.
---

# UI Smoke — stack desechable + navegador real

Smoke interactivo de UN flujo (botones, inputs, navegación) en un navegador de verdad.
No reemplaza `php artisan test` ni una futura suite E2E — si el objetivo es regresión,
esto NO es la herramienta. La capa de navegador es el **MCP oficial de Playwright**
(`@playwright/mcp`); este skill solo aporta el harness específico de este repo.

## Prerrequisito: Playwright MCP

Las herramientas `mcp__playwright__browser_*` deben estar disponibles (cárgalas con
ToolSearch: `+playwright browser`). Si no aparecen, pide al usuario habilitarlas:

```bash
claude mcp add playwright -- npx @playwright/mcp@latest
```

y reiniciar la sesión. **No** reimplementes automation con node/puppeteer/curl ni
instales dependencias nuevas.

## Hechos del repo (no inventar comandos)

| Qué | Valor |
|-----|-------|
| Backend dev | `php artisan serve` (dev.sh usa :8000) — para smoke usar **:8010** |
| Frontend dev | `npm run dev -- --port 5173` — para smoke usar **:5175** |
| Front → API | Sin proxy de Vite: el front pega directo a `VITE_API_URL` (`frontend/src/http/client.ts`) |
| Tenant en dev | Fallback por env `VITE_TENANT_SLUG` / `VITE_TENANT_ID` (`frontend/src/utils/tenant.ts`) |
| DB desechable | `lendusfind_e2e` (crearla si no existe). `lendusfind_test` es de phpunit (RefreshDatabase la arrasa); la de desarrollo NUNCA se toca |
| Staff MoneyCapital | `admin@` / `supervisor@` / `analista@moneycapital.mx` — pass `password` |
| Staff demo | `admin@lendus.mx` / `password` (seed de DatabaseSeeder) |
| Applicant sin SMS | Cuenta de revisión de tienda: tel `9615000000`, OTP fijo `321987` (config `services.store_review`, solo tenant moneycapital, enabled por default) |
| Rutas clave | Admin: `/admin/login`, `/admin/solicitudes`, `/admin/solicitudes/:id`. Applicant: `/:tenant/auth/phone`, `/dashboard`, `/solicitud/:id/estado`, `/m/solicitud/:id/oferta` |
| Suite E2E | No existe hoy. Si aparece (Playwright/Cypress en `frontend/`), la regresión va ahí y este skill queda solo para smoke exploratorio |

## Harness

### 1. Recrear la DB desechable

```bash
createdb -h 127.0.0.1 lendusfind_e2e 2>/dev/null || true
cd backend && php artisan config:clear   # con config cacheada las env overrides NO aplican
DB_DATABASE=lendusfind_e2e php artisan migrate:fresh --seed --force
```

`migrate:fresh` arrasa la DB — por eso SIEMPRE `DB_DATABASE=lendusfind_e2e` en el mismo
comando. Jamás correrlo sin el override.

### 2. Arrancar servers en background RASTREADO

Cada server con el tool **Bash con `run_in_background: true`** (nunca `&` inline: muere
al terminar ese bash y nadie lo mata después). Puertos alternos para no pisar los del
usuario (8000/5173 pueden estar en uso):

```bash
# Backend (proceso 1, run_in_background) — ¡NO usar `artisan serve`! (ver nota)
cd backend/public && DB_DATABASE=lendusfind_e2e QUEUE_CONNECTION=sync CACHE_STORE=file BROADCAST_CONNECTION=log \
  php -S 127.0.0.1:8010 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php

# Frontend (proceso 2, run_in_background) — apuntando al backend desechable
cd frontend && VITE_API_URL=http://127.0.0.1:8010/api VITE_TENANT_ID=<slug> VITE_TENANT_SLUG=<slug> npm run dev -- --port 5175 --strictPort
```

`<slug>` = tenant a probar (`moneycapital`, `demo`, `finatea`).

**Por qué NO `artisan serve`**: su `ServeCommand` solo pasa una whitelist de env vars al
proceso hijo (`APP_ENV`, `PATH`, `XDEBUG_*`...) — `DB_DATABASE` NO pasa y el hijo lee el
`.env` de desarrollo **en silencio** (el smoke correría contra la DB de dev). El `php -S`
directo con el router del vendor sí hereda las env reales. SIEMPRE verificar tras el
arranque que el tenant id del API coincide con el de `lendusfind_e2e` (paso 3).
`QUEUE_CONNECTION=sync` evita el Redis compartido; `CACHE_STORE=file` aísla el cache;
`BROADCAST_CONNECTION=log` evita pegarle al Reverb de dev. El frontend queda en
`http://localhost:5175` (vite puede atar solo IPv6: usar `localhost`, no `127.0.0.1`).

### 3. Esperar readiness con POLL (nunca sleep fijo)

```bash
for i in $(seq 1 30); do
  API=$(curl -s -o /dev/null -w "%{http_code}" -H "X-Tenant-ID: <slug>" http://127.0.0.1:8010/api/v2/config)
  FRONT=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:5175/)
  [ "$API" = "200" ] && [ "$FRONT" = "200" ] && echo LISTO && break
  sleep 1
done
```

Si a los 30s no hay `LISTO`, lee el output de los procesos background y reporta — no
sigas a ciegas.

Después del `LISTO`, verificación anti-DB-equivocada (obligatoria):

```bash
API_TENANT=$(curl -s -H "X-Tenant-ID: <slug>" http://127.0.0.1:8010/api/v2/config | python3 -c "import json,sys; print(json.load(sys.stdin)['data']['tenant']['id'])")
DB_TENANT=$(psql -h 127.0.0.1 -t -A -d lendusfind_e2e -c "SELECT id FROM tenants WHERE slug='<slug>';")
[ "$API_TENANT" = "$DB_TENANT" ] && echo "DB e2e OK" || echo "PELIGRO: el backend sirve OTRA DB — aborta"
```

### 4. Bucle de navegador (Playwright MCP)

1. `browser_navigate` a `http://127.0.0.1:5175/<ruta>`.
2. `browser_snapshot` — devuelve el árbol de accesibilidad con `ref` por elemento.
   **Los `ref` se invalidan tras cualquier re-render**: re-snapshot antes de cada
   acción, no recicles refs viejos.
3. `browser_click` / `browser_type` usando el `ref` del snapshot FRESCO.
4. `browser_take_screenshot` en los puntos clave (guárdalo en el scratchpad de la
   sesión) y **léelo con Read** para verificar visualmente — un click "exitoso" con
   pantalla rota no es éxito.
5. Al final del flujo: `browser_console_messages` con nivel `error` — un flujo sano
   termina con **0 errores de consola**; repórtalos si hay.

### 5. Verificar que PERSISTIÓ

El toast no es evidencia. Confirma contra el stack desechable:

```bash
psql -h 127.0.0.1 -d lendusfind_e2e -c "SELECT status, counter_offer_accepted FROM applications ORDER BY created_at DESC LIMIT 1;"
```

o vía API (`curl` al endpoint correspondiente con `X-Tenant-ID`). El dato debe reflejar
la acción del click.

### 6. Teardown SIEMPRE (aunque el smoke falle)

```bash
lsof -ti :8010 | xargs kill 2>/dev/null; lsof -ti :5175 | xargs kill 2>/dev/null
rm -f <scratchpad>/*.png
```

más `browser_close` del MCP. La DB `lendusfind_e2e` puede quedarse (se recrea en el
siguiente smoke).

## Aislar el ruido (opcional, recomendado para flujos largos)

Todo el bucle navegador es verboso (snapshots grandes, screenshots). Se puede delegar
a un subagente con el tool Agent que ejecute los pasos 4–5 y devuelva SOLO:
`✅/❌ + paso que falló + errores de consola + evidencia (query de persistencia)`.
El harness (DB + servers + teardown) conviene mantenerlo en el hilo principal para
garantizar el cleanup.

## Reglas duras

- **NUNCA** apuntar a la DB de desarrollo: todo comando artisan del harness lleva
  `DB_DATABASE=lendusfind_e2e` explícito.
- **NUNCA** matar los puertos 8000/5173 del usuario; el harness vive en 8010/5175.
- Skill delgado: navegador = Playwright MCP oficial; app = comandos reales del repo.
  Para "córreme la app y déjala corriendo" usa `/run`; para verificación integral de
  un cambio usa `/verify`.
