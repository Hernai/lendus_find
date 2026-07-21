## 1. Backend — persistencia del correo

- [x] 1.1 Route `POST /v2/applicant/profile/email` (grupo applicant autenticado, junto a /personal-data e /identifications).
- [x] 1.2 `ProfileController::updateEmail`: valida `email` (required|email); upsert idempotente de la `ApplicantIdentity` type `EMAIL` (`$account->identities()->updateOrCreate(['type'=>'EMAIL'], ...)`) con `verified_at = null` y `tenant_id` de la cuenta; captura el 23505 (correo duplicado entre cuentas) → 400 `DUPLICATE_EMAIL` en español (patrón de createBankAccount).
- [x] 1.3 `php -l` sin errores.

## 2. Frontend — tipo de step y renderer

- [x] 2.1 `email` en `OnboardingStepType`, interfaz `EmailStep` y unión `OnboardingStep`.
- [x] 2.2 Registrado `email` en `stepRegistry.ts` → `EmailStepRenderer`.
- [x] 2.3 `EmailStepRenderer.vue`: input de correo con validación de formato (regex), estilo mobile-first calcado de Address/PersonalData; emite el string en `update:modelValue` y el booleano en `update:valid`.
- [x] 2.4 `updateEmail(email)` en `profile.service.ts` + `stores/profile.ts`.
- [x] 2.5 `case 'email'` en el switch de `saveStepToBackend` (persistDynamic) → `profileStore.updateEmail`. Además `email` agregado a `MIGRATED_TYPES` en DynamicOnboardingView y rama en `legacyCanContinue` (paridad de reanudación).

## 3. Seeder MoneyCapital

- [x] 3.1 Step `['id'=>'email','type'=>'email','label'=>'Correo electrónico','required'=>true]` agregado como 2º paso (después de `education`, para no tapar el hero card del primer paso).

## 4. Verificación

- [x] 4.1 `npm run type-check` y `php -l` sin errores.
- [x] 4.2 `revisor-lendus`: cazó 1 importante (500 por correo duplicado) + 2 menores (hero card, paridad de reanudación) → corregidos. Upsert/aislamiento de tenant/resolución de cuenta OK.
- [ ] 4.3 PENDIENTE-SMOKE (batch): en el onboarding de MoneyCapital aparece el paso de correo (2º); un correo válido persiste una `ApplicantIdentity` EMAIL con `verified_at` nulo; corregirlo actualiza (no duplica).

## 5. Follow-up recomendado (fuera de este cambio)

- [ ] 5.1 El `onboarding_draft` de localStorage no está aislado por tenant/cuenta (mismo gap que motivó `pruneForeignTenantState` en `application.ts`); ahora carga PII (email). Portar ese guard al onboarding como cambio aparte.
