<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Sale;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class DashboardCustomersData
{
    public const PER_PAGE = 25;

    /**
     * Customers are not soft-deleted in this schema. A "deleted" customer is a
     * row whose status column carries this terminal value.
     */
    public const DELETED_STATUS = 'deleted';

    private const RECENT_TRANSACTIONS_LIMIT = 10;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function get(?Business $currentBusiness, array $filters = []): array
    {
        $currentFilters = $this->sanitizeFilters($filters);

        if ($currentBusiness === null) {
            return $this->emptyResult($currentFilters);
        }

        $businessId = (int) $currentBusiness->id;

        $hasAnyCustomers = Customer::query()
            ->where('business_id', $businessId)
            ->exists();

        $purchaseConstraint = $this->purchaseConstraint($businessId);

        $summary = [
            'total_customers' => Customer::query()
                ->where('business_id', $businessId)
                ->where('status', '!=', self::DELETED_STATUS)
                ->count(),
            'active_customers' => Customer::query()
                ->where('business_id', $businessId)
                ->where('status', 'active')
                ->count(),
            'customers_with_purchases' => Customer::query()
                ->where('business_id', $businessId)
                ->where('status', '!=', self::DELETED_STATUS)
                ->whereHas('sales', $purchaseConstraint)
                ->count(),
            'customers_without_purchases' => Customer::query()
                ->where('business_id', $businessId)
                ->where('status', '!=', self::DELETED_STATUS)
                ->whereDoesntHave('sales', $purchaseConstraint)
                ->count(),
        ];

        $query = Customer::query()
            ->where('business_id', $businessId)
            ->withCount(['sales as completed_paid_count' => $purchaseConstraint])
            ->withSum(['sales as completed_paid_total' => $purchaseConstraint], 'total_amount')
            ->withMax(['sales as last_purchase_at' => $purchaseConstraint], 'sold_at');

        $this->applyFilters($query, $currentFilters);

        /** @var LengthAwarePaginator<int, Customer> $paginator */
        $paginator = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $paginator->through(fn (Customer $customer): array => $this->presentCustomer($customer));

        return [
            'customers' => $paginator,
            'summary' => $summary,
            'filterOptions' => $this->buildFilterOptions($businessId),
            'currentFilters' => $currentFilters,
            'hasAnyCustomers' => $hasAnyCustomers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Business $currentBusiness, int $customerId): array
    {
        $businessId = (int) $currentBusiness->id;

        /** @var Customer|null $customer */
        $customer = Customer::query()
            ->where('business_id', $businessId)
            ->where('id', $customerId)
            ->first();

        if ($customer === null) {
            abort(404);
        }

        $resolvedCustomerId = (int) $customer->id;

        $purchaseQuery = Sale::query()
            ->where('business_id', $businessId)
            ->where('customer_id', $resolvedCustomerId)
            ->where('status', 'completed')
            ->where('payment_status', 'paid');

        $transactionsCount = (clone $purchaseQuery)->count();
        $purchaseTotal = (int) (clone $purchaseQuery)->sum('total_amount');
        $firstPurchaseAt = (clone $purchaseQuery)->min('sold_at');
        $lastPurchaseAt = (clone $purchaseQuery)->max('sold_at');

        /** @var array<int, array<string, mixed>> $recentTransactions */
        $recentTransactions = Sale::query()
            ->where('business_id', $businessId)
            ->where('customer_id', $resolvedCustomerId)
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_TRANSACTIONS_LIMIT)
            ->get()
            ->map(fn (Sale $sale): array => $this->presentTransaction($sale))
            ->values()
            ->all();

        return [
            'id' => $resolvedCustomerId,
            'name' => (string) $customer->name,
            'phone' => $this->nullableString($customer->phone),
            'email' => $this->nullableString($customer->email),
            'address' => $this->nullableString($customer->address),
            'notes' => $this->nullableString($customer->notes),
            'status_raw' => (string) $customer->status,
            'status' => $this->presentCustomerStatus((string) $customer->status),
            'created_at' => $this->formatDateTime($customer->created_at),
            'metrics' => [
                'transactions_count' => $transactionsCount,
                'purchase_total' => $purchaseTotal,
                'first_purchase_at' => $this->formatDateTime($firstPurchaseAt),
                'last_purchase_at' => $this->formatDateTime($lastPurchaseAt),
            ],
            'recent_transactions' => $recentTransactions,
        ];
    }

    /**
     * Constraint for transactions that count as a purchase:
     * completed sale, paid payment, same tenant.
     *
     * @return Closure(Builder<Sale>): void
     */
    private function purchaseConstraint(int $businessId): Closure
    {
        return function (Builder $query) use ($businessId): void {
            $query->where('sales.business_id', $businessId)
                ->where('sales.status', 'completed')
                ->where('sales.payment_status', 'paid');
        };
    }

    /**
     * @param  Builder<Customer>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if ($filters['q'] !== '') {
            $q = (string) $filters['q'];
            $query->where(function (Builder $sub) use ($q): void {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($filters['status'] !== 'all') {
            $query->where('status', (string) $filters['status']);
        } else {
            $query->where('status', '!=', self::DELETED_STATUS);
        }
    }

    /**
     * @return array{statuses: array<int, array{value: string, label: string}>}
     */
    private function buildFilterOptions(int $businessId): array
    {
        $statuses = Customer::query()
            ->where('business_id', $businessId)
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->map(fn (string $status): array => [
                'value' => $status,
                'label' => $this->presentCustomerStatus($status),
            ])
            ->values()
            ->all();

        return ['statuses' => $statuses];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCustomer(Customer $customer): array
    {
        $statusRaw = (string) $customer->status;
        $lastPurchaseRaw = $customer->getAttribute('last_purchase_at');

        return [
            'id' => (int) $customer->id,
            'name' => (string) $customer->name,
            'phone' => $this->nullableString($customer->phone),
            'email' => $this->nullableString($customer->email),
            'status_raw' => $statusRaw,
            'status' => $this->presentCustomerStatus($statusRaw),
            'transactions_count' => (int) ($customer->getAttribute('completed_paid_count') ?? 0),
            'purchase_total' => (int) ($customer->getAttribute('completed_paid_total') ?? 0),
            'last_purchase_at_raw' => is_string($lastPurchaseRaw) ? $lastPurchaseRaw : null,
            'last_purchase_at' => $this->formatDateTime($lastPurchaseRaw),
        ];
    }

    /**
     * Recent history keeps the original transaction / payment status so
     * cancelled or unpaid rows never blend into purchase metrics.
     *
     * @return array<string, mixed>
     */
    private function presentTransaction(Sale $sale): array
    {
        $soldAt = $sale->sold_at;

        return [
            'id' => (int) $sale->id,
            'transaction_number' => (string) $sale->transaction_number,
            'sold_at' => $this->formatDateTime($soldAt),
            'sold_at_raw' => $soldAt->format('Y-m-d H:i:s'),
            'total_amount' => (int) $sale->total_amount,
            'payment_method_raw' => $sale->payment_method,
            'payment_method' => $this->presentPaymentMethod($sale->payment_method),
            'payment_status_raw' => (string) $sale->payment_status,
            'payment_status' => $this->presentPaymentStatus((string) $sale->payment_status),
            'status_raw' => (string) $sale->status,
            'status' => $this->presentSaleStatus((string) $sale->status),
        ];
    }

    private function presentCustomerStatus(string $raw): string
    {
        return match ($raw) {
            'active' => 'Aktif',
            'inactive' => 'Tidak Aktif',
            'deleted' => 'Dihapus',
            default => ucwords(str_replace(['_', '-'], ' ', $raw)),
        };
    }

    private function presentPaymentMethod(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return 'Tidak Diketahui';
        }

        return match (strtolower($raw)) {
            'cash', 'tunai' => 'Tunai',
            'qris' => 'QRIS',
            'transfer' => 'Transfer',
            'card', 'kartu' => 'Kartu',
            default => ucwords(str_replace('_', ' ', $raw)),
        };
    }

    private function presentPaymentStatus(string $raw): string
    {
        return match ($raw) {
            'paid' => 'Lunas',
            'unpaid' => 'Belum Lunas',
            default => ucwords(str_replace('_', ' ', $raw)),
        };
    }

    private function presentSaleStatus(string $raw): string
    {
        return match ($raw) {
            'completed' => 'Selesai',
            'cancelled', 'canceled' => 'Dibatalkan',
            default => ucwords(str_replace('_', ' ', $raw)),
        };
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function formatDateTime(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            $carbon = Carbon::parse((string) $date);
        } catch (\Throwable) {
            return null;
        }

        $carbon->setLocale('id');

        return $carbon->translatedFormat('d M Y - H:i');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function sanitizeFilters(array $filters): array
    {
        return [
            'q' => isset($filters['q']) ? trim((string) $filters['q']) : '',
            'status' => isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all'
                ? (string) $filters['status']
                : 'all',
        ];
    }

    /**
     * @param  array<string, mixed>  $currentFilters
     * @return array<string, mixed>
     */
    private function emptyResult(array $currentFilters): array
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        /** @var LengthAwarePaginator<int, array<string, mixed>> $empty */
        $empty = new LengthAwarePaginator([], 0, self::PER_PAGE, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'query' => request()->query(),
        ]);

        return [
            'customers' => $empty,
            'summary' => [
                'total_customers' => 0,
                'active_customers' => 0,
                'customers_with_purchases' => 0,
                'customers_without_purchases' => 0,
            ],
            'filterOptions' => ['statuses' => []],
            'currentFilters' => $currentFilters,
            'hasAnyCustomers' => false,
        ];
    }
}
