## Context

El catálogo de códigos postales vive en la tabla global `postal_codes` (~145 mil filas, sin
tenant, sin UUID, sin timestamps; una fila por asentamiento). Hoy solo se puebla con
`php artisan postal-codes:import <archivo>` ([ImportPostalCodes.php](../../../backend/app/Console/Commands/ImportPostalCodes.php)),
que parsea el TXT/CSV de SEPOMEX por streaming e inserta en lotes, con `--truncate` opcional
y una marca de vigencia en cache (`postal_codes:last_import`) que consume
`postal-codes:check-freshness`. En producción (cPanel + Jenkins) el acceso a CLI es
limitado, así que actualizar el catálogo depende de un operador con shell.

El grilling resolvió: (1) cualquier SUPER_ADMIN puede cargar, con aviso de impacto global;
(2) staging + swap atómico con validación de conteo mínimo; (3) dos fases con preview +
confirmación; (4) progreso en vivo por Reverb; (5) historial de importaciones; (6) acepta
ZIP oficial o TXT/CSV.

El stack ya tiene lo necesario: broadcasting Reverb (evento `NotificationReceived` +
canales privados en `routes/channels.php` con helpers Staff/Applicant), colas Redis (jobs
en `app/Jobs`), y el grupo de rutas `integrations` bajo `permission:canConfigureTenant`
como plantilla para endpoints de SUPER_ADMIN.

## Goals / Non-Goals

**Goals:**
- Cargar/actualizar `postal_codes` desde el panel sin CLI, con dos fases (parsear→preview→
  confirmar→swap) y swap atómico que nunca deja el catálogo vacío.
- Progreso en vivo por Reverb y respaldo por polling.
- Historial de importaciones auditable (global, con tenant de origen).
- Un único servicio de parseo compartido por el panel y el comando CLI (que se conserva).

**Non-Goals:**
- `postal_codes` no se vuelve per-tenant; sigue global y único.
- No se tocan los requirements de consulta (`catalogo-municipios-sepomex`).
- No hay rol/permiso nuevo; se reutiliza `canConfigureTenant`.
- No hay descarga automática desde SEPOMEX ni upsert incremental (cada carga reemplaza).

## Decisions

### D1. Servicio `PostalCodeImporter` compartido; el comando CLI se conserva
Se extrae el parseo (resolveHeader, mapRow, toUtf8, inserción por lotes) de
`ImportPostalCodes` a `App\Services\PostalCode\PostalCodeImporter`. El comando pasa a ser
un thin wrapper que delega en el servicio; el job del panel usa el mismo servicio contra la
tabla de staging. **Alternativa rechazada:** duplicar la lógica en el job — divergiría del
comando y del formato SEPOMEX.

El servicio expone algo como `importToTable(string $path, string $table, ?callable $onProgress): ImportResult`
donde `ImportResult` trae conteos (filas, estados distintos, municipios distintos, muestra)
para alimentar preview y validación.

### D2. Staging permanente + swap atómico por RENAME dentro de transacción
Migración crea `postal_codes_staging` con la **misma estructura** que `postal_codes`
(incluido el índice de `cp`). El job: `TRUNCATE postal_codes_staging` → inserta el archivo
parseado → valida conteos → si pasa y el usuario confirma, hace el swap:

```sql
BEGIN;
ALTER TABLE postal_codes         RENAME TO _cp_swap_tmp;
ALTER TABLE postal_codes_staging RENAME TO postal_codes;
ALTER TABLE _cp_swap_tmp         RENAME TO postal_codes_staging;
COMMIT;
```

El RENAME solo toca metadata (no copia 145k filas): el `ACCESS EXCLUSIVE` lock dura
milisegundos y las consultas concurrentes esperan ese instante, nunca ven la tabla vacía o
parcial. Tras el swap, `postal_codes_staging` contiene el catálogo viejo, que se trunca al
inicio de la próxima carga. **Alternativa rechazada:** `TRUNCATE postal_codes; INSERT ... SELECT FROM staging`
copia 145k filas y mantiene el lock durante toda la copia (ventana más larga). **Alternativa
rechazada:** `CREATE TABLE` en runtime — puede chocar con permisos de DB restringidos en
cPanel; con staging creada por migración evitamos DDL dinámico salvo el RENAME.

### D3. Import en dos fases modelado por un registro de estado
Nueva tabla `postal_code_imports` (UUID) que **es el registro de auditoría y a la vez la
máquina de estado** del import: `PENDING_PARSE → PARSED` (listo para confirmar) `→ APPLYING → APPLIED`,
o `→ REJECTED` (con motivo). Guarda actor (`staff_account_id`), tenant de origen
(`origin_tenant_id` + slug, informativos), nombre de archivo, conteos del preview y
resultado. Su `id` es el `importId` que nombra el canal Reverb. El preview se sirve desde
las columnas de conteo de este registro (no requiere releer staging).

**Importante — sin tenant scoping:** aunque el resto del sistema usa `HasTenant`,
`postal_code_imports` NO lleva el global scope de tenant: el catálogo es compartido y todo
SUPER_ADMIN debe ver el historial completo, no solo las cargas de su tenant. El tenant de
origen queda como columna informativa, no como filtro.

