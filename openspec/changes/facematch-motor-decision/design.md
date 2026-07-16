## Context

El flujo de onboarding **dinámico** (MoneyCapital) captura la selfie en el paso `kyc_face` (tipo `kyc_selfie`) y la sube como documento `SELFIE` — sin compararla con la INE. La biometría facial (`validateFaceMatch`, `validateLiveness`) existe pero solo se invoca desde el flujo KYC **legacy** (`useKycValidation` / `StepKycVerification`), que el flujo dinámico no portó (sí portó el OCR del INE vía `runIneOcr`).

El motor de decisión (`DecisionInputCollector` → `DecisionEngineService`) recolecta `kyc_status` e `identity_mismatch` (este último = `kyc_data.ine_verification.mismatch`, OCR-vs-RENAPO), pero **no** el resultado facial. Y `kyc_status = VERIFIED` (`VerificationService::hasCompletedKyc`) solo exige CURP, nombre, primer apellido y fecha de nacimiento verificados contra RENAPO — el facematch no es requisito. El motor está en `SHADOW` para MoneyCapital; activar `ACTIVE` sin cerrar esto permitiría auto-ofertar identidades faciales no verificadas.

Restricciones: multitenancy (aislar por tenant), la política del motor no se expone en la API pública, Regla 21 (fail-open: una falla técnica del proveedor nunca detiene al cliente).

## Goals / Non-Goals

**Goals:**
- Ejecutar el facematch (selfie vs INE) en el flujo dinámico, en silencio, sin bloquear al cliente.
- Persistir siempre el resultado (coincide / no-coincide / falla técnica).
- Que el motor mande a `REVIEW` cuando el facematch no coincide, per-política y auditable.
- Umbral configurable y versionado en la política de producto (default 80).

**Non-Goals:**
- Liveness / prueba de vida (excluido en el grilling).
- Rechazo automático por facematch (siempre `REVIEW`, nunca `REJECT`).
- Modificar `hasCompletedKyc` / `kyc_status` (se preserva su semántica global).
- Tocar el flujo KYC legacy.
- Resolver el 403 de Nubarium del entorno de pruebas (prod ya funciona; es tema aparte).

## Decisions

### 1. El facematch se dispara en el frontend al confirmar el paso de selfie
Análogo a `runIneOcr`/`confirmIne` en `DynamicOnboardingView`: al confirmar la selfie se llama `KycController::validateFaceMatch` con la selfie del paso y la imagen de la INE ya capturada en `kyc_ine` (`dynamic_data.kyc_ine.front_image`; el paso `kyc_ine` precede a `kyc_face`). Condicionado a `tenantStore.hasKycProvider` (igual que el OCR del INE).
- **Alternativa (backend, al persistir el paso)**: el frontend solo sube la selfie y el backend corre el facematch en el job de persistencia. Descartada: rompe el patrón existente del INE, y el frontend ya tiene ambas imágenes en memoria (evita releer documentos).
- **Rationale**: reutiliza el patrón probado del INE, endpoint ya existente, cero dependencias nuevas.

### 2. El resultado se persiste SIEMPRE como verificación `face_match`
Hoy `KycController::validateFaceMatch` solo registra la verificación cuando `match=true` (el `if ($result['match'])` no tiene rama para el no-match). Se agrega el registro del **no-match**: `verificationService->verify('face_match', 'failed', ...)` con `is_verified=false` y el `score` en metadata. Falla técnica (Nubarium error/timeout) NO registra pass ni fail (queda ausente = "no concluido").
- **Alternativa (guardar en `person.kyc_data`)**: descartada por consistencia — el pass ya usa `DataVerification` field `face_match`; el collector lee de un solo lugar.
- **Rationale**: sin registrar el fallo, el motor no puede verlo y no queda en el expediente.

### 3. El motor lee `face_match` como insumo y aplica `face_match_failed → REVIEW`
`DecisionInputCollector` agrega `face_match` a `inputs` con tres estados: `passed` (verificación `face_match` con is_verified=true), `failed` (is_verified=false), `null`/missing (no concluyó → se suma a `missing` como `kyc`/`clabe`, para reintento+timeout). `DecisionEngineService` agrega la regla: `face_match` en estado `failed` ⇒ `ruleHit` efecto `REVIEW` (junto a `clabe_mismatch`/`phone_gate_flag`), con razón legible "La identidad facial no coincide con la INE".
- **Alternativa (incluir `face_match` en `hasCompletedKyc`)**: un facematch fallido dejaría `kyc_status` != VERIFIED → el motor lo trataría como `missing: kyc` → REVIEW. Descartada: cambia la semántica global de `kyc_status` (lo leen otros flujos y tenants), y obliga a que TODO tenant con KYC ejecute facematch o quede eternamente incompleto.
- **Rationale**: localizado, per-política (solo actúa donde la política lo incluya), aparece en `rule_hits` para auditoría, no acopla `kyc_status`.

### 4. El umbral vive en la política de producto (`face_match.min_score`, default 80)
La política de producto gana `rules.face_match.min_score`. El **backend** resuelve el umbral desde la política activa del producto de la solicitud y lo pasa a `validateFaceMatch` (no confía en el `threshold` del request para tenants con política). El seeder de MoneyCapital lo siembra en 80.
- **Alternativa (frontend manda el threshold)**: descartada — el cliente no debe poder relajar el umbral; la política es la fuente de verdad.
- **Rationale**: calibrable desde el configurador sin deploy; coherente con scoring/bandas ya configurables; sigue sin exponerse en la API pública.

### 5. Fail-open y silencioso
Falla técnica de Nubarium (no un no-match) ⇒ el cliente **avanza** igual y la solicitud queda con el insumo ausente → `REVIEW` por timeout (Regla 21). El frontend nunca muestra el resultado del facematch al cliente (encaja con "no mostrar que se está validando").

## Risks / Trade-offs

- **Falso negativo por foto/luz → REVIEW innecesario (carga del analista)** → Es `REVIEW` no `REJECT` (no quema al cliente); umbral configurable para calibrar durante el piloto.
- **El facematch corre en el frontend; un cliente podría no invocarlo** → Sin verificación `face_match` registrada, el collector lo marca `missing` → REVIEW; el motor **nunca auto-oferta sin el insumo**. No es un bypass a la oferta.
- **Dependencia de Nubarium en prod** → fail-open: falla técnica no bloquea, va a REVIEW.
- **Regla activa por error en un tenant sin selfie** → la regla es per-política y la ejecución es condicional a `hasKycProvider`; un tenant sin el paso/proveedor no dispara nada.

## Migration Plan

- **Sin migración de datos** (aplica a solicitudes nuevas; no hay backfill).
- **Orden de deploy**: (1) backend — registro del no-match, insumo y regla, lectura del umbral; (2) seeder — `face_match.min_score` en la política de MoneyCapital; (3) frontend — ejecutar facematch al confirmar la selfie.
- **Rollout gradual**: el motor sigue en `SHADOW` para MoneyCapital, así que la regla solo registra (no actúa) hasta pasar a `ACTIVE` — permite observar cuántas solicitudes caerían a REVIEW antes de activar.
- **Rollback**: quitar `face_match.min_score` de la política (o versión previa) neutraliza la regla sin tocar código.

## Open Questions

- ¿El umbral se resuelve por producto (política de producto) o admite override por tenant? Propuesta: política de producto; se confirma al escribir specs.
- ¿La ejecución del facematch en el frontend debe ser bloqueante del avance (esperar respuesta) o optimista (avanzar y registrar async)? Propuesta: optimista con registro async, consistente con el avance optimista del runner; a validar en specs.
