# cuenta-clabe-inferir-banco Specification

## Purpose
TBD - created by archiving change cuenta-clabe-inferir-banco. Update Purpose after archive.
## Requirements
### Requirement: Captura de cuenta bancaria solo por CLABE

El step de cuenta bancaria del onboarding y el modal de alta de cuenta del perfil SHALL
capturar la cuenta bancaria únicamente mediante una CLABE de 18 dígitos. NO SHALL ofrecerse
la opción de tarjeta de débito (16 dígitos, validación Luhn) en ninguno de los dos.

#### Scenario: Onboarding sin opción de tarjeta
- **WHEN** el solicitante llega al step de cuenta bancaria en el onboarding
- **THEN** solo puede capturar una CLABE de 18 dígitos; no hay selector de tipo CLABE/Tarjeta

#### Scenario: Perfil sin opción de tarjeta
- **WHEN** el usuario abre el modal de alta de cuenta bancaria en su perfil
- **THEN** solo puede capturar una CLABE de 18 dígitos; no aparece el toggle "Tarjeta de débito"

### Requirement: Inferencia automática del banco desde la CLABE

Al completar los 18 dígitos de la CLABE, el sistema SHALL inferir el banco automáticamente
vía el endpoint `POST /v2/applicant/profile/validate-clabe` (la misma fuente que usa el
perfil) y mostrar el banco detectado en modo lectura con indicador de confirmación. El
usuario NO SHALL tener que seleccionar el banco manualmente cuando se detecta.

#### Scenario: Banco detectado
- **WHEN** el solicitante escribe una CLABE de 18 dígitos válida cuyo código de banco existe
- **THEN** el banco se muestra automáticamente (readonly, con check) sin pedir selección manual

### Requirement: Fallback cuando el banco no se detecta

Si el endpoint no detecta un banco para la CLABE, el sistema SHALL permitir capturar el banco
de forma editable (igual que el perfil hoy), sin bloquear el avance.

#### Scenario: Banco no detectado
- **WHEN** la CLABE de 18 dígitos no corresponde a un banco del catálogo
- **THEN** el usuario puede ingresar/seleccionar el banco manualmente y continuar

