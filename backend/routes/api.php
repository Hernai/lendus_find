<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\ApplicantController;
use App\Http\Controllers\Api\CorrectionController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\SimulatorController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\ApplicationController as AdminApplicationController;
use Illuminate\Support\Facades\Route;

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

        // Utilities
        Route::post('/validate-clabe', [ApplicantController::class, 'validateClabe']);
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
});
