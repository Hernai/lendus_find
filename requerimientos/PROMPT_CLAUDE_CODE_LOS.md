# 🏦 ESPECIFICACIÓN TÉCNICA COMPLETA
## SaaS de Originación de Crédito (LOS) White-Label para SOFOMES

---

# PARTE A: PROMPT PARA CLAUDE CODE

> **INSTRUCCIÓN:** Copia todo el contenido desde aquí hasta el final de la PARTE A y pégalo en Claude Code.

---

## ROL Y CONTEXTO

Actúa como un equipo combinado de:
- **Arquitecto de Software Senior (CTO)** - Diseño de sistemas escalables
- **Experto en UI/UX para Fintech** - Experiencia mobile-first
- **Desarrollador Full-Stack** - Laravel + Vue.js

Tu objetivo es construir un **SaaS de Originación de Crédito (LOS) White-Label** completo y funcional para el mercado mexicano.

---

## STACK TECNOLÓGICO (NO NEGOCIABLE)

| Capa | Tecnología |
|------|------------|
| **Backend** | PHP 8.2+ con Laravel 11 (API RESTful, Sanctum Auth) |
| **Frontend** | Vue.js 3 (Composition API + TypeScript) + Tailwind CSS 3.4 |
| **Base de Datos** | PostgreSQL 15+ (con campos JSONB para flexibilidad) |
| **Multitenancy** | Single Database con Tenant Scoping (BelongsToTenant trait) |
| **Cache/Queue** | Redis para cache y colas de trabajos |
| **Storage** | S3/MinIO para documentos, con URLs firmadas |
| **OTP** | Twilio o MessageBird para SMS/WhatsApp |

---

## VISIÓN DEL PRODUCTO

Este es un **"Middleware Universal de Onboarding"** con tres características fundamentales:

### 1. AGNÓSTICO
El sistema **NO** administra cartera ni hace seguimiento de pagos. Su ÚNICA función es:
- Captar al cliente
- Validar identidad (KYC)
- Originar la solicitud de crédito

### 2. INTEGRATION FIRST
Al aprobar una solicitud, el sistema:
- Construye un JSON estandarizado
- Lo envía vía Webhooks al sistema externo de la financiera
- Compatible con: SAP, Core Bancario, Lendus, Excel Online, etc.

### 3. WHITE-LABEL
Múltiples financieras (Tenants) usan la misma instalación. Cada una ve:
- Su logo personalizado
- Sus colores corporativos
- Su URL personalizada (`tenant1.losapp.com`)

---

## ARQUITECTURA DE BASE DE DATOS (PostgreSQL)

### Diagrama ER (Mermaid)

```mermaid
erDiagram
    TENANTS ||--o{ PRODUCTS : has
    TENANTS ||--o{ USERS : has
    TENANTS ||--o{ APPLICANTS : has
    USERS ||--o| APPLICANTS : "may be"
    APPLICANTS ||--o{ APPLICATIONS : submits
    APPLICANTS ||--o{ DOCUMENTS : uploads
    APPLICANTS ||--o{ REFERENCES : has
    PRODUCTS ||--o{ APPLICATIONS : "used in"
    APPLICATIONS ||--o{ DOCUMENTS : requires
    APPLICATIONS ||--o{ AUDIT_LOGS : generates

    TENANTS {
        uuid id PK
        string name
        string slug UK "subdominio"
        jsonb branding "colores, logos"
        jsonb webhook_config "url, secret"
        jsonb settings "otp_provider, limits"
        boolean is_active
        timestamps
    }

    PRODUCTS {
        uuid id PK
        uuid tenant_id FK
        string name
        enum type "SIMPLE,NOMINA,ARRENDAMIENTO,HIPOTECARIO,PYME"
        jsonb rules "tasas, plazos, montos"
        jsonb required_docs "docs obligatorios"
        jsonb extra_fields "campos dinámicos"
        boolean is_active
        timestamps
    }

    USERS {
        uuid id PK
        uuid tenant_id FK
        string phone UK
        string email
        string password_hash
        enum role "APPLICANT,ANALYST,ADMIN"
        timestamp phone_verified_at
        timestamps
    }

    APPLICANTS {
        uuid id PK
        uuid tenant_id FK
        uuid user_id FK
        enum type "PERSONA_FISICA,PERSONA_MORAL"
        string rfc
        string curp
        jsonb personal_data "nombres, fecha_nac, genero"
        jsonb contact_info "phone, email"
        jsonb address "calle, cp, colonia, etc"
        jsonb employment_info "empresa, puesto, ingreso"
        enum kyc_status "PENDING,IN_PROGRESS,VERIFIED,REJECTED"
        timestamps
    }

    APPLICATIONS {
        uuid id PK
        uuid tenant_id FK
        uuid applicant_id FK
        uuid product_id FK
        string folio UK "LEN-2026-00001"
        enum status "DRAFT,SUBMITTED,IN_REVIEW,DOCS_PENDING,APPROVED,REJECTED,SYNCED"
        decimal requested_amount
        decimal approved_amount
        integer term_months
        enum payment_frequency "WEEKLY,BIWEEKLY,MONTHLY"
        jsonb dynamic_data "campos específicos del producto"
        jsonb simulation_data "tabla amortización, CAT"
        timestamp submitted_at
        timestamp approved_at
        timestamp webhook_sent_at
        timestamps
    }

    DOCUMENTS {
        uuid id PK
        uuid tenant_id FK
        uuid applicant_id FK
        uuid application_id FK
        enum type "INE_FRONT,INE_BACK,CURP,RFC_CSF,PROOF_ADDRESS,PROOF_INCOME,SIGNATURE"
        string file_path
        string mime_type
        integer file_size
        jsonb ocr_data
        enum status "PENDING,PROCESSING,VERIFIED,REJECTED"
        string rejection_reason
        timestamps
    }

    REFERENCES {
        uuid id PK
        uuid applicant_id FK
        string full_name
        string phone
        enum relationship "FAMILY,FRIEND,COWORKER,OTHER"
        enum type "PERSONAL,WORK"
        timestamps
    }

    AUDIT_LOGS {
        uuid id PK
        uuid tenant_id FK
        uuid user_id FK
        uuid application_id FK
        string action
        jsonb old_values
        jsonb new_values
        string ip_address
        timestamps
    }
```

---

### Tabla: `tenants`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | uuid PK | Primary key |
| `name` | string | Nombre comercial: "Financiera Lendus" |
| `slug` | string unique | Subdominio: "lendus" → lendus.app.com |
| `branding` | JSONB | `{primary_color, secondary_color, accent_color, logo_url, favicon_url, font_family}` |
| `webhook_config` | JSONB | `{url, secret_key, retry_count, events[], timeout_seconds}` |
| `settings` | JSONB | `{otp_provider, kyc_provider, max_loan_amount, min_loan_amount, currency, timezone}` |
| `is_active` | boolean | Activar/desactivar tenant |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Ejemplo de `branding` JSONB:**
```json
{
  "primary_color": "#6200EE",
  "secondary_color": "#03DAC6",
  "accent_color": "#FF5722",
  "logo_url": "https://cdn.los.com/tenants/lendus/logo.svg",
  "favicon_url": "https://cdn.los.com/tenants/lendus/favicon.ico",
  "font_family": "Inter, sans-serif",
  "border_radius": "8px"
}
```

**Ejemplo de `webhook_config` JSONB:**
```json
{
  "url": "https://core.lendus.mx/api/v1/applications",
  "secret_key": "whsec_abc123...",
  "retry_count": 3,
  "timeout_seconds": 30,
  "events": ["application.approved", "application.rejected", "documents.verified"]
}
```

---

### Tabla: `products`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | uuid PK | |
| `tenant_id` | uuid FK | Relación con tenants |
| `name` | string | "Crédito Nómina", "Arrendamiento Puro" |
| `type` | enum | SIMPLE, NOMINA, ARRENDAMIENTO, HIPOTECARIO, PYME |
| `rules` | JSONB | Reglas del producto |
| `required_docs` | JSONB | Documentos requeridos |
| `extra_fields` | JSONB | Campos dinámicos según tipo |
| `is_active` | boolean | |

**Ejemplo de `rules` JSONB:**
```json
{
  "min_amount": 5000,
  "max_amount": 500000,
  "min_term_months": 3,
  "max_term_months": 48,
  "annual_rate": 45.0,
  "opening_commission": 2.5,
  "amortization_type": "FRENCH",
  "payment_frequencies": ["WEEKLY", "BIWEEKLY", "MONTHLY"],
  "min_age": 18,
  "max_age": 70,
  "min_income": 8000
}
```

**Ejemplo de `required_docs` JSONB:**
```json
[
  {"type": "INE_FRONT", "required": true, "description": "INE/IFE vigente (frente)"},
  {"type": "INE_BACK", "required": true, "description": "INE/IFE vigente (reverso)"},
  {"type": "PROOF_ADDRESS", "required": true, "description": "Comprobante de domicilio (máx 3 meses)"},
  {"type": "PROOF_INCOME", "required": false, "description": "Comprobante de ingresos"}
]
```

**Ejemplo de `extra_fields` para ARRENDAMIENTO:**
```json
{
  "fields": [
    {"name": "asset_type", "label": "Tipo de activo", "type": "select", "options": ["AUTO", "MAQUINARIA", "EQUIPO_COMPUTO"], "required": true},
    {"name": "asset_brand", "label": "Marca", "type": "text", "required": true},
    {"name": "asset_model", "label": "Modelo", "type": "text", "required": true},
    {"name": "asset_year", "label": "Año", "type": "number", "min": 2015, "required": true},
    {"name": "asset_value", "label": "Valor del activo", "type": "currency", "required": true}
  ]
}
```

---

### Tabla: `applicants`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | uuid PK | |
| `tenant_id` | uuid FK | Scoping de tenant |
| `user_id` | uuid FK | Relación con users (auth) |
| `type` | enum | PERSONA_FISICA, PERSONA_MORAL |
| `rfc` | string | Unique por tenant (composite index) |
| `curp` | string nullable | Solo para personas físicas |
| `personal_data` | JSONB | Datos personales |
| `contact_info` | JSONB | Información de contacto |
| `address` | JSONB | Dirección completa |
| `employment_info` | JSONB | Información laboral |
| `kyc_status` | enum | PENDING, IN_PROGRESS, VERIFIED, REJECTED |

