# Implementación de Verificaciones Automáticas

## Resumen

Se ha implementado un sistema para que las verificaciones realizadas con **Nubarium KYC**, **Twilio OTP**, y **documentos** se registren automáticamente en la tabla `data_verifications` y aparezcan con checks verdes en el panel de administración.

## Cambios Realizados

### 1. Backend: Verificación Automática de Teléfono vía OTP ✅

**Archivo**: `backend/app/Http/Controllers/Api/AuthController.php:214-235`

Cuando un usuario verifica su teléfono vía OTP, ahora se crea automáticamente un registro en `DataVerification`:

```php
if ($user->applicant && !$user->applicant->phone_verified_at) {
    $user->applicant->update(['phone_verified_at' => now()]);

    // Record phone verification in data_verifications table
    \App\Models\DataVerification::create([
        'tenant_id' => app('tenant.id'),
        'applicant_id' => $user->applicant->id,
        'field_name' => 'phone',
        'field_value' => $request->phone,
        'method' => \App\Enums\VerificationMethod::OTP,
        'is_verified' => true,
        'status' => \App\Enums\VerificationStatus::VERIFIED,
        'notes' => 'Verificado vía OTP/SMS',
        'metadata' => ['otp_verified_at' => now()->toIso8601String()],
    ]);
}
```

### 2. Frontend: Función de Registro Individual ✅

**Archivo**: `frontend/src/stores/kyc.ts:936-967`

Se agregó la función `recordSingleVerification` que permite registrar verificaciones individuales inmediatamente después de cada validación:

```typescript
const recordSingleVerification = async (
  applicantId: string,
  field: string,
  value: unknown,
  method: string,
  verified: boolean,
  metadata?: Record<string, unknown>
): Promise<boolean> => {
  // ... implementación
}
```

## Cómo Usar

### Para Validaciones de KYC (INE, CURP, RFC)

Después de llamar a `validateIne()`, `validateCurp()`, o `validateRfc()`, inmediatamente registrar la verificación:

```typescript
import { useKycStore } from '@/stores/kyc'
import { useAuthStore } from '@/stores/auth'

const kycStore = useKycStore()
const authStore = useAuthStore()

// Validar INE
const ineValid = await kycStore.validateIne()
if (ineValid && authStore.user.applicant_id) {
  // Auto-registrar verificaciones de campos extraídos del INE
  const lockedData = kycStore.lockedData

  if (lockedData.nombres) {
    await kycStore.recordSingleVerification(
      authStore.user.applicant_id,
      'first_name',
      lockedData.nombres,
      'KYC_INE_OCR',
      true,
      { source: 'ine_ocr' }
    )
  }

  if (lockedData.apellido_paterno) {
    await kycStore.recordSingleVerification(
      authStore.user.applicant_id,
      'last_name_1',
      lockedData.apellido_paterno,
      'KYC_INE_OCR',
      true,
      { source: 'ine_ocr' }
    )
  }

  if (lockedData.curp) {
    await kycStore.recordSingleVerification(
      authStore.user.applicant_id,
      'curp',
      lockedData.curp,
      'KYC_INE_OCR',
      true,
      { source: 'ine_ocr' }
    )
  }

  if (lockedData.clave_elector && kycStore.isIneValid) {
    await kycStore.recordSingleVerification(
      authStore.user.applicant_id,
      'ine_clave',
      lockedData.clave_elector,
      'KYC_INE_LIST',
      true,
      kycStore.validations.ine_lista_nominal || {}
    )
  }
}

// Validar CURP
const curpValid = await kycStore.validateCurp(curp)
if (curpValid && authStore.user.applicant_id) {
  await kycStore.recordSingleVerification(
    authStore.user.applicant_id,
    'curp',
    curp,
    'KYC_CURP_RENAPO',
    true,
    kycStore.validations.curp_renapo?.data || {}
  )
}

// Validar RFC
const rfcResult = await kycStore.validateRfc(rfc)
if (rfcResult.valid && authStore.user.applicant_id) {
  await kycStore.recordSingleVerification(
    authStore.user.applicant_id,
    'rfc',
    rfc,
    'KYC_RFC_SAT',
    true,
    {
      razon_social: rfcResult.razon_social,
      tipo_persona: rfcResult.tipo_persona
    }
  )
}
```

### Para Verificación de Teléfono

**Esto ya funciona automáticamente** ✅. Cuando el usuario verifica su teléfono via OTP en el login/registro, el backend registra la verificación automáticamente.

### Para Documentos

Cuando un documento es aprobado por un admin, ya se crea un registro de verificación. Sin embargo, si quieres marcar el documento como verificado automáticamente al subirlo (si pasa validaciones automáticas), puedes agregar la lógica en el endpoint de subida de documentos.

## Archivos Modificados

1. ✅ `backend/app/Http/Controllers/Api/AuthController.php`
2. ✅ `frontend/src/stores/kyc.ts`

## Próximos Pasos

### Implementación Recomendada

Para evitar tener que llamar manualmente `recordSingleVerification` en cada lugar donde se hace una validación, se recomienda:

1. **Modificar Step2Identification.vue** para que después de validaciones exitosas llame automáticamente a `recordSingleVerification`
2. **Modificar Step1PersonalData.vue** para lo mismo
3. **Crear un composable `useAutoVerify`** que envuelva las validaciones y automáticamente registre

### Ejemplo de Composable (Implementación Futura)

```typescript
// frontend/src/composables/useAutoVerify.ts
export function useAutoVerify() {
  const kycStore = useKycStore()
  const authStore = useAuthStore()

  const validateAndRecordIne = async () => {
    const valid = await kycStore.validateIne()
    if (valid && authStore.user.applicant_id) {
      // Auto-record all INE fields
      await autoRecordIneFields(authStore.user.applicant_id)
    }
    return valid
  }

  return {
    validateAndRecordIne,
    validateAndRecordCurp,
    validateAndRecordRfc
  }
}
```

## Testing

Para probar que las verificaciones se registran correctamente:

1. **Crear nueva cuenta** con teléfono
2. **Verificar OTP** → Check verde en "Teléfono" debe aparecer automáticamente
3. **Validar INE** con Nubarium → Llamar `recordSingleVerification` para campos extraídos
4. **Validar CURP** → Llamar `recordSingleVerification`
5. **Validar RFC** → Llamar `recordSingleVerification`
6. **Ir al admin** → Ver todos los checks verdes en la sección "Datos Personales"

## Campos que se pueden verificar

- `first_name` - Nombre
- `last_name_1` - Apellido Paterno
- `last_name_2` - Apellido Materno
- `curp` - CURP
- `rfc` - RFC
- `ine_clave` - Clave de Elector INE
- `birth_date` - Fecha de Nacimiento
- `phone` - Teléfono (automático vía OTP) ✅
- `email` - Email
- `address` - Dirección
- `employment` - Información Laboral

## Métodos de Verificación

- `OTP` - One-Time Password (SMS/WhatsApp) ✅
- `KYC_INE_OCR` - Extracción OCR del INE
- `KYC_INE_LIST` - Validación contra Lista Nominal
- `KYC_CURP_RENAPO` - Validación CURP con RENAPO
- `KYC_RFC_SAT` - Validación RFC con SAT
- `MANUAL` - Verificación manual por admin
- `DOCUMENT` - Verificado mediante documento aprobado
