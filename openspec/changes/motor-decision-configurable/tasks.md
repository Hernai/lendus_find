# Tasks — motor-decision-configurable

## 1. Datos y modelos

- [x] 1.1 Migración `create_decision_policies_table`: UUID PK, `tenant_id`, `product_id` nullable, `version`, `mode` (OFF/SHADOW/ACTIVE), `is_active`, `rules` JSONB, `notes`, `activated_at/by`, audit fields; únicos `(tenant_id, product_id, version)` e índice único parcial de activa
- [x] 1.2 Migración `create_application_decisions_table`: `tenant_id`, `application_id` nullable, `person_id`, `loan_id` nullable, `decision_policy_id` + `policy_version`, `trigger`, `mode`, `inputs`/`rule_hits`/`outcome_detail` JSONB, `score`, `band`, `outcome`, `executed`; índices por application/person/trigger
- [x] 1.3 Migración columnas en `applications`: `renewal_of_loan_id` (UUID nullable, FK loans) y `cooldown_waived_at/by/reason`
- [x] 1.4 Modelos `DecisionPolicy` y `ApplicationDecision` (HasTenant, HasUuid, HasAuditFields; casts JSONB; scopes `active()`, helpers de versión) + enums `DecisionMode`, `DecisionTrigger`, `DecisionOutcome` (string-backed UPPERCASE, HasOptions)
- [x] 1.5 Factories + seeder: política v1 MoneyCapital (valores de la matriz, modo SHADOW) idempotente en `MoneyCapitalSeeder` — solo si no existe política para el tenant/producto

## 2. Motor de decisión (core)

- [x] 2.1 `DecisionEngineService::evaluate()` puro: entrada (insumos + política) → salida (outcome, score, band, rango, rule_hits); reglas duras de rechazo, FLAG del gate fuerza REVIEW, scoring de bandas con cortes
- [x] 2.2 `DecisionInputCollector`: arma insumos desde `risk_assessments`, `persons.kyc_status` + `person_identifications`, `nubarium_async_validations` (CLABE), datos declarativos de la solicitud, geo; reporta insumos faltantes
- [x] 2.3 `DecideApplicationJob`: despacho desde `ApplicationService::submit()` si hay política ≠ OFF; release con backoff ante insumo faltante; timeout `input_timeout_minutes` → REVIEW `inputs_incomplete`; en ACTIVE ejecuta salidas (OFFER → IN_REVIEW → COUNTER_OFFERED con oferta rango; REVIEW → IN_REVIEW; REJECT → reject); en SHADOW solo registra `executed=false`
- [x] 2.4 Persistencia de auditoría: toda corrida escribe `application_decisions` (política+versión, inputs, rule_hits, outcome, executed)
- [x] 2.5 Tests feature del motor: shadow no toca estado, active ejecuta cada salida, contraoferta automática por exceso de cupo, timeout → revisión, sin política → flujo manual intacto

## 3. Oferta con rango (riel COUNTER_OFFERED)

- [x] 3.1 Extender `Application::sendCounterOffer` / `ApplicationService::sendCounterOffer`: campos de rango (`min/max_amount`, `min/max_term_days`), `source` (ENGINE/STAFF), `offered_by: system`, vigencia en horas para el motor (minutos para staff se mantiene)
- [x] 3.2 Extender `respondToCounterOffer` (modelo + service + controller applicant): payload `{accepted, amount?, term_days?}`, validación contra rango, recálculo de pricing del monto elegido, evidencia `acceptance` (ip, user_agent, accepted_at, chosen_*), copia de elegidos a `approved_*`; ofertas fijas intactas
- [x] 3.3 Recordatorio de vencimiento: segundo barrido en `counter-offers:expire` → evento `COUNTER_OFFER_EXPIRING` una sola vez a `reminder_hours_before`; nuevo `NotificationEvent` + template seed
- [x] 3.4 Tests: aceptación dentro de rango, fuera de rango (422), cliente viejo sin monto, recordatorio único, expiración de oferta del motor

## 4. Gate telefónico y cooldown

- [x] 4.1 `PhoneRiskGateService`: evalúa último `RiskAssessment` vs política de tenant; enganchar como verificación en el endpoint del paso INE (KycController) — BLOCK detiene con mensaje neutro, FLAG/`score_unavailable` marcan; registro `trigger=PHONE_GATE`; sin política/disabled → comportamiento actual
- [x] 4.2 Cooldown: verificación en `Applicant\ApplicationController::store` (junto al 409 existente) + exposición temprana del estado de bloqueo en el arranque del flujo applicant (fecha de reintento); solo REJECTED sin waiver cuenta
- [x] 4.3 Endpoint `POST /v2/staff/applications/{id}/lift-cooldown` (permiso `canApproveRejectApplications`, motivo obligatorio, escribe `cooldown_waived_*`)
- [x] 4.4 Tests: block/flag/fail-open del gate, cooldown dentro/fuera de ventana, CANCELLED no bloquea, waiver con/sin permiso y sin motivo

