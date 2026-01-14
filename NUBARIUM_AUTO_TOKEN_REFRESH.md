# Renovación Automática del Token de Nubarium

## Problema Resuelto

Cuando el token JWT de Nubarium expiraba (después de ~60 minutos), las peticiones de validación de INE, CURP, RFC, etc. fallaban con error **403 Forbidden**, y el usuario veía el error en pantalla.

## Solución Implementada

Se modificó el servicio de Nubarium para que **detecte automáticamente cuando el token ha expirado** y lo renueve **sin que el usuario lo note**.

### Cambios Realizados

#### 1. Manejo de 403 Forbidden (Token Expirado)

**Archivo**: `backend/app/Services/ExternalApi/NubariumService.php:335-361`

Antes, solo se manejaba el error **401 Unauthorized**. Ahora también se maneja **403 Forbidden**, que es el código que Nubarium devuelve cuando el token JWT ha expirado.

```php
// Antes (solo 401):
if ($response->status() === 401 && !$this->tokenRefreshAttempted) {
    // Renovar token y reintentar
}

// Ahora (401 y 403):
if (($response->status() === 401 || $response->status() === 403) && !$this->tokenRefreshAttempted) {
    Log::info('Nubarium: Got ' . $response->status() . ', attempting token refresh', [
        'endpoint' => $endpoint,
        'tenant_id' => $this->tenant->id,
    ]);

    $newToken = $this->handleUnauthorized();

    if ($newToken) {
        // Reintenta automáticamente con el nuevo token
        $http = $this->serviceHttp($service);
        if ($timeout) {
            $http = $http->timeout($timeout);
        }

        $response = $method === 'GET'
            ? $http->get($endpoint, $payload)
            : $http->post($endpoint, $payload);

        Log::info('Nubarium: Retry after token refresh', [
            'endpoint' => $endpoint,
            'new_status' => $response->status(),
            'successful' => $response->successful(),
        ]);
    }
}
```

#### 2. Documentación Actualizada

**Archivo**: `backend/app/Services/ExternalApi/NubariumService.php:171-174`

Se actualizó la documentación del método `handleUnauthorized()` para reflejar que maneja tanto 401 como 403:

```php
/**
 * Handle 401 Unauthorized or 403 Forbidden by refreshing token and retrying once.
 * Nubarium returns 403 when the JWT token has expired.
 */
protected function handleUnauthorized(): ?string
```

## Cómo Funciona

### Flujo Automático de Renovación

1. **Usuario intenta validar INE** → Frontend hace POST a `/api/kyc/ine/validate`
2. **Backend llama a Nubarium** con el token JWT cacheado
3. **Nubarium responde 403** (token expirado)
4. **Backend detecta el 403** automáticamente
5. **Backend limpia el cache** del token viejo
6. **Backend genera un nuevo token** usando las credenciales (Basic Auth)
7. **Backend reintenta la petición** con el nuevo token
8. **Nubarium responde 200 OK** con los datos del INE
9. **Usuario ve el resultado** sin saber que hubo un error intermedio

### Protección contra Loops Infinitos

El sistema solo intenta renovar el token **una vez por petición**. Si después de renovar el token aún recibe 403, no lo vuelve a intentar (para evitar loops infinitos).

```php
protected bool $tokenRefreshAttempted = false;

if (($response->status() === 401 || $response->status() === 403) && !$this->tokenRefreshAttempted) {
    // Solo entra aquí una vez por petición
    $newToken = $this->handleUnauthorized();
    // $this->tokenRefreshAttempted = true (se marca dentro de handleUnauthorized)
}
```

## Configuración del Token

El token JWT de Nubarium se cachea por **58 minutos** (`3500 segundos`) para evitar regenerarlo en cada petición:

**Archivo**: `backend/app/Services/ExternalApi/NubariumService.php:62`

```php
protected int $tokenCacheDuration = 3500; // ~58 minutes (tokens expire in 60 minutes)
```

Los tokens de Nubarium tienen una duración de **60 minutos**. Se cachean por 58 minutos para tener un margen de seguridad.

## Logs para Debugging

Cuando se renueva el token, se generan logs automáticos que puedes revisar:

```bash
cd backend
tail -f storage/logs/laravel.log | grep Nubarium
```

Ejemplo de logs cuando se renueva el token:

```
[2026-01-14 XX:XX:XX] local.INFO: Nubarium: Got 403, attempting token refresh {"endpoint":"/api/v2/ine/validate","tenant_id":"61db7123-f322-4b88-8637-b0cbcea3f3e0"}
[2026-01-14 XX:XX:XX] local.INFO: Nubarium: Attempting token refresh due to expired token (401/403) {"tenant_id":"61db7123-f322-4b88-8637-b0cbcea3f3e0"}
[2026-01-14 XX:XX:XX] local.INFO: Nubarium: Attempting JWT generation {"tenant_id":"61db7123-f322-4b88-8637-b0cbcea3f3e0","url":"https://api.nubarium.com/global/account/v1/generate-jwt","username":"XXX***"}
[2026-01-14 XX:XX:XX] local.INFO: Nubarium: JWT token generated successfully {"tenant_id":"61db7123-f322-4b88-8637-b0cbcea3f3e0"}
[2026-01-14 XX:XX:XX] local.INFO: Nubarium: Retry after token refresh {"endpoint":"/api/v2/ine/validate","new_status":200,"successful":true}
```

## Limpiar Cache del Token Manualmente (si es necesario)

Si necesitas forzar la regeneración del token de Nubarium:

```bash
cd backend
php artisan tinker --execute="
\$tenant = App\Models\Tenant::where('slug', 'lendusdemoii')->first();
\$cacheKey = 'nubarium_jwt_' . \$tenant->id;
Cache::forget(\$cacheKey);
echo '✅ Token cache cleared' . PHP_EOL;
"
```

O también puedes limpiar todo el cache de Laravel:

```bash
php artisan cache:clear
```

## Beneficios

✅ **Invisible para el usuario**: No ve ningún error cuando el token expira
✅ **Automático**: No requiere intervención manual
✅ **Resiliente**: Si la renovación falla, el error se propaga correctamente
✅ **Performante**: El token se cachea 58 minutos, evitando regenerarlo en cada petición
✅ **Logs completos**: Toda la renovación queda registrada para debugging

## Testing

Para probar que funciona:

1. Limpia el cache del token: `Cache::forget('nubarium_jwt_61db7123-f322-4b88-8637-b0cbcea3f3e0')`
2. Intenta validar un INE desde el frontend
3. La primera petición generará un nuevo token automáticamente
4. Verifica los logs para confirmar que se renovó: `tail -f storage/logs/laravel.log | grep Nubarium`

## Servicios Afectados (Todos)

Esta mejora aplica a **todos los servicios de Nubarium** que requieren JWT:

- ✅ Validación de INE (OCR + Lista Nominal)
- ✅ Validación de CURP (RENAPO)
- ✅ Validación de RFC (SAT)
- ✅ Consulta OFAC
- ✅ Consulta Listas PLD
- ✅ Historial IMSS/ISSSTE
- ✅ Validación de Cédula Profesional
- ✅ Validación de CEP SPEI
- ✅ Token para SDK Biométrico

Todos estos servicios ahora renovarán el token automáticamente si reciben 401 o 403.
