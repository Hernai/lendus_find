# 🧪 Plan de Pruebas COMPLETO: Document Architecture Implementation

**Fecha:** 2026-01-22
**Feature:** Complete Document Management Architecture
**Tester:** Claude (TOC Mode - Trastorno Obsesivo Compulsivo) ✅
**Scope:** END-TO-END testing de toda la arquitectura de documentos

---

## 📋 Índice de Pruebas

1. [Backend: Database Schema](#fase-1-backend-database-schema)
2. [Backend: Document Model Methods](#fase-2-backend-document-model-methods)
3. [Backend: Controllers](#fase-3-backend-controllers)
4. [Backend: Services](#fase-4-backend-services)
5. [Backend: API Endpoints](#fase-5-backend-api-endpoints)
6. [Frontend: Document Upload Flow](#fase-6-frontend-document-upload-flow)
7. [Frontend: Document Replacement Flow](#fase-7-frontend-document-replacement-flow)
8. [Frontend: Document History UI](#fase-8-frontend-document-history-ui)
9. [Integration: Complete User Journey](#fase-9-integration-complete-user-journey)
10. [Performance & Security](#fase-10-performance--security)
11. [Edge Cases & Error Handling](#fase-11-edge-cases--error-handling)

---

## 🎯 Objetivo General

Verificar exhaustivamente que TODA la arquitectura de documentos funciona correctamente:
- ✅ Active Document Pattern (único documento activo por tipo)
- ✅ Temporal Validity (valid_from, valid_to)
- ✅ Supersession Chain (recursive CTEs)
- ✅ Documentable Relations (polymorphic OWNERSHIP/USAGE)
- ✅ Transaction Safety (todas las operaciones críticas)
- ✅ Document History (audit trail completo)
- ✅ Frontend Integration (upload, replace, history)

---

## 🔍 FASE 1: Backend - Database Schema

### Test 1.1: Verificar Estructura de Tabla `documents`

**Query:**
```sql
SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_name = 'documents'
AND column_name IN ('is_active', 'valid_from', 'valid_to', 'superseded_by_id')
ORDER BY ordinal_position;
```

**Checklist:**
- [ ] Campo `is_active` existe (tipo: boolean, default: true)
- [ ] Campo `valid_from` existe (tipo: timestamp, nullable)
- [ ] Campo `valid_to` existe (tipo: timestamp, nullable)
- [ ] Campo `superseded_by_id` existe (tipo: uuid, nullable)

### Test 1.2: Verificar Indexes de Performance

**Query:**
```sql
SELECT indexname, indexdef
FROM pg_indexes
WHERE tablename = 'documents'
AND (indexname LIKE 'idx_%' OR indexname = 'unique_active_document')
ORDER BY indexname;
```

**Checklist:**
- [ ] Index `idx_active_documents` existe (documentable_type, documentable_id, type, is_active)
- [ ] Index `idx_temporal_validity` existe (valid_from, valid_to)
- [ ] Index `idx_supersession_chain` existe (superseded_by_id)
- [ ] Index `unique_active_document` existe (partial unique WHERE is_active = true)

### Test 1.3: Verificar Foreign Keys

**Query:**
```sql
SELECT
    tc.constraint_name,
    tc.table_name,
    kcu.column_name,
    ccu.table_name AS foreign_table_name,
    ccu.column_name AS foreign_column_name,
    rc.delete_rule
FROM information_schema.table_constraints AS tc
JOIN information_schema.key_column_usage AS kcu
  ON tc.constraint_name = kcu.constraint_name
JOIN information_schema.constraint_column_usage AS ccu
  ON ccu.constraint_name = tc.constraint_name
JOIN information_schema.referential_constraints AS rc
  ON rc.constraint_name = tc.constraint_name
WHERE tc.table_name = 'documents'
AND tc.constraint_type = 'FOREIGN KEY'
AND kcu.column_name = 'superseded_by_id';
```

**Checklist:**
- [ ] Foreign key `superseded_by_id` → `documents(id)` existe
- [ ] Delete rule es `SET NULL` (nullOnDelete)

### Test 1.4: Verificar Tabla `documentable_relations`

**Query:**
```sql
SELECT column_name, data_type, is_nullable
FROM information_schema.columns
WHERE table_name = 'documentable_relations'
ORDER BY ordinal_position;
```

**Checklist:**
- [ ] Campo `id` (uuid, PK)
- [ ] Campo `tenant_id` (uuid, NOT NULL)
- [ ] Campo `document_id` (uuid, FK a documents)
- [ ] Campo `relatable_type` (varchar, polymorphic)
- [ ] Campo `relatable_id` (uuid, polymorphic)
- [ ] Campo `relation_context` (varchar: OWNERSHIP, USAGE, REFERENCE)
- [ ] Campo `notes` (text, nullable)
- [ ] Campo `created_by` (uuid, nullable)
- [ ] Campo `created_by_type` (varchar, nullable)
- [ ] Timestamps (created_at, updated_at, deleted_at)

### Test 1.5: Verificar Unique Constraint en documentable_relations

**Query:**
```sql
SELECT constraint_name, constraint_type
FROM information_schema.table_constraints
WHERE table_name = 'documentable_relations'
AND constraint_type = 'UNIQUE';
```

**Checklist:**
- [ ] Unique constraint en (document_id, relatable_type, relatable_id, relation_context)

### Test 1.6: Verificar Data Integrity (NO duplicates)

**Query:**
```sql
-- Check for duplicate active documents
SELECT documentable_type, documentable_id, type, COUNT(*) as active_count
FROM documents
WHERE is_active = true
GROUP BY documentable_type, documentable_id, type
HAVING COUNT(*) > 1;
```

**Esperado:**
- [ ] Resultado: 0 rows (NO duplicates)

**Query:**
```sql
-- Check for orphaned supersession references
SELECT COUNT(*)
FROM documents
WHERE superseded_by_id IS NOT NULL
AND superseded_by_id NOT IN (SELECT id FROM documents);
```

**Esperado:**
- [ ] Resultado: 0 (NO orphans)

**Query:**
```sql
-- Check for orphaned documentable_relations
SELECT COUNT(*)
FROM documentable_relations
WHERE document_id NOT IN (SELECT id FROM documents);
```

**Esperado:**
- [ ] Resultado: 0 (NO orphans)

---

## 🔍 FASE 2: Backend - Document Model Methods

### Test 2.1: Método `activate()`

**Test Code:**
```php
// Test en tinker o test unitario
$person = Person::first();

// Upload 3 documents of same type
$doc1 = Document::create([
    'tenant_id' => $person->tenant_id,
    'documentable_type' => 'App\\Models\\Person',
    'documentable_id' => $person->id,
    'type' => 'PROOF_OF_ADDRESS',
    'file_name' => 'luz_v1.pdf',
    'file_path' => 's3://test/luz_v1.pdf',
    'status' => 'PENDING',
    'is_active' => true,
]);

$doc2 = Document::create([
    'tenant_id' => $person->tenant_id,
    'documentable_type' => 'App\\Models\\Person',
    'documentable_id' => $person->id,
    'type' => 'PROOF_OF_ADDRESS',
    'file_name' => 'luz_v2.pdf',
    'file_path' => 's3://test/luz_v2.pdf',
    'status' => 'PENDING',
    'is_active' => true,
]);

// Call activate() on doc2
$doc2->activate();

// Verify
$doc1->refresh();
$doc2->refresh();

assert($doc1->is_active === false, 'doc1 should be deactivated');
assert($doc1->valid_to !== null, 'doc1 should have valid_to set');
assert($doc2->is_active === true, 'doc2 should remain active');
assert($doc2->valid_to === null, 'doc2 should have no valid_to');
```

**Checklist:**
- [ ] Solo `$doc2` queda con `is_active = true`
- [ ] `$doc1` tiene `is_active = false`
- [ ] `$doc1` tiene `valid_to` set a now()
- [ ] `$doc2` tiene `valid_to = NULL`
- [ ] No hay otros documentos activos del mismo tipo

### Test 2.2: Método `supersedeWith()`

**Test Code:**
```php
$oldDoc = Document::where('type', 'PROOF_OF_ADDRESS')->first();
$newDoc = Document::create([
    'tenant_id' => $oldDoc->tenant_id,
    'documentable_type' => $oldDoc->documentable_type,
    'documentable_id' => $oldDoc->documentable_id,
    'type' => 'PROOF_OF_ADDRESS',
    'file_name' => 'luz_new.pdf',
    'file_path' => 's3://test/luz_new.pdf',
    'status' => 'PENDING',
]);

$oldDoc->supersedeWith($newDoc, 'Updated document');

$oldDoc->refresh();
$newDoc->refresh();

assert($oldDoc->superseded_by_id === $newDoc->id, 'superseded_by_id should point to new doc');
assert($oldDoc->status === 'SUPERSEDED', 'old doc should be SUPERSEDED');
assert($oldDoc->is_active === false, 'old doc should be inactive');
assert($oldDoc->valid_to !== null, 'old doc should have valid_to');
assert($newDoc->is_active === true, 'new doc should be active');
assert($newDoc->valid_to === null, 'new doc should have no valid_to');
```

**Checklist:**
- [ ] `$oldDoc->superseded_by_id` apunta a `$newDoc->id`
- [ ] `$oldDoc->status` es `SUPERSEDED`
- [ ] `$oldDoc->is_active` es `false`
- [ ] `$oldDoc->valid_to` está set
- [ ] `$oldDoc->replacement_reason` es 'Updated document'
- [ ] `$newDoc->is_active` es `true`
- [ ] `$newDoc` es el único activo de ese tipo
- [ ] Log entry creado

### Test 2.3: Método `getSupersessionChain()` (Recursive CTE)

**Test Code:**
```php
// Create chain: doc1 → doc2 → doc3 → doc4
$doc1 = Document::create([...]); // No superseded_by_id
$doc2 = Document::create([...]);
$doc3 = Document::create([...]);
$doc4 = Document::create([...]);

$doc1->supersedeWith($doc2, 'v2');
$doc2->supersedeWith($doc3, 'v3');
$doc3->supersedeWith($doc4, 'v4');

// Get chain starting from doc1
$start = microtime(true);
$chain = $doc1->getSupersessionChain();
$end = microtime(true);
$time = ($end - $start) * 1000; // ms

assert($chain->count() === 4, 'Chain should have 4 documents');
assert($chain[0]->id === $doc1->id, 'First should be doc1');
assert($chain[3]->id === $doc4->id, 'Last should be doc4');
assert($time < 10, 'Should execute in < 10ms'); // Performance check
```

**Checklist:**
- [ ] Chain incluye todos los documentos (4)
- [ ] Chain está en orden correcto (oldest → newest)
- [ ] Query ejecuta en < 10ms (single CTE query)
- [ ] No hay N+1 queries (verificar con query log)

### Test 2.4: Método `getReverseSupersessionChain()`

**Test Code:**
```php
// Using same chain from Test 2.3
$chain = $doc4->getReverseSupersessionChain();

assert($chain->count() === 4, 'Reverse chain should have 4 documents');
assert($chain[0]->id === $doc4->id, 'First should be doc4');
assert($chain[3]->id === $doc1->id, 'Last should be doc1');
```

**Checklist:**
- [ ] Reverse chain incluye todos (4)
- [ ] Reverse chain está en orden inverso (newest → oldest)
- [ ] Query ejecuta en < 10ms

### Test 2.5: Método `getCompleteHistoryChain()`

**Test Code:**
```php
// Start from middle document (doc2)
$chain = $doc2->getCompleteHistoryChain();

assert($chain->count() === 4, 'Complete chain should have all 4');
assert($chain->unique('id')->count() === 4, 'Should not have duplicates');
```

**Checklist:**
- [ ] Complete chain incluye forward y backward
- [ ] No hay duplicados
- [ ] Orden es correcto

### Test 2.6: Scopes (isActive, currentlyValid, validAt)

**Test Code:**
```php
// Test isActive scope
$active = Document::isActive()->count();
// Should match manual count
$manualActive = Document::where('is_active', true)->count();
assert($active === $manualActive, 'isActive scope should match');

// Test currentlyValid scope
$valid = Document::currentlyValid()->count();
// Should only include documents where:
// valid_from <= now AND (valid_to IS NULL OR valid_to >= now)

// Test validAt scope with specific date
$pastDate = Carbon::parse('2026-01-01');
$validAtDate = Document::validAt($pastDate)->get();
// Verify each document was valid at that date
foreach ($validAtDate as $doc) {
    assert($doc->valid_from <= $pastDate, 'valid_from should be <= date');
    assert($doc->valid_to === null || $doc->valid_to >= $pastDate, 'valid_to should be null or >= date');
}
```

**Checklist:**
- [ ] Scope `isActive()` funciona
- [ ] Scope `currentlyValid()` funciona
- [ ] Scope `validAt($date)` funciona con fecha específica
- [ ] Scope `superseded()` funciona
- [ ] Scope `notSuperseded()` funciona

---

## 🔍 FASE 3: Backend - Controllers

### Test 3.1: DocumentController - Upload Nuevo Documento

**Endpoint:** `POST /api/v2/applicant/documents`

**Request:**
```json
{
  "type": "PROOF_OF_ADDRESS",
  "file": "<binary>",
  "application_id": "uuid-of-application" // optional
}
```

**Test Steps:**
1. [ ] Upload documento como applicant
2. [ ] Verificar response 200
3. [ ] Verificar que documento se creó con `is_active = true`
4. [ ] Verificar que `valid_from` está set
5. [ ] Verificar que `valid_to` es NULL
6. [ ] Si hay application_id, verificar que se creó documentable_relation USAGE
7. [ ] Verificar que log entry fue creado

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "type": "PROOF_OF_ADDRESS",
    "status": "PENDING",
    "is_active": true,
    "valid_from": "2026-01-22T10:00:00Z",
    "valid_to": null,
    "created_at": "2026-01-22T10:00:00Z"
  }
}
```

### Test 3.2: DocumentController - Reemplazar Documento Existente

**Scenario:** Usuario sube nuevo PROOF_OF_ADDRESS cuando ya existe uno activo

**Test Steps:**
1. [ ] Subir documento tipo A (doc1)
2. [ ] Verificar doc1 está `is_active = true`
3. [ ] Subir nuevo documento tipo A (doc2)
4. [ ] Verificar que:
   - [ ] doc2 está `is_active = true`
   - [ ] doc1 está `is_active = false`
   - [ ] doc1.superseded_by_id = doc2.id
   - [ ] doc1.status = 'SUPERSEDED'
   - [ ] doc1.valid_to está set
   - [ ] doc2.valid_to es NULL

### Test 3.3: DocumentController - attachDocumentToApplication

**Test Steps:**
1. [ ] Crear aplicación
2. [ ] Subir documento sin application_id
3. [ ] Attach documento a aplicación
4. [ ] Verificar que documentable_relation USAGE fue creada
5. [ ] Verificar que relación tiene tenant_id correcto
6. [ ] Verificar que si ya existe relación, se actualiza (no duplica)
7. [ ] Verificar transaction safety (simular error y verificar rollback)

### Test 3.4: DocumentHistoryController - GET /documents/history/{type}

**Endpoint:** `GET /api/v2/applicant/documents/history/PROOF_OF_ADDRESS`

**Test Steps:**
1. [ ] Upload 5 documentos del mismo tipo
2. [ ] Algunos aprobados, algunos rechazados
3. [ ] Call endpoint
4. [ ] Verificar response incluye:
   - [ ] Lista completa de documentos (5)
   - [ ] Ordenados por created_at desc
   - [ ] Cada uno con campos: id, status, is_active, valid_from, valid_to, superseded_by_id
   - [ ] Incluye applications donde se usó cada documento
5. [ ] Verificar input sanitization (uppercase, trim)
6. [ ] Verificar que tipo inválido retorna 400

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "type": "PROOF_OF_ADDRESS",
    "type_label": "Comprobante de Domicilio",
    "documents": [
      {
        "id": "uuid",
        "status": "APPROVED",
        "is_active": true,
        "is_currently_valid": true,
        "valid_from": "2026-01-22T10:00:00Z",
        "valid_to": null,
        "superseded_by_id": null,
        "applications": [
          {"id": "app-uuid", "folio": "2026-001", "status": "IN_REVIEW"}
        ]
      },
      ...
    ],
    "total": 5
  }
}
```

### Test 3.5: DocumentHistoryController - GET /documents/{id}/supersession-chain

**Endpoint:** `GET /api/v2/applicant/documents/{id}/supersession-chain`

**Test Steps:**
1. [ ] Crear chain de 4 documentos
2. [ ] Call endpoint con ID del documento en medio del chain
3. [ ] Verificar response incluye:
   - [ ] Chain completo (4 documentos)
   - [ ] Orden correcto
   - [ ] Cada documento con todos los campos
   - [ ] Flag `is_current` para el documento solicitado
4. [ ] Verificar ownership validation (solo owner puede ver)
5. [ ] Verificar tenant scoping

### Test 3.6: DocumentHistoryController - GET /documents/valid-at

**Endpoint:** `GET /api/v2/applicant/documents/valid-at?date=2026-01-15&type=PROOF_OF_ADDRESS`

**Test Steps:**
1. [ ] Crear documentos con diferentes valid_from/valid_to
2. [ ] Query con fecha específica
3. [ ] Verificar que solo retorna documentos válidos en esa fecha
4. [ ] Probar con tipo específico y sin tipo
5. [ ] Verificar validación de fecha

### Test 3.7: DocumentHistoryController - GET /documents/timeline

**Endpoint:** `GET /api/v2/applicant/documents/timeline?type=PROOF_OF_ADDRESS`

**Test Steps:**
1. [ ] Crear documentos y aplicaciones
2. [ ] Call endpoint
3. [ ] Verificar timeline incluye:
   - [ ] DOCUMENT_UPLOAD events
   - [ ] DOCUMENT_APPROVED events
   - [ ] DOCUMENT_REJECTED events
   - [ ] DOCUMENT_SUPERSEDED events
   - [ ] DOCUMENT_USED_IN_APPLICATION events
4. [ ] Verificar orden cronológico (más reciente primero)
5. [ ] Verificar que incluye info de applications

---

## 🔍 FASE 4: Backend - Services

### Test 4.1: ApplicationDocumentSnapshotService - createSnapshot

**Test Code:**
```php
$service = app(ApplicationDocumentSnapshotService::class);
$application = Application::first();
$person = $application->person;

// Upload 3 active documents
Document::create([...type A, is_active=true, currentlyValid]);
Document::create([...type B, is_active=true, currentlyValid]);
Document::create([...type C, is_active=false]); // Inactive - should NOT snapshot

$count = $service->createSnapshot($application, 'SUBMISSION');

assert($count === 2, 'Should snapshot only 2 active docs');

// Verify USAGE relations created
$usageCount = DB::table('documentable_relations')
    ->where('relatable_type', 'App\\Models\\Application')
    ->where('relatable_id', $application->id)
    ->where('relation_context', 'USAGE')
    ->whereNull('deleted_at')
    ->count();

assert($usageCount === 2, 'Should have 2 USAGE relations');

// Verify OWNERSHIP relations created
$ownershipCount = DB::table('documentable_relations')
    ->where('relatable_type', 'App\\Models\\Person')
    ->where('relatable_id', $person->id)
    ->where('relation_context', 'OWNERSHIP')
    ->whereNull('deleted_at')
    ->count();

assert($ownershipCount >= 2, 'Should have OWNERSHIP relations');
```

**Checklist:**
- [ ] Solo snapshots de documentos `is_active = true`
- [ ] Solo snapshots de documentos `currentlyValid()`
- [ ] Crea relaciones OWNERSHIP si no existen
- [ ] Crea relaciones USAGE
- [ ] Wrapped en transaction
- [ ] No duplica relaciones
- [ ] Context APPROVAL actualiza relaciones existentes

### Test 4.2: ApplicationDocumentSnapshotService - hasAllRequiredDocuments

**Test Code:**
```php
$product = Product::first();
$product->required_documents = ['PROOF_OF_ADDRESS', 'PAYSLIP', 'INE_FRONT'];
$product->save();

$application = Application::create([
    'product_id' => $product->id,
    ...
]);

// Attach 2 of 3 required docs
// Create USAGE relations for PROOF_OF_ADDRESS and PAYSLIP only

$hasAll = $service->hasAllRequiredDocuments($application);
assert($hasAll === false, 'Should return false when missing docs');

// Attach third document (INE_FRONT)
// Create USAGE relation

$hasAll = $service->hasAllRequiredDocuments($application);
assert($hasAll === true, 'Should return true when all docs attached');
```

**Checklist:**
- [ ] Retorna `false` cuando faltan documentos
- [ ] Retorna `true` cuando todos están attached
- [ ] Usa documentable_relations (no application_documents deprecada)
- [ ] Verifica solo relaciones USAGE no soft-deleted

### Test 4.3: ApplicationDocumentSnapshotService - getMissingDocumentTypes

**Test Code:**
```php
$missing = $service->getMissingDocumentTypes($application);

assert(in_array('INE_FRONT', $missing), 'INE_FRONT should be missing');
assert(count($missing) === 1, 'Should have 1 missing doc');
```

**Checklist:**
- [ ] Retorna array de tipos faltantes
- [ ] Array vacío cuando todos están attached
- [ ] Usa documentable_relations correctamente

---

## 🔍 FASE 5: Backend - API Endpoints Integration

### Test 5.1: Complete Document Upload Flow

**Steps:**
1. [ ] Login como applicant
2. [ ] POST /api/v2/applicant/documents con archivo
3. [ ] Verificar response 200
4. [ ] Verificar documento en BD
5. [ ] Verificar is_active = true
6. [ ] Verificar archivo en S3/storage

### Test 5.2: Complete Document Replacement Flow

**Steps:**
1. [ ] Subir documento A
2. [ ] Verificar A es activo
3. [ ] Subir documento B (mismo tipo)
4. [ ] Verificar B es activo
5. [ ] Verificar A es inactivo
6. [ ] Verificar A.superseded_by_id = B.id
7. [ ] GET /documents/history/{type} y verificar ambos en historial

### Test 5.3: Complete Application Submission Flow

**Steps:**
1. [ ] Crear aplicación
2. [ ] Subir 3 documentos requeridos
3. [ ] POST /api/v2/applicant/applications/{id}/submit
4. [ ] Verificar que snapshot se creó
5. [ ] Verificar documentable_relations USAGE creadas
6. [ ] Verificar que aplicación cambió status

---

## 🔍 FASE 6: Frontend - Document Upload Flow

### Test 6.1: Verificar Componente Step6Documents.vue

**Ubicación:** `/frontend/src/views/applicant/onboarding/Step6Documents.vue`

**Checklist:**
- [ ] Componente carga lista de documentos requeridos
- [ ] Muestra botón "Subir" para cada tipo
- [ ] Input file funciona
- [ ] Preview de imagen funciona
- [ ] Progress bar durante upload
- [ ] Muestra documento subido después de success
- [ ] Muestra botón "Reemplazar" si ya existe documento
- [ ] Maneja errores de upload (muestra mensaje)

### Test 6.2: Flujo de Upload en UI

**Steps:**
1. [ ] Navegar a Step 6 (Documentos) en onboarding
2. [ ] Click en "Subir PROOF_OF_ADDRESS"
3. [ ] Seleccionar archivo (PDF o imagen)
4. [ ] Verificar preview
5. [ ] Verificar progress bar
6. [ ] Verificar success message
7. [ ] Verificar que documento aparece como "Subido"
8. [ ] Verificar que botón cambia a "Reemplazar"

### Test 6.3: Validación de Archivos

**Test Cases:**
- [ ] Archivo muy grande (> 5MB) → Rechazado con error
- [ ] Formato inválido (.exe, .zip) → Rechazado con error
- [ ] PDF válido → Aceptado
- [ ] JPG válido → Aceptado
- [ ] PNG válido → Aceptado

---

## 🔍 FASE 7: Frontend - Document Replacement Flow

### Test 7.1: Reemplazar Documento Existente

**Steps:**
1. [ ] Ya hay documento PROOF_OF_ADDRESS subido y aprobado
2. [ ] Navegar a Step 6
3. [ ] Verificar que muestra documento existente
4. [ ] Verificar que botón dice "Reemplazar"
5. [ ] Click en "Reemplazar"
6. [ ] Subir nuevo documento
7. [ ] Verificar que reemplaza el anterior
8. [ ] Verificar que documento anterior queda en historial

### Test 7.2: Advertencia al Reemplazar

**Steps:**
1. [ ] Documento actual está "APPROVED"
2. [ ] Click en "Reemplazar"
3. [ ] Verificar que muestra modal de confirmación:
   - "Tu documento actual está aprobado. ¿Estás seguro de reemplazarlo?"
4. [ ] Click "Cancelar" → No hace nada
5. [ ] Click "Reemplazar" → Procede con upload

---

## 🔍 FASE 8: Frontend - Document History UI

### Test 8.1: Verificar Componente TimelineSection.vue

**Ubicación:** `/frontend/src/components/admin/application-detail/TimelineSection.vue`

**Checklist:**
- [ ] Timeline muestra eventos cronológicos
- [ ] Eventos de documento incluyen:
   - [ ] DOCUMENT_UPLOAD
   - [ ] DOCUMENT_APPROVED
   - [ ] DOCUMENT_REJECTED
   - [ ] DOCUMENT_SUPERSEDED
   - [ ] DOCUMENT_USED_IN_APPLICATION
- [ ] Cada evento muestra:
   - [ ] Fecha/hora
   - [ ] Usuario que lo creó
   - [ ] Tipo de documento
   - [ ] Status
- [ ] Eventos con metadata muestran botón "Ver detalles"
- [ ] Click en "Ver detalles" abre modal con metadata

### Test 8.2: Verificar Modal de Detalles

**Steps:**
1. [ ] Timeline tiene evento con metadata (document upload)
2. [ ] Verificar botón "Ver detalles" visible
3. [ ] Click en botón
4. [ ] Modal se abre
5. [ ] Modal muestra:
   - [ ] Título del evento
   - [ ] Fecha/hora
   - [ ] IP address (si existe)
   - [ ] User agent (si existe)
   - [ ] Document type
   - [ ] File name
   - [ ] Metadata adicional relevante
6. [ ] Click fuera del modal o en X → cierra modal

### Test 8.3: Document History View (Nueva UI)

**Ubicación:** `/frontend/src/components/admin/application-detail/DocumentHistoryView.vue` (si existe)

**Checklist:**
- [ ] Muestra lista de todos los documentos de un tipo
- [ ] Ordenados por fecha (más reciente primero)
- [ ] Cada documento muestra:
   - [ ] File name
   - [ ] Status (badge con color)
   - [ ] Fecha de subida
   - [ ] Si es activo (badge "Activo")
   - [ ] Si fue rechazado (razón de rechazo)
   - [ ] Si fue reemplazado (link al siguiente)
- [ ] Muestra estadísticas:
   - [ ] Total de versiones
   - [ ] Aprobadas
   - [ ] Rechazadas
- [ ] Visualización de supersession chain (opcional)

---

## 🔍 FASE 9: Integration - Complete User Journey

### Test 9.1: Journey del Applicant - Primera Solicitud

**Scenario:** Usuario nuevo crea primera solicitud

**Steps:**
1. [ ] Usuario se registra y completa perfil
2. [ ] Navega a "Nueva Solicitud"
3. [ ] Completa Steps 1-5
4. [ ] Llega a Step 6 (Documentos)
5. [ ] Sube 3 documentos requeridos:
   - [ ] PROOF_OF_ADDRESS
   - [ ] PAYSLIP
   - [ ] INE_FRONT
6. [ ] Verifica preview de cada documento
7. [ ] Click "Enviar Solicitud"
8. [ ] Verificar en BD:
   - [ ] 3 documentos creados con is_active = true
   - [ ] Application status = SUBMITTED
   - [ ] 3 documentable_relations USAGE creadas
   - [ ] 3 documentable_relations OWNERSHIP creadas
   - [ ] ApplicationStatusHistory creado

### Test 9.2: Journey del Applicant - Reemplazo de Documento

**Scenario:** Analista rechaza documento, applicant sube nuevo

**Steps:**
1. [ ] Analista rechaza PROOF_OF_ADDRESS con razón "Documento ilegible"
2. [ ] Applicant recibe notificación
3. [ ] Applicant navega a su Dashboard
4. [ ] Ve que documento fue rechazado
5. [ ] Click "Reemplazar"
6. [ ] Sube nuevo documento
7. [ ] Verificar en BD:
   - [ ] Documento viejo: status = REJECTED, is_active = false, superseded_by_id = nuevo
   - [ ] Documento nuevo: status = PENDING, is_active = true
   - [ ] ApplicationStatusHistory registra el evento

### Test 9.3: Journey del Analista - Revisión de Documentos

**Scenario:** Analista revisa documentos de una solicitud

**Steps:**
1. [ ] Analista login
2. [ ] Ve lista de solicitudes asignadas
3. [ ] Click en solicitud
4. [ ] Navega a tab "Documentos"
5. [ ] Ve lista de documentos con:
   - [ ] Thumbnail/preview
   - [ ] Tipo de documento
   - [ ] Status
   - [ ] Fecha de subida
6. [ ] Click en documento para ver full size
7. [ ] Decide aprobar o rechazar
8. [ ] Click "Aprobar" para PROOF_OF_ADDRESS
9. [ ] Click "Rechazar" para PAYSLIP con razón
10. [ ] Verificar en BD:
    - [ ] Documentos actualizados
    - [ ] Status history registrado
    - [ ] Metadata incluye reviewer_id

### Test 9.4: Journey del Analista - Ver Historial

**Scenario:** Analista quiere ver historial de reemplazos de un documento

**Steps:**
1. [ ] Analista en vista de solicitud
2. [ ] Click en documento PROOF_OF_ADDRESS
3. [ ] Click "Ver Historial"
4. [ ] Ve timeline con:
   - [ ] Versión 1: Subida el 15-ene, Rechazada
   - [ ] Versión 2: Subida el 16-ene, Aprobada
   - [ ] Versión 3: Subida el 20-ene, Activa (reemplazo voluntario)
5. [ ] Click en cualquier versión → Ve detalle completo
6. [ ] Ve supersession chain visualizado

### Test 9.5: Journey del Auditor - Query Histórica

**Scenario:** Auditor CNBV pregunta "¿Qué documento usó Juan en su solicitud de enero?"

**Steps:**
1. [ ] Staff con rol SUPER_ADMIN login
2. [ ] Navega a herramienta de auditoría
3. [ ] Busca solicitud por folio o persona
4. [ ] Ve snapshot de documentos usados en esa solicitud específica
5. [ ] Documentos mostrados son los que estaban activos en fecha de submission
6. [ ] Puede ver:
   - [ ] Qué documento se usó (ID, file_name)
   - [ ] Qué status tenía en ese momento
   - [ ] Si luego fue reemplazado (no afecta la solicitud)

---

## 🔍 FASE 10: Performance & Security

### Test 10.1: Performance - Supersession Chain Query

**Test:**
```php
// Chain de 100 documentos (edge case extremo)
$start = microtime(true);
$chain = $firstDoc->getSupersessionChain();
$end = microtime(true);
$time = ($end - $start) * 1000;

assert($time < 50, 'Chain of 100 docs should execute in < 50ms');
```

**Checklist:**
- [ ] Chain de 10 docs: < 10ms
- [ ] Chain de 100 docs: < 50ms
- [ ] 1 query (no N+1)

### Test 10.2: Performance - Active Documents Query

**Test:**
```sql
-- Query con 1000 documentos en BD
EXPLAIN ANALYZE
SELECT * FROM documents
WHERE documentable_type = 'App\Models\Person'
AND documentable_id = 'uuid'
AND type = 'PROOF_OF_ADDRESS'
AND is_active = true;
```

**Checklist:**
- [ ] Usa index `idx_active_documents`
- [ ] No hace seq scan
- [ ] Execution time < 5ms

### Test 10.3: Performance - Temporal Validity Query

**Test:**
```sql
EXPLAIN ANALYZE
SELECT * FROM documents
WHERE valid_from <= NOW()
AND (valid_to IS NULL OR valid_to >= NOW());
```

**Checklist:**
- [ ] Usa index `idx_temporal_validity`
- [ ] Execution time < 10ms con 1000 docs

### Test 10.4: Security - SQL Injection Prevention

**Test:**
```php
// Try SQL injection in document type
$type = "PROOF_OF_ADDRESS'; DROP TABLE documents; --";

try {
    $result = $controller->index($request, $type);
    // Should sanitize and return error, not execute SQL
    assert(true, 'SQL injection prevented');
} catch (\Exception $e) {
    // Should not drop table
}

// Verify table still exists
$count = Document::count();
assert($count > 0, 'Table was not dropped');
```

**Checklist:**
- [ ] Input sanitization funciona (strtoupper, trim)
- [ ] Parameter binding usado en todos los queries
- [ ] No string concatenation en SQL
- [ ] Validation de tipos antes de query

### Test 10.5: Security - Authorization

**Test Cases:**
- [ ] Applicant no puede ver documentos de otro applicant
- [ ] Applicant no puede ver historial de otro applicant
- [ ] Applicant no puede acceder a endpoints de staff
- [ ] Analyst puede ver documentos de solicitudes asignadas
- [ ] Analyst NO puede ver documentos de solicitudes no asignadas
- [ ] Super admin puede ver todo

### Test 10.6: Security - Tenant Isolation

**Test:**
```php
$tenant1 = Tenant::first();
$tenant2 = Tenant::skip(1)->first();

$person1 = Person::where('tenant_id', $tenant1->id)->first();
$person2 = Person::where('tenant_id', $tenant2->id)->first();

// Login as tenant1 user
// Try to access tenant2 documents
$doc2 = Document::where('documentable_id', $person2->id)->first();

// Should return null or error due to tenant scope
assert($doc2 === null, 'Tenant isolation should prevent access');
```

**Checklist:**
- [ ] HasTenant trait aplicado
- [ ] Global scope activo
- [ ] No puede acceder a datos de otro tenant
- [ ] API endpoints verifican tenant_id

---

## 🔍 FASE 11: Edge Cases & Error Handling

### Test 11.1: Edge Case - Documento sin Application

**Scenario:** Usuario sube documento pero no lo asocia a ninguna aplicación aún

**Steps:**
1. [ ] Subir documento sin application_id
2. [ ] Verificar documento creado
3. [ ] Verificar NO hay documentable_relation USAGE
4. [ ] Verificar SÍ hay documentable_relation OWNERSHIP
5. [ ] Luego asociar a aplicación
6. [ ] Verificar USAGE relation se crea

### Test 11.2: Edge Case - Múltiples Aplicaciones con Mismo Documento

**Scenario:** Documento usado en 2 aplicaciones diferentes

**Steps:**
1. [ ] Crear doc1
2. [ ] Crear app1, attach doc1
3. [ ] Crear app2, attach doc1
4. [ ] Verificar:
   - [ ] 2 documentable_relations USAGE (una por app)
   - [ ] 1 documentable_relation OWNERSHIP
   - [ ] Ambas apps tienen referencia al mismo documento

### Test 11.3: Edge Case - Reemplazar Documento ya Reemplazado

**Scenario:** Chain larga de reemplazos

**Steps:**
1. [ ] doc1 → doc2 → doc3 → doc4
2. [ ] Verificar chain intacto
3. [ ] Verificar solo doc4 está is_active = true
4. [ ] Verificar doc1, doc2, doc3 tienen superseded_by_id correcto

### Test 11.4: Error Handling - File Upload Falla

**Scenario:** Upload a S3 falla

**Steps:**
1. [ ] Simular fallo de S3 (mock)
2. [ ] Intentar subir documento
3. [ ] Verificar:
   - [ ] Transaction rollback
   - [ ] NO se crea registro en BD
   - [ ] User ve error message
   - [ ] Log contiene error details

### Test 11.5: Error Handling - Database Constraint Violation

**Scenario:** Intentar crear 2 documentos activos del mismo tipo (violación de unique constraint)

**Steps:**
1. [ ] Intentar crear doc1 con is_active = true
2. [ ] Intentar crear doc2 con is_active = true (mismo tipo, persona)
3. [ ] Verificar:
   - [ ] Database rechaza con constraint violation
   - [ ] Application maneja error gracefully
   - [ ] User ve mensaje apropiado
   - [ ] No hay data corruption

### Test 11.6: Error Handling - Orphaned Foreign Keys

**Scenario:** Intentar supersede con documento inexistente

**Steps:**
1. [ ] doc1->supersedeWith('fake-uuid', 'test')
2. [ ] Verificar:
   - [ ] Error thrown
   - [ ] Transaction rollback
   - [ ] doc1 permanece sin cambios

---

## 📊 Métricas de Éxito

### Coverage
- [ ] **Backend Model Tests:** 100% de métodos cubiertos
- [ ] **Backend Controller Tests:** 100% de endpoints cubiertos
- [ ] **Backend Service Tests:** 100% de métodos públicos cubiertos
- [ ] **Frontend Component Tests:** Todos los flujos principales cubiertos

### Performance
- [ ] **Supersession Chain:** < 10ms para chain de 10 docs
- [ ] **Active Documents Query:** < 5ms con 1000 docs
- [ ] **Timeline Render:** < 2 segundos con 50 eventos
- [ ] **Document Upload:** < 5 segundos para archivo de 2MB

### Security
- [ ] **SQL Injection:** 0 vulnerabilities
- [ ] **Authorization Bypass:** 0 vulnerabilities
- [ ] **Tenant Isolation:** 100% enforced
- [ ] **Data Integrity:** 0 orphans, 0 duplicates

### Data Integrity
- [ ] **Duplicate Active Docs:** 0 (verificado con query)
- [ ] **Orphaned Relations:** 0 (verificado con query)
- [ ] **Orphaned Foreign Keys:** 0 (verificado con query)
- [ ] **NULL valid_from:** 0 para active docs

---

## 🚀 Ejecución del Plan

### Fase 1-2: Backend Database & Model
**Tiempo estimado:** 2-3 horas
**Prioridad:** CRÍTICA
**Blocker:** Nada (puede empezar ya)

### Fase 3-4: Backend Controllers & Services
**Tiempo estimado:** 3-4 horas
**Prioridad:** CRÍTICA
**Blocker:** Requiere Fase 1-2 completa

### Fase 5: Backend API Integration
**Tiempo estimado:** 2 horas
**Prioridad:** ALTA
**Blocker:** Requiere Fase 3-4 completa

### Fase 6-8: Frontend
**Tiempo estimado:** 4-5 horas
**Prioridad:** ALTA
**Blocker:** Requiere Fase 5 completa (API funcional)

### Fase 9: End-to-End Integration
**Tiempo estimado:** 3-4 horas
**Prioridad:** CRÍTICA
**Blocker:** Requiere todo lo anterior

### Fase 10-11: Performance & Edge Cases
**Tiempo estimado:** 2-3 horas
**Prioridad:** MEDIA
**Blocker:** Requiere Fase 9 completa

**TOTAL ESTIMADO:** 16-23 horas de testing exhaustivo

---

## ✅ Checklist Final

Antes de considerar COMPLETO:

- [ ] Todas las pruebas de Fase 1-11 ejecutadas
- [ ] Todas las pruebas PASARON
- [ ] 0 errores en logs de Laravel
- [ ] 0 errores en consola de navegador
- [ ] 0 vulnerabilidades de seguridad detectadas
- [ ] 0 orphaned records en BD
- [ ] 0 duplicate active documents
- [ ] Performance metrics cumplidos
- [ ] Todos los edge cases manejados
- [ ] Documentation actualizada
- [ ] Git commits con mensajes descriptivos
- [ ] Code review completado
- [ ] Ready for PRODUCTION 🚀

---

**Creado por:** Claude Sonnet 4.5 (TOC Mode)
**Estado:** ⏳ READY TO EXECUTE
**Next Step:** Comenzar Fase 1 - Backend Database Schema
