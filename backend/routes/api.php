<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Public\ConfigController;
use App\Http\Controllers\Api\V1\Public\SimulatorController;
use App\Http\Controllers\Api\V1\Applicant\ApplicationController;
use App\Http\Controllers\Api\V1\Applicant\ApplicantController;
use App\Http\Controllers\Api\V1\Applicant\CorrectionController;
use App\Http\Controllers\Api\V1\Applicant\DocumentController;
use App\Http\Controllers\Api\V1\Applicant\KycController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\ApplicationController as AdminApplicationController;
use App\Http\Controllers\Api\V1\Admin\ApplicationDocumentController;
use App\Http\Controllers\Api\V1\Admin\ApplicationVerificationController;
use App\Http\Controllers\Api\V1\Admin\TenantIntegrationController;
use App\Http\Controllers\Api\V1\Admin\ProductController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Admin\TenantController;
use App\Http\Controllers\Api\V1\Admin\TenantConfigController;
use App\Http\Controllers\Api\V1\Admin\ApiLogController;
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
*/

// Public routes (with tenant)
Route::middleware(['tenant'])->group(function () {
    // Tenant Configuration (public)
    Route::get('/config', [ConfigController::class, 'index']);

    // Authentication (with metadata capture for audit logging and rate limiting)
    Route::middleware(['metadata'])->prefix('auth')->group(function () {
        // OTP endpoints with strict rate limiting (3/min for send, 5/min for verify)
        Route::middleware('throttle:otp')->post('/otp/request', [AuthController::class, 'requestOtp']);
        Route::middleware('throttle:otp-verify')->post('/otp/verify', [AuthController::class, 'verifyOtp']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

        // PIN Authentication with rate limiting (5/min)
        Route::post('/check-user', [AuthController::class, 'checkUser']);
        Route::middleware('throttle:pin-login')->post('/pin/login', [AuthController::class, 'loginWithPin']);
        Route::middleware('throttle:otp-verify')->post('/pin/reset', [AuthController::class, 'resetPinWithOtp']);

        // Email/Password Authentication with rate limiting (5/min)
        Route::middleware('throttle:password-login')->post('/password/login', [AuthController::class, 'loginWithPassword']);
    });

    // PIN Setup & Change (requires authentication)
    Route::middleware(['auth:sanctum'])->prefix('auth')->group(function () {
        Route::post('/pin/setup', [AuthController::class, 'setupPin']);
        Route::post('/pin/change', [AuthController::class, 'changePin']);
    });

    // Simulator (public)
    Route::prefix('simulator')->group(function () {
        Route::get('/products', [SimulatorController::class, 'products']);
        Route::post('/calculate', [SimulatorController::class, 'calculate']);
        Route::get('/amortization', [SimulatorController::class, 'amortization']);
    });

    // Utilities (public)
    Route::post('/validate-clabe', [ApplicantController::class, 'validateClabe']);
});

// Broadcasting auth (for WebSocket channel authorization)
Route::middleware(['tenant', 'auth:sanctum'])->post('/broadcasting/auth', function () {
    \Log::info('Broadcasting auth request', [
        'user_id' => auth()->id(),
        'channel_name' => request('channel_name'),
        'socket_id' => request('socket_id'),
    ]);

    try {
        $response = Broadcast::auth(request());
        \Log::info('Broadcasting auth success', ['response' => $response]);
        return $response;
    } catch (\Exception $e) {
        \Log::error('Broadcasting auth error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        throw $e;
    }
});

// Protected routes (authenticated user within tenant)
Route::middleware(['tenant', 'auth:sanctum', 'tenant.user', 'metadata'])->group(function () {
    // Current user
    Route::get('/me', [AuthController::class, 'me']);

    // Applicant profile
    Route::prefix('applicant')->group(function () {
        Route::get('/', [ApplicantController::class, 'show']);
        Route::post('/', [ApplicantController::class, 'store']);
        Route::put('/', [ApplicantController::class, 'update']);
        Route::put('/personal-data', [ApplicantController::class, 'updatePersonalData']);
        Route::put('/address', [ApplicantController::class, 'updateAddress']);
        Route::put('/employment', [ApplicantController::class, 'updateEmployment']);
        Route::put('/bank-account', [ApplicantController::class, 'updateBankAccount']);
        Route::post('/signature', [ApplicantController::class, 'saveSignature']);

        // Addresses management
        Route::get('/addresses', [ApplicantController::class, 'listAddresses']);
        Route::post('/addresses', [ApplicantController::class, 'storeAddress']);
        Route::put('/addresses/{address}', [ApplicantController::class, 'updateAddressById']);
        Route::delete('/addresses/{address}', [ApplicantController::class, 'destroyAddress']);

        // Employment records management
        Route::get('/employment-records', [ApplicantController::class, 'listEmploymentRecords']);
        Route::post('/employment-records', [ApplicantController::class, 'storeEmploymentRecord']);

        // Bank accounts management
        Route::get('/bank-accounts', [ApplicantController::class, 'listBankAccounts']);
        Route::post('/bank-accounts', [ApplicantController::class, 'storeBankAccount']);
        Route::patch('/bank-accounts/{bankAccount}/primary', [ApplicantController::class, 'setPrimaryBankAccount']);
        Route::delete('/bank-accounts/{bankAccount}', [ApplicantController::class, 'deleteBankAccount']);
    });

    // Data Corrections (for rejected fields)
    Route::prefix('corrections')->group(function () {
        Route::get('/', [CorrectionController::class, 'index']);
        Route::get('/{fieldName}', [CorrectionController::class, 'show']);
        Route::post('/', [CorrectionController::class, 'submitCorrection']);
    });

    // Applications (for applicants)
    Route::prefix('applications')->group(function () {
        Route::get('/', [ApplicationController::class, 'index']);
        Route::post('/', [ApplicationController::class, 'store']);
        Route::get('/{application}', [ApplicationController::class, 'show']);
        Route::put('/{application}', [ApplicationController::class, 'update']);
        Route::post('/{application}/submit', [ApplicationController::class, 'submit']);
        Route::post('/{application}/cancel', [ApplicationController::class, 'cancel']);

        // Documents
        Route::get('/{application}/documents', [DocumentController::class, 'index']);
        Route::post('/{application}/documents', [DocumentController::class, 'store']);
        Route::delete('/{application}/documents/{document}', [DocumentController::class, 'destroy']);

        // References
        Route::get('/{application}/references', [ApplicationController::class, 'references']);
        Route::post('/{application}/references', [ApplicationController::class, 'storeReference']);
    });

    // Document download (for local dev)
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])
        ->name('api.documents.download');

    // =============================================
    // KYC - Identity Validation Services
    // Rate limited to prevent abuse of external API calls
    // =============================================
    Route::prefix('kyc')->group(function () {
        // Get available services (no rate limit - read only)
        Route::get('/services', [KycController::class, 'services']);

        // Connection test and token management (standard KYC rate limit)
        Route::middleware('throttle:kyc')->group(function () {
            Route::post('/test-connection', [KycController::class, 'testConnection']);
            Route::post('/refresh-token', [KycController::class, 'refreshToken']);
        });

        // Document validation endpoints (standard KYC rate limit: 10/min)
        Route::middleware('throttle:kyc')->group(function () {
            // CURP validation and lookup
            Route::post('/curp/validate', [KycController::class, 'validateCurp']);
            Route::post('/curp/get', [KycController::class, 'getCurp']);

            // RFC validation
            Route::post('/rfc/validate', [KycController::class, 'validateRfc']);

            // INE/IFE validation with OCR
            Route::post('/ine/validate', [KycController::class, 'validateIne']);

            // SPEI CEP validation
            Route::post('/cep/validate', [KycController::class, 'validateCep']);

            // OFAC & UN sanctions block lists
            Route::post('/ofac/check', [KycController::class, 'checkOfac']);

            // PLD Mexican blacklists (PGR, PGJ, PEPs, SAT 69/69B, etc.)
            Route::post('/pld/check', [KycController::class, 'checkPldBlacklists']);

            // IMSS history
            Route::post('/imss/history', [KycController::class, 'getImssHistory']);

            // Cédula Profesional
            Route::post('/cedula/validate', [KycController::class, 'validateCedula']);
        });

        // Biometric endpoints (stricter rate limit: 5/min - more expensive, sensitive)
        Route::middleware('throttle:kyc-biometric')->group(function () {
            // Biometric SDK token
            Route::post('/biometric/token', [KycController::class, 'getBiometricToken']);

            // Face Match - compare selfie with INE photo
            Route::post('/biometric/face-match', [KycController::class, 'validateFaceMatch']);

            // Liveness detection - verify real person (anti-spoofing)
            Route::post('/biometric/liveness', [KycController::class, 'validateLiveness']);
        });

        // Data Verifications - record and retrieve verified fields (no external API)
        Route::post('/verifications', [KycController::class, 'recordVerifications']);
        Route::get('/verifications/{applicantId}', [KycController::class, 'getVerifications']);
        Route::post('/verifications/check', [KycController::class, 'checkFieldsVerified']);
    });
});

