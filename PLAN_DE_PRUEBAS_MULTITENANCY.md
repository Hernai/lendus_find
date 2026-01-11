# Plan de Pruebas: Multi-Tenancy

## Datos de Prueba

### Tenant 1: Lendus Demo (slug: `demo`)
| Tipo | Cantidad |
|------|----------|
| Staff | 10 usuarios |
| Solicitantes | 18 usuarios |
| Productos | 3 |
| Solicitudes | 17 |

### Tenant 2: LendusDemoII (slug: `lendusdemoii`)
| Tipo | Cantidad |
|------|----------|
| Staff | 6 usuarios |
| Solicitantes | 6 usuarios |
| Productos | 3 |
| Solicitudes | 6 |

---

## Credenciales de Prueba

### Tenant: demo
| Rol | Email | Password |
|-----|-------|----------|
| SUPER_ADMIN | superadmin@lendus.mx | password |
| ADMIN | admin@lendus.mx | password |
| SUPERVISOR | carlos.ramírez@lendus.mx | password |
| ANALYST | fernando.díaz@lendus.mx | password |
| ANALYST | patricia.moreno@lendus.mx | password |

### Tenant: lendusdemoii
| Rol | Email | Password |
|-----|-------|----------|
| SUPER_ADMIN | superadmin@lendusdemoii.mx | password |
| ADMIN | admin@lendusdemoii.mx | password |
| SUPERVISOR | roberto.sánchez@lendusdemoii.mx | password |
| ANALYST | miguel.torres@lendusdemoii.mx | password |

---

## Pruebas de Aislamiento de Tenant

### 1. ADMIN solo ve datos de su tenant

**Pasos:**
1. Login como `admin@lendus.mx`
2. Ir a `/admin/solicitudes`
3. Verificar que ve 17 solicitudes
4. Logout

5. Login como `admin@lendusdemoii.mx`
6. Ir a `/admin/solicitudes`
7. Verificar que ve 6 solicitudes

**Resultado esperado:** Cada admin solo ve solicitudes de su tenant.

---

### 2. ADMIN no puede acceder a otro tenant

**Pasos:**
1. Login como `admin@lendus.mx`
2. Intentar acceder API con header `X-Tenant-ID: lendusdemoii`

```bash
curl -X GET "http://localhost:8000/api/admin/applications" \
  -H "Authorization: Bearer {TOKEN_ADMIN_DEMO}" \
  -H "X-Tenant-ID: lendusdemoii"
```

**Resultado esperado:** 403 Forbidden - "You do not have access to this tenant."

---

### 3. SUPER_ADMIN puede cambiar entre tenants

**Pasos:**
1. Login como `superadmin@lendus.mx`
2. En el header, usar el TenantSwitcher
3. Seleccionar "LendusDemoII"
4. Verificar que la página se recarga
5. Ir a `/admin/solicitudes`
6. Verificar que ahora ve 6 solicitudes

**Resultado esperado:** Super Admin puede ver datos de cualquier tenant.

---

### 4. Productos son específicos por tenant

**Pasos:**
1. Login como `admin@lendus.mx`
2. Ir a `/admin/productos`
3. Verificar productos: Crédito Personal, Crédito Nómina, Arrendamiento

4. Login como `admin@lendusdemoii.mx`
5. Ir a `/admin/productos`
6. Verificar productos: Crédito Express, Crédito PyME Plus, Crédito Automotriz

**Resultado esperado:** Cada tenant tiene sus propios productos.

---

### 5. Usuarios son específicos por tenant

**Pasos:**
1. Login como `admin@lendus.mx`
2. Ir a `/admin/usuarios`
3. Verificar que ve 10 usuarios staff
4. Verificar que NO ve usuarios de lendusdemoii

5. Login como `admin@lendusdemoii.mx`
6. Ir a `/admin/usuarios`
7. Verificar que ve 6 usuarios staff

**Resultado esperado:** Cada admin solo ve usuarios de su tenant.

---

## Pruebas de Filtrado por Rol

### 6. ANALYST solo ve solicitudes asignadas

**Pasos:**
1. Login como `fernando.díaz@lendus.mx` (ANALYST en demo)
2. Ir a `/admin/solicitudes`
3. Verificar que solo ve 4 solicitudes (las asignadas a él)

4. Login como `patricia.moreno@lendus.mx` (ANALYST en demo)
5. Ir a `/admin/solicitudes`
6. Verificar que ve 0 solicitudes (ninguna asignada)

**Resultado esperado:** Cada analyst solo ve sus solicitudes asignadas.

---

### 7. SUPERVISOR ve todas las solicitudes del tenant

**Pasos:**
1. Login como `carlos.ramírez@lendus.mx` (SUPERVISOR en demo)
2. Ir a `/admin/solicitudes`
3. Verificar que ve 17 solicitudes

