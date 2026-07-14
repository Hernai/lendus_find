# CLAUDE.md

## Project Overview

LendusFind is a white-label **Loan Origination System (LOS) SaaS** for Mexican financial companies (SOFOMES). Handles credit application onboarding, KYC validation, and integration with external systems via webhooks.

**Key Characteristics:**
- **Agnostic**: Only captures clients, validates identity (KYC), and originates credit applications — does NOT manage portfolio or payments
- **Integration First**: Sends standardized JSON via webhooks to external systems (SAP, Core Banking, etc.)
- **White-Label**: Multiple tenants, custom branding, subdomains

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.2+ / Laravel 12 (RESTful API, Sanctum Auth) |
| Frontend | Vue.js 3 (Composition API + TypeScript) + Tailwind CSS 3 |
| Database | PostgreSQL 15+ (JSONB fields) |
| Multitenancy | Single DB with Tenant Scoping (`HasTenant` trait) |
| Cache/Queue | Redis (Predis) |
| Storage | S3/MinIO (signed URLs) |
| SMS/WhatsApp | Twilio |
| KYC | Nubarium (CURP, RFC, INE, biometrics, OFAC/PLD) |
| Email | SMTP / SendGrid / Mailgun (per tenant via TenantApiConfig) |
| WebSocket | Laravel Reverb + Laravel Echo |
| Templates | Handlebars (LightnCandy) for notifications |

## Project Structure

```
LendusFind/
├── backend/                    # Laravel 12 API
│   ├── app/
│   │   ├── Enums/              # 39 PHP 8.1 enums (HasOptions trait)
│   │   ├── Http/Controllers/Api/V2/  # Public/, Applicant/, Staff/
│   │   ├── Http/Middleware/    # IdentifyTenant, RequireStaff, RequirePermission
│   │   ├── Models/             # 26 Eloquent models (UUID PKs, HasTenant)
│   │   ├── Services/           # Business logic + ExternalApi/
│   │   ├── Traits/             # HasTenant, HasUuid, HasAuditFields
│   │   └── Jobs/               # SendNotificationJob
│   ├── routes/api.php          # V2 API routes
│   └── database/migrations/    # 82 migration files
│
├── frontend/                   # Vue.js 3 SPA
│   └── src/
│       ├── views/              # public/, applicant/, admin/
│       ├── stores/             # Pinia: auth, tenant, application, onboarding, kyc, ui
│       ├── services/v2/        # Role-based: *.applicant.service.ts, *.staff.service.ts
│       ├── composables/        # useAsyncAction, useToast, useKyc*, useModal, etc.
│       ├── components/         # common/, admin/, kyc/, simulator/
│       └── types/v2/index.ts   # V2ApiResponse<T> and all V2 types
│
└── .claude/skills/             # Modular conventions (loaded on-demand)
```

## Development Commands

```bash
# Quick start
./dev.sh start              # Backend :8000 + Frontend :5173
./dev.sh stop

# Backend
cd backend
composer install && php artisan serve
php artisan migrate && php artisan db:seed
php artisan test [--filter=TestClassName]
php artisan queue:work redis
php artisan cache:clear && php artisan config:clear && php artisan route:clear

# Frontend
cd frontend
npm install && npm run dev
npm run build
npm run lint && npm run format
npm run type-check           # vue-tsc --noEmit
```

## Key Architecture Decisions

### Multitenancy
- Tenant via subdomain or `X-Tenant-ID` header (slug or UUID)
- `HasTenant` trait: auto-assigns `tenant_id` + global scope filter
- Middleware stack: `tenant` → `metadata` → `auth:sanctum` → `staff` → `permission:method`
- See `.claude/skills/multitenancy/` for details

### API (V2)
- Route groups: `v2/public/`, `v2/applicant/`, `v2/staff/`
- Response: `{ success: bool, data?: T, message?: string, error?: string, errors?: {} }`
- `ApiResponses` trait on all controllers
- See `.claude/skills/api-design/` for full endpoint catalog

### Application Lifecycle
- 13 statuses (UPPERCASE): DRAFT → SUBMITTED → IN_REVIEW → ... → APPROVED/REJECTED
- State machine with `canTransitionTo()` / `validateTransition()`
- See `.claude/skills/application-lifecycle/` for complete workflow

## General Conventions

