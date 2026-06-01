# Auto-Grabación de Verificaciones KYC - Implementado

## Resumen

Se implementó el sistema de auto-grabación de verificaciones KYC que **automáticamente guarda y bloquea** los campos verificados por Nubarium y OTP en la tabla `data_verifications` cuando se validan durante el flujo de onboarding.

## ¿Qué Problema Resuelve?

**Antes:**
- Los campos validados por KYC (CURP, RFC, INE) NO se guardaban en `data_verifications`
- NO se marcaban como bloqueados (`is_locked = false`)
- NO se registraba QUIÉN y CUÁNDO se verificaron
- NO había historial de validaciones (`validation_history`)

**Después:**
- ✅ Los campos SE graban automáticamente después de validar con Nubarium
- ✅ Se marcan como bloqueados (`is_locked = true`) para campos KYC automatizados
- ✅ Se registra quién lo verificó (`verified_by = user_id`)
- ✅ Se registra cuándo se verificó (`created_at`, `updated_at`)
- ✅ Se guarda metadata de la validación (datos de Nubarium, razón social, etc.)

## Campos que se Auto-Graban y Bloquean

### 1. Validación de INE (KYC_INE_OCR)
**Bloquea:** ✅ Sí (método automatizado)

Cuando se valida el INE con OCR de Nubarium, se graban automáticamente:
- `first_name` (nombres)
- `last_name_1` (apellido paterno)
- `last_name_2` (apellido materno)
- `birth_date` (fecha de nacimiento)
- `gender` (sexo: H/M)
- `birth_state` (entidad de nacimiento extraída del CURP)
- `curp` (CURP extraído del INE)
- `ine_clave` (clave de elector)
- `ine_ocr` (número OCR de 13 dígitos)
- `address_street` (calle del INE)
- `address_neighborhood` (colonia del INE)
- `address_postal_code` (CP del INE)
- `address_city` (municipio/ciudad del INE)
- `address_state` (estado del INE)

**Todos estos campos quedan BLOQUEADOS y no pueden ser editados** porque fueron verificados por un método automatizado KYC.

### 2. Validación de Lista Nominal INE (KYC_INE_LIST)
**Bloquea:** ✅ Sí (método automatizado)

Cuando el INE se valida contra la lista nominal del INE:
- `ine_clave` - Se actualiza con método `KYC_INE_LIST` (más fuerte que OCR)

### 3. Validación de CURP con RENAPO (KYC_CURP_RENAPO)
**Bloquea:** ✅ Sí (método automatizado)

Cuando el CURP se valida contra RENAPO:
- `curp` - Se actualiza con método `KYC_CURP_RENAPO` (reemplaza la verificación de INE OCR)

**Este es el campo MÁS CRÍTICO** porque confirma que el CURP es real y coincide con RENAPO.

### 4. Validación de RFC con SAT (KYC_RFC_SAT)
**Bloquea:** ✅ Sí (método automatizado)

Cuando el RFC se valida contra el SAT:
- `rfc` - RFC verificado por SAT
- **Metadata guardada:**
  - `razon_social` - Nombre o razón social registrada en SAT
  - `tipo_persona` - M (Moral) o F (Física)
  - `tipo_persona_label` - Persona Moral / Persona Física

### 5. Validación de Teléfono con OTP
**Bloquea:** ✅ Sí (método automatizado)

⚠️ **PENDIENTE:** Actualmente el teléfono verificado por OTP NO se está grabando porque el OTP ocurre ANTES de crear el applicant.

**Solución recomendada:** Grabar la verificación de teléfono justo después de crear el applicant en el onboarding.

## Cambios Realizados

### 1. Backend - Sin cambios necesarios ✅

El backend YA TIENE todo lo necesario:
- ✅ `DataVerification::recordVerification()` - Ya marca `is_locked = true` para métodos automatizados
- ✅ `VerificationMethod::isAutomated()` - Ya incluye OTP, KYC_CURP_RENAPO, KYC_RFC_SAT, KYC_INE_OCR, etc.
- ✅ `KycController::recordVerifications()` - Ya existe endpoint POST `/api/kyc/verifications`
- ✅ `validation_history` en JSONB - Ya guarda el historial de validaciones

**El backend ya estaba perfecto**, solo faltaba que el frontend lo llamara.

### 2. Frontend - Store de KYC (`frontend/src/stores/kyc.ts`)

#### `validateIne()` - Modificado
**Antes:**
```typescript
const validateIne = async () => {
  // ...validaba INE con Nubarium
  // NO grababa las verificaciones
}
```

**Después:**
```typescript
const validateIne = async (applicantId?: string) => {
  // ...valida INE con Nubarium

  // ✅ Auto-graba todas las verificaciones del INE
  if (applicantId && response.data.is_valid) {
    await recordSingleVerification(applicantId, 'first_name', ocr.nombres, 'KYC_INE_OCR', true, ...)
    await recordSingleVerification(applicantId, 'last_name_1', ocr.apellido_paterno, 'KYC_INE_OCR', true, ...)
    await recordSingleVerification(applicantId, 'last_name_2', ocr.apellido_materno, 'KYC_INE_OCR', true, ...)
    await recordSingleVerification(applicantId, 'birth_date', ocr.fecha_nacimiento, 'KYC_INE_OCR', true, ...)
    // ... y más campos
  }
}
```

