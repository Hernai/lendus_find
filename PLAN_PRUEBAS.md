# Plan de Pruebas - LendusFind Onboarding

## Resumen Ejecutivo
Este documento describe el plan de pruebas end-to-end para el flujo completo de onboarding de LendusFind, desde la simulación de crédito hasta el envío de la solicitud.

---

## 1. Prerrequisitos

### 1.1 Limpiar Estado Anterior
Antes de cada prueba completa, ejecutar en la consola del navegador:
```javascript
localStorage.clear()
sessionStorage.clear()
```

### 1.2 Verificar Servicios
- [ ] Backend Laravel corriendo en `http://localhost:8000`
- [ ] Frontend Vue corriendo en `http://localhost:5173`
- [ ] PostgreSQL corriendo y accesible
- [ ] Tenant "demo" existente en la base de datos

### 1.3 Datos de Prueba
```
Teléfono: 5512345678
OTP (dev): 123456
```

---

## 2. Flujo de Pruebas

### FASE 1: Autenticación

#### TC-001: Página de Inicio
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Navegar a `http://localhost:5173/` | Página de inicio carga correctamente |
| 2 | Verificar branding del tenant | Logo, colores primarios visibles |
| 3 | Verificar botón "Simular crédito" | Botón visible y clickeable |

#### TC-002: Solicitud de OTP
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Click en "Simular" o "Solicitar" | Redirección a página de login/OTP |
| 2 | Ingresar teléfono: `5512345678` | Campo acepta 10 dígitos |
| 3 | Click "Enviar código" | Mensaje "Código enviado" aparece |
| 4 | Verificar llamada API | `POST /api/auth/otp/request` responde 200 |

#### TC-003: Verificación de OTP
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Ingresar código OTP: `123456` | Campo acepta 6 dígitos |
| 2 | Click "Verificar" | Autenticación exitosa |
| 3 | Verificar llamada API | `POST /api/auth/otp/verify` responde 200 con token |
| 4 | Verificar localStorage | `auth_token` guardado |

---

### FASE 2: Simulador de Crédito

#### TC-004: Cargar Productos
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Navegar al simulador | Página carga sin errores |
| 2 | Verificar productos | Lista de productos disponibles |
| 3 | Verificar API | `GET /api/config` retorna productos |

#### TC-005: Configurar Simulación
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Seleccionar producto "Crédito Personal" | Producto seleccionado |
| 2 | Ajustar monto: $25,000 | Slider/input actualiza |
| 3 | Seleccionar plazo: 12 meses | Plazo seleccionado |
| 4 | Seleccionar frecuencia: Quincenal | Frecuencia seleccionada |
| 5 | Verificar cálculos | Pago periódico, CAT, total calculados |

#### TC-006: Ejecutar Simulación
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Click "Simular" | Cálculo ejecutado |
| 2 | Verificar API | `POST /api/simulator` responde 200 |
| 3 | Verificar tabla amortización | Pagos detallados mostrados |

#### TC-007: Iniciar Solicitud
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Click "Solicitar este crédito" | Redirección a onboarding |
| 2 | Verificar localStorage | `pending_application` con datos de simulación |
| 3 | Verificar creación de aplicación | `POST /api/applications` responde 201 |
| 4 | Verificar localStorage | `current_application_id` con UUID válido |

---

### FASE 3: Onboarding - Datos Personales

#### TC-008: Step 1 - Datos Personales
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Verificar URL | `/solicitud/paso-1` |
| 2 | Verificar progress bar | Paso 1 de 8 |
| 3 | Ingresar nombre: `JUAN CARLOS` | Campo acepta mayúsculas |
| 4 | Ingresar apellido paterno: `PEREZ` | Campo acepta mayúsculas |
| 5 | Ingresar apellido materno: `GARCIA` | Campo acepta mayúsculas |
| 6 | Seleccionar fecha nacimiento: `1990-05-15` | Calendario funciona |
| 7 | Seleccionar género: `Masculino` | Radio button seleccionado |
| 8 | Seleccionar "Sí soy mexicano" | Radio button seleccionado |
| 9 | Seleccionar entidad: `Ciudad de México` | Select funciona |
| 10 | Click "Continuar" | Navegación a paso 2 |
| 11 | Verificar API | `PUT /api/applicant` responde 200 |

---

### FASE 4: Onboarding - Identificación

#### TC-009: Step 2 - Identificación (INE)
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Verificar URL | `/solicitud/paso-2` |
| 2 | Seleccionar tipo ID: INE | Tab INE activo |
| 3 | Ingresar CURP: `PEGJ900515HDFRRN09` | 18 caracteres, validación format |
| 4 | Ingresar RFC: `PEGJ900515XXX` | 13 caracteres |
| 5 | Ingresar Clave Elector: `PRGRJN90051509H100` | 18 caracteres |
| 6 | Ingresar Número OCR: `1234567890123` | 13 dígitos |
| 7 | Ingresar Folio INE: `<<01234567890123<<` | Formato correcto |
| 8 | Click "Continuar" | Navegación a paso 3 |
| 9 | Verificar API | `PUT /api/applicant` responde 200 |

