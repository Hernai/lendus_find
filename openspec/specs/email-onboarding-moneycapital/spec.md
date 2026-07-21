# email-onboarding-moneycapital Specification

## Purpose
TBD - created by archiving change email-onboarding-moneycapital. Update Purpose after archive.
## Requirements
### Requirement: Paso de captura de correo en el onboarding

El onboarding SHALL soportar un tipo de paso `email` que captura el correo electrónico del
solicitante con validación de formato. El paso SHALL ser requerido cuando el producto lo
incluya (MoneyCapital). El solicitante NO SHALL poder avanzar con un correo vacío o con
formato inválido.

#### Scenario: Correo válido permite avanzar
- **WHEN** el solicitante ingresa un correo con formato válido en el paso `email`
- **THEN** el paso se marca válido y puede continuar

#### Scenario: Correo inválido bloquea el avance
- **WHEN** el correo está vacío o con formato inválido
- **THEN** el paso no es válido y no se puede continuar

### Requirement: Persistencia del correo como identidad EMAIL sin verificar

Al completar el paso, el correo SHALL persistirse como una `ApplicantIdentity` de tipo
`EMAIL` asociada a la cuenta del solicitante, con `verified_at = null` (no verificado). Si ya
existe una identidad EMAIL para la cuenta, SHALL actualizarse en lugar de duplicarse.

#### Scenario: Se crea la identidad EMAIL
- **WHEN** el solicitante completa el paso de correo por primera vez
- **THEN** se crea una `ApplicantIdentity` EMAIL con el correo y `verified_at` nulo

#### Scenario: Se actualiza si ya existía
- **WHEN** el solicitante cambia el correo y vuelve a completar el paso
- **THEN** la identidad EMAIL existente se actualiza (no se crea una segunda)

### Requirement: MoneyCapital incluye el paso de correo

El pipeline de onboarding de MoneyCapital SHALL incluir el paso `email` como requerido.

#### Scenario: MoneyCapital pide el correo
- **WHEN** un solicitante de MoneyCapital recorre el onboarding
- **THEN** encuentra el paso de correo y debe capturarlo para continuar

