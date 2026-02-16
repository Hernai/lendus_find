---
name: application-lifecycle
description: Ciclo de vida de solicitudes de crédito en LendusFind. Usar al trabajar con status, transiciones, aprobación/rechazo, o workflow de solicitudes.
---

# Application Lifecycle

## Cuándo aplica
Seguir esta guía al trabajar con el flujo de solicitudes de crédito: creación, status transitions, aprobación/rechazo, contraoferta, asignación, o integración con sistemas externos.

## Status Values (ApplicationStatus enum)

13 estados posibles (siempre UPPERCASE):

```php
enum ApplicationStatus: string
{
    case DRAFT = 'DRAFT';                         // Borrador
    case SUBMITTED = 'SUBMITTED';                 // Enviada
    case IN_REVIEW = 'IN_REVIEW';                 // En revisión
    case DOCS_PENDING = 'DOCS_PENDING';           // Documentos pendientes
    case CORRECTIONS_PENDING = 'CORRECTIONS_PENDING'; // Correcciones pendientes
    case COUNTER_OFFERED = 'COUNTER_OFFERED';     // Contraoferta
    case APPROVED = 'APPROVED';                   // Aprobada
    case REJECTED = 'REJECTED';                   // Rechazada
    case CANCELLED = 'CANCELLED';                 // Cancelada
    case DISBURSED = 'DISBURSED';                 // Desembolsada
    case ACTIVE = 'ACTIVE';                       // Activa
    case COMPLETED = 'COMPLETED';                 // Completada
    case DEFAULT = 'DEFAULT';                     // En mora
}
```

Estados finales: `REJECTED`, `CANCELLED`, `COMPLETED`, `DEFAULT`

## Status Transitions (State Machine)

```php
// Application model: STATUS_TRANSITIONS
private const STATUS_TRANSITIONS = [
    'DRAFT'                => ['SUBMITTED', 'CANCELLED'],
    'SUBMITTED'            => ['IN_REVIEW', 'DOCS_PENDING', 'CORRECTIONS_PENDING', 'CANCELLED'],
    'IN_REVIEW'            => ['DOCS_PENDING', 'CORRECTIONS_PENDING', 'ANALYST_REVIEW', 'APPROVED', 'REJECTED', 'CANCELLED'],
    'DOCS_PENDING'         => ['IN_REVIEW', 'SUBMITTED', 'CANCELLED'],
    'CORRECTIONS_PENDING'  => ['IN_REVIEW', 'SUBMITTED', 'CANCELLED'],
    'ANALYST_REVIEW'       => ['SUPERVISOR_REVIEW', 'DOCS_PENDING', 'CORRECTIONS_PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'],
    'SUPERVISOR_REVIEW'    => ['APPROVED', 'REJECTED', 'CANCELLED'],
    'APPROVED'             => ['SYNCED', 'CANCELLED'],
    'REJECTED'             => [],  // Terminal
    'CANCELLED'            => [],  // Terminal
    'SYNCED'               => [],  // Terminal
];
```

**Nota**: El modelo Application tiene sus propios STATUS constants que incluyen `ANALYST_REVIEW`, `SUPERVISOR_REVIEW`, y `SYNCED` (usados internamente). El enum `ApplicationStatus` tiene estados adicionales post-desembolso (`DISBURSED`, `ACTIVE`, `COMPLETED`, `DEFAULT`).

## Workflow Visual

```
DRAFT → SUBMITTED → IN_REVIEW ─┬→ DOCS_PENDING ──→ (back to IN_REVIEW)
                                ├→ CORRECTIONS_PENDING → (back to IN_REVIEW)
                                ├→ ANALYST_REVIEW → SUPERVISOR_REVIEW
                                ├→ APPROVED → SYNCED
                                ├→ REJECTED (terminal)
                                └→ CANCELLED (terminal)

Post-desembolso: APPROVED → DISBURSED → ACTIVE → COMPLETED
                                                → DEFAULT (mora)
```

