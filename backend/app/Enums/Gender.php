<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

enum Gender: string
{
    use HasOptions;
    case M = 'M';
    case F = 'F';
    case O = 'O';

    public function label(): string
    {
        return match ($this) {
            self::M => 'Masculino',
            self::F => 'Femenino',
            self::O => 'Otro',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
