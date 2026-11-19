<?php

namespace App\Services;

class TwoFactorService
{
    /**
     * Standard RFC 4648 Base32 Alphabet
     */
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Decode a Base32 string to binary data.
     * Handles case-insensitivity, spaces, dashes, and '=' padding.
     */
    public static function base32Decode(string $input): string
    {
        // Normalize: uppercase, remove spaces, dashes, and non-base32 chars
        $clean = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $input));
        if (empty($clean)) {
            return '';
        }

        $binaryString = '';
        $len = strlen($clean);
        for ($i = 0; $i < $len; $i++) {
            $char = $clean[$i];
            $val = strpos(self::BASE32_ALPHABET, $char);
            if ($val === false) {
                continue;
            }
            $binaryString .= sprintf('%05b', $val);
        }

        $bytes = '';
        $totalBits = strlen($binaryString);
        for ($i = 0; $i + 8 <= $totalBits; $i += 8) {
            $bytes .= chr(bindec(substr($binaryString, $i, 8)));
        }

        return $bytes;
    }

    /**
     * Generate standard 6-digit TOTP (RFC 6238) for a given Base32 secret.
     *
     * @param string $secret Base32 secret key or otpauth:// URI
     * @param int|null $timestamp Unix timestamp (defaults to current time)
     * @param int $period Time step in seconds (standard is 30)
     * @param int $digits Number of digits (standard is 6)
     * @return string|null 6-digit numeric string or null if secret is invalid
     */
    public static function generateOtp(string $secret, ?int $timestamp = null, int $period = 30, int $digits = 6): ?string
    {
        // Extract secret if it's an otpauth:// URL
        $cleanSecret = self::extractSecretFromOtpAuth($secret);
        $binaryKey = self::base32Decode($cleanSecret);

        if (empty($binaryKey)) {
            return null;
        }

        $time = $timestamp ?? time();
        $counter = (int) floor($time / $period);

        // 8-byte big-endian binary counter
        $packedTime = pack('N*', 0) . pack('N*', $counter);

        // HMAC-SHA1
        $hash = hash_hmac('sha1', $packedTime, $binaryKey, true);

        // Dynamic truncation
        $offset = ord($hash[19]) & 0x0F;
        $binaryCode = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        $otp = $binaryCode % (10 ** $digits);
        return str_pad((string) $otp, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a cryptographically secure Base32 TOTP secret (RFC 4648).
     */
    public static function generateBase32Secret(int $length = 32): string
    {
        $secret = '';
        $maxIndex = strlen(self::BASE32_ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, $maxIndex)];
        }
        return $secret;
    }

    /**
     * Verify a 6-digit TOTP code against a Base32 secret with optional clock-skew window (±steps).
     */
    public static function verifyOtp(string $secret, string $code, int $window = 1, int $period = 30, int $digits = 6, ?int $timestamp = null): bool
    {
        $normalizedCode = preg_replace('/\s+/', '', trim($code));
        if (!preg_match('/^\d{' . $digits . '}$/', $normalizedCode)) {
            return false;
        }

        $now = $timestamp ?? time();
        for ($offset = -$window; $offset <= $window; $offset++) {
            $candidate = self::generateOtp($secret, $now + ($offset * $period), $period, $digits);
            if ($candidate !== null && hash_equals($candidate, $normalizedCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build standard otpauth://totp URI compatible with Google Authenticator, Authy, 1Password, etc.
     */
    public static function buildOtpAuthUri(string $issuer, string $accountName, string $secret): string
    {
        $cleanIssuer = trim($issuer) ?: 'Store';
        $cleanAccount = trim($accountName) ?: 'account';
        $label = rawurlencode($cleanIssuer . ':' . $cleanAccount);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            $label,
            rawurlencode($secret),
            rawurlencode($cleanIssuer)
        );
    }

    /**
     * Generate human-readable single-use recovery codes (format: XXXX-XXXX).
     */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $partA = '';
            $partB = '';
            for ($j = 0; $j < 4; $j++) {
                $partA .= $alphabet[random_int(0, $max)];
                $partB .= $alphabet[random_int(0, $max)];
            }
            $codes[] = "{$partA}-{$partB}";
        }

        return $codes;
    }

    /**
     * Hash recovery codes with HMAC-SHA256 keyed by APP_KEY for safe database storage.
     */
    public static function hashRecoveryCodes(array $plainCodes): array
    {
        $key = (string) config('app.key', 'fallback-app-key');
        return array_values(array_map(function (string $code) use ($key) {
            $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($code)));
            return hash_hmac('sha256', $normalized, $key);
        }, $plainCodes));
    }

    /**
     * Verify and consume a single-use recovery code. Returns remaining hashed codes if valid, or null if invalid.
     */
    public static function consumeRecoveryCode(array $hashedCodes, string $inputCode): ?array
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($inputCode)));
        if (strlen($normalized) < 8) {
            return null;
        }

        $key = (string) config('app.key', 'fallback-app-key');
        $candidateHash = hash_hmac('sha256', $normalized, $key);

        foreach ($hashedCodes as $index => $storedHash) {
            if (is_string($storedHash) && hash_equals($storedHash, $candidateHash)) {
                unset($hashedCodes[$index]);
                return array_values($hashedCodes);
            }
        }

        return null;
    }

    /**
     * Get remaining seconds for the current 30s window.
     */
    public static function getRemainingSeconds(?int $timestamp = null, int $period = 30): int
    {
        $time = $timestamp ?? time();
        return $period - ($time % $period);
    }

    /**
     * Extract secret from otpauth:// URI if present, otherwise clean the secret.
     */
    public static function extractSecretFromOtpAuth(string $text): string
    {
        $text = trim($text);
        if (stripos($text, 'otpauth://') === 0) {
            $parsed = parse_url($text);
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $queryParams);
                if (!empty($queryParams['secret'])) {
                    return trim($queryParams['secret']);
                }
            }
        }
        return $text;
    }

    /**
     * Detect all 2FA secrets present in raw credentials text.
     * Returns an array of detected secrets with metadata.
     */
    public static function detect2faSecrets(string $rawText): array
    {
        $results = [];
        $lines = preg_split("/\r\n|\n|\r/", $rawText);

        foreach ($lines as $lineIndex => $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine)) {
                continue;
            }

            // 1. Check for otpauth:// URI
            if (preg_match('/otpauth:\/\/totp\/[^\s]+/i', $trimmedLine, $otpMatch)) {
                $secret = self::extractSecretFromOtpAuth($otpMatch[0]);
                $cleanSecret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret));
                if (strlen($cleanSecret) >= 8) {
                    $results[] = [
                        'type' => 'otpauth',
                        'raw_line' => $trimmedLine,
                        'line_index' => $lineIndex,
                        'secret' => $cleanSecret,
                        'matched_text' => $otpMatch[0],
                    ];
                    continue;
                }
            }

            // 2. Check for explicit key-value: F2A, 2FA, TOTP, Authenticator, Two-Factor, Secret, Key
            if (preg_match('/^(.*?)(f2a|2fa|totp|authenticator|two[\s_-]*factor|secret)\s*[:=]\s*([A-Za-z2-7\s=-]{8,64})(.*)$/i', $trimmedLine, $matches)) {
                $rawSecretCandidate = $matches[3];
                $cleanSecret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $rawSecretCandidate));

                if (strlen($cleanSecret) >= 8) {
                    $results[] = [
                        'type' => 'key_value',
                        'prefix' => trim($matches[1]),
                        'label' => trim($matches[2]),
                        'raw_line' => $trimmedLine,
                        'line_index' => $lineIndex,
                        'secret' => $cleanSecret,
                        'raw_secret' => trim($rawSecretCandidate),
                    ];
                    continue;
                }
            }

            // 3. Check for pipe/colon delimited line: user|pass|2fa_secret
            if (str_contains($trimmedLine, '|') || (substr_count($trimmedLine, ':') >= 2 && !str_contains($trimmedLine, 'http'))) {
                $delimiter = str_contains($trimmedLine, '|') ? '|' : ':';
                $parts = explode($delimiter, $trimmedLine);
                foreach ($parts as $partIdx => $part) {
                    $candidate = trim($part);
                    // Don't consider email as secret
                    if (str_contains($candidate, '@') || strlen($candidate) < 16 || strlen($candidate) > 64) {
                        continue;
                    }
                    $cleanCandidate = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $candidate));
                    // Base32 standard secret is usually 16, 26, 32, 52, or 64 characters
                    if (strlen($cleanCandidate) === strlen($candidate) && strlen($cleanCandidate) >= 16) {
                        $results[] = [
                            'type' => 'delimited',
                            'raw_line' => $trimmedLine,
                            'line_index' => $lineIndex,
                            'part_index' => $partIdx,
                            'secret' => $cleanCandidate,
                            'raw_secret' => $candidate,
                        ];
                        break; // Match first valid candidate per line
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Check whether raw text contains any detectable 2FA secret.
     */
    public static function has2faSecret(string $rawText): bool
    {
        return !empty(self::detect2faSecrets($rawText));
    }

    /**
     * Format raw credentials with generated 2FA code right below F2A / 2FA lines.
     * Sesuai permintaan user:
     * "kode hasil generate F2A akan ditampilkan pada bagian bawah F2A: XXXXX dengan nama F2A Code: XXXXXX"
     *
     * @param string $rawText
     * @param bool $asHtml If true, returns HTML with interactive countdown & copy button
     * @return string
     */
    public static function renderWith2fa(string $rawText, bool $asHtml = true): string
    {
        $rawText = trim($rawText);
        if (empty($rawText)) {
            return '';
        }

        $secrets = self::detect2faSecrets($rawText);
        $lines = preg_split("/\r\n|\n|\r/", $rawText);
        $cleanLines = array_map('trim', $lines);

        if (empty($secrets)) {
            return $asHtml ? nl2br(e(implode("\n", $cleanLines))) : implode("\n", $cleanLines);
        }

        $outputLines = [];
        $secretLineMap = [];

        foreach ($secrets as $item) {
            $secretLineMap[$item['line_index']] = $item;
        }

        foreach ($cleanLines as $idx => $line) {
            $outputLines[] = $asHtml ? e($line) : $line;

            if (isset($secretLineMap[$idx])) {
                $item = $secretLineMap[$idx];
                $secret = $item['secret'];
                $code = self::generateOtp($secret);
                $remaining = self::getRemainingSeconds();

                if ($asHtml) {
                    $outputLines[] = '<div class="f2a-code-badge d-inline-flex align-items-center gap-2 px-2 py-1 my-1 rounded bg-success-subtle border border-success-subtle font-monospace text-dark" style="font-size: 0.88rem;" data-totp-container data-totp-secret="' . e($secret) . '">'
                        . '<i class="fas fa-shield-alt text-success"></i>'
                        . '<strong class="text-success-emphasis">F2A Code:</strong> '
                        . '<span class="totp-code fw-bold text-dark fs-6" style="letter-spacing: 1px;">' . ($code ?? '------') . '</span>'
                        . '<span class="totp-timer badge bg-success text-white py-0 px-1 rounded-pill" style="font-size: 0.72rem;">' . $remaining . 's</span>'
                        . '<button type="button" class="btn btn-sm btn-link p-0 text-success ms-1 btn-copy-totp" title="Salin Kode 2FA" onclick="copyTotpCode(this, \'' . e($secret) . '\')">'
                        . '<i class="far fa-copy"></i>'
                        . '</button>'
                        . '</div>';
                } else {
                    $outputLines[] = "F2A Code: " . ($code ?? '------') . " ({$remaining}s)";
                }
            }
        }

        return trim(implode($asHtml ? "<br>" : "\n", $outputLines));
    }
}
