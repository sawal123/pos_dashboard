@props(['subscription'])

@php
    $state = $subscription['state'] ?? 'none';

    $panel = match ($state) {
        'cloud_active' => [
            'icon' => 'check-circle-2',
            'title' => 'Cloud Aktif',
            'message' => 'Langganan Cloud aktif. Sinkronisasi Cloud tersedia selama periode berlangganan ini.',
            'wrapper' => 'border-emerald-200/80 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/20',
            'iconClass' => 'text-emerald-600 dark:text-emerald-400',
            'textClass' => 'text-emerald-800 dark:text-emerald-200',
        ],
        'free' => [
            'icon' => 'circle-slash-2',
            'title' => 'Free Aktif',
            'message' => 'Paket Free aktif. POS tetap dapat digunakan secara lokal, tetapi tidak mendapat sinkronisasi Cloud.',
            'wrapper' => 'border-slate-200/80 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40',
            'iconClass' => 'text-slate-500 dark:text-slate-400',
            'textClass' => 'text-slate-700 dark:text-slate-200',
        ],
        'cloud_expired' => [
            'icon' => 'alarm-clock',
            'title' => 'Cloud Kedaluwarsa',
            'message' => 'Langganan Cloud sudah melewati tanggal kedaluwarsa. Sinkronisasi Cloud dihentikan sampai paket aktif kembali.',
            'wrapper' => 'border-rose-200/80 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/20',
            'iconClass' => 'text-rose-600 dark:text-rose-400',
            'textClass' => 'text-rose-800 dark:text-rose-200',
        ],
        'cloud_inactive' => [
            'icon' => 'pause-circle',
            'title' => 'Cloud Tidak Aktif',
            'message' => 'Langganan Cloud ada, tetapi statusnya tidak aktif sehingga sinkronisasi Cloud tidak tersedia.',
            'wrapper' => 'border-rose-200/80 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/20',
            'iconClass' => 'text-rose-600 dark:text-rose-400',
            'textClass' => 'text-rose-800 dark:text-rose-200',
        ],
        'unknown' => [
            'icon' => 'circle-help',
            'title' => 'Status Tidak Dikenal',
            'message' => 'Data langganan tidak dikenal. Akses Cloud tidak dapat dipastikan dan diperlakukan sebagai tidak tersedia.',
            'wrapper' => 'border-amber-200/80 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/20',
            'iconClass' => 'text-amber-600 dark:text-amber-400',
            'textClass' => 'text-amber-800 dark:text-amber-200',
        ],
        default => [
            'icon' => 'credit-card',
            'title' => 'Belum Ada Langganan',
            'message' => 'Belum ada data langganan untuk bisnis aktif. Sinkronisasi Cloud tidak tersedia.',
            'wrapper' => 'border-amber-200/80 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/20',
            'iconClass' => 'text-amber-600 dark:text-amber-400',
            'textClass' => 'text-amber-800 dark:text-amber-200',
        ],
    };
@endphp

<section
    id="subscriptionStatusPanel"
    data-subscription-state="{{ $state }}"
    class="rounded-2xl border p-4 flex items-start gap-3 {{ $panel['wrapper'] }}"
    role="status"
>
    <i data-lucide="{{ $panel['icon'] }}" class="w-5 h-5 shrink-0 {{ $panel['iconClass'] }}"></i>
    <div class="space-y-0.5">
        <p class="text-sm font-semibold {{ $panel['textClass'] }}">{{ $panel['title'] }}</p>
        <p class="text-xs {{ $panel['textClass'] }} opacity-90">{{ $panel['message'] }}</p>
    </div>
</section>
