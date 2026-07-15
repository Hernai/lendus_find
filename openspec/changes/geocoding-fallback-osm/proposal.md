## Why

El botón "Estoy en mi domicilio" del onboarding solo autollena la dirección si
el tenant tiene Google Maps configurado. Hoy **ningún tenant** lo tiene, así que
el botón captura las coordenadas pero deja los campos vacíos y se percibe como
roto ("no funciona ni me llena todo"). Un fallback gratuito de reverse-geocoding
con OpenStreetMap/Nominatim hace que el autollenado funcione **sin requerir una
API key de pago**.

## What Changes

- Nuevo **fallback de reverse-geocoding con Nominatim** cuando el tenant no tiene
  Google configurado **o** Google falla (timeout / sin resultado / cuota).
  Cadena de resolución: **Google → Nominatim → `coords_only`**.
- El endpoint de Nominatim es **configurable por tenant** (`TenantApiConfig`
  provider `nominatim`, con `url_base` y `api_key` opcionales); por defecto usa el
  **endpoint público** (sin key), de modo que funciona out-of-the-box y un tenant
  con volumen puede apuntar a un Nominatim self-hosted o a LocationIQ sin tocar
  código.
- **Caché** de resultados por coordenada redondeada (~3 decimales ≈ 100 m, TTL
  ~30 días): cumple la política de uso de Nominatim, acelera reintentos y no
  guarda la ubicación exacta por usuario.
- El **gestor de integraciones** del admin gana el servicio `geocoding` con los
  proveedores `google_maps` y `nominatim`, para que el ADMIN autogestione el
  proveedor desde la UI existente (sin tinker).
- El frontend **autollena con cualquier proveedor** que devuelva campos (no solo
  Google): se generaliza el `source` de la respuesta a `google | osm | coords_only`.

## Capabilities

### New Capabilities
- `geocoding-fallback`: reverse-geocoding del onboarding con cadena de proveedores
  (Google → Nominatim → coords_only), endpoint de Nominatim configurable por
  tenant, caché por coordenada y autollenado del domicilio con cualquier proveedor
  que devuelva campos.
- `geocoding-config-admin`: gestión del proveedor de geocoding por el ADMIN del
  tenant desde el gestor de integraciones (alta/edición/activación del
  `TenantApiConfig` de `geocoding`), con aislamiento por tenant.

### Modified Capabilities
<!-- Ninguna: no existen specs previas de geocoding ni de integraciones. -->

## Impact

- **Backend**: `GeocodingService` (nueva rama Nominatim + caché + degradación),
  `TenantApiConfig` (provider `nominatim`, `service_type` `geocoding`), catálogo de
  proveedores/servicios del gestor de integraciones y validación en
  `StaffConfigController@saveApiConfig` (`/api-configs`).
- **Frontend**: `AddressStepRenderer.vue` (autollenado generalizado + mensaje de
  estado), `geo.service.ts` (tipo `source`), `IntegrationsManager.vue` (proveedor
  `geocoding`).
- **Dependencia externa nueva**: endpoint público de Nominatim, con `User-Agent`
  identificable (requisito de su política); el límite de 1 req/s se mitiga con la
  caché y por ser una llamada on-demand (un clic por onboarding).
- **Sin migración de datos ni backfill** (feature aditivo; `TenantApiConfig` ya
  existe). El comportamiento actual (Google, o `coords_only` si no hay proveedor)
  se conserva.

## Non-goals

- **No** self-hosting de Nominatim (solo se soporta una URL base configurable).
- **No** cambia la geolocalización del lado cliente (navegador / plugin Capacitor);
  el fallback es server-side.
- **No** reemplaza a Google donde ya esté configurado: Google mantiene la prioridad.
- **No** agrega forward-geocoding ni autocompletado de direcciones (solo reverse).
