<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Estados del ciclo de vida de un Loan (préstamo desembolsado).
 * Independiente del Application.status, que solo va hasta APPROVED/SYNCED.
 */
enum LoanStatus: string
{
    use HasOptions;

    case DISBURSED = 'DISBURSED';     // Recién dispersado
    case ACTIVE = 'ACTIVE';            // En curso, esperando pago
    case COMPLETED = 'COMPLETED';      // Pagado completamente
    case DEFAULT = 'DEFAULT';          // En mora (vencido sin pagar)
    case RESTRUCTURED = 'RESTRUCTURED'; // Restructurado (extensiones múltiples u oferta nueva)

    public function label(): string
    {
        return match ($this) {
            self::DISBURSED => 'Desembolsado',
            self::ACTIVE => 'Activo',
            self::COMPLETED => 'Completado',
            self::DEFAULT => 'En mora',
            self::RESTRUCTURED => 'Reestructurado',
        };
    }
}
