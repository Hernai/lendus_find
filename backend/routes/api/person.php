<?php

use App\Http\Controllers\Api\Person\PersonAddressController;
use App\Http\Controllers\Api\Person\PersonBankAccountController;
use App\Http\Controllers\Api\Person\PersonController;
use App\Http\Controllers\Api\Person\PersonEmploymentController;
use App\Http\Controllers\Api\Person\PersonIdentificationController;
use App\Http\Controllers\Api\Person\PersonReferenceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Person API Routes
|--------------------------------------------------------------------------
|
| Routes for managing persons and their related entities (identifications,
| addresses, employments, references, bank accounts).
|
*/

Route::middleware(['auth:sanctum'])->prefix('persons')->group(function () {
    // Person CRUD
    Route::get('/', [PersonController::class, 'index']);
    Route::post('/', [PersonController::class, 'store']);
    Route::get('/statistics', [PersonController::class, 'statistics']);
    Route::post('/find-by-curp', [PersonController::class, 'findByCurp']);
    Route::post('/find-by-rfc', [PersonController::class, 'findByRfc']);

    Route::prefix('{person}')->group(function () {
        Route::get('/', [PersonController::class, 'show']);
        Route::put('/', [PersonController::class, 'update']);
        Route::delete('/', [PersonController::class, 'destroy']);
        Route::get('/summary', [PersonController::class, 'summary']);
        Route::post('/kyc-status', [PersonController::class, 'updateKycStatus']);
        Route::post('/recalculate-completeness', [PersonController::class, 'recalculateCompleteness']);

        // Identifications
        Route::prefix('identifications')->group(function () {
            Route::get('/', [PersonIdentificationController::class, 'index']);
            Route::post('/', [PersonIdentificationController::class, 'store']);
            Route::get('/current', [PersonIdentificationController::class, 'current']);
            Route::get('/current/{type}', [PersonIdentificationController::class, 'currentByType']);
            Route::get('/pending', [PersonIdentificationController::class, 'pending']);
            Route::get('/has-verified/{type}', [PersonIdentificationController::class, 'hasVerified']);
            Route::post('/curp', [PersonIdentificationController::class, 'setCurp']);
            Route::post('/rfc', [PersonIdentificationController::class, 'setRfc']);
            Route::post('/ine', [PersonIdentificationController::class, 'setIne']);

            Route::prefix('{identification}')->group(function () {
                Route::get('/', [PersonIdentificationController::class, 'show']);
                Route::put('/', [PersonIdentificationController::class, 'update']);
                Route::delete('/', [PersonIdentificationController::class, 'destroy']);
                Route::post('/verify', [PersonIdentificationController::class, 'verify']);
                Route::post('/reject', [PersonIdentificationController::class, 'reject']);
            });
        });

        // Addresses
        Route::prefix('addresses')->group(function () {
            Route::get('/', [PersonAddressController::class, 'index']);
            Route::post('/', [PersonAddressController::class, 'store']);
            Route::get('/current', [PersonAddressController::class, 'current']);
            Route::get('/current-home', [PersonAddressController::class, 'currentHome']);
            Route::post('/home', [PersonAddressController::class, 'setHomeAddress']);
            Route::get('/history/{type}', [PersonAddressController::class, 'history']);
            Route::get('/has-verified/{type}', [PersonAddressController::class, 'hasVerified']);

            Route::prefix('{address}')->group(function () {
                Route::get('/', [PersonAddressController::class, 'show']);
                Route::put('/', [PersonAddressController::class, 'update']);
                Route::delete('/', [PersonAddressController::class, 'destroy']);
                Route::post('/verify', [PersonAddressController::class, 'verify']);
                Route::post('/reject', [PersonAddressController::class, 'reject']);
                Route::post('/geolocation', [PersonAddressController::class, 'setGeolocation']);
            });
        });

        // Employments
        Route::prefix('employments')->group(function () {
            Route::get('/', [PersonEmploymentController::class, 'index']);
            Route::post('/', [PersonEmploymentController::class, 'store']);
            Route::get('/current', [PersonEmploymentController::class, 'current']);
            Route::post('/current', [PersonEmploymentController::class, 'setCurrentEmployment']);
            Route::get('/income-summary', [PersonEmploymentController::class, 'incomeSummary']);
            Route::post('/calculate-dti', [PersonEmploymentController::class, 'calculateDti']);
            Route::get('/has-verified-current', [PersonEmploymentController::class, 'hasVerifiedCurrent']);
            Route::get('/has-verified-income', [PersonEmploymentController::class, 'hasVerifiedIncome']);

            Route::prefix('{employment}')->group(function () {
                Route::get('/', [PersonEmploymentController::class, 'show']);
                Route::put('/', [PersonEmploymentController::class, 'update']);
                Route::delete('/', [PersonEmploymentController::class, 'destroy']);
                Route::post('/verify', [PersonEmploymentController::class, 'verify']);
                Route::post('/verify-income', [PersonEmploymentController::class, 'verifyIncome']);
                Route::post('/reject', [PersonEmploymentController::class, 'reject']);
                Route::post('/end', [PersonEmploymentController::class, 'endEmployment']);
            });
        });

        // References
        Route::prefix('references')->group(function () {
            Route::get('/', [PersonReferenceController::class, 'index']);
            Route::post('/', [PersonReferenceController::class, 'store']);
            Route::post('/personal', [PersonReferenceController::class, 'addPersonal']);
            Route::post('/work', [PersonReferenceController::class, 'addWork']);
            Route::get('/type/{type}', [PersonReferenceController::class, 'byType']);
            Route::get('/verified', [PersonReferenceController::class, 'verified']);
            Route::get('/pending', [PersonReferenceController::class, 'pending']);
            Route::get('/summary', [PersonReferenceController::class, 'summary']);
            Route::post('/phone-exists', [PersonReferenceController::class, 'phoneExists']);
            Route::post('/has-required', [PersonReferenceController::class, 'hasRequired']);
            Route::post('/bulk-verify', [PersonReferenceController::class, 'bulkVerify']);

            Route::prefix('{reference}')->group(function () {
                Route::get('/', [PersonReferenceController::class, 'show']);
                Route::put('/', [PersonReferenceController::class, 'update']);
                Route::delete('/', [PersonReferenceController::class, 'destroy']);
                Route::post('/verify', [PersonReferenceController::class, 'verify']);
                Route::post('/reject', [PersonReferenceController::class, 'reject']);
                Route::post('/contact-attempt', [PersonReferenceController::class, 'logContactAttempt']);
            });
        });

        // Bank Accounts
        Route::prefix('bank-accounts')->group(function () {
            Route::get('/', [PersonBankAccountController::class, 'index']);
            Route::post('/', [PersonBankAccountController::class, 'store']);
            Route::get('/primary', [PersonBankAccountController::class, 'primary']);
            Route::post('/primary', [PersonBankAccountController::class, 'setPrimaryAccount']);
            Route::get('/for-disbursement', [PersonBankAccountController::class, 'forDisbursement']);
            Route::get('/can-receive-disbursement', [PersonBankAccountController::class, 'canReceiveDisbursement']);
            Route::get('/summary', [PersonBankAccountController::class, 'summary']);
            Route::get('/has-verified', [PersonBankAccountController::class, 'hasVerified']);

            Route::prefix('{bankAccount}')->group(function () {
                Route::get('/', [PersonBankAccountController::class, 'show']);
                Route::put('/', [PersonBankAccountController::class, 'update']);
                Route::delete('/', [PersonBankAccountController::class, 'destroy']);
                Route::post('/set-primary', [PersonBankAccountController::class, 'setPrimary']);
                Route::post('/verify', [PersonBankAccountController::class, 'verify']);
                Route::post('/unverify', [PersonBankAccountController::class, 'unverify']);
                Route::post('/deactivate', [PersonBankAccountController::class, 'deactivate']);
                Route::post('/reactivate', [PersonBankAccountController::class, 'reactivate']);
                Route::post('/close', [PersonBankAccountController::class, 'close']);
                Route::post('/freeze', [PersonBankAccountController::class, 'freeze']);
            });
        });
    });

    // Utility routes (no person context needed)
    Route::post('/validate-clabe', [PersonBankAccountController::class, 'validateClabe']);
    Route::get('/bank/{code}', [PersonBankAccountController::class, 'getBankName']);
});
