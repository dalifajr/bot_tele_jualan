<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GithubCheckerService
{
    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    /**
     * Build common headers for GitHub requests.
     */
    private function buildHeaders(string $cookie): array
    {
        return [
            'Cookie' => $cookie,
            'User-Agent' => $this->userAgent,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
        ];
    }

    /**
     * Validate if the GitHub cookie session is still active.
     * Accesses github.com and checks for logged-in indicator.
     */
    public function validateCookie(string $cookie): array
    {
        try {
            $response = Http::withHeaders($this->buildHeaders($cookie))
                ->timeout(15)
                ->get('https://github.com/');

            $html = $response->body();

            // Check for logged-in indicator: meta name="user-login"
            if (str_contains($html, 'meta name="user-login"')) {
                preg_match('/meta name="user-login" content="([^"]+)"/', $html, $matches);
                $loggedInAs = $matches[1] ?? 'Unknown';

                return [
                    'valid' => true,
                    'logged_in_as' => $loggedInAs,
                    'message' => "Cookie valid. Login sebagai: {$loggedInAs}",
                ];
            }

            return [
                'valid' => false,
                'logged_in_as' => null,
                'message' => 'Cookie invalid atau expired. Silakan input cookie baru.',
            ];
        } catch (\Exception $e) {
            Log::error('GitHub cookie validation error: ' . $e->getMessage());
            return [
                'valid' => false,
                'logged_in_as' => null,
                'message' => 'Gagal menghubungi GitHub: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check a single GitHub username using two-step process:
     * Step 1: Search for the username on GitHub search
     * Step 2: If found, open profile page and check for PRO badge
     *
     * Returns status: approved, not_approved, suspended, error
     */
    public function checkUsername(string $username, string $cookie): array
    {
        $username = trim($username);

        if (empty($username)) {
            return [
                'username' => $username,
                'result' => 'error',
                'detail' => 'Username kosong',
            ];
        }

        try {
            // ── Step 1: Search for the username ──
            $searchResult = $this->searchUsername($username, $cookie);

            if ($searchResult === 'error') {
                return [
                    'username' => $username,
                    'result' => 'error',
                    'detail' => 'Gagal melakukan pencarian di GitHub',
                ];
            }

            if ($searchResult === 'rate_limited') {
                return [
                    'username' => $username,
                    'result' => 'error',
                    'detail' => 'Rate limited oleh GitHub (HTTP 429). Coba tingkatkan delay.',
                ];
            }

            if (!$searchResult) {
                // User not found in search results → SUSPENDED / NOT FOUND
                return [
                    'username' => $username,
                    'result' => 'suspended',
                    'detail' => 'Akun tidak ditemukan saat pencarian (Suspended / Tidak Ada)',
                ];
            }

            // ── Step 2: Open profile and check PRO badge ──
            $profileResult = $this->checkProfileForPro($username, $cookie);

            return $profileResult;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return [
                'username' => $username,
                'result' => 'error',
                'detail' => 'Timeout koneksi ke GitHub',
            ];
        } catch (\Exception $e) {
            Log::error("GitHub check error for {$username}: " . $e->getMessage());
            return [
                'username' => $username,
                'result' => 'error',
                'detail' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Step 1: Search for a username on GitHub.
     * Uses: https://github.com/search?q={username}&type=users
     *
     * @return bool|string  true if found, false if not found, 'error'/'rate_limited' on failure
     */
    private function searchUsername(string $username, string $cookie): bool|string
    {
        try {
            $response = Http::withHeaders($this->buildHeaders($cookie))
                ->timeout(15)
                ->get('https://github.com/search', [
                    'q' => $username,
                    'type' => 'users',
                ]);

            $statusCode = $response->status();

            if ($statusCode === 429) {
                return 'rate_limited';
            }

            if ($statusCode !== 200) {
                return 'error';
            }

            $html = $response->body();

            // ── Try parsing the modern GitHub embedded JSON payload ──
            if (preg_match('/<script[^>]*data-target="react-app.embeddedData"[^>]*>(.*?)<\/script>/s', $html, $scriptMatches)) {
                $jsonData = json_decode(trim($scriptMatches[1]), true);
                if (isset($jsonData['payload']['results']) && is_array($jsonData['payload']['results'])) {
                    foreach ($jsonData['payload']['results'] as $res) {
                        if (isset($res['login']) && strcasecmp($res['login'], $username) === 0) {
                            return true;
                        }
                    }
                }
            }

            // Check if the exact username appears in the search results
            // Looking for a link to the user's profile in the results
            // The search result contains links like href="/username" or href="https://github.com/username"
            $usernameLC = strtolower($username);

            // Check for exact match in search results
            // Pattern: look for the username in search result entries
            if (preg_match('/href="\/' . preg_quote($username, '/') . '"/i', $html)) {
                return true;
            }

            // Also check for data-testid patterns or user card patterns
            if (preg_match('/\/search.*type=users/i', $html) &&
                stripos($html, $username) !== false) {
                // Verify it's an actual user link, not just query echo
                // Check for avatar link pattern which contains the username
                if (preg_match('/avatars\.githubusercontent\.com\/u\/\d+/i', $html) &&
                    preg_match('/href="\/[^"]*' . preg_quote($username, '/') . '[^"]*"/i', $html)) {
                    return true;
                }
            }

            // Check the result count - if "0 results" then user not found
            if (preg_match('/(\d+)\s+results?\s/i', $html, $matches)) {
                $resultCount = (int) $matches[1];
                if ($resultCount === 0) {
                    return false;
                }
                // If there are results, do a more lenient check
                if ($resultCount > 0 && stripos($html, $username) !== false) {
                    return true;
                }
            }

            return false;

        } catch (\Exception $e) {
            Log::error("GitHub search error for {$username}: " . $e->getMessage());
            return 'error';
        }
    }

    /**
     * Step 2: Open profile page and check for PRO badge.
     * Uses: https://github.com/{username}
     */
    private function checkProfileForPro(string $username, string $cookie): array
    {
        try {
            $response = Http::withHeaders($this->buildHeaders($cookie))
                ->timeout(15)
                ->get("https://github.com/{$username}");

            $statusCode = $response->status();
            $html = $response->body();

            // If profile returns 404, the account was removed between search and profile access
            if ($statusCode === 404) {
                return [
                    'username' => $username,
                    'result' => 'suspended',
                    'detail' => 'Profil tidak dapat diakses (HTTP 404)',
                ];
            }

            if ($statusCode === 429) {
                return [
                    'username' => $username,
                    'result' => 'error',
                    'detail' => 'Rate limited oleh GitHub (HTTP 429). Coba tingkatkan delay.',
                ];
            }

            if ($statusCode === 200) {
                $hasProBadge = $this->detectProBadge($html);

                if ($hasProBadge) {
                    return [
                        'username' => $username,
                        'result' => 'approved',
                        'detail' => 'Akun live dengan badge PRO aktif',
                    ];
                }

                return [
                    'username' => $username,
                    'result' => 'not_approved',
                    'detail' => 'Akun live tetapi tidak memiliki badge PRO (Belum di-approve / Revoked)',
                ];
            }

            return [
                'username' => $username,
                'result' => 'error',
                'detail' => "Response tidak terduga: HTTP {$statusCode}",
            ];

        } catch (\Exception $e) {
            Log::error("GitHub profile check error for {$username}: " . $e->getMessage());
            return [
                'username' => $username,
                'result' => 'error',
                'detail' => 'Error membuka profil: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Detect PRO badge in GitHub profile HTML.
     * Looks for: <span class="Label Label--purple text-uppercase">Pro</span>
     */
    public function detectProBadge(string $html): bool
    {
        // Primary detection: Label--purple with Pro text
        if (preg_match('/Label--purple[^>]*>[\s]*Pro[\s]*</i', $html)) {
            return true;
        }

        // Fallback: look for the exact class combination
        if (str_contains($html, 'Label--purple') &&
            preg_match('/text-uppercase[^>]*>\s*Pro\s*</i', $html)) {
            return true;
        }

        return false;
    }

    /**
     * Parse usernames from various input formats.
     * Supports:
     * - Plain username (one per line)
     * - "Username: xxx" format (from github_accounts.txt)
     * - "user:pass" or "user:pass:2fa" format
     */
    public function parseUsernames(string $input): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $input)));
        $usernames = [];

        foreach ($lines as $line) {
            if (empty($line)) continue;

            // Skip non-username lines (Password:, F2A:, etc.)
            if (preg_match('/^(Password|F2A|2FA|Token|Email|Pass)\s*:/i', $line)) continue;

            // Format: "Username: xxx"
            if (preg_match('/^Username\s*:\s*(.+)$/i', $line, $matches)) {
                $usernames[] = trim($matches[1]);
                continue;
            }

            // Format: "user:pass" or "user:pass:2fa" (no spaces)
            if (str_contains($line, ':') && !str_contains($line, ' ')) {
                $parts = explode(':', $line);
                $candidate = trim($parts[0]);
                if (preg_match('/^[a-zA-Z0-9][\w-]*$/', $candidate)) {
                    $usernames[] = $candidate;
                }
                continue;
            }

            // Format: plain username (alphanumeric + hyphens)
            if (preg_match('/^[a-zA-Z0-9][\w-]*$/', $line)) {
                $usernames[] = $line;
                continue;
            }
        }

        // Remove duplicates and empty values
        return array_values(array_unique(array_filter($usernames)));
    }
}
