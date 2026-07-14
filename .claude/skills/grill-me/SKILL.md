---
name: grill-me
description: Una entrevista implacable para afinar un plan o diseño. Invocable por el usuario con /grill-me.
disable-model-invocation: true
license: MIT
metadata:
  source: https://github.com/mattpocock/skills (skills/productivity/grill-me, adaptada para LendusFind)
---

Corre una sesión con la skill `grilling` sobre el tema que el usuario indique (o sobre el
tema en discusión si no indica ninguno). Sigue todas las reglas de esa skill: una pregunta
a la vez, respuesta recomendada en cada una, hechos se investigan / decisiones se
preguntan, y al cerrar ofrece continuar con el flujo OpenSpec (/opsx:new o /opsx:propose).
