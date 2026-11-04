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
     *     cta_label: string,
     *     cta_style: string,
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

        return [
            'code' => Subscription::PLAN_FREE,
            'name' => $plan?->name ?? 'Paket Free',
            'badge' => 'Gratis Selamanya',
            'badge_style' => 'background:#eaf8f1; color:#0d7a42;',
            'description' => $plan?->description ?: 'Mulai operasional kasir offline mandiri tanpa biaya langganan.',
            'price_formatted' => $priceFormatted,
            'price_period' => '/ selamanya',
            'features' => [
                'Aplikasi POS kasir offline cepat',
                'Transaksi &amp; cetak struk tanpa internet',
                'Katalog produk &amp; kategori lokal',
                'Hitung HPP &amp; pantau stok bahan',
                '1 perangkat kasir lokal',
            ],
            'is_featured' => false,
            'cta_label' => 'Mulai Gratis',
            'cta_style' => 'button button-light',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildCloudCards(SubscriptionPlan $plan): array
    {
        $monthly = $plan->prices->firstWhere('billing_period', 'monthly');
        $yearly = $plan->prices->firstWhere('billing_period', 'yearly');

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
            'features' => [
                'Semua fitur Paket Free',
                'Sinkronisasi Cloud otomatis',
                'Dashboard operasional &amp; laba rugi',
                'Varian Menu (Cup, Sugar, Level)',
                'Hingga 5 perangkat kasir',
                'Manajemen Promo &amp; Diskon',
            ],
            'is_featured' => false,
            'cta_label' => 'Pilih Bulanan',
            'cta_style' => 'button button-light',
        ];

        // Yearly Card (Featured)
        $yearlyAmount = $yearly ? 'Rp ' . number_format($yearly->price_minor, 0, ',', '.') : 'Segera hadir';
        $cards[] = [
            'code' => $plan->code,
            'name' => "{$plan->name} Yearly",
            'badge' => 'Hemat 2 Bulan • Rekomendasi',
            'badge_style' => '',
            'description' => 'Lebih praktis untuk bisnis yang ingin fokus tumbuh sepanjang tahun.',
            'price_formatted' => $yearlyAmount,
            'price_period' => $yearly ? '/ tahun' : '',
            'features' => [
                'Semua fitur Cloud Bulanan',
                'Lebih hemat 2 bulan langganan',
                'Prioritas sinkronisasi Cloud',
                'Dashboard operasional &amp; laba rugi',
                'Varian Menu &amp; Multi-device (5 unit)',
                'Backup &amp; restore data cloud aman',
            ],
            'is_featured' => true,
            'cta_label' => 'Mulai Berlangganan',
            'cta_style' => 'button',
        ];

        return $cards;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildCustomPlanCards(SubscriptionPlan $plan): array
    {
        if ($plan->prices->isEmpty()) {
            return [[
                'code' => $plan->code,
                'name' => $plan->name,
                'badge' => 'Kustom',
                'badge_style' => '',
                'description' => $plan->description ?: 'Paket langganan khusus untuk bisnis Anda.',
                'price_formatted' => 'Hubungi Kami',
                'price_period' => '',
                'features' => [
                    'Fitur paket ' . $plan->name,
                    'Akses Cloud &amp; sinkronisasi',
                    'Dukungan multi-perangkat',
                ],
                'is_featured' => false,
                'cta_label' => 'Pilih Paket',
                'cta_style' => 'button button-light',
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
                'features' => [
                    'Semua fitur dasar POS',
                    'Dukungan fitur paket ' . $plan->name,
                    'Akses Cloud &amp; sinkronisasi',
                    'Dukungan multi-perangkat',
                ],
                'is_featured' => $isYearly,
                'cta_label' => 'Pilih Paket',
                'cta_style' => $isYearly ? 'button' : 'button button-light',
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
