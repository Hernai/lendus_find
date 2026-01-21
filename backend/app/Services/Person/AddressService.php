<?php

namespace App\Services\Person;

use App\Models\Person;
use App\Models\Address;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AddressService
{
    /**
     * Create a new address for a person.
     */
    public function create(Person $person, array $data): Address
    {
        return DB::transaction(function () use ($person, $data) {
            $type = $data['type'] ?? 'HOME';

            // If this is a current address, mark existing ones of same type as historical
            if ($data['is_current'] ?? true) {
                Address::where('entity_type', 'persons')
                    ->where('entity_id', $person->id)
                    ->where('type', $type)
                    ->where('is_current', true)
                    ->update(['is_current' => false]);
            }

            $data['tenant_id'] = $person->tenant_id;
            $data['entity_type'] = 'persons';
            $data['entity_id'] = $person->id;
            $data['is_current'] = $data['is_current'] ?? true;
            $data['status'] = $data['status'] ?? Address::STATUS_PENDING;

            return Address::create($data);
        });
    }

    /**
     * Update an address.
     */
    public function update(Address $address, array $data): Address
    {
        $address->update($data);

        return $address->fresh();
    }

    /**
     * Delete an address (soft delete).
     */
    public function delete(Address $address): bool
    {
        return $address->delete();
    }

    /**
     * Find an address by ID.
     */
    public function find(string $id): ?Address
    {
        return Address::find($id);
    }

    /**
     * Get all addresses for a person.
     */
    public function getForPerson(string $personId): Collection
    {
        return Address::where('entity_type', 'persons')
            ->where('entity_id', $personId)
            ->orderBy('is_current', 'desc')
            ->orderBy('type')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get current addresses for a person.
     */
    public function getCurrentForPerson(string $personId): Collection
    {
        return Address::where('entity_type', 'persons')
            ->where('entity_id', $personId)
            ->current()
            ->get();
    }

    /**
     * Get current address of a specific type.
     */
    public function getCurrentByType(string $personId, string $type): ?Address
    {
        return Address::findCurrentByType($personId, $type);
    }

    /**
     * Get current home address.
     */
    public function getCurrentHome(string $personId): ?Address
    {
        return $this->getCurrentByType($personId, 'HOME');
    }

    /**
     * Get current work address.
     */
    public function getCurrentWork(string $personId): ?Address
    {
        return $this->getCurrentByType($personId, 'WORK');
    }

    /**
     * Mark address as verified.
     */
    public function verify(
        Address $address,
        string $method,
        ?string $verifiedBy = null,
        ?array $verificationData = null
    ): Address {
        $address->markAsVerified($method, $verifiedBy, $verificationData);

        return $address->fresh();
    }

    /**
     * Mark address as rejected.
     */
    public function reject(Address $address, string $reason): Address
    {
        $address->markAsRejected($reason);

        return $address->fresh();
    }

    /**
     * Replace an address with a new version.
     */
    public function replace(
        Address $oldAddress,
        array $newData,
        string $reason = 'MOVED'
    ): Address {
        return $oldAddress->replaceWith($newData, $reason);
    }

    /**
     * Set home address for a person.
     */
    public function setHomeAddress(Person $person, array $addressData): Address
    {
        $addressData['type'] = 'HOME';

        $existing = $this->getCurrentHome($person->id);

        if ($existing && $this->isSameAddress($existing, $addressData)) {
            // Same address, just update non-address fields
            $fieldsToUpdate = array_diff_key($addressData, array_flip([
                'street', 'exterior_number', 'interior_number',
                'neighborhood', 'municipality', 'state', 'postal_code', 'country'
            ]));

            if (!empty($fieldsToUpdate)) {
                $existing->update($fieldsToUpdate);
            }

            return $existing;
        }

        if ($existing) {
            return $this->replace($existing, $addressData, 'MOVED');
        }

        return $this->create($person, $addressData);
    }

    /**
     * Set work address for a person.
     */
    public function setWorkAddress(Person $person, array $addressData): Address
    {
        $addressData['type'] = 'WORK';

        $existing = $this->getCurrentWork($person->id);

        if ($existing && $this->isSameAddress($existing, $addressData)) {
            return $existing;
        }

        if ($existing) {
            return $this->replace($existing, $addressData, 'JOB_CHANGE');
        }

        return $this->create($person, $addressData);
    }

    /**
     * Update geolocation for an address.
     */
    public function setGeolocation(
        Address $address,
        float $latitude,
        float $longitude,
        ?float $accuracy = null
    ): Address {
        $address->latitude = $latitude;
        $address->longitude = $longitude;

        if ($accuracy !== null) {
            $address->geocode_accuracy = $accuracy <= 10 ? 'ROOFTOP' : ($accuracy <= 50 ? 'RANGE_INTERPOLATED' : 'APPROXIMATE');
            $address->metadata = array_merge(
                $address->metadata ?? [],
                ['geolocation' => ['accuracy' => $accuracy, 'captured_at' => now()->toIso8601String()]]
            );
        }

        $address->save();

        return $address;
    }

    /**
     * Get address history for a person and type.
     */
    public function getHistory(string $personId, string $type): Collection
    {
        return Address::where('entity_type', 'persons')
            ->where('entity_id', $personId)
            ->where('type', $type)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Check if person has verified address of type.
     */
    public function hasVerified(string $personId, string $type): bool
    {
        return Address::where('entity_type', 'persons')
            ->where('entity_id', $personId)
            ->where('type', $type)
            ->where('is_current', true)
            ->verified()
            ->exists();
    }

    /**
     * Get all pending verification addresses for a person.
     */
    public function getPending(string $personId): Collection
    {
        return Address::where('entity_type', 'persons')
            ->where('entity_id', $personId)
            ->where('is_current', true)
            ->where('status', Address::STATUS_PENDING)
            ->get();
    }

    /**
     * Get addresses by postal code.
     */
    public function getByPostalCode(string $tenantId, string $postalCode): Collection
    {
        return Address::where('tenant_id', $tenantId)
            ->where('postal_code', $postalCode)
            ->where('is_current', true)
            ->get();
    }

    /**
     * Get addresses near a location.
     */
    public function getNearLocation(
        string $tenantId,
        float $latitude,
        float $longitude,
        float $radiusKm = 5
    ): Collection {
        // Haversine formula for distance calculation
        $query = Address::where('tenant_id', $tenantId)
            ->where('is_current', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw("
                *,
                (6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )) AS distance
            ", [$latitude, $longitude, $latitude])
            ->having('distance', '<=', $radiusKm)
            ->orderBy('distance');

        return $query->get();
    }

    /**
     * Format address as single line.
     */
    public function formatSingleLine(Address $address): string
    {
        return $address->full_address;
    }

    /**
     * Format address as multi-line.
     */
    public function formatMultiLine(Address $address): array
    {
        $lines = [];

        // Line 1: Street and numbers
        $line1 = $address->street;
        if ($address->exterior_number) {
            $line1 .= ' #' . $address->exterior_number;
        }
        if ($address->interior_number) {
            $line1 .= ' Int. ' . $address->interior_number;
        }
        $lines[] = $line1;

        // Line 2: Neighborhood
        if ($address->neighborhood) {
            $lines[] = 'Col. ' . $address->neighborhood;
        }

        // Line 3: Municipality and State
        $line3 = '';
        if ($address->municipality) {
            $line3 = $address->municipality;
        }
        if ($address->state) {
            $line3 .= ($line3 ? ', ' : '') . $address->state;
        }
        if ($line3) {
            $lines[] = $line3;
        }

        // Line 4: Postal code and country
        $line4 = '';
        if ($address->postal_code) {
            $line4 = 'C.P. ' . $address->postal_code;
        }
        if ($address->country && $address->country !== 'MX') {
            $line4 .= ($line4 ? ', ' : '') . $address->country;
        }
        if ($line4) {
            $lines[] = $line4;
        }

        return $lines;
    }

    /**
     * Check if two addresses are the same.
     */
    protected function isSameAddress(Address $existing, array $new): bool
    {
        $fieldsToCompare = ['street', 'exterior_number', 'interior_number', 'neighborhood', 'municipality', 'state', 'postal_code'];

        foreach ($fieldsToCompare as $field) {
            $existingValue = $existing->{$field};
            $newValue = $new[$field] ?? null;

            if (strtolower(trim($existingValue ?? '')) !== strtolower(trim($newValue ?? ''))) {
                return false;
            }
        }

        return true;
    }
}
