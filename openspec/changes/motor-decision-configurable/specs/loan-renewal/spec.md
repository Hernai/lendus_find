# loan-renewal

## ADDED Requirements

### Requirement: Trigger de renovación al liquidar
Cuando un `Loan` transiciona a `COMPLETED` (saldo cero tras `recordPayment`, sin
importar el canal del pago), el sistema DEBE (MUST) despachar `EvaluateRenewalJob` si el
producto tiene política activa con graduación configurada.

#### Scenario: Pago que salda el crédito
- **WHEN** se registra un pago que deja el saldo en cero
- **THEN** el préstamo queda `COMPLETED` y la evaluación de renovación se encola en ese momento

### Requirement: Graduación por tabla de niveles
El motor DEBE (MUST) calcular el nivel del cliente con la tabla `graduation.levels` y
las reglas de avance: liquidación puntual (pago en o antes de `due_date`) sube un nivel;
pago tardío o uso de prórroga mantiene el nivel; atraso mayor a
`max_late_days_for_auto` inhabilita la oferta automática (salida `REVIEW` o `NO_OFFER`
según política). El nivel determina cupo máximo y plazo máximo de la siguiente oferta.

#### Scenario: Cliente puntual sube de nivel
- **WHEN** un cliente de nivel 0 liquida puntual su primer crédito
- **THEN** la oferta de renovación usa el nivel 1 (ej. cupo $2,000, plazo hasta 10 días)

#### Scenario: Prórroga mantiene nivel
- **WHEN** un cliente liquidó usando una extensión de 7 días
- **THEN** la oferta de renovación mantiene el cupo y plazo de su nivel actual

#### Scenario: Mora relevante no genera oferta automática
- **WHEN** un cliente liquidó con 12 días de atraso y `max_late_days_for_auto: 5`
- **THEN** no se crea oferta automática y la evaluación queda registrada para revisión del staff

### Requirement: Auto-creación de la solicitud de renovación
Cuando la graduación autorice, el sistema DEBE (MUST) crear automáticamente la solicitud
de renovación: estado inicial `COUNTER_OFFERED` (con entrada de historial "creada por el
motor — renovación"), `renewal_of_loan_id` apuntando al préstamo liquidado, datos y KYC
vigentes de la persona reutilizados **sin repetir onboarding**, y la oferta de rango
`[mín producto → cupo del nivel]` × `[7 días → plazo del nivel]` con vigencia
`offer.validity_hours`, notificando al cliente.

#### Scenario: Renovación en minutos
- **WHEN** un cliente elegible liquida su crédito
- **THEN** en minutos recibe la notificación de nueva oferta y la ve al entrar a la app, sin capturar datos de nuevo

#### Scenario: Oferta de renovación expira
- **WHEN** el cliente no responde la oferta de renovación en 72h
- **THEN** el comando de expiración la cancela (`CANCELLED`) sin generar cooldown

### Requirement: Elegibilidad de renovación
El motor NO DEBE (MUST NOT) crear la solicitud de renovación si la persona tiene otra
solicitud activa o un crédito vigente en el tenant (se reutiliza la validación de
unicidad existente), ni si la política está en `SHADOW` (solo registra qué habría
ofrecido).

#### Scenario: Crédito vigente bloquea renovación
- **WHEN** al liquidar, la persona tiene otro crédito activo en el mismo tenant
- **THEN** no se crea solicitud de renovación y la evaluación registra el motivo

#### Scenario: Sombra no crea solicitudes
- **WHEN** la política del producto está en `SHADOW` al liquidar un crédito
- **THEN** solo se registra en `application_decisions` la oferta que se habría generado