**Ejemplo de `personal_data` JSONB:**
```json
{
  "first_name": "JUAN",
  "middle_name": "CARLOS",
  "last_name": "PÉREZ",
  "second_last_name": "GARCÍA",
  "birth_date": "1990-05-15",
  "birth_state": "CHIAPAS",
  "gender": "M",
  "nationality": "MEXICANA",
  "marital_status": "SOLTERO",
  "education_level": "LICENCIATURA"
}
```

**Ejemplo de `address` JSONB:**
```json
{
  "street": "AV. REFORMA",
  "ext_number": "222",
  "int_number": "PISO 5",
  "neighborhood": "JUÁREZ",
  "postal_code": "06600",
  "municipality": "CUAUHTÉMOC",
  "city": "CIUDAD DE MÉXICO",
  "state": "CIUDAD DE MÉXICO",
  "country": "MX",
  "housing_type": "RENTADA",
  "years_living": 3
}
```

**Ejemplo de `employment_info` JSONB:**
```json
{
  "employment_status": "EMPLEADO",
  "company_name": "EMPRESA ABC S.A. DE C.V.",
  "company_sector": "TECNOLOGÍA",
  "position": "GERENTE DE SISTEMAS",
  "seniority_months": 36,
  "monthly_income": 45000,
  "other_income": 5000,
  "company_phone": "5555551234",
  "company_address": {
    "street": "PASEO DE LA REFORMA",
    "number": "500",
    "neighborhood": "LOMAS",
    "postal_code": "11000"
  }
}
```

---

### Tabla: `applications`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | uuid PK | |
| `tenant_id` | uuid FK | Scoping |
| `applicant_id` | uuid FK | Solicitante |
| `product_id` | uuid FK | Producto seleccionado |
| `folio` | string unique | Número legible: "LEN-2026-00001" |
| `status` | enum | DRAFT, SUBMITTED, IN_REVIEW, DOCS_PENDING, APPROVED, REJECTED, SYNCED |
| `requested_amount` | decimal(15,2) | Monto solicitado |
| `approved_amount` | decimal nullable | Monto aprobado (puede diferir) |
| `term_months` | integer | Plazo en meses |
| `payment_frequency` | enum | WEEKLY, BIWEEKLY, MONTHLY |
| `dynamic_data` | JSONB | Campos específicos del producto |
| `simulation_data` | JSONB | Resultados del simulador |
| `webhook_sent_at` | timestamp | Cuándo se envió al sistema externo |

**Ejemplo de `simulation_data` JSONB:**
```json
{
  "annual_rate": 45.0,
  "monthly_rate": 3.75,
  "opening_commission": 2500,
  "monthly_payment": 5832.45,
  "total_interest": 19946.40,
  "total_amount": 119946.40,
  "cat": 58.5,
  "amortization_table": [
    {
      "number": 1,
      "date": "2026-02-01",
      "opening_balance": 100000,
      "principal": 2082.45,
      "interest": 3750.00,
      "payment": 5832.45,
      "closing_balance": 97917.55
    }
  ]
}
```

---

### Tabla: `documents`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | uuid PK | |
| `tenant_id` | uuid FK | |
| `applicant_id` | uuid FK | Docs del solicitante |
| `application_id` | uuid FK nullable | Docs específicos de la solicitud |
| `type` | enum | INE_FRONT, INE_BACK, CURP, RFC_CSF, PROOF_ADDRESS, PROOF_INCOME, SIGNATURE |
| `file_path` | string | Path en S3: `tenants/{id}/docs/{uuid}.pdf` |
| `mime_type` | string | `image/jpeg`, `application/pdf` |
| `file_size` | integer | Bytes |
| `ocr_data` | JSONB nullable | Datos extraídos por OCR |
| `status` | enum | PENDING, PROCESSING, VERIFIED, REJECTED |
| `rejection_reason` | string nullable | Motivo de rechazo |

---

### Tabla: `references`

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | uuid PK | |
| `applicant_id` | uuid FK | |
| `full_name` | string | Nombre completo |
| `phone` | string | 10 dígitos |
| `relationship` | enum | FAMILY, FRIEND, COWORKER, OTHER |
| `type` | enum | PERSONAL, WORK |

---

## ARQUITECTURA BACKEND (Laravel 11)

### Estructura de Carpetas

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── AuthController.php
│   │   │   ├── ConfigController.php
│   │   │   ├── ApplicantController.php
│   │   │   ├── ApplicationController.php
│   │   │   ├── DocumentController.php
│   │   │   ├── SimulatorController.php
│   │   │   └── ReferenceController.php
│   │   └── Admin/
│   │       ├── TenantController.php
│   │       ├── ProductController.php
│   │       ├── ApplicationReviewController.php
│   │       └── DashboardController.php
│   ├── Middleware/
│   │   ├── IdentifyTenant.php
│   │   ├── EnsureTenantIsActive.php
│   │   └── CheckApplicationOwnership.php
│   ├── Requests/
│   │   ├── StoreApplicantRequest.php
│   │   ├── StoreApplicationRequest.php
│   │   └── UploadDocumentRequest.php
│   └── Resources/
│       ├── ApplicantResource.php
│       ├── ApplicationResource.php
│       └── TenantConfigResource.php
├── Models/
│   ├── Tenant.php
│   ├── Product.php
│   ├── User.php
│   ├── Applicant.php
│   ├── Application.php
│   ├── Document.php
│   ├── Reference.php
│   └── AuditLog.php
├── Services/
│   ├── TenantService.php
│   ├── SimulatorService.php
│   ├── OtpService.php
│   ├── DocumentService.php
│   ├── WebhookService.php
│   └── PostalCodeService.php
├── Jobs/
│   ├── DispatchWebhookJob.php
│   ├── ProcessDocumentOcrJob.php
│   └── SendOtpJob.php
├── Events/
│   ├── ApplicationSubmittedEvent.php
│   ├── ApplicationApprovedEvent.php
│   └── DocumentUploadedEvent.php
├── Listeners/
│   ├── SendWebhookOnApproval.php
│   ├── NotifyApplicantOnStatusChange.php
│   └── ProcessOcrOnUpload.php
├── Traits/
│   └── BelongsToTenant.php
└── Observers/
    └── ApplicationObserver.php
```

### Middleware: IdentifyTenant.php

```php
<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        
        // Extraer subdominio: lendus.losapp.com -> lendus
        $parts = explode('.', $host);
        $subdomain = count($parts) >= 3 ? $parts[0] : null;
        
        // También permitir header X-Tenant-Slug para desarrollo
        $subdomain = $subdomain ?? $request->header('X-Tenant-Slug');
        
        if (!$subdomain) {
            return response()->json([
                'success' => false,
                'error' => 'TENANT_NOT_SPECIFIED',
                'message' => 'No se pudo identificar la financiera'
            ], 400);
        }
        
        $tenant = Tenant::where('slug', $subdomain)->first();
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'error' => 'TENANT_NOT_FOUND',
                'message' => 'Financiera no encontrada'
            ], 404);
        }
        
        if (!$tenant->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'TENANT_INACTIVE',
                'message' => 'Esta financiera no está disponible'
            ], 403);
        }
        
        // Bind tenant al container para acceso global
        app()->instance('tenant', $tenant);
        app()->instance(Tenant::class, $tenant);
        
        // Agregar a request para fácil acceso
        $request->merge(['tenant' => $tenant]);
        
        return $next($request);
    }
}
```

### Trait: BelongsToTenant.php

```php
<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        // Auto-asignar tenant_id al crear
        static::creating(function (Model $model) {
            if (!$model->tenant_id && app()->has('tenant')) {
                $model->tenant_id = app('tenant')->id;
            }
        });

        // Global scope para filtrar por tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (app()->has('tenant')) {
                $builder->where(
                    $builder->getModel()->getTable() . '.tenant_id',
                    app('tenant')->id
                );
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
```

### Service: SimulatorService.php

```php
<?php

namespace App\Services;

use App\Models\Product;

class SimulatorService
{
    public function calculate(
        Product $product,
        float $amount,
        int $termMonths,
        string $frequency = 'MONTHLY'
    ): array {
        $rules = $product->rules;
        
        $annualRate = $rules['annual_rate'] / 100;
        $periodsPerYear = $this->getPeriodsPerYear($frequency);
        $totalPeriods = $this->getTotalPeriods($termMonths, $frequency);
        $periodicRate = $annualRate / $periodsPerYear;
        
        // Comisión por apertura
        $openingCommission = $amount * ($rules['opening_commission'] / 100);
        
        // Cálculo de pago periódico (Amortización Francesa)
        $payment = $this->calculatePayment($amount, $periodicRate, $totalPeriods);
        
        // Generar tabla de amortización
        $amortizationTable = $this->generateAmortizationTable(
            $amount,
            $periodicRate,
            $payment,
            $totalPeriods,
            $frequency
        );
        
        // Calcular totales
        $totalInterest = array_sum(array_column($amortizationTable, 'interest'));
        $totalAmount = $amount + $totalInterest + $openingCommission;
        
        // CAT (Costo Anual Total) - simplificado
        $cat = $this->calculateCAT($amount, $totalAmount, $termMonths / 12);
        
        return [
            'requested_amount' => $amount,
            'term_months' => $termMonths,
            'payment_frequency' => $frequency,
            'total_periods' => $totalPeriods,
            'annual_rate' => $rules['annual_rate'],
            'periodic_rate' => round($periodicRate * 100, 4),
            'opening_commission' => round($openingCommission, 2),
            'periodic_payment' => round($payment, 2),
            'total_interest' => round($totalInterest, 2),
            'total_amount' => round($totalAmount, 2),
            'cat' => round($cat, 2),
            'amortization_table' => $amortizationTable,
        ];
    }
    
    private function calculatePayment(float $principal, float $rate, int $periods): float
    {
        if ($rate === 0.0) {
            return $principal / $periods;
        }
        
        return $principal * ($rate * pow(1 + $rate, $periods)) / (pow(1 + $rate, $periods) - 1);
    }
    