#### TC-010: Step 2 - Identificación (Pasaporte)
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Seleccionar tipo ID: Pasaporte | Tab Pasaporte activo |
| 2 | Ingresar número pasaporte | Campo visible |
| 3 | Seleccionar fecha emisión | Calendario funciona |
| 4 | Seleccionar fecha vencimiento | Calendario funciona, >= hoy |

---

### FASE 5: Onboarding - Domicilio

#### TC-011: Step 3 - Dirección
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Verificar URL | `/solicitud/paso-3` |
| 2 | Ingresar código postal: `03100` | 5 dígitos |
| 3 | Verificar API SEPOMEX | Colonias cargadas automáticamente |
| 4 | Seleccionar colonia | Opciones disponibles |
| 5 | Ingresar calle: `Av. Insurgentes Sur` | Campo acepta texto |
| 6 | Ingresar número exterior: `1234` | Campo numérico |
| 7 | Ingresar número interior: `5A` | Campo opcional |
| 8 | Seleccionar tipo vivienda: `Propia pagada` | Select funciona |
| 9 | Ingresar años viviendo: `5` | Campo numérico |
| 10 | Click "Continuar" | Navegación a paso 4 |
| 11 | Verificar API | `POST /api/applicant/address` responde 201 |

---

### FASE 6: Onboarding - Empleo

#### TC-012: Step 4 - Información Laboral
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Verificar URL | `/solicitud/paso-4` |
| 2 | Seleccionar tipo empleo: `Empleado` | Select funciona |
| 3 | Ingresar empresa: `Empresa ABC` | Campo texto |
| 4 | Ingresar puesto: `Desarrollador` | Campo texto |
| 5 | Ingresar ingreso mensual: `25000` | Campo numérico |
| 6 | Ingresar antigüedad: `3 años` | Campo numérico |
| 7 | Click "Continuar" | Navegación a paso 5 |
| 8 | Verificar API | `POST /api/applicant/employment` responde 201 |

---

### FASE 7: Onboarding - Confirmación Crédito

#### TC-013: Step 5 - Detalles del Crédito
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Verificar URL | `/solicitud/paso-5` |
| 2 | Verificar resumen simulación | Monto, plazo, pago mostrados |
| 3 | Verificar desglose costos | Comisión, intereses, total |
| 4 | Seleccionar propósito: `Gastos personales` | Select funciona |
| 5 | Click "Continuar" | Navegación a paso 6 |
| 6 | Verificar API | `PUT /api/applications/{id}` responde 200 |

---

### FASE 8: Onboarding - Documentos

#### TC-014: Step 6 - Carga de Documentos
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Verificar URL | `/solicitud/paso-6` |
| 2 | Verificar lista documentos requeridos | INE frente, INE reverso, comprobante domicilio |
| 3 | Subir INE frente | Archivo cargado |
| 4 | Verificar preview | Imagen visible |
| 5 | Subir INE reverso | Archivo cargado |
| 6 | Subir comprobante domicilio | Archivo cargado |
| 7 | Click "Continuar" | Navegación a paso 7 |
| 8 | Verificar API | `POST /api/documents` para cada archivo |

---

### FASE 9: Onboarding - Referencias

#### TC-015: Step 7 - Referencias Personales
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Verificar URL | `/solicitud/paso-7` |
| 2 | **Referencia 1 (Familiar):** | |
| 3 | Ingresar nombre: `MARIA` | Campo mayúsculas |
| 4 | Ingresar apellido paterno: `PEREZ` | Campo mayúsculas |
| 5 | Ingresar apellido materno: `LOPEZ` | Campo opcional |
| 6 | Seleccionar parentesco: `Hermano(a)` | Select familiar |
| 7 | Ingresar teléfono: `5598765432` | 10 dígitos |
| 8 | **Referencia 2 (No Familiar):** | |
| 9 | Ingresar nombre: `PEDRO` | Campo mayúsculas |
| 10 | Ingresar apellido paterno: `MARTINEZ` | Campo mayúsculas |
| 11 | Seleccionar relación: `Amigo(a)` | Select no familiar |
| 12 | Ingresar teléfono: `5587654321` | 10 dígitos diferentes |
| 13 | Click "Continuar" | Navegación a paso 8 |
| 14 | Verificar API | `POST /api/applications/{id}/references` x2 |

---

### FASE 10: Onboarding - Revisión y Envío

