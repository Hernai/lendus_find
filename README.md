# LendusFind

**Loan Origination System (LOS) SaaS** white-label para SOFOMES mexicanas. Captura clientes, valida identidad (KYC) y origina solicitudes de crédito que se envían vía webhooks a sistemas externos (SAP, Core Banking, etc.).

> No administra portafolio ni pagos — es agnóstico, multi-tenant y white-label.

---

## Stack

### Backend — API REST
| Capa | Tecnología |
|------|-----------|
| Lenguaje | **PHP 8.2+** |
| Framework | **Laravel 12** (Sanctum auth) |
| Base de datos | **PostgreSQL 15+** (campos JSONB) |
| Cache / Colas / Sesiones | **Redis 7** |
| WebSocket | **Laravel Reverb** |
| Storage | **S3 / MinIO** (URLs firmadas) |
| Templates notif. | **Handlebars** (LightnCandy) |

### Frontend — Único proyecto, tres targets
Un solo proyecto Vue 3 que se construye con Vite y se despacha en tres destinos:

| Target | Tecnología | Salida |
|--------|-----------|--------|
| **Web** (backoffice + portal applicant + landings) | Vue 3 + TS + Tailwind + Vite | `frontend/dist/` → Nginx |
| **Android** | Capacitor (envuelve `dist/`) | `.aab` firmado |
| **iOS** | Capacitor (envuelve `dist/`) | `.ipa` firmado |

Las vistas viven en `frontend/src/views/`:
- `admin/` → **Backoffice** (analistas, supervisores, admins)
- `applicant/` → Portal del solicitante (web + mobile)
- `public/` → Landings comerciales
- `mobile/` → Vistas exclusivas de la app nativa

### Integraciones externas
- **Twilio** — SMS / WhatsApp
- **Nubarium** — KYC (CURP, RFC, INE, biometría, OFAC/PLD)
- **SMTP / SendGrid / Mailgun** — email (por tenant vía `TenantApiConfig`)
- **Webhooks** — envía JSON estandarizado a sistemas externos

### Arquitectura clave
- **Multi-tenant**: una sola DB con tenant scoping (trait `HasTenant`), identificación por subdominio o header `X-Tenant-ID`
- **White-label**: branding, subdominios y apps móviles propias por SOFOM
- **API V2**: rutas `public/`, `applicant/`, `staff/`
- **UUIDs** en todo, **UI en español**

---

## Documentación de despliegue