- **Language**: UI text, validation messages, toasts in **Spanish**
- **IDs**: UUIDs everywhere (never auto-increment)
- **Enums**: String-backed, UPPERCASE values, `HasOptions` trait (`toOptions()`, `toLabels()`)
- **Mexican validators**: CURP (18 chars), RFC (12-13), CLABE (18 digits), Phone (10 digits +52)
- **Backend**: PSR-12, PHP 8.2+ features, `ApiResponses` trait, constructor DI
- **Frontend**: `<script setup lang="ts">`, Composition API, `V2ApiResponse<T>`, `isAxiosError()`
- **Mobile-first**: Tailwind responsive, `primary-*` color system mapped to tenant CSS vars

## Workflow de cambios: Grilling + OpenSpec

Todo cambio no trivial (feature, rediseño, decisión de arquitectura) sigue este flujo:

1. **Grilling primero**: correr una sesión con la skill `grilling` (o cuando el usuario
   invoque `/grill-me`) — entrevista implacable, una pregunta a la vez con respuesta
   recomendada, hasta resolver todas las ramas del árbol de decisión. Los *hechos* se
   investigan en el código; las *decisiones* se le preguntan al usuario.
2. **OpenSpec después**: con las decisiones de la entrevista, crear el cambio con
   `/opsx:new` o `/opsx:propose` y seguir el flujo spec-driven (proposal → design →
   specs → tasks → apply → verify → archive). Las reglas per-artifact en
   `openspec/config.yaml` exigen la sesión de grilling antes de redactar proposals.
3. No escribir código ni artefactos hasta confirmar entendimiento compartido.

Excepción: fixes triviales/mecánicos (typos, un bug puntual con causa clara) no requieren
grilling ni OpenSpec.

## Roles & Permissions

| Role | Admin Access | Key Permissions |
|------|-------------|-----------------|
| APPLICANT | No | Self-service only |
| ANALYST | Yes | Review docs, verify references, change status (assigned only) |
| SUPERVISOR | Yes | All analyst + approve/reject, assign analysts |
| ADMIN | Yes | All supervisor + manage products, users |
| SUPER_ADMIN | Yes | All admin + configure tenant, manage integrations |

Permission methods on `StaffAccount`: `canReviewDocuments()`, `canApproveRejectApplications()`, `canManageUsers()`, `canManageProducts()`, `canAssignApplications()`, `canConfigureTenant()`, etc.

## Test Credentials (after seed)

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@lendus.mx | password |
| Analyst | patricia.moreno@lendus.mx | password |
| Supervisor | carlos.ramirez@lendus.mx | password |

## Skills Reference

Detailed conventions are in `.claude/skills/`. Use when working on specific areas:

| Skill | When to use |
|-------|-------------|
| `backend-conventions` | Creating/modifying PHP: controllers, models, services, enums, traits, migrations |
| `frontend-conventions` | Creating/modifying Vue components, stores, services, composables, types |
| `api-design` | Creating/consuming API endpoints, understanding permissions |
| `database-patterns` | Creating migrations, working with JSONB, indexes, schema |
| `kyc-integration` | Working with identity validation, CURP/RFC/INE, biometrics, Nubarium |
| `notification-system` | Working with templates, channels, email delivery, SendNotificationJob |
| `multitenancy` | Working with tenant scoping, branding, TenantApiConfig, identification |
| `application-lifecycle` | Working with status workflow, transitions, approval/rejection, counter-offers |
| `ui-components` | UI component catalog: common primitives, applicant components (KYC, bank, docs), admin components (tables, modals, editors) |
| `dev-ops` | Starting/stopping servers, installing deps, troubleshooting, scripts, env config |
| `dev-startup` | Manual de boot/halt completo: backend, Reverb, queue, frontend web, emulador Android, simulador iOS, recetas combinadas |
| `mobile-deploy` | Releases móviles white-label per-tenant: agregar SOFOMs, build iOS/Android, push APNs/FCM |
| `deploy-ops` | Runbook de deploy en producción AlmaLinux 9 bare-metal (nginx + php-fpm + Reverb + queue) — env vars, SELinux, firewalld, script de deploy, troubleshooting |
| `git-workflow` | Git commits, branches, PRs, tags, versionado — Conventional Commits en español |
| `grilling` | Entrevista implacable para afinar un plan/idea antes de implementar; obligatoria antes de crear cambios OpenSpec |
| `grill-me` | Wrapper invocable por el usuario (`/grill-me`) que corre una sesión de `grilling` |
| `ui-smoke` | Smoke interactivo en navegador real (Playwright MCP) contra stack desechable (DB `lendusfind_e2e`, puertos 8010/5175); no es para regresión |
