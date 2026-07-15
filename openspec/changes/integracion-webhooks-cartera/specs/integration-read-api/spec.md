# integration-read-api

## ADDED Requirements

### Requirement: Lectura del expediente/crédito estandarizado
`GET /api/v2/integration/applications/{id}` y `GET /api/v2/integration/loans/{id}` DEBEN
(MUST) devolver el **mismo esquema estandarizado** que viaja en el payload de los webhooks
(el recurso `data` del sobre), scoped por tenant, autenticado con el token de integración
del tenant (Sanctum ability `integration`) o HMAC. Sirve para reconciliar o recuperar un
evento perdido.

#### Scenario: Re-consulta tras perder un webhook
- **WHEN** el sistema externo perdió `application.approved` y consulta la solicitud por su id
- **THEN** recibe el mismo `data` que habría traído el webhook

#### Scenario: Aislamiento por tenant
- **WHEN** el token de un tenant consulta un recurso de otro tenant
- **THEN** el backend responde no encontrado

#### Scenario: Sin ability de integración
- **WHEN** un token sin la ability `integration` consulta el endpoint
- **THEN** el backend responde 403

### Requirement: Esquema consistente webhook ↔ lectura
El recurso devuelto por la API de lectura DEBE (MUST) ser idéntico en forma al `data` del
webhook del mismo recurso, construido por el mismo `WebhookPayloadBuilder`, para que el
integrador use un único parser.

#### Scenario: Mismo builder
- **WHEN** se compara el `data` de `loan.disbursed` con `GET /integration/loans/{id}`
- **THEN** ambos tienen la misma estructura de campos
