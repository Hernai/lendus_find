<?php

use App\Http\Controllers\Api\Company\CompanyAddressController;
use App\Http\Controllers\Api\Company\CompanyController;
use App\Http\Controllers\Api\Company\CompanyMemberController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Company API Routes
|--------------------------------------------------------------------------
|
| Routes for managing companies (personas morales) and their related
| entities (members, addresses).
|
*/

// =============================================
// APPLICANT COMPANY ROUTES
// =============================================
// Routes for applicants to manage their companies
Route::middleware(['auth:sanctum'])->prefix('companies')->group(function () {
    // List companies for current user
    Route::get('/', [CompanyController::class, 'index']);

    // Create a new company
    Route::post('/', [CompanyController::class, 'store']);

    // Find company by RFC
    Route::post('/find-by-rfc', [CompanyController::class, 'findByRfc']);

    // Company-specific routes
    Route::prefix('{company}')->group(function () {
        Route::get('/', [CompanyController::class, 'show']);
        Route::put('/', [CompanyController::class, 'update']);

        // Addresses
        Route::prefix('addresses')->group(function () {
            Route::get('/', [CompanyAddressController::class, 'index']);
            Route::post('/', [CompanyAddressController::class, 'store']);

            Route::prefix('{address}')->group(function () {
                Route::get('/', [CompanyAddressController::class, 'show']);
                Route::put('/', [CompanyAddressController::class, 'update']);
                Route::delete('/', [CompanyAddressController::class, 'destroy']);
            });
        });

        // Members
        Route::prefix('members')->group(function () {
            Route::get('/', [CompanyMemberController::class, 'index']);
            Route::post('/', [CompanyMemberController::class, 'store']);

            Route::prefix('{member}')->group(function () {
                Route::get('/', [CompanyMemberController::class, 'show']);
                Route::put('/', [CompanyMemberController::class, 'update']);
                Route::delete('/', [CompanyMemberController::class, 'destroy']);
                Route::post('/accept', [CompanyMemberController::class, 'acceptInvitation']);
                Route::post('/suspend', [CompanyMemberController::class, 'suspend']);
            });

            // Transfer ownership
            Route::post('/{member}/transfer-ownership', [CompanyMemberController::class, 'transferOwnership']);
        });
    });
});

// =============================================
// ADMIN COMPANY ROUTES
// =============================================
// Routes for staff to manage companies
Route::middleware(['auth:sanctum', 'staff'])->prefix('admin/companies')->group(function () {
    // List all companies with filters
    Route::get('/', [CompanyController::class, 'adminIndex']);

    // Find company by RFC
    Route::post('/find-by-rfc', [CompanyController::class, 'findByRfc']);

    // Company-specific admin routes
    Route::prefix('{company}')->group(function () {
        Route::get('/', [CompanyController::class, 'show']);
        Route::put('/', [CompanyController::class, 'update']);

        // Verification (KYB)
        Route::post('/verify', [CompanyController::class, 'verify']);
        Route::post('/reject-kyb', [CompanyController::class, 'rejectKyb']);

        // Status management
        Route::post('/suspend', [CompanyController::class, 'suspend']);
        Route::post('/reactivate', [CompanyController::class, 'reactivate']);
        Route::post('/close', [CompanyController::class, 'close']);

        // Addresses
        Route::prefix('addresses')->group(function () {
            Route::get('/', [CompanyAddressController::class, 'index']);

            Route::prefix('{address}')->group(function () {
                Route::get('/', [CompanyAddressController::class, 'show']);
                Route::post('/verify', [CompanyAddressController::class, 'verify']);
                Route::post('/reject', [CompanyAddressController::class, 'reject']);
            });
        });

        // Members
        Route::prefix('members')->group(function () {
            Route::get('/', [CompanyMemberController::class, 'index']);

            Route::prefix('{member}')->group(function () {
                Route::get('/', [CompanyMemberController::class, 'show']);
                Route::post('/verify', [CompanyMemberController::class, 'verify']);
                Route::post('/suspend', [CompanyMemberController::class, 'suspend']);
            });
        });
    });
});
