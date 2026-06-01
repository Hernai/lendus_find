# 🧪 Plan de Pruebas Exhaustivo: Timeline "Ver detalles" Button

**Fecha:** 2026-01-22
**Feature:** Fix para mostrar botón "Ver detalles" en timeline de aplicaciones
**Tester:** Claude (TOC Mode - Obsesivo Compulsivo) ✅

---

## 📋 Objetivo

Verificar exhaustivamente que el botón "Ver detalles" aparezca en el timeline del admin panel cuando los eventos tienen metadata relevante, y que toda la cadena (backend → frontend → modal) funcione correctamente.

---

## 🎯 Criterios de Aceptación

### Backend (ApplicationController.php)
- ✅ El método `formatApplicationDetail` debe incluir campos metadata en `status_history`
- ✅ Debe extraer `ip_address` del metadata
- ✅ Debe extraer `user_agent` del metadata
- ✅ Debe incluir el objeto `metadata` completo
- ✅ Debe incluir flag `is_lifecycle_event`
- ✅ Debe incluir `event_type` y `event_label` para lifecycle events
- ✅ El import de `ApplicationEventService` debe estar presente

### Frontend (TimelineSection.vue)
- ✅ El componente debe usar `hasDetailableMetadata()` correctamente
- ✅ El botón debe renderizarse con `v-if="hasDetailableMetadata(event)"`
- ✅ El método debe verificar `metadata.ip_address`, `metadata.user_agent`, etc.
- ✅ El click debe emitir evento `view-details` con el event completo

### Integración
- ✅ El API debe retornar metadata en la respuesta JSON
- ✅ El frontend debe recibir y parsear metadata correctamente
- ✅ El modal debe mostrarse con información completa

---

## 🔍 Fase 1: Verificación de Código Backend

### Test 1.1: Verificar mapping de status_history
**Ubicación:** `/backend/app/Http/Controllers/Api/V2/Staff/ApplicationController.php:787-809`

**Checklist:**
- [ ] El mapping usa función anónima (no arrow function simple)
- [ ] Extrae `$metadata = $h->metadata ?? []`
- [ ] Calcula `$isLifecycleEvent` usando `ApplicationEventService::isLifecycleEvent()`
- [ ] Retorna objeto con campos:
  - [ ] `from_status`
  - [ ] `from_status_label` (con lógica lifecycle)
  - [ ] `to_status`
  - [ ] `to_status_label`
  - [ ] `changed_by`
  - [ ] `notes`
  - [ ] `created_at`
  - [ ] `is_lifecycle_event`
  - [ ] `event_type`
  - [ ] `event_label`
  - [ ] `ip_address` (extraído de metadata)
  - [ ] `user_agent` (extraído de metadata)
  - [ ] `metadata` (objeto completo)

### Test 1.2: Verificar import de ApplicationEventService
**Ubicación:** `/backend/app/Http/Controllers/Api/V2/Staff/ApplicationController.php:13`

**Checklist:**
- [ ] Línea 13 contiene: `use App\Services\ApplicationEventService;`

### Test 1.3: Verificar que ApplicationEventService existe y tiene métodos necesarios
**Ubicación:** `/backend/app/Services/ApplicationEventService.php`

**Checklist:**
- [ ] Método `isLifecycleEvent($status)` existe
- [ ] Método `getEventLabel($status)` existe
- [ ] Ambos métodos son públicos y estáticos

---

## 🔍 Fase 2: Verificación de Código Frontend

### Test 2.1: Verificar método hasDetailableMetadata
**Ubicación:** `/frontend/src/components/admin/application-detail/TimelineSection.vue:45-68`

**Checklist:**
- [ ] Método recibe parámetro `event: TimelineEvent`
- [ ] Extrae `const m = event.metadata`
- [ ] Verifica `if (!m) return false`
- [ ] Retorna true si alguno de estos campos existe:
  - [ ] `m.ip_address`
  - [ ] `m.user_agent`
  - [ ] `m.location`
  - [ ] `m.old_value`
  - [ ] `m.new_value`
  - [ ] `m.changes && Object.keys(m.changes).length > 0`
  - [ ] `m.reason`
  - [ ] `m.document_type`
  - [ ] `m.changed_fields?.length`
  - [ ] `m.step_number`
  - [ ] `m.bank_name`
  - [ ] `m.reference_type`
  - [ ] `m.postal_code`
  - [ ] `m.employment_type`
  - [ ] `m.is_valid !== undefined`
  - [ ] `m.matched !== undefined`
  - [ ] `m.score !== undefined`

