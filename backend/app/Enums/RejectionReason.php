<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Application rejection reason enum.
 *
 * Used when rejecting loan applications to specify the reason.
 */
enum RejectionReason: string
{
    use HasOptions;

    case SCORE_BAJO = 'SCORE_BAJO';
    case INGRESOS_INSUFICIENTES = 'INGRESOS_INSUFICIENTES';
    case HISTORIAL_NEGATIVO = 'HISTORIAL_NEGATIVO';
    case DOCUMENTACION_FALSA = 'DOCUMENTACION_FALSA';
    case REFERENCIAS_NO_VERIFICADAS = 'REFERENCIAS_NO_VERIFICADAS';
    case SOBREENDEUDAMIENTO = 'SOBREENDEUDAMIENTO';
    case POLITICAS_INTERNAS = 'POLITICAS_INTERNAS';
    case OTRO = 'OTRO';

    /**
     * Get human-readable label in Spanish.
     */
    public function label(): string
    {
        return match ($this) {
            self::SCORE_BAJO => 'Score crediticio bajo',
            self::INGRESOS_INSUFICIENTES => 'Ingresos insuficientes',
            self::HISTORIAL_NEGATIVO => 'Historial crediticio negativo',
            self::DOCUMENTACION_FALSA => 'Documentación falsa o inconsistente',
            self::REFERENCIAS_NO_VERIFICADAS => 'Referencias no verificadas',
            self::SOBREENDEUDAMIENTO => 'Sobreendeudamiento',
            self::POLITICAS_INTERNAS => 'No cumple políticas internas',
            self::OTRO => 'Otro motivo',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::SCORE_BAJO, self::INGRESOS_INSUFICIENTES => 'orange',
            self::HISTORIAL_NEGATIVO, self::DOCUMENTACION_FALSA => 'red',
            self::REFERENCIAS_NO_VERIFICADAS => 'yellow',
            self::SOBREENDEUDAMIENTO => 'red',
            self::POLITICAS_INTERNAS => 'gray',
            self::OTRO => 'gray',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
