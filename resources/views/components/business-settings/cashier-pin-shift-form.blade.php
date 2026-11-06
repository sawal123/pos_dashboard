@props(['cashierPin' => '123456', 'defaultOpeningCash' => 0, 'autoOpenShift' => true])

<form
    method="POST"
    action="{{ route('business-settings.cashier-pin-shift.update') }}"
    id="cashierPinShiftForm"
    class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-5 space-y-5"
>
    @csrf
    @method('PATCH')

    <div>
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                <i data-lucide="key-round" class="w-4 h-4"></i>
            </div>
            <div>
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white">Pengaturan PIN Kasir &amp; Shift</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    PIN kasir dan setelan shift disimpan di database Cloud agar kasir di POS Mobile tidak perlu input PIN atau modal awal manual saat login pertama kali.
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- PIN Kasir Input --}}
        <div class="space-y-1.5">
            <label for="cashierPinInput" class="text-xs font-bold text-slate-700 dark:text-slate-300">
                PIN Kasir (6 Digit)
            </label>
            <div class="relative">
                <input
                    type="password"
                    name="cashier_pin"
                    id="cashierPinInput"
                    maxlength="6"
                    pattern="\d{6}"
                    inputmode="numeric"
                    required
                    value="{{ old('cashier_pin', $cashierPin) }}"
                    class="w-full pl-3.5 pr-10 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-mono font-bold tracking-widest text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500"
                    placeholder="123456"
                >
                <button
                    type="button"
                    id="toggleCashierPinBtn"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
                    aria-label="Lihat PIN"
                >
                    <i data-lucide="eye" id="toggleCashierPinIcon" class="w-4 h-4"></i>
                </button>
            </div>
            <p class="text-[11px] text-slate-400">
                Wajib 6 digit angka numerik. Digunakan saat verifikasi otorisasi/konfirmasi di POS.
            </p>
            @error('cashier_pin')
                <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        {{-- Modal Awal Default --}}
        <div class="space-y-1.5">
            <label for="defaultOpeningCashInput" class="text-xs font-bold text-slate-700 dark:text-slate-300">
                Modal Kas Awal Shift (Rp)
            </label>
            <div class="relative">
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400 pointer-events-none">Rp</span>
                <input
                    type="number"
                    name="default_opening_cash"
                    id="defaultOpeningCashInput"
                    min="0"
                    step="1000"
                    required
                    value="{{ old('default_opening_cash', $defaultOpeningCash) }}"
                    class="w-full pl-10 pr-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500"
                    placeholder="0"
                >
            </div>
            <p class="text-[11px] text-slate-400">
                Modal uang kas fisik awal di laci kasir saat shift baru dibuka (isi 0 jika tidak ada modal).
            </p>
            @error('default_opening_cash')
                <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>
    </div>

    {{-- Switch / Toggle Auto Open Shift --}}
    <label class="flex items-center justify-between gap-4 p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 cursor-pointer transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
        <div class="space-y-0.5">
            <span class="text-sm font-bold text-slate-900 dark:text-white block">Otomatis Buka Shift Kasir</span>
            <span class="text-xs text-slate-500 dark:text-slate-400 block">
                Jika aktif, POS Mobile akan otomatis membuka shift dengan modal awal di atas tanpa menahan kasir di form pembukaan shift manual.
            </span>
        </div>
        <div class="relative inline-flex items-center">
            <input
                type="checkbox"
                name="auto_open_shift"
                id="autoOpenShiftInput"
                value="1"
                @checked(old('auto_open_shift', $autoOpenShift))
                class="w-5 h-5 rounded text-amber-600 focus:ring-amber-500 border-slate-300 dark:border-slate-600"
            >
        </div>
    </label>

    <div class="flex justify-end pt-2">
        <button
            type="submit"
            id="saveCashierPinShiftBtn"
            class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold shadow-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 transition-colors"
        >
            <i data-lucide="save" class="w-4 h-4"></i>
            <span>Simpan Pengaturan PIN &amp; Shift</span>
        </button>
    </div>
</form>

<script>
    (function () {
        function initCashierPinToggle() {
            const pinInput = document.getElementById('cashierPinInput');
            const toggleBtn = document.getElementById('toggleCashierPinBtn');
            const toggleIcon = document.getElementById('toggleCashierPinIcon');

            if (!pinInput || !toggleBtn) return;

            toggleBtn.onclick = function () {
                const isPassword = pinInput.type === 'password';
                pinInput.type = isPassword ? 'text' : 'password';
                if (toggleIcon) {
                    toggleIcon.setAttribute('data-lucide', isPassword ? 'eye-off' : 'eye');
                    if (window.lucide) {
                        window.lucide.createIcons();
                    }
                }
            };
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initCashierPinToggle);
        } else {
            initCashierPinToggle();
        }
        document.addEventListener('livewire:navigated', initCashierPinToggle);
    })();
</script>
