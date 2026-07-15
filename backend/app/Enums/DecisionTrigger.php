<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Origen de una evaluación del motor de decisión.
 */
enum DecisionTrigger: string
{
    use HasOptions;

    case SUBMIT = 'SUBMIT';         // envío de solicitud
    case RENEWAL = 'RENEWAL';       // préstamo liquidado (renovación)
    case PHONE_GATE = 'PHONE_GATE'; // filtro telefónico temprano
    case DRY_RUN = 'DRY_RUN';       // probador del configurador

    public function label(): string
    {
        return match ($this) {
            self::SUBMIT => 'Envío de solicitud',
            self::RENEWAL => 'Renovación',
            self::PHONE_GATE => 'Filtro telefónico',
            self::DRY_RUN => 'Probador',
        };
    }
}
