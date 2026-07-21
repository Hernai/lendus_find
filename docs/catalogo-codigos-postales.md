# Catálogo de códigos postales (SEPOMEX)

El catálogo de códigos postales vive en la tabla **global** `postal_codes` (~145 mil
asentamientos de todo México, una fila por colonia). Alimenta el autollenado de
estado/municipio/colonia en el alta de domicilio y el endpoint de municipios por estado
del onboarding. Es **compartido por todos los tenants**: actualizarlo afecta a demo,
moneycapital, finatea, etc. a la vez.

## Cómo actualizarlo

Hay dos vías equivalentes; ambas reemplazan el catálogo completo.

### 1. Desde el panel (SUPER_ADMIN) — recomendado

Configuración → **Catálogos → Códigos postales**.

1. Sube el archivo oficial de SEPOMEX: el **ZIP** de "Descarga nacional" o el **TXT/CSV**
   ya extraído (máx. 30 MB).
2. El sistema lo procesa en segundo plano (barra de progreso en vivo) y muestra un
   **resumen** (filas, estados, municipios y una muestra). El catálogo vigente **no** se
   toca todavía.
3. Revisa el resumen y pulsa **Aplicar**. Solo entonces se reemplaza el catálogo, mediante
   un intercambio atómico que nunca deja la tabla vacía. Si el archivo viene truncado
   (menos filas/estados que el mínimo configurado) se **rechaza** y el catálogo vigente
   queda intacto.

Cada carga (aplicada o rechazada) queda registrada en el **historial** de la misma
pantalla: quién la hizo, desde qué tenant, archivo, conteo y resultado.

> ⚠️ El catálogo es nacional y compartido: al aplicar una actualización se reemplaza para
> **todos** los tenants. La pantalla lo advierte antes de confirmar.

### 2. Desde la línea de comandos (servidores)

Sigue disponible el comando de siempre (útil en despliegues o automatizaciones):

```bash
php artisan postal-codes:import storage/app/CPdescarga.txt
```

Usa el mismo motor de parseo que el panel. Acepta el TXT/CSV de SEPOMEX (delimitado por
`|`, Windows-1252). Reemplaza el contenido de `postal_codes`.

## Vigencia

El comando `php artisan postal-codes:check-freshness` avisa si el catálogo lleva demasiado
tiempo sin actualizarse (la marca de última importación se refresca en ambas vías).

## Dónde se baja el archivo

Portal oficial (requiere registro gratuito):
<https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/CodigoPostal_Exportar.aspx>

## Parámetros (config/postal_codes.php)

- `min_rows` / `min_states`: umbrales mínimos de integridad para aceptar una carga.
- `max_upload_mb`: tamaño máximo del archivo subido.
- `staging_table`: tabla temporal para el procesamiento en dos fases.
