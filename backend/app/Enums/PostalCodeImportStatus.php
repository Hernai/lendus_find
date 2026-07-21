<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Estado de una importación del catálogo de códigos postales desde el panel.
 *
 * Máquina de estado del import: PENDING_PARSE → PARSED → APPLYING → APPLIED,
 * o REJECTED (con motivo), o DISCARDED (el usuario lo descarta sin aplicar).
 *
 * Estados "en vuelo" (ocupan la staging compartida y bloquean nuevas cargas):
 * PENDING_PARSE, PARSED, APPLYING. Ver {@see self::IN_FLIGHT}.
 */
enum PostalCodeImportStatus: string
{
    use HasOptions;

    case PENDING_PARSE = 'PENDING_PARSE'; // archivo recibido, parseo en curso
    case PARSED = 'PARSED';               // parseado y validado, listo para aplicar
    case APPLYING = 'APPLYING';           // swap atómico en curso
    case APPLIED = 'APPLIED';             // catálogo reemplazado
    case REJECTED = 'REJECTED';           // rechazado (formato o conteo insuficiente)
    case DISCARDED = 'DISCARDED';         // descartado por el usuario sin aplicar

    /**
     * Estados que ocupan la staging compartida y por tanto impiden iniciar otra
     * carga hasta resolverlos (aplicar o descartar). Serializa los imports para
     * que un `apply` no opere sobre la staging sobrescrita por otra carga.
     *
     * @return array<int, self>
     */
    public const IN_FLIGHT = [self::PENDING_PARSE, self::PARSED, self::APPLYING];

    public function label(): string
    {
        return match ($this) {
            self::PENDING_PARSE => 'Procesando archivo',
            self::PARSED => 'Listo para aplicar',
            self::APPLYING => 'Aplicando',
            self::APPLIED => 'Aplicado',
            self::REJECTED => 'Rechazado',
            self::DISCARDED => 'Descartado',
        };
    }
}
