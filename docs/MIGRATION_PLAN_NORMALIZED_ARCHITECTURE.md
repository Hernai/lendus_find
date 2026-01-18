# Plan de Migración: Arquitectura Normalizada

## Resumen Ejecutivo

Este documento describe el plan de migración paso a paso para implementar la nueva arquitectura normalizada de base de datos. Cada fase es independiente, incluye pruebas, y debe completarse exitosamente antes de continuar con la siguiente.

**Duración estimada total**: 6-8 fases
**Principio clave**: Cada fase debe dejar el sistema funcionando al 100%

---

## Estado de Progreso

| Fase | Descripción | Estado | Tests |
|------|-------------|--------|-------|
| 0 | Preparación y Backup | ✅ Completada | 29 tests existentes pasan |
| 1 | Autenticación de Staff | ✅ Completada | 21 unit + 14 feature = 35 tests nuevos |
| 2 | Autenticación de Aplicantes | ✅ Completada | 29 tests V2/Applicant |
| 3 | Tabla Persons y Datos Normalizados | ✅ Completada | 98 tests Api/Person (6 controllers) |
| 4 | Empresas y Miembros | ✅ Completada | 14 + 16 + 19 + 25 = 74 tests nuevos |
| 5 | Documents v2 (Polimórfico) | ✅ Completada | 30 tests Unit/Models/DocumentV2Test |
| 6 | Applications v2 | ✅ Completada | 47 tests Unit/Models/ApplicationV2Test |
| 7 | Limpieza y Deprecación | ⏳ Pendiente | - |

**Total tests actuales**: 732 (todas las fases hasta la 6 + tests legacy)
**Última actualización**: 2026-01-17

---

## Estado Actual vs Estado Objetivo

### Estado Actual
```
users (mixto: staff + applicants)
    ↓
applicants (datos embebidos en JSONB)
    ↓
applications
    ↓
documents
```

### Estado Objetivo
```
staff_accounts ←→ staff_profiles
applicant_accounts ←→ applicant_identities
    ↓
persons
    ↓
person_identifications (con historial)
person_addresses (con historial)
person_employments (con historial)
person_references
person_bank_accounts
    ↓
companies ←→ company_members
    ↓
applications_v2
    ↓
documents_v2 (polimórfico)
```

---

## Fase 0: Preparación y Backup

### Objetivo
Preparar el entorno y asegurar que podemos revertir cualquier cambio.

### Tareas

#### 0.1 Backup de Base de Datos
```bash
# Crear backup completo
pg_dump -h localhost -U postgres -d lendus_find -F c -f backup_pre_migration_$(date +%Y%m%d_%H%M%S).dump

# Verificar backup
pg_restore --list backup_pre_migration_*.dump | head -20
```

#### 0.2 Crear rama de Git
```bash
git checkout -b feature/normalized-architecture
git push -u origin feature/normalized-architecture
```

#### 0.3 Documentar estado actual
```bash
# Exportar estructura actual
cd backend
php artisan schema:dump --path=database/schema/pre_migration_schema.sql
```

### Checklist de Verificación
- [ ] Backup creado y verificado
- [ ] Rama de Git creada
- [ ] Schema actual documentado
- [ ] Todos los tests actuales pasan: `php artisan test`

---

## Fase 1: Autenticación de Staff

### Objetivo
Separar la autenticación de usuarios administrativos (staff) de los aplicantes.

### Dependencias
- Ninguna (primera fase)

### Archivos a Crear

#### 1.1 Ejecutar Migración
```bash
php artisan migrate --path=database/migrations/2026_01_18_020001_create_staff_authentication_tables.php
```

#### 1.2 Crear Modelo StaffAccount
```php
// app/Models/StaffAccount.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class StaffAccount extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'email',
        'password',
        'role',
        'is_active',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // =====================================================
    // Relationships
    // =====================================================

    public function profile()
    {
        return $this->hasOne(StaffProfile::class, 'account_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    // =====================================================
    // Role Helpers
    // =====================================================

    public function isAnalyst(): bool
    {
        return $this->role === 'ANALYST';
    }

    public function isSupervisor(): bool
    {
        return $this->role === 'SUPERVISOR';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'SUPER_ADMIN';
    }

    // =====================================================
    // Permission Helpers (migrados de User)
    // =====================================================

    public function canReviewDocuments(): bool
    {
        return in_array($this->role, ['ANALYST', 'SUPERVISOR', 'ADMIN', 'SUPER_ADMIN']);
    }

    public function canVerifyReferences(): bool
    {
        return in_array($this->role, ['ANALYST', 'SUPERVISOR', 'ADMIN', 'SUPER_ADMIN']);
    }

    public function canChangeApplicationStatus(): bool
    {
        return in_array($this->role, ['ANALYST', 'SUPERVISOR', 'ADMIN', 'SUPER_ADMIN']);
    }

    public function canApproveRejectApplications(): bool
    {
        return in_array($this->role, ['SUPERVISOR', 'ADMIN', 'SUPER_ADMIN']);
    }

    public function canAssignApplications(): bool
    {
        return in_array($this->role, ['SUPERVISOR', 'ADMIN', 'SUPER_ADMIN']);
    }

    public function canManageProducts(): bool
    {
        return in_array($this->role, ['ADMIN', 'SUPER_ADMIN']);
    }

    public function canManageUsers(): bool
    {
        return in_array($this->role, ['ADMIN', 'SUPER_ADMIN']);
    }

    public function canViewReports(): bool
    {
        return in_array($this->role, ['ANALYST', 'ADMIN', 'SUPER_ADMIN']);
    }

    public function canConfigureTenant(): bool
    {
        return $this->role === 'SUPER_ADMIN';
    }
}
```

#### 1.3 Crear Modelo StaffProfile
```php
// app/Models/StaffProfile.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'account_id',
        'first_name',
        'last_name',
        'last_name_2',
        'phone',
        'avatar_url',
        'title',
        'preferences',
    ];

    protected $casts = [
        'preferences' => 'array',
    ];

    public function account()
    {
        return $this->belongsTo(StaffAccount::class, 'account_id');
    }

    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->last_name,
            $this->last_name_2,
        ]);
        return implode(' ', $parts);
    }
}
```

