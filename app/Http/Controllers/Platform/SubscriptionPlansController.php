<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Models\User;
use App\Services\Platform\PlatformAuditLogger;
use App\Services\Platform\SubscriptionPlanAdministrationService;
use App\Services\Subscription\PremiumPolicy;
use App\Support\PlatformAuditAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

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
        PlatformAuditLogger $auditLogger,
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

        DB::transaction(function () use ($request, $plan, $service, $validated, $description, $auditLogger): void {
            $before = [
                'name' => $plan->name,
                'description' => $plan->description,
                'is_active' => (bool) $plan->is_active,
            ];

            $service->updatePlan(
                $plan,
                (string) $validated['name'],
                $description,
                (bool) $validated['is_active'],
            );
            $plan->refresh();

            $after = [
                'name' => $plan->name,
                'description' => $plan->description,
                'is_active' => (bool) $plan->is_active,
            ];

            /** @var User $actor */
            $actor = $request->user();

            $auditLogger->record(
                actor: $actor,
                action: PlatformAuditAction::SUBSCRIPTION_PLAN_UPDATED,
                targetType: PlatformAuditAction::TARGET_SUBSCRIPTION_PLAN,
                targetId: $plan->id,
                targetLabel: $plan->name,
                businessId: null,
                before: $before,
                after: $after,
                metadata: [
                    'code' => $plan->code,
                ],
            );
        });

        return redirect()
            ->route('platform.subscription-plans.show', $plan)
            ->with('status', 'Paket berhasil diperbarui.');
    }

    public function storePrice(
        Request $request,
        SubscriptionPlan $plan,
        SubscriptionPlanAdministrationService $service,
        PremiumPolicy $policy,
        PlatformAuditLogger $auditLogger,
    ): RedirectResponse {
        $this->ensureCanonical($plan, $policy);

        $validated = $request->validate([
            'billing_period' => ['required', 'string', Rule::in($policy->billingPeriods())],
            'price_minor' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'is_active' => ['required', 'boolean'],
            'code' => ['prohibited'],
            'currency' => ['prohibited'],
        ]);

        $billingPeriod = (string) $validated['billing_period'];
        $priceMinor = (int) $validated['price_minor'];
        $isActive = (bool) $validated['is_active'];

        DB::transaction(function () use ($request, $plan, $service, $billingPeriod, $priceMinor, $isActive, $auditLogger): void {
            /** @var SubscriptionPlanPrice|null $existingPrice */
            $existingPrice = SubscriptionPlanPrice::query()
                ->where('subscription_plan_id', $plan->id)
                ->where('billing_period', $billingPeriod)
                ->where('currency', SubscriptionPlanAdministrationService::CURRENCY)
                ->first();

            $isCreated = $existingPrice === null;
            $before = $isCreated ? null : [
                'price_minor' => (int) $existingPrice->price_minor,
                'is_active' => (bool) $existingPrice->is_active,
            ];

            $price = $service->createPrice(
                $plan,
                $billingPeriod,
                $priceMinor,
                $isActive,
            );

            $after = [
                'price_minor' => (int) $price->price_minor,
                'is_active' => (bool) $price->is_active,
            ];

            $action = $isCreated
                ? PlatformAuditAction::SUBSCRIPTION_PRICE_CREATED
                : PlatformAuditAction::SUBSCRIPTION_PRICE_UPDATED;

            /** @var User $actor */
            $actor = $request->user();

            $auditLogger->record(
                actor: $actor,
                action: $action,
                targetType: PlatformAuditAction::TARGET_SUBSCRIPTION_PLAN_PRICE,
                targetId: $price->id,
                targetLabel: "{$plan->name} ({$billingPeriod})",
                businessId: null,
                before: $before,
                after: $after,
                metadata: [
                    'billing_period' => $billingPeriod,
                    'currency' => SubscriptionPlanAdministrationService::CURRENCY,
                    'plan_code' => $plan->code,
                ],
            );
        });

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
        PlatformAuditLogger $auditLogger,
    ): RedirectResponse {
        $this->ensureCanonical($plan, $policy);

        $validated = $request->validate([
            'price_minor' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'is_active' => ['required', 'boolean'],
            'code' => ['prohibited'],
            'billing_period' => ['prohibited'],
            'currency' => ['prohibited'],
        ]);

        $priceMinor = (int) $validated['price_minor'];
        $isActive = (bool) $validated['is_active'];

        try {
            DB::transaction(function () use ($request, $plan, $price, $service, $priceMinor, $isActive, $auditLogger): void {
                $before = [
                    'price_minor' => (int) $price->price_minor,
                    'is_active' => (bool) $price->is_active,
                ];

                $service->updatePrice(
                    $price,
                    $priceMinor,
                    $isActive,
                );
                $price->refresh();

                $after = [
                    'price_minor' => (int) $price->price_minor,
                    'is_active' => (bool) $price->is_active,
                ];

                /** @var User $actor */
                $actor = $request->user();

                $auditLogger->record(
                    actor: $actor,
                    action: PlatformAuditAction::SUBSCRIPTION_PRICE_UPDATED,
                    targetType: PlatformAuditAction::TARGET_SUBSCRIPTION_PLAN_PRICE,
                    targetId: $price->id,
                    targetLabel: "{$plan->name} ({$price->billing_period})",
                    businessId: null,
                    before: $before,
                    after: $after,
                    metadata: [
                        'billing_period' => $price->billing_period,
                        'currency' => $price->currency,
                        'plan_code' => $plan->code,
                    ],
                );
            });
        } catch (InvalidArgumentException) {
            // Not a manageable ADMIN-06 price row (canonical plan / supported
            // period / IDR currency). Treat it as not found instead of mutating.
            abort(404);
        }

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
