<x-layouts::app :title="'Pengaturan Bisnis'">
    <main id="mainContent" data-business-settings-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-4xl mx-auto">
        <section class="space-y-1">
            <p class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                Bisnis
            </p>
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                Pengaturan Bisnis
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 max-w-2xl">
                Tetapkan tipe bisnis aktif. Tipe bisnis menentukan menu operasional yang relevan; hak akses
                tetap ditentukan oleh peran pengguna (RBAC), bukan oleh tipe bisnis.
            </p>
        </section>

        @if(session('status'))
            <div role="status" class="rounded-2xl border border-emerald-200/80 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/20 p-4 flex items-start gap-3">
                <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400"></i>
                <p class="text-sm text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div role="alert" class="rounded-2xl border border-rose-200/80 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/20 p-4 space-y-1">
                <div class="flex items-start gap-3">
                    <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 text-rose-600 dark:text-rose-400"></i>
                    <p class="text-sm font-semibold text-rose-800 dark:text-rose-200">Tipe bisnis tidak dapat disimpan.</p>
                </div>
                <ul class="pl-8 list-disc text-xs text-rose-700 dark:text-rose-300 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs p-5 space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        Tipe Bisnis Saat Ini
                    </p>
                    <p id="businessTypeCurrent" class="text-xl font-extrabold text-slate-900 dark:text-white">
                        {{ $currentTypeLabel }}
                    </p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Bisnis aktif:
                        <span id="businessTypeBusinessName" class="font-semibold text-slate-700 dark:text-slate-200">{{ $business->name }}</span>
                    </p>
                </div>
                <span
                    id="businessTypeStateBadge"
                    data-business-type-state="{{ $hasType ? 'set' : 'unset' }}"
                    class="inline-flex items-center px-2.5 py-1 rounded-md border text-[11px] font-bold self-start {{ $hasType
                        ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
                        : 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800' }}"
                >
                    {{ $hasType ? 'Sudah ditentukan' : 'Belum ditentukan' }}
                </span>
            </div>

            @unless($hasType)
                <p class="text-xs text-amber-800 dark:text-amber-200 rounded-xl border border-amber-200/80 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/20 p-3">
                    Tipe bisnis belum ditentukan. Selama belum dipilih, navigasi menampilkan menu umum yang aman dan
                    tidak menganggap bisnis ini sebagai Cafe. Data historis tetap utuh.
                </p>
            @endunless
        </section>

        <x-business-settings.business-type-form
            :currentType="$currentType"
            :hasType="$hasType"
            :typeOptions="$typeOptions"
        />

        <p class="text-xs text-slate-500 dark:text-slate-400">
            Mengubah tipe bisnis tidak menghapus produk, transaksi, stok, data laundry, atau langganan.
            Hanya relevansi tampilan menu yang menyesuaikan.
        </p>
    </main>
</x-layouts::app>