#### `validateCurp()` - Modificado
**Antes:**
```typescript
const validateCurp = async (curp?: string) => {
  // ...validaba CURP con RENAPO
  // NO grababa la verificación
}
```

**Después:**
```typescript
const validateCurp = async (curp?: string, applicantId?: string) => {
  // ...valida CURP con RENAPO

  // ✅ Auto-graba verificación si exitosa
  if (applicantId && response.data.valid) {
    await recordSingleVerification(
      applicantId,
      'curp',
      curpToValidate,
      'KYC_CURP_RENAPO',
      true,
      response.data.data || { source: 'renapo' }
    )
  }
}
```

#### `validateRfc()` - Modificado
**Antes:**
```typescript
const validateRfc = async (rfc: string) => {
  // ...validaba RFC con SAT
  // NO grababa la verificación
}
```

**Después:**
```typescript
const validateRfc = async (rfc: string, applicantId?: string) => {
  // ...valida RFC con SAT

  // ✅ Auto-graba verificación si exitosa
  if (applicantId && isValid) {
    await recordSingleVerification(
      applicantId,
      'rfc',
      response.data.data.rfc,
      'KYC_RFC_SAT',
      true,
      {
        razon_social: razonSocial,
        tipo_persona: response.data.data.tipo_persona,
        tipo_persona_label: response.data.data.tipo_persona_label
      }
    )
  }
}
```

### 3. Frontend - Composable (`frontend/src/composables/useKycValidation.ts`)

**Modificado para pasar `applicantId` a las funciones de validación:**

```typescript
export function useKycValidation() {
  const kycStore = useKycStore()
  const applicantStore = useApplicantStore() // ✅ Nuevo

  const runValidations = async () => {
    // ✅ Obtiene applicant_id para auto-grabar
    const applicantId = applicantStore.applicant?.id

    // ✅ Pasa applicantId a validateIne
    await kycStore.validateIne(applicantId)

    // ✅ Pasa applicantId a validateCurp
    await kycStore.validateCurp(undefined, applicantId)
  }

  const retryValidations = async () => {
    // ✅ También en retry
    const applicantId = applicantStore.applicant?.id
    await kycStore.validateIne(applicantId)
    await kycStore.validateCurp(undefined, applicantId)
  }
}
```

### 4. Frontend - Step2Identification (`frontend/src/views/applicant/onboarding/Step2Identification.vue`)

**Modificado para pasar `applicantId` a validateRfc:**

```typescript
const applicantStore = useApplicantStore() // ✅ Nuevo

const validateRfcWithSat = async () => {
  // ✅ Pasa applicant_id si está disponible
  const applicantId = applicantStore.applicant?.id
  const result = await kycStore.validateRfc(form.rfc, applicantId)

  // ✅ Ya no necesita manualmente grabar - lo hace el store automáticamente
}
```

## Flujo Completo

### Durante el Onboarding (Primera Vez)

1. **Usuario captura INE** → StepKycVerification
   - Se capturan fotos del INE (frente y reverso)
   - Se envían a Nubarium para OCR

2. **Nubarium procesa INE** → `kycStore.validateIne(applicantId)`
   - ✅ Extrae datos: nombres, apellidos, CURP, dirección, etc.
   - ✅ **AUTO-GRABA** ~15 campos en `data_verifications`
   - ✅ **MARCA COMO BLOQUEADOS** (`is_locked = true`)
   - ✅ Guarda quién verificó (`verified_by`)
   - ✅ Guarda cuándo (`created_at`)

3. **Valida CURP con RENAPO** → `kycStore.validateCurp(curp, applicantId)`
   - ✅ Confirma que el CURP existe en RENAPO
   - ✅ **AUTO-GRABA** verificación de CURP
   - ✅ **ACTUALIZA** método a `KYC_CURP_RENAPO` (más fuerte que INE OCR)
   - ✅ **BLOQUEA** el campo CURP definitivamente

4. **Usuario ingresa RFC** → Step2Identification
   - Usuario escribe su RFC manualmente
   - Auto-valida con SAT mientras escribe (debounce 500ms)

5. **Valida RFC con SAT** → `kycStore.validateRfc(rfc, applicantId)`
   - ✅ Confirma que el RFC existe en SAT
   - ✅ **AUTO-GRABA** verificación de RFC
   - ✅ Guarda razón social y tipo de persona
   - ✅ **BLOQUEA** el campo RFC

### En Pasos Posteriores

6. **Step 1 - Datos Personales**
   - ✅ Campos bloqueados se muestran con ícono de candado
   - ✅ NO se pueden editar (readonly)
   - ✅ Se muestra método de verificación (badge: "OCR de INE", "CURP RENAPO", etc.)

