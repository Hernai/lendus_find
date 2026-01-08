<?php

namespace App\Enums;

enum VerifiableField: string
{
    case FIRST_NAME = 'first_name';
    case LAST_NAME_1 = 'last_name_1';
    case LAST_NAME_2 = 'last_name_2';
    case CURP = 'curp';
    case RFC = 'rfc';
    case INE = 'ine_clave';
    case BIRTH_DATE = 'birth_date';
    case PHONE = 'phone';
    case EMAIL = 'email';
    case ADDRESS = 'address';
    case EMPLOYMENT = 'employment';

    /**
     * Get human-readable label in Spanish.
     */
    public function label(): string
    {
        return match ($this) {
            self::FIRST_NAME => 'Nombre',
            self::LAST_NAME_1 => 'Apellido Paterno',
            self::LAST_NAME_2 => 'Apellido Materno',
            self::CURP => 'CURP',
            self::RFC => 'RFC',
            self::INE => 'Clave INE',
            self::BIRTH_DATE => 'Fecha de Nacimiento',
            self::PHONE => 'Teléfono',
            self::EMAIL => 'Email',
            self::ADDRESS => 'Domicilio',
            self::EMPLOYMENT => 'Empleo',
        };
    }

    /**
     * Get all values as array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if this is a personal data field.
     */
    public function isPersonalData(): bool
    {
        return in_array($this, [
            self::FIRST_NAME,
            self::LAST_NAME_1,
            self::LAST_NAME_2,
            self::CURP,
            self::RFC,
            self::INE,
            self::BIRTH_DATE,
        ]);
    }

    /**
     * Check if this is a contact field.
     */
    public function isContactInfo(): bool
    {
        return in_array($this, [
            self::PHONE,
            self::EMAIL,
        ]);
    }
}
