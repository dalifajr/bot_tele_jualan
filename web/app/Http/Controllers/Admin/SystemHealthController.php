<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class SystemHealthController extends Controller
{
    public function __construct(protected SystemHealthService $healthService)
    {
    }

    public function index(Request $request)
    {
        $data = $this->healthService->buildDashboardData($request);
        return view('admin.system.health', $data);
    }

    public function pingTelegram()
    {
        $res = $this->healthService->pingTelegram();
        return response()->json([
            'ok' => $res['ok'],
            'latency_ms' => $res['latency_ms'] ?? null,
            'message' => $res['message'],
        ], $res['status'] ?? 200);
    }

    public function pingVpn()
    {
        $res = $this->healthService->pingVpn();
        return response()->json([
            'ok' => $res['ok'],
            'latency_ms' => $res['latency_ms'] ?? null,
            'message' => $res['message'],
        ], $res['status'] ?? 200);
    }

    public function flushCaches(Request $request)
    {
        Cache::forget('nav:global_settings');
        Cache::forget('firewall:effective_rules');

        return $this->redirectToTab($request)
            ->with('success', __('Cache navigasi & aturan firewall berhasil disegarkan.'));
    }

    public function runArtisan(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|string|in:' . implode(',', array_keys(SystemHealthService::ARTISAN_ACTIONS)),
        ]);

        $res = $this->healthService->runArtisanAction($validated['action']);

        if ($request->expectsJson()) {
            return response()->json($res, $res['ok'] ? 200 : 500);
        }

        return $this->redirectToTab($request, 'pemeliharaan')
            ->with($res['ok'] ? 'success' : 'error', $res['message'])
            ->with('artisan_output', $res['output'] ?? '');
    }

    public function clearLogs(Request $request)
    {
        $res = $this->healthService->clearLaravelLogs();

        return $this->redirectToTab($request, 'pemeliharaan')
            ->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function downloadLog()
    {
        $logPath = storage_path('logs/laravel.log');
        if (!file_exists($logPath)) {
            return redirect()->route('admin.system.health', ['tab' => 'log'])
                ->with('error', __('File laravel.log tidak ditemukan.'));
        }

        return response()->download($logPath, 'laravel-' . now()->format('Ymd-His') . '.log');
    }

    public function fetchLogs(Request $request)
    {
        $source = (string) $request->query('source', 'laravel');
        $lines = (int) $request->query('lines', 100);
        $level = (string) $request->query('level', 'all');

        return response()->json($this->healthService->readLogSource($source, $lines, $level));
    }

    public function pruneBackups(Request $request)
    {
        $validated = $request->validate([
            'keep' => 'required|integer|min:1|max:100',
        ]);

        $res = $this->healthService->pruneOpsBackups((int) $validated['keep']);

        return $this->redirectToTab($request, 'pemeliharaan')
            ->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function toggleMaintenance(Request $request)
    {
        $res = $this->healthService->toggleMaintenanceMode();

        return $this->redirectToTab($request, 'pemeliharaan')
            ->with('success', $res['message']);
    }

    public function serviceAction(Request $request)
    {
        $validated = $request->validate([
            'unit' => 'required|string|in:' . implode(',', array_keys(SystemHealthService::SERVICE_CATALOG)),
            'action' => 'required|string|in:' . implode(',', SystemHealthService::ALLOWED_SERVICE_ACTIONS),
        ]);

        $res = $this->healthService->runServiceAction($validated['unit'], $validated['action']);

        if ($request->expectsJson()) {
            return response()->json($res, $res['ok'] ? 200 : 422);
        }

        return $this->redirectToTab($request, 'layanan')
            ->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function websiteRestart(Request $request)
    {
        $res = $this->healthService->triggerVpsBackgroundTask('website-restart');

        return $this->redirectToTab($request, 'layanan')
            ->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function toggleBotCommands(Request $request)
    {
        $validated = $request->validate([
            'mode' => 'required|string|in:disable,enable',
        ]);

        $res = $this->healthService->toggleBotCommands($validated['mode'] === 'disable');

        return $this->redirectToTab($request, 'layanan')
            ->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function checkUpdate()
    {
        $res = $this->healthService->checkGitUpdate();
        return response()->json($res, $res['ok'] ? 200 : 500);
    }

    public function runUpdate(Request $request)
    {
        $res = $this->healthService->triggerVpsBackgroundTask('update');

        return $this->redirectToTab($request, 'pembaruan')
            ->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function runRollback(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
        ]);

        $admin = Auth::user();
        if (!$admin || !Hash::check($validated['current_password'], (string) $admin->password)) {
            return $this->redirectToTab($request, 'pembaruan')
                ->with('error', __('Verifikasi kata sandi admin gagal. Rollback dibatalkan.'));
        }

        $res = $this->healthService->triggerVpsBackgroundTask('rollback');

        return $this->redirectToTab($request, 'pembaruan')
            ->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function runRepair(Request $request)
    {
        $res = $this->healthService->triggerVpsBackgroundTask('repair');

        return $this->redirectToTab($request, 'layanan')
            ->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function taskStatus()
    {
        return response()->json($this->healthService->readTaskStatus());
    }

    public function updateTurnstile(Request $request)
    {
        $validated = $request->validate([
            'site_key' => 'required|string|min:6|max:200',
            'secret_key' => 'required|string|min:6|max:200',
        ]);

        $res = $this->healthService->updateTurnstileKeys($validated['site_key'], $validated['secret_key']);

        return $this->redirectToTab($request, 'pembaruan')
            ->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function resetAdminPassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'target_admin_id' => 'required|integer|exists:users,id',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $admin = Auth::user();
        if (!$admin || !Hash::check($validated['current_password'], (string) $admin->password)) {
            return $this->redirectToTab($request, 'pembaruan')
                ->with('error', __('Kata sandi admin Anda saat ini tidak cocok.'));
        }

        $res = $this->healthService->resetAdminPassword((int) $validated['target_admin_id'], $validated['new_password']);

        return $this->redirectToTab($request, 'pembaruan')
            ->with($res['ok'] ? 'success' : 'error', $res['message']);
    }

    private function redirectToTab(Request $request, ?string $fallbackTab = null)
    {
        $tab = (string) $request->input('active_tab', $fallbackTab ?? '');
        if ($tab !== '' && in_array($tab, SystemHealthService::VALID_TABS, true)) {
            return redirect()->route('admin.system.health', ['tab' => $tab]);
        }

        return redirect()->route('admin.system.health');
    }
}