7. **Step 3 - Dirección**
   - ✅ Dirección del INE aparece bloqueada
   - ✅ Usuario puede confirmar o agregar más detalles (número exterior/interior)

## Verificar que Funciona

### 1. En Base de Datos

```sql
-- Ver verificaciones grabadas para un applicant
SELECT
  field_name,
  field_value,
  method,
  is_verified,
  is_locked,
  verified_by,
  created_at,
  metadata
FROM data_verifications
WHERE applicant_id = 'UUID-DEL-APPLICANT'
ORDER BY created_at DESC;
```

**Deberías ver:**
- ~15 registros después de validar INE
- `is_locked = true` para todos los campos KYC
- `method` = 'KYC_INE_OCR', 'KYC_CURP_RENAPO', 'KYC_RFC_SAT', etc.
- `verified_by` = ID del usuario autenticado
- `metadata` con datos adicionales (razón social para RFC, etc.)

### 2. En la Consola del Navegador

Busca estos logs durante el onboarding:

```
[KYC Store] Applicant ID for auto-recording: <UUID>
[KYC Store] Auto-recording INE verifications...
[KYC Store] Auto-recording first_name verification (KYC_INE_OCR)
[KYC Store] first_name verification recorded successfully
... (más campos)
[KYC Store] INE verifications auto-recorded

[KYC Store] Auto-recording CURP RENAPO validation...
[KYC Store] CURP RENAPO validation auto-recorded

[KYC Store] Auto-recording RFC SAT validation...
[KYC Store] RFC SAT validation auto-recorded
```

### 3. En Network Tab (DevTools)

Busca requests a:
```
POST /api/kyc/verifications
```

**Payload ejemplo:**
```json
{
  "applicant_id": "UUID",
  "verifications": [
    {
      "field": "first_name",
      "value": "JUAN",
      "method": "KYC_INE_OCR",
      "verified": true,
      "metadata": { "source": "ine_ocr" }
    },
    {
      "field": "curp",
      "value": "JUAZ850101HDFLRN08",
      "method": "KYC_CURP_RENAPO",
      "verified": true,
      "metadata": { "source": "renapo", ... }
    }
  ]
}
```

## Pendientes

### 1. Teléfono Verificado por OTP

**Problema:** El OTP se valida ANTES de crear el applicant, por lo que no hay `applicant_id` disponible.

**Solución recomendada:**
Después de crear el applicant en Step1, grabar la verificación del teléfono:

```typescript
// En Step1PersonalData.vue, después de crear applicant
if (authStore.user?.phone_verified_at) {
  await kycStore.recordSingleVerification(
    newApplicant.id,
    'phone',
    authStore.user.phone,
    'OTP',
    true,
    { otp_verified_at: authStore.user.phone_verified_at }
  )
}
```

### 2. Mostrar Campos Bloqueados en Admin Panel

**Ubicación:** `/admin/solicitudes/:id`

**Implementar:**
1. Cargar verificaciones del applicant: `GET /api/kyc/verifications/{applicantId}`
2. Mostrar campos bloqueados con:
   - Ícono de candado 🔒
   - Badge con método de verificación
   - Tooltip con fecha y quién verificó
   - Input deshabilitado (readonly)

### 3. Historial de Validaciones

Implementar UI para ver el historial completo de validaciones de un applicant:
- Mostrar todas las veces que se validó un campo
- Quién lo validó
- Qué método se usó
- Metadata de cada validación

## Beneficios

1. **Trazabilidad Completa**
   - Sabes exactamente QUÉ datos fueron verificados
   - Sabes CUÁNDO se verificaron
   - Sabes QUIÉN los verificó
   - Tienes la metadata de Nubarium/SAT

2. **Seguridad**
   - Campos verificados por KYC NO pueden ser editados
   - El usuario NO puede cambiar su CURP, nombre, etc. después de validarlo
   - Evita fraude y suplantación de identidad

3. **Auditoría**
   - Cumplimiento regulatorio
   - Puedes demostrar que validaste la identidad del cliente
   - Tienes el historial completo en caso de revisión

4. **UX Mejorada**
   - Usuario ve claramente qué campos están verificados
   - No tiene que volver a ingresar datos ya validados
   - Más rápido completar el onboarding

## Status

🎉 **IMPLEMENTADO Y LISTO PARA PROBAR**

- ✅ Backend ya tenía todo necesario
- ✅ Frontend modificado para auto-grabar verificaciones
- ✅ validateIne, validateCurp, validateRfc implementados
- ✅ useKycValidation composable actualizado
- ✅ Step2Identification actualizado
- ⏳ **PENDIENTE:** Grabar verificación de teléfono con OTP
- ⏳ **PENDIENTE:** UI en admin panel para mostrar campos bloqueados

---

**Fecha:** 14 de enero de 2026
**Archivos modificados:** 3
- `frontend/src/stores/kyc.ts`
- `frontend/src/composables/useKycValidation.ts`
- `frontend/src/views/applicant/onboarding/Step2Identification.vue`
