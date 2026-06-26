<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api and use the 'tenant' middleware
| to identify the current tenant.
|
| V2 Architecture - All routes use the new normalized architecture with
| separate tables for staff and applicants.
|
*/

// =============================================
// V2: IMPORTS
// =============================================
use App\Http\Controllers\Api\V2\Staff\AuthController as StaffAuthController;
use App\Http\Controllers\Api\V2\Applicant\AuthController as ApplicantAuthController;
use App\Http\Controllers\Api\V2\Public\SimulatorController as V2SimulatorController;
use App\Http\Controllers\Api\V2\Public\ConfigController as V2ConfigController;
use App\Http\Controllers\Api\V2\Public\ManifestController as V2ManifestController;
use App\Http\Controllers\Api\V2\Public\HealthController as V2HealthController;
use App\Http\Controllers\Api\V2\Public\VersionController as V2VersionController;
use App\Http\Controllers\Api\V2\Applicant\DeviceController as ApplicantDeviceController;
use App\Http\Controllers\Api\V2\Staff\DeviceController as StaffDeviceController;
use App\Http\Controllers\Api\V2\Applicant\ApplicationController as ApplicantAppController;
use App\Http\Controllers\Api\V2\Applicant\CorrectionController as ApplicantCorrectionController;
use App\Http\Controllers\Api\V2\Applicant\DocumentController as ApplicantDocController;
use App\Http\Controllers\Api\V2\Applicant\DocumentHistoryController as ApplicantDocHistoryController;
use App\Http\Controllers\Api\V2\Applicant\ProfileController as ApplicantProfileController;
use App\Http\Controllers\Api\V2\Applicant\KycController as ApplicantKycController;
use App\Http\Controllers\Api\V2\Staff\ApplicationController as StaffAppController;
use App\Http\Controllers\Api\V2\Staff\DocumentController as StaffDocController;
use App\Http\Controllers\Api\V2\Staff\UserController as StaffUserController;
use App\Http\Controllers\Api\V2\Staff\ProductController as StaffProductController;
use App\Http\Controllers\Api\V2\Staff\ConfigController as StaffConfigController;
use App\Http\Controllers\Api\V2\Staff\ApiLogController as StaffApiLogController;
use App\Http\Controllers\Api\V2\Staff\TenantController as StaffTenantController;
use App\Http\Controllers\Api\V2\Staff\IntegrationController as StaffIntegrationController;
use App\Http\Controllers\Api\V2\Staff\NotificationTemplateController as StaffNotificationTemplateController;
use App\Http\Controllers\Api\V2\Applicant\NotificationPreferenceController as ApplicantNotificationPreferenceController;
use App\Http\Controllers\Api\V2\Applicant\NotificationController as ApplicantNotificationController;
use App\Http\Controllers\Api\V2\Staff\NotificationPreferenceController as StaffNotificationPreferenceController;
use App\Http\Controllers\Api\V2\Applicant\LoanController as ApplicantLoanController;
use App\Http\Controllers\Api\V2\Staff\LoanController as StaffLoanController;

// =============================================
// BROADCASTING AUTH (for WebSocket channel authorization)
// =============================================
Route::middleware(['tenant', 'auth:sanctum'])->post('/broadcasting/auth', function () {
    /** @var \Illuminate\Contracts\Auth\Guard $auth */
    $auth = auth();
    \Illuminate\Support\Facades\Log::info('Broadcasting auth request', [
        'user_id' => $auth->id(),
        'channel_name' => request('channel_name'),
        'socket_id' => request('socket_id'),
    ]);

    try {
        $response = Broadcast::auth(request());
        \Illuminate\Support\Facades\Log::info('Broadcasting auth success', ['response' => $response]);
        return $response;
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Broadcasting auth error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        throw $e;
    }
});

// =============================================
// V2: PUBLIC CONFIG (no authentication required)
// =============================================
// `etag` agrega ETag debil y responde 304 si el cliente envia
// If-None-Match con el mismo hash. Ahorra el body (~5-50KB) y reduce
// latencia a ~30ms desde el cliente cuando el payload no cambio.
Route::middleware(['tenant', 'metadata', 'etag'])->prefix('v2')->group(function () {
    Route::get('/config', [V2ConfigController::class, 'index']);
    Route::get('/public/manifest', V2ManifestController::class);
    Route::get('/public/version', V2VersionController::class);
});

