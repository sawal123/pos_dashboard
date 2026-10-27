<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-release guard for production configuration (QA-RELEASE B1, closes H1).
 *
 * Reads *resolved* configuration, so it reflects exactly what the application
 * would use under `config:cache`. It fails (non-zero exit) when a value is
 * unsafe for production (e.g. APP_DEBUG=true) and warns on weaker settings.
 *
 * Run after `php artisan config:cache` as the last gate before switching
 * traffic, for example:
 *
 *     php artisan deploy:preflight --strict
 */
final class ProductionPreflightCommand extends Command
{
    /** @var string */
    protected $signature = 'deploy:preflight
        {--strict : Treat warnings as failures as well}';

    /** @var string */
    protected $description = 'Validate resolved configuration before a production release.';

    public function handle(): int
    {
        /** @var list<array{status: string, name: string, message: string}> $results */
        $results = [
            $this->checkSame('APP_ENV=production', 'production', (string) config('app.env')),
            $this->checkFalse('APP_DEBUG=false', (bool) config('app.debug')),
            $this->checkTrue('APP_KEY is set', is_string(config('app.key')) && config('app.key') !== ''),
            $this->checkAppUrl(),
            $this->checkFalse('SESSION_DRIVER is not array', config('session.driver') === 'array'),
            $this->checkTrue('SESSION_SECURE_COOKIE is enabled', (bool) config('session.secure')),
            $this->checkFalse('SESSION_HTTP_ONLY is enabled', config('session.http_only') === false),
            $this->checkFalse('DB_CONNECTION is not sqlite', config('database.default') === 'sqlite'),
            $this->checkWarn('LOG_LEVEL is not debug', config('logging.channels.'.config('logging.default').'.level') === 'debug' || config('logging.level') === 'debug'),
            $this->checkLogging(),
            $this->checkAuditLogging(),
            $this->checkWarn('QUEUE_CONNECTION is not sync', config('queue.default') === 'sync', 'A synchronous queue is fine for a single-node pilot but should be a real worker in production.'),
            $this->checkSafeMailer('MAIL_MAILER', (string) config('mail.default', 'log')),
            $this->checkSafeMailer('AUTH_OTP_MAILER', (string) config('auth_otp.mailer', 'log')),
            $this->checkMailSender(),
            $this->checkGoogleConfiguration(),
            $this->checkAuthCache(),
            $this->checkAuthSchema(),
            $this->checkWarn('config is cached', ! $this->laravel->configurationIsCached(), 'Run `php artisan config:cache` before release.'),
        ];

        $failures = 0;
        $warnings = 0;

        $this->newLine();
        foreach ($results as $result) {
            match ($result['status']) {
                'pass' => $this->line('  <fg=green>PASS</> '.$result['name']),
                'warn' => $this->line('  <fg=yellow>WARN</> '.$result['name'].' — '.$result['message']),
                default => $this->line('  <fg=red>FAIL</> '.$result['name'].' — '.$result['message']),
            };

            if ($result['status'] === 'fail') {
                $failures++;
            } elseif ($result['status'] === 'warn') {
                $warnings++;
            }
        }

        $this->newLine();
        $strict = (bool) $this->option('strict');

        if ($failures > 0 || ($strict && $warnings > 0)) {
            $this->error(sprintf(
                'Production preflight FAILED (%d failure(s), %d warning(s)).',
                $failures,
                $warnings,
            ));

            return self::FAILURE;
        }

        if ($warnings > 0) {
            $this->warn(sprintf('Production preflight passed with %d warning(s).', $warnings));
        } else {
            $this->info('Production preflight passed.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{status: string, name: string, message: string}
     */
    private function checkSame(string $name, string $expected, string $actual): array
    {
        return [
            'status' => $actual === $expected ? 'pass' : 'fail',
            'name' => $name,
            'message' => "expected '{$expected}', got '{$actual}'",
        ];
    }

    /**
     * @return array{status: string, name: string, message: string}
     */
    private function checkTrue(string $name, bool $condition, string $message = 'must be enabled'): array
    {
        return [
            'status' => $condition ? 'pass' : 'fail',
            'name' => $name,
            'message' => $message,
        ];
    }

    /**
     * @return array{status: string, name: string, message: string}
     */
    private function checkFalse(string $name, bool $condition, string $message = 'must not be set to that value in production'): array
    {
        return [
            'status' => $condition ? 'fail' : 'pass',
            'name' => $name,
            'message' => $message,
        ];
    }

    /**
     * @return array{status: string, name: string, message: string}
     */
    private function checkWarn(string $name, bool $condition, string $message = 'not recommended for production'): array
    {
        return [
            'status' => $condition ? 'warn' : 'pass',
            'name' => $name,
            'message' => $message,
        ];
    }

    /**
     * APP_URL must use HTTPS. There is deliberately no bypass flag: a
     * production preflight must never pass on a plaintext URL.
     *
     * @return array{status: string, name: string, message: string}
     */
    private function checkAppUrl(): array
    {
        $url = (string) config('app.url');
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = (string) parse_url($url, PHP_URL_HOST);

        if ($scheme !== 'https') {
            return [
                'status' => 'fail',
                'name' => 'APP_URL scheme',
                'message' => "APP_URL must use https (got '{$url}')",
            ];
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return [
                'status' => 'warn',
                'name' => 'APP_URL host',
                'message' => "APP_URL still points at a local host ('{$host}')",
            ];
        }

        return ['status' => 'pass', 'name' => 'APP_URL scheme', 'message' => ''];
    }

    /**
     * @return array{status: string, name: string, message: string}
     */
    private function checkSafeMailer(string $name, string $mailer): array
    {
        $allowed = (array) config('auth_otp.allowed_mailers', []);
        if (! in_array($mailer, $allowed, true)) {
            return [
                'status' => 'fail',
                'name' => $name,
                'message' => 'must use a real credential-delivery mailer; log, array, and unsafe failover are forbidden',
            ];
        }

        $mailerConfig = config('mail.mailers.'.$mailer);
        if (! is_array($mailerConfig) || ($mailerConfig['transport'] ?? null) === null) {
            return [
                'status' => 'fail',
                'name' => $name,
                'message' => 'mailer configuration is missing a transport',
            ];
        }

        return ['status' => 'pass', 'name' => $name, 'message' => ''];
    }

    /**
     * @return array{status: string, name: string, message: string}
     */
    private function checkMailSender(): array
    {
        $address = (string) config('mail.from.address', '');

        return filter_var($address, FILTER_VALIDATE_EMAIL) !== false
            ? ['status' => 'pass', 'name' => 'AUTH mail sender is valid', 'message' => '']
            : ['status' => 'fail', 'name' => 'AUTH mail sender is valid', 'message' => 'MAIL_FROM_ADDRESS must be a valid address'];
    }

    /**
     * @return array{status: string, name: string, message: string}
     */
    private function checkLogging(): array
    {
        $channel = (string) config('logging.default', '');
        $configured = $channel !== ''
            && $channel !== 'null'
            && is_array(config('logging.channels.'.$channel));

        return $configured
            ? ['status' => 'pass', 'name' => 'AUTH audit logging is configured', 'message' => '']
            : ['status' => 'fail', 'name' => 'AUTH audit logging is configured', 'message' => 'LOG_CHANNEL must resolve to a real channel'];
    }

    /**
     * @return array{status: string, name: string, message: string}
     */
    private function checkAuditLogging(): array
    {
        $channel = trim((string) config('auth_security.audit_channel', 'auth_security'));
        if ($channel === '' || $channel === 'null') {
            return [
                'status' => 'fail',
                'name' => 'AUTH audit channel is effective',
                'message' => 'AUTH_AUDIT_LOG_CHANNEL must not be null or empty',
            ];
        }

        $visited = [];
        $state = $this->auditChannelState($channel, $visited);

        if (! $state['valid'] || ! $state['effective']) {
            return [
                'status' => 'fail',
                'name' => 'AUTH audit channel is effective',
                'message' => $state['message'],
            ];
        }

        return ['status' => 'pass', 'name' => 'AUTH audit channel is effective', 'message' => ''];
    }

    /**
     * @param  array<string, bool>  $visited
     * @return array{valid: bool, effective: bool, message: string}
     */
    private function auditChannelState(string $name, array &$visited): array
    {
        if (isset($visited[$name])) {
            return ['valid' => false, 'effective' => false, 'message' => 'audit channel configuration contains a cycle'];
        }

        $visited[$name] = true;
        $channel = config('logging.channels.'.$name);
        if (! is_array($channel)) {
            return ['valid' => false, 'effective' => false, 'message' => "audit channel '{$name}' is not configured"];
        }

        $driver = (string) ($channel['driver'] ?? '');
        if ($driver === 'stack') {
            $destinations = $channel['channels'] ?? [];
            if (! is_array($destinations) || $destinations === []) {
                return ['valid' => false, 'effective' => false, 'message' => "audit stack '{$name}' has no destinations"];
            }

            $effective = false;
            foreach ($destinations as $destination) {
                if (! is_string($destination) || $destination === '') {
                    return ['valid' => false, 'effective' => false, 'message' => "audit stack '{$name}' has an invalid destination"];
                }

                $state = $this->auditChannelState($destination, $visited);
                if (! $state['valid']) {
                    return $state;
                }

                $effective = $effective || $state['effective'];
            }

            return $effective
                ? ['valid' => true, 'effective' => true, 'message' => '']
                : ['valid' => true, 'effective' => false, 'message' => "audit stack '{$name}' filters info events or has no effective handler"];
        }

        if (in_array($driver, ['', 'null', 'failover', 'roundrobin'], true)) {
            return ['valid' => false, 'effective' => false, 'message' => "audit channel '{$name}' has no effective handler"];
        }

        $level = strtolower((string) ($channel['level'] ?? 'debug'));
        $levels = [
            'debug' => 100,
            'info' => 200,
            'notice' => 250,
            'warning' => 300,
            'error' => 400,
            'critical' => 500,
            'alert' => 550,
            'emergency' => 600,
        ];
        if (! isset($levels[$level])) {
            return ['valid' => false, 'effective' => false, 'message' => "audit channel '{$name}' has an invalid log level"];
        }

        $handlerConfigured = match ($driver) {
            'single', 'daily', 'monthly' => is_string($channel['path'] ?? null) && $channel['path'] !== '',
            'stream' => is_string($channel['handler_with']['stream'] ?? null) && $channel['handler_with']['stream'] !== '',
            'slack' => is_string($channel['url'] ?? null) && $channel['url'] !== '',
            'monolog' => isset($channel['handler']),
            'custom' => is_string($channel['via'] ?? null) && $channel['via'] !== '',
            default => true,
        };

        if (! $handlerConfigured) {
            return ['valid' => false, 'effective' => false, 'message' => "audit channel '{$name}' has no configured handler"];
        }

        return [
            'valid' => true,
            'effective' => $levels[$level] <= $levels['info'],
            'message' => $levels[$level] <= $levels['info'] ? '' : "audit channel '{$name}' filters info events",
        ];
    }

    /**
     * @return array{status: string, name: string, message: string}
     */
    private function checkGoogleConfiguration(): array
    {
        $clientId = trim((string) config('services.google.client_id', ''));
        $androidIds = array_values(array_filter(array_map(
            static fn (mixed $id): string => trim((string) $id),
            (array) config('services.google.android_client_ids', []),
        )));

        if ($clientId === '' && $androidIds === []) {
            return ['status' => 'pass', 'name' => 'Google Sign-In configuration is optional and disabled', 'message' => ''];
        }

        if ($clientId === '') {
            return ['status' => 'fail', 'name' => 'Google OAuth client IDs are valid', 'message' => 'server audience is required when Google Sign-In is enabled'];
        }

        $ids = array_merge([$clientId], $androidIds);
        foreach ($ids as $id) {
            if (! str_ends_with(strtolower($id), '.apps.googleusercontent.com')) {
                return ['status' => 'fail', 'name' => 'Google OAuth client IDs are valid', 'message' => 'configured client IDs have an invalid format'];
            }
        }

        return ['status' => 'pass', 'name' => 'Google OAuth client IDs are valid', 'message' => ''];
    }

    /**
     * @return array{status: string, name: string, message: string}
     */
    private function checkAuthCache(): array
    {
        $store = (string) config('cache.default', 'array');
        if (in_array($store, ['array', 'null', 'failover', 'octane'], true)) {
            return [
                'status' => 'fail',
                'name' => 'AUTH cache is shared and atomic',
                'message' => "CACHE_STORE '{$store}' is not suitable for replay reservation and throttling",
            ];
        }

        return ['status' => 'pass', 'name' => 'AUTH cache is shared and atomic', 'message' => ''];
    }

    /**
     * @return array{status: string, name: string, message: string}
     */
    private function checkAuthSchema(): array
    {
        try {
            $schema = config('auth_security.schema_connection');
            $builder = is_string($schema) && $schema !== ''
                ? Schema::connection($schema)
                : Schema::connection((string) config('database.default'));

            $ready = $builder->hasTable('auth_otp_challenges')
                && $builder->hasTable('mobile_auth_challenges')
                && $builder->hasTable('email_change_requests')
                && $builder->hasColumn('users', 'password_login_enabled');
        } catch (\Throwable) {
            $ready = false;
        }

        return $ready
            ? ['status' => 'pass', 'name' => 'AUTH migrations are applied', 'message' => '']
            : ['status' => 'fail', 'name' => 'AUTH migrations are applied', 'message' => 'required AUTH tables or columns are missing'];
    }
}