## Application Model Key Methods

```php
// Transition validation
$app->canTransitionTo('APPROVED');     // bool
$app->validateTransition('APPROVED');  // throws InvalidArgumentException
$app->getAllowedTransitions();         // ['DOCS_PENDING', 'APPROVED', 'REJECTED', ...]

// Actions
$app->submit(string $accountId): void;
$app->changeStatus(string $newStatus, ?string $changedBy, ?string $notes): void;
$app->approve(string $staffId, ?float $amount, ?int $term, ?float $rate, ?string $notes): void;
$app->reject(string $staffId, string $reason, ?string $notes): void;
$app->sendCounterOffer(string $staffId, array $offerData): void;
$app->respondToCounterOffer(bool $accepted, ?string $accountId): void;
$app->cancel(?string $cancelledBy, ?string $reason): void;
$app->markSynced(?string $externalId, ?string $externalSystem): void;

// Status checks
$app->isSubmitted(): bool;
$app->isApproved(): bool;
$app->isRejected(): bool;
$app->isPending(): bool;
$app->isFinal(): bool;
```

## ApplicationService

```php
class ApplicationService
{
    public function createForPerson(
        Tenant $tenant,
        Person $person,
        Product $product,
        array $loanData,
        ?ApplicantAccount $submittedBy = null
    ): Application { ... }

    public function list(array $filters): LengthAwarePaginator { ... }
    public function getDetail(string $id): array { ... }
    public function approve(Application $app, string $staffId, ...): Application { ... }
    public function reject(Application $app, string $staffId, ...): Application { ... }
}
```

## Counter Offer Flow

```php
// Staff envía contraoferta
$app->sendCounterOffer($staffId, [
    'amount' => 40000,
    'term_months' => 24,
    'interest_rate' => 18.5,
    'reason' => 'Monto ajustado por perfil de riesgo',
]);

// Applicant responde
$app->respondToCounterOffer(accepted: true, accountId: $account->id);
```

Datos almacenados en `counter_offer` (JSONB): amount, term_months, interest_rate, reason, offered_by, offered_at, responded_at, accepted

## Status History (Audit Trail)

Cada cambio de status se registra en `application_status_histories`:

```php
ApplicationStatusHistory::create([
    'application_id' => $app->id,
    'from_status' => $oldStatus,
    'to_status' => $newStatus,
    'changed_by' => $staffId,
    'changed_by_type' => 'staff',
    'notes' => $notes,
    'ip_address' => request()->ip(),
]);
```

También se registran eventos del ciclo de vida (documentos, verificaciones, etc.) con `event_type` y `metadata`.

## Document Integration

```php
// Documentos requeridos vienen del Product
$product->required_documents; // ['INE', 'PROOF_OF_ADDRESS', 'PROOF_OF_INCOME']

// Verificar completitud
$app->hasAllRequiredDocuments(): bool;
$app->getMissingDocuments(): array;
```

## Risk Assessment

```php
$app->risk_level;  // 'LOW', 'MEDIUM', 'HIGH', 'VERY_HIGH'
$app->risk_data;   // JSONB con datos de evaluación
```

## Applicant Types

```php
Application::TYPE_INDIVIDUAL = 'INDIVIDUAL';  // Persona Física
Application::TYPE_COMPANY = 'COMPANY';        // Persona Moral (futuro)
```

Actualmente solo soporta `INDIVIDUAL`. Company support reservado para implementación futura.

## Frontend Application Views

- **AdminApplications.vue** — Lista de solicitudes con filtros (status, búsqueda, assigned_to)
- **AdminApplicationDetail.vue** — Detalle completo con tabs: datos, documentos, verificación, notas, timeline
- **ApplicationStatusView.vue** — Vista del applicant de su solicitud
- **DataCorrectionsView.vue** — Flujo de correcciones para el applicant

Frontend types: `V2Application`, `V2ApplicationDetail`, `V2ApplicationStatus` en `src/types/v2/index.ts`
