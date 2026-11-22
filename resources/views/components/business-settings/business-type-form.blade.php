@props(['currentType', 'hasType', 'typeOptions', 'currentTypeLabel' => null])

@if($hasType)
    <section
        id="businessTypeLockedCard"
        class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-5 space-y-4"
    >
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                    <i data-lucide="lock" class="w-4 h-4 text-emerald-600 dark:text-emerald-400"></i>
                    <span>Tipe Bisnis Terkunci</span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Tipe bisnis sudah ditetapkan sejak awal dan dikunci permanen agar data produk, resep, stok, dan transaksi tetap aman.
                </p>
            </div>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-[11px] font-bold self-start sm:self-auto shrink-0">
                <i data-lucide="shield-check" class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400"></i>
                <span>Terkunci Permanen</span>
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            @foreach($typeOptions as $option)
                @php
                    $isSelected = $currentType === $option['value'];
                @endphp
                <div
                    class="relative flex flex-col gap-1.5 rounded-2xl border p-4 select-none {{ $isSelected
                        ? 'border-indigo-300 dark:border-indigo-700 bg-indigo-50/70 dark:bg-indigo-950/30 ring-1 ring-indigo-500/20'
                        : 'border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/40 opacity-55 cursor-not-allowed' }}"
                >
                    <span class="flex items-center justify-between gap-2">
                        <span class="flex items-center gap-2">
                            <input
                                type="radio"
                                name="business_type_locked"
                                value="{{ $option['value'] }}"
                                @checked($isSelected)
                                disabled
                                class="w-4 h-4 text-indigo-600 border-slate-300 dark:border-slate-600 disabled:opacity-80 cursor-not-allowed"
                            >
                            <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $option['label'] }}</span>
                        </span>
                        @if($isSelected)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-600 text-white text-[10px] font-bold">
                                <i data-lucide="lock" class="w-2.5 h-2.5"></i>
                                Aktif
                            </span>
                        @endif
                    </span>
                    <span class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">{{ $option['description'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="flex items-start gap-2.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/40 p-3.5 text-xs text-slate-600 dark:text-slate-300">
            <i data-lucide="info" class="w-4 h-4 text-indigo-500 shrink-0 mt-0.5"></i>
            <p class="leading-relaxed">
                Tipe bisnis aktif Anda adalah <strong class="text-slate-900 dark:text-white">{{ $currentTypeLabel ?? $currentType }}</strong>.
                Untuk mencegah kerusakan struktur data operasional, tipe bisnis tidak dapat diubah setelah ditetapkan. Jika Anda memiliki usaha dengan jenis berbeda, silakan buat bisnis baru melalui switcher bisnis.
            </p>
        </div>
    </section>
@else
    <form
        method="POST"
        action="{{ route('business-settings.business-type.update') }}"
        class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-5 space-y-4"
    >
        @csrf
        @method('PATCH')

        <fieldset class="space-y-3">
            <legend class="text-sm font-extrabold text-slate-900 dark:text-white">Pilih Tipe Bisnis</legend>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach($typeOptions as $option)
                    <label
                        class="relative flex flex-col gap-1.5 rounded-2xl border p-4 cursor-pointer transition-colors {{ $currentType === $option['value']
                            ? 'border-indigo-300 dark:border-indigo-800 bg-indigo-50/60 dark:bg-indigo-950/20'
                            : 'border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/40' }}"
                    >
                        <span class="flex items-center gap-2">
                            <input
                                type="radio"
                                name="business_type"
                                value="{{ $option['value'] }}"
                                @checked(old('business_type', $currentType) === $option['value'])
                                class="w-4 h-4 text-indigo-600 border-slate-300 dark:border-slate-600 focus:ring-indigo-500"
                            >
                            <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $option['label'] }}</span>
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">{{ $option['description'] }}</span>
                    </label>
                @endforeach
            </div>

            @error('business_type')
                <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </fieldset>

        <div class="flex items-start gap-2.5 rounded-xl border border-amber-200/80 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/20 p-3.5 text-xs text-amber-800 dark:text-amber-200">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5"></i>
            <p class="leading-relaxed">
                <strong>Perhatian:</strong> Setelah tipe bisnis dipilih dan disimpan, tipe bisnis akan <strong>dikunci permanen</strong> agar tidak merusak data transaksi, stok, dan menu operasional Anda.
            </p>
        </div>

        <div class="flex justify-end">
            <button
                type="submit"
                class="inline-flex items-center justify-center gap-2 h-10 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <i data-lucide="save" class="w-4 h-4"></i>
                Simpan Tipe Bisnis
            </button>
        </div>
    </form>
@endif
