# Integración con LendusFind — Webhooks y API de cartera

Guía para el desarrollador que integra un **sistema externo de cartera / core bancario**
con LendusFind. LendusFind origina y valida créditos (LOS); tu plataforma los administra.
Esta integración cubre tres cosas:

1. **Webhooks salientes** — LendusFind te avisa de eventos del ciclo (crédito autorizado,
   dispersado, pago aplicado, liquidado) con el **payload completo**.
2. **API entrante** — tu plataforma le confirma a LendusFind los **pagos aplicados** y el
   **acuse de ingesta** del crédito a tu cartera.
3. **API de re-consulta** — puedes releer el expediente/crédito estandarizado para
   reconciliar o recuperar un evento perdido.

> **Estado:** contrato de integración v1 (spec `integracion-webhooks-cartera`). Los
> ejemplos usan valores ilustrativos; los campos exactos se confirman contra tu entorno.

---

## 1. Conceptos

| Concepto | Qué es |
|----------|--------|
| **Endpoint** | La URL HTTPS de tu plataforma donde recibes los webhooks. Cada endpoint tiene un **secreto** de firma y una lista de **eventos** suscritos. |
| **Evento** | Algo que ocurrió en el ciclo (`application.approved`, `loan.disbursed`…). |
| **Entrega (delivery)** | Un intento de POST de un evento a tu endpoint. Se reintenta si falla. |
| **Sobre (envelope)** | La estructura común de todo webhook: `{ id, event, version, occurred_at, tenant, data }`. |
| **`event_id`** | UUID único del evento (`id` del sobre). **Úsalo para idempotencia**: si recibes el mismo `event_id` dos veces, procésalo una sola vez. |

El endpoint, su secreto y las suscripciones se configuran desde el panel de LendusFind
(**Admin → Webhooks**). Puedes tener varios endpoints (p. ej. uno para cartera y otro para
BI) y marcar uno como **sandbox** para pruebas.

---

## 2. Catálogo de eventos (v1)

| Evento | Cuándo se dispara | Recurso en `data` |
|--------|-------------------|-------------------|
| `application.approved` | El crédito fue autorizado. **Es la señal de "dispersa"**: trae la CLABE completa y el monto para que tu cartera disperse. | `application` |
| `application.rejected` | La solicitud fue rechazada | `application` |
| `loan.disbursed` | Se confirmó la dispersión (tú la confirmas por la API entrante, §7.1) y LendusFind activó el crédito | `loan` |
| `payment.received` | Se registró un pago sobre el crédito | `loan` (con `last_payment`) |
| `loan.completed` | El crédito quedó liquidado | `loan` |

El catálogo es configurable por endpoint: suscribes solo los que te interesan (o `*`).
Se agregarán más eventos en versiones futuras sin romper el contrato existente.

> **Dispersión:** tu cartera es la que dispersa. LendusFind autoriza y te entrega el
> crédito con la CLABE (`application.approved`); tú dispersas y **confirmas de vuelta**
> (§7.1). Entonces LendusFind activa el crédito y emite `loan.disbursed`. El ciclo de
> dispersión de LendusFind (STP interno) queda apagado para tu tenant.

---

## 3. El sobre (envelope)

Todos los webhooks comparten esta estructura. Solo cambia `data` según el recurso.

```json
{
  "id": "evt_01J9Z8Q4C7M2X5T3B6N0K1P8RA",
  "event": "application.approved",
  "version": "1",
  "occurred_at": "2026-07-15T18:04:22Z",
  "tenant": { "id": "af428ded-0538-477c-b458-f09cef27c538", "slug": "moneycapital" },
  "data": { }
}
```

| Campo | Descripción |
|-------|-------------|
| `id` | `event_id` (UUID). Clave de idempotencia — no proceses el mismo dos veces. |
| `event` | Nombre del evento (ver catálogo). |
| `version` | Versión del esquema. Dentro de una versión los campos **solo se agregan**. |
| `occurred_at` | Timestamp UTC ISO-8601 del evento. |
| `tenant` | Tenant (SOFOM) dueño del dato. **Nunca cruza tenants.** |
| `data` | El recurso estandarizado (`application` o `loan`). |

---

## 4. Ejemplos de payload por evento

### 4.1 `application.approved` — crédito autorizado

