# Arquitectura de Gestión de Documentos - LendusFind

## Resumen Ejecutivo

Este documento describe la arquitectura de gestión de documentos implementada en LendusFind, basada en las mejores prácticas de la industria fintech (Stripe, Plaid, sistemas bancarios core) y diseñada para cumplir con regulaciones financieras mexicanas (CNBV, CONDUSEF).

**Patrón Implementado:** Active Document with Temporal Validity + Polymorphic Many-to-Many Relations

---

## 1. Análisis de Patrones en la Industria

### 1.1 Stripe (Payment & Identity Verification)

**Patrón:** Immutable Documents + Temporal Validity

```
Características:
- Cada documento tiene vigencia (valid_until)
- NO reemplazan documentos, crean versiones nuevas
- El sistema automáticamente usa el más reciente válido
```

**Ventajas:**
- ✅ Auditoría completa (immutability)
- ✅ Compliance con regulaciones de KYC/AML
- ✅ Trazabilidad perfecta

**Desventajas:**
- ❌ Queries más complejas para obtener documento actual
- ❌ Volumen de datos crece rápidamente

### 1.2 Plaid (Financial Identity)

**Patrón:** Document Versioning with Active Flag

```
Características:
- Múltiples versiones del mismo tipo de documento
- Solo UNA versión puede estar is_active = true
- Historial completo preservado
```

**Ventajas:**
- ✅ Queries simples: WHERE is_active = true
- ✅ Claro qué documento es el "actual"
- ✅ Historial preservado

**Desventajas:**
- ❌ Requiere lógica para manejar transiciones de active flag

### 1.3 DocuSign / SignNow

**Patrón:** Document Lifecycle with States

```
Estados: DRAFT → PENDING → APPROVED → SUPERSEDED

Características:
- Documentos anteriores pasan a SUPERSEDED cuando hay uno nuevo aprobado
- Estado explícito en cada momento
```

**Ventajas:**
- ✅ Máquina de estados clara
- ✅ Transiciones documentadas

**Desventajas:**
- ❌ Estados pueden volverse complejos
- ❌ Lógica de transición compleja

### 1.4 Banking Systems (Core Banking)

**Patrón:** Bi-temporal Data (Valid Time + Transaction Time)

```
Características:
- valid_from / valid_to: Cuándo el documento es válido en el mundo real
- created_at / superseded_at: Cuándo fue registrado en el sistema
```

**Ventajas:**
- ✅ Auditoría regulatoria perfecta
- ✅ Queries temporales: "¿Qué documento era válido el 15-ene-2026?"
- ✅ Cumplimiento CNBV

**Desventajas:**
- ❌ Complejidad adicional en queries
- ❌ Más campos a mantener

---

## 2. Patrón Elegido: Híbrido (Best of All Worlds)

### 2.1 Justificación

Combinamos los mejores elementos de cada patrón:

1. **Active Flag** (de Plaid) → Queries simples
2. **Temporal Validity** (de Banking) → Compliance regulatorio
3. **Superseded Chain** (de DocuSign) → Trazabilidad clara
4. **Polymorphic Relations** (de Stripe) → Flexibilidad

### 2.2 Componentes Clave

