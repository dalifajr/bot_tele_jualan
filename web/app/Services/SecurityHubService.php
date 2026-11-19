<?php

namespace App\Services;

use App\Models\BotSetting;
use App\Models\FirewallRule;
use App\Models\LoginLog;
use App\Models\TelegramLoginToken;
use App\Models\User;
use App\Models\UserSession;
use App\Models\Visitor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SecurityHubService
{
    public const VALID_TABS = ['peta', 'analitik', 'riwayat', 'firewall', 'ancaman', 'sesi-audit'];

    public function buildHubData(Request $request, string $defaultTab = 'peta'): array
    {
        $requestedTab = (string) $request->query('tab', '');
        if (in_array($requestedTab, self::VALID_TABS, true)) {
            $activeTab = $requestedTab;
        } elseif ($request->filled('fw_search') || $request->filled('fw_type') || $request->filled('fw_page')) {
            $activeTab = 'firewall';
        } elseif ($request->filled('search') || $request->filled('status') || $request->filled('device_type') || $request->filled('log_page') || $request->filled('tg_page')) {
            $activeTab = 'riwayat';
        } else {
            $activeTab = in_array($defaultTab, self::VALID_TABS, true) ? $defaultTab : 'peta';
        }

        // =========================================================================
        // A. LOGIN LOGS, TELEGRAM TOKENS, ANALYTICS & GEOLOCATION MAP
        // =========================================================================
        $loginTokens = TelegramLoginToken::orderBy('created_at', 'desc')
            ->paginate(15, ['*'], 'tg_page')
            ->withQueryString();

        $logsQuery = LoginLog::with('user')->orderBy('created_at', 'desc');
        if ($request->filled('search')) {
            $search = $request->search;
            $logsQuery->where(function ($q) use ($search) {
                $q->where('username_or_email', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('browser', 'like', "%{$search}%")
                    ->orWhere('device_type', 'like', "%{$search}%");
            });
        }
        if ($request->filled('status')) {
            if ($request->status === 'success') {
                $logsQuery->where('is_successful', true);
            } elseif ($request->status === 'failed') {
                $logsQuery->where('is_successful', false);
            }
        }
        if ($request->filled('device_type')) {
            $logsQuery->where('device_type', $request->device_type);
        }

        $loginLogs = $logsQuery->paginate(15, ['*'], 'log_page')->withQueryString();

        $analyticsBase = LoginLog::query();
        if ($request->filled('search')) {
            $search = $request->search;
            $analyticsBase->where(function ($q) use ($search) {
                $q->where('username_or_email', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('browser', 'like', "%{$search}%")
                    ->orWhere('device_type', 'like', "%{$search}%");
            });
        }
        if ($request->filled('status')) {
            if ($request->status === 'success') {
                $analyticsBase->where('is_successful', true);
            } elseif ($request->status === 'failed') {
                $analyticsBase->where('is_successful', false);
            }
        }
        if ($request->filled('device_type')) {
            $analyticsBase->where('device_type', $request->device_type);
        }

        $totalLoginAttempts = (clone $analyticsBase)->count();
        $successLoginCount = (clone $analyticsBase)->where('is_successful', true)->count();
        $failedLoginCount = (clone $analyticsBase)->where('is_successful', false)->count();
        $successRate = $totalLoginAttempts > 0 ? round(($successLoginCount / $totalLoginAttempts) * 100, 1) : 0;
        $failureRate = $totalLoginAttempts > 0 ? round(($failedLoginCount / $totalLoginAttempts) * 100, 1) : 0;

        $uniqueIpCount = (clone $analyticsBase)->whereNotNull('ip_address')->distinct('ip_address')->count('ip_address');
        $uniqueFailedIpCount = (clone $analyticsBase)->where('is_successful', false)->whereNotNull('ip_address')->distinct('ip_address')->count('ip_address');
        $todayLoginCount = (clone $analyticsBase)->whereDate('created_at', now()->toDateString())->count();
        $todayFailedCount = (clone $analyticsBase)->whereDate('created_at', now()->toDateString())->where('is_successful', false)->count();

        // 1. Tren Harian (7 Hari Terakhir)
        $loginTrendLabels = [];
        $loginSuccessTrend = [];
        $loginFailedTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateStr = $date->format('Y-m-d');
            $loginTrendLabels[] = $date->translatedFormat('d M');
            $loginSuccessTrend[] = (clone $analyticsBase)->whereDate('created_at', $dateStr)->where('is_successful', true)->count();
            $loginFailedTrend[] = (clone $analyticsBase)->whereDate('created_at', $dateStr)->where('is_successful', false)->count();
        }

        // 2. Distribusi Perangkat
        $deviceGroups = (clone $analyticsBase)->select('device_type', DB::raw('count(*) as count'))
            ->groupBy('device_type')
            ->orderByDesc('count')
            ->get();
        $deviceLabels = [];
        $deviceData = [];
        foreach ($deviceGroups as $dg) {
            $deviceLabels[] = $dg->device_type ?: 'Unknown';
            $deviceData[] = (int) $dg->count;
        }

        // 3. Distribusi Browser
        $browserGroups = (clone $analyticsBase)->select('browser', DB::raw('count(*) as count'))
            ->groupBy('browser')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
        $browserLabels = [];
        $browserData = [];
        foreach ($browserGroups as $bg) {
            $browserLabels[] = $bg->browser ?: 'Lainnya';
            $browserData[] = (int) $bg->count;
        }

        // 4. Distribusi Lokasi Teratas
        $locationGroups = (clone $analyticsBase)->select('location', DB::raw('count(*) as count'))
            ->groupBy('location')
            ->orderByDesc('count')
            ->limit(5)
            ->get();
        $locationLabels = [];
        $locationData = [];
        foreach ($locationGroups as $lg) {
            $locationLabels[] = $lg->location ?: 'Unknown';
            $locationData[] = (int) $lg->count;
        }

        // 5. Pola Waktu Akses (6 Slot 4-Jam)
        $hourlySlotLabels = ['00:00-03:59', '04:00-07:59', '08:00-11:59', '12:00-15:59', '16:00-19:59', '20:00-23:59'];
        $hourlySuccessSlotData = array_fill(0, 6, 0);
        $hourlyFailedSlotData = array_fill(0, 6, 0);

        $recentLogsForTime = (clone $analyticsBase)->select('created_at', 'is_successful')->limit(1000)->get();
        foreach ($recentLogsForTime as $rl) {
            if (!$rl->created_at) {
                continue;
            }
            $hour = (int) Carbon::parse($rl->created_at)->format('H');
            $slotIndex = min(5, intdiv($hour, 4));
            if ($rl->is_successful) {
                $hourlySuccessSlotData[$slotIndex]++;
            } else {
                $hourlyFailedSlotData[$slotIndex]++;
            }
        }

        $peakSlotIndex = 0;
        $maxSlotTotal = 0;
        for ($idx = 0; $idx < 6; $idx++) {
            $slotSum = $hourlySuccessSlotData[$idx] + $hourlyFailedSlotData[$idx];
            if ($slotSum > $maxSlotTotal) {
                $maxSlotTotal = $slotSum;
                $peakSlotIndex = $idx;
            }
        }
        $peakTimeWindow = $maxSlotTotal > 0 ? $hourlySlotLabels[$peakSlotIndex] : '-';

        // 6. Top IP Gagal & Target Akun
        $topFailedIps = (clone $analyticsBase)
            ->where('is_successful', false)
            ->whereNotNull('ip_address')
            ->select(
                'ip_address',
                DB::raw('count(*) as failed_count'),
                DB::raw('MAX(location) as last_location'),
                DB::raw('MAX(created_at) as last_attempt_at')
            )
            ->groupBy('ip_address')
            ->orderByDesc('failed_count')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $item->is_blocked = Cache::has('blocked_ip:' . $item->ip_address);
                $item->risk_badge = $item->failed_count >= 5 ? __('Tinggi') : ($item->failed_count >= 3 ? __('Sedang') : __('Rendah'));
                return $item;
            });

        $topTargetedAccounts = (clone $analyticsBase)
            ->where('is_successful', false)
            ->whereNotNull('username_or_email')
            ->where('username_or_email', '!=', '')
            ->select(
                'username_or_email',
                DB::raw('count(*) as failed_count'),
                DB::raw('count(distinct ip_address) as unique_ips')
            )
            ->groupBy('username_or_email')
            ->orderByDesc('failed_count')
            ->limit(5)
            ->get();

        // 7. Statistik Token Login Telegram
        $tgTotalTokens = TelegramLoginToken::count();
        $tgUsedTokens = TelegramLoginToken::where('status', 'used')->count();
        $tgPendingTokens = TelegramLoginToken::where('status', 'pending')->count();
        $tgConversionRate = $tgTotalTokens > 0 ? round(($tgUsedTokens / $tgTotalTokens) * 100, 1) : 0;

        // 8. Skor Kesehatan Keamanan & Insight Otomatis
        $maxIpFailures = $topFailedIps->first()->failed_count ?? 0;
        $securityScore = 100;
        if ($failureRate > 50) {
            $securityScore -= 35;
        } elseif ($failureRate > 25) {
            $securityScore -= 18;
        } elseif ($failureRate > 10) {
            $securityScore -= 8;
        }
        if ($maxIpFailures >= 10) {
            $securityScore -= 30;
        } elseif ($maxIpFailures >= 5) {
            $securityScore -= 15;
        }
        $securityScore = max(10, min(100, $securityScore));

        if ($securityScore >= 80) {
            $securityStatus = __('Aman & Terkendali');
            $securityBadgeColor = 'success';
        } elseif ($securityScore >= 55) {
            $securityStatus = __('Waspada (Anomali Terdeteksi)');
            $securityBadgeColor = 'warning';
        } else {
            $securityStatus = __('Risiko Tinggi (Indikasi Brute-Force)');
            $securityBadgeColor = 'danger';
        }

        $dominantDevice = !empty($deviceLabels) ? $deviceLabels[0] : __('Belum ada data');
        $dominantBrowser = !empty($browserLabels) ? $browserLabels[0] : __('Belum ada data');

        $loginAnalysisInsights = [];
        if ($totalLoginAttempts === 0) {
            $loginAnalysisInsights[] = [
                'icon' => 'fa-info-circle',
                'color' => 'secondary',
                'title' => __('Belum Ada Aktivitas Login'),
                'desc' => __('Sistem belum mencatat riwayat autentikasi pada filter saat ini.'),
            ];
        } else {
            $loginAnalysisInsights[] = [
                'icon' => $failureRate > 30 ? 'fa-exclamation-triangle' : 'fa-shield-alt',
                'color' => $failureRate > 30 ? 'danger' : 'success',
                'title' => __('Rasio Keberhasilan & Kegagalan Autentikasi'),
                'desc' => __('Dari :total percobaan login (:ips IP unik), sebesar :success% berhasil dan :failure% gagal (:failed kejadian dari :failedIps IP).', [
                    'total' => $totalLoginAttempts,
                    'ips' => $uniqueIpCount,
                    'success' => $successRate,
                    'failure' => $failureRate,
                    'failed' => $failedLoginCount,
                    'failedIps' => $uniqueFailedIpCount,
                ]),
            ];

            if ($maxIpFailures >= 3) {
                $topBadIp = $topFailedIps->first();
                $loginAnalysisInsights[] = [
                    'icon' => 'fa-user-secret',
                    'color' => 'warning',
                    'title' => __('Deteksi Percobaan Gagal Berulang per IP'),
                    'desc' => __('IP :ip (:location) tercatat gagal login sebanyak :count kali.', [
                        'ip' => $topBadIp->ip_address,
                        'location' => $topBadIp->last_location ?: 'Unknown',
                        'count' => $topBadIp->failed_count,
                    ]) . ' ' . ($topBadIp->is_blocked ? __('IP ini sudah dalam status diblokir.') : __('Disarankan untuk meninjau atau memblokir IP & perangkat tersebut.')),
                ];
            } else {
                $loginAnalysisInsights[] = [
                    'icon' => 'fa-check-double',
                    'color' => 'info',
                    'title' => __('Tidak Ditemukan Serangan Brute-Force Aktif'),
                    'desc' => __('Tidak ada alamat IP tunggal yang melampaui ambang batas kegagalan berulang (≥ 3 kali gagal).'),
                ];
            }

            $loginAnalysisInsights[] = [
                'icon' => 'fa-laptop-code',
                'color' => 'primary',
                'title' => __('Profil Perangkat & Jam Puncak Akses'),
                'desc' => __('Mayoritas pengguna mengakses melalui perangkat :device dengan peramban :browser. Waktu aktivitas tertinggi berada pada rentang pukul :peak.', [
                    'device' => $dominantDevice,
                    'browser' => $dominantBrowser,
                    'peak' => $peakTimeWindow,
                ]),
            ];
        }

        // 9. Peta Sebaran Geografis Pengunjung & Percobaan Login
        $geoBuckets = [];
        $registerGeoPoint = function (
            ?string $city,
            ?string $country,
            ?string $countryCode,
            ?float $lat,
            ?float $lng,
            ?string $fallbackLocation,
            ?string $ip,
            string $source,
            bool $isSuccess = true,
            int $hits = 1
        ) use (&$geoBuckets) {
            if ($lat === null || $lng === null) {
                $resolved = GeoIpService::resolveFromLocationText($fallbackLocation);
                if (!$resolved) {
                    return;
                }
                $city = $city ?: $resolved['city'];
                $country = $country ?: $resolved['country'];
                $countryCode = $countryCode ?: $resolved['country_code'];
                $lat = (float) $resolved['latitude'];
                $lng = (float) $resolved['longitude'];
            }

            $cityClean = trim((string) ($city ?: ''));
            $countryClean = trim((string) ($country ?: ''));
            $codeClean = strtoupper(trim((string) ($countryCode ?: '')));
            $label = trim(implode(', ', array_unique(array_filter([$cityClean, $countryClean]))));
            if ($label === '') {
                $label = trim((string) $fallbackLocation) ?: 'Unknown';
            }

            $bucketKey = strtolower($codeClean . '|' . $cityClean . '|' . round($lat, 2) . '|' . round($lng, 2));
            if (!isset($geoBuckets[$bucketKey])) {
                $geoBuckets[$bucketKey] = [
                    'label' => $label,
                    'city' => $cityClean ?: $countryClean ?: $label,
                    'country' => $countryClean ?: $label,
                    'country_code' => $codeClean ?: '--',
                    'lat' => round($lat, 5),
                    'lng' => round($lng, 5),
                    'visitors_count' => 0,
                    'visitor_hits' => 0,
                    'login_total' => 0,
                    'login_success' => 0,
                    'login_failed' => 0,
                    'ips' => [],
                    'filter_query' => $cityClean ?: $countryClean ?: $label,
                ];
            }

            if ($ip) {
                $geoBuckets[$bucketKey]['ips'][$ip] = true;
            }

            if ($source === 'visitor') {
                $geoBuckets[$bucketKey]['visitors_count']++;
                $geoBuckets[$bucketKey]['visitor_hits'] += max(1, $hits);
            } else {
                $geoBuckets[$bucketKey]['login_total']++;
                if ($isSuccess) {
                    $geoBuckets[$bucketKey]['login_success']++;
                } else {
                    $geoBuckets[$bucketKey]['login_failed']++;
                }
            }
        };

        $geoLoginRows = (clone $analyticsBase)
            ->select('ip_address', 'is_successful', 'location', 'country', 'country_code', 'city', 'latitude', 'longitude')
            ->orderByDesc('id')
            ->limit(2000)
            ->get();

        foreach ($geoLoginRows as $row) {
            $registerGeoPoint(
                $row->city,
                $row->country,
                $row->country_code,
                $row->latitude !== null ? (float) $row->latitude : null,
                $row->longitude !== null ? (float) $row->longitude : null,
                $row->location,
                $row->ip_address,
                'login',
                (bool) $row->is_successful
            );
        }

        $visitors30dQuery = Visitor::where('visited_date', '>=', now()->subDays(30)->toDateString());
        if ($request->filled('search')) {
            $searchVal = $request->search;
            $visitors30dQuery->where(function ($q) use ($searchVal) {
                $q->where('ip_address', 'like', "%{$searchVal}%")
                    ->orWhere('location', 'like', "%{$searchVal}%")
                    ->orWhere('city', 'like', "%{$searchVal}%")
                    ->orWhere('country', 'like', "%{$searchVal}%");
            });
        }

        $visitorRows = (clone $visitors30dQuery)
            ->select('ip_address', 'location', 'country', 'country_code', 'city', 'latitude', 'longitude', 'hits_count')
            ->orderByDesc('id')
            ->limit(2000)
            ->get();

        foreach ($visitorRows as $vRow) {
            $registerGeoPoint(
                $vRow->city,
                $vRow->country,
                $vRow->country_code,
                $vRow->latitude !== null ? (float) $vRow->latitude : null,
                $vRow->longitude !== null ? (float) $vRow->longitude : null,
                $vRow->location,
                $vRow->ip_address,
                'visitor',
                true,
                (int) ($vRow->hits_count ?? 1)
            );
        }

        $geoMapPoints = [];
        $uniqueCountriesSet = [];
        foreach ($geoBuckets as $bucket) {
            $totalActivity = $bucket['visitors_count'] + $bucket['login_total'];
            $riskLevel = $bucket['login_failed'] >= 5 ? 'high' : ($bucket['login_failed'] >= 2 ? 'medium' : 'normal');
            if ($bucket['country'] !== '' && $bucket['country'] !== 'Unknown') {
                $uniqueCountriesSet[$bucket['country']] = true;
            }
            $geoMapPoints[] = [
                'label' => $bucket['label'],
                'city' => $bucket['city'],
                'country' => $bucket['country'],
                'country_code' => $bucket['country_code'],
                'lat' => $bucket['lat'],
                'lng' => $bucket['lng'],
                'visitors_count' => $bucket['visitors_count'],
                'visitor_hits' => $bucket['visitor_hits'],
                'login_total' => $bucket['login_total'],
                'login_success' => $bucket['login_success'],
                'login_failed' => $bucket['login_failed'],
                'unique_ips' => count($bucket['ips']),
                'total_activity' => $totalActivity,
                'risk_level' => $riskLevel,
                'filter_query' => $bucket['filter_query'],
            ];
        }

        usort($geoMapPoints, fn ($a, $b) => $b['total_activity'] <=> $a['total_activity']);

        $geoMapSummary = [
            'mapped_locations' => count($geoMapPoints),
            'unique_countries' => count($uniqueCountriesSet),
            'visitors_today' => Visitor::where('visited_date', now()->toDateString())->count(),
            'visitors_30d' => (clone $visitors30dQuery)->count(),
            'visitor_hits_30d' => (int) (clone $visitors30dQuery)->sum('hits_count'),
            'top_locations' => array_slice($geoMapPoints, 0, 8),
        ];

        // =========================================================================
        // B. FIREWALL, THREAT INTELLIGENCE, ACTIVE SESSIONS & SECURITY POSTURE
        // =========================================================================
        $fwTypeFilter = $request->query('fw_type', $request->query('type', 'all'));
        $fwSearch = trim((string) $request->query('fw_search', ''));

        $rulesQuery = FirewallRule::with('creator')
            ->orderByDesc('is_active')
            ->orderByDesc('id');

        if (in_array($fwTypeFilter, ['ip', 'subnet', 'telegram_id'], true)) {
            $rulesQuery->where('type', $fwTypeFilter);
        }

        if ($fwSearch !== '') {
            $rulesQuery->where(function ($q) use ($fwSearch) {
                $q->where('value', 'like', "%{$fwSearch}%")
                    ->orWhere('reason', 'like', "%{$fwSearch}%");
            });
        }

        $rules = $rulesQuery->paginate(15, ['*'], 'fw_page')->withQueryString();
        $activeRulesCount = FirewallRule::effective()->count();
        $totalBlockedHits = (int) FirewallRule::sum('hit_count');

        $failedLogins24h = LoginLog::where('is_successful', false)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();
        $successLogins24h = LoginLog::where('is_successful', true)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();
        $totalLogins24h = $failedLogins24h + $successLogins24h;
        $failRate24h = $totalLogins24h > 0 ? round(($failedLogins24h / $totalLogins24h) * 100, 1) : 0.0;

        $threatLevel = 'low';
        if ($failedLogins24h >= 50 || $failRate24h >= 40) {
            $threatLevel = 'high';
        } elseif ($failedLogins24h >= 15 || $failRate24h >= 20) {
            $threatLevel = 'medium';
        }

        $suspendedUsersCount = User::where('is_suspended', true)->count();
        $totalUsersCount = max(1, User::count());
        $twoFactorUsersCount = User::where(function ($q) {
            $q->where('two_factor_enabled', true)->orWhere('totp_enabled', true);
        })->count();
        $twoFactorAdoptionRate = round(($twoFactorUsersCount / $totalUsersCount) * 100, 1);

        $activeSessionsCount = Schema::hasTable('user_sessions')
            ? UserSession::whereNull('revoked_at')->where('last_active_at', '>=', now()->subHours(24))->count()
            : 0;

        $existingBlockedIps = FirewallRule::effective()
            ->where('type', 'ip')
            ->pluck('value')
            ->flip();

        $topSuspiciousIps = LoginLog::where('is_successful', false)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('ip_address')
            ->selectRaw('ip_address, COUNT(*) as fail_count, COUNT(DISTINCT username_or_email) as unique_targets, MAX(location) as last_location, MAX(country) as last_country, MAX(created_at) as last_seen')
            ->groupBy('ip_address')
            ->orderByDesc('fail_count')
            ->limit(10)
            ->get()
            ->map(function ($row) use ($existingBlockedIps) {
                $row->is_blocked = $existingBlockedIps->has($row->ip_address) || Cache::has('blocked_ip:' . $row->ip_address);
                $row->subnet_24 = filter_var($row->ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                    ? preg_replace('/\.\d+$/', '.0/24', $row->ip_address)
                    : null;
                return $row;
            });

        $targetedAccounts = LoginLog::where('is_successful', false)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('username_or_email')
            ->where('username_or_email', '!=', '')
            ->selectRaw('username_or_email, COUNT(*) as fail_count, COUNT(DISTINCT ip_address) as unique_ips, MAX(created_at) as last_attempt')
            ->groupBy('username_or_email')
            ->orderByDesc('fail_count')
            ->limit(8)
            ->get();

        $multiAccountIps = User::whereNotNull('registration_ip')
            ->where('registration_ip', '!=', '')
            ->selectRaw('registration_ip, COUNT(*) as account_count, SUM(CASE WHEN is_suspended = 1 THEN 1 ELSE 0 END) as suspended_count, MAX(created_at) as latest_reg')
            ->groupBy('registration_ip')
            ->havingRaw('COUNT(*) >= 2')
            ->orderByDesc('account_count')
            ->limit(8)
            ->get()
            ->map(function ($row) use ($existingBlockedIps) {
                $row->is_blocked = $existingBlockedIps->has($row->registration_ip) || Cache::has('blocked_ip:' . $row->registration_ip);
                return $row;
            });

        $recentSessions = Schema::hasTable('user_sessions')
            ? UserSession::with('user')
                ->whereNull('revoked_at')
                ->orderByDesc('last_active_at')
                ->limit(15)
                ->get()
            : collect();

        $suspendedUsers = User::where('is_suspended', true)
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'username', 'full_name', 'registration_ip', 'suspension_reason', 'updated_at']);

        $recentSecurityEvents = Schema::hasTable('audit_logs')
            ? DB::table('audit_logs')
                ->where(function ($q) {
                    $q->where('action', 'like', 'firewall_%')
                        ->orWhere('action', 'like', 'totp_%')
                        ->orWhere('action', 'like', 'sessions_%')
                        ->orWhere('action', 'user_role_change')
                        ->orWhere('action', 'like', '%block%');
                })
                ->orderByDesc('created_at')
                ->limit(12)
                ->get()
            : collect();

        $spamThreshold = (int) (BotSetting::where('key', 'spam_registration_threshold')->value('value') ?? 5);
        $securityPosture = [
            'turnstile_active' => !empty(config('services.turnstile.site_key'))
                && !empty(config('services.turnstile.secret_key'))
                && config('services.turnstile.site_key') !== '1x00000000000000000000AA',
            'admin_2fa_enabled' => (bool) (Auth::user()?->hasAnyTwoFactorEnabled()),
            'maintenance_active' => BotSetting::where('key', 'maintenance_mode')->value('value') === '1',
            'spam_threshold' => $spamThreshold,
            'app_debug_off' => !config('app.debug'),
        ];

        return compact(
            'activeTab',
            'loginTokens',
            'loginLogs',
            'totalLoginAttempts',
            'successLoginCount',
            'failedLoginCount',
            'successRate',
            'failureRate',
            'uniqueIpCount',
            'uniqueFailedIpCount',
            'todayLoginCount',
            'todayFailedCount',
            'loginTrendLabels',
            'loginSuccessTrend',
            'loginFailedTrend',
            'deviceLabels',
            'deviceData',
            'browserLabels',
            'browserData',
            'locationLabels',
            'locationData',
            'hourlySlotLabels',
            'hourlySuccessSlotData',
            'hourlyFailedSlotData',
            'peakTimeWindow',
            'topFailedIps',
            'topTargetedAccounts',
            'tgTotalTokens',
            'tgUsedTokens',
            'tgPendingTokens',
            'tgConversionRate',
            'securityScore',
            'securityStatus',
            'securityBadgeColor',
            'loginAnalysisInsights',
            'geoMapPoints',
            'geoMapSummary',
            'rules',
            'fwTypeFilter',
            'fwSearch',
            'activeRulesCount',
            'totalBlockedHits',
            'failedLogins24h',
            'successLogins24h',
            'failRate24h',
            'threatLevel',
            'suspendedUsersCount',
            'twoFactorUsersCount',
            'twoFactorAdoptionRate',
            'activeSessionsCount',
            'topSuspiciousIps',
            'targetedAccounts',
            'multiAccountIps',
            'recentSessions',
            'suspendedUsers',
            'recentSecurityEvents',
            'securityPosture'
        );
    }
}
