<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Modalidad de arrendamiento. Cada producto de tipo ARRENDAMIENTO declara la
 * suya en `rules.lease.modality`. El financiero suele incluir opción de compra
 * y valor residual (ver `rules.lease.purchase_option` / `residual_value_pct`).
 */
enum LeaseModality: string
{
    use HasOptions;

    case PURO = 'PURO';
    case FINANCIERO = 'FINANCIERO';

    public function label(): string
    {
        return match ($this) {
            self::PURO => 'Arrendamiento Puro',
            self::FINANCIERO => 'Arrendamiento Financiero',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
