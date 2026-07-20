# reanudar-onboarding

## Purpose

Al reabrir el onboarding, el cliente reanuda en el primer paso incompleto (los datos ya se prellenan).

## Requirements

### Requirement: Reanudar en el primer paso incompleto al reabrir
Cuando el onboarding dinámico se monta sin `stepId` en la URL (reapertura del flujo desde
el home, relogin o recarga), la vista DEBE (MUST) calcular el **primer paso incompleto**
de la lista de pasos vigente (`steps`, ya filtrada por `condition`) y hacer `router.replace`
hacia ese paso, en lugar de saltar siempre a `steps[0]`. Solo si TODOS los pasos ya están
completos DEBE (MUST) aterrizar en el último paso del flujo (el resumen/envío).

#### Scenario: Reapertura con pasos ya completados
- **WHEN** el cliente reabre el onboarding sin `stepId` en la URL y los primeros 4 pasos ya tienen datos válidos persistidos (draft + perfil)
- **THEN** la vista hace `router.replace` al 5.º paso (el primero incompleto) y NO al paso 1

#### Scenario: Reapertura sin ningún dato previo
- **WHEN** el cliente reabre el onboarding sin `stepId` y ningún paso tiene datos válidos
- **THEN** la vista aterriza en `steps[0]` (el primer paso), igual que un inicio limpio

#### Scenario: Reapertura con todo completo
- **WHEN** el cliente reabre el onboarding sin `stepId` y todos los pasos previos al resumen ya están completos
- **THEN** la vista aterriza en el paso de resumen/envío (último paso), sin obligarlo a recorrer el flujo de nuevo

### Requirement: Criterio de completitud por paso evaluado contra los datos persistidos
La determinación de "paso incompleto" DEBE (MUST) evaluar la validez de cada paso con la
misma validación canónica que gatea el botón "Continuar" (la validez intrínseca del tipo
de paso), aplicada al valor persistido de ese paso leído de `dynamicData`/perfil por su
`id`. Un paso marcado `required === false` y los pasos de tipo `review`/`review_full`
DEBEN (MUST) contar SIEMPRE como completos (nunca detienen la reanudación); un paso
obligatorio cuyo valor persistido no pasa su validación DEBE (MUST) contar como incompleto.

#### Scenario: Paso opcional vacío no detiene la reanudación
- **WHEN** un paso con `required: false` no tiene valor y le siguen pasos obligatorios incompletos
- **THEN** el paso opcional se considera completo y el destino de reanudación es el siguiente paso obligatorio incompleto

#### Scenario: Paso obligatorio con datos inválidos cuenta como incompleto
- **WHEN** un paso obligatorio (p. ej. `bank_account`) tiene un valor persistido que no pasa su validación (CLABE incompleta)
- **THEN** ese paso se considera incompleto y es el destino de la reanudación

### Requirement: Respetar la navegación explícita y el filtrado de pasos
La reanudación al primer paso incompleto SOLO DEBE (MUST) aplicar cuando NO hay `stepId`
en la URL. Si la URL ya trae `stepId` (deep link, botón atrás/adelante, o re-edición desde
el resumen con `?returnTo=`), la vista NO DEBE (MUST NOT) recalcular ni sobrescribir ese
destino. El cálculo del primer paso incompleto DEBE (MUST) operar únicamente sobre la lista
`steps` ya filtrada por `condition`, de modo que un paso excluido por la rama/integraciones
del tenant NUNCA sea elegido como destino.

#### Scenario: URL con stepId explícito no se recalcula
- **WHEN** el onboarding se monta con un `stepId` presente en la URL
- **THEN** la vista respeta ese paso y NO ejecuta el salto al primer paso incompleto

#### Scenario: Paso filtrado por condición no es candidato
- **WHEN** un paso queda fuera de `steps` por su `condition` (p. ej. `if_kyc_provider` sin proveedor KYC, o rama persona moral)
- **THEN** ese paso no se evalúa ni se elige como destino de reanudación

### Requirement: Preservar los datos ya capturados al saltar adelante
Al reanudar saltando a un paso posterior, el flujo DEBE (MUST) conservar los valores ya
capturados: el paso destino y todos los pasos previos DEBEN (MUST) mostrar sus datos
prellenados desde el borrador local (`onboarding_draft`) y/o el perfil del backend, de modo
que reanudar NUNCA obligue a recapturar información ya guardada ni deje pasos previos vacíos.

#### Scenario: Paso previo conserva su valor tras reanudar
- **WHEN** el cliente reanuda en un paso posterior y regresa (botón atrás) a un paso anterior
- **THEN** ese paso anterior muestra el valor que había capturado antes, sin exigir recaptura

#### Scenario: El destino de reanudación arranca prellenado si tenía datos parciales
- **WHEN** el paso destino tenía datos parciales persistidos que no completaban su validación
- **THEN** el renderer del paso destino se monta con esos datos parciales ya cargados, listos para completarse
