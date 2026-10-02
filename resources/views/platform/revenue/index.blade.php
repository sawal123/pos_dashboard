<x-layouts::platform :title="'Revenue & Billing Langganan'">
    <div class="space-y-6">

        {{-- Page Heading & Filters --}}
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    Laporan Revenue &amp; Billing
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Pendapatan langganan platform SaaS Cloud/Premium berdasarkan pembayaran berstatus paid yang tersimpan di server.
                </p>
            </div>

            {{-- Period Filter Form --}}
            <form method="GET" action="{{ route('platform.revenue.index') }}" class="flex flex-wrap items-center gap-2 text-xs">
                {{-- Preset Buttons --}}
                <div class="inline-flex rounded-xl p-1 bg-slate-100 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80">
                    <button
                        type="submit"
                        name="date"
                        value="today"
                        class="px-2.5 py-1.5 rounded-lg font-semibold transition-all {{ $current_filters['date'] === 'today' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        Hari Ini
                    </button>
                    <button
                        type="submit"
                        name="date"
                        value="7days"
                        class="px-2.5 py-1.5 rounded-lg font-semibold transition-all {{ $current_filters['date'] === '7days' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        7 Hari
                    </button>
                    <button
                        type="submit"
                        name="date"
                        value="30days"
                        class="px-2.5 py-1.5 rounded-lg font-semibold transition-all {{ $current_filters['date'] === '30days' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        30 Hari
                    </button>
                </div>

                {{-- Custom Date Inputs --}}
                <div class="flex items-center gap-1.5 bg-white dark:bg-slate-800 p-1 rounded-xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs">
                    <input
                        type="hidden"
                        name="date"
                        value="custom"
                    >
                    <input
                        type="date"
                        name="start_date"
                        value="{{ $current_filters['start_date'] }}"
                        class="px-2 py-1 bg-transparent text-slate-700 dark:text-slate-200 text-xs border-0 focus:ring-1 focus:ring-indigo-500 rounded-lg"
                        placeholder="Mulai"
                        aria-label="Tanggal Mulai"
                    >
                    <span class="text-slate-400 text-xs">-</span>
                    <input
                        type="date"
                        name="end_date"
                        value="{{ $current_filters['end_date'] }}"
                        class="px-2 py-1 bg-transparent text-slate-700 dark:text-slate-200 text-xs border-0 focus:ring-1 focus:ring-indigo-500 rounded-lg"
                        placeholder="Selesai"
                        aria-label="Tanggal Selesai"
                    >
                    <button
                        type="submit"
                        class="px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold transition-colors"
                    >
                        Filter
                    </button>
                </div>
            </form>
        </div>

        {{-- Active Period Badge & Notice --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-3.5 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400">
            <div class="flex items-center gap-2">
                <i data-lucide="calendar" class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0"></i>
                <span>Periode Aktif: <strong>{{ $current_filters['period_label'] }}</strong> ({{ $current_filters['start_formatted'] }} s/d {{ $current_filters['end_formatted'] }})</span>
            </div>
            <span class="text-[11px] text-slate-400 dark:text-slate-500">
                Waktu pengakuan revenue: <strong>paid_at</strong> &bull; Percobaan payment: <strong>created_at</strong>
            </span>
        </div>

        {{-- Multi-Currency Notice Banner (Defensive safety) --}}
        @if($summary['is_multi_currency'])
            <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-800/80 text-amber-800 dark:text-amber-300 text-xs space-y-2">
                <div class="flex items-center gap-2 font-bold">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0"></i>
                    <span>Ditemukan Pembayaran Lebih Dari Satu Mata Uang</span>
                </div>
                <p>
                    Data revenue periode ini memiliki lebih dari 1 mata uang. Sistem mengisolasi agregasi per mata uang dan tidak melakukan konversi kurs (FX) otomatis untuk menjaga integritas data historis.
                </p>
                <div class="flex flex-wrap gap-2 pt-1">
                    @foreach($summary['currency_breakdown'] as $currRow)
                        <span class="px-2.5 py-1 rounded-lg bg-white/80 dark:bg-slate-900/80 border border-amber-300 dark:border-amber-700/60 font-semibold font-mono text-[11px]">
                            {{ $currRow['currency'] }}: {{ $currRow['formatted'] }} ({{ $currRow['count'] }} payment)
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Primary Revenue KPI Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3.5">
            {{-- Revenue Subscription Paid (Total Paid Revenue IDR) --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs col-span-2 sm:col-span-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        Revenue Subscription Paid {{ $summary['is_multi_currency'] ? '(IDR)' : '' }}
                    </span>
                    <span class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                        <i data-lucide="banknote" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl md:text-2xl font-black text-emerald-600 dark:text-emerald-400 truncate" title="{{ $summary['formatted_paid_revenue'] }}">
                    {{ $summary['formatted_paid_revenue'] }}
                </div>
                <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                    Pendapatan Pembayaran Berhasil {{ $summary['is_multi_currency'] ? '(Mata Uang IDR)' : '' }}
                </div>
            </div>

            {{-- Paid Payments Count --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Payment Paid</span>
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl md:text-2xl font-black text-slate-900 dark:text-white">
                    {{ number_format($summary['total_paid_payments'], 0, ',', '.') }}
                </div>
                <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                    Jumlah transaksi berhasil
                </div>
            </div>

            {{-- First Payment Activations --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Aktivasi Pertama</span>
                    <span class="p-1.5 rounded-lg bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400">
                        <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl md:text-2xl font-black text-slate-900 dark:text-white">
                    {{ number_format($summary['first_activations'], 0, ',', '.') }}
                </div>
                <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                    Aktivasi awal via pembayaran
                </div>
            </div>

            {{-- Subsequent Payment Activations (Renewals) --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Aktivasi Lanjutan</span>
                    <span class="p-1.5 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl md:text-2xl font-black text-slate-900 dark:text-white">
                    {{ number_format($summary['subsequent_activations'], 0, ',', '.') }}
                </div>
                <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                    Renewal / perpanjangan
                </div>
            </div>

            {{-- Current Refunded Total (Diagnostic current count) --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Refund Terkini</span>
                    <span class="p-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
                        <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl md:text-2xl font-black text-slate-900 dark:text-white">
                    {{ number_format($diagnostics['current_refunded_total'], 0, ',', '.') }}
                </div>
                <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
                    Status refund saat ini
                </div>
            </div>
        </div>

        {{-- Diagnostics Bar (If anomalous conditions exist) --}}
        @if($diagnostics['paid_not_activated'] > 0 || $diagnostics['non_paid_with_activation'] > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                @if($diagnostics['paid_not_activated'] > 0)
                    <div class="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/50 border border-amber-200/80 dark:border-amber-800/80 flex items-center justify-between text-xs text-amber-800 dark:text-amber-300">
                        <div class="flex items-center gap-2">
                            <i data-lucide="alert-circle" class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0"></i>
                            <div>
                                <span class="font-bold">Paid Belum Teraktivasi:</span>
                                <span>{{ $diagnostics['paid_not_activated'] }} pembayaran berstatus paid tanpa timestamp aktivasi. Tetap dihitung sebagai revenue paid.</span>
                            </div>
                        </div>
                    </div>
                @endif

                @if($diagnostics['non_paid_with_activation'] > 0)
                    <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/50 border border-rose-200/80 dark:border-rose-800/80 flex items-center justify-between text-xs text-rose-800 dark:text-rose-300">
                        <div class="flex items-center gap-2">
                            <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0"></i>
                            <div>
                                <span class="font-bold">Non-paid dengan Aktivasi:</span>
                                <span>{{ $diagnostics['non_paid_with_activation'] }} pembayaran non-paid memiliki activated_at (anomali diagnostic).</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Revenue Trend & Billing Breakdown Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left: Daily Paid Revenue Trend (Span 2) --}}
            <div class="lg:col-span-2 p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="trending-up" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                            Tren Revenue Harian (paid_at)
                        </h2>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                            Penerimaan pembayaran berstatus paid per hari dalam periode {{ $current_filters['period_label'] }}.
                        </p>
                    </div>
                    <div class="text-right">
                        @if($trend['is_multi_currency'])
                            <div class="flex flex-col items-end gap-0.5">
                                @foreach($trend['formatted_currency_totals'] as $currCode => $formattedVal)
                                    <span class="text-xs font-mono font-bold text-slate-900 dark:text-white">
                                        {{ $currCode }}: {{ $formattedVal }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <span class="text-xs font-bold text-slate-900 dark:text-white">
                                {{ $trend['formatted_total_amount'] }}
                            </span>
                        @endif
                        <span class="block text-[10px] text-slate-400 dark:text-slate-500">
                            {{ number_format($trend['total_count'], 0, ',', '.') }} pembayaran
                        </span>
                    </div>
                </div>

                {{-- CSS / SVG Histogram Chart Bars --}}
                @if(count($trend['intervals']) > 0)
                    <div class="pt-4 pb-2">
                        <div class="h-44 flex items-end gap-1 sm:gap-1.5 overflow-x-auto pb-2" role="img" aria-label="Grafik Tren Revenue Harian">
                            @foreach($trend['intervals'] as $item)
                                @php
                                    $heightPercent = $trend['max_count'] > 0
                                        ? max(4, (int) round(($item['count'] / $trend['max_count']) * 100))
                                        : 4;
                                @endphp
                                <div class="flex-1 min-w-[14px] sm:min-w-[20px] flex flex-col items-center justify-end h-full group relative">
                                    {{-- Tooltip Hover --}}
                                    <div class="absolute bottom-full mb-1.5 hidden group-hover:flex flex-col items-center z-20 pointer-events-none">
                                        <div class="bg-slate-900 dark:bg-slate-800 text-white text-[10px] rounded-lg px-2 py-1 shadow-lg whitespace-nowrap text-center">
                                            <p class="font-bold">{{ $item['label'] }}</p>
                                            @if(count($item['currencies']) > 0)
                                                @foreach($item['currencies'] as $cItem)
                                                    <p class="text-emerald-400 font-mono">{{ $cItem['formatted'] }} ({{ $cItem['count'] }} payment)</p>
                                                @endforeach
                                            @else
                                                <p class="text-slate-400">0 pembayaran</p>
                                            @endif
                                            <p class="text-slate-300">{{ $item['count'] }} total pembayaran</p>
                                        </div>
                                        <div class="w-1.5 h-1.5 bg-slate-900 dark:bg-slate-800 rotate-45 -mt-0.5"></div>
                                    </div>

                                    {{-- Bar --}}
                                    <div
                                        class="w-full rounded-t-md transition-all duration-300 {{ $item['count'] > 0 ? 'bg-indigo-600 hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400' : 'bg-slate-100 dark:bg-slate-800' }}"
                                        style="height: {{ $heightPercent }}%;"
                                    ></div>

                                    {{-- Day Label (Periodically shown) --}}
                                    @if(count($trend['intervals']) <= 14 || $loop->first || $loop->last || $loop->iteration % 5 === 0)
                                        <span class="text-[9px] text-slate-400 dark:text-slate-500 mt-1 whitespace-nowrap">
                                            {{ $item['short_label'] }}
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="p-8 text-center text-xs text-slate-400 dark:text-slate-500">
                        Tidak ada data revenue pada rentang periode yang dipilih.
                    </div>
                @endif
            </div>

            {{-- Right: Billing Period & Payment Attempt Status Funnel --}}
            <div class="space-y-4">
                {{-- Billing Period Breakdown (Bulanan vs Tahunan) --}}
                <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="layers" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                        Distribusi Periode Langganan
                    </h3>
                    <div class="space-y-3">
                        {{-- Bulanan --}}
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Bulanan (Monthly)</span>
                                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                                    {{ $billing_period_breakdown['monthly']['count'] }} pembayaran
                                </span>
                            </div>
                            @if(count($billing_period_breakdown['monthly']['currencies']) > 1)
                                <div class="mt-2 space-y-1 pt-1.5 border-t border-slate-200/60 dark:border-slate-700/60">
                                    @foreach($billing_period_breakdown['monthly']['currencies'] as $currItem)
                                        <div class="flex items-center justify-between text-[11px]">
                                            <span class="font-semibold text-slate-600 dark:text-slate-400">{{ $currItem['currency'] }}:</span>
                                            <span class="font-mono font-bold text-slate-900 dark:text-white">
                                                {{ $currItem['formatted'] }} ({{ $currItem['count'] }} pembayaran)
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-1 text-right">
                                    <span class="text-xs font-mono font-bold text-slate-900 dark:text-white">
                                        {{ $billing_period_breakdown['monthly']['formatted'] }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        {{-- Tahunan --}}
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Tahunan (Yearly)</span>
                                <span class="text-[11px] text-slate-400 dark:text-slate-500">
                                    {{ $billing_period_breakdown['yearly']['count'] }} pembayaran
                                </span>
                            </div>
                            @if(count($billing_period_breakdown['yearly']['currencies']) > 1)
                                <div class="mt-2 space-y-1 pt-1.5 border-t border-slate-200/60 dark:border-slate-700/60">
                                    @foreach($billing_period_breakdown['yearly']['currencies'] as $currItem)
                                        <div class="flex items-center justify-between text-[11px]">
                                            <span class="font-semibold text-slate-600 dark:text-slate-400">{{ $currItem['currency'] }}:</span>
                                            <span class="font-mono font-bold text-slate-900 dark:text-white">
                                                {{ $currItem['formatted'] }} ({{ $currItem['count'] }} pembayaran)
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-1 text-right">
                                    <span class="text-xs font-mono font-bold text-slate-900 dark:text-white">
                                        {{ $billing_period_breakdown['yearly']['formatted'] }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Status Payment Dibuat pada Periode (created_at) --}}
                <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="clock" class="w-4 h-4 text-purple-600 dark:text-purple-400"></i>
                            Status Payment Dibuat pada Periode
                        </h3>
                        <span class="text-[10px] text-slate-400 dark:text-slate-500">created_at</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 text-center border border-slate-100 dark:border-slate-800">
                            <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Pending</span>
                            <span class="text-sm font-bold text-amber-600 dark:text-amber-400">
                                {{ $payment_status_distribution['pending'] }}
                            </span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 text-center border border-slate-100 dark:border-slate-800">
                            <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Paid</span>
                            <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                {{ $payment_status_distribution['paid'] }}
                            </span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 text-center border border-slate-100 dark:border-slate-800">
                            <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Failed</span>
                            <span class="text-sm font-bold text-rose-600 dark:text-rose-400">
                                {{ $payment_status_distribution['failed'] }}
                            </span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 text-center border border-slate-100 dark:border-slate-800">
                            <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Expired</span>
                            <span class="text-sm font-bold text-slate-600 dark:text-slate-300">
                                {{ $payment_status_distribution['expired'] }}
                            </span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 text-center border border-slate-100 dark:border-slate-800">
                            <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Cancelled</span>
                            <span class="text-sm font-bold text-slate-600 dark:text-slate-300">
                                {{ $payment_status_distribution['cancelled'] }}
                            </span>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 text-center border border-slate-100 dark:border-slate-800">
                            <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Refunded</span>
                            <span class="text-sm font-bold text-purple-600 dark:text-purple-400">
                                {{ $payment_status_distribution['refunded'] }}
                            </span>
                        </div>
                    </div>
                    <div class="pt-1 text-[11px] text-slate-400 dark:text-slate-500 flex justify-between">
                        <span>Total Checkout Records:</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300">{{ $payment_status_distribution['total'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Top Paying Businesses Table (IDR Revenue Only) --}}
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="building-2" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                        Top 10 Bisnis Penghasil Revenue (IDR)
                    </h2>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                        Berdasarkan pembayaran berstatus paid dalam mata uang IDR pada periode {{ $current_filters['period_label'] }}. Pembayaran mata uang lain dirinci pada panel mata uang.
                    </p>
                </div>
            </div>

            @if(count($top_businesses) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200/80 dark:border-slate-800 text-[11px] text-slate-400 dark:text-slate-500 uppercase tracking-wider font-semibold">
                                <th class="py-2.5 px-3">#</th>
                                <th class="py-2.5 px-3">Bisnis</th>
                                <th class="py-2.5 px-3 text-center">Status Cloud Saat Ini</th>
                                <th class="py-2.5 px-3 text-right">Pembayaran Berhasil</th>
                                <th class="py-2.5 px-3 text-center">Paket (Bln / Thn)</th>
                                <th class="py-2.5 px-3 text-right">Revenue Paid (IDR)</th>
                                <th class="py-2.5 px-3 text-right">Terakhir Terbayar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($top_businesses as $index => $item)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="py-3 px-3 text-slate-400 dark:text-slate-500 font-mono">{{ $index + 1 }}</td>
                                    <td class="py-3 px-3">
                                        <div class="font-bold text-slate-900 dark:text-white">
                                            {{ $item['business_name'] }}
                                        </div>
                                        @if($item['business_slug'] !== '')
                                            <span class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">{{ $item['business_slug'] }}</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        @if($item['has_cloud_access'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200/70 dark:border-emerald-800/50">
                                                Cloud Aktif
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                                Free / Non-Cloud
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 text-right font-semibold text-slate-900 dark:text-white">
                                        {{ $item['paid_count'] }}
                                    </td>
                                    <td class="py-3 px-3 text-center text-slate-600 dark:text-slate-400">
                                        {{ $item['monthly_count'] }} bln / {{ $item['yearly_count'] }} thn
                                    </td>
                                    <td class="py-3 px-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                        {{ $item['formatted_revenue'] }}
                                    </td>
                                    <td class="py-3 px-3 text-right text-slate-500 dark:text-slate-400 text-[11px]">
                                        {{ $item['formatted_last_paid'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-8 text-center text-xs text-slate-400 dark:text-slate-500">
                    Tidak ada pembayaran berstatus paid pada rentang periode yang dipilih.
                </div>
            @endif
        </div>

        {{-- Current Subscription Landscape Snapshot (Real-time snapshot across all merchants) --}}
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="shield-check" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                        Kondisi Subscription Saat Ini
                    </h2>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                        Snapshot status langganan seluruh tenant merchant saat ini (independen dari filter rentang pembayaran historis).
                    </p>
                </div>
                <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
                    Total {{ $subscription_snapshot['total_businesses'] }} Bisnis
                </span>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                {{-- Cloud Aktif --}}
                <div class="p-3.5 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/40 border border-emerald-200/80 dark:border-emerald-800/80">
                    <span class="text-[11px] font-semibold text-emerald-800 dark:text-emerald-300 block">Cloud Aktif</span>
                    <span class="text-xl font-black text-emerald-700 dark:text-emerald-400 mt-1 block">
                        {{ $subscription_snapshot['cloud_active'] }}
                    </span>
                    <span class="text-[10px] text-emerald-600 dark:text-emerald-500 mt-0.5 block">
                        Plan cloud aktif &amp; belum expired
                    </span>
                </div>

                {{-- Cloud Kedaluwarsa --}}
                <div class="p-3.5 rounded-xl bg-amber-50/70 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-800/80">
                    <span class="text-[11px] font-semibold text-amber-800 dark:text-amber-300 block">Cloud Kedaluwarsa</span>
                    <span class="text-xl font-black text-amber-700 dark:text-amber-400 mt-1 block">
                        {{ $subscription_snapshot['cloud_expired'] }}
                    </span>
                    <span class="text-[10px] text-amber-600 dark:text-amber-500 mt-0.5 block">
                        Status expired atau tanggal kedaluwarsa lampau
                    </span>
                </div>

                {{-- Cloud Tidak Aktif --}}
                <div class="p-3.5 rounded-xl bg-rose-50/70 dark:bg-rose-950/40 border border-rose-200/80 dark:border-rose-800/80">
                    <span class="text-[11px] font-semibold text-rose-800 dark:text-rose-300 block">Cloud Non-aktif</span>
                    <span class="text-xl font-black text-rose-700 dark:text-rose-400 mt-1 block">
                        {{ $subscription_snapshot['cloud_inactive'] }}
                    </span>
                    <span class="text-[10px] text-rose-600 dark:text-rose-500 mt-0.5 block">
                        Status langganan dinonaktifkan
                    </span>
                </div>

                {{-- Free / Tanpa Langganan --}}
                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800">
                    <span class="text-[11px] font-semibold text-slate-700 dark:text-slate-300 block">Free / Non-Cloud</span>
                    <span class="text-xl font-black text-slate-800 dark:text-slate-200 mt-1 block">
                        {{ $subscription_snapshot['free_or_none'] }}
                    </span>
                    <span class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 block">
                        Tenant paket gratis / tanpa cloud
                    </span>
                </div>
            </div>
        </div>

        {{-- Metric Notes & Limitations Section --}}
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs space-y-3">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="info" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                Tentang Metrik &amp; Keterbatasan Operasional
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-1">
                    <h3 class="font-bold text-slate-800 dark:text-slate-200">Revenue Subscription Paid</h3>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed text-[11px]">
                        Revenue dihitung dari status payment saat ini. Payment yang kemudian berubah menjadi refunded tidak lagi termasuk revenue paid historis.
                    </p>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-1">
                    <h3 class="font-bold text-slate-800 dark:text-slate-200">Snapshot Nilai Historis Checkout</h3>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed text-[11px]">
                        Nilai amount yang tersimpan pada baris payment saat checkout adalah sumber kebenaran historis, bukan harga katalog saat ini.
                    </p>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-1">
                    <h3 class="font-bold text-slate-800 dark:text-slate-200">Keamanan Mata Uang (Currency Safety)</h3>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed text-[11px]">
                        Sistem tidak pernah menjumlah nominal lintas mata uang tanpa kurs konversi. Ranking bisnis dan grafik harian difokuskan pada IDR, dengan rincian mata uang lain disajikan terpisah.
                    </p>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-1">
                    <h3 class="font-bold text-slate-800 dark:text-slate-200">Status Payment Dibuat (created_at)</h3>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed text-[11px]">
                        Distribusi status percobaan pembayaran (pending, failed, expired, cancelled) dihitung berdasarkan created_at karena database server tidak menyimpan timestamp transisi status khusus.
                    </p>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-1">
                    <h3 class="font-bold text-slate-800 dark:text-slate-200">Klasifikasi Aktivasi Pembayaran</h3>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed text-[11px]">
                        Diturunkan dari urutan payment activation: aktivasi pertama = baru, aktivasi berikutnya = renewal.
                    </p>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 space-y-1">
                    <h3 class="font-bold text-slate-800 dark:text-slate-200">Aktivasi Manual Platform Admin</h3>
                    <p class="text-slate-600 dark:text-slate-400 leading-relaxed text-[11px]">
                        Aktivasi manual Platform Admin tidak termasuk klasifikasi payment activation.
                    </p>
                </div>
            </div>
        </div>

    </div>
</x-layouts::platform>
