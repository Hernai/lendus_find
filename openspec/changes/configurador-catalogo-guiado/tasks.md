# Tasks — configurador-catalogo-guiado

## 1. Backend

- [x] 1.1 `DecisionPolicyController::catalog()` + ruta GET `/decision-policies/catalog` (permiso canManageProducts): variables con valores/etiquetas desde los enums; state/city como valor libre
- [x] 1.2 `dryRun()` acepta `policy_id` XOR `rules`+`product_id`; valida coherencia de las reglas inline; sin persistir corrida cuando no hay política previa del producto
- [x] 1.3 Tests: catálogo (estructura + permiso), dry-run inline (evalúa, valida coherencia, no crea versiones)

## 2. Frontend

- [x] 2.1 Service: `getCatalog()` + `dryRun` con reglas inline
- [x] 2.2 Editor: select de variable desde catálogo (sin duplicados), valores pre-listados con etiqueta y solo puntos; captura libre para variables sin catálogo o desconocidas
- [x] 2.3 Guía inline: intro "¿Cómo decide el motor?" + hint por sección (bandas, cortes, gate/cooldown, graduación)
- [x] 2.4 Botón "Probar esta configuración" en el editor → probador pre-cargado con las reglas en pantalla

## 3. Verificación

- [x] 3.1 `php artisan test` grupos del motor + `vue-tsc` + lint de archivos tocados
- [x] 3.2 Verificación en navegador en el stack de dev (:5176): editor con catálogo, probar sin guardar