    private function generateAmortizationTable(
        float $principal,
        float $rate,
        float $payment,
        int $periods,
        string $frequency
    ): array {
        $table = [];
        $balance = $principal;
        $startDate = now()->addMonth();
        $interval = $this->getDateInterval($frequency);
        
        for ($i = 1; $i <= $periods; $i++) {
            $interest = $balance * $rate;
            $principalPayment = $payment - $interest;
            $newBalance = $balance - $principalPayment;
            
            $table[] = [
                'number' => $i,
                'date' => $startDate->copy()->add($interval, $i - 1)->format('Y-m-d'),
                'opening_balance' => round($balance, 2),
                'principal' => round($principalPayment, 2),
                'interest' => round($interest, 2),
                'iva' => round($interest * 0.16, 2),
                'payment' => round($payment + ($interest * 0.16), 2),
                'closing_balance' => round(max(0, $newBalance), 2),
            ];
            
            $balance = $newBalance;
        }
        
        return $table;
    }
    
    private function getPeriodsPerYear(string $frequency): int
    {
        return match($frequency) {
            'WEEKLY' => 52,
            'BIWEEKLY' => 26,
            'MONTHLY' => 12,
            default => 12,
        };
    }
    
    private function getTotalPeriods(int $months, string $frequency): int
    {
        return match($frequency) {
            'WEEKLY' => (int) round($months * 4.33),
            'BIWEEKLY' => $months * 2,
            'MONTHLY' => $months,
            default => $months,
        };
    }
    
    private function getDateInterval(string $frequency): \DateInterval
    {
        return match($frequency) {
            'WEEKLY' => new \DateInterval('P1W'),
            'BIWEEKLY' => new \DateInterval('P2W'),
            'MONTHLY' => new \DateInterval('P1M'),
            default => new \DateInterval('P1M'),
        };
    }
    
    private function calculateCAT(float $principal, float $total, float $years): float
    {
        // Fórmula simplificada del CAT
        return (pow($total / $principal, 1 / $years) - 1) * 100;
    }
}
```

### Job: DispatchWebhookJob.php

```php
<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public int $tries = 3;
    public array $backoff = [60, 300, 900]; // 1min, 5min, 15min
    
    public function __construct(
        public Application $application,
        public string $event = 'application.approved'
    ) {}

    public function handle(): void
    {
        $tenant = Tenant::find($this->application->tenant_id);
        $config = $tenant->webhook_config;
        
        if (!$config || empty($config['url'])) {
            Log::warning("No webhook configured for tenant", [
                'tenant_id' => $tenant->id,
                'application_id' => $this->application->id,
            ]);
            return;
        }
        
        // Verificar si el evento está habilitado
        if (!in_array($this->event, $config['events'] ?? [])) {
            Log::info("Event not enabled for webhook", [
                'event' => $this->event,
                'tenant_id' => $tenant->id,
            ]);
            return;
        }
        
        $payload = $this->buildPayload();
        $signature = $this->generateSignature($payload, $config['secret_key']);
        
        try {
            $response = Http::timeout($config['timeout_seconds'] ?? 30)
                ->withHeaders([
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $this->event,
                    'X-Tenant-ID' => $tenant->id,
                    'X-Application-Folio' => $this->application->folio,
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'LOS-Webhook/1.0',
                ])
                ->post($config['url'], $payload);
            
            if ($response->successful()) {
                $this->application->update([
                    'status' => 'SYNCED',
                    'webhook_sent_at' => now(),
                ]);
                
                Log::info("Webhook sent successfully", [
                    'application_id' => $this->application->id,
                    'folio' => $this->application->folio,
                    'response_status' => $response->status(),
                ]);
            } else {
                throw new \Exception("Webhook failed with status: {$response->status()}");
            }
        } catch (\Exception $e) {
            Log::error("Webhook dispatch failed", [
                'application_id' => $this->application->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);
            
            throw $e; // Re-throw para retry
        }
    }
    
    private function buildPayload(): array
    {
        $app = $this->application->load([
            'applicant.references',
            'product',
            'documents'
        ]);
        
        return [
            'event' => $this->event,
            'timestamp' => now()->toIso8601String(),
            'webhook_id' => (string) \Str::uuid(),
            'data' => [
                'folio' => $app->folio,
                'status' => $app->status,
                'submitted_at' => $app->submitted_at?->toIso8601String(),
                'approved_at' => $app->approved_at?->toIso8601String(),
                
                'product' => [
                    'id' => $app->product->id,
                    'type' => $app->product->type,
                    'name' => $app->product->name,
                ],
                
                'financial' => [
                    'requested_amount' => (float) $app->requested_amount,
                    'approved_amount' => (float) $app->approved_amount,
                    'term_months' => $app->term_months,
                    'payment_frequency' => $app->payment_frequency,
                    'simulation' => $app->simulation_data,
                ],
                
                'applicant' => [
                    'id' => $app->applicant->id,
                    'type' => $app->applicant->type,
                    'rfc' => $app->applicant->rfc,
                    'curp' => $app->applicant->curp,
                    'personal_data' => $app->applicant->personal_data,
                    'contact_info' => $app->applicant->contact_info,
                    'address' => $app->applicant->address,
                    'employment_info' => $app->applicant->employment_info,
                    'kyc_status' => $app->applicant->kyc_status,
                    'references' => $app->applicant->references->map(fn($ref) => [
                        'full_name' => $ref->full_name,
                        'phone' => $ref->phone,
                        'relationship' => $ref->relationship,
                        'type' => $ref->type,
                    ])->toArray(),
                ],
                
                'documents' => $app->documents->map(fn($doc) => [
                    'type' => $doc->type,
                    'url' => $doc->getSignedUrl(expiration: 3600),
                    'status' => $doc->status,
                    'ocr_data' => $doc->ocr_data,
                    'uploaded_at' => $doc->created_at->toIso8601String(),
                ])->toArray(),
                
                'dynamic_data' => $app->dynamic_data,
            ],
        ];
    }
    
    private function generateSignature(array $payload, string $secret): string
    {
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        return 'sha256=' . hash_hmac('sha256', $jsonPayload, $secret);
    }
    
    public function failed(\Throwable $exception): void
    {
        Log::critical("Webhook permanently failed", [
            'application_id' => $this->application->id,
            'folio' => $this->application->folio,
            'error' => $exception->getMessage(),
        ]);
        
        // Aquí podrías notificar al admin del tenant
    }
}
```

---

## ARQUITECTURA FRONTEND (Vue.js 3)

### Estructura de Carpetas

```
src/
├── assets/
│   ├── styles/
│   │   ├── base.css
│   │   ├── components.css
│   │   └── utilities.css
│   └── images/
├── components/
│   ├── common/
│   │   ├── AppButton.vue
│   │   ├── AppInput.vue
│   │   ├── AppSelect.vue
│   │   ├── AppCheckbox.vue
│   │   ├── AppRadioGroup.vue
│   │   ├── AppFileUpload.vue
│   │   ├── AppDatePicker.vue
│   │   ├── AppCurrencyInput.vue
│   │   ├── AppPhoneInput.vue
│   │   ├── AppOtpInput.vue
│   │   ├── AppStepper.vue
│   │   ├── AppModal.vue
│   │   ├── AppToast.vue
│   │   ├── AppSpinner.vue
│   │   └── AppSkeleton.vue
│   ├── simulator/
│   │   ├── AmountSlider.vue
│   │   ├── TermSelector.vue
│   │   ├── FrequencyToggle.vue
│   │   ├── PaymentCard.vue
│   │   └── AmortizationTable.vue
│   ├── onboarding/
│   │   ├── StepPersonalInfo.vue
│   │   ├── StepIdentification.vue
│   │   ├── StepAddress.vue
│   │   ├── StepEmployment.vue
│   │   ├── StepFinancial.vue
│   │   ├── StepDocuments.vue
│   │   ├── StepReferences.vue
│   │   └── StepReview.vue
│   └── layout/
│       ├── TheHeader.vue
│       ├── TheFooter.vue
│       ├── TheSidebar.vue
│       ├── TheNavigation.vue
│       └── StickyActionBar.vue
├── composables/
│   ├── useTheme.ts
│   ├── useTenant.ts
│   ├── useAuth.ts
│   ├── useValidation.ts
│   ├── usePostalCode.ts
│   ├── useSimulator.ts
│   └── useFileUpload.ts
├── stores/
│   ├── tenant.ts
│   ├── auth.ts
│   ├── applicant.ts
│   ├── application.ts
│   └── ui.ts
├── views/
│   ├── LandingPage.vue
│   ├── SimulatorPage.vue
│   ├── AuthPage.vue
│   ├── OnboardingWizard.vue
│   ├── DashboardPage.vue
│   ├── ApplicationDetail.vue
│   └── admin/
│       ├── AdminDashboard.vue
│       ├── ApplicationsReview.vue
│       └── TenantSettings.vue
├── router/
│   └── index.ts
├── services/
│   ├── api.ts
│   ├── auth.service.ts
│   ├── applicant.service.ts
│   └── document.service.ts
├── types/
│   ├── tenant.d.ts
│   ├── applicant.d.ts
│   ├── application.d.ts
│   └── api.d.ts
└── utils/
    ├── validators.ts
    ├── formatters.ts
    └── constants.ts
```

### Composable: useTheme.ts

```typescript
import { ref, computed, watchEffect, onMounted } from 'vue'
import { useTenantStore } from '@/stores/tenant'
import type { Branding } from '@/types/tenant'

