# onboarding-catalogos

## Purpose

Catálogos de onboarding: "Sin educación" (EducationLevel.NONE), label "Comerciante" para BUSINESS_OWNER, y HousingType unificado al enum backend canónico.

## Requirements

### Requirement: EducationLevel incluye "Sin educación" (NONE)

El enum `EducationLevel` (`backend/app/Enums/EducationLevel.php`) DEBE (MUST) exponer un caso canónico `NONE = 'NONE'` con label "Sin educación", cubierto tanto en `label()` como en `normalize()`, de modo que el catálogo de escolaridad del onboarding ofrezca la opción sin dejar huecos en el resto de los niveles existentes (PRIMARY, SECONDARY, HIGH_SCHOOL, TECHNICAL, BACHELOR, MASTER, DOCTORATE).

#### Scenario: NONE aparece en el catálogo de escolaridad
- **WHEN** el frontend consulta las opciones de `EducationLevel` (vía `toOptions()`/`toLabels()`)
- **THEN** la lista incluye el valor `NONE` con label "Sin educación" junto a los siete niveles preexistentes, sin duplicados ni valores faltantes

#### Scenario: label() resuelve NONE
- **WHEN** se llama `EducationLevel::NONE->label()`
- **THEN** devuelve exactamente "Sin educación" y no lanza excepción por caso no cubierto en el `match`

#### Scenario: normalize() reconoce el valor canónico y legacy
- **WHEN** se llama `EducationLevel::normalize('NONE')` o `EducationLevel::normalize('SIN EDUCACION')`
- **THEN** ambos devuelven `EducationLevel::NONE`

### Requirement: EmploymentType relabela BUSINESS_OWNER a "Comerciante" sin cambiar el value

El enum `EmploymentType` (`backend/app/Enums/EmploymentType.php`) DEBE (MUST) mostrar el label "Comerciante" para el caso `BUSINESS_OWNER`, conservando intacto el value backing `'BUSINESS_OWNER'` para no romper datos ni scoring existentes, y `normalize()` DEBE (MUST) mapear también la cadena legacy "COMERCIANTE" a `BUSINESS_OWNER`.

#### Scenario: label() devuelve "Comerciante"
- **WHEN** se llama `EmploymentType::BUSINESS_OWNER->label()`
- **THEN** devuelve "Comerciante" (ya no "Empresario")

#### Scenario: El value backing no cambia
- **WHEN** se inspecciona `EmploymentType::BUSINESS_OWNER->value`
- **THEN** sigue siendo `'BUSINESS_OWNER'`, de modo que los registros y reglas de scoring existentes que referencian ese value continúan resolviéndose

#### Scenario: normalize() acepta "COMERCIANTE"
- **WHEN** se llama `EmploymentType::normalize('COMERCIANTE')`
- **THEN** devuelve `EmploymentType::BUSINESS_OWNER`

### Requirement: HousingType unificado al enum backend canónico

El catálogo de tipo de vivienda del onboarding DEBE (MUST) emitir y persistir únicamente los valores canónicos del enum `HousingType` (`backend/app/Enums/HousingType.php`): `OWNED_PAID`, `OWNED_MORTGAGE`, `RENTED`, `FAMILY`, `BORROWED`, `OTHER`. Los tres sets inconsistentes actuales —el enum backend canónico, las opciones `OWN/RENT/FAMILY/OTHER` de `AddressStepRenderer.vue` (líneas 81-85) y el `HOUSING_MAP` del store que produce `OWNED/RENTED/FAMILY/MORTGAGED/EMPLOYER` (`frontend/src/stores/onboarding.ts:742`)— DEBEN (MUST) converger a los valores canónicos, y la lectura de valores legacy DEBE (MUST) mapearse vía `HousingType::normalize()` sin perder registros previos.

#### Scenario: El paso de vivienda ofrece las opciones canónicas
- **WHEN** el cliente llega al paso de domicilio/vivienda del onboarding
- **THEN** las opciones seleccionables corresponden a los casos canónicos de `HousingType` (Propia pagada, Propia con hipoteca, Rentada, Familiar, Prestada, Otro) y ya no a `OWN/RENT/FAMILY/OTHER`

#### Scenario: La selección persiste con el value canónico
- **WHEN** el cliente elige una opción de vivienda y el store persiste `housing_type`
- **THEN** el valor enviado al backend es uno de `OWNED_PAID/OWNED_MORTGAGE/RENTED/FAMILY/BORROWED/OTHER`, sin producir los valores intermedios `MORTGAGED` ni `EMPLOYER` del `HOUSING_MAP` anterior

#### Scenario: Los valores legacy se leen sin romperse
- **WHEN** se lee un `Address.housing_type` con un valor legacy (por ejemplo `OWNED`, `MORTGAGED`, `EMPLOYER` o un valor en español)
- **THEN** `HousingType::normalize()` lo mapea a un caso canónico (o `null` si no aplica) sin lanzar excepción, de modo que los registros existentes siguen mostrándose
