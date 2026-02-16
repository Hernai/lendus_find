# LendusFind AI Coding Instructions

## Project Overview
LendusFind is a **white-label Loan Origination System (LOS) SaaS** for Mexican financial companies (SOFOMES). It's agnostic—only captures applicants, validates KYC identity, and originates credit applications. Does NOT manage portfolio or payments. Integration-first via webhooks to external systems (SAP, Core Banking, etc.).

## Architecture

### Monorepo Structure
- `backend/` - Laravel 12 (PHP 8.2+) RESTful API with Sanctum auth
- `frontend/` - Vue 3 (Composition API + TypeScript) SPA with Tailwind CSS
- Database: PostgreSQL 15+ with JSONB fields for flexible data

### Multitenancy
**CRITICAL**: All tenant-scoped models use `HasTenant` trait which:
- Auto-assigns `tenant_id` on creation via boot method
- Adds global scope filtering queries by current tenant from `app('tenant.id')`
- Tenant identified by subdomain (`tenant.losapp.com`) or `X-Tenant-Slug` header

**Example**: When creating models, `tenant_id` is set automatically. Query scoping is automatic.

```php
// Auto-scoped to current tenant - no need to add tenant_id filter
$applications = Application::where('status', 'SUBMITTED')->get();
```

### Document Architecture Pattern
**Active Document with Temporal Validity** (see [docs/DOCUMENT_ARCHITECTURE.md](../docs/DOCUMENT_ARCHITECTURE.md)):
- Documents use `is_current=true` flag for quick queries
- Temporal validity: `valid_from`/`valid_to` for regulatory compliance
- Never delete documents—mark as superseded
- Polymorphic relations: `documentable_type`/`documentable_id` (Person, Company, Application)

## Key Development Patterns

### Backend Conventions
1. **Enums in English, Labels in Spanish**: All enums (e.g., `ApplicationStatus`, `DocumentType`) use English canonical values (`DRAFT`, `SUBMITTED`) with `label()` method returning Spanish for UI. Located in `backend/app/Enums/`.

2. **Services Layer**: Business logic in `backend/app/Services/`. External APIs grouped in `ExternalApi/` subdirectory:
   - `NubariumService` - KYC validation (CURP, RFC, INE OCR)
   - `TwilioService` - SMS/WhatsApp
   - Base class: `BaseExternalApiService` for shared API patterns

3. **Traits for Cross-Cutting Concerns**:
   - `HasTenant` - Multitenancy scoping (REQUIRED for all tenant-scoped models)
   - `HasAuditFields` - Auto-populates `created_by`, `updated_by`, `deleted_by`
   - `HasUuid` - UUID primary keys
   - `NormalizesText` - Uppercase normalization for Mexican data (CURP, RFC)

4. **Mexican Validations**: System validates RFC (13/12 chars), CURP (18 chars), phones (10 digits), postal codes (5 digits with SEPOMEX lookup). See `backend/app/Rules/`.

### Frontend Conventions
1. **Pinia Stores**: Domain-specific stores in `frontend/src/stores/` (`auth`, `tenant`, `application`, `onboarding`, etc.). Always check store state before API calls.

2. **Composables Pattern**: Reusable logic in `frontend/src/composables/` with `use*` prefix:
   - `usePermissions()` - Role-based access (staff vs applicant)
   - `useAsyncAction()` - Loading/error states for API calls
   - `useDeviceCapture()` - Camera access for document scanning
   - `useToast()` - Toast notifications

3. **Router Guards**: Routes use `meta.requiresStaff` for admin panel access. Tenant detection via subdomain in router beforeEach.

4. **Mobile-First UI**: Single-column layouts on mobile, conversational wizard UI (8-step onboarding), sticky bottom action buttons for thumb ergonomics.

## Development Workflows

### Starting Dev Servers
```bash
# Quick start both servers
./dev.sh start

# Or separately:
cd backend && php artisan serve   # http://localhost:8000
cd frontend && npm run dev          # http://localhost:5173
```

### Testing
```bash
cd backend
php artisan test                    # All tests
php artisan test --filter=TestName  # Single test class
```

### Database
```bash
cd backend
php artisan migrate                 # Run migrations
php artisan db:seed                 # Seed test data (admin@lendus.mx / password)
```

### Cache/Queue Management
```bash
cd backend
php artisan cache:clear && php artisan config:clear && php artisan route:clear
php artisan queue:work redis        # Start queue worker for jobs
```

## Roles & Permissions
5 user types: `APPLICANT` (client), `ANALYST`, `SUPERVISOR`, `ADMIN`, `SUPER_ADMIN` (staff).

**Backend**: Use `UserType` enum and permission methods (`canApproveRejectApplications()`, `canManageProducts()`).  
**Frontend**: Check `isStaff` computed in auth store before rendering admin UI.

## Integration Points
- **Webhooks**: Applications sync to external systems via `webhook_deliveries` table when status changes
- **KYC**: Nubarium API for CURP/RFC/INE validation (auto-token refresh on 401/403)
- **OTP**: Twilio for SMS/WhatsApp codes (fallback to email)
- **Storage**: S3/MinIO for documents with signed URLs
- **Real-time**: Laravel Echo + Pusher for WebSocket notifications

## When Creating New Features
1. **Models**: Add `HasTenant` trait if tenant-scoped, `HasAuditFields` for tracking
2. **Enums**: Use English values, add `label()` for Spanish UI text
3. **Services**: Put business logic in services, not controllers
4. **API Routes**: Organize in `routes/api/` by resource (e.g., `person.php`, `application.php`)
5. **Frontend**: Create Pinia store for state, composables for reusable logic, TypeScript types in `types/`

## Common Pitfalls
- ❌ Don't forget `HasTenant` trait on new models—will leak data across tenants
- ❌ Don't use Spanish in enum values—only in `label()` methods
- ❌ Don't delete documents—mark with `is_current=false` and set `superseded_at`
- ❌ Don't hardcode tenant—use `app('tenant.id')` or middleware injection
- ❌ Don't query without global scopes—`HasTenant` handles it automatically

## Key Files to Reference
- [CLAUDE.md](../CLAUDE.md) - Extended project context
- [docs/DOCUMENT_ARCHITECTURE.md](../docs/DOCUMENT_ARCHITECTURE.md) - Document versioning pattern
- [SERVICES_STRUCTURE.md](../SERVICES_STRUCTURE.md) - Services organization
- [backend/app/Traits/](../backend/app/Traits/) - Reusable model traits
- [frontend/src/composables/](../frontend/src/composables/) - Vue composition functions
