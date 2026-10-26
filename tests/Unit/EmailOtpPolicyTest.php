<?php

namespace Tests\Unit;

use App\Services\Auth\EmailOtpPolicy;
use Tests\TestCase;

class EmailOtpPolicyTest extends TestCase
{
    public function test_generated_codes_are_six_ascii_digits(): void
    {
        $policy = app(EmailOtpPolicy::class);

        for ($i = 0; $i < 100; $i++) {
            $code = $policy->generateCode();

            $this->assertMatchesRegularExpression('/\A[0-9]{6}\z/D', $code);
        }
    }

    public function test_code_format_retains_leading_zeroes(): void
    {
        $policy = app(EmailOtpPolicy::class);

        $this->assertSame('000042', $policy->formatCode(42));
        $this->assertSame('000000', $policy->formatCode(0));
        $this->assertSame('999999', $policy->formatCode(999999));
    }

    public function test_digest_is_keyed_and_changes_with_code_or_nonce(): void
    {
        $policy = app(EmailOtpPolicy::class);

        $first = $policy->digest('registration-reference-0001', 'email_verification', 'nonce-0001', '123456');

        $this->assertSame(64, strlen($first));
        $this->assertNotSame(
            $first,
            $policy->digest('registration-reference-0001', 'email_verification', 'nonce-0001', '123457'),
        );
        $this->assertNotSame(
            $first,
            $policy->digest('registration-reference-0001', 'email_verification', 'nonce-0002', '123456'),
        );
    }

    public function test_identifier_keys_do_not_contain_the_plaintext_identifier(): void
    {
        $policy = app(EmailOtpPolicy::class);
        $email = 'private-user@example.com';

        $key = $policy->identifierKey($email);

        $this->assertSame(64, strlen($key));
        $this->assertStringNotContainsString($email, $key);
    }
}
