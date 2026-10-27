<?php

namespace App\Services\Auth;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JsonException;
use Throwable;

final class GoogleOidcVerifier implements GoogleIdTokenVerifier
{
    private const PUBLIC_KEYS_CACHE_KEY = 'auth-google:oidc-public-keys:v1';

    public function verify(string $idToken, string $clientPlatform): GoogleIdTokenClaims
    {
        $idToken = trim($idToken);
        $allowedAudiences = $this->allowedAudiences($clientPlatform);

        if ($idToken === '' || $allowedAudiences === []) {
            throw new GoogleAuthenticationException(
                $allowedAudiences === [] ? 'GOOGLE_AUTH_NOT_CONFIGURED' : 'GOOGLE_TOKEN_INVALID',
                'Google authentication could not be verified safely.',
            );
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $this->segments($idToken);
        $header = $this->decodeJson($encodedHeader, 'GOOGLE_TOKEN_INVALID');
        $payload = $this->decodeJson($encodedPayload, 'GOOGLE_TOKEN_INVALID');

        if (($header['alg'] ?? null) !== 'RS256' || ! is_string($header['kid'] ?? null)) {
            throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.');
        }

        $now = time();
        $skew = $this->clockSkewSeconds();
        $publicKey = $this->publicKey((string) $header['kid']);
        $previousLeeway = JWT::$leeway;
        try {
            // firebase/php-jwt uses a process-global leeway. Scope it to this
            // verification and always restore it before returning or throwing.
            JWT::$leeway = $skew;
            JWT::decode($idToken, $publicKey);
        } catch (ExpiredException $exception) {
            throw new GoogleAuthenticationException('GOOGLE_TOKEN_EXPIRED', 'Google authentication could not be verified safely.', $exception);
        } catch (Throwable $exception) {
            throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.', $exception);
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        $issuer = (string) config('services.google.issuer', 'https://accounts.google.com');

        if (! in_array($payload['iss'] ?? null, [$issuer, 'accounts.google.com'], true)) {
            throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.');
        }

        $audience = $payload['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];
        $audiences = array_values(array_filter($audiences, 'is_string'));

        if (count(array_intersect($audiences, $this->allowedAudiences($clientPlatform))) === 0) {
            throw new GoogleAuthenticationException('GOOGLE_AUDIENCE_INVALID', 'Google authentication could not be verified safely.');
        }

        if (isset($payload['azp']) && ! in_array($payload['azp'], $this->allowedAudiences($clientPlatform), true)) {
            throw new GoogleAuthenticationException('GOOGLE_AUDIENCE_INVALID', 'Google authentication could not be verified safely.');
        }

        if (count($audiences) > 1 && ! isset($payload['azp'])) {
            throw new GoogleAuthenticationException('GOOGLE_AUDIENCE_INVALID', 'Google authentication could not be verified safely.');
        }

        if (! is_int($payload['exp'] ?? null) && ! is_numeric($payload['exp'] ?? null)) {
            throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.');
        }

        $expiresAt = (int) $payload['exp'];
        if ($expiresAt <= $now - $skew) {
            throw new GoogleAuthenticationException('GOOGLE_TOKEN_EXPIRED', 'Google authentication could not be verified safely.');
        }

        if (! is_int($payload['iat'] ?? null) && ! is_numeric($payload['iat'] ?? null)) {
            throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.');
        }

        $issuedAt = (int) $payload['iat'];
        if ($issuedAt > $now + $skew || $expiresAt <= $issuedAt) {
            throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.');
        }

        if (isset($payload['nbf'])
            && (! is_int($payload['nbf']) && ! is_numeric($payload['nbf']))) {
            throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.');
        }

        if (isset($payload['nbf']) && (int) $payload['nbf'] > $now + $skew) {
            throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.');
        }

        $maxLifetime = max(1, min(86400, (int) config('services.google.max_token_lifetime_seconds', 3600)));
        if ($expiresAt - $issuedAt > $maxLifetime) {
            throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.');
        }

        $subject = $payload['sub'] ?? null;
        if (! is_string($subject) || $subject === '' || strlen($subject) > 255) {
            throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.');
        }

        $email = $payload['email'] ?? null;
        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 255) {
            throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.');
        }

        if (($payload['email_verified'] ?? null) !== true) {
            throw new GoogleAuthenticationException('GOOGLE_EMAIL_UNVERIFIED', 'Google email verification is required.');
        }

        $this->reserveReplay($encodedHeader, $encodedPayload, $encodedSignature, $expiresAt, $now, $skew);

        $name = is_string($payload['name'] ?? null) && trim((string) $payload['name']) !== ''
            ? mb_substr(trim((string) $payload['name']), 0, 255)
            : null;

        return new GoogleIdTokenClaims(
            subject: $subject,
            email: mb_strtolower(trim($email)),
            expiresAt: $expiresAt,
            issuedAt: $issuedAt,
            name: $name,
        );
    }

    /** @return list<string> */
    private function allowedAudiences(string $clientPlatform): array
    {
        $clientId = trim((string) config('services.google.client_id', ''));
        $androidClientIds = config('services.google.android_client_ids', []);

        if (! is_array($androidClientIds)) {
            $androidClientIds = [];
        }

        if ($clientId === '') {
            return [];
        }

        $audiences = [$clientId];
        if ($clientPlatform === 'android') {
            $audiences = array_merge($audiences, $androidClientIds);
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $audiences,
        ))));
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function segments(string $idToken): array
    {
        $segments = explode('.', $idToken);
        if (count($segments) !== 3 || in_array('', $segments, true)) {
            throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.');
        }

        return [$segments[0], $segments[1], $segments[2]];
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $encoded, string $errorCode): array
    {
        $decoded = $this->decodeBase64Url($encoded);
        if ($decoded === null) {
            throw new GoogleAuthenticationException($errorCode, 'Google authentication could not be verified safely.');
        }

        try {
            $value = json_decode($decoded, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GoogleAuthenticationException($errorCode, 'Google authentication could not be verified safely.', $exception);
        }

        if (! is_array($value)) {
            throw new GoogleAuthenticationException($errorCode, 'Google authentication could not be verified safely.');
        }

        return $value;
    }

    private function decodeBase64Url(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    private function publicKey(string $keyId): Key
    {
        $keys = $this->publicKeys();
        if (! isset($keys[$keyId])) {
            $keys = $this->publicKeys(true);
        }

        if (! isset($keys[$keyId])) {
            throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.');
        }

        return $keys[$keyId];
    }

    /** @return array<string, Key> */
    private function publicKeys(bool $forceRefresh = false): array
    {
        try {
            if (! $forceRefresh) {
                $cached = Cache::get(self::PUBLIC_KEYS_CACHE_KEY);
                if (is_array($cached)) {
                    return $this->parsePublicKeys($cached);
                }
            }

            $response = Http::timeout(5)
                ->acceptJson()
                ->get((string) config('services.google.jwks_url'));

            if (! $response->successful()) {
                throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.');
            }

            $jwks = $response->json();
            if (! is_array($jwks)) {
                throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.');
            }

            $keys = $this->parsePublicKeys($jwks);
            if ($keys === []) {
                throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.');
            }

            Cache::put(self::PUBLIC_KEYS_CACHE_KEY, $jwks, now()->addSeconds($this->maxAge($response)));

            return $keys;
        } catch (GoogleAuthenticationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.', $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $jwks
     * @return array<string, Key>
     */
    private function parsePublicKeys(array $jwks): array
    {
        if (! is_array($jwks['keys'] ?? null)) {
            return [];
        }

        $keys = [];
        foreach ($jwks['keys'] as $key) {
            if (! is_array($key)
                || ($key['kty'] ?? null) !== 'RSA'
                || ($key['alg'] ?? null) !== 'RS256'
                || (isset($key['use']) && $key['use'] !== 'sig')
                || ! is_string($key['kid'] ?? null)
                || trim($key['kid']) === ''
                || ! is_string($key['n'] ?? null)
                || $key['n'] === ''
                || ! is_string($key['e'] ?? null)
                || $key['e'] === '') {
                continue;
            }

            try {
                $parsed = JWK::parseKey($key);
                if ($parsed !== null) {
                    $keys[$key['kid']] = $parsed;
                }
            } catch (Throwable) {
                // Ignore malformed or unsupported provider keys. The caller
                // fails closed when no usable key remains for the token kid.
            }
        }

        return $keys;
    }

    private function maxAge(Response $response): int
    {
        $cacheControl = strtolower((string) $response->header('cache-control'));
        if (preg_match('/max-age=(\d+)/', $cacheControl, $matches) === 1) {
            return max(60, min(86400, (int) $matches[1]));
        }

        return 3600;
    }

    private function reserveReplay(
        string $encodedHeader,
        string $encodedPayload,
        string $encodedSignature,
        int $expiresAt,
        int $now,
        int $skew,
    ): void {
        $applicationKey = (string) config('app.key');
        if ($applicationKey === '') {
            throw new GoogleAuthenticationException('GOOGLE_AUTH_NOT_CONFIGURED', 'Google authentication could not be verified safely.');
        }

        $header = $this->decodeBase64Url($encodedHeader);
        $payload = $this->decodeBase64Url($encodedPayload);
        $signature = $this->decodeBase64Url($encodedSignature);
        if ($header === null || $payload === null || $signature === null) {
            throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.');
        }

        $canonicalHeader = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $canonicalPayload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signedInput = $canonicalHeader.'.'.$canonicalPayload;
        $tokenHash = hash_hmac('sha256', $signedInput."\0".$signature, $applicationKey);
        $requiredTtl = $expiresAt - $now + $skew;
        $ttl = max(1, (int) config('services.google.replay_ttl_seconds', 3600), $requiredTtl);

        try {
            if (! Cache::add('auth-google:replay:'.$tokenHash, true, $ttl)) {
                app(AuthSecurityAuditService::class)->record('auth.google.replay_rejected', [
                    'status' => 'rejected',
                    'error_category' => 'replay',
                    'provider' => 'google',
                ]);
                throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.');
            }
        } catch (GoogleAuthenticationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            app(AuthSecurityAuditService::class)->record('auth.google.verification_failed', [
                'status' => 'failed',
                'error_category' => 'replay_reservation_unavailable',
                'provider' => 'google',
            ]);
            throw new GoogleAuthenticationException('GOOGLE_TOKEN_INVALID', 'Google authentication could not be verified safely.', $exception);
        }
    }

    private function clockSkewSeconds(): int
    {
        return min(300, max(0, (int) config('services.google.clock_skew_seconds', 60)));
    }
}