```
┌─────────────────────────────────────────────────────────────────┐
│                    ARQUITECTURA COMPLETA                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. documents                                                    │
│     - Archivo físico + metadata                                 │
│     - Propietario único (documentable_type/id)                  │
│     - Estado activo (is_active)                                 │
│     - Vigencia temporal (valid_from/valid_to)                   │
│     - Cadena de supersesión (superseded_by_id)                  │
│                                                                  │
│  2. documentable_relations                                       │
│     - Relaciones múltiples y flexibles                          │
│     - OWNERSHIP: Documento pertenece a Person                   │
│     - USAGE: Documento usado en Application                     │
│     - REFERENCE: Documento referenciado por otra entidad        │
│     - VERIFICATION: Documento usado para validación             │
│                                                                  │
│  3. Reglas de Negocio                                           │
│     - Solo UN documento por tipo puede estar activo por persona │
│     - Documentos superseded preservan historial                 │
│     - Relaciones USAGE permiten reutilización                   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 3. Estructura de Base de Datos

### 3.1 Tabla `documents`

```sql
CREATE TABLE documents (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL,

    -- Legacy fields (compatibilidad con código existente)
    application_id UUID,
    applicant_id UUID,

    -- Propietario (polymorphic)
    documentable_type VARCHAR NOT NULL,  -- 'App\Models\Person'
    documentable_id UUID NOT NULL,       -- person_789

    -- Información del documento
    type VARCHAR NOT NULL,               -- 'INE_FRONT', 'PROOF_OF_ADDRESS', etc.
    name VARCHAR NOT NULL,
    description TEXT,

    -- Archivo físico
    file_path VARCHAR NOT NULL,
    file_name VARCHAR NOT NULL,
    mime_type VARCHAR NOT NULL,
    file_size INTEGER NOT NULL,
    storage_disk VARCHAR DEFAULT 's3',

    -- Estado del documento
    status VARCHAR NOT NULL,             -- 'PENDING', 'APPROVED', 'REJECTED'
    rejection_reason TEXT,

    -- 🔑 ACTIVE FLAG (clave del patrón)
    is_active BOOLEAN DEFAULT true,

    -- 🔑 TEMPORAL VALIDITY (compliance regulatorio)
    valid_from TIMESTAMP,
    valid_to TIMESTAMP,

    -- 🔑 SUPERSEDED CHAIN (trazabilidad)
    superseded_by_id UUID,
    superseded_at TIMESTAMP,

    -- Legacy field (mantener por ahora, deprecar después)
    replaced_at TIMESTAMP,

    -- Revisión
    reviewed_by UUID,
    reviewed_at TIMESTAMP,

    -- Metadata y seguridad
    metadata JSONB,
    checksum VARCHAR,
    is_sensitive BOOLEAN DEFAULT true,

    -- Versionamiento
    version_number INTEGER DEFAULT 1,

    -- Timestamps
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    deleted_at TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (superseded_by_id) REFERENCES documents(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_documentable (documentable_type, documentable_id),
    INDEX idx_type_active (type, is_active),
    INDEX idx_valid_period (valid_from, valid_to),

    -- Constraint: Solo un documento activo por tipo por persona
    UNIQUE (documentable_type, documentable_id, type) WHERE is_active = true
);
```

### 3.2 Tabla `documentable_relations`

```sql
CREATE TABLE documentable_relations (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL,

    -- El documento
    document_id UUID NOT NULL,

    -- La entidad relacionada (polymorphic)
    relatable_type VARCHAR NOT NULL,     -- 'App\Models\Person', 'App\Models\Application', etc.
    relatable_id UUID NOT NULL,

    -- Contexto de la relación
    relation_context VARCHAR,            -- 'OWNERSHIP', 'USAGE', 'REFERENCE', 'VERIFICATION'
    notes TEXT,

    -- Auditoría
    created_by UUID,
    created_by_type VARCHAR,             -- 'App\Models\ApplicantAccount', 'App\Models\User'

    -- Timestamps
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    deleted_at TIMESTAMP,                -- Soft delete para auditoría

    -- Foreign keys
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_document (document_id, relatable_type, relatable_id),
    INDEX idx_relatable (relatable_type, relatable_id),
    INDEX idx_tenant_type (tenant_id, relatable_type),

    -- Constraint: Prevenir duplicados
    UNIQUE (document_id, relatable_type, relatable_id, relation_context)
);
```

---

## 4. Flujos de Negocio

### 4.1 Escenario 1: Primera Solicitud - Upload PROOF_OF_ADDRESS

**Contexto:**
- Juan Pérez (person_789) sube comprobante de luz para su primera solicitud (app_001)

**Paso 1: Crear documento**

```sql
INSERT INTO documents (
    id: 'doc_001',
    tenant_id: 'tenant_123',
    documentable_type: 'App\Models\Person',
    documentable_id: 'person_789',
    type: 'PROOF_OF_ADDRESS',
    file_path: 'documents/.../comprobante_luz.pdf',
    status: 'PENDING',
    is_active: true,          -- ✅ Es el documento activo
    valid_from: NOW(),        -- ✅ Válido desde ahora
    valid_to: NULL            -- ✅ Sin fecha de expiración (hasta que se superseda)
);
```

**Paso 2: Crear relaciones**

```sql
-- Relación OWNERSHIP: Documento pertenece a Juan
INSERT INTO documentable_relations (
    id: 'rel_001',
    document_id: 'doc_001',
    relatable_type: 'App\Models\Person',
    relatable_id: 'person_789',
    relation_context: 'OWNERSHIP'
);

-- Relación USAGE: Documento usado en app_001
INSERT INTO documentable_relations (
    id: 'rel_002',
    document_id: 'doc_001',
    relatable_type: 'App\Models\Application',
    relatable_id: 'app_001',
    relation_context: 'USAGE'
);
```

**Estado resultante:**

```
documents:
┌─────────┬─────────────┬──────────────────┬──────────┬───────────┬─────────────┐
│ id      │ documentab_ │ type             │ status   │ is_active │ valid_from  │
│         │ le_id       │                  │          │           │             │
├─────────┼─────────────┼──────────────────┼──────────┼───────────┼─────────────┤
│ doc_001 │ person_789  │ PROOF_OF_ADDRESS │ PENDING  │ true      │ 2026-01-22  │
└─────────┴─────────────┴──────────────────┴──────────┴───────────┴─────────────┘

documentable_relations:
┌─────────┬─────────────┬──────────────────────┬─────────────┬──────────────────┐
│ id      │ document_id │ relatable_type       │ relatable_  │ relation_context │
│         │             │                      │ id          │                  │
├─────────┼─────────────┼──────────────────────┼─────────────┼──────────────────┤
│ rel_001 │ doc_001     │ App\Models\Person    │ person_789  │ OWNERSHIP        │
│ rel_002 │ doc_001     │ App\Models\Applic... │ app_001     │ USAGE            │
└─────────┴─────────────┴──────────────────────┴─────────────┴──────────────────┘
```

---

### 4.2 Escenario 2: Rechazo y Reemplazo (MISMA Solicitud)

**Contexto:**
- Staff rechaza doc_001 (comprobante ilegible)
- Juan sube nuevo comprobante (doc_002)
- IMPORTANTE: Es la MISMA solicitud (app_001)

**Paso 1: Staff rechaza documento**

```sql
UPDATE documents
SET status = 'REJECTED',
    rejection_reason = 'Documento ilegible, favor de subir imagen clara'
WHERE id = 'doc_001';
```

**Paso 2: Juan sube nuevo documento**

```sql
INSERT INTO documents (
    id: 'doc_002',
    tenant_id: 'tenant_123',
    documentable_type: 'App\Models\Person',
    documentable_id: 'person_789',
    type: 'PROOF_OF_ADDRESS',
    file_path: 'documents/.../comprobante_luz_nuevo.pdf',
    status: 'PENDING',
    is_active: true,          -- ✅ Nuevo documento activo
    valid_from: NOW()
);
```

**Paso 3: Backend detecta documento anterior del MISMO tipo**

```php
// En attachDocumentToApplication()
$activeDocument = Document::where('documentable_type', 'App\\Models\\Person')
    ->where('documentable_id', $person->id)
    ->where('type', 'PROOF_OF_ADDRESS')
    ->where('is_active', true)
    ->where('id', '!=', $newDocument->id)
    ->first();
// Encuentra: doc_001 (aunque está rechazado)
```

**Paso 4: Superseder documento anterior**

```sql
-- Marcar doc_001 como superseded
UPDATE documents
SET is_active = false,
    superseded_by_id = 'doc_002',
    superseded_at = NOW(),
    valid_to = NOW()          -- ✅ Cierra la vigencia
WHERE id = 'doc_001';

-- Soft-delete relación USAGE antigua
UPDATE documentable_relations
SET deleted_at = NOW()
WHERE id = 'rel_002';  -- rel_002 era doc_001 → app_001
```

**Paso 5: Crear nuevas relaciones**

```sql
-- OWNERSHIP para doc_002
INSERT INTO documentable_relations (
    id: 'rel_003',
    document_id: 'doc_002',
    relatable_type: 'App\Models\Person',
    relatable_id: 'person_789',
    relation_context: 'OWNERSHIP'
);

-- USAGE para doc_002 en app_001
INSERT INTO documentable_relations (
    id: 'rel_004',
    document_id: 'doc_002',
    relatable_type: 'App\Models\Application',
    relatable_id: 'app_001',
    relation_context: 'USAGE'
);
```

**Estado resultante:**

```
documents:
┌─────────┬─────────────┬──────────────────┬──────────┬───────────┬──────────────────┬─────────────┐
│ id      │ documentab_ │ type             │ status   │ is_active │ superseded_by_id │ valid_to    │
│         │ le_id       │                  │          │           │                  │             │
├─────────┼─────────────┼──────────────────┼──────────┼───────────┼──────────────────┼─────────────┤
│ doc_001 │ person_789  │ PROOF_OF_ADDRESS │ REJECTED │ false     │ doc_002          │ 2026-01-22  │
│ doc_002 │ person_789  │ PROOF_OF_ADDRESS │ PENDING  │ true      │ NULL             │ NULL        │
└─────────┴─────────────┴──────────────────┴──────────┴───────────┴──────────────────┴─────────────┘

documentable_relations:
┌─────────┬─────────────┬──────────────────────┬─────────────┬──────────────────┬────────────────────┐
│ id      │ document_id │ relatable_type       │ relatable_  │ relation_context │ deleted_at         │
├─────────┼─────────────┼──────────────────────┼─────────────┼──────────────────┼────────────────────┤
│ rel_001 │ doc_001     │ Person               │ person_789  │ OWNERSHIP        │ NULL               │
│ rel_002 │ doc_001     │ Application          │ app_001     │ USAGE            │ 2026-01-22 11:00   │ ← Soft-deleted
│ rel_003 │ doc_002     │ Person               │ person_789  │ OWNERSHIP        │ NULL               │
│ rel_004 │ doc_002     │ Application          │ app_001     │ USAGE            │ NULL               │ ← Activa
└─────────┴─────────────┴──────────────────────┴─────────────┴──────────────────┴────────────────────┘
```

**Queries:**

```sql
-- Documento activo de PROOF_OF_ADDRESS para Juan
SELECT * FROM documents
WHERE documentable_type = 'App\Models\Person'
  AND documentable_id = 'person_789'
  AND type = 'PROOF_OF_ADDRESS'
  AND is_active = true;
-- Resultado: doc_002

-- Documentos usados en app_001 (solo activos)
SELECT d.*
FROM documents d
JOIN documentable_relations dr ON dr.document_id = d.id
WHERE dr.relatable_type = 'App\Models\Application'
  AND dr.relatable_id = 'app_001'
  AND dr.relation_context = 'USAGE'
  AND dr.deleted_at IS NULL;
-- Resultado: doc_002
```

---

### 4.3 Escenario 3: Nueva Solicitud - Reusar y Reemplazar

**Contexto:**
- Juan crea segunda solicitud (app_002) en marzo 2026
- Documentos de primera solicitud (app_001):
  - INE_FRONT (doc_010) - APPROVED (ene-2026)
  - INE_BACK (doc_011) - APPROVED (ene-2026)
  - PROOF_OF_ADDRESS (doc_002) - APPROVED (ene-2026)
  - PAYSLIP (doc_020) - APPROVED (ene-2026)

**Decisión de Juan:**
- ✅ Reusar INE (no cambió)
- ✅ Reusar PROOF_OF_ADDRESS (es reciente)
- ❌ Reemplazar PAYSLIP (quiere uno más actual)

#### 4.3.1 Reusar documentos (INE y PROOF_OF_ADDRESS)

**Lógica:**
- Los documentos YA existen y están activos
- Solo necesitamos crear relaciones USAGE para app_002

```sql
-- Reusar INE_FRONT
INSERT INTO documentable_relations (
    id: 'rel_100',
    document_id: 'doc_010',          -- Mismo documento
    relatable_type: 'App\Models\Application',
    relatable_id: 'app_002',         -- Nueva solicitud
    relation_context: 'USAGE'
);

-- Reusar INE_BACK
INSERT INTO documentable_relations (
    id: 'rel_101',
    document_id: 'doc_011',
    relatable_type: 'App\Models\Application',
    relatable_id: 'app_002',
    relation_context: 'USAGE'
);

-- Reusar PROOF_OF_ADDRESS
INSERT INTO documentable_relations (
    id: 'rel_102',
    document_id: 'doc_002',
    relatable_type: 'App\Models\Application',
    relatable_id: 'app_002',
    relation_context: 'USAGE'
);
```

**IMPORTANTE:**
- ❌ NO se modifica `is_active` (siguen siendo activos)
- ❌ NO se modifica `valid_to` (siguen vigentes)
- ❌ NO se marca como superseded
- ✅ Solo se agrega nueva relación USAGE

#### 4.3.2 Reemplazar PAYSLIP para nueva solicitud

**Paso 1: Juan sube nuevo recibo (marzo-2026)**

```sql
INSERT INTO documents (
    id: 'doc_021',
    tenant_id: 'tenant_123',
    documentable_type: 'App\Models\Person',
    documentable_id: 'person_789',
    type: 'PAYSLIP',
    file_path: 'documents/.../recibo_nomina_marzo.pdf',
    status: 'PENDING',
    is_active: true,          -- ✅ Nuevo documento activo
    valid_from: NOW()
);
```

**Paso 2: Backend detecta documento anterior ACTIVO del mismo tipo**

```php
$activePayslip = Document::where('documentable_type', 'App\\Models\\Person')
    ->where('documentable_id', 'person_789')
    ->where('type', 'PAYSLIP')
    ->where('is_active', true)
    ->where('id', '!=', 'doc_021')
    ->first();
// Encuentra: doc_020
```

**Paso 3: Superseder documento anterior**

```sql
-- CLAVE: Marcar doc_020 como superseded
UPDATE documents
SET is_active = false,           -- ✅ Ya no es el activo
    superseded_by_id = 'doc_021',
    superseded_at = NOW(),
    valid_to = NOW()             -- ✅ Cierra vigencia
WHERE id = 'doc_020';
```

**CRÍTICO - ¿Qué pasa con la relación USAGE de doc_020 en app_001?**

```sql
-- ❌ NO SE TOCA - app_001 sigue usando doc_020
-- La relación rel_021 (doc_020 → app_001) NO se soft-delete
-- Porque es una aplicación DIFERENTE

SELECT * FROM documentable_relations
WHERE id = 'rel_021';
-- deleted_at: NULL  ← Sigue activa
```

**Paso 4: Crear relaciones para doc_021**

```sql
-- OWNERSHIP
INSERT INTO documentable_relations (
    id: 'rel_103',
    document_id: 'doc_021',
    relatable_type: 'App\Models\Person',
    relatable_id: 'person_789',
    relation_context: 'OWNERSHIP'
);

-- USAGE en app_002
INSERT INTO documentable_relations (
    id: 'rel_104',
    document_id: 'doc_021',
    relatable_type: 'App\Models\Application',
    relatable_id: 'app_002',
    relation_context: 'USAGE'
);
```

**Estado final completo:**

```
documents:
┌─────────┬─────────────┬──────────────────┬──────────┬───────────┬──────────────────┬─────────────┐
│ id      │ documentab_ │ type             │ status   │ is_active │ superseded_by_id │ valid_to    │
├─────────┼─────────────┼──────────────────┼──────────┼───────────┼──────────────────┼─────────────┤
│ doc_002 │ person_789  │ PROOF_OF_ADDRESS │ APPROVED │ true      │ NULL             │ NULL        │
│ doc_010 │ person_789  │ INE_FRONT        │ APPROVED │ true      │ NULL             │ NULL        │
│ doc_011 │ person_789  │ INE_BACK         │ APPROVED │ true      │ NULL             │ NULL        │
│ doc_020 │ person_789  │ PAYSLIP          │ APPROVED │ false     │ doc_021          │ 2026-03-15  │ ← Superseded
│ doc_021 │ person_789  │ PAYSLIP          │ APPROVED │ true      │ NULL             │ NULL        │ ← Activo
└─────────┴─────────────┴──────────────────┴──────────┴───────────┴──────────────────┴─────────────┘

documentable_relations (USAGE solamente):
┌─────────┬─────────────┬─────────────┬──────────────────┬────────────┐
│ id      │ document_id │ relatable_  │ relation_context │ deleted_at │
│         │             │ id          │                  │            │
├─────────┼─────────────┼─────────────┼──────────────────┼────────────┤
│ rel_005 │ doc_002     │ app_001     │ USAGE            │ NULL       │
│ rel_011 │ doc_010     │ app_001     │ USAGE            │ NULL       │
│ rel_013 │ doc_011     │ app_001     │ USAGE            │ NULL       │
│ rel_021 │ doc_020     │ app_001     │ USAGE            │ NULL       │ ← Válido para app_001
│ rel_100 │ doc_010     │ app_002     │ USAGE            │ NULL       │ ← Reuso
│ rel_101 │ doc_011     │ app_002     │ USAGE            │ NULL       │ ← Reuso
│ rel_102 │ doc_002     │ app_002     │ USAGE            │ NULL       │ ← Reuso
│ rel_104 │ doc_021     │ app_002     │ USAGE            │ NULL       │ ← Nuevo
└─────────┴─────────────┴─────────────┴──────────────────┴────────────┘
```

---

## 5. Reglas de Negocio Críticas

### 5.1 Documento Activo (is_active)

**Regla:**
> Solo UN documento por tipo puede estar activo (`is_active = true`) por persona

**Implementación:**

```sql
-- Constraint en base de datos
UNIQUE (documentable_type, documentable_id, type)
WHERE is_active = true;
```

```php
// Lógica en Controller
private function activateDocument(Document $newDocument): void
{
    // Desactivar documento anterior del mismo tipo
    Document::where('documentable_type', $newDocument->documentable_type)
        ->where('documentable_id', $newDocument->documentable_id)
        ->where('type', $newDocument->type)
        ->where('is_active', true)
        ->where('id', '!=', $newDocument->id)
        ->update([
            'is_active' => false,
            'superseded_by_id' => $newDocument->id,
            'superseded_at' => now(),
            'valid_to' => now(),
        ]);

    // Activar nuevo documento
    $newDocument->update([
        'is_active' => true,
        'valid_from' => now(),
    ]);
}
```

### 5.2 Supersesión vs Reemplazo

**Diferencia Clave:**

```
SUPERSESIÓN (Supersede):
- Documento anterior sigue siendo VÁLIDO para aplicaciones donde ya se usó
- Solo se marca is_active = false
- Relaciones USAGE existentes NO se tocan

REEMPLAZO en MISMA Aplicación:
- Documento anterior NO es válido para esa aplicación específica
- Se marca is_active = false
- Relación USAGE se soft-delete
```

**Lógica:**

```php
private function attachDocumentToApplication(
    Application $application,
    Document $document,
    string $attachedBy
): void {
    $person = $application->person;

    // 1. Activar documento (supersede documento anterior si existe)
    $this->activateDocument($document);

    // 2. Verificar si ya existe USAGE en ESTA aplicación para ESTE tipo
    $existingUsageInThisApp = DB::table('documentable_relations')
        ->where('relatable_type', 'App\\Models\\Application')
        ->where('relatable_id', $application->id)
        ->where('relation_context', 'USAGE')
        ->whereNull('deleted_at')
        ->join('documents', 'documentable_relations.document_id', '=', 'documents.id')
        ->where('documents.type', $document->type)
        ->where('documents.id', '!=', $document->id)
        ->first();

    if ($existingUsageInThisApp) {
        // CASO: Reemplazo en misma aplicación (rechazo)
        // Soft-delete la relación USAGE anterior
        DB::table('documentable_relations')
            ->where('id', $existingUsageInThisApp->id)
            ->update(['deleted_at' => now()]);
    }

    // 3. Crear/restaurar relaciones
    $this->ensureOwnershipRelation($document, $person, $attachedBy);
    $this->ensureUsageRelation($document, $application, $attachedBy);
}
```

### 5.3 Documento del Perfil

**Regla:**
> El perfil siempre muestra el documento ACTIVO de cada tipo

**Query:**

```php
public function getProfileDocuments(Person $person): Collection
{
    return Document::where('documentable_type', 'App\\Models\\Person')
        ->where('documentable_id', $person->id)
        ->where('is_active', true)  // ✅ Solo activos
        ->where('status', 'APPROVED') // ✅ Solo aprobados
        ->get();
}
```

**Ejemplo:**

```
Perfil de Juan muestra:
- INE_FRONT: doc_010 (is_active = true)
- INE_BACK: doc_011 (is_active = true)
- PROOF_OF_ADDRESS: doc_002 (is_active = true)
- PAYSLIP: doc_021 (is_active = true) ← El más reciente

Aunque doc_020 existe y está aprobado:
- is_active = false
- Fue superseded por doc_021
```

### 5.4 Documentos de una Solicitud

**Regla:**
> Cada solicitud usa documentos específicos, preservados en el momento de su creación

**Query:**

```php
public function getApplicationDocuments(Application $application): Collection
{
    return Document::join('documentable_relations', 'documents.id', '=', 'documentable_relations.document_id')
        ->where('documentable_relations.relatable_type', 'App\\Models\\Application')
        ->where('documentable_relations.relatable_id', $application->id)
        ->where('documentable_relations.relation_context', 'USAGE')
        ->whereNull('documentable_relations.deleted_at')
        ->select('documents.*')
        ->get();
}
```

**Ejemplo:**

```
app_001 (ene-2026) muestra:
- INE_FRONT: doc_010
- INE_BACK: doc_011
- PROOF_OF_ADDRESS: doc_002
- PAYSLIP: doc_020 ← El que usó en esa fecha

app_002 (mar-2026) muestra:
- INE_FRONT: doc_010 (reutilizado)
- INE_BACK: doc_011 (reutilizado)
- PROOF_OF_ADDRESS: doc_002 (reutilizado)
- PAYSLIP: doc_021 ← Documento nuevo
```

### 5.5 Auditoría Temporal (Compliance)

**Regla:**
> Poder determinar qué documento era válido en una fecha específica

**Query:**

```php
public function getDocumentValidAt(Person $person, string $type, Carbon $date): ?Document
{
    return Document::where('documentable_type', 'App\\Models\\Person')
        ->where('documentable_id', $person->id)
        ->where('type', $type)
        ->where('valid_from', '<=', $date)
        ->where(function($q) use ($date) {
            $q->whereNull('valid_to')
              ->orWhere('valid_to', '>', $date);
        })
        ->first();
}
```

**Ejemplo:**

```php
// ¿Qué PAYSLIP era válido el 1-feb-2026?
$payslip = getDocumentValidAt($juan, 'PAYSLIP', Carbon::parse('2026-02-01'));
// Resultado: doc_020
// Porque: valid_from = 2026-01-15, valid_to = 2026-03-15

// ¿Qué PAYSLIP es válido hoy (22-mar-2026)?
$payslip = getDocumentValidAt($juan, 'PAYSLIP', now());
// Resultado: doc_021
// Porque: valid_from = 2026-03-15, valid_to = NULL (activo)
```

---

## 6. Implementación en Código

### 6.1 Modelo Document

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Carbon\Carbon;

class Document extends Model
{
    protected $fillable = [
        'tenant_id',
        'documentable_type',
        'documentable_id',
        'type',
        'name',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'storage_disk',
        'status',
        'rejection_reason',
        'is_active',
        'valid_from',
        'valid_to',
        'superseded_by_id',
        'superseded_at',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'superseded_at' => 'datetime',
        'metadata' => 'array',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Owner of the document (polymorphic).
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Document that superseded this one.
     */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'superseded_by_id');
    }

    /**
     * Document that this one supersedes (inverse).
     */
    public function supersedes(): HasOne
    {
        return $this->hasOne(Document::class, 'superseded_by_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope: Only active documents.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Valid at a specific date.
     */
    public function scopeValidAt($query, Carbon $date)
    {
        return $query->where('valid_from', '<=', $date)
            ->where(function($q) use ($date) {
                $q->whereNull('valid_to')
                  ->orWhere('valid_to', '>', $date);
            });
    }

    /**
     * Scope: Currently valid (valid_to is null or in the future).
     */
    public function scopeCurrentlyValid($query)
    {
        return $query->where(function($q) {
            $q->whereNull('valid_to')
              ->orWhere('valid_to', '>', now());
        });
    }

    // ==========================================
    // BUSINESS METHODS
    // ==========================================

    /**
     * Mark this document as superseded by a new one.
     */
    public function supersede(Document $newDocument): void
    {
        $this->update([
            'is_active' => false,
            'superseded_by_id' => $newDocument->id,
            'superseded_at' => now(),
            'valid_to' => now(),
        ]);
    }

    /**
     * Check if document is valid at a specific date.
     */
    public function isValidAt(Carbon $date): bool
    {
        return $this->valid_from <= $date
            && ($this->valid_to === null || $this->valid_to > $date);
    }

    /**
     * Check if document is currently valid.
     */
    public function isCurrentlyValid(): bool
    {
        return $this->isValidAt(now());
    }

    /**
     * Activate this document (deactivate others of same type).
     */
    public function activate(): void
    {
        DB::transaction(function () {
            // Deactivate other documents of same type
            static::where('documentable_type', $this->documentable_type)
                ->where('documentable_id', $this->documentable_id)
                ->where('type', $this->type)
                ->where('is_active', true)
                ->where('id', '!=', $this->id)
                ->each(function ($doc) {
                    $doc->supersede($this);
                });

            // Activate this one
            $this->update([
                'is_active' => true,
                'valid_from' => $this->valid_from ?? now(),
            ]);
        });
    }
}
```

### 6.2 Controller: attachDocumentToApplication()

```php
<?php

private function attachDocumentToApplication(
    Application $application,
    Document $document,
    string $attachedBy
): void {
    $person = $application->person;

    // 1. Activate document (will supersede previous active document if exists)
    $document->activate();

    // 2. Ensure OWNERSHIP relation exists
    $this->ensureDocumentRelation(
        $document,
        'App\\Models\\Person',
        $person->id,
        'OWNERSHIP',
        $attachedBy
    );

    // 3. Check if there's already a USAGE relation for this document type in THIS application
    $existingUsage = DB::table('documentable_relations as dr')
        ->join('documents as d', 'dr.document_id', '=', 'd.id')
        ->where('dr.relatable_type', 'App\\Models\\Application')
        ->where('dr.relatable_id', $application->id)
        ->where('dr.relation_context', 'USAGE')
        ->where('d.type', $document->type)
        ->where('d.id', '!=', $document->id)
        ->whereNull('dr.deleted_at')
        ->select('dr.id', 'd.id as document_id')
        ->first();

    if ($existingUsage) {
        // There's a different document of same type already used in THIS app
        // This is a REPLACEMENT (e.g., rejected document being re-uploaded)
        // Soft-delete the old USAGE relation
        DB::table('documentable_relations')
            ->where('id', $existingUsage->id)
            ->update(['deleted_at' => now()]);

        Log::info('Document replaced in application', [
            'application_id' => $application->id,
            'old_document_id' => $existingUsage->document_id,
            'new_document_id' => $document->id,
            'document_type' => $document->type,
        ]);
    }

    // 4. Create/restore USAGE relation
    $this->ensureDocumentRelation(
        $document,
        'App\\Models\\Application',
        $application->id,
        'USAGE',
        $attachedBy
    );

    Log::debug('Document attached to application', [
        'application_id' => $application->id,
        'document_id' => $document->id,
        'document_type' => $document->type,
        'is_active' => $document->is_active,
    ]);
}

private function ensureDocumentRelation(
    Document $document,
    string $relatableType,
    string $relatableId,
    string $context,
    string $createdBy
): void {
    $existing = DB::table('documentable_relations')
        ->where('document_id', $document->id)
        ->where('relatable_type', $relatableType)
        ->where('relatable_id', $relatableId)
        ->where('relation_context', $context)
        ->first();

    if ($existing) {
        // Restore if soft-deleted
        if ($existing->deleted_at) {
            DB::table('documentable_relations')
                ->where('id', $existing->id)
                ->update([
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);
        }
    } else {
        // Create new relation
        DB::table('documentable_relations')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->id,
            'relatable_type' => $relatableType,
            'relatable_id' => $relatableId,
            'relation_context' => $context,
            'created_by' => $createdBy,
            'created_by_type' => 'App\\Models\\ApplicantAccount',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
```

---

## 7. Queries Comunes

### 7.1 Documentos del Perfil

```php
// Obtener documentos activos y aprobados del perfil
$profileDocuments = Document::where('documentable_type', 'App\\Models\\Person')
    ->where('documentable_id', $person->id)
    ->where('is_active', true)
    ->where('status', 'APPROVED')
    ->get()
    ->keyBy('type');
```

### 7.2 Documentos de una Solicitud

```php
// Obtener documentos usados en una solicitud específica
$applicationDocuments = Document::join('documentable_relations', 'documents.id', '=', 'documentable_relations.document_id')
    ->where('documentable_relations.relatable_type', 'App\\Models\\Application')
    ->where('documentable_relations.relatable_id', $application->id)
    ->where('documentable_relations.relation_context', 'USAGE')
    ->whereNull('documentable_relations.deleted_at')
    ->select('documents.*')
    ->get();
```

### 7.3 Historial de un Tipo de Documento

```php
// Ver todas las versiones de un tipo de documento
$history = Document::where('documentable_type', 'App\\Models\\Person')
    ->where('documentable_id', $person->id)
    ->where('type', 'PAYSLIP')
    ->orderBy('valid_from', 'desc')
    ->get();

// Ver cadena de supersesión
$current = Document::where('documentable_id', $person->id)
    ->where('type', 'PAYSLIP')
    ->where('is_active', true)
    ->first();

$chain = [];
$doc = $current;
while ($doc) {
    $chain[] = $doc;
    $doc = $doc->supersedes; // Documento que este supersede
}
```

### 7.4 Documento Válido en Fecha Específica

```php
// ¿Qué documento era válido el 1-feb-2026?
$document = Document::where('documentable_type', 'App\\Models\\Person')
    ->where('documentable_id', $person->id)
    ->where('type', 'PROOF_OF_ADDRESS')
    ->validAt(Carbon::parse('2026-02-01'))
    ->first();
```

### 7.5 Solicitudes que Usan un Documento

```php
// ¿En qué solicitudes se usó doc_010 (INE_FRONT)?
$applications = Application::join('documentable_relations', 'applications.id', '=', 'documentable_relations.relatable_id')
    ->where('documentable_relations.document_id', 'doc_010')
    ->where('documentable_relations.relatable_type', 'App\\Models\\Application')
    ->where('documentable_relations.relation_context', 'USAGE')
    ->whereNull('documentable_relations.deleted_at')
    ->select('applications.*')
    ->get();
```

---

## 8. Ventajas del Patrón Elegido

### 8.1 Cumplimiento Regulatorio

✅ **CNBV (Comisión Nacional Bancaria y de Valores)**
- Temporal validity permite auditoría: "¿Qué documento era válido el día X?"
- Cadena de supersesión preserva historial completo
- Soft deletes en relaciones = auditoría sin pérdida de datos

✅ **CONDUSEF (Comisión Nacional para la Protección de Usuarios)**
- Trazabilidad completa de documentos presentados
- Preservación de documentos usados en cada solicitud
- Justificación de decisiones de crédito

### 8.2 Performance

✅ **Queries Simples**
```sql
-- Documentos activos del perfil
WHERE is_active = true
-- vs queries complejas con subqueries y MAX(created_at)
```

✅ **Indexes Optimizados**
```sql
INDEX (documentable_type, documentable_id, type) WHERE is_active = true
INDEX (valid_from, valid_to)
INDEX (relatable_type, relatable_id) ON documentable_relations
```

### 8.3 Mantenibilidad

✅ **Código Claro**
```php
$document->activate();  // Auto-maneja supersesión
$document->supersede($newDoc);  // Explícito
$document->isValidAt($date);  // Self-documenting
```

✅ **Reglas de Negocio Explícitas**
- Un solo lugar para activar documentos
- Constraint DB previene inconsistencias
- Scopes reutilizables

### 8.4 Flexibilidad

✅ **Relaciones Polimórficas**
- Documento puede relacionarse con cualquier entidad
- Contextos de relación personalizables (OWNERSHIP, USAGE, REFERENCE, VERIFICATION)

✅ **Extensible**
- Agregar nuevos contextos sin cambiar schema
- Soft deletes permiten "undo"

---

## 9. Comparación con Alternativas

| Característica | Patrón Actual | replaced_at | version_number |
|---------------|---------------|-------------|----------------|
| **Queries Simples** | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐ |
| **Compliance CNBV** | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐⭐ |
| **Auditoría Temporal** | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐⭐ |
| **Industry Standard** | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐⭐ |
| **Mantenibilidad** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐ |
| **Performance** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ |

---

## 10. Resumen Ejecutivo

### Decisiones Clave

1. **is_active flag**: Solo un documento activo por tipo por persona
2. **Temporal validity**: valid_from/valid_to para compliance regulatorio
3. **Superseded chain**: Trazabilidad clara de reemplazos
4. **Polymorphic relations**: Flexibilidad para múltiples contextos

### Beneficios

- ✅ Queries simples y rápidas
- ✅ Cumplimiento regulatorio CNBV/CONDUSEF
- ✅ Código mantenible y claro
- ✅ Industry standard (Stripe, Plaid, Banking)
- ✅ Auditoría completa sin pérdida de datos

### Trade-offs Aceptados

- Más campos en tabla documents (pero justificados)
- Lógica adicional en activate() (pero encapsulada)
- Constraint única requiere índice (pero mejora performance)

---

## 11. Historial y Auditoría de Documentos

### 11.1 Obtener Historial Completo

La arquitectura permite rastrear el historial completo de documentos por tipo.

**Caso de Uso:** Ver todos los comprobantes de domicilio que Juan ha subido.

#### Query 1: Historial Cronológico

```php
/**
 * Get complete history of documents by type.
 */
public function getDocumentHistory(Person $person, string $type): Collection
{
    return Document::where('documentable_type', 'App\\Models\\Person')
        ->where('documentable_id', $person->id)
        ->where('type', $type)
        ->orderBy('created_at', 'desc')  // Más reciente primero
        ->get();
}
```

**Ejemplo de resultado:**

```
Historial de PROOF_OF_ADDRESS para Juan:

┌─────────┬─────────────┬──────────┬───────────┬──────────────────┬─────────────────────┬─────────────┐
│ id      │ file_name   │ status   │ is_active │ superseded_by_id │ created_at          │ valid_to    │
├─────────┼─────────────┼──────────┼───────────┼──────────────────┼─────────────────────┼─────────────┤
│ doc_004 │ luz_mar.pdf │ APPROVED │ ✅ true   │ NULL             │ 2026-03-15 10:00:00 │ NULL        │
│ doc_003 │ luz_ene.pdf │ APPROVED │ ❌ false  │ doc_004          │ 2026-01-22 14:00:00 │ 2026-03-15  │
│ doc_002 │ luz_v2.pdf  │ REJECTED │ ❌ false  │ doc_003          │ 2026-01-21 11:00:00 │ 2026-01-22  │
│ doc_001 │ luz_v1.pdf  │ REJECTED │ ❌ false  │ doc_002          │ 2026-01-15 11:00:00 │ 2026-01-21  │
└─────────┴─────────────┴──────────┴───────────┴──────────────────┴─────────────────────┴─────────────┘
```

#### Query 2: Cadena de Supersesión

Seguir la cadena desde el documento actual hacia atrás.

```php
/**
 * Get supersession chain for a document.
 * Returns: [current] → [previous] → [older] → [oldest]
 */
public function getSupersessionChain(string $documentId): array
{
    $chain = [];
    $currentDoc = Document::find($documentId);

    // Recorrer hacia atrás siguiendo superseded_by_id
    while ($currentDoc) {
        $chain[] = [
            'id' => $currentDoc->id,
            'file_name' => $currentDoc->file_name,
            'status' => $currentDoc->status,
            'is_active' => $currentDoc->is_active,
            'created_at' => $currentDoc->created_at->toIso8601String(),
            'valid_from' => $currentDoc->valid_from?->toIso8601String(),
            'valid_to' => $currentDoc->valid_to?->toIso8601String(),
            'rejection_reason' => $currentDoc->rejection_reason,
        ];

        // Buscar documento anterior (el que este supersede)
        $currentDoc = Document::where('superseded_by_id', $currentDoc->id)->first();
    }

    return $chain;
}
```

**Ejemplo de resultado:**

```php
$chain = $this->getSupersessionChain('doc_004');

[
    // Documento actual
    [
        'id' => 'doc_004',
        'file_name' => 'luz_mar.pdf',
        'status' => 'APPROVED',
        'is_active' => true,
        'created_at' => '2026-03-15T10:00:00Z',
    ],
    // Documento anterior
    [
        'id' => 'doc_003',
        'file_name' => 'luz_ene.pdf',
        'status' => 'APPROVED',
        'is_active' => false,
        'created_at' => '2026-01-22T14:00:00Z',
        'valid_to' => '2026-03-15T10:00:00Z',
    ],
    // Documento rechazado
    [
        'id' => 'doc_002',
        'file_name' => 'luz_v2.pdf',
        'status' => 'REJECTED',
        'rejection_reason' => 'Sin dirección visible',
        'is_active' => false,
        'created_at' => '2026-01-21T11:00:00Z',
    ],
    // Primer intento
    [
        'id' => 'doc_001',
        'file_name' => 'luz_v1.pdf',
        'status' => 'REJECTED',
        'rejection_reason' => 'Documento borroso',
        'is_active' => false,
        'created_at' => '2026-01-15T11:00:00Z',
    ],
]
```

#### Query 3: Documento Válido en Fecha Específica

Auditoría regulatoria (requerido por CNBV).

```php
/**
 * Get document valid at a specific date.
 *
 * Use case: "¿Qué comprobante era válido el 1-feb-2026?"
 */
public function getDocumentValidAt(Person $person, string $type, Carbon $date): ?Document
{
    return Document::where('documentable_type', 'App\\Models\\Person')
        ->where('documentable_id', $person->id)
        ->where('type', $type)
        ->where('valid_from', '<=', $date)
        ->where(function($q) use ($date) {
            $q->whereNull('valid_to')
              ->orWhere('valid_to', '>', $date);
        })
        ->first();
}
```

**Ejemplos:**

```php
// ¿Qué comprobante era válido el 1-feb-2026?
$doc = getDocumentValidAt($juan, 'PROOF_OF_ADDRESS', Carbon::parse('2026-02-01'));
// Resultado: doc_003 (Recibo de Enero)
// Porque: valid_from = 2026-01-22, valid_to = 2026-03-15

// ¿Qué comprobante era válido el 10-ene-2026?
$doc = getDocumentValidAt($juan, 'PROOF_OF_ADDRESS', Carbon::parse('2026-01-10'));
// Resultado: NULL (aún no había subido ninguno aprobado)

// ¿Qué comprobante es válido HOY (22-mar-2026)?
$doc = getDocumentValidAt($juan, 'PROOF_OF_ADDRESS', now());
// Resultado: doc_004 (Recibo de Marzo - el activo)
```

#### Query 4: Timeline con Aplicaciones

Ver qué documento se usó en cada solicitud.

```php
/**
 * Get complete timeline: documents + applications where they were used.
 */
public function getDocumentTimeline(Person $person, string $type): array
{
    $documents = Document::where('documentable_type', 'App\\Models\\Person')
        ->where('documentable_id', $person->id)
        ->where('type', $type)
        ->with(['supersededBy', 'supersedes'])
        ->orderBy('created_at', 'desc')
        ->get();

    $timeline = [];

    foreach ($documents as $doc) {
        // Get applications where this document was used
        $applications = Application::join(
                'documentable_relations',
                'applications.id',
                '=',
                'documentable_relations.relatable_id'
            )
            ->where('documentable_relations.document_id', $doc->id)
            ->where('documentable_relations.relatable_type', 'App\\Models\\Application')
            ->where('documentable_relations.relation_context', 'USAGE')
            ->whereNull('documentable_relations.deleted_at')
            ->select('applications.*')
            ->get();

        $timeline[] = [
            'document' => [
                'id' => $doc->id,
                'file_name' => $doc->file_name,
                'status' => $doc->status,
                'is_active' => $doc->is_active,
                'rejection_reason' => $doc->rejection_reason,
                'created_at' => $doc->created_at->toIso8601String(),
                'valid_from' => $doc->valid_from?->toIso8601String(),
                'valid_to' => $doc->valid_to?->toIso8601String(),
            ],
            'used_in_applications' => $applications->map(fn($app) => [
                'id' => $app->id,
                'created_at' => $app->created_at->toIso8601String(),
                'status' => $app->status,
                'requested_amount' => $app->requested_amount,
            ]),
            'superseded_by' => $doc->supersededBy?->only(['id', 'file_name', 'created_at']),
            'supersedes' => $doc->supersedes?->only(['id', 'file_name', 'created_at']),
        ];
    }

    return $timeline;
}
```

**Ejemplo de resultado:**

```php
[
    // Documento más reciente
    [
        'document' => [
            'id' => 'doc_004',
            'file_name' => 'luz_mar.pdf',
            'status' => 'APPROVED',
            'is_active' => true,
            'created_at' => '2026-03-15T10:00:00Z',
        ],
        'used_in_applications' => [
            [
                'id' => 'app_002',
                'created_at' => '2026-03-15T09:00:00Z',
                'status' => 'IN_REVIEW',
                'requested_amount' => 75000,
            ],
        ],
        'superseded_by' => null,
        'supersedes' => ['id' => 'doc_003', 'file_name' => 'luz_ene.pdf'],
    ],

    // Documento anterior
    [
        'document' => [
            'id' => 'doc_003',
            'file_name' => 'luz_ene.pdf',
            'status' => 'APPROVED',
            'is_active' => false,
            'created_at' => '2026-01-22T14:00:00Z',
            'valid_to' => '2026-03-15T10:00:00Z',
        ],
        'used_in_applications' => [
            [
                'id' => 'app_001',
                'created_at' => '2026-01-15T10:00:00Z',
                'status' => 'APPROVED',
                'requested_amount' => 50000,
            ],
        ],
        'superseded_by' => ['id' => 'doc_004', 'file_name' => 'luz_mar.pdf'],
        'supersedes' => ['id' => 'doc_002', 'file_name' => 'luz_v2.pdf'],
    ],

    // Documentos rechazados (nunca usados en aplicaciones)
    [
        'document' => [
            'id' => 'doc_002',
            'status' => 'REJECTED',
            'rejection_reason' => 'Sin dirección visible',
        ],
        'used_in_applications' => [],  // Vacío - fue rechazado
        'superseded_by' => ['id' => 'doc_003'],
        'supersedes' => ['id' => 'doc_001'],
    ],
]
```

### 11.2 Endpoint API para Historial

```php
<?php

namespace App\Http\Controllers\Api\V2\Applicant;

class DocumentHistoryController extends Controller
{
    /**
     * Get document history for a specific type.
     *
     * GET /api/v2/applicant/documents/history/{type}
     */
    public function getHistory(Request $request, string $type): JsonResponse
    {
        $account = $request->user();
        $person = $account->person;

        if (!$person) {
            return $this->badRequest('PROFILE_INCOMPLETE', 'Complete tu perfil primero.');
        }

        // Validate document type
        if (!in_array($type, array_keys(Document::TYPE_LABELS))) {
            return $this->badRequest('INVALID_TYPE', 'Tipo de documento inválido.');
        }

        // Get all documents of this type
        $history = Document::where('documentable_type', 'App\\Models\\Person')
            ->where('documentable_id', $person->id)
            ->where('type', $type)
            ->orderBy('created_at', 'desc')
            ->get();

        // Get current active document
        $currentActive = $history->firstWhere('is_active', true);

        // Get supersession chain for active document
        $chain = [];
        if ($currentActive) {
            $chain = $this->getSupersessionChain($currentActive->id);
        }

        return $this->success([
            'history' => $history->map(fn($doc) => [
                'id' => $doc->id,
                'file_name' => $doc->file_name,
                'status' => $doc->status,
                'is_active' => $doc->is_active,
                'rejection_reason' => $doc->rejection_reason,
                'created_at' => $doc->created_at->toIso8601String(),
                'valid_from' => $doc->valid_from?->toIso8601String(),
                'valid_to' => $doc->valid_to?->toIso8601String(),
                'superseded_by_id' => $doc->superseded_by_id,
                'was_superseded' => $doc->superseded_by_id !== null,
                'is_currently_valid' => $doc->isCurrentlyValid(),
            ]),
            'current_active' => $currentActive?->only(['id', 'file_name', 'created_at', 'status']),
            'supersession_chain' => $chain,
            'total_versions' => $history->count(),
            'rejected_count' => $history->where('status', 'REJECTED')->count(),
            'approved_count' => $history->where('status', 'APPROVED')->count(),
        ]);
    }

    private function getSupersessionChain(string $documentId): array
    {
        $chain = [];
        $currentDoc = Document::find($documentId);

        while ($currentDoc) {
            $chain[] = [
                'id' => $currentDoc->id,
                'file_name' => $currentDoc->file_name,
                'status' => $currentDoc->status,
                'created_at' => $currentDoc->created_at->toIso8601String(),
            ];

            $currentDoc = Document::where('superseded_by_id', $currentDoc->id)->first();
        }

        return $chain;
    }
}
```

### 11.3 Componente Frontend: DocumentHistory.vue

```vue
<template>
  <div class="document-history">
    <div class="header">
      <h2>Historial de {{ getTypeLabel(type) }}</h2>
      <div class="stats">
        <span class="stat">
          <strong>{{ totalVersions }}</strong> versiones
        </span>
        <span class="stat success">
          <strong>{{ approvedCount }}</strong> aprobadas
        </span>
        <span class="stat danger" v-if="rejectedCount > 0">
          <strong>{{ rejectedCount }}</strong> rechazadas
        </span>
      </div>
    </div>

    <!-- Current Active Document -->
    <div v-if="currentActive" class="current-document">
      <div class="badge">Documento Actual</div>
      <h3>{{ currentActive.file_name }}</h3>
      <span :class="['status', currentActive.status.toLowerCase()]">
        {{ getStatusLabel(currentActive.status) }}
      </span>
      <p class="date">Subido: {{ formatDate(currentActive.created_at) }}</p>
    </div>

    <!-- Timeline -->
    <div class="timeline">
      <h3>Historial Completo</h3>

      <div
        v-for="(item, index) in history"
        :key="item.id"
        class="timeline-item"
        :class="{ 'is-active': item.is_active, 'is-first': index === 0 }"
      >
        <div class="timeline-marker"></div>

        <div class="timeline-content">
          <div class="document-card">
            <div class="document-header">
              <span class="file-name">{{ item.file_name }}</span>
              <span
                class="status-badge"
                :class="item.status.toLowerCase()"
              >
                {{ getStatusLabel(item.status) }}
              </span>
            </div>

            <div class="document-details">
              <p class="date">
                📅 {{ formatDate(item.created_at) }}
              </p>

              <p v-if="item.is_active" class="active-label">
                ✅ Documento activo actual
              </p>

              <p v-if="item.rejection_reason" class="rejection">
                ❌ {{ item.rejection_reason }}
              </p>

              <p v-if="item.valid_to" class="validity">
                ⏰ Válido hasta: {{ formatDate(item.valid_to) }}
              </p>

              <p v-if="!item.is_currently_valid && !item.is_active" class="expired">
                🕐 Ya no es válido
              </p>
            </div>

            <!-- Supersession info -->
            <div v-if="item.superseded_by_id" class="superseded-info">
              <p class="text-muted">
                ➡️ Reemplazado por una versión más reciente
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Supersession Chain -->
    <div v-if="supersessionChain.length > 1" class="chain-view">
      <h3>Cadena de Reemplazos</h3>
      <div class="chain">
        <div
          v-for="(doc, index) in supersessionChain"
          :key="doc.id"
          class="chain-item"
        >
          <div class="chain-node">
            <strong>{{ doc.file_name }}</strong>
            <small>{{ formatDate(doc.created_at) }}</small>
            <span :class="['status-dot', doc.status.toLowerCase()]"></span>
          </div>
          <div v-if="index < supersessionChain.length - 1" class="chain-arrow">→</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { v2 } from '@/services/api'

interface Props {
  type: string  // 'PROOF_OF_ADDRESS', 'PAYSLIP', etc.
}

const props = defineProps<Props>()

const history = ref([])
const currentActive = ref(null)
const supersessionChain = ref([])
const totalVersions = ref(0)
const approvedCount = ref(0)
const rejectedCount = ref(0)

const loadHistory = async () => {
  try {
    const response = await v2.applicant.document.getHistory(props.type)
    if (response.success) {
      history.value = response.data.history
      currentActive.value = response.data.current_active
      supersessionChain.value = response.data.supersession_chain
      totalVersions.value = response.data.total_versions
      approvedCount.value = response.data.approved_count
      rejectedCount.value = response.data.rejected_count
    }
  } catch (error) {
    console.error('Error loading document history:', error)
  }
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('es-MX', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getStatusLabel = (status: string) => {
  const labels = {
    'APPROVED': 'Aprobado',
    'PENDING': 'Pendiente',
    'REJECTED': 'Rechazado',
  }
  return labels[status] || status
}

const getTypeLabel = (type: string) => {
  const labels = {
    'PROOF_OF_ADDRESS': 'Comprobante de Domicilio',
    'PAYSLIP': 'Recibo de Nómina',
    'INE_FRONT': 'INE (Frente)',
    'INE_BACK': 'INE (Reverso)',
  }
  return labels[type] || type
}

onMounted(() => {
  loadHistory()
})
</script>

<style scoped>
.document-history {
  max-width: 800px;
  margin: 0 auto;
  padding: 20px;
}

.header {
  margin-bottom: 30px;
}

.stats {
  display: flex;
  gap: 20px;
  margin-top: 10px;
}

.stat {
  padding: 8px 16px;
  background: #f3f4f6;
  border-radius: 8px;
  font-size: 14px;
}

.stat.success {
  background: #dcfce7;
  color: #166534;
}

.stat.danger {
  background: #fee2e2;
  color: #991b1b;
}

.current-document {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 24px;
  border-radius: 12px;
  margin-bottom: 30px;
  position: relative;
  overflow: hidden;
}

.current-document .badge {
  display: inline-block;
  background: rgba(255, 255, 255, 0.2);
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 12px;
}

.timeline {
  position: relative;
  padding-left: 30px;
}

.timeline-item {
  position: relative;
  margin-bottom: 30px;
  padding-bottom: 30px;
  border-left: 2px solid #e5e7eb;
}

.timeline-item.is-active {
  border-left-color: #22c55e;
}

.timeline-marker {
  position: absolute;
  left: -6px;
  top: 0;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #e5e7eb;
}

.timeline-item.is-active .timeline-marker {
  background: #22c55e;
  box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.2);
}

.timeline-content {
  margin-left: 20px;
}

.document-card {
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.document-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.file-name {
  font-weight: 600;
  font-size: 16px;
}

.status-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

.status-badge.approved {
  background: #dcfce7;
  color: #166534;
}

.status-badge.pending {
  background: #fef3c7;
  color: #92400e;
}

.status-badge.rejected {
  background: #fee2e2;
  color: #991b1b;
}

.rejection {
  color: #dc2626;
  font-size: 14px;
  padding: 8px 12px;
  background: #fee2e2;
  border-radius: 6px;
  margin: 8px 0;
}

.active-label {
  color: #16a34a;
  font-weight: 600;
  margin: 8px 0;
}

.expired {
  color: #6b7280;
  font-size: 14px;
}

.chain-view {
  margin-top: 40px;
  padding: 24px;
  background: #f9fafb;
  border-radius: 12px;
}

.chain {
  display: flex;
  align-items: center;
  overflow-x: auto;
  padding: 20px 0;
}

.chain-item {
  display: flex;
  align-items: center;
  flex-shrink: 0;
}

.chain-node {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 16px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  min-width: 120px;
  text-align: center;
  position: relative;
}

.chain-arrow {
  font-size: 24px;
  color: #9ca3af;
  margin: 0 12px;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-top: 8px;
}

.status-dot.approved {
  background: #22c55e;
}

.status-dot.pending {
  background: #f59e0b;
}

.status-dot.rejected {
  background: #ef4444;
}
</style>
```

### 11.4 Casos de Uso de Auditoría

#### Caso 1: Auditoría CNBV

**Pregunta del auditor:** "¿Qué comprobante de domicilio usó Juan en su solicitud de enero 2026?"

```php
// Query exacta
$application = Application::find('app_001');
$doc = Document::join('documentable_relations', 'documents.id', '=', 'documentable_relations.document_id')
    ->where('documentable_relations.relatable_type', 'App\\Models\\Application')
    ->where('documentable_relations.relatable_id', $application->id)
    ->where('documentable_relations.relation_context', 'USAGE')
    ->where('documents.type', 'PROOF_OF_ADDRESS')
    ->whereNull('documentable_relations.deleted_at')
    ->select('documents.*')
    ->first();

// Respuesta: doc_003 (luz_ene.pdf)
// Aunque luego subió doc_004, la solicitud app_001 usó doc_003
```

#### Caso 2: Detección de Fraude

**Pregunta:** "¿Cuántas veces ha subido Juan comprobantes rechazados?"

```php
$rejectedCount = Document::where('documentable_type', 'App\\Models\\Person')
    ->where('documentable_id', $person->id)
    ->where('type', 'PROOF_OF_ADDRESS')
    ->where('status', 'REJECTED')
    ->count();

// Si rejectedCount > 3: Alerta de posible fraude
```

#### Caso 3: Análisis de Patrones

**Pregunta:** "¿Cuánto tiempo pasa entre subidas de documentos?"

```php
$history = Document::where('documentable_id', $person->id)
    ->where('type', 'PROOF_OF_ADDRESS')
    ->orderBy('created_at')
    ->get();

$intervals = [];
for ($i = 1; $i < $history->count(); $i++) {
    $intervals[] = $history[$i]->created_at->diffInDays($history[$i-1]->created_at);
}

// Análisis: Si intervalos son < 1 día repetidamente → posible automatización/fraude
```

---

## 12. Referencias

- [Stripe Identity API Documentation](https://stripe.com/docs/identity)
- [Plaid Identity Verification](https://plaid.com/docs/identity-verification/)
- [Bi-temporal Data Patterns (Martin Fowler)](https://martinfowler.com/articles/bitemporal-history.html)
- [CNBV Circular Única de Bancos](https://www.cnbv.gob.mx/)
- [Polymorphic Relationships in Laravel](https://laravel.com/docs/eloquent-relationships#polymorphic-relationships)

---

**Documento creado:** 2026-01-22
**Última actualización:** 2026-01-22
**Versión:** 1.1
**Autor:** Claude (Anthropic) + Hugo Celaya
