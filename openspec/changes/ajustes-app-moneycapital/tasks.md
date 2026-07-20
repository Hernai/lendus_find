# Tasks — ajustes-app-moneycapital

Orden por dependencia: `onboarding-catalogos` (unifica `HousingType`) va ANTES de
`motor-housing-ubicacion`. La investigación de bancos SPEI va antes de implementar
`bancos-transferencia-inmediata`. El grupo de verificación cierra al final.

## 1. onboarding-catalogos

- [ ] 1.1 Agregar `case NONE = 'NONE'` a `backend/app/Enums/EducationLevel.php`, con `label()` → "Sin educación" (nuevo brazo del `match`, sin romper los 7 niveles existentes).
- [ ] 1.2 En `EducationLevel::normalize()` (`backend/app/Enums/EducationLevel.php`) mapear `'NONE'` (directo vía `tryFrom`) y la cadena legacy `'SIN EDUCACION'` → `EducationLevel::NONE`.
- [ ] 1.3 En `backend/app/Enums/EmploymentType.php` cambiar el `label()` de `BUSINESS_OWNER` de "Empresario" a "Comerciante" (línea ~30) SIN tocar el value backing `'BUSINESS_OWNER'`.
- [ ] 1.4 En `EmploymentType::normalize()` (`backend/app/Enums/EmploymentType.php`) agregar el mapeo de la cadena `'COMERCIANTE'` → `EmploymentType::BUSINESS_OWNER` (junto al `'EMPRESARIO'` existente en línea ~86).
- [ ] 1.5 Ampliar `HousingType::normalize()` (`backend/app/Enums/HousingType.php`) para mapear los valores legacy del frontend/store — `OWN`→`OWNED_PAID`, `OWNED`→`OWNED_PAID`, `RENT`/`RENTED`→`RENTED`, `MORTGAGED`→`OWNED_MORTGAGE`, `EMPLOYER`→`OTHER` — devolviendo `null` (sin excepción) cuando no aplique; conservar los mapeos en español ya presentes.
- [ ] 1.6 Reemplazar `housingOptions` en `frontend/src/components/onboarding/steps/AddressStepRenderer.vue` (líneas 81-85, hoy `OWN/RENT/FAMILY/OTHER`) por las 6 opciones canónicas de `HousingType` con sus labels: `OWNED_PAID` "Propia (pagada)", `OWNED_MORTGAGE` "Propia (con hipoteca)", `RENTED` "Rentada", `FAMILY` "Familiar", `BORROWED` "Prestada", `OTHER` "Otro".
- [ ] 1.7 Unificar el `HOUSING_MAP` de `frontend/src/stores/onboarding.ts` (línea ~742, hoy produce `OWNED/RENTED/FAMILY/MORTGAGED/EMPLOYER`) para que `housing_type` persista los valores canónicos; ajustar la anotación de tipo de la línea 481 y el uso en la línea ~1054 (`HOUSING_MAP[...] || 'FAMILY'`) para que ya no aparezcan `MORTGAGED`/`EMPLOYER` como salida.
- [ ] 1.8 Test unitario en `backend/tests` cubriendo `EducationLevel::NONE` (label + normalize `NONE`/`SIN EDUCACION`), `EmploymentType::BUSINESS_OWNER` (label "Comerciante", value intacto, normalize `COMERCIANTE`) y `HousingType::normalize()` con los tokens legacy (`OWNED`, `MORTGAGED`, `EMPLOYER`, `OWN`, español) → canónicos.

## 2. motor-housing-ubicacion (depende de 1)

- [ ] 2.1 En `backend/app/Services/Decision/DecisionInputCollector.php`, dentro de `collect()`, agregar `housing_type` a `inputs.variables` tomándolo de `person.currentHomeAddress` (mismo domicilio del que ya salen `state`/`city`), normalizado con `HousingType::normalize()`; dejar la variable en `null` cuando no haya domicilio o `housing_type` sea nulo.
- [ ] 2.2 En `backend/database/seeders/MoneyCapitalSeeder.php`, agregar a `rules.scoring.variables` dos entradas nuevas — `key: 'housing_type'` y `key: 'state'` — con sus `points` maps arrancando en 0 (matriz sin calibrar), junto a las 5 variables existentes (`salary_range`, `employment_type`, `education_level`, `phone_risk_level`, `online_loans_count`). La entrada `state` usa las claves de estado que ya llegan en `inputs.variables.state`.
- [ ] 2.3 Verificar en `backend/app/Services/Decision/DecisionEngineService.php` que la suma de `housing_type` y `state` funciona por el recorrido genérico de `rules.scoring.variables` (valor recolectado buscado en el points map, ausente/`null`/vacío = 0 puntos) y que cada variable evaluada queda en el detalle del scoring con `key`, `value`, `points`; ajustar solo si el recorrido no fuese realmente genérico.
- [ ] 2.4 Feature test del motor: solicitud con `housing_type = OWNED_PAID` y points map `{ "OWNED_PAID": 10 }` suma +10 y registra el detalle; points maps en 0 no alteran banda/decisión; valor fuera del map aporta 0.

