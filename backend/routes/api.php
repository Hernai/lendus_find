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
Route::middleware(['tenant', 'metadata'])->prefix('v2')->group(function () {
    Route::get('/config', [V2ConfigController::class, 'index']);
});

// =============================================
// V2: PUBLIC SIMULATOR (no authentication required)
// =============================================
Route::middleware(['tenant', 'metadata'])->prefix('v2/simulator')->group(function () {
    Route::post('/calculate', [V2SimulatorController::class, 'calculate']);
});

// =============================================
// V2: STAFF AUTHENTICATION (uses StaffAccount model)
// =============================================
Route::middleware(['tenant', 'metadata'])->prefix('v2/staff/auth')->group(function () {
    Route::post('/login', [StaffAuthController::class, 'login']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/me', [StaffAuthController::class, 'me']);
    });
});

// =============================================
// V2: APPLICANT AUTHENTICATION (uses ApplicantAccount model)
// =============================================
Route::middleware(['tenant', 'metadata'])->prefix('v2/applicant/auth')->group(function () {
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
Route::middleware(['tenant', 'metadata', 'auth:sanctum'])
    ->prefix('v2/applicant')
    ->group(function () {
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
                Route::post('/rfc/validate', [ApplicantKycController::class, 'validateRfc']);
                Route::post('/ine/validate', [ApplicantKycController::class, 'validateIne']);
                Route::post('/ofac/check', [ApplicantKycController::class, 'checkOfac']);
                Route::post('/pld/check', [ApplicantKycController::class, 'checkPldBlacklists']);
            });

            Route::middleware('throttle:kyc-biometric')->group(function () {
                Route::post('/biometric/token', [ApplicantKycController::class, 'getBiometricToken']);
                Route::post('/biometric/face-match', [ApplicantKycController::class, 'validateFaceMatch']);
                Route::post('/biometric/liveness', [ApplicantKycController::class, 'validateLiveness']);
            });

            Route::post('/verifications', [ApplicantKycController::class, 'recordVerifications']);
            Route::get('/verifications', [ApplicantKycController::class, 'getVerifications']);
        });
    });

// =============================================
// V2: STAFF APPLICATIONS & DOCUMENTS
// =============================================
Route::middleware(['tenant', 'metadata', 'auth:sanctum', 'staff'])
    ->prefix('v2/staff')
    ->group(function () {
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
            Route::delete('/{id}', [StaffNotificationTemplateController::class, 'destroy']);
        });

        // Applications - Read (any staff)
        Route::get('/applications', [StaffAppController::class, 'index']);
        Route::get('/applications/board', [StaffAppController::class, 'board']);
        Route::get('/applications/statistics', [StaffAppController::class, 'statistics']);
        Route::get('/applications/{id}', [StaffAppController::class, 'show']);

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

        // Application API Logs
        Route::get('/applications/{id}/api-logs', [StaffAppController::class, 'getApiLogs']);

        // Documents - Types only (other document routes not used)
        Route::get('/documents/types', [StaffDocController::class, 'types']);
    });
