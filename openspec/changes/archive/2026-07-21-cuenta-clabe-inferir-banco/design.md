## Context

El onboarding de MoneyCapital captura la cuenta bancaria en el step `bank_account`
(`BankAccountStepRenderer.vue`), que hoy pide: tipo (radio CLABE 18 díg. / Tarjeta 16 díg.),
banco por selector manual (bottom-sheet con `MEXICAN_BANKS` del front) y número de cuenta. El
perfil del cliente (`AddBankAccountModal.vue`) ya resuelve mejor la CLABE: al completar 18
dígitos llama `profileStore.validateClabe()` → `POST /v2/applicant/profile/validate-clabe`
(`BankAccountService::extractBankCode()/getBankName()` en backend) y muestra el banco
detectado readonly. Existe además `bankFromClabe()` en `banks.ts` (front) sin uso.

Solo MoneyCapital incluye el step `bank_account` hoy (grep en seeders), así que cambiar el
renderer no impacta a otros tenants en el onboarding.

## Goals / Non-Goals

**Goals:**
- Onboarding: CLABE-only + banco inferido vía backend (misma UX que el perfil), sin tarjeta.
- Perfil: quitar la opción de tarjeta de débito (CLABE-only).

**Non-Goals:**
- Unificar catálogos de bancos front/back; cambiar la validación de CLABE del backend;
  tocar `validForTransfer`; tocar el step bancario de otros tenants.

## Decisions

### 1. Inferencia vía endpoint backend (no `bankFromClabe` del front)
Reusar `validate-clabe` mantiene una sola fuente de verdad y deja el onboarding idéntico al
perfil. *Alternativa descartada:* `bankFromClabe()` (front) evita el round-trip pero usa un
catálogo distinto al backend → riesgo de divergencia (un banco válido en un lado y no en el
otro). Se prefiere coherencia.

### 2. Quitar tarjeta en ambos (onboarding + perfil)
El perfil móvil de MoneyCapital (`/m/perfil`) usa el mismo `AddBankAccountModal`; dejar la
tarjeta solo en el perfil sería incoherente. *Trade-off:* el modal es compartido, así que la
tarjeta desaparece también para el perfil de otros tenants — aceptado por el usuario para
coherencia. Si algún tenant la necesitara, se gatearía después.

### 3. Selector manual como fallback, no como paso obligatorio
Cuando el backend detecta banco, se muestra readonly. Solo si no detecta, se habilita captura
editable. Reusa el patrón exacto del `AddBankAccountModal`.

## Risks / Trade-offs

- **`validate-clabe` durante onboarding** → El endpoint vive bajo `/v2/applicant/profile/`;
  el solicitante está autenticado (token applicant) pero su perfil aún no está completo.
  *Mitigación:* verificar en `ui-smoke` que la llamada responde 200 durante el onboarding; si
  requiere perfil creado, usar el mismo servicio sin depender de datos de perfil.
- **Quitar tarjeta afecta a otros tenants en el perfil** → aceptado; documentado.
- **Código muerto de tarjeta (Luhn, type CARD, card_number)** → retirarlo limpio para no
  dejar ramas sin uso.

## Migration Plan

Cambio de frontend; sin datos ni backend. Rollback = revertir los 2 componentes. No hay
cuentas ya guardadas afectadas (las existentes por tarjeta siguen en la DB; solo se retira la
opción de captura nueva).