#### TC-016: Step 8 - Revisión Final
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Verificar URL | `/solicitud/paso-8` |
| 2 | Verificar resumen datos personales | Nombre completo, CURP, RFC |
| 3 | Verificar resumen domicilio | Dirección completa |
| 4 | Verificar resumen empleo | Tipo, ingreso |
| 5 | Verificar resumen crédito | Monto, plazo, pago |
| 6 | Aceptar términos y condiciones | Checkbox marcado |
| 7 | Aceptar aviso de privacidad | Checkbox marcado |
| 8 | Autorizar consulta buró | Checkbox marcado |
| 9 | Firmar digitalmente | Firma en pad capturada |
| 10 | Verificar botón habilitado | "Enviar solicitud" activo |

#### TC-017: Envío de Solicitud
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Click "Enviar solicitud" | Loading state visible |
| 2 | Verificar firma guardada | `POST /api/applicant/signature` responde 200 |
| 3 | Verificar aplicación actualizada | `PUT /api/applications/{id}` responde 200 |
| 4 | Verificar submit | `POST /api/applications/{id}/submit` responde 200 |
| 5 | Verificar redirección | `/solicitud/{id}/estado` |
| 6 | Verificar estado | "Solicitud enviada" o similar |
| 7 | Verificar localStorage limpio | `current_application_id` eliminado |

---

## 3. Pruebas de Error

### TC-018: Validación de Campos
| Escenario | Acción | Resultado Esperado |
|-----------|--------|-------------------|
| Campo vacío requerido | Dejar nombre vacío, click continuar | Error "El nombre es requerido" |
| CURP inválido | Ingresar CURP < 18 chars | Error de formato |
| Teléfono duplicado refs | Mismo teléfono en ambas refs | Error "Teléfonos deben ser diferentes" |
| Sin firma | Intentar enviar sin firmar | Error "Debes firmar la solicitud" |

### TC-019: Errores de API
| Escenario | Acción | Resultado Esperado |
|-----------|--------|-------------------|
| Sin conexión | Desactivar red | Error amigable mostrado |
| Token expirado | Esperar expiración | Redirección a login |
| Aplicación no encontrada | ID inválido | Error "No se encontró la solicitud" |

### TC-020: Navegación
| Escenario | Acción | Resultado Esperado |
|-----------|--------|-------------------|
| Refresh página | F5 en paso 3 | Datos persistidos, continúa en paso 3 |
| Navegar atrás | Click "Anterior" | Regresa al paso anterior con datos |
| URL directa | Ir directo a /paso-5 sin auth | Redirección a inicio/login |

---

## 4. Pruebas de Regresión

### TC-021: Persistencia de Datos
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Completar hasta paso 3 | Datos guardados |
| 2 | Cerrar navegador | - |
| 3 | Abrir navegador nuevamente | Continuar desde paso 3 |
| 4 | Verificar datos anteriores | Pasos 1-2 con datos correctos |

### TC-022: Multi-dispositivo
| Paso | Acción | Resultado Esperado |
|------|--------|-------------------|
| 1 | Iniciar en dispositivo A | Aplicación creada |
| 2 | Continuar en dispositivo B | Cargar borrador existente |
| 3 | Verificar sincronización | Datos correctos en B |

---

## 5. Checklist Final

### Antes del Deploy
- [ ] Todos los TC-001 a TC-022 pasados
- [ ] Console sin errores JavaScript
- [ ] Network sin llamadas fallidas (4xx, 5xx)
- [ ] localStorage se limpia correctamente post-submit
- [ ] Responsive: probado en mobile (375px)
- [ ] Responsive: probado en tablet (768px)
- [ ] Responsive: probado en desktop (1280px)

### Datos a Verificar en BD (PostgreSQL)
```sql
-- Verificar aplicación creada
SELECT id, status, requested_amount FROM applications WHERE status = 'SUBMITTED';

-- Verificar aplicante
SELECT id, first_name, last_name_1, curp, signature_base64 IS NOT NULL as has_signature
FROM applicants WHERE id = '[applicant_id]';

-- Verificar referencias
SELECT full_name, relationship, phone FROM references
WHERE application_id = '[app_id]';

-- Verificar dirección
SELECT street, postal_code, neighborhood FROM addresses
WHERE applicant_id = '[applicant_id]';

-- Verificar empleo
SELECT employment_type, monthly_income FROM employment_records
WHERE applicant_id = '[applicant_id]';
```

---

## 6. Comandos Útiles

### Reiniciar Base de Datos
```bash
cd backend
php artisan migrate:fresh --seed
```

### Ver Logs Backend
```bash
tail -f backend/storage/logs/laravel.log
```

### Limpiar Cache Laravel
```bash
php artisan config:clear && php artisan cache:clear
```

---

**Última actualización:** 2026-01-07
**Autor:** Claude Code
