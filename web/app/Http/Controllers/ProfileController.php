<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\TelegramLinkToken;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $currentSessionId = $request->session()->getId();

        $activeSessions = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('user_sessions')) {
            $activeSessions = \App\Models\UserSession::where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->orderByDesc('last_active_at')
                ->limit(15)
                ->get();
        }

        $pendingTotpSecret = $request->session()->get('totp_setup_secret');
        $pendingTotpUri = null;
        if ($pendingTotpSecret) {
            $accountLabel = $user->email ?: ($user->username ?: ('user_' . $user->id));
            $pendingTotpUri = \App\Services\TwoFactorService::buildOtpAuthUri(
                config('app.name', 'Dzulfikrialifajri Store'),
                $accountLabel,
                $pendingTotpSecret
            );
        }

        $recoveryCodesRemaining = is_array($user->totp_recovery_codes) ? count($user->totp_recovery_codes) : 0;

        return view('profile', compact(
            'user',
            'activeSessions',
            'currentSessionId',
            'pendingTotpSecret',
            'pendingTotpUri',
            'recoveryCodesRemaining'
        ));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $rules = [
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'telegram_id' => 'nullable|integer|unique:users,telegram_id,' . $user->id,
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ];

        $request->validate($rules);

        // Keamanan: Cegah klaim atau pengubahan manual telegram_id tanpa verifikasi Bot Telegram.
        if ($request->filled('telegram_id') && (string) $request->input('telegram_id') !== (string) $user->telegram_id) {
            return redirect()->back()
                ->withErrors(['telegram_id' => __('Penautan atau perubahan ID Telegram hanya dapat dilakukan melalui verifikasi Bot Telegram.')])
                ->withInput();
        }

        $data = [
            'full_name' => $request->full_name,
            'email' => $request->email,
        ];

        if ($request->hasFile('avatar')) {
            if ($user->avatar && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->avatar)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = $path;
        }

        $user->update($data);

        return redirect()->back()->with('success', __('Profil berhasil diperbarui!'));
    }

    public function updateThemeColor(Request $request)
    {
        $request->validate([
            'theme_color' => ['nullable', 'string', 'max:30', 'regex:/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/'],
        ]);

        $user = Auth::user();
        $user->update([
            'theme_color' => $request->theme_color,
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => __('Warna tema berhasil disimpan!'),
                'theme_color' => $user->theme_color,
            ]);
        }

        return redirect()->back()->with('success', __('Warna tema berhasil disimpan!'));
    }

    public function checkTelegramId(Request $request)
    {
        $request->validate([
            'telegram_id' => 'required|integer'
        ]);

        $telegramId = $request->telegram_id;
        $currentUser = Auth::user();
        
        $query = User::where('telegram_id', $telegramId);
        
        // If logged in, exclude current user from the check
        if ($currentUser) {
            $query->where('id', '!=', $currentUser->id);
        }

        $existingUser = $query->first();

        if ($existingUser) {
            return response()->json([
                'available' => false,
                'message' => __('ID Telegram ini sudah tertaut dengan akun lain.')
            ]);
        }

        return response()->json([
            'available' => true,
            'message' => __('ID Telegram tersedia!')
        ]);
    }

    public function generateTelegramLink()
    {
        $user = Auth::user();
        
        // Delete old tokens
        TelegramLinkToken::where('user_id', $user->id)->delete();

        // Generate new token
        $token = Str::random(48);
        TelegramLinkToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addMinutes(15),
        ]);

        // We assume bot username is configured in .env or config, fallback to a placeholder
        $botUsername = env('TELEGRAM_BOT_USERNAME', 'JualanSayaBot');
        $botUrl = "https://t.me/{$botUsername}?start=weblink_{$token}";

        return redirect()->away($botUrl);
    }

    public function unlinkTelegram()
    {
        $user = Auth::user();

        if (!$user->has_custom_password) {
            return redirect()->back()->with('error', __('Atur kata sandi akun terlebih dahulu sebelum melepas tautan Telegram agar Anda tidak kehilangan akses akun.'));
        }

        $user->update([
            'telegram_id' => null,
            'two_factor_enabled' => false,
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);

        return redirect()->back()->with('success', __('Akun Telegram berhasil dilepas.'));
    }

    public function loginHistory()
    {
        $user = Auth::user();
        
        $loginLogs = \App\Models\LoginLog::where(function($q) use ($user) {
            $q->where('user_id', $user->id);
            if (!empty($user->username)) {
                $q->orWhere('username_or_email', $user->username);
            }
            if (!empty($user->email)) {
                $q->orWhere('username_or_email', $user->email);
            }
        })->orderBy('created_at', 'desc')->paginate(15);

        $admins = \App\Models\User::where('role', 'admin')->get(['id', 'username', 'full_name']);
        
        return view('profile.logins', compact('loginLogs', 'admins'));
    }

    public function blockIp(Request $request)
    {
        $request->validate([
            'ip_address' => 'required|ip',
            'duration' => 'nullable|integer|in:1,7,30,365'
        ]);
        $user = Auth::user();
        $ip = $request->ip_address;
        $duration = (int) ($request->duration ?? 7);

        // Non-admin hanya boleh memblokir IP yang benar-benar tercatat di riwayat login / notifikasi miliknya
        // dan blokir disimpan per-user agar tidak dapat disalahgunakan untuk DoS global.
        if ($user->role !== 'admin') {
            $hasLog = \App\Models\LoginLog::where('ip_address', $ip)
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                    if (!empty($user->username)) {
                        $q->orWhere('username_or_email', $user->username);
                    }
                    if (!empty($user->email)) {
                        $q->orWhere('username_or_email', $user->email);
                    }
                })->exists();

            $hasNotif = $user->notifications()
                ->where('data->type', 'login_gagal')
                ->where('data->ip_address', $ip)
                ->exists();

            if (!$hasLog && !$hasNotif) {
                abort(403, __('Anda hanya dapat memblokir alamat IP yang tercatat pada riwayat akun Anda.'));
            }
        }
        
        $durationText = '';
        if ($duration === 1) {
            $expire = now()->addDay();
            $durationText = __('1 hari');
        } elseif ($duration === 7) {
            $expire = now()->addDays(7);
            $durationText = __('7 hari');
        } elseif ($duration === 30) {
            $expire = now()->addDays(30);
            $durationText = __('30 hari');
        } elseif ($duration === 365) {
            $expire = now()->addYear();
            $durationText = __('1 tahun');
        } else {
            $expire = now()->addDay();
            $durationText = __('1 hari');
        }
        
        if ($user->role === 'admin') {
            \Illuminate\Support\Facades\Cache::put('blocked_ip:' . $ip, true, $expire);
        } else {
            \Illuminate\Support\Facades\Cache::put('blocked_user_ip:' . $user->id . ':' . $ip, true, $expire);
        }
        
        return back()->with('success', __("IP Address :ip telah diblokir selama :durationText.", ["ip" => $ip, "durationText" => $durationText]));
    }

    public function blockDevice(Request $request)
    {
        $request->validate([
            'device_fingerprint' => 'nullable|string|max:64',
            'device_id' => 'nullable|string|max:64',
            'duration' => 'required|integer|in:1,7,30,365'
        ]);

        $user = Auth::user();
        $fp = $request->device_fingerprint;
        $deviceId = $request->device_id;
        $duration = (int)$request->duration;

        if (!$fp && !$deviceId) {
            return back()->with('error', __('Data perangkat tidak valid.'));
        }

        if ($user->role !== 'admin') {
            $hasLog = \App\Models\LoginLog::where(function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                    if (!empty($user->username)) {
                        $q->orWhere('username_or_email', $user->username);
                    }
                    if (!empty($user->email)) {
                        $q->orWhere('username_or_email', $user->email);
                    }
                })
                ->where(function ($q) use ($fp, $deviceId) {
                    if ($fp) {
                        $q->orWhere('device_fingerprint', $fp);
                    }
                    if ($deviceId) {
                        $q->orWhere('device_id', $deviceId);
                    }
                })
                ->exists();

            if (!$hasLog) {
                abort(403, __('Anda hanya dapat memblokir perangkat yang tercatat pada riwayat akun Anda.'));
            }
        }

        $expire = now()->addDays($duration);
        if ($duration === 365) {
            $expire = now()->addYear();
        }

        if ($user->role === 'admin') {
            if ($fp) {
                \Illuminate\Support\Facades\Cache::put('blocked_device_fp:' . $fp, true, $expire);
            }
            if ($deviceId) {
                \Illuminate\Support\Facades\Cache::put('blocked_device_id:' . $deviceId, true, $expire);
            }
        } else {
            if ($fp) {
                \Illuminate\Support\Facades\Cache::put('blocked_user_device_fp:' . $user->id . ':' . $fp, true, $expire);
            }
            if ($deviceId) {
                \Illuminate\Support\Facades\Cache::put('blocked_user_device_id:' . $user->id . ':' . $deviceId, true, $expire);
            }
        }

        return back()->with('success', __('Perangkat berhasil diblokir.'));
    }

    public function requestUnblock(Request $request)
    {
        $request->validate([
            'admin_id' => 'required|exists:users,id',
            'ip_address' => 'required|ip',
            'location' => 'nullable|string',
            'device' => 'nullable|string',
            'browser' => 'nullable|string',
        ]);
        
        $admin = \App\Models\User::find($request->admin_id);
        $user = Auth::user();
        
        $ip = $request->ip_address;
        $location = $request->location ?? 'Unknown';
        $device = $request->device ?? 'Unknown';
        $browser = $request->browser ?? 'Unknown';
        
        // Notify selected admin via Telegram
        \App\Services\TelegramService::notifyAdminUnblockRequest($admin, $user, $ip, $location, $device, $browser);
        
        // Notify admin via database notification
        $admin->notify(new \App\Notifications\NewChatMessageNotification(
            "Izin minta dibuka blokirnya untuk IP {$ip}",
            $user->full_name ?? $user->username,
            $user->id
        ));

        // Format prefill text
        $prefill = "Halo Pak,\n"
                 . "izin minta dibuka blokirnya untuk;\n"
                 . "IP : {$ip}\n"
                 . "lokasi : {$location}\n"
                 . "perangkat: {$device}\n"
                 . "browser: {$browser}\n"
                 . "terimakasih banyak pak.";
                 
        return redirect()->route('chat.index', [
            'contact_id' => $admin->id,
            'prefill' => $prefill
        ]);
    }

    public function dismissPasswordPrompt(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $user->update([
                'dismiss_set_password_prompt' => true,
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Mulai proses pendaftaran TOTP Authenticator App (Google Authenticator / Authy).
     */
    public function initTotpSetup(Request $request)
    {
        $user = Auth::user();

        if (!$user->has_custom_password && !$user->password) {
            return redirect()->to(route('profile') . '#tab-keamanan')
                ->with('error', __('Atur kata sandi akun terlebih dahulu sebelum mengaktifkan Authenticator App (TOTP).'));
        }

        $secret = \App\Services\TwoFactorService::generateBase32Secret(32);
        $request->session()->put('totp_setup_secret', $secret);

        return redirect()->to(route('profile') . '#tab-keamanan')
            ->with('success', __('Secret key Authenticator dibuat. Pindai QR atau salin kunci lalu masukkan kode 6 digit untuk konfirmasi.'));
    }

    /**
     * Batalkan proses pendaftaran TOTP yang belum selesai.
     */
    public function cancelTotpSetup(Request $request)
    {
        $request->session()->forget('totp_setup_secret');
        return redirect()->to(route('profile') . '#tab-keamanan');
    }

    /**
     * Konfirmasi aktivasi TOTP Authenticator App dan hasilkan Recovery Codes.
     */
    public function confirmTotpSetup(Request $request)
    {
        $request->validate([
            'totp_code' => 'required|string|regex:/^\d{6}$/',
        ]);

        $secret = (string) $request->session()->get('totp_setup_secret', '');
        if ($secret === '') {
            return redirect()->to(route('profile') . '#tab-keamanan')
                ->with('error', __('Sesi penyiapan Authenticator sudah berakhir. Silakan mulai ulang.'));
        }

        if (!\App\Services\TwoFactorService::verifyOtp($secret, (string) $request->input('totp_code'))) {
            return redirect()->to(route('profile') . '#tab-keamanan')
                ->with('error', __('Kode Authenticator 6 digit tidak cocok. Pastikan waktu perangkat Anda sinkron dan coba lagi.'));
        }

        $user = Auth::user();
        $plainRecoveryCodes = \App\Services\TwoFactorService::generateRecoveryCodes(8);
        $hashedRecoveryCodes = \App\Services\TwoFactorService::hashRecoveryCodes($plainRecoveryCodes);

        $user->update([
            'totp_secret' => $secret,
            'totp_enabled' => true,
            'totp_recovery_codes' => $hashedRecoveryCodes,
            'totp_confirmed_at' => now(),
        ]);

        $request->session()->forget('totp_setup_secret');

        if (class_exists(\App\Models\AuditLog::class)) {
            \App\Models\AuditLog::create([
                'actor_id' => $user->id,
                'action' => 'totp_enabled',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'detail' => 'User enabled TOTP Authenticator 2FA',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return redirect()->to(route('profile') . '#tab-keamanan')
            ->with('success', __('Authenticator App (TOTP 2FA) berhasil diaktifkan! Simpan kode pemulihan di tempat yang aman.'))
            ->with('totp_recovery_codes_once', $plainRecoveryCodes);
    }

    /**
     * Buat ulang seluruh Recovery Codes TOTP.
     */
    public function regenerateTotpRecoveryCodes(Request $request)
    {
        $request->validate([
            'verification_code' => 'required|string|min:6|max:20',
        ]);

        $user = Auth::user();
        if (!$user->totp_enabled || empty($user->totp_secret)) {
            return redirect()->to(route('profile') . '#tab-keamanan')
                ->with('error', __('Authenticator App belum aktif pada akun Anda.'));
        }

        if (!\App\Services\TwoFactorService::verifyOtp((string) $user->totp_secret, (string) $request->input('verification_code'))) {
            return redirect()->to(route('profile') . '#tab-keamanan')
                ->with('error', __('Kode 6 digit dari Authenticator App tidak valid.'));
        }

        $plainRecoveryCodes = \App\Services\TwoFactorService::generateRecoveryCodes(8);
        $user->update([
            'totp_recovery_codes' => \App\Services\TwoFactorService::hashRecoveryCodes($plainRecoveryCodes),
        ]);

        return redirect()->to(route('profile') . '#tab-keamanan')
            ->with('success', __('Kode pemulihan baru berhasil dibuat. Kode lama kini tidak lagi berlaku.'))
            ->with('totp_recovery_codes_once', $plainRecoveryCodes);
    }

    /**
     * Nonaktifkan TOTP Authenticator App.
     */
    public function disableTotp(Request $request)
    {
        $request->validate([
            'verification_code' => 'required|string|min:6|max:20',
        ]);

        $user = Auth::user();
        if (!$user->totp_enabled || empty($user->totp_secret)) {
            return redirect()->to(route('profile') . '#tab-keamanan')
                ->with('error', __('Authenticator App memang sedang nonaktif.'));
        }

        $input = trim((string) $request->input('verification_code'));
        $validTotp = \App\Services\TwoFactorService::verifyOtp((string) $user->totp_secret, $input);
        $validRecovery = !$validTotp && is_array($user->totp_recovery_codes)
            && \App\Services\TwoFactorService::consumeRecoveryCode($user->totp_recovery_codes, $input) !== null;

        if (!$validTotp && !$validRecovery) {
            return redirect()->to(route('profile') . '#tab-keamanan')
                ->with('error', __('Kode Authenticator atau kode pemulihan tidak valid untuk menonaktifkan 2FA.'));
        }

        $user->update([
            'totp_secret' => null,
            'totp_enabled' => false,
            'totp_recovery_codes' => null,
            'totp_confirmed_at' => null,
        ]);

        if (class_exists(\App\Models\AuditLog::class)) {
            \App\Models\AuditLog::create([
                'actor_id' => $user->id,
                'action' => 'totp_disabled',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'detail' => 'User disabled TOTP Authenticator 2FA',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return redirect()->to(route('profile') . '#tab-keamanan')
            ->with('success', __('Authenticator App (TOTP 2FA) berhasil dinonaktifkan.'));
    }

    /**
     * Cabut satu sesi aktif tertentu milik pengguna.
     */
    public function revokeSession(Request $request, $id)
    {
        $user = Auth::user();
        $currentSessionId = $request->session()->getId();

        $sessionRecord = \App\Models\UserSession::where('user_id', $user->id)->findOrFail($id);

        if ($sessionRecord->isCurrent($currentSessionId)) {
            return redirect()->to(route('profile') . '#tab-geolokasi')
                ->with('error', __('Gunakan tombol Keluar untuk mengakhiri sesi perangkat yang sedang Anda gunakan.'));
        }

        $sessionRecord->update(['revoked_at' => now()]);

        if (config('session.driver') === 'database' && \Illuminate\Support\Facades\Schema::hasTable('sessions')) {
            \Illuminate\Support\Facades\DB::table('sessions')
                ->where('id', $sessionRecord->session_id)
                ->delete();
        }

        return redirect()->to(route('profile') . '#tab-geolokasi')
            ->with('success', __('Sesi perangkat tersebut berhasil diakhiri.'));
    }

    /**
     * Cabut semua sesi aktif di perangkat lain ("Keluar dari Semua Perangkat Lain").
     */
    public function revokeOtherSessions(Request $request)
    {
        $user = Auth::user();
        $currentSessionId = $request->session()->getId();

        $revokedCount = \App\Models\UserSession::where('user_id', $user->id)
            ->where('session_id', '!=', $currentSessionId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        if (config('session.driver') === 'database' && \Illuminate\Support\Facades\Schema::hasTable('sessions')) {
            \Illuminate\Support\Facades\DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->delete();
        }

        if (class_exists(\App\Models\AuditLog::class)) {
            \App\Models\AuditLog::create([
                'actor_id' => $user->id,
                'action' => 'sessions_revoked_others',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'detail' => "Revoked {$revokedCount} other active sessions",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return redirect()->to(route('profile') . '#tab-geolokasi')
            ->with('success', __('Semua sesi di perangkat lain (:count sesi) berhasil diakhiri.', ['count' => $revokedCount]));
    }
}
