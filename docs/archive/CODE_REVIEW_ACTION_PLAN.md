# Plan de Acción - Mejoras de Código Backend

**Basado en:** CODE_REVIEW.md
**Fecha:** 2026-01-15
**Principio:** Cambios incrementales que NO alteran el comportamiento

---

## Fase 1: Correcciones Críticas (Completada)

### ✅ 1.1 Seguridad de Middleware
- [x] RequirePermission: Whitelist de permisos
- [x] EnsureUserBelongsToTenant: Diseño fail-closed
- [x] IdentifyTenant: Uso de Str::isUuid()

### ✅ 1.2 Aislamiento Multi-Tenant
- [x] Reference.php: Agregar HasTenant
- [x] OtpCode.php: Agregar HasTenant

### ✅ 1.3 Constantes Indefinidas
- [x] Application.php: Usar ApplicationStatus enum
- [x] BankAccount.php: Usar BankAccountUsageType enum
- [x] EmploymentRecord.php: Usar EmploymentType enum

### ✅ 1.4 Interfaces de Servicios
- [x] Crear SmsServiceInterface
- [x] Crear KycServiceInterface
- [x] Crear DocumentStorageInterface
- [x] Crear ApiLoggerInterface

### ✅ 1.5 Traits Mejorados
- [x] HasAuditFields: Type hints y mejor detección SoftDeletes
- [x] HasUuid: Type hints correctos
- [x] HasFullName: Nuevo trait para nombres

### ✅ 1.6 Enums Normalizados
- [x] PaymentFrequency: Eliminar duplicados, agregar normalize()

---

## Fase 2: Refactorización de Servicios (Próxima)

### 2.1 Dividir NubariumService (Prioridad Alta)

**Objetivo:** Reducir de 1,559 líneas a ~300 líneas por servicio

```
Estructura propuesta:
app/Services/ExternalApi/Nubarium/
├── NubariumAuthService.php      (~150 líneas)
│   ├── getToken()
│   ├── refreshToken()
│   └── validateToken()
├── NubariumIdentityService.php  (~400 líneas)
│   ├── validateCurp()
│   ├── getCurpData()
│   ├── validateRfc()
│   └── validateIne()
├── NubariumBiometricsService.php (~300 líneas)
│   ├── matchFaces()
│   ├── checkLiveness()
│   └── extractIneOcr()
├── NubariumComplianceService.php (~300 líneas)
│   ├── checkOfac()
│   ├── checkPldBlacklists()
│   └── getImssHistory()
└── NubariumServiceFacade.php    (~100 líneas)
    └── Facade para compatibilidad
```

**Pasos:**
1. Crear estructura de directorios
2. Extraer métodos por responsabilidad
3. Crear facade para mantener API existente
4. Actualizar imports en controllers
5. Verificar que tests pasen

### 2.2 Implementar Interfaces en Servicios

```php
// Ejemplo: TwilioService
class TwilioService extends BaseExternalApiService implements SmsServiceInterface
{
    public function sendSms(string $to, string $message): array { ... }
    public function sendWhatsApp(string $to, string $message): array { ... }
    public function isConfigured(): bool { ... }
}
```

**Archivos a modificar:**
- [ ] TwilioService → SmsServiceInterface
- [ ] NubariumIdentityService → KycServiceInterface
- [ ] DocumentService → DocumentStorageInterface
- [ ] ApiLoggerService → ApiLoggerInterface

### 2.3 Registrar Bindings en ServiceProvider

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->bind(SmsServiceInterface::class, TwilioService::class);
    $this->app->bind(KycServiceInterface::class, NubariumIdentityService::class);
    $this->app->bind(DocumentStorageInterface::class, DocumentService::class);
}
```

---

## Fase 3: Form Requests (Mediana Prioridad)

### 3.1 Crear Form Requests por Controller

**Objetivo:** Eliminar 60+ validaciones inline

```
Estructura propuesta:
app/Http/Requests/
├── Applicant/
│   ├── StorePersonalDataRequest.php
│   ├── UpdatePersonalDataRequest.php
│   ├── StoreAddressRequest.php
│   └── StoreBankAccountRequest.php
├── Application/
│   ├── StoreApplicationRequest.php
│   ├── UpdateApplicationRequest.php
│   └── SubmitApplicationRequest.php
├── Auth/
│   ├── SendOtpRequest.php
│   ├── VerifyOtpRequest.php
│   └── LoginWithPinRequest.php
└── Admin/
    ├── StoreProductRequest.php
    ├── UpdateProductRequest.php
    └── AssignApplicationRequest.php
```

**Patrón de migración:**
```php
// 1. Crear Form Request
class StorePersonalDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // O lógica de autorización
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name_1' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|size:10',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'El nombre es requerido',
            // ...
        ];
    }
}

