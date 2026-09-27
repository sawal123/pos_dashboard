@props(['currentType', 'hasType', 'typeOptions'])

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

    {{-- Confirmation is only required when changing an existing type. --}}
    @if($hasType)
        <label class="flex items-start gap-3 rounded-xl border border-amber-200/80 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/20 p-4 cursor-pointer">
            <input
                type="checkbox"
                name="confirm_change"
                value="1"
                @checked(old('confirm_change'))
                class="mt-0.5 w-4 h-4 text-amber-600 border-amber-300 dark:border-amber-700 focus:ring-amber-500"
            >
            <span class="text-xs text-amber-800 dark:text-amber-200 leading-relaxed">
                Saya memahami mengganti tipe bisnis hanya menyesuaikan tampilan menu dan tidak menghapus produk,
                transaksi, stok, data laundry, atau langganan.
            </span>
        </label>
        @error('confirm_change')
            <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror
    @endif

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
