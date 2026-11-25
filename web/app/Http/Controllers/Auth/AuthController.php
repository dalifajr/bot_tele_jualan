<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Models\LoginLog;
use App\Traits\LogsLogin;

class AuthController extends Controller
{
    use LogsLogin;

    /**
     * Tampilkan halaman login gabungan (Password & Telegram).
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $todayVisitors = \App\Models\Visitor::where('visited_date', now()->toDateString())->count();
        $rawAnnouncement = __(\App\Models\BotSetting::where('key', 'web_announcement')->value('value') ?? 'Selamat datang Jurangan!<br>kalau punya akun telegram, langsung saja klik "Login Via Telegram" kalau gak punya, bisa regis dulu.');
        $announcement = $this->sanitizeAnnouncementHtml($rawAnnouncement);
        $maintenanceMode = \App\Models\BotSetting::where('key', 'maintenance_mode')->value('value') === '1';
        $maintenanceMessage = __(\App\Models\BotSetting::where('key', 'maintenance_message')->value('value')
            ?: 'Website sedang dalam pemeliharaan sistem (Maintenance). Hanya Admin yang dapat login.');
        $companyName = \App\Models\BotSetting::where('key', 'company_name')->value('value') ?? 'PT BANGUN JAYA KASIH';

        return view('auth.login', compact('todayVisitors', 'announcement', 'maintenanceMode', 'maintenanceMessage', 'companyName'));
    }

    /**
     * Tampilkan halaman akun ditangguhkan.
     */
    public function suspended()
    {
        if (!Auth::check() || !Auth::user()->is_suspended) {
            return redirect()->route('dashboard');
        }

        return view('auth.suspended');
    }

