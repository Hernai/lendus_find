# webhook-admin-ui

## ADDED Requirements

### Requirement: Módulo admin de webhooks con permiso por rol
El admin DEBE (MUST) incluir un módulo "Webhooks" (sistema de módulos + visibilidad por
rol) para gestionar endpoints y ver entregas. Todas las acciones requieren
`canManageProducts` (ADMIN del tenant y SUPER_ADMIN); ANALYST/SUPERVISOR no ven el módulo.
El scoping por tenant DEBE aplicarse en todos los endpoints.

#### Scenario: Admin gestiona sus webhooks
- **WHEN** un ADMIN entra al módulo
- **THEN** puede crear/editar endpoints y ver el log de entregas de su tenant

#### Scenario: Analista no ve el módulo
- **WHEN** un ANALYST navega el admin
- **THEN** el módulo "Webhooks" no aparece en su menú

### Requirement: Alta/edición de endpoints con secreto y sandbox
El panel DEBE (MUST) permitir registrar/editar un endpoint (URL, checklist de eventos
suscritos, activar/desactivar, marcar sandbox), mostrar el secreto una sola vez al crearlo
o rotarlo, y validar que la URL sea HTTPS.

#### Scenario: Ver secreto una sola vez
- **WHEN** el admin crea un endpoint
- **THEN** el secreto se muestra una vez con aviso de guardarlo; después solo puede rotarse

#### Scenario: URL no HTTPS
- **WHEN** el admin captura una URL http://
- **THEN** el formulario impide guardar

### Requirement: Log de entregas con reenvío y prueba
El panel DEBE (MUST) mostrar el log de entregas (estado, código, respuesta, intentos,
evento, fecha) con filtros, permitir **reenviar** una entrega fallida y **enviar un evento
de prueba** a un endpoint para validar la integración.

#### Scenario: Reenviar una entrega fallida
- **WHEN** el admin pulsa "Reenviar" en una entrega `FAILED`
- **THEN** la entrega vuelve a intentarse y el log refleja el nuevo intento

#### Scenario: Enviar evento de prueba
- **WHEN** el admin pulsa "Enviar evento de prueba" sobre un endpoint sandbox
- **THEN** se entrega un payload de ejemplo firmado y el resultado aparece en el log