## 3. catalogo-municipios-sepomex

- [ ] 3.1 Agregar un método (p. ej. `municipalities`) a `backend/app/Http/Controllers/Api/V2/Public/PostalCodeController.php` que, dado el estado, devuelva `DISTINCT municipio` de `postal_codes` ordenados alfabéticamente (cacheado como el CP), resolviendo la clave del enum `MexicanState` (`CDMX`, `JAL`, …) contra la columna `estado`/`estado_clave` SEPOMEX/INEGI; estado sin filas → lista vacía (no 500).
- [ ] 3.2 Registrar la ruta pública del endpoint en `backend/routes/api.php` junto a la de `postal-codes/{cp}` (línea ~91), p. ej. `GET /public/postal-codes/municipios/{estado}`.
- [ ] 3.3 Definir el mapeo clave-enum→nomenclatura-catálogo para los 32 estados (resolver `MexicanState` code vs. `estado_clave` INEGI) usado por el endpoint; confirmar con datos reales de `postal_codes` que cada estado empata.
- [ ] 3.4 En `frontend/src/components/onboarding/steps/StateCityStepRenderer.vue` reemplazar el mapa estático `SUGGESTED_CITIES` (líneas 54-60) por la carga de municipios desde el nuevo endpoint al seleccionar estado; el valor emitido `{ state, city }` toma el municipio de la opción del catálogo (sin input de texto libre como vía principal).
- [ ] 3.5 Corregir el bug `CMX`→`CDMX` en `frontend/src/components/onboarding/steps/StateCityStepRenderer.vue:57`, usando la clave canónica `CDMX` de `MexicanState::CIUDAD_DE_MEXICO` en todo el recorrido estado→municipios.
- [ ] 3.6 Registrar en `ImportPostalCodes` (`backend/app/Console/Commands/ImportPostalCodes.php`) una marca de "última importación" persistente (p. ej. cache/tabla de metadatos), ya que `PostalCode::$timestamps = false` no permite medir vigencia por fila.
- [ ] 3.7 Crear un comando programado y agendarlo en `backend/routes/console.php` que alerte cuando `postal_codes` esté vacía o cuando la última importación supere una ventana de vigencia configurable; no alertar si está poblada y vigente. La descarga del archivo oficial (Correos/SEPOMEX) sigue siendo manual.
- [ ] 3.8 Feature test del endpoint de municipios: estado con datos → únicos y ordenados; estado sin datos → lista vacía 200; `CDMX` devuelve demarcaciones (no vacío por `CMX`).

## 4. geolocalizacion-obligatoria

- [ ] 4.1 En `frontend/src/views/mobile/WelcomeConsentView.vue`, convertir `handleContinue()` en un gate bloqueante: si `platform.geolocation.requestPermission()` resuelve `denied`, NO ejecutar `router.replace(.../auth)`; permanecer en la vista (reemplaza el fail-open actual de "Permisos parciales").
- [ ] 4.2 Deshabilitar el control "Continuar" en `WelcomeConsentView.vue` hasta que el permiso de ubicación esté otorgado (además del checkbox de aceptación ya existente).
- [ ] 4.3 Agregar en `WelcomeConsentView.vue` la UI de guía+reintento cuando el permiso quede `denied` (explica cómo habilitarlo en ajustes y reintenta `requestPermission()` sin salir del flujo); manejar `unsupported`/`isSupported()===false` con mensaje claro sin dejar al usuario atorado.
- [ ] 4.4 Asegurar que el gate use la abstracción `platform.geolocation` (`frontend/src/platform/`, `geolocationNative`/`geolocationWeb`) y NO `navigator.geolocation` directo en la vista.
- [ ] 4.5 Confirmar que el botón "Estoy en mi domicilio" de `frontend/src/components/onboarding/steps/AddressStepRenderer.vue` sigue opcional (el paso `address` se valida y avanza sin lat/lng aunque se niegue el permiso ahí).

## 5. reanudar-onboarding

- [ ] 5.1 En `frontend/src/views/applicant/onboarding/DynamicOnboardingView.vue` (`onMounted`, cuando NO hay `stepId` en la URL) calcular el primer paso incompleto recorriendo `steps` (ya filtrada por `condition`) y hacer `router.replace` a ese paso en vez de a `steps[0]`.
- [ ] 5.2 Implementar el criterio de completitud por paso: evaluar la validez canónica del tipo de paso contra el valor persistido leído de `dynamicData`/perfil por su `id`; los pasos `required === false` y de tipo `review`/`review_full` cuentan SIEMPRE como completos; apoyarse en/extender `frontend/src/composables/useOnboardingSteps.ts` si ahí vive la validación.
- [ ] 5.3 Casos borde en `DynamicOnboardingView.vue`: sin ningún dato → `steps[0]`; todo completo → último paso (resumen/envío); NO recalcular cuando la URL ya trae `stepId` (deep link, atrás/adelante, `?returnTo=`); nunca elegir un paso excluido por `condition`.
- [ ] 5.4 Verificar que al saltar adelante los datos previos se conservan prellenados desde `onboarding_draft` + perfil backend (destino con datos parciales arranca cargado; pasos previos no se vacían).

