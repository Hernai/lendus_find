## Context

Flujo vivo de MoneyCapital = onboarding dinámico (`DynamicOnboardingView` + `MoneyCapitalSeeder::onboardingSteps()`). Los 10 ajustes tocan enums, seeder, motor de decisión, catálogos y pasos del onboarding. Este design agrupa el trabajo en 7 capabilities lo más independientes posible para implementarlas en paralelo (worktrees), respetando una dependencia: el motor de scoring de housing necesita primero el `HousingType` unificado.

## Goals / Non-Goals

**Goals:** implementar los 9 ítems del documento (todos menos #8/#9), con catálogos reales (SEPOMEX, bancos), geolocalización bloqueante, reanudar progreso, contactos nativos y variables nuevas de motor con puntos configurables.

**Non-Goals:** canal exclusivo-app / web informativa (#8/#9, fase 2); definir la matriz de puntos (queda configurable en 0); automatizar la descarga de SEPOMEX (solo re-import + aviso).

## Decisions

### 1. `geolocalizacion-obligatoria`
Gatear el avance en `WelcomeConsentView` (paso 1 nativo): si `platform.geolocation.requestPermission()` queda `denied`, NO permitir continuar — pantalla que explica cómo habilitarlo + reintentar. El botón "Estoy en mi domicilio" del paso `address` sigue opcional (el gate principal es el consent). Usar la abstracción `platform.geolocation` (no `navigator.geolocation` directo).

### 2. `onboarding-catalogos` (bloquea a #7)
- `EducationLevel`: `case NONE = 'NONE'`, label "Sin educación", en `label()`/`normalize()`.
- `EmploymentType`: label de `BUSINESS_OWNER` → "Comerciante" (el value NO cambia, para no romper datos/scoring); agregar `'COMERCIANTE'` al `normalize()`.
- `HousingType` **unificado** al enum backend canónico (`OWNED_PAID/OWNED_MORTGAGE/RENTED/FAMILY/BORROWED/OTHER`): adaptar `AddressStepRenderer` (hoy `OWN/RENT/FAMILY/OTHER`) y el `HOUSING_MAP` del store (hoy `OWNED/RENTED/FAMILY/MORTGAGED/EMPLOYER`) para emitir/mapear a los valores canónicos. Esta unificación es prerrequisito de #7.

### 3. `catalogo-municipios-sepomex`
- Endpoint `GET` municipios por estado desde `postal_codes` (`DISTINCT municipio WHERE estado_clave = ?`), consumido por `StateCityStepRenderer` (reemplaza `SUGGESTED_CITIES`).
- Fix del bug `CMX` → `CDMX` (`StateCityStepRenderer.vue:57`).
- Schedule (`routes/console.php`) que avisa si `postal_codes` está vacía o el import es viejo; el comando `postal-codes:import` ya existe. La descarga del archivo oficial (Correos, requiere registro) es manual periódica.

### 4. `bancos-transferencia-inmediata`
- Agregar flag `valid_for_transfer` (acreditación inmediata SPEI/CEP) al catálogo. **Un agente investiga** qué instituciones lo tienen (Banxico/SPEI) y puebla el flag; el usuario valida la lista.
- `BankAccountStepRenderer` deshabilita o avisa los bancos no válidos.
- **Open question**: ¿el catálogo canónico vive en `frontend/utils/banks.ts` (hoy) o se mueve a backend? Propuesta: mantener en front por ahora, agregar el flag ahí.

### 5. `reanudar-onboarding`
En `DynamicOnboardingView` (onMounted, cuando no hay `stepId` en la URL), calcular el **primer paso incompleto** (recorrer `steps` y evaluar la validez de cada uno contra `dynamicData`/perfil) y `router.replace` a ese paso, en vez de a `steps[0]`. Los datos ya se prellenan (draft + backend).

### 6. `contactos-agenda`
Plugin `@capacitor/contacts` + permisos nativos (iOS `NSContactsUsageDescription`, Android `READ_CONTACTS`). En `ReferencesStepRenderer`, botón "Elegir de mis contactos" (solo nativo, `platform.device.isNative()`) que abre el picker y rellena nombre+teléfono; en web, la captura manual actual (fallback). Abstracción nueva `platform.contacts` (web = no-op/null).

### 7. `motor-housing-ubicacion` (depende de #2)
- `DecisionInputCollector::collect()` agrega `housing_type` a `variables` (hoy no lo recolecta; `state`/`city` ya sí).
- La política (seeder) gana entradas en `scoring.variables` para `housing_type` y `state`, con `points` maps **en 0** (editables en el configurador). El `DecisionEngineService` ya suma variables genéricamente, así que es config.

## Risks / Trade-offs

- **HousingType unificado rompe datos existentes** (Address.housing_type con valores viejos) → mapear valores legacy en la lectura; documentar en tasks.
- **Contactos**: build nativo iOS/Android + permisos → el más pesado; su worktree no debe bloquear a los demás.
- **Re-seed de política** cambia points maps → SKIP_SEED en deploys que no quieran resetear; los puntos nuevos arrancan en 0 (no alteran decisiones hasta calibrar).
- **SEPOMEX**: si `postal_codes` no está poblada en prod, el catálogo de municipios sale vacío → el schedule/aviso lo detecta.

## Migration Plan

Sin migraciones de schema destructivas. `postal_codes` se puebla con el comando. La política se re-seedea (idempotente, no sobrescribe ediciones del admin). Rollback por capability (worktrees independientes).

## Open Questions

- Bancos: ¿catálogo canónico se queda en front o migra a backend? (propuesta: front por ahora).
- Geo: ¿el paso `address` además exige lat/lng, o basta el gate del consent inicial? (propuesta: basta el consent).
