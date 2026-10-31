<x-layouts::app :title="'Riwayat Stok'">
    <main id="mainContent" data-stock-history-page="true" class="p-4 md:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">

        {{-- ==================== HEADER ==================== --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200/80 dark:border-slate-800">
            <div>
                <nav class="flex items-center gap-1.5 text-xs text-slate-400 dark:text-slate-500 mb-1" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition-colors">Dashboard</a>
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    <a href="{{ route('stock.index') }}" class="hover:text-slate-700 dark:hover:text-slate-300 transition-colors">Stok</a>
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                    <span class="text-slate-700 dark:text-slate-300 font-semibold" aria-current="page">Riwayat</span>
                </nav>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    Riwayat Stok
                </h1>
                <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Setiap perubahan stok tercatat lengkap dengan stok sebelum dan sesudahnya.
                </p>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <x-stock.tabs active="history" />
                @if($canManageStock ?? false)
                    <button
                        type="button"
                        id="openAdjustmentBtn"
                        class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition-colors flex items-center gap-1.5"
                    >
                        <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
                        Penyesuaian
                    </button>
                    <button
                        type="button"
                        id="openReceiptBtn"
                        class="px-3.5 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors flex items-center gap-1.5"
                    >
                        <i data-lucide="package-plus" class="w-4 h-4"></i>
                        Penerimaan
                    </button>
                @endif
            </div>
        </div>

        {{-- ==================== FLASH ==================== --}}
        @if(session('status'))
            <div class="rounded-2xl border border-emerald-200/80 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/30 px-4 py-3 flex items-start gap-3" role="status">
                <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                <p class="text-sm text-emerald-800 dark:text-emerald-200">{{ session('status') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-2xl border border-rose-200/80 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/30 px-4 py-3" role="alert">
                <div class="flex items-start gap-3">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0"></i>
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-rose-800 dark:text-rose-200">Tindakan tidak dapat diproses.</p>
                        <ul class="pl-4 list-disc text-xs text-rose-700 dark:text-rose-300">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        {{-- ==================== SUMMARY ==================== --}}
        <x-stock.history-summary-cards :summary="$summary" />

        {{-- ==================== FILTER ==================== --}}
        <x-stock.history-filter-bar :products="$products" :users="$users" :filters="$currentFilters" />

        {{-- ==================== LIST ==================== --}}
        @if($items->total() > 0)
            <x-stock.history-table :items="$items" />
            <x-stock.history-mobile-cards :items="$items" />

            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 p-3 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs text-xs">
                <div class="text-slate-500 dark:text-slate-400 font-medium">
                    Menampilkan
                    <span class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ $items->firstItem() ?? 0 }}</span>
                    –
                    <span class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ $items->lastItem() ?? 0 }}</span>
                    dari
                    <span class="font-bold text-slate-800 dark:text-slate-200 tabular-nums">{{ $items->total() }}</span>
                    pergerakan
                </div>
                <nav class="flex items-center gap-1" aria-label="Navigasi Halaman Riwayat Stok">
                    @if($items->onFirstPage())
                        <span class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 font-medium cursor-not-allowed text-xs">Sebelumnya</span>
                    @else
                        <a href="{{ $items->previousPageUrl() }}" class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-medium text-xs hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors">Sebelumnya</a>
                    @endif

                    @foreach($items->getUrlRange(max(1, $items->currentPage() - 3), min($items->lastPage(), $items->currentPage() + 3)) as $page => $url)
                        @if($page === $items->currentPage())
                            <span class="w-8 h-8 rounded-xl bg-indigo-600 text-white font-bold text-xs flex items-center justify-center" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="w-8 h-8 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold text-xs flex items-center justify-center hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors">{{ $page }}</a>
                        @endif
                    @endforeach

                    @if($items->hasMorePages())
                        <a href="{{ $items->nextPageUrl() }}" class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-medium text-xs hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors">Berikutnya</a>
                    @else
                        <span class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 font-medium cursor-not-allowed text-xs">Berikutnya</span>
                    @endif
                </nav>
            </div>
        @else
            <div class="p-10 rounded-2xl bg-white dark:bg-slate-900 border border-dashed border-slate-300 dark:border-slate-700 text-center space-y-2">
                <div class="w-12 h-12 mx-auto rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                    <i data-lucide="history" class="w-6 h-6"></i>
                </div>
                <p class="text-sm font-bold text-slate-800 dark:text-slate-200">
                    {{ ($hasAnyMovements ?? false) ? 'Riwayat tidak ditemukan' : 'Belum ada riwayat stok' }}
                </p>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ ($hasAnyMovements ?? false)
                        ? 'Tidak ada pergerakan yang cocok dengan filter saat ini.'
                        : 'Pergerakan stok akan muncul di sini setelah ada penjualan atau pencatatan manual.' }}
                </p>
            </div>
        @endif

        @if($canManageStock ?? false)
            <x-stock.adjustment-modal :products="$products" />
        @endif

    </main>

    <script>
        function initStockHistoryPage() {
            const root = document.querySelector('main[data-stock-history-page="true"]');
            if (!root || root.dataset.stockHistoryInitialized === 'true') {
                return;
            }
            root.dataset.stockHistoryInitialized = 'true';

            const modal = document.getElementById('stockMovementModal');
            if (!modal) {
                if (window.lucide) window.lucide.createIcons();
                return;
            }

            const panels = modal.querySelectorAll('[data-stock-panel]');
            const tabs = modal.querySelectorAll('[data-stock-tab]');

            const setTab = (mode) => {
                panels.forEach((panel) => {
                    panel.classList.toggle('hidden', panel.dataset.stockPanel !== mode);
                });
                tabs.forEach((tab) => {
                    const active = tab.dataset.stockTab === mode;
                    tab.classList.toggle('bg-white', active);
                    tab.classList.toggle('dark:bg-slate-900', active);
                    tab.classList.toggle('text-slate-900', active);
                    tab.classList.toggle('dark:text-white', active);
                    tab.classList.toggle('shadow-xs', active);
                    tab.classList.toggle('text-slate-600', !active);
                    tab.classList.toggle('dark:text-slate-300', !active);
                });
            };

            const openModal = (mode) => {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                setTab(mode);
                if (window.lucide) window.lucide.createIcons();
                const firstField = modal.querySelector('[data-stock-panel="' + mode + '"] select, [data-stock-panel="' + mode + '"] input');
                if (firstField) firstField.focus();
            };

            const closeModal = () => {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            };

            tabs.forEach((tab) => {
                tab.onclick = () => setTab(tab.dataset.stockTab);
            });

            modal.querySelectorAll('[data-close-stock-movement]').forEach((button) => {
                button.onclick = closeModal;
            });

            document.getElementById('openAdjustmentBtn')?.addEventListener('click', () => openModal('adjustment'));
            document.getElementById('openReceiptBtn')?.addEventListener('click', () => openModal('receipt'));

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });

            const addLineBtn = document.getElementById('addReceiptLineBtn');
            const linesContainer = document.getElementById('receiptLines');
            const template = document.getElementById('receiptLineTemplate');

            if (addLineBtn && linesContainer && template) {
                addLineBtn.onclick = () => {
                    const index = linesContainer.querySelectorAll('.receipt-line').length;
                    if (index >= 100) return;

                    const clone = template.content.firstElementChild.cloneNode(true);
                    clone.querySelectorAll('select, input').forEach((field) => {
                        field.name = 'lines[' + index + '][' + (field.tagName === 'SELECT' ? 'product_id' : (field.type === 'number' && field.placeholder === 'HPP / unit' ? 'unit_cost' : 'quantity')) + ']';
                    });
                    linesContainer.appendChild(clone);
                    if (window.lucide) window.lucide.createIcons();
                };
            }

            const restoreMode = modal.dataset.restoreMode;
            if (restoreMode === 'adjustment' || restoreMode === 'receipt') {
                openModal(restoreMode);
            }

            if (window.lucide) window.lucide.createIcons();
        }

        initStockHistoryPage();

        if (!window.__stockHistoryListenersBound) {
            window.__stockHistoryListenersBound = true;
            document.addEventListener('DOMContentLoaded', initStockHistoryPage);
            document.addEventListener('livewire:navigated', initStockHistoryPage);
        }
    </script>
</x-layouts::app>