## 5. Renovaciones

- [x] 5.1 Hook en `LoanService::recordPayment`: al pasar a COMPLETED despacha `EvaluateRenewalJob` (si política con graduación)
- [x] 5.2 `EvaluateRenewalJob` + graduación en `DecisionEngineService`: nivel por préstamos liquidados + reglas de avance (puntual sube / tarde-prórroga mantiene / mora > umbral → sin oferta); respeta dedup de solicitud/crédito activo; SHADOW solo registra
- [x] 5.3 Auto-creación de solicitud de renovación: estado inicial COUNTER_OFFERED con historial, `renewal_of_loan_id`, snapshot de datos vigentes de la persona sin re-onboarding, oferta de rango del nivel, notificación al cliente
- [x] 5.4 Tests: puntual sube nivel, prórroga mantiene, mora sin oferta, crédito activo bloquea, oferta de renovación expira a CANCELLED sin cooldown

## 6. API staff de políticas y panel

- [x] 6.1 `Staff\DecisionPolicyController`: index/store/update (borradores), `activate` (rollback incluido), `dry-run` — permisos `canConfigureTenant`; validación de coherencia (cortes crecientes, bandas sin traslape y dentro de límites del producto, `flag_from < block_from`, niveles crecientes)
- [x] 6.2 `GET /v2/staff/applications/{id}/decision` (última evaluación formateada para el panel) + incluir resumen en `formatApplicationDetail`
- [x] 6.3 `POST /v2/staff/applications/{id}/apply-suggested-offer` (permiso `canApproveRejectApplications`, estados IN_REVIEW/DOCS_PENDING, usa rango de la evaluación, `source: ENGINE`)
- [x] 6.4 Rutas en `routes/api.php` + tests de permisos por rol (SUPER_ADMIN edita, ADMIN lee, ANALYST 403 en políticas)

## 7. Frontend applicant

- [x] 7.1 Types V2: extender `V2CounterOffer` (rango, source, acceptance) y `V2CounterOfferResponsePayload` (`amount?`, `term_days?`); tipo de estado de cooldown
- [x] 7.2 `LoanOfferView`: modo rango — sliders habilitados `[min→max]` con recálculo en vivo (fórmula bullet existente), pre-selección del snapshot, envío de monto/plazo elegidos; modo fijo intacto
- [x] 7.3 Mensajes de gate y cooldown en el flujo mobile: pantalla/aviso neutro de detención (BLOCK) y aviso de cooldown con fecha de reintento al inicio del flujo
- [x] 7.4 Notificación/redirect de oferta de renovación (reutiliza redirect existente a `m-loan-offer`)

## 8. Frontend admin

- [x] 8.1 Módulo "Motor de decisión" registrado en el sistema de módulos con visibilidad por rol; vista lista (política tenant + productos, versión activa, modo)
- [x] 8.2 Editor tipado por secciones (filtro telefónico, cooldown / scoring+bandas, oferta, graduación) con validación en formulario; guardar = nueva versión borrador
- [x] 8.3 Drawer de versiones: historial, activar/rollback, cambio de modo con confirmación explícita al pasar a ACTIVE
- [x] 8.4 Modal probador (dry-run): captura de perfil hipotético + selector de versión → decisión con desglose (outcome, puntos, banda, rango, reglas)
- [x] 8.5 Tarjeta "Evaluación del motor" en `AdminApplicationDetail` (outcome, score, banda, rule_hits, insumos faltantes, badge sombra) + botón "Aplicar oferta sugerida"; tarjeta de oferta vigente muestra rango y origen
- [x] 8.6 Service `decision-policy.staff.service.ts` + types V2 staff

## 9. Verificación integral

- [x] 9.1 `php artisan test` de los grupos nuevos + `npm run type-check` y lint
- [ ] 9.2 Smoke E2E manual (ui-smoke): onboarding MoneyCapital completo con política ACTIVE — solicitud → oferta de rango → ajustar slider → aceptar → APPROVED; y caso shadow (no actúa)
- [x] 9.3 Verificar aislamiento multi-tenant: demo/finatea sin política operan exactamente como hoy (regresión de submit y contraoferta manual)