### Test 2.2: Verificar renderizado del botón
**Ubicación:** `/frontend/src/components/admin/application-detail/TimelineSection.vue:100-109`

**Checklist:**
- [ ] Botón tiene `v-if="hasDetailableMetadata(event)"`
- [ ] Botón tiene `@click="emit('view-details', event)"`
- [ ] Botón tiene clases CSS correctas
- [ ] Texto es "Ver detalles"
- [ ] Tiene ícono SVG

### Test 2.3: Verificar tipo TypeScript de TimelineEvent
**Ubicación:** `/frontend/src/types/` (buscar definición)

**Checklist:**
- [ ] Interface `TimelineEvent` incluye campo `metadata?: any` o similar
- [ ] Permite metadata opcional (puede ser undefined)

---

## 🔍 Fase 3: Pruebas de Integración

### Test 3.1: Verificar respuesta API real
**Método:** GET `/api/v2/staff/applications/{id}`

**Pasos:**
1. [ ] Obtener ID de una aplicación real del sistema
2. [ ] Hacer request al endpoint con autenticación staff
3. [ ] Verificar que response incluye `workflow.status_history`
4. [ ] Verificar que cada item en `status_history` tiene:
   - [ ] Campo `metadata` (puede ser `{}` o con datos)
   - [ ] Campo `ip_address` (puede ser null)
   - [ ] Campo `user_agent` (puede ser null)
   - [ ] Campo `is_lifecycle_event` (boolean)
   - [ ] Campo `event_type` (string)

**Ejemplo de respuesta esperada:**
```json
{
  "workflow": {
    "status_history": [
      {
        "from_status": "DRAFT",
        "from_status_label": "Borrador",
        "to_status": "SUBMITTED",
        "to_status_label": "Enviada",
        "changed_by": "Juan Pérez",
        "notes": null,
        "created_at": "2026-01-22T10:00:00Z",
        "is_lifecycle_event": false,
        "event_type": "STATUS_CHANGE",
        "event_label": null,
        "ip_address": "192.168.1.1",
        "user_agent": "Mozilla/5.0...",
        "metadata": {
          "ip_address": "192.168.1.1",
          "user_agent": "Mozilla/5.0..."
        }
      }
    ]
  }
}
```

### Test 3.2: Verificar diferentes tipos de eventos

**Eventos a probar:**
1. [ ] **Status change normal** (DRAFT → SUBMITTED)
   - Debe tener: ip_address, user_agent
   - Botón "Ver detalles": ✅ SI (si hay metadata)

2. [ ] **Document upload** (lifecycle event)
   - Debe tener: document_type, ip_address, user_agent
   - Botón "Ver detalles": ✅ SI

3. [ ] **Profile update** (lifecycle event)
   - Debe tener: changed_fields, old_value, new_value
   - Botón "Ver detalles": ✅ SI

4. [ ] **KYC validation** (lifecycle event)
   - Debe tener: is_valid, score, matched
   - Botón "Ver detalles": ✅ SI

5. [ ] **Evento sin metadata**
   - metadata: {}
   - ip_address: null
   - user_agent: null
   - Botón "Ver detalles": ❌ NO

---

## 🔍 Fase 4: Pruebas de UI/UX

### Test 4.1: Verificar visibilidad del botón
**Ubicación:** Admin Panel → Solicitud → Tab "Historial"

**Pasos:**
1. [ ] Login como staff (admin, supervisor o analyst)
2. [ ] Navegar a `/admin/solicitudes/{id}`
3. [ ] Click en tab "Historial"
4. [ ] Verificar que eventos con metadata muestran botón "Ver detalles"
5. [ ] Verificar que eventos sin metadata NO muestran botón

**Criterios:**
- [ ] Botón tiene color `text-primary-600`
- [ ] Botón tiene hover effect `hover:text-primary-800`
- [ ] Botón tiene ícono de información (círculo con "i")
- [ ] Botón está alineado a la derecha

### Test 4.2: Verificar funcionalidad del modal
**Pasos:**
1. [ ] Click en botón "Ver detalles"
2. [ ] Verificar que modal se abre
3. [ ] Verificar que modal muestra:
   - [ ] Título del evento
   - [ ] Fecha/hora
   - [ ] IP address (si existe)
   - [ ] User agent (si existe)
   - [ ] Metadata adicional según tipo de evento
4. [ ] Verificar que modal se puede cerrar
5. [ ] Verificar que no hay errores en consola

