## ADDED Requirements

### Requirement: Bloqueo del avance sin permiso de ubicación

La pantalla unificada de consentimiento (`WelcomeConsentView.vue`, paso 1 nativo) DEBE (MUST) tratar el permiso de ubicación como requisito para avanzar: si `platform.geolocation.requestPermission()` devuelve `denied`, el flujo NO DEBE (MUST NOT) continuar a la pantalla de auth. Hoy es fail-open — `handleContinue()` ignora el estado `denied`, solo registra "Permisos parciales" y ejecuta `router.replace(.../auth)` de todos modos; este comportamiento se reemplaza por un gate bloqueante.

#### Scenario: Permiso denegado bloquea el avance
- **WHEN** el usuario marca el checkbox de aceptación, presiona "Continuar" y `platform.geolocation.requestPermission()` resuelve `denied`
- **THEN** la app NO navega a `/{slug}/auth` y permanece en `WelcomeConsentView` mostrando por qué se requiere la ubicación

#### Scenario: Permiso otorgado permite continuar
- **WHEN** `platform.geolocation.requestPermission()` resuelve `granted` (o `prompt` que termina en `granted`)
- **THEN** la app guarda los consents y navega a la pantalla de auth como hasta ahora

#### Scenario: Botón deshabilitado hasta resolver el permiso
- **WHEN** el permiso de ubicación aún no está otorgado
- **THEN** el control de avance ("Continuar") no permite pasar a la siguiente pantalla, además del requisito ya existente de haber aceptado el checkbox

### Requirement: Guía de reintento cuando el permiso está denegado

Cuando el permiso de ubicación queda en `denied`, la pantalla DEBE (MUST) explicar al usuario cómo habilitarlo (en ajustes del dispositivo/navegador) y ofrecer reintentar la solicitud sin salir del flujo. El estado `unsupported` (el dispositivo/navegador no ofrece geolocalización) DEBE (MUST) tratarse de forma que no deje al usuario atorado sin salida.

#### Scenario: Reintento tras denegar
- **WHEN** el permiso está `denied` y el usuario habilita la ubicación en ajustes y pulsa reintentar
- **THEN** la app vuelve a llamar `platform.geolocation.requestPermission()` y, si ahora resuelve `granted`, permite avanzar

#### Scenario: Geolocalización no soportada
- **WHEN** `platform.geolocation.isSupported()` es falso o `requestPermission()` devuelve `unsupported`
- **THEN** el usuario recibe un mensaje claro y no queda bloqueado de forma permanente sin ninguna acción posible

### Requirement: Uso de la abstracción de plataforma nativa/web

El gate de ubicación DEBE (MUST) usar la abstracción `platform.geolocation` (que resuelve a `geolocationNative` vía `@capacitor/geolocation` en la app nativa y a `geolocationWeb` vía `navigator.geolocation` en web) y NO DEBE (MUST NOT) llamar `navigator.geolocation` directamente en `WelcomeConsentView`, de modo que el bloqueo funcione igual en iOS/Android nativo y en web.

#### Scenario: Nativo usa la implementación de Capacitor
- **WHEN** la app corre en un dispositivo nativo y se evalúa el gate de ubicación
- **THEN** el permiso se solicita mediante `platform.geolocation.requestPermission()` que delega en `Geolocation.requestPermissions` de Capacitor

#### Scenario: Web usa navigator.geolocation vía la abstracción
- **WHEN** la app corre en web y se evalúa el gate de ubicación
- **THEN** el permiso se resuelve mediante `platform.geolocation` (implementación web), sin invocar `navigator.geolocation` directamente desde la vista

### Requirement: El botón "Estoy en mi domicilio" del paso address sigue opcional

El gate bloqueante aplica únicamente al consentimiento inicial de `WelcomeConsentView`; el botón "Estoy en mi domicilio" del paso `address` (`AddressStepRenderer.vue`) DEBE (MUST) seguir siendo opcional y NO DEBE (MUST NOT) impedir completar ni avanzar el paso de domicilio si el usuario no lo usa o niega el permiso ahí.

#### Scenario: Domicilio se completa sin usar la geolocalización del paso
- **WHEN** el usuario llena el domicilio a mano sin pulsar "Estoy en mi domicilio" (o niega el permiso en ese paso)
- **THEN** el paso `address` se valida y permite avanzar con los datos capturados, sin exigir lat/lng
