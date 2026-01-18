# Arquitectura de Base de Datos Normalizada

## Resumen de Cambios

Esta serie de migraciones implementa una arquitectura de base de datos completamente normalizada, separando autenticación, datos personales, y entidades de negocio.

## Tablas Creadas

### 1. Autenticación Staff (020001)
```
staff_accounts          - Autenticación para usuarios administrativos
staff_profiles          - Información personal del staff
```

### 2. Autenticación Aplicantes (020002)
```
applicant_accounts      - Cuenta principal del solicitante
applicant_identities    - Múltiples identidades de login (teléfono, email, WhatsApp)
otp_requests            - Control de OTPs para rate limiting
```

### 3. Personas (020003)
```
persons                 - Datos base de personas físicas
```

### 4. Identificaciones (020004)
```
person_identifications  - CURP, RFC, INE, pasaporte (con historial de versiones)
```

### 5. Direcciones (020005)
```
person_addresses        - Direcciones con historial (cuando se muda)
```

### 6. Empleo (020006)
```
person_employments      - Historial de empleos
```

### 7. Referencias y Cuentas Bancarias (020007)
```
person_references       - Referencias personales/laborales
person_bank_accounts    - Cuentas bancarias (polimórfico: persona o empresa)
```

### 8. Empresas (020008)
```
companies               - Personas morales
company_addresses       - Direcciones de empresas
company_members         - Miembros con roles/permisos (tipo Clara)
```

### 9. Documentos (020009)
```
documents_v2            - Documentos con relación polimórfica
```

### 10. Solicitudes (020010)
```
applications_v2             - Solicitudes de crédito refactorizadas
application_status_history  - Historial de cambios de estado
```

## Diagrama de Relaciones

```
┌─────────────────────────────────────────────────────────────────┐
│                       AUTENTICACIÓN                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌───────────────────┐            ┌───────────────────┐         │
│  │  staff_accounts   │────1:1────▶│  staff_profiles   │         │
│  │  (email/password) │            │  (nombre, avatar) │         │
│  └───────────────────┘            └───────────────────┘         │
│                                                                  │
│  ┌───────────────────┐            ┌────────────────────┐        │
│  │applicant_accounts │────1:N────▶│applicant_identities│        │
│  │  (PIN auth)       │            │(phone, email, wa)  │        │
│  └─────────┬─────────┘            └────────────────────┘        │
│            │                                                     │
│            │ 1:1                                                 │
│            ▼                                                     │
└────────────┼────────────────────────────────────────────────────┘
             │
┌────────────┼────────────────────────────────────────────────────┐
│            ▼             PERSONAS                                │
├─────────────────────────────────────────────────────────────────┤
│  ┌───────────────────┐                                          │
│  │     persons       │                                          │
│  │ (nombre, fecha    │                                          │
│  │  nacimiento, etc) │                                          │
│  └─────────┬─────────┘                                          │
│            │                                                     │
│     ┌──────┼──────┬──────────┬────────────┬────────────┐        │
│     │      │      │          │            │            │        │
│     ▼      ▼      ▼          ▼            ▼            ▼        │
│  ┌──────┐┌──────┐┌────────┐┌──────────┐┌──────────┐┌────────┐   │
│  │iden- ││addre-││employ- ││references││bank_     ││company_│   │
│  │tifi- ││sses  ││ments   ││          ││accounts  ││members │   │
│  │catio-││      ││        ││          ││          ││        │   │
│  │ns    ││      ││        ││          ││          ││        │   │
│  └──┬───┘└──┬───┘└────────┘└──────────┘└──────────┘└────┬───┘   │
│     │       │                                           │       │
│     │       │                                           │       │
│     ▼       ▼                                           │       │
│  ┌────────────────┐                                     │       │
│  │  documents_v2  │◄────────────────────────────────────┘       │
│  │  (polimórfico) │                                             │
│  └────────────────┘                                             │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                        EMPRESAS                                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌───────────────────┐                                          │
│  │    companies      │◄───────── company_members (roles)        │
│  │  (razón social,   │                                          │
│  │   RFC, industria) │                                          │
│  └─────────┬─────────┘                                          │
│            │                                                     │
│     ┌──────┴──────┐                                             │
│     ▼             ▼                                             │
│  ┌────────┐  ┌────────────┐                                     │
│  │company_│  │bank_accounts│                                    │
│  │addres- │  │(compartido  │                                    │
│  │ses     │  │con persons) │                                    │
│  └────────┘  └─────────────┘                                    │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                      SOLICITUDES                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌───────────────────────────────────────────┐                  │
│  │            applications_v2                 │                  │
│  │                                            │                  │
│  │  applicant_type: INDIVIDUAL | COMPANY      │                  │
│  │  person_id ────────▶ persons               │                  │
│  │  company_id ───────▶ companies             │                  │
│  │                                            │                  │
│  │  snapshot_references: {                    │                  │
│  │    identification_id,                      │                  │
│  │    address_id,                             │                  │
│  │    employment_id,                          │                  │
│  │    bank_account_id                         │                  │
│  │  }                                         │                  │
│  │                                            │                  │
│  └───────────────────────────────────────────┘                  │
│            │                                                     │
│            ▼                                                     │
│  ┌───────────────────────────────────────────┐                  │
│  │    application_status_history             │                  │
│  └───────────────────────────────────────────┘                  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Características Clave

### 1. Multi-identidad para Login
Un solicitante puede tener múltiples formas de acceso:
- Teléfono + OTP
- Email + OTP
- WhatsApp + OTP
- PIN (después del primer login)

```sql
-- Ejemplo: Encontrar cuenta por cualquier identidad
SELECT aa.* FROM applicant_accounts aa
JOIN applicant_identities ai ON ai.account_id = aa.id
WHERE ai.identifier = '+5212345678901'
   OR ai.identifier = 'usuario@email.com';
