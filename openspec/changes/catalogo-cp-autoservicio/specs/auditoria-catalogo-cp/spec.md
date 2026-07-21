# auditoria-catalogo-cp

## Purpose

Dejar un rastro persistente y consultable de cada carga del catálogo global de códigos
postales, dado que cualquier SUPER_ADMIN (de cualquier tenant) puede reemplazarlo y afecta
a todos, para poder responder después "¿quién cambió el catálogo y cuándo?".

## ADDED Requirements

### Requirement: Registro persistente de cada importación
El sistema DEBE (MUST) registrar de forma persistente cada intento de carga del catálogo,
tanto los aplicados como los rechazados, con al menos: la cuenta staff que la ejecutó, el
tenant de origen desde el que se ejecutó, el nombre del archivo, el conteo de filas
aplicado, el resultado (aplicado o rechazado) y la fecha. Los registros rechazados DEBEN
(MUST) incluir el motivo del rechazo.

#### Scenario: Carga aplicada queda registrada
- **WHEN** un SUPER_ADMIN aplica con éxito una actualización del catálogo
- **THEN** el sistema DEBE (MUST) crear un registro con actor, tenant de origen, archivo, conteo aplicado, resultado "aplicado" y fecha

#### Scenario: Carga rechazada queda registrada con motivo
- **WHEN** una carga se rechaza (formato inválido o conteo por debajo del umbral)
- **THEN** el sistema DEBE (MUST) crear un registro con resultado "rechazado" y el motivo, sin haber modificado el catálogo

### Requirement: Historial visible en el panel
La pantalla de gestión del catálogo DEBE (MUST) exponer el historial de importaciones al
SUPER_ADMIN, ordenado por fecha descendente, mostrando actor, tenant de origen, archivo,
conteo y resultado de cada carga.

#### Scenario: SUPER_ADMIN consulta el historial
- **WHEN** un SUPER_ADMIN abre la pantalla de gestión del catálogo
- **THEN** el sistema DEBE (MUST) listar las importaciones previas ordenadas de la más reciente a la más antigua, con sus datos de auditoría