export function useTheme() {
  const tenantStore = useTenantStore()
  const isLoaded = ref(false)

  const branding = computed(() => tenantStore.branding)

  const applyTheme = (config: Branding) => {
    const root = document.documentElement
    
    // Colores principales
    root.style.setProperty('--color-primary', config.primary_color)
    root.style.setProperty('--color-secondary', config.secondary_color)
    root.style.setProperty('--color-accent', config.accent_color)
    
    // Generar variantes automáticas
    root.style.setProperty('--color-primary-50', adjustBrightness(config.primary_color, 0.9))
    root.style.setProperty('--color-primary-100', adjustBrightness(config.primary_color, 0.8))
    root.style.setProperty('--color-primary-200', adjustBrightness(config.primary_color, 0.6))
    root.style.setProperty('--color-primary-700', adjustBrightness(config.primary_color, -0.2))
    root.style.setProperty('--color-primary-800', adjustBrightness(config.primary_color, -0.3))
    
    // Tipografía
    root.style.setProperty('--font-family', config.font_family || 'Inter, sans-serif')
    
    // Bordes
    root.style.setProperty('--border-radius', config.border_radius || '8px')
    root.style.setProperty('--border-radius-lg', `calc(${config.border_radius || '8px'} * 1.5)`)
    root.style.setProperty('--border-radius-full', '9999px')
    
    // Actualizar favicon
    updateFavicon(config.favicon_url)
    
    // Actualizar título
    document.title = tenantStore.name || 'Solicitud de Crédito'
    
    isLoaded.value = true
  }

  const updateFavicon = (url: string) => {
    if (!url) return
    
    let link = document.querySelector<HTMLLinkElement>("link[rel~='icon']")
    if (!link) {
      link = document.createElement('link')
      link.rel = 'icon'
      document.head.appendChild(link)
    }
    link.href = url
  }

  const adjustBrightness = (hex: string, percent: number): string => {
    const num = parseInt(hex.replace('#', ''), 16)
    const amt = Math.round(2.55 * percent * 100)
    const R = Math.max(0, Math.min(255, (num >> 16) + amt))
    const G = Math.max(0, Math.min(255, ((num >> 8) & 0x00FF) + amt))
    const B = Math.max(0, Math.min(255, (num & 0x0000FF) + amt))
    return `#${(0x1000000 + R * 0x10000 + G * 0x100 + B).toString(16).slice(1)}`
  }

  watchEffect(() => {
    if (branding.value) {
      applyTheme(branding.value)
    }
  })

  return {
    isLoaded,
    branding,
    applyTheme,
  }
}
```

### Composable: useValidation.ts

```typescript
import { ref, computed } from 'vue'

// Regex patterns para México
const PATTERNS = {
  RFC_FISICA: /^[A-ZÑ&]{4}\d{6}[A-V1-9][A-Z1-9][0-9A]$/,
  RFC_MORAL: /^[A-ZÑ&]{3}\d{6}[A-V1-9][A-Z1-9][0-9A]$/,
  CURP: /^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d$/,
  PHONE_MX: /^\d{10}$/,
  POSTAL_CODE: /^\d{5}$/,
  EMAIL: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
  INE_CLAVE: /^[A-Z]{6}\d{8}[HM]\d{3}$/,
}

export function useValidation() {
  const errors = ref<Record<string, string>>({})
  
  const validateRFC = (value: string, type: 'FISICA' | 'MORAL' = 'FISICA'): boolean => {
    const pattern = type === 'FISICA' ? PATTERNS.RFC_FISICA : PATTERNS.RFC_MORAL
    const isValid = pattern.test(value.toUpperCase())
    
    if (!isValid) {
      errors.value.rfc = 'RFC inválido. Verifica la estructura.'
    } else {
      delete errors.value.rfc
    }
    
    return isValid
  }
  
  const validateCURP = (value: string): boolean => {
    const isValid = PATTERNS.CURP.test(value.toUpperCase())
    
    if (!isValid) {
      errors.value.curp = 'CURP inválido. Debe tener 18 caracteres.'
    } else {
      // Validar dígito verificador
      const curp = value.toUpperCase()
      const calculatedDigit = calculateCURPVerificationDigit(curp.slice(0, 17))
      if (curp[17] !== calculatedDigit) {
        errors.value.curp = 'CURP inválido. El dígito verificador no coincide.'
        return false
      }
      delete errors.value.curp
    }
    
    return isValid
  }
  
  const validatePhone = (value: string): boolean => {
    const cleaned = value.replace(/\D/g, '')
    const isValid = PATTERNS.PHONE_MX.test(cleaned)
    
    if (!isValid) {
      errors.value.phone = 'Ingresa un número de 10 dígitos.'
    } else {
      delete errors.value.phone
    }
    
    return isValid
  }
  
  const validatePostalCode = (value: string): boolean => {
    const isValid = PATTERNS.POSTAL_CODE.test(value)
    
    if (!isValid) {
      errors.value.postal_code = 'Código postal inválido (5 dígitos).'
    } else {
      delete errors.value.postal_code
    }
    
    return isValid
  }
  
  const validateAge = (birthDate: string, minAge: number = 18, maxAge: number = 70): boolean => {
    const birth = new Date(birthDate)
    const today = new Date()
    let age = today.getFullYear() - birth.getFullYear()
    const monthDiff = today.getMonth() - birth.getMonth()
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
      age--
    }
    
    if (age < minAge) {
      errors.value.birth_date = `Debes tener al menos ${minAge} años.`
      return false
    }
    
    if (age > maxAge) {
      errors.value.birth_date = `La edad máxima es ${maxAge} años.`
      return false
    }
    
    delete errors.value.birth_date
    return true
  }
  
  const validateRequired = (value: any, field: string, label: string): boolean => {
    const isEmpty = value === null || value === undefined || value === '' || 
                   (Array.isArray(value) && value.length === 0)
    
    if (isEmpty) {
      errors.value[field] = `${label} es requerido.`
      return false
    }
    
    delete errors.value[field]
    return true
  }
  
  const clearErrors = () => {
    errors.value = {}
  }
  
  const hasErrors = computed(() => Object.keys(errors.value).length > 0)
  
  return {
    errors,
    hasErrors,
    validateRFC,
    validateCURP,
    validatePhone,
    validatePostalCode,
    validateAge,
    validateRequired,
    clearErrors,
  }
}

function calculateCURPVerificationDigit(curp17: string): string {
  const dictionary = '0123456789ABCDEFGHIJKLMNÑOPQRSTUVWXYZ'
  let sum = 0
  
  for (let i = 0; i < 17; i++) {
    sum += dictionary.indexOf(curp17[i]) * (18 - i)
  }
  
  const digit = 10 - (sum % 10)
  return digit === 10 ? '0' : digit.toString()
}
```

### Store: application.ts (Pinia)

```typescript
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { applicationService } from '@/services/application.service'
import type { Application, SimulationResult, DynamicData } from '@/types/application'

