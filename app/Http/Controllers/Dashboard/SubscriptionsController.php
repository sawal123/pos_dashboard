<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\Dashboard\DashboardSubscriptionData;
use App\Services\Subscription\Midtrans\MidtransGateway;
use App\Services\Subscription\MobileSubscriptionPlanCatalog;
use App\Services\Subscription\PremiumPolicy;
use App\Services\Subscription\PricingUnavailableException;
use App\Services\Subscription\SubscriptionCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * DASH-12A — owner-only subscription overview.
 *
 * The page is scoped to the active dashboard business, never to a request
 * parameter, so a forged `business_id` cannot change what is displayed or
 * purchased. Pricing is read from the database through the same catalog and
 * checkout service the mobile API uses; the server remains authoritative.
 */
class SubscriptionsController extends Controller
{
    public function __construct(
        protected DashboardSubscriptionData $subscriptionData,
        private readonly MobileSubscriptionPlanCatalog $catalog,
        private readonly SubscriptionCheckoutService $checkout,
        private readonly PremiumPolicy $policy,
        private readonly MidtransGateway $midtrans,
    ) {}

    public function index(Request $request): View
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        if ($currentBusiness === null) {
            abort(403);
        }

        // Reuse the existing owner-only rule without modifying BusinessPolicy.
        Gate::forUser($request->user())->authorize('update', $currentBusiness);

        $readiness = $this->checkout->readiness();

        $payments = SubscriptionPayment::query()
            ->where('business_id', $currentBusiness->id)
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('subscriptions.index', [
            'subscription' => $this->subscriptionData->get($currentBusiness),
            'plans' => $this->catalog->plans(),
            'checkoutAvailable' => $readiness['ready'],
            'checkoutReadiness' => $readiness['reasons'],
            'payments' => $payments,
        ]);
    }

    /**
     * Start a Midtrans Snap checkout for the active business.
     *
     * The client never supplies an amount, currency or business id: the price
     * is resolved from `subscription_plan_prices` and snapshotted by the
     * service, exactly like the mobile endpoint.
     */
    public function checkout(Request $request): RedirectResponse
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        if (! $currentBusiness instanceof Business) {
            abort(403);
        }

        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize('update', $currentBusiness);

        $validated = $request->validate([
            'billing_period' => ['required', 'string', Rule::in($this->policy->billingPeriods())],
        ]);

        try {
            $payment = $this->checkout->createCheckout(
                $currentBusiness,
                $user,
                $this->policy->planCode(),
                (string) $validated['billing_period'],
            );
        } catch (PricingUnavailableException) {
            return back()->withErrors([
                'checkout' => 'Checkout belum tersedia karena harga resmi atau konfigurasi Midtrans belum lengkap.',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'checkout' => 'Pembayaran tidak dapat dibuat. Silakan coba lagi.',
            ]);
        }

        $redirectUrl = $payment->redirect_url;

        if (! is_string($redirectUrl) || $redirectUrl === '') {
            return back()->withErrors([
                'checkout' => 'Tautan pembayaran tidak tersedia. Silakan coba lagi.',
            ]);
        }

        return redirect()->away($redirectUrl);
    }

    public function cancelPayment(Request $request, int $paymentId): RedirectResponse
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        if (! $currentBusiness instanceof Business) {
            abort(403);
        }

        Gate::forUser($request->user())->authorize('update', $currentBusiness);

        $payment = SubscriptionPayment::query()
            ->where('business_id', $currentBusiness->id)
            ->whereKey($paymentId)
            ->firstOrFail();

        if ($payment->status !== SubscriptionPayment::STATUS_PENDING) {
            return back()->withErrors([
                'cancel' => 'Hanya transaksi dengan status menunggu pembayaran yang dapat dibatalkan.',
            ]);
        }

        if ($payment->activated_at !== null) {
            return back()->withErrors([
                'cancel' => 'Transaksi yang sudah aktif tidak dapat dibatalkan.',
            ]);
        }

        try {
            app(\App\Services\Subscription\SubscriptionPaymentCancellationService::class)->cancel($payment);
        } catch (\Throwable) {
            if ($payment->provider === SubscriptionPayment::PROVIDER_MIDTRANS) {
                $this->midtrans->cancelTransaction($payment->provider_order_id);
            }

            $payment->forceFill([
                'status' => SubscriptionPayment::STATUS_CANCELLED,
            ])->save();
        }

        return back()->with('status', 'Transaksi pembayaran berhasil dibatalkan.');
    }
}
