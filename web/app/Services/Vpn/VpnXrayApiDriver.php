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
            'latency_ms' => $latency,
            'message' => $bridgeOk
                ? __('Terhubung ke panel :name (:domain, :ms ms).', ['name' => $this->server->name, 'domain' => $domain, 'ms' => $latency])
                : __('Panel merespons tetapi VPN bridge tidak aktif.'),
            'data' => $data,
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

    private function request(string $method, string $endpoint, array $body = [], array $query = [], ?string $idempotencyKey = null): array
    {
        if (!$this->server->isConfigured()) {
            return [
                'success' => false,
                'status' => 422,
                'message' => __('Konfigurasi API Server VPN :name belum lengkap.', ['name' => $this->server->name]),
            ];
        }

        $baseUrl = rtrim((string) $this->server->base_url, '/');
        if (!str_ends_with($baseUrl, '/api/v1/vpn')) {
            $baseUrl .= '/api/v1/vpn';
        }

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

        try {
            $client = Http::withHeaders($headers)
                ->timeout($timeout)
                ->withOptions(['verify' => (bool) $this->server->verify_ssl]);

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
                'message' => __('Gagal menjangkau panel VPN (:name): koneksi timeout atau tidak dapat dihubungi.', ['name' => $this->server->name]),
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