### D4. Job de parseo + evento de progreso Reverb
`ImportPostalCodesJob` (cola Redis, timeout amplio) parsea el archivo a staging llamando al
servicio con un callback `onProgress` que emite `PostalCodeImportProgress` (implements
`ShouldBroadcast`) en el canal privado `postal-codes-import.{importId}`. Fases emitidas:
`parsing` (con % aproximado por filas), `validating`, `ready`/`rejected`. El `apply` emite
`applying` → `applied`. Autorización del canal en `routes/channels.php`: permitir solo
`StaffAccount` con `canConfigureTenant`.

**Respaldo sin WebSocket:** `GET /imports/{id}` devuelve el mismo estado/resumen, para que
la pantalla degrade a polling si Echo no conecta (consistente con que Reverb puede no estar
disponible en algunos entornos).

### D5. Descompresión de ZIP y almacenamiento temporal
El upload acepta `.zip`, `.txt`, `.csv`. Un `.zip` se abre con `ZipArchive`, se valida que
contenga un único archivo de datos con el encabezado esperado (`d_codigo`/`d_asenta`) y se
extrae a `storage/app/tmp/postal-imports/{importId}/`. El archivo temporal (subido y/o
extraído) se **borra al terminar** el job (éxito o fallo). No va a S3: es efímero y releerlo
desde S3 para parsear sería innecesario. Límite de tamaño (~30 MB) y validación de
extensión/MIME en el request.

### D6. Endpoints staff bajo `canConfigureTenant`
Nuevo grupo con prefijo `catalogs/postal-codes` (junto a `integrations`/`tenants`):
- `POST /upload` — recibe el archivo, crea el registro `postal_code_imports` (PENDING_PARSE),
  despacha `ImportPostalCodesJob`, devuelve `importId`.
- `GET  /imports/{id}` — estado + resumen (preview y respaldo de polling).
- `POST /imports/{id}/apply` — valida que esté en `PARSED`, ejecuta el swap (D2) y marca
  `APPLIED`; actualiza la marca de vigencia (`postal_codes:last_import`). El swap es de
  milisegundos → corre síncrono en el request dentro de la transacción.
- `GET  /imports` — historial (auditoría), orden desc por fecha.
- `GET  /status` — vigencia actual (última importación y conteo) reutilizando la marca de
  cache existente.

### D7. Frontend
Nueva vista admin `AdminPostalCodes.vue` en Configuración → Catálogos → Códigos postales:
dropzone de subida + aviso de impacto global, barra de progreso suscrita al canal vía
Laravel Echo, pantalla de resumen con botón "Aplicar", y tabla de historial. Servicio V2
`postalCatalog.staff.service.ts`. Ruta admin protegida por rol SUPER_ADMIN.

## Risks / Trade-offs

- **Descompresión de ZIP subido (zip-bomb / path traversal)** → solo SUPER_ADMIN (usuarios
  confiables), límite de tamaño del subido y del descomprimido, exigir un único archivo de
  datos con el encabezado esperado, extraer a ruta controlada por `importId` (sin usar los
  nombres internos del zip como ruta).
- **Lock `ACCESS EXCLUSIVE` durante el swap** → el RENAME es metadata pura (milisegundos);
  las consultas concurrentes esperan ese instante, no fallan ni ven vacío.
- **Cualquier SUPER_ADMIN reemplaza un dato global** → aviso explícito en UI + validación de
  conteo mínimo (rechaza truncados) + historial de auditoría con actor y tenant de origen.
- **Job largo / memoria con 145k filas** → parseo por streaming (`fgets`) e inserción por
  lotes, ya probado en el comando; se reutiliza vía el servicio.
- **Reverb no disponible en el entorno** → respaldo por polling de `GET /imports/{id}`.
- **Staging ocupa espacio** → tras el swap guarda el catálogo viejo (~15 MB); se trunca al
  inicio de la siguiente carga. Aceptable.

## Migration Plan

- **Migraciones nuevas:** `postal_codes_staging` (misma estructura + índice `cp` que
  `postal_codes`) y `postal_code_imports` (estado + auditoría). `postal_codes` no se toca.
- **Sin backfill:** el catálogo vigente queda intacto; la primera carga desde el panel lo
  reemplaza.
- **Broadcasting:** registrar el canal `postal-codes-import.{importId}` en
  `routes/channels.php` y el evento `PostalCodeImportProgress`. La config de Reverb ya
  existe.
- **Rollback:** revertir código y dropear las dos tablas nuevas; como el swap deja el
  catálogo nuevo ya en `postal_codes`, un rollback de código no revierte datos (es lo
  deseado: el catálogo cargado es válido). Para volver a un catálogo previo se recarga el
  archivo anterior.

## Open Questions

- **Umbral de conteo mínimo:** propuesto ≥ 100 000 filas y ≥ 32 estados distintos,
  parametrizable en `config/` para no hardcodear. ¿Valores finales?
- **`apply` síncrono vs. job:** se propone síncrono (el swap es de milisegundos). Si se
  prefiere consistencia con el parseo async, se mueve a un job corto que emita `applying`/
  `applied` por el mismo canal.
