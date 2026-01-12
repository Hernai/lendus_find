# Plan de Integración KYC con Nubarium

## Resumen

Este documento describe cómo se integra la verificación de identidad (KYC) usando Nubarium en el flujo de onboarding.

**Regla principal**: El flujo KYC con Nubarium **solo se activa si el tenant tiene Nubarium configurado**. Si no está configurado, el flujo actual permanece sin cambios.

---

## Dos Flujos Posibles

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         INICIO DEL ONBOARDING                               │
│                                                                             │
│                    ¿Tenant tiene Nubarium configurado?                      │
│                                                                             │
│                         ┌───────┴───────┐                                   │
│                         │               │                                   │
│                        SÍ              NO                                   │
│                         │               │                                   │
│                         ▼               ▼                                   │
│              ┌──────────────────┐  ┌──────────────────┐                    │
│              │  FLUJO NUBARIUM  │  │  FLUJO ACTUAL    │                    │
│              │  (este documento)│  │  (sin cambios)   │                    │
│              └──────────────────┘  └──────────────────┘                    │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Flujo SIN Nubarium (actual, sin cambios)
```
Simulación → Step 1 (Datos) → Step 2 (Domicilio) → Step 3 (Empleo) → ...
            Usuario captura     Usuario captura      Usuario captura
            manualmente         manualmente          manualmente
```

### Flujo CON Nubarium (nuevo)
```
Simulación → STEP 0 (KYC) → Step 1 (Datos) → Step 2 (Domicilio) → Step 3 (Empleo) → ...
             INE + Selfie    Datos 🔒         Datos 🔒            Usuario captura
             Verificación    bloqueados       bloqueados          manualmente
```

---

## Flujo CON Nubarium (Detallado)

### PASO 1: Verificación de Identidad (NUEVO - Step 0)

**Objetivo**: Capturar INE, extraer datos, verificar identidad

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                      STEP 0: VERIFICACIÓN DE IDENTIDAD                      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  1. CAPTURA INE FRENTE                                                      │
│     ┌─────────────────────────────────┐                                    │
│     │  📷 Cámara con marco guía       │                                    │
│     │  "Posiciona tu INE aquí"        │                                    │
│     └─────────────────────────────────┘                                    │
│                                                                             │
│  2. CAPTURA INE REVERSO                                                     │
│     ┌─────────────────────────────────┐                                    │
│     │  📷 Cámara con marco guía       │                                    │
│     │  "Ahora el reverso"             │                                    │
│     └─────────────────────────────────┘                                    │
│                                                                             │
│  3. SELFIE + LIVENESS (prueba de vida)                                      │
│     ┌─────────────────────────────────┐                                    │
│     │  🤳 "Parpadea lentamente"       │                                    │
│     │  🤳 "Gira la cabeza"            │                                    │
│     └─────────────────────────────────┘                                    │
│                                                                             │
│  4. VALIDACIÓN AUTOMÁTICA                                                   │
│     ┌─────────────────────────────────┐                                    │
│     │  ✅ Extrayendo datos (OCR)      │                                    │
│     │  ✅ Verificando lista nominal   │                                    │
│     │  ✅ Comparando rostros (94%)    │                                    │
│     │  ✅ Validando CURP con RENAPO   │                                    │
│     │  ✅ Verificando OFAC            │                                    │
│     └─────────────────────────────────┘                                    │
│                                                                             │
│  5. RESULTADO                                                               │
│     ┌─────────────────────────────────────────────────────────────────────┐│
│     │ ✅ IDENTIDAD VERIFICADA                                             ││
│     │                                                                     ││
│     │ Nombre:     JUAN CARLOS GARCÍA RODRÍGUEZ                            ││
│     │ CURP:       GARJ850101HDFRRL09                                      ││
│     │ Nacimiento: 01/01/1985                                              ││
│     │ Sexo:       MASCULINO                                               ││
│     │ Dirección:  CALLE REFORMA 123, COL. CENTRO, CDMX 06000             ││
│     │ Vigencia:   2030                                                    ││
│     │                                                                     ││
│     │ [✓] Confirmo que estos datos son correctos                          ││
│     │                                                                     ││
│     │                         [Continuar →]                               ││
│     └─────────────────────────────────────────────────────────────────────┘│
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Datos extraídos del INE (OCR)**:
- Nombre completo
- CURP
- Fecha de nacimiento
- Sexo
- Dirección completa (calle, colonia, CP, municipio, estado)
- Clave de elector
- Vigencia

