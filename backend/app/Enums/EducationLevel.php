<?php

namespace App\Enums;

enum EducationLevel: string
{
    case PRIMARIA = 'PRIMARIA';
    case SECUNDARIA = 'SECUNDARIA';
    case PREPARATORIA = 'PREPARATORIA';
    case TECNICO = 'TECNICO';
    case LICENCIATURA = 'LICENCIATURA';
    case MAESTRIA = 'MAESTRIA';
    case DOCTORADO = 'DOCTORADO';

    public function label(): string
    {
        return match ($this) {
            self::PRIMARIA => 'Primaria',
            self::SECUNDARIA => 'Secundaria',
            self::PREPARATORIA => 'Preparatoria',
            self::TECNICO => 'Técnico',
            self::LICENCIATURA => 'Licenciatura',
            self::MAESTRIA => 'Maestría',
            self::DOCTORADO => 'Doctorado',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
