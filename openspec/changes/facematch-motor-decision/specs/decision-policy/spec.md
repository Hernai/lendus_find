## ADDED Requirements

### Requirement: Umbral de facematch configurable en la política de producto
La política de producto DEBE (MUST) admitir el umbral del facematch en `rules.face_match.min_score`, versionado junto al resto de la política y editable desde el configurador del admin sin deploy. El default DEBE ser 80. Como el resto de la política, el umbral NO DEBE (MUST NOT) exponerse en la API pública.

#### Scenario: Umbral por defecto sembrado
- **WHEN** se siembra la política de producto de MoneyCapital
- **THEN** `rules.face_match.min_score` queda en 80

#### Scenario: Ajuste del umbral desde el configurador
- **WHEN** un administrador edita `face_match.min_score` y activa una versión nueva de la política
- **THEN** las evaluaciones siguientes usan el umbral nuevo sin requerir deploy

#### Scenario: El umbral no se expone públicamente
- **WHEN** un cliente consulta la API pública de configuración
- **THEN** el payload no contiene `face_match.min_score` ni ningún otro umbral de la política
