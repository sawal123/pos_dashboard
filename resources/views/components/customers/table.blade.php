@props(['customers'])

@php
    $formatRupiah = fn ($amount) => 'Rp '.number_format((int) $amount, 0, ',', '.');
    $badgeClass = function (string $status): string {
        return match ($status) {
            'active' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
            'inactive' => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700',
            'deleted' => 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800',
            default => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
        };
    };
@endphp

<div class="hidden lg:block rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse" id="desktopCustomersTable">
            <thead>
                <tr class="border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-800/40 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider select-none">
                    <th class="py-3.5 px-4 font-bold">Pelanggan</th>
                    <th class="py-3.5 px-4 font-bold">Status</th>
                    <th class="py-3.5 px-4 font-bold text-right">Transaksi Selesai</th>
                    <th class="py-3.5 px-4 font-bold text-right">Total Pembelian</th>
                    <th class="py-3.5 px-4 font-bold">Pembelian Terakhir</th>
                    <th class="py-3.5 px-4 font-bold text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 text-slate-700 dark:text-slate-200">
                @foreach($customers as $customer)
                    <tr class="customer-row hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="py-3.5 px-4">
                            <div class="font-bold text-slate-900 dark:text-white">{{ $customer['name'] }}</div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 space-x-2">
                                @if($customer['phone'])
                                    <span class="inline-flex items-center gap-1">
                                        <i data-lucide="phone" class="w-3 h-3 text-slate-400"></i>{{ $customer['phone'] }}
                                    </span>
                                @endif
                                @if($customer['email'])
                                    <span class="inline-flex items-center gap-1">
                                        <i data-lucide="mail" class="w-3 h-3 text-slate-400"></i>{{ $customer['email'] }}
                                    </span>
                                @endif
                                @if(! $customer['phone'] && ! $customer['email'])
                                    <span class="text-slate-400 dark:text-slate-500 italic">Tanpa kontak</span>
                                @endif
                            </div>
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold {{ $badgeClass($customer['status_raw']) }}">
                                {{ $customer['status'] }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4 text-right font-bold text-slate-900 dark:text-white tabular-nums">
                            {{ number_format((int) $customer['transactions_count'], 0, ',', '.') }}
                        </td>
                        <td class="py-3.5 px-4 text-right font-bold text-slate-900 dark:text-white tabular-nums">
                            {{ $formatRupiah($customer['purchase_total']) }}
                        </td>
                        <td class="py-3.5 px-4 whitespace-nowrap text-slate-500 dark:text-slate-400">
                            {{ $customer['last_purchase_at'] ?? '—' }}
                        </td>
                        <td class="py-3.5 px-4 text-center">
                            <button
                                type="button"
                                class="view-customer-detail-btn inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                data-detail-url="{{ route('customers.detail', $customer['id']) }}"
                                aria-label="Lihat detail pelanggan {{ $customer['name'] }}"
                            >
                                <i data-lucide="panel-right-open" class="w-4 h-4"></i>
                                Detail
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