```

### 2. Historial de Identificaciones
Cuando una INE expira y se renueva:
```sql
-- La INE anterior
UPDATE person_identifications
SET is_current = false, replaced_at = NOW(), replacement_reason = 'RENEWED'
WHERE id = 'old-ine-uuid';

-- La nueva INE referencia la anterior
INSERT INTO person_identifications (
    person_id, type, identifier_value, is_current, previous_version_id
) VALUES (
    'person-uuid', 'INE', 'nueva-clave', true, 'old-ine-uuid'
);
```

### 3. Historial de Direcciones
Cuando alguien se muda:
```sql
-- Consultar historial de domicilios
SELECT * FROM person_addresses
WHERE person_id = 'uuid'
ORDER BY valid_from DESC;

-- Solo el domicilio actual
SELECT * FROM person_addresses
WHERE person_id = 'uuid' AND type = 'HOME' AND is_current = true;
```

### 4. Empresas tipo Clara/Konfío
```sql
-- Ver miembros de una empresa con sus permisos
SELECT cm.*, p.full_name, cm.role, cm.permissions
FROM company_members cm
JOIN persons p ON p.id = cm.person_id
WHERE cm.company_id = 'company-uuid'
AND cm.status = 'ACTIVE';

-- Verificar si puede solicitar crédito
SELECT (cm.permissions->>'can_apply_credit')::boolean as can_apply
FROM company_members cm
WHERE cm.account_id = 'account-uuid' AND cm.company_id = 'company-uuid';
```

### 5. Snapshots en Aplicaciones
Las solicitudes guardan referencias a las versiones específicas de datos:
```sql
-- La solicitud mantiene referencia al INE que se usó
SELECT
    a.*,
    pi.identifier_value as ine_used,
    pa.full_address as address_used
FROM applications_v2 a
JOIN person_identifications pi ON pi.id = (a.snapshot_references->>'identification_id')::uuid
JOIN person_addresses pa ON pa.id = (a.snapshot_references->>'address_id')::uuid
WHERE a.id = 'application-uuid';
```

### 6. Documentos Polimórficos
```sql
-- Obtener documentos de una identificación
SELECT * FROM documents_v2
WHERE documentable_type = 'person_identifications'
AND documentable_id = 'ine-uuid';

-- Obtener todos los documentos de una aplicación
SELECT * FROM documents_v2
WHERE documentable_type = 'applications'
AND documentable_id = 'app-uuid';
```

## Migración de Datos Existentes

Las tablas nuevas tienen sufijo `_v2` para permitir migración gradual:
- `documents` → `documents_v2`
- `applications` → `applications_v2`

Se requiere una migración de datos separada para:
1. Crear `persons` desde datos en `applicants`
2. Crear `person_identifications` desde datos embebidos
3. Crear `person_addresses` desde datos embebidos
4. Migrar `documents` a `documents_v2`
5. Migrar `applications` a `applications_v2`

## Notas de Implementación

1. **Virtual Columns**: `full_name` usa `virtualAs()` en PostgreSQL
2. **JSONB**: Usado para datos flexibles (permissions, metadata, document_data)
3. **Soft Deletes**: Todas las tablas principales tienen `deleted_at`
4. **Audit Columns**: `created_by`, `updated_by`, `deleted_by` para trazabilidad
5. **Tenant Scoping**: Todas las tablas tienen `tenant_id` para multi-tenancy
