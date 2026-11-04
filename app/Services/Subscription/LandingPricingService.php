<?php

namespace App\Services\Subscription;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class LandingPricingService
{
    /**
     * Build dynamic pricing cards for the landing page directly from the database.
     *
     * @return Collection<int, array{
     *     code: string,
     *     name: string,
     *     badge: string,
     *     badge_style: string,
     *     description: string,
     *     price_formatted: string,
     *     price_period: string,
     *     features: list<string>,
     *     is_featured: bool,
     *     is_free: bool,
     *     period_key: string|null,
     *     cta_label: string,
     *     btn_class: string,
     * }>
     */
    public function cards(): Collection
    {
        $cards = collect();

        if (! Schema::hasTable('subscription_plans') || ! Schema::hasTable('subscription_plan_prices')) {
            return $this->fallbackCards();
        }

        /** @var Collection<int, SubscriptionPlan> $plans */
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->with(['prices' => function ($query) {
                $query->where('is_active', true);
            }])
            ->orderBy('id')
            ->get();

        $hasFreeInDb = false;

        foreach ($plans as $plan) {
            if ($plan->code === Subscription::PLAN_FREE) {
                $hasFreeInDb = true;
                $cards->push($this->buildFreeCard($plan));
            } elseif ($plan->code === Subscription::PLAN_CLOUD) {
                foreach ($this->buildCloudCards($plan) as $cloudCard) {
                    $cards->push($cloudCard);
                }
            } else {
                foreach ($this->buildCustomPlanCards($plan) as $customCard) {
                    $cards->push($customCard);
                }
            }
        }

        // If the Free plan hasn't been created in DB yet, provide the default Free plan card
        if (! $hasFreeInDb) {
            $cards->prepend($this->buildFreeCard(null));
        }

        return $cards;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFreeCard(?SubscriptionPlan $plan): array
    {
        $price = $plan?->prices->first();
        $priceFormatted = $price && $price->price_minor > 0
            ? 'Rp ' . number_format($price->price_minor, 0, ',', '.')
            : 'Rp 0';

        $features = ! empty($plan?->features) && is_array($plan->features)
            ? $plan->features
            : [
                'Aplikasi POS kasir offline cepat',
                'Transaksi &amp; cetak struk tanpa internet',
                'Katalog produk &amp; kategori lokal',
                'Hitung HPP &amp; pantau stok bahan',
                '1 perangkat kasir lokal',
            ];

        return [
            'code' => Subscription::PLAN_FREE,
            'name' => $plan?->name ?? 'Paket Free',
            'badge' => 'Gratis Selamanya',
            'badge_style' => 'background:#eaf8f1; color:#0d7a42;',
            'description' => $plan?->description ?: 'Mulai operasional kasir offline mandiri tanpa biaya langganan.',
            'price_formatted' => $priceFormatted,
            'price_period' => '/ selamanya',
            'features' => $features,
            'is_featured' => false,
            'is_free' => true,
            'period_key' => null,
            'cta_label' => 'Mulai Gratis',
            'btn_class' => 'pricing-btn pricing-btn-free',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildCloudCards(SubscriptionPlan $plan): array
    {
        $monthly = $plan->prices->firstWhere('billing_period', 'monthly');
        $yearly = $plan->prices->firstWhere('billing_period', 'yearly');

        $features = ! empty($plan->features) && is_array($plan->features)
            ? $plan->features
            : [
                'Semua fitur Paket Free',
                'Sinkronisasi Cloud otomatis &amp; real-time',
                'Dashboard operasional &amp; laba rugi',
                'Varian Menu (Cup, Sugar, Level)',
                'Hingga 5 perangkat kasir terhubung',
                'Manajemen Promo &amp; Diskon kasir',
                'Backup &amp; restore data cloud aman',
            ];

        $cards = [];

        // Monthly Card
        $monthlyAmount = $monthly ? 'Rp ' . number_format($monthly->price_minor, 0, ',', '.') : 'Segera hadir';
        $cards[] = [
            'code' => $plan->code,
            'name' => "{$plan->name} Monthly",
            'badge' => 'Bulanan',
            'badge_style' => '',
            'description' => $plan->description ?: 'Mulai dari paket fleksibel untuk operasional yang terus bergerak.',
            'price_formatted' => $monthlyAmount,
            'price_period' => $monthly ? '/ bulan' : '',
            'features' => $features,
            'is_featured' => false,
            'is_free' => false,
            'period_key' => 'monthly',
            'cta_label' => 'Pilih Bulanan',
            'btn_class' => 'pricing-btn pricing-btn-monthly',
        ];

        // Yearly Card (Featured)
        $yearlyAmount = $yearly ? 'Rp ' . number_format($yearly->price_minor, 0, ',', '.') : 'Segera hadir';
        $yearlyFeatures = array_values(array_unique(array_merge([
            'Lebih hemat 2 bulan langganan',
            'Prioritas sinkronisasi Cloud',
        ], $features)));

        $cards[] = [
            'code' => $plan->code,
            'name' => "{$plan->name} Yearly",
            'badge' => 'Hemat 2 Bulan • Rekomendasi',
            'badge_style' => '',
            'description' => 'Lebih praktis untuk bisnis yang ingin fokus tumbuh sepanjang tahun.',
            'price_formatted' => $yearlyAmount,
            'price_period' => $yearly ? '/ tahun' : '',
            'features' => $yearlyFeatures,
            'is_featured' => true,
            'is_free' => false,
            'period_key' => 'yearly',
            'cta_label' => 'Mulai Berlangganan',
            'btn_class' => 'pricing-btn pricing-btn-yearly',
        ];

        return $cards;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildCustomPlanCards(SubscriptionPlan $plan): array
    {
        $features = ! empty($plan->features) && is_array($plan->features)
            ? $plan->features
            : [
                'Semua fitur dasar POS',
                'Dukungan fitur paket ' . $plan->name,
                'Akses Cloud &amp; sinkronisasi',
                'Dukungan multi-perangkat',
            ];

        if ($plan->prices->isEmpty()) {
            return [[
                'code' => $plan->code,
                'name' => $plan->name,
                'badge' => 'Kustom',
                'badge_style' => '',
                'description' => $plan->description ?: 'Paket langganan khusus untuk bisnis Anda.',
                'price_formatted' => 'Hubungi Kami',
                'price_period' => '',
                'features' => $features,
                'is_featured' => false,
                'is_free' => false,
                'period_key' => null,
                'cta_label' => 'Pilih Paket',
                'btn_class' => 'pricing-btn pricing-btn-monthly',
            ]];
        }

        $cards = [];
        foreach ($plan->prices as $price) {
            $isYearly = $price->billing_period === 'yearly';
            $periodLabel = $isYearly ? 'Yearly' : ($price->billing_period === 'monthly' ? 'Monthly' : ucfirst($price->billing_period));
            $periodSuffix = $isYearly ? '/ tahun' : ($price->billing_period === 'monthly' ? '/ bulan' : "/ {$price->billing_period}");

            $cards[] = [
                'code' => $plan->code,
                'name' => "{$plan->name} {$periodLabel}",
                'badge' => $isYearly ? 'Tahunan' : 'Bulanan',
                'badge_style' => '',
                'description' => $plan->description ?: 'Paket langganan untuk bisnis Anda.',
                'price_formatted' => 'Rp ' . number_format($price->price_minor, 0, ',', '.'),
                'price_period' => $periodSuffix,
                'features' => $features,
                'is_featured' => $isYearly,
                'is_free' => false,
                'period_key' => $price->billing_period,
                'cta_label' => 'Pilih Paket',
                'btn_class' => $isYearly ? 'pricing-btn pricing-btn-yearly' : 'pricing-btn pricing-btn-monthly',
            ];
        }

        return $cards;
    }

    private function fallbackCards(): Collection
    {
        return collect([
            $this->buildFreeCard(null),
        ]);
    }
}
