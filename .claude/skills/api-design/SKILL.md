---
name: api-design
description: Diseño de API REST V2 para LendusFind. Usar al crear, modificar o consumir endpoints de la API.
---

# API Design

## Cuándo aplica
Seguir estas convenciones al crear o modificar endpoints en `routes/api.php`, controllers, o servicios frontend que consumen la API.

## Response Format

Todas las respuestas V2 siguen este formato:

```json
// Success
{ "success": true, "data": { ... }, "message": "Operación exitosa" }

// Error
{ "success": false, "error": "VALIDATION_ERROR", "message": "Datos inválidos", "errors": { "field": ["mensaje"] } }
```

Backend trait: `App\Http\Controllers\Api\V2\Traits\ApiResponses`
Frontend type: `V2ApiResponse<T>` en `src/types/v2/index.ts`

## Route Structure

```php
// routes/api.php — Grupos por rol
Route::prefix('v2/public')->group(function () { ... });          // Sin auth
Route::prefix('v2/applicant')->middleware('auth:sanctum')->group(function () { ... });
Route::prefix('v2/staff')->middleware(['auth:sanctum', 'staff'])->group(function () {
    // Con permisos específicos
    Route::post('/{id}/approve', [ApplicationController::class, 'approve'])
        ->middleware('permission:canApproveRejectApplications');
});
```

## Middleware Stack

```
tenant → metadata → auth:sanctum → staff → permission:methodName
```

- `tenant` (`IdentifyTenant`): Identifica tenant via `X-Tenant-ID` header o subdomain
- `metadata` (`CaptureMetadata`): Captura IP, user agent
- `staff` (`RequireStaff`): Verifica que sea staff account
- `permission:method` (`RequirePermission`): Verifica permiso específico

## Endpoint Catalog

### Public (no auth)
| Method | Endpoint | Controller |
|--------|----------|------------|
| GET | `/api/v2/config` | `Public\ConfigController` |
| POST | `/api/v2/simulator/calculate` | `Public\SimulatorController` |

### Applicant Auth
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v2/applicant/auth/otp/request` | Enviar OTP |
| POST | `/api/v2/applicant/auth/otp/verify` | Verificar OTP |
| POST | `/api/v2/applicant/auth/check-user` | Verificar si usuario existe |
| POST | `/api/v2/applicant/auth/pin/login` | Login con PIN |
| POST | `/api/v2/applicant/auth/pin/setup` | Configurar PIN |
| POST | `/api/v2/applicant/auth/pin/change` | Cambiar PIN |
| POST | `/api/v2/applicant/auth/pin/reset` | Reset PIN con OTP |

### Applicant (auth required)
| Prefix | Description |
|--------|-------------|
| `/api/v2/applicant/profile/*` | Datos personales, identificaciones, dirección, empleo, cuentas bancarias, referencias, firma |
| `/api/v2/applicant/applications` | Listar/crear solicitudes |
| `/api/v2/applicant/documents` | Gestión de documentos |
| `/api/v2/applicant/kyc/*` | CURP, RFC, INE, OFAC/PLD, biometría |
| `/api/v2/applicant/corrections` | Flujo de correcciones |
| `/api/v2/applicant/notification-preferences` | Preferencias de notificación |

### Staff Admin (auth + staff)
| Prefix | Permission |
|--------|------------|
| `/api/v2/staff/applications/*` | Varios (review, assign, approve, reject, counter-offer, notes) |
| `/api/v2/staff/users/*` | `canManageUsers` |
| `/api/v2/staff/products/*` | `canManageProducts` |
| `/api/v2/staff/config/*` | `canManageProducts` |
| `/api/v2/staff/integrations/*` | `canConfigureTenant` |
| `/api/v2/staff/notification-templates/*` | `canManageProducts` |
| `/api/v2/staff/api-logs/*` | `canManageProducts` |
| `/api/v2/staff/tenants/*` | `canConfigureTenant` |

### Person Management (auth required)
| Prefix | Description |
|--------|-------------|
| `/api/v2/persons/*` | CRUD personas, búsqueda por CURP/RFC |
| `/api/v2/persons/{id}/identifications/*` | Documentos de identidad |
| `/api/v2/persons/{id}/addresses/*` | Direcciones con verificación |
| `/api/v2/persons/{id}/employments/*` | Registros de empleo |
| `/api/v2/persons/{id}/references/*` | Referencias |
| `/api/v2/persons/{id}/bank-accounts/*` | Cuentas bancarias con validación CLABE |

## Frontend Service Pattern

```typescript
import { api } from '../api'
import type { V2ApiResponse } from '@/types/v2'

const BASE_PATH = '/v2/staff/applications'

export async function list(params?: Record<string, unknown>): Promise<V2ApiResponse<{ applications: V2Application[] }>> {
  const response = await api.get<V2ApiResponse<{ applications: V2Application[] }>>(BASE_PATH, { params })
  return response.data
}
```

File naming: `{resource}.{role}.service.ts` (e.g., `application.staff.service.ts`)

## Permissions

```php
// StaffAccount model methods
canViewAllApplications()        // SUPERVISOR, ADMIN, SUPER_ADMIN
canReviewDocuments()            // All staff
canVerifyReferences()           // All staff
canChangeApplicationStatus()    // All staff
canApproveRejectApplications()  // SUPERVISOR, ADMIN, SUPER_ADMIN
canAssignApplications()         // SUPERVISOR, ADMIN, SUPER_ADMIN
canManageProducts()             // ADMIN, SUPER_ADMIN
canManageUsers()                // ADMIN, SUPER_ADMIN
canViewReports()                // ANALYST, ADMIN, SUPER_ADMIN
canConfigureTenant()            // SUPER_ADMIN only
```

## Test Credentials (after seed)

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@lendus.mx | password |
| Analyst | patricia.moreno@lendus.mx | password |
| Supervisor | carlos.ramirez@lendus.mx | password |
