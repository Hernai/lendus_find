# application-cooldown

## ADDED Requirements

### Requirement: Bloqueo temporal tras rechazo
Si la persona tiene una solicitud `REJECTED` dentro de los últimos `cooldown.days`
(default 30, configurable en la política de tenant) sin exención registrada, el sistema
DEBE (MUST) impedir crear una nueva solicitud en ese tenant, informando la fecha en que
podrá reintentar. El conteo inicia en la fecha del rechazo.

#### Scenario: Reintento dentro de la ventana
- **WHEN** una persona rechazada hace 10 días (cooldown 30) intenta crear solicitud
- **THEN** el sistema la bloquea indicando que podrá reintentar en 20 días

#### Scenario: Ventana vencida
- **WHEN** el rechazo ocurrió hace 31 días con cooldown de 30
- **THEN** la nueva solicitud procede y el motor la evalúa desde cero

### Requirement: Solo el rechazo real genera cooldown
Únicamente las solicitudes en `REJECTED` (del motor o de un analista) DEBEN (MUST)
generar cooldown. `CANCELLED` (oferta expirada, rechazo de oferta por el cliente,
cancelación) y las fallas técnicas NO DEBEN (MUST NOT) bloquear un nuevo intento.

#### Scenario: Oferta expirada no castiga
- **WHEN** la oferta de una persona expiró (`CANCELLED`) ayer
- **THEN** puede crear una nueva solicitud de inmediato

### Requirement: El bloqueo se informa al inicio, no al final
El estado de cooldown DEBE (MUST) comunicarse al solicitante desde el arranque del
flujo (al autenticarse/iniciar onboarding), antes de capturar datos o consumir
validaciones — no al intentar el submit.

#### Scenario: Cliente bloqueado entra a la app
- **WHEN** una persona en cooldown inicia sesión e intenta comenzar una solicitud
- **THEN** ve desde el inicio el aviso con la fecha de reintento y el flujo no avanza

### Requirement: Desbloqueo controlado y auditado
`POST /v2/staff/applications/{id}/lift-cooldown` DEBE (MUST) permitir a staff con
`canApproveRejectApplications` (SUPERVISOR/ADMIN) levantar el cooldown de la solicitud
rechazada con **motivo obligatorio**, registrando `cooldown_waived_at/by/reason` en la
solicitud. El desbloqueo aplica solo a ese rechazo.

#### Scenario: Supervisor levanta el bloqueo
- **WHEN** un supervisor levanta el cooldown con motivo "falla técnica comprobada"
- **THEN** la persona puede crear solicitud de inmediato y la exención queda auditada

#### Scenario: Motivo obligatorio
- **WHEN** el staff envía la exención sin motivo
- **THEN** el backend responde error de validación

#### Scenario: Sin permiso
- **WHEN** un analista sin `canApproveRejectApplications` intenta levantar un cooldown
- **THEN** el backend responde 403