## 6. Investigación — bancos SPEI / acreditación inmediata (bloquea 7)

- [ ] 6.1 Investigar contra fuentes Banxico/SPEI (participantes SPEI y esquema CEP) qué instituciones acreditan transferencias de inmediato, y cruzarlas con las entradas de `MEXICAN_BANKS` en `frontend/src/utils/banks.ts` (matching por `code`/`name`).
- [ ] 6.2 Producir la lista propuesta de `valid_for_transfer` por institución (true/false) con default conservador (no confirmado → `false`, incluida la comodín "Otro" `OTHR`) y entregarla al usuario para validación ANTES de poblar el flag en el catálogo.

## 7. bancos-transferencia-inmediata (depende de 6)

- [ ] 7.1 Extender la interfaz `BankOption` y `MEXICAN_BANKS` en `frontend/src/utils/banks.ts` con el flag booleano obligatorio `valid_for_transfer` en cada entrada (fuente única de verdad); "Otro" (`OTHR`) y cualquier no confirmado quedan en `false`.
- [ ] 7.2 Poblar `valid_for_transfer` por institución con la lista aprobada por el usuario (salida de 6.2); asegurar que `bankFromClabe`/derivaciones de banco respeten el flag.
- [ ] 7.3 En `frontend/src/components/onboarding/steps/BankAccountStepRenderer.vue`, en el sheet de selección deshabilitar o marcar con distintivo "no disponible para recibir tu préstamo" las instituciones con `valid_for_transfer === false`.
- [ ] 7.4 En `BankAccountStepRenderer.vue`, si se selecciona un banco no válido, mostrar aviso explicativo y emitir `update:valid` en `false` (no permitir avanzar); banco válido con cuenta de longitud correcta (CLABE 18 / tarjeta 16) → válido sin aviso.

## 8. contactos-agenda

- [ ] 8.1 Agregar la dependencia `@capacitor/contacts` a `frontend/package.json` (hoy no está entre los plugins de Capacitor) y correr `npx cap sync`.
- [ ] 8.2 Definir la interfaz `PlatformContacts` en `frontend/src/platform/types.ts` y registrarla en `frontend/src/platform/index.ts` con adapters `native` y `web` (siguiendo el patrón de `geolocation`).
- [ ] 8.3 Implementar `frontend/src/platform/native/contacts.native.ts` (picker vía `@capacitor/contacts`, devuelve nombre+teléfono) y `frontend/src/platform/web/contacts.web.ts` como no-op (reporta no soportado, devuelve resultado nulo sin lanzar).
- [ ] 8.4 En `frontend/src/components/onboarding/steps/ReferencesStepRenderer.vue` agregar, solo cuando `platform.device.isNative()`, el botón "Elegir de mis contactos" en cada sección (familiar y personal) que abra `platform.contacts` y prellene nombre + teléfono (teléfono normalizado a 10 dígitos); los campos siguen editables y sujetos a las validaciones del paso.
- [ ] 8.5 En web (`isNative() === false`) NO mostrar el botón: conservar solo la captura manual actual (fallback), sin depender de la agenda.
- [ ] 8.6 Manejar denegación/cancelación en `ReferencesStepRenderer.vue`: si niega el permiso o cierra el picker sin elegir, no romper ni modificar campos; permanecer en captura manual.
- [ ] 8.7 Declarar permisos nativos: `NSContactsUsageDescription` en `frontend/ios/App/App/Info.plist` (iOS) y `android.permission.READ_CONTACTS` en `frontend/android/app/src/main/AndroidManifest.xml` (Android).

## 9. Verificación final

- [ ] 9.1 Backend: `cd backend && php artisan test` (enums, motor de decisión, endpoint de municipios, schedule de vigencia) en verde.
- [ ] 9.2 Frontend: `cd frontend && npm run type-check` (vue-tsc) sin errores, con especial atención a los tipos de `housing_type` (canónicos) y la interfaz `BankOption`/`PlatformContacts`.
- [ ] 9.3 Frontend: `cd frontend && npm run lint` (y `npm run format` si aplica) sin errores.
- [ ] 9.4 Re-seed idempotente: correr `MoneyCapitalSeeder` y confirmar que la política gana `housing_type`/`state` con points en 0 sin sobrescribir ediciones del admin ni alterar decisiones previas.
- [ ] 9.5 Smoke del onboarding MoneyCapital (skill `ui-smoke`, stack desechable): geolocalización bloqueante, catálogo de municipios (incl. CDMX no vacío), reanudar en primer paso incompleto, y paso de banco que bloquea instituciones no válidas.
