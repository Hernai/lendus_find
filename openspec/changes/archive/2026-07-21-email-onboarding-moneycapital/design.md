## Context

El onboarding dinámico renderiza pasos por tipo vía `stepRegistry.ts` (11 tipos base) y
persiste cada paso en `stores/onboarding.ts` (`saveStepToBackend`, un switch por tipo que
llama a métodos de `profileStore` o guarda en `application.metadata`). No existe un tipo
`email` ni un endpoint para guardar el correo (solo hay OTP de auth con `type=email`).
`ApplicantAccount` ya tiene `emailIdentity()` (HasOne where type='EMAIL') y
`getIdentityByType()`; `ApplicantIdentity` tiene `verified_at` nullable y `type` EMAIL. El
step `personal_data` NO sirve para el email en MC porque se salta con `kyc_provider` (Nubarium).

## Goals / Non-Goals

**Goals:** capturar el correo en un paso propio y guardarlo como `ApplicantIdentity` EMAIL
sin verificar, utilizable para notificaciones.

**Non-Goals:** verificar el correo (OTP), cambiar el login de MC, tocar otros tenants.

## Decisions

### 1. Guardar como `ApplicantIdentity` EMAIL (no metadata)
El propósito de pedir el correo es contactar al cliente; una `ApplicantIdentity` EMAIL es el
hogar correcto (la lee el sistema de notificaciones y habilita login por correo a futuro).
*Alternativa descartada:* `application.metadata.email` — cero backend pero el correo quedaría
suelto, sin uso real. Se acepta el costo de un endpoint nuevo.

### 2. Endpoint dedicado idempotente
`POST /v2/applicant/profile/email` crea o actualiza (upsert) la identidad EMAIL de la cuenta
autenticada con `verified_at = null`. Idempotente para permitir corregir el correo sin
duplicar identidades. Sigue el patrón `ApiResponses` de la API V2.

### 3. Nuevo tipo de step declarativo `email`
Se agrega `email` a `OnboardingStepType` + `EmailStep` + registro en `stepRegistry` +
`EmailStepRenderer.vue` (input de correo, validación de formato, mismo estilo mobile-first que
los demás renderers). En `saveStepToBackend` se agrega `case 'email'` → `profileStore.updateEmail`.

### 4. Posición y obligatoriedad en MC
Se agrega el step `email` (requerido) al pipeline de `MoneyCapitalSeeder::onboardingSteps()`
cerca del inicio (dato de contacto temprano). La posición exacta es ajustable reordenando el
arreglo del seeder.

## Risks / Trace-offs

- **Unicidad del correo** → El upsert es por cuenta (una identidad EMAIL por cuenta). No se
  fuerza unicidad global del correo entre cuentas en este cambio (no se verifica).
- **Endpoint durante onboarding** → Vive bajo `/v2/applicant/profile/`; el solicitante está
  autenticado. Verificar en smoke que responde durante el onboarding.
- **Correo no verificado** → Se guarda `verified_at = null`; el sistema debe tratarlo como no
  verificado (no asumir validez).

## Migration Plan

Sin migración (el modelo ya soporta EMAIL). Deploy = build frontend + backend + re-seed del
tenant. Rollback = revertir los archivos y quitar el step del seeder.
