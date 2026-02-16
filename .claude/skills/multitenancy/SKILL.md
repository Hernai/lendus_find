---
name: multitenancy
description: Arquitectura multi-tenant de LendusFind. Usar al trabajar con tenant scoping, branding, identificación de tenant o modelos compartidos.
---

# Multitenancy

## Cuándo aplica
Seguir esta guía al crear modelos tenant-scoped, trabajar con branding, configurar integraciones por tenant, o manejar la identificación del tenant.

## Architecture

**Single Database + Tenant Scoping**: Todos los datos en una BD, filtrados por `tenant_id` en cada query mediante el trait `HasTenant`.

## Tenant Identification

Middleware `IdentifyTenant` resuelve el tenant en este orden:

1. **Header `X-Tenant-ID`** (slug o UUID) — Prioridad más alta para API calls
2. **Subdomain** — `tenant.losapp.com` → busca por slug
3. **Query param `?tenant=slug`** — Solo en `local`/`testing`
4. **Default tenant** — Solo en `local`, toma el primer tenant

```php
// El tenant se almacena en el container
app()->instance('tenant', $tenant);
app()->instance('tenant.id', $tenant->id);

// Acceso en controllers
$tenant = $request->attributes->get('tenant');
// O
$tenant = app('tenant');
```

Subdomains reservados: `www`, `api`, `admin`, `app`, `mail`, `smtp`

## HasTenant Trait

```php
trait HasTenant
{
    protected static function bootHasTenant(): void
    {
        // Global scope: filtra automáticamente por tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (app()->bound('tenant.id') && $tenantId = app('tenant.id')) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
            }
        });

        // Auto-asigna tenant_id al crear
        static::creating(function ($model) {
            if (!$model->tenant_id && app()->bound('tenant.id') && $tenantId = app('tenant.id')) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function scopeForTenant(Builder $query, string $tenantId): Builder { ... }
    public function scopeWithoutTenant(Builder $query): Builder { ... }
}
```

**IMPORTANTE**: Verificar `app()->bound('tenant.id')` antes de acceder — evita errores en CLI/seeders.

## Tenant Model

```php
// Relaciones clave
$tenant->branding        // TenantBranding (1:1)
$tenant->apiConfigs      // TenantApiConfig (1:N)
$tenant->products        // Product (1:N)
$tenant->staffAccounts   // StaffAccount (1:N)

// Campos JSONB
$tenant->settings        // Configuración general
$tenant->webhook_config  // Configuración de webhooks
```

## TenantBranding

White-label theming por tenant:

```php
$branding = $tenant->branding;
$branding->primary_color;       // '#1A56DB'
$branding->secondary_color;     // '#7E3AF2'
$branding->logo_url;            // URL del logo
$branding->favicon_url;
$branding->login_background_url;
$branding->font_family;
$branding->custom_css;
```

Frontend aplica colores como CSS variables:
```css
:root {
  --tenant-primary: #1A56DB;
  --tenant-secondary: #7E3AF2;
}
```

Store `tenant.ts` carga branding y aplica variables dinámicamente.

## TenantApiConfig (Integration Providers)

Cada tenant configura sus propios proveedores de servicios:

| Provider | Service Type | Uso |
|----------|-------------|-----|
| `twilio` | sms, whatsapp | Envío de SMS y WhatsApp |
| `smtp` | email | SMTP propio (Office 365, Gmail) |
| `sendgrid` | email | Email via SendGrid API |
| `mailgun` | email | Email via Mailgun API |
| `nubarium` | kyc | Validación de identidad |
| `circulo_credito` | credit_bureau | Bureau de crédito |

Credenciales (`api_key`, `api_secret`, `account_sid`, `auth_token`) encriptadas con Laravel `Crypt`.

Campos SMTP en `extra_config`: `host`, `port`, `encryption` (tls/ssl/none), `from_name`

## Frontend Tenant Store

```typescript
// stores/tenant.ts
const useTenantStore = defineStore('tenant', () => {
  const tenant = ref<Tenant | null>(null)
  const branding = ref<Branding | null>(null)

  async function loadConfig() {
    const response = await configService.getConfig()
    tenant.value = response.data.tenant
    branding.value = response.data.branding
    applyBrandingCssVars(branding.value)
  }
})
```

## Routing con Tenant

Frontend routes son tenant-prefixed: `/:tenant/...`

```typescript
// Router: detecta tenant del subdomain o path
const tenantSlug = extractTenantFromUrl()
// Redirige: /admin → /:tenant/admin
```

## Errores comunes a evitar

1. **Olvidar `HasTenant`** en un nuevo modelo — Los datos se mostrarán de todos los tenants
2. **Queries sin global scope** — `Model::withoutGlobalScope('tenant')` solo cuando es intencionado (seeders, admin)
3. **Acceder a `app('tenant.id')` sin verificar `bound()`** — Falla en CLI/seeders
4. **Hardcodear colores** — Usar `primary-*` classes (mapeadas a CSS vars del tenant)
5. **Olvidar `tenant_id` en foreign keys** — Todas las tablas tenant-scoped necesitan `foreignUuid('tenant_id')`
