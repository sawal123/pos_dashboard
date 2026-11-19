@props([
    'tableManagementEnabled' => false,
    'diningTables' => [],
    'outlets' => [],
])

<div class="rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 sm:p-6 shadow-xs space-y-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-800/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                <i data-lucide="layout-grid" class="w-5 h-5"></i>
            </div>
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">Pengaturan Nomor Meja (Dine-In)</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Aktifkan atau nonaktifkan fitur nomor meja di aplikasi kasir (POS).</p>
            </div>
        </div>
        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold {{ $tableManagementEnabled ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700' }}">
            {{ $tableManagementEnabled ? 'Aktif di POS' : 'Nonaktif di POS' }}
        </span>
    </div>

    {{-- Toggle On / Off Form --}}
    <form method="POST" action="{{ route('business-settings.tables.toggle') }}" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-700/60 space-y-3">
        @csrf
        @method('PATCH')

        <div class="flex items-start justify-between gap-4">
            <div class="space-y-0.5">
                <label for="table_management_toggle" class="text-xs font-bold text-slate-800 dark:text-slate-200 cursor-pointer">
                    Tampilkan Input Nomor Meja di Kasir (POS)
                </label>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 max-w-xl">
                    Jika diaktifkan, kasir dapat memilih atau mengetik nomor meja pada setiap pesanan dine-in. Matikan opsi ini jika bisnis Anda berupa retail, butik, atau takeaway yang tidak menggunakan meja.
                </p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                <input type="hidden" name="table_management_enabled" value="0">
                <input
                    type="checkbox"
                    id="table_management_toggle"
                    name="table_management_enabled"
                    value="1"
                    {{ $tableManagementEnabled ? 'checked' : '' }}
                    onchange="this.form.submit()"
                    class="sr-only peer"
                >
                <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
            </label>
        </div>
    </form>

    {{-- If table management enabled: Show Master Tables Management --}}
    @if($tableManagementEnabled)
        <div class="pt-2 border-t border-slate-200/70 dark:border-slate-800 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">
                        Daftar Meja Terdaftar
                    </h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        Meja yang terdaftar akan muncul sebagai pilihan cepat di kasir POS. Kasir juga tetap bisa mengetik nomor meja bebas.
                    </p>
                </div>
            </div>

            {{-- Form Tambah Meja --}}
            <form method="POST" action="{{ route('business-settings.tables.store') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-2.5 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-700/60 items-end">
                @csrf
                <div class="sm:col-span-2 space-y-1">
                    <label class="text-[11px] font-semibold text-slate-700 dark:text-slate-300" for="newTableName">
                        Nama / Nomor Meja <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="newTableName"
                        name="name"
                        placeholder="mis. Meja 01, VIP 1, Outdoor 2"
                        required
                        maxlength="100"
                        class="w-full h-9 px-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                </div>

                <div class="space-y-1">
                    <label class="text-[11px] font-semibold text-slate-700 dark:text-slate-300" for="newTableOutlet">
                        Cabang
                    </label>
                    <select
                        id="newTableOutlet"
                        name="outlet_id"
                        class="w-full h-9 px-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">Semua Cabang</option>
                        @foreach($outlets as $o)
                            <option value="{{ $o->id }}">{{ $o->name }} {{ $o->is_primary ? '(Utama)' : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <button
                        type="submit"
                        class="w-full h-9 px-3.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs flex items-center justify-center gap-1.5 transition-colors shadow-xs"
                    >
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                        <span>Tambah Meja</span>
                    </button>
                </div>
            </form>

            {{-- List of Tables --}}
            @if(count($diningTables) > 0)
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5">
                    @foreach($diningTables as $table)
                        <div class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-2xs group hover:border-indigo-200 dark:hover:border-indigo-800 transition-colors">
                            <div class="min-w-0 pr-2">
                                <div class="text-xs font-bold text-slate-900 dark:text-white truncate">
                                    {{ $table->name }}
                                </div>
                                <div class="text-[10px] text-slate-400 dark:text-slate-500 truncate">
                                    {{ $table->outlet ? $table->outlet->name : 'Semua Cabang' }}
                                </div>
                            </div>
                            <form method="POST" action="{{ route('business-settings.tables.destroy', ['tableId' => $table->id]) }}" onsubmit="return confirm('Hapus meja {{ $table->name }}?')">
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="p-1 rounded-md text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors"
                                    title="Hapus Meja"
                                >
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-4 rounded-xl border border-dashed border-slate-200 dark:border-slate-800 text-center text-xs text-slate-400 dark:text-slate-500">
                    Belum ada nomor meja yang didaftarkan. Anda dapat menambahkan meja di atas, atau kasir dapat langsung mengetik nomor meja saat transaksi di POS.
                </div>
            @endif
        </div>
    @endif
</div>
