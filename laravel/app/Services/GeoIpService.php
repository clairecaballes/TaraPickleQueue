<?php

namespace App\Services;

use App\Models\GeoLookup;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * IP -> country / region / city resolution for visitor analytics.
 *
 * Resolution strategy (never blocks a request on the network):
 *  1. Private / loopback addresses are tagged "Local".
 *  2. Cached results (GeoLookup table) are reused for 24h (1h for misses).
 *  3. Otherwise a free lookup API is called with a short timeout.
 *  4. Any failure degrades gracefully to "Unknown" so tracking keeps working
 *     offline — the dashboard just shows Unknown as a region.
 */
class GeoIpService
{
    /** How long a successful lookup is reused. */
    private const CACHE_TTL_HOURS = 24;

    /** Misses (Unknown) are cached much shorter so a later retry can succeed. */
    private const MISS_TTL_HOURS = 1;

    private const API_TIMEOUT_SECONDS = 3;

    /**
     * @return array{country: string, region: string, city: string}
     */
    public function resolve(?string $ip): array
    {
        $ip = trim((string) $ip);

        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return self::unknown();
        }

        // Normalize IPv4-mapped IPv6 (::ffff:1.2.3.4) before any matching.
        if (str_starts_with($ip, '::ffff:')) {
            $ip = substr($ip, 7);
        }

        if ($this->isLocal($ip)) {
            return ['country' => 'Local', 'region' => '', 'city' => ''];
        }

        $cached = GeoLookup::where('ip', $ip)->first();

        if ($cached) {
            $ttlHours = $cached->country === 'Unknown' ? self::MISS_TTL_HOURS : self::CACHE_TTL_HOURS;

            if ($cached->resolved_at->gt(now()->subHours($ttlHours))) {
                return [
                    'country' => (string) $cached->country,
                    'region' => (string) $cached->region,
                    'city' => (string) $cached->city,
                ];
            }
        }

        $result = $this->lookupRemote($ip);

        GeoLookup::updateOrCreate(['ip' => $ip], [
            'country' => $result['country'],
            'region' => $result['region'],
            'city' => $result['city'],
            'resolved_at' => now(),
        ]);

        return $result;
    }

    /**
     * @return array{country: string, region: string, city: string}
     */
    private function lookupRemote(string $ip): array
    {
        try {
            $response = Http::timeout(self::API_TIMEOUT_SECONDS)
                ->get("http://ip-api.com/json/{$ip}", ['fields' => 'status,country,regionName,city']);

            $data = $response->json();

            if ($response->successful() && ($data['status'] ?? '') === 'success') {
                return [
                    'country' => (string) ($data['country'] ?? 'Unknown') ?: 'Unknown',
                    'region' => (string) ($data['regionName'] ?? ''),
                    'city' => (string) ($data['city'] ?? ''),
                ];
            }
        } catch (Throwable) {
            // Offline / DNS failure / blocked — fall through to Unknown.
        }

        return self::unknown();
    }

    /** Loopback, link-local and RFC1918 private ranges are "Local". */
    private function isLocal(string $ip): bool
    {
        return in_array($ip, ['127.0.0.1', '::1'], true)
            || str_starts_with($ip, '192.168.')
            || str_starts_with($ip, '10.')
            || str_starts_with($ip, '169.254.')
            || preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip) === 1;
    }

    /**
     * @return array{country: string, region: string, city: string}
     */
    private static function unknown(): array
    {
        return ['country' => 'Unknown', 'region' => '', 'city' => ''];
    }
}