| Componente | Documento |
|------------|-----------|
| **Backend** (Laravel + PostgreSQL + Redis + Reverb) en AlmaLinux 9 | [backend/DEPLOYMENT.md](backend/DEPLOYMENT.md) |
| **Frontend Web** (backoffice, portal, landings) en Nginx | [frontend/DEPLOYMENT.md](frontend/DEPLOYMENT.md#web-backoffice--portal--landings) |
| **Frontend Android** (Capacitor, white-label per-tenant) | [frontend/DEPLOYMENT.md](frontend/DEPLOYMENT.md#android) |
| **Frontend iOS** (Capacitor, white-label per-tenant) | [frontend/DEPLOYMENT.md](frontend/DEPLOYMENT.md#ios) |
| **Push notifications** (FCM + APNs por tenant) | [frontend/DEPLOYMENT.md](frontend/DEPLOYMENT.md#push-notifications-fcm--apns) |
| **CI de builds móviles** (GitHub Actions per-tenant) | [.github/workflows/mobile-build.yml](.github/workflows/mobile-build.yml) |

## Documentación de arquitectura y producto

| Tema | Documento |
|------|-----------|
| Convenciones y guía para Claude / Copilot | [CLAUDE.md](CLAUDE.md) · [.github/copilot-instructions.md](.github/copilot-instructions.md) |
| Arquitectura de documentos | [docs/DOCUMENT_ARCHITECTURE.md](docs/DOCUMENT_ARCHITECTURE.md) |
| Plan de migración a arquitectura normalizada | [docs/MIGRATION_PLAN_NORMALIZED_ARCHITECTURE.md](docs/MIGRATION_PLAN_NORMALIZED_ARCHITECTURE.md) |
| Requerimientos de KYC Nubarium | [requerimientos/PLAN_KYC_NUBARIUM.md](requerimientos/PLAN_KYC_NUBARIUM.md) |
| Sistema de templates de notificaciones | [requerimientos/notification-templates-system.md](requerimientos/notification-templates-system.md) |
| Catálogo de tenants white-label | [frontend/tenants/README.md](frontend/tenants/README.md) |
| Histórico de planes/reportes ya ejecutados | [docs/archive/](docs/archive/) |

## Skills de Claude Code (convenciones por dominio)

Bajo `.claude/skills/` hay skills modulares que se cargan según el contexto:

| Skill | Cubre |
|-------|-------|
| `backend-conventions` | PHP/Laravel: controllers, models, services, enums, traits |
| `frontend-conventions` | Vue 3 + TS + Tailwind |
| `api-design` | Diseño de endpoints V2 |
| `database-patterns` | Migraciones, JSONB, índices |
| `kyc-integration` | Nubarium, CURP, RFC, INE, biometría |
| `notification-system` | Templates, canales, SendNotificationJob |
| `multitenancy` | Tenant scoping, branding |
| `application-lifecycle` | 13 estados, transiciones |
| `ui-components` | Catálogo de componentes |
| `dev-ops` · `dev-startup` | Arranque de servicios |
| `mobile-deploy` | Releases iOS/Android per-tenant |
| `git-workflow` | Conventional Commits en español |

---

## Quick start (desarrollo local)

```bash
# Backend + frontend en paralelo
./dev.sh start            # Backend :8000 + Frontend :5173
./dev.sh stop

# Backend solo
cd backend && composer install && php artisan migrate --seed && php artisan serve

# Frontend solo
cd frontend && npm install && npm run dev
```

## Tenants y seeders

### Seed default (fresh install)
`php artisan db:seed` solo crea el tenant **demo** con sus 3 productos, staff y
notification templates profesionales. Listo para desarrollo, QA y como base
inicial de producción.

```bash
php artisan migrate --force
php artisan db:seed --force
```

### Activar tenants adicionales
Cuando un SOFOM se incorpora, ejecuta su seeder específico:

```bash
# MoneyCapital
php artisan db:seed --class=MoneyCapitalSeeder --force
php artisan db:seed --class=NotificationTemplateSeeder --force      # templates pro
php artisan db:seed --class=MoneyCapitalNotificationSeeder --force  # templates MC

# Finatea
php artisan db:seed --class=FinateaSeeder --force
php artisan db:seed --class=NotificationTemplateSeeder --force
```

Todos los seeders son **idempotentes** (re-ejecutar no duplica) y los de
notification templates usan `firstOrCreate` para **respetar ediciones del
cliente** desde la UI.

### Credenciales de prueba (password: `password`)

| Tenant | Email | Rol |
|--------|-------|-----|
| demo | `superadmin@lendus.mx` | SUPER_ADMIN |
| demo | `admin@lendus.mx` | ADMIN |
| demo | `carlos.ramirez@lendus.mx` | SUPERVISOR |
| demo | `patricia.moreno@lendus.mx` | ANALYST |
| moneycapital | `superadmin@moneycapital.mx` · `admin@moneycapital.mx` | SUPER_ADMIN · ADMIN |
| finatea | `superadmin@finatea.mx` · `admin@finatea.mx` | SUPER_ADMIN · ADMIN |

### White-label per-tenant
Cada tenant declara su landing y métodos de login en `frontend/tenants/<slug>.tenant.ts`:

```ts
landingComponent: 'MoneyCapitalLanding' | 'FinateaLanding' | 'DemoLanding' | 'GenericLanding'
auth: { methods: ['phone' | 'whatsapp' | 'email' | 'pin' | 'biometric'] }
```

`TenantLandingDispatcher` resuelve la landing en `/:tenant`, y `AuthMethodView`
muestra solo los métodos habilitados (con auto-redirect si solo hay 1).
