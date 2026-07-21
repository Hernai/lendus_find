---
name: "Ciclo"
description: Corre UN ciclo de trabajo LendusFind end-to-end (estado → grilling → OpenSpec → apply → verificación → memoria) como un loop repetible y barato. Manual, no autónomo.
category: Workflow
tags: [loop, workflow, openspec, verification]
---

Orquesta UN ciclo de trabajo reutilizando lo que ya existe. NO es un loop autónomo
agendado: se dispara a mano, gasta tokens solo cuando lo invocas, y mantiene al humano en
los dos gates que importan (grilling y verificación).

**Input**: el tema/tarea del ciclo (ej. `/ciclo catálogo CP autoservicio`). Si se omite,
infiere de la conversación o del cambio OpenSpec activo; si hay ambigüedad, usa
AskUserQuestion.

## La espina (estado en disco, no en tu cabeza)
El modelo olvida todo entre corridas — la memoria del loop vive en disco:
- `openspec/changes/<cambio>/tasks.md` → qué falta (checkboxes = checkpoints).
- Auto-memoria (`.../memory/MEMORY.md` + notas) → decisiones y trampas conocidas.
- `git status` + rama → qué se tocó.
Lee estos TRES antes de decidir el siguiente paso. Nunca re-derives contexto ya escrito.

## Pasos

1. **Ubícate (barato).** Corre `openspec list` y `git status`; lee `MEMORY.md`. Anuncia en
   una línea: cambio activo, fase (proposal/design/specs/tasks/apply/verify) y qué sigue.
   Sin cambio y tema no trivial → paso 2. Cambio ya en apply → paso 4.

2. **Grilling (gate humano).** Si es feature/decisión/rediseño nuevo y no hubo grilling en
   esta conversación, corre la skill `grilling` (una pregunta a la vez, con recomendación).
   Los *hechos* se investigan en el código; las *decisiones* se preguntan. Fixes triviales
   saltan este gate.

3. **OpenSpec.** Con las decisiones del grilling, crea/continúa con `/opsx:new`,
   `/opsx:propose` o `/opsx:continue`. Flujo spec-driven.

4. **Apply.** Implementa la siguiente tarea de `tasks.md` con `/opsx:apply`. Marca los
   checkboxes conforme avanzas (ESE es el checkpoint del loop).
   - Opcional (trabajo paralelo/arriesgado): aísla en git worktree (herramienta
     EnterWorktree) para no colisionar; se limpia solo si no cambió nada.

5. **Verificación (gate: no califiques tu propia tarea).** Al cerrar la tarea NO te
   autoapruebes. Lanza el agente `revisor-lendus` (subagent_type: "revisor-lendus") con el
   diff + el spec del cambio: es read-only, barato y conoce las trampas recurrentes del
   proyecto. Corrige lo que confirme. Para dudas de comportamiento vivo usa la skill
   `ui-smoke`; para verificar el flujo end-to-end, la skill `verify`.

6. **Cierra el checkpoint.** Actualiza `tasks.md`. Si surgió una decisión/trampa nueva no
   obvia, guárdala en auto-memoria (una nota = un hecho) para que el próximo ciclo la
   herede. Reporta en 2-3 líneas: qué se hizo, qué verificó el revisor, qué queda.

## Regla de tokens
Este comando existe para gastar POCO: reutiliza skills por nombre (no las re-expliques),
mantén cada subagente con scope al diff, usa modelos baratos donde el segundo par de ojos
no requiere opus. Una versión AUTÓNOMA agendada (heartbeat con `/loop` o cron) NO se activa
por default: multiplica tokens y crea deuda de comprensión (Osmani, "loop engineering").
Pídela explícitamente si algún día la quieres.