export const useApplicationStore = defineStore('application', () => {
  // State
  const currentApplication = ref<Application | null>(null)
  const simulation = ref<SimulationResult | null>(null)
  const currentStep = ref(1)
  const totalSteps = ref(8)
  const isLoading = ref(false)
  const isSaving = ref(false)
  
  // Getters
  const progress = computed(() => Math.round((currentStep.value / totalSteps.value) * 100))
  
  const canSubmit = computed(() => {
    if (!currentApplication.value) return false
    return currentApplication.value.status === 'DRAFT' && currentStep.value === totalSteps.value
  })
  
  // Actions
  const createApplication = async (productId: string, amount: number, term: number, frequency: string) => {
    isLoading.value = true
    try {
      const response = await applicationService.create({
        product_id: productId,
        requested_amount: amount,
        term_months: term,
        payment_frequency: frequency,
      })
      currentApplication.value = response.data
      return response.data
    } finally {
      isLoading.value = false
    }
  }
  
  const updateApplication = async (data: Partial<Application>) => {
    if (!currentApplication.value) return
    
    isSaving.value = true
    try {
      const response = await applicationService.update(currentApplication.value.id, data)
      currentApplication.value = { ...currentApplication.value, ...response.data }
    } finally {
      isSaving.value = false
    }
  }
  
  const saveStep = async (stepData: Record<string, any>) => {
    await updateApplication({
      dynamic_data: {
        ...currentApplication.value?.dynamic_data,
        ...stepData,
      }
    })
  }
  
  const runSimulation = async (productId: string, amount: number, term: number, frequency: string) => {
    try {
      const response = await applicationService.simulate({
        product_id: productId,
        amount,
        term_months: term,
        payment_frequency: frequency,
      })
      simulation.value = response.data
      return response.data
    } catch (error) {
      console.error('Simulation failed:', error)
      throw error
    }
  }
  
  const submitApplication = async () => {
    if (!currentApplication.value || !canSubmit.value) return
    
    isLoading.value = true
    try {
      const response = await applicationService.submit(currentApplication.value.id)
      currentApplication.value = response.data
      return response.data
    } finally {
      isLoading.value = false
    }
  }
  
  const nextStep = () => {
    if (currentStep.value < totalSteps.value) {
      currentStep.value++
      saveProgress()
    }
  }
  
  const prevStep = () => {
    if (currentStep.value > 1) {
      currentStep.value--
    }
  }
  
  const goToStep = (step: number) => {
    if (step >= 1 && step <= totalSteps.value) {
      currentStep.value = step
    }
  }
  
  const saveProgress = () => {
    if (currentApplication.value) {
      localStorage.setItem(`app_progress_${currentApplication.value.id}`, JSON.stringify({
        step: currentStep.value,
        timestamp: Date.now(),
      }))
    }
  }
  
  const restoreProgress = () => {
    if (currentApplication.value) {
      const saved = localStorage.getItem(`app_progress_${currentApplication.value.id}`)
      if (saved) {
        const { step } = JSON.parse(saved)
        currentStep.value = step
      }
    }
  }
  
  const reset = () => {
    currentApplication.value = null
    simulation.value = null
    currentStep.value = 1
    isLoading.value = false
    isSaving.value = false
  }
  
  return {
    // State
    currentApplication,
    simulation,
    currentStep,
    totalSteps,
    isLoading,
    isSaving,
    // Getters
    progress,
    canSubmit,
    // Actions
    createApplication,
    updateApplication,
    saveStep,
    runSimulation,
    submitApplication,
    nextStep,
    prevStep,
    goToStep,
    restoreProgress,
    reset,
  }
})
```

---

# PARTE B: FLUJO DE PANTALLAS UI/UX

---

## FILOSOFÍA DE DISEÑO

El diseño propuesto rompe con el paradigma tradicional de formularios bancarios. Se basa en tres pilares:

### 1. CONVERSACIONAL
- Cada pantalla hace UNA sola pregunta principal
- El usuario avanza como si estuviera en un chat
- Reduce la carga cognitiva

### 2. THUMB-FIRST (Mobile Priority)
- Todos los botones de acción están en la zona inferior
- Áreas táctiles mínimo de 44x44px
- Inputs grandes y fáciles de tocar

### 3. PROGRESIVO
- No se muestra TODO el formulario de inicio
- Se revela paso a paso
- Progreso visible y motivador

---

## DISEÑO RESPONSIVO: WEB vs MOBILE

El sistema debe funcionar perfectamente en ambas plataformas con diseños optimizados para cada una.

### Diferencias Clave Web vs Mobile

| Aspecto | Mobile | Web/Desktop |
|---------|--------|-------------|
| **Layout** | Single column, full width | Multi-column, sidebar navigation |
| **Navegación** | Bottom sticky buttons | Sidebar con progress + top actions |
| **Formularios** | 1-2 campos por pantalla | 4-6 campos agrupados en cards |
| **Progress** | Top bar linear | Sidebar vertical con checkmarks |
| **Simulador** | Full screen takeover | Card integrada en landing |
| **Actions** | Bottom sticky footer | Inline buttons + floating actions |

### Layout Web: Landing con Simulador Integrado

```
┌────────────────────────────────────────────────────────────────┐
│ [LOGO]     Productos  Simulador  Nosotros    [Login] [CTA]     │ ← Nav
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  ┌──────────────────────────┐  ┌────────────────────────────┐  │
│  │                          │  │    SIMULA TU CRÉDITO       │  │
│  │  Tu crédito aprobado     │  │                            │  │
│  │  en minutos              │  │  Monto: $85,000           │  │
│  │                          │  │  ═══════●════════          │  │
│  │  Sin papeleos, sin       │  │                            │  │
│  │  filas, sin complicaciones│  │  Plazo: [6][12][●18][24]  │  │
│  │                          │  │                            │  │
│  │  [Comenzar →] [Requisitos]│  │  Frecuencia:               │  │
│  │                          │  │  [Sem][Quin][●Mes]         │  │
│  │  ┌────┐ ┌────┐ ┌────┐   │  │                            │  │
│  │  │24hr│ │ 0% │ │48mo│   │  │  ┌────────────────────────┐│  │
│  │  │resp│ │com │ │plaz│   │  │  │ Pago mensual          ││  │
│  │  └────┘ └────┘ └────┘   │  │  │      $5,832.45        ││  │
│  │                          │  │  │ CAT: 58.5%            ││  │
│  └──────────────────────────┘  │  └────────────────────────┘│  │
│                                │                            │  │
│                                │  [████ SOLICITAR ████]     │  │
│                                └────────────────────────────┘  │
└────────────────────────────────────────────────────────────────┘
```

### Layout Web: Wizard de Onboarding con Sidebar

```
┌────────────────────────────────────────────────────────────────┐
│ [LOGO]                              [Guardado ✓]  [Ayuda] [👤] │
├──────────────────┬─────────────────────────────────────────────┤
│                  │                                             │
│  TU PROGRESO     │     ¿Dónde vives?                          │
│                  │     Paso 3 de 8 • Domicilio                 │
│  ✓ Datos person. │     ─────────────────────────────────────   │
│  ✓ Identificación│                                             │
│  ● Domicilio     │     ┌─────────────────────────────────────┐ │
│  ○ Info laboral  │     │ UBICACIÓN                           │ │
│  ○ Tu crédito    │     │                                     │ │
│  ○ Documentos    │     │ CP: [29165✓]  Colonia: [Jardines▼]  │ │
│  ○ Referencias   │     │                                     │ │
│  ○ Revisión      │     │ Municipio: Chiapa de Corzo          │ │
│                  │     │ Estado: Chiapas                     │ │
│                  │     └─────────────────────────────────────┘ │
│                  │                                             │
│                  │     ┌─────────────────────────────────────┐ │
│                  │     │ DIRECCIÓN                           │ │
│                  │     │                                     │ │
│                  │     │ Calle: [AV. REFORMA_____________]   │ │
│  ─────────────── │     │                                     │ │
│  ¿Necesitas      │     │ No. Ext: [123]  No. Int: [Depto 5]  │ │
│  ayuda?          │     │                                     │ │
│                  │     │ Tipo: (●)Propia (○)Rentada (○)Fam   │ │
│  [Contactar]     │     │                                     │ │
│                  │     │ Años viviendo: [3] años             │ │
│                  │     └─────────────────────────────────────┘ │
│                  │                                             │
│                  │            [← Anterior]  [Continuar →]      │
└──────────────────┴─────────────────────────────────────────────┘
```

### Layout Web: Dashboard del Usuario

```
┌────────────────────────────────────────────────────────────────┐
│ [LOGO]                    🔔  │  Juan Carlos  │  Cerrar sesión │
├─────────────┬──────────────────────────────────────────────────┤
│             │                                                  │
│  🏠 Inicio  │   ¡Hola, Juan! 👋                                │
│             │   Aquí está el estado de tu solicitud            │
│  📄 Solic.  │                              [+ Nueva Solicitud] │
│             │   ─────────────────────────────────────────────  │
│  👤 Perfil  │                                                  │
│             │   ┌────────────────────────────────────────────┐ │
│  📁 Docs    │   │  EN REVISIÓN          Folio: LEN-2026-0042 │ │
│             │   │                                            │ │
│             │   │  Crédito Personal              $85,000 MXN │ │
│             │   │  18 meses • Mensual                        │ │
│             │   │                                            │ │
│             │   │  ●────●────○────○                          │ │
│             │   │  Enviada  Revisión  Docs  Aprobada         │ │
│             │   │                                            │ │
│             │   │  Última actualización: Hace 2 horas        │ │
│             │   └────────────────────────────────────────────┘ │
│             │                                                  │
│             │   ┌──────────┐ ┌──────────┐ ┌──────────┐        │
│             │   │ $5,832   │ │ 3 de 4   │ │ 24 hrs   │        │
│             │   │ Pago/mes │ │ Docs     │ │ Estimado │        │
│             │   └──────────┘ └──────────┘ └──────────┘        │
│             │                                                  │
└─────────────┴──────────────────────────────────────────────────┘
```

### Layout Web: Panel Administrativo (Mesa de Control)

```
┌────────────────────────────────────────────────────────────────┐
│ [LOGO] Admin                   🔍 Buscar...    🔔  Admin  ▼    │
├─────────────┬──────────────────────────────────────────────────┤
│             │                                                  │
│ PRINCIPAL   │   Mesa de Control                                │
│ ▫ Dashboard │   Gestión de solicitudes de crédito              │
│ ▪ Solicitud │   ─────────────────────────────────────────────  │
│ ▫ Clientes  │                                                  │
│             │   ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌────────┐│
│ CONFIG      │   │ NUEVAS  │ │REVISIÓN │ │  DOCS   │ │APROBADA││
│ ▫ Productos │   │   (5)   │ │   (3)   │ │   (2)   │ │  (8)   ││
│ ▫ Branding  │   │         │ │         │ │         │ │        ││
│ ▫ Webhooks  │   │┌───────┐│ │┌───────┐│ │┌───────┐│ │        ││
│             │   ││LEN-045││ ││LEN-042││ ││LEN-038││ │        ││
│             │   ││María G││ ││Juan P.││ ││Ana M. ││ │        ││
│             │   ││$120k  ││ ││$85k   ││ ││$350k  ││ │        ││
│             │   │└───────┘│ │└───────┘│ │└───────┘│ │        ││
│             │   │         │ │         │ │         │ │        ││
│             │   │┌───────┐│ │         │ │         │ │        ││
│             │   ││LEN-044││ │         │ │         │ │        ││
│             │   ││Roberto││ │         │ │         │ │        ││
│             │   ││$50k   ││ │         │ │         │ │        ││
│             │   │└───────┘│ │         │ │         │ │        ││
│             │   └─────────┘ └─────────┘ └─────────┘ └────────┘│
└─────────────┴──────────────────────────────────────────────────┘
```

### Breakpoints CSS (Tailwind)

```css
/* Mobile First */
@media (min-width: 640px) { /* sm */ }
@media (min-width: 768px) { /* md - Tablet */ }
@media (min-width: 1024px) { /* lg - Desktop */ }
@media (min-width: 1280px) { /* xl - Large Desktop */ }
```

### Composable: useResponsive.ts

```typescript
import { ref, onMounted, onUnmounted } from 'vue'

