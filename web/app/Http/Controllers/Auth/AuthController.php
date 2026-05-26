<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

        return view('auth.login');
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
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

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
        $request->validate([
            'full_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'nullable|string|email|max:255|unique:users',
            'telegram_id' => 'nullable|integer|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'full_name' => $request->full_name,
            'username' => $request->username,
            'email' => $request->email,
            'telegram_id' => $request->telegram_id,
            'password' => Hash::make($request->password),
            'role' => 'customer',
            'last_seen_at' => now(),
        ]);

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
