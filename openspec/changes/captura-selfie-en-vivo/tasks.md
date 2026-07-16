## 1. Captura de selfie en vivo (web)

- [ ] 1.1 En el paso de selfie (`KycSelfieStepRenderer`), mostrar un preview `<video>` con el stream de la cámara frontal usando `requestStream({ facing: 'user' })` de `camera.web.ts`
- [ ] 1.2 Botón "Tomar foto" captura el frame con `captureFromVideoElement` (mirror en frontal) y emite la selfie base64; mostrar la foto con "Usar / Retomar"
- [ ] 1.3 Mantener el marco de encuadre facial como overlay del preview ("Coloca tu rostro dentro del marco")
- [ ] 1.4 Detener el stream (`stopStream`) al capturar y al desmontar el paso
- [ ] 1.5 Eliminar el uso de `platform.camera.capture` (input file) en el paso de selfie — sin control de subir archivo/galería

## 2. Permisos y bloqueo

- [ ] 2.1 Si `getUserMedia` falla o el permiso se niega, mostrar una guía para habilitar el permiso y NO permitir avanzar (el paso queda inválido)
- [ ] 2.2 Botón para reintentar la solicitud de cámara tras otorgar el permiso

## 3. Móvil nativo (Capacitor)

- [ ] 3.1 En el flujo nativo (`camera.native.ts` / adapter), forzar `source: CAMERA` para la selfie (sin galería/PROMPT)

## 4. Verificación

- [ ] 4.1 `type-check` + `lint` frontend
- [ ] 4.2 Smoke (ui-smoke o manual): el paso de selfie abre la cámara en vivo, NO permite subir archivo, y sin permiso bloquea el avance
