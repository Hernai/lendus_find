## 1. Step de onboarding (BankAccountStepRenderer)

- [x] 1.1 Eliminar la opción/selector de tipo "Tarjeta" (16 díg.): dejar la captura solo como CLABE de 18 dígitos; quitar la validación de 16 dígitos y el estado `type`.
- [x] 1.2 Al completar 18 dígitos, llamar `profileStore.validateClabe()` (endpoint `validate-clabe`) e inferir el banco; mostrarlo readonly con check verde cuando se detecte (solo si es apto para transferencia).
- [x] 1.3 Fallback: si no se detecta banco, permitir capturarlo editable (reusar el patrón del perfil), sin bloquear el avance. Re-valida ante cualquier cambio de la CLABE (edición in-situ / paste-over) con guard de carrera.
- [x] 1.4 Retirar el bottom-sheet/selector manual como paso obligatorio (queda solo como fallback).

## 2. Modal de perfil (AddBankAccountModal)

- [x] 2.1 Eliminar el toggle "Tarjeta de débito" / "CLABE": dejar solo CLABE de 18 dígitos.
- [x] 2.2 Retirar la validación Luhn, el input de tarjeta y el envío de `card_number`.

## 3. Verificación

- [x] 3.1 `npm run type-check` sin errores nuevos (sin variables/imports muertos de `type`/tarjeta).
- [x] 3.2 Revisar el diff con el agente `revisor-lendus`: encontró 1 bloqueante (banco no apto = callejón sin salida) + 2 bugs (re-validación in-situ; race async) → corregidos y re-revisados por código.
- [ ] 3.3 PENDIENTE-SMOKE: `ui-smoke` (moneycapital) — escribir CLABE de 18 díg. y confirmar inferencia del banco en vivo; confirmar que ya no aparece tarjeta (onboarding ni perfil); confirmar que `validate-clabe` responde durante el onboarding. (Ruta y servicio verificados por código; falta confirmación visual.)
