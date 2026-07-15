# Motor de decisión configurable (Matriz Maestra MoneyCapital)

## Why

La Matriz Maestra de MoneyCapital exige respuesta en segundos con cuatro salidas
(aprobación automática, contraoferta automática, revisión manual, rechazo) y hoy todo el
flujo post-submit es 100% manual: no existe motor de decisión, `eligibility_rules` no se
evalúa, el phone risk de Nubarium es solo informativo y no hay renovaciones. Para el
piloto ($300–$1,000 a 7 días, Culiacán) se necesita un motor **configurable por
tenant/producto y auditable**, con modo sombra que permita validar la matriz con tráfico
real antes de dejarlo decidir solo.

*Sesión de grilling completada en esta conversación (13 decisiones registradas en
`design.md` § Decisiones).*

## What Changes

- **Políticas versionadas**: tabla `decision_policies` (tenant-level para filtros de
  entrada; per-producto para scoring/bandas/graduación) con versión activa, rollback y
  modo `off | shadow | active`. Auditoría por evaluación en tabla
  `application_decisions` (inputs + política aplicada + reglas disparadas + salida).
- **Motor asíncrono post-submit**: `DecideApplicationJob` evalúa al pasar a `SUBMITTED`;
  insumo faltante → reintentos con backoff → timeout configurable → revisión manual.
  Resultado al cliente vía WebSocket/push. En modo `shadow` solo registra qué habría
  decidido.
- **Filtro telefónico temprano (R21)**: gate post-OTP antes de INE/biometría con
  umbrales configurables (defaults escala Nubarium); BLOCK detiene el onboarding con
  mensaje neutro, FLAG fuerza revisión manual, falla del proveedor = fail-open marcado.
- **Cooldown post-rechazo (R01)**: 30 días configurable por tenant, solo `REJECTED`
  cuenta (no expiración/cancelación); bloqueo informado desde el inicio del onboarding;
  desbloqueo por ADMIN/SUPERVISOR con motivo auditado.
- **Oferta unificada con rango (R06/R07)**: toda salida positiva del motor crea una
  oferta sobre el riel `COUNTER_OFFERED` existente con rango autorizado
  `[mín producto → cupo]` × `[7 días → plazo autorizado]`; sliders desbloqueados con
  recálculo en vivo; vigencia configurable default 72h con recordatorio 24h antes;
  aceptación directa con evidencia (timestamp, IP, dispositivo) → `APPROVED`.
- **Scoring de bandas para primer crédito**: puntos configurables por variable
  (declarativas + verificables) y cortes por banda ($300-400 / $500-600 / $700-800 /
  $900-1,000); los porcentajes de participación del PDF son metas de monitoreo, no
  mecanismo de asignación.
- **Renovaciones E2E (R05/R20)**: al marcarse el `Loan` como `COMPLETED`, el motor
  evalúa la graduación por **tabla de niveles** (puntual sube, tarde/prórroga mantiene,
  mora relevante → revisión manual) y auto-crea la solicitud de renovación con oferta de
  rango, reutilizando KYC vigente sin re-onboarding.
- **Panel "Evaluación del motor"** en el detalle admin de la solicitud (reglas
  disparadas, puntaje, banda sugerida, insumos faltantes) + atajo "aplicar oferta
  sugerida" en un clic. Revisión manual cae en la bandeja `IN_REVIEW` existente.
- **Módulo admin "Motor de decisión"**: editor tipado por secciones, historial de
  versiones con activar/rollback, selector de modo y **probador de perfiles**
  hipotéticos (decisión + reglas disparadas sin tocar solicitudes reales). Edita
  SUPER_ADMIN (`canConfigureTenant`); ADMIN consulta; panel de evaluación visible a
  ANALYST+.

## Capabilities

### New Capabilities

- `decision-policy`: persistencia y ciclo de vida de políticas versionadas
  (tenant/producto, modos, activación/rollback, seeder idempotente).
- `decision-engine`: evaluación asíncrona post-submit — recolección de insumos, scoring
  de bandas, salidas (oferta/revisión/rechazo), auditoría en `application_decisions`,
  comportamiento shadow, timeout → revisión.
