<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Subscription;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BusinessesController extends Controller
{
    /**
     * Display a listing of businesses with search, filtering, and pagination.
     */
    public function index(Request $request): View
    {
        $query = Business::query()
            ->with([
                'subscription',
                'owners:id,name,email',
            ])
            ->withCount([
                'outlets',
                'users',
                'devices',
            ]);

        // Search by business name, slug, or owner name/email
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('owners', function ($ownerQuery) use ($search) {
                        $ownerQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by status (active/inactive)
        $status = $request->input('status');
        if (in_array($status, ['active', 'inactive'], true)) {
            $query->where('status', $status);
        }

        // Filter by subscription plan (free/cloud)
        // Businesses without a subscription record default to Free tier semantics in UI.
        $plan = $request->input('plan');
        if ($plan === Subscription::PLAN_FREE) {
            $query->where(function ($q) {
                $q->whereDoesntHave('subscription')
                    ->orWhereHas('subscription', fn ($subQuery) => $subQuery->where('plan', Subscription::PLAN_FREE)
                    );
            });
        } elseif ($plan === Subscription::PLAN_CLOUD) {
            $query->whereHas('subscription', fn ($subQuery) => $subQuery->where('plan', Subscription::PLAN_CLOUD)
            );
        }

        $businesses = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('platform.businesses.index', [
            'businesses' => $businesses,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'plan' => $plan,
            ],
        ]);
    }

    /**
     * Display the specified business detail.
     */
    public function show(Business $business): View
    {
        $business->load([
            'subscription',
            'owners:id,name,email',
            'outlets' => fn ($q) => $q->orderBy('name')->limit(10),
            'devices' => fn ($q) => $q->orderByDesc('last_seen_at')->limit(10),
        ]);

        $business->loadCount([
            'outlets',
            'users',
            'devices',
            'devices as active_devices_count' => fn ($q) => $q->where('status', 'active'),
            'devices as inactive_devices_count' => fn ($q) => $q->where('status', 'inactive'),
            'syncRequests',
        ]);

        $lastSync = $business->syncRequests()->max('processed_at');

        return view('platform.businesses.show', [
            'business' => $business,
            'lastSync' => $lastSync,
        ]);
    }

    /**
     * Update the business status (suspend/reactivate).
     */
    public function updateStatus(Request $request, Business $business): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $newStatus = $validated['status'];
        $business->update(['status' => $newStatus]);

        $label = $newStatus === 'active' ? 'diaktifkan kembali' : 'dinonaktifkan (suspend)';

        return redirect()
            ->back()
            ->with('status', "Status bisnis '{$business->name}' berhasil {$label}.");
    }
}
