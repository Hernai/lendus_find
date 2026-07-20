# catalogo-municipios-sepomex

## Purpose

Catálogo estado→municipios servido desde el catálogo SEPOMEX (postal_codes), con import periódico y aviso de vigencia.

# catalogo-municipios-sepomex

## Requirements

### Requirement: Endpoint de municipios por estado desde SEPOMEX
La API DEBE (MUST) exponer un endpoint público que, dado un identificador de estado,
devuelva la lista de municipios de ese estado tomada de la tabla de referencia
`postal_codes` (catálogo SEPOMEX oficial, poblado por `postal-codes:import`). La lista
DEBE (MUST) ser de municipios **distintos** (sin duplicados por la fila-por-colonia de
`postal_codes`) y ordenada alfabéticamente. El endpoint DEBE (MUST) resolver la clave de
estado que envía el frontend (código del enum `MexicanState`, p. ej. `CDMX`, `JAL`) contra
el catálogo, cuyas columnas `estado`/`estado_clave` usan la nomenclatura SEPOMEX/INEGI y
no coinciden textualmente con el código del enum.

#### Scenario: Estado con datos devuelve municipios únicos y ordenados
- **WHEN** se consulta el endpoint con un estado que tiene filas en `postal_codes` (p. ej. `JAL`)
- **THEN** la respuesta lista los municipios de ese estado, sin duplicados y ordenados alfabéticamente

#### Scenario: Estado sin datos en el catálogo
- **WHEN** se consulta un estado que no tiene filas en `postal_codes` (catálogo vacío o estado inexistente)
- **THEN** la respuesta DEBE (MUST) ser una lista vacía (no un error 500), para que el paso degrade con seguridad

### Requirement: El paso state_city elige municipio del catálogo, sin texto libre
El paso `state_city` (`StateCityStepRenderer.vue`) DEBE (MUST) poblar las opciones de
municipio desde el endpoint de municipios por estado y NO DEBE (MUST NOT) seguir dependiendo
del mapa estático `SUGGESTED_CITIES` (hoy en `StateCityStepRenderer.vue:54-60`, solo 5
estados) como fuente principal, ni ofrecer un input de texto libre como vía principal de
captura del municipio. Al seleccionar un estado, el paso DEBE (MUST) solicitar sus municipios
y presentarlos para selección; el valor emitido (`{ state, city }`) DEBE (MUST) tomar el
municipio de la opción elegida del catálogo.

#### Scenario: Seleccionar estado carga sus municipios del catálogo
- **WHEN** el usuario elige un estado en el paso `state_city`
- **THEN** el paso consulta el endpoint y muestra los municipios de ese estado provenientes de `postal_codes`, no la lista hardcodeada de 5 estados

#### Scenario: Elegir municipio del catálogo comprometido como valor
- **WHEN** el usuario selecciona un municipio de la lista servida por el endpoint
- **THEN** el paso emite `{ state, city }` con ese municipio y marca el paso como válido, sin requerir escritura libre

### Requirement: Clave de estado consistente para Ciudad de México (fix CMX≠CDMX)
El catálogo, el enum y el paso DEBEN (MUST) usar la **misma** clave canónica para cada
estado. En particular, la Ciudad de México DEBE (MUST) resolverse con la clave canónica
`CDMX` (valor de `MexicanState::CIUDAD_DE_MEXICO` en `MexicanState.php:24`) en todo el
recorrido estado→municipios; la clave errónea `CMX` (hoy en `StateCityStepRenderer.vue:57`)
NO DEBE (MUST NOT) usarse, pues nunca empata con lo que emite el selector de estado y deja
CDMX sin municipios.

#### Scenario: CDMX devuelve sus municipios y no queda vacío
- **WHEN** el usuario selecciona Ciudad de México (clave emitida `CDMX`) en el paso `state_city`
- **THEN** el endpoint resuelve `CDMX` contra el catálogo y devuelve los municipios/demarcaciones de la CDMX (lista no vacía), en lugar de no encontrar coincidencia por la clave `CMX`

### Requirement: Schedule que avisa si el catálogo está vacío o desactualizado
Un comando programado (registrado en `routes/console.php`) DEBE (MUST) verificar
periódicamente el estado de la tabla `postal_codes` y **emitir una alerta** cuando el
catálogo esté vacío o cuando su última importación exceda una ventana de vigencia
configurable, para disparar la re-importación manual (`postal-codes:import`, la descarga
del archivo oficial de Correos/SEPOMEX sigue siendo manual). Como `postal_codes` no tiene
timestamps (`PostalCode::$timestamps = false`), la vigencia DEBE (MUST) medirse contra una
marca de última importación que el comando de import registre.

#### Scenario: Catálogo vacío dispara alerta
- **WHEN** el schedule corre y `postal_codes` no tiene filas
- **THEN** el comando emite una alerta indicando que el catálogo de códigos postales está vacío y requiere importación

#### Scenario: Import viejo dispara alerta
- **WHEN** el schedule corre y la última importación registrada supera la ventana de vigencia configurada
- **THEN** el comando emite una alerta indicando que el catálogo está desactualizado y debe re-importarse

#### Scenario: Catálogo poblado y vigente no alerta
- **WHEN** el schedule corre con `postal_codes` poblada y una importación dentro de la ventana de vigencia
- **THEN** el comando termina sin emitir ninguna alerta
