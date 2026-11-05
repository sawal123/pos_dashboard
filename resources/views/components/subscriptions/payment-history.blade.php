@props([
    'payments' => null,
])

<section class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-5 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
        <div>
            <h2 class="text-sm font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="receipt" class="w-4 h-4 text-indigo-600 dark:text-indigo-400"></i>
                <span>Riwayat Transaksi Langganan</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Daftar transaksi pembayaran paket Cloud dan tagihan akun bisnis Anda.
            </p>
        </div>
    </div>

    @if($payments === null || $payments->isEmpty())
        <div class="py-8 text-center space-y-2">
            <div class="w-10 h-10 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 flex items-center justify-center mx-auto">
                <i data-lucide="inbox" class="w-5 h-5"></i>
            </div>
            <p class="text-xs font-medium text-slate-600 dark:text-slate-300">Belum ada riwayat transaksi</p>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">Transaksi baru akan tercatat di sini setelah Anda memulai langganan.</p>
        </div>
    @else
        {{-- Desktop Table --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600 dark:text-slate-300">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-bold">
                        <th class="py-3 px-3">Order ID & Tanggal</th>
                        <th class="py-3 px-3">Paket</th>
                        <th class="py-3 px-3">Periode</th>
                        <th class="py-3 px-3 text-right">Total</th>
                        <th class="py-3 px-3 text-center">Status</th>
                        <th class="py-3 px-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($payments as $payment)
                        @php
                            $isPending = $payment->status === \App\Models\SubscriptionPayment::STATUS_PENDING;
                            $isPaid = $payment->status === \App\Models\SubscriptionPayment::STATUS_PAID;
                            $isCancelled = $payment->status === \App\Models\SubscriptionPayment::STATUS_CANCELLED;
                            $isFailed = $payment->status === \App\Models\SubscriptionPayment::STATUS_FAILED;
                            $isExpired = $payment->status === \App\Models\SubscriptionPayment::STATUS_EXPIRED;

                            $badgeClass = match($payment->status) {
                                \App\Models\SubscriptionPayment::STATUS_PAID => 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                                \App\Models\SubscriptionPayment::STATUS_PENDING => 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
                                \App\Models\SubscriptionPayment::STATUS_FAILED => 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
                                \App\Models\SubscriptionPayment::STATUS_CANCELLED => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700',
                                \App\Models\SubscriptionPayment::STATUS_EXPIRED => 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700',
                                default => 'bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800',
                            };

                            $dotClass = match($payment->status) {
                                \App\Models\SubscriptionPayment::STATUS_PAID => 'bg-emerald-500',
                                \App\Models\SubscriptionPayment::STATUS_PENDING => 'bg-amber-500 animate-pulse',
                                \App\Models\SubscriptionPayment::STATUS_FAILED => 'bg-rose-500',
                                default => 'bg-slate-400',
                            };

                            $statusText = match($payment->status) {
                                \App\Models\SubscriptionPayment::STATUS_PAID => 'Berhasil',
                                \App\Models\SubscriptionPayment::STATUS_PENDING => 'Menunggu Pembayaran',
                                \App\Models\SubscriptionPayment::STATUS_FAILED => 'Gagal',
                                \App\Models\SubscriptionPayment::STATUS_CANCELLED => 'Dibatalkan',
                                \App\Models\SubscriptionPayment::STATUS_EXPIRED => 'Kedaluwarsa',
                                default => ucfirst($payment->status),
                            };

                            $periodLabel = match($payment->billing_period) {
                                'monthly' => 'Bulanan',
                                'yearly' => 'Tahunan',
                                default => ucfirst((string) $payment->billing_period),
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                            {{-- Order ID & Date --}}
                            <td class="py-3 px-3">
                                <div class="font-mono font-semibold text-slate-900 dark:text-white truncate max-w-[200px]" title="{{ $payment->provider_order_id }}">
                                    {{ $payment->provider_order_id }}
                                </div>
                                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5">
                                    {{ $payment->created_at?->translatedFormat('d M Y, H:i') ?? '-' }} WIB
                                </div>
                            </td>

                            {{-- Plan --}}
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/70 dark:border-indigo-800/60">
                                    <i data-lucide="cloud" class="w-3 h-3"></i>
                                    <span>{{ ucfirst($payment->plan) }}</span>
                                </span>
                            </td>

                            {{-- Period --}}
                            <td class="py-3 px-3">
                                <span class="text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {{ $periodLabel }}
                                </span>
                            </td>

                            {{-- Amount --}}
                            <td class="py-3 px-3 text-right font-bold text-slate-900 dark:text-white tabular-nums">
                                Rp {{ number_format((int) $payment->amount, 0, ',', '.') }}
                            </td>

                            {{-- Status Badge --}}
                            <td class="py-3 px-3 text-center">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold border {{ $badgeClass }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $dotClass }}"></span>
                                    <span>{{ $statusText }}</span>
                                </span>
                            </td>

                            {{-- Actions --}}
                            <td class="py-3 px-3 text-right">
                                @if($isPending)
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        @if(!empty($payment->redirect_url))
                                            <a
                                                href="{{ $payment->redirect_url }}"
                                                target="_blank"
                                                class="px-2.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs transition-colors inline-flex items-center gap-1 shadow-xs"
                                                title="Lanjutkan Pembayaran"
                                            >
                                                <i data-lucide="external-link" class="w-3 h-3"></i>
                                                <span>Bayar</span>
                                            </a>
                                        @endif
                                        <form
                                            method="POST"
                                            action="{{ route('subscriptions.payments.cancel', ['paymentId' => $payment->id]) }}"
                                            data-confirm-title="Batalkan Transaksi Pembayaran"
                                            data-confirm-message="Apakah Anda yakin ingin membatalkan transaksi pembayaran ini?"
                                            data-confirm-button="Ya, Batalkan"
                                            data-confirm-variant="danger"
                                            class="inline"
                                        >
                                            @csrf
                                            <button
                                                type="submit"
                                                class="px-2.5 py-1.5 rounded-xl border border-rose-200 dark:border-rose-800 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 font-semibold text-xs transition-colors cursor-pointer"
                                                title="Batalkan Transaksi"
                                            >
                                                Batalkan
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-slate-400 text-xs">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Mobile Cards --}}
        <div class="md:hidden space-y-3">
            @foreach($payments as $payment)
                @php
                    $isPending = $payment->status === \App\Models\SubscriptionPayment::STATUS_PENDING;
                    $badgeClass = match($payment->status) {
                        \App\Models\SubscriptionPayment::STATUS_PAID => 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                        \App\Models\SubscriptionPayment::STATUS_PENDING => 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
                        \App\Models\SubscriptionPayment::STATUS_FAILED => 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
                        \App\Models\SubscriptionPayment::STATUS_CANCELLED => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700',
                        \App\Models\SubscriptionPayment::STATUS_EXPIRED => 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700',
                        default => 'bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800',
                    };
                    $statusText = match($payment->status) {
                        \App\Models\SubscriptionPayment::STATUS_PAID => 'Berhasil',
                        \App\Models\SubscriptionPayment::STATUS_PENDING => 'Menunggu Pembayaran',
                        \App\Models\SubscriptionPayment::STATUS_FAILED => 'Gagal',
                        \App\Models\SubscriptionPayment::STATUS_CANCELLED => 'Dibatalkan',
                        \App\Models\SubscriptionPayment::STATUS_EXPIRED => 'Kedaluwarsa',
                        default => ucfirst($payment->status),
                    };
                    $periodLabel = match($payment->billing_period) {
                        'monthly' => 'Bulanan',
                        'yearly' => 'Tahunan',
                        default => ucfirst((string) $payment->billing_period),
                    };
                @endphp
                <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 space-y-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="space-y-0.5">
                            <p class="font-mono text-xs font-bold text-slate-900 dark:text-white truncate max-w-[200px]">
                                {{ $payment->provider_order_id }}
                            </p>
                            <p class="text-[11px] text-slate-400">
                                {{ $payment->created_at?->translatedFormat('d M Y, H:i') ?? '-' }} WIB
                            </p>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border {{ $badgeClass }}">
                            {{ $statusText }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
                        <span class="text-slate-500 dark:text-slate-400">Paket {{ ucfirst($payment->plan) }} ({{ $periodLabel }})</span>
                        <span class="font-bold text-slate-900 dark:text-white tabular-nums">
                            Rp {{ number_format((int) $payment->amount, 0, ',', '.') }}
                        </span>
                    </div>

                    @if($isPending)
                        <div class="flex items-center gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                            @if(!empty($payment->redirect_url))
                                <a
                                    href="{{ $payment->redirect_url }}"
                                    target="_blank"
                                    class="flex-1 py-2 text-center rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs transition-colors"
                                >
                                    Bayar Sekarang
                                </a>
                            @endif
                            <form
                                method="POST"
                                action="{{ route('subscriptions.payments.cancel', ['paymentId' => $payment->id]) }}"
                                data-confirm-title="Batalkan Transaksi Pembayaran"
                                data-confirm-message="Apakah Anda yakin ingin membatalkan transaksi pembayaran ini?"
                                data-confirm-button="Ya, Batalkan"
                                data-confirm-variant="danger"
                                class="{{ !empty($payment->redirect_url) ? '' : 'w-full' }}"
                            >
                                @csrf
                                <button
                                    type="submit"
                                    class="w-full px-3 py-2 rounded-xl border border-rose-200 dark:border-rose-800 text-rose-600 dark:text-rose-400 font-semibold text-xs"
                                >
                                    Batalkan
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Pagination Links --}}
        @if($payments->hasPages())
            <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                {{ $payments->links() }}
            </div>
        @endif
    @endif
</section>

