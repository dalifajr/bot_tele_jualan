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

        return redirect()->back()->with('success', 'Profil berhasil diperbarui!');
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

        return redirect()->back()->with('success', 'Akun Telegram berhasil dilepas.');
    }
}
