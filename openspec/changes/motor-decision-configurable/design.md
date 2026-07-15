# Design — Motor de decisión configurable

## Context

Hoy el flujo post-submit es 100% manual: `Application::submit()` solo hace
`DRAFT → SUBMITTED` y un analista decide con `approve/reject/sendCounterOffer`. Existen
piezas reutilizables que este diseño aprovecha en vez de reinventar:

- **Riel de contraoferta** (change `contraoferta-admin-aceptacion`, ya en esta rama):
  snapshot JSONB `applications.counter_offer`, estado `COUNTER_OFFERED`, comando
  `counter-offers:expire` (cada minuto), pantalla applicant `LoanOfferView.vue`,
  respuesta `respondToCounterOffer`.
- **Riesgo de contacto**: `RunContactRiskJob` (post-OTP) → tabla `risk_assessments`
  (score, level, recommendation `allow/review/deny` de Nubarium). Hoy informativo.
- **Dedup**: "una solicitud activa por persona por tenant" (409 `APPLICATION_EXISTS`
  con lock TOCTOU) + identidades únicas `(tenant_id, type, identifier)`.
- **Portafolio interno**: `Loan` se auto-marca `COMPLETED` al saldar
  (`Loan::recalculateBalance`), `LoanService::recordPayment` ya computa `paidOnTime`;
  `LoanExtension` mueve `due_date`.
- **Campos dormidos**: `applications.risk_score/risk_level/decision`,
  `products.eligibility_rules` (sin evaluar), tabla `webhooks` (sin servicio).

Restricción de estado: la fuente de verdad son las **constantes
`Application::STATUS_*`** y `STATUS_TRANSITIONS` (el enum `ApplicationStatus` diverge y
es solo de UI). El motor opera exclusivamente sobre las constantes del modelo.

## Goals / Non-Goals

**Goals:**

- Motor de decisión asíncrono, configurable por tenant/producto, auditable, con salidas:
  oferta automática (rango), revisión manual, rechazo.
- Configurador admin con versiones, rollback, modos `off/shadow/active` y probador.
- Filtro telefónico temprano, cooldown post-rechazo y renovaciones E2E con graduación.
- Validar la Matriz Maestra MoneyCapital con tráfico real (modo sombra) sin arriesgar
  decisiones.

**Non-Goals:** ver `proposal.md § Non-goals` (dispersión/horario, webhooks salientes,
cobranza/mora, buró, contrato, OTP en aceptación, editor JSON libre).

## Decisiones (sesión de grilling)

| # | Decisión | Elección | Alternativas descartadas |
|---|----------|----------|--------------------------|
| 1 | Alcance | Originación + renovaciones E2E | Solo originación; solo configurador sin ejecutar |
| 2 | Persistencia | Tabla `decision_policies` versionada | JSONB en `product.rules`/`tenant.settings` (sin versiones); todo en tenant.settings |
| 3 | Ejecución | Job asíncrono post-submit | Síncrono en request; híbrido con timeout |
| 4 | Filtro telefónico | Gate pre-INE, fail-open, umbrales configurables | Fail-closed (403 actual de Nubarium pararía el piloto); solo informativo |
| 5 | Banda inicial | Scoring configurable por variables; % del PDF = monitoreo | Todos banda base; distribución aleatoria controlada |
| 6 | Salida positiva | Oferta unificada con rango sobre riel `COUNTER_OFFERED` | APPROVED directo + contraoferta solo si excede; monto fijo |
| 7 | Vigencia | Configurable, default 72h; recordatorio 24h antes; vencer → CANCELLED sin cooldown | 24h; 7-15 días |
| 8 | Revisión manual | `IN_REVIEW` + panel del motor + atajo "aplicar oferta sugerida" | Solo contexto informativo; bandeja dedicada |
| 9 | Cooldown | 30 días configurable, solo `REJECTED` cuenta, desbloqueo auditado ADMIN/SUPERVISOR | 60-90 días; sin cooldown |
| 10 | Graduación | Tabla de niveles (puntual sube, tarde/prórroga mantiene, mora → manual) | Fórmula porcentual; sin graduación automática |
| 11 | Aceptación | Directa + evidencia (timestamp, IP, dispositivo) | OTP siempre; OTP sobre umbral |
| 12 | Despliegue | `off → shadow → active` por producto | Directo a activo; activo solo para aprobar |
| 13 | UI | Módulo admin dedicado + probador; edita SUPER_ADMIN, consulta ADMIN, panel ANALYST+ | Tab en modal de productos; sin UI (seeder) |

