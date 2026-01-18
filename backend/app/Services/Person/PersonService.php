<?php

namespace App\Services\Person;

use App\Models\Person;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PersonService
{
    /**
     * Create a new person.
     */
    public function create(array $data, string $tenantId): Person
    {
        $data['tenant_id'] = $tenantId;

        return DB::transaction(function () use ($data) {
            $person = Person::create($data);
            $person->profile_completeness = $person->calculateCompleteness();
            $person->save();

            return $person;
        });
    }

    /**
     * Update a person.
     */
    public function update(Person $person, array $data): Person
    {
        return DB::transaction(function () use ($person, $data) {
            $person->update($data);
            $person->profile_completeness = $person->calculateCompleteness();
            $person->save();

            return $person->fresh();
        });
    }

    /**
     * Delete a person (soft delete).
     */
    public function delete(Person $person, ?string $deletedBy = null): bool
    {
        if ($deletedBy) {
            $person->deleted_by = $deletedBy;
            $person->save();
        }

        return $person->delete();
    }

    /**
     * Find a person by ID.
     */
    public function find(string $id): ?Person
    {
        return Person::find($id);
    }

    /**
     * Find a person by ID or fail.
     */
    public function findOrFail(string $id): Person
    {
        return Person::findOrFail($id);
    }

    /**
     * Find a person by CURP.
     */
    public function findByCurp(string $curp, string $tenantId): ?Person
    {
        return Person::findByCurp($curp, $tenantId);
    }

    /**
     * Find a person by RFC.
     */
    public function findByRfc(string $rfc, string $tenantId): ?Person
    {
        return Person::findByRfc($rfc, $tenantId);
    }

    /**
     * Find a person by account ID.
     */
    public function findByAccountId(string $accountId): ?Person
    {
        return Person::where('account_id', $accountId)->first();
    }

    /**
     * Get persons with pagination and filters.
     */
    public function paginate(
        string $tenantId,
        int $perPage = 15,
        array $filters = [],
        string $sortBy = 'created_at',
        string $sortDirection = 'desc'
    ): LengthAwarePaginator {
        $query = Person::where('tenant_id', $tenantId);

        // Apply filters
        if (!empty($filters['search'])) {
            $query->searchByName($filters['search']);
        }

        if (!empty($filters['kyc_status'])) {
            $query->where('kyc_status', $filters['kyc_status']);
        }

        if (!empty($filters['marital_status'])) {
            $query->where('marital_status', $filters['marital_status']);
        }

        if (!empty($filters['education_level'])) {
            $query->where('education_level', $filters['education_level']);
        }

        if (!empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (isset($filters['kyc_verified'])) {
            if ($filters['kyc_verified']) {
                $query->kycVerified();
            } else {
                $query->where('kyc_status', '!=', 'VERIFIED');
            }
        }

        if (isset($filters['profile_complete'])) {
            if ($filters['profile_complete']) {
                $query->profileComplete();
            } else {
                $query->where('profile_completeness', '<', 100);
            }
        }

        if (!empty($filters['min_completeness'])) {
            $query->where('profile_completeness', '>=', (int) $filters['min_completeness']);
        }

        // Apply sorting
        $allowedSortFields = ['created_at', 'updated_at', 'first_name', 'last_name_1', 'birth_date', 'profile_completeness'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortDirection === 'asc' ? 'asc' : 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get all persons for a tenant.
     */
    public function getAllForTenant(string $tenantId): Collection
    {
        return Person::where('tenant_id', $tenantId)->get();
    }

    /**
     * Update KYC status.
     */
    public function updateKycStatus(
        Person $person,
        string $status,
        ?array $kycData = null,
        ?string $verifiedBy = null
    ): Person {
        $person->updateKycStatus($status, $kycData, $verifiedBy);

        return $person->fresh();
    }

    /**
     * Link person to account.
     */
    public function linkToAccount(Person $person, string $accountId): Person
    {
        $person->account_id = $accountId;
        $person->save();

        return $person;
    }

    /**
     * Unlink person from account.
     */
    public function unlinkFromAccount(Person $person): Person
    {
        $person->account_id = null;
        $person->save();

        return $person;
    }

    /**
     * Recalculate profile completeness.
     */
    public function recalculateCompleteness(Person $person): int
    {
        $completeness = $person->calculateCompleteness();
        $person->profile_completeness = $completeness;
        $person->save();

        return $completeness;
    }

    /**
     * Get person with all related data.
     */
    public function getWithRelations(string $personId, array $relations = []): ?Person
    {
        $defaultRelations = [
            'identifications' => fn($q) => $q->current(),
            'currentHomeAddress',
            'currentEmployment',
            'primaryBankAccount',
            'references',
        ];

        $relations = empty($relations) ? array_keys($defaultRelations) : $relations;

        return Person::with($relations)->find($personId);
    }

    /**
     * Get person profile summary.
     */
    public function getProfileSummary(Person $person): array
    {
        $person->load([
            'currentCurp',
            'currentRfc',
            'currentHomeAddress',
            'currentEmployment',
            'primaryBankAccount',
            'references' => fn($q) => $q->verified(),
        ]);

        return [
            'id' => $person->id,
            'full_name' => $person->full_name,
            'age' => $person->age,
            'gender' => $person->gender,
            'gender_label' => $person->gender_label,
            'marital_status' => $person->marital_status,
            'marital_status_label' => $person->marital_status_label,
            'education_level' => $person->education_level,
            'education_level_label' => $person->education_level_label,
            'curp' => $person->curp,
            'rfc' => $person->rfc,
            'kyc_status' => $person->kyc_status,
            'is_kyc_verified' => $person->is_kyc_verified,
            'profile_completeness' => $person->profile_completeness,
            'has_home_address' => $person->currentHomeAddress !== null,
            'has_employment' => $person->currentEmployment !== null,
            'has_bank_account' => $person->primaryBankAccount !== null,
            'verified_references_count' => $person->references->count(),
            'created_at' => $person->created_at,
        ];
    }

    /**
     * Check if person exists by CURP or RFC in tenant.
     */
    public function existsByCurpOrRfc(string $tenantId, ?string $curp, ?string $rfc): ?Person
    {
        if ($curp) {
            $person = $this->findByCurp($curp, $tenantId);
            if ($person) {
                return $person;
            }
        }

        if ($rfc) {
            return $this->findByRfc($rfc, $tenantId);
        }

        return null;
    }

    /**
     * Merge two person records.
     */
    public function merge(Person $primary, Person $secondary): Person
    {
        return DB::transaction(function () use ($primary, $secondary) {
            // Move all related records from secondary to primary
            $secondary->identifications()->update(['person_id' => $primary->id]);
            $secondary->addresses()->update(['person_id' => $primary->id]);
            $secondary->employments()->update(['person_id' => $primary->id]);
            $secondary->references()->update(['person_id' => $primary->id]);
            $secondary->bankAccounts()->update(['owner_id' => $primary->id]);

            // If secondary has account and primary doesn't, transfer it
            if ($secondary->account_id && !$primary->account_id) {
                $primary->account_id = $secondary->account_id;
                $primary->save();
            }

            // Merge any missing data from secondary to primary
            $fieldsToMerge = [
                'birth_date', 'birth_state', 'gender', 'marital_status',
                'education_level', 'dependents_count'
            ];

            foreach ($fieldsToMerge as $field) {
                if (empty($primary->{$field}) && !empty($secondary->{$field})) {
                    $primary->{$field} = $secondary->{$field};
                }
            }

            $primary->save();

            // Delete secondary
            $secondary->delete();

            // Recalculate completeness
            $this->recalculateCompleteness($primary);

            return $primary->fresh();
        });
    }

    /**
     * Get statistics for persons in tenant.
     */
    public function getStatistics(string $tenantId): array
    {
        $query = Person::where('tenant_id', $tenantId);

        return [
            'total' => (clone $query)->count(),
            'kyc_verified' => (clone $query)->kycVerified()->count(),
            'kyc_pending' => (clone $query)->where('kyc_status', 'PENDING')->count(),
            'kyc_rejected' => (clone $query)->where('kyc_status', 'REJECTED')->count(),
            'profile_complete' => (clone $query)->profileComplete()->count(),
            'with_account' => (clone $query)->whereNotNull('account_id')->count(),
            'average_completeness' => (clone $query)->avg('profile_completeness') ?? 0,
        ];
    }
}