**Resultado esperado:** Supervisor ve todas las solicitudes de su tenant.

---

### 8. Filtros respetan permisos de rol

**Pasos:**
1. Login como `fernando.díaz@lendus.mx` (ANALYST)
2. Ir a `/admin/solicitudes?status=SUBMITTED`
3. Verificar que solo ve solicitudes SUBMITTED que estén asignadas a él

4. Login como `carlos.ramírez@lendus.mx` (SUPERVISOR)
5. Ir a `/admin/solicitudes?assigned_to={analyst_id}`
6. Verificar que puede filtrar por cualquier analista

**Resultado esperado:** Los filtros funcionan dentro de los límites de permisos del rol.

---

## Pruebas de API

### 9. Verificar endpoint /api/admin/applications

```bash
# Como ANALYST (solo asignadas)
curl -X GET "http://localhost:8000/api/admin/applications" \
  -H "Authorization: Bearer {TOKEN_ANALYST}" \
  -H "X-Tenant-ID: demo"
# Espera: meta.total = 4

# Como SUPERVISOR (todas)
curl -X GET "http://localhost:8000/api/admin/applications" \
  -H "Authorization: Bearer {TOKEN_SUPERVISOR}" \
  -H "X-Tenant-ID: demo"
# Espera: meta.total = 17

# Como ADMIN (todas de su tenant)
curl -X GET "http://localhost:8000/api/admin/applications" \
  -H "Authorization: Bearer {TOKEN_ADMIN}" \
  -H "X-Tenant-ID: demo"
# Espera: meta.total = 17
```

---

### 10. Verificar endpoint /api/admin/users

```bash
# Como ADMIN de demo
curl -X GET "http://localhost:8000/api/admin/users" \
  -H "Authorization: Bearer {TOKEN_ADMIN_DEMO}" \
  -H "X-Tenant-ID: demo"
# Espera: meta.total = 10 (solo staff, no applicants)

# Como ADMIN de lendusdemoii
curl -X GET "http://localhost:8000/api/admin/users" \
  -H "Authorization: Bearer {TOKEN_ADMIN_LII}" \
  -H "X-Tenant-ID: lendusdemoii"
# Espera: meta.total = 6
```

---

## Pruebas de Frontend

### 11. TenantSwitcher solo visible para SUPER_ADMIN

**Pasos:**
1. Login como `admin@lendus.mx` (ADMIN)
2. Verificar que NO ve el TenantSwitcher en el header

3. Login como `superadmin@lendus.mx` (SUPER_ADMIN)
4. Verificar que SÍ ve el TenantSwitcher en el header

---

### 12. Navegación respeta permisos

| Elemento | ANALYST | SUPERVISOR | ADMIN | SUPER_ADMIN |
|----------|---------|------------|-------|-------------|
| Dashboard | ✓ | ✓ | ✓ | ✓ |
| Solicitudes | ✓ | ✓ | ✓ | ✓ |
| Productos | ✗ | ✗ | ✓ | ✓ |
| Usuarios | ✗ | ✗ | ✓ | ✓ |
| Reportes | ✗ | ✓ | ✓ | ✓ |
| Configuración | ✗ | ✗ | ✓ | ✗ |
| Tenants | ✗ | ✗ | ✗ | ✓ |

---

## Comandos de Verificación Rápida

```bash
# Ejecutar desde /backend

# Verificar permisos de usuarios
php artisan tinker --execute="
use App\Models\User;
\$users = User::whereIn('type', ['ANALYST', 'SUPERVISOR', 'ADMIN', 'SUPER_ADMIN'])->get();
foreach (\$users as \$u) {
    echo \"{\$u->name} ({\$u->type->value}): canViewAll=\" . (\$u->canViewAllApplications() ? 'Y' : 'N') . \"\n\";
}
"

# Contar datos por tenant
php artisan tinker --execute="
use App\Models\Application;
use App\Models\Tenant;
foreach (Tenant::all() as \$t) {
    echo \"{\$t->slug}: \" . Application::where('tenant_id', \$t->id)->count() . \" apps\n\";
}
"
```

---

## Matriz de Resultados

| # | Prueba | Resultado | Notas |
|---|--------|-----------|-------|
| 1 | ADMIN ve solo su tenant | | |
| 2 | ADMIN bloqueado cross-tenant | | |
| 3 | SUPER_ADMIN cambia tenant | | |
| 4 | Productos por tenant | | |
| 5 | Usuarios por tenant | | |
| 6 | ANALYST ve solo asignadas | | |
| 7 | SUPERVISOR ve todas | | |
| 8 | Filtros respetan rol | | |
| 9 | API applications | | |
| 10 | API users | | |
| 11 | TenantSwitcher visibility | | |
| 12 | Navegación por rol | | |