#### 1.4 Crear Migración de Datos (Staff)
```php
// database/migrations/2026_01_18_020011_migrate_staff_users_to_staff_accounts.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrar usuarios staff de la tabla users a staff_accounts
        $staffUsers = DB::table('users')
            ->whereIn('type', ['ANALYST', 'SUPERVISOR', 'ADMIN', 'SUPER_ADMIN'])
            ->get();

        foreach ($staffUsers as $user) {
            // Crear staff_account
            $accountId = DB::table('staff_accounts')->insertGetId([
                'id' => $user->id, // Mantener mismo UUID
                'tenant_id' => $user->tenant_id,
                'email' => $user->email,
                'password' => $user->password,
                'role' => $user->type,
                'is_active' => $user->is_active ?? true,
                'email_verified_at' => $user->email_verified_at,
                'last_login_at' => $user->last_login_at,
                'last_login_ip' => $user->last_login_ip,
                'remember_token' => $user->remember_token,
                'created_at' => $user->created_at,
                'updated_at' => now(),
            ]);

            // Crear staff_profile
            DB::table('staff_profiles')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'account_id' => $user->id,
                'first_name' => $user->first_name ?? $user->name,
                'last_name' => $user->last_name ?? '',
                'last_name_2' => $user->last_name_2,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No eliminar datos de users, solo limpiar las nuevas tablas
        DB::table('staff_profiles')->truncate();
        DB::table('staff_accounts')->truncate();
    }
};
```

#### 1.5 Crear Guard para Staff
```php
// config/auth.php - Agregar:
'guards' => [
    // ... existing guards ...
    'staff' => [
        'driver' => 'sanctum',
        'provider' => 'staff',
    ],
],

'providers' => [
    // ... existing providers ...
    'staff' => [
        'driver' => 'eloquent',
        'model' => App\Models\StaffAccount::class,
    ],
],
```

#### 1.6 Crear Controller de Auth para Staff
```php
// app/Http/Controllers/Api/Staff/AuthController.php
<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $staff = StaffAccount::where('email', $request->email)->first();

        if (!$staff || !Hash::check($request->password, $staff->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales son incorrectas.'],
            ]);
        }

        if (!$staff->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Esta cuenta está desactivada.'],
            ]);
        }

        // Actualizar último login
        $staff->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $token = $staff->createToken('staff-token', ['staff'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $staff->id,
                'email' => $staff->email,
                'role' => $staff->role,
                'name' => $staff->profile?->full_name,
                'tenant_id' => $staff->tenant_id,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function me(Request $request)
    {
        $staff = $request->user();
        $staff->load('profile', 'tenant');

        return response()->json([
            'id' => $staff->id,
            'email' => $staff->email,
            'role' => $staff->role,
            'name' => $staff->profile?->full_name,
            'tenant' => [
                'id' => $staff->tenant->id,
                'name' => $staff->tenant->name,
                'slug' => $staff->tenant->slug,
            ],
            'permissions' => [
                'canReviewDocuments' => $staff->canReviewDocuments(),
                'canVerifyReferences' => $staff->canVerifyReferences(),
                'canApproveRejectApplications' => $staff->canApproveRejectApplications(),
                'canAssignApplications' => $staff->canAssignApplications(),
                'canManageProducts' => $staff->canManageProducts(),
                'canManageUsers' => $staff->canManageUsers(),
                'canViewReports' => $staff->canViewReports(),
                'canConfigureTenant' => $staff->canConfigureTenant(),
            ],
        ]);
    }
}
```

#### 1.7 Agregar Rutas
```php
// routes/api.php - Agregar:

// Staff Authentication (nueva estructura)
Route::prefix('v2/staff')->group(function () {
    Route::post('login', [App\Http\Controllers\Api\Staff\AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [App\Http\Controllers\Api\Staff\AuthController::class, 'logout']);
        Route::get('me', [App\Http\Controllers\Api\Staff\AuthController::class, 'me']);
    });
});
```

### Plan de Pruebas - Fase 1

#### Tests Unitarios
```php
// tests/Unit/Models/StaffAccountTest.php
<?php

namespace Tests\Unit\Models;

use App\Models\StaffAccount;
use App\Models\StaffProfile;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_account_has_profile()
    {
        $tenant = Tenant::factory()->create();
        $account = StaffAccount::factory()->create(['tenant_id' => $tenant->id]);
        $profile = StaffProfile::factory()->create(['account_id' => $account->id]);

        $this->assertInstanceOf(StaffProfile::class, $account->profile);
        $this->assertEquals($profile->id, $account->profile->id);
    }

    public function test_analyst_permissions()
    {
        $account = new StaffAccount(['role' => 'ANALYST']);

        $this->assertTrue($account->canReviewDocuments());
        $this->assertTrue($account->canVerifyReferences());
        $this->assertFalse($account->canApproveRejectApplications());
        $this->assertFalse($account->canManageUsers());
    }

    public function test_supervisor_permissions()
    {
        $account = new StaffAccount(['role' => 'SUPERVISOR']);

        $this->assertTrue($account->canReviewDocuments());
        $this->assertTrue($account->canApproveRejectApplications());
        $this->assertTrue($account->canAssignApplications());
        $this->assertFalse($account->canManageUsers());
    }

    public function test_admin_permissions()
    {
        $account = new StaffAccount(['role' => 'ADMIN']);

        $this->assertTrue($account->canReviewDocuments());
        $this->assertTrue($account->canApproveRejectApplications());
        $this->assertTrue($account->canManageUsers());
        $this->assertFalse($account->canConfigureTenant());
    }

    public function test_super_admin_has_all_permissions()
    {
        $account = new StaffAccount(['role' => 'SUPER_ADMIN']);

        $this->assertTrue($account->canReviewDocuments());
        $this->assertTrue($account->canApproveRejectApplications());
        $this->assertTrue($account->canManageUsers());
        $this->assertTrue($account->canConfigureTenant());
    }
}
```

