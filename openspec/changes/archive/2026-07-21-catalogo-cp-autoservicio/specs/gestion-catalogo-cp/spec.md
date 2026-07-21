# gestion-catalogo-cp

## Purpose

Permitir que un SUPER_ADMIN cargue y actualice el catálogo global de códigos postales
(SEPOMEX, tabla `postal_codes`) desde el panel, sin acceso CLI al servidor, con un flujo
seguro de dos fases (parsear a staging → confirmar → swap atómico) apropiado para un dato
compartido por todos los tenants.

## ADDED Requirements

### Requirement: Acceso restringido a SUPER_ADMIN con aviso de impacto global
La pantalla de carga del catálogo y todos sus endpoints DEBEN (MUST) exigir el permiso
`canConfigureTenant` (SUPER_ADMIN). Dado que `postal_codes` es un catálogo global único
compartido por todos los tenants, la interfaz DEBE (MUST) mostrar un aviso explícito de
que la actualización reemplaza el catálogo para **todos** los tenants antes de permitir la
confirmación. No se introduce un rol/permiso nuevo: se reutiliza `canConfigureTenant`.

#### Scenario: Cuenta sin permiso no accede
- **WHEN** una cuenta staff sin `canConfigureTenant` (ANALYST/SUPERVISOR/ADMIN) intenta abrir la pantalla o llamar a los endpoints de carga
- **THEN** el sistema DEBE (MUST) responder 403 y no exponer ni la pantalla ni la acción

#### Scenario: SUPER_ADMIN ve el aviso de impacto global
- **WHEN** un SUPER_ADMIN abre la pantalla de carga del catálogo
- **THEN** la interfaz DEBE (MUST) mostrar el aviso de que el catálogo es global y la actualización afecta a todos los tenants

### Requirement: Carga en dos fases con resumen y confirmación
El sistema DEBE (MUST) separar la carga en dos fases. En la primera, tras subir el archivo,
DEBE (MUST) parsearlo a una tabla de staging y presentar un resumen con al menos: total de
filas válidas, número de estados distintos, número de municipios distintos y una muestra de
ejemplo. En la segunda, el swap del catálogo vigente SOLO (MUST) ocurre tras una
confirmación explícita del SUPER_ADMIN. Sin confirmación, el catálogo vigente permanece
intacto.

#### Scenario: Resumen tras el parseo, antes de aplicar
- **WHEN** un SUPER_ADMIN sube un archivo válido y el parseo a staging termina
- **THEN** el sistema DEBE (MUST) mostrar el resumen (filas, estados, municipios, muestra) y NO haber tocado aún el catálogo vigente

#### Scenario: El swap requiere confirmación explícita
- **WHEN** existe un staging parseado y el usuario NO confirma (cancela o abandona)
- **THEN** el catálogo vigente DEBE (MUST) seguir intacto y el staging DEBE (MUST) poder descartarse sin efecto

### Requirement: Formatos aceptados ZIP oficial y TXT/CSV
La carga DEBE (MUST) aceptar el ZIP oficial de SEPOMEX ("Descarga nacional") y también el
TXT/CSV ya extraído. Ante un ZIP, el sistema DEBE (MUST) descomprimirlo y localizar el
archivo de datos con el encabezado esperado (`d_codigo`/`d_asenta`). El sistema DEBE (MUST)
aplicar un límite de tamaño y rechazar formatos o contenidos no soportados con un mensaje
claro, sin dejar residuos en disco.

#### Scenario: ZIP oficial descomprimido y parseado
- **WHEN** un SUPER_ADMIN sube el ZIP oficial de SEPOMEX que contiene el TXT de datos
- **THEN** el sistema DEBE (MUST) descomprimirlo, localizar el TXT y parsearlo a staging

#### Scenario: Archivo no soportado se rechaza
- **WHEN** se sube un archivo que no es ZIP/TXT/CSV, excede el límite de tamaño, o no contiene el encabezado esperado
- **THEN** el sistema DEBE (MUST) rechazar la carga con un mensaje claro y no modificar el catálogo vigente

### Requirement: Parseo en background con progreso en vivo
El parseo del archivo a staging DEBE (MUST) ejecutarse en un job en cola (no en el ciclo
del request HTTP), para no exceder timeouts con ~145 mil filas. El sistema DEBE (MUST)
emitir el progreso (fase y avance) por un canal Reverb (WebSocket) para que la pantalla lo
refleje en vivo, y DEBE (MUST) publicar el resultado final (listo/rechazado) por el mismo
canal.

#### Scenario: Progreso emitido durante el parseo
- **WHEN** el job de parseo procesa el archivo
- **THEN** el sistema DEBE (MUST) emitir eventos de progreso por Reverb y la pantalla DEBE (MUST) mostrar el avance sin recargar

