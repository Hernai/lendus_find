# phone-risk-gate

## Requirements

### Requirement: Gate telefónico antes de validaciones costosas
Si la política de tenant activa tiene `phone_risk_gate.enabled=true`, el sistema DEBE
(MUST) evaluar el último `RiskAssessment` de tipo `phone_risk` de la persona **antes de
permitir el paso de INE/biometría** del onboarding: score ≥ `block_from` → `BLOCK`
(detener el flujo con mensaje neutro, sin consumir validaciones posteriores); score ≥
`flag_from` → `FLAG` (continúa marcado); score menor → `ALLOW`. Cada resultado del gate
DEBE registrarse en `application_decisions` con `trigger=PHONE_GATE`.

#### Scenario: Score de riesgo alto bloquea
- **WHEN** el score telefónico es 720 con `block_from: 601`
- **THEN** el onboarding se detiene antes del paso de INE con mensaje neutro ("no podemos continuar tu solicitud") y el gate queda registrado

#### Scenario: Score bajo continúa
- **WHEN** el score es 180 con `flag_from: 401`
- **THEN** el onboarding continúa normalmente con resultado `ALLOW`

### Requirement: FLAG fuerza revisión manual en la decisión
Cuando el gate resulte `FLAG`, el onboarding DEBE (MUST) continuar, pero el motor de
decisión NO DEBE producir `OFFER` automática para esa solicitud: la salida máxima
permitida es `REVIEW` (el analista decide).

#### Scenario: Solicitud marcada llega al motor
- **WHEN** una solicitud con gate `FLAG` completa el onboarding y el scoring la ubica en banda BASE
- **THEN** el motor concluye `REVIEW` con la banda sugerida visible para el analista, sin crear oferta

### Requirement: Fail-open ante falla del proveedor
Si el score no está disponible (403 de Nubarium, timeout, servicio no contratado o
assessment inexistente), el gate DEBE (MUST) dejar continuar el onboarding, registrar el
resultado como `score_unavailable`, y el motor DEBE tratar la solicitud como `FLAG`
(revisión manual forzada). Una falla técnica NUNCA DEBE bloquear al cliente.

#### Scenario: Nubarium responde 403
- **WHEN** la consulta de phone risk falla con 403
- **THEN** el cliente continúa su onboarding y la solicitud queda marcada para revisión manual

### Requirement: Gate configurable y desactivable
Sin política de tenant activa, o con `phone_risk_gate.enabled=false`, el comportamiento
DEBE (MUST) ser el actual: el riesgo se consulta async post-OTP con fines informativos y
no bloquea ningún paso.

#### Scenario: Tenant sin gate
- **WHEN** un tenant (demo/finatea) no tiene política de tenant activa
- **THEN** su onboarding no cambia en nada
