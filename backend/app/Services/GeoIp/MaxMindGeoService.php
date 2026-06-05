<?php

namespace App\Services\GeoIp;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lookup de geolocalizacion via base de datos local MaxMind GeoLite2-City.
 *
 * Reemplaza el lookup externo a ip-api.com que estaba en MetadataService.
 * Beneficios:
 *  - Sin rate limit (DB local).
 *  - Sin latencia de red (lookup en microsegundos).
 *  - Sin dependencia de API externa para responder requests.
 *  - Compatible con audit batch nocturno (millones de IPs sin problema).
 *
 * Tradeoff: la DB se actualiza ~semanal (martes/jueves). El command
 * `audit-logs:update-geoip-db` la baja del CDN de MaxMind cada miercoles
 * 03:00 (schedule en `routes/console.php`).
 *
 * El archivo .mmdb queda en storage/app/geoip/GeoLite2-City.mmdb
 * (~70MB descomprimido). Se versiona FUERA de git (.gitignore).
 */
class MaxMindGeoService
{
    private const DB_FILENAME = 'GeoLite2-City.mmdb';

    private ?Reader $reader = null;

    public function dbPath(): string
    {
        return storage_path('app/geoip/' . self::DB_FILENAME);
    }

    public function isAvailable(): bool
    {
        return is_readable($this->dbPath());
    }

    /**
     * Lazy-init del Reader. Si la DB no existe, retorna null y loguea
     * solo la primera vez por instancia.
     */
    private function reader(): ?Reader
    {
        if ($this->reader !== null) {
            return $this->reader;
        }

        if (! $this->isAvailable()) {
            Log::warning('MaxMind GeoLite2 DB no disponible', [
                'expected_path' => $this->dbPath(),
                'hint' => 'Correr: php artisan audit-logs:update-geoip-db',
            ]);
            return null;
        }

        try {
            $this->reader = new Reader($this->dbPath());
        } catch (Throwable $e) {
            Log::error('MaxMind Reader fallo al abrir DB', [
                'path' => $this->dbPath(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        return $this->reader;
    }

    /**
     * Resuelve la geolocalizacion de una IP.
     * Retorna array con keys: latitude, longitude, city, region, country, country_name
     * o null si la IP no se encuentra o es privada.
     */
    public function lookup(string $ip): ?array
    {
        if ($this->isPrivateIp($ip)) {
            return null;
        }

        // Cache por IP — incluso lookups locales acumulan CPU si se repiten
        // mucho dentro del mismo batch. 24h es generoso para algo determinista.
        return Cache::remember("geoip:mm:{$ip}", 86400, function () use ($ip) {
            $reader = $this->reader();
            if (! $reader) {
                return null;
            }

            try {
                $record = $reader->city($ip);

                return [
                    'latitude' => $record->location->latitude,
                    'longitude' => $record->location->longitude,
                    'city' => $record->city->name,
                    'region' => $record->mostSpecificSubdivision->name,
                    'country' => $record->country->isoCode,
                    'country_name' => $record->country->name,
                    'accuracy_radius_km' => $record->location->accuracyRadius,
                ];
            } catch (AddressNotFoundException) {
                // IP no esta en la DB (rangos reservados, ASN nuevo, etc.)
                return null;
            } catch (Throwable $e) {
                Log::debug('MaxMind lookup excepcion', [
                    'ip' => $ip,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    /**
     * Comprueba si la IP es privada/local (no tiene sentido buscarla en MaxMind).
     */
    private function isPrivateIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        // PHP nativo cubre todos los rangos privados y reservados.
        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Cierra el reader (util en tests o batch largos).
     */
    public function close(): void
    {
        if ($this->reader !== null) {
            try {
                $this->reader->close();
            } catch (Throwable) {
            }
            $this->reader = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
