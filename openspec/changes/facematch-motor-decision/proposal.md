## Why

En el onboarding dinámico (MoneyCapital y similares) la selfie se **captura y se sube como documento**, pero **no se compara con la INE**: el flujo dinámico portó el OCR del INE pero no la biometría facial (`validateFaceMatch` solo vive en el flujo legacy). En consecuencia, una identidad facial que **no coincide** con la INE puede llegar a `kyc_status = VERIFIED` (que solo exige CURP/nombre/apellido/fecha de nacimiento contra RENAPO) y **auto-ofertarse** cuando el motor esté en ACTIVE. La matriz de decisión no considera el resultado facial. Cerrar este hueco es requisito para activar el motor con seguridad.

## What Changes

- **Ejecutar el facematch automáticamente** al confirmar el paso de selfie en el flujo dinámico (análogo a como se ejecuta el OCR del INE), comparando la selfie con la imagen de la INE ya capturada. Condicionado a que el tenant tenga proveedor KYC activo (`hasKycProvider`).
- **Registrar SIEMPRE el resultado** del facematch (coincide y no-coincide). Hoy un no-match no se persiste; sin registro el motor no puede leerlo ni queda en el expediente.
- **Nueva regla en el motor de decisión**: `face_match_failed → REVIEW` (junto a `clabe_mismatch` / `identity_mismatch`). Un facematch que no coincide manda la solicitud a revisión manual — **nunca rechazo automático** (un no-match puede ser falso negativo por foto/luz).
- **Umbral configurable por política**: el `min_score` del facematch vive en la política del motor (default **80**), ajustable desde el configurador del admin sin deploy.
- **Fail-open y silencioso**: si Nubarium falla técnicamente (timeout/error, no un no-match claro) no se bloquea al cliente; se marca para REVIEW (Regla 21, ya vigente). El facematch corre sin mostrar el resultado al cliente y el cliente **siempre avanza**.

## Capabilities

### New Capabilities
- **`facematch-biometrico`**: ejecución del facematch (selfie vs INE) en el onboarding dinámico y registro persistente de su resultado (coincide / no-coincide / falla técnica) en el expediente, condicionado a proveedor KYC.

### Modified Capabilities
- **`decision-engine`**: agrega el insumo `face_match` (recolectado por `DecisionInputCollector`) y la regla `face_match_failed → REVIEW` a las salidas del motor.
- **`decision-policy`**: agrega el umbral del facematch (`face_match.min_score`, default 80) como parámetro configurable y versionado de la política de producto; sigue sin exponerse en la API pública.

## Impact

- **Backend**: `DecisionInputCollector` (recolectar `face_match_passed`), `DecisionEngineService` (regla nueva + razón legible), `VerificationService`/`KycController` (registrar el no-match, hoy ausente), `DecisionPolicy` (leer `face_match.min_score`), seeder de MoneyCapital (agregar el umbral). El endpoint de facematch (`KycController::validateFaceMatch`) ya existe.
- **Frontend**: paso de selfie del flujo dinámico (`KycSelfieStepRenderer` / `DynamicOnboardingView` / `stores/onboarding.ts`) — ejecutar el facematch al confirmar, en silencio, con la imagen de la INE del paso `kyc_ine`.
- **Config**: política de producto de MoneyCapital gana `face_match.min_score`. Recordar la whitelist de `formatRules` **no** aplica (el umbral es interno, no debe llegar al frontend público).
- **Sin migración de datos**: aplica a solicitudes nuevas; no hay backfill de expedientes previos.

## Non-goals

- **Liveness / prueba de vida**: fuera de alcance (decisión del grilling). Solo facematch.
- **Rechazo automático** por facematch: no. La salida es REVIEW, no REJECT.
- **Tocar `kyc_status` / `hasCompletedKyc`**: no se modifican; su semántica global se preserva. El facematch influye solo vía la regla del motor.
- **Flujo KYC legacy**: no se toca; el cambio es sobre el flujo dinámico.
- **Resolver el 403 de Nubarium**: es del entorno de pruebas, no de producción; se atiende aparte (contrato/whitelist), no en este cambio.
