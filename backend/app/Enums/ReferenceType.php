<?php

namespace App\Enums;

enum ReferenceType: string
{
    case PERSONAL = 'PERSONAL';
    case WORK = 'WORK';

    public function label(): string
    {
        return match ($this) {
            self::PERSONAL => 'Personal',
            self::WORK => 'Laboral',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
