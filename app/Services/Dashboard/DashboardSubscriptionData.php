<?php

namespace App\Services\Dashboard;

use App\Models\Business;
use App\Models\Subscription;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Read-only presentation of the active business subscription.
 *
 * The single source of truth is the `subscriptions` record (one per business)
 * and {@see Subscription::hasCloudAccess()} / {@see Business::hasCloudAccess()}.
 * A `plan = cloud` row is never presented as active unless hasCloudAccess()
 * actually returns true. Nothing here invents pricing, billing periods,
 * invoices or payment history.
 */
class DashboardSubscriptionData
{
    public const STATE_NONE = 'none';

    public const STATE_FREE = 'free';

    public const STATE_CLOUD_ACTIVE = 'cloud_active';

    public const STATE_CLOUD_EXPIRED = 'cloud_expired';

    public const STATE_CLOUD_INACTIVE = 'cloud_inactive';

    public const STATE_UNKNOWN = 'unknown';

    /**
     * @return array<string, mixed>
     */
    public function get(?Business $business): array
    {
        if ($business === null) {
            return $this->present(null, null, self::STATE_NONE, false);
        }

        /** @var Subscription|null $subscription */
        $subscription = $business->subscription;
        $cloudAccess = $business->hasCloudAccess();

        if ($subscription === null) {
            return $this->present($business, null, self::STATE_NONE, $cloudAccess);
        }

        return $this->present($business, $subscription, $this->resolveState($subscription, $cloudAccess), $cloudAccess);
    }

    /**
     * Distinguish Free / Cloud active / Cloud expired / Cloud inactive /
     * unknown, always deferring the "active" decision to hasCloudAccess().
     */
    private function resolveState(Subscription $subscription, bool $cloudAccess): string
    {
        if ($subscription->isFree()) {
            return self::STATE_FREE;
        }

        if ($subscription->isCloud()) {
            if ($cloudAccess) {
                return self::STATE_CLOUD_ACTIVE;
            }

            if ($subscription->isExpired()) {
                return self::STATE_CLOUD_EXPIRED;
            }

            return self::STATE_CLOUD_INACTIVE;
        }

        return self::STATE_UNKNOWN;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(?Business $business, ?Subscription $subscription, string $state, bool $cloudAccess): array
    {
        $startsAt = $subscription?->starts_at;
        $expiresAt = $subscription?->expires_at;

        return [
            'business_name' => $business?->name !== null ? (string) $business->name : null,
            'has_subscription' => $subscription !== null,
            'state' => $state,
            'state_label' => $this->stateLabel($state),
            'plan_raw' => $subscription?->plan,
            'plan_label' => $this->planLabel($subscription?->plan),
            'status_raw' => $subscription?->status,
            'status_label' => $this->statusLabel($subscription?->status),
            'starts_at' => $this->formatDate($startsAt),
            'starts_at_raw' => $startsAt?->format('Y-m-d H:i:s'),
            'expires_at' => $this->formatDate($expiresAt),
            'expires_at_raw' => $expiresAt?->format('Y-m-d H:i:s'),
            'remaining_days' => $this->remainingDays($expiresAt),
            'remaining_label' => $this->remainingLabel($expiresAt),
            'cloud_access' => $cloudAccess,
            'sync_enabled' => $cloudAccess,
            'is_expired' => $subscription !== null && $subscription->isExpired(),
            'billing_available' => false,
        ];
    }

    private function stateLabel(string $state): string
    {
        return match ($state) {
            self::STATE_FREE => 'Free',
            self::STATE_CLOUD_ACTIVE => 'Cloud Aktif',
            self::STATE_CLOUD_EXPIRED => 'Cloud Kedaluwarsa',
            self::STATE_CLOUD_INACTIVE => 'Cloud Tidak Aktif',
            self::STATE_UNKNOWN => 'Tidak Dikenal',
            default => 'Belum Ada Langganan',
        };
    }

    private function planLabel(?string $plan): string
    {
        if ($plan === null || $plan === '') {
            return 'Tidak Ada';
        }

        return match ($plan) {
            'free' => 'Free',
            'cloud' => 'Cloud',
            default => ucwords(str_replace(['_', '-'], ' ', $plan)),
        };
    }

    private function statusLabel(?string $status): string
    {
        if ($status === null || $status === '') {
            return '-';
        }

        return match ($status) {
            'active' => 'Aktif',
            'expired' => 'Kedaluwarsa',
            'inactive' => 'Tidak Aktif',
            default => ucwords(str_replace(['_', '-'], ' ', $status)),
        };
    }

    private function remainingDays(?DateTimeInterface $expiresAt): ?int
    {
        if ($expiresAt === null) {
            return null;
        }

        $seconds = (int) Carbon::now()->diffInSeconds(Carbon::instance($expiresAt), false);

        if ($seconds <= 0) {
            return 0;
        }

        return (int) ceil($seconds / 86400);
    }

    private function remainingLabel(?DateTimeInterface $expiresAt): ?string
    {
        if ($expiresAt === null) {
            return null;
        }

        $days = $this->remainingDays($expiresAt);

        if ($days === null || $days <= 0) {
            return 'Sudah kedaluwarsa';
        }

        return $days.' hari lagi';
    }

    private function formatDate(?DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $carbon = Carbon::instance($date);
        $carbon->setLocale('id');

        return $carbon->translatedFormat('d M Y - H:i');
    }
}