#### Tests de Feature
```php
// tests/Feature/Staff/AuthenticationTest.php
<?php

namespace Tests\Feature\Staff;

use App\Models\StaffAccount;
use App\Models\StaffProfile;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private StaffAccount $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->staff = StaffAccount::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'role' => 'ADMIN',
            'is_active' => true,
        ]);
        StaffProfile::create([
            'account_id' => $this->staff->id,
            'first_name' => 'Test',
            'last_name' => 'Admin',
        ]);
    }

    public function test_staff_can_login_with_valid_credentials()
    {
        $response = $this->postJson('/api/v2/staff/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'email', 'role', 'name'],
            ]);
    }

    public function test_staff_cannot_login_with_invalid_password()
    {
        $response = $this->postJson('/api/v2/staff/login', [
            'email' => 'admin@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_inactive_staff_cannot_login()
    {
        $this->staff->update(['is_active' => false]);

        $response = $this->postJson('/api/v2/staff/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable();
    }

    public function test_authenticated_staff_can_get_profile()
    {
        $token = $this->staff->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v2/staff/me');

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'email',
                'role',
                'name',
                'permissions',
            ]);
    }

    public function test_staff_can_logout()
    {
        $token = $this->staff->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v2/staff/logout');

        $response->assertOk();

        // Token should be invalidated
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->staff->id,
        ]);
    }

    public function test_login_updates_last_login_timestamp()
    {
        $this->postJson('/api/v2/staff/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $this->staff->refresh();
        $this->assertNotNull($this->staff->last_login_at);
    }
}
```

#### Tests Manuales
```markdown
## Checklist de Pruebas Manuales - Fase 1

### Preparación
- [ ] Base de datos limpia o backup restaurado
- [ ] Migraciones ejecutadas sin errores
- [ ] Datos de staff migrados correctamente

### Login Staff
- [ ] Login con credenciales correctas → Token recibido
- [ ] Login con contraseña incorrecta → Error 422
- [ ] Login con email inexistente → Error 422
- [ ] Login con cuenta inactiva → Error 422

### Sesión
- [ ] GET /api/v2/staff/me con token válido → Datos del usuario
- [ ] GET /api/v2/staff/me sin token → Error 401
- [ ] POST /api/v2/staff/logout → Sesión cerrada
- [ ] Usar token después de logout → Error 401

### Permisos
- [ ] Verificar que ANALYST tiene permisos correctos en respuesta /me
- [ ] Verificar que SUPERVISOR tiene permisos correctos
- [ ] Verificar que ADMIN tiene permisos correctos
- [ ] Verificar que SUPER_ADMIN tiene todos los permisos

### Compatibilidad
- [ ] Sistema antiguo (/api/admin/*) sigue funcionando
- [ ] Login antiguo sigue funcionando para staff
- [ ] No hay errores en logs
```

### Comandos de Ejecución - Fase 1

```bash
# 1. Ejecutar migraciones
cd backend
php artisan migrate

# 2. Verificar tablas creadas
php artisan tinker
>>> Schema::hasTable('staff_accounts')
>>> Schema::hasTable('staff_profiles')

# 3. Ejecutar migración de datos
php artisan migrate --path=database/migrations/2026_01_18_020011_migrate_staff_users_to_staff_accounts.php

# 4. Verificar datos migrados
php artisan tinker
>>> App\Models\StaffAccount::count()
>>> App\Models\StaffProfile::count()

# 5. Ejecutar tests
php artisan test --filter=StaffAccountTest
php artisan test --filter=Staff/AuthenticationTest

# 6. Verificar que tests existentes siguen pasando
php artisan test
```

### Rollback - Fase 1

Si algo falla:
```bash
# Revertir migraciones de esta fase
php artisan migrate:rollback --path=database/migrations/2026_01_18_020011_migrate_staff_users_to_staff_accounts.php
php artisan migrate:rollback --path=database/migrations/2026_01_18_020001_create_staff_authentication_tables.php

# Eliminar archivos creados
rm app/Models/StaffAccount.php
rm app/Models/StaffProfile.php
rm app/Http/Controllers/Api/Staff/AuthController.php
rm tests/Unit/Models/StaffAccountTest.php
rm tests/Feature/Staff/AuthenticationTest.php

# Revertir cambios en config/auth.php y routes/api.php
git checkout config/auth.php routes/api.php
```

### Criterios de Éxito - Fase 1

- [x] Todas las migraciones ejecutan sin errores
- [ ] Datos de staff migrados correctamente (pendiente: data migration script)
- [x] Tests unitarios pasan (100%) - 21 tests
- [x] Tests de feature pasan (100%) - 14 tests
- [ ] Tests manuales pasan
- [x] Sistema antiguo sigue funcionando - 29 tests de AuthControllerTest pasan
- [x] No hay errores en logs de Laravel

---

## Implementación Real - Fase 1 (Completada 2026-01-17)

### Archivos Creados

| Archivo | Descripción |
|---------|-------------|
| `app/Models/StaffAccount.php` | Modelo de autenticación para staff con HasApiTokens, permisos y scopes |
| `app/Models/StaffProfile.php` | Perfil personal separado del modelo de autenticación |
| `database/factories/StaffAccountFactory.php` | Factory con estados para roles (analyst, supervisor, admin, superAdmin, inactive) |
| `database/factories/StaffProfileFactory.php` | Factory para perfiles |
| `app/Http/Controllers/Api/V2/Staff/AuthController.php` | Controller v2 para login/logout/me/refresh |
| `tests/Unit/Models/StaffAccountTest.php` | 21 tests unitarios para modelo y permisos |
| `tests/Feature/V2/Staff/AuthControllerTest.php` | 14 tests de feature para endpoints |

### Rutas V2 Agregadas

```
POST   /api/v2/staff/auth/login    - Login con email/password
GET    /api/v2/staff/auth/me       - Obtener perfil del staff autenticado
POST   /api/v2/staff/auth/logout   - Cerrar sesión
POST   /api/v2/staff/auth/refresh  - Refrescar token
```

