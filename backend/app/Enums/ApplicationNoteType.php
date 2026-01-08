<?php

namespace App\Enums;

enum ApplicationNoteType: string
{
    case NOTE = 'NOTE';
    case STATUS_CHANGE = 'STATUS_CHANGE';
    case CALL = 'CALL';
    case EMAIL = 'EMAIL';
    case SYSTEM = 'SYSTEM';

    public function label(): string
    {
        return match ($this) {
            self::NOTE => 'Nota',
            self::STATUS_CHANGE => 'Cambio de estado',
            self::CALL => 'Llamada',
            self::EMAIL => 'Correo',
            self::SYSTEM => 'Sistema',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::NOTE => 'edit',
            self::STATUS_CHANGE => 'refresh',
            self::CALL => 'phone',
            self::EMAIL => 'mail',
            self::SYSTEM => 'cpu',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
