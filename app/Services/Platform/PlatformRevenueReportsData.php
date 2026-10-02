<?php

namespace App\Services\Platform;

use App\Models\Business;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PlatformRevenueReportsData
{
    /**
     * Build aggregated platform revenue and subscription billing reports.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function get(array $filters = []): array
    {
        $tz = (string) config('app.timezone', 'UTC');
        $now = Carbon::now($tz);

        // 1. Date Filter Resolution
        $dateFilter = isset($filters['date']) && in_array($filters['date'], ['today', '7days', '30days', 'custom'], true)
            ? (string) $filters['date']
            : '30days';

        $startRaw = isset($filters['start_date']) ? trim((string) $filters['start_date']) : '';
        $endRaw = isset($filters['end_date']) ? trim((string) $filters['end_date']) : '';

        [$startDate, $endDate, $periodLabel] = $this->resolveDateRange($dateFilter, $startRaw, $endRaw, $now, $tz);

        $currentFilters = [
            'date' => $dateFilter,
            'start_date' => $startRaw,
            'end_date' => $endRaw,
            'period_label' => $periodLabel,
            'start_formatted' => $startDate->format('Y-m-d'),
            'end_formatted' => $endDate->format('Y-m-d'),
        ];

        // 2. Canonical Paid Revenue Query (status = 'paid' AND paid_at within period)
        $paidRevenueQuery = SubscriptionPayment::query()
            ->where('status', SubscriptionPayment::STATUS_PAID)
            ->whereBetween('paid_at', [
                $startDate->toDateTimeString(),
                $endDate->toDateTimeString(),
            ]);

        // Group by currency to prevent cross-currency summation errors
        $paidByCurrency = (clone $paidRevenueQuery)
            ->selectRaw('currency, COUNT(id) as paid_count, SUM(amount) as paid_amount')
            ->groupBy('currency')
            ->get();

        $totalPaidCount = (int) $paidByCurrency->sum('paid_count');
        $isMultiCurrency = $paidByCurrency->count() > 1;

        $currencyBreakdown = [];
        $totalPaidAmountIdr = 0;
        foreach ($paidByCurrency as $row) {
            $curr = (string) $row->getAttribute('currency');
            $cnt = (int) $row->getAttribute('paid_count');
            $amt = (int) $row->getAttribute('paid_amount');

            if ($curr === 'IDR') {
                $totalPaidAmountIdr = $amt;
            }

            $currencyBreakdown[$curr] = [
                'currency' => $curr,
                'count' => $cnt,
                'amount' => $amt,
                'formatted' => $this->formatMoney($amt, $curr),
            ];
        }

        // 3. Billing Period Breakdown (Monthly vs Yearly)
        $billingPeriodBreakdown = $this->buildBillingPeriodBreakdown($paidRevenueQuery);

        // 4. Payment Attempt Status (Created within period)
        $paymentStatusCounts = $this->buildPaymentStatusCounts($startDate, $endDate);

        // 5. Payment Activation Classification (First vs Subsequent / Renewal)
        $activationStats = $this->buildActivationStats($startDate, $endDate);

        // 6. Diagnostics & Reconciliation Flags
        $diagnostics = [
            'paid_not_activated' => SubscriptionPayment::query()
                ->where('status', SubscriptionPayment::STATUS_PAID)
                ->whereNull('activated_at')
                ->count(),
            'non_paid_with_activation' => SubscriptionPayment::query()
                ->where('status', '!=', SubscriptionPayment::STATUS_PAID)
                ->whereNotNull('activated_at')
                ->count(),
            'current_refunded_total' => SubscriptionPayment::query()
                ->where('status', SubscriptionPayment::STATUS_REFUNDED)
                ->count(),
        ];

        // 7. Revenue Trend (Daily Zero-Filled)
        $trend = $this->buildDailyTrend($paidRevenueQuery, $startDate, $endDate);

        // 8. Top Paying Businesses (Top 10)
        $topBusinesses = $this->buildTopBusinesses($startDate, $endDate);

        // 9. Current Subscription State (Landscape Snapshot)
        $currentSubscriptionSnapshot = $this->buildCurrentSubscriptionSnapshot($now);

        return [
            'current_filters' => $currentFilters,
            'summary' => [
                'total_paid_revenue' => $totalPaidAmountIdr,
                'formatted_paid_revenue' => $this->formatMoney($totalPaidAmountIdr, 'IDR'),
                'total_paid_payments' => $totalPaidCount,
                'is_multi_currency' => $isMultiCurrency,
                'currency_breakdown' => $currencyBreakdown,
                'first_activations' => $activationStats['first_activations'],
                'subsequent_activations' => $activationStats['subsequent_activations'],
                'monthly_revenue' => $billingPeriodBreakdown['monthly']['amount'],
                'formatted_monthly_revenue' => $billingPeriodBreakdown['monthly']['formatted'],
                'monthly_count' => $billingPeriodBreakdown['monthly']['count'],
                'yearly_revenue' => $billingPeriodBreakdown['yearly']['amount'],
                'formatted_yearly_revenue' => $billingPeriodBreakdown['yearly']['formatted'],
                'yearly_count' => $billingPeriodBreakdown['yearly']['count'],
            ],
            'billing_period_breakdown' => $billingPeriodBreakdown,
            'payment_status_distribution' => $paymentStatusCounts,
            'activation_stats' => $activationStats,
            'diagnostics' => $diagnostics,
            'trend' => $trend,
            'top_businesses' => $topBusinesses,
            'subscription_snapshot' => $currentSubscriptionSnapshot,
            'metric_notes' => $this->getMetricNotes(),
        ];
    }

    /**
     * Resolve date range from preset or custom input.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function resolveDateRange(string $preset, string $startRaw, string $endRaw, Carbon $now, string $tz): array
    {
        if ($preset === 'today') {
            return [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay(),
                'Hari Ini',
            ];
        }

        if ($preset === '7days') {
            return [
                $now->copy()->subDays(6)->startOfDay(),
                $now->copy()->endOfDay(),
                '7 Hari Terakhir',
            ];
        }

        if ($preset === 'custom') {
            $startDate = null;
            if ($startRaw !== '') {
                try {
                    $startDate = Carbon::createFromFormat('Y-m-d', $startRaw, $tz)?->startOfDay();
                } catch (\Throwable) {
                    $startDate = null;
                }
            }

            $endDate = null;
            if ($endRaw !== '') {
                try {
                    $endDate = Carbon::createFromFormat('Y-m-d', $endRaw, $tz)?->endOfDay();
                } catch (\Throwable) {
                    $endDate = null;
                }
            }

            if ($startDate === null && $endDate === null) {
                return [
                    $now->copy()->subDays(29)->startOfDay(),
                    $now->copy()->endOfDay(),
                    '30 Hari Terakhir',
                ];
            }

            if ($startDate === null) {
                $startDate = $endDate->copy()->subDays(29)->startOfDay();
            }
            if ($endDate === null) {
                $endDate = $now->copy()->endOfDay();
            }

            // Safe swap if reversed
            if ($startDate->gt($endDate)) {
                [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
            }

            return [
                $startDate,
                $endDate,
                $startDate->format('d/m/Y').' - '.$endDate->format('d/m/Y'),
            ];
        }

        // Default '30days'
        return [
            $now->copy()->subDays(29)->startOfDay(),
            $now->copy()->endOfDay(),
            '30 Hari Terakhir',
        ];
    }

    /**
     * Build monthly vs yearly breakdown from historical paid payment snapshots.
     *
     * @param  Builder<SubscriptionPayment>  $paidRevenueQuery
     * @return array<string, array{count: int, amount: int, formatted: string}>
     */
    private function buildBillingPeriodBreakdown(Builder $paidRevenueQuery): array
    {
        $rows = (clone $paidRevenueQuery)
            ->selectRaw('billing_period, COUNT(id) as count, SUM(amount) as amount')
            ->groupBy('billing_period')
            ->get()
            ->keyBy('billing_period');

        $monthlyCount = isset($rows['monthly']) ? (int) $rows['monthly']->getAttribute('count') : 0;
        $monthlyAmount = isset($rows['monthly']) ? (int) $rows['monthly']->getAttribute('amount') : 0;

        $yearlyCount = isset($rows['yearly']) ? (int) $rows['yearly']->getAttribute('count') : 0;
        $yearlyAmount = isset($rows['yearly']) ? (int) $rows['yearly']->getAttribute('amount') : 0;

        return [
            'monthly' => [
                'count' => $monthlyCount,
                'amount' => $monthlyAmount,
                'formatted' => $this->formatMoney($monthlyAmount, 'IDR'),
            ],
            'yearly' => [
                'count' => $yearlyCount,
                'amount' => $yearlyAmount,
                'formatted' => $this->formatMoney($yearlyAmount, 'IDR'),
            ],
        ];
    }

    /**
     * Build breakdown of payment attempt records created within the selected period.
     *
     * @return array<string, int>
     */
    private function buildPaymentStatusCounts(Carbon $startDate, Carbon $endDate): array
    {
        $counts = SubscriptionPayment::query()
            ->whereBetween('created_at', [
                $startDate->toDateTimeString(),
                $endDate->toDateTimeString(),
            ])
            ->selectRaw('status, COUNT(id) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'pending' => (int) ($counts[SubscriptionPayment::STATUS_PENDING] ?? 0),
            'paid' => (int) ($counts[SubscriptionPayment::STATUS_PAID] ?? 0),
            'failed' => (int) ($counts[SubscriptionPayment::STATUS_FAILED] ?? 0),
            'expired' => (int) ($counts[SubscriptionPayment::STATUS_EXPIRED] ?? 0),
            'cancelled' => (int) ($counts[SubscriptionPayment::STATUS_CANCELLED] ?? 0),
            'refunded' => (int) ($counts[SubscriptionPayment::STATUS_REFUNDED] ?? 0),
            'total' => array_sum($counts),
        ];
    }

    /**
     * Build payment activation classification (First vs Subsequent / Renewal).
     * Tie-breaker: (activated_at ASC, id ASC) per business.
     *
     * @return array{first_activations: int, subsequent_activations: int, total_activations: int}
     */
    private function buildActivationStats(Carbon $startDate, Carbon $endDate): array
    {
        // First activations in period: activated_at in range AND no earlier activated payment exists for same business
        $firstCount = SubscriptionPayment::query()
            ->whereBetween('activated_at', [
                $startDate->toDateTimeString(),
                $endDate->toDateTimeString(),
            ])
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('subscription_payments as p2')
                    ->whereColumn('p2.business_id', 'subscription_payments.business_id')
                    ->whereNotNull('p2.activated_at')
                    ->where(function ($sub): void {
                        $sub->whereColumn('p2.activated_at', '<', 'subscription_payments.activated_at')
                            ->orWhere(function ($tie): void {
                                $tie->whereColumn('p2.activated_at', '=', 'subscription_payments.activated_at')
                                    ->whereColumn('p2.id', '<', 'subscription_payments.id');
                            });
                    });
            })
            ->count();

        // Subsequent activations in period: activated_at in range AND an earlier activated payment exists for same business
        $subsequentCount = SubscriptionPayment::query()
            ->whereBetween('activated_at', [
                $startDate->toDateTimeString(),
                $endDate->toDateTimeString(),
            ])
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('subscription_payments as p2')
                    ->whereColumn('p2.business_id', 'subscription_payments.business_id')
                    ->whereNotNull('p2.activated_at')
                    ->where(function ($sub): void {
                        $sub->whereColumn('p2.activated_at', '<', 'subscription_payments.activated_at')
                            ->orWhere(function ($tie): void {
                                $tie->whereColumn('p2.activated_at', '=', 'subscription_payments.activated_at')
                                    ->whereColumn('p2.id', '<', 'subscription_payments.id');
                            });
                    });
            })
            ->count();

        return [
            'first_activations' => $firstCount,
            'subsequent_activations' => $subsequentCount,
            'total_activations' => $firstCount + $subsequentCount,
        ];
    }

    /**
     * Build zero-filled daily paid revenue trend.
     *
     * @param  Builder<SubscriptionPayment>  $paidRevenueQuery
     * @return array<string, mixed>
     */
    private function buildDailyTrend(Builder $paidRevenueQuery, Carbon $startDate, Carbon $endDate): array
    {
        $rawRows = (clone $paidRevenueQuery)
            ->selectRaw('DATE(paid_at) as date_raw, COUNT(id) as paid_count, SUM(amount) as paid_amount')
            ->groupByRaw('DATE(paid_at)')
            ->get()
            ->keyBy('date_raw');

        $intervals = [];
        $cursor = $startDate->copy()->startOfDay();
        $targetEnd = $endDate->copy()->startOfDay();

        while ($cursor->lte($targetEnd)) {
            $dateRaw = $cursor->format('Y-m-d');
            $row = $rawRows[$dateRaw] ?? null;
            $paidCount = $row !== null ? (int) $row->getAttribute('paid_count') : 0;
            $paidAmount = $row !== null ? (int) $row->getAttribute('paid_amount') : 0;

            $intervals[] = [
                'date' => $dateRaw,
                'label' => $this->formatDateLabel($cursor),
                'short_label' => $cursor->format('d M'),
                'count' => $paidCount,
                'amount' => $paidAmount,
                'formatted_amount' => $this->formatMoney($paidAmount, 'IDR'),
            ];

            $cursor->addDay();
        }

        $maxCount = max(array_merge([1], array_column($intervals, 'count')));
        $maxAmount = max(array_merge([1], array_column($intervals, 'amount')));
        $totalPaidCount = array_sum(array_column($intervals, 'count'));
        $totalPaidAmount = array_sum(array_column($intervals, 'amount'));

        return [
            'intervals' => $intervals,
            'max_count' => $maxCount,
            'max_amount' => $maxAmount,
            'total_count' => $totalPaidCount,
            'total_amount' => $totalPaidAmount,
            'formatted_total_amount' => $this->formatMoney($totalPaidAmount, 'IDR'),
        ];
    }

    /**
     * Build top 10 paying businesses within the selected period.
     *
     * @return list<array<string, mixed>>
     */
    private function buildTopBusinesses(Carbon $startDate, Carbon $endDate): array
    {
        $topStats = SubscriptionPayment::query()
            ->where('status', SubscriptionPayment::STATUS_PAID)
            ->whereBetween('paid_at', [
                $startDate->toDateTimeString(),
                $endDate->toDateTimeString(),
            ])
            ->selectRaw('business_id, COUNT(id) as paid_count, SUM(amount) as paid_revenue, MAX(paid_at) as last_paid_at, SUM(CASE WHEN billing_period = "monthly" THEN 1 ELSE 0 END) as monthly_count, SUM(CASE WHEN billing_period = "yearly" THEN 1 ELSE 0 END) as yearly_count')
            ->groupBy('business_id')
            ->orderByDesc('paid_revenue')
            ->orderByDesc('paid_count')
            ->orderBy('business_id')
            ->limit(10)
            ->get();

        if ($topStats->isEmpty()) {
            return [];
        }

        $businessIds = $topStats->pluck('business_id')->toArray();
        $businesses = Business::query()
            ->with('subscription')
            ->whereIn('id', $businessIds)
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($topStats as $stat) {
            $businessId = (int) $stat->getAttribute('business_id');
            $business = $businesses->get($businessId);
            $paidCount = (int) $stat->getAttribute('paid_count');
            $paidRevenue = (int) $stat->getAttribute('paid_revenue');
            $monthlyCount = (int) $stat->getAttribute('monthly_count');
            $yearlyCount = (int) $stat->getAttribute('yearly_count');
            $lastPaidRaw = $stat->getAttribute('last_paid_at');

            $businessName = $business instanceof Business ? $business->name : 'Bisnis #'.$businessId;
            $businessSlug = $business instanceof Business ? $business->slug : '';
            $hasCloud = $business instanceof Business && $business->hasCloudAccess();

            $result[] = [
                'business_id' => $businessId,
                'business_name' => $businessName,
                'business_slug' => $businessSlug,
                'paid_count' => $paidCount,
                'paid_revenue' => $paidRevenue,
                'formatted_revenue' => $this->formatMoney($paidRevenue, 'IDR'),
                'monthly_count' => $monthlyCount,
                'yearly_count' => $yearlyCount,
                'last_paid_at' => $lastPaidRaw,
                'formatted_last_paid' => $lastPaidRaw !== null ? $this->formatDateTime(Carbon::parse($lastPaidRaw)) : '-',
                'has_cloud_access' => $hasCloud,
                'cloud_status_label' => $hasCloud ? 'Cloud Aktif' : 'Free / Non-Cloud',
            ];
        }

        return $result;
    }

    /**
     * Build real-time landscape snapshot of all current subscriptions.
     *
     * @return array<string, int>
     */
    private function buildCurrentSubscriptionSnapshot(Carbon $now): array
    {
        $totalBusinesses = Business::query()->count();

        // 1. Cloud Active (date-aware: plan = cloud, status = active, expires_at null or future)
        $cloudActiveCount = Business::query()
            ->whereHas('subscription', function ($q) use ($now): void {
                $q->where('plan', Subscription::PLAN_CLOUD)
                    ->where('status', Subscription::STATUS_ACTIVE)
                    ->where(function ($sub) use ($now): void {
                        $sub->whereNull('expires_at')->orWhere('expires_at', '>', $now);
                    });
            })
            ->count();

        // 2. Cloud Expired (status = expired OR status = active with expires_at in past)
        $cloudExpiredCount = Business::query()
            ->whereHas('subscription', function ($q) use ($now): void {
                $q->where('plan', Subscription::PLAN_CLOUD)
                    ->where(function ($sub) use ($now): void {
                        $sub->where('status', Subscription::STATUS_EXPIRED)
                            ->orWhere(function ($activePast) use ($now): void {
                                $activePast->where('status', Subscription::STATUS_ACTIVE)
                                    ->whereNotNull('expires_at')
                                    ->where('expires_at', '<=', $now);
                            });
                    });
            })
            ->count();

        // 3. Cloud Inactive
        $cloudInactiveCount = Business::query()
            ->whereHas('subscription', function ($q): void {
                $q->where('plan', Subscription::PLAN_CLOUD)
                    ->where('status', Subscription::STATUS_INACTIVE);
            })
            ->count();

        // 4. Free / No Subscription
        $freeOrNoneCount = max(0, $totalBusinesses - $cloudActiveCount - $cloudExpiredCount - $cloudInactiveCount);

        return [
            'total_businesses' => $totalBusinesses,
            'cloud_active' => $cloudActiveCount,
            'cloud_expired' => $cloudExpiredCount,
            'cloud_inactive' => $cloudInactiveCount,
            'free_or_none' => $freeOrNoneCount,
        ];
    }

    /**
     * Format money value safely according to currency.
     */
    private function formatMoney(int $amount, string $currency): string
    {
        if ($currency === 'IDR') {
            return 'Rp '.number_format($amount, 0, ',', '.');
        }

        return $currency.' '.number_format($amount, 0, ',', '.');
    }

    /**
     * Canonical metric notes and operational limitations.
     *
     * @return list<array<string, string>>
     */
    private function getMetricNotes(): array
    {
        return [
            [
                'title' => 'Dasar Perhitungan Revenue Paid',
                'description' => 'Revenue dihitung dari status payment saat ini = paid pada timestamp paid_at. Payment yang kemudian berubah status menjadi refunded tidak lagi termasuk dalam revenue paid.',
            ],
            [
                'title' => 'Snapshot Nilai Historis Checkout',
                'description' => 'Revenue historis menggunakan nilai amount yang tersimpan pada baris pembayaran saat checkout, bukan harga katalog langganan saat ini.',
            ],
            [
                'title' => 'Status Payment Dibuat (created_at)',
                'description' => 'Distribusi status percobaan pembayaran (pending, failed, expired, cancelled) dihitung berdasarkan created_at karena database server tidak menyimpan timestamp transisi status khusus.',
            ],
            [
                'title' => 'Klasifikasi Aktivasi Pertama vs Lanjutan',
                'description' => 'Status aktivasi pertama vs lanjutan (renewal) diturunkan secara logis dari urutan aktivasi per bisnis (activated_at ASC, id ASC).',
            ],
            [
                'title' => 'Aktivasi Manual Platform Admin',
                'description' => 'Perubahan langganan yang dilakukan secara manual oleh Platform Admin (tanpa payment gateway) tidak termasuk dalam metrik aktivasi berbasis pembayaran.',
            ],
            [
                'title' => 'Pemisahan Snapshot Subscription Terkini',
                'description' => 'Panel kondisi langganan saat ini mencerminkan status real-time seluruh merchant saat ini, independen dari rentang tanggal filter pembayaran historis.',
            ],
        ];
    }

    /**
     * Format Carbon date for chart labels in Indonesian.
     */
    private function formatDateLabel(Carbon $carbon): string
    {
        $carbon->setLocale('id');

        return $carbon->translatedFormat('d M Y');
    }

    /**
     * Format a date for display.
     */
    private function formatDateTime(?DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $carbon = Carbon::parse($date);
        $carbon->setLocale('id');

        return $carbon->translatedFormat('d M Y, H:i');
    }
}
