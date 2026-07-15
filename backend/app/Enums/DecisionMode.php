<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Modo de una política del motor de decisión.
 *
 * OFF: el motor no evalúa (flujo 100% manual, comportamiento previo).
 * SHADOW: evalúa y registra qué habría decidido, sin actuar.
 * ACTIVE: evalúa y ejecuta las salidas (oferta / revisión / rechazo).
 */
enum DecisionMode: string
{
    use HasOptions;

    case OFF = 'OFF';
    case SHADOW = 'SHADOW';
    case ACTIVE = 'ACTIVE';

    public function label(): string
    {
        return match ($this) {
            self::OFF => 'Apagado',
            self::SHADOW => 'Modo sombra',
            self::ACTIVE => 'Activo',
        };
    }

    public function evaluates(): bool
    {
        return $this !== self::OFF;
    }

    public function executes(): bool
    {
        return $this === self::ACTIVE;
    }
}
