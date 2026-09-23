<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Device;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\SyncCounter;
use App\Models\SyncRequest;
use App\Models\User;
use App\Services\Dashboard\DashboardSyncData;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncMonitoringPageTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // 1-3. Auth & Context
    // ============================================================

    public function test_1_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('sync.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_2_unverified_users_cannot_access_sync_page(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_3_verified_user_without_business_gets_200_and_empty_state(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $response = $this->get(route('sync.index'));
        $response->assertOk();
        $response->assertSee('Sinkronisasi');
        $response->assertSee('Belum Ada Riwayat Sinkronisasi');
    }

    // ============================================================
    // 4-6. Tenant Scoping & Switch Business
    // ============================================================

    public function test_4_current_business_sync_request_is_visible(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $req = $this->createSyncRequest([
            'business_id' => $business->id,
            'request_id' => '00000000-0000-0000-0000-000000000001',
        ]);

        $response = $this->actingAs($user)->get(route('sync.index'));
        $response->assertOk();
        $response->assertSee('00000000-0000-0000-0000-000000000001');
    }

    public function test_5_foreign_business_sync_request_is_not_visible(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $foreignBusiness = Business::factory()->create();
        $this->createSyncRequest([
            'business_id' => $foreignBusiness->id,
            'request_id' => '99999999-9999-9999-9999-999999999999',
        ]);

        $response = $this->actingAs($user)->get(route('sync.index'));
        $response->assertOk();
        $response->assertDontSee('99999999-9999-9999-9999-999999999999');
    }

    public function test_6_switching_current_business_changes_dataset(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $businessA = Business::factory()->create(['name' => 'Bisnis Alpha']);
        $businessB = Business::factory()->create(['name' => 'Bisnis Beta']);
        Subscription::factory()->create(['business_id' => $businessA->id]);
        Subscription::factory()->create(['business_id' => $businessB->id]);

        $user->businesses()->attach($businessA->id, ['role' => 'owner']);
        $user->businesses()->attach($businessB->id, ['role' => 'owner']);

        $reqA = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $reqB = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

        $this->createSyncRequest(['business_id' => $businessA->id, 'request_id' => $reqA]);
        $this->createSyncRequest(['business_id' => $businessB->id, 'request_id' => $reqB]);

        $resA = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $businessA->id])
            ->get(route('sync.index'));
        $resA->assertOk();
        $resA->assertSee($reqA);
        $resA->assertDontSee($reqB);

        $resB = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $businessB->id])
            ->get(route('sync.index'));
        $resB->assertOk();
        $resB->assertSee($reqB);
        $resB->assertDontSee($reqA);
    }

    // ============================================================
    // 7-15. Sync Counter, Summary Metrics & Invariance
    // ============================================================

    public function test_7_sync_counter_current_business_is_correct(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        SyncCounter::where('business_id', $business->id)->update([
            'current_sequence' => 450,
        ]);

        $response = $this->actingAs($user)->get(route('sync.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary) {
            return $summary['server_sequence'] === 450;
        });
    }

    public function test_8_sync_counter_foreign_tenant_does_not_leak(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $foreign = Business::factory()->create();

        SyncCounter::where('business_id', $business->id)->update(['current_sequence' => 10]);
        SyncCounter::where('business_id', $foreign->id)->update(['current_sequence' => 99999]);

        $response = $this->actingAs($user)->get(route('sync.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary) {
            return $summary['server_sequence'] === 10;
        });
    }

    public function test_9_missing_sync_counter_is_safe_and_renders_zero(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        SyncCounter::where('business_id', $business->id)->delete();

        $response = $this->actingAs($user)->get(route('sync.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary) {
            return $summary['server_sequence'] === 0;
        });
    }

    public function test_10_server_sequence_is_not_derived_from_count_requests(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        SyncCounter::where('business_id', $business->id)->update(['current_sequence' => 500]);

        $this->createSyncRequest(['business_id' => $business->id]);
        $this->createSyncRequest(['business_id' => $business->id]);

        $response = $this->actingAs($user)->get(route('sync.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary) {
            return $summary['server_sequence'] === 500 && $summary['total_requests'] === 2;
        });
    }

    public function test_11_total_requests_is_tenant_scoped(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $foreign = Business::factory()->create();

        $this->createSyncRequest(['business_id' => $business->id]);
        $this->createSyncRequest(['business_id' => $business->id]);
        $this->createSyncRequest(['business_id' => $foreign->id]);

        $response = $this->actingAs($user)->get(route('sync.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary) {
            return $summary['total_requests'] === 2;
        });
    }

    public function test_12_devices_with_push_is_distinct_device_count(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $dev1 = $this->createDevice(['business_id' => $business->id]);
        $dev2 = $this->createDevice(['business_id' => $business->id]);

        $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $dev1->id]);
        $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $dev1->id]);
        $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $dev2->id]);

        $response = $this->actingAs($user)->get(route('sync.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary) {
            return $summary['devices_with_push'] === 2;
        });
    }

    public function test_13_last_processed_uses_max_processed_at(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $timeEarlier = Carbon::parse('2026-09-20 10:00:00');
        $timeLater = Carbon::parse('2026-09-22 15:30:00');

        $this->createSyncRequest(['business_id' => $business->id, 'processed_at' => $timeEarlier]);
        $this->createSyncRequest(['business_id' => $business->id, 'processed_at' => $timeLater]);

        $response = $this->actingAs($user)->get(route('sync.index'));
        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary) {
            return str_contains($summary['last_processed'], '22 Sep 2026');
        });
    }

    public function test_14_summary_does_not_change_on_page_2(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        for ($i = 1; $i <= 30; $i++) {
            $this->createSyncRequest(['business_id' => $business->id]);
        }

        $res1 = $this->actingAs($user)->get(route('sync.index', ['page' => 1]));
        $res2 = $this->actingAs($user)->get(route('sync.index', ['page' => 2]));

        $res1->assertOk();
        $res2->assertOk();

        $this->assertSame(
            $res1->viewData('summary'),
            $res2->viewData('summary')
        );
    }

    public function test_15_summary_does_not_change_due_to_list_filters(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSyncRequest(['business_id' => $business->id]);

        $resAll = $this->actingAs($user)->get(route('sync.index'));
        $resFiltered = $this->actingAs($user)->get(route('sync.index', ['q' => 'NonExistent']));

        $resAll->assertOk();
        $resFiltered->assertOk();

        $this->assertSame(
            $resAll->viewData('summary'),
            $resFiltered->viewData('summary')
        );
    }

    // ============================================================
    // 16-20. Presentation, Sorting & Relationships
    // ============================================================

    public function test_16_request_sort_is_processed_at_desc_and_id_desc(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $req1 = $this->createSyncRequest([
            'business_id' => $business->id,
            'request_id' => '11111111-1111-1111-1111-111111111111',
            'processed_at' => now()->subHours(2),
        ]);
        $req2 = $this->createSyncRequest([
            'business_id' => $business->id,
            'request_id' => '22222222-2222-2222-2222-222222222222',
            'processed_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($user)->get(route('sync.index'));
        $response->assertOk();

        $requests = $response->viewData('requests');
        $this->assertSame($req2->request_id, $requests->first()['request_id']);
    }

    public function test_17_request_id_renders_completely(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $uuid = (string) Str::uuid();

        $this->createSyncRequest([
            'business_id' => $business->id,
            'request_id' => $uuid,
        ]);

        $response = $this->actingAs($user)->get(route('sync.index'));
        $response->assertOk();
        $response->assertSee($uuid);
    }

    public function test_18_device_name_and_identifier_relations_are_accurate(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $device = $this->createDevice([
            'business_id' => $business->id,
            'name' => 'Tablet Kasir Kasir',
            'identifier' => 'device-ident-789',
        ]);

        $this->createSyncRequest([
            'business_id' => $business->id,
            'device_id' => $device->id,
        ]);

        $response = $this->actingAs($user)->get(route('sync.index'));
        $response->assertOk();
        $response->assertSee('Tablet Kasir Kasir');
        $response->assertSee('device-ident-789');
    }

    public function test_19_missing_device_relation_is_safe_and_renders_fallback(): void
    {
        $service = app(DashboardSyncData::class);
        $req = new SyncRequest([
            'business_id' => 1,
            'device_id' => 999,
            'request_id' => (string) Str::uuid(),
            'processed_at' => now(),
        ]);
        $req->id = 1;
        $req->setRelation('device', null);

        $presented = $service->presentSyncRequest($req, 1);
        $this->assertSame('-', $presented['device_name']);
        $this->assertSame('-', $presented['device_identifier']);

        // When device belongs to another tenant
        $foreignDevice = new Device([
            'name' => 'Foreign POS',
            'identifier' => 'foreign-001',
        ]);
        $foreignDevice->id = 999;
        $foreignDevice->business_id = 99; // foreign business
        $req->setRelation('device', $foreignDevice);

        $presentedForeign = $service->presentSyncRequest($req, 1);
        $this->assertSame('-', $presentedForeign['device_name']);
        $this->assertSame('-', $presentedForeign['device_identifier']);
    }

    public function test_20_nullable_or_missing_outlet_relation_is_safe(): void
    {
        $service = app(DashboardSyncData::class);
        $device = new Device([
            'name' => 'POS Kasir',
            'identifier' => 'pos-001',
        ]);
        $device->id = 1;
        $device->business_id = 1;
        $device->setRelation('outlet', null);

        $req = new SyncRequest([
            'business_id' => 1,
            'device_id' => 1,
            'request_id' => (string) Str::uuid(),
            'processed_at' => now(),
        ]);
        $req->id = 1;
        $req->setRelation('device', $device);

        $presented = $service->presentSyncRequest($req, 1);
        $this->assertSame('POS Kasir', $presented['device_name']);
        $this->assertSame('-', $presented['outlet_name']);
    }

    // ============================================================
    // 21-24. Search Filters
    // ============================================================

    public function test_21_search_by_request_id(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSyncRequest(['business_id' => $business->id, 'request_id' => 'aaaa1111-0000-0000-0000-000000000000']);
        $this->createSyncRequest(['business_id' => $business->id, 'request_id' => 'bbbb2222-0000-0000-0000-000000000000']);

        $response = $this->actingAs($user)->get(route('sync.index', ['q' => 'aaaa1111']));
        $response->assertOk();
        $response->assertSee('aaaa1111-0000-0000-0000-000000000000');
        $response->assertDontSee('bbbb2222-0000-0000-0000-000000000000');
    }

    public function test_22_search_by_device_name(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $dev1 = $this->createDevice(['business_id' => $business->id, 'name' => 'Tablet Waiter Zebra']);
        $dev2 = $this->createDevice(['business_id' => $business->id, 'name' => 'Tablet Kasir Utama']);

        $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $dev1->id, 'request_id' => '1111-uuid-zebra']);
        $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $dev2->id, 'request_id' => '2222-uuid-kasir']);

        $response = $this->actingAs($user)->get(route('sync.index', ['q' => 'Zebra']));
        $response->assertOk();
        $response->assertSee('1111-uuid-zebra');
        $response->assertDontSee('2222-uuid-kasir');
    }

    public function test_23_search_by_device_identifier(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $dev1 = $this->createDevice(['business_id' => $business->id, 'identifier' => 'device-mac-445566']);
        $dev2 = $this->createDevice(['business_id' => $business->id, 'identifier' => 'device-mac-112233']);

        $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $dev1->id, 'request_id' => 'req-dev-445566']);
        $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $dev2->id, 'request_id' => 'req-dev-112233']);

        $response = $this->actingAs($user)->get(route('sync.index', ['q' => '445566']));
        $response->assertOk();
        $response->assertSee('req-dev-445566');
        $response->assertDontSee('req-dev-112233');
    }

    public function test_24_grouped_or_search_is_tenant_safe(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $foreign = Business::factory()->create();

        $foreignDevice = $this->createDevice(['business_id' => $foreign->id, 'name' => 'Special Secret Dev']);
        $this->createSyncRequest([
            'business_id' => $foreign->id,
            'device_id' => $foreignDevice->id,
            'request_id' => 'foreign-secret-request',
        ]);

        $response = $this->actingAs($user)->get(route('sync.index', ['q' => 'Special']));
        $response->assertOk();
        $response->assertDontSee('foreign-secret-request');
    }

    // ============================================================
    // 25-28. Device and Outlet Filters
    // ============================================================

    public function test_25_device_id_filter_exact(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $dev1 = $this->createDevice(['business_id' => $business->id]);
        $dev2 = $this->createDevice(['business_id' => $business->id]);

        $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $dev1->id, 'request_id' => 'req-dev-1']);
        $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $dev2->id, 'request_id' => 'req-dev-2']);

        $response = $this->actingAs($user)->get(route('sync.index', ['device_id' => $dev1->id]));
        $response->assertOk();
        $response->assertSee('req-dev-1');
        $response->assertDontSee('req-dev-2');
    }

    public function test_26_foreign_device_id_does_not_leak(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $foreign = Business::factory()->create();
        $foreignDevice = $this->createDevice(['business_id' => $foreign->id]);

        $this->createSyncRequest([
            'business_id' => $foreign->id,
            'device_id' => $foreignDevice->id,
            'request_id' => 'foreign-dev-req',
        ]);

        $response = $this->actingAs($user)->get(route('sync.index', ['device_id' => $foreignDevice->id]));
        $response->assertOk();
        $response->assertDontSee('foreign-dev-req');
    }

    public function test_27_outlet_id_filter_exact(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $outletA = Outlet::factory()->create(['business_id' => $business->id]);
        $outletB = Outlet::factory()->create(['business_id' => $business->id]);

        $devA = $this->createDevice(['business_id' => $business->id, 'outlet_id' => $outletA->id]);
        $devB = $this->createDevice(['business_id' => $business->id, 'outlet_id' => $outletB->id]);

        $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $devA->id, 'request_id' => 'req-outlet-a']);
        $this->createSyncRequest(['business_id' => $business->id, 'device_id' => $devB->id, 'request_id' => 'req-outlet-b']);

        $response = $this->actingAs($user)->get(route('sync.index', ['outlet_id' => $outletA->id]));
        $response->assertOk();
        $response->assertSee('req-outlet-a');
        $response->assertDontSee('req-outlet-b');
    }

    public function test_28_foreign_outlet_id_does_not_leak(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $foreign = Business::factory()->create();
        $foreignOutlet = Outlet::factory()->create(['business_id' => $foreign->id]);
        $foreignDevice = $this->createDevice(['business_id' => $foreign->id, 'outlet_id' => $foreignOutlet->id]);

        $this->createSyncRequest([
            'business_id' => $foreign->id,
            'device_id' => $foreignDevice->id,
            'request_id' => 'foreign-outlet-req',
        ]);

        $response = $this->actingAs($user)->get(route('sync.index', ['outlet_id' => $foreignOutlet->id]));
        $response->assertOk();
        $response->assertDontSee('foreign-outlet-req');
    }

    // ============================================================
    // 29-34. Date Filters
    // ============================================================

    public function test_29_today_date_filter(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSyncRequest([
            'business_id' => $business->id,
            'request_id' => 'today-req',
            'processed_at' => now(),
        ]);
        $this->createSyncRequest([
            'business_id' => $business->id,
            'request_id' => 'yesterday-req',
            'processed_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get(route('sync.index', ['date' => 'today']));
        $response->assertOk();
        $response->assertSee('today-req');
        $response->assertDontSee('yesterday-req');
    }

    public function test_30_7days_date_filter(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSyncRequest([
            'business_id' => $business->id,
            'request_id' => 'within-7d',
            'processed_at' => now()->subDays(3),
        ]);
        $this->createSyncRequest([
            'business_id' => $business->id,
            'request_id' => 'older-than-7d',
            'processed_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($user)->get(route('sync.index', ['date' => '7days']));
        $response->assertOk();
        $response->assertSee('within-7d');
        $response->assertDontSee('older-than-7d');
    }

    public function test_31_30days_date_filter(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSyncRequest([
            'business_id' => $business->id,
            'request_id' => 'within-30d',
            'processed_at' => now()->subDays(20),
        ]);
        $this->createSyncRequest([
            'business_id' => $business->id,
            'request_id' => 'older-than-30d',
            'processed_at' => now()->subDays(35),
        ]);

        $response = $this->actingAs($user)->get(route('sync.index', ['date' => '30days']));
        $response->assertOk();
        $response->assertSee('within-30d');
        $response->assertDontSee('older-than-30d');
    }

    public function test_32_custom_date_filter(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSyncRequest([
            'business_id' => $business->id,
            'request_id' => 'custom-match',
            'processed_at' => Carbon::parse('2026-05-15 12:00:00'),
        ]);
        $this->createSyncRequest([
            'business_id' => $business->id,
            'request_id' => 'custom-out',
            'processed_at' => Carbon::parse('2026-06-01 12:00:00'),
        ]);

        $response = $this->actingAs($user)->get(route('sync.index', [
            'date' => 'custom',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-20',
        ]));
        $response->assertOk();
        $response->assertSee('custom-match');
        $response->assertDontSee('custom-out');
    }

    public function test_33_reversed_custom_date_swaps_safely(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSyncRequest([
            'business_id' => $business->id,
            'request_id' => 'reversed-custom-match',
            'processed_at' => Carbon::parse('2026-05-15 12:00:00'),
        ]);

        $response = $this->actingAs($user)->get(route('sync.index', [
            'date' => 'custom',
            'start_date' => '2026-05-20',
            'end_date' => '2026-05-01',
        ]));
        $response->assertOk();
        $response->assertSee('reversed-custom-match');
    }

    public function test_34_invalid_date_does_not_cause_500(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('sync.index', [
            'date' => 'custom',
            'start_date' => 'invalid-date-format',
            'end_date' => 'not-a-date',
        ]));
        $response->assertOk();
    }

    // ============================================================
    // 35-36. Pagination & Query String Preservation
    // ============================================================

    public function test_35_pagination_25_records_per_page(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        for ($i = 1; $i <= 30; $i++) {
            $this->createSyncRequest([
                'business_id' => $business->id,
                'processed_at' => now()->subMinutes(40 - $i),
            ]);
        }

        $response = $this->actingAs($user)->get(route('sync.index'));
        $response->assertOk();
        $response->assertViewHas('requests', function ($requests) {
            return $requests->count() === 25 && $requests->total() === 30;
        });
    }

    public function test_36_query_string_preserved_in_pagination(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        for ($i = 1; $i <= 30; $i++) {
            $this->createSyncRequest([
                'business_id' => $business->id,
                'processed_at' => now()->subMinutes(40 - $i),
            ]);
        }

        $response = $this->actingAs($user)->get(route('sync.index', ['date' => 'today', 'page' => 2]));
        $response->assertOk();
        $response->assertSee('date=today', false);
    }

    // ============================================================
    // 37-44. Empty States, Integrity & Anti-Fabrication
    // ============================================================

    public function test_37_initial_empty_state(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('sync.index'));
        $response->assertOk();
        $response->assertSee('Belum Ada Riwayat Sinkronisasi');
        $response->assertDontSee('Riwayat Sinkronisasi Tidak Ditemukan');
    }

    public function test_38_filtered_empty_state_with_reset_link(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSyncRequest(['business_id' => $business->id]);

        $response = $this->actingAs($user)->get(route('sync.index', ['q' => 'NonExistentRequestId']));
        $response->assertOk();
        $response->assertSee('Riwayat Sinkronisasi Tidak Ditemukan');
        $response->assertSee(route('sync.index'));
    }

    public function test_39_empty_db_does_not_display_fixture_request_ids(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();

        $response = $this->actingAs($user)->get(route('sync.index'));
        $response->assertOk();
        $response->assertDontSee('6d736a0a-552a-4fa5-889c-01b61e430001');
    }

    public function test_40_page_does_not_require_sync_fixtures(): void
    {
        $viewContent = file_get_contents(resource_path('views/sync/index.blade.php'));
        $this->assertStringNotContainsString('views/sync/fixtures.php', $viewContent);
    }

    public function test_41_no_fake_pending_failed_or_conflict_metrics(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSyncRequest(['business_id' => $business->id]);

        $response = $this->actingAs($user)->get(route('sync.index'));
        $response->assertOk();
        $response->assertDontSee('Pending Sync');
        $response->assertDontSee('Conflict Count');
        $response->assertDontSee('Failed Sync');
        $response->assertDontSee('Success Rate');
    }

    public function test_42_no_fabricated_online_or_offline_state(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSyncRequest(['business_id' => $business->id]);

        $response = $this->actingAs($user)->get(route('sync.index'));
        $response->assertOk();
        $response->assertDontSee('Online Devices');
        $response->assertDontSee('Offline Devices');
    }

    public function test_43_drawer_remains_accessible_and_rendered(): void
    {
        [$user, $business] = $this->makeUserWithBusiness();
        $this->createSyncRequest(['business_id' => $business->id]);

        $response = $this->actingAs($user)->get(route('sync.index'));
        $response->assertOk();
        $response->assertSee('id="sync-detail-drawer"', false);
    }

    public function test_44_request_detail_does_not_expose_foreign_tenant_data(): void
    {
        $service = app(DashboardSyncData::class);
        $foreignOutlet = new Outlet(['name' => 'Outlet Rahasia Asing']);
        $foreignOutlet->id = 99;
        $foreignOutlet->business_id = 2; // foreign

        $foreignDevice = new Device(['name' => 'Foreign Confidential POS']);
        $foreignDevice->id = 88;
        $foreignDevice->business_id = 2; // foreign
        $foreignDevice->setRelation('outlet', $foreignOutlet);

        $req = new SyncRequest([
            'business_id' => 1, // current business
            'device_id' => 88,
            'request_id' => 'req-foreign-device-link',
            'processed_at' => now(),
        ]);
        $req->id = 1;
        $req->setRelation('device', $foreignDevice);

        $presented = $service->presentSyncRequest($req, 1);
        $this->assertSame('-', $presented['device_name']);
        $this->assertSame('-', $presented['outlet_name']);
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * @return array{0: User, 1: Business}
     */
    private function makeUserWithBusiness(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        Subscription::factory()->create(['business_id' => $business->id]);
        $user->businesses()->attach($business->id, ['role' => 'owner']);

        $this->withSession(['dashboard.current_business_id' => $business->id]);

        return [$user, $business];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDevice(array $attributes = []): Device
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;
        $outletId = $attributes['outlet_id'] ?? Outlet::factory()->create(['business_id' => $businessId])->id;

        return Device::create(array_merge([
            'business_id' => $businessId,
            'outlet_id' => $outletId,
            'name' => 'Device '.Str::random(6),
            'identifier' => 'device-'.Str::random(10),
            'platform' => 'Android',
            'status' => 'active',
            'registered_at' => now(),
            'last_seen_at' => now(),
            'notes' => null,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSyncRequest(array $attributes = []): SyncRequest
    {
        $businessId = $attributes['business_id'] ?? Business::factory()->create()->id;
        $deviceId = $attributes['device_id'] ?? $this->createDevice(['business_id' => $businessId])->id;

        return SyncRequest::create(array_merge([
            'business_id' => $businessId,
            'device_id' => $deviceId,
            'request_id' => (string) Str::uuid(),
            'processed_at' => now(),
        ], $attributes));
    }
}