### Notas Importantes

1. **SQLite y CONCAT**: Las migraciones tenían columnas virtuales con `CONCAT()` que no funcionan en SQLite (usado para tests). Se cambiaron a accessors en los modelos.

2. **AuditLog FK**: El modelo `AuditLog` tiene FK a `users.id`. Para el logout de StaffAccount, se usa `Log::info()` en lugar de AuditLog para evitar FK constraint violations. TODO: Agregar columna `staff_account_id` a audit_logs.

3. **Factory afterCreating**: El StaffAccountFactory crea automáticamente un StaffProfile después de crear la cuenta. Los tests deben usar `$account->refresh()` para cargar la relación.

### Comandos para Verificar

```bash
# Ejecutar todos los tests de Fase 1
cd backend
php artisan test tests/Unit/Models/StaffAccountTest.php
php artisan test tests/Feature/V2/Staff/AuthControllerTest.php

# Verificar que tests existentes siguen pasando
php artisan test tests/Feature/Auth/AuthControllerTest.php

# Total esperado: 64 tests pasando
```

---

## Fase 2: Autenticación de Aplicantes

### Objetivo
Implementar el nuevo sistema de autenticación multi-identidad para aplicantes.

### Dependencias
- Fase 1 completada

### Archivos a Crear

#### 2.1 Ejecutar Migración
```bash
php artisan migrate --path=database/migrations/2026_01_18_020002_create_applicant_authentication_tables.php
```

#### 2.2 Crear Modelo ApplicantAccount
```php
// app/Models/ApplicantAccount.php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class ApplicantAccount extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'person_id',
        'pin_hash',
        'pin_set_at',
        'pin_attempts',
        'pin_locked_until',
        'is_active',
        'onboarding_step',
        'onboarding_completed',
        'onboarding_completed_at',
        'last_login_at',
        'last_login_ip',
        'last_login_method',
        'known_devices',
        'preferences',
    ];

    protected $hidden = [
        'pin_hash',
    ];

    protected $casts = [
        'pin_set_at' => 'datetime',
        'pin_locked_until' => 'datetime',
        'onboarding_completed_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'onboarding_completed' => 'boolean',
        'known_devices' => 'array',
        'preferences' => 'array',
    ];

    // =====================================================
    // Relationships
    // =====================================================

    public function identities()
    {
        return $this->hasMany(ApplicantIdentity::class, 'account_id');
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    // =====================================================
    // Identity Helpers
    // =====================================================

    public function getPrimaryIdentity()
    {
        return $this->identities()->where('is_primary', true)->first();
    }

    public function getIdentityByType(string $type)
    {
        return $this->identities()->where('type', $type)->first();
    }

    public function hasVerifiedIdentity(): bool
    {
        return $this->identities()->whereNotNull('verified_at')->exists();
    }

    // =====================================================
    // PIN Helpers
    // =====================================================

    public function hasPin(): bool
    {
        return !is_null($this->pin_hash);
    }

    public function isPinLocked(): bool
    {
        return $this->pin_locked_until && $this->pin_locked_until->isFuture();
    }

    public function incrementPinAttempts(): void
    {
        $this->increment('pin_attempts');

        if ($this->pin_attempts >= 5) {
            $this->update([
                'pin_locked_until' => now()->addMinutes(30),
            ]);
        }
    }

    public function resetPinAttempts(): void
    {
        $this->update([
            'pin_attempts' => 0,
            'pin_locked_until' => null,
        ]);
    }
}
```

#### 2.3 Crear Modelo ApplicantIdentity
```php
// app/Models/ApplicantIdentity.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ApplicantIdentity extends Model
{
    use HasUuids;

    protected $fillable = [
        'account_id',
        'type',
        'identifier',
        'verified_at',
        'verification_code',
        'verification_code_expires_at',
        'verification_attempts',
        'is_primary',
        'last_used_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'verification_code_expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_primary' => 'boolean',
    ];

    // =====================================================
    // Relationships
    // =====================================================

    public function account()
    {
        return $this->belongsTo(ApplicantAccount::class, 'account_id');
    }

    // =====================================================
    // Helpers
    // =====================================================

    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }

    public function isPhone(): bool
    {
        return $this->type === 'PHONE';
    }

    public function isEmail(): bool
    {
        return $this->type === 'EMAIL';
    }

    public function isWhatsApp(): bool
    {
        return $this->type === 'WHATSAPP';
    }

    public function canRequestOtp(): bool
    {
        // Rate limiting: max 3 OTPs per hour
        $recentRequests = OtpRequest::where('identity_id', $this->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $recentRequests < 3;
    }

    public function generateOtp(): string
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->update([
            'verification_code' => $code,
            'verification_code_expires_at' => now()->addMinutes(10),
            'verification_attempts' => 0,
        ]);

        return $code;
    }

    public function verifyOtp(string $code): bool
    {
        if ($this->verification_code !== $code) {
            $this->increment('verification_attempts');
            return false;
        }

        if ($this->verification_code_expires_at->isPast()) {
            return false;
        }

        $this->update([
            'verified_at' => now(),
            'verification_code' => null,
            'verification_code_expires_at' => null,
            'verification_attempts' => 0,
            'last_used_at' => now(),
        ]);

        return true;
    }
}
```

#### 2.4 Crear Modelo OtpRequest
```php
// app/Models/OtpRequest.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OtpRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'identity_id',
        'target_type',
        'target_value',
        'code',
        'channel',
        'expires_at',
        'verified_at',
        'attempts',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function identity()
    {
        return $this->belongsTo(ApplicantIdentity::class, 'identity_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }
}
```

