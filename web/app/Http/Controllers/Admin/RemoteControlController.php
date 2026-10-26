<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RemoteControlController extends Controller
{
    /**
     * Tampilan Utama Remote Control Android Root di Dashboard Admin Web Jualan
     */
    public function index(Request $request)
    {
        // Konfigurasi Gateway Relay (Bisa disetel di .env VPS)
        $relayHttpUrl = config('services.remote_control.http_url', env('REMOTE_RELAY_HTTP_URL', 'http://127.0.0.1:8080'));
        $defaultWsScheme = ($request->isSecure() || $request->header('X-Forwarded-Proto') === 'https') ? 'wss://' : 'ws://';
        $defaultWsHost = ($request->isSecure() || $request->header('X-Forwarded-Proto') === 'https') ? $request->getHost() : $request->getHost() . ':8080';
        $relayWsUrl = config('services.remote_control.ws_url', env('REMOTE_RELAY_WS_URL', $defaultWsScheme . $defaultWsHost));
        $adminKey = config('services.remote_control.admin_key', env('ADMIN_SECRET_KEY', 'ADMIN_SECRET_PASS_2026'));

        $devices = [];
        try {
            $response = Http::timeout(2)->get("{$relayHttpUrl}/api/devices");
            if ($response->successful()) {
                $devices = $response->json('devices', []);
            }
        } catch (\Exception $e) {
            Log::warning("Gagal menghubungi Remote Relay Gateway: " . $e->getMessage());
        }

        return view('admin.remote_control.index', compact('devices', 'relayHttpUrl', 'relayWsUrl', 'adminKey'));
    }

    /**
     * Proxy API untuk memeriksa daftar perangkat online secara asinkron
     */
    public function getDevices()
    {
        $relayHttpUrl = config('services.remote_control.http_url', env('REMOTE_RELAY_HTTP_URL', 'http://127.0.0.1:8080'));
        try {
            $response = Http::timeout(2)->get("{$relayHttpUrl}/api/devices");
            if ($response->successful()) {
                return response()->json($response->json());
            }
        } catch (\Exception $e) {
            // Relay offline
        }

        return response()->json(['devices' => []]);
    }
}
