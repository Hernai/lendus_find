## 1. Backend — registrar siempre el resultado del facematch

- [x] 1.1 En `KycController::validateFaceMatch`, persistir SIEMPRE el resultado en `person.kyc_data['face_match'] = { passed, score, at }` (coincide y no-coincide); mantener el `verify('face_match','passed')` actual para el expediente cuando coincide
- [x] 1.2 No escribir `kyc_data.face_match` ante falla técnica (`!result.success`): el resultado queda sin concluir
- [x] 1.3 Confirmar que `kyc_data.face_match.passed` queda consultable por el collector vía `data_get`

## 2. Backend — insumo y regla en el motor de decisión

- [x] 2.1 En `DecisionInputCollector::collect`, recolectar `face_match` con tres estados (`passed` / `failed` / `null`) desde `kyc_data.face_match.passed`
- [x] 2.2 En `DecisionEngineService::evaluate`, agregar la regla `face_match` distinto de `true` → `ruleHit` con efecto `REVIEW`, SOLO si la política del producto incluye `rules.face_match`
- [x] 2.3 Agregar la razón legible "La identidad facial no coincide con la INE" al mapa `reasons`

## 3. Backend — umbral desde la política y seeder

- [x] 3.1 Resolver el umbral `face_match.min_score` desde la política de producto de la solicitud (`resolveFaceMatchThreshold`); usar 80 si no hay política; NO confiar en el `threshold` del request
- [x] 3.2 En `MoneyCapitalSeeder::seedDecisionPolicies`, sembrar `rules.face_match.min_score = 80` en la política de producto (MC-SIN-BURO)
- [x] 3.3 Confirmar que `face_match.min_score` NO se expone en la API pública (vive en `DecisionPolicy.rules`, no en `product.rules`)

## 4. Frontend — ejecutar el facematch al confirmar la selfie

- [x] 4.1 En `stores/onboarding.ts` (case `kyc_selfie`), al persistir la selfie invocar `validateFaceMatch(selfie, kyc_ine.front_image)`, condicional a `tenantStore.hasKycProvider`
- [x] 4.2 Ejecución silenciosa y optimista: el cliente avanza sin ver el resultado; sin bloquear ante falla técnica (fail-open, no marca `stepHadError`)

## 5. Tests

- [x] 5.1 `DecisionEngineTest`: face_match `failed` → REVIEW; ausente → REVIEW; `passed` → sin penalización; política sin regla → no altera (4 tests, pasan)
- [x] 5.2 Cubierto por 5.1: los tests pasan por `DecisionInputCollector` real (vía `runDecisionJob`), leyendo `kyc_data.face_match`
- [ ] 5.3 PENDIENTE: feature test del endpoint `POST /biometric/face-match` (registro del no-match en `kyc_data`) — requiere mockear el `NubariumService` del `kycFactory`
- [ ] 5.4 PENDIENTE: test del umbral resuelto desde la política (mismo setup de mock que 5.3)

## 6. Verificación

- [x] 6.1 Suite del motor en verde (`DecisionEngineTest` 22/22) + `type-check` y `lint` frontend limpios + sintaxis PHP OK
- [ ] 6.2 PENDIENTE: smoke (ui-smoke) del flujo en vivo — la parte silenciosa (facematch) es difícil de observar sin Nubarium en el entorno desechable; se hará cuando se pruebe contra prod
