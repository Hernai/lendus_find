<?php

namespace App\Services\Person;

use App\Models\Person;
use App\Models\PersonIdentification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PersonIdentificationService
{
    /**
     * Create a new identification for a person.
     */
    public function create(Person $person, array $data): PersonIdentification
    {
        return DB::transaction(function () use ($person, $data) {
            // If this is a current identification, mark existing ones as historical
            if ($data['is_current'] ?? true) {
                PersonIdentification::where('person_id', $person->id)
                    ->where('type', $data['type'])
                    ->where('is_current', true)
                    ->update(['is_current' => false]);
            }

            $data['tenant_id'] = $person->tenant_id;
            $data['person_id'] = $person->id;
            $data['is_current'] = $data['is_current'] ?? true;
            $data['status'] = $data['status'] ?? PersonIdentification::STATUS_PENDING;

            return PersonIdentification::create($data);
        });
    }

    /**
     * Update an identification.
     */
    public function update(PersonIdentification $identification, array $data): PersonIdentification
    {
        $identification->update($data);

        return $identification->fresh();
    }

    /**
     * Delete an identification (soft delete).
     */
    public function delete(PersonIdentification $identification): bool
    {
        return $identification->delete();
    }

    /**
     * Find an identification by ID.
     */
    public function find(string $id): ?PersonIdentification
    {
        return PersonIdentification::find($id);
    }

    /**
     * Get all identifications for a person.
     */
    public function getForPerson(string $personId): Collection
    {
        return PersonIdentification::where('person_id', $personId)
            ->orderBy('is_current', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get current identifications for a person.
     */
    public function getCurrentForPerson(string $personId): Collection
    {
        return PersonIdentification::where('person_id', $personId)
            ->current()
            ->get();
    }

    /**
     * Get current identification of a specific type.
     */
    public function getCurrentByType(string $personId, string $type): ?PersonIdentification
    {
        return PersonIdentification::findCurrentByType($personId, $type);
    }

    /**
     * Find identification by identifier value.
     */
    public function findByIdentifier(string $type, string $value, string $tenantId): ?PersonIdentification
    {
        return PersonIdentification::findByIdentifier($type, $value, $tenantId);
    }

    /**
     * Mark identification as verified.
     */
    public function verify(
        PersonIdentification $identification,
        string $method,
        ?string $verifiedBy = null,
        ?array $verificationData = null,
        ?float $confidence = null
    ): PersonIdentification {
        $identification->markAsVerified($method, $verifiedBy, $verificationData, $confidence);

        return $identification->fresh();
    }

    /**
     * Mark identification as rejected.
     */
    public function reject(
        PersonIdentification $identification,
        string $reason
    ): PersonIdentification {
        $identification->status = PersonIdentification::STATUS_REJECTED;
        $identification->notes = $reason;
        $identification->save();

        return $identification->fresh();
    }

    /**
     * Mark identification as expired.
     */
    public function expire(PersonIdentification $identification): PersonIdentification
    {
        $identification->markAsExpired();

        return $identification->fresh();
    }

    /**
     * Replace an identification with a new version.
     */
    public function replace(
        PersonIdentification $oldIdentification,
        array $newData,
        string $reason = 'RENEWED'
    ): PersonIdentification {
        return $oldIdentification->replaceWith($newData, $reason);
    }

    /**
     * Create or update CURP for a person.
     */
    public function setCurp(
        Person $person,
        string $curp,
        ?array $documentData = null,
        bool $verified = false
    ): PersonIdentification {
        $existing = $this->getCurrentByType($person->id, 'CURP');

        if ($existing && $existing->identifier_value === $curp) {
            // Same CURP, just update if needed
            if ($documentData) {
                $existing->document_data = array_merge($existing->document_data ?? [], $documentData);
                $existing->save();
            }
            return $existing;
        }

        // Create new or replace
        $data = [
            'type' => 'CURP',
            'identifier_value' => $curp,
            'document_data' => $documentData,
            'is_current' => true,
        ];

        if ($verified) {
            $data['status'] = PersonIdentification::STATUS_VERIFIED;
            $data['verified_at'] = now();
            $data['verification_method'] = 'RENAPO_API';
        }

        if ($existing) {
            return $this->replace($existing, $data, 'UPDATED');
        }

        return $this->create($person, $data);
    }

    /**
     * Create or update RFC for a person.
     */
    public function setRfc(
        Person $person,
        string $rfc,
        ?array $documentData = null,
        bool $verified = false
    ): PersonIdentification {
        $existing = $this->getCurrentByType($person->id, 'RFC');

        if ($existing && $existing->identifier_value === $rfc) {
            if ($documentData) {
                $existing->document_data = array_merge($existing->document_data ?? [], $documentData);
                $existing->save();
            }
            return $existing;
        }

        $data = [
            'type' => 'RFC',
            'identifier_value' => $rfc,
            'document_data' => $documentData,
            'is_current' => true,
        ];

        if ($verified) {
            $data['status'] = PersonIdentification::STATUS_VERIFIED;
            $data['verified_at'] = now();
            $data['verification_method'] = 'SAT_API';
        }

        if ($existing) {
            return $this->replace($existing, $data, 'UPDATED');
        }

        return $this->create($person, $data);
    }

    /**
     * Create or update INE for a person.
     */
    public function setIne(
        Person $person,
        string $cic,
        string $ocr,
        ?\DateTimeInterface $expiresAt = null,
        ?array $documentData = null
    ): PersonIdentification {
        $existing = $this->getCurrentByType($person->id, 'INE');

        $data = [
            'type' => 'INE',
            'identifier_value' => $cic,
            'expires_at' => $expiresAt,
            'document_data' => array_merge($documentData ?? [], [
                'cic' => $cic,
                'ocr' => $ocr,
            ]),
            'is_current' => true,
        ];

        if ($existing) {
            return $this->replace($existing, $data, 'RENEWED');
        }

        return $this->create($person, $data);
    }

    /**
     * Get identification history for a person and type.
     */
    public function getHistory(string $personId, string $type): Collection
    {
        return PersonIdentification::where('person_id', $personId)
            ->where('type', $type)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Check if person has verified identification of type.
     */
    public function hasVerified(string $personId, string $type): bool
    {
        return PersonIdentification::where('person_id', $personId)
            ->where('type', $type)
            ->where('is_current', true)
            ->verified()
            ->exists();
    }

    /**
     * Get all pending verifications for a person.
     */
    public function getPending(string $personId): Collection
    {
        return PersonIdentification::where('person_id', $personId)
            ->where('is_current', true)
            ->where('status', PersonIdentification::STATUS_PENDING)
            ->get();
    }

    /**
     * Get expiring identifications (within days).
     */
    public function getExpiring(string $tenantId, int $days = 30): Collection
    {
        return PersonIdentification::where('tenant_id', $tenantId)
            ->where('is_current', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now())
            ->where('status', '!=', PersonIdentification::STATUS_EXPIRED)
            ->get();
    }

    /**
     * Get expired identifications that are still current.
     */
    public function getExpired(string $tenantId): Collection
    {
        return PersonIdentification::where('tenant_id', $tenantId)
            ->where('is_current', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->where('status', '!=', PersonIdentification::STATUS_EXPIRED)
            ->get();
    }

    /**
     * Process expired identifications.
     */
    public function processExpiredIdentifications(string $tenantId): int
    {
        $expired = $this->getExpired($tenantId);
        $count = 0;

        foreach ($expired as $identification) {
            $identification->markAsExpired();
            $count++;
        }

        return $count;
    }
}
