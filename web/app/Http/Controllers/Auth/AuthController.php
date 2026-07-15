<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Models\LoginLog;
class AuthController extends Controller
{
    /**
     * Tampilkan halaman login gabungan (Password & Telegram).
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $todayVisitors = \App\Models\Visitor::where('visited_date', now()->toDateString())->count();
        $announcement = \App\Models\BotSetting::where('key', 'web_announcement')->value('value') ?? 'Selamat datang Jurangan!<br>kalau punya akun telegram, langsung saja klik "Login Via Telegram" kalau gak punya, bisa regis dulu.';

        return view('auth.login', compact('todayVisitors', 'announcement'));
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
            $captchaResponse = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $turnstileSecret,
                'response' => $request->input('cf-turnstile-response'),
                'remoteip' => $request->ip(),
            ]);

            if (!$captchaResponse->json('success')) {
                return back()->withErrors([
                    'login' => 'Verifikasi captcha (Turnstile) gagal. Silakan coba lagi.',
                ])->onlyInput('login');
            }
        }

        $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [
            $loginType => $request->login,
            'password' => $request->password,
        ];

        $remember = $request->has('remember');

        // Rate Limiter key: using IP + login identifier
        $throttleKey = Str::lower($request->input('login')) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'login' => 'Terlalu banyak percobaan login. Akun Anda diblokir sementara selama ' . ceil($seconds / 60) . ' menit.',
            ])->onlyInput('login');
        }

        if (Auth::attempt($credentials, $remember)) {
            RateLimiter::clear($throttleKey);
            $user = Auth::user();

            $this->recordLoginLog($request, true);

            // Check if 2FA is enabled
            if ($user->two_factor_enabled && $user->telegram_id) {
                // Generate 6-digit OTP
                $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
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
                        // Log but don't block login
                        \Illuminate\Support\Facades\Log::warning('Failed to send 2FA code via Telegram', ['error' => $e->getMessage()]);
                    }
                }

                // Store user ID in session for 2FA verification, then logout
                $userId = $user->id;
                Auth::logout();
                $request->session()->put('2fa_user_id', $userId);
                $request->session()->put('2fa_remember', $remember);

                return redirect()->route('auth.two-factor');
            }

            // Record login IP country
            $this->recordLoginCountry($user, $request);

            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($throttleKey, 300); // 300 seconds = 5 minutes
        
        $this->recordLoginLog($request, false);

        $user = User::where($loginType, $request->login)->first();
        if ($user) {
            // Notifikasi ke user bersangkutan
            $user->notify(new \App\Notifications\FailedLoginNotification($request->ip()));
        }

        return back()->withErrors([
            'login' => 'Username/Email atau password salah.',
        ])->onlyInput('login');
    }

    /**
     * Proses Pendaftaran Akun Baru.
     */
    public function register(Request $request)
    {
        $rules = [
            'full_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'nullable|string|email|max:255|unique:users',
            'telegram_id' => 'nullable|integer|unique:users',
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
            $captchaResponse = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $turnstileSecret,
                'response' => $request->input('cf-turnstile-response'),
                'remoteip' => $request->ip(),
            ]);

