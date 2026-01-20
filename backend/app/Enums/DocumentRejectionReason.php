<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Document rejection reason enum.
 *
 * Used when rejecting documents to specify the reason.
 */
enum DocumentRejectionReason: string
{
    use HasOptions;

    case ILLEGIBLE = 'ILLEGIBLE';
    case EXPIRED = 'EXPIRED';
    case WRONG_DOC = 'WRONG_DOC';
    case INCOMPLETE = 'INCOMPLETE';
    case TAMPERED = 'TAMPERED';
    case MISMATCH = 'MISMATCH';
    case OTHER = 'OTHER';

    /**
     * Get human-readable label in Spanish.
     */
    public function label(): string
    {
        return match ($this) {
            self::ILLEGIBLE => 'Documento ilegible',
            self::EXPIRED => 'Documento vencido',
            self::WRONG_DOC => 'Documento incorrecto',
            self::INCOMPLETE => 'Documento incompleto',
            self::TAMPERED => 'Documento alterado',
            self::MISMATCH => 'No coincide con datos',
            self::OTHER => 'Otro',
        };
    }

    /**
     * Get color for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::ILLEGIBLE => 'yellow',
            self::EXPIRED => 'orange',
            self::WRONG_DOC => 'red',
            self::INCOMPLETE => 'yellow',
            self::TAMPERED => 'red',
            self::MISMATCH => 'orange',
            self::OTHER => 'gray',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