#### 2.5 Crear Service de Autenticación
```php
// app/Services/ApplicantAuthService.php
<?php

namespace App\Services;

use App\Models\ApplicantAccount;
use App\Models\ApplicantIdentity;
use App\Models\OtpRequest;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;

class ApplicantAuthService
{
    public function __construct(
        private TwilioService $twilioService
    ) {}

    /**
     * Find or create account by identifier (phone/email)
     */
    public function findOrCreateByIdentifier(
        Tenant $tenant,
        string $type,
        string $identifier
    ): array {
        // Buscar identidad existente
        $identity = ApplicantIdentity::whereHas('account', function ($q) use ($tenant) {
            $q->where('tenant_id', $tenant->id);
        })
            ->where('type', $type)
            ->where('identifier', $identifier)
            ->first();

        if ($identity) {
            return [
                'account' => $identity->account,
                'identity' => $identity,
                'is_new' => false,
            ];
        }

        // Crear nueva cuenta
        $account = ApplicantAccount::create([
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        $identity = ApplicantIdentity::create([
            'account_id' => $account->id,
            'type' => $type,
            'identifier' => $identifier,
            'is_primary' => true,
        ]);

        return [
            'account' => $account,
            'identity' => $identity,
            'is_new' => true,
        ];
    }

    /**
     * Send OTP to identity
     */
    public function sendOtp(ApplicantIdentity $identity, string $channel = null): OtpRequest
    {
        $code = $identity->generateOtp();
        $channel = $channel ?? $this->getDefaultChannel($identity->type);

        // Registrar solicitud
        $otpRequest = OtpRequest::create([
            'identity_id' => $identity->id,
            'code' => $code,
            'channel' => $channel,
            'expires_at' => now()->addMinutes(10),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Enviar OTP
        match ($channel) {
            'SMS' => $this->twilioService->sendSms($identity->identifier, "Tu código es: $code"),
            'WHATSAPP' => $this->twilioService->sendWhatsApp($identity->identifier, "Tu código es: $code"),
            'EMAIL' => $this->sendEmailOtp($identity->identifier, $code),
            default => throw new \InvalidArgumentException("Canal no soportado: $channel"),
        };

        return $otpRequest;
    }

    /**
     * Verify OTP and return token
     */
    public function verifyOtp(ApplicantIdentity $identity, string $code): ?string
    {
        if (!$identity->verifyOtp($code)) {
            return null;
        }

        $account = $identity->account;
        $account->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
            'last_login_method' => $identity->type . '_OTP',
        ]);

        return $account->createToken('applicant-token', ['applicant'])->plainTextToken;
    }

    /**
     * Verify PIN and return token
     */
    public function verifyPin(ApplicantAccount $account, string $pin): ?string
    {
        if ($account->isPinLocked()) {
            return null;
        }

        if (!Hash::check($pin, $account->pin_hash)) {
            $account->incrementPinAttempts();
            return null;
        }

        $account->resetPinAttempts();
        $account->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
            'last_login_method' => 'PIN',
        ]);

        return $account->createToken('applicant-token', ['applicant'])->plainTextToken;
    }

    /**
     * Set PIN for account
     */
    public function setPin(ApplicantAccount $account, string $pin): void
    {
        $account->update([
            'pin_hash' => Hash::make($pin),
            'pin_set_at' => now(),
            'pin_attempts' => 0,
            'pin_locked_until' => null,
        ]);
    }

    /**
     * Add new identity to existing account
     */
    public function addIdentity(
        ApplicantAccount $account,
        string $type,
        string $identifier
    ): ApplicantIdentity {
        // Verificar que no exista
        $existing = ApplicantIdentity::where('type', $type)
            ->where('identifier', $identifier)
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException("Esta identidad ya está registrada");
        }

        return ApplicantIdentity::create([
            'account_id' => $account->id,
            'type' => $type,
            'identifier' => $identifier,
            'is_primary' => false,
        ]);
    }

    private function getDefaultChannel(string $type): string
    {
        return match ($type) {
            'PHONE' => 'SMS',
            'EMAIL' => 'EMAIL',
            'WHATSAPP' => 'WHATSAPP',
            default => 'SMS',
        };
    }

    private function sendEmailOtp(string $email, string $code): void
    {
        // TODO: Implementar envío de email
        // Mail::to($email)->send(new OtpMail($code));
    }
}
```

