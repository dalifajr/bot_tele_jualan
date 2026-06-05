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
        $botUsername = config('telegram.bot_username');
        $ttlMinutes = config('telegram.login_token_ttl_minutes', 5);

        // Generate shorter token (8 chars) so Telegram deep link doesn't reject it
        $token = \Illuminate\Support\Str::random(8);

        TelegramLoginToken::create([
            'token' => $token,
            'status' => 'pending',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        // Redirect to Telegram deep-link (using ?text= to ensure it pre-fills the message box, avoiding Telegram Desktop bugs)
        $telegramUrl = "https://t.me/{$botUsername}?text=/start%20weblogin_{$token}";

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

    /**
     * Authenticate session from Telegram WebApp initData.
     */
    public function webAppLogin(Request $request)
    {
        $initData = $request->input('init_data');
        if (empty($initData)) {
            return response()->json(['success' => false, 'message' => 'Init data kosong.'], 400);
        }

        $botToken = config('telegram.bot_token');
        if (empty($botToken)) {
            return response()->json(['success' => false, 'message' => 'Token bot belum dikonfigurasi di server.'], 500);
        }

        // Parse query string
        parse_str($initData, $params);
        if (!isset($params['hash'])) {
            return response()->json(['success' => false, 'message' => 'Hash tidak ditemukan.'], 400);
        }
        
        $hash = $params['hash'];
        unset($params['hash']);

        // Sort keys alphabetically
        ksort($params);
        $dataCheckArr = [];
        foreach ($params as $key => $value) {
            $dataCheckArr[] = "{$key}={$value}";
        }
        $dataCheckString = implode("\n", $dataCheckArr);

        // Verification signature key is HMAC-SHA256 of botToken with key "WebAppData"
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $signature = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (!hash_equals($signature, $hash)) {
            return response()->json(['success' => false, 'message' => 'Verifikasi signature Telegram gagal.'], 403);
        }

        // Verify auth date (limit to 30 days to prevent replay attacks)
        if (isset($params['auth_date'])) {
            $authDate = (int) $params['auth_date'];
            if (time() - $authDate > 86400 * 30) {
                return response()->json(['success' => false, 'message' => 'Sesi Telegram WebApp kedaluwarsa.'], 403);
            }
        }

        // Parse user field
        if (!isset($params['user'])) {
            return response()->json(['success' => false, 'message' => 'Data user Telegram tidak lengkap.'], 400);
        }

        $telegramUser = json_decode($params['user'], true);
        if (!$telegramUser || !isset($telegramUser['id'])) {
            return response()->json(['success' => false, 'message' => 'Data user Telegram tidak valid.'], 400);
        }

        $telegramId = $telegramUser['id'];

        // Find or create user
        $user = User::where('telegram_id', $telegramId)->first();
        if (!$user) {
            $username = $telegramUser['username'] ?? ('tg_' . $telegramId);
            $fullName = trim(($telegramUser['first_name'] ?? '') . ' ' . ($telegramUser['last_name'] ?? ''));
            if (empty($fullName)) {
                $fullName = $username;
            }

            // Ensure username uniqueness
            $baseUsername = $username;
            $counter = 1;
            while (User::where('username', $username)->exists()) {
                $username = $baseUsername . $counter;
                $counter++;
            }

            $user = User::create([
                'telegram_id' => $telegramId,
                'username' => $username,
                'full_name' => $fullName,
                'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)),
                'role' => 'customer',
                'wallet_balance' => 0,
                'last_seen_at' => now(),
            ]);
        }

        if ($user->is_suspended) {
            return response()->json(['success' => false, 'message' => 'Akun Anda ditangguhkan oleh Admin.'], 403);
        }

        // Login session
        Auth::login($user, true);
        
        $rememberDays = (int) config('telegram.remember_me_days', 30);
        config(['session.lifetime' => $rememberDays * 24 * 60]);
        $request->session()->regenerate();

        return response()->json(['success' => true]);
    }
}
