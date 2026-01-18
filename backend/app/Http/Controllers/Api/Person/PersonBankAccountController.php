<?php

namespace App\Http\Controllers\Api\Person;

use App\Http\Controllers\Controller;
use App\Http\Resources\Person\PersonBankAccountResource;
use App\Models\Person;
use App\Models\PersonBankAccount;
use App\Services\Person\PersonBankAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonBankAccountController extends Controller
{
    public function __construct(
        protected PersonBankAccountService $bankAccountService
    ) {}

    /**
     * List bank accounts for a person.
     */
    public function index(Person $person): AnonymousResourceCollection
    {
        $accounts = $this->bankAccountService->getForPerson($person->id);

        return PersonBankAccountResource::collection($accounts);
    }

    /**
     * Create a new bank account.
     */
    public function store(Request $request, Person $person): JsonResponse
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'bank_code' => 'nullable|string|max:10',
            'clabe' => 'nullable|string|size:18',
            'account_number' => 'nullable|string|max:20',
            'card_number' => 'nullable|string|max:20',
            'holder_name' => 'required|string|max:200',
            'account_type' => 'nullable|string|in:DEBIT,PAYROLL,SAVINGS,CHECKING',
            'is_primary' => 'nullable|boolean',
            'is_for_disbursement' => 'nullable|boolean',
            'is_for_collection' => 'nullable|boolean',
        ]);

        // Validate CLABE if provided
        if (!empty($validated['clabe']) && !$this->bankAccountService->validateClabe($validated['clabe'])) {
            return response()->json([
                'message' => 'Invalid CLABE',
                'errors' => ['clabe' => ['The CLABE check digit is invalid']]
            ], 422);
        }

        $account = $this->bankAccountService->create($person, $validated);

        return (new PersonBankAccountResource($account))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a bank account.
     */
    public function show(Person $person, PersonBankAccount $bankAccount): PersonBankAccountResource
    {
        return new PersonBankAccountResource($bankAccount);
    }

    /**
     * Update a bank account.
     */
    public function update(Request $request, Person $person, PersonBankAccount $bankAccount): PersonBankAccountResource
    {
        $validated = $request->validate([
            'bank_name' => 'sometimes|string|max:100',
            'bank_code' => 'nullable|string|max:10',
            'holder_name' => 'sometimes|string|max:200',
            'account_type' => 'nullable|string|in:DEBIT,PAYROLL,SAVINGS,CHECKING',
            'is_for_disbursement' => 'nullable|boolean',
            'is_for_collection' => 'nullable|boolean',
        ]);

        $bankAccount = $this->bankAccountService->update($bankAccount, $validated);

        return new PersonBankAccountResource($bankAccount);
    }

    /**
     * Delete a bank account.
     */
    public function destroy(Person $person, PersonBankAccount $bankAccount): JsonResponse
    {
        $this->bankAccountService->delete($bankAccount);

        return response()->json(['message' => 'Bank account deleted successfully']);
    }

    /**
     * Get primary bank account.
     */
    public function primary(Person $person): JsonResponse
    {
        $account = $this->bankAccountService->getPrimary($person->id);

        if (!$account) {
            return response()->json(['data' => null, 'found' => false]);
        }

        return response()->json([
            'data' => new PersonBankAccountResource($account),
            'found' => true
        ]);
    }

    /**
     * Set account as primary.
     */
    public function setPrimary(Person $person, PersonBankAccount $bankAccount): PersonBankAccountResource
    {
        $account = $this->bankAccountService->setPrimary($bankAccount);

        return new PersonBankAccountResource($account);
    }

    /**
     * Set primary account with data.
     */
    public function setPrimaryAccount(Request $request, Person $person): PersonBankAccountResource
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'bank_code' => 'nullable|string|max:10',
            'clabe' => 'required|string|size:18',
            'account_number' => 'nullable|string|max:20',
            'holder_name' => 'required|string|max:200',
            'account_type' => 'nullable|string|in:DEBIT,PAYROLL,SAVINGS,CHECKING',
        ]);

        // Validate CLABE
        if (!$this->bankAccountService->validateClabe($validated['clabe'])) {
            abort(422, 'Invalid CLABE check digit');
        }

        $account = $this->bankAccountService->setPrimaryAccount($person, $validated);

        return new PersonBankAccountResource($account);
    }

    /**
     * Verify a bank account.
     */
    public function verify(Request $request, Person $person, PersonBankAccount $bankAccount): PersonBankAccountResource
    {
        $validated = $request->validate([
            'method' => 'required|string|max:50',
            'verification_data' => 'nullable|array',
        ]);

        $verifiedBy = $request->user()?->id;
        $account = $this->bankAccountService->verify(
            $bankAccount,
            $validated['method'],
            $verifiedBy,
            $validated['verification_data'] ?? null
        );

        return new PersonBankAccountResource($account);
    }

    /**
     * Unverify a bank account.
     */
    public function unverify(Person $person, PersonBankAccount $bankAccount): PersonBankAccountResource
    {
        $account = $this->bankAccountService->unverify($bankAccount);

        return new PersonBankAccountResource($account);
    }

    /**
     * Deactivate a bank account.
     */
    public function deactivate(Person $person, PersonBankAccount $bankAccount): PersonBankAccountResource
    {
        $account = $this->bankAccountService->deactivate($bankAccount);

        return new PersonBankAccountResource($account);
    }

    /**
     * Reactivate a bank account.
     */
    public function reactivate(Person $person, PersonBankAccount $bankAccount): PersonBankAccountResource
    {
        $account = $this->bankAccountService->reactivate($bankAccount);

        return new PersonBankAccountResource($account);
    }

    /**
     * Close a bank account.
     */
    public function close(Person $person, PersonBankAccount $bankAccount): PersonBankAccountResource
    {
        $account = $this->bankAccountService->close($bankAccount);

        return new PersonBankAccountResource($account);
    }

    /**
     * Freeze a bank account.
     */
    public function freeze(Person $person, PersonBankAccount $bankAccount): PersonBankAccountResource
    {
        $account = $this->bankAccountService->freeze($bankAccount);

        return new PersonBankAccountResource($account);
    }

    /**
     * Get accounts available for disbursement.
     */
    public function forDisbursement(Person $person): AnonymousResourceCollection
    {
        $accounts = $this->bankAccountService->getForDisbursement($person->id);

        return PersonBankAccountResource::collection($accounts);
    }

    /**
     * Check if person can receive disbursement.
     */
    public function canReceiveDisbursement(Person $person): JsonResponse
    {
        $canReceive = $this->bankAccountService->canReceiveDisbursement($person->id);

        return response()->json(['can_receive_disbursement' => $canReceive]);
    }

    /**
     * Get bank account summary for a person.
     */
    public function summary(Person $person): JsonResponse
    {
        $summary = $this->bankAccountService->getSummary($person->id);

        return response()->json(['data' => $summary]);
    }

    /**
     * Validate a CLABE.
     */
    public function validateClabe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'clabe' => 'required|string|size:18',
        ]);

        $isValid = $this->bankAccountService->validateClabe($validated['clabe']);
        $bankCode = $this->bankAccountService->extractBankCode($validated['clabe']);
        $bankName = $this->bankAccountService->getBankName($bankCode);

        return response()->json([
            'is_valid' => $isValid,
            'bank_code' => $bankCode,
            'bank_name' => $bankName,
        ]);
    }

    /**
     * Get bank name by code.
     */
    public function getBankName(string $code): JsonResponse
    {
        $bankName = $this->bankAccountService->getBankName($code);

        return response()->json([
            'bank_code' => $code,
            'bank_name' => $bankName,
        ]);
    }

    /**
     * Check if person has verified bank account.
     */
    public function hasVerified(Person $person): JsonResponse
    {
        $hasVerified = $this->bankAccountService->hasVerified($person->id);

        return response()->json(['has_verified' => $hasVerified]);
    }
}
