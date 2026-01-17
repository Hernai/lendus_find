<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Relationship type enum for references.
 *
 * Uses English values as canonical for consistency across all enums.
 * Includes normalize() method for compatibility with legacy Spanish values.
 */
enum Relationship: string
{
    use HasOptions;

    // Family relationships
    case PARENT = 'PARENT';
    case SIBLING = 'SIBLING';
    case SPOUSE = 'SPOUSE';
    case CHILD = 'CHILD';
    case UNCLE_AUNT = 'UNCLE_AUNT';
    case COUSIN = 'COUSIN';
    case GRANDPARENT = 'GRANDPARENT';
    case OTHER_FAMILY = 'OTHER_FAMILY';

    // Non-family relationships
    case FRIEND = 'FRIEND';
    case NEIGHBOR = 'NEIGHBOR';
    case COWORKER = 'COWORKER';
    case BOSS = 'BOSS';
    case ACQUAINTANCE = 'ACQUAINTANCE';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::PARENT => 'Padre/Madre',
            self::SIBLING => 'Hermano(a)',
            self::SPOUSE => 'Cónyuge',
            self::CHILD => 'Hijo(a)',
            self::UNCLE_AUNT => 'Tío(a)',
            self::COUSIN => 'Primo(a)',
            self::GRANDPARENT => 'Abuelo(a)',
            self::OTHER_FAMILY => 'Otro familiar',
            self::FRIEND => 'Amigo(a)',
            self::NEIGHBOR => 'Vecino(a)',
            self::COWORKER => 'Compañero de trabajo',
            self::BOSS => 'Jefe/Supervisor',
            self::ACQUAINTANCE => 'Conocido',
            self::OTHER => 'Otro',
        };
    }

    /**
     * Check if this is a family relationship.
     */
    public function isFamily(): bool
    {
        return in_array($this, [
            self::PARENT,
            self::SIBLING,
            self::SPOUSE,
            self::CHILD,
            self::UNCLE_AUNT,
            self::COUSIN,
            self::GRANDPARENT,
            self::OTHER_FAMILY,
        ]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get only family relationships.
     */
    public static function familyOptions(): array
    {
        return array_values(array_filter(
            self::toOptions(),
            fn ($opt) => self::from($opt['value'])->isFamily()
        ));
    }

    /**
     * Get only non-family relationships.
     */
    public static function nonFamilyOptions(): array
    {
        return array_values(array_filter(
            self::toOptions(),
            fn ($opt) => !self::from($opt['value'])->isFamily()
        ));
    }

    /**
     * Normalize a value to the canonical enum.
     * Handles both English and legacy Spanish values.
     */
    public static function normalize(string $value): ?self
    {
        $normalized = strtoupper(trim($value));

        $direct = self::tryFrom($normalized);
        if ($direct !== null) {
            return $direct;
        }

        return match ($normalized) {
            'PADRE_MADRE', 'PADRE', 'MADRE' => self::PARENT,
            'HERMANO', 'HERMANA' => self::SIBLING,
            'CONYUGE', 'ESPOSO', 'ESPOSA' => self::SPOUSE,
            'HIJO', 'HIJA' => self::CHILD,
            'TIO', 'TIA' => self::UNCLE_AUNT,
            'PRIMO', 'PRIMA' => self::COUSIN,
            'ABUELO', 'ABUELA' => self::GRANDPARENT,
            'OTRO_FAMILIAR' => self::OTHER_FAMILY,
            'AMIGO', 'AMIGA' => self::FRIEND,
            'VECINO', 'VECINA' => self::NEIGHBOR,
            'COMPANERO_TRABAJO', 'COMPAÑERO_TRABAJO' => self::COWORKER,
            'JEFE', 'SUPERVISOR' => self::BOSS,
            'CONOCIDO' => self::ACQUAINTANCE,
            'OTRO' => self::OTHER,
            default => null,
        };
    }
}
