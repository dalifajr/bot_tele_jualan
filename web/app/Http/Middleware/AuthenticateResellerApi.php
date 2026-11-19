<?php

namespace App\Http\Middleware;

use App\Models\FirewallRule;
use App\Models\ResellerApiKey;
use App\Models\ResellerApiLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateResellerApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $ip = $request->ip();

        // Check global firewall IP block first
        try {
            if ($ip && FirewallRule::isIpBlocked($ip)) {
                return response()->json([
                    'success' => false,
                    'error' => 'ip_blocked',
                    'message' => 'IP address is blocked by security firewall.',
                ], 403);
            }
        } catch (\Throwable $e) {
            // Skip if firewall table unavailable
        }

        $plainKey = $this->extractApiKey($request);
        if (!$plainKey) {
            return response()->json([
                'success' => false,
                'error' => 'missing_api_key',
                'message' => 'API key is required via Authorization: Bearer <key> or X-API-Key header.',
            ], 401);
        }

        $keyHash = hash('sha256', $plainKey);
        $apiKey = ResellerApiKey::with('user')
            ->where('key_hash', $keyHash)
            ->first();

        if (!$apiKey || !$apiKey->isUsable()) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_api_key',
                'message' => 'API key is invalid, inactive, or revoked.',
            ], 401);
        }

        $user = $apiKey->user;
        if (!$user || !$user->canUseResellerApi()) {
            $this->recordLog($apiKey, $request, 403, $startedAt, null, 'User access suspended or not authorized for Reseller API');
            return response()->json([
                'success' => false,
                'error' => 'access_denied',
                'message' => 'Your account does not have active Reseller API access.',
            ], 403);
        }

        if (!$apiKey->matchesIp($ip)) {
            $this->recordLog($apiKey, $request, 403, $startedAt, null, "IP {$ip} not in whitelist");
            return response()->json([
                'success' => false,
                'error' => 'ip_not_whitelisted',
                'message' => "IP address {$ip} is not allowed by this API key whitelist.",
            ], 403);
        }

        $limit = max(1, (int) ($apiKey->rate_limit_per_minute ?: 60));
        $limiterKey = 'reseller-api-key:' . $apiKey->id;

        if (RateLimiter::tooManyAttempts($limiterKey, $limit)) {
            $retryAfter = RateLimiter::availableIn($limiterKey);
            $this->recordLog($apiKey, $request, 429, $startedAt, null, 'Rate limit exceeded');

            return response()->json([
                'success' => false,
                'error' => 'rate_limit_exceeded',
                'message' => 'Too many requests. Please slow down.',
                'retry_after_seconds' => $retryAfter,
            ], 429)->header('Retry-After', (string) $retryAfter);
        }

        RateLimiter::hit($limiterKey, 60);

        // Attach context for downstream controller
        $request->attributes->set('reseller_api_key', $apiKey);
        $request->attributes->set('reseller_api_user', $user);
        Auth::setUser($user);

        /** @var Response $response */
        $response = $next($request);

        // Update usage metadata
        try {
            $apiKey->forceFill([
                'last_used_at' => now(),
                'last_used_ip' => $ip,
                'requests_count' => (int) $apiKey->requests_count + 1,
            ])->save();
        } catch (\Throwable $e) {
            // Non-fatal
        }

        $orderRef = $request->attributes->get('reseller_order_ref');
        $summary = $request->attributes->get('reseller_response_summary');
        $this->recordLog($apiKey, $request, $response->getStatusCode(), $startedAt, $orderRef, $summary);

        return $response
            ->header('X-RateLimit-Limit', (string) $limit)
            ->header('X-RateLimit-Remaining', (string) max(0, $limit - RateLimiter::attempts($limiterKey)));
    }

    protected function extractApiKey(Request $request): ?string
    {
        $authHeader = $request->header('Authorization', '');
        if (is_string($authHeader) && preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $matches)) {
            return trim($matches[1]);
        }

        $xApiKey = $request->header('X-API-Key');
        if (is_string($xApiKey) && trim($xApiKey) !== '') {
            return trim($xApiKey);
        }

        return null;
    }

    protected function recordLog(
        ResellerApiKey $apiKey,
        Request $request,
        int $statusCode,
        float $startedAt,
        ?string $orderRef = null,
        ?string $responseSummary = null
    ): void {
        try {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $safePayload = $request->except(['password', 'vpn_password', 'api_key', 'token', 'secret']);
            $clientRef = $request->input('client_ref');
            if (is_string($clientRef)) {
                $clientRef = substr(trim($clientRef), 0, 80);
            } else {
                $clientRef = null;
            }

            ResellerApiLog::create([
                'api_key_id' => $apiKey->id,
                'user_id' => $apiKey->user_id,
                'method' => strtoupper($request->method()),
                'endpoint' => '/' . ltrim($request->path(), '/'),
                'status_code' => $statusCode,
                'ip_address' => $request->ip(),
                'duration_ms' => max(0, $durationMs),
                'order_ref' => $orderRef,
                'client_ref' => $clientRef,
                'request_summary' => !empty($safePayload) ? $safePayload : null,
                'response_summary' => $responseSummary ? substr($responseSummary, 0, 255) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Never break API response due to logging error
        }
    }
}