    /**
     * Proses Login menggunakan Username/Email & Password.
     */
    public function login(Request $request)
    {
        $rules = [
            'login' => 'required|string',
            'password' => 'required|string',
        ];

        $hasTurnstile = config('services.turnstile.site_key') 
            && config('services.turnstile.secret_key') 
            && config('services.turnstile.site_key') !== '1x00000000000000000000AA';

        if ($hasTurnstile) {
            $rules['cf-turnstile-response'] = 'required|string';
        }

        // Check if IP is blocked manually
        if (\Illuminate\Support\Facades\Cache::has('blocked_ip:' . $request->ip())) {
            return back()->withErrors([
                'login' => 'Akses dari IP Anda telah diblokir sementara.',
            ])->onlyInput('login');
        }

        $request->validate($rules);

        // Validate Captcha
        if ($hasTurnstile) {
            $turnstileSecret = config('services.turnstile.secret_key');
            try {
                $captchaResponse = Http::timeout(5)->asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $turnstileSecret,
                    'response' => $request->input('cf-turnstile-response'),
                ]);

                if (!$captchaResponse->json('success')) {
                    \Illuminate\Support\Facades\Log::warning('Turnstile login verification failed', [
                        'ip' => $request->ip(),
                        'error_codes' => $captchaResponse->json('error-codes'),
                        'secret_configured' => substr($turnstileSecret, 0, 6) . '...',
                    ]);
                    return back()->withErrors([
                        'login' => 'Verifikasi captcha (Turnstile) gagal. Silakan coba lagi.',
                    ])->onlyInput('login');
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Turnstile connection error during login: ' . $e->getMessage(), [
                    'ip' => $request->ip(),
                    'exception' => $e,
                ]);
                return back()->withErrors([
                    'login' => 'Gagal terhubung ke server verifikasi captcha (Cloudflare Timeout). Silakan coba beberapa saat lagi.',
                ])->onlyInput('login');
            }
        }

        $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $remember = $request->has('remember');

        // Rate Limiter key: using IP + login identifier
        $throttleKey = Str::lower($request->input('login')) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->recordLoginLog($request, $request->login, false);
            return back()->withErrors([
                'login' => __('Terlalu banyak percobaan login. Akun Anda diblokir sementara selama :minutes menit.', ['minutes' => ceil($seconds / 60)]),
            ])->onlyInput('login');
        }

        if (Auth::attempt([$loginType => $request->login, 'password' => $request->password], $remember)) {
            $user = Auth::user();

            // Cek blokir IP tingkat per-user (yang diatur pemilik akun dari riwayat login)
            if ($user->role !== 'admin' && \Illuminate\Support\Facades\Cache::has('blocked_user_ip:' . $user->id . ':' . $request->ip())) {
                Auth::logout();
                $this->recordLoginLog($request, $request->login, false);
                return back()->withErrors([
                    'login' => __('Akses ke akun ini dari alamat IP Anda telah diblokir.'),
                ])->onlyInput('login');
            }

            // Check if Maintenance Mode is active and user is not admin
            $isMaintenance = \App\Models\BotSetting::where('key', 'maintenance_mode')->value('value') === '1';
            if ($isMaintenance && $user->role !== 'admin') {
                Auth::logout();
                $this->recordLoginLog($request, $request->login, false);
                $maintenanceMessage = __(\App\Models\BotSetting::where('key', 'maintenance_message')->value('value')
                    ?: 'Website sedang dalam pemeliharaan sistem (Maintenance). Hanya Admin yang dapat login.');

                return back()->withErrors([
                    'login' => $maintenanceMessage,
                ])->onlyInput('login');
            }

            RateLimiter::clear($throttleKey);

            // Check if 2FA (TOTP Authenticator or Telegram OTP) is enabled
            if ($user->hasAnyTwoFactorEnabled()) {
                if ($user->two_factor_enabled && $user->telegram_id) {
                    // Generate 6-digit OTP for Telegram
                    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $user->update([
                        'two_factor_code' => $code,
                        'two_factor_expires_at' => now()->addMinutes(5),
                    ]);

                    // Send OTP via Telegram Bot
                    $botToken = config('telegram.bot_token');
                    if ($botToken) {
                        try {
                            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                                'chat_id' => $user->telegram_id,
                                'text' => "🔐 Kode Verifikasi 2FA\n\nKode Anda: *{$code}*\n\nKode ini berlaku selama 5 menit. Jangan bagikan kode ini kepada siapapun.",
                                'parse_mode' => 'Markdown',
                            ]);
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::warning('Failed to send 2FA code via Telegram', ['error' => $e->getMessage()]);
                        }
                    }
                }

                // Store user ID in session for 2FA verification, then logout
                $userId = $user->id;
                Auth::logout();
                $request->session()->put('2fa_user_id', $userId);
                $request->session()->put('2fa_remember', $remember);

                return redirect()->route('auth.two-factor');
            }

            // Only record successful login here for users WITHOUT 2FA
            $this->recordLoginLog($request, $request->login, true);

            // Record login IP country
            $this->recordLoginCountry($user, $request);

            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($throttleKey, 300); // 300 seconds = 5 minutes
        
        $this->recordLoginLog($request, $request->login, false);

        $user = User::where($loginType, $request->login)->first();
        if ($user) {
            // Notifikasi ke user bersangkutan
            $user->notify(new \App\Notifications\FailedLoginNotification($request->ip()));
        }

        return back()->withErrors([
            'login' => __('Username/Email atau password salah.'),
        ])->onlyInput('login');
    }

    /**
     * Proses Pendaftaran Akun Baru.
     */
    public function register(Request $request)
    {
        $isMaintenance = \App\Models\BotSetting::where('key', 'maintenance_mode')->value('value') === '1';
        if ($isMaintenance) {
            $maintenanceMessage = __(\App\Models\BotSetting::where('key', 'maintenance_message')->value('value')
                ?: 'Website sedang dalam pemeliharaan sistem (Maintenance). Pendaftaran akun baru ditutup sementara.');

            return back()->withErrors(['full_name' => $maintenanceMessage])->withInput();
        }

        $rules = [
            'full_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'nullable|string|email|max:255|unique:users',
            'password' => ['required', 'string', \Illuminate\Validation\Rules\Password::min(8)->letters()->numbers()->symbols(), 'confirmed'],
        ];

        $hasTurnstile = config('services.turnstile.site_key') 
            && config('services.turnstile.secret_key') 
            && config('services.turnstile.site_key') !== '1x00000000000000000000AA';

        if ($hasTurnstile) {
            $rules['cf-turnstile-response'] = 'required|string';
        }

        $request->validate($rules);

        // Validate Captcha
        if ($hasTurnstile) {
            $turnstileSecret = config('services.turnstile.secret_key');
            try {
                $captchaResponse = Http::timeout(5)->asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $turnstileSecret,
                    'response' => $request->input('cf-turnstile-response'),
                ]);

                if (!$captchaResponse->json('success')) {
                    \Illuminate\Support\Facades\Log::warning('Turnstile registration verification failed', [
                        'ip' => $request->ip(),
                        'error_codes' => $captchaResponse->json('error-codes'),
                        'secret_configured' => substr($turnstileSecret, 0, 6) . '...',
                    ]);
                    return back()->withErrors([
                        'username' => 'Verifikasi captcha (Turnstile) gagal. Silakan coba lagi.',
                    ])->withInput();
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Turnstile connection error during registration: ' . $e->getMessage(), [
                    'ip' => $request->ip(),
                    'exception' => $e,
                ]);
                return back()->withErrors([
                    'username' => 'Gagal terhubung ke server verifikasi captcha (Cloudflare Timeout). Silakan coba beberapa saat lagi.',
                ])->withInput();
            }
        }

        $ip = $request->ip();
        $threshold = intval(\App\Models\BotSetting::where('key', 'spam_registration_threshold')->value('value') ?? 5);

        // Check if there is already registrations from same IP in last 24h
        $existingCount = User::where('registration_ip', $ip)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        $shouldSuspend = false;
        if ($existingCount >= ($threshold - 1)) {
            $shouldSuspend = true;
            // Suspend existing ones from this IP
            User::where('registration_ip', $ip)->update([
                'is_suspended' => true,
                'suspension_reason' => 'Spam registrasi terdeteksi dari IP ini.',
            ]);
        }

        $user = new User([
            'full_name' => $request->full_name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'registration_ip' => $ip,
            'last_seen_at' => now(),
            'has_custom_password' => true,
        ]);
        
        $user->role = 'customer';
        $user->is_suspended = $shouldSuspend;
        $user->suspension_reason = $shouldSuspend ? 'Spam registrasi terdeteksi dari IP ini.' : null;
        $user->save();

        if ($shouldSuspend) {
            return redirect()->route('login')->with('error', __('Registrasi ditolak. Aktivitas mencurigakan (spam) terdeteksi. Semua akun dari IP Anda telah ditangguhkan.'));
        }

        Auth::login($user);

        return redirect()->route('dashboard')->with('success', __('Pendaftaran berhasil. Selamat datang!'));
    }

    /**
     * Set atau Update Password untuk user yang sedang login (khususnya yang tadinya cuma login via Telegram).
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $rules = [
            'password' => ['required', 'string', \Illuminate\Validation\Rules\Password::min(8)->letters()->numbers()->symbols(), 'confirmed'],
        ];

        if ((bool) $user->has_custom_password) {
            $rules['current_password'] = ['required', 'string'];
        }

        $request->validate($rules);

        if ((bool) $user->has_custom_password && !Hash::check($request->input('current_password'), (string) $user->password)) {
            return redirect()->back()->withErrors([
                'current_password' => __('Kata sandi saat ini tidak sesuai.'),
            ]);
        }

        $user->password = Hash::make($request->password);
        $user->has_custom_password = true;
        $user->dismiss_set_password_prompt = true;
        $user->save();

        return redirect()->back()->with('success', __('Password berhasil diatur! Anda kini bisa login menggunakan Username/Email dan Password ini.'));
    }

    /**
     * Tampilkan halaman verifikasi 2FA.
     */
    public function showTwoFactor(Request $request)
    {
        $userId = $request->session()->get('2fa_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (!$user) {
            $request->session()->forget(['2fa_user_id', '2fa_remember']);
            return redirect()->route('login');
        }

        $hasTotp = (bool) ($user->totp_enabled && !empty($user->totp_secret));
        $hasTelegramOtp = (bool) ($user->two_factor_enabled && !empty($user->telegram_id));

        return view('auth.two-factor', compact('hasTotp', 'hasTelegramOtp'));
    }

    /**
     * Verifikasi kode 2FA (TOTP Authenticator, Recovery Code, atau OTP Telegram).
     */
    public function verifyTwoFactor(Request $request)
    {
        $request->validate([
            'code' => 'required|string|min:6|max:20',
        ]);

        $userId = $request->session()->get('2fa_user_id');
        if (!$userId) {
            return redirect()->route('login')->with('error', __('Sesi verifikasi 2FA sudah berakhir.'));
        }

        $attemptKey = '2fa_attempts:' . $userId . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
            $user = User::find($userId);
            if ($user) {
                $user->update([
                    'two_factor_code' => null,
                    'two_factor_expires_at' => null,
                ]);
            }
            $request->session()->forget(['2fa_user_id', '2fa_remember']);
            return redirect()->route('login')->with('error', __('Terlalu banyak percobaan kode 2FA yang salah. Silakan login ulang untuk meminta kode baru.'));
        }

        $user = User::find($userId);
        if (!$user) {
            $request->session()->forget(['2fa_user_id', '2fa_remember']);
            return redirect()->route('login')->with('error', __('Pengguna tidak ditemukan.'));
        }

        $isMaintenance = \App\Models\BotSetting::where('key', 'maintenance_mode')->value('value') === '1';
        if ($isMaintenance && $user->role !== 'admin') {
            $request->session()->forget(['2fa_user_id', '2fa_remember']);
            $this->recordLoginLog($request, $user->username, false);
            $maintenanceMessage = __(\App\Models\BotSetting::where('key', 'maintenance_message')->value('value')
                ?: 'Website sedang dalam pemeliharaan sistem (Maintenance). Hanya Admin yang dapat login.');

            return redirect()->route('login')->with('error', $maintenanceMessage);
        }

        $inputCode = trim((string) $request->input('code'));
        $verified = false;
        $usedRecoveryCode = false;

        // 1. Cek TOTP Authenticator App (6 digit) atau Recovery Code (XXXX-XXXX)
        if ($user->totp_enabled && !empty($user->totp_secret)) {
            if (\App\Services\TwoFactorService::verifyOtp((string) $user->totp_secret, $inputCode)) {
                $verified = true;
            } elseif (is_array($user->totp_recovery_codes) && count($user->totp_recovery_codes) > 0) {
                $remainingCodes = \App\Services\TwoFactorService::consumeRecoveryCode($user->totp_recovery_codes, $inputCode);
                if ($remainingCodes !== null) {
                    $user->totp_recovery_codes = $remainingCodes;
                    $user->save();
                    $verified = true;
                    $usedRecoveryCode = true;
                }
            }
        }

        // 2. Cek Telegram OTP (jika belum lolos TOTP dan metode Telegram aktif)
        if (!$verified && $user->two_factor_enabled && !empty($user->telegram_id)) {
            $isExpired = $user->two_factor_expires_at && $user->two_factor_expires_at->isPast();
            if ($isExpired && !$user->totp_enabled) {
                $user->update([
                    'two_factor_code' => null,
                    'two_factor_expires_at' => null,
                ]);
                $request->session()->forget(['2fa_user_id', '2fa_remember']);
                return redirect()->route('login')->with('error', __('Kode verifikasi sudah kedaluwarsa. Silakan login ulang.'));
            }

            if (!$isExpired && !empty($user->two_factor_code) && hash_equals((string) $user->two_factor_code, $inputCode)) {
                $verified = true;
            }
        }

        if (!$verified) {
            $this->recordLoginLog($request, $user->username, false);
            RateLimiter::hit($attemptKey, 300);
            if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
                $user->update([
                    'two_factor_code' => null,
                    'two_factor_expires_at' => null,
                ]);
                $request->session()->forget(['2fa_user_id', '2fa_remember']);
                return redirect()->route('login')->with('error', __('Terlalu banyak percobaan kode 2FA yang salah. Silakan login ulang untuk meminta kode baru.'));
            }
            return back()->withErrors(['code' => __('Kode verifikasi salah.')]);
        }

        RateLimiter::clear($attemptKey);

        // Clear Telegram OTP code
        $user->update([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);

        // Login the user
        $remember = $request->session()->get('2fa_remember', false);
        $request->session()->forget(['2fa_user_id', '2fa_remember']);
        Auth::login($user, $remember);

        $this->recordLoginLog($request, $user->username, true);

        // Record login IP country
        $this->recordLoginCountry($user, $request);

        $request->session()->regenerate();

        $redirect = redirect()->intended(route('dashboard'));
        if ($usedRecoveryCode) {
            $remainingCount = is_array($user->totp_recovery_codes) ? count($user->totp_recovery_codes) : 0;
            $redirect->with('success', __('Berhasil masuk menggunakan kode pemulihan 2FA. Sisa kode pemulihan Anda: :count.', ['count' => $remainingCount]));
        }

        return $redirect;
    }

    /**
     * Toggle 2FA setting (dari halaman profil).
     */
    public function toggleTwoFactor(Request $request)
    {
        $user = Auth::user();

        // User harus punya telegram_id dan password untuk mengaktifkan 2FA
        if (!$user->telegram_id) {
            return redirect()->back()->with('error', __('Anda harus menautkan akun Telegram terlebih dahulu untuk mengaktifkan 2FA.'));
        }

        if (!$user->password) {
            return redirect()->back()->with('error', __('Anda harus mengatur password terlebih dahulu untuk mengaktifkan 2FA.'));
        }

        $user->update([
            'two_factor_enabled' => !$user->two_factor_enabled,
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);

        $status = $user->two_factor_enabled ? 'diaktifkan' : 'dinonaktifkan';
        return redirect()->back()->with('success', __("Verifikasi dua langkah (2FA) berhasil :status.", ["status" => $status]));
    }

    /**
     * Fetch and record IP location country.
     */
    private function recordLoginCountry(User $user, Request $request)
    {
        $ip = $request->ip();
        $country = 'Local / Unknown';
        
        if ($ip && $ip !== '127.0.0.1' && $ip !== '::1' && !str_starts_with($ip, '192.168.') && !str_starts_with($ip, '10.')) {
            try {
                $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}");
                if ($response->successful() && $response->json('status') === 'success') {
                    $country = $response->json('country') ?? 'Unknown';
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("IP Geolocation lookup failed: " . $e->getMessage());
            }
        }
        
        $user->update([
            'last_login_country' => $country
        ]);
    }

    /**
     * Sanitasi HTML pengumuman login (hanya izinkan tag format teks aman & link http/https).
     */
    protected function sanitizeAnnouncementHtml(string $html): string
    {
        // Hapus total blok script/style beserta isinya sebelum strip_tags
        $clean = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
        $clean = strip_tags($clean, '<br><p><b><strong><i><em><u><ul><ol><li><a><span>');
        // Hapus semua atribut event handler (on*) dan skema berbahaya pada href/src/style
        $clean = preg_replace('/\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/\b(href|src)\s*=\s*(?:"\s*(?:javascript|vbscript|data):[^"]*"|\'\s*(?:javascript|vbscript|data):[^\']*\'|(?:javascript|vbscript|data):[^\s>]+)/i', 'href="#"', $clean) ?? $clean;
        $clean = preg_replace('/\s+style\s*=\s*(?:"[^"]*"|\'[^\']*\')/i', '', $clean) ?? $clean;

        return $clean;
    }
}
