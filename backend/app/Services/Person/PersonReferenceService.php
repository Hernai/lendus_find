<?php

namespace App\Services\Person;

use App\Models\Person;
use App\Models\PersonReference;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PersonReferenceService
{
    /**
     * Create a new reference for a person.
     */
    public function create(Person $person, array $data): PersonReference
    {
        $data['tenant_id'] = $person->tenant_id;
        $data['person_id'] = $person->id;
        $data['status'] = $data['status'] ?? PersonReference::STATUS_PENDING;

        return PersonReference::create($data);
    }

    /**
     * Update a reference.
     */
    public function update(PersonReference $reference, array $data): PersonReference
    {
        $reference->update($data);

        return $reference->fresh();
    }

    /**
     * Delete a reference (soft delete).
     */
    public function delete(PersonReference $reference): bool
    {
        return $reference->delete();
    }

    /**
     * Find a reference by ID.
     */
    public function find(string $id): ?PersonReference
    {
        return PersonReference::find($id);
    }

    /**
     * Get all references for a person.
     */
    public function getForPerson(string $personId): Collection
    {
        return PersonReference::where('person_id', $personId)
            ->orderBy('type')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get references by type for a person.
     */
    public function getByType(string $personId, string $type): Collection
    {
        return PersonReference::findByPersonAndType($personId, $type);
    }

    /**
     * Get personal references for a person.
     */
    public function getPersonal(string $personId): Collection
    {
        return PersonReference::where('person_id', $personId)
            ->personal()
            ->get();
    }

    /**
     * Get work references for a person.
     */
    public function getWork(string $personId): Collection
    {
        return PersonReference::where('person_id', $personId)
            ->work()
            ->get();
    }

    /**
     * Mark reference as verified.
     */
    public function verify(
        PersonReference $reference,
        ?string $verifiedBy = null,
        ?string $notes = null
    ): PersonReference {
        $reference->markAsVerified($verifiedBy, $notes);

        return $reference->fresh();
    }

    /**
     * Mark reference as rejected.
     */
    public function reject(PersonReference $reference, string $reason): PersonReference
    {
        $reference->markAsRejected($reason);

        return $reference->fresh();
    }

    /**
     * Mark reference as unreachable.
     */
    public function markUnreachable(PersonReference $reference, string $notes): PersonReference
    {
        $reference->markAsUnreachable($notes);

        return $reference->fresh();
    }

    /**
     * Mark reference as no answer.
     */
    public function markNoAnswer(PersonReference $reference): PersonReference
    {
        $reference->markAsNoAnswer();

        return $reference->fresh();
    }

    /**
     * Log a contact attempt for a reference.
     */
    public function logContactAttempt(
        PersonReference $reference,
        string $result,
        ?string $notes = null,
        ?string $byUserId = null
    ): PersonReference {
        $reference->logContactAttempt($result, $notes, $byUserId);

        return $reference->fresh();
    }

    /**
     * Add a personal reference for a person.
     */
    public function addPersonalReference(Person $person, array $data): PersonReference
    {
        $data['type'] = 'PERSONAL';

        return $this->create($person, $data);
    }

    /**
     * Add a work reference for a person.
     */
    public function addWorkReference(Person $person, array $data): PersonReference
    {
        $data['type'] = 'WORK';

        return $this->create($person, $data);
    }

    /**
     * Check if phone exists for person's references.
     */
    public function phoneExists(string $personId, string $phone, ?string $excludeId = null): bool
    {
        $query = PersonReference::where('person_id', $personId)
            ->where('phone', $phone);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get verified references count for a person.
     */
    public function getVerifiedCount(string $personId): int
    {
        return PersonReference::where('person_id', $personId)
            ->verified()
            ->count();
    }

    /**
     * Get verified references for a person.
     */
    public function getVerified(string $personId): Collection
    {
        return PersonReference::where('person_id', $personId)
            ->verified()
            ->get();
    }

    /**
     * Get pending references for a person.
     */
    public function getPending(string $personId): Collection
    {
        return PersonReference::where('person_id', $personId)
            ->pending()
            ->get();
    }

    /**
     * Get references summary for a person.
     */
    public function getSummary(string $personId): array
    {
        $references = $this->getForPerson($personId);

        $personal = $references->where('type', 'PERSONAL');
        $work = $references->where('type', 'WORK');

        return [
            'total' => $references->count(),
            'personal' => [
                'total' => $personal->count(),
                'verified' => $personal->where('status', PersonReference::STATUS_VERIFIED)->count(),
                'pending' => $personal->where('status', PersonReference::STATUS_PENDING)->count(),
            ],
            'work' => [
                'total' => $work->count(),
                'verified' => $work->where('status', PersonReference::STATUS_VERIFIED)->count(),
                'pending' => $work->where('status', PersonReference::STATUS_PENDING)->count(),
            ],
            'verified_count' => $references->where('status', PersonReference::STATUS_VERIFIED)->count(),
            'pending_count' => $references->where('status', PersonReference::STATUS_PENDING)->count(),
            'has_required' => $this->hasRequiredReferences($personId),
        ];
    }

    /**
     * Check if person has required references.
     */
    public function hasRequiredReferences(
        string $personId,
        int $requiredPersonal = 1,
        int $requiredWork = 1
    ): bool {
        $personalCount = PersonReference::where('person_id', $personId)
            ->personal()
            ->count();

        $workCount = PersonReference::where('person_id', $personId)
            ->work()
            ->count();

        return $personalCount >= $requiredPersonal && $workCount >= $requiredWork;
    }

    /**
     * Check if person has verified required references.
     */
    public function hasVerifiedRequiredReferences(
        string $personId,
        int $requiredPersonal = 1,
        int $requiredWork = 1
    ): bool {
        $personalVerified = PersonReference::where('person_id', $personId)
            ->personal()
            ->verified()
            ->count();

        $workVerified = PersonReference::where('person_id', $personId)
            ->work()
            ->verified()
            ->count();

        return $personalVerified >= $requiredPersonal && $workVerified >= $requiredWork;
    }

    /**
     * Get references with most contact attempts.
     */
    public function getMostContacted(string $tenantId, int $limit = 10): Collection
    {
        return PersonReference::where('tenant_id', $tenantId)
            ->whereNotNull('contact_attempts')
            ->get()
            ->sortByDesc(fn($ref) => count($ref->contact_attempts ?? []))
            ->take($limit);
    }

    /**
     * Get references pending verification for tenant.
     */
    public function getPendingForTenant(string $tenantId, int $limit = 50): Collection
    {
        return PersonReference::where('tenant_id', $tenantId)
            ->pending()
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Bulk verify references.
     */
    public function bulkVerify(array $referenceIds, ?string $verifiedBy = null): int
    {
        $count = 0;

        foreach ($referenceIds as $id) {
            $reference = $this->find($id);
            if ($reference && $reference->status === PersonReference::STATUS_PENDING) {
                $this->verify($reference, $verifiedBy);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get family references for a person.
     */
    public function getFamilyReferences(string $personId): Collection
    {
        return PersonReference::where('person_id', $personId)
            ->get()
            ->filter(fn($ref) => $ref->isFamilyRelationship());
    }
}
