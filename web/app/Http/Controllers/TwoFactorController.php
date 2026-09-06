<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TwoFactorService;

class TwoFactorController extends Controller
{
    /**
     * Display the 2FA Code Generator view.
     */
    public function index(Request $request)
    {
        $serverTime = time();
        $remainingSeconds = TwoFactorService::getRemainingSeconds($serverTime);

        return view('tools.2fa-generator', compact('serverTime', 'remainingSeconds'));
    }

    /**
     * AJAX endpoint to generate 2FA code for a single secret.
     */
    public function generateAjax(Request $request)
    {
        $request->validate([
            'secret' => 'required|string',
        ]);

        $rawSecret = $request->input('secret');
        $cleanSecret = TwoFactorService::extractSecretFromOtpAuth($rawSecret);
        $code = TwoFactorService::generateOtp($cleanSecret);

        if ($code === null) {
            return response()->json([
                'success' => false,
                'message' => __('Format secret key 2FA tidak valid. Pastikan format Base32 benar.'),
            ], 422);
        }

        $serverTime = time();
        $remaining = TwoFactorService::getRemainingSeconds($serverTime);

        return response()->json([
            'success' => true,
            'code' => $code,
            'remaining' => $remaining,
            'server_time' => $serverTime,
        ]);
    }

    /**
     * AJAX endpoint to batch generate 2FA codes from multiline/pipe text.
     */
    public function batchGenerateAjax(Request $request)
    {
        $request->validate([
            'accounts' => 'required|string',
        ]);

        $rawText = $request->input('accounts');
        $lines = preg_split("/\r\n|\n|\r/", $rawText);
        $results = [];
        $serverTime = time();
        $remaining = TwoFactorService::getRemainingSeconds($serverTime);

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }

            $detected = TwoFactorService::detect2faSecrets($trimmed);
            if (!empty($detected)) {
                $secretInfo = $detected[0];
                $secret = $secretInfo['secret'];
                $code = TwoFactorService::generateOtp($secret, $serverTime);

                $results[] = [
                    'raw_line' => $trimmed,
                    'secret' => $secret,
                    'code' => $code,
                    'valid' => true,
                ];
            } else {
                // If line itself might be a standalone raw Base32 secret (standard lengths: 16, 26, 32, 52, 64)
                $trimmedClean = strtoupper(trim($trimmed));
                $candidate = preg_replace('/[^A-Z2-7]/', '', $trimmedClean);
                if ($candidate === $trimmedClean && in_array(strlen($candidate), [16, 26, 32, 52, 64])) {
                    $code = TwoFactorService::generateOtp($candidate, $serverTime);
                    $results[] = [
                        'raw_line' => $trimmed,
                        'secret' => $candidate,
                        'code' => $code,
                        'valid' => true,
                    ];
                } else {
                    $results[] = [
                        'raw_line' => $trimmed,
                        'secret' => null,
                        'code' => null,
                        'valid' => false,
                        'error' => '2FA Secret tidak terdeteksi',
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'items' => $results,
            'remaining' => $remaining,
            'server_time' => $serverTime,
        ]);
    }
}
