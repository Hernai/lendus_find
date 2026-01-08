<?php

namespace App\Enums;

enum PaymentFrequency: string
{
    case SEMANAL = 'SEMANAL';
    case QUINCENAL = 'QUINCENAL';
    case MENSUAL = 'MENSUAL';
    case WEEKLY = 'WEEKLY';
    case BIWEEKLY = 'BIWEEKLY';
    case MONTHLY = 'MONTHLY';

    public function label(): string
    {
        return match ($this) {
            self::SEMANAL, self::WEEKLY => 'Semanal',
            self::QUINCENAL, self::BIWEEKLY => 'Quincenal',
            self::MENSUAL, self::MONTHLY => 'Mensual',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
