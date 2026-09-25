@props(['subscription'])

@php
    $state = $subscription['state'] ?? 'none';
    $cloudActive = (bool) ($subscription['cloud_access'] ?? false);

    $stateBadgeClass = match ($state) {
        'cloud_active' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
        'free' => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700',
        'cloud_expired', 'cloud_inactive' => 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
        default => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
    };

    $cloudBadgeClass = $cloudActive
        ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700';

    $fields = [
        ['id' => 'subscriptionStatusLabel', 'label' => 'Status Langganan', 'value' => $subscription['status_label'] ?? '-'],
        ['id' => 'subscriptionStartsAt', 'label' => 'Tanggal Mulai', 'value' => $subscription['starts_at'] ?? '-'],
        ['id' => 'subscriptionExpiresAt', 'label' => 'Tanggal Kedaluwarsa', 'value' => $subscription['expires_at'] ?? '-'],
        ['id' => 'subscriptionRemaining', 'label' => 'Sisa Masa Aktif', 'value' => $subscription['remaining_label'] ?? '-'],
    ];
@endphp

<section class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-5 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="space-y-1 min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                Paket Saat Ini
            </p>
            <h2 id="subscriptionPlanLabel" class="text-xl font-extrabold text-slate-900 dark:text-white">
                {{ $subscription['plan_label'] ?? 'Tidak Ada' }}
            </h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Bisnis aktif:
                <span id="subscriptionBusinessName" class="font-semibold text-slate-700 dark:text-slate-200">
                    {{ $subscription['business_name'] ?? '-' }}
                </span>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span id="subscriptionStateLabel" class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold {{ $stateBadgeClass }}">
                {{ $subscription['state_label'] ?? '-' }}
            </span>
            <span id="subscriptionCloudAccess" class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold {{ $cloudBadgeClass }}">
                Akses Cloud: {{ $cloudActive ? 'Aktif' : 'Tidak Aktif' }}
            </span>
        </div>
    </div>

    <dl class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach($fields as $field)
            <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                <dt class="text-xs text-slate-500 dark:text-slate-400">{{ $field['label'] }}</dt>
                <dd id="{{ $field['id'] }}" class="mt-1 font-semibold text-slate-900 dark:text-white">{{ $field['value'] }}</dd>
            </div>
        @endforeach
    </dl>

    @unless($subscription['has_subscription'] ?? false)
        <p class="text-xs text-slate-500 dark:text-slate-400">
            Belum ada data langganan untuk bisnis ini. Selama belum ada, akses sinkronisasi Cloud tidak tersedia.
        </p>
    @endunless
</section>
