## 1. Captura de selfie en vivo (web)

- [x] 1.1 En el paso de selfie (`KycSelfieStepRenderer`), mostrar un preview `<video>` con el stream de la cámara frontal usando `requestStream({ facing: 'user' })` de `camera.web.ts`
- [x] 1.2 Botón "Tomar foto" captura el frame con `captureFromVideoElement` (mirror en frontal) y emite la selfie base64; mostrar la foto con "Tomar otra" (retomar) — "Usar" es el "Continuar" del footer
- [x] 1.3 Mantener el marco de encuadre facial como overlay del preview ("Coloca tu rostro dentro del marco")
- [x] 1.4 Detener el stream (`stopStream`) al capturar y al desmontar el paso (`onBeforeUnmount`)
- [x] 1.5 Eliminar el uso de `platform.camera.capture` en web (input file); en web se usa preview en vivo, sin control de subir archivo/galería

## 2. Permisos y bloqueo

- [x] 2.1 Si `getUserMedia` falla o el permiso se niega, mostrar una guía para habilitar el permiso y NO permitir avanzar (sin selfie, isValid=false → el runner bloquea "Continuar")
- [x] 2.2 Botón "Reintentar" para volver a solicitar la cámara tras otorgar el permiso

## 3. Móvil nativo (Capacitor)

- [x] 3.1 Nativo usa `platform.camera.capture` → `camera.native.ts` YA fija `source: CameraSource.Camera` (abre la cámara, sin galería). Sin cambios necesarios.

## 4. Verificación

- [x] 4.1 `type-check` + `lint` frontend (verde)
- [ ] 4.2 PENDIENTE (manual): smoke del paso de selfie en navegador con cámara — getUserMedia no se automatiza fácil en el stack desechable (requiere fake media stream). Se verifica en la demo real: abre cámara en vivo, no permite subir archivo, sin permiso bloquea.
