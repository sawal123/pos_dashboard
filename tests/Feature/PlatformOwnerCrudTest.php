<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SyncRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PlatformOwnerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_edit_and_delete_a_business(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create(['slug' => 'old-business']);

        $this->actingAs($admin)->patch(route('platform.businesses.update', $business), [
            'name' => 'Updated Business',
            'slug' => 'updated-business',
            'business_type' => 'cafe',
            'status' => 'active',
        ])->assertRedirect(route('platform.businesses.show', $business));

        $this->assertDatabaseHas('businesses', ['id' => $business->id, 'name' => 'Updated Business', 'slug' => 'updated-business']);

        $this->actingAs($admin)->delete(route('platform.businesses.destroy', $business), [
            'slug' => 'updated-business',
            'confirmation' => 'updated-business',
        ])->assertRedirect(route('platform.businesses.index'));

        $this->assertDatabaseMissing('businesses', ['id' => $business->id]);
    }

    public function test_platform_admin_can_edit_and_delete_an_unassigned_user(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $user = User::factory()->create(['name' => 'Before User']);

        $this->actingAs($admin)->patch(route('platform.users.update', $user), [
            'name' => 'After User',
            'email' => 'after-user@example.com',
            'is_platform_admin' => '0',
        ])->assertRedirect(route('platform.users.show', $user));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'After User', 'email' => 'after-user@example.com']);

        $this->actingAs($admin)->delete(route('platform.users.destroy', $user), [
            'confirmation' => 'DELETE',
        ])->assertRedirect(route('platform.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_platform_admin_can_edit_and_delete_a_subscription(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        $subscription = Subscription::factory()->create(['business_id' => $business->id]);

        $this->actingAs($admin)->patch(route('platform.subscriptions.update', $subscription), [
            'plan' => 'cloud',
            'status' => 'active',
            'starts_at' => '2026-10-23 10:00:00',
            'expires_at' => '2026-11-23 10:00:00',
        ])->assertRedirect(route('platform.subscriptions.show', $subscription));

        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'plan' => 'cloud', 'status' => 'active']);

        $this->actingAs($admin)->delete(route('platform.subscriptions.destroy', $subscription), [
            'confirmation' => 'DELETE',
        ])->assertRedirect(route('platform.subscriptions.index'));

        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
    }

    public function test_platform_admin_can_override_and_delete_an_unpaid_payment_attempt(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $payment = SubscriptionPayment::factory()->create(['status' => SubscriptionPayment::STATUS_PENDING]);

        $this->actingAs($admin)->patch(route('platform.payments.status.update', $payment), [
            'status' => SubscriptionPayment::STATUS_FAILED,
        ])->assertRedirect(route('platform.payments.show', $payment));

        $this->assertDatabaseHas('subscription_payments', ['id' => $payment->id, 'status' => SubscriptionPayment::STATUS_FAILED]);

        $this->actingAs($admin)->delete(route('platform.payments.destroy', $payment), [
            'confirmation' => 'DELETE',
        ])->assertRedirect(route('platform.payments.index'));

        $this->assertDatabaseMissing('subscription_payments', ['id' => $payment->id]);
    }

    public function test_platform_admin_can_mark_an_unpaid_payment_as_paid_and_activate_subscription(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $payment = SubscriptionPayment::factory()->create([
            'status' => SubscriptionPayment::STATUS_PENDING,
            'plan' => Subscription::PLAN_CLOUD,
            'billing_period' => 'monthly',
        ]);

        $this->actingAs($admin)->patch(route('platform.payments.status.update', $payment), [
            'status' => SubscriptionPayment::STATUS_PAID,
        ])->assertRedirect(route('platform.payments.show', $payment));

        $payment->refresh();

        $this->assertSame(SubscriptionPayment::STATUS_PAID, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertNotNull($payment->activated_at);
        $this->assertDatabaseHas('subscriptions', [
            'business_id' => $payment->business_id,
            'plan' => Subscription::PLAN_CLOUD,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
    }

    public function test_platform_admin_can_edit_and_delete_a_device(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = Device::query()->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS Lama',
            'identifier' => 'device-old',
            'platform' => 'android',
            'status' => Device::STATUS_INACTIVE,
            'registered_at' => now(),
        ]);

        $this->actingAs($admin)->patch(route('platform.devices.update', $device), [
            'name' => 'POS Baru',
            'identifier' => 'device-new',
            'platform' => 'windows',
            'outlet_id' => $outlet->id,
            'notes' => 'Updated by platform owner',
        ])->assertRedirect(route('platform.devices.show', $device));

        $this->assertDatabaseHas('devices', ['id' => $device->id, 'name' => 'POS Baru', 'identifier' => 'device-new']);

        $this->actingAs($admin)->delete(route('platform.devices.destroy', $device), [
            'confirmation' => 'DELETE',
        ])->assertRedirect(route('platform.devices.index'));

        $this->assertDatabaseMissing('devices', ['id' => $device->id]);
    }

    public function test_platform_admin_can_correct_and_delete_a_sync_record(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $business = Business::factory()->create();
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);
        $device = Device::query()->create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'name' => 'POS Sync',
            'identifier' => 'device-sync',
            'platform' => 'android',
            'status' => Device::STATUS_ACTIVE,
            'registered_at' => now(),
        ]);
        $syncRequest = SyncRequest::query()->create([
            'business_id' => $business->id,
            'device_id' => $device->id,
            'request_id' => (string) Str::uuid(),
            'processed_at' => Carbon::parse('2026-10-22 10:00:00'),
        ]);

        $this->actingAs($admin)->patch(route('platform.sync.update', $syncRequest), [
            'processed_at' => '2026-10-23 10:00:00',
        ])->assertRedirect(route('platform.sync.show', $syncRequest));

        $this->assertDatabaseHas('sync_requests', ['id' => $syncRequest->id, 'processed_at' => '2026-10-23 10:00:00']);

        $this->actingAs($admin)->delete(route('platform.sync.destroy', $syncRequest), [
            'confirmation' => 'DELETE',
        ])->assertRedirect(route('platform.sync.index'));

        $this->assertDatabaseMissing('sync_requests', ['id' => $syncRequest->id]);
    }
}
