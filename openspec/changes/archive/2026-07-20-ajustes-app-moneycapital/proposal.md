## Why

Relación de ajustes solicitados para la app móvil de MoneyCapital tras la demo: reforzar el onboarding (geolocalización obligatoria, catálogos completos, reanudar progreso, contactos desde la agenda), completar catálogos (bancos válidos, municipios SEPOMEX) y enriquecer el motor de decisión (tipo de propiedad + ubicación). El canal exclusivo-app y la web informativa (#8/#9) se difieren a un change aparte (fase 2).

## What Changes

- **Geolocalización obligatoria**: el permiso de ubicación pasa de fail-open a **bloqueante** — sin permiso, el onboarding no avanza.
- **Catálogos de onboarding**: `EducationLevel` gana `NONE` "Sin educación"; `EmploymentType` cambia el label de `BUSINESS_OWNER` a "Comerciante"; se **unifica** `HousingType` (hoy con 3 sets de valores inconsistentes entre enum/frontend/store) al enum backend canónico.
- **Municipios SEPOMEX**: catálogo estado→municipios servido desde `postal_codes` (ya existe la tabla), con import periódico + schedule que avisa si el archivo está viejo; se corrige el bug `CMX`≠`CDMX`; el paso deja de ser texto libre.
- **Bancos válidos**: el catálogo gana un flag de acreditación inmediata (SPEI/CEP); los bancos no válidos se deshabilitan/avisan en el paso de cuenta.
- **Reanudar progreso**: al reabrir el onboarding, saltar al **primer paso incompleto** (los datos ya se prellenan).
- **Contactos desde la agenda**: en nativo, seleccionar referencias desde la agenda (`@capacitor/contacts` + permisos iOS/Android); en web, captura manual (fallback actual).
- **Motor — tipo de propiedad + ubicación**: recolectar `housing_type` y ubicación en el motor y agregarlos como variables de scoring con **puntos configurables** (editables en el configurador, arrancan en 0).

## Capabilities

### New Capabilities
- **`geolocalizacion-obligatoria`**: el permiso de ubicación es requisito para avanzar el onboarding.
- **`onboarding-catalogos`**: catálogos de onboarding — `EducationLevel.NONE`, label "Comerciante", `HousingType` unificado.
- **`catalogo-municipios-sepomex`**: catálogo estado→municipios desde SEPOMEX con import periódico y aviso de vigencia.
- **`bancos-transferencia-inmediata`**: catálogo de bancos con flag de acreditación inmediata y filtro en el paso de cuenta.
- **`reanudar-onboarding`**: reanudar en el primer paso incompleto al reabrir.
- **`contactos-agenda`**: selección de referencias desde la agenda del teléfono (nativo), fallback manual en web.
- **`motor-housing-ubicacion`**: `housing_type` y ubicación como insumos y variables de scoring del motor de decisión.

### Modified Capabilities
Ninguna spec previa se modifica en sus requisitos (el motor consume variables nuevas de forma genérica; no cambia `decision-engine`).

## Impact

- **Backend**: `EducationLevel`, `EmploymentType`, `HousingType` (enums); `MoneyCapitalSeeder` (steps + política); `DecisionInputCollector` (housing/ubicación); `PostalCode`/`ImportPostalCodes` + nuevo endpoint municipios + schedule; catálogo de bancos (fuente de verdad a definir front o back).
- **Frontend**: `AddressStepRenderer`, `StateCityStepRenderer`, `ReferencesStepRenderer`, `BankAccountStepRenderer`, `WelcomeConsentView`, `DynamicOnboardingView`, `useOnboardingSteps`, `stores/onboarding.ts`, `utils/banks.ts`; nuevo plugin `@capacitor/contacts` (iOS/Android).
- **Datos**: la política de MoneyCapital se re-seedea (nuevos points maps); `postal_codes` se puebla/actualiza; sin migraciones destructivas.

## Non-goals

- **#8/#9 Canal exclusivo-app y web meramente informativa**: fase 2 (change aparte).
- **Definir la matriz de puntos** de housing/ubicación: se deja configurable (arranca en 0); MoneyCapital calibra en el admin.
- **Automatizar la descarga de SEPOMEX**: la descarga del archivo oficial (requiere registro) es manual periódica; solo se automatiza la re-importación y el aviso de vigencia.