#### 2.6 Crear Controller de Auth para Aplicantes
```php
// app/Http/Controllers/Api/Applicant/AuthController.php
<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Http\Controllers\Controller;
use App\Models\ApplicantIdentity;
use App\Services\ApplicantAuthService;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private ApplicantAuthService $authService,
        private TenantService $tenantService
    ) {}

    /**
     * Step 1: Send OTP to phone/email
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'type' => 'required|in:PHONE,EMAIL,WHATSAPP',
            'identifier' => 'required|string',
            'channel' => 'nullable|in:SMS,EMAIL,WHATSAPP',
        ]);

        $tenant = $this->tenantService->current();

        $result = $this->authService->findOrCreateByIdentifier(
            $tenant,
            $request->type,
            $request->identifier
        );

        if (!$result['identity']->canRequestOtp()) {
            throw ValidationException::withMessages([
                'identifier' => ['Demasiados intentos. Espera unos minutos.'],
            ]);
        }

        $this->authService->sendOtp($result['identity'], $request->channel);

        return response()->json([
            'message' => 'Código enviado',
            'is_new_account' => $result['is_new'],
            'has_pin' => $result['account']->hasPin(),
        ]);
    }

    /**
     * Step 2: Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'type' => 'required|in:PHONE,EMAIL,WHATSAPP',
            'identifier' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        $tenant = $this->tenantService->current();

        $identity = ApplicantIdentity::whereHas('account', function ($q) use ($tenant) {
            $q->where('tenant_id', $tenant->id);
        })
            ->where('type', $request->type)
            ->where('identifier', $request->identifier)
            ->first();

        if (!$identity) {
            throw ValidationException::withMessages([
                'identifier' => ['Cuenta no encontrada.'],
            ]);
        }

        $token = $this->authService->verifyOtp($identity, $request->code);

        if (!$token) {
            throw ValidationException::withMessages([
                'code' => ['Código incorrecto o expirado.'],
            ]);
        }

        $account = $identity->account;

        return response()->json([
            'token' => $token,
            'has_pin' => $account->hasPin(),
            'onboarding_completed' => $account->onboarding_completed,
            'onboarding_step' => $account->onboarding_step,
        ]);
    }

    /**
     * Login with PIN (for returning users)
     */
    public function loginWithPin(Request $request)
    {
        $request->validate([
            'type' => 'required|in:PHONE,EMAIL,WHATSAPP',
            'identifier' => 'required|string',
            'pin' => 'required|string|size:4',
        ]);

        $tenant = $this->tenantService->current();

        $identity = ApplicantIdentity::whereHas('account', function ($q) use ($tenant) {
            $q->where('tenant_id', $tenant->id);
        })
            ->where('type', $request->type)
            ->where('identifier', $request->identifier)
            ->whereNotNull('verified_at')
            ->first();

        if (!$identity) {
            throw ValidationException::withMessages([
                'identifier' => ['Cuenta no encontrada.'],
            ]);
        }

        $account = $identity->account;

        if (!$account->hasPin()) {
            throw ValidationException::withMessages([
                'pin' => ['Esta cuenta no tiene PIN configurado.'],
            ]);
        }

        if ($account->isPinLocked()) {
            throw ValidationException::withMessages([
                'pin' => ['Cuenta bloqueada. Intenta más tarde.'],
            ]);
        }

        $token = $this->authService->verifyPin($account, $request->pin);

        if (!$token) {
            throw ValidationException::withMessages([
                'pin' => ['PIN incorrecto.'],
            ]);
        }

        return response()->json([
            'token' => $token,
            'onboarding_completed' => $account->onboarding_completed,
            'onboarding_step' => $account->onboarding_step,
        ]);
    }

    /**
     * Set PIN for the account
     */
    public function setPin(Request $request)
    {
        $request->validate([
            'pin' => 'required|string|size:4|regex:/^[0-9]+$/',
            'pin_confirmation' => 'required|same:pin',
        ]);

        $account = $request->user();

        $this->authService->setPin($account, $request->pin);

        return response()->json([
            'message' => 'PIN configurado correctamente',
        ]);
    }

    /**
     * Add another identity (phone/email) to current account
     */
    public function addIdentity(Request $request)
    {
        $request->validate([
            'type' => 'required|in:PHONE,EMAIL,WHATSAPP',
            'identifier' => 'required|string',
        ]);

        $account = $request->user();

        try {
            $identity = $this->authService->addIdentity(
                $account,
                $request->type,
                $request->identifier
            );

            // Enviar OTP para verificar
            $this->authService->sendOtp($identity);

            return response()->json([
                'message' => 'Identidad agregada. Verifica con el código enviado.',
                'identity_id' => $identity->id,
            ]);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'identifier' => [$e->getMessage()],
            ]);
        }
    }

    /**
     * Get current user info
     */
    public function me(Request $request)
    {
        $account = $request->user();
        $account->load('identities', 'person');

        return response()->json([
            'id' => $account->id,
            'identities' => $account->identities->map(fn($i) => [
                'type' => $i->type,
                'identifier' => $this->maskIdentifier($i),
                'is_primary' => $i->is_primary,
                'verified' => $i->isVerified(),
            ]),
            'has_pin' => $account->hasPin(),
            'onboarding_completed' => $account->onboarding_completed,
            'onboarding_step' => $account->onboarding_step,
            'person' => $account->person ? [
                'id' => $account->person->id,
                'full_name' => $account->person->full_name,
            ] : null,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada']);
    }

    private function maskIdentifier(ApplicantIdentity $identity): string
    {
        if ($identity->isEmail()) {
            $parts = explode('@', $identity->identifier);
            return substr($parts[0], 0, 2) . '***@' . $parts[1];
        }

        // Phone
        return substr($identity->identifier, 0, 4) . '****' . substr($identity->identifier, -2);
    }
}
```

#### 2.7 Agregar Rutas
```php
// routes/api.php - Agregar:

// Applicant Authentication (nueva estructura v2)
Route::prefix('v2/applicant')->group(function () {
    Route::post('otp/send', [App\Http\Controllers\Api\Applicant\AuthController::class, 'sendOtp']);
    Route::post('otp/verify', [App\Http\Controllers\Api\Applicant\AuthController::class, 'verifyOtp']);
    Route::post('pin/login', [App\Http\Controllers\Api\Applicant\AuthController::class, 'loginWithPin']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('pin/set', [App\Http\Controllers\Api\Applicant\AuthController::class, 'setPin']);
        Route::post('identity/add', [App\Http\Controllers\Api\Applicant\AuthController::class, 'addIdentity']);
        Route::get('me', [App\Http\Controllers\Api\Applicant\AuthController::class, 'me']);
        Route::post('logout', [App\Http\Controllers\Api\Applicant\AuthController::class, 'logout']);
    });
});
```

### Plan de Pruebas - Fase 2

