<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Device;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Support\PlatformAuditAction;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AuditLogsController extends Controller
{
    /**
     * Display a listing of platform audit logs with search, filters, and pagination.
     */
    public function index(Request $request): View
    {
        $query = PlatformAuditLog::query()
            ->with([
                'actor:id,name,email',
                'business:id,name,slug',
            ]);

        // Search across actor name, email, target label, action, and target ID
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('actor_name', 'like', "%{$search}%")
                    ->orWhere('actor_email', 'like', "%{$search}%")
                    ->orWhere('target_label', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('target_id', 'like', "%{$search}%");
            });
        }

        // Action filter
        $action = $request->input('action');
        if (is_string($action) && array_key_exists($action, PlatformAuditAction::all())) {
            $query->where('action', $action);
        } else {
            $action = null;
        }

        // Target type filter
        $targetType = $request->input('target_type');
        if (is_string($targetType) && array_key_exists($targetType, PlatformAuditAction::targetTypes())) {
            $query->where('target_type', $targetType);
        } else {
            $targetType = null;
        }

        // Business filter
        $businessId = $request->input('business_id');
        if ($businessId && is_numeric($businessId)) {
            $query->where('business_id', (int) $businessId);
        } else {
            $businessId = null;
        }

        // Date range filter: default 30days
        $datePreset = $request->input('date', '30days');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if ($datePreset === 'today') {
            $query->where('created_at', '>=', Carbon::today()->startOfDay());
        } elseif ($datePreset === '7days') {
            $query->where('created_at', '>=', Carbon::now()->subDays(7)->startOfDay());
        } elseif ($datePreset === '30days') {
            $query->where('created_at', '>=', Carbon::now()->subDays(30)->startOfDay());
        } elseif ($datePreset === 'custom') {
            if ($startDate && $endDate) {
                $start = Carbon::parse($startDate)->startOfDay();
                $end = Carbon::parse($endDate)->endOfDay();

                // Safe swap if reversed
                if ($start->gt($end)) {
                    [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                    [$startDate, $endDate] = [$start->toDateString(), $end->toDateString()];
                }

                $query->whereBetween('created_at', [$start, $end]);
            }
        } elseif ($datePreset === 'all') {
            // No date restriction
        } else {
            // Default fallback: 30 days
            $datePreset = '30days';
            $query->where('created_at', '>=', Carbon::now()->subDays(30)->startOfDay());
        }

        $logs = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Summary cards
        $summary = [
            'total' => PlatformAuditLog::query()->count(),
            'today' => PlatformAuditLog::query()->where('created_at', '>=', Carbon::today()->startOfDay())->count(),
            'actors_count' => PlatformAuditLog::query()->distinct('actor_email')->count('actor_email'),
        ];

        return view('platform.audit-logs.index', [
            'logs' => $logs,
            'summary' => $summary,
            'filters' => [
                'q' => $search,
                'action' => $action,
                'target_type' => $targetType,
                'business_id' => $businessId,
                'date' => $datePreset,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'actions' => PlatformAuditAction::all(),
            'targetTypes' => PlatformAuditAction::targetTypes(),
        ]);
    }

    /**
     * Display the specified platform audit log detail.
     */
    public function show(PlatformAuditLog $auditLog): View
    {
        $auditLog->load([
            'actor:id,name,email',
            'business:id,name,slug',
        ]);

        // Safely resolve target URL if target still exists in database
        $targetUrl = $this->resolveTargetUrl($auditLog);

        return view('platform.audit-logs.show', [
            'auditLog' => $auditLog,
            'targetUrl' => $targetUrl,
        ]);
    }

    /**
     * Safely resolve the link to the audited target resource if it still exists.
     */
    private function resolveTargetUrl(PlatformAuditLog $auditLog): ?string
    {
        if ($auditLog->target_id === null) {
            return null;
        }

        try {
            return match ($auditLog->target_type) {
                PlatformAuditAction::TARGET_BUSINESS => Business::query()->whereKey($auditLog->target_id)->exists()
                    ? route('platform.businesses.show', $auditLog->target_id)
                    : null,

                PlatformAuditAction::TARGET_SUBSCRIPTION => Subscription::query()->whereKey($auditLog->target_id)->exists()
                    ? route('platform.subscriptions.show', $auditLog->target_id)
                    : null,

                PlatformAuditAction::TARGET_DEVICE => Device::query()->whereKey($auditLog->target_id)->exists()
                    ? route('platform.devices.show', $auditLog->target_id)
                    : null,

                PlatformAuditAction::TARGET_SUBSCRIPTION_PLAN => SubscriptionPlan::query()->whereKey($auditLog->target_id)->exists()
                    ? route('platform.subscription-plans.show', $auditLog->target_id)
                    : null,

                PlatformAuditAction::TARGET_SUBSCRIPTION_PLAN_PRICE => SubscriptionPlanPrice::query()->whereKey($auditLog->target_id)->exists()
                    ? route('platform.subscription-plans.show', SubscriptionPlanPrice::query()->whereKey($auditLog->target_id)->value('subscription_plan_id'))
                    : null,

                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }
}