### Test 4.3: Verificar responsive design
**Pasos:**
1. [ ] Probar en desktop (1920x1080)
2. [ ] Probar en tablet (768x1024)
3. [ ] Probar en mobile (375x667)
4. [ ] Verificar que botón es visible en todas las resoluciones
5. [ ] Verificar que modal es funcional en todas las resoluciones

---

## 🔍 Fase 5: Edge Cases y Validación

### Test 5.1: Metadata parcial
**Escenario:** Evento tiene solo `ip_address` pero no `user_agent`

**Esperado:**
- [ ] Botón "Ver detalles" se muestra (porque `ip_address` existe)
- [ ] Modal muestra IP address
- [ ] Modal muestra "No disponible" para user agent

### Test 5.2: Metadata con campos custom
**Escenario:** Evento tiene metadata con campos no estándar

**Esperado:**
- [ ] Frontend no crashea
- [ ] Metadata completo está disponible en el objeto
- [ ] Modal puede mostrar campos custom

### Test 5.3: Evento lifecycle sin metadata
**Escenario:** Evento es lifecycle pero metadata está vacío

**Esperado:**
- [ ] `is_lifecycle_event` = true
- [ ] `event_type` tiene valor correcto
- [ ] `event_label` tiene valor correcto
- [ ] Botón NO se muestra (porque metadata está vacío)

### Test 5.4: Performance con muchos eventos
**Escenario:** Aplicación tiene 50+ eventos en historial

**Esperado:**
- [ ] Timeline renderiza en < 2 segundos
- [ ] No hay lag al scroll
- [ ] Botones funcionan correctamente
- [ ] No hay memory leaks

---

## 🔍 Fase 6: Validación de Datos

### Test 6.1: Verificar que ApplicationStatusHistory guarda metadata
**Query SQL:**
```sql
SELECT id, from_status, to_status, metadata, created_at
FROM application_status_history
WHERE metadata IS NOT NULL
LIMIT 10;
```

**Esperado:**
- [ ] Registros existen con metadata no nulo
- [ ] Metadata es JSON válido
- [ ] Metadata contiene campos como `ip_address`, `user_agent`

### Test 6.2: Verificar que eventos lifecycle tienen metadata
**Query SQL:**
```sql
SELECT from_status, metadata
FROM application_status_history
WHERE from_status LIKE 'EVENT_%'
LIMIT 10;
```

**Esperado:**
- [ ] Eventos lifecycle tienen metadata poblado
- [ ] Metadata incluye campos específicos del evento

---

## 📊 Resultados Esperados

### Backend
```php
✅ ApplicationController.php líneas 787-809: Mapping correcto
✅ ApplicationEventService import: Presente
✅ Todos los campos metadata incluidos
✅ Lógica lifecycle event implementada
```

### Frontend
```typescript
✅ hasDetailableMetadata() implementado correctamente
✅ Botón renderiza con v-if correcto
✅ Modal funcional
✅ No errores en consola
```

### API Response
```json
✅ status_history incluye metadata
✅ ip_address extraído correctamente
✅ user_agent extraído correctamente
✅ is_lifecycle_event calculado
✅ event_type y event_label presentes
```

---

## 🚨 Criterios de Fallo

Si alguno de estos ocurre, la prueba FALLA:

1. ❌ Backend no incluye campo `metadata` en response
2. ❌ Backend no extrae `ip_address` del metadata
3. ❌ Frontend no muestra botón cuando metadata existe
4. ❌ Frontend muestra botón cuando metadata está vacío
5. ❌ Modal no se abre al hacer click
6. ❌ Modal muestra datos incorrectos o vacíos
7. ❌ Errores en consola del navegador
8. ❌ Errores 500 en backend

---

## 📝 Checklist Final

### Pre-deployment
- [ ] Todas las pruebas de Fase 1 pasadas
- [ ] Todas las pruebas de Fase 2 pasadas
- [ ] Todas las pruebas de Fase 3 pasadas
- [ ] Todas las pruebas de Fase 4 pasadas
- [ ] Todas las pruebas de Fase 5 pasadas
- [ ] Todas las pruebas de Fase 6 pasadas
- [ ] No hay errores en logs de Laravel
- [ ] No hay errores en consola de navegador
- [ ] Performance aceptable (< 2s render)
- [ ] Responsive design funcional
- [ ] Code review completado
- [ ] Git commit con mensaje descriptivo

---

**Estado:** ⏳ PENDING EXECUTION
**Next Step:** Ejecutar Fase 1 - Verificación de Código Backend
