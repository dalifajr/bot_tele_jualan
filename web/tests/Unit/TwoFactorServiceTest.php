<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\TwoFactorService;

class TwoFactorServiceTest extends TestCase
{
    public function test_base32_decoding(): void
    {
        // 'JBSWY3DPEHPK3PXP' in base32 is ASCII "Hello!\xde\xad"
        $binary = TwoFactorService::base32Decode('JBSWY3DPEHPK3PXP');
        $this->assertNotEmpty($binary);
        $this->assertStringStartsWith('Hello!', $binary);

        // Case insensitivity and space tolerance
        $binaryWithSpaces = TwoFactorService::base32Decode('jbsw y3dp ehpk 3pxp');
        $this->assertEquals($binary, $binaryWithSpaces);
    }

    public function test_generate_otp_rfc_6238(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';

        // Specific timestamp tests (deterministic)
        $code1 = TwoFactorService::generateOtp($secret, 1234567890);
        $this->assertNotNull($code1);
        $this->assertEquals(6, strlen($code1));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code1);

        // Another timestamp
        $code2 = TwoFactorService::generateOtp($secret, 1234567890 + 30);
        $this->assertNotNull($code2);
        $this->assertEquals(6, strlen($code2));
        $this->assertNotEquals($code1, $code2); // Changes after 30s
    }

    public function test_detect_2fa_secrets_multiline(): void
    {
        $text = "Email: user@example.com\nPassword: secretPassword123\nF2A: JBSWY3DPEHPK3PXP\nNotes: some other text";
        $detected = TwoFactorService::detect2faSecrets($text);

        $this->assertCount(1, $detected);
        $this->assertEquals('JBSWY3DPEHPK3PXP', $detected[0]['secret']);
        $this->assertEquals(2, $detected[0]['line_index']);
    }

    public function test_detect_2fa_secrets_case_insensitive_label(): void
    {
        $text = "username: myuser\n2FA: JBSWY3DPEHPK3PXP\n";
        $detected = TwoFactorService::detect2faSecrets($text);
        $this->assertCount(1, $detected);
        $this->assertEquals('JBSWY3DPEHPK3PXP', $detected[0]['secret']);

        $text2 = "username: myuser\n2fa: JBSWY3DPEHPK3PXP\n";
        $detected2 = TwoFactorService::detect2faSecrets($text2);
        $this->assertCount(1, $detected2);
        $this->assertEquals('JBSWY3DPEHPK3PXP', $detected2[0]['secret']);
    }

    public function test_detect_2fa_secrets_delimited_pipe(): void
    {
        $text = "user@example.com|myPassword123|JBSWY3DPEHPK3PXP";
        $detected = TwoFactorService::detect2faSecrets($text);
        $this->assertCount(1, $detected);
        $this->assertEquals('JBSWY3DPEHPK3PXP', $detected[0]['secret']);
    }

    public function test_detect_2fa_secrets_otpauth_uri(): void
    {
        $text = "otpauth://totp/GitHub:dalifajr?secret=JBSWY3DPEHPK3PXP&issuer=GitHub";
        $detected = TwoFactorService::detect2faSecrets($text);
        $this->assertCount(1, $detected);
        $this->assertEquals('JBSWY3DPEHPK3PXP', $detected[0]['secret']);
    }

    public function test_render_with_2fa_inserts_under_f2a_line(): void
    {
        $text = "Email: user@example.com\nPassword: secret123\nF2A: JBSWY3DPEHPK3PXP";
        $rendered = TwoFactorService::renderWith2fa($text, false);

        $this->assertStringContainsString("F2A: JBSWY3DPEHPK3PXP", $rendered);
        $this->assertStringContainsString("F2A Code:", $rendered);
    }
}
