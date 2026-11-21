<x-layouts::app :title="'Pengaturan Nomor Meja'">
    <main id="mainContent" data-tables-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto" x-data="tablesManager()">

        {{-- ==================== HEADER ==================== --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-slate-800">
            <div>
                {{-- Breadcrumb --}}
                <nav class="flex items-center gap-1.5 text-xs text-slate-400 dark:text-slate-500 mb-1" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition-colors">Dashboard</a>
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    <span class="text-slate-400 dark:text-slate-500">Bisnis</span>
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    <span class="text-slate-700 dark:text-slate-300 font-semibold" aria-current="page">Nomor Meja</span>
                </nav>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    Pengaturan Nomor Meja
                </h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Kelola master meja per cabang dan atur ketersediaan opsi nomor meja saat transaksi di kasir (POS).
                </p>
            </div>

            {{-- Action Buttons --}}
            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <button
                    type="button"
                    @click="openBulkModal()"
                    class="py-2.5 px-3.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold text-xs shadow-xs transition-colors flex items-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    <i data-lucide="layers" class="w-4 h-4 text-indigo-500"></i>
                    <span>Tambah Banyak (Bulk)</span>
                </button>
                <button
                    type="button"
                    @click="openCreateModal()"
                    class="py-2.5 px-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs shadow-sm shadow-indigo-600/20 transition-colors flex items-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                >
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Tambah Meja</span>
                </button>
            </div>
        </div>

        {{-- ==================== FLASH / VALIDATION FEEDBACK ==================== --}}
        @if(session('status'))
            <div class="rounded-2xl border border-emerald-200/80 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/20 p-4 flex items-start gap-3" role="status">
                <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400"></i>
                <p class="text-sm text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-rose-200/80 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/20 p-4 space-y-1" role="alert">
                <div class="flex items-start gap-3">
                    <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 text-rose-600 dark:text-rose-400"></i>
                    <p class="text-sm font-semibold text-rose-800 dark:text-rose-200">Periksa kembali data Anda:</p>
                </div>
                <ul class="pl-8 list-disc text-xs text-rose-700 dark:text-rose-300 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ==================== TOGGLE POS BANNER CARD ==================== --}}
        <div class="rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-xs">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-800/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 mt-0.5">
                        <i data-lucide="toggle-left" class="w-5 h-5"></i>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                                Tampilkan Pilihan Nomor Meja di Kasir (POS)
                            </h2>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $tableManagementEnabled ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $tableManagementEnabled ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                {{ $tableManagementEnabled ? 'Aktif di POS' : 'Nonaktif di POS' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-3xl leading-relaxed">
                            Jika diaktifkan, kolom nomor meja akan muncul di layar kasir (POS) untuk pesanan makan di tempat (*Dine-In*). Nonaktifkan sakelar ini jika bisnis Anda berupa retail, butik, jasa, atau takeaway yang tidak memerlukan nomor meja.
                        </p>
                    </div>
                </div>

                {{-- Toggle Form --}}
                <form method="POST" action="{{ route('tables.toggle') }}" class="shrink-0 flex items-center">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="table_management_enabled" value="{{ $tableManagementEnabled ? '0' : '1' }}">
                    <button
                        type="submit"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 {{ $tableManagementEnabled ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60 hover:bg-rose-100' : 'bg-emerald-600 text-white hover:bg-emerald-700 shadow-sm shadow-emerald-600/20' }}"
                    >
                        <i data-lucide="{{ $tableManagementEnabled ? 'power-off' : 'check' }}" class="w-4 h-4"></i>
                        <span>{{ $tableManagementEnabled ? 'Nonaktifkan di POS' : 'Aktifkan di POS' }}</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- ==================== SUMMARY METRICS ==================== --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-2xs">
                <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs mb-2">
                    <span class="font-medium">Total Meja</span>
                    <i data-lucide="layout-grid" class="w-4 h-4 text-indigo-500"></i>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white">
                    {{ $summary['total_tables'] }}
                </div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                    Semua meja terdaftar
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-2xs">
                <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs mb-2">
                    <span class="font-medium">Meja Semua Cabang</span>
                    <i data-lucide="globe" class="w-4 h-4 text-blue-500"></i>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white">
                    {{ $summary['global_tables'] }}
                </div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                    Berlaku di seluruh outlet
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-2xs">
                <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs mb-2">
                    <span class="font-medium">Meja Khusus Cabang</span>
                    <i data-lucide="store" class="w-4 h-4 text-amber-500"></i>
                </div>
                <div class="text-2xl font-black text-slate-900 dark:text-white">
                    {{ $summary['outlet_tables'] }}
                </div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                    Terspesifikasi per outlet
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-2xs">
                <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs mb-2">
                    <span class="font-medium">Status di POS</span>
                    <i data-lucide="monitor" class="w-4 h-4 text-emerald-500"></i>
                </div>
                <div class="text-lg font-black {{ $tableManagementEnabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400' }}">
                    {{ $tableManagementEnabled ? 'Aktif' : 'Nonaktif' }}
                </div>
                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
                    {{ $tableManagementEnabled ? 'Input meja tampil di kasir' : 'Input meja disembunyikan' }}
                </div>
            </div>
        </div>

        {{-- ==================== FILTER BAR ==================== --}}
        <div class="rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-2xs">
            <form method="GET" action="{{ route('tables.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                {{-- Search --}}
                <div class="space-y-1">
                    <label for="filterQ" class="text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                        Cari Nama / Nomor Meja
                    </label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i data-lucide="search" class="w-4 h-4"></i>
                        </span>
                        <input
                            type="text"
                            id="filterQ"
                            name="q"
                            value="{{ $filters['q'] ?? '' }}"
                            placeholder="mis. Meja 01, VIP..."
                            class="w-full h-9 pl-9 pr-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                    </div>
                </div>

                {{-- Outlet Filter --}}
                <div class="space-y-1">
                    <label for="filterOutlet" class="text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                        Filter Cabang / Outlet
                    </label>
                    <select
                        id="filterOutlet"
                        name="outlet_id"
                        class="w-full h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">Semua Cabang (Termasuk Global)</option>
                        <option value="global" @selected(($filters['outlet_id'] ?? '') === 'global')>Hanya Meja Global (Semua Cabang)</option>
                        @foreach($outlets as $o)
                            <option value="{{ $o->id }}" @selected(($filters['outlet_id'] ?? '') == (string)$o->id)>
                                {{ $o->name }} {{ $o->is_primary ? '(Utama)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Status Filter --}}
                <div class="space-y-1">
                    <label for="filterStatus" class="text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                        Status Meja
                    </label>
                    <select
                        id="filterStatus"
                        name="status"
                        class="w-full h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">Semua Status</option>
                        <option value="available" @selected(($filters['status'] ?? '') === 'available')>Tersedia</option>
                        <option value="occupied" @selected(($filters['status'] ?? '') === 'occupied')>Terisi</option>
                        <option value="reserved" @selected(($filters['status'] ?? '') === 'reserved')>Reservasi</option>
                    </select>
                </div>

                {{-- Buttons --}}
                <div class="flex items-center gap-2">
                    <button
                        type="submit"
                        class="flex-1 h-9 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs flex items-center justify-center gap-1.5 transition-colors shadow-xs"
                    >
                        <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                        <span>Terapkan</span>
                    </button>
                    <a
                        href="{{ route('tables.index') }}"
                        class="h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold text-xs flex items-center justify-center transition-colors"
                        title="Reset Filter"
                    >
                        <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </form>
        </div>

        {{-- ==================== TABLE LIST ==================== --}}
        @if($tables->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-200 dark:border-slate-800 bg-white/50 dark:bg-slate-900/50 p-12 text-center space-y-4">
                <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-800/60 text-indigo-500 dark:text-indigo-400 mx-auto flex items-center justify-center">
                    <i data-lucide="layout-grid" class="w-8 h-8"></i>
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">
                        Belum Ada Nomor Meja
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                        @if(!empty($filters['q']) || !empty($filters['outlet_id']) || !empty($filters['status']))
                            Tidak ditemukan meja yang sesuai dengan filter yang dipilih. Silakan atur ulang filter pencarian.
                        @else
                            Mulai tambahkan daftar nomor meja untuk memudahkan kasir saat melayani pesanan *dine-in*.
                        @endif
                    </p>
                </div>
                <div class="flex items-center justify-center gap-3 pt-2">
                    <button
                        type="button"
                        @click="openBulkModal()"
                        class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors shadow-2xs"
                    >
                        Tambah Banyak Sekaligus
                    </button>
                    <button
                        type="button"
                        @click="openCreateModal()"
                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition-colors"
                    >
                        Tambah Meja Satuan
                    </button>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach($tables as $table)
                    <div class="rounded-2xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-2xs hover:border-indigo-200 dark:hover:border-indigo-800 hover:shadow-xs transition-all flex flex-col justify-between group">
                        <div class="space-y-2.5">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-800/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                                        <i data-lucide="utensils" class="w-4 h-4"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="text-sm font-bold text-slate-900 dark:text-white truncate">
                                            {{ $table->name }}
                                        </h3>
                                        <p class="text-[11px] text-slate-400 dark:text-slate-500 truncate">
                                            {{ $table->outlet ? $table->outlet->name : 'Semua Cabang' }}
                                        </p>
                                    </div>
                                </div>

                                {{-- Status Badge --}}
                                @php
                                    $statusClasses = match($table->status) {
                                        'occupied' => 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
                                        'reserved' => 'bg-purple-50 dark:bg-purple-950/50 text-purple-700 dark:text-purple-300 border-purple-200 dark:border-purple-800',
                                        default => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                                    };
                                    $statusLabel = match($table->status) {
                                        'occupied' => 'Terisi',
                                        'reserved' => 'Reservasi',
                                        default => 'Tersedia',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold border {{ $statusClasses }} shrink-0">
                                    {{ $statusLabel }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 pt-1 border-t border-slate-100 dark:border-slate-800/60">
                                <span class="flex items-center gap-1">
                                    <i data-lucide="users" class="w-3.5 h-3.5 text-slate-400"></i>
                                    <span>{{ $table->capacity ?? 4 }} Kursi</span>
                                </span>
                                @if($table->outlet)
                                    <span class="inline-flex items-center gap-1 text-[11px] text-slate-500">
                                        <i data-lucide="map-pin" class="w-3 h-3 text-slate-400"></i>
                                        <span class="truncate max-w-[100px]">{{ $table->outlet->name }}</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[11px] text-indigo-600 dark:text-indigo-400 font-medium">
                                        <i data-lucide="globe" class="w-3 h-3"></i>
                                        <span>Global</span>
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="flex items-center justify-end gap-1.5 pt-3 mt-3 border-t border-slate-100 dark:border-slate-800/60">
                            <button
                                type="button"
                                @click='openEditModal(@js(["id" => $table->id, "name" => $table->name, "outlet_id" => $table->outlet_id, "capacity" => $table->capacity, "status" => $table->status]))'
                                class="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 transition-colors"
                                title="Edit Meja"
                            >
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                            </button>
                            <form method="POST" action="{{ route('tables.destroy', ['tableId' => $table->id]) }}" onsubmit="return confirm('Hapus nomor meja {{ addslashes($table->name) }}?')">
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition-colors"
                                    title="Hapus Meja"
                                >
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($tables->hasPages())
                <div class="pt-2">
                    {{ $tables->links() }}
                </div>
            @endif
        @endif

        {{-- ==================== MODAL TAMBAH SATUAN ==================== --}}
        <div
            x-show="showCreateModal"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div
                @click.away="showCreateModal = false"
                class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl overflow-hidden"
            >
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-800">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Tambah Nomor Meja</h2>
                    <button type="button" @click="showCreateModal = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('tables.store') }}" class="p-6 space-y-4">
                    @csrf
                    <div class="space-y-1">
                        <label for="createTableName" class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            Nama / Nomor Meja <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="createTableName"
                            name="name"
                            required
                            maxlength="100"
                            placeholder="mis. Meja 01, VIP 1, Outdoor A"
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                    </div>

                    <div class="space-y-1">
                        <label for="createTableOutlet" class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            Cabang / Outlet
                        </label>
                        <select
                            id="createTableOutlet"
                            name="outlet_id"
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="">Semua Cabang (Global)</option>
                            @foreach($outlets as $o)
                                <option value="{{ $o->id }}">{{ $o->name }} {{ $o->is_primary ? '(Utama)' : '' }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-slate-400">Pilih "Semua Cabang" jika nomor meja ini berlaku seragam di seluruh cabang.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label for="createTableCapacity" class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                Kapasitas Kursi
                            </label>
                            <input
                                type="number"
                                id="createTableCapacity"
                                name="capacity"
                                value="4"
                                min="1"
                                max="100"
                                class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                        </div>
                        <div class="space-y-1">
                            <label for="createTableStatus" class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                Status Awal
                            </label>
                            <select
                                id="createTableStatus"
                                name="status"
                                class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="available">Tersedia</option>
                                <option value="occupied">Terisi</option>
                                <option value="reserved">Reservasi</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            @click="showCreateModal = false"
                            class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs"
                        >
                            Simpan Meja
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ==================== MODAL TAMBAH BANYAK (BULK) ==================== --}}
        <div
            x-show="showBulkModal"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div
                @click.away="showBulkModal = false"
                class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl overflow-hidden"
            >
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-800">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Tambah Meja Sekaligus (Bulk)</h2>
                        <p class="text-xs text-slate-400">Buat urutan nomor meja secara otomatis (misal: Meja 1 s/d 15).</p>
                    </div>
                    <button type="button" @click="showBulkModal = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('tables.bulk') }}" class="p-6 space-y-4">
                    @csrf
                    <div class="space-y-1">
                        <label for="bulkPrefix" class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            Awalan Nama Meja (Prefix) <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="bulkPrefix"
                            name="prefix"
                            required
                            maxlength="50"
                            value="Meja"
                            placeholder="mis. Meja, Table, Outdoor"
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label for="bulkStart" class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                Mulai Nomor <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="number"
                                id="bulkStart"
                                name="start_number"
                                required
                                min="1"
                                max="999"
                                value="1"
                                class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                        </div>
                        <div class="space-y-1">
                            <label for="bulkEnd" class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                Sampai Nomor <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="number"
                                id="bulkEnd"
                                name="end_number"
                                required
                                min="1"
                                max="999"
                                value="10"
                                class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label for="bulkOutlet" class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            Cabang / Outlet
                        </label>
                        <select
                            id="bulkOutlet"
                            name="outlet_id"
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="">Semua Cabang (Global)</option>
                            @foreach($outlets as $o)
                                <option value="{{ $o->id }}">{{ $o->name }} {{ $o->is_primary ? '(Utama)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3 items-center">
                        <div class="space-y-1">
                            <label for="bulkCapacity" class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                Kapasitas Kursi Default
                            </label>
                            <input
                                type="number"
                                id="bulkCapacity"
                                name="capacity"
                                value="4"
                                min="1"
                                max="100"
                                class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                        </div>

                        <label class="inline-flex items-center gap-2 cursor-pointer pt-4 select-none">
                            <input type="checkbox" name="pad_zeros" value="1" checked class="custom-checkbox">
                            <span class="text-xs text-slate-700 dark:text-slate-300">Format 2 digit (01, 02...)</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            @click="showBulkModal = false"
                            class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs"
                        >
                            Buat Sekaligus
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ==================== MODAL EDIT MEJA ==================== --}}
        <div
            x-show="showEditModal"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
        >
            <div
                @click.away="showEditModal = false"
                class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl overflow-hidden"
            >
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-800">
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Edit Nomor Meja</h2>
                    <button type="button" @click="showEditModal = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <form :action="editActionUrl" method="POST" class="p-6 space-y-4">
                    @csrf
                    @method('PATCH')

                    <div class="space-y-1">
                        <label for="editTableName" class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            Nama / Nomor Meja <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="editTableName"
                            name="name"
                            x-model="editTable.name"
                            required
                            maxlength="100"
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                    </div>

                    <div class="space-y-1">
                        <label for="editTableOutlet" class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                            Cabang / Outlet
                        </label>
                        <select
                            id="editTableOutlet"
                            name="outlet_id"
                            x-model="editTable.outlet_id"
                            class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="">Semua Cabang (Global)</option>
                            @foreach($outlets as $o)
                                <option value="{{ $o->id }}">{{ $o->name }} {{ $o->is_primary ? '(Utama)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label for="editTableCapacity" class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                Kapasitas Kursi
                            </label>
                            <input
                                type="number"
                                id="editTableCapacity"
                                name="capacity"
                                x-model="editTable.capacity"
                                min="1"
                                max="100"
                                class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                        </div>
                        <div class="space-y-1">
                            <label for="editTableStatus" class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                                Status Meja
                            </label>
                            <select
                                id="editTableStatus"
                                name="status"
                                x-model="editTable.status"
                                class="w-full h-10 px-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="available">Tersedia</option>
                                <option value="occupied">Terisi</option>
                                <option value="reserved">Reservasi</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            @click="showEditModal = false"
                            class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs"
                        >
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </main>

    <script>
        function tablesManager() {
            return {
                showCreateModal: false,
                showBulkModal: false,
                showEditModal: false,
                editTable: {
                    id: null,
                    name: '',
                    outlet_id: '',
                    capacity: 4,
                    status: 'available'
                },
                get editActionUrl() {
                    return '{{ url("tables") }}/' + (this.editTable.id || '');
                },
                openCreateModal() {
                    this.showCreateModal = true;
                },
                openBulkModal() {
                    this.showBulkModal = true;
                },
                openEditModal(table) {
                    this.editTable = {
                        id: table.id,
                        name: table.name || '',
                        outlet_id: table.outlet_id ? String(table.outlet_id) : '',
                        capacity: table.capacity || 4,
                        status: table.status || 'available'
                    };
                    this.showEditModal = true;
                }
            };
        }
    </script>
</x-layouts::app>