#### Tests de Feature
```php
// tests/Feature/Applicant/AuthenticationTest.php
<?php

namespace Tests\Feature\Applicant;

use App\Models\ApplicantAccount;
use App\Models\ApplicantIdentity;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_can_send_otp_to_new_phone()
    {
        $response = $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->postJson('/api/v2/applicant/otp/send', [
                'type' => 'PHONE',
                'identifier' => '+5212345678901',
            ]);

        $response->assertOk()
            ->assertJson(['is_new_account' => true]);

        $this->assertDatabaseHas('applicant_accounts', [
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_can_verify_otp()
    {
        $account = ApplicantAccount::create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $identity = ApplicantIdentity::create([
            'account_id' => $account->id,
            'type' => 'PHONE',
            'identifier' => '+5212345678901',
            'is_primary' => true,
            'verification_code' => '123456',
            'verification_code_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->postJson('/api/v2/applicant/otp/verify', [
                'type' => 'PHONE',
                'identifier' => '+5212345678901',
                'code' => '123456',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_can_set_and_login_with_pin()
    {
        $account = ApplicantAccount::create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $identity = ApplicantIdentity::create([
            'account_id' => $account->id,
            'type' => 'PHONE',
            'identifier' => '+5212345678901',
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        $token = $account->createToken('test')->plainTextToken;

        // Set PIN
        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v2/applicant/pin/set', [
                'pin' => '1234',
                'pin_confirmation' => '1234',
            ])
            ->assertOk();

        // Login with PIN
        $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->postJson('/api/v2/applicant/pin/login', [
                'type' => 'PHONE',
                'identifier' => '+5212345678901',
                'pin' => '1234',
            ])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_can_add_email_to_existing_account()
    {
        $account = ApplicantAccount::create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        ApplicantIdentity::create([
            'account_id' => $account->id,
            'type' => 'PHONE',
            'identifier' => '+5212345678901',
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        $token = $account->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v2/applicant/identity/add', [
                'type' => 'EMAIL',
                'identifier' => 'test@example.com',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('applicant_identities', [
            'account_id' => $account->id,
            'type' => 'EMAIL',
            'identifier' => 'test@example.com',
        ]);
    }

    public function test_pin_lockout_after_failed_attempts()
    {
        $account = ApplicantAccount::create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'pin_hash' => Hash::make('1234'),
            'pin_set_at' => now(),
        ]);

        $identity = ApplicantIdentity::create([
            'account_id' => $account->id,
            'type' => 'PHONE',
            'identifier' => '+5212345678901',
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        // 5 intentos fallidos
        for ($i = 0; $i < 5; $i++) {
            $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
                ->postJson('/api/v2/applicant/pin/login', [
                    'type' => 'PHONE',
                    'identifier' => '+5212345678901',
                    'pin' => '0000', // PIN incorrecto
                ]);
        }

        // El 6to intento debe estar bloqueado
        $response = $this->withHeader('X-Tenant-Slug', $this->tenant->slug)
            ->postJson('/api/v2/applicant/pin/login', [
                'type' => 'PHONE',
                'identifier' => '+5212345678901',
                'pin' => '1234', // PIN correcto
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['pin']);
    }
}
```

#### Tests Manuales - Fase 2
```markdown
## Checklist de Pruebas Manuales - Fase 2

### Flujo Nuevo Usuario (Teléfono)
- [ ] POST /api/v2/applicant/otp/send con teléfono nuevo → OTP enviado
- [ ] Verificar que se creó cuenta en applicant_accounts
- [ ] Verificar que se creó identidad en applicant_identities
- [ ] POST /api/v2/applicant/otp/verify con código correcto → Token recibido
- [ ] Verificar que verified_at se actualizó

### Flujo Configurar PIN
- [ ] POST /api/v2/applicant/pin/set con PIN válido → Éxito
- [ ] Verificar que pin_hash se guardó en la cuenta
- [ ] POST /api/v2/applicant/pin/set con PIN muy corto → Error
- [ ] POST /api/v2/applicant/pin/set sin confirmación correcta → Error

### Flujo Login con PIN
- [ ] POST /api/v2/applicant/pin/login con PIN correcto → Token
- [ ] POST /api/v2/applicant/pin/login con PIN incorrecto → Error
- [ ] 5 intentos fallidos → Cuenta bloqueada por 30 min

### Agregar Segunda Identidad
- [ ] Usuario logueado agrega email → OTP enviado al email
- [ ] Verificar email con OTP → Email verificado
- [ ] Ahora puede hacer login con email O teléfono

### Rate Limiting
- [ ] Más de 3 OTPs en 1 hora → Error de rate limit

### Compatibilidad
- [ ] Sistema antiguo (/api/auth/*) sigue funcionando
- [ ] Aplicantes existentes pueden seguir usando sistema antiguo
```

### Criterios de Éxito - Fase 2

- [ ] Migraciones ejecutan sin errores
- [ ] Tests de feature pasan (100%)
- [ ] Flujo completo funciona: OTP → Verificar → Set PIN → Login PIN
- [ ] Multi-identidad funciona: agregar email a cuenta de teléfono
- [ ] Rate limiting funciona
- [ ] Sistema antiguo no se afecta

---

## Fase 3: Tabla Persons y Datos Normalizados

### Objetivo
Crear la estructura para personas y separar datos en tablas normalizadas.

### Dependencias
- Fase 2 completada

### Tareas

#### 3.1 Ejecutar Migraciones
```bash
php artisan migrate --path=database/migrations/2026_01_18_020003_create_persons_table.php
php artisan migrate --path=database/migrations/2026_01_18_020004_create_person_identifications_table.php
php artisan migrate --path=database/migrations/2026_01_18_020005_create_person_addresses_table.php
php artisan migrate --path=database/migrations/2026_01_18_020006_create_person_employments_table.php
php artisan migrate --path=database/migrations/2026_01_18_020007_create_person_references_and_bank_accounts_tables.php
```

#### 3.2 Crear Models
- `Person`
- `PersonIdentification`
- `PersonAddress`
- `PersonEmployment`
- `PersonReference`
- `PersonBankAccount`

#### 3.3 Crear Services
- `PersonService` - CRUD de personas
- `IdentificationService` - Manejo de identificaciones con historial
- `AddressService` - Manejo de direcciones con historial

#### 3.4 Migrar datos existentes
- Extraer datos de `applicants.personal_data` → `persons`
- Extraer CURP/RFC → `person_identifications`
- Extraer direcciones → `person_addresses`
- Extraer empleo → `person_employments`

### Plan de Pruebas - Fase 3
(Similar estructura a fases anteriores)

---

## Fase 4: Empresas y Miembros

### Objetivo
Implementar estructura de empresas (personas morales) con miembros.

### Dependencias
- Fase 3 completada

### Tareas
- Ejecutar migración de companies
- Crear models Company, CompanyMember, CompanyAddress
- Crear CompanyService
- Implementar invitación de miembros
- Implementar permisos por miembro

---

## Fase 5: Documents v2 (Polimórfico) - ✅ Completada

### Objetivo
Migrar a la nueva estructura de documentos polimórfica.

### Dependencias
- Fases 3 y 4 completadas

### Archivos Creados

| Archivo | Descripción |
|---------|-------------|
| `app/Models/DocumentV2.php` | Modelo polimórfico con tipos, categorías, estados, OCR, versionado |
| `database/factories/DocumentV2Factory.php` | Factory con estados para tipos (INE, comprobante, etc) y estados |
| `tests/Unit/Models/DocumentV2Test.php` | 30 tests para relaciones, accessors, status, actions, scopes |

