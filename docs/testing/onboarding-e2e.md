# Plan de pruebas E2E — Onboarding → Motor → Oferta → Cartera

Ejercicios de prueba de extremo a extremo del pipeline de originación, desde que el
cliente termina el onboarding hasta la emisión de webhooks a la cartera. Cada escenario
está implementado como feature test automatizado en
`backend/tests/Feature/V2/OnboardingE2ETest.php` y se ejecuta con:

```bash
cd backend && php artisan test tests/Feature/V2/OnboardingE2ETest.php
```

Los tests recorren la **API real del solicitante** (`POST /v2/applicant/applications`,
`/submit`, `/counter-offer/respond`) con cola síncrona: al hacer *submit* el motor decide
en línea. La política del producto está en modo **ACTIVE** con la matriz de MoneyCapital
(piso de puntaje 15, bandas BASE $300-400 / INTERMEDIA $500-600 / CONTROLADA $700-800).

## Configuración de los perfiles

| Perfil | Ingreso / actividad | Puntaje | Banda (cupo) |
|--------|--------------------|---------|--------------|
| Bueno | $16,000 · Empleado | 25+15 = **40** | INTERMEDIA ($600) |
| Medio | $7,000 · Empleado | 10+15 = **25** | BASE ($400) |
| Bajo | $2,000 · Desempleado | 0+0 = **0** | — (bajo el piso) |

> El **rango de la oferta** es `[mínimo del producto ($300) → cupo de la banda]`: el
> cliente elige dentro de eso. Por eso una oferta INTERMEDIA es `$300–$600`, no `$500–$600`.

---

## Escenarios y resultados

Última corrida: **12/12 en verde (52 aserciones)**.

### Happy paths

| # | Escenario | Pasos | Resultado esperado | ✅ |
|---|-----------|-------|--------------------|----|
| **H1** | Crédito autorizado con oferta de rango | Perfil bueno → crea solicitud $550 → submit | El motor genera oferta automática rango `$300–$600` (INTERMEDIA, `source: ENGINE`). El cliente acepta $550 → `APPROVED`, se crea el `Loan` en `PENDING_DISBURSEMENT` (cartera externa) y se **emite `application.approved`** al webhook. | ✅ |
| **H2** | Monto solicitado excede el cupo → contraoferta | Perfil medio → solicita **$15,000** → submit | El motor autoriza cupo BASE $400 y ofrece rango `$300–$400` con **$400 pre-seleccionado** (contraoferta automática). | ✅ |
| **H3** | Renovación automática al liquidar | Crédito activo liquidado **puntual** → `recordPayment` del total | Se auto-crea la solicitud de renovación en `COUNTER_OFFERED` con el cupo del **nivel 1 ($2,000)**, sin re-onboarding. | ✅ |

### Bad paths

| # | Escenario | Pasos | Resultado esperado | ✅ |
|---|-----------|-------|--------------------|----|
| **B1** | KYC rechazado → rechazo automático | Perfil con `kyc_status: REJECTED` → submit | El motor decide `REJECT` → solicitud `REJECTED` y se **emite `application.rejected`** al webhook. | ✅ |
| **B2** | Perfil muy bajo → revisión manual | Perfil bajo (0 pts, bajo el piso 15) → submit | Sin oferta automática: la solicitud pasa a `IN_REVIEW` (un analista decide). | ✅ |
| **B3** | Filtro telefónico bloquea antes de INE | Score telefónico **720** (zona de bloqueo ≥601) → gate | El gate resuelve `BLOCK` y lo registra en `application_decisions` (trigger `PHONE_GATE`); el onboarding se detiene antes de consumir INE/biometría. | ✅ |
| **B4** | Cooldown tras rechazo | Rechazo hace 5 días → intenta crear solicitud nueva | La API responde **409 `APPLICATION_COOLDOWN`** (cooldown de 30 días). | ✅ |
| **B5** | Solicitud duplicada activa | Ya tiene una solicitud activa → intenta crear otra | La API responde **409 `APPLICATION_EXISTS`**. | ✅ |
| **B6** | Aceptar monto fuera del rango | Oferta BASE `$300–$400` → intenta aceptar **$900** | La API responde **422** y la solicitud permanece en `COUNTER_OFFERED`. | ✅ |
| **B7** | Aceptar oferta expirada | Oferta con `expires_at` en el pasado → intenta aceptar | La API responde **422** (no se puede aceptar una oferta vencida). | ✅ |
| **B8** | Modo sombra no actúa | Política del producto en `SHADOW` → submit | La solicitud queda en `SUBMITTED` (flujo manual); la decisión se registra con `executed=false` y el outcome que **habría** ejecutado (`OFFER`). | ✅ |
| **B9** | Sin política activa → flujo manual | Ninguna política activa → submit | La solicitud queda en `SUBMITTED` sin evaluación; el comportamiento previo queda intacto. | ✅ |

---

## Hallazgo de la corrida

La ejecución de estos ejercicios **destapó un bug real**: el **rechazo automático del
motor** (`applyEngineOutcome` → `REJECT`) no emitía el webhook `application.rejected` —
solo lo hacía el rechazo manual de un analista. Se corrigió agregando la emisión en esa
rama. Cubierto ahora por **B1**.

## Cobertura complementaria

Estos E2E se apoyan en los suites de unidad/integración que cubren el detalle fino:

- `DecisionEngineTest` — scoring, bandas, timeout de insumos, salidas del motor.
- `DecisionPolicyApiTest` — configurador, cooldown, gate telefónico (fail-open), permisos.
- `WebhookIntegrationTest` — firma HMAC, entrega/backoff, API entrante idempotente.

Regresión conjunta al cierre: **81/81 en verde**.
