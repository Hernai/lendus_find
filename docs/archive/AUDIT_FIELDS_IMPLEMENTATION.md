# Implementación de Campos de Auditoría

## Resumen

Se han agregado campos de auditoría a todas las tablas principales del sistema para rastrear quién creó, actualizó y eliminó cada registro.

## Campos Agregados

### Para todas las tablas:

- ✅ **created_at** (timestamp) - Ya existía vía `timestamps()`
- ✅ **updated_at** (timestamp) - Ya existía vía `timestamps()`
- ✅ **deleted_at** (timestamp) - Ya existía vía `softDeletes()`
- ✅ **created_by** (UUID) - NUEVO - Usuario que creó el registro
- ✅ **updated_by** (UUID) - NUEVO - Usuario que actualizó el registro por última vez
- ✅ **deleted_by** (UUID) - NUEVO - Usuario que eliminó el registro (soft delete)

Todos los campos `*_by` son referencias UUID a la tabla `users` con `nullOnDelete`.

## Tablas Afectadas

Las siguientes 14 tablas ahora tienen campos de auditoría completos:

1. `tenants`
2. `products`
3. `applicants`
4. `applications`
5. `documents`
6. `references`
7. `application_notes`
8. `webhooks`
9. `addresses`
10. `employment_records`
11. `bank_accounts`
12. `data_verifications`
13. `tenant_branding`
14. `tenant_api_configs`

## Migración Ejecutada

**Archivo**: `database/migrations/2026_01_14_155746_add_audit_fields_to_main_tables.php`

La migración:
- Agrega los campos `created_by`, `updated_by`, `deleted_by` a todas las tablas
- Crea foreign keys a la tabla `users`
- Solo agrega `deleted_by` si la tabla ya tiene `deleted_at` (soft deletes)
- Es segura para re-ejecutar (verifica si las columnas ya existen)

## Trait Creado: HasAuditFields

**Archivo**: `app/Traits/HasAuditFields.php`

Este trait automáticamente:
- Registra `created_by` cuando se crea un registro
- Actualiza `updated_by` cuando se modifica un registro
- Registra `deleted_by` cuando se elimina un registro (soft delete)

### Cómo Usar

Agrega el trait a cualquier modelo que tenga campos de auditoría:

```php
<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use SoftDeletes, HasAuditFields; // ← Agrega el trait

    // ... resto del modelo
}
```

### Relaciones Disponibles

El trait agrega automáticamente las siguientes relaciones:

```php
// Obtener el usuario que creó el registro
$application->creator; // App\Models\User

// Obtener el usuario que actualizó el registro
$application->updater; // App\Models\User

// Obtener el usuario que eliminó el registro
$application->deleter; // App\Models\User
```

### Atributos Computed

También agrega atributos para obtener el nombre del usuario:

```php
// Nombre del usuario que creó el registro
$application->created_by_name; // "Juan Pérez" o "Sistema"

// Nombre del usuario que actualizó el registro
$application->updated_by_name; // "María López" o null

// Nombre del usuario que eliminó el registro
$application->deleted_by_name; // "Admin" o null
```

## Funcionamiento Automático

Cuando un usuario autenticado crea, actualiza o elimina un registro, el sistema automáticamente:

1. **Al crear** (`creating` event):
   ```php
   $application = Application::create([
       'name' => 'Mi Solicitud',
       // created_by se registra automáticamente con Auth::id()
   ]);
   ```

2. **Al actualizar** (`updating` event):
   ```php
   $application->update(['status' => 'APPROVED']);
   // updated_by se actualiza automáticamente con Auth::id()
   ```

3. **Al eliminar** (soft delete - `deleting` event):
   ```php
   $application->delete();
   // deleted_by se registra automáticamente con Auth::id()
   // deleted_at también se registra (de SoftDeletes)
   ```

## Ejemplo de Uso en Queries

### Obtener registros con información del creador

```php
$applications = Application::with('creator')->get();

foreach ($applications as $app) {
    echo "Creado por: {$app->creator->name} el {$app->created_at}\n";
}
```

### Filtrar por quién creó o actualizó

```php
// Solicitudes creadas por un usuario específico
$myApplications = Application::where('created_by', $userId)->get();

// Solicitudes modificadas por supervisores
$supervisorIds = User::where('type', 'SUPERVISOR')->pluck('id');
$reviewed = Application::whereIn('updated_by', $supervisorIds)->get();
```

### Ver auditoría completa

```php
$application = Application::with(['creator', 'updater', 'deleter'])->find($id);

echo "Creado por: {$application->created_by_name} el {$application->created_at}\n";
echo "Última actualización por: {$application->updated_by_name} el {$application->updated_at}\n";

if ($application->trashed()) {
    echo "Eliminado por: {$application->deleted_by_name} el {$application->deleted_at}\n";
}
```

