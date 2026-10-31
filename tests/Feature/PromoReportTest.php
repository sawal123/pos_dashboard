<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Outlet;
use App\Models\Promo;
use App\Models\Sale;
use App\Models\SalePromotion;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_01_report_includes_promo_summary_and_discount_metrics(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $business = Business::factory()->create();
        $business->users()->attach($user->id, ['role' => 'owner']);
        Subscription::factory()->cloud()->create(['business_id' => $business->id]);
        $outlet = Outlet::factory()->create(['business_id' => $business->id]);

        $promo = Promo::create([
            'business_id' => $business->id,
            'name' => 'Diskon Akhir Pekan',
            'discount_type' => 'percent',
            'discount_value' => 1000,
            'scope' => 'all',
            'status' => 'active',
        ]);

        $sale = Sale::create([
            'business_id' => $business->id,
            'outlet_id' => $outlet->id,
            'transaction_number' => 'TRX-PROMO-1',
            'status' => 'completed',
            'subtotal' => 10000,
            'discount_amount' => 1000,
            'promo_sync_id' => $promo->sync_id,
            'tax_amount' => 0,
            'total_amount' => 9000,
            'payment_status' => 'paid',
            'sold_at' => now(),
        ]);

        SalePromotion::create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'promo_id' => $promo->id,
            'promo_sync_id' => $promo->sync_id,
            'discount_amount' => 1000,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['dashboard.current_business_id' => $business->id])
            ->get(route('reports.index'));

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary): bool => $summary['total_discount'] === 1000
            && $summary['promo_transactions'] === 1
            && $summary['manual_discount_transactions'] === 0);
        $response->assertViewHas('promoSummary', fn (array $rows): bool => count($rows) === 1
            && $rows[0]['promo_name'] === 'Diskon Akhir Pekan'
            && $rows[0]['total_discount'] === 1000);
    }
}
