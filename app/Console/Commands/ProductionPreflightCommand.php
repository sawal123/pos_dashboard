<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
            $this->checkWarn('QUEUE_CONNECTION is not sync', config('queue.default') === 'sync', 'A synchronous queue is fine for a single-node pilot but should be a real worker in production.'),
            $this->checkWarn('MAIL_MAILER is not log', in_array(config('mail.default'), ['log', 'array'], true), 'Outgoing mail is not actually delivered.'),
            $this->checkWarn('CACHE_STORE is not array/null', in_array(config('cache.default'), ['array', 'null'], true), 'An in-memory cache is not shared across processes.'),
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
}