## Modelos que Deben Usar el Trait

Para activar la auditoría automática, agrega el trait `HasAuditFields` a estos modelos:

- [ ] `App\Models\Tenant`
- [ ] `App\Models\Product`
- [x] `App\Models\Applicant` (ya tiene timestamps y soft deletes)
- [x] `App\Models\Application` (ya tiene timestamps y soft deletes)
- [ ] `App\Models\Document`
- [ ] `App\Models\Reference`
- [ ] `App\Models\ApplicationNote`
- [ ] `App\Models\Webhook`
- [ ] `App\Models\Address`
- [ ] `App\Models\EmploymentRecord`
- [ ] `App\Models\BankAccount`
- [ ] `App\Models\DataVerification`
- [ ] `App\Models\TenantBranding`
- [ ] `App\Models\TenantApiConfig`

## Próximos Pasos

### 1. Agregar el Trait a los Modelos

Edita cada modelo y agrega `use HasAuditFields;`:

```php
<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasAuditFields; // ← Agrega esto

    // ... resto del código
}
```

### 2. Verificar que Funciona

Prueba creando un registro mientras estás autenticado:

```php
// En tinker o en tu código
$user = User::first();
Auth::login($user);

$application = Application::create([
    'tenant_id' => $tenant->id,
    'applicant_id' => $applicant->id,
    'product_id' => $product->id,
    'requested_amount' => 10000,
    'term_months' => 12,
    // ...
]);

dd([
    'created_by' => $application->created_by, // Debería ser el ID del usuario
    'creator_name' => $application->created_by_name, // Debería ser el nombre del usuario
]);
```

### 3. Mostrar Auditoría en el Admin Panel

En las vistas de administración, puedes mostrar quién creó/actualizó cada registro:

```blade
<div class="audit-info">
    <p>Creado por: {{ $application->created_by_name }} el {{ $application->created_at->format('d/m/Y H:i') }}</p>
    @if($application->updated_by)
        <p>Última actualización por: {{ $application->updated_by_name }} el {{ $application->updated_at->format('d/m/Y H:i') }}</p>
    @endif
    @if($application->trashed())
        <p>Eliminado por: {{ $application->deleted_by_name }} el {{ $application->deleted_at->format('d/m/Y H:i') }}</p>
    @endif
</div>
```

### 4. Reportes de Auditoría

Ahora puedes crear reportes como:

- ¿Quién ha creado más solicitudes este mes?
- ¿Qué analista ha revisado más documentos?
- ¿Qué supervisor ha eliminado más aplicaciones?

```php
// Analistas más activos este mes
$topAnalysts = Application::whereBetween('updated_at', [now()->startOfMonth(), now()])
    ->select('updated_by', DB::raw('count(*) as count'))
    ->groupBy('updated_by')
    ->with('updater')
    ->orderByDesc('count')
    ->limit(10)
    ->get();
```

## Consideraciones

### Rendimiento
- Los campos de auditoría son `nullable` y tienen índices via foreign keys
- No impactan negativamente el rendimiento
- Solo se actualizan cuando hay un usuario autenticado

### Procesos Background
- Los jobs en cola o comandos de Artisan que no tengan un usuario autenticado dejarán `created_by`/`updated_by` como `NULL`
- Si necesitas rastrear procesos automáticos, puedes crear un usuario "Sistema" y autenticarlo antes del proceso

### Testing
- En tests, si no estás autenticado, los campos `*_by` serán `NULL`
- Para tests que requieren auditoría, usa `$this->actingAs($user)` antes de crear registros

## Beneficios

✅ **Rastreabilidad completa**: Sabes quién hizo qué y cuándo
✅ **Cumplimiento normativo**: Registro de auditoría para reguladores
✅ **Debugging**: Puedes rastrear quién introdujo un cambio problemático
✅ **Reportes**: Métricas de productividad por usuario
✅ **Seguridad**: Evidencia de acciones para investigaciones
✅ **Soft deletes mejorados**: Sabes quién eliminó un registro

## Resumen de Archivos Modificados/Creados

### Creados:
- ✅ `database/migrations/2026_01_14_155746_add_audit_fields_to_main_tables.php`
- ✅ `app/Traits/HasAuditFields.php`

### Modificados (Base de datos):
- ✅ 14 tablas ahora tienen `created_by`, `updated_by`, `deleted_by`

### Pendientes de modificar:
- ⏳ 14 modelos necesitan agregar `use HasAuditFields;`
