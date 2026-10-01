<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Services\Platform\SubscriptionPlanAdministrationService;
use App\Services\Subscription\PremiumPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * ADMIN-06 — Platform Admin Premium plan & pricing management.
 *
 * Access is enforced by the platform route group (`auth`/`verified`/
 * `platform.admin`). This controller only authorizes the canonical plan,
 * validates input, calls {@see SubscriptionPlanAdministrationService} and
 * redirects. The plan `code`, `billing_period` and `currency` are immutable.
 */
class SubscriptionPlansController extends Controller
{
    public function index(SubscriptionPlanAdministrationService $service): View
    {
        return view('platform.subscription-plans.index', [
            'plans' => $service->canonicalPlans(),
            'readiness' => $service->checkoutReadiness(),
        ]);
    }

    public function show(
        SubscriptionPlan $plan,
        SubscriptionPlanAdministrationService $service,
        PremiumPolicy $policy,
    ): View {
        $this->ensureCanonical($plan, $policy);

        $plan->load('prices');

        return view('platform.subscription-plans.show', [
            'plan' => $plan,
            'prices' => $plan->prices->keyBy('billing_period'),
            'readiness' => $service->checkoutReadiness(),
            'billingPeriods' => $policy->billingPeriods(),
        ]);
    }

    public function update(
        Request $request,
        SubscriptionPlan $plan,
        SubscriptionPlanAdministrationService $service,
        PremiumPolicy $policy,
    ): RedirectResponse {
        $this->ensureCanonical($plan, $policy);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'code' => ['prohibited'],
            'billing_period' => ['prohibited'],
            'currency' => ['prohibited'],
        ]);

        $description = isset($validated['description']) && is_string($validated['description']) && $validated['description'] !== ''
            ? $validated['description']
            : null;

        $service->updatePlan(
            $plan,
            (string) $validated['name'],
            $description,
            (bool) $validated['is_active'],
        );

        return redirect()
            ->route('platform.subscription-plans.show', $plan)
            ->with('status', 'Paket berhasil diperbarui.');
    }

    public function storePrice(
        Request $request,
        SubscriptionPlan $plan,
        SubscriptionPlanAdministrationService $service,
        PremiumPolicy $policy,
    ): RedirectResponse {
        $this->ensureCanonical($plan, $policy);

        $validated = $request->validate([
            'billing_period' => ['required', 'string', Rule::in($policy->billingPeriods())],
            'price_minor' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'is_active' => ['required', 'boolean'],
            'code' => ['prohibited'],
            'currency' => ['prohibited'],
        ]);

        $service->createPrice(
            $plan,
            (string) $validated['billing_period'],
            (int) $validated['price_minor'],
            (bool) $validated['is_active'],
        );

        return redirect()
            ->route('platform.subscription-plans.show', $plan)
            ->with('status', $this->priceLabel((string) $validated['billing_period']).' berhasil disimpan.');
    }

    public function updatePrice(
        Request $request,
        SubscriptionPlan $plan,
        SubscriptionPlanPrice $price,
        SubscriptionPlanAdministrationService $service,
        PremiumPolicy $policy,
    ): RedirectResponse {
        $this->ensureCanonical($plan, $policy);

        $validated = $request->validate([
            'price_minor' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'is_active' => ['required', 'boolean'],
            'code' => ['prohibited'],
            'billing_period' => ['prohibited'],
            'currency' => ['prohibited'],
        ]);

        $service->updatePrice(
            $price,
            (int) $validated['price_minor'],
            (bool) $validated['is_active'],
        );

        return redirect()
            ->route('platform.subscription-plans.show', $plan)
            ->with('status', $this->priceLabel($price->billing_period).' berhasil diperbarui.');
    }

    private function ensureCanonical(SubscriptionPlan $plan, PremiumPolicy $policy): void
    {
        abort_unless($plan->code === $policy->planCode(), 404);
    }

    private function priceLabel(string $period): string
    {
        return match ($period) {
            'monthly' => 'Harga Bulanan',
            'yearly' => 'Harga Tahunan',
            default => 'Harga',
        };
    }
}
