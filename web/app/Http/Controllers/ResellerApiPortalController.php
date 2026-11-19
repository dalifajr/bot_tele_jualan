<?php

namespace App\Http\Controllers;

use App\Models\BotSetting;
use App\Models\ResellerApiKey;
use App\Models\ResellerApiLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ResellerApiPortalController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $isAdmin = $user->role === 'admin';

        if (!$user->canUseResellerApi()) {
            return redirect()->route('dashboard')
                ->with('error', __('Akses API Reseller (H2H) belum diaktifkan untuk akun Anda. Hubungi admin untuk aktivasi.'));
        }

        $allowedTabs = $isAdmin ? ['keys', 'docs', 'logs', 'admin'] : ['keys', 'docs', 'logs'];
        $activeTab = $request->query('tab', 'keys');
        if (!in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'keys';
        }

        $apiKeys = ResellerApiKey::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->orderByDesc('id')
            ->get();

        $logs = ResellerApiLog::with('apiKey')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $stats = [
            'active_keys' => $apiKeys->where('is_active', true)->count(),
            'total_requests' => (int) $apiKeys->sum('requests_count'),
            'today_requests' => ResellerApiLog::where('user_id', $user->id)
                ->where('created_at', '>=', now()->startOfDay())
                ->count(),
            'success_rate' => $this->calculateSuccessRate($user->id),
            'wallet_balance' => (int) ($user->customer_wallet_balance ?? 0),
        ];

        $adminData = null;
        if ($isAdmin) {
            $adminData = [
                'access_mode' => BotSetting::where('key', 'reseller_api_access_mode')->value('value') ?? 'seller_admin',
                'all_keys' => ResellerApiKey::with('user')
                    ->whereNull('revoked_at')
                    ->orderByDesc('last_used_at')
                    ->orderByDesc('id')
                    ->limit(100)
                    ->get(),
                'global_today_requests' => ResellerApiLog::where('created_at', '>=', now()->startOfDay())->count(),
                'global_active_keys' => ResellerApiKey::whereNull('revoked_at')->where('is_active', true)->count(),
                'global_orders_today' => ResellerApiLog::where('created_at', '>=', now()->startOfDay())
                    ->whereNotNull('order_ref')
                    ->whereIn('status_code', [200, 201])
                    ->count(),
            ];
        }

        $baseUrl = rtrim(config('app.url') ?: url('/'), '/');

        return view('developer.api', compact(
            'user',
            'isAdmin',
            'activeTab',
            'apiKeys',
            'logs',
            'stats',
            'adminData',
            'baseUrl'
        ));
    }

    public function storeKey(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->canUseResellerApi()) {
            abort(403, __('Akses API Reseller tidak diizinkan.'));
        }

        $maxKeys = $user->role === 'admin' ? 20 : 5;
        $existingCount = ResellerApiKey::where('user_id', $user->id)->whereNull('revoked_at')->count();
        if ($existingCount >= $maxKeys) {
            return redirect()->route('developer.api.index', ['tab' => 'keys'])
                ->with('error', __('Batas maksimal :max API Key aktif telah tercapai. Cabut key lama terlebih dahulu.', ['max' => $maxKeys]));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'allowed_ips' => ['nullable', 'string', 'max:1000'],
            'webhook_url' => ['nullable', 'url', 'max:500'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:5', 'max:300'],
        ]);

        $ips = ResellerApiKey::parseIpWhitelist($validated['allowed_ips'] ?? null);
        $pair = ResellerApiKey::generateKeyPair();
        $webhookSecret = !empty($validated['webhook_url']) ? ResellerApiKey::generateWebhookSecret() : null;

        $rateLimit = $user->role === 'admin'
            ? (int) ($validated['rate_limit_per_minute'] ?? 120)
            : min(120, (int) ($validated['rate_limit_per_minute'] ?? 60));

        $apiKey = ResellerApiKey::create([
            'user_id' => $user->id,
            'name' => trim($validated['name']),
            'key_prefix' => $pair['prefix'],
            'key_hash' => $pair['hash'],
            'allowed_ips' => !empty($ips) ? $ips : null,
            'webhook_url' => ($validated['webhook_url'] ?? null) ?: null,
            'webhook_secret' => $webhookSecret,
            'rate_limit_per_minute' => $rateLimit,
            'is_active' => true,
        ]);

        DB::table('audit_logs')->insert([
            'action' => 'reseller_api_key_created',
            'actor_id' => $user->id,
            'entity_type' => 'reseller_api_key',
            'entity_id' => $apiKey->id,
            'detail' => "Created Reseller API key '{$apiKey->name}' ({$apiKey->key_prefix})",
            'created_at' => now(),
        ]);

        return redirect()->route('developer.api.index', ['tab' => 'keys'])
            ->with('success', __('API Key berhasil dibuat! Segera salin kunci di bawah karena hanya ditampilkan satu kali.'))
            ->with('new_api_key', $pair['plain'])
            ->with('new_api_key_name', $apiKey->name)
            ->with('new_webhook_secret', $webhookSecret);
    }

    public function updateKey(Request $request, int $id)
    {
        $user = Auth::user();
        $apiKey = ResellerApiKey::where('id', $id)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'allowed_ips' => ['nullable', 'string', 'max:1000'],
            'webhook_url' => ['nullable', 'url', 'max:500'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:5', 'max:300'],
        ]);

        $ips = ResellerApiKey::parseIpWhitelist($validated['allowed_ips'] ?? null);
        $rateLimit = $user->role === 'admin'
            ? (int) ($validated['rate_limit_per_minute'] ?? $apiKey->rate_limit_per_minute)
            : min(120, (int) ($validated['rate_limit_per_minute'] ?? $apiKey->rate_limit_per_minute));

        $newWebhookSecret = null;
        if (!empty($validated['webhook_url']) && empty($apiKey->webhook_secret)) {
            $newWebhookSecret = ResellerApiKey::generateWebhookSecret();
            $apiKey->webhook_secret = $newWebhookSecret;
        }

        $apiKey->name = trim($validated['name']);
        $apiKey->allowed_ips = !empty($ips) ? $ips : null;
        $apiKey->webhook_url = ($validated['webhook_url'] ?? null) ?: null;
        $apiKey->rate_limit_per_minute = $rateLimit;
        $apiKey->save();

        $redirect = redirect()->route('developer.api.index', ['tab' => 'keys'])
            ->with('success', __('Pengaturan API Key berhasil diperbarui.'));

        if ($newWebhookSecret) {
            $redirect->with('new_webhook_secret', $newWebhookSecret);
        }

        return $redirect;
    }

    public function toggleKey(int $id)
    {
        $user = Auth::user();
        $apiKey = ResellerApiKey::where('id', $id)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->firstOrFail();

        $apiKey->is_active = !$apiKey->is_active;
        $apiKey->save();

        return redirect()->route('developer.api.index', ['tab' => 'keys'])
            ->with('success', $apiKey->is_active ? __('API Key diaktifkan.') : __('API Key dinonaktifkan sementara.'));
    }

    public function rotateKey(int $id)
    {
        $user = Auth::user();
        $apiKey = ResellerApiKey::where('id', $id)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->firstOrFail();

        $pair = ResellerApiKey::generateKeyPair();
        $apiKey->key_prefix = $pair['prefix'];
        $apiKey->key_hash = $pair['hash'];
        $apiKey->save();

        DB::table('audit_logs')->insert([
            'action' => 'reseller_api_key_rotated',
            'actor_id' => $user->id,
            'entity_type' => 'reseller_api_key',
            'entity_id' => $apiKey->id,
            'detail' => "Rotated Reseller API key '{$apiKey->name}' -> {$apiKey->key_prefix}",
            'created_at' => now(),
        ]);

        return redirect()->route('developer.api.index', ['tab' => 'keys'])
            ->with('success', __('API Key berhasil dirotasi! Kunci lama sudah tidak berlaku.'))
            ->with('new_api_key', $pair['plain'])
            ->with('new_api_key_name', $apiKey->name);
    }

    public function destroyKey(int $id)
    {
        $user = Auth::user();
        $apiKey = ResellerApiKey::where('id', $id)
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->firstOrFail();

        $apiKey->is_active = false;
        $apiKey->revoked_at = now();
        $apiKey->save();

        DB::table('audit_logs')->insert([
            'action' => 'reseller_api_key_revoked',
            'actor_id' => $user->id,
            'entity_type' => 'reseller_api_key',
            'entity_id' => $apiKey->id,
            'detail' => "Revoked Reseller API key '{$apiKey->name}' ({$apiKey->key_prefix})",
            'created_at' => now(),
        ]);

        return redirect()->route('developer.api.index', ['tab' => 'keys'])
            ->with('success', __('API Key berhasil dicabut secara permanen.'));
    }

    public function adminUpdateAccessMode(Request $request)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403);
        }

        $validated = $request->validate([
            'access_mode' => ['required', 'in:seller_admin,selected,all'],
        ]);

        BotSetting::updateOrCreate(
            ['key' => 'reseller_api_access_mode'],
            ['value' => $validated['access_mode']]
        );

        DB::table('audit_logs')->insert([
            'action' => 'reseller_api_mode_updated',
            'actor_id' => Auth::id(),
            'entity_type' => 'setting',
            'entity_id' => 0,
            'detail' => 'Updated reseller_api_access_mode=' . $validated['access_mode'],
            'created_at' => now(),
        ]);

        return redirect()->route('developer.api.index', ['tab' => 'admin'])
            ->with('success', __('Kebijakan akses API Reseller berhasil diperbarui.'));
    }

    public function adminRevokeKey(int $id)
    {
        if (Auth::user()->role !== 'admin') {
            abort(403);
        }

        $apiKey = ResellerApiKey::where('id', $id)->whereNull('revoked_at')->firstOrFail();
        $apiKey->is_active = false;
        $apiKey->revoked_at = now();
        $apiKey->save();

        DB::table('audit_logs')->insert([
            'action' => 'admin_reseller_api_key_revoked',
            'actor_id' => Auth::id(),
            'entity_type' => 'reseller_api_key',
            'entity_id' => $apiKey->id,
            'detail' => "Admin revoked Reseller API key #{$apiKey->id} owned by user #{$apiKey->user_id}",
            'created_at' => now(),
        ]);

        return redirect()->route('developer.api.index', ['tab' => 'admin'])
            ->with('success', __('API Key reseller berhasil dicabut oleh Admin.'));
    }

    protected function calculateSuccessRate(int $userId): float
    {
        $total = ResellerApiLog::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        if ($total === 0) {
            return 100.0;
        }

        $ok = ResellerApiLog::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereBetween('status_code', [200, 299])
            ->count();

        return round(($ok / $total) * 100, 1);
    }
}
