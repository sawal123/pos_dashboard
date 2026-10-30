<?php

namespace Tests\Feature\Console;

use App\Console\Commands\ProductionPreflightCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QA-RELEASE B1 — production configuration preflight.
 *
 * The command must fail closed on unsafe resolved configuration (notably
 * APP_ENV=production with APP_DEBUG=true) and pass on a hardened config.
 */
class ProductionPreflightTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config(['database.default' => 'sqlite']);

        parent::tearDown();
    }

    public function test_it_fails_when_debug_is_enabled_in_production(): void
    {
        $this->hardenedConfig(['app.debug' => true]);

        $this->artisan('deploy:preflight')
            ->expectsOutputToContain('APP_DEBUG=false')
            ->assertExitCode(1);
    }

    public function test_it_fails_when_app_env_is_not_production(): void
    {
        $this->hardenedConfig(['app.env' => 'local']);

        $this->artisan('deploy:preflight')->assertExitCode(1);
    }

    public function test_it_passes_with_a_hardened_production_config(): void
    {
        $this->hardenedConfig();

        $this->artisan('deploy:preflight')->assertExitCode(0);
    }

    public function test_it_verifies_the_google_jwt_library_is_installed(): void
    {
        $this->hardenedConfig();

        $this->artisan('deploy:preflight')
            ->expectsOutputToContain('Google JWT library is installed')
            ->assertExitCode(0);
    }

    public function test_unconfigured_google_sign_in_warns_instead_of_silently_passing(): void
    {
        $this->hardenedConfig([
            'services.google.client_id' => '',
            'services.google.android_client_ids' => [],
        ]);

        $this->artisan('deploy:preflight')
            ->expectsOutputToContain('GOOGLE_AUTH_NOT_CONFIGURED')
            ->assertExitCode(0);
    }

    public function test_a_malformed_google_client_id_fails_preflight(): void
    {
        $this->hardenedConfig([
            'services.google.client_id' => 'not-a-google-client-id',
            'services.google.android_client_ids' => [],
        ]);

        $this->artisan('deploy:preflight')
            ->expectsOutputToContain('Google OAuth client IDs are valid')
            ->assertExitCode(1);
    }

    public function test_it_fails_on_an_insecure_session_cookie(): void
    {
        $this->hardenedConfig(['session.secure' => false]);

        $this->artisan('deploy:preflight')
            ->expectsOutputToContain('SESSION_SECURE_COOKIE')
            ->assertExitCode(1);
    }

    public function test_it_fails_on_an_http_app_url_and_offers_no_bypass(): void
    {
        $this->hardenedConfig(['app.url' => 'http://pos.example.com']);

        $this->artisan('deploy:preflight')
            ->expectsOutputToContain('APP_URL scheme')
            ->assertExitCode(1);

        // No flag may bypass the HTTPS requirement in a production preflight.
        $command = new ProductionPreflightCommand;
        $this->assertFalse($command->getDefinition()->hasOption('allow-http'));
    }

    public function test_an_https_local_url_only_warns(): void
    {
        $this->hardenedConfig(['app.url' => 'https://localhost']);

        $this->artisan('deploy:preflight')->assertExitCode(0);
    }

    public function test_it_fails_on_a_sqlite_database(): void
    {
        $this->hardenedConfig(['database.default' => 'sqlite']);

        $this->artisan('deploy:preflight')->assertExitCode(1);
    }

    public function test_strict_mode_promotes_warnings_to_a_failure(): void
    {
        // Hardened, but the queue stays synchronous which is only a warning.
        $this->hardenedConfig(['queue.default' => 'sync']);

        $this->artisan('deploy:preflight')->assertExitCode(0);
        $this->artisan('deploy:preflight', ['--strict' => true])->assertExitCode(1);
    }

    public function test_audit_info_level_is_effective_when_application_log_level_is_warning(): void
    {
        $this->hardenedConfig([
            'logging.channels.single.level' => 'warning',
            'logging.channels.auth_security.level' => 'info',
            'auth_security.audit_channel' => 'auth_security',
        ]);

        $this->artisan('deploy:preflight')
            ->expectsOutputToContain('AUTH audit channel is effective')
            ->assertExitCode(0);
    }

    public function test_audit_channel_that_filters_info_fails_preflight(): void
    {
        $this->hardenedConfig([
            'auth_security.audit_channel' => 'single',
            'logging.channels.single.level' => 'warning',
        ]);

        $this->artisan('deploy:preflight')
            ->expectsOutputToContain('AUTH audit channel is effective')
            ->assertExitCode(1);
    }

    public function test_null_audit_channel_fails_preflight(): void
    {
        $this->hardenedConfig(['auth_security.audit_channel' => 'null']);

        $this->artisan('deploy:preflight')
            ->expectsOutputToContain('AUTH audit channel is effective')
            ->assertExitCode(1);
    }

    public function test_dedicated_audit_channel_passes_preflight(): void
    {
        $this->hardenedConfig([
            'auth_security.audit_channel' => 'auth_security',
            'logging.channels.auth_security.level' => 'info',
        ]);

        $this->artisan('deploy:preflight')->assertExitCode(0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function hardenedConfig(array $overrides = []): void
    {
        config(array_merge([
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.url' => 'https://pos.example.com',
            'session.driver' => 'database',
            'session.secure' => true,
            'session.http_only' => true,
            'database.default' => 'mysql',
            'logging.default' => 'stack',
            'logging.channels.stack.level' => 'warning',
            'queue.default' => 'database',
            'mail.default' => 'smtp',
            'auth_otp.mailer' => 'smtp',
            'auth_security.schema_connection' => 'sqlite',
            'cache.default' => 'database',
        ], $overrides));
    }
}
