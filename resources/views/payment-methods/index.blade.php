<x-layouts::app :title="'Metode Pembayaran'">
    <main
        id="mainContent"
        data-payment-methods-page="true"
        class="p-4 md:p-6 lg:p-8 space-y-6 max-w-6xl mx-auto"
    >
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-slate-800">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                    Bisnis &bull; Master Data
                </p>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    Metode Pembayaran
                </h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Kelola daftar metode pembayaran (Tunai, QRIS, Transfer, EDC, e-Wallet, Online Food) lalu centang metode mana saja yang aktif pada masing-masing cabang.
                </p>
            </div>
            <button
                type="button"
                id="openCreatePaymentMethodBtn"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs shadow-sm transition-colors cursor-pointer shrink-0"
            >
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Tambah Metode Pembayaran</span>
            </button>
        </div>

        {{-- Alerts --}}
        @if(session('status'))
            <div role="status" class="rounded-2xl border border-emerald-200/80 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/20 p-4 flex items-start gap-3">
                <i data-lucide="check-circle-2" class="w-5 h-5 shrink-0 text-emerald-600 dark:text-emerald-400"></i>
                <p class="text-sm text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div role="alert" class="rounded-2xl border border-rose-200/80 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/20 p-4 space-y-1">
                <ul class="list-disc pl-5 text-xs text-rose-700 dark:text-rose-300 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Info Banner --}}
        <div class="rounded-2xl border border-indigo-200/70 dark:border-indigo-900/50 bg-indigo-50/60 dark:bg-indigo-950/20 p-4 flex items-start gap-3 text-xs text-indigo-900 dark:text-indigo-200">
            <i data-lucide="info" class="w-4 h-4 text-indigo-600 dark:text-indigo-400 shrink-0 mt-0.5"></i>
            <p class="leading-relaxed">
                <strong>Aktivasi Per Cabang:</strong> Cukup centang atau hilangkan centang pada kolom cabang di bawah ini — perubahan langsung tersimpan otomatis dan menentukan metode pembayaran yang tampil di <strong>Kasir Web POS</strong> maupun <strong>POS Mobile Android</strong> cabang tersebut.
            </p>
        </div>

        {{-- Master Table + Per-Outlet Matrix --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th scope="col" class="py-3.5 px-4">Metode Pembayaran</th>
                            <th scope="col" class="py-3.5 px-4">Kategori & Arus Kas</th>
                            <th scope="col" class="py-3.5 px-4 text-center">Status Global</th>
                            @foreach($outlets as $outlet)
                                <th scope="col" class="py-3.5 px-3 text-center whitespace-nowrap">
                                    <span class="block text-slate-700 dark:text-slate-200">{{ $outlet->name }}</span>
                                    <span class="text-[10px] font-mono text-slate-400">{{ $outlet->code }}</span>
                                </th>
                            @endforeach
                            <th scope="col" class="py-3.5 px-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        @foreach($methods as $method)
                            @php
                                $enabledOutletsForMethod = [];
                                foreach ($outlets as $o) {
                                    if ($matrix[$method->id][$o->id] ?? true) {
                                        $enabledOutletsForMethod[] = (int) $o->id;
                                    }
                                }
                                $editPayload = [
                                    'id' => $method->id,
                                    'name' => $method->name,
                                    'code' => $method->code,
                                    'category' => $method->category,
                                    'is_cash' => (bool) $method->is_cash,
                                    'is_active' => (bool) $method->is_active,
                                    'description' => $method->description,
                                    'sort_order' => (int) $method->sort_order,
                                    'enabled_outlet_ids' => $enabledOutletsForMethod,
                                    'updateUrl' => route('payment-methods.update', $method),
                                ];
                            @endphp
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                                {{-- Nama & Kode --}}
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-xl {{ $method->is_cash ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' : 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400' }} flex items-center justify-center shrink-0">
                                            <i data-lucide="{{ $method->is_cash ? 'banknote' : ($method->category === 'qris' ? 'qr-code' : ($method->category === 'card' ? 'credit-card' : 'wallet')) }}" class="w-4 h-4"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                                <span>{{ $method->name }}</span>
                                                @if($method->is_system)
                                                    <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-[10px] font-semibold text-slate-500">Bawaan</span>
                                                @endif
                                            </p>
                                            <p class="text-[11px] text-slate-400 dark:text-slate-500">
                                                <span class="font-mono">{{ $method->code }}</span>
                                                @if($method->description)
                                                    &bull; {{ $method->description }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                {{-- Kategori & Arus Kas --}}
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                        {{ $method->categoryLabel() }}
                                    </span>
                                    <span class="block text-[10px] mt-1 font-semibold {{ $method->is_cash ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 dark:text-slate-500' }}">
                                        {{ $method->is_cash ? 'Masuk Laci Kas Tunai' : 'Non-Tunai (Tidak masuk laci)' }}
                                    </span>
                                </td>

                                {{-- Status Global --}}
                                <td class="py-3.5 px-4 text-center">
                                    @if($method->is_active)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/60 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Aktif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                                            Nonaktif
                                        </span>
                                    @endif
                                </td>

                                {{-- Per-Outlet Checkboxes --}}
                                @foreach($outlets as $outlet)
                                    @php
                                        $checked = $matrix[$method->id][$outlet->id] ?? true;
                                    @endphp
                                    <td class="py-3.5 px-3 text-center">
                                        <label class="inline-flex items-center justify-center p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer" title="Aktifkan {{ $method->name }} di {{ $outlet->name }}">
                                            <input
                                                type="checkbox"
                                                @checked($checked)
                                                data-outlet-toggle="true"
                                                data-method-id="{{ $method->id }}"
                                                data-outlet-id="{{ $outlet->id }}"
                                                class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                            >
                                        </label>
                                    </td>
                                @endforeach

                                {{-- Aksi (Icon Only) --}}
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            data-edit-payment-method="{{ json_encode($editPayload) }}"
                                            class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-indigo-50 dark:hover:bg-indigo-950/50 hover:border-indigo-300 dark:hover:border-indigo-700 text-slate-600 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors cursor-pointer"
                                            title="Edit {{ $method->name }}"
                                            aria-label="Edit {{ $method->name }}"
                                        >
                                            <i data-lucide="pencil" class="w-4 h-4"></i>
                                        </button>
                                        @if(!($method->is_system && $method->code === 'cash'))
                                            <button
                                                type="button"
                                                data-delete-payment-method="true"
                                                data-delete-url="{{ route('payment-methods.destroy', $method) }}"
                                                data-delete-name="{{ $method->name }}"
                                                class="p-2 rounded-xl border border-rose-200 dark:border-rose-800/60 bg-rose-50/70 dark:bg-rose-950/30 hover:bg-rose-100 dark:hover:bg-rose-900/50 text-rose-600 dark:text-rose-400 transition-colors cursor-pointer"
                                                title="Hapus {{ $method->name }}"
                                                aria-label="Hapus {{ $method->name }}"
                                            >
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ==================== CREATE / EDIT MODAL ==================== --}}
        <div
            id="paymentMethodModal"
            class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            role="dialog"
            aria-modal="true"
            aria-labelledby="pmModalTitle"
        >
            <div
                id="paymentMethodDialog"
                class="w-full max-w-lg rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-2xl overflow-hidden"
            >
                <form id="paymentMethodForm" action="{{ route('payment-methods.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="pmFormMethod" value="POST">
                    <input type="hidden" name="outlet_selection_present" value="1">

                    <div class="px-5 py-4 border-b border-slate-200/80 dark:border-slate-800 flex items-center justify-between">
                        <h2 id="pmModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">
                            Tambah Metode Pembayaran
                        </h2>
                        <button
                            type="button"
                            id="closePmModalBtn"
                            class="p-1.5 rounded-xl text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
                            aria-label="Tutup modal"
                        >
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>

                    <div class="p-5 space-y-4 text-xs sm:text-sm max-h-[70vh] overflow-y-auto">
                        <div>
                            <label for="pmNameInput" class="block font-semibold text-slate-800 dark:text-slate-200 mb-1">
                                Nama Metode Pembayaran *
                            </label>
                            <input
                                type="text"
                                name="name"
                                id="pmNameInput"
                                required
                                maxlength="100"
                                placeholder="Contoh: QRIS BCA, GoPay, Transfer Mandiri, GoFood"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            >
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="pmCategorySelect" class="block font-semibold text-slate-800 dark:text-slate-200 mb-1">
                                    Kategori
                                </label>
                                <select
                                    name="category"
                                    id="pmCategorySelect"
                                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                >
                                    @foreach($categoryLabels as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input type="hidden" id="pmCategoryHidden" disabled name="category" value="cash">
                            </div>
                            <div>
                                <label for="pmSortOrderInput" class="block font-semibold text-slate-800 dark:text-slate-200 mb-1">
                                    Urutan Tampil
                                </label>
                                <input
                                    type="number"
                                    name="sort_order"
                                    id="pmSortOrderInput"
                                    min="0"
                                    value="{{ ($methods->max('sort_order') ?? 0) + 1 }}"
                                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                >
                            </div>
                        </div>

                        <div>
                            <label for="pmDescriptionInput" class="block font-semibold text-slate-800 dark:text-slate-200 mb-1">
                                Catatan / No. Rekening (Opsional)
                            </label>
                            <input
                                type="text"
                                name="description"
                                id="pmDescriptionInput"
                                maxlength="255"
                                placeholder="Contoh: BCA 1234567890 a.n. Temuan Space"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            >
                        </div>

                        <div class="flex flex-col gap-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/70 dark:border-slate-800">
                            <label class="flex items-center gap-2.5 cursor-pointer text-xs">
                                <input type="hidden" name="is_cash" value="0">
                                <input
                                    type="checkbox"
                                    name="is_cash"
                                    id="pmIsCashCheckbox"
                                    value="1"
                                    class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                >
                                <span class="font-semibold text-slate-700 dark:text-slate-200">Masuk laci kas tunai (dihitung pada rekonsiliasi uang fisik shift)</span>
                            </label>

                            <label id="pmIsActiveWrapper" class="hidden flex items-center gap-2.5 cursor-pointer text-xs">
                                <input type="hidden" name="is_active" id="pmIsActiveHidden" value="0" disabled>
                                <input
                                    type="checkbox"
                                    name="is_active"
                                    id="pmIsActiveCheckbox"
                                    value="1"
                                    disabled
                                    class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                >
                                <span class="font-semibold text-slate-700 dark:text-slate-200">Aktif secara global di seluruh bisnis</span>
                            </label>
                        </div>

                        {{-- Per-Outlet Checkboxes inside Modal --}}
                        @if($outlets->isNotEmpty())
                            <div class="space-y-2">
                                <label class="block font-semibold text-slate-800 dark:text-slate-200">Aktifkan di Cabang:</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach($outlets as $outlet)
                                        <label class="flex items-center gap-2.5 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/60 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer text-xs">
                                            <input
                                                type="checkbox"
                                                name="enabled_outlet_ids[]"
                                                value="{{ $outlet->id }}"
                                                class="pm-modal-outlet-checkbox w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                                checked
                                            >
                                            <span class="min-w-0 truncate">
                                                <span class="font-bold text-slate-800 dark:text-slate-100 block truncate">{{ $outlet->name }}</span>
                                                <span class="text-[10px] font-mono text-slate-400">{{ $outlet->code }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="px-5 py-4 border-t border-slate-200/80 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/60 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            id="cancelPmModalBtn"
                            class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold text-xs cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs shadow-sm cursor-pointer"
                        >
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ==================== DELETE CONFIRMATION MODAL ==================== --}}
        <div
            id="deletePaymentMethodModal"
            class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            role="dialog"
            aria-modal="true"
            aria-labelledby="deletePmModalTitle"
        >
            <div class="w-full max-w-md rounded-3xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-2xl overflow-hidden">
                <form id="deletePaymentMethodForm" method="POST" action="">
                    @csrf
                    @method('DELETE')

                    <div class="p-6 text-center space-y-3">
                        <div class="mx-auto w-12 h-12 rounded-2xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200/70 dark:border-rose-800/60 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                            <i data-lucide="trash-2" class="w-6 h-6"></i>
                        </div>
                        <h3 id="deletePmModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">
                            Hapus Metode Pembayaran?
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Anda yakin ingin menghapus metode pembayaran <strong id="deletePmNameDisplay" class="text-slate-900 dark:text-white"></strong>?
                            Transaksi lama yang sudah menggunakan metode ini tetap tersimpan aman di riwayat.
                        </p>
                    </div>

                    <div class="px-5 py-4 border-t border-slate-200/80 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-900/60 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            id="cancelDeletePmBtn"
                            class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs shadow-sm inline-flex items-center gap-1.5 cursor-pointer"
                        >
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            <span>Ya, Hapus</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        function initPaymentMethodsPage() {
            const root = document.querySelector('main[data-payment-methods-page="true"]');
            if (!root || root.dataset.pmInitialized === 'true') return;
            root.dataset.pmInitialized = 'true';

            const storeUrl = '{{ route('payment-methods.store') }}';
            const nextSortOrder = {{ ($methods->max('sort_order') ?? 0) + 1 }};
            const allOutletIds = @js($outlets->pluck('id')->map(fn($id) => (int) $id)->values()->all());

            // Create / Edit Modal refs
            const pmModal = document.getElementById('paymentMethodModal');
            const pmForm = document.getElementById('paymentMethodForm');
            const pmFormMethod = document.getElementById('pmFormMethod');
            const pmModalTitle = document.getElementById('pmModalTitle');
            const pmNameInput = document.getElementById('pmNameInput');
            const pmCategorySelect = document.getElementById('pmCategorySelect');
            const pmCategoryHidden = document.getElementById('pmCategoryHidden');
            const pmSortOrderInput = document.getElementById('pmSortOrderInput');
            const pmDescriptionInput = document.getElementById('pmDescriptionInput');
            const pmIsCashCheckbox = document.getElementById('pmIsCashCheckbox');
            const pmIsActiveWrapper = document.getElementById('pmIsActiveWrapper');
            const pmIsActiveHidden = document.getElementById('pmIsActiveHidden');
            const pmIsActiveCheckbox = document.getElementById('pmIsActiveCheckbox');
            const outletCheckboxes = Array.from(document.querySelectorAll('.pm-modal-outlet-checkbox'));

            function openPmModal() {
                pmModal.classList.remove('hidden');
                pmModal.classList.add('flex');
                document.body.style.overflow = 'hidden';
                setTimeout(() => pmNameInput?.focus(), 50);
            }

            function closePmModal() {
                pmModal.classList.add('hidden');
                pmModal.classList.remove('flex');
                document.body.style.overflow = '';
            }

            function syncCashCategoryState(code = '') {
                const isSystemCash = code === 'cash';
                const isCashCat = pmCategorySelect.value === 'cash';
                pmCategorySelect.disabled = isSystemCash;
                pmCategoryHidden.disabled = !isSystemCash;
                if (isSystemCash || isCashCat) {
                    pmIsCashCheckbox.checked = true;
                    pmIsCashCheckbox.disabled = true;
                } else {
                    pmIsCashCheckbox.disabled = false;
                }
            }

            document.getElementById('openCreatePaymentMethodBtn')?.addEventListener('click', () => {
                pmModalTitle.textContent = 'Tambah Metode Pembayaran';
                pmForm.action = storeUrl;
                pmFormMethod.value = 'POST';
                pmNameInput.value = '';
                pmCategorySelect.value = 'ewallet';
                pmSortOrderInput.value = nextSortOrder;
                pmDescriptionInput.value = '';
                pmIsCashCheckbox.checked = false;
                pmIsActiveWrapper.classList.add('hidden');
                pmIsActiveHidden.disabled = true;
                pmIsActiveCheckbox.disabled = true;
                outletCheckboxes.forEach((cb) => {
                    cb.checked = allOutletIds.includes(Number(cb.value));
                });
                syncCashCategoryState('');
                openPmModal();
            });

            document.querySelectorAll('[data-edit-payment-method]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    try {
                        const item = JSON.parse(btn.getAttribute('data-edit-payment-method') || '{}');
                        pmModalTitle.textContent = 'Edit Metode Pembayaran';
                        pmForm.action = item.updateUrl;
                        pmFormMethod.value = 'PATCH';
                        pmNameInput.value = item.name || '';
                        pmCategorySelect.value = item.category || 'other';
                        pmSortOrderInput.value = item.sort_order ?? 0;
                        pmDescriptionInput.value = item.description || '';
                        pmIsCashCheckbox.checked = Boolean(item.is_cash);
                        pmIsActiveWrapper.classList.remove('hidden');
                        pmIsActiveHidden.disabled = false;
                        pmIsActiveCheckbox.disabled = false;
                        pmIsActiveCheckbox.checked = Boolean(item.is_active);
                        const enabledSet = new Set((item.enabled_outlet_ids || []).map(Number));
                        outletCheckboxes.forEach((cb) => {
                            cb.checked = enabledSet.has(Number(cb.value));
                        });
                        syncCashCategoryState(item.code || '');
                        openPmModal();
                    } catch (err) {
                        console.error('Failed to open edit modal:', err);
                    }
                });
            });

            pmCategorySelect?.addEventListener('change', () => {
                if (pmCategorySelect.value === 'cash') {
                    pmIsCashCheckbox.checked = true;
                    pmIsCashCheckbox.disabled = true;
                } else {
                    pmIsCashCheckbox.disabled = false;
                }
            });

            document.getElementById('closePmModalBtn')?.addEventListener('click', closePmModal);
            document.getElementById('cancelPmModalBtn')?.addEventListener('click', closePmModal);
            pmModal?.addEventListener('click', (e) => {
                if (e.target === pmModal) closePmModal();
            });

            // Delete Confirmation Modal refs
            const deleteModal = document.getElementById('deletePaymentMethodModal');
            const deleteForm = document.getElementById('deletePaymentMethodForm');
            const deleteNameDisplay = document.getElementById('deletePmNameDisplay');

            function openDeleteModal(url, name) {
                deleteForm.action = url;
                deleteNameDisplay.textContent = `"${name}"`;
                deleteModal.classList.remove('hidden');
                deleteModal.classList.add('flex');
                document.body.style.overflow = 'hidden';
            }

            function closeDeleteModal() {
                deleteModal.classList.add('hidden');
                deleteModal.classList.remove('flex');
                document.body.style.overflow = '';
            }

            document.querySelectorAll('[data-delete-payment-method="true"]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    openDeleteModal(
                        btn.getAttribute('data-delete-url') || '',
                        btn.getAttribute('data-delete-name') || ''
                    );
                });
            });

            document.getElementById('cancelDeletePmBtn')?.addEventListener('click', closeDeleteModal);
            deleteModal?.addEventListener('click', (e) => {
                if (e.target === deleteModal) closeDeleteModal();
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    if (pmModal && !pmModal.classList.contains('hidden')) closePmModal();
                    if (deleteModal && !deleteModal.classList.contains('hidden')) closeDeleteModal();
                }
            });

            // Per-outlet instant checkbox toggle
            document.querySelectorAll('[data-outlet-toggle="true"]').forEach((checkbox) => {
                checkbox.addEventListener('change', async () => {
                    const methodId = checkbox.getAttribute('data-method-id');
                    const outletId = checkbox.getAttribute('data-outlet-id');
                    const isEnabled = checkbox.checked;
                    checkbox.disabled = true;

                    try {
                        const res = await fetch(`/payment-methods/${methodId}/outlets/${outletId}/toggle`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({ is_enabled: isEnabled }),
                        });
                        const data = await res.json();
                        if (!res.ok || !data.success) {
                            throw new Error(data.message || 'Gagal memperbarui status cabang.');
                        }
                        if (typeof showToast === 'function') {
                            showToast('success', data.message);
                        }
                    } catch (err) {
                        checkbox.checked = !isEnabled;
                        if (typeof showToast === 'function') {
                            showToast('error', err.message || 'Gagal menyimpan perubahan.');
                        }
                    } finally {
                        checkbox.disabled = false;
                    }
                });
            });

            if (typeof window.initIcons === 'function') {
                window.initIcons();
            }
        }

        initPaymentMethodsPage();
        if (!window.__pmPageListenersBound) {
            window.__pmPageListenersBound = true;
            document.addEventListener('DOMContentLoaded', initPaymentMethodsPage);
            document.addEventListener('livewire:navigated', initPaymentMethodsPage);
        }
    </script>
</x-layouts::app>