// 2. Actualizar controller
public function store(StorePersonalDataRequest $request)
{
    // $request->validated() ya está validado
    $data = $request->validated();
    // ...
}
```

---

## Fase 4: Refactorización de Controllers (Baja Prioridad)

### 4.1 División de Controllers Masivos

**ApplicantController (1,204 líneas) →**
- PersonalDataController (~200 líneas)
- AddressController (~150 líneas)
- EmploymentController (~150 líneas)
- BankAccountController (~150 líneas)

**KycController (1,443 líneas) →**
- Mover lógica a NubariumIdentityService
- Mantener controller como orquestador (~300 líneas)

**ApplicationController Admin (1,817 líneas) →**
- ApplicationReviewController (~300 líneas)
- ApplicationDocumentController (~300 líneas)
- ApplicationReferenceController (~200 líneas)
- ApplicationStatusController (~200 líneas)

### 4.2 Estrategia de Migración

```php
// 1. Crear nuevo controller
class PersonalDataController extends Controller
{
    public function __construct(
        private readonly ApplicantService $applicantService
    ) {}

    public function store(StorePersonalDataRequest $request)
    {
        return $this->applicantService->storePersonalData(
            $request->validated()
        );
    }
}

// 2. Crear redirect en controller original (temporal)
class ApplicantController extends Controller
{
    public function storePersonalData(Request $request)
    {
        return app(PersonalDataController::class)->store($request);
    }
}

// 3. Actualizar rutas gradualmente
// 4. Deprecar método original
// 5. Eliminar después de período de gracia
```

---

## Fase 5: Optimización de Performance

### 5.1 Eager Loading Obligatorio

```php
// Crear trait para controllers
trait EnforcesEagerLoading
{
    protected function getApplicantWithRelations(string $id): Applicant
    {
        return Applicant::with([
            'addresses',
            'employmentRecords',
            'bankAccounts',
            'user',
        ])->findOrFail($id);
    }
}
```

### 5.2 Caché de Estadísticas

```php
// DashboardService
public function getStats(string $tenantId): array
{
    return Cache::remember(
        "dashboard_stats_{$tenantId}",
        now()->addMinutes(5),
        fn() => $this->calculateStats($tenantId)
    );
}

// Invalidación en eventos
Application::updated(fn($app) => Cache::forget("dashboard_stats_{$app->tenant_id}"));
```

---

## Fase 6: Seguridad Adicional

### 6.1 Enmascaramiento de PII

```php
// ApplicantResource.php
public function toArray(Request $request): array
{
    return [
        // ...
        'curp' => $this->maskCurp($this->curp),
        'rfc' => $this->maskRfc($this->rfc),
    ];
}

private function maskCurp(?string $curp): ?string
{
    if (!$curp) return null;
    return substr($curp, 0, 4) . '**********' . substr($curp, -4);
}
```

### 6.2 Rate Limiting en Auth

```php
// routes/api.php
Route::middleware(['throttle:otp'])->group(function () {
    Route::post('/auth/otp/send', [AuthController::class, 'requestOtp']);
});

// RouteServiceProvider
RateLimiter::for('otp', function (Request $request) {
    return Limit::perMinute(3)->by($request->ip());
});
```

---

## Cronograma Sugerido

| Fase | Descripción | Esfuerzo | Dependencias |
|------|-------------|----------|--------------|
| 1 | Correcciones Críticas | ✅ Completado | - |
| 2 | Refactorización de Servicios | 2-3 días | Fase 1 |
| 3 | Form Requests | 2-3 días | - |
| 4 | Refactorización Controllers | 3-5 días | Fase 2, 3 |
| 5 | Optimización Performance | 1-2 días | Fase 4 |
| 6 | Seguridad Adicional | 1 día | - |

---

## Verificación de Cada Cambio

Antes de cada commit:

```bash
# 1. Sintaxis PHP
php -l app/path/to/modified/file.php

# 2. Laravel carga
php artisan route:clear && php artisan config:clear

# 3. Tests existentes
php artisan test --filter=NombreDelTest

# 4. Verificar que endpoints responden igual
# (comparar response antes/después)
```

---

## Notas Importantes

1. **No cambiar comportamiento** - Cada refactorización debe mantener la API igual
2. **Commits pequeños** - Un cambio lógico por commit
3. **Tests primero** - Si no hay test, crear uno antes de refactorizar
4. **Documentar breaking changes** - Si es inevitable, documentar en CHANGELOG
5. **Feature flags** - Para cambios grandes, usar feature flags

---

## Métricas de Éxito

| Métrica | Antes | Objetivo |
|---------|-------|----------|
| Líneas por controller (max) | 1,817 | < 400 |
| Líneas por servicio (max) | 1,559 | < 400 |
| Validaciones inline | 60+ | 0 |
| Servicios con interfaz | 0 | 100% |
| Cobertura de tests | ? | > 70% |