```json
{
  "id": "evt_01J9Z8Q4C7M2X5T3B6N0K1P8RA",
  "event": "application.approved",
  "version": "1",
  "occurred_at": "2026-07-15T18:04:22Z",
  "tenant": { "id": "af428ded-0538-477c-b458-f09cef27c538", "slug": "moneycapital" },
  "data": {
    "application": {
      "id": "019f6404-1a2b-7c3d-8e4f-5a6b7c8d9e0f",
      "folio": "20260715-019F",
      "status": "APPROVED",
      "product": { "code": "MC-SIN-BURO", "name": "Préstamo Sin Buró" },
      "approved": {
        "amount": 350.00,
        "term_days": 7,
        "interest_rate": 36.0,
        "opening_commission_rate": 13.0,
        "currency": "MXN"
      },
      "person": {
        "id": "019f6404-2b3c-7d4e-9f5a-6b7c8d9e0f1a",
        "full_name": "Juan Pérez López",
        "curp": "PELJ950115HSLRPN01",
        "rfc": "PELJ950115AB1",
        "birth_date": "1995-01-15",
        "kyc_status": "VERIFIED"
      },
      "disbursement_account": {
        "bank_name": "STP",
        "clabe": "646180157099999993",
        "holder_name": "Juan Pérez López"
      },
      "approved_at": "2026-07-15T18:04:22Z"
    }
  }
}
```

> **CLABE completa:** viaja completa porque tu cartera la necesita para dispersar. Es un
> dato sensible: recíbela solo por HTTPS con firma verificada y trátala como PII.

### 4.2 `application.rejected`

```json
{
  "id": "evt_01J9ZB2K…", "event": "application.rejected", "version": "1",
  "occurred_at": "2026-07-15T18:10:00Z",
  "tenant": { "id": "af428ded-…", "slug": "moneycapital" },
  "data": {
    "application": {
      "id": "019f6404-…", "folio": "20260715-0A2B", "status": "REJECTED",
      "product": { "code": "MC-SIN-BURO", "name": "Préstamo Sin Buró" },
      "rejection": { "reason": "MOTOR_DECISION", "notes": "No pasó filtro inicial" },
      "person": { "id": "019f6404-…", "full_name": "María López", "curp": "LOMM900220MSLPRR03", "kyc_status": "VERIFIED" },
      "rejected_at": "2026-07-15T18:10:00Z"
    }
  }
}
```

### 4.3 `loan.disbursed` — dispersión confirmada

Se emite **después de que tú confirmas la dispersión** (§7.1); LendusFind entonces activa
el crédito. Útil para otros suscriptores (BI, conciliación).

```json
{
  "id": "evt_01J9ZC7…", "event": "loan.disbursed", "version": "1",
  "occurred_at": "2026-07-15T18:05:10Z",
  "tenant": { "id": "af428ded-…", "slug": "moneycapital" },
  "data": {
    "loan": {
      "id": "019f6404-9a8b-7c6d-5e4f-3a2b1c0d9e8f",
      "application_id": "019f6404-1a2b-7c3d-8e4f-5a6b7c8d9e0f",
      "status": "ACTIVE",
      "principal_amount": 350.00,
      "interest_rate": 36.0,
      "term_days": 7,
      "opening_commission_amount": 45.50,
      "total_to_pay": 405.59,
      "outstanding_balance": 405.59,
      "currency": "MXN",
      "disbursed_at": "2026-07-15T18:05:10Z",
      "due_date": "2026-07-22",
      "disbursement": { "provider": "STP", "reference": "STP-2026071500012345" },
      "person": { "id": "019f6404-…", "full_name": "Juan Pérez López", "curp": "PELJ950115HSLRPN01" }
    }
  }
}
```

### 4.4 `payment.received` — pago aplicado

```json
{
  "id": "evt_01J9ZD1…", "event": "payment.received", "version": "1",
  "occurred_at": "2026-07-18T12:30:00Z",
  "tenant": { "id": "af428ded-…", "slug": "moneycapital" },
  "data": {
    "loan": {
      "id": "019f6404-9a8b-…", "status": "ACTIVE",
      "total_to_pay": 405.59, "paid_amount": 200.00, "outstanding_balance": 205.59,
      "currency": "MXN",
      "last_payment": {
        "amount": 200.00, "channel": "STP", "provider_reference": "STP-88213",
        "paid_at": "2026-07-18T12:29:41Z"
      }
    }
  }
}
```

### 4.5 `loan.completed` — crédito liquidado

```json
{
  "id": "evt_01J9ZE9…", "event": "loan.completed", "version": "1",
  "occurred_at": "2026-07-20T09:15:00Z",
  "tenant": { "id": "af428ded-…", "slug": "moneycapital" },
  "data": {
    "loan": {
      "id": "019f6404-9a8b-…", "status": "COMPLETED",
      "total_to_pay": 405.59, "paid_amount": 405.59, "outstanding_balance": 0.00,
      "completed_at": "2026-07-20T09:15:00Z", "currency": "MXN"
    }
  }
}
```

---

## 5. Verificar la firma (obligatorio)

