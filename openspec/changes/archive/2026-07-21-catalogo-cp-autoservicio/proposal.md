## Why

Hoy el catálogo de códigos postales (SEPOMEX, tabla global `postal_codes` de ~145 mil
filas que comparten todos los tenants) solo se puede poblar/actualizar corriendo
`php artisan postal-codes:import <archivo>` con acceso CLI al servidor. En producción el
acceso al shell es limitado (cPanel + despliegue por Jenkins), así que actualizar el
catálogo depende de un operador con SSH y del archivo ya colocado en disco. Se quiere que
un SUPER_ADMIN pueda subir el archivo oficial de SEPOMEX y actualizar el catálogo desde el
mismo panel, sin CLI, y con salvaguardas apropiadas para un dato global compartido.

## What Changes

- Nueva pantalla en el panel (Configuración → Catálogos → Códigos postales) para cargar y
  actualizar el catálogo SEPOMEX desde el navegador. Visible para cualquier SUPER_ADMIN
  (`canConfigureTenant`), con **aviso explícito de que el catálogo es global y la
  actualización afecta a todos los tenants**.
- Carga en **dos fases**: (1) subir archivo → el sistema lo parsea en background a una
  tabla de staging y muestra un **resumen** (total de filas, # de estados, # de
  municipios, muestra de ejemplo); (2) el SUPER_ADMIN **confirma** y solo entonces se
  aplica.
- **Swap atómico**: el catálogo vigente sigue sirviendo hasta que el nuevo pasa la
  validación de conteo mínimo (rechaza archivos truncados/incompletos); nunca queda una
  ventana con la tabla vacía ni una carga a medias.
- **Progreso en vivo por Reverb** (WebSocket) durante el parseo y la aplicación.
- Acepta el **ZIP oficial de SEPOMEX** ("Descarga nacional") o el **TXT/CSV** ya
  extraído; el sistema descomprime el ZIP y localiza el archivo de datos.
- **Historial de importaciones**: registro persistente de cada carga (quién, tenant de
  origen, archivo, conteo aplicado, resultado ok/rechazado, fecha), visible en la misma
  pantalla, para trazabilidad de un dato que cualquier SUPER_ADMIN puede reemplazar.
- El parseo de SEPOMEX se **extrae** del comando `postal-codes:import` a un servicio
  reutilizable (`PostalCodeImporter`); el comando CLI se **conserva** y pasa a usar ese
  servicio (no se duplica lógica).

## Non-goals

- No convertir `postal_codes` en tabla per-tenant: sigue siendo un catálogo global único.
- No cambiar la **consulta** del catálogo (endpoint de municipios ni el paso `state_city`);
  eso lo cubre la capacidad existente `catalogo-municipios-sepomex` y no se modifica.
- No un rol/permiso nuevo de "operador de plataforma": se reutiliza `canConfigureTenant`.
- No descargar el archivo de SEPOMEX automáticamente (SEPOMEX exige registro/manual);
  el archivo lo sube el usuario.
- No merge/upsert incremental: cada carga es un reemplazo completo por swap.

## Capabilities

### New Capabilities

- `gestion-catalogo-cp`: carga y actualización del catálogo SEPOMEX desde el panel del
  SUPER_ADMIN — subida de archivo (ZIP/TXT/CSV), parseo en background a staging, validación
  de conteo mínimo, resumen + confirmación, swap atómico del catálogo global, progreso en
  vivo por Reverb, y el servicio de importación reutilizable compartido con el comando CLI.
- `auditoria-catalogo-cp`: historial persistente de importaciones del catálogo (actor,
  tenant de origen, archivo, conteo, resultado, fecha) y su exposición en el panel.

### Modified Capabilities

<!-- Ninguna: la consulta del catálogo (catalogo-municipios-sepomex) no cambia sus requirements. -->

## Impact

- **Backend**:
  - Nuevo servicio `App\Services\PostalCode\PostalCodeImporter` (extrae el parseo de
    `App\Console\Commands\ImportPostalCodes`, que se conserva y lo consume).
  - Nuevo job en cola (`ImportPostalCodesJob`) que parsea a staging y emite eventos de
    progreso Reverb; nuevo evento de aplicación (swap).
  - Nueva tabla `postal_codes_staging` (o equivalente) para el import en dos fases y el
    swap atómico (Postgres: swap por `RENAME`/transacción).
  - Nueva tabla `postal_code_imports` para el historial de importaciones.
  - Nuevos endpoints staff bajo `permission:canConfigureTenant` (subir, estado/preview,
    confirmar/aplicar, historial).
  - Canal Reverb privado para el progreso de la importación.
- **Frontend**: nueva vista de admin (Configuración → Catálogos → Códigos postales):
  dropzone de subida, barra de progreso (Laravel Echo), pantalla de resumen +
  confirmación, y tabla de historial. Servicio V2 staff correspondiente.
- **Dependencias**: descompresión de ZIP en el servidor (extensión zip de PHP), con
  límite de tamaño y validación de contenido.
- **Alimenta** a `catalogo-municipios-sepomex`: al poblar `postal_codes` desde el panel,
  el endpoint de municipios y el paso `state_city` dejan de caer al fallback de texto
  libre. No se modifican sus requirements.
