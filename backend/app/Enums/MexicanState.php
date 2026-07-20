<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Mexican states enum.
 *
 * Uses official 3-5 character codes commonly used in Mexico.
 */
enum MexicanState: string
{
    use HasOptions;

    case AGUASCALIENTES = 'AGU';
    case BAJA_CALIFORNIA = 'BCN';
    case BAJA_CALIFORNIA_SUR = 'BCS';
    case CAMPECHE = 'CAM';
    case CHIAPAS = 'CHP';
    case CHIHUAHUA = 'CHH';
    case COAHUILA = 'COA';
    case COLIMA = 'COL';
    case CIUDAD_DE_MEXICO = 'CDMX';
    case DURANGO = 'DUR';
    case GUANAJUATO = 'GUA';
    case GUERRERO = 'GRO';
    case HIDALGO = 'HID';
    case JALISCO = 'JAL';
    case ESTADO_DE_MEXICO = 'MEX';
    case MICHOACAN = 'MIC';
    case MORELOS = 'MOR';
    case NAYARIT = 'NAY';
    case NUEVO_LEON = 'NLE';
    case OAXACA = 'OAX';
    case PUEBLA = 'PUE';
    case QUERETARO = 'QUE';
    case QUINTANA_ROO = 'ROO';
    case SAN_LUIS_POTOSI = 'SLP';
    case SINALOA = 'SIN';
    case SONORA = 'SON';
    case TABASCO = 'TAB';
    case TAMAULIPAS = 'TAM';
    case TLAXCALA = 'TLA';
    case VERACRUZ = 'VER';
    case YUCATAN = 'YUC';
    case ZACATECAS = 'ZAC';

    public function label(): string
    {
        return match ($this) {
            self::AGUASCALIENTES => 'Aguascalientes',
            self::BAJA_CALIFORNIA => 'Baja California',
            self::BAJA_CALIFORNIA_SUR => 'Baja California Sur',
            self::CAMPECHE => 'Campeche',
            self::CHIAPAS => 'Chiapas',
            self::CHIHUAHUA => 'Chihuahua',
            self::COAHUILA => 'Coahuila',
            self::COLIMA => 'Colima',
            self::CIUDAD_DE_MEXICO => 'Ciudad de México',
            self::DURANGO => 'Durango',
            self::GUANAJUATO => 'Guanajuato',
            self::GUERRERO => 'Guerrero',
            self::HIDALGO => 'Hidalgo',
            self::JALISCO => 'Jalisco',
            self::ESTADO_DE_MEXICO => 'Estado de México',
            self::MICHOACAN => 'Michoacán',
            self::MORELOS => 'Morelos',
            self::NAYARIT => 'Nayarit',
            self::NUEVO_LEON => 'Nuevo León',
            self::OAXACA => 'Oaxaca',
            self::PUEBLA => 'Puebla',
            self::QUERETARO => 'Querétaro',
            self::QUINTANA_ROO => 'Quintana Roo',
            self::SAN_LUIS_POTOSI => 'San Luis Potosí',
            self::SINALOA => 'Sinaloa',
            self::SONORA => 'Sonora',
            self::TABASCO => 'Tabasco',
            self::TAMAULIPAS => 'Tamaulipas',
            self::TLAXCALA => 'Tlaxcala',
            self::VERACRUZ => 'Veracruz',
            self::YUCATAN => 'Yucatán',
            self::ZACATECAS => 'Zacatecas',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Clave INEGI de 2 dígitos (01–32) de la entidad federativa.
     *
     * Es la nomenclatura que usa el catálogo SEPOMEX en la columna
     * `estado_clave` (campo `c_estado` del archivo oficial), y NO coincide con
     * el código de 3-5 letras del enum (p. ej. `CDMX` ↔ `09`, `JAL` ↔ `14`).
     * El orden numérico sigue el nombre oficial del estado (Chiapas `07` va
     * antes que Chihuahua `08`, etc.), como en el estándar INEGI.
     */
    public function inegiCode(): string
    {
        return match ($this) {
            self::AGUASCALIENTES => '01',
            self::BAJA_CALIFORNIA => '02',
            self::BAJA_CALIFORNIA_SUR => '03',
            self::CAMPECHE => '04',
            self::COAHUILA => '05',
            self::COLIMA => '06',
            self::CHIAPAS => '07',
            self::CHIHUAHUA => '08',
            self::CIUDAD_DE_MEXICO => '09',
            self::DURANGO => '10',
            self::GUANAJUATO => '11',
            self::GUERRERO => '12',
            self::HIDALGO => '13',
            self::JALISCO => '14',
            self::ESTADO_DE_MEXICO => '15',
            self::MICHOACAN => '16',
            self::MORELOS => '17',
            self::NAYARIT => '18',
            self::NUEVO_LEON => '19',
            self::OAXACA => '20',
            self::PUEBLA => '21',
            self::QUERETARO => '22',
            self::QUINTANA_ROO => '23',
            self::SAN_LUIS_POTOSI => '24',
            self::SINALOA => '25',
            self::SONORA => '26',
            self::TABASCO => '27',
            self::TAMAULIPAS => '28',
            self::TLAXCALA => '29',
            self::VERACRUZ => '30',
            self::YUCATAN => '31',
            self::ZACATECAS => '32',
        };
    }

    /**
     * Deriva la entidad federativa desde la CURP.
     *
     * Las posiciones 12-13 de la CURP son la clave de entidad de nacimiento
     * (estándar RENAPO, 2 letras). Devuelve null si es "NE" (nacido en el
     * extranjero) o si la clave no se reconoce.
     */
    public static function fromCurp(?string $curp): ?self
    {
        $curp = strtoupper(trim((string) $curp));
        if (strlen($curp) < 13) {
            return null;
        }

        return match (substr($curp, 11, 2)) {
            'AS' => self::AGUASCALIENTES,
            'BC' => self::BAJA_CALIFORNIA,
            'BS' => self::BAJA_CALIFORNIA_SUR,
            'CC' => self::CAMPECHE,
            'CS' => self::CHIAPAS,
            'CH' => self::CHIHUAHUA,
            'CL' => self::COAHUILA,
            'CM' => self::COLIMA,
            'DF' => self::CIUDAD_DE_MEXICO,
            'DG' => self::DURANGO,
            'GT' => self::GUANAJUATO,
            'GR' => self::GUERRERO,
            'HG' => self::HIDALGO,
            'JC' => self::JALISCO,
            'MC' => self::ESTADO_DE_MEXICO,
            'MN' => self::MICHOACAN,
            'MS' => self::MORELOS,
            'NT' => self::NAYARIT,
            'NL' => self::NUEVO_LEON,
            'OC' => self::OAXACA,
            'PL' => self::PUEBLA,
            'QT' => self::QUERETARO,
            'QR' => self::QUINTANA_ROO,
            'SP' => self::SAN_LUIS_POTOSI,
            'SL' => self::SINALOA,
            'SR' => self::SONORA,
            'TC' => self::TABASCO,
            'TS' => self::TAMAULIPAS,
            'TL' => self::TLAXCALA,
            'VZ' => self::VERACRUZ,
            'YN' => self::YUCATAN,
            'ZS' => self::ZACATECAS,
            default => null,
        };
    }
}
