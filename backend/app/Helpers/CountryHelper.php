<?php

namespace App\Helpers;

class CountryHelper
{
    /**
     * Get country information including name and flag emoji
     *
     * @param string|null $countryCode ISO 3166-1 alpha-2 or alpha-3 country code
     * @return array{code: string, name: string, flag: string}
     */
    public static function getCountryInfo(?string $countryCode): array
    {
        if (! $countryCode) {
            return [
                'code' => 'MX',
                'name' => 'Mexicana',
                'flag' => '🇲🇽',
            ];
        }

        // Normalize to uppercase
        $countryCode = strtoupper($countryCode);

        // Map of country codes to names and flags
        $countries = [
            // North America
            'MX' => ['name' => 'Mexicana', 'flag' => '🇲🇽'],
            'US' => ['name' => 'Estadounidense', 'flag' => '🇺🇸'],
            'CA' => ['name' => 'Canadiense', 'flag' => '🇨🇦'],

            // Central America
            'GT' => ['name' => 'Guatemalteca', 'flag' => '🇬🇹'],
            'BZ' => ['name' => 'Beliceña', 'flag' => '🇧🇿'],
            'HN' => ['name' => 'Hondureña', 'flag' => '🇭🇳'],
            'SV' => ['name' => 'Salvadoreña', 'flag' => '🇸🇻'],
            'NI' => ['name' => 'Nicaragüense', 'flag' => '🇳🇮'],
            'CR' => ['name' => 'Costarricense', 'flag' => '🇨🇷'],
            'PA' => ['name' => 'Panameña', 'flag' => '🇵🇦'],

            // Caribbean
            'CU' => ['name' => 'Cubana', 'flag' => '🇨🇺'],
            'DO' => ['name' => 'Dominicana', 'flag' => '🇩🇴'],
            'HT' => ['name' => 'Haitiana', 'flag' => '🇭🇹'],
            'JM' => ['name' => 'Jamaiquina', 'flag' => '🇯🇲'],
            'PR' => ['name' => 'Puertorriqueña', 'flag' => '🇵🇷'],
            'TT' => ['name' => 'Trinitense', 'flag' => '🇹🇹'],

            // South America
            'CO' => ['name' => 'Colombiana', 'flag' => '🇨🇴'],
            'VE' => ['name' => 'Venezolana', 'flag' => '🇻🇪'],
            'EC' => ['name' => 'Ecuatoriana', 'flag' => '🇪🇨'],
            'PE' => ['name' => 'Peruana', 'flag' => '🇵🇪'],
            'BO' => ['name' => 'Boliviana', 'flag' => '🇧🇴'],
            'CL' => ['name' => 'Chilena', 'flag' => '🇨🇱'],
            'AR' => ['name' => 'Argentina', 'flag' => '🇦🇷'],
            'UY' => ['name' => 'Uruguaya', 'flag' => '🇺🇾'],
            'PY' => ['name' => 'Paraguaya', 'flag' => '🇵🇾'],
            'BR' => ['name' => 'Brasileña', 'flag' => '🇧🇷'],
            'GY' => ['name' => 'Guyanesa', 'flag' => '🇬🇾'],
            'SR' => ['name' => 'Surinamesa', 'flag' => '🇸🇷'],

            // Europe
            'ES' => ['name' => 'Española', 'flag' => '🇪🇸'],
            'FR' => ['name' => 'Francesa', 'flag' => '🇫🇷'],
            'IT' => ['name' => 'Italiana', 'flag' => '🇮🇹'],
            'DE' => ['name' => 'Alemana', 'flag' => '🇩🇪'],
            'GB' => ['name' => 'Británica', 'flag' => '🇬🇧'],
            'PT' => ['name' => 'Portuguesa', 'flag' => '🇵🇹'],
            'NL' => ['name' => 'Neerlandesa', 'flag' => '🇳🇱'],
            'BE' => ['name' => 'Belga', 'flag' => '🇧🇪'],
            'CH' => ['name' => 'Suiza', 'flag' => '🇨🇭'],
            'AT' => ['name' => 'Austriaca', 'flag' => '🇦🇹'],
            'SE' => ['name' => 'Sueca', 'flag' => '🇸🇪'],
            'NO' => ['name' => 'Noruega', 'flag' => '🇳🇴'],
            'DK' => ['name' => 'Danesa', 'flag' => '🇩🇰'],
            'FI' => ['name' => 'Finlandesa', 'flag' => '🇫🇮'],
            'PL' => ['name' => 'Polaca', 'flag' => '🇵🇱'],
            'RU' => ['name' => 'Rusa', 'flag' => '🇷🇺'],
            'UA' => ['name' => 'Ucraniana', 'flag' => '🇺🇦'],

            // Asia
            'CN' => ['name' => 'China', 'flag' => '🇨🇳'],
            'JP' => ['name' => 'Japonesa', 'flag' => '🇯🇵'],
            'KR' => ['name' => 'Surcoreana', 'flag' => '🇰🇷'],
            'IN' => ['name' => 'India', 'flag' => '🇮🇳'],
            'PH' => ['name' => 'Filipina', 'flag' => '🇵🇭'],
            'TH' => ['name' => 'Tailandesa', 'flag' => '🇹🇭'],
            'VN' => ['name' => 'Vietnamita', 'flag' => '🇻🇳'],
            'ID' => ['name' => 'Indonesia', 'flag' => '🇮🇩'],
            'MY' => ['name' => 'Malasia', 'flag' => '🇲🇾'],
            'SG' => ['name' => 'Singapurense', 'flag' => '🇸🇬'],
            'IL' => ['name' => 'Israelí', 'flag' => '🇮🇱'],
            'TR' => ['name' => 'Turca', 'flag' => '🇹🇷'],
            'SA' => ['name' => 'Saudí', 'flag' => '🇸🇦'],
            'AE' => ['name' => 'Emiratí', 'flag' => '🇦🇪'],

            // Africa
            'ZA' => ['name' => 'Sudafricana', 'flag' => '🇿🇦'],
            'EG' => ['name' => 'Egipcia', 'flag' => '🇪🇬'],
            'NG' => ['name' => 'Nigeriana', 'flag' => '🇳🇬'],
            'KE' => ['name' => 'Keniana', 'flag' => '🇰🇪'],
            'MA' => ['name' => 'Marroquí', 'flag' => '🇲🇦'],

            // Oceania
            'AU' => ['name' => 'Australiana', 'flag' => '🇦🇺'],
            'NZ' => ['name' => 'Neozelandesa', 'flag' => '🇳🇿'],
        ];

        $info = $countries[$countryCode] ?? [
            'name' => $countryCode,
            'flag' => '🌍',
        ];

        return [
            'code' => $countryCode,
            'name' => $info['name'],
            'flag' => $info['flag'],
        ];
    }

    /**
     * Check if a country code represents a foreign country (not Mexico)
     *
     * @param string|null $countryCode
     * @return bool
     */
    public static function isForeigner(?string $countryCode): bool
    {
        if (! $countryCode) {
            return false;
        }

        return strtoupper($countryCode) !== 'MX';
    }
}