// =============================================
// ADMIN AUTH - Staff login (no prior authentication required)
// =============================================
Route::middleware(['tenant', 'metadata'])->prefix('admin/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'adminLogin']);
});

// Admin routes (authenticated staff within tenant)
// Base middleware: staff = agent, analyst, admin, super_admin
Route::middleware(['tenant', 'auth:sanctum', 'tenant.user', 'staff', 'metadata'])->prefix('admin')->group(function () {

    // =============================================
    // DASHBOARD - All staff can view
    // =============================================
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // =============================================
    // APPLICATIONS - Varies by role
    // =============================================
    Route::prefix('applications')->group(function () {
        // List and view - all staff (agents see only assigned)
        Route::get('/', [AdminApplicationController::class, 'index']);
        Route::get('/{application}', [AdminApplicationController::class, 'show']);

        // Add notes - all staff can add notes
        Route::post('/{application}/notes', [AdminApplicationController::class, 'addNote']);

        // Status changes - requires canChangeApplicationStatus (analyst+)
        Route::put('/{application}/status', [AdminApplicationController::class, 'updateStatus'])
            ->middleware('permission:canChangeApplicationStatus');

        // Counter-offer - requires canApproveRejectApplications (admin+)
        Route::post('/{application}/counter-offer', [AdminApplicationController::class, 'counterOffer'])
            ->middleware('permission:canApproveRejectApplications');

        // Assign to agent - requires canAssignApplications (admin+)
        Route::put('/{application}/assign', [AdminApplicationController::class, 'assign'])
            ->middleware('permission:canAssignApplications');

        // Document review - requires canReviewDocuments (analyst+)
        Route::put('/{application}/documents/{document}/approve', [ApplicationDocumentController::class, 'approve'])
            ->middleware('permission:canReviewDocuments');
        Route::put('/{application}/documents/{document}/reject', [ApplicationDocumentController::class, 'reject'])
            ->middleware('permission:canReviewDocuments');
        Route::put('/{application}/documents/{document}/unapprove', [ApplicationDocumentController::class, 'unapprove'])
            ->middleware('permission:canReviewDocuments');

        // Document view - all staff can view
        Route::get('/{application}/documents/{document}/url', [ApplicationDocumentController::class, 'getUrl']);
        Route::get('/{application}/documents/{document}/download', [ApplicationDocumentController::class, 'download'])
            ->name('api.admin.documents.download');
        Route::get('/{application}/documents/{document}/history', [ApplicationDocumentController::class, 'history']);

        // API Logs for applicant - all staff can view
        Route::get('/{application}/api-logs', [AdminApplicationController::class, 'getApiLogs']);

        // Reference verification - requires canVerifyReferences (analyst+)
        Route::put('/{application}/references/{reference}/verify', [ApplicationVerificationController::class, 'verifyReference'])
            ->middleware('permission:canVerifyReferences');

        // Data verification - requires canVerifyReferences (analyst+)
        Route::put('/{application}/verify-data', [ApplicationVerificationController::class, 'verifyData'])
            ->middleware('permission:canVerifyReferences');

        // Bank account verification - requires canVerifyReferences (analyst+)
        Route::put('/{application}/bank-accounts/{bankAccount}/verify', [ApplicationVerificationController::class, 'verifyBankAccount'])
            ->middleware('permission:canVerifyReferences');
        Route::put('/{application}/bank-accounts/{bankAccount}/unverify', [ApplicationVerificationController::class, 'unverifyBankAccount'])
            ->middleware('permission:canVerifyReferences');
    });

    // =============================================
    // PRODUCTS MANAGEMENT - Admin only
    // =============================================
    Route::middleware('permission:canManageProducts')->group(function () {
        Route::apiResource('products', ProductController::class);
    });

    // =============================================
    // USERS MANAGEMENT - Admin only
    // =============================================
    Route::middleware('permission:canManageUsers')->group(function () {
        Route::apiResource('users', UserController::class);
    });

    // =============================================
    // INTEGRATIONS - Super Admin only
    // =============================================
    Route::middleware('permission:canConfigureTenant')->prefix('integrations')->group(function () {
        Route::get('/', [TenantIntegrationController::class, 'index']);
        Route::get('/options', [TenantIntegrationController::class, 'options']);
        Route::post('/', [TenantIntegrationController::class, 'store']);
        Route::post('/{id}/test', [TenantIntegrationController::class, 'test']);
        Route::patch('/{id}/toggle', [TenantIntegrationController::class, 'toggle']);
        Route::delete('/{id}', [TenantIntegrationController::class, 'destroy']);
    });

    // =============================================
    // REPORTS - Analyst+ can view
    // =============================================
    Route::middleware('permission:canViewReports')->prefix('reports')->group(function () {
        Route::get('/applications', [DashboardController::class, 'applicationsReport']);
        Route::get('/disbursements', [DashboardController::class, 'disbursementsReport']);
        Route::get('/portfolio', [DashboardController::class, 'portfolioReport']);

        // Export endpoints (CSV download)
        Route::get('/applications/export', [DashboardController::class, 'exportApplications']);
        Route::get('/disbursements/export', [DashboardController::class, 'exportDisbursements']);
        Route::get('/portfolio/export', [DashboardController::class, 'exportPortfolio']);
    });

    // =============================================
    // TENANT CONFIGURATION - Admin can configure their own tenant
    // =============================================
    Route::middleware('permission:canManageProducts')->prefix('config')->group(function () {
        Route::get('/', [TenantConfigController::class, 'show']);
        Route::put('/tenant', [TenantConfigController::class, 'updateTenant']);
        Route::put('/branding', [TenantConfigController::class, 'updateBranding']);
        Route::get('/api-configs', [TenantConfigController::class, 'listApiConfigs']);
        Route::post('/api-configs', [TenantConfigController::class, 'saveApiConfig']);
        Route::delete('/api-configs/{config}', [TenantConfigController::class, 'deleteApiConfig']);
        Route::post('/api-configs/{config}/test', [TenantConfigController::class, 'testApiConfig']);
    });

    // =============================================
    // TENANTS MANAGEMENT - Super Admin only
    // =============================================
    Route::middleware('permission:canConfigureTenant')->prefix('tenants')->group(function () {
        Route::get('/', [TenantController::class, 'index']);
        Route::post('/', [TenantController::class, 'store']);
        Route::get('/{tenant}', [TenantController::class, 'show']);
        Route::put('/{tenant}', [TenantController::class, 'update']);
        Route::delete('/{tenant}', [TenantController::class, 'destroy']);
        Route::get('/{tenant}/stats', [TenantController::class, 'stats']);

        // Tenant-specific configuration (including API configs)
        Route::get('/{tenant}/config', [TenantController::class, 'getConfig']);
        Route::put('/{tenant}/branding', [TenantController::class, 'updateBranding']);
        Route::post('/{tenant}/upload-logo', [TenantController::class, 'uploadLogo']);
        Route::get('/{tenant}/api-configs', [TenantController::class, 'listApiConfigs']);
        Route::post('/{tenant}/api-configs', [TenantController::class, 'saveApiConfig']);
        Route::delete('/{tenant}/api-configs/{config}', [TenantController::class, 'deleteApiConfig']);
        Route::post('/{tenant}/api-configs/{config}/test', [TenantController::class, 'testApiConfig']);
    });

    // =============================================
    // API LOGS - Admin+ can view API call history
    // =============================================
    Route::middleware('permission:canManageProducts')->prefix('api-logs')->group(function () {
        Route::get('/', [ApiLogController::class, 'index']);
        Route::get('/stats', [ApiLogController::class, 'stats']);
        Route::get('/providers', [ApiLogController::class, 'providers']);
        Route::get('/services', [ApiLogController::class, 'services']);
        Route::get('/{apiLog}', [ApiLogController::class, 'show']);
    });
});

