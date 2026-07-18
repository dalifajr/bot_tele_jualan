<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\TelegramLinkToken;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function index()
    {
        return view('profile', ['user' => Auth::user()]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $rules = [
            'full_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'telegram_id' => 'nullable|integer|unique:users,telegram_id,' . $user->id,
        ];

        $request->validate($rules);

        $user->update([
            'full_name' => $request->full_name,
            'username' => $request->username,
            'email' => $request->email,
            'telegram_id' => $request->telegram_id,
        ]);

        return redirect()->back()->with('success', __('Profil berhasil diperbarui!'));
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
                'message' => "ID Telegram ini sudah tertaut dengan akun @" . $existingUser->username
            ]);
        }

        return response()->json([
            'available' => true,
            'message' => "ID Telegram tersedia!"
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
        $user->update(['telegram_id' => null]);

        return redirect()->back()->with('success', __('Akun Telegram berhasil dilepas.'));
    }

    public function loginHistory()
    {
        $user = Auth::user();
        
        $loginLogs = \App\Models\LoginLog::where(function($q) use ($user) {
            $q->where('username_or_email', $user->username)
              ->orWhere('username_or_email', $user->email);
        })->orderBy('created_at', 'desc')->paginate(15);

        $admins = \App\Models\User::where('role', 'admin')->get(['id', 'username', 'full_name']);
        
        return view('profile.logins', compact('loginLogs', 'admins'));
    }

    public function blockIp(Request $request)
    {
        $request->validate([
            'ip_address' => 'required|ip',
            'duration' => 'required|integer|in:1,7,30,365'
        ]);
        $ip = $request->ip_address;
        $duration = (int)$request->duration;
        
        $durationText = '';
        if ($duration === 1) {
            $expire = now()->addDay();
            $durationText = '1 hari';
        } elseif ($duration === 7) {
            $expire = now()->addDays(7);
            $durationText = '7 hari';
        } elseif ($duration === 30) {
            $expire = now()->addDays(30);
            $durationText = '30 hari';
        } elseif ($duration === 365) {
            $expire = now()->addYear();
            $durationText = '1 tahun';
        } else {
            $expire = now()->addDay();
            $durationText = '1 hari';
        }
        
        \Illuminate\Support\Facades\Cache::put('blocked_ip:' . $ip, true, $expire);
        
        return back()->with('success', __("IP Address :ip telah diblokir selama :durationText.", ["ip" => $ip, "durationText" => $durationText]));
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
}