Cada webhook llega firmado. **Verifica siempre la firma** antes de procesar; rechaza si no
valida o si el timestamp está fuera de una ventana de ±5 minutos (anti-replay).

### Cabeceras

| Cabecera | Contenido |
|----------|-----------|
| `X-LendusFind-Signature` | `sha256=<hex>` — HMAC-SHA256 de `"{timestamp}.{body}"` |
| `X-LendusFind-Timestamp` | Unix epoch (segundos) usado en la firma |
| `X-LendusFind-Event` | Nombre del evento (redundante con el sobre) |
| `X-LendusFind-Delivery` | ID de la entrega (para soporte/log) |

La base firmada es la concatenación literal `timestamp + "." + cuerpo_crudo`. Usa el
**cuerpo crudo** (bytes tal cual), no el JSON re-serializado.

### PHP

```php
function verifyLendusSignature(string $rawBody, array $headers, string $secret): bool
{
    $signature = $headers['X-LendusFind-Signature'] ?? '';
    $timestamp = $headers['X-LendusFind-Timestamp'] ?? '';

    if (abs(time() - (int) $timestamp) > 300) return false; // ventana ±5 min

    $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    return hash_equals($expected, $signature); // comparación en tiempo constante
}
```

### Node.js

```js
const crypto = require('crypto');

function verifyLendusSignature(rawBody, headers, secret) {
  const signature = headers['x-lendusfind-signature'] || '';
  const timestamp = headers['x-lendusfind-timestamp'] || '';

  if (Math.abs(Date.now() / 1000 - Number(timestamp)) > 300) return false;

  const expected = 'sha256=' +
    crypto.createHmac('sha256', secret).update(`${timestamp}.${rawBody}`).digest('hex');

  const a = Buffer.from(expected), b = Buffer.from(signature);
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}
```

### Python

```python
import hashlib, hmac, time

def verify_lendus_signature(raw_body: bytes, headers: dict, secret: str) -> bool:
    signature = headers.get("X-LendusFind-Signature", "")
    timestamp = headers.get("X-LendusFind-Timestamp", "")

    if abs(time.time() - int(timestamp)) > 300:
        return False

    expected = "sha256=" + hmac.new(
        secret.encode(), f"{timestamp}.".encode() + raw_body, hashlib.sha256
    ).hexdigest()
    return hmac.compare_digest(expected, signature)
```

---

## 6. Responder a los webhooks

- Responde **2xx** rápido (idealmente < 5 s) para marcar la entrega como exitosa.
- Si respondes un código de error o tardas demasiado, LendusFind **reintenta** con backoff
  exponencial (aprox. 1 min, 5 min, 30 min, 2 h, 6 h; 5 intentos). Agotados, la entrega
  queda `FAILED` y el admin puede **reenviarla** desde el panel.
- **Procesa de forma idempotente por `event_id`**: los reintentos entregan el mismo
  `event_id`. Guarda los ids procesados y descarta duplicados.
- Haz el trabajo pesado en background: acusa recibo con 2xx y procesa async.

---

## 7. API entrante — confirmar pagos y acuse de ingesta

Tu plataforma le confirma cosas a LendusFind. Ambos endpoints usan la **misma firma HMAC**
(firmas tú, LendusFind verifica) y son **idempotentes por `external_event_id`**.

`{endpoint}` en la URL identifica tu endpoint registrado (LendusFind resuelve el secreto).

### 7.1 Confirmar la dispersión (cierra el handoff)

Cuando tu cartera dispersó el crédito a la CLABE, confírmalo. LendusFind activa el crédito
(lo pasa de "pendiente de dispersión" a **activo**), guarda tu `external_id` (la solicitud
queda `SYNCED`) y emite `loan.disbursed` a los demás suscriptores.

`POST /api/webhooks/inbound/{endpoint}/disbursement`

```json
{
  "external_event_id": "cartera-disb-000123",
  "loan_id": "019f6404-9a8b-7c6d-5e4f-3a2b1c0d9e8f",
  "external_id": "CARTERA-CTO-556677",
  "external_system": "CORE_CARTERA",
  "disbursement_reference": "STP-2026071500012345",
  "disbursed_at": "2026-07-15T18:05:10Z"
}
```

Respuesta (200):

```json
{ "success": true, "data": { "status": "processed", "loan_status": "ACTIVE", "application_status": "SYNCED" } }
```

> Hasta que confirmes la dispersión, el crédito NO está activo en LendusFind (no genera
> interés ni saldo). Si tu cartera ingiere y dispersa en un solo paso, este endpoint cierra
> todo el handoff; el `ingest-ack` (§7.3) solo hace falta si son pasos separados.

### 7.2 Confirmar un pago aplicado

