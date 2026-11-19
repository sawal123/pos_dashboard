@props(['outlets' => []])

<div
    id="userDeviceCodeModal"
    class="fixed inset-0 z-50 hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="deviceCodeModalTitle"
    tabindex="-1"
>
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-xs transition-opacity" data-close-device-code-modal></div>

    <div class="fixed inset-0 flex items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="w-full max-w-md my-4 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-2xl overflow-hidden animate-scale-in">
            {{-- Modal Header --}}
            <div class="flex items-center justify-between gap-3 px-6 py-4 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/30">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shadow-xs">
                        <i data-lucide="smartphone" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Verifikasi Perangkat</span>
                        <h2 id="deviceCodeModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white leading-tight">Kode 4 Digit Kasir</h2>
                    </div>
                </div>
                <button
                    type="button"
                    data-close-device-code-modal
                    class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Tutup form kode verifikasi"
                >
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <div class="p-6 space-y-4">
                {{-- Cashier Target Card --}}
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/70 dark:border-slate-700/60 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Kasir Tujuan</span>
                        <span id="deviceCodeModalUserRole" class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300">
                            Kasir
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i data-lucide="user" class="w-4 h-4 text-indigo-500 shrink-0"></i>
                        <span id="deviceCodeModalUserName" class="text-sm font-black text-slate-900 dark:text-white truncate">-</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <i data-lucide="map-pin" class="w-3.5 h-3.5 text-amber-500 shrink-0"></i>
                        <span>Cabang: <strong id="deviceCodeModalOutletName" class="text-slate-700 dark:text-slate-200">-</strong></span>
                    </div>
                </div>

                {{-- Outlet Selector (editable if needed) --}}
                <div>
                    <label for="deviceCodeOutletSelect" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">
                        Tugaskan ke Cabang / Outlet
                    </label>
                    <select
                        id="deviceCodeOutletSelect"
                        class="w-full text-xs bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500"
                    >
                        @foreach($outlets as $outlet)
                            <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Error Banner --}}
                <div id="deviceCodeErrorBanner" class="hidden p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/50 text-rose-700 dark:text-rose-300 text-xs font-medium"></div>

                {{-- Code Result Display (shown after generation) --}}
                <div id="deviceCodeResultSection" class="hidden space-y-3 pt-2">
                    <div class="text-center">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Kode Verifikasi Perangkat:</span>
                        
                        {{-- 4 Big Digits Display --}}
                        <div class="flex items-center justify-center gap-2.5 sm:gap-3 my-3">
                            <span id="digit0" class="w-13 h-16 sm:w-14 sm:h-16 rounded-2xl bg-amber-500/10 dark:bg-amber-950/60 border-2 border-amber-500/40 flex items-center justify-center text-3xl font-black text-amber-600 dark:text-amber-400 font-mono shadow-xs">0</span>
                            <span id="digit1" class="w-13 h-16 sm:w-14 sm:h-16 rounded-2xl bg-amber-500/10 dark:bg-amber-950/60 border-2 border-amber-500/40 flex items-center justify-center text-3xl font-black text-amber-600 dark:text-amber-400 font-mono shadow-xs">0</span>
                            <span id="digit2" class="w-13 h-16 sm:w-14 sm:h-16 rounded-2xl bg-amber-500/10 dark:bg-amber-950/60 border-2 border-amber-500/40 flex items-center justify-center text-3xl font-black text-amber-600 dark:text-amber-400 font-mono shadow-xs">0</span>
                            <span id="digit3" class="w-13 h-16 sm:w-14 sm:h-16 rounded-2xl bg-amber-500/10 dark:bg-amber-950/60 border-2 border-amber-500/40 flex items-center justify-center text-3xl font-black text-amber-600 dark:text-amber-400 font-mono shadow-xs">0</span>
                        </div>

                        {{-- Real-time Countdown --}}
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-[11px] font-semibold text-slate-600 dark:text-slate-300">
                            <i data-lucide="clock" class="w-3.5 h-3.5 text-amber-500"></i>
                            <span>Berlaku: <strong id="deviceCodeCountdownText">30m 00s</strong></span>
                        </div>
                    </div>

                    {{-- Copy Button --}}
                    <button
                        type="button"
                        id="btnCopyDeviceCode"
                        class="w-full py-2.5 px-3 rounded-xl border border-amber-300 dark:border-amber-800/80 bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 dark:hover:bg-amber-900/40 text-amber-700 dark:text-amber-300 text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer shadow-2xs"
                    >
                        <i data-lucide="copy" class="w-4 h-4"></i>
                        <span id="copyBtnText">Salin Kode 4 Digit</span>
                    </button>

                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800 text-[11px] text-slate-500 dark:text-slate-400 space-y-1">
                        <p class="leading-relaxed">
                            💡 <b>Petunjuk untuk Kasir:</b> Masukkan 4 digit ini pada layar aplikasi POS Mobile di HP kasir saat login atau berganti perangkat.
                        </p>
                        <p class="leading-relaxed text-[10px] text-slate-400">
                            Kode bersifat sekali pakai (*single-use*) dan otomatis kedaluwarsa setelah 30 menit.
                        </p>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button
                        type="button"
                        data-close-device-code-modal
                        class="py-2.5 px-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold text-xs hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors cursor-pointer"
                    >
                        Tutup
                    </button>
                    <button
                        type="button"
                        id="btnGenerateDeviceCodeAction"
                        class="py-2.5 px-4 rounded-xl bg-amber-600 hover:bg-amber-700 active:bg-amber-800 text-white font-bold text-xs shadow-md shadow-amber-500/25 transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                    >
                        <i data-lucide="key-round" class="w-4 h-4"></i>
                        <span id="generateBtnText">Buat Kode 4 Digit</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
