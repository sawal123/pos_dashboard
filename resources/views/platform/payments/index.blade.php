<x-layouts::platform :title="'Pembayaran'">
    <div class="space-y-6">

        {{-- Page Heading --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    Manajemen Pembayaran
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Riwayat pembayaran langganan Midtrans, observabilitas transaksi, dan diagnostik rekonsiliasi operator.
                </p>
            </div>
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs text-xs font-bold text-slate-700 dark:text-slate-300 shrink-0">
                <i data-lucide="receipt" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                <span>Total: <strong class="text-slate-900 dark:text-white">{{ number_format($payments->total()) }}</strong> Transaksi</span>
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
            {{-- Total Transaksi --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Transaksi</span>
                    <span class="p-1.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                        <i data-lucide="credit-card" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl font-black text-slate-900 dark:text-white">
                    {{ number_format($summary['total']) }}
                </div>
            </div>

            {{-- Menunggu Pembayaran --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Menunggu</span>
                    <span class="p-1.5 rounded-lg bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400">
                        <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl font-black text-amber-600 dark:text-amber-400">
                    {{ number_format($summary['pending']) }}
                </div>
            </div>

            {{-- Pembayaran Berhasil --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Berhasil</span>
                    <span class="p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400">
                        <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl font-black text-emerald-600 dark:text-emerald-400">
                    {{ number_format($summary['paid']) }}
                </div>
            </div>

            {{-- Gagal / Expired --}}
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Gagal / Kedaluwarsa</span>
                    <span class="p-1.5 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
                        <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-xl font-black text-rose-600 dark:text-rose-400">
                    {{ number_format($summary['failed']) }}
                </div>
            </div>

            {{-- Total Pembayaran Berhasil (Nominal IDR) --}}
            <div class="col-span-2 sm:col-span-2 lg:col-span-1 p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Pembayaran Berhasil</span>
                    <span class="p-1.5 rounded-lg bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400">
                        <i data-lucide="wallet" class="w-3.5 h-3.5"></i>
                    </span>
                </div>
                <div class="mt-2 text-base sm:text-lg font-black text-slate-900 dark:text-white truncate" title="Rp {{ number_format($summary['total_paid_amount'], 0, ',', '.') }}">
                    Rp {{ number_format($summary['total_paid_amount'], 0, ',', '.') }}
                </div>
            </div>
        </div>

        {{-- Search & Filter Bar --}}
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
            <form method="GET" action="{{ route('platform.payments.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
                {{-- Search query input --}}
                <div class="lg:col-span-4 relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i data-lucide="search" class="w-4 h-4"></i>
                    </span>
                    <input
                        type="text"
                        name="q"
                        value="{{ $filters['q'] }}"
                        placeholder="Cari order ID, bisnis, atau slug..."
                        class="w-full pl-9 pr-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        aria-label="Cari pembayaran"
                    />
                </div>

                {{-- Status Filter --}}
                <div class="lg:col-span-3">
                    <select
                        name="status"
                        class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        aria-label="Filter status pembayaran"
                    >
                        <option value="">Semua Status</option>
                        <option value="pending" {{ $filters['status'] === 'pending' ? 'selected' : '' }}>Menunggu Pembayaran</option>
                        <option value="paid" {{ $filters['status'] === 'paid' ? 'selected' : '' }}>Berhasil</option>
                        <option value="failed" {{ $filters['status'] === 'failed' ? 'selected' : '' }}>Gagal</option>
                        <option value="expired" {{ $filters['status'] === 'expired' ? 'selected' : '' }}>Kedaluwarsa</option>
                        <option value="cancelled" {{ $filters['status'] === 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
                        <option value="refunded" {{ $filters['status'] === 'refunded' ? 'selected' : '' }}>Dikembalikan</option>
                    </select>
                </div>

                {{-- Billing Period Filter --}}
                <div class="lg:col-span-2">
                    <select
                        name="billing_period"
                        class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        aria-label="Filter periode tagihan"
                    >
                        <option value="">Semua Periode</option>
                        <option value="monthly" {{ $filters['billing_period'] === 'monthly' ? 'selected' : '' }}>Bulanan (Monthly)</option>
                        <option value="yearly" {{ $filters['billing_period'] === 'yearly' ? 'selected' : '' }}>Tahunan (Yearly)</option>
                    </select>
                </div>

                {{-- Plan Filter --}}
                <div class="lg:col-span-1">
                    <select
                        name="plan"
                        class="w-full px-3 py-2 rounded-xl text-xs bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                        aria-label="Filter paket"
                    >
                        <option value="">Paket</option>
                        <option value="cloud" {{ $filters['plan'] === 'cloud' ? 'selected' : '' }}>Cloud</option>
                        <option value="free" {{ $filters['plan'] === 'free' ? 'selected' : '' }}>Free</option>
                    </select>
                </div>

                {{-- Actions --}}
                <div class="lg:col-span-2 flex items-center gap-2">
                    <button
                        type="submit"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                        Filter
                    </button>
                    @if($filters['q'] || $filters['status'] || $filters['billing_period'] || $filters['plan'])
                        <a
                            href="{{ route('platform.payments.index') }}"
                            class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 text-xs font-semibold transition-colors"
                            title="Reset Filter"
                        >
                            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Table Container --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 dark:text-slate-400" aria-label="Daftar Pembayaran Platform">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 select-none">
                        <tr>
                            <th scope="col" class="py-3.5 px-4 md:px-6">Order ID</th>
                            <th scope="col" class="py-3.5 px-4">Bisnis</th>
                            <th scope="col" class="py-3.5 px-4">Paket</th>
                            <th scope="col" class="py-3.5 px-4">Periode</th>
                            <th scope="col" class="py-3.5 px-4">Nominal</th>
                            <th scope="col" class="py-3.5 px-4">Status</th>
                            <th scope="col" class="py-3.5 px-4">Provider</th>
                            <th scope="col" class="py-3.5 px-4">Dibayar</th>
                            <th scope="col" class="py-3.5 px-4">Dibuat</th>
                            <th scope="col" class="py-3.5 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($payments as $payment)
                            @php
                                $statusClass = match($payment->status) {
                                    \App\Models\SubscriptionPayment::STATUS_PAID => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border-emerald-200/60 dark:border-emerald-800/60',
                                    \App\Models\SubscriptionPayment::STATUS_PENDING => 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border-amber-200/60 dark:border-amber-800/60',
                                    \App\Models\SubscriptionPayment::STATUS_FAILED => 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border-rose-200/60 dark:border-rose-800/60',
                                    \App\Models\SubscriptionPayment::STATUS_EXPIRED => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700',
                                    \App\Models\SubscriptionPayment::STATUS_CANCELLED => 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700',
                                    \App\Models\SubscriptionPayment::STATUS_REFUNDED => 'bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-400 border-purple-200/60 dark:border-purple-800/60',
                                    default => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700',
                                };
                                $statusLabel = match($payment->status) {
                                    \App\Models\SubscriptionPayment::STATUS_PAID => 'Berhasil',
                                    \App\Models\SubscriptionPayment::STATUS_PENDING => 'Menunggu',
                                    \App\Models\SubscriptionPayment::STATUS_FAILED => 'Gagal',
                                    \App\Models\SubscriptionPayment::STATUS_EXPIRED => 'Kedaluwarsa',
                                    \App\Models\SubscriptionPayment::STATUS_CANCELLED => 'Dibatalkan',
                                    \App\Models\SubscriptionPayment::STATUS_REFUNDED => 'Dikembalikan',
                                    default => ucfirst($payment->status),
                                };
                                $dotColor = match($payment->status) {
                                    \App\Models\SubscriptionPayment::STATUS_PAID => 'bg-emerald-500',
                                    \App\Models\SubscriptionPayment::STATUS_PENDING => 'bg-amber-500',
                                    \App\Models\SubscriptionPayment::STATUS_FAILED => 'bg-rose-500',
                                    \App\Models\SubscriptionPayment::STATUS_REFUNDED => 'bg-purple-500',
                                    default => 'bg-slate-400',
                                };
                            @endphp
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                {{-- Order ID --}}
                                <td class="py-3.5 px-4 md:px-6">
                                    <div class="font-mono font-bold text-slate-900 dark:text-white truncate max-w-[180px]" title="{{ $payment->provider_order_id }}">
                                        {{ $payment->provider_order_id }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 dark:text-slate-500">
                                        ID: #{{ $payment->id }}
                                    </div>
                                </td>

                                {{-- Bisnis --}}
                                <td class="py-3.5 px-4">
                                    @if($payment->business)
                                        <div class="font-bold text-slate-900 dark:text-white truncate max-w-[160px]">
                                            <a href="{{ route('platform.businesses.show', $payment->business) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 focus:outline-none">
                                                {{ $payment->business->name }}
                                            </a>
                                        </div>
                                        <div class="text-[11px] font-mono text-slate-400 dark:text-slate-500 truncate max-w-[160px]">
                                            {{ $payment->business->slug }}
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic">Bisnis tidak tersedia</span>
                                    @endif
                                </td>

                                {{-- Paket --}}
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[11px] font-bold {{ $payment->plan === 'cloud' ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/80 dark:border-indigo-800/60' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700' }}">
                                        @if($payment->plan === 'cloud')
                                            <i data-lucide="cloud" class="w-3 h-3 text-indigo-600 dark:text-indigo-400"></i>
                                            Cloud
                                        @else
                                            <i data-lucide="box" class="w-3 h-3 text-slate-400"></i>
                                            Free
                                        @endif
                                    </span>
                                </td>

                                {{-- Periode --}}
                                <td class="py-3.5 px-4">
                                    <span class="inline-block px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        {{ $payment->billing_period === 'monthly' ? 'Bulanan' : ($payment->billing_period === 'yearly' ? 'Tahunan' : ucfirst($payment->billing_period)) }}
                                    </span>
                                </td>

                                {{-- Nominal --}}
                                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                    Rp {{ number_format($payment->amount, 0, ',', '.') }}
                                </td>

                                {{-- Status --}}
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold border {{ $statusClass }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $dotColor }}"></span>
                                        {{ $statusLabel }}
                                    </span>
                                </td>

                                {{-- Provider --}}
                                <td class="py-3.5 px-4 text-slate-600 dark:text-slate-400 uppercase text-[11px] font-bold">
                                    {{ $payment->provider }}
                                </td>

                                {{-- Dibayar --}}
                                <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400 whitespace-nowrap text-[11px]">
                                    {{ $payment->paid_at ? $payment->paid_at->format('d M Y H:i') : '-' }}
                                </td>

                                {{-- Dibuat --}}
                                <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400 whitespace-nowrap text-[11px]">
                                    {{ $payment->created_at ? $payment->created_at->format('d M Y H:i') : '-' }}
                                </td>

                                {{-- Aksi --}}
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <a
                                        href="{{ route('platform.payments.show', $payment) }}"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-semibold transition-colors"
                                    >
                                        <span>Detail</span>
                                        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-12 px-4 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 dark:text-slate-500 mb-3">
                                            <i data-lucide="receipt" class="w-6 h-6"></i>
                                        </div>
                                        <p class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada riwayat pembayaran</p>
                                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1 max-w-sm">
                                            @if($filters['q'] || $filters['status'] || $filters['billing_period'] || $filters['plan'])
                                                Tidak ditemukan pembayaran yang sesuai dengan kriteria filter pencarian.
                                            @else
                                                Belum ada transaksi pembayaran subscription yang tercatat di sistem.
                                            @endif
                                        </p>
                                        @if($filters['q'] || $filters['status'] || $filters['billing_period'] || $filters['plan'])
                                            <a
                                                href="{{ route('platform.payments.index') }}"
                                                class="mt-4 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                                            >
                                                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                                                Reset Filter
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination Footer --}}
            @if($payments->hasPages())
                <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                    {{ $payments->links() }}
                </div>
            @endif
        </div>

    </div>
</x-layouts::platform>