---

### PASO 2: Datos Personales (Step 1 modificado)

**Los datos del INE están BLOQUEADOS (read-only)**

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         STEP 1: DATOS PERSONALES                            │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ 🔒 DATOS VERIFICADOS (del INE - no editables)                       │   │
│  │                                                                     │   │
│  │   Nombre(s):         JUAN CARLOS                         🔒        │   │
│  │   Apellido Paterno:  GARCÍA                              🔒        │   │
│  │   Apellido Materno:  RODRÍGUEZ                           🔒        │   │
│  │   Fecha Nacimiento:  01/01/1985                          🔒        │   │
│  │   Sexo:              MASCULINO                           🔒        │   │
│  │   CURP:              GARJ850101HDFRRL09                  🔒        │   │
│  │                                                                     │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ───────────────────────────────────────────────────────────────────────   │
│                                                                             │
│  📝 DATOS ADICIONALES (editables)                                          │
│                                                                             │
│   Teléfono:        [55 1234 5678        ]                                  │
│   Email:           [juan@email.com      ]                                  │
│   Estado Civil:    [Soltero        ▼    ]                                  │
│   Nacionalidad:    [Mexicana       ▼    ]                                  │
│                                                                             │
│                              [Continuar →]                                  │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

### PASO 3: Domicilio (Step 2 modificado)

**La dirección del INE está BLOQUEADA, pero el usuario puede indicar si cambió**

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            STEP 2: DOMICILIO                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐   │
│  │ 🔒 DIRECCIÓN EN TU INE (no editable)                                │   │
│  │                                                                     │   │
│  │   Calle:      CALLE REFORMA 123                          🔒        │   │
│  │   Colonia:    CENTRO                                     🔒        │   │
│  │   C.P.:       06000                                      🔒        │   │
│  │   Municipio:  Cuauhtémoc                                 🔒        │   │
│  │   Estado:     Ciudad de México                           🔒        │   │
│  │                                                                     │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│  ───────────────────────────────────────────────────────────────────────   │
│                                                                             │
│  ¿Tu domicilio ACTUAL es diferente al de tu INE?                           │
│                                                                             │
│   (○) No, vivo en la dirección de mi INE                                   │
│   (○) Sí, mi domicilio actual es diferente                                 │
│                                                                             │
│  ┌─ Si selecciona "Sí" ────────────────────────────────────────────────┐   │
│  │                                                                     │   │
│  │  📝 DOMICILIO ACTUAL (editable)                                     │   │
│  │                                                                     │   │
│  │   C.P.:        [      ] → auto-completa colonias                   │   │
│  │   Colonia:     [▼ Seleccionar...]                                  │   │
│  │   Calle:       [                              ]                    │   │
│  │   No. Ext:     [      ]    No. Int: [      ]                       │   │
│  │                                                                     │   │
│  └─────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│                              [Continuar →]                                  │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

### PASOS SIGUIENTES (sin cambios significativos)

| Step | Nombre | Cambios con Nubarium |
|------|--------|---------------------|
| Step 3 | Empleo | Sin cambios - datos laborales no están en INE |
| Step 4 | Detalles del Préstamo | Sin cambios |
| Step 5 | Documentos | **INE ya no se pide** (ya fue verificada) |
| Step 6 | Referencias | Sin cambios |
| Step 7 | Revisión y Firma | Muestra resumen de verificación KYC |

---

## Resumen: Qué Datos son Bloqueados

### Campos BLOQUEADOS (extraídos del INE, read-only)

| Campo | Fuente | Editable |
|-------|--------|----------|
| Nombre(s) | OCR INE | ❌ No |
| Apellido Paterno | OCR INE | ❌ No |
| Apellido Materno | OCR INE | ❌ No |
| Fecha de Nacimiento | OCR INE | ❌ No |
| Sexo | OCR INE | ❌ No |
| CURP | OCR INE + RENAPO | ❌ No |
| Dirección INE | OCR INE | ❌ No |

### Campos EDITABLES (usuario debe capturar)

| Campo | Razón |
|-------|-------|
| Teléfono | No está en INE |
| Email | No está en INE |
| Estado Civil | No está en INE |
| Nacionalidad | No está en INE |
| Domicilio Actual | Puede ser diferente al de INE |
| Datos de Empleo | No está en INE |
| Referencias | No está en INE |
| RFC | Opcional, no siempre necesario |

---

## Implementación Técnica

