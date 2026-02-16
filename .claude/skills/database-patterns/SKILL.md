---
name: database-patterns
description: Patrones de base de datos PostgreSQL para LendusFind. Usar al crear migraciones, modificar schema o trabajar con JSONB.
---

# Database Patterns

## Cuándo aplica
Seguir estos patrones al crear migraciones, modificar el schema de PostgreSQL, o trabajar con campos JSONB, UUIDs o índices.

## Migration Template

```php
Schema::create('applications', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignUuid('person_id')->nullable()->constrained();
    $table->foreignUuid('product_id')->constrained();
    $table->string('status')->default('DRAFT');
    $table->string('applicant_type')->default('INDIVIDUAL');
    $table->decimal('requested_amount', 15, 2)->nullable();
    $table->integer('requested_term_months')->nullable();
    $table->decimal('interest_rate', 8, 4)->nullable();
    $table->jsonb('metadata')->nullable();
    $table->jsonb('snapshot_data')->nullable();
    $table->timestamp('submitted_at')->nullable();
    $table->softDeletes();
    $table->timestamps();

    $table->index(['tenant_id', 'status']);
    $table->index(['person_id', 'created_at']);
});
```

## Key Conventions

| Pattern | Implementation |
|---------|---------------|
| Primary Keys | `$table->uuid('id')->primary()` — NUNCA auto-increment |
| Foreign Keys | `$table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete()` |
| Enums | `$table->string('status')` — NUNCA `$table->enum()` |
| Money | `$table->decimal('amount', 15, 2)` |
| Percentages | `$table->decimal('rate', 8, 4)` |
| JSON | `$table->jsonb('field')->nullable()` |
| Booleans | `$table->boolean('is_active')->default(true)` |
| Dates | `$table->timestamp('field_at')->nullable()` |
| Audit | `$table->timestamps()` + `$table->softDeletes()` |

## Main Tables (26 models)

| Category | Tables |
|----------|--------|
| **Tenancy** | `tenants`, `tenant_brandings`, `tenant_api_configs` |
| **Auth** | `staff_accounts`, `staff_profiles`, `applicant_accounts`, `applicant_identities` |
| **Person** | `persons`, `addresses`, `person_identifications`, `person_employments`, `person_references`, `bank_accounts` |
| **Application** | `applications`, `application_status_histories`, `documents`, `products` |
| **KYC** | `data_verifications` |
| **Notifications** | `notification_templates`, `notification_logs`, `notification_preferences`, `otp_codes`, `otp_requests`, `sms_logs` |
| **Audit** | `audit_logs`, `api_logs` |

## JSONB Usage Patterns

Campos JSONB comunes y su uso:

```php
// En el modelo
protected $casts = [
    'metadata' => 'array',          // Datos flexibles genéricos
    'snapshot_data' => 'array',     // Snapshot de datos al momento de crear
    'settings' => 'array',         // Configuración del tenant
    'extra_config' => 'array',     // Config adicional de integraciones
    'risk_data' => 'array',        // Datos de evaluación de riesgo
    'counter_offer' => 'array',    // Contraoferta estructurada
    'verification_checklist' => 'array',  // Checklist de verificación
];
```

Queries sobre JSONB:
```php
// PostgreSQL JSON operators
$query->whereJsonContains('metadata->tags', 'priority');
$query->where('extra_config->host', 'smtp.gmail.com');
```

## Encrypted Fields

`TenantApiConfig` almacena credenciales encriptadas usando accessors/mutators individuales:

```php
// En el modelo — cada campo sensible tiene su propio accessor/mutator
public function getApiKeyAttribute($value): ?string
{
    return $value ? Crypt::decryptString($value) : null;
}

public function setApiKeyAttribute($value): void
{
    $this->attributes['api_key'] = $value ? Crypt::encryptString($value) : null;
}
// Mismo patrón para: api_secret, account_sid, auth_token
```

Campos sensibles: `api_key`, `api_secret`, `account_sid`, `auth_token`, `webhook_secret`

## Composite Indexes

Patrones de índice comunes:

```php
$table->index(['tenant_id', 'status']);           // Filtro por tenant + status
$table->index(['tenant_id', 'created_at']);       // Listados con orden
$table->index(['tenant_id', 'event', 'channel']); // Templates por evento/canal
$table->unique(['tenant_id', 'slug']);            // Unicidad por tenant
$table->index(['documentable_type', 'documentable_id']); // Polimorfismo
```

## Soft Deletes

La mayoría de modelos usan `SoftDeletes`. Queries deben ser conscientes:

```php
// Incluir eliminados
Application::withTrashed()->find($id);

// Solo eliminados
Application::onlyTrashed()->where('tenant_id', $tenantId)->get();
```

## Seeder Structure

```
DatabaseSeeder
├── TenantSeeder (creates default tenant + branding)
├── StaffAccountSeeder (admin, analyst, supervisor)
├── DemoDataSeeder / V2DemoDataSeeder
├── PersonDemoSeeder
├── SecondTenantSeeder
├── NotificationTemplateSeeder
└── ProfessionalNotificationTemplates / ProfessionalEmailTemplates
```

Ejecutar: `php artisan db:seed`