export function useResponsive() {
  const isMobile = ref(false)
  const isTablet = ref(false)
  const isDesktop = ref(false)
  
  const updateBreakpoint = () => {
    const width = window.innerWidth
    isMobile.value = width < 768
    isTablet.value = width >= 768 && width < 1024
    isDesktop.value = width >= 1024
  }
  
  onMounted(() => {
    updateBreakpoint()
    window.addEventListener('resize', updateBreakpoint)
  })
  
  onUnmounted(() => {
    window.removeEventListener('resize', updateBreakpoint)
  })
  
  return { isMobile, isTablet, isDesktop }
}
```

---

## MÓDULO 1: LANDING Y SIMULADOR

### Pantalla 1.1: Landing Hero (Mobile)

**Propósito:** Captar atención, comunicar propuesta de valor, iniciar simulación.

**Layout Mobile:**
```
┌─────────────────────────────┐
│ [Logo Tenant]               │ ← Dinámico
├─────────────────────────────┤
│                             │
│   Tu crédito                │ ← H1 grande
│   en 5 minutos              │
│                             │
│   Sin papeleos,             │ ← Subtítulo
│   100% digital              │
│                             │
│   [Ilustración SVG]         │ ← Animada
│                             │
├─────────────────────────────┤
│ ┌─────────────────────────┐ │
│ │ ¿Cuánto necesitas?      │ │ ← Card flotante
│ │                         │ │
│ │ $50,000 ────●────       │ │ ← Slider
│ │                         │ │
│ │ Cuota desde $2,500/mes  │ │
│ └─────────────────────────┘ │
├─────────────────────────────┤
│ [████ COMENZAR ████]        │ ← Sticky bottom
└─────────────────────────────┘
```

**Comportamiento:**
- Al cargar, fetch a `/api/config` para obtener branding
- Aplicar colores dinámicamente via CSS variables
- Slider muestra cálculo aproximado en tiempo real
- Botón lleva a `/simulador`

---

### Pantalla 1.2: Simulador Completo

**Propósito:** Configurar crédito y ver costo real en tiempo real.

**Layout:**
```
┌─────────────────────────────┐
│ ← [Logo]                    │
├─────────────────────────────┤
│                             │
│ ¿Cuánto necesitas?          │
│                             │
│ $5,000 ═══════●═══ $500,000 │
│         $85,000             │
│                             │
├─────────────────────────────┤
│ ¿En cuánto tiempo?          │
│                             │
│ [6] [12] [●18] [24] [36]    │ ← Chips
│                             │
├─────────────────────────────┤
│ ¿Cada cuándo pagas?         │
│                             │
│ [Semanal] [Quincenal] [●Mes]│ ← Toggle
│                             │
├─────────────────────────────┤
│ ┌─────────────────────────┐ │
│ │ Tu pago mensual         │ │
│ │                         │ │
│ │    $5,832.45           │ │ ← Grande, animado
│ │                         │ │
│ │ Total a pagar  $104,984 │ │
│ │ Intereses      $19,984  │ │
│ │ CAT            58.5%    │ │
│ │                         │ │
│ │ [Ver desglose ▼]        │ │ ← Expandible
│ └─────────────────────────┘ │
│                             │
├─────────────────────────────┤
│ [████ SOLICITAR AHORA ████] │
└─────────────────────────────┘
```

**Tabla de Amortización (Expandible):**
```
┌─────────────────────────────────────────────┐
│ # │ Fecha    │ Capital │ Interés │ Total   │
├───┼──────────┼─────────┼─────────┼─────────┤
│ 1 │ 01/Feb   │ $2,082  │ $3,750  │ $5,832  │
│ 2 │ 01/Mar   │ $2,160  │ $3,672  │ $5,832  │
│...│ ...      │ ...     │ ...     │ ...     │
└─────────────────────────────────────────────┘
```

---

## MÓDULO 2: AUTENTICACIÓN MÚLTIPLE (SMS, WhatsApp, Email)

El sistema ofrece tres métodos de verificación OTP para máxima accesibilidad.

### Pantalla 2.1: Selección de Método

```
┌─────────────────────────────┐
│ [Logo]                      │
├─────────────────────────────┤
│                             │
│    Ingresa a tu cuenta      │
│                             │
│  Elige cómo verificarte:    │
│                             │
│  ┌─────────────────────────┐│
│  │ 📱 Celular (SMS)        ││ ← Opción 1
│  │ Código por mensaje      ││
│  └─────────────────────────┘│
│                             │
│  ┌─────────────────────────┐│
│  │ 💬 WhatsApp             ││ ← Opción 2
│  │ Código por WhatsApp     ││
│  └─────────────────────────┘│
│                             │
│  ┌─────────────────────────┐│
│  │ 📧 Correo Electrónico   ││ ← Opción 3
│  │ Código a tu email       ││
│  └─────────────────────────┘│
│                             │
│  ¿Ya tienes cuenta?         │
│  [Inicia sesión con pass]   │
└─────────────────────────────┘
```

### Pantalla 2.2a: Ingreso de Celular (SMS/WhatsApp)

```
┌─────────────────────────────┐
│ ← [Logo]                    │
├─────────────────────────────┤
│                             │
│  📱 ¿Cuál es tu número?     │
│                             │
│  +52 │ (55) 1234-5678       │ ← Input con máscara
│                             │
│  Te enviaremos un código    │
│  de 6 dígitos por SMS       │
│                             │
├─────────────────────────────┤
│ [████ ENVIAR CÓDIGO ████]   │
└─────────────────────────────┘
```

### Pantalla 2.2b: Ingreso de Email

```
┌─────────────────────────────┐
│ ← [Logo]                    │
├─────────────────────────────┤
│                             │
│  📧 ¿Cuál es tu correo?     │
│                             │
│  [juan.perez@gmail.com   ]  │ ← Input email
│                             │
│  Te enviaremos un código    │
│  de verificación            │
│                             │
│  ┌─────────────────────────┐│
│  │ ℹ️ Revisa tu bandeja de ││
│  │ entrada y spam          ││
│  └─────────────────────────┘│
│                             │
├─────────────────────────────┤
│ [████ ENVIAR CÓDIGO ████]   │
└─────────────────────────────┘
```

### Pantalla 2.3: Verificación OTP (Universal)

```
┌─────────────────────────────┐
│ ← [Logo]                    │
├─────────────────────────────┤
│                             │
│  🔐 Verifica tu código      │
│                             │
│  Enviado a:                 │
│  +52 55 1234 5678           │ (o email)
│                             │
│   ┌─┐ ┌─┐ ┌─┐ ┌─┐ ┌─┐ ┌─┐   │
│   │5│ │2│ │8│ │ │ │ │ │ │   │
│   └─┘ └─┘ └─┘ └─┘ └─┘ └─┘   │
│                             │
│  Expira en 4:32             │
│                             │
│  ¿No lo recibiste?          │
│  [SMS] [WhatsApp] [Email]   │ ← Cambiar método
│                             │
└─────────────────────────────┘
```

**Comportamiento:**
- Auto-focus en primer box
- Al escribir, avanza automáticamente
- Al completar 6 dígitos, valida sin botón
- Si error: shake animation + clear
- Timer de 5 minutos para expiración
- Opción de cambiar método de envío
- Máximo 3 intentos antes de bloqueo temporal

### Backend: OtpService.php

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OtpService
{
    private const OTP_LENGTH = 6;
    private const OTP_EXPIRY_MINUTES = 5;
    private const MAX_ATTEMPTS = 3;
    
    public function send(string $destination, string $method): array
    {
        // Generar código
        $code = $this->generateCode();
        
        // Guardar en cache con expiración
        $key = $this->getCacheKey($destination);
        Cache::put($key, [
            'code' => $code,
            'attempts' => 0,
            'method' => $method,
            'created_at' => now(),
        ], now()->addMinutes(self::OTP_EXPIRY_MINUTES));
        
        // Enviar según método
        match($method) {
            'sms' => $this->sendSms($destination, $code),
            'whatsapp' => $this->sendWhatsApp($destination, $code),
            'email' => $this->sendEmail($destination, $code),
            default => throw new \InvalidArgumentException("Invalid method: {$method}"),
        };
        
        return [
            'success' => true,
            'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES)->toIso8601String(),
            'method' => $method,
        ];
    }
    
    public function verify(string $destination, string $code): array
    {
        $key = $this->getCacheKey($destination);
        $data = Cache::get($key);
        
        if (!$data) {
            return ['success' => false, 'error' => 'OTP_EXPIRED'];
        }
        
        if ($data['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($key);
            return ['success' => false, 'error' => 'MAX_ATTEMPTS_EXCEEDED'];
        }
        
        if ($data['code'] !== $code) {
            $data['attempts']++;
            Cache::put($key, $data, now()->addMinutes(self::OTP_EXPIRY_MINUTES));
            return [
                'success' => false,
                'error' => 'INVALID_CODE',
                'attempts_remaining' => self::MAX_ATTEMPTS - $data['attempts'],
            ];
        }
        
        // Código válido
        Cache::forget($key);
        
        return ['success' => true];
    }
    
    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }
    
    private function getCacheKey(string $destination): string
    {
        return 'otp:' . hash('sha256', $destination);
    }
    
    private function sendSms(string $phone, string $code): void
    {
        // Integración con Twilio/MessageBird
        // dispatch(new SendSmsJob($phone, "Tu código es: {$code}"));
    }
    
    private function sendWhatsApp(string $phone, string $code): void
    {
        // Integración con WhatsApp Business API
        // dispatch(new SendWhatsAppJob($phone, $code));
    }
    
    private function sendEmail(string $email, string $code): void
    {
        // Enviar email con template
        // Mail::to($email)->queue(new OtpMail($code));
    }
}
```

---

## MÓDULO 3: ONBOARDING WIZARD

### Estructura General de Cada Step

```
┌─────────────────────────────┐
│ [Logo]        Paso 2 de 8   │ ← Header con progress
│ ████████░░░░░░░░░░░░░  25%  │ ← Progress bar
├─────────────────────────────┤
│                             │
│                             │
│    [CONTENIDO DEL STEP]     │ ← Área principal
│                             │
│                             │
├─────────────────────────────┤
│ [Anterior]   [Continuar →]  │ ← Footer sticky
└─────────────────────────────┘
```

---

### PASO 1: Datos Básicos

**Título:** "¿Cómo te llamas?"

| Campo | Tipo | Validación | UX |
|-------|------|------------|-----|
| Nombre(s) | text | required, min:2, alpha_spaces | Autofocus, UPPERCASE |
| Primer Apellido | text | required, min:2 | UPPERCASE |
| Segundo Apellido | text | optional | UPPERCASE |
| Fecha Nacimiento | date | required, edad ≥18 | Date picker nativo |
| Género | radio | required | [Masculino] [Femenino] |
| Estado Civil | select | required | Soltero, Casado, Unión Libre, Divorciado, Viudo |
| Email | email | required, valid | Autocompletar |

---

### PASO 2: Identificación Fiscal

**Título:** "Tu identificación oficial"

| Campo | Tipo | Validación | UX |
|-------|------|------------|-----|
| CURP | text | 18 chars, regex CURP | Máscara, uppercase, helper con link a RENAPO |
| RFC | text | 13 chars, regex RFC | Auto-sugerir base desde nombre/fecha |
| Clave de Elector | text | 18 chars alfanum | Helper: "Está al reverso de tu INE" |
| Número OCR | text | 13 dígitos | Helper: "Debajo del código de barras" |
| Folio INE | text | 20 dígitos | |

**Validación en tiempo real:**
- CURP: Al completar 18 chars, validar estructura + dígito verificador
- RFC: Validar estructura y homoclave

---

### PASO 3: Domicilio

**Título:** "¿Dónde vives?"

