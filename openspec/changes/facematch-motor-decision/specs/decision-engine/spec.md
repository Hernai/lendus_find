## ADDED Requirements

### Requirement: Regla de facematch en la decisión
El motor de decisión DEBE (MUST) recolectar el resultado del facematch (`face_match`) como insumo y, SOLO cuando la política del producto incluye la regla de facematch, aplicarlo así: si el facematch NO coincide (`passed=false`) o no concluyó (ausente), la salida DEBE ser `REVIEW` (nunca `REJECT`); si el facematch coincide (`passed=true`), no aporta ningún motivo negativo. Sin la regla en la política, el resultado del facematch NO altera la salida.

#### Scenario: Facematch no coincide va a revisión
- **WHEN** el motor evalúa una solicitud cuyo `face_match` quedó registrado como no-coincidente y la política incluye la regla de facematch
- **THEN** la salida es `REVIEW` con motivo "la identidad facial no coincide con la INE" y NO se genera oferta ni rechazo automático

#### Scenario: Facematch ausente o sin concluir
- **WHEN** el `face_match` no concluyó o no se ejecutó, en un producto cuya política incluye la regla de facematch
- **THEN** la salida es `REVIEW` (no se auto-oferta sin verificación facial) y nunca rechazo

#### Scenario: Facematch coincide no penaliza
- **WHEN** el `face_match` quedó registrado como coincidente
- **THEN** no aporta ningún motivo de revisión ni de rechazo por identidad facial

#### Scenario: Política sin regla de facematch
- **WHEN** la política del producto no incluye la regla de facematch
- **THEN** el resultado del facematch no altera la salida del motor
