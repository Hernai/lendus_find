## ADDED Requirements

### Requirement: El ADMIN gestiona el proveedor de geocoding

El gestor de integraciones SHALL exponer el servicio `geocoding` con los
proveedores `google_maps` y `nominatim`, de modo que un usuario con permiso
`canManageProducts` (ADMIN del tenant) pueda dar de alta, editar, activar y
desactivar la configuración de geocoding de su propio tenant a través de
`/v2/staff/config/api-configs`. Para `nominatim`, el formulario SHALL aceptar una
`url_base` y una `api_key` opcional.

#### Scenario: ADMIN da de alta Nominatim self-hosted
- **WHEN** un ADMIN guarda una integración `geocoding` con provider `nominatim` y una `url_base`
- **THEN** el sistema persiste el `TenantApiConfig` del tenant, activo, y queda disponible para el reverse-geocoding

#### Scenario: ADMIN desactiva el proveedor
- **WHEN** un ADMIN desactiva la integración de `geocoding` de su tenant
- **THEN** el reverse-geocoding deja de usar ese proveedor y cae al siguiente de la cadena

### Requirement: Permiso y aislamiento por tenant de la config de geocoding

La gestión de la configuración de geocoding SHALL requerir permiso
`canManageProducts` y SHALL estar scopeada al tenant del usuario autenticado. Un
rol sin ese permiso SHALL recibir 403, y un ADMIN SHALL NO poder ver ni editar la
configuración de otro tenant desde esta ruta.

#### Scenario: Rol sin permiso es rechazado
- **WHEN** un ANALYST o SUPERVISOR intenta guardar una config de `geocoding`
- **THEN** la API responde 403

#### Scenario: Aislamiento entre tenants
- **WHEN** un ADMIN del tenant A lista o guarda integraciones
- **THEN** solo ve y modifica configuraciones de geocoding del tenant A

### Requirement: Validación de la configuración de geocoding

El sistema SHALL validar la configuración de geocoding antes de persistirla: el
`provider` SHALL pertenecer al catálogo permitido (`google_maps`, `nominatim`) para
`service_type = geocoding`, y para `nominatim` la `url_base` SHALL ser una URL
http(s) válida cuando se proporcione.

#### Scenario: url_base inválida
- **WHEN** un ADMIN guarda `nominatim/geocoding` con una `url_base` que no es una URL válida
- **THEN** la API responde con error de validación y no persiste la config

#### Scenario: Provider fuera del catálogo
- **WHEN** se envía un `provider` no permitido para `service_type = geocoding`
- **THEN** la API responde con error de validación
