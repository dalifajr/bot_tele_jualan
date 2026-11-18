<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeoIpService
{
    /**
     * Kamus koordinat fallback untuk kota-kota besar Indonesia & dunia (mendukung data historis login_logs).
     */
    protected const KNOWN_CITIES = [
        'jakarta' => ['city' => 'Jakarta', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -6.2088, 'lng' => 106.8456],
        'bandung' => ['city' => 'Bandung', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -6.9175, 'lng' => 107.6191],
        'surabaya' => ['city' => 'Surabaya', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -7.2575, 'lng' => 112.7521],
        'medan' => ['city' => 'Medan', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => 3.5952, 'lng' => 98.6722],
        'semarang' => ['city' => 'Semarang', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -6.9667, 'lng' => 110.4167],
        'yogyakarta' => ['city' => 'Yogyakarta', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -7.7956, 'lng' => 110.3695],
        'jogja' => ['city' => 'Yogyakarta', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -7.7956, 'lng' => 110.3695],
        'makassar' => ['city' => 'Makassar', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -5.1477, 'lng' => 119.4327],
        'palembang' => ['city' => 'Palembang', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -2.9761, 'lng' => 104.7754],
        'tangerang' => ['city' => 'Tangerang', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -6.1783, 'lng' => 106.6319],
        'bekasi' => ['city' => 'Bekasi', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -6.2383, 'lng' => 106.9756],
        'depok' => ['city' => 'Depok', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -6.4025, 'lng' => 106.7942],
        'bogor' => ['city' => 'Bogor', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -6.5971, 'lng' => 106.8060],
        'denpasar' => ['city' => 'Denpasar', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -8.6705, 'lng' => 115.2126],
        'bali' => ['city' => 'Denpasar', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -8.4095, 'lng' => 115.1889],
        'malang' => ['city' => 'Malang', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -7.9666, 'lng' => 112.6326],
        'solo' => ['city' => 'Surakarta', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -7.5755, 'lng' => 110.8243],
        'surakarta' => ['city' => 'Surakarta', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -7.5755, 'lng' => 110.8243],
        'batam' => ['city' => 'Batam', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => 1.0456, 'lng' => 104.0305],
        'pekanbaru' => ['city' => 'Pekanbaru', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => 0.5071, 'lng' => 101.4478],
        'padang' => ['city' => 'Padang', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -0.9471, 'lng' => 100.4172],
        'bandar lampung' => ['city' => 'Bandar Lampung', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -5.4292, 'lng' => 105.2610],
        'lampung' => ['city' => 'Bandar Lampung', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -5.4292, 'lng' => 105.2610],
        'banjarmasin' => ['city' => 'Banjarmasin', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -3.3194, 'lng' => 114.5908],
        'balikpapan' => ['city' => 'Balikpapan', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -1.2379, 'lng' => 116.8529],
        'samarinda' => ['city' => 'Samarinda', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -0.5022, 'lng' => 117.1536],
        'pontianak' => ['city' => 'Pontianak', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -0.0263, 'lng' => 109.3425],
        'manado' => ['city' => 'Manado', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => 1.4748, 'lng' => 124.8421],
        'jayapura' => ['city' => 'Jayapura', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -2.5916, 'lng' => 140.6690],
        'mataram' => ['city' => 'Mataram', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -8.5833, 'lng' => 116.1167],
        'jambi' => ['city' => 'Jambi', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -1.6101, 'lng' => 103.6131],
        'bengkulu' => ['city' => 'Bengkulu', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -3.8004, 'lng' => 102.2655],
        'aceh' => ['city' => 'Banda Aceh', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => 5.5483, 'lng' => 95.3238],
        'banda aceh' => ['city' => 'Banda Aceh', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => 5.5483, 'lng' => 95.3238],
        'cirebon' => ['city' => 'Cirebon', 'country' => 'Indonesia', 'country_code' => 'ID', 'lat' => -6.7320, 'lng' => 108.5523],
        'singapore' => ['city' => 'Singapore', 'country' => 'Singapore', 'country_code' => 'SG', 'lat' => 1.3521, 'lng' => 103.8198],
        'kuala lumpur' => ['city' => 'Kuala Lumpur', 'country' => 'Malaysia', 'country_code' => 'MY', 'lat' => 3.1390, 'lng' => 101.6869],
        'bangkok' => ['city' => 'Bangkok', 'country' => 'Thailand', 'country_code' => 'TH', 'lat' => 13.7563, 'lng' => 100.5018],
        'manila' => ['city' => 'Manila', 'country' => 'Philippines', 'country_code' => 'PH', 'lat' => 14.5995, 'lng' => 120.9842],
        'ho chi minh' => ['city' => 'Ho Chi Minh City', 'country' => 'Vietnam', 'country_code' => 'VN', 'lat' => 10.8231, 'lng' => 106.6297],
        'hanoi' => ['city' => 'Hanoi', 'country' => 'Vietnam', 'country_code' => 'VN', 'lat' => 21.0285, 'lng' => 105.8542],
        'tokyo' => ['city' => 'Tokyo', 'country' => 'Japan', 'country_code' => 'JP', 'lat' => 35.6762, 'lng' => 139.6503],
        'seoul' => ['city' => 'Seoul', 'country' => 'South Korea', 'country_code' => 'KR', 'lat' => 37.5665, 'lng' => 126.9780],
        'hong kong' => ['city' => 'Hong Kong', 'country' => 'Hong Kong', 'country_code' => 'HK', 'lat' => 22.3193, 'lng' => 114.1694],
        'taipei' => ['city' => 'Taipei', 'country' => 'Taiwan', 'country_code' => 'TW', 'lat' => 25.0330, 'lng' => 121.5654],
        'sydney' => ['city' => 'Sydney', 'country' => 'Australia', 'country_code' => 'AU', 'lat' => -33.8688, 'lng' => 151.2093],
        'london' => ['city' => 'London', 'country' => 'United Kingdom', 'country_code' => 'GB', 'lat' => 51.5072, 'lng' => -0.1276],
        'amsterdam' => ['city' => 'Amsterdam', 'country' => 'Netherlands', 'country_code' => 'NL', 'lat' => 52.3676, 'lng' => 4.9041],
        'frankfurt' => ['city' => 'Frankfurt', 'country' => 'Germany', 'country_code' => 'DE', 'lat' => 50.1109, 'lng' => 8.6821],
        'paris' => ['city' => 'Paris', 'country' => 'France', 'country_code' => 'FR', 'lat' => 48.8566, 'lng' => 2.3522],
        'new york' => ['city' => 'New York', 'country' => 'United States', 'country_code' => 'US', 'lat' => 40.7128, 'lng' => -74.0060],
        'los angeles' => ['city' => 'Los Angeles', 'country' => 'United States', 'country_code' => 'US', 'lat' => 34.0522, 'lng' => -118.2437],
        'san francisco' => ['city' => 'San Francisco', 'country' => 'United States', 'country_code' => 'US', 'lat' => 37.7749, 'lng' => -122.4194],
        'ashburn' => ['city' => 'Ashburn', 'country' => 'United States', 'country_code' => 'US', 'lat' => 39.0438, 'lng' => -77.4874],
    ];

    protected const KNOWN_COUNTRIES = [
        'ID' => ['country' => 'Indonesia', 'lat' => -2.5489, 'lng' => 118.0149],
        'SG' => ['country' => 'Singapore', 'lat' => 1.3521, 'lng' => 103.8198],
        'MY' => ['country' => 'Malaysia', 'lat' => 4.2105, 'lng' => 101.9758],
        'TH' => ['country' => 'Thailand', 'lat' => 15.8700, 'lng' => 100.9925],
        'PH' => ['country' => 'Philippines', 'lat' => 12.8797, 'lng' => 121.7740],
        'VN' => ['country' => 'Vietnam', 'lat' => 14.0583, 'lng' => 108.2772],
        'US' => ['country' => 'United States', 'lat' => 37.0902, 'lng' => -95.7129],
        'GB' => ['country' => 'United Kingdom', 'lat' => 55.3781, 'lng' => -3.4360],
        'DE' => ['country' => 'Germany', 'lat' => 51.1657, 'lng' => 10.4515],
        'NL' => ['country' => 'Netherlands', 'lat' => 52.1326, 'lng' => 5.2913],
        'FR' => ['country' => 'France', 'lat' => 46.2276, 'lng' => 2.2137],
        'JP' => ['country' => 'Japan', 'lat' => 36.2048, 'lng' => 138.2529],
        'KR' => ['country' => 'South Korea', 'lat' => 35.9078, 'lng' => 127.7669],
        'AU' => ['country' => 'Australia', 'lat' => -25.2744, 'lng' => 133.7751],
        'IN' => ['country' => 'India', 'lat' => 20.5937, 'lng' => 78.9629],
        'CN' => ['country' => 'China', 'lat' => 35.8617, 'lng' => 104.1954],
        'HK' => ['country' => 'Hong Kong', 'lat' => 22.3193, 'lng' => 114.1694],
        'TW' => ['country' => 'Taiwan', 'lat' => 23.6978, 'lng' => 120.9605],
        'RU' => ['country' => 'Russia', 'lat' => 61.5240, 'lng' => 105.3188],
        'BR' => ['country' => 'Brazil', 'lat' => -14.2350, 'lng' => -51.9253],
        'CA' => ['country' => 'Canada', 'lat' => 56.1304, 'lng' => -106.3468],
    ];

    public static function isPublicIp(?string $ip): bool
    {
        if (empty($ip)) {
            return false;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /**
     * Resolve geolokasi IP dengan caching 7 hari dan guard aman untuk IP privat / unit testing.
     */
    public static function resolve(?string $ip, ?string $cfCountryCode = null): array
    {
        $default = [
            'location' => 'Local / Unknown',
            'country' => null,
            'country_code' => null,
            'region' => null,
            'city' => null,
            'latitude' => null,
            'longitude' => null,
        ];

        if (!self::isPublicIp($ip)) {
            return $default;
        }

        $cacheKey = 'geoip:v1:' . md5((string) $ip);

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($ip, $cfCountryCode, $default) {
            if (app()->runningUnitTests() && !config('services.geoip.allow_in_tests', false)) {
                return self::fallbackFromCountryCode($cfCountryCode) ?? $default;
            }

            try {
                $response = Http::timeout(1.5)->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,country,countryCode,regionName,city,lat,lon',
                ]);

                if ($response->successful() && $response->json('status') === 'success') {
                    $city = trim((string) ($response->json('city') ?? ''));
                    $country = trim((string) ($response->json('country') ?? ''));
                    $countryCode = strtoupper(trim((string) ($response->json('countryCode') ?? '')));
                    $region = trim((string) ($response->json('regionName') ?? ''));
                    $lat = $response->json('lat');
                    $lon = $response->json('lon');

                    $label = trim(implode(', ', array_filter([$city, $country])));

                    return [
                        'location' => $label !== '' ? $label : 'Unknown',
                        'country' => $country !== '' ? $country : null,
                        'country_code' => $countryCode !== '' ? $countryCode : null,
                        'region' => $region !== '' ? $region : null,
                        'city' => $city !== '' ? $city : null,
                        'latitude' => is_numeric($lat) ? round((float) $lat, 7) : null,
                        'longitude' => is_numeric($lon) ? round((float) $lon, 7) : null,
                    ];
                }
            } catch (\Throwable $e) {
                // Silent fail to keep login/page load fast
            }

            return self::fallbackFromCountryCode($cfCountryCode) ?? $default;
        });
    }

    /**
     * Cocokkan string lokasi historis (mis. "Jakarta, ID", "Singapore, SG") ke koordinat fallback.
     */
    public static function resolveFromLocationText(?string $locationText): ?array
    {
        if (empty($locationText)) {
            return null;
        }

        $normalized = strtolower(trim($locationText));
        if ($normalized === '' || str_contains($normalized, 'local') || str_contains($normalized, 'unknown')) {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $normalized))));

        // 1. Cek kecocokan nama kota
        foreach ($parts as $part) {
            if (isset(self::KNOWN_CITIES[$part])) {
                $c = self::KNOWN_CITIES[$part];
                return [
                    'city' => $c['city'],
                    'country' => $c['country'],
                    'country_code' => $c['country_code'],
                    'latitude' => $c['lat'],
                    'longitude' => $c['lng'],
                ];
            }
        }

        foreach (self::KNOWN_CITIES as $cityKey => $c) {
            if (str_contains($normalized, $cityKey)) {
                return [
                    'city' => $c['city'],
                    'country' => $c['country'],
                    'country_code' => $c['country_code'],
                    'latitude' => $c['lat'],
                    'longitude' => $c['lng'],
                ];
            }
        }

        // 2. Cek kecocokan kode negara (ID, SG, US, ...) atau nama negara
        foreach (array_reverse($parts) as $part) {
            $upper = strtoupper($part);
            if (isset(self::KNOWN_COUNTRIES[$upper])) {
                $co = self::KNOWN_COUNTRIES[$upper];
                return [
                    'city' => count($parts) > 1 ? ucwords($parts[0]) : $co['country'],
                    'country' => $co['country'],
                    'country_code' => $upper,
                    'latitude' => $co['lat'],
                    'longitude' => $co['lng'],
                ];
            }
            foreach (self::KNOWN_COUNTRIES as $code => $co) {
                if (strtolower($co['country']) === $part) {
                    return [
                        'city' => count($parts) > 1 ? ucwords($parts[0]) : $co['country'],
                        'country' => $co['country'],
                        'country_code' => $code,
                        'latitude' => $co['lat'],
                        'longitude' => $co['lng'],
                    ];
                }
            }
        }

        return null;
    }

    protected static function fallbackFromCountryCode(?string $code): ?array
    {
        if (empty($code)) {
            return null;
        }
        $upper = strtoupper(trim($code));
        if (!isset(self::KNOWN_COUNTRIES[$upper])) {
            return null;
        }
        $co = self::KNOWN_COUNTRIES[$upper];
        return [
            'location' => $co['country'],
            'country' => $co['country'],
            'country_code' => $upper,
            'region' => null,
            'city' => null,
            'latitude' => $co['lat'],
            'longitude' => $co['lng'],
        ];
    }
}
