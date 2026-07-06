<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Catálogo maestro de tipos de activo arrendables. El admin selecciona un
 * subconjunto por producto (`rules.lease.asset_types`) y el aplicante elige
 * uno en el onboarding (paso custom `asset_type`).
 */
enum AssetType: string
{
    use HasOptions;

    case SOLAR_PANELS = 'SOLAR_PANELS';
    case VEHICLE = 'VEHICLE';
    case MACHINERY = 'MACHINERY';

    public function label(): string
    {
        return match ($this) {
            self::SOLAR_PANELS => 'Paneles solares',
            self::VEHICLE => 'Vehículos',
            self::MACHINERY => 'Maquinaria',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SOLAR_PANELS => 'sun',
            self::VEHICLE => 'car',
            self::MACHINERY => 'cog',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
