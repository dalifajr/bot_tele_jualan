<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

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

        if (!app()->environment('testing')) {
            $rules['cf-turnstile-response'] = 'required|string';
        }

        $request->validate($rules);

        // Validate Captcha
        if (!app()->environment('testing')) {
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

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
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
            'password' => 'required|string|min:6|confirmed',
        ];

        if (!app()->environment('testing')) {
            $rules['cf-turnstile-response'] = 'required|string';
        }

        $request->validate($rules);

        // Validate Captcha
        if (!app()->environment('testing')) {
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
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = Auth::user();
        $user->password = Hash::make($request->password);
        $user->save();

        return redirect()->back()->with('success', 'Password berhasil diatur! Anda kini bisa login menggunakan Username/Email dan Password ini.');
    }
}
