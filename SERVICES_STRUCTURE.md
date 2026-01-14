# Estructura de Servicios - LendusFind

## Resumen de Cambios

Se reorganizó la estructura de servicios para mantener una arquitectura consistente y profesional, agrupando todos los servicios de APIs externas en un solo directorio.

## Estructura Final

```
backend/app/Services/
├── DocumentService.php              # Gestión de documentos y uploads
├── MetadataService.php              # Metadata de requests (IP, device, etc.)
├── NotificationService.php          # Sistema de notificaciones
├── WebhookService.php              # Envío de webhooks a sistemas externos
│
├── Export/
│   └── ExportService.php           # Exportación de datos
│
└── ExternalApi/                    # 🆕 Todos los servicios de APIs externas
    ├── BaseExternalApiService.php  # Clase base para APIs externas
    ├── NubariumService.php         # API de KYC (CURP, RFC, INE, etc.)
    └── TwilioService.php           # 🔄 MOVIDO - API de SMS/WhatsApp
```

## Servicios de APIs Externas

### BaseExternalApiService
**Ubicación:** `backend/app/Services/ExternalApi/BaseExternalApiService.php`

Clase base abstracta que proporciona funcionalidad común para todos los servicios de APIs externas:
- Gestión de configuración por tenant
- Carga de credenciales desde `tenant_api_configs`
- Métodos helper comunes

### NubariumService
**Ubicación:** `backend/app/Services/ExternalApi/NubariumService.php`
**Namespace:** `App\Services\ExternalApi\NubariumService`
**Tamaño:** 48 KB

Servicio para validación de identidad y KYC en México:
- ✅ Validación de CURP (RENAPO)
- ✅ Validación de RFC (SAT)
- ✅ Validación de INE/IFE con OCR
- ✅ Verificación contra listas OFAC
- ✅ Verificación contra listas PLD (lavado de dinero)
- ✅ Historial IMSS/ISSSTE
- ✅ Validación de Cédula Profesional
- ✅ Auto-renovación de token JWT (401/403)

**URLs de servicios:**
- `https://api.nubarium.com` - Auth y Global
- `https://curp.nubarium.com` - Servicio de CURP
- `https://ine.nubarium.com` - Servicio de INE
- `https://ocr.nubarium.com` - Servicio de OCR
- `https://sat.nubarium.com` - Servicio de RFC/SAT

### TwilioService
**Ubicación:** `backend/app/Services/ExternalApi/TwilioService.php` (🔄 MOVIDO)
**Namespace:** `App\Services\ExternalApi\TwilioService` (actualizado)
**Tamaño:** 12 KB

Servicio para envío de SMS y WhatsApp:
- ✅ Envío de SMS
- ✅ Envío de WhatsApp
- ✅ Logging de mensajes en `sms_logs`
- ✅ Configuración por tenant
- ✅ Fallback a configuración global

## Cambios Realizados

### 1. Movimiento de Archivo
```bash
# Antes
backend/app/Services/TwilioService.php

# Después
backend/app/Services/ExternalApi/TwilioService.php
```

### 2. Namespace Actualizado
```php
# Antes
namespace App\Services;

# Después
namespace App\Services\ExternalApi;
```

### 3. Imports Actualizados

Se actualizaron los siguientes archivos para usar el nuevo namespace:

#### AuthController
**Archivo:** `backend/app/Http/Controllers/Api/AuthController.php`
```php
use App\Services\ExternalApi\TwilioService;
```

#### TenantIntegrationController
**Archivo:** `backend/app/Http/Controllers/Api/Admin/TenantIntegrationController.php`
```php
use App\Services\ExternalApi\TwilioService;
```

#### TestTwilioSms Command
**Archivo:** `backend/app/Console/Commands/TestTwilioSms.php`
```php
use App\Services\ExternalApi\TwilioService;
```

## Verificación

Todos los archivos modificados fueron verificados:
- ✅ Sin errores de sintaxis
- ✅ Clases se cargan correctamente
- ✅ Namespaces actualizados
- ✅ Imports corregidos en todos los archivos

## Uso

### TwilioService
```php
use App\Services\ExternalApi\TwilioService;

// En un controlador
$twilioService = new TwilioService(app('tenant.id'));
$result = $twilioService->sendSms($phone, $message);
```

### NubariumService
```php
use App\Services\ExternalApi\NubariumService;

// En un controlador
$nubariumService = new NubariumService($tenant);
$result = $nubariumService->validateCurp($curp);
```

## Beneficios

1. **Organización Clara:** Todos los servicios de APIs externas están en un solo lugar
2. **Consistencia:** Estructura uniforme para todos los servicios externos
3. **Escalabilidad:** Fácil agregar nuevos servicios de APIs (Stripe, PayPal, etc.)
4. **Mantenibilidad:** Más fácil encontrar y mantener servicios relacionados
5. **Separación de Responsabilidades:** Clara distinción entre servicios internos y externos

## Futuros Servicios Externos

Cuando se agreguen más servicios de APIs externas, deben seguir la misma estructura:

```
backend/app/Services/ExternalApi/
├── BaseExternalApiService.php
├── NubariumService.php
├── TwilioService.php
├── StripeService.php         # 🆕 Futura integración de pagos
├── MessageBirdService.php    # 🆕 Alternativa a Twilio
└── CirculoCreditoService.php # 🆕 Buró de crédito
```

Cada servicio debe:
- Extender `BaseExternalApiService` (si aplica)
- Usar el namespace `App\Services\ExternalApi`
- Obtener configuración de `tenant_api_configs`
- Implementar logging apropiado
- Manejar errores de manera consistente

---

**Fecha de reorganización:** 14 de enero de 2026
**Archivos modificados:** 4
**Archivos movidos:** 1
**Estado:** ✅ Completado y verificado
