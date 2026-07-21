## ADDED Requirements

### Requirement: Los modales de diálogo no cierran por clic en el backdrop

Los modales de diálogo (formularios, confirmaciones, editores) de la aplicación NO SHALL
cerrarse al hacer clic fuera de su contenido (en el backdrop/overlay). SHALL cerrarse
únicamente mediante su botón de cierre explícito (✕) o de cancelación/acción. Cada modal de
diálogo SHALL conservar al menos un mecanismo de cierre visible.

#### Scenario: Clic en el backdrop no cierra
- **WHEN** el usuario hace clic fuera del contenido de un modal de diálogo (en el área oscura)
- **THEN** el modal permanece abierto y no se pierde lo capturado

#### Scenario: Cierre explícito
- **WHEN** el usuario presiona ✕ o Cancelar en el modal
- **THEN** el modal se cierra

### Requirement: Bottom-sheets y selectores conservan el cierre por clic afuera

Los bottom-sheets y selectores (pickers de banco, estado, número, hojas informativas, etc.)
SHALL seguir cerrándose al tocar fuera de su contenido, por ser la interacción esperada de
ese patrón.

#### Scenario: Tocar afuera de un selector lo cierra
- **WHEN** el usuario toca fuera de un bottom-sheet o selector (p. ej. el picker de banco)
- **THEN** el sheet se cierra