/*
|--------------------------------------------------------------------------
| API V2 Routes - New Architecture
|--------------------------------------------------------------------------
|
| V2 routes use the new normalized architecture with separate tables
| for staff and applicants. These routes run parallel to V1 for
| backwards compatibility during migration.
|
*/

use App\Http\Controllers\Api\V2\Staff\AuthController as StaffAuthController;
use App\Http\Controllers\Api\V2\Applicant\AuthController as ApplicantAuthController;

// =============================================
// V2: STAFF AUTHENTICATION (uses StaffAccount model)
// =============================================
Route::middleware(['tenant', 'metadata'])->prefix('v2/staff/auth')->group(function () {
    // Public staff login
    Route::post('/login', [StaffAuthController::class, 'login']);

    // Protected staff endpoints
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/me', [StaffAuthController::class, 'me']);
        Route::post('/logout', [StaffAuthController::class, 'logout']);
        Route::post('/refresh', [StaffAuthController::class, 'refresh']);
    });
});

// =============================================
// V2: APPLICANT AUTHENTICATION (uses ApplicantAccount model)
// =============================================
Route::middleware(['tenant', 'metadata'])->prefix('v2/applicant/auth')->group(function () {
    // Public applicant endpoints (with rate limiting)
    Route::middleware('throttle:otp')->post('/otp/request', [ApplicantAuthController::class, 'requestOtp']);
    Route::middleware('throttle:otp-verify')->post('/otp/verify', [ApplicantAuthController::class, 'verifyOtp']);
    Route::post('/check-user', [ApplicantAuthController::class, 'checkUser']);
    Route::middleware('throttle:pin-login')->post('/pin/login', [ApplicantAuthController::class, 'loginWithPin']);

    // Protected applicant endpoints
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/me', [ApplicantAuthController::class, 'me']);
        Route::post('/logout', [ApplicantAuthController::class, 'logout']);
        Route::post('/refresh', [ApplicantAuthController::class, 'refresh']);
        Route::post('/pin/setup', [ApplicantAuthController::class, 'setupPin']);
        Route::post('/pin/change', [ApplicantAuthController::class, 'changePin']);
    });
});

