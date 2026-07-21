# perfil-movil

## Purpose

El canal móvil expone "Mi Perfil" desde el nav inferior reutilizando la vista `ProfileView` existente (sin crear una vista móvil nueva), con navegación adaptada al contexto móvil, paridad funcional total con la web (datos, foto de perfil, cuentas bancarias) y coherencia del ingreso mostrado con lo que el onboarding realmente capturó.

## Requirements

### Requirement: Acceso a Mi Perfil desde el nav inferior móvil

El nav inferior móvil (`MobileBottomNav`) SHALL exponer un tab "Perfil" que navega a la
ruta `m-profile`. El tab reemplaza al placeholder "Mi cuenta" y su ícono de persona se
conserva. El tab SHALL estar disponible para todos los tenants que usan el canal móvil,
sin condicionar por tenant o slug.

#### Scenario: Tap en el tab Perfil
- **WHEN** el usuario autenticado toca el tab "Perfil" del nav inferior
- **THEN** la app navega a `/m/perfil` y el tab "Perfil" queda resaltado como activo

#### Scenario: Tab visible en cualquier tenant móvil
- **WHEN** se renderiza el `MobileBottomNav` en cualquier tenant del canal móvil
- **THEN** el tercer tab muestra la etiqueta "Perfil" (no "Mi cuenta") y navega al perfil

### Requirement: Ruta móvil de perfil reutiliza ProfileView

El router SHALL registrar la ruta `/m/perfil` con nombre `m-profile`, marcada
`requiresAuth` y `mobileEntry`, apuntando a la vista `ProfileView` existente. NO se crea una
vista de perfil móvil nueva. El nombre `m-profile` SHALL agregarse a `KNOWN_ROUTES` del
`MobileBottomNav` para que el tab quede activo.

#### Scenario: Acceso directo a la ruta
- **WHEN** un usuario autenticado abre `/m/perfil`
- **THEN** se monta `ProfileView` mostrando sus datos de perfil

#### Scenario: Acceso sin autenticación
- **WHEN** un usuario no autenticado intenta abrir `/m/perfil`
- **THEN** el guard de ruta lo redirige al flujo de autenticación (por `requiresAuth`)

### Requirement: Navegación del perfil adaptada al contexto móvil

Cuando `ProfileView` se monta bajo una ruta móvil (`/m/…` o `meta.mobileEntry`), SHALL
adaptar su navegación: el botón "Volver" regresa a `/m/home`, el logout regresa a `/m`, y
el `MobileBottomNav` permanece visible con el padding inferior necesario para no tapar
contenido. En contexto web (`/perfil`, `/:tenant/perfil`) el comportamiento actual SHALL
permanecer sin cambios.

#### Scenario: Volver desde el perfil móvil
- **WHEN** el usuario toca "Volver" estando en `/m/perfil`
- **THEN** la app navega a `/m/home`

#### Scenario: Logout desde el perfil móvil
- **WHEN** el usuario toca "Salir" estando en `/m/perfil`
- **THEN** la sesión se cierra y la app regresa a `/m`

#### Scenario: Perfil web intacto
- **WHEN** el usuario abre `/perfil` (contexto web)
- **THEN** "Volver" sigue yendo a `/dashboard` y no se muestra el nav inferior móvil

### Requirement: Paridad funcional total en móvil

El perfil móvil SHALL ofrecer la misma funcionalidad que el perfil web: consulta de datos
(personales, dirección, empleo, referencias si el flujo las capturó), foto de perfil
(ver y subir mientras no esté verificada) y gestión de cuentas bancarias (agregar,
eliminar, marcar principal), reutilizando los componentes existentes.

#### Scenario: Gestión de cuentas bancarias en móvil
- **WHEN** el usuario agrega, elimina o marca como principal una cuenta bancaria desde
  `/m/perfil`
- **THEN** la operación se persiste y la lista de cuentas se refleja actualizada, igual que
  en web

### Requirement: Coherencia del ingreso mostrado con el onboarding

Como `ProfileView` es compartido por todos los tenants y algunos flujos capturan un rango
salarial (`salary_range`) que se persiste como el midpoint en `monthly_income`, el perfil
SHALL mostrar el rango salarial reconstruido (espejando `SalaryRange::fromIncome`) junto al
monto, para no presentar como preciso un número que el solicitante nunca tecleó. Las filas
de datos que el onboarding no captura (p. ej. "Antigüedad" cuando `seniority_months` es
nulo) NO SHALL renderizarse vacías. Este comportamiento aplica igual en web y en móvil, sin
condicionar por tenant.

#### Scenario: Ingreso con rango reconstruido
- **WHEN** se muestra el perfil de un solicitante con `monthly_income = 7500`
- **THEN** la fila de ingreso muestra el monto (`$7,500`) y debajo el rango `$6,001 - $9,000`

#### Scenario: Antigüedad no capturada no se muestra
- **WHEN** el solicitante no tiene antigüedad registrada (el endpoint la colapsa a `0`, no
  `null`, porque MC/Demo no capturan el step)
- **THEN** la fila "Antigüedad" no se renderiza (no aparece "0 meses" fabricado ni un guion)
