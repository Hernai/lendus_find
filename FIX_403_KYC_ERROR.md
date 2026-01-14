# Fix: 403 Forbidden en Validación de INE

## Problema

Al intentar validar el INE vía Nubarium, recibes un error 403 Forbidden:

```
POST http://localhost:8000/api/kyc/ine/validate
Status: 403 Forbidden
```

## Causa

El endpoint `/api/kyc/ine/validate` requiere autenticación válida (token Sanctum). El error 403 indica que:

1. El token de autenticación ha expirado
2. El token no existe en localStorage
3. El usuario no pertenece al tenant actual

## Solución Rápida

### Opción 1: Refrescar el token (Recomendado)

1. **Cerrar sesión y volver a iniciar sesión**:
   - Ve a tu panel de usuario
   - Click en "Cerrar sesión"
   - Vuelve a iniciar sesión con tu teléfono y OTP

Esto generará un nuevo token válido que durará más tiempo.

### Opción 2: Verificar el token en localStorage

Abre la consola del navegador (F12) y ejecuta:

```javascript
// Ver si existe el token
console.log(localStorage.getItem('auth_token'))

// Ver el tenant actual
console.log(localStorage.getItem('selected_tenant_id'))
```

Si el token es `null` o está vacío, necesitas iniciar sesión de nuevo.

### Opción 3: Debug detallado

Si sigues teniendo el error después de re-autenticarte, verifica en la consola del navegador:

```javascript
// En Network tab del DevTools, revisa los headers de la petición:
// - Authorization: Bearer <token>
// - X-Tenant-ID: lendusdemoii
// - X-XSRF-TOKEN: <csrf_token>
```

## Verificación Técnica

Para confirmar que el problema está resuelto, ejecuta esto en la consola del navegador ANTES de intentar validar el INE:

```javascript
// 1. Verificar que el token existe
const token = localStorage.getItem('auth_token')
console.log('Token presente:', !!token)

// 2. Verificar el tenant
const tenant = localStorage.getItem('selected_tenant_id') || 'lendusdemoii'
console.log('Tenant:', tenant)

// 3. Hacer una petición de prueba al endpoint de KYC services
fetch('http://localhost:8000/api/kyc/services', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'X-Tenant-ID': tenant,
    'Accept': 'application/json'
  }
})
.then(r => r.json())
.then(data => console.log('✅ KYC Services disponibles:', data))
.catch(err => console.error('❌ Error:', err))
```

Si este test funciona, entonces el problema estaba en el token y ya está resuelto.

## Verificación Backend

Si el problema persiste, verifica en el backend:

```bash
cd backend

# Ver logs en tiempo real
tail -f storage/logs/laravel.log

# Probar directamente con tinker
php artisan tinker --execute="
\$token = App\Models\PersonalAccessToken::latest()->first();
echo 'Last token created: ' . \$token->created_at . PHP_EOL;
echo 'Token name: ' . \$token->name . PHP_EOL;
echo 'Tokenable (User) ID: ' . \$token->tokenable_id . PHP_EOL;
"
```

## Causa Raíz (Información Técnica)

El middleware `EnsureUserBelongsToTenant` en `backend/app/Http/Middleware/EnsureUserBelongsToTenant.php:32-36` está rechazando la petición.

Esto sucede cuando:
- `$request->user()` es null (no autenticado)
- `$user->tenant_id !== app('tenant.id')` (tenant no coincide)

La validación es correcta y es una medida de seguridad. La solución es simplemente re-autenticarse.

## Configuración Actual Verificada

✅ Nubarium está configurado para tenant `lendusdemoii`
✅ El servicio está activo (`is_active: true`)
✅ Las credenciales están presentes (`api_key` existe)
✅ El usuario pertenece al tenant correcto

El único problema es el **token de autenticación**.

## Próximos Pasos

1. Cierra sesión
2. Vuelve a iniciar sesión
3. Intenta validar el INE de nuevo
4. Si el problema persiste, comparte los headers de la petición fallida para diagnóstico adicional
