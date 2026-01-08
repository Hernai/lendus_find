<?php

namespace App\Enums;

enum UserType: string
{
    case APPLICANT = 'APPLICANT';
    case AGENT = 'AGENT';
    case ANALYST = 'ANALYST';
    case ADMIN = 'ADMIN';
    case SUPER_ADMIN = 'SUPER_ADMIN';

    /**
     * Get all staff role types.
     */
    public static function staffRoles(): array
    {
        return [
            self::AGENT,
            self::ANALYST,
            self::ADMIN,
            self::SUPER_ADMIN,
        ];
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
            self::AGENT => 'Agente',
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
