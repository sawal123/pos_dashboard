<?php

namespace App\Console\Commands;

use App\Services\Auth\GoogleAuthenticationException;
use App\Services\Auth\GoogleIdTokenVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Read-only triage for Google Sign-In failures.
 *
 * Run this on the deployment that rejects the login. It reports the resolved
 * configuration, whether Google's JWKS endpoint is reachable from the server,
 * and — when an ID token is supplied — why that token was rejected. The token,
 * subject, and email are never printed in raw form.
 */
final class GoogleAuthDiagnoseCommand extends Command
{
    /** @var string */
    protected $signature = 'auth:google:diagnose
        {--token= : Optional Google ID token to inspect and verify}
        {--platform=web : Client platform used for verification (web or android)}';

    /** @var string */
    protected $description = 'Diagnose Google Sign-In configuration, JWKS reachability, and token verification.';

    public function handle(): int
    {
        $ok = true;
        $clientId = trim((string) config('services.google.client_id'));
        $androidIds = (array) config('services.google.android_client_ids', []);
        $platform = (string) $this->option('platform');

        $this->line('Resolved configuration');
        $this->line('  GOOGLE_CLIENT_ID: '.($clientId === '' ? '<fg=red>EMPTY</>' : $this->mask($clientId)));
        $this->line('  Android client IDs: '.count(array_filter($androidIds)));
        $this->line('  APP_KEY set: '.((string) config('app.key') !== '' ? 'yes' : '<fg=red>no</>'));
        $this->line('  Cache store: '.(string) config('cache.default'));
        $this->line('  Clock skew allowance: '.max(0, (int) config('services.google.clock_skew_seconds', 60)).'s');
        $this->line('  Server time: '.now()->toIso8601String());

        if ($clientId === '') {
            $this->error('GOOGLE_CLIENT_ID is empty: /api/v2/auth/google returns 503 and /login hides the button.');
            $ok = false;
        }

        $this->newLine();
        $this->line('Google JWKS reachability');
        $started = microtime(true);
        try {
            $response = Http::timeout(10)->acceptJson()->get((string) config('services.google.jwks_url'));
            $duration = (int) round((microtime(true) - $started) * 1000);
            $keys = $response->json('keys');
            $usable = is_array($keys) ? count(array_filter($keys, static fn (mixed $key): bool => is_array($key)
                && ($key['kty'] ?? null) === 'RSA'
                && ($key['alg'] ?? null) === 'RS256'
                && is_string($key['kid'] ?? null)
                && $key['kid'] !== '')) : 0;

            $this->line("  HTTP {$response->status()} in {$duration}ms, {$usable} usable key(s)");

            if (! $response->successful() || $usable === 0) {
                $this->error('  JWKS is not usable. Check outbound HTTPS/DNS to googleapis.com and TLS certificates.');
                $ok = false;
            }
        } catch (Throwable $exception) {
            $this->error('  JWKS fetch failed: '.$exception::class.': '.$this->trim($exception->getMessage()));
            $this->error('  The server cannot verify any Google token until this request succeeds.');
            $ok = false;
        }

        $token = trim((string) ($this->option('token') ?? ''));
        if ($token === '') {
            $this->newLine();
            $this->line('No --token supplied; skipping token inspection and full verification.');

            return $ok ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->line('Token inspection (raw values are not printed)');
        $this->inspectToken($token);

        $this->newLine();
        $this->line('Full verification ('.$platform.')');
        try {
            $claims = app(GoogleIdTokenVerifier::class)->verify($token, $platform);
            $this->info('  PASS: subject '.$this->shortHash($claims->subject).', email '.$this->maskEmail($claims->email));
        } catch (GoogleAuthenticationException $exception) {
            $this->error('  FAIL: '.$exception->errorCode.' reason='.($exception->reason ?? 'n/a'));
            $previous = $exception->getPrevious();
            if ($previous !== null) {
                $this->line('  Underlying: '.$previous::class.': '.$this->trim($previous->getMessage()));
            }
            $ok = false;
        }

        $this->newLine();
        $this->warn('This command consumes the one-time replay marker for the supplied token; request a fresh token before logging in.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function inspectToken(string $token): void
    {
        $segments = explode('.', $token);
        if (count($segments) !== 3) {
            $this->line('  Malformed JWT: expected 3 segments, got '.count($segments));

            return;
        }

        $header = $this->decodeSegment($segments[0]);
        $payload = $this->decodeSegment($segments[1]);

        if ($header === null || $payload === null) {
            $this->line('  Header or payload is not valid base64url JSON.');

            return;
        }

        $now = time();
        $issuedAt = is_numeric($payload['iat'] ?? null) ? (int) $payload['iat'] : null;
        $expiresAt = is_numeric($payload['exp'] ?? null) ? (int) $payload['exp'] : null;

        $this->line('  alg='.($header['alg'] ?? 'n/a').' kid='.($header['kid'] ?? 'n/a'));
        $this->line('  iss='.($payload['iss'] ?? 'n/a'));
        $this->line('  aud='.$this->audience($payload['aud'] ?? null));
        $this->line('  azp='.($payload['azp'] ?? 'n/a'));
        $this->line('  email_verified='.var_export($payload['email_verified'] ?? null, true));
        $this->line('  email='.(is_string($payload['email'] ?? null) ? $this->maskEmail((string) $payload['email']) : 'n/a'));
        $this->line('  sub='.(is_string($payload['sub'] ?? null) ? $this->shortHash((string) $payload['sub']) : 'n/a'));
        $this->line('  iat='.($issuedAt ?? 'n/a').' (age '.($issuedAt !== null ? $now - $issuedAt : 'n/a').'s)');
        $this->line('  exp='.($expiresAt ?? 'n/a').' (in '.($expiresAt !== null ? $expiresAt - $now : 'n/a').'s)');
        $this->line('  lifetime='.(($issuedAt !== null && $expiresAt !== null) ? $expiresAt - $issuedAt : 'n/a').'s');
        $this->line('  configured server audience matches aud: '.$this->audienceMatches($payload['aud'] ?? null));
    }

    /** @return array<string, mixed>|null */
    private function decodeSegment(string $segment): ?array
    {
        $decoded = base64_decode(strtr($segment, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        $value = json_decode($decoded, true);

        return is_array($value) ? $value : null;
    }

    private function audience(mixed $audience): string
    {
        if (is_array($audience)) {
            return implode(', ', array_map([$this, 'mask'], array_filter($audience, 'is_string')));
        }

        return is_string($audience) ? $this->mask($audience) : 'n/a';
    }

    private function audienceMatches(mixed $audience): string
    {
        $allowed = trim((string) config('services.google.client_id'));
        $audiences = is_array($audience) ? $audience : [$audience];

        foreach ($audiences as $value) {
            if (is_string($value) && $value === $allowed) {
                return 'yes';
            }
        }

        return 'no';
    }

    private function mask(string $value): string
    {
        if (strlen($value) <= 20) {
            return $value;
        }

        return substr($value, 0, 10).'…'.substr($value, -12);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return ($local === '' ? '*' : substr($local, 0, 1).'***').'@'.$domain;
    }

    private function shortHash(string $value): string
    {
        return substr(hash('sha256', $value), 0, 16);
    }

    private function trim(string $message): string
    {
        return mb_substr($message, 0, 300);
    }
}
