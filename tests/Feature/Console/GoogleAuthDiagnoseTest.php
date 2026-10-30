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
            ->expectsOutputToContain('1 raw key')
            ->expectsOutputToContain('Parsed kid(s): diagnose-kid')
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
                    'n' => 'pIpnzA2ezyEERJSxiqpLBmMeIqATH-V6iuBtKIibXEyYovujrx8niqTeO6RIyXT6uDUUv0V2kJ8V_iWYFxzXY1BqK9IfcAmjg0XUDoyTVkoyLsF0gj299LH-zw5vCvy8jmamFIZKAbKcQ5hpHvSittM1vl-6vVL-i2GxyGbMA9aY6Hq15NylS1t7ELTYfQimlnvxcb7_DM0cuS5U1SfbCZMCpKhh0nrSlYds240oxpCJOV2rBahs_Ea5c7tezS1nwVC9W_E-bR9TF6BHkC_fv-E8DcWfkI_6geaJzBhINNxBfjx-w1-WUp2Jz3YYFWEfeQjxMqu-Fg6cGxwk7V16uQ',
                    'e' => 'AQAB',
                ]],
            ], 200),
        ]);
    }
}
