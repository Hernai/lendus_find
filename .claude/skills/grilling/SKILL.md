---
name: grilling
description: Entrevistar al usuario implacablemente sobre un plan, decisión o idea hasta llegar a un entendimiento compartido. Usar cuando el usuario quiera afinar/estresar una idea, proponga una feature o cambio nuevo, o use frases como "grill me", "cuestióname", "tuéstame", "hazme preguntas". Correr SIEMPRE antes de crear un cambio OpenSpec (opsx:new, opsx:propose, opsx:ff).
license: MIT
metadata:
  source: https://github.com/mattpocock/skills (skills/productivity/grilling, adaptada para LendusFind)
---

# Grilling — entrevista implacable

Entrevista al usuario implacablemente sobre cada aspecto del plan/idea hasta llegar a un
entendimiento compartido. Recorre cada rama del árbol de decisión, resolviendo las
dependencias entre decisiones una por una. Para cada pregunta, ofrece tu respuesta
recomendada.

## Reglas de la entrevista

1. **Una pregunta a la vez.** Haz las preguntas de una en una y espera la respuesta antes
   de continuar. Varias preguntas a la vez abruman.
2. **Recomienda siempre.** Cada pregunta lleva tu respuesta recomendada con una razón
   breve. Si las opciones son discretas, usa la herramienta `AskUserQuestion` con la
   recomendada primero y "(Recomendado)" en la etiqueta; si es abierta, pregunta en texto.
3. **Hechos ≠ decisiones.** Si un *hecho* se puede averiguar explorando el entorno
   (código, migraciones, rutas, enums, config de tenants, api_logs), búscalo tú en lugar
   de preguntar. Las *decisiones* son del usuario: plantéaselas y espera su respuesta.
4. **Recorre el árbol completo.** Cada respuesta abre ramas nuevas (edge cases, errores,
   permisos por rol, multitenancy, estados del ciclo de vida, migración de datos
   existentes, UI móvil). No dejes ramas sin resolver.
5. **No actúes hasta confirmar.** No escribas código ni artefactos hasta que el usuario
   confirme que hay entendimiento compartido.

## Ángulos que casi siempre aplican en LendusFind

- **Multitenancy**: ¿aplica a todos los tenants o es per-tenant (demo, moneycapital,
  finatea)? ¿Va en `TenantApiConfig`, `product.rules`, o hardcodeado?
- **Roles**: ¿quién lo ve/usa — APPLICANT, ANALYST, SUPERVISOR, ADMIN, SUPER_ADMIN?
- **Ciclo de vida**: ¿en qué estados de la solicitud aplica? ¿Afecta transiciones?
- **Datos existentes**: ¿necesita backfill/migración para registros ya creados?
- **API pública**: si agrega config nueva a `product.rules`, recuerda la whitelist de
  `formatRules` en `/api/v2/config` (si no se lista, no llega al frontend).
- **Móvil**: ¿cómo se ve/comporta en la app Capacitor y en mobile web?

## Cierre e integración con OpenSpec

Al llegar al entendimiento compartido:

1. Resume las decisiones tomadas en una lista compacta (decisión → respuesta elegida →
   por qué).
2. Ofrece continuar con el flujo OpenSpec: crear el cambio con `/opsx:new` o
   `/opsx:propose`, usando las decisiones de la entrevista como base del proposal,
   design y specs. Las decisiones NO resueltas en la entrevista no se inventan: se
   preguntan antes de escribirlas en un artefacto.

Si la sesión de grilling nació dentro de un flujo OpenSpec ya iniciado (p. ej. durante
`opsx:explore` o al detectar ambigüedad en un proposal), vuelca las conclusiones al
artefacto correspondiente al terminar.
