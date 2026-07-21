## Context

No existe un componente `Modal` común: cada modal/sheet implementa su propio overlay
`<div class="fixed inset-0 ...">`. El cierre por clic afuera se hace con `@click.self` (o un
`@click` en un backdrop). Un grep encontró `@click.self` en 24 archivos, mezclando dos
patrones distintos: modales de diálogo (con formulario/confirmación, donde el clic accidental
pierde datos) y bottom-sheets/selectores (pickers, donde tocar afuera para cerrar es esperado).

## Goals / Non-Goals

**Goals:** que los modales de diálogo no se cierren por backdrop; conservar el cierre por
backdrop en sheets/selectores.

**Non-Goals:** crear un `BaseModal` compartido; cambiar sheets/pickers; tocar el
`TenantSelectorModal` (picker forzoso) ni `CounterOfferModal` (ya lo evitaba).

## Decisions

### 1. Fix puntual archivo por archivo (sin BaseModal)
Al no haber componente común, el cambio se aplica quitando `@click.self` en cada modal de
diálogo. *Alternativa descartada:* refactorizar a un `BaseModal` — mayor alcance y riesgo
ahora; queda como mejora futura.

### 2. Distinguir modal de diálogo vs bottom-sheet por patrón
Modal de diálogo (dialog centrado con formulario/confirmación) → quitar backdrop-close.
Bottom-sheet/selector (slideUp, lista de opciones, hoja informativa dismissable) → conservar.
Se clasificó cada uno de los 24 leyendo el archivo; 16 resultaron modales (se limpiaron) y 8
sheets/pickers (se dejaron).

## Risks / Trade-offs

- **Quitar el único cierre de un modal** → Se verificó que cada modal limpiado conserva ✕ o
  Cancelar antes de quitar el backdrop-close; ninguno quedó sin salida.
- **Clasificar mal un overlay** → Verificado por diff (solo se removieron handlers de
  backdrop) + `type-check`. Los sheets/pickers no aparecen en la lista de modificados.

## Migration Plan

Cambio de frontend; sin datos ni backend. Rollback = revertir los 16 componentes.
