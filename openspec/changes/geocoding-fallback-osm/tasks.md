## 1. Backend — GeocodingService (cadena + Nominatim + caché)

- [x] 1.1 Agregar rama Nominatim a `GeocodingService`: método privado que consulta `{url_base}/reverse?format=jsonv2&addressdetails=1&lat=&lon=&accept-language=es` con `User-Agent` identificable; timeout corto; devuelve el sobre estandarizado o `null`
- [x] 1.2 Resolver `url_base`/`api_key` de Nominatim desde `TenantApiConfig` (provider `nominatim`, `service_type` `geocoding`, activo); default al endpoint público si no hay config
- [x] 1.3 Orquestar la cadena en `reverseGeocode`: Google (si config) → Nominatim (si no hay Google **o** Google devolvió `null`) → `coords_only`; nuevo `source = osm`
- [x] 1.4 Mapeo Nominatim `address` → esquema MX (`road→street`, `house_number→ext_number`, `neighbourhood|suburb|quarter→neighborhood`, `city|town|village→city`, `county|city_district|municipality→municipality`, `state→state`, `postcode→postal_code`)
- [x] 1.5 Cachear el resultado con `Cache::remember` por `lat`/`lng` redondeados a 3 decimales, TTL ~30 días (patrón de los services Nubarium)

## 2. Backend — catálogo de integraciones y validación

- [x] 2.1 Sumar `geocoding` al catálogo de `service_type` y los providers `google_maps` y `nominatim` que expone el gestor de integraciones (fuente del `sortedProviders`/service types)
- [x] 2.2 Validar en `StaffConfigController@saveApiConfig`: `provider` permitido para `service_type = geocoding`; para `nominatim`, `url_base` debe ser URL http(s) válida cuando venga; `api_key` opcional
- [x] 2.3 Confirmar que la ruta usada es `/v2/staff/config/api-configs` (permiso `canManageProducts`) y el scoping por tenant es automático (HasTenant)

## 3. Frontend — autollenado con cualquier proveedor

- [x] 3.1 `geo.service.ts`: ampliar `source` a `'google' | 'osm' | 'coords_only'` y los campos opcionales del resultado
- [x] 3.2 `AddressStepRenderer.vue`: autollenar si la respuesta trae campos (quitar el gate `source === 'google'`), conservando el disparo de colonias por CP; ajustar el mensaje de estado (`autollenado` vs `ingresa tu CP`)

## 4. Frontend — UI de config de geocoding

- [x] 4.1 `IntegrationsManager.vue`: registrar el provider `geocoding` (nominatim/google_maps) en la selección de provider + service type
- [x] 4.2 Campos específicos de `nominatim` en el modal (`url_base`, `api_key` opcional) y tipos en `config.staff.service.ts`
- [x] 4.3 Verificar visibilidad por rol: el módulo/gestor ya es ADMIN (`canManageProducts`); no requiere override

## 5. Tests

- [x] 5.1 Backend (`GeocodingService`, con `Http::fake`): Google OK → `google`; sin Google → `osm`; Google falla → `osm`; todos fallan/sin proveedor → `coords_only`
- [x] 5.2 Backend: usa `url_base` del tenant cuando existe vs público cuando no; `User-Agent` presente en la petición; caché sirve el segundo hit sin volver a llamar; aislamiento entre tenants
- [x] 5.3 Backend (config): `canManageProducts` requerido (ANALYST → 403); `url_base` inválida → 422; provider fuera del catálogo → 422; aislamiento por tenant
- [x] 5.4 Frontend: type-check de `geo.service.ts`/`AddressStepRenderer.vue`; (opcional) test de que `source = osm` con campos autollena el formulario

## 6. Verificación integral

- [x] 6.1 `php artisan test` de los grupos nuevos + `vue-tsc --noEmit` + `npm run lint`
- [x] 6.2 Smoke en el onboarding local (`:5175`): dar clic a "Estoy en mi domicilio" con el Nominatim público por default y confirmar que autollena calle/colonia/estado/CP; probar también un tenant con `url_base` configurada