| Campo | Tipo | Validación | UX |
|-------|------|------------|-----|
| Código Postal | number | 5 dígitos | **PRIMER CAMPO** - Al completar, consulta API SEPOMEX |
| Colonia | select | required | Dropdown con colonias del CP (searchable) |
| Municipio | readonly | - | Autocompletado |
| Estado | readonly | - | Autocompletado |
| Calle | text | required, min:3 | UPPERCASE |
| No. Exterior | text | required | Permite "S/N" |
| No. Interior | text | optional | |
| Tipo Vivienda | select | required | Propia, Rentada, Familiar, Hipotecada |
| Años viviendo | number | required, ≥0 | Stepper +/- 1 |

**Flujo especial:**
1. Usuario ingresa CP
2. Loading spinner mientras consulta
3. Se llenan Colonia (dropdown), Municipio, Estado automáticamente
4. Usuario completa resto de dirección

---

### PASO 4: Información Laboral

**Título:** "Cuéntanos sobre tu trabajo"

| Campo | Tipo | Validación | UX |
|-------|------|------------|-----|
| Situación Laboral | select | required | Empleado, Independiente, Jubilado, Sin empleo |
| Nombre Empresa | text | required if Empleado | UPPERCASE |
| Giro/Sector | select | required if Empleado | Comercio, Servicios, Manufactura, Gobierno, etc. |
| Puesto | text | required | |
| Antigüedad (meses) | number | required, ≥0 | Slider o input |
| Ingreso Mensual | currency | required, >0 | Máscara: $XX,XXX.XX |
| Otros Ingresos | currency | optional | |
| Teléfono Empresa | phone | optional | |

**Condicional:** Si producto es NOMINA, todos los campos son obligatorios.

---

### PASO 5: Datos Financieros del Crédito

**Título:** "Personaliza tu crédito"

Se muestra el simulador preconfigurado con los datos seleccionados al inicio, permitiendo ajustar:

- Monto solicitado (dentro de límites)
- Plazo (opciones según producto)
- Frecuencia de pago

Incluye resumen de:
- Pago periódico
- Total a pagar
- CAT

---

### PASO 6: Documentos

**Título:** "Sube tus documentos"

**Lista de documentos según producto:**

```
┌─────────────────────────────┐
│ INE (frente) *              │
│ ┌─────────────────────────┐ │
│ │  📷 Tomar foto          │ │ ← Captura directa
│ │  📁 Subir archivo       │ │
│ └─────────────────────────┘ │
│ ✓ ine_frente.jpg (2.1 MB)   │ ← Preview con check
├─────────────────────────────┤
│ INE (reverso) *             │
│ ┌─────────────────────────┐ │
│ │  □  Pendiente           │ │
│ └─────────────────────────┘ │
├─────────────────────────────┤
│ Comprobante de domicilio *  │
│ ┌─────────────────────────┐ │
│ │  □  Pendiente           │ │
│ │  (Máx. 3 meses)         │ │
│ └─────────────────────────┘ │
└─────────────────────────────┘
```

**Comportamiento:**
- Preview de imagen/PDF
- Indicador de tamaño máximo (5MB)
- Formatos: JPG, PNG, PDF
- OCR automático para INE (extraer datos)
- Barra de progreso de upload

---

### PASO 7: Referencias Personales

**Título:** "¿A quién podemos contactar?"

**Requisito:** Mínimo 2 referencias (1 familiar + 1 no familiar)

**Por cada referencia:**

| Campo | Tipo | Validación |
|-------|------|------------|
| Nombre Completo | text | required, min:5 |
| Teléfono | phone | required, 10 dígitos, diferente al solicitante |
| Parentesco | select | Padre/Madre, Hermano/a, Cónyuge, Hijo/a, Amigo/a, Compañero trabajo, Otro |
| Tipo | radio | [Personal] [Laboral] |

**Interfaz:**
```
┌─────────────────────────────┐
│ Referencia 1 (Familiar)     │
│ ─────────────────────────── │
│ Nombre: [________________]  │
│ Tel:    [________________]  │
│ Es tu:  [Hermano/a      ▼]  │
├─────────────────────────────┤
│ Referencia 2 (No familiar)  │
│ ─────────────────────────── │
│ Nombre: [________________]  │
│ Tel:    [________________]  │
│ Es tu:  [Compañero trabajo▼]│
├─────────────────────────────┤
│ [+ Agregar otra referencia] │
└─────────────────────────────┘
```

---

### PASO 8: Revisión y Firma

**Título:** "Revisa y confirma tu solicitud"

**Secciones colapsables:**
- Datos Personales (resumen)
- Domicilio (resumen)
- Información Laboral (resumen)
- Documentos (thumbnails)
- Referencias (lista)
- Condiciones del Crédito

**Aceptación:**
```
□ He leído y acepto el Aviso de Privacidad
□ Autorizo la consulta de mi historial crediticio
  en Buró de Crédito y Círculo de Crédito
□ Acepto los Términos y Condiciones del crédito

[Firma digital aquí]
┌─────────────────────────────┐
│                             │
│     [Dibujar firma]         │
│                             │
└─────────────────────────────┘

[████ ENVIAR SOLICITUD ████]
```

---

## MÓDULO 4: DASHBOARD DEL SOLICITANTE

### Vista de Estado de Solicitud

```
┌─────────────────────────────────────────────┐
│ [Logo]                    Hola, Juan 👋    │
├─────────────────────────────────────────────┤
│                                             │
│   Tu solicitud                              │
│   LEN-2026-00042                            │
│                                             │
│   ┌─────────────────────────────────────┐   │
│   │ ● Enviada                           │   │
│   │ ○ En revisión                       │   │
│   │ ○ Documentos verificados            │   │
│   │ ○ Aprobada                          │   │
│   └─────────────────────────────────────┘   │
│                                             │
│   Estado: EN REVISIÓN                       │
│   Actualizado: Hace 2 horas                 │
│                                             │
├─────────────────────────────────────────────┤
│   Resumen                                   │
│   ───────────────────────                   │
│   Monto:     $85,000 MXN                    │
│   Plazo:     18 meses                       │
│   Pago:      $5,832/mes                     │
│                                             │
├─────────────────────────────────────────────┤
│   [Ver detalle]  [Descargar PDF]            │
│                                             │
│   ¿Necesitas ayuda?                         │
│   📞 800-123-4567                           │
│   💬 WhatsApp                               │
└─────────────────────────────────────────────┘
```

---

## MÓDULO 5: PANEL ADMINISTRATIVO

### Dashboard Admin (Mesa de Control)

