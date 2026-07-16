## ADDED Requirements

### Requirement: Regla de facematch en la decisión
El motor de decisión DEBE (MUST) recolectar el resultado del facematch (`face_match`) como insumo y aplicarlo así: si el facematch NO coincide, la salida DEBE ser `REVIEW` (nunca `REJECT`); si el facematch no concluyó (falla técnica o ausente), se trata como insumo faltante y se reintenta hasta el timeout, tras el cual la salida es `REVIEW` con motivo `inputs_incomplete`; si el facematch coincide, no aporta ningún motivo negativo. La regla SOLO aplica cuando la política del producto la incluye.

#### Scenario: Facematch no coincide va a revisión
- **WHEN** el motor evalúa una solicitud cuyo `face_match` quedó registrado como no-coincidente
- **THEN** la salida es `REVIEW` con motivo "la identidad facial no coincide con la INE" y NO se genera oferta ni rechazo automático

#### Scenario: Facematch sin concluir al agotar el timeout
- **WHEN** el `face_match` no concluyó (falla técnica) y ya se agotó el timeout de insumos
- **THEN** la salida es `REVIEW` con motivo `inputs_incomplete` y nunca rechazo

#### Scenario: Facematch coincide no penaliza
- **WHEN** el `face_match` quedó registrado como coincidente
- **THEN** no aporta ningún motivo de revisión ni de rechazo por identidad facial

#### Scenario: Política sin regla de facematch
- **WHEN** la política del producto no incluye la regla de facematch
- **THEN** el resultado del facematch no altera la salida del motor
