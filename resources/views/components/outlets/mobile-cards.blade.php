@props(['outlets', 'canManageOutlets' => false])

@php
    $formatRupiah = fn ($amount) => 'Rp '.number_format((int) $amount, 0, ',', '.');
    $badgeClass = function (string $status): string {
        return match ($status) {
            'active' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            'inactive' => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700',
            default => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
        };
    };
@endphp

<div class="lg:hidden space-y-3">
    @foreach($outlets as $outlet)
        @php($sub = $outlet['subscription'] ?? ['state' => 'free', 'label' => '-', 'expires_at' => null, 'cloud_access' => false])
        <article class="outlet-card p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <h2 class="font-extrabold text-slate-900 dark:text-white break-words">{{ $outlet['name'] }}</h2>
                        @if(! empty($outlet['is_primary']))
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-[10px] font-bold uppercase">
                                Utama
                            </span>
                        @endif
                    </div>
                    <p class="text-xs font-mono text-slate-500 dark:text-slate-400">{{ $outlet['code'] }}</p>
                </div>
                <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold {{ $badgeClass($outlet['status_raw']) }}">
                    {{ $outlet['status'] }}
                </span>
            </div>

            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ $outlet['address'] ?? 'Alamat belum tersedia' }}
            </p>

            <div class="text-xs text-slate-600 dark:text-slate-300 flex items-center justify-between">
                <span>Langganan: <strong>{{ $sub['label'] }}</strong></span>
                @if(! empty($sub['expires_at']))
                    <span>s/d {{ $sub['expires_at'] }}</span>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs">
                <div>
                    <span class="block text-slate-500 dark:text-slate-400">Perangkat</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $outlet['device_count'] }}</span>
                </div>
                <div>
                    <span class="block text-slate-500 dark:text-slate-400">Shift Open</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $outlet['open_shift_count'] }}</span>
                </div>
                <div>
                    <span class="block text-slate-500 dark:text-slate-400">Transaksi</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $outlet['sales_count'] }} / {{ $formatRupiah($outlet['sales_total']) }}</span>
                </div>
                <div>
                    <span class="block text-slate-500 dark:text-slate-400">Terakhir</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $outlet['last_transaction_at'] ?? '-' }}</span>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @if($canManageOutlets && empty($outlet['is_primary']) && empty($sub['cloud_access']))
                    <button
                        type="button"
                        class="open-outlet-checkout-btn flex-1 inline-flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs transition-colors"
                        data-outlet-id="{{ $outlet['id'] }}"
                        data-outlet-name="{{ $outlet['name'] }}"
                    >
                        Bayar Cabang
                    </button>
                @endif
                <button
                    type="button"
                    class="view-outlet-detail-btn flex-1 inline-flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    data-detail-url="{{ route('outlets.detail', $outlet['id']) }}"
                >
                    <i data-lucide="panel-right-open" class="w-4 h-4"></i>
                    Lihat Detail
                </button>
            </div>
        </article>
    @endforeach
</div>
