## Context

El botón "Estoy en mi domicilio" ([AddressStepRenderer.vue](../../../frontend/src/components/onboarding/steps/AddressStepRenderer.vue))
geolocaliza el dispositivo y llama a `POST /v2/applicant/geo/reverse`
([GeoController](../../../backend/app/Http/Controllers/Api/V2/Applicant/GeoController.php)),
que delega en `GeocodingService`. Hoy el servicio:

- Busca un `TenantApiConfig` con `provider = google_maps`, `service_type = geocoding`,
  activo y con `api_key`. Si existe → llama a la Geocoding API de Google y devuelve
  `source = google` con los campos. Si no → devuelve `source = coords_only`.
- El frontend **solo autollena si `source === 'google'`** (línea 160); en cualquier
  otro caso captura coordenadas pero deja los campos vacíos.

**Ningún tenant** (`dbg`, `demo`, `finatea`, `moneycapital`) tiene el
`TenantApiConfig` de `google_maps/geocoding`, así que el autollenado nunca ocurre.
Ya existe un gestor de integraciones genérico y data-driven
([IntegrationsManager.vue](../../../frontend/src/modules/admin/components/IntegrationsManager.vue))
con CRUD backend `/api-configs` (`StaffConfigController`), que hoy maneja
`twilio`, `nubarium`, `smtp` y proveedores de email por `provider` + `service_type`.

## Goals / Non-Goals

**Goals:**
- Que el botón autollene la dirección **sin API key de pago**, vía un fallback de
  reverse-geocoding con OpenStreetMap/Nominatim.
- Endpoint de Nominatim **configurable por tenant** (default público), reutilizando
  `TenantApiConfig`.
- Que el ADMIN autogestione el proveedor de `geocoding` desde el gestor de
  integraciones **existente**.
- Degradación limpia y aislamiento por tenant.

**Non-Goals:**
- Self-hosting de Nominatim (solo URL configurable).
- Cambiar la geolocalización del cliente (navegador/Capacitor).
- Forward-geocoding / autocompletado.

## Decisions

| # | Decisión | Elección | Alternativas descartadas |
|---|----------|----------|--------------------------|
| 1 | Endpoint OSM | **Nominatim configurable** vía `TenantApiConfig` (provider `nominatim`, `url_base`+`api_key` opcionales), **default al público** | Nominatim público fijo/global (encierra, riesgo de bloqueo); Photon (reverse MX más pobre) |
| 2 | Cadena de resolución | **Google → Nominatim → `coords_only`**; Nominatim entra si **no hay Google O Google falla** | OSM solo cuando no hay Google (deja `coords_only` en fallos de Google) |
| 3 | UI de config | **Extender el gestor de integraciones existente** con servicio `geocoding` (providers `google_maps`, `nominatim`) | Pantalla nueva dedicada (duplica CRUD ya existente) |
| 4 | Caché | **`Cache::remember` por lat/lng redondeado a 3 decimales**, TTL ~30 días | Sin caché (repite llamadas, roza el límite de Nominatim) |
| 5 | `source` en la respuesta | Generalizar a **`google | osm | coords_only`**; el frontend **autollena si vienen campos**, sin importar el proveedor | Mantener `source === 'google'` como única condición (bloquea OSM) |

**Detalles derivados (no decisiones abiertas):**
- **`User-Agent` identificable** en cada request a Nominatim (`LendusFind/1.0
  (+contacto)`) — requisito de su política de uso.
- **Mapeo Nominatim → esquema MX** (respuesta `address` con `addressdetails=1`):
  `road → street`, `house_number → ext_number`, `neighbourhood|suburb|quarter →
  neighborhood`, `city|town|village → city`, `county|city_district|municipality →
  municipality`, `state → state`, `postcode → postal_code`. El CP recuperado sigue
  disparando el autollenado de colonias por SEPOMEX en el frontend.
- **Degradación**: si Nominatim falla/timeouta o no hay resultado → `coords_only`
  (comportamiento actual), nunca error al usuario.
- **`GeocodingService` orquesta la cadena** en un solo lugar; cada proveedor es un
  método privado que devuelve el sobre estandarizado o `null`.

## Risks / Trade-offs

- [Nominatim público limita 1 req/s y bloquea uso comercial masivo] → caché por
  coordenada + llamada on-demand (un clic por onboarding) + `User-Agent` con
  contacto; endpoint configurable para migrar a self-host/LocationIQ sin deploy.
- [Calidad de reverse-geocoding en México inferior a Google, sobre todo colonia] →
  el fallback SEPOMEX por CP ya completa la colonia; los campos que falten quedan
  editables. Google mantiene prioridad donde esté configurado.
- [Enviar coordenadas a un tercero (OSM público)] → solo lat/lng (sin PII); el
  redondeo en caché evita persistir la ubicación exacta; equivalente a lo que ya
  hace Google.
- [Un `url_base` mal configurado por el ADMIN rompe el geocoding del tenant] →
  validación del formato en `saveApiConfig`; ante fallo, degradación a
  `coords_only`; el proveedor es desactivable desde la UI.

## Migration Plan

1. Cambios aditivos: el catálogo de providers/service_types suma `geocoding`
   (`nominatim`, `google_maps`); `GeocodingService` gana la rama Nominatim + caché.
   Sin migración de datos.
2. Deploy sin ninguna config: el fallback usa el **Nominatim público por default**
   → el autollenado empieza a funcionar de inmediato para todos los tenants sin
   Google. Los tenants con Google conservan su comportamiento.
3. Un ADMIN puede, opcionalmente, dar de alta un `TenantApiConfig` de `geocoding`
   para apuntar a otro endpoint (self-host/LocationIQ) o desactivar el fallback.
4. Rollback: desactivar el proveedor `nominatim` del tenant (o feature-flag global)
   → vuelve a `coords_only`, sin deploy.

## Open Questions

- Ninguna bloqueante. El `User-Agent`/contacto y el TTL exacto de caché se fijan en
  implementación con los valores recomendados (30 días, contacto de soporte del
  tenant o genérico de LendusFind).