- `phone-risk-gate`: filtro telefónico temprano post-OTP con umbrales configurables,
  acciones allow/flag/block y fail-open.
- `application-cooldown`: bloqueo temporal post-rechazo con desbloqueo auditado.
- `loan-renewal`: trigger de liquidación, graduación por niveles y auto-creación de la
  oferta de renovación.
- `decision-review-panel`: tarjeta de evaluación del motor en el detalle staff + atajo
  de oferta sugerida.
- `decision-configurator-admin`: módulo admin de edición de políticas con versiones y
  probador de perfiles.

### Modified Capabilities

*(Base: delta specs del change pendiente `contraoferta-admin-aceptacion` — deben
sincronizarse a `openspec/specs/` antes de archivar este change.)*

- `counter-offer-lifecycle`: el snapshot admite **rango** (`min/max_amount`,
  `min/max_term_days`) además de monto fijo; origen `system` además de staff; vigencia
  en horas (default 72h) además de minutos; la aceptación lleva monto/plazo elegidos
  dentro del rango + evidencia (IP/dispositivo); recordatorio pre-vencimiento.
- `counter-offer-applicant`: la pantalla de oferta desbloquea sliders dentro del rango
  autorizado con recálculo en vivo; confirmación "Recibir mi préstamo ahora" directa.
- `counter-offer-admin`: la tarjeta de oferta vigente muestra rango y origen
  (motor vs staff); el modal manual permanece de monto fijo.

## Non-goals

- Dispersión STP / horario operativo (R03, R16): el motor opera 24/7; la dispersión ya
  tiene el feature flag `auto_disbursement` existente.
- Webhooks salientes a sistemas externos (la tabla `webhooks` sigue dormida).
- Cobranza, mora, prórrogas, visitas domiciliarias, recaudo en efectivo (R09–R15):
  viven en el servicing de `Loan`, fuera del motor de originación.
- Buró de crédito / Círculo (solo placeholders existentes).
- Contrato digital y carátula (R08), actualización de datos del cliente (R18), bancos
  aceptados para desembolso (R17), incentivos (R19), campañas comerciales (R10).
- OTP en la aceptación de oferta (queda como extensión futura de política).
- Editor JSON libre de políticas (el editor es tipado por secciones).

## Impact

- **Backend**: 2 tablas nuevas (`decision_policies`, `application_decisions`) +
  extensión del JSONB `counter_offer` (rango/origen/evidencia, retrocompatible: ofertas
  sin rango siguen siendo de monto fijo); modelos `DecisionPolicy` y
  `ApplicationDecision`; `DecisionEngineService` + `DecideApplicationJob` +
  `EvaluateRenewalJob`; hooks en `ApplicationService::submit`, verificación OTP
  (`ApplicantAuthService`) y `LoanService::recordPayment`; endpoints staff nuevos
  (políticas CRUD/activar/probar, aplicar oferta sugerida, levantar cooldown);
  `NotificationEvent` nuevos (decisión, recordatorio de vencimiento, oferta de
  renovación).
- **Frontend**: módulo admin nuevo "Motor de decisión"; tarjeta de evaluación en
  `AdminApplicationDetail`; sliders con rango en `LoanOfferView`; tipos V2 nuevos.
- **Dependencias**: el change `contraoferta-admin-aceptacion` es prerrequisito (esta
  rama ya lo contiene); Nubarium phone risk hoy devuelve 403 en algunos entornos
  (producto no contratado / IP sin whitelist) — el fail-open cubre ese caso desde el
  día uno.
- **Datos existentes**: sin backfill; solicitudes previas no se re-evalúan. El seeder de
  MoneyCapital siembra la política inicial de la matriz **solo si no existe** (prod
  re-seedea tenants en cada deploy).
- **API pública**: la política NO se expone en `/api/v2/config` (sin cambios a la
  whitelist `formatRules`); el rango de la oferta viaja en el payload de la solicitud
  del applicant.
