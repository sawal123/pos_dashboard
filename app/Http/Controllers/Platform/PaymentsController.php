<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    /**
     * Display a listing of subscription payments with summary metrics, search, filtering, and pagination.
     */
    public function index(Request $request): View
    {
        // Server-side aggregates for summary cards
        $summary = [
            'total' => SubscriptionPayment::query()->count(),
            'pending' => SubscriptionPayment::query()->where('status', SubscriptionPayment::STATUS_PENDING)->count(),
            'paid' => SubscriptionPayment::query()->where('status', SubscriptionPayment::STATUS_PAID)->count(),
            'failed' => SubscriptionPayment::query()->whereIn('status', [
                SubscriptionPayment::STATUS_FAILED,
                SubscriptionPayment::STATUS_EXPIRED,
                SubscriptionPayment::STATUS_CANCELLED,
                SubscriptionPayment::STATUS_REFUNDED,
            ])->count(),
            'total_paid_amount' => (int) SubscriptionPayment::query()
                ->where('status', SubscriptionPayment::STATUS_PAID)
                ->sum('amount'),
        ];

        $query = SubscriptionPayment::query()
            ->with(['business' => function ($bQuery) {
                $bQuery->select('id', 'name', 'slug', 'status');
            }]);

        // Search by provider_order_id, idempotency_key, business name, or slug
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('provider_order_id', 'like', "%{$search}%")
                    ->orWhere('idempotency_key', 'like', "%{$search}%")
                    ->orWhereHas('business', function ($bQuery) use ($search) {
                        $bQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by canonical status
        $status = $request->input('status');
        $validStatuses = [
            SubscriptionPayment::STATUS_PENDING,
            SubscriptionPayment::STATUS_PAID,
            SubscriptionPayment::STATUS_FAILED,
            SubscriptionPayment::STATUS_EXPIRED,
            SubscriptionPayment::STATUS_CANCELLED,
            SubscriptionPayment::STATUS_REFUNDED,
        ];
        if (in_array($status, $validStatuses, true)) {
            $query->where('status', $status);
        } else {
            $status = null;
        }

        // Filter by billing period (monthly / yearly)
        $billingPeriod = $request->input('billing_period');
        if (in_array($billingPeriod, ['monthly', 'yearly'], true)) {
            $query->where('billing_period', $billingPeriod);
        } else {
            $billingPeriod = null;
        }

        // Filter by plan snapshot (cloud / free)
        $plan = $request->input('plan');
        if (in_array($plan, [Subscription::PLAN_CLOUD, Subscription::PLAN_FREE], true)) {
            $query->where('plan', $plan);
        } else {
            $plan = null;
        }

        $payments = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('platform.payments.index', [
            'payments' => $payments,
            'summary' => $summary,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'billing_period' => $billingPeriod,
                'plan' => $plan,
            ],
        ]);
    }

    /**
     * Display the specified payment detail with business, subscription snapshot, timeline, and reconciliation diagnostic.
     */
    public function show(SubscriptionPayment $payment): View
    {
        $payment->load([
            'business' => function ($query) {
                $query->with([
                    'subscription',
                    'owners:id,name,email',
                ]);
            },
            'user:id,name,email',
        ]);

        $business = $payment->business;
        $subscription = $business->subscription;
        $hasCloudAccess = $business->hasCloudAccess();

        // Historical payment activation diagnostic (decoupled from current subscription state)
        if ($payment->status === SubscriptionPayment::STATUS_PAID) {
            if ($payment->activated_at !== null) {
                $isExpiredPeriod = $payment->expires_at !== null && $payment->expires_at->isPast();

                if ($isExpiredPeriod) {
                    $summary = 'Aktivasi pembayaran tercatat dan masa entitlement dari transaksi ini telah berakhir.';
                    $infoNotice = 'Aktivasi pembayaran tercatat dan masa entitlement dari transaksi ini telah berakhir.';
                } else {
                    $summary = 'Pembayaran berstatus berhasil dan waktu aktivasi langganan tercatat.';
                    $infoNotice = (! $hasCloudAccess || ($subscription && $subscription->plan !== $payment->plan))
                        ? 'Status subscription saat ini berbeda dari snapshot transaksi.'
                        : null;
                }

                $diagnostic = [
                    'status' => 'healthy',
                    'label' => 'Konsisten',
                    'badge_class' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60',
                    'dot_class' => 'bg-emerald-500',
                    'icon' => 'check-circle-2',
                    'summary' => $summary,
                    'info_notice' => $infoNotice,
                    'reasons' => [],
                    'is_consistent' => true,
                ];
            } else {
                $diagnostic = [
                    'status' => 'mismatch',
                    'label' => 'Perlu Pemeriksaan',
                    'badge_class' => 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border border-amber-200/60 dark:border-amber-800/60',
                    'dot_class' => 'bg-amber-500',
                    'icon' => 'alert-triangle',
                    'summary' => 'Pembayaran berhasil tetapi aktivasi subscription tidak tercatat.',
                    'info_notice' => null,
                    'reasons' => [
                        'Pembayaran berhasil tetapi aktivasi subscription tidak tercatat.',
                    ],
                    'is_consistent' => false,
                ];
            }
        } else {
            if ($payment->activated_at !== null) {
                $diagnostic = [
                    'status' => 'mismatch',
                    'label' => 'Perlu Pemeriksaan',
                    'badge_class' => 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border border-rose-200/60 dark:border-rose-800/60',
                    'dot_class' => 'bg-rose-500',
                    'icon' => 'alert-circle',
                    'summary' => 'Subscription activation tercatat meskipun status pembayaran bukan berhasil (paid).',
                    'info_notice' => null,
                    'reasons' => [
                        "Subscription activation tercatat meskipun status pembayaran bukan berhasil ({$payment->status}).",
                    ],
                    'is_consistent' => false,
                ];
            } else {
                $diagnostic = [
                    'status' => 'normal',
                    'label' => 'Sesuai Status',
                    'badge_class' => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700',
                    'dot_class' => 'bg-slate-400',
                    'icon' => 'info',
                    'summary' => 'Pembayaran belum atau tidak berhasil, dan tidak ada aktivasi yang terjadi dari transaksi ini.',
                    'info_notice' => null,
                    'reasons' => [],
                    'is_consistent' => true,
                ];
            }
        }

        // Safe provider metadata extraction (strictly excluding signatures, keys, and credentials)
        $rawPayload = is_array($payment->provider_payload) ? $payment->provider_payload : [];
        $safeKeys = [
            'transaction_status' => 'Status Transaksi Provider',
            'payment_type' => 'Metode Pembayaran',
            'fraud_status' => 'Status Fraud',
            'transaction_time' => 'Waktu Transaksi Provider',
            'settlement_time' => 'Waktu Settlement Provider',
            'status_message' => 'Pesan Status',
            'gross_amount' => 'Nominal Dilaporkan Provider',
            'currency' => 'Mata Uang Dilaporkan',
            'bank' => 'Bank',
            'va_numbers' => 'Nomor Virtual Account',
            'bca_va_number' => 'BCA Virtual Account',
            'bni_va_number' => 'BNI Virtual Account',
            'bri_va_number' => 'BRI Virtual Account',
            'permata_va_number' => 'Permata Virtual Account',
            'bill_key' => 'Bill Key',
            'biller_code' => 'Biller Code',
            'store' => 'Convenience Store',
            'acquirer' => 'Acquirer',
        ];

        $safeMetadata = [];
        foreach ($safeKeys as $key => $label) {
            if (isset($rawPayload[$key]) && $rawPayload[$key] !== '') {
                $val = $rawPayload[$key];
                if (is_array($val)) {
                    $encoded = json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    $safeMetadata[$label] = $encoded !== false ? $encoded : '';
                } else {
                    $safeMetadata[$label] = (string) $val;
                }
            }
        }

        return view('platform.payments.show', [
            'payment' => $payment,
            'business' => $business,
            'subscription' => $subscription,
            'hasCloudAccess' => $hasCloudAccess,
            'diagnostic' => $diagnostic,
            'safeMetadata' => $safeMetadata,
        ]);
    }
}
