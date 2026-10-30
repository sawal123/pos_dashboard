<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAuthDiagnoseTest extends TestCase
{
    public function test_it_reports_jwks_reachability_and_succeeds(): void
    {
        config(['services.google.client_id' => 'web-client-id.apps.googleusercontent.com']);
        $this->fakeJwks();

        $this->artisan('auth:google:diagnose')
            ->expectsOutputToContain('1 usable key')
            ->assertExitCode(0);
    }

    public function test_it_fails_when_the_server_client_id_is_missing(): void
    {
        config(['services.google.client_id' => null]);
        $this->fakeJwks();

        $this->artisan('auth:google:diagnose')
            ->expectsOutputToContain('GOOGLE_CLIENT_ID is empty')
            ->assertExitCode(1);
    }

    private function fakeJwks(): void
    {
        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response([
                'keys' => [[
                    'kty' => 'RSA',
                    'alg' => 'RS256',
                    'use' => 'sig',
                    'kid' => 'diagnose-kid',
                    'n' => 'abc',
                    'e' => 'AQAB',
                ]],
            ], 200),
        ]);
    }
}
