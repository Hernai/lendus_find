# Soluciones Aplicadas

## ✅ Problema 1: Error 404 en /api/applicant (RESUELTO)

### Causa
Después del Step 1, cuando el frontend hacía `GET /api/applicant`, recibía un error 404 porque el applicant no existía aún.

### Solución Aplicada
Modificado `backend/app/Http/Controllers/Api/ApplicantController.php:21-29` para usar `getOrCreateApplicant()` en el método `show()`.

**Cambio realizado**:
```php
// ANTES - Devolvía 404 si no existía:
public function show(Request $request): JsonResponse
{
    $user = $request->user();
    $applicant = $user->applicant;

    if (!$applicant) {
        return response()->json([
            'message' => 'Applicant profile not found',
            'data' => null
        ], 404);
    }
    // ...
}

// AHORA - Crea el applicant automáticamente:
public function show(Request $request): JsonResponse
{
    $applicant = $this->getOrCreateApplicant($request);
    $applicant->load(['addresses', 'currentEmployment', 'primaryBankAccount']);

    return response()->json([
        'data' => $this->formatApplicant($applicant)
    ]);
}
```

### Resultado
Ahora cuando el usuario completa el Step 1, el applicant se crea automáticamente y las siguientes peticiones a `/api/applicant` funcionan correctamente.

---

## 🔍 Problema 2: RFC no se valida con Nubarium

### Estado Actual
El frontend tiene el código para validar RFC con Nubarium:
- `frontend/src/stores/kyc.ts:509` - Función `validateRfc()`
- `frontend/src/views/applicant/onboarding/Step2Identification.vue:74` - Función `validateRfcWithSat()`

La validación se ejecuta automáticamente cuando:
1. El usuario escribe un RFC con formato válido (12-13 caracteres)
2. Nubarium está configurado (`kycStore.hasNubarium === true`)
3. Hay un debounce de 500ms después de que el usuario deja de escribir

### Cómo Verificar si Funciona

1. **Abrir la consola del navegador** (F12)
2. **Ir al Step 2** (Identificación)
3. **Escribir un RFC válido** (ejemplo: `GUCH871022HCS`)
4. **Esperar 500ms** sin escribir nada
5. **Ver en la consola** si aparece:
   ```
   [API] Request to: /api/kyc/rfc/validate | Tenant: lendusdemoii
   ```

### Si NO se ejecuta la validación automática:

**Posibles causas**:

1. **Nubarium no está disponible** - Verificar:
   ```javascript
   // En la consola del navegador (F12):
   import { useKycStore } from '@/stores/kyc'
   const kycStore = useKycStore()
   console.log('Nubarium disponible:', kycStore.hasNubarium)
   ```

2. **El RFC no tiene formato válido** - Debe ser 12-13 caracteres alfanuméricos:
   - Persona Moral: `ABC123456XYZ` (12 caracteres)
   - Persona Física: `GUCH871022HCS` (13 caracteres)

3. **Error silencioso** - Ver en Network tab (F12 → Network) si hay peticiones a `/api/kyc/rfc/validate` que fallan

### Validación Manual

Si la validación automática no funciona, puedes ejecutarla manualmente desde la consola del navegador:

```javascript
// En la consola del navegador (F12):
import { useKycStore } from '@/stores/kyc'
const kycStore = useKycStore()

// Validar RFC
const result = await kycStore.validateRfc('GUCH871022HCS')
console.log('Resultado:', result)
```

### Logs del Backend

Para ver si las peticiones están llegando al backend:

```bash
cd backend
tail -f storage/logs/laravel.log | grep -i "rfc"
```

Deberías ver logs como:
```
[2026-01-14 XX:XX:XX] local.INFO: Nubarium: Got 403, attempting token refresh
[2026-01-14 XX:XX:XX] local.INFO: Nubarium: JWT token generated successfully
[2026-01-14 XX:XX:XX] local.INFO: Nubarium: Retry after token refresh {"endpoint":"/api/v2/sat/rfc/validate","new_status":200,"successful":true}
```

---

## Próximos Pasos Recomendados

1. **Probar el flujo completo**:
   - Crear un nuevo usuario
   - Completar Step 1 (Datos Personales) ✅
   - Ir a Step 2 (Identificación)
   - Escribir un RFC válido y verificar si se valida automáticamente

2. **Si el RFC no se valida**:
   - Verificar en la consola del navegador (F12) si hay errores
   - Verificar en Network tab si se hace la petición a `/api/kyc/rfc/validate`
   - Revisar los logs del backend para ver si llegó la petición

3. **Alternativa manual**:
   - Si la validación automática no funciona, el usuario puede hacer clic en un botón "Validar RFC" (si existe en el UI)
   - O la validación se puede hacer al enviar el formulario del Step 2

---

## Testing

### Test 1: Applicant 404 (RESUELTO ✅)
```bash
# 1. Crear nuevo usuario con OTP
# 2. Completar Step 1
# 3. Verificar que NO hay error 404 en /api/applicant
# Expected: 200 OK
```

### Test 2: RFC Validation
```bash
# 1. Ir a Step 2
# 2. Escribir RFC: GUCH871022HCS
# 3. Esperar 500ms
# 4. Ver en la consola si se hace la petición a /api/kyc/rfc/validate
# Expected: Se valida automáticamente y muestra "RFC válido" o razón social
```