            if (!$captchaResponse->json('success')) {
                return back()->withErrors([
                    'username' => 'Verifikasi captcha (Turnstile) gagal. Silakan coba lagi.',
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

        $user = User::create([
            'full_name' => $request->full_name,
            'username' => $request->username,
            'email' => $request->email,
            'telegram_id' => $request->telegram_id,
            'password' => Hash::make($request->password),
            'role' => 'customer',
            'registration_ip' => $ip,
            'is_suspended' => $shouldSuspend,
            'suspension_reason' => $shouldSuspend ? 'Spam registrasi terdeteksi dari IP ini.' : null,
            'last_seen_at' => now(),
        ]);

        if ($shouldSuspend) {
            return redirect()->route('login')->with('error', 'Registrasi ditolak. Aktivitas mencurigakan (spam) terdeteksi. Semua akun dari IP Anda telah ditangguhkan.');
        }

        Auth::login($user);

        return redirect()->route('dashboard')->with('success', 'Pendaftaran berhasil. Selamat datang!');
    }

    /**
     * Set atau Update Password untuk user yang sedang login (khususnya yang tadinya cuma login via Telegram).
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string', \Illuminate\Validation\Rules\Password::min(8)->letters()->numbers()->symbols(), 'confirmed'],
        ]);

        $user = Auth::user();
        $user->password = Hash::make($request->password);
        $user->save();

        return redirect()->back()->with('success', 'Password berhasil diatur! Anda kini bisa login menggunakan Username/Email dan Password ini.');
    }

    /**
     * Tampilkan halaman verifikasi 2FA.
     */
    public function showTwoFactor(Request $request)
    {
        if (!$request->session()->has('2fa_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    /**
     * Verifikasi kode 2FA.
     */
    public function verifyTwoFactor(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $userId = $request->session()->get('2fa_user_id');
        if (!$userId) {
            return redirect()->route('login')->with('error', 'Sesi verifikasi 2FA sudah berakhir.');
        }

        $user = User::find($userId);
        if (!$user) {
            $request->session()->forget(['2fa_user_id', '2fa_remember']);
            return redirect()->route('login')->with('error', 'Pengguna tidak ditemukan.');
        }

        if ($user->two_factor_code !== $request->code) {
            return back()->withErrors(['code' => 'Kode verifikasi salah.']);
        }

        if ($user->two_factor_expires_at && $user->two_factor_expires_at->isPast()) {
            $request->session()->forget(['2fa_user_id', '2fa_remember']);
            return redirect()->route('login')->with('error', 'Kode verifikasi sudah kedaluwarsa. Silakan login ulang.');
        }

        // Clear 2FA code
        $user->update([
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);

        // Login the user
        $remember = $request->session()->get('2fa_remember', false);
        $request->session()->forget(['2fa_user_id', '2fa_remember']);
        Auth::login($user, $remember);
        
        // Record login IP country
        $this->recordLoginCountry($user, $request);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Toggle 2FA setting (dari halaman profil).
     */
    public function toggleTwoFactor(Request $request)
    {
        $user = Auth::user();

        // User harus punya telegram_id dan password untuk mengaktifkan 2FA
        if (!$user->telegram_id) {
            return redirect()->back()->with('error', 'Anda harus menautkan akun Telegram terlebih dahulu untuk mengaktifkan 2FA.');
        }

        if (!$user->password) {
            return redirect()->back()->with('error', 'Anda harus mengatur password terlebih dahulu untuk mengaktifkan 2FA.');
        }

        $user->update([
            'two_factor_enabled' => !$user->two_factor_enabled,
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);

        $status = $user->two_factor_enabled ? 'diaktifkan' : 'dinonaktifkan';
        return redirect()->back()->with('success', "Verifikasi dua langkah (2FA) berhasil {$status}.");
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
     * Record login attempt log.
     */
    private function recordLoginLog(Request $request, $isSuccessful)
    {
        $ip = $request->ip();
        $userAgent = $request->header('User-Agent');
        
        $deviceType = 'Desktop';
        if (preg_match('/(Mobile|Android|iPhone|iPad)/i', $userAgent)) {
            $deviceType = 'Smartphone/Tablet';
        }

        $browser = 'Unknown';
        if (preg_match('/Chrome/i', $userAgent)) $browser = 'Chrome';
        elseif (preg_match('/Firefox/i', $userAgent)) $browser = 'Firefox';
        elseif (preg_match('/Safari/i', $userAgent)) $browser = 'Safari';
        elseif (preg_match('/Edge/i', $userAgent)) $browser = 'Edge';

        $location = 'Local / Unknown';
        if ($ip && $ip !== '127.0.0.1' && $ip !== '::1' && !str_starts_with($ip, '192.168.') && !str_starts_with($ip, '10.')) {
            try {
                $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}");
                if ($response->successful() && $response->json('status') === 'success') {
                    $location = ($response->json('city') ?? '') . ', ' . ($response->json('country') ?? 'Unknown');
                }
            } catch (\Exception $e) {
                // ignore
            }
        }

        LoginLog::create([
            'ip_address' => $ip,
            'username_or_email' => $request->input('login'),
            'is_successful' => $isSuccessful,
            'user_agent' => $userAgent,
            'device_type' => $deviceType,
            'browser' => $browser,
            'location' => trim($location, ', '),
        ]);
    }
}
