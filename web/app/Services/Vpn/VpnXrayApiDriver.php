<?php

namespace App\Services\Vpn;

use App\Models\VpnServer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VpnXrayApiDriver implements VpnProviderInterface
{
    public function __construct(
        protected VpnServer $server
    ) {}

    public function healthCheck(): array
    {
        $start = microtime(true);
        $res = $this->request('GET', '/health');
        $latency = round((microtime(true) - $start) * 1000, 1);

        if (!$res['success']) {
            return [
                'ok' => false,
                'status' => $res['status'] ?? 502,
                'latency_ms' => $latency,
                'message' => $res['message'],
                'data' => $res['data'] ?? [],
            ];
        }

        $data = $res['data'] ?? [];
        $domain = $data['domain'] ?? '-';
        $bridgeOk = (bool) ($data['bridge_ok'] ?? false);

        return [
            'ok' => $bridgeOk,
            'status' => $bridgeOk ? 200 : 503,
            'latency_ms' => $latency,
            'message' => $bridgeOk
                ? __('Terhubung ke panel :name (:domain, :ms ms).', ['name' => $this->server->name, 'domain' => $domain, 'ms' => $latency])
                : __('Panel merespons tetapi VPN bridge (/tmp/vpn-bridge.sock) tidak aktif.'),
            'data' => $data,
        ];
    }

    /**
     * Deep 5-stage live diagnostic from Store Server -> vpnxray-main Panel Server.
     */
    public function diagnose(): array
    {
        $steps = [];
        $recommendations = [];
        $endpoint = $this->resolveApiBaseUrl();
        $parsed = parse_url($endpoint);
        $scheme = strtolower((string) ($parsed['scheme'] ?? 'https'));
        $host = (string) ($parsed['host'] ?? '');
        $port = (int) ($parsed['port'] ?? ($scheme === 'https' ? 443 : 80));

        // Stage 1: Config & DNS / TCP Connect
        if (!$this->server->isConfigured() || $host === '') {
            $steps[] = [
                'key' => 'config',
                'title' => __('1. Validasi Konfigurasi Server'),
                'status' => 'fail',
                'latency_ms' => 0,
                'detail' => __('Base URL, Bearer API Token, atau HMAC Secret belum lengkap.'),
                'hint' => __('Edit server dan pastikan Base URL, Token, serta HMAC Secret sudah diisi.'),
            ];
            return $this->finalizeDiagnostic($steps, $recommendations, $endpoint);
        }

        $t0 = microtime(true);
        $resolvedIp = filter_var($host, FILTER_VALIDATE_IP) ? $host : @gethostbyname($host);
        $dnsOk = $resolvedIp && $resolvedIp !== $host || filter_var($host, FILTER_VALIDATE_IP);
        $tcpOk = false;
        $tcpErr = '';
        if ($dnsOk) {
            $errno = 0;
            $errstr = '';
            $fp = @fsockopen($resolvedIp, $port, $errno, $errstr, 5);
            if ($fp) {
                $tcpOk = true;
                fclose($fp);
            } else {
                $tcpErr = trim("[$errno] $errstr");
            }
        }
        $tcpMs = round((microtime(true) - $t0) * 1000, 1);

        if (!$dnsOk) {
            $steps[] = [
                'key' => 'dns_tcp',
                'title' => __('1. DNS & Koneksi TCP'),
                'status' => 'fail',
                'latency_ms' => $tcpMs,
                'detail' => __('DNS gagal me-resolve domain :host.', ['host' => $host]),
                'hint' => __('Periksa A record domain panel VPN di DNS/Cloudflare Anda.'),
            ];
            $recommendations[] = __('Pastikan domain panel VPN sudah mengarah ke IP VPS dengan benar.');
        } elseif (!$tcpOk) {
            $steps[] = [
                'key' => 'dns_tcp',
                'title' => __('1. DNS & Koneksi TCP'),
                'status' => 'fail',
                'latency_ms' => $tcpMs,
                'detail' => __('DNS ok (:ip), namun koneksi TCP ke port :port gagal: :err', ['ip' => $resolvedIp, 'port' => $port, 'err' => $tcpErr]),
                'hint' => __('Periksa firewall VPS panel VPN (iptables/ufw/Oracle Security List) apakah port :port terbuka.', ['port' => $port]),
            ];
            $recommendations[] = __('Buka port :port pada firewall VPS panel VPN dan pastikan HAProxy/Nginx berjalan.', ['port' => $port]);
        } else {
            $steps[] = [
                'key' => 'dns_tcp',
                'title' => __('1. DNS & Koneksi TCP'),
                'status' => 'ok',
                'latency_ms' => $tcpMs,
                'detail' => __('Terhubung ke :ip::port (:ms ms).', ['ip' => $resolvedIp, 'port' => $port, 'ms' => $tcpMs]),
                'hint' => null,
            ];
        }

        // Stage 2: HTTP/2 vs HTTP/1.1 Protocol Probe (catches HAProxy -> Nginx Port 1010 bug!)
        $healthUrl = $endpoint . '/health';
        $h2Probe = $this->rawCurlProbe($healthUrl, defined('CURL_HTTP_VERSION_2_0') ? CURL_HTTP_VERSION_2_0 : null);
        $h1Probe = $this->rawCurlProbe($healthUrl, CURL_HTTP_VERSION_1_1);
        $remoteDateHeader = $h2Probe['date_header'] ?: $h1Probe['date_header'];

        if ($h2Probe['reachable'] && !$h1Probe['reachable']) {
            $steps[] = [
                'key' => 'protocol_routing',
                'title' => __('2. Routing HTTP/2 vs HTTP/1.1'),
                'status' => 'warn',
                'latency_ms' => $h2Probe['ms'],
                'detail' => __('HTTP/2 merespons (HTTP :h2, :ms ms), namun HTTP/1.1 gagal (:err).', [
                    'h2' => $h2Probe['http_code'],
                    'ms' => $h2Probe['ms'],
                    'err' => $h1Probe['error'] ?: ('HTTP ' . $h1Probe['http_code']),
                ]),
                'hint' => __('Port 1010 Nginx (backend ws_bridge HAProxy) di VPS VPN belum memiliki routing Laravel webpanel-mvc. Jalankan cek-update terbaru di VPS VPN.'),
            ];
            $recommendations[] = __('Jalankan cek-update di VPS panel VPN agar /etc/nginx/conf.d/xray.conf (Port 1010) mendukung HTTP/1.1 + HTTP/2 untuk REST API.');
        } elseif ($h2Probe['reachable'] && $h1Probe['reachable']) {
            $steps[] = [
                'key' => 'protocol_routing',
                'title' => __('2. Routing HTTP/2 vs HTTP/1.1'),
                'status' => 'ok',
                'latency_ms' => $h2Probe['ms'],
                'detail' => __('HTTP/2 (HTTP :h2, :ms2 ms) dan HTTP/1.1 (HTTP :h1, :ms1 ms) keduanya merespons normal.', [
                    'h2' => $h2Probe['http_code'],
                    'ms2' => $h2Probe['ms'],
                    'h1' => $h1Probe['http_code'],
                    'ms1' => $h1Probe['ms'],
                ]),
                'hint' => null,
            ];
        } elseif (!$h2Probe['reachable'] && $h1Probe['reachable']) {
            $steps[] = [
                'key' => 'protocol_routing',
                'title' => __('2. Routing HTTP/2 vs HTTP/1.1'),
                'status' => 'ok',
                'latency_ms' => $h1Probe['ms'],
                'detail' => __('HTTP/1.1 merespons normal (HTTP :h1, :ms ms).', ['h1' => $h1Probe['http_code'], 'ms' => $h1Probe['ms']]),
                'hint' => null,
            ];
        } else {
            $steps[] = [
                'key' => 'protocol_routing',
                'title' => __('2. Routing HTTP/2 vs HTTP/1.1'),
                'status' => 'fail',
                'latency_ms' => max($h2Probe['ms'], $h1Probe['ms']),
                'detail' => __('HTTP/2 gagal (:e2) & HTTP/1.1 gagal (:e1).', [
                    'e2' => $h2Probe['error'] ?: ('HTTP ' . $h2Probe['http_code']),
                    'e1' => $h1Probe['error'] ?: ('HTTP ' . $h1Probe['http_code']),
                ]),
                'hint' => __('Periksa sertifikat SSL, Nginx, HAProxy, atau coba nonaktifkan sementara opsi "Verifikasi SSL" jika memakai sertifikat self-signed.'),
            ];
            $recommendations[] = __('Jalankan perintah diag-vpn-api di SSH VPS panel VPN untuk melihat status Nginx/HAProxy/SSL.');
        }

        // Stage 3: SSL / TLS Verification
        $sslError = str_contains(strtolower($h2Probe['error'] . ' ' . $h1Probe['error']), 'ssl')
            || str_contains(strtolower($h2Probe['error'] . ' ' . $h1Probe['error']), 'certificate');
        $steps[] = [
            'key' => 'ssl',
            'title' => __('3. Sertifikat SSL / TLS'),
            'status' => $sslError ? 'fail' : ($scheme === 'https' ? 'ok' : 'warn'),
            'latency_ms' => null,
            'detail' => $sslError
                ? __('Handshake SSL/TLS gagal: :err', ['err' => $h2Probe['error'] ?: $h1Probe['error']])
                : ($scheme === 'https'
                    ? __('HTTPS aktif (Verifikasi SSL: :mode).', ['mode' => $this->server->verify_ssl ? 'Aktif' : 'Nonaktif'])
                    : __('Menggunakan HTTP tanpa enkripsi TLS (direkomendasikan HTTPS).')),
            'hint' => $sslError ? __('Pastikan sertifikat SSL Let\'s Encrypt/Cloudflare valid, atau matikan verifikasi SSL sementara untuk uji coba.') : null,
        ];

        // Stage 4: Clock Skew (HMAC ±60s window!)
        if ($remoteDateHeader) {
            $remoteTs = strtotime($remoteDateHeader);
            $localTs = time();
            $skew = $remoteTs !== false ? abs($localTs - $remoteTs) : null;
            if ($skew !== null && $skew > 55) {
                $steps[] = [
                    'key' => 'clock_skew',
                    'title' => __('4. Sinkronisasi Jam Server (HMAC ±60s)'),
                    'status' => 'fail',
                    'latency_ms' => null,
                    'detail' => __('Selisih jam terdeteksi :sec detik! Server Jualan: :local vs Panel VPN: :remote.', [
                        'sec' => $skew,
                        'local' => gmdate('H:i:s', $localTs) . ' UTC',
                        'remote' => gmdate('H:i:s', $remoteTs) . ' UTC',
                    ]),
                    'hint' => __('HMAC-SHA256 menolak request jika selisih jam > 60 detik. Jalankan "timedatectl set-ntp true" pada kedua VPS.'),
                ];
                $recommendations[] = __('Sinkronkan jam NTP pada kedua VPS (selisih saat ini :sec detik).', ['sec' => $skew]);
            } elseif ($skew !== null) {
                $steps[] = [
                    'key' => 'clock_skew',
                    'title' => __('4. Sinkronisasi Jam Server (HMAC ±60s)'),
                    'status' => 'ok',
                    'latency_ms' => null,
                    'detail' => __('Jam tersinkronisasi (selisih :sec detik, batas aman 60 detik).', ['sec' => $skew]),
                    'hint' => null,
                ];
            }
        } else {
            $steps[] = [
                'key' => 'clock_skew',
                'title' => __('4. Sinkronisasi Jam Server (HMAC ±60s)'),
                'status' => 'warn',
                'latency_ms' => null,
                'detail' => __('Header Date dari panel VPN belum terbaca (menunggu koneksi HTTP berhasil).'),
                'hint' => null,
            ];
        }

        // Stage 5: Full Signed Auth & Panel Health (/api/v1/vpn/health)
        $health = $this->healthCheck();
        if (!empty($health['ok'])) {
            $d = $health['data'] ?? [];
            $steps[] = [
                'key' => 'auth_health',
                'title' => __('5. Autentikasi Bearer + HMAC & Kesehatan Panel'),
                'status' => 'ok',
                'latency_ms' => $health['latency_ms'] ?? null,
                'detail' => __('Auth valid! Domain: :dom | Bridge: OK | Xray: :xray | SSH: :ssh | Akun: :cnt (:ms ms).', [
                    'dom' => $d['domain'] ?? '-',
                    'xray' => !empty($d['xray_active']) ? 'Aktif' : 'Mati',
                    'ssh' => !empty($d['ssh_active']) ? 'Aktif' : 'Mati',
                    'cnt' => $d['total_accounts'] ?? 0,
                    'ms' => $health['latency_ms'] ?? 0,
                ]),
                'hint' => null,
            ];
        } else {
            $statusCode = (int) ($health['status'] ?? 0);
            $hint = match ($statusCode) {
                401 => __('Periksa kembali Bearer API Token & HMAC-SHA256 Secret (pastikan tersalin utuh 64 karakter tanpa spasi, dan tidak berbeda dengan hasil Generate terbaru di panel VPN).'),
                403 => __('IP VPS Website Jualan tidak ada dalam IP Allowlist di panel vpnxray-main. Kosongkan IP Allowlist atau tambahkan IP VPS jualan.'),
                404 => __('Route /api/v1/vpn/health tidak ditemukan (404). Pastikan panel vpnxray-main sudah di-update ke commit terbaru dan jalankan php artisan route:clear.'),
                503 => __('External REST API masih nonaktif di panel VPN atau vpn-bridge.service tidak berjalan. Aktifkan switch External REST API & cek systemctl status vpn-bridge.'),
                default => __('Periksa tahap 1–4 di atas dan jalankan diag-vpn-api pada VPS panel VPN.'),
            };
            $steps[] = [
                'key' => 'auth_health',
                'title' => __('5. Autentikasi Bearer + HMAC & Kesehatan Panel'),
                'status' => 'fail',
                'latency_ms' => $health['latency_ms'] ?? null,
                'detail' => ($statusCode > 0 ? "HTTP {$statusCode}: " : '') . ($health['message'] ?? __('Gagal terhubung.')),
                'hint' => $hint,
            ];
            $recommendations[] = $hint;
        }

        return $this->finalizeDiagnostic($steps, $recommendations, $endpoint, $health);
    }

    private function finalizeDiagnostic(array $steps, array $recommendations, string $endpoint, ?array $health = null): array
    {
        $overallOk = !empty($health['ok']);
        $headline = $overallOk
            ? ($health['message'] ?? __('Semua pemeriksaan lulus, server VPN siap digunakan!'))
            : ($health['message'] ?? __('Ditemukan kendala koneksi pada server VPN. Lihat rincian tahap diagnostik di bawah.'));

        return [
            'ok' => $overallOk,
            'server_name' => $this->server->name,
            'server_code' => $this->server->code,
            'driver' => $this->server->driver,
            'endpoint' => $endpoint,
            'latency_ms' => $health['latency_ms'] ?? null,
            'headline' => $headline,
            'checked_at' => now()->format('Y-m-d H:i:s'),
            'steps' => $steps,
            'recommendations' => array_values(array_unique(array_filter($recommendations))),
        ];
    }

    private function rawCurlProbe(string $url, ?int $httpVersion): array
    {
        if (!function_exists('curl_init')) {
            return ['reachable' => false, 'http_code' => 0, 'ms' => 0, 'error' => 'php-curl unavailable', 'date_header' => null];
        }

        $t0 = microtime(true);
        $dateHeader = null;
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => (bool) $this->server->verify_ssl,
            CURLOPT_SSL_VERIFYHOST => $this->server->verify_ssl ? 2 : 0,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_HEADERFUNCTION => function ($ch, $headerLine) use (&$dateHeader) {
                if (stripos($headerLine, 'Date:') === 0) {
                    $dateHeader = trim(substr($headerLine, 5));
                }
                return strlen($headerLine);
            },
        ];
        if ($httpVersion !== null) {
            $opts[CURLOPT_HTTP_VERSION] = $httpVersion;
        }
        curl_setopt_array($ch, $opts);
        curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $ms = round((microtime(true) - $t0) * 1000, 1);

        return [
            'reachable' => $code > 0 && $err === '',
            'http_code' => $code,
            'ms' => $ms,
            'error' => $err,
            'date_header' => $dateHeader,
        ];
    }

    public function checkUsername(string $username, ?string $protocol = null): array
    {
        $query = ['username' => $username];
        if ($protocol) {
            $query['protocol'] = strtolower($protocol);
        }

        $res = $this->request('GET', '/check-username', [], $query);
        if (!$res['success']) {
            return [
                'success' => false,
                'message' => $res['message'],
            ];
        }

        return [
            'success' => true,
            'available' => (bool) ($res['data']['available'] ?? false),
            'exists' => (bool) ($res['data']['exists'] ?? true),
            'message' => $res['message'],
        ];
    }

    public function listAccounts(?string $protocol = null, ?string $status = null, ?string $search = null): array
    {
        $query = array_filter([
            'protocol' => $protocol,
            'status' => $status,
            'search' => $search,
        ], fn ($v) => $v !== null && $v !== '');

        $res = $this->request('GET', '/accounts', [], $query);
        if (!$res['success']) {
            return [
                'success' => false,
                'accounts' => [],
                'message' => $res['message'],
            ];
        }

        return [
            'success' => true,
            'accounts' => is_array($res['data']['accounts'] ?? null) ? $res['data']['accounts'] : [],
            'message' => $res['message'],
        ];
    }

    public function getAccountDetail(string $protocol, string $username, string $sniMode = '3'): array
    {
        $proto = rawurlencode(strtolower($protocol));
        $user = rawurlencode($username);
        return $this->request('GET', "/accounts/{$proto}/{$user}", [], ['sni_mode' => $sniMode]);
    }

    public function createAccount(array $payload, ?string $idempotencyKey = null): array
    {
        $res = $this->request('POST', '/accounts', $payload, [], $idempotencyKey);
        if ($res['success']) {
            $res['output'] = (string) ($res['data']['formatted_text'] ?? '');
        }
        return $res;
    }

    public function renewAccount(string $protocol, string $username, int $days, ?int $quotaGb = null, ?int $ipLimit = null, ?string $idempotencyKey = null): array
    {
        $proto = rawurlencode(strtolower($protocol));
        $user = rawurlencode($username);
        $body = ['days' => max(1, min(365, $days))];
        if ($quotaGb !== null) {
            $body['quota_gb'] = max(0, $quotaGb);
        }
        if ($ipLimit !== null) {
            $body['ip_limit'] = max(1, $ipLimit);
        }

        return $this->request('POST', "/accounts/{$proto}/{$user}/renew", $body, [], $idempotencyKey);
    }

    public function suspendAccount(string $protocol, string $username, ?string $reason = null, bool $adminSuspended = true): array
    {
        $proto = rawurlencode(strtolower($protocol));
        $user = rawurlencode($username);
        return $this->request('POST', "/accounts/{$proto}/{$user}/suspend", [
            'reason' => $reason ?: ($adminSuspended ? 'admin_suspend' : 'customer_suspend'),
            'admin_suspended' => $adminSuspended,
        ]);
    }

    public function unsuspendAccount(string $protocol, string $username): array
    {
        $proto = rawurlencode(strtolower($protocol));
        $user = rawurlencode($username);
        return $this->request('POST', "/accounts/{$proto}/{$user}/unsuspend");
    }

    public function deleteAccount(string $protocol, string $username): array
    {
        $proto = rawurlencode(strtolower($protocol));
        $user = rawurlencode($username);
        return $this->request('DELETE', "/accounts/{$proto}/{$user}");
    }

    /**
     * Build signed headers for a given HTTP request against vpnxray-main External API v1.
     */
    public function buildSignedHeaders(string $method, string $requestUri, string $rawBody, ?string $idempotencyKey = null, ?int $timestamp = null, ?string $nonce = null): array
    {
        $ts = (string) ($timestamp ?? time());
        $n = $nonce ?? ('vpn_' . Str::random(24));
        $normalizedMethod = strtoupper($method);
        $normalizedUri = '/' . ltrim($requestUri, '/');
        $bodyHash = hash('sha256', $rawBody);

        $canonical = implode("\n", [$normalizedMethod, $normalizedUri, $ts, $n, $bodyHash]);
        $signature = hash_hmac('sha256', $canonical, (string) $this->server->hmac_secret);

        $headers = [
            'Authorization' => 'Bearer ' . (string) $this->server->api_token,
            'Accept' => 'application/json',
            'X-VPN-Timestamp' => $ts,
            'X-VPN-Nonce' => $n,
            'X-VPN-Signature' => $signature,
        ];

        if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
            $headers['Idempotency-Key'] = trim($idempotencyKey);
        }

        return $headers;
    }

    private function resolveApiBaseUrl(): string
    {
        $baseUrl = rtrim(trim((string) $this->server->base_url), '/');
        if ($baseUrl !== '' && !str_ends_with($baseUrl, '/api/v1/vpn')) {
            $baseUrl .= '/api/v1/vpn';
        }
        return $baseUrl;
    }

    private function request(string $method, string $endpoint, array $body = [], array $query = [], ?string $idempotencyKey = null): array
    {
        if (!$this->server->isConfigured()) {
            return [
                'success' => false,
                'status' => 422,
                'message' => __('Konfigurasi API Server VPN :name belum lengkap.', ['name' => $this->server->name]),
            ];
        }

        $baseUrl = $this->resolveApiBaseUrl();
        $fullUrl = $baseUrl . '/' . ltrim($endpoint, '/');
        if (!empty($query)) {
            $fullUrl .= '?' . http_build_query($query);
        }

        $parsed = parse_url($fullUrl);
        $requestUri = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');

        $methodUpper = strtoupper($method);
        $hasBody = in_array($methodUpper, ['POST', 'PUT', 'PATCH'], true) && !empty($body);
        $rawBody = $hasBody ? (json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}') : '';

        $headers = $this->buildSignedHeaders($methodUpper, $requestUri, $rawBody, $idempotencyKey);
        $timeout = max(3, min(60, (int) ($this->server->timeout_seconds ?: 15)));

        $guzzleOptions = [
            'verify' => (bool) $this->server->verify_ssl,
        ];
        // Prefer HTTP/2 over TLS (fixes HAProxy ALPN h2 vs http/1.1 routing on vpnxray-main) with clean fallback to HTTP/1.1
        if (defined('CURLOPT_HTTP_VERSION') && defined('CURL_HTTP_VERSION_2TLS')) {
            $guzzleOptions['curl'] = [
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
            ];
        }

        try {
            $client = Http::withHeaders($headers)
                ->timeout($timeout)
                ->withOptions($guzzleOptions);

            /** @var Response $response */
            if ($hasBody) {
                $response = $client->withBody($rawBody, 'application/json')->send($methodUpper, $fullUrl);
            } else {
                $response = $client->send($methodUpper, $fullUrl);
            }

            $json = $response->json();
            $status = $response->status();

            if ($response->successful() && is_array($json) && !empty($json['success'])) {
                return [
                    'success' => true,
                    'status' => $status,
                    'message' => (string) ($json['message'] ?? 'OK'),
                    'data' => is_array($json['data'] ?? null) ? $json['data'] : [],
                ];
            }

            $errMsg = is_array($json) && !empty($json['message'])
                ? (string) $json['message']
                : __('Panel VPN mengembalikan error HTTP :code.', ['code' => $status]);

            Log::warning('VpnXrayApiDriver non-success response', [
                'server_id' => $this->server->id,
                'server_code' => $this->server->code,
                'method' => $methodUpper,
                'endpoint' => $endpoint,
                'status' => $status,
                'message' => $errMsg,
            ]);

            return [
                'success' => false,
                'status' => $status,
                'message' => $errMsg,
                'data' => is_array($json['data'] ?? null) ? $json['data'] : [],
            ];
        } catch (ConnectionException $e) {
            Log::error('VpnXrayApiDriver connection failed', [
                'server_id' => $this->server->id,
                'server_code' => $this->server->code,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 504,
                'message' => __('Gagal menjangkau panel VPN (:name): :err', [
                    'name' => $this->server->name,
                    'err' => Str::limit($e->getMessage(), 220),
                ]),
            ];
        } catch (\Throwable $e) {
            Log::error('VpnXrayApiDriver exception', [
                'server_id' => $this->server->id,
                'server_code' => $this->server->code,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 500,
                'message' => __('Terjadi kesalahan saat berkomunikasi dengan panel VPN: :err', ['err' => $e->getMessage()]),
            ];
        }
    }
}