## Arquitectura

### Datos

**`decision_policies`** (UUID PK, `HasTenant`, `HasUuid`, `HasAuditFields`):

- `tenant_id`, `product_id` (nullable — `NULL` = política de tenant para filtros de
  entrada), `version` (int), `mode` (`OFF|SHADOW|ACTIVE`), `is_active` (bool),
  `rules` (JSONB), `notes`, `activated_at/by`.
- Únicos: `(tenant_id, product_id, version)`; **una sola activa** por
  `(tenant_id, product_id)` (índice único parcial `WHERE is_active`).
- Editar = crear versión nueva en borrador; activar una versión desactiva la anterior
  (rollback = activar una versión previa).

Estructura de `rules` — **política de tenant** (`product_id NULL`):

```jsonc
{
  "phone_risk_gate": {
    "enabled": true,
    "flag_from": 401,          // score Nubarium ≥ → FLAG (revisión forzada)
    "block_from": 601,         // score ≥ → BLOCK (detener onboarding)
    "fail_mode": "open"        // proveedor caído/403 → continuar marcado
  },
  "cooldown": { "days": 30 }   // solo REJECTED; expiración/cancelación no cuentan
}
```

Estructura de `rules` — **política de producto**:

```jsonc
{
  "scoring": {
    "variables": [              // puntos por variable (declarativas + verificables)
      { "key": "salary_range", "points": { "0-8000": 0, "8000-15000": 10, "15000+": 20 } },
      { "key": "phone_risk_level", "points": { "very-low": 15, "low": 10, "moderate": 0 } },
      { "key": "employment_type", "points": { "FORMAL": 15, "INFORMAL": 5 } }
    ],
    "band_cutoffs": [           // puntaje mínimo → banda
      { "min_score": 0,  "band": "BASE" },
      { "min_score": 30, "band": "INTERMEDIA" },
      { "min_score": 50, "band": "CONTROLADA" },
      { "min_score": 70, "band": "EXCEPCIONAL" }
    ]
  },
  "bands": [
    { "key": "BASE",        "min_amount": 300, "max_amount": 400,  "target_share_pct": 85 },
    { "key": "INTERMEDIA",  "min_amount": 500, "max_amount": 600,  "target_share_pct": 13 },
    { "key": "CONTROLADA",  "min_amount": 700, "max_amount": 800,  "target_share_pct": 5 },
    { "key": "EXCEPCIONAL", "min_amount": 900, "max_amount": 1000, "target_share_pct": 2 }
  ],
  "first_credit": { "term_days": 7 },
  "offer": { "validity_hours": 72, "reminder_hours_before": 24 },
  "review": { "input_timeout_minutes": 30 },   // insumos incompletos → revisión
  "reject": [                                   // reglas duras de rechazo
    { "rule": "kyc_failed" }, { "rule": "identity_mismatch" }, { "rule": "out_of_coverage" }
  ],
  "graduation": {
    "levels": [                 // nivel 0 = primer crédito
      { "level": 0, "max_amount": 1000,  "max_term_days": 7 },
      { "level": 1, "max_amount": 2000,  "max_term_days": 10 },
      { "level": 2, "max_amount": 4000,  "max_term_days": 15 },
      { "level": 3, "max_amount": 8000,  "max_term_days": 20 },
      { "level": 4, "max_amount": 15000, "max_term_days": 30 }
    ],
    "advance": {
      "on_time": 1,             // liquidó puntual → sube 1 nivel
      "late_or_extension": 0,   // tarde o con prórroga → mantiene
      "max_late_days_for_auto": 5  // mora mayor → sin oferta automática (revisión)
    }
  }
}
```

**`application_decisions`** (auditoría, una fila por evaluación; UUID PK, `HasTenant`):

- `tenant_id`, `application_id` (nullable), `person_id`, `loan_id` (nullable — trigger
  de renovación), `decision_policy_id` + `policy_version`, `trigger`
  (`SUBMIT|RENEWAL|PHONE_GATE|DRY_RUN`), `mode` (`SHADOW|ACTIVE`), `inputs` (JSONB),
  `rule_hits` (JSONB — reglas disparadas con detalle), `score` (int nullable), `band`,
  `outcome` (`OFFER|REVIEW|REJECT|NO_OFFER|BLOCK|ALLOW|FLAG`), `outcome_detail` (JSONB:
  rango calculado, motivo, insumos faltantes), `executed` (bool — false en shadow/dry-run),
  `created_at`.

