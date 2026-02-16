---
name: backend-conventions
description: Convenciones PHP y Laravel para LendusFind. Usar al crear o modificar controllers, models, services, enums, traits, form requests o migraciones.
---

# Backend Conventions

## Cuándo aplica
Seguir estas convenciones al crear o modificar cualquier archivo PHP en el backend: controllers, models, services, enums, traits, form requests, migraciones o jobs.

## Code Style
- **PSR-12** coding standard
- PHP 8.2+ features: `match`, `?->`, `fn()`, enums, constructor promotion, `readonly`, union types, named args
- No docblocks cuando los tipos son evidentes; solo para lógica compleja

## Model Conventions

Orden de organización:

```php
class Application extends Model
{
    // 1. Traits
    use HasFactory, HasUuids, SoftDeletes, HasTenant, HasAuditFields;

    // 2. Table name
    protected $table = 'applications';

    // 3. Constants
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';

    // 4. Fillable
    protected $fillable = ['tenant_id', 'product_id', ...];

    // 5. Casts (ambas sintaxis válidas)
    protected $casts = [
        'requested_amount' => 'decimal:2',
        'metadata' => 'array',
        'submitted_at' => 'datetime',
    ];
    // O bien (Laravel 11+ style):
    // protected function casts(): array { return [...]; }

    // 6. Relationships
    public function tenant(): BelongsTo { ... }
    public function product(): BelongsTo { ... }

    // 7. Accessors
    public function getStatusLabelAttribute(): string { ... }

    // 8. Status helpers
    public function isSubmitted(): bool { ... }
    public function canBeApproved(): bool { ... }

    // 9. Actions (state changes)
    public function submit(string $accountId): void { ... }
    public function approve(string $staffId, ...): void { ... }

    // 10. Scopes
    public function scopeActive($query) { ... }
    public function scopeStatus($query, string $status) { ... }
}
```

UUID traits (uso mixto):
- **`HasUuids`** (Laravel built-in `Illuminate\Database\Eloquent\Concerns\HasUuids`) - Usado por la mayoría de modelos: Application, Person, StaffAccount, ApplicantAccount, Document, etc.
- **`HasUuid`** (custom `App\Traits\HasUuid`) - Usado por modelos más antiguos: Tenant, Product, TenantApiConfig, TenantBranding, ApiLog, etc.
- Ambos producen UUID PKs. Preferir `HasUuids` para modelos nuevos.

## Controller Conventions

```php
class ApplicationController extends Controller
{
    use ApiResponses;

    public function __construct(
        private ApplicationService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|array',
            'status.*' => 'string|in:DRAFT,SUBMITTED,...',
            'per_page' => 'nullable|integer|min:5|max:200',
        ]);

        $applications = $this->service->list($validated);
        return $this->success($applications, 'Solicitudes obtenidas');
    }

    public function store(StoreApplicationRequest $request): JsonResponse
    {
        $application = $this->service->create($request->validated());
        return $this->created($application, 'Solicitud creada');
    }
}
```

ApiResponses trait (`App\Http\Controllers\Api\V2\Traits\ApiResponses`):
- `success(mixed $data = null, ?string $message = null, int $status = 200)`
- `created(mixed $data = null, ?string $message = null)` → 201
- `error(string $error, string $message, int $status = 400, ?array $errors = null)`
- `notFound()`, `unauthorized()`, `forbidden()`, `validationError()`, `badRequest()`, `serviceUnavailable()`, `serverError()`

Response format:
```json
{ "success": true, "data": T, "message": "..." }
{ "success": false, "error": "NOT_FOUND", "message": "Recurso no encontrado", "errors": {} }
```

## Service Conventions

```php
class ApplicationService
{
    public function __construct(
        protected LoanCalculationService $loanCalculator,
        protected DocumentService $documentService
    ) {}

    public function createForPerson(
        Tenant $tenant,
        Person $person,
        Product $product,
        array $loanData,
        ?ApplicantAccount $submittedBy = null
    ): Application {
        // ...
    }
}
```

- Constructor injection con `protected` o `private`
- Typed return values
- Throw exceptions para business logic violations
- External API services: factory pattern `createFromConfig(TenantApiConfig)`

## Enum Conventions

```php
enum ApplicationStatus: string
{
    use HasOptions;

    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    // ... todos UPPERCASE

    public function label(): string { ... }  // Español
    public function color(): string { ... }
    public function icon(): string { ... }
    public function isFinal(): bool { ... }
    public static function normalize(string $value): ?self { ... }  // Legacy español → inglés
}
```

- Valores **SIEMPRE UPPERCASE**: `case DRAFT = 'DRAFT'`
- `HasOptions` trait provee `toOptions()` y `toLabels()` (NO `options()`)
- `normalize()` acepta valores legacy en español

## FormRequest Conventions
- Mensajes de validación en **español**
- `prepareForValidation()` para normalización (uppercase CURP/RFC, trim)
- Custom rules en `app/Rules/`: `ValidCurp`, `ValidRfc`, `ValidClabe`

## Migration Conventions
- UUID PKs: `$table->uuid('id')->primary()`
- Foreign keys: `$table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete()`
- JSONB: `$table->jsonb('metadata')->nullable()`
- Composite indexes: `$table->index(['tenant_id', 'status'])`
- Enum values como **strings** (NUNCA DB enums)
- Siempre `timestamps()` + usualmente `softDeletes()`

## Route Conventions
- V2 prefix para todos los endpoints nuevos
- Role-based grouping: `v2/public/`, `v2/applicant/`, `v2/staff/`
- Permission middleware: `->middleware('permission:canApproveRejectApplications')`

## Errores comunes a evitar
1. **Usar auto-increment** en lugar de UUID - Todos los modelos usan UUID PKs
2. **Olvidar `HasTenant`** en modelos tenant-scoped - Sin este trait, los datos se filtran mal
3. **Usar DB enums** en migraciones - Siempre strings para flexibilidad
4. **Olvidar `ApiResponses` trait** en controllers - Todos los V2 controllers lo requieren
5. **Valores lowercase en enums** (`'draft'` en vez de `'DRAFT'`) - Siempre UPPERCASE
