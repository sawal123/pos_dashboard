<?php

namespace App\Services\Platform;

use App\Models\Business;
use App\Models\Device;
use App\Models\Subscription;
use App\Models\SyncRequest;
use App\Models\User;
use Illuminate\Support\Carbon;

class PlatformOverviewQuery
{
    /**
     * Retrieve all platform overview metrics aggregated directly from the database.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $since30Days = Carbon::now()->subDays(30);

        return [
            'businesses' => [
                'total' => Business::query()->count(),
                'active' => Business::query()->where('status', 'active')->count(),
                'inactive' => Business::query()->where('status', 'inactive')->count(),
                'recent_30d' => Business::query()->where('created_at', '>=', $since30Days)->count(),
            ],
            'users' => [
                'total' => User::query()->count(),
                'merchants' => User::query()->where('is_platform_admin', false)->count(),
                'platform_admins' => User::query()->where('is_platform_admin', true)->count(),
                'recent_30d' => User::query()->where('created_at', '>=', $since30Days)->count(),
            ],
            'subscriptions' => [
                'total' => Subscription::query()->count(),
                'plans' => [
                    'free' => Subscription::query()->where('plan', Subscription::PLAN_FREE)->count(),
                    'cloud' => Subscription::query()->where('plan', Subscription::PLAN_CLOUD)->count(),
                ],
                'statuses' => [
                    'active' => Subscription::query()->where('status', Subscription::STATUS_ACTIVE)->count(),
                    'inactive' => Subscription::query()->where('status', Subscription::STATUS_INACTIVE)->count(),
                    'expired' => Subscription::query()->where('status', Subscription::STATUS_EXPIRED)->count(),
                ],
            ],
            'devices' => [
                'total' => Device::query()->count(),
                'active' => Device::query()->where('status', 'active')->count(),
                'inactive' => Device::query()->where('status', 'inactive')->count(),
                'recent_seen_30d' => Device::query()->whereNotNull('last_seen_at')->where('last_seen_at', '>=', $since30Days)->count(),
            ],
            'sync' => [
                'total_requests' => SyncRequest::query()->count(),
                'synced_devices' => SyncRequest::query()->distinct('device_id')->count('device_id'),
                'recent_30d' => SyncRequest::query()->where('processed_at', '>=', $since30Days)->count(),
                'last_processed_at' => SyncRequest::query()->max('processed_at'),
            ],
            'recentActivity' => [
                'businesses' => Business::query()->latest('id')->limit(5)->get(['id', 'name', 'slug', 'status', 'created_at']),
                'users' => User::query()->latest('id')->limit(5)->get(['id', 'name', 'email', 'is_platform_admin', 'created_at']),
            ],
        ];
    }
}
