<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCloudflareRealIp
{
    /**
     * Official Cloudflare IPv4 & IPv6 edge ranges plus local reverse proxies.
     * CF-Connecting-IP is ONLY trusted when REMOTE_ADDR belongs to one of these CIDRs.
     */
    private const TRUSTED_CIDRS = [
        // Local reverse proxies (Nginx/HAProxy)
        '127.0.0.1/32',
        '::1/128',
        // Cloudflare IPv4 (https://www.cloudflare.com/ips-v4)
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        // Cloudflare IPv6 (https://www.cloudflare.com/ips-v6)
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $remoteAddr = (string) $request->server->get('REMOTE_ADDR', '');
        $cfIp = trim((string) $request->headers->get('CF-Connecting-IP', ''));

        if ($cfIp !== '' && $remoteAddr !== '' && self::isTrustedProxy($remoteAddr)) {
            if (filter_var($cfIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
                || filter_var($cfIp, FILTER_VALIDATE_IP) !== false) {
                $request->server->set('REMOTE_ADDR', $cfIp);
            }
        }

        return $next($request);
    }

    public static function isTrustedProxy(string $ip): bool
    {
        $packedIp = @inet_pton($ip);
        if ($packedIp === false) {
            return false;
        }

        foreach (self::TRUSTED_CIDRS as $cidr) {
            if (self::ipInCidr($packedIp, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private static function ipInCidr(string $packedIp, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);
        $packedSubnet = @inet_pton($subnet);
        if ($packedSubnet === false || strlen($packedIp) !== strlen($packedSubnet)) {
            return false;
        }

        $prefixLen = (int) $bits;
        $fullBytes = intdiv($prefixLen, 8);
        $remBits = $prefixLen % 8;

        if ($fullBytes > 0 && substr($packedIp, 0, $fullBytes) !== substr($packedSubnet, 0, $fullBytes)) {
            return false;
        }

        if ($remBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remBits)) & 0xFF;
        return (ord($packedIp[$fullBytes]) & $mask) === (ord($packedSubnet[$fullBytes]) & $mask);
    }
}
