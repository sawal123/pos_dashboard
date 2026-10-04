<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Platform\PlatformAuditLogger;
use App\Services\Platform\SubscriptionAdministrationService;
use App\Services\Subscription\CloudDeviceLimit;
use App\Services\Subscription\PremiumPolicy;
use App\Support\PlatformAuditAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SubscriptionsController extends Controller
{
    /**
     * Display a listing of subscriptions with search, filtering, and pagination.
     */
    public function index(Request $request): View
    {
        $query = Subscription::query()
            ->with(['business' => function ($bQuery) {
                $bQuery->select('id', 'name', 'slug', 'status', 'business_type');
            }]);

        // Search by business name, slug, or owner name/email
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->whereHas('business', function ($bQuery) use ($search) {
                $bQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('owners', function ($oQuery) use ($search) {
                        $oQuery->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by plan (free / cloud)
        $plan = $request->input('plan');
        if (in_array($plan, [Subscription::PLAN_FREE, Subscription::PLAN_CLOUD], true)) {
            $query->where('plan', $plan);
        }

        // Filter by status (active / inactive / expired)
        $status = $request->input('status');
        if (in_array($status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_INACTIVE, Subscription::STATUS_EXPIRED], true)) {
            $query->where('status', $status);
        }

        // Filter by cloud entitlement (granted / denied)
        $entitlement = $request->input('entitlement');
        if ($entitlement === 'granted') {
            $query->where('plan', Subscription::PLAN_CLOUD)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                });
        } elseif ($entitlement === 'denied') {
            $query->where(function ($q) {
                $q->where('plan', '!=', Subscription::PLAN_CLOUD)
                    ->orWhere('status', '!=', Subscription::STATUS_ACTIVE)
                    ->orWhere(function ($subQ) {
                        $subQ->whereNotNull('expires_at')
                            ->where('expires_at', '<=', now());
                    });
            });
        }

        $subscriptions = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('platform.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'filters' => [
                'q' => $search,
                'plan' => $plan,
                'status' => $status,
                'entitlement' => $entitlement,
            ],
        ]);
    }

    /**
     * Display the specified subscription details, business info, entitlement, and capabilities.
     */
    public function show(
        Subscription $subscription,
        PremiumPolicy $policy,
        CloudDeviceLimit $deviceLimit
    ): View {
        $subscription->load([
            'business' => function ($query) {
                $query->withCount(['outlets', 'devices', 'users'])
                    ->with('owners:id,name,email');
            },
        ]);

        $business = $subscription->business;
        $capabilityMap = $policy->capabilityMap($business);
        $configuredDeviceLimit = $policy->deviceLimit();
        $activeDeviceCount = $business ? $deviceLimit->activeCount($business) : 0;
        $remainingDeviceSlots = $business ? $deviceLimit->remaining($business) : null;
        $supportedBillingPeriods = $policy->billingPeriods();

        return view('platform.subscriptions.show', [
            'subscription' => $subscription,
            'business' => $business,
            'capabilityMap' => $capabilityMap,
            'configuredDeviceLimit' => $configuredDeviceLimit,
            'activeDeviceCount' => $activeDeviceCount,
            'remainingDeviceSlots' => $remainingDeviceSlots,
            'supportedBillingPeriods' => $supportedBillingPeriods,
            'policy' => $policy,
        ]);
    }

    /**
     * Administratively activate Cloud plan with official billing duration.
     */
    public function activate(
        Request $request,
        Subscription $subscription,
        SubscriptionAdministrationService $service,
        PremiumPolicy $policy,
        PlatformAuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'billing_period' => ['required', 'string', Rule::in($policy->billingPeriods())],
        ]);

        DB::transaction(function () use ($request, $subscription, $service, $validated, $auditLogger): void {
            $before = [
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'expires_at' => $subscription->expires_at?->toIso8601String(),
            ];

            $service->activateCloud($subscription, $validated['billing_period']);
            $subscription->refresh();

            $after = [
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'expires_at' => $subscription->expires_at?->toIso8601String(),
            ];

            /** @var User $actor */
            $actor = $request->user();

            $auditLogger->record(
                actor: $actor,
                action: PlatformAuditAction::SUBSCRIPTION_CLOUD_ACTIVATED,
                targetType: PlatformAuditAction::TARGET_SUBSCRIPTION,
                targetId: $subscription->id,
                targetLabel: $subscription->business->name,
                businessId: $subscription->business_id,
                before: $before,
                after: $after,
                metadata: [
                    'billing_period' => $validated['billing_period'],
                    'administrative_override' => true,
                ],
            );
        });

        return redirect()
            ->route('platform.subscriptions.show', $subscription)
            ->with('status', 'Langganan berhasil diaktifkan ke paket Cloud (Penyesuaian Administratif). Entitlement aktif.');
    }

    /**
     * Administratively renew Cloud subscription.
     */
    public function renew(
        Request $request,
        Subscription $subscription,
        SubscriptionAdministrationService $service,
        PremiumPolicy $policy,
        PlatformAuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'billing_period' => ['required', 'string', Rule::in($policy->billingPeriods())],
        ]);

        DB::transaction(function () use ($request, $subscription, $service, $validated, $auditLogger): void {
            $before = [
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'expires_at' => $subscription->expires_at?->toIso8601String(),
            ];

            $service->renewCloud($subscription, $validated['billing_period']);
            $subscription->refresh();

            $after = [
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'expires_at' => $subscription->expires_at?->toIso8601String(),
            ];

            /** @var User $actor */
            $actor = $request->user();

            $auditLogger->record(
                actor: $actor,
                action: PlatformAuditAction::SUBSCRIPTION_CLOUD_RENEWED,
                targetType: PlatformAuditAction::TARGET_SUBSCRIPTION,
                targetId: $subscription->id,
                targetLabel: $subscription->business->name,
                businessId: $subscription->business_id,
                before: $before,
                after: $after,
                metadata: [
                    'billing_period' => $validated['billing_period'],
                    'administrative_override' => true,
                ],
            );
        });

        return redirect()
            ->route('platform.subscriptions.show', $subscription)
            ->with('status', 'Masa aktif langganan Cloud berhasil diperpanjang (Penyesuaian Administratif).');
    }

    /**
     * Administratively downgrade subscription to Free tier.
     */
    public function downgrade(
        Request $request,
        Subscription $subscription,
        SubscriptionAdministrationService $service,
        PlatformAuditLogger $auditLogger,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $subscription, $service, $auditLogger): void {
            $before = [
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'expires_at' => $subscription->expires_at?->toIso8601String(),
            ];

            $service->downgradeToFree($subscription);
            $subscription->refresh();

            $after = [
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'expires_at' => $subscription->expires_at?->toIso8601String(),
            ];

            /** @var User $actor */
            $actor = $request->user();

            $auditLogger->record(
                actor: $actor,
                action: PlatformAuditAction::SUBSCRIPTION_DOWNGRADED,
                targetType: PlatformAuditAction::TARGET_SUBSCRIPTION,
                targetId: $subscription->id,
                targetLabel: $subscription->business->name,
                businessId: $subscription->business_id,
                before: $before,
                after: $after,
                metadata: [
                    'administrative_override' => true,
                ],
            );
        });

        return redirect()
            ->route('platform.subscriptions.show', $subscription)
            ->with('status', 'Langganan berhasil diturunkan ke paket Free (Penyesuaian Administratif). Entitlement Cloud dicabut.');
    }

    /**
     * Administratively set subscription to Inactive.
     */
    public function inactivate(
        Request $request,
        Subscription $subscription,
        SubscriptionAdministrationService $service,
        PlatformAuditLogger $auditLogger,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $subscription, $service, $auditLogger): void {
            $before = [
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'expires_at' => $subscription->expires_at?->toIso8601String(),
            ];

            $service->setInactive($subscription);
            $subscription->refresh();

            $after = [
                'plan' => $subscription->plan,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'expires_at' => $subscription->expires_at?->toIso8601String(),
            ];

            /** @var User $actor */
            $actor = $request->user();

            $auditLogger->record(
                actor: $actor,
                action: PlatformAuditAction::SUBSCRIPTION_INACTIVATED,
                targetType: PlatformAuditAction::TARGET_SUBSCRIPTION,
                targetId: $subscription->id,
                targetLabel: $subscription->business->name,
                businessId: $subscription->business_id,
                before: $before,
                after: $after,
                metadata: [
                    'administrative_override' => true,
                ],
            );
        });

        return redirect()
            ->route('platform.subscriptions.show', $subscription)
            ->with('status', 'Status langganan berhasil diubah menjadi Nonaktif (Penyesuaian Administratif). Entitlement Cloud dicabut.');
    }
}
