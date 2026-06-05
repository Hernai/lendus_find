<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Jenssegers\Agent\Agent;

class MetadataService
{
    protected Agent $agent;

    public function __construct()
    {
        $this->agent = new Agent();
    }

    /**
     * Capture all metadata from a request.
     *
     * El IP lookup externo (~200-500ms a ip-api.com) NO se hace dentro del
     * camino crítico del request. Si el cliente mandó `X-Geo-Lat/Lng` (caso
     * normal en web/móvil con permiso de geolocalización), se usa eso sin
     * tocar la red. Si no, devolvemos `geolocation = null` y el lookup por
     * IP queda diferido para que lo dispare un job en background (ver
     * `resolveIpGeolocation()` y el middleware `LogClientRequest` que llama
     * `terminating()`).
     */
    public function capture(Request $request): array
    {
        $userAgent = $request->userAgent() ?? '';
        $this->agent->setUserAgent($userAgent);

        $clientGeo = $this->parseClientGeo($request);

        return [
            'tenant_id' => $request->attributes->get('tenant')?->id,
            'ip_address' => $this->getRealIp($request),
            'user_agent' => $userAgent,
            'device_info' => $this->parseUserAgent($userAgent),
            // Si vino del dispositivo (más preciso), se usa directo. Sino se
            // deja null: el IP lookup lo hace el job post-response.
            'geolocation' => $clientGeo,
            // Headers enviados por clientes móviles/PWA (X-Platform=web|ios|android).
            'platform' => $request->header('X-Platform'),
            'app_version' => $request->header('X-App-Version'),
            'device_id' => $request->header('X-Device-Id'),
        ];
    }

    /**
     * Resolver geolocalización por IP (lookup externo bloqueante).
     *
     * Solo debe llamarse fuera del camino crítico del request (job, cron,
     * `terminating()` callback). NO llamar desde un middleware HTTP que
     * preceda al response.
     */
    public function resolveIpGeolocation(string $ip): ?array
    {
        return $this->getGeolocation($ip);
    }

    /**
     * Lee `X-Geo-Lat`, `X-Geo-Lng`, `X-Geo-Accuracy`, `X-Geo-Timestamp`
     * enviados por el cliente móvil/PWA. Devuelve null si no hay valores
     * válidos.
     *
     * @return array{latitude:float,longitude:float,accuracy:?int,source:string}|null
     */
    public function parseClientGeo(Request $request): ?array
    {
        $lat = $request->header('X-Geo-Lat');
        $lng = $request->header('X-Geo-Lng');
        if ($lat === null || $lng === null) return null;

        $latF = filter_var($lat, FILTER_VALIDATE_FLOAT);
        $lngF = filter_var($lng, FILTER_VALIDATE_FLOAT);
        if ($latF === false || $lngF === false) return null;
        if ($latF < -90 || $latF > 90 || $lngF < -180 || $lngF > 180) return null;

        $accuracy = filter_var($request->header('X-Geo-Accuracy'), FILTER_VALIDATE_INT);

        return [
            'latitude' => $latF,
            'longitude' => $lngF,
            'accuracy' => $accuracy !== false ? $accuracy : null,
            'source' => 'device',
        ];
    }

    /**
     * Get real client IP, accounting for proxies and load balancers.
     */
    public function getRealIp(Request $request): string
    {
        // Check common proxy headers
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',        // Nginx proxy
            'HTTP_X_FORWARDED_FOR',  // Standard proxy header
        ];

        foreach ($headers as $header) {
            $ip = $request->server($header);
            if ($ip) {
                // X-Forwarded-For can contain multiple IPs
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);

                // Validate IP format
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $request->ip() ?? '0.0.0.0';
    }

    /**
     * Parse user agent to extract device information.
     */
    public function parseUserAgent(string $userAgent): array
    {
        $this->agent->setUserAgent($userAgent);

        // Determine device type
        $deviceType = 'desktop';
        if ($this->agent->isTablet()) {
            $deviceType = 'tablet';
        } elseif ($this->agent->isMobile()) {
            $deviceType = 'mobile';
        } elseif ($this->agent->isRobot()) {
            $deviceType = 'bot';
        }

        // Get browser info
        $browser = $this->agent->browser();
        $browserVersion = $this->agent->version($browser);

        // Get OS info
        $platform = $this->agent->platform();
        $platformVersion = $this->agent->version($platform);

        // Get device name for mobile/tablet
        $device = $this->agent->device();

        return [
            'device_type' => $deviceType,
            'device' => $device ?: null,
            'browser' => $browser ?: null,
            'browser_version' => $browserVersion ?: null,
            'os' => $platform ?: null,
            'os_version' => $platformVersion ?: null,
            'is_robot' => $this->agent->isRobot(),
            'robot_name' => $this->agent->robot() ?: null,
        ];
    }

