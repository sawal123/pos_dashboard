@props(['taxEnabled' => false, 'taxRate' => 11.0])

<form
    method="POST"
    action="{{ route('business-settings.tax.update') }}"
    id="taxSettingsForm"
    class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-5 space-y-5"
>
    @csrf
    @method('PATCH')

    <div>
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                <i data-lucide="receipt-tax" class="w-4 h-4"></i>
            </div>
            <div>
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white">Pengaturan Pajak (PB1 / PPN)</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Atur tarif pajak yang dikenakan pada transaksi penjualan di Web POS dan Mobile POS.
                </p>
            </div>
        </div>
    </div>

    {{-- Switch / Toggle --}}
    <label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 cursor-pointer transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
        <div class="space-y-0.5">
            <span class="text-sm font-bold text-slate-900 dark:text-white block">Aktifkan Pajak Transaksi</span>
            <span class="text-xs text-slate-500 dark:text-slate-400 block">
                Jika dinonaktifkan, pesanan akan diproses tanpa beban pajak (Rp 0).
            </span>
        </div>
        <div class="relative inline-flex items-center">
            <input
                type="checkbox"
                name="tax_enabled"
                id="taxEnabledInput"
                value="1"
                @checked(old('tax_enabled', $taxEnabled))
                class="w-5 h-5 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300 dark:border-slate-600"
            >
        </div>
    </label>

    {{-- Tax Rate Input --}}
    <div id="taxRateContainer" class="space-y-1.5 {{ old('tax_enabled', $taxEnabled) ? '' : 'opacity-60' }}">
        <label for="taxRateInput" class="text-xs font-bold text-slate-700 dark:text-slate-300">
            Tarif Pajak (%)
        </label>
        <div class="relative max-w-xs">
            <input
                type="number"
                name="tax_rate"
                id="taxRateInput"
                min="0"
                max="100"
                step="0.01"
                value="{{ old('tax_rate', number_format((float) $taxRate, 2, '.', '')) }}"
                @disabled(!old('tax_enabled', $taxEnabled))
                class="w-full pl-3.5 pr-8 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-slate-100 dark:disabled:bg-slate-800/50 disabled:cursor-not-allowed"
                placeholder="11.00"
            >
            <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-400 pointer-events-none">%</span>
        </div>
        <p class="text-[11px] text-slate-400">
            Rentang nilai 0 – 100 dengan maksimal 2 angka desimal (contoh: 11% untuk PPN standar, 10% untuk PB1 resto).
        </p>
        @error('tax_rate')
            <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror
    </div>

    {{-- Dynamic Simulation Card --}}
    <div class="p-4 rounded-xl border border-indigo-100 dark:border-indigo-950/60 bg-indigo-50/40 dark:bg-indigo-950/20 space-y-2">
        <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
            Simulasi Perhitungan Transaksi
        </p>
        <div class="space-y-1 text-xs">
            <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                <span>Subtotal belanja</span>
                <span class="font-medium text-slate-800 dark:text-slate-200">Rp 100.000</span>
            </div>
            <div id="simTaxRow" class="flex items-center justify-between text-slate-600 dark:text-slate-400 {{ old('tax_enabled', $taxEnabled) ? '' : 'hidden' }}">
                <span>Pajak (<span id="simTaxRateText">{{ number_format((float) $taxRate, 2, '.', '') }}</span>%)</span>
                <span id="simTaxText" class="font-bold text-indigo-600 dark:text-indigo-400">+ Rp 11.000</span>
            </div>
            <div class="pt-1.5 border-t border-indigo-200/60 dark:border-indigo-900/60 flex items-center justify-between font-bold text-slate-900 dark:text-white">
                <span>Total tagihan</span>
                <span id="simTotalText" class="text-sm text-indigo-600 dark:text-indigo-400 font-extrabold">Rp 111.000</span>
            </div>
        </div>
    </div>

    <div class="flex justify-end pt-2">
        <button
            type="submit"
            id="saveTaxSettingsBtn"
            class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 transition-colors"
        >
            <i data-lucide="save" class="w-4 h-4"></i>
            Simpan Pengaturan Pajak
        </button>
    </div>
</form>

<script>
    (function() {
        const checkbox = document.getElementById('taxEnabledInput');
        const rateInput = document.getElementById('taxRateInput');
        const rateContainer = document.getElementById('taxRateContainer');
        const simTaxRow = document.getElementById('simTaxRow');
        const simTaxRateText = document.getElementById('simTaxRateText');
        const simTaxText = document.getElementById('simTaxText');
        const simTotalText = document.getElementById('simTotalText');

        function formatRupiah(num) {
            return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
        }

        function updateSimulation() {
            if (!checkbox || !rateInput) return;

            const isEnabled = checkbox.checked;
            rateInput.disabled = !isEnabled;
            if (isEnabled) {
                rateContainer.classList.remove('opacity-60');
            } else {
                rateContainer.classList.add('opacity-60');
            }

            const rawRate = parseFloat(rateInput.value) || 0;
            const validRate = Math.min(100, Math.max(0, rawRate));

            if (isEnabled) {
                simTaxRow.classList.remove('hidden');
                simTaxRateText.textContent = validRate.toFixed(2);
                const taxAmount = Math.round((100000 * validRate) / 100);
                simTaxText.textContent = '+ ' + formatRupiah(taxAmount);
                simTotalText.textContent = formatRupiah(100000 + taxAmount);
            } else {
                simTaxRow.classList.add('hidden');
                simTotalText.textContent = formatRupiah(100000);
            }
        }

        if (checkbox) {
            checkbox.addEventListener('change', updateSimulation);
        }
        if (rateInput) {
            rateInput.addEventListener('input', updateSimulation);
        }
        updateSimulation();
    })();
</script>

