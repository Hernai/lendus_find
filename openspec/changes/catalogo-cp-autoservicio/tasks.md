## 1. Datos y modelo (backend)

- [x] 1.1 Migración `postal_codes_staging` con la misma estructura e índice de `cp` que `postal_codes` (sin timestamps, sin tenant, sin UUID).
- [x] 1.2 Migración `postal_code_imports` (UUID PK): `staff_account_id`, `origin_tenant_id`, `origin_tenant_slug`, `original_filename`, `status`, `rows_count`, `states_count`, `municipalities_count`, `sample` (JSONB), `rejection_reason`, `created_at`/`updated_at`. Índice por `created_at`.
- [x] 1.3 Enum `PostalCodeImportStatus` (string-backed, UPPERCASE, HasOptions): `PENDING_PARSE`, `PARSED`, `APPLYING`, `APPLIED`, `REJECTED`.
- [x] 1.4 Modelo `PostalCodeImport` (HasUuid), **sin** el global scope de tenant (catálogo global; todo SUPER_ADMIN ve el historial completo). Cast de `sample` a array y de `status` al enum.

## 2. Servicio de importación reutilizable

- [x] 2.1 Crear `App\Services\PostalCode\PostalCodeImporter` extrayendo el parseo de `ImportPostalCodes` (resolveHeader, mapRow, toUtf8, inserción por lotes); `importToTable(string $path, string $table, ?callable $onProgress): ImportResult` que devuelve conteos (filas, estados distintos, municipios distintos, muestra).
- [x] 2.2 Refactorizar el comando `postal-codes:import` para delegar en el servicio, conservando su firma y comportamiento (incluida la marca de vigencia `postal_codes:last_import`).
- [x] 2.3 Test: `php artisan postal-codes:import <fixture>` sigue funcionando vía el servicio (verificado en integración: 4 válidos importados, 1 CP inválido omitido, encoding UTF-8 correcto).

## 3. Descompresión, validación y swap atómico

- [x] 3.1 Helper de entrada de archivo: aceptar `.zip`/`.txt`/`.csv`; para ZIP, abrir con `ZipArchive`, exigir un único archivo de datos con encabezado `d_codigo`/`d_asenta`, extraer a `storage/app/tmp/postal-imports/{importId}/`; borrar temporales al final.
- [x] 3.2 Validación de conteo mínimo parametrizable en `config/` (propuesta: ≥100k filas y ≥32 estados) que decide `PARSED` vs `REJECTED` con motivo.
- [x] 3.3 Método de swap atómico (transacción con RENAME `postal_codes` ↔ `postal_codes_staging`) que reemplaza el catálogo sin ventana vacía y actualiza la marca de vigencia.
- [x] 3.4 Test: staging por debajo del umbral → `REJECTED`, `postal_codes` intacto. (`PostalCodeCatalogSwapperTest`)
- [x] 3.5 Test: swap deja `postal_codes` con las filas nuevas y `postal_codes_staging` con las viejas; consultas nunca ven vacío. (`PostalCodeCatalogSwapperTest`)

## 4. Job y progreso en vivo (Reverb)

- [x] 4.1 Job `ImportPostalCodesJob` (cola Redis, timeout amplio): trunca staging, parsea vía el servicio con `onProgress`, valida conteo y marca `PARSED`/`REJECTED`.
- [x] 4.2 Evento `PostalCodeImportProgress` (implements `ShouldBroadcast`) en canal privado `postal-codes-import.{importId}`; fases `parsing`(%)→`validating`→`ready`/`rejected`, y `applying`→`applied`.
- [x] 4.3 Autorizar el canal en `routes/channels.php`: solo `StaffAccount` con `canConfigureTenant`.

## 5. API staff

- [x] 5.1 Controller `StaffPostalCatalogController` con `upload`, `show` (estado+resumen), `apply` (swap síncrono en `PARSED`→`APPLIED`), `index` (historial), `status` (vigencia actual).
- [x] 5.2 FormRequest de `upload`: extensión/MIME permitidos y límite de tamaño (~30 MB).
- [x] 5.3 Rutas: grupo `catalogs/postal-codes` bajo `permission:canConfigureTenant` (junto a `integrations`/`tenants`) mapeando los cinco endpoints.
- [x] 5.4 Test: los endpoints devuelven 403 sin `canConfigureTenant`; 200 con SUPER_ADMIN. (`PostalCatalogControllerTest`)
- [x] 5.5 Test: `apply` sobre un import `PARSED` aplica el swap y registra `APPLIED`; el historial (`index`) lista aplicados y rechazados con auditoría. (`PostalCatalogControllerTest`)

## 6. Frontend (panel)

- [x] 6.1 Servicio `postalCatalog.staff.service.ts` (upload multipart, show, apply, index, status) con `V2ApiResponse<T>`. (en `modules/admin/services/`, ubicación canónica de staff services)
- [x] 6.2 Vista `AdminPostalCodes.vue`: dropzone + **aviso de impacto global**, barra de progreso suscrita al canal vía Laravel Echo (con respaldo a polling de `show`), pantalla de resumen con botón "Aplicar", y tabla de historial.
- [x] 6.3 Ruta admin (SUPER_ADMIN) y entrada en el menú Configuración → Catálogos → Códigos postales.

## 7. Verificación y cierre

- [x] 7.1 `php artisan test` de los tests nuevos (backend) en verde (8 tests / 42 aserciones) y `npm run type-check` sin errores (exit 0).
- [x] 7.2 Smoke navegador (ui-smoke): la vista de admin monta e integra en vivo — nav SUPER_ADMIN, aviso de impacto global, dropzone, vigencia con `current_count` real del backend, historial; 0 errores de app. (El flujo completo upload→apply requiere un archivo SEPOMEX real / umbral bajo; queda para verificación manual.)
- [x] 7.3 Nota breve en la doc de operación (`docs/catalogo-codigos-postales.md`): las dos vías (panel + CLI), el flujo de dos fases, vigencia y parámetros.

## 8. Fixes de la revisión adversarial (concurrencia sobre staging única)

- [x] 8.1 Serialización: `upload` rechaza con 409 `IMPORT_IN_PROGRESS` si hay un import en vuelo (PENDING_PARSE/PARSED/APPLYING); enum `PostalCodeImportStatus::IN_FLIGHT`.
- [x] 8.2 `apply` con transición atómica `PARSED→APPLYING` (UPDATE condicional) — un doble disparo no puede deshacer un swap ya aplicado.
- [x] 8.3 `apply` revalida la staging actual (conteo mínimo + correspondencia con lo parseado); si cambió, 409 `STAGING_MISMATCH` y `REJECTED` sin swap.
- [x] 8.4 Estado `DISCARDED` + endpoint `POST /imports/{id}/discard`; job blindado para no revivir un import descartado durante el parseo (`markIfPending`).
- [x] 8.5 Frontend: manejar 409 (in-progress/mismatch), acciones "Aplicar/Descartar" por fila en el historial, retomar import en vuelo al recargar, usar `status_label` del backend.
- [x] 8.6 Tests: serialización 409, doble-apply, staging desincronizada, discard (verde: 14 tests / 67 aserciones).
