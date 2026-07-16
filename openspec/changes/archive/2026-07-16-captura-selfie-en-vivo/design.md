## Context

`KycSelfieStepRenderer` llama `platform.camera.capture()`, que en web (`camera.web.ts:208`) crea un `<input type="file" accept="image/*" capture="user">`. `capture` es una sugerencia que los navegadores móviles suelen ignorar → abren el selector/galería. El cliente puede elegir la foto de la INE → el facematch la compara con la INE = 100% → aprueba sin cara viva. En `camera.web.ts` YA existen helpers de cámara en vivo (`requestStream`, `captureFromVideoElement`, `stopStream`) vía `getUserMedia`, pero el paso de selfie no los usa. En nativo, `camera.native.ts` usa `@capacitor/camera`.

## Goals / Non-Goals

**Goals:** captura de selfie exclusivamente desde cámara en vivo; sin fallback a galería; bloqueo claro sin cámara/permiso; paridad web y nativo.

**Non-Goals:** liveness (fase 2); captura del INE; el facematch.

## Decisions

### 1. Preview `<video>` en vivo con `getUserMedia`
El paso de selfie muestra un `<video>` con el stream de la cámara frontal (`requestStream({ facing: 'user' })`) y captura el frame con `captureFromVideoElement` (mirror en frontal). Se reusan los helpers existentes de `camera.web.ts`.
- **Alternativa (input[capture] "mejorado")**: descartada — `capture` no fuerza cámara en móvil; el selector/galería sigue disponible.
- **Rationale**: es la única forma de garantizar captura en vivo y bloquear la subida de la INE.

### 2. Sin fallback a archivo/galería
No se ofrece subir archivo en el paso de selfie. Si la cámara no está disponible, se bloquea (Decisión 3), no se cae a galería.
- **Alternativa (fallback a archivo)**: descartada — reabre exactamente el hueco (subir la INE).

### 3. Bloqueo sin cámara/permiso
Si `getUserMedia` falla o el permiso se niega, el paso NO permite avanzar: muestra una guía para habilitar el permiso y un botón para reintentar.
- **Alternativa (fail-open a REVIEW)**: considerada; se prefiere bloquear para que TODA solicitud tenga selfie en vivo (decisión del grilling).

### 4. Nativo fuerza cámara
En Capacitor, la captura de selfie usa `source: CAMERA` (sin `PROMPT`/galería), coherente con web.

### 5. Solo la selfie
El INE conserva su captura actual (documento). El requisito de cámara en vivo aplica únicamente a la selfie.

## Risks / Trade-offs

- **Cliente sin cámara/permiso no puede continuar → pérdida de conversión** → guía clara para habilitar el permiso; los clientes móviles casi siempre tienen cámara frontal.
- **`getUserMedia` requiere HTTPS y un contexto seguro** → prod es HTTPS; en dev local usar `localhost` (contexto seguro permitido).
- **Foto impresa de la INE frente al lente aún pasaría (sin liveness)** → fase 2 (liveness); el ataque es más raro que subir un JPG.

## Migration Plan

Sin migración de datos. Deploy de frontend. Rollback: revertir el componente del paso de selfie (el facematch sigue recibiendo la selfie base64 igual).

## Open Questions

- ¿Reusar el marco de encuadre facial existente del diseño o rediseñarlo sobre el `<video>`? Propuesta: reusar el marco actual como overlay del preview.