**Vista Kanban:**
```
┌──────────────────────────────────────────────────────────────┐
│ [Logo Admin]                    🔔 │ Admin │ Cerrar sesión   │
├──────────────────────────────────────────────────────────────┤
│ Solicitudes │ Productos │ Configuración │ Reportes           │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌──────────┐│
│ │ NUEVAS (5)  │ │ REVISIÓN(3) │ │ DOCS (2)    │ │APROBADAS │││
│ │             │ │             │ │             │ │   (8)    ││
│ │ ┌─────────┐ │ │ ┌─────────┐ │ │ ┌─────────┐ │ │          ││
│ │ │LEN-042  │ │ │ │LEN-039  │ │ │ │LEN-035  │ │ │          ││
│ │ │Juan P.  │ │ │ │María G. │ │ │ │Pedro S. │ │ │          ││
│ │ │$85,000  │ │ │ │$50,000  │ │ │ │$120,000 │ │ │          ││
│ │ │Hace 5min│ │ │ │Hace 1hr │ │ │ │Esperando│ │ │          ││
│ │ └─────────┘ │ │ └─────────┘ │ │ └─────────┘ │ │          ││
│ │             │ │             │ │             │ │          ││
│ └─────────────┘ └─────────────┘ └─────────────┘ └──────────┘│
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

**Detalle de Solicitud para Analista:**
- Todos los datos del solicitante
- Visualizador de documentos
- Validación de listas negras (integración PLD)
- Comentarios internos
- Botones: [Solicitar Docs] [Rechazar] [Aprobar]

---

# PARTE C: ESPECIFICACIONES DE FORMULARIOS

## Campos Dinámicos por Tipo de Producto

### Producto: ARRENDAMIENTO

```json
{
  "extra_fields": [
    {
      "name": "asset_type",
      "label": "Tipo de Activo",
      "type": "select",
      "options": [
        {"value": "AUTO_NUEVO", "label": "Automóvil Nuevo"},
        {"value": "AUTO_SEMINUEVO", "label": "Automóvil Seminuevo"},
        {"value": "MAQUINARIA", "label": "Maquinaria Industrial"},
        {"value": "EQUIPO_COMPUTO", "label": "Equipo de Cómputo"},
        {"value": "EQUIPO_MEDICO", "label": "Equipo Médico"},
        {"value": "OTRO", "label": "Otro"}
      ],
      "required": true
    },
    {
      "name": "asset_brand",
      "label": "Marca",
      "type": "text",
      "required": true,
      "maxLength": 50
    },
    {
      "name": "asset_model",
      "label": "Modelo",
      "type": "text",
      "required": true,
      "maxLength": 50
    },
    {
      "name": "asset_year",
      "label": "Año",
      "type": "number",
      "required": true,
      "min": 2018,
      "max": 2027
    },
    {
      "name": "asset_value",
      "label": "Valor del Activo (con IVA)",
      "type": "currency",
      "required": true,
      "min": 50000
    },
    {
      "name": "down_payment_percent",
      "label": "Enganche (%)",
      "type": "number",
      "required": true,
      "min": 10,
      "max": 50,
      "default": 20
    },
    {
      "name": "dealer_name",
      "label": "Nombre de Agencia/Distribuidor",
      "type": "text",
      "required": false
    }
  ]
}
```

### Producto: NOMINA

```json
{
  "extra_fields": [
    {
      "name": "employer_rfc",
      "label": "RFC del Empleador",
      "type": "text",
      "required": true,
      "pattern": "^[A-ZÑ&]{3,4}\\d{6}[A-V1-9][A-Z1-9][0-9A]$"
    },
    {
      "name": "employer_name",
      "label": "Razón Social del Empleador",
      "type": "text",
      "required": true
    },
    {
      "name": "employee_number",
      "label": "Número de Empleado",
      "type": "text",
      "required": false
    },
    {
      "name": "pay_frequency",
      "label": "Frecuencia de Pago de Nómina",
      "type": "select",
      "options": [
        {"value": "WEEKLY", "label": "Semanal"},
        {"value": "BIWEEKLY", "label": "Quincenal"},
        {"value": "MONTHLY", "label": "Mensual"}
      ],
      "required": true
    },
    {
      "name": "contract_type",
      "label": "Tipo de Contrato",
      "type": "select",
      "options": [
        {"value": "INDEFINIDO", "label": "Indefinido"},
        {"value": "TEMPORAL", "label": "Temporal"},
        {"value": "OBRA_DETERMINADA", "label": "Por Obra Determinada"}
      ],
      "required": true
    },
    {
      "name": "has_imss",
      "label": "¿Está dado de alta en IMSS?",
      "type": "boolean",
      "required": true
    },
    {
      "name": "nss",
      "label": "Número de Seguridad Social (NSS)",
      "type": "text",
      "required": false,
      "pattern": "^\\d{11}$"
    }
  ]
}
```

### Producto: PYME

```json
{
  "extra_fields": [
    {
      "name": "business_name",
      "label": "Nombre Comercial",
      "type": "text",
      "required": true
    },
    {
      "name": "business_rfc",
      "label": "RFC de la Empresa",
      "type": "text",
      "required": true
    },
    {
      "name": "business_sector",
      "label": "Giro del Negocio",
      "type": "select",
      "options": [
        {"value": "COMERCIO", "label": "Comercio"},
        {"value": "SERVICIOS", "label": "Servicios"},
        {"value": "MANUFACTURA", "label": "Manufactura"},
        {"value": "CONSTRUCCION", "label": "Construcción"},
        {"value": "TRANSPORTE", "label": "Transporte"},
        {"value": "AGROPECUARIO", "label": "Agropecuario"},
        {"value": "OTRO", "label": "Otro"}
      ],
      "required": true
    },
    {
      "name": "years_in_business",
      "label": "Años de Operación",
      "type": "number",
      "required": true,
      "min": 0
    },
    {
      "name": "monthly_sales",
      "label": "Ventas Mensuales Promedio",
      "type": "currency",
      "required": true
    },
    {
      "name": "employees_count",
      "label": "Número de Empleados",
      "type": "number",
      "required": true,
      "min": 0
    },
    {
      "name": "loan_purpose",
      "label": "Destino del Crédito",
      "type": "select",
      "options": [
        {"value": "CAPITAL_TRABAJO", "label": "Capital de Trabajo"},
        {"value": "ACTIVO_FIJO", "label": "Adquisición de Activo Fijo"},
        {"value": "EXPANSION", "label": "Expansión del Negocio"},
        {"value": "REFINANCIAMIENTO", "label": "Refinanciamiento"},
        {"value": "OTRO", "label": "Otro"}
      ],
      "required": true
    }
  ]
}
```

---

# PARTE D: JSON PAYLOADS Y WEBHOOKS

## Ejemplo de Payload de Webhook (application.approved)

```json
{
  "event": "application.approved",
  "timestamp": "2026-01-15T14:30:00-06:00",
  "webhook_id": "wh_a1b2c3d4e5f6",
  "data": {
    "folio": "LEN-2026-00042",
    "status": "APPROVED",
    "submitted_at": "2026-01-14T10:15:00-06:00",
    "approved_at": "2026-01-15T14:30:00-06:00",
    
    "product": {
      "id": "prod_xyz123",
      "type": "NOMINA",
      "name": "Crédito de Nómina Express"
    },
    
    "financial": {
      "requested_amount": 85000.00,
      "approved_amount": 85000.00,
      "term_months": 18,
      "payment_frequency": "MONTHLY",
      "simulation": {
        "annual_rate": 45.0,
        "monthly_rate": 3.75,
        "opening_commission": 2125.00,
        "monthly_payment": 5832.45,
        "total_interest": 19946.10,
        "total_amount": 107071.10,
        "cat": 58.5,
        "first_payment_date": "2026-02-15",
        "amortization_table": [
          {
            "number": 1,
            "date": "2026-02-15",
            "opening_balance": 85000.00,
            "principal": 2644.95,
            "interest": 3187.50,
            "iva": 510.00,
            "payment": 6342.45,
            "closing_balance": 82355.05
          }
        ]
      }
    },
    
    "applicant": {
      "id": "app_abc123",
      "type": "PERSONA_FISICA",
      "rfc": "PEGJ900515ABC",
      "curp": "PEGJ900515HCHRNS09",
      
      "personal_data": {
        "first_name": "JUAN",
        "middle_name": "CARLOS",
        "last_name": "PÉREZ",
        "second_last_name": "GARCÍA",
        "birth_date": "1990-05-15",
        "birth_state": "CHIAPAS",
        "gender": "M",
        "nationality": "MEXICANA",
        "marital_status": "SOLTERO",
        "education_level": "LICENCIATURA"
      },
      
      "contact_info": {
        "phone": "5512345678",
        "email": "juan.perez@email.com",
        "secondary_phone": null
      },
      
      "address": {
        "street": "AV. INSURGENTES SUR",
        "ext_number": "1234",
        "int_number": "DEPTO 501",
        "neighborhood": "DEL VALLE",
        "postal_code": "03100",
        "municipality": "BENITO JUÁREZ",
        "city": "CIUDAD DE MÉXICO",
        "state": "CIUDAD DE MÉXICO",
        "country": "MX",
        "housing_type": "RENTADA",
        "years_living": 3
      },
      
      "employment_info": {
        "employment_status": "EMPLEADO",
        "company_name": "TECNOLOGÍA GLOBAL SA DE CV",
        "company_sector": "TECNOLOGÍA",
        "position": "GERENTE DE PROYECTOS",
        "seniority_months": 36,
        "monthly_income": 45000.00,
        "other_income": 5000.00,
        "company_phone": "5555551234",
        "company_rfc": "TGL150101XYZ",
        "contract_type": "INDEFINIDO",
        "has_imss": true,
        "nss": "12345678901"
      },
      
      "kyc_status": "VERIFIED",
      
      "references": [
        {
          "full_name": "MARÍA PÉREZ LÓPEZ",
          "phone": "5598765432",
          "relationship": "FAMILY",
          "type": "PERSONAL"
        },
        {
          "full_name": "ROBERTO SÁNCHEZ MARTÍNEZ",
          "phone": "5511223344",
          "relationship": "COWORKER",
          "type": "WORK"
        }
      ]
    },
    
    "documents": [
      {
        "type": "INE_FRONT",
        "url": "https://cdn.los.com/signed/ine_front_abc123.jpg?token=xyz&expires=3600",
        "status": "VERIFIED",
        "ocr_data": {
          "nombre": "JUAN CARLOS",
          "apellido_paterno": "PÉREZ",
          "apellido_materno": "GARCÍA",
          "clave_elector": "PRGNJN90051509H800",
          "curp": "PEGJ900515HCHRNS09",
          "vigencia": "2030"
        },
        "uploaded_at": "2026-01-14T10:20:00-06:00"
      },
      {
        "type": "INE_BACK",
        "url": "https://cdn.los.com/signed/ine_back_abc123.jpg?token=xyz&expires=3600",
        "status": "VERIFIED",
        "ocr_data": null,
        "uploaded_at": "2026-01-14T10:21:00-06:00"
      },
      {
        "type": "PROOF_ADDRESS",
        "url": "https://cdn.los.com/signed/address_abc123.pdf?token=xyz&expires=3600",
        "status": "VERIFIED",
        "ocr_data": null,
        "uploaded_at": "2026-01-14T10:25:00-06:00"
      },
      {
        "type": "PROOF_INCOME",
        "url": "https://cdn.los.com/signed/income_abc123.pdf?token=xyz&expires=3600",
        "status": "VERIFIED",
        "ocr_data": null,
        "uploaded_at": "2026-01-14T10:28:00-06:00"
      }
    ],
    
    "dynamic_data": {
      "employer_rfc": "TGL150101XYZ",
      "employer_name": "TECNOLOGÍA GLOBAL SA DE CV",
      "employee_number": "EMP-1234",
      "pay_frequency": "BIWEEKLY",
      "contract_type": "INDEFINIDO",
      "has_imss": true,
      "nss": "12345678901"
    }
  }
}
```

---

## Endpoints API Principales

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/config` | Obtener configuración del tenant (branding, productos) |
| POST | `/api/auth/otp/send` | Enviar OTP por SMS/WhatsApp |
| POST | `/api/auth/otp/verify` | Verificar código OTP |
| GET | `/api/products` | Listar productos disponibles |
| GET | `/api/products/{id}` | Detalle de producto |
| POST | `/api/simulator` | Calcular simulación |
| POST | `/api/applicants` | Crear/actualizar solicitante |
| GET | `/api/applicants/me` | Obtener perfil del solicitante |
| POST | `/api/applications` | Crear solicitud |
| PATCH | `/api/applications/{id}` | Actualizar solicitud |
| POST | `/api/applications/{id}/submit` | Enviar solicitud |
| GET | `/api/applications/{id}` | Ver estado de solicitud |
| POST | `/api/documents` | Subir documento |
| DELETE | `/api/documents/{id}` | Eliminar documento |
| GET | `/api/postal-codes/{cp}` | Buscar colonias por CP |

---

## FIN DEL DOCUMENTO

Este documento contiene toda la especificación técnica necesaria para construir el SaaS LOS White-Label. Incluye:

1. ✅ Arquitectura de base de datos completa
2. ✅ Código de referencia para Laravel
3. ✅ Código de referencia para Vue.js
4. ✅ Flujo completo de pantallas UI/UX
5. ✅ Especificación de todos los formularios
6. ✅ Ejemplos de JSON payloads
7. ✅ Lista de endpoints API

**Para comenzar el desarrollo:**
1. Copia la PARTE A completa y pégala en Claude Code
2. Solicita que genere las migraciones de base de datos
3. Luego solicita los controladores y servicios
4. Finalmente, solicita los componentes de Vue.js
