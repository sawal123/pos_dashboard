@props([
    'maxDevices' => 5,
])

<div class="rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 sm:p-6 shadow-xs space-y-4">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-800/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                <i data-lucide="sliders" class="w-5 h-5"></i>
            </div>
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Batas Perangkat Terhubung (Cloud)</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Atur batas maksimal perangkat POS kasir yang diizinkan aktif secara bersamaan.</p>
            </div>
        </div>
        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
            Min. 5 Perangkat
        </span>
    </div>

    <form method="POST" action="{{ route('business-settings.device-limit.update') }}" class="space-y-4">
        @csrf
        @method('PATCH')

        <div class="max-w-xs space-y-1.5">
            <label for="max_devices" class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                Jumlah Maksimal Perangkat
            </label>
            <div class="relative">
                <input
                    type="number"
                    id="max_devices"
                    name="max_devices"
                    min="5"
                    max="500"
                    step="1"
                    value="{{ old('max_devices', $maxDevices) }}"
                    required
                    class="w-full h-10 px-3 pr-16 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-sm font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 tabular-nums"
                >
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-slate-400">
                    terminal
                </div>
            </div>
            <p class="text-[11px] text-slate-400 dark:text-slate-500">
                Batas default otomatis minimal 5 perangkat. Owner dapat menaikkan jumlah sesuai kebutuhan cabang dan kasir Anda.
            </p>
        </div>

        <button
            type="submit"
            class="py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs transition-colors shadow-xs"
        >
            Simpan Batas Perangkat
        </button>
    </form>
</div>
