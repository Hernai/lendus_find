<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

enum UserType: string
{
    use HasOptions;

    case APPLICANT = 'APPLICANT';
    case SUPERVISOR = 'SUPERVISOR';
    case ANALYST = 'ANALYST';
    case ADMIN = 'ADMIN';
    case SUPER_ADMIN = 'SUPER_ADMIN';

    /**
     * Get all staff role types.
     */
    public static function staffRoles(): array
    {
        return [
            self::SUPERVISOR,
            self::ANALYST,
            self::ADMIN,
            self::SUPER_ADMIN,
        ];
    }

    /**
     * Get staff role values as strings for validation.
     */
    public static function staffValues(): array
    {
        return array_map(fn($role) => $role->value, self::staffRoles());
    }

    /**
     * Check if this is a staff role.
     */
    public function isStaff(): bool
    {
        return in_array($this, self::staffRoles());
    }

    /**
     * Get human-readable label in Spanish.
     */
    public function label(): string
    {
        return match ($this) {
            self::APPLICANT => 'Solicitante',
            self::SUPERVISOR => 'Supervisor',
            self::ANALYST => 'Analista',
            self::ADMIN => 'Administrador',
            self::SUPER_ADMIN => 'Super Administrador',
        };
    }

    /**
     * Get all values as array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