// Health check no requiere tenant (es status del backend).
Route::get('v2/public/health', V2HealthController::class);

// Stats de OPCache para monitoreo. Sin auth — restringido por IP allowlist
// dentro del controller. Ver deploy-ops skill seccion 22.5.
Route::get('ops/opcache-stats', \App\Http\Controllers\Ops\OpcacheStatsController::class);

// Warmup del opcache de un worker PHP-FPM. Toca todas las clases del path
// login + autenticado para que el primer hit real no pague cold-compile
// (~2s -> ~30ms). Restringido por IP allowlist al loopback + LAN. Lo
// dispara cron `/etc/cron.d/lendus-apifind-warmup` cada minuto.
Route::get('ops/warmup', \App\Http\Controllers\Ops\WarmupController::class);

// Webhooks entrantes de Nubarium (validaciones asíncronas: CLABE, débito,
// IMSS, ISSSTE). PÚBLICO: Nubarium llama desde fuera, sin auth ni tenant. La
// seguridad es el `token` secreto y aleatorio en la URL (uno por validación).
Route::post('webhooks/nubarium/{type}/{token}', [\App\Http\Controllers\Api\Webhooks\NubariumWebhookController::class, 'handle'])
    ->where('type', 'clabe|debit_card|imss_nss|imss_employment|issste')
    ->where('token', '[A-Za-z0-9]+');

// =============================================
// V2: PUBLIC SIMULATOR (no authentication required)
// =============================================
Route::middleware(['tenant', 'metadata'])->prefix('v2/simulator')->group(function () {
    Route::post('/calculate', [V2SimulatorController::class, 'calculate']);
});

// =============================================
// V2: STAFF AUTHENTICATION (uses StaffAccount model)
// =============================================
Route::middleware(['tenant', 'metadata', 'log.request'])->prefix('v2/staff/auth')->group(function () {
    // Throttle: 5 intentos por minuto por IP. Bloquea brute force de
    // credenciales del backoffice (compliance CNBV / mejor práctica).
    Route::middleware('throttle:5,1')->post('/login', [StaffAuthController::class, 'login']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/me', [StaffAuthController::class, 'me']);
        Route::post('/logout', [StaffAuthController::class, 'logout']);
    });
});

// =============================================
// V2: APPLICANT AUTHENTICATION (uses ApplicantAccount model)
// =============================================
Route::middleware(['tenant', 'metadata', 'log.request'])->prefix('v2/applicant/auth')->group(function () {
    Route::middleware('throttle:otp')->post('/otp/request', [ApplicantAuthController::class, 'requestOtp']);
    Route::middleware('throttle:otp-verify')->post('/otp/verify', [ApplicantAuthController::class, 'verifyOtp']);
    Route::post('/check-user', [ApplicantAuthController::class, 'checkUser']);
    Route::middleware('throttle:pin-login')->post('/pin/login', [ApplicantAuthController::class, 'loginWithPin']);
    Route::middleware('throttle:otp-verify')->post('/pin/reset', [ApplicantAuthController::class, 'resetPinWithOtp']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/me', [ApplicantAuthController::class, 'me']);
        Route::post('/logout', [ApplicantAuthController::class, 'logout']);
        Route::post('/pin/setup', [ApplicantAuthController::class, 'setupPin']);
        Route::post('/pin/change', [ApplicantAuthController::class, 'changePin']);
    });
});

// =============================================
// V2: PERSON MANAGEMENT ROUTES
// =============================================
require __DIR__ . '/api/person.php';

