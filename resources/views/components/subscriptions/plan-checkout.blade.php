@props([
    'plans' => [],
    'checkoutAvailable' => false,
    'readiness' => [],
    'subscription' => [],
])

@php
    $cloudPlan = collect($plans)->firstWhere('code', 'cloud');
    $periods = is_array($cloudPlan) ? ($cloudPlan['billing_periods'] ?? []) : [];
    $isCloudPlan = ($subscription['plan_raw'] ?? null) === 'cloud';
    $cloudActive = (bool) ($subscription['cloud_access'] ?? false);
    $purchasable = is_array($cloudPlan) && ($cloudPlan['purchasable'] ?? false);

    $periodLabels = ['monthly' => 'Bulanan', 'yearly' => 'Tahunan'];
    $periodSuffix = ['monthly' => 'per bulan', 'yearly' => 'per tahun'];

    $formatPrice = static function (array $price): string {
        $currency = (string) ($price['currency'] ?? 'IDR');
        $prefix = $currency === 'IDR' ? 'Rp' : $currency;

        return $prefix.' '.number_format((int) ($price['price_minor'] ?? 0), 0, ',', '.');
    };
@endphp

<section class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-5 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="space-y-1">
            <h2 class="text-sm font-extrabold text-slate-900 dark:text-white">
                {{ $isCloudPlan ? 'Perpanjang Paket Cloud' : 'Tingkatkan ke Paket Cloud' }}
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Harga resmi dibaca langsung dari server. Pembayaran diproses aman melalui Midtrans.
            </p>
        </div>
        @if($isCloudPlan && $cloudActive)
            <span class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800">
                Cloud aktif
            </span>
        @endif
    </div>

    @if($periods === [])
        <div class="rounded-xl border border-amber-200/80 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/20 p-4 flex items-start gap-3">
            <i data-lucide="info" class="w-5 h-5 shrink-0 text-amber-600 dark:text-amber-400"></i>
            <div class="space-y-1">
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Harga langganan belum tersedia.</p>
                <p class="text-xs text-amber-800/90 dark:text-amber-200/90 leading-relaxed">
                    Administrator platform belum menetapkan harga paket Cloud yang aktif.
                </p>
                @if($readiness !== [])
                    <ul class="mt-1 space-y-0.5 text-xs text-amber-800/90 dark:text-amber-200/90 list-disc list-inside">
                        @foreach($readiness as $reason)
                            <li>{{ $reason }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($periods as $price)
                @php($period = (string) $price['period'])
                <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4 space-y-3">
                    <div class="space-y-0.5">
                        <p class="text-[11px] uppercase tracking-wider font-bold text-slate-500 dark:text-slate-400">
                            {{ $periodLabels[$period] ?? $period }}
                        </p>
                        <p class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                            {{ $formatPrice($price) }}
                        </p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            {{ $periodSuffix[$period] ?? '' }}
                        </p>
                    </div>

                    <form method="POST" action="{{ route('subscriptions.checkout') }}">
                        @csrf
                        <input type="hidden" name="billing_period" value="{{ $period }}">
                        <button
                            type="submit"
                            @disabled(! $purchasable)
                            class="w-full inline-flex items-center justify-center gap-2 h-10 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer"
                        >
                            <i data-lucide="credit-card" class="w-4 h-4"></i>
                            {{ $isCloudPlan ? 'Perpanjang' : 'Langganan Sekarang' }}
                        </button>
                    </form>
                </div>
            @endforeach
        </div>

        @if(! $checkoutAvailable)
            <div class="rounded-xl border border-amber-200/80 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/20 p-4 flex items-start gap-3">
                <i data-lucide="alert-triangle" class="w-5 h-5 shrink-0 text-amber-600 dark:text-amber-400"></i>
                <div class="space-y-1">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Checkout belum tersedia.</p>
                    @if($readiness !== [])
                        <ul class="space-y-0.5 text-xs text-amber-800/90 dark:text-amber-200/90 list-disc list-inside">
                            @foreach($readiness as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-xs text-amber-800/90 dark:text-amber-200/90">Konfigurasi pembayaran belum lengkap.</p>
                    @endif
                </div>
            </div>
        @endif
    @endif

    @error('checkout')
        <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
    @enderror

    <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
        Langganan aktif setelah pembayaran dikonfirmasi server. Perpanjangan bersifat manual — tidak ada auto-renewal,
        dan perpanjangan saat Cloud masih aktif menambah masa dari tanggal kedaluwarsa berjalan.
    </p>
</section>
