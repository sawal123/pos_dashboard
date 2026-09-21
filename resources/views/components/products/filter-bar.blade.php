@props([
    'categories' => [],
    'statuses' => [],
    'currentFilters' => [],
    'activeTab' => 'products',
])

@php
    $cf = $currentFilters;
    $actionWording = match($activeTab) {
        'services' => 'Pengelolaan layanan dari dashboard belum tersedia.',
        'categories' => 'Pengelolaan kategori dari dashboard belum tersedia.',
        default => 'Pengelolaan produk dari dashboard belum tersedia.',
    };
    $btnLabel = match($activeTab) {
        'services' => 'Tambah Layanan',
        'categories' => 'Tambah Kategori',
        default => 'Tambah Produk',
    };
    $placeholder = match($activeTab) {
        'services' => 'Cari layanan atau SKU...',
        'categories' => 'Cari nama kategori...',
        default => 'Cari produk, SKU, atau barcode...',
    };
@endphp

<form
    method="GET"
    action="{{ route('products.index') }}"
    id="productsFilterForm"
    class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 shadow-xs space-y-3"
>
    <input type="hidden" name="tab" value="{{ $activeTab }}">

    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">

        {{-- 1. Search Bar --}}
        <div class="relative flex-1">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400 dark:text-slate-500">
                <i data-lucide="search" class="w-4 h-4"></i>
            </span>
            <input
                type="text"
                id="searchCatalogInput"
                name="q"
                value="{{ $cf['q'] ?? '' }}"
                placeholder="{{ $placeholder }}"
                class="w-full pl-10 pr-4 py-2 text-xs sm:text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/60 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors"
                autocomplete="off"
                onchange="this.form.submit()"
            >
        </div>

        {{-- 2. Filter Dropdowns Group --}}
        <div class="flex items-center gap-2 flex-wrap">

            {{-- Kategori Filter (Hanya untuk Products & Services) --}}
            @if($activeTab !== 'categories')
                <div class="min-w-[130px] flex-1 sm:flex-initial">
                    <select
                        id="filterCategory"
                        name="category_id"
                        onchange="this.form.submit()"
                        class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer"
                    >
                        <option value="all">Semua Kategori</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat['id'] }}" @selected((string)($cf['category_id'] ?? '') === (string)$cat['id'])>
                                {{ $cat['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Status Filter --}}
            <div class="min-w-[110px] flex-1 sm:flex-initial">
                <select
                    id="filterStatus"
                    name="status"
                    onchange="this.form.submit()"
                    class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer"
                >
                    <option value="all">Semua Status</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st['value'] }}" @selected(($cf['status'] ?? '') === $st['value'])>
                            {{ $st['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Status Stok Filter (Hanya relevan di tab produk) --}}
            @if($activeTab === 'products')
                <div id="stockStatusFilterWrapper" class="min-w-[120px] flex-1 sm:flex-initial">
                    <select
                        id="filterStockStatus"
                        name="stock_status"
                        onchange="this.form.submit()"
                        class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer"
                    >
                        <option value="all" @selected(($cf['stock_status'] ?? 'all') === 'all')>Semua Stok</option>
                        <option value="safe" @selected(($cf['stock_status'] ?? '') === 'safe')>Aman</option>
                        <option value="low" @selected(($cf['stock_status'] ?? '') === 'low')>Menipis</option>
                        <option value="empty" @selected(($cf['stock_status'] ?? '') === 'empty')>Habis</option>
                        <option value="negative" @selected(($cf['stock_status'] ?? '') === 'negative')>Minus</option>
                    </select>
                </div>
            @endif

            {{-- Reset Filter Button (Direct GET link) --}}
            <a
                href="{{ route('products.index', ['tab' => $activeTab]) }}"
                id="resetFilterBtn"
                class="px-3 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors flex items-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                title="Reset semua filter"
            >
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5 text-slate-400"></i>
                <span class="hidden sm:inline">Reset</span>
            </a>

            {{-- Action Button (Neutral non-fake placeholder) --}}
            <button
                type="button"
                id="addCatalogBtn"
                onclick="showToast('info', '{{ $actionWording }}')"
                class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition-colors flex items-center gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
            >
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span id="addCatalogBtnText">{{ $btnLabel }}</span>
            </button>
        </div>
    </div>
</form>
