<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AuthSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_preflight_reports_auth_security_checks(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://pos.example.test',
            'app.key' => 'base64:test-application-key',
            'session.driver' => 'database',
            'session.secure' => true,
            'session.http_only' => true,
            'mail.default' => 'smtp',
            'auth_otp.mailer' => 'smtp',
            'mail.mailers.smtp' => ['transport' => 'smtp'],
            'mail.from.address' => 'auth@example.test',
            'cache.default' => 'database',
            'services.google.client_id' => null,
            'services.google.android_client_ids' => [],
        ]);

        Artisan::call('deploy:preflight');
        $output = Artisan::output();

        $this->assertStringContainsString('AUTH_OTP_MAILER', $output);
        $this->assertStringContainsString('AUTH cache is shared and atomic', $output);
        $this->assertStringContainsString('AUTH migrations are applied', $output);
        $this->assertStringContainsString('Google Sign-In configuration is optional and disabled', $output);
    }
}