### Detección del Flujo

```typescript
// Al iniciar onboarding, verificar si hay Nubarium
const { data: kycServices } = await api.get('/kyc/services')

const hasNubarium = kycServices.nubarium?.configured === true

if (hasNubarium) {
  // Ir a Step 0 (Verificación KYC)
  router.push('/onboarding/kyc-verification')
} else {
  // Ir a Step 1 (flujo actual sin cambios)
  router.push('/onboarding/step1')
}
```

### Store de Datos KYC

```typescript
// stores/kyc.ts
export const useKycStore = defineStore('kyc', {
  state: () => ({
    verified: false,
    lockedData: {
      nombres: null,
      apellido_paterno: null,
      apellido_materno: null,
      fecha_nacimiento: null,
      sexo: null,
      curp: null,
      direccion_ine: {
        calle: null,
        colonia: null,
        cp: null,
        municipio: null,
        estado: null,
      }
    },
    validations: {
      ine_ocr: null,
      ine_lista_nominal: null,
      curp_renapo: null,
      face_match: null,
      liveness: null,
      ofac: null,
    }
  })
})
```

### Componente de Campo Bloqueado

```vue
<!-- components/common/LockedField.vue -->
<template>
  <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
    <label class="text-xs text-gray-500 uppercase tracking-wide">{{ label }}</label>
    <div class="flex items-center justify-between mt-1">
      <span class="text-gray-900 font-medium">{{ value }}</span>
      <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
        <!-- Lock icon -->
        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
      </svg>
    </div>
  </div>
</template>
```

---

## Soporte Multi-Dispositivo

### Móvil
- Usa `<input type="file" capture="environment">` para INE
- Usa `<input type="file" capture="user">` para selfie
- Abre cámara nativa del sistema

### Desktop
- Usa `navigator.mediaDevices.getUserMedia()` para webcam
- Muestra stream en vivo con overlay/guía
- Fallback: botón para subir archivo

```vue
<template>
  <!-- Móvil -->
  <div v-if="isMobile">
    <input type="file" accept="image/*" capture="environment" @change="onCapture" />
  </div>

  <!-- Desktop -->
  <div v-else>
    <video ref="webcam" autoplay playsinline></video>
    <button @click="takePhoto">Capturar</button>
    <p class="text-sm text-gray-500">
      ¿Sin cámara? <label class="text-primary-600 cursor-pointer">Sube una imagen</label>
    </p>
  </div>
</template>
```

---

## APIs Backend (Ya Implementadas)

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `GET /api/kyc/services` | GET | Verificar si Nubarium está configurado |
| `POST /api/kyc/ine/validate` | POST | Enviar imágenes INE, recibir datos OCR + validación |
| `POST /api/kyc/biometric/token` | POST | Obtener token para SDK de liveness |
| `POST /api/kyc/curp/validate` | POST | Validar CURP con RENAPO |
| `POST /api/kyc/ofac/check` | POST | Verificar listas de sanciones |

---

## Manejo de Errores

| Error | Mensaje al Usuario | Acción |
|-------|-------------------|--------|
| INE borrosa | "La imagen no es clara. Toma otra foto." | Reintentar captura |
| INE no vigente | "Tu INE está vencida. Renuévala." | Bloquear, mostrar instrucciones |
| Face match < 85% | "No pudimos verificar tu identidad." | Reintentar selfie |
| CURP inválido | "El CURP no coincide con RENAPO." | Contactar soporte |
| OFAC match | "No es posible continuar." | Bloquear, contactar soporte |
| Servicio no disponible | "Servicio temporalmente no disponible." | Reintentar en 30s |

---

## Archivos a Crear/Modificar

### Nuevos
- `frontend/src/views/applicant/onboarding/StepKycVerification.vue` - Step 0
- `frontend/src/components/kyc/IneCapture.vue` - Captura de INE
- `frontend/src/components/kyc/SelfieCapture.vue` - Captura de selfie
- `frontend/src/components/kyc/ValidationProgress.vue` - Progreso de validación
- `frontend/src/components/common/LockedField.vue` - Campo bloqueado
- `frontend/src/composables/useKycValidation.ts` - Lógica KYC
- `frontend/src/composables/useDeviceCapture.ts` - Captura móvil/desktop
- `frontend/src/stores/kyc.ts` - Store de datos KYC

