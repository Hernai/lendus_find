## ADDED Requirements

### Requirement: Captura de selfie exclusivamente desde cámara en vivo
El paso de validación facial DEBE (MUST) capturar la selfie desde un preview de cámara en vivo (`getUserMedia`, cámara frontal) y NO DEBE (MUST NOT) ofrecer subir un archivo ni seleccionar de la galería.

#### Scenario: Captura en vivo con permiso otorgado
- **WHEN** el cliente llega al paso de selfie con permiso de cámara concedido
- **THEN** se muestra el preview de video en vivo y solo puede tomar la foto del stream (captura del frame), sin control para subir archivo

#### Scenario: No hay opción de galería
- **WHEN** el cliente está en el paso de selfie
- **THEN** no existe ningún control para subir archivo ni elegir de la galería, por lo que no puede aportar la foto de la INE como selfie

#### Scenario: Confirmar o retomar
- **WHEN** el cliente toma la foto en vivo
- **THEN** ve la foto capturada con opciones "Usar" y "Retomar" antes de continuar

### Requirement: Bloqueo sin cámara o sin permiso
Si no hay cámara disponible o el cliente niega el permiso, el paso de selfie NO DEBE (MUST NOT) permitir avanzar; DEBE mostrar cómo habilitar el permiso y permitir reintentar.

#### Scenario: Permiso de cámara denegado
- **WHEN** el cliente niega el permiso de cámara (o `getUserMedia` falla)
- **THEN** el paso muestra una guía para habilitar el permiso y NO permite avanzar hasta que se otorgue y se capture una selfie en vivo

### Requirement: Cámara en vivo también en móvil nativo
En la app nativa (Capacitor), la captura de selfie DEBE (MUST) usar la cámara (`source: CAMERA`), sin permitir selección de galería.

#### Scenario: Nativo abre la cámara directamente
- **WHEN** el cliente captura la selfie en la app nativa
- **THEN** se abre la cámara (no la galería) y la foto proviene de una captura en vivo

### Requirement: El requisito aplica solo a la selfie
La captura en vivo obligatoria DEBE (MUST) aplicar únicamente a la selfie; la captura del INE conserva su comportamiento actual (foto de documento, subir o tomar).

#### Scenario: El INE no cambia
- **WHEN** el cliente captura su INE
- **THEN** puede tomar o subir la foto del documento como hasta ahora, sin el requisito de cámara en vivo