### Características Implementadas

1. **Relaciones Polimórficas**: Los documentos pueden adjuntarse a múltiples entidades:
   - `person_identifications`
   - `person_addresses`
   - `person_employments`
   - `companies`
   - `company_addresses`
   - `applications_v2`

2. **Tipos de Documentos por Categoría**:
   - IDENTITY: INE (front/back), Pasaporte, CURP, RFC, Licencia
   - ADDRESS: Comprobante de domicilio, Recibo de servicios, Contrato de arrendamiento
   - INCOME: Recibo de nómina, Estado de cuenta, Declaración fiscal
   - COMPANY: Acta constitutiva, Poder notarial, Situación fiscal
   - VERIFICATION: Selfie
   - OTHER: Firma, Otros

3. **Estados y Transiciones**:
   - PENDING → APPROVED / REJECTED
   - APPROVED → EXPIRED / SUPERSEDED
   - Métodos: `approve()`, `reject()`, `markExpired()`, `supersede()`

4. **Versionado de Documentos**:
   - `previous_version_id` para historial
   - `version_number` para tracking
   - `newerVersions()` relación inversa

5. **OCR y Verificación**:
   - `ocr_processed`, `ocr_data`, `ocr_confidence`
   - `setOcrData()` método para actualizar

### Comandos de Verificación

```bash
php artisan test tests/Unit/Models/DocumentV2Test.php
# 30 tests, 71 assertions
```

---

## Fase 6: Applications v2 - ✅ Completada

### Objetivo
Migrar a la nueva estructura de aplicaciones.

### Dependencias
- Fase 5 completada

### Archivos Creados

| Archivo | Descripción |
|---------|-------------|
| `app/Models/ApplicationV2.php` | Modelo de solicitudes con soporte individual/empresa, workflow completo |
| `app/Models/ApplicationStatusHistory.php` | Historial de cambios de estado con auditoría |
| `database/factories/ApplicationV2Factory.php` | Factory con estados para flujo completo |
| `database/factories/ApplicationStatusHistoryFactory.php` | Factory para historial |
| `tests/Unit/Models/ApplicationV2Test.php` | 47 tests para relaciones, accessors, status, actions, scopes |

### Características Implementadas

1. **Tipos de Solicitante**:
   - `TYPE_INDIVIDUAL`: Persona física (`person_id`)
   - `TYPE_COMPANY`: Persona moral (`company_id`)
   - Accessor `applicant` retorna Person o Company

2. **Estados y Workflow**:
   - DRAFT → SUBMITTED → IN_REVIEW → ANALYST_REVIEW → SUPERVISOR_REVIEW
   - → APPROVED / REJECTED / CANCELLED
   - APPROVED → SYNCED (sincronizado a sistema externo)

3. **Decisiones**:
   - APPROVED: Aprobación directa o con ajustes
   - REJECTED: Con razón de rechazo
   - COUNTER_OFFER: Contraoferta que el solicitante puede aceptar/rechazar

4. **Verificación y Riesgo**:
   - `verification_checklist`: JSON para checkmarks de verificación
   - `risk_level`: LOW, MEDIUM, HIGH, VERY_HIGH
   - `risk_data`: Datos de evaluación de riesgo (score, bureau, PLD)

5. **Asignación y Auditoría**:
   - `assigned_to`, `assigned_by`, `assigned_at`
   - `decision_by`, `decision_at`, `decision_notes`
   - Historial completo en `ApplicationStatusHistory`

6. **Sincronización Externa**:
   - `external_id`, `external_system`, `sync_data`
   - `markSynced()` para registrar sincronización

7. **Snapshots**:
   - `snapshot_data`: Datos del solicitante al momento de la solicitud
   - `snapshot_references`: Referencias de IDs al momento de la solicitud

### Scopes Disponibles

- `draft()`, `submitted()`, `inReview()`, `approved()`, `rejected()`
- `active()`: Excluye rechazadas, canceladas y sincronizadas
- `forPerson($id)`, `forCompany($id)`
- `assignedToStaff($id)`, `unassigned()`
- `individuals()`, `companies()`
- `riskLevel($level)`
- `expiringSoon($days)`

### Comandos de Verificación

```bash
php artisan test tests/Unit/Models/ApplicationV2Test.php
# 47 tests, 108 assertions

# Todos los tests
php artisan test
# 732 tests, 1794 assertions
```

---

## Fase 7: Limpieza y Deprecación

### Objetivo
Eliminar código y tablas antiguas.

### Dependencias
- Todas las fases anteriores completadas y verificadas

### Tareas
- Marcar rutas antiguas como deprecated
- Migrar frontend a nuevas rutas
- Eliminar tablas antiguas (después de periodo de gracia)
- Eliminar código deprecated

---

## Anexos

### A. Comandos Útiles

```bash
# Ver estado de migraciones
php artisan migrate:status

# Ejecutar migración específica
php artisan migrate --path=database/migrations/NOMBRE.php

# Revertir última migración
php artisan migrate:rollback

# Ejecutar tests de una fase
php artisan test --filter=Staff
php artisan test --filter=Applicant

# Ver logs en tiempo real
tail -f storage/logs/laravel.log
```

### B. Troubleshooting

#### Error: "Column already exists"
```bash
# Verificar si la columna existe
php artisan tinker
>>> Schema::hasColumn('table_name', 'column_name')

# Si existe, modificar migración para verificar antes de agregar
```

#### Error: "Foreign key constraint fails"
```bash
# Verificar orden de migraciones
# Las tablas referenciadas deben existir antes

# Verificar datos huérfanos
SELECT * FROM child_table WHERE parent_id NOT IN (SELECT id FROM parent_table);
```

### C. Contactos

- **Desarrollador principal**: [Tu nombre]
- **DBA**: [Nombre DBA si aplica]
- **Fecha inicio**: [Fecha]
- **Fecha estimada fin**: [Fecha]
