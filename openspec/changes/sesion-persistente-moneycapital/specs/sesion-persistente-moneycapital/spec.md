## ADDED Requirements

### Requirement: El canal móvil no ofrece cerrar sesión

En el canal móvil (perfil abierto bajo `/m`, `isMobileContext`), la interfaz NO SHALL mostrar
la opción de cerrar sesión ("Salir"), para que la sesión persista y el usuario no tenga que
reingresar por OTP/SMS. En el perfil web ("/perfil") la opción de cerrar sesión SHALL seguir
disponible.

#### Scenario: Sin "Salir" en móvil
- **WHEN** el usuario abre su perfil en `/m/perfil` (canal móvil)
- **THEN** no aparece el botón "Salir"; la sesión permanece activa

#### Scenario: "Salir" disponible en web
- **WHEN** el usuario abre su perfil en `/perfil` (web)
- **THEN** el botón "Salir" sigue disponible y cierra sesión

### Requirement: La sesión persiste entre aperturas de la app

La sesión del solicitante SHALL persistir entre aperturas de la app móvil (token sin
expiración, sin auto-logout), de modo que reabrir la app no requiera volver a autenticarse
mientras no se borren los datos del dispositivo.

#### Scenario: Reabrir la app sin re-login
- **WHEN** el usuario cierra y vuelve a abrir la app móvil sin borrar datos
- **THEN** sigue autenticado, sin pedir OTP