**Columnas nuevas en `applications`**: `renewal_of_loan_id` (UUID nullable),
`cooldown_waived_at/by/reason` (en la solicitud rechazada cuyo cooldown se levanta).

**Extensión del JSONB `counter_offer`** (retrocompatible — la presencia de
`max_amount` define modo rango):

```jsonc
{
  // ...campos actuales (amount, term_days, interest_rate, opening_commission, ...)
  "min_amount": 300, "max_amount": 800,        // rango autorizado (modo rango)
  "min_term_days": 7, "max_term_days": 7,
  "source": "ENGINE",                           // ENGINE | STAFF
  "acceptance": {                               // evidencia al aceptar
    "chosen_amount": 650, "chosen_term_days": 7,
    "ip": "…", "user_agent": "…", "accepted_at": "…"
  }
}
```

### Backend — flujo

1. **Gate telefónico** (`PHONE_GATE`): al entrar al paso de INE del onboarding, el
   endpoint de KYC consulta el último `RiskAssessment` `phone_risk` contra la política
   de tenant activa. `BLOCK` → respuesta de negocio con mensaje neutro (el frontend
   detiene el flujo), registro en `application_decisions`. `FLAG`/score no disponible →
   continúa y el motor lo pondera después. Sin política activa o `enabled=false` →
   comportamiento actual.
2. **Cooldown**: verificación junto al 409 existente en
   `Applicant\ApplicationController::store` **y** en el arranque del onboarding
   (informar temprano): última `Application` `REJECTED` de la persona dentro de
   `cooldown.days` y sin `cooldown_waived_at` → bloqueo con fecha de reintento.
3. **Submit**: `ApplicationService::submit()` despacha `DecideApplicationJob` si hay
   política de producto activa (modo ≠ `OFF`).
4. **`DecideApplicationJob`**: recolecta insumos (`risk_assessments`, `kyc_status` +
   `person_identifications`, `nubarium_async_validations` de CLABE, datos declarativos,
   geo, flags del gate) → si falta un insumo, `release()` con backoff hasta
   `input_timeout_minutes` → `REVIEW` con motivo `inputs_incomplete`.
   Con insumos completos, `DecisionEngineService::evaluate()` (puro, sin efectos)
   produce la decisión; el job la **ejecuta** solo en modo `ACTIVE`:
   - `OFFER` → `sendCounterOffer` extendido con rango (`source: ENGINE`, vigencia
     `validity_hours`) → `COUNTER_OFFERED` (transición válida vía `IN_REVIEW`:
     el job pasa `SUBMITTED → IN_REVIEW → COUNTER_OFFERED` registrando historial).
   - `REVIEW` → `changeStatus(IN_REVIEW)`; cae en la bandeja de no-asignadas.
   - `REJECT` → `reject()` con motivo del motor; activa cooldown.
   En `SHADOW` solo persiste `application_decisions` (`executed=false`) y no toca el
   estado (el flujo sigue manual).
5. **Renovación**: `LoanService::recordPayment` — cuando el préstamo pasa a
   `COMPLETED`, despacha `EvaluateRenewalJob`. El motor calcula nivel actual (préstamos
   liquidados previos + reglas de avance sobre `paidOnTime`/extensiones/días de atraso)
   y si procede **auto-crea** la `Application` de renovación (estado inicial
   `COUNTER_OFFERED`, `renewal_of_loan_id`, snapshot de datos vigentes de la persona,
   sin re-onboarding) con oferta de rango y notificación. Mora relevante → sin oferta
   (`NO_OFFER`) o `REVIEW` según política.
6. **Aceptación**: `respondToCounterOffer` acepta payload extendido
   `{ accepted, amount?, term_days? }`; valida contra el rango, recalcula pricing del
   monto/plazo elegidos, guarda `acceptance` (evidencia) y copia a `approved_*`.
   Cliente sin monto (app vieja / oferta fija) → usa `amount` del snapshot.