// =============================================
// V2: PERSON MANAGEMENT ROUTES
// =============================================
// Routes for managing persons and their related entities
// Requires authentication and tenant context
require __DIR__ . '/api/person.php';

// =============================================
// V2: COMPANY MANAGEMENT ROUTES
// =============================================
// Routes for managing companies (personas morales) and their related entities
// Requires authentication and tenant context
require __DIR__ . '/api/company.php';

// =============================================
// V2: APPLICANT APPLICATIONS & DOCUMENTS
// =============================================
use App\Http\Controllers\Api\V2\Applicant\ApplicationController as ApplicantAppController;
use App\Http\Controllers\Api\V2\Applicant\DocumentController as ApplicantDocController;

Route::middleware(['tenant', 'metadata', 'auth:sanctum'])
    ->prefix('v2/applicant')
    ->group(function () {
        // Applications
        Route::get('/applications', [ApplicantAppController::class, 'index']);
        Route::post('/applications', [ApplicantAppController::class, 'store']);
        Route::get('/applications/{id}', [ApplicantAppController::class, 'show']);
        Route::patch('/applications/{id}', [ApplicantAppController::class, 'update']);
        Route::post('/applications/{id}/submit', [ApplicantAppController::class, 'submit']);
        Route::post('/applications/{id}/cancel', [ApplicantAppController::class, 'cancel']);
        Route::post('/applications/{id}/counter-offer/respond', [ApplicantAppController::class, 'respondToCounterOffer']);
        Route::get('/applications/{id}/history', [ApplicantAppController::class, 'history']);

        // Documents
        Route::get('/documents', [ApplicantDocController::class, 'index']);
        Route::post('/documents', [ApplicantDocController::class, 'store']);
        Route::get('/documents/types', [ApplicantDocController::class, 'types']);
        Route::get('/documents/rejected', [ApplicantDocController::class, 'rejected']);
        Route::get('/documents/missing', [ApplicantDocController::class, 'missing']);
        Route::get('/documents/{id}', [ApplicantDocController::class, 'show']);
        Route::get('/documents/{id}/download', [ApplicantDocController::class, 'download']);
        Route::delete('/documents/{id}', [ApplicantDocController::class, 'destroy']);
    });

