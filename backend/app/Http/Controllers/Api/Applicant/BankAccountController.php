<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\ApplicantHelpers;
use App\Models\BankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Controller for managing applicant bank accounts.
 *
 * Single Responsibility: Handle CRUD operations for bank accounts only.
 */
class BankAccountController extends Controller
{
    use ApplicantHelpers;

    /**
     * List all bank accounts for the applicant.
     */
    public function index(Request $request): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $applicant->bankAccounts
                ->where('is_active', true)
                ->map(fn($b) => $this->formatBankAccount($b))
                ->values()
        ]);
    }

    /**
     * Add a new bank account.
     */
    public function store(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:DISBURSEMENT,PAYMENT,BOTH',
            'is_primary' => 'sometimes|boolean',
            'bank_name' => 'required|string|max:100',
            'bank_code' => 'nullable|string|max:10',
            'clabe' => 'required|string|size:18',
            'account_number' => 'nullable|string|max:20',
            'account_type' => 'sometimes|in:DEBITO,NOMINA,AHORRO,CHEQUES,INVERSION,OTRO',
            'holder_name' => 'required|string|max:200',
            'holder_rfc' => 'nullable|string|max:13',
            'is_own_account' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate CLABE
        if (!BankAccount::validateClabe($request->clabe)) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => ['clabe' => ['CLABE inválida']]
            ], 422);
        }

        // If setting as primary, unset other primaries
        if ($request->input('is_primary', false)) {
            $applicant->bankAccounts()->where('is_primary', true)->update(['is_primary' => false]);
        }

        $bankAccount = BankAccount::create([
            'id' => Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'type' => $request->input('type', 'BOTH'),
            'is_primary' => $request->input('is_primary', false),
            'bank_name' => $request->bank_name,
            'bank_code' => $request->bank_code ?? substr($request->clabe, 0, 3),
            'clabe' => $request->clabe,
            'account_number' => $request->account_number,
            'account_type' => $request->input('account_type', 'DEBITO'),
            'holder_name' => strtoupper($request->holder_name),
            'holder_rfc' => $request->holder_rfc ? strtoupper($request->holder_rfc) : null,
            'is_own_account' => $request->input('is_own_account', true),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Bank account added',
            'data' => $this->formatBankAccount($bankAccount)
        ], 201);
    }

    /**
     * Set a bank account as primary.
     */
    public function setPrimary(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant) {
            return response()->json(['message' => 'No applicant found'], 404);
        }

        if ($bankAccount->applicant_id !== $applicant->id) {
            return response()->json(['message' => 'Bank account not found'], 404);
        }

        // Unset other primaries
        $applicant->bankAccounts()->where('is_primary', true)->update(['is_primary' => false]);

        // Set this one as primary
        $bankAccount->update(['is_primary' => true]);

        return response()->json([
            'message' => 'Bank account set as primary',
            'data' => $this->formatBankAccount($bankAccount->fresh())
        ]);
    }

    /**
     * Delete a bank account (soft delete by marking as inactive).
     */
    public function destroy(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant) {
            return response()->json(['message' => 'No applicant found'], 404);
        }

        if ($bankAccount->applicant_id !== $applicant->id) {
            return response()->json(['message' => 'Bank account not found'], 404);
        }

        // Cannot delete verified bank accounts
        if ($bankAccount->is_verified) {
            return response()->json([
                'message' => 'No se puede eliminar una cuenta bancaria verificada'
            ], 400);
        }

        // Soft delete by marking as inactive
        $bankAccount->update(['is_active' => false]);

        // If this was the primary account, set another one as primary
        if ($bankAccount->is_primary) {
            $newPrimary = $applicant->bankAccounts()
                ->where('is_active', true)
                ->where('id', '!=', $bankAccount->id)
                ->first();

            if ($newPrimary) {
                $newPrimary->update(['is_primary' => true]);
            }
        }

        return response()->json(['message' => 'Bank account deleted']);
    }

    /**
     * Update primary bank info (used in onboarding Step 5).
     */
    public function updatePrimary(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:100',
            'bank_code' => 'nullable|string|max:10',
            'clabe' => 'required|string|size:18',
            'account_number' => 'nullable|string|max:20',
            'account_type' => 'sometimes|in:DEBITO,NOMINA,AHORRO,CHEQUES,INVERSION,OTRO',
            'holder_name' => 'required|string|max:200',
            'holder_rfc' => 'nullable|string|max:13',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate CLABE
        if (!BankAccount::validateClabe($request->clabe)) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => ['clabe' => ['CLABE inválida']]
            ], 422);
        }

        // Deactivate any existing primary bank account
        $applicant->bankAccounts()->where('is_primary', true)->update(['is_primary' => false]);

        // Create new primary bank account
        $bankAccount = BankAccount::create([
            'id' => Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'type' => 'BOTH',
            'is_primary' => true,
            'bank_name' => $request->bank_name,
            'bank_code' => $request->bank_code ?? substr($request->clabe, 0, 3),
            'clabe' => $request->clabe,
            'account_number' => $request->account_number,
            'account_type' => $request->input('account_type', 'DEBITO'),
            'holder_name' => strtoupper($request->holder_name),
            'holder_rfc' => $request->holder_rfc ? strtoupper($request->holder_rfc) : null,
            'is_own_account' => true,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Bank info updated',
            'data' => $this->formatBankAccount($bankAccount)
        ]);
    }

    /**
     * Validate a CLABE number.
     */
    public function validateClabe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'clabe' => 'required|string|size:18',
        ]);

        if ($validator->fails()) {
            return response()->json(['data' => ['valid' => false]]);
        }

        $valid = BankAccount::validateClabe($request->clabe);
        $bankName = null;

        if ($valid) {
            $bankCode = substr($request->clabe, 0, 3);
            $bankName = BankAccount::BANKS[$bankCode] ?? null;
        }

        return response()->json([
            'data' => [
                'valid' => $valid,
                'bank_name' => $bankName,
            ]
        ]);
    }
}
