<?php

namespace App\Services\Person;

use App\Models\Person;
use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BankAccountService
{
    /**
     * Create a new bank account for a person.
     */
    public function create(Person $person, array $data): BankAccount
    {
        return DB::transaction(function () use ($person, $data) {
            // If this is primary, unset existing primary
            if ($data['is_primary'] ?? false) {
                BankAccount::where('entity_type', 'persons')
                    ->where('entity_id', $person->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $data['tenant_id'] = $person->tenant_id;
            $data['entity_type'] = 'persons';
            $data['entity_id'] = $person->id;
            $data['status'] = $data['status'] ?? BankAccount::STATUS_ACTIVE;

            // Set holder name if not provided
            if (empty($data['holder_name'])) {
                $data['holder_name'] = $person->full_name;
            }

            return BankAccount::create($data);
        });
    }

    /**
     * Update a bank account.
     */
    public function update(BankAccount $account, array $data): BankAccount
    {
        $account->update($data);

        return $account->fresh();
    }

    /**
     * Delete a bank account (soft delete).
     */
    public function delete(BankAccount $account): bool
    {
        return $account->delete();
    }

    /**
     * Find a bank account by ID.
     */
    public function find(string $id): ?BankAccount
    {
        return BankAccount::find($id);
    }

    /**
     * Get all bank accounts for a person.
     */
    public function getForPerson(string $personId): Collection
    {
        return BankAccount::getForPerson($personId);
    }

    /**
     * Get primary bank account for a person.
     */
    public function getPrimary(string $personId): ?BankAccount
    {
        return BankAccount::findPrimaryForPerson($personId);
    }

    /**
     * Get bank account by CLABE.
     */
    public function findByClabe(string $clabe, string $tenantId): ?BankAccount
    {
        return BankAccount::findByClabe($clabe, $tenantId);
    }

    /**
     * Set as primary bank account.
     */
    public function setPrimary(BankAccount $account): BankAccount
    {
        $account->setAsPrimary();

        return $account->fresh();
    }

    /**
     * Mark bank account as verified.
     */
    public function verify(
        BankAccount $account,
        string $method,
        ?string $verifiedBy = null,
        ?array $verificationData = null
    ): BankAccount {
        $account->markAsVerified($method, $verifiedBy, $verificationData);

        return $account->fresh();
    }

    /**
     * Mark bank account as unverified.
     */
    public function unverify(BankAccount $account): BankAccount
    {
        $account->markAsUnverified();

        return $account->fresh();
    }

    /**
     * Deactivate bank account.
     */
    public function deactivate(BankAccount $account): BankAccount
    {
        $account->deactivate();

        return $account->fresh();
    }

    /**
     * Close bank account.
     */
    public function close(BankAccount $account): BankAccount
    {
        $account->close();

        return $account->fresh();
    }

    /**
     * Freeze bank account.
     */
    public function freeze(BankAccount $account): BankAccount
    {
        $account->freeze();

        return $account->fresh();
    }

    /**
     * Reactivate bank account.
     */
    public function reactivate(BankAccount $account): BankAccount
    {
        $account->reactivate();

        return $account->fresh();
    }

    /**
     * Add or update primary bank account for a person.
     */
    public function setPrimaryAccount(Person $person, array $accountData): BankAccount
    {
        $accountData['is_primary'] = true;

        // Check if CLABE already exists for this person
        $existing = BankAccount::where('entity_type', 'persons')
            ->where('entity_id', $person->id)
            ->where('clabe', $accountData['clabe'])
            ->first();

        if ($existing) {
            // Update and set as primary
            $existing->update($accountData);
            $existing->setAsPrimary();
            return $existing->fresh();
        }

        return $this->create($person, $accountData);
    }

    /**
     * Get accounts for disbursement.
     */
    public function getForDisbursement(string $personId): Collection
    {
        return BankAccount::where('entity_type', 'persons')
            ->where('entity_id', $personId)
            ->forDisbursement()
            ->verified()
            ->active()
            ->get();
    }

    /**
     * Get accounts for collection.
     */
    public function getForCollection(string $personId): Collection
    {
        return BankAccount::where('entity_type', 'persons')
            ->where('entity_id', $personId)
            ->forCollection()
            ->active()
            ->get();
    }

    /**
     * Get primary disbursement account.
     */
    public function getPrimaryForDisbursement(string $personId): ?BankAccount
    {
        // First try primary account
        $primary = $this->getPrimary($personId);
        if ($primary && $primary->canReceiveDisbursement()) {
            return $primary;
        }

        // Fall back to any verified disbursement account
        return BankAccount::where('entity_type', 'persons')
            ->where('entity_id', $personId)
            ->forDisbursement()
            ->verified()
            ->active()
            ->first();
    }

    /**
     * Validate CLABE.
     */
    public function validateClabe(string $clabe): bool
    {
        return BankAccount::isValidClabe($clabe);
    }

    /**
     * Extract bank code from CLABE.
     */
    public function extractBankCode(string $clabe): string
    {
        return BankAccount::extractBankCode($clabe);
    }

    /**
     * Get bank accounts by bank.
     */
    public function getByBank(string $tenantId, string $bankCode): Collection
    {
        return BankAccount::where('tenant_id', $tenantId)
            ->where('bank_code', $bankCode)
            ->get();
    }

    /**
     * Get verified accounts for a person.
     */
    public function getVerified(string $personId): Collection
    {
        return BankAccount::where('entity_type', 'persons')
            ->where('entity_id', $personId)
            ->verified()
            ->get();
    }

    /**
     * Check if person has verified bank account.
     */
    public function hasVerified(string $personId): bool
    {
        return BankAccount::where('entity_type', 'persons')
            ->where('entity_id', $personId)
            ->verified()
            ->exists();
    }

    /**
     * Check if person can receive disbursement.
     */
    public function canReceiveDisbursement(string $personId): bool
    {
        return BankAccount::where('entity_type', 'persons')
            ->where('entity_id', $personId)
            ->where('is_verified', true)
            ->where('is_for_disbursement', true)
            ->active()
            ->exists();
    }

    /**
     * Get account summary for a person.
     */
    public function getSummary(string $personId): array
    {
        $accounts = $this->getForPerson($personId);
        $primary = $accounts->firstWhere('is_primary', true);

        return [
            'total' => $accounts->count(),
            'verified' => $accounts->where('is_verified', true)->count(),
            'active' => $accounts->where('status', BankAccount::STATUS_ACTIVE)->count(),
            'has_primary' => $primary !== null,
            'primary_bank' => $primary?->bank_name,
            'primary_masked_clabe' => $primary?->masked_clabe,
            'can_receive_disbursement' => $this->canReceiveDisbursement($personId),
        ];
    }

    /**
     * Get Mexican bank name from code.
     */
    public function getBankName(string $bankCode): ?string
    {
        $banks = [
            '002' => 'Citibanamex',
            '012' => 'BBVA México',
            '014' => 'Santander',
            '021' => 'HSBC',
            '030' => 'BajÍo',
            '036' => 'Inbursa',
            '044' => 'Scotiabank',
            '058' => 'Banregio',
            '072' => 'Banorte',
            '106' => 'Bank of America',
            '127' => 'Azteca',
            '128' => 'Autofin',
            '130' => 'Compartamos',
            '137' => 'Bancoppel',
            '140' => 'CIBanco',
            '145' => 'BanCrea',
            '166' => 'Bansefi',
            '168' => 'Hipotecaria Federal',
            '638' => 'Nu México',
            '646' => 'STP',
            '659' => 'UNAGRA',
            '901' => 'CLS',
            '902' => 'Indeval',
        ];

        return $banks[$bankCode] ?? null;
    }
}