// =============================================
// V2: STAFF APPLICATIONS & DOCUMENTS
// =============================================
use App\Http\Controllers\Api\V2\Staff\ApplicationController as StaffAppController;
use App\Http\Controllers\Api\V2\Staff\DocumentController as StaffDocController;
use App\Http\Controllers\Api\V2\Staff\UserController as StaffUserController;
use App\Http\Controllers\Api\V2\Staff\ProductController as StaffProductController;
use App\Http\Controllers\Api\V2\Staff\ConfigController as StaffConfigController;
use App\Http\Controllers\Api\V2\Staff\ApiLogController as StaffApiLogController;
use App\Http\Controllers\Api\V2\Staff\TenantController as StaffTenantController;
use App\Http\Controllers\Api\V2\Staff\IntegrationController as StaffIntegrationController;

Route::middleware(['tenant', 'metadata', 'auth:sanctum', 'staff'])
    ->prefix('v2/staff')
    ->group(function () {
        // =============================================
        // Users Management - Admin only
        // =============================================
        Route::middleware('permission:canManageUsers')->group(function () {
            Route::get('/users', [StaffUserController::class, 'index']);
            Route::post('/users', [StaffUserController::class, 'store']);
            Route::get('/users/{id}', [StaffUserController::class, 'show']);
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
            Route::get('/products/{id}', [StaffProductController::class, 'show']);
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
            Route::get('/api-configs', [StaffConfigController::class, 'listApiConfigs']);
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
            Route::get('/{id}', [StaffTenantController::class, 'show']);
            Route::put('/{id}', [StaffTenantController::class, 'update']);
            Route::delete('/{id}', [StaffTenantController::class, 'destroy']);
            Route::get('/{id}/stats', [StaffTenantController::class, 'stats']);
            Route::get('/{id}/config', [StaffTenantController::class, 'getConfig']);
            Route::put('/{id}/branding', [StaffTenantController::class, 'updateBranding']);
            Route::post('/{id}/upload-logo', [StaffTenantController::class, 'uploadLogo']);
            Route::get('/{id}/api-configs', [StaffTenantController::class, 'listApiConfigs']);
            Route::post('/{id}/api-configs', [StaffTenantController::class, 'saveApiConfig']);
            Route::delete('/{id}/api-configs/{configId}', [StaffTenantController::class, 'deleteApiConfig']);
            Route::post('/{id}/api-configs/{configId}/test', [StaffTenantController::class, 'testApiConfig']);
        });

        // Applications - Read (any staff)
        Route::get('/applications', [StaffAppController::class, 'index']);
        Route::get('/applications/board', [StaffAppController::class, 'board']);
        Route::get('/applications/statistics', [StaffAppController::class, 'statistics']);
        Route::get('/applications/unassigned', [StaffAppController::class, 'unassigned']);
        Route::get('/applications/my-queue', [StaffAppController::class, 'myQueue']);
        Route::get('/applications/{id}', [StaffAppController::class, 'show']);
        Route::get('/applications/{id}/history', [StaffAppController::class, 'history']);

        // Applications - Actions requiring permissions
        Route::post('/applications/{id}/assign', [StaffAppController::class, 'assign'])
            ->middleware('permission:canAssignApplications');
        Route::post('/applications/{id}/status', [StaffAppController::class, 'changeStatus'])
            ->middleware('permission:canChangeApplicationStatus');
        Route::post('/applications/{id}/approve', [StaffAppController::class, 'approve'])
            ->middleware(['permission:canApproveRejectApplications', 'throttle:30,1']); // 30 per minute
        Route::post('/applications/{id}/reject', [StaffAppController::class, 'reject'])
            ->middleware(['permission:canApproveRejectApplications', 'throttle:30,1']); // 30 per minute
        Route::post('/applications/{id}/counter-offer', [StaffAppController::class, 'sendCounterOffer'])
            ->middleware(['permission:canApproveRejectApplications', 'throttle:30,1']); // 30 per minute
        Route::patch('/applications/{id}/verification', [StaffAppController::class, 'updateVerification'])
            ->middleware('permission:canVerifyReferences');
        Route::post('/applications/{id}/risk-assessment', [StaffAppController::class, 'setRiskAssessment'])
            ->middleware('permission:canChangeApplicationStatus');

        // Documents - Read (any staff)
        Route::get('/documents/types', [StaffDocController::class, 'types']);
        Route::get('/documents/pending', [StaffDocController::class, 'pending']);
        Route::get('/documents/expiring', [StaffDocController::class, 'expiring']);
        Route::get('/documents/{id}', [StaffDocController::class, 'show']);
        Route::get('/documents/{id}/download', [StaffDocController::class, 'download']);

        // Documents - Actions requiring permissions
        Route::post('/documents/{id}/approve', [StaffDocController::class, 'approve'])
            ->middleware(['permission:canReviewDocuments', 'throttle:60,1']); // 60 per minute
        Route::post('/documents/{id}/reject', [StaffDocController::class, 'reject'])
            ->middleware(['permission:canReviewDocuments', 'throttle:60,1']); // 60 per minute
        Route::post('/documents/{id}/ocr', [StaffDocController::class, 'setOcrData'])
            ->middleware('permission:canReviewDocuments');

        // Person documents
        Route::get('/persons/{personId}/documents', [StaffDocController::class, 'forPerson']);
        Route::get('/persons/{personId}/documents/check', [StaffDocController::class, 'checkRequired']);
    });
