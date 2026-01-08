<?php

namespace App\Enums;

enum BankAccountUsageType: string
{
    case DISBURSEMENT = 'DISBURSEMENT';
    case PAYMENT = 'PAYMENT';
    case BOTH = 'BOTH';

    public function label(): string
    {
        return match ($this) {
            self::DISBURSEMENT => 'Desembolso',
            self::PAYMENT => 'Pago',
            self::BOTH => 'Ambos',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
