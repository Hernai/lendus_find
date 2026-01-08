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
     */
    public function capture(Request $request): array
    {
        $userAgent = $request->userAgent() ?? '';
        $this->agent->setUserAgent($userAgent);

        return [
            'tenant_id' => $request->attributes->get('tenant')?->id,
            'ip_address' => $this->getRealIp($request),
            'user_agent' => $userAgent,
            'device_info' => $this->parseUserAgent($userAgent),
            'geolocation' => $this->getGeolocation($this->getRealIp($request)),
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
     * Get device info as flat array for database storage.
     */
    public function getDeviceInfoFlat(string $userAgent): array
    {
        $info = $this->parseUserAgent($userAgent);

        return [
            'device_type' => $info['device_type'],
            'browser' => $info['browser'],
            'browser_version' => $info['browser_version'],
            'os' => $info['os'],
            'os_version' => $info['os_version'],
        ];
    }

    /**
     * Get geolocation as flat array for database storage.
     */
    public function getGeolocationFlat(string $ip): array
    {
        $geo = $this->getGeolocation($ip);

        if (!$geo) {
            return [
                'latitude' => null,
                'longitude' => null,
                'city' => null,
                'region' => null,
                'country' => null,
            ];
        }

        return [
            'latitude' => $geo['latitude'],
            'longitude' => $geo['longitude'],
            'city' => $geo['city'],
            'region' => $geo['region'],
            'country' => $geo['country'],
        ];
    }

    /**
     * Capture and flatten metadata for direct database insert.
     */
    public function captureFlat(Request $request): array
    {
        $ip = $this->getRealIp($request);
        $userAgent = $request->userAgent() ?? '';

        $deviceInfo = $this->getDeviceInfoFlat($userAgent);
        $geolocation = $this->getGeolocationFlat($ip);

        return array_merge(
            [
                'tenant_id' => $request->attributes->get('tenant')?->id,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ],
            $deviceInfo,
            $geolocation
        );
    }
}