7. **Recordatorio**: el comando `counter-offers:expire` (existente) gana un segundo
   barrido: ofertas vigentes que cruzan `reminder_hours_before` → notificación
   `COUNTER_OFFER_EXPIRING` (una sola vez, flag en el snapshot).

### API (staff, prefijo `/api/v2/staff`)

| Endpoint | Permiso |
|----------|---------|
| `GET/POST /decision-policies`, `PUT /decision-policies/{id}` (borradores) | `canConfigureTenant` |
| `POST /decision-policies/{id}/activate` (activar/rollback/modo) | `canConfigureTenant` |
| `POST /decision-policies/dry-run` (probador: perfil hipotético → decisión + rule_hits, `trigger=DRY_RUN`, sin efectos) | `canConfigureTenant` |
| `GET /applications/{id}/decision` (panel del motor) | staff con acceso al detalle |
| `POST /applications/{id}/apply-suggested-offer` (atajo) | `canApproveRejectApplications` |
| `POST /applications/{id}/lift-cooldown` (motivo obligatorio) | `canApproveRejectApplications` |

### Frontend

- **Módulo admin "Motor de decisión"** (`modules/admin`, visibilidad vía sistema de
  módulos + `TenantRoleModuleOverride`): tarjeta de política de tenant + lista por
  producto; editor tipado por secciones (filtro, cooldown, scoring/bandas, oferta,
  graduación); drawer de versiones (activar/rollback, diff de notas); selector
  off/shadow/active con confirmación; modal probador de perfiles.
- **Panel "Evaluación del motor"** en `AdminApplicationDetail`: outcome, score, banda,
  rule_hits legibles, insumos faltantes, modo (sombra muestra "el motor habría…");
  botón "Aplicar oferta sugerida".
- **`LoanOfferView`**: si el snapshot trae rango (`max_amount`), desbloquea sliders
  dentro de `[min→max]` con recálculo local en vivo (fórmula bullet existente) y envía
  monto/plazo elegidos al aceptar. Ofertas fijas → comportamiento actual.

## Risks / Trade-offs

- [Fail-open con Nubarium caído deja pasar teléfonos de riesgo] → FLAG automático
  `score_unavailable` fuerza revisión manual; el panel del motor y el listado de
  `application_decisions` permiten monitorear la tasa de "sin score".
- [Carrera de insumos async (CLABE por webhook llega tarde)] → backoff + timeout →
  `REVIEW`; **nunca** rechazo por insumo faltante.
- [Cambio semántico del snapshot `counter_offer`] → retrocompatible: sin `max_amount` es
  oferta fija; tests cubren ambos modos; clientes móviles viejos que manden solo
  `{accepted}` toman el monto pre-seleccionado del snapshot.
- [Renovación auto-crea solicitudes sin intención del cliente] → `renewal_of_loan_id`
  las distingue en métricas; expiran limpio a `CANCELLED` a las 72h.
- [Seeder de prod re-siembra tenants en cada deploy] → el seeder crea la política v1 de
  MoneyCapital **solo si no existe ninguna** para ese tenant/producto (idempotente, no
  pisa ediciones del admin).
- [Transición SUBMITTED→COUNTER_OFFERED no existe en la máquina] → el job transita por
  `IN_REVIEW` registrando historial (sin tocar `STATUS_TRANSITIONS`); la creación de
  renovación usa estado inicial (no transición), documentado en el spec.
- [Puntajes de scoring iniciales son estimaciones] → arrancar en `shadow`; los defaults
  del seeder son la mejor lectura de la matriz y MoneyCapital los calibra en el
  configurador con datos del probador y del modo sombra.

## Migration Plan

1. Migraciones aditivas (2 tablas + columnas nullable) — sin backfill.
2. Deploy con todo en `OFF` (sin política activa = comportamiento actual intacto).
3. Seeder siembra política v1 de MoneyCapital en modo `SHADOW` (si no existe).
4. Calibración con tráfico real (panel del motor + probador); ajustar versiones.
5. Activar `ACTIVE` desde el configurador. Rollback = volver a `SHADOW`/`OFF` o activar
   una versión anterior — sin deploy.

## Open Questions

- Ninguna bloqueante: las 13 decisiones vienen del grilling. Los **valores** finos de
  scoring (puntos por variable) los calibra MoneyCapital en el configurador; el seeder
  trae defaults derivados de la matriz.