// =============================================
// V2: APPLICANT APPLICATIONS, DOCUMENTS & PROFILE
// =============================================
Route::middleware(['tenant', 'metadata', 'auth:sanctum', 'log.request'])
    ->prefix('v2/applicant')
    ->group(function () {
        // =============================================
        // Device tokens (push notifications)
        // =============================================
        Route::post('/devices', [ApplicantDeviceController::class, 'register']);
        Route::delete('/devices/{token}', [ApplicantDeviceController::class, 'unregister'])
            ->where('token', '.*');

        // =============================================
        // Loan Portfolio (applicant) — opt-in por tenant.features.loan_portfolio
        // =============================================
        Route::get('/loans', [ApplicantLoanController::class, 'index']);
        Route::get('/loans/{id}', [ApplicantLoanController::class, 'show']);
        Route::post('/loans/{id}/extension/quote', [ApplicantLoanController::class, 'quoteExtension']);
        Route::post('/loans/{id}/extension', [ApplicantLoanController::class, 'requestExtension']);
        Route::post('/loans/{id}/pay', [ApplicantLoanController::class, 'pay']);

        // =============================================
        // Profile Management
        // =============================================
        Route::get('/profile', [ApplicantProfileController::class, 'show']);
        Route::prefix('profile')->group(function () {
            // Personal data
            Route::patch('/personal-data', [ApplicantProfileController::class, 'updatePersonalData']);
            Route::patch('/identifications', [ApplicantProfileController::class, 'updateIdentifications']);

            // Address
            Route::get('/address', [ApplicantProfileController::class, 'getAddress']);
            Route::put('/address', [ApplicantProfileController::class, 'updateAddress']);

            // Employment
            Route::get('/employment', [ApplicantProfileController::class, 'getEmployment']);
            Route::put('/employment', [ApplicantProfileController::class, 'updateEmployment']);

            // Bank accounts
            Route::get('/bank-accounts', [ApplicantProfileController::class, 'listBankAccounts']);
            Route::post('/bank-accounts', [ApplicantProfileController::class, 'storeBankAccount']);
            Route::patch('/bank-accounts/{id}/primary', [ApplicantProfileController::class, 'setPrimaryBankAccount']);
            Route::delete('/bank-accounts/{id}', [ApplicantProfileController::class, 'deleteBankAccount']);
            Route::post('/validate-clabe', [ApplicantProfileController::class, 'validateClabe']);

            // References
            Route::get('/references', [ApplicantProfileController::class, 'listReferences']);
            Route::post('/references', [ApplicantProfileController::class, 'storeReference']);

            // Signature
            Route::post('/signature', [ApplicantProfileController::class, 'saveSignature']);
        });

        // =============================================
        // Applications
        // =============================================
        Route::get('/applications', [ApplicantAppController::class, 'index']);
        Route::post('/applications', [ApplicantAppController::class, 'store']);
        Route::get('/applications/{id}', [ApplicantAppController::class, 'show']);
        Route::patch('/applications/{id}', [ApplicantAppController::class, 'update']);
        Route::post('/applications/{id}/submit', [ApplicantAppController::class, 'submit']);
        Route::post('/applications/{id}/cancel', [ApplicantAppController::class, 'cancel']);
        Route::post('/applications/{id}/counter-offer/respond', [ApplicantAppController::class, 'respondToCounterOffer']);

        // =============================================
        // Documents
        // =============================================
        Route::get('/documents', [ApplicantDocController::class, 'index']);
        Route::post('/documents', [ApplicantDocController::class, 'store']);
        Route::get('/documents/types', [ApplicantDocController::class, 'types']);
        Route::get('/documents/{id}/download', [ApplicantDocController::class, 'download']);
        Route::get('/documents/{id}/stream', [ApplicantDocController::class, 'stream']);
        Route::delete('/documents/{id}', [ApplicantDocController::class, 'destroy']);

        // Document History & Audit
        Route::get('/documents/history/{type}', [ApplicantDocHistoryController::class, 'index']);
        Route::get('/documents/{id}/supersession-chain', [ApplicantDocHistoryController::class, 'supersessionChain']);
        Route::get('/documents/valid-at', [ApplicantDocHistoryController::class, 'validAt']);
        Route::get('/documents/timeline', [ApplicantDocHistoryController::class, 'timeline']);

        // =============================================
        // Data Corrections (for rejected fields)
        // =============================================
        Route::get('/corrections', [ApplicantCorrectionController::class, 'index']);
        Route::post('/corrections', [ApplicantCorrectionController::class, 'submitCorrection']);

        // =============================================
        // KYC - Identity Validation Services
        // =============================================
        Route::prefix('kyc')->group(function () {
            Route::get('/services', [ApplicantKycController::class, 'services']);

            Route::middleware('throttle:kyc')->group(function () {
                Route::post('/test-connection', [ApplicantKycController::class, 'testConnection']);
                Route::post('/refresh-token', [ApplicantKycController::class, 'refreshToken']);
            });

            Route::middleware('throttle:kyc')->group(function () {
                Route::post('/curp/validate', [ApplicantKycController::class, 'validateCurp']);
                Route::post('/curp/get', [ApplicantKycController::class, 'getCurp']);
                Route::post('/rfc/validate', [ApplicantKycController::class, 'validateRfc']);
                Route::post('/ine/validate', [ApplicantKycController::class, 'validateIne']);
                Route::post('/ofac/check', [ApplicantKycController::class, 'checkOfac']);
                Route::post('/pld/check', [ApplicantKycController::class, 'checkPldBlacklists']);
                // Servicios México síncronos (Banxico / SEP)
                Route::post('/cep/validate', [ApplicantKycController::class, 'validateCep']);
                Route::post('/cedula/validate', [ApplicantKycController::class, 'validateCedula']);
                // Validación de cuenta bancaria (CLABE) — async por webhook
                Route::post('/clabe/validate', [ApplicantKycController::class, 'validateClabe']);
            });

            Route::middleware('throttle:kyc-biometric')->group(function () {
                Route::post('/biometric/token', [ApplicantKycController::class, 'getBiometricToken']);
                Route::post('/biometric/face-match', [ApplicantKycController::class, 'validateFaceMatch']);
                Route::post('/biometric/liveness', [ApplicantKycController::class, 'validateLiveness']);
            });

            Route::post('/verifications', [ApplicantKycController::class, 'recordVerifications']);
            Route::get('/verifications', [ApplicantKycController::class, 'getVerifications']);
            Route::post('/verifications/check', [ApplicantKycController::class, 'checkFieldsVerified']);

            // Estado/resultado de una validación asíncrona (CLABE, débito, etc.)
            Route::get('/async/{id}', [ApplicantKycController::class, 'getAsyncValidation']);
        });

        // =============================================
        // Notification Preferences
        // =============================================
        Route::prefix('notification-preferences')->group(function () {
            Route::get('/', [ApplicantNotificationPreferenceController::class, 'show']);
            Route::put('/', [ApplicantNotificationPreferenceController::class, 'update']);
            Route::post('/events/{event}/disable', [ApplicantNotificationPreferenceController::class, 'disableEvent']);
            Route::post('/events/{event}/enable', [ApplicantNotificationPreferenceController::class, 'enableEvent']);
        });

        // =============================================
        // Notifications (In-App)
        // =============================================
        Route::prefix('notifications')->group(function () {
            Route::get('/', [ApplicantNotificationController::class, 'index']);
            Route::get('/unread-count', [ApplicantNotificationController::class, 'unreadCount']);
            Route::patch('/{id}/read', [ApplicantNotificationController::class, 'markAsRead']);
            Route::post('/mark-all-read', [ApplicantNotificationController::class, 'markAllAsRead']);
        });
    });

