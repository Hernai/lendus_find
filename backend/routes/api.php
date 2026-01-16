<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\ApplicantController;
use App\Http\Controllers\Api\CorrectionController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\SimulatorController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\ApplicationController as AdminApplicationController;
use App\Http\Controllers\Api\Admin\TenantIntegrationController;
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

    // Authentication (with metadata capture for audit logging)
    Route::middleware(['metadata'])->prefix('auth')->group(function () {
        Route::post('/otp/request', [AuthController::class, 'requestOtp']);
        Route::post('/otp/verify', [AuthController::class, 'verifyOtp']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

        // PIN Authentication (no SMS cost)
        Route::post('/check-user', [AuthController::class, 'checkUser']);
        Route::post('/pin/login', [AuthController::class, 'loginWithPin']);
        Route::post('/pin/reset', [AuthController::class, 'resetPinWithOtp']);

        // Email/Password Authentication (for admin/staff users)
        Route::post('/password/login', [AuthController::class, 'loginWithPassword']);
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
    // =============================================
    Route::prefix('kyc')->group(function () {
        // Get available services
        Route::get('/services', [KycController::class, 'services']);

        // Connection test and token management
        Route::post('/test-connection', [KycController::class, 'testConnection']);
        Route::post('/refresh-token', [KycController::class, 'refreshToken']);

        // CURP validation and lookup
        Route::post('/curp/validate', [KycController::class, 'validateCurp']);
        Route::post('/curp/get', [KycController::class, 'getCurp']);

        // RFC validation
        Route::post('/rfc/validate', [KycController::class, 'validateRfc']);

        // INE/IFE validation with OCR
        Route::post('/ine/validate', [KycController::class, 'validateIne']);

        // Biometric SDK token
        Route::post('/biometric/token', [KycController::class, 'getBiometricToken']);

        // Face Match - compare selfie with INE photo
        Route::post('/biometric/face-match', [KycController::class, 'validateFaceMatch']);

        // Liveness detection - verify real person (anti-spoofing)
        Route::post('/biometric/liveness', [KycController::class, 'validateLiveness']);

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

        // Data Verifications - record and retrieve verified fields
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
        Route::put('/{application}/documents/{document}/approve', [AdminApplicationController::class, 'approveDocument'])
            ->middleware('permission:canReviewDocuments');
        Route::put('/{application}/documents/{document}/reject', [AdminApplicationController::class, 'rejectDocument'])
            ->middleware('permission:canReviewDocuments');
        Route::put('/{application}/documents/{document}/unapprove', [AdminApplicationController::class, 'unapproveDocument'])
            ->middleware('permission:canReviewDocuments');

        // Document view - all staff can view
        Route::get('/{application}/documents/{document}/url', [AdminApplicationController::class, 'getDocumentUrl']);
        Route::get('/{application}/documents/{document}/download', [AdminApplicationController::class, 'downloadDocument'])
            ->name('api.admin.documents.download');

        // Reference verification - requires canVerifyReferences (analyst+)
        Route::put('/{application}/references/{reference}/verify', [AdminApplicationController::class, 'verifyReference'])
            ->middleware('permission:canVerifyReferences');

        // Data verification - requires canVerifyReferences (analyst+)
        Route::put('/{application}/verify-data', [AdminApplicationController::class, 'verifyData'])
            ->middleware('permission:canVerifyReferences');

        // Bank account verification - requires canVerifyReferences (analyst+)
        Route::put('/{application}/bank-accounts/{bankAccount}/verify', [AdminApplicationController::class, 'verifyBankAccount'])
            ->middleware('permission:canVerifyReferences');
        Route::put('/{application}/bank-accounts/{bankAccount}/unverify', [AdminApplicationController::class, 'unverifyBankAccount'])
            ->middleware('permission:canVerifyReferences');
    });

    // =============================================
    // PRODUCTS MANAGEMENT - Admin only
    // =============================================
    Route::middleware('permission:canManageProducts')->group(function () {
        Route::apiResource('products', \App\Http\Controllers\Api\Admin\ProductController::class);
    });

    // =============================================
    // USERS MANAGEMENT - Admin only
    // =============================================
    Route::middleware('permission:canManageUsers')->group(function () {
        Route::apiResource('users', \App\Http\Controllers\Api\Admin\UserController::class);
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
        Route::get('/', [\App\Http\Controllers\Api\Admin\TenantConfigController::class, 'show']);
        Route::put('/tenant', [\App\Http\Controllers\Api\Admin\TenantConfigController::class, 'updateTenant']);
        Route::put('/branding', [\App\Http\Controllers\Api\Admin\TenantConfigController::class, 'updateBranding']);
        Route::get('/api-configs', [\App\Http\Controllers\Api\Admin\TenantConfigController::class, 'listApiConfigs']);
        Route::post('/api-configs', [\App\Http\Controllers\Api\Admin\TenantConfigController::class, 'saveApiConfig']);
        Route::delete('/api-configs/{config}', [\App\Http\Controllers\Api\Admin\TenantConfigController::class, 'deleteApiConfig']);
        Route::post('/api-configs/{config}/test', [\App\Http\Controllers\Api\Admin\TenantConfigController::class, 'testApiConfig']);
    });

    // =============================================
    // TENANTS MANAGEMENT - Super Admin only
    // =============================================
    Route::middleware('permission:canConfigureTenant')->prefix('tenants')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\Admin\TenantController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\Admin\TenantController::class, 'store']);
        Route::get('/{tenant}', [\App\Http\Controllers\Api\Admin\TenantController::class, 'show']);
        Route::put('/{tenant}', [\App\Http\Controllers\Api\Admin\TenantController::class, 'update']);
        Route::delete('/{tenant}', [\App\Http\Controllers\Api\Admin\TenantController::class, 'destroy']);
        Route::get('/{tenant}/stats', [\App\Http\Controllers\Api\Admin\TenantController::class, 'stats']);

        // Tenant-specific configuration (including API configs)
        Route::get('/{tenant}/config', [\App\Http\Controllers\Api\Admin\TenantController::class, 'getConfig']);
        Route::put('/{tenant}/branding', [\App\Http\Controllers\Api\Admin\TenantController::class, 'updateBranding']);
        Route::post('/{tenant}/upload-logo', [\App\Http\Controllers\Api\Admin\TenantController::class, 'uploadLogo']);
        Route::get('/{tenant}/api-configs', [\App\Http\Controllers\Api\Admin\TenantController::class, 'listApiConfigs']);
        Route::post('/{tenant}/api-configs', [\App\Http\Controllers\Api\Admin\TenantController::class, 'saveApiConfig']);
        Route::delete('/{tenant}/api-configs/{config}', [\App\Http\Controllers\Api\Admin\TenantController::class, 'deleteApiConfig']);
        Route::post('/{tenant}/api-configs/{config}/test', [\App\Http\Controllers\Api\Admin\TenantController::class, 'testApiConfig']);
    });
});
