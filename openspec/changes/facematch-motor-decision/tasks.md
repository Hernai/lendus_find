## 1. Backend — registrar siempre el resultado del facematch

- [ ] 1.1 En `KycController::validateFaceMatch`, agregar la rama de no-match: cuando `result.success && !result.match`, registrar `verificationService->verify('face_match', 'failed', ...)` con `is_verified=false` y el score en metadata (hoy solo registra si `match=true`)
- [ ] 1.2 No registrar pass ni fail ante falla técnica (`!result.success`): el resultado queda sin concluir
- [ ] 1.3 Verificar que la verificación `face_match` (pass y fail) queda consultable como `DataVerification` field `face_match`

## 2. Backend — insumo y regla en el motor de decisión

- [ ] 2.1 En `DecisionInputCollector::collect`, recolectar `face_match` con tres estados (`passed` / `failed` / `null`) desde la verificación `face_match`; sumar `face_match` a `missing` cuando no concluyó (reintento + timeout)
- [ ] 2.2 En `DecisionEngineService::evaluate`, agregar la regla `face_match` en estado `failed` → `ruleHit` con efecto `REVIEW`, SOLO si la política del producto la incluye (p.ej. `rules.face_match` presente)
- [ ] 2.3 Agregar la razón legible "la identidad facial no coincide con la INE" al mapa `reasons`

## 3. Backend — umbral desde la política y seeder

- [ ] 3.1 Resolver el umbral `face_match.min_score` desde la política de producto de la solicitud en el flujo del facematch; usar 80 si no hay política; NO confiar en el `threshold` del request cuando hay política
- [ ] 3.2 En `MoneyCapitalSeeder::seedDecisionPolicies`, sembrar `rules.face_match.min_score = 80` en la política de producto (MC-SIN-BURO)
- [ ] 3.3 Confirmar que `face_match.min_score` NO se expone en la API pública de configuración

## 4. Frontend — ejecutar el facematch al confirmar la selfie

- [ ] 4.1 En el flujo dinámico (`KycSelfieStepRenderer` / `DynamicOnboardingView` / `stores/onboarding.ts`), al confirmar la selfie invocar el facematch con la selfie del paso y la imagen de la INE de `dynamic_data.kyc_ine`, condicional a `tenantStore.hasKycProvider`
- [ ] 4.2 Ejecución silenciosa y optimista: el cliente avanza sin ver el resultado; registrar async; no bloquear ante falla técnica (fail-open)

## 5. Tests

- [ ] 5.1 `DecisionEngineService`: face_match `failed` → REVIEW; sin-concluir + timeout → REVIEW (`inputs_incomplete`); `passed` → sin penalización; política sin regla → no altera la salida
- [ ] 5.2 `DecisionInputCollector`: recolecta `face_match` (passed/failed/null) y lo marca `missing` cuando no concluyó
- [ ] 5.3 Registro del no-match: un facematch no-coincidente crea la verificación `face_match` con `is_verified=false`
- [ ] 5.4 Umbral resuelto desde la política de producto (no del request)

## 6. Verificación

- [ ] 6.1 Suite backend relevante en verde + `type-check` y `lint` frontend
- [ ] 6.2 Smoke (ui-smoke): la selfie ejecuta el facematch en silencio; un no-match no bloquea al cliente y, en SHADOW, la evaluación registra la salida `REVIEW` esperada