#### Scenario: Resultado final publicado
- **WHEN** el job termina (éxito o rechazo por validación)
- **THEN** el sistema DEBE (MUST) publicar el estado final por el canal para desbloquear el paso de confirmación o mostrar el error

### Requirement: Validación de conteo mínimo antes del swap
Antes de permitir el swap, el sistema DEBE (MUST) validar que el staging alcanza umbrales
mínimos de integridad (por ejemplo, un número mínimo de filas y de estados distintos
consistente con el catálogo nacional). Si el staging no los alcanza, el sistema DEBE (MUST)
rechazar la importación, NO aplicar el swap y conservar el catálogo vigente.

#### Scenario: Archivo truncado rechazado
- **WHEN** el staging parseado queda por debajo del umbral mínimo (p. ej. muchas menos filas o menos estados de los esperados)
- **THEN** el sistema DEBE (MUST) rechazar la importación, no aplicar el swap y dejar intacto el catálogo vigente

### Requirement: Swap atómico sin ventana con catálogo vacío
La aplicación del nuevo catálogo DEBE (MUST) ser atómica: el catálogo vigente sirve las
consultas hasta el instante del reemplazo y en ningún momento la tabla consultada queda
vacía o a medias. Si la aplicación falla, el catálogo previo DEBE (MUST) permanecer
intacto.

#### Scenario: Consultas nunca ven un catálogo vacío
- **WHEN** se aplica un nuevo catálogo mientras hay consultas de municipios en curso
- **THEN** las consultas DEBEN (MUST) devolver el catálogo previo o el nuevo completo, nunca un estado vacío o parcial

#### Scenario: Fallo en la aplicación conserva el catálogo previo
- **WHEN** ocurre un error durante el swap
- **THEN** el catálogo vigente DEBE (MUST) quedar igual que antes del intento

### Requirement: Servicio de importación reutilizable y comando CLI conservado
La lógica de parseo del archivo SEPOMEX DEBE (MUST) residir en un servicio reutilizable
compartido por la carga del panel y por el comando `postal-codes:import`. El comando CLI
DEBE (MUST) seguir funcionando (se conserva), delegando en ese servicio, para no duplicar
la lógica de parseo/mapeo de columnas. Tras aplicar el catálogo, el sistema DEBE (MUST)
actualizar la marca de vigencia que ya consume `postal-codes:check-freshness`.

#### Scenario: El comando CLI sigue operando vía el servicio
- **WHEN** se ejecuta `php artisan postal-codes:import <archivo>`
- **THEN** el import DEBE (MUST) funcionar como antes, usando el mismo servicio que la carga del panel

#### Scenario: Marca de vigencia actualizada tras aplicar
- **WHEN** una carga desde el panel se aplica con éxito
- **THEN** el sistema DEBE (MUST) actualizar la marca de última importación para que el chequeo de vigencia la refleje

### Requirement: Serialización de importaciones concurrentes y aplicación segura
Dado que el staging es una tabla física ÚNICA compartida, el sistema DEBE (MUST) impedir que
dos importaciones coexistan sobre él de forma que un `apply` termine aplicando datos ajenos.
El sistema DEBE (MUST) permitir una sola importación "en vuelo" a la vez (en parseo, lista o
aplicándose) y rechazar una nueva carga hasta resolver la pendiente (aplicarla o
descartarla). Al aplicar, el sistema DEBE (MUST) revalidar la staging actual (conteo mínimo y
correspondencia con lo parseado por ese import) y ejecutar el swap de forma atómica de modo
que un doble disparo no pueda deshacer un swap ya aplicado. El usuario DEBE (MUST) poder
descartar una importación pendiente para liberar el sistema.

#### Scenario: Nueva carga con otra en vuelo se rechaza
- **WHEN** un SUPER_ADMIN sube un archivo mientras existe otra importación en vuelo (parseando, lista o aplicándose)
- **THEN** el sistema DEBE (MUST) rechazar la nueva carga con un mensaje claro y no crear un segundo import ni despachar su parseo

#### Scenario: Aplicar con la staging cambiada se rechaza sin swap
- **WHEN** se intenta aplicar un import cuyo conteo ya no corresponde al contenido actual de la staging (fue sobrescrita)
- **THEN** el sistema DEBE (MUST) rechazar la aplicación sin ejecutar el swap y dejar el catálogo vigente intacto

#### Scenario: Doble aplicación no deshace el swap
- **WHEN** se dispara `apply` dos veces sobre el mismo import (doble clic o reintento)
- **THEN** solo una aplicación DEBE (MUST) ejecutar el swap; la segunda DEBE (MUST) rechazarse sin revertir el catálogo recién aplicado

#### Scenario: Descartar una importación pendiente libera el sistema
- **WHEN** un SUPER_ADMIN descarta una importación en estado pendiente o lista para aplicar
- **THEN** el sistema DEBE (MUST) marcarla como descartada, dejar de considerarla "en vuelo" y permitir una nueva carga
