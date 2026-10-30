<?php

namespace Tests\Feature\Auth;

use App\Services\Auth\AuthSecurityAuditService;
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
        $this->assertStringContainsString('Google Sign-In is configured', $output);
    }

    public function test_info_audit_events_are_written_when_application_log_level_is_warning(): void
    {
        $path = storage_path('framework/auth-security-test.log');
        @unlink($path);
        config([
            'auth_security.audit_channel' => 'auth_security',
            'logging.channels.single.level' => 'warning',
            'logging.channels.auth_security.driver' => 'single',
            'logging.channels.auth_security.path' => $path,
            'logging.channels.auth_security.level' => 'info',
        ]);

        app(AuthSecurityAuditService::class)->record('auth.security.test', [
            'status' => 'accepted',
            'account_identifier' => 'user@example.test',
            'password' => 'password-must-not-appear',
            'otp' => '123456',
            'bearer_token' => 'token-must-not-appear',
        ]);

        $this->assertFileExists($path);
        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString('auth.security', $contents);
        $this->assertStringNotContainsString('password-must-not-appear', $contents);
        $this->assertStringNotContainsString('123456', $contents);
        $this->assertStringNotContainsString('token-must-not-appear', $contents);
        $this->assertStringNotContainsString('user@example.test', $contents);
        @unlink($path);
    }
}
