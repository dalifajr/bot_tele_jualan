<?php

namespace Tests\Unit;

use App\Services\GithubCheckerService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GithubCheckerServiceTest extends TestCase
{
    protected GithubCheckerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GithubCheckerService();
    }

    /**
     * Test username parsing from different formats.
     */
    public function test_username_parsing(): void
    {
        $input = "
            dalifajr
            Username: another-user
            Password: mypassword123
            F2A: 123456
            user:pass:2fa
            invalid_username_with_space 123
            -invalid-start
            valid-user-123
        ";

        $parsed = $this->service->parseUsernames($input);

        $this->assertCount(4, $parsed);
        $this->assertContains('dalifajr', $parsed);
        $this->assertContains('another-user', $parsed);
        $this->assertContains('user', $parsed);
        $this->assertContains('valid-user-123', $parsed);
    }

    /**
     * Test PRO badge detection in profile HTML.
     */
    public function test_pro_badge_detection(): void
    {
        // Test case 1: Contiguous class name and text
        $html1 = '<span class="Label Label--purple text-uppercase">Pro</span>';
        $this->assertTrue($this->service->detectProBadge($html1));

        // Test case 2: Class name and text with newlines (as found in profile.html)
        $html2 = '
            <span title="Label: Pro" data-view-component="true"
                class="Label Label--purple text-uppercase">Pro
            </span>
        ';
        $this->assertTrue($this->service->detectProBadge($html2));

        // Test case 3: No PRO badge
        $html3 = '<span class="Label Label--secondary">Member</span>';
        $this->assertFalse($this->service->detectProBadge($html3));
    }

    /**
     * Test checking username when user is APPROVED (found in search, has PRO badge).
     */
    public function test_check_username_approved(): void
    {
        $username = 'dalifajr';
        $cookie = 'user_session=valid_cookie';

        // Mock search response (JSON format) and profile response (with PRO badge)
        Http::fake([
            'https://github.com/search*' => Http::response($this->getMockSearchHtml($username), 200),
            'https://github.com/*' => Http::response($this->getMockProfileHtml(true, '2026-06-04T06:30:13Z'), 200),
        ]);

        $result = $this->service->checkUsername($username, $cookie);

        $this->assertEquals('approved', $result['result']);
        $this->assertStringContainsString('PRO', $result['detail']);
        $this->assertEquals('2026-06-04T06:30:13Z', $result['github_joined_at']);
    }

    /**
     * Test checking username when user is NOT_APPROVED (found in search, no PRO badge).
     */
    public function test_check_username_not_approved(): void
    {
        $username = 'dalifajr';
        $cookie = 'user_session=valid_cookie';

        // Mock search response and profile response (without PRO badge)
        Http::fake([
            'https://github.com/search*' => Http::response($this->getMockSearchHtml($username), 200),
            'https://github.com/*' => Http::response($this->getMockProfileHtml(false), 200),
        ]);

        $result = $this->service->checkUsername($username, $cookie);

        $this->assertEquals('not_approved', $result['result']);
        $this->assertStringContainsString('tidak memiliki badge PRO', $result['detail']);
    }

    /**
     * Test checking username when user is SUSPENDED (not found in search).
     */
    public function test_check_username_suspended(): void
    {
        $username = 'suspended_user';
        $cookie = 'user_session=valid_cookie';

        // Mock search response with no results
        Http::fake([
            'https://github.com/search*' => Http::response($this->getMockSearchHtml('other_user'), 200),
        ]);

        $result = $this->service->checkUsername($username, $cookie);

        $this->assertEquals('suspended', $result['result']);
        $this->assertStringContainsString('tidak ditemukan saat pencarian', $result['detail']);
    }

    /**
     * Helper to generate mock search HTML containing react-app.embeddedData script.
     */
    private function getMockSearchHtml(string $foundUsername): string
    {
        $payload = [
            'payload' => [
                'results' => [
                    [
                        'login' => $foundUsername,
                        'name' => 'Mock User',
                    ]
                ]
            ]
        ];

        $json = json_encode($payload);

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <body>
            <script type="application/json" data-target="react-app.embeddedData">
                {$json}
            </script>
        </body>
        </html>
        HTML;
    }

    /**
     * Helper to generate mock profile HTML.
     */
    private function getMockProfileHtml(bool $hasPro, string $joinedAt = '2026-06-04T06:30:13Z'): string
    {
        $badge = $hasPro ? '
            <span title="Label: Pro" data-view-component="true"
                class="Label Label--purple text-uppercase">Pro
            </span>' : '';

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <body>
            <div class="user-profile">
                <h1>Mock Profile</h1>
                {$badge}
                <span>Joined <relative-time datetime="{$joinedAt}" class="no-wrap" title="Jun 4, 2026, 1:30 PM GMT+7"><template shadowrootmode="open">11 hours ago</template>Jun 3, 2026</relative-time></span>
            </div>
        </body>
        </html>
        HTML;
    }
}
