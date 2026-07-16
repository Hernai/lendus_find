## Why

El paso de "Validación facial" del onboarding captura la selfie con `<input type="file" capture="user">` ([camera.web.ts](../../../frontend/src/platform/web/camera.web.ts)). El atributo `capture` es solo una sugerencia que los navegadores móviles suelen ignorar → abren el **selector de archivos / galería**. Esto permite **subir la foto de la INE como selfie**: el facematch (recién implementado en `facematch-motor-decision`) compara INE vs INE, da 100% de coincidencia y aprueba **sin una cara viva**. Bug real detectado en demo.

## What Changes

- Capturar la selfie con **cámara en vivo obligatoria**: preview `<video>` con `getUserMedia` + captura del frame, **sin** opción de subir archivo ni galería.
- Si no hay cámara o se niega el permiso, **bloquear** el paso (no avanza) con una guía para habilitar el permiso y reintentar.
- En móvil nativo (Capacitor), forzar `source: CAMERA` para la selfie (sin galería).
- Mantener el marco de encuadre facial ("Coloca tu rostro dentro del marco"), espejo en cámara frontal, y confirmación **"Usar / Retomar"** tras capturar.

## Capabilities

### New Capabilities
- **`captura-selfie-en-vivo`**: la selfie del onboarding se captura exclusivamente desde cámara en vivo, sin subida de archivos, con bloqueo ante ausencia de cámara/permiso.

### Modified Capabilities
Ninguna. El facematch consume la selfie sin cambiar sus requisitos; el INE no se toca.

## Impact

- **Frontend**: `KycSelfieStepRenderer` (preview en vivo en vez de `platform.camera.capture`), `camera.web.ts` (reusar `requestStream`/`captureFromVideoElement`/`stopStream`, YA existentes), `camera.native.ts` (forzar `source: CAMERA`).
- **Sin backend**: el facematch recién implementado sigue recibiendo la selfie igual (base64).
- Solo afecta la **selfie**; el INE (documento) conserva su captura actual.
- Requiere **HTTPS** para `getUserMedia` (prod ya es HTTPS).

## Non-goals

- **Liveness / prueba de vida**: **fase 2** documentada. La cámara en vivo cierra el ataque demostrado (subir la INE de galería); el liveness cerraría la foto impresa frente al lente (ataque más sofisticado y raro), y ya existe `validateLiveness` para retomarlo.
- **INE**: no cambia su captura (subir/tomar foto de un documento es legítimo).
- **Facematch**: no se toca (`facematch-motor-decision`); esto es upstream de él.
