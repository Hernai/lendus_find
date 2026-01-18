<?php

namespace App\Http\Controllers\Api\V1\Applicant;

use App\Enums\BankAccountType;
use App\Enums\BankAccountUsageType;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApplicantHelpers;
use App\Http\Traits\ValidationHelpers;
use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Controller for managing applicant bank accounts.
 *
 * Single Responsibility: Handle CRUD operations for bank accounts only.
 */
class BankAccountController extends Controller
{
    use ApplicantHelpers;
    use ValidationHelpers;

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
            'data' => BankAccountResource::collection(
                $applicant->bankAccounts->where('is_active', true)->values()
            )
        ]);
    }

    /**
     * Add a new bank account.
     */
    public function store(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        $validation = $this->validateRequest($request->all(), [
            'type' => ['sometimes', Rule::in(BankAccountUsageType::values())],
            'is_primary' => 'sometimes|boolean',
            'bank_name' => 'required|string|max:100',
            'bank_code' => 'nullable|string|max:10',
            'clabe' => ['required', 'string', 'size:18', new \App\Rules\ValidClabe],
            'account_number' => 'nullable|string|max:20',
            'account_type' => ['sometimes', Rule::in(BankAccountType::values())],
            'holder_name' => 'required|string|max:200',
            'holder_rfc' => 'nullable|string|max:13',
            'is_own_account' => 'sometimes|boolean',
        ]);

        if ($this->isValidationError($validation)) {
            return $validation;
        }

        // If setting as primary, unset other primaries
        if ($request->input('is_primary', false)) {
            $applicant->bankAccounts()->where('is_primary', true)->update(['is_primary' => false]);
        }

        $bankAccount = BankAccount::create([
            'id' => Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'type' => $request->input('type', BankAccountUsageType::BOTH->value),
            'is_primary' => $request->input('is_primary', false),
            'bank_name' => $request->bank_name,
            'bank_code' => $request->bank_code ?? substr($request->clabe, 0, 3),
            'clabe' => $request->clabe,
            'account_number' => $request->account_number,
            'account_type' => $request->input('account_type', BankAccountType::DEBIT->value),
            'holder_name' => $request->holder_name,
            'holder_rfc' => $request->holder_rfc,
            'is_own_account' => $request->input('is_own_account', true),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Cuenta bancaria agregada',
            'data' => new BankAccountResource($bankAccount)
        ], 201);
    }

    /**
     * Set a bank account as primary.
     */
    public function setPrimary(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant) {
            return $this->notFoundResponse('Applicant');
        }

        if ($bankAccount->applicant_id !== $applicant->id) {
            return $this->notFoundResponse('Bank account');
        }

        // Unset other primaries
        $applicant->bankAccounts()->where('is_primary', true)->update(['is_primary' => false]);

        // Set this one as primary
        $bankAccount->update(['is_primary' => true]);

        return response()->json([
            'message' => 'Cuenta bancaria establecida como principal',
            'data' => new BankAccountResource($bankAccount->fresh())
        ]);
    }

    /**
     * Delete a bank account (soft delete by marking as inactive).
     */
    public function destroy(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant) {
            return $this->notFoundResponse('Applicant');
        }

        if ($bankAccount->applicant_id !== $applicant->id) {
            return $this->notFoundResponse('Bank account');
        }

        // Cannot delete verified bank accounts
        if ($bankAccount->is_verified) {
            return $this->errorResponse('No se puede eliminar una cuenta bancaria verificada');
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

        return response()->json(['message' => 'Cuenta bancaria eliminada']);
    }

    /**
     * Update primary bank info (used in onboarding Step 5).
     */
    public function updatePrimary(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        $validation = $this->validateRequest($request->all(), [
            'bank_name' => 'required|string|max:100',
            'bank_code' => 'nullable|string|max:10',
            'clabe' => ['required', 'string', 'size:18', new \App\Rules\ValidClabe],
            'account_number' => 'nullable|string|max:20',
            'account_type' => ['sometimes', Rule::in(BankAccountType::values())],
            'holder_name' => 'required|string|max:200',
            'holder_rfc' => 'nullable|string|max:13',
        ]);

        if ($this->isValidationError($validation)) {
            return $validation;
        }

        // Deactivate any existing primary bank account
        $applicant->bankAccounts()->where('is_primary', true)->update(['is_primary' => false]);

        // Create new primary bank account
        $bankAccount = BankAccount::create([
            'id' => Str::uuid(),
            'tenant_id' => $applicant->tenant_id,
            'applicant_id' => $applicant->id,
            'type' => BankAccountUsageType::BOTH->value,
            'is_primary' => true,
            'bank_name' => $request->bank_name,
            'bank_code' => $request->bank_code ?? substr($request->clabe, 0, 3),
            'clabe' => $request->clabe,
            'account_number' => $request->account_number,
            'account_type' => $request->input('account_type', BankAccountType::DEBIT->value),
            'holder_name' => $request->holder_name,
            'holder_rfc' => $request->holder_rfc,
            'is_own_account' => true,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Información bancaria actualizada',
            'data' => new BankAccountResource($bankAccount)
        ]);
    }

    /**
     * Validate a CLABE number.
     */
    public function validateClabe(Request $request): JsonResponse
    {
        $validation = $this->validateRequest($request->all(), [
            'clabe' => 'required|string|size:18',
        ]);

        if ($this->isValidationError($validation)) {
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
