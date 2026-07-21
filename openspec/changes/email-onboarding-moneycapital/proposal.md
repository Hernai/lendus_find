## Why

El onboarding de MoneyCapital no captura el correo del cliente en ningún paso, así que el
sistema no tiene un email para contacto/notificaciones. Queremos capturarlo en un paso del
flujo y guardarlo como identidad EMAIL (sin verificar), para tenerlo disponible (notificaciones
por correo y, a futuro, login por correo).

## What Changes

- **Nuevo tipo de step `email`** en el onboarding dinámico: tipo en `onboardingStep.ts`,
  `EmailStepRenderer.vue`, registro en `stepRegistry.ts`, validación de formato de correo.
- **Persistencia como identidad EMAIL**: nuevo endpoint backend `POST /v2/applicant/profile/email`
  que crea o actualiza la `ApplicantIdentity` type `EMAIL` (con `verified_at = null`) del
  solicitante autenticado. En `onboarding.ts` se agrega el `case 'email'` que llama a
  `profileStore.updateEmail(email)`.
- **Seeder MoneyCapital**: agregar el step `email` (requerido) al pipeline `onboarding_steps`.

## Non-goals

- No verificar el correo (sin OTP de email en este cambio).
- No cambiar el login de MoneyCapital (sigue por teléfono); el email queda disponible pero no
  obligatorio para autenticación.
- No tocar el onboarding de otros tenants.

## Capabilities

### New Capabilities
- `email-onboarding-moneycapital`: captura del correo electrónico en un paso del onboarding y
  su persistencia como identidad EMAIL sin verificar.

### Modified Capabilities
<!-- Ninguna. -->

## Impact

- **Frontend:** `types/v2/onboardingStep.ts`, `components/onboarding/stepRegistry.ts`, nuevo
  `components/onboarding/steps/EmailStepRenderer.vue`, `stores/onboarding.ts` (case `email`),
  `services/v2/profile.service.ts` + `stores/profile.ts` (`updateEmail`).
- **Backend:** nueva route en `routes/api.php`, `ProfileController::updateEmail` (o servicio)
  que crea/actualiza la `ApplicantIdentity` EMAIL. Sin migración (`ApplicantIdentity` ya
  soporta `type = EMAIL` y `verified_at` nullable).
- **Seeder:** `MoneyCapitalSeeder::onboardingSteps()`.
