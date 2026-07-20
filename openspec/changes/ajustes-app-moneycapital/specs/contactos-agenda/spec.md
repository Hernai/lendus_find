## ADDED Requirements

### Requirement: Selección de referencias desde la agenda en nativo
En la app nativa (`platform.device.isNative() === true`), el paso de referencias (`ReferencesStepRenderer`) DEBE (MUST) ofrecer un botón "Elegir de mis contactos" que abra el selector de contactos del sistema mediante el plugin `@capacitor/contacts` y, al elegir un contacto, prellene el nombre y el teléfono de la referencia correspondiente (familiar o personal). El plugin `@capacitor/contacts` DEBE (MUST) agregarse a `frontend/package.json` (hoy no está entre las dependencias de Capacitor).

#### Scenario: Nativo muestra el botón de contactos
- **WHEN** el cliente llega al paso de referencias en la app nativa
- **THEN** en cada sección (familiar y personal) se muestra el botón "Elegir de mis contactos" además de los campos de captura manual

#### Scenario: Elegir un contacto prellena nombre y teléfono
- **WHEN** el cliente pulsa "Elegir de mis contactos" y selecciona un contacto con nombre y teléfono
- **THEN** los campos de nombre y teléfono de esa referencia se prellenan con los datos del contacto elegido y el teléfono se normaliza a 10 dígitos

#### Scenario: El dato prellenado sigue siendo editable y validado
- **WHEN** una referencia se prellenó desde la agenda
- **THEN** el cliente puede editar nombre y teléfono antes de continuar, y siguen aplicando las validaciones del paso (nombre con ≥2 palabras, teléfono de 10 dígitos, referencias distintas entre sí y del propio cliente)

### Requirement: Captura manual como fallback en web
En web (no nativo, `platform.device.isNative() === false`), el paso de referencias DEBE (MUST) conservar la captura manual actual (inputs de nombre y teléfono) y NO DEBE (MUST NOT) mostrar el botón "Elegir de mis contactos".

#### Scenario: Web no ofrece el picker de contactos
- **WHEN** el cliente llega al paso de referencias en el navegador (web/PWA)
- **THEN** solo se muestran los inputs manuales de nombre y teléfono, sin el botón "Elegir de mis contactos"

#### Scenario: El fallback manual permite completar el paso
- **WHEN** el cliente captura manualmente las referencias en web
- **THEN** el paso valida y permite continuar exactamente igual que hoy, sin depender de la agenda del dispositivo

### Requirement: Permisos nativos de agenda y manejo de denegación
La app nativa DEBE (MUST) declarar los permisos de acceso a contactos: `NSContactsUsageDescription` en `frontend/ios/App/App/Info.plist` (iOS) y `android.permission.READ_CONTACTS` en `frontend/android/app/src/main/AndroidManifest.xml` (Android). Si el cliente niega el permiso o cancela el selector, el paso NO DEBE (MUST NOT) bloquearse ni romperse: DEBE (MUST) permanecer en captura manual.

#### Scenario: Permiso de contactos denegado
- **WHEN** el cliente pulsa "Elegir de mis contactos" y niega el permiso de acceso a la agenda
- **THEN** el paso sigue funcionando con captura manual y el cliente puede completar sus referencias escribiéndolas

#### Scenario: El cliente cancela el selector
- **WHEN** el cliente abre el selector de contactos y lo cierra sin elegir a nadie
- **THEN** no se modifica ningún campo y puede continuar capturando manualmente o reintentar el picker

### Requirement: Acceso a la agenda vía abstracción platform.contacts
Todo acceso a la agenda del dispositivo DEBE (MUST) pasar por una nueva abstracción `platform.contacts` (interfaz en `platform/types.ts`, registrada en `platform/index.ts` con adapters `native` y `web`); los componentes NO DEBEN (MUST NOT) importar `@capacitor/contacts` directamente. La implementación web DEBE (MUST) ser no-op.

#### Scenario: El componente consume la abstracción, no el plugin
- **WHEN** `ReferencesStepRenderer` necesita seleccionar un contacto
- **THEN** invoca `platform.contacts` y no importa `@capacitor/contacts` de forma directa

#### Scenario: La implementación web es no-op
- **WHEN** se invoca la selección de contactos de `platform.contacts` corriendo en web
- **THEN** el adapter web reporta que no está soportado y devuelve un resultado nulo sin lanzar error
