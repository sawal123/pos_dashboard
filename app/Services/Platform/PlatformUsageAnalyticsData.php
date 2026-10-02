<?php

namespace App\Services\Platform;

use App\Models\Business;
use App\Models\Device;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\SyncRequest;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PlatformUsageAnalyticsData
{
    /**
     * Build aggregated platform usage analytics across all merchants.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function get(array $filters = []): array
    {
        $tz = (string) config('app.timezone', 'UTC');
        $now = Carbon::now($tz);

        // 1. Parse date filter
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

        // 2. Canonical Transaction Aggregates (status = 'completed')
        $periodSalesQuery = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('sold_at', [
                $startDate->toDateTimeString(),
                $endDate->toDateTimeString(),
            ]);

        $totalTransactions = (int) (clone $periodSalesQuery)->count();
        $totalTransactionValue = (int) (clone $periodSalesQuery)->sum('total_amount');
        $businessesWithTransactions = (int) (clone $periodSalesQuery)->distinct('business_id')->count('business_id');

        // 3. Sync Usage Aggregates (committed sync requests in period)
        $periodSyncQuery = SyncRequest::query()
            ->whereBetween('processed_at', [
                $startDate->toDateTimeString(),
                $endDate->toDateTimeString(),
            ]);

        $totalSyncRequests = (int) (clone $periodSyncQuery)->count();
        $businessesWithSync = (int) (clone $periodSyncQuery)->distinct('business_id')->count('business_id');
        $devicesWithSync = (int) (clone $periodSyncQuery)->distinct('device_id')->count('device_id');

        // 4. Device Activity (latest seen telemetry within last 30 days)
        $thirtyDaysAgo = $now->copy()->subDays(30)->toDateTimeString();
        $devicesSeen30Days = Device::query()
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $thirtyDaysAgo)
            ->count();
        $totalRegisteredDevices = Device::query()->count();

        // 5. Subscription Distribution (canonical Cloud entitlement semantics)
        $totalBusinesses = Business::query()->count();
        $cloudActiveBusinesses = Business::query()
            ->whereHas('subscription', function ($q) use ($now) {
                $q->where('plan', Subscription::PLAN_CLOUD)
                    ->where('status', Subscription::STATUS_ACTIVE)
                    ->where(function ($sub) use ($now) {
                        $sub->whereNull('expires_at')->orWhere('expires_at', '>', $now);
                    });
            })
            ->count();
        $nonCloudBusinesses = max(0, $totalBusinesses - $cloudActiveBusinesses);

        // 6. Merchant Members (business_user distinct count)
        $totalMerchantMembers = DB::table('business_user')
            ->distinct('user_id')
            ->count('user_id');

        // 7. Transaction Trend (zero-filled daily buckets)
        $trend = $this->buildDailyTrend($periodSalesQuery, $startDate, $endDate);

        // 8. Top Businesses by Transaction Activity (limit 10, eager loaded)
        $topBusinesses = $this->buildTopBusinesses($periodSalesQuery);

        return [
            'current_filters' => $currentFilters,
            'summary' => [
                'total_transactions' => $totalTransactions,
                'total_transaction_value' => $totalTransactionValue,
                'formatted_transaction_value' => 'Rp '.number_format($totalTransactionValue, 0, ',', '.'),
                'businesses_with_transactions' => $businessesWithTransactions,
                'businesses_with_sync' => $businessesWithSync,
                'devices_seen_30d' => $devicesSeen30Days,
                'total_registered_devices' => $totalRegisteredDevices,
            ],
            'sync_overview' => [
                'total_sync_requests' => $totalSyncRequests,
                'businesses_with_sync' => $businessesWithSync,
                'devices_with_sync' => $devicesWithSync,
            ],
            'subscription_distribution' => [
                'total_businesses' => $totalBusinesses,
                'cloud_active' => $cloudActiveBusinesses,
                'non_cloud' => $nonCloudBusinesses,
                'cloud_percentage' => $totalBusinesses > 0 ? (int) round(($cloudActiveBusinesses / $totalBusinesses) * 100) : 0,
            ],
            'members_overview' => [
                'total_merchant_members' => $totalMerchantMembers,
            ],
            'trend' => $trend,
            'top_businesses' => $topBusinesses,
            'metric_notes' => $this->getMetricNotes(),
        ];
    }

    /**
     * Resolve date range from preset or custom values.
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

            // Safe fallback if either date is unparseable
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
     * Build zero-filled daily buckets for the selected date range.
     *
     * @param  Builder<Sale>  $periodSalesQuery
     * @return array<string, mixed>
     */
    private function buildDailyTrend($periodSalesQuery, Carbon $startDate, Carbon $endDate): array
    {
        $rawRows = (clone $periodSalesQuery)
            ->selectRaw('DATE(sold_at) as date_raw, COUNT(id) as tx_count, SUM(total_amount) as tx_amount')
            ->groupByRaw('DATE(sold_at)')
            ->get()
            ->keyBy('date_raw');

        $intervals = [];
        $cursor = $startDate->copy()->startOfDay();
        $targetEnd = $endDate->copy()->startOfDay();

        while ($cursor->lte($targetEnd)) {
            $dateRaw = $cursor->format('Y-m-d');
            $row = $rawRows[$dateRaw] ?? null;
            $txCount = $row !== null ? (int) $row->getAttribute('tx_count') : 0;
            $txAmount = $row !== null ? (int) $row->getAttribute('tx_amount') : 0;

            $intervals[] = [
                'date' => $dateRaw,
                'label' => $this->formatDateLabel($cursor),
                'short_label' => $cursor->format('d M'),
                'count' => $txCount,
                'amount' => $txAmount,
                'formatted_amount' => 'Rp '.number_format($txAmount, 0, ',', '.'),
            ];

            $cursor->addDay();
        }

        $maxCount = max(array_merge([1], array_column($intervals, 'count')));
        $maxAmount = max(array_merge([1], array_column($intervals, 'amount')));
        $totalTransactions = array_sum(array_column($intervals, 'count'));
        $totalAmount = array_sum(array_column($intervals, 'amount'));

        return [
            'intervals' => $intervals,
            'max_count' => $maxCount,
            'max_amount' => $maxAmount,
            'total_count' => $totalTransactions,
            'total_amount' => $totalAmount,
            'formatted_total_amount' => 'Rp '.number_format($totalAmount, 0, ',', '.'),
        ];
    }

    /**
     * Build top 10 businesses sorted by transaction count and value.
     *
     * @param  Builder<Sale>  $periodSalesQuery
     * @return list<array<string, mixed>>
     */
    private function buildTopBusinesses($periodSalesQuery): array
    {
        $topStats = (clone $periodSalesQuery)
            ->selectRaw('business_id, COUNT(id) as transaction_count, SUM(total_amount) as transaction_value, MAX(sold_at) as last_transaction_at')
            ->groupBy('business_id')
            ->orderByDesc('transaction_count')
            ->orderByDesc('transaction_value')
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
            $txCount = (int) $stat->getAttribute('transaction_count');
            $txValue = (int) $stat->getAttribute('transaction_value');
            $lastAtRaw = $stat->getAttribute('last_transaction_at');

            $businessName = $business instanceof Business ? $business->name : 'Bisnis #'.$businessId;
            $businessSlug = $business instanceof Business ? $business->slug : '';
            $businessType = ($business instanceof Business && $business->business_type !== null) ? $business->business_type : 'Belum ditentukan';
            $hasCloud = $business instanceof Business && $business->hasCloudAccess();

            $result[] = [
                'business_id' => $businessId,
                'business_name' => $businessName,
                'business_slug' => $businessSlug,
                'business_type' => $businessType,
                'transaction_count' => $txCount,
                'transaction_value' => $txValue,
                'formatted_value' => 'Rp '.number_format($txValue, 0, ',', '.'),
                'last_transaction_at' => $lastAtRaw,
                'formatted_last_transaction' => $lastAtRaw !== null ? $this->formatDateTime(Carbon::parse($lastAtRaw)) : '-',
                'has_cloud_access' => $hasCloud,
                'cloud_status_label' => $hasCloud ? 'Cloud Aktif' : 'Free / Non-Cloud',
            ];
        }

        return $result;
    }

    /**
     * Canonical metric notes and domain boundaries.
     *
     * @return list<array<string, string>>
     */
    private function getMetricNotes(): array
    {
        return [
            [
                'title' => 'Transaksi & Nilai Transaksi',
                'description' => 'Dihitung berdasarkan transaksi berstatus selesai (completed) pada kolom sold_at. Nilai transaksi adalah omzet penjualan merchant agregat, bukan pendapatan SaaS platform.',
            ],
            [
                'title' => 'Aktivitas Sinkronisasi',
                'description' => 'Metrik sync hanya menghitung committed sync requests yang berhasil disimpan ke server. Percobaan gagal tidak dihitung karena server tidak menyimpan log request gagal.',
            ],
            [
                'title' => 'Perangkat Terlihat 30 Hari',
                'description' => 'Dihitung dari nilai telemetri terbaru (last_seen_at) dalam 30 hari terakhir. Nilai ini tidak mencerminkan riwayat harian masa lalu karena server hanya menyimpan satu timestamp terakhir.',
            ],
            [
                'title' => 'Ketiadaan Metrik Aktivitas Pengguna Harian',
                'description' => 'Aktivitas pengguna individual tidak tersedia karena model data server saat ini tidak menyimpan telemetri login pengguna maupun atribusi kasir pada baris transaksi.',
            ],
            [
                'title' => 'Ketiadaan Metrik Cloud Backup',
                'description' => 'Fitur Cloud Backup belum diimplementasikan di backend server sehingga metrik pencadangan, pemulihan, dan kuota penyimpanan cloud belum tersedia.',
            ],
            [
                'title' => 'Kerahasiaan Data Merchant',
                'description' => 'Halaman ini murni analitik agregat operasional. Detail sensitif tenant seperti nama pelanggan, nomor telepon, catatan struk, rincian barang, dan margin laba kotor (gross profit) tidak diekspos.',
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
