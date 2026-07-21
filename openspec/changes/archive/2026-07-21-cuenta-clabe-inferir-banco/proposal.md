## Why

El step de cuenta bancaria del onboarding de MoneyCapital pide elegir el banco a mano de un
selector y ofrece capturar tarjeta de débito (16 díg.) además de CLABE. El perfil del
cliente (`AddBankAccountModal`) ya infiere el banco automáticamente desde la CLABE vía
backend. Queremos la misma experiencia "escribe la CLABE y listo" en el onboarding, y quitar
la tarjeta de débito para simplificar (SPEI/CEP usa CLABE).

## What Changes

- **Step de onboarding** (`BankAccountStepRenderer`, hoy usado solo por MoneyCapital):
  - Eliminar la opción "Tarjeta" (16 díg.): el step queda **solo CLABE** (18 díg.).
  - Al completar los 18 dígitos, **inferir el banco automáticamente** vía el endpoint
    `POST /v2/applicant/profile/validate-clabe` (mismo que usa el perfil); mostrar el banco
    detectado en readonly con check verde.
  - Si la CLABE no arroja banco, **fallback** a captura de banco editable (como el perfil).
  - Retirar el selector manual/bottom-sheet como paso obligatorio (queda solo como fallback).
- **Modal de perfil** (`AddBankAccountModal`, compartido): eliminar el toggle "Tarjeta de
  débito" → **solo CLABE**. Retirar la validación Luhn y el envío de `card_number`.

## Non-goals

- No unificar los dos catálogos de bancos (frontend `banks.ts` vs backend `BankAccountService`):
  se usa el backend como fuente de verdad (vía `validate-clabe`).
- No cambiar la validación de CLABE del backend (ya existe).
- No tocar el filtro `validForTransfer` (bancos no aptos para transferencia).
- No tocar el step bancario de otros tenants (solo MoneyCapital usa `bank_account` hoy).

## Capabilities

### New Capabilities
- `cuenta-clabe-inferir-banco`: captura de cuenta bancaria por CLABE de 18 dígitos con banco
  inferido automáticamente (vía backend) y sin opción de tarjeta de débito, tanto en el step
  de onboarding como en el modal de alta de cuenta del perfil.

### Modified Capabilities
<!-- Ninguna: no cambian requisitos de specs existentes (bancos-transferencia-inmediata sigue igual). -->

## Impact

- **Frontend:** `frontend/src/components/onboarding/steps/BankAccountStepRenderer.vue`,
  `frontend/src/components/AddBankAccountModal.vue`. Reusa `profileStore.validateClabe()` →
  `POST /v2/applicant/profile/validate-clabe`.
- **A verificar:** que `validate-clabe` sea invocable durante el onboarding (el solicitante
  está autenticado con token applicant, pero el perfil aún no está completo).
- **Impacto cross-tenant (explícito):** quitar la tarjeta del modal de perfil afecta el
  perfil de todos los tenants (no solo MC). Decisión tomada para coherencia con el perfil
  móvil de MoneyCapital.
- Sin cambios de backend, base de datos ni de la lógica de validación de CLABE.
