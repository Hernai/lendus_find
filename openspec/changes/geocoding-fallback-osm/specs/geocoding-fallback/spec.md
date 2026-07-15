## ADDED Requirements

### Requirement: Cadena de proveedores de reverse-geocoding

El sistema SHALL resolver la geocodificación inversa (lat/lng → dirección) en el
orden **Google → Nominatim → coords_only**, para el tenant del solicitante
autenticado. Nominatim SHALL usarse cuando el tenant no tiene Google configurado
**o** cuando Google falla (timeout, error o sin resultado). Si ningún proveedor
devuelve una dirección, el sistema SHALL responder con `source = coords_only` sin
error para el usuario.

#### Scenario: Google configurado y responde
- **WHEN** el tenant tiene un `TenantApiConfig` `google_maps/geocoding` activo con `api_key` y Google devuelve una dirección
- **THEN** la respuesta tiene `source = google` con los campos de dirección poblados

#### Scenario: Sin Google, cae a Nominatim
- **WHEN** el tenant no tiene Google configurado y Nominatim devuelve una dirección
- **THEN** la respuesta tiene `source = osm` con los campos de dirección poblados

#### Scenario: Google configurado pero falla
- **WHEN** el tenant tiene Google configurado pero la llamada a Google falla o no devuelve resultado, y Nominatim sí responde
- **THEN** la respuesta tiene `source = osm` (Nominatim actúa como respaldo de Google)

#### Scenario: Ningún proveedor resuelve
- **WHEN** no hay proveedor disponible o todos fallan
- **THEN** la respuesta tiene `source = coords_only` con solo `lat` y `lng`, y HTTP 200

### Requirement: Endpoint de Nominatim configurable por tenant con default público

El sistema SHALL usar el endpoint público de Nominatim por defecto (sin API key).
Si el tenant tiene un `TenantApiConfig` con `provider = nominatim` y
`service_type = geocoding` activo, el sistema SHALL usar su `url_base` (y `api_key`
si está presente, p. ej. para LocationIQ) en lugar del público.

#### Scenario: Tenant sin config de Nominatim usa el público
- **WHEN** el tenant no tiene `TenantApiConfig` de `nominatim/geocoding`
- **THEN** el sistema consulta el endpoint público de Nominatim

#### Scenario: Tenant con url_base propia
- **WHEN** el tenant tiene un `TenantApiConfig` `nominatim/geocoding` activo con `url_base`
- **THEN** el sistema consulta esa `url_base` (self-host o LocationIQ) en lugar del público

#### Scenario: Cumplimiento de la política de Nominatim
- **WHEN** el sistema hace una petición a un endpoint de Nominatim
- **THEN** incluye una cabecera `User-Agent` identificable con un contacto

### Requirement: Caché de resultados de geocoding por coordenada

El sistema SHALL cachear el resultado de reverse-geocoding por coordenada
redondeada (a 3 decimales) con un TTL de al menos 24 horas, para cumplir la
política de uso de Nominatim y no persistir la ubicación exacta del usuario.

#### Scenario: Segunda consulta de la misma zona sirve de caché
- **WHEN** se consulta reverse-geocoding para una coordenada cuya versión redondeada ya está en caché
- **THEN** el sistema devuelve el resultado cacheado sin llamar al proveedor externo

### Requirement: Autollenado del domicilio con cualquier proveedor

El frontend del onboarding SHALL autollenar los campos de domicilio disponibles
(estado, municipio, ciudad, colonia, calle, número exterior, código postal) cuando
la respuesta trae campos, **independientemente del proveedor** (`google` u `osm`).
Cuando `source = coords_only`, SHALL conservar las coordenadas y guiar al usuario a
ingresar el código postal para autollenar la colonia vía SEPOMEX.

#### Scenario: Respuesta de OSM autollena los campos
- **WHEN** la respuesta tiene `source = osm` con campos de dirección
- **THEN** el formulario de domicilio se autollena con esos campos y el CP dispara el autollenado de colonias

#### Scenario: Solo coordenadas
- **WHEN** la respuesta tiene `source = coords_only`
- **THEN** el formulario no se autollena y muestra el mensaje para ingresar el CP

### Requirement: Aislamiento por tenant

El sistema SHALL resolver el proveedor y la configuración de geocoding con base en
el tenant del solicitante autenticado; la configuración de un tenant SHALL NO
afectar la resolución de otro.

#### Scenario: Config de un tenant no afecta a otro
- **WHEN** el tenant A tiene Nominatim self-hosted y el tenant B no tiene config
- **THEN** las solicitudes del tenant A usan su `url_base` y las del tenant B usan el público, sin cruzarse
