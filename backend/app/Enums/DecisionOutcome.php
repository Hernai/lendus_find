<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Salida de una evaluación del motor de decisión.
 *
 * OFFER/REVIEW/REJECT/NO_OFFER aplican a evaluaciones de solicitud o
 * renovación; ALLOW/FLAG/BLOCK son resultados del gate telefónico.
 */
enum DecisionOutcome: string
{
    use HasOptions;

    case OFFER = 'OFFER';       // oferta automática (rango autorizado)
    case REVIEW = 'REVIEW';     // revisión manual (bandeja IN_REVIEW)
    case REJECT = 'REJECT';     // rechazo automático (inicia cooldown)
    case NO_OFFER = 'NO_OFFER'; // renovación sin oferta (ej. mora relevante)
    case ALLOW = 'ALLOW';       // gate: continuar
    case FLAG = 'FLAG';         // gate: continuar marcado (fuerza revisión)
    case BLOCK = 'BLOCK';       // gate: detener onboarding

    public function label(): string
    {
        return match ($this) {
            self::OFFER => 'Oferta automática',
            self::REVIEW => 'Revisión manual',
            self::REJECT => 'Rechazo',
            self::NO_OFFER => 'Sin oferta',
            self::ALLOW => 'Permitir',
            self::FLAG => 'Marcar alerta',
            self::BLOCK => 'Bloquear',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OFFER, self::ALLOW => 'green',
            self::REVIEW, self::FLAG => 'yellow',
            self::REJECT, self::BLOCK => 'red',
            self::NO_OFFER => 'gray',
        };
    }
}
