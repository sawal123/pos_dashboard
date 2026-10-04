<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\ImmutableCloudBackupException;
use App\Models\Business;
use App\Models\CloudBackup;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * PREM-D03 — mobile Cloud backup API contract.
 *
 * Covers the authorization stack, the upload contract (private storage, size,
 * checksum, idempotency, immutability, negative-stock compatibility), the
 * metadata-only list, and the authorized private download.
 */
class MobileCloudBackupApiTest extends TestCase
{
    use RefreshDatabase;

    private const CORS_ORIGIN = 'https://pos-mobile.test';

    private ?string $lastMobileToken = null;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('cloud_backups');
    }

    // =========================================================================
    // Configuration contract
    // =========================================================================

    public function test_backup_policy_defaults_are_the_documented_values(): void
    {
        $this->assertSame(25 * 1024 * 1024, config('premium.backup.max_bytes'));
        $this->assertSame(10, config('premium.backup.retention'));
        $this->assertSame('cloud_backups', config('premium.backup.disk'));
    }

    // =========================================================================
    // Authorization
    // =========================================================================

    public function test_upload_requires_authentication(): void
    {
        $this->postJson('/api/mobile/backups', [])->assertStatus(401);
    }

    public function test_upload_rejects_a_token_without_the_mobile_ability(): void
    {
        $env = $this->makeEnvironment();
        $webToken = $env['user']->createToken('web-api', ['web'])->plainTextToken;

        $this->withToken($webToken)
            ->postJson('/api/mobile/backups', $this->uploadPayload($env))
            ->assertStatus(403)
            ->assertJson(['code' => 'MOBILE_TOKEN_REQUIRED']);
    }

    public function test_upload_rejects_a_non_member_business(): void
    {
        $env = $this->makeEnvironment();
        $other = $this->makeEnvironment();

        $this->actingAsMobile($env)
            ->postJson('/api/mobile/backups', $this->uploadPayload($env, [
                'business_id' => $other['business']->id,
                'device_identifier' => $other['device']->identifier,
            ]))
            ->assertStatus(403)
            ->assertJson(['code' => 'BUSINESS_ACCESS_DENIED']);
    }

    public function test_upload_rejects_a_free_subscription(): void
    {
        $env = $this->makeEnvironment(subscription: 'free');

        $this->actingAsMobile($env)
            ->postJson('/api/mobile/backups', $this->uploadPayload($env))
            ->assertStatus(403)
            ->assertJson(['code' => 'CLOUD_SUBSCRIPTION_REQUIRED']);
    }

    public function test_upload_rejects_an_expired_subscription(): void
    {
        $env = $this->makeEnvironment(subscription: 'expired');

        $this->actingAsMobile($env)
            ->postJson('/api/mobile/backups', $this->uploadPayload($env))
            ->assertStatus(403)
            ->assertJson(['code' => 'CLOUD_SUBSCRIPTION_REQUIRED']);
    }

    public function test_upload_allows_an_active_cloud_subscription(): void
    {
        $env = $this->makeEnvironment();

        $this->actingAsMobile($env)
            ->postJson('/api/mobile/backups', $this->uploadPayload($env))
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'ready');
    }

    public function test_upload_rejects_an_inactive_device(): void
    {
        $env = $this->makeEnvironment(deviceActive: false);

        $this->actingAsMobile($env)
            ->postJson('/api/mobile/backups', $this->uploadPayload($env))
            ->assertStatus(403)
            ->assertJson(['code' => 'DEVICE_INACTIVE']);

        $this->assertDatabaseCount('cloud_backups', 0);
    }

    public function test_upload_rejects_a_device_from_another_business(): void
    {
        $env = $this->makeEnvironment();
        $other = $this->makeEnvironment();

        $this->actingAsMobile($env)
            ->postJson('/api/mobile/backups', $this->uploadPayload($env, [
                'device_identifier' => $other['device']->identifier,
            ]))
            ->assertStatus(403)
            ->assertJson(['code' => 'DEVICE_NOT_FOUND']);
    }

    public function test_cross_business_detail_is_hidden_behind_a_404(): void
    {
        $envA = $this->makeEnvironment();
        $envB = $this->makeEnvironment();
        $uuid = $this->upload($envB)->json('data.uuid');

        $this->actingAsMobile($envA)
            ->getJson('/api/mobile/backups/'.$uuid.'?business_id='.$envA['business']->id)
            ->assertStatus(404)
            ->assertJson(['code' => 'BACKUP_NOT_FOUND']);
    }

    public function test_cross_business_download_is_hidden_behind_a_404(): void
    {
        $envA = $this->makeEnvironment();
        $envB = $this->makeEnvironment();
        $uuid = $this->upload($envB)->json('data.uuid');

        $this->actingAsMobile($envA)
            ->getJson('/api/mobile/backups/'.$uuid.'/download?business_id='.$envA['business']->id)
            ->assertStatus(404)
            ->assertJson(['code' => 'BACKUP_NOT_FOUND']);
    }

    // =========================================================================
    // Upload contract
    // =========================================================================

    public function test_a_valid_snapshot_is_stored_on_the_private_disk(): void
    {
        $env = $this->makeEnvironment();
        $snapshot = $this->snapshot();

        $response = $this->actingAsMobile($env)
            ->postJson('/api/mobile/backups', $this->uploadPayload($env))
            ->assertStatus(201);

        $uuid = $response->json('data.uuid');
        $backup = CloudBackup::where('uuid', $uuid)->firstOrFail();

        $this->assertSame($env['business']->id, $backup->business_id);
        $this->assertSame($env['device']->id, $backup->device_id);
        $this->assertSame(CloudBackup::STATUS_READY, $backup->status);
        $this->assertSame('cloud_backups', $backup->storage_disk);
        Storage::disk('cloud_backups')->assertExists($backup->storage_path);
        $this->assertSame($snapshot['payload'], Storage::disk('cloud_backups')->get($backup->storage_path));
    }

    public function test_uploaded_metadata_is_persisted(): void
    {
        $env = $this->makeEnvironment();
        $snapshot = $this->snapshot();

        $this->actingAsMobile($env)->postJson('/api/mobile/backups', $this->uploadPayload($env))->assertStatus(201);

        $this->assertDatabaseHas('cloud_backups', [
            'business_id' => $env['business']->id,
            'device_identifier' => $env['device']->identifier,
            'schema_version' => 1,
            'app_version' => '1.4.0',
            'size_bytes' => strlen($snapshot['payload']),
            'checksum_sha256' => $snapshot['checksum_sha256'],
            'status' => 'ready',
        ]);
    }

    public function test_upload_never_exposes_the_storage_path(): void
    {
        $env = $this->makeEnvironment();

        $response = $this->actingAsMobile($env)
            ->postJson('/api/mobile/backups', $this->uploadPayload($env))
            ->assertStatus(201);

        $backup = CloudBackup::firstOrFail();

        $this->assertArrayNotHasKey('storage_path', $response->json('data'));
        $this->assertArrayNotHasKey('storage_disk', $response->json('data'));
        $this->assertStringNotContainsString($backup->storage_path, (string) $response->getContent());
    }

    public function test_checksum_mismatch_is_rejected_and_leaves_no_snapshot(): void
    {
        $env = $this->makeEnvironment();

        $this->actingAsMobile($env)
            ->postJson('/api/mobile/backups', $this->uploadPayload($env, [
                'checksum_sha256' => str_repeat('0', 64),
            ]))
            ->assertStatus(422)
            ->assertJson(['code' => 'BACKUP_CHECKSUM_MISMATCH']);

        $this->assertDatabaseCount('cloud_backups', 0);
        $this->assertSame([], Storage::disk('cloud_backups')->allFiles());
    }

    public function test_declared_size_mismatch_is_rejected(): void
    {
        $env = $this->makeEnvironment();
        $snapshot = $this->snapshot();

        $this->actingAsMobile($env)
            ->postJson('/api/mobile/backups', $this->uploadPayload($env, [
                'size_bytes' => strlen($snapshot['payload']) + 5,
            ]))
            ->assertStatus(422)
            ->assertJson(['code' => 'BACKUP_SIZE_MISMATCH']);

        $this->assertDatabaseCount('cloud_backups', 0);
        $this->assertSame([], Storage::disk('cloud_backups')->allFiles());
    }

    public function test_payload_over_the_application_limit_is_rejected(): void
    {
        $env = $this->makeEnvironment();
        config(['premium.backup.max_bytes' => 2048]);

        $payload = str_repeat('a', 4096);

        $this->actingAsMobile($env)
            ->postJson('/api/mobile/backups', $this->uploadPayload($env, [
                'payload' => $payload,
                'size_bytes' => strlen($payload),
                'checksum_sha256' => hash('sha256', $payload),
            ]))
            ->assertStatus(422)
            ->assertJson(['code' => 'BACKUP_TOO_LARGE']);

        $this->assertDatabaseCount('cloud_backups', 0);
        $this->assertSame([], Storage::disk('cloud_backups')->allFiles());
    }

    public function test_payload_exactly_at_the_limit_is_accepted(): void
    {
        $env = $this->makeEnvironment();
        config(['premium.backup.max_bytes' => 2048]);

        $payload = str_repeat('a', 2048);

        $this->actingAsMobile($env)
            ->postJson('/api/mobile/backups', $this->uploadPayload($env, [
                'payload' => $payload,
                'size_bytes' => strlen($payload),
                'checksum_sha256' => hash('sha256', $payload),
            ]))
            ->assertStatus(201);
    }

    public function test_a_malformed_request_is_rejected(): void
    {
        $env = $this->makeEnvironment();

        $this->actingAsMobile($env)
            ->postJson('/api/mobile/backups', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'business_id', 'device_identifier', 'schema_version',
                'checksum_sha256', 'size_bytes', 'payload',
            ]);
    }

    public function test_negative_stock_and_negative_movement_are_accepted_verbatim(): void
    {
        $env = $this->makeEnvironment();
        $snapshot = $this->snapshot();

        // The payload contains product.stock = -3 and stock_before 2 / stock_after -3.
        $this->assertStringContainsString('"stock":-3', $snapshot['payload']);
        $this->assertStringContainsString('"stock_after":-3', $snapshot['payload']);

        $response = $this->actingAsMobile($env)
            ->postJson('/api/mobile/backups', $this->uploadPayload($env))
            ->assertStatus(201);

        $backup = CloudBackup::where('uuid', $response->json('data.uuid'))->firstOrFail();

        // Stored byte-for-byte — no normalization, no recalculation, no schema change.
        $this->assertSame($snapshot['payload'], Storage::disk('cloud_backups')->get($backup->storage_path));
    }

    public function test_a_storage_failure_fails_closed(): void
    {
        $env = $this->makeEnvironment();
        config(['premium.backup.disk' => 'missing-disk']);

        $this->actingAsMobile($env)
            ->postJson('/api/mobile/backups', $this->uploadPayload($env))
            ->assertStatus(500)
            ->assertJson(['code' => 'BACKUP_STORAGE_FAILED']);

        $this->assertDatabaseCount('cloud_backups', 0);
    }

    public function test_a_database_failure_cleans_up_the_orphaned_file(): void
    {
        $env = $this->makeEnvironment();

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER block_cloud_backup_insert
            BEFORE INSERT ON "cloud_backups"
            BEGIN
                SELECT RAISE(FAIL, 'simulated database failure');
            END
        SQL);

        try {
            $this->actingAsMobile($env)
                ->postJson('/api/mobile/backups', $this->uploadPayload($env))
                ->assertStatus(500)
                ->assertJson(['code' => 'BACKUP_STORAGE_FAILED']);

            $this->assertDatabaseCount('cloud_backups', 0);
            $this->assertSame([], Storage::disk('cloud_backups')->allFiles());
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS "block_cloud_backup_insert"');
        }
    }

    public function test_a_ready_snapshot_is_immutable(): void
    {
        $env = $this->makeEnvironment();
        $this->upload($env);
        $backup = CloudBackup::firstOrFail();

        $this->expectException(ImmutableCloudBackupException::class);

        $backup->update(['size_bytes' => 1]);
    }

    public function test_idempotency_key_prevents_duplicate_snapshots(): void
    {
        $env = $this->makeEnvironment();

        $first = $this->upload($env, ['idempotency_key' => 'retry-key-1']);
        $first->assertStatus(201)->assertJsonPath('data.duplicate', false);

        $second = $this->upload($env, ['idempotency_key' => 'retry-key-1']);
        $second->assertStatus(200)->assertJsonPath('data.duplicate', true);

        $this->assertSame($first->json('data.uuid'), $second->json('data.uuid'));
        $this->assertDatabaseCount('cloud_backups', 1);
        $this->assertCount(1, Storage::disk('cloud_backups')->allFiles());
    }

    public function test_the_same_idempotency_key_is_independent_across_businesses(): void
    {
        $envA = $this->makeEnvironment();
        $envB = $this->makeEnvironment();

        $a = $this->upload($envA, ['idempotency_key' => 'shared-key'])->assertStatus(201);
        $b = $this->upload($envB, ['idempotency_key' => 'shared-key'])->assertStatus(201);

        $this->assertNotSame($a->json('data.uuid'), $b->json('data.uuid'));
        $this->assertDatabaseCount('cloud_backups', 2);
    }

    public function test_the_same_idempotency_key_is_independent_across_devices(): void
    {
        $env = $this->makeEnvironment();
        $second = Device::create([
            'business_id' => $env['business']->id,
            'outlet_id' => $env['outlet']->id,
            'name' => 'Second POS',
            'identifier' => 'POS-SECOND-01',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $a = $this->upload($env, ['idempotency_key' => 'device-key'])->assertStatus(201);
        $b = $this->upload($env, [
            'idempotency_key' => 'device-key',
            'device_identifier' => $second->identifier,
        ])->assertStatus(201);

        $this->assertNotSame($a->json('data.uuid'), $b->json('data.uuid'));
        $this->assertDatabaseCount('cloud_backups', 2);
    }

    // =========================================================================
    // List
    // =========================================================================

    public function test_list_is_scoped_to_the_authorized_business(): void
    {
        $envA = $this->makeEnvironment();
        $envB = $this->makeEnvironment();

        $this->upload($envA);
        $this->upload($envB);

        $response = $this->actingAsMobile($envA)
            ->getJson('/api/mobile/backups?business_id='.$envA['business']->id)
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($envA['business']->id, CloudBackup::where('uuid', $response->json('data.0.uuid'))->firstOrFail()->business_id);
    }

    public function test_list_returns_newest_first(): void
    {
        $env = $this->makeEnvironment();

        $first = CloudBackup::where('uuid', $this->upload($env)->json('data.uuid'))->firstOrFail();
        $second = CloudBackup::where('uuid', $this->upload($env)->json('data.uuid'))->firstOrFail();
        $third = CloudBackup::where('uuid', $this->upload($env)->json('data.uuid'))->firstOrFail();

        $response = $this->actingAsMobile($env)
            ->getJson('/api/mobile/backups?business_id='.$env['business']->id)
            ->assertOk();

        $this->assertSame([$third->id, $second->id, $first->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_list_returns_metadata_only_without_payload_or_storage_path(): void
    {
        $env = $this->makeEnvironment();
        $snapshot = $this->snapshot();
        $this->upload($env);
        $backup = CloudBackup::firstOrFail();

        $response = $this->actingAsMobile($env)
            ->getJson('/api/mobile/backups?business_id='.$env['business']->id)
            ->assertOk();

        $row = $response->json('data.0');

        $this->assertSame(
            ['id', 'uuid', 'created_at', 'schema_version', 'app_version', 'size_bytes', 'checksum_sha256', 'status', 'device'],
            array_keys($row),
        );
        $this->assertSame($backup->checksum_sha256, $row['checksum_sha256']);
        $this->assertStringNotContainsString($snapshot['payload'], (string) $response->getContent());
        $this->assertStringNotContainsString($backup->storage_path, (string) $response->getContent());
        $this->assertArrayNotHasKey('storage_path', $row);
        $this->assertArrayNotHasKey('payload', $row);
    }

    public function test_list_respects_the_documented_limit(): void
    {
        $env = $this->makeEnvironment();

        for ($i = 0; $i < 5; $i++) {
            $this->upload($env);
        }

        $response = $this->actingAsMobile($env)
            ->getJson('/api/mobile/backups?business_id='.$env['business']->id.'&limit=2')
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    // =========================================================================
    // Detail + download
    // =========================================================================

    public function test_detail_returns_metadata_only(): void
    {
        $env = $this->makeEnvironment();
        $snapshot = $this->snapshot();
        $uuid = $this->upload($env)->json('data.uuid');
        $backup = CloudBackup::firstOrFail();

        $response = $this->actingAsMobile($env)
            ->getJson('/api/mobile/backups/'.$uuid.'?business_id='.$env['business']->id)
            ->assertOk();

        $this->assertSame($uuid, $response->json('data.uuid'));
        $this->assertArrayNotHasKey('storage_path', $response->json('data'));
        $this->assertStringNotContainsString($snapshot['payload'], (string) $response->getContent());
        $this->assertStringNotContainsString($backup->storage_path, (string) $response->getContent());
    }

    public function test_authorized_download_streams_the_exact_original_bytes(): void
    {
        $env = $this->makeEnvironment();
        $snapshot = $this->snapshot();
        $uuid = $this->upload($env)->json('data.uuid');

        $response = $this->actingAsMobile($env)
            ->getJson('/api/mobile/backups/'.$uuid.'/download?business_id='.$env['business']->id)
            ->assertOk();

        $this->assertSame($snapshot['payload'], $response->streamedContent());
    }

    public function test_download_exposes_checksum_and_schema_version_headers(): void
    {
        $env = $this->makeEnvironment();
        $snapshot = $this->snapshot();
        $uuid = $this->upload($env)->json('data.uuid');

        $response = $this->actingAsMobile($env)
            ->getJson('/api/mobile/backups/'.$uuid.'/download?business_id='.$env['business']->id)
            ->assertOk();

        $response->assertHeader('X-Checksum-Sha256', $snapshot['checksum_sha256']);
        $response->assertHeader('X-Backup-Schema-Version', '1');
    }

    public function test_download_fails_safely_when_the_private_file_is_missing(): void
    {
        $env = $this->makeEnvironment();
        $uuid = $this->upload($env)->json('data.uuid');
        $backup = CloudBackup::firstOrFail();

        Storage::disk('cloud_backups')->delete($backup->storage_path);

        $this->actingAsMobile($env)
            ->getJson('/api/mobile/backups/'.$uuid.'/download?business_id='.$env['business']->id)
            ->assertStatus(404)
            ->assertJson(['code' => 'BACKUP_FILE_MISSING']);
    }

    public function test_free_subscription_cannot_download(): void
    {
        $env = $this->makeEnvironment();
        $uuid = $this->upload($env)->json('data.uuid');

        $env['business']->subscription()->update(['plan' => 'free', 'status' => 'active']);

        $this->actingAsMobile($env)
            ->getJson('/api/mobile/backups/'.$uuid.'/download?business_id='.$env['business']->id)
            ->assertStatus(403)
            ->assertJson(['code' => 'CLOUD_SUBSCRIPTION_REQUIRED']);
    }

    public function test_expired_subscription_cannot_download(): void
    {
        $env = $this->makeEnvironment();
        $uuid = $this->upload($env)->json('data.uuid');

        $env['business']->subscription()->update([
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);

        $this->actingAsMobile($env)
            ->getJson('/api/mobile/backups/'.$uuid.'/download?business_id='.$env['business']->id)
            ->assertStatus(403)
            ->assertJson(['code' => 'CLOUD_SUBSCRIPTION_REQUIRED']);
    }

    // =========================================================================
    // PREM-D03C — CORS exposure of the download integrity headers
    // =========================================================================

    public function test_download_cors_preflight_is_answered_for_api_paths(): void
    {
        $response = $this->withHeaders([
            'Origin' => self::CORS_ORIGIN,
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Authorization',
        ])->options('/api/mobile/backups/00000000-0000-4000-8000-000000000000/download?business_id=1');

        $response->assertNoContent();
        $response->assertHeader('Access-Control-Allow-Origin', '*');
        $response->assertHeader('Access-Control-Allow-Methods', 'GET');
        $response->assertHeader('Access-Control-Allow-Headers', 'Authorization');
        // The credentials policy must stay unchanged (supports_credentials = false).
        $this->assertFalse($response->headers->has('Access-Control-Allow-Credentials'));
    }

    public function test_download_exposes_integrity_headers_to_browser_clients(): void
    {
        $env = $this->makeEnvironment();
        $snapshot = $this->snapshot();
        $uuid = $this->upload($env)->json('data.uuid');

        $response = $this->actingAsMobile($env)
            ->withHeaders(['Origin' => self::CORS_ORIGIN])
            ->getJson('/api/mobile/backups/'.$uuid.'/download?business_id='.$env['business']->id)
            ->assertOk();

        $response->assertHeader('Access-Control-Allow-Origin', '*');

        // Exactly the two integrity headers are exposed — nothing else.
        $this->assertSame(
            ['x-checksum-sha256', 'x-backup-schema-version'],
            $this->corsExposedHeaders($response),
        );

        // The pre-existing download headers are still sent unchanged.
        $response->assertHeader('X-Checksum-Sha256', $snapshot['checksum_sha256']);
        $response->assertHeader('X-Backup-Schema-Version', '1');

        // Credentials remain disabled: CORS exposure never enables cookies/tokens.
        $this->assertFalse($response->headers->has('Access-Control-Allow-Credentials'));
    }

    public function test_download_cors_exposure_does_not_leak_internal_state(): void
    {
        $env = $this->makeEnvironment();
        $uuid = $this->upload($env)->json('data.uuid');
        $backup = CloudBackup::firstOrFail();

        $response = $this->actingAsMobile($env)
            ->withHeaders(['Origin' => self::CORS_ORIGIN])
            ->getJson('/api/mobile/backups/'.$uuid.'/download?business_id='.$env['business']->id)
            ->assertOk();

        $exposed = implode(',', $this->corsExposedHeaders($response));

        foreach (['authorization', 'bearer', 'token', 'storage_path', 'cloud-backups'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $exposed);
        }

        $this->assertStringNotContainsString($backup->storage_path, (string) $response->getContent());
        $this->assertStringNotContainsString(
            $backup->storage_path,
            (string) $response->headers->get('Access-Control-Expose-Headers'),
        );
    }

    public function test_unauthorized_download_stays_unauthorized_with_a_cors_origin(): void
    {
        $env = $this->makeEnvironment();
        $uuid = $this->upload($env)->json('data.uuid');

        // Drop the upload's token + resolved guard so the next request is anonymous.
        $this->flushHeaders();
        Auth::forgetGuards();

        $this->withHeaders(['Origin' => self::CORS_ORIGIN])
            ->getJson('/api/mobile/backups/'.$uuid.'/download?business_id='.$env['business']->id)
            ->assertStatus(401);
    }

    public function test_cross_tenant_download_stays_404_with_a_cors_origin(): void
    {
        $envA = $this->makeEnvironment();
        $envB = $this->makeEnvironment();
        $uuid = $this->upload($envB)->json('data.uuid');

        $this->actingAsMobile($envA)
            ->withHeaders(['Origin' => self::CORS_ORIGIN])
            ->getJson('/api/mobile/backups/'.$uuid.'/download?business_id='.$envA['business']->id)
            ->assertStatus(404)
            ->assertJson(['code' => 'BACKUP_NOT_FOUND']);
    }

    public function test_free_subscription_download_stays_forbidden_with_a_cors_origin(): void
    {
        $env = $this->makeEnvironment();
        $uuid = $this->upload($env)->json('data.uuid');

        $env['business']->subscription()->update(['plan' => 'free', 'status' => 'active']);

        $this->actingAsMobile($env)
            ->withHeaders(['Origin' => self::CORS_ORIGIN])
            ->getJson('/api/mobile/backups/'.$uuid.'/download?business_id='.$env['business']->id)
            ->assertStatus(403)
            ->assertJson(['code' => 'CLOUD_SUBSCRIPTION_REQUIRED']);
    }

    public function test_cashier_role_is_denied_on_the_backup_api(): void
    {
        $env = $this->makeEnvironment(role: 'cashier');

        $this->actingAsMobile($env)
            ->postJson('/api/mobile/backups', $this->uploadPayload($env))
            ->assertStatus(403)
            ->assertJson(['code' => 'MOBILE_ROLE_NOT_SUPPORTED']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * @return array{user: User, business: Business, outlet: Outlet, device: Device, token: string}
     */
    private function makeEnvironment(
        string $role = 'owner',
        string $subscription = 'cloud',
        bool $deviceActive = true,
    ): array {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($user->id, ['role' => $role]);

        if ($subscription === 'expired') {
            Subscription::factory()->cloud()->expired()->create(['business_id' => $business->id]);
        } elseif ($subscription === 'free') {
            Subscription::factory()->free()->create(['business_id' => $business->id]);
        } else {
            Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        }

        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS '.$role,
            'identifier' => 'POS-'.Str::upper(Str::random(8)),
            'platform' => 'android',
            'status' => $deviceActive ? 'active' : 'inactive',
            'registered_at' => now(),
        ]);
        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        return compact('user', 'business', 'outlet', 'device', 'token');
    }

    /**
     * The exact serialized snapshot the mobile app would upload. It deliberately
     * contains negative stock to prove the backend never re-validates domain
     * invariants of the POS payload.
     *
     * @return array{payload: string, size_bytes: int, checksum_sha256: string}
     */
    private function snapshot(): array
    {
        $payload = json_encode([
            'schema_version' => 1,
            'app_version' => '1.4.0',
            'exported_at' => '2026-10-02T03:00:00+07:00',
            'business' => ['id' => 7, 'name' => 'Demo Cafe'],
            'products' => [
                ['id' => 1, 'name' => 'Kopi Susu', 'stock' => -3],
            ],
            'stock_movements' => [
                ['product_id' => 1, 'stock_before' => 2, 'stock_after' => -3, 'quantity_change' => -5],
            ],
            'sales' => [],
        ], JSON_THROW_ON_ERROR);

        return [
            'payload' => $payload,
            'size_bytes' => strlen($payload),
            'checksum_sha256' => hash('sha256', $payload),
        ];
    }

    /**
     * @param  array{user: User, business: Business, outlet: Outlet, device: Device, token: string}  $env
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function uploadPayload(array $env, array $overrides = []): array
    {
        return array_merge([
            'business_id' => $env['business']->id,
            'device_identifier' => $env['device']->identifier,
            'schema_version' => 1,
            'app_version' => '1.4.0',
        ], $this->snapshot(), $overrides);
    }

    /**
     * @param  array{user: User, business: Business, outlet: Outlet, device: Device, token: string}  $env
     * @param  array<string, mixed>  $overrides
     */
    private function upload(array $env, array $overrides = []): TestResponse
    {
        return $this->actingAsMobile($env)
            ->postJson('/api/mobile/backups', $this->uploadPayload($env, $overrides));
    }

    /**
     * Authenticate the next request with an environment's mobile token.
     *
     * Sanctum caches the resolved guard user for the whole test, so switching
     * to a different actor requires forgetting the guards first. The first
     * request of a test needs no reset (the guard is already empty).
     *
     * @param  array{user: User, business: Business, outlet: Outlet, device: Device, token: string}  $env
     */
    private function actingAsMobile(array $env): static
    {
        if ($this->lastMobileToken !== null && $this->lastMobileToken !== $env['token']) {
            Auth::forgetGuards();
        }

        $this->lastMobileToken = $env['token'];

        return $this->withToken($env['token']);
    }

    /**
     * Parse `Access-Control-Expose-Headers` into a lower-cased, trimmed list so
     * assertions are case-insensitive as browsers treat them.
     *
     * @return list<string>
     */
    private function corsExposedHeaders(TestResponse $response): array
    {
        $raw = (string) $response->headers->get('Access-Control-Expose-Headers', '');

        return array_values(array_filter(array_map(
            static fn (string $header): string => strtolower(trim($header)),
            explode(',', $raw),
        )));
    }
}
