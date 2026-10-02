<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CloudBackup;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * PREM-D03 — automatic retention of private Cloud backups.
 *
 * Retention keeps at most ten READY snapshots per business, newest first.
 * It only ever runs after a NEW snapshot is successfully persisted, and a
 * cleanup failure must never invalidate the new snapshot or touch another
 * business.
 */
class CloudBackupRetentionTest extends TestCase
{
    use RefreshDatabase;

    private ?string $lastMobileToken = null;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('cloud_backups');
    }

    public function test_the_first_ten_backups_are_all_retained(): void
    {
        $env = $this->makeEnvironment();

        $uuids = [];
        for ($i = 0; $i < 10; $i++) {
            $uuids[] = $this->upload($env)->assertStatus(201)->json('data.uuid');
        }

        $this->assertDatabaseCount('cloud_backups', 10);

        foreach ($uuids as $uuid) {
            $this->assertDatabaseHas('cloud_backups', ['uuid' => $uuid]);
        }
    }

    public function test_the_eleventh_backup_removes_the_oldest(): void
    {
        $env = $this->makeEnvironment();

        $oldest = $this->upload($env)->json('data.uuid');
        for ($i = 0; $i < 9; $i++) {
            $this->upload($env);
        }

        $newest = $this->upload($env)->assertStatus(201)->json('data.uuid');

        $this->assertDatabaseCount('cloud_backups', 10);
        $this->assertDatabaseMissing('cloud_backups', ['uuid' => $oldest]);
        $this->assertDatabaseHas('cloud_backups', ['uuid' => $newest]);
    }

    public function test_retention_keeps_the_ten_newest_snapshots(): void
    {
        $env = $this->makeEnvironment();

        $uuids = [];
        for ($i = 0; $i < 12; $i++) {
            $uuids[] = $this->upload($env)->json('data.uuid');
        }

        $this->assertDatabaseCount('cloud_backups', 10);

        $this->assertDatabaseMissing('cloud_backups', ['uuid' => $uuids[0]]);
        $this->assertDatabaseMissing('cloud_backups', ['uuid' => $uuids[1]]);

        foreach (array_slice($uuids, 2) as $uuid) {
            $this->assertDatabaseHas('cloud_backups', ['uuid' => $uuid]);
        }
    }

    public function test_retention_of_one_business_does_not_affect_another(): void
    {
        $envA = $this->makeEnvironment();
        $envB = $this->makeEnvironment();

        for ($i = 0; $i < 11; $i++) {
            $this->upload($envA);
        }

        $bUuids = [];
        for ($i = 0; $i < 3; $i++) {
            $bUuids[] = $this->upload($envB)->json('data.uuid');
        }

        $this->assertSame(10, CloudBackup::where('business_id', $envA['business']->id)->count());
        $this->assertSame(3, CloudBackup::where('business_id', $envB['business']->id)->count());

        foreach ($bUuids as $uuid) {
            $this->assertDatabaseHas('cloud_backups', ['uuid' => $uuid]);
        }
    }

    public function test_a_failed_upload_does_not_remove_older_backups(): void
    {
        $env = $this->makeEnvironment();

        $oldest = $this->upload($env)->json('data.uuid');
        for ($i = 0; $i < 9; $i++) {
            $this->upload($env);
        }

        // The eleventh upload fails checksum verification: retention must not run.
        $this->upload($env, ['checksum_sha256' => str_repeat('0', 64)])
            ->assertStatus(422)
            ->assertJson(['code' => 'BACKUP_CHECKSUM_MISMATCH']);

        $this->assertDatabaseCount('cloud_backups', 10);
        $this->assertDatabaseHas('cloud_backups', ['uuid' => $oldest]);
    }

    public function test_a_retention_cleanup_failure_does_not_invalidate_the_new_backup(): void
    {
        $env = $this->makeEnvironment();

        for ($i = 0; $i < 10; $i++) {
            $this->upload($env);
        }

        // Point every existing snapshot at a disk that does not exist so the
        // retention file-delete throws. The row delete must still proceed and
        // the new snapshot must remain valid.
        DB::table('cloud_backups')
            ->where('business_id', $env['business']->id)
            ->update(['storage_disk' => 'missing-disk']);

        Log::spy();

        $newest = $this->upload($env)->assertStatus(201)->json('data.uuid');

        $this->assertDatabaseCount('cloud_backups', 10);
        $this->assertDatabaseHas('cloud_backups', ['uuid' => $newest, 'status' => 'ready']);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => $message === 'cloud_backup.retention_file_delete_failed')
            ->once();
    }

    public function test_the_retention_cap_holds_across_many_uploads(): void
    {
        $env = $this->makeEnvironment();

        for ($i = 0; $i < 20; $i++) {
            $this->upload($env)->assertStatus(201);
            $this->assertLessThanOrEqual(10, CloudBackup::where('business_id', $env['business']->id)->count());
        }

        $this->assertSame(10, CloudBackup::where('business_id', $env['business']->id)->count());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * @return array{user: User, business: Business, outlet: Outlet, device: Device, token: string}
     */
    private function makeEnvironment(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($user->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);

        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = Device::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS Retention',
            'identifier' => 'POS-'.Str::upper(Str::random(8)),
            'platform' => 'android',
            'status' => 'active',
            'registered_at' => now(),
        ]);
        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        return compact('user', 'business', 'outlet', 'device', 'token');
    }

    /**
     * @return array{payload: string, size_bytes: int, checksum_sha256: string}
     */
    private function snapshot(): array
    {
        $payload = json_encode([
            'schema_version' => 1,
            'app_version' => '1.4.0',
            'products' => [['id' => 1, 'name' => 'Widget', 'stock' => -3]],
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
     */
    private function upload(array $env, array $overrides = []): TestResponse
    {
        return $this->actingAsMobile($env)->postJson('/api/mobile/backups', array_merge([
            'business_id' => $env['business']->id,
            'device_identifier' => $env['device']->identifier,
            'schema_version' => 1,
            'app_version' => '1.4.0',
        ], $this->snapshot(), $overrides));
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
}
