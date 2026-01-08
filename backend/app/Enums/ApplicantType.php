<?php

namespace App\Enums;

enum ApplicantType: string
{
    case PERSONA_FISICA = 'PERSONA_FISICA';
    case PERSONA_MORAL = 'PERSONA_MORAL';

    public function label(): string
    {
        return match ($this) {
            self::PERSONA_FISICA => 'Persona Física',
            self::PERSONA_MORAL => 'Persona Moral',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
