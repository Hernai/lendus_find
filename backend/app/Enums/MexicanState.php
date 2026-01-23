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
}
