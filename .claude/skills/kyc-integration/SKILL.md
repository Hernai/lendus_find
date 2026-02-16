---
name: kyc-integration
description: Integración KYC con Nubarium para LendusFind. Usar al trabajar con validación de identidad, CURP, RFC, INE, biometría o compliance.
---

# KYC Integration (Nubarium)

## Cuándo aplica
Seguir esta guía al trabajar con servicios de KYC, validación de identidad mexicana, biometría facial, o cumplimiento OFAC/PLD.

## Service Architecture

```
app/Services/ExternalApi/Nubarium/
├── BaseNubariumService.php          # Base: HTTP client, auth, error handling
├── NubariumIdentityService.php      # CURP, RFC, INE validación
├── NubariumBiometricsService.php    # Face match, liveness detection
├── NubariumComplianceService.php    # OFAC screening, PLD blacklist
└── NubariumServiceFacade.php        # Agrega todos los sub-servicios
```

Factory: `KycServiceFactory` crea instancias por tenant con gestión automática de tokens.

## Identity Validation Flow

### CURP Validation
```
Frontend → POST /api/v2/applicant/kyc/curp/validate
         → KycController → NubariumIdentityService::validateCurp()
         → Nubarium API → Retorna datos personales validados
         → DataVerification record creado
```

### RFC Validation
```
Frontend → POST /api/v2/applicant/kyc/rfc/validate
         → NubariumIdentityService::validateRfc()
         → Valida RFC contra SAT
```

### INE Validation
```
Frontend → POST /api/v2/applicant/kyc/ine/validate
         → NubariumIdentityService::validateIne()
         → OCR extraction + validación contra INE database
```

## Biometrics Flow

### Face Match
```
Frontend (IneCapture.vue) → Captura foto INE
Frontend (SelfieCapture.vue) → Captura selfie
POST /api/v2/applicant/kyc/face-match
→ NubariumBiometricsService::matchFaces()
→ Compara selfie vs foto INE → score de similitud
```

### Liveness Detection
```
POST /api/v2/applicant/kyc/liveness
→ NubariumBiometricsService::checkLiveness()
→ Verifica que la imagen es de una persona real
```

## Compliance Checks

```
POST /api/v2/applicant/kyc/ofac/check → NubariumComplianceService::checkOfac()
POST /api/v2/applicant/kyc/pld/check  → NubariumComplianceService::checkPld()
```

## Mexican ID Formats

| Document | Format | Validation |
|----------|--------|------------|
| **CURP** | 18 chars alfanuméricos | Dígito verificador, regex pattern |
| **RFC** | 13 chars (persona física), 12 chars (persona moral) | Algoritmo SAT |
| **CLABE** | 18 dígitos numéricos | Dígito de control (`ValidClabe` rule) |
| **INE** | Clave elector + OCR + folio | Validación Nubarium |
| **Phone** | 10 dígitos | E.164 con prefijo +52 |
| **Postal Code** | 5 dígitos | Lookup SEPOMEX (barrios, municipio, estado) |

## Frontend KYC Components

- `IneCapture.vue` — Captura frontal/trasera de INE con cámara
- `SelfieCapture.vue` — Captura de selfie para face match

Composables:
- `useKycValidation()` — Validación de CURP, RFC, INE
- `useKycBiometrics()` — Face match, liveness
- `useKycCompliance()` — OFAC, PLD checks

## KYC Status Flow

```
PENDING → VERIFIED (todas las validaciones pasaron)
        → REJECTED (alguna validación falló)
        → EXPIRED (verificación caducó)
```

Modelo `DataVerification` — Registros polimórficos de verificación:
```php
$verification = DataVerification::create([
    'verifiable_type' => Person::class,
    'verifiable_id' => $person->id,
    'type' => 'curp_validation',
    'status' => 'VERIFIED',
    'provider' => 'nubarium',
    'response_data' => $apiResponse,
]);
```

## TenantApiConfig for KYC

```php
TenantApiConfig::where('tenant_id', $tenantId)
    ->where('provider', 'nubarium')
    ->where('service_type', 'kyc')
    ->where('is_active', true)
    ->first();

// Credentials: api_key (usuario) + api_secret (password) — encrypted
```

## Error Handling

Nubarium errors se mapean a mensajes en español:
```php
return match ($errorCode) {
    'CURP_NOT_FOUND' => 'No se encontró el CURP en RENAPO',
    'RFC_INVALID' => 'El RFC no es válido según el SAT',
    'INE_EXPIRED' => 'La credencial INE está vencida',
    'FACE_NO_MATCH' => 'La foto no coincide con la identificación',
    default => 'Error en la validación de identidad',
};
```
