{{-- PAIR-01 — modal pembuatan kode pairing kasir (owner only, cloud plan). --}}
@props([
    'outlets' => [],
])

<div
    id="pairingModal"
    class="fixed inset-0 z-[90] hidden"
    role="dialog"
    aria-modal="true"
    aria-labelledby="pairingModalTitle"
    tabindex="-1"
    data-restore-mode="{{ $errors->has('pairing_code') ? 'create' : '' }}"
>
    <div class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/70 backdrop-blur-xs" data-close-pairing-modal></div>

    <div class="fixed inset-0 flex items-start sm:items-center justify-center p-3 sm:p-4 overflow-y-auto">
        <div class="w-full max-w-md my-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-2xl">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-200/80 dark:border-slate-800">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Kode Verifikasi</span>
                    <h2 id="pairingModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">Pasangkan Perangkat Kasir</h2>
                </div>
                <button
                    type="button"
                    data-close-pairing-modal
                    class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    aria-label="Tutup form kode pairing"
                >
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('devices.pairing-code') }}" class="p-5 space-y-4">
                @csrf

                <div>
                    <label for="pairingOutlets" class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Outlet perangkat kasir</label>
                    <select
                        id="pairingOutlets"
                        name="outlet_id"
                        required
                        class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">Pilih outlet</option>
                        @foreach($outlets as $outlet)
                            <option value="{{ $outlet['id'] }}" @selected((string) old('outlet_id') === (string) $outlet['id'])>{{ $outlet['name'] }}</option>
                        @endforeach
                    </select>
                    @error('outlet_id')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    @error('pairing_code')<p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                </div>

                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40 p-3 space-y-1.5">
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        Sistem membuat <span class="font-semibold">kode 4 digit</span> yang berlaku
                        <span class="font-semibold">{{ \App\Models\DevicePairingCode::TTL_MINUTES }} menit</span>
                        dan hanya bisa dipakai <span class="font-semibold">satu kali</span>.
                    </p>
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        Kode hanya untuk role <span class="font-semibold">kasir</span>. Perangkat owner tidak
                        perlu pairing — owner cukup memakai akunnya di aplikasi.
                    </p>
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        Saat kasir menginput kode, server mencatat nama perangkat, IP, user agent, dan versi
                        aplikasi sebagai bukti keamanan.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row sm:justify-end gap-2 pt-1">
                    <button
                        type="button"
                        data-close-pairing-modal
                        class="inline-flex items-center justify-center h-10 px-4 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    >
                        <i data-lucide="key-round" class="w-4 h-4"></i>
                        <span>Buat Kode</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