### Modificar
- `frontend/src/views/applicant/onboarding/Step1PersonalData.vue` - Mostrar datos bloqueados
- `frontend/src/views/applicant/onboarding/Step2Address.vue` - Mostrar dirección bloqueada
- `frontend/src/views/applicant/onboarding/Step5Documents.vue` - No pedir INE si ya verificada
- `frontend/src/router/index.ts` - Agregar ruta Step 0

---

## Flujo Visual Completo

```
┌────────────────────────────────────────────────────────────────────────────┐
│                                                                            │
│   SIMULADOR                                                                │
│   ────────                                                                 │
│   Usuario simula préstamo                                                  │
│                           │                                                │
│                           ▼                                                │
│   ┌────────────────────────────────────────┐                              │
│   │  ¿Nubarium configurado?                │                              │
│   └────────────────────────────────────────┘                              │
│              │                    │                                        │
│             SÍ                   NO                                        │
│              │                    │                                        │
│              ▼                    │                                        │
│   ┌────────────────────┐         │                                        │
│   │  STEP 0: KYC       │         │                                        │
│   │  ──────────────    │         │                                        │
│   │  1. Foto INE       │         │                                        │
│   │  2. Selfie         │         │                                        │
│   │  3. Validación     │         │                                        │
│   │  4. Confirmación   │         │                                        │
│   │                    │         │                                        │
│   │  → Guarda datos    │         │                                        │
│   │    verificados     │         │                                        │
│   └────────────────────┘         │                                        │
│              │                    │                                        │
│              ▼                    ▼                                        │
│   ┌────────────────────────────────────────┐                              │
│   │  STEP 1: DATOS PERSONALES              │                              │
│   │  ─────────────────────────             │                              │
│   │                                        │                              │
│   │  CON Nubarium:      SIN Nubarium:     │                              │
│   │  ┌──────────────┐   ┌──────────────┐  │                              │
│   │  │ 🔒 Nombre    │   │ [ Nombre   ] │  │                              │
│   │  │ 🔒 CURP      │   │ [ CURP     ] │  │                              │
│   │  │ 🔒 Fecha Nac │   │ [ Fecha    ] │  │                              │
│   │  │              │   │              │  │                              │
│   │  │ [ Teléfono ] │   │ [ Teléfono ] │  │                              │
│   │  │ [ Email    ] │   │ [ Email    ] │  │                              │
│   │  └──────────────┘   └──────────────┘  │                              │
│   └────────────────────────────────────────┘                              │
│                           │                                                │
│                           ▼                                                │
│   ┌────────────────────────────────────────┐                              │
│   │  STEP 2: DOMICILIO                     │                              │
│   │  ─────────────────                     │                              │
│   │                                        │                              │
│   │  CON Nubarium:      SIN Nubarium:     │                              │
│   │  ┌──────────────┐   ┌──────────────┐  │                              │
│   │  │ 🔒 Dir. INE  │   │ [ C.P.     ] │  │                              │
│   │  │              │   │ [ Colonia  ] │  │                              │
│   │  │ ¿Diferente?  │   │ [ Calle    ] │  │                              │
│   │  │ [○] No       │   │ [ Estado   ] │  │                              │
│   │  │ [○] Sí →     │   │              │  │                              │
│   │  │   [Nueva dir]│   │              │  │                              │
│   │  └──────────────┘   └──────────────┘  │                              │
│   └────────────────────────────────────────┘                              │
│                           │                                                │
│                           ▼                                                │
│   ┌────────────────────────────────────────┐                              │
│   │  STEP 3-6: Sin cambios                 │                              │
│   └────────────────────────────────────────┘                              │
│                           │                                                │
│                           ▼                                                │
│   ┌────────────────────────────────────────┐                              │
│   │  STEP 7: REVISIÓN                      │                              │
│   │  ───────────────                       │                              │
│   │                                        │                              │
│   │  CON Nubarium:                         │                              │
│   │  ┌──────────────────────────────────┐ │                              │
│   │  │ ✅ INE Verificada                 │ │                              │
│   │  │ ✅ CURP Validado (RENAPO)         │ │                              │
│   │  │ ✅ Face Match: 94%                │ │                              │
│   │  │ ✅ Sin alertas OFAC               │ │                              │
│   │  └──────────────────────────────────┘ │                              │
│   │                                        │                              │
│   │            [Firmar y Enviar]           │                              │
│   └────────────────────────────────────────┘                              │
│                                                                            │
└────────────────────────────────────────────────────────────────────────────┘
```

---

*Documento actualizado: 2026-01-11*