    /**
     * Get geolocation data from IP address.
     * Uses ip-api.com free service (45 req/min limit).
     * Results are cached for 24 hours to reduce API calls.
     */
    public function getGeolocation(string $ip): ?array
    {
        // Skip for local/private IPs
        if ($this->isPrivateIp($ip)) {
            return null;
        }

        // Check cache first
        $cacheKey = "geolocation:{$ip}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached === 'null' ? null : $cached;
        }

        try {
            // Use ip-api.com free service
            // Endpoint: http://ip-api.com/json/{ip}
            // Note: Use http in production only for non-SSL fallback
            // For production, consider MaxMind or a paid service
            $response = Http::timeout(5)
                ->retry(2, 100)
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,message,country,countryCode,region,regionName,city,lat,lon,isp,org',
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if (($data['status'] ?? '') === 'success') {
                    $result = [
                        'latitude' => $data['lat'] ?? null,
                        'longitude' => $data['lon'] ?? null,
                        'city' => $data['city'] ?? null,
                        'region' => $data['regionName'] ?? null,
                        'country' => $data['countryCode'] ?? null,
                        'country_name' => $data['country'] ?? null,
                        'isp' => $data['isp'] ?? null,
                        'org' => $data['org'] ?? null,
                    ];

                    // Cache for 24 hours
                    Cache::put($cacheKey, $result, 86400);

                    return $result;
                }
            }

            // Cache null result to avoid repeated failed requests
            Cache::put($cacheKey, 'null', 3600); // 1 hour for failed lookups

        } catch (\Exception $e) {
            Log::warning('Geolocation lookup failed', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            // Cache null for failed requests
            Cache::put($cacheKey, 'null', 3600);
        }

        return null;
    }

    /**
     * Check if IP is private/local.
     */
    protected function isPrivateIp(string $ip): bool
    {
        // IPv4 private ranges
        $privateRanges = [
            '10.',          // 10.0.0.0 - 10.255.255.255
            '172.16.',      // 172.16.0.0 - 172.31.255.255
            '172.17.',
            '172.18.',
            '172.19.',
            '172.20.',
            '172.21.',
            '172.22.',
            '172.23.',
            '172.24.',
            '172.25.',
            '172.26.',
            '172.27.',
            '172.28.',
            '172.29.',
            '172.30.',
            '172.31.',
            '192.168.',     // 192.168.0.0 - 192.168.255.255
            '127.',         // Localhost
            '0.',           // Invalid
        ];

        foreach ($privateRanges as $range) {
            if (str_starts_with($ip, $range)) {
                return true;
            }
        }

        // IPv6 localhost
        if ($ip === '::1') {
            return true;
        }

        return false;
    }

    /**
     * Aplana el array devuelto por `capture()` para INSERT directo a tabla
     * (audit_logs). Elimina los wrappers `device_info` y `geolocation`,
     * dejando todas las claves al mismo nivel.
     *
     * Antes existía un `captureFlat()` separado que duplicaba la lógica de
     * `capture()` — eso causó un bug donde mi primer fix solo arregló
     * `capture()` y `captureFlat()` siguió haciendo IP lookup. Unificado.
     *
     * @param array $captured Resultado de `capture($request)`
     */
    public function flatten(array $captured): array
    {
        $deviceInfo = $captured['device_info'] ?? [];
        $geolocation = $captured['geolocation'] ?? [];

        return [
            'tenant_id' => $captured['tenant_id'] ?? null,
            'ip_address' => $captured['ip_address'] ?? null,
            'user_agent' => $captured['user_agent'] ?? null,
            'device_type' => $deviceInfo['device_type'] ?? null,
            'browser' => $deviceInfo['browser'] ?? null,
            'browser_version' => $deviceInfo['browser_version'] ?? null,
            'os' => $deviceInfo['os'] ?? null,
            'os_version' => $deviceInfo['os_version'] ?? null,
            'latitude' => $geolocation['latitude'] ?? null,
            'longitude' => $geolocation['longitude'] ?? null,
            'city' => $geolocation['city'] ?? null,
            'region' => $geolocation['region'] ?? null,
            'country' => $geolocation['country'] ?? null,
        ];
    }
}
