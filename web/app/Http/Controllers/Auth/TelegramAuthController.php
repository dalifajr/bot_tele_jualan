<?php

namespace App\Http\Controllers\Auth;

use App\Models\TelegramLoginToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;

class TelegramAuthController extends Controller
{
    /**
     * Show login page.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Generate a login token and redirect user to Telegram bot deep-link.
     */
    public function requestLogin(Request $request)
    {
        $botUsername = config('telegram.bot_username');

        if (empty($botUsername)) {
            return back()->with('error', 'Bot Telegram belum dikonfigurasi.');
        }

        // Create pending login token (must be < 64 chars total with prefix for Telegram start param)
        $token = Str::random(40);
        $ttlMinutes = (int) config('telegram.login_token_ttl_minutes', 5);

        TelegramLoginToken::create([
            'token' => $token,
            'status' => 'pending',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        // Redirect to Telegram deep-link
        $telegramUrl = "https://t.me/{$botUsername}?start=weblogin_{$token}";

        return redirect()->away($telegramUrl);
    }

    /**
     * Handle callback from the one-time login link sent by the bot.
     */
    public function callback(Request $request)
    {
        $linkToken = $request->query('token');

        if (empty($linkToken)) {
            return redirect()->route('login')->with('error', 'Token login tidak valid.');
        }

        // Find verified, non-expired, unused token
        $record = TelegramLoginToken::where('link_token', $linkToken)
            ->where('status', 'verified')
            ->where('link_expires_at', '>', now())
            ->whereNull('used_at')
            ->first();

        if (!$record) {
            return redirect()->route('login')->with('error', 'Link login sudah kedaluwarsa atau sudah digunakan. Silakan coba lagi.');
        }

        // Mark token as used
        $record->update([
            'status' => 'used',
            'used_at' => now(),
        ]);

        // Find or create user
        $user = User::where('telegram_id', $record->telegram_id)->first();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Akun Telegram tidak ditemukan. Pastikan Anda sudah pernah berinteraksi dengan bot.');
        }

        // Login with remember_me (30 days)
        $rememberDays = (int) config('telegram.remember_me_days', 30);
        Auth::login($user, true);

        // Set custom cookie lifetime
        config(['session.lifetime' => $rememberDays * 24 * 60]);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda berhasil logout.');
    }
}
