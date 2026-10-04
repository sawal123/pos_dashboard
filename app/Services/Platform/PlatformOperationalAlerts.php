<?php

namespace App\Services\Platform;

use App\Models\Business;
use App\Models\CloudBackup;
use App\Models\Device;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Services\Subscription\CloudDeviceLimit;
use App\Support\PlatformOperationalAlertType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class PlatformOperationalAlerts
{
    public const EXPIRY_WINDOW_DAYS = 7;

    public const BACKUP_STALE_DAYS = 7;

    public function __construct(
        protected CloudDeviceLimit $deviceLimit
    ) {}

    /**
     * Compute the full set of operational alerts derived strictly from canonical database state.
     *
     * @return array{
     *     summary: array{critical: int, warning: int, info: int, total: int},
     *     alerts: list<array<string, mixed>>,
     *     definitions: list<array{type: string, title: string, severity: string, source: string, condition: string}>,
     *     limitations: list<array{title: string, description: string}>
     * }
     */
    public function get(): array
    {
        $now = Carbon::now();
        $alerts = [];

        // 1. Subscription Expiry Alerts (Expiring Soon & Expired Date)
        $this->collectSubscriptionAlerts($alerts, $now);

        // 2. Payment Inconsistency Alerts (Paid not activated & Non-paid activated)
        $this->collectPaymentAlerts($alerts);

        // 3. Active Cloud Businesses Context for Device Quota & Backup Monitoring
        $cloudBusinesses = Business::query()
            ->whereHas('subscription', function (Builder $q) use ($now): void {
                $q->where('plan', Subscription::PLAN_CLOUD)
                    ->where('status', Subscription::STATUS_ACTIVE)
                    ->where(function (Builder $exp) use ($now): void {
                        $exp->whereNull('expires_at')
                            ->orWhere('expires_at', '>', $now);
                    });
            })
            ->get(['id', 'name', 'slug'])
            ->keyBy('id');

        if ($cloudBusinesses->isNotEmpty()) {
            /** @var list<int> $cloudBusinessIds */
            $cloudBusinessIds = array_values(array_map('intval', $cloudBusinesses->keys()->all()));

            // 4. Device Quota Alerts (Limit Reached & Near Limit)
            $this->collectDeviceQuotaAlerts($alerts, $cloudBusinesses, $cloudBusinessIds);

            // 5. Cloud Backup Freshness Alerts (Never Created & Stale)
            $this->collectBackupAlerts($alerts, $cloudBusinesses, $cloudBusinessIds, $now);
        }

        // Sort alerts by severity priority (critical -> warning -> info), then occurred_at, then key
        usort($alerts, function (array $a, array $b): int {
            $priorityA = PlatformOperationalAlertType::severityPriority((string) $a['severity']);
            $priorityB = PlatformOperationalAlertType::severityPriority((string) $b['severity']);

            if ($priorityA !== $priorityB) {
                return $priorityA <=> $priorityB;
            }

            // If same severity, compare occurred_at if present
            $timeA = $a['occurred_at'] ?? '';
            $timeB = $b['occurred_at'] ?? '';

            if ($timeA !== $timeB) {
                return strcmp((string) $timeA, (string) $timeB);
            }

            return strcmp((string) $a['key'], (string) $b['key']);
        });

        // Summary metric counts
        $criticalCount = 0;
        $warningCount = 0;
        $infoCount = 0;

        foreach ($alerts as $item) {
            match ($item['severity']) {
                PlatformOperationalAlertType::SEVERITY_CRITICAL => $criticalCount++,
                PlatformOperationalAlertType::SEVERITY_WARNING => $warningCount++,
                PlatformOperationalAlertType::SEVERITY_INFO => $infoCount++,
                default => null,
            };
        }

        return [
            'summary' => [
                'critical' => $criticalCount,
                'warning' => $warningCount,
                'info' => $infoCount,
                'total' => count($alerts),
            ],
            'alerts' => $alerts,
            'definitions' => $this->definitions(),
            'limitations' => $this->limitations(),
        ];
    }

    /**
     * Retrieve top N alerts for dashboard presentation.
     *
     * @return array{
     *     summary: array{critical: int, warning: int, info: int, total: int},
     *     top_alerts: list<array<string, mixed>>,
     *     total: int
     * }
     */
    public function topAlerts(int $limit = 5): array
    {
        $data = $this->get();

        return [
            'summary' => $data['summary'],
            'top_alerts' => array_slice($data['alerts'], 0, $limit),
            'total' => $data['summary']['total'],
        ];
    }

    /**
     * Collect subscription expiring soon and expired date alerts.
     *
     * @param  list<array<string, mixed>>  $alerts
     */
    protected function collectSubscriptionAlerts(array &$alerts, Carbon $now): void
    {
        $subscriptions = Subscription::query()
            ->where('plan', Subscription::PLAN_CLOUD)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->with(['business:id,name,slug'])
            ->get();

        $expiryWindow = $now->copy()->addDays(self::EXPIRY_WINDOW_DAYS);

        foreach ($subscriptions as $sub) {
            $businessName = $sub->business ? $sub->business->name : "Bisnis #{$sub->business_id}";

            if ($sub->expires_at === null) {
                continue;
            }

            if ($sub->expires_at->lte($now)) {
                // Critical: Status active but expiry timestamp has passed
                $alerts[] = [
                    'key' => PlatformOperationalAlertType::TYPE_SUBSCRIPTION_EXPIRED.":{$sub->id}",
                    'type' => PlatformOperationalAlertType::TYPE_SUBSCRIPTION_EXPIRED,
                    'severity' => PlatformOperationalAlertType::SEVERITY_CRITICAL,
                    'title' => 'Langganan Cloud Sudah Kedaluwarsa',
                    'description' => "Status langganan bisnis '{$businessName}' tercatat aktif namun batas waktu kedaluwarsa telah terlewati pada {$sub->expires_at->format('d M Y, H:i')} ({$sub->expires_at->diffForHumans()}). Entitlement Cloud ditolak oleh sistem.",
                    'business_id' => $sub->business_id,
                    'business_name' => $businessName,
                    'target_type' => 'subscription',
                    'target_id' => $sub->id,
                    'target_label' => $businessName,
                    'occurred_at' => $sub->expires_at->toIso8601String(),
                    'action_url' => route('platform.subscriptions.show', $sub->id),
                ];
            } elseif ($sub->expires_at->lte($expiryWindow)) {
                // Warning: Will expire within threshold days
                $remainingHuman = $sub->expires_at->diffForHumans($now, CarbonInterface::DIFF_ABSOLUTE);

                $alerts[] = [
                    'key' => PlatformOperationalAlertType::TYPE_SUBSCRIPTION_EXPIRING_SOON.":{$sub->id}",
                    'type' => PlatformOperationalAlertType::TYPE_SUBSCRIPTION_EXPIRING_SOON,
                    'severity' => PlatformOperationalAlertType::SEVERITY_WARNING,
                    'title' => 'Langganan Cloud Segera Berakhir',
                    'description' => "Masa aktif langganan Cloud untuk bisnis '{$businessName}' akan berakhir dalam {$remainingHuman} (pada {$sub->expires_at->format('d M Y, H:i')}).",
                    'business_id' => $sub->business_id,
                    'business_name' => $businessName,
                    'target_type' => 'subscription',
                    'target_id' => $sub->id,
                    'target_label' => $businessName,
                    'occurred_at' => $sub->expires_at->toIso8601String(),
                    'action_url' => route('platform.subscriptions.show', $sub->id),
                ];
            }
        }
    }

    /**
     * Collect payment status vs subscription activation mismatch alerts.
     *
     * @param  list<array<string, mixed>>  $alerts
     */
    protected function collectPaymentAlerts(array &$alerts): void
    {
        $payments = SubscriptionPayment::query()
            ->where(function (Builder $q): void {
                $q->where(fn (Builder $subQ) => $subQ->where('status', SubscriptionPayment::STATUS_PAID)->whereNull('activated_at'))
                    ->orWhere(fn (Builder $subQ) => $subQ->where('status', '!=', SubscriptionPayment::STATUS_PAID)->whereNotNull('activated_at'));
            })
            ->with(['business:id,name,slug'])
            ->get();

        foreach ($payments as $payment) {
            $businessName = $payment->business->name;
            $formattedAmount = 'Rp '.number_format((float) $payment->amount, 0, ',', '.');

            if ($payment->status === SubscriptionPayment::STATUS_PAID && $payment->activated_at === null) {
                // Critical: Paid but not activated
                $timestamp = $payment->paid_at ?? $payment->created_at;

                $alerts[] = [
                    'key' => PlatformOperationalAlertType::TYPE_PAYMENT_PAID_NOT_ACTIVATED.":{$payment->id}",
                    'type' => PlatformOperationalAlertType::TYPE_PAYMENT_PAID_NOT_ACTIVATED,
                    'severity' => PlatformOperationalAlertType::SEVERITY_CRITICAL,
                    'title' => 'Pembayaran Berhasil Belum Mengaktifkan Langganan',
                    'description' => "Pembayaran senilai {$formattedAmount} ({$payment->provider_order_id}) untuk bisnis '{$businessName}' telah berstatus PAID namun aktivasi langganan belum tercatat.",
                    'business_id' => $payment->business_id,
                    'business_name' => $businessName,
                    'target_type' => 'payment',
                    'target_id' => $payment->id,
                    'target_label' => $payment->provider_order_id,
                    'occurred_at' => $timestamp?->toIso8601String(),
                    'action_url' => route('platform.payments.show', $payment->id),
                ];
            } elseif ($payment->status !== SubscriptionPayment::STATUS_PAID && $payment->activated_at !== null) {
                // Critical: Non-paid status but has activated_at recorded
                $alerts[] = [
                    'key' => PlatformOperationalAlertType::TYPE_PAYMENT_NON_PAID_ACTIVATED.":{$payment->id}",
                    'type' => PlatformOperationalAlertType::TYPE_PAYMENT_NON_PAID_ACTIVATED,
                    'severity' => PlatformOperationalAlertType::SEVERITY_CRITICAL,
                    'title' => 'Aktivasi Subscription Tidak Sesuai Status Pembayaran',
                    'description' => "Transaksi {$payment->provider_order_id} ({$formattedAmount}) berstatus '{$payment->status}' namun memiliki stempel aktivasi langganan pada {$payment->activated_at->format('d M Y, H:i')}. Diperlukan rekonsiliasi manual operator.",
                    'business_id' => $payment->business_id,
                    'business_name' => $businessName,
                    'target_type' => 'payment',
                    'target_id' => $payment->id,
                    'target_label' => $payment->provider_order_id,
                    'occurred_at' => $payment->activated_at->toIso8601String(),
                    'action_url' => route('platform.payments.show', $payment->id),
                ];
            }
        }
    }

    /**
     * Collect device quota alerts for active Cloud businesses.
     *
     * @param  list<array<string, mixed>>  $alerts
     * @param  Collection<int, Business>  $cloudBusinesses
     * @param  list<int>  $cloudBusinessIds
     */
    protected function collectDeviceQuotaAlerts(array &$alerts, $cloudBusinesses, array $cloudBusinessIds): void
    {
        $limit = $this->deviceLimit->limit();

        if ($limit <= 0) {
            return;
        }

        $activeCounts = Device::query()
            ->whereIn('business_id', $cloudBusinessIds)
            ->where('status', Device::STATUS_ACTIVE)
            ->selectRaw('business_id, count(*) as count')
            ->groupBy('business_id')
            ->pluck('count', 'business_id')
            ->all();

        foreach ($activeCounts as $bizId => $count) {
            $count = (int) $count;
            $biz = $cloudBusinesses->get($bizId);

            if (! $biz) {
                continue;
            }

            if ($count >= $limit) {
                // Warning: Limit reached
                $alerts[] = [
                    'key' => PlatformOperationalAlertType::TYPE_DEVICE_LIMIT_REACHED.":{$biz->id}",
                    'type' => PlatformOperationalAlertType::TYPE_DEVICE_LIMIT_REACHED,
                    'severity' => PlatformOperationalAlertType::SEVERITY_WARNING,
                    'title' => 'Batas Perangkat Cloud Tercapai',
                    'description' => "Bisnis '{$biz->name}' telah mengaktifkan {$count} perangkat dari batas maksimal {$limit} perangkat Cloud. Pendaftaran atau aktivasi perangkat tambahan akan ditolak.",
                    'business_id' => $biz->id,
                    'business_name' => $biz->name,
                    'target_type' => 'device',
                    'target_id' => $biz->id,
                    'target_label' => $biz->name,
                    'occurred_at' => null,
                    'action_url' => route('platform.devices.index', ['q' => $biz->slug]),
                ];
            } elseif ($limit > 1 && $count === $limit - 1) {
                // Info: Near limit (1 slot remaining)
                $alerts[] = [
                    'key' => PlatformOperationalAlertType::TYPE_DEVICE_LIMIT_NEAR.":{$biz->id}",
                    'type' => PlatformOperationalAlertType::TYPE_DEVICE_LIMIT_NEAR,
                    'severity' => PlatformOperationalAlertType::SEVERITY_INFO,
                    'title' => 'Batas Perangkat Cloud Hampir Tercapai',
                    'description' => "Bisnis '{$biz->name}' telah menggunakan {$count} dari {$limit} kuota perangkat Cloud (tersisa 1 slot perangkat aktif).",
                    'business_id' => $biz->id,
                    'business_name' => $biz->name,
                    'target_type' => 'device',
                    'target_id' => $biz->id,
                    'target_label' => $biz->name,
                    'occurred_at' => null,
                    'action_url' => route('platform.devices.index', ['q' => $biz->slug]),
                ];
            }
        }
    }

    /**
     * Collect Cloud backup freshness alerts for active Cloud businesses.
     *
     * @param  list<array<string, mixed>>  $alerts
     * @param  Collection<int, Business>  $cloudBusinesses
     * @param  list<int>  $cloudBusinessIds
     */
    protected function collectBackupAlerts(array &$alerts, $cloudBusinesses, array $cloudBusinessIds, Carbon $now): void
    {
        $latestBackups = CloudBackup::query()
            ->whereIn('business_id', $cloudBusinessIds)
            ->where('status', CloudBackup::STATUS_READY)
            ->selectRaw('business_id, MAX(created_at) as latest_created_at')
            ->groupBy('business_id')
            ->pluck('latest_created_at', 'business_id')
            ->all();

        $staleThreshold = $now->copy()->subDays(self::BACKUP_STALE_DAYS);

        foreach ($cloudBusinessIds as $bizId) {
            $biz = $cloudBusinesses->get($bizId);

            if (! $biz) {
                continue;
            }

            if (! isset($latestBackups[$bizId])) {
                // Warning: Never created READY backup
                $alerts[] = [
                    'key' => PlatformOperationalAlertType::TYPE_BACKUP_NEVER_CREATED.":{$biz->id}",
                    'type' => PlatformOperationalAlertType::TYPE_BACKUP_NEVER_CREATED,
                    'severity' => PlatformOperationalAlertType::SEVERITY_WARNING,
                    'title' => 'Backup Cloud Belum Pernah Dibuat',
                    'description' => "Bisnis '{$biz->name}' memiliki langganan Cloud aktif namun belum memiliki catatan cadangan database Cloud (READY).",
                    'business_id' => $biz->id,
                    'business_name' => $biz->name,
                    'target_type' => 'backup',
                    'target_id' => $biz->id,
                    'target_label' => $biz->name,
                    'occurred_at' => null,
                    'action_url' => route('platform.backups.index'),
                ];
            } else {
                $latestDate = Carbon::parse((string) $latestBackups[$bizId]);

                if ($latestDate->lt($staleThreshold)) {
                    // Warning: Latest READY backup older than threshold days
                    $alerts[] = [
                        'key' => PlatformOperationalAlertType::TYPE_BACKUP_STALE.":{$biz->id}",
                        'type' => PlatformOperationalAlertType::TYPE_BACKUP_STALE,
                        'severity' => PlatformOperationalAlertType::SEVERITY_WARNING,
                        'title' => 'Backup Cloud Terakhir > 7 Hari',
                        'description' => "Cadangan database Cloud terakhir untuk bisnis '{$biz->name}' dibuat pada {$latestDate->format('d M Y, H:i')} ({$latestDate->diffForHumans()}). Tidak ada backup baru yang tercatat selama lebih dari 7 hari.",
                        'business_id' => $biz->id,
                        'business_name' => $biz->name,
                        'target_type' => 'backup',
                        'target_id' => $biz->id,
                        'target_label' => $biz->name,
                        'occurred_at' => $latestDate->toIso8601String(),
                        'action_url' => route('platform.backups.index'),
                    ];
                }
            }
        }
    }

    /**
     * Canonical definitions for each alert type and source.
     *
     * @return list<array{type: string, title: string, severity: string, source: string, condition: string}>
     */
    public function definitions(): array
    {
        return [
            [
                'type' => PlatformOperationalAlertType::TYPE_SUBSCRIPTION_EXPIRED,
                'title' => 'Langganan Cloud Sudah Kedaluwarsa',
                'severity' => PlatformOperationalAlertType::SEVERITY_CRITICAL,
                'source' => 'subscriptions (plan=cloud, status=active, expires_at <= now())',
                'condition' => 'Status masih aktif namun tanggal kedaluwarsa telah terlewati. Sistem mencabut entitlement Cloud secara otomatis.',
            ],
            [
                'type' => PlatformOperationalAlertType::TYPE_PAYMENT_PAID_NOT_ACTIVATED,
                'title' => 'Pembayaran Berhasil Belum Mengaktifkan Langganan',
                'severity' => PlatformOperationalAlertType::SEVERITY_CRITICAL,
                'source' => 'subscription_payments (status=paid, activated_at is null)',
                'condition' => 'Transaksi pembayaran Midtrans telah sukses dibayar namun masa aktif langganan bisnis belum teraktivasi.',
            ],
            [
                'type' => PlatformOperationalAlertType::TYPE_PAYMENT_NON_PAID_ACTIVATED,
                'title' => 'Aktivasi Subscription Tidak Sesuai Status Pembayaran',
                'severity' => PlatformOperationalAlertType::SEVERITY_CRITICAL,
                'source' => 'subscription_payments (status!=paid, activated_at is not null)',
                'condition' => 'Status transaksi bukan paid (misal: failed, refund, pending) namun tercatat memiliki waktu aktivasi langganan.',
            ],
            [
                'type' => PlatformOperationalAlertType::TYPE_SUBSCRIPTION_EXPIRING_SOON,
                'title' => 'Langganan Cloud Segera Berakhir',
                'severity' => PlatformOperationalAlertType::SEVERITY_WARNING,
                'source' => 'subscriptions (plan=cloud, status=active, expires_at > now() and <= now() + 7 hari)',
                'condition' => 'Masa aktif langganan Cloud tersisa 7 hari atau kurang.',
            ],
            [
                'type' => PlatformOperationalAlertType::TYPE_DEVICE_LIMIT_REACHED,
                'title' => 'Batas Perangkat Cloud Tercapai',
                'severity' => PlatformOperationalAlertType::SEVERITY_WARNING,
                'source' => 'devices (status=active grouped by business_id) >= CloudDeviceLimit::limit()',
                'condition' => 'Bisnis Cloud aktif telah menggunakan seluruh slot perangkat POS yang diperbolehkan.',
            ],
            [
                'type' => PlatformOperationalAlertType::TYPE_BACKUP_NEVER_CREATED,
                'title' => 'Backup Cloud Belum Pernah Dibuat',
                'severity' => PlatformOperationalAlertType::SEVERITY_WARNING,
                'source' => 'cloud_backups (status=ready count=0 for active cloud businesses)',
                'condition' => 'Bisnis dengan hak Cloud aktif belum memiliki riwayat backup database snapshot yang tersimpan.',
            ],
            [
                'type' => PlatformOperationalAlertType::TYPE_BACKUP_STALE,
                'title' => 'Backup Cloud Terakhir > 7 Hari',
                'severity' => PlatformOperationalAlertType::SEVERITY_WARNING,
                'source' => 'cloud_backups (latest ready created_at < now() - 7 hari)',
                'condition' => 'Tidak ada backup Cloud berstatus READY yang berhasil dibuat selama lebih dari 7 hari.',
            ],
            [
                'type' => PlatformOperationalAlertType::TYPE_DEVICE_LIMIT_NEAR,
                'title' => 'Batas Perangkat Cloud Hampir Tercapai',
                'severity' => PlatformOperationalAlertType::SEVERITY_INFO,
                'source' => 'devices (status=active count) == CloudDeviceLimit::limit() - 1',
                'condition' => 'Bisnis Cloud aktif menyisakan 1 slot perangkat tersisa sebelum kuota penuh.',
            ],
        ];
    }

    /**
     * Transparent disclosures of telemetry limitations.
     *
     * @return list<array{title: string, description: string}>
     */
    public function limitations(): array
    {
        return [
            [
                'title' => 'Telemetri Kegagalan Sinkronisasi (Sync Failure)',
                'description' => 'Alert "Sync Gagal" tidak tersedia karena backend hanya mencatat sinkronisasi yang berhasil diproses (committed sync requests). Percobaan sinkronisasi yang gagal atau retry pada sisi klien tidak disimpan dalam tabel database canonical.',
            ],
            [
                'title' => 'Telemetri Kegagalan Upload Backup (Backup Failure)',
                'description' => 'Alert "Backup Gagal" tidak dihitung dari database karena kegagalan proses upload dicatat langsung pada log aplikasi (Log::error) dan transaksi database di-rollback tanpa menyimpan record berstatus FAILED. Observabilitas backup didasarkan pada kesegaran backup terakhir yang berstatus READY.',
            ],
        ];
    }
}
