## ADDED Requirements

### Requirement: Ejecución del facematch en el onboarding dinámico
El flujo de onboarding dinámico DEBE (MUST) comparar la selfie capturada contra la imagen de la INE del solicitante mediante el servicio de facematch, al confirmar el paso de selfie, SOLO si el tenant tiene proveedor KYC activo (`hasKycProvider`). La imagen de la INE proviene del paso `kyc_ine`, que precede al de selfie.

#### Scenario: Tenant con proveedor KYC ejecuta el facematch
- **WHEN** el solicitante confirma la selfie en un tenant con proveedor KYC activo
- **THEN** el sistema invoca el facematch con la selfie y la imagen de la INE ya capturada en el paso `kyc_ine`

#### Scenario: Tenant sin proveedor KYC no ejecuta el facematch
- **WHEN** el solicitante confirma la selfie en un tenant sin proveedor KYC
- **THEN** el sistema NO invoca el facematch y la selfie se guarda como documento sin comparación

### Requirement: Registro persistente del resultado del facematch
El sistema DEBE (MUST) registrar SIEMPRE el resultado del facematch en `person.kyc_data['face_match']` con `passed` (booleano) y el `score`: `passed = true` cuando el score alcanza el umbral (coincide) y `passed = false` cuando no lo alcanza (no coincide). Una falla técnica del proveedor (error o timeout) NO DEBE escribir el resultado; queda ausente (sin concluir).

#### Scenario: La selfie coincide con la INE
- **WHEN** el facematch devuelve un score mayor o igual al umbral
- **THEN** `kyc_data.face_match.passed` queda en `true` con el `score` registrado

#### Scenario: La selfie NO coincide con la INE
- **WHEN** el facematch devuelve un score menor al umbral
- **THEN** `kyc_data.face_match.passed` queda en `false` con el `score` registrado

#### Scenario: Falla técnica del proveedor
- **WHEN** el facematch falla por error o timeout del proveedor
- **THEN** no se escribe `kyc_data.face_match` y el resultado queda sin concluir

### Requirement: Fail-open y sin exposición del resultado al cliente
La ejecución del facematch NO DEBE (MUST NOT) bloquear el avance del solicitante ni mostrarle el resultado. Ante una falla técnica del proveedor, el solicitante DEBE poder continuar (Regla 21, fail-open); la solicitud queda para revisión por el insumo sin concluir.

#### Scenario: El cliente avanza pese a un no-match
- **WHEN** el facematch no coincide o falla técnicamente
- **THEN** el solicitante avanza en el onboarding sin ver el resultado y sin quedar bloqueado

### Requirement: El umbral proviene de la política de producto
El umbral del facematch DEBE (MUST) resolverse desde la política del motor del producto de la solicitud; el sistema NO DEBE (MUST NOT) confiar en un umbral enviado por el cliente cuando existe política. Sin política aplica el default 80.

#### Scenario: Umbral tomado de la política
- **WHEN** el producto tiene política con `face_match.min_score` definido
- **THEN** el facematch usa ese umbral e ignora cualquier umbral recibido en el request