// =============================================
// V2: STAFF APPLICATIONS & DOCUMENTS
// =============================================
Route::middleware(['tenant', 'metadata', 'auth:sanctum', 'staff', 'log.request'])
    ->prefix('v2/staff')
    ->group(function () {
        // =============================================
        // Selector de tenant del usuario autenticado
        // (super admin global → todos; staff per-tenant → solo el suyo)
        // =============================================
        Route::get('/me/tenants', [\App\Http\Controllers\Api\V2\Staff\AuthController::class, 'availableTenants'])
            ->middleware('etag');

        // =============================================
        // Configuración de módulos visibles por (tenant, rol)
        // SUPER_ADMIN global only — validación en controller
        // =============================================
        Route::get('/tenants/{id}/modules', [\App\Http\Controllers\Api\V2\Staff\TenantModuleController::class, 'index']);
        Route::put('/tenants/{id}/modules', [\App\Http\Controllers\Api\V2\Staff\TenantModuleController::class, 'update']);

        // =============================================
        // Device tokens (push notifications)
        // =============================================
        Route::post('/devices', [StaffDeviceController::class, 'register']);
        Route::delete('/devices/{token}', [StaffDeviceController::class, 'unregister'])
            ->where('token', '.*');

        // =============================================
        // Users Management - Admin only
        // =============================================
        Route::middleware('permission:canManageUsers')->group(function () {
            Route::get('/users', [StaffUserController::class, 'index']);
            Route::post('/users', [StaffUserController::class, 'store']);
            Route::put('/users/{id}', [StaffUserController::class, 'update']);
            Route::patch('/users/{id}', [StaffUserController::class, 'update']);
            Route::delete('/users/{id}', [StaffUserController::class, 'destroy']);
        });

        // =============================================
        // Products Management - Admin only
        // =============================================
        Route::middleware('permission:canManageProducts')->group(function () {
            Route::get('/products', [StaffProductController::class, 'index']);
            Route::post('/products', [StaffProductController::class, 'store']);
            Route::put('/products/{id}', [StaffProductController::class, 'update']);
            Route::patch('/products/{id}', [StaffProductController::class, 'update']);
            Route::delete('/products/{id}', [StaffProductController::class, 'destroy']);
        });

        // =============================================
        // Tenant Configuration - Admin only
        // =============================================
        Route::middleware('permission:canManageProducts')->prefix('config')->group(function () {
            Route::get('/', [StaffConfigController::class, 'show']);
            Route::put('/tenant', [StaffConfigController::class, 'updateTenant']);
            Route::put('/branding', [StaffConfigController::class, 'updateBranding']);
            Route::post('/api-configs', [StaffConfigController::class, 'saveApiConfig']);
            Route::delete('/api-configs/{id}', [StaffConfigController::class, 'deleteApiConfig']);
            Route::post('/api-configs/{id}/test', [StaffConfigController::class, 'testApiConfig']);
        });

        // =============================================
        // Integrations Management - Super Admin only
        // =============================================
        Route::middleware('permission:canConfigureTenant')->prefix('integrations')->group(function () {
            Route::get('/', [StaffIntegrationController::class, 'index']);
            Route::get('/options', [StaffIntegrationController::class, 'options']);
            Route::post('/', [StaffIntegrationController::class, 'store']);
            Route::post('/{id}/test', [StaffIntegrationController::class, 'test']);
            Route::patch('/{id}/toggle', [StaffIntegrationController::class, 'toggle']);
            Route::delete('/{id}', [StaffIntegrationController::class, 'destroy']);
        });

        // =============================================
        // API Logs - Admin only
        // =============================================
        Route::middleware('permission:canManageProducts')->prefix('api-logs')->group(function () {
            Route::get('/', [StaffApiLogController::class, 'index']);
            Route::get('/stats', [StaffApiLogController::class, 'stats']);
            Route::get('/providers', [StaffApiLogController::class, 'providers']);
            Route::get('/{id}', [StaffApiLogController::class, 'show']);
        });

        // =============================================
        // Tenants Management - Super Admin only
        // =============================================
        Route::middleware('permission:canConfigureTenant')->prefix('tenants')->group(function () {
            Route::get('/', [StaffTenantController::class, 'index']);
            Route::post('/', [StaffTenantController::class, 'store']);
            Route::put('/{id}', [StaffTenantController::class, 'update']);
            Route::delete('/{id}', [StaffTenantController::class, 'destroy']);
            Route::get('/{id}/config', [StaffTenantController::class, 'getConfig']);
            Route::put('/{id}/branding', [StaffTenantController::class, 'updateBranding']);
            Route::post('/{id}/upload-logo', [StaffTenantController::class, 'uploadLogo']);
            Route::post('/{id}/api-configs', [StaffTenantController::class, 'saveApiConfig']);
            Route::delete('/{id}/api-configs/{configId}', [StaffTenantController::class, 'deleteApiConfig']);
            Route::post('/{id}/api-configs/{configId}/test', [StaffTenantController::class, 'testApiConfig']);
        });

        // =============================================
        // Notification Templates - Admin only
        // =============================================
        Route::middleware('permission:canManageProducts')->prefix('notification-templates')->group(function () {
            Route::get('/config', [StaffNotificationTemplateController::class, 'config']);
            Route::post('/test-render', [StaffNotificationTemplateController::class, 'testRender']);
            Route::get('/', [StaffNotificationTemplateController::class, 'index']);
            Route::post('/', [StaffNotificationTemplateController::class, 'store']);
            Route::get('/{id}', [StaffNotificationTemplateController::class, 'show']);
            Route::put('/{id}', [StaffNotificationTemplateController::class, 'update']);
            Route::post('/{id}/send-test', [StaffNotificationTemplateController::class, 'sendTest']);
            Route::delete('/{id}', [StaffNotificationTemplateController::class, 'destroy']);
        });

        // Applications - Read (any staff)
        // IMPORTANTE: rutas con segmentos fijos (unassigned, my-queue,
        // board, statistics) DEBEN declararse ANTES de /{id}, de lo
        // contrario Laravel matchea /applications/unassigned como
        // /applications/{id} con id='unassigned' y la query a Postgres
        // truena con "invalid input syntax for type uuid".
        Route::get('/applications', [StaffAppController::class, 'index']);
        Route::get('/applications/board', [StaffAppController::class, 'board']);
        Route::get('/applications/statistics', [StaffAppController::class, 'statistics']);
        Route::get('/applications/unassigned', [StaffAppController::class, 'unassigned']);
        Route::get('/applications/my-queue', [StaffAppController::class, 'myQueue']);
        Route::get('/applications/{id}', [StaffAppController::class, 'show']);
        // Audit logs por applicant/application eliminados: el feed unificado
        // los expone via /applications/{id}/activity?kind=audit.

        // Loan Portfolio (staff)
        Route::get('/loans', [StaffLoanController::class, 'index']);
        Route::get('/loans/{id}', [StaffLoanController::class, 'show']);
        Route::post('/loans/{id}/payments', [StaffLoanController::class, 'recordPayment']);
        Route::post('/loans/{loanId}/extensions/{extensionId}/approve', [StaffLoanController::class, 'approveExtension']);

        // Applications - Actions requiring permissions
        Route::post('/applications/{id}/assign', [StaffAppController::class, 'assign'])
            ->middleware('permission:canAssignApplications');
        Route::post('/applications/{id}/status', [StaffAppController::class, 'changeStatus'])
            ->middleware('permission:canChangeApplicationStatus');
        Route::post('/applications/{id}/reject', [StaffAppController::class, 'reject'])
            ->middleware(['permission:canApproveRejectApplications', 'throttle:30,1']);
        Route::post('/applications/{id}/counter-offer', [StaffAppController::class, 'sendCounterOffer'])
            ->middleware(['permission:canApproveRejectApplications', 'throttle:30,1']);

        // Application Notes
        Route::post('/applications/{id}/notes', [StaffAppController::class, 'addNote']);

        // Application Documents (nested under application)
        Route::get('/applications/{appId}/documents/{docId}/url', [StaffAppController::class, 'getDocumentUrl']);
        Route::get('/applications/{appId}/documents/{docId}/download', [StaffAppController::class, 'downloadDocument']);
        Route::put('/applications/{appId}/documents/{docId}/approve', [StaffAppController::class, 'approveDocument'])
            ->middleware('permission:canReviewDocuments');
        Route::put('/applications/{appId}/documents/{docId}/reject', [StaffAppController::class, 'rejectDocument'])
            ->middleware('permission:canReviewDocuments');
        Route::put('/applications/{appId}/documents/{docId}/unapprove', [StaffAppController::class, 'unapproveDocument'])
            ->middleware('permission:canReviewDocuments');

        // Application References
        Route::put('/applications/{appId}/references/{refId}/verify', [StaffAppController::class, 'verifyReference'])
            ->middleware('permission:canVerifyReferences');

        // Application Bank Accounts
        Route::put('/applications/{appId}/bank-accounts/{baId}/verify', [StaffAppController::class, 'verifyBankAccount'])
            ->middleware('permission:canVerifyReferences');
        Route::put('/applications/{appId}/bank-accounts/{baId}/unverify', [StaffAppController::class, 'unverifyBankAccount'])
            ->middleware('permission:canVerifyReferences');

        // Application Data Verification
        Route::put('/applications/{id}/verify-data', [StaffAppController::class, 'verifyData'])
            ->middleware('permission:canVerifyReferences');

        // Application Activity Feed (unified: status_history + audit + api logs)
        Route::get('/applications/{id}/activity', [StaffAppController::class, 'activity']);
        // /applications/{id}/api-logs eliminado: usar /activity?kind=api

        // Documents - Types only (other document routes not used)
        Route::get('/documents/types', [StaffDocController::class, 'types']);

        // =============================================
        // Notification Preferences
        // =============================================
        Route::prefix('notification-preferences')->group(function () {
            Route::get('/', [StaffNotificationPreferenceController::class, 'show']);
            Route::put('/', [StaffNotificationPreferenceController::class, 'update']);
            Route::post('/events/{event}/disable', [StaffNotificationPreferenceController::class, 'disableEvent']);
            Route::post('/events/{event}/enable', [StaffNotificationPreferenceController::class, 'enableEvent']);
        });
    });