`POST /api/webhooks/inbound/{endpoint}/payments`

```json
{
  "external_event_id": "cartera-pay-000123",
  "loan_id": "019f6404-9a8b-7c6d-5e4f-3a2b1c0d9e8f",
  "amount": 200.00,
  "channel": "STP",
  "provider_reference": "STP-88213",
  "paid_at": "2026-07-18T12:29:41Z"
}
```

Respuesta (200):

```json
{ "success": true, "data": { "status": "processed", "outstanding_balance": 205.59 } }
```

Reintento con el mismo `external_event_id`:

```json
{ "success": true, "data": { "status": "duplicate", "outstanding_balance": 205.59 } }
```

### 7.3 Acuse de ingesta (solo si es paso separado de la dispersión)

Si en tu operación la **ingesta a cartera** y la **dispersión** son pasos distintos, usa
este endpoint para marcar la solicitud `SYNCED` sin activar el crédito. Si dispersas e
ingieres a la vez, no lo necesitas — `…/disbursement` (§7.1) ya cierra el handoff.

`POST /api/webhooks/inbound/{endpoint}/ingest-ack`

```json
{
  "external_event_id": "cartera-ack-000123",
  "application_id": "019f6404-1a2b-7c3d-8e4f-5a6b7c8d9e0f",
  "external_id": "CARTERA-CTO-556677",
  "external_system": "CORE_CARTERA"
}
```

Respuesta (200): `{ "success": true, "data": { "status": "processed", "application_status": "SYNCED" } }`

### Firmar tus requests

Usa el mismo esquema de la §5, pero al revés: firma `"{timestamp}.{body}"` con el secreto
del endpoint y manda las cabeceras `X-LendusFind-Signature` y `X-LendusFind-Timestamp`.
Firma inválida o timestamp fuera de ventana → **401**.

---

## 8. API de re-consulta (reconciliación)

Si perdiste un webhook o necesitas reconciliar, relee el recurso. Devuelve **el mismo
`data`** que el webhook, así usas un solo parser.

- `GET /api/v2/integration/applications/{id}`
- `GET /api/v2/integration/loans/{id}`

Autenticación: token de integración de tu tenant (`Authorization: Bearer <token>`, ability
`integration`). Scoped por tenant — solo ves tus recursos.

```bash
curl -H "Authorization: Bearer <TOKEN_INTEGRACION>" \
     -H "X-Tenant-ID: moneycapital" \
     https://api.lendus.app/api/v2/integration/loans/019f6404-9a8b-7c6d-5e4f-3a2b1c0d9e8f
```

---

## 9. Checklist de integración

- [ ] Registrar el endpoint en **sandbox** desde el panel (Admin → Webhooks) y guardar el secreto.
- [ ] Implementar la **verificación de firma** (§5) y probarla con "Enviar evento de prueba".
- [ ] Suscribir los eventos que necesitas (§2). Como mínimo `application.approved` (para dispersar).
- [ ] Procesar **idempotente por `event_id`** y responder 2xx rápido (§6).
- [ ] Al recibir `application.approved`: **dispersar a la CLABE** y **confirmar la dispersión** (§7.1).
- [ ] Implementar la **API entrante** de pagos (§7.2), firmando tus requests.
- [ ] Manejar reintentos y el estado `FAILED` (reenvío desde el panel).
- [ ] Implementar la **re-consulta** para reconciliación (§8).
- [ ] Pasar el endpoint a **producción/activo** cuando todo valide en sandbox.

## 10. Pruebas (sandbox)

1. Marca tu endpoint como `is_sandbox` y actívalo.
2. En el panel, usa **"Enviar evento de prueba"**: recibes un payload de ejemplo firmado.
3. Verifica la firma y el parseo del sobre.
4. Dispara un flujo real en el ambiente de pruebas (aprobar una solicitud) y observa la
   entrega en el **log** (estado, código, respuesta). Usa **"Reenviar"** para repetir.
5. Prueba el entrante con un `POST /payments` y su reintento (debe responder `duplicate`).

---

## Apéndice — Errores y códigos

| Situación | Respuesta |
|-----------|-----------|
| Firma inválida o timestamp fuera de ventana (entrante) | `401` |
| `external_event_id` repetido (entrante) | `200` con `status: "duplicate"` |
| Recurso de otro tenant (re-consulta) | `404` |
| Token sin ability `integration` | `403` |
| Tu endpoint responde no-2xx (saliente) | LendusFind reintenta; tras 5 fallos → `FAILED` |

> **Contacto/soporte:** los ids de entrega (`X-LendusFind-Delivery`) y de evento (`id` del
> sobre) son la referencia para reportar problemas de integración.
