# webhook-endpoints

## ADDED Requirements

### Requirement: Registro de endpoints suscriptores por tenant
El sistema DEBE (MUST) persistir endpoints de webhook en `webhook_endpoints` (tenant
scoping) con: `url` (HTTPS), `secret` encriptado (patrón `Crypt`), `events` (lista de
nombres suscritos o `["*"]`), `is_active`, `is_sandbox`, `name`. Un tenant puede tener
varios endpoints (p. ej. cartera y BI). El secreto NUNCA DEBE (MUST NOT) devolverse en
claro en respuestas de lectura; solo se muestra al crearlo o al rotarlo.

#### Scenario: Alta de endpoint
- **WHEN** el admin registra un endpoint con URL, eventos `["application.approved","loan.disbursed"]` y activo
- **THEN** se guarda con el secreto generado y encriptado, y queda elegible para recibir esos eventos

#### Scenario: El secreto no se expone en lecturas
- **WHEN** se consulta el listado o el detalle de un endpoint
- **THEN** el `secret` no viaja en claro (solo un indicador de que existe)

#### Scenario: Aislamiento entre tenants
- **WHEN** un admin intenta operar un endpoint de otro tenant
- **THEN** el backend responde no encontrado (scoping por tenant)

### Requirement: Rotación de secreto y activación
El sistema DEBE (MUST) permitir rotar el secreto de un endpoint (invalida el anterior,
devuelve el nuevo una sola vez) y activar/desactivar o marcar `is_sandbox` sin afectar
otros endpoints. Un endpoint inactivo NO DEBE (MUST NOT) recibir entregas.

#### Scenario: Rotar secreto
- **WHEN** el admin rota el secreto
- **THEN** las firmas siguientes usan el nuevo secreto y el anterior deja de validar

#### Scenario: Endpoint inactivo no recibe
- **WHEN** ocurre un evento suscrito por un endpoint `is_active=false`
- **THEN** no se crea entrega para ese endpoint

### Requirement: Suscripción granular por evento
El endpoint DEBE (MUST) recibir solo los eventos de su lista `events` (o todos si es
`["*"]`). Un evento no suscrito NO DEBE generar entrega para ese endpoint.

#### Scenario: Evento no suscrito
- **WHEN** ocurre `payment.received` y el endpoint solo suscribe `application.approved`
- **THEN** no se crea entrega para ese endpoint
