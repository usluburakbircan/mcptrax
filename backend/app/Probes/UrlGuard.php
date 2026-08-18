<?php

namespace App\Probes;

/**
 * Prob hedefi URL'lerinin SSRF koruması: herkese açık checker ve kullanıcı
 * monitörleri keyfi adresleri probladığı için loopback/özel/link-local
 * aralıklara ve http(s) dışı şemalara izin verilmez.
 */
class UrlGuard
{
    public static function isSafe(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower(trim($parts['host'], '[]'));

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host);
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        if ($records === []) {
            // Çözülemeyen host'u probun kendisi zaten "connection failed"
            // olarak raporlar; burada engellemek yanlış negatif üretir.
            return true;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if ($ip !== null && ! self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    protected static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
